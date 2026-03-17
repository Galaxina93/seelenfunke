<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceCostItemHistory;

try {
    $item = FinanceCostItem::orderBy('updated_at', 'desc')->first();
    $history = FinanceCostItemHistory::where('finance_cost_item_id', $item->id)->whereNotNull('snapshot')->orderBy('created_at', 'desc')->first();
    
    if (!$history) {
        die("No history found with a snapshot\n");
    }

    $originalSnapshot = $history->snapshot;

    // Simulate putting `$originalSnapshot` into the Livewire component fields
    $itemAmount = $originalSnapshot['amount'] ?? null;
    $itemInterval = $originalSnapshot['interval_months'] ?? 1;
    $itemName = $originalSnapshot['name'] ?? null;
    $itemDescription = $originalSnapshot['description'] ?? null;
    $itemIsBusiness = $originalSnapshot['is_business'] ?? false;
    $itemTaxRate = $originalSnapshot['tax_rate'] ?? 0;
    
    $itemDate = isset($originalSnapshot['first_payment_date']) ? substr($originalSnapshot['first_payment_date'], 0, 10) : null;
    $itemLastPaymentDate = isset($originalSnapshot['last_payment_date']) ? substr($originalSnapshot['last_payment_date'], 0, 10) : null;

    // Simulate saveItem() formatting
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

    $item->fill($data);

    // Run the literal logic from the controller
    $amountChanged = abs((float)($originalSnapshot['amount'] ?? 0) - (float)$item->amount) > 0.001;
    $intervalChanged = (int)($originalSnapshot['interval_months'] ?? 1) !== (int)$item->interval_months;
    $nameChanged = ($originalSnapshot['name'] ?? '') !== ($item->name ?? '');
    $descChanged = ($originalSnapshot['description'] ?? '') !== ($item->description ?? '');
    $businessChanged = (bool)($originalSnapshot['is_business'] ?? false) !== (bool)$item->is_business;
    $taxChanged = (int)($originalSnapshot['tax_rate'] ?? 0) !== (int)$item->tax_rate;
    
    $oldFirstDate = isset($originalSnapshot['first_payment_date']) ? substr($originalSnapshot['first_payment_date'], 0, 10) : null;
    $newFirstDate = $item->first_payment_date ? (is_string($item->first_payment_date) ? substr($item->first_payment_date, 0, 10) : $item->first_payment_date->format('Y-m-d')) : null;
    $firstDateChanged = $oldFirstDate !== $newFirstDate;

    $oldLastDate = isset($originalSnapshot['last_payment_date']) ? substr($originalSnapshot['last_payment_date'], 0, 10) : null;
    $newLastDate = $item->last_payment_date ? (is_string($item->last_payment_date) ? substr($item->last_payment_date, 0, 10) : $item->last_payment_date->format('Y-m-d')) : null;
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
