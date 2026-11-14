using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
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
                try { Console.OutputEncoding = Encoding.UTF8; } catch { }

                try
                {
                    bool ok = FunkenwerkSshSecurity.RunIsolatedSelfTestSuite(out List<string> results);
                    foreach (string line in results)
                    {
                        try { Console.WriteLine(line); } catch { }
                    }
                    Environment.Exit(ok ? 0 : 1);
                    return;
                }
                catch (Exception ex)
                {
                    try { Console.Error.WriteLine($"[FATAL] {ex}"); } catch { }
                    Environment.Exit(2);
                    return;
                }
            }

            string? handoffNonce = null;
            for (int i = 0; i < e.Args.Length - 1; i++)
            {
                if (string.Equals(e.Args[i], "--handoff", StringComparison.OrdinalIgnoreCase))
                {
                    handoffNonce = e.Args[i + 1];
                    break;
                }
            }

            var win = new MainWindow(handoffNonce);
            MainWindow = win;
            win.Show();
        }
    }
}
