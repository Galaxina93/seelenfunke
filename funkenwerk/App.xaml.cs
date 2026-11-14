using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;

namespace Funkenwerk
{
    public sealed class WindowsSentinelEventDto
    {
        public string Platform { get; set; } = "windows_desktop";
        public string Severity { get; set; } = "error";
        public string Component { get; set; } = "funkenwerk_wpf";
        public string Title { get; set; } = "";
        public string Message { get; set; } = "";
        public string Stacktrace { get; set; } = "";
        public string Fingerprint { get; set; } = "";
        public string AppVersion { get; set; } = "1.4.0";
        public string Device { get; set; } = Environment.MachineName;
        public bool AutoHealed { get; set; }
        public string HealSummary { get; set; } = "";
        public string OccurredAt { get; set; } = DateTime.UtcNow.ToString("O");
    }

    public static class WindowsErrorSentinel
    {
        private static readonly object s_lock = new();
        private static readonly ConcurrentDictionary<string, DateTime> s_recent = new(StringComparer.Ordinal);
        private static readonly HttpClient s_http = FunkenwerkSecurityVault.CreateSecureHttpClient(TimeSpan.FromSeconds(12));
        private static int s_flushing;

        private static string QueuePath => FunkenwerkSecurityVault.GetAppDataFilePath("error_sentinel_queue.json", migrateFromBaseDir: true);

        public static string ScrubSecrets(string? raw)
        {
            if (string.IsNullOrEmpty(raw)) return "";
            string s = Regex.Replace(raw, @"(Bearer\s+)[A-Za-z0-9\-._~+/=|]+", "$1[REDACTED]", RegexOptions.IgnoreCase);
            s = Regex.Replace(s, @"\b\d+\|[A-Za-z0-9]{32,}\b", "[REDACTED_SANCTUM]");
            s = Regex.Replace(s, @"\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b", "[REDACTED_JWT]");
            s = Regex.Replace(s, @"(rtsps?://)[^\s""'@]+@", "$1[REDACTED_RTSP_AUTH]@", RegexOptions.IgnoreCase);
            s = Regex.Replace(s, @"FWAES256GCM1:[A-Za-z0-9+/=]+", "FWAES256GCM1:[REDACTED]", RegexOptions.IgnoreCase);
            s = Regex.Replace(s, @"FWVAULT2:[A-Za-z0-9+/=]+", "FWVAULT2:[REDACTED]", RegexOptions.IgnoreCase);
            s = Regex.Replace(s, @"DPAPI:[A-Za-z0-9+/=]+", "DPAPI:[REDACTED]", RegexOptions.IgnoreCase);
            s = Regex.Replace(s, @"(""password""\s*:\s*"")[^""]*("")", "$1[REDACTED]$2", RegexOptions.IgnoreCase);
            s = Regex.Replace(s, @"(?i)((?:api[_-]?key|token|password|passwd|secret|authorization)\s*[:=]\s*[""']?)([^\s""'&,;}{]+)", "$1[REDACTED]");
            s = Regex.Replace(s, @"(?i)([?&](?:key|token|password|secret)=)[^&\s]+", "$1[REDACTED]");
            if (s.Length > 3800) s = s.Substring(0, 3800);
            return s;
        }

        public static string ComputeFingerprint(string component, string title)
        {
            string raw = $"windows_desktop|{(component ?? "funkenwerk_wpf").Trim().ToLowerInvariant()}|{(title ?? "").Trim().ToLowerInvariant()}";
            byte[] hash = SHA256.HashData(Encoding.UTF8.GetBytes(raw));
            return Convert.ToHexString(hash).ToLowerInvariant();
        }

        public static void RecordException(string component, string title, Exception ex, bool flushSynchronously = false)
        {
            RecordEvent(
                component: component,
                title: $"{title}: {ex.GetType().Name}",
                message: ex.Message,
                stacktrace: ex.ToString(),
                autoHealed: false,
                healSummary: null,
                severity: "error",
                flushSynchronously: flushSynchronously);
        }

        public static void RecordAutoHeal(string component, string title, string message, string healSummary)
        {
            RecordEvent(component, title, message, null, autoHealed: true, healSummary: healSummary, severity: "warning");
        }

        public static void RecordEvent(
            string component,
            string title,
            string? message,
            string? stacktrace,
            bool autoHealed = false,
            string? healSummary = null,
            string severity = "error",
            bool flushSynchronously = false)
        {
            try
            {
                string safeComp = ScrubSecrets(component).Trim();
                string safeTitle = ScrubSecrets(title).Trim();
                if (string.IsNullOrWhiteSpace(safeTitle)) return;

                string fp = ComputeFingerprint(safeComp, safeTitle);
                string throttleKey = autoHealed ? (fp + ":healed") : fp;
                if (s_recent.TryGetValue(throttleKey, out var prev) && (DateTime.UtcNow - prev).TotalSeconds < 180)
                    return;
                s_recent[throttleKey] = DateTime.UtcNow;

                var evt = new WindowsSentinelEventDto
                {
                    Platform = "windows_desktop",
                    Severity = autoHealed ? "warning" : severity,
                    Component = string.IsNullOrWhiteSpace(safeComp) ? "funkenwerk_wpf" : safeComp,
                    Title = safeTitle,
                    Message = ScrubSecrets(message ?? safeTitle),
                    Stacktrace = ScrubSecrets(stacktrace ?? ""),
                    Fingerprint = fp,
                    AppVersion = "1.4.0",
                    Device = Environment.MachineName,
                    AutoHealed = autoHealed,
                    HealSummary = ScrubSecrets(healSummary ?? ""),
                    OccurredAt = DateTime.UtcNow.ToString("O")
                };

                lock (s_lock)
                {
                    var list = LoadUnsafe();
                    list.RemoveAll(x => x.Fingerprint == evt.Fingerprint && x.AutoHealed == evt.AutoHealed);
                    list.Add(evt);
                    while (list.Count > 50) list.RemoveAt(0);
                    SaveUnsafe(list);
                }

                if (flushSynchronously)
                {
                    try { FlushAsync().Wait(TimeSpan.FromMilliseconds(1800)); } catch { }
                }
                else
                {
                    _ = Task.Run(FlushAsync);
                }
            }
            catch { }
        }

        public static async Task FlushAsync()
        {
            if (FunkenwerkSecurityVault.IsUsingTemporaryAppData) return;
            if (Interlocked.Exchange(ref s_flushing, 1) == 1) return;
            try
            {
                List<WindowsSentinelEventDto> batch;
                lock (s_lock)
                {
                    batch = LoadUnsafe().Take(20).ToList();
                }
                if (batch.Count == 0) return;

                string settingsPath = FunkenwerkSecurityVault.GetAppDataFilePath("settings.json", migrateFromBaseDir: true);
                if (!File.Exists(settingsPath)) return;

                var cfg = JsonSerializer.Deserialize<AppSettings>(File.ReadAllText(settingsPath));
                if (cfg == null) return;

                string rawCandidateUrl = string.IsNullOrWhiteSpace(cfg.ApiBaseUrl) ? "https://stage.mein-seelenfunke.de/api" : cfg.ApiBaseUrl;
                // NormalizeSecureApiUrl enforces the strict host allowlist and throws UnauthorizedAccessException on untrusted hosts
                string baseUrl = FunkenwerkSecurityVault.NormalizeSecureApiUrl(rawCandidateUrl);
                if (!Uri.TryCreate(baseUrl, UriKind.Absolute, out var validatedUri) ||
                    !FunkenwerkSecurityVault.IsAllowedApiHost(validatedUri.Host))
                {
                    return;
                }

                // Strictly select ONLY the token matching the validated target environment (never cross-environment!)
                string token = MainWindow.ResolveSavedTokenForEnvironment(cfg, baseUrl);
                if (string.IsNullOrWhiteSpace(token)) return;

                var wireEvents = batch.Select(e => new
                {
                    platform = e.Platform,
                    severity = e.Severity,
                    component = e.Component,
                    title = e.Title,
                    message = e.Message,
                    stacktrace = string.IsNullOrWhiteSpace(e.Stacktrace) ? null : e.Stacktrace,
                    fingerprint = e.Fingerprint,
                    app_version = e.AppVersion,
                    device = e.Device,
                    auto_healed = e.AutoHealed,
                    heal_summary = string.IsNullOrWhiteSpace(e.HealSummary) ? null : e.HealSummary,
                    occurred_at = e.OccurredAt
                }).ToList();

                using var req = new HttpRequestMessage(HttpMethod.Post, $"{baseUrl.TrimEnd('/')}/system/error-ingest");
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token.Trim());
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                req.Content = new StringContent(JsonSerializer.Serialize(new { events = wireEvents }), new UTF8Encoding(false), "application/json");

                using var resp = await s_http.SendAsync(req).ConfigureAwait(false);
                if (resp.IsSuccessStatusCode)
                {
                    var sentKeys = new HashSet<string>(batch.Select(b => b.Fingerprint + "|" + b.OccurredAt), StringComparer.Ordinal);
                    lock (s_lock)
                    {
                        var cur = LoadUnsafe();
                        cur.RemoveAll(c => sentKeys.Contains(c.Fingerprint + "|" + c.OccurredAt));
                        SaveUnsafe(cur);
                    }
                }
            }
            catch { }
            finally
            {
                Interlocked.Exchange(ref s_flushing, 0);
            }
        }

        private static List<WindowsSentinelEventDto> LoadUnsafe()
        {
            try
            {
                if (!File.Exists(QueuePath)) return new List<WindowsSentinelEventDto>();
                return JsonSerializer.Deserialize<List<WindowsSentinelEventDto>>(File.ReadAllText(QueuePath)) ?? new List<WindowsSentinelEventDto>();
            }
            catch
            {
                return new List<WindowsSentinelEventDto>();
            }
        }

        private static void SaveUnsafe(List<WindowsSentinelEventDto> list)
        {
            try
            {
                if (list.Count == 0)
                {
                    if (File.Exists(QueuePath)) File.Delete(QueuePath);
                    return;
                }
                FunkenwerkSecurityVault.WriteSecureFileText(QueuePath, JsonSerializer.Serialize(list));
            }
            catch { }
        }
    }

    public partial class App : Application
    {
        [DllImport("kernel32.dll")]
        private static extern bool AttachConsole(int dwProcessId);

        private IDisposable? _selfTestAppDataScope;
        private string? _selfTestTempDir;

        private static string RedactSensitiveCrashDetails(string raw) => WindowsErrorSentinel.ScrubSecrets(raw);

        protected override void OnStartup(StartupEventArgs e)
        {
            bool isMigrateMode = e.Args != null && e.Args.Contains("--migrate-configs", StringComparer.OrdinalIgnoreCase);
            if (isMigrateMode)
            {
                AttachConsole(-1);
                try
                {
                    string summary = Funkenwerk.MainWindow.MigrateAllLocalConfigsHeadless();
                    Console.WriteLine(summary);
                    Environment.Exit(0);
                }
                catch (Exception ex)
                {
                    Console.WriteLine("MIGRATE-CONFIGS ERROR: " + RedactSensitiveCrashDetails(ex.Message));
                    Environment.Exit(1);
                }
                return;
            }

            bool isSelfTest = e.Args != null && e.Args.Contains("--self-test", StringComparer.OrdinalIgnoreCase);
            if (isSelfTest)
            {
                _selfTestTempDir = Path.Combine(Path.GetTempPath(), "fw_suite_selftest_" + Guid.NewGuid().ToString("N"));
                _selfTestAppDataScope = FunkenwerkSecurityVault.UseTemporaryAppDataDirectory(_selfTestTempDir);
            }

            string reportPath = Path.Combine(FunkenwerkSecurityVault.AppDataDirectory, "KameraMedien", "verification_report.txt");
            Directory.CreateDirectory(Path.GetDirectoryName(reportPath)!);

            if (!isSelfTest)
            {
                _ = Task.Run(WindowsErrorSentinel.FlushAsync);
            }

            AppDomain.CurrentDomain.UnhandledException += (_, args) =>
            {
                if (args.ExceptionObject is Exception ex)
                {
                    WindowsErrorSentinel.RecordException("wpf.appdomain", "Unbehandelte Ausnahme in Funkenwerk Suite", ex, flushSynchronously: !isSelfTest);
                }
            };

            TaskScheduler.UnobservedTaskException += (_, args) =>
            {
                try
                {
                    WindowsErrorSentinel.RecordException("wpf.task", "Hintergrund-Task-Ausnahme in Funkenwerk Suite", args.Exception);
                    args.SetObserved();
                }
                catch { }
            };

            this.DispatcherUnhandledException += (s, exArgs) =>
            {
                try { File.WriteAllText(reportPath, "UNHANDLED EXCEPTION:\r\n" + RedactSensitiveCrashDetails(exArgs.Exception.ToString())); } catch { }
                WindowsErrorSentinel.RecordException("wpf.dispatcher", "UI-Ausnahme in Funkenwerk Suite", exArgs.Exception, flushSynchronously: !isSelfTest);
                exArgs.Handled = true;
                Shutdown(1);
            };

            base.OnStartup(e);

            if (isSelfTest)
            {
                AttachConsole(-1);
                Dispatcher.InvokeAsync(async () =>
                {
                    int exitCode = 1;
                    try
                    {
                        if (MainWindow is MainWindow mw)
                        {
                            bool ok = await mw.RunAutomatedSuiteSelfTestAsync();
                            exitCode = ok ? 0 : 1;
                        }
                    }
                    catch (Exception ex)
                    {
                        Console.WriteLine("SELF-TEST EXCEPTION: " + RedactSensitiveCrashDetails(ex.ToString()));
                        exitCode = 2;
                    }
                    finally
                    {
                        try { _selfTestAppDataScope?.Dispose(); } catch { }
                        try
                        {
                            if (!string.IsNullOrEmpty(_selfTestTempDir) && Directory.Exists(_selfTestTempDir))
                                Directory.Delete(_selfTestTempDir, recursive: true);
                        }
                        catch { }
                    }
                    Environment.Exit(exitCode);
                });
                return;
            }

            if (e.Args != null && e.Args.Contains("--verify-camera-studio", StringComparer.OrdinalIgnoreCase))
            {
                Dispatcher.InvokeAsync(async () =>
                {
                    try
                    {
                        if (MainWindow is MainWindow mw)
                        {
                            await mw.RunAutomatedCameraStudioVerificationAsync(reportPath);
                        }
                        else
                        {
                            File.WriteAllText(reportPath, "MainWindow was null");
                        }
                    }
                    catch (Exception ex)
                    {
                        File.WriteAllText(reportPath, "EXCEPTION: " + RedactSensitiveCrashDetails(ex.ToString()));
                    }
                    finally
                    {
                        Shutdown(0);
                    }
                });
            }
        }
    }
}
