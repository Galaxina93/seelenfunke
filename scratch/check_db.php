<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== COST ITEMS ===\n";
print_r(App\Models\Accounting\AccountingCostItem::all()->toArray());

echo "=== SPECIAL ISSUES ===\n";
print_r(App\Models\Accounting\AccountingSpecialIssue::all()->toArray());

echo "=== ORDERS ===\n";
print_r(App\Models\Order\OrderOrder::all()->toArray());
