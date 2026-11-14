using System;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Threading;
using Microsoft.Win32;

namespace FunkenwerkDrucker
{
    public partial class App : Application
    {
        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool TerminateProcess(IntPtr hProcess, uint uExitCode);

        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                System.Windows.Media.RenderOptions.ProcessRenderMode = System.Windows.Interop.RenderMode.SoftwareOnly;
                ShutdownMode = ShutdownMode.OnExplicitShutdown;
                uint code = 1;
                try
                {
                    bool ok = AwaitOnSta(PrinterSelfTestRunner.RunAllAsync());
                    code = ok ? 0u : 1u;
                }
                catch (Exception ex)
                {
                    Console.Error.WriteLine("SELF-TEST ERROR: " + ex);
                    code = 2u;
                }
                Console.Out.Flush();
                Console.Error.Flush();
                TerminateProcess(Process.GetCurrentProcess().Handle, code);
                Shutdown((int)code);
                return;
            }

            RegisterOpenWithAssociation();

            bool startInScanMode = e.Args.Any(a =>
                string.Equals(a, "--scan", StringComparison.OrdinalIgnoreCase) ||
                string.Equals(a, "/scan", StringComparison.OrdinalIgnoreCase));

            string? initialFile = e.Args.FirstOrDefault(a => !a.StartsWith("-") && !a.StartsWith("/") && File.Exists(a));

            var win = new MainWindow(initialFile, startInScanMode);
            MainWindow = win;
            win.Show();
        }

        internal static T AwaitOnSta<T>(Task<T> task)
        {
            if (task.IsCompleted)
            {
                return task.GetAwaiter().GetResult();
            }

            var frame = new DispatcherFrame();
            task.ContinueWith(_ => frame.Continue = false, TaskScheduler.Default);
            Dispatcher.PushFrame(frame);
            return task.GetAwaiter().GetResult();
        }

        public static void RegisterOpenWithAssociation()
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
