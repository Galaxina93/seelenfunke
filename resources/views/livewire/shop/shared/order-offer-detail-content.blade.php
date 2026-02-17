@php
    // DATEN NORMALISIERUNG
    $isOrder = $context === 'order';
    $isQuote = $context === 'quote';

    // ADRESSDATEN ZUSAMMENSTELLEN
    if ($isOrder) {
        $billing = [
            'name' => $model->billing_address['first_name'] . ' ' . $model->billing_address['last_name'],
            'company' => $model->billing_address['company'] ?? null,
            'address' => $model->billing_address['address'],
            'city_zip' => $model->billing_address['postal_code'] . ' ' . $model->billing_address['city'],
            'country' => $model->billing_address['country'],
            'email' => $model->email
        ];
        $shipping = $model->shipping_address ?? null;
    } else {
        $billing = [
            'name' => $model->first_name . ' ' . $model->last_name,
            'company' => $model->company ?? null,
            'address' => trim(($model->street ?? '') . ' ' . ($model->house_number ?? '')),
            'city_zip' => ($model->postal ?? '') . ' ' . ($model->city ?? ''),
            'country' => $model->country ?? 'DE',
            'email' => $model->email
        ];
        $shipping = null;
    }

    // TYPEN PRÜFUNG FÜR PREVIEW (Hier fehlte $isService)
    $isDigitalItem = isset($previewItem->configuration['is_digital']) && $previewItem->configuration['is_digital'];
    $isService = isset($previewItem->product->type) && $previewItem->product->type === 'service';
    $fingerprint = $previewItem->config_fingerprint ?? null;
@endphp

{{-- LINKE SPALTE: DETAILS --}}
@include('livewire.shop.shared.partials.left')

{{-- RECHTE SPALTE: PREVIEW --}}
@include('livewire.shop.shared.partials.right')
