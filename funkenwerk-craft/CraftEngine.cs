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

        private static readonly ConcurrentDictionary<string, (double PeakSec, double DurationSec)> AudioPeakCache = new(StringComparer.OrdinalIgnoreCase);

        public static readonly HashSet<string> VideoExts = new(StringComparer.OrdinalIgnoreCase)
            { ".mp4", ".mov", ".mkv", ".webm", ".avi", ".m4v" };

        public static readonly HashSet<string> ImageExts = new(StringComparer.OrdinalIgnoreCase)
            { ".jpg", ".jpeg", ".png", ".webp", ".bmp" };

        public static readonly HashSet<string> AudioExts = new(StringComparer.OrdinalIgnoreCase)
            { ".wav", ".mp3", ".ogg", ".flac", ".m4a" };

        public static string WorkspaceRoot
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

        public static string VideosRoot => EnsureDir(Path.Combine(WorkspaceRoot, "Videos"));
        public static string SoundsRoot => EnsureDir(Path.Combine(WorkspaceRoot, "Sounds"));
        public static string FontsRoot => EnsureDir(Path.Combine(WorkspaceRoot, "Fonts"));
        public static string EngineRoot => EnsureDir(Path.Combine(WorkspaceRoot, "engine"));
        public static string ArtCraftExe => Path.Combine(EngineRoot, "artcraft", "artcraft.exe");
        public static string FilmCraftExe => Path.Combine(EngineRoot, "filmcraft", "filmcraft.exe");
        public static string FilmCraftCliExe => Path.Combine(EngineRoot, "filmcraft", "filmcraft-cli.exe");
        public static string SoundCraftExe => Path.Combine(EngineRoot, "soundcraft", "soundcraft.exe");
        public static string SoundCraftCliExe => Path.Combine(EngineRoot, "soundcraft", "soundcraft-cli.exe");

        public static string LocalDataDir
        {
            get
            {
                string dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkCraft");
                if (!Directory.Exists(dir)) Directory.CreateDirectory(dir);
                return dir;
            }
        }

        public static string SettingsFilePath => Path.Combine(LocalDataDir, "craft_settings.json");
        public static string BridgeStateFilePath => Path.Combine(LocalDataDir, "bridge_state.json");

        public static string PipeName
        {
            get
            {
                string sid = WindowsIdentity.GetCurrent().User?.Value ?? Environment.UserName;
                return $"Funkenwerk_Craft_IPC_{sid}";
            }
        }

        private static string EnsureDir(string p)
        {
            if (!Directory.Exists(p)) Directory.CreateDirectory(p);
            return p;
        }

        public static string SanitizeCleanText(string? input)
        {
            if (string.IsNullOrWhiteSpace(input)) return "";
            // Strictly enforce Seelenfunke rule: never allow " – " en-dashes
            return input.Replace(" – ", " · ").Replace("–", "-").Trim();
        }

        public static string SanitizeProjectName(string name)
        {
            if (string.IsNullOrWhiteSpace(name))
                throw new ArgumentException("Projektname darf nicht leer sein.");
            string clean = SanitizeCleanText(name);
            foreach (char c in Path.GetInvalidFileNameChars())
                clean = clean.Replace(c, '_');
            clean = clean.Replace("..", "_").Trim('.', ' ');
            if (string.IsNullOrWhiteSpace(clean))
                throw new ArgumentException("Ungültiger Projektname.");
            return clean;
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
            string full = Path.GetFullPath(Path.Combine(VideosRoot, safe));
            string rootPrefix = Path.GetFullPath(VideosRoot).TrimEnd('\\') + "\\";
            if (!full.StartsWith(rootPrefix, StringComparison.OrdinalIgnoreCase))
                throw new UnauthorizedAccessException("Projektpfad liegt außerhalb des Videos-Arbeitsbereichs.");
            return EnsureDir(full);
        }

        public static string GetProjectJsonPath(string projectName) =>
            Path.Combine(GetProjectDirectory(projectName), "craft_project.json");

        public static List<string> ListProjectNames()
        {
            EnsureDir(Path.Combine(VideosRoot, "Projekt 1"));
            return Directory.GetDirectories(VideosRoot)
                .Select(Path.GetFileName)
                .Where(n => !string.IsNullOrWhiteSpace(n))
                .Select(n => n!)
                .OrderBy(n => n, StringComparer.OrdinalIgnoreCase)
                .ToList();
        }

        public static List<string> ListProjectMediaFiles(string projectName)
        {
            string dir = GetProjectDirectory(projectName);
            var results = new List<string>();

            foreach (string f in Directory.GetFiles(dir))
            {
                string ext = Path.GetExtension(f);
                string fn = Path.GetFileName(f);
                if (fn.StartsWith("Social_Media_Ready_", StringComparison.OrdinalIgnoreCase) ||
                    fn.StartsWith("_temp_", StringComparison.OrdinalIgnoreCase))
                    continue;
                if (VideoExts.Contains(ext) || ImageExts.Contains(ext))
                    results.Add(f);
            }

            string rawSub = Path.Combine(dir, "Rohmaterial");
            if (Directory.Exists(rawSub))
            {
                foreach (string f in Directory.GetFiles(rawSub))
                {
                    string ext = Path.GetExtension(f);
                    if (VideoExts.Contains(ext) || ImageExts.Contains(ext))
                        results.Add(f);
                }
            }

            return results.OrderBy(Path.GetFileName, StringComparer.OrdinalIgnoreCase).ToList();
        }

        public static CraftProjectModel LoadOrCreateProject(string projectName)
        {
            string safe = SanitizeProjectName(projectName);
            string jsonPath = GetProjectJsonPath(safe);
            if (File.Exists(jsonPath))
            {
                try
                {
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
            string projDir = GetProjectDirectory(projectName).TrimEnd('\\') + "\\";
            string full = Path.GetFullPath(fullMediaPath);
            if (full.StartsWith(projDir, StringComparison.OrdinalIgnoreCase))
                return full.Substring(projDir.Length);
            return Path.GetFileName(full);
        }

        public static string ResolveSceneMediaPath(string projectName, string sceneFileName)
        {
            string projDir = GetProjectDirectory(projectName);
            string direct = Path.GetFullPath(Path.Combine(projDir, sceneFileName));
            string prefix = projDir.TrimEnd('\\') + "\\";
            if (!direct.StartsWith(prefix, StringComparison.OrdinalIgnoreCase))
                throw new UnauthorizedAccessException("Ungültiger Szenen-Dateipfad.");
            if (File.Exists(direct)) return direct;

            string inRaw = Path.Combine(projDir, "Rohmaterial", Path.GetFileName(sceneFileName));
            if (File.Exists(inRaw)) return inRaw;

            return direct;
        }

        public static void SaveProject(CraftProjectModel project)
        {
            project.Name = SanitizeProjectName(project.Name);
            project.BrandHeaderText = SanitizeCleanText(project.BrandHeaderText);
            project.TransitionDurationSec = Math.Clamp(project.TransitionDurationSec, 0.08, 1.20);
            foreach (var s in project.Scenes)
            {
                s.OverlayBadge = SanitizeCleanText(s.OverlayBadge);
                s.OverlayTitle = SanitizeCleanText(s.OverlayTitle);
                s.OverlaySubtitle = SanitizeCleanText(s.OverlaySubtitle);
                s.DurationSec = Math.Clamp(s.DurationSec, 0.8, 60.0);
                s.StartSec = Math.Max(0.0, s.StartSec);
                s.SlowMoFactor = Math.Clamp(s.SlowMoFactor <= 0 ? 0.42 : s.SlowMoFactor, 0.25, 0.90);
                s.TransitionDurSec = s.TransitionDurSec <= 0.02 ? 0.0 : Math.Clamp(s.TransitionDurSec, 0.08, 1.40);
                if (string.Equals(s.MediaType, "brand_outro", StringComparison.OrdinalIgnoreCase))
                {
                    if (string.IsNullOrWhiteSpace(s.FileName)) s.FileName = "__BRAND_OUTRO__";
                    if (string.IsNullOrWhiteSpace(s.OverlayTitle)) s.OverlayTitle = "Mein Seelenfunke";
                }
                if (string.IsNullOrWhiteSpace(s.SpeedMode))
                    s.SpeedMode = (s.MediaType == "image" || s.MediaType == "brand_outro") ? "linear" : "speed_ramp";
            }
            project.UpdatedAtUtc = DateTime.UtcNow;
            string jsonPath = GetProjectJsonPath(project.Name);
            File.WriteAllText(jsonPath, JsonSerializer.Serialize(project, JsonOpts), new UTF8Encoding(false));
        }

        public static List<CraftSoundItem> ListSoundPool(string? categoryFilter = null)
        {
            var list = new List<CraftSoundItem>();
            string licFile = Path.Combine(SoundsRoot, "LIZENZEN_GEWERBLICHE_NUTZUNG.json");
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

            if (!Directory.Exists(SoundsRoot)) return list;

            foreach (string subDir in Directory.GetDirectories(SoundsRoot))
            {
                string cat = Path.GetFileName(subDir);
                if (!string.IsNullOrWhiteSpace(categoryFilter) &&
                    !string.Equals(categoryFilter, "all", StringComparison.OrdinalIgnoreCase) &&
                    !cat.Contains(categoryFilter, StringComparison.OrdinalIgnoreCase))
                {
                    continue;
                }

                foreach (string f in Directory.GetFiles(subDir))
                {
                    if (!AudioExts.Contains(Path.GetExtension(f))) continue;
                    string rel = $"{cat}\\{Path.GetFileName(f)}";
                    metaMap.TryGetValue(rel, out var m);
                    list.Add(new CraftSoundItem
                    {
                        FileName = Path.GetFileName(f),
                        Category = cat,
                        RelativePath = rel,
                        FullPath = f,
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
            if (!File.Exists(filePath))
                throw new FileNotFoundException($"Mediendatei nicht gefunden: {filePath}");

            var fi = new FileInfo(filePath);
            string ext = fi.Extension.ToLowerInvariant();
            bool isImg = ImageExts.Contains(ext);

            var info = new CraftMediaInfo
            {
                FileName = fi.Name,
                FullPath = fi.FullName,
                MediaType = isImg ? "image" : "video",
                SizeMb = Math.Round(fi.Length / 1048576.0, 2)
            };

            if (isImg)
            {
                using var img = Image.FromFile(filePath);
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

            var psi = new ProcessStartInfo
            {
                FileName = "ffprobe",
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true
            };
            psi.ArgumentList.Add("-v");
            psi.ArgumentList.Add("quiet");
            psi.ArgumentList.Add("-print_format");
            psi.ArgumentList.Add("json");
            psi.ArgumentList.Add("-show_format");
            psi.ArgumentList.Add("-show_streams");
            psi.ArgumentList.Add(filePath);

            using var proc = Process.Start(psi) ?? throw new InvalidOperationException("ffprobe konnte nicht gestartet werden.");
            string json = await proc.StandardOutput.ReadToEndAsync();
            await proc.WaitForExitAsync();

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

        private static (double PeakSec, double DurationSec) GetAudioPeakEnvelopeInfo(string audioPath)
        {
            if (AudioPeakCache.TryGetValue(audioPath, out var cached))
                return cached;

            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = "ffmpeg",
                    UseShellExecute = false,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    CreateNoWindow = true
                };
                psi.ArgumentList.Add("-v");
                psi.ArgumentList.Add("quiet");
                psi.ArgumentList.Add("-i");
                psi.ArgumentList.Add(audioPath);
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
                    using var ms = new MemoryStream();
                    proc.StandardOutput.BaseStream.CopyTo(ms);
                    proc.WaitForExit(5000);
                    byte[] pcm = ms.ToArray();
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
                        AudioPeakCache[audioPath] = res;
                        return res;
                    }
                }
            }
            catch { }

            var fallback = (0.08, 1.5);
            AudioPeakCache[audioPath] = fallback;
            return fallback;
        }

        // =====================================================================
        // STORYTOLD ARTCRAFT / FILMCRAFT / SOUNDCRAFT ENGINE INTEGRATION
        // =====================================================================
        private static (int Port, string Token) GetOrCreateFilmCraftBridgeCredentials()
        {
            var settings = LoadSettings();
            int port = settings.FilmCraftControlPort > 1024 ? settings.FilmCraftControlPort : 9876;

            try
            {
                if (File.Exists(BridgeStateFilePath))
                {
                    using var doc = JsonDocument.Parse(File.ReadAllText(BridgeStateFilePath));
                    if (doc.RootElement.TryGetProperty("token", out var tp))
                    {
                        string? tok = tp.GetString();
                        int p = doc.RootElement.TryGetProperty("port", out var pp) ? pp.GetInt32() : port;
                        if (!string.IsNullOrWhiteSpace(tok) && tok.Length == 64)
                            return (p, tok);
                    }
                }
            }
            catch { }

            string newToken = Convert.ToHexString(RandomNumberGenerator.GetBytes(32)).ToLowerInvariant();
            File.WriteAllText(BridgeStateFilePath, JsonSerializer.Serialize(new { port, token = newToken, updatedUtc = DateTime.UtcNow }), new UTF8Encoding(false));
            return (port, newToken);
        }

        public static object LaunchStorytoldEngine(string engineKey, string? targetPath = null, bool enableControlBridge = true)
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

            if (!string.IsNullOrWhiteSpace(targetPath) && File.Exists(targetPath))
            {
                psi.ArgumentList.Add(targetPath);
            }

            var proc = Process.Start(psi);
            return new
            {
                launched = true,
                engine = norm,
                executable = exePath,
                pid = proc?.Id ?? 0,
                controlPort = activePort,
                targetPath = targetPath ?? ""
            };
        }

        public static async Task<string> RunFilmCraftCliAsync(IEnumerable<string> arguments, int timeoutSec = 90)
        {
            if (!File.Exists(FilmCraftCliExe))
                throw new FileNotFoundException($"filmcraft-cli.exe nicht gefunden: {FilmCraftCliExe}");

            var (_, token) = GetOrCreateFilmCraftBridgeCredentials();
            var psi = new ProcessStartInfo
            {
                FileName = FilmCraftCliExe,
                WorkingDirectory = Path.GetDirectoryName(FilmCraftCliExe)!,
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true,
                StandardOutputEncoding = Encoding.UTF8,
                StandardErrorEncoding = Encoding.UTF8
            };
            psi.Environment["FILMCRAFT_CONTROL_TOKEN"] = token;
            foreach (string a in arguments) psi.ArgumentList.Add(a);

            using var proc = Process.Start(psi) ?? throw new InvalidOperationException("filmcraft-cli Start fehlgeschlagen.");
            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(timeoutSec));
            Task<string> outTask = proc.StandardOutput.ReadToEndAsync(cts.Token);
            Task<string> errTask = proc.StandardError.ReadToEndAsync(cts.Token);
            await proc.WaitForExitAsync(cts.Token);

            string stdout = await outTask;
            string stderr = await errTask;
            return string.IsNullOrWhiteSpace(stdout) ? stderr.Trim() : stdout.Trim();
        }

        public static async Task<string> SendFilmCraftBridgeCommandAsync(string method, JsonNode? parameters = null)
        {
            var (port, token) = GetOrCreateFilmCraftBridgeCredentials();
            using var tcp = new TcpClient();
            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(10));
            await tcp.ConnectAsync("127.0.0.1", port, cts.Token);
            await using var stream = tcp.GetStream();
            using var writer = new StreamWriter(stream, new UTF8Encoding(false)) { AutoFlush = true };
            using var reader = new StreamReader(stream, Encoding.UTF8);

            var req = new JsonObject
            {
                ["token"] = token,
                ["method"] = method,
                ["params"] = parameters?.DeepClone() ?? new JsonObject()
            };
            await writer.WriteLineAsync(req.ToJsonString());
            string? line = await reader.ReadLineAsync(cts.Token);
            return line ?? "{}";
        }

        public static async Task<string> ExportFilmCraftNativeProjectAsync(CraftProjectModel project)
        {
            string projDir = GetProjectDirectory(project.Name);
            var (targetW, targetH) = ResolveDimensions(project.AspectRatio);
            string fcprojPath = Path.Combine(projDir, $"{project.Name.Replace(' ', '_')}.fcproj");
            string scriptPath = Path.Combine(projDir, "filmcraft_build.jsonl");

            var lines = new List<string>();
            lines.Add(JsonSerializer.Serialize(new
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
            }));

            foreach (var scene in project.Scenes)
            {
                if (!string.IsNullOrWhiteSpace(scene.OverlayTitle))
                {
                    lines.Add(JsonSerializer.Serialize(new
                    {
                        id = "graphics.newText",
                        @params = new
                        {
                            text = scene.OverlayTitle,
                            size = 56,
                            seconds = scene.DurationSec,
                            position = new[] { targetW / 2, (int)(targetH * 0.77) }
                        }
                    }));
                }
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
                    await RunFilmCraftCliAsync(new[] { "run", scriptPath }, timeoutSec: 45);
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

        private static void RenderLuxuryOverlayPng(
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

            // STRICT MONOCHROME PURE WHITE TYPOGRAPHY (Zero gold text, zero colored lines — authentic cinema look)
            Color pureWhite = Color.FromArgb(250, 255, 255, 255);      // #FFFFFF Pure Crisp White
            Color softWhite = Color.FromArgb(190, 255, 255, 255);      // Subtle Pure White for micro-overlines/sublines
            Color headerWhite = Color.FromArgb(158, 255, 255, 255);

            string headerTxt = SanitizeCleanText(brandHeaderText).ToUpperInvariant();
            string badge = SanitizeCleanText(scene.OverlayBadge).ToUpperInvariant();
            string title = SanitizeCleanText(scene.OverlayTitle);
            string sub = SanitizeCleanText(scene.OverlaySubtitle);

            bool useCard = string.Equals(overlayStyle, "card", StringComparison.OrdinalIgnoreCase);

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

                    using var overlineFont = CreateBrandFont("montserrat_light", 17.5f * scale, FontStyle.Regular);
                    using var testMainFont = CreateBrandFont("montserrat_semibold", titlePt, FontStyle.Bold);
                    using var subFont = CreateBrandFont("montserrat_light", 21f * scale, FontStyle.Regular);

                    while (MeasureTrackedWidth(g, upperTitle, testMainFont, titleTrack) > width * 0.85f && titlePt > 24f)
                    {
                        titlePt -= 2f;
                        titleTrack = Math.Max(1.8f, titleTrack - 0.35f);
                    }
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
                using var titleFont = CreateBrandFont("montserrat_semibold", 36f, FontStyle.Bold);
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

            // 2-pass separable box blur Approximating smooth optical Pro-Mist halation
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

            // 3. Stream raw 60 FPS BGRA frames directly into FFmpeg segment encoder
            string mkvPath = Path.ChangeExtension(outSegMp4, ".mkv");
            var psi = new ProcessStartInfo
            {
                FileName = "ffmpeg",
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

            using var proc = Process.Start(psi) ?? throw new InvalidOperationException("ffmpeg konnte für Brand-Outro nicht gestartet werden.");
            Task<string> errTask = proc.StandardError.ReadToEndAsync(ct);

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
                        ct.ThrowIfCancellationRequested();
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
                                g.Clear(Color.Transparent); // Render foreground bokeh + typography in straight alpha
                                g.SmoothingMode = SmoothingMode.HighQuality;
                                g.InterpolationMode = InterpolationMode.HighQualityBicubic;
                                g.PixelOffsetMode = PixelOffsetMode.HighQuality;

                                // A) Slow-drifting monochrome silver-white stardust & depth-of-field bokeh
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

                                // B) Volumetric diagonal light-veil reveal of "Mein Seelenfunke" + "EIN FUNKE, DER BLEIBT"
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

                                            // Crisp wordmark + subtle Pro-Mist 1/8 cinema halation peaking as light sweeps across
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

                                // Smooth continuous 60 FPS cinema camera push-in (0.962x -> 1.030x cubic ease-out)
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

                            // C) 32-bit Float Volumetric Obsidian Mist + Anamorphic Light Breath + Triangular Film Dither (Zero 8-bit Banding)
                            float mistBreath = (float)(0.68 + 0.32 * Math.Sin(t * 1.15)) * (float)endFade;
                            float cx1 = outW * (0.48f + 0.04f * MathF.Sin((float)t * 0.55f));
                            float cy1 = outH * (0.485f - 0.015f * (float)t);
                            float invRx1 = 1.0f / (outW * 0.56f);
                            float invRy1 = 1.0f / (outH * 0.27f);

                            // Subtle horizontal anamorphic lens whisper tracking the diagonal reveal
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

                                    // Deep obsidian base (R=3, G=4, B=7) + subtle cool-silver mist + pure white anamorphic breath
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

                                    // Fast PCG hash -> zero-mean triangular dither in [-1.15, +1.15] LSB (eliminates 8-bit concentric banding)
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
                            await stdin.WriteAsync(batchBuffers[bIdx], 0, frameByteSize, ct);
                        }
                    }
                }
            }
            finally
            {
                foreach (var b in sparkSlots) b.Dispose();
                foreach (var b in frameSlots) b.Dispose();
                foreach (var b in bandSlots) b.Dispose();
            }

            string err = await errTask;
            await proc.WaitForExitAsync(ct);
            if (proc.ExitCode != 0 || !File.Exists(mkvPath))
                throw new InvalidOperationException($"Brand-Outro Renderfehler: {err}");

            File.Move(mkvPath, outSegMp4, overwrite: true);
        }

        public static async Task<string> RenderProjectSocialVideoAsync(
            CraftProjectModel project,
            IProgress<(double Pct, string Step)>? progress = null,
            CancellationToken ct = default)
        {
            if (project.Scenes == null || project.Scenes.Count == 0)
                throw new InvalidOperationException("Das Projekt enthält noch keine Szenen zum Rendern.");

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
                                throw new FileNotFoundException($"Quelldatei für Szene {i + 1} fehlt: {srcMedia}");

                            var mediaInfo = await ProbeMediaFileAsync(srcMedia);
                            bool isImg = mediaInfo.MediaType == "image";
                            double startSec = isImg ? 0.0 : Math.Max(0.0, scene.StartSec);

                            string overlayPng = Path.Combine(tempWorkDir, $"overlay_{i:D2}.png");
                            lock (JsonOpts)
                            {
                                RenderLuxuryOverlayPng(
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

                progress?.Report((72, "Komponiere Speed-Ramp Übergänge, Peak-SFX & Mastering..."));
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

                progress?.Report((94, "Prüfe MP4-Container & FastStart-Atome..."));
                await ValidateMp4PlayabilityAsync(stagedMp4);

                string outName = string.IsNullOrWhiteSpace(project.OutputFileName)
                    ? $"Social_Media_Ready_{project.Name.Replace(' ', '_')}.mp4"
                    : Path.GetFileName(project.OutputFileName);
                if (!outName.EndsWith(".mp4", StringComparison.OrdinalIgnoreCase))
                    outName += ".mp4";

                string finalOutPath = Path.Combine(projDir, outName);
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

        private static async Task RenderSingleNormalizedSegmentAsync(
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
            bool nativeHighFps = mediaInfo.Fps >= 90.0;

            // Dark-Luxury Cinema Color Grade (Obsidian blacks + warm crystal highlights + subtle optical vignette)
            string gradeChain = string.Equals(colorGrade, "natural", StringComparison.OrdinalIgnoreCase)
                ? "setsar=1"
                : "eq=contrast=1.11:brightness=-0.028:saturation=1.08,colorbalance=rs=0.015:gs=0.0:bs=-0.025:rh=0.055:gh=0.022:bh=-0.042,vignette=PI/5.6,unsharp=5:5:0.52:5:5:0.0,setsar=1";

            double readStartSec = startSec;
            double readDurSec = durSec + 0.35;
            string baseVideoFilter;

            string scaleCropFilter = string.Equals(fitMode, "contain_blur", StringComparison.OrdinalIgnoreCase)
                ? $"split=2[bgsrc][fgsrc];[bgsrc]scale={outW}:{outH}:force_original_aspect_ratio=increase:flags=bilinear,crop={outW}:{outH},boxblur=28:4,eq=brightness=-0.18:saturation=0.82[bg];[fgsrc]scale={outW}:{outH}:force_original_aspect_ratio=decrease:flags=lanczos[fg];[bg][fg]overlay=(W-w)/2:(H-h)/2,setsar=1"
                : $"scale={outW}:{outH}:force_original_aspect_ratio=increase:flags=lanczos,crop={outW}:{outH},setsar=1";

            if (isImage)
            {
                int totalFrames = Math.Max(24, (int)Math.Round(durSec * fps));
                int preW = ((int)(outW * 1.18) / 2) * 2;
                int preH = ((int)(outH * 1.18) / 2) * 2;
                string endDip = isFinalScene
                    ? $",fade=t=out:st={Math.Max(0.1, durSec - 0.28).ToString("0.###", CultureInfo.InvariantCulture)}:d=0.26"
                    : "";
                baseVideoFilter =
                    $"[0:v]trim=end_frame=1,scale={preW}:{preH}:force_original_aspect_ratio=increase:flags=lanczos,crop={preW}:{preH}," +
                    $"zoompan=z='min(zoom+0.00072,1.12)':d={totalFrames}:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s={outW}x{outH}:fps={fps}," +
                    $"{gradeChain}{endDip},format=yuv420p[vraw]";
            }
            else if (normSpeed == "speed_ramp" && durSec >= 1.4)
            {
                // 3-Phase Viral Luxury Velocity Curve: Fast 2.4x Whip-In -> Silk Slow-Mo Hero -> Fast 2.4x Whip-Out
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
            }
            else
            {
                if (mediaInfo.DurationSec > 0.6 && readStartSec + durSec > mediaInfo.DurationSec)
                    readStartSec = Math.Max(0.0, mediaInfo.DurationSec - durSec);
                readDurSec = durSec + 0.15;
                baseVideoFilter =
                    $"[0:v]fps={fps},{scaleCropFilter},trim=0:{durStr},setpts=PTS-STARTPTS,{gradeChain},format=yuv420p[vraw]";
            }

            // Optional silky anamorphic rack-defocus when melting into the Mystical Brand Outro
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

            // Pure white editorial text fades in smoothly after entry whip and clears before exit transition
            string ovFadeIn = (normSpeed == "speed_ramp" ? 0.20 : 0.12).ToString("0.###", CultureInfo.InvariantCulture);
            string ovFadeOut = Math.Max(0.32, durSec - (velvetOutroMelt ? 0.85 : 0.30)).ToString("0.###", CultureInfo.InvariantCulture);

            string overlayFilter =
                $"[1:v]format=rgba,fade=t=in:st={ovFadeIn}:d=0.26:alpha=1,fade=t=out:st={ovFadeOut}:d=0.24:alpha=1[ov];" +
                $"[vbase][ov]overlay=0:0:format=auto,scale=out_color_matrix=bt709:out_range=tv,format=yuv420p[vout]";

            string audioFilter = (keepSourceAudio && normSpeed == "linear")
                ? $"[0:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,volume={sourceAudioVol.ToString("0.##", CultureInfo.InvariantCulture)},atrim=0:{durStr},asetpts=PTS-STARTPTS[aout]"
                : $"anullsrc=channel_layout=stereo:sample_rate=48000,atrim=0:{durStr},asetpts=PTS-STARTPTS[aout]";

            string fullComplex = $"{baseVideoFilter};{rackFocusFilter};{overlayFilter};{audioFilter}";

            var psi = new ProcessStartInfo
            {
                FileName = "ffmpeg",
                UseShellExecute = false,
                RedirectStandardError = true,
                RedirectStandardOutput = true,
                CreateNoWindow = true
            };
            psi.ArgumentList.Add("-hide_banner");
            psi.ArgumentList.Add("-loglevel");
            psi.ArgumentList.Add("error");
            psi.ArgumentList.Add("-y");

            if (isImage)
            {
                if (string.Equals(fitMode, "contain_blur", StringComparison.OrdinalIgnoreCase))
                {
                    psi.ArgumentList.Add("-loop");
                    psi.ArgumentList.Add("1");
                    psi.ArgumentList.Add("-t");
                    psi.ArgumentList.Add(durStr);
                }
                psi.ArgumentList.Add("-i");
                psi.ArgumentList.Add(srcMedia);
            }
            else
            {
                if (readStartSec > 0.01)
                {
                    psi.ArgumentList.Add("-ss");
                    psi.ArgumentList.Add(readStartSec.ToString("0.###", CultureInfo.InvariantCulture));
                }
                psi.ArgumentList.Add("-t");
                psi.ArgumentList.Add(readDurSec.ToString("0.###", CultureInfo.InvariantCulture));
                psi.ArgumentList.Add("-i");
                psi.ArgumentList.Add(srcMedia);
            }

            psi.ArgumentList.Add("-loop");
            psi.ArgumentList.Add("1");
            psi.ArgumentList.Add("-t");
            psi.ArgumentList.Add(durStr);
            psi.ArgumentList.Add("-i");
            psi.ArgumentList.Add(overlayPng);

            psi.ArgumentList.Add("-filter_complex");
            psi.ArgumentList.Add(fullComplex);
            psi.ArgumentList.Add("-map");
            psi.ArgumentList.Add("[vout]");
            psi.ArgumentList.Add("-map");
            psi.ArgumentList.Add("[aout]");
            psi.ArgumentList.Add("-t");
            psi.ArgumentList.Add(durStr);
            psi.ArgumentList.Add("-c:v");
            psi.ArgumentList.Add("libx264");
            psi.ArgumentList.Add("-preset");
            psi.ArgumentList.Add("fast");
            psi.ArgumentList.Add("-crf");
            psi.ArgumentList.Add("16");
            psi.ArgumentList.Add("-pix_fmt");
            psi.ArgumentList.Add("yuv420p");
            psi.ArgumentList.Add("-r");
            psi.ArgumentList.Add(fps.ToString(CultureInfo.InvariantCulture));
            psi.ArgumentList.Add("-c:a");
            psi.ArgumentList.Add("pcm_s16le");
            psi.ArgumentList.Add("-f");
            psi.ArgumentList.Add("matroska");
            string mkvPath = Path.ChangeExtension(outSegMp4, ".mkv");
            psi.ArgumentList.Add(mkvPath);

            using var proc = Process.Start(psi) ?? throw new InvalidOperationException("ffmpeg konnte nicht gestartet werden.");
            string err = await proc.StandardError.ReadToEndAsync(ct);
            await proc.WaitForExitAsync(ct);
            if (proc.ExitCode != 0 || !File.Exists(mkvPath))
                throw new InvalidOperationException($"Szenen-Renderfehler: {err}");

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

            // Per-cut transition duration resolution (tight 0.20s beat-cuts on B-roll + silky 0.90s velvet dissolve into Brand Outro!)
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

            var psi = new ProcessStartInfo
            {
                FileName = "ffmpeg",
                UseShellExecute = false,
                RedirectStandardError = true,
                RedirectStandardOutput = true,
                CreateNoWindow = true
            };
            psi.ArgumentList.Add("-hide_banner");
            psi.ArgumentList.Add("-loglevel");
            psi.ArgumentList.Add("error");
            psi.ArgumentList.Add("-y");

            foreach (string seg in segPaths)
            {
                psi.ArgumentList.Add("-i");
                psi.ArgumentList.Add(seg);
            }

            int nextInputIdx = n;
            int? musicInputIdx = null;
            if (!string.IsNullOrWhiteSpace(project.BackgroundMusic))
            {
                string musicFull = Path.IsPathRooted(project.BackgroundMusic)
                    ? project.BackgroundMusic
                    : Path.Combine(SoundsRoot, project.BackgroundMusic);
                if (File.Exists(musicFull))
                {
                    if (project.MusicStartSec > 0.02)
                    {
                        psi.ArgumentList.Add("-ss");
                        psi.ArgumentList.Add(project.MusicStartSec.ToString("0.###", CultureInfo.InvariantCulture));
                    }
                    psi.ArgumentList.Add("-stream_loop");
                    psi.ArgumentList.Add("-1");
                    psi.ArgumentList.Add("-t");
                    psi.ArgumentList.Add(totalDurStr);
                    psi.ArgumentList.Add("-i");
                    psi.ArgumentList.Add(musicFull);
                    musicInputIdx = nextInputIdx++;
                }
            }

            // (InputIdx, DelayMs, MaxPlaySec, FadeOutDurSec, Gain)
            var sfxEvents = new List<(int InputIdx, int DelayMs, double MaxPlaySec, double FadeOutSec, double Gain)>();

            void SchedulePeakAlignedSfx(string? relOrFull, double targetPeakTimelineSec, double gainMul, bool hardCutAfterPeak = false, double maxTailAfterPeak = 2.6)
            {
                if (string.IsNullOrWhiteSpace(relOrFull)) return;
                string full = Path.IsPathRooted(relOrFull) ? relOrFull : Path.Combine(SoundsRoot, relOrFull);
                if (!File.Exists(full)) return;

                var (peakSec, fileDur) = GetAudioPeakEnvelopeInfo(full);
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
                    psi.ArgumentList.Add("-ss");
                    psi.ArgumentList.Add(trimInFile.ToString("0.###", CultureInfo.InvariantCulture));
                }
                psi.ArgumentList.Add("-t");
                psi.ArgumentList.Add(playDur.ToString("0.###", CultureInfo.InvariantCulture));
                psi.ArgumentList.Add("-i");
                psi.ArgumentList.Add(full);

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

            psi.ArgumentList.Add("-filter_complex");
            psi.ArgumentList.Add(sb.ToString());
            psi.ArgumentList.Add("-map");
            psi.ArgumentList.Add("[vmaster]");
            psi.ArgumentList.Add("-map");
            psi.ArgumentList.Add("[amaster]");
            psi.ArgumentList.Add("-t");
            psi.ArgumentList.Add(totalDurStr);

            // Strict WhatsApp / Instagram Reels / TikTok / Windows Explorer H.264 Main + AAC faststart compliance
            psi.ArgumentList.Add("-c:v");
            psi.ArgumentList.Add("libx264");
            psi.ArgumentList.Add("-preset");
            psi.ArgumentList.Add("medium");
            psi.ArgumentList.Add("-crf");
            psi.ArgumentList.Add("16");
            psi.ArgumentList.Add("-aq-mode");
            psi.ArgumentList.Add("3");
            psi.ArgumentList.Add("-aq-strength");
            psi.ArgumentList.Add("1.15");
            psi.ArgumentList.Add("-profile:v");
            psi.ArgumentList.Add("main");
            psi.ArgumentList.Add("-pix_fmt");
            psi.ArgumentList.Add("yuv420p");
            psi.ArgumentList.Add("-color_range");
            psi.ArgumentList.Add("tv");
            psi.ArgumentList.Add("-colorspace");
            psi.ArgumentList.Add("bt709");
            psi.ArgumentList.Add("-color_primaries");
            psi.ArgumentList.Add("bt709");
            psi.ArgumentList.Add("-color_trc");
            psi.ArgumentList.Add("bt709");
            psi.ArgumentList.Add("-r");
            psi.ArgumentList.Add(fps.ToString(CultureInfo.InvariantCulture));
            psi.ArgumentList.Add("-c:a");
            psi.ArgumentList.Add("aac");
            psi.ArgumentList.Add("-b:a");
            psi.ArgumentList.Add("256k");
            psi.ArgumentList.Add("-ar");
            psi.ArgumentList.Add("48000");
            psi.ArgumentList.Add("-ac");
            psi.ArgumentList.Add("2");
            psi.ArgumentList.Add("-movflags");
            psi.ArgumentList.Add("+faststart");
            psi.ArgumentList.Add(stagedMp4);

            using var proc = Process.Start(psi) ?? throw new InvalidOperationException("ffmpeg Master-Export konnte nicht gestartet werden.");
            string err = await proc.StandardError.ReadToEndAsync(ct);
            await proc.WaitForExitAsync(ct);
            if (proc.ExitCode != 0 || !File.Exists(stagedMp4))
                throw new InvalidOperationException($"Master-Renderfehler: {err}");
        }

        public static async Task ValidateMp4PlayabilityAsync(string mp4Path)
        {
            var fi = new FileInfo(mp4Path);
            if (!fi.Exists || fi.Length < 4096)
                throw new InvalidOperationException("Gerendertes Video ist leer oder unvollständig.");

            byte[] head = new byte[Math.Min(65536, (int)fi.Length)];
            await using (var fs = new FileStream(mp4Path, FileMode.Open, FileAccess.Read, FileShare.Read))
            {
                _ = await fs.ReadAsync(head, 0, head.Length);
            }
            string ascii = Encoding.Latin1.GetString(head);
            if (!ascii.Contains("ftyp", StringComparison.Ordinal) || !ascii.Contains("moov", StringComparison.Ordinal))
                throw new InvalidOperationException("MP4 FastStart-Header (ftyp/moov) fehlt am Dateianfang.");

            var info = await ProbeMediaFileAsync(mp4Path);
            if (info.Width <= 0 || info.Height <= 0 || info.DurationSec < 0.5 || !info.HasAudio)
                throw new InvalidOperationException("MP4 Stream-Prüfung fehlgeschlagen (Video/Audio unvollständig).");
        }

        // =====================================================================
        // CLI & NAMED PIPE IPC AUTOMATION DISPATCHER (USED BY FUNKENWERK MCP)
        // =====================================================================
        public static async Task<string> ExecuteCliCommandJsonAsync(string jsonRequest)
        {
            try
            {
                var req = JsonNode.Parse(jsonRequest)?.AsObject() ?? throw new ArgumentException("Ungültiges JSON.");
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
                        req["enableControlBridge"]?.GetValue<bool>() ?? true),

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
            string projDir = GetProjectDirectory(projName);
            var copied = new List<object>();

            if (req["sourcePaths"] is JsonArray arr)
            {
                foreach (var node in arr)
                {
                    string? src = node?.GetValue<string>();
                    if (string.IsNullOrWhiteSpace(src) || !File.Exists(src)) continue;
                    string ext = Path.GetExtension(src);
                    if (!VideoExts.Contains(ext) && !ImageExts.Contains(ext)) continue;

                    string dest = Path.Combine(projDir, Path.GetFileName(src));
                    File.Copy(src, dest, overwrite: true);
                    var info = await ProbeMediaFileAsync(dest);
                    copied.Add(new { info.FileName, info.MediaType, info.EffectiveWidth, info.EffectiveHeight, info.Fps, info.DurationSec, info.SizeMb });
                }
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

        public static async Task<int> RunSelfTestAsync()
        {
            Console.WriteLine("=== FUNKENWERK CRAFT SELF-TEST ===");
            bool ok = Directory.Exists(WorkspaceRoot)
                && File.Exists(ArtCraftExe)
                && File.Exists(FilmCraftExe)
                && File.Exists(FilmCraftCliExe)
                && File.Exists(SoundCraftExe)
                && ListSoundPool().Count >= 20;
            Console.WriteLine($"Workspace={WorkspaceRoot} | ArtCraft={File.Exists(ArtCraftExe)} | FilmCraft={File.Exists(FilmCraftExe)} | SoundPool={ListSoundPool().Count}");
            await Task.CompletedTask;
            return ok ? 0 : 1;
        }
    }
}
