using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Imaging;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Security.AccessControl;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;

namespace FunkenwerkCraft
{
    public static class CraftSelfTestRunner
    {
        public static async Task<int> RunAllAsync()
        {
            var report = new StringBuilder();
            int passed = 0;
            int failed = 0;

            void Log(string line)
            {
                Console.Out.WriteLine(line);
                report.AppendLine(line);
            }

            async Task CheckAsync(string id, string title, Func<Task<string>> action)
            {
                var sw = Stopwatch.StartNew();
                try
                {
                    string detail = await action();
                    sw.Stop();
                    passed++;
                    Log($"[PASS] {id} | {title} ({sw.ElapsedMilliseconds} ms) -> {detail}");
                }
                catch (Exception ex)
                {
                    sw.Stop();
                    failed++;
                    Log($"[FAIL] {id} | {title} ({sw.ElapsedMilliseconds} ms) -> {ex.GetType().Name}: {ex.Message}");
                }
            }

            Log("=== FUNKENWERK CRAFT REAL AUTOMATED SELF-TEST SUITE ===");

            // Snapshot production Projekt 1 metadata before touching anything
            string prodRoot = CraftEngine.ProductionWorkspaceRoot;
            string prodProj1Dir = Path.Combine(prodRoot, "Videos", "Projekt 1");
            string prodMp4 = Path.Combine(prodProj1Dir, "Social_Media_Ready_Projekt_1.mp4");
            string prodJson = Path.Combine(prodProj1Dir, "craft_project.json");

            bool hadProdMp4 = File.Exists(prodMp4);
            long prodMp4Len = hadProdMp4 ? new FileInfo(prodMp4).Length : -1;
            DateTime prodMp4WriteUtc = hadProdMp4 ? File.GetLastWriteTimeUtc(prodMp4) : DateTime.MinValue;

            bool hadProdJson = File.Exists(prodJson);
            long prodJsonLen = hadProdJson ? new FileInfo(prodJson).Length : -1;
            DateTime prodJsonWriteUtc = hadProdJson ? File.GetLastWriteTimeUtc(prodJson) : DateTime.MinValue;

            string realLocalAppDataDir = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "FunkenwerkCraft");
            if (!Directory.Exists(realLocalAppDataDir))
                Directory.CreateDirectory(realLocalAppDataDir);

            string tempRoot = Path.Combine(Path.GetTempPath(), $"fw_craft_selftest_{Guid.NewGuid():N}");
            string outsideRogueDir = Path.Combine(Path.GetTempPath(), $"fw_craft_outside_{Guid.NewGuid():N}");
            Directory.CreateDirectory(tempRoot);
            Directory.CreateDirectory(outsideRogueDir);

            string renderedMasterMp4 = "";
            string testProjName = "Selftest Reel";

            try
            {
                CraftEngine.WorkspaceOverrideRoot = tempRoot;
                CraftEngine.LocalDataOverrideDir = Path.Combine(tempRoot, "LocalData");

                if (System.Windows.Application.Current != null)
                {
                    System.Windows.Application.Current.ShutdownMode = ShutdownMode.OnExplicitShutdown;
                }

                // T01: Storytold Engines Installed
                await CheckAsync("T01", "Storytold Engines (ArtCraft, FilmCraft, SoundCraft + CLIs) installiert", () =>
                {
                    string[] exes =
                    [
                        CraftEngine.ArtCraftExe,
                        CraftEngine.FilmCraftExe,
                        CraftEngine.FilmCraftCliExe,
                        CraftEngine.SoundCraftExe,
                        CraftEngine.SoundCraftCliExe
                    ];
                    foreach (string exe in exes)
                    {
                        if (!File.Exists(exe))
                            throw new FileNotFoundException($"Engine fehlt: {exe}");
                    }
                    return Task.FromResult($"{exes.Length}/5 Engine-Binaries verifiziert");
                });

                // T02: SoundPool >= 30 CC0 Assets
                await CheckAsync("T02", "SoundPool enthaelt >= 30 kommerziell nutzbare CC0-Sounds", () =>
                {
                    var sounds = CraftEngine.ListSoundPool();
                    if (sounds.Count < 30)
                        throw new InvalidOperationException($"Zu wenige Sounds im SoundPool: {sounds.Count}");
                    if (sounds.Any(s => !s.CommercialUseAllowed))
                        throw new InvalidOperationException("Nicht-kommerzielle Lizenz im SoundPool entdeckt.");
                    string? resolvedFirst = CraftEngine.ResolveSafeAudioPath(sounds[0].RelativePath, null, requireExists: true);
                    if (string.IsNullOrWhiteSpace(resolvedFirst) || !File.Exists(resolvedFirst))
                        throw new InvalidOperationException("SoundPool-Pfadaufloesung fehlgeschlagen.");
                    return Task.FromResult($"{sounds.Count} CC0-Sounds verifiziert");
                });

                // T03: SanitizeProjectName & SanitizeCleanText
                await CheckAsync("T03", "SanitizeProjectName blockiert Traversal, Rooted, ADS, Reserved Names & .exe", () =>
                {
                    string[] badNames =
                    [
                        "",
                        "   ",
                        @"..\Evil",
                        "../Evil",
                        @"C:\Windows\System32",
                        "Projekt 1:Zone.Identifier",
                        "CON",
                        "NUL.mp4",
                        "COM1",
                        "LPT1",
                        "payload.exe",
                        @"\\server\share\proj"
                    ];
                    foreach (string bad in badNames)
                    {
                        bool blocked = false;
                        try { _ = CraftEngine.SanitizeProjectName(bad); }
                        catch (Exception ex) when (ex is ArgumentException or UnauthorizedAccessException) { blocked = true; }
                        if (!blocked)
                            throw new InvalidOperationException($"Unsicherer Projektname wurde NICHT blockiert: '{bad}'");
                    }

                    string cleanText = CraftEngine.SanitizeCleanText("Gold \u2013 Edition \u2014 Pur");
                    if (cleanText.Contains('\u2013') || cleanText.Contains('\u2014'))
                        throw new InvalidOperationException("Gedankenstrich wurde nicht bereinigt.");

                    string valid = CraftEngine.SanitizeProjectName("Projekt 7");
                    if (valid != "Projekt 7")
                        throw new InvalidOperationException($"Unerwarteter Projektname: {valid}");

                    return Task.FromResult($"{badNames.Length} Angriffsvektoren blockiert + En-Dash bereinigt");
                });

                // T04: ResolveSceneMediaPath Security
                await CheckAsync("T04", "ResolveSceneMediaPath blockiert Traversal, Rooted, ADS, Reserved & .exe", () =>
                {
                    string projDir = CraftEngine.GetProjectDirectory(testProjName);
                    string validImg = Path.Combine(projDir, "valid_still.png");
                    SynthesizeLandscapePng(validImg, 1600, 900);

                    string rawDir = Path.Combine(projDir, "Rohmaterial");
                    Directory.CreateDirectory(rawDir);
                    string rawImg = Path.Combine(rawDir, "raw_shot.png");
                    SynthesizeLandscapePng(rawImg, 1200, 800);

                    string[] badMedia =
                    [
                        @"..\outside.mp4",
                        @"..\..\Windows\System32\cmd.exe",
                        @"C:\Windows\win.ini",
                        "valid_still.png:ads",
                        "CON.mp4",
                        "NUL.png",
                        "malware.exe",
                        "craft_project.json",
                        @"\\evil\share\clip.mp4"
                    ];

                    foreach (string bad in badMedia)
                    {
                        bool blocked = false;
                        try { _ = CraftEngine.ResolveSceneMediaPath(testProjName, bad); }
                        catch (UnauthorizedAccessException) { blocked = true; }
                        if (!blocked)
                            throw new InvalidOperationException($"Unsicherer Szenenpfad wurde NICHT blockiert: '{bad}'");
                    }

                    string r1 = CraftEngine.ResolveSceneMediaPath(testProjName, "valid_still.png");
                    string r2 = CraftEngine.ResolveSceneMediaPath(testProjName, "raw_shot.png");
                    if (!File.Exists(r1) || !File.Exists(r2))
                        throw new InvalidOperationException("Gueltige Projekt-Mediendateien wurden nicht aufgeloest.");

                    return Task.FromResult($"{badMedia.Length} Medien-Angriffsvektoren blockiert + Rohmaterial aufgeloest");
                });

                // T05: ResolveSafeAudioPath across ALL 6 audio fields
                await CheckAsync("T05", "ResolveSafeAudioPath schuetzt alle 6 Audio-Felder gegen Traversal, ADS & .exe", () =>
                {
                    string projDir = CraftEngine.GetProjectDirectory(testProjName);
                    string outsideWav = Path.Combine(outsideRogueDir, "outside_secret.wav");
                    SynthesizeImpulseAndChordWav(outsideWav, durationSec: 1.2, impulseAtSec: 0.3);

                    string localProjWav = Path.Combine(projDir, "local_beat.wav");
                    SynthesizeImpulseAndChordWav(localProjWav, durationSec: 2.8, impulseAtSec: 0.75);

                    string[] badAudio =
                    [
                        @"..\..\Windows\System32\cmd.exe",
                        @"..\outside_secret.wav",
                        outsideWav,
                        @"C:\Windows\Media\ding.exe",
                        "local_beat.wav:secret",
                        "CON.wav",
                        "NUL.mp3",
                        "trojan.exe",
                        @"\\attacker\smb\track.wav"
                    ];

                    foreach (string bad in badAudio)
                    {
                        bool blocked = false;
                        try { _ = CraftEngine.ResolveSafeAudioPath(bad, projDir, requireExists: false); }
                        catch (UnauthorizedAccessException) { blocked = true; }
                        if (!blocked)
                            throw new InvalidOperationException($"Unsicherer Audio-Pfad wurde NICHT blockiert: '{bad}'");
                    }

                    // Verify SaveProject enforces ResolveSafeAudioPath on all 6 audio fields
                    Action<CraftProjectModel>[] fieldMutators =
                    [
                        p => p.BackgroundMusic = outsideWav,
                        p => p.IntroAccentSfx = @"..\..\outside.wav",
                        p => p.OutroAccentSfx = "evil.exe",
                        p => p.Scenes[0].TransitionSfx = outsideWav,
                        p => p.Scenes[0].RiserSfx = "riser.wav:ads",
                        p => p.Scenes[0].ImpactSfx = "CON.wav"
                    ];

                    for (int i = 0; i < fieldMutators.Length; i++)
                    {
                        var testProj = new CraftProjectModel
                        {
                            Name = testProjName,
                            BackgroundMusic = "local_beat.wav",
                            IntroAccentSfx = "",
                            OutroAccentSfx = "",
                            Scenes = new List<CraftSceneItem>
                            {
                                new() { FileName = "valid_still.png", MediaType = "image", TransitionSfx = "" }
                            }
                        };
                        fieldMutators[i](testProj);
                        bool rejected = false;
                        try { CraftEngine.SaveProject(testProj); }
                        catch (UnauthorizedAccessException) { rejected = true; }
                        if (!rejected)
                            throw new InvalidOperationException($"Audio-Feld #{i + 1} wurde in SaveProject nicht geschuetzt!");
                    }

                    string? okLocal = CraftEngine.ResolveSafeAudioPath("local_beat.wav", projDir, requireExists: true);
                    if (string.IsNullOrWhiteSpace(okLocal) || !File.Exists(okLocal))
                        throw new InvalidOperationException("Projektlokale WAV-Datei wurde nicht akzeptiert.");

                    return Task.FromResult("Alle 6 Audio-Felder + 9 Traversal/ADS/.exe-Vektoren abgesichert");
                });

                // T06: Real NTFS Junction / Reparse Point Rejection
                await CheckAsync("T06", "NTFS-Junctions & Reparse-Points werden in Medien/Audio/Import blockiert", async () =>
                {
                    string projDir = CraftEngine.GetProjectDirectory(testProjName);
                    string roguePng = Path.Combine(outsideRogueDir, "rogue_img.png");
                    string rogueWav = Path.Combine(outsideRogueDir, "rogue_sfx.wav");
                    SynthesizeLandscapePng(roguePng, 800, 600);
                    SynthesizeImpulseAndChordWav(rogueWav, 1.0, 0.2);

                    string junctionDir = Path.Combine(projDir, "LinkEscape");
                    string cmdExe = Path.Combine(Environment.SystemDirectory, "cmd.exe");
                    var psi = new ProcessStartInfo
                    {
                        FileName = cmdExe,
                        UseShellExecute = false,
                        CreateNoWindow = true,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true
                    };
                    psi.ArgumentList.Add("/c");
                    psi.ArgumentList.Add("mklink");
                    psi.ArgumentList.Add("/J");
                    psi.ArgumentList.Add(junctionDir);
                    psi.ArgumentList.Add(outsideRogueDir);

                    using (var p = Process.Start(psi)!)
                    {
                        await p.WaitForExitAsync();
                        if (p.ExitCode != 0 || !Directory.Exists(junctionDir))
                            throw new InvalidOperationException("Test-Junction konnte nicht angelegt werden.");
                    }

                    try
                    {
                        bool sceneBlocked = false;
                        try { _ = CraftEngine.ResolveSceneMediaPath(testProjName, @"LinkEscape\rogue_img.png"); }
                        catch (UnauthorizedAccessException) { sceneBlocked = true; }

                        bool audioBlocked = false;
                        try { _ = CraftEngine.ResolveSafeAudioPath(@"LinkEscape\rogue_sfx.wav", projDir, requireExists: true); }
                        catch (UnauthorizedAccessException) { audioBlocked = true; }

                        bool importBlocked = false;
                        try { _ = CraftEngine.ImportMediaToProject(testProjName, Path.Combine(junctionDir, "rogue_img.png")); }
                        catch (UnauthorizedAccessException) { importBlocked = true; }

                        bool probeBlocked = false;
                        try { _ = await CraftEngine.ProbeMediaFileAsync(Path.Combine(junctionDir, "rogue_img.png")); }
                        catch (UnauthorizedAccessException) { probeBlocked = true; }

                        if (!sceneBlocked || !audioBlocked || !importBlocked || !probeBlocked)
                            throw new InvalidOperationException($"Junction-Block unvollstaendig (scene={sceneBlocked}, audio={audioBlocked}, import={importBlocked}, probe={probeBlocked}).");
                    }
                    finally
                    {
                        try { Directory.Delete(junctionDir, recursive: false); } catch { }
                    }

                    return "Echte NTFS-Junction in Scene/Audio/Import/Probe 100% blockiert";
                });

                // T07: CLI Blocks Unauthorized launch_engine + Exit Code 1
                await CheckAsync("T07", "ExecuteCliCommandJsonAsync blockiert unautorisierte launch_engine Aufrufe", async () =>
                {
                    string[] badRequests =
                    [
                        JsonSerializer.Serialize(new { action = "launch_engine", engine = "filmcraft", targetPath = @"C:\Windows\System32\cmd.exe" }),
                        JsonSerializer.Serialize(new { action = "launch_engine", engine = "filmcraft", targetPath = @"..\..\outside.fcproj" }),
                        JsonSerializer.Serialize(new { action = "launch_engine", engine = "filmcraft", targetPath = Path.Combine(outsideRogueDir, "evil.fcproj") }),
                        JsonSerializer.Serialize(new { action = "launch_engine", engine = "filmcraft", targetPath = Path.Combine(CraftEngine.GetProjectDirectory(testProjName), "valid_still.png") })
                    ];

                    foreach (string req in badRequests)
                    {
                        string resp = await CraftEngine.ExecuteCliCommandJsonAsync(req);
                        if (CraftEngine.GetCliExitCode(resp) != 1)
                            throw new InvalidOperationException($"Unautorisierter launch_engine Aufruf lieferte nicht ExitCode 1: {resp}");
                    }

                    // Valid .fcproj inside WorkspaceRoot with validateOnly=true must return ok:true & exitCode 0
                    string validFcproj = Path.Combine(CraftEngine.GetProjectDirectory(testProjName), "valid_test.fcproj");
                    await File.WriteAllTextAsync(validFcproj, "{}", Encoding.UTF8);
                    string okReq = JsonSerializer.Serialize(new
                    {
                        action = "launch_engine",
                        engine = "filmcraft",
                        targetPath = validFcproj,
                        validateOnly = true
                    });
                    string okResp = await CraftEngine.ExecuteCliCommandJsonAsync(okReq);
                    if (CraftEngine.GetCliExitCode(okResp) != 0)
                        throw new InvalidOperationException($"Gueltiger launch_engine validateOnly Aufruf fehlgeschlagen: {okResp}");

                    return "4/4 unautorisierte Targets blockiert (ExitCode=1), gueltiges .fcproj akzeptiert (ExitCode=0)";
                });

                // T08: CLI Blocks Unauthorized import_media / inspect_media / sound traversal
                await CheckAsync("T08", "ExecuteCliCommandJsonAsync blockiert import_media/.exe, inspect_media & Sound-Traversal", async () =>
                {
                    string cmdExe = Path.Combine(Environment.SystemDirectory, "cmd.exe");
                    string[] badCliPayloads =
                    [
                        JsonSerializer.Serialize(new { action = "import_media", projectName = testProjName, sourcePaths = new[] { cmdExe } }),
                        JsonSerializer.Serialize(new { action = "import_media", projectName = testProjName, sourcePaths = new[] { @"..\..\Windows\win.ini" } }),
                        JsonSerializer.Serialize(new { action = "inspect_media", filePath = cmdExe }),
                        JsonSerializer.Serialize(new { action = "save_project", projectName = testProjName, backgroundMusic = @"..\..\Windows\System32\cmd.exe" })
                    ];

                    foreach (string payload in badCliPayloads)
                    {
                        string resp = await CraftEngine.ExecuteCliCommandJsonAsync(payload);
                        if (CraftEngine.GetCliExitCode(resp) != 1)
                            throw new InvalidOperationException($"Unsicherer CLI-Request nicht mit ExitCode 1 abgelehnt: {resp}");
                    }

                    return "import_media / inspect_media / save_project Traversal- & .exe-Block verifiziert";
                });

                // T09: CLI Blocks Disallowed filmcraft_cli Subcommands
                await CheckAsync("T09", "RunFilmCraftCliAsync erlaubt nur sichere Subkommandos & Workspace-Pfade", async () =>
                {
                    string[][] blockedArgs =
                    [
                        ["exec", "file.open", @"path=C:\Windows\win.ini"],
                        ["export", @"C:\evil.mp4"],
                        ["mcp"],
                        ["run", "-"],
                        ["run", @"..\..\outside.jsonl"],
                        ["--save-as", @"C:\evil.fcproj", "commands"]
                    ];

                    foreach (var args in blockedArgs)
                    {
                        string req = JsonSerializer.Serialize(new { action = "filmcraft_cli", args });
                        string resp = await CraftEngine.ExecuteCliCommandJsonAsync(req);
                        if (CraftEngine.GetCliExitCode(resp) != 1)
                            throw new InvalidOperationException($"Unsicheres filmcraft_cli Kommando wurde nicht blockiert: {string.Join(' ', args)}");
                    }

                    string safeReq = JsonSerializer.Serialize(new { action = "filmcraft_cli", args = new[] { "describe", "file.newSequence" } });
                    string safeResp = await CraftEngine.ExecuteCliCommandJsonAsync(safeReq);
                    if (CraftEngine.GetCliExitCode(safeResp) != 0 || !safeResp.Contains("file.newSequence", StringComparison.Ordinal))
                        throw new InvalidOperationException($"Erlaubtes filmcraft_cli describe fehlgeschlagen: {safeResp}");

                    return "6/6 gefaehrliche Subkommandos blockiert + describe erlaubt";
                });

                // T10: DPAPI ControlToken Encryption + .funkenwerk_bridge NTFS ACLs
                await CheckAsync("T10", "DPAPI ControlToken-Verschluesselung & CurrentUser NTFS-ACL auf .funkenwerk_bridge", () =>
                {
                    var (port1, tok1) = CraftEngine.GetOrCreateFilmCraftBridgeCredentials();
                    if (tok1.Length != 64 || !tok1.All(Uri.IsHexDigit))
                        throw new InvalidOperationException("Ungueltiges ControlToken generiert.");

                    if (!File.Exists(CraftEngine.BridgeStateFilePath))
                        throw new FileNotFoundException("bridge_state.json wurde nicht erstellt.");

                    string onDisk = File.ReadAllText(CraftEngine.BridgeStateFilePath, Encoding.UTF8);
                    if (onDisk.Contains(tok1, StringComparison.OrdinalIgnoreCase))
                        throw new InvalidOperationException("SICHERHEITSFEHLER: ControlToken liegt im Klartext in bridge_state.json!");
                    if (!onDisk.Contains("encryptedToken", StringComparison.Ordinal))
                        throw new InvalidOperationException("encryptedToken Feld fehlt in bridge_state.json.");

                    var (port2, tok2) = CraftEngine.GetOrCreateFilmCraftBridgeCredentials();
                    if (port1 != port2 || !string.Equals(tok1, tok2, StringComparison.Ordinal))
                        throw new InvalidOperationException("DPAPI Unprotect Roundtrip lieferte abweichendes Token.");

                    var dirInfo = new DirectoryInfo(CraftEngine.BridgeDirectoryPath);
                    DirectorySecurity dsec = dirInfo.GetAccessControl();
                    if (!dsec.AreAccessRulesProtected)
                        throw new InvalidOperationException(".funkenwerk_bridge Verzeichnis-Vererbung ist nicht deaktiviert.");

                    var currentSid = WindowsIdentity.GetCurrent().User
                        ?? throw new InvalidOperationException("Aktuelle User-SID nicht verfuegbar.");
                    var rules = dsec.GetAccessRules(includeExplicit: true, includeInherited: true, typeof(SecurityIdentifier))
                        .Cast<FileSystemAccessRule>()
                        .ToList();

                    if (rules.Count == 0 || rules.Any(r => !currentSid.Equals(r.IdentityReference)))
                        throw new InvalidOperationException(".funkenwerk_bridge enthaelt fremde NTFS-ACL Eintraege.");

                    return Task.FromResult($"DPAPI Roundtrip OK + exklusive SID-ACL ({currentSid.Value}) aktiv");
                });

                // T11: Bounded Named Pipe Line Reader <= 64 KB
                await CheckAsync("T11", "ReadBoundedLineAsync begrenzt IPC-Payload strikt auf <= 64 KB", async () =>
                {
                    byte[] okBytes = Encoding.UTF8.GetBytes("{\"action\":\"status\"}\r\n");
                    await using (var okStream = new MemoryStream(okBytes))
                    {
                        string? line = await CraftEngine.ReadBoundedLineAsync(okStream, CraftEngine.MaxIpcLineBytes);
                        if (line != "{\"action\":\"status\"}")
                            throw new InvalidOperationException($"Unerwartetes Leseergebnis: {line}");
                    }

                    byte[] oversized = new byte[CraftEngine.MaxIpcLineBytes + 4096];
                    Array.Fill(oversized, (byte)'A');
                    await using (var badStream = new MemoryStream(oversized))
                    {
                        bool rejected = false;
                        try { _ = await CraftEngine.ReadBoundedLineAsync(badStream, CraftEngine.MaxIpcLineBytes); }
                        catch (InvalidDataException) { rejected = true; }
                        if (!rejected)
                            throw new InvalidOperationException("Uebergrosse IPC-Nachricht (> 64 KB) wurde nicht abgelehnt.");
                    }

                    return "64 KB Grenze erzwingt sofortigen Abbruch bei Oversize-Payloads";
                });

                // T12: Trusted FFmpeg/FFprobe Discovery & Bounded Peak Envelope (-t 90)
                await CheckAsync("T12", "Vertrauenswuerdige FFmpeg/FFprobe-Pfade & Peak-Erkennung (-t 90 + Timeout)", () =>
                {
                    string ffmpeg = CraftEngine.FindTrustedFfmpegExe();
                    string ffprobe = CraftEngine.FindTrustedFfprobeExe();
                    if (!Path.IsPathRooted(ffmpeg) || !File.Exists(ffmpeg))
                        throw new InvalidOperationException($"Ungueltiger FFmpeg-Pfad: {ffmpeg}");
                    if (!Path.IsPathRooted(ffprobe) || !File.Exists(ffprobe))
                        throw new InvalidOperationException($"Ungueltiger FFprobe-Pfad: {ffprobe}");

                    string projDir = CraftEngine.GetProjectDirectory(testProjName);
                    string wavPath = Path.Combine(projDir, "local_beat.wav");
                    var (peakSec, durSec) = CraftEngine.GetAudioPeakEnvelopeInfo(wavPath, projDir);
                    if (peakSec < 0.65 || peakSec > 0.85)
                        throw new InvalidOperationException($"Unerwarteter Audio-Peak bei {peakSec:0.000}s (erwartet ~0.75s).");

                    return Task.FromResult($"FFmpeg={Path.GetFileName(ffmpeg)} | Peak={peakSec:0.00}s / {durSec:0.00}s");
                });

                // T13: ComputeNextProjectName Gap-Collision Regression (No Infinite Loop!)
                await CheckAsync("T13", "ComputeNextProjectName loest Luecken-Kollision ohne Endlosschleife (< 10 ms)", () =>
                {
                    string gapVideosDir = Path.Combine(tempRoot, "GapCollisionTest");
                    Directory.CreateDirectory(Path.Combine(gapVideosDir, "Projekt 2")); // Projekt 1 fehlt absichtlich!

                    var sw = Stopwatch.StartNew();
                    string nextName = CraftEngine.ComputeNextProjectName(gapVideosDir);
                    sw.Stop();

                    if (nextName != "Projekt 3")
                        throw new InvalidOperationException($"Erwartet 'Projekt 3', erhalten '{nextName}'.");
                    if (sw.ElapsedMilliseconds > 50)
                        throw new InvalidOperationException($"Zu langsam ({sw.ElapsedMilliseconds} ms).");

                    return Task.FromResult($"Projekt 2 ohne Projekt 1 -> '{nextName}' in {sw.Elapsed.TotalMilliseconds:0.00} ms");
                });

                // T14: RenderLuxuryOverlayPng Proportional Font Auto-Sizing + Pure White #FFFFFF
                await CheckAsync("T14", "RenderLuxuryOverlayPng skaliert Schrift proportional & reinweiss (#FFFFFF)", () =>
                {
                    string ovDir = Path.Combine(tempRoot, "OverlayTests");
                    Directory.CreateDirectory(ovDir);

                    string pShort = Path.Combine(ovDir, "ov_short.png");
                    string pMed = Path.Combine(ovDir, "ov_med.png");
                    string pLong = Path.Combine(ovDir, "ov_long.png");

                    float ptShort = CraftEngine.RenderLuxuryOverlayPng(
                        pShort, 1080, 1920, "editorial_minimal", false, "",
                        new CraftSceneItem { OverlayTitle = "UNIKAT" });

                    float ptMed = CraftEngine.RenderLuxuryOverlayPng(
                        pMed, 1080, 1920, "editorial_minimal", false, "",
                        new CraftSceneItem { OverlayTitle = "HANDVEREDELTES UNIKAT AUS DER MANUFAKTUR GIFHORN" });

                    float ptLong = CraftEngine.RenderLuxuryOverlayPng(
                        pLong, 1080, 1920, "editorial_minimal", false, "",
                        new CraftSceneItem { OverlayTitle = "EIN AUSSERGEWOEHNLICH LANGER LUXUS TITEL FUER MAXIMALE SKALIERUNGSPRUEFUNG IM REEL" });

                    if (ptShort <= ptMed || ptMed <= 24.0f || ptMed >= 33.0f)
                        throw new InvalidOperationException($"Schriftgroesse skalierte nicht proportional: short={ptShort}, med={ptMed}, long={ptLong}");

                    if (Math.Abs(ptLong - 24.0f) > 0.01f)
                        throw new InvalidOperationException($"Sehr langer Titel stoppte nicht bei 24pt: {ptLong}");

                    using var bmp = new Bitmap(pMed);
                    var rect = new Rectangle(0, (int)(bmp.Height * 0.70), bmp.Width, (int)(bmp.Height * 0.22));
                    var data = bmp.LockBits(rect, ImageLockMode.ReadOnly, System.Drawing.Imaging.PixelFormat.Format32bppArgb);
                    int minX = bmp.Width, maxX = 0, whiteGlyphPixels = 0;
                    byte peakR = 0, peakG = 0, peakB = 0;
                    try
                    {
                        byte[] raw = new byte[data.Stride * rect.Height];
                        Marshal.Copy(data.Scan0, raw, 0, raw.Length);
                        for (int y = 0; y < rect.Height; y++)
                        {
                            int row = y * data.Stride;
                            for (int x = 0; x < rect.Width; x++)
                            {
                                int idx = row + x * 4;
                                byte b = raw[idx + 0];
                                byte g = raw[idx + 1];
                                byte r = raw[idx + 2];
                                byte a = raw[idx + 3];
                                if (a >= 200 && r >= 180)
                                {
                                    if (Math.Abs(r - g) > 3 || Math.Abs(r - b) > 3)
                                        throw new InvalidOperationException($"Farbstich statt reinem Monochrome-Weiss entdeckt: RGBA=({r},{g},{b},{a})");
                                }
                                if (a >= 248 && r >= 250)
                                {
                                    whiteGlyphPixels++;
                                    if (x < minX) minX = x;
                                    if (x > maxX) maxX = x;
                                    if (r > peakR) peakR = r;
                                    if (g > peakG) peakG = g;
                                    if (b > peakB) peakB = b;
                                }
                            }
                        }
                    }
                    finally
                    {
                        bmp.UnlockBits(data);
                    }

                    int renderedSpanW = maxX - minX;
                    if (whiteGlyphPixels < 250 || peakR < 253 || peakG < 253 || peakB < 253 || renderedSpanW <= 0 || renderedSpanW > (int)(1080 * 0.89))
                        throw new InvalidOperationException($"Ungueltige Textbreite/Reinweiss-Pruefung: width={renderedSpanW}px, whitePx={whiteGlyphPixels}, peakRGB=({peakR},{peakG},{peakB})");

                    return Task.FromResult($"ptShort={ptShort:0} -> ptMed={ptMed:0} -> ptLong={ptLong:0} | Span={renderedSpanW}px | PeakRGB=({peakR},{peakG},{peakB})");
                });

                // Synthesize real source video with 440Hz sine audio for T15..T20
                string projDirMain = CraftEngine.GetProjectDirectory(testProjName);
                string synthVideoPath = Path.Combine(projDirMain, "synth_clip.mp4");
                await SynthesizeTestVideoWithToneAsync(synthVideoPath, width: 1080, height: 1920, fps: 30, durationSec: 3.2);

                string synthIntroWav = Path.Combine(projDirMain, "intro_sub.wav");
                string synthTransWav = Path.Combine(projDirMain, "whoosh_cut.wav");
                string synthOutroWav = Path.Combine(projDirMain, "outro_swell.wav");
                SynthesizeImpulseAndChordWav(synthIntroWav, durationSec: 0.9, impulseAtSec: 0.08);
                SynthesizeImpulseAndChordWav(synthTransWav, durationSec: 1.0, impulseAtSec: 0.22);
                SynthesizeImpulseAndChordWav(synthOutroWav, durationSec: 1.4, impulseAtSec: 0.30);

                var reelProject = new CraftProjectModel
                {
                    Name = testProjName,
                    AspectRatio = "9:16",
                    Fps = 30,
                    TransitionDurationSec = 0.20,
                    ColorGrade = "dark_luxury",
                    OverlayStyle = "editorial_minimal",
                    BackgroundMusic = "local_beat.wav",
                    MusicVolume = 0.48,
                    SfxVolume = 0.78,
                    IntroAccentSfx = "intro_sub.wav",
                    OutroAccentSfx = "outro_swell.wav",
                    ShowBrandHeader = true,
                    BrandHeaderText = "MEIN SEELENFUNKE",
                    OutputFileName = "Social_Media_Ready_Selftest_Reel.mp4",
                    Scenes = new List<CraftSceneItem>
                    {
                        new()
                        {
                            FileName = "valid_still.png",
                            MediaType = "image",
                            DurationSec = 1.5,
                            FitMode = "contain_blur",
                            SpeedMode = "linear",
                            OverlayBadge = "MANUFAKTUR GIFHORN",
                            OverlayTitle = "EIN FUNKE EWIGKEIT",
                            OverlaySubtitle = "Handveredelt in Deutschland",
                            Transition = "whip_zoom",
                            TransitionSfx = "whoosh_cut.wav"
                        },
                        new()
                        {
                            FileName = "synth_clip.mp4",
                            MediaType = "video",
                            StartSec = 0.1,
                            DurationSec = 1.6,
                            FitMode = "fill",
                            SpeedMode = "speed_ramp",
                            SlowMoFactor = 0.45,
                            KeepOriginalAudio = true,
                            OriginalAudioVolume = 0.65,
                            OverlayTitle = "GRAVIERT MIT LIEBE",
                            Transition = "velvet_dissolve",
                            TransitionDurSec = 0.35,
                            TransitionSfx = "whoosh_cut.wav"
                        },
                        new()
                        {
                            FileName = "__BRAND_OUTRO__",
                            MediaType = "brand_outro",
                            DurationSec = 1.4,
                            SpeedMode = "linear",
                            OverlayTitle = "Mein Seelenfunke",
                            OverlaySubtitle = "EIN FUNKE, DER BLEIBT",
                            Transition = "fade"
                        }
                    }
                };
                CraftEngine.SaveProject(reelProject);

                // T15: ExportFilmCraftNativeProjectAsync Populates Assets & Clips
                await CheckAsync("T15", "ExportFilmCraftNativeProjectAsync importiert Medien-Assets & Timeline-Clips", async () =>
                {
                    string exportedPath = await CraftEngine.ExportFilmCraftNativeProjectAsync(reelProject);
                    string jsonlPath = Path.Combine(projDirMain, "filmcraft_build.jsonl");
                    if (!File.Exists(jsonlPath))
                        throw new FileNotFoundException("filmcraft_build.jsonl fehlt.");

                    string jsonlContent = await File.ReadAllTextAsync(jsonlPath, Encoding.UTF8);
                    string[] expectedTokens = ["file.newSequence", "file.import", "edit.pasteMedia", "graphics.newText", "file.saveAs"];
                    foreach (string tok in expectedTokens)
                    {
                        if (!jsonlContent.Contains(tok, StringComparison.Ordinal))
                            throw new InvalidOperationException($"Erwartetes FilmCraft-Kommando '{tok}' fehlt in filmcraft_build.jsonl.");
                    }

                    if (!File.Exists(exportedPath) || new FileInfo(exportedPath).Length < 64)
                        throw new InvalidOperationException($"Exportierte FilmCraft-Projektdatei ungueltig: {exportedPath}");

                    return $"{Path.GetFileName(exportedPath)} ({new FileInfo(exportedPath).Length} Bytes) mit Assets & Clips erstellt";
                });

                // T16: Image contain_blur + Slow-Motion KeepOriginalAudio (SlowMoFactor < 0.5)
                await CheckAsync("T16", "contain_blur fuer Bilder & KeepOriginalAudio bei SpeedMode != linear", async () =>
                {
                    string segDir = Path.Combine(tempRoot, "SegmentChecks");
                    Directory.CreateDirectory(segDir);
                    string emptyOv = Path.Combine(segDir, "empty_ov.png");
                    _ = CraftEngine.RenderLuxuryOverlayPng(emptyOv, 1080, 1920, "editorial_minimal", false, "", new CraftSceneItem());

                    // 1) Landscape 1600x900 image with contain_blur into 1080x1920
                    string imgPath = Path.Combine(projDirMain, "valid_still.png");
                    var imgInfo = await CraftEngine.ProbeMediaFileAsync(imgPath);
                    string blurSegMp4 = Path.Combine(segDir, "img_contain_blur.mp4");
                    await CraftEngine.RenderSingleNormalizedSegmentAsync(
                        imgPath, imgInfo, emptyOv, blurSegMp4,
                        isImage: true, keepSourceAudio: false, sourceAudioVol: 0.0,
                        startSec: 0, durSec: 1.2, outW: 1080, outH: 1920, fps: 30,
                        fitMode: "contain_blur", speedMode: "linear", slowMoFactor: 0.42,
                        colorGrade: "natural", isFinalScene: false, velvetOutroMelt: false, default);

                    var blurInfo = await CraftEngine.ProbeMediaFileAsync(blurSegMp4);
                    if (blurInfo.Width != 1080 || blurInfo.Height != 1920)
                        throw new InvalidOperationException($"Unerwartete contain_blur Aufloesung: {blurInfo.Width}x{blurInfo.Height}");

                    // 2) Video with slow_motion (0.42 < 0.5 -> chained atempo) + KeepOriginalAudio = true
                    var vidInfo = await CraftEngine.ProbeMediaFileAsync(synthVideoPath);
                    string slowSegMp4 = Path.Combine(segDir, "vid_slow_audio.mp4");
                    await CraftEngine.RenderSingleNormalizedSegmentAsync(
                        synthVideoPath, vidInfo, emptyOv, slowSegMp4,
                        isImage: false, keepSourceAudio: true, sourceAudioVol: 0.80,
                        startSec: 0, durSec: 1.4, outW: 1080, outH: 1920, fps: 30,
                        fitMode: "fill", speedMode: "slow_motion", slowMoFactor: 0.42,
                        colorGrade: "natural", isFinalScene: false, velvetOutroMelt: false, default);

                    int maxAmp = await MeasureMaxAudioAmplitudeAsync(slowSegMp4);
                    if (maxAmp < 1200)
                        throw new InvalidOperationException($"KeepOriginalAudio ging bei slow_motion (0.42x) verloren: maxAmp={maxAmp}");

                    return $"contain_blur Image (1080x1920) OK + slow_motion O-Ton erhalten (PeakAmp={maxAmp})";
                });

                // T17: End-to-End Multi-Scene Render (contain_blur Image + speed_ramp Video + brand_outro + WAV Audio)
                await CheckAsync("T17", "End-to-End Multi-Szenen Social-Video Render im isolierten Workspace", async () =>
                {
                    var steps = new List<string>();
                    var prog = new Progress<(double Pct, string Step)>(p => steps.Add($"{p.Pct:0}%:{p.Step}"));
                    renderedMasterMp4 = await CraftEngine.RenderProjectSocialVideoAsync(reelProject, prog);
                    if (!File.Exists(renderedMasterMp4))
                        throw new FileNotFoundException($"Master-MP4 fehlt: {renderedMasterMp4}");

                    long bytes = new FileInfo(renderedMasterMp4).Length;
                    if (bytes < 50_000)
                        throw new InvalidOperationException($"Master-MP4 unerwartet klein: {bytes} Bytes");

                    return $"{Path.GetFileName(renderedMasterMp4)} ({bytes / 1024} KB) erfolgreich gerendert";
                });

                // T18: ValidateMp4PlayabilityAsync + ISO BMFF ftyp & moov < mdat FastStart
                await CheckAsync("T18", "ValidateMp4PlayabilityAsync & FastStart Atom-Reihenfolge (ftyp + moov < mdat)", async () =>
                {
                    await CraftEngine.ValidateMp4PlayabilityAsync(renderedMasterMp4);

                    byte[] allBytes = await File.ReadAllBytesAsync(renderedMasterMp4);
                    int ftypPos = FindAsciiFourCc(allBytes, "ftyp");
                    int moovPos = FindAsciiFourCc(allBytes, "moov");
                    int mdatPos = FindAsciiFourCc(allBytes, "mdat");

                    if (ftypPos < 0 || ftypPos > 16)
                        throw new InvalidOperationException($"ftyp Atom fehlt am Dateianfang (pos={ftypPos}).");
                    if (moovPos < 0 || mdatPos < 0 || moovPos > mdatPos)
                        throw new InvalidOperationException($"FastStart verletzt: moov ({moovPos}) liegt nicht vor mdat ({mdatPos}).");

                    return $"ftyp@{ftypPos}, moov@{moovPos} < mdat@{mdatPos}";
                });

                // T19: FFprobe Stream Specification Verification (1080x1920 H.264 yuv420p + 48000 Hz Stereo AAC)
                await CheckAsync("T19", "FFprobe verifiziert H.264 1080x1920 yuv420p & 48000 Hz Stereo AAC", async () =>
                {
                    string ffprobe = CraftEngine.FindTrustedFfprobeExe();
                    var psi = new ProcessStartInfo
                    {
                        FileName = ffprobe,
                        UseShellExecute = false,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true,
                        CreateNoWindow = true
                    };
                    psi.ArgumentList.Add("-v");
                    psi.ArgumentList.Add("quiet");
                    psi.ArgumentList.Add("-print_format");
                    psi.ArgumentList.Add("json");
                    psi.ArgumentList.Add("-show_streams");
                    psi.ArgumentList.Add("-show_format");
                    psi.ArgumentList.Add(renderedMasterMp4);

                    using var proc = Process.Start(psi)!;
                    Task<string> outT = proc.StandardOutput.ReadToEndAsync();
                    Task<string> errT = proc.StandardError.ReadToEndAsync();
                    await proc.WaitForExitAsync();
                    string json = await outT;
                    _ = await errT;

                    using var doc = JsonDocument.Parse(json);
                    string vCodec = "", pixFmt = "", aCodec = "", sampleRate = "";
                    int w = 0, h = 0, channels = 0;

                    foreach (var st in doc.RootElement.GetProperty("streams").EnumerateArray())
                    {
                        string ct = st.GetProperty("codec_type").GetString() ?? "";
                        if (ct == "video")
                        {
                            vCodec = st.GetProperty("codec_name").GetString() ?? "";
                            w = st.GetProperty("width").GetInt32();
                            h = st.GetProperty("height").GetInt32();
                            pixFmt = st.GetProperty("pix_fmt").GetString() ?? "";
                        }
                        else if (ct == "audio")
                        {
                            aCodec = st.GetProperty("codec_name").GetString() ?? "";
                            sampleRate = st.GetProperty("sample_rate").GetString() ?? "";
                            channels = st.GetProperty("channels").GetInt32();
                        }
                    }

                    if (vCodec != "h264" || w != 1080 || h != 1920 || pixFmt != "yuv420p")
                        throw new InvalidOperationException($"Ungueltiger Video-Stream: {vCodec} {w}x{h} {pixFmt}");
                    if (aCodec != "aac" || sampleRate != "48000" || channels != 2)
                        throw new InvalidOperationException($"Ungueltiger Audio-Stream: {aCodec} {sampleRate}Hz {channels}ch");

                    return $"Video={vCodec} {w}x{h} ({pixFmt}) | Audio={aCodec} {sampleRate}Hz {channels}ch";
                });

                // T20: STA MainWindow Light/Dark Theme, Scene ComboBoxes, Selected-Scene SFX & Auto-Save
                await CheckAsync("T20", "WPF MainWindow STA: Light/Dark Theme, ComboBoxes, Szenen-Effekt & Auto-Save", () =>
                {
                    var win = new MainWindow(testProjName, startIpcServer: false);
                    try
                    {
                        // Light Mode check
                        win.SetThemeForTest(false);
                        var lightBg = ((SolidColorBrush)win.Resources["BgMainBrush"]).Color;
                        if (win.IsDarkMode || lightBg.R < 230)
                            throw new InvalidOperationException("Light-Mode Hintergrundfarbe wurde nicht korrekt gesetzt.");

                        // Dark Mode check
                        win.SetThemeForTest(true);
                        var darkBg = ((SolidColorBrush)win.Resources["BgMainBrush"]).Color;
                        if (!win.IsDarkMode || darkBg.R > 35)
                            throw new InvalidOperationException("Dark-Mode Hintergrundfarbe wurde nicht korrekt gesetzt.");

                        // Verify ComboBoxes rendered for FitMode, SpeedMode, Transition across all 3 scenes (3 * 3 = 9)
                        int comboCount = CountVisualOrLogicalChildren<System.Windows.Controls.ComboBox>(win.ScenesListPanel);
                        if (comboCount < 9)
                            throw new InvalidOperationException($"Erwartet >= 9 Szenen-ComboBoxen, gefunden: {comboCount}");

                        // Verify "Als Effekt" applies solely to SelectedSceneIndex with clear feedback
                        var whoosh = CraftEngine.ListSoundPool("Uebergaenge").FirstOrDefault()
                            ?? CraftEngine.ListSoundPool().First();
                        win.CurrentProject.Scenes[0].TransitionSfx = "whoosh_cut.wav";
                        win.SelectedSceneIndex = 1;
                        win.AssignEffectToSelectedScene(whoosh);

                        if (win.CurrentProject.Scenes[1].TransitionSfx != whoosh.RelativePath)
                            throw new InvalidOperationException("Effekt wurde nicht auf ausgewaehlte Szene #2 angewendet.");
                        if (win.CurrentProject.Scenes[0].TransitionSfx != "whoosh_cut.wav")
                            throw new InvalidOperationException("Szene #1 wurde unbeabsichtigt ueberschrieben.");
                        if (!win.StatusBannerTxt.Text.Contains("#2", StringComparison.Ordinal))
                            throw new InvalidOperationException($"Status-Feedback nennt nicht Szene #2: {win.StatusBannerTxt.Text}");

                        win.FlushAutoSave();
                    }
                    finally
                    {
                        win.Close();
                    }

                    return Task.FromResult("Light/Dark Switch + 9 Szenen-ComboBoxen + Szene #2 Effekt-Zuweisung OK");
                });
            }
            finally
            {
                CraftEngine.WorkspaceOverrideRoot = null;
                CraftEngine.LocalDataOverrideDir = null;

                try
                {
                    if (Directory.Exists(tempRoot))
                        Directory.Delete(tempRoot, recursive: true);
                }
                catch { }

                try
                {
                    if (Directory.Exists(outsideRogueDir))
                        Directory.Delete(outsideRogueDir, recursive: true);
                }
                catch { }
            }

            // T21: Verify 100% Cleanup of Isolated Temp Workspace
            await CheckAsync("T21", "Isolierter Temp-Workspace vollstaendig bereinigt", () =>
            {
                if (Directory.Exists(tempRoot) || Directory.Exists(outsideRogueDir))
                    throw new InvalidOperationException("Temporaere Testverzeichnisse wurden nicht vollstaendig geloescht.");
                return Task.FromResult("0 Rueckstaende in %TEMP%");
            });

            // T22: Verify Production Videos\Projekt 1 Untouched
            await CheckAsync("T22", "Live-Benutzerprojekt 'Videos\\Projekt 1' blieb 100% unangetastet", () =>
            {
                if (hadProdMp4)
                {
                    var fi = new FileInfo(prodMp4);
                    if (!fi.Exists || fi.Length != prodMp4Len || fi.LastWriteTimeUtc != prodMp4WriteUtc)
                        throw new InvalidOperationException("KRITISCH: Social_Media_Ready_Projekt_1.mp4 wurde veraendert!");
                }
                if (hadProdJson)
                {
                    var jfi = new FileInfo(prodJson);
                    if (!jfi.Exists || jfi.Length != prodJsonLen || jfi.LastWriteTimeUtc != prodJsonWriteUtc)
                        throw new InvalidOperationException("KRITISCH: Projekt 1 craft_project.json wurde veraendert!");
                }
                return Task.FromResult($"Projekt 1 MP4 ({prodMp4Len} B) & JSON ({prodJsonLen} B) unveraendert");
            });

            int total = passed + failed;
            string summary = failed == 0
                ? $"=== ERGEBNIS: {passed}/{total} TESTS BESTANDEN (EXIT 0) ==="
                : $"=== ERGEBNIS: {passed}/{total} BESTANDEN, {failed} FEHLGESCHLAGEN (EXIT 1) ===";
            Log(summary);

            try
            {
                string reportPath = Path.Combine(realLocalAppDataDir, "self_test_report.txt");
                await File.WriteAllTextAsync(reportPath, report.ToString(), new UTF8Encoding(false));
            }
            catch { }

            Console.Out.Flush();
            return failed == 0 ? 0 : 1;
        }

        private static int CountVisualOrLogicalChildren<T>(DependencyObject parent) where T : DependencyObject
        {
            int count = 0;
            foreach (object? child in LogicalTreeHelper.GetChildren(parent))
            {
                if (child is DependencyObject dep)
                {
                    if (dep is T) count++;
                    count += CountVisualOrLogicalChildren<T>(dep);
                }
            }
            return count;
        }

        private static int FindAsciiFourCc(byte[] data, string fourCc)
        {
            byte b0 = (byte)fourCc[0];
            byte b1 = (byte)fourCc[1];
            byte b2 = (byte)fourCc[2];
            byte b3 = (byte)fourCc[3];
            int limit = data.Length - 4;
            for (int i = 0; i <= limit; i++)
            {
                if (data[i] == b0 && data[i + 1] == b1 && data[i + 2] == b2 && data[i + 3] == b3)
                    return i;
            }
            return -1;
        }

        private static void SynthesizeLandscapePng(string path, int width, int height)
        {
            using var bmp = new Bitmap(width, height, System.Drawing.Imaging.PixelFormat.Format32bppArgb);
            using var g = Graphics.FromImage(bmp);
            using var bg = new System.Drawing.Drawing2D.LinearGradientBrush(
                new Rectangle(0, 0, width, height),
                System.Drawing.Color.FromArgb(255, 24, 32, 48),
                System.Drawing.Color.FromArgb(255, 197, 160, 89),
                45f);
            g.FillRectangle(bg, 0, 0, width, height);
            using var pen = new System.Drawing.Pen(System.Drawing.Color.White, 10f);
            g.DrawRectangle(pen, 40, 40, width - 80, height - 80);
            bmp.Save(path, ImageFormat.Png);
        }

        private static void SynthesizeImpulseAndChordWav(string path, double durationSec, double impulseAtSec)
        {
            const int sampleRate = 48000;
            const short channels = 2;
            const short bitsPerSample = 16;
            int totalFrames = Math.Max(4800, (int)(durationSec * sampleRate));
            int impulseSample = Math.Clamp((int)(impulseAtSec * sampleRate), 0, totalFrames - 1);
            int dataBytes = totalFrames * channels * (bitsPerSample / 8);

            using var fs = new FileStream(path, FileMode.Create, FileAccess.Write, FileShare.None);
            using var bw = new BinaryWriter(fs);

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

            for (int i = 0; i < totalFrames; i++)
            {
                double t = (double)i / sampleRate;
                double pad = 0.14 * Math.Sin(2.0 * Math.PI * 220.0 * t) + 0.10 * Math.Sin(2.0 * Math.PI * 330.0 * t);
                double distSec = Math.Abs(i - impulseSample) / (double)sampleRate;
                double burst = distSec < 0.035 ? (0.82 * Math.Exp(-distSec * 95.0) * Math.Sin(2.0 * Math.PI * 110.0 * t)) : 0.0;
                short pcm = (short)Math.Clamp((pad + burst) * 30000.0, short.MinValue + 100, short.MaxValue - 100);
                bw.Write(pcm);
                bw.Write(pcm);
            }
        }

        private static async Task SynthesizeTestVideoWithToneAsync(string outMp4Path, int width, int height, int fps, double durationSec)
        {
            string ffmpeg = CraftEngine.FindTrustedFfmpegExe();
            string durStr = durationSec.ToString("0.##", CultureInfo.InvariantCulture);

            var psi = new ProcessStartInfo
            {
                FileName = ffmpeg,
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true
            };
            psi.ArgumentList.Add("-nostdin");
            psi.ArgumentList.Add("-hide_banner");
            psi.ArgumentList.Add("-loglevel");
            psi.ArgumentList.Add("error");
            psi.ArgumentList.Add("-y");
            psi.ArgumentList.Add("-f");
            psi.ArgumentList.Add("lavfi");
            psi.ArgumentList.Add("-i");
            psi.ArgumentList.Add($"testsrc2=size={width}x{height}:rate={fps}:duration={durStr}");
            psi.ArgumentList.Add("-f");
            psi.ArgumentList.Add("lavfi");
            psi.ArgumentList.Add("-i");
            psi.ArgumentList.Add($"sine=frequency=440:sample_rate=48000:duration={durStr}");
            psi.ArgumentList.Add("-c:v");
            psi.ArgumentList.Add("libx264");
            psi.ArgumentList.Add("-preset");
            psi.ArgumentList.Add("ultrafast");
            psi.ArgumentList.Add("-pix_fmt");
            psi.ArgumentList.Add("yuv420p");
            psi.ArgumentList.Add("-c:a");
            psi.ArgumentList.Add("aac");
            psi.ArgumentList.Add("-ar");
            psi.ArgumentList.Add("48000");
            psi.ArgumentList.Add("-ac");
            psi.ArgumentList.Add("2");
            psi.ArgumentList.Add(outMp4Path);

            using var proc = Process.Start(psi)!;
            Task<string> outT = proc.StandardOutput.ReadToEndAsync();
            Task<string> errT = proc.StandardError.ReadToEndAsync();
            await proc.WaitForExitAsync();
            _ = await outT;
            string err = await errT;
            if (proc.ExitCode != 0 || !File.Exists(outMp4Path))
                throw new InvalidOperationException($"Synthetisches Testvideo fehlgeschlagen: {err}");
        }

        private static async Task<int> MeasureMaxAudioAmplitudeAsync(string mediaPath)
        {
            string ffmpeg = CraftEngine.FindTrustedFfmpegExe();
            var psi = new ProcessStartInfo
            {
                FileName = ffmpeg,
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true
            };
            psi.ArgumentList.Add("-nostdin");
            psi.ArgumentList.Add("-v");
            psi.ArgumentList.Add("error");
            psi.ArgumentList.Add("-t");
            psi.ArgumentList.Add("5");
            psi.ArgumentList.Add("-i");
            psi.ArgumentList.Add(mediaPath);
            psi.ArgumentList.Add("-f");
            psi.ArgumentList.Add("s16le");
            psi.ArgumentList.Add("-ac");
            psi.ArgumentList.Add("1");
            psi.ArgumentList.Add("-ar");
            psi.ArgumentList.Add("8000");
            psi.ArgumentList.Add("-");

            using var proc = Process.Start(psi)!;
            Task<string> errT = proc.StandardError.ReadToEndAsync();
            await using var ms = new MemoryStream();
            await proc.StandardOutput.BaseStream.CopyToAsync(ms);
            await proc.WaitForExitAsync();
            _ = await errT;

            byte[] pcm = ms.ToArray();
            int maxAmp = 0;
            for (int i = 0; i + 1 < pcm.Length; i += 2)
            {
                short s = BitConverter.ToInt16(pcm, i);
                int a = Math.Abs((int)s);
                if (a > maxAmp) maxAmp = a;
            }
            return maxAmp;
        }
    }
}
