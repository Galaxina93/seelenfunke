<?php
ob_implicit_flush(1);
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$adminId = 1;
Auth::guard('admin')->loginUsingId($adminId);

$plan = new App\Livewire\Shop\Financial\FinancialLiquidityPlanner;
$plan->mount();

echo "\nData 2026-06 IN:\n";
print_r($plan->data[2026][6]['in'] ?? []);
echo "\nData 2026-06 OUT:\n";
print_r($plan->data[2026][6]['out'] ?? []);
echo "\nData 2026-08 IN:\n";
print_r($plan->data[2026][8]['in'] ?? []);
echo "\nData 2026-08 OUT:\n";
print_r($plan->data[2026][8]['out'] ?? []);

echo "\nTotals 2026-06:\n";
print_r($plan->totals[2026][6] ?? []);
echo "\nTotals 2026-08:\n";
print_r($plan->totals[2026][8] ?? []);

echo "\nCost Items:\n";
$items = App\Models\Financial\FinanceCostItem::all();
foreach($items as $i) { echo $i->name . " | isBiz: " . $i->is_business . " | " . $i->first_payment_date . " to " . $i->last_payment_date . " | amt: " . $i->amount . "\n"; }
