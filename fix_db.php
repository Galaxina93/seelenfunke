<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

try {
    if (Schema::hasColumn('tool_usages', 'ai_agent_id')) {
        Schema::table('tool_usages', function($table) {
            $table->dropForeign(['ai_agent_id']);
            $table->dropColumn('ai_agent_id');
        });
    }
    if (Schema::hasColumn('tool_usages', 'is_error')) {
        Schema::table('tool_usages', function($table) {
            $table->dropColumn(['is_error', 'error_message']);
        });
    }
    echo "tool_usages cleaned\n";
} catch (\Exception $e) { echo "tool_usages err: " . $e->getMessage() . "\n"; }

try {
    Schema::dropIfExists('ai_metrics');
    echo "ai_metrics dropped\n";
} catch (\Exception $e) { echo "ai_metrics err: " . $e->getMessage() . "\n"; }

try {
    DB::table('migrations')->where('migration', 'like', '%ai_metrics%')->delete();
    echo "migrations cleared\n";
} catch (\Exception $e) { echo "migrations err: " . $e->getMessage() . "\n"; }
