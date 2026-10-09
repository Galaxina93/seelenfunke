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
    public $model = 'gemini-3.8-flash';
    public $temperature = 0.4;
    public $activePreset = null; // Tracks the currently clicked preset button
    public $is_active = true;
    public $color = 'cyan-500';
    public $icon = 'sparkles'; // Default to a heroicon name
    public $tts_enabled = false;
    public $tts_provider = 'gemini_native';
    public $tts_voice = '';
    public $tts_api_url = '';
    public $tts_speed = 1.0;
    
    public $telegram_bot_token = '';
    public $telegram_allowed_chat_ids = '';

    public $existing_profile_picture = null;
    public $profile_picture;
    public $inheritedDept = null;
    public $savedMessage = null;

    // Validierte Paletten
    public $availableColors = [
        'cyan-500', 'emerald-500', 'blue-500', 'indigo-500', 
        'purple-500', 'pink-500', 'rose-500', 'red-500', 
        'orange-500', 'amber-500', 'yellow-500', 'green-500',
        'sky-500', 'primary'
    ];

    public $availableModels = [
        'gemini-3.8-flash' => 'Google Gemini 3.8 Flash (Flaggschiff / Empfohlen)',
        'gemini-3.1-pro' => 'Google Gemini 3.1 Pro (Deep Reasoning, Code & Audit)',
        'gemini-3.5-flash-lite' => 'Google Gemini 3.5 Flash-Lite (Ultra-Speed)',
        'gemini-3.5-flash' => 'Google Gemini 3.5 Flash (Standard)',
        'gemini-2.5-flash' => 'Google Gemini 2.5 Flash (Legacy)'
    ];

    public $ttsProviders = [
        'gemini_native' => 'Google Gemini (Native TTS)',
        'browser_tts' => 'Standard Speech (Browser)',
        'none' => 'Deaktiviert (Nur Text)'
    ];

    public $ttsVoices = [
        'gemini_native' => [
            // Weibliche Stimmen
            'Aoede' => 'Aoede (Weiblich: Natürlich, Entspannt)',
            'Kore' => 'Kore (Weiblich: Bestimmt, Autoritär, CEO)',
            'Zephyr' => 'Zephyr (Weiblich: Frisch, Freundlich, Strahlend)',
            'Callirrhoe' => 'Callirrhoe (Weiblich: Sanft, Empathisch, Deeskalierend)',
            'Autonoe' => 'Autonoe (Weiblich: Klar, Hell, Strukturiert)',
            'Laomedeia' => 'Laomedeia (Weiblich: Lebhaft, Mitreißend, Werblich)',
            'Despina' => 'Despina (Weiblich: Melodisch, Geschmeidig)',
            'Erinome' => 'Erinome (Weiblich: Eloquent, Artikuliert, Klar)',
            'Leda' => 'Leda (Weiblich: Jugendlich, Energisch)',
            'Achernar' => 'Achernar (Weiblich: Weich, Warmherzig)',
            'Gacrux' => 'Gacrux (Weiblich: Beruhigend, Vertrauensvoll)',
            'Pulcherrima' => 'Pulcherrima (Weiblich: Elegant, Vornehm)',

            // Männliche Stimmen
            'Puck' => 'Puck (Männlich: Optimistisch, Agil, Direkt)',
            'Charon' => 'Charon (Männlich: Tief, Sonor, Intellektuell-Ruhig)',
            'Fenrir' => 'Fenrir (Männlich: Kraftvoll, Leidenschaftlich, Energetisch)',
            'Orus' => 'Orus (Männlich: Streng, Akkurat, Geschäftsmäßig Fest)',
            'Algieba' => 'Algieba (Männlich: Charismatisch, Geschmeidig, Überzeugend)',
            'Iapetus' => 'Iapetus (Männlich: Klar, Resonant, Vertrauenswürdig)',
            'Sadachbia' => 'Sadachbia (Männlich: Technisch, Streng Monoton, Digital)',
            'Umbriel' => 'Umbriel (Männlich: Lässig, Weltgewandt, Entspannt)',
            'Alnilam' => 'Alnilam (Männlich: Fokussiert, Sicherheitsbewusst, Fest)',
            'Schedar' => 'Schedar (Männlich: Sachlich, Gleichmäßig, Unaufgeregt)',
            'Enceladus' => 'Enceladus (Männlich: Sanft, Hauchig, Bedächtig)',
            'Algenib' => 'Algenib (Männlich: Reif, Rau, Lebenserfahren)',
            'Rasalgethi' => 'Rasalgethi (Männlich: Analytisch, Detailfokussiert)',
            'Zubenelgenubi' => 'Zubenelgenubi (Männlich: Sehr Tief, Gravitätisch)'
        ]
    ];

    public $modelDetails = [
        'gemini-3.8-flash' => [
            'type' => 'Chat + Vision + Reasoning (Flaggschiff)', 
            'capabilities' => 'Text, Bild, Audio, Video, Tool-Calling', 
            'context' => '1.000.000 Token', 
            'license' => 'Google Proprietary',
            'use_cases' => ['Neueste State-of-the-Art Flash Architektur', 'Blitzschnelle Tool-Ausführung & Reaktionszeiten', 'Multimodale Alltags- und Führungsaufgaben']
        ],
        'gemini-3.1-pro' => [
            'type' => 'Chat + Deep Reasoning + Code (Pro Grade)', 
            'capabilities' => 'Text, Bild, Video, Advanced Tool-Calling', 
            'context' => '2.000.000+ Token', 
            'license' => 'Google Proprietary',
            'use_cases' => ['Tiefe Finanz-, Steuer- und Rechtsanalysen', 'Komplexeste System-Architektur & Programmierung', 'Fehlerfreie mathematische und logische Deduktion']
        ],
        'gemini-3.5-flash-lite' => [
            'type' => 'Chat + Vision (Ultra-High Throughput)', 
            'capabilities' => 'Text, Bild, Tool-Calling', 
            'context' => '1.000.000 Token', 
            'license' => 'Google Proprietary',
            'use_cases' => ['Extrem geringe Latenz', 'Sehr hohe Taktung & Vorratsprüfung', 'Kosteneffiziente Standardabfragen']
        ],
        'gemini-3.5-flash' => [
            'type' => 'Chat + Vision (Next-Gen GA)', 
            'capabilities' => 'Text, Bild, Video, Tool-Calling', 
            'context' => '1.000.000 Token', 
            'license' => 'Google Proprietary',
            'use_cases' => ['Sehr gute Allround-Performance', 'Schnelle Tool-Ausführung', 'Zuverlässiger Standard-Betrieb']
        ],
        'gemini-2.5-flash' => [
            'type' => 'Chat + Vision (Legacy)', 
            'capabilities' => 'Text, Bild, Tool-Calling', 
            'context' => '1.000.000 Token', 
            'license' => 'Google Proprietary',
            'use_cases' => ['Rückwärtskompatibilität']
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
            $this->tts_provider = $agent->tts_provider ?? 'gemini_native';
            


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

    public function updatedModel($value)
    {
        $this->model = $value;
        $this->updateContextLoad();
        $this->autoSaveModelOrVoice();
    }

    public function updatedTtsVoice($value)
    {
        $this->tts_voice = $value;
        $this->autoSaveModelOrVoice();

        if ($this->tts_enabled && $this->tts_provider !== 'none') {
            $this->playVoiceSample($value);
        }
    }

    public function playVoiceSample($voice = null)
    {
        $voice = $voice ?: $this->tts_voice;
        if (empty($voice)) {
            $voice = 'Aoede';
        }

        $sampleText = "Guten Tag! Dies ist eine Hörprobe meiner Stimme für deinen KI-Agenten.";

        if ($this->tts_provider === 'gemini_native') {
            $wavBase64 = \App\Services\AI\GeminiTtsService::synthesizeWav($sampleText, $voice);
            if ($wavBase64) {
                $this->dispatch('play-voice-sample', audio: 'data:audio/wav;base64,' . $wavBase64, voice: $voice);
                return;
            }
        }

        // Browser fallback
        $this->dispatch('play-browser-voice-sample', text: $sampleText, speed: $this->tts_speed ?: 1.0, voice: $voice);
    }

    public function updatedTtsProvider($value)
    {
        $this->tts_provider = $value;
        $availableVoices = array_keys($this->ttsVoices[$value] ?? []);
        if (!empty($availableVoices) && !in_array($this->tts_voice, $availableVoices)) {
            $this->tts_voice = $availableVoices[0];
        }
        $this->autoSaveModelOrVoice();
    }

    public function updatedTtsEnabled($value)
    {
        $this->tts_enabled = (bool) $value;
        $this->autoSaveModelOrVoice();
    }

    public function autoSaveModelOrVoice()
    {
        $voiceLabel = $this->getActiveVoiceLabel();

        if ($this->agentId && $this->agentId !== 'new') {
            $agent = AiAgent::find($this->agentId);
            if ($agent) {
                $agent->model = $this->model;
                $agent->tts_enabled = (bool) $this->tts_enabled;
                $agent->tts_provider = $this->tts_provider;
                $agent->tts_voice = empty($this->tts_voice) ? null : $this->tts_voice;
                $agent->save();
            }
        }

        $this->savedMessage = "Gespeichert • Aktive Stimme: {$voiceLabel}";
        $this->dispatch('agent-saved', message: $this->savedMessage);
    }

    public function getActiveVoiceLabel(): string
    {
        if (!$this->tts_enabled || $this->tts_provider === 'none') {
            return 'Deaktiviert';
        }
        if (!empty($this->tts_voice)) {
            return $this->ttsVoices[$this->tts_provider][$this->tts_voice] ?? $this->tts_voice;
        }
        return 'Standard';
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
        
        if (str_contains($modelStr, 'gemini-3') || str_contains($modelStr, 'gemini-2.5-pro')) {
            $maxTokens = 2000000;
        } elseif (str_contains($modelStr, 'gemini')) {
            $maxTokens = 1000000;
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
        $agent->tts_voice = empty($this->tts_voice) ? null : $this->tts_voice;
        $agent->tts_api_url = empty($this->tts_api_url) ? null : $this->tts_api_url;
        $agent->tts_speed = $this->tts_speed;
        $agent->telegram_bot_token = empty($this->telegram_bot_token) ? null : $this->telegram_bot_token;
        
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
            $this->existing_profile_picture = $path;
            $this->profile_picture = null;
        }

        $agent->save();

        if ($this->agentId === 'new') {
            $this->agentId = $agent->id;
        }

        $this->savedMessage = 'Gespeichert';
        $this->dispatch('agent-saved', message: 'Gespeichert');
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
        return redirect()->route('admin.ai-company-structure');
    }

    public function render()
    {
        return view('livewire.shop.ai.ai-agent-editor', [
            'aiRoles' => \App\Models\Ai\AiRole::orderBy('name')->get()
        ]);
    }
}
