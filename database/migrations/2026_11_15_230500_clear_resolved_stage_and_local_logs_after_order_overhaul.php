<?php

use App\Console\Commands\System\CleanupSystemLogsCommand;
use App\Services\System\SentinelTriageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_logs')) {
            return;
        }

        app(SentinelTriageService::class)->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
        CleanupSystemLogsCommand::executePrune(7, 30, true);
    }

    public function down(): void
    {
        // Nicht-destruktive Log-Bereinigung nach Fehlerbehebung
    }
};
