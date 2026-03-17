<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Financial\FinanceCostItem;

try {
    $item = FinanceCostItem::first();
    echo "Item testing: " . $item->name . "\n";

    // Simulate Livewire state immediately after loading:
    $itemName = $item->name;
    $itemAmount = $item->amount; // e.g. "1806.30"
    $itemInterval = $item->interval_months;
    $itemDate = $item->first_payment_date ? $item->first_payment_date->format('Y-m-d') : null;
    $itemLastPaymentDate = $item->last_payment_date ? $item->last_payment_date->format('Y-m-d') : null;
    $itemDescription = $item->description;
    $itemIsBusiness = (bool) $item->is_business;
    $itemTaxRate = $item->tax_rate ?? 0;

    // saveItem() logic:
    if ($itemAmount) {
        $itemAmount = str_replace(',', '.', $itemAmount);
    }
    
    $data = [
        'name'               => $itemName,
        'amount'             => $itemAmount,
        'interval_months'    => $itemInterval,
        'first_payment_date' => $itemDate,
        'last_payment_date'  => $itemLastPaymentDate ?: null,
        'description'        => $itemDescription,
        'is_business'        => $itemIsBusiness ? 1 : 0,
        'tax_rate'           => (int) $itemTaxRate,
    ];

    $originalSnapshot = $item->toArray();
    $item->fill($data);

    $amountChanged = abs((float)($originalSnapshot['amount'] ?? 0) - (float)$item->amount) > 0.001;
    $intervalChanged = (int)($originalSnapshot['interval_months'] ?? 1) !== (int)$item->interval_months;
    $nameChanged = ($originalSnapshot['name'] ?? '') !== ($item->name ?? '');
    $descChanged = ($originalSnapshot['description'] ?? '') !== ($item->description ?? '');
    $businessChanged = (bool)($originalSnapshot['is_business'] ?? false) !== (bool)$item->is_business;
    $taxChanged = (int)($originalSnapshot['tax_rate'] ?? 0) !== (int)$item->tax_rate;
    
    $oldFirstDate = isset($originalSnapshot['first_payment_date']) ? substr($originalSnapshot['first_payment_date'], 0, 10) : null;
    // $item->first_payment_date might be a string now since we filled it with string $itemDate
    $newFirstDate = $item->first_payment_date instanceof \DateTimeInterface 
        ? $item->first_payment_date->format('Y-m-d') 
        : (is_string($item->first_payment_date) ? substr($item->first_payment_date, 0, 10) : null);
    $firstDateChanged = $oldFirstDate !== $newFirstDate;

    $oldLastDate = isset($originalSnapshot['last_payment_date']) ? substr($originalSnapshot['last_payment_date'], 0, 10) : null;
    $newLastDate = $item->last_payment_date instanceof \DateTimeInterface 
        ? $item->last_payment_date->format('Y-m-d') 
        : (is_string($item->last_payment_date) ? substr($item->last_payment_date, 0, 10) : null);
    $lastDateChanged = $oldLastDate !== $newLastDate;

    echo "amountChanged: " . ($amountChanged ? 'Yes' : 'No') . "\n";
    echo "intervalChanged: " . ($intervalChanged ? 'Yes' : 'No') . "\n";
    echo "nameChanged: " . ($nameChanged ? 'Yes' : 'No') . "\n";
    echo "descChanged: " . ($descChanged ? 'Yes' : 'No') . " (old: ".json_encode($originalSnapshot['description'] ?? '').", new: ".json_encode($item->description ?? '').")\n";
    echo "businessChanged: " . ($businessChanged ? 'Yes' : 'No') . "\n";
    echo "taxChanged: " . ($taxChanged ? 'Yes' : 'No') . "\n";
    echo "firstDateChanged: " . ($firstDateChanged ? 'Yes' : 'No') . " (old: ".json_encode($oldFirstDate).", new: ".json_encode($newFirstDate).")\n";
    echo "lastDateChanged: " . ($lastDateChanged ? 'Yes' : 'No') . " (old: ".json_encode($oldLastDate).", new: ".json_encode($newLastDate).")\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
