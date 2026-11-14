using System;
using System.IO;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Interop;

namespace FunkenwerkTresor
{
    public static class TresorSecurityGuard
    {
        public const uint WDA_NONE = 0x00000000;
        public const uint WDA_EXCLUDEFROMCAPTURE = 0x00000011;

        private const uint GW_HWNDNEXT = 2;
        private const uint GW_OWNER = 4;
        private const int GWL_EXSTYLE = -20;
        private const int WS_EX_TOOLWINDOW = 0x00000080;

        private const uint INPUT_KEYBOARD = 1;
        private const uint KEYEVENTF_KEYUP = 0x0002;

        private const ushort VK_TAB = 0x09;
        private const ushort VK_RETURN = 0x0D;
        private const ushort VK_CONTROL = 0x11;
        private const ushort VK_V = 0x56;

        [StructLayout(LayoutKind.Sequential)]
        private struct INPUT
        {
            public uint type;
            public InputUnion u;
        }

        [StructLayout(LayoutKind.Explicit)]
        private struct InputUnion
        {
            [FieldOffset(0)]
            public MOUSEINPUT mi;
            [FieldOffset(0)]
            public KEYBDINPUT ki;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct MOUSEINPUT
        {
            public int dx;
            public int dy;
            public uint mouseData;
            public uint dwFlags;
            public uint time;
            public UIntPtr dwExtraInfo;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct KEYBDINPUT
        {
            public ushort wVk;
            public ushort wScan;
            public uint dwFlags;
            public uint time;
            public UIntPtr dwExtraInfo;
        }

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool SetWindowDisplayAffinity(IntPtr hWnd, uint dwAffinity);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool GetWindowDisplayAffinity(IntPtr hWnd, out uint pdwAffinity);

        [DllImport("user32.dll")]
        private static extern IntPtr GetForegroundWindow();

        [DllImport("user32.dll")]
        private static extern bool SetForegroundWindow(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern IntPtr GetWindow(IntPtr hWnd, uint uCmd);

        [DllImport("user32.dll")]
        private static extern bool IsWindow(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern bool IsWindowVisible(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern bool IsIconic(IntPtr hWnd);

        [DllImport("user32.dll", EntryPoint = "GetWindowLongPtrW")]
        private static extern IntPtr GetWindowLongPtr(IntPtr hWnd, int nIndex);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern uint SendInput(uint nInputs, INPUT[] pInputs, int cbSize);

        private const uint CF_UNICODETEXT = 13;
        private const uint GHND = 0x0042;
        private static readonly IntPtr HWND_MESSAGE = new IntPtr(-3);

        [ThreadStatic]
        private static IntPtr _threadClipboardOwnerHwnd;

        [DllImport("user32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
        private static extern IntPtr CreateWindowExW(
            uint dwExStyle,
            string lpClassName,
            string lpWindowName,
            uint dwStyle,
            int x,
            int y,
            int nWidth,
            int nHeight,
            IntPtr hWndParent,
            IntPtr hMenu,
            IntPtr hInstance,
            IntPtr lpParam);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool OpenClipboard(IntPtr hWndNewOwner);

        internal static IntPtr EnsureClipboardOwnerHwnd()
        {
            if (_threadClipboardOwnerHwnd != IntPtr.Zero && IsWindow(_threadClipboardOwnerHwnd))
                return _threadClipboardOwnerHwnd;

            _threadClipboardOwnerHwnd = CreateWindowExW(
                0, "STATIC", "FwTresorClipboardOwner", 0,
                0, 0, 0, 0,
                HWND_MESSAGE, IntPtr.Zero, IntPtr.Zero, IntPtr.Zero);
            return _threadClipboardOwnerHwnd;
        }

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool CloseClipboard();

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool EmptyClipboard();

        [DllImport("user32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
        private static extern uint RegisterClipboardFormat(string lpszFormat);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern IntPtr SetClipboardData(uint uFormat, IntPtr hMem);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern IntPtr GetClipboardData(uint uFormat);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool IsClipboardFormatAvailable(uint format);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern IntPtr GlobalAlloc(uint uFlags, UIntPtr dwBytes);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern IntPtr GlobalLock(IntPtr hMem);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool GlobalUnlock(IntPtr hMem);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern IntPtr GlobalFree(IntPtr hMem);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern UIntPtr GlobalSize(IntPtr hMem);

        private static bool SetClipboardDwordLocked(string formatName, int value)
        {
            uint fmt = RegisterClipboardFormat(formatName);
            if (fmt == 0) return false;

            IntPtr hGlobal = GlobalAlloc(GHND, (UIntPtr)4);
            if (hGlobal == IntPtr.Zero) return false;

            IntPtr ptr = GlobalLock(hGlobal);
            if (ptr == IntPtr.Zero)
            {
                GlobalFree(hGlobal);
                return false;
            }

            try
            {
                Marshal.WriteInt32(ptr, value);
            }
            finally
            {
                GlobalUnlock(hGlobal);
            }

            if (SetClipboardData(fmt, hGlobal) == IntPtr.Zero)
            {
                GlobalFree(hGlobal);
                return false;
            }
            return true;
        }

        private static bool TrySetWin32ProtectedClipboard(string secretValue)
        {
            IntPtr ownerHwnd = EnsureClipboardOwnerHwnd();
            for (int attempt = 0; attempt < 5; attempt++)
            {
                if (OpenClipboard(ownerHwnd))
                {
                    try
                    {
                        if (!EmptyClipboard())
                            return false;

                        // Set all 3 exclusion flags BEFORE placing secret text; abort & empty if any flag fails
                        if (!SetClipboardDwordLocked("ExcludeClipboardContentFromMonitorProcessing", 1) ||
                            !SetClipboardDwordLocked("CanIncludeInClipboardHistory", 0) ||
                            !SetClipboardDwordLocked("CanUploadToCloudClipboard", 0))
                        {
                            EmptyClipboard();
                            return false;
                        }

                        byte[] unicodeBytes = Encoding.Unicode.GetBytes(secretValue + "\0");
                        IntPtr hText = GlobalAlloc(GHND, (UIntPtr)unicodeBytes.Length);
                        if (hText == IntPtr.Zero)
                        {
                            CryptographicOperations.ZeroMemory(unicodeBytes);
                            EmptyClipboard();
                            return false;
                        }

                        IntPtr pText = GlobalLock(hText);
                        if (pText == IntPtr.Zero)
                        {
                            CryptographicOperations.ZeroMemory(unicodeBytes);
                            GlobalFree(hText);
                            EmptyClipboard();
                            return false;
                        }

                        try
                        {
                            Marshal.Copy(unicodeBytes, 0, pText, unicodeBytes.Length);
                        }
                        finally
                        {
                            GlobalUnlock(hText);
                            CryptographicOperations.ZeroMemory(unicodeBytes);
                        }

                        if (SetClipboardData(CF_UNICODETEXT, hText) == IntPtr.Zero)
                        {
                            IntPtr pWipe = GlobalLock(hText);
                            if (pWipe != IntPtr.Zero)
                            {
                                for (int i = 0; i < unicodeBytes.Length; i++) Marshal.WriteByte(pWipe, i, 0);
                                GlobalUnlock(hText);
                            }
                            GlobalFree(hText);
                            EmptyClipboard();
                            return false;
                        }

                        return true;
                    }
                    finally
                    {
                        CloseClipboard();
                    }
                }

                if (attempt < 4)
                    Thread.Sleep(25);
            }
            return false;
        }

        public static bool TryReadWin32ClipboardDword(string formatName, out int value)
        {
            value = -999;
            uint fmt = RegisterClipboardFormat(formatName);
            if (fmt == 0) return false;
            for (int attempt = 0; attempt < 5; attempt++)
            {
                if (OpenClipboard(IntPtr.Zero))
                {
                    try
                    {
                        if (!IsClipboardFormatAvailable(fmt)) return false;
                        IntPtr hData = GetClipboardData(fmt);
                        if (hData == IntPtr.Zero) return false;
                        UIntPtr sz = GlobalSize(hData);
                        if (sz.ToUInt64() < 4) return false;
                        IntPtr ptr = GlobalLock(hData);
                        if (ptr == IntPtr.Zero) return false;
                        try
                        {
                            value = Marshal.ReadInt32(ptr);
                            return true;
                        }
                        finally
                        {
                            GlobalUnlock(hData);
                        }
                    }
                    finally
                    {
                        CloseClipboard();
                    }
                }
                if (attempt < 4)
                    Thread.Sleep(25);
            }
            return false;
        }

        public static bool TryReadWin32ClipboardUnicodeText(out string text)
        {
            text = "";
            for (int attempt = 0; attempt < 5; attempt++)
            {
                if (OpenClipboard(IntPtr.Zero))
                {
                    try
                    {
                        if (!IsClipboardFormatAvailable(CF_UNICODETEXT)) return true;
                        IntPtr hData = GetClipboardData(CF_UNICODETEXT);
                        if (hData == IntPtr.Zero) return true;
                        IntPtr ptr = GlobalLock(hData);
                        if (ptr == IntPtr.Zero) return false;
                        try
                        {
                            text = Marshal.PtrToStringUni(ptr) ?? "";
                            return true;
                        }
                        finally
                        {
                            GlobalUnlock(hData);
                        }
                    }
                    finally
                    {
                        CloseClipboard();
                    }
                }
                if (attempt < 4)
                    Thread.Sleep(25);
            }
            return false;
        }

        private static INPUT CreateKeyInput(ushort vk, bool keyUp)
        {
            return new INPUT
            {
                type = INPUT_KEYBOARD,
                u = new InputUnion
                {
                    ki = new KEYBDINPUT
                    {
                        wVk = vk,
                        wScan = 0,
                        dwFlags = keyUp ? KEYEVENTF_KEYUP : 0U,
                        time = 0,
                        dwExtraInfo = UIntPtr.Zero
                    }
                }
            };
        }

        private static bool SendCtrlV()
        {
            INPUT[] inputs =
            {
                CreateKeyInput(VK_CONTROL, keyUp: false),
                CreateKeyInput(VK_V, keyUp: false),
                CreateKeyInput(VK_V, keyUp: true),
                CreateKeyInput(VK_CONTROL, keyUp: true)
            };
            return SendInput((uint)inputs.Length, inputs, Marshal.SizeOf<INPUT>()) == inputs.Length;
        }

        private static bool SendSingleVk(ushort vk)
        {
            INPUT[] inputs =
            {
                CreateKeyInput(vk, keyUp: false),
                CreateKeyInput(vk, keyUp: true)
            };
            return SendInput((uint)inputs.Length, inputs, Marshal.SizeOf<INPUT>()) == inputs.Length;
        }

        private static IntPtr FindPreviousTopLevelWindow(IntPtr excludeHwnd)
        {
            IntPtr cur = excludeHwnd;
            for (int guard = 0; guard < 256 && cur != IntPtr.Zero; guard++)
            {
                cur = GetWindow(cur, GW_HWNDNEXT);
                if (cur == IntPtr.Zero || cur == excludeHwnd) continue;
                if (!IsWindowVisible(cur) || IsIconic(cur)) continue;
                if (GetWindow(cur, GW_OWNER) != IntPtr.Zero) continue;

                long exStyle = GetWindowLongPtr(cur, GWL_EXSTYLE).ToInt64();
                if ((exStyle & WS_EX_TOOLWINDOW) != 0) continue;

                return cur;
            }
            return IntPtr.Zero;
        }

        private static byte[]? _activeCopiedSecretSha256;
        private static DateTime _clipboardExpireUtc = DateTime.MinValue;
        private static double _clipboardTotalSeconds = 12.0;

        public static bool CopySensitiveToClipboard(string secretValue, int clearSeconds = 12)
        {
            _clipboardTotalSeconds = Math.Max(5, clearSeconds);
            bool ok = CopySecretToClipboardProtected(secretValue, _clipboardTotalSeconds);
            return ok;
        }

        public static bool TryGetClipboardRemainingSeconds(out double remainingSeconds, out double totalSeconds)
        {
            remainingSeconds = 0;
            totalSeconds = _clipboardTotalSeconds;
            if (_activeCopiedSecretSha256 == null || _clipboardExpireUtc == DateTime.MinValue)
                return false;

            double rem = (_clipboardExpireUtc - DateTime.UtcNow).TotalSeconds;
            if (rem <= 0)
            {
                if (ClearClipboardIfStillHoldingSecret())
                {
                    _clipboardExpireUtc = DateTime.MinValue;
                }
                else
                {
                    // Retry on next tick if another process momentarily locked the clipboard
                    _clipboardExpireUtc = DateTime.UtcNow.AddMilliseconds(250);
                }
                return false;
            }

            remainingSeconds = rem;
            return true;
        }

        public static void ClearClipboardNow()
        {
            if (ClearClipboardIfStillHoldingSecret())
                _clipboardExpireUtc = DateTime.MinValue;
            else if (_activeCopiedSecretSha256 == null)
                _clipboardExpireUtc = DateTime.MinValue;
        }

        public static bool ApplyScreenCaptureProtection(Window window, bool enable)
        {
            try
            {
                IntPtr hwnd = new WindowInteropHelper(window).Handle;
                if (hwnd == IntPtr.Zero) return false;
                return SetWindowDisplayAffinity(hwnd, enable ? WDA_EXCLUDEFROMCAPTURE : WDA_NONE);
            }
            catch
            {
                return false;
            }
        }

        public static bool TryQueryWindowDisplayAffinity(Window window, out uint affinity)
        {
            affinity = 0;
            try
            {
                IntPtr hwnd = new WindowInteropHelper(window).Handle;
                if (hwnd == IntPtr.Zero) return false;
                return GetWindowDisplayAffinity(hwnd, out affinity);
            }
            catch
            {
                return false;
            }
        }

        public static DataObject BuildProtectedClipboardDataObject(string secretValue)
        {
            var dataObj = new DataObject();
            dataObj.SetData(DataFormats.UnicodeText, secretValue);
            dataObj.SetData(DataFormats.Text, secretValue);

            // Prevent Windows 10/11 Clipboard History (Win+V), Cloud Clipboard, and clipboard monitors from storing secrets
            dataObj.SetData("ExcludeClipboardContentFromMonitorProcessing", new MemoryStream(BitConverter.GetBytes(1)), autoConvert: false);
            dataObj.SetData("CanIncludeInClipboardHistory", new MemoryStream(BitConverter.GetBytes(0)), autoConvert: false);
            dataObj.SetData("CanUploadToCloudClipboard", new MemoryStream(BitConverter.GetBytes(0)), autoConvert: false);
            return dataObj;
        }

        public static bool CopySecretToClipboardProtected(string secretValue, double clearSeconds = 12.0)
        {
            if (string.IsNullOrEmpty(secretValue)) return false;

            byte[] utf8 = Encoding.UTF8.GetBytes(secretValue);
            byte[] secretHash;
            try
            {
                secretHash = SHA256.HashData(utf8);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(utf8);
            }

            // Retry SetDataObject up to 5 times with 25ms backoff; NEVER fall back to unguarded Clipboard.SetText!
            bool copied = false;
            for (int attempt = 0; attempt < 5; attempt++)
            {
                try
                {
                    var dataObj = BuildProtectedClipboardDataObject(secretValue);
                    Clipboard.SetDataObject(dataObj, copy: true);
                    copied = true;
                    break;
                }
                catch
                {
                    if (attempt < 4)
                        Thread.Sleep(25);
                }
            }

            // If OLE clipboard broker is unavailable (e.g. restricted desktop/sandbox), use guarded Win32 clipboard with identical exclusion flags
            if (!copied)
            {
                copied = TrySetWin32ProtectedClipboard(secretValue);
            }

            if (copied)
            {
                _activeCopiedSecretSha256 = secretHash;
                _clipboardTotalSeconds = Math.Max(5.0, clearSeconds);
                _clipboardExpireUtc = DateTime.UtcNow.AddSeconds(_clipboardTotalSeconds);
            }
            else
            {
                CryptographicOperations.ZeroMemory(secretHash);
            }

            return copied;
        }

        public static bool ClearClipboardIfStillHoldingSecret()
        {
            if (_activeCopiedSecretSha256 == null) return true;

            for (int attempt = 0; attempt < 5; attempt++)
            {
                try
                {
                    if (!Clipboard.ContainsText())
                    {
                        _activeCopiedSecretSha256 = null;
                        return true;
                    }

                    string current = Clipboard.GetText() ?? "";
                    byte[] utf8 = Encoding.UTF8.GetBytes(current);
                    byte[] currentHash = SHA256.HashData(utf8);
                    CryptographicOperations.ZeroMemory(utf8);

                    if (CryptographicOperations.FixedTimeEquals(currentHash, _activeCopiedSecretSha256))
                    {
                        Clipboard.Clear();
                    }

                    _activeCopiedSecretSha256 = null;
                    return true;
                }
                catch
                {
                    if (attempt < 4)
                        Thread.Sleep(25);
                }
            }

            // Fallback to direct Win32 clipboard clear if OLE clipboard broker threw COMException
            IntPtr ownerHwnd = EnsureClipboardOwnerHwnd();
            for (int attempt = 0; attempt < 5; attempt++)
            {
                if (OpenClipboard(ownerHwnd))
                {
                    try
                    {
                        if (!IsClipboardFormatAvailable(CF_UNICODETEXT))
                        {
                            _activeCopiedSecretSha256 = null;
                            return true;
                        }

                        IntPtr hData = GetClipboardData(CF_UNICODETEXT);
                        if (hData != IntPtr.Zero)
                        {
                            IntPtr ptr = GlobalLock(hData);
                            if (ptr != IntPtr.Zero)
                            {
                                string current = "";
                                try { current = Marshal.PtrToStringUni(ptr) ?? ""; }
                                finally { GlobalUnlock(hData); }

                                byte[] utf8 = Encoding.UTF8.GetBytes(current);
                                byte[] currentHash = SHA256.HashData(utf8);
                                CryptographicOperations.ZeroMemory(utf8);

                                if (CryptographicOperations.FixedTimeEquals(currentHash, _activeCopiedSecretSha256))
                                {
                                    EmptyClipboard();
                                }
                            }
                        }

                        _activeCopiedSecretSha256 = null;
                        return true;
                    }
                    finally
                    {
                        CloseClipboard();
                    }
                }
                if (attempt < 4)
                    Thread.Sleep(25);
            }

            return false;
        }

        public static bool HasActiveClipboardSecret => _activeCopiedSecretSha256 != null;

        public static async Task<bool> PerformAutoTypeToPreviousWindowAsync(Window tresorWindow, string userName, string password)
        {
            try
            {
                IntPtr tresorHwnd = new WindowInteropHelper(tresorWindow).Handle;
                IntPtr currentFg = GetForegroundWindow();
                IntPtr targetHwnd = (currentFg != IntPtr.Zero && currentFg != tresorHwnd && IsWindow(currentFg))
                    ? currentFg
                    : FindPreviousTopLevelWindow(tresorHwnd);

                if (targetHwnd == IntPtr.Zero || targetHwnd == tresorHwnd || !IsWindow(targetHwnd))
                    return false;

                tresorWindow.WindowState = WindowState.Minimized;
                SetForegroundWindow(targetHwnd);
                await Task.Delay(280);

                if (GetForegroundWindow() != targetHwnd || !IsWindow(targetHwnd))
                    return false;

                if (!string.IsNullOrEmpty(userName))
                {
                    if (!CopySecretToClipboardProtected(userName, _clipboardTotalSeconds))
                        return false;
                    if (GetForegroundWindow() != targetHwnd)
                        return false;
                    if (!SendCtrlV())
                        return false;
                    await Task.Delay(95);
                    if (GetForegroundWindow() != targetHwnd)
                        return false;
                    if (!SendSingleVk(VK_TAB))
                        return false;
                    await Task.Delay(95);
                }

                if (!string.IsNullOrEmpty(password))
                {
                    if (!CopySecretToClipboardProtected(password, _clipboardTotalSeconds))
                        return false;
                    if (GetForegroundWindow() != targetHwnd)
                        return false;
                    if (!SendCtrlV())
                        return false;
                    await Task.Delay(95);
                    if (GetForegroundWindow() != targetHwnd)
                        return false;
                    if (!SendSingleVk(VK_RETURN))
                        return false;
                }

                await Task.Delay(250);
                return true;
            }
            catch
            {
                return false;
            }
            finally
            {
                if (!ClearClipboardIfStillHoldingSecret())
                {
                    _clipboardExpireUtc = DateTime.UtcNow.AddSeconds(Math.Max(5.0, _clipboardTotalSeconds));
                }
                else
                {
                    _clipboardExpireUtc = DateTime.MinValue;
                }
            }
        }
    }
}
