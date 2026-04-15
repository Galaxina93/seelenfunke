<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$file = \Illuminate\Http\UploadedFile::fake()->create('test-permissions.jpg', 100);
$path = $file->store('ai-chat-uploads', 'public');
echo "Stored path: " . $path . "\n";
echo "File exists? " . (file_exists(storage_path('app/public/' . $path)) ? 'YES' : 'NO') . "\n";
