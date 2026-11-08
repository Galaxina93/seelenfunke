using System;
using System.IO;
using System.Linq;
using System.Text;
using System.Windows;

namespace FunkenwerkVideo
{
    public partial class App : Application
    {
        protected override async void OnStartup(StartupEventArgs e)
        {
            if (e.Args != null && e.Args.Any(a => string.Equals(a, "--selftest", StringComparison.OrdinalIgnoreCase)))
            {
                ShutdownMode = ShutdownMode.OnExplicitShutdown;
                string baseDir = AppDomain.CurrentDomain.BaseDirectory;
                string assetsMusicDir = Path.Combine(baseDir, "Assets", "MarketingMusic");
                var report = await VideoEngineSelfTestSuite.RunFullVerificationSuiteAsync(assetsMusicDir);

                var sb = new StringBuilder();
                sb.AppendLine("=== FUNKENWERK VIDEO · AUTOMATED SELF-TEST & BENCHMARK REPORT ===");
                sb.AppendLine($"Zeitpunkt: {DateTime.Now:yyyy-MM-dd HH:mm:ss}");
                sb.AppendLine($"Status:    {(report.AllPassed ? "100 % ERFOLGREICH" : "FEHLER AUFGETRETEN")}");
                sb.AppendLine($"Ergebnis:  {report.SummaryText}");
                sb.AppendLine("-----------------------------------------------------------------");
                foreach (var line in report.LogLines)
                {
                    sb.AppendLine(line);
                }

                string reportFile = Path.Combine(baseDir, "selftest_report.txt");
                await File.WriteAllTextAsync(reportFile, sb.ToString(), Encoding.UTF8);
                Shutdown(report.AllPassed ? 0 : 2);
                return;
            }

            base.OnStartup(e);
        }
    }
}
