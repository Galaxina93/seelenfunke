<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$args = [];
$res = \App\Services\AI\Functions\SettingsFunctions::executeReadWikiFiles($args);

if ($res['status'] === 'success') {
    echo "SUCCESS:\n";
    echo substr($res['content'], 0, 1500);
} else {
    echo "ERROR:\n";
    echo $res['message'];
}

echo "\n";
