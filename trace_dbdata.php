<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Auth::guard('admin')->loginUsingId(1); // M.B. Id is mostly 1
$c = new App\Livewire\Shop\Financial\FinancialLiquidityPlanner();
$c->years = [2026];
$adminId = 1;
$dbData = [];

$groups = App\Models\Financial\FinanceGroup::with('items')->where('admin_id', $adminId)->get();
foreach ($groups as $group) {
    echo "Processing Group: {$group->name}\n";
    foreach ($group->items as $item) {
        $text = mb_strtolower($item->name . ' ' . $group->name);
        
        $type = 'out'; $key = 'private'; // default simplification
        if ($item->amount >= 0) {
            $type = 'in'; $key = 'private_in';
        }

        $amt = abs($item->amount);
        $y = 2026;
        $m = 1;

        $start = Carbon\Carbon::parse($item->first_payment_date);
        $check = Carbon\Carbon::createFromDate($y, $m, 1)->startOfMonth();
        $startMonth = $start->copy()->startOfMonth();

        $due = false;
        if (!$check->lt($startMonth)) {
            $diffMonths = $check->diffInMonths($startMonth);
            $due = ($diffMonths % max(1, $item->interval_months)) === 0;
        }

        echo "  - {$item->name} : amt={$item->amount}, due={$due}, interval={$item->interval_months}\n";
    }
}
