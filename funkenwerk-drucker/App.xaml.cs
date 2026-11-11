using System;
using System.IO;
using System.Linq;
using System.Windows;
using Microsoft.Win32;

namespace FunkenwerkDrucker
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            RegisterOpenWithAssociation();

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                try
                {
                    bool ok = Task.Run(() => PrinterSelfTestRunner.RunAllAsync()).GetAwaiter().GetResult();
                    Environment.Exit(ok ? 0 : 1);
                    return;
                }
                catch (Exception ex)
                {
                    Console.Error.WriteLine("SELF-TEST ERROR: " + ex.Message);
                    Environment.Exit(2);
                    return;
                }
            }

            bool startInScanMode = e.Args.Any(a =>
                string.Equals(a, "--scan", StringComparison.OrdinalIgnoreCase) ||
                string.Equals(a, "/scan", StringComparison.OrdinalIgnoreCase));

            string? initialFile = e.Args.FirstOrDefault(a => !a.StartsWith("-") && !a.StartsWith("/") && File.Exists(a));

            var win = new MainWindow(initialFile, startInScanMode);
            MainWindow = win;
            win.Show();
        }

        private static void RegisterOpenWithAssociation()
        {
            try
            {
                string exePath = Environment.ProcessPath ?? "";
                if (string.IsNullOrWhiteSpace(exePath) || !File.Exists(exePath)) return;

                string icoPath = Path.Combine(Path.GetDirectoryName(exePath) ?? "", "funkenwerk_drucker.ico");
                string iconSpec = File.Exists(icoPath) ? $"\"{icoPath}\",0" : $"\"{exePath}\",0";

                const string progId = "FunkenwerkDrucker.PrintJob";
                using (var cls = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{progId}"))
                {
                    cls?.SetValue("", "Funkenwerk Drucker");
                    cls?.SetValue("FriendlyTypeName", "Mit Funkenwerk Drucker drucken");
                    using var iconKey = cls?.CreateSubKey("DefaultIcon");
                    iconKey?.SetValue("", iconSpec);
                    using var cmdKey = cls?.CreateSubKey(@"shell\open\command");
                    cmdKey?.SetValue("", $"\"{exePath}\" \"%1\"");
                }

                foreach (string ext in new[] { ".pdf", ".png", ".jpg", ".jpeg" })
                {
                    using var extKey = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{ext}\OpenWithProgids");
                    extKey?.SetValue(progId, Array.Empty<byte>(), RegistryValueKind.Binary);
                }
            }
            catch { }
        }
    }
}
