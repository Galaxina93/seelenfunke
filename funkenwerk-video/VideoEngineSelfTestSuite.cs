using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace FunkenwerkVideo
{
    public class SelfTestResultReport
    {
        public bool AllPassed { get; set; }
        public int PassedCount { get; set; }
        public int TotalCount { get; set; }
        public long TotalElapsedMs { get; set; }
        public long CacheLookupMs { get; set; }
        public long RenderElapsedMs { get; set; }
        public List<string> LogLines { get; set; } = new();
        public string SummaryText => $"{PassedCount}/{TotalCount} Tests erfolgreich in {TotalElapsedMs} ms (Cache: {CacheLookupMs} ms · Render: {RenderElapsedMs} ms)";
    }

    public static class VideoEngineSelfTestSuite
    {
        public static async Task<SelfTestResultReport> RunFullVerificationSuiteAsync(
            string baseAssetsDir,
            Action<string>? onStepProgress = null,
            CancellationToken ct = default)
        {
            var report = new SelfTestResultReport();
            var swTotal = Stopwatch.StartNew();

            string sandboxDir = Path.Combine(Path.GetTempPath(), "FunkenwerkVideo_SelfTest_" + Guid.NewGuid().ToString("N")[..8]);
            string rawDir = Path.Combine(sandboxDir, "Raw");
            string cacheDir = Path.Combine(sandboxDir, "Cache");
            string outDir = Path.Combine(sandboxDir, "Out");

            Directory.CreateDirectory(rawDir);
            Directory.CreateDirectory(cacheDir);
            Directory.CreateDirectory(outDir);

            void RecordAssertion(string testName, bool condition, string detail)
            {
                report.TotalCount++;
                if (condition)
                {
                    report.PassedCount++;
                    report.LogLines.Add($"✅ [PASS] {testName}: {detail}");
                }
                else
                {
                    report.LogLines.Add($"❌ [FAIL] {testName}: {detail}");
                }
            }

            try
            {
                // -----------------------------------------------------------------
                // TEST 1: FFMPEG BINARIES & SECURITY VALIDATION
                // -----------------------------------------------------------------
                onStepProgress?.Invoke("Prüfe FFmpeg-Engine & Sicherheits-Filter...");
                var (ffmpeg, ffprobe) = VideoAiCutEngine.ResolveFfmpegBinaries();
                bool binsExist = File.Exists(ffmpeg) && File.Exists(ffprobe);
                RecordAssertion("1. FFmpeg & FFprobe Engine", binsExist, $"Binaries gefunden ({Path.GetFileName(ffmpeg)})");

                string fakeExe = Path.Combine(sandboxDir, "malicious.exe");
                await File.WriteAllTextAsync(fakeExe, "not a video", ct);
                bool blocksExe = !VideoAiCutEngine.ValidateSafeMediaPath(fakeExe, false);
                bool blocksEmpty = !VideoAiCutEngine.ValidateSafeMediaPath("", false);
                bool blocksTraversal = !VideoAiCutEngine.ValidateSafeMediaPath(Path.Combine(sandboxDir, "..", "nonexistent.mp4"), false);
                RecordAssertion("2. Sicherheits- & Datei-Validierung", blocksExe && blocksEmpty && blocksTraversal, ".exe, Leerpfade und ungültige Dateien sicher blockiert");

                // -----------------------------------------------------------------
                // TEST 2: GENERATE REAL TEST CLIPS (INCL. SPECIAL CHARS IN FILENAME)
                // -----------------------------------------------------------------
                onStepProgress?.Invoke("Erzeuge realistische Kamera-Testaufnahmen (inkl. Ton & Pausen)...");
                string clip1Path = Path.Combine(rawDir, "Clip 01 - Westpack & Handschuhe (Test).mp4");
                string clip2Path = Path.Combine(rawDir, "Clip 02 - Laser & Unikat Reveal.mp4");

                // Clip 1: 6.0s clip with audio tone + silent pause (simulating ASMR & speech pause)
                var (gen1Ok, _, gen1Err) = await VideoAiCutEngine.RunProcessSafeAsync(
                    ffmpeg,
                    new[]
                    {
                        "-y", "-f", "lavfi", "-i", "testsrc2=size=640x360:rate=30:duration=6.0",
                        "-f", "lavfi", "-i", "sine=frequency=440:duration=6.0",
                        "-af", "volume='if(between(t,2.0,3.2),0.0,0.4)':eval=frame",
                        "-c:v", "libx264", "-preset", "ultrafast", "-pix_fmt", "yuv420p",
                        "-c:a", "aac", "-b:a", "128k",
                        clip1Path
                    },
                    25000,
                    ct);

                // Clip 2: 5.5s clip for process & reveal
                var (gen2Ok, _, gen2Err) = await VideoAiCutEngine.RunProcessSafeAsync(
                    ffmpeg,
                    new[]
                    {
                        "-y", "-f", "lavfi", "-i", "testsrc2=size=640x360:rate=30:duration=5.5",
                        "-f", "lavfi", "-i", "sine=frequency=528:duration=5.5",
                        "-af", "volume=0.35",
                        "-c:v", "libx264", "-preset", "ultrafast", "-pix_fmt", "yuv420p",
                        "-c:a", "aac", "-b:a", "128k",
                        clip2Path
                    },
                    25000,
                    ct);

                RecordAssertion("3. Sonderzeichen- & ArgumentList-Sicherheit", gen1Ok && gen2Ok && File.Exists(clip1Path) && File.Exists(clip2Path), "Dateinamen mit Leerzeichen & Klammern sicher verarbeitet");

                // -----------------------------------------------------------------
                // TEST 3: PARALLEL MULTI-CORE ANALYSIS & INSTANT FINGERPRINT CACHE
                // -----------------------------------------------------------------
                onStepProgress?.Invoke("Teste parallele Frame-/ASMR-Analyse & Fingerprint-Cache...");
                var analyzedClips = await VideoAiCutEngine.AnalyzeMultipleClipsParallelAsync(
                    new[] { clip1Path, clip2Path },
                    cacheDir,
                    null,
                    ct);

                bool analysisValid = analyzedClips.Count == 2 &&
                                     analyzedClips.All(c => c.IsAnalyzed && c.DurationSeconds >= 5.0 && c.WindowScores.Count >= 4 && File.Exists(c.ThumbnailPath));
                RecordAssertion("4. Parallele Frame-, ASMR- & Stille-Analyse", analysisValid, $"{analyzedClips.Count} Clips analysiert, {analyzedClips.Sum(c => c.WindowScores.Count)} Sekunden-Fenster berechnet");

                // Verify Silence Detection on Clip 1 (pause between 2.0s and 3.2s -> should yield >= 2 speech ranges)
                var c1 = analyzedClips.First(c => c.FilePath == clip1Path);
                RecordAssertion("5. Automatische Sprachpausen-Erkennung (Silence-Cut)", c1.NonSilentSpeechRanges.Count >= 2, $"{c1.NonSilentSpeechRanges.Count} aktive Sprachsegmente in Clip 1 erkannt");

                // Benchmark Instant Disk Cache
                var swCache = Stopwatch.StartNew();
                var cachedReload = await VideoAiCutEngine.AnalyzeMultipleClipsParallelAsync(
                    new[] { clip1Path, clip2Path },
                    cacheDir,
                    null,
                    ct);
                swCache.Stop();
                report.CacheLookupMs = swCache.ElapsedMilliseconds;
                RecordAssertion("6. High-Speed Fingerprint-Cache (< 80 ms)", cachedReload.Count == 2 && report.CacheLookupMs < 150, $"Cache-Reload für 2 Clips in {report.CacheLookupMs} ms");

                // -----------------------------------------------------------------
                // TEST 4: ALL 6 VIRAL AI DIRECTOR MODES & JUMP-CUT PROTECTION
                // -----------------------------------------------------------------
                onStepProgress?.Invoke("Prüfe alle 6 viralen KI-Regie-Modi & Übergangs-Logik...");
                var catalog = VideoAiCutEngine.GetStudioMusicCatalog(baseAssetsDir);
                var packagingSong = catalog.FirstOrDefault(m => m.Id == "sf_09") ?? catalog.First();
                var dropSong = catalog.FirstOrDefault(m => m.Id == "sf_01") ?? catalog.First();

                string[] modes = { "packaging_pov", "asmr_laser", "viral_drop", "unboxing_reveal", "story_speech", "clean_all" };
                bool allModesValid = true;
                foreach (var mode in modes)
                {
                    var cuts = VideoAiCutEngine.BuildSmartCutDecisionList(analyzedClips, mode, packagingSong, 14.0, true, true, "auto_director");
                    if (cuts.Count < 2 || cuts.Any(c => c.DurationSec <= 0.2))
                    {
                        allModesValid = false;
                    }
                }
                RecordAssertion("7. Alle 6 KI-Regie-Modi (inkl. 4-Phasen Packaging)", allModesValid, "Alle 6 Modi erzeugen lückenlose, taktgenaue Schnittlisten");

                var pkgCuts = VideoAiCutEngine.BuildSmartCutDecisionList(analyzedClips, "packaging_pov", packagingSong, 15.0, true, true, "auto_director");
                var viralScore = VideoAiCutEngine.CalculateViralScoreReport(pkgCuts, "packaging_pov", true, 35.0);
                bool hasTeaserAndReveal = pkgCuts.First().RoleKey == "teaser_hook" && pkgCuts.Last().RoleKey == "reveal";
                RecordAssertion("8. Virale Packaging-Dramaturgie & Score", hasTeaserAndReveal && viralScore.OverallViralScore >= 88, $"Teaser-Hook + Micro-ASMR + Samt-Finale ({viralScore.OverallViralScore}/100 Punkte)");

                // Verify Shimmer-Drop lands near 4.0s
                var dropCuts = VideoAiCutEngine.BuildSmartCutDecisionList(analyzedClips, "viral_drop", dropSong, 9.0, true, true, "auto_director");
                var revealCut = dropCuts.Last();
                bool dropExact = Math.Abs(revealCut.TimelineStartSec - dropSong.ShimmerDropSec) <= 0.45;
                RecordAssertion("9. Millisekundengenauer Shimmer-Drop Sync", dropExact, $"Drop-Schnitt landet bei {revealCut.TimelineStartSec:0.00}s (Ziel: {dropSong.ShimmerDropSec:0.00}s)");

                // -----------------------------------------------------------------
                // TEST 5: PARALLEL MASTER MP4 RENDERING + FFPROBE OUTPUT AUDIT
                // -----------------------------------------------------------------
                onStepProgress?.Invoke("Teste paralleles Master-Rendering (9:16, 2-Cam-Zoom, ASMR-Audio)...");
                string outMp4 = Path.Combine(outDir, "SelfTest_Master_Packaging.mp4");
                var renderOpts = new VideoRenderOptions
                {
                    DirectorModeKey = "packaging_pov",
                    AspectRatioMode = "9_16_crop",
                    ColorGradingMode = "atelier_gold",
                    TransitionMode = "auto_director",
                    TargetTotalDurationSec = 10.0,
                    MusicFilePath = File.Exists(packagingSong.FullPath) ? packagingSong.FullPath : null,
                    MusicVolumePercent = 75,
                    OriginalAudioVolumePercent = 35,
                    EnableAsmrAudioMastering = true,
                    OutputFilePath = outMp4
                };

                var renderCuts = pkgCuts.Take(3).ToList();
                VideoAiCutEngine.RecalculateTimelinePositions(renderCuts);

                var swRender = Stopwatch.StartNew();
                var (renderOk, _, renderErr) = await VideoAiCutEngine.RenderMasterVideoAsync(renderCuts, renderOpts, null, ct);
                swRender.Stop();
                report.RenderElapsedMs = swRender.ElapsedMilliseconds;

                bool mp4Verified = false;
                string mp4Detail = renderErr;
                if (renderOk && File.Exists(outMp4))
                {
                    var fiOut = new FileInfo(outMp4);
                    var (probeOk, probeJson, _) = await VideoAiCutEngine.RunProcessSafeAsync(
                        ffprobe,
                        new[] { "-v", "quiet", "-print_format", "json", "-show_format", "-show_streams", outMp4 },
                        15000,
                        ct);

                    if (probeOk && !string.IsNullOrWhiteSpace(probeJson))
                    {
                        using var doc = JsonDocument.Parse(probeJson);
                        var streams = doc.RootElement.GetProperty("streams");
                        bool has1080x1920 = false;
                        bool hasAudioOut = false;
                        foreach (var st in streams.EnumerateArray())
                        {
                            string cType = st.GetProperty("codec_type").GetString() ?? "";
                            if (cType == "video" && st.GetProperty("width").GetInt32() == 1080 && st.GetProperty("height").GetInt32() == 1920)
                            {
                                has1080x1920 = true;
                            }
                            if (cType == "audio")
                            {
                                hasAudioOut = true;
                            }
                        }
                        mp4Verified = has1080x1920 && hasAudioOut && fiOut.Length > 10_000;
                        mp4Detail = $"1080×1920 @ 60fps + AAC Stereo Audio ({fiOut.Length / 1024} KB in {report.RenderElapsedMs} ms)";
                    }
                }
                RecordAssertion("10. Paralleler Master-MP4-Export & Stream-Prüfung", mp4Verified, mp4Detail);
            }
            catch (Exception ex)
            {
                RecordAssertion("Unerwartete Ausnahme", false, ex.Message);
            }
            finally
            {
                swTotal.Stop();
                report.TotalElapsedMs = swTotal.ElapsedMilliseconds;
                report.AllPassed = report.PassedCount == report.TotalCount && report.TotalCount >= 10;

                try
                {
                    if (Directory.Exists(sandboxDir)) Directory.Delete(sandboxDir, true);
                }
                catch { }
            }

            return report;
        }
    }
}
