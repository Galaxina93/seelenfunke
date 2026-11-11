using System;
using System.IO;
using System.Linq;
using System.Threading.Tasks;
using System.Windows;

namespace FunkenwerkVideo
{
    public partial class App : Application
    {
        protected override async void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            AppDomain.CurrentDomain.UnhandledException += (_, args) =>
            {
                try
                {
                    string logPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "funkenwerk_video_error.log");
                    File.AppendAllText(logPath, $"[{DateTime.Now:O}] {args.ExceptionObject}\n");
                }
                catch { }
            };

            DispatcherUnhandledException += (_, args) =>
            {
                try
                {
                    string logPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "funkenwerk_video_error.log");
                    File.AppendAllText(logPath, $"[{DateTime.Now:O}] {args.Exception}\n");
                }
                catch { }
                args.Handled = true;
            };

            if (e.Args != null && e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                int exitCode = await ViralVideographerSelfTest.RunAllAsync();
                Environment.Exit(exitCode);
                return;
            }

            var mainWindow = new MainWindow();
            MainWindow = mainWindow;
            mainWindow.Show();
        }
    }
}
