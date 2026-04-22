<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiTool;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.backend_layout')]
class AiAgentEditor extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Agenten';
    use WithFileUploads;

    public $agentId;
    public $ai_role_id = null;
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
    public $tts_enabled = false;
    public $tts_provider = 'toni_xttsv2';
    public $tts_voice = '';
    public $tts_api_url = '';
    public $tts_speed = 1.0;
    
    public $telegram_bot_token = '';
    public $telegram_allowed_chat_ids = '';

    public $existing_profile_picture = null;
    public $profile_picture;
    public $inheritedDept = null;

    // Validierte Paletten
    public $availableColors = [
        'cyan-500', 'emerald-500', 'blue-500', 'indigo-500', 
        'purple-500', 'pink-500', 'rose-500', 'red-500', 
        'orange-500', 'amber-500', 'yellow-500', 'green-500',
        'sky-500', 'primary'
    ];

    public $availableModels = [
        'gpt-oss-120b' => 'GPT-OSS 120B',
        'Ministral-3-14B-Instruct-2512' => 'Ministral 3 14B',
        'Devstral-Small-2-24B-Instruct-2512' => 'Devstral Small 2 24B',
        'Qwen3-Embedding-8B' => 'Qwen3 Embedding 8B',
        'whisper-large-v3-turbo' => 'Whisper Large v3 Turbo',
        'gemini-2.5-pro' => 'Google Gemini 2.5 Pro (Standard)',
        'gemini-2.0-flash' => 'Google Gemini 2.0 Flash',
        'gemini-3.1-pro-preview' => 'Google Gemini 3.1 Pro Preview'
    ];

    public $ttsProviders = [
        'toni_xttsv2' => 'Toni - Coqui XTTSv2',
        'gemini_native' => 'Google Gemini (Native TTS)',
        'browser_tts' => 'Standard Speech (Browser)',
        'none' => 'Deaktiviert (Nur Text)'
    ];

    public $ttsVoices = [
        'toni_xttsv2' => [], // Dynamische Keys
        'gemini_native' => [
            'Puck' => 'Puck (Neutral)',
            'Charon' => 'Charon (Tief)',
            'Kore' => 'Kore (Sanft)',
            'Fenrir' => 'Fenrir (Energetisch)',
            'Aoede' => 'Aoede (Ruhig)',
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
        ],
        'gemini-2.5-pro' => [
            'type' => 'Chat + Reasoning + Vision', 
            'capabilities' => 'Text, Bild, Tool-Calling', 
            'context' => '2.000.000 Token', 
            'license' => 'Google Proprietary',
            'use_cases' => ['Extreme Datenanalysen', 'Sehr tiefe Code-Erstellung', 'Auswertung gigantischer Kontextmengen']
        ],
        'gemini-2.0-flash' => [
            'type' => 'Chat + Vision', 
            'capabilities' => 'Text, Bild, Tool-Calling', 
            'context' => '1.000.000 Token', 
            'license' => 'Google Proprietary',
            'use_cases' => ['Blitzschneller Kundenchat', 'Hohe Frequenz, geringe Kosten', 'Alltags-Begleiter']
        ],
        'gemini-3.1-pro-preview' => [
            'type' => 'Chat + Reasoning + Vision (State of the Art)', 
            'capabilities' => 'Text, Bild, Video, Tool-Calling', 
            'context' => '2.000.000+ Token', 
            'license' => 'Google Proprietary',
            'use_cases' => ['Komplexeste Analysen', 'Erstellung von Architektur-Code', 'Aufwendiges Multi-Agent reasoning']
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

    public $contextLoad = ['tokens' => 0, 'max' => 32000, 'percent' => 0];

    #[\Livewire\Attributes\On('edit-agent')]
    public function openEditFromWorkspace($id = 'new')
    {
        $this->mount($id ?: 'new');
    }

    public function mount($id = 'new')
    {
        $this->agentId = $id;

        if ($id !== 'new') {
            $agent = AiAgent::findOrFail($id);
            $this->name = $agent->name;
            $this->ai_role_id = $agent->ai_role_id;
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
            
            // If assigned to a department, inherit color/icon virtually in the Editor UI
            $agent->load('department');
            if ($agent->department) {
                $this->inheritedDept = $agent->department->toArray();
                $this->color = $agent->department->color;
                $this->icon = $agent->department->icon;
            }
            
            $this->tts_enabled = (bool) ($agent->tts_enabled ?? false);
            $this->tts_provider = $agent->tts_provider ?? 'toni_xttsv2';
            


            $this->tts_voice = $agent->tts_voice ?? '';
            $this->tts_api_url = $agent->tts_api_url ?? '';
            $this->tts_speed = $agent->tts_speed ?? 1.0;
            
            $this->telegram_bot_token = $agent->telegram_bot_token ?? '';
            $this->telegram_allowed_chat_ids = is_array($agent->telegram_allowed_chat_ids) 
                                                ? implode(', ', $agent->telegram_allowed_chat_ids) 
                                                : ($agent->telegram_allowed_chat_ids ?? '');
            
            $this->existing_profile_picture = $agent->profile_picture;

            // Versuche das Preset anhand der Temperatur zu erkennen
            if ($this->temperature <= 0.3) {
                $this->activePreset = 'ceo';
            } elseif ($this->temperature <= 0.7) {
                $this->activePreset = 'colleague';
            } else {
                $this->activePreset = 'chill';
            }
        } else {
            $this->applyPreset('colleague');
        }

        $this->updateContextLoad();
    }

    public function updatedAiRoleId($value)
    {
        if ($value) {
            $role = \App\Models\Ai\AiRole::find($value);
            if ($role) {
                $this->role_description = $role->description;
            }
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
        $this->updateContextLoad();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['model', 'system_prompt', 'ai_role_id'])) {
            $this->updateContextLoad();
        }
    }

    public function updateContextLoad()
    {
        if ($this->agentId === 'new' && empty($this->system_prompt)) {
            $this->contextLoad = ['tokens' => 0, 'max' => 32000, 'percent' => 0];
            return;
        }

        $maxTokens = 32000;
        $modelStr = strtolower($this->model ?? '');
        
        if (str_contains($modelStr, 'gemini-3') || str_contains($modelStr, 'gemini-1.5-pro')) {
            $maxTokens = 2000000;
        } elseif (str_contains($modelStr, 'gemini')) {
            $maxTokens = 1000000;
        } elseif (str_contains($modelStr, '120b') || str_contains($modelStr, 'gpt-4')) {
            $maxTokens = 120000;
        } elseif (str_contains($modelStr, 'ministral') || str_contains($modelStr, 'devstral')) {
            $maxTokens = 32000;
        }

        $text = $this->system_prompt ?? '';
        if ($this->ai_role_id) {
            $role = \App\Models\Ai\AiRole::find($this->ai_role_id);
            if ($role) {
                $text .= $role->name . ' ' . $role->description;
            }
        }

        if ($this->agentId !== 'new') {
            $agent = AiAgent::find($this->agentId);
            if ($agent && $agent->tools && $agent->tools->count() > 0) {
                $text .= str_repeat("TOOLSCHEMA ", $agent->tools->count() * 10); // Approximation
            }
        }

        $estimatedTokens = (int) ceil(mb_strlen($text) / 4);
        $estimatedTokens += 1500; // Basic overhead

        $percentage = $maxTokens > 0 ? min(100, round(($estimatedTokens / $maxTokens) * 100)) : 0;

        $this->contextLoad = [
            'tokens' => $estimatedTokens,
            'max' => $maxTokens,
            'percent' => $percentage
        ];
    }

    public function save()
    {
        $this->validate([
            'ai_role_id' => 'nullable|exists:ai_roles,id',
            'name' => 'required|string|max:255',
            'wake_word' => 'nullable|string|max:255',
            'role_description' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'model' => 'nullable|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'is_active' => 'boolean',
            'color' => 'required|string|max:50',
            'icon' => 'required|string|max:50',
            'tts_enabled' => 'boolean',
            'tts_api_url' => 'nullable|string|url|max:255',
            'tts_speed' => 'nullable|numeric|min:0.1|max:3.0',
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_allowed_chat_ids' => 'nullable|string|max:1000',
            'profile_picture' => 'nullable|image|max:2048', // 2MB Max
        ]);

        if ($this->agentId === 'new') {
            $agent = new AiAgent();
        } else {
            $agent = AiAgent::findOrFail($this->agentId);
        }

        $agent->name = $this->name;
        $agent->ai_role_id = $this->ai_role_id;
        $agent->wake_word = empty($this->wake_word) ? $this->name : $this->wake_word;
        $agent->role_description = $this->role_description;
        $agent->system_prompt = $this->system_prompt;
        $agent->model = $this->model;
        $agent->temperature = $this->temperature;
        $agent->is_active = $this->is_active;
        $agent->color = $this->color;
        $agent->icon = $this->icon;
        $agent->tts_enabled = $this->tts_enabled;
        $agent->tts_provider = $this->tts_provider;
        $agent->tts_voice = $this->tts_voice;
        $agent->tts_api_url = $this->tts_api_url;
        $agent->tts_speed = $this->tts_speed;
        $agent->telegram_bot_token = $this->telegram_bot_token;
        
        if (!empty(trim($this->telegram_allowed_chat_ids))) {
            $agent->telegram_allowed_chat_ids = array_map('trim', explode(',', $this->telegram_allowed_chat_ids));
        } else {
            $agent->telegram_allowed_chat_ids = [];
        }

        if ($this->profile_picture) {
            // Delete old picture if exists
            if ($agent->profile_picture && Storage::disk('public')->exists($agent->profile_picture)) {
                Storage::disk('public')->delete($agent->profile_picture);
            }
            
            $path = $this->profile_picture->store('agenten/avatars', 'public');
            $agent->profile_picture = $path;
        }

        $agent->save();

        session()->flash('message', 'Agent Profil erfolgreich gespeichert.');

        $this->dispatch('close-agent-manager');
        $this->dispatch('$refresh');
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
        $this->dispatch('close-agent-manager');
    }

    public function render()
    {
        return view('livewire.shop.ai.ai-agent-editor', [
            'aiRoles' => \App\Models\Ai\AiRole::orderBy('name')->get()
        ]);
    }
}
