<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$text = "Er wurde am 25. Oktober 1963 geboren. Heute ist der 13.03.2026. Sein Ticket vom 1990-07-13 ist abgelaufen. Wir sehen uns am 1.12.";
$clean = \App\Services\AI\TTSHelper::sanitizeForGermanTTS($text);
echo "Original:\n'$text'\n\nResult:\n'$clean'\n";
