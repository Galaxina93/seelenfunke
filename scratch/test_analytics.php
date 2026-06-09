<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Order\OrderOrder;
use App\Models\Admin\Admin;
use App\Services\AnalyticsService;
use Carbon\Carbon;

// Clean up first
AccountingCostItem::query()->delete();
AccountingGroup::query()->delete();
OrderOrder::query()->delete();

$admin = Admin::first() ?: Admin::forceCreate([
    'id' => \Illuminate\Support\Str::uuid(),
    'first_name' => 'Admin',
    'last_name' => 'Test',
    'email' => 'admin@test.com',
    'password' => bcrypt('password'),
]);

$group = AccountingGroup::create([
    'admin_id' => $admin->id,
    'name' => 'Test Group',
    'type' => 'expense',
]);

AccountingCostItem::forceCreate([
    'id' => \Illuminate\Support\Str::uuid(),
    'accounting_group_id' => $group->id,
    'name' => 'Fixed Income Test',
    'amount' => 1000.00,
    'interval_months' => 1,
    'first_payment_date' => now()->format('Y-m-d'),
    'is_business' => true,
]);

AccountingCostItem::forceCreate([
    'id' => \Illuminate\Support\Str::uuid(),
    'accounting_group_id' => $group->id,
    'name' => 'Fixed Expense Test',
    'amount' => -500.00,
    'interval_months' => 1,
    'first_payment_date' => now()->format('Y-m-d'),
    'is_business' => true,
]);

$start = now()->startOfMonth();
$end = now()->endOfMonth();

$service = app(AnalyticsService::class);
$allLogins = $service->getAllLoginsCollection();
$stats = $service->getStats($start->format('Y-m-d'), $end->format('Y-m-d'), 'business', $allLogins);

echo "=== RESULTS ===\n";
echo "Total Revenue: {$stats['total_revenue']}\n";
echo "Total Profit: {$stats['total_profit']}\n";
echo "Average Profit (avg_profit): {$stats['avg_profit']}\n";
echo "Fixed Income Total: {$stats['fixed_income_total']}\n";
echo "Margin: {$stats['margin']}%\n";

echo "\n=== DAILY DATA ===\n";
foreach ($stats['chart_data']['labels'] as $i => $label) {
    echo "Date: {$label} | Rev: {$stats['chart_data']['revenue'][$i]} | Exp: {$stats['chart_data']['expenses'][$i]} | Profit: {$stats['chart_data']['profit'][$i]}\n";
}
