<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;

$tmpl = App\Models\Management\ManagementCalendarEvent::where('title', 'like', '%protopic%')
    ->whereNotNull('recurrence')
    ->first();

if (!$tmpl) {
    echo "No template found.\n";
    exit;
}



echo "Template Info:\n";
echo "Start Date: " . $tmpl->start_date->toIso8601String() . " (" . $tmpl->start_date->timezoneName . ")\n";
echo "End Date: " . $tmpl->end_date->toIso8601String() . " (" . $tmpl->end_date->timezoneName . ")\n";

$simDate = $tmpl->start_date->copy();
$calcEnd = Carbon::today()->addMonths(6);

if ($simDate < Carbon::today()) {
    while ($simDate < Carbon::today()) {
        switch ($tmpl->recurrence) {
            case 'daily': $simDate->addDay(); break;
            case 'weekly': $simDate->addWeek(); break;
            case 'monthly': $simDate->addMonth(); break;
            case 'yearly': $simDate->addYear(); break;
        }
    }
}

echo "\nSimulation starting at: " . $simDate->toIso8601String() . "\n";

for ($i = 0; $i < 3; $i++) {
    $duration = $tmpl->start_date->diffInSeconds($tmpl->end_date);
    echo "  Raw duration: " . $duration . "\n";
    
    // Let's also check with absolute parameter explicitly false
    $durationSign = $tmpl->start_date->diffInSeconds($tmpl->end_date, false);
    echo "  Signed duration: " . $durationSign . "\n";
    
    $simEnd = $simDate->copy()->addSeconds($duration);

    echo "Occurrence $i:\n";
    echo "  Start: " . $simDate->toIso8601String() . "\n";
    echo "  End:   " . $simEnd->toIso8601String() . "\n";
    
    switch ($tmpl->recurrence) {
        case 'daily': $simDate->addDay(); break;
        case 'weekly': $simDate->addWeek(); break;
        case 'monthly': $simDate->addMonth(); break;
        case 'yearly': $simDate->addYear(); break;
    }
}


