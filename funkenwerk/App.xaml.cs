using System;
using System.IO;
using System.Linq;
using System.Windows;

namespace Funkenwerk
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            string reportPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "KameraMedien", "verification_report.txt");
            Directory.CreateDirectory(Path.GetDirectoryName(reportPath)!);
            this.DispatcherUnhandledException += (s, exArgs) =>
            {
                try { File.WriteAllText(reportPath, "UNHANDLED EXCEPTION:\r\n" + exArgs.Exception.ToString()); } catch { }
                exArgs.Handled = true;
                Shutdown(1);
            };

            base.OnStartup(e);

            if (e.Args != null && e.Args.Contains("--verify-camera-studio", StringComparer.OrdinalIgnoreCase))
            {
                Dispatcher.InvokeAsync(async () =>
                {
                    try
                    {
                        if (MainWindow is MainWindow mw)
                        {
                            await mw.RunAutomatedCameraStudioVerificationAsync(reportPath);
                        }
                        else
                        {
                            File.WriteAllText(reportPath, "MainWindow was null");
                        }
                    }
                    catch (Exception ex)
                    {
                        File.WriteAllText(reportPath, "EXCEPTION: " + ex.ToString());
                    }
                    finally
                    {
                        Shutdown(0);
                    }
                });
            }
        }
    }
}
