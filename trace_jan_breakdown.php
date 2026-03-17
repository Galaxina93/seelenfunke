<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\Admin\Admin::first();
Auth::guard('admin')->loginUsingId($admin->id);

$c = new App\Livewire\Shop\Financial\FinancialLiquidityPlanner();
$c->mount();

echo "Einnahmen (Jan 2026):\n";
foreach($c->data[2026][1]['in'] as $k => $v) {
    if($v) echo " - $k: $v\n";
}
echo "\nAusgaben (Jan 2026):\n";
foreach($c->data[2026][1]['out'] as $k => $v) {
    if($v) echo " - $k: $v\n";
}
echo "Die privaten Ausgaben (" . $c->data[2026][1]['out']['private'] . ") setzen sich zusammen aus Fixkosten + 450€ Lebensmittel.\n";
