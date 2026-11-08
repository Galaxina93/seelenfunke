using System;
using System.IO;
using System.Linq;
using System.Text.RegularExpressions;
using System.Windows;

namespace Funkenwerk
{
    public partial class App : Application
    {
        private static string RedactSensitiveCrashDetails(string raw)
        {
            if (string.IsNullOrEmpty(raw)) return "";
            string s = Regex.Replace(raw, @"(Bearer\s+)[A-Za-z0-9\-._~+/=|]+", "$1[REDACTED]", RegexOptions.IgnoreCase);
            s = Regex.Replace(s, @"FWVAULT2:[A-Za-z0-9+/=]+", "FWVAULT2:[REDACTED]", RegexOptions.IgnoreCase);
            s = Regex.Replace(s, @"(""password""\s*:\s*"")[^""]*("")", "$1[REDACTED]$2", RegexOptions.IgnoreCase);
            return s;
        }

        protected override void OnStartup(StartupEventArgs e)
        {
            string reportPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "KameraMedien", "verification_report.txt");
            Directory.CreateDirectory(Path.GetDirectoryName(reportPath)!);
            this.DispatcherUnhandledException += (s, exArgs) =>
            {
                try { File.WriteAllText(reportPath, "UNHANDLED EXCEPTION:\r\n" + RedactSensitiveCrashDetails(exArgs.Exception.ToString())); } catch { }
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
                        File.WriteAllText(reportPath, "EXCEPTION: " + RedactSensitiveCrashDetails(ex.ToString()));
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
