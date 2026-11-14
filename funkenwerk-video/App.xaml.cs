using System;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Windows;

namespace FunkenwerkVideo
{
    public partial class App : Application
    {
        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool AttachConsole(int dwProcessId);

        private const int ATTACH_PARENT_PROCESS = -1;

        protected override async void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            AppDomain.CurrentDomain.UnhandledException += (_, args) =>
            {
                try
                {
                    string logPath = Path.Combine(ViralVideographerEngine.GetAppDataDir(), "funkenwerk_video_error.log");
                    File.AppendAllText(logPath, $"[{DateTime.Now:O}] {args.ExceptionObject}\n");
                }
                catch { }
            };

            DispatcherUnhandledException += (_, args) =>
            {
                try
                {
                    string logPath = Path.Combine(ViralVideographerEngine.GetAppDataDir(), "funkenwerk_video_error.log");
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

                int exitCode = await ViralVideographerSelfTest.RunAllAsync();
                try { Console.Out.Flush(); } catch { }
                Environment.Exit(exitCode);
                return;
            }

            var mainWindow = new MainWindow();
            MainWindow = mainWindow;
            mainWindow.Show();
        }
    }
}
