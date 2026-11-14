using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Drawing.Imaging;
using System.Drawing.Text;
using System.Globalization;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Text.Json.Serialization;
using System.Threading;
using System.Threading.Tasks;

namespace FunkenwerkCraft
{
    public sealed class CraftSettingsModel
    {
        public bool DarkMode { get; set; } = true;
        public string LastProjectName { get; set; } = "Projekt 1";
        public string DefaultAspect { get; set; } = "9:16";
        public int FilmCraftControlPort { get; set; } = 9876;
    }

    public sealed class CraftSceneItem
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N")[..8];
        public string FileName { get; set; } = "";
        public string MediaType { get; set; } = "video"; // "video" | "image" | "brand_outro"
        public double StartSec { get; set; } = 0.0;
        public double DurationSec { get; set; } = 2.2;
        public string FitMode { get; set; } = "fill"; // "fill" | "contain_blur"
        public string SpeedMode { get; set; } = "speed_ramp"; // "speed_ramp" | "slow_motion" | "linear"
        public double SlowMoFactor { get; set; } = 0.42; // 0.25 .. 0.85 (lower = slower hero slow-motion)
        public string OverlayBadge { get; set; } = "";
        public string OverlayTitle { get; set; } = "";
        public string OverlaySubtitle { get; set; } = "";
        public string Transition { get; set; } = "whip_zoom"; // "whip_zoom" | "whip_pan" | "dark_cut" | "velvet_dissolve" | "flash_cut" | "dissolve" | "smoothleft" | "fadeblack"
        public double TransitionDurSec { get; set; } = 0.0; // 0 = inherit project TransitionDurationSec
        public string TransitionSfx { get; set; } = @"Uebergaenge_Whoosh\Cinema_Whoosh_Deep_Impact_01.wav";
        public string ImpactSfx { get; set; } = "";
        public string RiserSfx { get; set; } = "";
        public bool KeepOriginalAudio { get; set; } = false;
        public double OriginalAudioVolume { get; set; } = 0.20;
    }

    public sealed class CraftProjectModel
    {
        public string Name { get; set; } = "Projekt 1";
        public string AspectRatio { get; set; } = "9:16"; // "9:16" | "1:1" | "16:9"
        public int Fps { get; set; } = 60;
        public double TransitionDurationSec { get; set; } = 0.20;
        public string ColorGrade { get; set; } = "dark_luxury"; // "dark_luxury" | "natural"
        public string OverlayStyle { get; set; } = "editorial_minimal"; // "editorial_minimal" | "card"
        public string BackgroundMusic { get; set; } = @"Beats_und_Ambient\Dark_Luxury_Viral_808_120BPM_CC0.wav";
        public double MusicStartSec { get; set; } = 0.0;
        public double MusicVolume { get; set; } = 0.56;
        public double SfxVolume { get; set; } = 0.82;
        public string IntroAccentSfx { get; set; } = @"Effekte_Akzente\Sub_Bass_Drop_Punch_06.wav";
        public string OutroAccentSfx { get; set; } = @"Effekte_Akzente\Mystic_Crystal_Velvet_Swell_05.wav";
        public bool ShowBrandHeader { get; set; } = false;
        public string BrandHeaderText { get; set; } = "MEIN SEELENFUNKE";
        public string OutputFileName { get; set; } = "Social_Media_Ready_Projekt_1.mp4";
        public string LastRenderedPath { get; set; } = "";
        public string LastFilmCraftProjectPath { get; set; } = "";
        public DateTime UpdatedAtUtc { get; set; } = DateTime.UtcNow;
        public List<CraftSceneItem> Scenes { get; set; } = new();
    }

    public sealed class CraftSoundItem
    {
        public string FileName { get; set; } = "";
        public string Category { get; set; } = "";
        public string RelativePath { get; set; } = "";
        public string FullPath { get; set; } = "";
        public double DurationSec { get; set; }
        public string License { get; set; } = "CC0-1.0";
        public bool CommercialUseAllowed { get; set; } = true;
        public string Source { get; set; } = "";
    }

    public sealed class CraftMediaInfo
    {
        public string FileName { get; set; } = "";
        public string FullPath { get; set; } = "";
        public string MediaType { get; set; } = "video";
        public int Width { get; set; }
        public int Height { get; set; }
        public int Rotation { get; set; }
        public int EffectiveWidth => (Math.Abs(Rotation) == 90 || Math.Abs(Rotation) == 270) ? Height : Width;
        public int EffectiveHeight => (Math.Abs(Rotation) == 90 || Math.Abs(Rotation) == 270) ? Width : Height;
        public bool IsPortrait => EffectiveHeight >= EffectiveWidth;
        public double DurationSec { get; set; }
        public double Fps { get; set; }
        public bool HasAudio { get; set; }
        public double SizeMb { get; set; }
    }

    public static class CraftEngine
    {
        private static readonly JsonSerializerOptions JsonOpts = new()
        {
            WriteIndented = true,
            PropertyNameCaseInsensitive = true,
            DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull
        };

        private static readonly byte[] BridgeDpapiEntropy = Encoding.UTF8.GetBytes("FunkenwerkCraft.Bridge.v1");
        private static readonly ConcurrentDictionary<string, (double PeakSec, double DurationSec)> AudioPeakCache = new(StringComparer.OrdinalIgnoreCase);
        private static readonly ConcurrentDictionary<string, string> TrustedToolCache = new(StringComparer.OrdinalIgnoreCase);

        public static readonly HashSet<string> VideoExts = new(StringComparer.OrdinalIgnoreCase)
            { ".mp4", ".mov", ".mkv", ".webm", ".avi", ".m4v" };

        public static readonly HashSet<string> ImageExts = new(StringComparer.OrdinalIgnoreCase)
            { ".jpg", ".jpeg", ".png", ".webp", ".bmp" };

        public static readonly HashSet<string> AudioExts = new(StringComparer.OrdinalIgnoreCase)
            { ".wav", ".mp3", ".ogg", ".flac", ".m4a" };

        private static readonly HashSet<string> ReservedWindowsNames = new(StringComparer.OrdinalIgnoreCase)
        {
            "CON", "PRN", "AUX", "NUL",
            "COM1", "COM2", "COM3", "COM4", "COM5", "COM6", "COM7", "COM8", "COM9",
            "LPT1", "LPT2", "LPT3", "LPT4", "LPT5", "LPT6", "LPT7", "LPT8", "LPT9"
        };

        private static readonly HashSet<string> DisallowedExecutableExts = new(StringComparer.OrdinalIgnoreCase)
        {
            ".exe", ".dll", ".bat", ".cmd", ".ps1", ".vbs", ".js", ".msi", ".scr", ".com", ".hta", ".lnk", ".pif"
        };

        private static readonly HashSet<string> AllowedFilmCraftSubcommands = new(StringComparer.OrdinalIgnoreCase)
        {
            "commands", "describe", "help", "--help", "--version", "inspect", "probe", "run"
        };

        public const int MaxIpcLineBytes = 65536;

        // Isolated workspace overrides used exclusively by --self-test so user projects remain 100% untouched
        public static string? WorkspaceOverrideRoot { get; set; }
        public static string? LocalDataOverrideDir { get; set; }

        public static string ProductionWorkspaceRoot
        {
            get
            {
                string desktopCol = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory),
                    "Funkenwerk Collection",
                    "Funkenwerk Craft");
                if (Directory.Exists(desktopCol)) return desktopCol;

                string baseDir = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\', '/');
                if (baseDir.EndsWith("Funkenwerk Craft", StringComparison.OrdinalIgnoreCase))
                    return baseDir;

                Directory.CreateDirectory(desktopCol);
                return desktopCol;
            }
        }

        public static string WorkspaceRoot
        {
            get
            {
                if (!string.IsNullOrWhiteSpace(WorkspaceOverrideRoot))
                    return EnsureDir(WorkspaceOverrideRoot!);
                return ProductionWorkspaceRoot;
            }
        }

        public static string VideosRoot => EnsureDir(Path.Combine(WorkspaceRoot, "Videos"));

        public static string SoundsRoot
        {
            get
            {
                if (!string.IsNullOrWhiteSpace(WorkspaceOverrideRoot))
                {
                    string customSounds = Path.Combine(WorkspaceOverrideRoot!, "Sounds");
                    if (Directory.Exists(customSounds)) return customSounds;
                }
                return EnsureDir(Path.Combine(ProductionWorkspaceRoot, "Sounds"));
            }
        }

        public static string FontsRoot
        {
            get
            {
                if (!string.IsNullOrWhiteSpace(WorkspaceOverrideRoot))
                {
                    string customFonts = Path.Combine(WorkspaceOverrideRoot!, "Fonts");
                    if (Directory.Exists(customFonts)) return customFonts;
                }
                return EnsureDir(Path.Combine(ProductionWorkspaceRoot, "Fonts"));
            }
        }

        public static string EngineRoot
        {
            get
            {
                if (!string.IsNullOrWhiteSpace(WorkspaceOverrideRoot))
                {
                    string customEngine = Path.Combine(WorkspaceOverrideRoot!, "engine");
                    if (Directory.Exists(customEngine)) return customEngine;
                }
                return EnsureDir(Path.Combine(ProductionWorkspaceRoot, "engine"));
            }
        }

        public static string ArtCraftExe => Path.Combine(EngineRoot, "artcraft", "artcraft.exe");
        public static string FilmCraftExe => Path.Combine(EngineRoot, "filmcraft", "filmcraft.exe");
        public static string FilmCraftCliExe => Path.Combine(EngineRoot, "filmcraft", "filmcraft-cli.exe");
        public static string SoundCraftExe => Path.Combine(EngineRoot, "soundcraft", "soundcraft.exe");
        public static string SoundCraftCliExe => Path.Combine(EngineRoot, "soundcraft", "soundcraft-cli.exe");

        public static string LocalDataDir
        {
            get
            {
                if (!string.IsNullOrWhiteSpace(LocalDataOverrideDir))
                    return EnsureDir(LocalDataOverrideDir!);

                string dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkCraft");
                if (!Directory.Exists(dir)) Directory.CreateDirectory(dir);
                return dir;
            }
        }

        public static string SettingsFilePath => Path.Combine(LocalDataDir, "craft_settings.json");
        public static string BridgeDirectoryPath => Path.Combine(LocalDataDir, ".funkenwerk_bridge");
        public static string BridgeStateFilePath => Path.Combine(BridgeDirectoryPath, "bridge_state.json");

        public static string PipeName
        {
            get
            {
                string sid = WindowsIdentity.GetCurrent().User?.Value ?? Environment.UserName;
                string suffix = !string.IsNullOrWhiteSpace(LocalDataOverrideDir) ? "_SelfTest" : "";
                return $"Funkenwerk_Craft_IPC_{sid}{suffix}";
            }
        }

        private static string EnsureDir(string p)
        {
            if (!Directory.Exists(p)) Directory.CreateDirectory(p);
            return p;
        }

        // =====================================================================
        // SECURITY & PATH VALIDATION HELPERS
        // =====================================================================
        private static bool IsReservedWindowsDeviceName(string? fileOrFolderName)
        {
            if (string.IsNullOrWhiteSpace(fileOrFolderName)) return false;
            string trimmed = fileOrFolderName.Trim().TrimEnd('.', ' ');
            if (ReservedWindowsNames.Contains(trimmed)) return true;

            int dotIdx = trimmed.IndexOf('.');
            if (dotIdx > 0)
            {
                string stem = trimmed[..dotIdx].Trim();
                if (ReservedWindowsNames.Contains(stem)) return true;
            }
            return false;
        }

        private static bool HasAlternateDataStreamOrUnc(string rawPath)
        {
            if (string.IsNullOrWhiteSpace(rawPath)) return false;
            string p = rawPath.Trim();
            if (p.StartsWith(@"\\", StringComparison.Ordinal) || p.StartsWith("//", StringComparison.Ordinal))
                return true;

            if (p.Length >= 2 && char.IsAsciiLetter(p[0]) && p[1] == ':' && (p.Length == 2 || p[2] == '\\' || p[2] == '/'))
            {
                return p.IndexOf(':', 2) >= 0;
            }

            return p.Contains(':', StringComparison.Ordinal);
        }

        private static bool ContainsTraversalSegment(string rawPath)
        {
            if (string.IsNullOrWhiteSpace(rawPath)) return false;
            string[] parts = rawPath.Split(new[] { '\\', '/' }, StringSplitOptions.RemoveEmptyEntries);
            foreach (string part in parts)
            {
                if (part == ".." || part.TrimEnd(' ') == "..")
                    return true;
            }
            return false;
        }

        private static string NormalizeRootPrefix(string rootDir)
        {
            return Path.GetFullPath(rootDir).TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar)
                + Path.DirectorySeparatorChar;
        }

        public static void EnsureNoReparsePointsInChain(string fullPath, string? stopAtContainerRoot = null)
        {
            string normalizedFull = Path.GetFullPath(fullPath);
            string? stopNorm = !string.IsNullOrWhiteSpace(stopAtContainerRoot)
                ? Path.GetFullPath(stopAtContainerRoot).TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar)
                : null;

            if (File.Exists(normalizedFull))
            {
                var fi = new FileInfo(normalizedFull);
                if (fi.Attributes.HasFlag(FileAttributes.ReparsePoint) || fi.LinkTarget != null)
                    throw new UnauthorizedAccessException("Symlinks und Reparse-Points sind aus Sicherheitsgruenden blockiert.");
            }
            else if (Directory.Exists(normalizedFull))
            {
                var diSelf = new DirectoryInfo(normalizedFull);
                if (diSelf.Attributes.HasFlag(FileAttributes.ReparsePoint) || diSelf.LinkTarget != null)
                    throw new UnauthorizedAccessException("Symlinks und Junctions sind aus Sicherheitsgruenden blockiert.");
            }

            string? curDir = Directory.Exists(normalizedFull) ? normalizedFull : Path.GetDirectoryName(normalizedFull);
            while (!string.IsNullOrWhiteSpace(curDir))
            {
                string trimmedCur = curDir.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
                string? rootDrive = Path.GetPathRoot(curDir)?.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
                if (!string.IsNullOrEmpty(rootDrive) && string.Equals(trimmedCur, rootDrive, StringComparison.OrdinalIgnoreCase))
                    break;

                if (Directory.Exists(curDir))
                {
                    var di = new DirectoryInfo(curDir);
                    if (di.Attributes.HasFlag(FileAttributes.ReparsePoint) || di.LinkTarget != null)
                        throw new UnauthorizedAccessException($"Verzeichnis-Junction / Reparse-Point blockiert: {di.Name}");
                }

                if (stopNorm != null && string.Equals(trimmedCur, stopNorm, StringComparison.OrdinalIgnoreCase))
                    break;

                curDir = Path.GetDirectoryName(trimmedCur);
            }
        }

        public static string SanitizeCleanText(string? input)
        {
            if (string.IsNullOrWhiteSpace(input)) return "";
            // Strictly enforce Seelenfunke rule: never allow en-dashes or em-dashes in rendered text
            return input
                .Replace(" \u2013 ", " \u00B7 ")
                .Replace(" \u2014 ", " \u00B7 ")
                .Replace('\u2013', '-')
                .Replace('\u2014', '-')
                .Trim();
        }

        public static string SanitizeProjectName(string name)
        {
            if (string.IsNullOrWhiteSpace(name))
                throw new ArgumentException("Projektname darf nicht leer sein.");

            if (HasAlternateDataStreamOrUnc(name) ||
                ContainsTraversalSegment(name) ||
                name.IndexOfAny(new[] { '\\', '/', ':', '*', '?', '"', '<', '>', '|' }) >= 0 ||
                name.Any(c => c < 32))
            {
                throw new UnauthorizedAccessException("Ungueltiger oder unsicherer Projektname blockiert.");
            }

            string clean = SanitizeCleanText(name).Trim('.', ' ');
            if (string.IsNullOrWhiteSpace(clean))
                throw new ArgumentException("Ungueltiger Projektname.");

            if (IsReservedWindowsDeviceName(clean))
                throw new UnauthorizedAccessException($"Reservierter Windows-Geraetename nicht erlaubt: {clean}");

            string ext = Path.GetExtension(clean);
            if (!string.IsNullOrEmpty(ext) && DisallowedExecutableExts.Contains(ext))
                throw new UnauthorizedAccessException($"Nicht erlaubte Endung im Projektnamen: {ext}");

            return clean;
        }

        public static string ComputeNextProjectName(string videosRoot)
        {
            int nextNum = 1;
            if (!string.IsNullOrWhiteSpace(videosRoot) && Directory.Exists(videosRoot))
            {
                nextNum = Directory.GetDirectories(videosRoot).Length + 1;
                if (nextNum < 1) nextNum = 1;
                while (Directory.Exists(Path.Combine(videosRoot, $"Projekt {nextNum}")))
                {
                    nextNum++;
                }
            }
            return $"Projekt {nextNum}";
        }

        private static int CompareNaturalStrings(string? a, string? b)
        {
            if (ReferenceEquals(a, b)) return 0;
            if (a == null) return -1;
            if (b == null) return 1;

            int ia = 0, ib = 0;
            while (ia < a.Length && ib < b.Length)
            {
                if (char.IsDigit(a[ia]) && char.IsDigit(b[ib]))
                {
                    int sa = ia, sb = ib;
                    while (ia < a.Length && char.IsDigit(a[ia])) ia++;
                    while (ib < b.Length && char.IsDigit(b[ib])) ib++;

                    string na = a[sa..ia].TrimStart('0');
                    string nb = b[sb..ib].TrimStart('0');
                    if (na.Length != nb.Length) return na.Length.CompareTo(nb.Length);
                    int cmpNum = string.CompareOrdinal(na, nb);
                    if (cmpNum != 0) return cmpNum;
                    int lenDiff = (ia - sa).CompareTo(ib - sb);
                    if (lenDiff != 0) return lenDiff;
                }
                else
                {
                    int cmp = char.ToUpperInvariant(a[ia]).CompareTo(char.ToUpperInvariant(b[ib]));
                    if (cmp != 0) return cmp;
                    ia++;
                    ib++;
                }
            }
            return a.Length.CompareTo(b.Length);
        }

        public static string? ResolveSafeAudioPath(string? relOrFull, string? projectDir = null, bool requireExists = true)
        {
            if (string.IsNullOrWhiteSpace(relOrFull))
                return null;

            string trimmed = relOrFull.Trim();
            if (HasAlternateDataStreamOrUnc(trimmed) || ContainsTraversalSegment(trimmed))
                throw new UnauthorizedAccessException($"Unsicherer Audio-Pfad blockiert: {relOrFull}");

            string leaf = Path.GetFileName(trimmed);
            if (string.IsNullOrWhiteSpace(leaf) || IsReservedWindowsDeviceName(leaf))
                throw new UnauthorizedAccessException($"Ungueltiger Audio-Dateiname blockiert: {relOrFull}");

            string ext = Path.GetExtension(leaf);
            if (string.IsNullOrWhiteSpace(ext) || !AudioExts.Contains(ext))
                throw new UnauthorizedAccessException($"Nicht erlaubte Audio-Dateiendung: '{ext}'.");

            string soundsRootFull = Path.GetFullPath(SoundsRoot);
            string soundsPrefix = NormalizeRootPrefix(soundsRootFull);
            string? projDirFull = !string.IsNullOrWhiteSpace(projectDir) ? Path.GetFullPath(projectDir) : null;
            string? projPrefix = projDirFull != null ? NormalizeRootPrefix(projDirFull) : null;

            string candidate;
            string matchedRoot;

            if (Path.IsPathRooted(trimmed))
            {
                candidate = Path.GetFullPath(trimmed);
                if (candidate.StartsWith(soundsPrefix, StringComparison.OrdinalIgnoreCase))
                {
                    matchedRoot = soundsRootFull;
                }
                else if (projPrefix != null && projDirFull != null && candidate.StartsWith(projPrefix, StringComparison.OrdinalIgnoreCase))
                {
                    matchedRoot = projDirFull;
                }
                else
                {
                    throw new UnauthorizedAccessException("Audio-Pfad liegt ausserhalb von SoundsRoot und Projektordner.");
                }
            }
            else
            {
                string candSounds = Path.GetFullPath(Path.Combine(soundsRootFull, trimmed));
                if (!candSounds.StartsWith(soundsPrefix, StringComparison.OrdinalIgnoreCase))
                    throw new UnauthorizedAccessException("Audio-Pfad verlaesst SoundsRoot.");

                string? candProj = null;
                if (projDirFull != null && projPrefix != null)
                {
                    candProj = Path.GetFullPath(Path.Combine(projDirFull, trimmed));
                    if (!candProj.StartsWith(projPrefix, StringComparison.OrdinalIgnoreCase))
                        throw new UnauthorizedAccessException("Audio-Pfad verlaesst den Projektordner.");
                }

                if (File.Exists(candSounds))
                {
                    candidate = candSounds;
                    matchedRoot = soundsRootFull;
                }
                else if (candProj != null && projDirFull != null && File.Exists(candProj))
                {
                    candidate = candProj;
                    matchedRoot = projDirFull;
                }
                else
                {
                    candidate = candSounds;
                    matchedRoot = soundsRootFull;
                }
            }

            EnsureNoReparsePointsInChain(candidate, matchedRoot);

            if (requireExists && !File.Exists(candidate))
                return null;

            return candidate;
        }

        // =====================================================================
        // TRUSTED ABSOLUTE FFMPEG / FFPROBE DISCOVERY & SAFE SUBPROCESS RUNNER
        // =====================================================================
        public static string FindTrustedFfmpegExe() => FindTrustedMediaTool("ffmpeg.exe");
        public static string FindTrustedFfprobeExe() => FindTrustedMediaTool("ffprobe.exe");

        private static string FindTrustedMediaTool(string exeName)
        {
            if (TrustedToolCache.TryGetValue(exeName, out string? cached) && File.Exists(cached))
                return cached;

            var candidates = new List<string>();

            // 1. Bundled inside Funkenwerk Craft or sibling Funkenwerk Collection folders
            string baseDir = AppDomain.CurrentDomain.BaseDirectory;
            candidates.Add(Path.Combine(baseDir, exeName));
            candidates.Add(Path.Combine(baseDir, "ffmpeg", exeName));
            candidates.Add(Path.Combine(ProductionWorkspaceRoot, "engine", "ffmpeg", exeName));

            string desktopCol = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory),
                "Funkenwerk Collection");
            candidates.Add(Path.Combine(desktopCol, "Funkenwerk Video", "ffmpeg", exeName));

            // 2. Dynamic WinGet Packages discovery (real binary directory, no CWD probing)
            string wingetPkgs = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "Microsoft", "WinGet", "Packages");
            if (Directory.Exists(wingetPkgs))
            {
                try
                {
                    foreach (string pkgDir in Directory.GetDirectories(wingetPkgs, "*FFmpeg*", SearchOption.TopDirectoryOnly))
                    {
                        foreach (string found in Directory.GetFiles(pkgDir, exeName, SearchOption.AllDirectories))
                        {
                            candidates.Add(found);
                        }
                    }
                }
                catch { }
            }

            // 3. Standard system locations
            string progFiles = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles);
            if (!string.IsNullOrWhiteSpace(progFiles))
                candidates.Add(Path.Combine(progFiles, "FFmpeg", "bin", exeName));
            candidates.Add(Path.Combine(@"C:\ffmpeg\bin", exeName));

            foreach (string c in candidates)
            {
                if (!string.IsNullOrWhiteSpace(c) && Path.IsPathRooted(c) && File.Exists(c))
                {
                    string full = Path.GetFullPath(c);
                    var fi = new FileInfo(full);
                    if (!fi.Attributes.HasFlag(FileAttributes.ReparsePoint))
                    {
                        TrustedToolCache[exeName] = full;
                        return full;
                    }
                }
            }

            // 4. Rooted PATH entries (strictly skipping empty, '.', or relative entries to prevent CWD hijacking)
            string? pathEnv = Environment.GetEnvironmentVariable("PATH");
            if (!string.IsNullOrWhiteSpace(pathEnv))
            {
                foreach (string rawEntry in pathEnv.Split(';', StringSplitOptions.RemoveEmptyEntries))
                {
                    string dir = rawEntry.Trim().Trim('"');
                    if (string.IsNullOrWhiteSpace(dir) || dir == "." || dir == ".." || !Path.IsPathRooted(dir))
                        continue;

                    try
                    {
                        string full = Path.GetFullPath(Path.Combine(dir, exeName));
                        if (File.Exists(full))
                        {
                            TrustedToolCache[exeName] = full;
                            return full;
                        }
                    }
                    catch { }
                }
            }

            throw new FileNotFoundException($"Vertrauenswuerdige Mediendatei '{exeName}' wurde auf dem System nicht gefunden.");
        }

        private static async Task<(int ExitCode, string StdOut, string StdErr)> RunProcessBoundedAsync(
            string executablePath,
            IReadOnlyList<string> args,
            int timeoutMs,
            string? workingDirectory = null,
            IDictionary<string, string>? environmentOverrides = null,
            CancellationToken ct = default)
        {
            if (string.IsNullOrWhiteSpace(executablePath) || !Path.IsPathRooted(executablePath) || !File.Exists(executablePath))
                throw new FileNotFoundException($"Ausfuehrbare Datei nicht gefunden oder nicht absolut: {executablePath}");

            var psi = new ProcessStartInfo
            {
                FileName = executablePath,
                WorkingDirectory = !string.IsNullOrWhiteSpace(workingDirectory) ? workingDirectory! : Path.GetDirectoryName(executablePath)!,
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true,
                StandardOutputEncoding = Encoding.UTF8,
                StandardErrorEncoding = Encoding.UTF8
            };

            if (environmentOverrides != null)
            {
                foreach (var kv in environmentOverrides)
                    psi.Environment[kv.Key] = kv.Value;
            }

            foreach (string arg in args)
                psi.ArgumentList.Add(arg);

            using var proc = Process.Start(psi) ?? throw new InvalidOperationException($"Prozess konnte nicht gestartet werden: {Path.GetFileName(executablePath)}");
            using var timeoutCts = new CancellationTokenSource(TimeSpan.FromMilliseconds(Math.Max(1000, timeoutMs)));
            using var linkedCts = CancellationTokenSource.CreateLinkedTokenSource(ct, timeoutCts.Token);

            Task<string> outTask = proc.StandardOutput.ReadToEndAsync();
            Task<string> errTask = proc.StandardError.ReadToEndAsync();

            try
            {
                await proc.WaitForExitAsync(linkedCts.Token);
                await Task.WhenAll(outTask, errTask);
            }
            catch (OperationCanceledException)
            {
                try { proc.Kill(entireProcessTree: true); } catch { }
                if (ct.IsCancellationRequested)
                    throw;
                throw new TimeoutException($"Prozess '{Path.GetFileName(executablePath)}' hat das Zeitlimit ({timeoutMs} ms) ueberschritten.");
            }

            return (proc.ExitCode, await outTask, await errTask);
        }

        public static CraftSettingsModel LoadSettings()
        {
            try
            {
                if (File.Exists(SettingsFilePath))
                {
                    var s = JsonSerializer.Deserialize<CraftSettingsModel>(File.ReadAllText(SettingsFilePath), JsonOpts);
                    if (s != null) return s;
                }
            }
            catch { }
            return new CraftSettingsModel();
        }

        public static void SaveSettings(CraftSettingsModel settings)
        {
            try
            {
                File.WriteAllText(SettingsFilePath, JsonSerializer.Serialize(settings, JsonOpts), new UTF8Encoding(false));
            }
            catch { }
        }

        public static string GetProjectDirectory(string projectName)
        {
            string safe = SanitizeProjectName(projectName);
            string videosRootFull = Path.GetFullPath(VideosRoot);
            string full = Path.GetFullPath(Path.Combine(videosRootFull, safe));
            string rootPrefix = NormalizeRootPrefix(videosRootFull);
            if (!full.StartsWith(rootPrefix, StringComparison.OrdinalIgnoreCase))
                throw new UnauthorizedAccessException("Projektpfad liegt ausserhalb des Videos-Arbeitsbereichs.");

            EnsureNoReparsePointsInChain(full, videosRootFull);
            return EnsureDir(full);
        }

        public static string GetProjectJsonPath(string projectName) =>
            Path.Combine(GetProjectDirectory(projectName), "craft_project.json");

        public static List<string> ListProjectNames()
        {
            string vRoot = VideosRoot;
            if (Directory.GetDirectories(vRoot).Length == 0)
            {
                EnsureDir(Path.Combine(vRoot, "Projekt 1"));
            }

            var names = Directory.GetDirectories(vRoot)
                .Where(d =>
                {
                    try
                    {
                        var di = new DirectoryInfo(d);
                        return !di.Attributes.HasFlag(FileAttributes.ReparsePoint) && di.LinkTarget == null;
                    }
                    catch { return false; }
                })
                .Select(Path.GetFileName)
                .Where(n => !string.IsNullOrWhiteSpace(n))
                .Select(n => n!)
                .ToList();

            names.Sort(CompareNaturalStrings);
            return names;
        }

        public static List<string> ListProjectMediaFiles(string projectName)
        {
            string dir = GetProjectDirectory(projectName);
            var results = new List<string>();

            foreach (string f in Directory.GetFiles(dir))
            {
                var fi = new FileInfo(f);
                if (fi.Attributes.HasFlag(FileAttributes.ReparsePoint) || fi.LinkTarget != null)
                    continue;
                string ext = fi.Extension;
                string fn = fi.Name;
                if (fn.StartsWith("Social_Media_Ready_", StringComparison.OrdinalIgnoreCase) ||
                    fn.StartsWith("_temp_", StringComparison.OrdinalIgnoreCase))
                    continue;
                if (VideoExts.Contains(ext) || ImageExts.Contains(ext))
                    results.Add(fi.FullName);
            }

            string rawSub = Path.Combine(dir, "Rohmaterial");
            if (Directory.Exists(rawSub))
            {
                var rawDi = new DirectoryInfo(rawSub);
                if (!rawDi.Attributes.HasFlag(FileAttributes.ReparsePoint) && rawDi.LinkTarget == null)
                {
                    foreach (string f in Directory.GetFiles(rawSub))
                    {
                        var fi = new FileInfo(f);
                        if (fi.Attributes.HasFlag(FileAttributes.ReparsePoint) || fi.LinkTarget != null)
                            continue;
                        string ext = fi.Extension;
                        if (VideoExts.Contains(ext) || ImageExts.Contains(ext))
                            results.Add(fi.FullName);
                    }
                }
            }

            results.Sort((x, y) => CompareNaturalStrings(Path.GetFileName(x), Path.GetFileName(y)));
            return results;
        }

        public static CraftProjectModel LoadOrCreateProject(string projectName)
        {
            string safe = SanitizeProjectName(projectName);
            string jsonPath = GetProjectJsonPath(safe);
            if (File.Exists(jsonPath))
            {
                try
                {
                    EnsureNoReparsePointsInChain(jsonPath, GetProjectDirectory(safe));
                    var loaded = JsonSerializer.Deserialize<CraftProjectModel>(File.ReadAllText(jsonPath), JsonOpts);
                    if (loaded != null)
                    {
                        loaded.Name = safe;
                        return loaded;
                    }
                }
                catch { }
            }

            var model = new CraftProjectModel
            {
                Name = safe,
                OutputFileName = $"Social_Media_Ready_{safe.Replace(' ', '_')}.mp4"
            };

            foreach (string mediaPath in ListProjectMediaFiles(safe))
            {
                string relName = GetProjectRelativeMediaName(safe, mediaPath);
                string ext = Path.GetExtension(mediaPath);
                bool isImg = ImageExts.Contains(ext);
                model.Scenes.Add(new CraftSceneItem
                {
                    FileName = relName,
                    MediaType = isImg ? "image" : "video",
                    StartSec = 0,
                    DurationSec = isImg ? 2.6 : 2.2,
                    FitMode = "fill",
                    SpeedMode = isImg ? "linear" : "speed_ramp",
                    SlowMoFactor = 0.42,
                    Transition = "whip_zoom"
                });
            }

            SaveProject(model);
            return model;
        }

        public static string GetProjectRelativeMediaName(string projectName, string fullMediaPath)
        {
            string projDir = NormalizeRootPrefix(GetProjectDirectory(projectName));
            string full = Path.GetFullPath(fullMediaPath);
            if (full.StartsWith(projDir, StringComparison.OrdinalIgnoreCase))
                return full.Substring(projDir.Length);
            return Path.GetFileName(full);
        }

        public static string ResolveSceneMediaPath(string projectName, string sceneFileName)
        {
            if (string.IsNullOrWhiteSpace(sceneFileName))
                throw new UnauthorizedAccessException("Szenen-Dateiname darf nicht leer sein.");

            string trimmed = sceneFileName.Trim();
            if (Path.IsPathRooted(trimmed) ||
                HasAlternateDataStreamOrUnc(trimmed) ||
                ContainsTraversalSegment(trimmed))
            {
                throw new UnauthorizedAccessException("Ungueltiger oder externer Szenen-Dateipfad blockiert.");
            }

            string leaf = Path.GetFileName(trimmed);
            if (string.IsNullOrWhiteSpace(leaf) || IsReservedWindowsDeviceName(leaf))
                throw new UnauthorizedAccessException($"Ungueltiger Szenen-Dateiname blockiert: {sceneFileName}");

            string ext = Path.GetExtension(leaf);
            if (string.IsNullOrWhiteSpace(ext) || (!VideoExts.Contains(ext) && !ImageExts.Contains(ext)))
                throw new UnauthorizedAccessException($"Nicht erlaubte Szenen-Dateiendung: '{ext}'.");

            string projDir = GetProjectDirectory(projectName);
            string prefix = NormalizeRootPrefix(projDir);
            string direct = Path.GetFullPath(Path.Combine(projDir, trimmed));
            if (!direct.StartsWith(prefix, StringComparison.OrdinalIgnoreCase))
                throw new UnauthorizedAccessException("Szenen-Dateipfad liegt ausserhalb des Projektordners.");

            EnsureNoReparsePointsInChain(direct, projDir);
            if (File.Exists(direct)) return direct;

            if (string.Equals(trimmed, leaf, StringComparison.Ordinal))
            {
                string inRaw = Path.GetFullPath(Path.Combine(projDir, "Rohmaterial", leaf));
                if (inRaw.StartsWith(prefix, StringComparison.OrdinalIgnoreCase))
                {
                    EnsureNoReparsePointsInChain(inRaw, projDir);
                    if (File.Exists(inRaw)) return inRaw;
                }
            }

            return direct;
        }

        public static string ImportMediaToProject(string projectName, string sourceFilePath)
        {
            if (string.IsNullOrWhiteSpace(sourceFilePath))
                throw new ArgumentException("Quelldateipfad darf nicht leer sein.");

            string trimmed = sourceFilePath.Trim();
            if (HasAlternateDataStreamOrUnc(trimmed) || ContainsTraversalSegment(trimmed))
                throw new UnauthorizedAccessException($"Unsicherer Import-Dateipfad blockiert: {sourceFilePath}");

            string leaf = Path.GetFileName(trimmed);
            if (string.IsNullOrWhiteSpace(leaf) || IsReservedWindowsDeviceName(leaf))
                throw new UnauthorizedAccessException($"Ungueltiger Import-Dateiname blockiert: {leaf}");

            string ext = Path.GetExtension(leaf);
            if (string.IsNullOrWhiteSpace(ext) || (!VideoExts.Contains(ext) && !ImageExts.Contains(ext)))
                throw new UnauthorizedAccessException($"Nicht erlaubter Dateityp beim Medien-Import: '{ext}'.");

            string fullSrc = Path.GetFullPath(trimmed);
            if (!File.Exists(fullSrc))
                throw new FileNotFoundException($"Mediendatei nicht gefunden: {fullSrc}");

            EnsureNoReparsePointsInChain(fullSrc);

            string projDir = GetProjectDirectory(projectName);
            string prefix = NormalizeRootPrefix(projDir);
            string dest = Path.GetFullPath(Path.Combine(projDir, leaf));
            if (!dest.StartsWith(prefix, StringComparison.OrdinalIgnoreCase))
                throw new UnauthorizedAccessException("Zielpfad liegt ausserhalb des Projektordners.");

            EnsureNoReparsePointsInChain(dest, projDir);

            if (!string.Equals(fullSrc, dest, StringComparison.OrdinalIgnoreCase))
            {
                File.Copy(fullSrc, dest, overwrite: true);
            }

            return dest;
        }

        public static void SaveProject(CraftProjectModel project)
        {
            project.Name = SanitizeProjectName(project.Name);
            string projDir = GetProjectDirectory(project.Name);

            project.BrandHeaderText = SanitizeCleanText(project.BrandHeaderText);
            project.TransitionDurationSec = Math.Clamp(project.TransitionDurationSec, 0.08, 1.20);
            project.MusicVolume = Math.Clamp(project.MusicVolume, 0.0, 1.5);
            project.SfxVolume = Math.Clamp(project.SfxVolume, 0.0, 1.5);

            if (!string.IsNullOrWhiteSpace(project.OutputFileName))
            {
                string rawOut = project.OutputFileName.Trim();
                if (HasAlternateDataStreamOrUnc(rawOut) ||
                    ContainsTraversalSegment(rawOut) ||
                    rawOut.IndexOfAny(new[] { '\\', '/' }) >= 0 ||
                    IsReservedWindowsDeviceName(rawOut))
                {
                    throw new UnauthorizedAccessException("Ungueltiger OutputFileName blockiert.");
                }
                if (!rawOut.EndsWith(".mp4", StringComparison.OrdinalIgnoreCase))
                    rawOut += ".mp4";
                project.OutputFileName = rawOut;
            }

            // Enforce safe audio containment + allowlist across ALL project-level audio fields
            _ = ResolveSafeAudioPath(project.BackgroundMusic, projDir, requireExists: false);
            _ = ResolveSafeAudioPath(project.IntroAccentSfx, projDir, requireExists: false);
            _ = ResolveSafeAudioPath(project.OutroAccentSfx, projDir, requireExists: false);

            foreach (var s in project.Scenes)
            {
                s.OverlayBadge = SanitizeCleanText(s.OverlayBadge);
                s.OverlayTitle = SanitizeCleanText(s.OverlayTitle);
                s.OverlaySubtitle = SanitizeCleanText(s.OverlaySubtitle);
                s.DurationSec = Math.Clamp(s.DurationSec, 0.8, 60.0);
                s.StartSec = Math.Max(0.0, s.StartSec);
                s.SlowMoFactor = Math.Clamp(s.SlowMoFactor <= 0 ? 0.42 : s.SlowMoFactor, 0.25, 0.90);
                s.OriginalAudioVolume = Math.Clamp(s.OriginalAudioVolume, 0.0, 1.5);
                s.TransitionDurSec = s.TransitionDurSec <= 0.02 ? 0.0 : Math.Clamp(s.TransitionDurSec, 0.08, 1.40);

                bool isBrandOutro = string.Equals(s.MediaType, "brand_outro", StringComparison.OrdinalIgnoreCase)
                    || string.Equals(s.FileName, "__BRAND_OUTRO__", StringComparison.OrdinalIgnoreCase);

                if (isBrandOutro)
                {
                    s.MediaType = "brand_outro";
                    if (string.IsNullOrWhiteSpace(s.FileName)) s.FileName = "__BRAND_OUTRO__";
                    if (string.IsNullOrWhiteSpace(s.OverlayTitle)) s.OverlayTitle = "Mein Seelenfunke";
                }
                else if (!string.IsNullOrWhiteSpace(s.FileName))
                {
                    _ = ResolveSceneMediaPath(project.Name, s.FileName);
                }

                // Enforce safe audio containment + allowlist across ALL scene-level audio fields
                _ = ResolveSafeAudioPath(s.TransitionSfx, projDir, requireExists: false);
                _ = ResolveSafeAudioPath(s.RiserSfx, projDir, requireExists: false);
                _ = ResolveSafeAudioPath(s.ImpactSfx, projDir, requireExists: false);

                if (string.IsNullOrWhiteSpace(s.FitMode))
                    s.FitMode = "fill";
                if (string.IsNullOrWhiteSpace(s.SpeedMode))
                    s.SpeedMode = (s.MediaType == "image" || s.MediaType == "brand_outro") ? "linear" : "speed_ramp";
                if (string.IsNullOrWhiteSpace(s.Transition))
                    s.Transition = "whip_zoom";
            }

            project.UpdatedAtUtc = DateTime.UtcNow;
            string jsonPath = GetProjectJsonPath(project.Name);
            File.WriteAllText(jsonPath, JsonSerializer.Serialize(project, JsonOpts), new UTF8Encoding(false));
        }

        public static List<CraftSoundItem> ListSoundPool(string? categoryFilter = null)
        {
            var list = new List<CraftSoundItem>();
            string soundsRootFull = SoundsRoot;
            string licFile = Path.Combine(soundsRootFull, "LIZENZEN_GEWERBLICHE_NUTZUNG.json");
            var metaMap = new Dictionary<string, (double dur, string lic, string src)>(StringComparer.OrdinalIgnoreCase);

            if (File.Exists(licFile))
            {
                try
                {
                    using var doc = JsonDocument.Parse(File.ReadAllText(licFile));
                    if (doc.RootElement.TryGetProperty("assets", out var arr) && arr.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var el in arr.EnumerateArray())
                        {
                            string rel = el.TryGetProperty("relativePath", out var rp) ? (rp.GetString() ?? "") : "";
                            double dur = el.TryGetProperty("durationSec", out var d) && d.TryGetDouble(out double dv) ? dv : 0;
                            string lic = el.TryGetProperty("license", out var l) ? (l.GetString() ?? "CC0-1.0") : "CC0-1.0";
                            string src = el.TryGetProperty("source", out var s) ? (s.GetString() ?? "") : "";
                            if (!string.IsNullOrEmpty(rel))
                                metaMap[rel.Replace('/', '\\')] = (dur, lic, src);
                        }
                    }
                }
                catch { }
            }

            if (!Directory.Exists(soundsRootFull)) return list;

            foreach (string subDir in Directory.GetDirectories(soundsRootFull))
            {
                var subDi = new DirectoryInfo(subDir);
                if (subDi.Attributes.HasFlag(FileAttributes.ReparsePoint) || subDi.LinkTarget != null)
                    continue;

                string cat = subDi.Name;
                if (!string.IsNullOrWhiteSpace(categoryFilter) &&
                    !string.Equals(categoryFilter, "all", StringComparison.OrdinalIgnoreCase) &&
                    !cat.Contains(categoryFilter, StringComparison.OrdinalIgnoreCase))
                {
                    continue;
                }

                foreach (string f in Directory.GetFiles(subDir))
                {
                    var fi = new FileInfo(f);
                    if (fi.Attributes.HasFlag(FileAttributes.ReparsePoint) || fi.LinkTarget != null)
                        continue;
                    if (!AudioExts.Contains(fi.Extension)) continue;
                    string rel = $"{cat}\\{fi.Name}";
                    metaMap.TryGetValue(rel, out var m);
                    list.Add(new CraftSoundItem
                    {
                        FileName = fi.Name,
                        Category = cat,
                        RelativePath = rel,
                        FullPath = fi.FullName,
                        DurationSec = m.dur,
                        License = string.IsNullOrEmpty(m.lic) ? "CC0-1.0" : m.lic,
                        CommercialUseAllowed = true,
                        Source = m.src
                    });
                }
            }

            return list.OrderBy(x => x.Category).ThenBy(x => x.FileName).ToList();
        }

        public static async Task<CraftMediaInfo> ProbeMediaFileAsync(string filePath)
        {
            if (string.IsNullOrWhiteSpace(filePath))
                throw new ArgumentException("Mediendateipfad darf nicht leer sein.");

            string trimmed = filePath.Trim();
            if (HasAlternateDataStreamOrUnc(trimmed) || ContainsTraversalSegment(trimmed))
                throw new UnauthorizedAccessException($"Unsicherer Mediendateipfad blockiert: {filePath}");

            string leaf = Path.GetFileName(trimmed);
            if (string.IsNullOrWhiteSpace(leaf) || IsReservedWindowsDeviceName(leaf))
                throw new UnauthorizedAccessException($"Ungueltiger Mediendateiname blockiert: {leaf}");

            string extCheck = Path.GetExtension(leaf).ToLowerInvariant();
            if (!VideoExts.Contains(extCheck) && !ImageExts.Contains(extCheck))
                throw new UnauthorizedAccessException($"Dateityp wird fuer Medien-Inspektion nicht unterstuetzt: '{extCheck}'.");

            string fullPath = Path.GetFullPath(trimmed);
            if (!File.Exists(fullPath))
                throw new FileNotFoundException($"Mediendatei nicht gefunden: {fullPath}");

            EnsureNoReparsePointsInChain(fullPath);

            var fi = new FileInfo(fullPath);
            bool isImg = ImageExts.Contains(extCheck);

            var info = new CraftMediaInfo
            {
                FileName = fi.Name,
                FullPath = fi.FullName,
                MediaType = isImg ? "image" : "video",
                SizeMb = Math.Round(fi.Length / 1048576.0, 2)
            };

            if (isImg)
            {
                using var img = Image.FromFile(fi.FullName);
                info.Width = img.Width;
                info.Height = img.Height;
                if (img.PropertyIdList.Contains(0x0112))
                {
                    int orient = img.GetPropertyItem(0x0112)!.Value![0];
                    if (orient == 6) info.Rotation = 90;
                    else if (orient == 8) info.Rotation = -90;
                    else if (orient == 3) info.Rotation = 180;
                }
                info.DurationSec = 3.0;
                info.Fps = 60;
                return info;
            }

            string ffprobeExe = FindTrustedFfprobeExe();
            var args = new[]
            {
                "-v", "quiet",
                "-print_format", "json",
                "-show_format",
                "-show_streams",
                fi.FullName
            };

            var (exitCode, json, stdErr) = await RunProcessBoundedAsync(ffprobeExe, args, timeoutMs: 20000);
            if (exitCode != 0 || string.IsNullOrWhiteSpace(json))
                throw new InvalidOperationException($"ffprobe Analyse fehlgeschlagen (Exit {exitCode}): {stdErr}");

            using var doc = JsonDocument.Parse(json);
            if (doc.RootElement.TryGetProperty("format", out var fmt) &&
                fmt.TryGetProperty("duration", out var durProp) &&
                double.TryParse(durProp.GetString(), NumberStyles.Float, CultureInfo.InvariantCulture, out double dSec))
            {
                info.DurationSec = Math.Round(dSec, 3);
            }

            if (doc.RootElement.TryGetProperty("streams", out var streams) && streams.ValueKind == JsonValueKind.Array)
            {
                foreach (var st in streams.EnumerateArray())
                {
                    string ctype = st.TryGetProperty("codec_type", out var ct) ? (ct.GetString() ?? "") : "";
                    if (ctype == "video" && info.Width == 0)
                    {
                        info.Width = st.TryGetProperty("width", out var wp) ? wp.GetInt32() : 0;
                        info.Height = st.TryGetProperty("height", out var hp) ? hp.GetInt32() : 0;
                        if (st.TryGetProperty("r_frame_rate", out var rfr))
                        {
                            string rate = rfr.GetString() ?? "30/1";
                            string[] parts = rate.Split('/');
                            if (parts.Length == 2 &&
                                double.TryParse(parts[0], NumberStyles.Float, CultureInfo.InvariantCulture, out double num) &&
                                double.TryParse(parts[1], NumberStyles.Float, CultureInfo.InvariantCulture, out double den) && den > 0)
                            {
                                info.Fps = Math.Round(num / den, 2);
                            }
                        }
                        if (st.TryGetProperty("side_data_list", out var sdl) && sdl.ValueKind == JsonValueKind.Array)
                        {
                            foreach (var sd in sdl.EnumerateArray())
                            {
                                if (sd.TryGetProperty("rotation", out var rotP) && rotP.TryGetInt32(out int rv))
                                    info.Rotation = rv;
                            }
                        }
                    }
                    else if (ctype == "audio")
                    {
                        info.HasAudio = true;
                    }
                }
            }

            return info;
        }

        public static (double PeakSec, double DurationSec) GetAudioPeakEnvelopeInfo(string audioPath, string? projectDir = null)
        {
            string? safeAudio = ResolveSafeAudioPath(audioPath, projectDir, requireExists: true);
            if (string.IsNullOrWhiteSpace(safeAudio))
                return (0.08, 1.5);

            if (AudioPeakCache.TryGetValue(safeAudio, out var cached))
                return cached;

            try
            {
                string ffmpegExe = FindTrustedFfmpegExe();
                var psi = new ProcessStartInfo
                {
                    FileName = ffmpegExe,
                    WorkingDirectory = Path.GetDirectoryName(ffmpegExe)!,
                    UseShellExecute = false,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    CreateNoWindow = true
                };
                psi.ArgumentList.Add("-nostdin");
                psi.ArgumentList.Add("-v");
                psi.ArgumentList.Add("error");
                psi.ArgumentList.Add("-t");
                psi.ArgumentList.Add("90");
                psi.ArgumentList.Add("-i");
                psi.ArgumentList.Add(safeAudio);
                psi.ArgumentList.Add("-f");
                psi.ArgumentList.Add("s16le");
                psi.ArgumentList.Add("-ac");
                psi.ArgumentList.Add("1");
                psi.ArgumentList.Add("-ar");
                psi.ArgumentList.Add("1000");
                psi.ArgumentList.Add("-");

                using var proc = Process.Start(psi);
                if (proc != null)
                {
                    byte[] pcm = Task.Run(async () =>
                    {
                        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(8));
                        Task<string> errDrain = proc.StandardError.ReadToEndAsync();
                        using var ms = new MemoryStream(capacity: 65536);
                        byte[] chunk = new byte[8192];
                        const int maxBytes = 200_000; // 90s * 1000Hz * 2 bytes = 180,000 bytes max
                        try
                        {
                            while (ms.Length < maxBytes)
                            {
                                int read = await proc.StandardOutput.BaseStream.ReadAsync(
                                    chunk.AsMemory(0, Math.Min(chunk.Length, maxBytes - (int)ms.Length)),
                                    cts.Token);
                                if (read <= 0) break;
                                ms.Write(chunk, 0, read);
                            }
                            if (ms.Length >= maxBytes && !proc.HasExited)
                            {
                                try { proc.Kill(entireProcessTree: true); } catch { }
                            }
                            else
                            {
                                await proc.WaitForExitAsync(cts.Token);
                            }
                            _ = await errDrain;
                        }
                        catch (OperationCanceledException)
                        {
                            try { proc.Kill(entireProcessTree: true); } catch { }
                        }
                        return ms.ToArray();
                    }).GetAwaiter().GetResult();

                    int sampleCount = pcm.Length / 2;
                    if (sampleCount > 10)
                    {
                        int bestIdx = 0;
                        int bestAmp = 0;
                        for (int i = 0; i < sampleCount; i++)
                        {
                            short s = BitConverter.ToInt16(pcm, i * 2);
                            int a = Math.Abs((int)s);
                            if (a > bestAmp)
                            {
                                bestAmp = a;
                                bestIdx = i;
                            }
                        }
                        var res = (bestIdx / 1000.0, sampleCount / 1000.0);
                        AudioPeakCache[safeAudio] = res;
                        return res;
                    }
                }
            }
            catch { }

            var fallback = (0.08, 1.5);
            AudioPeakCache[safeAudio] = fallback;
            return fallback;
        }

        // =====================================================================
        // STORYTOLD ARTCRAFT / FILMCRAFT / SOUNDCRAFT ENGINE INTEGRATION
        // =====================================================================
        private static void EnsureSecureBridgeDirectory()
        {
            var dirInfo = new DirectoryInfo(BridgeDirectoryPath);
            if (!dirInfo.Exists)
                dirInfo.Create();

            EnsureNoReparsePointsInChain(dirInfo.FullName, LocalDataDir);

            var userSid = WindowsIdentity.GetCurrent().User;
            if (userSid != null)
            {
                var dsec = new DirectorySecurity();
                dsec.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
                dsec.AddAccessRule(new FileSystemAccessRule(
                    userSid,
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));
                dirInfo.SetAccessControl(dsec);
            }
        }

        private static void ApplyCurrentUserFileAcl(string filePath)
        {
            var userSid = WindowsIdentity.GetCurrent().User;
            if (userSid == null || !File.Exists(filePath)) return;

            var fi = new FileInfo(filePath);
            var fsec = new FileSecurity();
            fsec.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
            fsec.AddAccessRule(new FileSystemAccessRule(
                userSid,
                FileSystemRights.FullControl,
                InheritanceFlags.None,
                PropagationFlags.None,
                AccessControlType.Allow));
            fi.SetAccessControl(fsec);
        }

        public static (int Port, string Token) GetOrCreateFilmCraftBridgeCredentials()
        {
            var settings = LoadSettings();
            int port = settings.FilmCraftControlPort > 1024 ? settings.FilmCraftControlPort : 9876;

            EnsureSecureBridgeDirectory();

            try
            {
                if (File.Exists(BridgeStateFilePath))
                {
                    EnsureNoReparsePointsInChain(BridgeStateFilePath, BridgeDirectoryPath);
                    using var doc = JsonDocument.Parse(File.ReadAllText(BridgeStateFilePath, Encoding.UTF8));
                    int savedPort = doc.RootElement.TryGetProperty("port", out var pp) ? pp.GetInt32() : port;
                    if (doc.RootElement.TryGetProperty("encryptedToken", out var encProp))
                    {
                        string? encB64 = encProp.GetString();
                        if (!string.IsNullOrWhiteSpace(encB64))
                        {
                            byte[] cipher = Convert.FromBase64String(encB64);
                            byte[] plain = ProtectedData.Unprotect(cipher, BridgeDpapiEntropy, DataProtectionScope.CurrentUser);
                            string tok = Encoding.UTF8.GetString(plain);
                            if (tok.Length == 64 && tok.All(Uri.IsHexDigit))
                                return (savedPort, tok);
                        }
                    }
                }
            }
            catch { }

            string newToken = Convert.ToHexString(RandomNumberGenerator.GetBytes(32)).ToLowerInvariant();
            byte[] cipherBytes = ProtectedData.Protect(
                Encoding.UTF8.GetBytes(newToken),
                BridgeDpapiEntropy,
                DataProtectionScope.CurrentUser);

            string stateJson = JsonSerializer.Serialize(new
            {
                port,
                encryptedToken = Convert.ToBase64String(cipherBytes),
                protection = "DPAPI-CurrentUser",
                updatedUtc = DateTime.UtcNow
            }, JsonOpts);

            File.WriteAllText(BridgeStateFilePath, stateJson, new UTF8Encoding(false));
            ApplyCurrentUserFileAcl(BridgeStateFilePath);
            return (port, newToken);
        }

        public static string? ValidateLaunchEngineTargetPath(string? targetPath)
        {
            if (string.IsNullOrWhiteSpace(targetPath))
                return null;

            string trimmed = targetPath.Trim();
            if (HasAlternateDataStreamOrUnc(trimmed) || ContainsTraversalSegment(trimmed))
                throw new UnauthorizedAccessException("Unsicherer Engine-Projektpfad blockiert.");

            string leaf = Path.GetFileName(trimmed);
            if (string.IsNullOrWhiteSpace(leaf) || IsReservedWindowsDeviceName(leaf))
                throw new UnauthorizedAccessException("Ungueltiger Projekt-Dateiname fuer Engine-Start.");

            string ext = Path.GetExtension(leaf);
            if (!string.Equals(ext, ".fcproj", StringComparison.OrdinalIgnoreCase) &&
                !string.Equals(ext, ".ecproj", StringComparison.OrdinalIgnoreCase))
            {
                throw new UnauthorizedAccessException("Nur .fcproj oder .ecproj Projektdateien sind beim Engine-Start erlaubt.");
            }

            string wsRootFull = Path.GetFullPath(WorkspaceRoot);
            string wsPrefix = NormalizeRootPrefix(wsRootFull);
            string fullTarget = Path.IsPathRooted(trimmed)
                ? Path.GetFullPath(trimmed)
                : Path.GetFullPath(Path.Combine(wsRootFull, trimmed));

            if (!fullTarget.StartsWith(wsPrefix, StringComparison.OrdinalIgnoreCase))
                throw new UnauthorizedAccessException("Ziel-Projektdatei liegt ausserhalb des Craft-Arbeitsbereichs.");

            EnsureNoReparsePointsInChain(fullTarget, wsRootFull);

            if (!File.Exists(fullTarget))
                throw new FileNotFoundException($"Projektdatei nicht gefunden: {fullTarget}");

            return fullTarget;
        }

        public static object LaunchStorytoldEngine(
            string engineKey,
            string? targetPath = null,
            bool enableControlBridge = true,
            bool validateOnly = false)
        {
            string norm = engineKey.Trim().ToLowerInvariant();
            string exePath = norm switch
            {
                "artcraft" => ArtCraftExe,
                "filmcraft" => FilmCraftExe,
                "soundcraft" => SoundCraftExe,
                _ => throw new ArgumentException($"Unbekannte Storytold-Engine '{engineKey}'. Erlaubt: artcraft, filmcraft, soundcraft.")
            };

            if (!File.Exists(exePath))
                throw new FileNotFoundException($"Engine-Binary nicht gefunden: {exePath}");

            EnsureNoReparsePointsInChain(exePath);
            string? validatedTarget = ValidateLaunchEngineTargetPath(targetPath);

            var psi = new ProcessStartInfo
            {
                FileName = exePath,
                WorkingDirectory = Path.GetDirectoryName(exePath)!,
                UseShellExecute = false
            };

            int? activePort = null;
            if (norm == "filmcraft" && enableControlBridge)
            {
                var (port, token) = GetOrCreateFilmCraftBridgeCredentials();
                psi.Environment["FILMCRAFT_CONTROL_TOKEN"] = token;
                psi.Environment["FILMCRAFT_CONTROL_PORT"] = port.ToString(CultureInfo.InvariantCulture);
                psi.ArgumentList.Add("--control");
                psi.ArgumentList.Add(port.ToString(CultureInfo.InvariantCulture));
                activePort = port;
            }
            else if (norm == "soundcraft" && enableControlBridge)
            {
                var (_, token) = GetOrCreateFilmCraftBridgeCredentials();
                int scPort = 9877;
                psi.Environment["SOUNDCRAFT_CONTROL_TOKEN"] = token;
                psi.ArgumentList.Add("--control");
                psi.ArgumentList.Add(scPort.ToString(CultureInfo.InvariantCulture));
                activePort = scPort;
            }

            if (!string.IsNullOrWhiteSpace(validatedTarget))
            {
                psi.ArgumentList.Add(validatedTarget);
            }

            if (validateOnly)
            {
                return new
                {
                    ok = true,
                    launched = false,
                    validated = true,
                    engine = norm,
                    executable = exePath,
                    pid = 0,
                    controlPort = activePort,
                    targetPath = validatedTarget ?? ""
                };
            }

            var proc = Process.Start(psi);
            return new
            {
                ok = true,
                launched = true,
                engine = norm,
                executable = exePath,
                pid = proc?.Id ?? 0,
                controlPort = activePort,
                targetPath = validatedTarget ?? ""
            };
        }

        private static List<string> ValidateAndNormalizeFilmCraftCliArgs(IEnumerable<string> arguments)
        {
            var raw = arguments?.Where(a => !string.IsNullOrWhiteSpace(a)).Select(a => a.Trim()).ToList()
                ?? new List<string>();
            if (raw.Count == 0)
                raw.Add("commands");

            if (raw.Count > 8)
                throw new UnauthorizedAccessException("Zu viele filmcraft-cli Argumente.");

            var normalized = new List<string>();
            int idx = 0;

            while (idx < raw.Count && (raw[idx] == "--keep-going" || raw[idx] == "--compact"))
            {
                normalized.Add(raw[idx]);
                idx++;
            }

            if (idx >= raw.Count)
                throw new UnauthorizedAccessException("Kein erlaubtes filmcraft-cli Subkommando angegeben.");

            string sub = raw[idx];
            if (!AllowedFilmCraftSubcommands.Contains(sub))
                throw new UnauthorizedAccessException($"filmcraft-cli Subkommando '{sub}' ist aus Sicherheitsgruenden nicht freigegeben.");

            normalized.Add(sub);
            idx++;

            string wsRootFull = Path.GetFullPath(WorkspaceRoot);
            string wsPrefix = NormalizeRootPrefix(wsRootFull);
            string soundsRootFull = Path.GetFullPath(SoundsRoot);
            string soundsPrefix = NormalizeRootPrefix(soundsRootFull);

            if (string.Equals(sub, "commands", StringComparison.OrdinalIgnoreCase))
            {
                while (idx < raw.Count)
                {
                    string opt = raw[idx++];
                    if (opt == "--json" || opt == "--compact")
                    {
                        normalized.Add(opt);
                    }
                    else if (!opt.StartsWith("-", StringComparison.Ordinal) && opt.Length <= 64 && opt.All(c => char.IsLetterOrDigit(c) || c == '.' || c == '_'))
                    {
                        normalized.Add(opt);
                    }
                    else
                    {
                        throw new UnauthorizedAccessException($"Ungueltiger Filter fuer filmcraft-cli commands: {opt}");
                    }
                }
            }
            else if (string.Equals(sub, "describe", StringComparison.OrdinalIgnoreCase))
            {
                if (idx >= raw.Count || idx + 1 < raw.Count)
                    throw new UnauthorizedAccessException("describe erwartet genau eine Befehls-ID.");
                string cmdId = raw[idx];
                if (cmdId.Length > 80 || !cmdId.All(c => char.IsLetterOrDigit(c) || c == '.' || c == '_'))
                    throw new UnauthorizedAccessException("Ungueltige Befehls-ID fuer filmcraft-cli describe.");
                normalized.Add(cmdId);
            }
            else if (string.Equals(sub, "inspect", StringComparison.OrdinalIgnoreCase))
            {
                if (idx < raw.Count)
                {
                    string target = raw[idx++];
                    if (target != "project" && target != "sequence")
                        throw new UnauthorizedAccessException("inspect erlaubt nur 'project' oder 'sequence'.");
                    normalized.Add(target);
                }
                if (idx < raw.Count)
                    throw new UnauthorizedAccessException("Unerwartete Zusatzargumente fuer filmcraft-cli inspect.");
            }
            else if (string.Equals(sub, "help", StringComparison.OrdinalIgnoreCase) ||
                     string.Equals(sub, "--help", StringComparison.OrdinalIgnoreCase) ||
                     string.Equals(sub, "--version", StringComparison.OrdinalIgnoreCase))
            {
                if (idx < raw.Count)
                    throw new UnauthorizedAccessException($"Keine Zusatzargumente fuer '{sub}' erlaubt.");
            }
            else if (string.Equals(sub, "probe", StringComparison.OrdinalIgnoreCase))
            {
                if (idx >= raw.Count || idx + 1 < raw.Count)
                    throw new UnauthorizedAccessException("probe erwartet genau einen Medienpfad im Arbeitsbereich.");
                string mediaArg = raw[idx];
                if (HasAlternateDataStreamOrUnc(mediaArg) || ContainsTraversalSegment(mediaArg))
                    throw new UnauthorizedAccessException("Unsicherer Medienpfad fuer filmcraft-cli probe.");
                string fullMedia = Path.IsPathRooted(mediaArg)
                    ? Path.GetFullPath(mediaArg)
                    : Path.GetFullPath(Path.Combine(wsRootFull, mediaArg));
                if (!fullMedia.StartsWith(wsPrefix, StringComparison.OrdinalIgnoreCase) &&
                    !fullMedia.StartsWith(soundsPrefix, StringComparison.OrdinalIgnoreCase))
                {
                    throw new UnauthorizedAccessException("probe-Pfad liegt ausserhalb des Craft-Arbeitsbereichs.");
                }
                string ext = Path.GetExtension(fullMedia);
                if (!VideoExts.Contains(ext) && !ImageExts.Contains(ext) && !AudioExts.Contains(ext))
                    throw new UnauthorizedAccessException("Nicht erlaubte Dateiendung fuer filmcraft-cli probe.");
                EnsureNoReparsePointsInChain(fullMedia);
                if (!File.Exists(fullMedia))
                    throw new FileNotFoundException($"Mediendatei fuer probe nicht gefunden: {fullMedia}");
                normalized.Add(fullMedia);
            }
            else if (string.Equals(sub, "run", StringComparison.OrdinalIgnoreCase))
            {
                if (idx >= raw.Count || idx + 1 < raw.Count)
                    throw new UnauthorizedAccessException("run erwartet genau eine .jsonl-Skriptdatei im Arbeitsbereich.");
                string scriptArg = raw[idx];
                if (scriptArg == "-" || HasAlternateDataStreamOrUnc(scriptArg) || ContainsTraversalSegment(scriptArg))
                    throw new UnauthorizedAccessException("Unsicherer Skriptpfad fuer filmcraft-cli run blockiert.");
                string fullScript = Path.IsPathRooted(scriptArg)
                    ? Path.GetFullPath(scriptArg)
                    : Path.GetFullPath(Path.Combine(wsRootFull, scriptArg));
                if (!fullScript.StartsWith(wsPrefix, StringComparison.OrdinalIgnoreCase))
                    throw new UnauthorizedAccessException("filmcraft-cli Skriptdatei muss innerhalb von WorkspaceRoot liegen.");
                if (!string.Equals(Path.GetExtension(fullScript), ".jsonl", StringComparison.OrdinalIgnoreCase))
                    throw new UnauthorizedAccessException("Nur .jsonl-Skriptdateien sind fuer filmcraft-cli run erlaubt.");
                EnsureNoReparsePointsInChain(fullScript, wsRootFull);
                if (!File.Exists(fullScript))
                    throw new FileNotFoundException($"JSONL-Skript nicht gefunden: {fullScript}");
                var sfi = new FileInfo(fullScript);
                if (sfi.Length > 1048576)
                    throw new InvalidDataException("JSONL-Skriptdatei ueberschreitet 1 MB.");

                ValidateJsonlWorkspaceContainment(fullScript, wsRootFull, wsPrefix, soundsPrefix);
                normalized.Add(fullScript);
            }

            return normalized;
        }

        private static void ValidateJsonlWorkspaceContainment(string scriptPath, string wsRootFull, string wsPrefix, string soundsPrefix)
        {
            foreach (string rawLine in File.ReadLines(scriptPath, Encoding.UTF8))
            {
                string line = rawLine.Trim();
                if (string.IsNullOrEmpty(line) || line.StartsWith("#", StringComparison.Ordinal))
                    continue;

                using var doc = JsonDocument.Parse(line);
                if (!doc.RootElement.TryGetProperty("id", out var idProp))
                    throw new UnauthorizedAccessException("JSONL-Zeile ohne Befehls-ID blockiert.");

                string cmdId = idProp.GetString() ?? "";
                if (cmdId.StartsWith("file.export", StringComparison.OrdinalIgnoreCase))
                    throw new UnauthorizedAccessException($"Export-Befehl '{cmdId}' in JSONL blockiert.");

                if (doc.RootElement.TryGetProperty("params", out var pObj) && pObj.ValueKind == JsonValueKind.Object)
                {
                    if (pObj.TryGetProperty("path", out var singlePath) && singlePath.ValueKind == JsonValueKind.String)
                    {
                        ValidateJsonlReferencedPath(singlePath.GetString() ?? "", wsRootFull, wsPrefix, soundsPrefix, mustStayInWorkspace: true);
                    }
                    if (pObj.TryGetProperty("paths", out var multiPaths) && multiPaths.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var el in multiPaths.EnumerateArray())
                        {
                            if (el.ValueKind == JsonValueKind.String)
                                ValidateJsonlReferencedPath(el.GetString() ?? "", wsRootFull, wsPrefix, soundsPrefix, mustStayInWorkspace: false);
                        }
                    }
                }
            }
        }

        private static void ValidateJsonlReferencedPath(string p, string wsRootFull, string wsPrefix, string soundsPrefix, bool mustStayInWorkspace)
        {
            if (string.IsNullOrWhiteSpace(p) || HasAlternateDataStreamOrUnc(p) || ContainsTraversalSegment(p))
                throw new UnauthorizedAccessException("Unsicherer Dateipfad in FilmCraft-JSONL blockiert.");
            string full = Path.GetFullPath(p);
            bool inWs = full.StartsWith(wsPrefix, StringComparison.OrdinalIgnoreCase);
            bool inSounds = !mustStayInWorkspace && full.StartsWith(soundsPrefix, StringComparison.OrdinalIgnoreCase);
            if (!inWs && !inSounds)
                throw new UnauthorizedAccessException("FilmCraft-JSONL Pfad liegt ausserhalb des erlaubten Arbeitsbereichs.");
            EnsureNoReparsePointsInChain(full, inWs ? wsRootFull : null);
        }

        public static async Task<string> RunFilmCraftCliAsync(IEnumerable<string> arguments, int timeoutSec = 90)
        {
            if (!File.Exists(FilmCraftCliExe))
                throw new FileNotFoundException($"filmcraft-cli.exe nicht gefunden: {FilmCraftCliExe}");

            EnsureNoReparsePointsInChain(FilmCraftCliExe);
            List<string> safeArgs = ValidateAndNormalizeFilmCraftCliArgs(arguments);

            var (_, token) = GetOrCreateFilmCraftBridgeCredentials();
            var env = new Dictionary<string, string>
            {
                ["FILMCRAFT_CONTROL_TOKEN"] = token
            };

            var (exitCode, stdout, stderr) = await RunProcessBoundedAsync(
                FilmCraftCliExe,
                safeArgs,
                timeoutMs: Math.Clamp(timeoutSec, 5, 300) * 1000,
                workingDirectory: Path.GetDirectoryName(FilmCraftCliExe),
                environmentOverrides: env);

            if (exitCode != 0 && string.IsNullOrWhiteSpace(stdout))
                throw new InvalidOperationException($"filmcraft-cli Fehler (Exit {exitCode}): {stderr.Trim()}");

            return string.IsNullOrWhiteSpace(stdout) ? stderr.Trim() : stdout.Trim();
        }

        public static async Task<string?> ReadBoundedLineAsync(Stream stream, int maxBytes = MaxIpcLineBytes, CancellationToken ct = default)
        {
            if (maxBytes <= 0)
                throw new ArgumentOutOfRangeException(nameof(maxBytes));

            using var ms = new MemoryStream(capacity: Math.Min(4096, maxBytes));
            byte[] oneByte = new byte[1];
            bool anyRead = false;

            while (true)
            {
                int n = await stream.ReadAsync(oneByte.AsMemory(0, 1), ct);
                if (n == 0)
                    break;

                anyRead = true;
                byte b = oneByte[0];
                if (b == (byte)'\n')
                    break;

                if (ms.Length >= maxBytes)
                    throw new InvalidDataException($"IPC-Zeile ueberschreitet das Sicherheitslimit von {maxBytes} Bytes.");

                ms.WriteByte(b);
            }

            if (!anyRead && ms.Length == 0)
                return null;

            byte[] buf = ms.ToArray();
            int len = buf.Length;
            if (len > 0 && buf[len - 1] == (byte)'\r')
                len--;

            return Encoding.UTF8.GetString(buf, 0, len);
        }

        public static async Task<string> SendFilmCraftBridgeCommandAsync(string method, JsonNode? parameters = null)
        {
            if (string.IsNullOrWhiteSpace(method) || method.Length > 96 || !method.All(c => char.IsLetterOrDigit(c) || c == '.' || c == '_'))
                throw new UnauthorizedAccessException("Ungueltige FilmCraft-Bridge-Methode.");

            var (port, token) = GetOrCreateFilmCraftBridgeCredentials();
            using var tcp = new TcpClient();
            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(10));
            await tcp.ConnectAsync("127.0.0.1", port, cts.Token);
            await using var stream = tcp.GetStream();

            var req = new JsonObject
            {
                ["token"] = token,
                ["method"] = method,
                ["params"] = parameters?.DeepClone() ?? new JsonObject()
            };
            byte[] payload = Encoding.UTF8.GetBytes(req.ToJsonString() + "\n");
            await stream.WriteAsync(payload, cts.Token);
            await stream.FlushAsync(cts.Token);

            string? line = await ReadBoundedLineAsync(stream, MaxIpcLineBytes, cts.Token);
            return line ?? "{}";
        }

        public static async Task<string> ExportFilmCraftNativeProjectAsync(CraftProjectModel project)
        {
            string projDir = GetProjectDirectory(project.Name);
            var (targetW, targetH) = ResolveDimensions(project.AspectRatio);
            string fcprojPath = Path.Combine(projDir, $"{project.Name.Replace(' ', '_')}.fcproj");
            string scriptPath = Path.Combine(projDir, "filmcraft_build.jsonl");

            var lines = new List<string>
            {
                JsonSerializer.Serialize(new
                {
                    id = "file.newSequence",
                    @params = new
                    {
                        name = project.Name,
                        width = targetW,
                        height = targetH,
                        fps = project.Fps,
                        sampleRate = 48000,
                        video = 2,
                        audio = 4
                    }
                })
            };

            // Collect and import all resolved scene media files + background music into FilmCraft project bin
            var importPaths = new List<string>();
            var resolvedSceneFiles = new List<(CraftSceneItem Scene, string? FullMediaPath, bool IsBrandOutro)>();

            foreach (var scene in project.Scenes)
            {
                bool isOutro = string.Equals(scene.MediaType, "brand_outro", StringComparison.OrdinalIgnoreCase)
                    || string.Equals(scene.FileName, "__BRAND_OUTRO__", StringComparison.OrdinalIgnoreCase);

                if (isOutro)
                {
                    resolvedSceneFiles.Add((scene, null, true));
                }
                else if (!string.IsNullOrWhiteSpace(scene.FileName))
                {
                    string mediaFull = ResolveSceneMediaPath(project.Name, scene.FileName);
                    if (File.Exists(mediaFull))
                    {
                        if (!importPaths.Contains(mediaFull, StringComparer.OrdinalIgnoreCase))
                            importPaths.Add(mediaFull);
                        resolvedSceneFiles.Add((scene, mediaFull, false));
                    }
                    else
                    {
                        resolvedSceneFiles.Add((scene, null, false));
                    }
                }
            }

            string? resolvedMusic = ResolveSafeAudioPath(project.BackgroundMusic, projDir, requireExists: true);
            if (!string.IsNullOrWhiteSpace(resolvedMusic) && !importPaths.Contains(resolvedMusic, StringComparer.OrdinalIgnoreCase))
            {
                importPaths.Add(resolvedMusic);
            }

            if (importPaths.Count > 0)
            {
                lines.Add(JsonSerializer.Serialize(new
                {
                    id = "file.import",
                    @params = new
                    {
                        paths = importPaths
                    }
                }));
            }

            // Place timeline clips sequentially with playhead synchronization and titles
            double timelineCursorSec = 0.0;
            for (int i = 0; i < resolvedSceneFiles.Count; i++)
            {
                var (scene, fullMediaPath, isOutro) = resolvedSceneFiles[i];
                double dur = Math.Clamp(scene.DurationSec, 0.8, 60.0);

                lines.Add(JsonSerializer.Serialize(new
                {
                    id = "playhead.set",
                    @params = new { seconds = Math.Round(timelineCursorSec, 3) }
                }));

                if (isOutro)
                {
                    lines.Add(JsonSerializer.Serialize(new
                    {
                        id = "file.newColorMatte",
                        @params = new
                        {
                            color = "#040508",
                            seconds = dur
                        }
                    }));
                }
                else if (!string.IsNullOrWhiteSpace(fullMediaPath))
                {
                    lines.Add(JsonSerializer.Serialize(new
                    {
                        id = "edit.pasteMedia",
                        @params = new
                        {
                            paths = new[] { fullMediaPath },
                            place = true
                        }
                    }));
                }

                if (!string.IsNullOrWhiteSpace(scene.OverlayTitle))
                {
                    lines.Add(JsonSerializer.Serialize(new
                    {
                        id = "playhead.set",
                        @params = new { seconds = Math.Round(timelineCursorSec, 3) }
                    }));

                    lines.Add(JsonSerializer.Serialize(new
                    {
                        id = "graphics.newText",
                        @params = new
                        {
                            text = SanitizeCleanText(scene.OverlayTitle),
                            size = 56,
                            seconds = dur,
                            position = new[] { targetW / 2, (int)(targetH * 0.77) },
                            newClip = true
                        }
                    }));
                }

                double cutOverlap = (i < resolvedSceneFiles.Count - 1)
                    ? Math.Clamp(scene.TransitionDurSec > 0.04 ? scene.TransitionDurSec : project.TransitionDurationSec, 0.08, Math.Min(1.2, dur * 0.45))
                    : 0.0;
                timelineCursorSec += Math.Max(0.25, dur - cutOverlap);
            }

            if (!string.IsNullOrWhiteSpace(resolvedMusic))
            {
                lines.Add(JsonSerializer.Serialize(new
                {
                    id = "playhead.set",
                    @params = new { seconds = 0.0 }
                }));
                lines.Add(JsonSerializer.Serialize(new
                {
                    id = "edit.pasteMedia",
                    @params = new
                    {
                        paths = new[] { resolvedMusic },
                        place = true
                    }
                }));
            }

            lines.Add(JsonSerializer.Serialize(new
            {
                id = "file.saveAs",
                @params = new { path = fcprojPath }
            }));

            await File.WriteAllLinesAsync(scriptPath, lines, new UTF8Encoding(false));

            if (File.Exists(FilmCraftCliExe))
            {
                try
                {
                    await RunFilmCraftCliAsync(new[] { "--keep-going", "run", scriptPath }, timeoutSec: 45);
                }
                catch { }
            }

            project.LastFilmCraftProjectPath = File.Exists(fcprojPath) ? fcprojPath : scriptPath;
            SaveProject(project);
            return project.LastFilmCraftProjectPath;
        }

        // =====================================================================
        // CINEMA VIRAL VIDEO RENDER PIPELINE (SPEED-RAMP + OPTICAL SLOW-MO + LUXURY)
        // =====================================================================
        private static readonly object FontLock = new();
        private static PrivateFontCollection? PlayfairBoldPfc;
        private static PrivateFontCollection? MontserratSemiBoldPfc;
        private static PrivateFontCollection? MontserratLightPfc;

        private static Font CreateBrandFont(string role, float sizePx, FontStyle fallbackStyle = FontStyle.Regular)
        {
            lock (FontLock)
            {
                try
                {
                    if (PlayfairBoldPfc == null)
                    {
                        PlayfairBoldPfc = new PrivateFontCollection();
                        string pf700 = Path.Combine(FontsRoot, "playfair-display-v40-latin-700.ttf");
                        if (File.Exists(pf700)) PlayfairBoldPfc.AddFontFile(pf700);
                    }
                    if (MontserratSemiBoldPfc == null)
                    {
                        MontserratSemiBoldPfc = new PrivateFontCollection();
                        string ms600 = Path.Combine(FontsRoot, "montserrat-v31-latin-600.ttf");
                        if (File.Exists(ms600)) MontserratSemiBoldPfc.AddFontFile(ms600);
                    }
                    if (MontserratLightPfc == null)
                    {
                        MontserratLightPfc = new PrivateFontCollection();
                        string ms300 = Path.Combine(FontsRoot, "montserrat-v31-latin-300.ttf");
                        if (File.Exists(ms300)) MontserratLightPfc.AddFontFile(ms300);
                    }

                    PrivateFontCollection? targetPfc = role switch
                    {
                        "playfair_bold" => PlayfairBoldPfc,
                        "montserrat_semibold" => MontserratSemiBoldPfc,
                        "montserrat_light" => MontserratLightPfc,
                        _ => null
                    };

                    if (targetPfc != null && targetPfc.Families.Length > 0)
                    {
                        var fam = targetPfc.Families[0];
                        FontStyle st = fam.IsStyleAvailable(FontStyle.Regular)
                            ? FontStyle.Regular
                            : (fam.IsStyleAvailable(FontStyle.Bold) ? FontStyle.Bold : fallbackStyle);
                        return new Font(fam, sizePx, st, GraphicsUnit.Pixel);
                    }
                }
                catch { }

                string fallbackName = role == "playfair_bold" ? "Georgia" : "Bahnschrift";
                return new Font(fallbackName, sizePx, fallbackStyle, GraphicsUnit.Pixel);
            }
        }

        public static (int Width, int Height) ResolveDimensions(string aspect) => aspect switch
        {
            "1:1" => (1080, 1080),
            "16:9" => (1920, 1080),
            _ => (1080, 1920)
        };

        private static float MeasureTrackedWidth(Graphics g, string text, Font font, float trackingPx)
        {
            if (string.IsNullOrEmpty(text)) return 0f;
            var sf = StringFormat.GenericTypographic;
            sf.FormatFlags |= StringFormatFlags.MeasureTrailingSpaces;
            float total = 0f;
            for (int i = 0; i < text.Length; i++)
            {
                string ch = text[i].ToString();
                SizeF sz = g.MeasureString(ch, font, 2000, sf);
                total += (ch == " " ? font.Size * 0.36f : sz.Width);
                if (i < text.Length - 1) total += trackingPx;
            }
            return total;
        }

        private static void DrawTrackedCenteredString(
            Graphics g,
            string text,
            Font font,
            Color mainColor,
            float centerX,
            float topY,
            float trackingPx,
            bool drawOpticalShadow = true)
        {
            if (string.IsNullOrEmpty(text)) return;
            var sf = StringFormat.GenericTypographic;
            sf.FormatFlags |= StringFormatFlags.MeasureTrailingSpaces;

            float totalW = MeasureTrackedWidth(g, text, font, trackingPx);
            float curX = centerX - (totalW / 2f);

            if (drawOpticalShadow)
            {
                // Multi-pass soft cinema optical shadow so pure white text floats cleanly without boxes
                (float dx, float dy, int alpha)[] shadowPasses =
                [
                    (0f, 6.5f, 42),
                    (0f, 3.5f, 82),
                    (0f, 1.6f, 128)
                ];

                foreach (var (dx, dy, a) in shadowPasses)
                {
                    using var shBrush = new SolidBrush(Color.FromArgb(a, 3, 4, 8));
                    float sx = curX;
                    for (int i = 0; i < text.Length; i++)
                    {
                        string ch = text[i].ToString();
                        SizeF sz = g.MeasureString(ch, font, 2000, sf);
                        if (ch != " ")
                            g.DrawString(ch, font, shBrush, sx + dx, topY + dy, sf);
                        sx += (ch == " " ? font.Size * 0.36f : sz.Width) + trackingPx;
                    }
                }
            }

            using var fgBrush = new SolidBrush(mainColor);
            for (int i = 0; i < text.Length; i++)
            {
                string ch = text[i].ToString();
                SizeF sz = g.MeasureString(ch, font, 2000, sf);
                if (ch != " ")
                    g.DrawString(ch, font, fgBrush, curX, topY, sf);
                curX += (ch == " " ? font.Size * 0.36f : sz.Width) + trackingPx;
            }
        }

        public static float RenderLuxuryOverlayPng(
            string outputPngPath,
            int width,
            int height,
            string overlayStyle,
            bool showBrandHeader,
            string brandHeaderText,
            CraftSceneItem scene)
        {
            using var bmp = new Bitmap(width, height, PixelFormat.Format32bppArgb);
            using var g = Graphics.FromImage(bmp);
            g.Clear(Color.Transparent);
            g.SmoothingMode = SmoothingMode.AntiAlias;
            g.TextRenderingHint = TextRenderingHint.AntiAlias;
            g.CompositingQuality = CompositingQuality.HighQuality;

            // STRICT MONOCHROME PURE WHITE TYPOGRAPHY (Zero gold text, zero colored lines, authentic cinema look)
            Color pureWhite = Color.FromArgb(250, 255, 255, 255);      // #FFFFFF Pure Crisp White
            Color softWhite = Color.FromArgb(190, 255, 255, 255);      // Subtle Pure White for micro-overlines/sublines
            Color headerWhite = Color.FromArgb(158, 255, 255, 255);

            string headerTxt = SanitizeCleanText(brandHeaderText).ToUpperInvariant();
            string badge = SanitizeCleanText(scene.OverlayBadge).ToUpperInvariant();
            string title = SanitizeCleanText(scene.OverlayTitle);
            string sub = SanitizeCleanText(scene.OverlaySubtitle);

            bool useCard = string.Equals(overlayStyle, "card", StringComparison.OrdinalIgnoreCase);
            float resolvedTitlePt = 0f;

            // Subtle cinema depth gradient at bottom (only when text is present so clean B-roll stays 100% pure)
            if (!string.IsNullOrWhiteSpace(title) || !string.IsNullOrWhiteSpace(sub) || !string.IsNullOrWhiteSpace(badge))
            {
                int gradTop = (int)(height * 0.67);
                using var botGrad = new LinearGradientBrush(
                    new Rectangle(0, gradTop, width, height - gradTop),
                    Color.FromArgb(0, 4, 5, 8),
                    Color.FromArgb(156, 4, 5, 8),
                    LinearGradientMode.Vertical);
                g.FillRectangle(botGrad, 0, gradTop, width, height - gradTop);
            }

            if (showBrandHeader && !string.IsNullOrWhiteSpace(headerTxt))
            {
                int topH = (int)(height * 0.13);
                using var topGrad = new LinearGradientBrush(
                    new Rectangle(0, 0, width, topH),
                    Color.FromArgb(105, 4, 5, 8),
                    Color.FromArgb(0, 4, 5, 8),
                    LinearGradientMode.Vertical);
                g.FillRectangle(topGrad, 0, 0, width, topH);

                using var wmFont = CreateBrandFont("montserrat_light", width >= 1080 ? 17f : 13.5f, FontStyle.Regular);
                DrawTrackedCenteredString(g, headerTxt, wmFont, headerWhite, width / 2f, height * 0.066f, 6.2f);
            }

            if (!useCard)
            {
                // PURE WHITE EDITORIAL MINIMALIST TYPOGRAPHY (No boxes, no gold lines, pure commercial cinema style)
                if (!string.IsNullOrWhiteSpace(title) || !string.IsNullOrWhiteSpace(sub) || !string.IsNullOrWhiteSpace(badge))
                {
                    float scale = width / 1080f;
                    string upperTitle = title.ToUpperInvariant();

                    float titlePt = upperTitle.Length > 21 ? 33f * scale : (upperTitle.Length > 15 ? 37f * scale : 41f * scale);
                    float titleTrack = upperTitle.Length > 19 ? 3.4f * scale : 5.2f * scale;
                    float minPt = 24f * scale;
                    float stepPt = 2f * scale;

                    using var overlineFont = CreateBrandFont("montserrat_light", 17.5f * scale, FontStyle.Regular);
                    using var subFont = CreateBrandFont("montserrat_light", 21f * scale, FontStyle.Regular);

                    if (!string.IsNullOrWhiteSpace(upperTitle))
                    {
                        while (titlePt > minPt)
                        {
                            using var testMainFont = CreateBrandFont("montserrat_semibold", titlePt, FontStyle.Bold);
                            if (MeasureTrackedWidth(g, upperTitle, testMainFont, titleTrack) <= width * 0.85f)
                                break;

                            titlePt = Math.Max(minPt, titlePt - stepPt);
                            titleTrack = Math.Max(1.8f * scale, titleTrack - (0.35f * scale));
                        }
                    }

                    resolvedTitlePt = titlePt;
                    using var fittedMainFont = CreateBrandFont("montserrat_semibold", titlePt, FontStyle.Bold);

                    float curY = height * 0.772f;
                    if (!string.IsNullOrWhiteSpace(badge)) curY -= 18f * scale;
                    if (!string.IsNullOrWhiteSpace(sub)) curY -= 15f * scale;

                    if (!string.IsNullOrWhiteSpace(badge))
                    {
                        DrawTrackedCenteredString(g, badge, overlineFont, softWhite, width / 2f, curY, 6.2f * scale);
                        curY += 32f * scale;
                    }

                    if (!string.IsNullOrWhiteSpace(upperTitle))
                    {
                        DrawTrackedCenteredString(g, upperTitle, fittedMainFont, pureWhite, width / 2f, curY, titleTrack);
                        curY += titlePt + (14f * scale);
                    }

                    if (!string.IsNullOrWhiteSpace(sub))
                    {
                        DrawTrackedCenteredString(g, sub, subFont, softWhite, width / 2f, curY, 2.2f * scale);
                    }
                }
            }
            else if (!string.IsNullOrWhiteSpace(title) || !string.IsNullOrWhiteSpace(sub))
            {
                // Minimalist Translucent White-Border Card Mode (optional)
                float maxCardW = width * 0.82f;
                float textPadX = 38f;
                float availTextW = maxCardW - (textPadX * 2f);
                resolvedTitlePt = 36f * (width / 1080f);
                using var titleFont = CreateBrandFont("montserrat_semibold", resolvedTitlePt, FontStyle.Bold);
                var sfWrap = new StringFormat { Alignment = StringAlignment.Center, LineAlignment = StringAlignment.Center };
                SizeF titleSz = g.MeasureString(title, titleFont, (int)availTextW, sfWrap);
                float cardW = Math.Clamp(titleSz.Width + textPadX * 2f, width * 0.45f, maxCardW);
                float cardH = titleSz.Height + 46f;
                float cardX = (width - cardW) / 2f;
                float cardY = (height * 0.77f) - (cardH / 2f);
                using var cardPath = CreateRoundedRect(cardX, cardY, cardW, cardH, 18f);
                using var bgBrush = new SolidBrush(Color.FromArgb(195, 10, 12, 17));
                using var borderPen = new Pen(Color.FromArgb(95, 255, 255, 255), 1.5f);
                g.FillPath(bgBrush, cardPath);
                g.DrawPath(borderPen, cardPath);
                using var tBrush = new SolidBrush(pureWhite);
                g.DrawString(title, titleFont, tBrush, new RectangleF(cardX, cardY, cardW, cardH), sfWrap);
            }

            bmp.Save(outputPngPath, ImageFormat.Png);
            return resolvedTitlePt;
        }

        private static GraphicsPath CreateRoundedRect(float x, float y, float w, float h, float r)
        {
            float d = r * 2f;
            var path = new GraphicsPath();
            path.AddArc(x, y, d, d, 180, 90);
            path.AddArc(x + w - d, y, d, d, 270, 90);
            path.AddArc(x + w - d, y + h - d, d, d, 0, 90);
            path.AddArc(x, y + h - d, d, d, 90, 90);
            path.CloseFigure();
            return path;
        }

        // =====================================================================
        // NATIVE 60 FPS MYSTICAL LUXURY BRAND OUTRO ("MEIN SEELENFUNKE" ABGANG)
        // =====================================================================
        private static double SmoothStep01(double x)
        {
            double u = Math.Clamp(x, 0.0, 1.0);
            return u * u * (3.0 - 2.0 * u);
        }

        private static Bitmap CreateSoftRadialOrbSprite(int size, byte r, byte g, byte b)
        {
            var bmp = new Bitmap(size, size, PixelFormat.Format32bppArgb);
            var rect = new Rectangle(0, 0, size, size);
            var data = bmp.LockBits(rect, ImageLockMode.WriteOnly, PixelFormat.Format32bppArgb);
            int stride = data.Stride;
            byte[] buf = new byte[stride * size];
            float half = (size - 1) * 0.5f;

            for (int y = 0; y < size; y++)
            {
                float dy = (y - half) / half;
                for (int x = 0; x < size; x++)
                {
                    float dx = (x - half) / half;
                    float d2 = dx * dx + dy * dy;
                    if (d2 >= 1.0f) continue;
                    float falloff = MathF.Pow(1.0f - d2, 2.4f);
                    int a = Math.Clamp((int)MathF.Round(falloff * 255f), 0, 255);
                    int idx = y * stride + x * 4;
                    buf[idx + 0] = b;
                    buf[idx + 1] = g;
                    buf[idx + 2] = r;
                    buf[idx + 3] = (byte)a;
                }
            }

            Marshal.Copy(buf, 0, data.Scan0, buf.Length);
            bmp.UnlockBits(data);
            return bmp;
        }

        private static byte[] ExtractBgraBytes(Bitmap bmp, out int stride)
        {
            var rect = new Rectangle(0, 0, bmp.Width, bmp.Height);
            var data = bmp.LockBits(rect, ImageLockMode.ReadOnly, PixelFormat.Format32bppArgb);
            stride = data.Stride;
            byte[] buf = new byte[stride * bmp.Height];
            Marshal.Copy(data.Scan0, buf, 0, buf.Length);
            bmp.UnlockBits(data);
            return buf;
        }

        private static byte[] BlurAlphaChannelSeparable(byte[] srcBgra, int width, int height, int stride, int radius)
        {
            float[] temp = new float[width * height];
            byte[] dst = new byte[stride * height];
            float inv = 1.0f / (2 * radius + 1);

            // 2-pass separable box blur approximating smooth optical Pro-Mist halation
            float[] srcA = new float[width * height];
            for (int y = 0; y < height; y++)
            {
                int row = y * stride;
                for (int x = 0; x < width; x++)
                    srcA[y * width + x] = srcBgra[row + x * 4 + 3];
            }

            for (int pass = 0; pass < 2; pass++)
            {
                // Horizontal
                for (int y = 0; y < height; y++)
                {
                    int yOff = y * width;
                    float sum = 0f;
                    for (int k = -radius; k <= radius; k++)
                        sum += srcA[yOff + Math.Clamp(k, 0, width - 1)];
                    for (int x = 0; x < width; x++)
                    {
                        temp[yOff + x] = sum * inv;
                        int rem = Math.Clamp(x - radius, 0, width - 1);
                        int add = Math.Clamp(x + radius + 1, 0, width - 1);
                        sum += srcA[yOff + add] - srcA[yOff + rem];
                    }
                }

                // Vertical
                for (int x = 0; x < width; x++)
                {
                    float sum = 0f;
                    for (int k = -radius; k <= radius; k++)
                        sum += temp[Math.Clamp(k, 0, height - 1) * width + x];
                    for (int y = 0; y < height; y++)
                    {
                        srcA[y * width + x] = sum * inv;
                        int rem = Math.Clamp(y - radius, 0, height - 1);
                        int add = Math.Clamp(y + radius + 1, 0, height - 1);
                        sum += temp[add * width + x] - temp[rem * width + x];
                    }
                }
            }

            for (int y = 0; y < height; y++)
            {
                int row = y * stride;
                for (int x = 0; x < width; x++)
                {
                    int a = Math.Clamp((int)MathF.Round(srcA[y * width + x]), 0, 255);
                    if (a == 0) continue;
                    int idx = row + x * 4;
                    dst[idx + 0] = 255;
                    dst[idx + 1] = 255;
                    dst[idx + 2] = 255;
                    dst[idx + 3] = (byte)a;
                }
            }
            return dst;
        }

        private static void DrawScaledSpriteAlpha(Graphics g, Bitmap sprite, float cx, float cy, float diameterW, float diameterH, float alpha)
        {
            if (alpha <= 0.003f) return;
            var cm = new ColorMatrix { Matrix33 = Math.Clamp(alpha, 0f, 1f) };
            using var ia = new ImageAttributes();
            ia.SetColorMatrix(cm, ColorMatrixFlag.Default, ColorAdjustType.Bitmap);
            var dest = new RectangleF(cx - diameterW * 0.5f, cy - diameterH * 0.5f, diameterW, diameterH);
            g.DrawImage(
                sprite,
                new PointF[]
                {
                    new(dest.Left, dest.Top),
                    new(dest.Right, dest.Top),
                    new(dest.Left, dest.Bottom)
                },
                new RectangleF(0, 0, sprite.Width, sprite.Height),
                GraphicsUnit.Pixel,
                ia);
        }

        private static async Task RenderMysticBrandOutroSegmentAsync(
            CraftSceneItem scene,
            string outSegMp4,
            double durSec,
            int outW,
            int outH,
            int fps,
            CancellationToken ct)
        {
            int totalFrames = Math.Max(30, (int)Math.Round(durSec * fps));
            string durStr = durSec.ToString("0.###", CultureInfo.InvariantCulture);
            float scaleFactor = outW / 1080f;

            string brandText = string.IsNullOrWhiteSpace(scene.OverlayTitle)
                ? "Mein Seelenfunke"
                : SanitizeCleanText(scene.OverlayTitle);
            string taglineText = string.IsNullOrWhiteSpace(scene.OverlaySubtitle)
                ? "EIN FUNKE, DER BLEIBT"
                : SanitizeCleanText(scene.OverlaySubtitle).ToUpperInvariant();

            // 1. Pre-render supersampled pure-white Playfair Display Bold wordmark + Montserrat Light tagline
            int bandH = Math.Max(260, (int)(440 * scaleFactor));
            byte[] crispBytes;
            byte[] glowBytes;
            byte[] subBytes;
            int bandStride;

            using (var wordBmp = new Bitmap(outW, bandH, PixelFormat.Format32bppArgb))
            using (var subBmp = new Bitmap(outW, bandH, PixelFormat.Format32bppArgb))
            {
                using (var gw = Graphics.FromImage(wordBmp))
                {
                    gw.Clear(Color.Transparent);
                    gw.SmoothingMode = SmoothingMode.HighQuality;
                    gw.TextRenderingHint = TextRenderingHint.AntiAlias;
                    gw.PixelOffsetMode = PixelOffsetMode.HighQuality;

                    float wmPt = 92f * scaleFactor;
                    var sfCenter = new StringFormat(StringFormat.GenericTypographic)
                    {
                        Alignment = StringAlignment.Center,
                        LineAlignment = StringAlignment.Near
                    };

                    while (wmPt > 46f * scaleFactor)
                    {
                        using var testF = CreateBrandFont("playfair_bold", wmPt, FontStyle.Bold);
                        SizeF sz = gw.MeasureString(brandText, testF, outW * 2, sfCenter);
                        if (sz.Width <= outW * 0.80f) break;
                        wmPt -= 2.5f * scaleFactor;
                    }

                    using var playfairFont = CreateBrandFont("playfair_bold", wmPt, FontStyle.Bold);
                    SizeF finalSz = gw.MeasureString(brandText, playfairFont, outW * 2, sfCenter);
                    float wmTopY = (bandH * 0.44f) - (finalSz.Height * 0.52f);

                    using var whiteBrush = new SolidBrush(Color.FromArgb(255, 255, 255, 255));
                    gw.DrawString(brandText, playfairFont, whiteBrush, new PointF(outW * 0.5f, wmTopY), sfCenter);

                    using var gs = Graphics.FromImage(subBmp);
                    gs.Clear(Color.Transparent);
                    gs.SmoothingMode = SmoothingMode.HighQuality;
                    gs.TextRenderingHint = TextRenderingHint.AntiAlias;
                    gs.PixelOffsetMode = PixelOffsetMode.HighQuality;

                    if (!string.IsNullOrWhiteSpace(taglineText))
                    {
                        using var subFont = CreateBrandFont("montserrat_light", 19.5f * scaleFactor, FontStyle.Regular);
                        float subTopY = wmTopY + finalSz.Height + (28f * scaleFactor);
                        DrawTrackedCenteredString(
                            gs,
                            taglineText,
                            subFont,
                            Color.FromArgb(255, 255, 255, 255),
                            outW * 0.5f,
                            subTopY,
                            7.6f * scaleFactor,
                            drawOpticalShadow: false);
                    }
                }

                crispBytes = ExtractBgraBytes(wordBmp, out bandStride);
                glowBytes = BlurAlphaChannelSeparable(crispBytes, outW, bandH, bandStride, Math.Max(6, (int)(15 * scaleFactor)));
                subBytes = ExtractBgraBytes(subBmp, out _);
            }

            // 2. Pre-compute deterministic mystical volumetric mist & silver-white stardust trajectories
            var rng = new Random(202611);
            var motes = Enumerable.Range(0, 38).Select(idx =>
            {
                bool isDeepBokeh = idx < 15;
                return (
                    BaseX: (float)(rng.NextDouble() * outW),
                    BaseY: (float)((0.18 + rng.NextDouble() * 0.66) * outH),
                    SpeedY: (float)((isDeepBokeh ? 14.0 : 24.0) + rng.NextDouble() * 20.0) * scaleFactor,
                    SwayAmp: (float)(8.0 + rng.NextDouble() * 18.0) * scaleFactor,
                    SwayFreq: (float)(0.45 + rng.NextDouble() * 0.85),
                    Phase: (float)(rng.NextDouble() * Math.PI * 2.0),
                    SizePx: (isDeepBokeh ? (float)(12.0 + rng.NextDouble() * 22.0) : (float)(2.4 + rng.NextDouble() * 4.2)) * scaleFactor,
                    MaxAlpha: isDeepBokeh ? (float)(0.045 + rng.NextDouble() * 0.075) : (float)(0.16 + rng.NextDouble() * 0.34)
                );
            }).ToArray();

            // 3. Stream raw BGRA frames directly into trusted FFmpeg segment encoder
            string ffmpegExe = FindTrustedFfmpegExe();
            string mkvPath = Path.ChangeExtension(outSegMp4, ".mkv");
            var psi = new ProcessStartInfo
            {
                FileName = ffmpegExe,
                WorkingDirectory = Path.GetDirectoryName(ffmpegExe)!,
                UseShellExecute = false,
                RedirectStandardInput = true,
                RedirectStandardError = true,
                RedirectStandardOutput = true,
                CreateNoWindow = true
            };
            psi.ArgumentList.Add("-hide_banner");
            psi.ArgumentList.Add("-loglevel");
            psi.ArgumentList.Add("error");
            psi.ArgumentList.Add("-y");
            psi.ArgumentList.Add("-f");
            psi.ArgumentList.Add("rawvideo");
            psi.ArgumentList.Add("-pix_fmt");
            psi.ArgumentList.Add("bgra");
            psi.ArgumentList.Add("-s");
            psi.ArgumentList.Add($"{outW}x{outH}");
            psi.ArgumentList.Add("-r");
            psi.ArgumentList.Add(fps.ToString(CultureInfo.InvariantCulture));
            psi.ArgumentList.Add("-i");
            psi.ArgumentList.Add("-");
            psi.ArgumentList.Add("-f");
            psi.ArgumentList.Add("lavfi");
            psi.ArgumentList.Add("-t");
            psi.ArgumentList.Add(durStr);
            psi.ArgumentList.Add("-i");
            psi.ArgumentList.Add("anullsrc=channel_layout=stereo:sample_rate=48000");
            psi.ArgumentList.Add("-vf");
            psi.ArgumentList.Add("scale=out_color_matrix=bt709:out_range=tv,format=yuv420p");
            psi.ArgumentList.Add("-map");
            psi.ArgumentList.Add("0:v");
            psi.ArgumentList.Add("-map");
            psi.ArgumentList.Add("1:a");
            psi.ArgumentList.Add("-t");
            psi.ArgumentList.Add(durStr);
            psi.ArgumentList.Add("-c:v");
            psi.ArgumentList.Add("libx264");
            psi.ArgumentList.Add("-preset");
            psi.ArgumentList.Add("fast");
            psi.ArgumentList.Add("-crf");
            psi.ArgumentList.Add("14");
            psi.ArgumentList.Add("-aq-mode");
            psi.ArgumentList.Add("3");
            psi.ArgumentList.Add("-aq-strength");
            psi.ArgumentList.Add("1.20");
            psi.ArgumentList.Add("-pix_fmt");
            psi.ArgumentList.Add("yuv420p");
            psi.ArgumentList.Add("-r");
            psi.ArgumentList.Add(fps.ToString(CultureInfo.InvariantCulture));
            psi.ArgumentList.Add("-c:a");
            psi.ArgumentList.Add("pcm_s16le");
            psi.ArgumentList.Add("-f");
            psi.ArgumentList.Add("matroska");
            psi.ArgumentList.Add(mkvPath);

            using var proc = Process.Start(psi) ?? throw new InvalidOperationException("ffmpeg konnte fuer Brand-Outro nicht gestartet werden.");
            using var timeoutCts = new CancellationTokenSource(TimeSpan.FromSeconds(180));
            using var linkedCts = CancellationTokenSource.CreateLinkedTokenSource(ct, timeoutCts.Token);

            Task<string> outTask = proc.StandardOutput.ReadToEndAsync();
            Task<string> errTask = proc.StandardError.ReadToEndAsync();

            int frameByteSize = outW * outH * 4;
            int batchSize = Math.Clamp(Environment.ProcessorCount, 4, 12);
            byte[][] batchBuffers = Enumerable.Range(0, batchSize).Select(_ => new byte[frameByteSize]).ToArray();
            byte[][] fgBuffers = Enumerable.Range(0, batchSize).Select(_ => new byte[frameByteSize]).ToArray();
            byte[][] dynBufSlots = Enumerable.Range(0, batchSize).Select(_ => new byte[bandStride * bandH]).ToArray();
            Bitmap[] sparkSlots = Enumerable.Range(0, batchSize).Select(_ => CreateSoftRadialOrbSprite(64, 255, 255, 255)).ToArray();
            Bitmap[] frameSlots = Enumerable.Range(0, batchSize).Select(_ => new Bitmap(outW, outH, PixelFormat.Format32bppArgb)).ToArray();
            Bitmap[] bandSlots = Enumerable.Range(0, batchSize).Select(_ => new Bitmap(outW, bandH, PixelFormat.Format32bppArgb)).ToArray();

            try
            {
                await using (var stdin = proc.StandardInput.BaseStream)
                {
                    for (int baseFrame = 0; baseFrame < totalFrames; baseFrame += batchSize)
                    {
                        linkedCts.Token.ThrowIfCancellationRequested();
                        int count = Math.Min(batchSize, totalFrames - baseFrame);

                        Parallel.For(0, count, bIdx =>
                        {
                            int f = baseFrame + bIdx;
                            double t = (double)f / fps;
                            double uAll = Math.Clamp(t / durSec, 0.0, 1.0);

                            // Smooth cosine fade-to-black in final 0.65s for seamless Reel loop
                            double endFade = t > (durSec - 0.65)
                                ? 0.5 * (1.0 + Math.Cos(Math.PI * Math.Clamp((t - (durSec - 0.65)) / 0.65, 0.0, 1.0)))
                                : 1.0;

                            Bitmap slotSpark = sparkSlots[bIdx];
                            Bitmap frameBmp = frameSlots[bIdx];
                            Bitmap dynamicBand = bandSlots[bIdx];
                            byte[] dynBuf = dynBufSlots[bIdx];
                            byte[] fgBuf = fgBuffers[bIdx];
                            byte[] outBuf = batchBuffers[bIdx];
                            Array.Clear(dynBuf, 0, dynBuf.Length);

                            double sweepU = SmoothStep01((t - 0.22) / 1.70);
                            float sweepX = (float)(-200.0 * scaleFactor + sweepU * (outW + 460.0 * scaleFactor));
                            float invSheenSigma2 = 1.0f / (2.0f * (92f * scaleFactor) * (92f * scaleFactor));
                            double subReveal = SmoothStep01((t - 0.92) / 1.15);

                            using (var g = Graphics.FromImage(frameBmp))
                            {
                                g.Clear(Color.Transparent);
                                g.SmoothingMode = SmoothingMode.HighQuality;
                                g.InterpolationMode = InterpolationMode.HighQualityBicubic;
                                g.PixelOffsetMode = PixelOffsetMode.HighQuality;

                                double moteIntro = SmoothStep01(t / 0.55);
                                for (int m = 0; m < motes.Length; m++)
                                {
                                    var p = motes[m];
                                    float mx = p.BaseX + MathF.Sin((float)t * p.SwayFreq + p.Phase) * p.SwayAmp;
                                    float my = p.BaseY - (float)t * p.SpeedY;
                                    float twinkle = 0.62f + 0.38f * MathF.Sin((float)t * 2.4f + p.Phase * 1.7f);
                                    float ma = p.MaxAlpha * twinkle * (float)(moteIntro * endFade);
                                    DrawScaledSpriteAlpha(g, slotSpark, mx, my, p.SizePx, p.SizePx, ma);
                                }

                                var bd = dynamicBand.LockBits(new Rectangle(0, 0, outW, bandH), ImageLockMode.WriteOnly, PixelFormat.Format32bppArgb);
                                try
                                {
                                    float halfBandH = bandH * 0.5f;
                                    for (int y = 0; y < bandH; y++)
                                    {
                                        int row = y * bandStride;
                                        float tiltOffset = (y - halfBandH) * 0.26f;
                                        for (int x = 0; x < outW; x++)
                                        {
                                            int idx = row + x * 4;
                                            byte cA = crispBytes[idx + 3];
                                            byte gA = glowBytes[idx + 3];
                                            byte sA = subBytes[idx + 3];
                                            if (cA == 0 && gA == 0 && sA == 0) continue;

                                            float dist = sweepX - (x + tiltOffset);
                                            double vis = SmoothStep01((dist + 135f * scaleFactor) / (230f * scaleFactor));
                                            float sheen = MathF.Exp(-(dist * dist) * invSheenSigma2);

                                            double wmAlpha = cA * vis * (0.95 + 0.05 * sheen)
                                                           + gA * (0.12 * vis + 0.32 * sheen * vis);
                                            double tgAlpha = sA * subReveal * 0.80;

                                            int finalA = Math.Clamp((int)Math.Round(Math.Max(wmAlpha, tgAlpha) * endFade), 0, 255);
                                            if (finalA == 0) continue;

                                            dynBuf[idx + 0] = 255;
                                            dynBuf[idx + 1] = 255;
                                            dynBuf[idx + 2] = 255;
                                            dynBuf[idx + 3] = (byte)finalA;
                                        }
                                    }
                                    Marshal.Copy(dynBuf, 0, bd.Scan0, dynBuf.Length);
                                }
                                finally
                                {
                                    dynamicBand.UnlockBits(bd);
                                }

                                double easePush = 1.0 - Math.Pow(1.0 - uAll, 2.6);
                                float camScale = (float)(0.962 + 0.068 * easePush);
                                float drawW = outW * camScale;
                                float drawH = bandH * camScale;
                                float drawX = (outW - drawW) * 0.5f;
                                float drawY = (outH * 0.485f) - (drawH * 0.48f);

                                g.DrawImage(dynamicBand, new RectangleF(drawX, drawY, drawW, drawH));
                            }

                            var fb = frameBmp.LockBits(new Rectangle(0, 0, outW, outH), ImageLockMode.ReadOnly, PixelFormat.Format32bppArgb);
                            try
                            {
                                if (fb.Stride == outW * 4)
                                {
                                    Marshal.Copy(fb.Scan0, fgBuf, 0, frameByteSize);
                                }
                                else
                                {
                                    for (int r = 0; r < outH; r++)
                                        Marshal.Copy(IntPtr.Add(fb.Scan0, r * fb.Stride), fgBuf, r * outW * 4, outW * 4);
                                }
                            }
                            finally
                            {
                                frameBmp.UnlockBits(fb);
                            }

                            float mistBreath = (float)(0.68 + 0.32 * Math.Sin(t * 1.15)) * (float)endFade;
                            float cx1 = outW * (0.48f + 0.04f * MathF.Sin((float)t * 0.55f));
                            float cy1 = outH * (0.485f - 0.015f * (float)t);
                            float invRx1 = 1.0f / (outW * 0.56f);
                            float invRy1 = 1.0f / (outH * 0.27f);

                            float anamPeak = (float)(Math.Sin(Math.Clamp(sweepU, 0.0, 1.0) * Math.PI) * 0.75 + 0.25 * SmoothStep01(t / 0.8)) * (float)endFade;
                            float anamCx = Math.Clamp(sweepX, outW * 0.18f, outW * 0.82f);
                            float anamCy = outH * 0.478f;
                            float invAnamRx = 1.0f / (outW * 0.44f);
                            float invAnamRy = 1.0f / (outH * 0.048f);

                            float[] gx1 = new float[outW];
                            float[] gAnamX = new float[outW];
                            for (int x = 0; x < outW; x++)
                            {
                                float dx1 = (x - cx1) * invRx1;
                                gx1[x] = MathF.Exp(-0.5f * dx1 * dx1);
                                float dAx = (x - anamCx) * invAnamRx;
                                gAnamX[x] = MathF.Exp(-0.5f * dAx * dAx);
                            }

                            uint frameSeed = (uint)(f * 747796405u + 2891336453u);
                            for (int y = 0; y < outH; y++)
                            {
                                float dy1 = (y - cy1) * invRy1;
                                float gy1 = MathF.Exp(-0.5f * dy1 * dy1) * mistBreath;
                                float dAy = (y - anamCy) * invAnamRy;
                                float gAnamY = MathF.Exp(-0.5f * dAy * dAy) * anamPeak;

                                int rowOff = y * outW * 4;
                                uint rowHash = frameSeed ^ (uint)(y * 3266489917u);

                                for (int x = 0; x < outW; x++)
                                {
                                    int idx = rowOff + x * 4;
                                    float mist = gx1[x] * gy1;
                                    float anam = gAnamX[x] * gAnamY;

                                    float bgR = 3.0f + mist * 16.5f + anam * 14.0f;
                                    float bgG = 4.0f + mist * 17.5f + anam * 14.5f;
                                    float bgB = 6.5f + mist * 20.5f + anam * 16.0f;

                                    byte fgA = fgBuf[idx + 3];
                                    if (fgA > 0)
                                    {
                                        float a = fgA * (1.0f / 255.0f);
                                        float invA = 1.0f - a;
                                        bgR = fgBuf[idx + 2] * a + bgR * invA;
                                        bgG = fgBuf[idx + 1] * a + bgG * invA;
                                        bgB = fgBuf[idx + 0] * a + bgB * invA;
                                    }

                                    uint h = rowHash + (uint)(x * 374761393u);
                                    h = (h ^ (h >> 15)) * 2246822519u;
                                    h = (h ^ (h >> 13)) * 3266489917u;
                                    h ^= (h >> 16);
                                    float u1 = (h & 0xFFFFu) * (1.0f / 65535.0f);
                                    float u2 = ((h >> 16) & 0xFFFFu) * (1.0f / 65535.0f);
                                    float dither = (u1 - u2) * 1.25f;

                                    outBuf[idx + 0] = (byte)Math.Clamp((int)MathF.Round(bgB + dither), 0, 255);
                                    outBuf[idx + 1] = (byte)Math.Clamp((int)MathF.Round(bgG + dither), 0, 255);
                                    outBuf[idx + 2] = (byte)Math.Clamp((int)MathF.Round(bgR + dither), 0, 255);
                                    outBuf[idx + 3] = 255;
                                }
                            }
                        });

                        for (int bIdx = 0; bIdx < count; bIdx++)
                        {
                            await stdin.WriteAsync(batchBuffers[bIdx], 0, frameByteSize, linkedCts.Token);
                        }
                    }
                }

                await proc.WaitForExitAsync(linkedCts.Token);
                await Task.WhenAll(outTask, errTask);
            }
            catch (OperationCanceledException)
            {
                try { proc.Kill(entireProcessTree: true); } catch { }
                if (ct.IsCancellationRequested) throw;
                throw new TimeoutException("Brand-Outro Rendering hat das Zeitlimit ueberschritten.");
            }
            finally
            {
                foreach (var b in sparkSlots) b.Dispose();
                foreach (var b in frameSlots) b.Dispose();
                foreach (var b in bandSlots) b.Dispose();
            }

            string err = await errTask;
            if (proc.ExitCode != 0 || !File.Exists(mkvPath))
                throw new InvalidOperationException($"Brand-Outro Renderfehler (Exit {proc.ExitCode}): {err}");

            File.Move(mkvPath, outSegMp4, overwrite: true);
        }

        public static async Task<string> RenderProjectSocialVideoAsync(
            CraftProjectModel project,
            IProgress<(double Pct, string Step)>? progress = null,
            CancellationToken ct = default)
        {
            if (project.Scenes == null || project.Scenes.Count == 0)
                throw new InvalidOperationException("Das Projekt enthaelt noch keine Szenen zum Rendern.");

            string projDir = GetProjectDirectory(project.Name);
            var (outW, outH) = ResolveDimensions(project.AspectRatio);
            int fps = Math.Clamp(project.Fps, 24, 60);
            double defaultTransDur = project.Scenes.Count > 1 ? Math.Clamp(project.TransitionDurationSec, 0.10, 1.20) : 0.0;

            string tempWorkDir = Path.Combine(Path.GetTempPath(), $"fw_craft_render_{Guid.NewGuid():N}");
            Directory.CreateDirectory(tempWorkDir);

            try
            {
                progress?.Report((5, "Synchronisiere FilmCraft-Projektstruktur..."));
                await ExportFilmCraftNativeProjectAsync(project);

                int sceneCount = project.Scenes.Count;
                var segmentPaths = new string[sceneCount];
                var sceneDurations = new double[sceneCount];
                int completedScenes = 0;
                int maxParallel = Math.Clamp(Environment.ProcessorCount / 2, 2, 4);
                using var sem = new SemaphoreSlim(maxParallel, maxParallel);

                var sceneTasks = Enumerable.Range(0, sceneCount).Select(async i =>
                {
                    await sem.WaitAsync(ct);
                    try
                    {
                        ct.ThrowIfCancellationRequested();
                        var scene = project.Scenes[i];
                        double dur = Math.Clamp(scene.DurationSec, 0.9, 45.0);
                        sceneDurations[i] = dur;
                        string segPath = Path.Combine(tempWorkDir, $"seg_{i:D2}.mp4");

                        bool isBrandOutro = string.Equals(scene.MediaType, "brand_outro", StringComparison.OrdinalIgnoreCase)
                            || string.Equals(scene.FileName, "__BRAND_OUTRO__", StringComparison.OrdinalIgnoreCase);

                        if (isBrandOutro)
                        {
                            await RenderMysticBrandOutroSegmentAsync(scene, segPath, dur, outW, outH, fps, ct);
                        }
                        else
                        {
                            string srcMedia = ResolveSceneMediaPath(project.Name, scene.FileName);
                            if (!File.Exists(srcMedia))
                                throw new FileNotFoundException($"Quelldatei fuer Szene {i + 1} fehlt: {srcMedia}");

                            var mediaInfo = await ProbeMediaFileAsync(srcMedia);
                            bool isImg = mediaInfo.MediaType == "image";
                            double startSec = isImg ? 0.0 : Math.Max(0.0, scene.StartSec);

                            string overlayPng = Path.Combine(tempWorkDir, $"overlay_{i:D2}.png");
                            lock (JsonOpts)
                            {
                                _ = RenderLuxuryOverlayPng(
                                    overlayPng,
                                    outW,
                                    outH,
                                    project.OverlayStyle,
                                    project.ShowBrandHeader,
                                    project.BrandHeaderText,
                                    scene);
                            }

                            bool velvetOutroMelt = (i < sceneCount - 1)
                                && string.Equals(scene.Transition, "velvet_dissolve", StringComparison.OrdinalIgnoreCase)
                                && (scene.TransitionDurSec >= 0.55 || string.Equals(project.Scenes[i + 1].MediaType, "brand_outro", StringComparison.OrdinalIgnoreCase));

                            await RenderSingleNormalizedSegmentAsync(
                                srcMedia,
                                mediaInfo,
                                overlayPng,
                                segPath,
                                isImg,
                                mediaInfo.HasAudio && scene.KeepOriginalAudio,
                                scene.OriginalAudioVolume,
                                startSec,
                                dur,
                                outW,
                                outH,
                                fps,
                                scene.FitMode,
                                scene.SpeedMode,
                                scene.SlowMoFactor,
                                project.ColorGrade,
                                i == sceneCount - 1,
                                velvetOutroMelt,
                                ct);
                        }

                        segmentPaths[i] = segPath;
                        int done = Interlocked.Increment(ref completedScenes);
                        double pct = 10 + ((double)done / sceneCount) * 58.0;
                        progress?.Report((pct, $"Cinema-Szene {done}/{sceneCount} fertiggestellt..."));
                    }
                    finally
                    {
                        sem.Release();
                    }
                }).ToArray();

                await Task.WhenAll(sceneTasks);

                progress?.Report((72, "Komponiere Speed-Ramp Uebergaenge, Peak-SFX & Mastering..."));
                string stagedMp4 = Path.Combine(tempWorkDir, "master_staged.mp4");

                await ComposeMasterTimelineWithAudioAsync(
                    project,
                    segmentPaths.ToList(),
                    sceneDurations.ToList(),
                    defaultTransDur,
                    outW,
                    outH,
                    fps,
                    stagedMp4,
                    ct);

                progress?.Report((94, "Pruefe MP4-Container & FastStart-Atome..."));
                await ValidateMp4PlayabilityAsync(stagedMp4);

                string outName = string.IsNullOrWhiteSpace(project.OutputFileName)
                    ? $"Social_Media_Ready_{project.Name.Replace(' ', '_')}.mp4"
                    : Path.GetFileName(project.OutputFileName);
                if (!outName.EndsWith(".mp4", StringComparison.OrdinalIgnoreCase))
                    outName += ".mp4";

                string finalOutPath = Path.Combine(projDir, outName);
                EnsureNoReparsePointsInChain(finalOutPath, projDir);
                File.Copy(stagedMp4, finalOutPath, overwrite: true);

                project.LastRenderedPath = finalOutPath;
                SaveProject(project);

                progress?.Report((100, "Fertiggestellt"));
                return finalOutPath;
            }
            finally
            {
                try
                {
                    if (Directory.Exists(tempWorkDir))
                        Directory.Delete(tempWorkDir, recursive: true);
                }
                catch { }
            }
        }

        private static string BuildAtempoChain(double speedFactor)
        {
            double clamped = Math.Clamp(speedFactor, 0.12, 8.0);
            var steps = new List<string>();
            while (clamped < 0.5)
            {
                steps.Add("atempo=0.5");
                clamped /= 0.5;
            }
            while (clamped > 2.0)
            {
                steps.Add("atempo=2.0");
                clamped /= 2.0;
            }
            steps.Add($"atempo={clamped.ToString("0.####", CultureInfo.InvariantCulture)}");
            return string.Join(",", steps);
        }

        public static async Task RenderSingleNormalizedSegmentAsync(
            string srcMedia,
            CraftMediaInfo mediaInfo,
            string overlayPng,
            string outSegMp4,
            bool isImage,
            bool keepSourceAudio,
            double sourceAudioVol,
            double startSec,
            double durSec,
            int outW,
            int outH,
            int fps,
            string fitMode,
            string speedMode,
            double slowMoFactor,
            string colorGrade,
            bool isFinalScene,
            bool velvetOutroMelt,
            CancellationToken ct)
        {
            string durStr = durSec.ToString("0.###", CultureInfo.InvariantCulture);
            double slowFactor = Math.Clamp(slowMoFactor <= 0 ? 0.42 : slowMoFactor, 0.25, 0.90);
            string normSpeed = isImage ? "linear" : (speedMode?.Trim().ToLowerInvariant() ?? "speed_ramp");
            bool isContainBlur = string.Equals(fitMode, "contain_blur", StringComparison.OrdinalIgnoreCase);
            bool nativeHighFps = mediaInfo.Fps >= 90.0;

            string gradeChain = string.Equals(colorGrade, "natural", StringComparison.OrdinalIgnoreCase)
                ? "setsar=1"
                : "eq=contrast=1.11:brightness=-0.028:saturation=1.08,colorbalance=rs=0.015:gs=0.0:bs=-0.025:rh=0.055:gh=0.022:bh=-0.042,vignette=PI/5.6,unsharp=5:5:0.52:5:5:0.0,setsar=1";

            double readStartSec = startSec;
            double readDurSec = durSec + 0.35;
            string baseVideoFilter;
            string audioFilter;
            string volStr = Math.Clamp(sourceAudioVol, 0.0, 1.5).ToString("0.##", CultureInfo.InvariantCulture);
            string nullAudioFilter = $"anullsrc=channel_layout=stereo:sample_rate=48000,atrim=0:{durStr},asetpts=PTS-STARTPTS[aout]";

            string scaleCropFilter = isContainBlur
                ? $"split=2[bgsrc][fgsrc];[bgsrc]scale={outW}:{outH}:force_original_aspect_ratio=increase:flags=bilinear,crop={outW}:{outH},boxblur=28:4,eq=brightness=-0.18:saturation=0.82[bg];[fgsrc]scale={outW}:{outH}:force_original_aspect_ratio=decrease:flags=lanczos[fg];[bg][fg]overlay=(W-w)/2:(H-h)/2,setsar=1"
                : $"scale={outW}:{outH}:force_original_aspect_ratio=increase:flags=lanczos,crop={outW}:{outH},setsar=1";

            if (isImage)
            {
                string endDip = isFinalScene
                    ? $",fade=t=out:st={Math.Max(0.1, durSec - 0.28).ToString("0.###", CultureInfo.InvariantCulture)}:d=0.26"
                    : "";

                if (isContainBlur)
                {
                    baseVideoFilter =
                        $"[0:v]{scaleCropFilter},trim=0:{durStr},setpts=PTS-STARTPTS,{gradeChain}{endDip},format=yuv420p[vraw]";
                }
                else
                {
                    int totalFrames = Math.Max(24, (int)Math.Round(durSec * fps));
                    int preW = ((int)(outW * 1.18) / 2) * 2;
                    int preH = ((int)(outH * 1.18) / 2) * 2;
                    baseVideoFilter =
                        $"[0:v]trim=end_frame=1,scale={preW}:{preH}:force_original_aspect_ratio=increase:flags=lanczos,crop={preW}:{preH}," +
                        $"zoompan=z='min(zoom+0.00072,1.12)':d={totalFrames}:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s={outW}x{outH}:fps={fps}," +
                        $"{gradeChain}{endDip},format=yuv420p[vraw]";
                }
                audioFilter = nullAudioFilter;
            }
            else if (normSpeed == "speed_ramp" && durSec >= 1.4)
            {
                double whipOutDur = Math.Min(0.26, durSec * 0.13);
                double slowHeroOutDur = Math.Max(0.60, durSec - 2.0 * whipOutDur);
                double fastMul = 2.40;

                double src1 = whipOutDur * fastMul;
                double src2 = slowHeroOutDur * slowFactor;
                double src3 = (whipOutDur + 0.08) * fastMul;
                double neededSrc = src1 + src2 + src3;

                if (mediaInfo.DurationSec > 0.6)
                {
                    if (readStartSec + neededSrc > mediaInfo.DurationSec)
                        readStartSec = Math.Max(0.0, mediaInfo.DurationSec - neededSrc - 0.05);

                    double avail = Math.Max(0.6, mediaInfo.DurationSec - readStartSec);
                    if (avail < neededSrc)
                    {
                        double ratio = (avail * 0.96) / neededSrc;
                        src1 *= ratio;
                        src2 *= ratio;
                        src3 *= ratio;
                        fastMul = Math.Max(1.15, src1 / whipOutDur);
                        slowFactor = Math.Clamp(src2 / slowHeroOutDur, 0.25, 0.95);
                    }
                }
                readDurSec = src1 + src2 + src3 + 0.20;

                string t1End = src1.ToString("0.###", CultureInfo.InvariantCulture);
                string t2End = (src1 + src2).ToString("0.###", CultureInfo.InvariantCulture);
                string t3End = (src1 + src2 + src3).ToString("0.###", CultureInfo.InvariantCulture);
                string fastStr = fastMul.ToString("0.###", CultureInfo.InvariantCulture);
                string slowStr = slowFactor.ToString("0.###", CultureInfo.InvariantCulture);

                string slowInterp = nativeHighFps
                    ? $"fps={fps}"
                    : $"minterpolate=fps={fps}:mi_mode=mci:mc_mode=obmc:me_mode=bilat:scd=none";

                baseVideoFilter =
                    $"[0:v]{scaleCropFilter},split=3[s1][s2][s3];" +
                    $"[s1]trim=0:{t1End},setpts=(PTS-STARTPTS)/{fastStr},tmix=frames=3:weights='1 2 1',fps={fps}[v1];" +
                    $"[s2]trim={t1End}:{t2End},setpts=(PTS-STARTPTS)/{slowStr},{slowInterp}[v2];" +
                    $"[s3]trim={t2End}:{t3End},setpts=(PTS-STARTPTS)/{fastStr},tmix=frames=3:weights='1 2 1',fps={fps}[v3];" +
                    $"[v1][v2][v3]concat=n=3:v=1:a=0,trim=0:{durStr},setpts=PTS-STARTPTS,{gradeChain},format=yuv420p[vraw]";

                if (keepSourceAudio)
                {
                    string fastAtempo = BuildAtempoChain(fastMul);
                    string slowAtempo = BuildAtempoChain(slowFactor);
                    audioFilter =
                        $"[0:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,asplit=3[as1][as2][as3];" +
                        $"[as1]atrim=0:{t1End},asetpts=PTS-STARTPTS,{fastAtempo}[a1];" +
                        $"[as2]atrim={t1End}:{t2End},asetpts=PTS-STARTPTS,{slowAtempo}[a2];" +
                        $"[as3]atrim={t2End}:{t3End},asetpts=PTS-STARTPTS,{fastAtempo}[a3];" +
                        $"[a1][a2][a3]concat=n=3:v=0:a=1,volume={volStr},apad=whole_dur={durStr},atrim=0:{durStr},asetpts=PTS-STARTPTS[aout]";
                }
                else
                {
                    audioFilter = nullAudioFilter;
                }
            }
            else if (normSpeed == "slow_motion")
            {
                double neededSrc = (durSec + 0.12) * slowFactor;
                if (mediaInfo.DurationSec > 0.6)
                {
                    if (readStartSec + neededSrc > mediaInfo.DurationSec)
                        readStartSec = Math.Max(0.0, mediaInfo.DurationSec - neededSrc - 0.05);
                    double avail = Math.Max(0.5, mediaInfo.DurationSec - readStartSec);
                    if (avail < neededSrc)
                        slowFactor = Math.Clamp((avail * 0.95) / (durSec + 0.08), 0.25, 1.0);
                }
                readDurSec = (durSec + 0.15) * slowFactor + 0.15;
                string slowStr = slowFactor.ToString("0.###", CultureInfo.InvariantCulture);
                string slowInterp = nativeHighFps
                    ? $"fps={fps}"
                    : $"minterpolate=fps={fps}:mi_mode=mci:mc_mode=obmc:me_mode=bilat:scd=none";

                baseVideoFilter =
                    $"[0:v]{scaleCropFilter},setpts=(PTS-STARTPTS)/{slowStr},{slowInterp},trim=0:{durStr},setpts=PTS-STARTPTS,{gradeChain},format=yuv420p[vraw]";

                if (keepSourceAudio)
                {
                    string slowAtempo = BuildAtempoChain(slowFactor);
                    audioFilter =
                        $"[0:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,{slowAtempo},volume={volStr},apad=whole_dur={durStr},atrim=0:{durStr},asetpts=PTS-STARTPTS[aout]";
                }
                else
                {
                    audioFilter = nullAudioFilter;
                }
            }
            else
            {
                if (mediaInfo.DurationSec > 0.6 && readStartSec + durSec > mediaInfo.DurationSec)
                    readStartSec = Math.Max(0.0, mediaInfo.DurationSec - durSec);
                readDurSec = durSec + 0.15;
                baseVideoFilter =
                    $"[0:v]fps={fps},{scaleCropFilter},trim=0:{durStr},setpts=PTS-STARTPTS,{gradeChain},format=yuv420p[vraw]";

                audioFilter = keepSourceAudio
                    ? $"[0:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,volume={volStr},apad=whole_dur={durStr},atrim=0:{durStr},asetpts=PTS-STARTPTS[aout]"
                    : nullAudioFilter;
            }

            string rackFocusFilter;
            if (velvetOutroMelt && durSec >= 1.5)
            {
                string blurSt = Math.Max(0.2, durSec - 0.92).ToString("0.###", CultureInfo.InvariantCulture);
                rackFocusFilter =
                    $"[vraw]split=2[vsharp][vsoftsrc];" +
                    $"[vsoftsrc]gblur=sigma=18,eq=brightness=-0.10,format=rgba,fade=t=in:st={blurSt}:d=0.82:alpha=1[vsoft];" +
                    $"[vsharp][vsoft]overlay=0:0:format=auto,format=yuv420p[vbase]";
            }
            else
            {
                rackFocusFilter = "[vraw]null[vbase]";
            }

            string ovFadeIn = (normSpeed == "speed_ramp" ? 0.20 : 0.12).ToString("0.###", CultureInfo.InvariantCulture);
            string ovFadeOut = Math.Max(0.32, durSec - (velvetOutroMelt ? 0.85 : 0.30)).ToString("0.###", CultureInfo.InvariantCulture);

            string overlayFilter =
                $"[1:v]format=rgba,fade=t=in:st={ovFadeIn}:d=0.26:alpha=1,fade=t=out:st={ovFadeOut}:d=0.24:alpha=1[ov];" +
                $"[vbase][ov]overlay=0:0:format=auto,scale=out_color_matrix=bt709:out_range=tv,format=yuv420p[vout]";

            string fullComplex = $"{baseVideoFilter};{rackFocusFilter};{overlayFilter};{audioFilter}";
            string mkvPath = Path.ChangeExtension(outSegMp4, ".mkv");

            var args = new List<string>
            {
                "-nostdin",
                "-hide_banner",
                "-loglevel", "error",
                "-y"
            };

            if (isImage)
            {
                if (isContainBlur)
                {
                    args.Add("-loop");
                    args.Add("1");
                    args.Add("-framerate");
                    args.Add(fps.ToString(CultureInfo.InvariantCulture));
                    args.Add("-t");
                    args.Add(durStr);
                }
                args.Add("-i");
                args.Add(srcMedia);
            }
            else
            {
                if (readStartSec > 0.01)
                {
                    args.Add("-ss");
                    args.Add(readStartSec.ToString("0.###", CultureInfo.InvariantCulture));
                }
                args.Add("-t");
                args.Add(readDurSec.ToString("0.###", CultureInfo.InvariantCulture));
                args.Add("-i");
                args.Add(srcMedia);
            }

            args.Add("-loop");
            args.Add("1");
            args.Add("-t");
            args.Add(durStr);
            args.Add("-i");
            args.Add(overlayPng);

            args.Add("-filter_complex");
            args.Add(fullComplex);
            args.Add("-map");
            args.Add("[vout]");
            args.Add("-map");
            args.Add("[aout]");
            args.Add("-t");
            args.Add(durStr);
            args.Add("-c:v");
            args.Add("libx264");
            args.Add("-preset");
            args.Add("fast");
            args.Add("-crf");
            args.Add("16");
            args.Add("-pix_fmt");
            args.Add("yuv420p");
            args.Add("-r");
            args.Add(fps.ToString(CultureInfo.InvariantCulture));
            args.Add("-c:a");
            args.Add("pcm_s16le");
            args.Add("-f");
            args.Add("matroska");
            args.Add(mkvPath);

            string ffmpegExe = FindTrustedFfmpegExe();
            var (exitCode, _, err) = await RunProcessBoundedAsync(ffmpegExe, args, timeoutMs: 300_000, ct: ct);
            if (exitCode != 0 || !File.Exists(mkvPath))
                throw new InvalidOperationException($"Szenen-Renderfehler (Exit {exitCode}): {err}");

            File.Move(mkvPath, outSegMp4, overwrite: true);
        }

        private static async Task ComposeMasterTimelineWithAudioAsync(
            CraftProjectModel project,
            List<string> segPaths,
            List<double> durs,
            double defaultTransDur,
            int outW,
            int outH,
            int fps,
            string stagedMp4,
            CancellationToken ct)
        {
            int n = segPaths.Count;
            string projDir = GetProjectDirectory(project.Name);

            var cutTransDurs = new List<double>();
            var cutOffsets = new List<double>();
            double curTimelineLen = durs[0];

            for (int k = 1; k < n; k++)
            {
                double rawTd = project.Scenes[k - 1].TransitionDurSec > 0.04
                    ? project.Scenes[k - 1].TransitionDurSec
                    : defaultTransDur;
                double maxAllowed = Math.Min(durs[k - 1], durs[k]) * 0.46;
                double td = Math.Clamp(rawTd, 0.08, Math.Max(0.10, maxAllowed));
                double offset = Math.Max(0.05, curTimelineLen - td);

                cutTransDurs.Add(td);
                cutOffsets.Add(offset);
                curTimelineLen = offset + durs[k];
            }

            double totalVideoDur = curTimelineLen;
            string totalDurStr = totalVideoDur.ToString("0.###", CultureInfo.InvariantCulture);

            var args = new List<string>
            {
                "-nostdin",
                "-hide_banner",
                "-loglevel", "error",
                "-y"
            };

            foreach (string seg in segPaths)
            {
                args.Add("-i");
                args.Add(seg);
            }

            int nextInputIdx = n;
            int? musicInputIdx = null;
            string? musicFull = ResolveSafeAudioPath(project.BackgroundMusic, projDir, requireExists: true);
            if (!string.IsNullOrWhiteSpace(musicFull))
            {
                if (project.MusicStartSec > 0.02)
                {
                    args.Add("-ss");
                    args.Add(project.MusicStartSec.ToString("0.###", CultureInfo.InvariantCulture));
                }
                args.Add("-stream_loop");
                args.Add("-1");
                args.Add("-t");
                args.Add(totalDurStr);
                args.Add("-i");
                args.Add(musicFull);
                musicInputIdx = nextInputIdx++;
            }

            var sfxEvents = new List<(int InputIdx, int DelayMs, double MaxPlaySec, double FadeOutSec, double Gain)>();

            void SchedulePeakAlignedSfx(string? relOrFull, double targetPeakTimelineSec, double gainMul, bool hardCutAfterPeak = false, double maxTailAfterPeak = 2.6)
            {
                string? full = ResolveSafeAudioPath(relOrFull, projDir, requireExists: true);
                if (string.IsNullOrWhiteSpace(full)) return;

                var (peakSec, fileDur) = GetAudioPeakEnvelopeInfo(full, projDir);
                double alignedPeak = Math.Min(peakSec, 3.2);
                double startTimelineSec = targetPeakTimelineSec - alignedPeak;
                double trimInFile = 0.0;
                if (startTimelineSec < 0.0)
                {
                    trimInFile = -startTimelineSec;
                    startTimelineSec = 0.0;
                }

                double playDur = hardCutAfterPeak
                    ? Math.Max(0.15, (alignedPeak - trimInFile) + 0.08)
                    : Math.Min(Math.Max(0.25, fileDur - trimInFile), Math.Max(0.4, (alignedPeak - trimInFile) + maxTailAfterPeak));

                if (trimInFile > 0.02)
                {
                    args.Add("-ss");
                    args.Add(trimInFile.ToString("0.###", CultureInfo.InvariantCulture));
                }
                args.Add("-t");
                args.Add(playDur.ToString("0.###", CultureInfo.InvariantCulture));
                args.Add("-i");
                args.Add(full);

                int delayMs = Math.Max(0, (int)Math.Round(startTimelineSec * 1000.0));
                double fadeOut = hardCutAfterPeak ? 0.06 : Math.Min(0.85, playDur * 0.35);
                sfxEvents.Add((nextInputIdx++, delayMs, playDur, fadeOut, project.SfxVolume * gainMul));
            }

            // 1. Cold-Open Intro Sub-Pulse right at frame 0 (0.04s)
            SchedulePeakAlignedSfx(project.IntroAccentSfx, 0.04, 0.88, hardCutAfterPeak: false, maxTailAfterPeak: 2.0);

            // 2. Beat-Locked Transition / Riser / Impact SFX per cut
            var sb = new StringBuilder();
            for (int k = 1; k < n; k++)
            {
                double offset = cutOffsets[k - 1];
                double td = cutTransDurs[k - 1];
                double cutCenter = offset + (td * 0.5);
                var prevScene = project.Scenes[k - 1];

                if (!string.IsNullOrWhiteSpace(prevScene.RiserSfx))
                    SchedulePeakAlignedSfx(prevScene.RiserSfx, cutCenter - 0.02, 0.75, hardCutAfterPeak: true);

                if (!string.IsNullOrWhiteSpace(prevScene.TransitionSfx))
                    SchedulePeakAlignedSfx(prevScene.TransitionSfx, cutCenter, 0.86, hardCutAfterPeak: false, maxTailAfterPeak: 1.6);

                if (!string.IsNullOrWhiteSpace(prevScene.ImpactSfx))
                    SchedulePeakAlignedSfx(prevScene.ImpactSfx, cutCenter + 0.02, 0.84, hardCutAfterPeak: false, maxTailAfterPeak: 2.0);
            }

            // 3. Mystical Outro Swell & Crystal Harmonics on the final reveal dissolve
            if (n >= 2 && !string.IsNullOrWhiteSpace(project.OutroAccentSfx))
            {
                double finalCutCenter = cutOffsets.Last() + (cutTransDurs.Last() * 0.5);
                SchedulePeakAlignedSfx(project.OutroAccentSfx, finalCutCenter, 0.92, hardCutAfterPeak: false, maxTailAfterPeak: 3.4);
            }

            // 4. Build Cinema Video Transition Chain (with per-cut custom smoothness)
            if (n == 1)
            {
                sb.Append("[0:v]null[vmaster];[0:a]anull[aseg];");
            }
            else
            {
                string prevV = "0:v";
                string prevA = "0:a";

                for (int k = 1; k < n; k++)
                {
                    string rawTr = project.Scenes[k - 1].Transition?.Trim().ToLowerInvariant() ?? "whip_zoom";
                    string xfadeName = rawTr switch
                    {
                        "whip_zoom" or "zoom_in" or "zoomin" => "zoomin",
                        "whip_pan" or "hblur" => "hblur",
                        "dark_cut" or "fadeblack" => "fadeblack",
                        "velvet_dissolve" or "dissolve" or "fade" => "fade",
                        "flash_cut" or "fadefast" => "fadefast",
                        "flash_white" or "fadewhite" => "fadewhite",
                        "smoothup" => "smoothup",
                        "smoothleft" => "smoothleft",
                        _ => "zoomin"
                    };
                    string tdStr = cutTransDurs[k - 1].ToString("0.###", CultureInfo.InvariantCulture);
                    string offStr = cutOffsets[k - 1].ToString("0.###", CultureInfo.InvariantCulture);
                    string outV = (k == n - 1) ? "vmaster" : $"vx{k}";
                    string outA = (k == n - 1) ? "aseg" : $"ax{k}";

                    sb.Append($"[{prevV}][{k}:v]xfade=transition={xfadeName}:duration={tdStr}:offset={offStr}[{outV}];");
                    sb.Append($"[{prevA}][{k}:a]acrossfade=d={tdStr}:c1=tri:c2=tri[{outA}];");
                    prevV = outV;
                    prevA = outA;
                }
            }

            // 5. Build Cinema Multi-Bus Audio Mix + Broadcast Mastering (-14.5 LUFS, -1.2 dBTP)
            var mixInputs = new List<string> { "[aseg]" };

            if (musicInputIdx.HasValue)
            {
                string mVol = Math.Clamp(project.MusicVolume, 0.0, 1.5).ToString("0.##", CultureInfo.InvariantCulture);
                string fadeOutSt = Math.Max(0.2, totalVideoDur - 0.85).ToString("0.###", CultureInfo.InvariantCulture);
                sb.Append($"[{musicInputIdx.Value}:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,volume={mVol},afade=t=in:st=0:d=0.14,afade=t=out:st={fadeOutSt}:d=0.80,atrim=0:{totalDurStr},asetpts=PTS-STARTPTS[amusic];");
                mixInputs.Add("[amusic]");
            }

            for (int s = 0; s < sfxEvents.Count; s++)
            {
                var ev = sfxEvents[s];
                string gStr = Math.Clamp(ev.Gain, 0.05, 1.6).ToString("0.##", CultureInfo.InvariantCulture);
                string foStart = Math.Max(0.02, ev.MaxPlaySec - ev.FadeOutSec).ToString("0.###", CultureInfo.InvariantCulture);
                string foDur = Math.Max(0.04, ev.FadeOutSec).ToString("0.###", CultureInfo.InvariantCulture);
                sb.Append($"[{ev.InputIdx}:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,afade=t=out:st={foStart}:d={foDur},volume={gStr},adelay={ev.DelayMs}|{ev.DelayMs}[asfx{s}];");
                mixInputs.Add($"[asfx{s}]");
            }

            if (mixInputs.Count == 1)
            {
                sb.Append($"[aseg]loudnorm=I=-15:TP=-1.2:LRA=10,atrim=0:{totalDurStr},asetpts=PTS-STARTPTS[amaster]");
            }
            else
            {
                sb.Append($"{string.Join("", mixInputs)}amix=inputs={mixInputs.Count}:duration=longest:dropout_transition=0:normalize=0,acompressor=threshold=-12dB:ratio=2.2:attack=8:release=110,loudnorm=I=-14.5:TP=-1.2:LRA=9,atrim=0:{totalDurStr},asetpts=PTS-STARTPTS[amaster]");
            }

            args.Add("-filter_complex");
            args.Add(sb.ToString());
            args.Add("-map");
            args.Add("[vmaster]");
            args.Add("-map");
            args.Add("[amaster]");
            args.Add("-t");
            args.Add(totalDurStr);

            // Strict WhatsApp / Instagram Reels / TikTok / Windows Explorer H.264 Main + AAC faststart compliance
            args.Add("-c:v");
            args.Add("libx264");
            args.Add("-preset");
            args.Add("medium");
            args.Add("-crf");
            args.Add("16");
            args.Add("-aq-mode");
            args.Add("3");
            args.Add("-aq-strength");
            args.Add("1.15");
            args.Add("-profile:v");
            args.Add("main");
            args.Add("-pix_fmt");
            args.Add("yuv420p");
            args.Add("-color_range");
            args.Add("tv");
            args.Add("-colorspace");
            args.Add("bt709");
            args.Add("-color_primaries");
            args.Add("bt709");
            args.Add("-color_trc");
            args.Add("bt709");
            args.Add("-r");
            args.Add(fps.ToString(CultureInfo.InvariantCulture));
            args.Add("-c:a");
            args.Add("aac");
            args.Add("-b:a");
            args.Add("256k");
            args.Add("-ar");
            args.Add("48000");
            args.Add("-ac");
            args.Add("2");
            args.Add("-movflags");
            args.Add("+faststart");
            args.Add(stagedMp4);

            string ffmpegExe = FindTrustedFfmpegExe();
            var (exitCode, _, err) = await RunProcessBoundedAsync(ffmpegExe, args, timeoutMs: 300_000, ct: ct);
            if (exitCode != 0 || !File.Exists(stagedMp4))
                throw new InvalidOperationException($"Master-Renderfehler (Exit {exitCode}): {err}");
        }

        public static async Task ValidateMp4PlayabilityAsync(string mp4Path)
        {
            var fi = new FileInfo(mp4Path);
            if (!fi.Exists || fi.Length < 4096)
                throw new InvalidOperationException("Gerendertes Video ist leer oder unvollstaendig.");

            byte[] head = new byte[Math.Min(65536, (int)fi.Length)];
            await using (var fs = new FileStream(mp4Path, FileMode.Open, FileAccess.Read, FileShare.Read))
            {
                _ = await fs.ReadAsync(head, 0, head.Length);
            }
            string ascii = Encoding.Latin1.GetString(head);
            int ftypIdx = ascii.IndexOf("ftyp", StringComparison.Ordinal);
            int moovIdx = ascii.IndexOf("moov", StringComparison.Ordinal);
            if (ftypIdx < 0 || moovIdx < 0)
                throw new InvalidOperationException("MP4 FastStart-Header (ftyp/moov) fehlt am Dateianfang.");

            var info = await ProbeMediaFileAsync(mp4Path);
            if (info.Width <= 0 || info.Height <= 0 || info.DurationSec < 0.5 || !info.HasAudio)
                throw new InvalidOperationException("MP4 Stream-Pruefung fehlgeschlagen (Video/Audio unvollstaendig).");
        }

        // =====================================================================
        // CLI & NAMED PIPE IPC AUTOMATION DISPATCHER (USED BY FUNKENWERK MCP)
        // =====================================================================
        public static int GetCliExitCode(string? resultJson)
        {
            if (string.IsNullOrWhiteSpace(resultJson))
                return 1;
            try
            {
                using var doc = JsonDocument.Parse(resultJson);
                if (doc.RootElement.TryGetProperty("ok", out var okProp) && okProp.ValueKind == JsonValueKind.True)
                    return 0;
            }
            catch { }
            return 1;
        }

        public static async Task<string> ExecuteCliCommandJsonAsync(string jsonRequest)
        {
            try
            {
                var req = JsonNode.Parse(jsonRequest)?.AsObject() ?? throw new ArgumentException("Ungueltiges JSON.");
                string action = req["action"]?.GetValue<string>()?.Trim().ToLowerInvariant() ?? "status";

                object payload = action switch
                {
                    "status" => new
                    {
                        ok = true,
                        workspaceRoot = WorkspaceRoot,
                        videosRoot = VideosRoot,
                        soundsRoot = SoundsRoot,
                        engines = new
                        {
                            artcraft = new { installed = File.Exists(ArtCraftExe), path = ArtCraftExe },
                            filmcraft = new { installed = File.Exists(FilmCraftExe), path = FilmCraftExe, cli = FilmCraftCliExe },
                            soundcraft = new { installed = File.Exists(SoundCraftExe), path = SoundCraftExe, cli = SoundCraftCliExe }
                        },
                        projects = ListProjectNames(),
                        soundPoolCount = ListSoundPool().Count
                    },

                    "list_projects" => new
                    {
                        ok = true,
                        projects = ListProjectNames().Select(n =>
                        {
                            var p = LoadOrCreateProject(n);
                            return new
                            {
                                p.Name,
                                p.AspectRatio,
                                sceneCount = p.Scenes.Count,
                                mediaCount = ListProjectMediaFiles(n).Count,
                                p.LastRenderedPath,
                                hasRenderedVideo = !string.IsNullOrEmpty(p.LastRenderedPath) && File.Exists(p.LastRenderedPath),
                                p.UpdatedAtUtc
                            };
                        }).ToList()
                    },

                    "get_project" => new
                    {
                        ok = true,
                        project = LoadOrCreateProject(req["projectName"]?.GetValue<string>() ?? "Projekt 1"),
                        availableMedia = ListProjectMediaFiles(req["projectName"]?.GetValue<string>() ?? "Projekt 1")
                            .Select(Path.GetFileName).ToList()
                    },

                    "create_project" => CreateOrUpdateProjectFromJson(req),

                    "save_project" => CreateOrUpdateProjectFromJson(req),

                    "import_media" => await ImportMediaFromJsonAsync(req),

                    "inspect_media" => new
                    {
                        ok = true,
                        media = await ProbeMediaFileAsync(req["filePath"]?.GetValue<string>() ?? throw new ArgumentException("filePath fehlt."))
                    },

                    "list_sounds" => new
                    {
                        ok = true,
                        sounds = ListSoundPool(req["category"]?.GetValue<string>())
                    },

                    "render_video" => await RenderFromJsonAsync(req),

                    "export_filmcraft" => new
                    {
                        ok = true,
                        filmcraftProjectPath = await ExportFilmCraftNativeProjectAsync(
                            LoadOrCreateProject(req["projectName"]?.GetValue<string>() ?? "Projekt 1"))
                    },

                    "launch_engine" => LaunchStorytoldEngine(
                        req["engine"]?.GetValue<string>() ?? "artcraft",
                        req["targetPath"]?.GetValue<string>(),
                        req["enableControlBridge"]?.GetValue<bool>() ?? true,
                        req["validateOnly"]?.GetValue<bool>() ?? false),

                    "filmcraft_cli" => new
                    {
                        ok = true,
                        output = await RunFilmCraftCliAsync(
                            req["args"]?.AsArray().Select(x => x?.GetValue<string>() ?? "").Where(s => s.Length > 0)
                            ?? new[] { "commands" })
                    },

                    "filmcraft_bridge" => new
                    {
                        ok = true,
                        response = await SendFilmCraftBridgeCommandAsync(
                            req["method"]?.GetValue<string>() ?? "engine.ping",
                            req["params"])
                    },

                    _ => throw new ArgumentException($"Unbekannte Craft-Aktion '{action}'.")
                };

                return JsonSerializer.Serialize(payload, JsonOpts);
            }
            catch (Exception ex)
            {
                return JsonSerializer.Serialize(new { ok = false, error = ex.Message }, JsonOpts);
            }
        }

        private static object CreateOrUpdateProjectFromJson(JsonObject req)
        {
            string name = SanitizeProjectName(req["projectName"]?.GetValue<string>()
                ?? req["project"]?["name"]?.GetValue<string>()
                ?? "Projekt 1");

            CraftProjectModel proj;
            if (req["project"] is JsonObject projObj)
            {
                proj = JsonSerializer.Deserialize<CraftProjectModel>(projObj.ToJsonString(), JsonOpts)
                    ?? LoadOrCreateProject(name);
                proj.Name = name;
            }
            else
            {
                proj = LoadOrCreateProject(name);
                if (req["aspectRatio"] != null) proj.AspectRatio = req["aspectRatio"]!.GetValue<string>();
                if (req["transitionDurationSec"] != null) proj.TransitionDurationSec = req["transitionDurationSec"]!.GetValue<double>();
                if (req["colorGrade"] != null) proj.ColorGrade = req["colorGrade"]!.GetValue<string>();
                if (req["overlayStyle"] != null) proj.OverlayStyle = req["overlayStyle"]!.GetValue<string>();
                if (req["backgroundMusic"] != null) proj.BackgroundMusic = req["backgroundMusic"]!.GetValue<string>();
                if (req["musicStartSec"] != null) proj.MusicStartSec = req["musicStartSec"]!.GetValue<double>();
                if (req["musicVolume"] != null) proj.MusicVolume = req["musicVolume"]!.GetValue<double>();
                if (req["sfxVolume"] != null) proj.SfxVolume = req["sfxVolume"]!.GetValue<double>();
                if (req["introAccentSfx"] != null) proj.IntroAccentSfx = req["introAccentSfx"]!.GetValue<string>();
                if (req["outroAccentSfx"] != null) proj.OutroAccentSfx = req["outroAccentSfx"]!.GetValue<string>();
                if (req["showBrandHeader"] != null) proj.ShowBrandHeader = req["showBrandHeader"]!.GetValue<bool>();
                if (req["brandHeaderText"] != null) proj.BrandHeaderText = req["brandHeaderText"]!.GetValue<string>();
                if (req["outputFileName"] != null) proj.OutputFileName = req["outputFileName"]!.GetValue<string>();
                if (req["scenes"] is JsonArray scenesArr)
                {
                    proj.Scenes = JsonSerializer.Deserialize<List<CraftSceneItem>>(scenesArr.ToJsonString(), JsonOpts) ?? new();
                }
            }

            SaveProject(proj);
            return new { ok = true, project = proj };
        }

        private static async Task<object> ImportMediaFromJsonAsync(JsonObject req)
        {
            string projName = SanitizeProjectName(req["projectName"]?.GetValue<string>() ?? "Projekt 1");
            var copied = new List<object>();

            if (req["sourcePaths"] is not JsonArray arr || arr.Count == 0)
                throw new ArgumentException("sourcePaths Array fehlt oder ist leer.");

            foreach (var node in arr)
            {
                string? src = node?.GetValue<string>();
                if (string.IsNullOrWhiteSpace(src))
                    throw new ArgumentException("Leerer Pfad in sourcePaths.");

                string dest = ImportMediaToProject(projName, src);
                var info = await ProbeMediaFileAsync(dest);
                copied.Add(new { info.FileName, info.MediaType, info.EffectiveWidth, info.EffectiveHeight, info.Fps, info.DurationSec, info.SizeMb });
            }

            var proj = LoadOrCreateProject(projName);
            bool modified = false;
            foreach (string mediaPath in ListProjectMediaFiles(projName))
            {
                string relName = GetProjectRelativeMediaName(projName, mediaPath);
                if (!proj.Scenes.Any(s => string.Equals(s.FileName, relName, StringComparison.OrdinalIgnoreCase)))
                {
                    bool isImg = ImageExts.Contains(Path.GetExtension(mediaPath));
                    proj.Scenes.Add(new CraftSceneItem
                    {
                        FileName = relName,
                        MediaType = isImg ? "image" : "video",
                        StartSec = 0,
                        DurationSec = isImg ? 2.6 : 2.2,
                        FitMode = "fill",
                        SpeedMode = isImg ? "linear" : "speed_ramp",
                        SlowMoFactor = 0.42,
                        Transition = "whip_zoom"
                    });
                    modified = true;
                }
            }
            if (modified) SaveProject(proj);
            return new { ok = true, projectName = projName, importedCount = copied.Count, imported = copied, totalScenes = proj.Scenes.Count };
        }

        private static async Task<object> RenderFromJsonAsync(JsonObject req)
        {
            string projName = SanitizeProjectName(req["projectName"]?.GetValue<string>() ?? "Projekt 1");
            var proj = LoadOrCreateProject(projName);
            string outPath = await RenderProjectSocialVideoAsync(proj);
            var info = await ProbeMediaFileAsync(outPath);
            return new
            {
                ok = true,
                projectName = projName,
                outputPath = outPath,
                filmcraftProjectPath = proj.LastFilmCraftProjectPath,
                durationSec = info.DurationSec,
                width = info.EffectiveWidth,
                height = info.EffectiveHeight,
                fps = info.Fps,
                hasAudio = info.HasAudio,
                sizeMb = info.SizeMb
            };
        }

        public static PipeSecurity CreateCurrentUserPipeSecurity()
        {
            var sec = new PipeSecurity();
            var user = WindowsIdentity.GetCurrent().User;
            if (user != null)
                sec.AddAccessRule(new PipeAccessRule(user, PipeAccessRights.FullControl, AccessControlType.Allow));
            return sec;
        }

        public static Task<int> RunSelfTestAsync() => CraftSelfTestRunner.RunAllAsync();
    }
}
