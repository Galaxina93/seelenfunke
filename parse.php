<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$view = view('livewire.shop.ai.ai-chat', [
    'attachments' => [],
    'uploadedFiles' => [\Illuminate\Http\UploadedFile::fake()->create('test.jpg', 100)]
])->render();
echo "HTML snippet:\n";
preg_match_all('/wire:click="[^"]*"/', $view, $matches);
print_r($matches);
