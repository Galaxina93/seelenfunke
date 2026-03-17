<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\Admin\Admin::first();
Auth::guard('admin')->loginUsingId($admin->id);

$c = new App\Livewire\Shop\Financial\FinancialLiquidityPlanner();
$c->mount();

echo "DATA ARRAY (Jan 2026):\n";
print_r($c->data[2026][1]);

echo "\nTOTALS ARRAY (Jan 2026):\n";
print_r($c->totals[2026][1]);

