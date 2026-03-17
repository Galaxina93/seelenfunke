<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
Auth::guard('admin')->loginUsingId(1);
$c = new App\Livewire\Shop\Financial\FinancialLiquidityPlanner();
$c->mount();

echo str_pad("Month", 6) . "| " . str_pad("IN", 8) . "| " . str_pad("OUT", 8) . "| " . str_pad("ADJ", 8) . "| " . str_pad("NET", 8) . "| " . str_pad("END", 8) . "\n";
echo str_repeat("-", 55) . "\n";
for($i=1; $i<=12; $i++) {
    $t = $c->totals[2026][$i];
    echo str_pad($i, 6) . "| " . 
         str_pad($t['in'], 8) . "| " . 
         str_pad($t['out'], 8) . "| " . 
         str_pad($t['adj'], 8) . "| " . 
         str_pad($t['net'], 8) . "| " . 
         str_pad($t['end'], 8) . "\n";
}

echo "\n--- RAW DB DATA GROUPS ---\n";
$groups = App\Models\Financial\FinanceGroup::with('items')->where('admin_id', 1)->get();
foreach($groups as $g) {
    echo "Group: {$g->name}\n";
    foreach($g->items as $i) {
        echo "  - {$i->name} | amt: {$i->amount} | is_biz: {$i->is_business} | start: {$i->first_payment_date}\n";
    }
}
