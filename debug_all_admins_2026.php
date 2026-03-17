<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admins = App\Models\Admin\Admin::all();
foreach($admins as $admin) {
    echo "====================================\n";
    echo "ADMIN ID: {$admin->id} ({$admin->email})\n";
    Auth::guard('admin')->loginUsingId($admin->id);
    $c = new App\Livewire\Shop\Financial\FinancialLiquidityPlanner();
    $c->mount();

    echo str_pad("M", 3) . "| " . str_pad("IN", 8) . "| " . str_pad("OUT", 8) . "| " . str_pad("ADJ", 8) . "| " . str_pad("NET", 8) . "| " . str_pad("END", 8) . "\n";
    echo str_repeat("-", 50) . "\n";
    for($i=1; $i<=8; $i++) {
        $t = $c->totals[2026][$i];
        echo str_pad($i, 3) . "| " . 
             str_pad($t['in'], 8) . "| " . 
             str_pad($t['out'], 8) . "| " . 
             str_pad($t['adj'], 8) . "| " . 
             str_pad($t['net'], 8) . "| " . 
             str_pad($t['end'], 8) . "\n";
    }

    echo "\nRAW DATA GROUPS:\n";
    $groups = App\Models\Financial\FinanceGroup::with('items')->where('admin_id', $admin->id)->get();
    foreach($groups as $g) {
        foreach($g->items as $i) {
            echo "  {$i->name} (amt: {$i->amount})\n";
        }
    }
}
