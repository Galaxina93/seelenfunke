<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Carbon\Carbon;
use Illuminate\Support\Str;

echo "Checking ALG Logic...\n";
$items = App\Models\Financial\FinanceCostItem::all();
foreach($items as $item) {
    if (Str::contains(mb_strtolower($item->name), ['alg', 'gz', 'gründerzuschuss'])) {
        echo "MATCH: " . $item->name . "\n";
        $start = Carbon::parse($item->first_payment_date);
        $end = $item->last_payment_date ? Carbon::parse($item->last_payment_date) : $start;

        echo "  Start: " . $start->toDateString() . " End: " . $end->toDateString() . "\n";
        $checkDate = Carbon::createFromDate(2026, 6, 1)->startOfMonth();
        $isBetween = $checkDate->betweenIncluded($start->copy()->startOfMonth(), $end->copy()->startOfMonth());
        echo "  In June 2026? " . ($isBetween ? 'YES' : 'NO') . "\n";
    }
}
