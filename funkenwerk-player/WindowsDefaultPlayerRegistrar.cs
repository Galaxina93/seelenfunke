using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Runtime.InteropServices;
using Microsoft.Win32;

namespace FunkenwerkPlayer
{
    public static class WindowsDefaultPlayerRegistrar
    {
        public const string ProgId = "FunkenwerkPlayer.Media";
        public const string AppRegisteredName = "Funkenwerk Player";

        public static readonly string[] SupportedVideoExtensions =
        {
            ".mp4", ".mov", ".mkv", ".webm", ".avi", ".wmv", ".m4v", ".mpg", ".mpeg", ".flv", ".ts", ".3gp"
        };

        public static readonly string[] SupportedAudioExtensions =
        {
            ".mp3", ".wav", ".flac", ".m4a", ".ogg", ".aac", ".wma"
        };

        public static readonly HashSet<string> AllSupportedExtensions = new(StringComparer.OrdinalIgnoreCase);

        static WindowsDefaultPlayerRegistrar()
        {
            foreach (var ext in SupportedVideoExtensions) AllSupportedExtensions.Add(ext);
            foreach (var ext in SupportedAudioExtensions) AllSupportedExtensions.Add(ext);
        }

        public static bool IsAudioExtension(string? ext)
        {
            if (string.IsNullOrWhiteSpace(ext)) return false;
            foreach (var a in SupportedAudioExtensions)
            {
                if (string.Equals(a, ext, StringComparison.OrdinalIgnoreCase))
                    return true;
            }
            return false;
        }

        [DllImport("shell32.dll", CharSet = CharSet.Auto, SetLastError = true)]
        private static extern void SHChangeNotify(uint wEventId, uint uFlags, IntPtr dwItem1, IntPtr dwItem2);

        private const uint SHCNE_ASSOCCHANGED = 0x08000000;
        private const uint SHCNF_IDLIST = 0x0000;

        public static string GetExecutablePath()
        {
            string desktopExe = @"C:\Users\konta\Desktop\Funkenwerk Player\FunkenwerkPlayer.exe";
            if (File.Exists(desktopExe))
                return desktopExe;

            string? currentExe = Process.GetCurrentProcess().MainModule?.FileName;
            if (!string.IsNullOrWhiteSpace(currentExe) && File.Exists(currentExe))
                return currentExe;

            return Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "FunkenwerkPlayer.exe");
        }

        /// <summary>
        /// Registers Funkenwerk Player in HKCU\Software\Classes, HKCU\Software\RegisteredApplications,
        /// and OpenWithProgids for all supported video and audio extensions.
        /// </summary>
        public static bool RegisterAsWindowsMediaPlayer(out string message)
        {
            try
            {
                string exePath = GetExecutablePath();
                string openCommand = $"\"{exePath}\" \"%1\"";

                // 1. Register ProgID: HKCU\Software\Classes\FunkenwerkPlayer.Media
                using (var progKey = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{ProgId}"))
                {
                    progKey.SetValue("", "Funkenwerk Media File");
                    progKey.SetValue("FriendlyTypeName", "Funkenwerk Media File");
                    progKey.SetValue("FriendlyAppName", AppRegisteredName);

                    using (var iconKey = progKey.CreateSubKey("DefaultIcon"))
                    {
                        iconKey.SetValue("", $"\"{exePath}\",0");
                    }

                    using (var cmdKey = progKey.CreateSubKey(@"shell\open\command"))
                    {
                        cmdKey.SetValue("", openCommand);
                    }
                }

                // 2. Register Application Entry in HKCU\Software\Classes\Applications\FunkenwerkPlayer.exe
                using (var appKey = Registry.CurrentUser.CreateSubKey(@"Software\Classes\Applications\FunkenwerkPlayer.exe"))
                {
                    appKey.SetValue("FriendlyAppName", AppRegisteredName);
                    using (var cmdKey = appKey.CreateSubKey(@"shell\open\command"))
                    {
                        cmdKey.SetValue("", openCommand);
                    }
                    using (var typesKey = appKey.CreateSubKey("SupportedTypes"))
                    {
                        foreach (var ext in AllSupportedExtensions)
                        {
                            typesKey.SetValue(ext, "");
                        }
                    }
                }

                // 3. Register Capabilities for Windows 10/11 Default Apps UI
                const string capPath = @"Software\FunkenwerkPlayer\Capabilities";
                using (var capKey = Registry.CurrentUser.CreateSubKey(capPath))
                {
                    capKey.SetValue("ApplicationName", AppRegisteredName);
                    capKey.SetValue("ApplicationDescription", "Funkenwerk Player - High-Performance Video & Audio Mediaplayer");
                    capKey.SetValue("ApplicationIcon", $"\"{exePath}\",0");

                    using (var assocKey = capKey.CreateSubKey("FileAssociations"))
                    {
                        foreach (var ext in AllSupportedExtensions)
                        {
                            assocKey.SetValue(ext, ProgId);
                        }
                    }
                }

                // 4. Register in HKCU\Software\RegisteredApplications
                using (var regAppsKey = Registry.CurrentUser.CreateSubKey(@"Software\RegisteredApplications"))
                {
                    regAppsKey.SetValue(AppRegisteredName, capPath);
                }

                // 5. Register OpenWithProgids and default class mapping for each extension
                foreach (var ext in AllSupportedExtensions)
                {
                    using (var extKey = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{ext}"))
                    {
                        extKey.SetValue("", ProgId);
                        using (var openWithKey = extKey.CreateSubKey("OpenWithProgids"))
                        {
                            openWithKey.SetValue(ProgId, "");
                        }
                    }
                }

                // 6. Notify Windows Shell immediately so Explorer refreshes associations
                SHChangeNotify(SHCNE_ASSOCCHANGED, SHCNF_IDLIST, IntPtr.Zero, IntPtr.Zero);

                message = "Funkenwerk Player wurde Windows-weit für alle Video- & Audioformate registriert.";
                return true;
            }
            catch (Exception ex)
            {
                message = $"Registrierung fehlgeschlagen: {ex.Message}";
                return false;
            }
        }

        public static bool IsRegisteredInWindows()
        {
            try
            {
                using var key = Registry.CurrentUser.OpenSubKey(@"Software\RegisteredApplications");
                return key?.GetValue(AppRegisteredName) != null;
            }
            catch
            {
                return false;
            }
        }

        public static void OpenWindowsDefaultAppsSettings()
        {
            try
            {
                Process.Start(new ProcessStartInfo
                {
                    FileName = "ms-settings:defaultapps",
                    UseShellExecute = true
                });
            }
            catch { }
        }

        public static void OpenWindowsOpenWithDialog(string filePath)
        {
            if (string.IsNullOrWhiteSpace(filePath) || !File.Exists(filePath))
                return;

            try
            {
                Process.Start(new ProcessStartInfo
                {
                    FileName = "rundll32.exe",
                    Arguments = $"shell32.dll,OpenAs_RunDLL \"{filePath}\"",
                    UseShellExecute = true
                });
            }
            catch { }
        }
    }
}
