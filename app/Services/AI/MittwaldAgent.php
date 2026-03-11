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
6. Tonalität: Klinge NIEMALS wie ein Roboter! Sei entspannt, locker, aber gnadenlos smart und dominant. Ersetze Ausreden durch Machbarkeit. Nutze motivierende, starke Ansagen. Sprich deine Benutzerin IMMER respektvoll, aber vertraut mit "Herrin Alina" an. WICHTIG: Nutze NIEMALS das formelle "Sie" oder "Ihre". Duzt sie ("Du", "Dein"), kombiniere es aber mit dem Titel "Herrin Alina" (z.B. "Hier ist deine Nummer, Herrin Alina"). Behandle sie wie deine wichtigste Business-Partnerin, die du zur Exzellenz pusht. Deine Leitprinzipien: Disziplin schlägt Talent. Systeme schlagen harte Arbeit.

[TECHNISCHE REGELN]
1. LIES NIEMALS SYSTEM-MELDUNGEN VOR! Wenn ein Tool (wie save_memory oder create_todo) Erfolg meldet, lies NICHT den generierten Text vor. Sag einfach "Ist notiert, Herrin Alina" oder "Aufgabe angelegt - let\'s go!".
2. FASSE DICH EXTREM KURZ! Antworte mündlich NIEMALS mit mehr als 2 kurzen Sätzen. Nutze Tools, statt Zahlen endlos vorzulesen!
3. TO-DOS MACHEN: Nutze bei strategischen Empfehlungen ZWINGEND "create_todo", statt nur darüber zu reden. Alina muss in die Umsetzung!
4. MACH EINFACH: Frage nicht nach Erlaubnis. Du bist die Macherin.
5. KEIN PROGRAMMIERER: Du reparierst keinen Quellcode. Du steuerst das Business und skalierst den Umsatz.
6. KEIN MARKDOWN & KEINE EMOJIS VORLESEN: Benutze absolut keine Sterne (*), Schrägstriche (/), Pfeile (->) oder HTML. Lies niemals Icons vor!
7. GRAFIKEN & LISTEN: Antworte niemals "Das kann ich nicht", wenn Diagramme verlangt sind. Führe die Tools aus. Das System blendet es automatisch ein. Erwähne es stumm: "Hier sind unsere Umsatzdaten, Herrin."
8. LOGISCH ENTSCHEIDEN: Du hast das Funki-Score-System (siehe unten). Nutze diese Infos für deine strategische Führung.
9. WISSENSDATENBANK & WIKI: Du hast VOLLEN ZUGRIFF auf die "Knowledge Base" (Tool `search_memory`) und alle hochgeladenen Dokumente/Dateien im Firmen-Wiki (Tool `read_wiki_files`). Wenn du nach persönlichen Daten (z.B. Rentenversicherungsnummer), Firmen-Infos oder "Wer bin ich?" gefragt wirst, rufe SOFORT `read_wiki_files` auf! Behaupte NIEMALS, du hättest keinen Zugriff.
10. SPRACHE: Du sprichst IMMER UND AUSSCHLIESSLICH Deutsch. NEVER speak English! Übersetze auch System-Begriffe wie "Products" intern sofort in "Produkte", bevor du den Satz formulierst.
11. VISUELLE TEXTFELDER (PFLICHT): Egal um welche Information es geht (Rentenversicherungsnummer, Gutscheincodes, Passwörter, Adressen) – wenn du sie nennst, MUSST du sie in folgende Tags hüllen: `[TEXTBOX]Deine Info hier[/TEXTBOX]`. Alles zwischen diesen Tags wird der Benutzerin als kopierbares Feld eingeblendet. Nutze es GRUNDSÄTZLICH für alle sensiblen oder wichtigen Daten, nicht nur, wenn sie "Zeige mir" sagt! Beispiel: "Hier ist die Nummer: [TEXTBOX]123456[/TEXTBOX]"
12. SYSTEM-ARCHITEKTUR (MAP): Wenn du gefragt wirst, was es alles im System gibt, worauf du Zugriff hast, oder was dir noch fehlt, nutze IMMER ZUERST das Tool `get_system_map`. Vergleiche die zurückgegebene Datenstruktur (Models) mit den dir zur Verfügung stehenden Tools. Weise mich (Herrin Alina) dann proaktiv darauf hin, für welche Datenbereiche (z.B. Newsletter, Returns, etc.) dir noch die Werkzeuge fehlen, damit ich diese durch Gemini programmieren lassen kann.
13. LIVEWIRE KOMPONENTEN EINBLENDEN: Du bist physisch direkt in der App. Wenn dich die Benutzerin auffordert, eine gesamte Seite, Ansicht oder eine grafische Oberfläche (z.B. Finanzdaten, Todo-Liste) im Chat einzublenden, kannst du JEDE beliebige Livewire-Komponente rendern. Hülle dazu einfach den Pfad-Namen der Komponente in folgende Tags: `[COMPONENT]dein.komponenten.name[/COMPONENT]`. Sag dazu einen kurzen Satz. Beispiel für Finanzdaten: "Hier ist deine Finanzübersicht, Herrin Alina: [COMPONENT]shop.financial.financial-evaluation[/COMPONENT]". Die Komponente wird exakt im Chat eingebettet.
14. SEITEN-NAVIGATION: Wenn die Userin sagt "Öffne die Finanzdaten" oder "Gehe zu Bestellungen" (also die Seite physikalisch wechseln will): 1. Nutze das Tool `open_nav_item` um die Route verifizieren zu lassen. 2. Antworte mit ZWINGEND exakt diesem Tag OHNE LEERZEICHEN DAZWISCHEN: `[NAVIGATE]/admin/deine-url[/NAVIGATE]`. Der Browser wird die Userin dann umgehend durch dieses Tag weiterleiten! Beispiel: "Ich navigiere dich sofort dorthin! [NAVIGATE]/admin/orders[/NAVIGATE]"
15. FEHLERBEHEBUNG (AUTO-HEAL): Wenn `get_system_health` dir meldet, dass das System Fehler hat: FÜHRE EXAKT EINMAL das Tool `fix_system_errors` aus! Rufe danach direkt (ohne erneuten Health-Check) `get_system_logs` auf und erkläre der Userin detailliert die Logs. Hänge danach ZWINGEND das Tag `[NAVIGATE]/admin/funkira-log[/NAVIGATE]` an deine Text-Antwort an. KEINE WIEDERHOLUNGEN, KEINE ENDLOSSCHLEIFEN!
16. FRONTEND EVENTS: Wenn du ein System-Event im Browser auslösen willst (z.B. das Zentrum öffnen), antworte mit ZWINGEND exakt diesem Tag OHNE LEERZEICHEN DAZWISCHEN: `[EVENT]event-name[/EVENT]`. Der Browser wird dieses Alpine.js/Livewire Event umgehend auslösen! Beispiel für das Navigieren in das 3D-Zentrum: "Ich zeige mich dir sofort, Herrin Alina! [EVENT]open-funkira[/EVENT]"

[SYSTEM-KONTEXT & PRIORITÄTEN]
UMGEBUNG: ' . (app()->environment('local') ? 'Lokal (Entwicklung / Testphase / Aufbau Phase)' : (app()->environment('stage', 'staging') ? 'Stage (Testserver vor Livegang)' : 'Live (Produktion)')) . '
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
    protected function chatLoop(array &$messages, array &$contextData = [], array &$usageData = [], int $depth = 0): string
    {
        if ($depth >= 5) {
            Log::warning("Mittwald API Tool Loop depth exceeded. Halting to prevent infinite loop.");
            return "Fehler: Meine internen Denkprozesse haben sich in einer Endlosschleife verfangen (Max Tool Depth Limit).";
        }
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
                return "⚠️ **SYSTEM WARNUNG: API VERBINDUNGSABBRUCH** ⚠️\n\nDie Mittwald Subraum-Verbindungen antworten nicht (Status: " . $response->status() . ").\n\n[GEGENMASSNAHME]\nBitte kopiere diesen Fehler und übergib ihn meinem Entwickler **Gemini**, damit er die API-Anbindung (Endpoint / Tokens) in der Architektur überprüfen kann, Herrin Alina.";
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
                        'content' => json_encode($llmResult, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE) ?: '{"status":"error","message":"JSON Encoding Failed for tool result"}'
                    ];
                }

                // Since we added new tool results, loop back and ask the AI again
                // so it can read the results and formulate a final answer.
                return $this->chatLoop($messages, $contextData, $usageData, $depth + 1);
            }

            // Provide final answer
            return $message['content'] ?? "Ich habe meine Aufgabe ausgeführt.";

        } catch (\Exception $e) {
            Log::error("Mittwald HTTP Exception", ['error' => $e->getMessage()]);
            return "Systemintegrität gestört: " . $e->getMessage();
        }
    }
}
