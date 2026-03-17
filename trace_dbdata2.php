<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\Admin\Admin::first();
Auth::guard('admin')->loginUsingId($admin->id);

$c = new App\Livewire\Shop\Financial\FinancialLiquidityPlanner();
$c->mount();

print_r($c->totals[2026][1]);

$groups = App\Models\Financial\FinanceGroup::with('items')->where('admin_id', $admin->id)->get();
foreach ($groups as $group) {
    foreach ($group->items as $item) {
        $y = 2026;
        $m = 1;
        $start = Carbon\Carbon::parse($item->first_payment_date);
        $check = Carbon\Carbon::createFromDate($y, $m, 1)->startOfMonth();
        $startMonth = $start->copy()->startOfMonth();

        if ($check->lt($startMonth)) {
            echo "Skipped < start: {$item->name}\n";
            continue;
        }

        $diffMonths = $check->diffInMonths($startMonth);
        $mod = $diffMonths % max(1, $item->interval_months);
        echo "{$item->name} | amt: {$item->amount} | inter: {$item->interval_months} | diff: {$diffMonths} | mod: {$mod}\n";
    }
}
