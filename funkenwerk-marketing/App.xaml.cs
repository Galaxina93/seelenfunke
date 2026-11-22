using System;
using System.Linq;
using System.Windows;

namespace Funkenwerk
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);
            if (e.Args.Any(a => a.Equals("--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                bool ok = MarketingSelfTest.Run();
                Shutdown(ok ? 0 : 1);
                return;
            }

            var win = new MainWindow();
            MainWindow = win;
            win.Show();
        }
    }

    public static class MarketingSelfTest
    {
        public static bool Run()
        {
            var win = new MainWindow();
            int clampedLow = win.ClampDailyRecipeIndexForTest(-5);
            int clampedHigh = win.ClampDailyRecipeIndexForTest(999);
            bool urlOk = MainWindow.IsAllowedViralReferenceUrl("https://www.tiktok.com/@meinseelenfunke") &&
                         !MainWindow.IsAllowedViralReferenceUrl("javascript:alert(1)");
            win.ApplyStandaloneTheme(false);
            bool lightOk = !win.IsDarkMode;
            win.ApplyStandaloneTheme(true);
            bool darkOk = win.IsDarkMode;
            win.Close();

            bool allOk = clampedLow == 0 && clampedHigh >= 0 && urlOk && lightOk && darkOk;
            Console.WriteLine(allOk
                ? "PASS: FunkenwerkMarketing self-test (4/4 checks passed)."
                : "FAIL: FunkenwerkMarketing self-test failed.");
            return allOk;
        }
    }
}
