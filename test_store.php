<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

mkdir('storage/app/public/test_perm', 0555);
chmod('storage/app/public/test_perm', 0555);

$file = \Illuminate\Http\UploadedFile::fake()->create('test.jpg', 100);
$path = $file->store('test_perm', 'public');
var_dump($path);
rmdir('storage/app/public/test_perm');
