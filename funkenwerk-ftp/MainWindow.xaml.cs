using System;
using System.Collections.Generic;
using System.Collections.Specialized;
using System.ComponentModel;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Security;
using System.Security.Authentication;
using System.Security.Cryptography;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Controls.Primitives;
using System.Windows.Input;
using System.Windows.Media;
using FluentFTP;
using Microsoft.Win32;
using Renci.SshNet;

namespace FunkenwerkFtp
{
    public sealed class FileEntryItem : INotifyPropertyChanged
    {
        private bool _isDropTarget;

        public string Name { get; set; } = "";
        public string FullPath { get; set; } = "";
        public bool IsDirectory { get; set; }
        public bool IsSymbolicLink { get; set; }
        public bool IsParentNav { get; set; }
        public long Size { get; set; }
        public DateTime Modified { get; set; }
        public string Permissions { get; set; } = "";

        public bool IsDropTarget
        {
            get => _isDropTarget;
            set
            {
                if (_isDropTarget == value) return;
                _isDropTarget = value;
                PropertyChanged?.Invoke(this, new PropertyChangedEventArgs(nameof(IsDropTarget)));
            }
        }

        public string DisplayName
        {
            get
            {
                if (IsParentNav) return "↩ ..";
                if (IsSymbolicLink) return $"🔗 {Name}";
                if (IsDirectory) return $"📁 {Name}";
                if (FtpTextBufferCodec.IsImageFileName(Name)) return $"🖼 {Name}";
                string ext = Path.GetExtension(Name);
                if (string.Equals(ext, ".pdf", StringComparison.OrdinalIgnoreCase)) return $"📕 {Name}";
                if (ext.Equals(".zip", StringComparison.OrdinalIgnoreCase) ||
                    ext.Equals(".7z", StringComparison.OrdinalIgnoreCase) ||
                    ext.Equals(".tar", StringComparison.OrdinalIgnoreCase) ||
                    ext.Equals(".gz", StringComparison.OrdinalIgnoreCase) ||
                    ext.Equals(".rar", StringComparison.OrdinalIgnoreCase))
                    return $"📦 {Name}";
                if (FtpTextBufferCodec.IsLikelyTextFileName(Name)) return $"📝 {Name}";
                return $"📄 {Name}";
            }
        }

        public string SizeFormatted => IsParentNav ? "" : (IsSymbolicLink ? "<LINK>" : (IsDirectory ? "<DIR>" : FormatBytes(Size)));
        public string ModifiedFormatted => (IsParentNav || Modified == DateTime.MinValue) ? "" : Modified.ToString("dd.MM.yyyy HH:mm");

        public event PropertyChangedEventHandler? PropertyChanged;

        private static string FormatBytes(long bytes)
        {
            if (bytes < 1024) return $"{bytes} B";
            if (bytes < 1024 * 1024) return $"{bytes / 1024.0:F1} KB";
            if (bytes < 1024L * 1024 * 1024) return $"{bytes / (1024.0 * 1024):F1} MB";
            return $"{bytes / (1024.0 * 1024 * 1024):F2} GB";
        }
    }

    public partial class MainWindow : Window
    {
        private List<FtpConnectionProfile> _profiles = new();
        private bool _isDarkMode = true;
        private bool _isPassVisible = false;
        private bool _suppressPassSync = false;
        private string _selectedPrivateKeyPath = "";

        private SftpClient? _sftpClient;
        private AsyncFtpClient? _ftpClient;
        private readonly SemaphoreSlim _remoteIoGate = new(1, 1);
        private string _activeProtocol = "SFTP";
        private string _lastPinnedFingerprint = "";

        // Sorting state
        private string _localSortColumn = "Name";
        private bool _localSortAsc = true;
        private string _remoteSortColumn = "Name";
        private bool _remoteSortAsc = true;
        private List<FileEntryItem> _localCurrentItems = new();
        private List<FileEntryItem> _remoteCurrentItems = new();

        // Drag & Drop state
        private Point _localDragStartPoint;
        private Point _remoteDragStartPoint;
        private bool _localCanStartDrag;
        private bool _remoteCanStartDrag;
        private FileEntryItem? _localDeferredSingleSelect;
        private FileEntryItem? _remoteDeferredSingleSelect;
        private bool _isDraggingInternal = false;
        private FileEntryItem? _highlightedDropItem;

        private const string DRAG_FORMAT_REMOTE = "FunkenwerkFtp.RemoteItems";
        private const string DRAG_FORMAT_LOCAL = "FunkenwerkFtp.LocalItems";
        private const long MAX_EXTERNAL_DRAG_STAGE_BYTES = 160L * 1024 * 1024; // 160 MB safety cap for synchronous Explorer drop

        // External Live-Edit file watchers (keyed by local staged file path to prevent duplicate watchers)
        private readonly Dictionary<string, FileSystemWatcher> _liveEditWatchers = new(StringComparer.OrdinalIgnoreCase);
        private readonly Dictionary<string, byte[]> _liveEditLastHashes = new(StringComparer.OrdinalIgnoreCase);
        private readonly Dictionary<string, DateTime> _liveEditDebounceUtc = new(StringComparer.OrdinalIgnoreCase);
        private bool _closeCleanupDone;
        private bool _closeCleanupInProgress;

        public bool IsDarkMode => _isDarkMode;

        public void SetTheme(bool isDark)
        {
            _isDarkMode = isDark;
            ApplyTheme();
            ResetTileBorders();
        }

        public MainWindow()
        {
            InitializeComponent();
            ApplyTheme();
            PurgeStaleLiveEditFiles();
            DragStagingManager.PruneOldBatches(TimeSpan.FromMinutes(15));
            LoadProfilesIntoUi();
            string startLocal = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);
            NavigateLocalDirectory(startLocal);
        }

        public static void PurgeStaleLiveEditFiles()
        {
            try
            {
                string liveRoot = Path.Combine(FunkenwerkFtpSecurity.VaultDir, "LiveEdit");
                if (Directory.Exists(liveRoot))
                    Directory.Delete(liveRoot, recursive: true);
                Directory.CreateDirectory(liveRoot);
                FunkenwerkFtpSecurity.LockDownDirectoryAcl(liveRoot);
            }
            catch { }
        }

        // =====================================================================
        // PROFILES & CONNECTION BAR
        // =====================================================================
        private void LoadProfilesIntoUi()
        {
            try
            {
                _profiles = FunkenwerkFtpSecurity.LoadAndImportProfiles();
                ProfilesCombo.ItemsSource = null;
                ProfilesCombo.ItemsSource = _profiles;
                if (_profiles.Count > 0)
                {
                    var preferred = _profiles.FirstOrDefault(p => p.Name.Contains("Mittwald", StringComparison.OrdinalIgnoreCase))
                                    ?? _profiles[0];
                    ProfilesCombo.SelectedItem = preferred;
                }
                StatusText.Text = $"🔒 AES-256-GCM + DPAPI Vault aktiv · {_profiles.Count} Profile geladen";
            }
            catch (Exception ex)
            {
                _profiles = new List<FtpConnectionProfile>();
                ProfilesCombo.ItemsSource = _profiles;
                StatusText.Text = $"🚨 Tresor-Schutz aktiv: {ex.Message}";
            }
        }

        private void ProfilesCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (ProfilesCombo.SelectedItem is not FtpConnectionProfile p) return;

            SelectProtocolItem(p.Protocol);
            HostBox.Text = p.Host;
            PortBox.Text = p.Port.ToString();
            UserBox.Text = p.Username;
            try
            {
                SetPasswordValue(FunkenwerkFtpSecurity.DecryptSecret(p.EncryptedPassword));
            }
            catch (Exception ex)
            {
                SetPasswordValue("");
                StatusText.Text = $"🚨 Tresor-Warnung ({p.Name}): {ex.Message}";
            }
            _selectedPrivateKeyPath = p.PrivateKeyPath ?? "";
            PickKeyBtn.ToolTip = string.IsNullOrWhiteSpace(_selectedPrivateKeyPath)
                ? "SSH Private Key wählen"
                : $"SSH Key: {_selectedPrivateKeyPath}";

            if (!string.IsNullOrWhiteSpace(p.RemoteDirectory))
                RemotePathBox.Text = p.RemoteDirectory;
            if (!string.IsNullOrWhiteSpace(p.LocalDirectory) && Directory.Exists(p.LocalDirectory))
                NavigateLocalDirectory(p.LocalDirectory);
        }

        private void ConnectionInput_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter && !IsConnected())
            {
                e.Handled = true;
                Connect_Click(ConnectBtn, new RoutedEventArgs());
            }
        }

        private void SelectProtocolItem(string proto)
        {
            foreach (ComboBoxItem item in ProtocolCombo.Items)
            {
                if (string.Equals(item.Content?.ToString(), proto, StringComparison.OrdinalIgnoreCase))
                {
                    ProtocolCombo.SelectedItem = item;
                    break;
                }
            }
        }

        private string GetSelectedProtocol() =>
            (ProtocolCombo.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "SFTP";

        private void ProtocolCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (PortBox == null) return;
            string proto = GetSelectedProtocol();
            if (proto == "SFTP" && PortBox.Text == "21") PortBox.Text = "22";
            else if (proto != "SFTP" && PortBox.Text == "22") PortBox.Text = "21";
        }

        private string GetPasswordValue() => _isPassVisible ? PassVisibleBox.Text : PassBox.Password;

        private void SetPasswordValue(string val)
        {
            _suppressPassSync = true;
            PassBox.Password = val ?? "";
            PassVisibleBox.Text = val ?? "";
            _suppressPassSync = false;
        }

        private void PassBox_PasswordChanged(object sender, RoutedEventArgs e)
        {
            if (_suppressPassSync) return;
            _suppressPassSync = true;
            PassVisibleBox.Text = PassBox.Password;
            _suppressPassSync = false;
        }

        private void PassVisibleBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (_suppressPassSync) return;
            _suppressPassSync = true;
            PassBox.Password = PassVisibleBox.Text;
            _suppressPassSync = false;
        }

        private void TogglePassEye_Click(object sender, RoutedEventArgs e)
        {
            _isPassVisible = !_isPassVisible;
            PassVisibleBox.Visibility = _isPassVisible ? Visibility.Visible : Visibility.Collapsed;
            PassBox.Visibility = _isPassVisible ? Visibility.Collapsed : Visibility.Visible;
            TogglePassEyeBtn.Content = _isPassVisible ? "🙈" : "👁";
        }

        private void PickKey_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "OpenSSH Private Key auswählen",
                InitialDirectory = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh")
            };
            if (dlg.ShowDialog() == true)
            {
                _selectedPrivateKeyPath = dlg.FileName;
                PickKeyBtn.ToolTip = $"SSH Key: {_selectedPrivateKeyPath}";
                StatusText.Text = $"🔑 SSH-Key ausgewählt: {Path.GetFileName(_selectedPrivateKeyPath)}";
            }
        }

        private void SaveProfile_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                string host = FunkenwerkFtpSecurity.SanitizeHost(HostBox.Text);
                if (string.IsNullOrWhiteSpace(host)) return;
                int port = int.TryParse(PortBox.Text, out int pt) ? pt : 22;
                string user = UserBox.Text.Trim();
                string pass = GetPasswordValue();
                string proto = GetSelectedProtocol();

                var current = ProfilesCombo.SelectedItem as FtpConnectionProfile;
                if (current != null && string.Equals(current.Host, host, StringComparison.OrdinalIgnoreCase))
                {
                    current.Protocol = proto;
                    current.Host = host;
                    current.Port = port;
                    current.Username = user;
                    current.EncryptedPassword = FunkenwerkFtpSecurity.EncryptSecret(pass);
                    current.PrivateKeyPath = _selectedPrivateKeyPath;
                    current.LocalDirectory = LocalPathBox.Text.Trim();
                    current.RemoteDirectory = RemotePathBox.Text.Trim();
                }
                else
                {
                    string defaultName = string.IsNullOrWhiteSpace(user) ? host : $"{user}@{host}";
                    string? chosenName = FtpModalDialogs.ShowInputPrompt(this, _isDarkMode, "Profilname speichern", defaultName, "Speichern");
                    if (string.IsNullOrWhiteSpace(chosenName)) return;

                    var newProf = new FtpConnectionProfile
                    {
                        Name = chosenName,
                        Protocol = proto,
                        Host = host,
                        Port = port,
                        Username = user,
                        EncryptedPassword = FunkenwerkFtpSecurity.EncryptSecret(pass),
                        PrivateKeyPath = _selectedPrivateKeyPath,
                        LocalDirectory = LocalPathBox.Text.Trim(),
                        RemoteDirectory = RemotePathBox.Text.Trim(),
                        Source = "Manuell"
                    };
                    _profiles.Add(newProf);
                    ProfilesCombo.ItemsSource = null;
                    ProfilesCombo.ItemsSource = _profiles;
                    ProfilesCombo.SelectedItem = newProf;
                }

                FunkenwerkFtpSecurity.SaveProfiles(_profiles);
                StatusText.Text = "🔒 Verbindungsprofil AES-256-GCM verschlüsselt gespeichert.";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ {ex.Message}";
            }
        }

        private void DeleteProfile_Click(object sender, RoutedEventArgs e)
        {
            if (ProfilesCombo.SelectedItem is not FtpConnectionProfile current) return;
            if (!FtpModalDialogs.ShowConfirmDialog(this, _isDarkMode, "Profil entfernen", $"Gespeichertes Profil '{current.Name}' wirklich entfernen?", "Entfernen", isDanger: true))
                return;

            _profiles.Remove(current);
            FunkenwerkFtpSecurity.SaveProfiles(_profiles);
            ProfilesCombo.ItemsSource = null;
            ProfilesCombo.ItemsSource = _profiles;
            if (_profiles.Count > 0) ProfilesCombo.SelectedIndex = 0;
            StatusText.Text = "Profil entfernt.";
        }

        // =====================================================================
        // SORTING HELPERS
        // =====================================================================
        private static List<FileEntryItem> SortEntryList(IEnumerable<FileEntryItem> source, string column, bool asc)
        {
            var list = source.ToList();
            var parentRow = list.FirstOrDefault(x => x.IsParentNav);
            var rest = list.Where(x => !x.IsParentNav);

            IOrderedEnumerable<FileEntryItem> ordered = rest.OrderByDescending(x => x.IsDirectory);
            ordered = column switch
            {
                "Größe" => asc ? ordered.ThenBy(x => x.Size).ThenBy(x => x.Name, StringComparer.OrdinalIgnoreCase)
                               : ordered.ThenByDescending(x => x.Size).ThenBy(x => x.Name, StringComparer.OrdinalIgnoreCase),
                "Geändert" => asc ? ordered.ThenBy(x => x.Modified).ThenBy(x => x.Name, StringComparer.OrdinalIgnoreCase)
                                  : ordered.ThenByDescending(x => x.Modified).ThenBy(x => x.Name, StringComparer.OrdinalIgnoreCase),
                "Rechte" => asc ? ordered.ThenBy(x => x.Permissions, StringComparer.OrdinalIgnoreCase).ThenBy(x => x.Name, StringComparer.OrdinalIgnoreCase)
                                : ordered.ThenByDescending(x => x.Permissions, StringComparer.OrdinalIgnoreCase).ThenBy(x => x.Name, StringComparer.OrdinalIgnoreCase),
                _ => asc ? ordered.ThenBy(x => x.Name, StringComparer.OrdinalIgnoreCase)
                         : ordered.ThenByDescending(x => x.Name, StringComparer.OrdinalIgnoreCase)
            };

            var final = new List<FileEntryItem>(list.Count);
            if (parentRow != null) final.Add(parentRow);
            final.AddRange(ordered);
            return final;
        }

        private void LocalFilesList_ColumnHeaderClick(object sender, RoutedEventArgs e)
        {
            if (e.OriginalSource is not GridViewColumnHeader hdr || hdr.Column == null) return;
            string col = hdr.Column.Header?.ToString() ?? "Name";
            if (_localSortColumn == col) _localSortAsc = !_localSortAsc;
            else { _localSortColumn = col; _localSortAsc = true; }
            _localCurrentItems = SortEntryList(_localCurrentItems, _localSortColumn, _localSortAsc);
            LocalFilesList.ItemsSource = _localCurrentItems;
        }

        private void RemoteFilesList_ColumnHeaderClick(object sender, RoutedEventArgs e)
        {
            if (e.OriginalSource is not GridViewColumnHeader hdr || hdr.Column == null) return;
            string col = hdr.Column.Header?.ToString() ?? "Name";
            if (_remoteSortColumn == col) _remoteSortAsc = !_remoteSortAsc;
            else { _remoteSortColumn = col; _remoteSortAsc = true; }
            _remoteCurrentItems = SortEntryList(_remoteCurrentItems, _remoteSortColumn, _remoteSortAsc);
            RemoteFilesList.ItemsSource = _remoteCurrentItems;
        }

        // =====================================================================
        // LOCAL FILESYSTEM BROWSER & ACTIONS
        // =====================================================================
        private void NavigateLocalDirectory(string path)
        {
            try
            {
                if (string.IsNullOrWhiteSpace(path) || !Directory.Exists(path)) return;
                string full = Path.GetFullPath(path);
                LocalPathBox.Text = full;

                var entries = new List<FileEntryItem>();
                var di = new DirectoryInfo(full);
                if (di.Parent != null)
                {
                    entries.Add(new FileEntryItem
                    {
                        Name = "..",
                        FullPath = di.Parent.FullName,
                        IsDirectory = true,
                        IsParentNav = true
                    });
                }

                foreach (var sub in di.GetDirectories())
                {
                    if ((sub.Attributes & FileAttributes.Hidden) != 0) continue;
                    entries.Add(new FileEntryItem
                    {
                        Name = sub.Name,
                        FullPath = sub.FullName,
                        IsDirectory = true,
                        Modified = sub.LastWriteTime
                    });
                }

                foreach (var file in di.GetFiles())
                {
                    if ((file.Attributes & FileAttributes.Hidden) != 0) continue;
                    entries.Add(new FileEntryItem
                    {
                        Name = file.Name,
                        FullPath = file.FullName,
                        IsDirectory = false,
                        Size = file.Length,
                        Modified = file.LastWriteTime
                    });
                }

                _localCurrentItems = SortEntryList(entries, _localSortColumn, _localSortAsc);
                LocalFilesList.ItemsSource = _localCurrentItems;
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Lokal: {ex.Message}";
            }
        }

        private List<FileEntryItem> GetSelectedActionableLocalItems() =>
            LocalFilesList.SelectedItems.Cast<FileEntryItem>().Where(x => !x.IsParentNav).ToList();

        private List<FileEntryItem> GetSelectedActionableRemoteItems() =>
            RemoteFilesList.SelectedItems.Cast<FileEntryItem>().Where(x => !x.IsParentNav).ToList();

        private void LocalUp_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                var parent = Directory.GetParent(LocalPathBox.Text.Trim());
                if (parent != null) NavigateLocalDirectory(parent.FullName);
            }
            catch { }
        }

        private void LocalRefresh_Click(object sender, RoutedEventArgs e) => NavigateLocalDirectory(LocalPathBox.Text.Trim());

        private void LocalPathBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter) NavigateLocalDirectory(LocalPathBox.Text.Trim());
        }

        private async void LocalFilesList_MouseDoubleClick(object sender, MouseButtonEventArgs e)
        {
            if (IsInteractiveChromeElement(e.OriginalSource as DependencyObject)) return;
            if (FindAncestor<ListViewItem>(e.OriginalSource as DependencyObject) == null) return;
            await OpenSelectedLocalItemSmartAsync();
        }

        private async void MenuLocalOpen_Click(object sender, RoutedEventArgs e) => await OpenSelectedLocalItemSmartAsync();

        private async Task OpenSelectedLocalItemSmartAsync()
        {
            if (LocalFilesList.SelectedItem is not FileEntryItem item) return;
            if (item.IsDirectory)
            {
                NavigateLocalDirectory(item.FullPath);
                return;
            }

            if (FtpTextBufferCodec.IsImageFileName(item.Name))
            {
                var imgs = _localCurrentItems.Where(x => !x.IsDirectory && FtpTextBufferCodec.IsImageFileName(x.Name)).ToList();
                int startIdx = Math.Max(0, imgs.FindIndex(x => x.FullPath == item.FullPath));
                FtpModalDialogs.ShowImageLightbox(
                    this,
                    _isDarkMode,
                    imgs,
                    startIdx,
                    async imgItem => await File.ReadAllBytesAsync(imgItem.FullPath),
                    IsConnected() ? async imgItem => await UploadSingleFileAsync(imgItem.FullPath) : null,
                    "→ Auf Server hochladen");
                return;
            }

            if (FtpTextBufferCodec.IsLikelyTextFileName(item.Name) || (item.Size <= 512 * 1024 && await IsLocalFileTextAsync(item.FullPath)))
            {
                await OpenLocalFileInEditorAsync(item.FullPath);
                return;
            }

            OpenLocalFileWithSystemDefault(item.FullPath);
        }

        private static async Task<bool> IsLocalFileTextAsync(string path)
        {
            try
            {
                await using var fs = File.OpenRead(path);
                byte[] buf = new byte[(int)Math.Min(4096, fs.Length)];
                int read = await fs.ReadAsync(buf, 0, buf.Length);
                if (read < buf.Length) Array.Resize(ref buf, read);
                return FtpTextBufferCodec.LooksLikeTextBytes(buf);
            }
            catch
            {
                return false;
            }
        }

        private async void LocalEdit_Click(object sender, RoutedEventArgs e)
        {
            var item = GetSelectedActionableLocalItems().FirstOrDefault(x => !x.IsDirectory);
            if (item == null)
            {
                StatusText.Text = "⚠ Bitte eine lokale Datei zum Bearbeiten auswählen.";
                return;
            }
            await OpenLocalFileInEditorAsync(item.FullPath);
        }

        private async Task OpenLocalFileInEditorAsync(string filePath)
        {
            try
            {
                if (!File.Exists(filePath)) return;
                var fi = new FileInfo(filePath);
                if (fi.Length > 15L * 1024 * 1024)
                {
                    if (!FtpModalDialogs.ShowConfirmDialog(this, _isDarkMode, "Große Datei öffnen",
                            $"Die Datei '{fi.Name}' ist {fi.Length / (1024.0 * 1024):F1} MB groß. Trotzdem im Text-Editor öffnen?",
                            "Öffnen", isDanger: false))
                        return;
                }

                byte[] raw = await File.ReadAllBytesAsync(filePath);
                if (!FtpTextBufferCodec.LooksLikeTextBytes(raw))
                {
                    if (!FtpModalDialogs.ShowConfirmDialog(this, _isDarkMode, "Binärdatei erkannt",
                            $"'{fi.Name}' scheint eine Binärdatei zu sein. Trotzdem als Text öffnen?",
                            "Trotzdem öffnen", isDanger: false))
                        return;
                }

                var doc = FtpTextBufferCodec.Decode(raw, defaultUseCrLf: true);
                Func<byte[], Task>? saveAndUpload = IsConnected()
                    ? async bytes =>
                    {
                        await File.WriteAllBytesAsync(filePath, bytes);
                        NavigateLocalDirectory(LocalPathBox.Text.Trim());
                        await UploadSingleFileAsync(filePath);
                    }
                    : null;

                var ed = new FtpFileEditorWindow(
                    this,
                    _isDarkMode,
                    isRemote: false,
                    fi.Name,
                    filePath,
                    doc,
                    async bytes =>
                    {
                        await File.WriteAllBytesAsync(filePath, bytes);
                        NavigateLocalDirectory(LocalPathBox.Text.Trim());
                        StatusText.Text = $"✓ Lokal gespeichert: {fi.Name}";
                    },
                    saveAndUpload);
                ed.Show();
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Editor-Fehler: {ex.Message}";
            }
        }

        private void MenuLocalOpenSystem_Click(object sender, RoutedEventArgs e)
        {
            var item = GetSelectedActionableLocalItems().FirstOrDefault();
            if (item != null) OpenLocalFileWithSystemDefault(item.FullPath);
        }

        private void OpenLocalFileWithSystemDefault(string fullPath)
        {
            try
            {
                if (fullPath.EndsWith(".pdf", StringComparison.OrdinalIgnoreCase))
                {
                    string fwPdf = @"C:\Users\konta\Desktop\Funkenwerk Collection\Funkenwerk PDF\FunkenwerkPdf.exe";
                    if (File.Exists(fwPdf))
                    {
                        var pdfPsi = new ProcessStartInfo { FileName = fwPdf, UseShellExecute = false };
                        pdfPsi.ArgumentList.Add(fullPath);
                        Process.Start(pdfPsi);
                        return;
                    }
                }
                Process.Start(new ProcessStartInfo { FileName = fullPath, UseShellExecute = true });
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Öffnen fehlgeschlagen: {ex.Message}";
            }
        }

        private async void LocalNewFile_Click(object sender, RoutedEventArgs e)
        {
            string baseDir = LocalPathBox.Text.Trim();
            if (!Directory.Exists(baseDir)) return;

            string? fileName = FtpModalDialogs.ShowInputPrompt(this, _isDarkMode, "Neue lokale Datei erstellen", "neue-datei.txt", "Erstellen & Bearbeiten", selectFileNameStemOnly: true);
            if (string.IsNullOrWhiteSpace(fileName)) return;

            try
            {
                string safeTarget = FunkenwerkFtpSecurity.ValidateSafeLocalPath(baseDir, fileName);
                if (!File.Exists(safeTarget))
                    await File.WriteAllBytesAsync(safeTarget, Array.Empty<byte>());
                NavigateLocalDirectory(baseDir);
                await OpenLocalFileInEditorAsync(safeTarget);
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ {ex.Message}";
            }
        }

        private void LocalMkdir_Click(object sender, RoutedEventArgs e)
        {
            string baseDir = LocalPathBox.Text.Trim();
            if (!Directory.Exists(baseDir)) return;

            string? folderName = FtpModalDialogs.ShowInputPrompt(this, _isDarkMode, "Neuen lokalen Ordner erstellen", "Neuer Ordner", "Erstellen");
            if (string.IsNullOrWhiteSpace(folderName)) return;

            try
            {
                string safeFolder = FunkenwerkFtpSecurity.ValidateSafeLocalPath(baseDir, folderName);
                Directory.CreateDirectory(safeFolder);
                NavigateLocalDirectory(baseDir);
                StatusText.Text = $"✓ Lokaler Ordner erstellt: {folderName}";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ {ex.Message}";
            }
        }

        private void MenuLocalRename_Click(object sender, RoutedEventArgs e)
        {
            var item = GetSelectedActionableLocalItems().FirstOrDefault();
            if (item == null) return;

            string? newName = FtpModalDialogs.ShowInputPrompt(this, _isDarkMode, "Lokal umbenennen", item.Name, "Umbenennen", selectFileNameStemOnly: !item.IsDirectory);
            if (string.IsNullOrWhiteSpace(newName) || string.Equals(newName, item.Name, StringComparison.Ordinal)) return;

            try
            {
                string parentDir = Path.GetDirectoryName(item.FullPath) ?? LocalPathBox.Text.Trim();
                string safeDest = FunkenwerkFtpSecurity.ValidateSafeLocalPath(parentDir, newName);
                if (item.IsDirectory) Directory.Move(item.FullPath, safeDest);
                else File.Move(item.FullPath, safeDest);
                NavigateLocalDirectory(LocalPathBox.Text.Trim());
                StatusText.Text = $"✓ Umbenannt in '{newName}'";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Umbenennen fehlgeschlagen: {ex.Message}";
            }
        }

        private void MenuLocalDuplicate_Click(object sender, RoutedEventArgs e)
        {
            var item = GetSelectedActionableLocalItems().FirstOrDefault();
            if (item == null) return;

            try
            {
                string parentDir = Path.GetDirectoryName(item.FullPath) ?? LocalPathBox.Text.Trim();
                string ext = item.IsDirectory ? "" : Path.GetExtension(item.Name);
                string stem = item.IsDirectory ? item.Name : Path.GetFileNameWithoutExtension(item.Name);
                string copyName = $"{stem}_Kopie{ext}";
                int n = 2;
                while (File.Exists(Path.Combine(parentDir, copyName)) || Directory.Exists(Path.Combine(parentDir, copyName)))
                    copyName = $"{stem}_Kopie_{n++}{ext}";

                string dest = Path.Combine(parentDir, copyName);
                if (item.IsDirectory) CopyLocalDirectoryRecursive(item.FullPath, dest);
                else File.Copy(item.FullPath, dest);

                NavigateLocalDirectory(LocalPathBox.Text.Trim());
                StatusText.Text = $"✓ Dupliziert: {copyName}";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Duplizieren fehlgeschlagen: {ex.Message}";
            }
        }

        private void MenuLocalCopy_Click(object sender, RoutedEventArgs e)
        {
            var items = GetSelectedActionableLocalItems();
            if (items.Count == 0) return;
            var sc = new StringCollection();
            foreach (var it in items) sc.Add(it.FullPath);
            Clipboard.SetFileDropList(sc);
            StatusText.Text = $"📋 {items.Count} Element(e) in Zwischenablage kopiert.";
        }

        private void MenuLocalPaste_Click(object sender, RoutedEventArgs e)
        {
            if (!Clipboard.ContainsFileDropList()) return;
            string destDir = LocalPathBox.Text.Trim();
            if (!Directory.Exists(destDir)) return;

            var list = Clipboard.GetFileDropList();
            int count = CopyExternalPathsIntoLocalDir(list.Cast<string>(), destDir);
            NavigateLocalDirectory(destDir);
            if (count > 0)
                StatusText.Text = $"✓ {count} Element(e) lokal eingefügt.";
        }

        private void MenuLocalCopyPath_Click(object sender, RoutedEventArgs e)
        {
            var item = GetSelectedActionableLocalItems().FirstOrDefault();
            string pathToCopy = item != null ? item.FullPath : LocalPathBox.Text.Trim();
            Clipboard.SetText(pathToCopy);
            StatusText.Text = $"🔗 Pfad kopiert: {pathToCopy}";
        }

        private void MenuLocalRevealExplorer_Click(object sender, RoutedEventArgs e)
        {
            var item = GetSelectedActionableLocalItems().FirstOrDefault();
            try
            {
                if (item != null)
                    Process.Start(new ProcessStartInfo("explorer.exe", $"/select,\"{item.FullPath}\"") { UseShellExecute = true });
                else
                    Process.Start(new ProcessStartInfo("explorer.exe", $"\"{LocalPathBox.Text.Trim()}\"") { UseShellExecute = true });
            }
            catch { }
        }

        private void LocalDelete_Click(object sender, RoutedEventArgs e)
        {
            var selected = GetSelectedActionableLocalItems();
            if (selected.Count == 0) return;

            string msg = selected.Count == 1
                ? $"Lokales Element '{selected[0].Name}' endgültig löschen?"
                : $"{selected.Count} ausgewählte lokale Elemente endgültig löschen?";
            if (!FtpModalDialogs.ShowConfirmDialog(this, _isDarkMode, "Lokal löschen", msg, "Löschen", isDanger: true))
                return;

            foreach (var item in selected)
            {
                try
                {
                    if (item.IsDirectory) Directory.Delete(item.FullPath, true);
                    else File.Delete(item.FullPath);
                }
                catch (Exception ex)
                {
                    StatusText.Text = $"⚠ Löschen: {ex.Message}";
                }
            }
            NavigateLocalDirectory(LocalPathBox.Text.Trim());
            StatusText.Text = $"🗑 {selected.Count} lokale(s) Element(e) gelöscht.";
        }

        private void LocalFilesList_ContextMenuOpening(object sender, ContextMenuEventArgs e)
        {
            var selected = GetSelectedActionableLocalItems();
            bool hasSingle = selected.Count == 1;
            bool hasAny = selected.Count > 0;
            bool hasSingleFile = hasSingle && !selected[0].IsDirectory;

            MenuLocalEdit.IsEnabled = hasSingleFile;
            MenuLocalOpen.IsEnabled = hasSingle || (LocalFilesList.SelectedItem is FileEntryItem p && p.IsParentNav);
            MenuLocalOpenSystem.IsEnabled = hasSingle;
            MenuLocalUpload.IsEnabled = hasAny && IsConnected();
            MenuLocalRename.IsEnabled = hasSingle;
            MenuLocalDuplicate.IsEnabled = hasSingle;
            MenuLocalCopy.IsEnabled = hasAny;
            MenuLocalPaste.IsEnabled = Clipboard.ContainsFileDropList();
            MenuLocalDelete.IsEnabled = hasAny;
        }

        private async void LocalFilesList_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.F4 && (Keyboard.Modifiers & ModifierKeys.Shift) != 0)
            {
                e.Handled = true;
                LocalNewFile_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.F4)
            {
                e.Handled = true;
                LocalEdit_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.F2)
            {
                e.Handled = true;
                MenuLocalRename_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.F5)
            {
                e.Handled = true;
                LocalRefresh_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.F7)
            {
                e.Handled = true;
                LocalMkdir_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.Delete)
            {
                e.Handled = true;
                LocalDelete_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.Back)
            {
                e.Handled = true;
                LocalUp_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.Enter)
            {
                e.Handled = true;
                await OpenSelectedLocalItemSmartAsync();
            }
            else if (e.Key == Key.C && (Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                e.Handled = true;
                MenuLocalCopy_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.V && (Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                e.Handled = true;
                MenuLocalPaste_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.A && (Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                e.Handled = true;
                LocalFilesList.SelectedItems.Clear();
                foreach (var it in _localCurrentItems.Where(x => !x.IsParentNav))
                    LocalFilesList.SelectedItems.Add(it);
            }
        }

        // =====================================================================
        // SECURE SERVER CONNECTION (SFTP / FTPS / FTP)
        // =====================================================================
        public readonly record struct RemoteNodeInfo(
            string Name,
            string FullPath,
            bool IsDirectory,
            bool IsSymbolicLink,
            long Size);

        public static FtpConfig BuildFtpConfig(string protocol)
        {
            bool isFtps = string.Equals(protocol, "FTPS", StringComparison.OrdinalIgnoreCase);
            return new FtpConfig
            {
                EncryptionMode = isFtps ? FtpEncryptionMode.Explicit : FtpEncryptionMode.None,
                DataConnectionEncryption = isFtps,
                SslProtocols = SslProtocols.Tls12 | SslProtocols.Tls13,
                ValidateAnyCertificate = false,
                ConnectTimeout = 10000
            };
        }

        private bool IsConnected()
        {
            if (_sftpClient != null && _sftpClient.IsConnected) return true;
            if (_ftpClient != null && _ftpClient.IsConnected) return true;
            return false;
        }

        private async void Connect_Click(object sender, RoutedEventArgs e)
        {
            if (IsConnected())
            {
                await DisconnectServerAsync();
                return;
            }

            try
            {
                string host = FunkenwerkFtpSecurity.SanitizeHost(HostBox.Text);
                if (string.IsNullOrWhiteSpace(host))
                {
                    StatusText.Text = "⚠ Bitte Hostnamen eingeben.";
                    return;
                }

                int port = int.TryParse(PortBox.Text, out int pt) ? pt : 22;
                string user = UserBox.Text.Trim();
                string pass = GetPasswordValue();
                _activeProtocol = GetSelectedProtocol();

                if (_activeProtocol == "FTP")
                {
                    bool confirmCleartext = FtpModalDialogs.ShowConfirmDialog(
                        this,
                        _isDarkMode,
                        "Unverschlüsseltes FTP",
                        $"WARNUNG: Unverschlüsseltes FTP überträgt Zugangsdaten und Dateien im Klartext an '{host}:{port}'.\n\nWirklich unverschlüsselt verbinden?",
                        "Unverschlüsselt verbinden",
                        isDanger: true);
                    if (!confirmCleartext)
                    {
                        StatusText.Text = "Verbindung abgebrochen (Klartext-FTP abgelehnt).";
                        return;
                    }
                }

                ConnectBtn.IsEnabled = false;
                StatusText.Text = _activeProtocol == "FTP"
                    ? $"⏳ Verbinde unverschlüsselt mit FTP://{host}:{port} ..."
                    : $"⏳ Verbinde verschlüsselt mit {_activeProtocol}://{host}:{port} ...";

                if (_activeProtocol == "SFTP")
                {
                    var authMethods = new List<AuthenticationMethod>();
                    if (!string.IsNullOrWhiteSpace(_selectedPrivateKeyPath) && File.Exists(_selectedPrivateKeyPath))
                    {
                        try
                        {
                            var pkFile = new PrivateKeyFile(_selectedPrivateKeyPath);
                            authMethods.Add(new PrivateKeyAuthenticationMethod(user, pkFile));
                        }
                        catch
                        {
                            if (!string.IsNullOrEmpty(pass))
                            {
                                try
                                {
                                    var pkFileEnc = new PrivateKeyFile(_selectedPrivateKeyPath, pass);
                                    authMethods.Add(new PrivateKeyAuthenticationMethod(user, pkFileEnc));
                                }
                                catch { }
                            }
                        }
                    }
                    if (!string.IsNullOrEmpty(pass))
                    {
                        authMethods.Add(new PasswordAuthenticationMethod(user, pass));
                        var kbAuth = new KeyboardInteractiveAuthenticationMethod(user);
                        kbAuth.AuthenticationPrompt += (s, promptArgs) =>
                        {
                            foreach (var prompt in promptArgs.Prompts)
                                prompt.Response = pass;
                        };
                        authMethods.Add(kbAuth);
                    }

                    if (authMethods.Count == 0)
                    {
                        StatusText.Text = "⚠ Bitte Passwort oder SSH-Key angeben.";
                        ConnectBtn.IsEnabled = true;
                        return;
                    }

                    var connInfo = new ConnectionInfo(host, port, user, authMethods.ToArray())
                    {
                        Timeout = TimeSpan.FromSeconds(12)
                    };

                    var sftp = new SftpClient(connInfo)
                    {
                        KeepAliveInterval = TimeSpan.FromSeconds(15)
                    };
                    bool hostKeyChangedAbort = false;
                    bool hostKeyRejectedAbort = false;
                    string? hostKeyVaultError = null;

                    sftp.HostKeyReceived += (s, hkArgs) =>
                    {
                        try
                        {
                            string alg = hkArgs.HostKeyName;
                            var check = FunkenwerkFtpSecurity.VerifyAndPinHostKey(
                                host,
                                port,
                                alg,
                                hkArgs.HostKey,
                                trustNewKeys: false,
                                confirmNewKeyCallback: fp => Dispatcher.Invoke(() =>
                                    FtpModalDialogs.ShowConfirmDialog(
                                        this,
                                        _isDarkMode,
                                        "SSH-Host-Key prüfen",
                                        $"Neuer Server-Schlüssel für '{host}:{port}' ({alg}):\n\n{fp}\n\nDiesem Fingerabdruck vertrauen und dauerhaft pinnen?",
                                        "Vertrauen & Pinnen",
                                        isDanger: false)));

                            _lastPinnedFingerprint = check.FingerprintSha256;
                            if (check.IsChanged)
                            {
                                hostKeyChangedAbort = true;
                                hkArgs.CanTrust = false;
                            }
                            else if (!check.IsTrusted)
                            {
                                hostKeyRejectedAbort = true;
                                hkArgs.CanTrust = false;
                            }
                            else
                            {
                                hkArgs.CanTrust = true;
                            }
                        }
                        catch (Exception hkEx)
                        {
                            hostKeyVaultError = hkEx.Message;
                            hkArgs.CanTrust = false;
                        }
                    };

                    try
                    {
                        await Task.Run(() => sftp.Connect());
                    }
                    catch
                    {
                        sftp.Dispose();
                        if (!string.IsNullOrEmpty(hostKeyVaultError))
                        {
                            StatusText.Text = $"🚨 Host-Key-Speicher blockiert: {hostKeyVaultError}";
                            return;
                        }
                        if (hostKeyChangedAbort)
                        {
                            StatusText.Text = "🚨 SICHERHEITSWARNUNG: Server-Host-Key hat sich geändert (MITM-Schutz hat Verbindung blockiert)!";
                            return;
                        }
                        if (hostKeyRejectedAbort)
                        {
                            StatusText.Text = "Verbindung abgebrochen (Server-Host-Key nicht bestätigt).";
                            return;
                        }
                        throw;
                    }

                    if (hostKeyChangedAbort || hostKeyRejectedAbort || !string.IsNullOrEmpty(hostKeyVaultError))
                    {
                        sftp.Dispose();
                        StatusText.Text = hostKeyChangedAbort
                            ? "🚨 SICHERHEITSWARNUNG: Server-Host-Key hat sich geändert (MITM-Schutz hat Verbindung blockiert)!"
                            : $"🚨 Verbindung blockiert: {hostKeyVaultError ?? "Host-Key abgelehnt."}";
                        return;
                    }

                    _sftpClient = sftp;
                    OpenSshBtn.Visibility = Visibility.Visible;
                    ConnectBtn.Content = "TRENNEN";
                    ConnectBtn.Background = BrushHex("#EF4444");
                    ConnectBtn.Foreground = Brushes.White;
                    StatusText.Text = $"🔒 SFTP Verbunden ({user}@{host}) · {_lastPinnedFingerprint}";

                    string startRemote = string.IsNullOrWhiteSpace(RemotePathBox.Text) ? "/" : RemotePathBox.Text.Trim();
                    await NavigateRemoteDirectoryAsync(startRemote);
                }
                else
                {
                    bool isFtps = _activeProtocol == "FTPS";
                    var cfg = BuildFtpConfig(_activeProtocol);
                    var ftp = new AsyncFtpClient(host, new NetworkCredential(user, pass), port, cfg);
                    bool certChangedAbort = false;
                    bool certRejectedAbort = false;
                    string? certVaultError = null;

                    if (isFtps)
                    {
                        ftp.ValidateCertificate += (control, certArgs) =>
                        {
                            try
                            {
                                if (certArgs.Certificate == null)
                                {
                                    certRejectedAbort = true;
                                    certArgs.Accept = false;
                                    return;
                                }

                                byte[] rawCert = certArgs.Certificate.GetRawCertData();
                                SslPolicyErrors policyErr = certArgs.PolicyErrors;
                                var check = FunkenwerkFtpSecurity.VerifyAndPinHostKey(
                                    host,
                                    port,
                                    "ftps-x509-sha256",
                                    rawCert,
                                    trustNewKeys: false,
                                    confirmNewKeyCallback: fp =>
                                    {
                                        // Prompt user to confirm fingerprint on unknown or self-signed certificates
                                        string tlsStatus = policyErr == SslPolicyErrors.None
                                            ? "Gültige CA-Zertifikatskette (erstmalige Verbindung)"
                                            : $"Selbstsigniert / Unbekannte CA ({policyErr})";
                                        return Dispatcher.Invoke(() => FtpModalDialogs.ShowConfirmDialog(
                                            this,
                                            _isDarkMode,
                                            "FTPS-Zertifikat prüfen",
                                            $"FTPS-Server '{host}:{port}' präsentiert folgendes TLS-Zertifikat:\nStatus: {tlsStatus}\n\n{fp}\n\nDiesem Zertifikat vertrauen und SHA-256-Pin speichern?",
                                            "Vertrauen & Pinnen",
                                            isDanger: policyErr != SslPolicyErrors.None));
                                    });

                                _lastPinnedFingerprint = check.FingerprintSha256;
                                if (check.IsChanged)
                                {
                                    certChangedAbort = true;
                                    certArgs.Accept = false;
                                }
                                else if (!check.IsTrusted)
                                {
                                    certRejectedAbort = true;
                                    certArgs.Accept = false;
                                }
                                else
                                {
                                    certArgs.Accept = true;
                                }
                            }
                            catch (Exception cEx)
                            {
                                certVaultError = cEx.Message;
                                certArgs.Accept = false;
                            }
                        };
                    }

                    try
                    {
                        // Strictly call Connect() (NEVER AutoConnect(), which silently downgrades to plaintext FTP!)
                        await ftp.Connect();
                    }
                    catch
                    {
                        try { ftp.Dispose(); } catch { }
                        if (!string.IsNullOrEmpty(certVaultError))
                        {
                            StatusText.Text = $"🚨 Zertifikats-Speicher blockiert: {certVaultError}";
                            return;
                        }
                        if (certChangedAbort)
                        {
                            StatusText.Text = "🚨 SICHERHEITSWARNUNG: FTPS-Zertifikat hat sich geändert (MITM-Schutz hat Verbindung blockiert)!";
                            return;
                        }
                        if (certRejectedAbort)
                        {
                            StatusText.Text = "Verbindung abgebrochen (FTPS-Zertifikat abgelehnt).";
                            return;
                        }
                        throw;
                    }

                    _ftpClient = ftp;
                    OpenSshBtn.Visibility = Visibility.Visible;

                    ConnectBtn.Content = "TRENNEN";
                    ConnectBtn.Background = BrushHex("#EF4444");
                    ConnectBtn.Foreground = Brushes.White;
                    StatusText.Text = isFtps
                        ? $"🔒 FTPS Verbunden ({user}@{host}:{port}) · {_lastPinnedFingerprint}"
                        : $"🔓 FTP (Unverschlüsselt) · {user}@{host}:{port}";

                    string startRemote = string.IsNullOrWhiteSpace(RemotePathBox.Text) ? "/" : RemotePathBox.Text.Trim();
                    await NavigateRemoteDirectoryAsync(startRemote);
                }
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Verbindung fehlgeschlagen: {ex.Message}";
            }
            finally
            {
                ConnectBtn.IsEnabled = true;
            }
        }

        private async Task DisconnectServerAsync()
        {
            CleanupLiveEditWatchersAndFiles();

            await _remoteIoGate.WaitAsync();
            try
            {
                if (_sftpClient != null)
                {
                    var sftp = _sftpClient;
                    _sftpClient = null;
                    await Task.Run(() =>
                    {
                        try { sftp.Disconnect(); } catch { }
                        try { sftp.Dispose(); } catch { }
                    });
                }
                if (_ftpClient != null)
                {
                    var ftp = _ftpClient;
                    _ftpClient = null;
                    try { await ftp.Disconnect(); } catch { }
                    try { ftp.Dispose(); } catch { }
                }
            }
            catch { }
            finally
            {
                _remoteIoGate.Release();
            }

            _remoteCurrentItems.Clear();
            RemoteFilesList.ItemsSource = null;
            OpenSshBtn.Visibility = Visibility.Collapsed;
            ConnectBtn.Content = "⚡ VERBINDEN";
            ConnectBtn.Background = BrushHex("#C5A059");
            ConnectBtn.Foreground = BrushHex("#0F1117");
            StatusText.Text = "🔒 Verbindung getrennt.";
        }

        private void OpenSsh_Click(object sender, RoutedEventArgs e)
        {
            string remoteDir = string.IsNullOrWhiteSpace(RemotePathBox.Text) ? "/" : RemotePathBox.Text.Trim();
            LaunchSshInRemoteDirectory(remoteDir);
        }

        private void MenuRemoteOpenSshHere_Click(object sender, RoutedEventArgs e)
        {
            var selected = GetSelectedActionableRemoteItems().FirstOrDefault();
            string targetDir = (selected != null && selected.IsDirectory)
                ? selected.FullPath
                : (string.IsNullOrWhiteSpace(RemotePathBox.Text) ? "/" : RemotePathBox.Text.Trim());
            LaunchSshInRemoteDirectory(targetDir);
        }

        private void LaunchSshInRemoteDirectory(string remoteDir)
        {
            if (!IsConnected()) return;
            try
            {
                if (!string.IsNullOrEmpty(remoteDir) && remoteDir.IndexOfAny(new[] { '\r', '\n', '\0' }) >= 0)
                {
                    StatusText.Text = "⚠ Ungültige Steuerzeichen im Server-Pfad blockiert.";
                    return;
                }

                string[] candidatePaths =
                {
                    Path.GetFullPath(Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "..", "Funkenwerk SSH", "FunkenwerkSsh.exe")),
                    @"C:\Users\konta\Desktop\Funkenwerk Collection\Funkenwerk SSH\FunkenwerkSsh.exe"
                };

                // Verify FunkenwerkSsh.exe exists BEFORE writing handoff_ticket.dpapi so no orphan ticket remains on disk
                string? sshExe = candidatePaths.FirstOrDefault(File.Exists);
                if (string.IsNullOrEmpty(sshExe))
                {
                    StatusText.Text = "⚠ FunkenwerkSsh.exe wurde nicht gefunden.";
                    return;
                }

                string host = FunkenwerkFtpSecurity.SanitizeHost(HostBox.Text);
                int port = int.TryParse(PortBox.Text, out int pt) ? pt : 22;
                if (port == 21) port = 22;
                string user = UserBox.Text.Trim();
                string pass = GetPasswordValue();
                string profileName = (ProfilesCombo.SelectedItem as FtpConnectionProfile)?.Name ?? $"{user}@{host}";

                string nonce = FunkenwerkFtpSecurity.WriteSshHandoffTicket(
                    profileName,
                    host,
                    port,
                    user,
                    pass,
                    _selectedPrivateKeyPath,
                    remoteDir);

                try
                {
                    var psi = new ProcessStartInfo
                    {
                        FileName = sshExe,
                        WorkingDirectory = Path.GetDirectoryName(sshExe) ?? "",
                        UseShellExecute = false
                    };
                    psi.ArgumentList.Add("--handoff");
                    psi.ArgumentList.Add(nonce);
                    Process.Start(psi);
                }
                catch
                {
                    FunkenwerkFtpSecurity.WipeAndDeleteSshHandoffTicket();
                    throw;
                }

                StatusText.Text = $"🔒 SSH-Verbindung in '{remoteDir}' gestartet.";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ SSH-Start fehlgeschlagen: {ex.Message}";
            }
        }

        // =====================================================================
        // REMOTE SERVER DIRECTORY & FILE OPERATIONS
        // =====================================================================
        private static string CombineRemotePath(string parentDir, string childName)
        {
            string cleanChild = (childName ?? "").Trim().TrimStart('/');
            if (string.IsNullOrEmpty(parentDir) || parentDir == "/")
                return "/" + cleanChild;
            return parentDir.TrimEnd('/') + "/" + cleanChild;
        }

        private static string GetRemoteParentPath(string remotePath)
        {
            string cur = (remotePath ?? "").Trim().Replace('\\', '/').TrimEnd('/');
            if (string.IsNullOrEmpty(cur) || cur == "/") return "/";
            int idx = cur.LastIndexOf('/');
            return idx <= 0 ? "/" : cur.Substring(0, idx);
        }

        private async Task NavigateRemoteDirectoryAsync(string remotePath)
        {
            if (!IsConnected()) return;
            await _remoteIoGate.WaitAsync();
            try
            {
                string target = string.IsNullOrWhiteSpace(remotePath) ? "/" : remotePath.Replace('\\', '/');
                if (target.Length > 1) target = target.TrimEnd('/');
                var items = new List<FileEntryItem>();

                if (target != "/")
                {
                    items.Add(new FileEntryItem
                    {
                        Name = "..",
                        FullPath = GetRemoteParentPath(target),
                        IsDirectory = true,
                        IsParentNav = true
                    });
                }

                if (_sftpClient != null && _sftpClient.IsConnected)
                {
                    var listing = await Task.Run(() => _sftpClient.ListDirectory(target).ToList());
                    foreach (var entry in listing.Where(x => x.Name != "." && x.Name != ".."))
                    {
                        bool isSym = entry.IsSymbolicLink;
                        items.Add(new FileEntryItem
                        {
                            Name = entry.Name,
                            FullPath = entry.FullName,
                            IsSymbolicLink = isSym,
                            IsDirectory = !isSym && entry.IsDirectory,
                            Size = entry.Length,
                            Modified = entry.LastWriteTime,
                            Permissions = FormatSftpPermissions(entry)
                        });
                    }
                }
                else if (_ftpClient != null && _ftpClient.IsConnected)
                {
                    var listing = await _ftpClient.GetListing(target);
                    foreach (var entry in listing.Where(x => x.Name != "." && x.Name != ".."))
                    {
                        bool isSym = entry.Type == FtpObjectType.Link;
                        items.Add(new FileEntryItem
                        {
                            Name = entry.Name,
                            FullPath = entry.FullName,
                            IsSymbolicLink = isSym,
                            IsDirectory = !isSym && entry.Type == FtpObjectType.Directory,
                            Size = entry.Size,
                            Modified = entry.Modified,
                            Permissions = entry.RawPermissions ?? ""
                        });
                    }
                }

                RemotePathBox.Text = target;
                _remoteCurrentItems = SortEntryList(items, _remoteSortColumn, _remoteSortAsc);
                RemoteFilesList.ItemsSource = _remoteCurrentItems;
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Server-Verzeichnis: {ex.Message}";
            }
            finally
            {
                _remoteIoGate.Release();
            }
        }

        private static string FormatSftpPermissions(Renci.SshNet.Sftp.ISftpFile f)
        {
            char d = f.IsSymbolicLink ? 'l' : (f.IsDirectory ? 'd' : '-');
            char ur = f.OwnerCanRead ? 'r' : '-';
            char uw = f.OwnerCanWrite ? 'w' : '-';
            char ux = f.OwnerCanExecute ? 'x' : '-';
            char gr = f.GroupCanRead ? 'r' : '-';
            char gw = f.GroupCanWrite ? 'w' : '-';
            char gx = f.GroupCanExecute ? 'x' : '-';
            char or = f.OthersCanRead ? 'r' : '-';
            char ow = f.OthersCanWrite ? 'w' : '-';
            char ox = f.OthersCanExecute ? 'x' : '-';
            return new string(new[] { d, ur, uw, ux, gr, gw, gx, or, ow, ox });
        }

        private async void RemoteUp_Click(object sender, RoutedEventArgs e)
        {
            await NavigateRemoteDirectoryAsync(GetRemoteParentPath(RemotePathBox.Text));
        }

        private async void RemoteRefresh_Click(object sender, RoutedEventArgs e) =>
            await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());

        private async void RemotePathBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter) await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
        }

        private async void RemoteFilesList_MouseDoubleClick(object sender, MouseButtonEventArgs e)
        {
            if (IsInteractiveChromeElement(e.OriginalSource as DependencyObject)) return;
            if (FindAncestor<ListViewItem>(e.OriginalSource as DependencyObject) == null) return;
            await OpenSelectedRemoteItemSmartAsync();
        }

        private async void MenuRemoteOpen_Click(object sender, RoutedEventArgs e) => await OpenSelectedRemoteItemSmartAsync();

        private async Task OpenSelectedRemoteItemSmartAsync()
        {
            if (RemoteFilesList.SelectedItem is not FileEntryItem item) return;
            if (item.IsDirectory)
            {
                await NavigateRemoteDirectoryAsync(item.FullPath);
                return;
            }

            if (FtpTextBufferCodec.IsImageFileName(item.Name))
            {
                var imgs = _remoteCurrentItems.Where(x => !x.IsDirectory && FtpTextBufferCodec.IsImageFileName(x.Name)).ToList();
                int startIdx = Math.Max(0, imgs.FindIndex(x => x.FullPath == item.FullPath));
                FtpModalDialogs.ShowImageLightbox(
                    this,
                    _isDarkMode,
                    imgs,
                    startIdx,
                    DownloadRemoteBytesAsync,
                    async imgItem => await DownloadSingleFileAsync(imgItem),
                    "← Herunterladen");
                return;
            }

            if (FtpTextBufferCodec.IsLikelyTextFileName(item.Name))
            {
                await OpenRemoteFileInEditorAsync(item);
                return;
            }

            if (item.Size <= 256 * 1024 && !Path.HasExtension(item.Name))
            {
                await OpenRemoteFileInEditorAsync(item);
                return;
            }

            await DownloadSingleFileAsync(item);
        }

        // =====================================================================
        // REMOTE FILE EDITING (BUILT-IN STUDIO EDITOR + EXTERNAL AUTO-UPLOAD)
        // =====================================================================
        private async Task<byte[]> DownloadRemoteBytesAsync(FileEntryItem item)
        {
            await _remoteIoGate.WaitAsync();
            try
            {
                using var ms = new MemoryStream();
                if (_sftpClient != null && _sftpClient.IsConnected)
                {
                    await Task.Run(() => _sftpClient.DownloadFile(item.FullPath, ms));
                }
                else if (_ftpClient != null && _ftpClient.IsConnected)
                {
                    await _ftpClient.DownloadStream(ms, item.FullPath);
                }
                return ms.ToArray();
            }
            finally
            {
                _remoteIoGate.Release();
            }
        }

        private async Task UploadBytesToRemoteFileAsync(string remoteFilePath, byte[] bytes, bool refreshListing)
        {
            if (!IsConnected())
                throw new InvalidOperationException("Keine aktive Serververbindung.");

            await _remoteIoGate.WaitAsync();
            try
            {
                if (_sftpClient != null && _sftpClient.IsConnected)
                {
                    await Task.Run(() =>
                    {
                        using var ms = new MemoryStream(bytes, writable: false);
                        _sftpClient.UploadFile(ms, remoteFilePath, canOverride: true);
                    });
                }
                else if (_ftpClient != null && _ftpClient.IsConnected)
                {
                    using var ms = new MemoryStream(bytes, writable: false);
                    await _ftpClient.UploadStream(ms, remoteFilePath, FtpRemoteExists.Overwrite, createRemoteDir: true);
                }
            }
            finally
            {
                _remoteIoGate.Release();
            }

            if (refreshListing)
                await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
        }

        private async void RemoteEdit_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            var item = GetSelectedActionableRemoteItems().FirstOrDefault(x => !x.IsDirectory);
            if (item == null)
            {
                StatusText.Text = "⚠ Bitte eine Server-Datei zum Bearbeiten auswählen.";
                return;
            }
            await OpenRemoteFileInEditorAsync(item);
        }

        private async Task OpenRemoteFileInEditorAsync(FileEntryItem item)
        {
            if (!IsConnected() || item.IsDirectory) return;
            try
            {
                if (item.Size > 15L * 1024 * 1024)
                {
                    if (!FtpModalDialogs.ShowConfirmDialog(this, _isDarkMode, "Große Server-Datei",
                            $"'{item.Name}' ist {item.Size / (1024.0 * 1024):F1} MB groß. Trotzdem im Text-Editor laden?",
                            "Laden", isDanger: false))
                        return;
                }

                StatusText.Text = $"⏳ Lade '{item.Name}' in den Editor ...";
                byte[] raw = await DownloadRemoteBytesAsync(item);

                if (!FtpTextBufferCodec.LooksLikeTextBytes(raw))
                {
                    if (!FtpModalDialogs.ShowConfirmDialog(this, _isDarkMode, "Binärdatei erkannt",
                            $"'{item.Name}' scheint eine Binärdatei zu sein. Trotzdem als Text öffnen?",
                            "Trotzdem öffnen", isDanger: false))
                    {
                        StatusText.Text = "Abgebrochen.";
                        return;
                    }
                }

                // Default to LF (\n) for server files so Linux scripts & configs stay 100% clean
                var doc = FtpTextBufferCodec.Decode(raw, defaultUseCrLf: false);
                string remotePath = item.FullPath;

                var ed = new FtpFileEditorWindow(
                    this,
                    _isDarkMode,
                    isRemote: true,
                    item.Name,
                    remotePath,
                    doc,
                    async updatedBytes =>
                    {
                        await UploadBytesToRemoteFileAsync(remotePath, updatedBytes, refreshListing: true);
                        StatusText.Text = $"✓ Auf Server gespeichert: {item.Name} ({DateTime.Now:HH:mm:ss})";
                    });
                StatusText.Text = $"✎ Bearbeite Server-Datei: {remotePath}";
                ed.Show();
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Server-Editor: {ex.Message}";
            }
        }

        private async void MenuRemoteEditExternal_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            var item = GetSelectedActionableRemoteItems().FirstOrDefault(x => !x.IsDirectory && !x.IsSymbolicLink);
            if (item == null) return;

            try
            {
                string liveRoot = Path.Combine(FunkenwerkFtpSecurity.VaultDir, "LiveEdit");
                Directory.CreateDirectory(liveRoot);
                FunkenwerkFtpSecurity.LockDownDirectoryAcl(liveRoot);

                string subHash = Convert.ToHexString(SHA256.HashData(System.Text.Encoding.UTF8.GetBytes(GetRemoteParentPath(item.FullPath))))[..12];
                string stageDir = Path.Combine(liveRoot, subHash);
                Directory.CreateDirectory(stageDir);
                FunkenwerkFtpSecurity.LockDownDirectoryAcl(stageDir);

                string localStageFile = FunkenwerkFtpSecurity.ValidateSafeLocalPath(stageDir, item.Name);

                // Dispose any prior watcher for the same staged file before overwriting so duplicate watchers never accumulate
                if (_liveEditWatchers.TryGetValue(localStageFile, out var oldWatcher))
                {
                    try { oldWatcher.EnableRaisingEvents = false; oldWatcher.Dispose(); } catch { }
                    _liveEditWatchers.Remove(localStageFile);
                }

                StatusText.Text = $"⏳ Bereite Live-Bearbeitung für '{item.Name}' vor ...";
                byte[] content = await DownloadRemoteBytesAsync(item);
                await File.WriteAllBytesAsync(localStageFile, content);
                FunkenwerkFtpSecurity.LockDownFileAcl(localStageFile);
                _liveEditLastHashes[localStageFile] = SHA256.HashData(content);

                string remoteTarget = item.FullPath;
                var watcher = new FileSystemWatcher(stageDir, item.Name)
                {
                    NotifyFilter = NotifyFilters.LastWrite | NotifyFilters.Size | NotifyFilters.FileName,
                    EnableRaisingEvents = true
                };

                async void OnExternalFileSaved(object s, FileSystemEventArgs fse)
                {
                    try
                    {
                        await Task.Delay(320); // Wait for IDE atomic write/rename completion
                        if (!File.Exists(localStageFile)) return;

                        lock (_liveEditDebounceUtc)
                        {
                            if (_liveEditDebounceUtc.TryGetValue(localStageFile, out var last) &&
                                (DateTime.UtcNow - last).TotalMilliseconds < 250)
                                return;
                            _liveEditDebounceUtc[localStageFile] = DateTime.UtcNow;
                        }

                        byte[] newBytes = await ReadFileWithRetryAsync(localStageFile);
                        byte[] newHash = SHA256.HashData(newBytes);
                        if (_liveEditLastHashes.TryGetValue(localStageFile, out var prevHash) &&
                            CryptographicOperations.FixedTimeEquals(prevHash, newHash))
                            return;

                        _liveEditLastHashes[localStageFile] = newHash;
                        await Dispatcher.InvokeAsync(async () =>
                        {
                            if (!IsConnected()) return;
                            try
                            {
                                await UploadBytesToRemoteFileAsync(remoteTarget, newBytes, refreshListing: true);
                                StatusText.Text = $"✓ Auto-Upload auf Server gespeichert: {item.Name} ({DateTime.Now:HH:mm:ss})";
                            }
                            catch (Exception upEx)
                            {
                                StatusText.Text = $"⚠ Auto-Upload fehlgeschlagen: {upEx.Message}";
                            }
                        });
                    }
                    catch { }
                }

                watcher.Changed += OnExternalFileSaved;
                watcher.Created += OnExternalFileSaved;
                watcher.Renamed += (s, re) => OnExternalFileSaved(s, re);
                _liveEditWatchers[localStageFile] = watcher;

                LaunchExternalEditorSmart(localStageFile);
                StatusText.Text = $"💻 Live-Sync aktiv für '{item.Name}' · Jedes Speichern lädt automatisch auf den Server hoch.";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Externer Editor: {ex.Message}";
            }
        }

        private static async Task<byte[]> ReadFileWithRetryAsync(string path)
        {
            for (int attempt = 0; attempt < 5; attempt++)
            {
                try
                {
                    await using var fs = new FileStream(path, FileMode.Open, FileAccess.Read, FileShare.ReadWrite);
                    using var ms = new MemoryStream();
                    await fs.CopyToAsync(ms);
                    return ms.ToArray();
                }
                catch (IOException) when (attempt < 4)
                {
                    await Task.Delay(120);
                }
            }
            return await File.ReadAllBytesAsync(path);
        }

        private static readonly HashSet<string> SafePhpStormExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".php", ".phtml", ".html", ".htm", ".twig", ".vue", ".svelte",
            ".css", ".scss", ".sass", ".less", ".ts", ".tsx", ".jsx",
            ".json", ".xml", ".yml", ".yaml", ".sql", ".md", ".env", ".htaccess", ".ini", ".conf"
        };

        /// <summary>
        /// Builds a guaranteed RCE-safe ProcessStartInfo that launches an explicit text editor executable
        /// with UseShellExecute = false and ArgumentList (never ShellExecute on .bat/.cmd/.ps1/.vbs/.js/.exe/.reg/.hta!).
        /// </summary>
        public static ProcessStartInfo BuildSafeExternalEditorStartInfo(string filePath, string? preferredEditorExecutable = null)
        {
            if (string.IsNullOrWhiteSpace(filePath))
                throw new ArgumentException("Ungültiger Dateipfad für externen Editor.", nameof(filePath));

            string fullTarget = Path.GetFullPath(filePath);
            string? resolvedEditor = null;

            if (!string.IsNullOrWhiteSpace(preferredEditorExecutable) &&
                preferredEditorExecutable.EndsWith(".exe", StringComparison.OrdinalIgnoreCase) &&
                File.Exists(preferredEditorExecutable))
            {
                resolvedEditor = Path.GetFullPath(preferredEditorExecutable);
            }

            if (resolvedEditor == null)
            {
                string localApp = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
                string progFiles = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles);
                string progFilesX86 = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86);
                string ext = Path.GetExtension(fullTarget);

                var candidates = new List<string>
                {
                    Path.Combine(localApp, "Programs", "Microsoft VS Code", "Code.exe"),
                    Path.Combine(progFiles, "Microsoft VS Code", "Code.exe"),
                    Path.Combine(progFiles, "Notepad++", "notepad++.exe"),
                    Path.Combine(progFilesX86, "Notepad++", "notepad++.exe")
                };

                if (SafePhpStormExtensions.Contains(ext) || fullTarget.EndsWith(".blade.php", StringComparison.OrdinalIgnoreCase))
                {
                    candidates.Add(Path.Combine(localApp, "Programs", "PhpStorm", "bin", "phpstorm64.exe"));
                    try
                    {
                        string jbDir = Path.Combine(progFiles, "JetBrains");
                        if (Directory.Exists(jbDir))
                        {
                            foreach (string sub in Directory.GetDirectories(jbDir, "PhpStorm*"))
                                candidates.Add(Path.Combine(sub, "bin", "phpstorm64.exe"));
                        }
                    }
                    catch { }
                }

                resolvedEditor = candidates.FirstOrDefault(File.Exists);
            }

            if (string.IsNullOrEmpty(resolvedEditor))
            {
                string sysNotepad = Path.Combine(Environment.SystemDirectory, "notepad.exe");
                if (!File.Exists(sysNotepad))
                    sysNotepad = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Windows), "notepad.exe");
                resolvedEditor = sysNotepad;
            }

            var psi = new ProcessStartInfo
            {
                FileName = resolvedEditor,
                UseShellExecute = false
            };
            psi.ArgumentList.Add(fullTarget);
            return psi;
        }

        private static void LaunchExternalEditorSmart(string filePath)
        {
            var psi = BuildSafeExternalEditorStartInfo(filePath);
            Process.Start(psi);
        }

        private void CleanupLiveEditWatchersAndFiles()
        {
            foreach (var kv in _liveEditWatchers)
            {
                try { kv.Value.EnableRaisingEvents = false; kv.Value.Dispose(); } catch { }
            }
            _liveEditWatchers.Clear();
            _liveEditLastHashes.Clear();
            _liveEditDebounceUtc.Clear();

            PurgeStaleLiveEditFiles();
        }

        // =====================================================================
        // REMOTE CREATE / RENAME / DUPLICATE / CHMOD / CLIPBOARD / DELETE
        // =====================================================================
        private async void RemoteNewFile_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            string? fileName = FtpModalDialogs.ShowInputPrompt(this, _isDarkMode, "Neue Datei auf Server anlegen", "neue-datei.php", "Anlegen & Bearbeiten", selectFileNameStemOnly: true);
            if (string.IsNullOrWhiteSpace(fileName)) return;

            try
            {
                string cleanName = FunkenwerkFtpSecurity.ValidateSafeRemoteEntryName(fileName);
                string remoteTarget = CombineRemotePath(RemotePathBox.Text.Trim(), cleanName);
                await UploadBytesToRemoteFileAsync(remoteTarget, Array.Empty<byte>(), refreshListing: true);
                var created = _remoteCurrentItems.FirstOrDefault(x => !x.IsDirectory && x.Name == cleanName)
                              ?? new FileEntryItem { Name = cleanName, FullPath = remoteTarget, IsDirectory = false };
                await OpenRemoteFileInEditorAsync(created);
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Neue Datei: {ex.Message}";
            }
        }

        private async void RemoteMkdir_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            string? folderName = FtpModalDialogs.ShowInputPrompt(this, _isDarkMode, "Neuen Ordner auf Server erstellen", "Neuer_Ordner", "Erstellen");
            if (string.IsNullOrWhiteSpace(folderName)) return;

            try
            {
                string cleanFolder = FunkenwerkFtpSecurity.ValidateSafeRemoteEntryName(folderName);
                string newFolder = CombineRemotePath(RemotePathBox.Text.Trim(), cleanFolder);
                await _remoteIoGate.WaitAsync();
                try
                {
                    if (_sftpClient != null && _sftpClient.IsConnected)
                        await Task.Run(() => _sftpClient.CreateDirectory(newFolder));
                    else if (_ftpClient != null && _ftpClient.IsConnected)
                        await _ftpClient.CreateDirectory(newFolder);
                }
                finally
                {
                    _remoteIoGate.Release();
                }

                await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
                StatusText.Text = $"✓ Server-Ordner erstellt: {cleanFolder}";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ {ex.Message}";
            }
        }

        private async void MenuRemoteRename_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            var item = GetSelectedActionableRemoteItems().FirstOrDefault();
            if (item == null) return;

            string? input = FtpModalDialogs.ShowInputPrompt(this, _isDarkMode, "Auf Server umbenennen / verschieben", item.Name, "Übernehmen", selectFileNameStemOnly: !item.IsDirectory);
            if (string.IsNullOrWhiteSpace(input) || string.Equals(input, item.Name, StringComparison.Ordinal)) return;

            try
            {
                string destRemote = input.StartsWith("/", StringComparison.Ordinal)
                    ? FunkenwerkFtpSecurity.ValidateSafeRemoteTargetPath(input)
                    : CombineRemotePath(GetRemoteParentPath(item.FullPath), FunkenwerkFtpSecurity.ValidateSafeRemoteEntryName(input));

                await MoveRemoteEntryAsync(item, destRemote, refreshAfter: true);
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Umbenennen blockiert: {ex.Message}";
            }
        }

        private async Task MoveRemoteEntryAsync(FileEntryItem item, string destRemotePath, bool refreshAfter)
        {
            if (!IsConnected() || string.Equals(item.FullPath, destRemotePath, StringComparison.Ordinal)) return;
            try
            {
                await _remoteIoGate.WaitAsync();
                try
                {
                    if (_sftpClient != null && _sftpClient.IsConnected)
                    {
                        await Task.Run(() => _sftpClient.RenameFile(item.FullPath, destRemotePath));
                    }
                    else if (_ftpClient != null && _ftpClient.IsConnected)
                    {
                        if (item.IsDirectory) await _ftpClient.MoveDirectory(item.FullPath, destRemotePath);
                        else await _ftpClient.MoveFile(item.FullPath, destRemotePath);
                    }
                }
                finally
                {
                    _remoteIoGate.Release();
                }

                StatusText.Text = $"✓ Verschoben / umbenannt: {destRemotePath}";
                if (refreshAfter)
                    await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Verschieben fehlgeschlagen: {ex.Message}";
            }
        }

        private async void MenuRemoteDuplicate_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            var item = GetSelectedActionableRemoteItems().FirstOrDefault(x => !x.IsDirectory);
            if (item == null) return;

            try
            {
                string ext = Path.GetExtension(item.Name);
                string stem = Path.GetFileNameWithoutExtension(item.Name);
                string copyName = $"{stem}_kopie{ext}";
                int n = 2;
                while (_remoteCurrentItems.Any(x => string.Equals(x.Name, copyName, StringComparison.OrdinalIgnoreCase)))
                    copyName = $"{stem}_kopie_{n++}{ext}";

                StatusText.Text = $"⏳ Dupliziere '{item.Name}' ...";
                byte[] data = await DownloadRemoteBytesAsync(item);
                string destPath = CombineRemotePath(GetRemoteParentPath(item.FullPath), copyName);
                await UploadBytesToRemoteFileAsync(destPath, data, refreshListing: true);
                StatusText.Text = $"✓ Auf Server dupliziert: {copyName}";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Duplizieren fehlgeschlagen: {ex.Message}";
            }
        }

        private async void MenuRemoteChmod_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            var selected = GetSelectedActionableRemoteItems();
            if (selected.Count == 0) return;

            string label = selected.Count == 1 ? selected[0].Name : $"{selected.Count} Elemente";
            bool anyDirs = selected.Any(x => x.IsDirectory);
            var chmod = FtpModalDialogs.ShowChmodDialog(this, _isDarkMode, label, selected[0].Permissions, anyDirs);
            if (chmod == null) return;

            try
            {
                StatusText.Text = $"⏳ Setze Berechtigungen ({chmod.Value.OctalString}) ...";
                await _remoteIoGate.WaitAsync();
                try
                {
                    int intOctal = int.Parse(chmod.Value.OctalString);
                    foreach (var it in selected)
                    {
                        await ApplyChmodInternalAsync(it.FullPath, it.IsDirectory, it.IsSymbolicLink, chmod.Value.OctalValue, intOctal, chmod.Value.Recursive);
                    }
                }
                finally
                {
                    _remoteIoGate.Release();
                }

                await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
                StatusText.Text = $"✓ Berechtigungen auf {chmod.Value.OctalString} gesetzt.";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ CHMOD fehlgeschlagen: {ex.Message}";
            }
        }

        public static void ExecuteSafeRemoteChmodTree(
            string remotePath,
            bool isDir,
            bool isSymlink,
            short fileOctal,
            bool recursive,
            Func<string, IEnumerable<RemoteNodeInfo>> listDir,
            Action<string, short> setPermissions)
        {
            if (isSymlink) return;
            short modeToApply = (isDir && recursive)
                ? FtpTextBufferCodec.EnsureDirectoryTraverseBits(fileOctal)
                : fileOctal;
            setPermissions(remotePath, modeToApply);

            if (isDir && recursive)
            {
                foreach (var sub in listDir(remotePath))
                {
                    if (sub.Name == "." || sub.Name == "..") continue;
                    if (sub.IsSymbolicLink) continue;
                    ExecuteSafeRemoteChmodTree(sub.FullPath, sub.IsDirectory, sub.IsSymbolicLink, fileOctal, true, listDir, setPermissions);
                }
            }
        }

        private async Task ApplyChmodInternalAsync(string remotePath, bool isDir, bool isSymlink, short octalShort, int octalDecimalDigits, bool recursive)
        {
            if (isSymlink) return;

            if (_sftpClient != null && _sftpClient.IsConnected)
            {
                await Task.Run(() => ApplySftpChmodSync(remotePath, isDir, isSymlink, octalShort, recursive));
            }
            else if (_ftpClient != null && _ftpClient.IsConnected)
            {
                int modeDigits = (isDir && recursive)
                    ? int.Parse(FtpTextBufferCodec.FormatOctalDigits(FtpTextBufferCodec.EnsureDirectoryTraverseBits(octalShort)))
                    : octalDecimalDigits;
                await _ftpClient.Chmod(remotePath, modeDigits);
                if (isDir && recursive)
                {
                    var subs = await _ftpClient.GetListing(remotePath);
                    foreach (var sub in subs.Where(x => x.Name != "." && x.Name != ".."))
                    {
                        if (sub.Type == FtpObjectType.Link) continue;
                        await ApplyChmodInternalAsync(sub.FullName, sub.Type == FtpObjectType.Directory, false, octalShort, octalDecimalDigits, true);
                    }
                }
            }
        }

        private void ApplySftpChmodSync(string remotePath, bool isDir, bool isSymlink, short octalShort, bool recursive)
        {
            if (_sftpClient == null) return;
            ExecuteSafeRemoteChmodTree(
                remotePath,
                isDir,
                isSymlink,
                octalShort,
                recursive,
                dir => _sftpClient.ListDirectory(dir).Select(x => new RemoteNodeInfo(x.Name, x.FullName, !x.IsSymbolicLink && x.IsDirectory, x.IsSymbolicLink, x.Length)),
                (p, mode) => _sftpClient.ChangePermissions(p, mode));
        }

        private async void MenuRemotePasteUpload_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected() || !Clipboard.ContainsFileDropList()) return;
            var files = Clipboard.GetFileDropList().Cast<string>().ToArray();
            if (files.Length == 0) return;

            string targetDir = RemotePathBox.Text.Trim();
            foreach (string p in files)
                await UploadPathRecursiveAsync(p, targetDir);

            await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
            StatusText.Text = $"✓ {files.Length} Element(e) aus Zwischenablage auf Server hochgeladen.";
        }

        private void MenuRemoteCopyPath_Click(object sender, RoutedEventArgs e)
        {
            var item = GetSelectedActionableRemoteItems().FirstOrDefault();
            string pathToCopy = item != null ? item.FullPath : RemotePathBox.Text.Trim();
            Clipboard.SetText(pathToCopy);
            StatusText.Text = $"🔗 Server-Pfad kopiert: {pathToCopy}";
        }

        private async void RemoteDelete_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            var selected = GetSelectedActionableRemoteItems();
            if (selected.Count == 0) return;

            string msg = selected.Count == 1
                ? $"Server-Element '{selected[0].Name}' endgültig vom Server löschen?"
                : $"{selected.Count} ausgewählte Elemente endgültig vom Server löschen?";
            if (!FtpModalDialogs.ShowConfirmDialog(this, _isDarkMode, "Auf Server löschen", msg, "Endgültig löschen", isDanger: true))
                return;

            foreach (var item in selected)
            {
                await DeleteRemoteRecursiveAsync(item.FullPath, item.IsDirectory, item.IsSymbolicLink);
            }
            await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
            StatusText.Text = $"🗑 {selected.Count} Server-Element(e) gelöscht.";
        }

        public static void ExecuteSafeRemoteDeleteTree(
            string dirPath,
            Func<string, IEnumerable<RemoteNodeInfo>> listDir,
            Action<string> deleteFileOrSymlink,
            Action<string> deleteEmptyDir)
        {
            foreach (var sub in listDir(dirPath))
            {
                if (sub.Name == "." || sub.Name == "..") continue;
                // CRITICAL: Check IsSymbolicLink BEFORE IsDirectory so symlinks are unlinked and NEVER recursed into!
                if (sub.IsSymbolicLink)
                {
                    deleteFileOrSymlink(sub.FullPath);
                }
                else if (sub.IsDirectory)
                {
                    ExecuteSafeRemoteDeleteTree(sub.FullPath, listDir, deleteFileOrSymlink, deleteEmptyDir);
                }
                else
                {
                    deleteFileOrSymlink(sub.FullPath);
                }
            }
            deleteEmptyDir(dirPath);
        }

        private async Task DeleteRemoteRecursiveAsync(string remotePath, bool isDir, bool isSymlink)
        {
            await _remoteIoGate.WaitAsync();
            try
            {
                if (_sftpClient != null && _sftpClient.IsConnected)
                {
                    await Task.Run(() =>
                    {
                        if (isSymlink) _sftpClient.DeleteFile(remotePath);
                        else if (isDir) DeleteSftpDirectorySync(remotePath);
                        else _sftpClient.DeleteFile(remotePath);
                    });
                }
                else if (_ftpClient != null && _ftpClient.IsConnected)
                {
                    if (isSymlink) await _ftpClient.DeleteFile(remotePath);
                    else if (isDir) await _ftpClient.DeleteDirectory(remotePath);
                    else await _ftpClient.DeleteFile(remotePath);
                }
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Löschen: {ex.Message}";
            }
            finally
            {
                _remoteIoGate.Release();
            }
        }

        private void DeleteSftpDirectorySync(string path)
        {
            if (_sftpClient == null) return;
            ExecuteSafeRemoteDeleteTree(
                path,
                dir => _sftpClient.ListDirectory(dir).Select(x => new RemoteNodeInfo(x.Name, x.FullName, !x.IsSymbolicLink && x.IsDirectory, x.IsSymbolicLink, x.Length)),
                fileOrLink => _sftpClient.DeleteFile(fileOrLink),
                emptyDir => _sftpClient.DeleteDirectory(emptyDir));
        }

        private void RemoteFilesList_ContextMenuOpening(object sender, ContextMenuEventArgs e)
        {
            bool connected = IsConnected();
            var selected = GetSelectedActionableRemoteItems();
            bool hasSingle = connected && selected.Count == 1;
            bool hasAny = connected && selected.Count > 0;
            bool hasSingleFile = hasSingle && !selected[0].IsDirectory;

            MenuRemoteEdit.IsEnabled = hasSingleFile;
            MenuRemoteEditExternal.IsEnabled = hasSingleFile;
            MenuRemoteOpen.IsEnabled = hasSingle || (connected && RemoteFilesList.SelectedItem is FileEntryItem p && p.IsParentNav);
            MenuRemoteDownload.IsEnabled = hasAny;
            MenuRemoteNewFile.IsEnabled = connected;
            MenuRemoteNewFolder.IsEnabled = connected;
            MenuRemoteRename.IsEnabled = hasSingle;
            MenuRemoteDuplicate.IsEnabled = hasSingleFile;
            MenuRemoteChmod.IsEnabled = hasAny;
            MenuRemotePasteUpload.IsEnabled = connected && Clipboard.ContainsFileDropList();
            MenuRemoteCopyPath.IsEnabled = connected;
            MenuRemoteOpenSsh.IsEnabled = connected;
            MenuRemoteRefresh.IsEnabled = connected;
            MenuRemoteDelete.IsEnabled = hasAny;
        }

        private async void RemoteFilesList_KeyDown(object sender, KeyEventArgs e)
        {
            if (!IsConnected()) return;

            if (e.Key == Key.F4 && (Keyboard.Modifiers & ModifierKeys.Shift) != 0)
            {
                e.Handled = true;
                RemoteNewFile_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.F4)
            {
                e.Handled = true;
                RemoteEdit_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.F2)
            {
                e.Handled = true;
                MenuRemoteRename_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.F5)
            {
                e.Handled = true;
                RemoteRefresh_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.F7)
            {
                e.Handled = true;
                RemoteMkdir_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.Delete)
            {
                e.Handled = true;
                RemoteDelete_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.Back)
            {
                e.Handled = true;
                RemoteUp_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.Enter)
            {
                e.Handled = true;
                await OpenSelectedRemoteItemSmartAsync();
            }
            else if (e.Key == Key.V && (Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                e.Handled = true;
                MenuRemotePasteUpload_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.C && (Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                e.Handled = true;
                MenuRemoteCopyPath_Click(sender, new RoutedEventArgs());
            }
            else if (e.Key == Key.A && (Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                e.Handled = true;
                RemoteFilesList.SelectedItems.Clear();
                foreach (var it in _remoteCurrentItems.Where(x => !x.IsParentNav))
                    RemoteFilesList.SelectedItems.Add(it);
            }
        }

        // =====================================================================
        // RECURSIVE UPLOAD & DOWNLOAD TRANSFERS
        // =====================================================================
        private async void UploadSelected_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            var selected = GetSelectedActionableLocalItems();
            if (selected.Count == 0) return;

            string remoteDir = RemotePathBox.Text.Trim();
            foreach (var item in selected)
            {
                await UploadPathRecursiveAsync(item.FullPath, remoteDir);
            }
            await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
        }

        private async Task UploadPathRecursiveAsync(string localPath, string remoteParentDir)
        {
            if (!IsConnected()) return;

            if (File.Exists(localPath))
            {
                await UploadSingleFileToDirAsync(localPath, remoteParentDir, refreshAfter: false);
            }
            else if (Directory.Exists(localPath))
            {
                string dirName = Path.GetFileName(localPath.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar));
                if (string.IsNullOrWhiteSpace(dirName)) return;

                string targetRemoteDir = CombineRemotePath(remoteParentDir, dirName);
                await EnsureRemoteDirectoryExistsAsync(targetRemoteDir);

                foreach (string file in Directory.GetFiles(localPath))
                {
                    await UploadSingleFileToDirAsync(file, targetRemoteDir, refreshAfter: false);
                }
                foreach (string subDir in Directory.GetDirectories(localPath))
                {
                    await UploadPathRecursiveAsync(subDir, targetRemoteDir);
                }
            }
        }

        private async Task EnsureRemoteDirectoryExistsAsync(string remoteDir)
        {
            await _remoteIoGate.WaitAsync();
            try
            {
                if (_sftpClient != null && _sftpClient.IsConnected)
                {
                    await Task.Run(() =>
                    {
                        if (!_sftpClient.Exists(remoteDir))
                            _sftpClient.CreateDirectory(remoteDir);
                    });
                }
                else if (_ftpClient != null && _ftpClient.IsConnected)
                {
                    if (!await _ftpClient.DirectoryExists(remoteDir))
                        await _ftpClient.CreateDirectory(remoteDir);
                }
            }
            finally
            {
                _remoteIoGate.Release();
            }
        }

        private async Task UploadSingleFileAsync(string localFilePath)
        {
            string remoteDir = RemotePathBox.Text.Trim();
            await UploadSingleFileToDirAsync(localFilePath, remoteDir, refreshAfter: true);
        }

        private async Task UploadSingleFileToDirAsync(string localFilePath, string remoteDir, bool refreshAfter)
        {
            if (!IsConnected() || !File.Exists(localFilePath)) return;
            try
            {
                string fileName = Path.GetFileName(localFilePath);
                string remoteTarget = CombineRemotePath(remoteDir, fileName);

                TransferProgressBar.Value = 10;
                StatusText.Text = $"⬆ Lade hoch: {fileName} -> {remoteDir} ...";

                await _remoteIoGate.WaitAsync();
                try
                {
                    if (_sftpClient != null && _sftpClient.IsConnected)
                    {
                        long totalBytes = new FileInfo(localFilePath).Length;
                        await Task.Run(() =>
                        {
                            using var fs = new FileStream(localFilePath, FileMode.Open, FileAccess.Read, FileShare.ReadWrite);
                            _sftpClient.UploadFile(fs, remoteTarget, true, uploaded =>
                            {
                                if (totalBytes > 0)
                                {
                                    double pct = (double)uploaded / totalBytes * 100.0;
                                    Dispatcher.InvokeAsync(() => TransferProgressBar.Value = pct);
                                }
                            });
                        });
                    }
                    else if (_ftpClient != null && _ftpClient.IsConnected)
                    {
                        await _ftpClient.UploadFile(localFilePath, remoteTarget, FtpRemoteExists.Overwrite, true);
                    }
                }
                finally
                {
                    _remoteIoGate.Release();
                }

                TransferProgressBar.Value = 100;
                StatusText.Text = $"✓ Hochgeladen: {fileName}";
                if (refreshAfter)
                    await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Upload-Fehler: {ex.Message}";
            }
        }

        private async void DownloadSelected_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            var selected = GetSelectedActionableRemoteItems();
            if (selected.Count == 0) return;

            string localBase = LocalPathBox.Text.Trim();
            foreach (var item in selected)
            {
                await DownloadItemRecursiveAsync(item, localBase, refreshAfter: false);
            }
            NavigateLocalDirectory(localBase);
        }

        private async Task DownloadSingleFileAsync(FileEntryItem remoteItem)
        {
            await DownloadItemRecursiveAsync(remoteItem, LocalPathBox.Text.Trim(), refreshAfter: true);
        }

        private async Task DownloadItemRecursiveAsync(FileEntryItem remoteItem, string localParentDir, bool refreshAfter)
        {
            if (!IsConnected() || remoteItem.IsParentNav) return;
            if (remoteItem.IsSymbolicLink)
            {
                StatusText.Text = $"↷ Symlink aus Sicherheitsgründen übersprungen: {remoteItem.Name}";
                return;
            }

            try
            {
                string safeLocalTarget = FunkenwerkFtpSecurity.ValidateSafeLocalPath(localParentDir, remoteItem.Name);

                if (remoteItem.IsDirectory)
                {
                    Directory.CreateDirectory(safeLocalTarget);
                    var children = new List<FileEntryItem>();

                    await _remoteIoGate.WaitAsync();
                    try
                    {
                        if (_sftpClient != null && _sftpClient.IsConnected)
                        {
                            var subEntries = await Task.Run(() => _sftpClient.ListDirectory(remoteItem.FullPath)
                                .Where(x => x.Name != "." && x.Name != "..")
                                .ToList());
                            foreach (var sub in subEntries)
                            {
                                // Check IsSymbolicLink BEFORE IsDirectory so symlinks are skipped and never traversed
                                if (sub.IsSymbolicLink) continue;
                                children.Add(new FileEntryItem
                                {
                                    Name = sub.Name,
                                    FullPath = sub.FullName,
                                    IsSymbolicLink = false,
                                    IsDirectory = sub.IsDirectory,
                                    Size = sub.Length
                                });
                            }
                        }
                        else if (_ftpClient != null && _ftpClient.IsConnected)
                        {
                            var subEntries = await _ftpClient.GetListing(remoteItem.FullPath);
                            foreach (var sub in subEntries.Where(x => x.Name != "." && x.Name != ".."))
                            {
                                if (sub.Type == FtpObjectType.Link) continue;
                                children.Add(new FileEntryItem
                                {
                                    Name = sub.Name,
                                    FullPath = sub.FullName,
                                    IsSymbolicLink = false,
                                    IsDirectory = sub.Type == FtpObjectType.Directory,
                                    Size = sub.Size
                                });
                            }
                        }
                    }
                    finally
                    {
                        _remoteIoGate.Release();
                    }

                    foreach (var child in children)
                    {
                        await DownloadItemRecursiveAsync(child, safeLocalTarget, refreshAfter: false);
                    }

                    if (refreshAfter)
                        NavigateLocalDirectory(LocalPathBox.Text.Trim());
                    return;
                }

                TransferProgressBar.Value = 10;
                StatusText.Text = $"⬇ Lade herunter: {remoteItem.Name} ...";

                await _remoteIoGate.WaitAsync();
                try
                {
                    if (_sftpClient != null && _sftpClient.IsConnected)
                    {
                        long totalBytes = Math.Max(1, remoteItem.Size);
                        await Task.Run(() =>
                        {
                            using var fs = File.Create(safeLocalTarget);
                            _sftpClient.DownloadFile(remoteItem.FullPath, fs, downloaded =>
                            {
                                double pct = (double)downloaded / totalBytes * 100.0;
                                Dispatcher.InvokeAsync(() => TransferProgressBar.Value = Math.Min(100, pct));
                            });
                        });
                    }
                    else if (_ftpClient != null && _ftpClient.IsConnected)
                    {
                        await _ftpClient.DownloadFile(safeLocalTarget, remoteItem.FullPath, FtpLocalExists.Overwrite);
                    }
                }
                finally
                {
                    _remoteIoGate.Release();
                }

                TransferProgressBar.Value = 100;
                StatusText.Text = $"✓ Heruntergeladen: {remoteItem.Name}";
                if (refreshAfter)
                    NavigateLocalDirectory(LocalPathBox.Text.Trim());
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Download-Fehler: {ex.Message}";
            }
        }

        public static void ExecuteSafeRemoteStageTree(
            string remotePath,
            bool isDir,
            bool isSymlink,
            long declaredSize,
            string safeLocalDest,
            DragStagingBudget budget,
            Func<string, IEnumerable<RemoteNodeInfo>> listDir,
            Action<string, Stream> downloadToStream)
        {
            // Check IsSymbolicLink BEFORE IsDirectory so symlinks are skipped and never traversed
            if (isSymlink) return;

            if (isDir)
            {
                Directory.CreateDirectory(safeLocalDest);
                FunkenwerkFtpSecurity.LockDownDirectoryAcl(safeLocalDest);
                foreach (var sub in listDir(remotePath))
                {
                    if (sub.Name == "." || sub.Name == "..") continue;
                    if (sub.IsSymbolicLink) continue;
                    string childLocal = FunkenwerkFtpSecurity.ValidateSafeLocalPath(safeLocalDest, sub.Name);
                    ExecuteSafeRemoteStageTree(sub.FullPath, sub.IsDirectory, sub.IsSymbolicLink, sub.Size, childLocal, budget, listDir, downloadToStream);
                }
            }
            else
            {
                budget.ReserveFile(declaredSize);
                using (var rawFs = File.Create(safeLocalDest))
                using (var bounded = new BoundedStagingStream(rawFs, budget, declaredSize))
                {
                    downloadToStream(remotePath, bounded);
                }
                FunkenwerkFtpSecurity.LockDownFileAcl(safeLocalDest);
            }
        }

        // Synchronous staging used ONLY when user drags remote item(s) out onto Windows Desktop / Explorer / PhpStorm
        private string[]? StageRemoteItemsForExternalDropSync(List<FileEntryItem> items)
        {
            if (!IsConnected() || items.Count == 0) return null;

            long approxBytes = items.Where(x => !x.IsDirectory && !x.IsSymbolicLink).Sum(x => Math.Max(0, x.Size));
            if (approxBytes > MAX_EXTERNAL_DRAG_STAGE_BYTES)
            {
                StatusText.Text = "⚠ Für sehr große Dateien (> 160 MB) bitte direkt in das linke LOKAL-Feld ziehen.";
                return null;
            }

            if (!_remoteIoGate.Wait(TimeSpan.FromSeconds(15)))
                return null;

            string? batchDir = null;
            try
            {
                batchDir = DragStagingManager.CreateIsolatedBatchDir();
                var budget = new DragStagingBudget(MAX_EXTERNAL_DRAG_STAGE_BYTES, DragStagingBudget.MaxFiles);
                var staged = new List<string>(items.Count);

                foreach (var it in items)
                {
                    if (it.IsParentNav || it.IsSymbolicLink) continue;
                    string safeDest = FunkenwerkFtpSecurity.ValidateSafeLocalPath(batchDir, it.Name);
                    StageSingleRemoteEntrySync(it.FullPath, it.IsDirectory, it.IsSymbolicLink, it.Size, safeDest, budget);
                    if (File.Exists(safeDest) || Directory.Exists(safeDest))
                        staged.Add(safeDest);
                }

                if (staged.Count > 0)
                    StatusText.Text = $"✓ {staged.Count} Server-Element(e) per Drag & Drop bereitgestellt.";
                return staged.ToArray();
            }
            catch (Exception ex)
            {
                if (!string.IsNullOrEmpty(batchDir) && Directory.Exists(batchDir))
                {
                    try { Directory.Delete(batchDir, recursive: true); } catch { }
                }
                StatusText.Text = $"⚠ Drag-Export abgebrochen: {ex.Message}";
                return null;
            }
            finally
            {
                _remoteIoGate.Release();
            }
        }

        private void StageSingleRemoteEntrySync(
            string remotePath,
            bool isDir,
            bool isSymlink,
            long declaredSize,
            string safeLocalDest,
            DragStagingBudget budget)
        {
            if (isSymlink) return;

            if (_sftpClient != null && _sftpClient.IsConnected)
            {
                ExecuteSafeRemoteStageTree(
                    remotePath,
                    isDir,
                    isSymlink,
                    declaredSize,
                    safeLocalDest,
                    budget,
                    dir => _sftpClient.ListDirectory(dir).Select(x => new RemoteNodeInfo(x.Name, x.FullName, !x.IsSymbolicLink && x.IsDirectory, x.IsSymbolicLink, x.Length)),
                    (file, stream) => _sftpClient.DownloadFile(file, stream));
            }
            else if (_ftpClient != null && _ftpClient.IsConnected)
            {
                var ftp = _ftpClient;
                Task.Run(() =>
                {
                    ExecuteSafeRemoteStageTree(
                        remotePath,
                        isDir,
                        isSymlink,
                        declaredSize,
                        safeLocalDest,
                        budget,
                        dir => ftp.GetListing(dir).GetAwaiter().GetResult().Select(x =>
                            new RemoteNodeInfo(x.Name, x.FullName, x.Type != FtpObjectType.Link && x.Type == FtpObjectType.Directory, x.Type == FtpObjectType.Link, x.Size)),
                        (file, stream) => ftp.DownloadStream(stream, file).GetAwaiter().GetResult());
                }).GetAwaiter().GetResult();
            }
        }

        // =====================================================================
        // 6-WAY EFFORTLESS DRAG & DROP + SUBFOLDER ROW HIGHLIGHTING
        // =====================================================================
        private static T? FindAncestor<T>(DependencyObject? cur) where T : DependencyObject
        {
            while (cur != null)
            {
                if (cur is T match) return match;
                cur = cur is Visual || cur is System.Windows.Media.Media3D.Visual3D
                    ? VisualTreeHelper.GetParent(cur)
                    : LogicalTreeHelper.GetParent(cur);
            }
            return null;
        }

        private static bool IsInteractiveChromeElement(DependencyObject? src)
        {
            return FindAncestor<ScrollBar>(src) != null ||
                   FindAncestor<Thumb>(src) != null ||
                   FindAncestor<GridViewColumnHeader>(src) != null;
        }

        private void ClearDropTargetHighlight()
        {
            if (_highlightedDropItem != null)
            {
                _highlightedDropItem.IsDropTarget = false;
                _highlightedDropItem = null;
            }
        }

        private FileEntryItem? GetHoveredDirectoryRow(DragEventArgs e)
        {
            var lvi = FindAncestor<ListViewItem>(e.OriginalSource as DependencyObject);
            if (lvi?.DataContext is FileEntryItem item && item.IsDirectory)
                return item;
            return null;
        }

        private void SetHighlightedDropRow(FileEntryItem? row)
        {
            if (ReferenceEquals(_highlightedDropItem, row)) return;
            if (_highlightedDropItem != null)
                _highlightedDropItem.IsDropTarget = false;
            _highlightedDropItem = row;
            if (_highlightedDropItem != null)
                _highlightedDropItem.IsDropTarget = true;
        }

        private void RootGrid_PreviewDragOver(object sender, DragEventArgs e)
        {
            // Keep internal drags smooth while crossing the 14px splitter gap
            if (e.Data.GetDataPresent(DRAG_FORMAT_REMOTE) || e.Data.GetDataPresent(DRAG_FORMAT_LOCAL) || e.Data.GetDataPresent(DataFormats.FileDrop))
            {
                e.Effects = DragDropEffects.Copy;
                e.Handled = true;
            }
        }

        // --- LOKAL Mouse & Drag Initiation ---
        private void LocalFilesList_PreviewMouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            _localDeferredSingleSelect = null;
            if (IsInteractiveChromeElement(e.OriginalSource as DependencyObject))
            {
                _localCanStartDrag = false;
                return;
            }

            var lvi = FindAncestor<ListViewItem>(e.OriginalSource as DependencyObject);
            if (lvi == null)
            {
                _localCanStartDrag = false;
                if ((Keyboard.Modifiers & (ModifierKeys.Control | ModifierKeys.Shift)) == 0)
                    LocalFilesList.SelectedItems.Clear();
                return;
            }

            if (lvi.DataContext is FileEntryItem item)
            {
                if (item.IsParentNav)
                {
                    _localCanStartDrag = false;
                    return;
                }

                _localDragStartPoint = e.GetPosition(null);
                _localCanStartDrag = true;

                // Preserve multi-selection on press so dragging multiple selected files drags ALL of them
                if (lvi.IsSelected && LocalFilesList.SelectedItems.Count > 1 &&
                    e.ClickCount == 1 && (Keyboard.Modifiers & (ModifierKeys.Control | ModifierKeys.Shift)) == 0)
                {
                    _localDeferredSingleSelect = item;
                    e.Handled = true;
                }
            }
        }

        private void LocalFilesList_PreviewMouseLeftButtonUp(object sender, MouseButtonEventArgs e)
        {
            if (_localDeferredSingleSelect != null && !_isDraggingInternal)
            {
                var target = _localDeferredSingleSelect;
                _localDeferredSingleSelect = null;
                LocalFilesList.SelectedItems.Clear();
                LocalFilesList.SelectedItem = target;
            }
            _localCanStartDrag = false;
        }

        private void LocalFilesList_PreviewMouseRightButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (IsInteractiveChromeElement(e.OriginalSource as DependencyObject)) return;
            var lvi = FindAncestor<ListViewItem>(e.OriginalSource as DependencyObject);
            if (lvi == null)
            {
                LocalFilesList.SelectedItems.Clear();
            }
            else if (!lvi.IsSelected)
            {
                LocalFilesList.SelectedItems.Clear();
                lvi.IsSelected = true;
            }
        }

        private void LocalFilesList_PreviewMouseMove(object sender, MouseEventArgs e)
        {
            if (!_localCanStartDrag || e.LeftButton != MouseButtonState.Pressed || _isDraggingInternal) return;
            Point pos = e.GetPosition(null);
            Vector diff = _localDragStartPoint - pos;
            if (Math.Abs(diff.X) <= 6 && Math.Abs(diff.Y) <= 6) return;

            var selected = GetSelectedActionableLocalItems();
            if (selected.Count == 0) return;

            try
            {
                _isDraggingInternal = true;
                _localDeferredSingleSelect = null;
                var data = new DataObject();
                string[] paths = selected.Select(x => x.FullPath).ToArray();
                data.SetData(DataFormats.FileDrop, paths);
                data.SetData(DRAG_FORMAT_LOCAL, paths);
                DragDrop.DoDragDrop(LocalFilesList, data, DragDropEffects.Copy | DragDropEffects.Move);
            }
            catch { }
            finally
            {
                _isDraggingInternal = false;
                _localCanStartDrag = false;
                ClearDropTargetHighlight();
                ResetTileBorders();
            }
        }

        // --- SERVER Mouse & Drag Initiation ---
        private void RemoteFilesList_PreviewMouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            _remoteDeferredSingleSelect = null;
            if (IsInteractiveChromeElement(e.OriginalSource as DependencyObject))
            {
                _remoteCanStartDrag = false;
                return;
            }

            var lvi = FindAncestor<ListViewItem>(e.OriginalSource as DependencyObject);
            if (lvi == null)
            {
                _remoteCanStartDrag = false;
                if ((Keyboard.Modifiers & (ModifierKeys.Control | ModifierKeys.Shift)) == 0)
                    RemoteFilesList.SelectedItems.Clear();
                return;
            }

            if (lvi.DataContext is FileEntryItem item)
            {
                if (item.IsParentNav)
                {
                    _remoteCanStartDrag = false;
                    return;
                }

                _remoteDragStartPoint = e.GetPosition(null);
                _remoteCanStartDrag = true;

                if (lvi.IsSelected && RemoteFilesList.SelectedItems.Count > 1 &&
                    e.ClickCount == 1 && (Keyboard.Modifiers & (ModifierKeys.Control | ModifierKeys.Shift)) == 0)
                {
                    _remoteDeferredSingleSelect = item;
                    e.Handled = true;
                }
            }
        }

        private void RemoteFilesList_PreviewMouseLeftButtonUp(object sender, MouseButtonEventArgs e)
        {
            if (_remoteDeferredSingleSelect != null && !_isDraggingInternal)
            {
                var target = _remoteDeferredSingleSelect;
                _remoteDeferredSingleSelect = null;
                RemoteFilesList.SelectedItems.Clear();
                RemoteFilesList.SelectedItem = target;
            }
            _remoteCanStartDrag = false;
        }

        private void RemoteFilesList_PreviewMouseRightButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (IsInteractiveChromeElement(e.OriginalSource as DependencyObject)) return;
            var lvi = FindAncestor<ListViewItem>(e.OriginalSource as DependencyObject);
            if (lvi == null)
            {
                RemoteFilesList.SelectedItems.Clear();
            }
            else if (!lvi.IsSelected)
            {
                RemoteFilesList.SelectedItems.Clear();
                lvi.IsSelected = true;
            }
        }

        private void RemoteFilesList_PreviewMouseMove(object sender, MouseEventArgs e)
        {
            if (!_remoteCanStartDrag || e.LeftButton != MouseButtonState.Pressed || _isDraggingInternal || !IsConnected()) return;
            Point pos = e.GetPosition(null);
            Vector diff = _remoteDragStartPoint - pos;
            if (Math.Abs(diff.X) <= 6 && Math.Abs(diff.Y) <= 6) return;

            var selected = GetSelectedActionableRemoteItems();
            if (selected.Count == 0) return;

            try
            {
                _isDraggingInternal = true;
                _remoteDeferredSingleSelect = null;
                var lazyData = new DelayedRemoteFileDropDataObject(
                    DRAG_FORMAT_REMOTE,
                    selected,
                    () => StageRemoteItemsForExternalDropSync(selected));

                DragDrop.DoDragDrop(RemoteFilesList, lazyData, DragDropEffects.Copy | DragDropEffects.Move);
            }
            catch { }
            finally
            {
                _isDraggingInternal = false;
                _remoteCanStartDrag = false;
                ClearDropTargetHighlight();
                ResetTileBorders();
            }
        }

        // --- LOKAL Drop Target Handling ---
        private void LocalTile_PreviewDragOver(object sender, DragEventArgs e)
        {
            var hoveredFolder = GetHoveredDirectoryRow(e);

            // Same-pane drag inside LOKAL: allow moving into a subfolder or '..'
            if (e.Data.GetDataPresent(DRAG_FORMAT_LOCAL) && e.Data.GetData(DRAG_FORMAT_LOCAL) is string[] localPaths)
            {
                if (hoveredFolder != null && !localPaths.Any(p => string.Equals(p, hoveredFolder.FullPath, StringComparison.OrdinalIgnoreCase)))
                {
                    SetHighlightedDropRow(hoveredFolder);
                    LocalTileBorder.BorderBrush = BrushHex("#C5A059");
                    e.Effects = DragDropEffects.Move;
                }
                else
                {
                    ClearDropTargetHighlight();
                    e.Effects = DragDropEffects.None;
                }
                e.Handled = true;
                return;
            }

            if (e.Data.GetDataPresent(DRAG_FORMAT_REMOTE) || e.Data.GetDataPresent(DataFormats.FileDrop))
            {
                SetHighlightedDropRow(hoveredFolder);
                LocalTileBorder.BorderBrush = BrushHex("#C5A059");
                e.Effects = DragDropEffects.Copy;
            }
            else
            {
                ClearDropTargetHighlight();
                e.Effects = DragDropEffects.None;
            }
            e.Handled = true;
        }

        private void LocalTile_DragLeave(object sender, DragEventArgs e)
        {
            ClearDropTargetHighlight();
            ResetTileBorders();
        }

        private async void LocalTile_PreviewDrop(object sender, DragEventArgs e)
        {
            var targetFolderRow = GetHoveredDirectoryRow(e);
            ClearDropTargetHighlight();
            ResetTileBorders();
            e.Handled = true;

            string currentLocal = LocalPathBox.Text.Trim();
            if (string.IsNullOrWhiteSpace(currentLocal) || !Directory.Exists(currentLocal)) return;
            string destDir = targetFolderRow != null ? targetFolderRow.FullPath : currentLocal;

            // 1. Same-pane move inside LOKAL onto a folder row or '..'
            if (e.Data.GetDataPresent(DRAG_FORMAT_LOCAL) && e.Data.GetData(DRAG_FORMAT_LOCAL) is string[] movePaths)
            {
                if (targetFolderRow == null) return;
                int moved = 0;
                foreach (string src in movePaths)
                {
                    try
                    {
                        if (string.Equals(src, destDir, StringComparison.OrdinalIgnoreCase)) continue;
                        string dest = Path.Combine(destDir, Path.GetFileName(src.TrimEnd(Path.DirectorySeparatorChar)));
                        if (Directory.Exists(src)) Directory.Move(src, dest);
                        else if (File.Exists(src)) File.Move(src, dest, overwrite: true);
                        moved++;
                    }
                    catch (Exception ex)
                    {
                        StatusText.Text = $"⚠ Verschieben: {ex.Message}";
                    }
                }
                NavigateLocalDirectory(currentLocal);
                if (moved > 0)
                    StatusText.Text = $"✓ {moved} Element(e) in '{targetFolderRow.Name}' verschoben.";
                return;
            }

            // 2. Dropped from SERVER pane -> Download to LOKAL (or hovered LOKAL subfolder!)
            if (e.Data.GetDataPresent(DRAG_FORMAT_REMOTE) &&
                e.Data.GetData(DRAG_FORMAT_REMOTE) is List<FileEntryItem> remoteItems)
            {
                foreach (var item in remoteItems)
                {
                    await DownloadItemRecursiveAsync(item, destDir, refreshAfter: false);
                }
                NavigateLocalDirectory(currentLocal);
                StatusText.Text = $"✓ {remoteItems.Count} Server-Element(e) nach '{destDir}' heruntergeladen.";
                return;
            }

            // 3. Dropped from Windows Explorer / Desktop onto LOKAL
            if (e.Data.GetDataPresent(DataFormats.FileDrop) &&
                e.Data.GetData(DataFormats.FileDrop) is string[] droppedPaths &&
                droppedPaths.Length > 0)
            {
                // If user dropped a folder directly onto LocalPathBox, navigate into it
                if (e.OriginalSource is DependencyObject dObj && FindAncestor<TextBox>(dObj) == LocalPathBox &&
                    droppedPaths.Length == 1 && Directory.Exists(droppedPaths[0]))
                {
                    NavigateLocalDirectory(droppedPaths[0]);
                    StatusText.Text = $"📂 Lokales Verzeichnis geöffnet: {droppedPaths[0]}";
                    return;
                }

                int copied = CopyExternalPathsIntoLocalDir(droppedPaths, destDir);
                NavigateLocalDirectory(currentLocal);
                if (copied > 0)
                    StatusText.Text = $"✓ {copied} Element(e) nach '{destDir}' übernommen.";
            }
        }

        private static int CopyExternalPathsIntoLocalDir(IEnumerable<string> srcPaths, string destDir)
        {
            int count = 0;
            foreach (string src in srcPaths)
            {
                try
                {
                    if (File.Exists(src))
                    {
                        string dest = FunkenwerkFtpSecurity.ValidateSafeLocalPath(destDir, Path.GetFileName(src));
                        if (!string.Equals(Path.GetFullPath(src), dest, StringComparison.OrdinalIgnoreCase))
                        {
                            File.Copy(src, dest, overwrite: true);
                            count++;
                        }
                    }
                    else if (Directory.Exists(src))
                    {
                        string dirName = Path.GetFileName(src.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar));
                        string destSub = FunkenwerkFtpSecurity.ValidateSafeLocalPath(destDir, dirName);
                        if (!string.Equals(Path.GetFullPath(src), destSub, StringComparison.OrdinalIgnoreCase) &&
                            !destSub.StartsWith(Path.GetFullPath(src) + Path.DirectorySeparatorChar, StringComparison.OrdinalIgnoreCase))
                        {
                            CopyLocalDirectoryRecursive(src, destSub);
                            count++;
                        }
                    }
                }
                catch { }
            }
            return count;
        }

        private static void CopyLocalDirectoryRecursive(string srcDir, string destDir)
        {
            Directory.CreateDirectory(destDir);
            foreach (string f in Directory.GetFiles(srcDir))
            {
                File.Copy(f, Path.Combine(destDir, Path.GetFileName(f)), overwrite: true);
            }
            foreach (string d in Directory.GetDirectories(srcDir))
            {
                CopyLocalDirectoryRecursive(d, Path.Combine(destDir, Path.GetFileName(d)));
            }
        }

        // --- SERVER Drop Target Handling ---
        private void RemoteTile_PreviewDragOver(object sender, DragEventArgs e)
        {
            if (!IsConnected())
            {
                ClearDropTargetHighlight();
                e.Effects = DragDropEffects.None;
                e.Handled = true;
                return;
            }

            var hoveredFolder = GetHoveredDirectoryRow(e);

            // Same-pane drag inside SERVER: move into hovered remote subfolder or '..'
            if (e.Data.GetDataPresent(DRAG_FORMAT_REMOTE) &&
                e.Data.GetData(DRAG_FORMAT_REMOTE) is List<FileEntryItem> remoteDragged)
            {
                if (hoveredFolder != null && !remoteDragged.Any(x => string.Equals(x.FullPath, hoveredFolder.FullPath, StringComparison.Ordinal)))
                {
                    SetHighlightedDropRow(hoveredFolder);
                    RemoteTileBorder.BorderBrush = BrushHex("#C5A059");
                    e.Effects = DragDropEffects.Move;
                }
                else
                {
                    ClearDropTargetHighlight();
                    e.Effects = DragDropEffects.None;
                }
                e.Handled = true;
                return;
            }

            if (e.Data.GetDataPresent(DRAG_FORMAT_LOCAL) || e.Data.GetDataPresent(DataFormats.FileDrop))
            {
                SetHighlightedDropRow(hoveredFolder);
                RemoteTileBorder.BorderBrush = BrushHex("#C5A059");
                e.Effects = DragDropEffects.Copy;
            }
            else
            {
                ClearDropTargetHighlight();
                e.Effects = DragDropEffects.None;
            }
            e.Handled = true;
        }

        private void RemoteTile_DragLeave(object sender, DragEventArgs e)
        {
            ClearDropTargetHighlight();
            ResetTileBorders();
        }

        private async void RemoteTile_PreviewDrop(object sender, DragEventArgs e)
        {
            var targetFolderRow = GetHoveredDirectoryRow(e);
            ClearDropTargetHighlight();
            ResetTileBorders();
            e.Handled = true;

            if (!IsConnected())
            {
                StatusText.Text = "⚠ Bitte zuerst mit dem Server verbinden, bevor Dateien hochgeladen werden.";
                return;
            }

            string currentRemote = RemotePathBox.Text.Trim();
            string targetRemoteDir = targetFolderRow != null ? targetFolderRow.FullPath : currentRemote;

            // 1. Same-pane move inside SERVER onto a folder row or '..'
            if (e.Data.GetDataPresent(DRAG_FORMAT_REMOTE) &&
                e.Data.GetData(DRAG_FORMAT_REMOTE) is List<FileEntryItem> remoteDragged)
            {
                if (targetFolderRow == null) return;
                foreach (var item in remoteDragged)
                {
                    if (string.Equals(item.FullPath, targetRemoteDir, StringComparison.Ordinal)) continue;
                    string destPath = CombineRemotePath(targetRemoteDir, item.Name);
                    await MoveRemoteEntryAsync(item, destPath, refreshAfter: false);
                }
                await NavigateRemoteDirectoryAsync(currentRemote);
                StatusText.Text = $"✓ {remoteDragged.Count} Element(e) auf Server nach '{targetRemoteDir}' verschoben.";
                return;
            }

            // 2. Upload from LOKAL or Windows Explorer / Desktop into targetRemoteDir
            string[]? paths = null;
            if (e.Data.GetDataPresent(DRAG_FORMAT_LOCAL))
                paths = e.Data.GetData(DRAG_FORMAT_LOCAL) as string[];
            else if (e.Data.GetDataPresent(DataFormats.FileDrop))
                paths = e.Data.GetData(DataFormats.FileDrop) as string[];

            if (paths == null || paths.Length == 0) return;

            foreach (string p in paths)
            {
                await UploadPathRecursiveAsync(p, targetRemoteDir);
            }
            await NavigateRemoteDirectoryAsync(currentRemote);
            StatusText.Text = $"✓ {paths.Length} Element(e) nach '{targetRemoteDir}' hochgeladen.";
        }

        private void ResetTileBorders()
        {
            var sub = BrushHex(_isDarkMode ? "#242938" : "#E5E7EB");
            LocalTileBorder.BorderBrush = sub;
            RemoteTileBorder.BorderBrush = sub;
        }

        // =====================================================================
        // 100 % LIGHT- & DARK-MODE CONSISTENCY
        // =====================================================================
        private void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            _isDarkMode = !_isDarkMode;
            ApplyTheme();
        }

        private void ApplyTheme()
        {
            ThemeToggleBtn.Content = _isDarkMode ? "☀" : "🌙";
            var res = Application.Current.Resources;

            res["FwBgRoot"] = BrushHex(_isDarkMode ? "#0F1117" : "#F3F4F6");
            res["FwBgBar"] = BrushHex(_isDarkMode ? "#12151E" : "#FFFFFF");
            res["FwBgTile"] = BrushHex(_isDarkMode ? "#141821" : "#FFFFFF");
            res["FwBgInput"] = BrushHex(_isDarkMode ? "#181C26" : "#F9FAFB");
            res["FwBgButton"] = BrushHex(_isDarkMode ? "#1D2230" : "#F3F4F6");
            res["FwBgMenu"] = BrushHex(_isDarkMode ? "#161A24" : "#FFFFFF");
            res["FwBgHover"] = BrushHex(_isDarkMode ? "#24C5A059" : "#1FC5A059");
            res["FwBgSelected"] = BrushHex(_isDarkMode ? "#33C5A059" : "#2AC5A059");
            res["FwBorderSubtle"] = BrushHex(_isDarkMode ? "#242938" : "#E5E7EB");
            res["FwBorderStrong"] = BrushHex(_isDarkMode ? "#2D3343" : "#D1D5DB");
            res["FwFgPrimary"] = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827");
            res["FwFgSecondary"] = BrushHex(_isDarkMode ? "#9CA3AF" : "#6B7280");
        }

        private static SolidColorBrush BrushHex(string hex)
        {
            var b = new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex));
            b.Freeze();
            return b;
        }

        private async void Window_Closing(object sender, CancelEventArgs e)
        {
            if (_closeCleanupDone) return;
            e.Cancel = true;
            if (_closeCleanupInProgress) return;
            _closeCleanupInProgress = true;
            try
            {
                await DisconnectServerAsync();
                DragStagingManager.PruneOldBatches(TimeSpan.Zero);
                DragStagingManager.PurgeAllStagingDirs();
            }
            catch { }
            finally
            {
                _closeCleanupDone = true;
                _closeCleanupInProgress = false;
            }
            Close();
        }
    }
}
