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
                ShutdownMode = ShutdownMode.OnExplicitShutdown;
                string? customReportPath = e.Args
                    .FirstOrDefault(a => a.StartsWith("--report=", StringComparison.OrdinalIgnoreCase))
                    ?.Substring("--report=".Length)
                    .Trim('"');

                bool ok = BrowserSelfTestRunner.RunSelfTest(customReportPath);
                Environment.Exit(ok ? 0 : 1);
                return;
            }

            BrowserShieldAndSettings.EnsureDataDirLockedDown();

            string? initialUrl = e.Args.FirstOrDefault(a => !a.StartsWith("--", StringComparison.Ordinal));
            var win = new MainWindow(initialUrl);
            MainWindow = win;
            win.Show();
        }
    }
}
