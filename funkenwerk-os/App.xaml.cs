using System;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Windows;

namespace FunkenwerkOS
{
    public partial class App : System.Windows.Application
    {
        private static System.Threading.Mutex? _singleInstanceMutex;

        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                bool ok = OsSelfTestRunner.RunEndToEndSelfTest();
                Environment.Exit(ok ? 0 : 1);
                return;
            }

            if (e.Args.Any(a => string.Equals(a, "--wake-gewerbe", StringComparison.OrdinalIgnoreCase)))
            {
                OsHardwareAndAudioEngine.WakeMonitors();
                OsHardwareAndAudioEngine.ArmHardwareRtcWakeTimerFor0800();
                try
                {
                    using var cur = Process.GetCurrentProcess();
                    bool otherRunning = Process.GetProcessesByName(cur.ProcessName).Any(p => p.Id != cur.Id);
                    if (!otherRunning)
                    {
                        string exe = Path.Combine(AppContext.BaseDirectory, "FunkenwerkOS.exe");
                        if (File.Exists(exe))
                        {
                            Process.Start(new ProcessStartInfo(exe, "--shell --workspace gewerbe")
                            {
                                WorkingDirectory = AppContext.BaseDirectory,
                                UseShellExecute = false
                            });
                        }
                    }
                }
                catch { }
                Environment.Exit(0);
                return;
            }

            if (e.Args.Any(a => string.Equals(a, "--night-sleep", StringComparison.OrdinalIgnoreCase)))
            {
                OsHardwareAndAudioEngine.TriggerNightSleepUntilMorning0800();
                Environment.Exit(0);
                return;
            }

            if (e.Args.Any(a => string.Equals(a, "--generate-usb-scripts", StringComparison.OrdinalIgnoreCase)))
            {
                string outDir = FortressProvisioningEngine.DefaultSetupScriptsDirectory;
                FortressProvisioningEngine.WriteMiniPcDeploymentScripts(outDir);
                Shutdown(0);
                return;
            }

            bool isShellMode = e.Args.Any(a => string.Equals(a, "--shell", StringComparison.OrdinalIgnoreCase));
            OsHardwareAndAudioEngine.EnsureWindowsExplorerTaskbarRunning();

            _singleInstanceMutex = new System.Threading.Mutex(true, @"Local\FunkenwerkOS_SingleInstance_Mutex", out bool isFirstInstance);
            if (!isFirstInstance)
            {
                if (isShellMode)
                {
                    Environment.Exit(0);
                    return;
                }

                try
                {
                    using var current = Process.GetCurrentProcess();
                    foreach (var proc in Process.GetProcessesByName(current.ProcessName))
                    {
                        using (proc)
                        {
                            if (proc.Id != current.Id)
                            {
                                try { proc.Kill(true); } catch { }
                            }
                        }
                    }
                }
                catch
                {
                    // Ignore if process enumeration fails
                }
            }

            string? initialWorkspace = null;
            for (int i = 0; i < e.Args.Length; i++)
            {
                if (string.Equals(e.Args[i], "--workspace", StringComparison.OrdinalIgnoreCase) && i + 1 < e.Args.Length)
                {
                    initialWorkspace = e.Args[i + 1];
                    break;
                }
            }

            var win = new MainWindow(isShellMode, initialWorkspace);
            MainWindow = win;
            win.Show();
        }
    }
}
