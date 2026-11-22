<?php

namespace App\Services\Shipping;

use App\Models\Order\OrderOrder;
use App\Models\Order\OrderShipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GrossbriefLabelService
{
    public const CARRIER_CODE = 'deutsche_post_brief';

    /**
     * Bereinigt optionalen Mobile-Briefmarken-Code (#PORTO + 8 Zeichen).
     */
    public function normalizePortoCode(?string $rawCode): ?string
    {
        $clean = strtoupper(trim((string) $rawCode));
        $clean = preg_replace('/^#?PORTO[\s:\-]*/i', '', $clean) ?? '';
        $clean = preg_replace('/[^A-Z0-9]/', '', $clean) ?? '';

        return $clean !== '' ? mb_substr($clean, 0, 16) : null;
    }

    /**
     * Erzeugt ein 4x6 Zoll (288x432 pt) Großbrief-Adressetikett (PDF) und speichert die Sendung.
     */
    public function createLabel(
        OrderOrder $order,
        ?string $portoCode = null,
        ?string $externalTrackingNumber = null
    ): OrderShipment {
        $order->loadMissing('items.product');

        if ($order->isOnlyDigital()) {
            throw new \RuntimeException('Für rein digitale Bestellungen ist kein Großbrief-Label erforderlich.');
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            throw new \RuntimeException('Für stornierte oder erstattete Bestellungen können keine Versandlabels erstellt werden.');
        }

        $address = $this->resolveRecipientAddress($order);
        $sender = $this->resolveSenderAddress();
        $normalizedPorto = $this->normalizePortoCode($portoCode);
        $cleanTracking = preg_replace('/[^A-Za-z0-9\-]/', '', trim((string) $externalTrackingNumber)) ?? '';

        $boxInfo = $order->resolveRecommendedShippingBox();
        $packagingLabel = $boxInfo['box_name'] ?: 'Vollpapp-Versandtasche DIN A5';
        if ($boxInfo['box_dimensions'] !== '') {
            $packagingLabel .= ' (' . $boxInfo['box_dimensions'] . ')';
        }

        $pdf = Pdf::loadView('global.pdf.grossbrief_label', [
            'orderNumber' => (string) $order->order_number,
            'sender' => $sender,
            'recipient' => $address,
            'portoCode' => $normalizedPorto,
            'trackingNumber' => $cleanTracking !== '' ? $cleanTracking : null,
            'packagingLabel' => $packagingLabel,
        ])
            ->setPaper([0, 0, 288, 432], 'portrait')
            ->setOption('isRemoteEnabled', false);

        $safeOrderNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $order->order_number);
        $relativePath = "bestellungen/brief_labels/brief_{$safeOrderNo}.pdf";
        Storage::disk('local')->put($relativePath, $pdf->output());

        $storedTracking = $cleanTracking !== ''
            ? $cleanTracking
            : ($normalizedPorto ? ('#PORTO-' . $normalizedPorto) : ('BRIEF-' . $safeOrderNo));

        $existingShipment = $order->shipments()
            ->where('carrier', self::CARRIER_CODE)
            ->first();

        if ($existingShipment) {
            $existingShipment->update([
                'tracking_number' => $storedTracking,
                'shipping_label_path' => $relativePath,
                'status' => 'shipped',
            ]);

            return $existingShipment->fresh();
        }

        return OrderShipment::create([
            'order_id' => $order->id,
            'tracking_number' => $storedTracking,
            'shipping_label_path' => $relativePath,
            'carrier' => self::CARRIER_CODE,
            'status' => 'shipped',
        ]);
    }

    private function resolveRecipientAddress(OrderOrder $order): array
    {
        $shipping = $order->shipping_address;
        $hasShipping = is_array($shipping)
            && trim((string) (($shipping['street'] ?? $shipping['address'] ?? '') . ($shipping['first_name'] ?? ''))) !== '';

        $addr = $hasShipping ? $shipping : ($order->billing_address ?? []);
        $name = trim((string) (($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')));
        if ($name === '') {
            $name = (string) ($order->customer_name ?: 'Kunde');
        }

        $street = trim((string) (($addr['street'] ?? $addr['address'] ?? '') . ' ' . ($addr['house_number'] ?? '')));
        $postalCode = trim((string) ($addr['postal_code'] ?? $addr['zip'] ?? ''));
        $city = trim((string) ($addr['city'] ?? ''));
        $country = strtoupper(trim((string) ($addr['country'] ?? 'DE')));

        if ($street === '' || $postalCode === '' || $city === '') {
            throw new \RuntimeException('Unvollständige Liefer- oder Rechnungsadresse für das Großbrief-Label.');
        }

        return [
            'name' => $name,
            'company' => trim((string) ($addr['company'] ?? '')),
            'street' => $street,
            'postal_code' => $postalCode,
            'city' => $city,
            'country' => $country,
            'country_label' => $country !== 'DE' ? $country : '',
        ];
    }

    private function resolveSenderAddress(): array
    {
        return [
            'name' => shop_setting('company_name') ?: 'Mein-Seelenfunke · Alina Steinhauer',
            'street' => trim((shop_setting('company_street') ?: 'Carl-Goerdeler-Ring') . ' ' . (shop_setting('company_street_number') ?: '26')),
            'postal_code' => shop_setting('company_zip') ?: '38518',
            'city' => shop_setting('company_city') ?: 'Gifhorn',
        ];
    }
}
