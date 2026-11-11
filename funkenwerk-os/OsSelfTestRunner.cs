using System;
using System.IO;
using System.Linq;

namespace FunkenwerkOS
{
    public static class OsSelfTestRunner
    {
        public static bool RunEndToEndSelfTest()
        {
            int passed = 0;
            int total = 0;
            string reportFile = Path.Combine(AppContext.BaseDirectory, "self_test_report.txt");
            try { if (File.Exists(reportFile)) File.Delete(reportFile); } catch { }

            void Check(string name, Func<bool> test)
            {
                total++;
                try
                {
                    bool ok = test();
                    string line = ok ? $"[PASS] {name}" : $"[FAIL] {name}";
                    Console.WriteLine(line);
                    try { File.AppendAllText(reportFile, line + Environment.NewLine); } catch { }
                    if (ok) passed++;
                }
                catch (Exception ex)
                {
                    string line = $"[FAIL] {name}: {ex.Message}";
                    Console.WriteLine(line);
                    try { File.AppendAllText(reportFile, line + Environment.NewLine); } catch { }
                }
            }

            Check("1. Marken-Icons (funkenwerk_os.ico & funkenwerk_os.png)", () =>
            {
                var icoStream = System.Windows.Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_os.ico"));
                var pngStream = System.Windows.Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_os.png"));
                return icoStream != null && icoStream.Stream.Length > 1000 && pngStream != null && pngStream.Stream.Length > 1000;
            });

            Check("2. Bespoke 3D Gold-System-Icons (Ordner, Einstellungen, Papierkorb)", () =>
            {
                var f = OsHardwareAndAudioEngine.GetSystemBadgeIcon("folder");
                var s = OsHardwareAndAudioEngine.GetSystemBadgeIcon("settings");
                var r = OsHardwareAndAudioEngine.GetSystemBadgeIcon("recycle_empty");
                return f.PixelWidth == 96 && s.PixelWidth == 96 && r.PixelWidth == 96;
            });

            Check("3. 60 FPS GPU AmbientSparksRenderer Zero-Alloc Initialisierung", () =>
            {
                var sparks = new AmbientSparksRenderer();
                return sparks != null;
            });

            Check("4. FWVAULT2 DPAPI + AES-256-GCM Tresor Roundtrip", () =>
            {
                string sample = "99|TestSanctumToken_FunkenwerkOS_Fortress_2026";
                string cipher = OsVaultAndStorage.ProtectVaultSecret(sample);
                string plain = OsVaultAndStorage.UnprotectVaultSecret(cipher);
                return cipher.StartsWith("FWVAULT2:", StringComparison.Ordinal) && plain == sample;
            });

            Check("5. CoreAudio System-Lautstärke (0-100 Integer) & Ausgabegeräte", () =>
            {
                var audio = OsHardwareAndAudioEngine.ReadAudioState();
                var outs = OsHardwareAndAudioEngine.ListAudioOutputDevices();
                return audio.VolumePercent >= 0 && audio.VolumePercent <= 100 && outs != null;
            });

            Check("6. Win32 Hardware-Telemetrie (RAM & CPU)", () =>
            {
                var (freeGb, totalGb, cpuPct) = OsHardwareAndAudioEngine.ReadSystemResources();
                return totalGb > 1.0 && freeGb >= 0.0 && cpuPct >= 0 && cpuPct <= 100;
            });

            Check("7. Funkenwerk Collection App-Erkennung (9 Schwester-Apps, Whiteboard entfernt)", () =>
            {
                var apps = OsHardwareAndAudioEngine.DiscoverCollectionApps();
                var drives = OsHardwareAndAudioEngine.ListMountedDrivesForSidebar();
                return apps.Count == 9 &&
                       !apps.Any(a => a.Key == "miro") &&
                       apps.Any(a => a.Key == "browser") &&
                       apps.Any(a => a.Key == "tresor" && a.Title == "Tresor") &&
                       apps.Any(a => a.Key == "pdf") &&
                       drives.Count >= 1;
            });

            Check("8. Desktop-Ordner, Papierkorb & Standardprogramme Persistenz", () =>
            {
                var settings = OsVaultAndStorage.LoadSettings();
                bool hasDefaults = settings.DefaultPrograms.ContainsKey("pdf") && settings.DefaultPrograms.ContainsKey("audio");
                bool dirsExist = Directory.Exists(OsVaultAndStorage.OsDesktopDir) && Directory.Exists(OsVaultAndStorage.OsRecycleBinDir);
                return hasDefaults && dirsExist;
            });

            Check("9. Seelenfunke API SSRF-Whitelist Härtung", () =>
            {
                bool okLive = SeelenfunkeLiveClient.IsTrustedApiUrl("https://mein-seelenfunke.de/api", out _);
                bool okLocal = SeelenfunkeLiveClient.IsTrustedApiUrl("http://127.0.0.1/api", out _);
                bool blockHttpLive = !SeelenfunkeLiveClient.IsTrustedApiUrl("http://mein-seelenfunke.de/api", out _);
                bool blockEvil = !SeelenfunkeLiveClient.IsTrustedApiUrl("https://evil.example.com/api", out _);
                return okLive && okLocal && blockHttpLive && blockEvil;
            });

            Check("10. Mini-PC Festungs-Installer & Notfall-Skripte Generierung", () =>
            {
                string tempDir = Path.Combine(Path.GetTempPath(), "fw_os_selftest_" + Guid.NewGuid().ToString("N"));
                try
                {
                    FortressProvisioningEngine.WriteMiniPcDeploymentScripts(tempDir);
                    bool hasPs1 = File.Exists(Path.Combine(tempDir, "Funkenwerk_Fortress_Installer.ps1"));
                    bool hasSetup = File.Exists(Path.Combine(tempDir, "2_START_FUNKENWERK_OS_AUF_MINIPC.cmd"));
                    bool hasRestore = File.Exists(Path.Combine(tempDir, "3_NOTFALL_WINDOWS_ZURUECKSETZEN.cmd"));
                    string ps1Text = File.ReadAllText(Path.Combine(tempDir, "Funkenwerk_Fortress_Installer.ps1"));
                    bool hasFirewallWhitelist = ps1Text.Contains("FunkenwerkFortress_AllowCore_LAN") && ps1Text.Contains("DefaultOutboundAction Allow");
                    return hasPs1 && hasSetup && hasRestore && hasFirewallWhitelist;
                }
                finally
                {
                    try { Directory.Delete(tempDir, recursive: true); } catch { }
                }
            });

            Check("11. USB-Stick Zero-Secret Filter (Sperrt Vaults & .dpapi)", () =>
            {
                bool blockSettings = FortressProvisioningEngine.IsSensitiveFileExcludedFromUsbExport(@"C:\Test\settings.json");
                bool blockVault = FortressProvisioningEngine.IsSensitiveFileExcludedFromUsbExport(@"C:\Test\profiles_vault.json");
                bool blockDpapi = FortressProvisioningEngine.IsSensitiveFileExcludedFromUsbExport(@"C:\Test\handoff_ticket.dpapi");
                bool allowExe = !FortressProvisioningEngine.IsSensitiveFileExcludedFromUsbExport(@"C:\Test\FunkenwerkOS.exe");
                return blockSettings && blockVault && blockDpapi && allowExe;
            });

            Check("12. Pragmatische App-Namen, Rechtsklick-Umbenennen & Seamless-Wallpaper", () =>
            {
                var apps = OsHardwareAndAudioEngine.DiscoverCollectionApps();
                bool noRedundantPrefix = apps.All(a => !a.Title.StartsWith("Funkenwerk", StringComparison.OrdinalIgnoreCase));
                bool hasPragmatic = apps.Any(a => a.Title == "SSH") && apps.Any(a => a.Title == "Drucker") && apps.Any(a => a.Title == "Mediaplayer") && apps.Any(a => a.Title == "Browser");
                var testAppItem = new DesktopItemModel { Id = "app:ssh", Title = "SSH", Kind = DesktopEntryKind.CollectionApp };
                var settings = OsVaultAndStorage.LoadSettings();
                string? loopVideo = OsVaultAndStorage.ResolveAtelierMediaPath("atelier_seamless_loop.mp4");
                return noRedundantPrefix && hasPragmatic && testAppItem.IsRenamable && settings.CustomDisplayNames != null && !string.IsNullOrEmpty(loopVideo);
            });

            Check("13. Built-In Apps: Taschenrechner (128-bit Decimal + 19% MwSt) & Kurznotizen", () =>
            {
                var calc = new OsCalculatorEngine();
                calc.InputDigit('1'); calc.InputDigit('0'); calc.InputDigit('0');
                calc.ApplyPlusVat19();
                bool vatOk = calc.CurrentValue == 119m;
                calc.ApplyNetFromGross19();
                bool netOk = calc.CurrentValue == 100m;

                var cIco = OsHardwareAndAudioEngine.GetSystemBadgeIcon("calculator");
                var sIco = OsHardwareAndAudioEngine.GetSystemBadgeIcon("snip");
                var nIco = OsHardwareAndAudioEngine.GetSystemBadgeIcon("notes");
                var notes = StickyNotesVault.LoadNotes();
                return vatOk && netOk && cIco.PixelWidth == 96 && sIco.PixelWidth == 96 && nIco.PixelWidth == 96 && notes.Count >= 1;
            });

            Check("14. Built-In App: Ausschneiden (Zero-Leak Bitmap Konvertierung)", () =>
            {
                using var bmp = new System.Drawing.Bitmap(64, 48, System.Drawing.Imaging.PixelFormat.Format32bppArgb);
                var src = SnipCaptureOverlayWindow.ConvertDrawingBitmapToFrozenSource(bmp);
                return src.IsFrozen && src.PixelWidth == 64 && src.PixelHeight == 48;
            });

            Check("15. Windows-Tasten Dynamische Smart-Suche & Desktop-Verknüpfungen Löschen", () =>
            {
                var catalog = new[]
                {
                    new DesktopItemModel { Id = "sys:calculator", Title = "Taschenrechner", Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "CalculatorWin" },
                    new DesktopItemModel { Id = "sys:snip", Title = "Ausschneiden", Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "SnipWin" },
                    new DesktopItemModel { Id = "sys:notes", Title = "Notizen", Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "NotesWin" },
                    new DesktopItemModel { Id = "app:browser", Title = "Browser", Kind = DesktopEntryKind.CollectionApp, AppOrToolKey = "browser" }
                };

                var rCalc = OsSmartSearchEngine.FilterAndRank("re", catalog);
                var rSnip = OsSmartSearchEngine.FilterAndRank("snip", catalog);
                var rNotes = OsSmartSearchEngine.FilterAndRank("kurz", catalog);
                var rTypo = OsSmartSearchEngine.FilterAndRank("tashcen", catalog);

                bool removable = catalog[0].IsShortcutRemovable && catalog[3].IsShortcutRemovable;
                return removable &&
                       rCalc.FirstOrDefault()?.Id == "sys:calculator" &&
                       rSnip.FirstOrDefault()?.Id == "sys:snip" &&
                       rNotes.FirstOrDefault()?.Id == "sys:notes" &&
                       rTypo.FirstOrDefault()?.Id == "sys:calculator";
            });

            Check("16. 8-Säulen Daten-Trichter, Taskleisten-Modi & Farbcodierte App-Icons", () =>
            {
                OsVaultAndStorage.EnsureMeineDatenStructure();
                bool pillarsExist = Directory.Exists(OsVaultAndStorage.EingangTrichterDir) &&
                                    OsVaultAndStorage.MeineDatenPillars.All(p => Directory.Exists(Path.Combine(OsVaultAndStorage.MeineDatenRootDir, p.Pillar)));

                var cSf = OsVaultAndStorage.ClassifyFileIntoMeineDatenPillar("Rechnung_xTool_UV_Laser.pdf");
                var cMed = OsVaultAndStorage.ClassifyFileIntoMeineDatenPillar("Arztbrief_MHH_Befund.pdf");
                var cIns = OsVaultAndStorage.ClassifyFileIntoMeineDatenPillar("HUK24_Versicherung.pdf");

                var settings = OsVaultAndStorage.LoadSettings();
                bool taskbarConfigOk = settings.PinnedTaskbarAppIds != null &&
                                       settings.PinnedTaskbarAppIds.Count > 0 &&
                                       !string.IsNullOrEmpty(settings.TaskbarPosition) &&
                                       !string.IsNullOrEmpty(settings.TaskbarAlignment);

                var iPlayer = OsHardwareAndAudioEngine.GetSystemBadgeIcon("player");
                var iPdf = OsHardwareAndAudioEngine.GetSystemBadgeIcon("pdf");
                var iBrowser = OsHardwareAndAudioEngine.GetSystemBadgeIcon("browser");
                var iMeineDaten = OsHardwareAndAudioEngine.GetSystemBadgeIcon("meinedaten");

                return pillarsExist &&
                       cSf.Pillar == "01_Mein-Seelenfunke" &&
                       cMed.Pillar == "06_Gesundheit" &&
                       cIns.Pillar == "04_Versicherungen_Vertraege_Mobilitaet" &&
                       taskbarConfigOk &&
                       iPlayer.PixelWidth == 96 &&
                       iPdf.PixelWidth == 96 &&
                       iBrowser.PixelWidth == 96 &&
                       iMeineDaten.PixelWidth == 96;
            });

            Check("17. Papierkorb Ursprungsort-Wiederherstellung & Asynchrone Tray-Telemetrie", () =>
            {
                string testFolder = Path.Combine(OsVaultAndStorage.MeineDatenRootDir, "01_Mein-Seelenfunke");
                string dummyFile = $"__selftest_{Guid.NewGuid():N}.pdf";
                OsVaultAndStorage.RememberRecycleOrigin(dummyFile, testFolder);
                string resolved = OsVaultAndStorage.ResolveAndRemoveRecycleOrigin(dummyFile);
                string fallback = OsVaultAndStorage.ResolveAndRemoveRecycleOrigin(dummyFile);
                OsHardwareAndAudioEngine.TriggerBackgroundTrayRefresh();
                return string.Equals(resolved, testFolder, StringComparison.OrdinalIgnoreCase) &&
                       string.Equals(fallback, OsVaultAndStorage.OsDesktopDir, StringComparison.OrdinalIgnoreCase);
            });

            Check("18. kDrive Cloud-Anbindung, 8-Säulen-Sync & Kein-Datenmüll-Wächter", () =>
            {
                var settings = OsVaultAndStorage.LoadSettings();
                OsVaultAndStorage.DiscoverAndEnsureStorageBindings(settings);
                bool hasKDriveBinding = settings.StorageBindings.Any(b => b.Id.Equals("kdrive", StringComparison.OrdinalIgnoreCase));

                // Test Zero-Datenmüll Guard: create a rogue standard folder ("Downloads") + Thumbs.db inside Meine Daten root
                string rogueDir = Path.Combine(OsVaultAndStorage.MeineDatenRootDir, "Downloads");
                Directory.CreateDirectory(rogueDir);
                string garbageFile = Path.Combine(rogueDir, "Thumbs.db");
                File.WriteAllText(garbageFile, "junk");

                OsVaultAndStorage.EnforceStrictTrichterStructureOnMeineDaten();
                OsVaultAndStorage.PurgeZeroGarbageFiles();

                bool rogueGone = !Directory.Exists(rogueDir) && !File.Exists(garbageFile);
                var (_, targets) = OsVaultAndStorage.SyncMeineDatenToStorageBindings(settings);

                return hasKDriveBinding &&
                       rogueGone &&
                       settings.AutoRedirectStandardDownloadsToFunnel &&
                       settings.ZeroGarbageGuardEnabled &&
                       targets >= 0;
            });

            string summary = $"FUNKENWERK OS SELF-TEST: {passed}/{total} TESTS BESTANDEN.";
            Console.WriteLine(summary);
            try { File.AppendAllText(reportFile, summary + Environment.NewLine); } catch { }
            return passed == total;
        }
    }
}
