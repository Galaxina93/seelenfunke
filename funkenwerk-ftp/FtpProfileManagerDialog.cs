using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Win32;

namespace FunkenwerkFtp
{
    public sealed class ProfileManagerDraft
    {
        public string Name { get; set; } = "";
        public string Protocol { get; set; } = "SFTP";
        public string Host { get; set; } = "";
        public int Port { get; set; } = 22;
        public string Username { get; set; } = "";
        public string Password { get; set; } = "";
        public string PrivateKeyPath { get; set; } = "";
        public string RemoteDirectory { get; set; } = "/";
        public string LocalDirectory { get; set; } = "";
    }

    public sealed class FtpProfileManagerDialog : Window
    {
        private readonly List<FtpConnectionProfile> _profiles;
        private readonly bool _isDark;

        private readonly TextBox _filterBox;
        private readonly ListBox _profileList;
        private readonly Button _duplicateBtn;
        private readonly Button _moveUpBtn;
        private readonly Button _moveDownBtn;
        private readonly Button _deleteBtn;

        private readonly TextBlock _editorHeading;
        private readonly TextBlock _editorSourceTxt;
        private readonly TextBox _nameBox;
        private readonly ComboBox _protocolCombo;
        private readonly TextBox _portBox;
        private readonly TextBox _hostBox;
        private readonly TextBox _userBox;
        private readonly PasswordBox _passBox;
        private readonly TextBox _passVisibleBox;
        private readonly Button _togglePassEyeBtn;
        private readonly TextBox _keyPathBox;
        private readonly TextBox _remoteDirBox;
        private readonly TextBox _localDirBox;
        private readonly TextBlock _feedbackTxt;
        private readonly Button _saveBtn;
        private readonly Button _saveConnectBtn;

        private string? _editingProfileId;
        private bool _isCreatingNew;
        private bool _isPassVisible;
        private bool _suppressPassSync;
        private bool _suppressSelectionChange;
        private bool _suppressProtocolPortSync;

        public bool ProfilesChanged { get; private set; }
        public string? SelectedProfileId { get; private set; }
        public bool ConnectRequested { get; private set; }

        public FtpProfileManagerDialog(
            Window owner,
            bool isDark,
            List<FtpConnectionProfile> profiles,
            string? initialSelectedProfileId = null,
            bool startInNewMode = false,
            ProfileManagerDraft? initialDraft = null)
        {
            _profiles = profiles ?? throw new ArgumentNullException(nameof(profiles));
            _isDark = isDark;
            SelectedProfileId = initialSelectedProfileId;

            Title = "FTP-Profile verwalten";
            Icon = owner.Icon;
            Width = 840;
            Height = 560;
            MinWidth = 740;
            MinHeight = 500;
            ResizeMode = ResizeMode.CanResize;
            WindowStartupLocation = owner.IsLoaded ? WindowStartupLocation.CenterOwner : WindowStartupLocation.CenterScreen;
            if (owner.IsLoaded) Owner = owner;

            var bgRoot = BrushHex(isDark ? "#0F1117" : "#F3F4F6");
            var bgCard = BrushHex(isDark ? "#141821" : "#FFFFFF");
            var bgInput = BrushHex(isDark ? "#181C26" : "#F9FAFB");
            var borderSubtle = BrushHex(isDark ? "#242938" : "#E5E7EB");
            var borderStrong = BrushHex(isDark ? "#2D3343" : "#D1D5DB");
            var fgPrimary = BrushHex(isDark ? "#F9FAFB" : "#111827");
            var fgSecondary = BrushHex(isDark ? "#9CA3AF" : "#6B7280");
            var goldBrush = BrushHex("#C5A059");

            Background = bgRoot;
            Foreground = fgPrimary;
            FontFamily = new FontFamily("Segoe UI");

            var root = new Grid { Margin = new Thickness(16) };
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(305) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(14) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

            // =================================================================
            // LEFT PANE: SAVED PROFILES LIST + CRUD ACTIONS
            // =================================================================
            var leftCard = new Border
            {
                Background = bgCard,
                BorderBrush = borderSubtle,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(12),
                Padding = new Thickness(12)
            };

            var leftGrid = new Grid();
            leftGrid.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
            leftGrid.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
            leftGrid.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });
            leftGrid.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });

            var leftHeader = new Grid { Margin = new Thickness(2, 0, 2, 10) };
            leftHeader.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            leftHeader.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var leftTitle = new TextBlock
            {
                Text = "GESPEICHERTE PROFILE",
                Foreground = goldBrush,
                FontSize = 11.5,
                FontWeight = FontWeights.Bold,
                VerticalAlignment = VerticalAlignment.Center
            };
            Grid.SetColumn(leftTitle, 0);
            leftHeader.Children.Add(leftTitle);

            var importBtn = CreateSmallButton("Import", isDark, isPrimary: false, isDanger: false);
            importBtn.ToolTip = "Sitzungen aus WinSCP / PuTTY importieren";
            importBtn.Padding = new Thickness(8, 0, 8, 0);
            importBtn.Click += (_, _) => ImportFromRegistry();
            Grid.SetColumn(importBtn, 1);
            leftHeader.Children.Add(importBtn);

            Grid.SetRow(leftHeader, 0);
            leftGrid.Children.Add(leftHeader);

            // Search box
            var filterBorder = new Border
            {
                Background = bgInput,
                BorderBrush = borderStrong,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(6),
                Margin = new Thickness(0, 0, 0, 10),
                Height = 32
            };
            var filterGrid = new Grid();
            _filterBox = new TextBox
            {
                Background = Brushes.Transparent,
                Foreground = fgPrimary,
                CaretBrush = goldBrush,
                BorderThickness = new Thickness(0),
                VerticalContentAlignment = VerticalAlignment.Center,
                Padding = new Thickness(9, 0, 9, 0),
                FontSize = 12.5
            };
            var filterPlaceholder = new TextBlock
            {
                Text = "Profile filtern...",
                Foreground = fgSecondary,
                FontSize = 12,
                VerticalAlignment = VerticalAlignment.Center,
                Margin = new Thickness(11, 0, 0, 0),
                IsHitTestVisible = false
            };
            _filterBox.TextChanged += (_, _) =>
            {
                filterPlaceholder.Visibility = string.IsNullOrEmpty(_filterBox.Text) ? Visibility.Visible : Visibility.Collapsed;
                RefreshList(keepSelectionId: _editingProfileId);
            };
            filterGrid.Children.Add(_filterBox);
            filterGrid.Children.Add(filterPlaceholder);
            filterBorder.Child = filterGrid;
            Grid.SetRow(filterBorder, 1);
            leftGrid.Children.Add(filterBorder);

            // Profile list
            _profileList = new ListBox
            {
                Background = Brushes.Transparent,
                BorderThickness = new Thickness(0),
                Foreground = fgPrimary,
                HorizontalContentAlignment = HorizontalAlignment.Stretch,
                ItemContainerStyle = CreateProfileListItemStyle(isDark),
                ItemTemplate = CreateProfileItemTemplate(isDark)
            };
            ScrollViewer.SetHorizontalScrollBarVisibility(_profileList, ScrollBarVisibility.Disabled);
            _profileList.SelectionChanged += ProfileList_SelectionChanged;
            _profileList.MouseDoubleClick += (_, _) =>
            {
                if (_profileList.SelectedItem is FtpConnectionProfile p)
                {
                    SelectedProfileId = p.Id;
                    DialogResult = true;
                }
            };
            _profileList.KeyDown += (_, ke) =>
            {
                if (ke.Key == Key.Delete && _profileList.SelectedItem is FtpConnectionProfile)
                {
                    DeleteSelectedProfile();
                    ke.Handled = true;
                }
            };
            Grid.SetRow(_profileList, 2);
            leftGrid.Children.Add(_profileList);

            // Bottom CRUD action bar
            var leftActions = new Grid { Margin = new Thickness(0, 10, 0, 0) };
            leftActions.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            leftActions.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var leftBtnsPrimary = new StackPanel { Orientation = Orientation.Horizontal };
            var newBtn = CreateSmallButton("+ Neu", isDark, isPrimary: true, isDanger: false);
            newBtn.ToolTip = "Neues FTP-/SFTP-Profil anlegen (Strg+N)";
            newBtn.Click += (_, _) => StartNewProfileMode(null);
            leftBtnsPrimary.Children.Add(newBtn);

            _duplicateBtn = CreateSmallButton("Duplizieren", isDark, isPrimary: false, isDanger: false);
            _duplicateBtn.Margin = new Thickness(6, 0, 0, 0);
            _duplicateBtn.ToolTip = "Ausgewähltes Profil kopieren (z. B. weiterer Benutzer am selben Server)";
            _duplicateBtn.Click += (_, _) => DuplicateSelectedProfile();
            leftBtnsPrimary.Children.Add(_duplicateBtn);

            Grid.SetColumn(leftBtnsPrimary, 0);
            leftActions.Children.Add(leftBtnsPrimary);

            var leftBtnsOrder = new StackPanel { Orientation = Orientation.Horizontal };
            _moveUpBtn = CreateSmallButton("↑", isDark, isPrimary: false, isDanger: false);
            _moveUpBtn.Width = 28;
            _moveUpBtn.Padding = new Thickness(0);
            _moveUpBtn.ToolTip = "Nach oben verschieben";
            _moveUpBtn.Click += (_, _) => MoveSelectedProfile(-1);
            leftBtnsOrder.Children.Add(_moveUpBtn);

            _moveDownBtn = CreateSmallButton("↓", isDark, isPrimary: false, isDanger: false);
            _moveDownBtn.Width = 28;
            _moveDownBtn.Padding = new Thickness(0);
            _moveDownBtn.Margin = new Thickness(4, 0, 0, 0);
            _moveDownBtn.ToolTip = "Nach unten verschieben";
            _moveDownBtn.Click += (_, _) => MoveSelectedProfile(1);
            leftBtnsOrder.Children.Add(_moveDownBtn);

            _deleteBtn = CreateSmallButton("✕", isDark, isPrimary: false, isDanger: true);
            _deleteBtn.Width = 28;
            _deleteBtn.Padding = new Thickness(0);
            _deleteBtn.Margin = new Thickness(4, 0, 0, 0);
            _deleteBtn.ToolTip = "Ausgewähltes Profil entfernen (Entf)";
            _deleteBtn.Click += (_, _) => DeleteSelectedProfile();
            leftBtnsOrder.Children.Add(_deleteBtn);

            Grid.SetColumn(leftBtnsOrder, 1);
            leftActions.Children.Add(leftBtnsOrder);

            Grid.SetRow(leftActions, 3);
            leftGrid.Children.Add(leftActions);

            leftCard.Child = leftGrid;
            Grid.SetColumn(leftCard, 0);
            root.Children.Add(leftCard);

            // =================================================================
            // RIGHT PANE: PROFILE DETAIL FORM
            // =================================================================
            var rightCard = new Border
            {
                Background = bgCard,
                BorderBrush = borderSubtle,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(12),
                Padding = new Thickness(18, 14, 18, 14)
            };

            var rightGrid = new Grid();
            rightGrid.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
            rightGrid.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });
            rightGrid.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
            rightGrid.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });

            // Heading row
            var headingRow = new Grid { Margin = new Thickness(0, 0, 0, 12) };
            headingRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            headingRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            _editorHeading = new TextBlock
            {
                Text = "Profil bearbeiten",
                Foreground = fgPrimary,
                FontSize = 15,
                FontWeight = FontWeights.Bold,
                VerticalAlignment = VerticalAlignment.Center
            };
            Grid.SetColumn(_editorHeading, 0);
            headingRow.Children.Add(_editorHeading);

            _editorSourceTxt = new TextBlock
            {
                Foreground = fgSecondary,
                FontSize = 11.5,
                VerticalAlignment = VerticalAlignment.Center
            };
            Grid.SetColumn(_editorSourceTxt, 1);
            headingRow.Children.Add(_editorSourceTxt);

            Grid.SetRow(headingRow, 0);
            rightGrid.Children.Add(headingRow);

            // Scrollable Form
            var formScroll = new ScrollViewer
            {
                VerticalScrollBarVisibility = ScrollBarVisibility.Auto,
                HorizontalScrollBarVisibility = ScrollBarVisibility.Disabled
            };
            var formStack = new StackPanel();

            // Row 1: Profilname
            _nameBox = CreateInputBox(bgInput, fgPrimary, borderStrong, goldBrush);
            formStack.Children.Add(CreateFieldBlock("Profilname", _nameBox, fgSecondary));

            // Row 2: Protokoll + Server-Host + Port
            var connRow = new Grid { Margin = new Thickness(0, 10, 0, 0) };
            connRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(105) });
            connRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(10) });
            connRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            connRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(10) });
            connRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(82) });

            _protocolCombo = new ComboBox
            {
                Height = 34,
                VerticalContentAlignment = VerticalAlignment.Center,
                Padding = new Thickness(8, 0, 8, 0)
            };
            if (Application.Current?.TryFindResource("FwComboBox") is Style comboStyle)
                _protocolCombo.Style = comboStyle;
            _protocolCombo.Items.Add(new ComboBoxItem { Content = "SFTP" });
            _protocolCombo.Items.Add(new ComboBoxItem { Content = "FTPS" });
            _protocolCombo.Items.Add(new ComboBoxItem { Content = "FTP" });
            _protocolCombo.SelectedIndex = 0;
            _protocolCombo.SelectionChanged += (_, _) =>
            {
                if (_suppressProtocolPortSync || _portBox == null) return;
                string proto = GetFormProtocol();
                if (proto == "SFTP" && _portBox.Text.Trim() == "21") _portBox.Text = "22";
                else if (proto != "SFTP" && _portBox.Text.Trim() == "22") _portBox.Text = "21";
            };
            var protoField = CreateFieldBlock("Protokoll", _protocolCombo, fgSecondary, topMargin: 0);
            Grid.SetColumn(protoField, 0);
            connRow.Children.Add(protoField);

            _hostBox = CreateInputBox(bgInput, fgPrimary, borderStrong, goldBrush);
            var hostField = CreateFieldBlock("Server-Host", _hostBox, fgSecondary, topMargin: 0);
            Grid.SetColumn(hostField, 2);
            connRow.Children.Add(hostField);

            _portBox = CreateInputBox(bgInput, fgPrimary, borderStrong, goldBrush);
            _portBox.HorizontalContentAlignment = HorizontalAlignment.Center;
            _portBox.Text = "22";
            var portField = CreateFieldBlock("Port", _portBox, fgSecondary, topMargin: 0);
            Grid.SetColumn(portField, 4);
            connRow.Children.Add(portField);

            formStack.Children.Add(connRow);

            // Row 3: Benutzername + Passwort (mit Auge-Icon)
            var authRow = new Grid { Margin = new Thickness(0, 10, 0, 0) };
            authRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            authRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(10) });
            authRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

            _userBox = CreateInputBox(bgInput, fgPrimary, borderStrong, goldBrush);
            var userField = CreateFieldBlock("Benutzername", _userBox, fgSecondary, topMargin: 0);
            Grid.SetColumn(userField, 0);
            authRow.Children.Add(userField);

            var passContainer = new Border
            {
                Height = 34,
                Background = bgInput,
                BorderBrush = borderStrong,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(5)
            };
            var passGrid = new Grid();
            passGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            passGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(30) });

            _passBox = new PasswordBox
            {
                Background = Brushes.Transparent,
                Foreground = fgPrimary,
                CaretBrush = goldBrush,
                BorderThickness = new Thickness(0),
                VerticalContentAlignment = VerticalAlignment.Center,
                Padding = new Thickness(9, 0, 6, 0),
                FontSize = 13
            };
            _passVisibleBox = new TextBox
            {
                Background = Brushes.Transparent,
                Foreground = fgPrimary,
                CaretBrush = goldBrush,
                BorderThickness = new Thickness(0),
                VerticalContentAlignment = VerticalAlignment.Center,
                Padding = new Thickness(9, 0, 6, 0),
                FontSize = 13,
                Visibility = Visibility.Collapsed
            };
            _passBox.PasswordChanged += (_, _) =>
            {
                if (_suppressPassSync) return;
                _suppressPassSync = true;
                _passVisibleBox.Text = _passBox.Password;
                _suppressPassSync = false;
            };
            _passVisibleBox.TextChanged += (_, _) =>
            {
                if (_suppressPassSync) return;
                _suppressPassSync = true;
                _passBox.Password = _passVisibleBox.Text;
                _suppressPassSync = false;
            };

            _togglePassEyeBtn = new Button
            {
                Content = "👁",
                Background = Brushes.Transparent,
                Foreground = fgSecondary,
                BorderThickness = new Thickness(0),
                Cursor = Cursors.Hand,
                ToolTip = "Passwort ein-/ausblenden"
            };
            _togglePassEyeBtn.Click += (_, _) =>
            {
                _isPassVisible = !_isPassVisible;
                _passVisibleBox.Visibility = _isPassVisible ? Visibility.Visible : Visibility.Collapsed;
                _passBox.Visibility = _isPassVisible ? Visibility.Collapsed : Visibility.Visible;
                _togglePassEyeBtn.Content = _isPassVisible ? "🙈" : "👁";
            };

            Grid.SetColumn(_passBox, 0);
            Grid.SetColumn(_passVisibleBox, 0);
            Grid.SetColumn(_togglePassEyeBtn, 1);
            passGrid.Children.Add(_passBox);
            passGrid.Children.Add(_passVisibleBox);
            passGrid.Children.Add(_togglePassEyeBtn);
            passContainer.Child = passGrid;

            var passField = CreateFieldBlock("Passwort", passContainer, fgSecondary, topMargin: 0);
            Grid.SetColumn(passField, 2);
            authRow.Children.Add(passField);

            formStack.Children.Add(authRow);

            // Row 4: SSH Private Key (optional)
            var keyRow = new Grid();
            keyRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            keyRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            keyRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            _keyPathBox = CreateInputBox(bgInput, fgPrimary, borderStrong, goldBrush);
            Grid.SetColumn(_keyPathBox, 0);
            keyRow.Children.Add(_keyPathBox);

            var pickKeyBtn = CreateSmallButton("...", isDark, isPrimary: false, isDanger: false);
            pickKeyBtn.Height = 34;
            pickKeyBtn.Width = 34;
            pickKeyBtn.Padding = new Thickness(0);
            pickKeyBtn.Margin = new Thickness(6, 0, 0, 0);
            pickKeyBtn.ToolTip = "SSH Private Key auswählen";
            pickKeyBtn.Click += (_, _) =>
            {
                var dlg = new OpenFileDialog
                {
                    Title = "OpenSSH Private Key auswählen",
                    InitialDirectory = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh")
                };
                if (dlg.ShowDialog() == true)
                    _keyPathBox.Text = dlg.FileName;
            };
            Grid.SetColumn(pickKeyBtn, 1);
            keyRow.Children.Add(pickKeyBtn);

            var clearKeyBtn = CreateSmallButton("✕", isDark, isPrimary: false, isDanger: false);
            clearKeyBtn.Height = 34;
            clearKeyBtn.Width = 34;
            clearKeyBtn.Padding = new Thickness(0);
            clearKeyBtn.Margin = new Thickness(4, 0, 0, 0);
            clearKeyBtn.ToolTip = "SSH-Key entfernen";
            clearKeyBtn.Click += (_, _) => _keyPathBox.Text = "";
            Grid.SetColumn(clearKeyBtn, 2);
            keyRow.Children.Add(clearKeyBtn);

            formStack.Children.Add(CreateFieldBlock("SSH Private Key (optional)", keyRow, fgSecondary, topMargin: 10));

            // Row 5: Server-Startordner + Lokaler Startordner
            var dirRow = new Grid { Margin = new Thickness(0, 10, 0, 0) };
            dirRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            dirRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(10) });
            dirRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

            _remoteDirBox = CreateInputBox(bgInput, fgPrimary, borderStrong, goldBrush);
            _remoteDirBox.Text = "/";
            var remoteField = CreateFieldBlock("Server-Startordner", _remoteDirBox, fgSecondary, topMargin: 0);
            Grid.SetColumn(remoteField, 0);
            dirRow.Children.Add(remoteField);

            var localInputGrid = new Grid();
            localInputGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            localInputGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            _localDirBox = CreateInputBox(bgInput, fgPrimary, borderStrong, goldBrush);
            Grid.SetColumn(_localDirBox, 0);
            localInputGrid.Children.Add(_localDirBox);

            var pickLocalBtn = CreateSmallButton("...", isDark, isPrimary: false, isDanger: false);
            pickLocalBtn.Height = 34;
            pickLocalBtn.Width = 34;
            pickLocalBtn.Padding = new Thickness(0);
            pickLocalBtn.Margin = new Thickness(6, 0, 0, 0);
            pickLocalBtn.ToolTip = "Lokalen Startordner wählen";
            pickLocalBtn.Click += (_, _) =>
            {
                var folderDlg = new OpenFolderDialog { Title = "Lokalen Startordner wählen" };
                if (Directory.Exists(_localDirBox.Text.Trim()))
                    folderDlg.InitialDirectory = _localDirBox.Text.Trim();
                if (folderDlg.ShowDialog() == true)
                    _localDirBox.Text = folderDlg.FolderName;
            };
            Grid.SetColumn(pickLocalBtn, 1);
            localInputGrid.Children.Add(pickLocalBtn);

            var localField = CreateFieldBlock("Lokaler Startordner", localInputGrid, fgSecondary, topMargin: 0);
            Grid.SetColumn(localField, 2);
            dirRow.Children.Add(localField);

            formStack.Children.Add(dirRow);
            formScroll.Content = formStack;
            Grid.SetRow(formScroll, 1);
            rightGrid.Children.Add(formScroll);

            // Inline feedback text
            _feedbackTxt = new TextBlock
            {
                FontSize = 12,
                Margin = new Thickness(0, 10, 0, 0),
                TextWrapping = TextWrapping.Wrap,
                Visibility = Visibility.Collapsed
            };
            Grid.SetRow(_feedbackTxt, 2);
            rightGrid.Children.Add(_feedbackTxt);

            // Bottom action bar
            var footer = new Grid { Margin = new Thickness(0, 14, 0, 0) };
            footer.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            footer.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            footer.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            _saveBtn = CreateDialogButton("Speichern", isDark, isPrimary: false, isDanger: false);
            _saveBtn.Click += (_, _) => SaveCurrentForm(connectAfterSave: false);
            Grid.SetColumn(_saveBtn, 0);
            footer.Children.Add(_saveBtn);

            var rightFooterBtns = new StackPanel { Orientation = Orientation.Horizontal };
            var closeBtn = CreateDialogButton("Schließen", isDark, isPrimary: false, isDanger: false);
            closeBtn.Click += (_, _) => DialogResult = ProfilesChanged;
            rightFooterBtns.Children.Add(closeBtn);

            _saveConnectBtn = CreateDialogButton("Speichern & Verbinden", isDark, isPrimary: true, isDanger: false);
            _saveConnectBtn.Margin = new Thickness(8, 0, 0, 0);
            _saveConnectBtn.Click += (_, _) => SaveCurrentForm(connectAfterSave: true);
            rightFooterBtns.Children.Add(_saveConnectBtn);

            Grid.SetColumn(rightFooterBtns, 2);
            footer.Children.Add(rightFooterBtns);

            Grid.SetRow(footer, 3);
            rightGrid.Children.Add(footer);

            rightCard.Child = rightGrid;
            Grid.SetColumn(rightCard, 2);
            root.Children.Add(rightCard);

            Content = root;

            PreviewKeyDown += (_, ke) =>
            {
                if (ke.Key == Key.Escape)
                {
                    DialogResult = ProfilesChanged;
                    ke.Handled = true;
                }
                else if (ke.Key == Key.S && (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control)
                {
                    SaveCurrentForm(connectAfterSave: false);
                    ke.Handled = true;
                }
                else if (ke.Key == Key.N && (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control)
                {
                    StartNewProfileMode(null);
                    ke.Handled = true;
                }
            };

            RefreshList(keepSelectionId: initialSelectedProfileId);

            if (startInNewMode || _profiles.Count == 0)
            {
                StartNewProfileMode(initialDraft);
            }
            else if (!string.IsNullOrWhiteSpace(initialSelectedProfileId))
            {
                SelectProfileById(initialSelectedProfileId);
            }
            else if (_profiles.Count > 0)
            {
                SelectProfileById(_profiles[0].Id);
            }

            Loaded += (_, _) =>
            {
                if (_isCreatingNew)
                {
                    _nameBox.Focus();
                    _nameBox.SelectAll();
                }
            };
        }

        public void StartNewProfileMode(ProfileManagerDraft? draft)
        {
            _isCreatingNew = true;
            _editingProfileId = null;

            _suppressSelectionChange = true;
            _profileList.SelectedItem = null;
            _suppressSelectionChange = false;

            _editorHeading.Text = "Neues Profil anlegen";
            _editorSourceTxt.Text = "";
            ClearFeedback();

            _nameBox.Text = draft?.Name ?? "";
            SetFormProtocol(draft?.Protocol ?? "SFTP");
            _hostBox.Text = draft?.Host ?? "";
            _portBox.Text = (draft != null && draft.Port >= 1 && draft.Port <= 65535 ? draft.Port : FtpProfileStore.DefaultPortForProtocol(draft?.Protocol)).ToString();
            _userBox.Text = draft?.Username ?? "";
            SetFormPassword(draft?.Password ?? "");
            _keyPathBox.Text = draft?.PrivateKeyPath ?? "";
            _remoteDirBox.Text = string.IsNullOrWhiteSpace(draft?.RemoteDirectory) ? "/" : draft.RemoteDirectory;
            _localDirBox.Text = !string.IsNullOrWhiteSpace(draft?.LocalDirectory)
                ? draft.LocalDirectory
                : Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);

            UpdateListActionStates();
            if (IsLoaded)
            {
                _nameBox.Focus();
                _nameBox.SelectAll();
            }
        }

        public void SelectProfileById(string? profileId)
        {
            if (string.IsNullOrWhiteSpace(profileId)) return;
            var match = _profiles.FirstOrDefault(p => string.Equals(p.Id, profileId, StringComparison.OrdinalIgnoreCase));
            if (match == null) return;

            _suppressSelectionChange = true;
            _profileList.SelectedItem = match;
            _suppressSelectionChange = false;

            LoadProfileIntoForm(match);
            SelectedProfileId = match.Id;
        }

        private void ProfileList_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressSelectionChange) return;
            if (_profileList.SelectedItem is FtpConnectionProfile p)
            {
                LoadProfileIntoForm(p);
                SelectedProfileId = p.Id;
            }
            UpdateListActionStates();
        }

        private void LoadProfileIntoForm(FtpConnectionProfile p)
        {
            _isCreatingNew = false;
            _editingProfileId = p.Id;
            _editorHeading.Text = "Profil bearbeiten";
            _editorSourceTxt.Text = string.IsNullOrWhiteSpace(p.Source) ? "" : $"Quelle: {p.Source}";
            ClearFeedback();

            _nameBox.Text = p.Name;
            SetFormProtocol(p.Protocol);
            _hostBox.Text = p.Host;
            _portBox.Text = p.Port.ToString();
            _userBox.Text = p.Username;
            try
            {
                SetFormPassword(FunkenwerkFtpSecurity.DecryptSecret(p.EncryptedPassword));
            }
            catch (Exception ex)
            {
                SetFormPassword("");
                ShowFeedback($"Tresor-Warnung: {ex.Message}", isError: true);
            }
            _keyPathBox.Text = p.PrivateKeyPath ?? "";
            _remoteDirBox.Text = string.IsNullOrWhiteSpace(p.RemoteDirectory) ? "/" : p.RemoteDirectory;
            _localDirBox.Text = p.LocalDirectory ?? "";

            UpdateListActionStates();
        }

        private bool SaveCurrentForm(bool connectAfterSave)
        {
            ClearFeedback();
            try
            {
                string proto = GetFormProtocol();
                int port = FtpProfileStore.ParseAndValidatePort(_portBox.Text, proto);
                string name = _nameBox.Text.Trim();
                string host = _hostBox.Text.Trim();
                string user = _userBox.Text.Trim();
                string pass = GetFormPassword();
                string keyPath = _keyPathBox.Text.Trim();
                string remoteDir = _remoteDirBox.Text.Trim();
                string localDir = _localDirBox.Text.Trim();

                FtpConnectionProfile saved;
                if (_isCreatingNew || string.IsNullOrWhiteSpace(_editingProfileId))
                {
                    saved = FtpProfileStore.CreateProfile(
                        _profiles, name, proto, host, port, user, pass, keyPath, remoteDir, localDir);
                }
                else
                {
                    saved = FtpProfileStore.UpdateProfile(
                        _profiles, _editingProfileId!, name, proto, host, port, user, pass,
                        preserveExistingPasswordIfNull: false, keyPath, remoteDir, localDir);
                }

                FunkenwerkFtpSecurity.SaveProfiles(_profiles);
                FunkenwerkFtpSecurity.SaveLastSelectedProfileId(saved.Id);
                ProfilesChanged = true;
                _isCreatingNew = false;
                _editingProfileId = saved.Id;
                SelectedProfileId = saved.Id;

                RefreshList(keepSelectionId: saved.Id);
                LoadProfileIntoForm(saved);
                ShowFeedback($"Profil '{saved.Name}' verschlüsselt gespeichert.", isError: false);

                if (connectAfterSave)
                {
                    ConnectRequested = true;
                    DialogResult = true;
                }
                return true;
            }
            catch (Exception ex)
            {
                ShowFeedback(ex.Message, isError: true);
                return false;
            }
        }

        private void DuplicateSelectedProfile()
        {
            if (_profileList.SelectedItem is not FtpConnectionProfile selected) return;
            try
            {
                var clone = FtpProfileStore.DuplicateProfile(_profiles, selected.Id);
                FunkenwerkFtpSecurity.SaveProfiles(_profiles);
                ProfilesChanged = true;
                SelectedProfileId = clone.Id;
                _filterBox.Text = "";
                RefreshList(keepSelectionId: clone.Id);
                LoadProfileIntoForm(clone);
                ShowFeedback($"Profil '{clone.Name}' dupliziert. Benutzername & Passwort jetzt anpassbar.", isError: false);
                if (IsLoaded)
                {
                    _nameBox.Focus();
                    _nameBox.SelectAll();
                }
            }
            catch (Exception ex)
            {
                ShowFeedback(ex.Message, isError: true);
            }
        }

        private void MoveSelectedProfile(int delta)
        {
            if (_profileList.SelectedItem is not FtpConnectionProfile selected) return;
            if (!string.IsNullOrWhiteSpace(_filterBox.Text)) return;

            if (FtpProfileStore.MoveProfile(_profiles, selected.Id, delta))
            {
                FunkenwerkFtpSecurity.SaveProfiles(_profiles);
                ProfilesChanged = true;
                RefreshList(keepSelectionId: selected.Id);
            }
        }

        private void DeleteSelectedProfile()
        {
            if (_profileList.SelectedItem is not FtpConnectionProfile selected) return;
            if (!FtpModalDialogs.ShowConfirmDialog(
                    this,
                    _isDark,
                    "Profil entfernen",
                    $"Gespeichertes Profil '{selected.Name}' ({selected.SubtitleLabel}) wirklich entfernen?",
                    "Entfernen",
                    isDanger: true))
                return;

            DeleteProfileByIdCore(selected.Id);
        }

        private void DeleteProfileByIdCore(string profileId)
        {
            int oldIdx = _profiles.FindIndex(p => string.Equals(p.Id, profileId, StringComparison.OrdinalIgnoreCase));
            if (!FtpProfileStore.DeleteProfile(_profiles, profileId)) return;

            FunkenwerkFtpSecurity.SaveProfiles(_profiles);
            ProfilesChanged = true;

            if (_profiles.Count > 0)
            {
                int nextIdx = Math.Clamp(oldIdx, 0, _profiles.Count - 1);
                var next = _profiles[nextIdx];
                SelectedProfileId = next.Id;
                RefreshList(keepSelectionId: next.Id);
                LoadProfileIntoForm(next);
            }
            else
            {
                SelectedProfileId = null;
                RefreshList(keepSelectionId: null);
                StartNewProfileMode(null);
            }
            ShowFeedback("Profil entfernt.", isError: false);
        }

        private void ImportFromRegistry()
        {
            try
            {
                int before = _profiles.Count;
                bool added = FunkenwerkFtpSecurity.ImportFromWinScpAndPuttyRegistry(_profiles);
                if (added)
                {
                    FtpProfileStore.EnsureProfileIntegrity(_profiles);
                    FunkenwerkFtpSecurity.SaveProfiles(_profiles);
                    ProfilesChanged = true;
                    RefreshList(keepSelectionId: _editingProfileId ?? _profiles.FirstOrDefault()?.Id);
                    int count = Math.Max(0, _profiles.Count - before);
                    ShowFeedback($"{count} Profil(e) aus WinSCP / PuTTY importiert.", isError: false);
                }
                else
                {
                    ShowFeedback("Keine neuen WinSCP- oder PuTTY-Profile gefunden.", isError: false);
                }
            }
            catch (Exception ex)
            {
                ShowFeedback(ex.Message, isError: true);
            }
        }

        private void RefreshList(string? keepSelectionId)
        {
            string q = (_filterBox.Text ?? "").Trim();
            IEnumerable<FtpConnectionProfile> visible = _profiles;
            if (!string.IsNullOrEmpty(q))
            {
                visible = visible.Where(p =>
                    p.Name.Contains(q, StringComparison.OrdinalIgnoreCase) ||
                    p.Host.Contains(q, StringComparison.OrdinalIgnoreCase) ||
                    p.Username.Contains(q, StringComparison.OrdinalIgnoreCase) ||
                    p.ProtocolTag.Contains(q, StringComparison.OrdinalIgnoreCase));
            }

            var list = visible.ToList();
            _suppressSelectionChange = true;
            _profileList.ItemsSource = null;
            _profileList.ItemsSource = list;
            if (!string.IsNullOrWhiteSpace(keepSelectionId))
            {
                var match = list.FirstOrDefault(p => string.Equals(p.Id, keepSelectionId, StringComparison.OrdinalIgnoreCase));
                _profileList.SelectedItem = match;
            }
            _suppressSelectionChange = false;
            UpdateListActionStates();
        }

        private void UpdateListActionStates()
        {
            bool hasSel = _profileList.SelectedItem is FtpConnectionProfile;
            bool filterActive = !string.IsNullOrWhiteSpace(_filterBox.Text);
            int idx = hasSel ? _profiles.FindIndex(p => string.Equals(p.Id, ((FtpConnectionProfile)_profileList.SelectedItem!).Id, StringComparison.OrdinalIgnoreCase)) : -1;

            _duplicateBtn.IsEnabled = hasSel;
            _deleteBtn.IsEnabled = hasSel;
            _moveUpBtn.IsEnabled = hasSel && !filterActive && idx > 0;
            _moveDownBtn.IsEnabled = hasSel && !filterActive && idx >= 0 && idx < _profiles.Count - 1;
        }

        private string GetFormProtocol() =>
            (_protocolCombo.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "SFTP";

        private void SetFormProtocol(string? protocol)
        {
            string norm = FtpProfileStore.NormalizeProtocol(protocol);
            _suppressProtocolPortSync = true;
            foreach (ComboBoxItem item in _protocolCombo.Items)
            {
                if (string.Equals(item.Content?.ToString(), norm, StringComparison.OrdinalIgnoreCase))
                {
                    _protocolCombo.SelectedItem = item;
                    break;
                }
            }
            _suppressProtocolPortSync = false;
        }

        private string GetFormPassword() => _isPassVisible ? _passVisibleBox.Text : _passBox.Password;

        private void SetFormPassword(string val)
        {
            _suppressPassSync = true;
            _passBox.Password = val ?? "";
            _passVisibleBox.Text = val ?? "";
            _suppressPassSync = false;
        }

        private void ShowFeedback(string message, bool isError)
        {
            _feedbackTxt.Text = message;
            _feedbackTxt.Foreground = BrushHex(isError ? "#F87171" : "#C5A059");
            _feedbackTxt.Visibility = Visibility.Visible;
        }

        private void ClearFeedback()
        {
            _feedbackTxt.Text = "";
            _feedbackTxt.Visibility = Visibility.Collapsed;
        }

        // Self-test helpers (headless STA verification without modal blocking)
        internal void FillFormForSelfTest(string name, string protocol, string host, int port, string user, string password, string remoteDir = "/")
        {
            _nameBox.Text = name;
            SetFormProtocol(protocol);
            _hostBox.Text = host;
            _portBox.Text = port.ToString();
            _userBox.Text = user;
            SetFormPassword(password);
            _remoteDirBox.Text = remoteDir;
        }

        internal bool SaveFormForSelfTest() => SaveCurrentForm(connectAfterSave: false);
        internal void DuplicateSelectedForSelfTest() => DuplicateSelectedProfile();
        internal void DeleteProfileForSelfTest(string profileId) => DeleteProfileByIdCore(profileId);

        // =====================================================================
        // CLEAN UI BUILDERS (100 % DARK & LIGHT MODE PARITY)
        // =====================================================================
        private static StackPanel CreateFieldBlock(string label, UIElement input, Brush fgLabel, double topMargin = 0)
        {
            var sp = new StackPanel { Margin = new Thickness(0, topMargin, 0, 0) };
            sp.Children.Add(new TextBlock
            {
                Text = label,
                Foreground = fgLabel,
                FontSize = 11.5,
                FontWeight = FontWeights.SemiBold,
                Margin = new Thickness(1, 0, 0, 4)
            });
            sp.Children.Add(input);
            return sp;
        }

        private static TextBox CreateInputBox(Brush bg, Brush fg, Brush border, Brush caret) =>
            new()
            {
                Height = 34,
                Background = bg,
                Foreground = fg,
                BorderBrush = border,
                BorderThickness = new Thickness(1),
                CaretBrush = caret,
                VerticalContentAlignment = VerticalAlignment.Center,
                Padding = new Thickness(9, 0, 9, 0),
                FontSize = 13
            };

        private static Button CreateSmallButton(string text, bool isDark, bool isPrimary, bool isDanger)
        {
            Brush bg = isDanger
                ? BrushHex(isDark ? "#2A181B" : "#FEE2E2")
                : isPrimary
                    ? BrushHex("#C5A059")
                    : BrushHex(isDark ? "#1D2230" : "#F3F4F6");

            Brush fg = isDanger
                ? BrushHex(isDark ? "#F87171" : "#B91C1C")
                : isPrimary
                    ? BrushHex("#0F1117")
                    : BrushHex(isDark ? "#F9FAFB" : "#111827");

            Brush border = isDanger
                ? BrushHex(isDark ? "#4D252B" : "#FCA5A5")
                : isPrimary
                    ? BrushHex("#C5A059")
                    : BrushHex(isDark ? "#2D3343" : "#D1D5DB");

            var btn = new Button
            {
                Content = text,
                Height = 28,
                Padding = new Thickness(10, 0, 10, 0),
                Background = bg,
                Foreground = fg,
                BorderBrush = border,
                BorderThickness = new Thickness(1),
                FontSize = 12,
                FontWeight = isPrimary ? FontWeights.Bold : FontWeights.SemiBold,
                Cursor = Cursors.Hand
            };
            btn.Template = CreateRoundedButtonTemplate(6, isDark, isPrimary, isDanger);
            return btn;
        }

        private static Button CreateDialogButton(string text, bool isDark, bool isPrimary, bool isDanger)
        {
            Brush bg = isDanger
                ? BrushHex("#DC2626")
                : isPrimary
                    ? BrushHex("#C5A059")
                    : BrushHex(isDark ? "#1E2330" : "#F3F4F6");

            Brush fg = isDanger
                ? Brushes.White
                : isPrimary
                    ? BrushHex("#0F1117")
                    : BrushHex(isDark ? "#E5E7EB" : "#111827");

            var btn = new Button
            {
                Content = text,
                Height = 34,
                Padding = new Thickness(16, 0, 16, 0),
                Background = bg,
                Foreground = fg,
                FontWeight = (isPrimary || isDanger) ? FontWeights.Bold : FontWeights.SemiBold,
                BorderBrush = BrushHex(isDark ? "#2D3343" : "#D1D5DB"),
                BorderThickness = new Thickness((isPrimary || isDanger) ? 0 : 1),
                Cursor = Cursors.Hand
            };
            btn.Template = CreateRoundedButtonTemplate(6, isDark, isPrimary, isDanger);
            return btn;
        }

        private static ControlTemplate CreateRoundedButtonTemplate(double radius, bool isDark, bool isPrimary, bool isDanger)
        {
            var template = new ControlTemplate(typeof(Button));
            var borderFactory = new FrameworkElementFactory(typeof(Border), "Bd");
            borderFactory.SetValue(Border.BackgroundProperty, new TemplateBindingExtension(Button.BackgroundProperty));
            borderFactory.SetValue(Border.BorderBrushProperty, new TemplateBindingExtension(Button.BorderBrushProperty));
            borderFactory.SetValue(Border.BorderThicknessProperty, new TemplateBindingExtension(Button.BorderThicknessProperty));
            borderFactory.SetValue(Border.CornerRadiusProperty, new CornerRadius(radius));
            borderFactory.SetValue(Border.PaddingProperty, new TemplateBindingExtension(Button.PaddingProperty));

            var cpFactory = new FrameworkElementFactory(typeof(ContentPresenter));
            cpFactory.SetValue(ContentPresenter.HorizontalAlignmentProperty, HorizontalAlignment.Center);
            cpFactory.SetValue(ContentPresenter.VerticalAlignmentProperty, VerticalAlignment.Center);
            borderFactory.AppendChild(cpFactory);
            template.VisualTree = borderFactory;

            var hoverTrigger = new Trigger { Property = UIElement.IsMouseOverProperty, Value = true };
            if (isPrimary)
            {
                hoverTrigger.Setters.Add(new Setter(Border.BackgroundProperty, BrushHex("#D4B06A"), "Bd"));
            }
            else if (isDanger)
            {
                hoverTrigger.Setters.Add(new Setter(Border.BorderBrushProperty, BrushHex("#F87171"), "Bd"));
            }
            else
            {
                hoverTrigger.Setters.Add(new Setter(Border.BorderBrushProperty, BrushHex("#C5A059"), "Bd"));
                hoverTrigger.Setters.Add(new Setter(Border.BackgroundProperty, BrushHex(isDark ? "#24C5A059" : "#1FC5A059"), "Bd"));
            }
            template.Triggers.Add(hoverTrigger);

            var disabledTrigger = new Trigger { Property = UIElement.IsEnabledProperty, Value = false };
            disabledTrigger.Setters.Add(new Setter(UIElement.OpacityProperty, 0.42));
            template.Triggers.Add(disabledTrigger);

            return template;
        }

        private static Style CreateProfileListItemStyle(bool isDark)
        {
            var style = new Style(typeof(ListBoxItem));
            style.Setters.Add(new Setter(Control.BackgroundProperty, Brushes.Transparent));
            style.Setters.Add(new Setter(Control.BorderBrushProperty, Brushes.Transparent));
            style.Setters.Add(new Setter(Control.BorderThicknessProperty, new Thickness(1)));
            style.Setters.Add(new Setter(FrameworkElement.MarginProperty, new Thickness(0, 2, 0, 2)));
            style.Setters.Add(new Setter(Control.PaddingProperty, new Thickness(10, 7, 10, 7)));
            style.Setters.Add(new Setter(Control.HorizontalContentAlignmentProperty, HorizontalAlignment.Stretch));
            style.Setters.Add(new Setter(FrameworkElement.CursorProperty, Cursors.Hand));

            var tpl = new ControlTemplate(typeof(ListBoxItem));
            var bd = new FrameworkElementFactory(typeof(Border), "RowBd");
            bd.SetValue(Border.BackgroundProperty, new TemplateBindingExtension(Control.BackgroundProperty));
            bd.SetValue(Border.BorderBrushProperty, new TemplateBindingExtension(Control.BorderBrushProperty));
            bd.SetValue(Border.BorderThicknessProperty, new TemplateBindingExtension(Control.BorderThicknessProperty));
            bd.SetValue(Border.CornerRadiusProperty, new CornerRadius(7));
            bd.SetValue(Border.PaddingProperty, new TemplateBindingExtension(Control.PaddingProperty));

            var cp = new FrameworkElementFactory(typeof(ContentPresenter));
            bd.AppendChild(cp);
            tpl.VisualTree = bd;

            var hover = new Trigger { Property = UIElement.IsMouseOverProperty, Value = true };
            hover.Setters.Add(new Setter(Border.BackgroundProperty, BrushHex(isDark ? "#1EC5A059" : "#1AC5A059"), "RowBd"));
            tpl.Triggers.Add(hover);

            var sel = new Trigger { Property = ListBoxItem.IsSelectedProperty, Value = true };
            sel.Setters.Add(new Setter(Border.BackgroundProperty, BrushHex(isDark ? "#2EC5A059" : "#26C5A059"), "RowBd"));
            sel.Setters.Add(new Setter(Border.BorderBrushProperty, BrushHex("#88C5A059"), "RowBd"));
            tpl.Triggers.Add(sel);

            style.Setters.Add(new Setter(Control.TemplateProperty, tpl));
            return style;
        }

        private static DataTemplate CreateProfileItemTemplate(bool isDark)
        {
            var fgPrimary = BrushHex(isDark ? "#F9FAFB" : "#111827");
            var fgSecondary = BrushHex(isDark ? "#9CA3AF" : "#6B7280");
            var gold = BrushHex("#C5A059");

            var tpl = new DataTemplate(typeof(FtpConnectionProfile));
            var stack = new FrameworkElementFactory(typeof(StackPanel));

            var topRow = new FrameworkElementFactory(typeof(Grid));
            var col0 = new FrameworkElementFactory(typeof(ColumnDefinition));
            col0.SetValue(ColumnDefinition.WidthProperty, new GridLength(1, GridUnitType.Star));
            var col1 = new FrameworkElementFactory(typeof(ColumnDefinition));
            col1.SetValue(ColumnDefinition.WidthProperty, GridLength.Auto);
            topRow.AppendChild(col0);
            topRow.AppendChild(col1);

            var nameTxt = new FrameworkElementFactory(typeof(TextBlock));
            nameTxt.SetBinding(TextBlock.TextProperty, new System.Windows.Data.Binding(nameof(FtpConnectionProfile.Name)));
            nameTxt.SetValue(TextBlock.ForegroundProperty, fgPrimary);
            nameTxt.SetValue(TextBlock.FontWeightProperty, FontWeights.SemiBold);
            nameTxt.SetValue(TextBlock.FontSizeProperty, 13.0);
            nameTxt.SetValue(TextBlock.TextTrimmingProperty, TextTrimming.CharacterEllipsis);
            nameTxt.SetValue(Grid.ColumnProperty, 0);
            topRow.AppendChild(nameTxt);

            var protoTxt = new FrameworkElementFactory(typeof(TextBlock));
            protoTxt.SetBinding(TextBlock.TextProperty, new System.Windows.Data.Binding(nameof(FtpConnectionProfile.ProtocolTag)));
            protoTxt.SetValue(TextBlock.ForegroundProperty, gold);
            protoTxt.SetValue(TextBlock.FontWeightProperty, FontWeights.Bold);
            protoTxt.SetValue(TextBlock.FontSizeProperty, 10.5);
            protoTxt.SetValue(FrameworkElement.MarginProperty, new Thickness(8, 0, 0, 0));
            protoTxt.SetValue(FrameworkElement.VerticalAlignmentProperty, VerticalAlignment.Center);
            protoTxt.SetValue(Grid.ColumnProperty, 1);
            topRow.AppendChild(protoTxt);

            var subTxt = new FrameworkElementFactory(typeof(TextBlock));
            subTxt.SetBinding(TextBlock.TextProperty, new System.Windows.Data.Binding(nameof(FtpConnectionProfile.SubtitleLabel)));
            subTxt.SetValue(TextBlock.ForegroundProperty, fgSecondary);
            subTxt.SetValue(TextBlock.FontSizeProperty, 11.0);
            subTxt.SetValue(FrameworkElement.MarginProperty, new Thickness(0, 2, 0, 0));
            subTxt.SetValue(TextBlock.TextTrimmingProperty, TextTrimming.CharacterEllipsis);

            stack.AppendChild(topRow);
            stack.AppendChild(subTxt);
            tpl.VisualTree = stack;
            return tpl;
        }

        private static SolidColorBrush BrushHex(string hex)
        {
            var b = new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex));
            b.Freeze();
            return b;
        }
    }
}
