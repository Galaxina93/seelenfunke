using System;
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
        public string MediaType { get; set; } = "video"; // "video" | "image"
        public double StartSec { get; set; } = 0.0;
        public double DurationSec { get; set; } = 3.2;
        public string FitMode { get; set; } = "fill"; // "fill" | "contain_blur"
        public string OverlayBadge { get; set; } = "";
        public string OverlayTitle { get; set; } = "";
        public string OverlaySubtitle { get; set; } = "";
        public string Transition { get; set; } = "smoothleft"; // "smoothleft" | "fadeblack" | "dissolve" | "circleopen" | "wiperight"
        public string TransitionSfx { get; set; } = @"Uebergaenge_Whoosh\Whoosh_Klassisch_Schnell_01.wav";
        public bool KeepOriginalAudio { get; set; } = false;
        public double OriginalAudioVolume { get; set; } = 0.25;
    }

    public sealed class CraftProjectModel
    {
        public string Name { get; set; } = "Projekt 1";
        public string AspectRatio { get; set; } = "9:16"; // "9:16" | "1:1" | "16:9"
        public int Fps { get; set; } = 60;
        public double TransitionDurationSec { get; set; } = 0.32;
        public string BackgroundMusic { get; set; } = @"Beats_und_Ambient\Emotion_Romantic_Inspiration_CC0.mp3";
        public double MusicStartSec { get; set; } = 0.0;
        public double MusicVolume { get; set; } = 0.38;
        public double SfxVolume { get; set; } = 0.68;
        public string IntroAccentSfx { get; set; } = @"Effekte_Akzente\Kristall_Klang_Fein_01.wav";
        public string OutroAccentSfx { get; set; } = @"Effekte_Akzente\Gold_Funken_Reveal_01.wav";
        public bool ShowBrandHeader { get; set; } = true;
        public string BrandHeaderText { get; set; } = "MEIN SEELENFUNKE · MANUFAKTUR GIFHORN";
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

            // Auto-populate scenes from any media files already placed in the project folder
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
                    DurationSec = isImg ? 2.8 : 3.4,
                    FitMode = "fill",
                    Transition = "smoothleft"
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
            foreach (var s in project.Scenes)
            {
                s.OverlayBadge = SanitizeCleanText(s.OverlayBadge);
                s.OverlayTitle = SanitizeCleanText(s.OverlayTitle);
                s.OverlaySubtitle = SanitizeCleanText(s.OverlaySubtitle);
                s.DurationSec = Math.Clamp(s.DurationSec, 0.8, 60.0);
                s.StartSec = Math.Max(0.0, s.StartSec);
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
                info.DurationSec = Math.Round(dSec, 2);
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
                    audio = 3
                }
            }));

            double cursorSec = 0.0;
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
                            size = 64,
                            seconds = scene.DurationSec,
                            position = new[] { targetW / 2, (int)(targetH * 0.76) }
                        }
                    }));
                }
                cursorSec += scene.DurationSec;
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
        // SOCIAL MEDIA VIDEO RENDER PIPELINE (1080x1920 60FPS H.264 + AAC + SFX)
        // =====================================================================
        public static (int Width, int Height) ResolveDimensions(string aspect) => aspect switch
        {
            "1:1" => (1080, 1080),
            "16:9" => (1920, 1080),
            _ => (1080, 1920)
        };

        private static void RenderLuxuryOverlayPng(
            string outputPngPath,
            int width,
            int height,
            bool showBrandHeader,
            string brandHeaderText,
            CraftSceneItem scene)
        {
            using var bmp = new Bitmap(width, height, PixelFormat.Format32bppArgb);
            using var g = Graphics.FromImage(bmp);
            g.Clear(Color.Transparent);
            g.SmoothingMode = SmoothingMode.AntiAlias;
            g.TextRenderingHint = TextRenderingHint.AntiAliasGridFit;
            g.CompositingQuality = CompositingQuality.HighQuality;

            Color gold = Color.FromArgb(255, 197, 160, 89);       // #C5A059
            Color goldSoft = Color.FromArgb(190, 197, 160, 89);
            Color cardBg = Color.FromArgb(218, 13, 15, 21);       // Matte dark glass
            Color white = Color.FromArgb(250, 249, 250, 251);
            Color lightGray = Color.FromArgb(230, 209, 213, 219);

            // Subtle top & bottom cinematic gradient vignette for legibility
            using (var topGrad = new LinearGradientBrush(
                new Rectangle(0, 0, width, (int)(height * 0.16)),
                Color.FromArgb(125, 8, 10, 14),
                Color.FromArgb(0, 8, 10, 14),
                LinearGradientMode.Vertical))
            {
                g.FillRectangle(topGrad, 0, 0, width, (int)(height * 0.16));
            }

            using (var botGrad = new LinearGradientBrush(
                new Rectangle(0, (int)(height * 0.62), width, height - (int)(height * 0.62)),
                Color.FromArgb(0, 8, 10, 14),
                Color.FromArgb(165, 8, 10, 14),
                LinearGradientMode.Vertical))
            {
                g.FillRectangle(botGrad, 0, (int)(height * 0.62), width, height - (int)(height * 0.62));
            }

            // 1. Optional Top Brand Pill (Safe below Instagram/TikTok status bar: Y = 6.5%)
            string headerTxt = SanitizeCleanText(brandHeaderText);
            if (showBrandHeader && !string.IsNullOrWhiteSpace(headerTxt))
            {
                using var hFont = new Font("Segoe UI", width >= 1080 ? 19f : 15f, FontStyle.Bold, GraphicsUnit.Pixel);
                SizeF hSize = g.MeasureString(headerTxt, hFont);
                float pillW = hSize.Width + 46f;
                float pillH = hSize.Height + 20f;
                float pillX = (width - pillW) / 2f;
                float pillY = height * 0.062f;

                using var pillPath = CreateRoundedRect(pillX, pillY, pillW, pillH, pillH / 2f);
                using var pillBrush = new SolidBrush(Color.FromArgb(196, 13, 15, 21));
                using var pillPen = new Pen(goldSoft, 1.8f);
                g.FillPath(pillBrush, pillPath);
                g.DrawPath(pillPen, pillPath);

                using var goldBrush = new SolidBrush(gold);
                var sfCenter = new StringFormat { Alignment = StringAlignment.Center, LineAlignment = StringAlignment.Center };
                g.DrawString(headerTxt, hFont, goldBrush, new RectangleF(pillX, pillY + 1f, pillW, pillH), sfCenter);
            }

            // 2. Scene Caption Card (Safe above Reels/TikTok bottom caption & right icons: centered around Y = 75%)
            string badge = SanitizeCleanText(scene.OverlayBadge);
            string title = SanitizeCleanText(scene.OverlayTitle);
            string sub = SanitizeCleanText(scene.OverlaySubtitle);

            if (!string.IsNullOrWhiteSpace(title) || !string.IsNullOrWhiteSpace(sub))
            {
                float maxCardW = width * 0.82f;
                float textPadX = 38f;
                float availTextW = maxCardW - (textPadX * 2f);

                using var badgeFont = new Font("Segoe UI", 21f, FontStyle.Bold, GraphicsUnit.Pixel);
                using var titleFont = new Font("Segoe UI", 42f, FontStyle.Bold, GraphicsUnit.Pixel);
                using var subFont = new Font("Segoe UI", 27f, FontStyle.Regular, GraphicsUnit.Pixel);

                var sfWrap = new StringFormat
                {
                    Alignment = StringAlignment.Center,
                    LineAlignment = StringAlignment.Near,
                    Trimming = StringTrimming.Word
                };

                SizeF badgeSz = string.IsNullOrWhiteSpace(badge) ? SizeF.Empty : g.MeasureString(badge.ToUpperInvariant(), badgeFont, (int)availTextW, sfWrap);
                SizeF titleSz = string.IsNullOrWhiteSpace(title) ? SizeF.Empty : g.MeasureString(title, titleFont, (int)availTextW, sfWrap);
                SizeF subSz = string.IsNullOrWhiteSpace(sub) ? SizeF.Empty : g.MeasureString(sub, subFont, (int)availTextW, sfWrap);

                float contentW = Math.Max(titleSz.Width, Math.Max(subSz.Width, badgeSz.Width));
                float cardW = Math.Clamp(contentW + (textPadX * 2f), width * 0.48f, maxCardW);
                float cardH = 34f;
                if (!badgeSz.IsEmpty) cardH += badgeSz.Height + 10f;
                if (!titleSz.IsEmpty) cardH += titleSz.Height + 6f;
                if (!subSz.IsEmpty) cardH += subSz.Height + 8f;
                cardH += 18f;

                float cardX = (width - cardW) / 2f;
                float cardY = (height * 0.77f) - (cardH / 2f);

                using var cardPath = CreateRoundedRect(cardX, cardY, cardW, cardH, 24f);
                using var bgBrush = new SolidBrush(cardBg);
                using var borderPen = new Pen(gold, 2.4f);
                g.FillPath(bgBrush, cardPath);
                g.DrawPath(borderPen, cardPath);

                // Subtle top gold accent bar inside card
                using var barBrush = new SolidBrush(gold);
                g.FillRectangle(barBrush, (width / 2f) - 36f, cardY + 12f, 72f, 3.5f);

                float curY = cardY + 26f;
                if (!badgeSz.IsEmpty)
                {
                    g.DrawString(badge.ToUpperInvariant(), badgeFont, barBrush, new RectangleF(cardX + textPadX, curY, cardW - textPadX * 2f, badgeSz.Height + 6f), sfWrap);
                    curY += badgeSz.Height + 8f;
                }
                if (!titleSz.IsEmpty)
                {
                    using var tBrush = new SolidBrush(white);
                    g.DrawString(title, titleFont, tBrush, new RectangleF(cardX + textPadX, curY, cardW - textPadX * 2f, titleSz.Height + 8f), sfWrap);
                    curY += titleSz.Height + 8f;
                }
                if (!subSz.IsEmpty)
                {
                    using var sBrush = new SolidBrush(lightGray);
                    g.DrawString(sub, subFont, sBrush, new RectangleF(cardX + textPadX, curY, cardW - textPadX * 2f, subSz.Height + 8f), sfWrap);
                }
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
            double transDur = project.Scenes.Count > 1 ? Math.Clamp(project.TransitionDurationSec, 0.15, 0.8) : 0.0;

            string tempWorkDir = Path.Combine(Path.GetTempPath(), $"fw_craft_render_{Guid.NewGuid():N}");
            Directory.CreateDirectory(tempWorkDir);

            try
            {
                // Also keep FilmCraft native .fcproj / .jsonl up to date
                progress?.Report((5, "Synchronisiere FilmCraft-Projektstruktur..."));
                await ExportFilmCraftNativeProjectAsync(project);

                var segmentPaths = new List<string>();
                var sceneDurations = new List<double>();

                for (int i = 0; i < project.Scenes.Count; i++)
                {
                    ct.ThrowIfCancellationRequested();
                    var scene = project.Scenes[i];
                    string srcMedia = ResolveSceneMediaPath(project.Name, scene.FileName);
                    if (!File.Exists(srcMedia))
                        throw new FileNotFoundException($"Quelldatei für Szene {i + 1} fehlt: {srcMedia}");

                    var mediaInfo = await ProbeMediaFileAsync(srcMedia);
                    bool isImg = mediaInfo.MediaType == "image";
                    double dur = Math.Clamp(scene.DurationSec, 1.0, 45.0);
                    double startSec = isImg ? 0.0 : Math.Max(0.0, scene.StartSec);
                    if (!isImg && mediaInfo.DurationSec > 0.5 && startSec + dur > mediaInfo.DurationSec)
                    {
                        if (startSec >= mediaInfo.DurationSec - 0.5) startSec = 0;
                        dur = Math.Max(1.0, Math.Min(dur, mediaInfo.DurationSec - startSec));
                    }
                    sceneDurations.Add(dur);

                    double pct = 10 + ((double)i / project.Scenes.Count) * 48.0;
                    progress?.Report((pct, $"Verarbeite Szene {i + 1}/{project.Scenes.Count}..."));

                    string overlayPng = Path.Combine(tempWorkDir, $"overlay_{i:D2}.png");
                    RenderLuxuryOverlayPng(overlayPng, outW, outH, project.ShowBrandHeader, project.BrandHeaderText, scene);

                    string segPath = Path.Combine(tempWorkDir, $"seg_{i:D2}.mp4");
                    await RenderSingleNormalizedSegmentAsync(
                        srcMedia,
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
                        ct);

                    segmentPaths.Add(segPath);
                }

                progress?.Report((65, "Komponiere Übergänge, CC0-Soundpool & Mastering..."));
                string stagedMp4 = Path.Combine(tempWorkDir, "master_staged.mp4");

                await ComposeMasterTimelineWithAudioAsync(
                    project,
                    segmentPaths,
                    sceneDurations,
                    transDur,
                    outW,
                    outH,
                    fps,
                    stagedMp4,
                    ct);

                progress?.Report((92, "Prüfe MP4-Container & FastStart-Atome..."));
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
            CancellationToken ct)
        {
            string durStr = durSec.ToString("0.###", CultureInfo.InvariantCulture);
            string startStr = startSec.ToString("0.###", CultureInfo.InvariantCulture);
            string fadeOutStart = Math.Max(0.05, durSec - 0.25).ToString("0.###", CultureInfo.InvariantCulture);

            string baseVideoFilter;
            if (string.Equals(fitMode, "contain_blur", StringComparison.OrdinalIgnoreCase))
            {
                // Luxury blurred 9:16 ambient backdrop + sharp framed foreground
                baseVideoFilter =
                    $"[0:v]fps={fps},format=yuv420p,split=2[bgsrc][fgsrc];" +
                    $"[bgsrc]scale={outW}:{outH}:force_original_aspect_ratio=increase:flags=bilinear,crop={outW}:{outH},boxblur=28:4,eq=brightness=-0.16:saturation=0.85[bg];" +
                    $"[fgsrc]scale={outW}:{outH}:force_original_aspect_ratio=decrease:flags=lanczos[fg];" +
                    $"[bg][fg]overlay=(W-w)/2:(H-h)/2,setsar=1[vbase]";
            }
            else if (isImage)
            {
                // Subtle smooth 60fps Ken-Burns push-in on single high-res photo frame
                int totalFrames = Math.Max(24, (int)Math.Round(durSec * fps));
                int preW = ((int)(outW * 1.14) / 2) * 2;
                int preH = ((int)(outH * 1.14) / 2) * 2;
                baseVideoFilter =
                    $"[0:v]trim=end_frame=1,scale={preW}:{preH}:force_original_aspect_ratio=increase:flags=lanczos,crop={preW}:{preH}," +
                    $"zoompan=z='min(zoom+0.00045,1.08)':d={totalFrames}:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s={outW}x{outH}:fps={fps}," +
                    $"setsar=1,format=yuv420p[vbase]";
            }
            else
            {
                baseVideoFilter =
                    $"[0:v]fps={fps},scale={outW}:{outH}:force_original_aspect_ratio=increase:flags=lanczos,crop={outW}:{outH},setsar=1,format=yuv420p[vbase]";
            }

            string overlayFilter =
                $"[1:v]format=rgba,fade=t=in:st=0.06:d=0.24:alpha=1,fade=t=out:st={fadeOutStart}:d=0.22:alpha=1[ov];" +
                $"[vbase][ov]overlay=0:0:format=auto,scale=out_color_matrix=bt709:out_range=tv,format=yuv420p[vout]";

            string audioFilter = keepSourceAudio
                ? $"[0:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,volume={sourceAudioVol.ToString("0.##", CultureInfo.InvariantCulture)},atrim=0:{durStr},asetpts=PTS-STARTPTS[aout]"
                : $"anullsrc=channel_layout=stereo:sample_rate=48000,atrim=0:{durStr},asetpts=PTS-STARTPTS[aout]";

            string fullComplex = $"{baseVideoFilter};{overlayFilter};{audioFilter}";

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
                if (startSec > 0.01)
                {
                    psi.ArgumentList.Add("-ss");
                    psi.ArgumentList.Add(startStr);
                }
                psi.ArgumentList.Add("-t");
                psi.ArgumentList.Add(durStr);
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
            double transDur,
            int outW,
            int outH,
            int fps,
            string stagedMp4,
            CancellationToken ct)
        {
            int n = segPaths.Count;
            double totalVideoDur = durs.Sum() - Math.Max(0, n - 1) * transDur;
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

            // Inputs 0 .. n-1: scene segments
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
                    if (project.MusicStartSec > 0.05)
                    {
                        psi.ArgumentList.Add("-ss");
                        psi.ArgumentList.Add(project.MusicStartSec.ToString("0.##", CultureInfo.InvariantCulture));
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

            // Collect SFX events: (inputIdx, delayMs, gain)
            var sfxEvents = new List<(int InputIdx, int DelayMs, double Gain)>();

            void TryAddSfx(string? relOrFull, double atSeconds, double gainMul)
            {
                if (string.IsNullOrWhiteSpace(relOrFull)) return;
                string full = Path.IsPathRooted(relOrFull) ? relOrFull : Path.Combine(SoundsRoot, relOrFull);
                if (!File.Exists(full)) return;
                psi.ArgumentList.Add("-i");
                psi.ArgumentList.Add(full);
                int delayMs = Math.Max(0, (int)Math.Round(atSeconds * 1000.0));
                sfxEvents.Add((nextInputIdx++, delayMs, project.SfxVolume * gainMul));
            }

            // Intro crystal hook chime
            TryAddSfx(project.IntroAccentSfx, 0.08, 0.75);

            // Video xfade & transition SFX cues
            var sb = new StringBuilder();
            var cutOffsets = new List<double>();
            double accum = 0.0;
            for (int k = 1; k < n; k++)
            {
                accum += durs[k - 1] - transDur;
                double offset = Math.Max(0.05, accum);
                cutOffsets.Add(offset);
                string sfxRel = project.Scenes[k - 1].TransitionSfx;
                TryAddSfx(sfxRel, Math.Max(0.0, offset - 0.06), 0.9);
            }

            // Final reveal sparkle on the last scene
            if (n >= 2 && !string.IsNullOrWhiteSpace(project.OutroAccentSfx))
            {
                double lastStart = cutOffsets.Last() + 0.18;
                if (lastStart < totalVideoDur - 0.4)
                    TryAddSfx(project.OutroAccentSfx, lastStart, 0.85);
            }

            // Build video xfade chain
            if (n == 1)
            {
                sb.Append("[0:v]null[vmaster];[0:a]anull[aseg];");
            }
            else
            {
                string prevV = "0:v";
                string prevA = "0:a";
                string tdStr = transDur.ToString("0.###", CultureInfo.InvariantCulture);

                for (int k = 1; k < n; k++)
                {
                    string rawTr = project.Scenes[k - 1].Transition?.Trim().ToLowerInvariant() ?? "smoothleft";
                    string xfadeName = rawTr switch
                    {
                        "fadeblack" => "fadeblack",
                        "dissolve" => "fade",
                        "circleopen" => "circleopen",
                        "wiperight" => "wiperight",
                        "smoothup" => "smoothup",
                        _ => "smoothleft"
                    };
                    string offStr = cutOffsets[k - 1].ToString("0.###", CultureInfo.InvariantCulture);
                    string outV = (k == n - 1) ? "vmaster" : $"vx{k}";
                    string outA = (k == n - 1) ? "aseg" : $"ax{k}";

                    sb.Append($"[{prevV}][{k}:v]xfade=transition={xfadeName}:duration={tdStr}:offset={offStr}[{outV}];");
                    sb.Append($"[{prevA}][{k}:a]acrossfade=d={tdStr}:c1=tri:c2=tri[{outA}];");
                    prevV = outV;
                    prevA = outA;
                }
            }

            // Build audio mix chain (segment audio + music bed + transition/hook SFX)
            var mixInputs = new List<string> { "[aseg]" };

            if (musicInputIdx.HasValue)
            {
                string mVol = Math.Clamp(project.MusicVolume, 0.0, 1.5).ToString("0.##", CultureInfo.InvariantCulture);
                string fadeOutSt = Math.Max(0.2, totalVideoDur - 1.5).ToString("0.###", CultureInfo.InvariantCulture);
                sb.Append($"[{musicInputIdx.Value}:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,volume={mVol},afade=t=in:st=0:d=0.6,afade=t=out:st={fadeOutSt}:d=1.4,atrim=0:{totalDurStr},asetpts=PTS-STARTPTS[amusic];");
                mixInputs.Add("[amusic]");
            }

            for (int s = 0; s < sfxEvents.Count; s++)
            {
                var ev = sfxEvents[s];
                string gStr = Math.Clamp(ev.Gain, 0.05, 1.5).ToString("0.##", CultureInfo.InvariantCulture);
                sb.Append($"[{ev.InputIdx}:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,volume={gStr},adelay={ev.DelayMs}|{ev.DelayMs}[asfx{s}];");
                mixInputs.Add($"[asfx{s}]");
            }

            if (mixInputs.Count == 1)
            {
                sb.Append($"[aseg]loudnorm=I=-16:TP=-1.5:LRA=11,atrim=0:{totalDurStr},asetpts=PTS-STARTPTS[amaster]");
            }
            else
            {
                sb.Append($"{string.Join("", mixInputs)}amix=inputs={mixInputs.Count}:duration=first:dropout_transition=0:normalize=0,loudnorm=I=-16:TP=-1.5:LRA=11,atrim=0:{totalDurStr},asetpts=PTS-STARTPTS[amaster]");
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
            psi.ArgumentList.Add("18");
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
            psi.ArgumentList.Add("192k");
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
                if (req["backgroundMusic"] != null) proj.BackgroundMusic = req["backgroundMusic"]!.GetValue<string>();
                if (req["musicVolume"] != null) proj.MusicVolume = req["musicVolume"]!.GetValue<double>();
                if (req["sfxVolume"] != null) proj.SfxVolume = req["sfxVolume"]!.GetValue<double>();
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
                    copied.Add(new { info.FileName, info.MediaType, info.EffectiveWidth, info.EffectiveHeight, info.DurationSec, info.SizeMb });
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
                        DurationSec = isImg ? 2.8 : 3.2,
                        FitMode = "fill",
                        Transition = "smoothleft"
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
