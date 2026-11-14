using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Security;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;
using Microsoft.Win32;

namespace FunkenwerkOS
{
    public static class FortressProvisioningEngine
    {
        private static readonly string[] ConsumerBloatwareTokens =
        {
            "Microsoft.BingNews",
            "Microsoft.BingWeather",
            "Microsoft.GamingApp",
            "Microsoft.XboxGamingOverlay",
            "Microsoft.MicrosoftSolitaireCollection",
            "Clipchamp.Clipchamp",
            "MicrosoftTeams",
            "Microsoft.Copilot",
            "Microsoft.Todos",
            "Microsoft.WindowsFeedbackHub",
            "Microsoft.Getstarted"
        };

        // OS-SEC-02: Strict USB runtime artifact allowlist (zero user JSON settings, cookies, SQLite DBs, CSVs, notes, or vaults!)
        private static readonly HashSet<string> AllowedUsbRuntimeExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".exe", ".dll", ".pdb", ".ico", ".png", ".jpg", ".jpeg", ".webp", ".mp4", ".wav", ".mp3", ".ttf", ".otf"
        };

        private static readonly HashSet<string> SkippedUsbDirectoryNames = new(StringComparer.OrdinalIgnoreCase)
        {
            "WebViewProfile",
            "LiveEdit",
            "Videos",
            "Projects",
            "bin",
            "obj",
            "storage",
            "Backup",
            ".git",
            ".vs",
            "MiniPC-USB-Setup",
            "build_mkt",
            "build_phase3",
            "__pycache__",
            "KameraMedien",
            "files"
        };

        public static string BackupDirectory
        {
            get
            {
                string dir = Path.Combine(OsVaultAndStorage.DataDir, "Backup");
                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                    OsVaultAndStorage.LockDownDirectoryAcl(dir);
                }
                return dir;
            }
        }

        public static string WindowsKeyBackupFilePath => Path.Combine(BackupDirectory, "Windows11Pro_Key_Backup.vault");
        public static string InitialProvisioningPasswordVaultPath => Path.Combine(BackupDirectory, "InitialProvisioningPassword.vault");

        public static string DefaultSetupScriptsDirectory
        {
            get
            {
                if (OsVaultAndStorage.IsSelfTestSandboxActive)
                    return Path.Combine(OsVaultAndStorage.OsDataDir, "MiniPC-USB-Setup");
                string target = Path.Combine(AppContext.BaseDirectory, "MiniPC-USB-Setup");
                return target;
            }
        }

        public static bool IsSkippedUsbDirectoryName(string? dirName)
        {
            if (string.IsNullOrWhiteSpace(dirName)) return false;
            return SkippedUsbDirectoryNames.Contains(dirName.Trim());
        }

        public static bool IsAllowedUsbRuntimeArtifact(string? filePath)
        {
            if (string.IsNullOrWhiteSpace(filePath))
                return false;

            string normalized = filePath.Replace('/', '\\');
            string[] parts = normalized.Split('\\', StringSplitOptions.RemoveEmptyEntries);
            if (parts.Length == 0)
                return false;

            // Reject if any parent directory segment is in the strict skip list (e.g. WebViewProfile, Backup, storage, .git)
            for (int i = 0; i < parts.Length - 1; i++)
            {
                if (SkippedUsbDirectoryNames.Contains(parts[i]))
                    return false;
            }

            string fileName = parts[^1];
            if (string.IsNullOrWhiteSpace(fileName))
                return false;

            // Allow ONLY .deps.json and .runtimeconfig.json among JSON files; block all other .json files!
            if (fileName.EndsWith(".deps.json", StringComparison.OrdinalIgnoreCase) ||
                fileName.EndsWith(".runtimeconfig.json", StringComparison.OrdinalIgnoreCase))
            {
                return true;
            }

            string ext = Path.GetExtension(fileName);
            if (string.IsNullOrEmpty(ext))
                return false;

            return AllowedUsbRuntimeExtensions.Contains(ext);
        }

        public static bool IsSensitiveFileExcludedFromUsbExport(string filePath)
            => !IsAllowedUsbRuntimeArtifact(filePath);

        public static FortressAuditReport AuditSystemState()
        {
            var report = new FortressAuditReport();
            var (freeGb, totalGb, cpuPct) = OsHardwareAndAudioEngine.ReadSystemResources();
            report.FreeRamGb = freeGb;
            report.TotalRamGb = totalGb;
            report.CpuLoadPercent = cpuPct;

            // 1. Custom Shell Status (HKCU Winlogon Shell)
            try
            {
                using var key = Registry.CurrentUser.OpenSubKey(@"Software\Microsoft\Windows NT\CurrentVersion\Winlogon", writable: false);
                string? shellVal = key?.GetValue("Shell") as string;
                report.IsCustomShellActive = !string.IsNullOrWhiteSpace(shellVal)
                    && shellVal.Contains("FunkenwerkOS", StringComparison.OrdinalIgnoreCase);
            }
            catch { }

            // 2. Fortress Firewall Marker (HKCU or Firewall Rule Registry)
            try
            {
                using var fwKey = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Services\SharedAccess\Parameters\FirewallPolicy\FirewallRules", writable: false);
                if (fwKey != null)
                {
                    report.IsFortressFirewallActive = fwKey.GetValueNames()
                        .Any(n => n.Contains("FunkenwerkFortress_AllowCore_OS", StringComparison.OrdinalIgnoreCase));
                }
            }
            catch { }

            // 3. Telemetry Locked
            try
            {
                bool polTelemetryZero = false;
                using (var polKey = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Policies\Microsoft\Windows\DataCollection", writable: false))
                {
                    if (polKey?.GetValue("AllowTelemetry") is int v && v == 0)
                        polTelemetryZero = true;
                }

                bool advOff = false;
                using (var advKey = Registry.CurrentUser.OpenSubKey(@"Software\Microsoft\Windows\CurrentVersion\AdvertisingInfo", writable: false))
                {
                    if (advKey?.GetValue("Enabled") is int av && av == 0)
                        advOff = true;
                }

                report.IsTelemetryLocked = polTelemetryZero || advOff;
            }
            catch { }

            // 4. Remaining Consumer Bloatware Packages (fast LocalAppData\Packages scan, zero PowerShell lag)
            try
            {
                string pkgDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Packages");
                if (Directory.Exists(pkgDir))
                {
                    string[] dirs = Directory.GetDirectories(pkgDir);
                    int found = 0;
                    foreach (string token in ConsumerBloatwareTokens)
                    {
                        if (dirs.Any(d => Path.GetFileName(d).StartsWith(token, StringComparison.OrdinalIgnoreCase)))
                            found++;
                    }
                    report.RemainingBloatwareCount = found;
                }
            }
            catch { }

            // 5. Wall Power Plan Marker
            try
            {
                using var fwOsKey = Registry.CurrentUser.OpenSubKey(@"Software\FunkenwerkOS", writable: false);
                if (fwOsKey?.GetValue("WallPowerConfigured") is int wp && wp == 1)
                    report.IsWallPowerPlanActive = true;
            }
            catch { }

            // 6. Windows 11 Pro License Backup (Encrypted via FWOSVAULT1)
            try
            {
                if (File.Exists(WindowsKeyBackupFilePath))
                {
                    string raw = File.ReadAllText(WindowsKeyBackupFilePath, Encoding.UTF8).Trim();
                    string content = raw.StartsWith("FWOSVAULT1:", StringComparison.Ordinal) || raw.StartsWith("FWVAULT2:", StringComparison.Ordinal)
                        ? OsVaultAndStorage.UnprotectVaultSecret(raw)
                        : raw;
                    report.HasWindowsKeyBackup = !string.IsNullOrWhiteSpace(content);
                    report.MaskedWindowsKey = ExtractMaskedKeyFromBackup(content);
                }
            }
            catch { }

            return report;
        }

        private static string ExtractMaskedKeyFromBackup(string text)
        {
            foreach (string rawLine in text.Split('\n'))
            {
                string line = rawLine.Trim();
                int idx = line.IndexOf(':');
                if (idx > 0 && line.Contains('-'))
                {
                    string val = line.Substring(idx + 1).Trim();
                    if (val.Length >= 25 && val.Count(c => c == '-') >= 4)
                    {
                        string lastBlock = val.Substring(val.Length - 5);
                        return $"XXXXX-XXXXX-XXXXX-XXXXX-{lastBlock}";
                    }
                }
            }
            return "Gesichert";
        }

        public static (bool Success, string MaskedKey, string FilePath) BackupWindowsProductKey(string? syntheticKeyForTest = null)
        {
            try
            {
                string regBackupKey = "";
                string productName = "Windows 11 Pro";

                if (!string.IsNullOrWhiteSpace(syntheticKeyForTest))
                {
                    regBackupKey = syntheticKeyForTest.Trim();
                }
                else
                {
                    using (var ntKey = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Windows NT\CurrentVersion", writable: false))
                    {
                        productName = (ntKey?.GetValue("ProductName") as string) ?? "Windows";
                    }

                    using (var sppKey = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Windows NT\CurrentVersion\SoftwareProtectionPlatform", writable: false))
                    {
                        regBackupKey = (sppKey?.GetValue("BackupProductKeyDefault") as string)?.Trim() ?? "";
                    }
                }

                string biosKey = string.IsNullOrWhiteSpace(syntheticKeyForTest) ? QueryBiosOemKeyFast() : "";
                string primaryKey = !string.IsNullOrWhiteSpace(biosKey) ? biosKey : regBackupKey;

                var sb = new StringBuilder();
                sb.AppendLine("==========================================================");
                sb.AppendLine(" FUNKENWERK OS · WINDOWS LIZENZ-BACKUP");
                sb.AppendLine("==========================================================");
                sb.AppendLine($"Zeitpunkt   : {DateTime.Now:dd.MM.yyyy HH:mm:ss}");
                sb.AppendLine($"Gerätename  : {Environment.MachineName}");
                sb.AppendLine($"Edition     : {productName}");
                if (!string.IsNullOrWhiteSpace(biosKey))
                    sb.AppendLine($"BIOS-OEM-Key: {biosKey}");
                if (!string.IsNullOrWhiteSpace(regBackupKey))
                    sb.AppendLine($"System-Key  : {regBackupKey}");
                if (string.IsNullOrWhiteSpace(primaryKey))
                    sb.AppendLine("Hinweis     : Digitale Hardware-Lizenz (UEFI-gebunden)");

                // OS-SEC-02: Encrypt Windows Product Key backup with FWOSVAULT1 (DPAPI + AES-256-GCM) and lock down file ACL!
                string encryptedPayload = OsVaultAndStorage.ProtectOsVaultSecret(sb.ToString());
                sb.Clear();

                string targetPath = WindowsKeyBackupFilePath;
                if (!File.Exists(targetPath))
                {
                    using var pre = new FileStream(targetPath, FileMode.CreateNew, FileAccess.Write, FileShare.None);
                    pre.Flush(flushToDisk: true);
                }
                OsVaultAndStorage.LockDownFileAcl(targetPath);
                File.WriteAllText(targetPath, encryptedPayload, new UTF8Encoding(false));
                OsVaultAndStorage.LockDownFileAcl(targetPath);

                string masked = !string.IsNullOrWhiteSpace(primaryKey) && primaryKey.Length >= 5
                    ? $"XXXXX-XXXXX-XXXXX-XXXXX-{primaryKey.Substring(primaryKey.Length - 5)}"
                    : "Digitale Lizenz gesichert";

                return (true, masked, targetPath);
            }
            catch (Exception ex)
            {
                return (false, ex.Message, "");
            }
        }

        private static string QueryBiosOemKeyFast()
        {
            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = "powershell.exe",
                    Arguments = "-NoProfile -NonInteractive -Command \"(Get-CimInstance -ClassName SoftwareLicensingService -ErrorAction SilentlyContinue).OA3xOriginalProductKey\"",
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    RedirectStandardOutput = true
                };
                using var proc = Process.Start(psi);
                if (proc != null)
                {
                    string output = proc.StandardOutput.ReadToEnd().Trim();
                    proc.WaitForExit(3500);
                    if (output.Length >= 25 && output.Contains('-'))
                        return output;
                }
            }
            catch { }
            return "";
        }

        public static bool SetCustomShellForCurrentUser(bool enableFunkenwerkShell)
        {
            if (OsVaultAndStorage.IsSelfTestSandboxActive)
            {
                var sandboxSettings = OsVaultAndStorage.LoadSettings();
                sandboxSettings.IntentionalCustomShellEnabled = enableFunkenwerkShell;
                OsVaultAndStorage.SaveSettings(sandboxSettings);
                return true;
            }

            try
            {
                using var key = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows NT\CurrentVersion\Winlogon", writable: true);
                if (key == null) return false;

                if (enableFunkenwerkShell)
                {
                    string exePath = Path.Combine(AppContext.BaseDirectory, "FunkenwerkOS.exe");
                    if (!File.Exists(exePath))
                    {
                        string fallback = Path.Combine(OsVaultAndStorage.CollectionRootDir, "Funkenwerk OS", "FunkenwerkOS.exe");
                        if (File.Exists(fallback)) exePath = fallback;
                    }
                    key.SetValue("Shell", $"\"{exePath}\" --shell", RegistryValueKind.String);
                }
                else
                {
                    key.DeleteValue("Shell", throwOnMissingValue: false);
                }

                var settings = OsVaultAndStorage.LoadSettings();
                settings.IntentionalCustomShellEnabled = enableFunkenwerkShell;
                OsVaultAndStorage.SaveSettings(settings);

                return true;
            }
            catch
            {
                return false;
            }
        }

        private static string GenerateStrongProvisioningPassword(int length = 24)
        {
            const string upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
            const string lower = "abcdefghijkmnopqrstuvwxyz";
            const string digits = "23456789";
            const string symbols = "!@#%^*+-_";
            string all = upper + lower + digits + symbols;

            char[] chars = new char[Math.Max(16, length)];
            chars[0] = upper[RandomNumberGenerator.GetInt32(upper.Length)];
            chars[1] = lower[RandomNumberGenerator.GetInt32(lower.Length)];
            chars[2] = digits[RandomNumberGenerator.GetInt32(digits.Length)];
            chars[3] = symbols[RandomNumberGenerator.GetInt32(symbols.Length)];
            for (int i = 4; i < chars.Length; i++)
            {
                chars[i] = all[RandomNumberGenerator.GetInt32(all.Length)];
            }

            // Fisher-Yates cryptographic shuffle
            for (int i = chars.Length - 1; i > 0; i--)
            {
                int j = RandomNumberGenerator.GetInt32(i + 1);
                (chars[i], chars[j]) = (chars[j], chars[i]);
            }

            return new string(chars);
        }

        /// <summary>
        /// OS-SEC-03: Generates a hardened Windows 11 Unattend XML with a cryptographically random initial local password
        /// (encoded as UTF-16LE Base64 Password, never blank!) and stores the owner recovery copy encrypted in FWOSVAULT1.
        /// </summary>
        public static (string Xml, string GeneratedPassword, string EncryptedBackupPath) BuildUnattendXml(string localUserName = "Alina")
        {
            string cleanUser = string.IsNullOrWhiteSpace(localUserName) ? "Alina" : localUserName.Trim();
            string safeUserXml = SecurityElement.Escape(cleanUser) ?? "Alina";
            string generatedPassword = GenerateStrongProvisioningPassword(24);

            // Windows SIM / Unattend specification: UTF-16LE bytes of (password + "Password") when PlainText=false
            byte[] utf16PwdBytes = Encoding.Unicode.GetBytes(generatedPassword + "Password");
            string encodedPwdBase64;
            try
            {
                encodedPwdBase64 = Convert.ToBase64String(utf16PwdBytes);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(utf16PwdBytes);
            }

            string vaultPayload = OsVaultAndStorage.ProtectOsVaultSecret(
                $"User={cleanUser}\nGeneratedUtc={DateTime.UtcNow:O}\nPassword={generatedPassword}");
            string vaultPath = InitialProvisioningPasswordVaultPath;
            if (!File.Exists(vaultPath))
            {
                using var pre = new FileStream(vaultPath, FileMode.CreateNew, FileAccess.Write, FileShare.None);
                pre.Flush(flushToDisk: true);
            }
            OsVaultAndStorage.LockDownFileAcl(vaultPath);
            File.WriteAllText(vaultPath, vaultPayload, new UTF8Encoding(false));
            OsVaultAndStorage.LockDownFileAcl(vaultPath);

            string xml = $@"<?xml version=""1.0"" encoding=""utf-8""?>
<unattend xmlns=""urn:schemas-microsoft-com:unattend"">
  <settings pass=""oobeSystem"">
    <component name=""Microsoft-Windows-International-Core"" processorArchitecture=""amd64"" publicKeyToken=""31bf3856ad364e35"" language=""neutral"" versionScope=""nonSxS"">
      <InputLocale>0407:00000407</InputLocale>
      <SystemLocale>de-DE</SystemLocale>
      <UILanguage>de-DE</UILanguage>
      <UserLocale>de-DE</UserLocale>
    </component>
    <component name=""Microsoft-Windows-Shell-Setup"" processorArchitecture=""amd64"" publicKeyToken=""31bf3856ad364e35"" language=""neutral"" versionScope=""nonSxS"">
      <OOBE>
        <HideEULAPage>true</HideEULAPage>
        <HideOnlineAccountScreens>true</HideOnlineAccountScreens>
        <HideWirelessSetupInOOBE>false</HideWirelessSetupInOOBE>
        <ProtectYourPC>3</ProtectYourPC>
      </OOBE>
      <UserAccounts>
        <LocalAccounts>
          <LocalAccount wcm:action=""add"" xmlns:wcm=""http://schemas.microsoft.com/WMIConfig/2002/State"">
            <Password>
              <Value>{SecurityElement.Escape(encodedPwdBase64)}</Value>
              <PlainText>false</PlainText>
            </Password>
            <DisplayName>{safeUserXml}</DisplayName>
            <Group>Administrators</Group>
            <Name>{safeUserXml}</Name>
          </LocalAccount>
        </LocalAccounts>
      </UserAccounts>
      <TimeZone>W. Europe Standard Time</TimeZone>
    </component>
  </settings>
</unattend>";

            return (xml, generatedPassword, vaultPath);
        }

        public static string BuildExtraAppFirewallToggleScript(string appKey, bool enable)
        {
            string normalizedKey = (appKey ?? "").Trim().ToLowerInvariant();
            string safeRuleId;
            string displayLabel;
            string candidateDiscoveryPs;

            switch (normalizedKey)
            {
                case "kdrive":
                    safeRuleId = "kdrive";
                    displayLabel = "Infomaniak kDrive";
                    candidateDiscoveryPs = @"
$candidates = @(
    (Join-Path $env:LOCALAPPDATA 'Programs\kDrive\kDrive.exe'),
    (Join-Path $env:LOCALAPPDATA 'Programs\kDrive\kDrive_client.exe'),
    'C:\Program Files\kDrive\kDrive.exe',
    'C:\Program Files\kDrive\kDrive_client.exe',
    'C:\Program Files (x86)\kDrive\kDrive.exe'
)";
                    break;
                case "discord":
                    safeRuleId = "discord";
                    displayLabel = "Discord";
                    candidateDiscoveryPs = @"
$candidates = @()
$discRoot = Join-Path $env:LOCALAPPDATA 'Discord'
if (Test-Path $discRoot) {
    $candidates += Get-ChildItem -Path $discRoot -Filter 'Discord.exe' -Recurse -ErrorAction SilentlyContinue | Select-Object -ExpandProperty FullName
}";
                    break;
                case "lol":
                    safeRuleId = "lol";
                    displayLabel = "Riot / League of Legends";
                    candidateDiscoveryPs = @"
$candidates = @(
    'C:\Riot Games\Riot Client\RiotClientServices.exe',
    'C:\Riot Games\League of Legends\LeagueClient.exe',
    'C:\Riot Games\League of Legends\LeagueClientUx.exe',
    'C:\Riot Games\League of Legends\Game\League of Legends.exe'
)";
                    break;
                case "dev":
                    safeRuleId = "dev";
                    displayLabel = "Entwickler-Werkzeuge (dotnet/git/node/code)";
                    candidateDiscoveryPs = @"
$candidates = @(
    'C:\Program Files\dotnet\dotnet.exe',
    'C:\Program Files\Git\cmd\git.exe',
    'C:\Program Files\Git\mingw64\bin\git.exe',
    'C:\Program Files\Git\mingw64\bin\git-remote-https.exe',
    'C:\Program Files\nodejs\node.exe',
    (Join-Path $env:LOCALAPPDATA 'Programs\Microsoft VS Code\Code.exe')
)";
                    break;
                default:
                    throw new ArgumentOutOfRangeException(nameof(appKey), "Unbekannter Firewall-Freigabeschluessel.");
            }

            var sb = new StringBuilder();
            sb.AppendLine("$ErrorActionPreference = 'SilentlyContinue'");
            sb.AppendLine($"Get-NetFirewallRule -Name 'FunkenwerkFortress_Extra_{safeRuleId}_*' -ErrorAction SilentlyContinue | Remove-NetFirewallRule");

            if (enable)
            {
                sb.AppendLine(candidateDiscoveryPs.Trim());
                sb.AppendLine("$idx = 0");
                sb.AppendLine("foreach ($exe in ($candidates | Select-Object -Unique)) {");
                sb.AppendLine("    if (-not [string]::IsNullOrWhiteSpace($exe) -and (Test-Path $exe)) {");
                sb.AppendLine("        $idx++");
                if (safeRuleId == "kdrive")
                {
                    sb.AppendLine($"        New-NetFirewallRule -Name \"FunkenwerkFortress_Extra_{safeRuleId}_$idx\" -DisplayName \"Funkenwerk OS Extra: {displayLabel} ($idx)\" -Direction Outbound -Action Allow -Program $exe -Protocol TCP -RemotePort 80,443 | Out-Null");
                }
                else
                {
                    sb.AppendLine($"        New-NetFirewallRule -Name \"FunkenwerkFortress_Extra_{safeRuleId}_$idx\" -DisplayName \"Funkenwerk OS Extra: {displayLabel} ($idx)\" -Direction Outbound -Action Allow -Program $exe | Out-Null");
                }
                sb.AppendLine("    }");
                sb.AppendLine("}");
            }

            return sb.ToString();
        }

        public static bool SyncExtraAppFirewallRuleIfActive(string appKey, bool enable)
        {
            if (OsVaultAndStorage.IsSelfTestSandboxActive)
                return false;

            try
            {
                var audit = AuditSystemState();
                if (!audit.IsFortressFirewallActive)
                    return false;

                string psContent = BuildExtraAppFirewallToggleScript(appKey, enable);
                string dataDir = OsVaultAndStorage.OsDataDir;
                Directory.CreateDirectory(dataDir);
                OsVaultAndStorage.LockDownDirectoryAcl(dataDir);

                string tempPs1 = Path.Combine(dataDir, $"fw_extra_fw_{Guid.NewGuid():N}.ps1");
                try
                {
                    using (var pre = new FileStream(tempPs1, FileMode.CreateNew, FileAccess.Write, FileShare.None))
                    {
                        pre.Flush(flushToDisk: true);
                    }
                    OsVaultAndStorage.LockDownFileAcl(tempPs1);
                    File.WriteAllText(tempPs1, psContent, new UTF8Encoding(true));
                    OsVaultAndStorage.LockDownFileAcl(tempPs1);

                    var psi = new ProcessStartInfo
                    {
                        FileName = "powershell.exe",
                        Arguments = $"-NoProfile -ExecutionPolicy RemoteSigned -File \"{tempPs1}\"",
                        Verb = "runas",
                        UseShellExecute = true
                    };
                    using var p = Process.Start(psi);
                    p?.WaitForExit(8000);
                    return true;
                }
                finally
                {
                    OsVaultAndStorage.SecureZeroWipeAndDeleteFile(tempPs1);
                }
            }
            catch
            {
                return false;
            }
        }

        public static string GenerateFortressPowerShellScriptForAudit()
            => BuildFortressPowerShellScript();

        public static void StartMaintenanceExplorer()
        {
            try
            {
                Process.Start(new ProcessStartInfo("explorer.exe") { UseShellExecute = true });
            }
            catch { }
        }

        public static bool RunElevatedFortressScript(string mode)
        {
            try
            {
                string scriptsDir = DefaultSetupScriptsDirectory;
                WriteMiniPcDeploymentScripts(scriptsDir);
                string ps1 = Path.Combine(scriptsDir, "Funkenwerk_Fortress_Installer.ps1");
                if (!File.Exists(ps1)) return false;

                var psi = new ProcessStartInfo
                {
                    FileName = "powershell.exe",
                    Arguments = $"-NoProfile -ExecutionPolicy Bypass -File \"{ps1}\" -Mode {mode}",
                    Verb = "runas",
                    UseShellExecute = true
                };
                Process.Start(psi);
                return true;
            }
            catch
            {
                return false;
            }
        }

        // =====================================================================
        // MINI-PC USB INSTALLER & FORTRESS SCRIPT GENERATOR
        // =====================================================================
        public static void WriteMiniPcDeploymentScripts(string targetDir)
        {
            Directory.CreateDirectory(targetDir);

            string ps1Content = BuildFortressPowerShellScript();
            File.WriteAllText(Path.Combine(targetDir, "Funkenwerk_Fortress_Installer.ps1"), ps1Content, new UTF8Encoding(true));

            string setupCmd =
@"@echo off
chcp 65001 >nul
title FUNKENWERK OS - MINI-PC FESTUNGS-INSTALLATION
echo ================================================================
echo   FUNKENWERK OS - DIGITALE FESTUNG SETUP (MINI-PC)
echo ================================================================
echo.
net session >nul 2>&1
if %errorLevel% == 0 (
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File ""%~dp0Funkenwerk_Fortress_Installer.ps1"" -Mode FullFortress
) else (
    echo Startet die automatische Einrichtung mit Administratorrechten...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ""Start-Process powershell.exe -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \""%~dp0Funkenwerk_Fortress_Installer.ps1\"" -Mode FullFortress'""
)
";
            File.WriteAllText(Path.Combine(targetDir, "2_START_FUNKENWERK_OS_AUF_MINIPC.cmd"), setupCmd, new UTF8Encoding(false));

            string restoreCmd =
@"@echo off
chcp 65001 >nul
title FUNKENWERK OS - NOTFALL WINDOWS WIEDERHERSTELLUNG
echo ================================================================
echo   NOTFALL-WIEDERHERSTELLUNG: STANDARD WINDOWS-OBERFLAECHE
echo ================================================================
reg delete ""HKCU\Software\Microsoft\Windows NT\CurrentVersion\Winlogon"" /v Shell /f >nul 2>&1
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ""Start-Process powershell.exe -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \""%~dp0Funkenwerk_Fortress_Installer.ps1\"" -Mode RestoreWindows'""
start explorer.exe
echo.
echo Standard Windows-Oberflaeche und Firewall wurden wiederhergestellt.
pause
";
            File.WriteAllText(Path.Combine(targetDir, "3_NOTFALL_WINDOWS_ZURUECKSETZEN.cmd"), restoreCmd, new UTF8Encoding(false));

            string keyBackupCmd =
@"@echo off
chcp 65001 >nul
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ""%~dp0Funkenwerk_Fortress_Installer.ps1"" -Mode BackupKeyOnly
pause
";
            File.WriteAllText(Path.Combine(targetDir, "1_NUR_WINDOWS_KEY_SICHERN.cmd"), keyBackupCmd, new UTF8Encoding(false));

            string readme =
@"====================================================================
  FUNKENWERK OS · DIGITALE FESTUNG FÜR DEN MINI-PC (NiPoGi GK3 Plus)
====================================================================

ANLEITUNG SOBALD DER MINI-PC ANKOMMT (DAUER: CA. 3 MINUTEN):

1. Mini-PC anschließen, einschalten und einmalig durch die erste
   Windows-Grundeinrichtung klicken (WLAN verbinden + lokales Konto).
2. Diesen USB-Stick in den Mini-PC stecken.
3. Im Ordner 'Funkenwerk-MiniPC-Festung' doppelklicken auf:
   -> 2_START_FUNKENWERK_OS_AUF_MINIPC.cmd
4. Das Skript erledigt vollautomatisch:
   - Sichert den vorinstallierten Windows-11-Pro-Key verschlüsselt lokal
   - Kopiert die komplette Funkenwerk Collection auf den Mini-PC
   - Entfernt alle Windows-Werbe-Apps, Store-Müll, Copilot & OneDrive
   - Schaltet Telemetrie und ungefragte Update-Neustarts ab
   - Aktiviert den 24/7-Wandmonitor-Energieplan (kein Ruhezustand)
   - Richtet die Festungs-Firewall ein (nur Funkenwerk + lokales Netz)
   - Setzt Funkenwerk OS als direkte Start-Oberfläche statt Windows
5. Nach dem Neustart startet der Mini-PC direkt in FUNKENWERK OS.
   Oben rechts einmal auf 'Verbinden' klicken und mit deinem
   Mein-Seelenfunke Admin-Konto anmelden -> Fertig!

SICHERHEITS-FALLSCHIRM:
Falls du irgendwann zum normalen Windows-Desktop zurück möchtest:
Einfach in Funkenwerk OS unter 'FESTUNG' umschalten oder auf dem
USB-Stick '3_NOTFALL_WINDOWS_ZURUECKSETZEN.cmd' starten.
====================================================================
";
            File.WriteAllText(Path.Combine(targetDir, "LIESMICH_MINIPC.txt"), readme, new UTF8Encoding(false));

            string btFixCmd =
@"@echo off
chcp 65001 >nul
title FUNKENWERK OS - BLUETOOTH & AUDIO STABILITAETS-UPDATE
echo ================================================================
echo   FUNKENWERK OS - BLUETOOTH SBC + 5 GHz AUDIO-OPTIMIERUNG
echo ================================================================
echo.
net session >nul 2>&1
if %errorLevel% == 0 (
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File ""%~dp0Funkenwerk_Fortress_Installer.ps1"" -Mode BluetoothFix
) else (
    echo Startet die Bluetooth-Optimierung mit Administratorrechten...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ""Start-Process powershell.exe -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \""%~dp0Funkenwerk_Fortress_Installer.ps1\"" -Mode BluetoothFix'""
)
";
            File.WriteAllText(Path.Combine(targetDir, "4_BLUETOOTH_AUDIO_FIX_MINIPC.cmd"), btFixCmd, new UTF8Encoding(false));
        }

        private static string BuildFortressPowerShellScript()
        {
            return @"param(
    [ValidateSet('FullFortress', 'DebloatOnly', 'FirewallOn', 'FirewallOff', 'RestoreWindows', 'BackupKeyOnly', 'BluetoothFix')]
    [string]$Mode = 'FullFortress'
)

$ErrorActionPreference = 'SilentlyContinue'
$ScriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$ParentBundleRoot = Split-Path -Parent $ScriptRoot

Write-Host '================================================================' -ForegroundColor Yellow
Write-Host ""  FUNKENWERK OS FORTRESS ENGINE ($Mode)"" -ForegroundColor Yellow
Write-Host '================================================================' -ForegroundColor Yellow

function Backup-OemKey {
    # OS-SEC-02: Store license backup ONLY locally encrypted via DPAPI inside ACL-locked Backup directory; NEVER dump plaintext keys to USB!
    $localBackupDir = Join-Path $env:LOCALAPPDATA 'FunkenwerkOS\Backup'
    New-Item -ItemType Directory -Path $localBackupDir -Force | Out-Null
    & icacls.exe $localBackupDir /inheritance:r /grant '*S-1-5-18:(OI)(CI)(F)' /grant '*S-1-5-32-544:(OI)(CI)(F)' /grant ""$($env:USERNAME):(OI)(CI)(F)"" 2>$null | Out-Null

    $oemKey = (Get-CimInstance -ClassName SoftwareLicensingService).OA3xOriginalProductKey
    $regKey = (Get-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\SoftwareProtectionPlatform').BackupProductKeyDefault
    $edition = (Get-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion').ProductName

    $content = @(
        '==========================================================',
        ' FUNKENWERK OS · WINDOWS LIZENZ-BACKUP',
        '==========================================================',
        ""Zeitpunkt   : $(Get-Date -Format 'dd.MM.yyyy HH:mm:ss')"",
        ""Geraetename : $env:COMPUTERNAME"",
        ""Edition     : $edition"",
        ""BIOS-OEM-Key: $oemKey"",
        ""System-Key  : $regKey""
    ) -join ""`r`n""

    $targetVaultFile = Join-Path $localBackupDir 'Windows11Pro_Key_Backup.dpapi'
    $secStr = ConvertTo-SecureString -String $content -AsPlainText -Force
    $dpapiBlob = ConvertFrom-SecureString -SecureString $secStr
    Set-Content -Path $targetVaultFile -Value $dpapiBlob -Encoding ASCII
    & icacls.exe $targetVaultFile /inheritance:r /grant '*S-1-5-18:(F)' /grant '*S-1-5-32-544:(F)' /grant ""$($env:USERNAME):(F)"" 2>$null | Out-Null
    Write-Host '[OK] Windows-11-Pro Lizenzschluessel lokal verschluesselt gesichert.' -ForegroundColor Green
}

function Invoke-VerifiedInstaller {
    param(
        [string]$ExePath,
        [string]$InstallArgs = '/install /quiet /norestart'
    )
    if (-not (Test-Path $ExePath)) { return $false }
    # OS-SEC-03: Verify Microsoft Authenticode signature before executing any runtime installer!
    $sig = Get-AuthenticodeSignature -FilePath $ExePath -ErrorAction SilentlyContinue
    if (($null -eq $sig) -or ($sig.Status -ne 'Valid')) {
        Write-Host ""[BLOCK] Ungueltige Authenticode-Signatur fuer Installer: $ExePath"" -ForegroundColor Red
        return $false
    }
    Start-Process -FilePath $ExePath -ArgumentList $InstallArgs -Wait
    return $true
}

function Restore-FortressFirewallState {
    Get-NetFirewallRule -Name 'FunkenwerkFortress_*' -ErrorAction SilentlyContinue | Remove-NetFirewallRule

    $localBackupDir = Join-Path $env:LOCALAPPDATA 'FunkenwerkOS\Backup'
    $rulesSnapPath = Join-Path $localBackupDir 'firewall_inbound_rules_snapshot.json'
    if (Test-Path $rulesSnapPath) {
        try {
            $disabledRuleNames = Get-Content -Path $rulesSnapPath -Raw -Encoding UTF8 | ConvertFrom-Json
            foreach ($rName in $disabledRuleNames) {
                if (-not [string]::IsNullOrWhiteSpace($rName)) {
                    Enable-NetFirewallRule -Name $rName -ErrorAction SilentlyContinue | Out-Null
                }
            }
            Remove-Item -Path $rulesSnapPath -Force -ErrorAction SilentlyContinue
        } catch { }
    }

    $profSnapPath = Join-Path $localBackupDir 'firewall_profiles_snapshot.json'
    if (Test-Path $profSnapPath) {
        try {
            $profiles = Get-Content -Path $profSnapPath -Raw -Encoding UTF8 | ConvertFrom-Json
            foreach ($p in $profiles) {
                Set-NetFirewallProfile -Name $p.Name -Enabled $p.Enabled -DefaultInboundAction $p.DefaultInboundAction -DefaultOutboundAction $p.DefaultOutboundAction -AllowInboundRules $p.AllowInboundRules -ErrorAction SilentlyContinue
            }
            Remove-Item -Path $profSnapPath -Force -ErrorAction SilentlyContinue
            return
        } catch { }
    }

    Set-NetFirewallProfile -Profile Domain,Private,Public -Enabled True -DefaultInboundAction Block -DefaultOutboundAction Allow -AllowInboundRules True -ErrorAction SilentlyContinue
}

if ($Mode -eq 'BackupKeyOnly') {
    Backup-OemKey
    exit 0
}

if ($Mode -eq 'RestoreWindows') {
    Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows NT\CurrentVersion\Winlogon' -Name 'Shell' -ErrorAction SilentlyContinue
    Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon' -Name 'Shell' -Value 'explorer.exe' -ErrorAction SilentlyContinue
    Restore-FortressFirewallState
    Stop-Process -Name 'explorer' -Force -ErrorAction SilentlyContinue
    Start-Sleep -Milliseconds 400
    Start-Process -FilePath 'explorer.exe'
    Write-Host '[OK] Standard Windows-Oberflaeche, Taskleiste & Standard-Firewall 100% wiederhergestellt.' -ForegroundColor Green
    exit 0
}

if ($Mode -eq 'FirewallOff') {
    Restore-FortressFirewallState
    Write-Host '[OK] Festungs-Firewall deaktiviert (Vorheriger Windows-Zustand 100% wiederhergestellt).' -ForegroundColor Green
    exit 0
}

if ($Mode -eq 'FullFortress') {
    # 1. Backup License Key first
    Backup-OemKey

    # 1b. Ensure .NET 8 & .NET 9 Windows Desktop Runtimes are present BEFORE activating strict Outbound Firewall
    $installedRuntimes = ''
    try { $installedRuntimes = (& dotnet --list-runtimes 2>$null) -join ""`n"" } catch { }

    if ($installedRuntimes -notmatch 'Microsoft\.WindowsDesktop\.App 8\.') {
        Write-Host '[..] Installiere .NET 8 Desktop Runtime fuer Funkenwerk Collection...' -ForegroundColor Cyan
        $localRt8 = Join-Path $ScriptRoot 'Runtimes\windowsdesktop-runtime-8-x64.exe'
        if (-not (Test-Path $localRt8)) { $localRt8 = Join-Path $ParentBundleRoot 'Runtimes\windowsdesktop-runtime-8-x64.exe' }
        if (Test-Path $localRt8) {
            Invoke-VerifiedInstaller -ExePath $localRt8 | Out-Null
        } else {
            $rt8Installer = Join-Path $env:TEMP ""windowsdesktop-runtime-8-x64_$([guid]::NewGuid().ToString('N')).exe""
            Invoke-WebRequest -Uri 'https://aka.ms/dotnet/8.0/windowsdesktop-runtime-win-x64.exe' -OutFile $rt8Installer -UseBasicParsing
            if (Test-Path $rt8Installer) {
                Invoke-VerifiedInstaller -ExePath $rt8Installer | Out-Null
                Remove-Item $rt8Installer -Force -ErrorAction SilentlyContinue
            }
        }
    }

    if ($installedRuntimes -notmatch 'Microsoft\.WindowsDesktop\.App 9\.') {
        Write-Host '[..] Installiere .NET 9 Desktop Runtime fuer Funkenwerk Collection...' -ForegroundColor Cyan
        $localRt9 = Join-Path $ScriptRoot 'Runtimes\windowsdesktop-runtime-9-x64.exe'
        if (-not (Test-Path $localRt9)) { $localRt9 = Join-Path $ParentBundleRoot 'Runtimes\windowsdesktop-runtime-9-x64.exe' }
        if (Test-Path $localRt9) {
            Invoke-VerifiedInstaller -ExePath $localRt9 | Out-Null
        } else {
            $rt9Installer = Join-Path $env:TEMP ""windowsdesktop-runtime-9-x64_$([guid]::NewGuid().ToString('N')).exe""
            Invoke-WebRequest -Uri 'https://aka.ms/dotnet/9.0/windowsdesktop-runtime-win-x64.exe' -OutFile $rt9Installer -UseBasicParsing
            if (Test-Path $rt9Installer) {
                Invoke-VerifiedInstaller -ExePath $rt9Installer | Out-Null
                Remove-Item $rt9Installer -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

# 2. Copy Funkenwerk Collection from USB bundle if running from USB stick
$usbCollectionSource = Join-Path $ParentBundleRoot 'Funkenwerk Collection'
if (-not (Test-Path $usbCollectionSource)) {
    $usbCollectionSource = Join-Path $ScriptRoot 'Funkenwerk Collection'
}

$systemCollectionTarget = 'C:\Funkenwerk Collection'
$isOobeOrSystem = ($env:USERNAME -ieq 'defaultuser0') -or ($env:USERNAME -ieq 'SYSTEM') -or ((Get-ItemProperty -Path 'HKLM:\SYSTEM\Setup' -ErrorAction SilentlyContinue).OOBEInProgress -eq 1)
$desktopPath = [Environment]::GetFolderPath('Desktop')
$desktopCollectionTarget = if ($isOobeOrSystem -or [string]::IsNullOrWhiteSpace($desktopPath)) { $systemCollectionTarget } else { Join-Path $desktopPath 'Funkenwerk Collection' }

if (Test-Path $usbCollectionSource) {
    Write-Host '[..] Aktualisiere Funkenwerk Collection auf C:\Funkenwerk Collection...' -ForegroundColor Cyan
    Stop-Process -Name 'FunkenwerkOS' -Force -ErrorAction SilentlyContinue
    Start-Sleep -Milliseconds 500
    New-Item -ItemType Directory -Path $systemCollectionTarget -Force | Out-Null
    Copy-Item -Path (Join-Path $usbCollectionSource '*') -Destination $systemCollectionTarget -Recurse -Force

    if ($desktopCollectionTarget -ne $systemCollectionTarget) {
        New-Item -ItemType Directory -Path $desktopCollectionTarget -Force | Out-Null
        Copy-Item -Path (Join-Path $usbCollectionSource '*') -Destination $desktopCollectionTarget -Recurse -Force
    }

    # Erzeuge saubere Schnellstarter-Verknuepfungen (.lnk) passend zum Mini-PC
    $wsh = New-Object -ComObject WScript.Shell
    $shortcutSpecs = @(
        @{ Name = 'Funkenwerk OS';      Sub = 'Funkenwerk OS';      Exe = 'FunkenwerkOS.exe';      Ico = 'funkenwerk_os.ico' },
        @{ Name = 'Funkenwerk';         Sub = 'Funkenwerk';         Exe = 'Funkenwerk.exe';        Ico = 'funkenwerk.ico' },
        @{ Name = 'Funkenwerk Browser'; Sub = 'Funkenwerk Browser'; Exe = 'FunkenwerkBrowser.exe'; Ico = 'funkenwerk_browser.ico' },
        @{ Name = 'Funkenwerk PDF';     Sub = 'Funkenwerk PDF';     Exe = 'FunkenwerkPdf.exe';     Ico = 'funkenwerk_pdf.ico' },
        @{ Name = 'Funkenwerk Drucker'; Sub = 'Funkenwerk Drucker'; Exe = 'FunkenwerkDrucker.exe'; Ico = 'funkenwerk_drucker.ico' },
        @{ Name = 'Funkenwerk FTP';     Sub = 'Funkenwerk FTP';     Exe = 'FunkenwerkFtp.exe';     Ico = 'funkenwerk_ftp.ico' },
        @{ Name = 'Funkenwerk SSH';     Sub = 'Funkenwerk SSH';     Exe = 'FunkenwerkSsh.exe';     Ico = 'funkenwerk_ssh.ico' },
        @{ Name = 'Funkenwerk Player';  Sub = 'Funkenwerk Player';  Exe = 'FunkenwerkPlayer.exe';  Ico = 'funkenwerk_player.ico' },
        @{ Name = 'Funkenwerk Video';   Sub = 'Funkenwerk Video';   Exe = 'FunkenwerkVideo.exe';   Ico = 'funkenwerk_video.ico' },
        @{ Name = 'Funkenwerk Craft';   Sub = 'Funkenwerk Craft';   Exe = 'FunkenwerkCraft.exe';   Ico = 'funkenwerk_craft.ico' },
        @{ Name = 'Funkenwerk Tresor';  Sub = 'Funkenwerk Tresor';  Exe = 'FunkenwerkTresor.exe';  Ico = 'funkenwerk_tresor.ico' }
    )
    foreach ($targetRoot in @($systemCollectionTarget, $desktopCollectionTarget) | Select-Object -Unique) {
        foreach ($sc in $shortcutSpecs) {
            $appDir = Join-Path $targetRoot $sc.Sub
            $exeFull = Join-Path $appDir $sc.Exe
            if (Test-Path $exeFull) {
                $lnk = $wsh.CreateShortcut((Join-Path $targetRoot ""$($sc.Name).lnk""))
                $lnk.TargetPath = $exeFull
                $lnk.WorkingDirectory = $appDir
                $icoFull = Join-Path $appDir $sc.Ico
                if (Test-Path $icoFull) { $lnk.IconLocation = $icoFull }
                $lnk.Save()
            }
        }
    }
    Write-Host '[OK] Funkenwerk Collection installiert.' -ForegroundColor Green
} elseif ($Mode -eq 'BluetoothFix') {
    Stop-Process -Name 'FunkenwerkOS' -Force -ErrorAction SilentlyContinue
    Start-Sleep -Milliseconds 500
    $osTargetDir = Join-Path $systemCollectionTarget 'Funkenwerk OS'
    foreach ($candDir in @($ScriptRoot, $ParentBundleRoot)) {
        $candDll = Join-Path $candDir 'FunkenwerkOS.dll'
        $candExe = Join-Path $candDir 'FunkenwerkOS.exe'
        if ((Test-Path $candDll) -and ($candDir.TrimEnd('\') -ine $osTargetDir.TrimEnd('\'))) {
            New-Item -ItemType Directory -Path $osTargetDir -Force | Out-Null
            Copy-Item -Path $candDll -Destination (Join-Path $osTargetDir 'FunkenwerkOS.dll') -Force
            if (Test-Path $candExe) {
                Copy-Item -Path $candExe -Destination (Join-Path $osTargetDir 'FunkenwerkOS.exe') -Force
            }
            Write-Host '[OK] Funkenwerk OS Programmdateien aktualisiert.' -ForegroundColor Green
            break
        }
    }
}

if (-not (Test-Path $desktopCollectionTarget) -and (Test-Path $systemCollectionTarget)) {
    $desktopCollectionTarget = $systemCollectionTarget
}

if ($Mode -in @('FullFortress', 'DebloatOnly')) {
    Write-Host '[..] Entferne Windows-Bloatware, Werbung & Consumer-Apps...' -ForegroundColor Cyan
    $bloatPatterns = @(
        '*BingNews*', '*BingWeather*', '*BingFinance*', '*BingSports*',
        '*Xbox*', '*GamingApp*', '*MicrosoftSolitaireCollection*',
        '*Clipchamp*', '*Copilot*', '*Teams*', '*Todos*',
        '*WindowsFeedbackHub*', '*Getstarted*', '*GetHelp*',
        '*ZuneVideo*', '*ZuneMusic*', '*People*', '*PowerAutomateDesktop*',
        '*MicrosoftOfficeHub*', '*OutlookForWindows*'
    )
    foreach ($pat in $bloatPatterns) {
        Get-AppxPackage -AllUsers -Name $pat | Remove-AppxPackage -AllUsers -ErrorAction SilentlyContinue
        Get-AppxPackage -Name $pat | Remove-AppxPackage -ErrorAction SilentlyContinue
        Get-AppxProvisionedPackage -Online | Where-Object { $_.DisplayName -like $pat } | Remove-AppxProvisionedPackage -Online -ErrorAction SilentlyContinue
    }

    # Disable OneDrive autostart
    Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Name 'OneDrive' -ErrorAction SilentlyContinue

    # Disable Advertising ID, Lockscreen Ads, Web Search & Forced Reboots (keep Windows Update & Winget unlocked)
    Remove-Item -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\DataCollection' -Recurse -Force -ErrorAction SilentlyContinue

    New-Item -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\AdvertisingInfo' -Force | Out-Null
    Set-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\AdvertisingInfo' -Name 'Enabled' -Type DWord -Value 0

    New-Item -Path 'HKCU:\Software\Policies\Microsoft\Windows\Explorer' -Force | Out-Null
    Set-ItemProperty -Path 'HKCU:\Software\Policies\Microsoft\Windows\Explorer' -Name 'DisableSearchBoxSuggestions' -Type DWord -Value 1

    New-Item -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsChat' -Force | Out-Null
    Set-ItemProperty -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsChat' -Name 'ChatIcon' -Type DWord -Value 3

    New-Item -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Dsh' -Force | Out-Null
    Set-ItemProperty -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Dsh' -Name 'AllowNewsAndInterests' -Type DWord -Value 0

    New-Item -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU' -Force | Out-Null
    Set-ItemProperty -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU' -Name 'NoAutoRebootWithLoggedOnUsers' -Type DWord -Value 1

    Stop-Service -Name 'DiagTrack' -Force -ErrorAction SilentlyContinue
    Set-Service -Name 'DiagTrack' -StartupType Disabled -ErrorAction SilentlyContinue

    # 24/7 Wall-Monitor Power Plan (No screen sleep, no standby)
    powercfg /change monitor-timeout-ac 0
    powercfg /change standby-timeout-ac 0
    powercfg /change hibernate-timeout-ac 0
    New-Item -Path 'HKCU:\Software\FunkenwerkOS' -Force | Out-Null
    Set-ItemProperty -Path 'HKCU:\Software\FunkenwerkOS' -Name 'WallPowerConfigured' -Type DWord -Value 1

    # Enable WLAN, Location Consent & Stereo Bluetooth Services (keep Hands-Free BTAGService DISABLED to prevent A2DP audio stuttering!)
    foreach ($svc in @('WlanSvc', 'lfsvc', 'bthserv', 'BthAvctpSvc')) {
        Set-Service -Name $svc -StartupType Automatic -ErrorAction SilentlyContinue
        Start-Service -Name $svc -ErrorAction SilentlyContinue
    }
    Stop-Service -Name 'BTAGService' -Force -ErrorAction SilentlyContinue
    Set-Service -Name 'BTAGService' -StartupType Disabled -ErrorAction SilentlyContinue
    foreach ($cap in @('location', 'wifiData', 'bluetooth', 'bluetoothSync')) {
        New-Item -Path ""HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\ConsentStore\$cap"" -Force | Out-Null
        Set-ItemProperty -Path ""HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\ConsentStore\$cap"" -Name 'Value' -Type String -Value 'Allow'
        New-Item -Path ""HKCU:\Software\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\ConsentStore\$cap"" -Force | Out-Null
        Set-ItemProperty -Path ""HKCU:\Software\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\ConsentStore\$cap"" -Name 'Value' -Type String -Value 'Allow'
    }
    New-Item -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\LocationAndSensors' -Force | Out-Null
    Set-ItemProperty -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\LocationAndSensors' -Name 'DisableLocation' -Type DWord -Value 0

    # Import pre-bundled WLAN profiles from USB stick if present and auto-connect
    $wlanProfDir = Join-Path $ScriptRoot 'WlanProfiles'
    if (Test-Path $wlanProfDir) {
        Get-ChildItem -Path $wlanProfDir -Filter '*.xml' -ErrorAction SilentlyContinue | ForEach-Object {
            & netsh.exe wlan add profile filename=""$($_.FullName)"" user=all 2>$null | Out-Null
        }
    }

    # Zero-Trust Hardening: Block Blank Passwords, Disable Remote Local Admin Token Filter, Disable WinRM & Remote Assistance
    Get-NetConnectionProfile -ErrorAction SilentlyContinue | Set-NetConnectionProfile -NetworkCategory Private -ErrorAction SilentlyContinue
    Set-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\Lsa' -Name 'LimitBlankPasswordUse' -Type DWord -Value 1
    Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System' -Name 'LocalAccountTokenFilterPolicy' -Type DWord -Value 0
    Stop-Service -Name 'WinRM' -Force -ErrorAction SilentlyContinue
    Set-Service -Name 'WinRM' -StartupType Disabled -ErrorAction SilentlyContinue
    Set-ItemProperty -Path 'HKLM:\System\CurrentControlSet\Control\Remote Assistance' -Name 'fAllowToGetHelp' -Type DWord -Value 0 -ErrorAction SilentlyContinue

    Write-Host '[OK] Entkernung, Zero-Trust-Haertung, WLAN/BT-Freigabe & 24/7-Energieplan aktiv.' -ForegroundColor Green
}

if ($Mode -in @('FullFortress', 'FirewallOn', 'BluetoothFix')) {
    Write-Host '[..] Aktiviere Festungs-Firewall (Zero-Inbound + Strikte Programm-Whitelist Outbound)...' -ForegroundColor Cyan

    # OS-SEC-03: Snapshot prior Windows Firewall profile state so RestoreWindows / FirewallOff restores 100% of prior state!
    $localBackupDir = Join-Path $env:LOCALAPPDATA 'FunkenwerkOS\Backup'
    New-Item -ItemType Directory -Path $localBackupDir -Force | Out-Null
    & icacls.exe $localBackupDir /inheritance:r /grant '*S-1-5-18:(OI)(CI)(F)' /grant '*S-1-5-32-544:(OI)(CI)(F)' /grant ""$($env:USERNAME):(OI)(CI)(F)"" 2>$null | Out-Null
    $profSnapPath = Join-Path $localBackupDir 'firewall_profiles_snapshot.json'
    if (-not (Test-Path $profSnapPath)) {
        try {
            $curProfiles = Get-NetFirewallProfile -Profile Domain,Private,Public | Select-Object Name,Enabled,DefaultInboundAction,DefaultOutboundAction,AllowInboundRules
            $curProfiles | ConvertTo-Json -Depth 4 | Set-Content -Path $profSnapPath -Encoding UTF8
        } catch { }
    }

    Get-NetFirewallRule -Name 'FunkenwerkFortress_*' -ErrorAction SilentlyContinue | Remove-NetFirewallRule

    # Core networking essentials (DNS, DHCP, NTP, Loopback Outbound ONLY - NO blanket svchost 80/443!)
    New-NetFirewallRule -Name 'FunkenwerkFortress_AllowCore_DNS_UDP' -DisplayName 'Funkenwerk OS: DNS (UDP)' -Direction Outbound -Action Allow -Protocol UDP -RemotePort 53 | Out-Null
    New-NetFirewallRule -Name 'FunkenwerkFortress_AllowCore_DNS_TCP' -DisplayName 'Funkenwerk OS: DNS (TCP)' -Direction Outbound -Action Allow -Protocol TCP -RemotePort 53 | Out-Null
    New-NetFirewallRule -Name 'FunkenwerkFortress_AllowCore_DHCP' -DisplayName 'Funkenwerk OS: DHCP' -Direction Outbound -Action Allow -Protocol UDP -LocalPort 68 -RemotePort 67 | Out-Null
    New-NetFirewallRule -Name 'FunkenwerkFortress_AllowCore_NTP' -DisplayName 'Funkenwerk OS: Zeit-Sync' -Direction Outbound -Action Allow -Protocol UDP -RemotePort 123 | Out-Null
    New-NetFirewallRule -Name 'FunkenwerkFortress_AllowCore_Loopback' -DisplayName 'Funkenwerk OS: Loopback' -Direction Outbound -Action Allow -RemoteAddress '127.0.0.1','::1' | Out-Null

    $fwExes = @(
        @{ Id = 'OS';      Rel = 'Funkenwerk OS\FunkenwerkOS.exe' },
        @{ Id = 'Suite';   Rel = 'Funkenwerk\Funkenwerk.exe' },
        @{ Id = 'Browser'; Rel = 'Funkenwerk Browser\FunkenwerkBrowser.exe' },
        @{ Id = 'PDF';     Rel = 'Funkenwerk PDF\FunkenwerkPdf.exe' },
        @{ Id = 'Drucker'; Rel = 'Funkenwerk Drucker\FunkenwerkDrucker.exe' },
        @{ Id = 'FTP';     Rel = 'Funkenwerk FTP\FunkenwerkFtp.exe' },
        @{ Id = 'SSH';     Rel = 'Funkenwerk SSH\FunkenwerkSsh.exe' },
        @{ Id = 'Player';  Rel = 'Funkenwerk Player\FunkenwerkPlayer.exe' },
        @{ Id = 'Video';   Rel = 'Funkenwerk Video\FunkenwerkVideo.exe' },
        @{ Id = 'Craft';   Rel = 'Funkenwerk Craft\FunkenwerkCraft.exe' },
        @{ Id = 'Tresor';  Rel = 'Funkenwerk Tresor\FunkenwerkTresor.exe' },
        @{ Id = 'MCP';     Rel = 'Funkenwerk MCP\FunkenwerkMcp.exe' }
    )

    $rootsToWhitelist = @($systemCollectionTarget, $desktopCollectionTarget) | Select-Object -Unique
    $ruleIdx = 0
    foreach ($rootDir in $rootsToWhitelist) {
        $ruleIdx++
        foreach ($entry in $fwExes) {
            $fullExe = Join-Path $rootDir $entry.Rel
            if (Test-Path $fullExe) {
                New-NetFirewallRule -Name ""FunkenwerkFortress_AllowCore_$($entry.Id)_$ruleIdx"" -DisplayName ""Funkenwerk OS: $($entry.Id)"" -Direction Outbound -Action Allow -Program $fullExe | Out-Null
            }
        }
    }

    # Whitelist Infomaniak kDrive Sync Client (HTTPS/HTTP ports 80,443)
    $kDriveCandidates = @(
        (Join-Path $env:LOCALAPPDATA 'Programs\kDrive\kDrive.exe'),
        (Join-Path $env:LOCALAPPDATA 'Programs\kDrive\kDrive_client.exe'),
        'C:\Program Files\kDrive\kDrive.exe',
        'C:\Program Files\kDrive\kDrive_client.exe',
        'C:\Program Files (x86)\kDrive\kDrive.exe'
    )
    foreach ($kd in $kDriveCandidates) {
        if (Test-Path $kd) {
            $kdLeaf = [System.IO.Path]::GetFileNameWithoutExtension($kd)
            New-NetFirewallRule -Name ""FunkenwerkFortress_AllowCloud_$kdLeaf"" -DisplayName ""Funkenwerk OS: kDrive ($kdLeaf)"" -Direction Outbound -Action Allow -Program $kd -Protocol TCP -RemotePort 80,443 | Out-Null
        }
    }

    # OS-SEC-03: Restrict Microsoft Edge WebView2 Runtime outbound strictly to TCP 80,443
    $wv2Roots = @('C:\Program Files (x86)\Microsoft\EdgeWebView\Application', 'C:\Program Files\Microsoft\EdgeWebView\Application')
    $wvIdx = 0
    foreach ($wvRoot in $wv2Roots) {
        if (Test-Path $wvRoot) {
            Get-ChildItem -Path $wvRoot -Filter 'msedgewebview2.exe' -Recurse -ErrorAction SilentlyContinue | ForEach-Object {
                $wvIdx++
                New-NetFirewallRule -Name ""FunkenwerkFortress_AllowWebView2_$wvIdx"" -DisplayName ""Funkenwerk OS: WebView2 Runtime ($wvIdx)"" -Direction Outbound -Action Allow -Program $_.FullName -Protocol TCP -RemotePort 80,443 | Out-Null
            }
        }
    }
    $defPlatform = 'C:\ProgramData\Microsoft\Windows Defender\Platform'
    if (Test-Path $defPlatform) {
        $mpIdx = 0
        Get-ChildItem -Path $defPlatform -Filter 'MsMpEng.exe' -Recurse -ErrorAction SilentlyContinue | ForEach-Object {
            $mpIdx++
            New-NetFirewallRule -Name ""FunkenwerkFortress_AllowDefender_$mpIdx"" -DisplayName ""Funkenwerk OS: Windows Defender ($mpIdx)"" -Direction Outbound -Action Allow -Program $_.FullName -Protocol TCP -RemotePort 80,443 | Out-Null
        }
    }

    # OS-SEC-03: Enforce inbound blocking cleanly at profile level (-DefaultInboundAction Block -AllowInboundRules False)
    # without destructively mutating built-in Windows inbound rules!
    Set-NetFirewallProfile -Profile Domain,Private,Public -Enabled True -DefaultInboundAction Block -AllowInboundRules False -DefaultOutboundAction Block

    # Optional: If Ed25519 Key-Only OpenSSH Server is installed, snapshot & disable other enabled Private inbound rules and allow LAN SSH on Private
    $hasOpenSsh = (Test-Path 'C:\Program Files\OpenSSH\sshd.exe') -or (Test-Path ""$env:SystemRoot\System32\OpenSSH\sshd.exe"") -or (Test-Path (Join-Path $ScriptRoot 'OpenSSH-Win64\sshd.exe'))
    if ($hasOpenSsh) {
        $rulesSnapPath = Join-Path $localBackupDir 'firewall_inbound_rules_snapshot.json'
        if (-not (Test-Path $rulesSnapPath)) {
            $activeInRules = Get-NetFirewallRule -Direction Inbound -Action Allow -Enabled True -ErrorAction SilentlyContinue | Where-Object { $_.Name -notlike 'FunkenwerkFortress_*' }
            @($activeInRules | Select-Object -ExpandProperty Name) | ConvertTo-Json | Set-Content -Path $rulesSnapPath -Encoding UTF8
            $activeInRules | Disable-NetFirewallRule -ErrorAction SilentlyContinue
        }
        New-NetFirewallRule -Name 'FunkenwerkFortress_AllowInbound_SSH_LAN' -DisplayName 'Funkenwerk OS: Ed25519 SSH Wartung (Nur LAN)' -Direction Inbound -Action Allow -Profile Private -Protocol TCP -LocalPort 22 -RemoteAddress '192.168.188.0/24','LocalSubnet' | Out-Null
        Set-NetFirewallProfile -Profile Private -AllowInboundRules True -ErrorAction SilentlyContinue
    }

    Write-Host '[OK] Festungs-Firewall (Profile-Shield + Outbound-Whitelist) scharfgeschaltet.' -ForegroundColor Green
}

if ($Mode -in @('FullFortress', 'BluetoothFix')) {
    $usbOpenSshDir = Join-Path $ScriptRoot 'OpenSSH-Win64'
    if (-not (Test-Path (Join-Path $usbOpenSshDir 'sshd.exe'))) {
        $usbOpenSshDir = Join-Path $ScriptRoot 'Payload\OpenSSH-Win64'
    }
    $usbPubKeyFile = Join-Path $ScriptRoot 'authorized_notebook_ed25519.pub'
    if (-not (Test-Path $usbPubKeyFile)) {
        $usbPubKeyFile = Join-Path $ScriptRoot 'Payload\authorized_notebook_ed25519.pub'
    }

    if ((Test-Path (Join-Path $usbOpenSshDir 'sshd.exe')) -and (Test-Path $usbPubKeyFile)) {
        Write-Host '[..] Installiere Microsoft OpenSSH Server (Ed25519 Key-Only, Passwort-Login AUS)...' -ForegroundColor Cyan
        $targetSshDir = 'C:\Program Files\OpenSSH'
        Stop-Service -Name 'sshd' -Force -ErrorAction SilentlyContinue
        Stop-Service -Name 'ssh-agent' -Force -ErrorAction SilentlyContinue
        Start-Sleep -Milliseconds 400
        New-Item -ItemType Directory -Path $targetSshDir -Force | Out-Null
        Copy-Item -Path (Join-Path $usbOpenSshDir '*') -Destination $targetSshDir -Recurse -Force

        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $targetSshDir 'install-sshd.ps1') -Confirm:$false | Out-Null

        $sshDataDir = 'C:\ProgramData\ssh'
        New-Item -ItemType Directory -Path $sshDataDir -Force | Out-Null
        & (Join-Path $targetSshDir 'ssh-keygen.exe') -A 2>$null | Out-Null

        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        $sshdCfgLines = @(
            'Port 22',
            'AddressFamily any',
            'ListenAddress 0.0.0.0',
            'PubkeyAuthentication yes',
            'AuthorizedKeysFile .ssh/authorized_keys',
            'PasswordAuthentication no',
            'PermitEmptyPasswords no',
            'KbdInteractiveAuthentication no',
            'GSSAPIAuthentication no',
            'StrictModes yes',
            'MaxAuthTries 4',
            'Subsystem sftp sftp-server.exe',
            '',
            'Match Group administrators',
            '       AuthorizedKeysFile __PROGRAMDATA__/ssh/administrators_authorized_keys',
            ''
        ) -join ""`n""
        [System.IO.File]::WriteAllText((Join-Path $sshDataDir 'sshd_config'), $sshdCfgLines, $utf8NoBom)

        $pubKeyText = ([System.IO.File]::ReadAllText($usbPubKeyFile).Trim()) + ""`n""
        $adminKeysPath = Join-Path $sshDataDir 'administrators_authorized_keys'
        [System.IO.File]::WriteAllText($adminKeysPath, $pubKeyText, $utf8NoBom)

        $userSshDir = Join-Path $env:USERPROFILE '.ssh'
        New-Item -ItemType Directory -Path $userSshDir -Force | Out-Null
        $userKeysPath = Join-Path $userSshDir 'authorized_keys'
        [System.IO.File]::WriteAllText($userKeysPath, $pubKeyText, $utf8NoBom)

        Import-Module (Join-Path $targetSshDir 'OpenSSHUtils.psm1') -Force
        Repair-SSHFolderPermission -FilePath $sshDataDir -Confirm:$false | Out-Null
        Repair-SshdConfigPermission -FilePath (Join-Path $sshDataDir 'sshd_config') -Confirm:$false | Out-Null
        Get-ChildItem -Path (Join-Path $sshDataDir 'ssh_host_*_key') -ErrorAction SilentlyContinue | ForEach-Object {
            Repair-SshdHostKeyPermission -FilePath $_.FullName -Confirm:$false | Out-Null
        }
        Repair-AuthorizedKeyPermission -FilePath $userKeysPath -Confirm:$false | Out-Null
        Repair-AdministratorsAuthorizedKeysPermission -FilePath $adminKeysPath -Confirm:$false | Out-Null
        & icacls.exe $adminKeysPath /inheritance:r /grant '*S-1-5-18:(F)' /grant '*S-1-5-32-544:(F)' 2>$null | Out-Null

        Set-Service -Name 'sshd' -StartupType Automatic -ErrorAction SilentlyContinue
        & sc.exe failure sshd reset= 0 actions= restart/5000/restart/5000/restart/5000 2>$null | Out-Null
        Start-Service -Name 'sshd' -ErrorAction SilentlyContinue

        Remove-NetFirewallRule -Name 'FunkenwerkFortress_AllowInbound_SSH_LAN' -ErrorAction SilentlyContinue
        New-NetFirewallRule -Name 'FunkenwerkFortress_AllowInbound_SSH_LAN' -DisplayName 'Funkenwerk OS: Ed25519 SSH Wartung (Nur LAN)' -Direction Inbound -Action Allow -Protocol TCP -LocalPort 22 -RemoteAddress '192.168.188.0/24','LocalSubnet' | Out-Null
        Get-NetConnectionProfile -ErrorAction SilentlyContinue | Set-NetConnectionProfile -NetworkCategory Private -ErrorAction SilentlyContinue

        $sshdState = (Get-Service -Name 'sshd' -ErrorAction SilentlyContinue).Status
        Write-Host ""[OK] OpenSSH Server (Ed25519 Key-Only) aktiv! Status: $sshdState"" -ForegroundColor Green
    }

    Write-Host '[..] Optimiere Bluetooth A2DP (Pure-SBC), 5 GHz WLAN-Trennung & USB/PCIe Echtzeit-Audio...' -ForegroundColor Cyan

    # 1. Disable buggy Windows 11 AAC over Bluetooth A2DP -> force glitch-free hardware SBC!
    New-Item -Path 'HKLM:\SYSTEM\CurrentControlSet\Services\BthA2dp\Parameters' -Force | Out-Null
    Set-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Services\BthA2dp\Parameters' -Name 'BluetoothAacEnable' -Type DWord -Value 0
    Set-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Services\BthA2dp\Parameters' -Name 'EnableAAC' -Type DWord -Value 0

    New-Item -Path 'HKLM:\SYSTEM\CurrentControlSet\Services\BTHUSB\Parameters' -Force | Out-Null
    Set-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Services\BTHUSB\Parameters' -Name 'SelectiveSuspendEnabled' -Type DWord -Value 0

    # 2. Stop & disable Bluetooth Hands-Free Telephony (BTAGService) + Hands-Free PnP endpoints + Audio Ducking
    Stop-Service -Name 'BTAGService' -Force -ErrorAction SilentlyContinue
    Set-Service -Name 'BTAGService' -StartupType Disabled -ErrorAction SilentlyContinue
    Get-PnpDevice -Class AudioEndpoint -ErrorAction SilentlyContinue | Where-Object { $_.FriendlyName -match 'Hands-Free|Freisprech' } | Disable-PnpDevice -Confirm:$false -ErrorAction SilentlyContinue
    Get-PnpDevice -ErrorAction SilentlyContinue | Where-Object { $_.InstanceId -match '0000111e-0000-1000-8000-00805f9b34fb' } | Disable-PnpDevice -Confirm:$false -ErrorAction SilentlyContinue

    New-Item -Path 'HKCU:\Software\Microsoft\Multimedia\Audio' -Force | Out-Null
    Set-ItemProperty -Path 'HKCU:\Software\Microsoft\Multimedia\Audio' -Name 'UserDuckingPreference' -Type DWord -Value 3

    # 3. Disable USB Selective Suspend across ALL Realtek / Intel Bluetooth USB controllers + disable PCIe ASPM & WLAN PowerSave
    powercfg /SETACVALUEINDEX SCHEME_CURRENT 2a737441-1930-4402-8d77-b2bebba308a3 48e6b7a6-50f5-4782-a5d4-53bb8f07e226 0
    powercfg /SETACVALUEINDEX SCHEME_CURRENT 501a4d13-42af-4429-9fd1-a8218c268e20 ee12f906-d277-404b-b6da-e5fa1a576df5 0
    powercfg /SETACVALUEINDEX SCHEME_CURRENT 19cbb8fa-5279-450e-9fac-8a3d5fedd0c1 12bbebe6-58d6-4636-95bb-3217ef867c1a 0
    powercfg /setactive SCHEME_CURRENT

    Get-ChildItem -Path 'HKLM:\SYSTEM\CurrentControlSet\Enum\USB' -ErrorAction SilentlyContinue | Where-Object { $_.PSChildName -match 'VID_0BDA|VID_8087' } | ForEach-Object {
        Get-ChildItem -Path $_.PSPath -ErrorAction SilentlyContinue | ForEach-Object {
            $dp = Join-Path $_.PSPath 'Device Parameters'
            if (Test-Path $dp) {
                Set-ItemProperty -Path $dp -Name 'DeviceSelectiveSuspended' -Type DWord -Value 0 -ErrorAction SilentlyContinue
                Set-ItemProperty -Path $dp -Name 'SelectiveSuspendEnabled' -Type DWord -Value 0 -ErrorAction SilentlyContinue
                Set-ItemProperty -Path $dp -Name 'EnhancedPowerManagementEnabled' -Type DWord -Value 0 -ErrorAction SilentlyContinue
                Set-ItemProperty -Path $dp -Name 'IdleInWorkingState' -Type DWord -Value 0 -ErrorAction SilentlyContinue
            }
        }
    }

    # 4. Switch Realtek RTL8821CE Wi-Fi to 5 GHz Preferred (PreferBand = 2) & restore hardware BT-Wi-Fi coexistence
    Get-ChildItem -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\Class\{4d36e972-e325-11ce-bfc1-08002be10318}' -ErrorAction SilentlyContinue | ForEach-Object {
        $desc = (Get-ItemProperty -Path $_.PSPath -ErrorAction SilentlyContinue).DriverDesc
        if ($desc -match '8821|Wireless') {
            Set-ItemProperty -Path $_.PSPath -Name 'bLowPowerEnable' -Type String -Value '0' -ErrorAction SilentlyContinue
            Set-ItemProperty -Path $_.PSPath -Name 'Scan_IPSEnable' -Type String -Value '0' -ErrorAction SilentlyContinue
            Set-ItemProperty -Path $_.PSPath -Name 'PowerSaveMode' -Type String -Value '0' -ErrorAction SilentlyContinue
            Set-ItemProperty -Path $_.PSPath -Name 'RoamAggressiveness' -Type String -Value '1' -ErrorAction SilentlyContinue
            Set-ItemProperty -Path $_.PSPath -Name 'PreferBand' -Type String -Value '2' -ErrorAction SilentlyContinue
            Set-ItemProperty -Path $_.PSPath -Name 'PreferredBand' -Type String -Value '2' -ErrorAction SilentlyContinue
            Remove-ItemProperty -Path $_.PSPath -Name 'ConcurrentOpPref' -ErrorAction SilentlyContinue
            Remove-ItemProperty -Path $_.PSPath -Name 'MultiChannelFcsMode' -ErrorAction SilentlyContinue
        }
    }
    Get-NetAdapterAdvancedProperty -ErrorAction SilentlyContinue | Where-Object { $_.RegistryKeyword -in @('PreferBand','PreferredBand') } | Set-NetAdapterAdvancedProperty -RegistryValue 2 -ErrorAction SilentlyContinue

    New-Item -Path 'HKCU:\Software\FunkenwerkOS' -Force | Out-Null
    Set-ItemProperty -Path 'HKCU:\Software\FunkenwerkOS' -Name 'BtAudioOptimizedVersion' -Type DWord -Value 2

    Restart-Service -Name 'bthserv' -Force -ErrorAction SilentlyContinue
    Start-Service -Name 'BthAvctpSvc' -ErrorAction SilentlyContinue
    Write-Host '[OK] Bluetooth Pure-SBC + 5 GHz WLAN-Trennung + Echtzeit-Audio aktiviert!' -ForegroundColor Green

    if ($Mode -eq 'BluetoothFix') {
        Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows NT\CurrentVersion\Winlogon' -Name 'Shell' -ErrorAction SilentlyContinue
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon' -Name 'Shell' -Value 'explorer.exe' -ErrorAction SilentlyContinue
        if (-not (Get-Process -Name 'explorer' -ErrorAction SilentlyContinue)) {
            Start-Process -FilePath 'explorer.exe'
        }
        $osExeFix = Join-Path $systemCollectionTarget 'Funkenwerk OS\FunkenwerkOS.exe'
        if (Test-Path $osExeFix) {
            New-Item -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Force | Out-Null
            Set-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Name 'FunkenwerkOS' -Value ""`""$osExeFix`"" --shell --workspace gewerbe""
            Start-Sleep -Seconds 1
            Start-Process -FilePath $osExeFix -ArgumentList '--shell --workspace gewerbe'
        }
        Write-Host '[OK] Windows-Taskleiste aktiviert & Funkenwerk OS neu gestartet!' -ForegroundColor Green
        Start-Sleep -Seconds 2
        exit 0
    }
}

if ($Mode -eq 'FullFortress') {
    $osExe = Join-Path $systemCollectionTarget 'Funkenwerk OS\FunkenwerkOS.exe'
    if (-not (Test-Path $osExe)) {
        $osExe = Join-Path $desktopCollectionTarget 'Funkenwerk OS\FunkenwerkOS.exe'
    }
    $shellCmdValue = ""`""$osExe`"" --shell --workspace gewerbe""

    if (Test-Path $osExe) {
        Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows NT\CurrentVersion\Winlogon' -Name 'Shell' -ErrorAction SilentlyContinue
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon' -Name 'Shell' -Value 'explorer.exe' -ErrorAction SilentlyContinue
        New-Item -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Force | Out-Null
        Set-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Name 'FunkenwerkOS' -Value $shellCmdValue
        Write-Host '[OK] Windows-Taskleiste (explorer.exe) & Funkenwerk OS Autostart gesetzt.' -ForegroundColor Green
    }

    # Falls direkt im Boot / Windows-Einrichtungs-Modus (OOBE / Shift+F10) gestartet:
    if ($isOobeOrSystem) {
        Write-Host '[..] Ueberspringe komplette Windows-Einrichtung (OOBE) & richte Direkt-Boot fuer Alina ein...' -ForegroundColor Cyan

        try {
            Set-WinSystemLocale -SystemLocale de-DE
            Set-TimeZone -Id 'W. Europe Standard Time'
        } catch { }

        # 1. OS-SEC-03: Lokales Admin-Konto 'Alina' mit kryptografisch starkem Initial-Passwort anlegen (NIEMALS Leer-Passwort!)
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%^*+-_'
        $rngBytes = New-Object byte[] 24
        [System.Security.Cryptography.RandomNumberGenerator]::Fill($rngBytes)
        $oobeInitPwdChars = for ($b in $rngBytes) { $alphabet[$b % $alphabet.Length] }
        $oobeInitPwd = -join $oobeInitPwdChars

        & net.exe user Alina $oobeInitPwd /add /y 2>$null | Out-Null
        & net.exe user Alina $oobeInitPwd 2>$null | Out-Null
        & net.exe localgroup Administratoren Alina /add 2>$null | Out-Null
        & net.exe localgroup Administrators Alina /add 2>$null | Out-Null
        & wmic.exe useraccount where ""Name='Alina'"" set PasswordExpires=False 2>$null | Out-Null

        $sysVaultDir = 'C:\ProgramData\FunkenwerkOS\Backup'
        New-Item -ItemType Directory -Path $sysVaultDir -Force | Out-Null
        & icacls.exe $sysVaultDir /inheritance:r /grant '*S-1-5-18:(OI)(CI)(F)' /grant '*S-1-5-32-544:(OI)(CI)(F)' 2>$null | Out-Null
        $oobeVaultPath = Join-Path $sysVaultDir 'InitialOobePassword.dpapi'
        $oobeSec = ConvertTo-SecureString -String $oobeInitPwd -AsPlainText -Force
        Set-Content -Path $oobeVaultPath -Value (ConvertFrom-SecureString -SecureString $oobeSec) -Encoding ASCII
        & icacls.exe $oobeVaultPath /inheritance:r /grant '*S-1-5-18:(F)' /grant '*S-1-5-32-544:(F)' 2>$null | Out-Null

        # 2. Winlogon ohne Klartext-DefaultPassword in der Registry absichern
        $winlogonPath = 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon'
        Set-ItemProperty -Path $winlogonPath -Name 'DefaultUserName' -Type String -Value 'Alina'
        Remove-ItemProperty -Path $winlogonPath -Name 'DefaultPassword' -ErrorAction SilentlyContinue

        # 3. Default-User-Profil (NTUSER.DAT) direkt mit Funkenwerk OS Shell & deutschem Tastaturlayout impfen
        $defaultNtUser = 'C:\Users\Default\NTUSER.DAT'
        if (Test-Path $defaultNtUser) {
            & reg.exe load HKLM\TempDefaultUser $defaultNtUser 2>$null | Out-Null
            & reg.exe add ""HKLM\TempDefaultUser\Software\Microsoft\Windows NT\CurrentVersion\Winlogon"" /v Shell /t REG_SZ /d $shellCmdValue /f 2>$null | Out-Null
            & reg.exe add ""HKLM\TempDefaultUser\Keyboard Layout\Preload"" /v 1 /t REG_SZ /d 00000407 /f 2>$null | Out-Null
            & reg.exe add ""HKLM\TempDefaultUser\Software\FunkenwerkOS"" /v WallPowerConfigured /t REG_DWORD /d 1 /f 2>$null | Out-Null
            [gc]::Collect()
            Start-Sleep -Milliseconds 300
            & reg.exe unload HKLM\TempDefaultUser 2>$null | Out-Null
        }

        # 4. RunOnce-Absicherung fuer den allerersten Login von Alina
        $firstLogonCmd = 'reg add ""HKCU\Software\Microsoft\Windows NT\CurrentVersion\Winlogon"" /v Shell /t REG_SZ /d ""\""C:\Funkenwerk Collection\Funkenwerk OS\FunkenwerkOS.exe\"" --shell"" /f'
        New-Item -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\RunOnce' -Force | Out-Null
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\RunOnce' -Name 'FunkenwerkShellInit' -Type String -Value ""cmd.exe /c $firstLogonCmd""

        # 5. Windows-11-Ersteinrichtung (OOBE) in der Registry als 100% abgeschlossen markieren
        New-Item -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Setup\OOBE' -Force | Out-Null
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Setup\OOBE' -Name 'OOBEInProgress' -Type DWord -Value 0
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Setup\OOBE' -Name 'SetupDisplayedEula' -Type DWord -Value 1
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Setup\OOBE' -Name 'PrivacyConsentStatus' -Type DWord -Value 1
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Setup\OOBE' -Name 'ProtectYourPC' -Type DWord -Value 3
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Setup\OOBE' -Name 'UnattendCreatedUser' -Type DWord -Value 1

        New-Item -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\OOBE' -Force | Out-Null
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\OOBE' -Name 'SkipMachineOOBE' -Type DWord -Value 1
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\OOBE' -Name 'SkipUserOOBE' -Type DWord -Value 1
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\OOBE' -Name 'DisableVoice' -Type DWord -Value 1
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\OOBE' -Name 'PrivacyConsentStatus' -Type DWord -Value 1
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\OOBE' -Name 'ProtectYourPC' -Type DWord -Value 3
        Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\OOBE' -Name 'LaunchUserOOBE' -Type DWord -Value 0

        Set-ItemProperty -Path 'HKLM:\SYSTEM\Setup' -Name 'OOBEInProgress' -Type DWord -Value 0
        Set-ItemProperty -Path 'HKLM:\SYSTEM\Setup' -Name 'SetupPhase' -Type DWord -Value 0
        Set-ItemProperty -Path 'HKLM:\SYSTEM\Setup' -Name 'SetupType' -Type DWord -Value 0
        Set-ItemProperty -Path 'HKLM:\SYSTEM\Setup' -Name 'SystemSetupInProgress' -Type DWord -Value 0
        Set-ItemProperty -Path 'HKLM:\SYSTEM\Setup' -Name 'RestartSetup' -Type DWord -Value 0
        Set-ItemProperty -Path 'HKLM:\SYSTEM\Setup' -Name 'CmdLine' -Type String -Value ''

        & net.exe user defaultuser0 /active:no 2>$null | Out-Null

        Write-Host '[OK] Windows-Einrichtung komplett uebersprungen! Mini-PC startet jetzt direkt in Funkenwerk OS neu...' -ForegroundColor Green
        Start-Sleep -Seconds 2
        & shutdown.exe /r /f /t 2
        exit 0
    }

    if (Test-Path $osExe) {
        if (-not (Get-Process -Name 'explorer' -ErrorAction SilentlyContinue)) {
            Start-Process -FilePath 'explorer.exe'
        }
        Start-Process -FilePath $osExe -ArgumentList '--shell --workspace gewerbe'
    }
    Write-Host ''
    Write-Host '[OK] FERTIG! Funkenwerk OS laeuft und Netzwerk-Fernwartung ist aktiv.' -ForegroundColor Green
    Start-Sleep -Seconds 2
}
";
        }

        // =====================================================================
        // ONE-CLICK USB STICK CREATOR (SAFE, SECRET-SCRUBBED)
        // =====================================================================
        public static List<DriveInfo> GetCandidateUsbDrives()
        {
            string sysRoot = Path.GetPathRoot(Environment.SystemDirectory) ?? @"C:\";
            var list = new List<DriveInfo>();
            try
            {
                foreach (var d in DriveInfo.GetDrives())
                {
                    if (!d.IsReady) continue;
                    if (string.Equals(d.RootDirectory.FullName, sysRoot, StringComparison.OrdinalIgnoreCase)) continue;
                    if (d.DriveType is DriveType.Removable or DriveType.Fixed)
                    {
                        // Never allow C:\
                        if (d.Name.StartsWith("C:", StringComparison.OrdinalIgnoreCase)) continue;
                        list.Add(d);
                    }
                }
            }
            catch { }
            return list;
        }

        public static async Task<(bool Success, string Message, string OutputFolder)> ExportCompleteUsbStickAsync(
            string targetRootOrFolder,
            IProgress<string>? progress = null)
        {
            if (string.IsNullOrWhiteSpace(targetRootOrFolder))
                return (false, "Kein Zielverzeichnis gewählt.", "");

            string fullTarget = Path.GetFullPath(targetRootOrFolder);
            string sysDrive = Path.GetPathRoot(Environment.SystemDirectory) ?? @"C:\";

            // Block exporting straight onto C:\ root
            if (string.Equals(fullTarget.TrimEnd('\\') + "\\", sysDrive, StringComparison.OrdinalIgnoreCase))
                return (false, "Systemlaufwerk C:\\ ist als direktes USB-Ziel gesperrt.", "");

            string bundleRoot = Path.Combine(fullTarget, "Funkenwerk-MiniPC-Festung");
            string bundleCollectionDir = Path.Combine(bundleRoot, "Funkenwerk Collection");

            return await Task.Run(() =>
            {
                try
                {
                    Directory.CreateDirectory(bundleRoot);
                    Directory.CreateDirectory(bundleCollectionDir);

                    progress?.Report("Erzeuge Festungs-Skripte & Notfall-Wiederherstellung...");
                    WriteMiniPcDeploymentScripts(bundleRoot);

                    string rootStarterCmd = "@echo off\r\n" +
                        "chcp 65001 >nul\r\n" +
                        "call \"%~dp0Funkenwerk-MiniPC-Festung\\2_START_FUNKENWERK_OS_AUF_MINIPC.cmd\"\r\n";
                    File.WriteAllText(Path.Combine(fullTarget, "FUNKENWERK_OS_AUF_MINIPC_INSTALLIEREN.cmd"), rootStarterCmd, new UTF8Encoding(false));

                    string rootBtFixCmd = "@echo off\r\n" +
                        "chcp 65001 >nul\r\n" +
                        "call \"%~dp0Funkenwerk-MiniPC-Festung\\4_BLUETOOTH_AUDIO_FIX_MINIPC.cmd\"\r\n";
                    File.WriteAllText(Path.Combine(fullTarget, "BLUETOOTH_FIX_MINIPC.cmd"), rootBtFixCmd, new UTF8Encoding(false));

                    string srcRoot = OsVaultAndStorage.CollectionRootDir;
                    if (!Directory.Exists(srcRoot))
                        return (false, "Funkenwerk Collection Quellordner nicht gefunden.", bundleRoot);

                    string[] appDirs = Directory.GetDirectories(srcRoot);
                    int copiedFiles = 0;

                    foreach (string appDir in appDirs)
                    {
                        string folderName = Path.GetFileName(appDir);
                        if (IsSkippedUsbDirectoryName(folderName))
                            continue;
                        progress?.Report($"Kopiere {folderName}...");
                        string destAppDir = Path.Combine(bundleCollectionDir, folderName);
                        copiedFiles += CopyCollectionSafeRecursive(appDir, destAppDir);
                    }

                    // Also copy top-level .lnk shortcuts as convenience
                    foreach (string lnk in Directory.GetFiles(srcRoot, "*.lnk"))
                    {
                        string destLnk = Path.Combine(bundleCollectionDir, Path.GetFileName(lnk));
                        File.Copy(lnk, destLnk, overwrite: true);
                    }

                    progress?.Report($"Fertig ({copiedFiles} Dateien übertragen).");
                    return (true, $"USB-Paket bereit ({copiedFiles} Dateien, 0 lokale Secrets).", bundleRoot);
                }
                catch (Exception ex)
                {
                    return (false, $"Fehler beim Erstellen: {ex.Message}", bundleRoot);
                }
            });
        }

        public static int CopyCollectionSafeRecursive(string sourceDir, string targetDir)
        {
            if (string.IsNullOrWhiteSpace(sourceDir) || !Directory.Exists(sourceDir))
                return 0;

            string srcName = Path.GetFileName(sourceDir.TrimEnd('\\', '/'));
            if (IsSkippedUsbDirectoryName(srcName))
                return 0;

            int count = 0;
            bool createdTarget = false;

            foreach (string file in Directory.GetFiles(sourceDir))
            {
                if (!IsAllowedUsbRuntimeArtifact(file))
                    continue;

                if (!createdTarget)
                {
                    Directory.CreateDirectory(targetDir);
                    createdTarget = true;
                }

                string destFile = Path.Combine(targetDir, Path.GetFileName(file));
                File.Copy(file, destFile, overwrite: true);
                count++;
            }

            foreach (string subDir in Directory.GetDirectories(sourceDir))
            {
                string subName = Path.GetFileName(subDir);
                if (IsSkippedUsbDirectoryName(subName))
                    continue;

                count += CopyCollectionSafeRecursive(subDir, Path.Combine(targetDir, subName));
            }

            return count;
        }

        private static int CopyDirectoryCleanForUsb(string sourceDir, string targetDir)
            => CopyCollectionSafeRecursive(sourceDir, targetDir);
    }
}
