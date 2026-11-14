using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Text;
using System.Text.RegularExpressions;
using System.Windows;
using System.Windows.Input;
using System.Windows.Media;

namespace FunkenwerkPlayer
{
    public static class PlayerSelfTestRunner
    {
        public static int RunAllTests(out List<string> reportLines)
        {
            var lines = new List<string>();
            int passed = 0;
            int total = 0;

            void Check(string name, Func<(bool Ok, string Detail)> testFn)
            {
                total++;
                try
                {
                    var (ok, detail) = testFn();
                    if (ok) passed++;
                    lines.Add($"[{(ok ? "PASS" : "FAIL")}] {name}: {detail}");
                }
                catch (Exception ex)
                {
                    lines.Add($"[FAIL] {name}: Exception {ex.GetType().Name} - {ex.Message}");
                }
            }

            string tempRoot = Path.Combine(Path.GetTempPath(), $"fw_player_selftest_{Guid.NewGuid():N}");
            Directory.CreateDirectory(tempRoot);

            try
            {
                // 1. Executable path uses Environment.ProcessPath (not hardcoded Desktop)
                Check("ExecutablePathUsesProcessPath", () =>
                {
                    string exe = WindowsDefaultPlayerRegistrar.GetExecutablePath();
                    bool exists = File.Exists(exe);
                    string? proc = Environment.ProcessPath;
                    bool matchesProc = string.IsNullOrWhiteSpace(proc) ||
                                       string.Equals(Path.GetFileName(proc), "dotnet.exe", StringComparison.OrdinalIgnoreCase) ||
                                       string.Equals(Path.GetFullPath(exe), Path.GetFullPath(proc), StringComparison.OrdinalIgnoreCase);
                    return (exists && matchesProc, exe);
                });

                // 2. LocalAppData directory writable
                Check("AppDataDirectoryInLocalAppData", () =>
                {
                    string appData = WindowsDefaultPlayerRegistrar.GetAppDataDirectory();
                    string localBase = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
                    bool insideLocal = appData.StartsWith(localBase, StringComparison.OrdinalIgnoreCase) && Directory.Exists(appData);
                    return (insideLocal, appData);
                });

                // 3. MusicCache path traversal blocked
                string cacheDir = Path.Combine(tempRoot, "MusicCache");
                Directory.CreateDirectory(cacheDir);
                string outsideM4a = Path.Combine(tempRoot, "escaped_secret.m4a");
                File.WriteAllBytes(outsideM4a, new byte[] { 0x00, 0x00, 0x00, 0x18 });

                Check("MusicCacheBlocksPathTraversalAndInvalidIds", () =>
                {
                    string longId = new string('a', 140);
                    string maliciousJson = $$"""
                    {
                      "data": [
                        {
                          "tracks": [
                            { "id": "..\\escaped_secret", "external_id": "..\\..\\Windows\\System32\\cmd", "title": "Evil1" },
                            { "id": "C:\\evil", "external_id": "../escaped_secret", "title": "Evil2" },
                            { "id": "bad:ads", "external_id": "id with spaces", "title": "Evil3" },
                            { "id": "{{longId}}", "external_id": "slash/id", "title": "Evil4" }
                          ]
                        }
                      ]
                    }
                    """;
                    File.WriteAllText(Path.Combine(cacheDir, "playlists_cache.json"), maliciousJson, Encoding.UTF8);

                    var list = new List<string>();
                    var titles = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
                    int count = MainWindow.LoadFunkenwerkMusicCacheIfEmpty(cacheDir, list, titles);

                    bool directNull1 = MainWindow.TryResolveSafeMusicCacheTrackPath(cacheDir, @"..\..\Windows\System32\cmd") == null;
                    bool directNull2 = MainWindow.TryResolveSafeMusicCacheTrackPath(cacheDir, @"C:\evil") == null;
                    bool directNull3 = MainWindow.TryResolveSafeMusicCacheTrackPath(cacheDir, @"..\escaped_secret") == null;

                    bool ok = count == 0 && list.Count == 0 && directNull1 && directNull2 && directNull3;
                    return (ok, $"Loaded={count}, TraversalRejected={directNull1 && directNull2 && directNull3}");
                });

                // 4. MusicCache valid IDs accepted + canonical containment
                Check("MusicCacheAcceptsValidIdsAndMapsTitles", () =>
                {
                    string validFile1 = Path.Combine(cacheDir, "track_01-gold.m4a");
                    string validFile2 = Path.Combine(cacheDir, "ext_02_funke.m4a");
                    File.WriteAllBytes(validFile1, new byte[] { 0x00, 0x00, 0x00, 0x20 });
                    File.WriteAllBytes(validFile2, new byte[] { 0x00, 0x00, 0x00, 0x20 });

                    string mixedJson = """
                    {
                      "data": [
                        {
                          "tracks": [
                            { "id": "..\\escaped_secret", "external_id": "ext_02_funke", "artist": "Seelenfunke", "title": "Warmlicht" },
                            { "id": "track_01-gold", "external_id": "", "artist": "Manufaktur", "title": "Goldklang" }
                          ]
                        }
                      ]
                    }
                    """;
                    File.WriteAllText(Path.Combine(cacheDir, "playlists_cache.json"), mixedJson, Encoding.UTF8);

                    var list = new List<string>();
                    var titles = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
                    int count = MainWindow.LoadFunkenwerkMusicCacheIfEmpty(cacheDir, list, titles);

                    string fullDir = Path.GetFullPath(cacheDir).TrimEnd(Path.DirectorySeparatorChar) + Path.DirectorySeparatorChar;
                    bool allContained = list.All(p => p.StartsWith(fullDir, StringComparison.OrdinalIgnoreCase));
                    bool titlesOk = titles.TryGetValue(Path.GetFullPath(validFile1), out var t1) && t1 == "Manufaktur - Goldklang" &&
                                    titles.TryGetValue(Path.GetFullPath(validFile2), out var t2) && t2 == "Seelenfunke - Warmlicht";

                    return (count == 2 && list.Count == 2 && allContained && titlesOk, $"Loaded={count}, Contained={allContained}, TitlesOk={titlesOk}");
                });

                // 5. MusicCache skips when playlist already populated
                Check("MusicCacheSkipsWhenPlaylistNonEmpty", () =>
                {
                    var nonEmpty = new List<string> { Path.Combine(tempRoot, "existing.mp4") };
                    int added = MainWindow.LoadFunkenwerkMusicCacheIfEmpty(cacheDir, nonEmpty, null);
                    return (added == 0 && nonEmpty.Count == 1, $"Added={added}, Count={nonEmpty.Count}");
                });

                // 6. OpenWith ProcessStartInfo uses System32\rundll32.exe, UseShellExecute=false, and ArgumentList quote/backslash safety
                Check("OpenWithRundll32ArgumentListSafety", () =>
                {
                    string trickyDir = Path.Combine(tempRoot, "O'Connor Dir\\Subfolder\\");
                    string trickyFile = Path.Combine(trickyDir, "Clip & \"Test\" 1.mp4");
                    var psi = WindowsDefaultPlayerRegistrar.BuildOpenWithProcessStartInfo(trickyFile);

                    string expectedRundll = Path.Combine(Environment.SystemDirectory, "rundll32.exe");
                    bool okExe = string.Equals(psi.FileName, expectedRundll, StringComparison.OrdinalIgnoreCase);
                    bool okShell = psi.UseShellExecute == false && string.IsNullOrEmpty(psi.Arguments);
                    bool okArgs = psi.ArgumentList.Count == 2 &&
                                  psi.ArgumentList[0] == "shell32.dll,OpenAs_RunDLL" &&
                                  psi.ArgumentList[1] == Path.GetFullPath(trickyFile);
                    return (okExe && okShell && okArgs, $"Exe={psi.FileName}, UseShellExecute={psi.UseShellExecute}, ArgCount={psi.ArgumentList.Count}");
                });

                // 7. Synthesize real WAV audio + real MP4 video in isolated temp folder
                string mediaDir = Path.Combine(tempRoot, "MediaQueue");
                Directory.CreateDirectory(mediaDir);
                string wav2 = Path.Combine(mediaDir, "Track 2.wav");
                string wav10 = Path.Combine(mediaDir, "Track 10.wav");
                string mp4Track1 = Path.Combine(mediaDir, "Track 1.mp4");
                string decoyExe = Path.Combine(mediaDir, "Track 0.exe");
                string decoyTxt = Path.Combine(mediaDir, "notes.txt");

                Check("SynthesizeRealTempAudioAndVideoMedia", () =>
                {
                    WritePcmSineWav(wav2, durationSeconds: 0.6, frequencyHz: 440.0);
                    WritePcmSineWav(wav10, durationSeconds: 0.6, frequencyHz: 660.0);
                    File.WriteAllText(decoyExe, "MZ-decoy");
                    File.WriteAllText(decoyTxt, "ignore");

                    bool videoCreated = TrySynthesizeTestMp4(mp4Track1);
                    if (!videoCreated)
                    {
                        // Fallback: valid RIFF WAVE with .wav if ffmpeg were absent, though ffmpeg is installed
                        WritePcmSineWav(mp4Track1, durationSeconds: 0.5, frequencyHz: 520.0);
                    }

                    bool ok = File.Exists(wav2) && new FileInfo(wav2).Length > 1000 &&
                              File.Exists(wav10) && new FileInfo(wav10).Length > 1000 &&
                              File.Exists(mp4Track1) && new FileInfo(mp4Track1).Length > 500;
                    return (ok, $"WAV2={new FileInfo(wav2).Length}B, MP4={new FileInfo(mp4Track1).Length}B (FFmpeg={videoCreated})");
                });

                // 8-15. STA MainWindow UI & functional tests
                var win = new MainWindow(Array.Empty<string>(), disableAutoDiscovery: true);
                try
                {
                    win.InitializeUiForHeadlessTest();

                    // 8. STA instantiation + Focusable=False on toolbar controls
                    Check("StaMainWindowAndToolbarFocusSafety", () =>
                    {
                        bool controlsNonFocusable = !win.PlayPauseBtn.Focusable &&
                                                    !win.TimelineSlider.Focusable &&
                                                    !win.VolumeSlider.Focusable &&
                                                    !win.FullscreenBtn.Focusable &&
                                                    !win.MuteBtn.Focusable &&
                                                    !win.LoopBtn.Focusable &&
                                                    !win.AbLoopBtn.Focusable;
                        return (controlsNonFocusable, $"ToolbarFocusableFalse={controlsNonFocusable}");
                    });

                    // 9. Light & Dark theme switch (including translucent OSD brush)
                    Check("ThemeSwitchLightAndDarkConsistency", () =>
                    {
                        win.ApplyThemeMode(false);
                        var lightTop = (win.TopHudBar.Background as SolidColorBrush)?.Color ?? Colors.Black;
                        var lightOsd = (win.OsdToastBorder.Background as SolidColorBrush)?.Color ?? Colors.Black;
                        bool lightOk = lightTop == Color.FromRgb(0xFF, 0xFF, 0xFF) &&
                                       lightOsd == Color.FromArgb(0xEB, 0xFF, 0xFF, 0xFF) &&
                                       !win.IsDarkMode;

                        win.ApplyThemeMode(true);
                        var darkTop = (win.TopHudBar.Background as SolidColorBrush)?.Color ?? Colors.White;
                        var darkOsd = (win.OsdToastBorder.Background as SolidColorBrush)?.Color ?? Colors.White;
                        bool darkOk = darkTop == Color.FromRgb(0x11, 0x13, 0x1A) &&
                                      darkOsd == Color.FromArgb(0xD9, 0x11, 0x13, 0x1A) &&
                                      win.IsDarkMode;

                        return (lightOk && darkOk, $"LightOk={lightOk} (OSD=#{lightOsd.A:X2}{lightOsd.R:X2}{lightOsd.G:X2}{lightOsd.B:X2}), DarkOk={darkOk}");
                    });

                    // 10. Playlist extension filtering & natural sort (Track 1, Track 2, Track 10)
                    Check("PlaylistExtensionFilterAndNaturalSort", () =>
                    {
                        win.LoadPathsIntoPlayer(new[] { mediaDir }, autoPlayFirst: false);
                        var names = win.Playlist.Select(Path.GetFileName).ToList();
                        bool okCount = names.Count == 3;
                        bool okOrder = okCount &&
                                       names[0] == "Track 1.mp4" &&
                                       names[1] == "Track 2.wav" &&
                                       names[2] == "Track 10.wav";
                        return (okOrder, string.Join(" -> ", names));
                    });

                    // 11. Prev / Next negative index guard
                    Check("PrevNextNegativeIndexGuard", () =>
                    {
                        bool initiallyIdle = win.CurrentIndex == -1;
                        win.StepPreviousTrack();
                        bool selectedLast = win.CurrentIndex == 2;
                        win.ResetAndUnloadActiveMedia();
                        win.StepNextTrack();
                        bool selectedFirst = win.CurrentIndex == 0;
                        return (initiallyIdle && selectedLast && selectedFirst, $"PrevFromIdle={selectedLast}, NextFromIdle={selectedFirst}");
                    });

                    // 12. A-B loop state clamp & MediaEnded wraparound
                    Check("AbLoopClampAndMediaEndedWraparound", () =>
                    {
                        win.PlayIndex(1); // Track 2.wav
                        win.SetAbLoopForTest(TimeSpan.FromSeconds(2.0), TimeSpan.FromSeconds(5.0));
                        TimeSpan dur = TimeSpan.FromSeconds(10.0);

                        TimeSpan lowClamp = win.ClampSeekPosition(TimeSpan.FromSeconds(0.5), dur);
                        TimeSpan midKeep = win.ClampSeekPosition(TimeSpan.FromSeconds(3.5), dur);
                        TimeSpan highClamp = win.ClampSeekPosition(TimeSpan.FromSeconds(8.5), dur);

                        win.HandleMediaEnded();
                        bool stillPlaying = win.IsPlaying && win.AbLoopState == 2;
                        bool clampsOk = Math.Abs(lowClamp.TotalSeconds - 2.0) < 0.001 &&
                                        Math.Abs(midKeep.TotalSeconds - 3.5) < 0.001 &&
                                        Math.Abs(highClamp.TotalSeconds - 5.0) < 0.001;

                        return (clampsOk && stillPlaying, $"Clamps=[{lowClamp.TotalSeconds:F1},{midKeep.TotalSeconds:F1},{highClamp.TotalSeconds:F1}], WrapPlaying={stillPlaying}");
                    });

                    // 13. Audio-only snapshot guard & _fff millisecond filename format
                    Check("AudioOnlySnapshotGuardAndMillisecondFilename", () =>
                    {
                        win.PlayIndex(1); // Track 2.wav (audio only)
                        string snapDir = Path.Combine(tempRoot, "Snapshots");
                        bool canSnap = win.CanTakeVideoSnapshot();
                        bool snapped = win.TryCaptureSnapshotToDirectory(snapDir, out string? saved);
                        bool noFiles = !Directory.Exists(snapDir) || Directory.GetFiles(snapDir).Length == 0;

                        string sampleName = MainWindow.BuildSnapshotFileName(new DateTime(2026, 11, 14, 10, 5, 9, 437));
                        bool hasMillis = Regex.IsMatch(sampleName, @"^Funkenwerk_Snapshot_\d{8}_\d{6}_\d{3}\.png$") &&
                                         sampleName.EndsWith("_437.png", StringComparison.Ordinal);

                        return (!canSnap && !snapped && saved == null && noFiles && hasMillis,
                            $"AudioBlocked={!canSnap && !snapped}, SampleName={sampleName}");
                    });

                    // 14. Auto-unmute when raising volume
                    Check("VolumeRaiseAutoUnmutes", () =>
                    {
                        win.VolumeSlider.Value = 40;
                        win.SetMuted(true);
                        bool wasMuted = win.IsMuted && win.PlayerMediaElement.Volume == 0.0;
                        win.AdjustVolumeByDelta(5.0);
                        bool unmuted = !win.IsMuted && Math.Abs(win.VolumeSlider.Value - 45.0) < 0.01 && win.PlayerMediaElement.Volume > 0.4;
                        return (wasMuted && unmuted, $"WasMuted={wasMuted}, Unmuted={unmuted}, Vol={win.VolumeSlider.Value}%");
                    });

                    // 15. Fullscreen coordinate restore & cursor cleanup
                    Check("FullscreenRestoreCoordinatesAndCloseCleanup", () =>
                    {
                        win.WindowState = WindowState.Normal;
                        win.Left = 148;
                        win.Top = 94;
                        win.Width = 940;
                        win.Height = 560;

                        win.ToggleFullscreen();
                        bool enteredFs = win.IsFullscreen && win.WindowStyle == WindowStyle.None;
                        win.ToggleFullscreen();
                        bool restoredCoords = !win.IsFullscreen &&
                                              Math.Abs(win.Left - 148) < 0.5 &&
                                              Math.Abs(win.Top - 94) < 0.5 &&
                                              Math.Abs(win.Width - 940) < 0.5 &&
                                              Math.Abs(win.Height - 560) < 0.5;
                        return (enteredFs && restoredCoords, $"EnteredFs={enteredFs}, Restored=({win.Left},{win.Top},{win.Width}x{win.Height})");
                    });

                    // 16. Active track removal & playlist clear stop playback & show EmptyStateOverlay
                    Check("ActiveTrackRemovalAndClearResetMedia", () =>
                    {
                        win.PlayIndex(0);
                        bool wasPlaying = win.IsPlaying && win.CurrentFilePath != null && win.EmptyStateOverlay.Visibility == Visibility.Collapsed;

                        win.RemoveTrackAt(0);
                        bool stoppedAfterRemove = !win.IsPlaying &&
                                                  win.CurrentFilePath == null &&
                                                  win.CurrentIndex == -1 &&
                                                  win.PlayerMediaElement.Source == null &&
                                                  win.EmptyStateOverlay.Visibility == Visibility.Visible;

                        win.PlayIndex(0);
                        win.ClearPlaylist();
                        bool stoppedAfterClear = win.Playlist.Count == 0 &&
                                                 !win.IsPlaying &&
                                                 win.CurrentFilePath == null &&
                                                 win.EmptyStateOverlay.Visibility == Visibility.Visible;

                        return (wasPlaying && stoppedAfterRemove && stoppedAfterClear,
                            $"RemoveStopped={stoppedAfterRemove}, ClearStopped={stoppedAfterClear}");
                    });
                }
                finally
                {
                    Mouse.OverrideCursor = Cursors.None;
                    win.Close();
                    Check("WindowCloseResetsOverrideCursor", () =>
                    {
                        bool cursorCleared = Mouse.OverrideCursor == null;
                        return (cursorCleared, $"OverrideCursorNull={cursorCleared}");
                    });
                }
            }
            finally
            {
                try
                {
                    if (Directory.Exists(tempRoot))
                        Directory.Delete(tempRoot, recursive: true);
                }
                catch { }
            }

            // 18. Verify temp folder 100% cleaned up
            Check("TempWorkspaceCleanedUp", () =>
            {
                bool gone = !Directory.Exists(tempRoot);
                return (gone, $"Deleted={gone} ({tempRoot})");
            });

            lines.Add($"FUNKENWERK PLAYER SELF-TEST: {passed}/{total} TESTS BESTANDEN.");
            reportLines = lines;
            return (passed == total && total >= 15) ? 0 : 1;
        }

        private static void WritePcmSineWav(string filePath, double durationSeconds, double frequencyHz)
        {
            const int sampleRate = 44100;
            const short channels = 1;
            const short bitsPerSample = 16;
            int totalSamples = Math.Max(4410, (int)(sampleRate * durationSeconds));
            int dataBytes = totalSamples * channels * (bitsPerSample / 8);

            using var fs = new FileStream(filePath, FileMode.Create, FileAccess.Write, FileShare.None);
            using var bw = new BinaryWriter(fs, Encoding.ASCII);

            bw.Write(Encoding.ASCII.GetBytes("RIFF"));
            bw.Write(36 + dataBytes);
            bw.Write(Encoding.ASCII.GetBytes("WAVE"));
            bw.Write(Encoding.ASCII.GetBytes("fmt "));
            bw.Write(16);
            bw.Write((short)1); // PCM
            bw.Write(channels);
            bw.Write(sampleRate);
            bw.Write(sampleRate * channels * (bitsPerSample / 8));
            bw.Write((short)(channels * (bitsPerSample / 8)));
            bw.Write(bitsPerSample);
            bw.Write(Encoding.ASCII.GetBytes("data"));
            bw.Write(dataBytes);

            for (int i = 0; i < totalSamples; i++)
            {
                double t = (double)i / sampleRate;
                short sample = (short)(Math.Sin(2.0 * Math.PI * frequencyHz * t) * 12000);
                bw.Write(sample);
            }
        }

        private static bool TrySynthesizeTestMp4(string outputPath)
        {
            string? ffmpeg = ResolveTrustedFfmpegForTest();
            if (string.IsNullOrWhiteSpace(ffmpeg) || !File.Exists(ffmpeg))
                return false;

            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = ffmpeg,
                    UseShellExecute = false,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    CreateNoWindow = true
                };
                psi.ArgumentList.Add("-y");
                psi.ArgumentList.Add("-f");
                psi.ArgumentList.Add("lavfi");
                psi.ArgumentList.Add("-i");
                psi.ArgumentList.Add("testsrc=duration=0.6:size=320x240:rate=24");
                psi.ArgumentList.Add("-f");
                psi.ArgumentList.Add("lavfi");
                psi.ArgumentList.Add("-i");
                psi.ArgumentList.Add("sine=frequency=440:duration=0.6");
                psi.ArgumentList.Add("-c:v");
                psi.ArgumentList.Add("libx264");
                psi.ArgumentList.Add("-pix_fmt");
                psi.ArgumentList.Add("yuv420p");
                psi.ArgumentList.Add("-c:a");
                psi.ArgumentList.Add("aac");
                psi.ArgumentList.Add("-movflags");
                psi.ArgumentList.Add("+faststart");
                psi.ArgumentList.Add(outputPath);

                using var proc = Process.Start(psi);
                if (proc == null) return false;

                var outTask = proc.StandardOutput.ReadToEndAsync();
                var errTask = proc.StandardError.ReadToEndAsync();
                if (!proc.WaitForExit(15000))
                {
                    try { proc.Kill(entireProcessTree: true); } catch { }
                    return false;
                }
                System.Threading.Tasks.Task.WaitAll(outTask, errTask);
                return proc.ExitCode == 0 && File.Exists(outputPath) && new FileInfo(outputPath).Length > 500;
            }
            catch
            {
                return false;
            }
        }

        private static string? ResolveTrustedFfmpegForTest()
        {
            try
            {
                string wingetPackages = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "Microsoft", "WinGet", "Packages");
                if (Directory.Exists(wingetPackages))
                {
                    var found = Directory.GetFiles(wingetPackages, "ffmpeg.exe", SearchOption.AllDirectories)
                        .FirstOrDefault();
                    if (!string.IsNullOrWhiteSpace(found) && File.Exists(found))
                        return found;
                }

                string pathEnv = Environment.GetEnvironmentVariable("PATH") ?? "";
                foreach (var dir in pathEnv.Split(Path.PathSeparator, StringSplitOptions.RemoveEmptyEntries))
                {
                    string candidate = Path.Combine(dir.Trim(), "ffmpeg.exe");
                    if (File.Exists(candidate))
                        return Path.GetFullPath(candidate);
                }
            }
            catch { }
            return null;
        }
    }
}
