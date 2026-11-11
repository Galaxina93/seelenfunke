using System;
using System.Linq;
using System.Windows;

namespace FunkenwerkSsh
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                try
                {
                    var profiles = FunkenwerkSshSecurity.LoadAndImportProfiles();
                    bool ok = profiles.Count > 0 && FunkenwerkSshSecurity.RunSelfTest();
                    Environment.Exit(ok ? 0 : 1);
                    return;
                }
                catch
                {
                    Environment.Exit(2);
                    return;
                }
            }

            var win = new MainWindow();
            MainWindow = win;
            win.Show();
        }
    }
}
