<?php

require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Accounting\AccountingSpecialIssue;

$issues = AccountingSpecialIssue::latest()->take(10)->get();

foreach ($issues as $issue) {
    echo "ID: {$issue->id}\n";
    echo "Title: {$issue->title}\n";
    echo "Amount: {$issue->amount}\n";
    echo "Category: {$issue->category}\n";
    echo "Is Business: " . ($issue->is_business ? 'Yes' : 'No') . "\n";
    echo "Date: {$issue->execution_date}\n";
    echo "Location: {$issue->location}\n";
    echo "File Paths: " . json_encode($issue->file_paths) . "\n";
    echo "Created At: {$issue->created_at}\n";
    echo "-----------------------------------------\n";
}
