# AI Execution Flow Dokumentation (Seelenfunke / Funkira)

Diese Dokumentation beschreibt die exakte technische Architektur und den Ausführungsverlauf der KI-Interaktionen innerhalb des Seelenfunke-Dashboards, speziell nach der Umstellung auf eine exklusive Google Gemini-Architektur.

Es wird zwischen zwei Kommunikationswegen unterschieden: Der asynchronen Text-Chat-Verarbeitung und dem Multimodal Live Audio-Stream.

---

## 1. Asynchrone Text-Kommunikation (Schreibe per Chat)

Dieser Weg läuft Request/Response-basiert vollständig über das Laravel-Backend ab.

### Ausführungsreihenfolge:

1. **Frontend UI** (`resources/views/livewire/shop/ai/partials-ai-workspace/tab-chat.blade.php`)
   * **Zweck:** Die interaktive Benutzeroberfläche.
   * **Aktion:** Der Benutzer gibt eine Textnachricht ein. Beim Senden überträgt JavaScript/Alpine.js den Text mitsamt dem lokalen Chat-Verlauf als JSON-Payload an die API.

2. **API Routing** (`routes/api/ai.php`)
   * **Zweck:** Die Definition der Endpunkte.
   * **Aktion:** Nimmt den HTTP POST-Request entgegen und routet ihn an die entsprechende Controller-Methode (`AIController@chat`).

3. **Controller & Persistenz** (`app/Http/Controllers/AIController.php` -> Methode `chat`)
   * **Zweck:** Verteiler, Kontext-Anreicherung und Datenbankspeicherung.
   * **Aktion:** 
      - Lädt das Profil und die Metadaten des aktuellen KI-Agenten.
      - Erzeugt einen dynamischen `system_prompt` (inklusive verfügbarer Navigations-Routen).
      - Speichert die Eingabe des Benutzers dauerhaft in der Datenbank-Tabelle `ai_chat_memories`.
      - Instanziiert die `GeminiAgent`-Klasse.

4. **KI-Kern / LLM Call** (`app/Services/AI/GeminiAgent.php`)
   * **Zweck:** Die eigentliche Kommunikation mit der Google Gemini API.
   * **Aktion:** 
      - Formatiert alle Nachrichten und System-Prompts gemäß der API-Spezifikationen.
      - Fügt alle verfügbaren System-Tools aus der `AIFunctionsRegistry` dem Payload hinzu.
      - Öffnet eine Server-Sent Events (SSE) Streaming-Verbindung (cURL) zu Google.
      - Fängt den asynchronen Datenstrom ab, parst den Inhalt und extrahiert `usage` (Token-Metriken) in die Tabelle `ai_metrics`.

5. **Tool Execution** (`app/Services/AI/AIFunctionsRegistry.php`)
   * **Zweck:** Die Schnittstelle zwischen LLM-Entscheidung und Laravel-Infrastruktur.
   * **Aktion:** Wenn Gemini entscheidet, ein Tool zu nutzen (z.B. Kundendaten abzurufen), wird die Verarbeitung im `GeminiAgent` pausiert. Der Agent übergibt den Funktionsnamen und die Argumente an die `AIFunctionsRegistry`. Diese führt den lokalen PHP-Code aus, liest ggf. aus der Datenbank und reicht das JSON-Ergebnis wieder an den `GeminiAgent` zurück. Der Agent initiiert daraufhin einen zweiten (Follow-up) API-Call an Google, um das Ergebnis evaluieren zu lassen.

6. **Rückgabe an den Client** (`app/Http/Controllers/AIController.php`)
   * **Zweck:** Finalisierung.
   * **Aktion:** Die vom LLM finale generierte Antwort wird ebenfalls in `ai_chat_memories` gespeichert. Ist Text-to-Speech (TTS) über `gemini_native` aktiviert, wird hier ein weiterer Call an die `gemini-3.1-flash-tts-preview`-API ausgelöst, um den Text in eine Base64 WAV-Audiodatei zu verwandeln. Text und Audio werden als JSON-Response an das Frontend übergeben und dem Benutzer angezeigt/abgespielt.

---

## 2. Multimodal Live API (Sprache / Audio-Stream)

Dieser Weg ist auf minimale Latenz ausgelegt und basiert auf WebSockets/WebRTC-ähnlichen Streaming-Mechanismen, wobei das Laravel-Backend nach der Initialisierung größtenteils umgangen wird.

### Ausführungsreihenfolge:

1. **Audio-Initialisierung** (`resources/views/livewire/shop/ai/partials-ai-workspace/modals-and-scripts.blade.php`)
   * **Zweck:** Steuerung der Client-Geräte.
   * **Aktion:** Sobald der "Live-Modus" aktiviert wird, greift das Skript über die Browser-API auf das Mikrofon zu und bereitet die Echtzeit-Übertragung vor.

2. **Security & Context Bootstrap** (`app/Http/Controllers/AIController.php` -> Methode `liveCredentials`)
   * **Zweck:** Bereitstellung eines sicheren Kontextes.
   * **Aktion:** Vor dem Start der Übertragung fragt das Frontend beim Backend die nötigen Verbindungsdaten an. Der Server liefert den Gemini API-Key, den dedizierten System-Prompt des ausgewählten Agenten sowie eine Zusammenfassung des bisherigen Chat-Verlaufs an das Frontend zurück.

3. **Direktes Audio-Streaming** (Client-Side JS)
   * **Zweck:** Nahtlose Speech-to-Speech Verarbeitung.
   * **Aktion:** Das Frontend sendet nun die aufgenommenen Audio-PCM-Daten **direkt** an die Google Gemini Multimodal Live API (unter Umgehung von Laravel). Gemini analysiert die Frequenzen nativ (ohne vorherigen STT/Whisper-Schritt), verarbeitet die Logik und streamt eine Audio-Antwort sofort in den Browser des Benutzers zurück.

4. **Client-gesteuerte Tool Execution** (`app/Http/Controllers/AIController.php` -> Methode `execute`)
   * **Zweck:** Einbindung von System-Aktionen während eines Live-Gesprächs.
   * **Aktion:** Falls Gemini während des Live-Streams entscheidet, eine Aktion durchzuführen (z.B. ein Dashboard zu öffnen), signalisiert die Google-API dies im Stream. Das Frontend-JavaScript fängt diesen `tool_call` ab und sendet eine AJAX-Anfrage (POST) an den `execute` Endpunkt im Laravel Backend. Laravel führt das Tool aus und das Frontend leitet die Resultate zurück in den aktiven Google Audio-Stream, damit Gemini das System-Ereignis bestätigen und kommentieren kann.
