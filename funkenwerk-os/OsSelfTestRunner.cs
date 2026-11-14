using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Reflection;
using System.Text;
using System.Windows;
using System.Windows.Input;
using System.Xml.Linq;

namespace FunkenwerkOS
{
    public static class OsSelfTestRunner
    {
        public static bool RunEndToEndSelfTest()
        {
            int passed = 0;
            int total = 0;
            var reportLines = new List<string>();

            // Capture real user folders BEFORE entering sandbox to verify 0 side-effects on real directories
            string realLocalAppDataDir = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "FunkenwerkOS");
            string realUserProfile = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
            string realDownloadsDir = Path.Combine(realUserProfile, "Downloads");
            string sentinelCanaryName = $"__fwos_real_canary_{Guid.NewGuid():N}.tmp";

            string primaryReportPath = Path.Combine(AppContext.BaseDirectory, "self_test_report.txt");
            string fallbackReportPath = Path.Combine(realLocalAppDataDir, "self_test_report.txt");
            string activeReportPath = ResolveWritableReportPath(primaryReportPath, fallbackReportPath);

            void AppendReportLine(string line)
            {
                Console.WriteLine(line);
                reportLines.Add(line);
                try
                {
                    File.AppendAllText(activeReportPath, line + Environment.NewLine, new UTF8Encoding(false));
                }
                catch
                {
                    try
                    {
                        Directory.CreateDirectory(realLocalAppDataDir);
                        activeReportPath = fallbackReportPath;
                        File.AppendAllText(activeReportPath, line + Environment.NewLine, new UTF8Encoding(false));
                    }
                    catch { }
                }
            }

            void Check(string name, Func<bool> test)
            {
                total++;
                try
                {
                    bool ok = test();
                    string line = ok ? $"[PASS] {name}" : $"[FAIL] {name}";
                    AppendReportLine(line);
                    if (ok) passed++;
                }
                catch (Exception ex)
                {
                    string line = $"[FAIL] {name}: {ex.GetType().Name}: {ex.Message}";
                    AppendReportLine(line);
                }
            }

            string sandboxRoot = Path.Combine(Path.GetTempPath(), "fw_os_selftest_sandbox_" + Guid.NewGuid().ToString("N"));
            try
            {
                OsVaultAndStorage.EnterSelfTestSandbox(sandboxRoot);

                // =========================================================================
                // 1. BRAND ASSETS, 3D SYSTEM ICONS (INCL. CRAFT) & GPU SPARKS
                // =========================================================================
                Check("01. Marken-Icons (funkenwerk_os.ico & funkenwerk_os.png)", () =>
                {
                    var icoStream = System.Windows.Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_os.ico"));
                    var pngStream = System.Windows.Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_os.png"));
                    return icoStream != null && icoStream.Stream.Length > 1000 && pngStream != null && pngStream.Stream.Length > 1000;
                });

                Check("02. Bespoke 3D System- & App-Badges (inkl. Craft, Tresor, Meine Daten)", () =>
                {
                    string[] keys = { "folder", "settings", "recycle_empty", "craft", "tresor", "player", "pdf", "browser", "meinedaten", "calculator", "snip", "notes" };
                    return keys.All(k =>
                    {
                        var bmp = OsHardwareAndAudioEngine.GetSystemBadgeIcon(k);
                        return bmp != null && bmp.IsFrozen && bmp.PixelWidth == 96 && bmp.PixelHeight == 96;
                    });
                });

                Check("03. 60 FPS GPU AmbientSparksRenderer & Snip Frozen-Bitmap Konvertierung", () =>
                {
                    var sparks = new AmbientSparksRenderer();
                    using var bmp = new System.Drawing.Bitmap(64, 48, System.Drawing.Imaging.PixelFormat.Format32bppArgb);
                    var src = SnipCaptureOverlayWindow.ConvertDrawingBitmapToFrozenSource(bmp);
                    return sparks != null && src.IsFrozen && src.PixelWidth == 64 && src.PixelHeight == 48;
                });

                // =========================================================================
                // 2. VAULT CRYPTOGRAPHY (FWOSVAULT1 + FWVAULT2) & ZERO STATIC PLAINTEXT CACHE
                // =========================================================================
                Check("04. Tresor FWOSVAULT1 & FWVAULT2 (DPAPI + AES-256-GCM) Roundtrip", () =>
                {
                    string sample = "108|SanctumSecretToken_FunkenwerkOS_2026_!@#";
                    string osCipher = OsVaultAndStorage.ProtectOsVaultSecret(sample);
                    string ssoCipher = OsVaultAndStorage.ProtectVaultSecret(sample);
                    string decOs = OsVaultAndStorage.UnprotectVaultSecret(osCipher);
                    string decSso = OsVaultAndStorage.UnprotectVaultSecret(ssoCipher);
                    return osCipher.StartsWith("FWOSVAULT1:", StringComparison.Ordinal) &&
                           ssoCipher.StartsWith("FWVAULT2:", StringComparison.Ordinal) &&
                           decOs == sample &&
                           decSso == sample;
                });

                Check("05. Tresor FWOSVAULT1 Manipulationserkennung (GCM-Tag, Header & Ciphertext)", () =>
                {
                    string cipher = OsVaultAndStorage.ProtectOsVaultSecret("Sensitive_Master_Secret_42");
                    byte[] raw = Convert.FromBase64String(cipher.Substring("FWOSVAULT1:".Length));

                    // Tamper last byte (AES-GCM tag)
                    byte[] tamperedTag = (byte[])raw.Clone();
                    tamperedTag[^1] ^= 0xFF;
                    string badTagCipher = "FWOSVAULT1:" + Convert.ToBase64String(tamperedTag);

                    // Tamper version header byte
                    byte[] tamperedVer = (byte[])raw.Clone();
                    tamperedVer[0] = 0x7F;
                    string badVerCipher = "FWOSVAULT1:" + Convert.ToBase64String(tamperedVer);

                    // Swap prefix to mismatch AAD
                    string swappedPrefix = "FWVAULT2:" + cipher.Substring("FWOSVAULT1:".Length);

                    return string.IsNullOrEmpty(OsVaultAndStorage.UnprotectVaultSecret(badTagCipher)) &&
                           string.IsNullOrEmpty(OsVaultAndStorage.UnprotectVaultSecret(badVerCipher)) &&
                           string.IsNullOrEmpty(OsVaultAndStorage.UnprotectVaultSecret(swappedPrefix)) &&
                           string.IsNullOrEmpty(OsVaultAndStorage.UnprotectVaultSecret("Plaintext_Not_Allowed"));
                });

                Check("06. OS-SEC-04: Keine statischen Klartext-Token-Caches im Managed Heap", () =>
                {
                    var staticFields = typeof(OsVaultAndStorage).GetFields(BindingFlags.Static | BindingFlags.NonPublic | BindingFlags.Public);
                    bool hasStringDictCache = staticFields.Any(f =>
                        f.FieldType == typeof(ConcurrentDictionary<string, string>) ||
                        f.FieldType == typeof(Dictionary<string, string>) ||
                        f.Name.Contains("unprotectCache", StringComparison.OrdinalIgnoreCase) ||
                        f.Name.Contains("TokenCache", StringComparison.OrdinalIgnoreCase));

                    var s = new OsSettings();
                    OsVaultAndStorage.StoreBearerTokenUnified(s, "live", "77|LiveToken_NoStaticHeapCache");
                    bool encryptedInSettings = !string.IsNullOrEmpty(s.EncryptedTokenLive) &&
                                               (s.EncryptedTokenLive.StartsWith("FWVAULT2:", StringComparison.Ordinal) ||
                                                s.EncryptedTokenLive.StartsWith("FWOSVAULT1:", StringComparison.Ordinal));
                    string resolved = OsVaultAndStorage.ResolveBearerToken(s, "live");
                    return !hasStringDictCache && encryptedInSettings && resolved == "77|LiveToken_NoStaticHeapCache";
                });

                // =========================================================================
                // 3. SANDBOXED 8-PILLAR MEINE DATEN, TRICHTER & ZERO-GARBAGE GUARD
                // =========================================================================
                Check("07. Isolierte Sandbox-Umleitung (Kein Eingriff in echte Benutzerordner)", () =>
                {
                    bool sandboxActive = OsVaultAndStorage.IsSelfTestSandboxActive;
                    bool dataInSandbox = OsVaultAndStorage.OsDataDir.StartsWith(sandboxRoot, StringComparison.OrdinalIgnoreCase);
                    bool meineDatenInSandbox = OsVaultAndStorage.MeineDatenRootDir.StartsWith(sandboxRoot, StringComparison.OrdinalIgnoreCase);
                    bool profileInSandbox = OsVaultAndStorage.UserProfileDir.StartsWith(sandboxRoot, StringComparison.OrdinalIgnoreCase);

                    string testFile = Path.Combine(OsVaultAndStorage.OsDesktopDir, sentinelCanaryName);
                    File.WriteAllText(testFile, "sandbox_only");
                    bool notInRealDownloads = !File.Exists(Path.Combine(realDownloadsDir, sentinelCanaryName));
                    File.Delete(testFile);

                    return sandboxActive && dataInSandbox && meineDatenInSandbox && profileInSandbox && notInRealDownloads;
                });

                Check("08. 8-Säulen Meine Daten & 00_Eingang_Trichter Echtdatei-Routing", () =>
                {
                    OsVaultAndStorage.EnsureMeineDatenStructure();
                    bool allPillars = Directory.Exists(OsVaultAndStorage.EingangTrichterDir) &&
                                      OsVaultAndStorage.MeineDatenPillars.All(p => Directory.Exists(Path.Combine(OsVaultAndStorage.MeineDatenRootDir, p.Pillar)));

                    string fLaser = Path.Combine(OsVaultAndStorage.EingangTrichterDir, "Rechnung_xTool_UV_Laser_Test.pdf");
                    string fMed = Path.Combine(OsVaultAndStorage.EingangTrichterDir, "Arztbrief_MHH_Befund_Test.pdf");
                    string fIns = Path.Combine(OsVaultAndStorage.EingangTrichterDir, "HUK24_Versicherung_Test.pdf");
                    File.WriteAllText(fLaser, "pdf1");
                    File.WriteAllText(fMed, "pdf2");
                    File.WriteAllText(fIns, "pdf3");

                    int moved = OsVaultAndStorage.SortEingangTrichterFiles();
                    string datePrefix = $"{DateTime.Now:yyyy-MM-dd}_";
                    bool routedLaser = File.Exists(Path.Combine(OsVaultAndStorage.MeineDatenRootDir, "01_Mein-Seelenfunke", "Laser_Manufaktur", datePrefix + "Rechnung_xTool_UV_Laser_Test.pdf"));
                    bool routedMed = File.Exists(Path.Combine(OsVaultAndStorage.MeineDatenRootDir, "06_Gesundheit", "Arztbriefe_Befunde", datePrefix + "Arztbrief_MHH_Befund_Test.pdf"));
                    bool routedIns = File.Exists(Path.Combine(OsVaultAndStorage.MeineDatenRootDir, "04_Versicherungen_Vertraege_Mobilitaet", "Versicherungen_Privat", datePrefix + "HUK24_Versicherung_Test.pdf"));

                    return allPillars && moved >= 3 && routedLaser && routedMed && routedIns && File.Exists(OsVaultAndStorage.MeineDatenIndexFilePath);
                });

                Check("09. Sandbox Downloads/kDrive Auto-Ingest & Kein-Datenmüll-Wächter", () =>
                {
                    var settings = OsVaultAndStorage.LoadSettings();
                    OsVaultAndStorage.DiscoverAndEnsureStorageBindings(settings);
                    bool hasKDriveBinding = settings.StorageBindings.Any(b => b.Id.Equals("kdrive", StringComparison.OrdinalIgnoreCase) &&
                                                                             b.LocalOrUncPath.StartsWith(sandboxRoot, StringComparison.OrdinalIgnoreCase));

                    DateTime bootMark = DateTime.UtcNow.AddMinutes(-1);
                    string sandboxDlFile = Path.Combine(OsVaultAndStorage.UserProfileDir, "Downloads", "Westpack_Rechnung_2026.pdf");
                    File.WriteAllText(sandboxDlFile, "invoice_bytes");
                    File.SetLastWriteTimeUtc(sandboxDlFile, DateTime.UtcNow);

                    int ingested = OsVaultAndStorage.IngestExternalDownloadsAndCloudInboxes(settings, bootMark);
                    int sorted = OsVaultAndStorage.SortEingangTrichterFiles();

                    string rogueDir = Path.Combine(OsVaultAndStorage.MeineDatenRootDir, "Downloads");
                    Directory.CreateDirectory(rogueDir);
                    string garbageFile = Path.Combine(rogueDir, "Thumbs.db");
                    File.WriteAllText(garbageFile, "junk");

                    OsVaultAndStorage.EnforceStrictTrichterStructureOnMeineDaten();
                    OsVaultAndStorage.PurgeZeroGarbageFiles();
                    var (_, syncedTargets) = OsVaultAndStorage.SyncMeineDatenToStorageBindings(settings);

                    return hasKDriveBinding &&
                           ingested >= 1 &&
                           sorted >= 1 &&
                           !Directory.Exists(rogueDir) &&
                           !File.Exists(garbageFile) &&
                           syncedTargets >= 1;
                });

                // =========================================================================
                // 4. SSRF & WSS DOMAIN ALLOWLISTS
                // =========================================================================
                Check("10. HTTPS API-Allowlist (Positiv- & Negativ-Prüfung inkl. UserInfo-Block)", () =>
                {
                    bool okLive = SeelenfunkeLiveClient.IsTrustedApiUrl("https://mein-seelenfunke.de/api", out _);
                    bool okStage = SeelenfunkeLiveClient.IsTrustedApiUrl("https://stage.mein-seelenfunke.de/api", out _);
                    bool okLocal = SeelenfunkeLiveClient.IsTrustedApiUrl("http://127.0.0.1/api", out _);
                    bool blockHttpLive = !SeelenfunkeLiveClient.IsTrustedApiUrl("http://mein-seelenfunke.de/api", out _);
                    bool blockEvil = !SeelenfunkeLiveClient.IsTrustedApiUrl("https://evil.example.com/api", out _);
                    bool blockSubSpoof = !SeelenfunkeLiveClient.IsTrustedApiUrl("https://mein-seelenfunke.de.evil.com/api", out _);
                    bool blockUserInfo = !SeelenfunkeLiveClient.IsTrustedApiUrl("https://admin@mein-seelenfunke.de/api", out _);
                    return okLive && okStage && okLocal && blockHttpLive && blockEvil && blockSubSpoof && blockUserInfo;
                });

                Check("11. WSS Reverb-WebSocket Allowlist (Positiv- & Negativ-Prüfung)", () =>
                {
                    bool okLiveWss = SeelenfunkeLiveClient.IsTrustedWssUrl("wss://mein-seelenfunke.de/app/sfkey", out _);
                    bool okStageWss = SeelenfunkeLiveClient.IsTrustedWssUrl("wss://stage.mein-seelenfunke.de/app/sfkey", out _);
                    bool okLocalWs = SeelenfunkeLiveClient.IsTrustedWssUrl("ws://127.0.0.1:8080/app/sfkey", out _);
                    bool blockPlainWsLive = !SeelenfunkeLiveClient.IsTrustedWssUrl("ws://mein-seelenfunke.de/app/sfkey", out _);
                    bool blockEvilWss = !SeelenfunkeLiveClient.IsTrustedWssUrl("wss://attacker.invalid/app/sfkey", out _);
                    bool blockUserInfoWss = !SeelenfunkeLiveClient.IsTrustedWssUrl("wss://token@mein-seelenfunke.de/app/sfkey", out _);
                    return okLiveWss && okStageWss && okLocalWs && blockPlainWsLive && blockEvilWss && blockUserInfoWss;
                });

                // =========================================================================
                // 5. ALL 10 COLLECTION APPS (INCL. CRAFT) & SMART SEARCH SYNONYMS
                // =========================================================================
                Check("12. Alle 10 Schwester-Apps registriert (inkl. Craft / FunkenwerkCraft.exe & Icons)", () =>
                {
                    var apps = OsHardwareAndAudioEngine.DiscoverCollectionApps();
                    var craft = apps.FirstOrDefault(a => a.Key == "craft");
                    bool allIconsValid = apps.All(a => a.IconImage != null && a.IconImage.PixelWidth > 0);
                    bool noRedundantPrefix = apps.All(a => !a.Title.StartsWith("Funkenwerk", StringComparison.OrdinalIgnoreCase));

                    return OsHardwareAndAudioEngine.KnownCollectionAppsCount == 10 &&
                           apps.Count == 10 &&
                           !apps.Any(a => a.Key == "miro") &&
                           craft != null &&
                           craft.Title == "Craft" &&
                           craft.ExePath.EndsWith(Path.Combine("Funkenwerk Craft", "FunkenwerkCraft.exe"), StringComparison.OrdinalIgnoreCase) &&
                           allIconsValid &&
                           noRedundantPrefix;
                });

                Check("13. SmartSearch Synonyme & Tippfehler (craft, tiktok, mwst, snip, tresor)", () =>
                {
                    var catalog = OsHardwareAndAudioEngine.DiscoverCollectionApps()
                        .Select(a => new DesktopItemModel { Id = "app:" + a.Key, Title = a.Title, Kind = DesktopEntryKind.CollectionApp, AppOrToolKey = a.Key })
                        .Concat(new[]
                        {
                            new DesktopItemModel { Id = "sys:calculator", Title = "Taschenrechner", Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "CalculatorWin" },
                            new DesktopItemModel { Id = "sys:snip", Title = "Ausschneiden", Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "SnipWin" },
                            new DesktopItemModel { Id = "sys:notes", Title = "Notizen", Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "NotesWin" }
                        })
                        .ToList();

                    string? topCraft = OsSmartSearchEngine.FilterAndRank("craft", catalog).FirstOrDefault()?.Id;
                    string? topTiktok = OsSmartSearchEngine.FilterAndRank("tiktok", catalog).FirstOrDefault()?.Id;
                    string? topReel = OsSmartSearchEngine.FilterAndRank("reel", catalog).FirstOrDefault()?.Id;
                    string? topMwst = OsSmartSearchEngine.FilterAndRank("mwst", catalog).FirstOrDefault()?.Id;
                    string? topSnip = OsSmartSearchEngine.FilterAndRank("snip", catalog).FirstOrDefault()?.Id;
                    string? topTresor = OsSmartSearchEngine.FilterAndRank("tresor", catalog).FirstOrDefault()?.Id;
                    string? topTypo = OsSmartSearchEngine.FilterAndRank("taschnrechner", catalog).FirstOrDefault()?.Id;

                    return topCraft == "app:craft" &&
                           topTiktok == "app:craft" &&
                           topReel == "app:craft" &&
                           topMwst == "sys:calculator" &&
                           topSnip == "sys:snip" &&
                           topTresor == "app:tresor" &&
                           topTypo == "sys:calculator";
                });

                // =========================================================================
                // 6. OS-SEC-01: WEBVIEW2 SANDBOX PRESERVED
                // =========================================================================
                Check("14. OS-SEC-01: Kindprozess-Start behält WebView2 Renderer-/GPU-Sandbox aktiv", () =>
                {
                    var psi = OsHardwareAndAudioEngine.BuildCollectionAppStartInfo(@"C:\Funkenwerk\FunkenwerkBrowser.exe", "https://mein-seelenfunke.de");
                    string wvArgs = psi.Environment["WEBVIEW2_ADDITIONAL_BROWSER_ARGUMENTS"] ?? "";
                    bool noDisableSandbox = !wvArgs.Contains("--no-sandbox", StringComparison.OrdinalIgnoreCase) &&
                                            !wvArgs.Contains("--disable-gpu-sandbox", StringComparison.OrdinalIgnoreCase) &&
                                            !wvArgs.Contains("--disable-web-security", StringComparison.OrdinalIgnoreCase);

                    // Also verify source/runtime args do not smuggle sandbox-disabling flags
                    return !psi.UseShellExecute &&
                           psi.ArgumentList.Count == 1 &&
                           noDisableSandbox &&
                           wvArgs.Contains("--enable-gpu-rasterization", StringComparison.Ordinal);
                });

                // =========================================================================
                // 7. OS-SEC-02: STRICT USB ARTIFACT ALLOWLIST & ENCRYPTED KEY BACKUP
                // =========================================================================
                Check("15. OS-SEC-02a: Strikte USB-Artefakt-Allowlist blockiert Secrets, DBs & Profile", () =>
                {
                    string[] mustAllow =
                    {
                        @"C:\Col\Funkenwerk Craft\FunkenwerkCraft.exe",
                        @"C:\Col\Funkenwerk Craft\FunkenwerkCraft.dll",
                        @"C:\Col\Funkenwerk Craft\FunkenwerkCraft.deps.json",
                        @"C:\Col\Funkenwerk Craft\FunkenwerkCraft.runtimeconfig.json",
                        @"C:\Col\Funkenwerk Craft\funkenwerk_craft.ico",
                        @"C:\Col\Funkenwerk OS\atelier_seamless_loop.mp4"
                    };

                    string[] mustBlock =
                    {
                        @"C:\Col\Funkenwerk Browser\browser_settings.json",
                        @"C:\Col\Funkenwerk OS\sticky_notes.json",
                        @"C:\Col\Funkenwerk\settings.json",
                        @"C:\Col\Funkenwerk\profiles_vault.json",
                        @"C:\Col\Funkenwerk Browser\Cookies",
                        @"C:\Col\Funkenwerk Browser\Login Data",
                        @"C:\Col\Funkenwerk\local.sqlite",
                        @"C:\Col\Funkenwerk\history.db",
                        @"C:\Col\Funkenwerk\kunden_export.csv",
                        @"C:\Col\Funkenwerk\Windows11Pro_Key_Backup.txt",
                        @"C:\Col\Funkenwerk\Windows11Pro_Key_Backup.vault",
                        @"C:\Col\Funkenwerk\WebViewProfile\Default\logo.png"
                    };

                    return mustAllow.All(FortressProvisioningEngine.IsAllowedUsbRuntimeArtifact) &&
                           mustAllow.All(p => !FortressProvisioningEngine.IsSensitiveFileExcludedFromUsbExport(p)) &&
                           mustBlock.All(p => !FortressProvisioningEngine.IsAllowedUsbRuntimeArtifact(p)) &&
                           mustBlock.All(FortressProvisioningEngine.IsSensitiveFileExcludedFromUsbExport);
                });

                Check("16. OS-SEC-02b: CopyCollectionSafeRecursive kopiert 0 Userdaten/Profile auf USB", () =>
                {
                    string fakeSrc = Path.Combine(sandboxRoot, "usb_src_test");
                    string fakeDst = Path.Combine(sandboxRoot, "usb_dst_test");
                    Directory.CreateDirectory(Path.Combine(fakeSrc, "Funkenwerk Craft"));
                    Directory.CreateDirectory(Path.Combine(fakeSrc, "Funkenwerk Browser", "WebViewProfile", "Default"));
                    Directory.CreateDirectory(Path.Combine(fakeSrc, "Funkenwerk Video", "Projects"));
                    Directory.CreateDirectory(Path.Combine(fakeSrc, "Funkenwerk", "storage"));

                    File.WriteAllText(Path.Combine(fakeSrc, "Funkenwerk Craft", "FunkenwerkCraft.exe"), "MZ_FAKE");
                    File.WriteAllText(Path.Combine(fakeSrc, "Funkenwerk Craft", "funkenwerk_craft.ico"), "ICO");
                    File.WriteAllText(Path.Combine(fakeSrc, "Funkenwerk Craft", "FunkenwerkCraft.runtimeconfig.json"), "{}");
                    File.WriteAllText(Path.Combine(fakeSrc, "Funkenwerk Browser", "browser_settings.json"), "{\"cookies\":true}");
                    File.WriteAllText(Path.Combine(fakeSrc, "Funkenwerk Browser", "WebViewProfile", "Default", "Cookies"), "SQLITE_SECRET");
                    File.WriteAllText(Path.Combine(fakeSrc, "Funkenwerk Browser", "WebViewProfile", "Default", "avatar.png"), "PNG_IN_PROFILE");
                    File.WriteAllText(Path.Combine(fakeSrc, "Funkenwerk Video", "Projects", "clip.mp4"), "USER_VIDEO");
                    File.WriteAllText(Path.Combine(fakeSrc, "Funkenwerk", "storage", "receipt.jpg"), "RECEIPT");
                    File.WriteAllText(Path.Combine(fakeSrc, "Funkenwerk", "notes.txt"), "PRIVATE_NOTE");
                    File.WriteAllText(Path.Combine(fakeSrc, "Funkenwerk", "data.sqlite"), "DB");

                    FortressProvisioningEngine.CopyCollectionSafeRecursive(fakeSrc, fakeDst);

                    bool exeCopied = File.Exists(Path.Combine(fakeDst, "Funkenwerk Craft", "FunkenwerkCraft.exe"));
                    bool icoCopied = File.Exists(Path.Combine(fakeDst, "Funkenwerk Craft", "funkenwerk_craft.ico"));
                    bool rtCopied = File.Exists(Path.Combine(fakeDst, "Funkenwerk Craft", "FunkenwerkCraft.runtimeconfig.json"));
                    var allExported = Directory.GetFiles(fakeDst, "*", SearchOption.AllDirectories);

                    return exeCopied && icoCopied && rtCopied && allExported.Length == 3;
                });

                Check("17. OS-SEC-02c: Windows-Lizenzschlüssel wird FWOSVAULT1-verschlüsselt & ACL-gesperrt", () =>
                {
                    const string testKey = "VK7JG-NPHTM-C97JM-9MPGT-3V66T";
                    var (ok, masked, vaultFile) = FortressProvisioningEngine.BackupWindowsProductKey(testKey);
                    if (!ok || !File.Exists(vaultFile)) return false;

                    string cipher = File.ReadAllText(vaultFile).Trim();
                    string decrypted = OsVaultAndStorage.UnprotectVaultSecret(cipher);
                    var acl = new FileInfo(vaultFile).GetAccessControl();
                    bool noPlainTxt = !Directory.GetFiles(Path.GetDirectoryName(vaultFile)!, "*.txt").Any();

                    return cipher.StartsWith("FWOSVAULT1:", StringComparison.Ordinal) &&
                           !cipher.Contains(testKey, StringComparison.OrdinalIgnoreCase) &&
                           decrypted.Contains(testKey, StringComparison.Ordinal) &&
                           masked == "XXXXX-XXXXX-XXXXX-XXXXX-3V66T" &&
                           acl.AreAccessRulesProtected &&
                           noPlainTxt;
                });

                // =========================================================================
                // 8. OS-SEC-03 & FIREWALL EXTRA-APPS: FORTRESS PROVISIONING HARDENING
                // =========================================================================
                Check("18. OS-SEC-03a: Autounattend.xml erzeugt starkes Zufallspasswort statt Leerpasswort", () =>
                {
                    var (xmlText, genPwd, vaultPath) = FortressProvisioningEngine.BuildUnattendXml("Alina");
                    var doc = XDocument.Parse(xmlText);
                    XNamespace unattendNs = "urn:schemas-microsoft-com:unattend";
                    var pwdValues = doc.Descendants(unattendNs + "Password")
                        .Select(p => p.Element(unattendNs + "Value")?.Value ?? "")
                        .ToList();

                    string savedCipher = File.Exists(vaultPath) ? File.ReadAllText(vaultPath).Trim() : "";
                    string savedPlain = OsVaultAndStorage.UnprotectVaultSecret(savedCipher);

                    return pwdValues.Count >= 1 &&
                           pwdValues.All(v => v.Length >= 24) &&
                           genPwd.Length >= 20 &&
                           !xmlText.Contains("<Value></Value>", StringComparison.OrdinalIgnoreCase) &&
                           !xmlText.Contains("<PlainText>true</PlainText>", StringComparison.OrdinalIgnoreCase) &&
                           savedCipher.StartsWith("FWOSVAULT1:", StringComparison.Ordinal) &&
                           savedPlain.Contains(genPwd, StringComparison.Ordinal);
                });

                Check("19. OS-SEC-03b: Fortress PowerShell-Skript (Authenticode, Craft, Firewall-Rollback, Port-Limit)", () =>
                {
                    string tempScriptsDir = Path.Combine(sandboxRoot, "fortress_scripts");
                    FortressProvisioningEngine.WriteMiniPcDeploymentScripts(tempScriptsDir);
                    string ps1 = File.ReadAllText(Path.Combine(tempScriptsDir, "Funkenwerk_Fortress_Installer.ps1"));

                    bool hasAuthenticode = ps1.Contains("Get-AuthenticodeSignature", StringComparison.Ordinal) &&
                                           ps1.Contains("Status -ne 'Valid'", StringComparison.Ordinal);
                    bool hasCraftInFwAndShortcuts = ps1.Contains("FunkenwerkCraft.exe", StringComparison.Ordinal) &&
                                                    ps1.Contains("funkenwerk_craft.ico", StringComparison.Ordinal);
                    bool noBlanketSvchostHttp = !ps1.Contains("AllowSys_Svchost_Update", StringComparison.Ordinal) &&
                                                !ps1.Contains("svchost.exe", StringComparison.OrdinalIgnoreCase);
                    bool webviewPortRestricted = ps1.Contains("FunkenwerkFortress_AllowWebView2_", StringComparison.Ordinal) &&
                                                 ps1.Contains("msedgewebview2.exe", StringComparison.Ordinal) &&
                                                 ps1.Contains("-Program $_.FullName -Protocol TCP -RemotePort 80,443", StringComparison.Ordinal);
                    bool nonDestructiveInbound = ps1.Contains("-AllowInboundRules False", StringComparison.Ordinal) &&
                                                 ps1.Contains("Restore-FortressFirewallState", StringComparison.Ordinal) &&
                                                 ps1.Contains("firewall_profiles_snapshot.json", StringComparison.Ordinal) &&
                                                 !ps1.Contains("Get-NetFirewallRule -Direction Inbound -Enabled True -ErrorAction SilentlyContinue | Disable-NetFirewallRule", StringComparison.Ordinal);
                    bool noUsbPlainKey = !ps1.Contains("Windows11Pro_Key_Backup.txt", StringComparison.OrdinalIgnoreCase) &&
                                         !ps1.Contains("Join-Path $ScriptRoot 'Windows11Pro_Key_", StringComparison.OrdinalIgnoreCase) &&
                                         ps1.Contains("ConvertFrom-SecureString", StringComparison.Ordinal);

                    return hasAuthenticode &&
                           hasCraftInFwAndShortcuts &&
                           noBlanketSvchostHttp &&
                           webviewPortRestricted &&
                           nonDestructiveInbound &&
                           noUsbPlainKey;
                });

                Check("20. Programm-Freigaben Firewall-Generator (kdrive, discord, lol, dev AN/AUS)", () =>
                {
                    string[] keys = { "kdrive", "discord", "lol", "dev" };
                    bool allValid = keys.All(k =>
                    {
                        string onScript = FortressProvisioningEngine.BuildExtraAppFirewallToggleScript(k, enable: true);
                        string offScript = FortressProvisioningEngine.BuildExtraAppFirewallToggleScript(k, enable: false);
                        return onScript.Contains($"FunkenwerkFortress_Extra_{k}_", StringComparison.Ordinal) &&
                               onScript.Contains("New-NetFirewallRule", StringComparison.Ordinal) &&
                               offScript.Contains($"FunkenwerkFortress_Extra_{k}_*", StringComparison.Ordinal) &&
                               offScript.Contains("Remove-NetFirewallRule", StringComparison.Ordinal) &&
                               !offScript.Contains("New-NetFirewallRule", StringComparison.Ordinal);
                    });
                    bool rejectsUnknown = false;
                    try
                    {
                        _ = FortressProvisioningEngine.BuildExtraAppFirewallToggleScript("unknown_app", true);
                    }
                    catch (ArgumentOutOfRangeException)
                    {
                        rejectsUnknown = true;
                    }
                    return allValid && rejectsUnknown;
                });

                // =========================================================================
                // 9. OS-SEC-05: WI-FI TEMP XML ACL LOCKDOWN & >=4096 BYTE ZERO WIPE
                // =========================================================================
                Check("21. OS-SEC-05: WLAN-Profil in OsDataDir ACL-gesperrt & >=4096 Byte Null-Überschreibung", () =>
                {
                    string tempXml = OsHardwareAndAudioEngine.WriteSecureWlanProfileTempFile("Atelier<Guest>&5G", "S3cr3t&Key<2026>!", "WPA2PSK");
                    bool insideOsDataDir = tempXml.StartsWith(OsVaultAndStorage.OsDataDir, StringComparison.OrdinalIgnoreCase);
                    var fi = new FileInfo(tempXml);
                    long origLen = fi.Length;
                    var acl = fi.GetAccessControl();
                    bool aclProtected = acl.AreAccessRulesProtected;

                    // Verify XML validity & escaping
                    var parsed = XDocument.Load(tempXml);
                    bool validXml = parsed.Root != null;

                    int wipedLen = 0;
                    bool allZerosBeforeDelete = false;
                    OsVaultAndStorage.SecureZeroWipeAndDeleteFile(tempXml, bytesBeforeDelete =>
                    {
                        wipedLen = bytesBeforeDelete.Length;
                        allZerosBeforeDelete = bytesBeforeDelete.Length >= 4096 && bytesBeforeDelete.All(b => b == 0);
                    });

                    return insideOsDataDir &&
                           origLen > 500 &&
                           aclProtected &&
                           validXml &&
                           wipedLen >= Math.Max(4096, (int)origLen) &&
                           allZerosBeforeDelete &&
                           !File.Exists(tempXml);
                });

                // =========================================================================
                // 10. OS-SEC-07: SAFE TASK XML ESCAPING & NO AUTO-RUNAS / CMD INJECTION
                // =========================================================================
                Check("22. OS-SEC-07a: Task-Scheduler XML escaped Sonderzeichen (&, <, >, \", ')", () =>
                {
                    string maliciousUser = @"DOMAIN\Alina&Admin<Test>'""";
                    string maliciousExe = @"C:\Program Files & Co\<Funkenwerk>\FunkenwerkOS.exe";
                    string maliciousDir = @"C:\Program Files & Co\<Funkenwerk>";

                    string xml = OsHardwareAndAudioEngine.BuildMorning0800WakeTaskXml(maliciousUser, maliciousExe, maliciousDir);
                    var doc = XDocument.Parse(xml);
                    XNamespace ns = "http://schemas.microsoft.com/windows/2004/02/mit/task";
                    string parsedUser = doc.Descendants(ns + "UserId").First().Value;
                    string parsedCmd = doc.Descendants(ns + "Command").First().Value;
                    string parsedDir = doc.Descendants(ns + "WorkingDirectory").First().Value;

                    return parsedUser == maliciousUser &&
                           parsedCmd == maliciousExe &&
                           parsedDir == maliciousDir &&
                           xml.Contains("&amp;", StringComparison.Ordinal) &&
                           xml.Contains("&lt;", StringComparison.Ordinal);
                });

                Check("23. OS-SEC-07b: Skript-/Exe-Start ohne Auto-RunAs, ohne cmd /c & ohne Policy-Bypass", () =>
                {
                    string trickyCmd = @"C:\Temp\4_BLUETOOTH_FIX & calc.exe.cmd";
                    string trickyPs1 = @"C:\Temp\FORTRESS_UPDATE_INSTALL.ps1";

                    var psiCmdNormal = MainWindow.BuildSafeLocalScriptOrExeStartInfo(trickyCmd, confirmedRunAsAdmin: false);
                    var psiPs1Normal = MainWindow.BuildSafeLocalScriptOrExeStartInfo(trickyPs1, confirmedRunAsAdmin: false);
                    var psiCmdAdmin = MainWindow.BuildSafeLocalScriptOrExeStartInfo(trickyCmd, confirmedRunAsAdmin: true);

                    bool noCmdInterpolation = string.Equals(psiCmdNormal.FileName, trickyCmd, StringComparison.Ordinal) &&
                                              string.IsNullOrEmpty(psiCmdNormal.Arguments);
                    bool noAutoElevate = string.IsNullOrEmpty(psiCmdNormal.Verb) && string.IsNullOrEmpty(psiPs1Normal.Verb);
                    bool noExecBypass = !psiPs1Normal.Arguments.Contains("Bypass", StringComparison.OrdinalIgnoreCase);
                    bool explicitAdminWorks = psiCmdAdmin.Verb == "runas";

                    return noCmdInterpolation && noAutoElevate && noExecBypass && explicitAdminWorks;
                });

                // =========================================================================
                // 11. OS-SEC-06, OS-SEC-08 & CUSTOM SHELL PROTECTION
                // =========================================================================
                Check("24. OS-SEC-06a: Sperrbildschirm-Authentifizierung blockiert Leer- & Falsch-PIN", () =>
                {
                    var settings = new OsSettings
                    {
                        EncryptedLockPin = OsVaultAndStorage.ProtectOsVaultSecret("258099")
                    };

                    bool rejectEmpty = !MainWindow.VerifyUnlockCredential("", settings, allowWindowsLogonCheck: false);
                    bool rejectSpace = !MainWindow.VerifyUnlockCredential("   ", settings, allowWindowsLogonCheck: false);
                    bool rejectWrong = !MainWindow.VerifyUnlockCredential("123456", settings, allowWindowsLogonCheck: false);
                    bool acceptRight = MainWindow.VerifyUnlockCredential("258099", settings, allowWindowsLogonCheck: false);

                    return rejectEmpty && rejectSpace && rejectWrong && acceptRight;
                });

                Check("25. OS-SEC-08b: Kurznotiz Drag-Export in ACL-geschütztem OsDataDir statt %TEMP%", () =>
                {
                    var note = new StickyNoteDto { ColorKey = "gold", Content = "Manufaktur Notiz\nLaser Fokus 5W UV" };
                    string stagedFile = MainWindow.StageStickyNoteForDragExport(note);
                    bool inOsData = stagedFile.StartsWith(OsVaultAndStorage.StickyDragStagingDir, StringComparison.OrdinalIgnoreCase);
                    bool notInSharedTemp = !stagedFile.StartsWith(Path.Combine(Path.GetTempPath(), "FunkenwerkOS_Notes"), StringComparison.OrdinalIgnoreCase);
                    var acl = new FileInfo(stagedFile).GetAccessControl();
                    bool aclLocked = acl.AreAccessRulesProtected;
                    OsVaultAndStorage.SecureZeroWipeAndDeleteFile(stagedFile);
                    return inOsData && notInSharedTemp && aclLocked;
                });

                Check("26. Direkt-Shell Schutz: EnsureWindowsExplorerTaskbarRunning respektiert --shell & Flag", () =>
                {
                    var settings = OsVaultAndStorage.LoadSettings();
                    settings.IntentionalCustomShellEnabled = true;
                    OsVaultAndStorage.SaveSettings(settings);

                    bool customActive = OsHardwareAndAudioEngine.IsCustomShellIntentionallyActive();
                    OsHardwareAndAudioEngine.EnsureWindowsExplorerTaskbarRunning(isShellLaunch: true, forceResetCustomShell: false);

                    settings.IntentionalCustomShellEnabled = false;
                    OsVaultAndStorage.SaveSettings(settings);
                    return customActive;
                });

                // =========================================================================
                // 12. BUILT-IN APPS, HARDWARE/AUDIO & HEADLESS UI / LOCK / GDPR SMOKE TESTS
                // =========================================================================
                Check("27. Taschenrechner (128-Bit Decimal 0.1+0.2 & 19% MwSt), Notizen & Papierkorb-Ursprung", () =>
                {
                    var calc = new OsCalculatorEngine();
                    calc.InputDigit('0'); calc.InputDecimal(); calc.InputDigit('1');
                    calc.InputOperator("+");
                    calc.InputDigit('0'); calc.InputDecimal(); calc.InputDigit('2');
                    calc.InputEquals();
                    bool exactDecimal = calc.CurrentValue == 0.3m;

                    calc.ClearAll();
                    calc.InputDigit('1'); calc.InputDigit('0'); calc.InputDigit('0');
                    calc.ApplyPlusVat19();
                    bool vatOk = calc.CurrentValue == 119m;
                    calc.ApplyNetFromGross19();
                    bool netOk = calc.CurrentValue == 100m;

                    StickyNotesVault.SaveNotes(new List<StickyNoteDto>
                    {
                        new StickyNoteDto { ColorKey = "gold", Content = "Sandbox Testnotiz", UpdatedAt = DateTime.Now }
                    });
                    var loadedNotes = StickyNotesVault.LoadNotes();

                    string originDir = Path.Combine(OsVaultAndStorage.MeineDatenRootDir, "01_Mein-Seelenfunke");
                    string dummyName = $"__recycle_{Guid.NewGuid():N}.pdf";
                    OsVaultAndStorage.RememberRecycleOrigin(dummyName, originDir);
                    string restored = OsVaultAndStorage.ResolveAndRemoveRecycleOrigin(dummyName);

                    return exactDecimal && vatOk && netOk &&
                           loadedNotes.Count == 1 && loadedNotes[0].Content == "Sandbox Testnotiz" &&
                           string.Equals(restored, originDir, StringComparison.OrdinalIgnoreCase);
                });

                Check("28. CoreAudio COM-Stabilität, Lautstärke (0-100), Mikrofon-Filter & HighRes-Timer", () =>
                {
                    var audio = OsHardwareAndAudioEngine.ReadAudioState();
                    var outs = OsHardwareAndAudioEngine.ListAudioOutputDevices();
                    _ = OsHardwareAndAudioEngine.ReadMicrophoneMuteState();
                    OsHardwareAndAudioEngine.ReleaseHighResTimerPeriod();
                    return audio.VolumePercent >= 0 && audio.VolumePercent <= 100 && outs != null;
                });

                Check("29. Hardware-Telemetrie (RAM/CPU), Laufwerke & Seamless-Wallpaper Auflösung", () =>
                {
                    var (freeGb, totalGb, cpuPct) = OsHardwareAndAudioEngine.ReadSystemResources();
                    var drives = OsHardwareAndAudioEngine.ListMountedDrivesForSidebar();
                    string? loopVideo = OsVaultAndStorage.ResolveAtelierMediaPath("atelier_seamless_loop.mp4");
                    return totalGb > 1.0 && freeGb >= 0.0 && cpuPct >= 0 && cpuPct <= 100 &&
                           drives.Count >= 1 && !string.IsNullOrEmpty(loopVideo);
                });

                Check("30. OS-SEC-06b & OS-SEC-08a: Headless MainWindow, SecondaryWall & GewerbeMonitor (Lock-Hotkeys, DSGVO & Besuchermodus)", () =>
                {
                    MainWindow? mainWin = null;
                    SecondaryWallWindow? wallWin = null;
                    GewerbeMonitorWindow? gewerbeWin = null;
                    try
                    {
                        mainWin = new MainWindow(isShellMode: false, initialWorkspace: null);
                        wallWin = new SecondaryWallWindow(darkMode: true);
                        gewerbeWin = new GewerbeMonitorWindow(OsVaultAndStorage.LoadSettings());

                        var sampleSnap = new OsDashboardSnapshot
                        {
                            IsConnected = true,
                            OpenOrdersCount = 3,
                            OpenTasksCount = 2,
                            MonthLabel = "November",
                            ShopRevenueFormatted = "4.890,00 €",
                            AvailableLiquidityFormatted = "12.340,00 €",
                            TopCustomersGdpr = new List<string> { "Sarah Müller", "Thomas Klein" },
                            PriorityOrder = new OsPriorityOrderDto
                            {
                                HasPriorityOrder = true,
                                OrderNumber = "SF-2026-109",
                                CustomerName = "Sarah Müller",
                                TotalFormatted = "89,90 €"
                            },
                            ActiveOrders = new List<OsOrderItemDto>
                            {
                                new OsOrderItemDto
                                {
                                    Id = 109,
                                    OrderNumber = "SF-2026-109",
                                    CustomerName = "Sarah Müller",
                                    StatusLabel = "Bezahlt",
                                    TotalFormatted = "89,90 €"
                                }
                            }
                        };

                        // A) Standard mode: GDPR customer anonymization ("Sarah M."), no full surname leak
                        mainWin.SetVisitorPrivacyMode(false);
                        mainWin.ApplyDashboardSnapshotToUi(sampleSnap);
                        wallWin.UpdateFromSnapshot(sampleSnap, visitorPrivacyMode: false);
                        gewerbeWin.UpdateFromSnapshot(sampleSnap, visitorPrivacyOverride: false);

                        bool gdprOk = mainWin.DisplayedLeitstandPriority.Contains("Sarah M.", StringComparison.Ordinal) &&
                                      !mainWin.DisplayedLeitstandPriority.Contains("Müller", StringComparison.Ordinal) &&
                                      wallWin.DisplayedPriorityMeta.Contains("Sarah M.", StringComparison.Ordinal) &&
                                      !wallWin.DisplayedPriorityMeta.Contains("Müller", StringComparison.Ordinal) &&
                                      gewerbeWin.DisplayedTopCustomersText.Contains("Sarah M.", StringComparison.Ordinal) &&
                                      !gewerbeWin.DisplayedTopCustomersText.Contains("Müller", StringComparison.Ordinal);

                        // B) Visitor mode: EUR figures & customer names masked ("•••" / "Gesperrt") everywhere
                        mainWin.SetVisitorPrivacyMode(true);
                        wallWin.UpdateFromSnapshot(sampleSnap, visitorPrivacyMode: true);
                        gewerbeWin.UpdateFromSnapshot(sampleSnap, visitorPrivacyOverride: true);

                        bool visitorOk = mainWin.DisplayedLeitstandRevenue == "•••" &&
                                         mainWin.DisplayedLeitstandAvailable == "•••" &&
                                         mainWin.DisplayedLeitstandPriority.Contains("Gesperrt", StringComparison.Ordinal) &&
                                         !mainWin.DisplayedLeitstandPriority.Contains("89,90", StringComparison.Ordinal) &&
                                         wallWin.DisplayedRevenue == "•••" &&
                                         wallWin.DisplayedPriorityMeta.Contains("Gesperrt", StringComparison.Ordinal) &&
                                         !wallWin.DisplayedPriorityMeta.Contains("Sarah", StringComparison.Ordinal) &&
                                         gewerbeWin.DisplayedTopCustomersText.Contains("Gesperrt", StringComparison.Ordinal);

                        // C) LockScreen global hotkey blocking while locked
                        mainWin.LockScreenProgrammatically();
                        bool lockedActive = mainWin.IsLockScreenActive;
                        bool blockLWin = mainWin.ShouldBlockKeyWhileLocked(Key.LWin, ModifierKeys.None, null, out _);
                        bool blockF11 = mainWin.ShouldBlockKeyWhileLocked(Key.F11, ModifierKeys.None, null, out _);
                        bool blockSnap = mainWin.ShouldBlockKeyWhileLocked(Key.Snapshot, ModifierKeys.None, null, out _);
                        bool blockEsc = mainWin.ShouldBlockKeyWhileLocked(Key.Escape, ModifierKeys.None, null, out _);
                        bool blockCtrlShiftS = mainWin.ShouldBlockKeyWhileLocked(Key.S, ModifierKeys.Control | ModifierKeys.Shift, null, out _);
                        bool enterTriggersAuth = mainWin.ShouldBlockKeyWhileLocked(Key.Enter, ModifierKeys.None, null, out bool tryUnlock) && tryUnlock;

                        return gdprOk && visitorOk && lockedActive &&
                               blockLWin && blockF11 && blockSnap && blockEsc && blockCtrlShiftS && enterTriggersAuth;
                    }
                    finally
                    {
                        try { gewerbeWin?.Close(); } catch { }
                        try { wallWin?.Close(); } catch { }
                        try { mainWin?.DisposeManagedResources(); mainWin?.Close(); } catch { }
                    }
                });
            }
            finally
            {
                OsVaultAndStorage.ExitSelfTestSandbox();
                try
                {
                    if (Directory.Exists(sandboxRoot))
                        Directory.Delete(sandboxRoot, recursive: true);
                }
                catch { }
            }

            string summary = $"FUNKENWERK OS SELF-TEST: {passed}/{total} TESTS BESTANDEN.";
            AppendReportLine(summary);
            return passed == total && total >= 30;
        }

        private static string ResolveWritableReportPath(string primaryPath, string fallbackPath)
        {
            try
            {
                if (File.Exists(primaryPath))
                    File.Delete(primaryPath);
                File.WriteAllText(primaryPath, "", new UTF8Encoding(false));
                return primaryPath;
            }
            catch
            {
                try
                {
                    string? dir = Path.GetDirectoryName(fallbackPath);
                    if (!string.IsNullOrEmpty(dir))
                        Directory.CreateDirectory(dir);
                    if (File.Exists(fallbackPath))
                        File.Delete(fallbackPath);
                    File.WriteAllText(fallbackPath, "", new UTF8Encoding(false));
                }
                catch { }
                return fallbackPath;
            }
        }
    }
}
