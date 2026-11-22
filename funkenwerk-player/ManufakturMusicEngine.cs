using System;
using System.Collections.Generic;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Runtime.InteropServices;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Threading;
using Funkenwerk;

namespace FunkenwerkPlayer
{
    public sealed class MusicPlaylistDto
    {
        [JsonPropertyName("id")] public string Id { get; set; } = "";
        [JsonPropertyName("name")] public string Name { get; set; } = "";
        [JsonPropertyName("description")] public string? Description { get; set; }
        [JsonPropertyName("position")] public int Position { get; set; }
        [JsonPropertyName("is_local_folder")] public bool IsLocalFolder { get; set; }
        [JsonPropertyName("tracks")] public List<MusicTrackDto>? Tracks { get; set; }
    }

    public sealed class MusicTrackDto
    {
        [JsonPropertyName("id")] public string Id { get; set; } = "";
        [JsonPropertyName("playlist_id")] public string? PlaylistId { get; set; }
        [JsonPropertyName("external_id")] public string? ExternalId { get; set; }
        [JsonPropertyName("title")] public string Title { get; set; } = "";
        [JsonPropertyName("artist")] public string? Artist { get; set; }
        [JsonPropertyName("duration_seconds")] public int DurationSeconds { get; set; }
        [JsonPropertyName("is_favorite")] public bool IsFavorite { get; set; }
        [JsonPropertyName("position")] public int Position { get; set; }

        [JsonIgnore] public string? LocalFilePath { get; set; }

        [JsonIgnore]
        public string FormattedDuration => DurationSeconds <= 0
            ? "--:--"
            : $"{DurationSeconds / 60}:{DurationSeconds % 60:D2}";
    }

    public partial class MainWindow
    {
        public const string PlayerPipeName = "Funkenwerk_PlayerPipe_v1";
        private const string FavSyncPlaylistName = "__FUNKENWERK_FAV_SYNC__";
        private const string LocalPcPlaylistId = "local_pc_music";
        private const string LocalOrphanPlaylistId = "local_cached_orphans";

        private static readonly HttpClient _musicHttp = new() { Timeout = TimeSpan.FromSeconds(14) };
        private static readonly HashSet<string> _allowedMusicApiHosts = new(StringComparer.OrdinalIgnoreCase)
        {
            "127.0.0.1",
            "localhost",
            "::1",
            "[::1]",
            "seelenfunke.test",
            "stage.mein-seelenfunke.de",
            "mein-seelenfunke.de"
        };

        private string _playerMode = "media"; // "playlists" or "media"
        private List<MusicPlaylistDto> _manufakturPlaylists = new();
        private readonly List<MusicTrackDto> _allManufakturTracks = new();
        private readonly List<MusicTrackDto> _currentManufakturQueue = new();
        private string _activeManufakturPlaylistId = "all";
        private string _manufakturSearchQuery = "";
        private MusicTrackDto? _activeManufakturTrack;
        private string? _customMusicCacheDirOverride;

        private bool _isManufakturPlaybackActive;
        private bool _isFocusModeActive;
        private bool _isShuffleEnabled;
        private long _localFavSyncTimestamp;
        private DateTime _lastFocusMouseUtc = DateTime.UtcNow;
        private DispatcherTimer? _focusIdleTimer;
        private DispatcherTimer? _importPollTimer;
        private CancellationTokenSource? _playerPipeCts;
        private CancellationTokenSource? _preCacheCts;
        private readonly Dictionary<string, string> _localCacheStemMap = new(StringComparer.OrdinalIgnoreCase);
        private HashSet<string> _wslAvailableStems = new(StringComparer.OrdinalIgnoreCase);
        private string? _cachedWslTracksDir;

        public string ActivePlayerMode => _playerMode;
        public bool IsFocusModeActive => _isFocusModeActive;
        public bool IsManufakturPlaybackActive => _isManufakturPlaybackActive;
        public bool IsShuffleEnabled => _isShuffleEnabled;
        public bool IsLoopEnabled => _isLoopEnabled;
        public string ActiveManufakturPlaylistId => _activeManufakturPlaylistId;
        public MusicTrackDto? ActiveManufakturTrack => _activeManufakturTrack;
        public IReadOnlyList<MusicPlaylistDto> ManufakturPlaylists => _manufakturPlaylists;
        public IReadOnlyList<MusicTrackDto> CurrentManufakturQueue => _currentManufakturQueue;

        private static string GetMusicCacheDirectory()
        {
            string dir = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "Funkenwerk", "MusicCache");
            Directory.CreateDirectory(dir);
            return dir;
        }

        private string GetActiveMusicCacheDirectory()
        {
            if (!string.IsNullOrWhiteSpace(_customMusicCacheDirOverride))
            {
                Directory.CreateDirectory(_customMusicCacheDirOverride);
                return _customMusicCacheDirOverride;
            }
            return GetMusicCacheDirectory();
        }

        private static string? _resolvedWslMusicRoot;
        private static DateTime _lastWslProbeUtc = DateTime.MinValue;

        private static string? TryGetLocalWslMusicRoot()
        {
            if (!string.IsNullOrWhiteSpace(_resolvedWslMusicRoot) && Directory.Exists(_resolvedWslMusicRoot))
                return _resolvedWslMusicRoot;

            if ((DateTime.UtcNow - _lastWslProbeUtc).TotalSeconds < 30)
                return null;
            _lastWslProbeUtc = DateTime.UtcNow;

            try
            {
                string repoRel = Path.GetFullPath(Path.Combine(AppContext.BaseDirectory, "..", "..", "..", "..", "storage", "app", "leitung", "music"));
                if (Directory.Exists(repoRel))
                {
                    _resolvedWslMusicRoot = repoRel;
                    return repoRel;
                }

                // Fast non-blocking SMB port-445 probe against local WSL Hyper-V 172.16.0.0/12 ARP neighbors (avoids Windows wsl.localhost P9Rdr hang!)
                var candidateIps = new HashSet<string>(StringComparer.Ordinal);
                try
                {
                    string arpExe = Path.Combine(Environment.SystemDirectory, "arp.exe");
                    if (File.Exists(arpExe))
                    {
                        var psi = new System.Diagnostics.ProcessStartInfo
                        {
                            FileName = arpExe,
                            UseShellExecute = false,
                            RedirectStandardOutput = true,
                            CreateNoWindow = true
                        };
                        psi.ArgumentList.Add("-a");
                        using var proc = System.Diagnostics.Process.Start(psi);
                        if (proc != null)
                        {
                            string arpOut = proc.StandardOutput.ReadToEnd();
                            proc.WaitForExit(1000);
                            foreach (System.Text.RegularExpressions.Match m in System.Text.RegularExpressions.Regex.Matches(arpOut, @"\b(172\.(?:1[6-9]|2[0-9]|3[0-1])\.\d{1,3}\.\d{1,3})\b"))
                            {
                                string ip = m.Groups[1].Value;
                                if (!ip.EndsWith(".1", StringComparison.Ordinal) && !ip.EndsWith(".255", StringComparison.Ordinal))
                                {
                                    candidateIps.Add(ip);
                                }
                            }
                        }
                    }
                }
                catch { }

                var smbReachable = new System.Collections.Concurrent.ConcurrentBag<string>();
                Parallel.ForEach(candidateIps, ip =>
                {
                    try
                    {
                        using var tcp = new System.Net.Sockets.TcpClient();
                        var connectTask = tcp.ConnectAsync(ip, 445);
                        if (connectTask.Wait(220) && tcp.Connected)
                        {
                            smbReachable.Add(ip);
                        }
                    }
                    catch { }
                });

                foreach (string ip in smbReachable)
                {
                    string uncRoot = $@"\\{ip}\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\storage\app\leitung\music";
                    if (Directory.Exists(uncRoot))
                    {
                        _resolvedWslMusicRoot = uncRoot;
                        return uncRoot;
                    }
                }
            }
            catch { }
            return null;
        }

        // =====================================================================
        // AUDIO MAGIC-BYTE VALIDATOR (1:1 Android MusicPlayerManager.isValidAudioFile)
        // =====================================================================
        public static bool IsValidAudioFile(string? filePath, long minBytes = 1024)
        {
            if (string.IsNullOrWhiteSpace(filePath)) return false;
            try
            {
                var fi = new FileInfo(filePath);
                if (!fi.Exists || fi.Length < minBytes) return false;
                if ((fi.Attributes & FileAttributes.ReparsePoint) != 0) return false;

                Span<byte> header = stackalloc byte[64];
                int read;
                using (var fs = new FileStream(filePath, FileMode.Open, FileAccess.Read, FileShare.ReadWrite))
                {
                    read = fs.Read(header);
                }
                if (read < 12) return false;

                string asciiPrefix = Encoding.ASCII.GetString(header.Slice(0, Math.Min(read, 48))).TrimStart();
                if (asciiPrefix.StartsWith("<!doctype", StringComparison.OrdinalIgnoreCase) ||
                    asciiPrefix.StartsWith("<html", StringComparison.OrdinalIgnoreCase) ||
                    asciiPrefix.StartsWith("<?xml", StringComparison.OrdinalIgnoreCase) ||
                    asciiPrefix.StartsWith("{\"error", StringComparison.OrdinalIgnoreCase) ||
                    asciiPrefix.StartsWith("{\"message", StringComparison.OrdinalIgnoreCase) ||
                    asciiPrefix.StartsWith("{\"success\":false", StringComparison.OrdinalIgnoreCase))
                {
                    return false;
                }

                // ISO BMFF / MP4 / M4A (ftyp, moov, styp, mdat at offset 4)
                string boxType = Encoding.ASCII.GetString(header.Slice(4, 4));
                if (boxType is "ftyp" or "moov" or "styp" or "mdat" or "free" or "skip")
                    return true;

                // RIFF WAVE
                if (header[0] == 'R' && header[1] == 'I' && header[2] == 'F' && header[3] == 'F' &&
                    header[8] == 'W' && header[9] == 'A' && header[10] == 'V' && header[11] == 'E')
                    return true;

                // ID3v2 MP3 or raw MPEG ADTS/MP3 sync frame
                if (header[0] == 'I' && header[1] == 'D' && header[2] == '3')
                    return true;
                if (header[0] == 0xFF && (header[1] & 0xE0) == 0xE0)
                    return true;

                // FLAC
                if (header[0] == 'f' && header[1] == 'L' && header[2] == 'a' && header[3] == 'C')
                    return true;

                // Ogg / Opus
                if (header[0] == 'O' && header[1] == 'g' && header[2] == 'g' && header[3] == 'S')
                    return true;

                // Matroska / WebM
                if (header[0] == 0x1A && header[1] == 0x45 && header[2] == 0xDF && header[3] == 0xA3)
                    return true;

                return false;
            }
            catch
            {
                return false;
            }
        }

        // =====================================================================
        // MULTI-SOURCE AUTHENTICATED ENDPOINT RESOLVER (SharedSession + Vault)
        // =====================================================================
        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private struct VaultDataBlob
        {
            public int cbData;
            public IntPtr pbData;
        }

        [DllImport("crypt32.dll", SetLastError = true, CharSet = CharSet.Auto)]
        private static extern bool CryptUnprotectData(
            ref VaultDataBlob pCipherText,
            IntPtr ppszDescription,
            ref VaultDataBlob pEntropy,
            IntPtr pReserved,
            IntPtr pPrompt,
            int dwFlags,
            out VaultDataBlob pPlainText);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern IntPtr LocalFree(IntPtr hMem);

        private static string UnprotectLayoutVaultToken(string? storedText)
        {
            if (string.IsNullOrWhiteSpace(storedText)) return "";
            const string aesPrefix = "FWAES256GCM1:";
            const string v2Prefix = "FWVAULT2:";
            const string dpapiPrefix = "DPAPI:";

            string? matchedPrefix = null;
            if (storedText.StartsWith(aesPrefix, StringComparison.Ordinal)) matchedPrefix = aesPrefix;
            else if (storedText.StartsWith(v2Prefix, StringComparison.Ordinal)) matchedPrefix = v2Prefix;

            byte[] entropyBytes = Encoding.UTF8.GetBytes("Funkenwerk_Seelenfunke_HardwareVault_v1");

            if (matchedPrefix != null)
            {
                byte[]? key = null;
                byte[]? plainBytes = null;
                try
                {
                    byte[] dpapiCipher = Convert.FromBase64String(storedText.Substring(matchedPrefix.Length));
                    byte[]? envelope = TryDpapiUnprotectRaw(dpapiCipher, entropyBytes);
                    if (envelope == null || envelope.Length < 45 || envelope[0] != 0x02) return "";

                    byte[] salt = new byte[16];
                    byte[] nonce = new byte[12];
                    byte[] tag = new byte[16];
                    int cipherLen = envelope.Length - 45;
                    byte[] cipher = new byte[cipherLen];
                    plainBytes = new byte[cipherLen];

                    Buffer.BlockCopy(envelope, 1, salt, 0, 16);
                    Buffer.BlockCopy(envelope, 17, nonce, 0, 12);
                    Buffer.BlockCopy(envelope, 29, tag, 0, 16);
                    Buffer.BlockCopy(envelope, 45, cipher, 0, cipherLen);

                    byte[] idBytes = Encoding.UTF8.GetBytes($"{Environment.MachineName}|{Environment.UserName}|Funkenwerk_AES256GCM_KeyDerivation_v2");
                    try
                    {
                        key = Rfc2898DeriveBytes.Pbkdf2(idBytes, salt, 100_000, HashAlgorithmName.SHA512, 32);
                    }
                    finally
                    {
                        CryptographicOperations.ZeroMemory(idBytes);
                    }

                    byte[] aad = Encoding.UTF8.GetBytes("Funkenwerk-Vault-v2-AEAD-AES256GCM");
                    using var aes = new AesGcm(key, 16);
                    aes.Decrypt(nonce, cipher, tag, plainBytes, aad);
                    return Encoding.UTF8.GetString(plainBytes).Trim();
                }
                catch
                {
                    return "";
                }
                finally
                {
                    if (key != null) CryptographicOperations.ZeroMemory(key);
                    if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
                }
            }

            if (storedText.StartsWith(dpapiPrefix, StringComparison.Ordinal))
            {
                try
                {
                    byte[] cipher = Convert.FromBase64String(storedText.Substring(dpapiPrefix.Length));
                    byte[]? plain = TryDpapiUnprotectRaw(cipher, entropyBytes);
                    return plain != null ? Encoding.UTF8.GetString(plain).Trim() : "";
                }
                catch { return ""; }
            }

            return storedText.Trim();
        }

        private static byte[]? TryDpapiUnprotectRaw(byte[] cipherBytes, byte[] entropyBytes)
        {
            IntPtr cipherPtr = IntPtr.Zero;
            IntPtr entropyPtr = IntPtr.Zero;
            VaultDataBlob outBlob = default;
            try
            {
                cipherPtr = Marshal.AllocHGlobal(cipherBytes.Length);
                Marshal.Copy(cipherBytes, 0, cipherPtr, cipherBytes.Length);
                entropyPtr = Marshal.AllocHGlobal(entropyBytes.Length);
                Marshal.Copy(entropyBytes, 0, entropyPtr, entropyBytes.Length);

                var inBlob = new VaultDataBlob { cbData = cipherBytes.Length, pbData = cipherPtr };
                var entBlob = new VaultDataBlob { cbData = entropyBytes.Length, pbData = entropyPtr };

                if (CryptUnprotectData(ref inBlob, IntPtr.Zero, ref entBlob, IntPtr.Zero, IntPtr.Zero, 0x1, out outBlob) &&
                    outBlob.pbData != IntPtr.Zero && outBlob.cbData >= 0)
                {
                    byte[] plain = new byte[outBlob.cbData];
                    if (outBlob.cbData > 0)
                        Marshal.Copy(outBlob.pbData, plain, 0, outBlob.cbData);
                    return plain;
                }
            }
            catch { }
            finally
            {
                if (cipherPtr != IntPtr.Zero) Marshal.FreeHGlobal(cipherPtr);
                if (entropyPtr != IntPtr.Zero) Marshal.FreeHGlobal(entropyPtr);
                if (outBlob.pbData != IntPtr.Zero) LocalFree(outBlob.pbData);
            }
            return null;
        }

        private static bool IsTrustedMusicApiBaseUrl(string? rawBaseUrl, out string normalizedBaseUrl)
        {
            normalizedBaseUrl = "";
            if (string.IsNullOrWhiteSpace(rawBaseUrl)) return false;
            if (!Uri.TryCreate(rawBaseUrl.Trim(), UriKind.Absolute, out var uri)) return false;
            if (!_allowedMusicApiHosts.Contains(uri.Host)) return false;

            bool isLoopback = uri.IsLoopback || uri.Host.Equals("seelenfunke.test", StringComparison.OrdinalIgnoreCase);
            if (!isLoopback && !uri.Scheme.Equals(Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase))
                return false;
            if (isLoopback && !uri.Scheme.Equals(Uri.UriSchemeHttp, StringComparison.OrdinalIgnoreCase) &&
                !uri.Scheme.Equals(Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase))
                return false;

            normalizedBaseUrl = uri.ToString().TrimEnd('/');
            if (!normalizedBaseUrl.EndsWith("/api", StringComparison.OrdinalIgnoreCase))
                normalizedBaseUrl += "/api";
            return true;
        }

        private List<(string BaseUrl, string Token)> GetCandidateEndpointsAndTokens()
        {
            var results = new List<(string BaseUrl, string Token)>();
            if (!string.IsNullOrWhiteSpace(_customMusicCacheDirOverride))
                return results;

            void AddCandidate(string? rawUrl, string? rawToken)
            {
                if (!IsTrustedMusicApiBaseUrl(rawUrl, out string cleanUrl)) return;
                string tok = (rawToken ?? "").Trim();
                int existingIdx = results.FindIndex(x => x.BaseUrl.Equals(cleanUrl, StringComparison.OrdinalIgnoreCase));
                if (existingIdx >= 0)
                {
                    if (string.IsNullOrWhiteSpace(results[existingIdx].Token) && !string.IsNullOrWhiteSpace(tok))
                        results[existingIdx] = (cleanUrl, tok);
                    return;
                }
                results.Add((cleanUrl, tok));
            }

            try
            {
                var session = FunkenwerkSharedSession.Load();
                if (!session.IsSessionLocked)
                {
                    AddCandidate(session.ApiBaseUrl, session.BearerToken);
                }
            }
            catch { }

            try
            {
                string appData = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Funkenwerk");
                foreach (string fileName in new[] { "layout_settings.json", "settings.json" })
                {
                    string cfgPath = Path.Combine(appData, fileName);
                    if (!File.Exists(cfgPath)) continue;
                    using var doc = JsonDocument.Parse(File.ReadAllText(cfgPath, Encoding.UTF8));
                    var root = doc.RootElement;
                    if (root.TryGetProperty("IsSessionLocked", out var lockedProp) && lockedProp.ValueKind == JsonValueKind.True)
                        continue;

                    string tokLocal = root.TryGetProperty("ApiTokenLocal", out var tl) && tl.ValueKind == JsonValueKind.String
                        ? UnprotectLayoutVaultToken(tl.GetString()) : "";
                    string tokStage = root.TryGetProperty("ApiTokenStage", out var ts) && ts.ValueKind == JsonValueKind.String
                        ? UnprotectLayoutVaultToken(ts.GetString()) : "";
                    string tokLive = root.TryGetProperty("ApiTokenLive", out var tv) && tv.ValueKind == JsonValueKind.String
                        ? UnprotectLayoutVaultToken(tv.GetString()) : "";
                    string activeUrl = root.TryGetProperty("ApiBaseUrl", out var au) && au.ValueKind == JsonValueKind.String
                        ? (au.GetString() ?? "") : "";

                    if (!string.IsNullOrWhiteSpace(activeUrl))
                    {
                        string activeTok = activeUrl.Contains("stage.", StringComparison.OrdinalIgnoreCase) ? tokStage
                            : (activeUrl.Contains("mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase) ? tokLive : tokLocal);
                        AddCandidate(activeUrl, activeTok);
                    }
                    AddCandidate("http://127.0.0.1/api", tokLocal);
                    AddCandidate("https://stage.mein-seelenfunke.de/api", tokStage);
                    AddCandidate("https://mein-seelenfunke.de/api", tokLive);
                }
            }
            catch { }

            AddCandidate("http://127.0.0.1/api", "");
            return results;
        }

        // =====================================================================
        // INITIALIZATION, CATALOG DISCOVERY & PC MUSIC SCANNER
        // =====================================================================
        private async Task InitializeManufakturEngineAsync(bool openPlaylistsViewByDefault)
        {
            StartPlayerIpcPipeServer();
            LoadManufakturPlaylistsFromDisk();
            if (openPlaylistsViewByDefault)
            {
                SwitchPlayerMode("playlists");
            }
            else
            {
                UpdateModeButtonsUI();
            }

            _ = RefreshWslMusicIndexInBackgroundAsync();
            await SyncManufakturPlaylistsOnlineAsync();
        }

        private void RefreshLocalCacheStemIndex()
        {
            _localCacheStemMap.Clear();
            try
            {
                string dir = GetActiveMusicCacheDirectory();
                if (!Directory.Exists(dir)) return;
                var allowedExts = new HashSet<string>(StringComparer.OrdinalIgnoreCase) { ".m4a", ".wav", ".mp3", ".flac", ".ogg" };
                foreach (var file in Directory.EnumerateFiles(dir))
                {
                    string ext = Path.GetExtension(file);
                    if (!allowedExts.Contains(ext)) continue;
                    string stem = Path.GetFileNameWithoutExtension(file);
                    if (SafeCacheIdRegex.IsMatch(stem))
                    {
                        _localCacheStemMap[stem] = file;
                    }
                }
            }
            catch { }
        }

        private async Task RefreshWslMusicIndexInBackgroundAsync()
        {
            if (_customMusicCacheDirOverride != null) return;
            try
            {
                var (wslTracksDir, stems, wslCatalog) = await Task.Run(() =>
                {
                    string? root = TryGetLocalWslMusicRoot();
                    if (string.IsNullOrWhiteSpace(root))
                        return ((string?)null, new HashSet<string>(StringComparer.OrdinalIgnoreCase), (List<MusicPlaylistDto>?)null);

                    var foundStems = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
                    string tDir = Path.Combine(root, "tracks");
                    if (Directory.Exists(tDir))
                    {
                        foreach (var f in Directory.EnumerateFiles(tDir, "*.m4a"))
                        {
                            string s = Path.GetFileNameWithoutExtension(f);
                            if (SafeCacheIdRegex.IsMatch(s)) foundStems.Add(s);
                        }
                    }

                    List<MusicPlaylistDto>? cat = null;
                    string catFile = Path.Combine(root, "playlists_catalog.json");
                    if (File.Exists(catFile))
                    {
                        cat = ParsePlaylistsPayload(File.ReadAllText(catFile, Encoding.UTF8));
                    }
                    return ((string?)tDir, foundStems, cat);
                });

                await Dispatcher.InvokeAsync(() =>
                {
                    if (_customMusicCacheDirOverride != null) return;
                    _cachedWslTracksDir = wslTracksDir;
                    _wslAvailableStems = stems;

                    if (wslCatalog != null)
                    {
                        int wslTrackCount = wslCatalog
                            .Where(p => !p.Name.Equals(FavSyncPlaylistName, StringComparison.OrdinalIgnoreCase))
                            .Sum(p => p.Tracks?.Count ?? 0);
                        if (wslTrackCount > _allManufakturTracks.Count(t => t.PlaylistId != LocalPcPlaylistId && t.PlaylistId != LocalOrphanPlaylistId))
                        {
                            string cacheDir = GetMusicCacheDirectory();
                            ApplyManufakturPlaylists(wslCatalog, cacheDir, includeLocalPcScan: true);
                            try
                            {
                                string outJson = JsonSerializer.Serialize(new { success = true, data = wslCatalog });
                                File.WriteAllText(Path.Combine(cacheDir, "playlists_cache.json"), outJson, Encoding.UTF8);
                            }
                            catch { }
                            return;
                        }
                    }

                    UpdateManufakturCacheLabel();
                    if (_playerMode == "playlists") RenderManufakturLibrary();
                });
            }
            catch { }
        }

        private void ShutdownManufakturEngine()
        {
            try { _playerPipeCts?.Cancel(); } catch { }
            try { _preCacheCts?.Cancel(); } catch { }
            try { _importPollTimer?.Stop(); } catch { }
            try { _focusIdleTimer?.Stop(); } catch { }
            try { CelestialFocusHost?.StopRendering(); } catch { }
        }

        public void SwitchPlayerMode(string mode)
        {
            _playerMode = mode == "playlists" ? "playlists" : "media";
            if (ManufakturPlaylistsViewGrid != null)
            {
                ManufakturPlaylistsViewGrid.Visibility = _playerMode == "playlists"
                    ? Visibility.Visible
                    : Visibility.Collapsed;
            }
            UpdateModeButtonsUI();
            if (_playerMode == "playlists")
            {
                SyncManufakturNowPlayingDeck();
                RenderManufakturLibrary();
            }
        }

        private void UpdateModeButtonsUI()
        {
            if (ModePlaylistsBtn == null || ModeMediaBtn == null) return;
            bool isPl = _playerMode == "playlists";

            ModePlaylistsBtn.Background = BrushFromHex(isPl ? "#26231F" : "#181B24");
            ModePlaylistsBtn.Foreground = BrushFromHex(isPl ? "#C5A059" : "#F9FAFB");
            ModePlaylistsBtn.BorderBrush = BrushFromHex(isPl ? "#C5A059" : "#262A36");
            ModePlaylistsBtn.FontWeight = isPl ? FontWeights.Bold : FontWeights.SemiBold;

            ModeMediaBtn.Background = BrushFromHex(!isPl ? "#26231F" : "#181B24");
            ModeMediaBtn.Foreground = BrushFromHex(!isPl ? "#C5A059" : "#F9FAFB");
            ModeMediaBtn.BorderBrush = BrushFromHex(!isPl ? "#C5A059" : "#262A36");
            ModeMediaBtn.FontWeight = !isPl ? FontWeights.Bold : FontWeights.SemiBold;
        }

        private void ModePlaylists_Click(object sender, RoutedEventArgs e) => SwitchPlayerMode("playlists");
        private void ModeMedia_Click(object sender, RoutedEventArgs e) => SwitchPlayerMode("media");

        public static List<MusicPlaylistDto> ParsePlaylistsPayload(string json)
        {
            try
            {
                using var doc = JsonDocument.Parse(json);
                JsonElement arr = doc.RootElement;
                if (arr.ValueKind == JsonValueKind.Object && arr.TryGetProperty("data", out var dataProp))
                {
                    arr = dataProp;
                }
                if (arr.ValueKind == JsonValueKind.Array)
                {
                    return JsonSerializer.Deserialize<List<MusicPlaylistDto>>(
                        arr.GetRawText(),
                        new JsonSerializerOptions { PropertyNameCaseInsensitive = true }) ?? new();
                }
            }
            catch { }
            return new();
        }

        public void LoadManufakturPlaylistsFromDisk(string? customCacheDir = null)
        {
            _customMusicCacheDirOverride = customCacheDir;
            if (customCacheDir != null)
            {
                _wslAvailableStems = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
                _cachedWslTracksDir = null;
            }
            RefreshLocalCacheStemIndex();

            try
            {
                string cacheDir = GetActiveMusicCacheDirectory();
                string cacheFile = Path.Combine(cacheDir, "playlists_cache.json");

                List<MusicPlaylistDto> best = new();
                int bestTrackCount = -1;

                void ConsiderCatalogFile(string? candidatePath)
                {
                    if (string.IsNullOrWhiteSpace(candidatePath) || !File.Exists(candidatePath)) return;
                    try
                    {
                        var parsed = ParsePlaylistsPayload(File.ReadAllText(candidatePath, Encoding.UTF8));
                        int cnt = parsed
                            .Where(p => !p.Name.Equals(FavSyncPlaylistName, StringComparison.OrdinalIgnoreCase))
                            .Sum(p => p.Tracks?.Count ?? 0);
                        if (cnt > bestTrackCount || (best.Count == 0 && parsed.Count > 0))
                        {
                            best = parsed;
                            bestTrackCount = cnt;
                        }
                    }
                    catch { }
                }

                ConsiderCatalogFile(cacheFile);

                if (customCacheDir == null)
                {
                    string bundledFile = Path.Combine(AppContext.BaseDirectory, "playlists_cache.json");
                    ConsiderCatalogFile(bundledFile);

                    if (best.Count > 0 && !File.Exists(cacheFile))
                    {
                        try
                        {
                            string outJson = JsonSerializer.Serialize(new { success = true, data = best });
                            File.WriteAllText(cacheFile, outJson, Encoding.UTF8);
                        }
                        catch { }
                    }
                }

                ApplyManufakturPlaylists(best, cacheDir, includeLocalPcScan: customCacheDir == null);
            }
            catch { }
        }

        private async Task SyncManufakturPlaylistsOnlineAsync()
        {
            if (!string.IsNullOrWhiteSpace(_customMusicCacheDirOverride)) return;

            foreach (var (baseUrl, token) in GetCandidateEndpointsAndTokens())
            {
                try
                {
                    using var req = new HttpRequestMessage(HttpMethod.Get, $"{baseUrl}/funki/music/playlists");
                    req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                    if (!string.IsNullOrWhiteSpace(token))
                    {
                        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);
                    }

                    using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(4));
                    using var resp = await _musicHttp.SendAsync(req, cts.Token);
                    if (!resp.IsSuccessStatusCode) continue;

                    string body = await resp.Content.ReadAsStringAsync(cts.Token);
                    var remote = ParsePlaylistsPayload(body);
                    int remoteTracks = remote
                        .Where(p => !p.Name.Equals(FavSyncPlaylistName, StringComparison.OrdinalIgnoreCase))
                        .Sum(p => p.Tracks?.Count ?? 0);

                    if (remoteTracks > 0 || (_manufakturPlaylists.Count == 0 && remote.Count > 0))
                    {
                        string cacheDir = GetMusicCacheDirectory();
                        ApplyManufakturPlaylists(remote, cacheDir, includeLocalPcScan: true);
                        string outJson = JsonSerializer.Serialize(new { success = true, data = remote });
                        File.WriteAllText(Path.Combine(cacheDir, "playlists_cache.json"), outJson, Encoding.UTF8);
                        return;
                    }
                }
                catch { }
            }
        }

        private void ApplyManufakturPlaylists(List<MusicPlaylistDto> playlists, string cacheDir, bool includeLocalPcScan)
        {
            var localFavIds = LoadLocalFavoriteIds(cacheDir, out long savedFavTs);
            _localFavSyncTimestamp = savedFavTs;

            // Reconcile hidden __FUNKENWERK_FAV_SYNC__ playlist (1:1 Android fav_sync:v1:<ts>:<id8prefixes>)
            var favSyncPl = playlists.FirstOrDefault(p => string.Equals(p.Name, FavSyncPlaylistName, StringComparison.OrdinalIgnoreCase));
            HashSet<string>? remoteFavPrefixes = null;
            long remoteFavTs = 0;
            if (favSyncPl != null && !string.IsNullOrWhiteSpace(favSyncPl.Description) &&
                favSyncPl.Description.StartsWith("fav_sync:v1:", StringComparison.Ordinal))
            {
                var parts = favSyncPl.Description.Split(':');
                if (parts.Length >= 4 && long.TryParse(parts[2], out remoteFavTs))
                {
                    remoteFavPrefixes = new HashSet<string>(
                        parts[3].Split(',', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries),
                        StringComparer.OrdinalIgnoreCase);
                }
            }

            _manufakturPlaylists = playlists
                .Where(p => !string.Equals(p.Name, FavSyncPlaylistName, StringComparison.OrdinalIgnoreCase) && !p.IsLocalFolder)
                .OrderBy(p => p.Position)
                .ToList();

            _allManufakturTracks.Clear();
            var knownFileNames = new HashSet<string>(StringComparer.OrdinalIgnoreCase);

            foreach (var pl in _manufakturPlaylists)
            {
                if (pl.Tracks == null) continue;
                foreach (var tr in pl.Tracks)
                {
                    if (string.IsNullOrWhiteSpace(tr.PlaylistId)) tr.PlaylistId = pl.Id;
                    if (!string.IsNullOrWhiteSpace(tr.Id)) knownFileNames.Add(tr.Id);
                    if (!string.IsNullOrWhiteSpace(tr.ExternalId)) knownFileNames.Add(tr.ExternalId);

                    if (remoteFavPrefixes != null && remoteFavTs > _localFavSyncTimestamp)
                    {
                        string prefix = tr.Id.Length >= 8 ? tr.Id.Substring(0, 8) : tr.Id;
                        tr.IsFavorite = remoteFavPrefixes.Contains(prefix) || localFavIds.Contains(tr.Id);
                    }
                    else if (localFavIds.Contains(tr.Id) || (!string.IsNullOrWhiteSpace(tr.ExternalId) && localFavIds.Contains(tr.ExternalId)))
                    {
                        tr.IsFavorite = true;
                    }

                    _allManufakturTracks.Add(tr);
                }
            }

            // Recover orphan cached .m4a files in MusicCache so cached music is never lost offline
            try
            {
                if (Directory.Exists(cacheDir))
                {
                    var orphans = new List<MusicTrackDto>();
                    int pos = 1;
                    foreach (var m4a in Directory.GetFiles(cacheDir, "*.m4a"))
                    {
                        string stem = Path.GetFileNameWithoutExtension(m4a);
                        if (knownFileNames.Contains(stem)) continue;
                        if (!SafeCacheIdRegex.IsMatch(stem) || !IsValidAudioFile(m4a)) continue;

                        var dto = new MusicTrackDto
                        {
                            Id = stem,
                            PlaylistId = LocalOrphanPlaylistId,
                            Title = stem,
                            Artist = "Offline gespeichert",
                            Position = pos++,
                            IsFavorite = localFavIds.Contains(stem),
                            LocalFilePath = m4a
                        };
                        orphans.Add(dto);
                        _allManufakturTracks.Add(dto);
                    }
                    if (orphans.Count > 0)
                    {
                        _manufakturPlaylists.Add(new MusicPlaylistDto
                        {
                            Id = LocalOrphanPlaylistId,
                            Name = "Offline gespeichert",
                            Position = 998,
                            IsLocalFolder = true,
                            Tracks = orphans
                        });
                    }
                }
            }
            catch { }

            // Scan Windows "MyMusic" folder as "Auf diesem PC" (1:1 Android "Auf diesem Gerät")
            if (includeLocalPcScan)
            {
                try
                {
                    string myMusic = Environment.GetFolderPath(Environment.SpecialFolder.MyMusic);
                    if (!string.IsNullOrWhiteSpace(myMusic) && Directory.Exists(myMusic))
                    {
                        var audioExts = new HashSet<string>(StringComparer.OrdinalIgnoreCase) { ".mp3", ".m4a", ".wav", ".flac", ".ogg" };
                        var pcTracks = new List<MusicTrackDto>();
                        int pcPos = 1;
                        foreach (var file in Directory.EnumerateFiles(myMusic, "*.*", SearchOption.TopDirectoryOnly).Take(250))
                        {
                            if (!audioExts.Contains(Path.GetExtension(file))) continue;
                            if (!IsValidAudioFile(file)) continue;

                            string stem = Path.GetFileNameWithoutExtension(file);
                            string artist = "Auf diesem PC";
                            string title = stem;
                            int dashIdx = stem.IndexOf(" - ", StringComparison.Ordinal);
                            if (dashIdx > 0 && dashIdx + 3 < stem.Length)
                            {
                                artist = stem.Substring(0, dashIdx).Trim();
                                title = stem.Substring(dashIdx + 3).Trim();
                            }

                            string syntheticId = "pc_" + Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(file))).Substring(0, 16).ToLowerInvariant();
                            var pcDto = new MusicTrackDto
                            {
                                Id = syntheticId,
                                PlaylistId = LocalPcPlaylistId,
                                Title = title,
                                Artist = artist,
                                Position = pcPos++,
                                IsFavorite = localFavIds.Contains(syntheticId),
                                LocalFilePath = file
                            };
                            pcTracks.Add(pcDto);
                            _allManufakturTracks.Add(pcDto);
                        }

                        if (pcTracks.Count > 0)
                        {
                            _manufakturPlaylists.Add(new MusicPlaylistDto
                            {
                                Id = LocalPcPlaylistId,
                                Name = "Auf diesem PC",
                                Position = 999,
                                IsLocalFolder = true,
                                Tracks = pcTracks
                            });
                        }
                    }
                }
                catch { }
            }

            bool currentExists = _activeManufakturPlaylistId == "all" ||
                                 _activeManufakturPlaylistId == "favorites" ||
                                 _manufakturPlaylists.Any(p => p.Id == _activeManufakturPlaylistId);

            if (!currentExists || (_activeManufakturPlaylistId == "all" && _manufakturPlaylists.Count > 0 && _activeManufakturTrack == null))
            {
                var favPl = _manufakturPlaylists.FirstOrDefault(p => p.Name.Contains("Lieblingssongs", StringComparison.OrdinalIgnoreCase))
                            ?? _manufakturPlaylists.FirstOrDefault();
                _activeManufakturPlaylistId = favPl?.Id ?? "all";
            }

            SyncManufakturNowPlayingDeck();
            RenderManufakturLibrary();
            UpdateManufakturCacheLabel();
        }

        private static HashSet<string> LoadLocalFavoriteIds(string cacheDir, out long updatedUnixTs)
        {
            updatedUnixTs = 0;
            try
            {
                string f = Path.Combine(cacheDir, "favorites_local.json");
                if (File.Exists(f))
                {
                    string raw = File.ReadAllText(f, Encoding.UTF8);
                    using var doc = JsonDocument.Parse(raw);
                    if (doc.RootElement.ValueKind == JsonValueKind.Array)
                    {
                        var arr = JsonSerializer.Deserialize<List<string>>(raw);
                        if (arr != null) return new HashSet<string>(arr, StringComparer.OrdinalIgnoreCase);
                    }
                    else if (doc.RootElement.ValueKind == JsonValueKind.Object)
                    {
                        if (doc.RootElement.TryGetProperty("ts", out var tsP) && tsP.TryGetInt64(out long ts))
                            updatedUnixTs = ts;
                        if (doc.RootElement.TryGetProperty("ids", out var idsP) && idsP.ValueKind == JsonValueKind.Array)
                        {
                            var arr = JsonSerializer.Deserialize<List<string>>(idsP.GetRawText());
                            if (arr != null) return new HashSet<string>(arr, StringComparer.OrdinalIgnoreCase);
                        }
                    }
                }
            }
            catch { }
            return new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        }

        private void SaveLocalFavoriteIds()
        {
            try
            {
                _localFavSyncTimestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds();
                string f = Path.Combine(GetActiveMusicCacheDirectory(), "favorites_local.json");
                var ids = _allManufakturTracks
                    .Where(t => t.IsFavorite)
                    .Select(t => t.Id)
                    .Distinct(StringComparer.OrdinalIgnoreCase)
                    .ToList();
                string payload = JsonSerializer.Serialize(new { ts = _localFavSyncTimestamp, ids });
                File.WriteAllText(f, payload, Encoding.UTF8);
            }
            catch { }
        }

        private List<MusicTrackDto> GetTracksForPlaylistId(string playlistId, string? searchOverride = null)
        {
            IEnumerable<MusicTrackDto> list = _allManufakturTracks;
            if (playlistId == "favorites")
                list = list.Where(t => t.IsFavorite);
            else if (playlistId != "all")
                list = list.Where(t => t.PlaylistId == playlistId);

            string q = (searchOverride ?? _manufakturSearchQuery).Trim();
            if (!string.IsNullOrWhiteSpace(q))
            {
                list = list.Where(t =>
                    t.Title.Contains(q, StringComparison.OrdinalIgnoreCase) ||
                    (!string.IsNullOrWhiteSpace(t.Artist) && t.Artist.Contains(q, StringComparison.OrdinalIgnoreCase)));
            }
            return list.OrderBy(t => t.Position).ToList();
        }

        private List<MusicTrackDto> GetFilteredManufakturTracks()
            => GetTracksForPlaylistId(_activeManufakturPlaylistId);

        // =====================================================================
        // 3-TIER TRACK RESOLUTION & PRE-CACHE (MusicCache -> WSL Storage -> API)
        // =====================================================================
        private string? ResolveCachedTrackFile(MusicTrackDto track)
        {
            if (!string.IsNullOrWhiteSpace(track.LocalFilePath) && IsValidAudioFile(track.LocalFilePath))
            {
                return track.LocalFilePath;
            }

            string dir = GetActiveMusicCacheDirectory();
            foreach (string ext in new[] { ".m4a", ".wav", ".mp3", ".flac", ".ogg" })
            {
                string? byId = TryResolveSafeAudioFileInDir(dir, track.Id, ext);
                if (byId != null && IsValidAudioFile(byId)) return byId;

                string? byExtId = TryResolveSafeAudioFileInDir(dir, track.ExternalId, ext);
                if (byExtId != null && IsValidAudioFile(byExtId)) return byExtId;
            }

            return null;
        }

        private string? TryFindLocalWslSourceTrack(MusicTrackDto track)
        {
            if (_customMusicCacheDirOverride != null) return null;
            string? tracksDir = _cachedWslTracksDir;
            if (string.IsNullOrWhiteSpace(tracksDir))
            {
                string? wslRoot = TryGetLocalWslMusicRoot();
                if (string.IsNullOrWhiteSpace(wslRoot)) return null;
                tracksDir = Path.Combine(wslRoot, "tracks");
                _cachedWslTracksDir = tracksDir;
            }
            if (!Directory.Exists(tracksDir)) return null;

            string? p1 = TryResolveSafeAudioFileInDir(tracksDir, track.ExternalId, ".m4a");
            if (p1 != null && IsValidAudioFile(p1)) return p1;

            string? p2 = TryResolveSafeAudioFileInDir(tracksDir, track.Id, ".m4a");
            if (p2 != null && IsValidAudioFile(p2)) return p2;

            return null;
        }

        private bool IsTrackAvailableLocallyOrOnWsl(MusicTrackDto track, out string? directCachePath)
        {
            directCachePath = null;
            if (!string.IsNullOrWhiteSpace(track.LocalFilePath))
            {
                directCachePath = track.LocalFilePath;
                return true;
            }

            if (!string.IsNullOrWhiteSpace(track.Id) && _localCacheStemMap.TryGetValue(track.Id, out string? byId))
            {
                directCachePath = byId;
                return true;
            }
            if (!string.IsNullOrWhiteSpace(track.ExternalId) && _localCacheStemMap.TryGetValue(track.ExternalId, out string? byExt))
            {
                directCachePath = byExt;
                return true;
            }

            if (_customMusicCacheDirOverride == null)
            {
                if ((!string.IsNullOrWhiteSpace(track.ExternalId) && _wslAvailableStems.Contains(track.ExternalId)) ||
                    (!string.IsNullOrWhiteSpace(track.Id) && _wslAvailableStems.Contains(track.Id)))
                {
                    return true;
                }
            }
            return false;
        }

        private static string? TryResolveSafeAudioFileInDir(string baseDir, string? rawId, string extensionWithDot)
        {
            if (string.IsNullOrWhiteSpace(baseDir) || string.IsNullOrWhiteSpace(rawId)) return null;
            if (!SafeCacheIdRegex.IsMatch(rawId)) return null;

            try
            {
                string fullBase = Path.GetFullPath(baseDir).TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
                string candidate = Path.GetFullPath(Path.Combine(fullBase, rawId + extensionWithDot));
                if (!candidate.StartsWith(fullBase + Path.DirectorySeparatorChar, StringComparison.OrdinalIgnoreCase))
                    return null;
                if (!File.Exists(candidate))
                    return null;
                if ((File.GetAttributes(candidate) & FileAttributes.ReparsePoint) != 0)
                    return null;
                return candidate;
            }
            catch
            {
                return null;
            }
        }

        private async Task<string?> EnsureManufakturTrackCachedAsync(MusicTrackDto track, CancellationToken ct = default)
        {
            // Tier 1: Already in Windows NTFS MusicCache or Local PC Music
            string? existing = ResolveCachedTrackFile(track);
            if (!string.IsNullOrEmpty(existing))
            {
                return existing;
            }

            string cacheKey = !string.IsNullOrWhiteSpace(track.ExternalId) && SafeCacheIdRegex.IsMatch(track.ExternalId)
                ? track.ExternalId
                : track.Id;
            if (!SafeCacheIdRegex.IsMatch(cacheKey)) return null;

            string cacheDir = GetActiveMusicCacheDirectory();
            string targetPath = Path.Combine(cacheDir, $"{cacheKey}.m4a");

            // Tier 2: Instant atomic copy from local Seelenfunke WSL storage/app/leitung/music/tracks/ (< 10 ms!)
            string? wslSource = TryFindLocalWslSourceTrack(track);
            if (!string.IsNullOrEmpty(wslSource))
            {
                string tmpCopy = targetPath + ".tmp_" + Guid.NewGuid().ToString("N").Substring(0, 6);
                try
                {
                    await Task.Run(() =>
                    {
                        File.Copy(wslSource, tmpCopy, overwrite: true);
                        File.Move(tmpCopy, targetPath, overwrite: true);
                    }, ct);

                    if (IsValidAudioFile(targetPath))
                    {
                        _localCacheStemMap[cacheKey] = targetPath;
                        UpdateManufakturCacheLabel();
                        return targetPath;
                    }
                }
                catch { }
                finally
                {
                    try { if (File.Exists(tmpCopy)) File.Delete(tmpCopy); } catch { }
                }
            }

            // Tier 3: Multi-endpoint authenticated HTTP stream (/funki/music/stream/{id}) with container validation
            if (!SafeCacheIdRegex.IsMatch(track.Id)) return null;
            foreach (var (baseUrl, token) in GetCandidateEndpointsAndTokens())
            {
                string tmpDl = targetPath + ".dl_" + Guid.NewGuid().ToString("N").Substring(0, 6);
                try
                {
                    using var req = new HttpRequestMessage(HttpMethod.Get, $"{baseUrl}/funki/music/stream/{track.Id}");
                    if (!string.IsNullOrWhiteSpace(token))
                    {
                        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);
                    }

                    using var reqCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
                    reqCts.CancelAfter(TimeSpan.FromSeconds(4.5));
                    using var resp = await _musicHttp.SendAsync(req, HttpCompletionOption.ResponseHeadersRead, reqCts.Token);
                    if (!resp.IsSuccessStatusCode) continue;

                    string mediaType = resp.Content.Headers.ContentType?.MediaType ?? "";
                    if (mediaType.Contains("html", StringComparison.OrdinalIgnoreCase) ||
                        mediaType.Contains("json", StringComparison.OrdinalIgnoreCase))
                    {
                        continue;
                    }

                    await using (var netStream = await resp.Content.ReadAsStreamAsync(ct))
                    await using (var fs = new FileStream(tmpDl, FileMode.Create, FileAccess.Write, FileShare.None))
                    {
                        await netStream.CopyToAsync(fs, ct);
                    }

                    if (!IsValidAudioFile(tmpDl, minBytes: 4096))
                    {
                        try { File.Delete(tmpDl); } catch { }
                        continue;
                    }

                    File.Move(tmpDl, targetPath, overwrite: true);
                    _localCacheStemMap[cacheKey] = targetPath;
                    UpdateManufakturCacheLabel();
                    return targetPath;
                }
                catch (OperationCanceledException)
                {
                    try { if (File.Exists(tmpDl)) File.Delete(tmpDl); } catch { }
                    throw;
                }
                catch
                {
                    try { if (File.Exists(tmpDl)) File.Delete(tmpDl); } catch { }
                }
            }

            return null;
        }

        private void StartBackgroundPreCacheNextTracks(MusicTrackDto currentTrack, List<MusicTrackDto> queue, int count = 3)
        {
            if (_customMusicCacheDirOverride != null || queue.Count <= 1) return;

            try { _preCacheCts?.Cancel(); } catch { }
            _preCacheCts = new CancellationTokenSource();
            var ct = _preCacheCts.Token;

            int curIdx = queue.FindIndex(t => t.Id == currentTrack.Id);
            if (curIdx < 0) curIdx = 0;

            var upcoming = new List<MusicTrackDto>();
            for (int offset = 1; offset <= count && offset < queue.Count; offset++)
            {
                upcoming.Add(queue[(curIdx + offset) % queue.Count]);
            }

            _ = Task.Run(async () =>
            {
                foreach (var nextTrack in upcoming)
                {
                    if (ct.IsCancellationRequested) break;
                    try
                    {
                        if (ResolveCachedTrackFile(nextTrack) == null)
                        {
                            await EnsureManufakturTrackCachedAsync(nextTrack, ct);
                        }
                    }
                    catch { }
                }
            }, ct);
        }

        // =====================================================================
        // STUDIO NOW-PLAYING DECK & ANDROID-PARITY PLAYLIST/QUEUE ENGINE
        // =====================================================================
        private void SyncManufakturNowPlayingDeck(string? statusOverride = null, bool isError = false)
        {
            if (ManufakturNowTitleTxt == null || ManufakturNowArtistTxt == null || ManufakturStatusLineTxt == null)
                return;

            if (_activeManufakturTrack != null)
            {
                ManufakturNowTitleTxt.Text = _activeManufakturTrack.Title;
                ManufakturNowArtistTxt.Text = !string.IsNullOrWhiteSpace(_activeManufakturTrack.Artist)
                    ? _activeManufakturTrack.Artist!
                    : "Mein Seelenfunke";

                if (ManufakturCurrentFavBtn != null)
                {
                    ManufakturCurrentFavBtn.Content = _activeManufakturTrack.IsFavorite ? "♥" : "♡";
                    ManufakturCurrentFavBtn.Foreground = BrushFromHex(_activeManufakturTrack.IsFavorite ? "#C5A059" : "#9CA3AF");
                }

                if (!string.IsNullOrWhiteSpace(statusOverride))
                {
                    ManufakturStatusLineTxt.Text = statusOverride;
                    ManufakturStatusLineTxt.Foreground = BrushFromHex(isError ? "#F87171" : "#C5A059");
                }
                else
                {
                    string modeBadge = _isShuffleEnabled ? " · Zufall" : (_isLoopEnabled ? " · Repeat" : "");
                    ManufakturStatusLineTxt.Text = _isPlaying
                        ? $"▶ Spielt jetzt{modeBadge}"
                        : $"❚❚ Pausiert{modeBadge}";
                    ManufakturStatusLineTxt.Foreground = BrushFromHex(_isPlaying ? "#34D399" : "#9CA3AF");
                }
            }
            else
            {
                ManufakturNowTitleTxt.Text = "Kein Song ausgewählt";
                ManufakturNowArtistTxt.Text = _allManufakturTracks.Count > 0
                    ? $"{_allManufakturTracks.Count} Songs bereit"
                    : "Bibliothek wird geladen";
                ManufakturStatusLineTxt.Text = !string.IsNullOrWhiteSpace(statusOverride)
                    ? statusOverride
                    : "⚡ Klicke links eine Playlist oder einen Song";
                ManufakturStatusLineTxt.Foreground = BrushFromHex(isError ? "#F87171" : "#34D399");

                if (ManufakturCurrentFavBtn != null)
                {
                    ManufakturCurrentFavBtn.Content = "♡";
                    ManufakturCurrentFavBtn.Foreground = BrushFromHex("#9CA3AF");
                }
            }

            UpdateDynamicControlBrushes();
        }

        public async Task SelectManufakturPlaylistAsync(string playlistId, bool autoPlayRandom = true)
        {
            _activeManufakturPlaylistId = string.IsNullOrWhiteSpace(playlistId) ? "all" : playlistId;
            if (_playerMode != "playlists")
            {
                SwitchPlayerMode("playlists");
            }

            var tracks = GetFilteredManufakturTracks();
            if (tracks.Count == 0 && !string.IsNullOrWhiteSpace(_manufakturSearchQuery))
            {
                _manufakturSearchQuery = "";
                if (ManufakturSearchBox != null) ManufakturSearchBox.Text = "";
                tracks = GetFilteredManufakturTracks();
            }

            RenderManufakturLibrary();
            SyncManufakturNowPlayingDeck();

            if (!autoPlayRandom) return;

            if (tracks.Count == 0)
            {
                ShowOsdToast("ℹ Keine Songs in dieser Playlist");
                SyncManufakturNowPlayingDeck("Keine Songs in dieser Auswahl", isError: false);
                return;
            }

            // 1:1 Android selectPlaylist: pick a random playable song from the clicked playlist and start immediately!
            var locallyReady = tracks.Where(t => IsTrackAvailableLocallyOrOnWsl(t, out _)).ToList();
            var pool = locallyReady.Count > 0 ? locallyReady : tracks;
            var chosen = pool[_vizRng.Next(pool.Count)];

            await PlayManufakturTrackFromQueueAsync(chosen, tracks, retryDepth: 0);
        }

        public async Task StartOrResumeManufakturPlaybackAsync()
        {
            if (_activeManufakturTrack != null && !string.IsNullOrWhiteSpace(_currentFilePath))
            {
                TogglePlayPause();
                return;
            }

            var tracks = _currentManufakturQueue.Count > 0
                ? _currentManufakturQueue.ToList()
                : GetFilteredManufakturTracks();

            if (tracks.Count == 0) tracks = _allManufakturTracks.ToList();
            if (tracks.Count == 0)
            {
                ShowOsdToast("⚠ Keine Songs in der Musikbibliothek");
                return;
            }

            var pool = tracks.Where(t => IsTrackAvailableLocallyOrOnWsl(t, out _)).ToList();
            if (pool.Count == 0) pool = tracks;
            var startTrack = _isShuffleEnabled ? pool[_vizRng.Next(pool.Count)] : pool[0];

            await PlayManufakturTrackFromQueueAsync(startTrack, tracks, retryDepth: 0);
        }

        public async Task PlayManufakturTrackFromQueueAsync(MusicTrackDto track, List<MusicTrackDto> queue, int retryDepth = 0)
        {
            if (queue == null || queue.Count == 0)
            {
                queue = new List<MusicTrackDto> { track };
            }

            _isManufakturPlaybackActive = true;
            _activeManufakturTrack = track;
            _currentManufakturQueue.Clear();
            _currentManufakturQueue.AddRange(queue);

            SyncManufakturNowPlayingDeck($"⏳ Lade \"{track.Title}\"...");
            RenderManufakturLibrary();

            string? localFile = await EnsureManufakturTrackCachedAsync(track);
            if (string.IsNullOrEmpty(localFile) || !File.Exists(localFile))
            {
                if (retryDepth < 8 && queue.Count > 1)
                {
                    var fallback = FindNextPlayableCandidate(track, queue);
                    if (fallback != null && !string.Equals(fallback.Id, track.Id, StringComparison.OrdinalIgnoreCase))
                    {
                        await PlayManufakturTrackFromQueueAsync(fallback, queue, retryDepth + 1);
                        return;
                    }
                }

                SyncManufakturNowPlayingDeck("⚠ Song konnte weder lokal noch vom Server geladen werden", isError: true);
                ShowOsdToast("⚠ Song aktuell nicht verfügbar");
                return;
            }

            string label = !string.IsNullOrWhiteSpace(track.Artist) ? $"{track.Artist} · {track.Title}" : track.Title;
            _trackDisplayTitles[localFile] = label;

            _playlist.Clear();
            _playlist.Add(localFile);

            RenderPlaylistUI();
            PlayIndex(0);

            SyncManufakturNowPlayingDeck();
            SyncFocusOverlayLabels();
            RenderManufakturLibrary();

            StartBackgroundPreCacheNextTracks(track, _currentManufakturQueue, count: 3);
        }

        private MusicTrackDto? FindNextPlayableCandidate(MusicTrackDto current, List<MusicTrackDto> queue)
        {
            if (queue.Count == 0) return null;
            int idx = queue.FindIndex(t => string.Equals(t.Id, current.Id, StringComparison.OrdinalIgnoreCase));
            if (idx < 0) idx = 0;

            for (int step = 1; step < queue.Count; step++)
            {
                var cand = queue[(idx + step) % queue.Count];
                if (IsTrackAvailableLocallyOrOnWsl(cand, out _))
                    return cand;
            }
            return queue[(idx + 1) % queue.Count];
        }

        public async Task PlayNextManufakturTrackAsync()
        {
            var queue = _currentManufakturQueue.Count > 0
                ? _currentManufakturQueue.ToList()
                : GetFilteredManufakturTracks();

            if (queue.Count == 0) return;

            MusicTrackDto nextTrack;
            if (_activeManufakturTrack == null)
            {
                nextTrack = _isShuffleEnabled ? queue[_vizRng.Next(queue.Count)] : queue[0];
            }
            else if (_isShuffleEnabled && queue.Count > 1)
            {
                int curIdx = queue.FindIndex(t => string.Equals(t.Id, _activeManufakturTrack.Id, StringComparison.OrdinalIgnoreCase));
                int rand = _vizRng.Next(queue.Count - 1);
                if (curIdx >= 0 && rand >= curIdx) rand++;
                nextTrack = queue[rand];
            }
            else
            {
                int curIdx = queue.FindIndex(t => string.Equals(t.Id, _activeManufakturTrack.Id, StringComparison.OrdinalIgnoreCase));
                int nextIdx = curIdx >= 0 ? (curIdx + 1) % queue.Count : 0;
                nextTrack = queue[nextIdx];
            }

            await PlayManufakturTrackFromQueueAsync(nextTrack, queue, retryDepth: 0);
        }

        public async Task PlayPreviousManufakturTrackAsync()
        {
            var queue = _currentManufakturQueue.Count > 0
                ? _currentManufakturQueue.ToList()
                : GetFilteredManufakturTracks();

            if (queue.Count == 0) return;

            int curIdx = _activeManufakturTrack != null
                ? queue.FindIndex(t => string.Equals(t.Id, _activeManufakturTrack.Id, StringComparison.OrdinalIgnoreCase))
                : -1;

            int prevIdx = curIdx >= 0 ? (curIdx - 1 + queue.Count) % queue.Count : queue.Count - 1;
            await PlayManufakturTrackFromQueueAsync(queue[prevIdx], queue, retryDepth: 0);
        }

        public async Task HandleManufakturMediaFailedFallbackAsync()
        {
            if (_activeManufakturTrack == null || _currentManufakturQueue.Count <= 1) return;
            var next = FindNextPlayableCandidate(_activeManufakturTrack, _currentManufakturQueue);
            if (next != null)
            {
                await PlayManufakturTrackFromQueueAsync(next, _currentManufakturQueue.ToList(), retryDepth: 1);
            }
        }

        public void ToggleManufakturShuffle()
        {
            _isShuffleEnabled = !_isShuffleEnabled;
            UpdateDynamicControlBrushes();
            SyncManufakturNowPlayingDeck();
            ShowOsdToast(_isShuffleEnabled ? "🔀 Zufallswiedergabe Aktiv" : "🔀 Reihenfolge normal");
        }

        public void ToggleManufakturFavorite(MusicTrackDto track)
        {
            bool targetState = !track.IsFavorite;
            track.IsFavorite = targetState;

            foreach (var item in _allManufakturTracks)
            {
                if (string.Equals(item.Id, track.Id, StringComparison.OrdinalIgnoreCase) ||
                    (!string.IsNullOrWhiteSpace(track.ExternalId) && string.Equals(item.ExternalId, track.ExternalId, StringComparison.OrdinalIgnoreCase)))
                {
                    item.IsFavorite = targetState;
                }
            }

            SaveLocalFavoriteIds();
            SyncManufakturNowPlayingDeck();
            RenderManufakturLibrary();
            ShowOsdToast(targetState ? $"♥ Favorit: {track.Title}" : $"♡ Entfernt: {track.Title}");

            if (_customMusicCacheDirOverride == null && !track.Id.StartsWith("pc_", StringComparison.OrdinalIgnoreCase))
            {
                _ = SyncFavoriteToBackendAsync(track);
            }
        }

        private async Task SyncFavoriteToBackendAsync(MusicTrackDto track)
        {
            long snapTs = _localFavSyncTimestamp > 0 ? _localFavSyncTimestamp : DateTimeOffset.UtcNow.ToUnixTimeSeconds();
            var favPrefixes = _allManufakturTracks
                .Where(t => t.IsFavorite && !t.Id.StartsWith("pc_", StringComparison.OrdinalIgnoreCase))
                .Select(t => t.Id.Length >= 8 ? t.Id.Substring(0, 8) : t.Id)
                .Distinct(StringComparer.OrdinalIgnoreCase)
                .ToList();
            string syncMarker = $"fav_sync:v1:{snapTs}:{string.Join(",", favPrefixes)}";

            foreach (var (baseUrl, token) in GetCandidateEndpointsAndTokens())
            {
                if (string.IsNullOrWhiteSpace(token)) continue;
                try
                {
                    if (SafeCacheIdRegex.IsMatch(track.Id))
                    {
                        using var favReq = new HttpRequestMessage(HttpMethod.Post, $"{baseUrl}/funki/music/tracks/{track.Id}/favorite");
                        favReq.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);
                        favReq.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                        favReq.Content = new StringContent(JsonSerializer.Serialize(new { is_favorite = track.IsFavorite }), Encoding.UTF8, "application/json");
                        using var favResp = await _musicHttp.SendAsync(favReq);

                        // Also push Android-compatible __FUNKENWERK_FAV_SYNC__ snapshot
                        using var plReq = new HttpRequestMessage(HttpMethod.Post, $"{baseUrl}/funki/music/playlists");
                        plReq.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);
                        plReq.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                        plReq.Content = new StringContent(JsonSerializer.Serialize(new
                        {
                            name = FavSyncPlaylistName,
                            description = syncMarker
                        }), Encoding.UTF8, "application/json");
                        using var plResp = await _musicHttp.SendAsync(plReq);

                        if (favResp.IsSuccessStatusCode) return;
                    }
                }
                catch { }
            }
        }

        // =====================================================================
        // YOUTUBE SONG / PLAYLIST IMPORTER & PLAYLIST CREATION (1:1 Android)
        // =====================================================================
        private static bool LooksLikeMusicImportUrl(string raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return false;
            return raw.Contains("http://", StringComparison.OrdinalIgnoreCase) ||
                   raw.Contains("https://", StringComparison.OrdinalIgnoreCase) ||
                   raw.Contains("youtube.com", StringComparison.OrdinalIgnoreCase) ||
                   raw.Contains("youtu.be", StringComparison.OrdinalIgnoreCase) ||
                   raw.Contains("music.youtube", StringComparison.OrdinalIgnoreCase);
        }

        private void ManufakturImportInputBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            string val = ManufakturImportInputBox?.Text ?? "";
            if (ManufakturImportPlaceholderTxt != null)
            {
                ManufakturImportPlaceholderTxt.Visibility = string.IsNullOrEmpty(val) ? Visibility.Visible : Visibility.Collapsed;
            }
            if (ManufakturImportBtn != null)
            {
                bool isUrl = string.IsNullOrWhiteSpace(val) || LooksLikeMusicImportUrl(val);
                ManufakturImportBtn.Content = isUrl ? "⬇ Import" : "＋ Playlist";
            }
        }

        private void ManufakturImportInputBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                e.Handled = true;
                ManufakturImportOrCreate_Click(sender, new RoutedEventArgs());
            }
        }

        private async void ManufakturImportOrCreate_Click(object sender, RoutedEventArgs e)
        {
            string raw = (ManufakturImportInputBox?.Text ?? "").Trim();
            if (string.IsNullOrWhiteSpace(raw))
            {
                ShowOsdToast("ℹ Bitte YouTube-Link oder Playlist-Namen eingeben");
                return;
            }

            if (ManufakturImportBtn != null) ManufakturImportBtn.IsEnabled = false;
            try
            {
                if (LooksLikeMusicImportUrl(raw))
                {
                    await SubmitMusicUrlImportAsync(raw);
                }
                else
                {
                    await CreateNewManufakturPlaylistAsync(raw);
                }
            }
            finally
            {
                if (ManufakturImportBtn != null) ManufakturImportBtn.IsEnabled = true;
            }
        }

        private async Task CreateNewManufakturPlaylistAsync(string playlistName)
        {
            foreach (var (baseUrl, token) in GetCandidateEndpointsAndTokens())
            {
                try
                {
                    using var req = new HttpRequestMessage(HttpMethod.Post, $"{baseUrl}/funki/music/playlists");
                    req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                    if (!string.IsNullOrWhiteSpace(token))
                        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);
                    req.Content = new StringContent(JsonSerializer.Serialize(new { name = playlistName }), Encoding.UTF8, "application/json");

                    using var resp = await _musicHttp.SendAsync(req);
                    if (!resp.IsSuccessStatusCode) continue;

                    if (ManufakturImportInputBox != null) ManufakturImportInputBox.Text = "";
                    await SyncManufakturPlaylistsOnlineAsync();

                    var created = _manufakturPlaylists.FirstOrDefault(p => p.Name.Equals(playlistName, StringComparison.OrdinalIgnoreCase));
                    if (created != null)
                    {
                        _activeManufakturPlaylistId = created.Id;
                        RenderManufakturLibrary();
                    }
                    ShowOsdToast($"✓ Playlist \"{playlistName}\" erstellt");
                    return;
                }
                catch { }
            }

            ShowOsdToast("⚠ Playlist konnte nicht erstellt werden (Anmeldung prüfen)");
        }

        private async Task SubmitMusicUrlImportAsync(string url)
        {
            string? targetPlId = (_activeManufakturPlaylistId != "all" &&
                                  _activeManufakturPlaylistId != "favorites" &&
                                  _activeManufakturPlaylistId != LocalPcPlaylistId &&
                                  _activeManufakturPlaylistId != LocalOrphanPlaylistId)
                ? _activeManufakturPlaylistId
                : null;

            foreach (var (baseUrl, token) in GetCandidateEndpointsAndTokens())
            {
                try
                {
                    using var req = new HttpRequestMessage(HttpMethod.Post, $"{baseUrl}/funki/music/import-url");
                    req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                    if (!string.IsNullOrWhiteSpace(token))
                        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);
                    req.Content = new StringContent(JsonSerializer.Serialize(new { url, playlist_id = targetPlId }), Encoding.UTF8, "application/json");

                    using var resp = await _musicHttp.SendAsync(req);
                    if (!resp.IsSuccessStatusCode) continue;

                    if (ManufakturImportInputBox != null) ManufakturImportInputBox.Text = "";
                    if (ManufakturImportProgressCard != null)
                    {
                        ManufakturImportLabelTxt.Text = "Import gestartet...";
                        ManufakturImportPctTxt.Text = "5 %";
                        ManufakturImportProgressBar.Value = 5;
                        ManufakturImportSubTxt.Text = url;
                        ManufakturImportProgressCard.Visibility = Visibility.Visible;
                    }

                    StartImportProgressPolling(baseUrl, token);
                    ShowOsdToast("⬇ Musik-Import gestartet");
                    return;
                }
                catch { }
            }

            ShowOsdToast("⚠ Import fehlgeschlagen (Server/Login prüfen)");
        }

        private void StartImportProgressPolling(string baseUrl, string token)
        {
            _importPollTimer?.Stop();
            int idleTicks = 0;
            _importPollTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(1500) };
            _importPollTimer.Tick += async (_, _) =>
            {
                try
                {
                    using var req = new HttpRequestMessage(HttpMethod.Get, $"{baseUrl}/funki/music/import-status");
                    req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                    if (!string.IsNullOrWhiteSpace(token))
                        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);

                    using var resp = await _musicHttp.SendAsync(req);
                    if (!resp.IsSuccessStatusCode) return;

                    using var doc = JsonDocument.Parse(await resp.Content.ReadAsStringAsync());
                    var root = doc.RootElement.TryGetProperty("data", out var d) ? d : doc.RootElement;

                    string status = root.TryGetProperty("status", out var st) && st.ValueKind == JsonValueKind.String
                        ? (st.GetString() ?? "") : "";
                    int pct = root.TryGetProperty("percent", out var pc) && pc.TryGetInt32(out int pVal)
                        ? Math.Clamp(pVal, 0, 100) : 0;
                    string msg = root.TryGetProperty("message", out var mg) && mg.ValueKind == JsonValueKind.String
                        ? (mg.GetString() ?? "") : "";
                    string curTitle = root.TryGetProperty("current_title", out var ct) && ct.ValueKind == JsonValueKind.String
                        ? (ct.GetString() ?? "") : "";

                    if (ManufakturImportProgressCard != null)
                    {
                        ManufakturImportProgressCard.Visibility = Visibility.Visible;
                        ManufakturImportLabelTxt.Text = !string.IsNullOrWhiteSpace(msg) ? msg : "Importiere Songs...";
                        ManufakturImportPctTxt.Text = $"{pct} %";
                        ManufakturImportProgressBar.Value = pct;
                        ManufakturImportSubTxt.Text = curTitle;
                    }

                    bool isFinished = status.Equals("completed", StringComparison.OrdinalIgnoreCase) ||
                                      status.Equals("done", StringComparison.OrdinalIgnoreCase) ||
                                      status.Equals("idle", StringComparison.OrdinalIgnoreCase) ||
                                      status.Equals("error", StringComparison.OrdinalIgnoreCase) ||
                                      pct >= 100;

                    if (isFinished)
                    {
                        idleTicks++;
                        if (idleTicks >= 1)
                        {
                            _importPollTimer?.Stop();
                            LoadManufakturPlaylistsFromDisk();
                            await SyncManufakturPlaylistsOnlineAsync();
                            if (ManufakturImportProgressCard != null)
                                ManufakturImportProgressCard.Visibility = Visibility.Collapsed;
                            ShowOsdToast(status.Equals("error", StringComparison.OrdinalIgnoreCase)
                                ? "⚠ Import mit Hinweis beendet"
                                : "✓ Musik-Import abgeschlossen");
                        }
                    }
                }
                catch { }
            };
            _importPollTimer.Start();
        }

        // =====================================================================
        // LIBRARY RENDERER (SIDEBAR + TRACK ROWS)
        // =====================================================================
        private void RenderManufakturLibrary()
        {
            if (ManufakturPlaylistsStack == null || ManufakturTracksStack == null) return;

            ManufakturCountAllTxt.Text = _allManufakturTracks.Count.ToString();
            ManufakturCountFavTxt.Text = _allManufakturTracks.Count(t => t.IsFavorite).ToString();

            ManufakturAllBtn.Background = BrushFromHex(_activeManufakturPlaylistId == "all" ? "#26231F" : "#181B24");
            ManufakturAllBtn.BorderBrush = BrushFromHex(_activeManufakturPlaylistId == "all" ? "#C5A059" : "#262A36");
            ManufakturFavBtn.Background = BrushFromHex(_activeManufakturPlaylistId == "favorites" ? "#26231F" : "#181B24");
            ManufakturFavBtn.BorderBrush = BrushFromHex(_activeManufakturPlaylistId == "favorites" ? "#C5A059" : "#262A36");

            ManufakturPlaylistsStack.Children.Clear();
            foreach (var pl in _manufakturPlaylists)
            {
                bool sel = _activeManufakturPlaylistId == pl.Id;
                var bd = new Border
                {
                    Background = BrushFromHex(sel ? "#26231F" : "Transparent"),
                    BorderBrush = BrushFromHex(sel ? "#C5A059" : "Transparent"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(9),
                    Padding = new Thickness(11, 8, 11, 8),
                    Margin = new Thickness(0, 0, 0, 4),
                    Cursor = Cursors.Hand,
                    ToolTip = $"{pl.Name} direkt abspielen"
                };
                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                string icon = pl.Id == LocalPcPlaylistId ? "💻" : (pl.Id == LocalOrphanPlaylistId ? "⚡" : "💿");
                var nameTxt = new TextBlock
                {
                    Text = $"{icon}  {pl.Name}",
                    FontSize = 12.5,
                    FontWeight = sel ? FontWeights.Bold : FontWeights.SemiBold,
                    Foreground = BrushFromHex(sel ? "#C5A059" : "#F9FAFB"),
                    TextTrimming = TextTrimming.CharacterEllipsis
                };
                var cntTxt = new TextBlock
                {
                    Text = (pl.Tracks?.Count ?? 0).ToString(),
                    FontSize = 11,
                    FontWeight = FontWeights.Bold,
                    Foreground = BrushFromHex(sel ? "#C5A059" : "#9CA3AF"),
                    Margin = new Thickness(8, 0, 0, 0)
                };
                Grid.SetColumn(nameTxt, 0);
                Grid.SetColumn(cntTxt, 1);
                g.Children.Add(nameTxt);
                g.Children.Add(cntTxt);
                bd.Child = g;

                var capturedPl = pl;
                bd.MouseLeftButtonUp += async (_, _) => await SelectManufakturPlaylistAsync(capturedPl.Id, autoPlayRandom: true);
                ManufakturPlaylistsStack.Children.Add(bd);
            }

            if (_activeManufakturPlaylistId == "all")
                ManufakturHeaderTitleTxt.Text = "Alle Songs";
            else if (_activeManufakturPlaylistId == "favorites")
                ManufakturHeaderTitleTxt.Text = "Favoriten";
            else
                ManufakturHeaderTitleTxt.Text = _manufakturPlaylists.FirstOrDefault(p => p.Id == _activeManufakturPlaylistId)?.Name ?? "Playlist";

            var tracks = GetFilteredManufakturTracks();
            ManufakturTracksStack.Children.Clear();
            int pos = 1;
            foreach (var tr in tracks)
            {
                bool readyLocally = IsTrackAvailableLocallyOrOnWsl(tr, out string? directCachedFile);
                bool isCurrent = (_activeManufakturTrack != null && string.Equals(_activeManufakturTrack.Id, tr.Id, StringComparison.OrdinalIgnoreCase)) ||
                                 (directCachedFile != null && string.Equals(_currentFilePath, directCachedFile, StringComparison.OrdinalIgnoreCase));

                var row = new Border
                {
                    Background = BrushFromHex(isCurrent ? "#26231F" : "#181B24"),
                    BorderBrush = BrushFromHex(isCurrent ? "#C5A059" : "#232734"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(14, 9, 14, 9),
                    Margin = new Thickness(0, 0, 0, 5),
                    Cursor = Cursors.Hand
                };

                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(32) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(52) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(30) });

                g.Children.Add(new TextBlock
                {
                    Text = isCurrent ? (_isPlaying ? "▶" : "❚❚") : pos.ToString(),
                    Foreground = BrushFromHex(isCurrent ? "#C5A059" : "#9CA3AF"),
                    FontWeight = FontWeights.Bold,
                    FontSize = 12,
                    VerticalAlignment = VerticalAlignment.Center
                });

                var info = new StackPanel { VerticalAlignment = VerticalAlignment.Center };
                info.Children.Add(new TextBlock
                {
                    Text = tr.Title,
                    FontSize = 13,
                    FontWeight = FontWeights.Bold,
                    Foreground = BrushFromHex(isCurrent ? "#C5A059" : "#F9FAFB"),
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
                if (!string.IsNullOrWhiteSpace(tr.Artist))
                {
                    info.Children.Add(new TextBlock
                    {
                        Text = tr.Artist,
                        FontSize = 11,
                        Foreground = BrushFromHex("#9CA3AF"),
                        TextTrimming = TextTrimming.CharacterEllipsis
                    });
                }
                Grid.SetColumn(info, 1);
                g.Children.Add(info);

                if (readyLocally)
                {
                    var badge = new Border
                    {
                        Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(_isDarkMode ? "#142921" : "#DCFCE7")),
                        CornerRadius = new CornerRadius(5),
                        Padding = new Thickness(6, 2, 6, 2),
                        Margin = new Thickness(8, 0, 8, 0),
                        VerticalAlignment = VerticalAlignment.Center,
                        Child = new TextBlock
                        {
                            Text = directCachedFile != null ? "⚡ Lokal" : "⚡ Bereit",
                            Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(_isDarkMode ? "#34D399" : "#15803D")),
                            FontSize = 9.5,
                            FontWeight = FontWeights.Bold
                        }
                    };
                    Grid.SetColumn(badge, 2);
                    g.Children.Add(badge);
                }

                var durTxt = new TextBlock
                {
                    Text = tr.FormattedDuration,
                    FontSize = 11.5,
                    FontWeight = FontWeights.SemiBold,
                    Foreground = BrushFromHex("#9CA3AF"),
                    VerticalAlignment = VerticalAlignment.Center,
                    HorizontalAlignment = HorizontalAlignment.Right,
                    Margin = new Thickness(0, 0, 8, 0)
                };
                Grid.SetColumn(durTxt, 3);
                g.Children.Add(durTxt);

                var favBtn = new Button
                {
                    Content = tr.IsFavorite ? "♥" : "♡",
                    Focusable = false,
                    Background = Brushes.Transparent,
                    BorderThickness = new Thickness(0),
                    Foreground = BrushFromHex(tr.IsFavorite ? "#C5A059" : "#9CA3AF"),
                    FontSize = 14,
                    Cursor = Cursors.Hand
                };
                var capturedTrack = tr;
                favBtn.Click += (_, ev) =>
                {
                    ev.Handled = true;
                    ToggleManufakturFavorite(capturedTrack);
                };
                Grid.SetColumn(favBtn, 4);
                g.Children.Add(favBtn);

                var capturedList = tracks;
                row.MouseLeftButtonUp += async (_, _) =>
                {
                    // 1:1 Android MusicTabContent: clicking the already active track toggles Play/Pause!
                    if (_activeManufakturTrack != null &&
                        string.Equals(_activeManufakturTrack.Id, capturedTrack.Id, StringComparison.OrdinalIgnoreCase) &&
                        !string.IsNullOrWhiteSpace(_currentFilePath))
                    {
                        TogglePlayPause();
                    }
                    else
                    {
                        await PlayManufakturTrackFromQueueAsync(capturedTrack, capturedList, retryDepth: 0);
                    }
                };
                row.Child = g;
                ManufakturTracksStack.Children.Add(row);
                pos++;
            }
        }

        private void UpdateManufakturCacheLabel()
        {
            if (ManufakturCacheStatusTxt == null) return;
            try
            {
                RefreshLocalCacheStemIndex();
                string dir = GetActiveMusicCacheDirectory();
                var files = Directory.Exists(dir) ? Directory.GetFiles(dir, "*.m4a") : Array.Empty<string>();
                double mb = files.Sum(f => new FileInfo(f).Length) / (1024.0 * 1024.0);
                if (files.Length > 0)
                {
                    ManufakturCacheStatusTxt.Text = $"⚡ {files.Length} Songs im Cache ({mb:0.0} MB)";
                }
                else
                {
                    int wslCount = _customMusicCacheDirOverride == null ? _wslAvailableStems.Count : 0;
                    ManufakturCacheStatusTxt.Text = wslCount > 0
                        ? $"⚡ {wslCount} Manufaktur-Songs bereit"
                        : "⚡ 0 Songs lokal (0.0 MB)";
                }
            }
            catch { }
        }

        private void StartPlayerIpcPipeServerOnce()
        {
            if (_playerPipeCts != null) return;
            StartPlayerIpcPipeServer();
        }

        private async void SelectManufakturAll_Click(object sender, MouseButtonEventArgs e)
            => await SelectManufakturPlaylistAsync("all", autoPlayRandom: true);

        private async void SelectManufakturFavorites_Click(object sender, MouseButtonEventArgs e)
            => await SelectManufakturPlaylistAsync("favorites", autoPlayRandom: true);

        private void ManufakturCurrentFav_Click(object sender, RoutedEventArgs e)
        {
            if (_activeManufakturTrack != null)
            {
                ToggleManufakturFavorite(_activeManufakturTrack);
            }
        }

        private void ManufakturSearchBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            _manufakturSearchQuery = ManufakturSearchBox.Text ?? "";
            if (ManufakturSearchPlaceholderTxt != null)
            {
                ManufakturSearchPlaceholderTxt.Visibility = string.IsNullOrEmpty(_manufakturSearchQuery)
                    ? Visibility.Visible
                    : Visibility.Collapsed;
            }
            RenderManufakturLibrary();
        }

        private async void ManufakturShuffle_Click(object sender, RoutedEventArgs e)
        {
            ToggleManufakturShuffle();
            if (_isShuffleEnabled && !_isPlaying && _playerMode == "playlists")
            {
                var tracks = GetFilteredManufakturTracks();
                if (tracks.Count > 0)
                {
                    await PlayManufakturTrackFromQueueAsync(tracks[_vizRng.Next(tracks.Count)], tracks, retryDepth: 0);
                }
            }
        }

        private async void ManufakturCacheAll_Click(object sender, RoutedEventArgs e)
        {
            var tracks = GetFilteredManufakturTracks();
            if (tracks.Count == 0) return;
            ManufakturCacheAllBtn.IsEnabled = false;
            try
            {
                int done = 0;
                int cachedOk = 0;
                foreach (var tr in tracks)
                {
                    done++;
                    ManufakturCacheStatusTxt.Text = $"⚡ Speichere ({done}/{tracks.Count})...";
                    string? path = await EnsureManufakturTrackCachedAsync(tr);
                    if (!string.IsNullOrEmpty(path)) cachedOk++;
                }
                RenderManufakturLibrary();
                UpdateManufakturCacheLabel();
                ShowOsdToast($"⚡ {cachedOk}/{tracks.Count} Songs offline gespeichert");
            }
            finally
            {
                ManufakturCacheAllBtn.IsEnabled = true;
            }
        }

        // =====================================================================
        // GPU CELESTIAL FOCUS MODE
        // =====================================================================
        private void SyncFocusOverlayLabels()
        {
            if (FocusTrackTitleTxt == null || FocusTrackArtistTxt == null) return;
            if (_activeManufakturTrack != null)
            {
                FocusTrackTitleTxt.Text = _activeManufakturTrack.Title;
                FocusTrackArtistTxt.Text = !string.IsNullOrWhiteSpace(_activeManufakturTrack.Artist) ? _activeManufakturTrack.Artist : "Mein Seelenfunke";
            }
            else if (!string.IsNullOrWhiteSpace(_currentFilePath))
            {
                FocusTrackTitleTxt.Text = CurrentTitleTxt.Text;
                FocusTrackArtistTxt.Text = "Funkenwerk Player";
            }
            if (FocusOverlayPlayPauseBtn != null)
            {
                FocusOverlayPlayPauseBtn.Content = _isPlaying ? "❚❚" : "▶";
            }
        }

        private (double Bass, double Kick, double Mid, double High, bool IsPlaying) SampleFocusRhythmBands()
        {
            if (!_isPlaying)
                return (0.08, 0.0, 0.06, 0.04, false);

            double audioSec = 0.0;
            try { audioSec = PlayerMediaElement.Position.TotalSeconds; } catch { }
            int seed = (_currentFilePath ?? "seelenfunke").GetHashCode();
            double bpm = 100.0 + Math.Abs(seed % 26);
            double omega = (bpm / 60.0) * Math.PI * 2.0;
            double beat = Math.Max(0.0, Math.Sin(audioSec * omega));
            double kick = beat * beat * beat * beat * beat * beat;
            double bass = 0.18 + (beat * beat * beat) * 0.65;
            double mid = 0.16 + 0.40 * Math.Abs(Math.Sin(audioSec * 2.4));
            double high = 0.12 + 0.35 * Math.Abs(Math.Cos(audioSec * 6.8));
            return (bass, kick, mid, high, true);
        }

        public async Task EnterFocusModeAsync(bool startPlaybackIfIdle = true)
        {
            if (startPlaybackIfIdle && !_isPlaying)
            {
                await StartOrResumeManufakturPlaybackAsync();
            }

            SyncFocusOverlayLabels();
            _isFocusModeActive = true;
            _lastFocusMouseUtc = DateTime.UtcNow;

            if (MusicVenomFocusOverlay != null)
                MusicVenomFocusOverlay.Visibility = Visibility.Visible;
            if (VenomExitFocusBtn != null)
                VenomExitFocusBtn.Visibility = IsMouseOver ? Visibility.Visible : Visibility.Collapsed;

            if (CelestialFocusHost != null)
            {
                CelestialFocusHost.SampleProvider = SampleFocusRhythmBands;
                CelestialFocusHost.StartRendering();
            }

            if (_focusIdleTimer == null)
            {
                _focusIdleTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(600) };
                _focusIdleTimer.Tick += (_, _) =>
                {
                    if (!_isFocusModeActive || VenomExitFocusBtn == null) return;
                    if (VenomExitFocusBtn.IsMouseOver) { _lastFocusMouseUtc = DateTime.UtcNow; return; }
                    if (VenomExitFocusBtn.Visibility == Visibility.Visible &&
                        (DateTime.UtcNow - _lastFocusMouseUtc).TotalSeconds >= 2.4)
                    {
                        VenomExitFocusBtn.Visibility = Visibility.Collapsed;
                    }
                };
            }
            _focusIdleTimer.Start();
        }

        public void ExitFocusMode()
        {
            _isFocusModeActive = false;
            _focusIdleTimer?.Stop();
            CelestialFocusHost?.StopRendering();
            if (MusicVenomFocusOverlay != null)
                MusicVenomFocusOverlay.Visibility = Visibility.Collapsed;
        }

        private async void EnterFocusMode_Click(object sender, RoutedEventArgs e) => await EnterFocusModeAsync(true);
        private void ExitFocusMode_Click(object sender, RoutedEventArgs e) => ExitFocusMode();

        private void MusicVenomFocusOverlay_MouseEnter(object sender, MouseEventArgs e)
        {
            _lastFocusMouseUtc = DateTime.UtcNow;
            if (_isFocusModeActive && VenomExitFocusBtn != null)
                VenomExitFocusBtn.Visibility = Visibility.Visible;
        }

        private void MusicVenomFocusOverlay_MouseMove(object sender, MouseEventArgs e)
        {
            _lastFocusMouseUtc = DateTime.UtcNow;
            if (_isFocusModeActive && VenomExitFocusBtn != null && VenomExitFocusBtn.Visibility != Visibility.Visible)
                VenomExitFocusBtn.Visibility = Visibility.Visible;
        }

        private void MusicVenomFocusOverlay_MouseLeave(object sender, MouseEventArgs e)
        {
            if (VenomExitFocusBtn != null)
                VenomExitFocusBtn.Visibility = Visibility.Collapsed;
        }

        private void MusicVenomFocusOverlay_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (e.ClickCount == 2) ExitFocusMode();
        }

        // =====================================================================
        // NAMED PIPE IPC SERVER (Funkenwerk_PlayerPipe_v1) FOR FUNKIRA & MCP
        // =====================================================================
        private void StartPlayerIpcPipeServer()
        {
            _playerPipeCts = new CancellationTokenSource();
            var ct = _playerPipeCts.Token;
            _ = Task.Run(async () =>
            {
                while (!ct.IsCancellationRequested)
                {
                    try
                    {
                        var pipeSec = new PipeSecurity();
                        var sid = WindowsIdentity.GetCurrent().User;
                        if (sid != null)
                            pipeSec.AddAccessRule(new PipeAccessRule(sid, PipeAccessRights.FullControl, AccessControlType.Allow));

                        using var server = NamedPipeServerStreamAcl.Create(
                            PlayerPipeName,
                            PipeDirection.InOut,
                            NamedPipeServerStream.MaxAllowedServerInstances,
                            PipeTransmissionMode.Byte,
                            PipeOptions.Asynchronous,
                            4096,
                            4096,
                            pipeSec);

                        await server.WaitForConnectionAsync(ct);
                        using var reader = new StreamReader(server, Encoding.UTF8, false, 4096, leaveOpen: true);
                        using var writer = new StreamWriter(server, new UTF8Encoding(false), 4096, leaveOpen: true) { AutoFlush = true };

                        string? line = await reader.ReadLineAsync(ct);
                        if (!string.IsNullOrWhiteSpace(line))
                        {
                            string resp = await HandlePlayerPipeCommandAsync(line);
                            await writer.WriteLineAsync(resp);
                        }
                    }
                    catch (OperationCanceledException) { break; }
                    catch { await Task.Delay(250, ct); }
                }
            }, ct);
        }

        public async Task<string> HandlePlayerPipeCommandAsync(string rawJson)
        {
            try
            {
                using var doc = JsonDocument.Parse(rawJson);
                var root = doc.RootElement;
                string action = root.TryGetProperty("action", out var a) && a.ValueKind == JsonValueKind.String
                    ? (a.GetString() ?? "status").Trim().ToLowerInvariant()
                    : "status";
                string query = root.TryGetProperty("query", out var q) && q.ValueKind == JsonValueKind.String
                    ? (q.GetString() ?? "").Trim()
                    : "";
                int? volRaw = root.TryGetProperty("volume", out var v) && v.ValueKind == JsonValueKind.Number && v.TryGetInt32(out int vInt)
                    ? vInt
                    : null;

                async Task<string> ExecuteOnUiAsync()
                {
                    switch (action)
                    {
                        case "play":
                            if (!string.IsNullOrWhiteSpace(query))
                            {
                                var matchPl = _manufakturPlaylists.FirstOrDefault(p => p.Name.Contains(query, StringComparison.OrdinalIgnoreCase));
                                if (matchPl != null)
                                {
                                    await SelectManufakturPlaylistAsync(matchPl.Id, autoPlayRandom: true);
                                }
                                else
                                {
                                    _manufakturSearchQuery = query;
                                    if (ManufakturSearchBox != null) ManufakturSearchBox.Text = query;
                                    SwitchPlayerMode("playlists");
                                    var qTracks = GetFilteredManufakturTracks();
                                    if (qTracks.Count > 0)
                                        await PlayManufakturTrackFromQueueAsync(qTracks[0], qTracks, retryDepth: 0);
                                }
                            }
                            else if (!_isPlaying)
                            {
                                await StartOrResumeManufakturPlaybackAsync();
                            }
                            break;
                        case "pause":
                            if (_isPlaying) TogglePlayPause();
                            break;
                        case "toggle":
                            TogglePlayPause();
                            break;
                        case "next":
                            if (_isManufakturPlaybackActive) await PlayNextManufakturTrackAsync();
                            else StepNextTrack();
                            break;
                        case "prev":
                            if (_isManufakturPlaybackActive) await PlayPreviousManufakturTrackAsync();
                            else StepPreviousTrack();
                            break;
                        case "volume":
                        case "set_volume":
                            if (volRaw.HasValue)
                            {
                                SetMuted(false);
                                VolumeSlider.Value = Math.Clamp(volRaw.Value, 0, 100);
                            }
                            break;
                        case "volume_delta":
                            if (volRaw.HasValue)
                            {
                                SetMuted(false);
                                VolumeSlider.Value = Math.Clamp((int)Math.Round(VolumeSlider.Value) + volRaw.Value, 0, 100);
                            }
                            break;
                        case "shuffle":
                            ToggleManufakturShuffle();
                            break;
                        case "repeat":
                            _isLoopEnabled = !_isLoopEnabled;
                            UpdateDynamicControlBrushes();
                            break;
                        case "focus_on":
                            await EnterFocusModeAsync(true);
                            break;
                        case "focus_off":
                            ExitFocusMode();
                            break;
                        case "play_playlist":
                            if (!string.IsNullOrWhiteSpace(query))
                            {
                                var matchPl = _manufakturPlaylists.FirstOrDefault(p => p.Name.Contains(query, StringComparison.OrdinalIgnoreCase));
                                if (matchPl != null)
                                {
                                    await SelectManufakturPlaylistAsync(matchPl.Id, autoPlayRandom: true);
                                }
                                else if (query.Contains("favorit", StringComparison.OrdinalIgnoreCase))
                                {
                                    await SelectManufakturPlaylistAsync("favorites", autoPlayRandom: true);
                                }
                                else if (query.Contains("alle", StringComparison.OrdinalIgnoreCase))
                                {
                                    await SelectManufakturPlaylistAsync("all", autoPlayRandom: true);
                                }
                                else
                                {
                                    _manufakturSearchQuery = query;
                                    SwitchPlayerMode("playlists");
                                    var plTracks = GetFilteredManufakturTracks();
                                    if (plTracks.Count > 0)
                                        await PlayManufakturTrackFromQueueAsync(plTracks[0], plTracks, retryDepth: 0);
                                }
                            }
                            else
                            {
                                await SelectManufakturPlaylistAsync(_activeManufakturPlaylistId, autoPlayRandom: true);
                            }
                            break;
                    }

                    SyncFocusOverlayLabels();
                    SyncManufakturNowPlayingDeck();
                    return JsonSerializer.Serialize(new
                    {
                        ok = true,
                        is_playing = _isPlaying,
                        shuffle = _isShuffleEnabled,
                        repeat = _isLoopEnabled,
                        volume = (int)Math.Round(VolumeSlider.Value),
                        focus_mode = _isFocusModeActive,
                        current_track = CurrentTitleTxt.Text,
                        playlists_count = _manufakturPlaylists.Count,
                        tracks_count = _allManufakturTracks.Count,
                        summary = _isPlaying
                            ? $"Spielt: {CurrentTitleTxt.Text} ({(int)Math.Round(VolumeSlider.Value)}%)"
                            : $"Pausiert ({_allManufakturTracks.Count} Manufaktur-Songs bereit)"
                    });
                }

                return Dispatcher.CheckAccess()
                    ? await ExecuteOnUiAsync()
                    : await Dispatcher.InvokeAsync(ExecuteOnUiAsync).Task.Unwrap();
            }
            catch (Exception ex)
            {
                return JsonSerializer.Serialize(new { ok = false, summary = ex.Message });
            }
        }
    }
}
