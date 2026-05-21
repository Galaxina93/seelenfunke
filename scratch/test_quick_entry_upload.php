<?php

require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Models\Admin\Admin;
use App\Models\Accounting\AccountingSpecialIssue;

$user = Admin::first();
if (!$user) {
    echo "No admin user found\n";
    exit;
}

auth()->login($user);

// Create a dummy file
$tempFile = tempnam(sys_get_temp_dir(), 'test_receipt');
file_put_contents($tempFile, 'Dummy receipt content');
$uploadedFile = new UploadedFile(
    $tempFile,
    'test_receipt.txt',
    'text/plain',
    null,
    true // test mode
);

// Create a POST request with the file
$request = Request::create(
    '/api/funki/financials/quick-entry',
    'POST',
    [
        'title' => 'Test Quick Entry with Upload',
        'amount' => '45,50',
        'category' => 'Werbung & Marketing',
        'is_business' => '1',
        'date' => '2026-05-20',
        'location' => 'Test Runner'
    ],
    [], // cookies
    [
        'file' => $uploadedFile
    ]
);

$request->setUserResolver(fn() => $user);

try {
    $response = app()->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content:\n" . $response->getContent() . "\n";
    
    // Check if the issue was created and if files exist in public storage
    $issue = AccountingSpecialIssue::latest()->first();
    echo "Latest issue in DB: \n";
    echo "  Title: {$issue->title}\n";
    echo "  Amount: {$issue->amount}\n";
    echo "  File paths: " . json_encode($issue->file_paths) . "\n";
    
    if ($issue->file_paths && count($issue->file_paths) > 0) {
        $storedFile = storage_path('app/public/' . $issue->file_paths[0]);
        echo "Stored file location: $storedFile\n";
        echo "File exists: " . (file_exists($storedFile) ? 'Yes' : 'No') . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
}
