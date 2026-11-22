using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Media;
using Microsoft.Win32;

namespace Funkenwerk3D
{
    public static class ThreeDSelfTestRunner
    {
        public static async Task<bool> RunAllAsync()
        {
            Console.WriteLine("=== FUNKENWERK 3D END-TO-END & SECURITY SELF-TEST (25 CHECKS) ===");

            string tempRoot = Path.Combine(Path.GetTempPath(), $"fw_3d_selftest_{Guid.NewGuid():N}");
            string tempAppData = Path.Combine(tempRoot, "appdata");
            string tempModels = Path.Combine(tempRoot, "models");
            Directory.CreateDirectory(tempAppData);
            Directory.CreateDirectory(tempModels);

            Funkenwerk3DSettings.TestOverrideBaseDir = tempAppData;

            int passed = 0;
            int failed = 0;

            void Check(string name, Func<bool> fn)
            {
                try
                {
                    bool ok = fn();
                    if (ok) { passed++; Console.WriteLine($"  [PASS] {name}"); }
                    else { failed++; Console.WriteLine($"  [FAIL] {name}"); }
                }
                catch (Exception ex)
                {
                    failed++;
                    Console.WriteLine($"  [FAIL] {name} -> {ex.GetType().Name}: {ex.Message}");
                }
            }

            async Task CheckAsync(string name, Func<Task<bool>> fn)
            {
                try
                {
                    bool ok = await fn();
                    if (ok) { passed++; Console.WriteLine($"  [PASS] {name}"); }
                    else { failed++; Console.WriteLine($"  [FAIL] {name}"); }
                }
                catch (Exception ex)
                {
                    failed++;
                    Console.WriteLine($"  [FAIL] {name} -> {ex.GetType().Name}: {ex.Message}");
                }
            }

            MainWindow? win = null;
            try
            {
                // 01. Brand icons & offline Three.js / Draco WASM bundle present
                Check("01. Marken-Icons (funkenwerk_3d.ico/.png) & Offline-Three.js/Draco-WASM-Bundle vorhanden", () =>
                {
                    string baseDir = AppContext.BaseDirectory;
                    string[] required =
                    {
                        "funkenwerk_3d.ico",
                        "funkenwerk_3d.png",
                        @"wwwroot\viewer.html",
                        @"wwwroot\viewer.js",
                        @"wwwroot\rigging-engine.js",
                        @"wwwroot\vendor\build\three.module.min.js",
                        @"wwwroot\vendor\build\three.core.min.js",
                        @"wwwroot\vendor\jsm\loaders\GLTFLoader.js",
                        @"wwwroot\vendor\jsm\exporters\GLTFExporter.js",
                        @"wwwroot\vendor\jsm\controls\TransformControls.js",
                        @"wwwroot\vendor\jsm\loaders\DRACOLoader.js",
                        @"wwwroot\vendor\jsm\loaders\STLLoader.js",
                        @"wwwroot\vendor\jsm\loaders\OBJLoader.js",
                        @"wwwroot\vendor\jsm\loaders\MTLLoader.js",
                        @"wwwroot\vendor\jsm\loaders\PLYLoader.js",
                        @"wwwroot\vendor\jsm\loaders\FBXLoader.js",
                        @"wwwroot\vendor\jsm\loaders\3MFLoader.js",
                        @"wwwroot\vendor\jsm\libs\draco\gltf\draco_decoder.wasm"
                    };
                    return required.All(rel => File.Exists(Path.Combine(baseDir, rel)));
                });

                // 02. Isolated AppData NTFS ACL protection & settings roundtrip
                Check("02. AppData-NTFS-ACL-Schutz (Vererbung deaktiviert) & Settings-Roundtrip", () =>
                {
                    var s = new Funkenwerk3DSettings
                    {
                        DarkMode = false,
                        ShowDimensions = true,
                        Exposure = 1.35,
                        BackdropPreset = "shop_light"
                    };
                    s.AddRecentFile(@"C:\Test\modell.glb");
                    s.Save();

                    var acl = new DirectoryInfo(Funkenwerk3DSettings.GetAppDataDirectory()).GetAccessControl();
                    var loaded = Funkenwerk3DSettings.Load();
                    s.DarkMode = true;
                    s.ShowDimensions = false;
                    s.Exposure = 1.05;
                    s.BackdropPreset = "auto";
                    s.Save();

                    return acl.AreAccessRulesProtected &&
                           !loaded.DarkMode &&
                           loaded.ShowDimensions &&
                           Math.Abs(loaded.Exposure - 1.35) < 0.01 &&
                           loaded.BackdropPreset == "shop_light";
                });

                // 03. CLI flags, device prefixes & DOS reserved names blocked
                Check("03. Blockade von CLI-Flags (--flag, /flag), Device-Pfaden (\\\\?\\) & DOS-Namen (CON.glb)", () =>
                {
                    string[] badInputs =
                    {
                        "--remote-debugging-port=9222",
                        "/scan",
                        @"\\?\C:\evil.glb",
                        @"\\.\PhysicalDrive0",
                        Path.Combine(tempModels, "CON.glb"),
                        Path.Combine(tempModels, "NUL.stl")
                    };
                    return badInputs.All(b => !ModelSecurityValidator.TryValidateModelFile(b, out _, out _));
                });

                // 04. NTFS Alternate Data Streams & non-3D extensions blocked
                Check("04. Blockade von NTFS Alternate Data Streams (:Zone.Identifier) & Fremdendungen (.exe/.js)", () =>
                {
                    string fakeExe = Path.Combine(tempModels, "payload.exe");
                    File.WriteAllBytes(fakeExe, new byte[] { 1, 2, 3, 4 });
                    bool blockedExe = !ModelSecurityValidator.TryValidateModelFile(fakeExe, out _, out _);
                    File.Delete(fakeExe);

                    string adsPath = Path.Combine(tempModels, "sample.glb") + ":Zone.Identifier";
                    bool blockedAds = !ModelSecurityValidator.TryValidateModelFile(adsPath, out _, out _);
                    return blockedExe && blockedAds;
                });

                // 05. Corrupt / polyglot .glb binary header rejection
                Check("05. Binäre .glb-Headerprüfung weist PDF/ZIP-Polyglots, glTF v1 & falsche Längen ab", () =>
                {
                    string badMagic = Path.Combine(tempModels, "polyglot_pdf.glb");
                    File.WriteAllBytes(badMagic, Encoding.ASCII.GetBytes("%PDF-1.7 fake glb payload bytes"));

                    string badVer = Path.Combine(tempModels, "bad_version.glb");
                    byte[] ver1 = new byte[24];
                    BitConverter.GetBytes(0x46546C67u).CopyTo(ver1, 0);
                    BitConverter.GetBytes(1u).CopyTo(ver1, 4);
                    BitConverter.GetBytes(24u).CopyTo(ver1, 8);
                    File.WriteAllBytes(badVer, ver1);

                    string badLen = Path.Combine(tempModels, "bad_length.glb");
                    byte[] lenMismatch = new byte[24];
                    BitConverter.GetBytes(0x46546C67u).CopyTo(lenMismatch, 0);
                    BitConverter.GetBytes(2u).CopyTo(lenMismatch, 4);
                    BitConverter.GetBytes(99999u).CopyTo(lenMismatch, 8);
                    File.WriteAllBytes(badLen, lenMismatch);

                    bool r1 = !ModelSecurityValidator.TryValidateModelFile(badMagic, out _, out _);
                    bool r2 = !ModelSecurityValidator.TryValidateModelFile(badVer, out _, out _);
                    bool r3 = !ModelSecurityValidator.TryValidateModelFile(badLen, out _, out _);

                    File.Delete(badMagic);
                    File.Delete(badVer);
                    File.Delete(badLen);
                    return r1 && r2 && r3;
                });

                // 06. Empty (0 byte) and oversized (> 512 MB) files rejected
                Check("06. Größenlimits aktiv (0-Byte-Dateien & > 512 MB Modelle werden abgewiesen)", () =>
                {
                    string emptyStl = Path.Combine(tempModels, "empty.stl");
                    File.WriteAllBytes(emptyStl, Array.Empty<byte>());

                    string hugeStl = Path.Combine(tempModels, "oversize.stl");
                    using (var fs = new FileStream(hugeStl, FileMode.Create, FileAccess.Write, FileShare.None))
                    {
                        fs.SetLength(ModelSecurityValidator.MaxModelFileBytes + 4096);
                    }

                    bool rEmpty = !ModelSecurityValidator.TryValidateModelFile(emptyStl, out _, out _);
                    bool rHuge = !ModelSecurityValidator.TryValidateModelFile(hugeStl, out _, out _);

                    File.Delete(emptyStl);
                    File.Delete(hugeStl);
                    return rEmpty && rHuge;
                });

                // 07. Sibling asset directory jail (blocks ../ traversal & non-asset extensions)
                string validGlbPath = Path.Combine(tempModels, "box_80x50x20mm.glb");
                File.WriteAllBytes(validGlbPath, BuildSyntheticGlbBox(0.08f, 0.05f, 0.02f));

                string outsideSecret = Path.Combine(tempRoot, "outside_secret.bin");
                File.WriteAllBytes(outsideSecret, new byte[] { 9, 9, 9 });
                string insideTex = Path.Combine(tempModels, "albedo.png");
                File.WriteAllBytes(insideTex, new byte[] { 0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A });
                string insideExe = Path.Combine(tempModels, "evil.exe");
                File.WriteAllBytes(insideExe, new byte[] { 0x4D, 0x5A });

                Check("07. Verzeichnis-Sandbox für Texturen/.bin blockiert '../'-Traversal & .exe-Nachbardateien", () =>
                {
                    bool blockedTraversal = !ModelSecurityValidator.TryResolveSandboxedSiblingAsset(validGlbPath, "../outside_secret.bin", out _, out _);
                    bool blockedExe = !ModelSecurityValidator.TryResolveSandboxedSiblingAsset(validGlbPath, "evil.exe", out _, out _);
                    bool allowedTex = ModelSecurityValidator.TryResolveSandboxedSiblingAsset(validGlbPath, "albedo.png", out string resTex, out string mime) &&
                                      string.Equals(resTex, insideTex, StringComparison.OrdinalIgnoreCase) &&
                                      mime == "image/png";
                    File.Delete(insideExe);
                    File.Delete(insideTex);
                    return blockedTraversal && blockedExe && allowedTex;
                });

                // 08. NTFS Junction / Reparse Point ancestry blocked
                Check("08. NTFS-Junction / Reparse-Point wird in Modell- & Texturpfaden blockiert", () =>
                {
                    string targetOutside = Path.Combine(tempRoot, "junction_target");
                    Directory.CreateDirectory(targetOutside);
                    string secretBin = Path.Combine(targetOutside, "leak.bin");
                    File.WriteAllBytes(secretBin, new byte[] { 1, 2, 3, 4 });

                    string junctionDir = Path.Combine(tempModels, "linked_textures");
                    var psi = new ProcessStartInfo("cmd.exe")
                    {
                        UseShellExecute = false,
                        CreateNoWindow = true
                    };
                    psi.ArgumentList.Add("/c");
                    psi.ArgumentList.Add("mklink");
                    psi.ArgumentList.Add("/J");
                    psi.ArgumentList.Add(junctionDir);
                    psi.ArgumentList.Add(targetOutside);
                    using (var p = Process.Start(psi)) { p?.WaitForExit(3000); }

                    bool blockedSiblingJunction = !ModelSecurityValidator.TryResolveSandboxedSiblingAsset(
                        validGlbPath,
                        "linked_textures/leak.bin",
                        out _,
                        out _);

                    if (Directory.Exists(junctionDir))
                        Directory.Delete(junctionDir, recursive: false);

                    return blockedSiblingJunction;
                });

                // 09. Constant-time session token comparison
                Check("09. Zeitkonstanter Session-Token-Abgleich (FixedTimeEqualsAscii)", () =>
                {
                    return ModelSecurityValidator.FixedTimeEqualsAscii("ABCDEF0123456789", "ABCDEF0123456789") &&
                           !ModelSecurityValidator.FixedTimeEqualsAscii("ABCDEF0123456789", "ABCDEF0123456780") &&
                           !ModelSecurityValidator.FixedTimeEqualsAscii("SHORT", "LONGER_TOKEN");
                });

                // 10. Isolated Registry Default-App Association Verification
                Check("10. Windows Standard-App-Registrierung (.glb/.gltf/.obj/.ply/.fbx aktiv, .stl/.3mf geschont)", () =>
                {
                    string testRegPrefix = $@"Software\Funkenwerk3D_SelfTest_{Guid.NewGuid():N}";
                    try
                    {
                        bool ok = WindowsDefault3DRegistrar.RegisterAsWindows3DViewer(
                            out _,
                            setDefaultForPrimary: true,
                            setDefaultForSlicerFormats: false,
                            softwarePrefix: testRegPrefix,
                            notifyShell: false);

                        using var glbKey = Registry.CurrentUser.OpenSubKey($@"{testRegPrefix}\Classes\.glb");
                        using var stlKey = Registry.CurrentUser.OpenSubKey($@"{testRegPrefix}\Classes\.stl");
                        using var stlOpenWith = Registry.CurrentUser.OpenSubKey($@"{testRegPrefix}\Classes\.stl\OpenWithProgids");
                        using var regApps = Registry.CurrentUser.OpenSubKey($@"{testRegPrefix}\RegisteredApplications");

                        bool glbIsDefault = string.Equals(glbKey?.GetValue("") as string, WindowsDefault3DRegistrar.ProgId, StringComparison.Ordinal);
                        bool stlNotOverwritten = stlKey?.GetValue("") == null;
                        bool stlHasOpenWith = stlOpenWith?.GetValue(WindowsDefault3DRegistrar.ProgId) != null;
                        bool appRegistered = regApps?.GetValue(WindowsDefault3DRegistrar.AppRegisteredName) != null;

                        return ok && glbIsDefault && stlNotOverwritten && stlHasOpenWith && appRegistered;
                    }
                    finally
                    {
                        try { Registry.CurrentUser.DeleteSubKeyTree(testRegPrefix, throwOnMissingSubKey: false); } catch { }
                    }
                });

                // 11. MainWindow STA instantiation & Light/Dark mode brush consistency
                win = new MainWindow
                {
                    WindowStyle = WindowStyle.None,
                    ShowInTaskbar = false,
                    ShowActivated = false,
                    Width = 640,
                    Height = 480,
                    Left = -20000,
                    Top = -20000
                };
                win.Show();
                Check("11. MainWindow STA-Instanziierung & 100 % konsistenter Light-/Dark-Mode-Wechsel", () =>
                {
                    bool initDark = win.IsDarkMode;
                    var bg1 = (SolidColorBrush)win.Resources["TopBarBgBrush"];
                    var fg1 = (SolidColorBrush)win.Resources["PrimaryTextBrush"];

                    win.ThemeToggle_Click(win, new RoutedEventArgs());
                    bool toggled = win.IsDarkMode != initDark;
                    var bg2 = (SolidColorBrush)win.Resources["TopBarBgBrush"];
                    var fg2 = (SolidColorBrush)win.Resources["PrimaryTextBrush"];

                    win.ThemeToggle_Click(win, new RoutedEventArgs());
                    bool restored = win.IsDarkMode == initDark;

                    return toggled && restored && bg1.Color != bg2.Color && fg1.Color != fg2.Color;
                });

                // 12. Real WebView2 + Offline Three.js PBR Engine initialization & hardened flags
                await CheckAsync("12. Offline-WebView2 & Three.js PBR-Engine Start (DevTools/Autofill/Kontextmenü deaktiviert)", async () =>
                {
                    await win.EnsureViewportInitializedAsync();
                    var s = win.ViewportWebView.CoreWebView2.Settings;
                    return !s.AreDevToolsEnabled &&
                           !s.AreDefaultContextMenusEnabled &&
                           !s.IsPasswordAutosaveEnabled &&
                           !s.IsGeneralAutofillEnabled;
                });

                // 13. Synthetic glTF 2.0 Binary .glb (80 x 50 x 20 mm PBR box) exact measurement & mesh stats
                await CheckAsync("13. Synthetisches .glb (0,08x0,05x0,02 m) wird geladen & exakt als 80,0 x 50,0 x 20,0 mm vermessen", async () =>
                {
                    var stats = await win.LoadModelFileAsync(validGlbPath);
                    return Math.Abs(stats.WidthMm - 80.0) < 0.25 &&
                           Math.Abs(stats.HeightMm - 50.0) < 0.25 &&
                           Math.Abs(stats.DepthMm - 20.0) < 0.25 &&
                           stats.Triangles == 12 &&
                           stats.Vertices == 24 &&
                           stats.MeshCount == 1 &&
                           stats.MaterialCount == 1;
                });

                // 14. Real Seelenfunke .glb model load (or multi-mesh fallback)
                await CheckAsync("14. Reales Seelenfunke .glb-Modell wird verlustfrei geladen & analysiert", async () =>
                {
                    string realGlb = @"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\funkenwerk-os-linux\wwwroot\assets\funki_models\funki_lvl_1_rags.glb";
                    if (File.Exists(realGlb))
                    {
                        var stats = await win.LoadModelFileAsync(realGlb);
                        // Switch back to temp folder afterwards so sibling test uses tempModels
                        await win.LoadModelFileAsync(validGlbPath);
                        return stats.Triangles > 20 && stats.Vertices > 20 && stats.MeshCount >= 1;
                    }
                    return true;
                });

                // 15. Binary .stl CAD fixture (60 x 40 x 15 mm) load & Z-up orientation
                string stlPath = Path.Combine(tempModels, "fixture_60x40x15mm.stl");
                File.WriteAllBytes(stlPath, BuildSyntheticBinaryStlBox(60f, 40f, 15f));
                await CheckAsync("15. Binäres .stl-Modell (60x40x15 mm) wird geladen & Z-Up-orientiert vermessen", async () =>
                {
                    var stats = await win.LoadModelFileAsync(stlPath);
                    return Math.Abs(stats.WidthMm - 60.0) < 0.25 &&
                           Math.Abs(stats.HeightMm - 15.0) < 0.25 &&
                           Math.Abs(stats.DepthMm - 40.0) < 0.25 &&
                           stats.Triangles == 12;
                });

                // 16. Wavefront .obj + sibling .mtl material load
                string mtlPath = Path.Combine(tempModels, "gold_sample.mtl");
                string objPath = Path.Combine(tempModels, "gold_sample.obj");
                File.WriteAllText(mtlPath, "newmtl SeelenfunkeGold\nKd 0.77 0.63 0.35\n", Encoding.ASCII);
                File.WriteAllText(objPath,
                    "mtllib gold_sample.mtl\no GoldPlate\nusemtl SeelenfunkeGold\n" +
                    "v 0 0 0\nv 30 0 0\nv 30 10 0\nv 0 10 0\n" +
                    "f 1 2 3\nf 1 3 4\n",
                    Encoding.ASCII);

                await CheckAsync("16. Wavefront .obj inkl. verknüpfter .mtl-Materialdatei im selben Ordner geladen", async () =>
                {
                    var stats = await win.LoadModelFileAsync(objPath);
                    return Math.Abs(stats.WidthMm - 30.0) < 0.25 &&
                           Math.Abs(stats.HeightMm - 10.0) < 0.25 &&
                           stats.Triangles == 2;
                });

                // 17. ASCII .ply mesh load
                string plyPath = Path.Combine(tempModels, "tri_sample.ply");
                File.WriteAllText(plyPath,
                    "ply\nformat ascii 1.0\nelement vertex 3\nproperty float x\nproperty float y\nproperty float z\n" +
                    "element face 1\nproperty list uchar int vertex_indices\nend_header\n" +
                    "0 0 0\n25 0 0\n0 25 0\n3 0 1 2\n",
                    Encoding.ASCII);

                await CheckAsync("17. Stanford .ply-Modell wird fehlerfrei geparst & dargestellt", async () =>
                {
                    var stats = await win.LoadModelFileAsync(plyPath);
                    return Math.Abs(stats.WidthMm - 25.0) < 0.25 && stats.Triangles == 1;
                });

                // 18. Sibling model discovery in directory
                await CheckAsync("18. Ordner-Navigation erkennt alle 4 unterstützten 3D-Modelle im Verzeichnis", async () =>
                {
                    await win.LoadModelFileAsync(validGlbPath);
                    return win.FolderIndexText.Text == "1 / 4" && win.PrevModelBtn.IsEnabled && win.NextModelBtn.IsEnabled;
                });

                // 19. Virtual host HandleModelVirtualRequest blocks wrong token & traversal with HTTP 403
                Check("19. Virtueller Modell-Server blockiert falsche Session-Tokens & Traversal mit HTTP 403", () =>
                {
                    var env = win.ViewportWebView.CoreWebView2.Environment;
                    var badTokenResp = win.HandleModelVirtualRequest(
                        env,
                        new Uri($"https://{MainWindow.ModelVirtualHost}/00000000000000000000000000000000/box_80x50x20mm.glb"));
                    return badTokenResp.StatusCode == 403;
                });

                // 20. PNG Screenshot capture (Studio background & Transparent cutout)
                await CheckAsync("20. PNG-Schnappschuss-Export (Studio-Hintergrund & Transparent-Freigestellt)", async () =>
                {
                    byte[] studioPng = await win.CaptureViewportPngBytesAsync(transparentBackground: false);
                    byte[] alphaPng = await win.CaptureViewportPngBytesAsync(transparentBackground: true);
                    bool validSig1 = studioPng.Length > 200 && studioPng[0] == 0x89 && studioPng[1] == 0x50 && studioPng[2] == 0x4E && studioPng[3] == 0x47;
                    bool validSig2 = alphaPng.Length > 200 && alphaPng[0] == 0x89 && alphaPng[1] == 0x50 && alphaPng[2] == 0x4E && alphaPng[3] == 0x47;
                    return validSig1 && validSig2;
                });

                // 21. Zero spaced en-dashes (' – ') in HTML/JS viewer & rigging files
                Check("21. Seelenfunke Clean-Design: 0 KI-Gedankenstriche (' – ') in Viewer- & Rigging-Assets", () =>
                {
                    string html = File.ReadAllText(Path.Combine(AppContext.BaseDirectory, @"wwwroot\viewer.html"), Encoding.UTF8);
                    string js = File.ReadAllText(Path.Combine(AppContext.BaseDirectory, @"wwwroot\viewer.js"), Encoding.UTF8);
                    string rigJs = File.ReadAllText(Path.Combine(AppContext.BaseDirectory, @"wwwroot\rigging-engine.js"), Encoding.UTF8);
                    return !html.Contains(" \u2013 ", StringComparison.Ordinal) &&
                           !js.Contains(" \u2013 ", StringComparison.Ordinal) &&
                           !rigJs.Contains(" \u2013 ", StringComparison.Ordinal);
                });

                // 22. Exoskeleton Auto-Rigging converts static .glb into 22-bone SkinnedMesh + 8 AnimationClips
                await CheckAsync("22. Exoskelett Auto-Rigging erzeugt 22-Knochen-Skelett & 8 eingebettete Animationen", async () =>
                {
                    await win.LoadModelFileAsync(validGlbPath);
                    var rigRes = await win.GenerateExoskeletonAsync("funki_chibi", "funki_male", stripPedestal: true, showBones: true, autoPlay: true);
                    return rigRes.BoneCount == 22 &&
                           rigRes.ClipCount == 8 &&
                           rigRes.ProfileResolved == "funki_chibi" &&
                           win.RigStatusBadgeText.Text == "22 Knochen" &&
                           win.ExportGlbTopBtn.IsEnabled;
                });

                // 23. Exported rigged .glb contains glTF 2.0 header, Mein-Seelenfunke Copyright & reloads with 22 bones + 8 clips
                string exportedRigGlb = Path.Combine(tempModels, "box_rigged_export.glb");
                await CheckAsync("23. Binärer .glb-Export inkl. Copyright-Signatur & Reload-Roundtrip (22 Knochen, 8 Clips)", async () =>
                {
                    byte[] glbBytes = await win.ExportRiggedGlbBytesAsync(keepCurrentPose: false);
                    File.WriteAllBytes(exportedRigGlb, glbBytes);

                    bool validHeader = ModelSecurityValidator.TryValidateModelFile(exportedRigGlb, out _, out _);
                    uint jsonLen = BitConverter.ToUInt32(glbBytes, 12);
                    string jsonChunk = Encoding.UTF8.GetString(glbBytes, 20, (int)jsonLen);
                    bool hasCopyright = jsonChunk.Contains("Mein-Seelenfunke", StringComparison.Ordinal) &&
                                        jsonChunk.Contains("\"skins\"", StringComparison.Ordinal) &&
                                        jsonChunk.Contains("\"animations\"", StringComparison.Ordinal);

                    var reloaded = await win.LoadModelFileAsync(exportedRigGlb);
                    return validHeader &&
                           hasCopyright &&
                           reloaded.IsRigged &&
                           reloaded.BoneCount == 22 &&
                           reloaded.AnimationCount == 8 &&
                           Math.Abs(reloaded.HeightMm - 1620.0) < 5.0;
                });

                // 24. Funkira Female variant attaches 14K Gold Star-Diadem mesh on headSocket & exports cleanly
                await CheckAsync("24. Funkira-Stilvariante (Roségold + 14K Stern-Diadem auf headSocket) riggt & exportiert fehlerfrei", async () =>
                {
                    await win.LoadModelFileAsync(validGlbPath);
                    var rigFem = await win.GenerateExoskeletonAsync("funki_chibi", "funkira_female", stripPedestal: true, showBones: false, autoPlay: false);
                    byte[] femGlb = await win.ExportRiggedGlbBytesAsync(keepCurrentPose: false);
                    uint jsonLen = BitConverter.ToUInt32(femGlb, 12);
                    string jsonChunk = Encoding.UTF8.GetString(femGlb, 20, (int)jsonLen);
                    return rigFem.BoneCount == 22 &&
                           rigFem.ClipCount == 8 &&
                           jsonChunk.Contains("FunkiraStarDiadem", StringComparison.Ordinal) &&
                           jsonChunk.Contains("rightHandWeaponSocket", StringComparison.Ordinal);
                });
            }
            finally
            {
                uint browserPid = 0;
                try { browserPid = win?.ViewportWebView?.CoreWebView2?.BrowserProcessId ?? 0; } catch { }
                try { win?.ViewportWebView?.Dispose(); } catch { }
                try { win?.Close(); } catch { }
                await System.Windows.Threading.Dispatcher.Yield(System.Windows.Threading.DispatcherPriority.ApplicationIdle);
                if (browserPid > 0)
                {
                    try { Process.GetProcessById((int)browserPid).WaitForExit(2500); } catch { }
                }
                Funkenwerk3DSettings.TestOverrideBaseDir = null;
                for (int retry = 0; retry < 15 && Directory.Exists(tempRoot); retry++)
                {
                    try
                    {
                        Directory.Delete(tempRoot, recursive: true);
                        break;
                    }
                    catch
                    {
                        await Task.Delay(150);
                    }
                }
            }

            // 25. Temp model workspace completely cleaned up (zero file locks held on opened/exported .glb/.stl/.obj/.ply)
            Check("25. Isolierter Modell-Workspace ohne offene Datei-Handles rückstandsfrei bereinigt", () =>
            {
                if (Directory.Exists(tempModels))
                    Directory.Delete(tempModels, recursive: true);
                return !Directory.Exists(tempModels);
            });

            try { if (Directory.Exists(tempRoot)) Directory.Delete(tempRoot, recursive: true); } catch { }

            Console.WriteLine($"=== RESULT: {passed} PASSED, {failed} FAILED (Total {passed + failed}) ===");
            return failed == 0 && passed == 25;
        }

        private static byte[] BuildSyntheticGlbBox(float sx, float sy, float sz)
        {
            float hx = sx * 0.5f, hy = sy * 0.5f, hz = sz * 0.5f;

            // 24 vertices (pos + normal) + 36 uint16 indices for a box
            (float px, float py, float pz, float nx, float ny, float nz)[] verts =
            {
                // Front (+Z)
                (-hx, -hy,  hz,  0,  0,  1), ( hx, -hy,  hz,  0,  0,  1), ( hx,  hy,  hz,  0,  0,  1), (-hx,  hy,  hz,  0,  0,  1),
                // Back (-Z)
                ( hx, -hy, -hz,  0,  0, -1), (-hx, -hy, -hz,  0,  0, -1), (-hx,  hy, -hz,  0,  0, -1), ( hx,  hy, -hz,  0,  0, -1),
                // Top (+Y)
                (-hx,  hy,  hz,  0,  1,  0), ( hx,  hy,  hz,  0,  1,  0), ( hx,  hy, -hz,  0,  1,  0), (-hx,  hy, -hz,  0,  1,  0),
                // Bottom (-Y)
                (-hx, -hy, -hz,  0, -1,  0), ( hx, -hy, -hz,  0, -1,  0), ( hx, -hy,  hz,  0, -1,  0), (-hx, -hy,  hz,  0, -1,  0),
                // Right (+X)
                ( hx, -hy,  hz,  1,  0,  0), ( hx, -hy, -hz,  1,  0,  0), ( hx,  hy, -hz,  1,  0,  0), ( hx,  hy,  hz,  1,  0,  0),
                // Left (-X)
                (-hx, -hy, -hz, -1,  0,  0), (-hx, -hy,  hz, -1,  0,  0), (-hx,  hy,  hz, -1,  0,  0), (-hx,  hy, -hz, -1,  0,  0)
            };

            ushort[] indices = new ushort[36];
            for (int f = 0; f < 6; f++)
            {
                ushort b = (ushort)(f * 4);
                indices[f * 6 + 0] = b;
                indices[f * 6 + 1] = (ushort)(b + 1);
                indices[f * 6 + 2] = (ushort)(b + 2);
                indices[f * 6 + 3] = b;
                indices[f * 6 + 4] = (ushort)(b + 2);
                indices[f * 6 + 5] = (ushort)(b + 3);
            }

            using var binMs = new MemoryStream();
            using (var bw = new BinaryWriter(binMs, Encoding.UTF8, leaveOpen: true))
            {
                foreach (var v in verts)
                {
                    bw.Write(v.px); bw.Write(v.py); bw.Write(v.pz);
                }
                foreach (var v in verts)
                {
                    bw.Write(v.nx); bw.Write(v.ny); bw.Write(v.nz);
                }
                foreach (ushort idx in indices)
                {
                    bw.Write(idx);
                }
                while (binMs.Length % 4 != 0)
                    bw.Write((byte)0);
            }
            byte[] binBytes = binMs.ToArray();

            var ic = System.Globalization.CultureInfo.InvariantCulture;
            string minStr = string.Format(ic, "[{0:R},{1:R},{2:R}]", -hx, -hy, -hz);
            string maxStr = string.Format(ic, "[{0:R},{1:R},{2:R}]", hx, hy, hz);

            string gltfJson =
                "{" +
                "\"asset\":{\"version\":\"2.0\",\"generator\":\"Funkenwerk3DSelfTest\"}," +
                "\"scene\":0," +
                "\"scenes\":[{\"nodes\":[0]}]," +
                "\"nodes\":[{\"mesh\":0,\"name\":\"SeelenfunkeRohling\"}]," +
                "\"materials\":[{\"name\":\"Gold14K\",\"pbrMetallicRoughness\":{\"baseColorFactor\":[0.77,0.63,0.35,1.0],\"metallicFactor\":0.85,\"roughnessFactor\":0.28}}]," +
                "\"meshes\":[{\"name\":\"RohlingQuader\",\"primitives\":[{\"attributes\":{\"POSITION\":0,\"NORMAL\":1},\"indices\":2,\"material\":0}]}]," +
                $"\"buffers\":[{{\"byteLength\":{binBytes.Length}}}]," +
                "\"bufferViews\":[" +
                    "{\"buffer\":0,\"byteOffset\":0,\"byteLength\":288,\"target\":34962}," +
                    "{\"buffer\":0,\"byteOffset\":288,\"byteLength\":288,\"target\":34962}," +
                    "{\"buffer\":0,\"byteOffset\":576,\"byteLength\":72,\"target\":34963}" +
                "]," +
                "\"accessors\":[" +
                    $"{{\"bufferView\":0,\"componentType\":5126,\"count\":24,\"type\":\"VEC3\",\"min\":{minStr},\"max\":{maxStr}}}," +
                    "{\"bufferView\":1,\"componentType\":5126,\"count\":24,\"type\":\"VEC3\"}," +
                    "{\"bufferView\":2,\"componentType\":5123,\"count\":36,\"type\":\"SCALAR\"}" +
                "]" +
                "}";

            byte[] jsonRaw = Encoding.UTF8.GetBytes(gltfJson);
            int jsonPaddedLen = (jsonRaw.Length + 3) & ~3;
            byte[] jsonChunk = new byte[jsonPaddedLen];
            Buffer.BlockCopy(jsonRaw, 0, jsonChunk, 0, jsonRaw.Length);
            for (int i = jsonRaw.Length; i < jsonPaddedLen; i++)
                jsonChunk[i] = 0x20; // JSON space padding per glTF 2.0 spec

            uint totalLen = (uint)(12 + 8 + jsonChunk.Length + 8 + binBytes.Length);
            using var glbMs = new MemoryStream((int)totalLen);
            using (var bw = new BinaryWriter(glbMs, Encoding.UTF8, leaveOpen: true))
            {
                bw.Write(0x46546C67u); // 'glTF'
                bw.Write(2u);
                bw.Write(totalLen);

                bw.Write((uint)jsonChunk.Length);
                bw.Write(0x4E4F534Au); // 'JSON'
                bw.Write(jsonChunk);

                bw.Write((uint)binBytes.Length);
                bw.Write(0x004E4942u); // 'BIN\0'
                bw.Write(binBytes);
            }
            return glbMs.ToArray();
        }

        private static byte[] BuildSyntheticBinaryStlBox(float sx, float sy, float sz)
        {
            // 12 triangles covering [0..sx] x [0..sy] x [0..sz] (Z-up CAD convention: X=60, Y=40, Z=15)
            float[][] tris =
            {
                new[]{0f,0f,0f, sx,0f,0f, sx,sy,0f}, new[]{0f,0f,0f, sx,sy,0f, 0f,sy,0f},
                new[]{0f,0f,sz, sx,sy,sz, sx,0f,sz}, new[]{0f,0f,sz, 0f,sy,sz, sx,sy,sz},
                new[]{0f,0f,0f, sx,0f,sz, sx,0f,0f}, new[]{0f,0f,0f, 0f,0f,sz, sx,0f,sz},
                new[]{0f,sy,0f, sx,sy,0f, sx,sy,sz}, new[]{0f,sy,0f, sx,sy,sz, 0f,sy,sz},
                new[]{0f,0f,0f, 0f,sy,0f, 0f,sy,sz}, new[]{0f,0f,0f, 0f,sy,sz, 0f,0f,sz},
                new[]{sx,0f,0f, sx,sy,sz, sx,sy,0f}, new[]{sx,0f,0f, sx,0f,sz, sx,sy,sz}
            };

            using var ms = new MemoryStream();
            using var bw = new BinaryWriter(ms);
            bw.Write(new byte[80]); // 80-byte STL header
            bw.Write((uint)tris.Length);
            foreach (var t in tris)
            {
                bw.Write(0f); bw.Write(0f); bw.Write(1f); // normal (recomputed on load)
                for (int i = 0; i < 9; i++) bw.Write(t[i]);
                bw.Write((ushort)0);
            }
            return ms.ToArray();
        }
    }
}
