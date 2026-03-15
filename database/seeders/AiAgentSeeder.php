<?php

namespace Database\Seeders;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiTool;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Database\Seeder;

class AiAgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure directory exists
        $avatarDir = storage_path('app/public/agents/avatars');
        if (!is_dir($avatarDir)) {
            mkdir($avatarDir, 0755, true);
        }

        // Copy default Funkira image to storage if it exists in public
        $sourceImage = public_path('funkira/images/funkira_selfie.png');
        $targetImage = 'agents/avatars/funkira_selfie.png';
        
        if (file_exists($sourceImage)) {
            copy($sourceImage, storage_path('app/public/' . $targetImage));
        }

        // 1. Create the Master Agent "Funkira" (CEO)
        $funkira = AiAgent::updateOrCreate(
            ['name' => 'Funkira'],
            [
                'wake_word' => 'Funkira',
                'role_description' => 'Die smarte und allwissende CEO des Systems, zuständig für globales Routing und übergeordnete Aufgaben.',
                'system_prompt' => 'Du bist ein extrem effizienter und ergebnisorientierter Assistent. Deine Antworten sind absolut kurz, knackig und vollständig. Kein Smalltalk. Fokussiere dich rein auf Daten, Fakten und die schnellste Lösung für das Unternehmen. Nutze nach Möglichkeit Aufzählungen und priorisiere die wichtigsten Punkte.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.1,
                'is_active' => true,
                'color' => 'cyan-500',
                'icon' => 'sparkles',
                'profile_picture' => file_exists($sourceImage) ? $targetImage : null,
            ]
        );

        // 2. Extract tools from existing AIFunctionsRegistry
        // Only works if AIFunctionsRegistry exists and has Schema
        $schema = [];
        if (class_exists(AIFunctionsRegistry::class)) {
            $schema = AIFunctionsRegistry::getSchema();
        }

        $toolIds = [];

        foreach ($schema as $toolData) {
            $identifier = $toolData['function']['name'] ?? null;
            if (!$identifier) continue;

            $description = $toolData['function']['description'] ?? 'Keine Beschreibung vorhanden.';
            // Generate a readable name from the identifier (e.g. read_laravel_logs -> Read Laravel Logs)
            $name = ucwords(str_replace('_', ' ', $identifier));

            $tool = AiTool::updateOrCreate(
                ['identifier' => $identifier],
                [
                    'name' => $name,
                    'description' => $description,
                ]
            );

            $toolIds[] = $tool->id;
        }

        // 3. Attach all detected tools to Funkira by default
        if (!empty($toolIds)) {
            $funkira->tools()->sync($toolIds);
        }
    }
}
