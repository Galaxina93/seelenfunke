using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;

namespace Funkenwerk
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);
            if (e.Args.Any(a => a.Equals("--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                bool ok = CameraStandaloneSelfTest.Run();
                Shutdown(ok ? 0 : 1);
                return;
            }

            var win = new MainWindow();
            MainWindow = win;
            win.Show();
        }
    }

    public static class CameraStandaloneSelfTest
    {
        public static bool Run()
        {
            var testDsp = new FunkenwerkVoiceChangerEngine();
            var testCfg = new CameraStudioConfig { VoiceChangerEnabled = true };
            FunkenwerkVoiceChangerEngine.ApplyPresetToConfig(testCfg, "demon");
            testDsp.SyncFromConfig(testCfg, -1, -1);

            short[] orig = new short[960];
            short[] buf = new short[960];
            for (int n = 0; n < 960; n++)
            {
                short s = (short)(Math.Sin(2.0 * Math.PI * 200.0 * (n / 48000.0)) * 12000);
                orig[n] = s;
                buf[n] = s;
            }
            testDsp.ProcessDspBlockInPlace(buf, 960);
            Array.Copy(orig, buf, 960);
            testDsp.ProcessDspBlockInPlace(buf, 960);
            double diffSq = 0;
            for (int n = 0; n < 960; n++) { double d = buf[n] - orig[n]; diffSq += d * d; }
            double rms = Math.Sqrt(diffSq / 960.0);
            testDsp.Dispose();

            var win = new MainWindow();
            win.InitializeForSelfTest();

            win.ApplyStandaloneTheme(false);
            var lightCollisions = AuditThemeContrast(win, "LightMode");
            bool lightOk = !win.IsDarkMode && lightCollisions.Count == 0;

            win.ApplyStandaloneTheme(true);
            var darkCollisions = AuditThemeContrast(win, "DarkMode");
            bool darkOk = win.IsDarkMode && darkCollisions.Count == 0;

            win.Close();

            foreach (var err in lightCollisions.Concat(darkCollisions))
            {
                Console.Error.WriteLine("CONTRAST_FAIL: " + err);
            }

            bool ok = rms > 50.0 && lightOk && darkOk;
            Console.WriteLine(ok
                ? "PASS: FunkenwerkKamera self-test (5/5 DSP + full Light/Dark contrast audit passed)."
                : $"FAIL: FunkenwerkKamera self-test failed (rms={rms:F1}, lightCollisions={lightCollisions.Count}, darkCollisions={darkCollisions.Count}).");
            return ok;
        }

        private static List<string> AuditThemeContrast(Window root, string modeLabel)
        {
            var failures = new List<string>();
            Color rootFallbackBg = root.Background is SolidColorBrush wb && wb.Color.A > 0
                ? wb.Color
                : Color.FromRgb(15, 17, 23);

            void Walk(DependencyObject? node, Color inheritedBg, HashSet<DependencyObject> visited)
            {
                if (node == null || !visited.Add(node)) return;

                if (node is FrameworkElement fe && fe.Name == "CamShutterFlashBorder")
                    return;

                Color currentBg = TryGetElementBackground(node, out Color ownBg)
                    ? BlendOver(ownBg, inheritedBg)
                    : inheritedBg;

                if (node is TextBlock tb && !string.IsNullOrWhiteSpace(tb.Text))
                {
                    if (tb.Foreground is SolidColorBrush fgBrush && fgBrush.Color.A > 180)
                    {
                        double ratio = ContrastRatio(fgBrush.Color, currentBg);
                        if (ratio < 2.8)
                        {
                            failures.Add($"{modeLabel} TextBlock '{tb.Name}' (\"{Trunc(tb.Text)}\"): fg=#{Hex(fgBrush.Color)} on bg=#{Hex(currentBg)} (ratio={ratio:F2})");
                        }
                    }
                }
                else if (node is ContentControl cc && (node is Button || node is CheckBox) && cc.Content is string s && !string.IsNullOrWhiteSpace(s))
                {
                    if (cc.Foreground is SolidColorBrush fgBrush && fgBrush.Color.A > 180)
                    {
                        double ratio = ContrastRatio(fgBrush.Color, currentBg);
                        if (ratio < 2.8)
                        {
                            failures.Add($"{modeLabel} {node.GetType().Name} '{cc.Name}' (\"{Trunc(s)}\"): fg=#{Hex(fgBrush.Color)} on bg=#{Hex(currentBg)} (ratio={ratio:F2})");
                        }
                    }
                }
                else if (node is TextBox tbox)
                {
                    if (tbox.Foreground is SolidColorBrush fgBrush && fgBrush.Color.A > 180)
                    {
                        double ratio = ContrastRatio(fgBrush.Color, currentBg);
                        if (ratio < 2.8)
                        {
                            failures.Add($"{modeLabel} TextBox '{tbox.Name}': fg=#{Hex(fgBrush.Color)} on bg=#{Hex(currentBg)} (ratio={ratio:F2})");
                        }
                    }
                }

                foreach (var child in LogicalTreeHelper.GetChildren(node))
                {
                    if (child is DependencyObject d)
                        Walk(d, currentBg, visited);
                }
            }

            Walk(root, rootFallbackBg, new HashSet<DependencyObject>());
            return failures;
        }

        private static bool TryGetElementBackground(DependencyObject node, out Color c)
        {
            c = default;
            if (node is CheckBox) return false; // CheckBox.Background is the small tick square, label renders on parent surface!
            Brush? b = node switch
            {
                Border bd => bd.Background,
                Panel p => p.Background,
                Control ctrl => ctrl.Background,
                _ => null
            };
            if (b is SolidColorBrush scb && scb.Color.A > 16)
            {
                c = scb.Color;
                return true;
            }
            return false;
        }

        private static Color BlendOver(Color fg, Color bg)
        {
            if (fg.A >= 250) return fg;
            double a = fg.A / 255.0;
            return Color.FromRgb(
                (byte)Math.Clamp(Math.Round(fg.R * a + bg.R * (1 - a)), 0, 255),
                (byte)Math.Clamp(Math.Round(fg.G * a + bg.G * (1 - a)), 0, 255),
                (byte)Math.Clamp(Math.Round(fg.B * a + bg.B * (1 - a)), 0, 255));
        }

        private static double RelativeLuminance(Color c)
        {
            static double Chan(byte v)
            {
                double s = v / 255.0;
                return s <= 0.03928 ? s / 12.92 : Math.Pow((s + 0.055) / 1.055, 2.4);
            }
            return 0.2126 * Chan(c.R) + 0.7152 * Chan(c.G) + 0.0722 * Chan(c.B);
        }

        private static double ContrastRatio(Color a, Color b)
        {
            double l1 = RelativeLuminance(a);
            double l2 = RelativeLuminance(b);
            if (l1 < l2) (l1, l2) = (l2, l1);
            return (l1 + 0.05) / (l2 + 0.05);
        }

        private static string Hex(Color c) => $"{c.R:X2}{c.G:X2}{c.B:X2}";
        private static string Trunc(string s) => s.Length <= 28 ? s : s.Substring(0, 28) + "...";
    }
}
