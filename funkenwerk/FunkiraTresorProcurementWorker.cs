using System;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace Funkenwerk
{
    /// <summary>
    /// Isolierter Tresor-Beschaffungs-Worker für freigegebene Einkaufi-Tickets (<= 25,00 EUR Auto-Kauf auf LIVE oder explizites Go).
    /// Nutzt ein dediziertes Browser-Profil (%LOCALAPPDATA%\FunkenwerkTresor\IsolatedBrowserProfile, Port 9239)
    /// ohne manuelle Freigabe von chrome://inspect/#remote-debugging und bricht bei > 5% Preisabweichung sofort ab.
    /// </summary>
    internal static class FunkiraTresorProcurementWorker
    {
        public const int IsolatedCdpPort = 9239;
        public const double MaxAllowedPriceDriftRatio = 0.05;

        public static string IsolatedProfileDirectory =>
            Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "FunkenwerkTresor",
                "IsolatedBrowserProfile");

        public static async Task<int> PollAndProcessQueueOnceAsync(
            HttpClient http,
            string apiBaseUrl,
            string bearerToken,
            Func<string, string, int, Task<(bool Ok, int LiveTotalCents, string? OrderReference, string? Error)>>? browserCheckoutExecutor,
            CancellationToken ct)
        {
            if (string.IsNullOrWhiteSpace(apiBaseUrl) || string.IsNullOrWhiteSpace(bearerToken))
                return 0;

            string safeBase = FunkenwerkSecurityVault.NormalizeSecureApiUrl(apiBaseUrl).TrimEnd('/');
            using var req = new HttpRequestMessage(HttpMethod.Get, $"{safeBase}/ai/tresor-queue");
            req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
            req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearerToken);

            using var resp = await http.SendAsync(req, ct).ConfigureAwait(false);
            if (!resp.IsSuccessStatusCode)
                return 0;

            string json = await resp.Content.ReadAsStringAsync(ct).ConfigureAwait(false);
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty("orders", out var queueArr) || queueArr.ValueKind != JsonValueKind.Array)
                return 0;

            int processed = 0;
            foreach (var item in queueArr.EnumerateArray())
            {
                string ticketId = item.TryGetProperty("id", out var idEl) ? (idEl.GetString() ?? "") : "";
                JsonElement payloadEl = item.TryGetProperty("action_payload", out var apEl) && apEl.ValueKind == JsonValueKind.Object ? apEl : item;
                string reorderUrl = payloadEl.TryGetProperty("reorder_url", out var urlEl) ? (urlEl.GetString() ?? "") : "";
                string itemName = payloadEl.TryGetProperty("item_name", out var nameEl) ? (nameEl.GetString() ?? "") : "";
                int expectedCents = payloadEl.TryGetProperty("gross_total_cents", out var centsEl) && centsEl.TryGetInt32(out int c) ? c : 0;
                int packs = payloadEl.TryGetProperty("packs", out var packsEl) && packsEl.TryGetInt32(out int p) ? Math.Max(1, p) : 1;

                if (string.IsNullOrWhiteSpace(ticketId) || string.IsNullOrWhiteSpace(reorderUrl) || expectedCents <= 0)
                    continue;

                if (!Uri.TryCreate(reorderUrl, UriKind.Absolute, out var targetUri) || targetUri.Scheme != Uri.UriSchemeHttps)
                {
                    await ReportTresorOutcomeAsync(http, safeBase, bearerToken, ticketId, false, null, null, "Ungültige HTTPS-Nachbestell-URL blockiert.", ct).ConfigureAwait(false);
                    continue;
                }

                if (browserCheckoutExecutor == null)
                {
                    EnsureIsolatedSupplierTabOpened(targetUri.AbsoluteUri);
                    continue;
                }

                var (ok, liveTotalCents, orderReference, err) = await browserCheckoutExecutor(targetUri.AbsoluteUri, itemName, packs).ConfigureAwait(false);
                if (ok && liveTotalCents > (int)Math.Round(expectedCents * (1.0 + MaxAllowedPriceDriftRatio)))
                {
                    await ReportTresorOutcomeAsync(
                        http,
                        safeBase,
                        bearerToken,
                        ticketId,
                        success: false,
                        liveGrossCents: liveTotalCents,
                        orderReference: null,
                        errorMessage: $"Sicherheits-Abbruch: Live-Shop-Preis ({liveTotalCents / 100.0:0.00} EUR) liegt mehr als 5% über Kalkulation ({expectedCents / 100.0:0.00} EUR).",
                        ct).ConfigureAwait(false);
                    processed++;
                    continue;
                }

                await ReportTresorOutcomeAsync(http, safeBase, bearerToken, ticketId, ok, liveTotalCents, orderReference, err, ct).ConfigureAwait(false);
                processed++;
            }

            return processed;
        }

        public static bool EnsureIsolatedSupplierTabOpened(string httpsUrl)
        {
            if (!Uri.TryCreate(httpsUrl, UriKind.Absolute, out var uri) || uri.Scheme != Uri.UriSchemeHttps)
                return false;

            string[] candidates =
            {
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), "Google", "Chrome", "Application", "chrome.exe"),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86), "Google", "Chrome", "Application", "chrome.exe"),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Google", "Chrome", "Application", "chrome.exe"),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86), "Microsoft", "Edge", "Application", "msedge.exe"),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), "Microsoft", "Edge", "Application", "msedge.exe")
            };

            string? exe = candidates.FirstOrDefault(File.Exists);
            if (string.IsNullOrWhiteSpace(exe))
                return false;

            Directory.CreateDirectory(IsolatedProfileDirectory);
            var psi = new System.Diagnostics.ProcessStartInfo
            {
                FileName = exe,
                UseShellExecute = false
            };
            psi.ArgumentList.Add($"--user-data-dir={IsolatedProfileDirectory}");
            psi.ArgumentList.Add($"--remote-debugging-port={IsolatedCdpPort}");
            psi.ArgumentList.Add("--remote-debugging-address=127.0.0.1");
            psi.ArgumentList.Add("--no-first-run");
            psi.ArgumentList.Add("--no-default-browser-check");
            psi.ArgumentList.Add(uri.AbsoluteUri);

            System.Diagnostics.Process.Start(psi);
            return true;
        }

        private static async Task ReportTresorOutcomeAsync(
            HttpClient http,
            string safeBase,
            string bearerToken,
            string ticketId,
            bool success,
            int? liveGrossCents,
            string? orderReference,
            string? errorMessage,
            CancellationToken ct)
        {
            string summary = success
                ? $"Isolierter Tresor-Kauf erfolgreich ausgeführt" + (!string.IsNullOrWhiteSpace(orderReference) ? $" (Bestellnummer: {orderReference})." : ".")
                : (errorMessage ?? "Tresor-Kauf konnte nicht abgeschlossen werden.");

            string body = JsonSerializer.Serialize(new
            {
                success,
                final_gross_cents = liveGrossCents,
                summary
            });

            using var req = new HttpRequestMessage(HttpMethod.Post, $"{safeBase}/ai/tresor-queue/{Uri.EscapeDataString(ticketId)}/complete")
            {
                Content = new StringContent(body, Encoding.UTF8, "application/json")
            };
            req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
            req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearerToken);
            using var resp = await http.SendAsync(req, ct).ConfigureAwait(false);
        }
    }
}
