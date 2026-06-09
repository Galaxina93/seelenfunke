<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== COST ITEMS ===\n";
foreach (App\Models\Accounting\AccountingCostItem::all() as $item) {
    echo "ID: {$item->id} | Title: {$item->title} | Amount: {$item->amount} | Interval: {$item->interval_months} | IsBusiness: {$item->is_business}\n";
}

echo "\n=== SPECIAL ISSUES FOR 2026-06-08 ===\n";
$start = Carbon\Carbon::parse('2026-06-08')->startOfDay();
$end = Carbon\Carbon::parse('2026-06-08')->endOfDay();
foreach (App\Models\Accounting\AccountingSpecialIssue::whereBetween('execution_date', [$start, $end])->get() as $issue) {
    echo "ID: {$issue->id} | Title: {$issue->title} | Amount: {$issue->amount} | Date: {$issue->execution_date} | IsBusiness: {$issue->is_business}\n";
}
