<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\System\SystemAiHostingPlan;
use Illuminate\Support\Facades\DB;

class AiTarifeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // To ensure idempotency and clean state for this table, we can optionally clear existing AI hosting plans
        // DB::table('system_ai_hosting_plans')->truncate();

        SystemAiHostingPlan::updateOrCreate(
            ['name' => 'Google AI Ultra'],
            [
                'token_limit' => null, // unlimited/custom
                'price_monthly' => 219.99, // EUR/Month
                'description' => 'Maximaler Zugriff auf das Beste von Google AI und exklusive Funktionen. €219,99 EUR/Monat in den ersten 3 Monaten. (Gemini Ultra 3)',
                'is_active' => true,
                'features' => [
                    [
                        'title' => 'Gemini',
                        'description' => 'Die höchsten Limits für Modelle und Funktionen, einschließlich der Videoerstellung mit Veo 3.16, sowie Zugriff auf Deep Think und Gemini Agent.'
                    ],
                    [
                        'title' => '25.000 KI-Guthabenpunkte pro Monat',
                        'description' => 'Guthabenpunkte für die Videogenerierung in Flow und Whisk.'
                    ],
                    [
                        'title' => 'Flow',
                        'description' => 'Maximaler Zugriff auf unsere KI‑Tool für die Filmproduktion, mit dem du Filmszenen und -geschichten erstellen kannst, einschließlich eingeschränktem Zugriff auf Veo 3.16.'
                    ],
                    [
                        'title' => 'Gemini Code Assist und die Gemini-Befehlszeile',
                        'description' => 'Höchste Tageslimits für Anfragen in der Gemini-Befehlszeile und in den IDE-Erweiterungen von Gemini Code Assist.'
                    ],
                    [
                        'title' => 'Google Antigravity',
                        'description' => 'Die höchsten Ratenbegrenzungen für das Agentenmodell in Google Antigravity, unserer agentischen Entwicklungsplattform.'
                    ],
                    [
                        'title' => 'NotebookLM',
                        'description' => 'Die höchsten Limits und besten Modellfunktionen.'
                    ],
                    [
                        'title' => 'Gemini in Gmail, Docs, Vids und weiteren Apps',
                        'description' => 'Die höchsten Limits für Gemini direkt in Google-Apps.'
                    ],
                    [
                        'title' => 'Google Home Premium (Advanced-Abo)',
                        'description' => 'Lückenloser Videoverlauf, Ereignisbeschreibungen und weitere Vorteile.'
                    ],
                    [
                        'title' => 'YouTube Premium-Einzelmitgliedschaft',
                        'description' => 'Alle YouTube-Videos ohne Werbeunterbrechungen, offline und im Hintergrund abspielen.'
                    ],
                    [
                        'title' => 'Speicher',
                        'description' => '30 TB Speicherplatz für Google Fotos, Drive und Gmail.'
                    ],
                ],
            ]
        );
    }
}
