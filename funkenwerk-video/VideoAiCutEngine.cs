using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;

namespace FunkenwerkVideo
{
    public class RawVideoClipInfo
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N");
        public string FilePath { get; set; } = "";
        public string FileName => Path.GetFileName(FilePath);
        public long FileSizeBytes { get; set; }
        public long LastWriteTicks { get; set; }
        public DateTime CreatedAt { get; set; }
        public double DurationSeconds { get; set; }
        public int Width { get; set; }
        public int Height { get; set; }
        public double Fps { get; set; } = 30.0;
        public bool HasAudio { get; set; } = true;
        public string ThumbnailPath { get; set; } = "";
        public bool IsIncluded { get; set; } = true;
        public bool IsAnalyzed { get; set; }
        public bool IsBadTakeWarning { get; set; }

        // AI Frame & Audio Analysis Results (100% based on real camera signal)
        public double TrimStartSec { get; set; }
        public double TrimEndSec { get; set; }
        public double BestHookStartSec { get; set; }
        public double BestProcessStartSec { get; set; }
        public double BestRevealStartSec { get; set; }
        public double BestAsmrTriggerSec { get; set; }
        public int QualityScore { get; set; } = 88;
        public double AvgMotionScore { get; set; }
        public double PeakContrastScore { get; set; }
        public double PeakAudioDb { get; set; } = -24.0;
        public string AiBadgeText { get; set; } = "Bereit zur Analyse";
        public string AiDetailSummary { get; set; } = "";
        public List<SpeechRangeDto> NonSilentSpeechRanges { get; set; } = new();
        public List<ClipWindowScore> WindowScores { get; set; } = new();

        public string ResolutionLabel => Width > 0 && Height > 0 ? $"{Width}×{Height}" : "HD";
        public string DurationLabel => FormatSeconds(DurationSeconds);
        public string FileSizeLabel => $"{(FileSizeBytes / (1024.0 * 1024.0)):0.1} MB";

        public static string FormatSeconds(double sec)
        {
            if (sec <= 0) return "0:00.0";
            int m = (int)(sec / 60);
            double s = sec - (m * 60);
            return $"{m}:{s:00.0}";
        }
    }

    public class SpeechRangeDto
    {
        public double StartSec { get; set; }
        public double EndSec { get; set; }

        public SpeechRangeDto() { }
        public SpeechRangeDto(double startSec, double endSec)
        {
            StartSec = startSec;
            EndSec = endSec;
        }
    }

    public class ClipWindowScore
    {
        public double StartSec { get; set; }
        public double Contrast { get; set; }
        public double Motion { get; set; }
        public double Brightness { get; set; }
        public double Saturation { get; set; }
        public double AudioRmsDb { get; set; } = -35.0;
        public double AudioTransientBonus { get; set; }
        public double HookFitness { get; set; }
        public double ProcessFitness { get; set; }
        public double RevealFitness { get; set; }
        public double PackagingAsmrFitness { get; set; }
    }

    public class CutDecisionItem
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N");
        public int Index { get; set; }
        public string SourceClipId { get; set; } = "";
        public string SourceFilePath { get; set; } = "";
        public string SourceFileName => Path.GetFileName(SourceFilePath);
        public string ThumbnailPath { get; set; } = "";
        public bool SourceHasAudio { get; set; } = true;

        public double InPointSec { get; set; }
        public double DurationSec { get; set; }
        public double OutPointSec => Math.Round(InPointSec + DurationSec, 2);
        public double SourceClipTotalDuration { get; set; }

        public double TimelineStartSec { get; set; }
        public double TimelineEndSec => Math.Round(TimelineStartSec + DurationSec, 2);

        public string RoleKey { get; set; } = "process"; // "teaser_hook", "hook", "statement", "process", "asmr_step", "drop", "reveal", "speech"
        public string RoleBadge { get; set; } = "🔥 PROZESS";
        public string AiCutReason { get; set; } = "";
        public int Score { get; set; } = 90;

        // Smart Transition, Framing & Speed Controls (Viral Packaging & Studio NLE Logic)
        public string TransitionType { get; set; } = "hard_action"; // "hard_action", "punch_zoom", "velvet_dip", "whip_flow", "flash_luma"
        public double ZoomFactor { get; set; } = 1.00; // 1.00 = Wide/Standard, 1.08 = 2-Cam Macro Punch-In
        public double SpeedFactor { get; set; } = 1.00; // 0.88 = Luxury Slow-Mo, 1.00 = Normal, 1.15 = Snappy Flow
        public bool EnableJLCAudioOverlap { get; set; } = true; // Smooth 80ms J/L audio bridge

        public string TransitionBadgeLabel => TransitionType switch
        {
            "punch_zoom" => $"🔍 2-Cam Makro ({ZoomFactor:0.00}x)",
            "velvet_dip" => "🖤 Samt-Blende",
            "whip_flow" => "💨 Whip-Flow",
            "flash_luma" => "✨ Licht-Impuls",
            _ => ZoomFactor > 1.02 ? $"⚡ Action-Cut + {ZoomFactor:0.00}x" : "⚡ Action-Cut (J/L-Ton)"
        };

        public string SpeedBadgeLabel => Math.Abs(SpeedFactor - 1.0) < 0.03
            ? "1.0x Echtzeit"
            : (SpeedFactor < 1.0 ? $"💎 {SpeedFactor:0.00}x Smooth" : $"⚡ {SpeedFactor:0.00}x Dynamik");

        public string TimeRangeLabel => $"{RawVideoClipInfo.FormatSeconds(InPointSec)} → {RawVideoClipInfo.FormatSeconds(OutPointSec)} ({DurationSec:0.0}s)";
        public string TimelinePositionLabel => $"Video {TimelineStartSec:0.0}s – {TimelineEndSec:0.0}s";
    }

    public class ViralScoreReport
    {
        public int OverallViralScore { get; set; } = 94;
        public int HookRetentionScore { get; set; } = 95;
        public int PacingRhythmScore { get; set; } = 94;
        public int TransitionFlowScore { get; set; } = 93;
        public int AsmrAudioScore { get; set; } = 92;
        public double AverageCutSeconds { get; set; } = 2.0;
        public string VerdictBadge { get; set; } = "🔥 EXZELLENTES VIRAL-SETUP (94%)";
        public string PacingSummary { get; set; } = "";
    }

    public class SeelenfunkeMusicTrackItem
    {
        public string Id { get; set; } = "";
        public string Title { get; set; } = "";
        public string CategoryLabel { get; set; } = "";
        public int Bpm { get; set; } = 92;
        public double ShimmerDropSec { get; set; } = 4.0;
        public string KeySignature { get; set; } = "D-Moll";
        public string FileName { get; set; } = "";
        public string FullPath { get; set; } = "";
        public string Description { get; set; } = "";

        public double BeatIntervalSec => Bpm > 0 ? 60.0 / Bpm : 0.65;
        public string DisplayLabel => string.IsNullOrEmpty(FileName)
            ? Title
            : $"🎵 {Title} ({Bpm} BPM · Drop @ {ShimmerDropSec:0.0}s)";
    }

    public class VideoRenderOptions
    {
        public string DirectorModeKey { get; set; } = "packaging_pov";
        public string AspectRatioMode { get; set; } = "9_16_crop"; // "9_16_crop", "9_16_pad", "16_9"
        public string ColorGradingMode { get; set; } = "atelier_gold"; // "none", "atelier_gold", "crystal_bright", "matte_luxury"
        public string TransitionMode { get; set; } = "auto_director"; // "auto_director", "hard_action", "punch_zoom", "velvet_dip", "whip_flow", "flash_luma"
        public double TargetTotalDurationSec { get; set; } = 15.0;
        public string? MusicFilePath { get; set; }
        public double MusicVolumePercent { get; set; } = 75.0;
        public double OriginalAudioVolumePercent { get; set; } = 35.0;
        public bool SnapCutsToBeat { get; set; } = true;
        public bool AutoTrimCameraShake { get; set; } = true;
        public bool EnableAsmrAudioMastering { get; set; } = true;
        public string OutputFilePath { get; set; } = "";
    }

    public static class VideoAiCutEngine
    {
        private static string? _ffmpegPath;
        private static string? _ffprobePath;
        private static readonly object _cacheLock = new();

        public static readonly HashSet<string> AllowedVideoExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".mp4", ".mov", ".mkv", ".avi", ".webm", ".m4v"
        };

        public static readonly HashSet<string> AllowedAudioExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".m4a", ".mp3", ".wav", ".aac", ".flac", ".ogg"
        };

        // =========================================================================
        // SECURITY & PATH VALIDATION
        // =========================================================================
        public static bool ValidateSafeMediaPath(string? filePath, bool isAudio = false)
        {
            if (string.IsNullOrWhiteSpace(filePath)) return false;
            try
            {
                string full = Path.GetFullPath(filePath);
                if (full.IndexOfAny(Path.GetInvalidPathChars()) >= 0) return false;
                if (!File.Exists(full)) return false;

                var fi = new FileInfo(full);
                if (fi.Length <= 128) return false; // Reject empty/corrupt 0-byte files

                string ext = fi.Extension;
                return isAudio ? AllowedAudioExtensions.Contains(ext) : AllowedVideoExtensions.Contains(ext);
            }
            catch
            {
                return false;
            }
        }

        public static List<SeelenfunkeMusicTrackItem> GetStudioMusicCatalog(string baseAssetsDir)
        {
            var list = new List<SeelenfunkeMusicTrackItem>
            {
                new SeelenfunkeMusicTrackItem
                {
                    Id = "none",
                    Title = "Kein Musik-Track (100 % nur Originalton / Pure ASMR)",
                    CategoryLabel = "Pure ASMR",
                    Bpm = 90,
                    ShimmerDropSec = 4.0,
                    KeySignature = "-",
                    FileName = "",
                    Description = "Verwendet ausschließlich den kristallklar aufbereiteten Originalton deiner Kamera (Seidenpapier, Box-Snap, Sprache)."
                },
                new SeelenfunkeMusicTrackItem
                {
                    Id = "sf_09",
                    Title = "Pack With Me in Gifhorn (Luxury Westpack Box)",
                    CategoryLabel = "Verpackung & POV",
                    Bpm = 86,
                    ShimmerDropSec = 4.2,
                    KeySignature = "D-Moll",
                    FileName = "sf_mkt_09_pack_with_me_gifhorn.m4a",
                    Description = "Perfekt abgestimmt auf virale Packaging-Reels: Raum für ASMR-Trigger + sanfter Puls beim Einlegen."
                },
                new SeelenfunkeMusicTrackItem
                {
                    Id = "sf_01",
                    Title = "Golden Shimmer Drop (Viral Reveal)",
                    CategoryLabel = "Viral & Reveal",
                    Bpm = 92,
                    ShimmerDropSec = 4.0,
                    KeySignature = "D-Moll",
                    FileName = "sf_mkt_01_golden_shimmer_drop.m4a",
                    Description = "Tiefer Puls bis Sek. 4,0 -> Edler Kristall-Shimmer-Drop exakt beim Umdrehen des Unikats."
                },
                new SeelenfunkeMusicTrackItem
                {
                    Id = "sf_07",
                    Title = "Action-Cam White Glove (Werkstatt Flow)",
                    CategoryLabel = "Werkstatt & ASMR",
                    Bpm = 88,
                    ShimmerDropSec = 4.1,
                    KeySignature = "D-Moll",
                    FileName = "sf_mkt_07_actioncam_white_glove.m4a",
                    Description = "Speziell für POV-Aufnahmen mit weißen Handschuhen, Laser-Gravur & Abpinseln."
                },
                new SeelenfunkeMusicTrackItem
                {
                    Id = "sf_08",
                    Title = "Schiefer & Gold Manufaktur (Deep Craft)",
                    CategoryLabel = "Werkstatt & ASMR",
                    Bpm = 84,
                    ShimmerDropSec = 4.3,
                    KeySignature = "A-Moll",
                    FileName = "sf_mkt_08_schiefer_gold_manufaktur.m4a",
                    Description = "Warmer, rhythmischer Manufaktur-Puls für Schiefer-, Metall- und Schmuck-Veredelung."
                },
                new SeelenfunkeMusicTrackItem
                {
                    Id = "sf_03",
                    Title = "Velvet Impulse Beat (Vorher / Nachher & Unboxing)",
                    CategoryLabel = "Viral & Reveal",
                    Bpm = 92,
                    ShimmerDropSec = 3.9,
                    KeySignature = "D-Moll",
                    FileName = "sf_mkt_03_velvet_impulse_beat.m4a",
                    Description = "Moderner, knackiger Takt für schnelle Rohling-zu-Unikat und Unboxing-Schnitte."
                },
                new SeelenfunkeMusicTrackItem
                {
                    Id = "sf_02",
                    Title = "Lights Off Crystal (Gegenlicht-Magie)",
                    CategoryLabel = "Viral & Reveal",
                    Bpm = 90,
                    ShimmerDropSec = 4.0,
                    KeySignature = "A-Moll",
                    FileName = "sf_mkt_02_lights_off_crystal.m4a",
                    Description = "Mystisches Kristall-Intro für 3D-Glas-Innengravur und Leuchtsockel-Reveals."
                },
                new SeelenfunkeMusicTrackItem
                {
                    Id = "sf_04",
                    Title = "Founder Trust Piano (Voiceover & Story)",
                    CategoryLabel = "Storytelling & Sprache",
                    Bpm = 74,
                    ShimmerDropSec = 5.0,
                    KeySignature = "D-Moll",
                    FileName = "sf_mkt_04_founder_trust_piano.m4a",
                    Description = "Warmes Filzklavier, das im Frequenzbereich Platz für deine echte Stimme lässt."
                },
                new SeelenfunkeMusicTrackItem
                {
                    Id = "sf_05",
                    Title = "Omas Handschrift Elegie (Gedenken & Emotion)",
                    CategoryLabel = "Emotion & Gedenken",
                    Bpm = 68,
                    ShimmerDropSec = 5.2,
                    KeySignature = "A-Moll",
                    FileName = "sf_mkt_05_oma_handschrift_elegie.m4a",
                    Description = "Tief berührendes Cello & Filzklavier für Gedenk-Unikate und Seelen-Steine."
                },
                new SeelenfunkeMusicTrackItem
                {
                    Id = "sf_06",
                    Title = "Atelier Herzschlag (Behind the Scenes)",
                    CategoryLabel = "Storytelling & Sprache",
                    Bpm = 76,
                    ShimmerDropSec = 4.8,
                    KeySignature = "D-Moll",
                    FileName = "sf_mkt_06_atelier_herzschlag.m4a",
                    Description = "Ruhiger, vertrauensvoller Puls für Einblicke in deine Manufaktur in Gifhorn."
                },
                new SeelenfunkeMusicTrackItem
                {
                    Id = "sf_10",
                    Title = "Mystic Atelier (Dunkel, Edel, Ruhig)",
                    CategoryLabel = "Ambient & Ruhe",
                    Bpm = 60,
                    ShimmerDropSec = 6.0,
                    KeySignature = "D-Dorisch",
                    FileName = "sf_mkt_10_mystic_atelier_login.m4a",
                    Description = "Der bekannte mystische, langsame Funkenwerk-Atelier-Sound für ruhige Makro-Aufnahmen."
                }
            };

            foreach (var item in list)
            {
                if (!string.IsNullOrEmpty(item.FileName))
                {
                    string candidate = Path.Combine(baseAssetsDir, item.FileName);
                    if (File.Exists(candidate))
                    {
                        item.FullPath = candidate;
                    }
                }
            }

            return list;
        }

        public static (string Ffmpeg, string Ffprobe) ResolveFfmpegBinaries()
        {
            if (!string.IsNullOrEmpty(_ffmpegPath) && File.Exists(_ffmpegPath) &&
                !string.IsNullOrEmpty(_ffprobePath) && File.Exists(_ffprobePath))
            {
                return (_ffmpegPath!, _ffprobePath!);
            }

            string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            string wingetPkgs = Path.Combine(localAppData, "Microsoft", "WinGet", "Packages");
            if (Directory.Exists(wingetPkgs))
            {
                try
                {
                    var ff = Directory.GetFiles(wingetPkgs, "ffmpeg.exe", SearchOption.AllDirectories).FirstOrDefault();
                    var fp = Directory.GetFiles(wingetPkgs, "ffprobe.exe", SearchOption.AllDirectories).FirstOrDefault();
                    if (!string.IsNullOrEmpty(ff) && !string.IsNullOrEmpty(fp))
                    {
                        _ffmpegPath = ff;
                        _ffprobePath = fp;
                        return (ff, fp);
                    }
                }
                catch { }
            }

            _ffmpegPath = "ffmpeg";
            _ffprobePath = "ffprobe";
            return (_ffmpegPath, _ffprobePath);
        }

        private static string ComputeFileFingerprint(string filePath, long length, long lastWriteTicks)
        {
            string raw = $"{filePath.ToLowerInvariant()}|{length}|{lastWriteTicks}|v2";
            byte[] bytes = SHA256.HashData(Encoding.UTF8.GetBytes(raw));
            return Convert.ToHexString(bytes)[..16].ToLowerInvariant();
        }

        // =========================================================================
        // ULTRA-FAST PARALLEL BATCH ANALYSIS WITH FINGERPRINT CACHE
        // =========================================================================
        public static async Task<List<RawVideoClipInfo>> AnalyzeMultipleClipsParallelAsync(
            IEnumerable<string> filePaths,
            string thumbCacheDir,
            Action<int, int, string>? onProgress = null,
            CancellationToken ct = default)
        {
            var validPaths = filePaths.Where(p => ValidateSafeMediaPath(p, false)).Distinct(StringComparer.OrdinalIgnoreCase).ToList();
            var results = new ConcurrentBag<RawVideoClipInfo>();
            if (validPaths.Count == 0) return new List<RawVideoClipInfo>();

            int completed = 0;
            int maxParallel = Math.Clamp(Environment.ProcessorCount / 2, 2, 4);
            using var sem = new SemaphoreSlim(maxParallel, maxParallel);

            var tasks = validPaths.Select(async path =>
            {
                await sem.WaitAsync(ct);
                try
                {
                    var clip = await AnalyzeRawClipAsync(path, thumbCacheDir, ct);
                    results.Add(clip);
                    int done = Interlocked.Increment(ref completed);
                    onProgress?.Invoke(done, validPaths.Count, clip.FileName);
                }
                finally
                {
                    sem.Release();
                }
            });

            await Task.WhenAll(tasks);
            return results
                .OrderBy(c => c.CreatedAt)
                .ThenBy(c => c.FileName, StringComparer.OrdinalIgnoreCase)
                .ToList();
        }

        public static async Task<RawVideoClipInfo> AnalyzeRawClipAsync(string filePath, string thumbCacheDir, CancellationToken ct = default)
        {
            if (!ValidateSafeMediaPath(filePath, false))
            {
                return new RawVideoClipInfo
                {
                    FilePath = filePath,
                    DurationSeconds = 0,
                    IsIncluded = false,
                    IsBadTakeWarning = true,
                    AiBadgeText = "⚠️ Ungültige oder leere Videodatei"
                };
            }

            var (ffmpeg, ffprobe) = ResolveFfmpegBinaries();
            var fi = new FileInfo(filePath);
            string fingerprint = ComputeFileFingerprint(filePath, fi.Length, fi.LastWriteTimeUtc.Ticks);

            Directory.CreateDirectory(thumbCacheDir);
            string cacheJsonPath = Path.Combine(thumbCacheDir, $"analysis_{fingerprint}.json");
            string thumbPath = Path.Combine(thumbCacheDir, $"thumb_{fingerprint}.jpg");

            // Check Instant Disk Cache first (< 2ms reload!)
            if (File.Exists(cacheJsonPath) && File.Exists(thumbPath))
            {
                try
                {
                    string cachedText = await File.ReadAllTextAsync(cacheJsonPath, ct);
                    var cachedClip = JsonSerializer.Deserialize<RawVideoClipInfo>(cachedText);
                    if (cachedClip != null && cachedClip.DurationSeconds > 0.2)
                    {
                        cachedClip.FilePath = filePath;
                        cachedClip.ThumbnailPath = thumbPath;
                        cachedClip.IsAnalyzed = true;
                        return cachedClip;
                    }
                }
                catch { }
            }

            var clip = new RawVideoClipInfo
            {
                FilePath = filePath,
                FileSizeBytes = fi.Length,
                LastWriteTicks = fi.LastWriteTimeUtc.Ticks,
                CreatedAt = fi.LastWriteTime
            };

            // 1. Run ffprobe securely via ArgumentList
            try
            {
                var (okProbe, stdoutProbe, _) = await RunProcessSafeAsync(
                    ffprobe,
                    new[] { "-v", "quiet", "-print_format", "json", "-show_format", "-show_streams", filePath },
                    15000,
                    ct);

                if (okProbe && !string.IsNullOrWhiteSpace(stdoutProbe))
                {
                    using var doc = JsonDocument.Parse(stdoutProbe);
                    var root = doc.RootElement;

                    if (root.TryGetProperty("format", out var fmt) &&
                        fmt.TryGetProperty("duration", out var durEl) &&
                        double.TryParse(durEl.GetString(), NumberStyles.Float, CultureInfo.InvariantCulture, out double dur))
                    {
                        clip.DurationSeconds = Math.Max(0.4, dur);
                    }

                    bool foundAudio = false;
                    if (root.TryGetProperty("streams", out var streams) && streams.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var st in streams.EnumerateArray())
                        {
                            string codecType = st.TryGetProperty("codec_type", out var ctEl) ? (ctEl.GetString() ?? "") : "";
                            if (codecType == "video" && clip.Width == 0)
                            {
                                clip.Width = st.TryGetProperty("width", out var wEl) ? wEl.GetInt32() : 1920;
                                clip.Height = st.TryGetProperty("height", out var hEl) ? hEl.GetInt32() : 1080;

                                int rotation = 0;
                                if (st.TryGetProperty("tags", out var tags) && tags.TryGetProperty("rotate", out var rotEl))
                                {
                                    int.TryParse(rotEl.GetString(), out rotation);
                                }
                                else if (st.TryGetProperty("side_data_list", out var sdList) && sdList.ValueKind == JsonValueKind.Array)
                                {
                                    foreach (var sd in sdList.EnumerateArray())
                                    {
                                        if (sd.TryGetProperty("rotation", out var rEl) && rEl.ValueKind == JsonValueKind.Number)
                                        {
                                            rotation = Math.Abs(rEl.GetInt32());
                                        }
                                    }
                                }
                                if (Math.Abs(rotation) == 90 || Math.Abs(rotation) == 270)
                                {
                                    (clip.Width, clip.Height) = (clip.Height, clip.Width);
                                }

                                if (st.TryGetProperty("r_frame_rate", out var fpsEl))
                                {
                                    string fpsRaw = fpsEl.GetString() ?? "30/1";
                                    var parts = fpsRaw.Split('/');
                                    if (parts.Length == 2 &&
                                        double.TryParse(parts[0], NumberStyles.Float, CultureInfo.InvariantCulture, out double num) &&
                                        double.TryParse(parts[1], NumberStyles.Float, CultureInfo.InvariantCulture, out double den) &&
                                        den > 0)
                                    {
                                        clip.Fps = Math.Clamp(num / den, 15.0, 120.0);
                                    }
                                }
                            }
                            else if (codecType == "audio")
                            {
                                foundAudio = true;
                            }
                        }
                    }
                    clip.HasAudio = foundAudio;
                }
            }
            catch { }

            if (clip.DurationSeconds <= 0.1) clip.DurationSeconds = 5.0;

            // 2. Run Thumbnail Extraction AND Signal/Audio Transient Analysis concurrently!
            var thumbTask = Task.Run(async () =>
            {
                try
                {
                    if (!File.Exists(thumbPath))
                    {
                        double thumbTime = Math.Clamp(clip.DurationSeconds * 0.38, 0.15, Math.Max(0.15, clip.DurationSeconds - 0.25));
                        string thumbTimeStr = thumbTime.ToString("0.00", CultureInfo.InvariantCulture);
                        await RunProcessSafeAsync(
                            ffmpeg,
                            new[] { "-y", "-ss", thumbTimeStr, "-i", filePath, "-frames:v", "1", "-vf", "scale=320:-2", "-q:v", "4", thumbPath },
                            12000,
                            ct);
                    }
                    if (File.Exists(thumbPath))
                    {
                        clip.ThumbnailPath = thumbPath;
                    }
                }
                catch { }
            }, ct);

            var analysisTask = RunSignalAndSilenceAnalysisAsync(clip, ffmpeg, ct);

            await Task.WhenAll(thumbTask, analysisTask);

            clip.IsAnalyzed = true;

            // Save to Fingerprint Cache for instant future loads
            try
            {
                string jsonOut = JsonSerializer.Serialize(clip);
                lock (_cacheLock)
                {
                    File.WriteAllText(cacheJsonPath, jsonOut, Encoding.UTF8);
                }
            }
            catch { }

            return clip;
        }

        private static async Task RunSignalAndSilenceAnalysisAsync(RawVideoClipInfo clip, string ffmpeg, CancellationToken ct)
        {
            double dur = clip.DurationSeconds;
            clip.TrimStartSec = dur > 3.8 ? Math.Min(0.55, dur * 0.08) : 0.0;
            clip.TrimEndSec = dur > 3.8 ? Math.Min(0.45, dur * 0.07) : 0.0;

            var windows = new List<ClipWindowScore>();
            var silentIntervals = new List<(double Start, double End)>();
            var audioPeakBySec = new Dictionary<int, double>();

            try
            {
                // Ultra-fast downscaled analysis (160x90 at 5 fps = 5,700x fewer pixels than 4K while preserving exact motion/contrast/exposure + ASMR transients)
                double analyzeDur = Math.Min(dur, 75.0);
                string durArg = analyzeDur.ToString("0.00", CultureInfo.InvariantCulture);

                var argList = new List<string>
                {
                    "-threads", "2",
                    "-t", durArg,
                    "-i", clip.FilePath,
                    "-vf", "fps=5,scale=160:90,signalstats,metadata=mode=print"
                };

                if (clip.HasAudio)
                {
                    argList.Add("-af");
                    argList.Add("silencedetect=noise=-34dB:d=0.35,astats=metadata=1:reset=1,ametadata=mode=print");
                }
                else
                {
                    argList.Add("-an");
                }

                argList.AddRange(new[] { "-f", "null", "-" });

                var (_, _, stderr) = await RunProcessSafeAsync(ffmpeg, argList, 25000, ct);

                double currentPts = 0;
                int currentAudioSec = 0;
                double yAvg = 128, yMin = 16, yMax = 235, yDif = 4.0, satAvg = 30;
                var frameSamples = new List<(double T, double YAvg, double Contrast, double Motion, double Sat)>();

                double? silenceStart = null;
                foreach (var rawLine in stderr.Split('\n'))
                {
                    string line = rawLine.Trim();
                    if (line.Contains("pts_time:"))
                    {
                        var m = Regex.Match(line, @"pts_time:([0-9.]+)");
                        if (m.Success && double.TryParse(m.Groups[1].Value, NumberStyles.Float, CultureInfo.InvariantCulture, out double pt))
                        {
                            if (line.Contains("Parsed_metadata"))
                            {
                                if (frameSamples.Count > 0 || currentPts > 0)
                                {
                                    frameSamples.Add((currentPts, yAvg, Math.Max(1, yMax - yMin), yDif, satAvg));
                                }
                                currentPts = pt;
                            }
                            else if (line.Contains("Parsed_ametadata"))
                            {
                                currentAudioSec = (int)Math.Floor(pt);
                            }
                        }
                    }
                    else if (line.Contains("lavfi.signalstats.YAVG="))
                    {
                        double.TryParse(line.Split('=').Last(), NumberStyles.Float, CultureInfo.InvariantCulture, out yAvg);
                    }
                    else if (line.Contains("lavfi.signalstats.YMIN="))
                    {
                        double.TryParse(line.Split('=').Last(), NumberStyles.Float, CultureInfo.InvariantCulture, out yMin);
                    }
                    else if (line.Contains("lavfi.signalstats.YMAX="))
                    {
                        double.TryParse(line.Split('=').Last(), NumberStyles.Float, CultureInfo.InvariantCulture, out yMax);
                    }
                    else if (line.Contains("lavfi.signalstats.YDIF="))
                    {
                        double.TryParse(line.Split('=').Last(), NumberStyles.Float, CultureInfo.InvariantCulture, out yDif);
                    }
                    else if (line.Contains("lavfi.signalstats.SATAVG="))
                    {
                        double.TryParse(line.Split('=').Last(), NumberStyles.Float, CultureInfo.InvariantCulture, out satAvg);
                    }
                    else if (line.Contains("lavfi.astats.Overall.Peak_level="))
                    {
                        if (double.TryParse(line.Split('=').Last(), NumberStyles.Float, CultureInfo.InvariantCulture, out double pkDb) &&
                            !double.IsInfinity(pkDb) && !double.IsNaN(pkDb))
                        {
                            if (!audioPeakBySec.TryGetValue(currentAudioSec, out double prev) || pkDb > prev)
                            {
                                audioPeakBySec[currentAudioSec] = pkDb;
                            }
                        }
                    }
                    else if (line.Contains("silence_start:"))
                    {
                        var m = Regex.Match(line, @"silence_start:\s*([0-9.]+)");
                        if (m.Success && double.TryParse(m.Groups[1].Value, NumberStyles.Float, CultureInfo.InvariantCulture, out double ss))
                        {
                            silenceStart = ss;
                        }
                    }
                    else if (line.Contains("silence_end:"))
                    {
                        var m = Regex.Match(line, @"silence_end:\s*([0-9.]+)");
                        if (m.Success && double.TryParse(m.Groups[1].Value, NumberStyles.Float, CultureInfo.InvariantCulture, out double se))
                        {
                            silentIntervals.Add((silenceStart ?? 0.0, se));
                            silenceStart = null;
                        }
                    }
                }

                if (frameSamples.Count == 0 && currentPts >= 0)
                {
                    frameSamples.Add((currentPts, yAvg, Math.Max(1, yMax - yMin), yDif, satAvg));
                }
                if (silenceStart.HasValue)
                {
                    silentIntervals.Add((silenceStart.Value, analyzeDur));
                }

                int windowCount = Math.Max(1, (int)Math.Ceiling(analyzeDur));
                for (int w = 0; w < windowCount; w++)
                {
                    double wStart = w * 1.0;
                    double wEnd = wStart + 1.0;
                    var inWin = frameSamples.Where(f => f.T >= wStart && f.T < wEnd).ToList();
                    if (inWin.Count == 0) continue;

                    double avgContrast = inWin.Average(f => f.Contrast);
                    double avgMotion = inWin.Average(f => f.Motion);
                    double avgBright = inWin.Average(f => f.YAvg);
                    double avgSat = inWin.Average(f => f.Sat);
                    double audioDb = audioPeakBySec.TryGetValue(w, out double dbVal) ? dbVal : -38.0;

                    // ASMR transient bonus (crisp sound between -28dB and -2dB, e.g. Westpack box snap, tissue rustle, laser sound)
                    double transientBonus = (audioDb >= -28.0 && audioDb <= -1.5)
                        ? Math.Clamp((audioDb + 32.0) * 1.1, 2.0, 28.0)
                        : 0.0;

                    double exposurePenalty = (avgBright < 20 || avgBright > 242) ? 0.48 : 1.0;
                    double motionQuality = (avgMotion >= 1.6 && avgMotion <= 15.5)
                        ? 1.0
                        : (avgMotion > 21.0 ? 0.42 : 0.78);

                    double hookFit = (avgContrast * 0.52 + Math.Min(avgMotion * 9.5, 92.0) * 0.48 + transientBonus * 0.6) * exposurePenalty * motionQuality;
                    double procFit = (avgContrast * 0.45 + Math.Min(avgMotion * 11.0, 95.0) * 0.55 + transientBonus * 0.8) * exposurePenalty * motionQuality;
                    double asmrFit = (avgContrast * 0.40 + Math.Min(avgMotion * 10.0, 85.0) * 0.35 + transientBonus * 1.6) * exposurePenalty * motionQuality;

                    double steadyBonus = avgMotion <= 8.5 ? 1.18 : 0.84;
                    double revFit = (avgContrast * 0.65 + avgSat * 0.85 + avgBright * 0.25) * exposurePenalty * steadyBonus;

                    windows.Add(new ClipWindowScore
                    {
                        StartSec = wStart,
                        Contrast = Math.Round(avgContrast, 1),
                        Motion = Math.Round(avgMotion, 2),
                        Brightness = Math.Round(avgBright, 1),
                        Saturation = Math.Round(avgSat, 1),
                        AudioRmsDb = Math.Round(audioDb, 1),
                        AudioTransientBonus = Math.Round(transientBonus, 1),
                        HookFitness = Math.Round(hookFit, 1),
                        ProcessFitness = Math.Round(procFit, 1),
                        RevealFitness = Math.Round(revFit, 1),
                        PackagingAsmrFitness = Math.Round(asmrFit, 1)
                    });
                }
            }
            catch { }

            clip.WindowScores = windows;
            if (audioPeakBySec.Count > 0)
            {
                clip.PeakAudioDb = Math.Round(audioPeakBySec.Values.Max(), 1);
            }

            // Detect camera shake at start/end from real motion spikes
            if (windows.Count >= 4)
            {
                if (windows[0].Motion > 17.5 || windows[0].Brightness < 18.0)
                {
                    clip.TrimStartSec = Math.Min(1.25, dur * 0.22);
                }
                if (windows[^1].Motion > 17.5 || windows[^1].Brightness < 18.0)
                {
                    clip.TrimEndSec = Math.Min(1.10, dur * 0.20);
                }
            }

            double usableStart = clip.TrimStartSec;
            double usableEnd = Math.Max(usableStart + 0.9, dur - clip.TrimEndSec);
            var usableWindows = windows.Where(w => w.StartSec >= usableStart && w.StartSec <= usableEnd - 1.0).ToList();

            if (usableWindows.Count > 0)
            {
                var bestHook = usableWindows.OrderByDescending(w => w.HookFitness).First();
                var bestProc = usableWindows
                    .Where(w => Math.Abs(w.StartSec - bestHook.StartSec) > 1.4 || usableWindows.Count <= 2)
                    .OrderByDescending(w => w.ProcessFitness)
                    .FirstOrDefault() ?? bestHook;

                var bestAsmr = usableWindows
                    .OrderByDescending(w => w.PackagingAsmrFitness)
                    .FirstOrDefault() ?? bestProc;

                var secondHalf = usableWindows.Where(w => w.StartSec >= usableStart + (usableEnd - usableStart) * 0.35).ToList();
                var bestRev = (secondHalf.Count > 0 ? secondHalf : usableWindows)
                    .OrderByDescending(w => w.RevealFitness)
                    .First();

                clip.BestHookStartSec = Math.Clamp(bestHook.StartSec, usableStart, Math.Max(usableStart, usableEnd - 1.8));
                clip.BestProcessStartSec = Math.Clamp(bestProc.StartSec, usableStart, Math.Max(usableStart, usableEnd - 1.8));
                clip.BestAsmrTriggerSec = Math.Clamp(bestAsmr.StartSec, usableStart, Math.Max(usableStart, usableEnd - 1.6));
                clip.BestRevealStartSec = Math.Clamp(bestRev.StartSec, usableStart, Math.Max(usableStart, usableEnd - 2.2));

                clip.AvgMotionScore = Math.Round(usableWindows.Average(w => w.Motion), 1);
                clip.PeakContrastScore = Math.Round(usableWindows.Max(w => w.Contrast), 1);

                double avgBri = usableWindows.Average(w => w.Brightness);
                clip.IsBadTakeWarning = dur < 1.1 || avgBri < 16.0 || clip.AvgMotionScore > 24.0;

                clip.QualityScore = (int)Math.Clamp(
                    70 + (clip.PeakContrastScore / 9.5) + (clip.AvgMotionScore >= 1.4 && clip.AvgMotionScore <= 15.5 ? 11 : 2) - (clip.IsBadTakeWarning ? 20 : 0),
                    55,
                    99);
            }
            else
            {
                clip.BestHookStartSec = usableStart;
                clip.BestProcessStartSec = Math.Clamp(dur * 0.35, usableStart, Math.Max(usableStart, usableEnd - 1.5));
                clip.BestAsmrTriggerSec = clip.BestProcessStartSec;
                clip.BestRevealStartSec = Math.Clamp(dur * 0.65, usableStart, Math.Max(usableStart, usableEnd - 2.0));
                clip.QualityScore = 88;
            }

            // Build non-silent speech/sound intervals from silencedetect
            var speechRanges = new List<SpeechRangeDto>();
            double cursor = usableStart;
            foreach (var (sStart, sEnd) in silentIntervals.OrderBy(x => x.Start))
            {
                if (sStart > cursor + 0.38 && cursor < usableEnd)
                {
                    double segStart = Math.Max(usableStart, cursor - 0.06);
                    double segEnd = Math.Min(usableEnd, sStart + 0.06);
                    if (segEnd - segStart >= 0.55)
                    {
                        speechRanges.Add(new SpeechRangeDto(Math.Round(segStart, 2), Math.Round(segEnd, 2)));
                    }
                }
                cursor = Math.Max(cursor, sEnd);
            }
            if (usableEnd - cursor >= 0.55)
            {
                speechRanges.Add(new SpeechRangeDto(Math.Round(cursor, 2), Math.Round(usableEnd, 2)));
            }
            if (speechRanges.Count == 0)
            {
                speechRanges.Add(new SpeechRangeDto(Math.Round(usableStart, 2), Math.Round(usableEnd, 2)));
            }
            clip.NonSilentSpeechRanges = speechRanges;

            if (clip.IsBadTakeWarning)
            {
                clip.AiBadgeText = $"⚠️ Verwackelt / Zu kurz ({clip.QualityScore}/100)";
            }
            else if (clip.PeakAudioDb >= -18.0 && clip.AvgMotionScore >= 2.5)
            {
                clip.AiBadgeText = $"📦 Top Packaging- & ASMR-Trigger ({clip.QualityScore}/100)";
            }
            else if (clip.AvgMotionScore >= 3.5 && clip.PeakContrastScore >= 160)
            {
                clip.AiBadgeText = $"🔥 Starker Action- & Licht-Moment ({clip.QualityScore}/100)";
            }
            else if (clip.AvgMotionScore < 3.5 && clip.PeakContrastScore >= 145)
            {
                clip.AiBadgeText = $"✨ Ruhige Makro-/Reveal-Aufnahme ({clip.QualityScore}/100)";
            }
            else
            {
                clip.AiBadgeText = $"🎬 Saubere Manufaktur-Sequenz ({clip.QualityScore}/100)";
            }

            clip.AiDetailSummary = $"Nutzbar: {RawVideoClipInfo.FormatSeconds(usableStart)}–{RawVideoClipInfo.FormatSeconds(usableEnd)} · ASMR-Peak @ {RawVideoClipInfo.FormatSeconds(clip.BestAsmrTriggerSec)} · Reveal @ {RawVideoClipInfo.FormatSeconds(clip.BestRevealStartSec)}";
        }

        // =========================================================================
        // VIRAL AI CUT DECISION ENGINE (WITH PACKAGING BLUEPRINT & SMART TRANSITIONS)
        // =========================================================================
        public static List<CutDecisionItem> BuildSmartCutDecisionList(
            List<RawVideoClipInfo> rawClips,
            string directorModeKey,
            SeelenfunkeMusicTrackItem? musicTrack,
            double targetDurationSec,
            bool snapToBeat,
            bool autoTrimShake,
            string transitionPreference = "auto_director")
        {
            var activeClips = rawClips.Where(c => c.IsIncluded && c.DurationSeconds >= 0.6).ToList();
            var decisions = new List<CutDecisionItem>();
            if (activeClips.Count == 0) return decisions;

            // Prefer non-bad-take clips for Hook and Reveal when available
            var goodClips = activeClips.Where(c => !c.IsBadTakeWarning).ToList();
            if (goodClips.Count == 0) goodClips = activeClips;

            double beatSec = (musicTrack != null && musicTrack.Bpm > 0) ? (60.0 / musicTrack.Bpm) : 0.652; // ~92 BPM
            double dropSec = (musicTrack != null && musicTrack.ShimmerDropSec > 0) ? musicTrack.ShimmerDropSec : 4.0;

            double SnapDuration(double desiredSec, double minBeats = 2.0, double maxBeats = 8.0)
            {
                if (!snapToBeat) return Math.Round(desiredSec, 2);
                double beats = Math.Clamp(Math.Round((desiredSec * 2.0) / beatSec) / 2.0, minBeats, maxBeats);
                return Math.Round(beats * beatSec, 2);
            }

            (double UsableStart, double UsableEnd) GetUsableBounds(RawVideoClipInfo c)
            {
                double s = autoTrimShake ? c.TrimStartSec : 0.0;
                double e = autoTrimShake ? Math.Max(s + 0.6, c.DurationSeconds - c.TrimEndSec) : c.DurationSeconds;
                return (Math.Round(s, 2), Math.Round(e, 2));
            }

            // -------------------------------------------------------------------------
            // MODE 1: VIRAL LUXURY PACKAGING BLUEPRINT ("packaging_pov")
            // Formula: 1. Reverse-Teaser Hook (0-1.5s) -> 2. Statement Unikat (1.5-3.2s)
            //          -> 3. Rapid Micro-ASMR Packaging Cuts (1.2-1.8s each) -> 4. Box-Snap Finale
            // -------------------------------------------------------------------------
            if (directorModeKey == "packaging_pov")
            {
                double goalSec = Math.Clamp(targetDurationSec, 10.0, 28.0);
                var bestRevealClip = goodClips.OrderByDescending(c => c.QualityScore + (c.PeakContrastScore * 0.12)).Last();
                var bestHeroClip = goodClips.OrderByDescending(c => c.QualityScore + c.AvgMotionScore).First();

                double timelinePos = 0.0;
                int cutIdx = 1;

                // PHASE 1 (0.0s - ~1.5s): THE GRAND REVEAL TEASER HOOK (Stoppt Weiterscrollen sofort!)
                {
                    var (uStart, uEnd) = GetUsableBounds(bestRevealClip);
                    double teaserDur = Math.Min(uEnd - uStart, SnapDuration(beatSec * 2.0, 1.5, 2.5)); // ~1.4s
                    if (teaserDur < 1.1) teaserDur = Math.Min(uEnd - uStart, 1.4);
                    double teaserIn = Math.Clamp(bestRevealClip.BestRevealStartSec, uStart, Math.Max(uStart, uEnd - teaserDur));

                    decisions.Add(new CutDecisionItem
                    {
                        Index = cutIdx++,
                        SourceClipId = bestRevealClip.Id,
                        SourceFilePath = bestRevealClip.FilePath,
                        ThumbnailPath = bestRevealClip.ThumbnailPath,
                        SourceHasAudio = bestRevealClip.HasAudio,
                        SourceClipTotalDuration = bestRevealClip.DurationSeconds,
                        InPointSec = Math.Round(teaserIn, 2),
                        DurationSec = Math.Round(teaserDur, 2),
                        TimelineStartSec = 0.0,
                        RoleKey = "teaser_hook",
                        RoleBadge = "⚡ 1. PACKAGING-HOOK (Teaser)",
                        AiCutReason = "Virale Packaging-Formel: Zeigt 1,4s Teaser des fertigen Pakets/Unikats gegen Weiterscrollen",
                        Score = Math.Min(99, bestRevealClip.QualityScore + 4),
                        TransitionType = "hard_action",
                        ZoomFactor = 1.06,
                        SpeedFactor = 1.00
                    });
                    timelinePos += teaserDur;
                }

                // PHASE 2 (1.5s - ~3.3s): THE STATEMENT ITEM (Hero Produkt in weißen Handschuhen)
                {
                    var (uStart, uEnd) = GetUsableBounds(bestHeroClip);
                    double heroDur = Math.Min(uEnd - uStart, SnapDuration(beatSec * 2.5, 2.0, 3.0)); // ~1.75s
                    double heroIn = Math.Clamp(bestHeroClip.BestHookStartSec, uStart, Math.Max(uStart, uEnd - heroDur));

                    decisions.Add(new CutDecisionItem
                    {
                        Index = cutIdx++,
                        SourceClipId = bestHeroClip.Id,
                        SourceFilePath = bestHeroClip.FilePath,
                        ThumbnailPath = bestHeroClip.ThumbnailPath,
                        SourceHasAudio = bestHeroClip.HasAudio,
                        SourceClipTotalDuration = bestHeroClip.DurationSeconds,
                        InPointSec = Math.Round(heroIn, 2),
                        DurationSec = Math.Round(heroDur, 2),
                        TimelineStartSec = Math.Round(timelinePos, 2),
                        RoleKey = "statement",
                        RoleBadge = "💎 2. HERO-UNIKAT (White-Glove)",
                        AiCutReason = "Whip-Flow zurück zum Anfang: Präsentiert das Hauptprodukt vor dem Einpacken",
                        Score = bestHeroClip.QualityScore,
                        TransitionType = "whip_flow",
                        ZoomFactor = 1.00,
                        SpeedFactor = 1.00
                    });
                    timelinePos += heroDur;
                }

                // PHASE 3: SATISFYING MICRO-ASMR PROCESS STEPS (Seidenpapier, Einlegen, Westpack-Box, Schleife)
                double finalPayoffDur = Math.Min(GetUsableBounds(bestRevealClip).UsableEnd - GetUsableBounds(bestRevealClip).UsableStart, SnapDuration(beatSec * 3.5, 3.0, 4.5));
                if (finalPayoffDur < 2.0) finalPayoffDur = Math.Min(bestRevealClip.DurationSeconds, 2.4);

                double middleBudget = Math.Max(3.0, goalSec - timelinePos - finalPayoffDur);
                // Viral packaging uses crisp micro-cuts (~1.4s - 1.8s per step)
                int microStepCount = Math.Clamp((int)Math.Round(middleBudget / (beatSec * 2.2)), 2, 8);

                string[] packagingStepLabels =
                {
                    "📦 ASMR: Seidenpapier & Vorbereitung",
                    "📦 ASMR: Einlegen in Westpack-Box",
                    "📦 ASMR: Samt-Einlage & Ausrichten",
                    "📦 ASMR: Geschenkband & Siegel",
                    "📦 ASMR: Box-Deckel Snap (Lid-Drop)",
                    "📦 ASMR: Schutzfolie & Finish",
                    "📦 ASMR: Feinschliff mit Handschuhen",
                    "📦 ASMR: Versandfertiges Paket"
                };

                for (int i = 0; i < microStepCount; i++)
                {
                    var clip = activeClips[i % activeClips.Count];
                    var (uStart, uEnd) = GetUsableBounds(clip);
                    double avail = uEnd - uStart;
                    if (avail < 0.8) continue;

                    double stepDur = Math.Min(avail, SnapDuration(beatSec * 2.0, 1.5, 3.0)); // ~1.4s - 1.8s micro-cut
                    double targetCandidate = (i % 2 == 0) ? clip.BestAsmrTriggerSec : clip.BestProcessStartSec;

                    // Pick distinct non-overlapping section if same clip is reused
                    var prevSame = decisions.Where(d => d.SourceClipId == clip.Id).ToList();
                    if (prevSame.Count > 0 && avail > stepDur * 1.5)
                    {
                        double fraction = (i + 1.0) / (microStepCount + 1.5);
                        targetCandidate = uStart + (avail - stepDur) * fraction;
                        foreach (var ex in prevSame)
                        {
                            if (Math.Abs(targetCandidate - ex.InPointSec) < stepDur * 0.7)
                            {
                                targetCandidate = ex.OutPointSec + 0.25;
                                if (targetCandidate + stepDur > uEnd)
                                {
                                    targetCandidate = Math.Max(uStart, ex.InPointSec - stepDur - 0.25);
                                }
                            }
                        }
                    }

                    double inPt = Math.Clamp(targetCandidate, uStart, Math.Max(uStart, uEnd - stepDur));
                    string stepBadge = packagingStepLabels[i % packagingStepLabels.Length];

                    decisions.Add(new CutDecisionItem
                    {
                        Index = cutIdx++,
                        SourceClipId = clip.Id,
                        SourceFilePath = clip.FilePath,
                        ThumbnailPath = clip.ThumbnailPath,
                        SourceHasAudio = clip.HasAudio,
                        SourceClipTotalDuration = clip.DurationSeconds,
                        InPointSec = Math.Round(inPt, 2),
                        DurationSec = Math.Round(stepDur, 2),
                        TimelineStartSec = Math.Round(timelinePos, 2),
                        RoleKey = "asmr_step",
                        RoleBadge = stepBadge,
                        AiCutReason = $"Micro-Pacing ({stepDur:0.0}s) auf ASMR-Ton-Peak & Takt + 2-Kamera-Perspektivwechsel",
                        Score = clip.QualityScore,
                        TransitionType = (i % 2 == 1) ? "punch_zoom" : "hard_action",
                        ZoomFactor = (i % 2 == 1) ? 1.08 : 1.00,
                        SpeedFactor = stepDur > 1.7 ? 1.12 : 1.00
                    });
                    timelinePos += stepDur;
                }

                // PHASE 4: THE PAYOFF & CLIMAX HOLD (Fertige schwarze Westpack-Box / End-Reveal)
                {
                    var (uStart, uEnd) = GetUsableBounds(bestRevealClip);
                    double avail = uEnd - uStart;
                    double revDur = Math.Clamp(finalPayoffDur, 1.4, Math.Max(1.4, avail));
                    double revIn = Math.Max(uStart, uEnd - revDur);

                    decisions.Add(new CutDecisionItem
                    {
                        Index = cutIdx,
                        SourceClipId = bestRevealClip.Id,
                        SourceFilePath = bestRevealClip.FilePath,
                        ThumbnailPath = bestRevealClip.ThumbnailPath,
                        SourceHasAudio = bestRevealClip.HasAudio,
                        SourceClipTotalDuration = bestRevealClip.DurationSeconds,
                        InPointSec = Math.Round(revIn, 2),
                        DurationSec = Math.Round(revDur, 2),
                        TimelineStartSec = Math.Round(timelinePos, 2),
                        RoleKey = "reveal",
                        RoleBadge = "✨ 4. PACKAGING-FINALE & REVEAL",
                        AiCutReason = "Edle Samt-Blende in den ruhigen Abschluss-Shot (schließt den Loop zum Teaser)",
                        Score = Math.Min(99, bestRevealClip.QualityScore + 3),
                        TransitionType = "velvet_dip",
                        ZoomFactor = 1.00,
                        SpeedFactor = 0.92
                    });
                }

                ApplyTransitionRules(decisions, transitionPreference);
                RecalculateTimelinePositions(decisions);
                return decisions;
            }

            // -------------------------------------------------------------------------
            // MODE 4: VORHER / NACHHER & LUXURY UNBOXING ("unboxing_reveal")
            // -------------------------------------------------------------------------
            if (directorModeKey == "unboxing_reveal")
            {
                double goalSec = Math.Clamp(targetDurationSec, 8.0, 20.0);
                var firstClip = goodClips.First();
                var lastClip = goodClips.Last();
                int idx = 1;
                double pos = 0.0;

                // 1. Vorher / Rohling-Zustand
                {
                    var (uStart, uEnd) = GetUsableBounds(firstClip);
                    double d1 = Math.Min(uEnd - uStart, SnapDuration(beatSec * 2.5, 2, 3));
                    decisions.Add(new CutDecisionItem
                    {
                        Index = idx++,
                        SourceClipId = firstClip.Id,
                        SourceFilePath = firstClip.FilePath,
                        ThumbnailPath = firstClip.ThumbnailPath,
                        SourceHasAudio = firstClip.HasAudio,
                        SourceClipTotalDuration = firstClip.DurationSeconds,
                        InPointSec = uStart,
                        DurationSec = d1,
                        TimelineStartSec = 0,
                        RoleKey = "hook",
                        RoleBadge = "⚡ 1. VORHER / ROHLING",
                        AiCutReason = "Zeigt den Ausgangszustand vor der Laser-Veredelung",
                        Score = firstClip.QualityScore,
                        TransitionType = "hard_action",
                        ZoomFactor = 1.00,
                        SpeedFactor = 1.00
                    });
                    pos += d1;
                }

                // 2. Veredelungs-Schritte bis zum Drop
                int midCount = Math.Clamp(activeClips.Count, 2, 4);
                for (int i = 0; i < midCount; i++)
                {
                    var c = activeClips[i % activeClips.Count];
                    var (uStart, uEnd) = GetUsableBounds(c);
                    double dMid = Math.Min(uEnd - uStart, SnapDuration(beatSec * 2.0, 1.5, 3.0));
                    double inMid = Math.Clamp(c.BestProcessStartSec, uStart, Math.Max(uStart, uEnd - dMid));
                    decisions.Add(new CutDecisionItem
                    {
                        Index = idx++,
                        SourceClipId = c.Id,
                        SourceFilePath = c.FilePath,
                        ThumbnailPath = c.ThumbnailPath,
                        SourceHasAudio = c.HasAudio,
                        SourceClipTotalDuration = c.DurationSeconds,
                        InPointSec = Math.Round(inMid, 2),
                        DurationSec = dMid,
                        TimelineStartSec = Math.Round(pos, 2),
                        RoleKey = "process",
                        RoleBadge = $"🔥 {idx - 1}. VEREDELUNG & LASER",
                        AiCutReason = "Dynamischer Match-Cut auf die Veredelungs-Bewegung",
                        Score = c.QualityScore,
                        TransitionType = i == 0 ? "whip_flow" : "punch_zoom",
                        ZoomFactor = (i % 2 == 1) ? 1.08 : 1.00,
                        SpeedFactor = 1.10
                    });
                    pos += dMid;
                }

                // 3. Grand Nachher-Reveal mit Licht-Impuls
                {
                    var (uStart, uEnd) = GetUsableBounds(lastClip);
                    double dRev = Math.Min(uEnd - uStart, SnapDuration(beatSec * 4.5, 3.5, 6.0));
                    double inRev = Math.Clamp(lastClip.BestRevealStartSec, uStart, Math.Max(uStart, uEnd - dRev));
                    decisions.Add(new CutDecisionItem
                    {
                        Index = idx,
                        SourceClipId = lastClip.Id,
                        SourceFilePath = lastClip.FilePath,
                        ThumbnailPath = lastClip.ThumbnailPath,
                        SourceHasAudio = lastClip.HasAudio,
                        SourceClipTotalDuration = lastClip.DurationSeconds,
                        InPointSec = Math.Round(inRev, 2),
                        DurationSec = dRev,
                        TimelineStartSec = Math.Round(pos, 2),
                        RoleKey = "reveal",
                        RoleBadge = "✨ NACHHER: UNIKAT-REVEAL",
                        AiCutReason = "Licht-Impuls-Übergang auf das fertig veredelte Unikat in leichter Zeitlupe",
                        Score = Math.Min(99, lastClip.QualityScore + 3),
                        TransitionType = "flash_luma",
                        ZoomFactor = 1.05,
                        SpeedFactor = 0.90
                    });
                }

                ApplyTransitionRules(decisions, transitionPreference);
                RecalculateTimelinePositions(decisions);
                return decisions;
            }

            // -------------------------------------------------------------------------
            // MODE 5: STORYTELLING / SPRACHE (Gling-Style Auto-Silence-Cut + 2-Cam Zoom)
            // -------------------------------------------------------------------------
            if (directorModeKey == "story_speech")
            {
                int idx = 1;
                double timelineCursor = 0;
                foreach (var clip in activeClips.OrderBy(c => c.CreatedAt).ThenBy(c => c.FileName))
                {
                    var ranges = clip.NonSilentSpeechRanges.Count > 0
                        ? clip.NonSilentSpeechRanges
                        : new List<SpeechRangeDto> { new(GetUsableBounds(clip).UsableStart, GetUsableBounds(clip).UsableEnd) };

                    foreach (var range in ranges)
                    {
                        double segDur = Math.Round(range.EndSec - range.StartSec, 2);
                        if (segDur < 0.55) continue;
                        if (timelineCursor + segDur > targetDurationSec + 6.0 && decisions.Count >= 2) break;

                        bool alternateZoom = idx > 1 && idx % 2 == 0;
                        decisions.Add(new CutDecisionItem
                        {
                            Index = idx,
                            SourceClipId = clip.Id,
                            SourceFilePath = clip.FilePath,
                            ThumbnailPath = clip.ThumbnailPath,
                            SourceHasAudio = clip.HasAudio,
                            SourceClipTotalDuration = clip.DurationSeconds,
                            InPointSec = range.StartSec,
                            DurationSec = segDur,
                            TimelineStartSec = Math.Round(timelineCursor, 2),
                            RoleKey = idx == 1 ? "hook" : "speech",
                            RoleBadge = idx == 1 ? "🎙️ SPRACH-HOOK" : $"✂️ SATZ #{idx}",
                            AiCutReason = alternateZoom
                                ? "Sprechpause entfernt + 1.06x Kamera-Zoom gegen Jump-Cut (2-Kamera-Effekt)"
                                : "Sprechpause & Atmer automatisch entfernt (J/L-Audio-Glättung aktiv)",
                            Score = clip.QualityScore,
                            TransitionType = alternateZoom ? "punch_zoom" : "hard_action",
                            ZoomFactor = alternateZoom ? 1.06 : 1.00,
                            SpeedFactor = 1.00
                        });
                        timelineCursor += segDur;
                        idx++;
                    }
                }
                ApplyTransitionRules(decisions, transitionPreference);
                RecalculateTimelinePositions(decisions);
                return decisions;
            }

            // -------------------------------------------------------------------------
            // MODE 6: CHRONOLOGISCH BEREINIGT ("clean_all")
            // -------------------------------------------------------------------------
            if (directorModeKey == "clean_all")
            {
                int idx = 1;
                double timelineCursor = 0;
                double perClipTarget = Math.Clamp(targetDurationSec / Math.Max(1, activeClips.Count), 1.8, 5.5);

                foreach (var clip in activeClips)
                {
                    var (uStart, uEnd) = GetUsableBounds(clip);
                    double avail = uEnd - uStart;
                    double dur = Math.Min(avail, SnapDuration(perClipTarget, 2, 10));
                    double inPt = Math.Clamp(clip.BestProcessStartSec, uStart, Math.Max(uStart, uEnd - dur));

                    decisions.Add(new CutDecisionItem
                    {
                        Index = idx,
                        SourceClipId = clip.Id,
                        SourceFilePath = clip.FilePath,
                        ThumbnailPath = clip.ThumbnailPath,
                        SourceHasAudio = clip.HasAudio,
                        SourceClipTotalDuration = clip.DurationSeconds,
                        InPointSec = Math.Round(inPt, 2),
                        DurationSec = Math.Round(dur, 2),
                        TimelineStartSec = Math.Round(timelineCursor, 2),
                        RoleKey = idx == 1 ? "hook" : (idx == activeClips.Count ? "reveal" : "process"),
                        RoleBadge = idx == 1 ? "⚡ EINSTIEG" : (idx == activeClips.Count ? "✨ ABSCHLUSS" : $"🎬 SZENE #{idx}"),
                        AiCutReason = "Wackler am Anfang/Ende entfernt & schärfsten Abschnitt auf den Takt gelegt",
                        Score = clip.QualityScore,
                        TransitionType = idx == activeClips.Count && activeClips.Count > 1 ? "velvet_dip" : "hard_action",
                        ZoomFactor = 1.00,
                        SpeedFactor = 1.00
                    });
                    timelineCursor += dur;
                    idx++;
                }
                ApplyTransitionRules(decisions, transitionPreference);
                RecalculateTimelinePositions(decisions);
                return decisions;
            }

            // -------------------------------------------------------------------------
            // MODES 2 & 3: WHITE-GLOVE LASER ASMR ("asmr_laser") & VIRAL SHIMMER-DROP ("viral_drop")
            // -------------------------------------------------------------------------
            double goal = directorModeKey == "viral_drop"
                ? Math.Clamp(targetDurationSec, 7.0, 13.0)
                : Math.Clamp(targetDurationSec, 10.0, 22.0);

            var hookClip = goodClips.OrderByDescending(c => c.QualityScore + c.AvgMotionScore).First();
            var revealClip = goodClips
                .OrderByDescending(c => c.QualityScore + (c.PeakContrastScore * 0.1))
                .FirstOrDefault(c => c.Id != hookClip.Id) ?? hookClip;

            double tPos = 0.0;
            int cIdx = 1;

            // CUT 1: HOOK (0.0s -> ~1.8s)
            {
                var (uStart, uEnd) = GetUsableBounds(hookClip);
                double hookDur = Math.Min(uEnd - uStart, SnapDuration(beatSec * 2.5, 2, 3.5));
                if (hookDur < 1.2) hookDur = Math.Min(uEnd - uStart, 1.7);
                double hookIn = Math.Clamp(hookClip.BestHookStartSec, uStart, Math.Max(uStart, uEnd - hookDur));

                decisions.Add(new CutDecisionItem
                {
                    Index = cIdx++,
                    SourceClipId = hookClip.Id,
                    SourceFilePath = hookClip.FilePath,
                    ThumbnailPath = hookClip.ThumbnailPath,
                    SourceHasAudio = hookClip.HasAudio,
                    SourceClipTotalDuration = hookClip.DurationSeconds,
                    InPointSec = Math.Round(hookIn, 2),
                    DurationSec = Math.Round(hookDur, 2),
                    TimelineStartSec = 0.0,
                    RoleKey = "hook",
                    RoleBadge = "⚡ HOOK (0–1,8s)",
                    AiCutReason = $"Stärkster visueller Action-Einstieg ({hookClip.FileName}) ohne Anlauf-Verwackler",
                    Score = hookClip.QualityScore,
                    TransitionType = "hard_action",
                    ZoomFactor = 1.00,
                    SpeedFactor = 1.00
                });
                tPos += hookDur;
            }

            double revealHoldDur = Math.Min(GetUsableBounds(revealClip).UsableEnd - GetUsableBounds(revealClip).UsableStart, SnapDuration(beatSec * 4.5, 3.5, 6.5));
            if (revealHoldDur < 2.2) revealHoldDur = Math.Min(revealClip.DurationSeconds, 2.8);

            if (directorModeKey == "viral_drop")
            {
                double preDropRemaining = Math.Max(1.3, dropSec - tPos);
                var procClip = goodClips.FirstOrDefault(c => c.Id != hookClip.Id) ?? hookClip;
                var (uStart, uEnd) = GetUsableBounds(procClip);
                double procDur = Math.Min(uEnd - uStart, Math.Round(preDropRemaining, 2));
                double procIn = Math.Clamp(procClip.BestProcessStartSec, uStart, Math.Max(uStart, uEnd - procDur));

                decisions.Add(new CutDecisionItem
                {
                    Index = cIdx++,
                    SourceClipId = procClip.Id,
                    SourceFilePath = procClip.FilePath,
                    ThumbnailPath = procClip.ThumbnailPath,
                    SourceHasAudio = procClip.HasAudio,
                    SourceClipTotalDuration = procClip.DurationSeconds,
                    InPointSec = Math.Round(procIn, 2),
                    DurationSec = Math.Round(procDur, 2),
                    TimelineStartSec = Math.Round(tPos, 2),
                    RoleKey = "process",
                    RoleBadge = "🔥 BUILD-UP VOR DROP",
                    AiCutReason = $"Spannungsaufbau exakt bis zum Shimmer-Drop bei {dropSec:0.0}s",
                    Score = procClip.QualityScore,
                    TransitionType = "punch_zoom",
                    ZoomFactor = 1.07,
                    SpeedFactor = 1.05
                });
                tPos += procDur;
            }
            else
            {
                double processBudget = Math.Max(2.5, goal - tPos - revealHoldDur);
                int desiredMiddleCuts = Math.Clamp((int)Math.Round(processBudget / (beatSec * 2.6)), 2, 6);
                var processPool = activeClips.OrderByDescending(c => c.AvgMotionScore + c.QualityScore).ToList();

                for (int i = 0; i < desiredMiddleCuts; i++)
                {
                    var clip = processPool[i % processPool.Count];
                    var (uStart, uEnd) = GetUsableBounds(clip);
                    double avail = uEnd - uStart;
                    if (avail < 0.9) continue;

                    double cutDur = Math.Min(avail, SnapDuration(beatSec * 2.5, 2, 3.5));
                    double baseOffset = (i == 0)
                        ? clip.BestProcessStartSec
                        : uStart + ((avail - cutDur) * ((i + 1.0) / (desiredMiddleCuts + 2.0)));

                    var existingFromSame = decisions.Where(d => d.SourceClipId == clip.Id).ToList();
                    foreach (var ex in existingFromSame)
                    {
                        if (Math.Abs(baseOffset - ex.InPointSec) < cutDur * 0.75)
                        {
                            baseOffset = ex.OutPointSec + 0.35;
                            if (baseOffset + cutDur > uEnd)
                            {
                                baseOffset = Math.Max(uStart, ex.InPointSec - cutDur - 0.35);
                            }
                        }
                    }

                    double inPt = Math.Clamp(baseOffset, uStart, Math.Max(uStart, uEnd - cutDur));
                    bool isDropMoment = Math.Abs(tPos - dropSec) <= beatSec * 1.4;

                    decisions.Add(new CutDecisionItem
                    {
                        Index = cIdx++,
                        SourceClipId = clip.Id,
                        SourceFilePath = clip.FilePath,
                        ThumbnailPath = clip.ThumbnailPath,
                        SourceHasAudio = clip.HasAudio,
                        SourceClipTotalDuration = clip.DurationSeconds,
                        InPointSec = Math.Round(inPt, 2),
                        DurationSec = Math.Round(cutDur, 2),
                        TimelineStartSec = Math.Round(tPos, 2),
                        RoleKey = isDropMoment ? "drop" : "process",
                        RoleBadge = isDropMoment ? $"✨ SHIMMER-DROP ({tPos:0.0}s)" : $"🔥 PROZESS #{i + 1} (Beat-Cut)",
                        AiCutReason = isDropMoment
                            ? $"Schnitt landet exakt auf dem Musik-Drop ({musicTrack?.Bpm ?? 92} BPM)"
                            : $"Manufaktur-Schritt im Takt ({musicTrack?.Bpm ?? 92} BPM) + 2-Kamera-Dynamik",
                        Score = clip.QualityScore,
                        TransitionType = isDropMoment ? "flash_luma" : ((i % 2 == 1) ? "punch_zoom" : "hard_action"),
                        ZoomFactor = (i % 2 == 1) ? 1.07 : 1.00,
                        SpeedFactor = 1.00
                    });

                    tPos += cutDur;
                }
            }

            // FINAL CUT: LUXURY REVEAL
            {
                var (uStart, uEnd) = GetUsableBounds(revealClip);
                double avail = uEnd - uStart;
                double revDur = Math.Clamp(revealHoldDur, 1.5, Math.Max(1.5, avail));
                double revIn = Math.Clamp(revealClip.BestRevealStartSec, uStart, Math.Max(uStart, uEnd - revDur));

                var prevSame = decisions.Where(d => d.SourceClipId == revealClip.Id).ToList();
                if (prevSame.Any(d => Math.Abs(d.InPointSec - revIn) < revDur * 0.6) && avail > revDur * 1.6)
                {
                    revIn = Math.Max(uStart, uEnd - revDur);
                }

                decisions.Add(new CutDecisionItem
                {
                    Index = cIdx,
                    SourceClipId = revealClip.Id,
                    SourceFilePath = revealClip.FilePath,
                    ThumbnailPath = revealClip.ThumbnailPath,
                    SourceHasAudio = revealClip.HasAudio,
                    SourceClipTotalDuration = revealClip.DurationSeconds,
                    InPointSec = Math.Round(revIn, 2),
                    DurationSec = Math.Round(revDur, 2),
                    TimelineStartSec = Math.Round(tPos, 2),
                    RoleKey = "reveal",
                    RoleBadge = directorModeKey == "viral_drop" ? "✨ SHIMMER-DROP REVEAL" : "✨ FINALE & REVEAL",
                    AiCutReason = $"Höchste Bildruhe & Ausleuchtung ({revealClip.FileName}) für das fertige Unikat",
                    Score = revealClip.QualityScore,
                    TransitionType = directorModeKey == "viral_drop" ? "flash_luma" : "velvet_dip",
                    ZoomFactor = 1.00,
                    SpeedFactor = 0.90
                });
            }

            ApplyTransitionRules(decisions, transitionPreference);
            RecalculateTimelinePositions(decisions);
            return decisions;
        }

        // Ensures no unmasked jump-cuts when two consecutive cuts come from the same source video
        public static void ApplyTransitionRules(List<CutDecisionItem> decisions, string transitionPreference)
        {
            for (int i = 0; i < decisions.Count; i++)
            {
                var cur = decisions[i];
                if (i == 0)
                {
                    cur.TransitionType = "hard_action";
                    continue;
                }

                var prev = decisions[i - 1];
                bool sameSourceAsPrev = string.Equals(cur.SourceClipId, prev.SourceClipId, StringComparison.OrdinalIgnoreCase);

                if (transitionPreference != "auto_director" && !string.IsNullOrEmpty(transitionPreference))
                {
                    cur.TransitionType = transitionPreference;
                    if (transitionPreference == "punch_zoom")
                    {
                        cur.ZoomFactor = (i % 2 == 1) ? 1.08 : 1.00;
                    }
                }
                else
                {
                    // Auto-Director Intelligence: If same clip is cut twice in a row, force 2-Camera Macro Zoom alternation to prevent jump-cuts!
                    if (sameSourceAsPrev && Math.Abs(cur.ZoomFactor - prev.ZoomFactor) < 0.03)
                    {
                        cur.ZoomFactor = prev.ZoomFactor > 1.03 ? 1.00 : 1.08;
                        if (cur.TransitionType == "hard_action")
                        {
                            cur.TransitionType = "punch_zoom";
                        }
                    }
                }
            }
        }

        public static void RecalculateTimelinePositions(List<CutDecisionItem> decisions)
        {
            double pos = 0.0;
            for (int i = 0; i < decisions.Count; i++)
            {
                decisions[i].Index = i + 1;
                decisions[i].TimelineStartSec = Math.Round(pos, 2);
                pos += decisions[i].DurationSec;
            }
        }

        // =========================================================================
        // OPUSCLIP-STYLE VIRALITY & PACING SCORE ENGINE
        // =========================================================================
        public static ViralScoreReport CalculateViralScoreReport(List<CutDecisionItem> cuts, string directorModeKey, bool hasMusic, double origAudioPct)
        {
            if (cuts == null || cuts.Count == 0)
            {
                return new ViralScoreReport
                {
                    OverallViralScore = 0,
                    VerdictBadge = "⏳ Warte auf Rohmaterial",
                    PacingSummary = "Lege deine Aufnahmen in den Ordner Rohmaterial."
                };
            }

            double totalDur = Math.Max(0.5, cuts.Sum(c => c.DurationSec));
            double avgCut = totalDur / cuts.Count;

            // 1. Hook Score (First cut should be 1.1s - 2.2s and high quality)
            var first = cuts[0];
            int hookScore = Math.Clamp(first.Score + (first.DurationSec <= 2.1 ? 6 : -4), 75, 99);

            // 2. Pacing Score (Ideal short-form average cut duration is 1.3s - 2.6s; speech up to 4.0s)
            int pacingScore = (avgCut >= 1.2 && avgCut <= 2.7)
                ? 97
                : (avgCut <= 3.6 ? 91 : 84);
            if (cuts.Count >= 4) pacingScore = Math.Min(99, pacingScore + 2);

            // 3. Transition & Jump-Cut Protection Score
            int unmaskedJumpCuts = 0;
            for (int i = 1; i < cuts.Count; i++)
            {
                if (cuts[i].SourceClipId == cuts[i - 1].SourceClipId &&
                    Math.Abs(cuts[i].ZoomFactor - cuts[i - 1].ZoomFactor) < 0.03 &&
                    cuts[i].TransitionType == "hard_action")
                {
                    unmaskedJumpCuts++;
                }
            }
            int transScore = Math.Clamp(98 - (unmaskedJumpCuts * 8), 78, 99);

            // 4. ASMR & Audio Balance Score
            int audioScore = (hasMusic && origAudioPct >= 15 && origAudioPct <= 60)
                ? 98
                : (origAudioPct >= 80 && directorModeKey == "story_speech" ? 97 : 91);

            int overall = (int)Math.Round(hookScore * 0.32 + pacingScore * 0.28 + transScore * 0.22 + audioScore * 0.18);
            overall = Math.Clamp(overall, 76, 99);

            string badge = overall >= 93
                ? $"🔥 VIRAL-POTENZIAL: {overall}/100 (Exzellent)"
                : $"✨ STUDIO-SCORE: {overall}/100 (Sehr gut)";

            string summary = $"Ø Schnitt: {avgCut:0.1}s · Hook: {first.DurationSec:0.1}s · {cuts.Count} Schnitte · J/L-Audio & 2-Cam-Zoom aktiv";

            return new ViralScoreReport
            {
                OverallViralScore = overall,
                HookRetentionScore = hookScore,
                PacingRhythmScore = pacingScore,
                TransitionFlowScore = transScore,
                AsmrAudioScore = audioScore,
                AverageCutSeconds = Math.Round(avgCut, 2),
                VerdictBadge = badge,
                PacingSummary = summary
            };
        }

        // =========================================================================
        // REAL FFMPEG MASTER RENDERING ENGINE (PARALLEL + SMART TRANSITIONS + J/L AUDIO)
        // =========================================================================
        public static async Task<(bool Success, string OutputPath, string ErrorMessage)> RenderMasterVideoAsync(
            List<CutDecisionItem> cuts,
            VideoRenderOptions options,
            Action<int, string>? onProgress = null,
            CancellationToken ct = default)
        {
            if (cuts == null || cuts.Count == 0)
            {
                return (false, "", "Keine Schnitt-Segmente in der Timeline vorhanden.");
            }

            foreach (var c in cuts)
            {
                if (!ValidateSafeMediaPath(c.SourceFilePath, false))
                {
                    return (false, "", $"Ungültige Quelldatei: {c.SourceFileName}");
                }
            }

            var (ffmpeg, _) = ResolveFfmpegBinaries();
            string outPath = Path.GetFullPath(options.OutputFilePath);
            Directory.CreateDirectory(Path.GetDirectoryName(outPath)!);

            string tempDir = Path.Combine(Path.GetTempPath(), "FunkenwerkVideo_" + Guid.NewGuid().ToString("N")[..10]);
            Directory.CreateDirectory(tempDir);

            try
            {
                int targetW = options.AspectRatioMode == "16_9" ? 1920 : 1080;
                int targetH = options.AspectRatioMode == "16_9" ? 1080 : 1920;

                string colorFilter = options.ColorGradingMode switch
                {
                    "atelier_gold" => ",eq=contrast=1.06:brightness=-0.010:saturation=1.08,colorbalance=rh=0.032:gh=0.016:bh=-0.024",
                    "matte_luxury" => ",eq=contrast=1.08:brightness=-0.018:saturation=0.94,colorbalance=rh=0.025:gh=0.012:bh=-0.015",
                    "crystal_bright" => ",eq=contrast=1.04:brightness=0.022:saturation=1.05",
                    _ => ""
                };

                var preparedSegmentFiles = new string[cuts.Count];
                int completedSegs = 0;
                int maxParallelSegs = Math.Clamp(Environment.ProcessorCount / 2, 2, 3);
                using var sem = new SemaphoreSlim(maxParallelSegs, maxParallelSegs);

                // Step 1: Parallel Segment Processing (Fast Keyframe Seek + 2-Cam Zoom + Speed Ramp + Transition FX + ASMR Audio Mastering)
                var segTasks = Enumerable.Range(0, cuts.Count).Select(async i =>
                {
                    await sem.WaitAsync(ct);
                    try
                    {
                        ct.ThrowIfCancellationRequested();
                        var cut = cuts[i];
                        string segFile = Path.Combine(tempDir, $"seg_{i:D3}.mp4");

                        double speed = Math.Clamp(cut.SpeedFactor, 0.70, 1.50);
                        double zoom = Math.Clamp(cut.ZoomFactor, 1.00, 1.25);
                        double rawReadDuration = Math.Min(
                            Math.Max(0.4, cut.SourceClipTotalDuration - cut.InPointSec),
                            cut.DurationSec * speed);

                        string ssStr = cut.InPointSec.ToString("0.000", CultureInfo.InvariantCulture);
                        string rawDurStr = rawReadDuration.ToString("0.000", CultureInfo.InvariantCulture);
                        string outDurStr = cut.DurationSec.ToString("0.000", CultureInfo.InvariantCulture);

                        int scaledW = (int)(Math.Round((targetW * zoom) / 2.0) * 2);
                        int scaledH = (int)(Math.Round((targetH * zoom) / 2.0) * 2);

                        string scaleFilter;
                        if (options.AspectRatioMode == "9_16_pad")
                        {
                            scaleFilter = $"split[bg][fg];[bg]scale={targetW}:{targetH}:force_original_aspect_ratio=increase,crop={targetW}:{targetH},boxblur=24:4,eq=brightness=-0.28[bg2];[fg]scale={scaledW}:{scaledH}:force_original_aspect_ratio=decrease,crop=w='min(iw,{targetW})':h='min(ih,{targetH})'[fg2];[bg2][fg2]overlay=(W-w)/2:(H-h)/2,setsar=1,fps=60{colorFilter}";
                        }
                        else
                        {
                            scaleFilter = $"scale={scaledW}:{scaledH}:force_original_aspect_ratio=increase,crop={targetW}:{targetH},setsar=1,fps=60{colorFilter}";
                        }

                        // Apply Speed Factor if != 1.0
                        if (Math.Abs(speed - 1.0) >= 0.03)
                        {
                            string ptsMult = (1.0 / speed).ToString("0.000", CultureInfo.InvariantCulture);
                            scaleFilter += $",setpts={ptsMult}*PTS";
                        }

                        // Apply clean visual entrance/exit transitions per cut
                        string effTransition = options.TransitionMode == "auto_director" ? cut.TransitionType : options.TransitionMode;
                        if (cut.DurationSec >= 0.9)
                        {
                            if (effTransition == "velvet_dip")
                            {
                                string foStart = Math.Max(0.1, cut.DurationSec - 0.18).ToString("0.00", CultureInfo.InvariantCulture);
                                scaleFilter += $",fade=t=in:st=0:d=0.20:color=black,fade=t=out:st={foStart}:d=0.16:color=black";
                            }
                            else if (effTransition == "flash_luma")
                            {
                                scaleFilter += ",fade=t=in:st=0:d=0.15:color=white";
                            }
                            else if (effTransition == "whip_flow")
                            {
                                // Subtle brightness motion accent at the first 0.12s of a whip cut
                                scaleFilter += ",fade=t=in:st=0:d=0.10:color=black";
                            }
                        }

                        // Audio filter: ASMR & Voice Clarity Enhancer + Speed Tempo + J/L Micro-Anti-Click Fade
                        var afParts = new List<string> { "aresample=48000" };
                        if (options.EnableAsmrAudioMastering)
                        {
                            afParts.Add("highpass=f=75");
                            afParts.Add("acompressor=threshold=-20dB:ratio=2.0:attack=15:release=180:makeup=1.4dB");
                        }
                        if (Math.Abs(speed - 1.0) >= 0.03)
                        {
                            afParts.Add($"atempo={speed.ToString("0.00", CultureInfo.InvariantCulture)}");
                        }
                        // 45ms smooth audio fade-in/out on every cut prevents any digital click and mimics J/L smoothing
                        string aFadeOutStart = Math.Max(0.05, cut.DurationSec - 0.05).ToString("0.000", CultureInfo.InvariantCulture);
                        afParts.Add($"afade=t=in:st=0:d=0.045,afade=t=out:st={aFadeOutStart}:d=0.045");
                        afParts.Add("aformat=sample_fmts=fltp:channel_layouts=stereo");
                        string afChain = string.Join(",", afParts);

                        var argList = new List<string> { "-y", "-threads", "2", "-ss", ssStr, "-t", rawDurStr, "-i", cut.SourceFilePath };
                        if (cut.SourceHasAudio)
                        {
                            argList.AddRange(new[]
                            {
                                "-t", outDurStr,
                                "-vf", scaleFilter,
                                "-af", afChain,
                                "-c:v", "libx264", "-preset", "veryfast", "-crf", "17", "-pix_fmt", "yuv420p",
                                "-c:a", "aac", "-b:a", "192k",
                                segFile
                            });
                        }
                        else
                        {
                            argList.AddRange(new[]
                            {
                                "-f", "lavfi", "-t", outDurStr, "-i", "anullsrc=channel_layout=stereo:sample_rate=48000",
                                "-t", outDurStr,
                                "-vf", scaleFilter,
                                "-map", "0:v:0", "-map", "1:a:0", "-shortest",
                                "-c:v", "libx264", "-preset", "veryfast", "-crf", "17", "-pix_fmt", "yuv420p",
                                "-c:a", "aac", "-b:a", "192k",
                                segFile
                            });
                        }

                        var (ok, _, err) = await RunProcessSafeAsync(ffmpeg, argList, 90000, ct);
                        if (!ok || !File.Exists(segFile))
                        {
                            throw new InvalidOperationException($"Fehler bei Segment #{i + 1} ({cut.SourceFileName}): {err}");
                        }

                        preparedSegmentFiles[i] = segFile;
                        int doneNow = Interlocked.Increment(ref completedSegs);
                        int pct = (int)Math.Round((doneNow / (double)(cuts.Count + 1)) * 82.0);
                        onProgress?.Invoke(pct, $"Schneide & veredle Segment {doneNow}/{cuts.Count}: {cut.RoleBadge} ({cut.TransitionBadgeLabel})...");
                    }
                    finally
                    {
                        sem.Release();
                    }
                });

                await Task.WhenAll(segTasks);

                // Step 2: Concatenate all cut segments & mix with Seelenfunke Studio Music + Fade-Out
                onProgress?.Invoke(86, "Füge KI-Schnitt-Timeline zusammen & mische Studio-Audio...");

                string concatListPath = Path.Combine(tempDir, "concat_list.txt");
                var concatSb = new StringBuilder();
                foreach (var sf in preparedSegmentFiles)
                {
                    string safeConcatPath = sf.Replace('\\', '/').Replace("'", "'\\''");
                    concatSb.AppendLine($"file '{safeConcatPath}'");
                }
                await File.WriteAllTextAsync(concatListPath, concatSb.ToString(), new UTF8Encoding(false), ct);

                string joinedVideoPath = Path.Combine(tempDir, "joined_raw.mp4");
                var (joinOk, _, joinErr) = await RunProcessSafeAsync(
                    ffmpeg,
                    new[] { "-y", "-f", "concat", "-safe", "0", "-i", concatListPath, "-c", "copy", joinedVideoPath },
                    45000,
                    ct);

                if (!joinOk || !File.Exists(joinedVideoPath))
                {
                    return (false, "", "Fehler beim Zusammenfügen der Segmente: " + joinErr);
                }

                double totalVideoDur = cuts.Sum(c => c.DurationSec);
                string totalDurStr = totalVideoDur.ToString("0.00", CultureInfo.InvariantCulture);
                string fadeOutStartStr = Math.Max(0.2, totalVideoDur - 1.2).ToString("0.00", CultureInfo.InvariantCulture);

                bool hasMusicFile = ValidateSafeMediaPath(options.MusicFilePath, true) && options.MusicVolumePercent > 0.5;
                double origVol = Math.Clamp(options.OriginalAudioVolumePercent / 100.0, 0.0, 1.5);
                double musVol = Math.Clamp(options.MusicVolumePercent / 100.0, 0.0, 1.5);

                string origVolStr = origVol.ToString("0.00", CultureInfo.InvariantCulture);
                string musVolStr = musVol.ToString("0.00", CultureInfo.InvariantCulture);

                var masterArgList = new List<string> { "-y", "-threads", "0", "-i", joinedVideoPath };
                if (hasMusicFile)
                {
                    masterArgList.AddRange(new[]
                    {
                        "-stream_loop", "-1", "-t", totalDurStr, "-i", options.MusicFilePath!,
                        "-filter_complex",
                        $"[0:a]volume={origVolStr}[a_orig];[1:a]volume={musVolStr},afade=t=in:st=0:d=0.30,afade=t=out:st={fadeOutStartStr}:d=1.2[a_mus];[a_orig][a_mus]amix=inputs=2:duration=first:dropout_transition=0:normalize=0[a_out]",
                        "-map", "0:v:0", "-map", "[a_out]",
                        "-c:v", "copy",
                        "-c:a", "aac", "-b:a", "256k",
                        "-movflags", "+faststart",
                        outPath
                    });
                }
                else
                {
                    masterArgList.AddRange(new[]
                    {
                        "-af", $"volume={origVolStr},afade=t=out:st={fadeOutStartStr}:d=0.8",
                        "-c:v", "copy",
                        "-c:a", "aac", "-b:a", "256k",
                        "-movflags", "+faststart",
                        outPath
                    });
                }

                var (masterOk, _, masterErr) = await RunProcessSafeAsync(ffmpeg, masterArgList, 60000, ct);
                if (!masterOk || !File.Exists(outPath))
                {
                    return (false, "", "Fehler beim Audio-Mastering & MP4-Export: " + masterErr);
                }

                onProgress?.Invoke(100, "Fertig! Dein Video wurde in höchster Studio-Qualität gespeichert.");
                return (true, outPath, "");
            }
            catch (OperationCanceledException)
            {
                return (false, "", "Vorgang abgebrochen.");
            }
            catch (Exception ex)
            {
                return (false, "", ex.Message);
            }
            finally
            {
                try
                {
                    if (Directory.Exists(tempDir)) Directory.Delete(tempDir, true);
                }
                catch { }
            }
        }

        // =========================================================================
        // SECURE PROCESS EXECUTION WITH ARGUMENTLIST & TIMEOUT PROTECTION
        // =========================================================================
        public static async Task<(bool Ok, string Stdout, string Stderr)> RunProcessSafeAsync(
            string exePath,
            IEnumerable<string> args,
            int timeoutMs = 60000,
            CancellationToken ct = default)
        {
            var psi = new ProcessStartInfo
            {
                FileName = exePath,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                UseShellExecute = false,
                CreateNoWindow = true,
                StandardOutputEncoding = Encoding.UTF8,
                StandardErrorEncoding = Encoding.UTF8
            };

            foreach (var a in args)
            {
                psi.ArgumentList.Add(a);
            }

            using var proc = Process.Start(psi);
            if (proc == null)
            {
                return (false, "", "Prozess konnte nicht gestartet werden.");
            }

            using var linkedCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
            linkedCts.CancelAfter(timeoutMs);

            try
            {
                var stdoutTask = proc.StandardOutput.ReadToEndAsync(linkedCts.Token);
                var stderrTask = proc.StandardError.ReadToEndAsync(linkedCts.Token);

                await Task.WhenAll(stdoutTask, stderrTask, proc.WaitForExitAsync(linkedCts.Token));

                string stdout = stdoutTask.Result;
                string stderr = stderrTask.Result;

                if (proc.ExitCode != 0)
                {
                    var lines = stderr.Split('\n', StringSplitOptions.RemoveEmptyEntries);
                    string tail = string.Join(" | ", lines.TakeLast(3)).Trim();
                    return (false, stdout, tail);
                }

                return (true, stdout, stderr);
            }
            catch (OperationCanceledException)
            {
                try { proc.Kill(entireProcessTree: true); } catch { }
                throw;
            }
        }
    }
}
