using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.IO.Compression;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Xml.Linq;
using Microsoft.AspNetCore.Http;
using Microsoft.Data.Sqlite;
using SMBLibrary;
using SMBLibrary.Client;
using UglyToad.PdfPig;

namespace FunkenwerkOSLinux
{
    public sealed class DocumentSearchHitDto
    {
        public long Id { get; set; }
        public string RelPath { get; set; } = "";
        public string FullPath { get; set; } = "";
        public string Pillar { get; set; } = "";
        public string Subfolder { get; set; } = "";
        public string FileName { get; set; } = "";
        public string DocDate { get; set; } = "";
        public string Sender { get; set; } = "";
        public string Category { get; set; } = "";
        public string Summary { get; set; } = "";
        public string Snippet { get; set; } = "";
        public long SizeBytes { get; set; }
        public bool OcrUsed { get; set; }
    }

    public sealed class NasBackupEntryMeta
    {
        public long Size { get; set; }
        public long MtimeTicks { get; set; }
    }

    public sealed class NasBackupStatusDto
    {
        public bool Configured { get; set; }
        public bool Running { get; set; }
        public bool PendingCatchUp { get; set; }
        public string NasHost { get; set; } = "192.168.188.33";
        public string NasShare { get; set; } = "Backups";
        public string NasSubFolder { get; set; } = "Unterlagen/Meine_Daten_Backup";
        public string NasUsername { get; set; } = "alina";
        public string LastBackupAt { get; set; } = "Noch kein Lauf";
        public string LastBackupResult { get; set; } = "Bereit";
        public int IndexedDocumentsCount { get; set; }
        public int ManifestFilesCount { get; set; }
    }

    public static class DocumentArchiveAndNasEngine
    {
        private static readonly HttpClient s_ocrHttp = new()
        {
            BaseAddress = new Uri("http://127.0.0.1:17701"),
            Timeout = TimeSpan.FromSeconds(35)
        };

        private static readonly SemaphoreSlim s_dbInitLock = new(1, 1);
        private static readonly SemaphoreSlim s_trichterLock = new(1, 1);
        private static readonly SemaphoreSlim s_indexLock = new(1, 1);
        private static readonly SemaphoreSlim s_backupLock = new(1, 1);

        private static bool s_dbInitialized;
        private static bool s_daemonStarted;
        private static volatile bool s_backupRunning;
        private static FileSystemWatcher? s_trichterWatcher;
        private static readonly ConcurrentQueue<string> s_recentTrichterLog = new();

        public static string IndexDbPath => Path.Combine(LinuxVaultAndStorage.DataDir, "documents_index.db");
        public static string BackupManifestPath => Path.Combine(LinuxVaultAndStorage.DataDir, "nas_backup_manifest.json");

        private static string ConnectionString => $"Data Source={IndexDbPath};Mode=ReadWriteCreate;Cache=Shared";

        // =====================================================================
        // 1.EXTENDED 8-PILLAR SUBFOLDER TAXONOMY
        // =====================================================================
        public static void EnsureDetailedSubfolderTaxonomy()
        {
            string root = LinuxVaultAndStorage.MeineDatenRoot;
            string[] standardSubfolders =
            {
                "00_Eingang_Trichter/_Manuell_Pruefen",
                "01_Unternehmen_Recht/Gewerbe_Vertraege",
                "01_Unternehmen_Recht/Versicherungen_Gewerbe",
                "02_Buchhaltung_Steuern",
                "03_Manufaktur_Produkte",
                "04_Bestellungen_Versand",
                "05_Marketing_Content",
                "06_Lieferanten_Einkauf",
                "07_IT_Funkenwerk/Keepass",
                "07_IT_Funkenwerk/System_Konfig",
                "08_Privat_Archiv/Ausweise_Urkunden",
                "08_Privat_Archiv/Banken_Finanzen",
                "08_Privat_Archiv/Behoerden_Recht",
                "08_Privat_Archiv/Beruf_Arbeitgeber",
                "08_Privat_Archiv/Erbe_Vorsorge",
                "08_Privat_Archiv/Gesundheit",
                "08_Privat_Archiv/Haus_Immobilien",
                "08_Privat_Archiv/Rechnungen_Privat",
                "08_Privat_Archiv/Steuern_Privat",
                "08_Privat_Archiv/Versicherungen",
                "08_Privat_Archiv/Vertraege_Alltag"
            };

            foreach (string sub in standardSubfolders)
            {
                Directory.CreateDirectory(Path.Combine(root, sub.Replace('/', Path.DirectorySeparatorChar)));
            }
        }

        // =====================================================================
        // 2. SQLITE FTS5 DATABASE INITIALIZATION & QUERY ENGINE (< 3 ms)
        // =====================================================================
        public static void EnsureDatabaseInitialized()
        {
            if (s_dbInitialized && File.Exists(IndexDbPath)) return;

            s_dbInitLock.Wait();
            try
            {
                if (s_dbInitialized && File.Exists(IndexDbPath)) return;

                EnsureDetailedSubfolderTaxonomy();

                using var conn = new SqliteConnection(ConnectionString);
                conn.Open();

                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"
                    PRAGMA journal_mode = WAL;
                    PRAGMA synchronous = NORMAL;
                    PRAGMA temp_store = MEMORY;

                    CREATE TABLE IF NOT EXISTS documents (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        rel_path TEXT NOT NULL UNIQUE,
                        pillar TEXT NOT NULL,
                        subfolder TEXT NOT NULL,
                        file_name TEXT NOT NULL,
                        extension TEXT NOT NULL,
                        size_bytes INTEGER NOT NULL,
                        mtime_ticks INTEGER NOT NULL,
                        doc_date TEXT NOT NULL,
                        sender TEXT NOT NULL,
                        category TEXT NOT NULL,
                        summary TEXT NOT NULL,
                        full_text TEXT NOT NULL,
                        ocr_used INTEGER NOT NULL DEFAULT 0,
                        indexed_at TEXT NOT NULL
                    );

                    CREATE INDEX IF NOT EXISTS idx_documents_pillar ON documents(pillar);
                    CREATE INDEX IF NOT EXISTS idx_documents_doc_date ON documents(doc_date DESC);

                    CREATE VIRTUAL TABLE IF NOT EXISTS documents_fts USING fts5(
                        rel_path,
                        file_name,
                        sender,
                        category,
                        doc_date,
                        summary,
                        full_text,
                        content='documents',
                        content_rowid='id',
                        tokenize='unicode61 remove_diacritics 2'
                    );

                    CREATE TRIGGER IF NOT EXISTS documents_ai AFTER INSERT ON documents BEGIN
                        INSERT INTO documents_fts(rowid, rel_path, file_name, sender, category, doc_date, summary, full_text)
                        VALUES (new.id, new.rel_path, new.file_name, new.sender, new.category, new.doc_date, new.summary, new.full_text);
                    END;

                    CREATE TRIGGER IF NOT EXISTS documents_ad AFTER DELETE ON documents BEGIN
                        INSERT INTO documents_fts(documents_fts, rowid, rel_path, file_name, sender, category, doc_date, summary, full_text)
                        VALUES ('delete', old.id, old.rel_path, old.file_name, old.sender, old.category, old.doc_date, old.summary, old.full_text);
                    END;

                    CREATE TRIGGER IF NOT EXISTS documents_au AFTER UPDATE ON documents BEGIN
                        INSERT INTO documents_fts(documents_fts, rowid, rel_path, file_name, sender, category, doc_date, summary, full_text)
                        VALUES ('delete', old.id, old.rel_path, old.file_name, old.sender, old.category, old.doc_date, old.summary, old.full_text);
                        INSERT INTO documents_fts(rowid, rel_path, file_name, sender, category, doc_date, summary, full_text)
                        VALUES (new.id, new.rel_path, new.file_name, new.sender, new.category, new.doc_date, new.summary, new.full_text);
                    END;
                ";
                cmd.ExecuteNonQuery();

                LinuxVaultAndStorage.TryRestrictFilePermissions(IndexDbPath);
                s_dbInitialized = true;
            }
            finally
            {
                s_dbInitLock.Release();
            }
        }

        public static int GetIndexedDocumentCount()
        {
            try
            {
                EnsureDatabaseInitialized();
                using var conn = new SqliteConnection(ConnectionString);
                conn.Open();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT COUNT(*) FROM documents;";
                return Convert.ToInt32(cmd.ExecuteScalar() ?? 0);
            }
            catch
            {
                return 0;
            }
        }

        public static List<DocumentSearchHitDto> SearchDocuments(string? rawQuery, string? pillarFilter = null, int limit = 12)
        {
            EnsureDatabaseInitialized();
            int safeLimit = Math.Clamp(limit, 1, 40);
            string root = LinuxVaultAndStorage.MeineDatenRoot;
            var results = new List<DocumentSearchHitDto>();

            using var conn = new SqliteConnection(ConnectionString);
            conn.Open();

            string cleaned = (rawQuery ?? "").Trim();
            var tokens = Regex.Matches(cleaned, @"[\p{L}\p{N}]{2,}")
                .Select(m => m.Value)
                .Distinct(StringComparer.OrdinalIgnoreCase)
                .Take(8)
                .ToList();

            if (tokens.Count > 0)
            {
                // First try AND prefix match; if 0 hits and >1 token, fallback to OR match
                foreach (string joinOp in tokens.Count > 1 ? new[] { " AND ", " OR " } : new[] { " AND " })
                {
                    string ftsExpr = string.Join(joinOp, tokens.Select(t => $"\"{t.Replace("\"", "")}\"*"));
                    using var cmd = conn.CreateCommand();
                    string pillarClause = string.IsNullOrWhiteSpace(pillarFilter) ? "" : " AND d.pillar = $pillar ";
                    cmd.CommandText = $@"
                        SELECT d.id, d.rel_path, d.pillar, d.subfolder, d.file_name, d.doc_date,
                               d.sender, d.category, d.summary, d.size_bytes, d.ocr_used,
                               snippet(documents_fts, 6, '[', ']', ' ... ', 22) AS snip
                        FROM documents_fts
                        JOIN documents d ON d.id = documents_fts.rowid
                        WHERE documents_fts MATCH $q {pillarClause}
                        ORDER BY bm25(documents_fts, 3.0, 4.0, 3.5, 2.5, 1.5, 2.5, 1.0), d.doc_date DESC
                        LIMIT $lim;
                    ";
                    cmd.Parameters.AddWithValue("$q", ftsExpr);
                    cmd.Parameters.AddWithValue("$lim", safeLimit);
                    if (!string.IsNullOrWhiteSpace(pillarFilter))
                        cmd.Parameters.AddWithValue("$pillar", pillarFilter.Trim());

                    using var rdr = cmd.ExecuteReader();
                    while (rdr.Read())
                    {
                        string rel = rdr.GetString(1);
                        results.Add(new DocumentSearchHitDto
                        {
                            Id = rdr.GetInt64(0),
                            RelPath = rel,
                            FullPath = Path.Combine(root, rel.Replace('/', Path.DirectorySeparatorChar)),
                            Pillar = rdr.GetString(2),
                            Subfolder = rdr.GetString(3),
                            FileName = rdr.GetString(4),
                            DocDate = rdr.GetString(5),
                            Sender = rdr.GetString(6),
                            Category = rdr.GetString(7),
                            Summary = rdr.GetString(8),
                            SizeBytes = rdr.GetInt64(9),
                            OcrUsed = rdr.GetInt32(10) != 0,
                            Snippet = rdr.IsDBNull(11) ? "" : rdr.GetString(11)
                        });
                    }

                    if (results.Count > 0) break;
                }
            }
            else
            {
                using var cmd = conn.CreateCommand();
                string pillarClause = string.IsNullOrWhiteSpace(pillarFilter) ? "" : " WHERE pillar = $pillar ";
                cmd.CommandText = $@"
                    SELECT id, rel_path, pillar, subfolder, file_name, doc_date,
                           sender, category, summary, size_bytes, ocr_used
                    FROM documents
                    {pillarClause}
                    ORDER BY indexed_at DESC, id DESC
                    LIMIT $lim;
                ";
                cmd.Parameters.AddWithValue("$lim", safeLimit);
                if (!string.IsNullOrWhiteSpace(pillarFilter))
                    cmd.Parameters.AddWithValue("$pillar", pillarFilter.Trim());

                using var rdr = cmd.ExecuteReader();
                while (rdr.Read())
                {
                    string rel = rdr.GetString(1);
                    results.Add(new DocumentSearchHitDto
                    {
                        Id = rdr.GetInt64(0),
                        RelPath = rel,
                        FullPath = Path.Combine(root, rel.Replace('/', Path.DirectorySeparatorChar)),
                        Pillar = rdr.GetString(2),
                        Subfolder = rdr.GetString(3),
                        FileName = rdr.GetString(4),
                        DocDate = rdr.GetString(5),
                        Sender = rdr.GetString(6),
                        Category = rdr.GetString(7),
                        Summary = rdr.GetString(8),
                        SizeBytes = rdr.GetInt64(9),
                        OcrUsed = rdr.GetInt32(10) != 0,
                        Snippet = rdr.GetString(8)
                    });
                }
            }

            return results;
        }

        public static (bool Found, DocumentSearchHitDto? Meta, string FullText) ReadIndexedDocument(string relPathOrQuery)
        {
            EnsureDatabaseInitialized();
            string q = (relPathOrQuery ?? "").Trim().Replace('\\', '/').TrimStart('/');
            if (string.IsNullOrWhiteSpace(q)) return (false, null, "");

            string root = LinuxVaultAndStorage.MeineDatenRoot;
            using var conn = new SqliteConnection(ConnectionString);
            conn.Open();

            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"
                SELECT id, rel_path, pillar, subfolder, file_name, doc_date,
                       sender, category, summary, size_bytes, ocr_used, full_text
                FROM documents
                WHERE rel_path = $exact OR file_name = $exact OR rel_path LIKE $like
                ORDER BY CASE WHEN rel_path = $exact THEN 0 WHEN file_name = $exact THEN 1 ELSE 2 END, id DESC
                LIMIT 1;
            ";
            cmd.Parameters.AddWithValue("$exact", q);
            cmd.Parameters.AddWithValue("$like", "%" + q + "%");

            using var rdr = cmd.ExecuteReader();
            if (rdr.Read())
            {
                string rel = rdr.GetString(1);
                var dto = new DocumentSearchHitDto
                {
                    Id = rdr.GetInt64(0),
                    RelPath = rel,
                    FullPath = Path.Combine(root, rel.Replace('/', Path.DirectorySeparatorChar)),
                    Pillar = rdr.GetString(2),
                    Subfolder = rdr.GetString(3),
                    FileName = rdr.GetString(4),
                    DocDate = rdr.GetString(5),
                    Sender = rdr.GetString(6),
                    Category = rdr.GetString(7),
                    Summary = rdr.GetString(8),
                    SizeBytes = rdr.GetInt64(9),
                    OcrUsed = rdr.GetInt32(10) != 0
                };
                string text = rdr.GetString(11);
                return (true, dto, text);
            }

            var fallback = SearchDocuments(q, null, 1).FirstOrDefault();
            if (fallback != null)
            {
                return ReadIndexedDocument(fallback.RelPath);
            }

            return (false, null, "");
        }

        public static List<string> GetRecentTrichterEvents()
        {
            return s_recentTrichterLog.Reverse().Take(15).ToList();
        }

        // =====================================================================
        // 3. DAEMON STARTUP: WATCHER + INCREMENTAL INDEXER + NIGHTLY CATCH-UP
        // =====================================================================
        public static void EnsureDaemonStarted()
        {
            if (s_daemonStarted) return;
            s_daemonStarted = true;

            EnsureDatabaseInitialized();

            try
            {
                string trichterDir = Path.Combine(LinuxVaultAndStorage.MeineDatenRoot, "00_Eingang_Trichter");
                Directory.CreateDirectory(trichterDir);
                s_trichterWatcher = new FileSystemWatcher(trichterDir)
                {
                    IncludeSubdirectories = false,
                    NotifyFilter = NotifyFilters.FileName | NotifyFilters.Size | NotifyFilters.LastWrite,
                    EnableRaisingEvents = true
                };
                FileSystemEventHandler onFs = (_, _) => _ = TriggerDelayedTrichterScanAsync(1800);
                RenamedEventHandler onRen = (_, _) => _ = TriggerDelayedTrichterScanAsync(1200);
                s_trichterWatcher.Created += onFs;
                s_trichterWatcher.Changed += onFs;
                s_trichterWatcher.Renamed += onRen;
            }
            catch { }

            // Background maintenance loop (initial indexing + periodic Trichter check + 08:02 catch-up / 23:30 nightly backup)
            _ = Task.Run(async () =>
            {
                await Task.Delay(3500).ConfigureAwait(false);
                try { await ProcessTrichterDirectoryAsync().ConfigureAwait(false); } catch { }
                try { await SyncFullArchiveIndexIncrementalAsync(allowImageOcrPass: false).ConfigureAwait(false); } catch { }
                // Gentle second pass for scanned image/PDF OCR without starving CPU
                try { await SyncFullArchiveIndexIncrementalAsync(allowImageOcrPass: true, maxOcrFilesPerPass: 60).ConfigureAwait(false); } catch { }

                int lastNightlyBackupDayOfYear = -1;
                while (true)
                {
                    await Task.Delay(12000).ConfigureAwait(false);
                    try { await ProcessTrichterDirectoryAsync().ConfigureAwait(false); } catch { }
                    try { await SyncFullArchiveIndexIncrementalAsync(allowImageOcrPass: true, maxOcrFilesPerPass: 8).ConfigureAwait(false); } catch { }

                    try
                    {
                        var st = LinuxVaultAndStorage.LoadSettings();
                        DateTime nowLocal = GetBerlinNow();
                        // Morning catch-up if pre-sleep backup missed NAS the night before
                        if (st.NasBackupPendingCatchUp && nowLocal.Hour >= 8)
                        {
                            await RunDeltaNasBackupAsync("Morgen-Nachholung").ConfigureAwait(false);
                        }
                        // Nightly safety backup at 23:30 if PC stays awake
                        else if (nowLocal.Hour == 23 && nowLocal.Minute >= 30 && lastNightlyBackupDayOfYear != nowLocal.DayOfYear)
                        {
                            lastNightlyBackupDayOfYear = nowLocal.DayOfYear;
                            await RunDeltaNasBackupAsync("Abend-Automatik").ConfigureAwait(false);
                        }
                    }
                    catch { }
                }
            });
        }

        private static async Task TriggerDelayedTrichterScanAsync(int delayMs)
        {
            await Task.Delay(delayMs).ConfigureAwait(false);
            await ProcessTrichterDirectoryAsync().ConfigureAwait(false);
        }

        // =====================================================================
        // 4. REAL-TIME TRICHTER PROCESSOR (OCR + RENAME + SORT + INDEX)
        // =====================================================================
        public static async Task<int> ProcessTrichterDirectoryAsync(string? customTitleHint = null)
        {
            if (!await s_trichterLock.WaitAsync(0).ConfigureAwait(false))
                return 0;

            int processed = 0;
            try
            {
                EnsureDatabaseInitialized();
                string root = LinuxVaultAndStorage.MeineDatenRoot;
                string trichter = Path.Combine(root, "00_Eingang_Trichter");
                if (!Directory.Exists(trichter)) return 0;

                var files = new DirectoryInfo(trichter).GetFiles()
                    .Where(f => !f.Name.StartsWith('.') &&
                                !f.Name.EndsWith(".tmp", StringComparison.OrdinalIgnoreCase) &&
                                !f.Name.EndsWith(".part", StringComparison.OrdinalIgnoreCase) &&
                                !f.Name.Equals("Thumbs.db", StringComparison.OrdinalIgnoreCase))
                    .OrderBy(f => f.LastWriteTimeUtc)
                    .ToList();

                foreach (var f in files)
                {
                    if (!IsFileStableForProcessing(f)) continue;

                    var (text, ocrUsed) = await ExtractDocumentTextAsync(f.FullName, allowOcr: true).ConfigureAwait(false);
                    var meta = ClassifyAndBuildMetadata(f, text, isFromTrichter: true, customTitleHint: customTitleHint);

                    string destFolder = Path.Combine(root, meta.TargetRelFolder.Replace('/', Path.DirectorySeparatorChar));
                    Directory.CreateDirectory(destFolder);

                    string finalFileName = ShouldAutoRenameTrichterFile(f.Name) && !string.IsNullOrWhiteSpace(meta.SuggestedFileName)
                        ? meta.SuggestedFileName
                        : f.Name;

                    string finalPath = GetUniquePath(Path.Combine(destFolder, finalFileName));
                    File.Move(f.FullName, finalPath);

                    var movedInfo = new FileInfo(finalPath);
                    UpsertDocumentInIndex(movedInfo, text, ocrUsed, meta.DocDate, meta.Sender, meta.Category, meta.Summary);

                    string relDest = Path.GetRelativePath(root, finalPath).Replace('\\', '/');
                    PushTrichterEvent($"{DateTime.Now:dd.MM. HH:mm} · {movedInfo.Name} -> {meta.TargetRelFolder}");
                    processed++;
                }
            }
            catch { }
            finally
            {
                s_trichterLock.Release();
            }

            return processed;
        }

        private static bool IsFileStableForProcessing(FileInfo fi)
        {
            try
            {
                fi.Refresh();
                if (!fi.Exists || fi.Length <= 0) return false;
                if ((DateTime.UtcNow - fi.LastWriteTimeUtc).TotalMilliseconds < 900) return false;
                using var fs = new FileStream(fi.FullName, FileMode.Open, FileAccess.Read, FileShare.Read);
                return fs.Length > 0;
            }
            catch
            {
                return false;
            }
        }

        private static void PushTrichterEvent(string msg)
        {
            s_recentTrichterLog.Enqueue(msg);
            while (s_recentTrichterLog.Count > 30 && s_recentTrichterLog.TryDequeue(out _)) { }
        }

        // =====================================================================
        // 5. SERVER RELAY PULLER (ANDROID / MOBILE SCANS -> 00_EINGANG_TRICHTER)
        // =====================================================================
        public static async Task<int> PullPendingServerTrichterRelayAsync(HttpClient http, string baseUrl, string bearerToken)
        {
            if (string.IsNullOrWhiteSpace(bearerToken)) return 0;

            int imported = 0;
            try
            {
                string apiBase = (baseUrl ?? "").TrimEnd('/');
                if (!apiBase.EndsWith("/api", StringComparison.OrdinalIgnoreCase))
                    apiBase += "/api";

                using var listReq = new HttpRequestMessage(HttpMethod.Get, $"{apiBase}/shop/minipc-trichter/pending");
                listReq.Headers.Authorization = new System.Net.Http.Headers.AuthenticationHeaderValue("Bearer", bearerToken);
                using var listResp = await http.SendAsync(listReq).ConfigureAwait(false);
                if (!listResp.IsSuccessStatusCode) return 0;

                string listJson = await listResp.Content.ReadAsStringAsync().ConfigureAwait(false);
                using var doc = JsonDocument.Parse(listJson);
                if (!doc.RootElement.TryGetProperty("items", out var itemsEl) || itemsEl.ValueKind != JsonValueKind.Array)
                    return 0;

                string trichterDir = Path.Combine(LinuxVaultAndStorage.MeineDatenRoot, "00_Eingang_Trichter");
                Directory.CreateDirectory(trichterDir);

                foreach (var item in itemsEl.EnumerateArray())
                {
                    string id = item.TryGetProperty("id", out var idEl) ? (idEl.GetString() ?? "") : "";
                    if (string.IsNullOrWhiteSpace(id)) continue;

                    string origName = item.TryGetProperty("file_name", out var fnEl) ? (fnEl.GetString() ?? $"Trichter_{id}.pdf") : $"Trichter_{id}.pdf";
                    string customTitle = item.TryGetProperty("custom_title", out var ctEl) ? (ctEl.GetString() ?? "") : "";
                    string expectedSha = item.TryGetProperty("sha256", out var shEl) ? (shEl.GetString() ?? "") : "";

                    using var pullReq = new HttpRequestMessage(HttpMethod.Get, $"{apiBase}/shop/minipc-trichter/pull/{Uri.EscapeDataString(id)}");
                    pullReq.Headers.Authorization = new System.Net.Http.Headers.AuthenticationHeaderValue("Bearer", bearerToken);
                    using var pullResp = await http.SendAsync(pullReq).ConfigureAwait(false);
                    if (!pullResp.IsSuccessStatusCode) continue;

                    byte[] bytes = await pullResp.Content.ReadAsByteArrayAsync().ConfigureAwait(false);
                    if (bytes.Length == 0) continue;

                    if (!string.IsNullOrWhiteSpace(expectedSha))
                    {
                        string actualSha = Convert.ToHexString(SHA256.HashData(bytes)).ToLowerInvariant();
                        if (!string.Equals(actualSha, expectedSha.ToLowerInvariant(), StringComparison.Ordinal))
                            continue;
                    }

                    string safeLeaf = SanitizeSafeFileName(origName);
                    string destPath = GetUniquePath(Path.Combine(trichterDir, safeLeaf));
                    string tmpPath = destPath + ".tmp";
                    await File.WriteAllBytesAsync(tmpPath, bytes).ConfigureAwait(false);
                    File.Move(tmpPath, destPath, overwrite: true);
                    // Backdate mtime by 2s so stability guard processes immediately
                    try { File.SetLastWriteTimeUtc(destPath, DateTime.UtcNow.AddSeconds(-2)); } catch { }

                    // Immediately ACK & wipe from web server!
                    using var ackReq = new HttpRequestMessage(HttpMethod.Delete, $"{apiBase}/shop/minipc-trichter/ack/{Uri.EscapeDataString(id)}");
                    ackReq.Headers.Authorization = new System.Net.Http.Headers.AuthenticationHeaderValue("Bearer", bearerToken);
                    using var _ = await http.SendAsync(ackReq).ConfigureAwait(false);

                    await ProcessTrichterDirectoryAsync(customTitle).ConfigureAwait(false);
                    imported++;
                }
            }
            catch { }

            return imported;
        }

        // =====================================================================
        // 6. INCREMENTAL ARCHIVE INDEXER (INDEXES ALL 3,671+ FILES IN SECONDS)
        // =====================================================================
        public static async Task<(int AddedOrUpdated, int Removed, int TotalIndexed)> SyncFullArchiveIndexIncrementalAsync(
            bool allowImageOcrPass = false,
            int maxOcrFilesPerPass = 50)
        {
            if (!await s_indexLock.WaitAsync(0).ConfigureAwait(false))
                return (0, 0, GetIndexedDocumentCount());

            int upserted = 0;
            int removed = 0;
            int ocrDone = 0;

            try
            {
                EnsureDatabaseInitialized();
                string root = LinuxVaultAndStorage.MeineDatenRoot;

                var existingMap = new Dictionary<string, (long Size, long Mtime, int OcrUsed, int TextLen)>(StringComparer.Ordinal);
                using (var conn = new SqliteConnection(ConnectionString))
                {
                    conn.Open();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "SELECT rel_path, size_bytes, mtime_ticks, ocr_used, length(full_text) FROM documents;";
                    using var rdr = cmd.ExecuteReader();
                    while (rdr.Read())
                    {
                        existingMap[rdr.GetString(0)] = (rdr.GetInt64(1), rdr.GetInt64(2), rdr.GetInt32(3), rdr.GetInt32(4));
                    }
                }

                var seenRelPaths = new HashSet<string>(StringComparer.Ordinal);
                var allFiles = EnumerateArchiveFiles(root);

                foreach (var fi in allFiles)
                {
                    string rel = Path.GetRelativePath(root, fi.FullName).Replace('\\', '/');
                    if (rel.StartsWith("00_Eingang_Trichter/", StringComparison.Ordinal) &&
                        !rel.StartsWith("00_Eingang_Trichter/_Manuell_Pruefen/", StringComparison.Ordinal))
                    {
                        continue;
                    }

                    seenRelPaths.Add(rel);
                    long mtimeTicks = fi.LastWriteTimeUtc.Ticks;

                    bool needsIndex = true;
                    bool wantOcrUpgrade = false;
                    if (existingMap.TryGetValue(rel, out var ex))
                    {
                        if (ex.Size == fi.Length && ex.Mtime == mtimeTicks)
                        {
                            needsIndex = false;
                            string ext = fi.Extension.ToLowerInvariant();
                            bool isOcrCandidate = ext is ".pdf" or ".jpg" or ".jpeg" or ".png" or ".webp";
                            if (allowImageOcrPass && isOcrCandidate && ex.OcrUsed == 0 && ex.TextLen < 35 && ocrDone < maxOcrFilesPerPass && fi.Length < 18 * 1024 * 1024)
                            {
                                wantOcrUpgrade = true;
                            }
                        }
                    }

                    if (!needsIndex && !wantOcrUpgrade) continue;

                    bool doOcrNow = wantOcrUpgrade || (allowImageOcrPass && ocrDone < maxOcrFilesPerPass);
                    var (text, ocrUsed) = await ExtractDocumentTextAsync(fi.FullName, allowOcr: doOcrNow).ConfigureAwait(false);
                    if (ocrUsed) ocrDone++;
                    else if (wantOcrUpgrade) ocrUsed = true; // mark checked so empty graphic isn't re-OCRed forever

                    var meta = ClassifyAndBuildMetadata(fi, text, isFromTrichter: false, customTitleHint: null);
                    UpsertDocumentInIndex(fi, text, ocrUsed, meta.DocDate, meta.Sender, meta.Category, meta.Summary);
                    upserted++;
                }

                // Prune deleted files from index
                var stale = existingMap.Keys.Where(k => !seenRelPaths.Contains(k)).ToList();
                if (stale.Count > 0)
                {
                    using var conn = new SqliteConnection(ConnectionString);
                    conn.Open();
                    using var tx = conn.BeginTransaction();
                    using var delCmd = conn.CreateCommand();
                    delCmd.Transaction = tx;
                    delCmd.CommandText = "DELETE FROM documents WHERE rel_path = $p;";
                    var pParam = delCmd.Parameters.Add("$p", SqliteType.Text);
                    foreach (string s in stale)
                    {
                        pParam.Value = s;
                        delCmd.ExecuteNonQuery();
                        removed++;
                    }
                    tx.Commit();
                }
            }
            catch { }
            finally
            {
                s_indexLock.Release();
            }

            return (upserted, removed, GetIndexedDocumentCount());
        }

        private static List<FileInfo> EnumerateArchiveFiles(string root)
        {
            var list = new List<FileInfo>(4096);
            var stack = new Stack<DirectoryInfo>();
            stack.Push(new DirectoryInfo(root));

            while (stack.Count > 0)
            {
                var dir = stack.Pop();
                try
                {
                    foreach (var sub in dir.GetDirectories())
                    {
                        if (sub.Name.StartsWith('.') || sub.Name.Equals("#recycle", StringComparison.OrdinalIgnoreCase))
                            continue;
                        stack.Push(sub);
                    }
                    foreach (var f in dir.GetFiles())
                    {
                        if (f.Name.StartsWith('.') ||
                            f.Name.Equals("Thumbs.db", StringComparison.OrdinalIgnoreCase) ||
                            f.Name.EndsWith(".tmp", StringComparison.OrdinalIgnoreCase))
                            continue;
                        list.Add(f);
                    }
                }
                catch { }
            }
            return list;
        }

        private static void UpsertDocumentInIndex(
            FileInfo fi,
            string rawText,
            bool ocrUsed,
            string docDate,
            string sender,
            string category,
            string summary)
        {
            string root = LinuxVaultAndStorage.MeineDatenRoot;
            string rel = Path.GetRelativePath(root, fi.FullName).Replace('\\', '/');
            string[] segs = rel.Split('/', StringSplitOptions.RemoveEmptyEntries);
            string pillar = segs.Length > 0 ? segs[0] : "08_Privat_Archiv";
            string subfolder = segs.Length > 2 ? string.Join("/", segs.Skip(1).Take(segs.Length - 2)) : "";

            string trimmedText = rawText ?? "";
            if (trimmedText.Length > 40000) trimmedText = trimmedText.Substring(0, 40000);

            using var conn = new SqliteConnection(ConnectionString);
            conn.Open();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"
                INSERT INTO documents (
                    rel_path, pillar, subfolder, file_name, extension,
                    size_bytes, mtime_ticks, doc_date, sender, category,
                    summary, full_text, ocr_used, indexed_at
                ) VALUES (
                    $rel, $pillar, $sub, $fn, $ext,
                    $sz, $mt, $dt, $snd, $cat,
                    $sum, $txt, $ocr, $now
                )
                ON CONFLICT(rel_path) DO UPDATE SET
                    pillar = excluded.pillar,
                    subfolder = excluded.subfolder,
                    file_name = excluded.file_name,
                    extension = excluded.extension,
                    size_bytes = excluded.size_bytes,
                    mtime_ticks = excluded.mtime_ticks,
                    doc_date = excluded.doc_date,
                    sender = excluded.sender,
                    category = excluded.category,
                    summary = excluded.summary,
                    full_text = excluded.full_text,
                    ocr_used = CASE WHEN excluded.ocr_used = 1 THEN 1 ELSE documents.ocr_used END,
                    indexed_at = excluded.indexed_at;
            ";
            cmd.Parameters.AddWithValue("$rel", rel);
            cmd.Parameters.AddWithValue("$pillar", pillar);
            cmd.Parameters.AddWithValue("$sub", subfolder);
            cmd.Parameters.AddWithValue("$fn", fi.Name);
            cmd.Parameters.AddWithValue("$ext", fi.Extension.ToLowerInvariant());
            cmd.Parameters.AddWithValue("$sz", fi.Length);
            cmd.Parameters.AddWithValue("$mt", fi.LastWriteTimeUtc.Ticks);
            cmd.Parameters.AddWithValue("$dt", docDate);
            cmd.Parameters.AddWithValue("$snd", sender);
            cmd.Parameters.AddWithValue("$cat", category);
            cmd.Parameters.AddWithValue("$sum", summary);
            cmd.Parameters.AddWithValue("$txt", trimmedText);
            cmd.Parameters.AddWithValue("$ocr", ocrUsed ? 1 : 0);
            cmd.Parameters.AddWithValue("$now", DateTime.UtcNow.ToString("O"));
            cmd.ExecuteNonQuery();
        }

        // =====================================================================
        // 7. TEXT EXTRACTION (PDFPIG + OFFICE XML + OFFLINE RAPIDOCR)
        // =====================================================================
        public static async Task<(string Text, bool OcrUsed)> ExtractDocumentTextAsync(string fullPath, bool allowOcr)
        {
            string ext = Path.GetExtension(fullPath).ToLowerInvariant();
            try
            {
                if (ext == ".pdf")
                {
                    var sb = new StringBuilder();
                    try
                    {
                        using var pdf = PdfDocument.Open(fullPath);
                        int maxPages = Math.Min(pdf.NumberOfPages, 25);
                        for (int i = 1; i <= maxPages; i++)
                        {
                            string pt = pdf.GetPage(i).Text;
                            if (!string.IsNullOrWhiteSpace(pt))
                            {
                                sb.AppendLine(pt);
                            }
                        }
                    }
                    catch { }

                    string extracted = NormalizeWhitespace(sb.ToString());
                    if (extracted.Length >= 35 || !allowOcr)
                        return (extracted, false);

                    var ocrRes = await CallLocalSenseOcrAsync(fullPath, maxPages: 5).ConfigureAwait(false);
                    if (!string.IsNullOrWhiteSpace(ocrRes))
                        return (NormalizeWhitespace(ocrRes), true);

                    return (extracted, false);
                }

                if (ext is ".txt" or ".md" or ".csv" or ".json" or ".xml" or ".html" or ".htm" or ".eml" or ".ini" or ".log")
                {
                    var fi = new FileInfo(fullPath);
                    if (fi.Length > 2 * 1024 * 1024) return ("", false);
                    string raw = await File.ReadAllTextAsync(fullPath).ConfigureAwait(false);
                    return (NormalizeWhitespace(raw), false);
                }

                if (ext is ".docx" or ".odt" or ".xlsx")
                {
                    return (ExtractOfficeZipXmlText(fullPath, ext), false);
                }

                if (allowOcr && ext is ".jpg" or ".jpeg" or ".png" or ".webp" or ".bmp" or ".tif" or ".tiff")
                {
                    var fi = new FileInfo(fullPath);
                    if (fi.Length <= 15 * 1024 * 1024)
                    {
                        string ocrTxt = await CallLocalSenseOcrAsync(fullPath, maxPages: 1).ConfigureAwait(false);
                        return (NormalizeWhitespace(ocrTxt), !string.IsNullOrWhiteSpace(ocrTxt));
                    }
                }
            }
            catch { }

            return ("", false);
        }

        private static string ExtractOfficeZipXmlText(string fullPath, string ext)
        {
            try
            {
                using var archive = ZipFile.OpenRead(fullPath);
                string entryName = ext == ".docx"
                    ? "word/document.xml"
                    : (ext == ".odt" ? "content.xml" : "xl/sharedStrings.xml");
                var entry = archive.GetEntry(entryName);
                if (entry == null) return "";
                using var sr = new StreamReader(entry.Open(), Encoding.UTF8);
                string xml = sr.ReadToEnd();
                string stripped = Regex.Replace(xml, "<[^>]+>", " ");
                return NormalizeWhitespace(WebUtility.HtmlDecode(stripped));
            }
            catch
            {
                return "";
            }
        }

        private static async Task<string> CallLocalSenseOcrAsync(string fullPath, int maxPages)
        {
            try
            {
                string payload = JsonSerializer.Serialize(new { filePath = fullPath, maxPages });
                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var resp = await s_ocrHttp.PostAsync("/ocr", content).ConfigureAwait(false);
                if (!resp.IsSuccessStatusCode) return "";
                string json = await resp.Content.ReadAsStringAsync().ConfigureAwait(false);
                using var doc = JsonDocument.Parse(json);
                if (doc.RootElement.TryGetProperty("ok", out var okEl) && okEl.GetBoolean() &&
                    doc.RootElement.TryGetProperty("text", out var txtEl))
                {
                    return txtEl.GetString() ?? "";
                }
            }
            catch { }
            return "";
        }

        // =====================================================================
        // 8. GERMAN DOCUMENT CLASSIFIER (8 PILLARS + SENDER + DATE + SUMMARY)
        // =====================================================================
        public sealed class DocumentClassificationResult
        {
            public string TargetRelFolder { get; set; } = "08_Privat_Archiv/Behoerden_Recht";
            public string DocDate { get; set; } = "";
            public string Sender { get; set; } = "";
            public string Category { get; set; } = "";
            public string Summary { get; set; } = "";
            public string SuggestedFileName { get; set; } = "";
        }

        public static DocumentClassificationResult ClassifyAndBuildMetadata(
            FileInfo fi,
            string extractedText,
            bool isFromTrichter,
            string? customTitleHint)
        {
            string root = LinuxVaultAndStorage.MeineDatenRoot;
            string relPath = "";
            try { relPath = Path.GetRelativePath(root, fi.FullName).Replace('\\', '/'); } catch { }

            string combined = $"{customTitleHint} {fi.Name} {relPath}\n{(extractedText.Length > 4500 ? extractedText.Substring(0, 4500) : extractedText)}";
            string lower = combined.ToLowerInvariant();
            string ext = fi.Extension.ToLowerInvariant();

            string docDate = DetectDocumentDate(fi, extractedText);
            string sender = DetectSender(combined);

            string targetFolder;
            string category = "Allgemeines Dokument";

            // Business vs Private Routing
            bool mentionsSeelenfunke = lower.Contains("seelenfunke") || lower.Contains("fyrst") ||
                                       lower.Contains("westpack") || lower.Contains("printequipment") ||
                                       lower.Contains("glücksfieber") || lower.Contains("gluecksfieber") ||
                                       lower.Contains("kartonfritze") || lower.Contains("karton.eu") ||
                                       lower.Contains("xtool") || lower.Contains("betriebshaftpflicht") ||
                                       lower.Contains("hiscox");

            if (ext is ".kdbx" or ".keyx" || lower.Contains("keepass"))
            {
                targetFolder = "07_IT_Funkenwerk/Keepass";
                category = "Passwort-Tresor (KeePass)";
            }
            else if (ext is ".svg" or ".stl" or ".glb" or ".gltf" or ".dxf" or ".xcs" || lower.Contains("xtool") || lower.Contains("laserparameter"))
            {
                targetFolder = "03_Manufaktur_Produkte";
                category = "Manufaktur & Laser";
            }
            else if (mentionsSeelenfunke && (lower.Contains("westpack") || lower.Contains("printequipment") || lower.Contains("glücksfieber") || lower.Contains("karton") || lower.Contains("lieferschein")))
            {
                targetFolder = "06_Lieferanten_Einkauf";
                category = "Lieferanten & Einkauf (Gewerbe)";
            }
            else if (mentionsSeelenfunke && (lower.Contains("dhl") || lower.Contains("sendungsnummer") || lower.Contains("widerruf") || lower.Contains("bestellung #")))
            {
                targetFolder = "04_Bestellungen_Versand";
                category = "Bestellungen & Versand";
            }
            else if (lower.Contains("betriebshaftpflicht") || lower.Contains("hiscox"))
            {
                targetFolder = "01_Unternehmen_Recht/Versicherungen_Gewerbe";
                category = "Betriebsversicherung (Gewerbe)";
            }
            else if (mentionsSeelenfunke && (lower.Contains("gewerbeanmeldung") || lower.Contains("lucid") || lower.Contains("verpackungsregister") || lower.Contains("markenamt") || lower.Contains("impressum") || lower.Contains("agb")))
            {
                targetFolder = "01_Unternehmen_Recht/Gewerbe_Vertraege";
                category = "Unternehmen & Recht (Gewerbe)";
            }
            else if (mentionsSeelenfunke && (lower.Contains("rechnung") || lower.Contains("umsatzsteuer") || lower.Contains("beleg") || lower.Contains("kontoauszug") || lower.Contains("fyrst")))
            {
                targetFolder = "02_Buchhaltung_Steuern";
                category = "Buchhaltung & Steuern (Gewerbe)";
            }
            else if (lower.Contains("gebäudever") || lower.Contains("haftpflicht") || lower.Contains("krankenversicherung") ||
                     lower.Contains("zahnzusatz") || lower.Contains("autoversicherung") || lower.Contains("kfz-versicherung") ||
                     lower.Contains("unfallversicherung") || lower.Contains("rentenversicherung") || lower.Contains("versicherungsschein") ||
                     lower.Contains("police"))
            {
                targetFolder = "08_Privat_Archiv/Versicherungen";
                category = "Versicherungen (Privat)";
            }
            else if (lower.Contains("arzt") || lower.Contains("klinik") || lower.Contains("krankenhaus") || lower.Contains("befund") ||
                     lower.Contains("rezept") || lower.Contains("krankmeldung") || lower.Contains("arbeitsunfähig") ||
                     lower.Contains("apotheke") || lower.Contains("labor") || lower.Contains("endokrinolog") || lower.Contains("transition"))
            {
                targetFolder = "08_Privat_Archiv/Gesundheit";
                category = "Gesundheit & Medizin";
            }
            else if (lower.Contains("grundsteuer") || lower.Contains("bauakte") || lower.Contains("grundriss") || lower.Contains("wärmepumpe") ||
                     lower.Contains("photovoltaik") || lower.Contains("schornsteinfeger") || lower.Contains("notar") ||
                     lower.Contains("grundbuch") || lower.Contains("stadtwerke") || lower.Contains("stromabrechnung") || lower.Contains("gasabrechnung") ||
                     lower.Contains("wasserverband") || lower.Contains("garagentor") || lower.Contains("hausdaten"))
            {
                targetFolder = "08_Privat_Archiv/Haus_Immobilien";
                category = "Haus & Immobilie";
            }
            else if (lower.Contains("einkommensteuer") || lower.Contains("steuerbescheid") || lower.Contains("elster") ||
                     lower.Contains("lohnsteuerbescheinigung") || lower.Contains("finanzamt"))
            {
                targetFolder = "08_Privat_Archiv/Steuern_Privat";
                category = "Steuern (Privat)";
            }
            else if (lower.Contains("personalausweis") || lower.Contains("reisepass") || lower.Contains("geburtsurkunde") ||
                     lower.Contains("eheurkunde") || lower.Contains("führerschein") || lower.Contains("unterschrift") || lower.Contains("urkunde"))
            {
                targetFolder = "08_Privat_Archiv/Ausweise_Urkunden";
                category = "Ausweise & Urkunden";
            }
            else if (lower.Contains("nachlass") || lower.Contains("erbschein") || lower.Contains("testament") || lower.Contains("vorsorgevollmacht") || lower.Contains("patientenverfügung"))
            {
                targetFolder = "08_Privat_Archiv/Erbe_Vorsorge";
                category = "Erbe & Vorsorge";
            }
            else if (lower.Contains("arbeitsvertrag") || lower.Contains("gehaltsabrechnung") || lower.Contains("entgeltabrechnung") ||
                     lower.Contains("arbeitszeugnis") || lower.Contains("kündigungsschreiben arbeitgeber"))
            {
                targetFolder = "08_Privat_Archiv/Beruf_Arbeitgeber";
                category = "Beruf & Frühere Arbeitgeber";
            }
            else if (lower.Contains("volksbank") || lower.Contains("brawo") || lower.Contains("darlehen") || lower.Contains("kreditvertrag") ||
                     lower.Contains("kontoauszug") || lower.Contains("schufa") || lower.Contains("sparkasse"))
            {
                targetFolder = "08_Privat_Archiv/Banken_Finanzen";
                category = "Banken, Darlehen & Schufa";
            }
            else if (lower.Contains("agentur für arbeit") || lower.Contains("arbeitsagentur") || lower.Contains("elterngeld") ||
                     lower.Contains("kindergeld") || lower.Contains("familienkasse") || lower.Contains("amtsgericht") ||
                     lower.Contains("landkreis gifhorn") || lower.Contains("stadt gifhorn") || lower.Contains("standesamt") ||
                     lower.Contains("polizei") || lower.Contains("führungszeugnis") || lower.Contains("bescheid"))
            {
                targetFolder = "08_Privat_Archiv/Behoerden_Recht";
                category = "Behörden, Ämter & Recht";
            }
            else if (lower.Contains("telekom") || lower.Contains("vodafone") || lower.Contains("glasfaser") || lower.Contains("1&1") ||
                     lower.Contains("simplytel") || lower.Contains("handyvertrag") || lower.Contains("rundfunk") || lower.Contains("beitragsservice") ||
                     lower.Contains("e-roller"))
            {
                targetFolder = "08_Privat_Archiv/Vertraege_Alltag";
                category = "Verträge, Internet & Mobilfunk";
            }
            else if (lower.Contains("rechnung") || lower.Contains("quittung") || lower.Contains("kaufbeleg") || lower.Contains("bestellbestätigung"))
            {
                targetFolder = "08_Privat_Archiv/Rechnungen_Privat";
                category = "Rechnungen & Käufe (Privat)";
            }
            else
            {
                targetFolder = isFromTrichter && string.IsNullOrWhiteSpace(extractedText) && string.IsNullOrWhiteSpace(customTitleHint)
                    ? "00_Eingang_Trichter/_Manuell_Pruefen"
                    : MapCategoryFromExistingRelPath(relPath, out category);
                if (targetFolder == "00_Eingang_Trichter/_Manuell_Pruefen")
                    category = "Eingang zur Prüfung";
            }

            string summary = BuildCrispSummary(fi.Name, customTitleHint, sender, docDate, extractedText);
            string suggestedName = BuildSuggestedFileName(docDate, sender, customTitleHint, category, extractedText, ext);

            return new DocumentClassificationResult
            {
                TargetRelFolder = targetFolder,
                DocDate = docDate,
                Sender = sender,
                Category = category,
                Summary = summary,
                SuggestedFileName = suggestedName
            };
        }

        private static string MapCategoryFromExistingRelPath(string relPath, out string category)
        {
            string l = (relPath ?? "").ToLowerInvariant();
            if (l.Contains("haus")) { category = "Haus & Immobilie"; return "08_Privat_Archiv/Haus_Immobilien"; }
            if (l.Contains("versicher")) { category = "Versicherungen (Privat)"; return "08_Privat_Archiv/Versicherungen"; }
            if (l.Contains("gesundheit")) { category = "Gesundheit & Medizin"; return "08_Privat_Archiv/Gesundheit"; }
            if (l.Contains("steuer")) { category = "Steuern (Privat)"; return "08_Privat_Archiv/Steuern_Privat"; }
            if (l.Contains("beruf") || l.Contains("arbeitgeber")) { category = "Beruf & Frühere Arbeitgeber"; return "08_Privat_Archiv/Beruf_Arbeitgeber"; }
            if (l.Contains("ausweis") || l.Contains("urkunde")) { category = "Ausweise & Urkunden"; return "08_Privat_Archiv/Ausweise_Urkunden"; }
            if (l.Contains("erbe")) { category = "Erbe & Vorsorge"; return "08_Privat_Archiv/Erbe_Vorsorge"; }
            if (l.Contains("bank") || l.Contains("darlehen") || l.Contains("schufa")) { category = "Banken, Darlehen & Schufa"; return "08_Privat_Archiv/Banken_Finanzen"; }
            if (l.Contains("rechnung")) { category = "Rechnungen & Käufe (Privat)"; return "08_Privat_Archiv/Rechnungen_Privat"; }
            if (l.Contains("vertrag") || l.Contains("internet") || l.Contains("handy")) { category = "Verträge, Internet & Mobilfunk"; return "08_Privat_Archiv/Vertraege_Alltag"; }
            if (l.StartsWith("01_")) { category = "Unternehmen & Recht"; return "01_Unternehmen_Recht/Gewerbe_Vertraege"; }
            if (l.StartsWith("02_")) { category = "Buchhaltung & Steuern"; return "02_Buchhaltung_Steuern"; }
            if (l.StartsWith("03_")) { category = "Manufaktur & Produkte"; return "03_Manufaktur_Produkte"; }
            if (l.StartsWith("04_")) { category = "Bestellungen & Versand"; return "04_Bestellungen_Versand"; }
            if (l.StartsWith("05_")) { category = "Marketing & Content"; return "05_Marketing_Content"; }
            if (l.StartsWith("06_")) { category = "Lieferanten & Einkauf"; return "06_Lieferanten_Einkauf"; }
            if (l.StartsWith("07_")) { category = "IT & Funkenwerk"; return "07_IT_Funkenwerk/System_Konfig"; }
            category = "Allgemeines Dokument";
            return "08_Privat_Archiv/Behoerden_Recht";
        }

        private static string DetectDocumentDate(FileInfo fi, string text)
        {
            // 1. Filename ISO YYYY-MM-DD or YYYYMMDD
            var fnIso = Regex.Match(fi.Name, @"\b(20[0-3]\d)-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])\b");
            if (fnIso.Success) return fnIso.Value;

            var fnDe = Regex.Match(fi.Name, @"\b(0[1-9]|[12]\d|3[01])\.(0[1-9]|1[0-2])\.(20[0-3]\d|19[89]\d)\b");
            if (fnDe.Success)
                return $"{fnDe.Groups[3].Value}-{fnDe.Groups[2].Value}-{fnDe.Groups[1].Value}";

            // 2. Document body German date dd.MM.yyyy
            if (!string.IsNullOrWhiteSpace(text))
            {
                string head = text.Length > 1800 ? text.Substring(0, 1800) : text;
                var bodyDe = Regex.Match(head, @"\b(0?[1-9]|[12]\d|3[01])\.(0?[1-9]|1[0-2])\.(20[0-3]\d)\b");
                if (bodyDe.Success)
                {
                    int d = int.Parse(bodyDe.Groups[1].Value);
                    int m = int.Parse(bodyDe.Groups[2].Value);
                    string y = bodyDe.Groups[3].Value;
                    return $"{y}-{m:D2}-{d:D2}";
                }
            }

            return fi.LastWriteTime.ToString("yyyy-MM-dd");
        }

        private static readonly (string Keyword, string CleanName)[] KnownSenders =
        {
            ("finanzamt gifhorn", "Finanzamt_Gifhorn"),
            ("finanzamt", "Finanzamt"),
            ("stadt gifhorn", "Stadt_Gifhorn"),
            ("landkreis gifhorn", "Landkreis_Gifhorn"),
            ("amtsgericht gifhorn", "Amtsgericht_Gifhorn"),
            ("standesamt gifhorn", "Standesamt_Gifhorn"),
            ("agentur für arbeit", "Agentur_fuer_Arbeit"),
            ("bundesagentur für arbeit", "Agentur_fuer_Arbeit"),
            ("familienkasse", "Familienkasse"),
            ("volksbank", "Volksbank_BraWo"),
            ("fyrst", "FYRST_Bank"),
            ("hiscox", "Hiscox"),
            ("allianz", "Allianz"),
            ("huk-coburg", "HUK_Coburg"),
            ("huk24", "HUK24"),
            ("vhv", "VHV"),
            ("debeka", "Debeka"),
            ("techniker krankenkasse", "TK_Krankenkasse"),
            ("barmer", "Barmer"),
            ("aok", "AOK"),
            ("dak", "DAK"),
            ("deutsche rentenversicherung", "Rentenversicherung"),
            ("deutsche glasfaser", "Deutsche_Glasfaser"),
            ("telekom", "Telekom"),
            ("vodafone", "Vodafone"),
            ("1&1", "1und1"),
            ("simplytel", "SimplyTel"),
            ("beitragsservice", "Rundfunkbeitrag"),
            ("schufa", "Schufa"),
            ("notar esser", "Notar_Esser"),
            ("notar", "Notar"),
            ("polizei", "Polizei"),
            ("westpack", "Westpack"),
            ("printequipment", "PrintEquipment"),
            ("glücksfieber", "Gluecksfieber"),
            ("kartonfritze", "Kartonfritze"),
            ("karton.eu", "Karton_eu"),
            ("xtool", "xTool"),
            ("dhl", "DHL")
        };

        private static string DetectSender(string combinedText)
        {
            string lower = combinedText.ToLowerInvariant();
            foreach (var (kw, clean) in KnownSenders)
            {
                if (lower.Contains(kw)) return clean.Replace('_', ' ');
            }
            return "Allgemein";
        }

        private static string BuildCrispSummary(string fileName, string? customTitle, string sender, string docDate, string text)
        {
            var parts = new List<string>();
            if (!string.IsNullOrWhiteSpace(customTitle)) parts.Add(customTitle.Trim());
            if (!string.IsNullOrWhiteSpace(sender) && sender != "Allgemein") parts.Add($"Absender: {sender}");
            if (!string.IsNullOrWhiteSpace(docDate)) parts.Add($"Datum: {docDate}");

            if (!string.IsNullOrWhiteSpace(text))
            {
                var amtMatch = Regex.Match(text, @"\b\d{1,3}(?:\.\d{3})*,\d{2}\s?(?:€|EUR)\b");
                if (amtMatch.Success) parts.Add($"Betrag: {amtMatch.Value}");

                string snippet = text.Length > 260 ? text.Substring(0, 260) + "..." : text;
                parts.Add(snippet);
            }
            else
            {
                parts.Add($"Datei: {fileName}");
            }

            string joined = string.Join(" | ", parts);
            return joined.Length > 460 ? joined.Substring(0, 460) : joined;
        }

        private static bool ShouldAutoRenameTrichterFile(string fileName)
        {
            string stem = Path.GetFileNameWithoutExtension(fileName).ToLowerInvariant();
            if (stem.Length < 4) return true;
            string[] genericPrefixes =
            {
                "scan", "img", "image", "dsc", "foto", "photo", "beleg_kamera",
                "kamera_funkira", "trichter", "dokument", "document", "whatsapp",
                "camscanner", "unbenannt", "untitled", "page", "seite", "upload"
            };
            return genericPrefixes.Any(p => stem.StartsWith(p, StringComparison.Ordinal)) ||
                   Regex.IsMatch(stem, @"^[0-9_\-]{6,}$");
        }

        private static string BuildSuggestedFileName(
            string docDate,
            string sender,
            string? customTitle,
            string category,
            string text,
            string ext)
        {
            string safeDate = string.IsNullOrWhiteSpace(docDate) ? DateTime.Now.ToString("yyyy-MM-dd") : docDate;
            string safeSender = SanitizeSlug(sender == "Allgemein" ? "" : sender, 26);

            string topicSource = !string.IsNullOrWhiteSpace(customTitle)
                ? customTitle!
                : ExtractHeadlineTopic(text, category);
            string safeTopic = SanitizeSlug(topicSource, 42);
            if (string.IsNullOrWhiteSpace(safeTopic))
                safeTopic = SanitizeSlug(category, 32);

            var segs = new List<string> { safeDate };
            if (!string.IsNullOrWhiteSpace(safeSender)) segs.Add(safeSender);
            if (!string.IsNullOrWhiteSpace(safeTopic)) segs.Add(safeTopic);

            string safeExt = string.IsNullOrWhiteSpace(ext) ? ".pdf" : ext;
            return string.Join("_", segs) + safeExt;
        }

        private static string ExtractHeadlineTopic(string text, string fallbackCategory)
        {
            if (string.IsNullOrWhiteSpace(text)) return fallbackCategory;
            var subjMatch = Regex.Match(text, @"(?:Betreff|Rechnung|Bescheid|Versicherungsschein|Vertrag|Kundennummer|Aktenzeichen)[:\s]+([^\r\n]{4,48})", RegexOptions.IgnoreCase);
            if (subjMatch.Success) return subjMatch.Groups[1].Value.Trim();

            foreach (string line in text.Split('\n', StringSplitOptions.RemoveEmptyEntries))
            {
                string trimmed = line.Trim();
                if (trimmed.Length >= 6 && trimmed.Length <= 48 && Regex.IsMatch(trimmed, @"\p{L}{3,}"))
                    return trimmed;
            }
            return fallbackCategory;
        }

        private static string SanitizeSlug(string input, int maxLen)
        {
            if (string.IsNullOrWhiteSpace(input)) return "";
            string s = input
                .Replace("ä", "ae").Replace("ö", "oe").Replace("ü", "ue")
                .Replace("Ä", "Ae").Replace("Ö", "Oe").Replace("Ü", "Ue")
                .Replace("ß", "ss");
            s = Regex.Replace(s, @"[^A-Za-z0-9]+", "_").Trim('_');
            if (s.Length > maxLen) s = s.Substring(0, maxLen).TrimEnd('_');
            return s;
        }

        private static string SanitizeSafeFileName(string name)
        {
            string leaf = Path.GetFileName(name);
            string clean = Regex.Replace(leaf, @"[^\p{L}\p{N}._\- ]+", "_").Trim(' ', '.');
            return string.IsNullOrWhiteSpace(clean) ? $"Trichter_{DateTime.Now:yyyyMMdd_HHmmss}.pdf" : clean;
        }

        private static string NormalizeWhitespace(string s)
        {
            if (string.IsNullOrWhiteSpace(s)) return "";
            return Regex.Replace(s, @"[ \t]+", " ").Trim();
        }

        // =====================================================================
        // 9. ULTRA-FAST SNAPSHOT DELTA BACKUP TO NAS (SMBLIBRARY SMB2/SMB3)
        // =====================================================================
        public static NasBackupStatusDto GetBackupStatus()
        {
            var st = LinuxVaultAndStorage.LoadSettings();
            var manifest = LoadBackupManifest();
            bool hasPass = !string.IsNullOrWhiteSpace(LinuxVaultAndStorage.GetNasPassword(st));
            return new NasBackupStatusDto
            {
                Configured = hasPass,
                Running = s_backupRunning,
                PendingCatchUp = st.NasBackupPendingCatchUp,
                NasHost = st.NasHost,
                NasShare = st.NasShare,
                NasSubFolder = st.NasSubFolder,
                NasUsername = st.NasUsername,
                LastBackupAt = string.IsNullOrWhiteSpace(st.LastNasBackupAt) ? "Noch kein Lauf" : st.LastNasBackupAt,
                LastBackupResult = string.IsNullOrWhiteSpace(st.LastNasBackupResult) ? "Bereit" : st.LastNasBackupResult,
                IndexedDocumentsCount = GetIndexedDocumentCount(),
                ManifestFilesCount = manifest.Count
            };
        }

        public static Dictionary<string, NasBackupEntryMeta> LoadBackupManifest()
        {
            try
            {
                if (File.Exists(BackupManifestPath))
                {
                    return JsonSerializer.Deserialize<Dictionary<string, NasBackupEntryMeta>>(
                        File.ReadAllText(BackupManifestPath)) ?? new(StringComparer.Ordinal);
                }
            }
            catch { }
            return new Dictionary<string, NasBackupEntryMeta>(StringComparer.Ordinal);
        }

        public static void SaveBackupManifest(Dictionary<string, NasBackupEntryMeta> manifest)
        {
            try
            {
                string tmp = BackupManifestPath + ".tmp";
                File.WriteAllText(tmp, JsonSerializer.Serialize(manifest));
                LinuxVaultAndStorage.TryRestrictFilePermissions(tmp);
                File.Move(tmp, BackupManifestPath, overwrite: true);
            }
            catch { }
        }

        public static int RebuildManifestFromCurrentLocalFiles()
        {
            string root = LinuxVaultAndStorage.MeineDatenRoot;
            var manifest = new Dictionary<string, NasBackupEntryMeta>(StringComparer.Ordinal);
            foreach (var fi in EnumerateArchiveFiles(root))
            {
                string rel = Path.GetRelativePath(root, fi.FullName).Replace('\\', '/');
                manifest[rel] = new NasBackupEntryMeta
                {
                    Size = fi.Length,
                    MtimeTicks = fi.LastWriteTimeUtc.Ticks
                };
            }
            SaveBackupManifest(manifest);
            return manifest.Count;
        }

        public static async Task<(bool Ok, string Summary, int CopiedCount, int DeletedCount)> RunDeltaNasBackupAsync(
            string reason = "Manuell",
            int connectTimeoutMs = 5500)
        {
            if (!await s_backupLock.WaitAsync(TimeSpan.FromSeconds(10)).ConfigureAwait(false))
                return (false, "Backup läuft bereits.", 0, 0);

            s_backupRunning = true;
            var sw = Stopwatch.StartNew();
            try
            {
                // Make sure any files in 00_Eingang_Trichter are sorted & indexed right before backing up!
                await ProcessTrichterDirectoryAsync().ConfigureAwait(false);

                var st = LinuxVaultAndStorage.LoadSettings();
                string root = LinuxVaultAndStorage.MeineDatenRoot;
                var manifest = LoadBackupManifest();

                // 1. Ultra-fast RAM snapshot diff (~35 ms for 4,000 files)
                var currentFiles = new Dictionary<string, FileInfo>(StringComparer.Ordinal);
                foreach (var fi in EnumerateArchiveFiles(root))
                {
                    string rel = Path.GetRelativePath(root, fi.FullName).Replace('\\', '/');
                    currentFiles[rel] = fi;
                }

                var toUpload = new List<(string Rel, FileInfo File)>();
                foreach (var kv in currentFiles)
                {
                    if (!manifest.TryGetValue(kv.Key, out var prev) ||
                        prev.Size != kv.Value.Length ||
                        prev.MtimeTicks != kv.Value.LastWriteTimeUtc.Ticks)
                    {
                        toUpload.Add((kv.Key, kv.Value));
                    }
                }

                var toArchiveDelete = manifest.Keys.Where(k => !currentFiles.ContainsKey(k)).ToList();

                // Fast zero-change exit (0 network bytes if nothing changed since last snapshot!)
                if (toUpload.Count == 0 && toArchiveDelete.Count == 0 && !st.NasBackupPendingCatchUp)
                {
                    st.LastNasBackupAt = GetBerlinNow().ToString("dd.MM.yyyy HH:mm");
                    st.LastNasBackupResult = $"✓ Aktuell ({reason}, 0 Änderungen in {sw.ElapsedMilliseconds} ms)";
                    LinuxVaultAndStorage.SaveSettings(st);
                    return (true, st.LastNasBackupResult, 0, 0);
                }

                bool wasCatchUp = st.NasBackupPendingCatchUp;

                // 2. Connect to Synology NAS via SMB2/SMB3
                string nasPass = LinuxVaultAndStorage.GetNasPassword(st);
                var client = new SMB2Client();
                bool tcpOk = await Task.Run(() =>
                {
                    if (IPAddress.TryParse(st.NasHost, out var ip))
                        return client.Connect(ip, SMBTransportType.DirectTCPTransport);
                    var addrs = Dns.GetHostAddresses(st.NasHost);
                    var ipv4 = addrs.FirstOrDefault(a => a.AddressFamily == System.Net.Sockets.AddressFamily.InterNetwork) ?? addrs.FirstOrDefault();
                    return ipv4 != null && client.Connect(ipv4, SMBTransportType.DirectTCPTransport);
                }).WaitAsync(TimeSpan.FromMilliseconds(connectTimeoutMs)).ConfigureAwait(false);

                if (!tcpOk)
                {
                    st.NasBackupPendingCatchUp = true;
                    st.LastNasBackupResult = $"NAS ({st.NasHost}) gerade nicht erreichbar (wird automatisch nachgeholt)";
                    LinuxVaultAndStorage.SaveSettings(st);
                    ErrorSentinelAndWatchdog.RecordEvent("nas_backup", "NAS SMB-Sicherung vorübergehend nicht erreichbar", st.LastNasBackupResult, severity: "warning");
                    return (false, st.LastNasBackupResult, 0, 0);
                }

                try
                {
                    NTStatus loginStatus = string.IsNullOrWhiteSpace(nasPass)
                        ? client.Login(string.Empty, "guest", string.Empty)
                        : client.Login(string.Empty, st.NasUsername, nasPass);

                    if (loginStatus != NTStatus.STATUS_SUCCESS)
                    {
                        st.NasBackupPendingCatchUp = true;
                        st.LastNasBackupResult = string.IsNullOrWhiteSpace(nasPass)
                            ? "Bitte einmalig NAS-Passwort (alina) in den Funkenwerk-Einstellungen hinterlegen."
                            : $"NAS-Anmeldung fehlgeschlagen ({loginStatus}).";
                        LinuxVaultAndStorage.SaveSettings(st);
                        ErrorSentinelAndWatchdog.RecordEvent("nas_backup", "NAS SMB-Anmeldung fehlgeschlagen", st.LastNasBackupResult, severity: "error");
                        return (false, st.LastNasBackupResult, 0, 0);
                    }

                    ISMBFileStore? share = client.TreeConnect(st.NasShare, out NTStatus treeStatus);
                    if (share == null || treeStatus != NTStatus.STATUS_SUCCESS)
                    {
                        st.NasBackupPendingCatchUp = true;
                        st.LastNasBackupResult = $"NAS-Freigabe '{st.NasShare}' nicht erreichbar ({treeStatus}).";
                        LinuxVaultAndStorage.SaveSettings(st);
                        return (false, st.LastNasBackupResult, 0, 0);
                    }

                    try
                    {
                        string baseSub = st.NasSubFolder.Trim('/', '\\').Replace('/', '\\');
                        EnsureSmbDirectoryTree(share, baseSub);

                        int copied = 0;
                        foreach (var (rel, fi) in toUpload)
                        {
                            string smbRelPath = baseSub + "\\" + rel.Replace('/', '\\');
                            string? parentDir = Path.GetDirectoryName(smbRelPath);
                            if (!string.IsNullOrWhiteSpace(parentDir))
                                EnsureSmbDirectoryTree(share, parentDir);

                            if (UploadFileToSmbShare(share, fi.FullName, smbRelPath, (int)client.MaxWriteSize))
                            {
                                manifest[rel] = new NasBackupEntryMeta
                                {
                                    Size = fi.Length,
                                    MtimeTicks = fi.LastWriteTimeUtc.Ticks
                                };
                                copied++;
                            }
                        }

                        // Move removed/relocated files into 30-day safety trash folder on NAS instead of hard deleting immediately
                        int archivedDeletes = 0;
                        string todayBucket = GetBerlinNow().ToString("yyyy-MM-dd");
                        foreach (string oldRel in toArchiveDelete)
                        {
                            string oldSmbPath = baseSub + "\\" + oldRel.Replace('/', '\\');
                            string trashSmbPath = $"{baseSub}\\_Papierkorb_30Tage\\{todayBucket}\\{oldRel.Replace('/', '\\')}";
                            MoveOrDeleteSmbFile(share, oldSmbPath, trashSmbPath);
                            manifest.Remove(oldRel);
                            archivedDeletes++;
                        }

                        // Also back up the SQLite FTS5 knowledge index so Funkira's DB is preserved on NAS too
                        if (File.Exists(IndexDbPath))
                        {
                            string dbSnapLocal = Path.Combine(LinuxVaultAndStorage.DataDir, "documents_index_backup_snap.db");
                            try
                            {
                                using (var srcConn = new SqliteConnection(ConnectionString))
                                using (var dstConn = new SqliteConnection($"Data Source={dbSnapLocal};Mode=ReadWriteCreate"))
                                {
                                    srcConn.Open();
                                    dstConn.Open();
                                    srcConn.BackupDatabase(dstConn);
                                }
                                EnsureSmbDirectoryTree(share, baseSub + "\\_Funkira_Index_DB");
                                UploadFileToSmbShare(share, dbSnapLocal, baseSub + "\\_Funkira_Index_DB\\documents_index.db", (int)client.MaxWriteSize);
                                File.Delete(dbSnapLocal);
                            }
                            catch { }
                        }

                        PurgeOldSmbTrashBuckets(share, baseSub + "\\_Papierkorb_30Tage", 30);
                        SaveBackupManifest(manifest);

                        st.NasBackupPendingCatchUp = false;
                        st.LastNasBackupAt = GetBerlinNow().ToString("dd.MM.yyyy HH:mm");
                        st.LastNasBackupResult = $"✓ Gesichert ({copied} neu/geändert, {archivedDeletes} bereinigt in {sw.ElapsedMilliseconds} ms)";
                        LinuxVaultAndStorage.SaveSettings(st);
                        if (wasCatchUp)
                        {
                            ErrorSentinelAndWatchdog.RecordEvent(
                                "nas_backup",
                                "NAS SMB-Sicherung vorübergehend nicht erreichbar",
                                st.LastNasBackupResult,
                                autoHealed: true,
                                healSummary: $"Delta-NAS-Backup auf //{st.NasHost}/{st.NasShare} wurde erfolgreich automatisch nachgeholt ({copied} Dateien synchronisiert).");
                        }
                        return (true, st.LastNasBackupResult, copied, archivedDeletes);
                    }
                    finally
                    {
                        try { share.Disconnect(); } catch { }
                    }
                }
                finally
                {
                    try { client.Logoff(); } catch { }
                    try { client.Disconnect(); } catch { }
                }
            }
            catch (Exception ex)
            {
                try
                {
                    var st = LinuxVaultAndStorage.LoadSettings();
                    st.NasBackupPendingCatchUp = true;
                    st.LastNasBackupResult = $"Backup-Hinweis: {ex.Message}";
                    LinuxVaultAndStorage.SaveSettings(st);
                }
                catch { }
                return (false, ex.Message, 0, 0);
            }
            finally
            {
                s_backupRunning = false;
                s_backupLock.Release();
            }
        }

        private static void EnsureSmbDirectoryTree(ISMBFileStore share, string smbFolder)
        {
            string[] parts = smbFolder.Split('\\', StringSplitOptions.RemoveEmptyEntries);
            string current = "";
            foreach (string p in parts)
            {
                current = string.IsNullOrEmpty(current) ? p : (current + "\\" + p);
                NTStatus st = share.CreateFile(
                    out object handle,
                    out _,
                    current,
                    AccessMask.GENERIC_READ,
                    SMBLibrary.FileAttributes.Directory,
                    ShareAccess.Read | ShareAccess.Write,
                    CreateDisposition.FILE_OPEN_IF,
                    CreateOptions.FILE_DIRECTORY_FILE,
                    null);
                if (st == NTStatus.STATUS_SUCCESS && handle != null)
                {
                    share.CloseFile(handle);
                }
            }
        }

        private static bool UploadFileToSmbShare(ISMBFileStore share, string localPath, string smbPath, int maxWriteSize)
        {
            NTStatus st = share.CreateFile(
                out object handle,
                out _,
                smbPath,
                AccessMask.GENERIC_WRITE | AccessMask.SYNCHRONIZE,
                SMBLibrary.FileAttributes.Normal,
                ShareAccess.None,
                CreateDisposition.FILE_OVERWRITE_IF,
                CreateOptions.FILE_NON_DIRECTORY_FILE | CreateOptions.FILE_SYNCHRONOUS_IO_NONALERT,
                null);

            if (st != NTStatus.STATUS_SUCCESS || handle == null)
                return false;

            try
            {
                int chunkSize = Math.Clamp(maxWriteSize > 0 ? maxWriteSize : 65536, 16384, 1048576);
                byte[] buf = new byte[chunkSize];
                long offset = 0;
                using var fs = new FileStream(localPath, FileMode.Open, FileAccess.Read, FileShare.ReadWrite);
                while (true)
                {
                    int read = fs.Read(buf, 0, buf.Length);
                    if (read <= 0) break;
                    byte[] slice = read == buf.Length ? buf : buf.AsSpan(0, read).ToArray();
                    NTStatus wst = share.WriteFile(out int written, handle, offset, slice);
                    if (wst != NTStatus.STATUS_SUCCESS) return false;
                    offset += written;
                }
                return true;
            }
            finally
            {
                share.CloseFile(handle);
            }
        }

        private static void MoveOrDeleteSmbFile(ISMBFileStore share, string oldSmbPath, string trashSmbPath)
        {
            try
            {
                string? trashParent = Path.GetDirectoryName(trashSmbPath);
                if (!string.IsNullOrWhiteSpace(trashParent))
                    EnsureSmbDirectoryTree(share, trashParent);

                NTStatus st = share.CreateFile(
                    out object handle,
                    out _,
                    oldSmbPath,
                    AccessMask.DELETE | AccessMask.GENERIC_WRITE | AccessMask.SYNCHRONIZE,
                    SMBLibrary.FileAttributes.Normal,
                    ShareAccess.None,
                    CreateDisposition.FILE_OPEN,
                    CreateOptions.FILE_NON_DIRECTORY_FILE,
                    null);

                if (st == NTStatus.STATUS_SUCCESS && handle != null)
                {
                    try
                    {
                        var renameInfo = new FileRenameInformationType2
                        {
                            ReplaceIfExists = true,
                            FileName = trashSmbPath
                        };
                        NTStatus renSt = share.SetFileInformation(handle, renameInfo);
                        if (renSt != NTStatus.STATUS_SUCCESS)
                        {
                            var disp = new FileDispositionInformation { DeletePending = true };
                            share.SetFileInformation(handle, disp);
                        }
                    }
                    finally
                    {
                        share.CloseFile(handle);
                    }
                }
            }
            catch { }
        }

        private static void PurgeOldSmbTrashBuckets(ISMBFileStore share, string trashRoot, int keepDays)
        {
            try
            {
                NTStatus st = share.CreateFile(
                    out object dirHandle,
                    out _,
                    trashRoot,
                    AccessMask.GENERIC_READ,
                    SMBLibrary.FileAttributes.Directory,
                    ShareAccess.Read | ShareAccess.Write,
                    CreateDisposition.FILE_OPEN,
                    CreateOptions.FILE_DIRECTORY_FILE,
                    null);
                if (st != NTStatus.STATUS_SUCCESS || dirHandle == null) return;

                List<QueryDirectoryFileInformation> entries;
                try
                {
                    share.QueryDirectory(out entries, dirHandle, "*", FileInformationClass.FileDirectoryInformation);
                }
                finally
                {
                    share.CloseFile(dirHandle);
                }

                if (entries == null) return;
                DateTime cutoff = GetBerlinNow().Date.AddDays(-keepDays);
                foreach (var e in entries.OfType<FileDirectoryInformation>())
                {
                    if (e.FileName is "." or "..") continue;
                    if (DateTime.TryParseExact(e.FileName, "yyyy-MM-dd", CultureInfo.InvariantCulture, DateTimeStyles.None, out var bucketDate) &&
                        bucketDate < cutoff)
                    {
                        DeleteSmbDirectoryRecursive(share, trashRoot + "\\" + e.FileName);
                    }
                }
            }
            catch { }
        }

        private static void DeleteSmbDirectoryRecursive(ISMBFileStore share, string smbDir)
        {
            try
            {
                NTStatus st = share.CreateFile(
                    out object h,
                    out _,
                    smbDir,
                    AccessMask.GENERIC_READ,
                    SMBLibrary.FileAttributes.Directory,
                    ShareAccess.Read | ShareAccess.Write,
                    CreateDisposition.FILE_OPEN,
                    CreateOptions.FILE_DIRECTORY_FILE,
                    null);
                if (st == NTStatus.STATUS_SUCCESS && h != null)
                {
                    share.QueryDirectory(out var list, h, "*", FileInformationClass.FileDirectoryInformation);
                    share.CloseFile(h);
                    if (list != null)
                    {
                        foreach (var item in list.OfType<FileDirectoryInformation>())
                        {
                            if (item.FileName is "." or "..") continue;
                            string child = smbDir + "\\" + item.FileName;
                            if ((item.FileAttributes & SMBLibrary.FileAttributes.Directory) != 0)
                                DeleteSmbDirectoryRecursive(share, child);
                            else
                            {
                                if (share.CreateFile(out object fh, out _, child, AccessMask.DELETE, SMBLibrary.FileAttributes.Normal, ShareAccess.None, CreateDisposition.FILE_OPEN, CreateOptions.FILE_NON_DIRECTORY_FILE, null) == NTStatus.STATUS_SUCCESS && fh != null)
                                {
                                    share.SetFileInformation(fh, new FileDispositionInformation { DeletePending = true });
                                    share.CloseFile(fh);
                                }
                            }
                        }
                    }
                }

                if (share.CreateFile(out object dh, out _, smbDir, AccessMask.DELETE, SMBLibrary.FileAttributes.Directory, ShareAccess.None, CreateDisposition.FILE_OPEN, CreateOptions.FILE_DIRECTORY_FILE, null) == NTStatus.STATUS_SUCCESS && dh != null)
                {
                    share.SetFileInformation(dh, new FileDispositionInformation { DeletePending = true });
                    share.CloseFile(dh);
                }
            }
            catch { }
        }

        // =====================================================================
        // 10. LOOPBACK WEBDAV SERVER (/Meine_Daten) FOR WINDOWS Z:\ DRIVE
        // =====================================================================
        public static async Task HandleWebDavRequestAsync(HttpContext ctx)
        {
            string method = ctx.Request.Method.ToUpperInvariant();
            ctx.Response.Headers["DAV"] = "1, 2";
            ctx.Response.Headers["MS-Author-Via"] = "DAV";
            ctx.Response.Headers["Allow"] = "OPTIONS, GET, HEAD, PUT, DELETE, PROPFIND, PROPPATCH, MKCOL, COPY, MOVE, LOCK, UNLOCK";

            if (method == "OPTIONS")
            {
                ctx.Response.StatusCode = StatusCodes.Status200OK;
                return;
            }

            string root = Path.GetFullPath(LinuxVaultAndStorage.MeineDatenRoot);
            string reqPath = Uri.UnescapeDataString(ctx.Request.Path.Value ?? "/Meine_Daten");
            string sub = reqPath.StartsWith("/Meine_Daten", StringComparison.OrdinalIgnoreCase)
                ? reqPath.Substring("/Meine_Daten".Length).Trim('/')
                : "";

            string? localPath = ResolveSafePathInsideRoot(root, sub);
            if (localPath == null)
            {
                ctx.Response.StatusCode = StatusCodes.Status403Forbidden;
                return;
            }

            switch (method)
            {
                case "PROPFIND":
                {
                    bool isDir = Directory.Exists(localPath);
                    bool isFile = !isDir && File.Exists(localPath);
                    if (!isDir && !isFile)
                    {
                        ctx.Response.StatusCode = StatusCodes.Status404NotFound;
                        return;
                    }

                    string depth = ctx.Request.Headers["Depth"].FirstOrDefault() ?? "1";
                    XNamespace d = "DAV:";
                    var multistatus = new XElement(d + "multistatus");

                    string baseHref = "/Meine_Daten" + (string.IsNullOrEmpty(sub) ? "/" : ("/" + EncodeDavPath(sub) + (isDir ? "/" : "")));
                    multistatus.Add(BuildDavPropResponse(d, baseHref, localPath, isDir));

                    if (isDir && depth != "0")
                    {
                        var di = new DirectoryInfo(localPath);
                        foreach (var childDir in di.GetDirectories().Where(x => !x.Name.StartsWith('.')).OrderBy(x => x.Name))
                        {
                            string cHref = baseHref.TrimEnd('/') + "/" + Uri.EscapeDataString(childDir.Name) + "/";
                            multistatus.Add(BuildDavPropResponse(d, cHref, childDir.FullName, true));
                        }
                        foreach (var childFile in di.GetFiles().Where(x => !x.Name.StartsWith('.')).OrderBy(x => x.Name))
                        {
                            string cHref = baseHref.TrimEnd('/') + "/" + Uri.EscapeDataString(childFile.Name);
                            multistatus.Add(BuildDavPropResponse(d, cHref, childFile.FullName, false));
                        }
                    }

                    byte[] xmlBytes = Encoding.UTF8.GetBytes("<?xml version=\"1.0\" encoding=\"utf-8\"?>" + multistatus.ToString(SaveOptions.DisableFormatting));
                    ctx.Response.StatusCode = 207;
                    ctx.Response.ContentType = "application/xml; charset=utf-8";
                    ctx.Response.ContentLength = xmlBytes.Length;
                    await ctx.Response.Body.WriteAsync(xmlBytes).ConfigureAwait(false);
                    return;
                }

                case "GET":
                case "HEAD":
                {
                    if (!File.Exists(localPath))
                    {
                        ctx.Response.StatusCode = Directory.Exists(localPath) ? StatusCodes.Status200OK : StatusCodes.Status404NotFound;
                        return;
                    }
                    var fi = new FileInfo(localPath);
                    ctx.Response.StatusCode = StatusCodes.Status200OK;
                    ctx.Response.ContentType = "application/octet-stream";
                    ctx.Response.ContentLength = fi.Length;
                    ctx.Response.Headers["Last-Modified"] = fi.LastWriteTimeUtc.ToString("R");
                    if (method == "GET")
                    {
                        await using var fs = new FileStream(localPath, FileMode.Open, FileAccess.Read, FileShare.ReadWrite);
                        await fs.CopyToAsync(ctx.Response.Body).ConfigureAwait(false);
                    }
                    return;
                }

                case "PUT":
                {
                    string? parent = Path.GetDirectoryName(localPath);
                    if (!string.IsNullOrEmpty(parent)) Directory.CreateDirectory(parent);
                    bool existed = File.Exists(localPath);
                    string tmp = localPath + ".davtmp";
                    await using (var fs = new FileStream(tmp, FileMode.Create, FileAccess.Write, FileShare.None))
                    {
                        await ctx.Request.Body.CopyToAsync(fs).ConfigureAwait(false);
                    }
                    File.Move(tmp, localPath, overwrite: true);
                    ctx.Response.StatusCode = existed ? StatusCodes.Status204NoContent : StatusCodes.Status201Created;

                    var fi = new FileInfo(localPath);
                    if (fi.Length > 0)
                    {
                        if (sub.StartsWith("00_Eingang_Trichter/", StringComparison.OrdinalIgnoreCase))
                        {
                            try { File.SetLastWriteTimeUtc(localPath, DateTime.UtcNow.AddSeconds(-2)); } catch { }
                            _ = Task.Run(() => ProcessTrichterDirectoryAsync());
                        }
                        else
                        {
                            _ = Task.Run(() => SyncFullArchiveIndexIncrementalAsync(allowImageOcrPass: true, maxOcrFilesPerPass: 3));
                        }
                    }
                    return;
                }

                case "MKCOL":
                {
                    if (Directory.Exists(localPath) || File.Exists(localPath))
                    {
                        ctx.Response.StatusCode = StatusCodes.Status405MethodNotAllowed;
                        return;
                    }
                    Directory.CreateDirectory(localPath);
                    ctx.Response.StatusCode = StatusCodes.Status201Created;
                    return;
                }

                case "DELETE":
                {
                    if (localPath == root)
                    {
                        ctx.Response.StatusCode = StatusCodes.Status403Forbidden;
                        return;
                    }
                    if (!File.Exists(localPath) && !Directory.Exists(localPath))
                    {
                        ctx.Response.StatusCode = StatusCodes.Status404NotFound;
                        return;
                    }
                    LinuxFileManager.MoveToTrash(localPath);
                    _ = Task.Run(() => SyncFullArchiveIndexIncrementalAsync(allowImageOcrPass: false));
                    ctx.Response.StatusCode = StatusCodes.Status204NoContent;
                    return;
                }

                case "MOVE":
                case "COPY":
                {
                    string? destHeader = ctx.Request.Headers["Destination"].FirstOrDefault();
                    if (string.IsNullOrWhiteSpace(destHeader))
                    {
                        ctx.Response.StatusCode = StatusCodes.Status400BadRequest;
                        return;
                    }
                    string destPathUri = Uri.TryCreate(destHeader, UriKind.Absolute, out var absU)
                        ? Uri.UnescapeDataString(absU.AbsolutePath)
                        : Uri.UnescapeDataString(destHeader);
                    string destSub = destPathUri.StartsWith("/Meine_Daten", StringComparison.OrdinalIgnoreCase)
                        ? destPathUri.Substring("/Meine_Daten".Length).Trim('/')
                        : "";
                    string? destLocal = ResolveSafePathInsideRoot(root, destSub);
                    if (destLocal == null || destLocal == root)
                    {
                        ctx.Response.StatusCode = StatusCodes.Status403Forbidden;
                        return;
                    }
                    bool destExisted = File.Exists(destLocal) || Directory.Exists(destLocal);
                    string? destParent = Path.GetDirectoryName(destLocal);
                    if (!string.IsNullOrEmpty(destParent)) Directory.CreateDirectory(destParent);

                    if (File.Exists(localPath))
                    {
                        if (method == "MOVE") File.Move(localPath, destLocal, overwrite: true);
                        else File.Copy(localPath, destLocal, overwrite: true);
                    }
                    else if (Directory.Exists(localPath) && method == "MOVE")
                    {
                        Directory.Move(localPath, destLocal);
                    }
                    else
                    {
                        ctx.Response.StatusCode = StatusCodes.Status404NotFound;
                        return;
                    }
                    _ = Task.Run(() => SyncFullArchiveIndexIncrementalAsync(allowImageOcrPass: false));
                    ctx.Response.StatusCode = destExisted ? StatusCodes.Status204NoContent : StatusCodes.Status201Created;
                    return;
                }

                case "PROPPATCH":
                {
                    XNamespace d = "DAV:";
                    var ms = new XElement(d + "multistatus",
                        new XElement(d + "response",
                            new XElement(d + "href", ctx.Request.Path.Value ?? "/Meine_Daten"),
                            new XElement(d + "propstat",
                                new XElement(d + "prop"),
                                new XElement(d + "status", "HTTP/1.1 200 OK"))));
                    byte[] b = Encoding.UTF8.GetBytes("<?xml version=\"1.0\" encoding=\"utf-8\"?>" + ms.ToString(SaveOptions.DisableFormatting));
                    ctx.Response.StatusCode = 207;
                    ctx.Response.ContentType = "application/xml; charset=utf-8";
                    await ctx.Response.Body.WriteAsync(b).ConfigureAwait(false);
                    return;
                }

                case "LOCK":
                {
                    string token = "opaquelocktoken:" + Guid.NewGuid().ToString("D");
                    XNamespace d = "DAV:";
                    var prop = new XElement(d + "prop",
                        new XElement(d + "lockdiscovery",
                            new XElement(d + "activelock",
                                new XElement(d + "locktype", new XElement(d + "write")),
                                new XElement(d + "lockscope", new XElement(d + "exclusive")),
                                new XElement(d + "depth", "0"),
                                new XElement(d + "timeout", "Second-3600"),
                                new XElement(d + "locktoken", new XElement(d + "href", token)))));
                    byte[] b = Encoding.UTF8.GetBytes("<?xml version=\"1.0\" encoding=\"utf-8\"?>" + prop.ToString(SaveOptions.DisableFormatting));
                    ctx.Response.StatusCode = StatusCodes.Status200OK;
                    ctx.Response.Headers["Lock-Token"] = $"<{token}>";
                    ctx.Response.ContentType = "application/xml; charset=utf-8";
                    await ctx.Response.Body.WriteAsync(b).ConfigureAwait(false);
                    return;
                }

                case "UNLOCK":
                {
                    ctx.Response.StatusCode = StatusCodes.Status204NoContent;
                    return;
                }

                default:
                    ctx.Response.StatusCode = StatusCodes.Status405MethodNotAllowed;
                    return;
            }
        }

        private static XElement BuildDavPropResponse(XNamespace d, string href, string fullPath, bool isDir)
        {
            DateTime createdUtc = isDir ? Directory.GetCreationTimeUtc(fullPath) : File.GetCreationTimeUtc(fullPath);
            DateTime modUtc = isDir ? Directory.GetLastWriteTimeUtc(fullPath) : File.GetLastWriteTimeUtc(fullPath);
            long len = isDir ? 0 : new FileInfo(fullPath).Length;
            string displayName = Path.GetFileName(fullPath.TrimEnd(Path.DirectorySeparatorChar));
            if (string.IsNullOrEmpty(displayName)) displayName = "Meine_Daten";

            var prop = new XElement(d + "prop",
                new XElement(d + "displayname", displayName),
                new XElement(d + "creationdate", createdUtc.ToString("yyyy-MM-ddTHH:mm:ssZ")),
                new XElement(d + "getlastmodified", modUtc.ToString("R")),
                new XElement(d + "resourcetype", isDir ? new XElement(d + "collection") : null));

            if (!isDir)
            {
                prop.Add(new XElement(d + "getcontentlength", len));
                prop.Add(new XElement(d + "getcontenttype", "application/octet-stream"));
            }

            return new XElement(d + "response",
                new XElement(d + "href", href),
                new XElement(d + "propstat",
                    prop,
                    new XElement(d + "status", "HTTP/1.1 200 OK")));
        }

        private static string EncodeDavPath(string sub)
        {
            return string.Join("/", sub.Split('/', StringSplitOptions.RemoveEmptyEntries).Select(Uri.EscapeDataString));
        }

        private static string? ResolveSafePathInsideRoot(string rootFullPath, string relativeSub)
        {
            if (relativeSub.Contains('\0')) return null;
            string combined = string.IsNullOrWhiteSpace(relativeSub)
                ? rootFullPath
                : Path.GetFullPath(Path.Combine(rootFullPath, relativeSub.Replace('/', Path.DirectorySeparatorChar)));

            if (combined == rootFullPath || combined.StartsWith(rootFullPath + Path.DirectorySeparatorChar, StringComparison.Ordinal))
                return combined;

            return null;
        }

        private static string GetUniquePath(string candidate)
        {
            if (!File.Exists(candidate) && !Directory.Exists(candidate)) return candidate;
            string dir = Path.GetDirectoryName(candidate) ?? "";
            string stem = Path.GetFileNameWithoutExtension(candidate);
            string ext = Path.GetExtension(candidate);
            for (int i = 2; i < 999; i++)
            {
                string p = Path.Combine(dir, $"{stem}_{i}{ext}");
                if (!File.Exists(p) && !Directory.Exists(p)) return p;
            }
            return Path.Combine(dir, $"{stem}_{Guid.NewGuid():N}{ext}");
        }

        private static DateTime GetBerlinNow()
        {
            try
            {
                var tz = TimeZoneInfo.FindSystemTimeZoneById("Europe/Berlin");
                return TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, tz);
            }
            catch
            {
                return DateTime.Now;
            }
        }
    }
}
