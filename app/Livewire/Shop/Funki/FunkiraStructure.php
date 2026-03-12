<?php

namespace App\Livewire\Shop\Funki;

use Livewire\Component;

class FunkiraStructure extends Component
{
    public string $selectedModelId = 'gpt-oss-120b';

    public function getModelsDataProperty()
    {
        return [
            'gpt-oss-120b' => [
                'id' => 'gpt-oss-120b',
                'name' => 'GPT-OSS-120B',
                'badge' => 'AKTUELL GENUTZT / SYSTEM-STANDARD',
                'provider' => 'Mittwald / Seelenfunke Local API',
                'description' => 'Das standardmäßig verwendete, hochgradig an Funktionallitäten angepasste Open-Source KI-Modell. Bietet höchste Datenkontrolle direkt auf dem Server und ein extrem breites, tiefgehendes Systemverständnis für komplexe Architekturen.',
                'pros' => [
                    ['title' => 'Höchster Datenschutz', 'desc' => 'Daten verlassen das europäische Server-Netzwerk nicht an Drittanbieter (wie OpenAI oder US-Groq). Volle Datenhoheit.'],
                    ['title' => 'Tiefes Systemverständnis', 'desc' => 'Exzellente Performance bei komplexen, mehrschichtigen Logik-Aufgaben und breiten Datenbank-Vergleichen (MMLU ~89.0).'],
                    ['title' => 'Keine externen Pay-per-Token Kosten', 'desc' => 'Vollständige Konstentkontrolle durch eigene Backend-Verarbeitung.']
                ],
                'cons' => [
                    ['title' => 'Inferenz-Geschwindigkeit', 'desc' => 'Langsameres Token-Streaming (~18-25 T/s) im Vergleich zu speziell designten Tensor-Platinen (LPUs).'],
                    ['title' => 'Hoher Ressourcen-Bedarf', 'desc' => 'Benötigt enorme Server-Rechenleistung (RAM/VRAM), was die Parallelverarbeitung (viele Zugriffe) einschränkt.']
                ],
                'metrics' => [
                    'speed' => ['label' => 'Generation Speed', 'value' => 20, 'max' => 350, 'text' => '~ 20 T/s'],
                    'logic' => ['label' => 'Complex Reasoning', 'value' => 89, 'max' => 100, 'text' => '~ 89.0 MMLU'],
                    'tools' => ['label' => 'Tool Accuracy', 'value' => 85, 'max' => 100, 'text' => '~ 85%']
                ]
            ],
            'llama-3.3-70b' => [
                'id' => 'llama-3.3-70b',
                'name' => 'Llama-3.3-70B-Versatile',
                'badge' => 'ALTERNATIVE / GROQ API',
                'provider' => 'Groq Cloud Compute',
                'description' => 'Das auf unglaubliche Geschwindigkeit optimierte Modell. Ausgeführt über blitzschnelle LPUs der Groq Infrastruktur. Kombiniert starkes menschliches Sprachverständnis mit extremer Tool-Calling Präzision.',
                'pros' => [
                    ['title' => 'Extreme Inferenz-Geschwindigkeit', 'desc' => 'Über 300 Tokens/Sekunde durch LPUs. Antworten fließen augenblicklich in das Frontend (fast verzögerungsfrei).'],
                    ['title' => 'Exzellentes System Prompt Handling', 'desc' => 'Hält sich bei Rollenspielen exakter an strikte Constraints und Personas als ältere Modelle.'],
                    ['title' => 'Zuverlässiges Tool-Calling', 'desc' => 'Überragende Zuverlässigkeit beim Ausgeben exakter JSON-Strukturen für Hintergrund-Funktionen.']
                ],
                'cons' => [
                    ['title' => 'Limitierbares Kontext-Fenster', 'desc' => 'US-API-Limitationen zwingen den Agenten oft auf kleinere Request-Grenzen (z.B. max 8k Kontext Tokens).'],
                    ['title' => 'Externe Datenverarbeitung', 'desc' => 'Firmen- und Kundendaten müssen (wenn nicht anders verschleiert) an externe Groq-Server in den USA gesendet werden.']
                ],
                'metrics' => [
                    'speed' => ['label' => 'Generation Speed', 'value' => 320, 'max' => 350, 'text' => '~ 320 T/s'],
                    'logic' => ['label' => 'Complex Reasoning', 'value' => 86, 'max' => 100, 'text' => '~ 86.1 MMLU'],
                    'tools' => ['label' => 'Tool Accuracy', 'value' => 98, 'max' => 100, 'text' => '~ 98%']
                ]
            ]
        ];
    }

    public function selectModel($modelId)
    {
        if (isset($this->modelsData[$modelId])) {
            $this->selectedModelId = $modelId;
        }
    }

    public function render()
    {
        $models = $this->modelsData;
        $currentModel = $models[$this->selectedModelId] ?? collect($models)->first();

        return view('livewire.shop.funki.funkira-structure', [
            'models' => $models,
            'current' => $currentModel
        ]);
    }
}
