<?php

namespace App\Livewire\Shop\Ai;

use App\Models\Ai\AiAgent;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class AiAgentManager extends Component
{
    public string $themingDepartment = 'Agenten';
    public $pingResults = [];

    public function render()
    {
        $agents = AiAgent::orderByRaw("CASE WHEN name = 'Funkira' THEN 0 ELSE 1 END")->orderBy('name')->get();

        $contextLoads = [];
        foreach ($agents as $agent) {
            $contextLoads[$agent->id] = $this->calculateContextLoad($agent);
        }

        return view('livewire.shop.ai.ai-agent-manager', [
            'agents' => $agents,
            'contextLoads' => $contextLoads
        ]);
    }

    private function calculateContextLoad(AiAgent $agent): array
    {
        // Approximate context window based on the model name
        $maxTokens = 32000;
        $model = strtolower($agent->model ?? '');
        
        if (str_contains($model, 'gemini-3') || str_contains($model, 'gemini-1.5-pro')) {
            $maxTokens = 2000000;
        } elseif (str_contains($model, 'gemini')) {
            $maxTokens = 1000000;
        } elseif (str_contains($model, '120b') || str_contains($model, 'gpt-4')) {
            $maxTokens = 120000; // Leaving some padding
        } elseif (str_contains($model, 'ministral') || str_contains($model, 'devstral')) {
            $maxTokens = 32000;
        }

        // Sum up text that will be injected into every prompt
        $text = $agent->system_prompt ?? '';
        if ($agent->role) {
            $text .= $agent->role->name . ' ' . $agent->role->description;
        }

        // Simulate tool JSON schema injection
        $tools = $agent->tools;
        if ($tools && $tools->count() > 0) {
            $globalSchema = class_exists('\App\Services\AI\AIFunctionsRegistry') 
                ? \App\Services\AI\AIFunctionsRegistry::getSchema() : [];
            $allowedIdentifiers = $tools->pluck('identifier')->toArray();
            $filteredSchema = array_filter($globalSchema, function ($t) use ($allowedIdentifiers) {
                return in_array($t['function']['name'] ?? '', $allowedIdentifiers);
            });
            $text .= json_encode($filteredSchema);
        }

        // Formula: 1 Token ≈ 4 Characters
        $estimatedTokens = (int) ceil(mb_strlen($text) / 4);
        
        // Add fixed buffer for chat history & system overhead
        $estimatedTokens += 1500;

        $percentage = $maxTokens > 0 ? min(100, round(($estimatedTokens / $maxTokens) * 100)) : 0;

        if (class_exists(\App\Models\Ai\AiMetric::class)) {
            // Um Spam zu vermeiden: Nur einmal pro Stunde pro Agent loggen
            $lastLog = \App\Models\Ai\AiMetric::where('ai_agent_id', $agent->id)
                ->where('type', 'cognitive_load')
                ->where('created_at', '>=', now()->subHour())
                ->first();

            if (!$lastLog) {
                try {
                    \App\Models\Ai\AiMetric::create([
                        'ai_agent_id' => $agent->id,
                        'type' => 'cognitive_load',
                        'input_tokens' => $estimatedTokens,
                        'output_tokens' => 0,
                        'total_time_ms' => 0,
                        'is_success' => true
                    ]);
                } catch (\Exception $e) { }
            }
        }

        return [
            'tokens' => $estimatedTokens,
            'max' => $maxTokens,
            'percent' => $percentage
        ];
    }

    public function createAgent()
    {
        return redirect()->route('admin.ai-agents.editor', ['id' => 'new']);
    }

    public function editAgent($id)
    {
        return redirect()->route('admin.ai-agents.editor', ['id' => $id]);
    }

    public function syncAll()
    {
        $agents = AiAgent::all();
        foreach ($agents as $agent) {
            $this->pingTest($agent->id);
        }
    }

    public function pingTest($id)
    {
        $agent = AiAgent::find($id);
        if (!$agent) return;

        if (!$agent->is_active) {
            $this->pingResults[$id] = [
                'llm' => 'Inaktiv',
                'tts' => 'Inaktiv',
            ];
            return;
        }

        // Ping LLM
        $llmStatus = 'Fehler';
        try {
            $start = microtime(true);
            $modelStr = strtolower($agent->model ?? '');
            
            if (str_starts_with($modelStr, 'gemini')) {
                $llmUrl = config('services.gemini.url') ?: 'https://generativelanguage.googleapis.com/v1beta/openai/';
                $key = config('services.gemini.key');
            } else {
                $llmUrl = config('services.mittwald.url') ?: 'https://api.mittwald.example/v1';
                $key = config('services.mittwald.key');
            }
            
            $response = \Illuminate\Support\Facades\Http::timeout(3)->withToken($key)->get(rtrim($llmUrl, '/') . '/models');
            
            // If it returns 200, 401, or 404, the server is reachable
            if ($response->successful() || $response->status() === 401 || $response->status() === 404) {
                $llmStatus = round((microtime(true) - $start) * 1000) . 'ms';
            }
        } catch (\Exception $e) {
            $llmStatus = 'Offline';
        }

        // Ping TTS
        $ttsStatus = !$agent->tts_enabled ? 'Deaktiviert' : 'Inaktiv';
        if ($agent->tts_enabled && $agent->tts_provider && $agent->tts_provider !== 'none') {
            try {
                $start = microtime(true);
                $ttsUrl = $agent->tts_api_url ?: 'http://127.0.0.1:8020';
                $response = \Illuminate\Support\Facades\Http::timeout(3)->get(rtrim($ttsUrl, '/') . '/languages');
                if ($response->successful() || $response->status() === 404) {
                    // Even a 404 means the server is reachable and instantly replied
                    $ttsStatus = round((microtime(true) - $start) * 1000) . 'ms';
                } else {
                    $ttsStatus = 'Fehler';
                }
            } catch (\Exception $e) {
                $ttsStatus = 'Offline';
            }
        }

        $this->pingResults[$id] = [
            'llm' => $llmStatus,
            'tts' => $ttsStatus,
        ];
    }
}
