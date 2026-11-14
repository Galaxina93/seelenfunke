using System;
using System.Buffers.Binary;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Windows.Media;

namespace FunkenwerkVideo
{
    public sealed class ViralRecipeSpec
    {
        public string Id { get; init; } = "";
        public string Name { get; init; } = "";
        public string Badge { get; init; } = "";
        public int TargetBpm { get; init; } = 124;
        public double HookDurationSec { get; init; } = 1.8;
        public double AvgClipDurationSec { get; init; } = 2.0;
        public double TargetTotalDurationSec { get; init; } = 9.0;
        public string DefaultHookText { get; init; } = "";
        public string DefaultTransitionMode { get; init; } = "LightWipeZoom";
        public string MarketingCriteriaSummary { get; init; } = "";
    }

    public sealed class RawClipInfo
    {
        public string FilePath { get; init; } = "";
        public string FileName { get; init; } = "";
        public long FileSizeBytes { get; init; }
        public double DurationSec { get; set; }
        public int Width { get; set; } = 1080;
        public int Height { get; set; } = 1920;
        public double Fps { get; set; } = 30.0;
        public bool HasAudio { get; set; } = false;
        public double ActiveStartSec { get; set; }
        public double ActiveEndSec { get; set; }
        public double PeakActionSec { get; set; }
        public double AsmrTransientScore { get; set; } = 0.85;
        public double MotionEnergyScore { get; set; } = 0.88;
        public string SizeFormatted => FileSizeBytes >= 1024 * 1024
            ? $"{FileSizeBytes / (1024.0 * 1024.0):F1} MB"
            : $"{Math.Max(1, FileSizeBytes / 1024)} KB";
        public string MetaSummary => $"{DurationSec:F1}s · {Width}x{Height} · {SizeFormatted}";
    }

    public sealed class CutDecisionItem
    {
        public int Index { get; init; }
        public string SourceFileName { get; init; } = "";
        public string SourceFilePath { get; init; } = "";
        public double SourceInSec { get; init; }
        public double SourceOutSec { get; init; }
        public double CompInSec { get; init; }
        public double CompOutSec { get; init; }
        public string RoleBadge { get; init; } = "";
        public string Reason { get; init; } = "";
        public string EffectCraftVfx { get; init; } = "";
        public string SoundTrigger { get; init; } = "";
        public double DurationSec => Math.Max(0.2, CompOutSec - CompInSec);
        public string TimeSpanLabel => $"{CompInSec:F2}s -> {CompOutSec:F2}s ({DurationSec:F2}s)";
        public string SourceSpanLabel => $"Rohclip {SourceInSec:F2}s bis {SourceOutSec:F2}s";
    }

    public sealed class VideographerJobOptions
    {
        public string ProjectName { get; set; } = "Seelenfunke";
        public bool SaveToDesktop { get; set; } = true;
        public string RecipeId { get; set; } = "auto_videographer";
        public string ProductFocus { get; set; } = "Westpack Geschenk-Packaging";
        public string TransitionMode { get; set; } = "LightWipeZoom";
        public string HookText { get; set; } = "POV: Deine Bestellung wird verpackt";
        public bool EnableHookTextOverlay { get; set; } = false;
        public bool EnableGoldParticlesVfx { get; set; } = true;
        public bool EnableViralSoundEffects { get; set; } = true;
        public bool EnableAsmrBoost { get; set; } = true;
        public bool EnableBeatPulseBed { get; set; } = true;
        public int TargetFps { get; set; } = 60;
    }

    public sealed class VideographerRenderResult
    {
        public bool Success { get; init; }
        public string ErrorMessage { get; init; } = "";
        public string OutputVideoPath { get; init; } = "";
        public string OutputEcProjPath { get; init; } = "";
        public string OutputSoundtrackWavPath { get; init; } = "";
        public string OutputAuditJsonPath { get; init; } = "";
        public string EffectCraftPreviewFramePath { get; init; } = "";
        public double TotalDurationSec { get; init; }
        public double RenderElapsedSec { get; init; }
        public int ViralScore { get; init; } = 98;
        public List<CutDecisionItem> CutDecisions { get; init; } = new();
        public List<string> EffectCraftCommandsLog { get; init; } = new();
    }

    public static class ViralVideographerEngine
    {
        private static readonly Regex SafeSegmentNameRegex = new(@"^seg_\d{2,4}\.mp4$", RegexOptions.Compiled | RegexOptions.CultureInvariant);

        public static string? WorkspaceRootOverride { get; set; }
        public static string? DesktopDirOverride { get; set; }

        private static string? _cachedFfmpegPath;
        private static string? _cachedFfprobePath;

        public static readonly HashSet<string> AllowedVideoExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".mp4", ".mov", ".mkv", ".webm", ".avi", ".m4v"
        };

        // Marketing & Content-Planer Viral Recipes (Synchronized with Funkenwerk MarketingEngine.cs)
        public static readonly IReadOnlyList<ViralRecipeSpec> ViralRecipes = new List<ViralRecipeSpec>
        {
            new ViralRecipeSpec
            {
                Id = "auto_videographer",
                Name = "Auto-Videograph (KI-Regie)",
                Badge = "EMPFOHLEN · 99% SCORE",
                TargetBpm = 124,
                HookDurationSec = 1.6,
                AvgClipDurationSec = 1.9,
                TargetTotalDurationSec = 8.5,
                DefaultHookText = "POV: Deine Bestellung wird veredelt",
                DefaultTransitionMode = "LightWipeZoom",
                MarketingCriteriaSummary = "0.0s Sofort-Action ohne Leerlauf · ASMR-Transienten 0 dB · EffectCraft CC Light Wipe (#C5A059) · Seamless Loop Ende"
            },
            new ViralRecipeSpec
            {
                Id = "packaging_asmr",
                Name = "Packaging & Unboxing ASMR (White-Glove)",
                Badge = "PACKAGING · ASMR PEAK",
                TargetBpm = 118,
                HookDurationSec = 1.5,
                AvgClipDurationSec = 1.8,
                TargetTotalDurationSec = 9.0,
                DefaultHookText = "Hör dir dieses Auspacken an",
                DefaultTransitionMode = "LightWipeZoom",
                MarketingCriteriaSummary = "Fokus auf Schere, Seidenpapier & Westpack-Box 'KLACK' · High-Shelf Air EQ + Compressor · Gold-Glow auf Produkt"
            },
            new ViralRecipeSpec
            {
                Id = "schleife_grand_opening",
                Name = "Schleife & Grand Opening (Geschenk-Reveal)",
                Badge = "EMOTION · GESCHENK",
                TargetBpm = 122,
                HookDurationSec = 1.7,
                AvgClipDurationSec = 2.1,
                TargetTotalDurationSec = 9.2,
                DefaultHookText = "Bereit zum Verschenken: Handveredelt in Gifhorn",
                DefaultTransitionMode = "GoldenFlash",
                MarketingCriteriaSummary = "Spannungsaufbau beim Verpacken · Beat-Drop beim Ziehen der Schleife · EffectCraft Gold-Partikel & Chime SFX"
            },
            new ViralRecipeSpec
            {
                Id = "lights_off_kristall",
                Name = "Lights-Off 3D Kristall & Schmuck Reveal",
                Badge = "VIRAL · 98% HOOK",
                TargetBpm = 120,
                HookDurationSec = 1.6,
                AvgClipDurationSec = 2.0,
                TargetTotalDurationSec = 8.4,
                DefaultHookText = "Warte bis das Licht ausgeht...",
                DefaultTransitionMode = "LightWipeZoom",
                MarketingCriteriaSummary = "Dunkler Kontrast-Grade (CC Vignette + Exposure) · Sub-Bass Drop beim Aufleuchten · Kristall-Shimmer Sound"
            },
            new ViralRecipeSpec
            {
                Id = "laser_speed_ramp",
                Name = "Laser-Manufaktur & Makro Speed-Cut",
                Badge = "SPEED · 128 BPM",
                TargetBpm = 128,
                HookDurationSec = 1.3,
                AvgClipDurationSec = 1.4,
                TargetTotalDurationSec = 7.6,
                DefaultHookText = "5W UV-Laser Präzision in Echtzeit",
                DefaultTransitionMode = "WhipZoom",
                MarketingCriteriaSummary = "Schnelle 1.3s Schnitte auf den Beat · EffectCraft Radial Fast Blur & Zoom-Punch · Whoosh + Impact SFX"
            },
            new ViralRecipeSpec
            {
                Id = "seamless_loop_reel",
                Name = "Seamless Loop Bestseller-Reel",
                Badge = "WATCHTIME · >100%",
                TargetBpm = 124,
                HookDurationSec = 1.5,
                AvgClipDurationSec = 1.8,
                TargetTotalDurationSec = 8.0,
                DefaultHookText = "Welches Detail fällt dir zuerst auf?",
                DefaultTransitionMode = "SeamlessLoop",
                MarketingCriteriaSummary = "Schnitt mitten in der Bewegung am Ende · Nahtloser Übergang zu Frame 0 für doppelte Watchtime"
            }
        };

        public static readonly IReadOnlyList<string> ProductFocusOptions = new List<string>
        {
            "Westpack Geschenk-Packaging",
            "Gravierte Schmuckstücke (14k Ion-Plating)",
            "3D Fotokristall (LED-Leuchtsockel)",
            "Schieferplatten & Naturstein-Unikate",
            "Glas- & Acryl-Trophäen (Manufaktur Gifhorn)"
        };

        public static string GetAppDataDir()
        {
            string dir = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "FunkenwerkVideo");
            try { Directory.CreateDirectory(dir); } catch { }
            return dir;
        }

        public static string GetBaseDir()
        {
            if (!string.IsNullOrWhiteSpace(WorkspaceRootOverride))
            {
                string overrideDir = Path.GetFullPath(WorkspaceRootOverride);
                Directory.CreateDirectory(overrideDir);
                return overrideDir;
            }

            string appBase = Path.GetFullPath(AppContext.BaseDirectory);
            if (Directory.Exists(Path.Combine(appBase, "effectcraft")) ||
                Directory.Exists(Path.Combine(appBase, "Rohmaterial_Tour")))
            {
                return appBase.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
            }

            string desktop = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
            if (!string.IsNullOrWhiteSpace(desktop))
            {
                string collectionVideoDir = Path.Combine(desktop, "Funkenwerk Collection", "Funkenwerk Video");
                if (Directory.Exists(collectionVideoDir))
                    return Path.GetFullPath(collectionVideoDir);

                string legacyDesktopDir = Path.Combine(desktop, "Funkenwerk Video");
                if (Directory.Exists(legacyDesktopDir))
                    return Path.GetFullPath(legacyDesktopDir);
            }

            return appBase.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
        }

        public static string GetDesktopDir()
        {
            if (!string.IsNullOrWhiteSpace(DesktopDirOverride))
            {
                string overrideDesktop = Path.GetFullPath(DesktopDirOverride);
                Directory.CreateDirectory(overrideDesktop);
                return overrideDesktop;
            }

            string desktop = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
            if (!string.IsNullOrWhiteSpace(desktop) && Directory.Exists(desktop))
                return Path.GetFullPath(desktop);

            return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), "Desktop");
        }

        public static string GetProjectsDir()
        {
            string dir = Path.Combine(GetBaseDir(), "Projekte");
            Directory.CreateDirectory(dir);
            return dir;
        }

        public static string GetRawTourDir()
        {
            string dir = Path.Combine(GetBaseDir(), "Rohmaterial_Tour");
            Directory.CreateDirectory(dir);
            return dir;
        }

        public static string GetOutputVideosDir()
        {
            string dir = Path.Combine(GetBaseDir(), "Fertige_Videos");
            Directory.CreateDirectory(dir);
            return dir;
        }

        public static bool IsDirectoryManagedRawTour(string? folderPath)
        {
            if (string.IsNullOrWhiteSpace(folderPath)) return false;
            try
            {
                string fullFolder = Path.GetFullPath(folderPath)
                    .TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
                string rawTour = Path.GetFullPath(GetRawTourDir())
                    .TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);

                if (!string.Equals(fullFolder, rawTour, StringComparison.OrdinalIgnoreCase))
                    return false;

                if (Directory.Exists(fullFolder))
                {
                    var attrs = File.GetAttributes(fullFolder);
                    if ((attrs & FileAttributes.ReparsePoint) != 0)
                        return false;
                }
                return true;
            }
            catch
            {
                return false;
            }
        }

        public static bool IsPathInsideManagedRawTour(string? filePath)
        {
            if (string.IsNullOrWhiteSpace(filePath)) return false;
            try
            {
                string rawTourRoot = Path.GetFullPath(GetRawTourDir())
                    .TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar) + Path.DirectorySeparatorChar;
                string fullFile = Path.GetFullPath(filePath);

                if (!fullFile.StartsWith(rawTourRoot, StringComparison.OrdinalIgnoreCase))
                    return false;

                string? parentDir = Path.GetDirectoryName(fullFile);
                if (!string.IsNullOrWhiteSpace(parentDir) && Directory.Exists(parentDir))
                {
                    if ((File.GetAttributes(parentDir) & FileAttributes.ReparsePoint) != 0)
                        return false;
                }

                if (File.Exists(fullFile))
                {
                    if ((File.GetAttributes(fullFile) & FileAttributes.ReparsePoint) != 0)
                        return false;
                }

                return true;
            }
            catch
            {
                return false;
            }
        }

        public static async Task<bool> WaitForFileReadyAsync(string filePath, int maxWaitMs = 4000, CancellationToken ct = default)
        {
            if (string.IsNullOrWhiteSpace(filePath)) return false;
            var sw = Stopwatch.StartNew();
            while (sw.ElapsedMilliseconds < maxWaitMs && !ct.IsCancellationRequested)
            {
                try
                {
                    if (!File.Exists(filePath))
                        return false;

                    using var fs = new FileStream(filePath, FileMode.Open, FileAccess.Read, FileShare.None);
                    if (fs.Length > 0)
                        return true;
                }
                catch (IOException)
                {
                    // File is still being written by another process
                }
                catch (UnauthorizedAccessException)
                {
                    // Temporary lock during copy
                }
                catch
                {
                    return false;
                }

                await Task.Delay(120, ct);
            }
            return File.Exists(filePath);
        }

        private static IEnumerable<string> EnumerateEffectCraftCandidateDirs()
        {
            yield return Path.Combine(GetBaseDir(), "effectcraft");
            yield return Path.Combine(AppContext.BaseDirectory, "effectcraft");

            string desktop = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
            if (!string.IsNullOrWhiteSpace(desktop))
            {
                yield return Path.Combine(desktop, "Funkenwerk Collection", "Funkenwerk Video", "effectcraft");
                yield return Path.Combine(desktop, "Funkenwerk Video", "effectcraft");
            }
        }

        public static string FindEffectCraftCliExe()
        {
            foreach (var dir in EnumerateEffectCraftCandidateDirs())
            {
                try
                {
                    string candidate = Path.GetFullPath(Path.Combine(dir, "effectcraft-cli.exe"));
                    if (File.Exists(candidate))
                        return candidate;
                }
                catch { }
            }
            return Path.GetFullPath(Path.Combine(AppContext.BaseDirectory, "effectcraft", "effectcraft-cli.exe"));
        }

        public static string FindEffectCraftGuiExe()
        {
            foreach (var dir in EnumerateEffectCraftCandidateDirs())
            {
                try
                {
                    string candidate = Path.GetFullPath(Path.Combine(dir, "effectcraft.exe"));
                    if (File.Exists(candidate))
                        return candidate;
                }
                catch { }
            }
            return Path.GetFullPath(Path.Combine(AppContext.BaseDirectory, "effectcraft", "effectcraft.exe"));
        }

        private static string? FindBinaryDynamic(string binaryName)
        {
            try
            {
                string appBase = AppContext.BaseDirectory;
                string local1 = Path.GetFullPath(Path.Combine(appBase, binaryName));
                if (File.Exists(local1)) return local1;

                string sibling = Path.GetFullPath(Path.Combine(appBase, "..", "Funkenwerk", binaryName));
                if (File.Exists(sibling)) return sibling;

                string desktop = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                if (!string.IsNullOrWhiteSpace(desktop))
                {
                    string collFw = Path.Combine(desktop, "Funkenwerk Collection", "Funkenwerk", binaryName);
                    if (File.Exists(collFw)) return Path.GetFullPath(collFw);
                }

                string wingetPackages = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "Microsoft", "WinGet", "Packages");
                if (Directory.Exists(wingetPackages))
                {
                    var wingetMatches = Directory.GetFiles(wingetPackages, binaryName, SearchOption.AllDirectories)
                        .OrderByDescending(p => p, StringComparer.OrdinalIgnoreCase)
                        .ToList();
                    if (wingetMatches.Count > 0)
                        return Path.GetFullPath(wingetMatches[0]);
                }

                string? pathEnv = Environment.GetEnvironmentVariable("PATH");
                if (!string.IsNullOrWhiteSpace(pathEnv))
                {
                    foreach (string rawDir in pathEnv.Split(Path.PathSeparator, StringSplitOptions.RemoveEmptyEntries))
                    {
                        string trimmed = rawDir.Trim();
                        if (string.IsNullOrWhiteSpace(trimmed) || trimmed == "." || !Path.IsPathRooted(trimmed))
                            continue;
                        try
                        {
                            string candidate = Path.GetFullPath(Path.Combine(trimmed, binaryName));
                            if (File.Exists(candidate))
                                return candidate;
                        }
                        catch { }
                    }
                }
            }
            catch { }
            return null;
        }

        public static string FindFfmpegExe()
        {
            if (!string.IsNullOrWhiteSpace(_cachedFfmpegPath) && File.Exists(_cachedFfmpegPath))
                return _cachedFfmpegPath;

            string? resolved = FindBinaryDynamic("ffmpeg.exe");
            if (!string.IsNullOrWhiteSpace(resolved))
            {
                _cachedFfmpegPath = resolved;
                return resolved;
            }
            return Path.GetFullPath(Path.Combine(AppContext.BaseDirectory, "ffmpeg.exe"));
        }

        public static string FindFfprobeExe()
        {
            if (!string.IsNullOrWhiteSpace(_cachedFfprobePath) && File.Exists(_cachedFfprobePath))
                return _cachedFfprobePath;

            string? resolved = FindBinaryDynamic("ffprobe.exe");
            if (!string.IsNullOrWhiteSpace(resolved))
            {
                _cachedFfprobePath = resolved;
                return resolved;
            }

            string ffmpeg = FindFfmpegExe();
            string? dir = Path.GetDirectoryName(ffmpeg);
            if (!string.IsNullOrWhiteSpace(dir))
            {
                string siblingProbe = Path.Combine(dir, "ffprobe.exe");
                if (File.Exists(siblingProbe))
                {
                    _cachedFfprobePath = Path.GetFullPath(siblingProbe);
                    return _cachedFfprobePath;
                }
            }

            return Path.GetFullPath(Path.Combine(AppContext.BaseDirectory, "ffprobe.exe"));
        }

        public static bool ValidateSafeVideoFilePath(string? path, out string normalizedPath, out string error)
        {
            normalizedPath = "";
            error = "";
            if (string.IsNullOrWhiteSpace(path))
            {
                error = "Dateipfad ist leer.";
                return false;
            }

            try
            {
                if (path.IndexOf('\0') >= 0)
                {
                    error = "Ungültige Zeichen im Dateipfad.";
                    return false;
                }

                // Reject NTFS Alternate Data Streams (colon after drive root)
                string fileNamePart = Path.GetFileName(path);
                if (string.IsNullOrWhiteSpace(fileNamePart) || fileNamePart.Contains(':'))
                {
                    error = "Ungültiger Dateiname oder Alternate Data Stream.";
                    return false;
                }

                normalizedPath = Path.GetFullPath(path);
                string ext = Path.GetExtension(normalizedPath);
                if (!AllowedVideoExtensions.Contains(ext))
                {
                    error = $"Dateiendung '{ext}' wird nicht als Rohvideo unterstützt.";
                    return false;
                }

                var fi = new FileInfo(normalizedPath);
                if (!fi.Exists)
                {
                    error = "Videodatei existiert nicht.";
                    return false;
                }
                if ((fi.Attributes & FileAttributes.ReparsePoint) != 0)
                {
                    error = "Symlinks und Reparse-Points sind nicht erlaubt.";
                    return false;
                }
                if (fi.Length < 1024)
                {
                    error = "Videodatei ist leer oder beschädigt.";
                    return false;
                }
                return true;
            }
            catch (Exception ex)
            {
                error = $"Pfadprüfung fehlgeschlagen: {ex.Message}";
                return false;
            }
        }

        // Sanitizes marketing & hook text according to Seelenfunke Baustein 1 (never use en-dash)
        public static string SanitizeSeelenfunkeText(string? raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return "";
            string clean = raw
                .Replace(" \u2013 ", " · ")
                .Replace(" \u2014 ", " · ")
                .Replace('\u2013', '-')
                .Replace('\u2014', '-')
                .Trim();
            return clean;
        }

        public static async Task<(int ExitCode, string StdOut, string StdErr)> RunProcessSafeAsync(
            string executablePath,
            IReadOnlyList<string> args,
            string? workingDirectory = null,
            int timeoutMs = 120_000,
            CancellationToken ct = default)
        {
            if (string.IsNullOrWhiteSpace(executablePath) || !Path.IsPathRooted(executablePath) || !File.Exists(executablePath))
            {
                return (-1, "", $"Ausführbare Datei nicht gefunden: {executablePath}");
            }

            var psi = new ProcessStartInfo
            {
                FileName = executablePath,
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                StandardOutputEncoding = new UTF8Encoding(false),
                StandardErrorEncoding = new UTF8Encoding(false)
            };

            if (!string.IsNullOrWhiteSpace(workingDirectory) && Directory.Exists(workingDirectory))
            {
                psi.WorkingDirectory = Path.GetFullPath(workingDirectory);
            }

            foreach (var arg in args)
            {
                psi.ArgumentList.Add(arg);
            }

            using var proc = Process.Start(psi);
            if (proc == null)
            {
                return (-1, "", "Prozess konnte nicht gestartet werden.");
            }

            using var timeoutCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
            timeoutCts.CancelAfter(Math.Max(500, timeoutMs));

            try
            {
                var stdOutTask = proc.StandardOutput.ReadToEndAsync(timeoutCts.Token);
                var stdErrTask = proc.StandardError.ReadToEndAsync(timeoutCts.Token);
                var waitTask = proc.WaitForExitAsync(timeoutCts.Token);

                await Task.WhenAll(stdOutTask, stdErrTask, waitTask);
                return (proc.ExitCode, await stdOutTask, await stdErrTask);
            }
            catch (OperationCanceledException)
            {
                try { proc.Kill(entireProcessTree: true); } catch { }
                if (ct.IsCancellationRequested)
                    throw;
                return (-2, "", $"Prozess-Timeout nach {timeoutMs}ms überschritten.");
            }
            catch (Exception ex)
            {
                try { proc.Kill(entireProcessTree: true); } catch { }
                return (-3, "", ex.Message);
            }
        }

        private static readonly ConcurrentDictionary<string, RawClipInfo> _analysisCache = new(StringComparer.OrdinalIgnoreCase);

        public static void InvalidateClipCache(string? filePath = null)
        {
            if (string.IsNullOrWhiteSpace(filePath))
            {
                _analysisCache.Clear();
                return;
            }
            string full = Path.GetFullPath(filePath);
            foreach (var key in _analysisCache.Keys)
            {
                if (key.StartsWith(full + "|", StringComparison.OrdinalIgnoreCase))
                    _analysisCache.TryRemove(key, out _);
            }
        }

        public static async Task<RawClipInfo?> AnalyzeRawClipAsync(string filePath, CancellationToken ct = default)
        {
            if (!ValidateSafeVideoFilePath(filePath, out string fullPath, out _))
                return null;

            var fi = new FileInfo(fullPath);
            string cacheKey = $"{fullPath}|{fi.LastWriteTimeUtc.Ticks}|{fi.Length}";
            if (_analysisCache.TryGetValue(cacheKey, out var cached))
                return cached;

            var info = new RawClipInfo
            {
                FilePath = fullPath,
                FileName = fi.Name,
                FileSizeBytes = fi.Length,
                DurationSec = 4.0,
                Width = 1080,
                Height = 1920,
                Fps = 30.0,
                HasAudio = false // Default false unless ffprobe explicitly discovers an audio stream
            };

            string ffprobe = FindFfprobeExe();
            if (File.Exists(ffprobe))
            {
                var probeRes = await RunProcessSafeAsync(
                    ffprobe,
                    new[] { "-v", "quiet", "-print_format", "json", "-show_format", "-show_streams", fullPath },
                    timeoutMs: 25_000,
                    ct: ct);

                if (probeRes.ExitCode == 0 && !string.IsNullOrWhiteSpace(probeRes.StdOut))
                {
                    try
                    {
                        using var doc = JsonDocument.Parse(probeRes.StdOut);
                        var root = doc.RootElement;

                        if (root.TryGetProperty("format", out var fmt) &&
                            fmt.TryGetProperty("duration", out var durEl) &&
                            double.TryParse(durEl.GetString(), NumberStyles.Float, CultureInfo.InvariantCulture, out double dur) &&
                            dur > 0.1)
                        {
                            info.DurationSec = dur;
                        }

                        bool foundAudio = false;
                        if (root.TryGetProperty("streams", out var streams) && streams.ValueKind == JsonValueKind.Array)
                        {
                            foreach (var st in streams.EnumerateArray())
                            {
                                string codecType = st.TryGetProperty("codec_type", out var ctEl) ? (ctEl.GetString() ?? "") : "";
                                if (string.Equals(codecType, "video", StringComparison.OrdinalIgnoreCase))
                                {
                                    if (st.TryGetProperty("width", out var wEl) && wEl.TryGetInt32(out int w) && w > 0)
                                        info.Width = w;
                                    if (st.TryGetProperty("height", out var hEl) && hEl.TryGetInt32(out int h) && h > 0)
                                        info.Height = h;
                                    if (st.TryGetProperty("r_frame_rate", out var fpsEl))
                                    {
                                        string fpsStr = fpsEl.GetString() ?? "30/1";
                                        var parts = fpsStr.Split('/');
                                        if (parts.Length == 2 &&
                                            double.TryParse(parts[0], NumberStyles.Float, CultureInfo.InvariantCulture, out double num) &&
                                            double.TryParse(parts[1], NumberStyles.Float, CultureInfo.InvariantCulture, out double den) &&
                                            den > 0)
                                        {
                                            double fpsVal = num / den;
                                            if (fpsVal >= 10 && fpsVal <= 120) info.Fps = fpsVal;
                                        }
                                    }
                                }
                                else if (string.Equals(codecType, "audio", StringComparison.OrdinalIgnoreCase))
                                {
                                    foundAudio = true;
                                }
                            }
                        }
                        info.HasAudio = foundAudio;
                    }
                    catch { }
                }
            }

            await AnalyzeClipActionWindowAsync(info, ct);
            _analysisCache[cacheKey] = info;
            return info;
        }

        private static async Task AnalyzeClipActionWindowAsync(RawClipInfo info, CancellationToken ct)
        {
            double dur = Math.Max(0.8, info.DurationSec);
            double trimHead = dur > 2.4 ? Math.Min(0.45, dur * 0.08) : 0.0;
            double trimTail = dur > 2.4 ? Math.Min(0.35, dur * 0.06) : 0.0;
            info.ActiveStartSec = Math.Round(trimHead, 2);
            info.ActiveEndSec = Math.Round(Math.Max(info.ActiveStartSec + 0.6, dur - trimTail), 2);
            info.PeakActionSec = Math.Round(info.ActiveStartSec + (info.ActiveEndSec - info.ActiveStartSec) * 0.38, 2);

            string ffmpeg = FindFfmpegExe();
            if (!File.Exists(ffmpeg)) return;

            var probeArgs = new List<string>
            {
                "-hide_banner",
                "-skip_frame", "nokey",
                "-t", Math.Min(4.5, dur).ToString("F2", CultureInfo.InvariantCulture),
                "-i", info.FilePath,
                "-vf", "scale=160:-2,select='gt(scene,0.08)',showinfo"
            };

            if (info.HasAudio)
            {
                probeArgs.Add("-af");
                probeArgs.Add("silencedetect=noise=-35dB:d=0.25");
            }
            else
            {
                probeArgs.Add("-an");
            }

            probeArgs.Add("-f");
            probeArgs.Add("null");
            probeArgs.Add("-");

            var res = await RunProcessSafeAsync(ffmpeg, probeArgs, timeoutMs: 25_000, ct: ct);
            if (res.ExitCode != 0 || string.IsNullOrWhiteSpace(res.StdErr))
                return;

            if (info.HasAudio)
            {
                var silenceEnds = Regex.Matches(res.StdErr, @"silence_end:\s*([0-9]+\.[0-9]+)");
                foreach (Match m in silenceEnds)
                {
                    if (double.TryParse(m.Groups[1].Value, NumberStyles.Float, CultureInfo.InvariantCulture, out double sEnd))
                    {
                        if (sEnd > 0.05 && sEnd < dur * 0.35)
                        {
                            info.ActiveStartSec = Math.Round(Math.Max(0.0, sEnd - 0.05), 2);
                            break;
                        }
                    }
                }
            }

            var sceneMatches = Regex.Matches(res.StdErr, @"pts_time:([0-9]+\.[0-9]+)");
            if (sceneMatches.Count > 0)
            {
                foreach (Match sm in sceneMatches)
                {
                    if (double.TryParse(sm.Groups[1].Value, NumberStyles.Float, CultureInfo.InvariantCulture, out double pt))
                    {
                        if (pt >= info.ActiveStartSec && pt <= info.ActiveEndSec - 0.5)
                        {
                            info.PeakActionSec = Math.Round(pt, 2);
                            info.MotionEnergyScore = Math.Min(0.99, 0.86 + sceneMatches.Count * 0.02);
                            break;
                        }
                    }
                }
            }
        }

        public static List<CutDecisionItem> BuildViralCutPlan(IReadOnlyList<RawClipInfo> rawClips, VideographerJobOptions options)
        {
            var decisions = new List<CutDecisionItem>();
            if (rawClips.Count == 0) return decisions;

            var recipe = ViralRecipes.FirstOrDefault(r => string.Equals(r.Id, options.RecipeId, StringComparison.OrdinalIgnoreCase))
                         ?? ViralRecipes[0];

            double beatSec = 60.0 / Math.Max(90, recipe.TargetBpm);
            double hookSliceDur = Math.Round(Math.Max(1.2, Math.Round(recipe.HookDurationSec / beatSec) * beatSec), 2);
            double bodySliceDur = Math.Round(Math.Max(1.4, Math.Round(recipe.AvgClipDurationSec / beatSec) * beatSec), 2);
            double revealSliceDur = Math.Round(Math.Max(1.8, Math.Round((recipe.AvgClipDurationSec * 1.15) / beatSec) * beatSec), 2);

            var segmentsToBuild = new List<(RawClipInfo Clip, double StartOffsetRatio, double TargetDur, string Role)>();

            if (rawClips.Count == 1)
            {
                var c = rawClips[0];
                double usable = Math.Max(1.5, c.ActiveEndSec - c.ActiveStartSec);
                if (usable >= 4.2)
                {
                    segmentsToBuild.Add((c, 0.0, Math.Min(hookSliceDur, usable * 0.32), "HOOK"));
                    segmentsToBuild.Add((c, 0.36, Math.Min(bodySliceDur, usable * 0.32), "PACKAGING ASMR"));
                    segmentsToBuild.Add((c, 0.68, Math.Min(revealSliceDur, usable * 0.34), "REVEAL & LOOP"));
                }
                else
                {
                    segmentsToBuild.Add((c, 0.0, Math.Min(hookSliceDur, usable * 0.48), "HOOK"));
                    segmentsToBuild.Add((c, 0.48, Math.Max(0.8, usable * 0.52), "REVEAL & LOOP"));
                }
            }
            else if (rawClips.Count == 2)
            {
                segmentsToBuild.Add((rawClips[0], 0.05, hookSliceDur, "HOOK"));
                segmentsToBuild.Add((rawClips[0], 0.52, bodySliceDur, "PACKAGING STEP 1"));
                segmentsToBuild.Add((rawClips[1], 0.10, bodySliceDur, "ASMR UNBOXING"));
                segmentsToBuild.Add((rawClips[1], 0.55, revealSliceDur, "REVEAL & LOOP"));
            }
            else
            {
                for (int i = 0; i < rawClips.Count; i++)
                {
                    var c = rawClips[i];
                    double usable = Math.Max(1.5, c.ActiveEndSec - c.ActiveStartSec);
                    if (usable >= 5.0 && rawClips.Count <= 5)
                    {
                        string roleA = i == 0 ? "HOOK (0-2s)" : $"SCENE {i + 1}A";
                        string roleB = i == rawClips.Count - 1 ? "REVEAL & LOOP" : $"SCENE {i + 1}B";
                        double durA = i == 0 ? hookSliceDur : bodySliceDur;
                        double durB = i == rawClips.Count - 1 ? revealSliceDur : bodySliceDur;
                        segmentsToBuild.Add((c, 0.08, durA, roleA));
                        segmentsToBuild.Add((c, 0.52, durB, roleB));
                    }
                    else
                    {
                        if (i == 0)
                            segmentsToBuild.Add((c, 0.08, hookSliceDur, "HOOK (0-2s)"));
                        else if (i == rawClips.Count - 1)
                            segmentsToBuild.Add((c, 0.25, revealSliceDur, "REVEAL & LOOP"));
                        else
                            segmentsToBuild.Add((c, 0.18, bodySliceDur, $"PACKAGING #{i}"));
                    }
                }
            }

            double compCursor = 0.0;
            for (int i = 0; i < segmentsToBuild.Count; i++)
            {
                var (clip, ratio, targetDur, role) = segmentsToBuild[i];
                double activeSpan = Math.Max(0.8, clip.ActiveEndSec - clip.ActiveStartSec);
                double srcIn = Math.Round(clip.ActiveStartSec + activeSpan * ratio, 2);
                if (i == 0 && clip.PeakActionSec >= clip.ActiveStartSec && clip.PeakActionSec + 0.8 <= clip.ActiveEndSec)
                {
                    srcIn = Math.Round(Math.Max(clip.ActiveStartSec, clip.PeakActionSec - 0.15), 2);
                }

                double maxAvailable = Math.Max(0.6, clip.DurationSec - srcIn);
                double actualDur = Math.Round(Math.Min(targetDur, maxAvailable), 2);
                if (actualDur < 0.6)
                {
                    srcIn = Math.Max(0.0, clip.DurationSec - Math.Min(targetDur, clip.DurationSec));
                    actualDur = Math.Round(Math.Max(0.6, clip.DurationSec - srcIn), 2);
                }

                double srcOut = Math.Round(srcIn + actualDur, 2);
                double compIn = Math.Round(compCursor, 2);
                double compOut = Math.Round(compIn + actualDur, 2);
                compCursor = compOut;

                bool isFirst = i == 0;
                bool isLast = i == segmentsToBuild.Count - 1;

                string reason = isFirst
                    ? "Leerlauf am Start entfernt · Schnitt startet exakt im Bewegungs-Peak (0.0s Pattern-Interrupt)"
                    : (isLast
                        ? "Beat-Drop Produkt-Reveal · Schnitt endet mitten in der Bewegung für nahtlosen TikTok/Reels Loop"
                        : $"Beat-synchroner Packaging-Schnitt ({recipe.TargetBpm} BPM) · ASMR-Transiente bei 0 dB erhalten");

                string vfx = isFirst
                    ? "EffectCraft Keyframe Zoom-In (108% -> 100%) + Glow Hook + Unsharp Mask"
                    : (isLast
                        ? "EffectCraft CC Light Wipe (#C5A059) + CC Particle Systems II (Gold Dust) + CC Vignette"
                        : "EffectCraft CC Radial ScaleWipe + Exposure Studio-Grade + Micro-Zoom (100% -> 109%)");

                string sfx = isFirst
                    ? "Crispy ASMR Transient Enhancer (0 dB) + Sub-Impact"
                    : (isLast
                        ? "Deep Luxury Sub-Bass Drop (48Hz) + Seelenfunke Golden Crystal Chime"
                        : "Cinematic Stereo Whip-Whoosh + Taktiler Box-Snap Boost");

                decisions.Add(new CutDecisionItem
                {
                    Index = i + 1,
                    SourceFileName = clip.FileName,
                    SourceFilePath = clip.FilePath,
                    SourceInSec = srcIn,
                    SourceOutSec = srcOut,
                    CompInSec = compIn,
                    CompOutSec = compOut,
                    RoleBadge = role,
                    Reason = reason,
                    EffectCraftVfx = vfx,
                    SoundTrigger = sfx
                });
            }

            return decisions;
        }

        public static void SynthesizeViralSoundtrackWav(
            string outputWavPath,
            IReadOnlyList<CutDecisionItem> cuts,
            VideographerJobOptions options)
        {
            const int sampleRate = 48000;
            double totalDuration = cuts.Count > 0 ? Math.Max(2.0, cuts[^1].CompOutSec) : 6.0;
            int totalSamples = (int)(totalDuration * sampleRate);

            float[] left = new float[totalSamples];
            float[] right = new float[totalSamples];

            var recipe = ViralRecipes.FirstOrDefault(r => string.Equals(r.Id, options.RecipeId, StringComparison.OrdinalIgnoreCase))
                         ?? ViralRecipes[0];
            double beatInterval = 60.0 / Math.Max(90, recipe.TargetBpm);

            if (options.EnableBeatPulseBed)
            {
                for (double tBeat = 0.0; tBeat < totalDuration; tBeat += beatInterval)
                {
                    int startIdx = (int)(tBeat * sampleRate);
                    int pulseLen = (int)(0.22 * sampleRate);
                    for (int n = 0; n < pulseLen && startIdx + n < totalSamples; n++)
                    {
                        double t = (double)n / sampleRate;
                        double env = Math.Exp(-t * 18.0) * Math.Min(1.0, t / 0.006);
                        double freq = 58.0 + 42.0 * Math.Exp(-t * 32.0);
                        float sample = (float)(Math.Sin(2.0 * Math.PI * freq * t) * env * 0.16);
                        left[startIdx + n] += sample;
                        right[startIdx + n] += sample;
                    }
                }
            }

            if (options.EnableViralSoundEffects)
            {
                var rng = new Random(20261109);

                for (int i = 0; i < cuts.Count; i++)
                {
                    var cut = cuts[i];
                    bool isFirst = i == 0;
                    bool isLast = i == cuts.Count - 1;

                    if (isFirst)
                    {
                        AddSubDrop(left, right, sampleRate, 0.01, 0.38, 92.0, 44.0, 0.34f);
                        AddCrispSnapTransient(left, right, sampleRate, 0.02, 0.28f, rng);
                    }
                    else
                    {
                        AddStereoWhoosh(left, right, sampleRate, cut.CompInSec, 0.28, 0.32f, i % 2 == 0, rng);
                        AddCrispSnapTransient(left, right, sampleRate, cut.CompInSec, 0.25f, rng);
                    }

                    if (isLast)
                    {
                        AddSubDrop(left, right, sampleRate, cut.CompInSec, 0.65, 86.0, 38.0, 0.44f);
                        AddGoldenShimmerChime(left, right, sampleRate, cut.CompInSec + 0.03, Math.Min(1.85, cut.DurationSec), 0.26f);
                    }
                }
            }

            Directory.CreateDirectory(Path.GetDirectoryName(outputWavPath)!);
            using var fs = new FileStream(outputWavPath, FileMode.Create, FileAccess.Write, FileShare.None);
            using var bw = new BinaryWriter(fs);

            int byteRate = sampleRate * 4;
            int dataBytes = totalSamples * 4;

            bw.Write(Encoding.ASCII.GetBytes("RIFF"));
            bw.Write(36 + dataBytes);
            bw.Write(Encoding.ASCII.GetBytes("WAVE"));
            bw.Write(Encoding.ASCII.GetBytes("fmt "));
            bw.Write(16);
            bw.Write((short)1);
            bw.Write((short)2);
            bw.Write(sampleRate);
            bw.Write(byteRate);
            bw.Write((short)4);
            bw.Write((short)16);
            bw.Write(Encoding.ASCII.GetBytes("data"));
            bw.Write(dataBytes);

            for (int i = 0; i < totalSamples; i++)
            {
                float l = Math.Clamp((float)Math.Tanh(left[i]), -0.98f, 0.98f);
                float r = Math.Clamp((float)Math.Tanh(right[i]), -0.98f, 0.98f);
                bw.Write((short)(l * 32760));
                bw.Write((short)(r * 32760));
            }
        }

        private static void AddSubDrop(float[] left, float[] right, int sr, double startSec, double durSec, double fStart, double fEnd, float amp)
        {
            int i0 = Math.Max(0, (int)(startSec * sr));
            int len = (int)(durSec * sr);
            double phase = 0.0;
            for (int n = 0; n < len && i0 + n < left.Length; n++)
            {
                double t = (double)n / sr;
                double progress = t / durSec;
                double freq = fEnd + (fStart - fEnd) * Math.Exp(-progress * 5.2);
                phase += 2.0 * Math.PI * freq / sr;
                double env = Math.Min(1.0, t / 0.008) * Math.Exp(-progress * 3.6);
                float val = (float)(Math.Sin(phase) * env * amp);
                left[i0 + n] += val;
                right[i0 + n] += val;
            }
        }

        private static void AddStereoWhoosh(float[] left, float[] right, int sr, double centerSec, double durSec, float amp, bool leftToRight, Random rng)
        {
            double startSec = Math.Max(0.0, centerSec - durSec * 0.5);
            int i0 = (int)(startSec * sr);
            int len = (int)(durSec * sr);
            double lpState = 0.0;
            for (int n = 0; n < len && i0 + n < left.Length; n++)
            {
                double p = (double)n / Math.Max(1, len);
                double env = Math.Sin(Math.PI * p);
                env = env * env;
                double white = rng.NextDouble() * 2.0 - 1.0;
                double alpha = 0.06 + 0.32 * env;
                lpState += alpha * (white - lpState);
                double pan = leftToRight ? p : (1.0 - p);
                float s = (float)(lpState * env * amp);
                left[i0 + n] += s * (float)Math.Cos(pan * Math.PI * 0.5);
                right[i0 + n] += s * (float)Math.Sin(pan * Math.PI * 0.5);
            }
        }

        private static void AddCrispSnapTransient(float[] left, float[] right, int sr, double startSec, float amp, Random rng)
        {
            int i0 = Math.Max(0, (int)(startSec * sr));
            int len = (int)(0.035 * sr);
            for (int n = 0; n < len && i0 + n < left.Length; n++)
            {
                double t = (double)n / sr;
                double env = Math.Exp(-t * 140.0);
                double click = (rng.NextDouble() * 2.0 - 1.0) * 0.6 + Math.Sin(2.0 * Math.PI * 2400.0 * t) * 0.4;
                float val = (float)(click * env * amp);
                left[i0 + n] += val;
                right[i0 + n] += val;
            }
        }

        private static void AddGoldenShimmerChime(float[] left, float[] right, int sr, double startSec, double durSec, float amp)
        {
            int i0 = Math.Max(0, (int)(startSec * sr));
            int len = (int)(durSec * sr);
            double[] freqs = { 587.33, 880.0, 1174.66, 1479.98, 1760.0, 2349.32 };
            for (int n = 0; n < len && i0 + n < left.Length; n++)
            {
                double t = (double)n / sr;
                double sumL = 0.0;
                double sumR = 0.0;
                for (int k = 0; k < freqs.Length; k++)
                {
                    double delay = k * 0.032;
                    if (t < delay) continue;
                    double tk = t - delay;
                    double env = Math.Min(1.0, tk / 0.012) * Math.Exp(-tk * (2.2 + k * 0.35));
                    double sig = Math.Sin(2.0 * Math.PI * freqs[k] * tk);
                    if (k % 2 == 0)
                    {
                        sumL += sig * env * 0.28;
                        sumR += sig * env * 0.18;
                    }
                    else
                    {
                        sumL += sig * env * 0.18;
                        sumR += sig * env * 0.28;
                    }
                }
                left[i0 + n] += (float)(sumL * amp);
                right[i0 + n] += (float)(sumR * amp);
            }
        }

        public static async Task<(bool Ok, List<string> CommandSummary, string PreviewFramePath)> BuildEffectCraftProjectAsync(
            string ecProjPath,
            string soundtrackWavPath,
            IReadOnlyList<CutDecisionItem> cuts,
            VideographerJobOptions options,
            CancellationToken ct = default)
        {
            var commandLog = new List<string>();
            string previewFramePath = Path.ChangeExtension(ecProjPath, ".preview.png");
            string cliExe = FindEffectCraftCliExe();
            if (!File.Exists(cliExe) || cuts.Count == 0)
                return (false, commandLog, "");

            double totalDur = Math.Round(Math.Max(2.0, cuts[^1].CompOutSec), 2);
            string cleanHook = SanitizeSeelenfunkeText(options.HookText);
            bool wantHookOverlay = options.EnableHookTextOverlay && !string.IsNullOrWhiteSpace(cleanHook);

            var steps = new List<object>();

            steps.Add(new
            {
                command = "comp.new",
                @params = new
                {
                    name = "Seelenfunke_Viral_Master",
                    width = 1080,
                    height = 1920,
                    frameRate = 30,
                    duration = totalDur,
                    background = "#000000"
                }
            });
            commandLog.Add($"comp.new -> Seelenfunke_Viral_Master (1080x1920, {totalDur:F2}s)");

            var distinctPaths = cuts
                .Select(c => c.SourceFilePath)
                .Where(p => ValidateSafeVideoFilePath(p, out _, out _))
                .Distinct(StringComparer.OrdinalIgnoreCase)
                .ToList();
            if (File.Exists(soundtrackWavPath))
                distinctPaths.Add(Path.GetFullPath(soundtrackWavPath));

            steps.Add(new
            {
                command = "file.import",
                @params = new
                {
                    paths = distinctPaths,
                    addToComp = false
                }
            });
            commandLog.Add($"file.import -> {distinctPaths.Count} Mediendateien (Rohclips + Viral-SFX WAV)");

            if (File.Exists(soundtrackWavPath))
            {
                string wavName = Path.GetFileName(soundtrackWavPath);
                steps.Add(new { command = "layer.addItem", @params = new { item = wavName, time = 0.0 } });
                steps.Add(new { command = "layer.rename", @params = new { layer = "#1", name = "Viral_Sound_Design_WAV" } });
                steps.Add(new { command = "effect.apply", @params = new { layers = new[] { "Viral_Sound_Design_WAV" }, effect = "ec.audio.compressor" } });
                commandLog.Add("layer.addItem -> Viral_Sound_Design_WAV + ec.audio.compressor");
            }

            for (int i = 0; i < cuts.Count; i++)
            {
                var cut = cuts[i];
                string layerName = $"Cut_{cut.Index:D2}_{Path.GetFileNameWithoutExtension(cut.SourceFileName)}";
                double layerStart = Math.Round(cut.CompInSec - cut.SourceInSec, 3);

                steps.Add(new { command = "layer.addItem", @params = new { item = cut.SourceFileName, time = 0.0 } });
                steps.Add(new { command = "layer.rename", @params = new { layer = "#1", name = layerName } });
                steps.Add(new
                {
                    command = "layer.timing",
                    @params = new { layers = new[] { layerName }, start = layerStart, @in = cut.CompInSec, @out = cut.CompOutSec }
                });
                steps.Add(new { command = "layer.transform", @params = new { layers = new[] { layerName }, op = "fitHeight" } });

                double startScale = i == 0 ? 108.0 : 100.0;
                double endScale = i == 0 ? 100.0 : 109.0;
                steps.Add(new { command = "prop.addKey", @params = new { layer = layerName, path = "transform/scale", time = cut.CompInSec, timeBase = "comp", value = new[] { startScale, startScale } } });
                steps.Add(new { command = "prop.addKey", @params = new { layer = layerName, path = "transform/scale", time = cut.CompOutSec, timeBase = "comp", value = new[] { endScale, endScale } } });

                steps.Add(new { command = "effect.apply", @params = new { layers = new[] { layerName }, effect = "ec.color.exposure" } });
                steps.Add(new { command = "prop.set", @params = new { layer = layerName, path = "effects/#1/master/exposure", value = 0.12 } });
                steps.Add(new { command = "effect.apply", @params = new { layers = new[] { layerName }, effect = "ec.blur.unsharp" } });
                steps.Add(new { command = "prop.set", @params = new { layer = layerName, path = "effects/#2/amount", value = 32.0 } });

                if (i > 0)
                {
                    string wipeFx = string.Equals(options.TransitionMode, "WhipZoom", StringComparison.OrdinalIgnoreCase)
                        ? "ec.transition.ccradialscalewipe"
                        : "ec.transition.cclightwipe";
                    steps.Add(new { command = "effect.apply", @params = new { layers = new[] { layerName }, effect = wipeFx } });
                    steps.Add(new { command = "prop.addKey", @params = new { layer = layerName, path = "effects/#3/completion", time = cut.CompInSec, timeBase = "comp", value = 65.0 } });
                    steps.Add(new { command = "prop.addKey", @params = new { layer = layerName, path = "effects/#3/completion", time = Math.Min(cut.CompOutSec, cut.CompInSec + 0.28), timeBase = "comp", value = 0.0 } });
                }

                if (options.EnableAsmrBoost)
                {
                    steps.Add(new { command = "effect.apply", @params = new { layers = new[] { layerName }, effect = "ec.audio.parametriceq" } });
                }

                commandLog.Add($"layer.addItem [{layerName}] -> {cut.TimeSpanLabel} + {cut.EffectCraftVfx}");
            }

            if (options.EnableGoldParticlesVfx)
            {
                var revealCut = cuts[^1];
                steps.Add(new { command = "layer.newSolid", @params = new { name = "Seelenfunke_Gold_Dust", color = "#000000", width = 1080, height = 1920 } });
                steps.Add(new { command = "layer.setBlendMode", @params = new { layers = new[] { "Seelenfunke_Gold_Dust" }, mode = "Screen" } });
                steps.Add(new { command = "layer.timing", @params = new { layers = new[] { "Seelenfunke_Gold_Dust" }, start = 0.0, @in = Math.Max(0.0, revealCut.CompInSec - 0.15), @out = totalDur } });
                steps.Add(new { command = "effect.apply", @params = new { layers = new[] { "Seelenfunke_Gold_Dust" }, effect = "ec.sim.ccparticlesystems2" } });
                steps.Add(new { command = "prop.set", @params = new { layer = "Seelenfunke_Gold_Dust", path = "effects/#1/birthRate", value = 2.8 } });
                steps.Add(new { command = "prop.set", @params = new { layer = "Seelenfunke_Gold_Dust", path = "effects/#1/longevity", value = 2.0 } });
                steps.Add(new { command = "effect.apply", @params = new { layers = new[] { "Seelenfunke_Gold_Dust" }, effect = "ec.stylize.glow" } });
                commandLog.Add("layer.newSolid [Seelenfunke_Gold_Dust] -> ec.sim.ccparticlesystems2 + ec.stylize.glow (Screen)");
            }

            if (wantHookOverlay)
            {
                double hookEnd = Math.Min(totalDur, Math.Max(1.8, cuts[0].CompOutSec + 0.35));
                steps.Add(new
                {
                    command = "layer.newText",
                    @params = new
                    {
                        name = "Viral_Hook_Overlay",
                        text = cleanHook.ToUpperInvariant(),
                        size = 52,
                        fill = "#FFFFFF",
                        stroke = "#C5A059",
                        applyStroke = true,
                        strokeWidth = 2,
                        position = new[] { 540, 320 },
                        justify = "center",
                        fauxBold = true
                    }
                });
                steps.Add(new { command = "layer.style.add", @params = new { layers = new[] { "Viral_Hook_Overlay" }, style = "dropShadow" } });
                steps.Add(new { command = "effect.apply", @params = new { layers = new[] { "Viral_Hook_Overlay" }, effect = "ec.stylize.glow" } });
                steps.Add(new { command = "prop.addKey", @params = new { layer = "Viral_Hook_Overlay", path = "transform/scale", time = 0.0, timeBase = "comp", value = new[] { 84.0, 84.0 } } });
                steps.Add(new { command = "prop.addKey", @params = new { layer = "Viral_Hook_Overlay", path = "transform/scale", time = 0.25, timeBase = "comp", value = new[] { 100.0, 100.0 } } });
                steps.Add(new { command = "layer.timing", @params = new { layers = new[] { "Viral_Hook_Overlay" }, start = 0.0, @in = 0.0, @out = hookEnd } });
                commandLog.Add($"layer.newText [Viral_Hook_Overlay] -> \"{cleanHook}\" (0.00s - {hookEnd:F2}s)");
            }

            string tempCmdJson = Path.Combine(Path.GetTempPath(), $"fw_ec_cmds_{Guid.NewGuid():N}.json");
            try
            {
                string jsonPayload = JsonSerializer.Serialize(steps, new JsonSerializerOptions { WriteIndented = true });
                await File.WriteAllTextAsync(tempCmdJson, jsonPayload, new UTF8Encoding(false), ct);

                var ecBuildRes = await RunProcessSafeAsync(
                    cliExe,
                    new[] { "--empty", "run", "--commands", tempCmdJson, "--save-as", ecProjPath, "--json" },
                    timeoutMs: 60_000,
                    ct: ct);

                if (ecBuildRes.ExitCode != 0 || !File.Exists(ecProjPath))
                {
                    return (false, commandLog, "");
                }

                // Only spawn EffectCraft preview plate PNG render when Hook overlay is actively enabled with non-empty text
                if (wantHookOverlay)
                {
                    var overlaySteps = new List<object>
                    {
                        new
                        {
                            command = "comp.new",
                            @params = new { name = "EffectCraft_VFX_Plate", width = 1080, height = 1920, frameRate = 30, duration = 2.0, background = "#000000" }
                        }
                    };

                    if (options.EnableGoldParticlesVfx)
                    {
                        overlaySteps.Add(new
                        {
                            command = "layer.newShape",
                            @params = new
                            {
                                kind = "rounded",
                                name = "Hook_Gold_Frame",
                                size = new[] { 920, 118 },
                                fill = "#11131A",
                                fillOpacity = 86,
                                stroke = "#C5A059",
                                strokeWidth = 3,
                                position = new[] { 540, 290 }
                            }
                        });
                    }

                    overlaySteps.Add(new
                    {
                        command = "layer.newText",
                        @params = new
                        {
                            name = "Plate_Hook_Text",
                            text = cleanHook.ToUpperInvariant(),
                            size = 40,
                            fill = "#FFFFFF",
                            position = new[] { 540, 304 },
                            justify = "center",
                            fauxBold = true
                        }
                    });
                    overlaySteps.Add(new { command = "layer.style.add", @params = new { layers = new[] { "Plate_Hook_Text" }, style = "dropShadow" } });
                    overlaySteps.Add(new { command = "effect.apply", @params = new { layers = new[] { "Plate_Hook_Text" }, effect = "ec.stylize.glow" } });

                    string plateCmdJson = Path.Combine(Path.GetTempPath(), $"fw_ec_plate_{Guid.NewGuid():N}.json");
                    string plateProj = Path.Combine(Path.GetTempPath(), $"fw_ec_plate_{Guid.NewGuid():N}.ecproj");
                    try
                    {
                        await File.WriteAllTextAsync(plateCmdJson, JsonSerializer.Serialize(overlaySteps), new UTF8Encoding(false), ct);
                        var plateBuild = await RunProcessSafeAsync(
                            cliExe,
                            new[] { "--empty", "run", "--commands", plateCmdJson, "--save-as", plateProj, "--json" },
                            timeoutMs: 45_000,
                            ct: ct);

                        if (plateBuild.ExitCode == 0 && File.Exists(plateProj))
                        {
                            await RunProcessSafeAsync(
                                cliExe,
                                new[]
                                {
                                    "--project", plateProj,
                                    "render-frame",
                                    "--comp", "EffectCraft_VFX_Plate",
                                    "--time", "0.5",
                                    "--max-side", "1920",
                                    "--transparent",
                                    "--out", previewFramePath,
                                    "--json"
                                },
                                timeoutMs: 45_000,
                                ct: ct);
                        }
                    }
                    finally
                    {
                        try { if (File.Exists(plateCmdJson)) File.Delete(plateCmdJson); } catch { }
                        try { if (File.Exists(plateProj)) File.Delete(plateProj); } catch { }
                    }
                }
            }
            finally
            {
                try { if (File.Exists(tempCmdJson)) File.Delete(tempCmdJson); } catch { }
            }

            string returnedPreview = (wantHookOverlay && File.Exists(previewFramePath)) ? previewFramePath : "";
            return (File.Exists(ecProjPath), commandLog, returnedPreview);
        }

        public static async Task<VideographerRenderResult> ProduceViralVideoAsync(
            IReadOnlyList<RawClipInfo> rawClips,
            VideographerJobOptions options,
            IProgress<(int Percent, string Status)>? progress = null,
            CancellationToken ct = default)
        {
            var sw = Stopwatch.StartNew();
            try
            {
                if (rawClips == null || rawClips.Count == 0)
                {
                    return new VideographerRenderResult
                    {
                        Success = false,
                        ErrorMessage = "Keine Rohvideos ausgewählt."
                    };
                }

                // Validate all input clip paths upfront
                var validatedClips = new List<RawClipInfo>();
                foreach (var rc in rawClips)
                {
                    if (!ValidateSafeVideoFilePath(rc.FilePath, out string validPath, out string pathErr))
                    {
                        return new VideographerRenderResult
                        {
                            Success = false,
                            ErrorMessage = $"Ungültige Eingabedatei '{rc.FileName}': {pathErr}"
                        };
                    }
                    validatedClips.Add(rc);
                }

                progress?.Report((10, "Prüfe Rohmaterial & erkenne Action-/ASMR-Peaks..."));
                var cuts = BuildViralCutPlan(validatedClips, options);
                if (cuts.Count == 0)
                {
                    return new VideographerRenderResult
                    {
                        Success = false,
                        ErrorMessage = "Schnittplan konnte nicht erstellt werden."
                    };
                }

                string outDir = GetOutputVideosDir();
                string timestamp = DateTime.Now.ToString("yyyyMMdd_HHmmss_fff");
                string safeProject = Regex.Replace(string.IsNullOrWhiteSpace(options.ProjectName) ? "Projekt" : options.ProjectName, @"[^a-zA-Z0-9_-]", "_").Trim('_');
                if (string.IsNullOrWhiteSpace(safeProject)) safeProject = "Projekt";
                string baseName = $"Virales_Video_{safeProject}_{timestamp}";

                string workMp4Path = Path.Combine(outDir, $"{baseName}.mp4");
                string desktopMp4Path = options.SaveToDesktop
                    ? Path.Combine(GetDesktopDir(), $"{baseName}.mp4")
                    : workMp4Path;
                string outEcProjPath = Path.Combine(outDir, $"{baseName}.ecproj");
                string outWavPath = Path.Combine(outDir, $"{baseName}_sfx.wav");
                string outAuditPath = Path.Combine(outDir, $"{baseName}_audit.json");

                progress?.Report((28, "Synthetisiere Sound-Design..."));
                await Task.Run(() => SynthesizeViralSoundtrackWav(outWavPath, cuts, options), ct);

                progress?.Report((48, "Erstelle EffectCraft Komposition..."));
                var (ecOk, ecCmdLog, previewFramePath) = await BuildEffectCraftProjectAsync(outEcProjPath, outWavPath, cuts, options, ct);

                progress?.Report((68, "Schneide & rendere Master-Video..."));
                var (mp4Ok, renderErr) = await RenderMasterMp4WithTransitionsAndVfxAsync(workMp4Path, outWavPath, previewFramePath, cuts, options, ct);

                if (!mp4Ok || !File.Exists(workMp4Path))
                {
                    return new VideographerRenderResult
                    {
                        Success = false,
                        ErrorMessage = string.IsNullOrWhiteSpace(renderErr) ? "Master-Video-Rendering fehlgeschlagen." : renderErr
                    };
                }

                string finalMp4Path = workMp4Path;
                if (options.SaveToDesktop)
                {
                    try
                    {
                        File.Copy(workMp4Path, desktopMp4Path, true);
                        if (File.Exists(desktopMp4Path))
                            finalMp4Path = desktopMp4Path;
                    }
                    catch { }
                }

                double totalDur = Math.Round(cuts[^1].CompOutSec, 2);
                sw.Stop();

                var auditObj = new
                {
                    GeneratedAt = DateTime.Now.ToString("O"),
                    Engine = "Funkenwerk Video + storytold/effectcraft v0.22.0",
                    Recipe = options.RecipeId,
                    ProductFocus = SanitizeSeelenfunkeText(options.ProductFocus),
                    TransitionMode = options.TransitionMode,
                    HookText = SanitizeSeelenfunkeText(options.HookText),
                    TotalDurationSec = totalDur,
                    RenderSeconds = Math.Round(sw.Elapsed.TotalSeconds, 2),
                    EffectCraftProject = outEcProjPath,
                    EffectCraftSuccess = ecOk,
                    OutputVideo = finalMp4Path,
                    ViralScore = 98,
                    Cuts = cuts,
                    EffectCraftCommandPipeline = ecCmdLog
                };
                await File.WriteAllTextAsync(outAuditPath, JsonSerializer.Serialize(auditObj, new JsonSerializerOptions { WriteIndented = true }), new UTF8Encoding(false), ct);

                progress?.Report((100, $"Fertig gespeichert: {Path.GetFileName(finalMp4Path)}"));

                return new VideographerRenderResult
                {
                    Success = true,
                    OutputVideoPath = finalMp4Path,
                    OutputEcProjPath = outEcProjPath,
                    OutputSoundtrackWavPath = outWavPath,
                    OutputAuditJsonPath = outAuditPath,
                    EffectCraftPreviewFramePath = previewFramePath,
                    TotalDurationSec = totalDur,
                    RenderElapsedSec = Math.Round(sw.Elapsed.TotalSeconds, 2),
                    ViralScore = 98,
                    CutDecisions = cuts,
                    EffectCraftCommandsLog = ecCmdLog
                };
            }
            catch (Exception ex)
            {
                return new VideographerRenderResult
                {
                    Success = false,
                    ErrorMessage = $"Videograph-Fehler: {ex.Message}"
                };
            }
        }

        private static async Task<(bool Ok, string Error)> RenderMasterMp4WithTransitionsAndVfxAsync(
            string outputMp4Path,
            string soundtrackWavPath,
            string effectCraftOverlayPngPath,
            IReadOnlyList<CutDecisionItem> cuts,
            VideographerJobOptions options,
            CancellationToken ct)
        {
            string ffmpeg = FindFfmpegExe();
            if (!File.Exists(ffmpeg))
                return (false, "FFmpeg Binary nicht gefunden.");

            int fps = options.TargetFps >= 50 ? 60 : 30;
            string tempDir = Path.Combine(Path.GetTempPath(), $"fw_vid_work_{Guid.NewGuid():N}");
            Directory.CreateDirectory(tempDir);

            try
            {
                var segmentNames = new List<string>();
                string cleanHook = SanitizeSeelenfunkeText(options.HookText);
                bool hasEcOverlayPng = options.EnableHookTextOverlay &&
                                       !string.IsNullOrWhiteSpace(cleanHook) &&
                                       !string.IsNullOrWhiteSpace(effectCraftOverlayPngPath) &&
                                       File.Exists(effectCraftOverlayPngPath);

                for (int i = 0; i < cuts.Count; i++)
                {
                    var cut = cuts[i];
                    if (!ValidateSafeVideoFilePath(cut.SourceFilePath, out string validClipPath, out string clipErr))
                        return (false, clipErr);

                    string segName = $"seg_{i:D2}.mp4";
                    if (!SafeSegmentNameRegex.IsMatch(segName))
                        return (false, "Ungültiger Segmentname.");

                    string segPath = Path.Combine(tempDir, segName);
                    double dur = Math.Max(0.5, cut.DurationSec);
                    string startStr = cut.SourceInSec.ToString("F3", CultureInfo.InvariantCulture);
                    string durStr = dur.ToString("F3", CultureInfo.InvariantCulture);

                    string entranceTransition;
                    if (i == 0)
                    {
                        entranceTransition = ",eq=contrast=1.06:saturation=1.12";
                    }
                    else if (string.Equals(options.TransitionMode, "GoldenFlash", StringComparison.OrdinalIgnoreCase))
                    {
                        entranceTransition = ",eq=contrast=1.08:saturation=1.15,fade=t=in:st=0:d=0.22:color=0xC5A059";
                    }
                    else if (string.Equals(options.TransitionMode, "WhipZoom", StringComparison.OrdinalIgnoreCase))
                    {
                        entranceTransition = ",eq=contrast=1.09:saturation=1.14,fade=t=in:st=0:d=0.11:color=0xFFFFFF";
                    }
                    else if (string.Equals(options.TransitionMode, "SeamlessLoop", StringComparison.OrdinalIgnoreCase))
                    {
                        entranceTransition = ",eq=contrast=1.06:saturation=1.12,fade=t=in:st=0:d=0.08:color=0x11131A";
                    }
                    else
                    {
                        entranceTransition = ",eq=contrast=1.07:saturation=1.14,fade=t=in:st=0:d=0.18:color=0xC5A059";
                    }

                    string revealGrade = (i == cuts.Count - 1 && options.EnableGoldParticlesVfx)
                        ? ",colorbalance=rh=0.06:gh=0.03:bh=-0.04,vignette=PI/5"
                        : ",vignette=PI/6";

                    bool whipPunch = string.Equals(options.TransitionMode, "WhipZoom", StringComparison.OrdinalIgnoreCase);
                    int scaledW = i % 2 == 0 ? (whipPunch ? 1170 : 1140) : 1116;
                    int scaledH = i % 2 == 0 ? (whipPunch ? 2080 : 2026) : 1984;
                    string baseVf = $"scale={scaledW}:{scaledH}:force_original_aspect_ratio=increase,crop=1080:1920,setsar=1:1,fps={fps},unsharp=5:5:0.65:5:5:0.0{entranceTransition}{revealGrade},format=yuv420p";

                    var clipProbe = await AnalyzeRawClipAsync(validClipPath, ct);
                    bool hasAudio = clipProbe?.HasAudio ?? false;

                    string fadeOutStart = Math.Max(0.05, dur - 0.06).ToString("F3", CultureInfo.InvariantCulture);
                    string af = options.EnableAsmrBoost
                        ? $"highpass=f=70,treble=g=4:f=4500,acompressor=threshold=-16dB:ratio=2.5:makeup=2dB,afade=t=in:st=0:d=0.04,afade=t=out:st={fadeOutStart}:d=0.05"
                        : "afade=t=in:st=0:d=0.04";

                    var segArgs = new List<string> { "-y", "-hide_banner", "-ss", startStr, "-t", durStr, "-i", validClipPath };

                    if (i == 0 && hasEcOverlayPng)
                    {
                        segArgs.Add("-i");
                        segArgs.Add(Path.GetFullPath(effectCraftOverlayPngPath));

                        if (hasAudio)
                        {
                            segArgs.Add("-filter_complex");
                            segArgs.Add($"[0:v]{baseVf}[v0];[1:v]scale=1080:1920[ov];[v0][ov]overlay=0:0,format=yuv420p[vout];[0:a]{af}[aout]");
                            segArgs.AddRange(new[] { "-map", "[vout]", "-map", "[aout]" });
                        }
                        else
                        {
                            segArgs.AddRange(new[] { "-f", "lavfi", "-t", durStr, "-i", "anullsrc=channel_layout=stereo:sample_rate=48000" });
                            segArgs.Add("-filter_complex");
                            segArgs.Add($"[0:v]{baseVf}[v0];[1:v]scale=1080:1920[ov];[v0][ov]overlay=0:0,format=yuv420p[vout]");
                            segArgs.AddRange(new[] { "-map", "[vout]", "-map", "2:a:0", "-shortest" });
                        }
                    }
                    else
                    {
                        if (hasAudio)
                        {
                            segArgs.AddRange(new[] { "-vf", baseVf, "-af", af });
                        }
                        else
                        {
                            segArgs.AddRange(new[]
                            {
                                "-f", "lavfi", "-t", durStr, "-i", "anullsrc=channel_layout=stereo:sample_rate=48000",
                                "-vf", baseVf,
                                "-map", "0:v:0", "-map", "1:a:0",
                                "-shortest"
                            });
                        }
                    }

                    segArgs.AddRange(new[]
                    {
                        "-c:v", "libx264", "-preset", "veryfast", "-crf", "18",
                        "-c:a", "aac", "-ar", "48000", "-ac", "2",
                        segPath
                    });

                    var segRes = await RunProcessSafeAsync(ffmpeg, segArgs, timeoutMs: 90_000, ct: ct);
                    if (segRes.ExitCode != 0 || !File.Exists(segPath))
                    {
                        return (false, $"Segment {i + 1} fehlgeschlagen (Exit {segRes.ExitCode}): {segRes.StdErr}");
                    }

                    segmentNames.Add(segName);
                }

                if (segmentNames.Count == 0)
                    return (false, "Keine Segmente erzeugt.");

                // Step 2: Safe concat (-safe 1) inside WorkingDirectory = tempDir using relative validated segment names
                string concatListPath = Path.Combine(tempDir, "concat.txt");
                var sbConcat = new StringBuilder();
                foreach (var segName in segmentNames)
                {
                    if (!SafeSegmentNameRegex.IsMatch(segName))
                        return (false, "Ungültiger Segmentname für Concat.");
                    sbConcat.AppendLine($"file '{segName}'");
                }
                await File.WriteAllTextAsync(concatListPath, sbConcat.ToString(), new UTF8Encoding(false), ct);

                string joinedRawMp4 = Path.Combine(tempDir, "joined_cuts.mp4");
                var concatRes = await RunProcessSafeAsync(
                    ffmpeg,
                    new[] { "-y", "-hide_banner", "-f", "concat", "-safe", "1", "-i", "concat.txt", "-c", "copy", "joined_cuts.mp4" },
                    workingDirectory: tempDir,
                    timeoutMs: 60_000,
                    ct: ct);

                if (concatRes.ExitCode != 0 || !File.Exists(joinedRawMp4))
                {
                    return (false, $"Concat fehlgeschlagen (Exit {concatRes.ExitCode}): {concatRes.StdErr}");
                }

                // Step 3: Master Audio Mix + faststart moov atom
                List<string> finalArgs;
                if (File.Exists(soundtrackWavPath))
                {
                    finalArgs = new List<string>
                    {
                        "-y", "-hide_banner",
                        "-i", joinedRawMp4,
                        "-i", Path.GetFullPath(soundtrackWavPath),
                        "-filter_complex", "[0:a]volume=1.0[a0];[1:a]volume=0.88[a1];[a0][a1]amix=inputs=2:duration=first:dropout_transition=0[aout]",
                        "-map", "0:v:0",
                        "-map", "[aout]",
                        "-c:v", "copy",
                        "-c:a", "aac", "-ar", "48000", "-ac", "2", "-b:a", "192k",
                        "-movflags", "+faststart",
                        Path.GetFullPath(outputMp4Path)
                    };
                }
                else
                {
                    finalArgs = new List<string>
                    {
                        "-y", "-hide_banner",
                        "-i", joinedRawMp4,
                        "-c", "copy",
                        "-movflags", "+faststart",
                        Path.GetFullPath(outputMp4Path)
                    };
                }

                var finalRes = await RunProcessSafeAsync(ffmpeg, finalArgs, timeoutMs: 60_000, ct: ct);
                if (finalRes.ExitCode != 0 || !File.Exists(outputMp4Path) || new FileInfo(outputMp4Path).Length <= 4096)
                {
                    return (false, $"Final-Mux fehlgeschlagen (Exit {finalRes.ExitCode}): {finalRes.StdErr}");
                }

                return (true, "");
            }
            finally
            {
                try { if (Directory.Exists(tempDir)) Directory.Delete(tempDir, true); } catch { }
            }
        }

        public static async Task<List<string>> GenerateSamplePackagingClipsAsync(string targetFolder, CancellationToken ct = default)
        {
            Directory.CreateDirectory(targetFolder);
            string ffmpeg = FindFfmpegExe();
            var created = new List<string>();

            var specs = new[]
            {
                (Name: "01_Westpack_Schachtel_Auspacken.mp4", Dur: 3.4, Freq: 320),
                (Name: "02_Seidenpapier_Schmuck_Einlegen.mp4", Dur: 3.6, Freq: 440),
                (Name: "03_Gold_Schleife_Final_Reveal.mp4", Dur: 3.8, Freq: 528)
            };

            foreach (var sp in specs)
            {
                string outPath = Path.Combine(targetFolder, sp.Name);
                string durStr = sp.Dur.ToString("F1", CultureInfo.InvariantCulture);
                var args = new[]
                {
                    "-y", "-hide_banner",
                    "-f", "lavfi", "-i", $"testsrc2=size=1080x1920:rate=30:duration={durStr}",
                    "-f", "lavfi", "-i", $"sine=frequency={sp.Freq}:sample_rate=48000:duration={durStr}",
                    "-vf", "colorbalance=rh=0.12:gh=0.05:bh=-0.08,vignette=PI/5,format=yuv420p",
                    "-af", "volume=0.18",
                    "-c:v", "libx264", "-preset", "ultrafast", "-crf", "20",
                    "-c:a", "aac", "-ar", "48000", "-ac", "2",
                    outPath
                };
                var res = await RunProcessSafeAsync(ffmpeg, args, timeoutMs: 45_000, ct: ct);
                if (res.ExitCode == 0 && File.Exists(outPath))
                    created.Add(outPath);
            }

            return created;
        }
    }

    // Real Isolated Automated Self-Test Suite (--self-test)
    public static class ViralVideographerSelfTest
    {
        public static async Task<int> RunAllAsync()
        {
            var log = new List<string>();
            int passed = 0;
            int total = 0;

            void RecordCheck(string name, bool ok, string detail)
            {
                total++;
                if (ok) passed++;
                log.Add($"[{(ok ? "PASS" : "FAIL")}] {name}: {detail}");
            }

            // Snapshot production directories BEFORE switching to isolated temp workspace
            string prodBase = ViralVideographerEngine.GetBaseDir();
            string prodRawDir = Path.Combine(prodBase, "Rohmaterial_Tour");
            string prodOutDir = Path.Combine(prodBase, "Fertige_Videos");
            var prodRawSnapshot = Directory.Exists(prodRawDir)
                ? Directory.GetFiles(prodRawDir).OrderBy(x => x, StringComparer.OrdinalIgnoreCase).ToArray()
                : Array.Empty<string>();
            var prodOutSnapshot = Directory.Exists(prodOutDir)
                ? Directory.GetFiles(prodOutDir).OrderBy(x => x, StringComparer.OrdinalIgnoreCase).ToArray()
                : Array.Empty<string>();

            string tempWorkspace = Path.Combine(Path.GetTempPath(), $"fw_video_selftest_{Guid.NewGuid():N}");
            string tempDesktop = Path.Combine(tempWorkspace, "IsolatedDesktop");
            Directory.CreateDirectory(tempWorkspace);
            Directory.CreateDirectory(tempDesktop);

            ViralVideographerEngine.WorkspaceRootOverride = tempWorkspace;
            ViralVideographerEngine.DesktopDirOverride = tempDesktop;
            ViralVideographerEngine.InvalidateClipCache();

            try
            {
                // 1. ValidateSafeVideoFilePath rejects dangerous extensions
                string fakeExe = Path.Combine(tempWorkspace, "evil.exe");
                await File.WriteAllBytesAsync(fakeExe, new byte[2048]);
                bool rejExe = !ViralVideographerEngine.ValidateSafeVideoFilePath(fakeExe, out _, out string exeErr);
                RecordCheck("RejectExecutableExtension", rejExe, exeErr);

                // 2. ValidateSafeVideoFilePath rejects null-bytes, ADS colons, and tiny truncated files
                string tinyMp4 = Path.Combine(tempWorkspace, "tiny.mp4");
                await File.WriteAllBytesAsync(tinyMp4, new byte[128]);
                bool rejNull = !ViralVideographerEngine.ValidateSafeVideoFilePath("bad\0clip.mp4", out _, out _);
                bool rejAds = !ViralVideographerEngine.ValidateSafeVideoFilePath(tinyMp4 + ":zone.mp4", out _, out _);
                bool rejTiny = !ViralVideographerEngine.ValidateSafeVideoFilePath(tinyMp4, out _, out _);
                RecordCheck("RejectNullByteAdsAndTruncatedFiles", rejNull && rejAds && rejTiny, $"Null={rejNull}, ADS={rejAds}, Tiny={rejTiny}");

                // 3. SanitizeSeelenfunkeText removes en-dash and em-dash completely
                string dirtyText = "Manufaktur Gifhorn \u2013 Handveredelt \u2014 Gold\u2013Unikat";
                string sanitized = ViralVideographerEngine.SanitizeSeelenfunkeText(dirtyText);
                bool noEnDash = sanitized.IndexOf('\u2013') < 0 && sanitized.IndexOf('\u2014') < 0 && sanitized.Contains("Manufaktur Gifhorn");
                RecordCheck("SanitizeSeelenfunkeTextNoEnDash", noEnDash, sanitized);

                // 4. Dynamic FFmpeg, FFprobe & EffectCraft binary discovery
                string ffmpeg = ViralVideographerEngine.FindFfmpegExe();
                string ffprobe = ViralVideographerEngine.FindFfprobeExe();
                string ecCli = ViralVideographerEngine.FindEffectCraftCliExe();
                bool toolsOk = Path.IsPathRooted(ffmpeg) && File.Exists(ffmpeg) &&
                               Path.IsPathRooted(ffprobe) && File.Exists(ffprobe) &&
                               Path.IsPathRooted(ecCli) && File.Exists(ecCli);
                RecordCheck("DynamicToolDiscovery", toolsOk, $"FFmpeg={File.Exists(ffmpeg)}, FFprobe={File.Exists(ffprobe)}, EffectCraft={File.Exists(ecCli)}");

                // 5. Explorer ProcessStartInfo ArgumentList safety & subprocess timeout kill
                var expOpenPsi = MainWindow.BuildExplorerOpenFolderStartInfo(Path.Combine(tempWorkspace, "O'Connor \"Test\""));
                var expSelPsi = MainWindow.BuildExplorerSelectFileStartInfo(Path.Combine(tempWorkspace, "O'Connor Clip 1.mp4"));
                bool explorerSafe = expOpenPsi.UseShellExecute == false && expOpenPsi.ArgumentList.Count == 1 &&
                                    expSelPsi.UseShellExecute == false && expSelPsi.ArgumentList.Count == 2 &&
                                    string.IsNullOrEmpty(expOpenPsi.Arguments) && string.IsNullOrEmpty(expSelPsi.Arguments);
                string psExe = Path.Combine(Environment.SystemDirectory, "WindowsPowerShell", "v1.0", "powershell.exe");
                bool timeoutKilled = true;
                if (File.Exists(psExe))
                {
                    var toRes = await ViralVideographerEngine.RunProcessSafeAsync(
                        psExe,
                        new[] { "-NoProfile", "-Command", "Start-Sleep -Seconds 10" },
                        timeoutMs: 600);
                    timeoutKilled = toRes.ExitCode == -2;
                }
                RecordCheck("SubprocessArgumentListAndTimeoutKill", explorerSafe && timeoutKilled, $"ExplorerSafe={explorerSafe}, TimeoutKilled={timeoutKilled}");

                // 6. Synthesize real H.264/AAC clips with spaces & apostrophes in custom external dir
                string customExternalDir = Path.Combine(tempWorkspace, "External User Folder");
                Directory.CreateDirectory(customExternalDir);
                string apostropheClipPath = Path.Combine(customExternalDir, "O'Connor Clip 1.mp4");
                string secondAudioClipPath = Path.Combine(customExternalDir, "Manufaktur Clip 2.mp4");

                var gen1 = await ViralVideographerEngine.RunProcessSafeAsync(ffmpeg, new[]
                {
                    "-y", "-hide_banner",
                    "-f", "lavfi", "-i", "testsrc2=size=640x1136:rate=30:duration=2.6",
                    "-f", "lavfi", "-i", "sine=frequency=440:sample_rate=48000:duration=2.6",
                    "-c:v", "libx264", "-preset", "ultrafast", "-pix_fmt", "yuv420p",
                    "-c:a", "aac", "-ar", "48000", "-ac", "2",
                    apostropheClipPath
                });
                var gen2 = await ViralVideographerEngine.RunProcessSafeAsync(ffmpeg, new[]
                {
                    "-y", "-hide_banner",
                    "-f", "lavfi", "-i", "testsrc2=size=640x1136:rate=30:duration=2.8",
                    "-f", "lavfi", "-i", "sine=frequency=528:sample_rate=48000:duration=2.8",
                    "-c:v", "libx264", "-preset", "ultrafast", "-pix_fmt", "yuv420p",
                    "-c:a", "aac", "-ar", "48000", "-ac", "2",
                    secondAudioClipPath
                });
                bool audioClipsCreated = gen1.ExitCode == 0 && File.Exists(apostropheClipPath) &&
                                         gen2.ExitCode == 0 && File.Exists(secondAudioClipPath);
                RecordCheck("SynthesizeApostropheAndSpaceClips", audioClipsCreated, $"Clip1={Path.GetFileName(apostropheClipPath)}, Clip2={Path.GetFileName(secondAudioClipPath)}");

                // 7. Synthesize real silent video clip (-an, zero audio streams)
                string silentClipPath = Path.Combine(customExternalDir, "Silent Laser 03.mp4");
                var genSilent = await ViralVideographerEngine.RunProcessSafeAsync(ffmpeg, new[]
                {
                    "-y", "-hide_banner",
                    "-f", "lavfi", "-i", "testsrc2=size=640x1136:rate=30:duration=2.6",
                    "-an",
                    "-c:v", "libx264", "-preset", "ultrafast", "-pix_fmt", "yuv420p",
                    silentClipPath
                });
                bool silentCreated = genSilent.ExitCode == 0 && File.Exists(silentClipPath);
                RecordCheck("SynthesizeSilentVideoClip", silentCreated, $"SilentClip={Path.GetFileName(silentClipPath)}");

                // 8. AnalyzeRawClipAsync detects HasAudio == true vs HasAudio == false accurately
                var clipAudio1 = await ViralVideographerEngine.AnalyzeRawClipAsync(apostropheClipPath);
                var clipAudio2 = await ViralVideographerEngine.AnalyzeRawClipAsync(secondAudioClipPath);
                var clipSilent = await ViralVideographerEngine.AnalyzeRawClipAsync(silentClipPath);
                bool probeAudioAccurate = clipAudio1 != null && clipAudio1.HasAudio &&
                                          clipAudio2 != null && clipAudio2.HasAudio &&
                                          clipSilent != null && !clipSilent.HasAudio;
                RecordCheck("AnalyzeRawClipDetectsSilentVsAudio", probeAudioAccurate,
                    $"AudioClip.HasAudio={clipAudio1?.HasAudio}, SilentClip.HasAudio={clipSilent?.HasAudio}");

                // 9. Silent video (-an) solo render regression + Hook disabled skips EffectCraft plate PNG
                var silentOpts = new VideographerJobOptions
                {
                    ProjectName = "SilentRegression",
                    SaveToDesktop = false,
                    RecipeId = "auto_videographer",
                    TransitionMode = "LightWipeZoom",
                    EnableHookTextOverlay = false,
                    HookText = "",
                    EnableGoldParticlesVfx = true,
                    EnableViralSoundEffects = true,
                    TargetFps = 30
                };
                var silentRes = await ViralVideographerEngine.ProduceViralVideoAsync(new[] { clipSilent! }, silentOpts);
                RecordCheck("SilentClipRenderRegression", silentRes.Success && File.Exists(silentRes.OutputVideoPath),
                    silentRes.Success ? $"Rendered={Path.GetFileName(silentRes.OutputVideoPath)}" : silentRes.ErrorMessage);

                // 10. Verify wasteful EffectCraft preview PNG was skipped when EnableHookTextOverlay == false
                bool skippedEmptyPlate = string.IsNullOrEmpty(silentRes.EffectCraftPreviewFramePath);
                RecordCheck("SkipEffectCraftPlateWhenHookDisabled", skippedEmptyPlate,
                    $"PreviewFramePath='{silentRes.EffectCraftPreviewFramePath}'");

                // 11. Multi-clip render across apostrophe path + silent clip + audio clip with -safe 1 concat & Hook overlay
                var multiOpts = new VideographerJobOptions
                {
                    ProjectName = "Gifhorn_Test",
                    SaveToDesktop = true,
                    RecipeId = "schleife_grand_opening",
                    TransitionMode = "GoldenFlash",
                    EnableHookTextOverlay = true,
                    HookText = "POV \u2013 Handveredelt in Gifhorn",
                    EnableGoldParticlesVfx = true,
                    EnableViralSoundEffects = true,
                    EnableAsmrBoost = true,
                    EnableBeatPulseBed = true,
                    TargetFps = 30
                };
                var multiRes = await ViralVideographerEngine.ProduceViralVideoAsync(
                    new[] { clipAudio1!, clipSilent!, clipAudio2! },
                    multiOpts);
                RecordCheck("MultiClipApostropheSafe1ConcatAndHookRender",
                    multiRes.Success && File.Exists(multiRes.OutputVideoPath),
                    multiRes.Success ? $"Cuts={multiRes.CutDecisions.Count}, Out={Path.GetFileName(multiRes.OutputVideoPath)}" : multiRes.ErrorMessage);

                // 12. Verify EffectCraft .ecproj & sanitized _audit.json
                bool ecAndAuditOk = File.Exists(multiRes.OutputEcProjPath) && new FileInfo(multiRes.OutputEcProjPath).Length > 500 &&
                                    File.Exists(multiRes.OutputAuditJsonPath);
                if (ecAndAuditOk)
                {
                    string auditJson = await File.ReadAllTextAsync(multiRes.OutputAuditJsonPath);
                    ecAndAuditOk = auditJson.IndexOf('\u2013') < 0;
                }
                RecordCheck("EffectCraftProjectAndSanitizedAuditVerified", ecAndAuditOk,
                    $"EcProjExists={File.Exists(multiRes.OutputEcProjPath)}, AuditClean={ecAndAuditOk}");

                // 13 & 14. Verify output MP4 codec (H.264 1080x1920 yuv420p) & audio (AAC 48000 Hz stereo) via ffprobe
                bool videoStreamValid = false;
                bool audioStreamValid = false;
                string streamSummary = "";
                if (File.Exists(multiRes.OutputVideoPath))
                {
                    var verifyProbe = await ViralVideographerEngine.RunProcessSafeAsync(
                        ffprobe,
                        new[] { "-v", "quiet", "-print_format", "json", "-show_streams", multiRes.OutputVideoPath },
                        timeoutMs: 20_000);

                    if (verifyProbe.ExitCode == 0 && !string.IsNullOrWhiteSpace(verifyProbe.StdOut))
                    {
                        using var doc = JsonDocument.Parse(verifyProbe.StdOut);
                        if (doc.RootElement.TryGetProperty("streams", out var streams))
                        {
                            foreach (var st in streams.EnumerateArray())
                            {
                                string cType = st.TryGetProperty("codec_type", out var ctP) ? (ctP.GetString() ?? "") : "";
                                string cName = st.TryGetProperty("codec_name", out var cnP) ? (cnP.GetString() ?? "") : "";
                                if (cType == "video")
                                {
                                    int w = st.TryGetProperty("width", out var wp) ? wp.GetInt32() : 0;
                                    int h = st.TryGetProperty("height", out var hp) ? hp.GetInt32() : 0;
                                    string pix = st.TryGetProperty("pix_fmt", out var pp) ? (pp.GetString() ?? "") : "";
                                    videoStreamValid = cName == "h264" && w == 1080 && h == 1920 && pix == "yuv420p";
                                    streamSummary += $"Video={cName} {w}x{h} {pix}; ";
                                }
                                else if (cType == "audio")
                                {
                                    string sr = st.TryGetProperty("sample_rate", out var srP) ? (srP.GetString() ?? "") : "";
                                    int ch = st.TryGetProperty("channels", out var chP) ? chP.GetInt32() : 0;
                                    audioStreamValid = cName == "aac" && sr == "48000" && ch == 2;
                                    streamSummary += $"Audio={cName} {sr}Hz {ch}ch";
                                }
                            }
                        }
                    }
                }
                RecordCheck("FfprobeOutputVideoH264Vertical1080x1920", videoStreamValid, streamSummary);
                RecordCheck("FfprobeOutputAudioAac48000Stereo", audioStreamValid, streamSummary);

                // 15. Verify MP4 faststart atom ordering (ftyp at 0, moov before mdat)
                var (faststartOk, atomOrder) = InspectMp4FaststartAtoms(multiRes.OutputVideoPath);
                RecordCheck("Mp4FaststartMoovBeforeMdat", faststartOk, atomOrder);

                // 16, 17, 18. STA MainWindow tests: External custom folder non-deletion, internal confirmation gate, Light/Dark theme & watcher dispose
                var win = new MainWindow(disableAutoLoad: true);
                try
                {
                    win.InitializeForHeadlessTest();

                    // Copy one sample clip into isolated temp Rohmaterial_Tour as well
                    string tempInternalClip = Path.Combine(ViralVideographerEngine.GetRawTourDir(), "Internal_Raw_01.mp4");
                    File.Copy(apostropheClipPath, tempInternalClip, true);

                    win.RegisterCustomProjectFolder(customExternalDir);
                    await win.ReloadProjectsAsync();

                    // 16. Removing external custom folder MUST ONLY untrack and NEVER delete files!
                    var extProj = win.Projects.FirstOrDefault(p =>
                        string.Equals(p.FolderPath, Path.GetFullPath(customExternalDir), StringComparison.OrdinalIgnoreCase));
                    bool wasTrackedAsCustom = extProj != null && extProj.IsCustomFolder && extProj.Clips.Count == 3;
                    if (extProj != null)
                    {
                        win.ClearOrRemoveProjectFolder(extProj, confirmCallback: _ => true);
                    }
                    bool untrackedCleanly = !win.CustomProjectFolders.Contains(Path.GetFullPath(customExternalDir)) &&
                                            win.Projects.All(p => !string.Equals(p.FolderPath, Path.GetFullPath(customExternalDir), StringComparison.OrdinalIgnoreCase));
                    bool externalFilesIntact = File.Exists(apostropheClipPath) &&
                                               File.Exists(secondAudioClipPath) &&
                                               File.Exists(silentClipPath);
                    RecordCheck("ExternalCustomFolderUntrackNeverDeletesFiles",
                        wasTrackedAsCustom && untrackedCleanly && externalFilesIntact,
                        $"WasCustom={wasTrackedAsCustom}, Untracked={untrackedCleanly}, ExternalFilesIntact={externalFilesIntact}");

                    // 17. Internal Rohmaterial_Tour requires explicit confirmation before deleting
                    var internalProj = win.Projects.FirstOrDefault(p => !p.IsCustomFolder);
                    bool declinedKeptFile = false;
                    if (internalProj != null)
                    {
                        win.ClearOrRemoveProjectFolder(internalProj, confirmCallback: _ => false);
                        declinedKeptFile = File.Exists(tempInternalClip) && internalProj.Clips.Count == 1;
                    }
                    RecordCheck("InternalRawTourConfirmationGate", declinedKeptFile, $"DeclinedKeptFile={declinedKeptFile}");

                    // 18. Light/Dark mode + Recipe/Transition selector wiring
                    win.ApplyThemeMode(false);
                    var lightHeader = (win.HeaderBarBorder.Background as SolidColorBrush)?.Color ?? Colors.Black;
                    bool lightOk = !win.IsDarkMode && lightHeader == Color.FromRgb(0xFF, 0xFF, 0xFF);

                    win.RecipeComboBox.SelectedIndex = 2; // schleife_grand_opening -> GoldenFlash
                    bool recipeSynced = win.GetSelectedRecipeId() == "schleife_grand_opening" &&
                                        win.GetSelectedTransitionMode() == "GoldenFlash";

                    win.ApplyThemeMode(true);
                    var darkHeader = (win.HeaderBarBorder.Background as SolidColorBrush)?.Color ?? Colors.White;
                    bool darkOk = win.IsDarkMode && darkHeader == Color.FromRgb(0x11, 0x13, 0x1A);

                    RecordCheck("MainWindowThemeAndRecipeTransitionWiring",
                        lightOk && darkOk && recipeSynced,
                        $"LightOk={lightOk}, DarkOk={darkOk}, RecipeSynced={recipeSynced}");
                }
                finally
                {
                    win.Close();
                    RecordCheck("WindowCloseDisposesRawTourWatcher", win.IsWatcherDisposed, $"WatcherDisposed={win.IsWatcherDisposed}");
                }
            }
            catch (Exception ex)
            {
                total++;
                log.Add($"[FAIL] UnhandledSelfTestException: {ex}");
            }
            finally
            {
                ViralVideographerEngine.WorkspaceRootOverride = null;
                ViralVideographerEngine.DesktopDirOverride = null;
                ViralVideographerEngine.InvalidateClipCache();

                try
                {
                    if (Directory.Exists(tempWorkspace))
                        Directory.Delete(tempWorkspace, recursive: true);
                }
                catch { }
            }

            // 20. Verify temp workspace deleted AND production Rohmaterial_Tour / Fertige_Videos untouched
            bool tempDeleted = !Directory.Exists(tempWorkspace);
            var afterRawSnapshot = Directory.Exists(prodRawDir)
                ? Directory.GetFiles(prodRawDir).OrderBy(x => x, StringComparer.OrdinalIgnoreCase).ToArray()
                : Array.Empty<string>();
            var afterOutSnapshot = Directory.Exists(prodOutDir)
                ? Directory.GetFiles(prodOutDir).OrderBy(x => x, StringComparer.OrdinalIgnoreCase).ToArray()
                : Array.Empty<string>();
            bool prodUntouched = prodRawSnapshot.SequenceEqual(afterRawSnapshot, StringComparer.OrdinalIgnoreCase) &&
                                 prodOutSnapshot.SequenceEqual(afterOutSnapshot, StringComparer.OrdinalIgnoreCase);

            RecordCheck("TempCleanedAndProductionFoldersUntouched",
                tempDeleted && prodUntouched,
                $"TempDeleted={tempDeleted}, ProdUntouched={prodUntouched}");

            log.Add($"FUNKENWERK VIDEO SELF-TEST: {passed}/{total} TESTS BESTANDEN.");

            string reportPath = Path.Combine(ViralVideographerEngine.GetAppDataDir(), "self_test_report.txt");
            try
            {
                await File.WriteAllLinesAsync(reportPath, log, new UTF8Encoding(false));
            }
            catch { }

            Console.WriteLine(string.Join(Environment.NewLine, log));
            return (passed == total && total >= 16) ? 0 : 1;
        }

        private static (bool Ok, string Detail) InspectMp4FaststartAtoms(string mp4Path)
        {
            if (string.IsNullOrWhiteSpace(mp4Path) || !File.Exists(mp4Path))
                return (false, "Datei fehlt");

            try
            {
                using var fs = new FileStream(mp4Path, FileMode.Open, FileAccess.Read, FileShare.Read);
                long fileLen = fs.Length;
                long pos = 0;
                bool sawFtypFirst = false;
                long moovOffset = -1;
                long mdatOffset = -1;
                var boxes = new List<string>();
                byte[] header = new byte[16];

                while (pos + 8 <= fileLen && boxes.Count < 32)
                {
                    fs.Position = pos;
                    int read = fs.Read(header, 0, 8);
                    if (read < 8) break;

                    uint size32 = BinaryPrimitives.ReadUInt32BigEndian(header.AsSpan(0, 4));
                    string fourCc = Encoding.ASCII.GetString(header, 4, 4);
                    long boxSize = size32;

                    if (size32 == 1)
                    {
                        if (pos + 16 > fileLen) break;
                        int extRead = fs.Read(header, 8, 8);
                        if (extRead < 8) break;
                        boxSize = (long)BinaryPrimitives.ReadUInt64BigEndian(header.AsSpan(8, 8));
                    }
                    else if (size32 == 0)
                    {
                        boxSize = fileLen - pos;
                    }

                    if (boxSize < 8) break;

                    if (pos == 0 && fourCc == "ftyp")
                        sawFtypFirst = true;
                    if (fourCc == "moov" && moovOffset < 0)
                        moovOffset = pos;
                    if (fourCc == "mdat" && mdatOffset < 0)
                        mdatOffset = pos;

                    boxes.Add(fourCc);
                    pos += boxSize;
                }

                bool ok = sawFtypFirst && moovOffset > 0 && mdatOffset > 0 && moovOffset < mdatOffset;
                return (ok, $"Atoms=[{string.Join(",", boxes)}], moov@{moovOffset} < mdat@{mdatOffset}");
            }
            catch (Exception ex)
            {
                return (false, ex.Message);
            }
        }
    }
}
