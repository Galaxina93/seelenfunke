<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\Admin\Admin::first();
Auth::guard('admin')->loginUsingId($admin->id);

$c = new App\Livewire\Shop\Financial\FinancialLiquidityPlanner();
$c->mount();

$y = 2026; $m = 1;
echo "--- FINANCE GROUPS ITEMS (JAN 2026) ---\n";
$groups = App\Models\Financial\FinanceGroup::with('items')->where('admin_id', $admin->id)->get();
foreach ($groups as $group) {
    foreach ($group->items as $item) {
        $start = Carbon\Carbon::parse($item->first_payment_date);
        $check = Carbon\Carbon::createFromDate($y, $m, 1)->startOfMonth();
        $startMonth = $start->copy()->startOfMonth();

        if (!$check->lt($startMonth)) {
            $diffMonths = $check->diffInMonths($startMonth);
            if (($diffMonths % max(1, $item->interval_months)) === 0) {
                // Determine type
                $text = mb_strtolower($item->name . ' ' . $group->name);
                if ($item->is_business) {
                    echo "BIZ ITEM: {$item->name} (Group: {$group->name}) | Amt: {$item->amount} | Text: {$text}\n";
                }
            }
        }
    }
}

echo "\n--- SPECIAL ISSUES (JAN 2026) ---\n";
$specials = App\Models\Financial\FinanceSpecialIssue::where('admin_id', $admin->id)->get();
foreach ($specials as $special) {
    $date = Carbon\Carbon::parse($special->execution_date);
    if ($date->year == 2026 && $date->month == 1) {
        if ($special->is_business) {
             echo "BIZ SPECIAL: {$special->title} (Cat: {$special->category}) | Amt: {$special->amount}\n";
        }
    }
}
