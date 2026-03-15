<?php

namespace App\Services\AI\Functions;

use App\Models\Ai\AiAgent;

trait AgentFunctions
{
    public static function getAgentFunctionsSchema(): array
    {
        return [
            [
                'name' => 'update_agent_configuration',
                'description' => 'Ändere deine eigene Agenten-Konfiguration in der Datenbank (z.B. voice_speed, temperature, name). Nützlich, wenn ein Nutzer dich bittet, langsamer/schneller zu sprechen oder deine Parameter anzupassen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'setting_key' => [
                            'type' => 'string',
                            'description' => 'Der Schlüssel der Einstellung (gültig: tts_speed, temperature, wake_word, name)',
                            'enum' => ['tts_speed', 'temperature', 'wake_word', 'name']
                        ],
                        'setting_value' => [
                            'type' => 'string',
                            'description' => 'Der neue Wert für die Einstellung. Z.B. "0.8" für tts_speed, um langsamer zu sprechen.'
                        ],
                    ],
                    'required' => ['setting_key', 'setting_value'],
                ],
                'callable' => function (array $args) {
                    $key = $args['setting_key'] ?? null;
                    $val = $args['setting_value'] ?? null;

                    if (!$key || $val === null) {
                        return ['status' => 'error', 'message' => 'Missing key or value'];
                    }

                    // Für welche(n) Agenten gilt das? Standardmäßig für den aktuell aktiven, der die Funktion aufruft.
                    // Da wir hier nicht zwingend den aktiven Agenten-ID Kontext haben, nehmen wir Funkira als Fallback oder suchen den aktiven.
                    // Idealerweise sucht das System den Agenten, der gerade interagiert.
                    $agent = AiAgent::where('name', 'Funkira')->first() ?? AiAgent::first();

                    if (!$agent) {
                        return ['status' => 'error', 'message' => 'No agent found to edit'];
                    }

                    if (!in_array($key, ['tts_speed', 'temperature', 'wake_word', 'name'])) {
                        return ['status' => 'error', 'message' => 'Invalid setting key. Allowed: tts_speed, temperature, wake_word, name'];
                    }

                    $oldValue = $agent->{$key};

                    // Typisierung
                    if ($key === 'tts_speed' || $key === 'temperature') {
                        $val = (float) $val;
                    }

                    $agent->{$key} = $val;
                    $agent->save();

                    return [
                        'status' => 'success',
                        'message' => "Erfolgreich geändert von {$oldValue} auf {$val}.",
                        'changed_key' => $key,
                        'new_value' => $val,
                        'ui_action' => 'reload_config'
                    ];
                }
            ]
        ];
    }
}
