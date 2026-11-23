using System;
using System.Diagnostics;
using System.Globalization;
using System.Reflection;
using System.Windows.Forms;
using System.Windows.Forms.Integration;

namespace FunkenwerkRemote
{
    public sealed class RdpActiveXHost : AxHost
    {
        public RdpActiveXHost(string clsid) : base(clsid)
        {
        }

        public object? RawOcx => GetOcx();
    }

    public sealed class RdpSessionHostControl : IDisposable
    {
        private static readonly string[] PreferredProgIds = new[]
        {
            "MsTscAx.MsTscAx.12",
            "MsTscAx.MsTscAx.11",
            "MsTscAx.MsTscAx.10",
            "MsTscAx.MsTscAx.9",
            "MsTscAx.MsTscAx"
        };

        private readonly Panel _containerPanel;
        private readonly RdpActiveXHost _axHost;
        private readonly WindowsFormsHost _wpfHost;
        private bool _isViewOnly;
        private bool _disposed;

        public WindowsFormsHost WpfHost => _wpfHost;
        public Panel ContainerPanel => _containerPanel;
        public bool IsViewOnly => _isViewOnly;
        public string ActiveProgId { get; }
        public string ActiveClsid { get; }

        public RdpSessionHostControl(bool initialViewOnly = true)
        {
            var (progId, clsid) = ResolveBestRdpClsid();
            ActiveProgId = progId;
            ActiveClsid = clsid;

            _containerPanel = new Panel
            {
                BackColor = System.Drawing.Color.FromArgb(11, 13, 18),
                Dock = DockStyle.Fill,
                Margin = new Padding(0),
                Padding = new Padding(0)
            };

            _axHost = new RdpActiveXHost(clsid)
            {
                Dock = DockStyle.Fill,
                Margin = new Padding(0)
            };

            _axHost.BeginInit();
            _containerPanel.Controls.Add(_axHost);
            _axHost.EndInit();

            // Force HWND creation so GetOcx() is immediately available even before layout pass
            _containerPanel.CreateControl();
            if (!_containerPanel.IsHandleCreated)
            {
                _ = _containerPanel.Handle;
            }
            _axHost.CreateControl();
            if (!_axHost.IsHandleCreated)
            {
                _ = _axHost.Handle;
            }

            SetViewOnly(initialViewOnly);

            _wpfHost = new WindowsFormsHost
            {
                Child = _containerPanel,
                Background = System.Windows.Media.Brushes.Black,
                Focusable = true
            };
        }

        public static (string ProgId, string Clsid) ResolveBestRdpClsid()
        {
            foreach (string progId in PreferredProgIds)
            {
                Type? comType = Type.GetTypeFromProgID(progId, throwOnError: false);
                if (comType != null && comType.GUID != Guid.Empty)
                {
                    return (progId, comType.GUID.ToString("D"));
                }
            }
            throw new InvalidOperationException("Windows RDP ActiveX-Komponente (mstscax.dll) wurde auf diesem System nicht gefunden.");
        }

        public void SetViewOnly(bool viewOnly)
        {
            _isViewOnly = viewOnly;
            if (!_disposed)
            {
                // Disabling the parent WinForms HWND blocks Win32 mouse/keyboard delivery while keeping live 60 FPS RDP rendering active
                _containerPanel.Enabled = !viewOnly;
            }
        }

        public void ConfigureConnection(
            string host,
            int port,
            string username,
            string password,
            int desktopWidth,
            int desktopHeight,
            bool smartSizing,
            bool useNla)
        {
            string cleanHost = FunkenwerkRemoteSecurity.SanitizeHost(host);
            if (port < 1 || port > 65535)
                throw new ArgumentOutOfRangeException(nameof(port), "RDP-Port muss zwischen 1 und 65535 liegen.");

            object ocx = _axHost.RawOcx
                ?? throw new InvalidOperationException("RDP-ActiveX-Instanz (OCX) ist noch nicht initialisiert.");

            int safeW = Math.Clamp(desktopWidth, 800, 7680);
            int safeH = Math.Clamp(desktopHeight, 600, 4320);

            bool isMiniPcStream = string.Equals(cleanHost, FunkenwerkRemoteSecurity.MiniPcDefaultHost, StringComparison.OrdinalIgnoreCase);

            SetComProperty(ocx, "Server", cleanHost);
            SetComProperty(ocx, "UserName", username?.Trim() ?? "");
            SetComProperty(ocx, "DesktopWidth", safeW);
            SetComProperty(ocx, "DesktopHeight", safeH);
            // 16-bit High-Speed Farbtiefe für den Linux-Mini-PC halbiert die Framebuffer-Bandbreite & verhindert 100%-CPU-Stau in xrdp
            TrySetComProperty(ocx, "ColorDepth", isMiniPcStream ? 16 : 32);
            TrySetComProperty(ocx, "FullScreenTitle", $"Funkenwerk Remote · {cleanHost}");

            object? adv2 = TryGetComProperty(ocx, "AdvancedSettings2");
            if (adv2 != null)
            {
                SetComProperty(adv2, "RDPPort", port);
                TrySetComProperty(adv2, "SmartSizing", smartSizing);
                TrySetComProperty(adv2, "Compression", 1);
                TrySetComProperty(adv2, "BitmapPeristence", 1);
                TrySetComProperty(adv2, "MinInputSendInterval", 10);
                if (!string.IsNullOrEmpty(password))
                {
                    SetComProperty(adv2, "ClearTextPassword", password);
                }
            }

            // Zero-Trust peripheral isolation + friction-free local LAN certificate handling
            object? adv5 = TryGetComProperty(ocx, "AdvancedSettings5");
            if (adv5 != null)
            {
                // 0 = Connect without modal warning on self-signed LAN xrdp certificates
                TrySetComProperty(adv5, "AuthenticationLevel", 0);
                TrySetComProperty(adv5, "RedirectClipboard", true);
                TrySetComProperty(adv5, "RedirectPrinters", false);
                TrySetComProperty(adv5, "RedirectDrives", false);
                TrySetComProperty(adv5, "RedirectPorts", false);
                TrySetComProperty(adv5, "RedirectSmartCards", false);
            }

            object? adv7 = TryGetComProperty(ocx, "AdvancedSettings7");
            if (adv7 != null)
            {
                // false for Ubuntu xrdp TLS/VNC sessions, optional true for strict Windows NLA hosts
                TrySetComProperty(adv7, "EnableCredSspSupport", useNla);
            }

            object? adv8 = TryGetComProperty(ocx, "AdvancedSettings8");
            if (adv8 != null)
            {
                TrySetComProperty(adv8, "EnableSuperPan", false);
            }
        }

        public (string Server, int Port, int Width, int Height, bool SmartSizing) ReadBackConfiguration()
        {
            object ocx = _axHost.RawOcx
                ?? throw new InvalidOperationException("RDP-ActiveX-Instanz (OCX) fehlt.");

            string srv = Convert.ToString(GetComProperty(ocx, "Server"), CultureInfo.InvariantCulture) ?? "";
            int w = Convert.ToInt32(GetComProperty(ocx, "DesktopWidth"), CultureInfo.InvariantCulture);
            int h = Convert.ToInt32(GetComProperty(ocx, "DesktopHeight"), CultureInfo.InvariantCulture);

            int port = 3389;
            bool smart = false;
            object? adv2 = TryGetComProperty(ocx, "AdvancedSettings2");
            if (adv2 != null)
            {
                object? pVal = TryGetComProperty(adv2, "RDPPort");
                if (pVal != null) port = Convert.ToInt32(pVal, CultureInfo.InvariantCulture);
                object? sVal = TryGetComProperty(adv2, "SmartSizing");
                if (sVal != null) smart = Convert.ToBoolean(sVal, CultureInfo.InvariantCulture);
            }

            return (srv, port, w, h, smart);
        }

        /// <summary>
        /// 0 = Disconnected, 1 = Connected, 2 = Connecting
        /// </summary>
        public int GetConnectionState()
        {
            if (_disposed) return 0;
            try
            {
                object? ocx = _axHost.RawOcx;
                if (ocx == null) return 0;
                object? val = TryGetComProperty(ocx, "Connected");
                return val != null ? Convert.ToInt32(val, CultureInfo.InvariantCulture) : 0;
            }
            catch
            {
                return 0;
            }
        }

        public void Connect()
        {
            object ocx = _axHost.RawOcx
                ?? throw new InvalidOperationException("RDP-ActiveX-Instanz (OCX) ist nicht bereit.");
            InvokeComMethod(ocx, "Connect");
        }

        public void Disconnect()
        {
            if (_disposed) return;
            try
            {
                int state = GetConnectionState();
                if (state != 0 && _axHost.RawOcx != null)
                {
                    InvokeComMethod(_axHost.RawOcx, "Disconnect");
                }
            }
            catch
            {
                // Ignore if remote end already closed socket
            }
        }

        public static void LaunchExternalMstsc(string host, int port)
        {
            string cleanHost = FunkenwerkRemoteSecurity.SanitizeHost(host);
            int cleanPort = (port > 0 && port <= 65535) ? port : 3389;
            string endpoint = cleanPort == 3389 ? cleanHost : $"{cleanHost}:{cleanPort}";

            var psi = new ProcessStartInfo
            {
                FileName = "mstsc.exe",
                UseShellExecute = false
            };
            psi.ArgumentList.Add($"/v:{endpoint}");
            Process.Start(psi);
        }

        private static object? GetComProperty(object target, string propertyName)
        {
            return target.GetType().InvokeMember(
                propertyName,
                BindingFlags.GetProperty,
                binder: null,
                target: target,
                args: null,
                culture: CultureInfo.InvariantCulture);
        }

        private static object? TryGetComProperty(object target, string propertyName)
        {
            try
            {
                return GetComProperty(target, propertyName);
            }
            catch
            {
                return null;
            }
        }

        private static void SetComProperty(object target, string propertyName, object? value)
        {
            target.GetType().InvokeMember(
                propertyName,
                BindingFlags.SetProperty,
                binder: null,
                target: target,
                args: new[] { value },
                culture: CultureInfo.InvariantCulture);
        }

        private static void TrySetComProperty(object target, string propertyName, object? value)
        {
            try
            {
                SetComProperty(target, propertyName, value);
            }
            catch
            {
                // Optional newer RDP client sub-interface property
            }
        }

        private static object? InvokeComMethod(object target, string methodName, params object[] args)
        {
            return target.GetType().InvokeMember(
                methodName,
                BindingFlags.InvokeMethod,
                binder: null,
                target: target,
                args: args,
                culture: CultureInfo.InvariantCulture);
        }

        public void Dispose()
        {
            if (_disposed) return;
            _disposed = true;
            try { Disconnect(); } catch { }
            try { _wpfHost.Child = null; } catch { }
            try { _wpfHost.Dispose(); } catch { }
            try { _axHost.Dispose(); } catch { }
            try { _containerPanel.Dispose(); } catch { }
        }
    }
}
