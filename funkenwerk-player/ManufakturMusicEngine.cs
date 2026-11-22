using System;
using System.Collections.Generic;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Security.AccessControl;
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

        [JsonIgnore]
        public string FormattedDuration => DurationSeconds <= 0
            ? "--:--"
            : $"{DurationSeconds / 60}:{DurationSeconds % 60:D2}";
    }

    public partial class MainWindow
    {
        public const string PlayerPipeName = "Funkenwerk_PlayerPipe_v1";
        private const string FavSyncPlaylistName = "__FUNKENWERK_FAV_SYNC__";

        private static readonly HttpClient _musicHttp = new() { Timeout = TimeSpan.FromSeconds(10) };

        private string _playerMode = "media"; // "playlists" or "media"
        private List<MusicPlaylistDto> _manufakturPlaylists = new();
        private readonly List<MusicTrackDto> _allManufakturTracks = new();
        private string _activeManufakturPlaylistId = "all";
        private string _manufakturSearchQuery = "";
        private MusicTrackDto? _activeManufakturTrack;

        private bool _isFocusModeActive;
        private bool _isShuffleEnabled;
        private DateTime _lastFocusMouseUtc = DateTime.UtcNow;
        private DispatcherTimer? _focusIdleTimer;
        private CancellationTokenSource? _playerPipeCts;

        public string ActivePlayerMode => _playerMode;
        public bool IsFocusModeActive => _isFocusModeActive;
        public IReadOnlyList<MusicPlaylistDto> ManufakturPlaylists => _manufakturPlaylists;

        private static string GetMusicCacheDirectory()
        {
            string dir = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "Funkenwerk", "MusicCache");
            Directory.CreateDirectory(dir);
            return dir;
        }

        private async Task InitializeManufakturEngineAsync(bool openPlaylistsViewByDefault)
        {
            LoadManufakturPlaylistsFromDisk();
            if (openPlaylistsViewByDefault)
            {
                SwitchPlayerMode("playlists");
            }
            else
            {
                UpdateModeButtonsUI();
            }

            StartPlayerIpcPipeServer();
            await SyncManufakturPlaylistsOnlineAsync();
        }

        private void ShutdownManufakturEngine()
        {
            try { _playerPipeCts?.Cancel(); } catch { }
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
            try
            {
                string cacheDir = customCacheDir ?? GetMusicCacheDirectory();
                string cacheFile = Path.Combine(cacheDir, "playlists_cache.json");
                string bundledFile = Path.Combine(AppContext.BaseDirectory, "playlists_cache.json");

                List<MusicPlaylistDto> bundled = File.Exists(bundledFile)
                    ? ParsePlaylistsPayload(File.ReadAllText(bundledFile, Encoding.UTF8))
                    : new();
                if (bundled.Count > 0 && !File.Exists(cacheFile))
                {
                    Directory.CreateDirectory(cacheDir);
                    File.Copy(bundledFile, cacheFile, overwrite: true);
                }

                List<MusicPlaylistDto> cached = File.Exists(cacheFile)
                    ? ParsePlaylistsPayload(File.ReadAllText(cacheFile, Encoding.UTF8))
                    : new();

                int cachedCount = cached.Where(p => !p.Name.Equals(FavSyncPlaylistName, StringComparison.OrdinalIgnoreCase)).Sum(p => p.Tracks?.Count ?? 0);
                int bundledCount = bundled.Where(p => !p.Name.Equals(FavSyncPlaylistName, StringComparison.OrdinalIgnoreCase)).Sum(p => p.Tracks?.Count ?? 0);
                var best = bundledCount > cachedCount ? bundled : cached;
                if (best.Count > 0)
                {
                    ApplyManufakturPlaylists(best, cacheDir);
                }
            }
            catch { }
        }

        private async Task SyncManufakturPlaylistsOnlineAsync()
        {
            try
            {
                var session = FunkenwerkSharedSession.Load();
                if (string.IsNullOrWhiteSpace(session.ApiBaseUrl)) return;

                using var req = new HttpRequestMessage(HttpMethod.Get, $"{session.ApiBaseUrl.TrimEnd('/')}/funki/music/playlists");
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                if (!string.IsNullOrWhiteSpace(session.BearerToken))
                {
                    req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", session.BearerToken);
                }

                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(4));
                using var resp = await _musicHttp.SendAsync(req, cts.Token);
                if (!resp.IsSuccessStatusCode) return;

                string body = await resp.Content.ReadAsStringAsync(cts.Token);
                var remote = ParsePlaylistsPayload(body);
                int remoteTracks = remote.Where(p => !p.Name.Equals(FavSyncPlaylistName, StringComparison.OrdinalIgnoreCase)).Sum(p => p.Tracks?.Count ?? 0);
                if (remoteTracks > 0)
                {
                    string cacheDir = GetMusicCacheDirectory();
                    ApplyManufakturPlaylists(remote, cacheDir);
                    string outJson = JsonSerializer.Serialize(new { success = true, data = _manufakturPlaylists });
                    File.WriteAllText(Path.Combine(cacheDir, "playlists_cache.json"), outJson, Encoding.UTF8);
                }
            }
            catch { }
        }

        private void ApplyManufakturPlaylists(List<MusicPlaylistDto> playlists, string cacheDir)
        {
            var localFavIds = LoadLocalFavoriteIds(cacheDir);
            _manufakturPlaylists = playlists
                .Where(p => !string.Equals(p.Name, FavSyncPlaylistName, StringComparison.OrdinalIgnoreCase))
                .ToList();

            _allManufakturTracks.Clear();
            foreach (var pl in _manufakturPlaylists)
            {
                if (pl.Tracks == null) continue;
                foreach (var tr in pl.Tracks)
                {
                    if (string.IsNullOrWhiteSpace(tr.PlaylistId)) tr.PlaylistId = pl.Id;
                    if (localFavIds.Contains(tr.Id)) tr.IsFavorite = true;
                    _allManufakturTracks.Add(tr);
                }
            }

            if (_activeManufakturPlaylistId == "all" && _manufakturPlaylists.Count > 0)
            {
                var favPl = _manufakturPlaylists.FirstOrDefault(p => p.Name.Contains("Lieblingssongs", StringComparison.OrdinalIgnoreCase))
                            ?? _manufakturPlaylists[0];
                _activeManufakturPlaylistId = favPl.Id;
            }

            RenderManufakturLibrary();
            UpdateManufakturCacheLabel();
        }

        private static HashSet<string> LoadLocalFavoriteIds(string cacheDir)
        {
            try
            {
                string f = Path.Combine(cacheDir, "favorites_local.json");
                if (File.Exists(f))
                {
                    var arr = JsonSerializer.Deserialize<List<string>>(File.ReadAllText(f, Encoding.UTF8));
                    if (arr != null) return new HashSet<string>(arr, StringComparer.OrdinalIgnoreCase);
                }
            }
            catch { }
            return new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        }

        private void SaveLocalFavoriteIds()
        {
            try
            {
                string f = Path.Combine(GetMusicCacheDirectory(), "favorites_local.json");
                var ids = _allManufakturTracks.Where(t => t.IsFavorite).Select(t => t.Id).Distinct(StringComparer.OrdinalIgnoreCase).ToList();
                File.WriteAllText(f, JsonSerializer.Serialize(ids), Encoding.UTF8);
            }
            catch { }
        }

        private List<MusicTrackDto> GetFilteredManufakturTracks()
        {
            IEnumerable<MusicTrackDto> list = _allManufakturTracks;
            if (_activeManufakturPlaylistId == "favorites")
                list = list.Where(t => t.IsFavorite);
            else if (_activeManufakturPlaylistId != "all")
                list = list.Where(t => t.PlaylistId == _activeManufakturPlaylistId);

            if (!string.IsNullOrWhiteSpace(_manufakturSearchQuery))
            {
                string q = _manufakturSearchQuery.Trim();
                list = list.Where(t =>
                    t.Title.Contains(q, StringComparison.OrdinalIgnoreCase) ||
                    (!string.IsNullOrWhiteSpace(t.Artist) && t.Artist.Contains(q, StringComparison.OrdinalIgnoreCase)));
            }
            return list.OrderBy(t => t.Position).ToList();
        }

        private string? ResolveCachedTrackFile(MusicTrackDto track)
        {
            string dir = GetMusicCacheDirectory();
            return TryResolveSafeMusicCacheTrackPath(dir, track.Id)
                   ?? TryResolveSafeMusicCacheTrackPath(dir, track.ExternalId);
        }

        private async Task<string?> EnsureManufakturTrackCachedAsync(MusicTrackDto track)
        {
            string? existing = ResolveCachedTrackFile(track);
            if (!string.IsNullOrEmpty(existing) && new FileInfo(existing).Length > 1024)
            {
                return existing;
            }

            if (!SafeCacheIdRegex.IsMatch(track.Id)) return null;
            string targetPath = Path.Combine(GetMusicCacheDirectory(), $"{track.Id}.m4a");

            try
            {
                var session = FunkenwerkSharedSession.Load();
                string baseUrl = string.IsNullOrWhiteSpace(session.ApiBaseUrl) ? "http://127.0.0.1/api" : session.ApiBaseUrl.TrimEnd('/');
                using var req = new HttpRequestMessage(HttpMethod.Get, $"{baseUrl}/funki/music/stream/{track.Id}");
                if (!string.IsNullOrWhiteSpace(session.BearerToken))
                {
                    req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", session.BearerToken);
                }
                using var resp = await _musicHttp.SendAsync(req);
                if (!resp.IsSuccessStatusCode) return null;

                byte[] bytes = await resp.Content.ReadAsByteArrayAsync();
                if (bytes.Length <= 1024) return null;
                await File.WriteAllBytesAsync(targetPath, bytes);
                UpdateManufakturCacheLabel();
                return targetPath;
            }
            catch
            {
                return null;
            }
        }

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
                    Cursor = Cursors.Hand
                };
                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var nameTxt = new TextBlock
                {
                    Text = $"💿  {pl.Name}",
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
                    Foreground = BrushFromHex("#9CA3AF"),
                    Margin = new Thickness(8, 0, 0, 0)
                };
                Grid.SetColumn(nameTxt, 0);
                Grid.SetColumn(cntTxt, 1);
                g.Children.Add(nameTxt);
                g.Children.Add(cntTxt);
                bd.Child = g;

                var capturedPl = pl;
                bd.MouseLeftButtonUp += (_, _) =>
                {
                    _activeManufakturPlaylistId = capturedPl.Id;
                    RenderManufakturLibrary();
                };
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
                string? cachedFile = ResolveCachedTrackFile(tr);
                bool isCurrent = (_activeManufakturTrack != null && _activeManufakturTrack.Id == tr.Id) ||
                                 (cachedFile != null && string.Equals(_currentFilePath, cachedFile, StringComparison.OrdinalIgnoreCase));

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

                if (cachedFile != null)
                {
                    var badge = new Border
                    {
                        Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#142921")),
                        CornerRadius = new CornerRadius(5),
                        Padding = new Thickness(6, 2, 6, 2),
                        Margin = new Thickness(8, 0, 8, 0),
                        VerticalAlignment = VerticalAlignment.Center,
                        Child = new TextBlock
                        {
                            Text = "⚡ Lokal",
                            Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#34D399")),
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
                    capturedTrack.IsFavorite = !capturedTrack.IsFavorite;
                    SaveLocalFavoriteIds();
                    RenderManufakturLibrary();
                };
                Grid.SetColumn(favBtn, 4);
                g.Children.Add(favBtn);

                var capturedList = tracks;
                row.MouseLeftButtonUp += async (_, _) => await PlayManufakturTrackFromQueueAsync(capturedTrack, capturedList);
                row.Child = g;
                ManufakturTracksStack.Children.Add(row);
                pos++;
            }
        }

        private async Task PlayManufakturTrackFromQueueAsync(MusicTrackDto track, List<MusicTrackDto> queue)
        {
            _activeManufakturTrack = track;
            string? localFile = await EnsureManufakturTrackCachedAsync(track);
            if (string.IsNullOrEmpty(localFile))
            {
                ShowOsdToast("⚠ Song offline noch nicht im lokalen Cache");
                return;
            }

            // Build active playlist from all locally cached tracks in this view + current track
            _playlist.Clear();
            int targetIdx = 0;
            foreach (var q in queue)
            {
                string? qFile = q.Id == track.Id ? localFile : ResolveCachedTrackFile(q);
                if (!string.IsNullOrEmpty(qFile))
                {
                    string label = !string.IsNullOrWhiteSpace(q.Artist) ? $"{q.Artist} · {q.Title}" : q.Title;
                    _trackDisplayTitles[qFile] = label;
                    if (!_playlist.Contains(qFile, StringComparer.OrdinalIgnoreCase))
                    {
                        if (q.Id == track.Id) targetIdx = _playlist.Count;
                        _playlist.Add(qFile);
                    }
                }
            }

            if (_playlist.Count == 0)
            {
                _playlist.Add(localFile);
                targetIdx = 0;
            }

            RenderPlaylistUI();
            PlayIndex(targetIdx);
            SyncFocusOverlayLabels();
            RenderManufakturLibrary();
        }

        private void UpdateManufakturCacheLabel()
        {
            if (ManufakturCacheStatusTxt == null) return;
            try
            {
                string dir = GetMusicCacheDirectory();
                var files = Directory.Exists(dir) ? Directory.GetFiles(dir, "*.m4a") : Array.Empty<string>();
                double mb = files.Sum(f => new FileInfo(f).Length) / (1024.0 * 1024.0);
                ManufakturCacheStatusTxt.Text = $"⚡ {files.Length} Songs lokal ({mb:0.0} MB)";
            }
            catch { }
        }

        private void SelectManufakturAll_Click(object sender, MouseButtonEventArgs e)
        {
            _activeManufakturPlaylistId = "all";
            RenderManufakturLibrary();
        }

        private void SelectManufakturFavorites_Click(object sender, MouseButtonEventArgs e)
        {
            _activeManufakturPlaylistId = "favorites";
            RenderManufakturLibrary();
        }

        private void ManufakturSearchBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            _manufakturSearchQuery = ManufakturSearchBox.Text ?? "";
            RenderManufakturLibrary();
        }

        private async void ManufakturShuffle_Click(object sender, RoutedEventArgs e)
        {
            var tracks = GetFilteredManufakturTracks();
            if (tracks.Count == 0) return;
            var shuffled = tracks.OrderBy(_ => _vizRng.Next()).ToList();
            await PlayManufakturTrackFromQueueAsync(shuffled[0], shuffled);
        }

        private async void ManufakturCacheAll_Click(object sender, RoutedEventArgs e)
        {
            var tracks = GetFilteredManufakturTracks();
            if (tracks.Count == 0) return;
            ManufakturCacheAllBtn.IsEnabled = false;
            try
            {
                int done = 0;
                foreach (var tr in tracks)
                {
                    done++;
                    ManufakturCacheStatusTxt.Text = $"⚡ Lade ({done}/{tracks.Count})...";
                    await EnsureManufakturTrackCachedAsync(tr);
                }
                RenderManufakturLibrary();
                UpdateManufakturCacheLabel();
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
                if (_playlist.Count > 0)
                {
                    if (_currentIndex < 0) PlayIndex(0);
                    else TogglePlayPause();
                }
                else
                {
                    var tracks = GetFilteredManufakturTracks();
                    if (tracks.Count > 0)
                    {
                        await PlayManufakturTrackFromQueueAsync(tracks[0], tracks);
                    }
                }
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
                                    _activeManufakturPlaylistId = matchPl.Id;
                                else
                                    _manufakturSearchQuery = query;

                                SwitchPlayerMode("playlists");
                                var qTracks = GetFilteredManufakturTracks();
                                if (qTracks.Count > 0)
                                    await PlayManufakturTrackFromQueueAsync(qTracks[0], qTracks);
                            }
                            else if (!_isPlaying)
                            {
                                if (_playlist.Count > 0)
                                {
                                    if (_currentIndex < 0) PlayIndex(0);
                                    else TogglePlayPause();
                                }
                                else
                                {
                                    var tracks = GetFilteredManufakturTracks();
                                    if (tracks.Count > 0) await PlayManufakturTrackFromQueueAsync(tracks[0], tracks);
                                }
                            }
                            break;
                        case "pause":
                            if (_isPlaying) TogglePlayPause();
                            break;
                        case "toggle":
                            TogglePlayPause();
                            break;
                        case "next":
                            StepNextTrack();
                            break;
                        case "prev":
                            StepPreviousTrack();
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
                            _isShuffleEnabled = !_isShuffleEnabled;
                            break;
                        case "repeat":
                            _isLoopEnabled = !_isLoopEnabled;
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
                                    _activeManufakturPlaylistId = matchPl.Id;
                                }
                                else
                                {
                                    _manufakturSearchQuery = query;
                                }
                            }
                            SwitchPlayerMode("playlists");
                            var plTracks = GetFilteredManufakturTracks();
                            if (plTracks.Count > 0)
                            {
                                await PlayManufakturTrackFromQueueAsync(plTracks[0], plTracks);
                            }
                            break;
                    }

                    SyncFocusOverlayLabels();
                    return JsonSerializer.Serialize(new
                    {
                        ok = true,
                        is_playing = _isPlaying,
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
