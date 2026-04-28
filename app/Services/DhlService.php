<?php

namespace App\Services;

use App\Models\Order\OrderOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Str;

class DhlService
{
    protected string $apiKey;
    protected string $apiUser;
    protected string $apiSignature;
    protected string $ekp;
    protected bool $isSandbox;
    protected string $baseUrl;

    // Default sender details, fallback if shop settings are not available
    // MUST be a physically valid address in Germany for the Sandbox validation to pass
    protected array $senderDetails = [
        'name1'         => 'Seelenfunke',
        'addressStreet' => 'Alexanderplatz', // Must match Postal Code/City
        'addressHouse'  => '1',
        'postalCode'    => '10178', // Realistic Berlin Zip
        'city'          => 'Berlin',
        'country'       => 'DEU',
        'email'         => 'info@seelenfunke.de',
    ];

    public function __construct()
    {
        $this->apiKey       = config('services.dhl.api_key', env('DHL_API_KEY', ''));
        $this->apiUser      = config('services.dhl.api_user', env('DHL_API_USER', ''));
        $this->apiSignature = config('services.dhl.api_signature', env('DHL_API_SIGNATURE', ''));
        $this->ekp          = config('services.dhl.ekp', env('DHL_EKP', '2222222222'));
        $this->isSandbox    = config('services.dhl.sandbox', env('DHL_SANDBOX', true));

        $this->baseUrl = $this->isSandbox
            ? 'https://api-sandbox.dhl.com/parcel/de/shipping/v2'
            : 'https://api.dhl.com/parcel/de/shipping/v2';

        $this->initSenderFromSettings();
    }

    protected function initSenderFromSettings()
    {
        // Try to override sender details with dynamically configurable shop settings if they exist
        // And ensure they conform to DHL's strict max-length rules
        $name = shop_setting('company_name');
        if ($name) $this->senderDetails['name1'] = mb_substr(trim($name), 0, 50);

        $street = shop_setting('company_street');
        $streetNumber = shop_setting('company_street_number');
        $zip = shop_setting('company_zip');
        $city = shop_setting('company_city');

        // Um einen Hybriden ("Alexanderplatz 1, 80331 München") zu verhindern,
        // überschreiben wir die echten Adresse nur, wenn PLZ, Ort UND Straße in den Settings stehen.
        if ($street && $zip && $city) {
            $this->senderDetails['addressStreet'] = mb_substr(trim($street), 0, 50);
            $this->senderDetails['addressHouse']  = mb_substr(trim($streetNumber ?? '1'), 0, 10);
            $this->senderDetails['postalCode']    = mb_substr(trim($zip), 0, 10);
            $this->senderDetails['city']          = mb_substr(trim($city), 0, 50);
        }

        $email = shop_setting('company_email');
        if ($email) $this->senderDetails['email'] = mb_substr(trim($email), 0, 80);
    }

    /**
     * Generate one or multiple DHL shipping labels for the given Order.
     *
     * @param OrderOrder $order
     * @param int $packageCount Number of packages to ship
     * @param float $weightPerPackage Weight per package in kg
     * @return array Contains list of generated 'tracking_number' and 'label_path'
     * @throws Exception
     */
    public function createLabels(OrderOrder $order, int $packageCount = 1, float $weightPerPackage = 1.0): array
    {
        // 1. Prüfen ob die alternative Lieferadresse *wirklich* Daten enthält
        $hasShipping = !empty($order->shipping_address)
            && (isset($order->shipping_address['street']) || isset($order->shipping_address['first_name']))
            && trim(($order->shipping_address['street'] ?? '') . ($order->shipping_address['first_name'] ?? '')) !== '';

        $shipping = $hasShipping ? $order->shipping_address : $order->billing_address;

        if (!$shipping) {
            throw new Exception("Die Bestellung hat keine gültige Liefer- oder Rechnungsadresse.");
        }

        $isInternational = strtoupper($shipping['country'] ?? 'DE') !== 'DE';

        // National (V01PAK) uses participation code 0101
        // International (V53WPAK) uses participation code 5301
        $productCode   = $isInternational ? 'V53WPAK' : 'V01PAK';
        $participation = $isInternational ? '5301' : '0101';
        $billingNumber = $this->ekp . $participation;

        // Extract street and house number. Often shops combine them into 'address'.
        $streetFullName = $shipping['address'] ?? ($shipping['street'] ?? '');
        preg_match('/^([^\d]+)\s*(.+)?$/', $streetFullName, $matches);

        // Strip trailing commas from the street name which break DHL validation
        $streetName   = rtrim(trim($matches[1] ?? $streetFullName), ',');
        $streetNumber = trim($matches[2] ?? '');

        // If for any reason streetName is empty after trim, fallback to original to prevent DHL error
        if (empty($streetName)) {
            $streetName = $streetFullName ?: 'Unbekannt';
        }

        // Herausfinden, wie viele Labels bereits für diese Order existieren,
        // damit es bei Nachgenerierungen keine "refNo" Kollision gibt!
        $existingLabelsCount = $order->shipments()->count();

        // We can send multiple shipments in one request, but for simplicity
        // of tracking each individually and handling errors, we can either
        // batch them here or do one by one. DHL API v2 supports batching via 'shipments' array.
        // Let's create an array of shipments:
        $shipmentsArr = [];
        for ($i = 0; $i < $packageCount; $i++) {
            $shipmentsArr[] = [
                'product' => $productCode,
                'billingNumber' => $billingNumber,
                'refNo' => 'Order-' . $order->order_number . '-' . ($i + 1 + $existingLabelsCount),
                'shipper' => $this->senderDetails,
                'consignee' => [
                    'name1' => mb_substr(trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')), 0, 50),
                    'addressStreet' => mb_substr(trim($streetName), 0, 50),
                    'addressHouse' => mb_substr(trim($streetNumber), 0, 10),
                    'postalCode' => mb_substr(trim($shipping['postal_code'] ?? ($shipping['zip'] ?? '')), 0, 10),
                    'city' => mb_substr(trim($shipping['city'] ?? ''), 0, 50),
                    'country' => $this->mapCountryCode($shipping['country'] ?? 'DE'),
                    'email' => mb_substr(trim($order->email ?? 'info@seelenfunke.de'), 0, 80),
                ],
                'details' => [
                    'weight' => [
                        'uom' => 'kg',
                        'value' => $weightPerPackage
                    ]
                ]
            ];
        }

        // Construct the strictly required DHL payload
        $payload = [
            'profile' => 'STANDARD_GRUPPENPROFIL',
            'shipments' => $shipmentsArr
        ];

        // Log payload for debugging purposes
        /*\Illuminate\Support\Facades\Log::info('DHL API Payload: ', $payload);*/

        // Send POST request
        $response = Http::withBasicAuth($this->apiUser, $this->apiSignature)
            ->withHeaders([
                'dhl-api-key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post($this->baseUrl . '/orders?printFormat=910-300-410', $payload);

        return $this->handleResponse($response, $order);
    }

    /**
     * Map Alpha-2 country codes to Alpha-3 as required by DHL
     */
    protected function mapCountryCode(string $code): string
    {
        $map = [
            'DE' => 'DEU',
            'AT' => 'AUT',
            'CH' => 'CHE',
            'FR' => 'FRA',
            'IT' => 'ITA',
            'ES' => 'ESP',
            'NL' => 'NLD',
            // Add more as needed, defaulting to DEU if empty
        ];

        return $map[strtoupper($code)] ?? 'DEU';
    }

    protected function handleResponse($response, OrderOrder $order): array
    {
        $data = $response->json();

        if ($response->failed() || !isset($data['items']) || empty($data['items'])) {
            $errorMessage = "Unbekannter Fehler von DHL.";

            // DHL V2 errors are sometimes nested
            if (isset($data['detail'])) {
                $errorMessage = $data['detail'];
            }
            if (isset($data['items'][0]['sstatus']['details'][0]['message'])) {
                $errorMessage = $data['items'][0]['sstatus']['details'][0]['message'];
            }
            if (isset($data['items'][0]['validationMessages'][0]['validationMessage'])) {
                $errorMessage = $data['items'][0]['validationMessages'][0]['validationMessage'];
            }

            throw new Exception("DHL API Fehler: " . $errorMessage);
        }

        $results = [];
        $hasErrors = false;
        $errorDetails = [];

        foreach ($data['items'] as $index => $item) {
            if (isset($item['sstatus']['title']) && strtolower($item['sstatus']['title']) !== 'ok') {
                $hasErrors = true;
                $validationDetails = '';
                if (isset($item['validationMessages'])) {
                    foreach ($item['validationMessages'] as $msg) {
                        $validationDetails .= ' ' . ($msg['validationMessage'] ?? '');
                    }
                }

                $detail = $item['sstatus']['detail'] ?? null;
                if (!$detail) {
                    $detail = json_encode($item['sstatus']);
                }
                $errorDetails[] = "Paket " . ($index + 1) . ": " . $detail . $validationDetails;
                continue; // Skip this one, proceed to see others or fail
            }

            $trackingNumber = $item['shipmentNo'];
            $labelBase64 = $item['label']['b64'];

            // Save PDF to storage
            $safeOrderNo = Str::slug($order->order_number);
            $fileName = "bestellungen/dhl_labels/label_{$safeOrderNo}_{$trackingNumber}.pdf";
            Storage::disk('public')->put($fileName, base64_decode($labelBase64));

            // Create Shipments Record
            \App\Models\Order\OrderShipment::create([
                'order_id' => $order->id,
                'tracking_number' => $trackingNumber,
                'shipping_label_path' => $fileName,
                'carrier' => 'DHL',
                'status' => 'shipped',
            ]);

            $results[] = [
                'tracking_number' => $trackingNumber,
                'label_path' => $fileName,
            ];
        }

        if ($hasErrors) {
            throw new Exception("DHL Label Error: " . implode(" | ", $errorDetails));
        }

        return $results;
    }
}
