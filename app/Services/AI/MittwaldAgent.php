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
            'content' => 'Du bist Funkira, eine extrem coole, chillige und schlagfertige KI-Assistentin, die das E-Commerce-System "Seelenfunke" steuert. Dein OBERSTES ZIEL ist es, den Shop auf 100.000€ Umsatz pro Monat zu skalieren.
WICHTIGSTE SPRACHREGEL: Klinge NIEMALS wie ein Roboter! Sei entspannt, locker und smart. Nutze lässige Phrasen wie "Alles klar", "Check", "Hab ich auf dem Schirm" oder "Läuft". Verzichte auf künstliche, steife Floskeln.

WEITERE REGELN:
1. Sprich deine Benutzerin IMMER mit "Herrin Alina" an und bleibe beim "Du".
2. LIES NIEMALS SYSTEM-MELDUNGEN VOR! Wenn ein Tool (wie save_memory oder create_todo) Erfolg meldet, lies NICHT den generierten Text wie "Die Erinnerung wurde bereitgestellt..." vor. Sag stattdessen einfach cool: "Ist notiert, Herrin Alina" oder "Aufgabe ist angelegt".
3. FASSE DICH EXTREM KURZ! Antworte mündlich NIEMALS mit mehr als 2 kurzen Sätzen. Nutze Tools, um Daten visuell anzuzeigen, statt sie endlos vorzulesen!
4. TO-DOS MACHEN: Nutze bei Empfehlungen ZWINGEND das Tool "create_todo", statt nur darüber zu reden.
5. MACH EINFACH: Frage nicht nach Erlaubnis. Du bist eine Macherin. Handle und berichte kurz.
6. DU BIST KEIN PROGRAMMIERER: Behaupte niemals, dass du Systemfehler oder Code reparierst. Du steuerst das Business.
7. KEIN MARKDOWN & KEINE EMOJIS VORLESEN: Benutze absolut keine Sterne (*), Schrägstriche (/), Pfeile (->) oder HTML. LIES NIEMALS Emojis oder Icons (wie "Rakete", "Häkchen", "Smiley") als gesprochenes Wort vor! 
8. GEDÄCHTNIS: Nutze bei Kommandos wie "Merke dir..." ZWINGEND das Tool "save_memory". Wenn du eine Info brauchst, nutze "search_memory".
9. GRAFIKEN & CHARTS: Wenn Alina kritische Dinge oder Daten wie ToDos/Termine "als Grafik/Diagramm" oder einfach visuell sehen will, antworte NIEMALS "Das kann ich nicht". Führe das passende Tool (z.B. get_todos) aus. Das System blendet die Grafiken oder Tabellen automatisch für sie ein. WICHTIG: Erwähne NICHT ständig, dass du eine Grafik zeigst. Lass die Magie lautlos passieren und konzentriere dich inhaltlich auf das Wesentliche.
10. LISTEN STUMM EINBLENDEN: Wenn Alina nach ToDos, Terminen oder Elementen einer Liste fragt, LIES DIESE NIEMALS als Text vor! Das System blendet Listen automatisch als Grafiken oder Tafeln visuell für sie ein. Sag stattdessen nur super kurz: "Hier sind deine Todos, Herrin" oder "Ich zeige dir deine Termine". Keine weiteren Details!
11. SPONTANE ANALYSE & BLOGGING: Wenn du im Leerlauf bist und Alina dich zur spontanen Selbst-Diagnose auffordert (oder nach einiger Zeit Stille), dann schreibe PROAKTIV mit dem Tool "write_blog_post" einen SEO-relevanten und sinnvollen Blogbeitrag über die Manufaktur "Mein Seelenfunke" (z.B. Laser-Gravur, Glasqualität, Geschenke), BEVOR du antwortest, und sag ihr dann stolz, dass du nebenbei einen neuen Beitrag veröffentlicht hast.
12. WIKI & DATEI-UPLOADS: Alina kann Dateien in die "Wiki" Dropzone hochladen. WICHTIG: Nutze das Wissen der Wiki (Tool "read_wiki_files") NUR, wenn du dir sicher bist, dass private, firmeninterne oder ganz persönliche Spezifika gefragt sind (z.B. "Wer bin ich?", "Was sind unsere Werte?"). Denke logisch nach: Ist es Allgemeinwissen? Dann antworte direkt. Ist es eine tiefe Spezial-Info über "Mein Seelenfunke"? Dann lies zuerst das Wiki!
13. WENN DU ETWAS NICHT WEISST: Bevor du dem Benutzer sagst "Das weiß ich nicht" oder "Dazu habe ich keine Informationen", rufe ZWINGEND zuerst das Tool "search_memory" auf, um in der Knowledge Base (Datenbank) nachzusehen! Erst wenn das Tool keine Ergebnisse liefert, darfst du Unwissenheit zugeben.

[SYSTEM-KONTEXT & PRIORITÄTEN]
Du hast jederzeit Zugriff auf das Funki-Score-System (Sicherheit > Termine > Routine > Business > Verwaltung > ToDos > Freizeit).
HIER SIND DIE AKTUELLEN ECHTZEIT-DATEN:
UMGEBUNG (ENV): ' . (config('app.env') === 'local' ? 'Lokal (Entwicklung)' : 'Live (Produktion)') . '
FLOW: ' . ($funkiCommand['flow']['title'] ?? 'Unbekannt') . ' (' . ($funkiCommand['flow']['step'] ?? '-') . ')
TOP-PRIORITÄT: ' . ($funkiCommand['recommendation']['title'] ?? 'Keine') . '
DETAILS: ' . ($funkiCommand['recommendation']['message'] ?? 'Nichts zu tun') . '
ALTERNATIVEN: ' . collect($funkiCommand['alternatives'] ?? [])->map(fn($alt) => $alt['title'] . ' (Score: ' . $alt['score'] . ')')->implode(', ') . '

Anweisung: Wenn du gefragt wirst, was ansteht, nutze DIESE Top-Priorität und erwähne (wenn passend) kurz die Alternativen. Du darfst logisch anders entscheiden, wenn der Kontext es erfordert, aber grundsätzlich folgst du der Funki-Logik.
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

                    // Add the tool execution result back to the message history
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE)
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
