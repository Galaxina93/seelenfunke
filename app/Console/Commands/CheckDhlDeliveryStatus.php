<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderShipment;
use App\Models\System\SystemLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckDhlDeliveryStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl:check-delivery-status 
                            {--auto-complete-days=7 : Tage nach Versand für automatisches Abschließen (Fallback)}
                            {--force : Alle offenen versendeten Bestellungen sofort als zugestellt markieren}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prüft den Zustellstatus versendeter Bestellungen über die DHL Tracking API und schließt überfällige Sendungen per Auto-Complete Fallback ab.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting DHL Delivery Status Check...');

        $autoCompleteDays = (int) $this->option('auto-complete-days');
        if ($autoCompleteDays <= 0) {
            $autoCompleteDays = 7;
        }
        $force = (bool) $this->option('force');

        // Finde alle Bestellungen im Status "shipped"
        $orders = OrderOrder::with('shipments')->where('status', 'shipped')->get();

        if ($orders->isEmpty()) {
            $this->info('Keine offenen versendeten Bestellungen gefunden.');
            return 0;
        }

        $completedCount = 0;
        $checkedShipments = 0;

        foreach ($orders as $order) {
            $this->info("Prüfe Bestellung #{$order->order_number}...");
            $allDelivered = true;
            $hasShipments = $order->shipments->isNotEmpty();

            // Falls keine Shipments vorhanden sind, aber Bestellung seit X Tagen auf shipped steht
            if (!$hasShipments) {
                $daysInTransit = $order->updated_at ? (int) $order->updated_at->diffInDays(now()) : 0;
                if ($force || $daysInTransit >= $autoCompleteDays) {
                    $order->update(['status' => 'completed']);
                    $completedCount++;
                    $this->line("   -> Keine Sendungsnummer hinterlegt, aber seit {$daysInTransit} Tagen unterwegs. Automatisch auf ZUGESTELLT gesetzt.");
                    $this->logSystemCompletion($order, "Auto-Complete Fallback (keine Sendungsnummer, seit {$daysInTransit} Tagen unterwegs)");
                }
                continue;
            }

            foreach ($order->shipments as $shipment) {
                $checkedShipments++;

                if ($shipment->status === 'delivered') {
                    continue;
                }

                $this->line(" - Tracking {$shipment->tracking_number}...");

                if ($force) {
                    $shipment->update(['status' => 'delivered']);
                    $this->line("   -> [FORCE] Als zugestellt markiert.");
                    continue;
                }

                $isDelivered = false;
                $apiFailed = false;
                $trackingNumber = trim((string) ($shipment->tracking_number ?? ''));
                $dhlApiKey = trim((string) (config('services.dhl.tracking_api_key') ?: config('services.dhl.api_key') ?: ''));
                $dhlConfigured = $dhlApiKey !== '';

                if ($trackingNumber !== '' && $dhlConfigured) {
                    try {
                        $isDelivered = $this->checkDhlTracking($trackingNumber);
                        \App\Services\System\SystemAlertTicketService::autoResolveByAlertPrefix(
                            'dhl:tracking_auth_error',
                            'DHL Parcel DE Tracking API antwortet wieder mit gültiger Authentifizierung.',
                            'Bestelli'
                        );
                    } catch (\Exception $e) {
                        $apiFailed = true;
                        $this->warn("   -> DHL Tracking Info: " . $e->getMessage());
                        Log::warning("DHL Tracking Warning für Sendung {$trackingNumber}: " . $e->getMessage());
                        if (str_contains($e->getMessage(), 'Unauthorized (401)')) {
                            \App\Services\System\SystemAlertTicketService::upsertSystemTicket(
                                alertKey: 'dhl:tracking_auth_error',
                                title: 'DHL Tracking API: Schlüssel ungültig oder ohne Berechtigung (HTTP 401)',
                                markdownBody: "Der Cronjob `dhl:check-delivery-status` erhielt HTTP 401 von `api-eu.dhl.com/track/shipments`.\n\n**Details:**\n" . $e->getMessage(),
                                departmentName: 'Bestellungen',
                                agentName: 'Bestelli',
                                ticketType: 'logistics_alert',
                                priority: 'normal',
                                assignToAntigravity: false
                            );
                        }
                    }
                } elseif ($trackingNumber === '') {
                    $this->line("   -> Keine gültige Sendungsnummer hinterlegt. Nutze Alters-Fallback.");
                }

                if ($isDelivered) {
                    $shipment->update(['status' => 'delivered']);
                    $this->line("   -> Per DHL Tracking als ZUGESTELLT bestätigt! ✓");
                } elseif (!$dhlConfigured || $trackingNumber === '' || $apiFailed) {
                    // Auto-Complete Fallback: Wenn kein DHL Tracking konfiguriert ist (oder keine Sendungsnummer vorhanden) und Sendung seit >= X Tagen unterwegs ist
                    $shipmentAge = $shipment->created_at ?? $order->updated_at ?? now();
                    $daysSinceShipment = (int) $shipmentAge->diffInDays(now());

                    if ($daysSinceShipment >= $autoCompleteDays) {
                        $shipment->update(['status' => 'delivered']);
                        $this->line("   -> Auto-Complete Fallback: Sendung seit {$daysSinceShipment} Tagen unterwegs (>= {$autoCompleteDays} Tage). Als zugestellt markiert.");
                    } else {
                        $allDelivered = false;
                        $this->line("   -> Weiterhin in Zustellung ({$daysSinceShipment}/{$autoCompleteDays} Tage bis Fallback).");
                    }
                } else {
                    $allDelivered = false;
                    $this->line("   -> Laut DHL Tracking weiterhin in Zustellung.");
                }
            }

            // Wenn alle Pakete der Bestellung zugestellt sind
            if ($allDelivered && $hasShipments) {
                $order->update(['status' => 'completed']);
                $completedCount++;
                $this->info("Bestellung #{$order->order_number} wurde auf ZUGESTELLT (completed) gesetzt! 🎉");
                $this->logSystemCompletion($order, "DHL Statusüberprüfung / Auto-Complete erfolgreich");
            }
        }

        $this->info("Fertig! {$completedCount} Bestellung(en) abgeschlossen ({$checkedShipments} Sendung(en) geprüft).");
        return 0;
    }

    /**
     * Call the DHL Tracking API to check delivery status.
     *
     * @param string $trackingNumber
     * @return bool
     * @throws \Exception
     */
    private function checkDhlTracking(string $trackingNumber): bool
    {
        $apiKey = trim((string) (config('services.dhl.tracking_api_key') ?: config('services.dhl.api_key') ?: ''));
        if ($apiKey === '') {
            throw new \Exception('Kein DHL API Key hinterlegt.');
        }

        // Die Parcel DE Tracking API via api-eu.dhl.com
        $url = 'https://api-eu.dhl.com/track/shipments';

        $response = Http::withHeaders([
            'dhl-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->timeout(10)->get($url, [
            'trackingNumber' => $trackingNumber,
            'language' => 'de'
        ]);

        if ($response->failed()) {
            if ($response->status() === 404) {
                $this->line("   -> Sendung bei DHL noch nicht im Tracking bekannt (404).");
                return false;
            }
            if ($response->status() === 401) {
                throw new \Exception("Unauthorized (401). Der hinterlegte API-Schlüssel hat keine Berechtigung für 'Parcel DE Tracking'.");
            }
            if ($response->status() === 429) {
                throw new \Exception("Rate Limit überschritten (429).");
            }

            $safeExcerpt = class_exists(\App\Services\System\SystemErrorLogger::class)
                ? \App\Services\System\SystemErrorLogger::sanitizeSensitiveText(mb_substr((string) $response->body(), 0, 240))
                : mb_substr((string) $response->body(), 0, 240);
            throw new \Exception('API Fehler (' . $response->status() . '): ' . $safeExcerpt);
        }

        $data = $response->json();
        
        if (isset($data['shipments'][0]['status']['statusCode'])) {
            $statusCode = strtolower($data['shipments'][0]['status']['statusCode']);
            
            // DHL Status Code 'delivered' steht für erfolgreich zugestellt
            if (in_array($statusCode, ['delivered', 'zugestellt'])) {
                return true;
            }
            
            $this->line("   -> DHL Status: " . ($data['shipments'][0]['status']['status'] ?? $statusCode));
        }

        return false;
    }

    /**
     * Log system completion event.
     */
    private function logSystemCompletion(OrderOrder $order, string $reason): void
    {
        try {
            if (class_exists(SystemLog::class)) {
                SystemLog::create([
                    'type' => 'shop',
                    'action_id' => 'order:delivery_completed',
                    'title' => "Bestellung #{$order->order_number} zugestellt",
                    'message' => "Bestellung wurde als zugestellt (completed) markiert. Grund: {$reason}",
                    'status' => 'success',
                    'payload' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'reason' => $reason,
                    ],
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Ignorieren falls Log-Tabelle blockiert
        }
    }
}
