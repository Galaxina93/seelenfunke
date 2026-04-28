<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderShipment;
use Illuminate\Support\Facades\Log;

class CheckDhlDeliveryStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl:check-delivery-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the delivery status of shipped orders and updates their status to completed when all packages are delivered.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting DHL Delivery Status Check...');

        // Find all orders that are currently marked as shipped
        $orders = OrderOrder::with('shipments')->where('status', 'shipped')->get();

        if ($orders->isEmpty()) {
            $this->info('No shipped orders found.');
            return;
        }

        foreach ($orders as $order) {
            $this->info("Checking Order #{$order->order_number}");
            $allDelivered = true;

            foreach ($order->shipments as $shipment) {
                // If the shipment is already marked delivered, skip API call
                if ($shipment->status === 'delivered') {
                    continue;
                }

                $this->line(" - Tracking {$shipment->tracking_number}...");
                
                try {
                    // TODO: Replace this with the actual DHL Tracking API call
                    $isDelivered = $this->checkDhlTracking($shipment->tracking_number);

                    if ($isDelivered) {
                        $shipment->update(['status' => 'delivered']);
                        $this->line("   -> Delivered!");
                    } else {
                        $allDelivered = false;
                        $this->line("   -> Still in transit.");
                    }
                } catch (\Exception $e) {
                    $allDelivered = false;
                    $this->error("   -> Error checking tracking: " . $e->getMessage());
                    Log::error("DHL Tracking Error for shipment {$shipment->tracking_number}: " . $e->getMessage());
                }
            }

            // If all packages for this order are delivered, set order to completed
            if ($allDelivered && $order->shipments->isNotEmpty()) {
                $order->update(['status' => 'completed']);
                $this->info("Order #{$order->order_number} marked as COMPLETED.");
            }
        }

        $this->info('Finished checking delivery statuses.');
    }

    /**
     * Call the DHL Tracking API to check delivery status.
     * @param string $trackingNumber
     * @return bool
     * @throws \Exception
     */
    private function checkDhlTracking(string $trackingNumber): bool
    {
        $apiKey = config('services.dhl.api_key', env('DHL_API_KEY', ''));
        if (empty($apiKey)) {
            $this->error('   -> FEHLER: Kein DHL API Key hinterlegt. (DHL_API_KEY)');
            return false;
        }

        // Die Parcel DE Tracking API via api-eu.dhl.com
        $url = 'https://api-eu.dhl.com/track/shipments';

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'dhl-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->timeout(15)->get($url, [
            'trackingNumber' => $trackingNumber,
            'language' => 'de'
        ]);

        if ($response->failed()) {
            if ($response->status() === 404) {
                // Tracking-Nummer existiert (noch) nicht im System oder ist zu neu
                $this->line("   -> Sendung bei DHL noch nicht bekannt (404).");
                return false;
            }
            if ($response->status() === 401) {
                throw new \Exception("Unauthorized (401). Der hinterlegte DHL_API_KEY hat keine Berechtigung für die 'Parcel DE Tracking' API.");
            }
            if ($response->status() === 429) {
                throw new \Exception("Rate Limit überschritten (429).");
            }
            
            throw new \Exception('API Fehler (' . $response->status() . '): ' . $response->body());
        }

        $data = $response->json();
        
        if (isset($data['shipments'][0]['status']['statusCode'])) {
            $statusCode = strtolower($data['shipments'][0]['status']['statusCode']);
            
            // DHL Status Code 'delivered' steht i.d.R. für zugestellt.
            if ($statusCode === 'delivered') {
                return true;
            }
            
            $this->line("   -> DHL Status: " . ($data['shipments'][0]['status']['status'] ?? $statusCode));
        }

        return false;
    }
}
