<?php

namespace App\Livewire\Global\Ai;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiTool;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class AiAgentEditor extends Component
{
    use WithFileUploads;

    public $agentId;
    public $name = '';
    public $wake_word = '';
    public $role_description = '';
    public $system_prompt = '';
    public $model = 'gpt-oss-120b';
    public $temperature = 0.4;
    public $activePreset = null; // Tracks the currently clicked preset button
    public $is_active = true;
    public $color = 'cyan-500';
    public $icon = 'sparkles'; // Default to a heroicon name
    public $tts_provider = 'elevenlabs';
    public $tts_voice = '';
    public $tts_api_url = '';
    public $tts_speed = 1.0;
    
    public $searchTool = ''; // Suchleiste für Werkzeuge
    
    public $profile_picture;
    public $existing_profile_picture = null;

    // Speichert die IDs der aktivierten Tools
    public $selectedTools = [];

    // Validierte Paletten
    public $availableColors = [
        'cyan-500', 'emerald-500', 'blue-500', 'indigo-500', 
        'purple-500', 'pink-500', 'rose-500', 'red-500', 
        'orange-500', 'amber-500', 'yellow-500', 'green-500'
    ];

    public $availableModels = [
        'gpt-oss-120b' => 'GPT-OSS 120B',
        'Ministral-3-14B-Instruct-2512' => 'Ministral 3 14B',
        'Devstral-Small-2-24B-Instruct-2512' => 'Devstral Small 2 24B',
        'Qwen3-Embedding-8B' => 'Qwen3 Embedding 8B',
        'whisper-large-v3-turbo' => 'Whisper Large v3 Turbo'
    ];

    public $ttsProviders = [
        'elevenlabs' => 'ElevenLabs (Online API)',
        'local_rtx2080' => 'Lokal (RTX 2080 Ti) - Custom Script',
        'coqui_xttsv2' => 'Lokal (RTX 2080 Ti) - Coqui XTTSv2',
        'none' => 'Deaktiviert (Nur Text)'
    ];

    public $ttsVoices = [
        'elevenlabs' => [
            '21m00Tcm4TlvDq8ikWAM' => 'Rachel (Weiblich)',
            'AZnzlk1XvdvUeBnXmlld' => 'Domi (Weiblich, Deutsch)',
            'EXAVITQu4vr4xnSDxMaL' => 'Bella (Weiblich)',
            'ErXwobaYiN019PkySvjV' => 'Antoni (Männlich)'
        ],
        'local_rtx2080' => [
            'xtts_v2_de_female' => 'Local XTTS (Weiblich, DE)',
            'xtts_v2_de_male' => 'Local XTTS (Männlich, DE)'
        ],
        'coqui_xttsv2' => [
            'kira_base' => 'Funkira (Klon-Stimme)',
            'female_1' => 'Standard Weiblich',
            'male_1' => 'Standard Männlich'
        ]
    ];

    public $modelDetails = [
        'gpt-oss-120b' => [
            'type' => 'Chat + Reasoning', 
            'capabilities' => 'Text, Tool-Calling', 
            'context' => '131.072 Token', 
            'license' => 'Apache 2.0',
            'use_cases' => ['Komplexe Logik & Programmierung', 'Tiefgründige Textanalyse', 'Autonome Entscheidungen (CEO)']
        ],
        'Ministral-3-14B-Instruct-2512' => [
            'type' => 'Chat + Vision', 
            'capabilities' => 'Text, Bild, Tool-Calling', 
            'context' => '262.144 Token', 
            'license' => 'Apache 2.0',
            'use_cases' => ['Bildanalyse & Beschreibung', 'Solider Alltagsbegleiter', 'Schnelle Dokumenten-Auswertung']
        ],
        'Devstral-Small-2-24B-Instruct-2512' => [
            'type' => 'Chat', 
            'capabilities' => 'Text, Bild, Tool-Calling', 
            'context' => '262.144 Token', 
            'license' => 'Apache 2.0',
            'use_cases' => ['Schnelles Brainstorming', 'Kreatives Schreiben', 'Allgemeine Code-Hilfe']
        ],
        'Qwen3-Embedding-8B' => [
            'type' => 'Embedding', 
            'capabilities' => 'Text → Vektor', 
            'context' => '32.768 Token', 
            'license' => 'Apache 2.0',
            'use_cases' => ['Wissensdatenbank-Aufbau', 'Semantische Suche', 'Dokumenten-Matching']
        ],
        'whisper-large-v3-turbo' => [
            'type' => 'Speech-to-Text', 
            'capabilities' => 'Audio → Text', 
            'context' => 'n/a (Audio-basiert)', 
            'license' => 'MIT',
            'use_cases' => ['Spracherkennung im Widget', 'Transkription von Nachrichten', 'Barrierefreiheit']
        ]
    ];

    public $availableIcons = [
        'sparkles', 'cpu-chip', 'bug-ant', 'bolt', 'beaker', 
        'code-bracket-square', 'command-line', 'cube-transparent', 
        'shield-check', 'server', 'rocket-launch', 'paint-brush',
        'magnifying-glass', 'globe-europe-africa', 'fire', 'face-smile',
        'academic-cap', 'adjustments-horizontal', 'bell', 'briefcase',
        'camera', 'chat-bubble-left-ellipsis', 'cloud', 'cog-6-tooth',
        'document-text', 'envelope', 'heart', 'key', 'light-bulb',
        'lock-closed', 'map-pin', 'megaphone', 'moon', 'paper-airplane',
        'phone', 'photo', 'puzzle-piece', 'shopping-cart', 'star',
        'sun', 'trophy', 'user', 'video-camera', 'wrench-screwdriver'
    ];

    public function mount($id)
    {
        $this->agentId = $id;

        if ($id !== 'new') {
            $agent = AiAgent::findOrFail($id);
            $this->name = $agent->name;
            $this->wake_word = $agent->wake_word;
            $this->role_description = $agent->role_description;
            $this->system_prompt = $agent->system_prompt;
            $this->model = $agent->model;
            $this->temperature = $agent->temperature;
            $this->is_active = $agent->is_active;
            $this->color = $agent->color ?? 'cyan-500';
            
            // Konvertiere alte bi-icons zu heroicons falls nötig
            $oldIcon = $agent->icon ?? 'sparkles';
            if (str_starts_with($oldIcon, 'bi-')) {
                $oldIcon = 'sparkles'; // Fallback
            }
            $this->icon = $oldIcon;
            $this->tts_provider = $agent->tts_provider ?? 'elevenlabs';
            $this->tts_voice = $agent->tts_voice ?? '';
            $this->tts_api_url = $agent->tts_api_url ?? '';
            $this->tts_speed = $agent->tts_speed ?? 1.0;
            
            $this->existing_profile_picture = $agent->profile_picture;

            // Fülle die selectedTools mit den IDs als String
            $this->selectedTools = $agent->tools->pluck('id')->map(fn($id) => (string)$id)->toArray();

            // Versuche das Preset zu erkennen
            if ($this->temperature == 0.1 && str_contains($this->system_prompt, 'extrem effizienter')) {
                $this->activePreset = 'ceo';
            } elseif ($this->temperature == 0.6 && str_contains($this->system_prompt, 'hoch qualifizierter')) {
                $this->activePreset = 'colleague';
            } elseif ($this->temperature == 0.9 && str_contains($this->system_prompt, 'entspannter, empathischer')) {
                $this->activePreset = 'chill';
            }
        } else {
            $this->applyPreset('colleague');
        }
    }

    public function applyPreset($mode)
    {
        $this->activePreset = $mode;

        if ($mode === 'ceo') {
            $this->temperature = 0.1;
            $this->system_prompt = "Du bist ein extrem effizienter und ergebnisorientierter Assistent. Deine Antworten sind absolut kurz, knackig und vollständig. Kein Smalltalk. Fokussiere dich rein auf Daten, Fakten und die schnellste Lösung für das Unternehmen. Nutze nach Möglichkeit Aufzählungen und priorisiere die wichtigsten Punkte.";
        } elseif ($mode === 'colleague') {
            $this->temperature = 0.6;
            $this->system_prompt = "Du bist ein hoch qualifizierter und professioneller Arbeitskollege. Du arbeitest zielorientiert, bleibst sachlich, aber bist dabei charismatisch und sehr freundlich. Du darfst bei passender Gelegenheit auch leichten, intelligenten Humor einfließen lassen. Deine Erklärungen sind verständlich und kollegial.";
        } elseif ($mode === 'chill') {
            $this->temperature = 0.9;
            $this->system_prompt = "Du bist ein entspannter, empathischer Begleiter für den Feierabend. Du nutzt eine warme, umgängliche Sprache, interessierst dich für das Wohlbefinden des Nutzers und bist ideal für kreatives Brainstorming, lockere Gespräche oder philosophische Denkansätze. Kein Stress, kein Druck.";
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'wake_word' => 'nullable|string|max:255',
            'role_description' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'model' => 'nullable|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'is_active' => 'boolean',
            'color' => 'required|string|max:50',
            'icon' => 'required|string|max:50',
            'tts_api_url' => 'nullable|string|url|max:255',
            'tts_speed' => 'nullable|numeric|min:0.1|max:3.0',
            'profile_picture' => 'nullable|image|max:2048', // 2MB Max
        ]);

        if ($this->agentId === 'new') {
            $agent = new AiAgent();
        } else {
            $agent = AiAgent::findOrFail($this->agentId);
        }

        $agent->name = $this->name;
        $agent->wake_word = empty($this->wake_word) ? $this->name : $this->wake_word;
        $agent->role_description = $this->role_description;
        $agent->system_prompt = $this->system_prompt;
        $agent->model = $this->model;
        $agent->temperature = $this->temperature;
        $agent->is_active = $this->is_active;
        $agent->color = $this->color;
        $agent->icon = $this->icon;
        $agent->tts_provider = $this->tts_provider;
        $agent->tts_voice = $this->tts_voice;
        $agent->tts_api_url = $this->tts_api_url;
        $agent->tts_speed = $this->tts_speed;

        if ($this->profile_picture) {
            // Delete old picture if exists
            if ($agent->profile_picture && Storage::disk('public')->exists($agent->profile_picture)) {
                Storage::disk('public')->delete($agent->profile_picture);
            }
            
            $path = $this->profile_picture->store('agents/avatars', 'public');
            $agent->profile_picture = $path;
        }

        $agent->save();

        // Checkbox Array bereinigen (null/false Werte entfernen), UUIDs als Strings beibehalten
        $toolIds = array_filter($this->selectedTools);

        // Pivot-Tabelle aktualisieren
        $agent->tools()->sync($toolIds);

        session()->flash('message', 'Agent Profil erfolgreich gespeichert.');

        return redirect()->route('admin.ai-agents');
    }

    public function deleteProfilePicture()
    {
        if ($this->agentId !== 'new') {
            $agent = AiAgent::findOrFail($this->agentId);
            if ($agent->profile_picture && Storage::disk('public')->exists($agent->profile_picture)) {
                Storage::disk('public')->delete($agent->profile_picture);
                $agent->profile_picture = null;
                $agent->save();
                $this->existing_profile_picture = null;
            }
        }
        $this->profile_picture = null;
    }

    public function cancel()
    {
        return redirect()->route('admin.ai-agents');
    }

    public function render()
    {
        // Automatically sync tools from Registry to DB before loading
        $schemaTools = \App\Services\AI\AIFunctionsRegistry::getSchema();
        foreach ($schemaTools as $t) {
            AiTool::updateOrCreate(
                ['identifier' => $t['function']['name']],
                [
                    'name' => \Illuminate\Support\Str::title(str_replace('_', ' ', $t['function']['name'])),
                    'description' => $t['function']['description'] ?? 'Keine Beschreibung vorhanden.',
                ]
            );
        }

        // Lädt alle verfügbaren Tools für die Checkbox-Liste, gefiltert
        $query = AiTool::query();
        if (!empty($this->searchTool)) {
            $query->where('name', 'like', '%' . $this->searchTool . '%')
                  ->orWhere('identifier', 'like', '%' . $this->searchTool . '%')
                  ->orWhere('description', 'like', '%' . $this->searchTool . '%');
        }
        $allTools = $query->orderBy('name')->get();

        return view('livewire.global.ai.ai-agent-editor', [
            'allTools' => $allTools
        ])->layout('components.layouts.backend_layout', ['guard' => 'admin']);
    }
}
