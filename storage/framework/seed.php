<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiRole;

$depts = [
    ['name' => 'Leitung', 'icon' => 'star', 'color' => 'amber-500'],
    ['name' => 'Produkte', 'icon' => 'cube', 'color' => 'blue-500'],
    ['name' => 'Marketing', 'icon' => 'megaphone', 'color' => 'purple-500'],
    ['name' => 'Bestellungen', 'icon' => 'shopping-cart', 'color' => 'emerald-500'],
    ['name' => 'Buchhaltung', 'icon' => 'calculator', 'color' => 'rose-500'],
];

$order = 0;
$created = [];
foreach ($depts as $d) {
    $dept = AiDepartment::firstOrCreate(['name' => $d['name']], [
        'description' => "Zuständig für {$d['name']}",
        'icon' => $d['icon'],
        'color' => $d['color'],
        'order_index' => $order++
    ]);
    $created[$d['name']] = $dept;
    echo "Created {$d['name']}\n";
}

$fb = $created['Leitung'] ?? AiDepartment::first();
if ($fb) {
    $updated = AiRole::whereNull('ai_department_id')->update(['ai_department_id' => $fb->id]);
    echo "Assigned $updated roles to fallback department.";
}
