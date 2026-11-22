<?php

use App\Services\AI\AntigravityUniverseTicketBridge;
use App\Services\System\AntigravityDirectInboxService;
use Database\Seeders\AiAgentSeeder;
use Database\Seeders\AiCompanyStructureSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (Schema::hasTable('ai_departments') && Schema::hasTable('ai_agents')) {
            (new AiCompanyStructureSeeder())->run();
            (new AiAgentSeeder())->run();
        }

        if (!Schema::hasTable('ai_universe_tickets') || !Schema::hasTable('ai_universe_ticket_messages')) {
            return;
        }

        $inbox = app(AntigravityDirectInboxService::class);

        // Falls der Workflow-Auftrag ag_20261122_204308_4095b2d1 (awaiting_go Spiegelung ins Ticketsystem) offen liegt, direkt als erledigt quittieren
        try {
            $inbox->completeItem(
                'ag_20261122_204308_4095b2d1',
                'Vollständige bidirektionale 1:1-Spiegelung aller Antigravity-Konzepte, awaiting_go-Freigaben, Direktaufträge und Verwerfungen (inkl. ag_20261116_193337_c18c676f) ohne E-Mail-Umwege direkt im zentralen Ticketsystem (/admin/tickets) umgesetzt.',
                true
            );
        } catch (\Throwable) {
        }

        AntigravityUniverseTicketBridge::backfillAllFromInbox($inbox);
    }

    public function down(): void
    {
        // Idempotente Historien-Spiegelung
    }
};
