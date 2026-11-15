<?php

use App\Console\Commands\System\CleanupSystemLogsCommand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_logs')) {
            return;
        }

        // Einmalige Bereinigung & Archivierung der vor dem SystemLog-Umbau behobenen Alt-Meldungen
        CleanupSystemLogsCommand::executePrune(7, 30, false);
        CleanupSystemLogsCommand::archiveAllOpenIssues();
    }

    public function down(): void
    {
        // Nicht-destruktive Einmal-Archivierung
    }
};
