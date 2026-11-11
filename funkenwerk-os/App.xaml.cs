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

            for (int i = 0; i < e.Args.Length; i++)
            {
                if (string.Equals(e.Args[i], "--export-tokens", StringComparison.OrdinalIgnoreCase) && i + 1 < e.Args.Length)
                {
                    string outFile = e.Args[i + 1];
                    try
                    {
                        var s = OsVaultAndStorage.LoadSettings();
                        var dict = new System.Collections.Generic.Dictionary<string, string>
                        {
                            ["local"] = OsVaultAndStorage.ResolveBearerToken(s, "local"),
                            ["stage"] = OsVaultAndStorage.ResolveBearerToken(s, "stage"),
                            ["live"] = OsVaultAndStorage.ResolveBearerToken(s, "live"),
                            ["active_env"] = s.ApiEnvironment ?? "live"
                        };
                        File.WriteAllText(outFile, System.Text.Json.JsonSerializer.Serialize(dict), new System.Text.UTF8Encoding(false));
                    }
                    catch { }
                    Environment.Exit(0);
                    return;
                }

                if (string.Equals(e.Args[i], "--import-tokens", StringComparison.OrdinalIgnoreCase) && i + 1 < e.Args.Length)
                {
                    string inFile = e.Args[i + 1];
                    try
                    {
                        if (File.Exists(inFile))
                        {
                            var dict = System.Text.Json.JsonSerializer.Deserialize<System.Collections.Generic.Dictionary<string, string>>(File.ReadAllText(inFile));
                            if (dict != null)
                            {
                                var s = OsVaultAndStorage.LoadSettings();
                                s.OrderChimeEnabled = true;
                                if (dict.TryGetValue("local", out string? tLocal) && !string.IsNullOrWhiteSpace(tLocal))
                                    OsVaultAndStorage.StoreBearerTokenUnified(s, "local", tLocal, switchActiveEnv: false);
                                if (dict.TryGetValue("stage", out string? tStage) && !string.IsNullOrWhiteSpace(tStage))
                                    OsVaultAndStorage.StoreBearerTokenUnified(s, "stage", tStage, switchActiveEnv: false);
                                if (dict.TryGetValue("live", out string? tLive) && !string.IsNullOrWhiteSpace(tLive))
                                    OsVaultAndStorage.StoreBearerTokenUnified(s, "live", tLive, switchActiveEnv: false);

                                string activeEnv = dict.TryGetValue("active_env", out string? ae) && !string.IsNullOrWhiteSpace(ae) ? ae : "live";
                                if (!string.IsNullOrWhiteSpace(OsVaultAndStorage.ResolveBearerToken(s, activeEnv)))
                                    s.ApiEnvironment = activeEnv;
                                else if (!string.IsNullOrWhiteSpace(OsVaultAndStorage.ResolveBearerToken(s, "live")))
                                    s.ApiEnvironment = "live";
                                else if (!string.IsNullOrWhiteSpace(OsVaultAndStorage.ResolveBearerToken(s, "stage")))
                                    s.ApiEnvironment = "stage";

                                OsVaultAndStorage.SaveSettings(s);
                            }
                        }
                    }
                    catch { }
                    Environment.Exit(0);
                    return;
                }

                if (string.Equals(e.Args[i], "--diag-orders", StringComparison.OrdinalIgnoreCase) && i + 1 < e.Args.Length)
                {
                    string outFile = e.Args[i + 1];
                    try
                    {
                        var s = OsVaultAndStorage.LoadSettings();
                        var sn = SeelenfunkeLiveClient.FetchActiveEnvOrdersPulseAsync(s).GetAwaiter().GetResult();
                        var lines = new System.Collections.Generic.List<string>
                        {
                            $"ACTIVE_ENV={s.ApiEnvironment} | CHIME={s.OrderChimeEnabled}",
                            $"ENV={s.ApiEnvironment} | CONNECTED={sn.IsConnected} | STATUS={sn.StatusSummary} | OPEN={sn.OpenOrdersCount} | TOTAL={sn.TotalOrdersCount} | ACTIVE_ITEMS={sn.ActiveOrders.Count} | LATEST_KEY={sn.LatestOrderKey}"
                        };
                        File.WriteAllLines(outFile, lines, new System.Text.UTF8Encoding(false));
                    }
                    catch (Exception ex)
                    {
                        File.WriteAllText(outFile, "ERROR: " + ex.Message);
                    }
                    Environment.Exit(0);
                    return;
                }
            }

            bool isShellMode = e.Args.Any(a => string.Equals(a, "--shell", StringComparison.OrdinalIgnoreCase));

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
