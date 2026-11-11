using System;
using System.Linq;
using System.Windows;

namespace FunkenwerkBrowser
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                bool ok = BrowserSelfTestRunner.RunSelfTest();
                Environment.Exit(ok ? 0 : 1);
                return;
            }

            string? initialUrl = e.Args.FirstOrDefault(a => !a.StartsWith("--", StringComparison.Ordinal));
            var win = new MainWindow(initialUrl);
            MainWindow = win;
            win.Show();
        }
    }
}
