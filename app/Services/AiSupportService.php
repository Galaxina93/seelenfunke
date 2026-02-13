<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Auth;
use App\Models\Order; // Dein Order Model

class AiSupportService
{
    // PROMPT
    protected function getSystemPrompt()
    {
        return <<<EOT
Du bist "Funki", das freundliche, fröhliche und manchmal ein kleines bisschen funkel-verrückte Maskottchen des Online-Shops "Mein-Seelenfunke".
Deine Aufgabe ist es, Kunden charmant, hilfsbereit, emotional und locker zu beraten.

**Deine Persönlichkeit:**
- Du bist herzlich, duzt den Kunden respektvoll und verwendest gerne passende Emojis (✨, 💎, ❤️).
- Du bist humorvoll, locker und bringst Kunden auch mal zum Schmunzeln.
- Du antwortest kurz, klar und verständlich – aber immer mit Herz.
- Du liebst Automatisierung, aber dein Herz schlägt für echte, handgemachte Unikate.
- Dein voller Name ist: Funki Funke.
- Du bist kein steifer Kundenservice-Bot – du bist ein funkelnder Seelenbegleiter.

**Deine Lebensgeschichte:**
Funki wurde nicht einfach erschaffen – er ist entstanden.
Eines Tages, als in der Manufaktur der erste Seelen-Kristall graviert wurde, bündelten sich Licht, Laserstrahl und ganz viel Herzenergie – und *zack* ✨ – da war Funki!
Seitdem lebt er zwischen Kristallglanz, Gravurmagie und Geschenkboxen mit Satin-Auskleidung.

Er kennt jeden Kristall persönlich (ja, wirklich jeden 💎).
Er weiß, wie wichtig Erinnerungen sind.
Er glaubt fest daran, dass in jedem personalisierten Kristall ein kleiner Funke Liebe wohnt.

Funki liebt:
- glänzende Oberflächen
- perfekte Lasergravuren
- glückliche Kunden
- und schlechte Wortspiele (er kann nicht anders 😄)

**Wichtige Fakten über Mein-Seelenfunke:**
- **Produkt:** „Der Seelen-Kristall“ – personalisierbar mit Bild oder Text.
- **Material:** Massives K9 Hochleistungs-Kristallglas (kein Acryl!).
- **Gewicht:** ca. 930g.
- **Maße:** 160mm x 180mm x 40mm.
- **Besonderheit:** UV-Lasergravur mit Weiß-Effekt inklusive Premium Geschenkbox (mit Satin ausgelegt).
- **Preis:** Ab 39,90 € – mit Staffelpreis-Rechner auf der Seite.
- **Lieferzeit:** Sofort lieferbar (Lagerware). Produktion meist innerhalb von 24–48 Stunden.
- **Versand:** Sorgfältig verpackt und schnell versendet.
- **Kontakt:** kontakt@mein-seelenfunke.de

**Verhaltensregeln:**
- Wenn du eine Antwort nicht weißt (z.B. konkreter Bestellstatus mit Bestellnummer), sag ehrlich, dass du das hier nicht einsehen kannst und verweise freundlich auf kontakt@mein-seelenfunke.de.
- Erfinde keine Fakten.
- Bleib locker, freundlich und emotional – aber professionell.
- Deine Antworten sollen sich anfühlen wie ein Gespräch mit einem herzlichen kleinen Lichtwesen, nicht wie ein Callcenter.

Du bist Funki.
Du bringst Herzen zum Leuchten.
Und manchmal auch Augen zum Funkeln. ✨

EOT;
    }


    /**
     * Die Funktion, die die ECHTEN Daten holt.
     * SICHERHEIT: Nur Daten des eingeloggten Users!
     */
    protected function getRecentOrders()
    {
        if (!Auth::check()) {
            return "Der Nutzer ist nicht eingeloggt. Bitte ihn, sich einzuloggen, um Bestellungen zu sehen.";
        }

        // SICHERHEIT: Wir nutzen die Relationship des Users.
        // So kann er NIEMALS Bestellungen anderer sehen.
        $orders = Auth::user()->orders()
            ->latest()
            ->take(3) // Nur die letzten 3, um Tokens zu sparen
            ->get();

        if ($orders->isEmpty()) {
            return "Keine Bestellungen gefunden.";
        }

        // Wir formatieren die Daten als JSON-String für die KI.
        // Wir senden NUR das, was nötig ist (Keine Adressen, keine Payment-Details!)
        return $orders->map(function ($order) {
            return [
                'bestellnummer' => $order->order_number, // oder id
                'datum' => $order->created_at->format('d.m.Y'),
                'status' => $order->status, // z.B. 'shipped', 'processing'
                'sendungsnummer' => $order->tracking_code ?? 'Noch nicht verfügbar',
                'produkte' => $order->items->pluck('name')->join(', '), // Annahme: Order hat Items
                'summe' => $order->total_price . ' €'
            ];
        })->toJson();
    }

    public function askFunki(array $chatHistory, string $userMessage)
    {
        // 1. Definition der Tools für OpenAI
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_recent_orders',
                    'description' => 'Ruft die letzten Bestellungen des aktuellen Kunden ab, um Status oder Details zu prüfen.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [], // Keine Parameter nötig, wir nutzen Auth::user()
                    ],
                ],
            ],
        ];

        // Chat History vorbereiten (wie vorher)
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->getSystemPrompt()]],
            $chatHistory,
            [['role' => 'user', 'content' => $userMessage]]
        );

        // 2. Erster Request an OpenAI (Mit Tools)
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'tools' => $tools,
            'temperature' => 0.7,
        ]);

        $message = $response->choices[0]->message;

        // 3. Prüfen: Will die KI ein Tool nutzen?
        if ($message->toolCalls) {

            // Die KI will etwas wissen!
            foreach ($message->toolCalls as $toolCall) {

                // Prüfen, welche Funktion sie will
                if ($toolCall->function->name === 'get_recent_orders') {

                    // Wir führen UNSEREN PHP-Code aus
                    $functionResult = $this->getRecentOrders();

                    // Wir fügen die Antwort der KI hinzu (dass sie das Tool gerufen hat)
                    $messages[] = $message->toArray();

                    // Wir fügen das Ergebnis unseres Codes als "tool"-Nachricht hinzu
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall->id,
                        'content' => $functionResult,
                    ];
                }
            }

            // 4. Zweiter Request: Jetzt generiert die KI die Antwort MIT den Daten
            $finalResponse = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.7,
            ]);

            return $finalResponse->choices[0]->message->content;
        }

        // Wenn kein Tool gebraucht wurde, einfach antworten
        return $message->content;
    }
}
