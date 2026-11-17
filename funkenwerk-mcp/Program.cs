using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Sockets;
using System.Net.WebSockets;
using System.Security.Cryptography;
using System.Text;
using System.Text.Encodings.Web;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Threading;
using System.Threading.Tasks;

namespace FunkenwerkMcp
{
    public readonly struct JsonRpcFrameResult
    {
        public bool IsEof { get; init; }
        public bool UseContentLength { get; init; }
        public string? RawJson { get; init; }
        public string? ProtocolError { get; init; }

        public static JsonRpcFrameResult Eof() => new() { IsEof = true };
        public static JsonRpcFrameResult Success(string json, bool useContentLength) =>
            new() { RawJson = json, UseContentLength = useContentLength };
        public static JsonRpcFrameResult Error(string error, bool useContentLength) =>
            new() { ProtocolError = error, UseContentLength = useContentLength };
    }

    public sealed class JsonRpcByteFrameReader
    {
        private readonly Stream _stream;
        private readonly byte[] _buffer = new byte[16384];
        private int _pos;
        private int _len;

        public JsonRpcByteFrameReader(Stream stream)
        {
            _stream = stream ?? throw new ArgumentNullException(nameof(stream));
        }

        private async ValueTask<int> ReadByteAsync(CancellationToken ct)
        {
            if (_pos < _len)
                return _buffer[_pos++];

            _pos = 0;
            _len = await _stream.ReadAsync(_buffer.AsMemory(0, _buffer.Length), ct);
            if (_len <= 0)
                return -1;

            return _buffer[_pos++];
        }

        private async Task<int> ReadExactBytesAsync(byte[] destination, int count, CancellationToken ct)
        {
            int offset = 0;
            int buffered = _len - _pos;
            if (buffered > 0 && count > 0)
            {
                int take = Math.Min(buffered, count);
                Buffer.BlockCopy(_buffer, _pos, destination, 0, take);
                _pos += take;
                offset += take;
            }

            while (offset < count)
            {
                int n = await _stream.ReadAsync(destination.AsMemory(offset, count - offset), ct);
                if (n <= 0)
                    break;
                offset += n;
            }

            return offset;
        }

        private async Task DrainBytesAsync(long count, CancellationToken ct)
        {
            long remaining = count;
            int buffered = _len - _pos;
            if (buffered > 0 && remaining > 0)
            {
                int take = (int)Math.Min(buffered, remaining);
                _pos += take;
                remaining -= take;
            }

            byte[] discard = new byte[8192];
            while (remaining > 0)
            {
                int chunk = (int)Math.Min(discard.Length, remaining);
                int n = await _stream.ReadAsync(discard.AsMemory(0, chunk), ct);
                if (n <= 0)
                    break;
                remaining -= n;
            }
        }

        private async Task<(byte[]? Bytes, bool ExceededLimit, bool HitEof)> ReadRawLineBytesAsync(int maxBytes, CancellationToken ct)
        {
            using var ms = new MemoryStream(256);
            bool anyRead = false;
            bool exceeded = false;

            while (true)
            {
                int b = await ReadByteAsync(ct);
                if (b < 0)
                {
                    if (!anyRead)
                        return (null, false, true);
                    break;
                }

                anyRead = true;
                if (b == '\n')
                    break;
                if (b == '\r')
                    continue;

                if (!exceeded)
                {
                    if (ms.Length >= maxBytes)
                        exceeded = true;
                    else
                        ms.WriteByte((byte)b);
                }
            }

            return (exceeded ? null : ms.ToArray(), exceeded, false);
        }

        private static string StripLeadingBom(string text) =>
            text.Length > 0 && text[0] == '\uFEFF' ? text.Substring(1) : text;

        public async Task<JsonRpcFrameResult> ReadNextFrameAsync(CancellationToken ct = default)
        {
            while (true)
            {
                var (firstLineBytes, exceeded, hitEof) = await ReadRawLineBytesAsync(McpSecurityGuard.MaxJsonRpcMessageBytes, ct);
                if (hitEof)
                    return JsonRpcFrameResult.Eof();

                if (exceeded)
                {
                    return JsonRpcFrameResult.Error(
                        "Invalid Request: JSON-RPC Nachricht ueberschreitet das 512 KB Sicherheitslimit.",
                        useContentLength: false);
                }

                if (firstLineBytes == null || firstLineBytes.Length == 0)
                    continue;

                int prefixCheckLen = Math.Min(firstLineBytes.Length, 64);
                string asciiPrefix = StripLeadingBom(Encoding.UTF8.GetString(firstLineBytes, 0, prefixCheckLen)).TrimStart();

                if (asciiPrefix.StartsWith("Content-Length:", StringComparison.OrdinalIgnoreCase) ||
                    asciiPrefix.StartsWith("Content-Type:", StringComparison.OrdinalIgnoreCase))
                {
                    long? declaredLength = null;
                    string firstHeader = StripLeadingBom(Encoding.UTF8.GetString(firstLineBytes)).Trim();
                    if (firstHeader.StartsWith("Content-Length:", StringComparison.OrdinalIgnoreCase))
                    {
                        string numPart = firstHeader.Substring("Content-Length:".Length).Trim();
                        if (long.TryParse(numPart, out long parsed))
                            declaredLength = parsed;
                        else
                            declaredLength = -1;
                    }

                    int headerCount = 1;
                    bool tooManyHeaders = false;
                    while (true)
                    {
                        var (hBytes, hExceeded, hEof) = await ReadRawLineBytesAsync(4096, ct);
                        if (hEof)
                            return JsonRpcFrameResult.Eof();
                        if (hExceeded)
                            return JsonRpcFrameResult.Error("Invalid Request: Uebergrosser JSON-RPC Header.", useContentLength: true);
                        if (hBytes == null || hBytes.Length == 0)
                            break;

                        if (++headerCount > 32)
                        {
                            tooManyHeaders = true;
                            continue;
                        }

                        string headerLine = Encoding.ASCII.GetString(hBytes).Trim();
                        if (headerLine.StartsWith("Content-Length:", StringComparison.OrdinalIgnoreCase))
                        {
                            string numPart = headerLine.Substring("Content-Length:".Length).Trim();
                            if (long.TryParse(numPart, out long parsed))
                                declaredLength = parsed;
                            else
                                declaredLength = -1;
                        }
                    }

                    if (tooManyHeaders)
                        return JsonRpcFrameResult.Error("Invalid Request: Zu viele Header-Zeilen im JSON-RPC Frame.", useContentLength: true);

                    if (!declaredLength.HasValue || declaredLength.Value < 0)
                        return JsonRpcFrameResult.Error("Invalid Request: Ungueltiger Content-Length Header.", useContentLength: true);

                    long contentLength = declaredLength.Value;
                    if (contentLength > McpSecurityGuard.MaxJsonRpcMessageBytes)
                    {
                        if (contentLength <= 16L * 1024L * 1024L)
                            await DrainBytesAsync(contentLength, ct);

                        return JsonRpcFrameResult.Error(
                            $"Invalid Request: Content-Length ({contentLength} Bytes) ueberschreitet das 512 KB Sicherheitslimit.",
                            useContentLength: true);
                    }

                    if (contentLength == 0)
                        continue;

                    byte[] payload = new byte[(int)contentLength];
                    int totalRead = await ReadExactBytesAsync(payload, (int)contentLength, ct);
                    if (totalRead < (int)contentLength)
                        return JsonRpcFrameResult.Eof();

                    string decoded = StripLeadingBom(Encoding.UTF8.GetString(payload, 0, totalRead));
                    return JsonRpcFrameResult.Success(decoded, useContentLength: true);
                }

                string lineJson = StripLeadingBom(Encoding.UTF8.GetString(firstLineBytes));
                return JsonRpcFrameResult.Success(lineJson, useContentLength: false);
            }
        }
    }

    public static class Program
    {
        private static readonly UTF8Encoding Utf8NoBom = new(encoderShouldEmitUTF8Identifier: false);

        private static readonly JsonSerializerOptions JsonCompact = new()
        {
            WriteIndented = false,
            Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping
        };

        private static readonly JsonSerializerOptions JsonPretty = new()
        {
            WriteIndented = true,
            Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping
        };

        private static readonly JsonDocumentOptions StrictJsonParseOptions = new()
        {
            MaxDepth = 24,
            CommentHandling = JsonCommentHandling.Disallow,
            AllowTrailingCommas = false
        };

        public static async Task<int> Main(string[] args)
        {
            if (args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                return await McpSelfTestSuite.RunAllAsync();
            }

            if (args.Any(a => string.Equals(a, "--stage-sync-shop-assets", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunStageShopSyncCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--stage-deploy", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunStageDeployCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--stage-restart-workers", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunStageRestartWorkersCliAsync();
            }

            if (args.Any(a => string.Equals(a, "--stage-publish-apk", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunStagePublishApkCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--sentinel-sync", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunStageSentinelSyncCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--sentinel-hourly-guard", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunSentinelHourlyGuardCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--ag-set-master-chat", StringComparison.OrdinalIgnoreCase)) ||
                args.Any(a => a.StartsWith("--ag-set-master-chat=", StringComparison.OrdinalIgnoreCase)))
            {
                return RunAgSetMasterChatCli(args);
            }

            if (args.Any(a => string.Equals(a, "--ag-list-channels", StringComparison.OrdinalIgnoreCase)))
            {
                return RunAgListChannelsCli();
            }

            if (args.Any(a => string.Equals(a, "--ag-set-channel-chat", StringComparison.OrdinalIgnoreCase)) ||
                args.Any(a => a.StartsWith("--ag-set-channel-chat=", StringComparison.OrdinalIgnoreCase)))
            {
                return RunAgSetChannelChatCli(args);
            }

            if (args.Any(a => string.Equals(a, "--ag-inbox-sync", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunAgInboxSyncCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--ag-inbox-complete", StringComparison.OrdinalIgnoreCase)) ||
                args.Any(a => a.StartsWith("--ag-inbox-complete=", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunAgInboxCompleteCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--ag-sidecar-daemon", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunAgSidecarDaemonCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--stage-laser-relay-once", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunStageLaserRelayOnceCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--tresor-list", StringComparison.OrdinalIgnoreCase)))
            {
                return RunTresorListCli();
            }

            if (args.Any(a => string.Equals(a, "--tresor-browser-login", StringComparison.OrdinalIgnoreCase)) ||
                args.Any(a => a.StartsWith("--tresor-browser-login=", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunTresorBrowserLoginCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--mittwald-api", StringComparison.OrdinalIgnoreCase)) ||
                args.Any(a => a.StartsWith("--mittwald-api=", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunMittwaldApiBrokerCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--mittwald-prepare-live", StringComparison.OrdinalIgnoreCase)) ||
                args.Any(a => a.StartsWith("--mittwald-prepare-live=", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunMittwaldPrepareLiveCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--stripe-api", StringComparison.OrdinalIgnoreCase)) ||
                args.Any(a => a.StartsWith("--stripe-api=", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunStripeApiBrokerCliAsync(args);
            }

            // Ensure ACL lockdown on MCP data directory immediately at startup
            McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

            // Capture raw stdin/stdout streams FIRST, then redirect Console.Out -> Console.Error
            // so no library (PDFsharp, SSH.NET, WPF) can ever pollute the JSON-RPC wire!
            using var stdin = Console.OpenStandardInput();
            using var stdout = Console.OpenStandardOutput();
            Console.SetOut(Console.Error);

            await RunStdioServerLoopAsync(stdin, stdout);
            return 0;
        }

        private static async Task<int> RunStageShopSyncCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                bool dryRun = args.Any(a => string.Equals(a, "--dry-run", StringComparison.OrdinalIgnoreCase));
                var res = await McpSecurityGuard.SyncStageShopAssetsDeltaAsync("Mittwald", dryRun: dryRun);
                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(res, JsonPretty)));
                return res.Success ? 0 : 1;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Stage Shop-Asset-Sync Fehler: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        public const string DefaultStageDeployMutexName = @"Local\FunkenwerkStageDeployLock";

        public sealed class StageDeployMutexLock : IDisposable
        {
            private readonly ManualResetEventSlim _releaseSignal = new(false);
            private readonly Thread _ownerThread;
            private int _disposed;

            public bool WaitedForContention { get; }

            private StageDeployMutexLock(Thread ownerThread, bool waitedForContention)
            {
                _ownerThread = ownerThread;
                WaitedForContention = waitedForContention;
            }

            public static async Task<StageDeployMutexLock> AcquireAsync(
                TimeSpan timeout,
                string? customMutexName = null,
                Action<string>? onWaiting = null)
            {
                string mutexName = string.IsNullOrWhiteSpace(customMutexName)
                    ? DefaultStageDeployMutexName
                    : customMutexName.Trim();

                var readyTcs = new TaskCompletionSource<StageDeployMutexLock>(TaskCreationOptions.RunContinuationsAsynchronously);
                StageDeployMutexLock? instance = null;

                var thread = new Thread(() =>
                {
                    Mutex? mutex = null;
                    bool acquired = false;
                    bool waited = false;
                    try
                    {
                        mutex = new Mutex(initiallyOwned: false, name: mutexName);
                        try
                        {
                            acquired = mutex.WaitOne(0);
                        }
                        catch (AbandonedMutexException)
                        {
                            acquired = true;
                        }

                        if (!acquired)
                        {
                            waited = true;
                            onWaiting?.Invoke($"⏳ [StageDeployMutex] Paralleler Stage-Rollout aktiv ({mutexName}) - warte sauber bis zu {(int)Math.Ceiling(timeout.TotalSeconds)} s auf Freigabe...");
                            try
                            {
                                acquired = mutex.WaitOne(timeout);
                            }
                            catch (AbandonedMutexException)
                            {
                                acquired = true;
                            }
                        }

                        if (!acquired)
                        {
                            readyTcs.TrySetException(new TimeoutException(
                                $"Stage-Deploy-Sperre ({mutexName}) wurde nach {(int)timeout.TotalSeconds} Sekunden nicht frei."));
                            return;
                        }

                        instance = new StageDeployMutexLock(Thread.CurrentThread, waited);
                        readyTcs.TrySetResult(instance);

                        // Hold mutex ownership on this exact OS thread until Dispose() signals release
                        instance._releaseSignal.Wait();
                    }
                    catch (Exception ex)
                    {
                        readyTcs.TrySetException(ex);
                    }
                    finally
                    {
                        if (acquired && mutex != null)
                        {
                            try { mutex.ReleaseMutex(); } catch { }
                        }
                        mutex?.Dispose();
                        instance?._releaseSignal.Dispose();
                    }
                })
                {
                    IsBackground = true,
                    Name = "FunkenwerkStageDeployMutexOwner"
                };

                thread.Start();
                return await readyTcs.Task;
            }

            public void Dispose()
            {
                if (Interlocked.Exchange(ref _disposed, 1) != 0)
                    return;

                try { _releaseSignal.Set(); } catch { }
                if (Thread.CurrentThread != _ownerThread && _ownerThread.IsAlive)
                {
                    _ownerThread.Join(TimeSpan.FromSeconds(5));
                }
            }
        }

        private static async Task<int> RunStageDeployCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                using var deployLock = await StageDeployMutexLock.AcquireAsync(
                    TimeSpan.FromSeconds(180),
                    onWaiting: msg => Console.WriteLine(msg));
                bool skipAssets = args.Any(a => string.Equals(a, "--skip-shop-assets", StringComparison.OrdinalIgnoreCase));

                StageShopAssetSyncResultDto? syncRes = null;
                if (!skipAssets)
                {
                    Console.WriteLine("🛍️ [1/3] Prüfe reines Shop-Asset-Delta per SHA-256 (Musik, Buchhaltung & Privatdaten 100 % gesperrt)...");
                    syncRes = await McpSecurityGuard.SyncStageShopAssetsDeltaAsync("Mittwald", dryRun: false);
                    Console.WriteLine($"   ✓ Manifest: {syncRes.TotalManifestFiles} Shop-Assets | Unverändert: {syncRes.UnchangedOnStageCount} | Hochgeladen: {syncRes.UploadedCount} ({syncRes.UploadedBytes / 1024.0:F1} KB)");
                }
                else
                {
                    Console.WriteLine("⏭️ [1/3] Shop-Asset-Abgleich übersprungen (--skip-shop-assets).");
                }

                Console.WriteLine("🚀 [2/4] Führe Stage-Deploy auf 'Mittwald' (/home/p-g27wim/html/seelenfunke-stage) aus...");
                string deployOut = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "stage_deploy");
                Console.WriteLine(deployOut);

                Console.WriteLine("👷 [3/4] Starte isolierten Stage-Worker neu ('Mittwald Websocket' / worker-stage-3)...");
                string workerOut = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald Websocket", "stage_worker_restart");
                if (!string.IsNullOrWhiteSpace(workerOut))
                    Console.WriteLine(workerOut);

                Console.WriteLine("🎙️ [4/4] Starte isolierte Node.js Audio-Bridge neu ('Mittwald Node.js App' / twilio-bridge)...");
                string bridgeOut = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald Node.js App", "stage_worker_restart");
                if (!string.IsNullOrWhiteSpace(bridgeOut))
                    Console.WriteLine(bridgeOut);

                Console.WriteLine("✅ Stage-Autopilot komplett erfolgreich abgeschlossen (Live-Server blieb 100 % unberührt).");
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Stage-Autopilot abgebrochen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        private static async Task<int> RunStageRestartWorkersCliAsync()
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

                Console.WriteLine("👷 [1/2] Starte isolierten Stage-Worker neu ('Mittwald Websocket' / a-iurgq8)...");
                string workerOut = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald Websocket", "stage_worker_restart");
                if (!string.IsNullOrWhiteSpace(workerOut))
                    Console.WriteLine(workerOut);

                Console.WriteLine("🎙️ [2/2] Starte isolierte Node.js Audio-Bridge neu ('Mittwald Node.js App' / a-yeq7el)...");
                string bridgeOut = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald Node.js App", "stage_worker_restart");
                if (!string.IsNullOrWhiteSpace(bridgeOut))
                    Console.WriteLine(bridgeOut);

                Console.WriteLine("✅ Beide Stage-Container (Websocket & Node.js Bridge) erfolgreich per mittnitectl neu gestartet.");
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Stage-Worker-Restart abgebrochen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        private static async Task<int> RunStagePublishApkCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                using var deployLock = await StageDeployMutexLock.AcquireAsync(
                    TimeSpan.FromSeconds(180),
                    onWaiting: msg => Console.WriteLine(msg));

                string? GetArgValue(string flag)
                {
                    for (int i = 0; i < args.Length - 1; i++)
                    {
                        if (string.Equals(args[i], flag, StringComparison.OrdinalIgnoreCase))
                            return args[i + 1];
                    }
                    return null;
                }

                string? apkPath = GetArgValue("--apk");
                string? rawCode = GetArgValue("--version-code");
                string? versionName = GetArgValue("--version-name");
                string notes = GetArgValue("--notes") ?? "";

                int versionCode = 0;
                if (!string.IsNullOrWhiteSpace(rawCode))
                    int.TryParse(rawCode, out versionCode);

                if (versionCode <= 0 || string.IsNullOrWhiteSpace(versionName))
                {
                    string[] gradleCandidates =
                    {
                        @"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\android-app\app\build.gradle.kts",
                        @"C:\Users\konta\AndroidStudioProjects\seelenfunke-android\app\build.gradle.kts"
                    };
                    string? gradleFile = gradleCandidates.FirstOrDefault(File.Exists);
                    if (gradleFile != null)
                    {
                        string kts = await File.ReadAllTextAsync(gradleFile);
                        if (versionCode <= 0)
                        {
                            var mCode = System.Text.RegularExpressions.Regex.Match(kts, @"versionCode\s*=\s*(\d+)");
                            if (mCode.Success) int.TryParse(mCode.Groups[1].Value, out versionCode);
                        }
                        if (string.IsNullOrWhiteSpace(versionName))
                        {
                            var mName = System.Text.RegularExpressions.Regex.Match(kts, @"versionName\s*=\s*""([^""]+)""");
                            if (mName.Success) versionName = mName.Groups[1].Value;
                        }
                    }
                }

                Console.WriteLine($"📱 [Stage-OTA] Prüfe & veröffentliche Funkenwerk v{versionName} (Build {versionCode}) per Ed25519-Key-Only auf Stage...");
                var res = await McpSecurityGuard.PublishStageAndroidApkAsync(
                    profileName: "Mittwald",
                    customApkPath: apkPath,
                    versionCode: versionCode,
                    versionName: versionName ?? "",
                    releaseNotes: notes);

                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(res, JsonPretty)));
                return res.Success ? 0 : 1;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Stage-APK-Publish abgebrochen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        internal static (int ArchivedFiles, long ArchivedBytes, long RemainingActiveBytes) ArchiveAndZeroWindowsDesktopLogs(string? localAppDataOverride = null)
        {
            string baseLocal = string.IsNullOrWhiteSpace(localAppDataOverride)
                ? Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData)
                : localAppDataOverride;

            int archivedFiles = 0;
            long archivedBytes = 0;
            string stamp = DateTime.UtcNow.ToString("yyyy-MM-dd_HHmmss");
            var cutoffUtc = DateTime.UtcNow.AddDays(-30);

            string tresorDir = Path.Combine(baseLocal, "FunkenwerkTresor");
            string tresorBootLog = Path.Combine(tresorDir, "funkenwerk_tresor_boot.log");
            if (File.Exists(tresorBootLog))
            {
                long sz = new FileInfo(tresorBootLog).Length;
                if (sz > 0)
                {
                    string archDir = Path.Combine(tresorDir, "archive");
                    Directory.CreateDirectory(archDir);
                    McpSecurityGuard.LockDownDirectoryAcl(archDir);
                    string raw = File.ReadAllText(tresorBootLog, Encoding.UTF8);
                    string archPath = Path.Combine(archDir, $"healed_{stamp}_funkenwerk_tresor_boot.log");
                    string header = $"# Seelenfunke Windows Log-Archiv | Archiviert: {DateTimeOffset.Now:O} | Quelle: funkenwerk_tresor_boot.log ({sz} Bytes){Environment.NewLine}";
                    File.WriteAllText(archPath, header + McpSecurityGuard.ScrubSecrets(raw), Utf8NoBom);
                    File.WriteAllText(tresorBootLog, "", Utf8NoBom);
                    archivedFiles++;
                    archivedBytes += sz;

                    foreach (var f in Directory.GetFiles(archDir))
                    {
                        if (File.GetLastWriteTimeUtc(f) < cutoffUtc)
                        {
                            try { File.Delete(f); } catch { }
                        }
                    }
                }
            }

            string fwDir = Path.Combine(baseLocal, "Funkenwerk");
            string fwQueue = Path.Combine(fwDir, "error_sentinel_queue.json");
            if (File.Exists(fwQueue))
            {
                long qSz = new FileInfo(fwQueue).Length;
                if (qSz > 0)
                {
                    string archDir = Path.Combine(fwDir, "archive");
                    Directory.CreateDirectory(archDir);
                    McpSecurityGuard.LockDownDirectoryAcl(archDir);
                    string rawQ = File.ReadAllText(fwQueue, Encoding.UTF8);
                    string archQ = Path.Combine(archDir, $"healed_{stamp}_error_sentinel_queue.json");
                    File.WriteAllText(archQ, McpSecurityGuard.ScrubSecrets(rawQ), Utf8NoBom);
                    archivedFiles++;
                    archivedBytes += qSz;
                }
                try { File.Delete(fwQueue); } catch { File.WriteAllText(fwQueue, "", Utf8NoBom); }
            }

            long rem = (File.Exists(tresorBootLog) ? new FileInfo(tresorBootLog).Length : 0)
                     + (File.Exists(fwQueue) ? new FileInfo(fwQueue).Length : 0);
            return (archivedFiles, archivedBytes, rem);
        }

        internal static int CollectWindowsDesktopDossiers(
            HashSet<string> seenFingerprints,
            JsonArray itemsArray,
            string? localAppDataOverride = null)
        {
            string baseLocal = string.IsNullOrWhiteSpace(localAppDataOverride)
                ? Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData)
                : localAppDataOverride;

            int added = 0;

            string fwQueue = Path.Combine(baseLocal, "Funkenwerk", "error_sentinel_queue.json");
            if (File.Exists(fwQueue) && new FileInfo(fwQueue).Length > 0)
            {
                try
                {
                    if (JsonNode.Parse(File.ReadAllText(fwQueue, Encoding.UTF8)) is JsonArray qArr)
                    {
                        foreach (var qItem in qArr)
                        {
                            if (qItem is not JsonObject ev) continue;
                            bool autoHealed = (ev["AutoHealed"] ?? ev["auto_healed"])?.GetValue<bool>() ?? false;
                            if (autoHealed) continue;

                            string evTitle = McpSecurityGuard.ScrubSecrets((ev["Title"] ?? ev["title"])?.GetValue<string>() ?? "Windows Funkenwerk Fehler");
                            string evComp = McpSecurityGuard.ScrubSecrets((ev["Component"] ?? ev["component"])?.GetValue<string>() ?? "funkenwerk_wpf");
                            string evFp = (ev["Fingerprint"] ?? ev["fingerprint"])?.GetValue<string>() ?? "";
                            if (string.IsNullOrWhiteSpace(evFp))
                            {
                                byte[] hash = System.Security.Cryptography.SHA256.HashData(Encoding.UTF8.GetBytes($"windows_desktop|{evComp}|{evTitle}".ToLowerInvariant()));
                                evFp = Convert.ToHexString(hash).ToLowerInvariant();
                            }
                            if (!seenFingerprints.Add(evFp)) continue;

                            itemsArray.Add(new JsonObject
                            {
                                ["dossier_version"] = 2,
                                ["fingerprint"] = evFp,
                                ["origin_environment"] = "windows_local",
                                ["prepared_at"] = (ev["OccurredAt"] ?? ev["occurred_at"])?.GetValue<string>() ?? DateTimeOffset.Now.ToString("O"),
                                ["prepared_by"] = "Funkenwerk Windows Sentinel",
                                ["criticality"] = "standard",
                                ["platform"] = "windows_desktop",
                                ["platform_label"] = "Funkenwerk Software",
                                ["type"] = "windows_desktop",
                                ["action_id"] = $"windows_desktop:{evComp}",
                                ["severity"] = (ev["Severity"] ?? ev["severity"])?.GetValue<string>() ?? "error",
                                ["title"] = evTitle,
                                ["message"] = McpSecurityGuard.ScrubSecrets((ev["Message"] ?? ev["message"])?.GetValue<string>() ?? ""),
                                ["culprit_file"] = "funkenwerk/App.xaml.cs",
                                ["line"] = null,
                                ["occurrences_count"] = 1,
                                ["probable_cause"] = "Lokaler Laufzeitfehler in der Funkenwerk Windows Suite.",
                                ["recommended_action"] = "Stacktrace in funkenwerk/ prüfen und Fehlerursache beheben.",
                                ["trace_excerpt"] = McpSecurityGuard.ScrubSecrets((ev["Stacktrace"] ?? ev["stacktrace"])?.GetValue<string>() ?? "")
                            });
                            added++;
                        }
                    }
                }
                catch { }
            }

            string tresorBootLog = Path.Combine(baseLocal, "FunkenwerkTresor", "funkenwerk_tresor_boot.log");
            if (File.Exists(tresorBootLog) && new FileInfo(tresorBootLog).Length > 0)
            {
                try
                {
                    string rawBoot = File.ReadAllText(tresorBootLog, Encoding.UTF8);
                    string trimmedBoot = rawBoot.Trim();
                    if (!string.IsNullOrEmpty(trimmedBoot))
                    {
                        string scrubbedBoot = McpSecurityGuard.ScrubSecrets(trimmedBoot);
                        string excerpt = scrubbedBoot.Length > 1800
                            ? scrubbedBoot.Substring(scrubbedBoot.Length - 1800)
                            : scrubbedBoot;
                        bool hasException = scrubbedBoot.Contains("EXCEPTION", StringComparison.OrdinalIgnoreCase) ||
                                            scrubbedBoot.Contains("STARTUP_CATCH", StringComparison.OrdinalIgnoreCase);
                        string title = hasException
                            ? "[Funkenwerk Tresor] Ausnahme in funkenwerk_tresor_boot.log erkannt"
                            : "[Funkenwerk Tresor] funkenwerk_tresor_boot.log > 0 Bytes";
                        byte[] hash = System.Security.Cryptography.SHA256.HashData(
                            Encoding.UTF8.GetBytes($"windows_desktop|funkenwerk_tresor_boot|{title}".ToLowerInvariant()));
                        string fp = Convert.ToHexString(hash).ToLowerInvariant();

                        if (seenFingerprints.Add(fp))
                        {
                            itemsArray.Add(new JsonObject
                            {
                                ["dossier_version"] = 2,
                                ["fingerprint"] = fp,
                                ["origin_environment"] = "windows_local",
                                ["prepared_at"] = DateTimeOffset.Now.ToString("O"),
                                ["prepared_by"] = "Funkenwerk Windows Sentinel",
                                ["criticality"] = "standard",
                                ["platform"] = "windows_desktop",
                                ["platform_label"] = "Funkenwerk Software",
                                ["type"] = "windows_desktop",
                                ["action_id"] = "windows_desktop:funkenwerk_tresor_boot",
                                ["severity"] = hasException ? "error" : "warning",
                                ["title"] = title,
                                ["message"] = $"funkenwerk_tresor_boot.log ({new FileInfo(tresorBootLog).Length} Bytes) enthält unarchivierte Einträge.",
                                ["culprit_file"] = "funkenwerk-tresor/App.xaml.cs",
                                ["line"] = 48,
                                ["occurrences_count"] = 1,
                                ["probable_cause"] = "Unbehandelte Ausnahme oder nicht-archiviertes Boot-Log in Funkenwerk Tresor.",
                                ["recommended_action"] = "funkenwerk-tresor/App.xaml.cs prüfen und nach Fix per --resolve-all archivieren.",
                                ["trace_excerpt"] = excerpt
                            });
                            added++;
                        }
                    }
                }
                catch { }
            }

            return added;
        }

        private static async Task<int> RunStageSentinelSyncCliAsync(string[] args, bool quietOutput = false)
        {
            TextWriter origOut = Console.Out;
            if (quietOutput)
            {
                Console.SetOut(TextWriter.Null);
            }
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

                bool resolveAll = args.Any(a => string.Equals(a, "--resolve-all", StringComparison.OrdinalIgnoreCase));
                bool resolveStageFirst = resolveAll || args.Any(a => string.Equals(a, "--resolve-stage", StringComparison.OrdinalIgnoreCase));
                bool resolveMiniPcFirst = resolveAll || args.Any(a => string.Equals(a, "--resolve-minipc", StringComparison.OrdinalIgnoreCase));

                if (resolveStageFirst)
                {
                    Console.WriteLine("🧹 [Stage-Pool] Markiere offene Stage-Pool-Dossiers als gelöst...");
                    string resolveOut = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "stage_sentinel_resolve_all");
                    Console.WriteLine($"   ✓ Stage-Antwort: {resolveOut}");
                }

                if (resolveMiniPcFirst)
                {
                    try
                    {
                        Console.WriteLine("🧹 [Mini-PC] Archiviere erledigte Dossiers & leere Haupt-Logs auf 0 Bytes...");
                        string miniResolveOut = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Manufaktur Mini-PC", "minipc_sentinel_resolve_all");
                        Console.WriteLine($"   ✓ Mini-PC-Antwort: {miniResolveOut}");
                    }
                    catch (Exception miniEx)
                    {
                        Console.WriteLine($"   ℹ Mini-PC aktuell im Energiesparmodus/offline ({McpSecurityGuard.ScrubSecrets(miniEx.Message)}).");
                    }
                }

                if (resolveAll || args.Any(a => string.Equals(a, "--resolve-windows", StringComparison.OrdinalIgnoreCase)))
                {
                    try
                    {
                        var (winFiles, winBytes, winRem) = ArchiveAndZeroWindowsDesktopLogs();
                        if (winFiles > 0)
                        {
                            Console.WriteLine($"🧹 [Windows-Desktop] {winFiles} Logdatei(en) ({winBytes} Bytes) ins Archiv verschoben (Rest: {winRem} Bytes).");
                        }
                    }
                    catch { }
                }

                if (resolveAll)
                {
                    try
                    {
                        Console.WriteLine("🧹 [Lokal] Archiviere lokale Haupt-Logs (storage/logs/*.log -> archive/) & leere auf 0 Bytes...");
                        var localResolvePsi = new ProcessStartInfo
                        {
                            FileName = "wsl.exe",
                            Arguments = "-d Ubuntu bash -c \"docker exec -u www-data mein_php_server php /var/www/html/artisan system:sentinel-inbox --resolve-all --resolved-by=Antigravity 2>/dev/null\"",
                            UseShellExecute = false,
                            RedirectStandardOutput = true,
                            RedirectStandardError = true,
                            CreateNoWindow = true
                        };
                        using var lProc = Process.Start(localResolvePsi);
                        if (lProc != null)
                        {
                            using var lCts = new CancellationTokenSource(TimeSpan.FromSeconds(15));
                            string lOut = await lProc.StandardOutput.ReadToEndAsync(lCts.Token);
                            await lProc.WaitForExitAsync(lCts.Token);
                            if (lProc.ExitCode == 0 && !string.IsNullOrWhiteSpace(lOut))
                            {
                                Console.WriteLine($"   ✓ Lokal-Antwort: {McpSecurityGuard.ScrubSecrets(lOut.Trim())}");
                            }
                        }
                    }
                    catch { }

                    try
                    {
                        string localStorageDir = Path.GetDirectoryName(McpSecurityGuard.DefaultLocalStorageAppRoot) ?? "";
                        string localLogsDir = Path.Combine(localStorageDir, "logs");
                        var (locArchCount, locArchBytes, _) = ArchiveAndZeroLocalPortalLogsFallback(localLogsDir);
                        if (locArchCount > 0)
                        {
                            Console.WriteLine($"   ✓ Lokales Log-Archiv-Fallback: {locArchCount} Datei(en) ({locArchBytes} Bytes) archiviert und auf 0 Bytes geleert.");
                        }
                    }
                    catch { }
                }

                Console.WriteLine("🛡️ [1/3] Rufe 24/7 Funkira-Auffang-Pool von Stage ('Mittwald' / seelenfunke-stage) per Ed25519-SSH ab...");
                string rawJson = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "stage_sentinel_export");

                int jsonStart = rawJson.IndexOf('{');
                int jsonEnd = rawJson.LastIndexOf('}');
                if (jsonStart < 0 || jsonEnd <= jsonStart)
                    throw new InvalidOperationException("Stage lieferte kein gültiges JSON-Objekt für den Auffang-Pool.");

                string cleanJson = McpSecurityGuard.ScrubSecrets(rawJson.Substring(jsonStart, jsonEnd - jsonStart + 1));
                var rootNode = JsonNode.Parse(cleanJson)?.AsObject()
                    ?? throw new InvalidOperationException("Stage-JSON konnte nicht als Objekt geparst werden.");

                var itemsArray = rootNode["items"]?.AsArray() ?? new JsonArray();
                if (rootNode["items"] == null)
                {
                    rootNode["items"] = itemsArray;
                }

                var seenFingerprints = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
                foreach (var existingNode in itemsArray)
                {
                    string? fp = existingNode?["fingerprint"]?.GetValue<string>();
                    if (!string.IsNullOrWhiteSpace(fp))
                        seenFingerprints.Add(fp);
                }

                int miniPcAdded = 0;
                Console.WriteLine("🖥️ [2/3] Rufe direkte Funkira-Keller-Dossiers vom Manufaktur Mini-PC (192.168.188.151) ab...");
                try
                {
                    string rawMiniJson = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Manufaktur Mini-PC", "minipc_sentinel_export");
                    int mStart = rawMiniJson.IndexOf('{');
                    int mEnd = rawMiniJson.LastIndexOf('}');
                    if (mStart >= 0 && mEnd > mStart)
                    {
                        string cleanMini = McpSecurityGuard.ScrubSecrets(rawMiniJson.Substring(mStart, mEnd - mStart + 1));
                        var miniRoot = JsonNode.Parse(cleanMini)?.AsObject();
                        if (miniRoot != null)
                        {
                            if (miniRoot["handoff"] is JsonObject handoffObj)
                            {
                                string hoTitle = handoffObj["title"]?.GetValue<string>() ?? "Mini-PC Befund";
                                string hoPlatform = handoffObj["platform"]?.GetValue<string>() ?? "minipc_linux";
                                string hoComp = handoffObj["item_key"]?.GetValue<string>() ?? "cellar_handoff";
                                string hoFp = handoffObj["fingerprint"]?.GetValue<string>() ?? "";
                                if (string.IsNullOrWhiteSpace(hoFp))
                                {
                                    byte[] hash = System.Security.Cryptography.SHA256.HashData(Encoding.UTF8.GetBytes($"{hoPlatform}|{hoComp}|{hoTitle}".ToLowerInvariant()));
                                    hoFp = Convert.ToHexString(hash).ToLowerInvariant();
                                }

                                if (seenFingerprints.Add(hoFp))
                                {
                                    var converted = new JsonObject
                                    {
                                        ["dossier_version"] = 2,
                                        ["fingerprint"] = hoFp,
                                        ["origin_environment"] = "minipc_direct",
                                        ["prepared_at"] = handoffObj["prepared_at"]?.GetValue<string>() ?? DateTimeOffset.Now.ToString("O"),
                                        ["prepared_by"] = handoffObj["prepared_by"]?.GetValue<string>() ?? "Funkira Mini-PC Analyse-Keller",
                                        ["criticality"] = (handoffObj["severity"]?.GetValue<string>() == "critical") ? "critical" : "high",
                                        ["platform"] = hoPlatform,
                                        ["platform_label"] = handoffObj["platform_label"]?.GetValue<string>() ?? "Mini-PC",
                                        ["type"] = "minipc",
                                        ["action_id"] = hoComp,
                                        ["severity"] = handoffObj["severity"]?.GetValue<string>() ?? "error",
                                        ["title"] = hoTitle,
                                        ["message"] = handoffObj["message"]?.GetValue<string>() ?? "",
                                        ["culprit_file"] = handoffObj["culprit_file"]?.GetValue<string>(),
                                        ["line"] = handoffObj["line"] != null ? JsonNode.Parse(handoffObj["line"]!.ToJsonString()) : null,
                                        ["route_or_command"] = handoffObj["route"]?.GetValue<string>() ?? "",
                                        ["occurrences_count"] = handoffObj["occurrences_count"]?.GetValue<int>() ?? 1,
                                        ["probable_cause"] = handoffObj["probable_cause"]?.GetValue<string>() ?? "",
                                        ["recommended_action"] = handoffObj["recommended_action"]?.GetValue<string>() ?? "",
                                        ["alina_note"] = handoffObj["alina_note"]?.GetValue<string>(),
                                        ["trace_excerpt"] = handoffObj["trace_excerpt"]?.GetValue<string>() ?? ""
                                    };
                                    itemsArray.Add(converted);
                                    miniPcAdded++;
                                }
                            }

                            if (miniRoot["open_history"] is JsonArray openHist)
                            {
                                foreach (var histItem in openHist)
                                {
                                    if (histItem is not JsonObject ev) continue;
                                    string evFp = (ev["Fingerprint"] ?? ev["fingerprint"])?.GetValue<string>() ?? "";
                                    string evTitle = (ev["Title"] ?? ev["title"])?.GetValue<string>() ?? "Mini-PC Laufzeitfehler";
                                    string evComp = (ev["Component"] ?? ev["component"])?.GetValue<string>() ?? "minipc_runtime";
                                    if (string.IsNullOrWhiteSpace(evFp))
                                    {
                                        byte[] hash = System.Security.Cryptography.SHA256.HashData(Encoding.UTF8.GetBytes($"minipc_linux|{evComp}|{evTitle}".ToLowerInvariant()));
                                        evFp = Convert.ToHexString(hash).ToLowerInvariant();
                                    }
                                    if (!seenFingerprints.Add(evFp)) continue;

                                    var convertedEv = new JsonObject
                                    {
                                        ["dossier_version"] = 2,
                                        ["fingerprint"] = evFp,
                                        ["origin_environment"] = "minipc_direct",
                                        ["prepared_at"] = (ev["OccurredAt"] ?? ev["occurred_at"])?.GetValue<string>() ?? DateTimeOffset.Now.ToString("O"),
                                        ["prepared_by"] = "Funkira Mini-PC Sentinel",
                                        ["criticality"] = "standard",
                                        ["platform"] = "minipc_linux",
                                        ["platform_label"] = "Mini-PC",
                                        ["type"] = "minipc",
                                        ["action_id"] = evComp,
                                        ["severity"] = (ev["Severity"] ?? ev["severity"])?.GetValue<string>() ?? "error",
                                        ["title"] = evTitle,
                                        ["message"] = (ev["Message"] ?? ev["message"])?.GetValue<string>() ?? "",
                                        ["culprit_file"] = null,
                                        ["line"] = null,
                                        ["occurrences_count"] = 1,
                                        ["probable_cause"] = "Ungeheilter Laufzeitbefund aus Funkiras lokalem Mini-PC-Protokoll.",
                                        ["recommended_action"] = "Stacktrace und Komponente in funkenwerk-os-linux prüfen.",
                                        ["trace_excerpt"] = (ev["Stacktrace"] ?? ev["stacktrace"])?.GetValue<string>() ?? ""
                                    };
                                    itemsArray.Add(convertedEv);
                                    miniPcAdded++;
                                }
                            }
                        }
                    }
                    Console.WriteLine($"   ✓ Mini-PC direkt abgeglichen: {miniPcAdded} zusätzliche lokale Dossiers übernommen.");
                }
                catch (Exception miniSyncEx)
                {
                    Console.WriteLine($"   ℹ Mini-PC aktuell im Energiesparmodus/nicht erreichbar ({McpSecurityGuard.ScrubSecrets(miniSyncEx.Message)}). Stage-Pool wird weiterverarbeitet.");
                }

                int winDesktopAdded = CollectWindowsDesktopDossiers(seenFingerprints, itemsArray);
                if (winDesktopAdded > 0)
                {
                    Console.WriteLine($"   ⚠ Windows-Desktop geprüft: {winDesktopAdded} lokale(s) Dossier(s) übernommen.");
                }

                int openPoolCount = itemsArray.Count;
                rootNode["open_pool_count"] = openPoolCount;
                int autoHealed7d = rootNode["auto_healed_last_7d_count"]?.GetValue<int>() ?? 0;

                string localPrivateDir = Path.Combine(McpSecurityGuard.DefaultLocalStorageAppRoot, "private");
                Directory.CreateDirectory(localPrivateDir);
                string localInboxPath = Path.Combine(localPrivateDir, "antigravity_inbox.json");
                string localHandoffPath = Path.Combine(localPrivateDir, "antigravity_error_handoff.json");

                string prettyJson = rootNode.ToJsonString(JsonPretty);
                try
                {
                    await File.WriteAllTextAsync(localInboxPath, prettyJson, Utf8NoBom);
                }
                catch (UnauthorizedAccessException)
                {
                    if (File.Exists(localInboxPath))
                        File.Delete(localInboxPath);
                    await File.WriteAllTextAsync(localInboxPath, prettyJson, Utf8NoBom);
                }

                if (openPoolCount == 0 && File.Exists(localHandoffPath))
                {
                    try { File.Delete(localHandoffPath); } catch { }
                }

                Console.WriteLine($"   ✓ Universums-Pool zusammengeführt: {openPoolCount} offene Antigravity-Dossiers | {autoHealed7d} Auto-Heilungen (7 Tage).");

                Console.WriteLine("📥 [3/3] Synchronisiere Universums-Auffang-Pool in lokale Datenbank (mein_php_server)...");
                try
                {
                    var psi = new ProcessStartInfo
                    {
                        FileName = "wsl.exe",
                        Arguments = "-d Ubuntu bash -c \"docker exec -u www-data mein_php_server php /var/www/html/artisan system:sentinel-inbox --import-file=storage/app/private/antigravity_inbox.json 2>/dev/null\"",
                        UseShellExecute = false,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true,
                        CreateNoWindow = true
                    };
                    using var proc = Process.Start(psi);
                    if (proc != null)
                    {
                        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(20));
                        string importOut = await proc.StandardOutput.ReadToEndAsync(cts.Token);
                        await proc.WaitForExitAsync(cts.Token);
                        if (proc.ExitCode == 0 && !string.IsNullOrWhiteSpace(importOut))
                        {
                            Console.WriteLine($"   ✓ Lokaler DB-Import & Code-Snippet-Anreicherung erfolgreich: {McpSecurityGuard.ScrubSecrets(importOut.Trim())}");
                        }
                        else
                        {
                            Console.WriteLine("   ℹ Lokaler Docker-Container schläft aktuell; Datei storage/app/private/antigravity_inbox.json liegt vollständig bereit.");
                        }
                    }
                }
                catch
                {
                    Console.WriteLine("   ℹ Lokaler DB-Import übersprungen (Datei storage/app/private/antigravity_inbox.json ist vollständig gespeichert).");
                }

                Console.WriteLine("✅ 24/7 Universums-Auffang-Pool-Sync abgeschlossen.");
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Sentinel-Pool-Sync abgebrochen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
            finally
            {
                if (quietOutput)
                {
                    Console.SetOut(origOut);
                }
            }
        }

        // =====================================================================
        // HOURLY SENTINEL SILENT GUARD (0 NEW CHATS, 0 TOKENS WHEN HEALTHY)
        // =====================================================================
        public enum SentinelHourlyDecision
        {
            HealthyZeroBytes,
            HarmlessInfoLogsOnlyRotate,
            ErrorNeedsMasterChatNotification,
            DuplicateErrorAlreadyNotified
        }

        public sealed class SentinelHourlyEvaluationResult
        {
            public SentinelHourlyDecision Decision { get; init; }
            public int OpenPoolCount { get; init; }
            public long TotalActiveLogBytes { get; init; }
            public int NonEmptyLogFilesCount { get; init; }
            public int SevereLogFilesCount { get; init; }
            public string StateHash { get; init; } = "";
            public string TargetConversationId { get; init; } = "";
            public string? SelfHealPrompt { get; init; }
            public List<string> SummaryLines { get; init; } = new();
        }

        internal static bool ContainsSevereLogEntry(string? logContent)
        {
            if (string.IsNullOrWhiteSpace(logContent)) return false;
            return logContent.Contains("[ERROR]", StringComparison.OrdinalIgnoreCase)
                || logContent.Contains(".ERROR:", StringComparison.OrdinalIgnoreCase)
                || logContent.Contains("CRITICAL", StringComparison.OrdinalIgnoreCase)
                || logContent.Contains(".EMERGENCY:", StringComparison.OrdinalIgnoreCase)
                || logContent.Contains(".ALERT:", StringComparison.OrdinalIgnoreCase)
                || logContent.Contains("Exception", StringComparison.OrdinalIgnoreCase)
                || logContent.Contains("Stack trace", StringComparison.OrdinalIgnoreCase)
                || logContent.Contains("[stacktrace]", StringComparison.OrdinalIgnoreCase);
        }

        internal static (int ArchivedFiles, long ArchivedBytes, long RemainingActiveBytes) ArchiveAndZeroLocalPortalLogsFallback(string logsDirPath)
        {
            if (string.IsNullOrWhiteSpace(logsDirPath) || !Directory.Exists(logsDirPath))
                return (0, 0, 0);

            int archivedFiles = 0;
            long archivedBytes = 0;
            string stamp = DateTime.UtcNow.ToString("yyyy-MM-dd_HHmmss");
            var cutoffUtc = DateTime.UtcNow.AddDays(-30);
            string archDir = Path.Combine(logsDirPath, "archive");

            foreach (string logFile in Directory.GetFiles(logsDirPath, "*.log", SearchOption.TopDirectoryOnly))
            {
                if (McpSecurityGuard.HasReparsePointInAncestry(logFile)) continue;
                var fi = new FileInfo(logFile);
                if (fi.Length <= 0) continue;

                Directory.CreateDirectory(archDir);
                string raw = File.ReadAllText(logFile, Encoding.UTF8);
                string fileName = Path.GetFileName(logFile);
                string archPath = Path.Combine(archDir, $"healed_{stamp}_{fileName}");
                string header = $"# Seelenfunke Portal Log-Archiv | Archiviert: {DateTimeOffset.Now:O} | Quelle: {fileName} ({fi.Length} Bytes){Environment.NewLine}";
                File.WriteAllText(archPath, header + McpSecurityGuard.ScrubSecrets(raw), Utf8NoBom);
                File.WriteAllText(logFile, "", Utf8NoBom);
                archivedFiles++;
                archivedBytes += fi.Length;
            }

            if (Directory.Exists(archDir))
            {
                foreach (string oldArch in Directory.GetFiles(archDir, "healed_*.log", SearchOption.TopDirectoryOnly))
                {
                    if (File.GetLastWriteTimeUtc(oldArch) < cutoffUtc)
                    {
                        try { File.Delete(oldArch); } catch { }
                    }
                }
            }

            long rem = Directory.GetFiles(logsDirPath, "*.log", SearchOption.TopDirectoryOnly)
                .Sum(f => File.Exists(f) ? new FileInfo(f).Length : 0L);

            return (archivedFiles, archivedBytes, rem);
        }

        internal static SentinelHourlyEvaluationResult EvaluateSentinelHourlyState(
            string inboxJsonPath,
            string logsDirPath,
            string targetConversationId,
            string? customLastNotifiedPath = null)
        {
            string safeConvId = McpSecurityGuard.ValidateAgConversationId(targetConversationId);
            int openPoolCount = 0;
            var fingerprintKeys = new List<string>();
            var summaryLines = new List<string>();

            if (!string.IsNullOrWhiteSpace(inboxJsonPath) && File.Exists(inboxJsonPath) && !McpSecurityGuard.HasReparsePointInAncestry(inboxJsonPath))
            {
                try
                {
                    string rawInbox = File.ReadAllText(inboxJsonPath, Encoding.UTF8);
                    if (!string.IsNullOrWhiteSpace(rawInbox))
                    {
                        var root = JsonNode.Parse(rawInbox, documentOptions: StrictJsonParseOptions)?.AsObject();
                        if (root != null)
                        {
                            openPoolCount = root["open_pool_count"]?.GetValue<int>() ?? 0;
                            if (root["items"] is JsonArray itemsArr)
                            {
                                if (itemsArr.Count > openPoolCount)
                                    openPoolCount = itemsArr.Count;

                                foreach (var n in itemsArr)
                                {
                                    if (n is not JsonObject item) continue;
                                    string platform = McpSecurityGuard.ScrubSecrets(item["platform"]?.GetValue<string>() ?? "portal");
                                    string title = McpSecurityGuard.ScrubSecrets(item["title"]?.GetValue<string>() ?? "Unbekannter Befund");
                                    string fp = item["fingerprint"]?.GetValue<string>() ?? "";
                                    if (string.IsNullOrWhiteSpace(fp))
                                    {
                                        fp = Convert.ToHexString(System.Security.Cryptography.SHA256.HashData(
                                            Encoding.UTF8.GetBytes($"{platform}|{title}".ToLowerInvariant()))).ToLowerInvariant();
                                    }
                                    fingerprintKeys.Add($"dossier:{platform}:{fp.ToLowerInvariant()}");
                                    if (summaryLines.Count < 15)
                                    {
                                        summaryLines.Add($"- [{platform}] {title} (Fingerprint: {fp})");
                                    }
                                }
                            }
                        }
                    }
                }
                catch { }
            }

            long totalActiveLogBytes = 0;
            int nonEmptyLogsCount = 0;
            int severeLogsCount = 0;

            if (!string.IsNullOrWhiteSpace(logsDirPath) && Directory.Exists(logsDirPath))
            {
                foreach (string logFile in Directory.GetFiles(logsDirPath, "*.log", SearchOption.TopDirectoryOnly).OrderBy(f => f, StringComparer.OrdinalIgnoreCase))
                {
                    if (McpSecurityGuard.HasReparsePointInAncestry(logFile)) continue;
                    var fi = new FileInfo(logFile);
                    if (fi.Length <= 0) continue;

                    totalActiveLogBytes += fi.Length;
                    nonEmptyLogsCount++;

                    string sample;
                    using (var fs = new FileStream(logFile, FileMode.Open, FileAccess.Read, FileShare.ReadWrite))
                    {
                        int readLen = (int)Math.Min(fi.Length, 262144L);
                        if (fi.Length > readLen)
                            fs.Position = fi.Length - readLen;
                        byte[] buf = new byte[readLen];
                        int actual = fs.Read(buf, 0, readLen);
                        sample = Encoding.UTF8.GetString(buf, 0, actual);
                    }

                    if (ContainsSevereLogEntry(sample))
                    {
                        severeLogsCount++;
                        string logHash = Convert.ToHexString(System.Security.Cryptography.SHA256.HashData(Encoding.UTF8.GetBytes(sample))).ToLowerInvariant();
                        string fileName = Path.GetFileName(logFile);
                        fingerprintKeys.Add($"severelog:{fileName.ToLowerInvariant()}:{logHash}");
                        if (summaryLines.Count < 15)
                        {
                            summaryLines.Add($"- [lokal-log] {fileName} ({fi.Length} Bytes) enthält [ERROR]/CRITICAL/Exception");
                        }
                    }
                }
            }

            // Fall 1: 0 offene Dossiers UND alle Haupt-Logs 0 Bytes -> Stiller Sofort-Exit (0 KI-Token, 0 Chats)
            if (openPoolCount == 0 && totalActiveLogBytes == 0)
            {
                McpSecurityGuard.ClearSentinelLastNotifiedHash(customLastNotifiedPath);
                return new SentinelHourlyEvaluationResult
                {
                    Decision = SentinelHourlyDecision.HealthyZeroBytes,
                    OpenPoolCount = 0,
                    TotalActiveLogBytes = 0,
                    NonEmptyLogFilesCount = 0,
                    SevereLogFilesCount = 0,
                    TargetConversationId = safeConvId
                };
            }

            // Fall 2: 0 offene Dossiers, Haupt-Logs > 0 Bytes aber nur harmlose Info-Zeilen -> Direkt rotieren & 0 Bytes (0 KI-Token, 0 Chats)
            if (openPoolCount == 0 && totalActiveLogBytes > 0 && severeLogsCount == 0)
            {
                McpSecurityGuard.ClearSentinelLastNotifiedHash(customLastNotifiedPath);
                return new SentinelHourlyEvaluationResult
                {
                    Decision = SentinelHourlyDecision.HarmlessInfoLogsOnlyRotate,
                    OpenPoolCount = 0,
                    TotalActiveLogBytes = totalActiveLogBytes,
                    NonEmptyLogFilesCount = nonEmptyLogsCount,
                    SevereLogFilesCount = 0,
                    TargetConversationId = safeConvId
                };
            }

            // Fall 3: Echter ungelöster Fehler -> Deduplizierungs-Hash prüfen & bei neuem Stand an festen Master-Chat senden
            fingerprintKeys.Sort(StringComparer.Ordinal);
            string canonical = string.Join("\n", fingerprintKeys);
            if (string.IsNullOrWhiteSpace(canonical))
            {
                canonical = $"open:{openPoolCount}|severe:{severeLogsCount}";
            }
            string stateHash = Convert.ToHexString(System.Security.Cryptography.SHA256.HashData(Encoding.UTF8.GetBytes(canonical))).ToLowerInvariant();

            string? prevHash = McpSecurityGuard.LoadSentinelLastNotifiedHash(customLastNotifiedPath);
            if (!string.IsNullOrWhiteSpace(prevHash) && string.Equals(prevHash, stateHash, StringComparison.OrdinalIgnoreCase))
            {
                return new SentinelHourlyEvaluationResult
                {
                    Decision = SentinelHourlyDecision.DuplicateErrorAlreadyNotified,
                    OpenPoolCount = openPoolCount,
                    TotalActiveLogBytes = totalActiveLogBytes,
                    NonEmptyLogFilesCount = nonEmptyLogsCount,
                    SevereLogFilesCount = severeLogsCount,
                    StateHash = stateHash,
                    TargetConversationId = safeConvId,
                    SummaryLines = summaryLines
                };
            }

            string prompt = BuildSentinelSelfHealMasterPrompt(openPoolCount, severeLogsCount, summaryLines);
            return new SentinelHourlyEvaluationResult
            {
                Decision = SentinelHourlyDecision.ErrorNeedsMasterChatNotification,
                OpenPoolCount = openPoolCount,
                TotalActiveLogBytes = totalActiveLogBytes,
                NonEmptyLogFilesCount = nonEmptyLogsCount,
                SevereLogFilesCount = severeLogsCount,
                StateHash = stateHash,
                TargetConversationId = safeConvId,
                SelfHealPrompt = prompt,
                SummaryLines = summaryLines
            };
        }

        internal static string BuildSentinelSelfHealMasterPrompt(int openPoolCount, int severeLogFilesCount, IReadOnlyList<string> summaryLines)
        {
            var sb = new StringBuilder();
            sb.AppendLine($"[SEELENFUNKE SELBSTHEILUNGS-WÄCHTER | Offene Dossiers: {openPoolCount} | Lokale Fehler-Logs: {severeLogFilesCount}]");
            sb.AppendLine();
            sb.AppendLine("Der stündliche C#-Wächter (`--sentinel-hourly-guard`) hat Stage, Mini-PC, Windows & Lokal synchronisiert und echte ungelöste Befunde erkannt:");
            foreach (string line in summaryLines)
            {
                sb.AppendLine(line);
            }
            sb.AppendLine();
            sb.AppendLine("Bitte führe jetzt im bestehenden Master-Chat (delegiert an die passenden Orchester-Subagenten) die verbindliche Selbstheilungskette durch:");
            sb.AppendLine("1. Lies `\\\\wsl.localhost\\Ubuntu\\home\\ubuntuxina\\meine-projekte\\seelenfunke\\storage\\app\\private\\antigravity_inbox.json` per read_file (und ggf. betroffene `storage/logs/*.log`).");
            sb.AppendLine("2. SICHERHEITS-LEITPLANKEN: Niemals `production`-Branch (`mein-seelenfunke.de`), `.env`/Keys, `AccountingSeeder.php` oder Live-Zahlungsdaten anfassen; bei Entscheidungsbedarf von Alina ein To-Do über `funkenwerk/tasks_manage` anlegen.");
            sb.AppendLine("3. CODE-FIX & PFLICHT-TEST (100 % GRÜN): Portal (`docker exec -u www-data mein_php_server php artisan test <Test>`), Mini-PC (`funkenwerk-os-linux` `dotnet build`), Android (synchron in WSL + Mirror `C:\\Users\\konta\\AndroidStudioProjects\\seelenfunke-android`, `gradlew testDebugUnitTest` + `--stage-publish-apk`), Windows (`funkenwerk/` `dotnet build`).");
            sb.AppendLine("4. PROTOKOLLIERUNG: Jeden Fix kompakt in Abschnitt 4 von `\\\\wsl.localhost\\Ubuntu\\home\\ubuntuxina\\meine-projekte\\seelenfunke\\Abschlussberichte\\System\\System_Universum_Vollautomatische_Selbstheilung.md` dokumentieren.");
            sb.Append("5. ROLLOUT & 0-BYTE-NULLSTELLUNG: Stage-Fixes via `--stage-deploy --skip-shop-assets` ausrollen und abschließend zwingend `& 'C:\\Users\\konta\\AppData\\Local\\Funkenwerk\\SentinelCli\\FunkenwerkMcp.exe' --sentinel-sync --resolve-all` ausführen, bis `0` offene Dossiers und `0 Bytes` in allen Haupt-Logdateien erreicht sind.");
            return sb.ToString();
        }

        private static async Task<int> RunSentinelHourlyGuardCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

                string? cliConv = GetCliOptionValue(args, "--conversation-id");
                string targetConvId = !string.IsNullOrWhiteSpace(cliConv)
                    ? McpSecurityGuard.ValidateAgConversationId(cliConv)
                    : McpSecurityGuard.LoadAgBridgeConfig().MasterConversationId;

                // a) Stiller C#-Sync (Stage + Mini-PC + Windows + Lokal, 0 KI-Token, 0 neue Chats)
                await RunStageSentinelSyncCliAsync(Array.Empty<string>(), quietOutput: true);

                string inboxPath = Path.Combine(McpSecurityGuard.DefaultLocalStorageAppRoot, "private", "antigravity_inbox.json");
                string localStorageDir = Path.GetDirectoryName(McpSecurityGuard.DefaultLocalStorageAppRoot) ?? "";
                string logsDir = Path.Combine(localStorageDir, "logs");

                // b) Lokale Auswertung nach dem Zero-Log-Prinzip
                var eval = EvaluateSentinelHourlyState(inboxPath, logsDir, targetConvId);

                switch (eval.Decision)
                {
                    case SentinelHourlyDecision.HealthyZeroBytes:
                        Console.WriteLine("Universum gesund (0 offene Dossiers, Haupt-Logs 0 Bytes)");
                        return 0;

                    case SentinelHourlyDecision.HarmlessInfoLogsOnlyRotate:
                        await RunStageSentinelSyncCliAsync(new[] { "--sentinel-sync", "--resolve-all" }, quietOutput: true);
                        Console.WriteLine($"Universum gesund (0 offene Dossiers; {eval.TotalActiveLogBytes} Bytes harmlose Info-Logs archiviert & auf 0 Bytes geleert)");
                        return 0;

                    case SentinelHourlyDecision.DuplicateErrorAlreadyNotified:
                        Console.WriteLine($"Offener Befund ({eval.OpenPoolCount} Dossiers, Hash {eval.StateHash.Substring(0, 12)}) wurde bereits an Master-Chat {eval.TargetConversationId} gemeldet.");
                        return 0;

                    case SentinelHourlyDecision.ErrorNeedsMasterChatNotification:
                    {
                        var (sentOk, exitCode, errOut) = await SendAgentApiMessageSafeAsync(
                            eval.TargetConversationId,
                            eval.SelfHealPrompt!,
                            CancellationToken.None);

                        if (!sentOk || exitCode != 0)
                        {
                            Console.Error.WriteLine($"[Sentinel-Hourly-Guard] Master-Chat Zustellung noch nicht möglich (Exit {exitCode}: {errOut}) - erneuter Versuch beim nächsten Intervall.");
                            return 1;
                        }

                        McpSecurityGuard.SaveSentinelLastNotifiedHash(eval.StateHash, eval.TargetConversationId);
                        Console.WriteLine($"Selbstheilungs-Auftrag ({eval.OpenPoolCount} Dossiers, {eval.SevereLogFilesCount} Fehler-Logs) an festen Master-Chat ({eval.TargetConversationId}) übermittelt.");
                        return 0;
                    }

                    default:
                        return 0;
                }
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Sentinel-Hourly-Guard Fehler: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        // =====================================================================
        // AG DIRECT BRIDGE CLI & SHARED ORCHESTRATION (2-PHASE COMMIT)
        // =====================================================================
        private static string? GetCliOptionValue(string[] args, string flagName)
        {
            string eqPrefix = flagName + "=";
            for (int i = 0; i < args.Length; i++)
            {
                if (args[i].StartsWith(eqPrefix, StringComparison.OrdinalIgnoreCase))
                    return args[i].Substring(eqPrefix.Length);

                if (string.Equals(args[i], flagName, StringComparison.OrdinalIgnoreCase) && i + 1 < args.Length)
                    return args[i + 1];
            }
            return null;
        }

        private static object BuildAgChannelsOverviewObject(AgDirectBridgeConfigDto cfg)
        {
            var channelRows = McpSecurityGuard.FixedAgChannels.Select(ch =>
            {
                bool hasAssigned = cfg.ChannelConversationIds.TryGetValue(ch, out string? cid) && !string.IsNullOrWhiteSpace(cid);
                return new
                {
                    channel = ch,
                    title = McpSecurityGuard.GetAgChannelDisplayTitle(ch),
                    conversation_id = hasAssigned ? cid : null,
                    auto_create_on_next_message = !hasAssigned && ch != "leitung"
                };
            }).ToList();

            return new
            {
                status = "success",
                master_conversation_id = cfg.MasterConversationId,
                poll_interval_seconds = cfg.PollIntervalSeconds,
                channel_conversation_ids = cfg.ChannelConversationIds,
                channels = channelRows,
                special_channels = new[]
                {
                    new { channel = "new", title = McpSecurityGuard.GetAgChannelDisplayTitle("new"), behavior = "opens_dedicated_conversation_per_item" }
                },
                config_path = McpSecurityGuard.AgDirectBridgeConfigPath
            };
        }

        private static object UpdateOrResetAgChannelChat(string rawChannel, string? rawGuidOrReset, bool forceReset = false)
        {
            string safeChannel = McpSecurityGuard.ValidateFixedAgDepartmentChannel(rawChannel);
            var cfg = McpSecurityGuard.LoadAgBridgeConfig();

            string trimmedVal = (rawGuidOrReset ?? "").Trim();
            bool isReset = forceReset
                           || string.IsNullOrEmpty(trimmedVal)
                           || string.Equals(trimmedVal, "reset", StringComparison.OrdinalIgnoreCase)
                           || string.Equals(trimmedVal, "clear", StringComparison.OrdinalIgnoreCase)
                           || string.Equals(trimmedVal, "none", StringComparison.OrdinalIgnoreCase);

            if (isReset)
            {
                if (safeChannel == "leitung")
                {
                    cfg.MasterConversationId = McpSecurityGuard.DefaultMasterConversationId;
                    cfg.ChannelConversationIds["leitung"] = McpSecurityGuard.DefaultMasterConversationId;
                }
                else
                {
                    cfg.ChannelConversationIds.Remove(safeChannel);
                }
            }
            else
            {
                string safeGuid = McpSecurityGuard.ValidateAgConversationId(trimmedVal);
                cfg.ChannelConversationIds[safeChannel] = safeGuid;
                if (safeChannel == "leitung")
                {
                    cfg.MasterConversationId = safeGuid;
                }
            }

            var saved = McpSecurityGuard.SaveAgBridgeConfig(cfg);
            return BuildAgChannelsOverviewObject(saved);
        }

        private static int RunAgListChannelsCli()
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                var cfg = McpSecurityGuard.LoadAgBridgeConfig();
                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(BuildAgChannelsOverviewObject(cfg), JsonPretty)));
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Kanal-Übersicht fehlgeschlagen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        private static int RunAgSetChannelChatCli(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                bool resetFlag = args.Any(a => string.Equals(a, "--reset", StringComparison.OrdinalIgnoreCase));
                string? rawChannel = null;
                string? rawGuid = null;

                int idx = Array.FindIndex(args, a => string.Equals(a, "--ag-set-channel-chat", StringComparison.OrdinalIgnoreCase));
                if (idx >= 0)
                {
                    if (idx + 1 < args.Length) rawChannel = args[idx + 1];
                    if (idx + 2 < args.Length && !args[idx + 2].StartsWith("--", StringComparison.Ordinal))
                        rawGuid = args[idx + 2];
                }
                else
                {
                    rawChannel = GetCliOptionValue(args, "--ag-set-channel-chat");
                    rawGuid = GetCliOptionValue(args, "--conversation-id");
                }

                if (string.IsNullOrWhiteSpace(rawChannel))
                    throw new ArgumentException("Aufruf: --ag-set-channel-chat <leitung|shop|android|minipc|buchhaltung> <guid|reset>");

                if (!resetFlag && rawGuid == null)
                    throw new ArgumentException("Bitte eine Konversations-UUID oder 'reset' angeben: --ag-set-channel-chat <channel> <guid|reset>");

                var result = UpdateOrResetAgChannelChat(rawChannel!, rawGuid, forceReset: resetFlag);
                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(result, JsonPretty)));
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Kanal-Konfiguration fehlgeschlagen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        private static int RunAgSetMasterChatCli(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                string? rawGuid = GetCliOptionValue(args, "--ag-set-master-chat");
                if (string.IsNullOrWhiteSpace(rawGuid))
                    throw new ArgumentException("Bitte eine gültige Konversations-ID übergeben: --ag-set-master-chat <guid>");

                var existing = McpSecurityGuard.LoadAgBridgeConfig();
                existing.MasterConversationId = McpSecurityGuard.ValidateAgConversationId(rawGuid);
                var saved = McpSecurityGuard.SaveAgBridgeConfig(existing);

                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(new
                {
                    status = "success",
                    master_conversation_id = saved.MasterConversationId,
                    poll_interval_seconds = saved.PollIntervalSeconds,
                    channel_conversation_ids = saved.ChannelConversationIds,
                    config_path = McpSecurityGuard.AgDirectBridgeConfigPath
                }, JsonPretty)));
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Master-Chat Konfiguration fehlgeschlagen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        private static async Task<int> RunAgInboxSyncCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                bool peekOnly = args.Any(a => string.Equals(a, "--peek-only", StringComparison.OrdinalIgnoreCase));
                var resultNode = await SyncAgDirectInboxAsync(peekOnly: peekOnly, isDaemonInternal: false);
                Console.WriteLine(McpSecurityGuard.ScrubSecrets(resultNode.ToJsonString(JsonPretty)));
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ AG-Inbox-Sync fehlgeschlagen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        private static async Task<int> RunAgInboxCompleteCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

                string? rawId = null;
                string summary = "Erledigt durch Antigravity.";

                int idx = Array.FindIndex(args, a => string.Equals(a, "--ag-inbox-complete", StringComparison.OrdinalIgnoreCase));
                if (idx >= 0)
                {
                    if (idx + 1 < args.Length)
                        rawId = args[idx + 1];
                    if (idx + 2 < args.Length)
                        summary = string.Join(" ", args.Skip(idx + 2)).Trim();
                }
                else
                {
                    rawId = GetCliOptionValue(args, "--ag-inbox-complete");
                    string? optSum = GetCliOptionValue(args, "--summary");
                    if (!string.IsNullOrWhiteSpace(optSum))
                        summary = optSum!;
                }

                if (string.IsNullOrWhiteSpace(rawId))
                    throw new ArgumentException("Aufruf: --ag-inbox-complete <id> \"<kurze Zusammenfassung>\"");

                var res = await CompleteAgDirectInboxItemAsync(rawId!, summary);
                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(res, JsonPretty)));
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ AG-Inbox-Fertigmeldung fehlgeschlagen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        private static async Task<int> RunAgSidecarDaemonCliAsync(string[] args)
        {
            McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

            string? cliConvId = GetCliOptionValue(args, "--conversation-id");
            if (!string.IsNullOrWhiteSpace(cliConvId))
            {
                cliConvId = McpSecurityGuard.ValidateAgConversationId(cliConvId);
                if (!File.Exists(McpSecurityGuard.AgDirectBridgeConfigPath))
                {
                    try
                    {
                        McpSecurityGuard.SaveAgBridgeConfig(new AgDirectBridgeConfigDto
                        {
                            MasterConversationId = cliConvId,
                            PollIntervalSeconds = McpSecurityGuard.DefaultAgPollIntervalSeconds
                        });
                    }
                    catch { }
                }
            }

            DateTime startupConfigWriteUtc = File.Exists(McpSecurityGuard.AgDirectBridgeConfigPath)
                ? File.GetLastWriteTimeUtc(McpSecurityGuard.AgDirectBridgeConfigPath)
                : DateTime.MinValue;

            using var cts = new CancellationTokenSource();
            Console.CancelKeyPress += (s, e) =>
            {
                e.Cancel = true;
                try { cts.Cancel(); } catch { }
            };
            AppDomain.CurrentDomain.ProcessExit += (s, e) =>
            {
                try { cts.Cancel(); } catch { }
            };

            // Automatic Windows Stage-Laser Failover Relay (~2.5s background loop; 100% passive when Mini-PC 192.168.188.151:22 is awake)
            Task laserFailoverRelayTask = McpStageLaserFailoverRelay.RunDaemonLoopAsync(cts.Token);

            while (!cts.IsCancellationRequested)
            {
                int pollSeconds = McpSecurityGuard.DefaultAgPollIntervalSeconds;
                try
                {
                    var cfg = McpSecurityGuard.LoadAgBridgeConfig();
                    pollSeconds = Math.Clamp(cfg.PollIntervalSeconds, McpSecurityGuard.MinAgPollIntervalSeconds, 3600);

                    DateTime currentCfgWriteUtc = File.Exists(McpSecurityGuard.AgDirectBridgeConfigPath)
                        ? File.GetLastWriteTimeUtc(McpSecurityGuard.AgDirectBridgeConfigPath)
                        : DateTime.MinValue;

                    // Hot-reload if ag_direct_bridge_config.json was updated via --ag-set-master-chat after startup
                    string targetConversationId = (currentCfgWriteUtc > startupConfigWriteUtc || string.IsNullOrWhiteSpace(cliConvId))
                        ? cfg.MasterConversationId
                        : cliConvId!;

                    targetConversationId = McpSecurityGuard.ValidateAgConversationId(targetConversationId);

                    var pendingEntries = new List<(JsonObject Item, bool FromStage, bool FromLocal)>();
                    var byId = new Dictionary<string, int>(StringComparer.Ordinal);

                    // 1a. Peek pending on Stage (0 status mutation, 0 KI-tokens when empty)
                    try
                    {
                        string stageRaw = await McpSecurityGuard.ExecuteStageAgInboxCommandAsync("peek_pending", isDaemonInternal: true);
                        var stageObj = ParseJsonObjectFromCliOutput(stageRaw);
                        if (stageObj?["items"] is JsonArray stageItems)
                        {
                            foreach (var node in stageItems)
                            {
                                if (node is not JsonObject itemObj) continue;
                                string? id = itemObj["id"]?.GetValue<string>();
                                if (string.IsNullOrWhiteSpace(id)) continue;
                                string safeId = McpSecurityGuard.ValidateAgInboxSingleId(id);
                                byId[safeId] = pendingEntries.Count;
                                pendingEntries.Add(((JsonObject)itemObj.DeepClone(), FromStage: true, FromLocal: false));
                            }
                        }
                    }
                    catch (Exception stageEx)
                    {
                        Console.Error.WriteLine($"[AG-Sidecar] Stage-Poll Warnung: {McpSecurityGuard.ScrubSecrets(stageEx.Message)}");
                    }

                    // 1b. Peek local antigravity_direct_inbox.json (0 process spawns when idle/absent)
                    try
                    {
                        foreach (var localItem in ReadLocalDirectInboxItems(onlyPending: true))
                        {
                            string? id = localItem["id"]?.GetValue<string>();
                            if (string.IsNullOrWhiteSpace(id)) continue;
                            string safeId = McpSecurityGuard.ValidateAgInboxSingleId(id);
                            if (byId.TryGetValue(safeId, out int existingIdx))
                            {
                                var cur = pendingEntries[existingIdx];
                                pendingEntries[existingIdx] = (cur.Item, cur.FromStage, FromLocal: true);
                            }
                            else
                            {
                                byId[safeId] = pendingEntries.Count;
                                pendingEntries.Add((localItem, FromStage: false, FromLocal: true));
                            }
                        }
                    }
                    catch { }

                    // 2. Deliver each pending item with strict 2-phase commit
                    foreach (var (itemObj, fromStage, fromLocal) in pendingEntries)
                    {
                        if (cts.IsCancellationRequested) break;

                        string safeId = McpSecurityGuard.ValidateAgInboxSingleId(itemObj["id"]?.GetValue<string>());
                        string? savedMediaPath = null;
                        bool hasAttachment = itemObj["has_attachment"]?.GetValue<bool>() == true;
                        string? b64 = itemObj["attachment_base64"]?.GetValue<string>();

                        if (hasAttachment && !string.IsNullOrWhiteSpace(b64))
                        {
                            savedMediaPath = McpSecurityGuard.SaveAgDirectAttachmentSafe(
                                safeId,
                                itemObj["attachment_name"]?.GetValue<string>(),
                                itemObj["attachment_mime"]?.GetValue<string>(),
                                b64!);
                        }
                        else if (hasAttachment)
                        {
                            string? existingLocalMedia = itemObj["local_attachment_path"]?.GetValue<string>();
                            if (!string.IsNullOrWhiteSpace(existingLocalMedia) && File.Exists(existingLocalMedia))
                                savedMediaPath = existingLocalMedia;
                        }

                        string title = itemObj["title"]?.GetValue<string>() ?? "";
                        string? body = itemObj["body"]?.GetValue<string>();
                        string? execMode = itemObj["execution_mode"]?.GetValue<string>();
                        string channel = McpSecurityGuard.NormalizeAgChannel(itemObj["channel"]?.GetValue<string>());
                        string channelTitle = McpSecurityGuard.GetAgChannelDisplayTitle(channel);
                        var checklist = ExtractChecklistStrings(itemObj["checklist"]);

                        string promptText = McpSecurityGuard.BuildAgDirectMasterPrompt(
                            safeId,
                            title,
                            body,
                            checklist,
                            execMode,
                            savedMediaPath,
                            channel);

                        // Phase 1: Route to target channel conversation via agentapi (UseShellExecute = false, ArgumentList)
                        bool sentOk;
                        int exitCode;
                        string errOut;
                        string deliveredTargetDesc;

                        if (channel == "new")
                        {
                            string customTitle = string.IsNullOrWhiteSpace(title) ? $"Auftrag {safeId}" : title;
                            var newConvRes = await StartNewAgentConversationSafeAsync(customTitle, promptText, captureUuid: true, cts.Token);
                            sentOk = newConvRes.Success;
                            exitCode = newConvRes.ExitCode;
                            errOut = newConvRes.ErrorSummary;
                            deliveredTargetDesc = $"Einzel-Chat '{McpSecurityGuard.SanitizeAgConversationTitle(customTitle)}' ({newConvRes.ExtractedUuid ?? "neu"})";
                        }
                        else if (channel == "leitung")
                        {
                            (sentOk, exitCode, errOut) = await SendAgentApiMessageSafeAsync(targetConversationId, promptText, cts.Token);
                            deliveredTargetDesc = $"Kanal 'Leitung' ({targetConversationId})";
                        }
                        else
                        {
                            // Fixed department channels: shop, android, minipc, buchhaltung
                            cfg = McpSecurityGuard.LoadAgBridgeConfig();
                            bool hasExistingDeptUuid = cfg.ChannelConversationIds.TryGetValue(channel, out string? deptUuid)
                                                       && !string.IsNullOrWhiteSpace(deptUuid);

                            if (hasExistingDeptUuid)
                            {
                                string validDeptUuid = McpSecurityGuard.ValidateAgConversationId(deptUuid);
                                (sentOk, exitCode, errOut) = await SendAgentApiMessageSafeAsync(validDeptUuid, promptText, cts.Token);
                                deliveredTargetDesc = $"Abteilungs-Kanal '{channelTitle}' ({validDeptUuid})";

                                if (!sentOk || exitCode != 0)
                                {
                                    // Fallback if department chat was deleted by user: attempt clean auto-recreation
                                    var recreateRes = await StartNewAgentConversationSafeAsync(channelTitle, promptText, captureUuid: true, cts.Token);
                                    if (recreateRes.Success && recreateRes.ExitCode == 0)
                                    {
                                        sentOk = true;
                                        exitCode = 0;
                                        errOut = "";
                                        if (!string.IsNullOrWhiteSpace(recreateRes.ExtractedUuid))
                                        {
                                            cfg.ChannelConversationIds[channel] = recreateRes.ExtractedUuid!;
                                            McpSecurityGuard.SaveAgBridgeConfig(cfg);
                                            startupConfigWriteUtc = DateTime.MinValue;
                                        }
                                        deliveredTargetDesc = $"Neu angelegter Abteilungs-Kanal '{channelTitle}' ({recreateRes.ExtractedUuid ?? "auto"})";
                                    }
                                }
                            }
                            else
                            {
                                var createRes = await StartNewAgentConversationSafeAsync(channelTitle, promptText, captureUuid: true, cts.Token);
                                sentOk = createRes.Success;
                                exitCode = createRes.ExitCode;
                                errOut = createRes.ErrorSummary;
                                if (sentOk && exitCode == 0 && !string.IsNullOrWhiteSpace(createRes.ExtractedUuid))
                                {
                                    cfg.ChannelConversationIds[channel] = createRes.ExtractedUuid!;
                                    McpSecurityGuard.SaveAgBridgeConfig(cfg);
                                    startupConfigWriteUtc = DateTime.MinValue;
                                }
                                deliveredTargetDesc = $"Erstmalig angelegter Abteilungs-Kanal '{channelTitle}' ({createRes.ExtractedUuid ?? "auto"})";
                            }
                        }

                        if (!sentOk || exitCode != 0)
                        {
                            Console.Error.WriteLine($"[AG-Sidecar] Zustellung für '{safeId}' (Kanal '{channel}') noch nicht möglich (Exit {exitCode}: {errOut}) - bleibt pending für nächsten Zyklus.");
                            continue;
                        }

                        // Phase 2 (ONLY on ExitCode == 0): Mark delivered on Stage and locally
                        if (fromStage)
                        {
                            await McpSecurityGuard.ExecuteStageAgInboxCommandAsync("mark_delivered", itemId: safeId, isDaemonInternal: true);
                        }

                        var sanitizedCopy = (JsonObject)itemObj.DeepClone();
                        sanitizedCopy.Remove("attachment_base64");
                        sanitizedCopy["channel"] = channel;
                        sanitizedCopy["status"] = "delivered";
                        sanitizedCopy["delivered_at"] = DateTimeOffset.Now.ToString("O");
                        if (!string.IsNullOrWhiteSpace(savedMediaPath))
                            sanitizedCopy["local_attachment_path"] = savedMediaPath;

                        await SyncItemsToLocalPortalBestEffortAsync(new JsonArray(sanitizedCopy), markDeliveredId: fromLocal ? safeId : null);
                        await UpsertOrUpdateLocalDirectInboxJsonAsync(sanitizedCopy, safeId, "delivered", null);

                        Console.Error.WriteLine($"[AG-Sidecar] Direktnachricht '{safeId}' verlustfrei an {deliveredTargetDesc} zugestellt.");
                    }
                }
                catch (OperationCanceledException) when (cts.IsCancellationRequested)
                {
                    break;
                }
                catch (Exception cycleEx)
                {
                    Console.Error.WriteLine($"[AG-Sidecar] Zyklus-Fehler: {McpSecurityGuard.ScrubSecrets(cycleEx.Message)}");
                }

                try
                {
                    await Task.Delay(TimeSpan.FromSeconds(pollSeconds), cts.Token);
                }
                catch (OperationCanceledException)
                {
                    break;
                }
            }

            try { await laserFailoverRelayTask; } catch { }
            return 0;
        }

        private static async Task<int> RunStageLaserRelayOnceCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                bool captureCamera = args.Any(a => string.Equals(a, "--capture-camera", StringComparison.OrdinalIgnoreCase));
                var report = await McpStageLaserFailoverRelay.RunSingleFailoverCycleAsync(
                    sendExplicitHeartbeat: true,
                    runDiagnosticCameraTest: captureCamera,
                    forceRefreshProbes: true);

                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(report, JsonPretty)));
                return string.Equals(report.Status, "ok", StringComparison.OrdinalIgnoreCase) ? 0 : 1;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Stage-Laser-Failover-Relay Fehler: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        private static async Task<(bool Success, int ExitCode, string ErrorSummary, string? ExtractedUuid)> StartNewAgentConversationSafeAsync(
            string title,
            string promptText,
            bool captureUuid,
            CancellationToken ct)
        {
            try
            {
                HashSet<string>? preExistingBrainDirs = captureUuid
                    ? McpSecurityGuard.SnapshotAntigravityBrainUuidFolders()
                    : null;

                var psi = McpSecurityGuard.BuildAgentApiNewConversationStartInfo(title, promptText);
                using var proc = Process.Start(psi);
                if (proc == null)
                    return (false, -1, "agentapi new-conversation Prozess konnte nicht gestartet werden.", null);

                using var timeoutCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
                timeoutCts.CancelAfter(TimeSpan.FromSeconds(30));

                var outTask = proc.StandardOutput.ReadToEndAsync(timeoutCts.Token);
                var errTask = proc.StandardError.ReadToEndAsync(timeoutCts.Token);
                await proc.WaitForExitAsync(timeoutCts.Token);
                string stdOut = await outTask;
                string stdErr = await errTask;

                string? extractedUuid = null;
                if (proc.ExitCode == 0 && captureUuid)
                {
                    extractedUuid = McpSecurityGuard.ExtractConversationUuid(stdOut, stdErr, preExistingBrainDirs);
                    if (string.IsNullOrWhiteSpace(extractedUuid))
                    {
                        // Give Antigravity up to 1.8s to materialize %USERPROFILE%\.gemini\antigravity\brain\<uuid>
                        for (int retry = 0; retry < 6 && string.IsNullOrWhiteSpace(extractedUuid); retry++)
                        {
                            await Task.Delay(300, ct);
                            extractedUuid = McpSecurityGuard.ExtractConversationUuid(null, null, preExistingBrainDirs);
                        }
                    }
                }

                string combinedErr = McpSecurityGuard.ScrubSecrets((stdErr + " " + stdOut).Trim());
                if (combinedErr.Length > 300) combinedErr = combinedErr.Substring(0, 300);
                return (proc.ExitCode == 0, proc.ExitCode, combinedErr, extractedUuid);
            }
            catch (Exception ex)
            {
                return (false, -1, McpSecurityGuard.ScrubSecrets(ex.Message), null);
            }
        }

        private static async Task<(bool Success, int ExitCode, string ErrorSummary)> SendAgentApiMessageSafeAsync(
            string targetConversationId,
            string promptText,
            CancellationToken ct)
        {
            try
            {
                var psi = McpSecurityGuard.BuildAgentApiSendMessageStartInfo(targetConversationId, promptText);
                using var proc = Process.Start(psi);
                if (proc == null)
                    return (false, -1, "agentapi Prozess konnte nicht gestartet werden.");

                using var timeoutCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
                timeoutCts.CancelAfter(TimeSpan.FromSeconds(30));

                var outTask = proc.StandardOutput.ReadToEndAsync(timeoutCts.Token);
                var errTask = proc.StandardError.ReadToEndAsync(timeoutCts.Token);
                await proc.WaitForExitAsync(timeoutCts.Token);
                string stdOut = await outTask;
                string stdErr = await errTask;

                string combinedErr = McpSecurityGuard.ScrubSecrets((stdErr + " " + stdOut).Trim());
                if (combinedErr.Length > 300) combinedErr = combinedErr.Substring(0, 300);
                return (proc.ExitCode == 0, proc.ExitCode, combinedErr);
            }
            catch (Exception ex)
            {
                return (false, -1, McpSecurityGuard.ScrubSecrets(ex.Message));
            }
        }

        internal static List<string> ExtractChecklistStrings(JsonNode? checklistNode)
        {
            var list = new List<string>();
            if (checklistNode is not JsonArray arr)
                return list;

            foreach (var elem in arr)
            {
                if (elem == null) continue;
                if (elem is JsonValue val && val.TryGetValue<string>(out string? s) && !string.IsNullOrWhiteSpace(s))
                {
                    list.Add(s.Trim());
                }
                else if (elem is JsonObject obj)
                {
                    string? txt = (obj["text"] ?? obj["title"] ?? obj["label"] ?? obj["item"])?.GetValue<string>();
                    if (!string.IsNullOrWhiteSpace(txt))
                    {
                        bool done = (obj["done"] ?? obj["checked"] ?? obj["completed"])?.GetValue<bool>() == true;
                        list.Add(done ? $"[x] {txt!.Trim()}" : txt!.Trim());
                    }
                }
            }
            return list;
        }

        private static JsonObject? ParseJsonObjectFromCliOutput(string? raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return null;
            int start = raw.IndexOf('{');
            int end = raw.LastIndexOf('}');
            if (start < 0 || end <= start) return null;
            string slice = raw.Substring(start, end - start + 1);
            return JsonNode.Parse(slice, documentOptions: StrictJsonParseOptions)?.AsObject();
        }

        private static void ExtractAttachmentsAndSanitizeItems(JsonArray itemsArray)
        {
            foreach (var node in itemsArray)
            {
                if (node is not JsonObject itemObj) continue;
                string? rawId = itemObj["id"]?.GetValue<string>();
                if (string.IsNullOrWhiteSpace(rawId)) continue;

                string safeId = McpSecurityGuard.ValidateAgInboxSingleId(rawId);
                bool hasAttachment = itemObj["has_attachment"]?.GetValue<bool>() == true;
                string? b64 = itemObj["attachment_base64"]?.GetValue<string>();

                if (hasAttachment && !string.IsNullOrWhiteSpace(b64))
                {
                    string savedPath = McpSecurityGuard.SaveAgDirectAttachmentSafe(
                        safeId,
                        itemObj["attachment_name"]?.GetValue<string>(),
                        itemObj["attachment_mime"]?.GetValue<string>(),
                        b64!);
                    itemObj["local_attachment_path"] = savedPath;
                }

                itemObj.Remove("attachment_base64");

                if (itemObj["title"] != null)
                    itemObj["title"] = McpSecurityGuard.ScrubSecrets(itemObj["title"]!.GetValue<string>());
                if (itemObj["body"] != null && itemObj["body"]!.GetValueKind() == JsonValueKind.String)
                    itemObj["body"] = McpSecurityGuard.ScrubSecrets(itemObj["body"]!.GetValue<string>());
            }
        }

        private static async Task<JsonObject> SyncAgDirectInboxAsync(bool peekOnly, bool isDaemonInternal)
        {
            string stageOp = peekOnly ? "peek_pending" : "pull_pending";
            JsonObject? rootObj = null;
            string? stageWarning = null;

            try
            {
                string rawStage = await McpSecurityGuard.ExecuteStageAgInboxCommandAsync(stageOp, isDaemonInternal: isDaemonInternal);
                rootObj = ParseJsonObjectFromCliOutput(rawStage);
                if (rootObj == null)
                    throw new InvalidOperationException("Stage lieferte kein gültiges JSON-Objekt für system:ag-inbox.");
            }
            catch (Exception ex)
            {
                if (!File.Exists(McpSecurityGuard.DefaultLocalDirectInboxPath))
                    throw;
                stageWarning = McpSecurityGuard.ScrubSecrets(ex.Message);
                rootObj = new JsonObject
                {
                    ["status"] = "success",
                    ["source"] = "local_fallback",
                    ["stage_warning"] = stageWarning,
                    ["items"] = new JsonArray()
                };
            }

            var itemsArray = rootObj["items"]?.AsArray() ?? new JsonArray();
            if (rootObj["items"] == null)
                rootObj["items"] = itemsArray;

            var seenIds = new HashSet<string>(StringComparer.Ordinal);
            foreach (var n in itemsArray)
            {
                string? id = n?["id"]?.GetValue<string>();
                if (!string.IsNullOrWhiteSpace(id))
                    seenIds.Add(id!);
            }

            // Merge any local pending items from storage/app/private/antigravity_direct_inbox.json
            foreach (var localItem in ReadLocalDirectInboxItems(onlyPending: true))
            {
                string? id = localItem["id"]?.GetValue<string>();
                if (string.IsNullOrWhiteSpace(id) || !seenIds.Add(id!))
                    continue;

                string safeId = McpSecurityGuard.ValidateAgInboxSingleId(id);
                if (!peekOnly)
                {
                    localItem["status"] = "delivered";
                    localItem["delivered_at"] = DateTimeOffset.Now.ToString("O");
                    await UpsertOrUpdateLocalDirectInboxJsonAsync(null, safeId, "delivered", null);
                    await TryRunLocalDockerAgInboxAsync($"--mark-delivered={safeId}");
                }
                itemsArray.Add(localItem);
            }

            ExtractAttachmentsAndSanitizeItems(itemsArray);
            rootObj["count"] = itemsArray.Count;

            if (itemsArray.Count > 0)
            {
                await SyncItemsToLocalPortalBestEffortAsync(itemsArray, markDeliveredId: null);
                foreach (var n in itemsArray)
                {
                    if (n is JsonObject jo && jo["id"] != null)
                    {
                        string sid = McpSecurityGuard.ValidateAgInboxSingleId(jo["id"]!.GetValue<string>());
                        await UpsertOrUpdateLocalDirectInboxJsonAsync(jo, sid, jo["status"]?.GetValue<string>() ?? (peekOnly ? "pending" : "delivered"), null);
                    }
                }
            }

            return rootObj;
        }

        private static async Task<JsonObject> ListAgDirectInboxAsync()
        {
            string rawStage = await McpSecurityGuard.ExecuteStageAgInboxCommandAsync("list", isDaemonInternal: false);
            var rootObj = ParseJsonObjectFromCliOutput(rawStage)
                ?? throw new InvalidOperationException("Stage lieferte kein gültiges JSON für system:ag-inbox --list.");

            if (rootObj["items"] is JsonArray itemsArr)
            {
                ExtractAttachmentsAndSanitizeItems(itemsArr);
            }

            var cfg = McpSecurityGuard.LoadAgBridgeConfig();
            rootObj["bridge_config"] = JsonSerializer.SerializeToNode(cfg, JsonCompact);
            return rootObj;
        }

        private static async Task<object> CompleteAgDirectInboxItemAsync(string rawId, string? rawSummary)
        {
            string safeId = McpSecurityGuard.ValidateAgInboxSingleId(rawId);
            string cleanSummary = McpSecurityGuard.ScrubSecrets((rawSummary ?? "").Replace('\0', ' ').Trim());
            if (string.IsNullOrWhiteSpace(cleanSummary))
                cleanSummary = "Erledigt durch Antigravity.";
            if (cleanSummary.Length > 4000)
                cleanSummary = cleanSummary.Substring(0, 4000);

            string summaryB64 = Convert.ToBase64String(Encoding.UTF8.GetBytes(cleanSummary));

            bool stageCompleted = false;
            string? stageError = null;
            try
            {
                await McpSecurityGuard.ExecuteStageAgInboxCommandAsync("complete", itemId: safeId, summary: cleanSummary, isDaemonInternal: false);
                stageCompleted = true;
            }
            catch (Exception ex)
            {
                stageError = McpSecurityGuard.ScrubSecrets(ex.Message);
            }

            bool localDockerCompleted = await TryRunLocalDockerAgInboxAsync($"--complete={safeId}", $"--summary-b64={summaryB64}", "--ignore-missing");
            bool localJsonUpdated = await UpsertOrUpdateLocalDirectInboxJsonAsync(null, safeId, "completed", cleanSummary);

            if (!stageCompleted && !localDockerCompleted && !localJsonUpdated)
            {
                throw new InvalidOperationException($"Fertigmeldung für '{safeId}' konnte weder auf Stage ({stageError}) noch lokal gespeichert werden.");
            }

            return new
            {
                status = "success",
                id = safeId,
                summary = cleanSummary,
                stage_completed = stageCompleted,
                stage_warning = stageError,
                local_docker_completed = localDockerCompleted,
                local_file_updated = localJsonUpdated
            };
        }

        private static List<JsonObject> ReadLocalDirectInboxItems(bool onlyPending)
        {
            var results = new List<JsonObject>();
            string path = McpSecurityGuard.DefaultLocalDirectInboxPath;
            if (!File.Exists(path)) return results;

            try
            {
                var fi = new FileInfo(path);
                if (fi.Length < 2 || fi.Length > 32L * 1024L * 1024L) return results;
                if (McpSecurityGuard.HasReparsePointInAncestry(path)) return results;

                string raw = File.ReadAllText(path, Encoding.UTF8);
                if (string.IsNullOrWhiteSpace(raw)) return results;

                var parsed = JsonNode.Parse(raw, documentOptions: StrictJsonParseOptions);
                JsonArray? arr = parsed as JsonArray ?? parsed?["items"] as JsonArray;
                if (arr == null) return results;

                foreach (var node in arr)
                {
                    if (node is not JsonObject obj) continue;
                    string status = obj["status"]?.GetValue<string>() ?? "pending";
                    if (!onlyPending || string.Equals(status, "pending", StringComparison.OrdinalIgnoreCase))
                    {
                        results.Add((JsonObject)obj.DeepClone());
                    }
                }
            }
            catch { }

            return results;
        }

        private static async Task<bool> UpsertOrUpdateLocalDirectInboxJsonAsync(
            JsonObject? itemSnapshot,
            string safeId,
            string newStatus,
            string? completedSummary)
        {
            string path = McpSecurityGuard.DefaultLocalDirectInboxPath;
            try
            {
                string? dir = Path.GetDirectoryName(path);
                if (string.IsNullOrWhiteSpace(dir) || !Directory.Exists(McpSecurityGuard.DefaultLocalStorageAppRoot))
                    return false;

                Directory.CreateDirectory(dir);
                if (McpSecurityGuard.HasReparsePointInAncestry(path))
                    return false;

                JsonNode rootNode;
                JsonArray itemsArr;
                if (File.Exists(path))
                {
                    string existingRaw = await File.ReadAllTextAsync(path, Encoding.UTF8);
                    var parsed = string.IsNullOrWhiteSpace(existingRaw)
                        ? null
                        : JsonNode.Parse(existingRaw, documentOptions: StrictJsonParseOptions);

                    if (parsed is JsonArray topArr)
                    {
                        rootNode = topArr;
                        itemsArr = topArr;
                    }
                    else if (parsed is JsonObject topObj)
                    {
                        rootNode = topObj;
                        itemsArr = topObj["items"] as JsonArray ?? new JsonArray();
                        topObj["items"] = itemsArr;
                    }
                    else
                    {
                        itemsArr = new JsonArray();
                        rootNode = new JsonObject { ["items"] = itemsArr };
                    }
                }
                else
                {
                    if (itemSnapshot == null)
                        return false;
                    itemsArr = new JsonArray();
                    rootNode = new JsonObject { ["items"] = itemsArr };
                }

                JsonObject? found = null;
                foreach (var n in itemsArr)
                {
                    if (n is JsonObject o && string.Equals(o["id"]?.GetValue<string>(), safeId, StringComparison.Ordinal))
                    {
                        found = o;
                        break;
                    }
                }

                if (found == null)
                {
                    if (itemSnapshot == null)
                        return false;
                    found = (JsonObject)itemSnapshot.DeepClone();
                    found.Remove("attachment_base64");
                    itemsArr.Add(found);
                }

                found["status"] = newStatus;
                string nowIso = DateTimeOffset.Now.ToString("O");
                if (newStatus == "delivered")
                    found["delivered_at"] = nowIso;
                if (newStatus == "completed")
                {
                    found["completed_at"] = nowIso;
                    if (completedSummary != null)
                        found["ag_response_summary"] = completedSummary;
                }

                if (rootNode is JsonObject rootObj)
                    rootObj["updated_at"] = nowIso;

                string outJson = rootNode.ToJsonString(JsonPretty);
                try
                {
                    await File.WriteAllTextAsync(path, outJson, Utf8NoBom);
                }
                catch (UnauthorizedAccessException)
                {
                    if (File.Exists(path)) File.Delete(path);
                    await File.WriteAllTextAsync(path, outJson, Utf8NoBom);
                }
                return true;
            }
            catch
            {
                return false;
            }
        }

        private static async Task SyncItemsToLocalPortalBestEffortAsync(JsonArray sanitizedItems, string? markDeliveredId)
        {
            try
            {
                var payloadObj = new JsonObject
                {
                    ["items"] = sanitizedItems.DeepClone()
                };
                string payloadJson = payloadObj.ToJsonString(JsonCompact);
                if (payloadJson.Length <= 90_000)
                {
                    string b64 = Convert.ToBase64String(Encoding.UTF8.GetBytes(payloadJson));
                    await TryRunLocalDockerAgInboxAsync($"--import-b64={b64}");
                }

                if (!string.IsNullOrWhiteSpace(markDeliveredId))
                {
                    string safeId = McpSecurityGuard.ValidateAgInboxMarkDeliveredIds(markDeliveredId);
                    await TryRunLocalDockerAgInboxAsync($"--mark-delivered={safeId}");
                }
            }
            catch { }
        }

        private static async Task<bool> TryRunLocalDockerAgInboxAsync(params string[] artisanFlags)
        {
            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = "wsl.exe",
                    UseShellExecute = false,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    CreateNoWindow = true
                };
                psi.ArgumentList.Add("-d");
                psi.ArgumentList.Add("Ubuntu");
                psi.ArgumentList.Add("--");
                psi.ArgumentList.Add("docker");
                psi.ArgumentList.Add("exec");
                psi.ArgumentList.Add("-u");
                psi.ArgumentList.Add("www-data");
                psi.ArgumentList.Add("mein_php_server");
                psi.ArgumentList.Add("php");
                psi.ArgumentList.Add("/var/www/html/artisan");
                psi.ArgumentList.Add("system:ag-inbox");
                foreach (string flag in artisanFlags)
                {
                    psi.ArgumentList.Add(flag);
                }

                using var proc = Process.Start(psi);
                if (proc == null) return false;

                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(15));
                var outTask = proc.StandardOutput.ReadToEndAsync(cts.Token);
                var errTask = proc.StandardError.ReadToEndAsync(cts.Token);
                await proc.WaitForExitAsync(cts.Token);
                await Task.WhenAll(outTask, errTask);
                return proc.ExitCode == 0;
            }
            catch
            {
                return false;
            }
        }

        private static string? TryReadCliOption(string[] args, string flagName)
        {
            string eqPrefix = flagName + "=";
            for (int i = 0; i < args.Length; i++)
            {
                if (args[i].StartsWith(eqPrefix, StringComparison.OrdinalIgnoreCase))
                    return args[i].Substring(eqPrefix.Length).Trim();

                if (string.Equals(args[i], flagName, StringComparison.OrdinalIgnoreCase) &&
                    i + 1 < args.Length &&
                    !args[i + 1].StartsWith("--", StringComparison.Ordinal))
                {
                    return args[i + 1].Trim();
                }
            }
            return null;
        }

        public static int RunTresorListCli()
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                var res = McpTresorBroker.ListApprovedEntries();
                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(res, JsonPretty)));
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Tresor-Broker Fehler: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        public static async Task<int> RunTresorBrowserLoginCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

                string? titleOrId = TryReadCliOption(args, "--tresor-browser-login")
                                 ?? TryReadCliOption(args, "--entry");

                if (string.IsNullOrWhiteSpace(titleOrId))
                    throw new ArgumentException("Bitte <titleOrId> angeben: --tresor-browser-login <titleOrId> [--submit] [--totp-only]");

                bool submit = args.Any(a => string.Equals(a, "--submit", StringComparison.OrdinalIgnoreCase));
                bool totpOnly = args.Any(a => string.Equals(a, "--totp-only", StringComparison.OrdinalIgnoreCase));

                int? portOverride = null;
                string? rawPort = TryReadCliOption(args, "--port");
                if (!string.IsNullOrWhiteSpace(rawPort) && int.TryParse(rawPort, out int pVal))
                    portOverride = pVal;

                var res = await McpTresorBroker.BrowserLoginAsync(titleOrId, submit: submit, totpOnly: totpOnly, cdpPortOverride: portOverride);
                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(res, JsonPretty)));
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Tresor Browser-Login abgebrochen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        public static async Task<int> RunMittwaldApiBrokerCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

                string? preset = TryReadCliOption(args, "--mittwald-api")
                              ?? TryReadCliOption(args, "--preset");

                if (string.IsNullOrWhiteSpace(preset))
                    throw new ArgumentException("Bitte <preset> angeben: --mittwald-api <preset> [--entry <titleOrId>] [--project-id <id>] [--app-id <id>] [--cronjob-id <id>]");

                string? entry = TryReadCliOption(args, "--entry");
                string? projectId = TryReadCliOption(args, "--project-id");
                string? appId = TryReadCliOption(args, "--app-id");
                string? cronjobId = TryReadCliOption(args, "--cronjob-id");

                var res = await McpTresorBroker.ExecuteMittwaldApiPresetAsync(
                    preset: preset,
                    entryTitleOrId: entry,
                    projectId: projectId,
                    appId: appId,
                    cronjobId: cronjobId);

                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(res, JsonPretty)));
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Mittwald API-Broker abgebrochen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        public static async Task<int> RunMittwaldPrepareLiveCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

                foreach (string rawArg in args)
                {
                    if (rawArg.Contains("steinhauer-invest", StringComparison.OrdinalIgnoreCase)
                        || rawArg.Contains("a-s4sdym", StringComparison.OrdinalIgnoreCase)
                        || rawArg.Contains("2be54dff-9651-416e-8aba-79a5b63749ff", StringComparison.OrdinalIgnoreCase)
                        || rawArg.Contains("mysql_fg9zlg", StringComparison.OrdinalIgnoreCase))
                    {
                        McpSecurityGuard.AppendAuditLog("mittwald_prepare_live", "cli_arg_guard", "DENY:steinhauer_invest_blocked");
                        throw new UnauthorizedAccessException("HARTE SICHERHEITSSPERRE: 'steinhauer-invest' (a-s4sdym / mysql_fg9zlg) ist überall zu 100 % gesperrt!");
                    }
                }

                string step = TryReadCliOption(args, "--step")
                           ?? TryReadCliOption(args, "--mittwald-prepare-live")
                           ?? "all";
                string? entry = TryReadCliOption(args, "--entry");

                var summary = await McpTresorBroker.ExecutePinnedSeelenfunkeLivePrepareAsync(
                    step: step,
                    entryTitleOrId: entry);

                Console.WriteLine(McpSecurityGuard.ScrubSecrets(summary.ToJsonString(JsonPretty)));
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Mittwald Live-Vorbereitung abgebrochen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        public static async Task<int> RunStripeApiBrokerCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

                string? preset = TryReadCliOption(args, "--stripe-api")
                              ?? TryReadCliOption(args, "--preset");

                if (string.IsNullOrWhiteSpace(preset))
                    throw new ArgumentException("Bitte <preset> angeben: --stripe-api <preset> [--entry <titleOrId>] [--object-id <id>] [--limit <1..50>]");

                string? entry = TryReadCliOption(args, "--entry");
                string? objectId = TryReadCliOption(args, "--object-id");
                int limit = 10;
                string? rawLimit = TryReadCliOption(args, "--limit");
                if (!string.IsNullOrWhiteSpace(rawLimit) && int.TryParse(rawLimit, out int parsedLim))
                    limit = parsedLim;

                var res = await McpTresorBroker.ExecuteStripeApiPresetAsync(
                    preset: preset,
                    entryTitleOrId: entry,
                    objectId: objectId,
                    limit: limit);

                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(res, JsonPretty)));
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Stripe API-Broker abgebrochen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        public static async Task RunStdioServerLoopAsync(Stream stdin, Stream stdout, CancellationToken ct = default)
        {
            var frameReader = new JsonRpcByteFrameReader(stdin);

            while (!ct.IsCancellationRequested)
            {
                JsonRpcFrameResult frame;
                try
                {
                    frame = await frameReader.ReadNextFrameAsync(ct);
                }
                catch (OperationCanceledException)
                {
                    break;
                }
                catch (Exception ex)
                {
                    string errJson = BuildErrorResponse(null, -32600, ex.Message);
                    await WriteJsonRpcResponseAsync(stdout, errJson, false);
                    continue;
                }

                if (frame.IsEof)
                    break;

                if (frame.ProtocolError != null)
                {
                    string errJson = BuildErrorResponse(null, -32600, frame.ProtocolError);
                    await WriteJsonRpcResponseAsync(stdout, errJson, frame.UseContentLength);
                    continue;
                }

                if (string.IsNullOrWhiteSpace(frame.RawJson))
                    continue;

                string? responseJson = await ProcessJsonRpcMessageAsync(frame.RawJson);
                if (responseJson != null)
                {
                    await WriteJsonRpcResponseAsync(stdout, responseJson, frame.UseContentLength);
                }
            }
        }

        public static Task<JsonRpcFrameResult> ReadNextJsonRpcMessageAsync(Stream stdin, CancellationToken ct = default)
        {
            var reader = new JsonRpcByteFrameReader(stdin);
            return reader.ReadNextFrameAsync(ct);
        }

        private static async Task<string?> ReadBoundedLineAsync(StreamReader reader, int maxChars)
        {
            var sb = new StringBuilder(256);
            char[] one = new char[1];
            bool anyRead = false;

            while (true)
            {
                int n = await reader.ReadAsync(one, 0, 1);
                if (n == 0)
                {
                    return anyRead ? sb.ToString() : null;
                }
                anyRead = true;
                char c = one[0];
                if (c == '\n')
                    break;
                if (c == '\r')
                    continue;

                if (sb.Length >= maxChars)
                    throw new InvalidOperationException("IPC Zeile ueberschreitet das Sicherheitslimit.");

                sb.Append(c);
            }
            return sb.ToString();
        }

        private static async Task WriteJsonRpcResponseAsync(Stream stdout, string singleLineJson, bool useContentLength)
        {
            byte[] payload = Utf8NoBom.GetBytes(singleLineJson);
            if (useContentLength)
            {
                byte[] header = Encoding.ASCII.GetBytes($"Content-Length: {payload.Length}\r\n\r\n");
                await stdout.WriteAsync(header, 0, header.Length);
                await stdout.WriteAsync(payload, 0, payload.Length);
            }
            else
            {
                await stdout.WriteAsync(payload, 0, payload.Length);
                await stdout.WriteAsync(new byte[] { (byte)'\n' }, 0, 1);
            }
            await stdout.FlushAsync();
        }

        public static async Task<string?> ProcessJsonRpcMessageAsync(string rawJson)
        {
            if (rawJson.Length > McpSecurityGuard.MaxJsonRpcMessageBytes)
                return BuildErrorResponse(null, -32600, "Message exceeds 512 KB limit");

            JsonNode? reqNode;
            try
            {
                reqNode = JsonNode.Parse(rawJson, nodeOptions: null, documentOptions: StrictJsonParseOptions);
                if (reqNode == null) return BuildErrorResponse(null, -32700, "Parse error");
            }
            catch
            {
                return BuildErrorResponse(null, -32700, "Invalid or overly nested JSON");
            }

            bool hasId = reqNode is JsonObject obj && obj.ContainsKey("id");
            JsonNode? idNode = hasId ? reqNode["id"]?.DeepClone() : null;
            string method = reqNode["method"]?.GetValue<string>() ?? "";
            JsonObject? @params = reqNode["params"] as JsonObject;

            // JSON-RPC notifications (no "id" field) must not produce a response
            if (!hasId)
            {
                return null;
            }

            try
            {
                object result = method switch
                {
                    "initialize" => HandleInitialize(@params),
                    "ping" => new { },
                    "tools/list" => new { tools = BuildToolsCatalog() },
                    "tools/call" => await HandleToolCallAsync(@params),
                    "resources/list" => new { resources = BuildResourcesCatalog() },
                    "resources/read" => HandleResourceRead(@params),
                    "prompts/list" => new { prompts = Array.Empty<object>() },
                    _ => throw new JsonRpcMethodNotFoundException($"Unknown MCP method '{method}'")
                };

                var resp = new JsonObject
                {
                    ["jsonrpc"] = "2.0",
                    ["id"] = idNode,
                    ["result"] = JsonSerializer.SerializeToNode(result, JsonCompact)
                };
                return resp.ToJsonString(JsonCompact);
            }
            catch (JsonRpcMethodNotFoundException mnf)
            {
                return BuildErrorResponse(idNode, -32601, mnf.Message);
            }
            catch (Exception ex)
            {
                return BuildErrorResponse(idNode, -32603, McpSecurityGuard.ScrubSecrets(ex.Message));
            }
        }

        private sealed class JsonRpcMethodNotFoundException : Exception
        {
            public JsonRpcMethodNotFoundException(string msg) : base(msg) { }
        }

        private static string BuildErrorResponse(JsonNode? idNode, int code, string message)
        {
            var errResp = new JsonObject
            {
                ["jsonrpc"] = "2.0",
                ["id"] = idNode,
                ["error"] = new JsonObject
                {
                    ["code"] = code,
                    ["message"] = McpSecurityGuard.ScrubSecrets(message)
                }
            };
            return errResp.ToJsonString(JsonCompact);
        }

        private static object HandleInitialize(JsonObject? @params)
        {
            string clientProto = @params?["protocolVersion"]?.GetValue<string>() ?? "2024-11-05";
            string[] supported = { "2025-06-18", "2025-03-26", "2024-11-05" };
            string negotiated = supported.Contains(clientProto, StringComparer.Ordinal) ? clientProto : "2024-11-05";

            return new
            {
                protocolVersion = negotiated,
                capabilities = new
                {
                    tools = new { listChanged = false },
                    resources = new { subscribe = false, listChanged = false }
                },
                serverInfo = new
                {
                    name = "funkenwerk-mcp",
                    version = "1.6.0"
                },
                instructions = "Gehärteter lokaler Funkenwerk MCP-Server (Zero-Secret Architektur v1.3) für Mein-Seelenfunke: PDF-Formulare inspizieren & ausfüllen, 3-Wege Aufgaben/Kalender/Einkaufsliste synchronisieren, Musikplayer & Fokus-Modus live steuern, Smart-Home-Steckdosen & IP-Kameras steuern, alle Funkenwerk Collection Apps starten und sichere Server-Aktionen ohne Credential-Exposition ausführen."
            };
        }

        // =====================================================================
        // MCP RESOURCES (SAFE READ-ONLY STAMMDATEN & POLICY)
        // =====================================================================
        private static object[] BuildResourcesCatalog() => new object[]
        {
            new
            {
                uri = "funkenwerk://company/stammdaten",
                name = "Mein-Seelenfunke Unternehmens-Stammdaten",
                description = "Verifizierte Stammdaten von Mein-Seelenfunke (Alina Steinhauer) zum Ausfüllen von Lieferanten-, Händler- und Behörden-Formularen.",
                mimeType = "application/json"
            },
            new
            {
                uri = "funkenwerk://security/policy",
                name = "Funkenwerk MCP Sicherheits- & Härtungsstatus",
                description = "Aktive 8-Schichten-Sicherheitsarchitektur, erlaubte Pfade und Hash-Chain-Status.",
                mimeType = "application/json"
            }
        };

        private static object HandleResourceRead(JsonObject? @params)
        {
            string uri = @params?["uri"]?.GetValue<string>() ?? "";
            object data = uri switch
            {
                "funkenwerk://company/stammdaten" => BuildStammdatenObject(),
                "funkenwerk://security/policy" => McpSecurityGuard.RunSecuritySelfAudit(),
                _ => throw new ArgumentException($"Unbekannte Resource-URI: {uri}")
            };

            return new
            {
                contents = new[]
                {
                    new
                    {
                        uri,
                        mimeType = "application/json",
                        text = McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(data, JsonPretty))
                    }
                }
            };
        }

        public static object BuildStammdatenObject() => new
        {
            unternehmen = "Mein-Seelenfunke",
            rechtsform = "Einzelunternehmen (reguläre Besteuerung)",
            startdatum = "01.11.2026",
            inhaberin = "Alina Steinhauer",
            funktion = "Inhaberin",
            strasse = "Carl-Goerdeler-Ring 26",
            plz = "38518",
            ort = "Gifhorn",
            bundesland = "Niedersachsen",
            land = "Deutschland",
            email = "kontakt@mein-seelenfunke.de",
            telefon = "+49 1590 1966864",
            webseite = "https://mein-seelenfunke.de",
            geschaeftskontoBank = "FYRST (Deutsche Bank AG)",
            manufaktur = "xTool F2 Ultra UV (5W UV-Laser) + Förderband + RA3 Rotationsaufsatz + SafetyPro IF2 2.0",
            haupttaetigkeit = "Personalisierte Geschenkartikel & Schmuck-Veredelung (Handveredelt in Deutschland, Manufaktur Gifhorn)"
        };

        // =====================================================================
        // MCP TOOLS CATALOG (12 COMPREHENSIVE, STRICTLY TYPED TOOLS)
        // =====================================================================
        private static object[] BuildToolsCatalog() => new object[]
        {
            new
            {
                name = "pdf_inspect",
                description = "Analysiert eine lokale PDF-Datei (Seitenanzahl, Maße sowie sämtliche ausfüllbaren AcroForm-Formularfelder inkl. Namen, Typen, Koordinaten, Optionen und aktuellen Werten).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "filePath": { "type": "string", "description": "Absoluter Pfad zur PDF-Datei (z. B. in Downloads, Documents, Desktop)." }
                  },
                  "required": ["filePath"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "pdf_fill_form",
                description = "Füllt interaktive PDF-Formularfelder (AcroForm: Text, Mehrzeilig, Checkboxen, RadioButtons, Dropdowns, kleine Ja/Nein-Boxen) sowie optionale Vektor-Overlays (Text, Haken, Kreuz, Korrektur) aus, speichert das PDF atomar & weiterhin bearbeitbar ab und öffnet es auf Wunsch direkt in Funkenwerk PDF zur Kontrolle & Unterschrift.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "inputPath": { "type": "string", "description": "Quell-PDF-Pfad." },
                    "outputPath": { "type": "string", "description": "Ziel-PDF-Pfad. Wenn leer, wird automatisch '<Name>_ausgefuellt.pdf' erstellt." },
                    "overwrite": { "type": "boolean", "description": "Erlaubt das Überschreiben einer bestehenden Zieldatei (Standard: false)." },
                    "openInFunkenwerkPdf": { "type": "boolean", "description": "Öffnet das fertig ausgefüllte Dokument direkt in Funkenwerk PDF (Standard: true)." },
                    "fieldValues": {
                      "type": "object",
                      "description": "Key-Value-Map der Formularfeldnamen auf ihre gewünschten Werte (für Checkboxen/Ja-Nein-Boxen 'X' oder 'true').",
                      "additionalProperties": { "type": "string" }
                    },
                    "overlays": {
                      "type": "array",
                      "description": "Optionale freie Vektor-Elemente für PDFs ohne Formularfelder.",
                      "items": {
                        "type": "object",
                        "properties": {
                          "pageNumber": { "type": "integer" },
                          "type": { "type": "string", "enum": ["text", "check", "cross", "whiteout"] },
                          "xPt": { "type": "number" },
                          "yPt": { "type": "number" },
                          "widthPt": { "type": "number" },
                          "heightPt": { "type": "number" },
                          "text": { "type": "string" },
                          "fontSizePt": { "type": "number" }
                        },
                        "required": ["pageNumber", "type", "xPt", "yPt"]
                      }
                    }
                  },
                  "required": ["inputPath"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "pdf_autofill_stammdaten",
                description = "Füllt ein Lieferanten-, KYC-, Händler- oder Behörden-PDF automatisch mit den verifizierten Mein-Seelenfunke Stammdaten (Firma, Inhaberin, Adresse, Kontakt, Bank, Ort/Datum) plus optionalen Zusatzwerten aus, speichert eine neue Kopie atomar und öffnet sie in Funkenwerk PDF.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "inputPath": { "type": "string", "description": "Pfad zum unausgefüllten Formular-PDF." },
                    "outputPath": { "type": "string", "description": "Optionaler Zielpfad (Standard: '<Name>_Seelenfunke.pdf')." },
                    "overwrite": { "type": "boolean", "description": "Erlaubt das Überschreiben einer bestehenden Zieldatei (Standard: false)." },
                    "openInFunkenwerkPdf": { "type": "boolean", "description": "Direkt nach dem Ausfüllen in Funkenwerk PDF öffnen (Standard: true)." },
                    "overrides": {
                      "type": "object",
                      "description": "Zusätzliche oder überschreibende Feldwerte (z. B. Ja/Nein-Kästchen 'Zugehörigkeit Nein': 'X').",
                      "additionalProperties": { "type": "string" }
                    }
                  },
                  "required": ["inputPath"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "tasks_list",
                description = "Ruft Aufgaben und Aufgabenlisten synchron aus dem Seelenfunke-Ökosystem (Webportal + Funkenwerk Windows + Android App) ab.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "onlyOpen": { "type": "boolean", "description": "Nur offene Aufgaben anzeigen (Standard: true)." },
                    "priority": { "type": "string", "enum": ["hoch", "mittel", "niedrig", "low", "normal", "high", "urgent"] },
                    "listName": { "type": "string" },
                    "search": { "type": "string" },
                    "environment": { "type": "string", "enum": ["active", "local", "stage", "live"] }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "tasks_manage",
                description = "Erstellt, aktualisiert, erledigt (toggle), ergänzt Unteraufgaben (subtask) oder löscht Aufgaben synchron für Webportal, Funkenwerk Windows und Android App.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": { "type": "string", "enum": ["create", "update", "toggle", "subtask", "delete"] },
                    "taskId": { "type": "string", "description": "Erforderlich bei update, toggle, subtask, delete." },
                    "title": { "type": "string", "description": "Aufgabentitel (erforderlich bei create und subtask)." },
                    "priority": { "type": "string", "enum": ["hoch", "mittel", "niedrig", "low", "normal", "high", "urgent"] },
                    "listIdOrAlias": { "type": "string", "description": "z. B. 'default', 'my_day', 'important', 'planned' oder Listen-UUID." },
                    "listName": { "type": "string" },
                    "relevantFrom": { "type": "string", "description": "Fälligkeits-/Startdatum YYYY-MM-DD." },
                    "notes": { "type": "string" },
                    "isCompleted": { "type": "boolean" },
                    "environment": { "type": "string", "enum": ["active", "local", "stage", "live"] }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "calendar_list",
                description = "Liest anstehende Termine und terminierte Aufgaben aus dem zentralen Funkenwerk/Seelenfunke-Kalender.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "date": { "type": "string", "description": "Einzeltag YYYY-MM-DD (setzt Start- und Enddatum auf diesen Tag)." },
                    "startDate": { "type": "string", "description": "YYYY-MM-DD (Standard: heute)." },
                    "endDate": { "type": "string", "description": "YYYY-MM-DD." },
                    "daysAhead": { "type": "integer", "description": "Zeitraum in Tagen ab heute, falls endDate leer ist (Standard: 14)." },
                    "environment": { "type": "string", "enum": ["active", "local", "stage", "live"] }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "calendar_create",
                description = "Trägt einen neuen Termin oder Meilenstein in den synchronisierten Seelenfunke/Funkenwerk-Kalender ein.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "title": { "type": "string" },
                    "start": { "type": "string", "description": "ISO-8601 Datum/Uhrzeit, z. B. '2026-11-12T10:00:00' oder '2026-11-12'." },
                    "end": { "type": "string" },
                    "isAllDay": { "type": "boolean" },
                    "category": { "type": "string" },
                    "priority": { "type": "string", "enum": ["low", "medium", "high"] },
                    "description": { "type": "string" },
                    "environment": { "type": "string", "enum": ["active", "local", "stage", "live"] }
                  },
                  "required": ["title", "start"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "shopping_manage",
                description = "Verwaltet die synchronisierte Einkaufs-/Besorgungsliste (list, add, toggle, delete).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": { "type": "string", "enum": ["list", "add", "toggle", "delete"] },
                    "itemId": { "type": "string" },
                    "name": { "type": "string" },
                    "quantity": { "type": "integer" },
                    "category": { "type": "string", "description": "Optionaler Kategoriename oder Kategorie-UUID beim Hinzufügen." },
                    "categoryId": { "type": "string", "description": "Optionale Kategorie-UUID." },
                    "statusFilter": { "type": "string", "enum": ["needed", "stocked"] },
                    "environment": { "type": "string", "enum": ["active", "local", "stage", "live"] }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "server_list_profiles",
                description = "Listet ausschließlich passwortfreie Metadaten der gespeicherten Funkenwerk FTP- und SSH-Profile auf (Name, Protokoll, Host, Port, SHA-256 Pinning-Status). Gibt NIEMALS Passwörter oder Keys zurück.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {},
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "server_open_client",
                description = "Startet Funkenwerk FTP oder Funkenwerk SSH auf dem Desktop. Bei SSH kann optional ein Profilname + Zielverzeichnis per verschlüsseltem Einmal-Handoff direkt verbunden werden (ohne Credential-Einblick für die KI).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "client": { "type": "string", "enum": ["ftp", "ssh"] },
                    "profileName": { "type": "string", "description": "Optionaler Profilname für direkten SSH-Verbindungsaufbau." },
                    "remoteDirectory": { "type": "string", "description": "Optionales Startverzeichnis auf dem Server (z. B. '/home/p-g27wim/html/seelenfunke-stage')." }
                  },
                  "required": ["client"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "ssh_exec_safe_readonly",
                description = "Führt ein streng freigegebenes Diagnose- oder Stage-Rollout-Preset auf einem per SHA-256 Host-Key gepinnten SSH-Profil aus (keine freien Shell-Befehle möglich, Zero-Secret Schutz, Live-Server technisch gesperrt).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "profileName": { "type": "string", "description": "Name des gespeicherten Profils (z. B. 'Mittwald' oder 'Mittwald Websocket')." },
                    "preset": {
                      "type": "string",
                      "enum": ["disk_usage", "uptime_and_load", "php_version", "artisan_about", "git_status", "git_log", "git_branch", "list_dir", "stage_git_pull", "stage_deploy", "stage_worker_restart", "stage_sentinel_export", "stage_sentinel_resolve_all", "minipc_sentinel_export", "minipc_sentinel_resolve_all"]
                    },
                    "remoteDirectory": { "type": "string", "description": "Optionaler sicherer Zielordner auf dem Server." }
                  },
                  "required": ["profileName", "preset"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "stage_sync_shop_assets",
                description = "Synchronisiert per SHA-256-Delta-SFTP ausschließlich reine öffentliche Shop-, Produkt-, 3D-, Konfigurator- und Blog-Medien aus shop-asset-manifest.json auf den Stage-Server (/home/p-g27wim/html/seelenfunke-stage). Musik, Buchhaltung, Leitung, Agenten, Support und Privatdaten sowie der Live-Server sind im C#-Code zu 100 % technisch gesperrt.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "profileName": { "type": "string", "description": "Stage-Profilname (Standard: 'Mittwald')." },
                    "dryRun": { "type": "boolean", "description": "Nur Delta berechnen ohne SFTP-Upload (Standard: false)." }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "collection_control",
                description = "Zentrale Steuerung & Prüfung der Funkenwerk Collection: Systemstatus abrufen ('status'), eine der 10 Desktop-Apps starten ('launch'), Unternehmens-Stammdaten abrufen ('stammdaten') oder das Sicherheits-Audit prüfen ('security_audit').",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": { "type": "string", "enum": ["status", "launch", "stammdaten", "security_audit"] },
                    "app": { "type": "string", "enum": ["funkenwerk", "tresor", "player", "video", "craft", "ftp", "ssh", "pdf", "drucker", "os", "browser"], "description": "Ziel-App bei action='launch'." },
                    "filePath": { "type": "string", "description": "Optionaler sicherer Dateipfad (für 'pdf'/'drucker'/'player'), HTTPS-URL (für 'browser') oder Projektname (für 'craft'). CLI-Flags sind gesperrt." }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "music_control",
                description = "Steuert den integrierten Funkenwerk Musikplayer und den VSync-GPU Fokus-Modus live auf Alinas Windows-PC (Status abfragen, Songs/Playlists/Favoriten abspielen, Pause, Nächster/Vorheriger Titel, Lautstärke 0 bis 100 in 1er-Schritten, Fokus-Modus ein/aus, Shuffle, Repeat).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "enum": ["status", "play", "pause", "toggle", "next", "prev", "set_volume", "volume_delta", "focus_on", "focus_off", "favorite", "shuffle", "repeat"]
                    },
                    "query": { "type": "string", "description": "Optionaler Songtitel, Künstler oder Playlistname bei action='play' (z. B. 'Favoriten')." },
                    "volume": { "type": "integer", "minimum": -100, "maximum": 100, "description": "Ziel-Lautstärke 0..100 (bei set_volume) bzw. Änderung in 1er-Schritten (bei volume_delta)." },
                    "autoStartApp": { "type": "boolean", "description": "Startet Funkenwerk automatisch, falls die App gerade geschlossen ist (Standard: true bei play/focus_on, sonst false)." }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "smarthome_control",
                description = "Steuert Smart-Home-Steckdosen ('Lichterkette Fußleiste', 'Packtisch Warm Licht', 'Lichterkette Spiegel' oder 'alle') und IP-Kameras ('Terasse', 'Einfahrt', 'Garage', 'Gewerbe': Großansicht, 4er-Raster, PTZ-Drehung, Schnappschuss) lokal über Funkenwerk ohne Passwort-Exposition.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "enum": ["status", "plug_on", "plug_off", "plug_toggle", "camera_focus", "camera_grid", "camera_ptz", "camera_snapshot"]
                    },
                    "target": { "type": "string", "description": "Name der Steckdose (z. B. 'Packtisch Warm Licht', 'alle') oder Kamera ('Terasse', 'Einfahrt', 'Garage', 'Gewerbe')." },
                    "direction": { "type": "string", "enum": ["left", "right", "up", "down", "stop"], "description": "PTZ-Bewegungsrichtung bei action='camera_ptz'." },
                    "autoStartApp": { "type": "boolean", "description": "Startet Funkenwerk automatisch, falls geschlossen (Standard: true)." }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_workspace_status",
                description = "Prüft den kompletten Status von Funkenwerk Craft (ArtCraft v0.41.1, FilmCraft v0.20.1, SoundCraft v0.20.1, angelegte Video-Projekte, CC0-Soundpool sowie optional die Produkt- & Saisonbild-Abdeckung unter Y:\\Medien\\Produkte).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "includeMedienCoverage": { "type": "boolean", "description": "Scannt zusätzlich Y:\\Medien\\Produkte und zeigt für alle 9 Seelenfunke-Produkte vorhandene sowie noch fehlende Saison-/Feiertags-Hauptbilder (Standard: true)." }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_project_manage",
                description = "Verwaltet Video-Projekte in Funkenwerk Craft ('Funkenwerk Collection\\Funkenwerk Craft\\Videos\\<Projekt>'): Projekte auflisten ('list'), Projektdetails abrufen ('get'), Projekt anlegen/aktualisieren ('save'), Quelldateien aus Y:\\Medien sicher in das Projekt kopieren ('import_media').",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": { "type": "string", "enum": ["list", "get", "save", "import_media"] },
                    "projectName": { "type": "string", "description": "Name des Projekts unter Videos\\<Projektname>, z. B. 'Projekt 1'." },
                    "projectPath": { "type": "string", "description": "Optionaler Zielpfad der Projektdatei innerhalb von Funkenwerk Craft\\Videos." },
                    "outputPath": { "type": "string", "description": "Optionaler Zielpfad des Exportvideos innerhalb von Funkenwerk Craft\\Videos." },
                    "aspectRatio": { "type": "string", "enum": ["9:16", "1:1", "16:9"], "description": "Zielformat: '9:16' (Instagram Reel / TikTok / Shorts), '1:1' (Feed), '16:9' (YouTube)." },
                    "transitionDurationSec": { "type": "number", "minimum": 0.08, "maximum": 1.20, "description": "Standard-Übergangsdauer in Sekunden (Standard 0.20s für knackige Beat-Cuts)." },
                    "colorGrade": { "type": "string", "enum": ["dark_luxury", "natural"], "description": "Farbgrading: 'dark_luxury' (Obsidian-Kontrast & warme Kristall-Highlights) oder 'natural'." },
                    "overlayStyle": { "type": "string", "enum": ["editorial_minimal", "card"], "description": "Typografie-Stil: 'editorial_minimal' (rahmenlose reinweiße High-Fashion Cinema-Typografie) oder 'card'." },
                    "backgroundMusic": { "type": "string", "description": "Relativer Pfad im CC0-Soundpool, z. B. 'Beats_und_Ambient\\Dark_Luxury_Viral_808_120BPM_CC0.wav'." },
                    "musicVolume": { "type": "number", "minimum": 0, "maximum": 1.5, "description": "Lautstärke des Musikbetts (0.0 bis 1.5, Standard 0.56)." },
                    "sfxVolume": { "type": "number", "minimum": 0, "maximum": 1.5, "description": "Lautstärke der Cinema-SFX (0.0 bis 1.5, Standard 0.82)." },
                    "introAccentSfx": { "type": "string", "description": "Optionaler Sub-Boom / Sub-Drop zum Video-Start (Frame 0)." },
                    "outroAccentSfx": { "type": "string", "description": "Optionaler mystischer Kristall-/Sub-Swell auf dem finalen Abgang-Dissolve." },
                    "showBrandHeader": { "type": "boolean", "description": "Optionales dezentes reinweißes Marken-Wasserzeichen oben im Bild." },
                    "brandHeaderText": { "type": "string", "description": "Optionaler Marken-Header oben im Video." },
                    "outputFileName": { "type": "string", "description": "Zieldateiname für das fertige MP4 im Projektordner." },
                    "sourcePaths": {
                      "type": "array",
                      "items": { "type": "string" },
                      "description": "Liste sicherer Mediendateipfade (z. B. aus Y:\\Medien) bei action='import_media'."
                    },
                    "scenes": {
                      "type": "array",
                      "description": "Geordnete Szenen-Timeline bei action='save' (inkl. optionalem 'brand_outro' Abgang am Ende).",
                      "items": {
                        "type": "object",
                        "properties": {
                          "fileName": { "type": "string", "description": "Dateiname im Projektordner oder '__BRAND_OUTRO__' für den mystischen 60fps Marken-Abgang." },
                          "mediaType": { "type": "string", "enum": ["video", "image", "brand_outro"] },
                          "startSec": { "type": "number", "minimum": 0 },
                          "durationSec": { "type": "number", "minimum": 0.8, "maximum": 45 },
                          "fitMode": { "type": "string", "enum": ["fill", "contain_blur"] },
                          "speedMode": { "type": "string", "enum": ["speed_ramp", "slow_motion", "linear"], "description": "'speed_ramp' (2.4x Whip -> Optical-Flow Slow-Mo -> 2.4x Whip), 'slow_motion' oder 'linear'." },
                          "slowMoFactor": { "type": "number", "minimum": 0.25, "maximum": 0.90, "description": "Zeitlupen-Faktor für Hero-Phase (z. B. 0.35 bis 0.48)." },
                          "overlayBadge": { "type": "string" },
                          "overlayTitle": { "type": "string" },
                          "overlaySubtitle": { "type": "string" },
                          "transition": { "type": "string", "enum": ["whip_zoom", "whip_pan", "dark_cut", "velvet_dissolve", "flash_cut", "flash_white", "dissolve", "smoothleft", "fadeblack", "smoothup"] },
                          "transitionDurSec": { "type": "number", "minimum": 0, "maximum": 1.40, "description": "Optionale individuelle Übergangsdauer dieser Szene in Sekunden (z. B. 0.90 für einen weichen velvet_dissolve in den Marken-Abgang; 0 = Projektstandard)." },
                          "transitionSfx": { "type": "string" },
                          "impactSfx": { "type": "string", "description": "Optionaler Sub-Bass / Impulse-Hit auf dem Schnittpunkt." },
                          "riserSfx": { "type": "string", "description": "Optionaler Spannungs-Riser vor dem Schnittpunkt (schneidet exakt auf dem Beat ab)." },
                          "keepOriginalAudio": { "type": "boolean" },
                          "originalAudioVolume": { "type": "number", "minimum": 0, "maximum": 1.5 }
                        },
                        "required": ["fileName"]
                      }
                    }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_media_inspect",
                description = "Analysiert Foto- und Videodateien oder ganze Ordner in Y:\\Medien bzw. Funkenwerk Craft\\Videos per ffprobe/EXIF (Auflösung, Hoch-/Querformat, Dauer, FPS, Tonspur, Dateigröße).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "targetPath": { "type": "string", "description": "Pfad zu einer Mediendatei oder einem Ordner innerhalb von Y:\\Medien oder Funkenwerk Craft\\Videos." },
                    "maxFiles": { "type": "integer", "minimum": 1, "maximum": 100, "description": "Maximale Dateianzahl bei Ordner-Scan (Standard: 40)." }
                  },
                  "required": ["targetPath"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_soundpool_browser",
                description = "Durchsucht den lokalen, lizenzfreien & gewerblich nutzbaren CC0-Soundpool von Funkenwerk Craft (Übergangs-Whooshes, Kristall-/Gold-Akzente, Social-Media-Beats & Ambient-Tracks inkl. Lizenznachweis).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "category": { "type": "string", "enum": ["all", "Uebergaenge_Whoosh", "Effekte_Akzente", "Beats_und_Ambient"], "description": "Kategorie-Filter (Standard: 'all')." },
                    "inspectAudioFile": { "type": "string", "description": "Optionaler Pfad einer Sounddatei für detaillierte SoundCraft-CLI Formatprüfung." }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_render_social_video",
                description = "Verarbeitet und rendert ein Funkenwerk Craft Projekt ('Videos\\<Projektname>') zu einem postbereiten Social-Media-Mastervideo (H.264 Main Profile, yuv420p, bt709, 60fps, AAC 48kHz, FastStart, Optical-Flow Speed-Ramps, CC0-Sounddesign, reinweiße Cinema-Typografie & mystischer Marken-Abgang) und aktualisiert synchron die FilmCraft .fcproj Datei.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "projectName": { "type": "string", "description": "Projektname unter Videos\\<Projektname>, z. B. 'Projekt 1'." },
                    "outputPath": { "type": "string", "description": "Optionaler Zielpfad des MP4-Videos innerhalb von Funkenwerk Craft\\Videos." },
                    "openInCraftUi": { "type": "boolean", "description": "Öffnet nach erfolgreichem Rendern optional Funkenwerk Craft zur Live-Vorschau (Standard: false)." }
                  },
                  "required": ["projectName"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_storytold_engine",
                description = "Direkte Profi-Schnittstelle zu den integrierten Storytold Engines (ArtCraft, FilmCraft, SoundCraft): Desktop-App mit geschützter Control-Bridge starten ('launch'), FilmCraft-Befehle abfragen ('filmcraft_commands'), .fcproj Projekt exportieren ('export_fcproj'), Live-TCP-Bridge Befehl senden ('filmcraft_bridge') oder SoundCraft-CLI nutzen ('soundcraft_commands' / 'soundcraft_plugins').",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "enum": ["launch", "filmcraft_commands", "export_fcproj", "filmcraft_bridge", "soundcraft_commands", "soundcraft_plugins"]
                    },
                    "engine": { "type": "string", "enum": ["artcraft", "filmcraft", "soundcraft"], "description": "Ziel-Engine bei action='launch'." },
                    "projectName": { "type": "string", "description": "Optionaler Projektname für action='export_fcproj' oder 'launch' (öffnet die .fcproj in FilmCraft)." },
                    "filter": { "type": "string", "description": "Optionaler Befehlsfilter bei action='filmcraft_commands' (z. B. 'timeline', 'sequence', 'graphics')." },
                    "bridgeMethod": { "type": "string", "description": "JSON-RPC Methode für action='filmcraft_bridge' (z. B. 'engine.execute', 'ui.screenshot')." },
                    "bridgeParams": { "type": "object", "description": "Optionale Parameter für action='filmcraft_bridge'." }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "ag_direct_inbox",
                description = "Verwaltet die 24/7 'Direktnachricht an AG'-Brücke inkl. der 5 festen Abteilungs-Kanäle ('leitung', 'shop', 'android', 'minipc', 'buchhaltung') und Sonder-Kanal 'new': Neue pending-Aufträge abholen ('pull'), alle Einträge auflisten ('list'), erledigte Punkte mit Zusammenfassung zurückmelden ('complete'), Master-Chat setzen ('set_master_chat') oder Abteilungs-Kanal-UUIDs abfragen/setzen/zurücksetzen ('list_channels', 'set_channel_chat', 'reset_channel_chat').",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "enum": ["pull", "list", "complete", "set_master_chat", "list_channels", "set_channel_chat", "reset_channel_chat"],
                      "description": "Aktion: 'pull', 'list', 'complete', 'set_master_chat', 'list_channels', 'set_channel_chat' oder 'reset_channel_chat'."
                    },
                    "id": {
                      "type": "string",
                      "description": "Auftrags-ID (z. B. 'ag_...') bei action='complete'."
                    },
                    "summary": {
                      "type": "string",
                      "description": "Kurze Zusammenfassung der Umsetzung bzw. des Konzepts für Alinas Android-App bei action='complete'."
                    },
                    "channel": {
                      "type": "string",
                      "enum": ["leitung", "shop", "android", "minipc", "buchhaltung"],
                      "description": "Fester Abteilungs-Kanal bei action='set_channel_chat' oder 'reset_channel_chat'."
                    },
                    "conversation_id": {
                      "type": "string",
                      "description": "Konversations-ID (GUID) bei action='set_master_chat' oder 'set_channel_chat' (oder 'reset' zum Zurücksetzen)."
                    }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "tresor_list_ai_entries",
                description = "Listet ausschließlich passwortfreie Metadaten der in Funkenwerk Tresor explizit für den KI-Broker freigegebenen Einträge auf (id, title, allowed_host, has_username, has_password, has_totp, has_api_token, updated_at). Gibt NIEMALS Passwörter, TOTP-Seeds oder API-Tokens aus; private Einträge ohne Freigabe sind zu 100 % ausgeschlossen.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {},
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "tresor_browser_login",
                description = "Führt einen blinden Zero-Secret Browser-Auto-Fill (inkl. mehrstufigem Login & 6-stelligem RFC-6238 2FA-TOTP) über lokales Chrome DevTools Loopback (127.0.0.1 DevToolsActivePort / Port 9222) aus. Ein harter Fail-Closed Anti-Phishing-Guard prüft zwingend https:// und die exakte Host-/Basis-Domain des Tresor-Eintrags. Liefert ausschließlich passwortfreie Statusfelder zurück.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "titleOrId": {
                      "type": "string",
                      "description": "Titel oder ID des im Funkenwerk Tresor freigegebenen Eintrags (z. B. 'Mittwald')."
                    },
                    "entry": {
                      "type": "string",
                      "description": "Alternativer Alias für titleOrId."
                    },
                    "submit": {
                      "type": "boolean",
                      "description": "Klickt nach dem Befüllen automatisch auf Weiter/Anmelden und durchläuft mehrstufige E-Mail -> Passwort -> 2FA-Schritte (Standard: false)."
                    },
                    "totpOnly": {
                      "type": "boolean",
                      "description": "Füllt ausschließlich den 6-stelligen RFC-6238 2FA-TOTP-Code ein (Standard: false)."
                    },
                    "cdpPort": {
                      "type": "integer",
                      "minimum": 1,
                      "maximum": 65535,
                      "description": "Optionaler lokaler Loopback-Port (127.0.0.1), falls DevToolsActivePort übersprungen werden soll."
                    }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "mittwald_api_broker",
                description = "Offizieller Zero-Secret Mittwald mStudio REST API v2 Broker (strikt https://api.mittwald.de/v2/...): Liest das API-Token im RAM aus dem freigegebenen Tresor-Eintrag, sendet die Anfrage, nullt das Token via ZeroMemory und bereinigt die Antwort per DLP. Stage-Mutationen unterliegen einer harten Fail-Closed Live-Air-Gap-Sperre (nur Stage-IDs a-lc6tkm, a-iurgq8, a-yeq7el bzw. verifizierte Stage-Apps/Cronjobs erlaubt; Live/Production wird hart blockiert).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "preset": {
                      "type": "string",
                      "enum": ["user_self", "list_projects", "list_apps", "get_app", "list_cronjobs", "list_ingresses", "restart_stage_app", "execute_stage_cronjob"],
                      "description": "Freigegebenes Diagnose- oder Stage-Preset."
                    },
                    "entry": {
                      "type": "string",
                      "description": "Optionaler Titel oder ID des freigegebenen Tresor-Eintrags (Standard-Suche: 'Mittwald')."
                    },
                    "projectId": {
                      "type": "string",
                      "description": "Mittwald Projekt-ID (z. B. 'p-g27wim' oder UUID) für list_apps, list_cronjobs, list_ingresses."
                    },
                    "project_id": {
                      "type": "string",
                      "description": "Alias für projectId."
                    },
                    "appId": {
                      "type": "string",
                      "description": "Mittwald App-Installations-ID (z. B. 'a-lc6tkm', 'a-iurgq8', 'a-yeq7el' oder UUID) für get_app / restart_stage_app."
                    },
                    "app_id": {
                      "type": "string",
                      "description": "Alias für appId."
                    },
                    "cronjobId": {
                      "type": "string",
                      "description": "Mittwald Cronjob-ID für execute_stage_cronjob (nur verifizierte Stage-Cronjobs erlaubt)."
                    },
                    "cronjob_id": {
                      "type": "string",
                      "description": "Alias für cronjobId."
                    }
                  },
                  "required": ["preset"],
                  "additionalProperties": false
                }
                """)
            }
        };

        // =====================================================================
        // TOOL EXECUTION GATEKEEPER
        // =====================================================================
        private static async Task<object> HandleToolCallAsync(JsonObject? @params)
        {
            string toolName = @params?["name"]?.GetValue<string>() ?? "";
            JsonObject args = (@params?["arguments"] as JsonObject) ?? new JsonObject();

            try
            {
                McpSecurityGuard.EnforceRateLimit(toolName);
                object payload = await DispatchToolAsync(toolName, args);

                string jsonText = McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(payload, JsonPretty));
                McpSecurityGuard.AppendAuditLog(toolName, "ok", "ALLOW");

                return new
                {
                    content = new[]
                    {
                        new { type = "text", text = jsonText }
                    },
                    isError = false
                };
            }
            catch (Exception ex)
            {
                string safeError = McpSecurityGuard.ScrubSecrets(ex.Message);
                McpSecurityGuard.AppendAuditLog(toolName, safeError, "ERROR_OR_DENY");

                return new
                {
                    content = new[]
                    {
                        new { type = "text", text = $"⚠ Sicherheits-/Ausführungsfehler in '{toolName}': {safeError}" }
                    },
                    isError = true
                };
            }
        }

        private static async Task<object> DispatchToolAsync(string toolName, JsonObject args)
        {
            switch (toolName)
            {
                case "pdf_inspect":
                {
                    string rawPath = GetString(args, "filePath");
                    string safePath = McpSecurityGuard.ValidateSafeFilePath(rawPath, new[] { ".pdf" }, mustExist: true);
                    return McpPdfEngine.InspectPdf(safePath);
                }

                case "pdf_fill_form":
                {
                    string rawIn = GetString(args, "inputPath");
                    string safeIn = McpSecurityGuard.ValidateSafeFilePath(rawIn, new[] { ".pdf" }, mustExist: true);

                    string? rawOut = GetOptString(args, "outputPath");
                    bool overwrite = GetBool(args, "overwrite", defaultVal: false);
                    bool openApp = GetBool(args, "openInFunkenwerkPdf", defaultVal: true);

                    if (string.IsNullOrWhiteSpace(rawOut))
                    {
                        string dir = Path.GetDirectoryName(safeIn)!;
                        string stem = Path.GetFileNameWithoutExtension(safeIn);
                        rawOut = Path.Combine(dir, $"{stem}_ausgefuellt.pdf");
                    }

                    string safeOut = McpSecurityGuard.ValidateSafeFilePath(rawOut, new[] { ".pdf" }, mustExist: false);

                    var fieldValues = new Dictionary<string, string>(StringComparer.Ordinal);
                    if (args["fieldValues"] is JsonObject fvObj)
                    {
                        if (fvObj.Count > 300)
                            throw new InvalidOperationException("Maximal 300 Formularfelder pro Aufruf erlaubt.");
                        foreach (var kv in fvObj)
                            fieldValues[kv.Key] = kv.Value?.ToString() ?? "";
                    }

                    var overlays = new List<McpPdfOverlayInput>();
                    if (args["overlays"] is JsonArray ovArr)
                    {
                        if (ovArr.Count > 150)
                            throw new InvalidOperationException("Maximal 150 Overlays pro Aufruf erlaubt.");
                        foreach (var item in ovArr)
                        {
                            if (item == null) continue;
                            var parsed = JsonSerializer.Deserialize<McpPdfOverlayInput>(item.ToJsonString(), new JsonSerializerOptions { PropertyNameCaseInsensitive = true });
                            if (parsed != null) overlays.Add(parsed);
                        }
                    }

                    byte[] srcBytes = await File.ReadAllBytesAsync(safeIn);
                    var (savedBytes, updatedFields, unmatchedKeys) = McpPdfEngine.ApplyFormValuesAndOverlays(srcBytes, fieldValues, overlays);
                    await McpSecurityGuard.WriteSafeFileAtomicAsync(safeOut, new[] { ".pdf" }, savedBytes, overwrite);

                    bool launched = false;
                    if (openApp)
                        launched = LaunchCollectionExecutable("pdf", safeOut);

                    return new
                    {
                        success = true,
                        inputPath = safeIn,
                        outputPath = safeOut,
                        updatedFieldsCount = updatedFields.Count,
                        updatedFields,
                        overlaysCount = overlays.Count,
                        unmatchedFieldNames = unmatchedKeys,
                        openedInFunkenwerkPdf = launched
                    };
                }

                case "pdf_autofill_stammdaten":
                {
                    string rawIn = GetString(args, "inputPath");
                    string safeIn = McpSecurityGuard.ValidateSafeFilePath(rawIn, new[] { ".pdf" }, mustExist: true);

                    string? rawOut = GetOptString(args, "outputPath");
                    if (string.IsNullOrWhiteSpace(rawOut))
                    {
                        string dir = Path.GetDirectoryName(safeIn)!;
                        string stem = Path.GetFileNameWithoutExtension(safeIn);
                        rawOut = Path.Combine(dir, $"{stem}_Seelenfunke.pdf");
                    }
                    string safeOut = McpSecurityGuard.ValidateSafeFilePath(rawOut, new[] { ".pdf" }, mustExist: false);
                    bool overwrite = GetBool(args, "overwrite", defaultVal: false);
                    bool openApp = GetBool(args, "openInFunkenwerkPdf", defaultVal: true);

                    byte[] srcBytes = await File.ReadAllBytesAsync(safeIn);
                    var fields = McpPdfEngine.ExtractFieldsFromBytes(srcBytes);
                    var autoMap = McpPdfEngine.BuildSeelenfunkeStammdatenMapping(fields);

                    if (args["overrides"] is JsonObject ovObj)
                    {
                        if (ovObj.Count > 300)
                            throw new InvalidOperationException("Maximal 300 Override-Felder erlaubt.");
                        foreach (var kv in ovObj)
                            autoMap[kv.Key] = kv.Value?.ToString() ?? "";
                    }

                    var (savedBytes, updatedFields, unmatchedKeys) = McpPdfEngine.ApplyFormValuesAndOverlays(srcBytes, autoMap, null);
                    await McpSecurityGuard.WriteSafeFileAtomicAsync(safeOut, new[] { ".pdf" }, savedBytes, overwrite);

                    var remainingOpenFields = fields
                        .Where(f => !f.IsReadOnly && !updatedFields.Contains(f.Name))
                        .Select(f => new { f.PageNumber, f.Name, f.Kind, f.IsTinyBoxText })
                        .ToList();

                    bool launched = false;
                    if (openApp)
                        launched = LaunchCollectionExecutable("pdf", safeOut);

                    return new
                    {
                        success = true,
                        outputPath = safeOut,
                        autoFilledFieldsCount = updatedFields.Count,
                        appliedValues = autoMap,
                        remainingFieldsForManualReview = remainingOpenFields,
                        unmatchedOverrideKeys = unmatchedKeys,
                        openedInFunkenwerkPdf = launched
                    };
                }

                case "tasks_list":
                    return await McpOrganizerClient.ListTasksAsync(
                        onlyOpen: GetBool(args, "onlyOpen", true),
                        priority: GetOptString(args, "priority"),
                        listName: GetOptString(args, "listName"),
                        search: GetOptString(args, "search"),
                        environment: GetOptString(args, "environment") ?? "active");

                case "tasks_manage":
                    return await McpOrganizerClient.ManageTaskAsync(
                        action: GetString(args, "action"),
                        taskId: GetOptString(args, "taskId"),
                        title: GetOptString(args, "title"),
                        priority: GetOptString(args, "priority"),
                        listIdOrAlias: GetOptString(args, "listIdOrAlias") ?? "default",
                        listName: GetOptString(args, "listName"),
                        relevantFrom: GetOptString(args, "relevantFrom"),
                        notes: GetOptString(args, "notes", maxLen: 8192),
                        isCompleted: args["isCompleted"] != null ? args["isCompleted"]!.GetValue<bool>() : null,
                        environment: GetOptString(args, "environment") ?? "active");

                case "calendar_list":
                    return await McpOrganizerClient.ListCalendarEventsAsync(
                        startDate: GetOptString(args, "startDate"),
                        endDate: GetOptString(args, "endDate"),
                        daysAhead: args["daysAhead"] != null ? args["daysAhead"]!.GetValue<int>() : 14,
                        environment: GetOptString(args, "environment") ?? "active",
                        date: GetOptString(args, "date"));

                case "calendar_create":
                    return await McpOrganizerClient.CreateCalendarEventAsync(
                        title: GetString(args, "title"),
                        start: GetString(args, "start"),
                        end: GetOptString(args, "end"),
                        isAllDay: GetBool(args, "isAllDay", false),
                        category: GetOptString(args, "category") ?? "general",
                        priority: GetOptString(args, "priority") ?? "low",
                        description: GetOptString(args, "description", maxLen: 8192),
                        environment: GetOptString(args, "environment") ?? "active");

                case "shopping_manage":
                    return await McpOrganizerClient.ManageShoppingAsync(
                        action: GetString(args, "action"),
                        itemId: GetOptString(args, "itemId"),
                        name: GetOptString(args, "name"),
                        quantity: args["quantity"] != null ? args["quantity"]!.GetValue<int>() : 1,
                        statusFilter: GetOptString(args, "statusFilter"),
                        environment: GetOptString(args, "environment") ?? "active",
                        category: GetOptString(args, "category") ?? GetOptString(args, "categoryId"));

                case "server_list_profiles":
                    return new
                    {
                        zeroSecretPolicy = "Passwörter, Tokens und Private Keys werden niemals an das Modell übergeben.",
                        profiles = McpSecurityGuard.GetSanitizedServerProfiles()
                    };

                case "server_open_client":
                {
                    string client = GetString(args, "client").ToLowerInvariant();
                    string? profileName = GetOptString(args, "profileName");
                    string? remoteDir = GetOptString(args, "remoteDirectory");

                    if (client == "ssh" && !string.IsNullOrWhiteSpace(profileName))
                    {
                        McpSecurityGuard.CreateBlindSshHandoffTicket(profileName!, remoteDir);
                        bool ok = LaunchCollectionExecutable("ssh", null, trustedInternalFlag: "--handoff");
                        return new { success = ok, launched = "Funkenwerk SSH", mode = "dpapi_blind_handoff", profile = profileName, remoteDirectory = remoteDir ?? "default" };
                    }
                    else if (client == "ftp" || client == "ssh")
                    {
                        bool ok = LaunchCollectionExecutable(client, null);
                        return new { success = ok, launched = client == "ftp" ? "Funkenwerk FTP" : "Funkenwerk SSH" };
                    }

                    throw new ArgumentException("Ungültiger Client (erlaubt: 'ftp' oder 'ssh').");
                }

                case "ssh_exec_safe_readonly":
                {
                    string profileName = GetString(args, "profileName");
                    string preset = GetString(args, "preset");
                    string? remDir = GetOptString(args, "remoteDirectory");
                    string output = await McpSecurityGuard.ExecutePinnedSshPresetAsync(profileName, preset, remDir);
                    return new
                    {
                        profile = profileName,
                        preset,
                        output
                    };
                }

                case "stage_sync_shop_assets":
                {
                    string profileName = GetOptString(args, "profileName") ?? "Mittwald";
                    bool dryRun = GetBool(args, "dryRun", false);
                    return await McpSecurityGuard.SyncStageShopAssetsDeltaAsync(profileName, dryRun);
                }

                case "collection_control":
                {
                    string action = GetString(args, "action").ToLowerInvariant();
                    switch (action)
                    {
                        case "status":
                            return await McpOrganizerClient.ProbeEcosystemHealthAsync();
                        case "stammdaten":
                            return BuildStammdatenObject();
                        case "security_audit":
                            return McpSecurityGuard.RunSecuritySelfAudit();
                        case "launch":
                        {
                            string appKey = GetString(args, "app").ToLowerInvariant();
                            string? rawFile = GetOptString(args, "filePath");
                            string? safeArg = null;
                            if (!string.IsNullOrWhiteSpace(rawFile))
                            {
                                string trimmed = rawFile.Trim();
                                if (trimmed.StartsWith('-') || trimmed.StartsWith('/'))
                                    throw new UnauthorizedAccessException("CLI-Flags sind beim App-Start aus Sicherheitsgruenden gesperrt.");

                                if (appKey == "craft")
                                {
                                    safeArg = McpCraftBridge.ValidateSafeProjectName(trimmed);
                                }
                                else if (appKey == "browser")
                                {
                                    if (!Uri.TryCreate(trimmed, UriKind.Absolute, out var browserUri) ||
                                        !string.Equals(browserUri.Scheme, Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase) ||
                                        !string.IsNullOrEmpty(browserUri.UserInfo))
                                    {
                                        throw new UnauthorizedAccessException("Funkenwerk Browser akzeptiert per MCP nur sichere https:// URLs.");
                                    }
                                    safeArg = browserUri.AbsoluteUri;
                                }
                                else
                                {
                                    string[] allowedExts = appKey switch
                                    {
                                        "pdf" => new[] { ".pdf" },
                                        "drucker" => new[] { ".pdf", ".png", ".jpg", ".jpeg", ".webp", ".bmp", ".tif", ".tiff" },
                                        "player" => new[] { ".mp4", ".mkv", ".webm", ".mov", ".avi", ".mp3", ".wav", ".flac", ".m4a", ".ogg" },
                                        _ => Array.Empty<string>()
                                    };
                                    if (allowedExts.Length == 0)
                                        throw new UnauthorizedAccessException("Dateiparameter werden aus Sicherheitsgründen nur für 'pdf', 'drucker', 'player', 'browser' und 'craft' akzeptiert.");

                                    string validated = McpSecurityGuard.ValidateSafeFilePath(trimmed, allowedExts, mustExist: true, maxBytes: 500L * 1024L * 1024L);
                                    safeArg = validated;
                                }
                            }

                            bool ok = LaunchCollectionExecutable(appKey, safeArg);
                            return new { success = ok, app = appKey, openedFile = safeArg };
                        }
                        default:
                            throw new ArgumentException("Erlaubte collection_control Aktionen: status, launch, stammdaten, security_audit.");
                    }
                }

                case "music_control":
                {
                    string action = GetString(args, "action").ToLowerInvariant();
                    string? query = GetOptString(args, "query", maxLen: 256);
                    int? volume = args["volume"] != null ? args["volume"]!.GetValue<int>() : null;
                    if (action == "set_volume" && (!volume.HasValue || volume.Value < 0 || volume.Value > 100))
                        throw new ArgumentException("Für action='set_volume' muss 'volume' zwischen 0 und 100 liegen.");

                    bool defaultAutoStart = action is "play" or "focus_on" or "toggle";
                    bool autoStart = GetBool(args, "autoStartApp", defaultAutoStart);
                    return await SendFunkenwerkLocalPipeCommandAsync(
                        domain: "music",
                        action: action,
                        query: query,
                        target: null,
                        direction: null,
                        volume: volume,
                        autoStartApp: autoStart);
                }

                case "smarthome_control":
                {
                    string action = GetString(args, "action").ToLowerInvariant();
                    string? target = GetOptString(args, "target", maxLen: 128);
                    string? direction = GetOptString(args, "direction", maxLen: 32);
                    bool defaultAutoStart = action != "status";
                    bool autoStart = GetBool(args, "autoStartApp", defaultAutoStart);
                    return await SendFunkenwerkLocalPipeCommandAsync(
                        domain: "smarthome",
                        action: action,
                        query: null,
                        target: target,
                        direction: direction,
                        volume: null,
                        autoStartApp: autoStart);
                }

                case "craft_workspace_status":
                {
                    bool includeCov = GetBool(args, "includeMedienCoverage", true);
                    var craftStatus = await McpCraftBridge.InvokeCraftActionAsync(new JsonObject { ["action"] = "status" });
                    return new
                    {
                        craft = craftStatus,
                        medienCoverage = includeCov ? McpCraftBridge.ScanMedienProductCoverage() : null
                    };
                }

                case "craft_project_manage":
                {
                    string action = GetString(args, "action").ToLowerInvariant();
                    switch (action)
                    {
                        case "list":
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject { ["action"] = "list_projects" });

                        case "get":
                        {
                            string safeProjectName = McpCraftBridge.ValidateSafeProjectName(GetOptString(args, "projectName", 80) ?? "Projekt 1");
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "get_project",
                                ["projectName"] = safeProjectName
                            });
                        }

                        case "save":
                        {
                            string safeProjectName = McpCraftBridge.ValidateSafeProjectName(GetOptString(args, "projectName", 80) ?? "Projekt 1");
                            var req = new JsonObject
                            {
                                ["action"] = "save_project",
                                ["projectName"] = safeProjectName
                            };

                            string? rawProjectPath = GetOptString(args, "projectPath");
                            if (!string.IsNullOrWhiteSpace(rawProjectPath))
                            {
                                req["projectPath"] = McpCraftBridge.ValidateSafeCraftWritePath(rawProjectPath, new[] { ".json", ".fcproj" });
                            }

                            string? rawOutputPath = GetOptString(args, "outputPath");
                            if (!string.IsNullOrWhiteSpace(rawOutputPath))
                            {
                                req["outputPath"] = McpCraftBridge.ValidateSafeCraftWritePath(rawOutputPath, new[] { ".mp4", ".mov", ".webm" });
                            }

                            if (args["aspectRatio"] != null) req["aspectRatio"] = GetOptString(args, "aspectRatio", 16);
                            if (args["transitionDurationSec"] != null) req["transitionDurationSec"] = args["transitionDurationSec"]!.GetValue<double>();
                            if (args["colorGrade"] != null) req["colorGrade"] = GetOptString(args, "colorGrade", 32);
                            if (args["overlayStyle"] != null) req["overlayStyle"] = GetOptString(args, "overlayStyle", 32);
                            if (args["backgroundMusic"] != null) req["backgroundMusic"] = GetOptString(args, "backgroundMusic", 260);
                            if (args["musicVolume"] != null) req["musicVolume"] = args["musicVolume"]!.GetValue<double>();
                            if (args["sfxVolume"] != null) req["sfxVolume"] = args["sfxVolume"]!.GetValue<double>();
                            if (args["introAccentSfx"] != null) req["introAccentSfx"] = GetOptString(args, "introAccentSfx", 260);
                            if (args["outroAccentSfx"] != null) req["outroAccentSfx"] = GetOptString(args, "outroAccentSfx", 260);
                            if (args["showBrandHeader"] != null) req["showBrandHeader"] = args["showBrandHeader"]!.GetValue<bool>();
                            if (args["brandHeaderText"] != null) req["brandHeaderText"] = GetOptString(args, "brandHeaderText", 160);
                            if (args["outputFileName"] != null)
                            {
                                string outName = GetOptString(args, "outputFileName", 160) ?? "";
                                if (outName.Contains("..", StringComparison.Ordinal) ||
                                    outName.Contains('/') || outName.Contains('\\') || outName.Contains(':') ||
                                    outName.IndexOfAny(Path.GetInvalidFileNameChars()) >= 0)
                                {
                                    throw new UnauthorizedAccessException("Ungültiger outputFileName (keine Pfadtrenner oder '..' erlaubt).");
                                }
                                string candidatePath = Path.Combine(McpCraftBridge.CraftVideosDir, safeProjectName, outName);
                                McpCraftBridge.ValidateSafeCraftWritePath(candidatePath, new[] { ".mp4", ".mov", ".webm" });
                                req["outputFileName"] = outName;
                            }
                            if (args["scenes"] is JsonArray scArr)
                            {
                                if (scArr.Count > 60)
                                    throw new InvalidOperationException("Maximal 60 Szenen pro Projekt erlaubt.");
                                req["scenes"] = scArr.DeepClone();
                            }
                            return await McpCraftBridge.InvokeCraftActionAsync(req);
                        }

                        case "import_media":
                        {
                            string projName = McpCraftBridge.ValidateSafeProjectName(GetOptString(args, "projectName", 80) ?? "Projekt 1");
                            var validatedPaths = new JsonArray();
                            if (args["sourcePaths"] is JsonArray spArr)
                            {
                                if (spArr.Count > 50)
                                    throw new InvalidOperationException("Maximal 50 Mediendateien pro Import-Aufruf erlaubt.");
                                foreach (var item in spArr)
                                {
                                    string raw = item?.GetValue<string>() ?? "";
                                    string safe = McpCraftBridge.ValidateSafeMediaSourceOrCraftPath(raw, allowAudio: false, allowDirectories: false);
                                    validatedPaths.Add(safe);
                                }
                            }
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "import_media",
                                ["projectName"] = projName,
                                ["sourcePaths"] = validatedPaths
                            });
                        }

                        default:
                            throw new ArgumentException("Erlaubte craft_project_manage Aktionen: list, get, save, import_media.");
                    }
                }

                case "craft_media_inspect":
                {
                    string rawTarget = GetString(args, "targetPath");
                    string safeTarget = McpCraftBridge.ValidateSafeMediaSourceOrCraftPath(rawTarget, allowAudio: true, allowDirectories: true);
                    int maxFiles = args["maxFiles"] != null ? Math.Clamp(args["maxFiles"]!.GetValue<int>(), 1, 100) : 40;

                    if (Directory.Exists(safeTarget))
                    {
                        var files = Directory.GetFiles(safeTarget, "*", SearchOption.AllDirectories)
                            .Where(f =>
                            {
                                string ext = Path.GetExtension(f).ToLowerInvariant();
                                return ext is ".mp4" or ".mov" or ".mkv" or ".webm" or ".jpg" or ".jpeg" or ".png" or ".webp";
                            })
                            .OrderBy(f => f)
                            .Take(maxFiles)
                            .ToList();

                        var items = new List<JsonNode?>();
                        foreach (string f in files)
                        {
                            string safeFile = McpCraftBridge.ValidateSafeMediaSourceOrCraftPath(f, allowAudio: false, allowDirectories: false);
                            var res = await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "inspect_media",
                                ["filePath"] = safeFile
                            });
                            items.Add(res["media"]?.DeepClone());
                        }
                        return new { directory = safeTarget, inspectedCount = items.Count, items };
                    }
                    else
                    {
                        return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                        {
                            ["action"] = "inspect_media",
                            ["filePath"] = safeTarget
                        });
                    }
                }

                case "craft_soundpool_browser":
                {
                    string? cat = GetOptString(args, "category", 64);
                    string? inspectFile = GetOptString(args, "inspectAudioFile");
                    var pool = await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                    {
                        ["action"] = "list_sounds",
                        ["category"] = cat ?? "all"
                    });

                    string? scInfo = null;
                    if (!string.IsNullOrWhiteSpace(inspectFile))
                    {
                        scInfo = await McpCraftBridge.RunSoundCraftCliSafeAsync("info", inspectFile);
                    }

                    return new { soundPool = pool, soundCraftFileInfo = scInfo };
                }

                case "craft_render_social_video":
                {
                    string projName = McpCraftBridge.ValidateSafeProjectName(GetString(args, "projectName", 80));
                    string? rawOutputPath = GetOptString(args, "outputPath");
                    var renderReq = new JsonObject
                    {
                        ["action"] = "render_video",
                        ["projectName"] = projName
                    };
                    if (!string.IsNullOrWhiteSpace(rawOutputPath))
                    {
                        renderReq["outputPath"] = McpCraftBridge.ValidateSafeCraftWritePath(rawOutputPath, new[] { ".mp4", ".mov", ".webm" });
                    }

                    bool openUi = GetBool(args, "openInCraftUi", false);
                    var renderRes = await McpCraftBridge.InvokeCraftActionAsync(renderReq, timeoutSec: 420);

                    if (openUi && renderRes["ok"]?.GetValue<bool>() == true)
                    {
                        LaunchCollectionExecutable("craft", projName);
                    }
                    return renderRes;
                }

                case "craft_storytold_engine":
                {
                    string action = GetString(args, "action").ToLowerInvariant();
                    switch (action)
                    {
                        case "launch":
                        {
                            string eng = GetOptString(args, "engine", 32) ?? "artcraft";
                            string? rawProj = GetOptString(args, "projectName", 80);
                            string? targetPath = null;
                            if (eng == "filmcraft" && !string.IsNullOrWhiteSpace(rawProj))
                            {
                                string safeProj = McpCraftBridge.ValidateSafeProjectName(rawProj);
                                var exp = await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                                {
                                    ["action"] = "export_filmcraft",
                                    ["projectName"] = safeProj
                                });
                                targetPath = exp["filmcraftProjectPath"]?.GetValue<string>();
                            }
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "launch_engine",
                                ["engine"] = eng,
                                ["targetPath"] = targetPath,
                                ["enableControlBridge"] = true
                            });
                        }

                        case "filmcraft_commands":
                        {
                            string? filter = GetOptString(args, "filter", 64);
                            var cliArgs = new JsonArray("commands");
                            if (!string.IsNullOrWhiteSpace(filter))
                                cliArgs.Add(filter);
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "filmcraft_cli",
                                ["args"] = cliArgs
                            });
                        }

                        case "export_fcproj":
                        {
                            string proj = McpCraftBridge.ValidateSafeProjectName(GetOptString(args, "projectName", 80) ?? "Projekt 1");
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "export_filmcraft",
                                ["projectName"] = proj
                            });
                        }

                        case "filmcraft_bridge":
                        {
                            string rawMethod = GetString(args, "bridgeMethod", 120);
                            string safeMethod = McpCraftBridge.ValidateAllowedFilmCraftBridgeMethod(rawMethod);
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "filmcraft_bridge",
                                ["method"] = safeMethod,
                                ["params"] = args["bridgeParams"]?.DeepClone() ?? new JsonObject()
                            });
                        }

                        case "soundcraft_commands":
                            return new { ok = true, output = await McpCraftBridge.RunSoundCraftCliSafeAsync("commands") };

                        case "soundcraft_plugins":
                            return new { ok = true, output = await McpCraftBridge.RunSoundCraftCliSafeAsync("plugins") };

                        default:
                            throw new ArgumentException("Unbekannte craft_storytold_engine Aktion.");
                    }
                }

                case "ag_direct_inbox":
                {
                    string action = GetString(args, "action", 32).ToLowerInvariant();
                    switch (action)
                    {
                        case "pull":
                            return await SyncAgDirectInboxAsync(peekOnly: false, isDaemonInternal: false);

                        case "list":
                            return await ListAgDirectInboxAsync();

                        case "complete":
                        {
                            string itemId = GetString(args, "id", 80);
                            string? summary = GetOptString(args, "summary", 4000);
                            return await CompleteAgDirectInboxItemAsync(itemId, summary);
                        }

                        case "set_master_chat":
                        {
                            string convId = GetString(args, "conversation_id", 80);
                            var cfg = McpSecurityGuard.LoadAgBridgeConfig();
                            cfg.MasterConversationId = McpSecurityGuard.ValidateAgConversationId(convId);
                            var saved = McpSecurityGuard.SaveAgBridgeConfig(cfg);
                            return BuildAgChannelsOverviewObject(saved);
                        }

                        case "list_channels":
                        {
                            var cfg = McpSecurityGuard.LoadAgBridgeConfig();
                            return BuildAgChannelsOverviewObject(cfg);
                        }

                        case "set_channel_chat":
                        {
                            string chan = GetString(args, "channel", 32);
                            string? convId = GetOptString(args, "conversation_id", 80);
                            return UpdateOrResetAgChannelChat(chan, convId, forceReset: false);
                        }

                        case "reset_channel_chat":
                        {
                            string chan = GetString(args, "channel", 32);
                            return UpdateOrResetAgChannelChat(chan, null, forceReset: true);
                        }

                        default:
                            throw new ArgumentException("Erlaubte ag_direct_inbox Aktionen: pull, list, complete, set_master_chat, list_channels, set_channel_chat, reset_channel_chat.");
                    }
                }

                case "tresor_list_ai_entries":
                {
                    return McpTresorBroker.ListApprovedEntries();
                }

                case "tresor_browser_login":
                {
                    string titleOrId = GetOptString(args, "titleOrId", 256)
                                    ?? GetOptString(args, "entry", 256)
                                    ?? throw new ArgumentException("Pflichtparameter 'titleOrId' (oder 'entry') fehlt.");
                    bool submit = GetBool(args, "submit", defaultVal: false);
                    bool totpOnly = GetBool(args, "totpOnly", defaultVal: false);
                    int? cdpPort = args["cdpPort"] != null ? args["cdpPort"]!.GetValue<int>() : null;
                    return await McpTresorBroker.BrowserLoginAsync(titleOrId, submit: submit, totpOnly: totpOnly, cdpPortOverride: cdpPort);
                }

                case "mittwald_api_broker":
                {
                    string preset = GetString(args, "preset", 64);
                    string? entry = GetOptString(args, "entry", 256);
                    string? projectId = GetOptString(args, "projectId", 80) ?? GetOptString(args, "project_id", 80);
                    string? appId = GetOptString(args, "appId", 80) ?? GetOptString(args, "app_id", 80);
                    string? cronjobId = GetOptString(args, "cronjobId", 80) ?? GetOptString(args, "cronjob_id", 80);
                    return await McpTresorBroker.ExecuteMittwaldApiPresetAsync(
                        preset: preset,
                        entryTitleOrId: entry,
                        projectId: projectId,
                        appId: appId,
                        cronjobId: cronjobId);
                }

                default:
                    throw new ArgumentException($"Unbekanntes Tool '{toolName}'.");
            }
        }

        private const string FunkenwerkControlPipeName = "Funkenwerk_ControlPipe_v1";

        private static async Task<object> SendFunkenwerkLocalPipeCommandAsync(
            string domain,
            string action,
            string? query,
            string? target,
            string? direction,
            int? volume,
            bool autoStartApp)
        {
            string reqJson = JsonSerializer.Serialize(new
            {
                domain,
                action,
                query,
                target,
                direction,
                volume
            }, JsonCompact);

            for (int attempt = 0; attempt < 2; attempt++)
            {
                try
                {
                    using var cts = new CancellationTokenSource(TimeSpan.FromMilliseconds(attempt == 0 ? 2500 : 5000));
                    using var pipe = new NamedPipeClientStream(
                        serverName: ".",
                        pipeName: FunkenwerkControlPipeName,
                        direction: PipeDirection.InOut,
                        options: PipeOptions.Asynchronous | PipeOptions.CurrentUserOnly);

                    await pipe.ConnectAsync(cts.Token);

                    using var writer = new StreamWriter(pipe, Utf8NoBom, bufferSize: 4096, leaveOpen: true) { AutoFlush = true };
                    using var reader = new StreamReader(pipe, Utf8NoBom, detectEncodingFromByteOrderMarks: false, bufferSize: 4096, leaveOpen: true);

                    await writer.WriteLineAsync(reqJson);
                    string? respLine = await ReadBoundedLineAsync(reader, 65536);
                    if (string.IsNullOrWhiteSpace(respLine))
                    {
                        return new { ok = false, error = "Leere Antwort von Funkenwerk IPC-Bridge." };
                    }

                    var parsed = JsonNode.Parse(respLine, documentOptions: StrictJsonParseOptions);
                    return parsed ?? (object)new { ok = true, raw = respLine };
                }
                catch (Exception) when (attempt == 0 && autoStartApp)
                {
                    LaunchCollectionExecutable("funkenwerk", null);
                    await Task.Delay(2600);
                }
                catch (TimeoutException)
                {
                    return new
                    {
                        ok = false,
                        funkenwerkRunning = false,
                        hint = "Funkenwerk Desktop Suite läuft aktuell nicht auf dem Desktop. Setze autoStartApp=true oder starte Funkenwerk über collection_control (action='launch', app='funkenwerk')."
                    };
                }
                catch (OperationCanceledException)
                {
                    return new
                    {
                        ok = false,
                        funkenwerkRunning = false,
                        hint = "Funkenwerk Desktop Suite läuft aktuell nicht auf dem Desktop. Setze autoStartApp=true oder starte Funkenwerk über collection_control (action='launch', app='funkenwerk')."
                    };
                }
            }

            return new { ok = false, funkenwerkRunning = false };
        }

        public static ProcessStartInfo BuildValidatedCollectionStartInfo(
            string appKey,
            string? userArgument,
            string? trustedInternalFlag = null,
            bool requireBinaryExists = true)
        {
            (string subFolder, string exeName) = (appKey ?? "").Trim().ToLowerInvariant() switch
            {
                "funkenwerk" => ("Funkenwerk", "Funkenwerk.exe"),
                "tresor" => ("Funkenwerk Tresor", "FunkenwerkTresor.exe"),
                "player" => ("Funkenwerk Player", "FunkenwerkPlayer.exe"),
                "video" => ("Funkenwerk Video", "FunkenwerkVideo.exe"),
                "craft" => ("Funkenwerk Craft", "FunkenwerkCraft.exe"),
                "ftp" => ("Funkenwerk FTP", "FunkenwerkFtp.exe"),
                "ssh" => ("Funkenwerk SSH", "FunkenwerkSsh.exe"),
                "pdf" => ("Funkenwerk PDF", "FunkenwerkPdf.exe"),
                "drucker" => ("Funkenwerk Drucker", "FunkenwerkDrucker.exe"),
                "os" => ("Funkenwerk OS", "FunkenwerkOS.exe"),
                "browser" => ("Funkenwerk Browser", "FunkenwerkBrowser.exe"),
                _ => throw new ArgumentException($"Unbekannte Funkenwerk-App '{appKey}'.")
            };

            string exePath = Path.Combine(McpSecurityGuard.CollectionRootDir, subFolder, exeName);
            if (requireBinaryExists && !File.Exists(exePath))
                throw new FileNotFoundException($"App-Binary nicht gefunden: {exePath}");

            var psi = new ProcessStartInfo
            {
                FileName = exePath,
                WorkingDirectory = Path.GetDirectoryName(exePath)!,
                UseShellExecute = false
            };

            if (!string.IsNullOrWhiteSpace(trustedInternalFlag))
            {
                if (appKey != "ssh" || trustedInternalFlag != "--handoff")
                    throw new UnauthorizedAccessException("Unbekanntes internes Handoff-Flag.");
                psi.ArgumentList.Add("--handoff");
            }

            if (!string.IsNullOrWhiteSpace(userArgument))
            {
                string cleanArg = userArgument.Trim();
                if (cleanArg.StartsWith('-') || cleanArg.StartsWith('/'))
                    throw new UnauthorizedAccessException("CLI-Flags (beginnend mit '-' oder '/') sind in LaunchCollectionExecutable strikt verboten.");
                if (cleanArg.Any(c => c == '\0' || c == '\r' || c == '\n'))
                    throw new UnauthorizedAccessException("Steuerzeichen im Argument sind verboten.");

                psi.ArgumentList.Add(cleanArg);
            }

            return psi;
        }

        private static bool LaunchCollectionExecutable(string appKey, string? userArgument, string? trustedInternalFlag = null)
        {
            var psi = BuildValidatedCollectionStartInfo(appKey, userArgument, trustedInternalFlag, requireBinaryExists: true);
            Process.Start(psi);
            return true;
        }

        private static string GetString(JsonObject obj, string key, int maxLen = McpSecurityGuard.MaxStringArgumentChars)
        {
            string val = obj[key]?.GetValue<string>() ?? throw new ArgumentException($"Pflichtparameter '{key}' fehlt.");
            if (val.Length > maxLen)
                throw new InvalidOperationException($"Parameter '{key}' überschreitet das Sicherheitslimit von {maxLen} Zeichen.");
            return val;
        }

        private static string? GetOptString(JsonObject obj, string key, int maxLen = McpSecurityGuard.MaxStringArgumentChars)
        {
            string? val = obj[key]?.GetValue<string>();
            if (val != null && val.Length > maxLen)
                throw new InvalidOperationException($"Parameter '{key}' überschreitet das Sicherheitslimit von {maxLen} Zeichen.");
            return val;
        }

        private static bool GetBool(JsonObject obj, string key, bool defaultVal) =>
            obj[key] != null ? obj[key]!.GetValue<bool>() : defaultVal;
    }

    // =========================================================================
    // PORTABLE, DETERMINISTIC 36-POINT SECURITY & E2E SELF-TEST SUITE
    // =========================================================================
    public static class McpSelfTestSuite
    {
        private sealed class MockOrganizerHttpHandler : HttpMessageHandler
        {
            public List<(HttpMethod Method, Uri Uri, string Body)> CapturedRequests { get; } = new();

            protected override async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
            {
                string body = request.Content != null ? await request.Content.ReadAsStringAsync(cancellationToken) : "";
                lock (CapturedRequests)
                {
                    CapturedRequests.Add((request.Method, request.RequestUri!, body));
                }

                string pathAndQuery = request.RequestUri!.PathAndQuery;
                string responseJson;

                if (pathAndQuery.StartsWith("/api/funki/tasks/lists/", StringComparison.OrdinalIgnoreCase) && request.Method == HttpMethod.Post)
                {
                    using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(body) ? "{}" : body);
                    string prio = doc.RootElement.TryGetProperty("priority", out var p) ? p.GetString() ?? "mittel" : "mittel";
                    string title = doc.RootElement.TryGetProperty("title", out var t) ? t.GetString() ?? "" : "";
                    responseJson = JsonSerializer.Serialize(new
                    {
                        success = true,
                        data = new { id = "99999999-1111-2222-3333-444444444444", title, priority = prio, is_completed = false }
                    });
                }
                else if (pathAndQuery.StartsWith("/api/funki/tasks/lists", StringComparison.OrdinalIgnoreCase))
                {
                    responseJson = """
                    {
                      "success": true,
                      "data": [
                        {
                          "id": "11111111-1111-1111-1111-111111111111",
                          "name": "Operativer Status",
                          "tasks": [
                            { "id": "22222222-2222-2222-2222-222222222222", "title": "Laser Rohlinge pruefen", "priority": "hoch", "is_completed": false }
                          ]
                        }
                      ]
                    }
                    """;
                }
                else if (pathAndQuery.StartsWith("/api/funki/calendar/events", StringComparison.OrdinalIgnoreCase) && request.Method == HttpMethod.Get)
                {
                    responseJson = """
                    {
                      "success": true,
                      "data": [
                        { "id": "33333333-3333-3333-3333-333333333333", "title": "Steuerberater Export", "start": "2026-11-14T10:00:00", "end": "2026-11-14T11:00:00", "is_all_day": false, "category": "business" }
                      ]
                    }
                    """;
                }
                else if (pathAndQuery.StartsWith("/api/funki/calendar/events", StringComparison.OrdinalIgnoreCase) && request.Method == HttpMethod.Post)
                {
                    responseJson = """{"success":true,"data":{"id":"44444444-4444-4444-4444-444444444444"}}""";
                }
                else if (pathAndQuery.StartsWith("/api/funki/shopping/categories", StringComparison.OrdinalIgnoreCase))
                {
                    responseJson = """
                    {
                      "success": true,
                      "data": [
                        { "id": "cat-uuid-verpackung-001", "name": "Verpackung" },
                        { "id": "cat-uuid-rohlinge-002", "name": "Rohlinge" }
                      ]
                    }
                    """;
                }
                else if (pathAndQuery.StartsWith("/api/funki/shopping/items", StringComparison.OrdinalIgnoreCase) && request.Method == HttpMethod.Post)
                {
                    using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(body) ? "{}" : body);
                    string? catId = doc.RootElement.TryGetProperty("shopping_category_id", out var sc) ? sc.GetString() : null;
                    string? itemName = doc.RootElement.TryGetProperty("name", out var nm) ? nm.GetString() : null;
                    responseJson = JsonSerializer.Serialize(new
                    {
                        success = true,
                        data = new { id = "shop-item-001", name = itemName, shopping_category_id = catId }
                    });
                }
                else if (pathAndQuery.StartsWith("/api/funki/shopping/items", StringComparison.OrdinalIgnoreCase))
                {
                    responseJson = """
                    {
                      "success": true,
                      "categories": [
                        { "id": "cat-uuid-verpackung-001", "name": "Verpackung" },
                        { "id": "cat-uuid-rohlinge-002", "name": "Rohlinge" }
                      ],
                      "data": [
                        { "id": "shop-item-001", "name": "Westpack Boxen", "quantity": 50 }
                      ]
                    }
                    """;
                }
                else
                {
                    responseJson = """{"success":true,"data":{}}""";
                }

                return new HttpResponseMessage(HttpStatusCode.OK)
                {
                    Content = new StringContent(responseJson, Encoding.UTF8, "application/json")
                };
            }
        }

        private sealed class MockMittwaldHttpHandler : HttpMessageHandler
        {
            public List<(HttpMethod Method, Uri Uri, string? Bearer, string? XAccessToken)> Captured { get; } = new();
            public List<(HttpMethod Method, string PathAndQuery, string BodyJson)> CapturedBodies { get; } = new();

            public string LiveDocRoot { get; set; } = "/";
            public bool LiveWwwIngressCreated { get; set; }
            public bool LiveWwwDnsZoneCreated { get; set; }
            public bool LiveWwwDnsManaged { get; set; }
            public bool LiveDigitalIngressExists { get; set; } = true;
            public bool LiveDigitalDnsZoneExists { get; set; } = true;
            public bool LiveCronCreated { get; set; }
            public int LiveMysqlPasswordPatchCount { get; set; }

            protected override async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
            {
                string? bearer = request.Headers.Authorization?.Parameter;
                string? xTok = request.Headers.TryGetValues("x-access-token", out var vals) ? vals.FirstOrDefault() : null;
                string bodyStr = request.Content != null ? await request.Content.ReadAsStringAsync(cancellationToken) : "";
                lock (Captured)
                {
                    Captured.Add((request.Method, request.RequestUri!, bearer, xTok));
                    CapturedBodies.Add((request.Method, request.RequestUri!.PathAndQuery, bodyStr));
                }

                string p = request.RequestUri!.PathAndQuery;
                HttpStatusCode code = HttpStatusCode.OK;
                string json = "{}";

                if (p == "/v2/users/self" && request.Method == HttpMethod.Get)
                {
                    // Intentionally echo token inside a field to prove Outbound DLP scrubbing strips it!
                    json = JsonSerializer.Serialize(new
                    {
                        userId = "u-alina-sf",
                        email = "kontakt@mein-seelenfunke.de",
                        echoLeak = bearer ?? ""
                    });
                }
                else if (p == "/v2/projects" && request.Method == HttpMethod.Get)
                {
                    json = """[{"id":"proj-uuid-sf-01","shortId":"p-g27wim","description":"Mein-Seelenfunke Hosting"}]""";
                }
                else if (p == "/v2/projects/proj-uuid-sf-01/app-installations" && request.Method == HttpMethod.Get)
                {
                    json = """
                    [
                      {"id":"app-uuid-stage-web","shortId":"a-lc6tkm","description":"seelenfunke-stage","installationPath":"/html/seelenfunke-stage"},
                      {"id":"app-uuid-stage-worker","shortId":"a-iurgq8","description":"worker-stage-3","installationPath":"/html/worker-stage-3"},
                      {"id":"app-uuid-live-web","shortId":"a-bycqrf","description":"seelenfunke-live","installationPath":"/html/seelenfunke-live"}
                    ]
                    """;
                }
                else if (p == "/v2/app-installations/a-lc6tkm" && request.Method == HttpMethod.Get)
                {
                    // Simulate Mittwald requiring UUID on direct GET so shortId->UUID resolution is exercised!
                    code = HttpStatusCode.NotFound;
                    json = """{"message":"App installation not found by shortId"}""";
                }
                else if (p == "/v2/app-installations/app-uuid-stage-web" && request.Method == HttpMethod.Get)
                {
                    json = """{"id":"app-uuid-stage-web","shortId":"a-lc6tkm","description":"seelenfunke-stage","installationPath":"/html/seelenfunke-stage"}""";
                }
                else if (p == "/v2/app-installations/app-uuid-live-web" && request.Method == HttpMethod.Get)
                {
                    json = """{"id":"app-uuid-live-web","shortId":"a-bycqrf","description":"seelenfunke-live","installationPath":"/html/seelenfunke-live"}""";
                }
                else if (p == "/v2/app-installations/app-uuid-ambiguous" && request.Method == HttpMethod.Get)
                {
                    json = """{"id":"app-uuid-ambiguous","shortId":"a-zzzz99","description":"kundenportal","installationPath":"/html/portal"}""";
                }
                else if (p == "/v2/app-installations/app-uuid-stage-web/actions/restart" && request.Method == HttpMethod.Post)
                {
                    code = HttpStatusCode.NoContent;
                    json = "";
                }
                else if (p == "/v2/projects/proj-uuid-sf-01/cronjobs" && request.Method == HttpMethod.Get)
                {
                    json = """[{"id":"cron-stage-01","shortId":"cj-stage1","description":"Stage Scheduler","appId":"app-uuid-stage-web"}]""";
                }
                else if (p == "/v2/ingresses?projectId=proj-uuid-sf-01" && request.Method == HttpMethod.Get)
                {
                    json = """[{"id":"ing-stage-01","hostname":"stage.mein-seelenfunke.de"}]""";
                }
                else if (p == "/v2/cronjobs/cron-stage-01" && request.Method == HttpMethod.Get)
                {
                    json = """{"id":"cron-stage-01","shortId":"cj-stage1","description":"Stage Queue Check","appId":"app-uuid-stage-web","destination":{"url":"https://stage.mein-seelenfunke.de/cron"}}""";
                }
                else if (p == "/v2/cronjobs/cron-stage-01/actions/execute" && request.Method == HttpMethod.Post)
                {
                    code = HttpStatusCode.NoContent;
                    json = "";
                }
                else if (p == "/v2/cronjobs/cron-generic-on-live-app" && request.Method == HttpMethod.Get)
                {
                    // Generic cronjob title without 'live', but owned by Live app app-uuid-live-web -> must still be caught & blocked!
                    json = """{"id":"cron-generic-on-live-app","shortId":"cj-999","description":"Hourly Artisan Schedule","appId":"app-uuid-live-web"}""";
                }
                // --- Pinned Seelenfunke-Live CLI mock endpoints ---
                else if (p == $"/v2/app-installations/{McpSecurityGuard.PinnedLiveAppUuid}" && request.Method == HttpMethod.Get)
                {
                    json = JsonSerializer.Serialize(new
                    {
                        id = McpSecurityGuard.PinnedLiveAppUuid,
                        projectId = McpSecurityGuard.PinnedLiveProjectId,
                        shortId = McpSecurityGuard.PinnedLiveAppShortId,
                        description = "seelenfunke-live",
                        installationPath = "/seelenfunke-live",
                        customDocumentRoot = LiveDocRoot
                    });
                }
                else if (p == $"/v2/app-installations/{McpSecurityGuard.PinnedLiveAppUuid}" && request.Method == HttpMethod.Patch)
                {
                    using var doc = JsonDocument.Parse(bodyStr);
                    if (doc.RootElement.TryGetProperty("customDocumentRoot", out var dr))
                        LiveDocRoot = dr.GetString() ?? "/";
                    code = HttpStatusCode.NoContent;
                    json = "";
                }
                else if (p == $"/v2/ingresses?projectId={McpSecurityGuard.PinnedLiveProjectId}" && request.Method == HttpMethod.Get)
                {
                    var items = new List<string>
                    {
                        $$$"""{"id":"{{{McpSecurityGuard.PinnedLiveApexIngressUuid}}}","hostname":"mein-seelenfunke.de"}"""
                    };
                    if (LiveWwwIngressCreated)
                    {
                        items.Add($$$"""{"id":"11111111-2222-4333-8444-555555555555","hostname":"www.mein-seelenfunke.de","paths":[{"path":"/","target":{"installationId":"{{{McpSecurityGuard.PinnedLiveAppUuid}}}"}}],"tls":{"acme":true,"isCreated":true},"ownership":{"verified":true}}""");
                    }
                    if (LiveDigitalIngressExists)
                    {
                        items.Add($$$"""{"id":"88888888-9999-4aaa-8bbb-cccccccccccc","hostname":"digital.mein-seelenfunke.de","paths":[{"path":"/","target":{"installationId":"{{{McpSecurityGuard.PinnedLiveAppUuid}}}"}}],"tls":{"acme":true,"isCreated":true},"ownership":{"verified":true}}""");
                    }
                    json = "[" + string.Join(",", items) + "]";
                }
                else if (p == "/v2/ingresses" && request.Method == HttpMethod.Post)
                {
                    LiveWwwIngressCreated = true;
                    code = HttpStatusCode.Created;
                    json = """{"id":"11111111-2222-4333-8444-555555555555","ownership":{"verified":true,"txtRecord":""}}""";
                }
                else if (p == "/v2/ingresses/11111111-2222-4333-8444-555555555555" && request.Method == HttpMethod.Get)
                {
                    json = $$$"""{"id":"11111111-2222-4333-8444-555555555555","hostname":"www.mein-seelenfunke.de","projectId":"{{{McpSecurityGuard.PinnedLiveProjectId}}}","paths":[{"path":"/","target":{"installationId":"{{{McpSecurityGuard.PinnedLiveAppUuid}}}"}}],"tls":{"acme":true,"isCreated":true},"ownership":{"verified":true}}""";
                }
                else if (p == "/v2/ingresses/88888888-9999-4aaa-8bbb-cccccccccccc" && request.Method == HttpMethod.Delete)
                {
                    LiveDigitalIngressExists = false;
                    code = HttpStatusCode.NoContent;
                    json = "";
                }
                else if (p == $"/v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/dns-zones" && request.Method == HttpMethod.Get)
                {
                    var zItems = new List<string>
                    {
                        """{"id":"4fbe5480-bc45-4edc-8a38-6c9648c00f4e","domain":"mein-seelenfunke.de"}"""
                    };
                    if (LiveWwwDnsZoneCreated)
                    {
                        zItems.Add(LiveWwwDnsManaged
                            ? """{"id":"66666666-7777-4888-9999-aaaaaaaaaaaa","domain":"www.mein-seelenfunke.de","recordSet":{"combinedARecords":{"managedBy":{"ingressId":"11111111-2222-4333-8444-555555555555"}}}}"""
                            : """{"id":"66666666-7777-4888-9999-aaaaaaaaaaaa","domain":"www.mein-seelenfunke.de","recordSet":{"combinedARecords":{}}}""");
                    }
                    if (LiveDigitalDnsZoneExists)
                    {
                        zItems.Add("""{"id":"22222222-3333-4444-8555-666666666666","domain":"digital.mein-seelenfunke.de","recordSet":{"combinedARecords":{"managedBy":{"ingressId":"88888888-9999-4aaa-8bbb-cccccccccccc"}}}}""");
                    }
                    json = "[" + string.Join(",", zItems) + "]";
                }
                else if ((p == "/v2/dns-zones" || p == $"/v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/dns-zones") && request.Method == HttpMethod.Post)
                {
                    LiveWwwDnsZoneCreated = true;
                    code = HttpStatusCode.Created;
                    json = """{"id":"66666666-7777-4888-9999-aaaaaaaaaaaa"}""";
                }
                else if (p == "/v2/dns-zones/66666666-7777-4888-9999-aaaaaaaaaaaa" && request.Method == HttpMethod.Get)
                {
                    json = LiveWwwDnsManaged
                        ? """{"id":"66666666-7777-4888-9999-aaaaaaaaaaaa","domain":"www.mein-seelenfunke.de","recordSet":{"combinedARecords":{"managedBy":{"ingressId":"11111111-2222-4333-8444-555555555555"}}}}"""
                        : """{"id":"66666666-7777-4888-9999-aaaaaaaaaaaa","domain":"www.mein-seelenfunke.de","recordSet":{"combinedARecords":{}}}""";
                }
                else if (p == "/v2/dns-zones/22222222-3333-4444-8555-666666666666" && request.Method == HttpMethod.Delete)
                {
                    LiveDigitalDnsZoneExists = false;
                    code = HttpStatusCode.NoContent;
                    json = "";
                }
                else if (p == "/v2/dns-zones/66666666-7777-4888-9999-aaaaaaaaaaaa/record-sets/a/actions/set-managed" && request.Method == HttpMethod.Post)
                {
                    LiveWwwDnsManaged = true;
                    code = HttpStatusCode.OK;
                    json = """{"ingressId":"11111111-2222-4333-8444-555555555555"}""";
                }
                else if (p == $"/v2/mysql-users/{McpSecurityGuard.PinnedLiveMysqlUserUuid}/password" && request.Method == HttpMethod.Patch)
                {
                    LiveMysqlPasswordPatchCount++;
                    code = HttpStatusCode.NoContent;
                    json = "";
                }
                else if (p == $"/v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/cronjobs" && request.Method == HttpMethod.Get)
                {
                    json = LiveCronCreated
                        ? $$$"""[{"id":"cron-stage-01","appInstallationId":"c505b682-4bf2-4801-8b04-d3f525185daf"},{"id":"77777777-8888-4999-aaaa-bbbbbbbbbbbb","shortId":"cron-live01","description":"Seelenfunke Live - Scheduler","appInstallationId":"{{{McpSecurityGuard.PinnedLiveAppUuid}}}","target":{"appInstallationId":"{{{McpSecurityGuard.PinnedLiveAppUuid}}}"}}]"""
                        : """[{"id":"cron-stage-01","appInstallationId":"c505b682-4bf2-4801-8b04-d3f525185daf"}]""";
                }
                else if (p == $"/v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/cronjobs" && request.Method == HttpMethod.Post)
                {
                    LiveCronCreated = true;
                    code = HttpStatusCode.Created;
                    json = """{"id":"77777777-8888-4999-aaaa-bbbbbbbbbbbb"}""";
                }
                else
                {
                    code = HttpStatusCode.NotFound;
                    json = """{"message":"not found"}""";
                }

                var resp = new HttpResponseMessage(code);
                if (!string.IsNullOrEmpty(json))
                    resp.Content = new StringContent(json, Encoding.UTF8, "application/json");
                return resp;
            }
        }

        private sealed class MockCdpLoopbackServer : IAsyncDisposable
        {
            private readonly TcpListener _listener;
            private readonly CancellationTokenSource _cts = new();
            private readonly Task _acceptLoop;

            public int Port { get; }
            public string ActiveTabUrl { get; set; } = "https://login.mittwald.de/sso";
            public bool SimulateMultiStep { get; set; } = true;
            public bool DisableHttpJsonList { get; set; }
            public bool FlattenAttachUsed { get; private set; }
            public int CallFunctionCount { get; private set; }
            public string? CapturedUsername { get; private set; }
            public string? CapturedPassword { get; private set; }
            public string? CapturedTotp { get; private set; }
            public bool CapturedSubmitted { get; private set; }

            private int _stepPhase; // 0 = email only, 1 = password visible, 2 = otp visible

            public MockCdpLoopbackServer()
            {
                _listener = new TcpListener(IPAddress.Loopback, 0);
                _listener.Start();
                Port = ((IPEndPoint)_listener.LocalEndpoint).Port;
                _acceptLoop = Task.Run(AcceptLoopAsync);
            }

            public void ResetState(string tabUrl, bool multiStep, bool disableJsonList)
            {
                ActiveTabUrl = tabUrl;
                SimulateMultiStep = multiStep;
                DisableHttpJsonList = disableJsonList;
                FlattenAttachUsed = false;
                CallFunctionCount = 0;
                CapturedUsername = null;
                CapturedPassword = null;
                CapturedTotp = null;
                CapturedSubmitted = false;
                _stepPhase = 0;
            }

            private async Task AcceptLoopAsync()
            {
                while (!_cts.IsCancellationRequested)
                {
                    TcpClient client;
                    try
                    {
                        client = await _listener.AcceptTcpClientAsync(_cts.Token);
                    }
                    catch
                    {
                        break;
                    }
                    _ = Task.Run(() => HandleClientAsync(client));
                }
            }

            private async Task HandleClientAsync(TcpClient tcp)
            {
                using (tcp)
                await using (var stream = tcp.GetStream())
                {
                    var headerSb = new StringBuilder();
                    byte[] one = new byte[1];
                    while (headerSb.Length < 8192)
                    {
                        int n = await stream.ReadAsync(one.AsMemory(0, 1), _cts.Token);
                        if (n <= 0) return;
                        headerSb.Append((char)one[0]);
                        if (headerSb.Length >= 4 &&
                            headerSb[^4] == '\r' && headerSb[^3] == '\n' &&
                            headerSb[^2] == '\r' && headerSb[^1] == '\n')
                        {
                            break;
                        }
                    }

                    string rawHeaders = headerSb.ToString();
                    string[] lines = rawHeaders.Split("\r\n", StringSplitOptions.RemoveEmptyEntries);
                    if (lines.Length == 0) return;

                    if (lines[0].StartsWith("GET /json/list ", StringComparison.OrdinalIgnoreCase))
                    {
                        if (DisableHttpJsonList)
                        {
                            byte[] r404 = Encoding.UTF8.GetBytes("HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
                            await stream.WriteAsync(r404, _cts.Token);
                            return;
                        }

                        string listBody = JsonSerializer.Serialize(new[]
                        {
                            new
                            {
                                id = "page-1",
                                type = "page",
                                url = ActiveTabUrl,
                                webSocketDebuggerUrl = $"ws://127.0.0.1:{Port}/devtools/page/page-1"
                            }
                        });
                        byte[] bodyBytes = Encoding.UTF8.GetBytes(listBody);
                        byte[] hdrBytes = Encoding.ASCII.GetBytes(
                            $"HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: {bodyBytes.Length}\r\nConnection: close\r\n\r\n");
                        await stream.WriteAsync(hdrBytes, _cts.Token);
                        await stream.WriteAsync(bodyBytes, _cts.Token);
                        return;
                    }

                    string? secKey = null;
                    foreach (string h in lines)
                    {
                        if (h.StartsWith("Sec-WebSocket-Key:", StringComparison.OrdinalIgnoreCase))
                            secKey = h.Substring("Sec-WebSocket-Key:".Length).Trim();
                    }
                    if (string.IsNullOrEmpty(secKey)) return;

                    string accept = Convert.ToBase64String(
                        SHA1.HashData(Encoding.ASCII.GetBytes(secKey + "258EAFA5-E914-47DA-95CA-C5AB0DC85B11")));
                    byte[] upgradeResp = Encoding.ASCII.GetBytes(
                        $"HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {accept}\r\n\r\n");
                    await stream.WriteAsync(upgradeResp, _cts.Token);

                    using var ws = WebSocket.CreateFromStream(stream, isServer: true, subProtocol: null, keepAliveInterval: TimeSpan.FromSeconds(30));
                    byte[] buf = new byte[32768];

                    while (ws.State == WebSocketState.Open && !_cts.IsCancellationRequested)
                    {
                        using var msgMs = new MemoryStream();
                        WebSocketReceiveResult r;
                        do
                        {
                            r = await ws.ReceiveAsync(new ArraySegment<byte>(buf), _cts.Token);
                            if (r.MessageType == WebSocketMessageType.Close)
                            {
                                await ws.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "bye", CancellationToken.None);
                                return;
                            }
                            msgMs.Write(buf, 0, r.Count);
                        }
                        while (!r.EndOfMessage);

                        var req = JsonNode.Parse(msgMs.ToArray()) as JsonObject;
                        if (req == null) continue;

                        int id = req["id"]?.GetValue<int>() ?? 0;
                        string? sessId = req["sessionId"]?.GetValue<string>();
                        string method = req["method"]?.GetValue<string>() ?? "";
                        var pObj = req["params"] as JsonObject;

                        JsonObject resultObj;
                        if (method == "Target.getTargets")
                        {
                            resultObj = new JsonObject
                            {
                                ["targetInfos"] = new JsonArray(
                                    new JsonObject
                                    {
                                        ["targetId"] = "target-mw-1",
                                        ["type"] = "page",
                                        ["url"] = ActiveTabUrl
                                    })
                            };
                        }
                        else if (method == "Target.attachToTarget")
                        {
                            if (pObj?["flatten"]?.GetValue<bool>() == true)
                                FlattenAttachUsed = true;
                            resultObj = new JsonObject { ["sessionId"] = "sess-flat-1" };
                        }
                        else if (method == "Target.detachFromTarget")
                        {
                            resultObj = new JsonObject();
                        }
                        else if (method == "Runtime.evaluate")
                        {
                            string expr = pObj?["expression"]?.GetValue<string>() ?? "";
                            if (expr.Contains("location.href", StringComparison.Ordinal))
                            {
                                resultObj = new JsonObject
                                {
                                    ["result"] = new JsonObject { ["type"] = "string", ["value"] = ActiveTabUrl }
                                };
                            }
                            else
                            {
                                resultObj = new JsonObject
                                {
                                    ["result"] = new JsonObject { ["type"] = "object", ["objectId"] = "win-obj-1" }
                                };
                            }
                        }
                        else if (method == "Runtime.callFunctionOn")
                        {
                            CallFunctionCount++;
                            var argsArr = pObj?["arguments"] as JsonArray;
                            string mode = argsArr?[0]?["value"]?.GetValue<string>() ?? "";
                            string uVal = argsArr?[1]?["value"]?.GetValue<string>() ?? "";
                            string pVal = argsArr?[2]?["value"]?.GetValue<string>() ?? "";
                            string tVal = argsArr?[3]?["value"]?.GetValue<string>() ?? "";
                            bool sub = argsArr?[4]?["value"]?.GetValue<bool>() ?? false;

                            JsonObject valPayload;
                            if (mode == "probe")
                            {
                                valPayload = new JsonObject
                                {
                                    ["hasUser"] = _stepPhase == 0,
                                    ["hasPass"] = _stepPhase == 1 || !SimulateMultiStep,
                                    ["hasOtp"] = _stepPhase == 2,
                                    ["href"] = ActiveTabUrl
                                };
                            }
                            else if (mode == "credentials")
                            {
                                CapturedUsername = uVal;
                                if (!SimulateMultiStep)
                                {
                                    CapturedPassword = pVal;
                                    CapturedSubmitted = sub;
                                    valPayload = new JsonObject
                                    {
                                        ["filledUser"] = !string.IsNullOrEmpty(uVal),
                                        ["filledPass"] = !string.IsNullOrEmpty(pVal),
                                        ["filledTotp"] = false,
                                        ["submitted"] = sub,
                                        ["hasPassNow"] = true,
                                        ["hasOtpNow"] = false
                                    };
                                }
                                else
                                {
                                    // Multi-step Step 1: only email visible initially
                                    if (sub) _stepPhase = 1;
                                    CapturedSubmitted = sub;
                                    valPayload = new JsonObject
                                    {
                                        ["filledUser"] = !string.IsNullOrEmpty(uVal),
                                        ["filledPass"] = false,
                                        ["filledTotp"] = false,
                                        ["submitted"] = sub,
                                        ["hasPassNow"] = false,
                                        ["hasOtpNow"] = false
                                    };
                                }
                            }
                            else if (mode == "password_only")
                            {
                                CapturedPassword = pVal;
                                if (sub) _stepPhase = 2;
                                CapturedSubmitted = sub;
                                valPayload = new JsonObject
                                {
                                    ["filledUser"] = false,
                                    ["filledPass"] = !string.IsNullOrEmpty(pVal),
                                    ["filledTotp"] = false,
                                    ["submitted"] = sub,
                                    ["hasPassNow"] = true,
                                    ["hasOtpNow"] = false
                                };
                            }
                            else // totp_only
                            {
                                CapturedTotp = tVal;
                                CapturedSubmitted = sub;
                                valPayload = new JsonObject
                                {
                                    ["filledUser"] = false,
                                    ["filledPass"] = false,
                                    ["filledTotp"] = !string.IsNullOrEmpty(tVal),
                                    ["submitted"] = sub,
                                    ["hasPassNow"] = false,
                                    ["hasOtpNow"] = true
                                };
                            }

                            resultObj = new JsonObject
                            {
                                ["result"] = new JsonObject
                                {
                                    ["type"] = "object",
                                    ["value"] = valPayload
                                }
                            };
                        }
                        else
                        {
                            resultObj = new JsonObject();
                        }

                        var respObj = new JsonObject
                        {
                            ["id"] = id,
                            ["result"] = resultObj
                        };
                        if (!string.IsNullOrEmpty(sessId))
                            respObj["sessionId"] = sessId;

                        byte[] outBytes = Encoding.UTF8.GetBytes(respObj.ToJsonString());
                        await ws.SendAsync(new ArraySegment<byte>(outBytes), WebSocketMessageType.Text, endOfMessage: true, _cts.Token);
                    }
                }
            }

            public async ValueTask DisposeAsync()
            {
                _cts.Cancel();
                try { _listener.Stop(); } catch { }
                try { await _acceptLoop; } catch { }
                _cts.Dispose();
            }
        }

        public static async Task<int> RunAllAsync()
        {
            int passed = 0;
            int failed = 0;

            void AssertTest(string name, bool condition, string detail = "")
            {
                if (condition)
                {
                    passed++;
                    Console.WriteLine($"  [PASS] {name}");
                }
                else
                {
                    failed++;
                    Console.WriteLine($"  [FAIL] {name} -> {detail}");
                }
            }

            void AssertThrowsSecurity(string name, Action act)
            {
                try
                {
                    act();
                    AssertTest(name, false, "Erwartete Sicherheits-Blockade trat NICHT ein!");
                }
                catch (Exception ex) when (ex is UnauthorizedAccessException || ex is ArgumentException || ex is InvalidOperationException)
                {
                    AssertTest(name, true);
                }
            }

            string tempRoot = Path.Combine(Path.GetTempPath(), $"FunkenwerkMcpSelfTest_{Guid.NewGuid():N}");
            string tempVaultDir = Path.Combine(tempRoot, "Vault");
            string tempDocsDir = Path.Combine(tempRoot, "AllowedDocs");
            Directory.CreateDirectory(tempVaultDir);
            Directory.CreateDirectory(tempDocsDir);

            using var mockHandler = new MockOrganizerHttpHandler();
            using var mockClient = new HttpClient(mockHandler) { Timeout = TimeSpan.FromSeconds(5) };

            try
            {
                using var vaultScope = McpSecurityGuard.UseTemporaryVaultDirectory(tempVaultDir, tempDocsDir);
                McpSecurityGuard.ResetRateLimiterForTests();
                McpOrganizerClient.TestHttpClientOverride = mockClient;
                McpOrganizerClient.TestBaseUrlOverride = "https://stage.mein-seelenfunke.de/api";

                Console.WriteLine("=== FUNKENWERK MCP v1.8 HARDENED ZERO-TRUST & E2E SELF-TEST (63 CHECKS) ===");

                // 01. Path Traversal Defense
                AssertThrowsSecurity("01. Block Path Traversal (..\\..\\Windows\\System32)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(tempDocsDir, @"..\..\Windows\System32\drivers\etc\hosts"), new[] { ".pdf" }, false));

                // 02. SSH Private Key Sandbox Defense
                string userHome = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
                AssertThrowsSecurity("02. Block .ssh Private Key Access (id_ed25519)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(userHome, ".ssh", "id_ed25519"), Array.Empty<string>(), false));

                // 03. Antigravity .gemini Config Defense
                AssertThrowsSecurity("03. Block .gemini Config Access (mcp_config.json)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(userHome, ".gemini", "config", "mcp_config.json"), Array.Empty<string>(), false));

                // 04. AppData DPAPI Vault Defense
                AssertThrowsSecurity("04. Block AppData Vault Access (profiles_vault.json)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(
                        Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkFtp", "profiles_vault.json"),
                        Array.Empty<string>(), false));

                // 05. Funkenwerk Collection Suite Directory Defense
                AssertThrowsSecurity("05. Block Entire Desktop\\Funkenwerk Collection Binary/Vault Tree", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(McpSecurityGuard.CollectionRootDir, "Funkenwerk PDF", "trojan.pdf"), new[] { ".pdf" }, false));

                // 06. NTFS Alternate Data Stream (ADS) Defense
                AssertThrowsSecurity("06. Block NTFS Alternate Data Stream (test.pdf:Zone.Identifier)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(tempDocsDir, "test.pdf:Zone.Identifier"), new[] { ".pdf" }, false));

                // 07. Executable Extension Defense
                AssertThrowsSecurity("07. Block Executable File Extensions (.exe / .ps1 / .lnk)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(tempDocsDir, "exploit.ps1"), new[] { ".ps1" }, false));

                // 08. Windows DOS Reserved Device Names Defense
                AssertThrowsSecurity("08. Block Windows DOS Reserved Device Names (CON.pdf)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(tempDocsDir, "CON.pdf"), new[] { ".pdf" }, false));

                // 09. Disguised Non-PDF File Rejected + UTF-8 BOM Offset-3 PDF Accepted
                string fakePdf = Path.Combine(tempDocsDir, "_fake_env.pdf");
                string bomPdf = Path.Combine(tempDocsDir, "_valid_bom.pdf");
                await File.WriteAllTextAsync(fakePdf, "APP_KEY=base64:FakeDisguisedEnvFileNotAPdf");
                byte[] syntheticPdfBytes = McpPdfEngine.CreateSyntheticAcroFormPdfBytes();
                byte[] bomPdfBytes = new byte[3 + syntheticPdfBytes.Length];
                bomPdfBytes[0] = 0xEF; bomPdfBytes[1] = 0xBB; bomPdfBytes[2] = 0xBF;
                Buffer.BlockCopy(syntheticPdfBytes, 0, bomPdfBytes, 3, syntheticPdfBytes.Length);
                await File.WriteAllBytesAsync(bomPdf, bomPdfBytes);
                bool disguisedBlocked = false;
                try { McpSecurityGuard.ValidateSafeFilePath(fakePdf, new[] { ".pdf" }, mustExist: true); }
                catch (UnauthorizedAccessException) { disguisedBlocked = true; }
                string validatedBom = McpSecurityGuard.ValidateSafeFilePath(bomPdf, new[] { ".pdf" }, mustExist: true);
                AssertTest("09. Disguised Non-PDF Blocked & Valid Offset-0 / UTF-8 BOM Offset-3 PDF Accepted",
                    disguisedBlocked && File.Exists(validatedBom));

                // 10. Polyglot PDF with '%PDF-' Shifted to Byte Offset 20 Strictly Rejected
                string polyglotPdf = Path.Combine(tempDocsDir, "_polyglot_offset20.pdf");
                byte[] polyBytes = new byte[20 + syntheticPdfBytes.Length];
                Encoding.ASCII.GetBytes("MZ_HTML_POLYGLOT_PAD").CopyTo(polyBytes, 0);
                Buffer.BlockCopy(syntheticPdfBytes, 0, polyBytes, 20, syntheticPdfBytes.Length);
                await File.WriteAllBytesAsync(polyglotPdf, polyBytes);
                AssertThrowsSecurity("10. Block Polyglot File With '%PDF-' Shifted to Byte Offset 20", () =>
                    McpSecurityGuard.ValidateSafeFilePath(polyglotPdf, new[] { ".pdf" }, mustExist: true));

                // 11. NTFS Junction / Reparse Point Ancestry Blocked
                string junctionTarget = Path.Combine(tempRoot, "OutsideSecretDir");
                Directory.CreateDirectory(junctionTarget);
                string secretTargetPdf = Path.Combine(junctionTarget, "secret.pdf");
                await File.WriteAllBytesAsync(secretTargetPdf, syntheticPdfBytes);
                string junctionLink = Path.Combine(tempDocsDir, "JunctionEscape");
                using (var mkProc = Process.Start(new ProcessStartInfo("cmd.exe")
                {
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    ArgumentList = { "/c", "mklink", "/J", junctionLink, junctionTarget }
                })!)
                {
                    await mkProc.WaitForExitAsync();
                }
                string viaJunctionFile = Path.Combine(junctionLink, "secret.pdf");
                bool reparseDetected = McpSecurityGuard.HasReparsePointInAncestry(viaJunctionFile);
                bool safePathBlockedJunction = false;
                try { McpSecurityGuard.ValidateSafeFilePath(viaJunctionFile, new[] { ".pdf" }, mustExist: true); }
                catch (UnauthorizedAccessException) { safePathBlockedJunction = true; }
                bool craftBlockedJunction = false;
                try { McpCraftBridge.ValidateSafeMediaSourceOrCraftPath(viaJunctionFile, allowAudio: false); }
                catch (UnauthorizedAccessException) { craftBlockedJunction = true; }
                try { Directory.Delete(junctionLink); } catch { }
                AssertTest("11. NTFS Junction / Reparse Point Ancestry Blocked Across SafePath & Craft",
                    reparseDetected && safePathBlockedJunction && craftBlockedJunction);

                // 12. Outbound DLP Scrubber Redacts Sanctum, FWAES256GCM1, FWVAULT2, JWT, JSON password & RTSP URLs
                string simulatedLeak =
                    "Sanctum: 12|AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCD " +
                    "VaultNew: FWAES256GCM1:YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXo= " +
                    "VaultOld: FWVAULT2:YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXo= " +
                    "Jwt: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U " +
                    "JsonPass: {\"password\":\"GeheimJsonPass99!\"} " +
                    "Rtsp: rtsp://admin:KameraGeheim77@192.168.178.55:554/stream1 " +
                    "Env: DB_PASSWORD=SuperGeheim123! " +
                    "Url: https://admin:GeheimPass@mein-seelenfunke.de/api";
                string scrubbed = McpSecurityGuard.ScrubSecrets(simulatedLeak);
                AssertTest("12. Outbound DLP Redacts Sanctum, FWAES256GCM1, JWT, JSON Password & RTSP Credentials",
                    !scrubbed.Contains("AbCdEfGhIjKl")
                    && !scrubbed.Contains("YWJjZGVm")
                    && !scrubbed.Contains("dozjgNryP4J3")
                    && !scrubbed.Contains("GeheimJsonPass99")
                    && !scrubbed.Contains("KameraGeheim77")
                    && !scrubbed.Contains("SuperGeheim123")
                    && !scrubbed.Contains("GeheimPass")
                    && scrubbed.Contains("[REDACTED-BY-FUNKENWERK-DLP]"),
                    scrubbed);

                // 13. SSRF, UserInfo & HTTP Downgrade Defense
                bool evilBlocked = !McpSecurityGuard.IsAllowedApiUrl("https://attacker.example.com/api", out _);
                bool httpRemoteBlocked = !McpSecurityGuard.IsAllowedApiUrl("http://stage.mein-seelenfunke.de/api", out _);
                bool userInfoBlocked = !McpSecurityGuard.IsAllowedApiUrl("https://user:pass@stage.mein-seelenfunke.de/api", out _);
                bool httpsStageAllowed = McpSecurityGuard.IsAllowedApiUrl("https://stage.mein-seelenfunke.de/api", out _);
                bool httpsLiveAllowed = McpSecurityGuard.IsAllowedApiUrl("https://mein-seelenfunke.de/api", out _);
                AssertTest("13. SSRF Guard, UserInfo Rejection & HTTPS Enforcement",
                    evilBlocked && httpRemoteBlocked && userInfoBlocked && httpsStageAllowed && httpsLiveAllowed);

                // 14. Arbitrary SSH Command Injection Blocked + 15 Safe Presets Verified (incl. stage_deploy, stage_worker_restart, stage & minipc sentinel pool)
                bool sshInjectionBlocked = false;
                try { await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "rm -rf /; cat ~/.ssh/id_ed25519"); }
                catch (UnauthorizedAccessException) { sshInjectionBlocked = true; }
                bool hasAll15Presets = McpSecurityGuard.SafeReadonlySshPresets.Count == 15
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("uptime_and_load")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("disk_usage")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("stage_git_pull")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("stage_deploy")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("stage_worker_restart")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("stage_sentinel_export")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("stage_sentinel_resolve_all")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("minipc_sentinel_export")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("minipc_sentinel_resolve_all");
                AssertTest("14. Arbitrary SSH Command Blocked & 15 SafeReadonlySshPresets (incl. Stage & Mini-PC Sentinel) Verified",
                    sshInjectionBlocked && hasAll15Presets);

                // 15. SSH RemoteDirectory Metacharacter Injection Blocked
                bool sshDirBlocked = false;
                try { await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "git_status", "/home/p-g27wim'; id #"); }
                catch (UnauthorizedAccessException) { sshDirBlocked = true; }
                AssertTest("15. Block SSH RemoteDirectory Shell-Escape / Metacharacter Injection", sshDirBlocked);

                // 16. RunSecuritySelfAudit Bootstraps Missing security_audit.jsonl Before LockDownFileAcl (Isolated Vault)
                string isolatedAuditLog = Path.Combine(McpSecurityGuard.VaultDirectory, "security_audit.jsonl");
                if (File.Exists(isolatedAuditLog)) File.Delete(isolatedAuditLog);
                var freshAuditStatus = McpSecurityGuard.RunSecuritySelfAudit();
                McpSecurityGuard.AppendAuditLog("self_test", "integrity_probe_v1_4", "ALLOW");
                var afterEntryAudit = McpSecurityGuard.RunSecuritySelfAudit();
                AssertTest("16. RunSecuritySelfAudit Creates & ACL-Locks security_audit.jsonl in Isolated Temp Vault",
                    File.Exists(isolatedAuditLog)
                    && freshAuditStatus.DirectoryAclRestrictedToCurrentUser
                    && freshAuditStatus.AuditFileAclRestrictedToCurrentUser
                    && afterEntryAudit.AuditHashChainValid
                    && afterEntryAudit.AuditEntriesCount >= 1);

                // 17. Cryptographic Audit Hash-Chain Tamper Detection
                string tempChain = Path.Combine(tempVaultDir, "_tamper_test_chain.jsonl");
                McpSecurityGuard.AppendAuditLog("t1", "alpha", "ALLOW", tempChain);
                McpSecurityGuard.AppendAuditLog("t2", "beta", "DENY", tempChain);
                McpSecurityGuard.AppendAuditLog("t3", "gamma", "ALLOW", tempChain);
                var (validBefore, countBefore) = McpSecurityGuard.VerifyAuditChain(tempChain);
                string[] chainLines = await File.ReadAllLinesAsync(tempChain);
                chainLines[1] = chainLines[1].Replace("\"DENY\"", "\"ALLOW\"");
                await File.WriteAllLinesAsync(tempChain, chainLines);
                var (validAfter, _) = McpSecurityGuard.VerifyAuditChain(tempChain);
                AssertTest("17. SHA-256 Audit Hash-Chain Detects 1-Byte Log Tampering",
                    validBefore && countBefore == 3 && !validAfter);

                // 18. Deeply Nested JSON Bomb Defense (MaxDepth = 24)
                string jsonBomb = "{\"jsonrpc\":\"2.0\",\"id\":99,\"method\":\"ping\",\"params\":{\"a\":" + new string('[', 40) + "1" + new string(']', 40) + "}}";
                string? bombResp = await Program.ProcessJsonRpcMessageAsync(jsonBomb);
                AssertTest("18. Deeply Nested JSON Bomb Rejected Cleanly (MaxDepth=24 -> -32700)",
                    bombResp != null && bombResp.Contains("-32700"));

                // 19. Oversized Tool Argument (> 4096 chars) Rejected Safely
                string hugePathReq = "{\"jsonrpc\":\"2.0\",\"id\":100,\"method\":\"tools/call\",\"params\":{\"name\":\"pdf_inspect\",\"arguments\":{\"filePath\":\"" + new string('A', 5000) + ".pdf\"}}}";
                string? hugeResp = await Program.ProcessJsonRpcMessageAsync(hugePathReq);
                AssertTest("19. Oversized Tool Argument (>4 KB) Rejected Safely",
                    hugeResp != null && hugeResp.Contains("\"isError\":true"));

                // 20. Zero-Secret Server Profile Listing
                var sanitizedProfiles = McpSecurityGuard.GetSanitizedServerProfiles();
                string profilesJson = JsonSerializer.Serialize(sanitizedProfiles);
                AssertTest("20. Zero-Secret Server Profiles Serialization (Zero Passwords/Keys Exposed)",
                    !profilesJson.Contains("EncryptedPassword") && !profilesJson.Contains("FWAES256GCM1:") && !profilesJson.Contains("FWSSHAES256GCM1:"));

                // 21. CRITICAL Real UTF-8 Multi-Byte Content-Length Byte-Stream Framing (< 200ms, Zero Hang!)
                {
                    string utf8ReqBody = """{"jsonrpc":"2.0","id":701,"method":"tools/call","params":{"name":"tasks_manage","arguments":{"action":"create","title":"Alina Steinhauer · Größe 50€ äöüß ✨","priority":"normal"}}}""";
                    byte[] utf8PayloadBytes = Encoding.UTF8.GetBytes(utf8ReqBody);
                    byte[] headerBytes = Encoding.ASCII.GetBytes($"Content-Length: {utf8PayloadBytes.Length}\r\n\r\n");

                    using var inMs = new MemoryStream();
                    inMs.Write(headerBytes, 0, headerBytes.Length);
                    inMs.Write(utf8PayloadBytes, 0, utf8PayloadBytes.Length);
                    inMs.Position = 0;

                    using var outMs = new MemoryStream();
                    var sw = Stopwatch.StartNew();
                    var loopTask = Program.RunStdioServerLoopAsync(inMs, outMs);
                    bool finishedFast = await Task.WhenAny(loopTask, Task.Delay(200)) == loopTask;
                    sw.Stop();

                    string wireOut = Encoding.UTF8.GetString(outMs.ToArray());
                    AssertTest("21. UTF-8 Multi-Byte Content-Length Framing ('Alina Steinhauer · Größe 50€ äöüß ✨') < 200ms",
                        finishedFast
                        && sw.ElapsedMilliseconds < 200
                        && utf8PayloadBytes.Length > utf8ReqBody.Length
                        && wireOut.StartsWith("Content-Length:", StringComparison.Ordinal)
                        && wireOut.Contains("\"id\":701")
                        && wireOut.Contains("Alina Steinhauer"),
                        $"elapsed={sw.ElapsedMilliseconds}ms, out={wireOut}");
                }

                // 22. Oversized Frame Returns JSON-RPC -32600 Error WITHOUT Breaking Server Loop
                {
                    int overLen = McpSecurityGuard.MaxJsonRpcMessageBytes + 1024;
                    byte[] overBody = Encoding.ASCII.GetBytes(new string('X', overLen));
                    byte[] overHdr = Encoding.ASCII.GetBytes($"Content-Length: {overLen}\r\n\r\n");
                    string followUpPing = "{\"jsonrpc\":\"2.0\",\"id\":702,\"method\":\"ping\"}\n";
                    byte[] pingBytes = Encoding.UTF8.GetBytes(followUpPing);

                    using var inMs = new MemoryStream();
                    inMs.Write(overHdr, 0, overHdr.Length);
                    inMs.Write(overBody, 0, overBody.Length);
                    inMs.Write(pingBytes, 0, pingBytes.Length);
                    inMs.Position = 0;

                    using var outMs = new MemoryStream();
                    await Program.RunStdioServerLoopAsync(inMs, outMs);
                    string wireOut = Encoding.UTF8.GetString(outMs.ToArray());
                    AssertTest("22. Oversized Frame Returns -32600 And Keeps Server Loop Alive For Next Frame",
                        wireOut.Contains("-32600") && wireOut.Contains("\"id\":702"),
                        wireOut);
                }

                // 23. Synthetic AcroForm PDF E2E: pdf_inspect + pdf_fill_form (Rotated /MK /R 90 + Unicode + CheckBox + Overwrite Guard)
                string synthInputPdf = Path.Combine(tempDocsDir, "Synthetic_Haendlerbogen.pdf");
                string synthFilledPdf = Path.Combine(tempDocsDir, "Synthetic_Haendlerbogen_Filled.pdf");
                await File.WriteAllBytesAsync(synthInputPdf, syntheticPdfBytes);
                {
                    var inspectReq = new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 2301,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "pdf_inspect",
                            ["arguments"] = new JsonObject { ["filePath"] = synthInputPdf }
                        }
                    };
                    string? inspectResp = await Program.ProcessJsonRpcMessageAsync(inspectReq.ToJsonString());

                    var fillReq = new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 2302,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "pdf_fill_form",
                            ["arguments"] = new JsonObject
                            {
                                ["inputPath"] = synthInputPdf,
                                ["outputPath"] = synthFilledPdf,
                                ["overwrite"] = false,
                                ["openInFunkenwerkPdf"] = false,
                                ["fieldValues"] = new JsonObject
                                {
                                    ["Firmenname"] = "Mein-Seelenfunke",
                                    ["Name des Inhabers"] = "Alina Steinhauer · Größe 50€ Čech ✨",
                                    ["NotizRotiert90"] = "Rotiert 90 Grad OK",
                                    ["AGB_Akzeptiert"] = "X"
                                }
                            }
                        }
                    };
                    string? fillResp = await Program.ProcessJsonRpcMessageAsync(fillReq.ToJsonString());
                    // Second call with overwrite=false must return isError=true
                    string? dupFillResp = await Program.ProcessJsonRpcMessageAsync(fillReq.ToJsonString());

                    var verifyFilled = McpPdfEngine.InspectPdf(synthFilledPdf);
                    var rotField = verifyFilled.Fields.FirstOrDefault(f => f.Name == "NotizRotiert90");
                    var ownerField = verifyFilled.Fields.FirstOrDefault(f => f.Name == "Name des Inhabers");
                    var chkField = verifyFilled.Fields.FirstOrDefault(f => f.Name == "AGB_Akzeptiert");

                    AssertTest("23. Synthetic AcroForm PDF Inspect + Fill (Rotated /MK /R 90 + Unicode + Overwrite Guard)",
                        inspectResp != null && inspectResp.Contains("NotizRotiert90")
                        && fillResp != null && fillResp.Contains("\"isError\":false")
                        && dupFillResp != null && dupFillResp.Contains("\"isError\":true")
                        && rotField != null && rotField.WidgetRotation == 90 && rotField.Value.Contains("Rotiert 90 Grad OK")
                        && ownerField != null && ownerField.Value.Contains("Alina Steinhauer")
                        && chkField != null && chkField.IsChecked);
                }

                // 24. Synthetic AcroForm PDF E2E: pdf_autofill_stammdaten (Zero Downloads Dependency)
                string synthStammdatenOut = Path.Combine(tempDocsDir, "Synthetic_Haendlerbogen_Stammdaten.pdf");
                {
                    var autoReq = new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 2401,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "pdf_autofill_stammdaten",
                            ["arguments"] = new JsonObject
                            {
                                ["inputPath"] = synthInputPdf,
                                ["outputPath"] = synthStammdatenOut,
                                ["overwrite"] = true,
                                ["openInFunkenwerkPdf"] = false,
                                ["overrides"] = new JsonObject { ["AGB_Akzeptiert"] = "X" }
                            }
                        }
                    };
                    string? autoResp = await Program.ProcessJsonRpcMessageAsync(autoReq.ToJsonString());
                    var verifyAuto = McpPdfEngine.InspectPdf(synthStammdatenOut);
                    AssertTest("24. Synthetic AcroForm PDF Stammdaten Autofill (Mein-Seelenfunke / Gifhorn / FYRST)",
                        autoResp != null && autoResp.Contains("\"isError\":false")
                        && verifyAuto.Fields.First(f => f.Name == "Firmenname").Value == "Mein-Seelenfunke"
                        && verifyAuto.Fields.First(f => f.Name == "Name des Inhabers").Value == "Alina Steinhauer"
                        && verifyAuto.Fields.First(f => f.Name == "PLZ").Value == "38518"
                        && verifyAuto.Fields.First(f => f.Name == "Ort").Value == "Gifhorn"
                        && verifyAuto.Fields.First(f => f.Name == "Bank-Name").Value == "FYRST (Deutsche Bank AG)");
                }

                // 25. MCP JSON-RPC 2.0 Handshake, Notification Silence & 25-Tool Schema Catalog
                string? initResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"antigravity","version":"2.0"}}}""");
                string? notifResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","method":"notifications/initialized"}""");
                string? toolsResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3,"method":"tools/list","params":{}}""");
                int catalogToolCount = toolsResp != null
                    ? (JsonNode.Parse(toolsResp)?["result"]?["tools"] as JsonArray)?.Count ?? 0
                    : 0;
                AssertTest("25. MCP JSON-RPC 2.0 Handshake, Notification Silence & 25-Tool Schema Catalog",
                    initResp != null && initResp.Contains("\"funkenwerk-mcp\"")
                    && notifResp == null
                    && catalogToolCount == 25
                    && toolsResp != null && toolsResp.Contains("\"uptime_and_load\"") && toolsResp.Contains("\"pdf_autofill_stammdaten\"")
                    && toolsResp.Contains("\"stage_sync_shop_assets\"") && toolsResp.Contains("\"stage_deploy\"")
                    && toolsResp.Contains("\"ag_direct_inbox\"")
                    && toolsResp.Contains("\"tresor_list_ai_entries\"")
                    && toolsResp.Contains("\"tresor_browser_login\"")
                    && toolsResp.Contains("\"mittwald_api_broker\""),
                    $"catalogToolCount={catalogToolCount}");

                // 26. MCP resources/list & resources/read E2E
                string? resListResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2601,"method":"resources/list","params":{}}""");
                string? resStammdatenResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2602,"method":"resources/read","params":{"uri":"funkenwerk://company/stammdaten"}}""");
                string? resPolicyResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2603,"method":"resources/read","params":{"uri":"funkenwerk://security/policy"}}""");
                string? resInvalidResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2604,"method":"resources/read","params":{"uri":"funkenwerk://unknown/secret"}}""");
                AssertTest("26. MCP resources/list & resources/read (Stammdaten + Security Policy + Invalid URI Error)",
                    resListResp != null && resListResp.Contains("funkenwerk://company/stammdaten")
                    && resStammdatenResp != null && resStammdatenResp.Contains("Carl-Goerdeler-Ring 26")
                    && resPolicyResp != null && resPolicyResp.Contains("AuditHashChainValid")
                    && resInvalidResp != null && resInvalidResp.Contains("-32603"));

                // 27. Mocked HttpMessageHandler: tasks_manage Priority Mapping (low->niedrig, normal->mittel, urgent->hoch)
                mockHandler.CapturedRequests.Clear();
                await McpOrganizerClient.ManageTaskAsync(action: "create", title: "T1", priority: "low");
                await McpOrganizerClient.ManageTaskAsync(action: "create", title: "T2", priority: "normal");
                await McpOrganizerClient.ManageTaskAsync(action: "create", title: "T3", priority: "urgent");
                await McpOrganizerClient.ManageTaskAsync(action: "create", title: "T4", priority: "high");
                bool invalidPrioRejected = false;
                try { await McpOrganizerClient.ManageTaskAsync(action: "create", title: "T5", priority: "critical_invalid"); }
                catch (ArgumentException) { invalidPrioRejected = true; }
                var postBodies = mockHandler.CapturedRequests.Where(r => r.Method == HttpMethod.Post).Select(r => r.Body).ToList();
                AssertTest("27. Organizer tasks_manage Priority Normalization (low->niedrig, normal->mittel, urgent|high->hoch)",
                    postBodies.Count == 4
                    && postBodies[0].Contains("\"priority\":\"niedrig\"")
                    && postBodies[1].Contains("\"priority\":\"mittel\"")
                    && postBodies[2].Contains("\"priority\":\"hoch\"")
                    && postBodies[3].Contains("\"priority\":\"hoch\"")
                    && invalidPrioRejected);

                // 28. Mocked HttpMessageHandler: calendar_list 'date' Parameter & calendar_create
                mockHandler.CapturedRequests.Clear();
                string? calDateResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2801,"method":"tools/call","params":{"name":"calendar_list","arguments":{"date":"2026-11-14"}}}""");
                string? calCreateResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2802,"method":"tools/call","params":{"name":"calendar_create","arguments":{"title":"Laser Wartung","start":"2026-11-15T09:00:00"}}}""");
                bool exactDateQuerySent = mockHandler.CapturedRequests.Any(r =>
                    r.Method == HttpMethod.Get &&
                    r.Uri.Query.Contains("start=2026-11-14", StringComparison.Ordinal) &&
                    r.Uri.Query.Contains("end=2026-11-14", StringComparison.Ordinal));
                AssertTest("28. Organizer calendar_list Single-Day 'date' Parameter & calendar_create Verified",
                    calDateResp != null && calDateResp.Contains("Steuerberater Export") && exactDateQuerySent
                    && calCreateResp != null && calCreateResp.Contains("\"isError\":false"));

                // 29. Mocked HttpMessageHandler: shopping_manage Category Name Resolution & UUID Passthrough
                mockHandler.CapturedRequests.Clear();
                string? shopByNameResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2901,"method":"tools/call","params":{"name":"shopping_manage","arguments":{"action":"add","name":"Schmuckboxen Schwarz","quantity":100,"category":"Verpackung"}}}""");
                string directUuid = "88888888-4444-4444-4444-121212121212";
                string? shopByUuidResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                {
                    ["jsonrpc"] = "2.0",
                    ["id"] = 2902,
                    ["method"] = "tools/call",
                    ["params"] = new JsonObject
                    {
                        ["name"] = "shopping_manage",
                        ["arguments"] = new JsonObject
                        {
                            ["action"] = "add",
                            ["name"] = "Edelstahl Rohlinge",
                            ["quantity"] = 25,
                            ["category"] = directUuid
                        }
                    }
                }.ToJsonString());
                var shopPosts = mockHandler.CapturedRequests.Where(r => r.Method == HttpMethod.Post && r.Uri.AbsolutePath.EndsWith("/shopping/items")).Select(r => r.Body).ToList();
                AssertTest("29. Organizer shopping_manage Category Name->UUID Resolution & Direct UUID Passthrough",
                    shopByNameResp != null && shopByNameResp.Contains("cat-uuid-verpackung-001")
                    && shopByUuidResp != null && shopByUuidResp.Contains(directUuid)
                    && shopPosts.Count == 2);

                // 30. LaunchCollectionExecutable Hardening (UseShellExecute=false, ArgumentList, Reject '-flag' & '/flag')
                var safePsi = Program.BuildValidatedCollectionStartInfo("pdf", synthFilledPdf, requireBinaryExists: false);
                bool dashFlagRejected = false;
                try { Program.BuildValidatedCollectionStartInfo("browser", "--remote-debugging-port=9222", requireBinaryExists: false); }
                catch (UnauthorizedAccessException) { dashFlagRejected = true; }
                bool slashFlagRejected = false;
                try { Program.BuildValidatedCollectionStartInfo("pdf", "/print-to-default", requireBinaryExists: false); }
                catch (UnauthorizedAccessException) { slashFlagRejected = true; }
                AssertTest("30. LaunchCollectionExecutable Uses UseShellExecute=false + ArgumentList & Blocks CLI Flags",
                    !safePsi.UseShellExecute
                    && safePsi.ArgumentList.Count == 1
                    && safePsi.ArgumentList[0] == synthFilledPdf
                    && dashFlagRejected
                    && slashFlagRejected);

                // 31. Craft projectName Sanitization Guard
                AssertThrowsSecurity("31. Block Craft projectName Traversal & Invalid Characters ('../evil')", () =>
                {
                    string okName = McpCraftBridge.ValidateSafeProjectName("Reel Herbst 2026_v1");
                    if (okName != "Reel Herbst 2026_v1") throw new Exception("Valid name rejected");
                    McpCraftBridge.ValidateSafeProjectName("../evil_escape");
                });

                // 32. Craft Write Sandbox (projectPath & outputPath Outside Craft\Videos Blocked, Inside Allowed)
                string allowedCraftExport = Path.Combine(McpCraftBridge.CraftVideosDir, "Exports", "social_master.mp4");
                string validatedCraftOut = McpCraftBridge.ValidateSafeCraftWritePath(allowedCraftExport, new[] { ".mp4" });
                string? craftSaveOutsideResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                {
                    ["jsonrpc"] = "2.0",
                    ["id"] = 3201,
                    ["method"] = "tools/call",
                    ["params"] = new JsonObject
                    {
                        ["name"] = "craft_project_manage",
                        ["arguments"] = new JsonObject
                        {
                            ["action"] = "save",
                            ["projectName"] = "Projekt 1",
                            ["outputPath"] = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "outside_escape.mp4")
                        }
                    }
                }.ToJsonString());
                string? craftRenderOutsideResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                {
                    ["jsonrpc"] = "2.0",
                    ["id"] = 3202,
                    ["method"] = "tools/call",
                    ["params"] = new JsonObject
                    {
                        ["name"] = "craft_render_social_video",
                        ["arguments"] = new JsonObject
                        {
                            ["projectName"] = "Projekt 1",
                            ["outputPath"] = Path.Combine(tempDocsDir, "outside_escape.mp4")
                        }
                    }
                }.ToJsonString());
                AssertTest("32. Craft Write Sandbox Enforced For projectPath & outputPath",
                    validatedCraftOut == Path.GetFullPath(allowedCraftExport)
                    && craftSaveOutsideResp != null && craftSaveOutsideResp.Contains("\"isError\":true")
                    && craftRenderOutsideResp != null && craftRenderOutsideResp.Contains("\"isError\":true"));

                // 33. Craft filmcraft_bridge RPC Method Allowlist
                string allowedRpc = McpCraftBridge.ValidateAllowedFilmCraftBridgeMethod("engine.ping");
                string? blockedBridgeResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3301,"method":"tools/call","params":{"name":"craft_storytold_engine","arguments":{"action":"filmcraft_bridge","bridgeMethod":"system.exec"}}}""");
                AssertTest("33. Craft filmcraft_bridge RPC Method Allowlist ('engine.ping' Allowed, 'system.exec' Blocked)",
                    allowedRpc == "engine.ping"
                    && blockedBridgeResp != null && blockedBridgeResp.Contains("\"isError\":true"));

                // 34. Craft Media Inspect Path Guard (Blocks System & UNC Paths) + Craft Workspace Check
                string? mediaWinIniResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3401,"method":"tools/call","params":{"name":"craft_media_inspect","arguments":{"targetPath":"C:\\Windows\\win.ini"}}}""");
                string? mediaUncResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3402,"method":"tools/call","params":{"name":"craft_media_inspect","arguments":{"targetPath":"\\\\attacker.local\\share\\clip.mp4"}}}""");
                bool craftCheckOk = mediaWinIniResp != null && mediaWinIniResp.Contains("\"isError\":true")
                    && mediaUncResp != null && mediaUncResp.Contains("\"isError\":true");
                if (File.Exists(McpCraftBridge.CraftExePath))
                {
                    string? craftStatusResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3403,"method":"tools/call","params":{"name":"craft_workspace_status","arguments":{"includeMedienCoverage":false}}}""");
                    craftCheckOk = craftCheckOk && craftStatusResp != null && craftStatusResp.Contains("\"isError\":false");
                }
                AssertTest("34. Craft Media Inspect Blocks System/UNC Paths & Verifies Craft Bridge", craftCheckOk);

                // 35. Music & SmartHome Control Tools + Ecosystem Health Probe
                var health = await McpOrganizerClient.ProbeEcosystemHealthAsync();
                string healthJson = JsonSerializer.Serialize(health);
                string? volInvalidResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3501,"method":"tools/call","params":{"name":"music_control","arguments":{"action":"set_volume","volume":150}}}""");
                string? musicStatusResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3502,"method":"tools/call","params":{"name":"music_control","arguments":{"action":"status","autoStartApp":false}}}""");
                string? smartStatusResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3503,"method":"tools/call","params":{"name":"smarthome_control","arguments":{"action":"status","autoStartApp":false}}}""");
                AssertTest("35. Ecosystem Health, Music 0..100 Volume Guard & CurrentUser Pipe Probes",
                    healthJson.Contains("\"apiOnline\":true")
                    && volInvalidResp != null && volInvalidResp.Contains("\"isError\":true")
                    && musicStatusResp != null && musicStatusResp.Contains("\"isError\":false")
                    && smartStatusResp != null && smartStatusResp.Contains("\"isError\":false"));

                // 36. Sliding-Window Rate Limiter Exhaustion Test (60 Allowed -> 61st Blocked)
                McpSecurityGuard.ResetRateLimiterForTests();
                bool all60Allowed = true;
                for (int i = 0; i < 60; i++)
                {
                    try { McpSecurityGuard.EnforceRateLimit("rate_test_tool"); }
                    catch { all60Allowed = false; break; }
                }
                bool call61Blocked = false;
                try { McpSecurityGuard.EnforceRateLimit("rate_test_tool"); }
                catch (InvalidOperationException) { call61Blocked = true; }
                McpSecurityGuard.ResetRateLimiterForTests();
                AssertTest("36. Sliding-Window Rate Limiter Allows 60/min And Rejects 61st Burst Call",
                    all60Allowed && call61Blocked);

                // 37. Stage Live-Air-Gap Guard Blocks seelenfunke-live, steinhauer-invest, production & Non-Mittwald Hosts
                {
                    bool liveBlocked = false;
                    try { McpSecurityGuard.ValidateStageOnlyTarget("ssh.altgemeinde.project.host", "/home/p-g27wim/html/seelenfunke-live"); }
                    catch (UnauthorizedAccessException) { liveBlocked = true; }

                    bool investBlocked = false;
                    try { McpSecurityGuard.ValidateStageOnlyTarget("ssh.altgemeinde.project.host", "/home/p-g27wim/html/steinhauer-invest"); }
                    catch (UnauthorizedAccessException) { investBlocked = true; }

                    bool wrongHostBlocked = false;
                    try { McpSecurityGuard.ValidateStageOnlyTarget("mein-seelenfunke.de", "/home/p-g27wim/html/seelenfunke-stage"); }
                    catch (UnauthorizedAccessException) { wrongHostBlocked = true; }

                    McpSecurityGuard.ValidateStageOnlyTarget("ssh.altgemeinde.project.host", "/home/p-g27wim/html/seelenfunke-stage");
                    McpSecurityGuard.ValidateStageOnlyTarget("ssh.altgemeinde.project.host", "/home/p-g27wim/html/worker-stage-3", allowWorkerStage: true);

                    AssertTest("37. Stage Live-Air-Gap Guard Blocks seelenfunke-live, steinhauer-invest & Non-Stage Hosts",
                        liveBlocked && investBlocked && wrongHostBlocked);
                }

                // 38. Stage Pure-Shop Asset Guard Blocks Music (.mp3/.m4a), Buchhaltung, Health, Env & Traversal
                {
                    string[] blockedSamples =
                    {
                        "leitung/music/tracks/song.m4a",
                        "leitung/music/playlists_catalog.json",
                        "buchhaltung/receipts/2026-11-01_Beleg.pdf",
                        "buchhaltung/contracts/Vertrag.pdf",
                        "buchhaltung/erictresor/cert.pfx",
                        "agenten/workspace/Gesundheit/befund.pdf",
                        "public/support/wiki/health/rezept.jpg",
                        "public/leitung/person_profiles/avatar.webp",
                        "private/system-brain-map.json",
                        "firebase-service-account.json",
                        ".env",
                        "public/produkte/products/echtgold-seelen-herz/overlay.png",
                        "public/produkte/products/seelen-kristall/backup/old.png",
                        "public/produkte/../../.env"
                    };

                    bool allForbiddenBlocked = true;
                    foreach (string bad in blockedSamples)
                    {
                        bool threw = false;
                        try { McpSecurityGuard.ValidatePureShopAssetRelativePath(bad); }
                        catch (UnauthorizedAccessException) { threw = true; }
                        if (!threw) { allForbiddenBlocked = false; break; }
                    }
                    AssertTest("38. Stage Pure-Shop Asset Guard Hard-Blocks Music, Buchhaltung, Health, Env & Backups",
                        allForbiddenBlocked);
                }

                // 39. Stage Pure-Shop Asset Guard Accepts Active Product, 3D GLB, Configurator, Digital ZIP & Blog Assets
                {
                    string p1 = McpSecurityGuard.ValidatePureShopAssetRelativePath("public/produkte/products/seelen-kristall/t_seelenk.glb");
                    string p2 = McpSecurityGuard.ValidatePureShopAssetRelativePath("public/produkte/product-templates/seelen-kristall-weihnachten.jpg");
                    string p3 = McpSecurityGuard.ValidatePureShopAssetRelativePath("public/produkte/laser-anleitungen/Anleitung_1_360_Grad_Gravur_xTool_RA3.pdf");
                    string p4 = McpSecurityGuard.ValidatePureShopAssetRelativePath("produkte/products-secure/KI_Agenten_Management_Paket.zip");
                    string p5 = McpSecurityGuard.ValidatePureShopAssetRelativePath("public/marketing/blog/herzensprojekt.webp");
                    AssertTest("39. Stage Pure-Shop Asset Guard Accepts Valid 3D GLB, Templates, Laser-Guides, Digital ZIP & Blog",
                        p1.EndsWith(".glb") && p2.EndsWith(".jpg") && p3.EndsWith(".pdf") && p4.EndsWith(".zip") && p5.EndsWith(".webp"));
                }

                // 40. SSH Preset stage_deploy Rejects Live Target Path Before Any Network Socket Opens
                {
                    bool livePresetRejected = false;
                    try
                    {
                        await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "stage_deploy", "/home/p-g27wim/html/seelenfunke-live");
                    }
                    catch (UnauthorizedAccessException)
                    {
                        livePresetRejected = true;
                    }
                    AssertTest("40. SSH Preset stage_deploy Rejects /seelenfunke-live Before Opening Any Network Socket",
                        livePresetRejected);
                }

                // 41. Stage Autopilot Enforces Strict Ed25519 Key-Only Mode (Zero Vault Password Decryption In RAM)
                {
                    var (found, hasKey, pwdInRam) = McpSecurityGuard.InspectStageKeyOnlyModeForSelfTest("Mittwald");
                    bool keyOnlyOk = !found || (hasKey && !pwdInRam);
                    AssertTest("41. Stage Autopilot Enforces Ed25519 Key-Only Mode (Zero Vault Password Decryption In RAM)",
                        keyOnlyOk);
                }

                // 42. Stage APK Publisher Rejects Non-APK / Unsigned ZIP & Cryptographically Verifies Real 4096-Bit Funkenwerk APK
                {
                    bool nonApkBlocked = false;
                    try { McpSecurityGuard.VerifyLocalSignedFunkenwerkApk(Path.Combine(tempDocsDir, "evil.exe")); }
                    catch (UnauthorizedAccessException) { nonApkBlocked = true; }

                    string fakeUnsignedApk = Path.Combine(tempDocsDir, "unsigned.apk");
                    var fakePayload = new byte[600 * 1024];
                    fakePayload[0] = 0x50; fakePayload[1] = 0x4B; fakePayload[2] = 0x03; fakePayload[3] = 0x04;
                    File.WriteAllBytes(fakeUnsignedApk, fakePayload);

                    bool unsignedBlocked = false;
                    try { McpSecurityGuard.VerifyLocalSignedFunkenwerkApk(fakeUnsignedApk); }
                    catch (UnauthorizedAccessException) { unsignedBlocked = true; }

                    bool realVerified = true;
                    if (File.Exists(McpSecurityGuard.DefaultLocalReleaseApkPath))
                    {
                        var (sz, sha, cert) = McpSecurityGuard.VerifyLocalSignedFunkenwerkApk(McpSecurityGuard.DefaultLocalReleaseApkPath);
                        realVerified = sz > 512 * 1024 && sha.Length == 64 && cert == McpSecurityGuard.PinnedFunkenwerkApkCertSha256;
                    }

                    AssertTest("42. Stage APK Publisher Blocks Non-APK/Unsigned Files & Cryptographically Verifies Real 4096-Bit Release APK",
                        nonApkBlocked && unsignedBlocked && realVerified);
                }

                // 43. Stage APK Publisher Blocks /seelenfunke-live & Invalid Version Strings Before Opening Sockets
                {
                    bool liveApkBlocked = false;
                    try
                    {
                        await McpSecurityGuard.PublishStageAndroidApkAsync(
                            profileName: "Mittwald",
                            customApkPath: McpSecurityGuard.DefaultLocalReleaseApkPath,
                            versionCode: 13,
                            versionName: "1.6.0",
                            customRemoteStageDirForTest: "/home/p-g27wim/html/seelenfunke-live");
                    }
                    catch (UnauthorizedAccessException) { liveApkBlocked = true; }

                    bool badVerBlocked = false;
                    try
                    {
                        await McpSecurityGuard.PublishStageAndroidApkAsync(
                            profileName: "Mittwald",
                            customApkPath: McpSecurityGuard.DefaultLocalReleaseApkPath,
                            versionCode: 13,
                            versionName: "1.6.0; rm -rf /");
                    }
                    catch (UnauthorizedAccessException) { badVerBlocked = true; }

                    AssertTest("43. Stage APK Publisher Blocks /seelenfunke-live & Shell-Injection Version Strings Before Opening Sockets",
                        liveApkBlocked && badVerBlocked);
                }

                // 44. Mittwald Node.js App Container Mapping (a-yeq7el / twilio-bridge) & Stage Worker Guard
                {
                    McpSecurityGuard.ValidateStageOnlyTarget("ssh.altgemeinde.project.host", "/home/p-g27wim/html/twilio-bridge", allowWorkerStage: true);
                    bool nonWorkerBlocked = false;
                    try { McpSecurityGuard.ValidateStageOnlyTarget("ssh.altgemeinde.project.host", "/home/p-g27wim/html/twilio-bridge", allowWorkerStage: false); }
                    catch (UnauthorizedAccessException) { nonWorkerBlocked = true; }

                    var (found, resolvedUser, resolvedDir, pwdInRam) = McpSecurityGuard.InspectNodeBridgeProfileForSelfTest();
                    bool profileMappedOk = !found || (
                        resolvedUser == McpSecurityGuard.StageTwilioBridgeSshUser &&
                        resolvedDir == McpSecurityGuard.StageTwilioBridgeRemoteDir &&
                        !pwdInRam);

                    AssertTest("44. Mittwald Node.js App Maps To Container a-yeq7el (/twilio-bridge) In Ed25519 Key-Only Mode",
                        nonWorkerBlocked && profileMappedOk);
                }

                // 45. 24/7 Sentinel Catch-All Pool SSH Presets Are Allowlisted & Block /seelenfunke-live Before Socket Open
                {
                    bool presetsRegistered = McpSecurityGuard.SafeReadonlySshPresets.Contains("stage_sentinel_export")
                        && McpSecurityGuard.SafeReadonlySshPresets.Contains("stage_sentinel_resolve_all");

                    bool liveExportBlocked = false;
                    try
                    {
                        await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "stage_sentinel_export", "/home/p-g27wim/html/seelenfunke-live");
                    }
                    catch (UnauthorizedAccessException) { liveExportBlocked = true; }

                    bool liveResolveBlocked = false;
                    try
                    {
                        await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "stage_sentinel_resolve_all", "/home/p-g27wim/html/seelenfunke-live");
                    }
                    catch (UnauthorizedAccessException) { liveResolveBlocked = true; }

                    AssertTest("45. 24/7 Sentinel Pool Presets Allowlisted & Block /seelenfunke-live Before Opening Sockets",
                        presetsRegistered && liveExportBlocked && liveResolveBlocked);
                }

                // 46. Mini-PC Direct Sentinel Presets Block Non-Mini-PC Hosts & Master-Key Access Before Socket Open
                {
                    bool nonMiniHostBlocked = false;
                    try
                    {
                        McpSecurityGuard.ValidateMiniPcOnlyTarget("ssh.altgemeinde.project.host");
                    }
                    catch (UnauthorizedAccessException) { nonMiniHostBlocked = true; }

                    bool masterKeyPathBlocked = false;
                    try
                    {
                        McpSecurityGuard.ValidateMiniPcOnlyTarget("192.168.188.151", "/home/alina/.local/share/FunkenwerkOS/master.key");
                    }
                    catch (UnauthorizedAccessException) { masterKeyPathBlocked = true; }

                    McpSecurityGuard.ValidateMiniPcOnlyTarget("192.168.188.151", "/home/alina/.local/share/FunkenwerkOS");

                    AssertTest("46. Mini-PC Direct Sentinel Presets Enforce 192.168.188.151 & Block master.key Paths",
                        nonMiniHostBlocked && masterKeyPathBlocked);
                }

                // 47. AG Direct Bridge ID & Stage-Only Guard (Blocks Shell-Injection, Flags, Traversal & /seelenfunke-live)
                {
                    string okSingle = McpSecurityGuard.ValidateAgInboxSingleId("ag_20261116_abc123");
                    string okBatch = McpSecurityGuard.ValidateAgInboxMarkDeliveredIds("ag_20261116_001,ag_20261116_002");
                    string okGuid = McpSecurityGuard.ValidateAgConversationId("a75e6db9-d026-4955-97c0-6e8695d6193d");

                    string[] maliciousIds =
                    {
                        "",
                        "ab",
                        "-rf",
                        "--help",
                        "ag_1; rm -rf /",
                        "ag_1$(whoami)",
                        "../ag_1",
                        "ag_1' OR '1'='1",
                        "ag_1,,ag_2",
                        "ag_1,"
                    };

                    bool allMaliciousBlocked = true;
                    foreach (string bad in maliciousIds)
                    {
                        bool blockedSingle = false;
                        try { McpSecurityGuard.ValidateAgInboxSingleId(bad); }
                        catch (UnauthorizedAccessException) { blockedSingle = true; }

                        bool blockedDelivered = false;
                        try { McpSecurityGuard.ValidateAgInboxMarkDeliveredIds(bad); }
                        catch (UnauthorizedAccessException) { blockedDelivered = true; }

                        bool blockedConv = false;
                        try { McpSecurityGuard.ValidateAgConversationId(bad); }
                        catch (UnauthorizedAccessException) { blockedConv = true; }

                        if (!blockedSingle || !blockedDelivered || !blockedConv)
                        {
                            allMaliciousBlocked = false;
                            break;
                        }
                    }

                    bool liveAgBlocked = false;
                    try
                    {
                        await McpSecurityGuard.ExecuteStageAgInboxCommandAsync(
                            "peek_pending",
                            customRemoteStageDirForTest: "/home/p-g27wim/html/seelenfunke-live");
                    }
                    catch (UnauthorizedAccessException) { liveAgBlocked = true; }

                    AssertTest("47. AG Direct Bridge Validates IDs/GUIDs Against Command-Injection & Blocks /seelenfunke-live",
                        okSingle == "ag_20261116_abc123"
                        && okBatch == "ag_20261116_001,ag_20261116_002"
                        && okGuid == "a75e6db9-d026-4955-97c0-6e8695d6193d"
                        && allMaliciousBlocked
                        && liveAgBlocked);
                }

                // 48. AG Direct Bridge Prompt Formatting (concept vs direct + Numbered Checklist + Attachment + Completion CLI)
                {
                    var sampleChecklistNode = JsonNode.Parse("""["Rohling ausmessen", {"text": "3D-Modell anpassen", "done": false}]""");
                    var extractedChecklist = Program.ExtractChecklistStrings(sampleChecklistNode);
                    string fakeMediaPath = @"C:\Users\konta\AppData\Local\Funkenwerk\AgDirectMedia\ag_test_01.png";

                    string conceptPrompt = McpSecurityGuard.BuildAgDirectMasterPrompt(
                        itemId: "ag_test_01",
                        title: "Neuer Schlüsselanhänger",
                        body: "Bitte erst Materialkosten kalkulieren.",
                        checklist: extractedChecklist,
                        executionMode: "concept",
                        savedAttachmentPath: fakeMediaPath);

                    string directPrompt = McpSecurityGuard.BuildAgDirectMasterPrompt(
                        itemId: "ag_test_02",
                        title: "Tippfehler im Footer beheben",
                        body: null,
                        checklist: null,
                        executionMode: "direct",
                        savedAttachmentPath: null);

                    AssertTest("48. AG Direct Bridge Prompt Formatting (concept vs direct + Checklist + Media Path + Complete CLI)",
                        conceptPrompt.Contains("[DIREKTNACHRICHT AUS ANDROID-APP | ID: ag_test_01 | MODUS: Erst Konzept (warte auf Go)]")
                        && conceptPrompt.Contains("Hauptpunkt: Neuer Schlüsselanhänger")
                        && conceptPrompt.Contains("Bitte erst Materialkosten kalkulieren.")
                        && conceptPrompt.Contains("1. Rohling ausmessen")
                        && conceptPrompt.Contains("2. 3D-Modell anpassen")
                        && conceptPrompt.Contains($"Angehängtes Bild lokal gespeichert unter: {fakeMediaPath} (bitte per read_file ansehen)")
                        && conceptPrompt.Contains("--ag-inbox-complete ag_test_01 \"<kurze Zusammenfassung>\"")
                        && directPrompt.Contains("[DIREKTNACHRICHT AUS ANDROID-APP | ID: ag_test_02 | MODUS: Direkt umsetzen via Orchester-Subagenten]")
                        && !directPrompt.Contains("Angehängtes Bild lokal gespeichert unter:")
                        && directPrompt.Contains("--ag-inbox-complete ag_test_02 \"<kurze Zusammenfassung>\""));
                }

                // 49. AG Direct Bridge Attachment Decoder Magic-Header & Extension Security Guard (.png / .jpg / .webp Only)
                {
                    byte[] validPng = { 0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A, 0x00, 0x00, 0x00, 0x0D, 0x49, 0x48, 0x44, 0x52 };
                    byte[] validJpg = { 0xFF, 0xD8, 0xFF, 0xE0, 0x00, 0x10, 0x4A, 0x46, 0x49, 0x46, 0x00, 0x01, 0xFF, 0xD9 };
                    byte[] validWebp = { 0x52, 0x49, 0x46, 0x46, 0x10, 0x00, 0x00, 0x00, 0x57, 0x45, 0x42, 0x50, 0x56, 0x50, 0x38, 0x20 };
                    byte[] disguisedExe = Encoding.ASCII.GetBytes("MZ_FAKE_WINDOWS_EXECUTABLE_PAYLOAD");

                    string savedPng = McpSecurityGuard.SaveAgDirectAttachmentSafe("ag_img_png", "screen.png", "image/png", Convert.ToBase64String(validPng));
                    string savedJpg = McpSecurityGuard.SaveAgDirectAttachmentSafe("ag_img_jpg", "photo.jpeg", "image/jpeg", Convert.ToBase64String(validJpg));
                    string savedWebp = McpSecurityGuard.SaveAgDirectAttachmentSafe("ag_img_webp", "shot.webp", "image/webp", Convert.ToBase64String(validWebp));

                    bool exeBlocked = false;
                    try { McpSecurityGuard.SaveAgDirectAttachmentSafe("ag_bad_exe", "screen.png", "image/png", Convert.ToBase64String(disguisedExe)); }
                    catch (UnauthorizedAccessException) { exeBlocked = true; }

                    bool svgExtBlocked = false;
                    try { McpSecurityGuard.SaveAgDirectAttachmentSafe("ag_bad_svg", "xss.svg", "image/png", Convert.ToBase64String(validPng)); }
                    catch (UnauthorizedAccessException) { svgExtBlocked = true; }

                    AssertTest("49. AG Direct Bridge Media Guard Saves Valid PNG/JPG/WEBP & Blocks Disguised EXE/SVG Payloads",
                        File.Exists(savedPng) && savedPng.EndsWith("ag_img_png.png", StringComparison.Ordinal)
                        && File.Exists(savedJpg) && savedJpg.EndsWith("ag_img_jpg.jpg", StringComparison.Ordinal)
                        && File.Exists(savedWebp) && savedWebp.EndsWith("ag_img_webp.webp", StringComparison.Ordinal)
                        && exeBlocked
                        && svgExtBlocked);
                }

                // 50. AG Direct Bridge Config Roundtrip (ag_direct_bridge_config.json) & MCP Tool set_master_chat E2E
                {
                    McpSecurityGuard.ResetRateLimiterForTests();
                    var savedCfg = McpSecurityGuard.SaveAgBridgeConfig(new AgDirectBridgeConfigDto
                    {
                        MasterConversationId = "11111111-2222-3333-4444-555555555555",
                        PollIntervalSeconds = 5 // Must clamp to MinAgPollIntervalSeconds (10s)
                    });
                    var loadedCfg = McpSecurityGuard.LoadAgBridgeConfig();
                    string rawCfgJson = await File.ReadAllTextAsync(McpSecurityGuard.AgDirectBridgeConfigPath);

                    string? mcpSetResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 5001,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "ag_direct_inbox",
                            ["arguments"] = new JsonObject
                            {
                                ["action"] = "set_master_chat",
                                ["conversation_id"] = "a75e6db9-d026-4955-97c0-6e8695d6193d"
                            }
                        }
                    }.ToJsonString());

                    string? mcpBadIdResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 5002,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "ag_direct_inbox",
                            ["arguments"] = new JsonObject
                            {
                                ["action"] = "complete",
                                ["id"] = "ag_1; whoami",
                                ["summary"] = "Test"
                            }
                        }
                    }.ToJsonString());

                    var reloadedAfterMcp = McpSecurityGuard.LoadAgBridgeConfig();
                    AssertTest("50. AG Direct Bridge Config JSON Roundtrip, Min-10s Clamp & MCP ag_direct_inbox E2E",
                        savedCfg.PollIntervalSeconds == 10
                        && loadedCfg.MasterConversationId == "11111111-2222-3333-4444-555555555555"
                        && loadedCfg.PollIntervalSeconds == 10
                        && rawCfgJson.Contains("\"master_conversation_id\"")
                        && rawCfgJson.Contains("\"poll_interval_seconds\"")
                        && mcpSetResp != null && mcpSetResp.Contains("\"isError\":false")
                        && reloadedAfterMcp.MasterConversationId == "a75e6db9-d026-4955-97c0-6e8695d6193d"
                        && mcpBadIdResp != null && mcpBadIdResp.Contains("\"isError\":true"));
                }

                // 51. AG Direct Bridge agentapi ProcessStartInfo Shell-Injection Immunity (UseShellExecute=false + ArgumentList)
                {
                    string trickyPrompt = "[DIREKTNACHRICHT] \"Quotes\" & calc.exe | $(whoami) %APPDATA%\nZeile 2";
                    var psiWithExe = McpSecurityGuard.BuildAgentApiSendMessageStartInfo(
                        "a75e6db9-d026-4955-97c0-6e8695d6193d",
                        trickyPrompt,
                        agentApiExeOverride: @"C:\Antigravity\language_server.exe");
                    var psiFallback = McpSecurityGuard.BuildAgentApiSendMessageStartInfo(
                        "a75e6db9-d026-4955-97c0-6e8695d6193d",
                        trickyPrompt,
                        agentApiExeOverride: null,
                        ignoreEnvironmentVariableForTest: true);

                    AssertTest("51. AG Direct Bridge agentapi ProcessStartInfo Enforces UseShellExecute=false & ArgumentList",
                        !psiWithExe.UseShellExecute
                        && psiWithExe.FileName == @"C:\Antigravity\language_server.exe"
                        && psiWithExe.ArgumentList.Count == 5
                        && psiWithExe.ArgumentList[0] == "agentapi"
                        && psiWithExe.ArgumentList[1] == "send-message"
                        && psiWithExe.ArgumentList[2] == "--"
                        && psiWithExe.ArgumentList[3] == "a75e6db9-d026-4955-97c0-6e8695d6193d"
                        && psiWithExe.ArgumentList[4] == trickyPrompt
                        && !psiFallback.UseShellExecute
                        && psiFallback.FileName == "agentapi"
                        && psiFallback.ArgumentList.Count == 4
                        && psiFallback.ArgumentList[0] == "send-message"
                        && psiFallback.ArgumentList[1] == "--"
                        && psiFallback.ArgumentList[2] == "a75e6db9-d026-4955-97c0-6e8695d6193d"
                        && psiFallback.ArgumentList[3] == trickyPrompt);
                }

                // 52. Zero-Secret Tresor Broker Vault Roundtrip (DPAPI + AES-256-GCM), Exclusion Of Private Entries & Tamper Guard
                string tempBrokerVault = Path.Combine(tempVaultDir, "ai_broker_vault.bin");
                McpTresorBroker.TestBrokerVaultFileOverride = tempBrokerVault;
                {
                    McpSecurityGuard.ResetRateLimiterForTests();
                    McpTresorBroker.WriteBrokerVaultForTest(tempBrokerVault, new[]
                    {
                        new McpTresorBroker.TestBrokerSeedEntry
                        {
                            Id = "priv-bank-001",
                            Title = "Sparkasse Privat",
                            Url = "https://sparkasse.de",
                            Username = "alina_privat",
                            Password = "PrivatBankTopSecretPassword#99!",
                            TotpSeedOrConfig = "JBSWY3DPEHPK3PXP",
                            ApiToken = "priv_bank_token_999",
                            AiBrokerEnabled = false
                        },
                        new McpTresorBroker.TestBrokerSeedEntry
                        {
                            Id = "mw-approved-001",
                            Title = "Mittwald mStudio",
                            Url = "https://studio.mittwald.de",
                            Username = "kontakt@mein-seelenfunke.de",
                            Password = "MittwaldVaultSecretPassword#2026!",
                            TotpSeedOrConfig = "otpauth://totp/Mittwald:Alina?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&digits=6&algorithm=SHA1",
                            ApiToken = "mw_pat_SuperSecretBrokerToken2026!",
                            UpdatedAtUtc = "2026-11-16T09:00:00Z",
                            AiBrokerEnabled = true
                        }
                    });

                    string? listMcpResp = await Program.ProcessJsonRpcMessageAsync(
                        """{"jsonrpc":"2.0","id":5201,"method":"tools/call","params":{"name":"tresor_list_ai_entries","arguments":{}}}""");
                    var directList = McpTresorBroker.ListApprovedEntries();

                    // Verify 1-byte AES-GCM tamper is rejected Fail-Closed
                    string tamperedVault = tempBrokerVault + ".tampered";
                    byte[] rawVaultBytes = await File.ReadAllBytesAsync(tempBrokerVault);
                    rawVaultBytes[^1] ^= 0xFF;
                    await File.WriteAllBytesAsync(tamperedVault, rawVaultBytes);
                    McpTresorBroker.TestBrokerVaultFileOverride = tamperedVault;
                    bool tamperBlocked = false;
                    try { McpTresorBroker.ListApprovedEntries(); }
                    catch (CryptographicException) { tamperBlocked = true; }
                    finally { McpTresorBroker.TestBrokerVaultFileOverride = tempBrokerVault; }

                    // Verify private entry cannot be looked up by BrowserLogin or MittwaldBroker
                    string? privLookupResp = await Program.ProcessJsonRpcMessageAsync(
                        """{"jsonrpc":"2.0","id":5202,"method":"tools/call","params":{"name":"tresor_browser_login","arguments":{"titleOrId":"Sparkasse Privat"}}}""");

                    string listInnerText = listMcpResp != null
                        ? (JsonNode.Parse(listMcpResp)?["result"]?["content"]?[0]?["text"]?.GetValue<string>() ?? "")
                        : "";

                    AssertTest("52. Tresor Broker DPAPI+AES-256-GCM Roundtrip: 0 % Private Entries & 0 Secrets Exposed In tresor_list_ai_entries",
                        directList.VaultExists
                        && directList.Count == 1
                        && directList.Entries[0].Id == "mw-approved-001"
                        && directList.Entries[0].AllowedHost == "studio.mittwald.de"
                        && directList.Entries[0].HasUsername
                        && directList.Entries[0].HasPassword
                        && directList.Entries[0].HasTotp
                        && directList.Entries[0].HasApiToken
                        && listMcpResp != null
                        && listMcpResp.Contains("\"isError\":false")
                        && listInnerText.Contains("\"studio.mittwald.de\"", StringComparison.Ordinal)
                        && !listMcpResp.Contains("Sparkasse", StringComparison.OrdinalIgnoreCase)
                        && !listMcpResp.Contains("MittwaldVaultSecretPassword", StringComparison.Ordinal)
                        && !listMcpResp.Contains("GEZDGNBVGY3TQOJQ", StringComparison.Ordinal)
                        && !listMcpResp.Contains("mw_pat_SuperSecretBrokerToken", StringComparison.Ordinal)
                        && !listMcpResp.Contains("kontakt@mein-seelenfunke.de", StringComparison.Ordinal)
                        && tamperBlocked
                        && privLookupResp != null && privLookupResp.Contains("\"isError\":true"));
                }

                // 53. RFC-6238 TOTP Vectors (SHA1/SHA256/SHA512) & Anti-Phishing Domain Guard
                {
                    var t59 = DateTimeOffset.FromUnixTimeSeconds(59);
                    byte[] sha1Seed = Encoding.UTF8.GetBytes("GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ");
                    byte[] sha256Uri = Encoding.UTF8.GetBytes("otpauth://totp/Test?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZA====&digits=8&algorithm=SHA256");
                    byte[] sha512Uri = Encoding.UTF8.GetBytes("otpauth://totp/Test?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNA=&digits=8&algorithm=SHA512");

                    bool totpOk = McpTresorBroker.TryGenerateTotpCode(sha1Seed, t59, out string c1, out int rem1) && c1 == "287082" && rem1 == 1
                        && McpTresorBroker.TryGenerateTotpCode(sha256Uri, t59, out string c256, out _) && c256 == "46119246"
                        && McpTresorBroker.TryGenerateTotpCode(sha512Uri, t59, out string c512, out _) && c512 == "90693936";

                    // Exact + direct subdomain of registrable base domain allowed
                    string m1 = McpTresorBroker.ValidateAntiPhishingTargetUrl("https://studio.mittwald.de", "https://studio.mittwald.de/app/projects");
                    string m2 = McpTresorBroker.ValidateAntiPhishingTargetUrl("https://studio.mittwald.de", "https://login.mittwald.de/oauth2/authorize");

                    // Phishing / downgrade / PSL / UserInfo attacks MUST all throw UnauthorizedAccessException
                    (string EntryUrl, string EvilTabUrl)[] phishingCases =
                    {
                        ("https://studio.mittwald.de", "http://studio.mittwald.de/login"),
                        ("https://studio.mittwald.de", "https://evil-mittwald.com/login"),
                        ("https://studio.mittwald.de", "https://studio.mittwald.de.evil.com/login"),
                        ("https://studio.mittwald.de", "https://google.com"),
                        ("https://studio.mittwald.de", "https://studio.mittwald.de@evil.com/login"),
                        ("https://portal.firma.co.uk", "https://attacker.co.uk/login"),
                        ("https://myshop.github.io", "https://phisher.github.io/login"),
                        ("https://studio.mittwald.de", "https://deep.nested.login.mittwald.de/phish")
                    };

                    bool allPhishingBlocked = true;
                    foreach (var (eUrl, badTab) in phishingCases)
                    {
                        bool threw = false;
                        try { McpTresorBroker.ValidateAntiPhishingTargetUrl(eUrl, badTab); }
                        catch (UnauthorizedAccessException) { threw = true; }
                        if (!threw) { allPhishingBlocked = false; break; }
                    }

                    AssertTest("53. RFC-6238 TOTP Vectors (SHA1/SHA256/SHA512) & Anti-Phishing Guard (Blocks HTTP, evil-mittwald.com, PSL & UserInfo)",
                        totpOk && m1 == "studio.mittwald.de" && m2 == "login.mittwald.de" && allPhishingBlocked);
                }

                // 54 & 55. Loopback CDP Browser Auto-Fill E2E (DevToolsActivePort flatten=true Multi-Step + /json/list Fallback + Phishing Abort)
                await using (var mockCdp = new MockCdpLoopbackServer())
                {
                    McpSecurityGuard.ResetRateLimiterForTests();
                    string fakeActivePortFile = Path.Combine(tempVaultDir, "DevToolsActivePort");
                    await File.WriteAllLinesAsync(fakeActivePortFile, new[]
                    {
                        mockCdp.Port.ToString(),
                        "/devtools/browser/11111111-2222-3333-4444-555555555555"
                    });
                    McpTresorBroker.TestDevToolsActivePortFileOverride = fakeActivePortFile;
                    McpTresorBroker.TestDefaultCdpPortOverride = mockCdp.Port;

                    // 54: DevToolsActivePort mode (HTTP /json/list disabled just like chrome://inspect/#remote-debugging!) + Multi-Step Email -> Password -> 2FA TOTP
                    mockCdp.ResetState("https://login.mittwald.de/sso", multiStep: true, disableJsonList: true);
                    string? loginMcpResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 5401,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "tresor_browser_login",
                            ["arguments"] = new JsonObject
                            {
                                ["titleOrId"] = "Mittwald",
                                ["submit"] = true
                            }
                        }
                    }.ToJsonString());

                    string loginInnerText = loginMcpResp != null
                        ? (JsonNode.Parse(loginMcpResp)?["result"]?["content"]?[0]?["text"]?.GetValue<string>() ?? "")
                        : "";

                    bool multiStepOk = mockCdp.FlattenAttachUsed
                        && mockCdp.CapturedUsername == "kontakt@mein-seelenfunke.de"
                        && mockCdp.CapturedPassword == "MittwaldVaultSecretPassword#2026!"
                        && !string.IsNullOrEmpty(mockCdp.CapturedTotp) && mockCdp.CapturedTotp!.Length == 6 && mockCdp.CapturedTotp.All(char.IsDigit)
                        && mockCdp.CapturedSubmitted
                        && loginMcpResp != null
                        && loginMcpResp.Contains("\"isError\":false")
                        && loginInnerText.Contains("\"matched_host\": \"login.mittwald.de\"", StringComparison.Ordinal)
                        && loginInnerText.Contains("\"filled_username\": true", StringComparison.Ordinal)
                        && loginInnerText.Contains("\"filled_password\": true", StringComparison.Ordinal)
                        && loginInnerText.Contains("\"filled_totp\": true", StringComparison.Ordinal)
                        && !loginMcpResp.Contains("MittwaldVaultSecretPassword", StringComparison.Ordinal)
                        && !loginMcpResp.Contains(mockCdp.CapturedTotp, StringComparison.Ordinal);

                    AssertTest("54. Tresor Browser Login E2E Over DevToolsActivePort (Target.attachToTarget flatten=true, Multi-Step + 2FA TOTP, 0 Secrets Output)",
                        multiStepOk, loginMcpResp ?? "null");

                    // 55: Fallback to /json/list when DevToolsActivePort is absent + Fail-Closed Anti-Phishing on evil tab (0 credentials sent!)
                    McpTresorBroker.TestDevToolsActivePortFileOverride = Path.Combine(tempVaultDir, "NonExistent_DevToolsActivePort");
                    mockCdp.ResetState("https://studio.mittwald.de/login", multiStep: false, disableJsonList: false);
                    var jsonListRes = await McpTresorBroker.BrowserLoginAsync("Mittwald", submit: true, totpOnly: false);

                    mockCdp.ResetState("https://evil-mittwald.com/phish", multiStep: false, disableJsonList: false);
                    bool evilAbortedBeforeFill = false;
                    try
                    {
                        await McpTresorBroker.BrowserLoginAsync("Mittwald", submit: true, totpOnly: false);
                    }
                    catch (UnauthorizedAccessException)
                    {
                        evilAbortedBeforeFill = true;
                    }

                    AssertTest("55. Tresor Browser Login /json/list Fallback & Fail-Closed Phishing Abort (0 DOM Calls On evil-mittwald.com)",
                        jsonListRes.Status == "ok"
                        && jsonListRes.MatchedHost == "studio.mittwald.de"
                        && jsonListRes.FilledUsername
                        && jsonListRes.FilledPassword
                        && evilAbortedBeforeFill
                        && mockCdp.CallFunctionCount == 0
                        && mockCdp.CapturedPassword == null);
                }

                // 56 & 57. Official Mittwald mStudio REST API v2 Broker & Hard Live-Air-Gap Guard
                using (var mockMw = new MockMittwaldHttpHandler())
                using (var mockMwClient = new HttpClient(mockMw) { Timeout = TimeSpan.FromSeconds(5) })
                {
                    McpSecurityGuard.ResetRateLimiterForTests();
                    McpTresorBroker.TestMittwaldHttpClientOverride = mockMwClient;

                    // 56: Read-only presets + dual headers + DLP token scrubbing when echoed
                    var selfRes = await McpTresorBroker.ExecuteMittwaldApiPresetAsync("user_self");
                    var projRes = await McpTresorBroker.ExecuteMittwaldApiPresetAsync("list_projects");
                    var appsRes = await McpTresorBroker.ExecuteMittwaldApiPresetAsync("list_apps", projectId: "proj-uuid-sf-01");
                    var cronListRes = await McpTresorBroker.ExecuteMittwaldApiPresetAsync("list_cronjobs", projectId: "proj-uuid-sf-01");
                    var ingRes = await McpTresorBroker.ExecuteMittwaldApiPresetAsync("list_ingresses", projectId: "proj-uuid-sf-01");

                    string serializedSelf = JsonSerializer.Serialize(selfRes);
                    bool headersVerified = mockMw.Captured.Count == 5
                        && mockMw.Captured.All(r => r.Uri.Scheme == "https" && r.Uri.Host == "api.mittwald.de"
                            && r.Bearer == "mw_pat_SuperSecretBrokerToken2026!"
                            && r.XAccessToken == "mw_pat_SuperSecretBrokerToken2026!");

                    AssertTest("56. Mittwald API v2 Broker Read-Only Presets, Dual Auth Headers & Outbound DLP Token Scrubbing",
                        headersVerified
                        && selfRes.Status == "ok"
                        && projRes.Data is JsonArray
                        && appsRes.Data is JsonArray
                        && cronListRes.Data is JsonArray
                        && ingRes.Data is JsonArray
                        && !serializedSelf.Contains("mw_pat_SuperSecretBrokerToken2026!", StringComparison.Ordinal)
                        && serializedSelf.Contains("[REDACTED-BY-FUNKENWERK-DLP]", StringComparison.Ordinal));

                    // 57: Stage mutations (shortId a-lc6tkm -> UUID resolution + Stage cronjob) ALLOWED; ALL Live/Ambiguous apps & cronjobs HARD-BLOCKED!
                    mockMw.Captured.Clear();
                    var restartStageRes = await McpTresorBroker.ExecuteMittwaldApiPresetAsync("restart_stage_app", appId: "a-lc6tkm");
                    var execStageCronRes = await McpTresorBroker.ExecuteMittwaldApiPresetAsync("execute_stage_cronjob", cronjobId: "cron-stage-01");

                    int postsAfterValidStage = mockMw.Captured.Count(r => r.Method == HttpMethod.Post);

                    bool liveShortIdBlocked = false;
                    try { await McpTresorBroker.ExecuteMittwaldApiPresetAsync("restart_stage_app", appId: "a-bycqrf"); }
                    catch (UnauthorizedAccessException) { liveShortIdBlocked = true; }

                    bool liveUuidBlockedAfterGet = false;
                    try { await McpTresorBroker.ExecuteMittwaldApiPresetAsync("restart_stage_app", appId: "app-uuid-live-web"); }
                    catch (UnauthorizedAccessException) { liveUuidBlockedAfterGet = true; }

                    bool ambiguousAppBlocked = false;
                    try { await McpTresorBroker.ExecuteMittwaldApiPresetAsync("restart_stage_app", appId: "app-uuid-ambiguous"); }
                    catch (UnauthorizedAccessException) { ambiguousAppBlocked = true; }

                    bool liveOwnedCronBlocked = false;
                    try { await McpTresorBroker.ExecuteMittwaldApiPresetAsync("execute_stage_cronjob", cronjobId: "cron-generic-on-live-app"); }
                    catch (UnauthorizedAccessException) { liveOwnedCronBlocked = true; }

                    int postsAfterBlockedAttempts = mockMw.Captured.Count(r => r.Method == HttpMethod.Post);

                    AssertTest("57. Mittwald API v2 Live-Air-Gap Guard Allows Stage (a-lc6tkm) & Blocks All Live Apps/Cronjobs With 0 Live POSTs",
                        restartStageRes.HttpStatus == 204
                        && execStageCronRes.HttpStatus == 204
                        && postsAfterValidStage == 2
                        && liveShortIdBlocked
                        && liveUuidBlockedAfterGet
                        && ambiguousAppBlocked
                        && liveOwnedCronBlocked
                        && postsAfterBlockedAttempts == 2);
                }

                // 58. Windows Desktop Zero-Log-Baseline (funkenwerk_tresor_boot.log + error_sentinel_queue.json Detection, DLP Scrubbing & Archive-to-0-Bytes)
                {
                    string winLocalRoot = Path.Combine(tempRoot, "MockLocalAppData");
                    string mockTresorDir = Path.Combine(winLocalRoot, "FunkenwerkTresor");
                    string mockFwDir = Path.Combine(winLocalRoot, "Funkenwerk");
                    Directory.CreateDirectory(mockTresorDir);
                    Directory.CreateDirectory(mockFwDir);

                    string mockBootLog = Path.Combine(mockTresorDir, "funkenwerk_tresor_boot.log");
                    File.WriteAllText(mockBootLog, "[12:00:00.000] DOMAIN_EXCEPTION: Crash with Bearer SecretBearerToken99999999999999999999", Encoding.UTF8);

                    string mockQueue = Path.Combine(mockFwDir, "error_sentinel_queue.json");
                    File.WriteAllText(mockQueue, "[{\"Component\":\"camera_studio\",\"Title\":\"MockCrash\",\"Message\":\"key=SuperSecret12345\",\"Severity\":\"error\",\"AutoHealed\":false}]", Encoding.UTF8);

                    var seenFps = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
                    var items = new JsonArray();
                    int detected = Program.CollectWindowsDesktopDossiers(seenFps, items, winLocalRoot);
                    string itemsDump = items.ToJsonString();

                    var (archFiles, archBytes, remBytes) = Program.ArchiveAndZeroWindowsDesktopLogs(winLocalRoot);
                    int detectedAfterZero = Program.CollectWindowsDesktopDossiers(new HashSet<string>(StringComparer.OrdinalIgnoreCase), new JsonArray(), winLocalRoot);
                    bool tresorArchiveExists = Directory.Exists(Path.Combine(mockTresorDir, "archive")) &&
                                               Directory.GetFiles(Path.Combine(mockTresorDir, "archive"), "healed_*_funkenwerk_tresor_boot.log").Length == 1;

                    AssertTest("58. Windows Desktop Zero-Log-Baseline (Tresor Boot-Log & Queue Detection, DLP Scrubbing & Lossless 0-Byte Reset)",
                        detected == 2
                        && !itemsDump.Contains("SecretBearerToken99999999999999999999", StringComparison.Ordinal)
                        && archFiles == 2
                        && archBytes > 0
                        && remBytes == 0
                        && new FileInfo(mockBootLog).Length == 0
                        && !File.Exists(mockQueue)
                        && tresorArchiveExists
                        && detectedAfterZero == 0);
                }

                // 59. Sentinel Hourly Silent Guard (0 New Chats, Healthy Fast-Exit, Info-Log Rotation, Master-Chat send-message Only & SHA-256 Deduplication)
                {
                    string guardDir = Path.Combine(tempRoot, "SentinelGuardTest");
                    string guardLogsDir = Path.Combine(guardDir, "logs");
                    Directory.CreateDirectory(guardLogsDir);
                    string mockInbox = Path.Combine(guardDir, "antigravity_inbox.json");
                    string mockLaravelLog = Path.Combine(guardLogsDir, "laravel.log");
                    string mockNotified = Path.Combine(guardDir, "sentinel_last_notified.json");
                    string masterChatId = "a75e6db9-d026-4955-97c0-6e8695d6193d";

                    // Case 1: Healthy (open_pool_count == 0, 0-byte log) -> HealthyZeroBytes, 0 prompts
                    await File.WriteAllTextAsync(mockInbox, """{"open_pool_count":0,"items":[]}""");
                    await File.WriteAllTextAsync(mockLaravelLog, "");
                    var evalHealthy = Program.EvaluateSentinelHourlyState(mockInbox, guardLogsDir, masterChatId, mockNotified);

                    // Case 2: Harmless info log (>0 bytes, no ERROR/CRITICAL/Exception) -> HarmlessInfoLogsOnlyRotate + fallback 0-byte reset
                    await File.WriteAllTextAsync(mockLaravelLog, "[2026-11-16 14:00:00] local.INFO: Scheduler heartbeat completed cleanly.\n");
                    var evalInfoOnly = Program.EvaluateSentinelHourlyState(mockInbox, guardLogsDir, masterChatId, mockNotified);
                    var (fbArchCount, fbArchBytes, fbRemBytes) = Program.ArchiveAndZeroLocalPortalLogsFallback(guardLogsDir);

                    // Case 3: Real unresolved dossier -> ErrorNeedsMasterChatNotification via send-message (NEVER new-conversation!) + Deduplication
                    await File.WriteAllTextAsync(mockInbox, """
                    {
                      "open_pool_count": 1,
                      "items": [
                        {
                          "fingerprint": "fp_stage_checkout_err_01",
                          "platform": "portal",
                          "title": "Checkout Livewire Exception",
                          "occurrences_count": 1
                        }
                      ]
                    }
                    """);
                    var evalErrFirst = Program.EvaluateSentinelHourlyState(mockInbox, guardLogsDir, masterChatId, mockNotified);
                    var guardPsi = McpSecurityGuard.BuildAgentApiSendMessageStartInfo(
                        evalErrFirst.TargetConversationId,
                        evalErrFirst.SelfHealPrompt ?? "missing",
                        agentApiExeOverride: @"C:\Antigravity\language_server.exe");

                    McpSecurityGuard.SaveSentinelLastNotifiedHash(evalErrFirst.StateHash, masterChatId, mockNotified);

                    // Even if occurrences_count increments next hour, identical fingerprint set must be deduplicated!
                    await File.WriteAllTextAsync(mockInbox, """
                    {
                      "open_pool_count": 1,
                      "items": [
                        {
                          "fingerprint": "fp_stage_checkout_err_01",
                          "platform": "portal",
                          "title": "Checkout Livewire Exception",
                          "occurrences_count": 7
                        }
                      ]
                    }
                    """);
                    var evalErrDuplicate = Program.EvaluateSentinelHourlyState(mockInbox, guardLogsDir, masterChatId, mockNotified);

                    // Once healed back to 0, deduplication state must clear automatically so future regressions re-notify
                    await File.WriteAllTextAsync(mockInbox, """{"open_pool_count":0,"items":[]}""");
                    var evalHealedAgain = Program.EvaluateSentinelHourlyState(mockInbox, guardLogsDir, masterChatId, mockNotified);

                    string sidecarFile = Path.Combine(
                        Environment.GetFolderPath(Environment.SpecialFolder.UserProfile),
                        ".gemini", "config", "sidecars", "seelenfunke-universe-self-heal", "sidecar.json");
                    bool sidecarVerified = true;
                    if (File.Exists(sidecarFile))
                    {
                        string scRaw = await File.ReadAllTextAsync(sidecarFile);
                        sidecarVerified = scRaw.Contains("--sentinel-hourly-guard", StringComparison.Ordinal)
                            && !scRaw.Contains("new-conversation", StringComparison.Ordinal);
                    }

                    AssertTest("59. Sentinel Hourly Silent Guard (0 New Chats, Healthy Fast-Exit, Info Rotation & SHA-256 Deduplication)",
                        evalHealthy.Decision == Program.SentinelHourlyDecision.HealthyZeroBytes
                        && evalHealthy.SelfHealPrompt == null
                        && evalInfoOnly.Decision == Program.SentinelHourlyDecision.HarmlessInfoLogsOnlyRotate
                        && evalInfoOnly.SelfHealPrompt == null
                        && fbArchCount == 1 && fbArchBytes > 0 && fbRemBytes == 0
                        && evalErrFirst.Decision == Program.SentinelHourlyDecision.ErrorNeedsMasterChatNotification
                        && !string.IsNullOrWhiteSpace(evalErrFirst.SelfHealPrompt)
                        && guardPsi.ArgumentList.Contains("send-message")
                        && !guardPsi.ArgumentList.Contains("new-conversation")
                        && guardPsi.ArgumentList.Contains(masterChatId)
                        && evalErrDuplicate.Decision == Program.SentinelHourlyDecision.DuplicateErrorAlreadyNotified
                        && evalErrDuplicate.SelfHealPrompt == null
                        && evalHealedAgain.Decision == Program.SentinelHourlyDecision.HealthyZeroBytes
                        && !File.Exists(mockNotified)
                        && sidecarVerified);
                }

                // 60. 5 Parallel Department Channels + 'new' Special Channel + UUID Auto-Discovery + StageDeployMutex
                {
                    McpSecurityGuard.ResetRateLimiterForTests();

                    // a) NormalizeAgChannel & Title/Scope mapping
                    bool normOk = McpSecurityGuard.NormalizeAgChannel("shop") == "shop"
                                  && McpSecurityGuard.NormalizeAgChannel("ANDROID") == "android"
                                  && McpSecurityGuard.NormalizeAgChannel("mini-pc") == "minipc"
                                  && McpSecurityGuard.NormalizeAgChannel("buchhaltung") == "buchhaltung"
                                  && McpSecurityGuard.NormalizeAgChannel("new") == "new"
                                  && McpSecurityGuard.NormalizeAgChannel("") == "leitung"
                                  && McpSecurityGuard.NormalizeAgChannel("unbekannt_xyz") == "leitung"
                                  && McpSecurityGuard.GetAgChannelDisplayTitle("shop") == "Shop & Konfigurator"
                                  && McpSecurityGuard.GetAgChannelDisplayTitle("buchhaltung") == "Buchhaltung & Marketing";

                    // b) Prompt header includes [KANAL: ...] & file-conflict scope hint
                    string shopPrompt = McpSecurityGuard.BuildAgDirectMasterPrompt(
                        "ag_chan_01", "Konfigurator Preislogik", "Prüfen", null, "direct", null, "shop");
                    bool shopPromptOk = shopPrompt.Contains("[KANAL: Shop & Konfigurator]", StringComparison.Ordinal)
                                        && shopPrompt.Contains("ohne AccountingSeeder.php", StringComparison.Ordinal);

                    // c) BuildAgentApiNewConversationStartInfo hardening (UseShellExecute=false, --title=..., --)
                    var newConvPsi = McpSecurityGuard.BuildAgentApiNewConversationStartInfo(
                        "Shop & Konfigurator\r\nInject",
                        shopPrompt,
                        agentApiExeOverride: @"C:\Antigravity\language_server.exe");
                    bool newConvPsiOk = !newConvPsi.UseShellExecute
                                        && newConvPsi.ArgumentList.Count == 5
                                        && newConvPsi.ArgumentList[0] == "agentapi"
                                        && newConvPsi.ArgumentList[1] == "new-conversation"
                                        && newConvPsi.ArgumentList[2] == "--title=Shop & Konfigurator Inject"
                                        && newConvPsi.ArgumentList[3] == "--"
                                        && newConvPsi.ArgumentList[4] == shopPrompt;

                    // d) UUID extraction from stdout AND from simulated brain directory fallback
                    string mockBrainRoot = Path.Combine(tempRoot, "MockBrain");
                    string oldUuid = "11111111-1111-4111-8111-111111111111";
                    string freshFolderUuid = "22222222-3333-4444-9999-aaaaaaaaaaaa";
                    string stdoutUuid = "33333333-4444-4555-8666-bbbbbbbbbbbb";
                    Directory.CreateDirectory(Path.Combine(mockBrainRoot, oldUuid));
                    var preSnap = McpSecurityGuard.SnapshotAntigravityBrainUuidFolders(mockBrainRoot);
                    Directory.CreateDirectory(Path.Combine(mockBrainRoot, freshFolderUuid));

                    string? fromStdout = McpSecurityGuard.ExtractConversationUuid($"Created conversation {stdoutUuid} OK", null, preSnap, mockBrainRoot);
                    string? fromBrainFallback = McpSecurityGuard.ExtractConversationUuid("OK (no id printed)", "", preSnap, mockBrainRoot);

                    // e) ChannelConversationIds persistence + MCP set_channel_chat / list_channels / reset_channel_chat
                    string? setShopResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 6001,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "ag_direct_inbox",
                            ["arguments"] = new JsonObject
                            {
                                ["action"] = "set_channel_chat",
                                ["channel"] = "shop",
                                ["conversation_id"] = freshFolderUuid
                            }
                        }
                    }.ToJsonString());
                    var afterSetShopCfg = McpSecurityGuard.LoadAgBridgeConfig();
                    bool shopPersisted = afterSetShopCfg.ChannelConversationIds.TryGetValue("shop", out string? savedShopId)
                                         && savedShopId == freshFolderUuid
                                         && afterSetShopCfg.ChannelConversationIds["leitung"] == afterSetShopCfg.MasterConversationId;

                    string? resetShopResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 6002,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "ag_direct_inbox",
                            ["arguments"] = new JsonObject
                            {
                                ["action"] = "reset_channel_chat",
                                ["channel"] = "shop"
                            }
                        }
                    }.ToJsonString());
                    var afterResetShopCfg = McpSecurityGuard.LoadAgBridgeConfig();
                    bool shopResetOk = !afterResetShopCfg.ChannelConversationIds.ContainsKey("shop")
                                       && afterResetShopCfg.ChannelConversationIds["leitung"] == afterResetShopCfg.MasterConversationId;

                    // f) Cross-thread / cross-process StageDeployMutex contention & clean wait handoff
                    string testMutexName = $@"Local\FunkenwerkStageDeployTest_{Guid.NewGuid():N}";
                    int waitingLogCount = 0;
                    Task<Program.StageDeployMutexLock> secondAcquireTask;
                    using (var firstLock = await Program.StageDeployMutexLock.AcquireAsync(TimeSpan.FromSeconds(5), testMutexName))
                    {
                        secondAcquireTask = Program.StageDeployMutexLock.AcquireAsync(
                            TimeSpan.FromSeconds(5),
                            testMutexName,
                            onWaiting: _ => Interlocked.Increment(ref waitingLogCount));
                        await Task.Delay(120);
                    }
                    using var secondLock = await secondAcquireTask;
                    bool mutexOk = secondLock.WaitedForContention && waitingLogCount == 1;

                    AssertTest("60. 5 Department Channels + 'new' Special Channel + Brain UUID Fallback + StageDeployMutex",
                        normOk
                        && shopPromptOk
                        && newConvPsiOk
                        && fromStdout == stdoutUuid
                        && fromBrainFallback == freshFolderUuid
                        && setShopResp != null && setShopResp.Contains("\"isError\":false")
                        && shopPersisted
                        && resetShopResp != null && resetShopResp.Contains("\"isError\":false")
                        && shopResetOk
                        && mutexOk);
                }

                // 61. Pinned Zero-Secret CLI --mittwald-prepare-live (steinhauer-invest Hard Block, Stage Air-Gap Intact, MCP Exclusion, Infra/Env/Cron & .env Isolation)
                using (var mockLiveMw = new MockMittwaldHttpHandler())
                using (var mockLiveClient = new HttpClient(mockLiveMw) { Timeout = TimeSpan.FromSeconds(5) })
                {
                    McpSecurityGuard.ResetRateLimiterForTests();
                    McpTresorBroker.TestMittwaldHttpClientOverride = mockLiveClient;
                    McpTresorBroker.TestPdoRetryDelayMs = 5;
                    McpTresorBroker.TestDnsManagedRetryDelayMs = 5;

                    // a) Not exposed in MCP tools/list or AllowedMittwaldPresets
                    string? toolsListJson = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 6101,
                        ["method"] = "tools/list"
                    }.ToJsonString());
                    bool mcpStrictlyExcluded = toolsListJson != null
                        && !toolsListJson.Contains("mittwald-prepare-live", StringComparison.OrdinalIgnoreCase)
                        && !toolsListJson.Contains("mittwald_prepare_live", StringComparison.OrdinalIgnoreCase)
                        && !McpTresorBroker.AllowedMittwaldPresets.Any(p => p.Contains("prepare", StringComparison.OrdinalIgnoreCase));

                    // b) Hard block on steinhauer-invest (a-s4sdym / 2be54dff-9651-416e-8aba-79a5b63749ff / mysql_fg9zlg) everywhere,
                    //    and Stage Air-Gap still blocks seelenfunke-live (93f33f15-d27c-4d15-9f85-155eb9adace8 / a-bycqrf)
                    int blockedCount = 0;
                    try { McpSecurityGuard.ValidatePinnedSeelenfunkeLiveTarget(McpSecurityGuard.PinnedLiveProjectId, "2be54dff-9651-416e-8aba-79a5b63749ff", "/home/p-g27wim/html/steinhauer-invest", "kontakt@mein-seelenfunke.de@a-s4sdym", "mysql_fg9zlg", McpSecurityGuard.PinnedLiveMysqlUserUuid); }
                    catch (UnauthorizedAccessException) { blockedCount++; }

                    try { McpSecurityGuard.ValidatePinnedSeelenfunkeLiveTarget(McpSecurityGuard.PinnedLiveProjectId, "other-uuid", McpSecurityGuard.PinnedLiveRemoteDir, McpSecurityGuard.PinnedLiveSshUser, McpSecurityGuard.PinnedLiveMysqlDatabase, McpSecurityGuard.PinnedLiveMysqlUserUuid); }
                    catch (UnauthorizedAccessException) { blockedCount++; }

                    try { await McpTresorBroker.ExecutePinnedSeelenfunkeLivePrepareAsync(step: "infra", entryTitleOrId: "steinhauer-invest"); }
                    catch (UnauthorizedAccessException) { blockedCount++; }

                    try { McpSecurityGuard.ValidateStageOnlyTarget(McpSecurityGuard.StageAllowedHost, McpSecurityGuard.PinnedLiveRemoteDir); }
                    catch (UnauthorizedAccessException) { blockedCount++; }

                    try { McpSecurityGuard.ValidateStageOnlyTarget(McpSecurityGuard.StageAllowedHost, "/home/p-g27wim/html/steinhauer-invest"); }
                    catch (UnauthorizedAccessException) { blockedCount++; }

                    try { await McpTresorBroker.ExecuteMittwaldApiPresetAsync("restart_stage_app", appId: McpSecurityGuard.PinnedLiveAppUuid); }
                    catch (UnauthorizedAccessException) { blockedCount++; }

                    try { await McpTresorBroker.ExecuteMittwaldApiPresetAsync("restart_stage_app", appId: "2be54dff-9651-416e-8aba-79a5b63749ff"); }
                    catch (UnauthorizedAccessException) { blockedCount++; }

                    // c) CSPRNG password validator check (28 chars, starts with letter, Mittwald + .env compliant)
                    char[] samplePwd = new char[28];
                    McpTresorBroker.GenerateMittwaldCompliantPasswordChars(samplePwd);
                    string samplePwdStr = new string(samplePwd);
                    Array.Clear(samplePwd, 0, samplePwd.Length);
                    bool pwdCompliant = samplePwdStr.Length == 28
                        && char.IsLetter(samplePwdStr[0])
                        && samplePwdStr.Any(char.IsUpper)
                        && samplePwdStr.Any(char.IsLower)
                        && samplePwdStr.Any(char.IsDigit)
                        && samplePwdStr.Any(c => "!%^*_-+=".Contains(c))
                        && !samplePwdStr.Any(c => "#\"'$\\;@ ".Contains(c));

                    // d) Full --step=all mock execution (initial provisioning) + idempotent second run
                    const string mockStageEnvText =
                        "APP_NAME=\"Mein Seelenfunke Stage\"\r\n" +
                        "APP_ENV=staging\r\n" +
                        "APP_DEBUG=true\r\n" +
                        "APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\r\n" +
                        "APP_URL=https://stage.mein-seelenfunke.de\r\n" +
                        "SESSION_DOMAIN=stage.mein-seelenfunke.de\r\n" +
                        "DB_CONNECTION=mysql\r\n" +
                        "DB_HOST=mysql-stage.project.host\r\n" +
                        "DB_DATABASE=mysql_stage\r\n" +
                        "DB_USERNAME=dbu_stage\r\n" +
                        "DB_PASSWORD=StageDbSecretShouldNeverCarryOver!\r\n" +
                        "CACHE_STORE=redis\r\n" +
                        "SESSION_DRIVER=redis\r\n" +
                        "QUEUE_CONNECTION=redis\r\n" +
                        "REDIS_HOST=redis-t4tm3u.pg-s-pis5pf.db.project.host\r\n" +
                        "REDIS_PASSWORD=StageRedisSecretPassword!\r\n" +
                        "GOOGLE_REDIRECT_URL=https://stage.mein-seelenfunke.de/auth/google/callback\r\n" +
                        "STRIPE_SECRET=sk_test_MockStageStripeKey999\r\n" +
                        "MAIL_HOST=mail.agenturserver.de\r\n" +
                        "GEMINI_API_KEY=AIzaMockGeminiKey123\r\n";

                    byte[]? currentMockLiveEnv = null;
                    int pdoCallsOnFirstRun = 0;

                    McpTresorBroker.TestReadContainerEnvOverride = (sshUser, remotePath) =>
                    {
                        if (remotePath == McpSecurityGuard.PinnedLiveEnvRemotePath)
                            return Task.FromResult(currentMockLiveEnv?.ToArray());
                        if (remotePath == McpSecurityGuard.PinnedStageEnvRemotePath)
                            return Task.FromResult<byte[]?>(Encoding.UTF8.GetBytes(mockStageEnvText));
                        throw new InvalidOperationException("Unexpected path");
                    };
                    McpTresorBroker.TestWriteLiveEnvOverride = envMem =>
                    {
                        currentMockLiveEnv = envMem.ToArray();
                        return Task.FromResult("600");
                    };
                    McpTresorBroker.TestProbeLiveMysqlPdoOverride = () =>
                    {
                        pdoCallsOnFirstRun++;
                        // Simulate 1 propagation retry before PDO_OK
                        if (pdoCallsOnFirstRun == 1)
                            return Task.FromResult((false, "PDO_ERR:1045"));
                        return Task.FromResult((true, "PDO_OK"));
                    };

                    var firstRunSummary = await McpTresorBroker.ExecutePinnedSeelenfunkeLivePrepareAsync(step: "all");
                    string firstRunJson = firstRunSummary.ToJsonString();
                    string writtenEnv1 = Encoding.UTF8.GetString(currentMockLiveEnv!);

                    bool firstRunOk = mockLiveMw.LiveDocRoot == "/public"
                        && mockLiveMw.LiveWwwIngressCreated
                        && mockLiveMw.LiveWwwDnsZoneCreated
                        && mockLiveMw.LiveWwwDnsManaged
                        && !mockLiveMw.LiveDigitalIngressExists
                        && !mockLiveMw.LiveDigitalDnsZoneExists
                        && firstRunSummary["infra"]?["retired_digital_ingress_removed"]?.GetValue<bool>() == true
                        && firstRunSummary["infra"]?["retired_digital_dns_zone_removed"]?.GetValue<bool>() == true
                        && mockLiveMw.LiveCronCreated
                        && mockLiveMw.LiveMysqlPasswordPatchCount == 1
                        && pdoCallsOnFirstRun == 2
                        && writtenEnv1.Contains("APP_ENV=production\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("APP_DEBUG=false\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("APP_URL=https://mein-seelenfunke.de\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains($"DB_HOST={McpSecurityGuard.PinnedLiveMysqlHost}\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains($"DB_DATABASE={McpSecurityGuard.PinnedLiveMysqlDatabase}\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains($"DB_USERNAME={McpSecurityGuard.PinnedLiveMysqlUser}\n", StringComparison.Ordinal)
                        && !writtenEnv1.Contains("StageDbSecretShouldNeverCarryOver!", StringComparison.Ordinal)
                        && !writtenEnv1.Contains("base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=", StringComparison.Ordinal)
                        && writtenEnv1.Contains("CACHE_STORE=file\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("CACHE_DRIVER=file\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("CACHE_PREFIX=seelenfunke_live_cache_\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("SESSION_DRIVER=file\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("SESSION_DOMAIN=null\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("QUEUE_CONNECTION=sync\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("BROADCAST_CONNECTION=log\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("REDIS_HOST=127.0.0.1\n", StringComparison.Ordinal)
                        && !writtenEnv1.Contains("redis-t4tm3u", StringComparison.Ordinal)
                        && !writtenEnv1.Contains("StageRedisSecretPassword!", StringComparison.Ordinal)
                        && writtenEnv1.Contains("GOOGLE_REDIRECT_URL=https://mein-seelenfunke.de/auth/google/callback\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("SANCTUM_STATEFUL_DOMAINS=mein-seelenfunke.de,www.mein-seelenfunke.de\n", StringComparison.Ordinal)
                        && !writtenEnv1.Contains("digital.mein-seelenfunke.de", StringComparison.Ordinal)
                        && firstRunSummary["env"]?["isolation_enforced"]?["APP_URL"]?.GetValue<string>() == "https://mein-seelenfunke.de"
                        && writtenEnv1.Contains("STRIPE_SECRET=sk_test_MockStageStripeKey999\n", StringComparison.Ordinal)
                        && writtenEnv1.Contains("MAIL_HOST=mail.agenturserver.de\n", StringComparison.Ordinal)
                        && !writtenEnv1.Contains("\r\n", StringComparison.Ordinal)
                        && !firstRunJson.Contains("sk_test_MockStageStripeKey999", StringComparison.Ordinal)
                        && !firstRunJson.Contains("DB_PASSWORD", StringComparison.Ordinal);

                    // Second run (idempotent: PDO already OK, docRoot already /public, ingress/zone/cron already present)
                    mockLiveMw.CapturedBodies.Clear();
                    McpTresorBroker.TestProbeLiveMysqlPdoOverride = () => Task.FromResult((true, "PDO_OK"));

                    var secondRunSummary = await McpTresorBroker.ExecutePinnedSeelenfunkeLivePrepareAsync(step: "all");
                    string writtenEnv2 = Encoding.UTF8.GetString(currentMockLiveEnv!);
                    var checkSummary = await McpTresorBroker.ExecutePinnedSeelenfunkeLivePrepareAsync(step: "check");
                    bool checkOk = checkSummary["check"]?["www_ingress_exists"]?.GetValue<bool>() == true
                        && checkSummary["check"]?["retired_digital_ingress_present"]?.GetValue<bool>() == false
                        && checkSummary["check"]?["retired_digital_dns_zone_present"]?.GetValue<bool>() == false;

                    bool secondRunIdempotent = mockLiveMw.LiveMysqlPasswordPatchCount == 1
                        && mockLiveMw.CapturedBodies.All(r => r.Method == HttpMethod.Get)
                        && secondRunSummary["env"]?["mysql_password_rotated"]?.GetValue<bool>() == false
                        && secondRunSummary["env"]?["reused_existing_app_key"]?.GetValue<bool>() == true
                        && secondRunSummary["env"]?["reused_existing_db_password"]?.GetValue<bool>() == true
                        && string.Equals(writtenEnv1, writtenEnv2, StringComparison.Ordinal)
                        && checkOk;

                    AssertTest("61. Pinned Zero-Secret CLI --mittwald-prepare-live (steinhauer-invest Block, Stage Air-Gap, MCP Exclusion, Infra/Env/Cron & Idempotency)",
                        mcpStrictlyExcluded
                        && blockedCount == 7
                        && pwdCompliant
                        && firstRunOk
                        && secondRunIdempotent);
                }

                // 62. Zero-Secret Stripe API v1 Broker (api.stripe.com:443 Pinning, Bearer Auth, Key-Mode Detection & DLP Scrubbing)
                {
                    McpSecurityGuard.ResetRateLimiterForTests();
                    McpTresorBroker.WriteBrokerVaultForTest(tempBrokerVault, new[]
                    {
                        new McpTresorBroker.TestBrokerSeedEntry
                        {
                            Id = "stripe-sbx-001",
                            Title = "Stripe Sandbox",
                            Url = "https://dashboard.stripe.com",
                            Username = "kontakt@mein-seelenfunke.de",
                            Password = "StripeLoginPwd#2026!",
                            ApiToken = "rk_test_SuperSecretRestrictedKey2026AbCdEf123456",
                            AiBrokerEnabled = true
                        }
                    });

                    var capturedUris = new List<Uri>();
                    var capturedAuth = new List<string>();
                    using var mockStripeHandler = new DelegatingMockStripeHandler((req) =>
                    {
                        capturedUris.Add(req.RequestUri!);
                        capturedAuth.Add(req.Headers.Authorization?.ToString() ?? "");
                        string body = """{"object":"balance","livemode":false,"available":[{"amount":12500,"currency":"eur"}],"echo_leak":"whsec_TestWebhookSecret9876543210abcdef rk_test_SuperSecretRestrictedKey2026AbCdEf123456"}""";
                        return new HttpResponseMessage(HttpStatusCode.OK)
                        {
                            Content = new StringContent(body, Encoding.UTF8, "application/json")
                        };
                    });
                    using var mockStripeClient = new HttpClient(mockStripeHandler);
                    McpTresorBroker.TestStripeHttpClientOverride = mockStripeClient;

                    var balRes = await McpTresorBroker.ExecuteStripeApiPresetAsync("balance", entryTitleOrId: "Stripe Sandbox");
                    string balJson = McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(balRes));

                    bool badPresetBlocked = false;
                    try { await McpTresorBroker.ExecuteStripeApiPresetAsync("create_transfer", entryTitleOrId: "Stripe Sandbox"); }
                    catch (UnauthorizedAccessException) { badPresetBlocked = true; }

                    McpTresorBroker.TestStripeHttpClientOverride = null;

                    AssertTest("62. Zero-Secret Stripe API v1 Broker (api.stripe.com:443 Pinning, rk_test_ Detection, DLP Scrubbing & Preset Allowlist)",
                        balRes.Status == "ok"
                        && balRes.KeyKind == "restricted"
                        && balRes.KeyMode == "test"
                        && capturedUris.Count == 1
                        && capturedUris[0].Scheme == "https"
                        && capturedUris[0].Host == "api.stripe.com"
                        && capturedAuth[0] == "Bearer rk_test_SuperSecretRestrictedKey2026AbCdEf123456"
                        && !balJson.Contains("rk_test_SuperSecretRestrictedKey2026AbCdEf123456", StringComparison.Ordinal)
                        && !balJson.Contains("whsec_TestWebhookSecret9876543210abcdef", StringComparison.Ordinal)
                        && balJson.Contains("[REDACTED-BY-FUNKENWERK-DLP]", StringComparison.Ordinal)
                        && badPresetBlocked);
                }

                // 63. Stage-Laser Windows Failover Relay (RFC1918 SSRF Guard, Mini-PC Online Passive Priority, 21x21 Dewarp & Failover Activation)
                {
                    McpStageLaserFailoverRelay.ResetCachesForTests();

                    // a) MCP JSON-RPC catalog untouched (CLI/Daemon-only feature)
                    string? toolsJson63 = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 6301,
                        ["method"] = "tools/list"
                    }.ToJsonString());
                    bool mcpCatalogUntouched = toolsJson63 != null
                        && !toolsJson63.Contains("stage-laser-relay", StringComparison.OrdinalIgnoreCase)
                        && !toolsJson63.Contains("laser_relay", StringComparison.OrdinalIgnoreCase);

                    // b) Strict RFC1918 Private LAN SSRF Guard (1:1 with Linux Check 45)
                    bool ssrfAllowOk =
                        McpStageLaserFailoverRelay.TryValidateRelayLaserIp(null, out string defIp, out _) && defIp == "192.168.188.150"
                        && McpStageLaserFailoverRelay.TryValidateRelayLaserIp("192.168.188.150", out string lanIp1, out _) && lanIp1 == "192.168.188.150"
                        && McpStageLaserFailoverRelay.TryValidateRelayLaserIp("10.0.4.12", out _, out _)
                        && McpStageLaserFailoverRelay.TryValidateRelayLaserIp("172.16.1.5", out _, out _)
                        && McpStageLaserFailoverRelay.TryValidateRelayLaserIp("172.31.255.254", out _, out _);

                    bool ssrfDenyOk =
                        !McpStageLaserFailoverRelay.TryValidateRelayLaserIp("127.0.0.1", out _, out _)
                        && !McpStageLaserFailoverRelay.TryValidateRelayLaserIp("169.254.169.254", out _, out _)
                        && !McpStageLaserFailoverRelay.TryValidateRelayLaserIp("100.64.0.1", out _, out _)
                        && !McpStageLaserFailoverRelay.TryValidateRelayLaserIp("172.32.0.1", out _, out _)
                        && !McpStageLaserFailoverRelay.TryValidateRelayLaserIp("8.8.8.8", out _, out _)
                        && !McpStageLaserFailoverRelay.TryValidateRelayLaserIp("192.168.01.5", out _, out _)
                        && !McpStageLaserFailoverRelay.TryValidateRelayLaserIp("localhost", out _, out _)
                        && !McpStageLaserFailoverRelay.TryValidateRelayLaserIp("192.168.188.150:28900", out _, out _)
                        && !McpStageLaserFailoverRelay.TryValidateRelayLaserIp("::1", out _, out _);

                    var blockedJobRes = await McpStageLaserFailoverRelay.ExecuteRelayJobAsync(new McpStageLaserFailoverRelay.LaserRelayJobDto
                    {
                        JobId = "lrel_ssrf_block_01",
                        Action = "capture_camera",
                        LaserIp = "169.254.169.254"
                    });
                    bool ssrfJobBlocked = !blockedJobRes.Ok
                        && blockedJobRes.State == "OFFLINE"
                        && (blockedJobRes.Error?.Contains("SSRF-Schutz", StringComparison.Ordinal) ?? false);

                    // c) Poll response deserialization & 21x21 perspective warp + G-code generation
                    var emptyPoll = McpStageLaserFailoverRelay.TryDeserializePollResponse("""{"success":true,"job":null}""");
                    var parsedCamJob = McpStageLaserFailoverRelay.TryDeserializePollResponse("""
                    {
                      "success": true,
                      "job": {
                        "job_id": "lrel_20261117_001500_a1b2c3d4",
                        "action": "capture_camera",
                        "laser_ip": "192.168.188.150"
                      }
                    }
                    """);
                    var parsedFrameJob = McpStageLaserFailoverRelay.TryDeserializePollResponse("""
                    {
                      "success": true,
                      "job": {
                        "job_id": "lrel_frame_02",
                        "action": "frame_border",
                        "center_x_mm": 100.0,
                        "center_y_mm": 95.0,
                        "width_mm": 60.0,
                        "height_mm": 40.0,
                        "angle_deg": 15.0
                      }
                    }
                    """);
                    string borderGcode = McpStageLaserFailoverRelay.GenerateBorderGcode(100.0, 95.0, 60.0, 40.0, 15.0);
                    bool deserOk = emptyPoll == null
                        && parsedCamJob != null
                        && parsedCamJob.JobId == "lrel_20261117_001500_a1b2c3d4"
                        && parsedCamJob.Action == "capture_camera"
                        && parsedCamJob.LaserIp == "192.168.188.150"
                        && parsedFrameJob != null
                        && parsedFrameJob.Action == "frame_border"
                        && Math.Abs(parsedFrameJob.CenterYMm - 95.0) < 0.01
                        && borderGcode.Contains("# GS009-4 HEAD", StringComparison.Ordinal);

                    // Synthetic 21x21 dewarp verification (160x120 synthetic JPEG -> 2000x2000 calibrated JPEG)
                    byte[] synthPixels = new byte[160 * 120 * 4];
                    for (int p = 0; p < synthPixels.Length; p += 4)
                    {
                        synthPixels[p] = 40;
                        synthPixels[p + 1] = 120;
                        synthPixels[p + 2] = 197;
                        synthPixels[p + 3] = 255;
                    }
                    var synthBmp = System.Windows.Media.Imaging.BitmapSource.Create(
                        160, 120, 96.0, 96.0, System.Windows.Media.PixelFormats.Bgra32, null, synthPixels, 160 * 4);
                    synthBmp.Freeze();
                    var synthEnc = new System.Windows.Media.Imaging.JpegBitmapEncoder { QualityLevel = 85 };
                    synthEnc.Frames.Add(System.Windows.Media.Imaging.BitmapFrame.Create(synthBmp));
                    byte[] synthRawJpeg;
                    using (var msSynth = new MemoryStream())
                    {
                        synthEnc.Save(msSynth);
                        synthRawJpeg = msSynth.ToArray();
                    }
                    var calibPts = new StringBuilder();
                    for (int r = 0; r < 21; r++)
                    {
                        for (int c = 0; c < 21; c++)
                        {
                            calibPts.Append($"[{c * 7},{r * 5}],");
                        }
                    }
                    string synthCalibJson = new JsonObject { ["data0"] = calibPts.ToString() }.ToJsonString();
                    var (warpedTestBytes, usedGridTest) = McpStageLaserFailoverRelay.TryWarpRawCameraJpeg2000x2000(synthRawJpeg, synthCalibJson);
                    bool warpOk = warpedTestBytes != null
                        && usedGridTest
                        && McpStageLaserFailoverRelay.TryReadJpegDimensions(warpedTestBytes, out int ww, out int wh)
                        && ww == 2000 && wh == 2000;

                    // d) Vault roundtrip + Mini-PC Awake Priority (0 Stage HTTP requests when Mini-PC 192.168.188.151:22 is online!)
                    string encVaultTok = McpSecurityGuard.ProtectFunkenwerkMainVaultSecret("99|MockStageFailoverTokenAbCdEf12345678901234567890");
                    string decVaultTok = McpSecurityGuard.UnprotectFunkenwerkMainVaultSecret(encVaultTok);
                    bool vaultRoundtripOk = encVaultTok.StartsWith("FWAES256GCM1:", StringComparison.Ordinal)
                        && decVaultTok == "99|MockStageFailoverTokenAbCdEf12345678901234567890";

                    var stageCapturedRequests = new List<(HttpMethod Method, Uri Uri, string Body)>();
                    using var mockStageRelayHandler = new AsyncDelegatingMockHandler(async req =>
                    {
                        string reqBody = req.Content != null ? await req.Content.ReadAsStringAsync() : "";
                        stageCapturedRequests.Add((req.Method, req.RequestUri!, reqBody));

                        if (req.Method == HttpMethod.Get && req.RequestUri!.AbsolutePath.EndsWith("/laser-relay/poll", StringComparison.Ordinal))
                        {
                            string pollReply = """
                            {
                              "success": true,
                              "job": {
                                "job_id": "lrel_failover_win_01",
                                "action": "capture_camera",
                                "laser_ip": "192.168.188.150"
                              }
                            }
                            """;
                            return new HttpResponseMessage(HttpStatusCode.OK)
                            {
                                Content = new StringContent(pollReply, Encoding.UTF8, "application/json")
                            };
                        }

                        if (req.Method == HttpMethod.Post && req.RequestUri!.AbsolutePath.EndsWith("/laser-relay/complete", StringComparison.Ordinal))
                        {
                            return new HttpResponseMessage(HttpStatusCode.OK)
                            {
                                Content = new StringContent("""{"success":true,"status":"completed","camera_saved":true}""", Encoding.UTF8, "application/json")
                            };
                        }

                        return new HttpResponseMessage(HttpStatusCode.OK)
                        {
                            Content = new StringContent("""{"success":true}""", Encoding.UTF8, "application/json")
                        };
                    });
                    using var mockStageRelayClient = new HttpClient(mockStageRelayHandler);

                    McpStageLaserFailoverRelay.TestStageHttpClientOverride = mockStageRelayClient;
                    McpStageLaserFailoverRelay.TestStageBearerTokenOverride = "99|MockStageFailoverTokenAbCdEf12345678901234567890";
                    McpStageLaserFailoverRelay.TestMiniPcProbeOverride = () => Task.FromResult(true);
                    McpStageLaserFailoverRelay.TestLaserPortProbeOverride = (_, port, _) => Task.FromResult(port == 28900);

                    var passiveReport = await McpStageLaserFailoverRelay.RunSingleFailoverCycleAsync(
                        sendExplicitHeartbeat: true,
                        runDiagnosticCameraTest: false,
                        forceRefreshProbes: true);
                    int requestsWhileMiniPcAwake = stageCapturedRequests.Count;

                    // e) Mini-PC Sleeping -> Automatic Windows Failover Activation
                    McpStageLaserFailoverRelay.ResetCachesForTests();
                    McpStageLaserFailoverRelay.TestMiniPcProbeOverride = () => Task.FromResult(false);
                    McpStageLaserFailoverRelay.TestJobExecutorOverride = job => Task.FromResult(new McpStageLaserFailoverRelay.LaserRelayCompletionPayload
                    {
                        JobId = job.JobId,
                        Ok = true,
                        LaserOnline = true,
                        State = "CAMERA_OK",
                        WarpedJpegBase64 = Convert.ToBase64String(warpedTestBytes!)
                    });

                    var activeReport = await McpStageLaserFailoverRelay.RunSingleFailoverCycleAsync(
                        sendExplicitHeartbeat: false,
                        runDiagnosticCameraTest: false,
                        forceRefreshProbes: true);
                    string scrubbedReportJson = McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(activeReport));

                    bool failoverFlowOk = passiveReport.Mode == "passive_minipc_online"
                        && passiveReport.MiniPcOnline
                        && !passiveReport.PolledStage
                        && !passiveReport.HeartbeatSent
                        && requestsWhileMiniPcAwake == 0
                        && activeReport.Mode == "active_windows_failover"
                        && !activeReport.MiniPcOnline
                        && activeReport.LaserOnline
                        && activeReport.PolledStage
                        && activeReport.HeartbeatSent
                        && activeReport.ClaimedJobId == "lrel_failover_win_01"
                        && activeReport.JobCompletedOk == true
                        && activeReport.JobResultState == "CAMERA_OK"
                        && stageCapturedRequests.Count == 2
                        && stageCapturedRequests[0].Method == HttpMethod.Get
                        && stageCapturedRequests[0].Uri.Host == "stage.mein-seelenfunke.de"
                        && stageCapturedRequests[0].Uri.Query.Contains("laser_online=1", StringComparison.Ordinal)
                        && stageCapturedRequests[1].Method == HttpMethod.Post
                        && stageCapturedRequests[1].Body.Contains("\"CAMERA_OK\"", StringComparison.Ordinal)
                        && !scrubbedReportJson.Contains("MockStageFailoverToken", StringComparison.Ordinal);

                    McpStageLaserFailoverRelay.TestMiniPcProbeOverride = null;
                    McpStageLaserFailoverRelay.TestLaserPortProbeOverride = null;
                    McpStageLaserFailoverRelay.TestJobExecutorOverride = null;
                    McpStageLaserFailoverRelay.TestStageHttpClientOverride = null;
                    McpStageLaserFailoverRelay.TestStageBearerTokenOverride = null;
                    McpStageLaserFailoverRelay.ResetCachesForTests();

                    AssertTest("63. Stage-Laser Windows Failover Relay (RFC1918 SSRF Guard, Mini-PC Priority 0-Poll Mode, 21x21 Dewarp & Auto-Failover)",
                        mcpCatalogUntouched
                        && ssrfAllowOk
                        && ssrfDenyOk
                        && ssrfJobBlocked
                        && deserOk
                        && warpOk
                        && vaultRoundtripOk
                        && failoverFlowOk);
                }

                Console.WriteLine($"=== RESULT: {passed} PASSED, {failed} FAILED ===");
                return failed == 0 ? 0 : 1;
            }
            finally
            {
                McpStageLaserFailoverRelay.TestMiniPcProbeOverride = null;
                McpStageLaserFailoverRelay.TestLaserPortProbeOverride = null;
                McpStageLaserFailoverRelay.TestJobExecutorOverride = null;
                McpStageLaserFailoverRelay.TestStageHttpClientOverride = null;
                McpStageLaserFailoverRelay.TestStageBearerTokenOverride = null;
                McpStageLaserFailoverRelay.ResetCachesForTests();
                McpOrganizerClient.TestHttpClientOverride = null;
                McpOrganizerClient.TestBaseUrlOverride = null;
                McpTresorBroker.TestBrokerVaultFileOverride = null;
                McpTresorBroker.TestDevToolsActivePortFileOverride = null;
                McpTresorBroker.TestDefaultCdpPortOverride = null;
                McpTresorBroker.TestMittwaldHttpClientOverride = null;
                McpTresorBroker.TestStripeHttpClientOverride = null;
                McpTresorBroker.TestReadContainerEnvOverride = null;
                McpTresorBroker.TestProbeLiveMysqlPdoOverride = null;
                McpTresorBroker.TestWriteLiveEnvOverride = null;
                McpTresorBroker.TestPdoRetryDelayMs = 1500;
                McpTresorBroker.TestDnsManagedRetryDelayMs = 2500;
                McpSecurityGuard.ResetRateLimiterForTests();
                try
                {
                    if (Directory.Exists(tempRoot))
                        Directory.Delete(tempRoot, recursive: true);
                }
                catch { }
            }
        }

        private sealed class DelegatingMockStripeHandler : HttpMessageHandler
        {
            private readonly Func<HttpRequestMessage, HttpResponseMessage> _responder;
            public DelegatingMockStripeHandler(Func<HttpRequestMessage, HttpResponseMessage> responder) => _responder = responder;
            protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
                => Task.FromResult(_responder(request));
        }

        private sealed class AsyncDelegatingMockHandler : HttpMessageHandler
        {
            private readonly Func<HttpRequestMessage, Task<HttpResponseMessage>> _responder;
            public AsyncDelegatingMockHandler(Func<HttpRequestMessage, Task<HttpResponseMessage>> responder) => _responder = responder;
            protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
                => _responder(request);
        }
    }
}
