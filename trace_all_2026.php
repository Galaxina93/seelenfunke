<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\Admin\Admin::first();
Auth::guard('admin')->loginUsingId($admin->id);

$c = new App\Livewire\Shop\Financial\FinancialLiquidityPlanner();
$c->mount();

echo str_pad("M", 3) . "| " . str_pad("IN", 8) . "| " . str_pad("OUT", 8) . "| " . str_pad("NET", 8) . "| " . str_pad("END", 8) . "\n";
echo str_repeat("-", 45) . "\n";
for($i=1; $i<=12; $i++) {
    $t = $c->totals[2026][$i];
    echo str_pad($i, 3) . "| " . 
         str_pad($t['in'], 8) . "| " . 
         str_pad($t['out'], 8) . "| " . 
         str_pad($t['net'], 8) . "| " . 
         str_pad($t['end'], 8) . "\n";
}
