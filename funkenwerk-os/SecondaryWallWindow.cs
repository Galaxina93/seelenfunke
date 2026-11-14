using System;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;

namespace FunkenwerkOS
{
    public sealed class SecondaryWallWindow : Window
    {
        private readonly Border _rootBorder;
        private readonly Border _headerBorder;
        private readonly TextBlock _clockTxt;
        private readonly TextBlock _openOrdersValTxt;
        private readonly TextBlock _expressValTxt;
        private readonly TextBlock _revenueValTxt;
        private readonly TextBlock _tasksValTxt;
        private readonly Border _prioCard;
        private readonly TextBlock _prioOrderTitleTxt;
        private readonly TextBlock _prioOrderMetaTxt;
        private readonly ItemsControl _ordersList;
        private bool _darkMode = true;

        public SecondaryWallWindow(bool darkMode)
        {
            _darkMode = darkMode;
            Title = "Funkenwerk OS · HDMI-2 Leitstand";
            WindowStyle = WindowStyle.None;
            ResizeMode = ResizeMode.NoResize;
            Width = 1280;
            Height = 720;

            var rootGrid = new Grid();
            rootGrid.RowDefinitions.Add(new RowDefinition { Height = new GridLength(56) });
            rootGrid.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
            rootGrid.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });

            // Top Header
            _headerBorder = new Border
            {
                BorderThickness = new Thickness(0, 0, 0, 1),
                Padding = new Thickness(24, 0, 24, 0)
            };
            var headerGrid = new Grid();
            headerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            headerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            headerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var brandTxt = new TextBlock
            {
                Text = "✦  FUNKENWERK OS · LEITSTAND MONITOR 2",
                Foreground = new SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#C5A059")),
                FontSize = 15,
                FontWeight = FontWeights.Bold,
                VerticalAlignment = VerticalAlignment.Center
            };
            Grid.SetColumn(brandTxt, 0);
            headerGrid.Children.Add(brandTxt);

            _clockTxt = new TextBlock
            {
                Text = DateTime.Now.ToString("HH:mm:ss  ·  dd.MM.yyyy"),
                FontSize = 15,
                FontWeight = FontWeights.SemiBold,
                VerticalAlignment = VerticalAlignment.Center,
                Margin = new Thickness(0, 0, 18, 0)
            };
            Grid.SetColumn(_clockTxt, 1);
            headerGrid.Children.Add(_clockTxt);

            var closeBtn = new System.Windows.Controls.Button
            {
                Content = "✕",
                Width = 34,
                Height = 32,
                Background = System.Windows.Media.Brushes.Transparent,
                Foreground = new SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#C5A059")),
                BorderBrush = new SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#C5A059")),
                BorderThickness = new Thickness(1),
                FontWeight = FontWeights.Bold,
                Cursor = System.Windows.Input.Cursors.Hand
            };
            closeBtn.Click += (_, _) => Close();
            Grid.SetColumn(closeBtn, 2);
            headerGrid.Children.Add(closeBtn);

            _headerBorder.Child = headerGrid;
            Grid.SetRow(_headerBorder, 0);
            rootGrid.Children.Add(_headerBorder);

            // KPI Strip
            var kpiUniform = new System.Windows.Controls.Primitives.UniformGrid
            {
                Columns = 4,
                Margin = new Thickness(24, 20, 24, 14)
            };
            kpiUniform.Children.Add(BuildKpiTile("OFFENE AUFTRÄGE", out _openOrdersValTxt));
            kpiUniform.Children.Add(BuildKpiTile("EXPRESS", out _expressValTxt));
            kpiUniform.Children.Add(BuildKpiTile("UMSATZ MONAT", out _revenueValTxt));
            kpiUniform.Children.Add(BuildKpiTile("OFFENE AUFGABEN", out _tasksValTxt));
            Grid.SetRow(kpiUniform, 1);
            rootGrid.Children.Add(kpiUniform);

            // Priority + Orders Body
            var bodyGrid = new Grid { Margin = new Thickness(24, 4, 24, 24) };
            bodyGrid.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
            bodyGrid.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });

            _prioCard = new Border
            {
                CornerRadius = new CornerRadius(12),
                BorderThickness = new Thickness(1.5),
                BorderBrush = new SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#C5A059")),
                Padding = new Thickness(20, 16, 20, 16),
                Margin = new Thickness(0, 0, 0, 16)
            };
            var prioStack = new StackPanel();
            prioStack.Children.Add(new TextBlock
            {
                Text = "NÄCHSTER FOKUS-AUFTRAG",
                Foreground = new SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#C5A059")),
                FontSize = 11.5,
                FontWeight = FontWeights.Bold
            });
            _prioOrderTitleTxt = new TextBlock
            {
                Text = "Bereit",
                FontSize = 24,
                FontWeight = FontWeights.Bold,
                Margin = new Thickness(0, 4, 0, 2)
            };
            _prioOrderMetaTxt = new TextBlock
            {
                Text = "",
                FontSize = 14
            };
            prioStack.Children.Add(_prioOrderTitleTxt);
            prioStack.Children.Add(_prioOrderMetaTxt);
            _prioCard.Child = prioStack;
            Grid.SetRow(_prioCard, 0);
            bodyGrid.Children.Add(_prioCard);

            _ordersList = new ItemsControl();
            var scroll = new ScrollViewer
            {
                VerticalScrollBarVisibility = ScrollBarVisibility.Auto,
                Content = _ordersList
            };
            Grid.SetRow(scroll, 1);
            bodyGrid.Children.Add(scroll);

            Grid.SetRow(bodyGrid, 2);
            rootGrid.Children.Add(bodyGrid);

            _rootBorder = new Border { Child = rootGrid };
            Content = _rootBorder;

            PositionOnSecondaryOrPrimaryScreen();
            ApplyTheme(darkMode);
        }

        private Border BuildKpiTile(string label, out TextBlock valueTxt)
        {
            var card = new Border
            {
                CornerRadius = new CornerRadius(12),
                BorderThickness = new Thickness(1),
                Padding = new Thickness(18, 14, 18, 14),
                Margin = new Thickness(6, 0, 6, 0),
                Tag = "KpiCard"
            };
            var sp = new StackPanel();
            sp.Children.Add(new TextBlock
            {
                Text = label,
                Foreground = new SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#C5A059")),
                FontSize = 11.5,
                FontWeight = FontWeights.Bold
            });
            valueTxt = new TextBlock
            {
                Text = "0",
                FontSize = 28,
                FontWeight = FontWeights.Bold,
                Margin = new Thickness(0, 6, 0, 0)
            };
            sp.Children.Add(valueTxt);
            card.Child = sp;
            return card;
        }

        private void PositionOnSecondaryOrPrimaryScreen()
        {
            try
            {
                var screens = System.Windows.Forms.Screen.AllScreens;
                var target = screens.FirstOrDefault(s => !s.Primary) ?? screens.FirstOrDefault();
                if (target != null)
                {
                    WindowStartupLocation = WindowStartupLocation.Manual;
                    Left = target.Bounds.Left;
                    Top = target.Bounds.Top;
                    Width = target.Bounds.Width;
                    Height = target.Bounds.Height;
                    Loaded += (_, _) => WindowState = WindowState.Maximized;
                }
            }
            catch { }
        }

        public void ApplyTheme(bool darkMode)
        {
            _darkMode = darkMode;
            var bgMain = Solid(darkMode ? "#0D1017" : "#F4F5F7");
            var bgCard = Solid(darkMode ? "#141824" : "#FFFFFF");
            var border = Solid(darkMode ? "#252B3B" : "#E5E7EB");
            var fgMain = Solid(darkMode ? "#F9FAFB" : "#111827");
            var fgMuted = Solid(darkMode ? "#9CA3AF" : "#4B5563");

            _rootBorder.Background = bgMain;
            _headerBorder.Background = Solid(darkMode ? "#111520" : "#FFFFFF");
            _headerBorder.BorderBrush = border;
            _clockTxt.Foreground = fgMain;
            _openOrdersValTxt.Foreground = fgMain;
            _expressValTxt.Foreground = Solid("#C5A059");
            _revenueValTxt.Foreground = fgMain;
            _tasksValTxt.Foreground = fgMain;
            _prioCard.Background = bgCard;
            _prioOrderTitleTxt.Foreground = fgMain;
            _prioOrderMetaTxt.Foreground = fgMuted;

            if (_rootBorder.Child is Grid g && g.Children.Count > 1 && g.Children[1] is System.Windows.Controls.Primitives.UniformGrid ug)
            {
                foreach (var child in ug.Children.OfType<Border>())
                {
                    child.Background = bgCard;
                    child.BorderBrush = border;
                }
            }
        }

        public string DisplayedRevenue => _revenueValTxt.Text;
        public string DisplayedPriorityTitle => _prioOrderTitleTxt.Text;
        public string DisplayedPriorityMeta => _prioOrderMetaTxt.Text;

        public void UpdateSnapshot(OsDashboardSnapshot snap, bool visitorPrivacyMode = false) =>
            UpdateFromSnapshot(snap, visitorPrivacyMode);

        public void UpdateFromSnapshot(OsDashboardSnapshot snap, bool visitorPrivacyMode = false)
        {
            if (snap == null) return;

            _clockTxt.Text = DateTime.Now.ToString("HH:mm:ss  ·  dd.MM.yyyy");
            _openOrdersValTxt.Text = snap.OpenOrdersCount.ToString();
            _expressValTxt.Text = snap.ExpressOrdersCount.ToString();
            _revenueValTxt.Text = visitorPrivacyMode ? "•••" : snap.ShopRevenueFormatted;
            _tasksValTxt.Text = snap.OpenTasksCount.ToString();

            if (snap.PriorityOrder.HasPriorityOrder)
            {
                string exp = snap.PriorityOrder.IsExpress ? " · EXPRESS" : "";
                string prioTotal = visitorPrivacyMode ? "•••" : snap.PriorityOrder.TotalFormatted;
                string safeCustomer = visitorPrivacyMode
                    ? "Gesperrt"
                    : GewerbeMonitorWindow.AnonymizeCustomerNameGdpr(snap.PriorityOrder.CustomerName);
                if (string.IsNullOrWhiteSpace(safeCustomer))
                    safeCustomer = visitorPrivacyMode ? "Gesperrt" : "Kunde";

                _prioOrderTitleTxt.Text = $"{snap.PriorityOrder.OrderNumber}  ·  {prioTotal}{exp}";
                _prioOrderMetaTxt.Text = $"{snap.PriorityOrder.ItemCount} Pos.  ·  {safeCustomer}";
            }
            else
            {
                _prioOrderTitleTxt.Text = "Alle Aufträge abgearbeitet";
                _prioOrderMetaTxt.Text = snap.StatusSummary;
            }

            _ordersList.Items.Clear();
            var fgMain = Solid(_darkMode ? "#F9FAFB" : "#111827");
            var bgRow = Solid(_darkMode ? "#141824" : "#FFFFFF");
            var border = Solid(_darkMode ? "#252B3B" : "#E5E7EB");

            foreach (var ord in snap.ActiveOrders.Take(10))
            {
                var row = new Border
                {
                    Background = bgRow,
                    BorderBrush = border,
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(9),
                    Padding = new Thickness(16, 11, 16, 11),
                    Margin = new Thickness(0, 0, 0, 7)
                };
                var rg = new Grid();
                rg.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(170) });
                rg.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                rg.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(110) });
                rg.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(130) });

                string rowTotal = visitorPrivacyMode ? "•••" : ord.TotalFormatted;

                var numTxt = new TextBlock { Text = ord.OrderNumber, Foreground = fgMain, FontWeight = FontWeights.Bold, FontSize = 15 };
                var stTxt = new TextBlock { Text = ord.StatusLabel, Foreground = Solid(ord.StatusBadgeHex), FontWeight = FontWeights.Bold, FontSize = 14 };
                var posTxt = new TextBlock { Text = ord.ItemCountLabel, Foreground = fgMain, FontSize = 14 };
                var totTxt = new TextBlock { Text = rowTotal, Foreground = Solid("#C5A059"), FontWeight = FontWeights.Bold, FontSize = 15, HorizontalAlignment = System.Windows.HorizontalAlignment.Right };

                Grid.SetColumn(numTxt, 0);
                Grid.SetColumn(stTxt, 1);
                Grid.SetColumn(posTxt, 2);
                Grid.SetColumn(totTxt, 3);

                rg.Children.Add(numTxt);
                rg.Children.Add(stTxt);
                rg.Children.Add(posTxt);
                rg.Children.Add(totTxt);
                row.Child = rg;
                _ordersList.Items.Add(row);
            }
        }

        public void TickClock()
        {
            _clockTxt.Text = DateTime.Now.ToString("HH:mm:ss  ·  dd.MM.yyyy");
        }

        private static SolidColorBrush Solid(string hex) =>
            new((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString(hex));
    }
}
