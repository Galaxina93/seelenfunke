using System;
using System.Diagnostics;
using System.IO;
using System.IO.Compression;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace Funkenwerk
{
    public sealed class WindowsOtaManifestDto
    {
        public int VersionCode { get; init; }
        public string VersionName { get; init; } = "";
        public string Sha256 { get; init; } = "";
        public long SizeBytes { get; init; }
        public string PublishedAt { get; init; } = "";
        public string? ReleaseNotes { get; init; }
    }

    public static class FunkenwerkAutoUpdater
    {
        public static async Task<WindowsOtaManifestDto?> CheckForUpdateAsync(
            HttpClient http,
            string apiBaseUrl,
            string bearerToken,
            CancellationToken ct = default)
        {
            if (string.IsNullOrWhiteSpace(apiBaseUrl) || string.IsNullOrWhiteSpace(bearerToken))
            {
                return null;
            }

            try
            {
                string cleanBase = apiBaseUrl.Trim().TrimEnd('/');
                using var cts = CancellationTokenSource.CreateLinkedTokenSource(ct);
                cts.CancelAfter(TimeSpan.FromSeconds(8));

                using var req = new HttpRequestMessage(HttpMethod.Get, $"{cleanBase}/funki/system/windows-update/check");
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearerToken.Trim());

                using var res = await http.SendAsync(req, cts.Token).ConfigureAwait(false);
                if (!res.IsSuccessStatusCode)
                {
                    return null;
                }

                string json = await res.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                using var doc = JsonDocument.Parse(json);
                var root = doc.RootElement;

                bool hasRelease = root.TryGetProperty("has_release", out var hr) && hr.ValueKind == JsonValueKind.True;
                if (!hasRelease || !root.TryGetProperty("data", out var data) || data.ValueKind != JsonValueKind.Object)
                {
                    return null;
                }

                int versionCode = data.TryGetProperty("version_code", out var vc) && vc.ValueKind == JsonValueKind.Number
                    ? vc.GetInt32()
                    : 0;
                string versionName = data.TryGetProperty("version_name", out var vn) && vn.ValueKind == JsonValueKind.String
                    ? (vn.GetString() ?? "").Trim()
                    : "";
                string sha256 = data.TryGetProperty("sha256", out var sh) && sh.ValueKind == JsonValueKind.String
                    ? (sh.GetString() ?? "").Trim().ToLowerInvariant()
                    : "";
                long sizeBytes = data.TryGetProperty("size_bytes", out var sb) && sb.ValueKind == JsonValueKind.Number
                    ? sb.GetInt64()
                    : 0L;
                string publishedAt = data.TryGetProperty("published_at", out var pa) && pa.ValueKind == JsonValueKind.String
                    ? (pa.GetString() ?? "")
                    : "";
                string? notes = data.TryGetProperty("release_notes", out var rn) && rn.ValueKind == JsonValueKind.String
                    ? rn.GetString()
                    : null;

                if (versionCode <= FunkenwerkVersioning.VersionCode || string.IsNullOrWhiteSpace(versionName) || sha256.Length != 64)
                {
                    return null;
                }

                return new WindowsOtaManifestDto
                {
                    VersionCode = versionCode,
                    VersionName = versionName,
                    Sha256 = sha256,
                    SizeBytes = sizeBytes,
                    PublishedAt = publishedAt,
                    ReleaseNotes = notes
                };
            }
            catch
            {
                return null;
            }
        }

        public static async Task<(bool Ok, string Message)> DownloadVerifyAndApplyUpdateAsync(
            HttpClient http,
            string apiBaseUrl,
            string bearerToken,
            WindowsOtaManifestDto manifest,
            IProgress<int>? progress = null,
            CancellationToken ct = default)
        {
            if (manifest == null || manifest.Sha256.Length != 64)
            {
                return (false, "Ungültiges Update-Manifest.");
            }

            string stagingRoot = Path.Combine(FunkenwerkSharedSession.GetSharedDataDirectory(), "ota_staging");
            string zipPath = Path.Combine(stagingRoot, $"Funkenwerk-Suite-v{manifest.VersionName}-b{manifest.VersionCode}.zip");
            string unpackDir = Path.Combine(stagingRoot, "unpacked");

            try
            {
                if (Directory.Exists(unpackDir))
                {
                    Directory.Delete(unpackDir, recursive: true);
                }
                Directory.CreateDirectory(stagingRoot);
                Directory.CreateDirectory(unpackDir);

                string cleanBase = apiBaseUrl.Trim().TrimEnd('/');
                using var req = new HttpRequestMessage(HttpMethod.Get, $"{cleanBase}/funki/system/windows-update/download");
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearerToken.Trim());

                using var res = await http.SendAsync(req, HttpCompletionOption.ResponseHeadersRead, ct).ConfigureAwait(false);
                if (!res.IsSuccessStatusCode)
                {
                    return (false, $"Download fehlgeschlagen (HTTP {(int)res.StatusCode}).");
                }

                long totalBytes = res.Content.Headers.ContentLength ?? manifest.SizeBytes;
                await using (var netStream = await res.Content.ReadAsStreamAsync(ct).ConfigureAwait(false))
                await using (var fileStream = new FileStream(zipPath, FileMode.Create, FileAccess.Write, FileShare.None, 81920, useAsync: true))
                {
                    byte[] buffer = new byte[81920];
                    long received = 0;
                    int lastPct = -1;
                    int read;
                    while ((read = await netStream.ReadAsync(buffer.AsMemory(0, buffer.Length), ct).ConfigureAwait(false)) > 0)
                    {
                        await fileStream.WriteAsync(buffer.AsMemory(0, read), ct).ConfigureAwait(false);
                        received += read;
                        if (totalBytes > 0 && progress != null)
                        {
                            int pct = (int)Math.Clamp(received * 100L / totalBytes, 0L, 100L);
                            if (pct != lastPct)
                            {
                                lastPct = pct;
                                progress.Report(pct);
                            }
                        }
                    }
                }

                string actualSha;
                await using (var verifyStream = new FileStream(zipPath, FileMode.Open, FileAccess.Read, FileShare.Read))
                {
                    byte[] hash = await SHA256.HashDataAsync(verifyStream, ct).ConfigureAwait(false);
                    actualSha = Convert.ToHexString(hash).ToLowerInvariant();
                }

                if (!CryptographicOperations.FixedTimeEquals(
                        Encoding.ASCII.GetBytes(actualSha),
                        Encoding.ASCII.GetBytes(manifest.Sha256.ToLowerInvariant())))
                {
                    try { File.Delete(zipPath); } catch { }
                    return (false, "SHA-256 Signaturprüfung fehlgeschlagen. Update wurde verworfen.");
                }

                // Safe extraction with strict Zip-Slip protection
                string fullUnpackRoot = Path.GetFullPath(unpackDir);
                if (!fullUnpackRoot.EndsWith(Path.DirectorySeparatorChar))
                {
                    fullUnpackRoot += Path.DirectorySeparatorChar;
                }

                using (var archive = ZipFile.OpenRead(zipPath))
                {
                    foreach (var entry in archive.Entries)
                    {
                        string normEntry = entry.FullName.Replace('/', Path.DirectorySeparatorChar);
                        if (string.IsNullOrWhiteSpace(normEntry)) continue;

                        string destPath = Path.GetFullPath(Path.Combine(fullUnpackRoot, normEntry));
                        if (!destPath.StartsWith(fullUnpackRoot, StringComparison.OrdinalIgnoreCase))
                        {
                            try { Directory.Delete(unpackDir, recursive: true); } catch { }
                            return (false, "Ungültiger Archiv-Pfad (Zip-Slip blockiert).");
                        }

                        if (string.IsNullOrEmpty(entry.Name))
                        {
                            Directory.CreateDirectory(destPath);
                            continue;
                        }

                        string? parentDir = Path.GetDirectoryName(destPath);
                        if (!string.IsNullOrEmpty(parentDir))
                        {
                            Directory.CreateDirectory(parentDir);
                        }
                        entry.ExtractToFile(destPath, overwrite: true);
                    }
                }

                // Determine target directory:
                // - If archive contains "Funkenwerk\Funkenwerk.exe" or "Module\...", apply to CollectionRoot
                // - If archive contains "Funkenwerk.exe" directly at top-level, apply to CollectionRoot\Funkenwerk
                string collectionRoot = CollectionModuleRegistry.ResolveCollectionRoot();
                string sourceCopyRoot = unpackDir;
                string targetInstallDir = collectionRoot;

                if (File.Exists(Path.Combine(unpackDir, "Funkenwerk.exe")) &&
                    !Directory.Exists(Path.Combine(unpackDir, "Funkenwerk")))
                {
                    targetInstallDir = Path.Combine(collectionRoot, "Funkenwerk");
                }
                else
                {
                    var topDirs = Directory.GetDirectories(unpackDir);
                    var topFiles = Directory.GetFiles(unpackDir);
                    if (topDirs.Length == 1 && topFiles.Length == 0 &&
                        string.Equals(Path.GetFileName(topDirs[0]), "Funkenwerk Collection", StringComparison.OrdinalIgnoreCase))
                    {
                        sourceCopyRoot = topDirs[0];
                    }
                }

                string mainExeToRestart = Path.Combine(collectionRoot, "Funkenwerk", "Funkenwerk.exe");
                if (!File.Exists(mainExeToRestart))
                {
                    string currentProcExe = Environment.ProcessPath ?? "";
                    if (!string.IsNullOrWhiteSpace(currentProcExe) && File.Exists(currentProcExe))
                    {
                        mainExeToRestart = currentProcExe;
                    }
                }

                string scriptPath = Path.Combine(stagingRoot, "apply_funkenwerk_ota.ps1");
                int currentPid = Environment.ProcessId;
                string psScript = BuildDetachedSwapScript(currentPid, sourceCopyRoot, targetInstallDir, mainExeToRestart, zipPath);
                await File.WriteAllTextAsync(scriptPath, psScript, new UTF8Encoding(encoderShouldEmitUTF8Identifier: true), ct).ConfigureAwait(false);

                var psi = new ProcessStartInfo
                {
                    FileName = "powershell.exe",
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    WindowStyle = ProcessWindowStyle.Hidden
                };
                psi.ArgumentList.Add("-NoProfile");
                psi.ArgumentList.Add("-ExecutionPolicy");
                psi.ArgumentList.Add("Bypass");
                psi.ArgumentList.Add("-File");
                psi.ArgumentList.Add(scriptPath);

                Process.Start(psi);
                return (true, $"Update v{manifest.VersionName} (Build #{manifest.VersionCode}) wird installiert...");
            }
            catch (Exception ex)
            {
                return (false, $"Update-Fehler: {ex.Message}");
            }
        }

        private static string EscapePsSingleQuoted(string value)
        {
            return (value ?? "").Replace("'", "''");
        }

        private static string BuildDetachedSwapScript(
            int parentPid,
            string sourceDir,
            string targetDir,
            string restartExePath,
            string zipFileToClean)
        {
            string sSrc = EscapePsSingleQuoted(sourceDir);
            string sDst = EscapePsSingleQuoted(targetDir);
            string sExe = EscapePsSingleQuoted(restartExePath);
            string sZip = EscapePsSingleQuoted(zipFileToClean);

            return $@"$ErrorActionPreference = 'SilentlyContinue'
$parentPid = {parentPid}
for ($i = 0; $i -lt 30; $i++) {{
    $p = Get-Process -Id $parentPid -ErrorAction SilentlyContinue
    if (-not $p) {{ break }}
    Start-Sleep -Milliseconds 300
}}
Start-Sleep -Milliseconds 400
New-Item -ItemType Directory -Force -Path '{sDst}' | Out-Null
Copy-Item -Path (Join-Path '{sSrc}' '*') -Destination '{sDst}' -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath '{sZip}' -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath '{sSrc}' -Recurse -Force -ErrorAction SilentlyContinue
if (Test-Path -LiteralPath '{sExe}') {{
    Start-Process -FilePath '{sExe}' -WorkingDirectory (Split-Path -Parent '{sExe}')
}}
";
        }
    }
}
