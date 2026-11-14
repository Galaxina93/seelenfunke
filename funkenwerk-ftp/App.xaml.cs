using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Windows;

namespace FunkenwerkFtp
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                try { Console.OutputEncoding = Encoding.UTF8; } catch { }

                try
                {
                    bool allOk = FunkenwerkFtpSecurity.RunIsolatedSelfTestSuite(out List<string> results);
                    foreach (string line in results)
                    {
                        try { Console.WriteLine(line); } catch { }
                    }
                    Environment.Exit(allOk ? 0 : 1);
                    return;
                }
                catch (Exception ex)
                {
                    try { Console.Error.WriteLine($"[FATAL] {ex}"); } catch { }
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
