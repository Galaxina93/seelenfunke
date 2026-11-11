using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Net;
using System.Security.Authentication;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using FluentFTP;
using Microsoft.Win32;
using Renci.SshNet;

namespace FunkenwerkFtp
{
    public sealed class FileEntryItem
    {
        public string Name { get; set; } = "";
        public string FullPath { get; set; } = "";
        public bool IsDirectory { get; set; }
        public long Size { get; set; }
        public DateTime Modified { get; set; }
        public string Permissions { get; set; } = "";

        public string DisplayName => IsDirectory ? $"📁 {Name}" : $"📄 {Name}";
        public string SizeFormatted => IsDirectory ? "<DIR>" : FormatBytes(Size);
        public string ModifiedFormatted => Modified == DateTime.MinValue ? "-" : Modified.ToString("dd.MM.yyyy HH:mm");

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
        private string _activeProtocol = "SFTP";
        private string _lastPinnedFingerprint = "";
        private Point _localDragStartPoint;
        private Point _remoteDragStartPoint;
        private bool _isDraggingInternal = false;
        private const string DRAG_FORMAT_REMOTE = "FunkenwerkFtp.RemoteItems";
        private const string DRAG_FORMAT_LOCAL = "FunkenwerkFtp.LocalItems";

        public MainWindow()
        {
            InitializeComponent();
            LoadProfilesIntoUi();
            string startLocal = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);
            NavigateLocalDirectory(startLocal);
        }

        private void LoadProfilesIntoUi()
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

        private void ProfilesCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (ProfilesCombo.SelectedItem is not FtpConnectionProfile p) return;

            SelectProtocolItem(p.Protocol);
            HostBox.Text = p.Host;
            PortBox.Text = p.Port.ToString();
            UserBox.Text = p.Username;
            SetPasswordValue(FunkenwerkFtpSecurity.DecryptSecret(p.EncryptedPassword));
            _selectedPrivateKeyPath = p.PrivateKeyPath ?? "";
            PickKeyBtn.ToolTip = string.IsNullOrWhiteSpace(_selectedPrivateKeyPath)
                ? "SSH Private Key wählen"
                : $"SSH Key: {_selectedPrivateKeyPath}";

            if (!string.IsNullOrWhiteSpace(p.RemoteDirectory))
                RemotePathBox.Text = p.RemoteDirectory;
            if (!string.IsNullOrWhiteSpace(p.LocalDirectory) && Directory.Exists(p.LocalDirectory))
                NavigateLocalDirectory(p.LocalDirectory);
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

        private string GetSelectedProtocol()
        {
            return (ProtocolCombo.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "SFTP";
        }

        private void ProtocolCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (PortBox == null) return;
            string proto = GetSelectedProtocol();
            if (proto == "SFTP" && PortBox.Text == "21") PortBox.Text = "22";
            else if (proto != "SFTP" && PortBox.Text == "22") PortBox.Text = "21";
        }

        private string GetPasswordValue()
        {
            return _isPassVisible ? PassVisibleBox.Text : PassBox.Password;
        }

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
                    var newProf = new FtpConnectionProfile
                    {
                        Name = string.IsNullOrWhiteSpace(user) ? host : $"{user}@{host}",
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
            _profiles.Remove(current);
            FunkenwerkFtpSecurity.SaveProfiles(_profiles);
            ProfilesCombo.ItemsSource = null;
            ProfilesCombo.ItemsSource = _profiles;
            if (_profiles.Count > 0) ProfilesCombo.SelectedIndex = 0;
            StatusText.Text = "Profil gelöscht.";
        }

        // =====================================================================
        // LOCAL FILESYSTEM BROWSER
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

                foreach (var sub in di.GetDirectories().OrderBy(d => d.Name))
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

                foreach (var file in di.GetFiles().OrderBy(f => f.Name))
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

                LocalFilesList.ItemsSource = entries;
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Lokal: {ex.Message}";
            }
        }

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
            if (LocalFilesList.SelectedItem is not FileEntryItem item) return;
            if (item.IsDirectory)
            {
                NavigateLocalDirectory(item.FullPath);
            }
            else if (IsConnected())
            {
                await UploadSingleFileAsync(item.FullPath);
            }
        }

        private void LocalMkdir_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                string baseDir = LocalPathBox.Text.Trim();
                string newFolder = Path.Combine(baseDir, $"Neuer_Ordner_{DateTime.Now:HHmmss}");
                Directory.CreateDirectory(newFolder);
                NavigateLocalDirectory(baseDir);
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ {ex.Message}";
            }
        }

        private void LocalDelete_Click(object sender, RoutedEventArgs e)
        {
            var selected = LocalFilesList.SelectedItems.Cast<FileEntryItem>().ToList();
            if (selected.Count == 0) return;

            foreach (var item in selected)
            {
                try
                {
                    if (item.IsDirectory) Directory.Delete(item.FullPath, true);
                    else File.Delete(item.FullPath);
                }
                catch { }
            }
            NavigateLocalDirectory(LocalPathBox.Text.Trim());
        }

        // =====================================================================
        // SECURE SERVER CONNECTION (SFTP / FTPS / FTP)
        // =====================================================================
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

                ConnectBtn.IsEnabled = false;
                StatusText.Text = $"⏳ Verbinde verschlüsselt mit {_activeProtocol}://{host}:{port} ...";

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

                    sftp.HostKeyReceived += (s, hkArgs) =>
                    {
                        var check = FunkenwerkFtpSecurity.VerifyAndPinHostKey(host, port, hkArgs.HostKeyName, hkArgs.HostKey);
                        _lastPinnedFingerprint = check.FingerprintSha256;
                        if (check.IsChanged)
                        {
                            hostKeyChangedAbort = true;
                            hkArgs.CanTrust = false;
                        }
                        else
                        {
                            hkArgs.CanTrust = check.IsTrusted;
                        }
                    };

                    await Task.Run(() => sftp.Connect());
                    if (hostKeyChangedAbort)
                    {
                        sftp.Dispose();
                        StatusText.Text = "🚨 SICHERHEITSWARNUNG: Server-Host-Key hat sich geändert (MITM-Schutz hat Verbindung blockiert)!";
                        ConnectBtn.IsEnabled = true;
                        return;
                    }

                    _sftpClient = sftp;
                    OpenSshBtn.Visibility = Visibility.Visible;
                    ConnectBtn.Content = "TRENNEN";
                    ConnectBtn.Background = new SolidColorBrush(Color.FromRgb(239, 68, 68));
                    ConnectBtn.Foreground = Brushes.White;
                    StatusText.Text = $"🔒 SFTP Verbunden ({user}@{host}) · {_lastPinnedFingerprint}";

                    string startRemote = string.IsNullOrWhiteSpace(RemotePathBox.Text) ? "/" : RemotePathBox.Text.Trim();
                    await NavigateRemoteDirectoryAsync(startRemote);
                }
                else
                {
                    var cfg = new FtpConfig
                    {
                        EncryptionMode = _activeProtocol == "FTPS" ? FtpEncryptionMode.Explicit : FtpEncryptionMode.None,
                        SslProtocols = SslProtocols.Tls12 | SslProtocols.Tls13,
                        ValidateAnyCertificate = false,
                        ConnectTimeout = 10000
                    };
                    var ftp = new AsyncFtpClient(host, new NetworkCredential(user, pass), port, cfg);
                    await ftp.AutoConnect();
                    _ftpClient = ftp;
                    OpenSshBtn.Visibility = Visibility.Visible;

                    ConnectBtn.Content = "TRENNEN";
                    ConnectBtn.Background = new SolidColorBrush(Color.FromRgb(239, 68, 68));
                    ConnectBtn.Foreground = Brushes.White;
                    StatusText.Text = $"🔒 {_activeProtocol} Verbunden ({user}@{host}:{port})";

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
            try
            {
                if (_sftpClient != null)
                {
                    await Task.Run(() =>
                    {
                        try { _sftpClient.Disconnect(); } catch { }
                        try { _sftpClient.Dispose(); } catch { }
                    });
                    _sftpClient = null;
                }
                if (_ftpClient != null)
                {
                    try { await _ftpClient.Disconnect(); } catch { }
                    try { _ftpClient.Dispose(); } catch { }
                    _ftpClient = null;
                }
            }
            catch { }

            RemoteFilesList.ItemsSource = null;
            OpenSshBtn.Visibility = Visibility.Collapsed;
            ConnectBtn.Content = "⚡ VERBINDEN";
            ConnectBtn.Background = new SolidColorBrush(Color.FromRgb(197, 160, 89));
            ConnectBtn.Foreground = new SolidColorBrush(Color.FromRgb(15, 17, 23));
            StatusText.Text = "🔒 Verbindung getrennt.";
        }

        private void OpenSsh_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;

            try
            {
                string host = FunkenwerkFtpSecurity.SanitizeHost(HostBox.Text);
                int port = int.TryParse(PortBox.Text, out int pt) ? pt : 22;
                if (port == 21) port = 22;
                string user = UserBox.Text.Trim();
                string pass = GetPasswordValue();
                string remoteDir = string.IsNullOrWhiteSpace(RemotePathBox.Text) ? "/" : RemotePathBox.Text.Trim();
                string profileName = (ProfilesCombo.SelectedItem as FtpConnectionProfile)?.Name ?? $"{user}@{host}";

                FunkenwerkFtpSecurity.WriteSshHandoffTicket(
                    profileName,
                    host,
                    port,
                    user,
                    pass,
                    _selectedPrivateKeyPath,
                    remoteDir);

                string[] candidatePaths =
                {
                    Path.GetFullPath(Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "..", "Funkenwerk SSH", "FunkenwerkSsh.exe")),
                    @"C:\Users\konta\Desktop\Funkenwerk Collection\Funkenwerk SSH\FunkenwerkSsh.exe",
                    @"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\funkenwerk-ssh\bin\Release\net9.0-windows\FunkenwerkSsh.exe"
                };

                string? sshExe = candidatePaths.FirstOrDefault(File.Exists);
                if (string.IsNullOrEmpty(sshExe))
                {
                    StatusText.Text = "⚠ FunkenwerkSsh.exe wurde nicht gefunden.";
                    return;
                }

                System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo
                {
                    FileName = sshExe,
                    Arguments = "--handoff",
                    WorkingDirectory = Path.GetDirectoryName(sshExe) ?? "",
                    UseShellExecute = true
                });

                StatusText.Text = $"🔒 SSH-Verbindung in '{remoteDir}' gestartet.";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ SSH-Start fehlgeschlagen: {ex.Message}";
            }
        }

        private async Task NavigateRemoteDirectoryAsync(string remotePath)
        {
            if (!IsConnected()) return;
            try
            {
                string target = string.IsNullOrWhiteSpace(remotePath) ? "/" : remotePath.Replace('\\', '/');
                var items = new List<FileEntryItem>();

                if (_sftpClient != null && _sftpClient.IsConnected)
                {
                    var listing = await Task.Run(() => _sftpClient.ListDirectory(target).ToList());
                    foreach (var entry in listing
                                 .Where(x => x.Name != "." && x.Name != "..")
                                 .OrderByDescending(x => x.IsDirectory)
                                 .ThenBy(x => x.Name))
                    {
                        items.Add(new FileEntryItem
                        {
                            Name = entry.Name,
                            FullPath = entry.FullName,
                            IsDirectory = entry.IsDirectory,
                            Size = entry.Length,
                            Modified = entry.LastWriteTime,
                            Permissions = FormatSftpPermissions(entry)
                        });
                    }
                }
                else if (_ftpClient != null && _ftpClient.IsConnected)
                {
                    var listing = await _ftpClient.GetListing(target);
                    foreach (var entry in listing
                                 .Where(x => x.Name != "." && x.Name != "..")
                                 .OrderByDescending(x => x.Type == FtpObjectType.Directory)
                                 .ThenBy(x => x.Name))
                    {
                        items.Add(new FileEntryItem
                        {
                            Name = entry.Name,
                            FullPath = entry.FullName,
                            IsDirectory = entry.Type == FtpObjectType.Directory,
                            Size = entry.Size,
                            Modified = entry.Modified,
                            Permissions = entry.RawPermissions ?? ""
                        });
                    }
                }

                RemotePathBox.Text = target;
                RemoteFilesList.ItemsSource = items;
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Server-Verzeichnis: {ex.Message}";
            }
        }

        private static string FormatSftpPermissions(Renci.SshNet.Sftp.ISftpFile f)
        {
            char d = f.IsDirectory ? 'd' : '-';
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
            string cur = RemotePathBox.Text.Trim().TrimEnd('/');
            if (string.IsNullOrEmpty(cur) || cur == "/")
            {
                await NavigateRemoteDirectoryAsync("/");
                return;
            }
            int idx = cur.LastIndexOf('/');
            string parent = idx <= 0 ? "/" : cur.Substring(0, idx);
            await NavigateRemoteDirectoryAsync(parent);
        }

        private async void RemoteRefresh_Click(object sender, RoutedEventArgs e) => await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());

        private async void RemotePathBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter) await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
        }

        private async void RemoteFilesList_MouseDoubleClick(object sender, MouseButtonEventArgs e)
        {
            if (RemoteFilesList.SelectedItem is not FileEntryItem item) return;
            if (item.IsDirectory)
            {
                await NavigateRemoteDirectoryAsync(item.FullPath);
            }
            else
            {
                await DownloadSingleFileAsync(item);
            }
        }

        private async void UploadSelected_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            var selected = LocalFilesList.SelectedItems.Cast<FileEntryItem>().ToList();
            if (selected.Count == 0) return;

            string remoteDir = RemotePathBox.Text.Trim().TrimEnd('/');
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

                string targetRemoteDir = (string.IsNullOrEmpty(remoteParentDir) || remoteParentDir == "/" ? "" : remoteParentDir.TrimEnd('/')) + "/" + dirName;
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

        private async Task UploadSingleFileAsync(string localFilePath)
        {
            string remoteDir = RemotePathBox.Text.Trim().TrimEnd('/');
            await UploadSingleFileToDirAsync(localFilePath, remoteDir, refreshAfter: true);
        }

        private async Task UploadSingleFileToDirAsync(string localFilePath, string remoteDir, bool refreshAfter)
        {
            if (!IsConnected() || !File.Exists(localFilePath)) return;
            try
            {
                string fileName = Path.GetFileName(localFilePath);
                string cleanRemoteDir = (remoteDir ?? "").Trim().TrimEnd('/');
                string remoteTarget = (string.IsNullOrEmpty(cleanRemoteDir) ? "" : cleanRemoteDir) + "/" + fileName;

                TransferProgressBar.Value = 10;
                StatusText.Text = $"⬆ Lade hoch: {fileName} ...";

                if (_sftpClient != null && _sftpClient.IsConnected)
                {
                    long totalBytes = new FileInfo(localFilePath).Length;
                    await Task.Run(() =>
                    {
                        using var fs = File.OpenRead(localFilePath);
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
            var selected = RemoteFilesList.SelectedItems.Cast<FileEntryItem>().ToList();
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
            if (!IsConnected()) return;
            try
            {
                string safeLocalTarget = FunkenwerkFtpSecurity.ValidateSafeLocalPath(localParentDir, remoteItem.Name);

                if (remoteItem.IsDirectory)
                {
                    Directory.CreateDirectory(safeLocalTarget);
                    if (_sftpClient != null && _sftpClient.IsConnected)
                    {
                        var subEntries = await Task.Run(() => _sftpClient.ListDirectory(remoteItem.FullPath)
                            .Where(x => x.Name != "." && x.Name != "..")
                            .ToList());
                        foreach (var sub in subEntries)
                        {
                            await DownloadItemRecursiveAsync(new FileEntryItem
                            {
                                Name = sub.Name,
                                FullPath = sub.FullName,
                                IsDirectory = sub.IsDirectory,
                                Size = sub.Length
                            }, safeLocalTarget, refreshAfter: false);
                        }
                    }
                    else if (_ftpClient != null && _ftpClient.IsConnected)
                    {
                        var subEntries = await _ftpClient.GetListing(remoteItem.FullPath);
                        foreach (var sub in subEntries.Where(x => x.Name != "." && x.Name != ".."))
                        {
                            await DownloadItemRecursiveAsync(new FileEntryItem
                            {
                                Name = sub.Name,
                                FullPath = sub.FullName,
                                IsDirectory = sub.Type == FtpObjectType.Directory,
                                Size = sub.Size
                            }, safeLocalTarget, refreshAfter: false);
                        }
                    }
                    if (refreshAfter)
                        NavigateLocalDirectory(LocalPathBox.Text.Trim());
                    return;
                }

                TransferProgressBar.Value = 10;
                StatusText.Text = $"⬇ Lade herunter: {remoteItem.Name} ...";

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

        private async void RemoteMkdir_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            try
            {
                string remoteDir = RemotePathBox.Text.Trim().TrimEnd('/');
                string newFolder = (string.IsNullOrEmpty(remoteDir) ? "" : remoteDir) + $"/Ordner_{DateTime.Now:HHmmss}";
                if (_sftpClient != null && _sftpClient.IsConnected)
                    await Task.Run(() => _sftpClient.CreateDirectory(newFolder));
                else if (_ftpClient != null && _ftpClient.IsConnected)
                    await _ftpClient.CreateDirectory(newFolder);

                await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ {ex.Message}";
            }
        }

        private async void RemoteDelete_Click(object sender, RoutedEventArgs e)
        {
            if (!IsConnected()) return;
            var selected = RemoteFilesList.SelectedItems.Cast<FileEntryItem>().ToList();
            foreach (var item in selected)
            {
                await DeleteRemoteRecursiveAsync(item.FullPath, item.IsDirectory);
            }
            await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
        }

        private async Task DeleteRemoteRecursiveAsync(string remotePath, bool isDir)
        {
            try
            {
                if (_sftpClient != null && _sftpClient.IsConnected)
                {
                    await Task.Run(() =>
                    {
                        if (isDir)
                        {
                            foreach (var sub in _sftpClient.ListDirectory(remotePath).Where(x => x.Name != "." && x.Name != ".."))
                            {
                                if (sub.IsDirectory)
                                    DeleteSftpDirectorySync(sub.FullName);
                                else
                                    _sftpClient.DeleteFile(sub.FullName);
                            }
                            _sftpClient.DeleteDirectory(remotePath);
                        }
                        else
                        {
                            _sftpClient.DeleteFile(remotePath);
                        }
                    });
                }
                else if (_ftpClient != null && _ftpClient.IsConnected)
                {
                    if (isDir) await _ftpClient.DeleteDirectory(remotePath);
                    else await _ftpClient.DeleteFile(remotePath);
                }
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Löschen: {ex.Message}";
            }
        }

        private void DeleteSftpDirectorySync(string path)
        {
            if (_sftpClient == null) return;
            foreach (var sub in _sftpClient.ListDirectory(path).Where(x => x.Name != "." && x.Name != ".."))
            {
                if (sub.IsDirectory) DeleteSftpDirectorySync(sub.FullName);
                else _sftpClient.DeleteFile(sub.FullName);
            }
            _sftpClient.DeleteDirectory(path);
        }

        // =====================================================================
        // BIDIRECTIONAL DRAG & DROP (EXPLORER <-> LOKAL <-> SERVER)
        // =====================================================================
        private void LocalFilesList_PreviewMouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            _localDragStartPoint = e.GetPosition(null);
        }

        private void LocalFilesList_PreviewMouseMove(object sender, MouseEventArgs e)
        {
            if (e.LeftButton != MouseButtonState.Pressed || _isDraggingInternal) return;
            Point pos = e.GetPosition(null);
            Vector diff = _localDragStartPoint - pos;
            if (Math.Abs(diff.X) <= 8 && Math.Abs(diff.Y) <= 8) return;

            var selected = LocalFilesList.SelectedItems.Cast<FileEntryItem>().ToList();
            if (selected.Count == 0) return;

            try
            {
                _isDraggingInternal = true;
                var data = new DataObject();
                string[] paths = selected.Select(x => x.FullPath).ToArray();
                data.SetData(DataFormats.FileDrop, paths);
                data.SetData(DRAG_FORMAT_LOCAL, paths);
                DragDrop.DoDragDrop(LocalFilesList, data, DragDropEffects.Copy);
            }
            catch { }
            finally
            {
                _isDraggingInternal = false;
            }
        }

        private void RemoteFilesList_PreviewMouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            _remoteDragStartPoint = e.GetPosition(null);
        }

        private void RemoteFilesList_PreviewMouseMove(object sender, MouseEventArgs e)
        {
            if (e.LeftButton != MouseButtonState.Pressed || _isDraggingInternal || !IsConnected()) return;
            Point pos = e.GetPosition(null);
            Vector diff = _remoteDragStartPoint - pos;
            if (Math.Abs(diff.X) <= 8 && Math.Abs(diff.Y) <= 8) return;

            var selected = RemoteFilesList.SelectedItems.Cast<FileEntryItem>().ToList();
            if (selected.Count == 0) return;

            try
            {
                _isDraggingInternal = true;
                var data = new DataObject();
                data.SetData(DRAG_FORMAT_REMOTE, selected);
                DragDrop.DoDragDrop(RemoteFilesList, data, DragDropEffects.Copy);
            }
            catch { }
            finally
            {
                _isDraggingInternal = false;
            }
        }

        private void LocalTile_PreviewDragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DRAG_FORMAT_REMOTE) || e.Data.GetDataPresent(DataFormats.FileDrop))
            {
                e.Effects = DragDropEffects.Copy;
                LocalTileBorder.BorderBrush = BrushHex("#C5A059");
            }
            else
            {
                e.Effects = DragDropEffects.None;
            }
            e.Handled = true;
        }

        private void LocalTile_DragLeave(object sender, DragEventArgs e)
        {
            LocalTileBorder.BorderBrush = BrushHex(_isDarkMode ? "#242938" : "#E5E7EB");
        }

        private async void LocalTile_PreviewDrop(object sender, DragEventArgs e)
        {
            LocalTileBorder.BorderBrush = BrushHex(_isDarkMode ? "#242938" : "#E5E7EB");
            e.Handled = true;

            string localBase = LocalPathBox.Text.Trim();
            if (string.IsNullOrWhiteSpace(localBase) || !Directory.Exists(localBase)) return;

            // 1. Dropped from SERVER pane -> Download to LOKAL
            if (e.Data.GetDataPresent(DRAG_FORMAT_REMOTE) &&
                e.Data.GetData(DRAG_FORMAT_REMOTE) is List<FileEntryItem> remoteItems)
            {
                foreach (var item in remoteItems)
                {
                    await DownloadItemRecursiveAsync(item, localBase, refreshAfter: false);
                }
                NavigateLocalDirectory(localBase);
                return;
            }

            // 2. Dropped from Windows Explorer onto LOKAL pane
            if (e.Data.GetDataPresent(DataFormats.FileDrop) &&
                !e.Data.GetDataPresent(DRAG_FORMAT_LOCAL) &&
                e.Data.GetData(DataFormats.FileDrop) is string[] droppedPaths &&
                droppedPaths.Length > 0)
            {
                // If user dropped a single directory from Explorer, navigate LOKAL into it; otherwise copy files into current LOKAL directory
                if (droppedPaths.Length == 1 && Directory.Exists(droppedPaths[0]))
                {
                    NavigateLocalDirectory(droppedPaths[0]);
                    StatusText.Text = $"📂 Lokales Verzeichnis geöffnet: {droppedPaths[0]}";
                    return;
                }

                int copied = 0;
                foreach (string src in droppedPaths)
                {
                    try
                    {
                        if (File.Exists(src))
                        {
                            string dest = FunkenwerkFtpSecurity.ValidateSafeLocalPath(localBase, Path.GetFileName(src));
                            if (!string.Equals(Path.GetFullPath(src), dest, StringComparison.OrdinalIgnoreCase))
                            {
                                File.Copy(src, dest, true);
                                copied++;
                            }
                        }
                    }
                    catch { }
                }
                NavigateLocalDirectory(localBase);
                if (copied > 0)
                    StatusText.Text = $"✓ {copied} Datei(en) in lokales Verzeichnis übernommen.";
            }
        }

        private void RemoteTile_PreviewDragOver(object sender, DragEventArgs e)
        {
            if (IsConnected() && (e.Data.GetDataPresent(DataFormats.FileDrop) || e.Data.GetDataPresent(DRAG_FORMAT_LOCAL)))
            {
                e.Effects = DragDropEffects.Copy;
                RemoteTileBorder.BorderBrush = BrushHex("#C5A059");
            }
            else
            {
                e.Effects = DragDropEffects.None;
            }
            e.Handled = true;
        }

        private void RemoteTile_DragLeave(object sender, DragEventArgs e)
        {
            RemoteTileBorder.BorderBrush = BrushHex(_isDarkMode ? "#242938" : "#E5E7EB");
        }

        private async void RemoteTile_PreviewDrop(object sender, DragEventArgs e)
        {
            RemoteTileBorder.BorderBrush = BrushHex(_isDarkMode ? "#242938" : "#E5E7EB");
            e.Handled = true;

            if (!IsConnected())
            {
                StatusText.Text = "⚠ Bitte zuerst mit dem Server verbinden, bevor Dateien per Drag & Drop hochgeladen werden.";
                return;
            }

            string[]? paths = null;
            if (e.Data.GetDataPresent(DRAG_FORMAT_LOCAL))
                paths = e.Data.GetData(DRAG_FORMAT_LOCAL) as string[];
            else if (e.Data.GetDataPresent(DataFormats.FileDrop))
                paths = e.Data.GetData(DataFormats.FileDrop) as string[];

            if (paths == null || paths.Length == 0) return;

            string remoteDir = RemotePathBox.Text.Trim().TrimEnd('/');
            foreach (string p in paths)
            {
                await UploadPathRecursiveAsync(p, remoteDir);
            }
            await NavigateRemoteDirectoryAsync(RemotePathBox.Text.Trim());
        }

        private void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            _isDarkMode = !_isDarkMode;
            ThemeToggleBtn.Content = _isDarkMode ? "☀" : "🌙";

            var rootBg = BrushHex(_isDarkMode ? "#0F1117" : "#F3F4F6");
            var barBg = BrushHex(_isDarkMode ? "#12151E" : "#FFFFFF");
            var tileBg = BrushHex(_isDarkMode ? "#141821" : "#FFFFFF");
            var borderCol = BrushHex(_isDarkMode ? "#232734" : "#E5E7EB");
            var inputBg = BrushHex(_isDarkMode ? "#181C26" : "#F9FAFB");
            var textPrimary = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827");

            RootGrid.Background = rootBg;
            TopBarBorder.Background = barBg;
            TopBarBorder.BorderBrush = borderCol;
            BottomBarBorder.Background = barBg;
            BottomBarBorder.BorderBrush = borderCol;
            LocalTileBorder.Background = tileBg;
            LocalTileBorder.BorderBrush = borderCol;
            RemoteTileBorder.Background = tileBg;
            RemoteTileBorder.BorderBrush = borderCol;

            foreach (var tb in new[] { HostBox, PortBox, UserBox, LocalPathBox, RemotePathBox })
            {
                tb.Background = inputBg;
                tb.Foreground = textPrimary;
                tb.BorderBrush = borderCol;
            }
            PassContainerBorder.Background = inputBg;
            PassContainerBorder.BorderBrush = borderCol;
            PassBox.Foreground = textPrimary;
            PassVisibleBox.Foreground = textPrimary;
            LocalFilesList.Foreground = textPrimary;
            RemoteFilesList.Foreground = textPrimary;
        }

        private static SolidColorBrush BrushHex(string hex) =>
            new((Color)ColorConverter.ConvertFromString(hex));

        private async void Window_Closing(object sender, System.ComponentModel.CancelEventArgs e)
        {
            await DisconnectServerAsync();
        }
    }
}
