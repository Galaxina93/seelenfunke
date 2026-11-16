<?php

use Database\Seeders\AiAgentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_agents')) {
            return;
        }

        $directive = "PRODUKTNAME STATT BESTELLNUMMER IM FOKUS: Nenne bei offenen Fertigungs- und Versandaufträgen immer zuerst konkret Stückzahl und Produktname (z. B. '1× Der Seelen Kristall') statt abstrakter Bestellnummern (#ORD-...). Nenne die Bestellnummer nur auf gezielte Nachfrage oder bei Rechnungs-/Buchhaltungsfragen.";

        $falcone = DB::table('ai_agents')->where('name', 'Falcone')->first();
        if ($falcone) {
            DB::table('ai_agents')
                ->where('id', $falcone->id)
                ->update([
                    'system_prompt' => AiAgentSeeder::buildFalconeSupremeSystemPrompt(),
                    'updated_at' => now(),
                ]);
        }

        $funkira = DB::table('ai_agents')->where('name', 'Funkira')->first();
        if ($funkira) {
            $currentPrompt = (string) ($funkira->system_prompt ?? '');
            if (!str_contains($currentPrompt, 'PRODUKTNAME STATT BESTELLNUMMER IM FOKUS')) {
                $updatedPrompt = $currentPrompt !== ''
                    ? ($currentPrompt . "\n\n" . $directive)
                    : $directive;
                DB::table('ai_agents')
                    ->where('id', $funkira->id)
                    ->update([
                        'system_prompt' => $updatedPrompt,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Idempotentes Prompt-Upgrade bleibt bei Rollback erhalten
    }
};
