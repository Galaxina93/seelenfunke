using System;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Windows;

namespace FunkenwerkTresor
{
    public partial class App : Application
    {
        [DllImport("kernel32.dll")]
        private static extern bool AttachConsole(int dwProcessId);

        public static void LogBoot(string msg)
        {
            try
            {
                string logFile = Path.Combine(Path.GetTempPath(), "funkenwerk_tresor_boot.log");
                File.AppendAllText(logFile, $"[{DateTime.Now:HH:mm:ss.fff}] [PID {Environment.ProcessId}] {msg}{Environment.NewLine}");
            }
            catch { }
        }

        protected override void OnStartup(StartupEventArgs e)
        {
            DispatcherUnhandledException += (s, ex) =>
            {
                LogBoot("DISPATCHER_EXCEPTION: " + ex.Exception);
            };
            AppDomain.CurrentDomain.UnhandledException += (s, ex) =>
            {
                LogBoot("DOMAIN_EXCEPTION: " + ex.ExceptionObject);
            };

            base.OnStartup(e);

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                AttachConsole(-1);
                bool ok = TresorSelfTestRunner.RunEndToEndSelfTest();
                Environment.Exit(ok ? 0 : 1);
                return;
            }

            LogBoot("OnStartup args=" + string.Join(" ", e.Args));

            string? initialKdbxPath = e.Args.FirstOrDefault(a =>
                !a.StartsWith("-", StringComparison.Ordinal) &&
                File.Exists(a));

            try
            {
                var win = new MainWindow(initialKdbxPath);
                win.Loaded += (_, _) => LogBoot($"MainWindow.Loaded Title='{win.Title}' Vis={win.Visibility} State={win.WindowState} W={win.ActualWidth} H={win.ActualHeight}");
                win.ContentRendered += (_, _) => LogBoot("MainWindow.ContentRendered");
                win.Closing += (_, _) => LogBoot("MainWindow.Closing\n" + Environment.StackTrace);
                win.Closed += (_, _) => LogBoot("MainWindow.Closed");
                MainWindow = win;
                win.Show();
                win.Activate();
                LogBoot("win.Show() completed");
            }
            catch (Exception ex)
            {
                LogBoot("STARTUP_CATCH: " + ex);
            }
        }
    }
}
