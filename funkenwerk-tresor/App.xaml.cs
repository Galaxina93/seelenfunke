using System;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Windows;

namespace FunkenwerkTresor
{
    public partial class App : Application
    {
        private static volatile bool s_isShuttingDown;

        [DllImport("kernel32.dll")]
        private static extern bool AttachConsole(int dwProcessId);

        public static bool IsBenignShutdownException(object? exObj)
        {
            if (exObj == null) return false;
            string text = exObj.ToString() ?? "";
            bool isNativeUnloadNoise = text.Contains("__std_type_info_destroy_list", StringComparison.Ordinal) ||
                                       text.Contains("__scrt_uninitialize_type_info", StringComparison.Ordinal) ||
                                       text.Contains("SingletonDomainUnload", StringComparison.Ordinal) ||
                                       text.Contains("_app_exit_callback", StringComparison.Ordinal);
            if (isNativeUnloadNoise)
                return true;

            if ((s_isShuttingDown || Environment.HasShutdownStarted) &&
                (exObj is OperationCanceledException || text.Contains("TaskCanceledException", StringComparison.Ordinal)))
            {
                return true;
            }

            return false;
        }

        public static void LogBoot(string msg)
        {
            try
            {
                string logFile = Path.Combine(TresorAppSettings.DataDir, "funkenwerk_tresor_boot.log");
                bool created = !File.Exists(logFile);
                File.AppendAllText(logFile, $"[{DateTime.Now:HH:mm:ss.fff}] [PID {Environment.ProcessId}] {msg}{Environment.NewLine}");
                if (created)
                    TresorAppSettings.LockDownFileAcl(logFile);
            }
            catch { }
        }

        protected override void OnExit(ExitEventArgs e)
        {
            s_isShuttingDown = true;
            base.OnExit(e);
        }

        protected override void OnStartup(StartupEventArgs e)
        {
            DispatcherUnhandledException += (s, ex) =>
            {
                if (!IsBenignShutdownException(ex.Exception))
                {
                    LogBoot("DISPATCHER_EXCEPTION: " + ex.Exception);
                }
            };
            AppDomain.CurrentDomain.UnhandledException += (s, ex) =>
            {
                if (!IsBenignShutdownException(ex.ExceptionObject))
                {
                    LogBoot("DOMAIN_EXCEPTION: " + ex.ExceptionObject);
                }
            };

            base.OnStartup(e);

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                AttachConsole(-1);
                bool ok = TresorSelfTestRunner.RunEndToEndSelfTest();
                Environment.Exit(ok ? 0 : 1);
                return;
            }

            string? initialKdbxPath = e.Args.FirstOrDefault(a =>
                !a.StartsWith("-", StringComparison.Ordinal) &&
                File.Exists(a));

            _ = System.Threading.Tasks.Task.Run(Funkenwerk.CollectionFileAssociationHealer.EnsureCollectionAssociationsRegistered);

            try
            {
                var win = new MainWindow(initialKdbxPath);
                win.Closing += (_, _) => { s_isShuttingDown = true; };
                win.Closed += (_, _) => { s_isShuttingDown = true; };
                MainWindow = win;
                win.Show();
                win.Activate();
            }
            catch (Exception ex)
            {
                LogBoot("STARTUP_CATCH: " + ex);
            }
        }
    }
}
