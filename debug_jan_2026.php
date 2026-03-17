<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
Auth::guard('admin')->loginUsingId(1);
$c = new App\Livewire\Shop\Financial\FinancialLiquidityPlanner();
$c->mount();
echo "--- JANUAR 2026 ---\n";
print_r($c->totals[2026][1]);
echo "--- APRIL 2026 ---\n";
print_r($c->totals[2026][4]);
echo "--- AUGUST 2026 ---\n";
print_r($c->totals[2026][8]);
