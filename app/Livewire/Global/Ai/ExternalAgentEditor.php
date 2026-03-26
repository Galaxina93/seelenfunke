<?php

namespace App\Livewire\Global\Ai;

use Livewire\Attributes\Layout;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[Layout('components.layouts.backend_layout')]
class ExternalAgentEditor extends Component
{
    public $agentId;
    public $system_prompt = '';
    public $voice_preset = '';
    public $llm_hoster = '';
    public $llm_model = '';
    public $temperature = 0.7;

    public $voices = [];
    public $connectionError = null;
    public $isSaving = false;
    public $saveSuccess = false;
    public $temperature_fallback = 0.6; // initial default for fallback

    public $toniUrl;

    public function mount($agentId)
    {
        $this->toniUrl = env('TONI_AI_URL', 'http://192.168.188.32:8000');
        $this->agentId = $agentId;
        $this->fetchConfig();
        $this->fetchVoices();
    }

    public function fetchConfig()
    {
        try {
            $response = Http::timeout(5)->withToken(env('TONI_AI_API_KEY'))->get($this->toniUrl . '/api/toni/config');
            if ($response->successful()) {
                $data = $response->json();
                $this->system_prompt = $data['system_prompt'] ?? '';
                $this->voice_preset = $data['voice_preset'] ?? '';
                $this->llm_hoster = $data['llm_hoster'] ?? '';
                $this->llm_model = $data['llm_model'] ?? '';
                $this->temperature = $data['temperature'] ?? 0.7;
                $this->connectionError = null;
            } else {
                $this->connectionError = 'Toni antwortet nicht korrekt (' . $response->status() . ').';
            }
        } catch (\Exception $e) {
            $this->connectionError = 'Toni ist offline oder nicht erreichbar.';
        }
    }

    public function fetchVoices()
    {
        try {
            $response = Http::timeout(5)->withToken(env('TONI_AI_API_KEY'))->get($this->toniUrl . '/api/toni/voices');
            if ($response->successful()) {
                $data = $response->json();
                $this->voices = $data['voices'] ?? [];
            }
        } catch (\Exception $e) {
            Log::warning('Konnt Stimmen von Toni nicht abrufen: ' . $e->getMessage());
        }
    }

    public function setPreset($type)
    {
        if ($type === 'ceo') {
            $this->temperature = 0.2;
            $this->system_prompt = "Du bist ein extrem effizienter und ergebnisorientierter Assistent. Deine Antworten sind absolut kurz, knackig und vollständig. Kein Smalltalk. Fokussiere dich rein auf Daten, Fakten und die schnellste Lösung für das Unternehmen. Nutze nach Möglichkeit Aufzählungen und priorisiere die wichtigsten Punkte.";
        } elseif ($type === 'kollege') {
            $this->temperature = 0.6;
            $this->system_prompt = "Du bist ein professioneller, aber herzlicher und zugänglicher Kollege. Du unterstützt bei Aufgaben präzise und lösungsorientiert. Gelegentlich lockerst du Gespräche mit einem feinen, professionellen Humor auf. Bleib stets hilfsbereit und verständnisvoll.";
        } elseif ($type === 'feierabend') {
            $this->temperature = 0.9;
            $this->system_prompt = "Du bist ein entspannter Begleiter für den Feierabend. Du hilfst stressfrei bei alltäglichen Überlegungen, hast eine lockere, fast freundschaftliche Tonalität und verurteilst niemanden. Perfekt für entspanntes Brainstorming und lockeres Geplauder.";
        }
    }

    public function saveConfig()
    {
        $this->isSaving = true;
        $this->saveSuccess = false;

        $payload = [
            'system_prompt' => $this->system_prompt,
            'voice_preset' => $this->voice_preset,
            'llm_hoster' => $this->llm_hoster,
            'llm_model' => $this->llm_model,
            'temperature' => (float) $this->temperature,
        ];

        try {
            $response = Http::timeout(10)->withToken(env('TONI_AI_API_KEY'))->patch($this->toniUrl . '/api/toni/config', $payload);
            
            if ($response->successful()) {
                $this->saveSuccess = true;
                $this->connectionError = null;
            } else {
                $this->connectionError = 'Fehler beim Speichern der Konfiguration.';
            }
        } catch (\Exception $e) {
            $this->connectionError = 'Verbindungsabbruch zu Toni während des Speicherns.';
        }

        $this->isSaving = false;
    }

    public function render()
    {
        return view('livewire.global.ai.external-agent-editor');
    }
}
