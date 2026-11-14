using System;
using System.Collections.Generic;
using System.ComponentModel;
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
        public enum ActiveSessionMode
        {
            None,
            PtyShell,
            ExecShell,
            SftpShell
        }

        private readonly string? _startupHandoffNonce;
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
        private string _execInputBuffer = "";
        private bool _execCommandRunning = false;

        private ActiveSessionMode _sessionMode = ActiveSessionMode.None;
        private readonly PuTTYAnsiScreenBuffer _screen = new();

        private bool _closeCleanupDone;
        private bool _closeCleanupInProgress;

        public bool IsDarkMode => _isDarkMode;
        public ActiveSessionMode CurrentSessionMode => _sessionMode;
        public string CurrentHostText => HostBox.Text;

        public MainWindow(string? startupHandoffNonce = null)
        {
            _startupHandoffNonce = string.IsNullOrWhiteSpace(startupHandoffNonce) ? null : startupHandoffNonce.Trim();
            InitializeComponent();
            ApplyTheme();
            LoadProfilesIntoUi();
            _screen.Reset();
            _screen.WriteSystemLine("Funkenwerk SSH · Bereit. Wähle links eine Verbindung oder klicke auf VERBINDEN.");
            RenderTerminalScreen();
            Loaded += MainWindow_Loaded;
        }

        public void SetTheme(bool isDark)
        {
            _isDarkMode = isDark;
            ApplyTheme();
        }

        private async void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            // Only consume handoff_ticket.dpapi when explicitly launched with --handoff <nonce>
            if (string.IsNullOrWhiteSpace(_startupHandoffNonce))
                return;

            var ticket = FunkenwerkSshSecurity.ConsumeHandoffTicket(_startupHandoffNonce);
            if (ticket == null || string.IsNullOrWhiteSpace(ticket.Host))
            {
                StatusText.Text = "⚠ SSH-Handoff-Ticket ungültig oder abgelaufen.";
                return;
            }

            // Match strictly on Host + Port + Username; NEVER overwrite another profile's credentials!
            var exactMatch = FunkenwerkSshSecurity.FindMatchingHandoffProfile(_profiles, ticket);
            if (exactMatch != null)
            {
                _suppressPassSync = true;
                ProfilesListBox.SelectedItem = exactMatch;
                _suppressPassSync = false;
            }
            else
            {
                // Create clean transient UI session when username or port differs from saved profiles
                ProfilesListBox.SelectedItem = null;
            }

            HostBox.Text = ticket.Host;
            PortBox.Text = ticket.Port.ToString();
            UserBox.Text = ticket.Username;
            SetPasswordValue(ticket.Password);
            _selectedPrivateKeyPath = FunkenwerkSshSecurity.ResolveOpenSshKeyPath(ticket.PrivateKeyPath);
            UpdateKeyButtonTooltip();

            await ConnectOrReconnectSshAsync(ticket.RemoteDirectory);
        }

        private void LoadProfilesIntoUi()
        {
            try
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
            catch (Exception ex)
            {
                _profiles = new List<SshConnectionProfile>();
                ProfilesListBox.ItemsSource = _profiles;
                StatusText.Text = $"🚨 Tresor-Schutz aktiv: {ex.Message}";
            }
        }

        private void UpdateKeyButtonTooltip()
        {
            PickKeyBtn.ToolTip = string.IsNullOrWhiteSpace(_selectedPrivateKeyPath)
                ? "OpenSSH Private Key wählen"
                : $"SSH Key: {_selectedPrivateKeyPath}";
        }

        private void ProfilesListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressPassSync) return;
            if (ProfilesListBox.SelectedItem is not SshConnectionProfile p) return;

            HostBox.Text = p.Host;
            PortBox.Text = p.Port.ToString();
            UserBox.Text = p.Username;
            try
            {
                SetPasswordValue(FunkenwerkSshSecurity.DecryptSecret(p.EncryptedPassword));
            }
            catch (Exception ex)
            {
                SetPasswordValue("");
                StatusText.Text = $"🚨 Tresor-Warnung ({p.Name}): {ex.Message}";
            }

            _selectedPrivateKeyPath = p.PrivateKeyPath ?? "";
            UpdateKeyButtonTooltip();
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
                UpdateKeyButtonTooltip();
                StatusText.Text = $"🔑 SSH-Key ausgewählt: {Path.GetFileName(_selectedPrivateKeyPath)}";
            }
        }

        private void NewProfile_Click(object sender, RoutedEventArgs e)
        {
            ProfilesListBox.SelectedItem = null;
            HostBox.Text = "";
            PortBox.Text = "22";
            UserBox.Text = "";
            _selectedPrivateKeyPath = "";
            UpdateKeyButtonTooltip();
            SetPasswordValue("");
            HostBox.Focus();
        }

        private void SaveProfile_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                string host = FunkenwerkSshSecurity.SanitizeHost(HostBox.Text);
                if (string.IsNullOrWhiteSpace(host)) return;
                int port = int.TryParse(PortBox.Text, out int pt) && pt > 0 && pt <= 65535 ? pt : 22;
                string user = UserBox.Text.Trim();
                string pass = GetPasswordValue();

                if (ProfilesListBox.SelectedItem is SshConnectionProfile current &&
                    string.Equals(current.Host, host, StringComparison.OrdinalIgnoreCase) &&
                    current.Port == port &&
                    string.Equals(current.Username, user, StringComparison.OrdinalIgnoreCase))
                {
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
            if (!ShowCleanConfirmDialog("Profil entfernen", $"Gespeichertes SSH-Profil '{current.Name}' wirklich entfernen?", "Entfernen", isDanger: true))
                return;

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
            return Path.GetFileName(keyPath);
        }

        // =====================================================================
        // POSIX SINGLE-QUOTE COMMAND INJECTION GUARD
        // =====================================================================
        public static string ValidateSafeRemoteDirectoryNoControlChars(string dir)
        {
            if (string.IsNullOrWhiteSpace(dir))
                throw new ArgumentException("Remote-Verzeichnis darf nicht leer sein.", nameof(dir));
            if (dir.IndexOfAny(new[] { '\r', '\n', '\0' }) >= 0)
                throw new ArgumentException("Steuerzeichen im Remote-Verzeichnis blockiert.", nameof(dir));
            return dir.Trim();
        }

        /// <summary>
        /// Quotes target directory strictly with POSIX single quotes (' -> '\'') and rejects \r, \n, \0
        /// so $(...), `...`, $VAR, ", \, ;, |, & can NEVER execute commands!
        /// </summary>
        public static string BuildSafePosixCdCommand(string dir)
        {
            string clean = ValidateSafeRemoteDirectoryNoControlChars(dir);
            return "cd -- '" + clean.Replace("'", "'\\''", StringComparison.Ordinal) + "' && clear\r";
        }

        public static string QuotePosixSingleArgument(string arg)
        {
            string clean = ValidateSafeRemoteDirectoryNoControlChars(arg);
            return "'" + clean.Replace("'", "'\\''", StringComparison.Ordinal) + "'";
        }

        // =====================================================================
        // SECURE SSH / EXEC / SFTP CONNECTION ENGINE
        // =====================================================================
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

                int port = int.TryParse(PortBox.Text, out int pt) && pt > 0 && pt <= 65535 ? pt : 22;
                string user = UserBox.Text.Trim();

                // Map Mittwald SFTP-only user (sftp-...) to the interactive SSH container user
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

                string rawTargetDir = !string.IsNullOrWhiteSpace(overrideStartupDir)
                    ? overrideStartupDir
                    : (ProfilesListBox.SelectedItem as SshConnectionProfile)?.StartupDirectory ?? "";

                if (!string.IsNullOrEmpty(rawTargetDir) && rawTargetDir.IndexOfAny(new[] { '\r', '\n', '\0' }) >= 0)
                {
                    StatusText.Text = "🚨 Sicherheitsblockade: Steuerzeichen im Startverzeichnis erkannt!";
                    return;
                }

                string targetDir = rawTargetDir.Trim();
                string pass = GetPasswordValue();
                var authMethods = new List<AuthenticationMethod>();
                bool hasPublicKeyAuth = false;
                string keyComment = "";

                // Strictly use ONLY an explicitly configured PrivateKeyPath (never auto-load ~/.ssh/id_ed25519 when empty!)
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
                    StatusText.Text = "⚠ Bitte Passwort oder expliziten SSH-Key angeben.";
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
                bool rejectedNewKeyAbort = false;
                string? hostKeyVaultError = null;
                string negotiatedKeyAlgo = "";

                void HandleHostKeyVerification(HostKeyEventArgs hkArgs)
                {
                    try
                    {
                        negotiatedKeyAlgo = hkArgs.HostKeyName;
                        var verify = FunkenwerkSshSecurity.VerifyAndPinHostKey(
                            host,
                            port,
                            hkArgs.HostKeyName,
                            hkArgs.HostKey,
                            trustNewKeys: false,
                            confirmNewKeyCallback: fp => Dispatcher.Invoke(() =>
                                ShowCleanConfirmDialog(
                                    "SSH-Host-Key prüfen",
                                    $"Neuer Server-Schlüssel für '{host}:{port}' ({hkArgs.HostKeyName}):\n\n{fp}\n\nDiesem Fingerabdruck vertrauen und dauerhaft pinnen?",
                                    "Vertrauen & Pinnen",
                                    isDanger: false)));

                        _lastPinnedFingerprint = verify.FingerprintSha256;
                        if (verify.IsChanged)
                        {
                            mitmAbort = true;
                            hkArgs.CanTrust = false;
                        }
                        else if (!verify.IsTrusted)
                        {
                            rejectedNewKeyAbort = true;
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
                }

                client.HostKeyReceived += (_, hkArgs) => HandleHostKeyVerification(hkArgs);

                try
                {
                    await Task.Run(() => client.Connect());
                }
                catch
                {
                    client.Dispose();
                    if (!string.IsNullOrEmpty(hostKeyVaultError))
                    {
                        _screen.WriteSystemLine($"🚨 Host-Key-Datenbank blockiert: {hostKeyVaultError}");
                        RenderTerminalScreen();
                        StatusText.Text = $"🚨 Host-Key-Datenbank blockiert: {hostKeyVaultError}";
                        return;
                    }
                    if (mitmAbort)
                    {
                        _screen.WriteSystemLine("🚨 Host-Key geändert! Verbindung durch MITM-Schutz blockiert.");
                        RenderTerminalScreen();
                        StatusText.Text = "🚨 MITM-Schutz hat die Verbindung blockiert (Host-Key geändert)!";
                        return;
                    }
                    if (rejectedNewKeyAbort)
                    {
                        _screen.WriteSystemLine("Verbindung abgebrochen (Server-Host-Key nicht bestätigt).");
                        RenderTerminalScreen();
                        StatusText.Text = "Verbindung abgebrochen (Server-Host-Key nicht bestätigt).";
                        return;
                    }
                    throw;
                }

                if (mitmAbort || rejectedNewKeyAbort || !string.IsNullOrEmpty(hostKeyVaultError))
                {
                    client.Dispose();
                    StatusText.Text = mitmAbort
                        ? "🚨 MITM-Schutz hat die Verbindung blockiert (Host-Key geändert)!"
                        : $"🚨 Verbindung blockiert: {hostKeyVaultError ?? "Host-Key abgelehnt."}";
                    return;
                }

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
                    _sessionMode = ActiveSessionMode.PtyShell;
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
                            _sessionMode = ActiveSessionMode.ExecShell;
                    }
                    catch { }
                }

                if (_sessionMode == ActiveSessionMode.PtyShell && shell != null)
                {
                    _sshClient = client;
                    _shellStream = shell;

                    if (!string.IsNullOrWhiteSpace(targetDir) && targetDir != "/")
                    {
                        string safeCdCmd = BuildSafePosixCdCommand(targetDir);
                        byte[] initCmd = Encoding.UTF8.GetBytes(safeCdCmd);
                        await shell.WriteAsync(initCmd, 0, initCmd.Length);
                        await shell.FlushAsync();
                    }

                    _readerCts = new CancellationTokenSource();
                    _ = ReadShellStreamLoopAsync(shell, _readerCts.Token);
                }
                else if (_sessionMode == ActiveSessionMode.ExecShell)
                {
                    _sshClient = client;
                    _execInputBuffer = "";

                    if (!string.IsNullOrWhiteSpace(targetDir) && targetDir != "/")
                    {
                        string quotedDir = QuotePosixSingleArgument(targetDir);
                        string resolvedDir = await Task.Run(() =>
                        {
                            using var c = client.CreateCommand($"cd -- {quotedDir} && pwd");
                            return c.Execute();
                        });
                        string lastLine = (resolvedDir ?? "").Split('\n', StringSplitOptions.RemoveEmptyEntries).LastOrDefault()?.Trim() ?? "";
                        if (!string.IsNullOrEmpty(lastLine))
                            _currentRemoteDir = lastLine;
                    }

                    WritePromptBanner();
                    RenderTerminalScreen();
                }
                else
                {
                    // SFTP fallback shell for SFTP-only server accounts
                    try { client.Disconnect(); client.Dispose(); } catch { }
                    var sftp = new SftpClient(connInfo) { KeepAliveInterval = TimeSpan.FromSeconds(15) };
                    sftp.HostKeyReceived += (_, hkArgs) => HandleHostKeyVerification(hkArgs);
                    await Task.Run(() => sftp.Connect());
                    _sftpFallbackClient = sftp;
                    _sessionMode = ActiveSessionMode.SftpShell;
                    _sftpInputBuffer = "";

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
                    WritePromptBanner();
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

        private void WritePromptBanner()
        {
            _screen.ProcessAnsiStream($"\x1B[1;37m\x1B[48;2;197;160;89m {_activeUserHost} \x1B[0m\x1B[1;37m\x1B[48;2;30;64;175m {_currentRemoteDir} \x1B[0m ");
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
                            byte[] packet = buffer.AsSpan(0, read).ToArray();
                            await Dispatcher.InvokeAsync(() =>
                            {
                                _screen.ProcessUtf8Bytes(packet, 0, packet.Length);
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
                _sessionMode = ActiveSessionMode.None;
                _execInputBuffer = "";
                _sftpInputBuffer = "";
            }
            catch { }

            ConnectBtn.Content = "⚡ VERBINDEN";
            ConnectBtn.Background = new SolidColorBrush(Color.FromRgb(197, 160, 89));
            ConnectBtn.Foreground = new SolidColorBrush(Color.FromRgb(15, 17, 23));
            StatusText.Text = "🔒 SSHv2 Outbound-Only (0 offene Ports) · Verbindung getrennt.";
        }

        // =====================================================================
        // BRACKETED PASTE & MULTI-LINE PASTE GUARD
        // =====================================================================
        public static (string Payload, string CleanedPreview, bool IsMultiLine, int LineCount) PrepareSafePastePayload(
            string? rawClipboardText,
            bool bracketedPasteActive)
        {
            if (string.IsNullOrEmpty(rawClipboardText))
                return ("", "", false, 0);

            var sb = new StringBuilder(rawClipboardText.Length);
            foreach (char ch in rawClipboardText)
            {
                // Strip ESC (\x1B), DEL (\x7F), C1 controls (0x80..0x9F), and dangerous C0 controls except \t, \r, \n
                if (ch == '\x1B' || ch == '\x7F')
                    continue;
                if (ch >= '\x80' && ch <= '\x9F')
                    continue;
                if (ch < ' ' && ch != '\t' && ch != '\r' && ch != '\n')
                    continue;

                sb.Append(ch);
            }

            string cleaned = sb.ToString();
            if (cleaned.Length == 0)
                return ("", "", false, 0);

            bool isMultiLine = cleaned.IndexOfAny(new[] { '\r', '\n' }) >= 0;
            string normalizedPreview = cleaned.Replace("\r\n", "\n", StringComparison.Ordinal).Replace('\r', '\n');
            int lineCount = normalizedPreview.Split('\n').Length;

            string ptyText = cleaned.Replace("\r\n", "\r", StringComparison.Ordinal).Replace('\n', '\r');
            string finalPayload = bracketedPasteActive
                ? "\x1B[200~" + ptyText + "\x1B[201~"
                : ptyText;

            return (finalPayload, normalizedPreview, isMultiLine, lineCount);
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
            PasteClipboardToTerminal();
            e.Handled = true;
        }

        public static ActiveSessionMode ResolveSessionDispatchTarget(ActiveSessionMode mode) => mode switch
        {
            ActiveSessionMode.PtyShell => ActiveSessionMode.PtyShell,
            ActiveSessionMode.ExecShell => ActiveSessionMode.ExecShell,
            ActiveSessionMode.SftpShell => ActiveSessionMode.SftpShell,
            _ => ActiveSessionMode.None
        };

        private void SendRawToShell(string data)
        {
            if (_sessionMode == ActiveSessionMode.PtyShell && _shellStream != null && _sshClient != null && _sshClient.IsConnected)
            {
                try
                {
                    byte[] bytes = Encoding.UTF8.GetBytes(data);
                    _shellStream.Write(bytes, 0, bytes.Length);
                    _shellStream.Flush();
                }
                catch { }
            }
            else if (_sessionMode == ActiveSessionMode.ExecShell)
            {
                HandleExecKeyInput(data);
            }
            else if (_sessionMode == ActiveSessionMode.SftpShell)
            {
                HandleSftpKeyInput(data);
            }
        }

        private void PasteClipboardToTerminal()
        {
            if (!IsAnySessionConnected()) return;

            try
            {
                if (!Clipboard.ContainsText()) return;

                string rawText = Clipboard.GetText();
                bool useBracketed = _sessionMode == ActiveSessionMode.PtyShell && _screen.BracketedPasteEnabled;
                var prepared = PrepareSafePastePayload(rawText, useBracketed);
                if (string.IsNullOrEmpty(prepared.Payload))
                    return;

                if (prepared.IsMultiLine)
                {
                    if (!ShowMultiLinePasteConfirmDialog(prepared.CleanedPreview, prepared.LineCount))
                        return;
                }

                SendRawToShell(prepared.Payload);
            }
            catch { }
        }

        private void TerminalRichBox_PreviewTextInput(object sender, TextCompositionEventArgs e)
        {
            if (!IsAnySessionConnected() || string.IsNullOrEmpty(e.Text)) return;

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
            bool ctrl = (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control;

            // Allow Ctrl+C selection copy even when disconnected
            if (ctrl && e.Key == Key.C)
            {
                if (!TerminalRichBox.Selection.IsEmpty && !string.IsNullOrEmpty(TerminalRichBox.Selection.Text))
                {
                    try { Clipboard.SetText(TerminalRichBox.Selection.Text); } catch { }
                    TerminalRichBox.Selection.Select(TerminalRichBox.Selection.End, TerminalRichBox.Selection.End);
                    StatusText.Text = "📋 Markierter Text kopiert.";
                    e.Handled = true;
                    return;
                }

                if (IsAnySessionConnected())
                {
                    SendRawToShell("\x03"); // SIGINT when selection is empty
                    e.Handled = true;
                }
                return;
            }

            if (!IsAnySessionConnected()) return;

            if (ctrl)
            {
                if (e.Key == Key.V)
                {
                    PasteClipboardToTerminal();
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.D)
                {
                    SendRawToShell("\x04");
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.L)
                {
                    SendRawToShell("\x0C");
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

        private async void HandleExecKeyInput(string data)
        {
            if (_sshClient == null || !_sshClient.IsConnected || _execCommandRunning) return;

            foreach (char ch in data)
            {
                if (ch == '\x03')
                {
                    _execInputBuffer = "";
                    _screen.ProcessAnsiStream("^C\r\n");
                    WritePromptBanner();
                    RenderTerminalScreen();
                }
                else if (ch == '\x0C')
                {
                    _execInputBuffer = "";
                    _screen.ClearKeepHeader();
                    WritePromptBanner();
                    RenderTerminalScreen();
                }
                else if (ch == '\r' || ch == '\n')
                {
                    string cmdText = _execInputBuffer.Trim();
                    _execInputBuffer = "";
                    _screen.ProcessAnsiStream("\r\n");

                    if (cmdText == "clear" || cmdText == "cls")
                    {
                        _screen.ClearKeepHeader();
                        WritePromptBanner();
                        RenderTerminalScreen();
                        continue;
                    }

                    if (!string.IsNullOrWhiteSpace(cmdText))
                    {
                        _execCommandRunning = true;
                        try
                        {
                            const string pwdMarker = "__FW_EXEC_PWD__:";
                            string safeDirArg = QuotePosixSingleArgument(string.IsNullOrWhiteSpace(_currentRemoteDir) ? "/" : _currentRemoteDir);
                            string fullExec = $"cd -- {safeDirArg} 2>/dev/null; {cmdText}; printf '\\n{pwdMarker}%s\\n' \"$PWD\"";

                            var (stdout, stderr) = await Task.Run(() =>
                            {
                                using var sshCmd = _sshClient.CreateCommand(fullExec);
                                string outStr = sshCmd.Execute();
                                return (outStr ?? "", sshCmd.Error ?? "");
                            });

                            int markerIdx = stdout.LastIndexOf(pwdMarker, StringComparison.Ordinal);
                            if (markerIdx >= 0)
                            {
                                string after = stdout.Substring(markerIdx + pwdMarker.Length).Trim();
                                int nl = after.IndexOfAny(new[] { '\r', '\n' });
                                string parsedPwd = (nl >= 0 ? after.Substring(0, nl) : after).Trim();
                                if (!string.IsNullOrEmpty(parsedPwd) && parsedPwd.IndexOfAny(new[] { '\r', '\n', '\0' }) < 0)
                                    _currentRemoteDir = parsedPwd;
                                stdout = stdout.Substring(0, markerIdx);
                            }

                            if (!string.IsNullOrEmpty(stdout))
                                _screen.ProcessAnsiStream(stdout.Replace("\r\n", "\n", StringComparison.Ordinal).Replace("\n", "\r\n", StringComparison.Ordinal));
                            if (!string.IsNullOrEmpty(stderr))
                                _screen.ProcessAnsiStream("\x1B[91m" + stderr.Replace("\r\n", "\n", StringComparison.Ordinal).Replace("\n", "\r\n", StringComparison.Ordinal) + "\x1B[0m");
                            if (!stdout.EndsWith("\n", StringComparison.Ordinal) && !string.IsNullOrEmpty(stdout))
                                _screen.ProcessAnsiStream("\r\n");
                        }
                        catch (Exception ex)
                        {
                            _screen.ProcessAnsiStream($"\x1B[91m{ex.Message}\x1B[0m\r\n");
                        }
                        finally
                        {
                            _execCommandRunning = false;
                        }
                    }

                    WritePromptBanner();
                    RenderTerminalScreen();
                }
                else if (ch == '\x7F' || ch == '\b')
                {
                    if (_execInputBuffer.Length > 0)
                    {
                        _execInputBuffer = _execInputBuffer.Substring(0, _execInputBuffer.Length - 1);
                        _screen.ProcessAnsiStream("\b\x1B[K");
                        RenderTerminalScreen();
                    }
                }
                else if (ch >= 32)
                {
                    _execInputBuffer += ch;
                    _screen.ProcessAnsiStream(ch.ToString());
                    RenderTerminalScreen();
                }
            }
        }

        private async void HandleSftpKeyInput(string data)
        {
            if (_sftpFallbackClient == null || !_sftpFallbackClient.IsConnected) return;
            foreach (char ch in data)
            {
                if (ch == '\x03')
                {
                    _sftpInputBuffer = "";
                    _screen.ProcessAnsiStream("^C\r\n");
                    WritePromptBanner();
                    RenderTerminalScreen();
                }
                else if (ch == '\r' || ch == '\n')
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
                                if (cmd == "pwd")
                                {
                                    _screen.ProcessAnsiStream(_currentRemoteDir + "\r\n");
                                }
                                else if (cmd.StartsWith("cd ", StringComparison.Ordinal))
                                {
                                    _sftpFallbackClient.ChangeDirectory(cmd.Substring(3).Trim().Trim('"', '\''));
                                    _currentRemoteDir = _sftpFallbackClient.WorkingDirectory;
                                }
                                else if (cmd == "ls" || cmd == "ll" || cmd == "dir")
                                {
                                    foreach (var item in _sftpFallbackClient.ListDirectory(_currentRemoteDir).Where(x => x.Name != "." && x.Name != ".."))
                                        _screen.ProcessAnsiStream(item.IsDirectory ? $"\x1B[1;34m{item.Name}/\x1B[0m  " : $"{item.Name}  ");
                                    _screen.ProcessAnsiStream("\r\n");
                                }
                            }
                            catch (Exception ex) { _screen.ProcessAnsiStream($"{ex.Message}\r\n"); }
                        });
                    }
                    WritePromptBanner();
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
            if (_sessionMode == ActiveSessionMode.PtyShell)
            {
                SendRawToShell("\x0C");
            }
            else
            {
                _screen.ClearKeepHeader();
                if (_sessionMode == ActiveSessionMode.ExecShell || _sessionMode == ActiveSessionMode.SftpShell)
                    WritePromptBanner();
                RenderTerminalScreen();
            }
            TerminalRichBox.Focus();
        }

        private ScrollViewer? FindTerminalScrollViewer()
        {
            if (VisualTreeHelper.GetChildrenCount(TerminalRichBox) == 0) return null;
            DependencyObject cur = VisualTreeHelper.GetChild(TerminalRichBox, 0);
            while (cur != null)
            {
                if (cur is ScrollViewer sv) return sv;
                if (VisualTreeHelper.GetChildrenCount(cur) == 0) break;
                cur = VisualTreeHelper.GetChild(cur, 0);
            }
            return null;
        }

        private void RenderTerminalScreen()
        {
            var sv = FindTerminalScrollViewer();
            bool followBottom = sv == null ||
                                sv.ExtentHeight <= sv.ViewportHeight ||
                                (sv.VerticalOffset + sv.ViewportHeight >= sv.ExtentHeight - 24.0);
            double savedOffset = sv?.VerticalOffset ?? 0.0;

            _screen.RenderInto(TerminalDoc);

            if (followBottom)
            {
                TerminalRichBox.ScrollToEnd();
            }
            else
            {
                sv?.ScrollToVerticalOffset(savedOffset);
            }
        }

        // =====================================================================
        // CLEAN LIGHT/DARK MODAL DIALOGS (HOST KEY & MULTI-LINE PASTE PREVIEW)
        // =====================================================================
        private bool ShowCleanConfirmDialog(string title, string message, string confirmLabel, bool isDanger)
        {
            var dlg = new Window
            {
                Title = title,
                Owner = IsLoaded ? this : null,
                WindowStartupLocation = IsLoaded ? WindowStartupLocation.CenterOwner : WindowStartupLocation.CenterScreen,
                Width = 460,
                SizeToContent = SizeToContent.Height,
                ResizeMode = ResizeMode.NoResize,
                Background = BrushHex(_isDarkMode ? "#141821" : "#FFFFFF"),
                Foreground = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827"),
                FontFamily = new FontFamily("Segoe UI")
            };

            var root = new StackPanel { Margin = new Thickness(20) };
            root.Children.Add(new TextBlock
            {
                Text = title,
                FontSize = 15,
                FontWeight = FontWeights.Bold,
                Foreground = BrushHex(isDanger ? "#EF4444" : "#C5A059"),
                Margin = new Thickness(0, 0, 0, 10)
            });
            root.Children.Add(new TextBlock
            {
                Text = message,
                FontSize = 13,
                TextWrapping = TextWrapping.Wrap,
                Foreground = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827"),
                Margin = new Thickness(0, 0, 0, 18)
            });

            var btnBar = new StackPanel { Orientation = Orientation.Horizontal, HorizontalAlignment = HorizontalAlignment.Right };
            var cancelBtn = new Button
            {
                Content = "Abbrechen",
                IsCancel = true,
                Height = 32,
                Padding = new Thickness(14, 0, 14, 0),
                Margin = new Thickness(0, 0, 8, 0),
                Cursor = Cursors.Hand,
                Background = BrushHex(_isDarkMode ? "#1D2230" : "#F3F4F6"),
                Foreground = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827"),
                BorderBrush = BrushHex(_isDarkMode ? "#2D3343" : "#D1D5DB")
            };
            cancelBtn.Click += (_, _) => { dlg.DialogResult = false; dlg.Close(); };

            var okBtn = new Button
            {
                Content = confirmLabel,
                IsDefault = !isDanger,
                Height = 32,
                Padding = new Thickness(16, 0, 16, 0),
                Cursor = Cursors.Hand,
                FontWeight = FontWeights.Bold,
                Background = BrushHex(isDanger ? "#EF4444" : "#C5A059"),
                Foreground = isDanger ? Brushes.White : BrushHex("#0F1117"),
                BorderThickness = new Thickness(0)
            };
            okBtn.Click += (_, _) => { dlg.DialogResult = true; dlg.Close(); };

            btnBar.Children.Add(cancelBtn);
            btnBar.Children.Add(okBtn);
            root.Children.Add(btnBar);
            dlg.Content = root;

            return dlg.ShowDialog() == true;
        }

        private bool ShowMultiLinePasteConfirmDialog(string previewText, int lineCount)
        {
            var dlg = new Window
            {
                Title = "Mehrzeiliges Einfügen prüfen",
                Owner = IsLoaded ? this : null,
                WindowStartupLocation = IsLoaded ? WindowStartupLocation.CenterOwner : WindowStartupLocation.CenterScreen,
                Width = 560,
                SizeToContent = SizeToContent.Height,
                ResizeMode = ResizeMode.NoResize,
                Background = BrushHex(_isDarkMode ? "#141821" : "#FFFFFF"),
                Foreground = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827"),
                FontFamily = new FontFamily("Segoe UI")
            };

            var root = new StackPanel { Margin = new Thickness(20) };
            root.Children.Add(new TextBlock
            {
                Text = $"Mehrzeiliger Text ({lineCount} Zeilen / Zeilenumbruch erkannt)",
                FontSize = 15,
                FontWeight = FontWeights.Bold,
                Foreground = BrushHex("#C5A059"),
                Margin = new Thickness(0, 0, 0, 10)
            });

            var previewBox = new TextBox
            {
                Text = previewText.Length > 4000 ? previewText.Substring(0, 4000) + "\n..." : previewText,
                IsReadOnly = true,
                FontFamily = new FontFamily("Consolas, Cascadia Mono, Courier New"),
                FontSize = 12.5,
                MaxHeight = 220,
                VerticalScrollBarVisibility = ScrollBarVisibility.Auto,
                HorizontalScrollBarVisibility = ScrollBarVisibility.Auto,
                Padding = new Thickness(10),
                Margin = new Thickness(0, 0, 0, 16),
                Background = BrushHex(_isDarkMode ? "#0F1117" : "#F9FAFB"),
                Foreground = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827"),
                BorderBrush = BrushHex(_isDarkMode ? "#2D3343" : "#D1D5DB")
            };
            root.Children.Add(previewBox);

            var btnBar = new StackPanel { Orientation = Orientation.Horizontal, HorizontalAlignment = HorizontalAlignment.Right };
            var cancelBtn = new Button
            {
                Content = "Abbrechen",
                IsCancel = true,
                IsDefault = true,
                Height = 32,
                Padding = new Thickness(14, 0, 14, 0),
                Margin = new Thickness(0, 0, 8, 0),
                Cursor = Cursors.Hand,
                Background = BrushHex(_isDarkMode ? "#1D2230" : "#F3F4F6"),
                Foreground = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827"),
                BorderBrush = BrushHex(_isDarkMode ? "#2D3343" : "#D1D5DB")
            };
            cancelBtn.Click += (_, _) => { dlg.DialogResult = false; dlg.Close(); };

            var pasteBtn = new Button
            {
                Content = "In Terminal einfügen",
                Height = 32,
                Padding = new Thickness(16, 0, 16, 0),
                Cursor = Cursors.Hand,
                FontWeight = FontWeights.Bold,
                Background = BrushHex("#C5A059"),
                Foreground = BrushHex("#0F1117"),
                BorderThickness = new Thickness(0)
            };
            pasteBtn.Click += (_, _) => { dlg.DialogResult = true; dlg.Close(); };

            btnBar.Children.Add(cancelBtn);
            btnBar.Children.Add(pasteBtn);
            root.Children.Add(btnBar);
            dlg.Content = root;

            return dlg.ShowDialog() == true;
        }

        // =====================================================================
        // 100% LIGHT- & DARK-MODE CONSISTENCY
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

            res["SshBgRoot"] = BrushHex(_isDarkMode ? "#0F1117" : "#F3F4F6");
            res["SshBgBar"] = BrushHex(_isDarkMode ? "#12151E" : "#FFFFFF");
            res["SshBgSidebar"] = BrushHex(_isDarkMode ? "#141821" : "#FFFFFF");
            res["SshBgCard"] = BrushHex(_isDarkMode ? "#1A1F2C" : "#F9FAFB");
            res["SshBgCardHover"] = BrushHex(_isDarkMode ? "#24C5A059" : "#1FC5A059");
            res["SshBgCardSelected"] = BrushHex(_isDarkMode ? "#33C5A059" : "#2AC5A059");
            res["SshBgInput"] = BrushHex(_isDarkMode ? "#181C26" : "#F9FAFB");
            res["SshBgButton"] = BrushHex(_isDarkMode ? "#1B1F2B" : "#F3F4F6");
            res["SshBorderSubtle"] = BrushHex(_isDarkMode ? "#232734" : "#E5E7EB");
            res["SshBorderStrong"] = BrushHex(_isDarkMode ? "#2D3343" : "#D1D5DB");
            res["SshFgPrimary"] = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827");
            res["SshFgSecondary"] = BrushHex(_isDarkMode ? "#9CA3AF" : "#4B5563");
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
                await DisconnectSshAsync();
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

    /// <summary>
    /// PuTTY-Style VT100 / xterm-256color / 24-Bit TrueColor Screen Buffer & WPF FlowDocument Renderer.
    /// Hardened with strict column/row/escape DoS clamps, stateful multi-byte UTF-8 decoding across socket chunks,
    /// DECSET ?2004h/l bracketed paste tracking, OSC 52 & DSR 5n/6n blocking, and complete VT100 cursor/erase commands.
    /// </summary>
    public sealed class PuTTYAnsiScreenBuffer
    {
        public const int MaxCols = 512;
        public const int MaxRows = 5000;
        public const int MaxPendingEscapeLength = 4096;

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
        private Decoder _utf8Decoder = Encoding.UTF8.GetDecoder();
        private int _headerLineCount = 0;
        private int _row = 0;
        private int _col = 0;
        private Color? _curFg = null;
        private Color? _curBg = null;
        private bool _curBold = false;
        private string _pendingEscape = "";

        public bool BracketedPasteEnabled { get; private set; }
        public int CursorRow => _row;
        public int CursorCol => _col;
        public int RowCount => _lines.Count;
        public int PendingEscapeLength => _pendingEscape.Length;

        private static readonly Color DefaultFgColor = Color.FromRgb(217, 217, 217);
        private static readonly Color PuTTYCursorGreen = Color.FromRgb(0, 255, 0);

        public void Reset()
        {
            _lines.Clear();
            _lines.Add(new List<StyledCell>());
            _utf8Decoder = Encoding.UTF8.GetDecoder();
            _headerLineCount = 0;
            _row = 0;
            _col = 0;
            _curFg = null;
            _curBg = null;
            _curBold = false;
            _pendingEscape = "";
            BracketedPasteEnabled = false;
        }

        public int GetLineLength(int row)
        {
            if (row < 0 || row >= _lines.Count) return 0;
            return _lines[row].Count;
        }

        public string GetLineText(int row)
        {
            if (row < 0 || row >= _lines.Count) return "";
            var line = _lines[row];
            var sb = new StringBuilder(line.Count);
            foreach (var c in line)
                sb.Append(c.Ch == '\0' ? ' ' : c.Ch);
            return sb.ToString();
        }

        public string GetPlainScreenText()
        {
            var sb = new StringBuilder();
            for (int r = 0; r < _lines.Count; r++)
            {
                if (r > 0) sb.Append('\n');
                sb.Append(GetLineText(r));
            }
            return sb.ToString();
        }

        public void WriteHeaderLine(string text)
        {
            EnsureRow(_row);
            _lines[_row].Clear();
            foreach (char c in text)
            {
                if (_lines[_row].Count >= MaxCols) break;
                _lines[_row].Add(new StyledCell { Ch = c, Fg = DefaultFgColor, Bg = null, Bold = false });
            }
            _row = Math.Min(MaxRows - 1, _row + 1);
            EnsureRow(_row);
            _col = 0;
            _headerLineCount = _row;
        }

        public void WriteSystemLine(string text)
        {
            EnsureRow(_row);
            foreach (char c in text)
            {
                if (_lines[_row].Count >= MaxCols) break;
                _lines[_row].Add(new StyledCell { Ch = c, Fg = DefaultFgColor, Bg = null, Bold = false });
            }
            _row = Math.Min(MaxRows - 1, _row + 1);
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
            int clamped = Math.Clamp(r, 0, MaxRows - 1);
            while (_lines.Count <= clamped)
                _lines.Add(new List<StyledCell>());
        }

        /// <summary>
        /// Stateful UTF-8 byte stream decoder so multi-byte characters (äöüß, box-drawing, emojis)
        /// split across TCP/SSH socket read boundaries never corrupt into U+FFFD!
        /// </summary>
        public void ProcessUtf8Bytes(byte[] buffer, int offset, int count)
        {
            if (buffer == null || count <= 0) return;
            int maxChars = Encoding.UTF8.GetMaxCharCount(count) + 4;
            char[] chars = new char[maxChars];
            int decoded = _utf8Decoder.GetChars(buffer, offset, count, chars, 0, flush: false);
            if (decoded > 0)
            {
                ProcessAnsiStream(new string(chars, 0, decoded));
            }
        }

        public void ProcessAnsiStream(string chunk)
        {
            if (string.IsNullOrEmpty(chunk)) return;

            string data;
            if (_pendingEscape.Length > 0)
            {
                if (_pendingEscape.Length + chunk.Length > MaxPendingEscapeLength &&
                    chunk.IndexOf('\x07') < 0 && chunk.IndexOf('\x1B') < 0)
                {
                    // Oversized unterminated OSC/CSI sequence: drop escape state to prevent memory DoS
                    _pendingEscape = "";
                    return;
                }
                data = _pendingEscape + chunk;
                _pendingEscape = "";
            }
            else
            {
                data = chunk;
            }

            int i = 0;
            int len = data.Length;

            while (i < len)
            {
                char c = data[i];

                if (c == '\x1B')
                {
                    if (i + 1 >= len)
                    {
                        _pendingEscape = "\x1B";
                        break;
                    }

                    char next = data[i + 1];

                    // 1. OSC Sequence: \x1B ] ... (\x07 or \x1B\\)
                    // Note: OSC 52 clipboard writes are strictly ignored (zero clipboard access!)
                    if (next == ']')
                    {
                        int endOsc = -1;
                        int advance = 1;
                        int maxScan = Math.Min(len, i + MaxPendingEscapeLength + 2);
                        for (int k = i + 2; k < maxScan; k++)
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
                            int remaining = len - i;
                            if (remaining <= MaxPendingEscapeLength)
                            {
                                _pendingEscape = data.Substring(i);
                                break;
                            }
                            // Unterminated OSC exceeded MaxPendingEscapeLength: discard safely up to maxScan
                            i = maxScan;
                            continue;
                        }

                        i = endOsc + advance;
                        continue;
                    }

                    // 2. CSI Sequence: \x1B [ ... <finalByte 0x40..0x7E>
                    if (next == '[')
                    {
                        int finalIdx = -1;
                        int maxScan = Math.Min(len, i + MaxPendingEscapeLength + 2);
                        for (int k = i + 2; k < maxScan; k++)
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
                            int remaining = len - i;
                            if (remaining <= MaxPendingEscapeLength)
                            {
                                _pendingEscape = data.Substring(i);
                                break;
                            }
                            i = maxScan;
                            continue;
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

                    if (next == 'M') // Reverse Index (RI)
                    {
                        _row = Math.Max(_headerLineCount, _row - 1);
                        EnsureRow(_row);
                        i += 2;
                        continue;
                    }

                    if (next == 'D') // Index (IND)
                    {
                        _row = Math.Min(MaxRows - 1, _row + 1);
                        EnsureRow(_row);
                        i += 2;
                        continue;
                    }

                    if (next == 'E') // Next Line (NEL)
                    {
                        _row = Math.Min(MaxRows - 1, _row + 1);
                        EnsureRow(_row);
                        _col = 0;
                        i += 2;
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
                    if (_row + 1 >= MaxRows)
                    {
                        if (_lines.Count > _headerLineCount + 1)
                            _lines.RemoveAt(_headerLineCount);
                        _row = MaxRows - 1;
                    }
                    else
                    {
                        _row++;
                    }
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
                    for (int s = 0; s < spaces && _col < MaxCols; s++)
                        PutChar(' ');
                    i++;
                    continue;
                }

                // Ignore C0/DEL/C1 control characters in display output
                if (c >= 32 && c != '\x7F' && !(c >= '\x80' && c <= '\x9F'))
                {
                    PutChar(c);
                }

                i++;
            }

            // Keep scrollback lean while strictly honoring MaxRows
            if (_lines.Count > 1200)
            {
                int removeCount = _lines.Count - 1000;
                int safeRemove = Math.Min(removeCount, Math.Max(0, _lines.Count - _headerLineCount - 1));
                if (safeRemove > 0)
                {
                    _lines.RemoveRange(_headerLineCount, safeRemove);
                    _row = Math.Clamp(_row - safeRemove, _headerLineCount, MaxRows - 1);
                    EnsureRow(_row);
                }
            }
        }

        private void PutChar(char ch)
        {
            if (_col >= MaxCols)
            {
                // Line wrap within bounded MaxCols / MaxRows
                if (_row + 1 < MaxRows)
                    _row++;
                EnsureRow(_row);
                _col = 0;
            }

            _row = Math.Clamp(_row, 0, MaxRows - 1);
            EnsureRow(_row);
            var line = _lines[_row];
            while (line.Count <= _col && line.Count < MaxCols)
            {
                line.Add(new StyledCell { Ch = ' ', Fg = null, Bg = null, Bold = false });
            }

            if (_col < line.Count)
            {
                line[_col] = new StyledCell
                {
                    Ch = ch,
                    Fg = _curFg,
                    Bg = _curBg,
                    Bold = _curBold
                };
            }
            _col = Math.Min(MaxCols - 1, _col + 1);
        }

        private static int ParseClampedPositiveParam(string raw, int defaultVal, int maxVal)
        {
            if (string.IsNullOrWhiteSpace(raw)) return defaultVal;
            string s = raw.Trim();
            bool allDigits = true;
            for (int i = 0; i < s.Length; i++)
            {
                if (s[i] < '0' || s[i] > '9') { allDigits = false; break; }
            }
            if (!allDigits) return defaultVal;
            if (s.Length > 7) return maxVal; // Huge numeric repeat count -> clamp directly without overflow!
            if (!int.TryParse(s, out int val) || val <= 0) return defaultVal;
            return Math.Min(val, maxVal);
        }

        private void HandleCsiSequence(string paramStr, char cmd)
        {
            // DEC Private Mode Set/Reset (\x1B[?...h / \x1B[?...l)
            if (paramStr.StartsWith("?", StringComparison.Ordinal))
            {
                if (cmd == 'h' || cmd == 'l')
                {
                    bool enable = (cmd == 'h');
                    string[] modes = paramStr.Substring(1).Split(';');
                    foreach (string m in modes)
                    {
                        if (m == "2004")
                        {
                            BracketedPasteEnabled = enable;
                        }
                    }
                }
                // Note: ?6n (DECXCPR) and all other private status reports are never reflected back
                return;
            }

            switch (cmd)
            {
                case 'n':
                    // Block CSI 5n / 6n Device Status Report reflection completely
                    break;

                case 'm':
                    ApplySgrParameters(paramStr);
                    break;

                case 'A': // Cursor Up (CUU)
                {
                    int n = ParseClampedPositiveParam(paramStr, 1, MaxRows);
                    _row = Math.Max(_headerLineCount, _row - n);
                    EnsureRow(_row);
                    break;
                }

                case 'B': // Cursor Down (CUD)
                {
                    int n = ParseClampedPositiveParam(paramStr, 1, MaxRows);
                    _row = Math.Clamp(_row + n, _headerLineCount, MaxRows - 1);
                    EnsureRow(_row);
                    break;
                }

                case 'C': // Cursor Forward (CUF) - clamped to MaxCols - 1
                {
                    int n = ParseClampedPositiveParam(paramStr, 1, MaxCols);
                    _col = Math.Clamp(_col + n, 0, MaxCols - 1);
                    break;
                }

                case 'D': // Cursor Back (CUB)
                {
                    int n = ParseClampedPositiveParam(paramStr, 1, MaxCols);
                    _col = Math.Max(0, _col - n);
                    break;
                }

                case 'E': // Cursor Next Line (CNL)
                {
                    int n = ParseClampedPositiveParam(paramStr, 1, MaxRows);
                    _row = Math.Clamp(_row + n, _headerLineCount, MaxRows - 1);
                    EnsureRow(_row);
                    _col = 0;
                    break;
                }

                case 'F': // Cursor Preceding Line (CPL)
                {
                    int n = ParseClampedPositiveParam(paramStr, 1, MaxRows);
                    _row = Math.Max(_headerLineCount, _row - n);
                    EnsureRow(_row);
                    _col = 0;
                    break;
                }

                case 'G': // Cursor Character Absolute (CHA, 1-based column)
                case '`':
                {
                    int col1 = ParseClampedPositiveParam(paramStr, 1, MaxCols);
                    _col = Math.Clamp(col1 - 1, 0, MaxCols - 1);
                    break;
                }

                case 'd': // Vertical Line Position Absolute (VPA, 1-based row)
                {
                    int row1 = ParseClampedPositiveParam(paramStr, 1, MaxRows);
                    long targetRow = (long)_headerLineCount + row1 - 1;
                    _row = (int)Math.Clamp(targetRow, _headerLineCount, MaxRows - 1);
                    EnsureRow(_row);
                    break;
                }

                case 'H': // Cursor Position (CUP, 1-based row;col)
                case 'f':
                {
                    int row1 = 1;
                    int col1 = 1;
                    if (!string.IsNullOrEmpty(paramStr))
                    {
                        string[] parts = paramStr.Split(';');
                        if (parts.Length >= 1 && !string.IsNullOrWhiteSpace(parts[0]))
                            row1 = ParseClampedPositiveParam(parts[0], 1, MaxRows);
                        if (parts.Length >= 2 && !string.IsNullOrWhiteSpace(parts[1]))
                            col1 = ParseClampedPositiveParam(parts[1], 1, MaxCols);
                    }

                    long targetRow = (long)_headerLineCount + row1 - 1;
                    _row = (int)Math.Clamp(targetRow, _headerLineCount, MaxRows - 1);
                    EnsureRow(_row);
                    _col = Math.Clamp(col1 - 1, 0, MaxCols - 1);
                    break;
                }

                case 'K': // Erase in Line (EL: 0=cursor..end, 1=start..cursor, 2=entire line)
                {
                    EnsureRow(_row);
                    var line = _lines[_row];
                    int mode = string.IsNullOrWhiteSpace(paramStr) ? 0 : (int.TryParse(paramStr, out int m) ? m : 0);
                    if (mode == 0)
                    {
                        if (_col < line.Count)
                            line.RemoveRange(_col, line.Count - _col);
                    }
                    else if (mode == 1)
                    {
                        int end = Math.Min(_col, line.Count - 1);
                        for (int x = 0; x <= end; x++)
                            line[x] = new StyledCell { Ch = ' ', Fg = null, Bg = _curBg, Bold = false };
                    }
                    else if (mode == 2)
                    {
                        line.Clear();
                    }
                    break;
                }

                case 'J': // Erase in Display (ED: 0=cursor..end, 1=start..cursor, 2/3=entire display)
                {
                    EnsureRow(_row);
                    int mode = string.IsNullOrWhiteSpace(paramStr) ? 0 : (int.TryParse(paramStr, out int m) ? m : 0);
                    if (mode == 0)
                    {
                        var line = _lines[_row];
                        if (_col < line.Count)
                            line.RemoveRange(_col, line.Count - _col);
                        if (_row + 1 < _lines.Count)
                            _lines.RemoveRange(_row + 1, _lines.Count - (_row + 1));
                    }
                    else if (mode == 1)
                    {
                        for (int r = _headerLineCount; r < _row && r < _lines.Count; r++)
                            _lines[r].Clear();
                        var line = _lines[_row];
                        int end = Math.Min(_col, line.Count - 1);
                        for (int x = 0; x <= end; x++)
                            line[x] = new StyledCell { Ch = ' ', Fg = null, Bg = _curBg, Bold = false };
                    }
                    else if (mode == 2 || mode == 3)
                    {
                        ClearKeepHeader();
                    }
                    break;
                }

                case 'P': // Delete Character (DCH)
                {
                    EnsureRow(_row);
                    var line = _lines[_row];
                    int n = ParseClampedPositiveParam(paramStr, 1, MaxCols);
                    if (_col < line.Count)
                    {
                        int count = Math.Min(n, line.Count - _col);
                        line.RemoveRange(_col, count);
                    }
                    break;
                }

                case '@': // Insert Character (ICH) - clamped to MaxCols
                {
                    EnsureRow(_row);
                    var line = _lines[_row];
                    int n = ParseClampedPositiveParam(paramStr, 1, MaxCols);
                    int maxInsert = Math.Max(0, MaxCols - _col);
                    int count = Math.Min(n, maxInsert);
                    if (_col <= line.Count && count > 0)
                    {
                        for (int k = 0; k < count; k++)
                            line.Insert(_col, new StyledCell { Ch = ' ' });
                        if (line.Count > MaxCols)
                            line.RemoveRange(MaxCols, line.Count - MaxCols);
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
                (0, true) => Color.FromRgb(102, 102, 102),
                (1, true) => Color.FromRgb(255, 85, 85),
                (2, true) => Color.FromRgb(85, 255, 85),
                (3, true) => Color.FromRgb(255, 255, 85),
                (4, true) => Color.FromRgb(128, 170, 250),
                (5, true) => Color.FromRgb(255, 85, 255),
                (6, true) => Color.FromRgb(85, 255, 255),
                _ => Color.FromRgb(255, 255, 255),
            };
        }

        private static Color GetXterm256Color(int n)
        {
            n = Math.Clamp(n, 0, 255);
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
            int clampedCursorCol = Math.Clamp(_col, 0, MaxCols - 1);

            for (int r = 0; r <= lastNonEmptyRow; r++)
            {
                var line = _lines[r];
                bool isCursorRow = (r == _row);

                if (line.Count == 0 && !isCursorRow)
                {
                    if (r < lastNonEmptyRow) para.Inlines.Add(new LineBreak());
                    continue;
                }

                int maxCol = isCursorRow ? Math.Min(MaxCols, Math.Max(line.Count, clampedCursorCol + 1)) : Math.Min(MaxCols, line.Count);
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

                    if (isCursorRow && c == clampedCursorCol)
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
