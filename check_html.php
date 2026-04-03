<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$msg = App\Models\Management\Mail\MailMessage::orderBy('id', 'desc')->first();
file_put_contents('/tmp/safe_html.txt', $msg->safe_body_html);
echo "done";
