<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\Admin\Admin::where('email', 'kontakt@mein-seelenfunke.de')->first();
echo $user ? $user->createToken('test')->plainTextToken : 'no admin';
