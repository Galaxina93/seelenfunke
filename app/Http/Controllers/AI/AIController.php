<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AI\AIFunctionsRegistry;
use App\Models\Funki\FunkiraChatMemory;

class AIController extends Controller
{
    /**
     * Retrieve the JSON Schema describing all available AI Tools.
     */
    public function schema(Request $request)
    {
        // Here you would implement rudimentary security check. 
        // Example: if($request->header('X-AI-TOKEN') !== env('AI_SECRET')) return abort(403);

        return response()->json([
            'status' => 'success',
            'tools' => AIFunctionsRegistry::getSchema(),
        ]);
    }

    /**
     * Execute an AI Tool call coming from Ollama/Python Script.
     */
    public function execute(Request $request)
    {
        // Example Payload: 
        // { "function": "get_system_health", "args": { "param1": "val1" } }
        
        $request->validate([
            'function' => 'required|string',
            'args' => 'sometimes|array'
        ]);

        $functionName = $request->input('function');
        $args = $request->input('args', []);

        try {
            // Forward execution to the registry
            $result = AIFunctionsRegistry::execute($functionName, $args);

            return response()->json([
                'status' => 'success',
                'function' => $functionName,
                'result' => $result
            ]);

        } catch (\InvalidArgumentException $e) {
            // Function not found
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
            
        } catch (\Exception $e) {
            // Internal execution error
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Frontend Endpoint: Receives a conversation history, sends it to the Agent, and returns the response.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'prompt' => 'sometimes|string|max:1000',
            'history' => 'sometimes|array'
        ]);

        $history = $request->input('history', []);
        
        // Backwards compatibility or single-shot prompts
        if (empty($history) && $request->has('prompt')) {
            $history[] = [
                'role' => 'user',
                'content' => $request->input('prompt')
            ];
        }

        $map = [
            '/admin/dashboard' => 'Dashboard / Startseite',
            '/admin/funki-routine' => 'Morgenroutine',
            '/admin/funki-todos' => 'Todo-Listenverwaltung',
            '/admin/funki-kalender' => 'Firmenkalender / Termine',
            '/admin/company-map' => 'Firmenstruktur & Partner',
            '/admin/tickets' => 'Kundensupport (Tickets)',
            '/admin/knowledge_base' => 'Wiki / Wissensdatenbank',
            '/admin/user-management' => 'Benutzerverwaltung (Mitarbeiter)',
            '/admin/products' => 'Produkte / Shop-Artikel',
            '/admin/product-templates' => 'Produkt-Templates',
            '/admin/reviews' => 'Produkt-Bewertungen',
            '/admin/invoices' => 'Rechnungen',
            '/admin/credit-management' => 'Gutschriftenverwaltung',
            '/admin/orders' => 'Bestellungen',
            '/admin/quote-requests' => 'Angebotsanfragen (Quotes)',
            '/admin/financial-evaluation' => 'Finanzübersicht & Auswertung',
            '/admin/financial-liquidity-planning' => 'Liquiditätsplanung',
            '/admin/financial-banks' => 'Bankkonten & Liquidität',
            '/admin/financial-fix-costs' => 'Fixkosten',
            '/admin/financial-variable-costs' => 'Variable Kosten / Sonderausgaben',
            '/admin/financial-tax' => 'Steuern Export & Tresor',
            '/admin/configuration' => 'System-Einstellungen',
            '/admin/blog' => 'Blog-Beiträge',
            '/admin/voucher' => 'Gutscheine / Rabattcodes',
            '/admin/newsletter' => 'Newsletter-Verwaltung',
            '/admin/right-management' => 'Rechte & Rollen',
        ];

        $navMap = "Verfügbare Admin-Routen & Bezeichnungen:\n";
        foreach ($map as $path => $name) {
            $navMap .= "- " . $name . " => " . $path . "\n";
        }

        $history[] = [
            'role' => 'system',
            'content' => "WICHTIG ZUR NAVIGATION: Wenn du das Tool `open_nav_item` einsetzt, wähle IMMER nur eine exakte Route aus dieser Liste. Erfinde und rate NIEMALS fremde URLs! Nutze ausschließlich diese:\n" . $navMap . "\n" .
                         "ACHTUNG: Wenn Alina befiehlt das 'Zentrum' zu öffnen, dann MUSS zwingend das Tool `open_zentrum` ausgeführt werden! Vergiss in dem Fall `open_nav_item`!"
        ];

        $agent = new \App\Services\AI\MittwaldAgent();
        $result = $agent->ask($history);
        
        // Speichere finalen Dialog-Verlauf in der Datenbank
        $sessionId = session()->getId();
        
        // Was hat der User gesagt? (Finde die neuste User-Nachricht)
        $userMsg = collect($history)->reverse()->firstWhere('role', 'user');
        if ($userMsg) {
            FunkiraChatMemory::create([
                'session_id' => $sessionId,
                'role' => 'user',
                'content' => $userMsg['content'],
            ]);
        }

        // Was hat die KI final geantwortet?
        if (!empty($result['response'])) {
            FunkiraChatMemory::create([
                'session_id' => $sessionId,
                'role' => 'assistant',
                'content' => $result['response'],
            ]);
        }

        // Generiere ElevenLabs Audio (Base64) falls möglich
        $base64Audio = null;
        if (!empty($result['response'])) {
            $apiKey = env('ELEVENLABS_API_KEY');
            $voiceId = env('ELEVENLABS_VOICE_ID', '21m00Tcm4TlvDq8ikWAM');

            if (!empty($apiKey)) {
                try {
                    // Filtere Markdown und Tags (*, _, #, usw) heraus
                    $cleanText = preg_replace('/\[.*?\]/s', '', $result['response']);
                    $cleanText = preg_replace('/[*_#`~>]/', '', $cleanText);

                    $ttsResponse = \Illuminate\Support\Facades\Http::withHeaders([
                        'xi-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'audio/mpeg'
                    ])->timeout(10)->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                        'text' => $cleanText,
                        'model_id' => 'eleven_multilingual_v2',
                        'voice_settings' => [
                            'stability' => 0.5,
                            'similarity_boost' => 0.75,
                        ]
                    ]);

                    if ($ttsResponse->successful()) {
                        $base64Audio = base64_encode($ttsResponse->body());
                    } else {
                        // Auto-Fallback if the Voice ID does not exist on this account
                        $apiResponse = $ttsResponse->json();
                        if (($ttsResponse->status() === 400 || $ttsResponse->status() === 404) && str_contains($ttsResponse->body(), 'voice_not_found')) {
                            \Illuminate\Support\Facades\Log::warning("Voice {$voiceId} not found in AIController. Attempting auto-fallback.");
                            $voices = \Illuminate\Support\Facades\Http::withHeaders(['xi-api-key' => $apiKey])->get("https://api.elevenlabs.io/v1/voices")->json();
                            
                            if (!empty($voices['voices'])) {
                                $firstVoice = $voices['voices'][0]['voice_id'];
                                $retry = \Illuminate\Support\Facades\Http::withHeaders([
                                    'xi-api-key' => $apiKey,
                                    'Content-Type' => 'application/json',
                                    'Accept' => 'audio/mpeg'
                                ])->timeout(10)->post("https://api.elevenlabs.io/v1/text-to-speech/{$firstVoice}", [
                                    'text' => $cleanText,
                                    'model_id' => 'eleven_multilingual_v2',
                                    'voice_settings' => [
                                        'stability' => 0.5,
                                        'similarity_boost' => 0.75,
                                    ]
                                ]);
                                
                                if ($retry->successful()) {
                                    $base64Audio = base64_encode($retry->body());
                                } else {
                                    \Illuminate\Support\Facades\Log::warning("ElevenLabs Fallback Error in AIController: " . $retry->body());
                                }
                            }
                        } else {
                            \Illuminate\Support\Facades\Log::warning("ElevenLabs Error in AIController: " . $ttsResponse->body());
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("ElevenLabs TTS Fetch failed: " . $e->getMessage());
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'response' => $result['response'],
            'history' => $result['history'] ?? [],
            'context_data' => $result['context_data'] ?? [],
            'usage' => $result['usage'] ?? [],
            'audio' => $base64Audio
        ]);
    }
}
