<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\FirebaseService;

echo "Starting Firebase push notification test...\n";

$firebase = resolve(FirebaseService::class);

$token = "drVUOGjeS8i-1qiMW6PGPD:APA91bFEDelO4ntPNmjnJpRrTA7xN--yqGcWXbRKHzk89OMs9bErBRvk-z0QNPvxd2Q3U57OdhnuoZerYYLnRp2DYhldgbEV0yPFkkoLnn9f69-GL_E4gtA";

echo "Sending direct notification to token: " . substr($token, 0, 15) . "...\n";
$success = $firebase->sendPushNotification(
    $token,
    "Direkter Test-Push! 🚀",
    "Dies ist ein direkt an dein Gerät gesendeter Push.",
    ['open_tab' => '1']
);

echo "Direct send result: " . ($success ? "SUCCESS" : "FAILED") . "\n\n";

echo "Sending notification to all admins via sendToAdmins...\n";
$sentCount = $firebase->sendToAdmins(
    "Neue Bestellung eingegangen! 🎉",
    "Bestellung #TEST-9999 von Max Mustermann (150,00 €)",
    [
        'open_tab' => '1',
        'order_id' => '019ea69c-cba5-722e-842f-04655edac746' // Some uuid order ID format
    ]
);

echo "Admins notification result: Sent to " . $sentCount . " devices.\n";
