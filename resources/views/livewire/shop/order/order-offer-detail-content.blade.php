@php
    // DATEN NORMALISIERUNG
    $isOrder = $context === 'order';
    $isQuote = $context === 'quote';

    // ADRESSDATEN ZUSAMMENSTELLEN
    if ($isOrder) {
        $b = is_array($model->billing_address) ? $model->billing_address : [];
        $firstName = $b['first_name'] ?? '';
        $lastName = $b['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        $postal = $b['postal_code'] ?? '';
        $city = $b['city'] ?? '';
        $cityZip = trim($postal . ' ' . $city);

        $billing = [
            'name' => $fullName !== '' ? $fullName : ($model->customer_name ?? 'Gast'),
            'company' => $b['company'] ?? null,
            'address' => $b['address'] ?? '',
            'city_zip' => $cityZip,
            'country' => $b['country'] ?? 'DE',
            'email' => $model->email ?? ($b['email'] ?? '')
        ];
        $shipping = $model->shipping_address ?? null;
    } else {
        $billing = [
            'name' => trim(($model->first_name ?? '') . ' ' . ($model->last_name ?? '')),
            'company' => $model->company ?? null,
            'address' => trim(($model->street ?? '') . ' ' . ($model->house_number ?? '')),
            'city_zip' => trim(($model->postal ?? '') . ' ' . ($model->city ?? '')),
            'country' => $model->country ?? 'DE',
            'email' => $model->email ?? ''
        ];
        $shipping = null;
    }

    // TYPEN PRÜFUNG FÜR PREVIEW (Hier fehlte $isService)
    $isDigitalItem = isset($previewItem->configuration['is_digital']) && $previewItem->configuration['is_digital'];
    $isService = isset($previewItem->product->type) && $previewItem->product->type === 'service';
    $fingerprint = $previewItem->config_fingerprint ?? null;
@endphp

{{-- LINKE SPALTE: DETAILS --}}
@include('livewire.shop.order.order-offer-detail-partials.left')

{{-- RECHTE SPALTE: PREVIEW --}}
@include('livewire.shop.order.order-offer-detail-partials.right')
