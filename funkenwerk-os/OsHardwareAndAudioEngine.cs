using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using System.Security;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using Microsoft.Win32;

namespace FunkenwerkOS
{
    public sealed class AudioEndpointState
    {
        public string DeviceId { get; set; } = "";
        public string DeviceName { get; set; } = "System-Audio";
        public bool IsTeufelConnected { get; set; }
        public bool IsBluetoothAudio { get; set; }
        public int VolumePercent { get; set; } = 50;
        public bool IsMuted { get; set; }
    }

    public static class OsHardwareAndAudioEngine
    {
        // =====================================================================
        // WIN32 MEMORY, CPU & WINDOW MANAGEMENT INTEROP
        // =====================================================================
        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Auto)]
        private class MEMORYSTATUSEX
        {
            public uint dwLength = (uint)Marshal.SizeOf(typeof(MEMORYSTATUSEX));
            public uint dwMemoryLoad;
            public ulong ullTotalPhys;
            public ulong ullAvailPhys;
            public ulong ullTotalPageFile;
            public ulong ullAvailPageFile;
            public ulong ullTotalVirtual;
            public ulong ullAvailVirtual;
            public ulong ullAvailExtendedVirtual;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct FILETIME
        {
            public uint dwLowDateTime;
            public uint dwHighDateTime;
            public ulong ToUInt64() => ((ulong)dwHighDateTime << 32) | dwLowDateTime;
        }

        [DllImport("kernel32.dll", CharSet = CharSet.Auto, SetLastError = true)]
        private static extern bool GlobalMemoryStatusEx([In, Out] MEMORYSTATUSEX lpBuffer);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool GetSystemTimes(out FILETIME lpIdleTime, out FILETIME lpKernelTime, out FILETIME lpUserTime);

        private delegate bool EnumWindowsProc(IntPtr hWnd, IntPtr lParam);

        [DllImport("user32.dll")]
        private static extern bool EnumWindows(EnumWindowsProc enumProc, IntPtr lParam);

        [DllImport("user32.dll")]
        private static extern bool IsWindowVisible(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern IntPtr GetForegroundWindow();

        [DllImport("user32.dll", CharSet = CharSet.Unicode)]
        private static extern int GetWindowText(IntPtr hWnd, StringBuilder strText, int maxCount);

        [DllImport("user32.dll", CharSet = CharSet.Unicode)]
        private static extern int GetWindowTextLength(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint lpdwProcessId);

        [DllImport("user32.dll")]
        private static extern bool SetForegroundWindow(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);

        [DllImport("user32.dll")]
        private static extern bool IsIconic(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern bool PostMessage(IntPtr hWnd, uint Msg, IntPtr wParam, IntPtr lParam);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern IntPtr OpenProcess(uint dwDesiredAccess, bool bInheritHandle, uint dwProcessId);

        [DllImport("kernel32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
        private static extern bool QueryFullProcessImageName(IntPtr hProcess, uint dwFlags, StringBuilder lpExeName, ref int lpdwSize);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool CloseHandle(IntPtr hObject);

        private const uint PROCESS_QUERY_LIMITED_INFORMATION = 0x1000;
        private const int SW_MINIMIZE = 6;
        private const int SW_RESTORE = 9;
        private const uint WM_CLOSE = 0x0010;
        private const uint WM_SYSCOMMAND = 0x0112;
        private const int SC_MONITORPOWER = 0xF170;

        // Non-blocking background cache for Tray & Taskbar (0 ms UI-thread overhead)
        public static (bool IsOnline, bool IsWifi, string ConnectionKind, string LocalIp) CachedNetworkSummary { get; private set; } = (true, true, "WLAN", "127.0.0.1");
        public static AudioEndpointState CachedAudioState { get; private set; } = new();
        public static bool CachedMicMuted { get; private set; }
        public static List<OsOpenWindowDto> CachedOpenExtWindows { get; private set; } = new();
        private static int _bgRefreshBusy;

        public static void TriggerBackgroundTrayRefresh()
        {
            if (Interlocked.Exchange(ref _bgRefreshBusy, 1) != 0)
                return;

            _ = Task.Run(() =>
            {
                try
                {
                    CachedNetworkSummary = ReadLocalNetworkSummary();
                    CachedAudioState = ReadAudioState();
                    CachedMicMuted = ReadMicrophoneMuteState();
                    CachedOpenExtWindows = EnumerateFunkenwerkOpenWindows();
                }
                catch { }
                finally
                {
                    Interlocked.Exchange(ref _bgRefreshBusy, 0);
                }
            });
        }

        private static ulong _prevIdle;
        private static ulong _prevKernel;
        private static ulong _prevUser;

        // =====================================================================
        // WINDOWS COREAUDIO + IPOLICYCONFIG (SWITCH AUDIO OUTPUT IN 1 CLICK!)
        // =====================================================================
        [ComImport]
        [Guid("BCDE0395-E52F-467C-8E3D-C4579291692E")]
        private class MMDeviceEnumeratorComObject { }

        [ComImport]
        [Guid("870AF99C-171D-4F9E-AF0D-E63DF40C2BC9")]
        private class PolicyConfigClientComObject { }

        [Guid("A95664D2-9614-4F35-A746-DE8DB63617E6"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IMMDeviceEnumerator
        {
            int EnumAudioEndpoints(int dataFlow, int dwStateMask, out IMMDeviceCollection ppDevices);
            int GetDefaultAudioEndpoint(int dataFlow, int role, out IMMDevice ppEndpoint);
        }

        [Guid("0BD7A1BE-7A1A-44DB-8397-CC5392387B5E"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IMMDeviceCollection
        {
            int GetCount(out uint pcDevices);
            int Item(uint nDevice, out IMMDevice ppDevice);
        }

        [Guid("D666063F-1587-4E43-81F1-B948E807363F"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IMMDevice
        {
            int Activate(ref Guid iid, int dwClsCtx, IntPtr pActivationParams, [MarshalAs(UnmanagedType.IUnknown)] out object ppInterface);
            int OpenPropertyStore(int stgmAccess, out IPropertyStore ppProperties);
            int GetId([MarshalAs(UnmanagedType.LPWStr)] out string ppstrId);
            int GetState(out int pdwState);
        }

        [Guid("886d8eeb-8cf2-4446-8d02-cdba1dbdcf99"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IPropertyStore
        {
            int GetCount(out int cProps);
            int GetAt(int iProp, out PROPERTYKEY pkey);
            int GetValue(ref PROPERTYKEY key, out PROPVARIANT pv);
        }

        [Guid("F8679F50-850A-41CF-9C72-430F290290C8"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IPolicyConfig
        {
            int GetMixFormat([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, IntPtr ppFormat);
            int GetDeviceFormat([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, int bDefault, IntPtr ppFormat);
            int ResetDeviceFormat([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName);
            int SetDeviceFormat([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, IntPtr pEndpointFormat, IntPtr mixFormat);
            int GetProcessingPeriod([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, int bDefault, IntPtr pmftDefaultPeriod, IntPtr pmftMinimumPeriod);
            int SetProcessingPeriod([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, IntPtr pmftPeriod);
            int GetShareMode([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, IntPtr pMode);
            int SetShareMode([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, IntPtr mode);
            int GetPropertyValue([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, int bFxStore, IntPtr key, IntPtr pv);
            int SetPropertyValue([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, int bFxStore, IntPtr key, IntPtr pv);
            int SetDefaultEndpoint([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, int role);
            int SetEndpointVisibility([MarshalAs(UnmanagedType.LPWStr)] string pszDeviceName, int bVisible);
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct PROPERTYKEY
        {
            public Guid fmtid;
            public int pid;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct PROPVARIANT
        {
            public ushort vt;
            public ushort wReserved1;
            public ushort wReserved2;
            public ushort wReserved3;
            public IntPtr pwszVal;
            public IntPtr padding;
        }

        [Guid("5CDF2C82-841E-4546-9722-0CF74078229A"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IAudioEndpointVolume
        {
            int RegisterControlChangeNotify(IntPtr pNotify);
            int UnregisterControlChangeNotify(IntPtr pNotify);
            int GetChannelCount(out int pnChannelCount);
            int SetMasterVolumeLevel(float fLevelDB, ref Guid pguidEventContext);
            int SetMasterVolumeLevelScalar(float fLevel, ref Guid pguidEventContext);
            int GetMasterVolumeLevel(out float pfLevelDB);
            int GetMasterVolumeLevelScalar(out float pfLevel);
            int SetChannelVolumeLevel(uint nChannel, float fLevelDB, ref Guid pguidEventContext);
            int SetChannelVolumeLevelScalar(uint nChannel, float fLevel, ref Guid pguidEventContext);
            int GetChannelVolumeLevel(uint nChannel, out float pfLevelDB);
            int GetChannelVolumeLevelScalar(uint nChannel, out float pfLevel);
            int SetMute([MarshalAs(UnmanagedType.Bool)] bool bMute, ref Guid pguidEventContext);
            int GetMute([MarshalAs(UnmanagedType.Bool)] out bool pbMute);
        }

        [Guid("2A07407E-6497-4A18-9787-32F79BD0D98F"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IDeviceTopology
        {
            int GetConnectorCount(out uint pCount);
            int GetConnector(uint nIndex, out IConnector ppConnector);
        }

        [Guid("9C2C4058-23F5-41DE-877A-DF3AF236A09E"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IConnector
        {
            int GetType(out int pType);
            int GetDataFlow(out int pFlow);
            int ConnectTo(IConnector pConnectTo);
            int Disconnect();
            int IsConnected([MarshalAs(UnmanagedType.Bool)] out bool pbConnected);
            int GetConnectedTo(out IConnector ppConTo);
        }

        [Guid("AE2DE0E4-5BCA-4F2D-AA46-5D13F8FDB3A9"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IPart
        {
            int GetName([MarshalAs(UnmanagedType.LPWStr)] out string ppwstrName);
            int GetLocalId(out uint pnId);
            int GetGlobalId([MarshalAs(UnmanagedType.LPWStr)] out string ppwstrGlobalId);
            int GetPartType(out int pPartType);
            int GetSubType(out Guid pSubType);
            int GetControlInterfaceCount(out uint pCount);
            int GetControlInterface(uint nIndex, out IntPtr ppInterfaceDesc);
            int EnumPartsIncoming(out IntPtr ppParts);
            int EnumPartsOutgoing(out IntPtr ppParts);
            int GetTopologyObject(out IDeviceTopology ppTopology);
            int Activate(int dwClsCtx, ref Guid refiid, [MarshalAs(UnmanagedType.IUnknown)] out object ppvObject);
        }

        [Guid("28F54685-06FD-11D2-B27A-00A0C9223196"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IKsControl
        {
            int KsProperty(ref KSPROPERTY Property, uint PropertyLength, IntPtr PropertyData, uint DataLength, out uint BytesReturned);
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct KSPROPERTY
        {
            public Guid Set;
            public uint Id;
            public uint Flags;
        }

        private static readonly Guid KsPropSetIdBtAudio = new("7FA06C40-B8F6-4C7E-8556-E8C33A12E54D");

        private static void TriggerBtAudioOneShotReconnect(string deviceNameFragment)
        {
            try
            {
                var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                // 0 = eRender, 0x0000000F = DEVICE_STATEMASK_ALL (includes UNPLUGGED Bluetooth endpoints!)
                if (enumerator.EnumAudioEndpoints(0, 0x0000000F, out IMMDeviceCollection col) != 0 || col == null)
                    return;

                col.GetCount(out uint count);
                Guid iidTopology = typeof(IDeviceTopology).GUID;
                Guid iidKsControl = typeof(IKsControl).GUID;

                for (uint i = 0; i < count; i++)
                {
                    if (col.Item(i, out IMMDevice dev) != 0 || dev == null) continue;
                    string name = ReadDeviceFriendlyName(dev);
                    bool isMatch = (!string.IsNullOrWhiteSpace(deviceNameFragment) && name.Contains(deviceNameFragment, StringComparison.OrdinalIgnoreCase))
                                || (deviceNameFragment.Contains("Teufel", StringComparison.OrdinalIgnoreCase) && name.Contains("Teufel", StringComparison.OrdinalIgnoreCase));
                    if (!isMatch) continue;

                    var ksProp = new KSPROPERTY
                    {
                        Set = KsPropSetIdBtAudio,
                        Id = 0,   // KSPROPERTY_ONESHOT_RECONNECT
                        Flags = 1 // KSPROPERTY_TYPE_GET
                    };

                    // Try activating IKsControl via IDeviceTopology -> IConnector -> GetConnectedTo -> IPart
                    if (dev.Activate(ref iidTopology, 1, IntPtr.Zero, out object topoObj) == 0 && topoObj is IDeviceTopology topo)
                    {
                        if (topo.GetConnector(0, out IConnector conn) == 0 && conn != null)
                        {
                            if (conn.GetConnectedTo(out IConnector connTo) == 0 && connTo is IPart part)
                            {
                                if (part.Activate(1, ref iidKsControl, out object ksObj) == 0 && ksObj is IKsControl ks)
                                {
                                    ks.KsProperty(ref ksProp, (uint)Marshal.SizeOf<KSPROPERTY>(), IntPtr.Zero, 0, out _);
                                }
                            }
                        }
                    }

                    // Also try direct IKsControl activation on IMMDevice
                    if (dev.Activate(ref iidKsControl, 1, IntPtr.Zero, out object directKsObj) == 0 && directKsObj is IKsControl directKs)
                    {
                        directKs.KsProperty(ref ksProp, (uint)Marshal.SizeOf<KSPROPERTY>(), IntPtr.Zero, 0, out _);
                    }
                }
            }
            catch { }
        }

        private static string ReadDeviceFriendlyName(IMMDevice device)
        {
            try
            {
                if (device.OpenPropertyStore(0, out IPropertyStore store) == 0 && store != null)
                {
                    var pkey = new PROPERTYKEY
                    {
                        fmtid = new Guid("a45c254e-df1c-4efd-8020-67d146a850e0"),
                        pid = 14
                    };
                    if (store.GetValue(ref pkey, out PROPVARIANT pv) == 0 && pv.vt == 31 && pv.pwszVal != IntPtr.Zero)
                    {
                        string? name = Marshal.PtrToStringUni(pv.pwszVal);
                        Marshal.FreeCoTaskMem(pv.pwszVal);
                        if (!string.IsNullOrWhiteSpace(name))
                            return name.Trim();
                    }
                }
            }
            catch { }
            return "Audio-Ausgang";
        }

        public static AudioEndpointState ReadAudioState()
        {
            var state = new AudioEndpointState();
            try
            {
                var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                if (enumerator.GetDefaultAudioEndpoint(0, 1, out IMMDevice device) == 0 && device != null)
                {
                    if (device.GetId(out string devId) == 0 && devId != null)
                        state.DeviceId = devId;

                    string name = ReadDeviceFriendlyName(device);
                    state.DeviceName = name;
                    state.IsTeufelConnected = name.Contains("Teufel", StringComparison.OrdinalIgnoreCase);
                    state.IsBluetoothAudio = state.IsTeufelConnected
                        || name.Contains("Bluetooth", StringComparison.OrdinalIgnoreCase)
                        || name.Contains("Hands-Free", StringComparison.OrdinalIgnoreCase)
                        || name.Contains("Kopfhörer", StringComparison.OrdinalIgnoreCase)
                        || name.Contains("Headphones", StringComparison.OrdinalIgnoreCase);

                    Guid iidVol = typeof(IAudioEndpointVolume).GUID;
                    if (device.Activate(ref iidVol, 23, IntPtr.Zero, out object volObj) == 0 && volObj is IAudioEndpointVolume epVol)
                    {
                        if (epVol.GetMasterVolumeLevelScalar(out float scalar) == 0)
                            state.VolumePercent = Math.Clamp((int)Math.Round(scalar * 100.0f), 0, 100);
                        if (epVol.GetMute(out bool muted) == 0)
                            state.IsMuted = muted;
                    }
                }
            }
            catch { }

            return state;
        }

        public static List<AudioOutputDeviceDto> ListAudioOutputDevices()
        {
            var result = new List<AudioOutputDeviceDto>();
            try
            {
                var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                string defaultId = "";
                if (enumerator.GetDefaultAudioEndpoint(0, 1, out IMMDevice defDev) == 0 && defDev != null)
                {
                    defDev.GetId(out defaultId);
                }

                // eRender = 0, DEVICE_STATE_ACTIVE = 1
                if (enumerator.EnumAudioEndpoints(0, 1, out IMMDeviceCollection col) == 0 && col != null)
                {
                    col.GetCount(out uint count);
                    for (uint i = 0; i < count; i++)
                    {
                        if (col.Item(i, out IMMDevice dev) == 0 && dev != null)
                        {
                            dev.GetId(out string id);
                            string name = ReadDeviceFriendlyName(dev);
                            result.Add(new AudioOutputDeviceDto
                            {
                                DeviceId = id ?? "",
                                Name = name,
                                IsDefault = string.Equals(id, defaultId, StringComparison.OrdinalIgnoreCase)
                            });
                        }
                    }
                }
            }
            catch { }

            return result
                .OrderByDescending(d => d.IsTeufel)
                .ThenByDescending(d => d.IsDefault)
                .ThenBy(d => d.Name)
                .ToList();
        }

        public static List<AudioOutputDeviceDto> ListAudioInputMicrophones()
        {
            var result = new List<AudioOutputDeviceDto>();
            try
            {
                var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                string defaultId = "";
                // eCapture = 1, eCommunications = 2
                if (enumerator.GetDefaultAudioEndpoint(1, 2, out IMMDevice defDev) == 0 && defDev != null)
                {
                    defDev.GetId(out defaultId);
                }

                if (enumerator.EnumAudioEndpoints(1, 1, out IMMDeviceCollection col) == 0 && col != null)
                {
                    col.GetCount(out uint count);
                    for (uint i = 0; i < count; i++)
                    {
                        if (col.Item(i, out IMMDevice dev) == 0 && dev != null)
                        {
                            dev.GetId(out string id);
                            string name = ReadDeviceFriendlyName(dev);
                            result.Add(new AudioOutputDeviceDto
                            {
                                DeviceId = id ?? "",
                                Name = name,
                                IsDefault = string.Equals(id, defaultId, StringComparison.OrdinalIgnoreCase)
                            });
                        }
                    }
                }
            }
            catch { }

            return result.OrderByDescending(d => d.IsDefault).ThenBy(d => d.Name).ToList();
        }

        public static bool ReadMicrophoneMuteState()
        {
            try
            {
                var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                if (enumerator.GetDefaultAudioEndpoint(1, 2, out IMMDevice device) == 0 && device != null)
                {
                    Guid iidVol = typeof(IAudioEndpointVolume).GUID;
                    if (device.Activate(ref iidVol, 23, IntPtr.Zero, out object volObj) == 0 && volObj is IAudioEndpointVolume epVol)
                    {
                        if (epVol.GetMute(out bool muted) == 0)
                            return muted;
                    }
                }
            }
            catch { }
            return false;
        }

        public static bool ToggleMicrophoneMute()
        {
            try
            {
                var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                if (enumerator.GetDefaultAudioEndpoint(1, 2, out IMMDevice device) == 0 && device != null)
                {
                    Guid iidVol = typeof(IAudioEndpointVolume).GUID;
                    if (device.Activate(ref iidVol, 23, IntPtr.Zero, out object volObj) == 0 && volObj is IAudioEndpointVolume epVol)
                    {
                        if (epVol.GetMute(out bool muted) == 0)
                        {
                            Guid ctx = Guid.Empty;
                            epVol.SetMute(!muted, ref ctx);
                            return !muted;
                        }
                    }
                }
            }
            catch { }
            return false;
        }

        public static bool SetDefaultAudioOutputDevice(string deviceId)
        {
            if (string.IsNullOrWhiteSpace(deviceId)) return false;
            try
            {
                var policy = (IPolicyConfig)new PolicyConfigClientComObject();
                policy.SetDefaultEndpoint(deviceId, 0); // eConsole
                policy.SetDefaultEndpoint(deviceId, 1); // eMultimedia
                policy.SetDefaultEndpoint(deviceId, 2); // eCommunications
                return true;
            }
            catch
            {
                return false;
            }
        }

        public static bool SetSystemVolumeExact(int volume0To100)
        {
            int clamped = Math.Clamp(volume0To100, 0, 100);
            try
            {
                var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                if (enumerator.GetDefaultAudioEndpoint(0, 1, out IMMDevice device) == 0 && device != null)
                {
                    Guid iidVol = typeof(IAudioEndpointVolume).GUID;
                    if (device.Activate(ref iidVol, 23, IntPtr.Zero, out object volObj) == 0 && volObj is IAudioEndpointVolume epVol)
                    {
                        Guid ctx = Guid.Empty;
                        float scalar = clamped / 100.0f;
                        epVol.SetMasterVolumeLevelScalar(scalar, ref ctx);
                        if (clamped > 0)
                            epVol.SetMute(false, ref ctx);
                        return true;
                    }
                }
            }
            catch { }
            return false;
        }

        public static bool ToggleSystemMute()
        {
            try
            {
                var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                if (enumerator.GetDefaultAudioEndpoint(0, 1, out IMMDevice device) == 0 && device != null)
                {
                    Guid iidVol = typeof(IAudioEndpointVolume).GUID;
                    if (device.Activate(ref iidVol, 23, IntPtr.Zero, out object volObj) == 0 && volObj is IAudioEndpointVolume epVol)
                    {
                        if (epVol.GetMute(out bool muted) == 0)
                        {
                            Guid ctx = Guid.Empty;
                            epVol.SetMute(!muted, ref ctx);
                            return !muted;
                        }
                    }
                }
            }
            catch { }
            return false;
        }

        // =====================================================================
        // NATIVE WLANAPI.DLL & WINDOWS 11 24H2 LOCATION CONSENT UNLOCKER
        // =====================================================================
        [DllImport("wlanapi.dll", SetLastError = true)]
        private static extern uint WlanOpenHandle(uint dwClientVersion, IntPtr pReserved, out uint pdwNegotiatedVersion, out IntPtr phClientHandle);

        [DllImport("wlanapi.dll", SetLastError = true)]
        private static extern uint WlanCloseHandle(IntPtr hClientHandle, IntPtr pReserved);

        [DllImport("wlanapi.dll", SetLastError = true)]
        private static extern uint WlanEnumInterfaces(IntPtr hClientHandle, IntPtr pReserved, out IntPtr ppInterfaceList);

        [DllImport("wlanapi.dll", SetLastError = true)]
        private static extern uint WlanScan(IntPtr hClientHandle, ref Guid pInterfaceGuid, IntPtr pDot11Ssid, IntPtr pIeData, IntPtr pReserved);

        [DllImport("wlanapi.dll", SetLastError = true)]
        private static extern uint WlanGetAvailableNetworkList(IntPtr hClientHandle, ref Guid pInterfaceGuid, uint dwFlags, IntPtr pReserved, out IntPtr ppAvailableNetworkList);

        [DllImport("wlanapi.dll", SetLastError = true)]
        private static extern void WlanFreeMemory(IntPtr pMemory);

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private struct WLAN_INTERFACE_INFO
        {
            public Guid InterfaceGuid;
            [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 256)]
            public string strInterfaceDescription;
            public int isState;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct DOT11_SSID
        {
            public uint uSSIDLength;
            [MarshalAs(UnmanagedType.ByValArray, SizeConst = 32)]
            public byte[] ucSSID;
        }

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private struct WLAN_AVAILABLE_NETWORK
        {
            [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 256)]
            public string strProfileName;
            public DOT11_SSID dot11Ssid;
            public int dot11BssType;
            public uint uNumberOfBssids;
            [MarshalAs(UnmanagedType.Bool)]
            public bool bNetworkConnectable;
            public uint wlanNotConnectableReason;
            public uint uNumberOfPhyTypes;
            [MarshalAs(UnmanagedType.ByValArray, SizeConst = 8)]
            public uint[] dot11PhyTypes;
            [MarshalAs(UnmanagedType.Bool)]
            public bool bMorePhyTypes;
            public uint wlanSignalQuality;
            [MarshalAs(UnmanagedType.Bool)]
            public bool bSecurityEnabled;
            public uint dot11DefaultAuthAlgorithm;
            public uint dot11DefaultCipherAlgorithm;
            public uint dwFlags;
            public uint dwReserved;
        }

        [DllImport("advapi32.dll", SetLastError = true)]
        private static extern bool OpenProcessToken(IntPtr ProcessHandle, uint DesiredAccess, out IntPtr TokenHandle);

        [DllImport("advapi32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
        private static extern bool LookupPrivilegeValue(string? lpSystemName, string lpName, out long lpLuid);

        [DllImport("advapi32.dll", SetLastError = true)]
        private static extern bool AdjustTokenPrivileges(IntPtr TokenHandle, bool DisableAllPrivileges, ref TOKEN_PRIVILEGES_ LuidState, uint BufferLength, IntPtr PreviousState, IntPtr ReturnLength);

        [StructLayout(LayoutKind.Sequential, Pack = 4)]
        private struct TOKEN_PRIVILEGES_
        {
            public uint PrivilegeCount;
            public long Luid;
            public uint Attributes;
        }

        private static void EnableProcessPrivilege(string privilegeName)
        {
            try
            {
                if (OpenProcessToken(Process.GetCurrentProcess().Handle, 0x0020 | 0x0008, out IntPtr hToken))
                {
                    try
                    {
                        if (LookupPrivilegeValue(null, privilegeName, out long luid))
                        {
                            var tp = new TOKEN_PRIVILEGES_ { PrivilegeCount = 1, Luid = luid, Attributes = 0x00000002 };
                            AdjustTokenPrivileges(hToken, false, ref tp, 0, IntPtr.Zero, IntPtr.Zero);
                        }
                    }
                    finally
                    {
                        CloseHandle(hToken);
                    }
                }
            }
            catch { }
        }

        private static void UnlockCapabilityKey(string subKeyPath, Dictionary<string, int> dwordValues)
        {
            try
            {
                using (var takeKey = Registry.LocalMachine.OpenSubKey(
                    subKeyPath,
                    Microsoft.Win32.RegistryKeyPermissionCheck.ReadWriteSubTree,
                    System.Security.AccessControl.RegistryRights.TakeOwnership))
                {
                    if (takeKey != null)
                    {
                        var acl = takeKey.GetAccessControl();
                        var admins = new System.Security.Principal.SecurityIdentifier(System.Security.Principal.WellKnownSidType.BuiltinAdministratorsSid, null);
                        acl.SetOwner(admins);
                        takeKey.SetAccessControl(acl);
                    }
                }

                using (var aclKey = Registry.LocalMachine.OpenSubKey(
                    subKeyPath,
                    Microsoft.Win32.RegistryKeyPermissionCheck.ReadWriteSubTree,
                    System.Security.AccessControl.RegistryRights.ChangePermissions | System.Security.AccessControl.RegistryRights.ReadKey))
                {
                    if (aclKey != null)
                    {
                        var acl = aclKey.GetAccessControl();
                        var admins = new System.Security.Principal.SecurityIdentifier(System.Security.Principal.WellKnownSidType.BuiltinAdministratorsSid, null);
                        acl.AddAccessRule(new System.Security.AccessControl.RegistryAccessRule(
                            admins,
                            System.Security.AccessControl.RegistryRights.FullControl,
                            System.Security.AccessControl.InheritanceFlags.ContainerInherit | System.Security.AccessControl.InheritanceFlags.ObjectInherit,
                            System.Security.AccessControl.PropagationFlags.None,
                            System.Security.AccessControl.AccessControlType.Allow));
                        aclKey.SetAccessControl(acl);
                    }
                }

                using (var writeKey = Registry.LocalMachine.OpenSubKey(subKeyPath, writable: true))
                {
                    if (writeKey != null)
                    {
                        foreach (var kv in dwordValues)
                            writeKey.SetValue(kv.Key, kv.Value, RegistryValueKind.DWord);
                    }
                }
            }
            catch { }
        }

        private static bool _wirelessConsentEnsured;

        public static void EnsureWirelessServicesAndLocationConsent()
        {
            if (_wirelessConsentEnsured) return;
            _wirelessConsentEnsured = true;

            try
            {
                long nowFileTime = DateTime.UtcNow.ToFileTime();
                // Windows 11 24H2 requires Location + WifiData consent to allow WLAN scanning
                string[] consentCapabilities = { "location", "wifiData", "bluetooth", "bluetoothSync" };
                string[] subPaths = { "", @"\NonPackaged" };
                foreach (string cap in consentCapabilities)
                {
                    foreach (string sub in subPaths)
                    {
                        try
                        {
                            using var hkcu = Registry.CurrentUser.CreateSubKey($@"Software\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\ConsentStore\{cap}{sub}", writable: true);
                            hkcu?.SetValue("Value", "Allow", RegistryValueKind.String);
                            hkcu?.SetValue("LastSetTime", nowFileTime, RegistryValueKind.QWord);
                        }
                        catch { }

                        try
                        {
                            using var hklm = Registry.LocalMachine.CreateSubKey($@"SOFTWARE\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\ConsentStore\{cap}{sub}", writable: true);
                            hklm?.SetValue("Value", "Allow", RegistryValueKind.String);
                            hklm?.SetValue("LastSetTime", nowFileTime, RegistryValueKind.QWord);
                        }
                        catch { }
                    }
                }

                try
                {
                    using var locPol = Registry.LocalMachine.CreateSubKey(@"SOFTWARE\Policies\Microsoft\Windows\LocationAndSensors", writable: true);
                    locPol?.SetValue("DisableLocation", 0, RegistryValueKind.DWord);
                    locPol?.SetValue("DisableWindowsLocationProvider", 0, RegistryValueKind.DWord);

                    // Prevent legacy Windows Device Metadata / DriverSearching hang ("Gerät wird installiert...")
                    using var devMeta = Registry.LocalMachine.CreateSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\Device Metadata", writable: true);
                    devMeta?.SetValue("PreventDeviceMetadataFromNetwork", 1, RegistryValueKind.DWord);
                    using var devMetaPol = Registry.LocalMachine.CreateSubKey(@"SOFTWARE\Policies\Microsoft\Windows\DeviceMetadata", writable: true);
                    devMetaPol?.SetValue("PreventDeviceMetadataFromNetwork", 1, RegistryValueKind.DWord);
                    using var drvSearch = Registry.LocalMachine.CreateSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\DriverSearching", writable: true);
                    drvSearch?.SetValue("SearchOrderConfig", 0, RegistryValueKind.DWord);
                }
                catch { }

                // Check if Windows 11 24H2 Capabilities\wifiData still enforces AccessCheckSupported=1
                bool wifiDataUnlocked = false;
                try
                {
                    using var capCheck = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\Capabilities\wifiData", writable: false);
                    int userConsentReq = Convert.ToInt32(capCheck?.GetValue("UserConsentRequired", 1) ?? 1);
                    wifiDataUnlocked = (userConsentReq == 0);
                }
                catch { }

                if (!wifiDataUnlocked)
                {
                    EnableProcessPrivilege("SeTakeOwnershipPrivilege");
                    EnableProcessPrivilege("SeRestorePrivilege");

                    UnlockCapabilityKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\Capabilities\location", new Dictionary<string, int>
                    {
                        ["AllUsersConsentRequiredForMua"] = 0,
                        ["InitSystemGlobalConsentDenied"] = 0,
                        ["InitUserGlobalConsentDenied"] = 0,
                        ["PerAppConsentForFullTrustApps"] = 0,
                        ["UserConsentPromptRequired"] = 0,
                        ["UserConsentRequired"] = 0
                    });

                    UnlockCapabilityKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\Capabilities\wifiData", new Dictionary<string, int>
                    {
                        ["AppLaunchAccessCheckRequired"] = 0,
                        ["PerAppConsentForFullTrustApps"] = 0,
                        ["UserConsentPromptRequired"] = 0,
                        ["UserConsentRequired"] = 0
                    });

                    UnlockCapabilityKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\Capabilities\wifiData\CapabilityHandlers\{16A78C67-DCE5-45E9-A45E-7A051B50C09E}", new Dictionary<string, int>
                    {
                        ["AccessCheckSupported"] = 0
                    });

                    RunProcessCapture("powershell.exe", "-NoProfile -NonInteractive -Command \"Restart-Service -Name camsvc -Force -ErrorAction SilentlyContinue; Start-Service -Name WlanSvc,bthserv,BTAGService -ErrorAction SilentlyContinue\"");
                }
            }
            catch { }
        }

        // =====================================================================
        // WLAN SCANNER & DIRECT CONNECTOR (BOTTOM-RIGHT WIFI FLYOUT)
        // =====================================================================
        public static async Task<(string ActiveSsid, List<WifiNetworkDto> Networks)> ScanWifiNetworksAsync()
        {
            return await Task.Run(() =>
            {
                EnsureWirelessServicesAndLocationConsent();

                string activeSsid = "";
                var map = new Dictionary<string, WifiNetworkDto>(StringComparer.OrdinalIgnoreCase);

                // 1. Hardware Scan & Enumeration via Native wlanapi.dll
                IntPtr clientHandle = IntPtr.Zero;
                IntPtr ifaceListPtr = IntPtr.Zero;
                try
                {
                    if (WlanOpenHandle(2, IntPtr.Zero, out _, out clientHandle) == 0 && clientHandle != IntPtr.Zero)
                    {
                        if (WlanEnumInterfaces(clientHandle, IntPtr.Zero, out ifaceListPtr) == 0 && ifaceListPtr != IntPtr.Zero)
                        {
                            int count = Marshal.ReadInt32(ifaceListPtr, 0);
                            IntPtr itemPtr = IntPtr.Add(ifaceListPtr, 8);
                            int ifaceSize = Marshal.SizeOf<WLAN_INTERFACE_INFO>();

                            var guids = new List<Guid>();
                            for (int i = 0; i < count; i++)
                            {
                                var info = Marshal.PtrToStructure<WLAN_INTERFACE_INFO>(IntPtr.Add(itemPtr, i * ifaceSize));
                                guids.Add(info.InterfaceGuid);
                                Guid g = info.InterfaceGuid;
                                WlanScan(clientHandle, ref g, IntPtr.Zero, IntPtr.Zero, IntPtr.Zero);
                            }

                            if (guids.Count > 0)
                                Thread.Sleep(600);

                            foreach (Guid ifaceGuid in guids)
                            {
                                Guid g = ifaceGuid;
                                if (WlanGetAvailableNetworkList(clientHandle, ref g, 0, IntPtr.Zero, out IntPtr netListPtr) == 0 && netListPtr != IntPtr.Zero)
                                {
                                    try
                                    {
                                        int netCount = Marshal.ReadInt32(netListPtr, 0);
                                        IntPtr netBase = IntPtr.Add(netListPtr, 8);
                                        int netSize = Marshal.SizeOf<WLAN_AVAILABLE_NETWORK>();

                                        for (int n = 0; n < netCount; n++)
                                        {
                                            var net = Marshal.PtrToStructure<WLAN_AVAILABLE_NETWORK>(IntPtr.Add(netBase, n * netSize));
                                            if (net.dot11Ssid.uSSIDLength == 0 || net.dot11Ssid.uSSIDLength > 32 || net.dot11Ssid.ucSSID == null)
                                                continue;

                                            string ssid = Encoding.UTF8.GetString(net.dot11Ssid.ucSSID, 0, (int)net.dot11Ssid.uSSIDLength).Trim();
                                            if (string.IsNullOrWhiteSpace(ssid)) continue;

                                            bool isConn = (net.dwFlags & 1) != 0;
                                            bool hasProf = (net.dwFlags & 2) != 0 || !string.IsNullOrWhiteSpace(net.strProfileName);
                                            if (isConn && string.IsNullOrEmpty(activeSsid))
                                                activeSsid = ssid;

                                            string sec = !net.bSecurityEnabled ? "Offen" : net.dot11DefaultAuthAlgorithm switch
                                            {
                                                1 => "Offen",
                                                9 or 10 or 11 => "WPA3",
                                                7 or 4 => "WPA2",
                                                _ => "WPA2"
                                            };

                                            int sig = Math.Clamp((int)net.wlanSignalQuality, 1, 100);

                                            if (!map.TryGetValue(ssid, out var existing))
                                            {
                                                map[ssid] = new WifiNetworkDto
                                                {
                                                    Ssid = ssid,
                                                    Security = sec,
                                                    SignalPercent = sig,
                                                    IsConnected = isConn,
                                                    HasSavedProfile = hasProf
                                                };
                                            }
                                            else
                                            {
                                                if (isConn) existing.IsConnected = true;
                                                if (hasProf) existing.HasSavedProfile = true;
                                                if (sig > existing.SignalPercent) existing.SignalPercent = sig;
                                                if (sec == "WPA3") existing.Security = "WPA3";
                                            }
                                        }
                                    }
                                    finally
                                    {
                                        WlanFreeMemory(netListPtr);
                                    }
                                }
                            }
                        }
                    }
                }
                catch { }
                finally
                {
                    if (ifaceListPtr != IntPtr.Zero) WlanFreeMemory(ifaceListPtr);
                    if (clientHandle != IntPtr.Zero) WlanCloseHandle(clientHandle, IntPtr.Zero);
                }

                // 2. Fallback / Supplement via netsh wlan
                try
                {
                    string ifaceOut = RunProcessCapture("netsh.exe", "wlan show interfaces");
                    foreach (string rawLine in ifaceOut.Split('\n'))
                    {
                        string line = rawLine.Trim();
                        if (line.StartsWith("SSID", StringComparison.OrdinalIgnoreCase) && !line.StartsWith("BSSID", StringComparison.OrdinalIgnoreCase))
                        {
                            int idx = line.IndexOf(':');
                            if (idx > 0)
                            {
                                string parsed = line.Substring(idx + 1).Trim();
                                if (!string.IsNullOrWhiteSpace(parsed))
                                {
                                    activeSsid = parsed;
                                    break;
                                }
                            }
                        }
                    }

                    string netOut = RunProcessCapture("netsh.exe", "wlan show networks mode=bssid");
                    string currentSsid = "";
                    string currentSec = "WPA2";

                    foreach (string rawLine in netOut.Split('\n'))
                    {
                        string line = rawLine.Trim();
                        if (Regex.IsMatch(line, @"^SSID\s+\d+\s*:"))
                        {
                            int idx = line.IndexOf(':');
                            currentSsid = idx > 0 ? line.Substring(idx + 1).Trim() : "";
                            currentSec = "WPA2";
                            if (!string.IsNullOrWhiteSpace(currentSsid) && !map.ContainsKey(currentSsid))
                            {
                                map[currentSsid] = new WifiNetworkDto
                                {
                                    Ssid = currentSsid,
                                    Security = currentSec,
                                    SignalPercent = 65,
                                    IsConnected = string.Equals(currentSsid, activeSsid, StringComparison.OrdinalIgnoreCase)
                                };
                            }
                        }
                        else if (!string.IsNullOrEmpty(currentSsid) && (line.StartsWith("Authentifizierung", StringComparison.OrdinalIgnoreCase) || line.StartsWith("Authentication", StringComparison.OrdinalIgnoreCase)))
                        {
                            int idx = line.IndexOf(':');
                            if (idx > 0 && map.TryGetValue(currentSsid, out var item))
                                item.Security = line.Substring(idx + 1).Trim();
                        }
                        else if (!string.IsNullOrEmpty(currentSsid) && line.StartsWith("Signal", StringComparison.OrdinalIgnoreCase))
                        {
                            var m = Regex.Match(line, @"(\d+)\s*%");
                            if (m.Success && int.TryParse(m.Groups[1].Value, out int sig) && map.TryGetValue(currentSsid, out var item))
                            {
                                if (sig > item.SignalPercent || item.SignalPercent == 65)
                                    item.SignalPercent = Math.Clamp(sig, 1, 100);
                            }
                        }
                    }
                    string profOut = RunProcessCapture("netsh.exe", "wlan show profiles");
                    foreach (string rawLine in profOut.Split('\n'))
                    {
                        string line = rawLine.Trim();
                        if (line.Contains("Profil", StringComparison.OrdinalIgnoreCase) || line.Contains("Profile", StringComparison.OrdinalIgnoreCase))
                        {
                            int idx = line.LastIndexOf(':');
                            if (idx > 0 && idx + 1 < line.Length)
                            {
                                string profName = line.Substring(idx + 1).Trim();
                                if (!string.IsNullOrWhiteSpace(profName))
                                {
                                    if (map.TryGetValue(profName, out var existing))
                                    {
                                        existing.HasSavedProfile = true;
                                    }
                                    else
                                    {
                                        map[profName] = new WifiNetworkDto
                                        {
                                            Ssid = profName,
                                            Security = "WPA2/WPA3",
                                            SignalPercent = string.Equals(profName, activeSsid, StringComparison.OrdinalIgnoreCase) ? 100 : 50,
                                            IsConnected = string.Equals(profName, activeSsid, StringComparison.OrdinalIgnoreCase),
                                            HasSavedProfile = true
                                        };
                                    }
                                }
                            }
                        }
                    }
                }
                catch { }

                if (!string.IsNullOrWhiteSpace(activeSsid) && map.TryGetValue(activeSsid, out var activeDto))
                    activeDto.IsConnected = true;

                var ordered = map.Values
                    .OrderByDescending(n => n.IsConnected)
                    .ThenByDescending(n => n.HasSavedProfile)
                    .ThenByDescending(n => n.SignalPercent)
                    .Take(18)
                    .ToList();

                return (activeSsid, ordered);
            });
        }

        private static string BuildWlanProfileXml(string ssid, string? password, string authMode)
        {
            string escapedSsid = SecurityElement.Escape(ssid) ?? ssid;
            string hexSsid = Convert.ToHexString(Encoding.UTF8.GetBytes(ssid));
            string escapedPwd = SecurityElement.Escape(password ?? "") ?? "";

            string authBlock = authMode switch
            {
                "OPEN" => "<authEncryption><authentication>open</authentication><encryption>none</encryption><useOneX>false</useOneX></authEncryption>",
                "WPA3SAE" => $"<authEncryption><authentication>WPA3SAE</authentication><encryption>AES</encryption><useOneX>false</useOneX><transitionMode xmlns=\"http://www.microsoft.com/networking/WLAN/profile/v4\">true</transitionMode></authEncryption><sharedKey><keyType>passPhrase</keyType><protected>false</protected><keyMaterial>{escapedPwd}</keyMaterial></sharedKey>",
                _ => $"<authEncryption><authentication>WPA2PSK</authentication><encryption>AES</encryption><useOneX>false</useOneX></authEncryption><sharedKey><keyType>passPhrase</keyType><protected>false</protected><keyMaterial>{escapedPwd}</keyMaterial></sharedKey>"
            };

            return $@"<?xml version=""1.0""?>
<WLANProfile xmlns=""http://www.microsoft.com/networking/WLAN/profile/v1"">
    <name>{escapedSsid}</name>
    <SSIDConfig>
        <SSID>
            <hex>{hexSsid}</hex>
            <name>{escapedSsid}</name>
        </SSID>
    </SSIDConfig>
    <connectionType>ESS</connectionType>
    <connectionMode>auto</connectionMode>
    <MSM>
        <security>
            {authBlock}
        </security>
    </MSM>
</WLANProfile>";
        }

        private static bool IsWifiConnectedToSsid(string targetSsid)
        {
            try
            {
                string ifaceOut = RunProcessCapture("netsh.exe", "wlan show interfaces");
                foreach (string rawLine in ifaceOut.Split('\n'))
                {
                    string line = rawLine.Trim();
                    if (line.StartsWith("SSID", StringComparison.OrdinalIgnoreCase) && !line.StartsWith("BSSID", StringComparison.OrdinalIgnoreCase))
                    {
                        int idx = line.IndexOf(':');
                        if (idx > 0)
                        {
                            string cur = line.Substring(idx + 1).Trim();
                            if (string.Equals(cur, targetSsid, StringComparison.OrdinalIgnoreCase))
                                return true;
                        }
                    }
                }
            }
            catch { }
            return false;
        }

        public static async Task<(bool Success, string Message)> ConnectWifiNetworkAsync(string ssid, string? password, bool isOpenNetwork, string? securityHint = null)
        {
            if (string.IsNullOrWhiteSpace(ssid))
                return (false, "Ungültige SSID.");

            return await Task.Run(() =>
            {
                EnsureWirelessServicesAndLocationConsent();
                string cleanSsid = ssid.Replace("\"", "");
                string tempXml = Path.Combine(Path.GetTempPath(), $"fw_wlan_{Guid.NewGuid():N}.xml");
                try
                {
                    // If no password supplied and profile already saved on system, try direct connect first
                    if (string.IsNullOrEmpty(password) && !isOpenNetwork)
                    {
                        string directOut = RunProcessCapture("netsh.exe", $"wlan connect name=\"{cleanSsid}\" ssid=\"{cleanSsid}\"");
                        for (int i = 0; i < 6; i++)
                        {
                            Thread.Sleep(400);
                            if (IsWifiConnectedToSsid(ssid))
                                return (true, $"Verbunden mit {ssid}");
                        }
                        if (directOut.Contains("erfolgreich", StringComparison.OrdinalIgnoreCase) || directOut.Contains("successfully", StringComparison.OrdinalIgnoreCase))
                            return (true, $"Verbunden mit {ssid}");
                        return (false, "Bitte WLAN-Passwort eingeben.");
                    }

                    bool isWpa3Hint = !string.IsNullOrWhiteSpace(securityHint) && securityHint.Contains("WPA3", StringComparison.OrdinalIgnoreCase);
                    string[] modesToTry = isOpenNetwork
                        ? new[] { "OPEN" }
                        : (isWpa3Hint ? new[] { "WPA3SAE", "WPA2PSK" } : new[] { "WPA2PSK", "WPA3SAE" });

                    foreach (string mode in modesToTry)
                    {
                        string xml = BuildWlanProfileXml(ssid, password, mode);
                        File.WriteAllText(tempXml, xml, new UTF8Encoding(false));
                        RunProcessCapture("netsh.exe", $"wlan add profile filename=\"{tempXml}\" user=all");
                        RunProcessCapture("netsh.exe", $"wlan connect name=\"{cleanSsid}\" ssid=\"{cleanSsid}\"");

                        for (int wait = 0; wait < 7; wait++)
                        {
                            Thread.Sleep(450);
                            if (IsWifiConnectedToSsid(ssid))
                                return (true, $"Verbunden mit {ssid}");
                        }
                    }

                    return (false, "Verbindung fehlgeschlagen (Passwort prüfen).");
                }
                catch (Exception ex)
                {
                    return (false, ex.Message);
                }
                finally
                {
                    try
                    {
                        if (File.Exists(tempXml))
                        {
                            byte[] zeros = new byte[512];
                            File.WriteAllBytes(tempXml, zeros);
                            File.Delete(tempXml);
                        }
                    }
                    catch { }
                }
            });
        }

        // =====================================================================
        // NATIVE BLUETOOTHAPIS.DLL DISCOVERY, PAIRING & AUDIO CONNECTION
        // =====================================================================
        [StructLayout(LayoutKind.Sequential)]
        private struct BLUETOOTH_FIND_RADIO_PARAMS
        {
            public uint dwSize;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct BLUETOOTH_DEVICE_SEARCH_PARAMS
        {
            public uint dwSize;
            [MarshalAs(UnmanagedType.Bool)]
            public bool fReturnAuthenticated;
            [MarshalAs(UnmanagedType.Bool)]
            public bool fReturnRemembered;
            [MarshalAs(UnmanagedType.Bool)]
            public bool fReturnUnknown;
            [MarshalAs(UnmanagedType.Bool)]
            public bool fReturnConnected;
            [MarshalAs(UnmanagedType.Bool)]
            public bool fIssueInquiry;
            public byte cTimeoutMultiplier;
            public IntPtr hRadio;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct SYSTEMTIME
        {
            public ushort wYear, wMonth, wDayOfWeek, wDay, wHour, wMinute, wSecond, wMilliseconds;
        }

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private struct BLUETOOTH_DEVICE_INFO
        {
            public uint dwSize;
            public ulong Address;
            public uint ulClassofDevice;
            [MarshalAs(UnmanagedType.Bool)]
            public bool fConnected;
            [MarshalAs(UnmanagedType.Bool)]
            public bool fRemembered;
            [MarshalAs(UnmanagedType.Bool)]
            public bool fAuthenticated;
            public SYSTEMTIME stLastSeen;
            public SYSTEMTIME stLastUsed;
            [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 248)]
            public string szName;
        }

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private struct BLUETOOTH_AUTHENTICATION_CALLBACK_PARAMS
        {
            public BLUETOOTH_DEVICE_INFO deviceInfo;
            public int authenticationMethod;
            public int ioCapability;
            public int authenticationRequirements;
            public uint Numeric_Value;
        }

        [StructLayout(LayoutKind.Explicit, Size = 48)]
        private struct BLUETOOTH_AUTHENTICATE_RESPONSE
        {
            [FieldOffset(0)]
            public ulong bthAddressRemote;
            [FieldOffset(8)]
            public int authMethod;
            [FieldOffset(12)]
            public uint numericCompInfo;
            [FieldOffset(12)]
            public byte pin0;
            [FieldOffset(13)]
            public byte pin1;
            [FieldOffset(14)]
            public byte pin2;
            [FieldOffset(15)]
            public byte pin3;
            [FieldOffset(28)]
            public byte pinLength;
            [FieldOffset(44)]
            public byte negativeResponse;
        }

        [UnmanagedFunctionPointer(CallingConvention.StdCall)]
        private delegate bool PFN_AUTHENTICATION_CALLBACK_EX(IntPtr pvParam, IntPtr pAuthCallbackParams);

        [DllImport("BluetoothAPIs.dll", SetLastError = true)]
        private static extern IntPtr BluetoothFindFirstRadio(ref BLUETOOTH_FIND_RADIO_PARAMS pbtfrp, out IntPtr phRadio);

        [DllImport("BluetoothAPIs.dll", SetLastError = true)]
        [return: MarshalAs(UnmanagedType.Bool)]
        private static extern bool BluetoothFindRadioClose(IntPtr hFind);

        [DllImport("BluetoothAPIs.dll", SetLastError = true)]
        [return: MarshalAs(UnmanagedType.Bool)]
        private static extern bool BluetoothEnableDiscovery(IntPtr hRadio, [MarshalAs(UnmanagedType.Bool)] bool fEnabled);

        [DllImport("BluetoothAPIs.dll", SetLastError = true)]
        [return: MarshalAs(UnmanagedType.Bool)]
        private static extern bool BluetoothEnableIncomingConnections(IntPtr hRadio, [MarshalAs(UnmanagedType.Bool)] bool fEnabled);

        [DllImport("BluetoothAPIs.dll", SetLastError = true, CharSet = CharSet.Unicode)]
        private static extern IntPtr BluetoothFindFirstDevice(ref BLUETOOTH_DEVICE_SEARCH_PARAMS pbtsp, ref BLUETOOTH_DEVICE_INFO pbtdi);

        [DllImport("BluetoothAPIs.dll", SetLastError = true, CharSet = CharSet.Unicode)]
        [return: MarshalAs(UnmanagedType.Bool)]
        private static extern bool BluetoothFindNextDevice(IntPtr hFind, ref BLUETOOTH_DEVICE_INFO pbtdi);

        [DllImport("BluetoothAPIs.dll", SetLastError = true)]
        [return: MarshalAs(UnmanagedType.Bool)]
        private static extern bool BluetoothFindDeviceClose(IntPtr hFind);

        [DllImport("BluetoothAPIs.dll", SetLastError = true, CharSet = CharSet.Unicode)]
        private static extern uint BluetoothGetDeviceInfo(IntPtr hRadio, ref BLUETOOTH_DEVICE_INFO pbtdi);

        [DllImport("BluetoothAPIs.dll", SetLastError = true)]
        private static extern uint BluetoothRemoveDevice(ref ulong pAddress);

        [DllImport("bthprops.cpl", SetLastError = true, CharSet = CharSet.Unicode)]
        private static extern uint BluetoothRegisterForAuthenticationEx(IntPtr pbtdiIn, out IntPtr phRegHandleOut, PFN_AUTHENTICATION_CALLBACK_EX pfnCallbackIn, IntPtr pvParam);

        [DllImport("bthprops.cpl", SetLastError = true)]
        [return: MarshalAs(UnmanagedType.Bool)]
        private static extern bool BluetoothUnregisterAuthentication(IntPtr hRegHandle);

        [DllImport("bthprops.cpl", SetLastError = true)]
        private static extern uint BluetoothSendAuthenticationResponseEx(IntPtr hRadioIn, ref BLUETOOTH_AUTHENTICATE_RESPONSE pauthResponse);

        [DllImport("bthprops.cpl", SetLastError = true, CharSet = CharSet.Unicode)]
        private static extern uint BluetoothAuthenticateDeviceEx(IntPtr hwndParentIn, IntPtr hRadioIn, ref BLUETOOTH_DEVICE_INFO pbtdiInout, IntPtr pbtOobData, int authenticationRequirement);

        [DllImport("BluetoothAPIs.dll", SetLastError = true)]
        private static extern uint BluetoothSetServiceState(IntPtr hRadio, ref BLUETOOTH_DEVICE_INFO pbtdi, ref Guid pGuidService, uint dwServiceFlags);

        private static IntPtr s_activePairingRadioHandle;
        private static string s_lastAuthCallbackDiag = "NONE";

        private static bool OnBluetoothAuthCallbackEx(IntPtr pvParam, IntPtr pAuthCallbackParams)
        {
            try
            {
                if (pAuthCallbackParams == IntPtr.Zero) return true;
                int devInfoSize = Marshal.SizeOf<BLUETOOTH_DEVICE_INFO>();
                ulong remoteAddr = (ulong)Marshal.ReadInt64(pAuthCallbackParams, 8);
                int authMethod = Marshal.ReadInt32(pAuthCallbackParams, devInfoSize);
                uint numericVal = (uint)Marshal.ReadInt32(pAuthCallbackParams, devInfoSize + 12);

                var resp = new BLUETOOTH_AUTHENTICATE_RESPONSE
                {
                    bthAddressRemote = remoteAddr,
                    authMethod = authMethod,
                    negativeResponse = 0
                };

                if (authMethod == 1) // BLUETOOTH_AUTHENTICATION_METHOD_LEGACY ("0000")
                {
                    resp.pin0 = (byte)'0';
                    resp.pin1 = (byte)'0';
                    resp.pin2 = (byte)'0';
                    resp.pin3 = (byte)'0';
                    resp.pinLength = 4;
                }
                else
                {
                    resp.numericCompInfo = numericVal;
                }

                uint sendRes = BluetoothSendAuthenticationResponseEx(s_activePairingRadioHandle, ref resp);
                if (sendRes != 0 && s_activePairingRadioHandle != IntPtr.Zero)
                {
                    sendRes = BluetoothSendAuthenticationResponseEx(IntPtr.Zero, ref resp);
                }
                s_lastAuthCallbackDiag = $"CB_METHOD={authMethod}_VAL={numericVal}_SENDRES={sendRes}";
            }
            catch (Exception ex)
            {
                s_lastAuthCallbackDiag = $"CB_ERR={ex.Message}";
            }
            return true;
        }

        private static readonly Guid GuidAudioSink = new("0000110B-0000-1000-8000-00805F9B34FB");
        private static readonly Guid GuidHandsfree = new("0000111E-0000-1000-8000-00805F9B34FB");
        private static readonly Guid GuidAvRemote = new("0000110E-0000-1000-8000-00805F9B34FB");
        private static readonly Guid GuidHid = new("00001124-0000-1000-8000-00805F9B34FB");

        public static async Task<List<BluetoothDeviceDto>> ScanBluetoothDevicesAsync(bool issueHardwareInquiry = false)
        {
            return await Task.Run(() =>
            {
                EnsureWirelessServicesAndLocationConsent();

                var map = new Dictionary<string, BluetoothDeviceDto>(StringComparer.OrdinalIgnoreCase);
                var audioOutputs = ListAudioOutputDevices();

                // 1. Native BluetoothAPIs.dll Radio & Device Discovery (paired + nearby unpaired devices)
                IntPtr hRadio = IntPtr.Zero;
                IntPtr hFindRadio = IntPtr.Zero;
                try
                {
                    var radioParams = new BLUETOOTH_FIND_RADIO_PARAMS { dwSize = (uint)Marshal.SizeOf<BLUETOOTH_FIND_RADIO_PARAMS>() };
                    hFindRadio = BluetoothFindFirstRadio(ref radioParams, out hRadio);
                    if (hRadio != IntPtr.Zero)
                    {
                        // CRITICAL FOR CLEAR BLUETOOTH AUDIO:
                        // Only enable inquiry scan during an explicit user-triggered search!
                        // Leaving discovery enabled forces the Realtek BT radio to switch off the A2DP channel every 1.28s, causing audio dropouts.
                        BluetoothEnableDiscovery(hRadio, issueHardwareInquiry);
                        BluetoothEnableIncomingConnections(hRadio, true);
                    }

                    bool[] passes = issueHardwareInquiry ? new[] { false, true } : new[] { false };
                    foreach (bool doInquiry in passes)
                    {
                        var search = new BLUETOOTH_DEVICE_SEARCH_PARAMS
                        {
                            dwSize = (uint)Marshal.SizeOf<BLUETOOTH_DEVICE_SEARCH_PARAMS>(),
                            fReturnAuthenticated = true,
                            fReturnRemembered = true,
                            fReturnUnknown = true,
                            fReturnConnected = true,
                            fIssueInquiry = doInquiry,
                            cTimeoutMultiplier = (byte)(doInquiry ? 2 : 1),
                            hRadio = hRadio
                        };

                        var devInfo = new BLUETOOTH_DEVICE_INFO { dwSize = (uint)Marshal.SizeOf<BLUETOOTH_DEVICE_INFO>() };
                        IntPtr hFindDev = BluetoothFindFirstDevice(ref search, ref devInfo);
                        if (hFindDev != IntPtr.Zero)
                        {
                            try
                            {
                                do
                                {
                                    string name = (devInfo.szName ?? "").Trim();
                                    if (string.IsNullOrWhiteSpace(name) && devInfo.Address != 0)
                                        name = $"Bluetooth-Gerät ({devInfo.Address:X12})";
                                    if (string.IsNullOrWhiteSpace(name)) continue;

                                    bool isAudioActive = name.Length >= 5
                                        && !string.Equals(name, "Headphones", StringComparison.OrdinalIgnoreCase)
                                        && !string.Equals(name, "Speakers", StringComparison.OrdinalIgnoreCase)
                                        && !string.Equals(name, "Kopfhörer", StringComparison.OrdinalIgnoreCase)
                                        && !string.Equals(name, "Lautsprecher", StringComparison.OrdinalIgnoreCase)
                                        && audioOutputs.Any(a => a.Name.Contains(name, StringComparison.OrdinalIgnoreCase));
                                    bool isConn = devInfo.fConnected || isAudioActive;
                                    bool isPaired = devInfo.fAuthenticated || devInfo.fRemembered;

                                    map[name] = new BluetoothDeviceDto
                                    {
                                        Name = name,
                                        InstanceId = $"BTH\\{devInfo.Address:X12}",
                                        BluetoothAddress = devInfo.Address,
                                        IsConnected = isConn,
                                        IsPaired = isPaired
                                    };
                                }
                                while (BluetoothFindNextDevice(hFindDev, ref devInfo));
                            }
                            finally
                            {
                                BluetoothFindDeviceClose(hFindDev);
                            }
                        }
                    }

                    // Turn discovery back off after scan so A2DP stereo streaming has 100% uninterrupted radio airtime
                    if (hRadio != IntPtr.Zero && issueHardwareInquiry)
                    {
                        BluetoothEnableDiscovery(hRadio, false);
                    }
                }
                catch { }
                finally
                {
                    if (hFindRadio != IntPtr.Zero) BluetoothFindRadioClose(hFindRadio);
                    if (hRadio != IntPtr.Zero) CloseHandle(hRadio);
                }

                if (!map.Values.Any(b => b.IsTeufel))
                {
                    bool activeTeufelAudio = audioOutputs.Any(a => a.Name.Contains("Teufel", StringComparison.OrdinalIgnoreCase));
                    map["Teufel KB 43 CR 19"] = new BluetoothDeviceDto
                    {
                        Name = "Teufel KB 43 CR 19",
                        InstanceId = "BTH\\FC58FAC7F41D",
                        BluetoothAddress = 0xFC58FAC7F41DUL,
                        IsConnected = activeTeufelAudio,
                        IsPaired = false
                    };
                }

                return map.Values
                    .OrderByDescending(b => b.IsTeufel)
                    .ThenByDescending(b => b.IsConnected)
                    .ThenByDescending(b => b.IsPaired)
                    .ThenBy(b => b.Name)
                    .ToList();
            });
        }

        private static readonly SemaphoreSlim s_btConnectLock = new(1, 1);

        public static async Task<(bool Success, string Message)> ConnectOrPairBluetoothDeviceAsync(BluetoothDeviceDto dev)
        {
            if (dev == null || string.IsNullOrWhiteSpace(dev.Name))
                return (false, "Ungültiges Bluetooth-Gerät.");

            return await Task.Run(() =>
            {
                s_btConnectLock.Wait();
                try
                {
                    bool TrySwitchToActiveCoreAudioOutput()
                    {
                        var outputs = ListAudioOutputDevices();
                        var match = outputs.FirstOrDefault(a =>
                            a.Name.Contains(dev.Name, StringComparison.OrdinalIgnoreCase) ||
                            (dev.Name.Contains("Teufel", StringComparison.OrdinalIgnoreCase) && a.Name.Contains("Teufel", StringComparison.OrdinalIgnoreCase)));
                        if (match != null)
                        {
                            SetDefaultAudioOutputDevice(match.DeviceId);
                            return true;
                        }
                        return false;
                    }

                    if (TrySwitchToActiveCoreAudioOutput())
                        return (true, $"Verbunden mit '{dev.Name}'");

                    EnsureWirelessServicesAndLocationConsent();

                    // Resolve BluetoothAddress if missing
                    if (dev.BluetoothAddress == 0)
                    {
                        var known = ScanBluetoothDevicesAsync(issueHardwareInquiry: false).GetAwaiter().GetResult();
                        var found = known.FirstOrDefault(x =>
                            x.BluetoothAddress != 0 &&
                            (string.Equals(x.Name, dev.Name, StringComparison.OrdinalIgnoreCase) ||
                             (dev.Name.Contains("Teufel", StringComparison.OrdinalIgnoreCase) && x.IsTeufel)));
                        if (found != null)
                            dev.BluetoothAddress = found.BluetoothAddress;
                        else if (dev.Name.Contains("Teufel", StringComparison.OrdinalIgnoreCase))
                            dev.BluetoothAddress = 0xFC58FAC7F41DUL;
                    }

                    IntPtr hRadio = IntPtr.Zero;
                    IntPtr hFindRadio = IntPtr.Zero;
                    try
                    {
                        var rp = new BLUETOOTH_FIND_RADIO_PARAMS { dwSize = (uint)Marshal.SizeOf<BLUETOOTH_FIND_RADIO_PARAMS>() };
                        hFindRadio = BluetoothFindFirstRadio(ref rp, out hRadio);
                        s_activePairingRadioHandle = hRadio;

                        Guid gSink = GuidAudioSink;
                        Guid gHf = GuidHandsfree;
                        Guid gAv = GuidAvRemote;
                        Guid gHid = GuidHid;

                        if (dev.BluetoothAddress != 0 && hRadio != IntPtr.Zero)
                        {
                            var bdi = new BLUETOOTH_DEVICE_INFO
                            {
                                dwSize = (uint)Marshal.SizeOf<BLUETOOTH_DEVICE_INFO>(),
                                Address = dev.BluetoothAddress,
                                szName = dev.Name
                            };
                            BluetoothGetDeviceInfo(hRadio, ref bdi);

                            // Phase 1: If already remembered AND authenticated in Windows, try KSPROPERTY_ONESHOT_RECONNECT + A2DP Service Toggle (0 -> 1) first!
                            if (bdi.fRemembered && bdi.fAuthenticated)
                            {
                                TriggerBtAudioOneShotReconnect(dev.Name);
                                if (!bdi.fConnected)
                                {
                                    BluetoothSetServiceState(hRadio, ref bdi, ref gSink, 0);
                                    BluetoothSetServiceState(hRadio, ref bdi, ref gAv, 0);
                                    Thread.Sleep(280);
                                }
                                BluetoothSetServiceState(hRadio, ref bdi, ref gSink, 1);
                                BluetoothSetServiceState(hRadio, ref bdi, ref gAv, 1);
                                BluetoothSetServiceState(hRadio, ref bdi, ref gHf, 0);
                                if (dev.IsTeufel || dev.Name.Contains("Speaker", StringComparison.OrdinalIgnoreCase) || dev.Name.Contains("Audio", StringComparison.OrdinalIgnoreCase))
                                {
                                    BluetoothSetServiceState(hRadio, ref bdi, ref gHid, 0);
                                }
                                TriggerBtAudioOneShotReconnect(dev.Name);
                                BluetoothEnableDiscovery(hRadio, false);
                                BluetoothEnableIncomingConnections(hRadio, false);

                                for (int w = 0; w < 4; w++)
                                {
                                    Thread.Sleep(380);
                                    if (TrySwitchToActiveCoreAudioOutput())
                                    {
                                        return (true, $"Verbunden mit '{dev.Name}'");
                                    }
                                }

                                BluetoothGetDeviceInfo(hRadio, ref bdi);
                            }

                            // Phase 2: If speaker is in PAIRING mode (or unauthenticated / stale link key rejected), pair via WinRT DAS + bthprops.cpl and bind A2DP!
                            if (!bdi.fConnected || !bdi.fAuthenticated)
                            {
                                string winRtPairDiag = "NONE";
                                if (bdi.fRemembered)
                                {
                                    try
                                    {
                                        var oldBt = Windows.Devices.Bluetooth.BluetoothDevice.FromBluetoothAddressAsync(dev.BluetoothAddress).AsTask().GetAwaiter().GetResult();
                                        if (oldBt?.DeviceInformation?.Pairing != null && oldBt.DeviceInformation.Pairing.IsPaired)
                                        {
                                            oldBt.DeviceInformation.Pairing.UnpairAsync().AsTask().GetAwaiter().GetResult();
                                        }
                                        oldBt?.Dispose();
                                    }
                                    catch { }

                                    ulong remAddr = dev.BluetoothAddress;
                                    BluetoothRemoveDevice(ref remAddr);
                                    Thread.Sleep(450);
                                }

                                BluetoothEnableDiscovery(hRadio, true);
                                BluetoothEnableIncomingConnections(hRadio, true);

                                // 2A. Native Windows 10/11 WinRT DeviceAssociationService (DAS) Custom Pairing (registers PnP MMDevice Audio Endpoint!)
                                try
                                {
                                    var btDev = Windows.Devices.Bluetooth.BluetoothDevice.FromBluetoothAddressAsync(dev.BluetoothAddress).AsTask().GetAwaiter().GetResult();
                                    if (btDev?.DeviceInformation?.Pairing != null)
                                    {
                                        if (!btDev.DeviceInformation.Pairing.IsPaired)
                                        {
                                            var custom = btDev.DeviceInformation.Pairing.Custom;
                                            custom.PairingRequested += static (_, args) =>
                                            {
                                                try
                                                {
                                                    if (args.PairingKind == Windows.Devices.Enumeration.DevicePairingKinds.ProvidePin)
                                                        args.Accept("0000");
                                                    else
                                                        args.Accept();
                                                }
                                                catch { }
                                            };

                                            var kinds = Windows.Devices.Enumeration.DevicePairingKinds.ConfirmOnly
                                                      | Windows.Devices.Enumeration.DevicePairingKinds.ConfirmPinMatch
                                                      | Windows.Devices.Enumeration.DevicePairingKinds.ProvidePin
                                                      | Windows.Devices.Enumeration.DevicePairingKinds.DisplayPin;

                                            var pRes = custom.PairAsync(kinds, Windows.Devices.Enumeration.DevicePairingProtectionLevel.None).AsTask().GetAwaiter().GetResult();
                                            winRtPairDiag = pRes.Status.ToString();
                                        }
                                        else
                                        {
                                            winRtPairDiag = "AlreadyPaired";
                                        }

                                        // Trigger uncached SDP/RFCOMM query to wake ACL & A2DP driver
                                        try
                                        {
                                            _ = btDev.GetRfcommServicesAsync(Windows.Devices.Bluetooth.BluetoothCacheMode.Uncached).AsTask().GetAwaiter().GetResult();
                                        }
                                        catch { }
                                    }
                                    btDev?.Dispose();
                                }
                                catch (Exception wex)
                                {
                                    winRtPairDiag = "ERR_" + wex.GetType().Name;
                                }

                                bdi = new BLUETOOTH_DEVICE_INFO
                                {
                                    dwSize = (uint)Marshal.SizeOf<BLUETOOTH_DEVICE_INFO>(),
                                    Address = dev.BluetoothAddress,
                                    szName = dev.Name
                                };
                                BluetoothGetDeviceInfo(hRadio, ref bdi);

                                uint authRes = 0;
                                if (!bdi.fAuthenticated)
                                {
                                    PFN_AUTHENTICATION_CALLBACK_EX authCb = OnBluetoothAuthCallbackEx;
                                    BluetoothRegisterForAuthenticationEx(IntPtr.Zero, out IntPtr hAuthReg, authCb, IntPtr.Zero);
                                    try
                                    {
                                        authRes = BluetoothAuthenticateDeviceEx(IntPtr.Zero, hRadio, ref bdi, IntPtr.Zero, 2);
                                        if (authRes != 0)
                                            authRes = BluetoothAuthenticateDeviceEx(IntPtr.Zero, hRadio, ref bdi, IntPtr.Zero, 0);
                                        Thread.Sleep(500);
                                    }
                                    finally
                                    {
                                        if (hAuthReg != IntPtr.Zero)
                                            BluetoothUnregisterAuthentication(hAuthReg);
                                        GC.KeepAlive(authCb);
                                    }
                                }

                                BluetoothGetDeviceInfo(hRadio, ref bdi);
                                uint sSink = BluetoothSetServiceState(hRadio, ref bdi, ref gSink, 1);
                                uint sAv = BluetoothSetServiceState(hRadio, ref bdi, ref gAv, 1);
                                BluetoothSetServiceState(hRadio, ref bdi, ref gHf, 0);
                                if (dev.IsTeufel || dev.Name.Contains("Speaker", StringComparison.OrdinalIgnoreCase) || dev.Name.Contains("Audio", StringComparison.OrdinalIgnoreCase))
                                {
                                    BluetoothSetServiceState(hRadio, ref bdi, ref gHid, 0);
                                }
                                else
                                {
                                    BluetoothSetServiceState(hRadio, ref bdi, ref gHid, 1);
                                }

                                Thread.Sleep(550);
                                TriggerBtAudioOneShotReconnect(dev.Name);
                                BluetoothEnableDiscovery(hRadio, false);
                                BluetoothEnableIncomingConnections(hRadio, false);
                                BluetoothGetDeviceInfo(hRadio, ref bdi);

                                try
                                {
                                    string diagDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkOS");
                                    File.WriteAllText(Path.Combine(diagDir, "bt_lowlevel.log"),
                                        $"ADDR={dev.BluetoothAddress:X12} | WINRT={winRtPairDiag} | CB={s_lastAuthCallbackDiag} | AUTH_RES={authRes} | SINK_RES={sSink} | AV_RES={sAv} | CONN={bdi.fConnected} | REM={bdi.fRemembered} | AUTH={bdi.fAuthenticated}");
                                }
                                catch { }
                            }
                        }
                    }
                    finally
                    {
                        s_activePairingRadioHandle = IntPtr.Zero;
                        if (hFindRadio != IntPtr.Zero) BluetoothFindRadioClose(hFindRadio);
                        if (hRadio != IntPtr.Zero) CloseHandle(hRadio);
                    }

                    // Wait up to 3.5s for CoreAudio endpoint to become active and switch default audio output
                    for (int i = 0; i < 8; i++)
                    {
                        if (TrySwitchToActiveCoreAudioOutput())
                            return (true, $"Verbunden mit '{dev.Name}'");
                        if (i == 3)
                            TriggerBtAudioOneShotReconnect(dev.Name);
                        Thread.Sleep(420);
                    }

                    if (dev.BluetoothAddress != 0)
                        return (true, $"Bluetooth-Gerät '{dev.Name}' gekoppelt/aktiviert");

                    return (false, $"'{dev.Name}' ist im Standby oder außerhalb der Reichweite.");
                }
                catch (Exception ex)
                {
                    return (false, ex.Message);
                }
                finally
                {
                    s_btConnectLock.Release();
                }
            });
        }

        public static void OpenSystemBluetoothPairingDialog()
        {
            // CRITICAL: In Custom Shell mode (--shell without explorer.exe), launching ms-settings:bluetooth or
            // control.exe bthprops.cpl via ShellExecute invokes rundll32.exe shell32.dll,SHCreateLocalServerRunDll
            // which fails with "rundll32 - Klasse nicht registriert" because explorer.exe's shell COM server is not running!
            // All Bluetooth scanning, pairing, and A2DP connection is handled 100% natively via BluetoothAPIs.dll inside Funkenwerk OS.
            try
            {
                foreach (var proc in Process.GetProcessesByName("DevicePairingWizard"))
                {
                    try { proc.Kill(); } catch { }
                }
            }
            catch { }
        }

        private static string RunProcessCapture(string fileName, string arguments)
        {
            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = fileName,
                    Arguments = arguments,
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    RedirectStandardOutput = true
                };
                using var p = Process.Start(psi);
                if (p == null) return "";
                string output = p.StandardOutput.ReadToEnd();
                p.WaitForExit(4000);
                return output;
            }
            catch
            {
                return "";
            }
        }

        // =====================================================================
        // NAMED PIPE MUSIC BRIDGE TO FUNKENWERK SUITE (NEVER AUTOPLAYS!)
        // =====================================================================
        public static async Task<string> SendFunkenwerkMusicCommandAsync(string action, int? volume = null)
        {
            try
            {
                using var pipe = new NamedPipeClientStream(".", "Funkenwerk_ControlPipe_v1", PipeDirection.InOut, PipeOptions.Asynchronous | PipeOptions.CurrentUserOnly);
                using var cts = new CancellationTokenSource(1200);
                await pipe.ConnectAsync(cts.Token);

                var payload = new Dictionary<string, object?>
                {
                    ["domain"] = "music",
                    ["action"] = action
                };
                if (volume.HasValue)
                    payload["volume"] = Math.Clamp(volume.Value, 0, 100);

                string reqLine = JsonSerializer.Serialize(payload) + "\n";
                byte[] reqBytes = Encoding.UTF8.GetBytes(reqLine);
                await pipe.WriteAsync(reqBytes, 0, reqBytes.Length, cts.Token);
                await pipe.FlushAsync(cts.Token);

                using var sr = new StreamReader(pipe, Encoding.UTF8, false, 4096, leaveOpen: true);
                string? resp = await sr.ReadLineAsync(cts.Token);
                return resp ?? "{}";
            }
            catch
            {
                return "";
            }
        }

        // =====================================================================
        // HARDWARE TELEMETRY & NETWORK DISCOVERY
        // =====================================================================
        public static (double FreeRamGb, double TotalRamGb, int CpuPercent) ReadSystemResources()
        {
            double freeGb = 0;
            double totalGb = 0;
            int cpuPct = 0;

            try
            {
                var mem = new MEMORYSTATUSEX();
                if (GlobalMemoryStatusEx(mem))
                {
                    totalGb = Math.Round(mem.ullTotalPhys / (1024.0 * 1024.0 * 1024.0), 1);
                    freeGb = Math.Round(mem.ullAvailPhys / (1024.0 * 1024.0 * 1024.0), 1);
                }
            }
            catch { }

            try
            {
                if (GetSystemTimes(out FILETIME idle, out FILETIME kernel, out FILETIME user))
                {
                    ulong idleTicks = idle.ToUInt64();
                    ulong kernelTicks = kernel.ToUInt64();
                    ulong userTicks = user.ToUInt64();

                    if (_prevKernel != 0 || _prevUser != 0)
                    {
                        ulong idleDiff = idleTicks - _prevIdle;
                        ulong totalDiff = (kernelTicks - _prevKernel) + (userTicks - _prevUser);
                        if (totalDiff > 0)
                        {
                            double busy = 1.0 - ((double)idleDiff / totalDiff);
                            cpuPct = Math.Clamp((int)Math.Round(busy * 100.0), 0, 100);
                        }
                    }

                    _prevIdle = idleTicks;
                    _prevKernel = kernelTicks;
                    _prevUser = userTicks;
                }
            }
            catch { }

            return (freeGb, totalGb, cpuPct);
        }

        public static (bool IsOnline, bool IsWifi, string ConnectionKind, string LocalIp) ReadLocalNetworkSummary()
        {
            try
            {
                foreach (var nic in NetworkInterface.GetAllNetworkInterfaces())
                {
                    if (nic.OperationalStatus != OperationalStatus.Up) continue;
                    if (nic.NetworkInterfaceType is NetworkInterfaceType.Loopback or NetworkInterfaceType.Tunnel) continue;
                    string desc = (nic.Name + " " + nic.Description).ToLowerInvariant();
                    if (desc.Contains("wsl") || desc.Contains("vethernet") || desc.Contains("hyper-v") || desc.Contains("virtual")) continue;

                    var props = nic.GetIPProperties();
                    var ipv4 = props.UnicastAddresses.FirstOrDefault(u => u.Address.AddressFamily == AddressFamily.InterNetwork);
                    if (ipv4 != null)
                    {
                        bool wifi = nic.NetworkInterfaceType == NetworkInterfaceType.Wireless80211;
                        return (true, wifi, wifi ? "WLAN" : "LAN", ipv4.Address.ToString());
                    }
                }
            }
            catch { }

            return (false, false, "Offline", "-");
        }

        public static async Task<List<OsLanDeviceDto>> ProbeManufactoryLanDevicesAsync()
        {
            var targets = new List<OsLanDeviceDto>
            {
                new() { Name = "FRITZ!Box Gateway", IpAddress = "192.168.188.1", ProbePort = 80 },
                new() { Name = "HP OfficeJet 5230", IpAddress = "192.168.188.26", ProbePort = 631 },
                new() { Name = "Labeldrucker", IpAddress = "192.168.188.149", ProbePort = 9100 },
                new() { Name = "Tapo Kamera 1", IpAddress = "192.168.188.101", ProbePort = 554 },
                new() { Name = "Tapo Kamera 2", IpAddress = "192.168.188.102", ProbePort = 554 },
                new() { Name = "Tapo Kamera 3", IpAddress = "192.168.188.105", ProbePort = 554 },
                new() { Name = "Tapo Kamera 4", IpAddress = "192.168.188.132", ProbePort = 554 },
                new() { Name = "Tapo Steckdose 1", IpAddress = "192.168.188.109", ProbePort = 80 },
                new() { Name = "Tapo Steckdose 2", IpAddress = "192.168.188.110", ProbePort = 80 },
                new() { Name = "Tapo Steckdose 3", IpAddress = "192.168.188.123", ProbePort = 80 }
            };

            var tasks = targets.Select(async dev =>
            {
                var sw = Stopwatch.StartNew();
                try
                {
                    using var ping = new Ping();
                    var reply = await ping.SendPingAsync(dev.IpAddress, 850);
                    if (reply.Status == IPStatus.Success)
                    {
                        dev.IsOnline = true;
                        dev.LatencyMs = Math.Max(1, reply.RoundtripTime);
                        return;
                    }
                }
                catch { }

                try
                {
                    using var tcp = new TcpClient();
                    using var cts = new CancellationTokenSource(900);
                    await tcp.ConnectAsync(dev.IpAddress, dev.ProbePort, cts.Token);
                    sw.Stop();
                    dev.IsOnline = true;
                    dev.LatencyMs = Math.Max(1, sw.ElapsedMilliseconds);
                }
                catch
                {
                    dev.IsOnline = false;
                }
            });

            await Task.WhenAll(tasks);
            return targets;
        }

        // =====================================================================
        // BESPOKE 3D OBSIDIAN-GOLD SYSTEM ICON GENERATOR + COLLECTION APPS
        // =====================================================================
        private static readonly Dictionary<string, BitmapSource> SystemBadgeCache = new(StringComparer.OrdinalIgnoreCase);

        public static BitmapSource GetSystemBadgeIcon(string kind)
        {
            if (SystemBadgeCache.TryGetValue(kind, out var cached))
                return cached;

            if (string.Equals(kind, "leitstand", StringComparison.OrdinalIgnoreCase))
            {
                try
                {
                    var bi = new BitmapImage();
                    bi.BeginInit();
                    bi.UriSource = new Uri("pack://application:,,,/funkenwerk_os.png", UriKind.Absolute);
                    bi.CacheOption = BitmapCacheOption.OnLoad;
                    bi.EndInit();
                    bi.Freeze();
                    SystemBadgeCache[kind] = bi;
                    return bi;
                }
                catch { }
            }

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                string lowKind = kind.ToLowerInvariant();
                var innerDark = new SolidColorBrush(System.Windows.Media.Color.FromRgb(13, 16, 23));

                // Default Gold palette (used by Notizen, Ordner, Dateien, Papierkorb, etc.)
                System.Windows.Media.Brush outerBorderBrush = new LinearGradientBrush(
                    System.Windows.Media.Color.FromRgb(226, 193, 124),
                    System.Windows.Media.Color.FromRgb(154, 116, 48),
                    new System.Windows.Point(0, 0),
                    new System.Windows.Point(0, 1));
                var rimPen = new System.Windows.Media.Pen(new SolidColorBrush(System.Windows.Media.Color.FromArgb(135, 197, 160, 89)), 1.2);
                var accentPen = new System.Windows.Media.Pen(new LinearGradientBrush(
                    System.Windows.Media.Color.FromRgb(250, 224, 158),
                    System.Windows.Media.Color.FromRgb(197, 160, 89),
                    new System.Windows.Point(0, 0),
                    new System.Windows.Point(1, 1)), 4.6)
                {
                    StartLineCap = PenLineCap.Round,
                    EndLineCap = PenLineCap.Round,
                    LineJoin = PenLineJoin.Round
                };
                System.Windows.Media.Brush accentFill = new LinearGradientBrush(
                    System.Windows.Media.Color.FromRgb(236, 202, 130),
                    System.Windows.Media.Color.FromRgb(178, 138, 64),
                    new System.Windows.Point(0, 0),
                    new System.Windows.Point(1, 1));

                // Distinct visual color themes per user specification:
                if (lowKind == "player") // 1. Mediaplayer: VLC Orange
                {
                    outerBorderBrush = new LinearGradientBrush(
                        System.Windows.Media.Color.FromRgb(255, 159, 67),
                        System.Windows.Media.Color.FromRgb(217, 72, 15),
                        new System.Windows.Point(0, 0),
                        new System.Windows.Point(0, 1));
                    rimPen = new System.Windows.Media.Pen(new SolidColorBrush(System.Windows.Media.Color.FromArgb(150, 255, 146, 43)), 1.2);
                    accentFill = new LinearGradientBrush(
                        System.Windows.Media.Color.FromRgb(255, 169, 77),
                        System.Windows.Media.Color.FromRgb(230, 81, 0),
                        new System.Windows.Point(0, 0),
                        new System.Windows.Point(1, 1));
                    accentPen = new System.Windows.Media.Pen(accentFill, 4.4)
                    {
                        StartLineCap = PenLineCap.Round,
                        EndLineCap = PenLineCap.Round,
                        LineJoin = PenLineJoin.Round
                    };
                }
                else if (lowKind == "calculator") // 2. Taschenrechner: Leicht blauer Ton
                {
                    outerBorderBrush = new LinearGradientBrush(
                        System.Windows.Media.Color.FromRgb(125, 211, 252),
                        System.Windows.Media.Color.FromRgb(37, 99, 235),
                        new System.Windows.Point(0, 0),
                        new System.Windows.Point(0, 1));
                    rimPen = new System.Windows.Media.Pen(new SolidColorBrush(System.Windows.Media.Color.FromArgb(150, 96, 165, 250)), 1.2);
                    accentFill = new LinearGradientBrush(
                        System.Windows.Media.Color.FromRgb(147, 197, 253),
                        System.Windows.Media.Color.FromRgb(59, 130, 246),
                        new System.Windows.Point(0, 0),
                        new System.Windows.Point(1, 1));
                    accentPen = new System.Windows.Media.Pen(accentFill, 4.4)
                    {
                        StartLineCap = PenLineCap.Round,
                        EndLineCap = PenLineCap.Round,
                        LineJoin = PenLineJoin.Round
                    };
                }
                else if (lowKind == "snip") // 3. Snipping Tool (Ausschneiden): Rot & Blau leicht gemischt
                {
                    var mixGrad = new LinearGradientBrush { StartPoint = new System.Windows.Point(0, 0), EndPoint = new System.Windows.Point(1, 1) };
                    mixGrad.GradientStops.Add(new GradientStop(System.Windows.Media.Color.FromRgb(248, 113, 113), 0.0));
                    mixGrad.GradientStops.Add(new GradientStop(System.Windows.Media.Color.FromRgb(168, 85, 247), 0.5));
                    mixGrad.GradientStops.Add(new GradientStop(System.Windows.Media.Color.FromRgb(59, 130, 246), 1.0));
                    outerBorderBrush = mixGrad;
                    rimPen = new System.Windows.Media.Pen(mixGrad, 1.3);
                    accentFill = mixGrad;
                    accentPen = new System.Windows.Media.Pen(mixGrad, 4.2)
                    {
                        StartLineCap = PenLineCap.Round,
                        EndLineCap = PenLineCap.Round,
                        LineJoin = PenLineJoin.Round
                    };
                }
                else if (lowKind == "pdf") // 5. PDF: Rot
                {
                    outerBorderBrush = new LinearGradientBrush(
                        System.Windows.Media.Color.FromRgb(248, 113, 113),
                        System.Windows.Media.Color.FromRgb(185, 28, 28),
                        new System.Windows.Point(0, 0),
                        new System.Windows.Point(0, 1));
                    rimPen = new System.Windows.Media.Pen(new SolidColorBrush(System.Windows.Media.Color.FromArgb(150, 239, 68, 68)), 1.2);
                    accentFill = new LinearGradientBrush(
                        System.Windows.Media.Color.FromRgb(252, 129, 129),
                        System.Windows.Media.Color.FromRgb(220, 38, 38),
                        new System.Windows.Point(0, 0),
                        new System.Windows.Point(1, 1));
                    accentPen = new System.Windows.Media.Pen(accentFill, 4.4)
                    {
                        StartLineCap = PenLineCap.Round,
                        EndLineCap = PenLineCap.Round,
                        LineJoin = PenLineJoin.Round
                    };
                }
                else if (lowKind == "settings") // 7. Einstellungen: Silber
                {
                    outerBorderBrush = new LinearGradientBrush(
                        System.Windows.Media.Color.FromRgb(243, 244, 246),
                        System.Windows.Media.Color.FromRgb(107, 114, 128),
                        new System.Windows.Point(0, 0),
                        new System.Windows.Point(0, 1));
                    rimPen = new System.Windows.Media.Pen(new SolidColorBrush(System.Windows.Media.Color.FromArgb(155, 209, 213, 219)), 1.2);
                    accentFill = new LinearGradientBrush(
                        System.Windows.Media.Color.FromRgb(249, 250, 251),
                        System.Windows.Media.Color.FromRgb(156, 163, 175),
                        new System.Windows.Point(0, 0),
                        new System.Windows.Point(1, 1));
                    accentPen = new System.Windows.Media.Pen(accentFill, 4.6)
                    {
                        StartLineCap = PenLineCap.Round,
                        EndLineCap = PenLineCap.Round,
                        LineJoin = PenLineJoin.Round
                    };
                }

                if (lowKind == "browser") // 6. Browser: 3/3 Rot, Gelb und Grün
                {
                    var clipRound = new RectangleGeometry(new Rect(4, 4, 88, 88), 22, 22);
                    dc.PushClip(clipRound);
                    // 3 equal 120-degree sectors from center (48,48):
                    // Sector 1 (Top/Left 120°): Red (#EF4444)
                    dc.DrawGeometry(new SolidColorBrush(System.Windows.Media.Color.FromRgb(239, 68, 68)), null,
                        Geometry.Parse("M 48,48 L 48,-20 L 115,10 Z M 48,48 L -20,10 L 48,-20 Z"));
                    // Sector 2 (Right/Bottom-Right 120°): Yellow (#FACC15)
                    dc.DrawGeometry(new SolidColorBrush(System.Windows.Media.Color.FromRgb(250, 204, 21)), null,
                        Geometry.Parse("M 48,48 L 115,10 L 115,96 L 48,115 Z"));
                    // Sector 3 (Bottom-Left/Left 120°): Green (#22C55E)
                    dc.DrawGeometry(new SolidColorBrush(System.Windows.Media.Color.FromRgb(34, 197, 94)), null,
                        Geometry.Parse("M 48,48 L 48,115 L -20,96 L -20,10 Z"));
                    dc.Pop();

                    dc.DrawRoundedRectangle(innerDark, null, new Rect(8, 8, 80, 80), 19, 19);
                }
                else
                {
                    dc.DrawRoundedRectangle(outerBorderBrush, null, new Rect(4, 4, 88, 88), 22, 22);
                    dc.DrawRoundedRectangle(innerDark, null, new Rect(8, 8, 80, 80), 19, 19);
                    dc.DrawRoundedRectangle(null, rimPen, new Rect(11, 11, 74, 74), 16, 16);
                }

                switch (lowKind)
                {
                    case "folder":
                    case "explorer":
                    {
                        var geom = Geometry.Parse("M 22,31 L 39,31 L 45,37 L 74,37 C 76,37 77,38 77,40 L 77,65 C 77,67 76,69 73,69 L 23,69 C 20,69 19,67 19,65 L 19,34 C 19,32 20,31 22,31 Z");
                        dc.DrawGeometry(accentFill, null, geom);
                        dc.DrawLine(new System.Windows.Media.Pen(innerDark, 2.4), new System.Windows.Point(21, 43), new System.Windows.Point(75, 43));
                        break;
                    }
                    case "meinedaten":
                    {
                        var geom = Geometry.Parse("M 20,30 L 38,30 L 44,36 L 76,36 C 78,36 79,37 79,39 L 79,66 C 79,68 78,70 75,70 L 21,70 C 18,70 17,68 17,66 L 17,33 C 17,31 18,30 20,30 Z");
                        dc.DrawGeometry(accentFill, null, geom);
                        dc.DrawGeometry(innerDark, null, Geometry.Parse("M 48,41 L 51,49 L 59,52 L 51,55 L 48,63 L 45,55 L 37,52 L 45,49 Z"));
                        break;
                    }
                    case "settings":
                    {
                        dc.DrawLine(accentPen, new System.Windows.Point(25, 33), new System.Windows.Point(71, 33));
                        dc.DrawLine(accentPen, new System.Windows.Point(25, 48), new System.Windows.Point(71, 48));
                        dc.DrawLine(accentPen, new System.Windows.Point(25, 63), new System.Windows.Point(71, 63));
                        dc.DrawEllipse(accentFill, new System.Windows.Media.Pen(innerDark, 2.2), new System.Windows.Point(39, 33), 6.5, 6.5);
                        dc.DrawEllipse(accentFill, new System.Windows.Media.Pen(innerDark, 2.2), new System.Windows.Point(58, 48), 6.5, 6.5);
                        dc.DrawEllipse(accentFill, new System.Windows.Media.Pen(innerDark, 2.2), new System.Windows.Point(43, 63), 6.5, 6.5);
                        break;
                    }
                    case "player":
                    {
                        // VLC-inspired Orange Cone + Play Emblem
                        var coneBrush = accentFill;
                        var stripeBrush = new SolidColorBrush(System.Windows.Media.Color.FromRgb(255, 243, 224));
                        dc.DrawRoundedRectangle(coneBrush, null, new Rect(24, 64, 48, 7), 3.5, 3.5);
                        dc.DrawGeometry(coneBrush, null, Geometry.Parse("M 44,22 L 52,22 L 64,64 L 32,64 Z"));
                        dc.DrawGeometry(stripeBrush, null, Geometry.Parse("M 40.5,35 L 55.5,35 L 57.5,42 L 38.5,42 Z"));
                        dc.DrawGeometry(stripeBrush, null, Geometry.Parse("M 36.5,49 L 59.5,49 L 61.5,56 L 34.5,56 Z"));
                        break;
                    }
                    case "pdf":
                    {
                        // Crimson Red PDF Document + Folded Corner + PDF Curves
                        dc.DrawGeometry(null, accentPen, Geometry.Parse("M 27,21 L 55,21 L 69,35 L 69,73 L 27,73 Z"));
                        dc.DrawGeometry(accentFill, null, Geometry.Parse("M 54,21 L 54,36 L 69,36 Z"));
                        dc.DrawRoundedRectangle(accentFill, null, new Rect(33, 43, 30, 12), 4, 4);
                        var whitePen = new System.Windows.Media.Pen(System.Windows.Media.Brushes.White, 2.2) { StartLineCap = PenLineCap.Round, EndLineCap = PenLineCap.Round };
                        dc.DrawLine(whitePen, new System.Windows.Point(38, 49), new System.Windows.Point(58, 49));
                        dc.DrawLine(accentPen, new System.Windows.Point(35, 63), new System.Windows.Point(61, 63));
                        break;
                    }
                    case "browser":
                    {
                        // 3/3 Red, Yellow, Green Segmented Globe
                        var redPen = new System.Windows.Media.Pen(new SolidColorBrush(System.Windows.Media.Color.FromRgb(239, 68, 68)), 5.2) { StartLineCap = PenLineCap.Round, EndLineCap = PenLineCap.Round };
                        var yellowPen = new System.Windows.Media.Pen(new SolidColorBrush(System.Windows.Media.Color.FromRgb(250, 204, 21)), 5.2) { StartLineCap = PenLineCap.Round, EndLineCap = PenLineCap.Round };
                        var greenPen = new System.Windows.Media.Pen(new SolidColorBrush(System.Windows.Media.Color.FromRgb(34, 197, 94)), 5.2) { StartLineCap = PenLineCap.Round, EndLineCap = PenLineCap.Round };

                        // Top-left to top-right arc (Red)
                        dc.DrawGeometry(null, redPen, Geometry.Parse("M 27,36 A 24,24 0 0 1 69,36"));
                        // Right to bottom arc (Yellow)
                        dc.DrawGeometry(null, yellowPen, Geometry.Parse("M 69,36 A 24,24 0 0 1 48,72"));
                        // Bottom to top-left arc (Green)
                        dc.DrawGeometry(null, greenPen, Geometry.Parse("M 48,72 A 24,24 0 0 1 27,36"));

                        // Center core sphere
                        var coreFill = new RadialGradientBrush(
                            System.Windows.Media.Color.FromRgb(96, 165, 250),
                            System.Windows.Media.Color.FromRgb(29, 78, 216));
                        dc.DrawEllipse(coreFill, new System.Windows.Media.Pen(System.Windows.Media.Brushes.White, 2.2), new System.Windows.Point(48, 48), 10.5, 10.5);
                        break;
                    }
                    case "recycle_empty":
                    case "recycle_full":
                    {
                        dc.DrawLine(accentPen, new System.Windows.Point(28, 31), new System.Windows.Point(68, 31));
                        dc.DrawLine(accentPen, new System.Windows.Point(41, 25), new System.Windows.Point(55, 25));
                        var binGeom = Geometry.Parse("M 32,35 L 64,35 L 61,68 C 61,70 59,71 57,71 L 39,71 C 37,71 35,70 35,68 Z");
                        dc.DrawGeometry(lowKind == "recycle_full" ? accentFill : null, accentPen, binGeom);
                        break;
                    }
                    case "file_image":
                    {
                        dc.DrawRoundedRectangle(null, accentPen, new Rect(24, 26, 48, 44), 6, 6);
                        dc.DrawEllipse(accentFill, null, new System.Windows.Point(36, 38), 4.5, 4.5);
                        dc.DrawGeometry(accentFill, null, Geometry.Parse("M 27,67 L 43,49 L 54,59 L 61,52 L 69,67 Z"));
                        break;
                    }
                    case "drive":
                    {
                        dc.DrawRoundedRectangle(null, accentPen, new Rect(21, 33, 54, 30), 7, 7);
                        dc.DrawLine(accentPen, new System.Windows.Point(29, 48), new System.Windows.Point(51, 48));
                        dc.DrawEllipse(accentFill, null, new System.Windows.Point(64, 48), 4.2, 4.2);
                        break;
                    }
                    case "calculator":
                    {
                        dc.DrawRoundedRectangle(null, accentPen, new Rect(25, 20, 46, 56), 8, 8);
                        var screenFill = new LinearGradientBrush(
                            System.Windows.Media.Color.FromRgb(186, 230, 253),
                            System.Windows.Media.Color.FromRgb(56, 189, 248),
                            new System.Windows.Point(0, 0),
                            new System.Windows.Point(1, 1));
                        dc.DrawRoundedRectangle(screenFill, null, new Rect(31, 26, 34, 13), 4, 4);
                        for (int r = 0; r < 2; r++)
                        {
                            for (int c = 0; c < 3; c++)
                            {
                                dc.DrawEllipse(accentFill, null, new System.Windows.Point(35 + c * 13, 49 + r * 12), 3.6, 3.6);
                            }
                        }
                        break;
                    }
                    case "snip":
                    {
                        var redPen = new System.Windows.Media.Pen(new SolidColorBrush(System.Windows.Media.Color.FromRgb(248, 113, 113)), 4.2) { StartLineCap = PenLineCap.Round, EndLineCap = PenLineCap.Round };
                        var bluePen = new System.Windows.Media.Pen(new SolidColorBrush(System.Windows.Media.Color.FromRgb(96, 165, 250)), 4.2) { StartLineCap = PenLineCap.Round, EndLineCap = PenLineCap.Round };
                        dc.DrawGeometry(null, redPen, Geometry.Parse("M 23,37 L 23,25 L 35,25"));
                        dc.DrawGeometry(null, bluePen, Geometry.Parse("M 61,25 L 73,25 L 73,37"));
                        dc.DrawGeometry(null, bluePen, Geometry.Parse("M 23,59 L 23,71 L 35,71"));
                        dc.DrawGeometry(null, redPen, Geometry.Parse("M 61,71 L 73,71 L 73,59"));
                        dc.DrawLine(redPen, new System.Windows.Point(37, 57), new System.Windows.Point(60, 36));
                        dc.DrawLine(bluePen, new System.Windows.Point(37, 37), new System.Windows.Point(60, 58));
                        dc.DrawEllipse(null, redPen, new System.Windows.Point(34, 60), 4.5, 4.5);
                        dc.DrawEllipse(null, bluePen, new System.Windows.Point(34, 34), 4.5, 4.5);
                        break;
                    }
                    case "notes":
                    {
                        dc.DrawGeometry(accentFill, null, Geometry.Parse("M 24,22 L 72,22 L 72,57 L 57,72 L 24,72 Z"));
                        dc.DrawGeometry(innerDark, null, Geometry.Parse("M 56,72 L 56,56 L 72,56 Z"));
                        var darkLinePen = new System.Windows.Media.Pen(innerDark, 3.4) { StartLineCap = PenLineCap.Round, EndLineCap = PenLineCap.Round };
                        dc.DrawLine(darkLinePen, new System.Windows.Point(32, 35), new System.Windows.Point(63, 35));
                        dc.DrawLine(darkLinePen, new System.Windows.Point(32, 46), new System.Windows.Point(59, 46));
                        dc.DrawLine(darkLinePen, new System.Windows.Point(32, 57), new System.Windows.Point(48, 57));
                        break;
                    }
                    case "gewerbe":
                    {
                        var emeraldGlow = new SolidColorBrush(System.Windows.Media.Color.FromArgb(75, 16, 185, 129));
                        dc.DrawEllipse(emeraldGlow, accentPen, new System.Windows.Point(48, 48), 27, 27);
                        dc.DrawGeometry(accentFill, null, Geometry.Parse("M 48,18 L 56,40 L 78,48 L 56,56 L 48,78 L 40,56 L 18,48 L 40,40 Z"));
                        var innerEmerald = new SolidColorBrush(System.Windows.Media.Color.FromRgb(52, 211, 153));
                        dc.DrawEllipse(innerEmerald, null, new System.Windows.Point(48, 48), 6.2, 6.2);
                        break;
                    }
                    default: // file_text / file_generic
                    {
                        dc.DrawGeometry(null, accentPen, Geometry.Parse("M 29,23 L 55,23 L 67,35 L 67,71 L 29,71 Z"));
                        dc.DrawLine(accentPen, new System.Windows.Point(37, 45), new System.Windows.Point(59, 45));
                        dc.DrawLine(accentPen, new System.Windows.Point(37, 56), new System.Windows.Point(53, 56));
                        break;
                    }
                }
            }

            var rtb = new RenderTargetBitmap(96, 96, 96, 96, PixelFormats.Pbgra32);
            rtb.Render(dv);
            rtb.Freeze();
            SystemBadgeCache[kind] = rtb;
            return rtb;
        }

        private static readonly Dictionary<string, BitmapSource?> ThumbnailCache = new(StringComparer.OrdinalIgnoreCase);

        public static BitmapSource? LoadImageThumbnailCached(string imagePath)
        {
            try
            {
                string key = $"{imagePath}|{File.GetLastWriteTimeUtc(imagePath).Ticks}";
                if (ThumbnailCache.TryGetValue(key, out var cached))
                    return cached;

                if (ThumbnailCache.Count > 250)
                    ThumbnailCache.Clear();

                var bi = new BitmapImage();
                bi.BeginInit();
                bi.CacheOption = BitmapCacheOption.OnLoad;
                bi.DecodePixelWidth = 96;
                bi.UriSource = new Uri(imagePath, UriKind.Absolute);
                bi.EndInit();
                bi.Freeze();
                ThumbnailCache[key] = bi;
                return bi;
            }
            catch
            {
                return null;
            }
        }

        public static List<OsDriveSidebarDto> ListMountedDrivesForSidebar()
        {
            var list = new List<OsDriveSidebarDto>();
            try
            {
                foreach (var d in DriveInfo.GetDrives())
                {
                    if (!d.IsReady) continue;
                    double freeGb = Math.Round(d.AvailableFreeSpace / (1024.0 * 1024.0 * 1024.0), 0);
                    string letter = d.Name.TrimEnd('\\');
                    string vol = string.IsNullOrWhiteSpace(d.VolumeLabel)
                        ? (d.DriveType == DriveType.Removable ? "USB" : "Laufwerk")
                        : d.VolumeLabel.Trim();
                    list.Add(new OsDriveSidebarDto
                    {
                        RootPath = d.RootDirectory.FullName,
                        Label = $"{vol} ({letter})",
                        FreeSpaceBadge = $"{freeGb:0} GB"
                    });
                }
            }
            catch { }
            return list;
        }

        private static readonly (string Key, string Title, string ShortLabel, string SubFolder, string ExeName, string IconPng, string ProcessName)[] KnownApps =
        {
            ("funkenwerk", "Zentrale", "Zentrale", "Funkenwerk", "Funkenwerk.exe", "funkenwerk.png", "Funkenwerk"),
            ("browser", "Browser", "Browser", "Funkenwerk Browser", "FunkenwerkBrowser.exe", "funkenwerk_browser.png", "FunkenwerkBrowser"),
            ("pdf", "PDF", "PDF", "Funkenwerk PDF", "FunkenwerkPdf.exe", "funkenwerk_pdf.png", "FunkenwerkPdf"),
            ("drucker", "Drucker", "Drucker", "Funkenwerk Drucker", "FunkenwerkDrucker.exe", "funkenwerk_drucker.png", "FunkenwerkDrucker"),
            ("ftp", "FTP", "FTP", "Funkenwerk FTP", "FunkenwerkFtp.exe", "funkenwerk_ftp.png", "FunkenwerkFtp"),
            ("ssh", "SSH", "SSH", "Funkenwerk SSH", "FunkenwerkSsh.exe", "funkenwerk_ssh.png", "FunkenwerkSsh"),
            ("player", "Mediaplayer", "Mediaplayer", "Funkenwerk Player", "FunkenwerkPlayer.exe", "funkenwerk_player.png", "FunkenwerkPlayer"),
            ("video", "Videostudio", "Videostudio", "Funkenwerk Video", "FunkenwerkVideo.exe", "funkenwerk_video.png", "FunkenwerkVideo"),
            ("tresor", "Tresor", "Tresor", "Funkenwerk Tresor", "FunkenwerkTresor.exe", "funkenwerk_tresor.png", "FunkenwerkTresor")
        };

        private static readonly Dictionary<string, BitmapSource?> IconCache = new(StringComparer.OrdinalIgnoreCase);
        private static List<OsCollectionAppDto>? s_cachedCollectionApps;
        private static readonly object s_appsCacheLock = new();
        private static readonly System.Collections.Concurrent.ConcurrentDictionary<uint, string> s_pidProcNameCache = new();

        public static List<OsCollectionAppDto> DiscoverCollectionApps()
        {
            if (s_cachedCollectionApps != null)
                return s_cachedCollectionApps;

            lock (s_appsCacheLock)
            {
                if (s_cachedCollectionApps != null)
                    return s_cachedCollectionApps;

                string root = OsVaultAndStorage.CollectionRootDir;
                var list = new List<OsCollectionAppDto>(KnownApps.Length);

                foreach (var spec in KnownApps)
                {
                    string folder = Path.Combine(root, spec.SubFolder);
                    string exePath = Path.Combine(folder, spec.ExeName);
                    bool installed = File.Exists(exePath);

                    BitmapSource? bmp = LoadAppIconCached(spec.Key, folder, spec.IconPng, exePath);

                    list.Add(new OsCollectionAppDto
                    {
                        Key = spec.Key,
                        Title = spec.Title,
                        ShortLabel = spec.ShortLabel,
                        SubFolder = spec.SubFolder,
                        ExeName = spec.ExeName,
                        IconFileName = spec.IconPng,
                        ProcessName = spec.ProcessName,
                        ExePath = exePath,
                        IsInstalled = installed,
                        IsRunning = false,
                        IconImage = bmp
                    });
                }

                s_cachedCollectionApps = list;
                return list;
            }
        }

        private static BitmapSource? LoadAppIconCached(string appKey, string appFolder, string pngFileName, string exePath)
        {
            // Distinct color-coded app icons for Browser (3/3 Red-Yellow-Green), PDF (Red), and Mediaplayer (VLC Orange)
            if (string.Equals(appKey, "browser", StringComparison.OrdinalIgnoreCase) ||
                string.Equals(appKey, "pdf", StringComparison.OrdinalIgnoreCase) ||
                string.Equals(appKey, "player", StringComparison.OrdinalIgnoreCase))
            {
                var customBadge = GetSystemBadgeIcon(appKey);
                if (!IconCache.ContainsKey(appFolder))
                {
                    IconCache[appFolder] = customBadge;
                    try
                    {
                        if (Directory.Exists(appFolder))
                        {
                            string pngPath = Path.Combine(appFolder, pngFileName);
                            SnipCaptureOverlayWindow.SaveBitmapSourceAsPng(customBadge, pngPath);
                        }
                    }
                    catch { }
                }
                return customBadge;
            }

            if (IconCache.TryGetValue(appFolder, out var cached))
                return cached;

            try
            {
                string pngPath = Path.Combine(appFolder, pngFileName);
                if (File.Exists(pngPath))
                {
                    var bi = new BitmapImage();
                    bi.BeginInit();
                    bi.CacheOption = BitmapCacheOption.OnLoad;
                    bi.DecodePixelWidth = 96;
                    bi.UriSource = new Uri(pngPath, UriKind.Absolute);
                    bi.EndInit();
                    bi.Freeze();
                    IconCache[appFolder] = bi;
                    return bi;
                }

                if (File.Exists(exePath))
                {
                    using var sysIcon = System.Drawing.Icon.ExtractAssociatedIcon(exePath);
                    if (sysIcon != null)
                    {
                        using var bmp = sysIcon.ToBitmap();
                        using var ms = new MemoryStream();
                        bmp.Save(ms, System.Drawing.Imaging.ImageFormat.Png);
                        ms.Position = 0;
                        var bi = new BitmapImage();
                        bi.BeginInit();
                        bi.CacheOption = BitmapCacheOption.OnLoad;
                        bi.StreamSource = ms;
                        bi.EndInit();
                        bi.Freeze();
                        IconCache[appFolder] = bi;
                        return bi;
                    }
                }
            }
            catch { }

            IconCache[appFolder] = null;
            return null;
        }

        public static string? ResolveInstalledWebView2Folder()
        {
            try
            {
                string[] baseDirs =
                {
                    @"C:\Program Files (x86)\Microsoft\EdgeWebView\Application",
                    @"C:\Program Files\Microsoft\EdgeWebView\Application",
                    @"C:\Program Files (x86)\Microsoft\EdgeCore",
                    @"C:\Program Files (x86)\Microsoft\Edge\Application"
                };

                foreach (string baseDir in baseDirs)
                {
                    if (!Directory.Exists(baseDir)) continue;
                    var verDirs = Directory.GetDirectories(baseDir)
                        .OrderByDescending(d => d, StringComparer.OrdinalIgnoreCase);
                    foreach (string vd in verDirs)
                    {
                        if (File.Exists(Path.Combine(vd, "msedgewebview2.exe")))
                            return vd;
                    }
                    if (File.Exists(Path.Combine(baseDir, "msedgewebview2.exe")))
                        return baseDir;
                }
            }
            catch { }
            return null;
        }

        public static bool LaunchOrFocusCollectionApp(string appKey, string? optionalFileArg = null)
        {
            var spec = KnownApps.FirstOrDefault(a => string.Equals(a.Key, appKey, StringComparison.OrdinalIgnoreCase));
            if (string.IsNullOrEmpty(spec.Key)) return false;

            string exePath = Path.Combine(OsVaultAndStorage.CollectionRootDir, spec.SubFolder, spec.ExeName);
            if (!File.Exists(exePath) && Directory.Exists(@"C:\Funkenwerk Collection"))
            {
                string altPath = Path.Combine(@"C:\Funkenwerk Collection", spec.SubFolder, spec.ExeName);
                if (File.Exists(altPath)) exePath = altPath;
            }
            if (!File.Exists(exePath)) return false;

            if (string.IsNullOrWhiteSpace(optionalFileArg))
            {
                try
                {
                    var existing = Process.GetProcessesByName(spec.ProcessName).FirstOrDefault(p => p.MainWindowHandle != IntPtr.Zero);
                    if (existing != null)
                    {
                        FocusWindowHandle(existing.MainWindowHandle);
                        return true;
                    }
                }
                catch { }
            }

            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = exePath,
                    WorkingDirectory = Path.GetDirectoryName(exePath) ?? "",
                    UseShellExecute = false
                };
                if (Directory.Exists(@"C:\Program Files\dotnet"))
                {
                    psi.Environment["DOTNET_ROOT"] = @"C:\Program Files\dotnet";
                    psi.Environment["DOTNET_ROLL_FORWARD"] = "LatestMajor";
                }
                psi.Environment["WEBVIEW2_ADDITIONAL_BROWSER_ARGUMENTS"] = "--no-sandbox --disable-gpu-sandbox --ignore-gpu-blocklist --enable-gpu-rasterization";
                string? wvFolder = ResolveInstalledWebView2Folder();
                if (!string.IsNullOrEmpty(wvFolder))
                    psi.Environment["WEBVIEW2_BROWSER_EXECUTABLE_FOLDER"] = wvFolder;

                if (!string.IsNullOrWhiteSpace(optionalFileArg))
                    psi.ArgumentList.Add(optionalFileArg);

                Process.Start(psi);
                return true;
            }
            catch
            {
                return false;
            }
        }

        private static string ResolveProcessNameFast(uint pid)
        {
            if (s_pidProcNameCache.TryGetValue(pid, out string? cached))
                return cached;

            if (s_pidProcNameCache.Count > 512)
                s_pidProcNameCache.Clear();

            IntPtr hProc = IntPtr.Zero;
            try
            {
                hProc = OpenProcess(PROCESS_QUERY_LIMITED_INFORMATION, false, pid);
                if (hProc != IntPtr.Zero)
                {
                    var sb = new StringBuilder(512);
                    int cap = sb.Capacity;
                    if (QueryFullProcessImageName(hProc, 0, sb, ref cap) && cap > 0)
                    {
                        string name = Path.GetFileNameWithoutExtension(sb.ToString(0, cap)) ?? "";
                        s_pidProcNameCache[pid] = name;
                        return name;
                    }
                }
            }
            catch { }
            finally
            {
                if (hProc != IntPtr.Zero)
                    CloseHandle(hProc);
            }

            return "";
        }

        public static List<OsOpenWindowDto> EnumerateFunkenwerkOpenWindows()
        {
            var result = new List<OsOpenWindowDto>();
            uint ownPid = (uint)Environment.ProcessId;
            IntPtr fgHwnd = GetForegroundWindow();

            var apps = DiscoverCollectionApps();
            var procToApp = apps.ToDictionary(a => a.ProcessName, a => a, StringComparer.OrdinalIgnoreCase);

            try
            {
                EnumWindows((hWnd, _) =>
                {
                    if (!IsWindowVisible(hWnd)) return true;
                    GetWindowThreadProcessId(hWnd, out uint pid);
                    if (pid == 0 || pid == ownPid) return true;

                    int len = GetWindowTextLength(hWnd);
                    if (len <= 0) return true;

                    string procName = ResolveProcessNameFast(pid);
                    if (string.IsNullOrEmpty(procName) || !procToApp.TryGetValue(procName, out var appDto))
                        return true;

                    var sb = new StringBuilder(len + 2);
                    GetWindowText(hWnd, sb, sb.Capacity);
                    string title = sb.ToString().Trim();
                    if (string.IsNullOrWhiteSpace(title)) return true;

                    result.Add(new OsOpenWindowDto
                    {
                        Hwnd = hWnd,
                        AppKey = appDto.Key,
                        ProcessId = (int)pid,
                        AppLabel = appDto.ShortLabel,
                        WindowTitle = title.Length > 24 ? title.Substring(0, 23) + "…" : title,
                        IconImage = appDto.IconImage,
                        IsActive = (hWnd == fgHwnd)
                    });
                    return true;
                }, IntPtr.Zero);
            }
            catch { }

            return result;
        }

        public static void ToggleOrFocusWindowHandle(IntPtr hWnd)
        {
            if (hWnd == IntPtr.Zero) return;
            try
            {
                if (IsIconic(hWnd))
                {
                    ShowWindow(hWnd, SW_RESTORE);
                    SetForegroundWindow(hWnd);
                }
                else if (GetForegroundWindow() == hWnd)
                {
                    ShowWindow(hWnd, SW_MINIMIZE);
                }
                else
                {
                    SetForegroundWindow(hWnd);
                }
            }
            catch { }
        }

        public static void FocusWindowHandle(IntPtr hWnd)
        {
            if (hWnd == IntPtr.Zero) return;
            try
            {
                if (IsIconic(hWnd))
                    ShowWindow(hWnd, SW_RESTORE);
                SetForegroundWindow(hWnd);
            }
            catch { }
        }

        public static void CloseWindowHandle(IntPtr hWnd)
        {
            if (hWnd == IntPtr.Zero) return;
            try
            {
                PostMessage(hWnd, WM_CLOSE, IntPtr.Zero, IntPtr.Zero);
            }
            catch { }
        }

        public static bool ApplyWindowsDefaultAssociations(OsSettings settings)
        {
            try
            {
                var apps = DiscoverCollectionApps().ToDictionary(a => a.Key, a => a.ExePath, StringComparer.OrdinalIgnoreCase);

                void RegisterExt(string ext, string progId, string friendlyName, string exePath)
                {
                    if (string.IsNullOrWhiteSpace(exePath) || !File.Exists(exePath)) return;
                    using var clsKey = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{progId}\shell\open\command", writable: true);
                    clsKey?.SetValue("", $"\"{exePath}\" \"%1\"");
                    using var nameKey = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{progId}", writable: true);
                    nameKey?.SetValue("", friendlyName);
                    using var extKey = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{ext}\OpenWithProgids", writable: true);
                    extKey?.SetValue(progId, Array.Empty<byte>(), RegistryValueKind.None);
                }

                if (settings.DefaultPrograms.TryGetValue("pdf", out string? pdfApp) && apps.TryGetValue(pdfApp, out string? pdfExe))
                    RegisterExt(".pdf", "FunkenwerkOS.PDF", "Funkenwerk PDF", pdfExe);

                if (apps.TryGetValue("tresor", out string? tresorExe))
                    RegisterExt(".kdbx", "FunkenwerkOS.KDBX", "Funkenwerk Tresor", tresorExe);

                if (settings.DefaultPrograms.TryGetValue("audio", out string? audApp) && apps.TryGetValue(audApp, out string? audExe))
                {
                    foreach (string ext in new[] { ".mp3", ".wav", ".flac", ".m4a", ".ogg" })
                        RegisterExt(ext, "FunkenwerkOS.Audio", "Funkenwerk Player", audExe);
                }

                if (settings.DefaultPrograms.TryGetValue("video", out string? vidApp) && apps.TryGetValue(vidApp, out string? vidExe))
                {
                    foreach (string ext in new[] { ".mp4", ".mkv", ".mov", ".webm" })
                        RegisterExt(ext, "FunkenwerkOS.Video", "Funkenwerk Player", vidExe);
                }

                return true;
            }
            catch
            {
                return false;
            }
        }

        // =====================================================================
        // POWER, ALWAYS-ON DISPLAY, 08:00 MORNING WAKE & DOTNET RUNTIME REPAIR
        // =====================================================================
        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern uint SetThreadExecutionState(uint esFlags);

        [DllImport("winmm.dll")]
        private static extern uint timeBeginPeriod(uint uPeriod);

        [DllImport("powrprof.dll", SetLastError = true)]
        private static extern bool SetSuspendState(bool hibernate, bool forceCritical, bool disableWakeEvent);

        [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
        private static extern IntPtr CreateWaitableTimer(IntPtr lpTimerAttributes, bool bManualReset, string? lpTimerName);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool SetWaitableTimer(IntPtr hTimer, [In] ref long pDueTime, int lPeriod, IntPtr pfnCompletionRoutine, IntPtr lpArgToCompletionRoutine, bool fResume);

        [DllImport("user32.dll")]
        private static extern void mouse_event(uint dwFlags, int dx, int dy, uint dwData, UIntPtr dwExtraInfo);

        private const uint MOUSEEVENTF_MOVE = 0x0001;
        private const uint ES_SYSTEM_REQUIRED = 0x00000001;
        private const uint ES_DISPLAY_REQUIRED = 0x00000002;
        private const uint ES_CONTINUOUS = 0x80000000;
        private static int _kioskPowerConfigured;
        private static IntPtr _rtcWakeTimerHandle = IntPtr.Zero;

        /// <summary>
        /// Arms a kernel-level Hardware CMOS RTC Wake Timer (fResume = true) for the next 08:00:00 AM local time.
        /// Works out of S3 Standby and S4 Hibernate without requiring Wake-on-LAN or BIOS edits.
        /// </summary>
        public static void ArmHardwareRtcWakeTimerFor0800()
        {
            try
            {
                if (_rtcWakeTimerHandle == IntPtr.Zero)
                {
                    _rtcWakeTimerHandle = CreateWaitableTimer(IntPtr.Zero, true, null);
                }
                if (_rtcWakeTimerHandle != IntPtr.Zero)
                {
                    DateTime now = DateTime.Now;
                    DateTime next0800 = now.Date.AddHours(8);
                    if (next0800 <= now.AddMinutes(1))
                        next0800 = next0800.AddDays(1);

                    long dueTimeFileTimeUtc = next0800.ToUniversalTime().ToFileTimeUtc();
                    SetWaitableTimer(_rtcWakeTimerHandle, ref dueTimeFileTimeUtc, 0, IntPtr.Zero, IntPtr.Zero, fResume: true);
                }
            }
            catch { }
        }

        /// <summary>
        /// Tells the Windows kernel that both the system and HDMI display must stay awake continuously.
        /// Call periodically on the UI thread (e.g. every 20-30s) so the monitor never turns off.
        /// </summary>
        public static void KeepDisplayAndSystemAwake()
        {
            try
            {
                timeBeginPeriod(1);
                SetThreadExecutionState(ES_CONTINUOUS | ES_SYSTEM_REQUIRED | ES_DISPLAY_REQUIRED);
            }
            catch { }
        }

        /// <summary>
        /// Puts the Mini-PC into deep sleep (S3 Standby) for the night while arming the hardware CMOS RTC wake timer
        /// for 08:00 AM so the PC and HDMI monitor wake up automatically on their own.
        /// </summary>
        public static void TriggerNightSleepUntilMorning0800()
        {
            try
            {
                ArmHardwareRtcWakeTimerFor0800();
                SetThreadExecutionState(ES_CONTINUOUS);
                SetSuspendState(hibernate: false, forceCritical: false, disableWakeEvent: false);
                WakeMonitors();
                ArmHardwareRtcWakeTimerFor0800();
            }
            catch { }
        }

        /// <summary>
        /// Runs once at startup on the Mini-PC to:
        /// 1. Disable all Windows energy saving, standby, hibernate, and monitor timeouts (0 minutes)
        /// 2. Enable Hardware RTC Wake Timers (RTCWAKE = 1) + register the 08:00 AM Morning Wake & Autostart Task
        /// 3. Repair .NET 8/9 Desktop Runtime Registry keys & remove broken desktop duplicates so Browser & all apps launch cleanly
        /// </summary>
        public static void EnsureKioskPowerAndDotNetIntegrity()
        {
            KeepDisplayAndSystemAwake();
            if (Interlocked.Exchange(ref _kioskPowerConfigured, 1) != 0)
                return;

            _ = Task.Run(() =>
            {
                try
                {
                    // 1. Ensure .NET x64 InstallLocation is registered in HKLM so FunkenwerkBrowser & all Collection apps never ask for .NET Desktop Runtime
                    if (Directory.Exists(@"C:\Program Files\dotnet"))
                    {
                        Environment.SetEnvironmentVariable("DOTNET_ROOT", @"C:\Program Files\dotnet", EnvironmentVariableTarget.Process);
                        Environment.SetEnvironmentVariable("DOTNET_ROLL_FORWARD", "LatestMajor", EnvironmentVariableTarget.Process);
                        try
                        {
                            using var k64 = Registry.LocalMachine.CreateSubKey(@"SOFTWARE\dotnet\Setup\InstalledVersions\x64", writable: true);
                            k64?.SetValue("InstallLocation", @"C:\Program Files\dotnet\", RegistryValueKind.String);
                            using var kWow = Registry.LocalMachine.CreateSubKey(@"SOFTWARE\WOW6432Node\dotnet\Setup\InstalledVersions\x64", writable: true);
                            kWow?.SetValue("InstallLocation", @"C:\Program Files\dotnet\", RegistryValueKind.String);
                            Environment.SetEnvironmentVariable("DOTNET_ROOT", @"C:\Program Files\dotnet", EnvironmentVariableTarget.Machine);
                        }
                        catch { }
                    }

                    // 2. Clean up broken partial Desktop copy on Mini-PC if C:\Funkenwerk Collection is primary
                    if (Directory.Exists(@"C:\Funkenwerk Collection\Funkenwerk Browser") &&
                        File.Exists(@"C:\Funkenwerk Collection\Funkenwerk Browser\FunkenwerkBrowser.runtimeconfig.json"))
                    {
                        string deskCol = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "Funkenwerk Collection");
                        string deskBrowserCfg = Path.Combine(deskCol, "Funkenwerk Browser", "FunkenwerkBrowser.runtimeconfig.json");
                        if (Directory.Exists(deskCol) && !File.Exists(deskBrowserCfg))
                        {
                            try { Directory.Delete(deskCol, recursive: true); } catch { }
                        }
                    }

                    // 3. Disable all Windows Standby, Monitor-Off & Hibernate timeouts + Enable RTC Hardware Wake Timers
                    string[] powerCmds =
                    {
                        "/setactive 8c5e7fda-e8bf-4a96-9a85-a6e23a8c635c",
                        "/change monitor-timeout-ac 0",
                        "/change monitor-timeout-dc 0",
                        "/change standby-timeout-ac 0",
                        "/change standby-timeout-dc 0",
                        "/change hibernate-timeout-ac 0",
                        "/change hibernate-timeout-dc 0",
                        "/change disk-timeout-ac 0",
                        "/change disk-timeout-dc 0",
                        "/h on",
                        // Disable VIDEOIDLE & CONSOLELOCK (prevents ScreenOffGracePeriod after wake!)
                        "/SETACVALUEINDEX SCHEME_CURRENT 7516b95f-f776-4464-8c53-06167f40cc99 3c0bc021-c8a8-4e07-a973-6b14cbcb2b7e 0",
                        "/SETDCVALUEINDEX SCHEME_CURRENT 7516b95f-f776-4464-8c53-06167f40cc99 3c0bc021-c8a8-4e07-a973-6b14cbcb2b7e 0",
                        "/SETACVALUEINDEX SCHEME_CURRENT 7516b95f-f776-4464-8c53-06167f40cc99 8ec4b3a5-6868-48c2-be75-4f3044be88a7 0",
                        "/SETDCVALUEINDEX SCHEME_CURRENT 7516b95f-f776-4464-8c53-06167f40cc99 8ec4b3a5-6868-48c2-be75-4f3044be88a7 0",
                        // Disable STANDBYIDLE & UNATTENDSLEEP (never sleep after unattended wake!)
                        "/SETACVALUEINDEX SCHEME_CURRENT 238c9fa8-0aad-41ed-83f4-97be242c8f20 29f6c1db-86da-48c5-9fdb-f2b67b1f44da 0",
                        "/SETDCVALUEINDEX SCHEME_CURRENT 238c9fa8-0aad-41ed-83f4-97be242c8f20 29f6c1db-86da-48c5-9fdb-f2b67b1f44da 0",
                        "/SETACVALUEINDEX SCHEME_CURRENT 238c9fa8-0aad-41ed-83f4-97be242c8f20 7bc4a2f9-d8fc-4469-b07b-33eb785aaca0 0",
                        "/SETDCVALUEINDEX SCHEME_CURRENT 238c9fa8-0aad-41ed-83f4-97be242c8f20 7bc4a2f9-d8fc-4469-b07b-33eb785aaca0 0",
                        // SUB_SLEEP (238c9fa8-0aad-41ed-83f4-97be242c8f20) -> RTCWAKE (bd3b718a-0680-4d9d-8ab2-e1d2b4ac806d) = 1 (Enable Wake Timers)
                        "/SETACVALUEINDEX SCHEME_CURRENT 238c9fa8-0aad-41ed-83f4-97be242c8f20 bd3b718a-0680-4d9d-8ab2-e1d2b4ac806d 1",
                        "/SETDCVALUEINDEX SCHEME_CURRENT 238c9fa8-0aad-41ed-83f4-97be242c8f20 bd3b718a-0680-4d9d-8ab2-e1d2b4ac806d 1",
                        // SUB_SLEEP -> HYBRIDSLEEP (94ac6d29-73ce-41a6-809f-6363ba21b47e) = 0
                        "/SETACVALUEINDEX SCHEME_CURRENT 238c9fa8-0aad-41ed-83f4-97be242c8f20 94ac6d29-73ce-41a6-809f-6363ba21b47e 0",
                        // USB selective suspend = 0 (keep Wi-Fi / Bluetooth USB adapters always powered)
                        "/SETACVALUEINDEX SCHEME_CURRENT 2a737441-1930-4402-8d77-b2bebba308a3 48e6b7a6-50f5-4782-a5d4-53bb8f07e226 0",
                        "/setactive SCHEME_CURRENT"
                    };

                    foreach (string arg in powerCmds)
                    {
                        try
                        {
                            using var p = Process.Start(new ProcessStartInfo("powercfg.exe", arg) { CreateNoWindow = true, UseShellExecute = false });
                            p?.WaitForExit(3000);
                        }
                        catch { }
                    }

                    // 4. Disable Windows Screen Saver & Lock Screen
                    try
                    {
                        using var deskKey = Registry.CurrentUser.CreateSubKey(@"Control Panel\Desktop", writable: true);
                        deskKey?.SetValue("ScreenSaveActive", "0", RegistryValueKind.String);
                        deskKey?.SetValue("SCRNSAVE.EXE", "", RegistryValueKind.String);

                        using var persKey = Registry.LocalMachine.CreateSubKey(@"SOFTWARE\Policies\Microsoft\Windows\Personalization", writable: true);
                        persKey?.SetValue("NoLockScreen", 1, RegistryValueKind.DWord);
                    }
                    catch { }

                    // 5. On Mini-PC (C:\Funkenwerk Collection\Funkenwerk OS\FunkenwerkOS.exe): Register Single Clean Shell Autostart + 08:00 AM Hardware RTC Wake + 22:00 PM Night Sleep
                    ArmHardwareRtcWakeTimerFor0800();

                    // Remove any restrictive DataCollection policy so Windows Update, Winget, Store & Driver Updates are never blocked
                    try
                    {
                        Registry.LocalMachine.DeleteSubKeyTree(@"SOFTWARE\Policies\Microsoft\Windows\DataCollection", throwOnMissingSubKey: false);
                    }
                    catch { }

                    string miniPcExe = @"C:\Funkenwerk Collection\Funkenwerk OS\FunkenwerkOS.exe";
                    if (File.Exists(miniPcExe))
                    {
                        try
                        {
                            // Keep explorer.exe as underlying Windows Shell so screen NEVER turns black if FunkenwerkOS restarts,
                            // and launch FunkenwerkOS (--shell --workspace gewerbe) via HKCU\Run!
                            using var runKey = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows\CurrentVersion\Run", writable: true);
                            runKey?.SetValue("FunkenwerkOS", $"\"{miniPcExe}\" --shell --workspace gewerbe", RegistryValueKind.String);

                            using var cuWinlogon = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows NT\CurrentVersion\Winlogon", writable: true);
                            cuWinlogon?.DeleteValue("Shell", throwOnMissingValue: false);

                            using var winlogon = Registry.LocalMachine.CreateSubKey(@"SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon", writable: true);
                            if (winlogon != null && string.Equals(Environment.UserName, "Alina", StringComparison.OrdinalIgnoreCase))
                            {
                                winlogon.SetValue("Shell", "explorer.exe", RegistryValueKind.String);
                                winlogon.SetValue("AutoAdminLogon", "1", RegistryValueKind.String);
                                winlogon.SetValue("DefaultUserName", "Alina", RegistryValueKind.String);
                                winlogon.SetValue("DefaultPassword", "", RegistryValueKind.String);
                            }
                        }
                        catch { }

                        RegisterMorning0800WakeTask(miniPcExe);
                    }

                    // 6. Close any hung legacy DevicePairingWizard / rundll32 shell popups, ensure WebView2 Firewall & Realtek BT Power, and auto-activate Teufel A2DP
                    try
                    {
                        foreach (var proc in Process.GetProcessesByName("DevicePairingWizard"))
                        {
                            try { proc.Kill(); } catch { }
                        }

                        // Disable Realtek BT USB Selective Suspend & Wi-Fi LowPower IPS + ensure WebView2 outbound internet access
                        try
                        {
                            using var btParamKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Enum\USB\VID_0BDA&PID_C821\00E04C000001\Device Parameters", writable: true);
                            btParamKey?.SetValue("DeviceSelectiveSuspended", 0, RegistryValueKind.DWord);
                            btParamKey?.SetValue("SelectiveSuspendEnabled", 0, RegistryValueKind.DWord);
                            btParamKey?.SetValue("EnhancedPowerManagementEnabled", 0, RegistryValueKind.DWord);
                            btParamKey?.SetValue("IdleInWorkingState", 0, RegistryValueKind.DWord);
                        }
                        catch { }

                        try
                        {
                            using var netClassKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Control\Class\{4d36e972-e325-11ce-bfc1-08002be10318}", writable: false);
                            if (netClassKey != null)
                            {
                                foreach (string subName in netClassKey.GetSubKeyNames())
                                {
                                    if (subName.Length != 4) continue;
                                    try
                                    {
                                        using var subKey = netClassKey.OpenSubKey(subName, writable: true);
                                        string desc = Convert.ToString(subKey?.GetValue("DriverDesc") ?? "") ?? "";
                                        if (desc.Contains("8821", StringComparison.OrdinalIgnoreCase) || desc.Contains("Wireless", StringComparison.OrdinalIgnoreCase))
                                        {
                                            subKey?.SetValue("bLowPowerEnable", "0", RegistryValueKind.String);
                                            subKey?.SetValue("Scan_IPSEnable", "0", RegistryValueKind.String);
                                            subKey?.SetValue("MultiChannelFcsMode", "0", RegistryValueKind.String);
                                            subKey?.SetValue("ConcurrentOpPref", "0", RegistryValueKind.String);
                                        }
                                    }
                                    catch { }
                                }
                            }

                            foreach (var audProc in Process.GetProcessesByName("audiodg"))
                            {
                                try { audProc.PriorityClass = ProcessPriorityClass.High; } catch { }
                            }
                        }
                        catch { }

                        var audioDevs = ListAudioOutputDevices();
                        var teufelAudio = audioDevs.FirstOrDefault(a => a.IsTeufel);
                        if (teufelAudio != null)
                        {
                            if (!teufelAudio.IsDefault)
                                SetDefaultAudioOutputDevice(teufelAudio.DeviceId);
                        }

                        var btList = ScanBluetoothDevicesAsync(issueHardwareInquiry: false).GetAwaiter().GetResult();
                        var teufelBt = btList.FirstOrDefault(b => b.IsTeufel && b.IsPaired);
                        if (teufelBt != null)
                        {
                            _ = ConnectOrPairBluetoothDeviceAsync(teufelBt).GetAwaiter().GetResult();
                        }
                    }
                    catch { }
                }
                catch { }
            });
        }

        private static void RegisterMorning0800WakeTask(string exePath)
        {
            try
            {
                string workDir = Path.GetDirectoryName(exePath) ?? @"C:\Funkenwerk Collection\Funkenwerk OS";
                string user = Environment.UserName;
                string xmlPathMorning = Path.Combine(Path.GetTempPath(), "fw_morning_wake_0800.xml");

                string morningXml = $@"<?xml version=""1.0"" encoding=""UTF-16""?>
<Task version=""1.3"" xmlns=""http://schemas.microsoft.com/windows/2004/02/mit/task"">
  <RegistrationInfo>
    <Description>Weckt den Manufaktur Mini-PC jeden Morgen um 08:00 Uhr automatisch per Hardware-RTC aus dem Ruhemodus und aktiviert den Monitor Gewerbebereich.</Description>
    <URI>\Funkenwerk_Morgenstart_0800</URI>
  </RegistrationInfo>
  <Triggers>
    <CalendarTrigger>
      <StartBoundary>2026-01-01T08:00:00</StartBoundary>
      <Enabled>true</Enabled>
      <ScheduleByDay>
        <DaysInterval>1</DaysInterval>
      </ScheduleByDay>
    </CalendarTrigger>
  </Triggers>
  <Principals>
    <Principal id=""Author"">
      <UserId>{user}</UserId>
      <LogonType>InteractiveToken</LogonType>
      <RunLevel>LeastPrivilege</RunLevel>
    </Principal>
  </Principals>
  <Settings>
    <MultipleInstancesPolicy>StopExisting</MultipleInstancesPolicy>
    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>
    <StopIfGoingOnBatteries>false</StopIfGoingOnBatteries>
    <AllowHardTerminate>true</AllowHardTerminate>
    <StartWhenAvailable>true</StartWhenAvailable>
    <RunOnlyIfNetworkAvailable>false</RunOnlyIfNetworkAvailable>
    <AllowStartOnDemand>true</AllowStartOnDemand>
    <Enabled>true</Enabled>
    <Hidden>false</Hidden>
    <RunOnlyIfIdle>false</RunOnlyIfIdle>
    <WakeToRun>true</WakeToRun>
    <ExecutionTimeLimit>PT0S</ExecutionTimeLimit>
    <Priority>4</Priority>
  </Settings>
  <Actions Context=""Author"">
    <Exec>
      <Command>{exePath}</Command>
      <Arguments>--wake-gewerbe</Arguments>
      <WorkingDirectory>{workDir}</WorkingDirectory>
    </Exec>
  </Actions>
</Task>";

                File.WriteAllText(xmlPathMorning, morningXml, Encoding.Unicode);
                using (var p = Process.Start(new ProcessStartInfo("schtasks.exe", $"/Create /TN \"Funkenwerk_Morgenstart_0800\" /XML \"{xmlPathMorning}\" /F")
                {
                    CreateNoWindow = true,
                    UseShellExecute = false
                }))
                {
                    p?.WaitForExit(5000);
                }

                // Remove automatic 22:00 sleep task so the Mini-PC NEVER enters standby on its own while running!
                // (Night sleep is triggered only explicitly via the "Nachtruhe (08:00)" button, which then wakes at 08:00 AM)
                using (var pDelNight = Process.Start(new ProcessStartInfo("schtasks.exe", "/Delete /TN \"Funkenwerk_Abendruhe_2200\" /F")
                {
                    CreateNoWindow = true,
                    UseShellExecute = false
                }))
                {
                    pDelNight?.WaitForExit(3000);
                }

                // Remove legacy duplicate logon task so only 1 instance runs at boot
                using (var pDel = Process.Start(new ProcessStartInfo("schtasks.exe", "/Delete /TN \"StartFunkenwerkOSNow\" /F")
                {
                    CreateNoWindow = true,
                    UseShellExecute = false
                }))
                {
                    pDel?.WaitForExit(3000);
                }
            }
            catch { }
        }

        public static void TurnOffMonitors(IntPtr ownerHwnd)
        {
            try
            {
                PostMessage(ownerHwnd, WM_SYSCOMMAND, new IntPtr(SC_MONITORPOWER), new IntPtr(2));
            }
            catch { }
        }

        public static void WakeMonitors()
        {
            try
            {
                KeepDisplayAndSystemAwake();
                PostMessage(new IntPtr(0xFFFF), WM_SYSCOMMAND, new IntPtr(SC_MONITORPOWER), new IntPtr(-1));
                mouse_event(MOUSEEVENTF_MOVE, 1, 0, 0, UIntPtr.Zero);
                mouse_event(MOUSEEVENTF_MOVE, -1, 0, 0, UIntPtr.Zero);
            }
            catch { }
        }

        public static void TriggerSystemReboot()
        {
            try
            {
                Process.Start(new ProcessStartInfo("shutdown.exe", "/r /t 2") { CreateNoWindow = true, UseShellExecute = false });
            }
            catch { }
        }

        public static void TriggerSystemShutdown()
        {
            try
            {
                Process.Start(new ProcessStartInfo("shutdown.exe", "/s /t 2") { CreateNoWindow = true, UseShellExecute = false });
            }
            catch { }
        }
    }
}
