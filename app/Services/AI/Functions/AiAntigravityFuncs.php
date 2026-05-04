<?php

namespace App\Services\AI\Functions;

trait AiAntigravityFuncs
{
    public static function getAiAntigravityFuncsSchema(): array
    {
        return [
            [
                'name' => 'system_send_to_antigravity',
                'description' => 'Sendet eine direkte Anweisung, einen Fehlerbericht oder einen Task an den Entwickler-Agenten (Antigravity). Nutze dies IMMER, wenn der Nutzer sagt "Sag Antigravity er soll..." oder "Schick das an Antigravity".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_description' => [
                            'type' => 'string',
                            'description' => 'Die genaue Aufgabe, Fehlermeldung oder Information für Antigravity. Formuliere es als klaren Arbeitsauftrag von dir (dem KI-Agenten) an Antigravity (den Entwickler).'
                        ]
                    ],
                    'required' => ['task_description']
                ],
                'callable' => [self::class, 'executeSendToAntigravity']
            ]
        ];
    }

    public static function executeSendToAntigravity(array $args)
    {
        try {
            $task = $args['task_description'] ?? '';
            
            // Formatierung für Antigravity
            $payload = "[AUTO-TASK VON DEINEM AGENTEN]\n" . $task;

            // TCP Verbindung zum Windows-Host aufbauen (aus dem Docker-Container heraus)
            // host.docker.internal leitet auf den WSL/Windows Host weiter.
            $fp = @fsockopen("host.docker.internal", 8888, $errno, $errstr, 3);
            if (!$fp) {
                // Fallback, falls host.docker.internal nicht aufgelöst werden kann (z.B. native Linux)
                $fp = @fsockopen("172.17.0.1", 8888, $errno, $errstr, 3);
            }
            
            if (!$fp) {
                return ['status' => 'error', 'message' => "Konnte Antigravity Bridge nicht erreichen ($errno: $errstr). Sag dem Nutzer, er muss das Python-Skript (antigravity_bridge.py) auf Windows starten!"];
            }

            fwrite($fp, $payload);
            fclose($fp);

            return [
                'status' => 'success',
                'message' => 'Die Aufgabe wurde über die TCP-Bridge erfolgreich in das Antigravity Chat-Fenster des Nutzers eingefügt.'
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'TCP Fehler: ' . $e->getMessage()];
        }
    }
}
