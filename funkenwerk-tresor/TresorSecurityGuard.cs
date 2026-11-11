using System;
using System.IO;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Interop;

namespace FunkenwerkTresor
{
    public static class TresorSecurityGuard
    {
        private const uint WDA_NONE = 0x00000000;
        private const uint WDA_EXCLUDEFROMCAPTURE = 0x00000011;

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool SetWindowDisplayAffinity(IntPtr hWnd, uint dwAffinity);

        [DllImport("user32.dll")]
        private static extern IntPtr GetForegroundWindow();

        [DllImport("user32.dll")]
        private static extern bool SetForegroundWindow(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern void keybd_event(byte bVk, byte bScan, uint dwFlags, UIntPtr dwExtraInfo);

        private const byte VK_TAB = 0x09;
        private const byte VK_RETURN = 0x0D;
        private const byte VK_CONTROL = 0x11;
        private const byte VK_V = 0x56;
        private const uint KEYEVENTF_KEYUP = 0x0002;

        private static void SendCtrlV()
        {
            keybd_event(VK_CONTROL, 0, 0, UIntPtr.Zero);
            keybd_event(VK_V, 0, 0, UIntPtr.Zero);
            keybd_event(VK_V, 0, KEYEVENTF_KEYUP, UIntPtr.Zero);
            keybd_event(VK_CONTROL, 0, KEYEVENTF_KEYUP, UIntPtr.Zero);
        }

        private static void SendSingleVk(byte vk)
        {
            keybd_event(vk, 0, 0, UIntPtr.Zero);
            keybd_event(vk, 0, KEYEVENTF_KEYUP, UIntPtr.Zero);
        }

        private static byte[]? _activeCopiedSecretSha256;
        private static DateTime _clipboardExpireUtc = DateTime.MinValue;
        private static double _clipboardTotalSeconds = 12.0;

        public static void CopySensitiveToClipboard(string secretValue, int clearSeconds = 12)
        {
            CopySecretToClipboardProtected(secretValue);
            _clipboardTotalSeconds = Math.Max(5, clearSeconds);
            _clipboardExpireUtc = DateTime.UtcNow.AddSeconds(_clipboardTotalSeconds);
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
                ClearClipboardIfStillHoldingSecret();
                _clipboardExpireUtc = DateTime.MinValue;
                return false;
            }

            remainingSeconds = rem;
            return true;
        }

        public static void ClearClipboardNow()
        {
            ClearClipboardIfStillHoldingSecret();
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

        public static void CopySecretToClipboardProtected(string secretValue)
        {
            if (string.IsNullOrEmpty(secretValue)) return;

            byte[] utf8 = Encoding.UTF8.GetBytes(secretValue);
            try
            {
                _activeCopiedSecretSha256 = SHA256.HashData(utf8);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(utf8);
            }

            try
            {
                var dataObj = new DataObject();
                dataObj.SetData(DataFormats.UnicodeText, secretValue);
                dataObj.SetData(DataFormats.Text, secretValue);

                // Tell Windows 10/11 Clipboard History (Win+V) and Cloud Sync to NEVER store this secret
                dataObj.SetData("ExcludeClipboardContentFromMonitorProcessing", new MemoryStream(BitConverter.GetBytes(1)));
                dataObj.SetData("CanIncludeInClipboardHistory", new MemoryStream(BitConverter.GetBytes(0)));
                dataObj.SetData("CanUploadToCloudClipboard", new MemoryStream(BitConverter.GetBytes(0)));

                Clipboard.SetDataObject(dataObj, copy: false);
            }
            catch
            {
                try { Clipboard.SetText(secretValue); } catch { }
            }
        }

        public static bool ClearClipboardIfStillHoldingSecret()
        {
            if (_activeCopiedSecretSha256 == null) return false;
            try
            {
                if (Clipboard.ContainsText())
                {
                    string current = Clipboard.GetText() ?? "";
                    byte[] utf8 = Encoding.UTF8.GetBytes(current);
                    byte[] currentHash = SHA256.HashData(utf8);
                    CryptographicOperations.ZeroMemory(utf8);

                    if (CryptographicOperations.FixedTimeEquals(currentHash, _activeCopiedSecretSha256))
                    {
                        Clipboard.Clear();
                        _activeCopiedSecretSha256 = null;
                        return true;
                    }
                }
            }
            catch { }

            _activeCopiedSecretSha256 = null;
            return false;
        }

        public static bool HasActiveClipboardSecret => _activeCopiedSecretSha256 != null;

        public static async Task<bool> PerformAutoTypeToPreviousWindowAsync(Window tresorWindow, string userName, string password)
        {
            try
            {
                tresorWindow.WindowState = WindowState.Minimized;
                await Task.Delay(320);

                if (!string.IsNullOrEmpty(userName))
                {
                    CopySecretToClipboardProtected(userName);
                    SendCtrlV();
                    await Task.Delay(95);
                    SendSingleVk(VK_TAB);
                    await Task.Delay(95);
                }

                if (!string.IsNullOrEmpty(password))
                {
                    CopySecretToClipboardProtected(password);
                    SendCtrlV();
                    await Task.Delay(95);
                    SendSingleVk(VK_RETURN);
                }

                await Task.Delay(250);
                ClearClipboardIfStillHoldingSecret();
                return true;
            }
            catch
            {
                return false;
            }
        }
    }
}
