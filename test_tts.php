<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$text = "0176 57793016 oder +49 160 90592752.";
$clean = \App\Services\AI\TTSHelper::sanitizeForGermanTTS($text);
echo "Result:\n'$clean'\n";
