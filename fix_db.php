<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

Schema::table('support_tickets', function (Blueprint $table) {
    if (!Schema::hasColumn('support_tickets', 'rating')) {
        $table->unsignedTinyInteger('rating')->nullable()->comment('1-5 Sterne Bewertung nach Abschluss');
    }
    if (!Schema::hasColumn('support_tickets', 'feedback_text')) {
        $table->text('feedback_text')->nullable()->comment('Kundenfeedback');
    }
});
echo "Columns successfully added\n";
