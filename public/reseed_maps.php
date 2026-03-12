<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\Seeders\MapSeeder', '--force' => true]);
    echo "Success: Map Data Seeded.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
