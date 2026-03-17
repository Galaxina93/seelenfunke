<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Financial\FinanceCostItem;

try {
    $item = FinanceCostItem::orderBy('updated_at', 'desc')->first();
    echo "Item ID: " . $item->id . "\n";
    echo "Current amount in DB: " . $item->amount . " (type: " . gettype($item->amount) . ")\n";

    $data = [
        'name'               => $item->name,
        'amount'             => $item->amount,
        'interval_months'    => $item->interval_months,
        'first_payment_date' => $item->first_payment_date ? $item->first_payment_date->format('Y-m-d') : null,
        'last_payment_date'  => $item->last_payment_date ? $item->last_payment_date->format('Y-m-d') : null,
        'description'        => $item->description,
        'is_business'        => $item->is_business ? 1 : 0,
        'tax_rate'           => (int) $item->tax_rate,
    ];

    echo "Filling data...\n";
    $item->fill($data);
    $dirtyRaw = $item->getDirty();
    echo "Dirty raw keys: " . implode(", ", array_keys($dirtyRaw)) . "\n";
    
    foreach ($dirtyRaw as $k => $newVal) {
        $oldVal = $item->getOriginal($k);
        echo "$k changed from ".json_encode($oldVal)." to ".json_encode($newVal)."\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
