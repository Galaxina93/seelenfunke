<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->call('migrate');
echo "Migrate Output:\n" . $kernel->output() . "\n\n";

$kernel->call('db:seed', ['--class' => 'Database\Seeders\MapSeeder']);
echo "Seed Output:\n" . $kernel->output();
