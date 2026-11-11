using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Win32;
using Renci.SshNet;
using Renci.SshNet.Common;

namespace FunkenwerkSsh
{
    public partial class MainWindow : Window
    {
        private List<SshConnectionProfile> _profiles = new();
        private bool _isDarkMode = true;
        private bool _isPassVisible = false;
        private bool _suppressPassSync = false;
        private string _selectedPrivateKeyPath = "";

        private SshClient? _sshClient;
        private SftpClient? _sftpFallbackClient;
        private ShellStream? _shellStream;
        private CancellationTokenSource? _readerCts;
        private string _lastPinnedFingerprint = "";
        private string _currentRemoteDir = "/";
        private string _activeUserHost = "";
        private string _sftpInputBuffer = "";

        private enum TerminalSessionMode { None, PtyShell, ExecShell, SftpShell }
        private TerminalSessionMode _sessionMode = TerminalSessionMode.None;

        private readonly PuTTYAnsiScreenBuffer _screen = new();

        public MainWindow()
        {
            InitializeComponent();
            LoadProfilesIntoUi();
            _screen.Reset();
            _screen.WriteSystemLine("Funkenwerk SSH · Bereit. Wähle links eine Verbindung oder klicke auf VERBINDEN.");
            RenderTerminalScreen();
            Loaded += MainWindow_Loaded;
        }

        private async void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            var ticket = FunkenwerkSshSecurity.ConsumeHandoffTicket();
            if (ticket == null || string.IsNullOrWhiteSpace(ticket.Host)) return;

            var match = _profiles.FirstOrDefault(p =>
                string.Equals(p.Host, ticket.Host, StringComparison.OrdinalIgnoreCase) &&
                string.Equals(p.Username, ticket.Username, StringComparison.OrdinalIgnoreCase))
                ?? _profiles.FirstOrDefault(p => string.Equals(p.Host, ticket.Host, StringComparison.OrdinalIgnoreCase));

            if (match != null)
            {
                match.Username = ticket.Username;
                match.StartupDirectory = ticket.RemoteDirectory;
                if (!string.IsNullOrEmpty(ticket.Password))
                    match.EncryptedPassword = FunkenwerkSshSecurity.EncryptSecret(ticket.Password);
                if (!string.IsNullOrWhiteSpace(ticket.PrivateKeyPath))
                    match.PrivateKeyPath = ticket.PrivateKeyPath;
                ProfilesListBox.SelectedItem = match;
            }

            HostBox.Text = ticket.Host;
            PortBox.Text = ticket.Port.ToString();
            UserBox.Text = ticket.Username;
            SetPasswordValue(ticket.Password);
            _selectedPrivateKeyPath = ticket.PrivateKeyPath ?? "";

            await ConnectOrReconnectSshAsync(ticket.RemoteDirectory);
        }

        private void LoadProfilesIntoUi()
        {
            _profiles = FunkenwerkSshSecurity.LoadAndImportProfiles();
            ProfilesListBox.ItemsSource = null;
            ProfilesListBox.ItemsSource = _profiles;
            if (_profiles.Count > 0)
            {
                var preferred = _profiles.FirstOrDefault(p => p.Name.Equals("Mittwald", StringComparison.OrdinalIgnoreCase))
                                ?? _profiles.FirstOrDefault(p => p.Name.Contains("Mittwald", StringComparison.OrdinalIgnoreCase))
                                ?? _profiles[0];
                ProfilesListBox.SelectedItem = preferred;
            }
        }

        private void ProfilesListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (ProfilesListBox.SelectedItem is not SshConnectionProfile p) return;

            HostBox.Text = p.Host;
            PortBox.Text = p.Port.ToString();
            UserBox.Text = p.Username;
            SetPasswordValue(FunkenwerkSshSecurity.DecryptSecret(p.EncryptedPassword));
            _selectedPrivateKeyPath = p.PrivateKeyPath ?? "";
            PickKeyBtn.ToolTip = string.IsNullOrWhiteSpace(_selectedPrivateKeyPath)
                ? "OpenSSH Private Key wählen"
                : $"SSH Key: {_selectedPrivateKeyPath}";
        }

        private async void ProfilesListBox_MouseDoubleClick(object sender, MouseButtonEventArgs e)
        {
            if (ProfilesListBox.SelectedItem is SshConnectionProfile)
            {
                await ConnectOrReconnectSshAsync();
            }
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
                StatusText.Text = $"🔑 SSH-Key ausgewählt: {Path.GetFileName(_selectedPrivateKeyPath)}";
            }
        }

        private void NewProfile_Click(object sender, RoutedEventArgs e)
        {
            ProfilesListBox.SelectedItem = null;
            HostBox.Text = "";
            PortBox.Text = "22";
            UserBox.Text = "";
            SetPasswordValue("");
            HostBox.Focus();
        }

        private void SaveProfile_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                string host = FunkenwerkSshSecurity.SanitizeHost(HostBox.Text);
                if (string.IsNullOrWhiteSpace(host)) return;
                int port = int.TryParse(PortBox.Text, out int pt) ? pt : 22;
                string user = UserBox.Text.Trim();
                string pass = GetPasswordValue();

                if (ProfilesListBox.SelectedItem is SshConnectionProfile current &&
                    string.Equals(current.Host, host, StringComparison.OrdinalIgnoreCase))
                {
                    current.Host = host;
                    current.Port = port;
                    current.Username = user;
                    current.EncryptedPassword = FunkenwerkSshSecurity.EncryptSecret(pass);
                    current.PrivateKeyPath = _selectedPrivateKeyPath;
                }
                else
                {
                    var created = new SshConnectionProfile
                    {
                        Name = string.IsNullOrWhiteSpace(user) ? host : $"{user}@{host}",
                        Host = host,
                        Port = port,
                        Username = user,
                        EncryptedPassword = FunkenwerkSshSecurity.EncryptSecret(pass),
                        PrivateKeyPath = _selectedPrivateKeyPath,
                        Source = "Manuell"
                    };
                    _profiles.Add(created);
                    ProfilesListBox.ItemsSource = null;
                    ProfilesListBox.ItemsSource = _profiles;
                    ProfilesListBox.SelectedItem = created;
                }

                FunkenwerkSshSecurity.SaveProfiles(_profiles);
                ProfilesListBox.Items.Refresh();
                StatusText.Text = "🔒 SSH-Profil AES-256-GCM verschlüsselt gespeichert.";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ {ex.Message}";
            }
        }

        private void DeleteProfile_Click(object sender, RoutedEventArgs e)
        {
            if (ProfilesListBox.SelectedItem is not SshConnectionProfile current) return;
            _profiles.Remove(current);
            FunkenwerkSshSecurity.SaveProfiles(_profiles);
            ProfilesListBox.ItemsSource = null;
            ProfilesListBox.ItemsSource = _profiles;
            if (_profiles.Count > 0) ProfilesListBox.SelectedIndex = 0;
        }

        private async void Connect_Click(object sender, RoutedEventArgs e)
        {
            if (IsAnySessionConnected())
            {
                await DisconnectSshAsync();
                return;
            }
            await ConnectOrReconnectSshAsync();
        }

        private bool IsAnySessionConnected()
        {
            return (_sshClient != null && _sshClient.IsConnected) ||
                   (_sftpFallbackClient != null && _sftpFallbackClient.IsConnected);
        }

        private static string ExtractPublicKeyComment(string keyPath)
        {
            try
            {
                string pubPath = keyPath + ".pub";
                if (File.Exists(pubPath))
                {
                    string[] parts = File.ReadAllText(pubPath).Trim().Split(' ', StringSplitOptions.RemoveEmptyEntries);
                    if (parts.Length >= 3) return parts[2];
                }
            }
            catch { }
            return "kontakt@mein-seelenfunke.de";
        }

        private async Task ConnectOrReconnectSshAsync(string? overrideStartupDir = null)
        {
            await DisconnectSshAsync();

            try
            {
                string host = FunkenwerkSshSecurity.SanitizeHost(HostBox.Text);
                if (string.IsNullOrWhiteSpace(host))
                {
                    StatusText.Text = "⚠ Bitte Hostnamen eingeben.";
                    return;
                }

                int port = int.TryParse(PortBox.Text, out int pt) ? pt : 22;
                string user = UserBox.Text.Trim();

                // Automatically map Mittwald SFTP-only user (sftp-...) to the interactive SSH container user
                if (host.Equals("ssh.altgemeinde.project.host", StringComparison.OrdinalIgnoreCase) &&
                    (string.IsNullOrWhiteSpace(user) || user.StartsWith("sftp-", StringComparison.OrdinalIgnoreCase)))
                {
                    user = "kontakt@mein-seelenfunke.de@a-lc6tkm";
                    UserBox.Text = user;
                }

                if (string.IsNullOrWhiteSpace(user))
                {
                    StatusText.Text = "⚠ Bitte Benutzernamen eingeben.";
                    UserBox.Focus();
                    return;
                }

                string pass = GetPasswordValue();
                var authMethods = new List<AuthenticationMethod>();
                bool hasPublicKeyAuth = false;
                string keyComment = "kontakt@mein-seelenfunke.de";

                if (string.IsNullOrWhiteSpace(_selectedPrivateKeyPath))
                {
                    string defaultKey = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh", "id_ed25519");
                    if (File.Exists(defaultKey)) _selectedPrivateKeyPath = defaultKey;
                }

                if (!string.IsNullOrWhiteSpace(_selectedPrivateKeyPath) && File.Exists(_selectedPrivateKeyPath))
                {
                    keyComment = ExtractPublicKeyComment(_selectedPrivateKeyPath);
                    try
                    {
                        var pkFile = new PrivateKeyFile(_selectedPrivateKeyPath);
                        authMethods.Add(new PrivateKeyAuthenticationMethod(user, pkFile));
                        hasPublicKeyAuth = true;
                    }
                    catch
                    {
                        if (!string.IsNullOrEmpty(pass))
                        {
                            try
                            {
                                var pkFileEnc = new PrivateKeyFile(_selectedPrivateKeyPath, pass);
                                authMethods.Add(new PrivateKeyAuthenticationMethod(user, pkFileEnc));
                                hasPublicKeyAuth = true;
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
                    return;
                }

                ConnectBtn.IsEnabled = false;
                StatusText.Text = $"⏳ Verbinde mit {user}@{host}:{port} ...";

                _screen.Reset();
                _screen.WriteHeaderLine($"Using username \"{user}\"");
                if (hasPublicKeyAuth)
                    _screen.WriteHeaderLine($"Authenticating with public key \"{keyComment}\"");
                else
                    _screen.WriteHeaderLine($"Authenticating with password for \"{user}\"");
                RenderTerminalScreen();

                var connInfo = new ConnectionInfo(host, port, user, authMethods.ToArray())
                {
                    Timeout = TimeSpan.FromSeconds(12)
                };

                var client = new SshClient(connInfo)
                {
                    KeepAliveInterval = TimeSpan.FromSeconds(15)
                };

                bool mitmAbort = false;
                string negotiatedKeyAlgo = "";

                client.HostKeyReceived += (s, hkArgs) =>
                {
                    negotiatedKeyAlgo = hkArgs.HostKeyName;
                    var verify = FunkenwerkSshSecurity.VerifyAndPinHostKey(host, port, hkArgs.HostKeyName, hkArgs.HostKey);
                    _lastPinnedFingerprint = verify.FingerprintSha256;
                    if (verify.IsChanged)
                    {
                        mitmAbort = true;
                        hkArgs.CanTrust = false;
                    }
                    else
                    {
                        hkArgs.CanTrust = verify.IsTrusted;
                    }
                };

                await Task.Run(() => client.Connect());

                if (mitmAbort)
                {
                    client.Dispose();
                    _screen.WriteSystemLine("🚨 Host-Key geändert! Verbindung durch MITM-Schutz blockiert.");
                    RenderTerminalScreen();
                    StatusText.Text = "🚨 MITM-Schutz hat die Verbindung blockiert (Host-Key geändert)!";
                    ConnectBtn.IsEnabled = true;
                    return;
                }

                string targetDir = !string.IsNullOrWhiteSpace(overrideStartupDir)
                    ? overrideStartupDir.Trim()
                    : (ProfilesListBox.SelectedItem as SshConnectionProfile)?.StartupDirectory?.Trim() ?? "";
                _currentRemoteDir = string.IsNullOrWhiteSpace(targetDir) ? "/" : targetDir;
                _activeUserHost = $"{user}@{host}";

                var terminalModes = new Dictionary<TerminalModes, uint>
                {
                    { TerminalModes.ECHO, 1 }
                };

                ShellStream? shell = null;
                try
                {
                    shell = await Task.Run(() => client.CreateShellStream("xterm-256color", 140, 42, 1120, 680, 8192, terminalModes));
                    _sessionMode = TerminalSessionMode.PtyShell;
                }
                catch
                {
                    try
                    {
                        string testOut = await Task.Run(() =>
                        {
                            using var cmd = client.CreateCommand("pwd");
                            return cmd.Execute();
                        });
                        if (!string.IsNullOrWhiteSpace(testOut))
                            _sessionMode = TerminalSessionMode.ExecShell;
                    }
                    catch { }
                }

                if (_sessionMode == TerminalSessionMode.PtyShell && shell != null)
                {
                    _sshClient = client;
                    _shellStream = shell;

                    // If a startup directory is specified (from profile or Funkenwerk FTP handoff), cd into it and clear cleanly
                    if (!string.IsNullOrWhiteSpace(targetDir) && targetDir != "/")
                    {
                        string safeDir = targetDir.Replace("\"", "");
                        byte[] initCmd = Encoding.UTF8.GetBytes($"cd \"{safeDir}\" && clear\r");
                        await shell.WriteAsync(initCmd, 0, initCmd.Length);
                        await shell.FlushAsync();
                    }

                    _readerCts = new CancellationTokenSource();
                    _ = ReadShellStreamLoopAsync(shell, _readerCts.Token);
                }
                else
                {
                    // SFTP fallback shell for SFTP-only server accounts
                    try { client.Disconnect(); client.Dispose(); } catch { }
                    var sftp = new SftpClient(connInfo) { KeepAliveInterval = TimeSpan.FromSeconds(15) };
                    sftp.HostKeyReceived += (s, hkArgs) =>
                    {
                        var verify = FunkenwerkSshSecurity.VerifyAndPinHostKey(host, port, hkArgs.HostKeyName, hkArgs.HostKey);
                        hkArgs.CanTrust = !verify.IsChanged && verify.IsTrusted;
                    };
                    await Task.Run(() => sftp.Connect());
                    _sftpFallbackClient = sftp;
                    _sessionMode = TerminalSessionMode.SftpShell;

                    await Task.Run(() =>
                    {
                        try
                        {
                            if (!string.IsNullOrWhiteSpace(targetDir) && sftp.Exists(targetDir))
                                sftp.ChangeDirectory(targetDir);
                            _currentRemoteDir = sftp.WorkingDirectory;
                        }
                        catch { _currentRemoteDir = sftp.WorkingDirectory; }
                    });
                    _screen.ProcessAnsiStream($"\x1B[1;37m\x1B[48;2;132;122;253m {user} @ \x1B[0m\x1B[1;37m\x1B[48;2;0;84;245m [{host}] \x1B[0m\x1B[1;37m\x1B[48;2;0;199;149m {_currentRemoteDir} \x1B[0m ");
                    RenderTerminalScreen();
                }

                ConnectBtn.Content = "TRENNEN";
                ConnectBtn.Background = new SolidColorBrush(Color.FromRgb(239, 68, 68));
                ConnectBtn.Foreground = Brushes.White;
                StatusText.Text = $"🔒 Verbunden: {user}@{host}:{port} ({negotiatedKeyAlgo} · {_lastPinnedFingerprint})";

                TerminalRichBox.Focus();
            }
            catch (Exception ex)
            {
                _screen.WriteSystemLine($"✗ [SSH Fehler] {ex.Message}");
                RenderTerminalScreen();
                StatusText.Text = $"⚠ SSH-Verbindung fehlgeschlagen: {ex.Message}";
            }
            finally
            {
                ConnectBtn.IsEnabled = true;
            }
        }

        private async Task ReadShellStreamLoopAsync(ShellStream stream, CancellationToken ct)
        {
            byte[] buffer = new byte[8192];
            try
            {
                while (!ct.IsCancellationRequested && _sshClient != null && _sshClient.IsConnected)
                {
                    if (stream.DataAvailable)
                    {
                        int read = await stream.ReadAsync(buffer, 0, buffer.Length, ct);
                        if (read > 0)
                        {
                            string chunk = Encoding.UTF8.GetString(buffer, 0, read);
                            await Dispatcher.InvokeAsync(() =>
                            {
                                _screen.ProcessAnsiStream(chunk);
                                RenderTerminalScreen();
                            });
                        }
                    }
                    else
                    {
                        await Task.Delay(20, ct);
                    }
                }
            }
            catch { }
        }

        private async Task DisconnectSshAsync()
        {
            try
            {
                _readerCts?.Cancel();
                _readerCts?.Dispose();
                _readerCts = null;

                if (_shellStream != null)
                {
                    try { _shellStream.Dispose(); } catch { }
                    _shellStream = null;
                }

                if (_sshClient != null)
                {
                    var c = _sshClient;
                    _sshClient = null;
                    await Task.Run(() =>
                    {
                        try { c.Disconnect(); } catch { }
                        try { c.Dispose(); } catch { }
                    });
                }

                if (_sftpFallbackClient != null)
                {
                    var sc = _sftpFallbackClient;
                    _sftpFallbackClient = null;
                    await Task.Run(() =>
                    {
                        try { sc.Disconnect(); } catch { }
                        try { sc.Dispose(); } catch { }
                    });
                }
                _sessionMode = TerminalSessionMode.None;
            }
            catch { }

            ConnectBtn.Content = "⚡ VERBINDEN";
            ConnectBtn.Background = new SolidColorBrush(Color.FromRgb(197, 160, 89));
            ConnectBtn.Foreground = new SolidColorBrush(Color.FromRgb(15, 17, 23));
            StatusText.Text = "🔒 SSHv2 Outbound-Only (0 offene Ports) · Verbindung getrennt.";
        }

        // =====================================================================
        // DIRECT PUTTY-STYLE TERMINAL KEYBOARD & MOUSE INPUT
        // =====================================================================
        private void TerminalSurface_MouseDown(object sender, MouseButtonEventArgs e)
        {
            TerminalRichBox.Focus();
        }

        private void TerminalRichBox_MouseRightButtonUp(object sender, MouseButtonEventArgs e)
        {
            // PuTTY behavior: Right-click pastes clipboard into terminal
            PasteClipboardToTerminal();
            e.Handled = true;
        }

        private void SendRawToShell(string data)
        {
            if (_sessionMode == TerminalSessionMode.PtyShell && _shellStream != null && _sshClient != null && _sshClient.IsConnected)
            {
                try
                {
                    byte[] bytes = Encoding.UTF8.GetBytes(data);
                    _shellStream.Write(bytes, 0, bytes.Length);
                    _shellStream.Flush();
                }
                catch { }
            }
            else if (_sessionMode == TerminalSessionMode.SftpShell)
            {
                HandleSftpKeyInput(data);
            }
        }

        private void PasteClipboardToTerminal()
        {
            try
            {
                if (Clipboard.ContainsText())
                {
                    string text = Clipboard.GetText().Replace("\r\n", "\r").Replace("\n", "\r");
                    if (!string.IsNullOrEmpty(text))
                        SendRawToShell(text);
                }
            }
            catch { }
        }

        private void TerminalRichBox_PreviewTextInput(object sender, TextCompositionEventArgs e)
        {
            if (!IsAnySessionConnected() || string.IsNullOrEmpty(e.Text)) return;

            // Ignore control characters here because PreviewKeyDown handles Enter, Backspace, Tab, Ctrl+C, Esc
            if (e.Text.Length == 1 && (e.Text[0] < 32 || e.Text[0] == 127))
            {
                e.Handled = true;
                return;
            }

            SendRawToShell(e.Text);
            e.Handled = true;
        }

        private void TerminalRichBox_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (!IsAnySessionConnected()) return;

            bool ctrl = (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control;

            if (ctrl)
            {
                if (e.Key == Key.C)
                {
                    SendRawToShell("\x03"); // SIGINT
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.V)
                {
                    PasteClipboardToTerminal();
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.D)
                {
                    SendRawToShell("\x04"); // EOF
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.L)
                {
                    SendRawToShell("\x0C"); // Clear
                    e.Handled = true;
                    return;
                }
            }

            switch (e.Key)
            {
                case Key.Enter:
                    SendRawToShell("\r");
                    e.Handled = true;
                    break;
                case Key.Back:
                    SendRawToShell("\x7F");
                    e.Handled = true;
                    break;
                case Key.Tab:
                    SendRawToShell("\t");
                    e.Handled = true;
                    break;
                case Key.Space:
                    SendRawToShell(" ");
                    e.Handled = true;
                    break;
                case Key.Up:
                    SendRawToShell("\x1B[A");
                    e.Handled = true;
                    break;
                case Key.Down:
                    SendRawToShell("\x1B[B");
                    e.Handled = true;
                    break;
                case Key.Right:
                    SendRawToShell("\x1B[C");
                    e.Handled = true;
                    break;
                case Key.Left:
                    SendRawToShell("\x1B[D");
                    e.Handled = true;
                    break;
                case Key.Home:
                    SendRawToShell("\x1B[H");
                    e.Handled = true;
                    break;
                case Key.End:
                    SendRawToShell("\x1B[F");
                    e.Handled = true;
                    break;
                case Key.Delete:
                    SendRawToShell("\x1B[3~");
                    e.Handled = true;
                    break;
                case Key.Escape:
                    SendRawToShell("\x1B");
                    e.Handled = true;
                    break;
            }
        }

        private async void HandleSftpKeyInput(string data)
        {
            if (_sftpFallbackClient == null || !_sftpFallbackClient.IsConnected) return;
            foreach (char ch in data)
            {
                if (ch == '\r' || ch == '\n')
                {
                    string cmd = _sftpInputBuffer.Trim();
                    _sftpInputBuffer = "";
                    _screen.ProcessAnsiStream("\r\n");
                    if (!string.IsNullOrWhiteSpace(cmd))
                    {
                        await Task.Run(() =>
                        {
                            try
                            {
                                if (cmd == "pwd") _screen.ProcessAnsiStream(_currentRemoteDir + "\r\n");
                                else if (cmd.StartsWith("cd "))
                                {
                                    _sftpFallbackClient.ChangeDirectory(cmd.Substring(3).Trim().Trim('"', '\''));
                                    _currentRemoteDir = _sftpFallbackClient.WorkingDirectory;
                                }
                                else if (cmd == "ls" || cmd == "ll" || cmd == "dir")
                                {
                                    foreach (var item in _sftpFallbackClient.ListDirectory(_currentRemoteDir).Where(x => x.Name != "." && x.Name != ".."))
                                        _screen.ProcessAnsiStream((item.IsDirectory ? $"\x1B[1;34m{item.Name}/\x1B[0m  " : $"{item.Name}  "));
                                    _screen.ProcessAnsiStream("\r\n");
                                }
                            }
                            catch (Exception ex) { _screen.ProcessAnsiStream($"{ex.Message}\r\n"); }
                        });
                    }
                    _screen.ProcessAnsiStream($"\x1B[1;37m\x1B[48;2;132;122;253m {_activeUserHost} \x1B[0m\x1B[1;37m\x1B[48;2;0;199;149m {_currentRemoteDir} \x1B[0m ");
                    RenderTerminalScreen();
                }
                else if (ch == '\x7F' || ch == '\b')
                {
                    if (_sftpInputBuffer.Length > 0)
                    {
                        _sftpInputBuffer = _sftpInputBuffer.Substring(0, _sftpInputBuffer.Length - 1);
                        _screen.ProcessAnsiStream("\b\x1B[K");
                        RenderTerminalScreen();
                    }
                }
                else if (ch >= 32)
                {
                    _sftpInputBuffer += ch;
                    _screen.ProcessAnsiStream(ch.ToString());
                    RenderTerminalScreen();
                }
            }
        }

        private void ClearTerminal_Click(object sender, RoutedEventArgs e)
        {
            if (_sessionMode == TerminalSessionMode.PtyShell)
            {
                SendRawToShell("\x0C");
            }
            else
            {
                _screen.ClearKeepHeader();
                RenderTerminalScreen();
            }
            TerminalRichBox.Focus();
        }

        private void RenderTerminalScreen()
        {
            _screen.RenderInto(TerminalDoc);
            TerminalRichBox.ScrollToEnd();
        }

        private void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            _isDarkMode = !_isDarkMode;
            ThemeToggleBtn.Content = _isDarkMode ? "☀" : "🌙";

            var rootBg = BrushHex(_isDarkMode ? "#0F1117" : "#F3F4F6");
            var barBg = BrushHex(_isDarkMode ? "#12151E" : "#FFFFFF");
            var sideBg = BrushHex(_isDarkMode ? "#141821" : "#FFFFFF");
            var borderCol = BrushHex(_isDarkMode ? "#232734" : "#E5E7EB");
            var inputBg = BrushHex(_isDarkMode ? "#181C26" : "#F9FAFB");
            var textPrimary = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827");

            RootGrid.Background = rootBg;
            TopBarBorder.Background = barBg;
            TopBarBorder.BorderBrush = borderCol;
            BottomBarBorder.Background = barBg;
            BottomBarBorder.BorderBrush = borderCol;
            SidebarTileBorder.Background = sideBg;
            SidebarTileBorder.BorderBrush = borderCol;

            foreach (var tb in new[] { HostBox, PortBox, UserBox })
            {
                tb.Background = inputBg;
                tb.Foreground = textPrimary;
                tb.BorderBrush = borderCol;
            }
            PassContainerBorder.Background = inputBg;
            PassContainerBorder.BorderBrush = borderCol;
            PassBox.Foreground = textPrimary;
            PassVisibleBox.Foreground = textPrimary;
        }

        private static SolidColorBrush BrushHex(string hex) =>
            new((Color)ColorConverter.ConvertFromString(hex));

        private async void Window_Closing(object sender, System.ComponentModel.CancelEventArgs e)
        {
            await DisconnectSshAsync();
        }
    }

    /// <summary>
    /// PuTTY-Style VT100 / xterm-256color / 24-Bit TrueColor Screen Buffer & WPF FlowDocument Renderer.
    /// Supports 38;2;R;G;B / 48;2;R;G;B TrueColor badges (e.g. Mittwald's Violet/Blue/Green prompt),
    /// cursor movements, backspace/erase-in-line (\x1B[K), clear screen (\x1B[2J), and the classic PuTTY Green Block Cursor.
    /// </summary>
    internal sealed class PuTTYAnsiScreenBuffer
    {
        public struct StyledCell : IEquatable<StyledCell>
        {
            public char Ch;
            public Color? Fg;
            public Color? Bg;
            public bool Bold;

            public bool SameStyle(StyledCell other) =>
                Fg == other.Fg && Bg == other.Bg && Bold == other.Bold;

            public bool Equals(StyledCell other) =>
                Ch == other.Ch && Fg == other.Fg && Bg == other.Bg && Bold == other.Bold;
        }

        private readonly List<List<StyledCell>> _lines = new();
        private int _headerLineCount = 0;
        private int _row = 0;
        private int _col = 0;
        private Color? _curFg = null;
        private Color? _curBg = null;
        private bool _curBold = false;
        private string _pendingEscape = "";

        private static readonly Color DefaultFgColor = Color.FromRgb(217, 217, 217); // PuTTY light gray
        private static readonly Color PuTTYCursorGreen = Color.FromRgb(0, 255, 0);   // Classic PuTTY #00FF00 cursor

        public void Reset()
        {
            _lines.Clear();
            _lines.Add(new List<StyledCell>());
            _headerLineCount = 0;
            _row = 0;
            _col = 0;
            _curFg = null;
            _curBg = null;
            _curBold = false;
            _pendingEscape = "";
        }

        public void WriteHeaderLine(string text)
        {
            EnsureRow(_row);
            _lines[_row].Clear();
            foreach (char c in text)
            {
                _lines[_row].Add(new StyledCell { Ch = c, Fg = DefaultFgColor, Bg = null, Bold = false });
            }
            _row++;
            EnsureRow(_row);
            _col = 0;
            _headerLineCount = _row;
        }

        public void WriteSystemLine(string text)
        {
            EnsureRow(_row);
            foreach (char c in text)
            {
                _lines[_row].Add(new StyledCell { Ch = c, Fg = DefaultFgColor, Bg = null, Bold = false });
            }
            _row++;
            EnsureRow(_row);
            _col = 0;
        }

        public void ClearKeepHeader()
        {
            while (_lines.Count > _headerLineCount)
                _lines.RemoveAt(_lines.Count - 1);
            _row = _headerLineCount;
            EnsureRow(_row);
            _col = 0;
        }

        private void EnsureRow(int r)
        {
            while (_lines.Count <= r)
                _lines.Add(new List<StyledCell>());
        }

        public void ProcessAnsiStream(string chunk)
        {
            if (string.IsNullOrEmpty(chunk)) return;
            string data = _pendingEscape + chunk;
            _pendingEscape = "";

            int i = 0;
            int len = data.Length;

            while (i < len)
            {
                char c = data[i];

                if (c == '\x1B')
                {
                    if (i + 1 >= len)
                    {
                        _pendingEscape = data.Substring(i);
                        break;
                    }

                    char next = data[i + 1];

                    // 1. OSC Sequence: \x1B ] ... (\x07 or \x1B\\)
                    if (next == ']')
                    {
                        int endOsc = -1;
                        int advance = 1;
                        for (int k = i + 2; k < len; k++)
                        {
                            if (data[k] == '\x07')
                            {
                                endOsc = k;
                                advance = 1;
                                break;
                            }
                            if (data[k] == '\x1B' && k + 1 < len && data[k + 1] == '\\')
                            {
                                endOsc = k;
                                advance = 2;
                                break;
                            }
                        }
                        if (endOsc == -1)
                        {
                            _pendingEscape = data.Substring(i);
                            break;
                        }
                        i = endOsc + advance;
                        continue;
                    }

                    // 2. CSI Sequence: \x1B [ ... <finalByte 0x40..0x7E>
                    if (next == '[')
                    {
                        int finalIdx = -1;
                        for (int k = i + 2; k < len; k++)
                        {
                            char fc = data[k];
                            if (fc >= '@' && fc <= '~')
                            {
                                finalIdx = k;
                                break;
                            }
                        }
                        if (finalIdx == -1)
                        {
                            _pendingEscape = data.Substring(i);
                            break;
                        }

                        string paramStr = data.Substring(i + 2, finalIdx - (i + 2));
                        char finalCmd = data[finalIdx];
                        HandleCsiSequence(paramStr, finalCmd);
                        i = finalIdx + 1;
                        continue;
                    }

                    // 3. Charset / Keypad short sequences: \x1B ( B, \x1B =, \x1B >
                    if (next == '(' || next == ')')
                    {
                        if (i + 2 >= len)
                        {
                            _pendingEscape = data.Substring(i);
                            break;
                        }
                        i += 3;
                        continue;
                    }

                    i += 2;
                    continue;
                }

                if (c == '\r')
                {
                    _col = 0;
                    i++;
                    continue;
                }

                if (c == '\n')
                {
                    _row++;
                    EnsureRow(_row);
                    i++;
                    continue;
                }

                if (c == '\b')
                {
                    if (_col > 0) _col--;
                    i++;
                    continue;
                }

                if (c == '\a')
                {
                    i++;
                    continue;
                }

                if (c == '\t')
                {
                    int spaces = 8 - (_col % 8);
                    for (int s = 0; s < spaces; s++) PutChar(' ');
                    i++;
                    continue;
                }

                if (c >= 32)
                {
                    PutChar(c);
                }

                i++;
            }

            // Trim scrollback to max 600 lines for instant rendering performance
            if (_lines.Count > 600)
            {
                int removeCount = _lines.Count - 500;
                _lines.RemoveRange(_headerLineCount, removeCount);
                _row = Math.Max(_headerLineCount, _row - removeCount);
            }
        }

        private void PutChar(char ch)
        {
            EnsureRow(_row);
            var line = _lines[_row];
            while (line.Count <= _col)
            {
                line.Add(new StyledCell { Ch = ' ', Fg = null, Bg = null, Bold = false });
            }
            line[_col] = new StyledCell
            {
                Ch = ch,
                Fg = _curFg,
                Bg = _curBg,
                Bold = _curBold
            };
            _col++;
        }

        private void HandleCsiSequence(string paramStr, char cmd)
        {
            if (paramStr.StartsWith("?", StringComparison.Ordinal))
                return; // Private mode toggles like ?2004h (bracketed paste)

            switch (cmd)
            {
                case 'm':
                    ApplySgrParameters(paramStr);
                    break;

                case 'K': // Erase in Line (EL)
                {
                    EnsureRow(_row);
                    var line = _lines[_row];
                    int mode = int.TryParse(paramStr, out int m) ? m : 0;
                    if (mode == 0) // Cursor to end of line
                    {
                        if (_col < line.Count)
                            line.RemoveRange(_col, line.Count - _col);
                    }
                    else if (mode == 2) // Entire line
                    {
                        line.Clear();
                        _col = 0;
                    }
                    break;
                }

                case 'J': // Erase in Display (ED)
                {
                    int mode = int.TryParse(paramStr, out int m) ? m : 0;
                    if (mode == 2 || mode == 3 || string.IsNullOrEmpty(paramStr))
                    {
                        ClearKeepHeader();
                    }
                    break;
                }

                case 'H': // Cursor Position (CUP)
                case 'f':
                {
                    if (string.IsNullOrEmpty(paramStr) || paramStr == "1;1" || paramStr == ";")
                    {
                        _row = _headerLineCount;
                        EnsureRow(_row);
                        _col = 0;
                    }
                    break;
                }

                case 'C': // Cursor Forward
                {
                    int n = int.TryParse(paramStr, out int v) && v > 0 ? v : 1;
                    _col += n;
                    break;
                }

                case 'D': // Cursor Back
                {
                    int n = int.TryParse(paramStr, out int v) && v > 0 ? v : 1;
                    _col = Math.Max(0, _col - n);
                    break;
                }

                case 'P': // Delete Character (DCH)
                {
                    EnsureRow(_row);
                    var line = _lines[_row];
                    int n = int.TryParse(paramStr, out int v) && v > 0 ? v : 1;
                    if (_col < line.Count)
                    {
                        int count = Math.Min(n, line.Count - _col);
                        line.RemoveRange(_col, count);
                    }
                    break;
                }

                case '@': // Insert Character (ICH)
                {
                    EnsureRow(_row);
                    var line = _lines[_row];
                    int n = int.TryParse(paramStr, out int v) && v > 0 ? v : 1;
                    if (_col <= line.Count)
                    {
                        for (int k = 0; k < n; k++)
                            line.Insert(_col, new StyledCell { Ch = ' ' });
                    }
                    break;
                }
            }
        }

        private void ApplySgrParameters(string paramStr)
        {
            if (string.IsNullOrEmpty(paramStr))
            {
                _curFg = null;
                _curBg = null;
                _curBold = false;
                return;
            }

            string[] parts = paramStr.Split(';');
            int idx = 0;
            while (idx < parts.Length)
            {
                int code = int.TryParse(parts[idx], out int val) ? val : 0;
                switch (code)
                {
                    case 0:
                        _curFg = null;
                        _curBg = null;
                        _curBold = false;
                        break;
                    case 1:
                        _curBold = true;
                        break;
                    case 22:
                        _curBold = false;
                        break;
                    case >= 30 and <= 37:
                        _curFg = GetAnsi16Color(code - 30, _curBold);
                        break;
                    case 38:
                        if (idx + 4 < parts.Length && parts[idx + 1] == "2")
                        {
                            byte r = byte.TryParse(parts[idx + 2], out byte rv) ? rv : (byte)255;
                            byte g = byte.TryParse(parts[idx + 3], out byte gv) ? gv : (byte)255;
                            byte b = byte.TryParse(parts[idx + 4], out byte bv) ? bv : (byte)255;
                            _curFg = Color.FromRgb(r, g, b);
                            idx += 4;
                        }
                        else if (idx + 2 < parts.Length && parts[idx + 1] == "5")
                        {
                            int n = int.TryParse(parts[idx + 2], out int nv) ? nv : 7;
                            _curFg = GetXterm256Color(n);
                            idx += 2;
                        }
                        break;
                    case 39:
                        _curFg = null;
                        break;
                    case >= 40 and <= 47:
                        _curBg = GetAnsi16Color(code - 40, false);
                        break;
                    case 48:
                        if (idx + 4 < parts.Length && parts[idx + 1] == "2")
                        {
                            byte r = byte.TryParse(parts[idx + 2], out byte rv) ? rv : (byte)0;
                            byte g = byte.TryParse(parts[idx + 3], out byte gv) ? gv : (byte)0;
                            byte b = byte.TryParse(parts[idx + 4], out byte bv) ? bv : (byte)0;
                            _curBg = Color.FromRgb(r, g, b);
                            idx += 4;
                        }
                        else if (idx + 2 < parts.Length && parts[idx + 1] == "5")
                        {
                            int n = int.TryParse(parts[idx + 2], out int nv) ? nv : 0;
                            _curBg = GetXterm256Color(n);
                            idx += 2;
                        }
                        break;
                    case 49:
                        _curBg = null;
                        break;
                    case >= 90 and <= 97:
                        _curFg = GetAnsi16Color(code - 90, true);
                        break;
                    case >= 100 and <= 107:
                        _curBg = GetAnsi16Color(code - 100, true);
                        break;
                }
                idx++;
            }
        }

        private static Color GetAnsi16Color(int index, bool bright)
        {
            return (index, bright) switch
            {
                (0, false) => Color.FromRgb(0, 0, 0),
                (1, false) => Color.FromRgb(187, 0, 0),
                (2, false) => Color.FromRgb(0, 187, 0),
                (3, false) => Color.FromRgb(187, 187, 0),
                (4, false) => Color.FromRgb(0, 84, 245),
                (5, false) => Color.FromRgb(187, 0, 187),
                (6, false) => Color.FromRgb(0, 187, 187),
                (7, false) => Color.FromRgb(217, 217, 217),
                (0, true)  => Color.FromRgb(102, 102, 102),
                (1, true)  => Color.FromRgb(255, 85, 85),
                (2, true)  => Color.FromRgb(85, 255, 85),
                (3, true)  => Color.FromRgb(255, 255, 85),
                (4, true)  => Color.FromRgb(128, 170, 250),
                (5, true)  => Color.FromRgb(255, 85, 255),
                (6, true)  => Color.FromRgb(85, 255, 255),
                _          => Color.FromRgb(255, 255, 255),
            };
        }

        private static Color GetXterm256Color(int n)
        {
            if (n < 8) return GetAnsi16Color(n, false);
            if (n < 16) return GetAnsi16Color(n - 8, true);
            if (n >= 232)
            {
                byte gray = (byte)Math.Clamp(8 + (n - 232) * 10, 0, 255);
                return Color.FromRgb(gray, gray, gray);
            }
            int idx = n - 16;
            int r = (idx / 36) % 6;
            int g = (idx / 6) % 6;
            int b = idx % 6;
            return Color.FromRgb(
                (byte)(r == 0 ? 0 : 55 + r * 40),
                (byte)(g == 0 ? 0 : 55 + g * 40),
                (byte)(b == 0 ? 0 : 55 + b * 40));
        }

        public void RenderInto(FlowDocument doc)
        {
            doc.Blocks.Clear();
            var para = new Paragraph
            {
                Margin = new Thickness(0),
                LineHeight = 19,
                LineStackingStrategy = LineStackingStrategy.BlockLineHeight,
                FontFamily = new FontFamily("Consolas, Cascadia Mono, Courier New"),
                FontSize = 14
            };

            int lastNonEmptyRow = _lines.Count - 1;
            for (int r = 0; r <= lastNonEmptyRow; r++)
            {
                var line = _lines[r];
                bool isCursorRow = (r == _row);

                if (line.Count == 0 && !isCursorRow)
                {
                    if (r < lastNonEmptyRow) para.Inlines.Add(new LineBreak());
                    continue;
                }

                int maxCol = isCursorRow ? Math.Max(line.Count, _col + 1) : line.Count;
                var sb = new StringBuilder();
                StyledCell currentStyle = default;
                bool hasRun = false;

                void FlushRun()
                {
                    if (!hasRun || sb.Length == 0) return;
                    var run = new Run(sb.ToString())
                    {
                        Foreground = new SolidColorBrush(currentStyle.Fg ?? DefaultFgColor),
                        FontWeight = currentStyle.Bold ? FontWeights.Bold : FontWeights.Normal
                    };
                    if (currentStyle.Bg.HasValue)
                        run.Background = new SolidColorBrush(currentStyle.Bg.Value);
                    para.Inlines.Add(run);
                    sb.Clear();
                    hasRun = false;
                }

                for (int c = 0; c < maxCol; c++)
                {
                    StyledCell cell = c < line.Count
                        ? line[c]
                        : new StyledCell { Ch = ' ', Fg = null, Bg = null, Bold = false };

                    if (isCursorRow && c == _col)
                    {
                        FlushRun();
                        var cursorRun = new Run(cell.Ch == '\0' ? " " : cell.Ch.ToString())
                        {
                            Background = new SolidColorBrush(PuTTYCursorGreen),
                            Foreground = Brushes.Black,
                            FontWeight = FontWeights.Bold
                        };
                        para.Inlines.Add(cursorRun);
                        continue;
                    }

                    if (!hasRun)
                    {
                        currentStyle = cell;
                        hasRun = true;
                        sb.Append(cell.Ch);
                    }
                    else if (currentStyle.SameStyle(cell))
                    {
                        sb.Append(cell.Ch);
                    }
                    else
                    {
                        FlushRun();
                        currentStyle = cell;
                        hasRun = true;
                        sb.Append(cell.Ch);
                    }
                }

                FlushRun();
                if (r < lastNonEmptyRow)
                    para.Inlines.Add(new LineBreak());
            }

            doc.Blocks.Add(para);
        }
    }
}
