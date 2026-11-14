using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;

namespace FunkenwerkMcp
{
    public static class McpCraftBridge
    {
        private static readonly Regex SafeProjectNameRegex = new(@"^[A-Za-z0-9 _\-\.]{1,80}$", RegexOptions.Compiled);

        public static readonly IReadOnlySet<string> AllowedFilmCraftBridgeMethods = new HashSet<string>(StringComparer.Ordinal)
        {
            "engine.ping",
            "engine.status",
            "engine.version",
            "engine.commands",
            "project.info",
            "project.status",
            "sequence.info",
            "timeline.info",
            "playback.status"
        };

        private static readonly HashSet<string> AllowedMediaExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".mp4", ".mov", ".mkv", ".webm", ".avi", ".m4v",
            ".jpg", ".jpeg", ".png", ".webp", ".bmp"
        };

        private static readonly HashSet<string> AllowedAudioExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".wav", ".mp3", ".ogg", ".flac", ".m4a"
        };

        public static string ValidateSafeProjectName(string? rawName)
        {
            if (string.IsNullOrWhiteSpace(rawName))
                throw new ArgumentException("projectName darf nicht leer sein.");

            string trimmed = rawName.Trim();
            if (!SafeProjectNameRegex.IsMatch(trimmed) ||
                trimmed.Contains("..", StringComparison.Ordinal) ||
                trimmed == "." ||
                trimmed.EndsWith('.'))
            {
                throw new UnauthorizedAccessException("Ungueltiger projectName: Nur [A-Za-z0-9 _-.] (1-80 Zeichen, ohne '..') erlaubt.");
            }

            return trimmed;
        }

        public static string ValidateAllowedFilmCraftBridgeMethod(string? method)
        {
            if (string.IsNullOrWhiteSpace(method))
                throw new ArgumentException("RPC-Methode fuer filmcraft_bridge darf nicht leer sein.");

            string trimmed = method.Trim();
            if (!AllowedFilmCraftBridgeMethods.Contains(trimmed))
                throw new UnauthorizedAccessException($"FilmCraft-Bridge RPC-Methode '{trimmed}' ist nicht in der Sicherheits-Allowlist enthalten.");

            return trimmed;
        }

        public static string ValidateSafeCraftWritePath(string? rawPath, string[] allowedExtensions)
        {
            if (string.IsNullOrWhiteSpace(rawPath))
                throw new ArgumentException("Zielpfad darf nicht leer sein.");
            if (rawPath.Length > 1024)
                throw new UnauthorizedAccessException("Zielpfad ueberschreitet Maximallaenge.");

            string trimmed = rawPath.Trim().Trim('"');
            if (trimmed.IndexOf('\0') >= 0 || trimmed.Any(char.IsControl))
                throw new UnauthorizedAccessException("Steuerzeichen im Zielpfad blockiert.");

            if (trimmed.StartsWith(@"\\", StringComparison.Ordinal) ||
                trimmed.StartsWith("//", StringComparison.Ordinal) ||
                trimmed.Contains("..", StringComparison.Ordinal))
            {
                throw new UnauthorizedAccessException("UNC-/Device-Pfade und Pfad-Traversal ('..') sind fuer Craft-Schreibpfade strikt gesperrt.");
            }

            int colon = trimmed.IndexOf(':');
            if (colon >= 0 && !(colon == 1 && char.IsLetter(trimmed[0]) && trimmed.IndexOf(':', 2) < 0))
                throw new UnauthorizedAccessException("NTFS Alternate Data Streams (ADS) sind gesperrt.");

            string full = Path.GetFullPath(trimmed);
            string ext = Path.GetExtension(full).ToLowerInvariant();
            if (string.IsNullOrEmpty(ext) || allowedExtensions == null || !allowedExtensions.Any(e => string.Equals(e, ext, StringComparison.OrdinalIgnoreCase)))
                throw new UnauthorizedAccessException($"Dateierweiterung '{ext}' ist fuer diesen Craft-Schreibvorgang nicht freigegeben.");

            string userProfile = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
            var allowedWriteRoots = new List<string>
            {
                EnsureTrailingSlash(CraftVideosDir),
                EnsureTrailingSlash(Path.Combine(CraftRootDir, "Projects")),
                EnsureTrailingSlash(Path.Combine(CraftRootDir, "Exports")),
                EnsureTrailingSlash(Path.Combine(userProfile, "Documents", "Funkenwerk Craft")),
                EnsureTrailingSlash(Path.Combine(userProfile, "Videos", "Funkenwerk Craft")),
                EnsureTrailingSlash(Path.Combine(userProfile, "Videos", "Exports")),
                EnsureTrailingSlash(Path.Combine(userProfile, "Videos"))
            };

            bool insideSandbox = allowedWriteRoots.Any(prefix => full.StartsWith(prefix, StringComparison.OrdinalIgnoreCase));
            if (!insideSandbox)
            {
                throw new UnauthorizedAccessException("Zielpfad liegt ausserhalb der freigegebenen Craft-/Videos-/Exports-Schreibsandbox.");
            }

            if (McpSecurityGuard.HasReparsePointInAncestry(full))
                throw new UnauthorizedAccessException("Reparse-Points/Symlinks/Junctions in Pfadhierarchie sind strikt blockiert.");

            return full;
        }

        public static string CraftRootDir =>
            Path.Combine(McpSecurityGuard.CollectionRootDir, "Funkenwerk Craft");

        public static string CraftExePath =>
            Path.Combine(CraftRootDir, "FunkenwerkCraft.exe");

        public static string CraftVideosDir =>
            Path.Combine(CraftRootDir, "Videos");

        public static string CraftSoundsDir =>
            Path.Combine(CraftRootDir, "Sounds");

        public static string SoundCraftCliPath =>
            Path.Combine(CraftRootDir, "engine", "soundcraft", "soundcraft-cli.exe");

        public static string FilmCraftCliPath =>
            Path.Combine(CraftRootDir, "engine", "filmcraft", "filmcraft-cli.exe");

        private static string CraftPipeName
        {
            get
            {
                string sid = WindowsIdentity.GetCurrent().User?.Value ?? Environment.UserName;
                return $"Funkenwerk_Craft_IPC_{sid}";
            }
        }

        public static string ValidateSafeMediaSourceOrCraftPath(string rawPath, bool allowAudio = false, bool allowDirectories = false)
        {
            if (string.IsNullOrWhiteSpace(rawPath))
                throw new ArgumentException("Pfad darf nicht leer sein.");
            if (rawPath.Length > 1024)
                throw new UnauthorizedAccessException("Pfad überschreitet Maximallänge.");

            string trimmed = rawPath.Trim().Trim('"');
            if (trimmed.IndexOf('\0') >= 0 || trimmed.Any(char.IsControl))
                throw new UnauthorizedAccessException("Steuerzeichen im Pfad blockiert.");
            if (trimmed.StartsWith(@"\\", StringComparison.Ordinal) ||
                trimmed.StartsWith("//", StringComparison.Ordinal) ||
                trimmed.Contains("..", StringComparison.Ordinal))
            {
                throw new UnauthorizedAccessException("UNC-/Device-Pfade und Pfad-Traversal ('..') sind im Sicherheitsfilter gesperrt.");
            }

            int colon = trimmed.IndexOf(':');
            if (colon >= 0 && !(colon == 1 && char.IsLetter(trimmed[0]) && trimmed.IndexOf(':', 2) < 0))
                throw new UnauthorizedAccessException("NTFS Alternate Data Streams (ADS) sind gesperrt.");

            string full = Path.GetFullPath(trimmed);

            string userProfile = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
            var allowedPrefixes = new List<string>
            {
                EnsureTrailingSlash(CraftVideosDir),
                EnsureTrailingSlash(CraftSoundsDir),
                EnsureTrailingSlash(Path.Combine(userProfile, "Videos")),
                EnsureTrailingSlash(Path.Combine(userProfile, "Pictures")),
                EnsureTrailingSlash(Path.Combine(userProfile, "Downloads"))
            };
            if (Directory.Exists(@"Y:\Medien"))
                allowedPrefixes.Add(@"Y:\Medien\");

            string checkTarget = allowDirectories && Directory.Exists(full) ? EnsureTrailingSlash(full) : full;
            bool allowed = allowedPrefixes.Any(prefix => checkTarget.StartsWith(prefix, StringComparison.OrdinalIgnoreCase));
            if (!allowed)
            {
                throw new UnauthorizedAccessException(
                    "Medienpfad liegt ausserhalb der freigegebenen Medienbereiche (Y:\\Medien, Funkenwerk Craft\\Videos, Funkenwerk Craft\\Sounds, Videos, Pictures, Downloads).");
            }

            if (McpSecurityGuard.HasReparsePointInAncestry(full))
                throw new UnauthorizedAccessException("Reparse-Points/Symlinks/Junctions in Pfadhierarchie sind blockiert.");

            if (allowDirectories && Directory.Exists(full))
            {
                var di = new DirectoryInfo(full);
                if ((di.Attributes & FileAttributes.ReparsePoint) != 0)
                    throw new UnauthorizedAccessException("Reparse-Points/Symlinks sind blockiert.");
                return full;
            }

            if (!File.Exists(full))
                throw new FileNotFoundException($"Mediendatei nicht gefunden: {full}");

            var fi = new FileInfo(full);
            if ((fi.Attributes & FileAttributes.ReparsePoint) != 0)
                throw new UnauthorizedAccessException("Symlinks sind blockiert.");

            string ext = fi.Extension.ToLowerInvariant();
            bool extOk = AllowedMediaExtensions.Contains(ext) || (allowAudio && AllowedAudioExtensions.Contains(ext));
            if (!extOk)
                throw new UnauthorizedAccessException($"Dateityp '{ext}' ist für Craft-Medienwerkzeuge nicht freigegeben.");

            return full;
        }

        private static string EnsureTrailingSlash(string p) =>
            Path.GetFullPath(p).TrimEnd('\\', '/') + "\\";

        public static async Task<JsonNode> InvokeCraftActionAsync(JsonObject requestObj, int timeoutSec = 240)
        {
            if (!File.Exists(CraftExePath))
                throw new FileNotFoundException($"FunkenwerkCraft.exe nicht gefunden unter: {CraftExePath}");

            string reqJson = requestObj.ToJsonString();

            // 1. Try fast CurrentUser Named Pipe first if Funkenwerk Craft UI is running (keeps UI synced live!)
            try
            {
                if (Process.GetProcessesByName("FunkenwerkCraft").Length > 0)
                {
                    using var pipeCts = new CancellationTokenSource(TimeSpan.FromSeconds(Math.Min(timeoutSec, 15)));
                    using var pipe = new NamedPipeClientStream(".", CraftPipeName, PipeDirection.InOut, PipeOptions.Asynchronous | PipeOptions.CurrentUserOnly);
                    await pipe.ConnectAsync(1200, pipeCts.Token);
                    await using var writer = new StreamWriter(pipe, new UTF8Encoding(false), 4096, leaveOpen: true) { AutoFlush = true };
                    using var reader = new StreamReader(pipe, Encoding.UTF8, false, 4096, leaveOpen: true);

                    string action = requestObj["action"]?.GetValue<string>() ?? "";
                    if (action != "render_video")
                    {
                        await writer.WriteLineAsync(reqJson);
                        string? line = await reader.ReadLineAsync(pipeCts.Token);
                        if (!string.IsNullOrWhiteSpace(line))
                        {
                            var parsed = JsonNode.Parse(line);
                            if (parsed != null) return parsed;
                        }
                    }
                }
            }
            catch { }

            // 2. Deterministic headless CLI invocation via ACL-locked temporary UTF-8 JSON request file
            string tmpDir = Path.Combine(McpSecurityGuard.VaultDirectory, "tmp");
            Directory.CreateDirectory(tmpDir);
            McpSecurityGuard.LockDownDirectoryAcl(tmpDir);
            string tempReqFile = Path.Combine(tmpDir, $"_craft_req_{Guid.NewGuid():N}.json");
            try
            {
                byte[] reqBytes = new UTF8Encoding(false).GetBytes(reqJson);
                await using (var fs = new FileStream(tempReqFile, FileMode.CreateNew, FileAccess.Write, FileShare.None, 4096, FileOptions.Asynchronous))
                {
                    await fs.WriteAsync(reqBytes, 0, reqBytes.Length);
                    await fs.FlushAsync();
                }
                McpSecurityGuard.LockDownFileAcl(tempReqFile);

                var psi = new ProcessStartInfo
                {
                    FileName = CraftExePath,
                    WorkingDirectory = CraftRootDir,
                    UseShellExecute = false,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    CreateNoWindow = true,
                    StandardOutputEncoding = Encoding.UTF8,
                    StandardErrorEncoding = Encoding.UTF8
                };
                psi.ArgumentList.Add("--cli");
                psi.ArgumentList.Add(tempReqFile);

                using var proc = Process.Start(psi) ?? throw new InvalidOperationException("FunkenwerkCraft CLI konnte nicht gestartet werden.");
                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(timeoutSec));
                try
                {
                    var stdoutTask = proc.StandardOutput.ReadToEndAsync(cts.Token);
                    var stderrTask = proc.StandardError.ReadToEndAsync(cts.Token);
                    await Task.WhenAll(stdoutTask, stderrTask);
                    await proc.WaitForExitAsync(cts.Token);

                    string stdout = await stdoutTask;
                    string stderr = await stderrTask;
                    if (string.IsNullOrWhiteSpace(stdout))
                        throw new InvalidOperationException($"Keine Antwort von FunkenwerkCraft CLI ({stderr.Trim()}).");

                    return JsonNode.Parse(stdout.Trim()) ?? new JsonObject { ["ok"] = false, ["raw"] = stdout.Trim() };
                }
                catch (OperationCanceledException)
                {
                    try { proc.Kill(entireProcessTree: true); } catch { }
                    throw new TimeoutException($"FunkenwerkCraft CLI Zeitlimit ({timeoutSec}s) ueberschritten.");
                }
            }
            finally
            {
                try { if (File.Exists(tempReqFile)) File.Delete(tempReqFile); } catch { }
            }
        }

        public static object ScanMedienProductCoverage()
        {
            string produkteRoot = @"Y:\Medien\Produkte";
            if (!Directory.Exists(produkteRoot))
                return new { available = false, path = produkteRoot };

            var report = new List<object>();
            foreach (string prodDir in Directory.GetDirectories(produkteRoot).OrderBy(Path.GetFileName))
            {
                string prodName = Path.GetFileName(prodDir);
                string bilderDir = Path.Combine(prodDir, "Bilder");
                string videosDir = Path.Combine(prodDir, "Videos");

                var filledCategories = new Dictionary<string, int>();
                var missingCategories = new List<string>();

                if (Directory.Exists(bilderDir))
                {
                    foreach (string catDir in Directory.GetDirectories(bilderDir).OrderBy(Path.GetFileName))
                    {
                        string catName = Path.GetFileName(catDir);
                        int count = Directory.GetFiles(catDir).Count(f => AllowedMediaExtensions.Contains(Path.GetExtension(f)));
                        if (count > 0) filledCategories[catName] = count;
                        else missingCategories.Add(catName);
                    }
                }

                int videoCount = Directory.Exists(videosDir)
                    ? Directory.GetFiles(videosDir).Count(f => AllowedMediaExtensions.Contains(Path.GetExtension(f)))
                    : 0;

                report.Add(new
                {
                    product = prodName,
                    videoCount,
                    filledImageCategories = filledCategories,
                    missingImageCategories = missingCategories
                });
            }

            return new
            {
                available = true,
                medienRoot = @"Y:\Medien",
                topFolders = Directory.GetDirectories(@"Y:\Medien").Select(Path.GetFileName).OrderBy(x => x).ToList(),
                productCoverage = report
            };
        }

        public static async Task<string> RunSoundCraftCliSafeAsync(string subcommand, string? audioFilePath = null)
        {
            if (!File.Exists(SoundCraftCliPath))
                throw new FileNotFoundException($"soundcraft-cli.exe nicht gefunden: {SoundCraftCliPath}");

            var psi = new ProcessStartInfo
            {
                FileName = SoundCraftCliPath,
                WorkingDirectory = Path.GetDirectoryName(SoundCraftCliPath)!,
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true,
                StandardOutputEncoding = Encoding.UTF8
            };

            switch (subcommand)
            {
                case "commands":
                    psi.ArgumentList.Add("commands");
                    break;
                case "plugins":
                    psi.ArgumentList.Add("plugins");
                    break;
                case "info":
                    string safeAudio = ValidateSafeMediaSourceOrCraftPath(
                        audioFilePath ?? throw new ArgumentException("Für 'info' ist 'filePath' erforderlich."),
                        allowAudio: true);
                    psi.ArgumentList.Add("info");
                    psi.ArgumentList.Add(safeAudio);
                    break;
                default:
                    throw new ArgumentException("Erlaubte SoundCraft-Subcommands: commands, plugins, info.");
            }

            using var proc = Process.Start(psi) ?? throw new InvalidOperationException("soundcraft-cli Start fehlgeschlagen.");
            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(25));
            try
            {
                var stdoutTask = proc.StandardOutput.ReadToEndAsync(cts.Token);
                var stderrTask = proc.StandardError.ReadToEndAsync(cts.Token);
                await Task.WhenAll(stdoutTask, stderrTask);
                await proc.WaitForExitAsync(cts.Token);

                string stdout = await stdoutTask;
                string stderr = await stderrTask;
                return string.IsNullOrWhiteSpace(stdout) ? stderr.Trim() : stdout.Trim();
            }
            catch (OperationCanceledException)
            {
                try { proc.Kill(entireProcessTree: true); } catch { }
                throw new TimeoutException("soundcraft-cli Zeitlimit (25s) ueberschritten.");
            }
        }
    }
}
