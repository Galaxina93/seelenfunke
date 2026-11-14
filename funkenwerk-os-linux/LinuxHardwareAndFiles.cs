using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading.Tasks;

namespace FunkenwerkOSLinux
{
    public sealed class AudioSinkDto
    {
        public string Id { get; set; } = "";
        public string Name { get; set; } = "";
        public bool IsDefault { get; set; }
    }

    public sealed class AudioStateDto
    {
        public int Volume { get; set; } = 65;
        public bool IsMuted { get; set; }
        public string ActiveDeviceName { get; set; } = "System-Audio";
        public List<AudioSinkDto> Sinks { get; set; } = new();
    }

    public sealed class BtDeviceItemDto
    {
        public string Mac { get; set; } = "";
        public string Name { get; set; } = "";
        public bool IsPaired { get; set; }
        public bool IsConnected { get; set; }
        public string StatusLabel => IsConnected ? "✓ Verbunden" : (IsPaired ? "Gekoppelt" : "Koppeln");
    }

    public sealed class WifiNetworkDto
    {
        public string Ssid { get; set; } = "";
        public int Signal { get; set; }
        public string Band { get; set; } = "5 GHz";
        public string Security { get; set; } = "";
        public bool IsConnected { get; set; }
    }

    public sealed class SystemStatsDto
    {
        public string Hostname { get; set; } = Environment.MachineName;
        public string IpAddress { get; set; } = "Offline";
        public double CpuPercent { get; set; }
        public long RamUsedMb { get; set; }
        public long RamTotalMb { get; set; }
        public double CpuTempC { get; set; }
        public bool SshActive { get; set; }
        public string KernelVersion { get; set; } = "";
    }

    public sealed class WebcamDeviceStatusDto
    {
        public bool HasCamera { get; set; }
        public string CameraName { get; set; } = "Keine Kamera erkannt";
        public string VideoDevice { get; set; } = "";
        public bool HasUsbMic { get; set; }
        public string MicSourceId { get; set; } = "";
        public string MicSourceName { get; set; } = "";
        public double DiskFreeGb { get; set; }
        public double DiskTotalGb { get; set; }
    }

    public sealed class FileEntryDto
    {
        public string Name { get; set; } = "";
        public string FullPath { get; set; } = "";
        public bool IsDirectory { get; set; }
        public long SizeBytes { get; set; }
        public string SizeFormatted { get; set; } = "";
        public string ModifiedAt { get; set; } = "";
        public string Extension { get; set; } = "";
        public string OriginalPath { get; set; } = "";
    }

    public static class LinuxHardwareEngine
    {
        private static long s_prevIdleTicks;
        private static long s_prevTotalTicks;

        public static async Task<(int ExitCode, string StdOut, string StdErr)> RunProcessAsync(
            string fileName,
            IEnumerable<string> args,
            int timeoutMs = 8000)
        {
            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = fileName,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    UseShellExecute = false,
                    CreateNoWindow = true
                };
                psi.Environment["LC_ALL"] = "C.UTF-8";
                psi.Environment["LANG"] = "C.UTF-8";
                string xdgRuntime = Environment.GetEnvironmentVariable("XDG_RUNTIME_DIR") ?? "/run/user/1000";
                if (Directory.Exists(xdgRuntime))
                    psi.Environment["XDG_RUNTIME_DIR"] = xdgRuntime;

                foreach (string a in args)
                    psi.ArgumentList.Add(a);

                using var proc = Process.Start(psi);
                if (proc == null) return (-1, "", "Process failed to start");

                var outTask = proc.StandardOutput.ReadToEndAsync();
                var errTask = proc.StandardError.ReadToEndAsync();

                using var cts = new System.Threading.CancellationTokenSource(timeoutMs);
                try
                {
                    await proc.WaitForExitAsync(cts.Token).ConfigureAwait(false);
                }
                catch (OperationCanceledException)
                {
                    try { proc.Kill(entireProcessTree: true); } catch { }
                    return (-2, await outTask.ConfigureAwait(false), "Timeout");
                }

                return (proc.ExitCode, await outTask.ConfigureAwait(false), await errTask.ConfigureAwait(false));
            }
            catch (Exception ex)
            {
                return (-1, "", ex.Message);
            }
        }

        public static async Task<(int ExitCode, string StdOut, string StdErr)> RunProcessWithStdinAsync(
            string fileName,
            IEnumerable<string> args,
            string stdinText,
            int timeoutMs = 8000)
        {
            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = fileName,
                    RedirectStandardInput = true,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    UseShellExecute = false,
                    CreateNoWindow = true
                };
                psi.Environment["LC_ALL"] = "C.UTF-8";
                psi.Environment["LANG"] = "C.UTF-8";
                string xdgRuntime = Environment.GetEnvironmentVariable("XDG_RUNTIME_DIR") ?? "/run/user/1000";
                if (Directory.Exists(xdgRuntime))
                    psi.Environment["XDG_RUNTIME_DIR"] = xdgRuntime;

                foreach (string a in args)
                    psi.ArgumentList.Add(a);

                using var proc = Process.Start(psi);
                if (proc == null) return (-1, "", "Process failed to start");

                try
                {
                    await proc.StandardInput.WriteAsync(stdinText).ConfigureAwait(false);
                    await proc.StandardInput.FlushAsync().ConfigureAwait(false);
                }
                catch { }
                finally
                {
                    try { proc.StandardInput.Close(); } catch { }
                }

                var outTask = proc.StandardOutput.ReadToEndAsync();
                var errTask = proc.StandardError.ReadToEndAsync();

                using var cts = new System.Threading.CancellationTokenSource(timeoutMs);
                try
                {
                    await proc.WaitForExitAsync(cts.Token).ConfigureAwait(false);
                }
                catch (OperationCanceledException)
                {
                    try { proc.Kill(entireProcessTree: true); } catch { }
                    return (-2, await outTask.ConfigureAwait(false), "Timeout");
                }

                return (proc.ExitCode, await outTask.ConfigureAwait(false), await errTask.ConfigureAwait(false));
            }
            catch (Exception ex)
            {
                return (-1, "", ex.Message);
            }
        }

        public static SystemStatsDto GetSystemStats()
        {
            var dto = new SystemStatsDto
            {
                Hostname = Environment.MachineName,
                IpAddress = GetLocalIpv4(),
                CpuPercent = SampleCpuPercent(),
                CpuTempC = ReadCpuTemp(),
                KernelVersion = Environment.OSVersion.VersionString
            };

            ReadMemoryMb(out long usedMb, out long totalMb);
            dto.RamUsedMb = usedMb;
            dto.RamTotalMb = totalMb;
            dto.SshActive = File.Exists("/run/sshd.pid") || Directory.Exists("/run/sshd");
            return dto;
        }

        private static string GetLocalIpv4()
        {
            try
            {
                foreach (var ni in NetworkInterface.GetAllNetworkInterfaces())
                {
                    if (ni.OperationalStatus != OperationalStatus.Up || ni.NetworkInterfaceType == NetworkInterfaceType.Loopback)
                        continue;
                    foreach (var ua in ni.GetIPProperties().UnicastAddresses)
                    {
                        if (ua.Address.AddressFamily == AddressFamily.InterNetwork && !ua.Address.ToString().StartsWith("127."))
                            return ua.Address.ToString();
                    }
                }
            }
            catch { }
            return "Kein Netzwerk";
        }

        private static double SampleCpuPercent()
        {
            try
            {
                if (!File.Exists("/proc/stat")) return 0;
                string? firstLine = File.ReadLines("/proc/stat").FirstOrDefault();
                if (string.IsNullOrWhiteSpace(firstLine)) return 0;
                var parts = firstLine.Split(' ', StringSplitOptions.RemoveEmptyEntries);
                if (parts.Length < 5) return 0;

                long total = 0;
                for (int i = 1; i < parts.Length; i++)
                {
                    if (long.TryParse(parts[i], out long v)) total += v;
                }
                long idle = long.Parse(parts[4]) + (parts.Length > 5 ? long.Parse(parts[5]) : 0);

                long dTotal = total - s_prevTotalTicks;
                long dIdle = idle - s_prevIdleTicks;
                s_prevTotalTicks = total;
                s_prevIdleTicks = idle;

                if (dTotal <= 0) return 0.5;
                double pct = (1.0 - (double)dIdle / dTotal) * 100.0;
                return Math.Round(Math.Clamp(pct, 0.1, 100.0), 1);
            }
            catch
            {
                return 0;
            }
        }

        private static void ReadMemoryMb(out long usedMb, out long totalMb)
        {
            usedMb = 420;
            totalMb = 8192;
            try
            {
                if (!File.Exists("/proc/meminfo")) return;
                long memTotalKb = 0, memAvailKb = 0;
                foreach (string line in File.ReadLines("/proc/meminfo"))
                {
                    if (line.StartsWith("MemTotal:"))
                        memTotalKb = ParseKb(line);
                    else if (line.StartsWith("MemAvailable:"))
                        memAvailKb = ParseKb(line);
                    if (memTotalKb > 0 && memAvailKb > 0) break;
                }
                if (memTotalKb > 0)
                {
                    totalMb = memTotalKb / 1024;
                    usedMb = Math.Max(0, (memTotalKb - memAvailKb) / 1024);
                }
            }
            catch { }
        }

        private static long ParseKb(string line)
        {
            var m = Regex.Match(line, @"(\d+)");
            return m.Success && long.TryParse(m.Groups[1].Value, out long v) ? v : 0;
        }

        private static double ReadCpuTemp()
        {
            try
            {
                const string tzRoot = "/sys/class/thermal";
                if (!Directory.Exists(tzRoot)) return 0;
                foreach (string dir in Directory.GetDirectories(tzRoot, "thermal_zone*"))
                {
                    string tFile = Path.Combine(dir, "temp");
                    if (File.Exists(tFile) && int.TryParse(File.ReadAllText(tFile).Trim(), out int milliC) && milliC > 5000)
                        return Math.Round(milliC / 1000.0, 1);
                }
            }
            catch { }
            return 0;
        }

        // =====================================================================
        // AUDIO (PIPEWIRE / PACTL — 0..100 INTEGER STEPS, HFP PHONE CALL BRIDGE)
        // =====================================================================
        public static volatile bool PhoneCallAudioActive;

        public static async Task<AudioStateDto> GetAudioStateAsync()
        {
            var state = new AudioStateDto();
            var (_, defSinkOut, _) = await RunProcessAsync("pactl", new[] { "get-default-sink" }, 2500).ConfigureAwait(false);
            string defSink = defSinkOut.Trim();

            var (_, volOut, _) = await RunProcessAsync("pactl", new[] { "get-sink-volume", "@DEFAULT_SINK@" }, 2500).ConfigureAwait(false);
            var mVol = Regex.Match(volOut, @"(\d+)%");
            if (mVol.Success && int.TryParse(mVol.Groups[1].Value, out int v))
                state.Volume = Math.Clamp(v, 0, 100);

            var (_, muteOut, _) = await RunProcessAsync("pactl", new[] { "get-sink-mute", "@DEFAULT_SINK@" }, 2500).ConfigureAwait(false);
            state.IsMuted = muteOut.Contains("yes", StringComparison.OrdinalIgnoreCase);

            int defSinkNumericIndex = -1;
            var (code, sinksJson, _) = await RunProcessAsync("pactl", new[] { "-f", "json", "list", "sinks" }, 3000).ConfigureAwait(false);
            if (code == 0 && !string.IsNullOrWhiteSpace(sinksJson))
            {
                try
                {
                    using var doc = JsonDocument.Parse(sinksJson);
                    if (doc.RootElement.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var el in doc.RootElement.EnumerateArray())
                        {
                            string id = el.TryGetProperty("name", out var nEl) ? (nEl.GetString() ?? "") : "";
                            string desc = el.TryGetProperty("description", out var dEl) ? (dEl.GetString() ?? id) : id;
                            if (string.IsNullOrWhiteSpace(id)) continue;
                            if (desc.Contains("Hands-Free", StringComparison.OrdinalIgnoreCase) ||
                                id.Contains("headset_head_unit", StringComparison.OrdinalIgnoreCase))
                                continue;

                            bool isDef = string.Equals(id, defSink, StringComparison.OrdinalIgnoreCase);
                            if (isDef && el.TryGetProperty("index", out var idxEl) && idxEl.TryGetInt32(out int parsedIdx))
                                defSinkNumericIndex = parsedIdx;

                            state.Sinks.Add(new AudioSinkDto { Id = id, Name = desc, IsDefault = isDef });
                            if (isDef) state.ActiveDeviceName = desc;
                        }
                    }
                }
                catch { }
            }

            // Self-healing stream guard: if Bluetooth A2DP (e.g. Teufel) is default sink, rescue any stray stream stuck on internal ALSA
            // (Paused while an active Smartphone HFP phone call is bridged!)
            if (!PhoneCallAudioActive && !string.IsNullOrWhiteSpace(defSink) && defSink.StartsWith("bluez_output.", StringComparison.OrdinalIgnoreCase))
            {
                await MigrateAllSinkInputsToSinkAsync(defSink, defSinkNumericIndex).ConfigureAwait(false);
            }

            return state;
        }

        public static async Task MigrateAllSinkInputsToSinkAsync(string targetSinkName, int knownTargetSinkIndex = -1)
        {
            if (string.IsNullOrWhiteSpace(targetSinkName)) return;
            var (code, inputsOut, _) = await RunProcessAsync("pactl", new[] { "list", "short", "sink-inputs" }, 2000).ConfigureAwait(false);
            if (code != 0 || string.IsNullOrWhiteSpace(inputsOut)) return;

            foreach (string line in inputsOut.Split('\n', StringSplitOptions.RemoveEmptyEntries))
            {
                var parts = line.Trim().Split(new[] { '\t', ' ' }, StringSplitOptions.RemoveEmptyEntries);
                if (parts.Length >= 2 && int.TryParse(parts[0], out int inputId))
                {
                    if (knownTargetSinkIndex >= 0 && int.TryParse(parts[1], out int currentSinkIdx) && currentSinkIdx == knownTargetSinkIndex)
                        continue;

                    await RunProcessAsync("pactl", new[] { "move-sink-input", inputId.ToString(CultureInfo.InvariantCulture), targetSinkName }, 1500).ConfigureAwait(false);
                }
            }
        }

        public static async Task SetVolumeAsync(int volume)
        {
            int clamped = Math.Clamp(volume, 0, 100);
            await RunProcessAsync("pactl", new[] { "set-sink-mute", "@DEFAULT_SINK@", "0" }, 2000).ConfigureAwait(false);
            await RunProcessAsync("pactl", new[] { "set-sink-volume", "@DEFAULT_SINK@", $"{clamped}%" }, 2000).ConfigureAwait(false);
        }

        public static async Task ToggleMuteAsync()
        {
            await RunProcessAsync("pactl", new[] { "set-sink-mute", "@DEFAULT_SINK@", "toggle" }, 2000).ConfigureAwait(false);
        }

        private static readonly System.Threading.SemaphoreSlim s_btLock = new(1, 1);
        private static readonly Regex s_ansiRegex = new(@"\x1B\[[0-9;]*[a-zA-Z]", RegexOptions.Compiled);

        public static async Task SetDefaultSinkAsync(string sinkId)
        {
            if (string.IsNullOrWhiteSpace(sinkId)) return;
            string cleanSink = sinkId.Trim();
            await RunProcessAsync("pactl", new[] { "set-default-sink", cleanSink }, 2500).ConfigureAwait(false);
            await RunProcessAsync("pactl", new[] { "set-sink-mute", cleanSink, "0" }, 2000).ConfigureAwait(false);

            // Immediately migrate all active streams (e.g. Chrome WebAudio / Funkira live voice) to the new sink
            await MigrateAllSinkInputsToSinkAsync(cleanSink).ConfigureAwait(false);
        }

        public static async Task SetMuteExplicitAsync(bool mute)
        {
            await RunProcessAsync("pactl", new[] { "set-sink-mute", "@DEFAULT_SINK@", mute ? "1" : "0" }, 2000).ConfigureAwait(false);
        }

        // =====================================================================
        // LOGITECH C920 / USB WEBCAM & PIPEWIRE MICROPHONE ROUTING
        // =====================================================================
        public static async Task<WebcamDeviceStatusDto> DetectAndPrepareWebcamAsync(bool activateUsbMicDefault = false)
        {
            var status = new WebcamDeviceStatusDto();

            try
            {
                const string v4lRoot = "/sys/class/video4linux";
                if (Directory.Exists(v4lRoot))
                {
                    foreach (string devDir in Directory.GetDirectories(v4lRoot, "video*").OrderBy(x => x))
                    {
                        string leaf = Path.GetFileName(devDir);
                        string nameFile = Path.Combine(devDir, "name");
                        string devNode = "/dev/" + leaf;
                        if (!File.Exists(nameFile) || !File.Exists(devNode)) continue;

                        string camName = File.ReadAllText(nameFile).Trim();
                        if (string.IsNullOrWhiteSpace(camName) || camName.Contains("Metadata", StringComparison.OrdinalIgnoreCase))
                            continue;

                        if (!status.HasCamera || camName.Contains("C920", StringComparison.OrdinalIgnoreCase) || camName.Contains("Logitech", StringComparison.OrdinalIgnoreCase))
                        {
                            status.HasCamera = true;
                            status.CameraName = camName;
                            status.VideoDevice = devNode;
                            if (camName.Contains("C920", StringComparison.OrdinalIgnoreCase))
                                break;
                        }
                    }
                }
            }
            catch { }

            try
            {
                var (code, sourcesJson, _) = await RunProcessAsync("pactl", new[] { "-f", "json", "list", "sources" }, 3000).ConfigureAwait(false);
                if (code == 0 && !string.IsNullOrWhiteSpace(sourcesJson))
                {
                    using var doc = JsonDocument.Parse(sourcesJson);
                    if (doc.RootElement.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var el in doc.RootElement.EnumerateArray())
                        {
                            string id = el.TryGetProperty("name", out var nEl) ? (nEl.GetString() ?? "") : "";
                            string desc = el.TryGetProperty("description", out var dEl) ? (dEl.GetString() ?? id) : id;
                            if (string.IsNullOrWhiteSpace(id) || id.EndsWith(".monitor", StringComparison.OrdinalIgnoreCase))
                                continue;

                            bool isUsb = id.Contains("usb", StringComparison.OrdinalIgnoreCase) ||
                                         desc.Contains("C920", StringComparison.OrdinalIgnoreCase) ||
                                         desc.Contains("Webcam", StringComparison.OrdinalIgnoreCase) ||
                                         desc.Contains("Logitech", StringComparison.OrdinalIgnoreCase);

                            if (isUsb)
                            {
                                status.HasUsbMic = true;
                                status.MicSourceId = id;
                                status.MicSourceName = desc;
                                if (desc.Contains("C920", StringComparison.OrdinalIgnoreCase))
                                    break;
                            }
                            else if (string.IsNullOrWhiteSpace(status.MicSourceId))
                            {
                                status.MicSourceId = id;
                                status.MicSourceName = desc;
                            }
                        }
                    }
                }

                if (activateUsbMicDefault && !PhoneCallAudioActive && !string.IsNullOrWhiteSpace(status.MicSourceId))
                {
                    await RunProcessAsync("pactl", new[] { "set-default-source", status.MicSourceId }, 2000).ConfigureAwait(false);
                    await RunProcessAsync("pactl", new[] { "set-source-mute", status.MicSourceId, "0" }, 2000).ConfigureAwait(false);
                    await RunProcessAsync("pactl", new[] { "set-source-volume", status.MicSourceId, "88%" }, 2000).ConfigureAwait(false);
                }
            }
            catch { }

            try
            {
                var di = new DriveInfo(OperatingSystem.IsLinux() ? "/" : Path.GetPathRoot(Environment.CurrentDirectory) ?? "C:\\");
                if (di.IsReady)
                {
                    status.DiskFreeGb = Math.Round(di.AvailableFreeSpace / (1024.0 * 1024 * 1024), 1);
                    status.DiskTotalGb = Math.Round(di.TotalSize / (1024.0 * 1024 * 1024), 1);
                }
            }
            catch { }

            return status;
        }

        // =====================================================================
        // BLUETOOTH (BLUEZ INTERACTIVE BREDR+LE PAIRING + PIPEWIRE A2DP SINK)
        // =====================================================================
        private static Process? StartInteractiveBluetoothCtl(StringBuilder liveLog, object logSync)
        {
            var psi = new ProcessStartInfo
            {
                FileName = "bluetoothctl",
                RedirectStandardInput = true,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                UseShellExecute = false,
                CreateNoWindow = true
            };
            psi.Environment["LC_ALL"] = "C.UTF-8";
            psi.Environment["LANG"] = "C.UTF-8";

            var proc = Process.Start(psi);
            if (proc == null) return null;

            void AttachReader(StreamReader reader)
            {
                _ = Task.Run(async () =>
                {
                    char[] buf = new char[512];
                    try
                    {
                        while (!proc.HasExited)
                        {
                            int n = await reader.ReadAsync(buf, 0, buf.Length).ConfigureAwait(false);
                            if (n <= 0) break;
                            string clean = s_ansiRegex.Replace(new string(buf, 0, n), "");
                            lock (logSync) { liveLog.Append(clean); }
                        }
                    }
                    catch { }
                });
            }

            AttachReader(proc.StandardOutput);
            AttachReader(proc.StandardError);
            return proc;
        }

        private static string SnapshotBtLog(StringBuilder liveLog, object logSync)
        {
            lock (logSync) { return liveLog.ToString(); }
        }

        private static async Task SendBtCmdAsync(Process proc, string cmd, int waitMs = 250)
        {
            try
            {
                await proc.StandardInput.WriteLineAsync(cmd).ConfigureAwait(false);
                await proc.StandardInput.FlushAsync().ConfigureAwait(false);
                if (waitMs > 0)
                    await Task.Delay(waitMs).ConfigureAwait(false);
            }
            catch { }
        }

        public static async Task<List<BtDeviceItemDto>> ListBluetoothDevicesAsync(bool activeScan)
        {
            if (activeScan)
            {
                await s_btLock.WaitAsync().ConfigureAwait(false);
                try
                {
                    await RunProcessAsync("rfkill", new[] { "unblock", "bluetooth" }, 2000).ConfigureAwait(false);
                    var log = new StringBuilder();
                    var sync = new object();
                    using var proc = StartInteractiveBluetoothCtl(log, sync);
                    if (proc != null)
                    {
                        await SendBtCmdAsync(proc, "power on", 250).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "pairable on", 200).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "agent NoInputNoOutput", 150).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "default-agent", 150).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "scan bredr", 3400).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "scan on", 2200).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "scan off", 250).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "quit", 150).ConfigureAwait(false);
                        try { proc.Kill(true); } catch { }
                    }
                }
                finally
                {
                    s_btLock.Release();
                }
            }

            var (_, pairedOut, _) = await RunProcessAsync("bluetoothctl", new[] { "devices", "Paired" }, 2500).ConfigureAwait(false);
            var pairedMacs = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            foreach (string line in pairedOut.Split('\n', StringSplitOptions.RemoveEmptyEntries))
            {
                var m = Regex.Match(line, @"Device\s+([0-9A-Fa-f:]{17})\s+(.+)$");
                if (m.Success) pairedMacs.Add(m.Groups[1].Value.ToUpperInvariant());
            }

            var (_, allOut, _) = await RunProcessAsync("bluetoothctl", new[] { "devices" }, 2500).ConfigureAwait(false);
            var result = new List<BtDeviceItemDto>();

            foreach (string line in allOut.Split('\n', StringSplitOptions.RemoveEmptyEntries))
            {
                var m = Regex.Match(line.Trim(), @"Device\s+([0-9A-Fa-f:]{17})\s+(.+)$");
                if (!m.Success) continue;
                string mac = m.Groups[1].Value.ToUpperInvariant();
                string name = m.Groups[2].Value.Trim();

                bool isMacOnly = Regex.IsMatch(name, @"^[0-9A-Fa-f:-]{17}$");
                bool isPaired = pairedMacs.Contains(mac);
                if (isMacOnly && !isPaired) continue;

                var (_, infoOut, _) = await RunProcessAsync("bluetoothctl", new[] { "info", mac }, 2000).ConfigureAwait(false);
                bool isConn = Regex.IsMatch(infoOut, @"Connected:\s+yes", RegexOptions.IgnoreCase);
                if (!isPaired && Regex.IsMatch(infoOut, @"Paired:\s+yes", RegexOptions.IgnoreCase))
                    isPaired = true;

                result.Add(new BtDeviceItemDto
                {
                    Mac = mac,
                    Name = name,
                    IsPaired = isPaired,
                    IsConnected = isConn
                });
            }

            return result
                .OrderByDescending(d => d.IsConnected)
                .ThenByDescending(d => d.IsPaired)
                .ThenBy(d => d.Name)
                .ToList();
        }

        private static async Task<string?> RoutePipeWireBluetoothSinkAsync(string cleanMac, string deviceHintName)
        {
            string macUnderscore = cleanMac.Replace(':', '_');
            for (int attempt = 0; attempt < 8; attempt++)
            {
                await Task.Delay(attempt == 0 ? 450 : 500).ConfigureAwait(false);
                var audio = await GetAudioStateAsync().ConfigureAwait(false);
                var btSink = audio.Sinks.FirstOrDefault(s =>
                                 !string.IsNullOrWhiteSpace(cleanMac) &&
                                 s.Id.Contains(macUnderscore, StringComparison.OrdinalIgnoreCase))
                             ?? audio.Sinks.FirstOrDefault(s =>
                                 !string.IsNullOrWhiteSpace(deviceHintName) &&
                                 s.Name.Contains(deviceHintName, StringComparison.OrdinalIgnoreCase))
                             ?? audio.Sinks.FirstOrDefault(s =>
                                 s.Id.Contains("bluez", StringComparison.OrdinalIgnoreCase));
                if (btSink != null)
                {
                    await SetDefaultSinkAsync(btSink.Id).ConfigureAwait(false);
                    var (_, volOut, _) = await RunProcessAsync("pactl", new[] { "get-sink-volume", btSink.Id }, 2000).ConfigureAwait(false);
                    var mVol = Regex.Match(volOut, @"(\d+)%");
                    if (!mVol.Success || (int.TryParse(mVol.Groups[1].Value, out int curV) && curV < 82))
                    {
                        await RunProcessAsync("pactl", new[] { "set-sink-volume", btSink.Id, "88%" }, 2000).ConfigureAwait(false);
                    }

                    string capturedSinkId = btSink.Id;
                    _ = Task.Run(async () =>
                    {
                        try
                        {
                            await Task.Delay(1100).ConfigureAwait(false);
                            await SetDefaultSinkAsync(capturedSinkId).ConfigureAwait(false);
                            await Task.Delay(1500).ConfigureAwait(false);
                            await MigrateAllSinkInputsToSinkAsync(capturedSinkId).ConfigureAwait(false);
                        }
                        catch { }
                    });

                    return btSink.Name;
                }
            }
            return null;
        }

        private static volatile bool s_manualBtDisconnect;

        public static Task<(bool Success, string Message)> ConnectBluetoothAsync(string mac)
            => ConnectOrPairBluetoothAsync(mac, forcePairingMode: false);

        public static async Task<(bool Success, string Message)> ConnectOrPairBluetoothAsync(
            string? targetMacOrName,
            bool forcePairingMode = false)
        {
            s_manualBtDisconnect = false;
            await s_btLock.WaitAsync().ConfigureAwait(false);
            try
            {
                var settings = LinuxVaultAndStorage.LoadSettings();
                string query = (targetMacOrName ?? "").Trim();
                bool isMacQuery = Regex.IsMatch(query, @"^[0-9A-Fa-f:]{17}$");

                bool wantsTeufel = string.IsNullOrWhiteSpace(query) ||
                                   query.Contains("teufel", StringComparison.OrdinalIgnoreCase) ||
                                   query.Contains("anlage", StringComparison.OrdinalIgnoreCase) ||
                                   query.Contains("stereo", StringComparison.OrdinalIgnoreCase) ||
                                   query.Contains("lautsprecher", StringComparison.OrdinalIgnoreCase) ||
                                   query.Contains("box", StringComparison.OrdinalIgnoreCase);

                string candidateMac = "";
                string nameHint = wantsTeufel ? "Teufel" : query;

                if (isMacQuery)
                {
                    candidateMac = query.ToUpperInvariant();
                }
                else if (wantsTeufel)
                {
                    candidateMac = string.IsNullOrWhiteSpace(settings.PreferredBluetoothMac)
                        ? "FC:58:FA:C7:F4:1D"
                        : settings.PreferredBluetoothMac.Trim().ToUpperInvariant();
                }

                await RunProcessAsync("rfkill", new[] { "unblock", "bluetooth" }, 2000).ConfigureAwait(false);
                await RunProcessAsync("bluetoothctl", new[] { "power", "on" }, 2000).ConfigureAwait(false);

                // 1. Fast Path: Already paired & known -> try quick connect unless explicit Pairing Mode was requested
                bool staleKeyDetected = false;
                bool hadExistingPair = false;
                if (!forcePairingMode && !string.IsNullOrWhiteSpace(candidateMac))
                {
                    var (_, infoBefore, _) = await RunProcessAsync("bluetoothctl", new[] { "info", candidateMac }, 2000).ConfigureAwait(false);
                    if (Regex.IsMatch(infoBefore, @"Connected:\s+yes", RegexOptions.IgnoreCase))
                    {
                        string? sinkName = await RoutePipeWireBluetoothSinkAsync(candidateMac, nameHint).ConfigureAwait(false);
                        return (true, $"✓ Verbunden mit {(sinkName ?? "Teufel Stereo-Anlage")} ({candidateMac})");
                    }

                    if (Regex.IsMatch(infoBefore, @"Paired:\s+yes", RegexOptions.IgnoreCase))
                    {
                        hadExistingPair = true;
                        await RunProcessAsync("bluetoothctl", new[] { "trust", candidateMac }, 2000).ConfigureAwait(false);
                        var (qCode, qOut, qErr) = await RunProcessAsync("bluetoothctl", new[] { "connect", candidateMac }, 6500).ConfigureAwait(false);
                        string combined = qOut + "\n" + qErr;
                        if (qCode == 0 || combined.Contains("Connection successful", StringComparison.OrdinalIgnoreCase))
                        {
                            string? sinkName = await RoutePipeWireBluetoothSinkAsync(candidateMac, nameHint).ConfigureAwait(false);
                            settings.PreferredBluetoothMac = candidateMac;
                            LinuxVaultAndStorage.SaveSettings(settings);
                            return (true, $"✓ Verbunden mit {(sinkName ?? nameHint)} ({candidateMac})");
                        }

                        if (combined.Contains("key-missing", StringComparison.OrdinalIgnoreCase) ||
                            combined.Contains("Authentication", StringComparison.OrdinalIgnoreCase) ||
                            combined.Contains("Rejected", StringComparison.OrdinalIgnoreCase))
                        {
                            staleKeyDetected = true;
                        }
                    }
                }

                // 2. Full Interactive BlueZ Pairing & Discovery Engine (handles live Pairing Mode & uncached BR/EDR + LE devices!)
                var log = new StringBuilder();
                var sync = new object();
                using var proc = StartInteractiveBluetoothCtl(log, sync);
                if (proc == null)
                    return (false, "Bluetooth-Dienst (bluetoothctl) konnte nicht gestartet werden.");

                try
                {
                    await SendBtCmdAsync(proc, "power on", 250).ConfigureAwait(false);
                    await SendBtCmdAsync(proc, "pairable on", 200).ConfigureAwait(false);
                    await SendBtCmdAsync(proc, "agent NoInputNoOutput", 200).ConfigureAwait(false);
                    await SendBtCmdAsync(proc, "default-agent", 200).ConfigureAwait(false);

                    bool removedStale = false;
                    if ((forcePairingMode || staleKeyDetected) && !string.IsNullOrWhiteSpace(candidateMac))
                    {
                        await SendBtCmdAsync(proc, $"remove {candidateMac}", 650).ConfigureAwait(false);
                        removedStale = true;
                    }

                    int scanStartLogLen = SnapshotBtLog(log, sync).Length;
                    await SendBtCmdAsync(proc, "scan on", 250).ConfigureAwait(false);

                    string resolvedMac = "";
                    string resolvedName = nameHint;
                    bool sawLiveInquiryForCandidate = false;

                    var scanSw = Stopwatch.StartNew();
                    while (scanSw.ElapsedMilliseconds < 8500)
                    {
                        await SendBtCmdAsync(proc, "devices", 450).ConfigureAwait(false);
                        string snap = SnapshotBtLog(log, sync);
                        string scanDelta = snap.Length > scanStartLogLen ? snap.Substring(scanStartLogLen) : snap;

                        if (!string.IsNullOrWhiteSpace(candidateMac))
                        {
                            string escMac = Regex.Escape(candidateMac);
                            if (Regex.IsMatch(scanDelta, $@"(\[NEW\]\s+Device\s+{escMac}|\[CHG\]\s+Device\s+{escMac}\s+RSSI:)", RegexOptions.IgnoreCase))
                            {
                                sawLiveInquiryForCandidate = true;
                            }
                        }

                        foreach (Match dm in Regex.Matches(snap, @"Device\s+([0-9A-Fa-f:]{17})\s+([^\r\n]+)"))
                        {
                            string dMac = dm.Groups[1].Value.ToUpperInvariant();
                            string dName = dm.Groups[2].Value.Trim();
                            if (Regex.IsMatch(dName, @"^[0-9A-Fa-f:-]{17}$") && dMac != candidateMac)
                                continue;

                            if (!string.IsNullOrWhiteSpace(candidateMac) && string.Equals(dMac, candidateMac, StringComparison.OrdinalIgnoreCase))
                            {
                                // If we kept an old pair, wait up to 4.5s to see if the device is broadcasting live Pairing-Mode RSSI
                                if (hadExistingPair && !removedStale && !sawLiveInquiryForCandidate && scanSw.ElapsedMilliseconds < 4500)
                                    continue;

                                resolvedMac = dMac;
                                if (!Regex.IsMatch(dName, @"^[0-9A-Fa-f:-]{17}$")) resolvedName = dName;
                                break;
                            }

                            if (!isMacQuery && !string.IsNullOrWhiteSpace(nameHint) &&
                                dName.Contains(nameHint, StringComparison.OrdinalIgnoreCase))
                            {
                                resolvedMac = dMac;
                                resolvedName = dName;
                                break;
                            }
                        }

                        if (!string.IsNullOrWhiteSpace(resolvedMac))
                            break;
                    }

                    if (string.IsNullOrWhiteSpace(resolvedMac))
                        resolvedMac = candidateMac;

                    if (string.IsNullOrWhiteSpace(resolvedMac))
                    {
                        await SendBtCmdAsync(proc, "scan off", 200).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "quit", 100).ConfigureAwait(false);
                        return (false, $"Kein passendes Bluetooth-Gerät ('{nameHint}') im Pairing-Modus gefunden. Bitte prüfe, ob die Pairing-LED blinkt.");
                    }

                    // CRITICAL FIX FOR BLUEZ 5.72 + TEUFEL PAIRING MODE:
                    // If connect failed (e.g. br-connection-create-socket) AND the speaker is actively broadcasting Pairing-Mode RSSI,
                    // remove the stale bond first and re-discover for 3s so 'pair' performs a genuine link-key exchange instead of 'AlreadyExists'!
                    if (hadExistingPair && !removedStale && sawLiveInquiryForCandidate)
                    {
                        await SendBtCmdAsync(proc, $"remove {resolvedMac}", 750).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "scan on", 3200).ConfigureAwait(false);
                    }

                    // Pair & Trust inside the SAME live bluetoothctl session while inquiry record is active in BlueZ!
                    int beforePairLen = SnapshotBtLog(log, sync).Length;
                    await SendBtCmdAsync(proc, $"pair {resolvedMac}", 350).ConfigureAwait(false);

                    var pairSw = Stopwatch.StartNew();
                    while (pairSw.ElapsedMilliseconds < 6500)
                    {
                        string cur = SnapshotBtLog(log, sync);
                        string delta = cur.Length > beforePairLen ? cur.Substring(beforePairLen) : cur;
                        if (delta.Contains("Pairing successful", StringComparison.OrdinalIgnoreCase) ||
                            delta.Contains("AlreadyExists", StringComparison.OrdinalIgnoreCase) ||
                            delta.Contains("Paired: yes", StringComparison.OrdinalIgnoreCase))
                        {
                            break;
                        }
                        if (delta.Contains("Failed to pair", StringComparison.OrdinalIgnoreCase))
                            break;
                        await Task.Delay(250).ConfigureAwait(false);
                    }

                    await SendBtCmdAsync(proc, $"trust {resolvedMac}", 350).ConfigureAwait(false);
                    await SendBtCmdAsync(proc, "scan off", 350).ConfigureAwait(false);

                    int beforeConnLen = SnapshotBtLog(log, sync).Length;
                    await SendBtCmdAsync(proc, $"connect {resolvedMac}", 350).ConfigureAwait(false);

                    bool connected = false;
                    var connSw = Stopwatch.StartNew();
                    while (connSw.ElapsedMilliseconds < 6500)
                    {
                        string cur = SnapshotBtLog(log, sync);
                        string delta = cur.Length > beforeConnLen ? cur.Substring(beforeConnLen) : cur;
                        if (delta.Contains("Connection successful", StringComparison.OrdinalIgnoreCase))
                        {
                            connected = true;
                            break;
                        }
                        if (delta.Contains("Failed to connect", StringComparison.OrdinalIgnoreCase))
                            break;
                        await Task.Delay(250).ConfigureAwait(false);
                    }

                    await SendBtCmdAsync(proc, $"info {resolvedMac}", 450).ConfigureAwait(false);
                    string finalLog = SnapshotBtLog(log, sync);
                    if (!connected && Regex.IsMatch(finalLog, @"Connected:\s+yes", RegexOptions.IgnoreCase))
                        connected = true;

                    await SendBtCmdAsync(proc, "quit", 150).ConfigureAwait(false);

                    if (connected)
                    {
                        string? sinkName = await RoutePipeWireBluetoothSinkAsync(resolvedMac, resolvedName).ConfigureAwait(false);
                        settings.PreferredBluetoothMac = resolvedMac;
                        LinuxVaultAndStorage.SaveSettings(settings);
                        return (true, $"✓ Erfolgreich gekoppelt & verbunden mit {(sinkName ?? resolvedName)} ({resolvedMac})");
                    }

                    return (false, $"Bluetooth-Gerät {resolvedName} ({resolvedMac}) antwortet noch nicht auf den Verbindungsaufbau. Bitte kurz prüfen, ob das Gerät im Pairing-Modus blinkt.");
                }
                finally
                {
                    try { if (!proc.HasExited) proc.Kill(true); } catch { }
                }
            }
            finally
            {
                s_btLock.Release();
            }
        }

        public static async Task<(bool Connected, bool AutoHealedNow, string Summary)> EnsurePreferredBluetoothConnectedWatchdogAsync()
        {
            if (PhoneCallAudioActive) return (true, false, "");
            if (s_manualBtDisconnect) return (false, false, "");
            if (s_btLock.CurrentCount == 0) return (false, false, "");

            var settings = LinuxVaultAndStorage.LoadSettings();
            string prefMac = string.IsNullOrWhiteSpace(settings.PreferredBluetoothMac)
                ? "FC:58:FA:C7:F4:1D"
                : settings.PreferredBluetoothMac.Trim().ToUpperInvariant();

            var (_, infoOut, _) = await RunProcessAsync("bluetoothctl", new[] { "info", prefMac }, 2000).ConfigureAwait(false);
            if (Regex.IsMatch(infoOut, @"Connected:\s+yes", RegexOptions.IgnoreCase))
            {
                await RoutePipeWireBluetoothSinkAsync(prefMac, "Teufel").ConfigureAwait(false);
                return (true, false, "");
            }

            // Not connected: try fast silent reconnect first (wakes/reconnects when Teufel is turned on)
            if (Regex.IsMatch(infoOut, @"Paired:\s+yes", RegexOptions.IgnoreCase))
            {
                var (qCode, qOut, qErr) = await RunProcessAsync("bluetoothctl", new[] { "connect", prefMac }, 4500).ConfigureAwait(false);
                if (qCode == 0 || (qOut + qErr).Contains("Connection successful", StringComparison.OrdinalIgnoreCase))
                {
                    string? sink = await RoutePipeWireBluetoothSinkAsync(prefMac, "Teufel").ConfigureAwait(false);
                    return (true, true, $"Bluetooth-Verbindung zu {(sink ?? "Teufel KB 43 CR 19")} ({prefMac}) automatisch wiederhergestellt.");
                }
            }

            // Check briefly (3.8s) if Teufel is actively broadcasting in Pairing Mode right now!
            if (!await s_btLock.WaitAsync(0).ConfigureAwait(false))
                return (false, false, "");

            bool inPairingMode = false;
            try
            {
                var log = new StringBuilder();
                var sync = new object();
                using var proc = StartInteractiveBluetoothCtl(log, sync);
                if (proc != null)
                {
                    try
                    {
                        int startLen = SnapshotBtLog(log, sync).Length;
                        await SendBtCmdAsync(proc, "scan on", 3600).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "scan off", 200).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "quit", 100).ConfigureAwait(false);
                        string delta = SnapshotBtLog(log, sync);
                        if (delta.Length > startLen) delta = delta.Substring(startLen);
                        string escMac = Regex.Escape(prefMac);
                        if (Regex.IsMatch(delta, $@"(\[NEW\]\s+Device\s+{escMac}|\[CHG\]\s+Device\s+{escMac}\s+RSSI:|Teufel\s+KB)", RegexOptions.IgnoreCase))
                        {
                            inPairingMode = true;
                        }
                    }
                    finally
                    {
                        try { if (!proc.HasExited) proc.Kill(true); } catch { }
                    }
                }
            }
            finally
            {
                s_btLock.Release();
            }

            if (inPairingMode)
            {
                var (ok, msg) = await ConnectOrPairBluetoothAsync(prefMac, forcePairingMode: true).ConfigureAwait(false);
                if (ok)
                    return (true, true, $"Teufel-Anlage im Pairing-Modus erkannt und automatisch neu gekoppelt: {msg}");
            }

            return (false, false, "");
        }

        public static async Task MigrateAllSourceOutputsToSourceAsync(string targetSourceName)
        {
            if (string.IsNullOrWhiteSpace(targetSourceName)) return;
            var (code, outputsOut, _) = await RunProcessAsync("pactl", new[] { "list", "short", "source-outputs" }, 2000).ConfigureAwait(false);
            if (code != 0 || string.IsNullOrWhiteSpace(outputsOut)) return;

            foreach (string line in outputsOut.Split('\n', StringSplitOptions.RemoveEmptyEntries))
            {
                var parts = line.Trim().Split(new[] { '\t', ' ' }, StringSplitOptions.RemoveEmptyEntries);
                if (parts.Length >= 2 && int.TryParse(parts[0], out int outputId))
                {
                    await RunProcessAsync("pactl", new[] { "move-source-output", outputId.ToString(CultureInfo.InvariantCulture), targetSourceName }, 1500).ConfigureAwait(false);
                }
            }
        }

        public static async Task<(bool BridgedHfp, string ActiveModeLabel)> SwitchAudioToPhoneCallHfpAsync(bool active)
        {
            var settings = LinuxVaultAndStorage.LoadSettings();
            string teufelMacUnderscore = (string.IsNullOrWhiteSpace(settings.PreferredBluetoothMac)
                ? "FC:58:FA:C7:F4:1D"
                : settings.PreferredBluetoothMac.Trim().ToUpperInvariant()).Replace(':', '_');

            if (!active)
            {
                PhoneCallAudioActive = false;
                var cam = await DetectAndPrepareWebcamAsync(activateUsbMicDefault: true).ConfigureAwait(false);
                if (!string.IsNullOrWhiteSpace(cam.MicSourceId))
                {
                    await MigrateAllSourceOutputsToSourceAsync(cam.MicSourceId).ConfigureAwait(false);
                }

                string? restoredSink = await RoutePipeWireBluetoothSinkAsync(
                    string.IsNullOrWhiteSpace(settings.PreferredBluetoothMac) ? "FC:58:FA:C7:F4:1D" : settings.PreferredBluetoothMac,
                    "Teufel").ConfigureAwait(false);

                return (false, restoredSink ?? "Teufel Stereo + USB C920");
            }

            // Look for a connected Smartphone Bluetooth HFP card/sink/source (distinct from Teufel speaker!)
            string? phoneCardName = null;
            var (cCode, cardsJson, _) = await RunProcessAsync("pactl", new[] { "-f", "json", "list", "cards" }, 2500).ConfigureAwait(false);
            if (cCode == 0 && !string.IsNullOrWhiteSpace(cardsJson))
            {
                try
                {
                    using var cDoc = JsonDocument.Parse(cardsJson);
                    if (cDoc.RootElement.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var cEl in cDoc.RootElement.EnumerateArray())
                        {
                            string cName = cEl.TryGetProperty("name", out var nEl) ? (nEl.GetString() ?? "") : "";
                            if (cName.StartsWith("bluez_card.", StringComparison.OrdinalIgnoreCase) &&
                                !cName.Contains(teufelMacUnderscore, StringComparison.OrdinalIgnoreCase))
                            {
                                phoneCardName = cName;
                                break;
                            }
                        }
                    }
                }
                catch { }
            }

            if (!string.IsNullOrWhiteSpace(phoneCardName))
            {
                // Activate headset-audio-gateway profile if available on the smartphone card
                await RunProcessAsync("pactl", new[] { "set-card-profile", phoneCardName, "headset-audio-gateway" }, 2000).ConfigureAwait(false);
                await Task.Delay(350).ConfigureAwait(false);
            }

            string? phoneSink = null;
            string? phoneSource = null;

            var (sCode, sinksOut, _) = await RunProcessAsync("pactl", new[] { "list", "short", "sinks" }, 2000).ConfigureAwait(false);
            if (sCode == 0 && !string.IsNullOrWhiteSpace(sinksOut))
            {
                foreach (string line in sinksOut.Split('\n', StringSplitOptions.RemoveEmptyEntries))
                {
                    var parts = line.Trim().Split(new[] { '\t', ' ' }, StringSplitOptions.RemoveEmptyEntries);
                    if (parts.Length >= 2)
                    {
                        string sName = parts[1];
                        if (sName.StartsWith("bluez_output.", StringComparison.OrdinalIgnoreCase) &&
                            !sName.Contains(teufelMacUnderscore, StringComparison.OrdinalIgnoreCase))
                        {
                            phoneSink = sName;
                            break;
                        }
                    }
                }
            }

            var (srcCode, sourcesOut, _) = await RunProcessAsync("pactl", new[] { "list", "short", "sources" }, 2000).ConfigureAwait(false);
            if (srcCode == 0 && !string.IsNullOrWhiteSpace(sourcesOut))
            {
                foreach (string line in sourcesOut.Split('\n', StringSplitOptions.RemoveEmptyEntries))
                {
                    var parts = line.Trim().Split(new[] { '\t', ' ' }, StringSplitOptions.RemoveEmptyEntries);
                    if (parts.Length >= 2)
                    {
                        string srcName = parts[1];
                        if (srcName.StartsWith("bluez_input.", StringComparison.OrdinalIgnoreCase) &&
                            !srcName.EndsWith(".monitor", StringComparison.OrdinalIgnoreCase) &&
                            !srcName.Contains(teufelMacUnderscore, StringComparison.OrdinalIgnoreCase))
                        {
                            phoneSource = srcName;
                            break;
                        }
                    }
                }
            }

            if (!string.IsNullOrWhiteSpace(phoneSink) && !string.IsNullOrWhiteSpace(phoneSource))
            {
                PhoneCallAudioActive = true;
                await RunProcessAsync("pactl", new[] { "set-default-sink", phoneSink }, 2000).ConfigureAwait(false);
                await RunProcessAsync("pactl", new[] { "set-sink-mute", phoneSink, "0" }, 1500).ConfigureAwait(false);
                await RunProcessAsync("pactl", new[] { "set-sink-volume", phoneSink, "92%" }, 1500).ConfigureAwait(false);
                await MigrateAllSinkInputsToSinkAsync(phoneSink).ConfigureAwait(false);

                await RunProcessAsync("pactl", new[] { "set-default-source", phoneSource }, 2000).ConfigureAwait(false);
                await RunProcessAsync("pactl", new[] { "set-source-mute", phoneSource, "0" }, 1500).ConfigureAwait(false);
                await RunProcessAsync("pactl", new[] { "set-source-volume", phoneSource, "95%" }, 1500).ConfigureAwait(false);
                await MigrateAllSourceOutputsToSourceAsync(phoneSource).ConfigureAwait(false);

                return (true, "Bluetooth HFP Smartphone-Brücke");
            }

            // Fallback: Workshop speakerphone / test-simulation mode (Teufel + USB C920 mic)
            PhoneCallAudioActive = false;
            return (false, "Werkstatt-Freisprechen (Teufel + C920)");
        }

        public static async Task<(bool Ok, string Message, List<string> PairedPhones)> EnableSmartphoneBluetoothPairableAsync()
        {
            var settings = LinuxVaultAndStorage.LoadSettings();
            string teufelMac = string.IsNullOrWhiteSpace(settings.PreferredBluetoothMac)
                ? "FC:58:FA:C7:F4:1D"
                : settings.PreferredBluetoothMac.Trim().ToUpperInvariant();

            if (!await s_btLock.WaitAsync(4000).ConfigureAwait(false))
                return (false, "Bluetooth ist gerade beschäftigt. Bitte in 3 Sekunden erneut versuchen.", new List<string>());

            try
            {
                await RunProcessAsync("rfkill", new[] { "unblock", "bluetooth" }, 2000).ConfigureAwait(false);
                var log = new StringBuilder();
                var sync = new object();
                using var proc = StartInteractiveBluetoothCtl(log, sync);
                if (proc != null)
                {
                    try
                    {
                        await SendBtCmdAsync(proc, "power on", 200).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "system-alias Funkenwerk-MiniPC", 200).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "discoverable-timeout 180", 150).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "discoverable on", 250).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "pairable on", 200).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "agent NoInputNoOutput", 150).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "default-agent", 150).ConfigureAwait(false);
                        await SendBtCmdAsync(proc, "quit", 100).ConfigureAwait(false);
                    }
                    finally
                    {
                        try { if (!proc.HasExited) proc.Kill(true); } catch { }
                    }
                }
            }
            finally
            {
                s_btLock.Release();
            }

            // Keep an auto-trust helper alive for 180 seconds so when Alina taps "Funkenwerk-MiniPC" on Android, BlueZ trusts it immediately
            _ = Task.Run(async () =>
            {
                for (int i = 0; i < 36; i++)
                {
                    await Task.Delay(5000).ConfigureAwait(false);
                    try
                    {
                        var (_, pOut, _) = await RunProcessAsync("bluetoothctl", new[] { "devices", "Paired" }, 2000).ConfigureAwait(false);
                        foreach (string line in pOut.Split('\n', StringSplitOptions.RemoveEmptyEntries))
                        {
                            var m = Regex.Match(line, @"Device\s+([0-9A-Fa-f:]{17})\s+(.+)$");
                            if (m.Success)
                            {
                                string mac = m.Groups[1].Value.ToUpperInvariant();
                                if (!string.Equals(mac, teufelMac, StringComparison.OrdinalIgnoreCase))
                                {
                                    await RunProcessAsync("bluetoothctl", new[] { "trust", mac }, 1500).ConfigureAwait(false);
                                }
                            }
                        }
                    }
                    catch { }
                }
            });

            var phones = new List<string>();
            var (_, pairedOut, _) = await RunProcessAsync("bluetoothctl", new[] { "devices", "Paired" }, 2000).ConfigureAwait(false);
            foreach (string line in pairedOut.Split('\n', StringSplitOptions.RemoveEmptyEntries))
            {
                var m = Regex.Match(line, @"Device\s+([0-9A-Fa-f:]{17})\s+(.+)$");
                if (m.Success)
                {
                    string mac = m.Groups[1].Value.ToUpperInvariant();
                    string name = m.Groups[2].Value.Trim();
                    if (!string.Equals(mac, teufelMac, StringComparison.OrdinalIgnoreCase))
                        phones.Add($"{name} ({mac})");
                }
            }

            return (true, "Mini-PC ist für 3 Minuten als 'Funkenwerk-MiniPC' per Bluetooth sichtbar. Einfach auf dem Handy koppeln.", phones);
        }

        public static async Task<string> DisconnectBluetoothAsync(string? macOrName)
        {
            s_manualBtDisconnect = true;
            string q = (macOrName ?? "").Trim();
            if (Regex.IsMatch(q, @"^[0-9A-Fa-f:]{17}$"))
            {
                await RunProcessAsync("bluetoothctl", new[] { "disconnect", q.ToUpperInvariant() }, 4000).ConfigureAwait(false);
                return q.ToUpperInvariant();
            }

            var list = await ListBluetoothDevicesAsync(activeScan: false).ConfigureAwait(false);
            var targets = list.Where(d => d.IsConnected &&
                (string.IsNullOrWhiteSpace(q) || q.Equals("all", StringComparison.OrdinalIgnoreCase) || d.Name.Contains(q, StringComparison.OrdinalIgnoreCase))).ToList();

            foreach (var t in targets)
            {
                await RunProcessAsync("bluetoothctl", new[] { "disconnect", t.Mac }, 4000).ConfigureAwait(false);
            }
            return targets.Count > 0 ? string.Join(", ", targets.Select(t => t.Name)) : "Kein aktives Gerät";
        }

        // =====================================================================
        // WLAN (NETWORKMANAGER NMCLI — 5 GHz PREFERENCE)
        // =====================================================================
        public static async Task<List<WifiNetworkDto>> ScanWifiAsync(bool rescan)
        {
            var args = new List<string> { "-t", "-f", "ACTIVE,SSID,SIGNAL,FREQ,SECURITY", "dev", "wifi", "list" };
            if (rescan)
            {
                await RunProcessAsync("rfkill", new[] { "unblock", "wifi" }, 2000).ConfigureAwait(false);
                args.Add("--rescan");
                args.Add("yes");
            }

            var (_, outTxt, _) = await RunProcessAsync("nmcli", args, 8000).ConfigureAwait(false);
            var map = new Dictionary<string, WifiNetworkDto>(StringComparer.Ordinal);

            foreach (string rawLine in outTxt.Split('\n', StringSplitOptions.RemoveEmptyEntries))
            {
                // nmcli -t escapes ':' inside SSIDs as '\:'
                var parts = Regex.Split(rawLine.Trim(), @"(?<!\\):");
                if (parts.Length < 5) continue;

                bool active = parts[0].Equals("yes", StringComparison.OrdinalIgnoreCase);
                string ssid = parts[1].Replace(@"\:", ":").Trim();
                if (string.IsNullOrWhiteSpace(ssid)) continue;

                int.TryParse(parts[2], out int sig);
                string freqStr = parts[3];
                bool is5Ghz = freqStr.StartsWith("5", StringComparison.Ordinal);
                string band = is5Ghz ? "5 GHz" : "2,4 GHz";
                string sec = parts[4].Trim();

                if (!map.TryGetValue(ssid, out var existing))
                {
                    map[ssid] = new WifiNetworkDto
                    {
                        Ssid = ssid,
                        Signal = sig,
                        Band = band,
                        Security = sec,
                        IsConnected = active
                    };
                }
                else
                {
                    if (active) existing.IsConnected = true;
                    if (is5Ghz) existing.Band = "5 GHz";
                    if (sig > existing.Signal) existing.Signal = sig;
                }
            }

            return map.Values
                .OrderByDescending(w => w.IsConnected)
                .ThenByDescending(w => w.Band == "5 GHz")
                .ThenByDescending(w => w.Signal)
                .ToList();
        }

        internal static (string[] Args, bool UsesStdin) BuildWifiConnectInvocation(string cleanSsid, string? password)
        {
            if (!string.IsNullOrEmpty(password))
            {
                return (new[] { "--ask", "dev", "wifi", "connect", cleanSsid }, true);
            }
            return (new[] { "dev", "wifi", "connect", cleanSsid }, false);
        }

        public static async Task<(bool Success, string Message)> ConnectWifiAsync(string ssid, string? password)
        {
            if (string.IsNullOrWhiteSpace(ssid))
                return (false, "Bitte WLAN auswählen.");

            string cleanSsid = ssid.Trim();
            if (cleanSsid.StartsWith('-') || cleanSsid.IndexOfAny(new[] { '\0', '\r', '\n' }) >= 0)
                return (false, "Ungültiger WLAN-Name.");

            var (args, usesStdin) = BuildWifiConnectInvocation(cleanSsid, password);
            var (code, outTxt, errTxt) = usesStdin
                ? await RunProcessWithStdinAsync("nmcli", args, (password ?? "") + "\n", 18000).ConfigureAwait(false)
                : await RunProcessAsync("nmcli", args, 18000).ConfigureAwait(false);

            if (code == 0 || outTxt.Contains("successfully activated", StringComparison.OrdinalIgnoreCase))
            {
                return (true, "WLAN verbunden");
            }
            return (false, string.IsNullOrWhiteSpace(errTxt) ? "WLAN-Verbindung fehlgeschlagen" : errTxt.Trim());
        }

        // =====================================================================
        // POWER, WORKDAY 08:00 AM RTC WAKE (WEEKEND-FREE!) & CHROME LAUNCHER
        // =====================================================================
        public static DateTime ComputeNextWorkdayWakeupLocal(DateTime nowLocal)
        {
            DateTime target = new DateTime(nowLocal.Year, nowLocal.Month, nowLocal.Day, 8, 0, 0, DateTimeKind.Unspecified);
            if (nowLocal >= target.AddMinutes(-2))
                target = target.AddDays(1);

            // Am Wochenende (Samstag & Sonntag) nicht automatisch aufwachen -> erst am Montag um 08:00 Uhr!
            while (target.DayOfWeek == DayOfWeek.Saturday || target.DayOfWeek == DayOfWeek.Sunday)
            {
                target = target.AddDays(1);
            }
            return target;
        }

        public static async Task<(bool Success, string Message)> EnterNightSleepUntil0800Async()
        {
            try
            {
                // 1. Pre-Sleep Delta NAS Backup: Secure all daily work to //192.168.188.33/Backups/Unterlagen/Meine_Daten_Backup BEFORE sleeping!
                string backupNote = "";
                try
                {
                    var (bkOk, bkSummary, copied, _) = await DocumentArchiveAndNasEngine
                        .RunDeltaNasBackupAsync("Vor Energiesparmodus", connectTimeoutMs: 5000)
                        .ConfigureAwait(false);
                    backupNote = bkOk ? $" · NAS-Backup: {copied} geändert" : $" · {bkSummary}";
                }
                catch { }

                TimeZoneInfo berlinTz;
                try { berlinTz = TimeZoneInfo.FindSystemTimeZoneById("Europe/Berlin"); }
                catch { berlinTz = TimeZoneInfo.Local; }

                DateTime nowBerlin = TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, berlinTz);
                DateTime targetBerlin = ComputeNextWorkdayWakeupLocal(nowBerlin);
                bool skippedWeekend = (targetBerlin.Date - nowBerlin.Date).TotalDays > 1.5;

                DateTime targetUtc = TimeZoneInfo.ConvertTimeToUtc(targetBerlin, berlinTz);
                long unixEpoch = new DateTimeOffset(targetUtc).ToUnixTimeSeconds();

                _ = Task.Run(async () =>
                {
                    await Task.Delay(600).ConfigureAwait(false);
                    await RunProcessAsync("sudo", new[] { "-n", "/usr/local/bin/fw-power", "sleep", unixEpoch.ToString(CultureInfo.InvariantCulture) }, 10000).ConfigureAwait(false);
                });

                string dayNote = skippedWeekend ? " (Wochenende frei)" : "";
                return (true, $"Energiesparmodus aktiv · Aufwachen am {targetBerlin:dd.MM. HH:mm} Uhr{dayNote}{backupNote}");
            }
            catch (Exception ex)
            {
                return (false, ex.Message);
            }
        }

        public static void TriggerPowerAction(string action)
        {
            string cmd = action == "poweroff" ? "poweroff" : "reboot";
            _ = Task.Run(async () =>
            {
                await Task.Delay(400).ConfigureAwait(false);
                await RunProcessAsync("sudo", new[] { "-n", "/usr/local/bin/fw-power", cmd }, 5000).ConfigureAwait(false);
            });
        }

        public static void OpenFullBrowserWindow(string? url)
        {
            string target = string.IsNullOrWhiteSpace(url) ? "https://mein-seelenfunke.de" : url.Trim();
            if (target.StartsWith('-') || target.IndexOfAny(new[] { '\0', '\r', '\n' }) >= 0)
                return;

            if (!target.StartsWith("http://", StringComparison.OrdinalIgnoreCase) &&
                !target.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
            {
                target = "https://" + target;
            }

            if (!Uri.TryCreate(target, UriKind.Absolute, out var parsedUri) ||
                (parsedUri.Scheme != Uri.UriSchemeHttp && parsedUri.Scheme != Uri.UriSchemeHttps))
            {
                return;
            }

            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = "/usr/bin/google-chrome-stable",
                    UseShellExecute = false,
                    CreateNoWindow = true
                };
                psi.Environment["DISPLAY"] = Environment.GetEnvironmentVariable("DISPLAY") ?? ":0";
                string xauth = Environment.GetEnvironmentVariable("XAUTHORITY") ?? "/home/alina/.Xauthority";
                if (File.Exists(xauth))
                    psi.Environment["XAUTHORITY"] = xauth;
                string xdgRuntime = Environment.GetEnvironmentVariable("XDG_RUNTIME_DIR") ?? "/run/user/1000";
                if (Directory.Exists(xdgRuntime))
                    psi.Environment["XDG_RUNTIME_DIR"] = xdgRuntime;

                psi.ArgumentList.Add("--no-first-run");
                psi.ArgumentList.Add("--no-default-browser-check");
                psi.ArgumentList.Add("--password-store=basic");
                psi.ArgumentList.Add("--enable-gpu-rasterization");
                psi.ArgumentList.Add("--start-maximized");
                psi.ArgumentList.Add("--new-window");
                psi.ArgumentList.Add("--");
                psi.ArgumentList.Add(parsedUri.AbsoluteUri);
                Process.Start(psi);
            }
            catch { }
        }
    }

    // =========================================================================
    // 8-PILLAR FILE MANAGER, TRASH ORIGIN MEMORY & FUNNEL AUTO-SORTER
    // =========================================================================
    public static class LinuxFileManager
    {
        private static string OriginsFile => Path.Combine(LinuxVaultAndStorage.DataDir, "recycle_origins.json");

        private static bool TryResolveRealPath(string rawFullPath, bool allowNonExistentLeaf, out string canonicalPath)
        {
            canonicalPath = "";
            try
            {
                string full = Path.GetFullPath(rawFullPath);
                string? rootPart = Path.GetPathRoot(full);
                if (string.IsNullOrEmpty(rootPart)) return false;

                string relative = full.Substring(rootPart.Length);
                string[] segments = relative.Split(new[] { Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar }, StringSplitOptions.RemoveEmptyEntries);

                string current = rootPart;
                for (int i = 0; i < segments.Length; i++)
                {
                    string seg = segments[i];
                    if (seg is "." or "..") return false;

                    string next = Path.Combine(current, seg);
                    var fi = new FileInfo(next);
                    var di = new DirectoryInfo(next);
                    bool isLink = fi.LinkTarget != null || di.LinkTarget != null;
                    bool exists = fi.Exists || di.Exists;

                    if (isLink)
                    {
                        FileSystemInfo? targetInfo = fi.LinkTarget != null
                            ? fi.ResolveLinkTarget(returnFinalTarget: true)
                            : di.ResolveLinkTarget(returnFinalTarget: true);
                        if (targetInfo == null || (!targetInfo.Exists))
                            return false;
                        current = Path.GetFullPath(targetInfo.FullName);
                    }
                    else if (exists)
                    {
                        current = next;
                    }
                    else
                    {
                        if (i == segments.Length - 1 && allowNonExistentLeaf)
                        {
                            current = next;
                        }
                        else
                        {
                            return false;
                        }
                    }
                }

                canonicalPath = current.Length > 1 ? current.TrimEnd(Path.DirectorySeparatorChar) : current;
                return true;
            }
            catch
            {
                return false;
            }
        }

        private static List<string> GetAllowedCanonicalRoots(bool allowTrashRoot, out string canonicalTrashRoot, out string canonicalDataDir)
        {
            string home = LinuxVaultAndStorage.UserHomeDir;
            string downloadsDir = Path.Combine(home, "Downloads");
            Directory.CreateDirectory(downloadsDir);

            string mdRaw = LinuxVaultAndStorage.MeineDatenRoot;
            string trRaw = LinuxVaultAndStorage.TrashDir;
            string ddRaw = LinuxVaultAndStorage.DataDir;

            canonicalTrashRoot = TryResolveRealPath(trRaw, false, out string trRes) ? trRes : Path.GetFullPath(trRaw).TrimEnd(Path.DirectorySeparatorChar);
            canonicalDataDir = TryResolveRealPath(ddRaw, false, out string ddRes) ? ddRes : Path.GetFullPath(ddRaw).TrimEnd(Path.DirectorySeparatorChar);

            var roots = new List<string>();
            if (TryResolveRealPath(mdRaw, false, out string mdRes)) roots.Add(mdRes);
            else roots.Add(Path.GetFullPath(mdRaw).TrimEnd(Path.DirectorySeparatorChar));

            if (TryResolveRealPath(downloadsDir, false, out string dlRes)) roots.Add(dlRes);
            else roots.Add(Path.GetFullPath(downloadsDir).TrimEnd(Path.DirectorySeparatorChar));

            if (allowTrashRoot)
                roots.Add(canonicalTrashRoot);

            string userName = Environment.UserName;
            var mediaBases = new List<string> { "/media/alina", "/media" };
            if (!string.IsNullOrWhiteSpace(userName))
                mediaBases.Insert(0, $"/media/{userName}");

            foreach (string mediaBase in mediaBases.Distinct(StringComparer.Ordinal))
            {
                if (!Directory.Exists(mediaBase)) continue;
                try
                {
                    foreach (string sub in Directory.GetDirectories(mediaBase))
                    {
                        string leaf = Path.GetFileName(sub);
                        if (string.IsNullOrEmpty(leaf) || leaf.StartsWith('.') || leaf is "c" or "wsl" or "wslg")
                            continue;
                        if (TryResolveRealPath(sub, false, out string usbRes) &&
                            !usbRes.Equals("/etc", StringComparison.Ordinal) &&
                            !usbRes.StartsWith("/etc/", StringComparison.Ordinal))
                        {
                            roots.Add(usbRes);
                        }
                    }
                }
                catch { }
            }

            return roots.Distinct(StringComparer.Ordinal).ToList();
        }

        public static bool TryResolveJailedPath(string? rawPath, bool allowNonExistentLeaf, bool allowTrashRoot, out string canonicalPath)
        {
            canonicalPath = "";
            if (string.IsNullOrWhiteSpace(rawPath) || rawPath.Contains('\0'))
                return false;

            // Reject any dotfile or traversal component in caller-supplied path below known roots
            if (!TryResolveRealPath(rawPath, allowNonExistentLeaf, out string resolved))
                return false;

            var allowedRoots = GetAllowedCanonicalRoots(allowTrashRoot, out string canonicalTrash, out string canonicalDataDir);

            // Explicit defense-in-depth denylist for sensitive system/secret directories
            string realUserHome = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
            foreach (string homeBase in new[] { LinuxVaultAndStorage.UserHomeDir, realUserHome }.Where(x => !string.IsNullOrWhiteSpace(x)))
            {
                string sshDir = Path.GetFullPath(Path.Combine(homeBase, ".ssh")).TrimEnd(Path.DirectorySeparatorChar);
                if (resolved.Equals(sshDir, StringComparison.Ordinal) || resolved.StartsWith(sshDir + Path.DirectorySeparatorChar, StringComparison.Ordinal))
                    return false;
            }
            if (resolved.Equals("/etc", StringComparison.Ordinal) || resolved.StartsWith("/etc/", StringComparison.Ordinal))
                return false;

            // Block ~/.local/share/FunkenwerkOS except TrashDir when allowTrashRoot == true
            if (resolved.Equals(canonicalDataDir, StringComparison.Ordinal) ||
                resolved.StartsWith(canonicalDataDir + Path.DirectorySeparatorChar, StringComparison.Ordinal))
            {
                if (!allowTrashRoot)
                    return false;
                if (!resolved.Equals(canonicalTrash, StringComparison.Ordinal) &&
                    !resolved.StartsWith(canonicalTrash + Path.DirectorySeparatorChar, StringComparison.Ordinal))
                {
                    return false;
                }
            }

            string? matchedRoot = null;
            foreach (string root in allowedRoots)
            {
                if (resolved.Equals(root, StringComparison.Ordinal) ||
                    resolved.StartsWith(root + Path.DirectorySeparatorChar, StringComparison.Ordinal))
                {
                    matchedRoot = root;
                    break;
                }
            }

            if (matchedRoot == null)
                return false;

            if (!resolved.Equals(matchedRoot, StringComparison.Ordinal))
            {
                string rel = Path.GetRelativePath(matchedRoot, resolved);
                string[] relParts = rel.Split(new[] { Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar }, StringSplitOptions.RemoveEmptyEntries);
                foreach (string part in relParts)
                {
                    if (part.StartsWith('.'))
                        return false;
                }
            }

            canonicalPath = resolved;
            return true;
        }

        public static List<(string Label, string Path)> GetQuickRoots()
        {
            string home = LinuxVaultAndStorage.UserHomeDir;

            string md = LinuxVaultAndStorage.MeineDatenRoot;
            string dl = Path.Combine(home, "Downloads");
            Directory.CreateDirectory(dl);

            var list = new List<(string, string)>
            {
                ("✦ Meine Daten", md),
                ("📥 Daten-Trichter", Path.Combine(md, "00_Eingang_Trichter")),
                ("⬇ Downloads", dl),
                ("🗑 Papierkorb", LinuxVaultAndStorage.TrashDir)
            };

            string userName = Environment.UserName;
            var mediaBases = new List<string> { "/media/alina", "/media" };
            if (!string.IsNullOrWhiteSpace(userName))
                mediaBases.Insert(0, $"/media/{userName}");

            foreach (string mediaBase in mediaBases.Distinct(StringComparer.Ordinal))
            {
                if (!Directory.Exists(mediaBase)) continue;
                try
                {
                    foreach (string sub in Directory.GetDirectories(mediaBase))
                    {
                        string leaf = Path.GetFileName(sub);
                        if (string.IsNullOrEmpty(leaf) || leaf.StartsWith('.') || leaf is "c" or "wsl" or "wslg") continue;
                        list.Add(($"💾 USB: {leaf}", sub));
                    }
                }
                catch { }
            }

            return list;
        }

        public static List<FileEntryDto> ListDirectory(string? requestedPath)
        {
            string target;
            if (string.IsNullOrWhiteSpace(requestedPath))
            {
                target = LinuxVaultAndStorage.MeineDatenRoot;
            }
            else if (!TryResolveJailedPath(requestedPath, allowNonExistentLeaf: false, allowTrashRoot: true, out target) || !Directory.Exists(target))
            {
                return new List<FileEntryDto>();
            }

            // Auto-sort 00_Eingang_Trichter if viewing Meine_Daten
            AutoSortTrichter();

            GetAllowedCanonicalRoots(allowTrashRoot: true, out string canonicalTrash, out _);
            bool isTrash = string.Equals(target.TrimEnd('/'), canonicalTrash.TrimEnd('/'), StringComparison.Ordinal);
            var origins = isTrash ? LoadRecycleOrigins() : new Dictionary<string, string>();

            var items = new List<FileEntryDto>();
            try
            {
                foreach (var d in new DirectoryInfo(target).GetDirectories().OrderBy(x => x.Name))
                {
                    if (d.Name.StartsWith('.')) continue;
                    if (!TryResolveJailedPath(d.FullName, allowNonExistentLeaf: false, allowTrashRoot: isTrash, out _)) continue;
                    items.Add(new FileEntryDto
                    {
                        Name = d.Name,
                        FullPath = d.FullName,
                        IsDirectory = true,
                        ModifiedAt = d.LastWriteTime.ToString("dd.MM.yyyy HH:mm"),
                        OriginalPath = origins.GetValueOrDefault(d.Name, "")
                    });
                }

                foreach (var f in new DirectoryInfo(target).GetFiles().OrderBy(x => x.Name))
                {
                    if (f.Name.StartsWith('.')) continue;
                    if (!TryResolveJailedPath(f.FullName, allowNonExistentLeaf: false, allowTrashRoot: isTrash, out _)) continue;
                    items.Add(new FileEntryDto
                    {
                        Name = f.Name,
                        FullPath = f.FullName,
                        IsDirectory = false,
                        SizeBytes = f.Length,
                        SizeFormatted = FormatBytes(f.Length),
                        ModifiedAt = f.LastWriteTime.ToString("dd.MM.yyyy HH:mm"),
                        Extension = f.Extension.ToLowerInvariant(),
                        OriginalPath = origins.GetValueOrDefault(f.Name, "")
                    });
                }
            }
            catch { }

            return items;
        }

        public static void AutoSortTrichter()
        {
            try
            {
                _ = DocumentArchiveAndNasEngine.ProcessTrichterDirectoryAsync();
            }
            catch { }
        }

        public static bool MoveToTrash(string fullPath)
        {
            if (!TryResolveJailedPath(fullPath, allowNonExistentLeaf: false, allowTrashRoot: false, out string safeSource))
                return false;

            var topRoots = GetAllowedCanonicalRoots(allowTrashRoot: false, out _, out _);
            if (topRoots.Any(r => string.Equals(r, safeSource, StringComparison.Ordinal)))
                return false;

            try
            {
                string name = Path.GetFileName(safeSource.TrimEnd('/'));
                if (string.IsNullOrWhiteSpace(name) || name.StartsWith('.')) return false;

                string dest = GetUniquePath(Path.Combine(LinuxVaultAndStorage.TrashDir, name));
                string destLeaf = Path.GetFileName(dest);

                if (File.Exists(safeSource)) File.Move(safeSource, dest);
                else if (Directory.Exists(safeSource)) Directory.Move(safeSource, dest);
                else return false;

                var origins = LoadRecycleOrigins();
                origins[destLeaf] = safeSource;
                SaveRecycleOrigins(origins);
                return true;
            }
            catch
            {
                return false;
            }
        }

        public static bool RestoreFromTrash(string trashItemPath)
        {
            if (!TryResolveJailedPath(trashItemPath, allowNonExistentLeaf: false, allowTrashRoot: true, out string safeTrashItem))
                return false;

            GetAllowedCanonicalRoots(allowTrashRoot: true, out string canonicalTrash, out _);
            if (!safeTrashItem.StartsWith(canonicalTrash + Path.DirectorySeparatorChar, StringComparison.Ordinal))
                return false;

            try
            {
                string leaf = Path.GetFileName(safeTrashItem.TrimEnd('/'));
                if (string.IsNullOrWhiteSpace(leaf) || leaf.StartsWith('.')) return false;

                var origins = LoadRecycleOrigins();
                string candidateOrig = origins.GetValueOrDefault(leaf, Path.Combine(LinuxVaultAndStorage.MeineDatenRoot, leaf));

                string? origParent = Path.GetDirectoryName(candidateOrig);
                if (!string.IsNullOrEmpty(origParent) &&
                    TryResolveJailedPath(origParent, allowNonExistentLeaf: true, allowTrashRoot: false, out string safeParent))
                {
                    Directory.CreateDirectory(safeParent);
                }

                if (!TryResolveJailedPath(candidateOrig, allowNonExistentLeaf: true, allowTrashRoot: false, out string safeDest))
                {
                    safeDest = Path.Combine(LinuxVaultAndStorage.MeineDatenRoot, leaf);
                    if (!TryResolveJailedPath(safeDest, allowNonExistentLeaf: true, allowTrashRoot: false, out safeDest))
                        return false;
                }

                string finalDest = GetUniquePath(safeDest);
                if (File.Exists(safeTrashItem)) File.Move(safeTrashItem, finalDest);
                else if (Directory.Exists(safeTrashItem)) Directory.Move(safeTrashItem, finalDest);
                else return false;

                origins.Remove(leaf);
                SaveRecycleOrigins(origins);
                return true;
            }
            catch
            {
                return false;
            }
        }

        private static string GetUniquePath(string candidate)
        {
            if (!File.Exists(candidate) && !Directory.Exists(candidate)) return candidate;
            string dir = Path.GetDirectoryName(candidate) ?? "";
            string stem = Path.GetFileNameWithoutExtension(candidate);
            string ext = Path.GetExtension(candidate);
            for (int i = 2; i < 999; i++)
            {
                string p = Path.Combine(dir, $"{stem}_{i}{ext}");
                if (!File.Exists(p) && !Directory.Exists(p)) return p;
            }
            return Path.Combine(dir, $"{stem}_{Guid.NewGuid():N}{ext}");
        }

        private static Dictionary<string, string> LoadRecycleOrigins()
        {
            try
            {
                if (File.Exists(OriginsFile))
                    return JsonSerializer.Deserialize<Dictionary<string, string>>(File.ReadAllText(OriginsFile)) ?? new();
            }
            catch { }
            return new();
        }

        private static void SaveRecycleOrigins(Dictionary<string, string> map)
        {
            try
            {
                byte[] utf8 = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(map, new JsonSerializerOptions { WriteIndented = true }));
                LinuxVaultAndStorage.WriteFileAtomically0600(OriginsFile, utf8);
            }
            catch { }
        }

        public static (bool Ok, string FullPath, string FileName, string Message) SaveCameraSnapshot(byte[] jpegBytes, string? folderKey, string? customLabel)
        {
            if (jpegBytes == null || jpegBytes.Length < 256 || jpegBytes.Length > 15 * 1024 * 1024)
                return (false, "", "", "Ungültige Bildgröße.");

            // Strict JPEG magic header verification (FF D8 FF)
            if (jpegBytes[0] != 0xFF || jpegBytes[1] != 0xD8 || jpegBytes[2] != 0xFF)
                return (false, "", "", "Sicherheitsprüfung fehlgeschlagen: Kein gültiges JPEG-Kamerabild.");

            string pillar = string.Equals(folderKey?.Trim(), "trichter", StringComparison.OrdinalIgnoreCase)
                ? "00_Eingang_Trichter"
                : "03_Manufaktur_Produkte";

            string destDir = Path.Combine(LinuxVaultAndStorage.MeineDatenRoot, pillar);
            Directory.CreateDirectory(destDir);

            string cleanTag = Regex.Replace(customLabel ?? "", @"[^A-Za-z0-9_-]", "_").Trim('_');
            if (cleanTag.Length > 36) cleanTag = cleanTag.Substring(0, 36);
            string suffix = string.IsNullOrWhiteSpace(cleanTag) ? "" : $"_{cleanTag}";

            string fileName = $"Kamera_Funkira_{DateTime.Now:yyyyMMdd_HHmmss}{suffix}.jpg";
            string finalPath = GetUniquePath(Path.Combine(destDir, fileName));
            string tmpPath = finalPath + ".tmp";

            try
            {
                File.WriteAllBytes(tmpPath, jpegBytes);
                LinuxVaultAndStorage.TryRestrictFilePermissions(tmpPath);
                File.Move(tmpPath, finalPath, overwrite: true);
                return (true, finalPath, Path.GetFileName(finalPath), $"Kamerabild gespeichert unter {finalPath}");
            }
            catch (Exception ex)
            {
                try { if (File.Exists(tmpPath)) File.Delete(tmpPath); } catch { }
                return (false, "", "", $"Speicherfehler: {ex.Message}");
            }
        }

        private static string FormatBytes(long b)
        {
            if (b < 1024) return $"{b} B";
            if (b < 1024 * 1024) return $"{b / 1024.0:F1} KB";
            if (b < 1024L * 1024 * 1024) return $"{b / (1024.0 * 1024):F1} MB";
            return $"{b / (1024.0 * 1024 * 1024):F2} GB";
        }
    }
}
