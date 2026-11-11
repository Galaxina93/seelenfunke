using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Web;
using System.Windows.Media;

namespace FunkenwerkTresor
{
    public sealed class TresorAppSettings
    {
        public bool DarkMode { get; set; } = false; // Klassische helle KeePass-Ansicht (wie im Screenshot) als Standard
        public bool AutoLockEnabled { get; set; } = false; // Standardmäßig AUS: Tresor bleibt geöffnet, optional aktivierbar
        public int AutoLockMinutes { get; set; } = 5; // 1, 2, 5, 10, 30 Min. (wenn AutoLockEnabled = true)
        public int ClipboardClearSeconds { get; set; } = 12; // 12s Standard
        public bool ScreenCaptureProtection { get; set; } = false; // Optional über Extras aktivierbar (verhindert unsichtbares Fenster bei RDP/Display-Capture)
        public bool LockOnWindowsLock { get; set; } = false;
        public string LastDatabasePath { get; set; } = "";
        public string LastKeyfilePath { get; set; } = "";
        public List<string> RecentDatabases { get; set; } = new();

        public static string DataDir
        {
            get
            {
                string dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkTresor");
                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                    LockDownDirectoryAcl(dir);
                }
                return dir;
            }
        }

        public static string DefaultVaultsDir
        {
            get
            {
                string dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments), "Funkenwerk Tresore");
                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                    LockDownDirectoryAcl(dir);
                }
                return dir;
            }
        }

        public static string SettingsFilePath => Path.Combine(DataDir, "tresor_settings.json");

        public static TresorAppSettings Load()
        {
            TresorAppSettings s = new TresorAppSettings();
            try
            {
                if (File.Exists(SettingsFilePath))
                {
                    string json = File.ReadAllText(SettingsFilePath, Encoding.UTF8);
                    var loaded = JsonSerializer.Deserialize<TresorAppSettings>(json);
                    if (loaded != null)
                    {
                        loaded.RecentDatabases ??= new List<string>();
                        if (loaded.AutoLockMinutes <= 0) loaded.AutoLockMinutes = 5;
                        if (loaded.ClipboardClearSeconds < 5) loaded.ClipboardClearSeconds = 12;
                        s = loaded;
                    }
                }
            }
            catch { }

            s.EnsureKnownKdbxFilesDiscovered();
            return s;
        }

        public void EnsureKnownKdbxFilesDiscovered()
        {
            try
            {
                if (Directory.Exists(DefaultVaultsDir))
                {
                    var localVaults = Directory.GetFiles(DefaultVaultsDir, "*.kdbx")
                        .OrderByDescending(File.GetLastWriteTimeUtc);
                    foreach (string v in localVaults)
                    {
                        if (!RecentDatabases.Any(p => string.Equals(p, v, StringComparison.OrdinalIgnoreCase)))
                            RecentDatabases.Add(v);
                        if (string.IsNullOrWhiteSpace(LastDatabasePath) || !File.Exists(LastDatabasePath))
                            LastDatabasePath = v;
                    }
                }

                string userProfile = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
                string[] knownCandidates =
                {
                    Path.Combine(userProfile, "kDrive", "Unterlagen_Alina", "Dokumente", "Keepass", "DB - Privat.kdbx"),
                    Path.Combine(userProfile, "kDrive", "Gewerbe", "DoKa", "Keepass", "DB - Doka.kdbx")
                };

                foreach (string candidate in knownCandidates)
                {
                    if (File.Exists(candidate))
                    {
                        if (!RecentDatabases.Any(p => string.Equals(p, candidate, StringComparison.OrdinalIgnoreCase)))
                            RecentDatabases.Add(candidate);
                        if (string.IsNullOrWhiteSpace(LastDatabasePath) || !File.Exists(LastDatabasePath))
                            LastDatabasePath = candidate;
                    }
                }
            }
            catch { }
        }

        public string GetMostRecentDatabasePath()
        {
            EnsureKnownKdbxFilesDiscovered();
            if (!string.IsNullOrWhiteSpace(LastDatabasePath) && File.Exists(LastDatabasePath))
                return LastDatabasePath;

            string? firstExisting = RecentDatabases.FirstOrDefault(File.Exists);
            if (!string.IsNullOrWhiteSpace(firstExisting))
            {
                LastDatabasePath = firstExisting;
                return firstExisting;
            }
            return "";
        }

        public void Save()
        {
            try
            {
                string tmp = SettingsFilePath + ".tmp";
                string json = JsonSerializer.Serialize(this, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(tmp, json, new UTF8Encoding(false));
                File.Move(tmp, SettingsFilePath, overwrite: true);
                LockDownFileAcl(SettingsFilePath);
            }
            catch { }
        }

        public void AddRecentDatabase(string path)
        {
            if (string.IsNullOrWhiteSpace(path)) return;
            try
            {
                string full = Path.GetFullPath(path);
                LastDatabasePath = full;
                RecentDatabases.RemoveAll(p => string.Equals(p, full, StringComparison.OrdinalIgnoreCase));
                RecentDatabases.Insert(0, full);
                if (RecentDatabases.Count > 8)
                    RecentDatabases.RemoveRange(8, RecentDatabases.Count - 8);
                Save();
            }
            catch { }
        }

        public static void LockDownDirectoryAcl(string dirPath)
        {
            try
            {
                var di = new DirectoryInfo(dirPath);
                if (!di.Exists) return;
                var user = WindowsIdentity.GetCurrent().User;
                if (user == null) return;

                var sec = new DirectorySecurity();
                sec.SetAccessRuleProtection(true, false);
                sec.AddAccessRule(new FileSystemAccessRule(user, FileSystemRights.FullControl, InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit, PropagationFlags.None, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null), FileSystemRights.FullControl, InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit, PropagationFlags.None, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null), FileSystemRights.FullControl, InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit, PropagationFlags.None, AccessControlType.Allow));
                di.SetAccessControl(sec);
            }
            catch { }
        }

        public static void LockDownFileAcl(string filePath)
        {
            try
            {
                var fi = new FileInfo(filePath);
                if (!fi.Exists) return;
                var user = WindowsIdentity.GetCurrent().User;
                if (user == null) return;

                var sec = new FileSecurity();
                sec.SetAccessRuleProtection(true, false);
                sec.AddAccessRule(new FileSystemAccessRule(user, FileSystemRights.FullControl, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null), FileSystemRights.FullControl, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null), FileSystemRights.FullControl, AccessControlType.Allow));
                fi.SetAccessControl(sec);
            }
            catch { }
        }
    }

    public sealed class KdbxCustomString
    {
        public string Value { get; set; } = "";
        public bool IsProtected { get; set; }
    }

    public sealed class KdbxAttachment
    {
        public string FileName { get; set; } = "";
        public byte[] Data { get; set; } = Array.Empty<byte>();
        public string SizeFormatted => Data.Length < 1024
            ? $"{Data.Length} B"
            : (Data.Length < 1024 * 1024 ? $"{Data.Length / 1024.0:0.0} KB" : $"{Data.Length / (1024.0 * 1024.0):0.00} MB");
    }

    public sealed class KdbxEntry
    {
        public string UuidHex { get; set; } = NewUuidHex();
        public string GroupUuidHex { get; set; } = "";
        public string GroupName { get; set; } = "";
        public int IconId { get; set; } = 0;
        public string CustomIconUuidHex { get; set; } = "";
        public Dictionary<string, byte[]>? CustomIconsRef { get; set; }

        public string Title { get; set; } = "";
        public string UserName { get; set; } = "";
        public string Password { get; set; } = "";
        public string Url { get; set; } = "";
        public string Notes { get; set; } = "";
        public string Tags { get; set; } = "";

        public Dictionary<string, KdbxCustomString> CustomStrings { get; set; } = new(StringComparer.Ordinal);
        public List<KdbxAttachment> Attachments { get; set; } = new();

        public DateTime CreatedUtc { get; set; } = DateTime.UtcNow;
        public DateTime ModifiedUtc { get; set; } = DateTime.UtcNow;
        public bool Expires { get; set; }
        public DateTime? ExpiresUtc { get; set; }
        public string? RawHistoryXml { get; set; }

        public ImageSource IconImage => KeePassIconRenderer.GetIcon(IconId, CustomIconUuidHex, CustomIconsRef, false);

        public string NotesSingleLine => string.IsNullOrWhiteSpace(Notes)
            ? ""
            : Notes.Replace("\r\n", " ").Replace("\n", " ").Replace("\r", " ").Trim();

        public bool IsFavorite
        {
            get
            {
                if (string.IsNullOrWhiteSpace(Tags)) return false;
                var parts = Tags.Split(new[] { ',', ';', ' ' }, StringSplitOptions.RemoveEmptyEntries);
                return parts.Any(p => string.Equals(p, "Favorit", StringComparison.OrdinalIgnoreCase)
                                   || string.Equals(p, "Favorite", StringComparison.OrdinalIgnoreCase)
                                   || p == "★");
            }
            set
            {
                var parts = (Tags ?? "")
                    .Split(new[] { ',', ';' }, StringSplitOptions.RemoveEmptyEntries)
                    .Select(p => p.Trim())
                    .Where(p => !string.IsNullOrEmpty(p)
                             && !string.Equals(p, "Favorit", StringComparison.OrdinalIgnoreCase)
                             && !string.Equals(p, "Favorite", StringComparison.OrdinalIgnoreCase)
                             && p != "★")
                    .ToList();
                if (value)
                    parts.Insert(0, "Favorit");
                Tags = string.Join(", ", parts);
            }
        }

        public string TotpSecretOrUri
        {
            get
            {
                if (CustomStrings.TryGetValue("otp", out var otp) && !string.IsNullOrWhiteSpace(otp.Value))
                    return otp.Value;
                if (CustomStrings.TryGetValue("TimeOtp-Secret-Base32", out var kp2) && !string.IsNullOrWhiteSpace(kp2.Value))
                    return kp2.Value;
                if (CustomStrings.TryGetValue("TOTP Seed", out var seed) && !string.IsNullOrWhiteSpace(seed.Value))
                    return seed.Value;
                if (CustomStrings.TryGetValue("TOTP", out var totp) && !string.IsNullOrWhiteSpace(totp.Value))
                    return totp.Value;
                return "";
            }
            set
            {
                string clean = (value ?? "").Trim();
                if (string.IsNullOrEmpty(clean))
                {
                    CustomStrings.Remove("otp");
                    CustomStrings.Remove("TimeOtp-Secret-Base32");
                    CustomStrings.Remove("TOTP Seed");
                    CustomStrings.Remove("TOTP");
                }
                else
                {
                    string base32 = TotpEngine.ExtractBase32Secret(clean);
                    string issuer = string.IsNullOrWhiteSpace(Title) ? "Tresor" : Title.Trim();
                    string account = string.IsNullOrWhiteSpace(UserName) ? "account" : UserName.Trim();
                    string uri = clean.StartsWith("otpauth://", StringComparison.OrdinalIgnoreCase)
                        ? clean
                        : $"otpauth://totp/{Uri.EscapeDataString(issuer)}:{Uri.EscapeDataString(account)}?secret={base32}&issuer={Uri.EscapeDataString(issuer)}&period=30&digits=6&algorithm=SHA1";

                    CustomStrings["otp"] = new KdbxCustomString { Value = uri, IsProtected = true };
                    if (!string.IsNullOrEmpty(base32))
                        CustomStrings["TimeOtp-Secret-Base32"] = new KdbxCustomString { Value = base32, IsProtected = true };
                }
            }
        }

        public bool HasTotp => !string.IsNullOrWhiteSpace(TotpSecretOrUri) && TotpEngine.TryGenerateTotp(TotpSecretOrUri, DateTimeOffset.UtcNow, out _, out _);

        public string DomainBadge
        {
            get
            {
                if (string.IsNullOrWhiteSpace(Url)) return "";
                try
                {
                    string raw = Url.Trim();
                    if (!raw.Contains("://", StringComparison.Ordinal))
                        raw = "https://" + raw;
                    if (Uri.TryCreate(raw, UriKind.Absolute, out var u) && !string.IsNullOrWhiteSpace(u.Host))
                        return u.Host.StartsWith("www.", StringComparison.OrdinalIgnoreCase) ? u.Host.Substring(4) : u.Host;
                }
                catch { }
                return Url.Length > 24 ? Url.Substring(0, 23) + "…" : Url;
            }
        }

        public string AvatarInitial => string.IsNullOrWhiteSpace(Title)
            ? "🔑"
            : Title.Trim().Substring(0, 1).ToUpperInvariant();

        public KdbxEntry CloneEntry(bool newUuid = false)
        {
            var copy = new KdbxEntry
            {
                UuidHex = newUuid ? NewUuidHex() : UuidHex,
                GroupUuidHex = GroupUuidHex,
                GroupName = GroupName,
                IconId = IconId,
                CustomIconUuidHex = CustomIconUuidHex,
                CustomIconsRef = CustomIconsRef,
                Title = Title,
                UserName = UserName,
                Password = Password,
                Url = Url,
                Notes = Notes,
                Tags = Tags,
                CreatedUtc = newUuid ? DateTime.UtcNow : CreatedUtc,
                ModifiedUtc = DateTime.UtcNow,
                Expires = Expires,
                ExpiresUtc = ExpiresUtc,
                RawHistoryXml = newUuid ? null : RawHistoryXml
            };
            foreach (var kv in CustomStrings)
                copy.CustomStrings[kv.Key] = new KdbxCustomString { Value = kv.Value.Value, IsProtected = kv.Value.IsProtected };
            foreach (var att in Attachments)
            {
                byte[] buf = new byte[att.Data.Length];
                Buffer.BlockCopy(att.Data, 0, buf, 0, att.Data.Length);
                copy.Attachments.Add(new KdbxAttachment { FileName = att.FileName, Data = buf });
            }
            return copy;
        }

        public void WipeSensitiveMemory()
        {
            Password = new string('\0', Password.Length);
            Password = "";
            Notes = "";
            foreach (var kv in CustomStrings)
                kv.Value.Value = "";
            CustomStrings.Clear();
            foreach (var att in Attachments)
                CryptographicOperations.ZeroMemory(att.Data);
            Attachments.Clear();
        }

        public static string NewUuidHex() => Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
    }

    public sealed class KdbxGroup
    {
        public string UuidHex { get; set; } = KdbxEntry.NewUuidHex();
        public string Name { get; set; } = "Allgemein";
        public int IconId { get; set; } = 48;
        public string CustomIconUuidHex { get; set; } = "";
        public Dictionary<string, byte[]>? CustomIconsRef { get; set; }
        public bool IsRootGroup { get; set; }
        public KdbxGroup? Parent { get; set; }
        public string Notes { get; set; } = "";
        public bool IsExpanded { get; set; } = true;
        public DateTime CreatedUtc { get; set; } = DateTime.UtcNow;
        public DateTime ModifiedUtc { get; set; } = DateTime.UtcNow;
        public List<KdbxGroup> SubGroups { get; set; } = new();
        public List<KdbxEntry> Entries { get; set; } = new();

        public ImageSource IconImage => KeePassIconRenderer.GetIcon(IconId, CustomIconUuidHex, CustomIconsRef, IsRootGroup);

        public bool IsRecycleBin(string? recycleBinUuidHex) =>
            (!string.IsNullOrEmpty(recycleBinUuidHex) && string.Equals(UuidHex, recycleBinUuidHex, StringComparison.OrdinalIgnoreCase))
            || string.Equals(Name, "Papierkorb", StringComparison.OrdinalIgnoreCase)
            || string.Equals(Name, "Recycle Bin", StringComparison.OrdinalIgnoreCase)
            || string.Equals(Name, "Trash", StringComparison.OrdinalIgnoreCase);

        public KdbxGroup AddChild(string name, int iconId, bool expanded = true)
        {
            var child = new KdbxGroup
            {
                Name = name,
                IconId = iconId,
                IsExpanded = expanded,
                Parent = this,
                CustomIconsRef = CustomIconsRef
            };
            SubGroups.Add(child);
            return child;
        }
    }

    public sealed class KdbxDatabase
    {
        public string FilePath { get; set; } = "";
        public string DatabaseName { get; set; } = "DB";
        public string DatabaseDescription { get; set; } = "Verschlüsselter KDBX 4 Passwort-Tresor";
        public string GeneratorName { get; set; } = "Funkenwerk Tresor";

        public ushort FormatMajor { get; set; } = 4;
        public ushort FormatMinor { get; set; } = 0;
        public string KdfAlgorithm { get; set; } = "Argon2id"; // Argon2id | Argon2d | AES-KDF
        public string CipherAlgorithm { get; set; } = "AES-256"; // AES-256 | ChaCha20 | Twofish

        public ulong Argon2MemoryBytes { get; set; } = 64UL * 1024UL * 1024UL; // 64 MB
        public ulong Argon2Iterations { get; set; } = 3;
        public uint Argon2Parallelism { get; set; } = 2;
        public ulong AesKdfRounds { get; set; } = 600_000;

        public string RecycleBinUuidHex { get; set; } = "";
        public bool RecycleBinEnabled { get; set; } = true;
        public Dictionary<string, byte[]> CustomIcons { get; set; } = new(StringComparer.OrdinalIgnoreCase);
        public KdbxGroup RootGroup { get; set; } = new() { Name = "DB", IconId = 48, IsRootGroup = true };
        public bool IsDirty { get; set; }

        public bool IsModernArgon2Kdbx4 =>
            FormatMajor >= 4 &&
            (string.Equals(KdfAlgorithm, "Argon2id", StringComparison.OrdinalIgnoreCase) ||
             string.Equals(KdfAlgorithm, "Argon2d", StringComparison.OrdinalIgnoreCase));

        public string SecurityBadgeText =>
            $"KDBX {FormatMajor}.{FormatMinor} · {KdfAlgorithm} · {CipherAlgorithm}";

        public void RefreshHierarchyReferences()
        {
            RootGroup.IsRootGroup = true;
            RootGroup.Parent = null;
            RootGroup.CustomIconsRef = CustomIcons;

            void Walk(KdbxGroup g)
            {
                g.CustomIconsRef = CustomIcons;
                foreach (var e in g.Entries)
                {
                    e.GroupUuidHex = g.UuidHex;
                    e.GroupName = g.Name;
                    e.CustomIconsRef = CustomIcons;
                }
                foreach (var sub in g.SubGroups)
                {
                    sub.IsRootGroup = false;
                    sub.Parent = g;
                    Walk(sub);
                }
            }
            Walk(RootGroup);
        }

        public static KdbxDatabase CreateNewDefault(string databaseName)
        {
            string rootName = string.IsNullOrWhiteSpace(databaseName) ? "DB" : databaseName.Trim();
            var db = new KdbxDatabase
            {
                DatabaseName = rootName,
                FormatMajor = 4,
                FormatMinor = 0,
                KdfAlgorithm = "Argon2id",
                CipherAlgorithm = "AES-256",
                Argon2MemoryBytes = 64UL * 1024UL * 1024UL,
                Argon2Iterations = 3,
                Argon2Parallelism = 2,
                RootGroup = new KdbxGroup { Name = rootName, IconId = 48, IsRootGroup = true, IsExpanded = true }
            };

            // Exakter Strukturbaum wie auf Alinas Screenshot als Standard-Vorlage für neue Tresore
            var alina = db.RootGroup.AddChild("Alina", 55, expanded: true);

            var privat = alina.AddChild("Privat", 61, expanded: true);
            var emails = privat.AddChild("Emails", 40, expanded: true);
            emails.AddChild("Fakes", 40);
            emails.AddChild("Original", 40);

            var internetPrivat = privat.AddChild("Internet", 1, expanded: true);
            internetPrivat.AddChild("Online Shops", 1);
            internetPrivat.AddChild("Postdienste", 1);
            internetPrivat.AddChild("Portale", 1);
            internetPrivat.AddChild("Games", 1);
            internetPrivat.AddChild("Programme", 1);
            internetPrivat.AddChild("Webseiten", 1);

            privat.AddChild("Smarthome", 53);
            privat.AddChild("Financial", 66);

            var netzwerk = privat.AddChild("Netzwerk", 3, expanded: true);
            netzwerk.AddChild("Router", 3);
            netzwerk.AddChild("NAS", 3);
            netzwerk.AddChild("APs", 3);
            netzwerk.AddChild("Cams", 3);

            privat.AddChild("Betriebssysteme", 32);
            privat.AddChild("Karten", 58);

            var sf = alina.AddChild("Mein-Seelenfunke", 61, expanded: true);
            sf.AddChild("E-Mails", 19);
            sf.AddChild("Einkauf", 7);
            sf.AddChild("Social Media", 61);
            sf.AddChild("Shops", 3);
            sf.AddChild("Financial", 66);

            var internetSf = sf.AddChild("Internet", 1, expanded: true);
            internetSf.AddChild("Accounts", 1);

            var geraete = sf.AddChild("Geräte", 3, expanded: true);
            geraete.AddChild("Lenovo IdeaPad Pro 5", 3);

            var hosting = sf.AddChild("Hosting", 48, expanded: true);
            hosting.AddChild("Felix-Machts", 0);
            hosting.AddChild("Seelenfunke", 0);
            hosting.AddChild("Finance-Manager", 0);
            hosting.AddChild("Steinhauer-Invest", 0);
            hosting.AddChild("Fim", 0);

            sf.AddChild("Entwicklung", 0);

            db.RefreshHierarchyReferences();
            return db;
        }

        public List<KdbxGroup> GetDisplayGroups()
        {
            var list = new List<KdbxGroup>();
            void Walk(KdbxGroup g)
            {
                list.Add(g);
                foreach (var sub in g.SubGroups)
                    Walk(sub);
            }
            Walk(RootGroup);
            return list;
        }

        public List<KdbxEntry> GetAllEntriesFlat(bool includeRecycleBin = false)
        {
            var list = new List<KdbxEntry>();
            void Walk(KdbxGroup g)
            {
                bool isTrash = g.IsRecycleBin(RecycleBinUuidHex);
                if (!includeRecycleBin && isTrash)
                    return;

                foreach (var e in g.Entries)
                {
                    e.GroupUuidHex = g.UuidHex;
                    e.GroupName = g.Name;
                    list.Add(e);
                }
                foreach (var sub in g.SubGroups)
                    Walk(sub);
            }
            Walk(RootGroup);
            return list;
        }

        public KdbxGroup? FindGroupByUuid(string? uuidHex)
        {
            if (string.IsNullOrWhiteSpace(uuidHex)) return null;
            KdbxGroup? Match(KdbxGroup g)
            {
                if (string.Equals(g.UuidHex, uuidHex, StringComparison.OrdinalIgnoreCase))
                    return g;
                foreach (var sub in g.SubGroups)
                {
                    var m = Match(sub);
                    if (m != null) return m;
                }
                return null;
            }
            return Match(RootGroup);
        }

        public KdbxGroup EnsureRecycleBinGroup()
        {
            var existing = FindGroupByUuid(RecycleBinUuidHex)
                        ?? RootGroup.SubGroups.FirstOrDefault(g => g.IsRecycleBin(RecycleBinUuidHex));
            if (existing != null)
            {
                RecycleBinUuidHex = existing.UuidHex;
                return existing;
            }

            var trash = new KdbxGroup { Name = "Papierkorb", IconId = 43 };
            RecycleBinUuidHex = trash.UuidHex;
            RootGroup.SubGroups.Add(trash);
            return trash;
        }

        public (int ImportedGroups, int ImportedEntries, int UpdatedEntries, int SkippedDuplicates) MergeFrom(KdbxDatabase sourceDb)
        {
            int importedGroups = 0;
            int importedEntries = 0;
            int updatedEntries = 0;
            int skippedDuplicates = 0;

            var existingEntriesById = GetAllEntriesFlat(includeRecycleBin: true)
                .ToDictionary(e => e.UuidHex, e => e, StringComparer.OrdinalIgnoreCase);

            void MergeGroupEntriesInto(KdbxGroup srcGroup, KdbxGroup dstGroup)
            {
                foreach (var srcEntry in srcGroup.Entries)
                {
                    if (existingEntriesById.TryGetValue(srcEntry.UuidHex, out var existing))
                    {
                        if (srcEntry.ModifiedUtc > existing.ModifiedUtc.AddSeconds(1))
                        {
                            existing.Title = srcEntry.Title;
                            existing.UserName = srcEntry.UserName;
                            existing.Password = srcEntry.Password;
                            existing.Url = srcEntry.Url;
                            existing.Notes = srcEntry.Notes;
                            existing.Tags = srcEntry.Tags;
                            existing.ModifiedUtc = srcEntry.ModifiedUtc;
                            existing.CustomStrings.Clear();
                            foreach (var kv in srcEntry.CustomStrings)
                                existing.CustomStrings[kv.Key] = new KdbxCustomString { Value = kv.Value.Value, IsProtected = kv.Value.IsProtected };
                            existing.Attachments.Clear();
                            foreach (var att in srcEntry.Attachments)
                            {
                                byte[] b = new byte[att.Data.Length];
                                Buffer.BlockCopy(att.Data, 0, b, 0, att.Data.Length);
                                existing.Attachments.Add(new KdbxAttachment { FileName = att.FileName, Data = b });
                            }
                            updatedEntries++;
                        }
                        else
                        {
                            skippedDuplicates++;
                        }
                        continue;
                    }

                    // Also check content-identical entry in destination group (same Title + UserName + Password + Url)
                    bool exactContentMatch = dstGroup.Entries.Any(e =>
                        string.Equals(e.Title, srcEntry.Title, StringComparison.OrdinalIgnoreCase) &&
                        string.Equals(e.UserName, srcEntry.UserName, StringComparison.OrdinalIgnoreCase) &&
                        string.Equals(e.Password, srcEntry.Password, StringComparison.Ordinal) &&
                        string.Equals(e.Url, srcEntry.Url, StringComparison.OrdinalIgnoreCase));

                    if (exactContentMatch)
                    {
                        skippedDuplicates++;
                        continue;
                    }

                    var cloned = srcEntry.CloneEntry(newUuid: false);
                    cloned.GroupUuidHex = dstGroup.UuidHex;
                    cloned.GroupName = dstGroup.Name;
                    dstGroup.Entries.Add(cloned);
                    existingEntriesById[cloned.UuidHex] = cloned;
                    importedEntries++;
                }

                foreach (var srcSub in srcGroup.SubGroups)
                {
                    if (srcSub.IsRecycleBin(sourceDb.RecycleBinUuidHex) && srcSub.Entries.Count == 0 && srcSub.SubGroups.Count == 0)
                        continue;

                    var dstSub = dstGroup.SubGroups.FirstOrDefault(g =>
                        string.Equals(g.UuidHex, srcSub.UuidHex, StringComparison.OrdinalIgnoreCase) ||
                        string.Equals(g.Name, srcSub.Name, StringComparison.OrdinalIgnoreCase));

                    if (dstSub == null)
                    {
                        dstSub = new KdbxGroup
                        {
                            UuidHex = FindGroupByUuid(srcSub.UuidHex) == null ? srcSub.UuidHex : KdbxEntry.NewUuidHex(),
                            Name = srcSub.Name,
                            IconId = srcSub.IconId,
                            Notes = srcSub.Notes,
                            CreatedUtc = srcSub.CreatedUtc,
                            ModifiedUtc = srcSub.ModifiedUtc
                        };
                        dstGroup.SubGroups.Add(dstSub);
                        importedGroups++;
                    }

                    MergeGroupEntriesInto(srcSub, dstSub);
                }
            }

            // If source root has direct entries, place them in first non-trash group or root
            MergeGroupEntriesInto(sourceDb.RootGroup, RootGroup);
            if (importedEntries > 0 || updatedEntries > 0 || importedGroups > 0)
                IsDirty = true;

            return (importedGroups, importedEntries, updatedEntries, skippedDuplicates);
        }

        public void WipeAllInMemory()
        {
            void Walk(KdbxGroup g)
            {
                foreach (var e in g.Entries)
                    e.WipeSensitiveMemory();
                g.Entries.Clear();
                foreach (var sub in g.SubGroups)
                    Walk(sub);
                g.SubGroups.Clear();
            }
            Walk(RootGroup);
        }
    }

    // =========================================================================
    // RFC 6238 TOTP AUTHENTICATOR ENGINE (BASE32 + OTPAUTH:// URI)
    // =========================================================================
    public static class TotpEngine
    {
        private const string Base32Alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

        public static string ExtractBase32Secret(string rawInput)
        {
            if (string.IsNullOrWhiteSpace(rawInput)) return "";
            string trimmed = rawInput.Trim();

            if (trimmed.StartsWith("otpauth://", StringComparison.OrdinalIgnoreCase))
            {
                try
                {
                    var uri = new Uri(trimmed);
                    var query = HttpUtility.ParseQueryString(uri.Query);
                    string? sec = query["secret"];
                    if (!string.IsNullOrWhiteSpace(sec))
                        trimmed = sec;
                }
                catch { }
            }

            var sb = new StringBuilder(trimmed.Length);
            foreach (char c in trimmed.ToUpperInvariant())
            {
                if (Base32Alphabet.IndexOf(c) >= 0)
                    sb.Append(c);
            }
            return sb.ToString();
        }

        public static bool TryGenerateTotp(string secretOrUri, DateTimeOffset timestampUtc, out string formattedCode, out int remainingSeconds)
        {
            formattedCode = "";
            remainingSeconds = 30;
            if (string.IsNullOrWhiteSpace(secretOrUri)) return false;

            int digits = 6;
            int period = 30;
            string algo = "SHA1";

            string trimmed = secretOrUri.Trim();
            if (trimmed.StartsWith("otpauth://", StringComparison.OrdinalIgnoreCase))
            {
                try
                {
                    var uri = new Uri(trimmed);
                    var q = HttpUtility.ParseQueryString(uri.Query);
                    if (int.TryParse(q["digits"], out int d) && (d == 6 || d == 8)) digits = d;
                    if (int.TryParse(q["period"], out int p) && p >= 10 && p <= 120) period = p;
                    if (!string.IsNullOrWhiteSpace(q["algorithm"])) algo = q["algorithm"]!.Trim().ToUpperInvariant();
                }
                catch { }
            }

            string base32 = ExtractBase32Secret(trimmed);
            if (base32.Length < 8) return false;

            byte[]? keyBytes = null;
            try
            {
                keyBytes = DecodeBase32(base32);
                if (keyBytes.Length == 0) return false;

                long unixSeconds = timestampUtc.ToUnixTimeSeconds();
                long counter = unixSeconds / period;
                remainingSeconds = period - (int)(unixSeconds % period);

                byte[] counterBytes = BitConverter.GetBytes(counter);
                if (BitConverter.IsLittleEndian)
                    Array.Reverse(counterBytes);

                byte[] hash = algo switch
                {
                    "SHA256" => HMACSHA256.HashData(keyBytes, counterBytes),
                    "SHA512" => HMACSHA512.HashData(keyBytes, counterBytes),
                    _ => HMACSHA1.HashData(keyBytes, counterBytes)
                };

                int offset = hash[hash.Length - 1] & 0x0F;
                int binaryCode =
                    ((hash[offset] & 0x7F) << 24) |
                    ((hash[offset + 1] & 0xFF) << 16) |
                    ((hash[offset + 2] & 0xFF) << 8) |
                    (hash[offset + 3] & 0xFF);

                int mod = digits == 8 ? 100_000_000 : 1_000_000;
                int otp = binaryCode % mod;
                string raw = otp.ToString(digits == 8 ? "D8" : "D6");
                formattedCode = digits == 6
                    ? $"{raw.Substring(0, 3)} {raw.Substring(3, 3)}"
                    : $"{raw.Substring(0, 4)} {raw.Substring(4, 4)}";
                return true;
            }
            catch
            {
                return false;
            }
            finally
            {
                if (keyBytes != null) CryptographicOperations.ZeroMemory(keyBytes);
            }
        }

        public static byte[] DecodeBase32(string base32)
        {
            string clean = base32.Trim().TrimEnd('=').ToUpperInvariant();
            var bytes = new List<byte>((clean.Length * 5) / 8);
            int buffer = 0;
            int bitsLeft = 0;

            foreach (char c in clean)
            {
                int val = Base32Alphabet.IndexOf(c);
                if (val < 0) continue;
                buffer = (buffer << 5) | val;
                bitsLeft += 5;
                if (bitsLeft >= 8)
                {
                    bitsLeft -= 8;
                    bytes.Add((byte)((buffer >> bitsLeft) & 0xFF));
                }
            }
            return bytes.ToArray();
        }
    }

    // =========================================================================
    // CRYPTOGRAPHIC PASSWORD GENERATOR & OFFLINE SECURITY AUDIT ENGINE
    // =========================================================================
    public sealed class PasswordAuditIssue
    {
        public KdbxEntry Entry { get; set; } = null!;
        public string Severity { get; set; } = "Warnung"; // Kritisch | Warnung
        public string Reason { get; set; } = "";
        public string BadgeHex => Severity == "Kritisch" ? "#EF4444" : "#F59E0B";
    }

    public static class PasswordGeneratorEngine
    {
        private const string UpperChars = "ABCDEFGHJKLMNPQRSTUVWXYZ";
        private const string UpperAll = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        private const string LowerChars = "abcdefghijkmnopqrstuvwxyz";
        private const string LowerAll = "abcdefghijklmnopqrstuvwxyz";
        private const string DigitChars = "23456789";
        private const string DigitAll = "0123456789";
        private const string SymbolChars = "!@#$%^&*()-_=+[]{};:,.?";

        private static readonly string[] PassphraseWords =
        {
            "Atelier", "Seelenfunke", "Obsidian", "Goldglanz", "Nordlicht", "Kristall", "Sternenstaub", "Manufaktur",
            "Bergwerk", "Bernstein", "Kompass", "Horizont", "Leuchtturm", "Saphir", "Phönix", "Gletscher",
            "Komet", "Uhrwerk", "Smaragd", "Wellenbrecher", "Tiefenraum", "ankern", "funkeln", "schmieden",
            "bewahren", "leuchten", "fliegen", "wachsen", "Falke", "Luchs", "Orkan", "Nebelwald",
            "Mondschein", "Sonnenwende", "Polarstern", "Silberpfeil", "Eichenholz", "Feuerstein", "Granit", "Titan"
        };

        public static string GeneratePassword(int length = 20, bool upper = true, bool lower = true, bool digits = true, bool symbols = true, bool avoidAmbiguous = true)
        {
            int len = Math.Clamp(length, 8, 64);
            string u = avoidAmbiguous ? UpperChars : UpperAll;
            string l = avoidAmbiguous ? LowerChars : LowerAll;
            string d = avoidAmbiguous ? DigitChars : DigitAll;
            string s = SymbolChars;

            var requiredClasses = new List<string>();
            var pool = new StringBuilder();
            if (upper) { requiredClasses.Add(u); pool.Append(u); }
            if (lower) { requiredClasses.Add(l); pool.Append(l); }
            if (digits) { requiredClasses.Add(d); pool.Append(d); }
            if (symbols) { requiredClasses.Add(s); pool.Append(s); }

            if (pool.Length == 0)
            {
                requiredClasses.Add(LowerAll);
                requiredClasses.Add(DigitAll);
                pool.Append(LowerAll).Append(DigitAll);
            }

            string poolStr = pool.ToString();
            char[] result = new char[len];
            int idx = 0;

            foreach (string cls in requiredClasses)
            {
                if (idx < len)
                    result[idx++] = cls[RandomNumberGenerator.GetInt32(cls.Length)];
            }

            for (; idx < len; idx++)
                result[idx] = poolStr[RandomNumberGenerator.GetInt32(poolStr.Length)];

            // Fisher-Yates cryptographic shuffle
            for (int i = len - 1; i > 0; i--)
            {
                int j = RandomNumberGenerator.GetInt32(i + 1);
                (result[i], result[j]) = (result[j], result[i]);
            }

            return new string(result);
        }

        public static string GeneratePassphrase(int wordCount = 4, string separator = "-")
        {
            int count = Math.Clamp(wordCount, 3, 7);
            var words = new string[count];
            for (int i = 0; i < count; i++)
                words[i] = PassphraseWords[RandomNumberGenerator.GetInt32(PassphraseWords.Length)];

            int num = RandomNumberGenerator.GetInt32(10, 99);
            return string.Join(separator, words) + separator + num;
        }

        public static double CalculateEntropyBits(string? password)
        {
            if (string.IsNullOrEmpty(password)) return 0;
            bool hasLower = false, hasUpper = false, hasDigit = false, hasSym = false;
            foreach (char c in password)
            {
                if (char.IsLower(c)) hasLower = true;
                else if (char.IsUpper(c)) hasUpper = true;
                else if (char.IsDigit(c)) hasDigit = true;
                else hasSym = true;
            }

            int pool = (hasLower ? 26 : 0) + (hasUpper ? 26 : 0) + (hasDigit ? 10 : 0) + (hasSym ? 32 : 0);
            if (pool == 0) return 0;
            int uniqueChars = password.Distinct().Count();
            double effectiveLen = (password.Length * 0.7) + (uniqueChars * 0.3);
            return Math.Round(effectiveLen * Math.Log2(pool), 1);
        }

        public static (string Label, string ColorHex, double ProgressRatio) EvaluateStrength(string? password)
        {
            double bits = CalculateEntropyBits(password);
            int len = password?.Length ?? 0;

            if (len == 0) return ("Leer", "#6B7280", 0.0);
            if (len < 8 || bits < 40) return ($"Schwach ({bits:0} Bit)", "#EF4444", 0.25);
            if (len < 12 || bits < 62) return ($"Mittel ({bits:0} Bit)", "#F59E0B", 0.55);
            if (bits < 85) return ($"Stark ({bits:0} Bit)", "#10B981", 0.82);
            return ($"Sehr stark ({bits:0} Bit)", "#10B981", 1.0);
        }

        public static List<PasswordAuditIssue> AuditEntries(IEnumerable<KdbxEntry> entries)
        {
            var list = entries.ToList();
            var issues = new List<PasswordAuditIssue>();

            var duplicateGroups = list
                .Where(e => !string.IsNullOrEmpty(e.Password))
                .GroupBy(e => e.Password, StringComparer.Ordinal)
                .Where(g => g.Count() > 1)
                .ToDictionary(g => g.Key, g => g.Count(), StringComparer.Ordinal);

            foreach (var e in list)
            {
                if (string.IsNullOrEmpty(e.Password))
                {
                    issues.Add(new PasswordAuditIssue { Entry = e, Severity = "Kritisch", Reason = "Kein Passwort hinterlegt" });
                    continue;
                }

                double bits = CalculateEntropyBits(e.Password);
                if (e.Password.Length < 10 || bits < 45)
                {
                    issues.Add(new PasswordAuditIssue { Entry = e, Severity = "Kritisch", Reason = $"Schwaches Passwort ({e.Password.Length} Zeichen · {bits:0} Bit)" });
                }

                if (duplicateGroups.TryGetValue(e.Password, out int dupCount))
                {
                    issues.Add(new PasswordAuditIssue { Entry = e, Severity = "Warnung", Reason = $"Mehrfach verwendet ({dupCount}× identisch)" });
                }
            }

            return issues
                .OrderByDescending(i => i.Severity == "Kritisch")
                .ThenBy(i => i.Entry.Title)
                .ToList();
        }
    }
}
