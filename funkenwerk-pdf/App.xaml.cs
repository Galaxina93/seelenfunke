using System;
using System.IO;
using System.Linq;
using System.Windows;
using Microsoft.Win32;

namespace FunkenwerkPdf
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            RegisterWindowsPdfAssociation();

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                try
                {
                    bool ok = PdfSelfTestRunner.RunEndToEndSelfTest();
                    Environment.Exit(ok ? 0 : 1);
                    return;
                }
                catch
                {
                    Environment.Exit(2);
                    return;
                }
            }

            string? initialPdf = e.Args.FirstOrDefault(a => !a.StartsWith("-") && File.Exists(a));
            var win = new MainWindow(initialPdf);
            MainWindow = win;
            win.Show();
        }

        public static void RegisterWindowsPdfAssociation()
        {
            try
            {
                string exePath = Environment.ProcessPath ?? "";
                if (string.IsNullOrWhiteSpace(exePath) || !File.Exists(exePath)) return;

                string icoPath = Path.Combine(Path.GetDirectoryName(exePath) ?? "", "funkenwerk_pdf.ico");
                string iconSpec = File.Exists(icoPath) ? $"\"{icoPath}\",0" : $"\"{exePath}\",0";

                const string progId = "FunkenwerkPdf.Document";
                using (var cls = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{progId}"))
                {
                    cls?.SetValue("", "Funkenwerk PDF Dokument");
                    cls?.SetValue("FriendlyTypeName", "Funkenwerk PDF Dokument");
                    using var iconKey = cls?.CreateSubKey("DefaultIcon");
                    iconKey?.SetValue("", iconSpec);
                    using var cmdKey = cls?.CreateSubKey(@"shell\open\command");
                    cmdKey?.SetValue("", $"\"{exePath}\" \"%1\"");
                }

                using (var extKey = Registry.CurrentUser.CreateSubKey(@"Software\Classes\.pdf\OpenWithProgids"))
                {
                    extKey?.SetValue(progId, Array.Empty<byte>(), RegistryValueKind.Binary);
                }

                using (var appCapKey = Registry.CurrentUser.CreateSubKey(@"Software\Funkenwerk\FunkenwerkPdf\Capabilities"))
                {
                    appCapKey?.SetValue("ApplicationName", "Funkenwerk PDF");
                    appCapKey?.SetValue("ApplicationDescription", "Funkenwerk PDF – Öffnen, Ausfüllen, Unterschreiben & Bearbeiten");
                    using var fa = appCapKey?.CreateSubKey("FileAssociations");
                    fa?.SetValue(".pdf", progId);
                }

                using (var regApps = Registry.CurrentUser.CreateSubKey(@"Software\RegisteredApplications"))
                {
                    regApps?.SetValue("Funkenwerk PDF", @"Software\Funkenwerk\FunkenwerkPdf\Capabilities");
                }
            }
            catch { }
        }
    }
}
