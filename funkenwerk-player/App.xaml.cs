using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Windows;

namespace FunkenwerkPlayer
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            AppDomain.CurrentDomain.UnhandledException += (_, args) =>
            {
                try
                {
                    string logPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "funkenwerk_player_error.log");
                    File.AppendAllText(logPath, $"[{DateTime.Now:O}] {args.ExceptionObject}\n");
                }
                catch { }
            };

            DispatcherUnhandledException += (_, args) =>
            {
                try
                {
                    string logPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "funkenwerk_player_error.log");
                    File.AppendAllText(logPath, $"[{DateTime.Now:O}] {args.Exception}\n");
                }
                catch { }
                args.Handled = true;
            };

            // Automatically ensure Windows Media Player registration on every launch
            WindowsDefaultPlayerRegistrar.RegisterAsWindowsMediaPlayer(out _);

            if (e.Args != null && e.Args.Any(a => string.Equals(a, "--register-default", StringComparison.OrdinalIgnoreCase)))
            {
                bool ok = WindowsDefaultPlayerRegistrar.RegisterAsWindowsMediaPlayer(out string msg);
                Console.WriteLine(msg);
                Shutdown(ok ? 0 : 1);
                return;
            }

            if (e.Args != null && e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                bool regOk = WindowsDefaultPlayerRegistrar.RegisterAsWindowsMediaPlayer(out string regMsg);
                bool isReg = WindowsDefaultPlayerRegistrar.IsRegisteredInWindows();
                string reportPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "self_test_report.txt");
                File.WriteAllLines(reportPath, new[]
                {
                    $"[{(regOk ? "PASS" : "FAIL")}] RegisterAsWindowsMediaPlayer: {regMsg}",
                    $"[{(isReg ? "PASS" : "FAIL")}] IsRegisteredInWindows: {isReg}",
                    $"FUNKENWERK PLAYER SELF-TEST: {(regOk && isReg ? "2/2" : "0/2")} TESTS BESTANDEN."
                });
                Environment.Exit(regOk && isReg ? 0 : 1);
                return;
            }

            var initialFiles = new List<string>();
            if (e.Args != null)
            {
                foreach (var arg in e.Args)
                {
                    if (!string.IsNullOrWhiteSpace(arg) && !arg.StartsWith("--", StringComparison.Ordinal) &&
                        (File.Exists(arg) || Directory.Exists(arg)))
                    {
                        initialFiles.Add(arg);
                    }
                }
            }

            var mainWindow = new MainWindow(initialFiles);
            MainWindow = mainWindow;
            mainWindow.Show();
        }
    }
}
