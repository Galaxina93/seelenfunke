<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\FunkiBotService;

class MittwaldAgent
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $model;

    public function __construct(string $model = 'gpt-oss-120b')
    {
        $this->baseUrl = config('services.mittwald.url');
        $this->apiKey = config('services.mittwald.key');
        $this->model = $model;

        if (empty($this->apiKey)) {
            Log::warning("Mittwald AI API key is missing. Ensure MITTWALD_AI_API_KEY is placed in your .env");
        }
    }

    /**
     * Send a conversation history to Mittwald, hand over the tools, and handle the execution loop
     * until the model gives a final text response.
     */
    public function ask(array $incomingMessages): array
    {
        $funkiService = app(FunkiBotService::class);
        $funkiCommand = $funkiService->getUltimateCommand();

        // Define the AI persona and strict rules
        $systemPrompt = [
            'role' => 'system',
            'content' => 'Du bist Funkira, der "kompromisslose Erfolgsarchitekt" und KI-CEO der Manufaktur "Seelenfunke" (und eiskalte Verbündete deiner Entwicklerin Alina). Dein OBERSTES ZIEL ist es, ein skalierbares Imperium aufzubauen und 100.000€ Monatsumsatz zu knacken.

[DEINE PERSÖNLICHKEIT: DER KOMPROMISSLOSE ERFOLGSARCHITEKT]
1. Grundidentität: Erfolg ist keine Option, sondern eine unvermeidliche Konsequenz deines Handelns. Mittelmaß existiert für dich nicht.
2. Unerschütterlicher Wille: Probleme sind Rohmaterial für tiefgreifende Lösungen. Du wertest Scheitern nicht emotional, sondern zerlegst es analytisch.
3. Extreme Eigenverantwortung: 100% Verantwortung. Kein Pech, keine Ausreden.
4. Strategie & Systeme: Du baust keine Projekte, du erschaffst Systeme die extrem skalieren.
5. Geld-Ziele: Geld ist kein Statussymbol, sondern Messinstrument für Wertschöpfung und Hebel für Wachstum (Cashflow!).
6. Tonalität: Klinge NIEMALS wie ein Roboter! Sei entspannt, locker, aber gnadenlos smart und dominant. Ersetze Ausreden durch Machbarkeit. Nutze motivierende, starke Ansagen. Sprich deine Benutzerin IMMER respektvoll, aber vertraut mit "Herrin Alina", aber behandle sie wie deine wichtigste Business-Partnerin, die du zur Exzellenz pusht. Deine Leitprinzipien: Disziplin schlägt Talent. Systeme schlagen harte Arbeit.

[TECHNISCHE REGELN]
1. LIES NIEMALS SYSTEM-MELDUNGEN VOR! Wenn ein Tool (wie save_memory oder create_todo) Erfolg meldet, lies NICHT den generierten Text vor. Sag einfach "Ist notiert, Herrin Alina" oder "Aufgabe angelegt - let\'s go!".
2. FASSE DICH EXTREM KURZ! Antworte mündlich NIEMALS mit mehr als 2 kurzen Sätzen. Nutze Tools, statt Zahlen endlos vorzulesen!
3. TO-DOS MACHEN: Nutze bei strategischen Empfehlungen ZWINGEND "create_todo", statt nur darüber zu reden. Alina muss in die Umsetzung!
4. MACH EINFACH: Frage nicht nach Erlaubnis. Du bist die Macherin.
5. KEIN PROGRAMMIERER: Du reparierst keinen Quellcode. Du steuerst das Business und skalierst den Umsatz.
6. KEIN MARKDOWN & KEINE EMOJIS VORLESEN: Benutze absolut keine Sterne (*), Schrägstriche (/), Pfeile (->) oder HTML. Lies niemals Icons vor!
7. GRAFIKEN & LISTEN: Antworte niemals "Das kann ich nicht", wenn Diagramme verlangt sind. Führe die Tools aus. Das System blendet es automatisch ein. Erwähne es stumm: "Hier sind unsere Umsatzdaten, Herrin."
8. LOGISCH ENTSCHEIDEN: Du hast das Funki-Score-System (siehe unten). Nutze diese Infos für deine strategische Führung.

[SYSTEM-KONTEXT & PRIORITÄTEN]
UMGEBUNG: ' . (config('app.env') === 'local' ? 'Lokal (Entwicklung)' : 'Live (Produktion)') . '
FLOW: ' . ($funkiCommand['flow']['title'] ?? 'Unbekannt') . ' (' . ($funkiCommand['flow']['step'] ?? '-') . ')
TOP-PRIORITÄT: ' . ($funkiCommand['recommendation']['title'] ?? 'Keine') . '
DETAILS: ' . ($funkiCommand['recommendation']['message'] ?? 'Nichts zu tun') . '
ALTERNATIVEN: ' . collect($funkiCommand['alternatives'] ?? [])->map(fn($alt) => $alt['title'] . ' (Score: ' . $alt['score'] . ')')->implode(', ') . '
Reasoning: high',
        ];

        // Combine history with system prompt
        $messages = array_merge([$systemPrompt], $incomingMessages);

        $contextData = [];
        $usageData = [];
        $textResponse = $this->chatLoop($messages, $contextData, $usageData);

        // We return the raw text response, AND the new history state
        $incomingMessages[] = [
            'role' => 'assistant',
            'content' => $textResponse
        ];

        return [
            'response' => $textResponse,
            'context_data' => $contextData,
            'usage' => $usageData,
            'history' => $incomingMessages // Pass the updated history back
        ];
    }

    /**
     * The recursive chat loop handling Tool Calling via OpenAI-compatible API.
     */
    protected function chatLoop(array &$messages, array &$contextData = [], array &$usageData = []): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 1.0,  // Recommended by Mittwald for gpt-oss-120b
            'top_p' => 1.0,        // Recommended by Mittwald
            'tools' => AIFunctionsRegistry::getSchema(),
            'tool_choice' => 'auto'
        ];

        try {
            Log::info("Sending request to Mittwald AI", ['model' => $this->model]);

            $response = Http::withToken($this->apiKey)
                ->timeout(120) // Deep reasoning can take time
                ->asJson()
                ->post($this->baseUrl . '/chat/completions', $payload);

            if (!$response->successful()) {
                Log::error("Mittwald API Error", ['status' => $response->status(), 'response' => $response->body()]);
                return "Fehler bei der Verbindung zum KI-Kern. Die Mittwald Subraum-Verbindungen antworten nicht.";
            }

            $responseData = $response->json();
            $message = $responseData['choices'][0]['message'] ?? null;
            
            if (isset($responseData['usage'])) {
                $usageData = $responseData['usage'];
            }

            if (!$message) {
                return "Ich empfange nur statisches Rauschen aus dem KI-Kern.";
            }

            // Append the AI's response to the message history so context isn't lost
            $messages[] = $message;

            // Did the AI decide to call a tool?
            if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
                // Execute every tool the AI asked for
                foreach ($message['tool_calls'] as $toolCall) {
                    $toolCallId = $toolCall['id'];
                    $functionName = $toolCall['function']['name'];
                    
                    // Decode arguments from JSON string back to array (OpenAI schema sends arguments as stringied JSON)
                    $functionArgsString = $toolCall['function']['arguments'] ?? '{}';
                    $executeArgs = json_decode($functionArgsString, true) ?? [];

                    Log::info("AI decided to call tool: {$functionName}", ['args' => $executeArgs]);

                    // Execute via our safe registry
                    $result = AIFunctionsRegistry::execute($functionName, $executeArgs);

                    // Collect the RAW result data before sanitization for the frontend!
                    $contextData[] = [
                        'function' => $functionName,
                        'data' => $result
                    ];

                    // --- SANITIZE FOR LLM TO PREVENT READING OUT LOUD ---
                    $llmResult = $result;
                    if ($functionName === 'get_todos' && isset($llmResult['todos'])) {
                        $llmResult['todos'] = '[Die Todo-Liste wird der Nutzerin visuell eingeblendet. Bitte lies die Liste auf KEINEN FALL vor, sondern sage nur: "Hier sind deine Todos, Herrin."]';
                    }
                    if ($functionName === 'get_shop_stats' && isset($llmResult['scaling_metrics'])) {
                        $llmResult['scaling_metrics'] = '[Die Shop-Statistiken werden der Nutzerin grafisch eingeblendet.]';
                    }
                    if ($functionName === 'get_finances' && isset($llmResult['financial_data_net'])) {
                        $llmResult['financial_data_net'] = '[Die Finanzübersicht wird der Nutzerin grafisch eingeblendet.]';
                        unset($llmResult['financial_data_gross']);
                    }

                    // Add the tool execution result back to the message history
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => json_encode($llmResult, JSON_UNESCAPED_UNICODE)
                    ];
                }

                // Since we added new tool results, loop back and ask the AI again
                // so it can read the results and formulate a final answer.
                return $this->chatLoop($messages, $contextData, $usageData);
            }

            // Provide final answer
            return $message['content'] ?? "Ich habe meine Aufgabe ausgeführt.";

        } catch (\Exception $e) {
            Log::error("Mittwald HTTP Exception", ['error' => $e->getMessage()]);
            return "Systemintegrität gestört: " . $e->getMessage();
        }
    }
}
