using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Windows;

namespace FunkenwerkRemote
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            DispatcherUnhandledException += (_, args) =>
            {
                if (args.Exception is System.Runtime.InteropServices.SEHException ||
                    args.Exception is System.Runtime.InteropServices.COMException)
                {
                    // Fängt asynchrone Win32/ActiveX-Abbrüche von mstscax.dll sauber ab, ohne das Fenster zu beenden
                    args.Handled = true;
                    if (MainWindow is MainWindow mw)
                    {
                        mw.HandleNativeRdpFault(args.Exception.Message);
                    }
                }
            };

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                try { Console.OutputEncoding = Encoding.UTF8; } catch { }

                try
                {
                    bool ok = FunkenwerkRemoteSecurity.RunIsolatedSelfTestSuite(out List<string> results);
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

            string? autoConnectTarget = null;
            for (int i = 0; i < e.Args.Length - 1; i++)
            {
                if (string.Equals(e.Args[i], "--connect", StringComparison.OrdinalIgnoreCase))
                {
                    autoConnectTarget = e.Args[i + 1];
                    break;
                }
            }

            var win = new MainWindow(autoConnectTarget);
            MainWindow = win;
            win.Show();
        }
    }
}
