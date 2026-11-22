using System;
using System.IO;
using System.Linq;
using System.Windows;
using Funkenwerk;

namespace FunkenwerkSmartHome
{
    public partial class App : Application
    {
        protected override async void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);
            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                ShutdownMode = ShutdownMode.OnExplicitShutdown;
                string tmpDir = Path.Combine(Path.GetTempPath(), "FunkenwerkSmartHome_SelfTest_" + Guid.NewGuid().ToString("N"));
                bool ok = false;
                try
                {
                    using (FunkenwerkSecurityVault.UseTemporaryAppDataDirectory(tmpDir))
                    {
                        ok = await SmartHomeSelfTest.RunAsync();
                    }
                }
                finally
                {
                    try { if (Directory.Exists(tmpDir)) Directory.Delete(tmpDir, recursive: true); } catch { }
                }
                Environment.Exit(ok ? 0 : 1);
                return;
            }

            var win = new MainWindow(e.Args);
            MainWindow = win;
            win.Show();
        }
    }
}
