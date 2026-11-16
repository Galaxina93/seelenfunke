using System;
using System.IO;
using System.Net;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Threading;
using Microsoft.Win32;
using Renci.SshNet;

namespace FunkenwerkSsh
{
    public static class MiniPcTunnelEngine
    {
        public const string DefaultMiniPcIp = "192.168.188.151";
        public const int DefaultSshPort = 22;
        public const string DefaultSshUser = "alina";
        public const uint LoopbackSmbPort = 17445;

        public static string DefaultKeyPath =>
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh", "id_ed25519_minipc");

        public static string DefaultPinnedHostKeyFile =>
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData), "Funkenwerk", "minipc_hostkey.sha256");

        public static bool VerifyOrPinHostKeyFingerprint(string pinFilePath, byte[] hostKeyBytes)
        {
            if (hostKeyBytes == null || hostKeyBytes.Length < 16 || string.IsNullOrWhiteSpace(pinFilePath))
                return false;

            string actualHex = Convert.ToHexString(SHA256.HashData(hostKeyBytes));

            if (File.Exists(pinFilePath))
            {
                string expectedHex = File.ReadAllText(pinFilePath, Encoding.ASCII).Trim();
                if (expectedHex.Length == 64)
                {
                    return CryptographicOperations.FixedTimeEquals(
                        Encoding.ASCII.GetBytes(expectedHex.ToUpperInvariant()),
                        Encoding.ASCII.GetBytes(actualHex.ToUpperInvariant()));
                }
            }

            string? dir = Path.GetDirectoryName(pinFilePath);
            if (!string.IsNullOrEmpty(dir))
                Directory.CreateDirectory(dir);

            File.WriteAllText(pinFilePath, actualHex, Encoding.ASCII);
            TryLockFileCurrentUserOnly(pinFilePath);
            return true;
        }

        public static bool IsLoopbackBindOnly(string boundHost)
        {
            return string.Equals(boundHost, "127.0.0.1", StringComparison.Ordinal);
        }

        private static void TryLockFileCurrentUserOnly(string path)
        {
            try
            {
                var fi = new FileInfo(path);
                var security = new FileSecurity();
                security.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
                var currentSid = WindowsIdentity.GetCurrent().User;
                if (currentSid != null)
                {
                    security.AddAccessRule(new FileSystemAccessRule(
                        currentSid,
                        FileSystemRights.FullControl,
                        AccessControlType.Allow));
                    fi.SetAccessControl(security);
                }
            }
            catch { }
        }

        private static bool ProbeTcpReachable(string host, int port, int timeoutMs)
        {
            try
            {
                using var tcp = new TcpClient();
                var ar = tcp.BeginConnect(host, port, null, null);
                if (ar.AsyncWaitHandle.WaitOne(timeoutMs) && tcp.Connected)
                {
                    tcp.EndConnect(ar);
                    return true;
                }
            }
            catch { }
            return false;
        }

        public static int RunHeadlessTunnelDaemon()
        {
            using var singleInstance = new Mutex(true, @"Local\FunkenwerkMiniPcSmbTunnelDaemon", out bool createdNew);
            if (!createdNew)
                return 0;

            using var wakeEvent = new AutoResetEvent(false);

            PowerModeChangedEventHandler powerHandler = (_, e) =>
            {
                if (e.Mode == PowerModes.Resume)
                    wakeEvent.Set();
            };
            NetworkAddressChangedEventHandler netHandler = (_, _) => wakeEvent.Set();

            try { SystemEvents.PowerModeChanged += powerHandler; } catch { }
            try { NetworkChange.NetworkAddressChanged += netHandler; } catch { }

            try
            {
                while (true)
                {
                    if (!File.Exists(DefaultKeyPath) || !ProbeTcpReachable(DefaultMiniPcIp, DefaultSshPort, 1500))
                    {
                        wakeEvent.WaitOne(TimeSpan.FromSeconds(10));
                        continue;
                    }

                    SshClient? ssh = null;
                    ForwardedPortLocal? fwd = null;
                    using var faultSignal = new ManualResetEventSlim(false);

                    try
                    {
                        var keyFile = new PrivateKeyFile(DefaultKeyPath);
                        var connInfo = new ConnectionInfo(
                            DefaultMiniPcIp,
                            DefaultSshPort,
                            DefaultSshUser,
                            new PrivateKeyAuthenticationMethod(DefaultSshUser, keyFile))
                        {
                            Timeout = TimeSpan.FromSeconds(6)
                        };

                        ssh = new SshClient(connInfo)
                        {
                            KeepAliveInterval = TimeSpan.FromSeconds(15)
                        };

                        ssh.HostKeyReceived += (_, hkArgs) =>
                        {
                            hkArgs.CanTrust = VerifyOrPinHostKeyFingerprint(DefaultPinnedHostKeyFile, hkArgs.HostKey);
                        };
                        ssh.ErrorOccurred += (_, _) => faultSignal.Set();

                        ssh.Connect();
                        if (!ssh.IsConnected)
                        {
                            wakeEvent.WaitOne(TimeSpan.FromSeconds(5));
                            continue;
                        }

                        const string boundHost = "127.0.0.1";
                        if (!IsLoopbackBindOnly(boundHost))
                            return 2;

                        fwd = new ForwardedPortLocal(boundHost, LoopbackSmbPort, "127.0.0.1", LoopbackSmbPort);
                        fwd.Exception += (_, _) => faultSignal.Set();
                        ssh.AddForwardedPort(fwd);
                        fwd.Start();

                        while (ssh.IsConnected && fwd.IsStarted && !faultSignal.IsSet)
                        {
                            int signaled = WaitHandle.WaitAny(new WaitHandle[] { wakeEvent, faultSignal.WaitHandle }, TimeSpan.FromSeconds(12));
                            if (signaled == 0)
                            {
                                // Resume from standby or Wi-Fi roam -> verify real SSH roundtrip immediately
                                try
                                {
                                    using var cmd = ssh.CreateCommand("true");
                                    cmd.CommandTimeout = TimeSpan.FromSeconds(3);
                                    cmd.Execute();
                                }
                                catch
                                {
                                    break;
                                }
                            }
                        }
                    }
                    catch
                    {
                        // Transient network loss or sleep; loop will cleanly reconnect once reachable
                    }
                    finally
                    {
                        try { fwd?.Stop(); } catch { }
                        try { fwd?.Dispose(); } catch { }
                        try { if (ssh?.IsConnected == true) ssh.Disconnect(); } catch { }
                        try { ssh?.Dispose(); } catch { }
                    }

                    wakeEvent.WaitOne(TimeSpan.FromSeconds(2));
                }
            }
            finally
            {
                try { SystemEvents.PowerModeChanged -= powerHandler; } catch { }
                try { NetworkChange.NetworkAddressChanged -= netHandler; } catch { }
            }
        }
    }
}
