using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Windows;

namespace FunkenwerkPlayer
{
    public partial class App : Application
    {
        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool AttachConsole(int dwProcessId);

        private const int ATTACH_PARENT_PROCESS = -1;

        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            AppDomain.CurrentDomain.UnhandledException += (_, args) =>
            {
                try
                {
                    string logPath = Path.Combine(WindowsDefaultPlayerRegistrar.GetAppDataDirectory(), "funkenwerk_player_error.log");
                    File.AppendAllText(logPath, $"[{DateTime.Now:O}] {args.ExceptionObject}\n");
                }
                catch { }
            };

            DispatcherUnhandledException += (_, args) =>
            {
                try
                {
                    string logPath = Path.Combine(WindowsDefaultPlayerRegistrar.GetAppDataDirectory(), "funkenwerk_player_error.log");
                    File.AppendAllText(logPath, $"[{DateTime.Now:O}] {args.Exception}\n");
                }
                catch { }
                args.Handled = true;
            };

            if (e.Args != null && e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                try
                {
                    if (!Console.IsOutputRedirected)
                        AttachConsole(ATTACH_PARENT_PROCESS);
                }
                catch { }

                int exitCode = PlayerSelfTestRunner.RunAllTests(out var reportLines);
                try
                {
                    string reportPath = Path.Combine(WindowsDefaultPlayerRegistrar.GetAppDataDirectory(), "self_test_report.txt");
                    File.WriteAllLines(reportPath, reportLines);
                }
                catch { }

                try
                {
                    foreach (var line in reportLines)
                        Console.WriteLine(line);
                    Console.Out.Flush();
                }
                catch { }

                Environment.Exit(exitCode);
                return;
            }

            if (e.Args != null && e.Args.Any(a => string.Equals(a, "--register-default", StringComparison.OrdinalIgnoreCase)))
            {
                try
                {
                    if (!Console.IsOutputRedirected)
                        AttachConsole(ATTACH_PARENT_PROCESS);
                }
                catch { }

                bool ok = WindowsDefaultPlayerRegistrar.RegisterAsWindowsMediaPlayer(out string msg, setDefaultVerb: true);
                try { Console.WriteLine(msg); } catch { }
                Shutdown(ok ? 0 : 1);
                return;
            }

            // Normal startup: only register OpenWithProgids and Capabilities (do NOT override default verb automatically)
            WindowsDefaultPlayerRegistrar.RegisterAsWindowsMediaPlayer(out _, setDefaultVerb: false);

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
