# Google Cloud Vertex AI Postpay-Architektur (`VertexAiService`)

## 1. Ausgangslage & Strategische Lösung
* **Problem:** Google AI Studio (`generativelanguage.googleapis.com`) erzwingt seit 2026 für neuere Konten ein reines **Prepay-Guthabenmodell**, bei dem SEPA-Lastschriften automatisiert mit `[OR_BACR2_36]` abgelehnt werden.
* **Lösung (Option A - Google Cloud Vertex AI Postpay):** Vollständige Migration sämtlicher KI-, Vision-, OCR-, Grounding-, TTS- und Live-Audio-Schnittstellen auf **Google Cloud Vertex AI (`aiplatform.googleapis.com`)** über ein dediziertes **Postpay-Rechnungskonto** mit monatlicher SEPA-Lastschrift vom **FYRST-Geschäftskonto**.

## 2. Google Cloud & Billing Stammdaten
* **Google Cloud Projekte auf Postpay:** `SEELEN` (`gen-lang-client-0670786367`, Projektnummer `623907765016`) sowie `mein-seelenfunke-checkout` (`fluted-anthem-334018`, Google Maps / Places / Address Validation API)
* **Aktives Rechnungskonto (Postpay):** `Mein-Seelenfunke (Postpay)` (`01527A-BF3B0A-E2C9CB`, Zahlungsprofil `Alina Steinhauer · Organisation · ID: 9554-0689-9279`); Altkonto `My Billing Account` (`013732-508BC2-295946`) wurde nach Projekt-Umzug mit `0` Ressourcen dauerhaft geschlossen
* **Primäre Zahlungsmethode:** Ausschließlich verifiziertes **FYRST-Geschäftskonto** (`DE•• •••• •••• •••• ••29 00`, keine sekundäre/private Fallback-Zahlungsmethode hinterlegt)
* **Kosten-Budget & E-Mail-Warnungen:** Aktives Monatsbudget `Mein-Seelenfunke Monatsbudget 50 EUR` (`50,00 €` über alle 2 Projekte & Dienste mit automatischen E-Mail-Warnungen bei `50 %` / `25 €`, `90 %` / `45 €` und `100 %` / `50 €`)
* **Service Account:** `laravel-backend@gen-lang-client-0670786367.iam.gserviceaccount.com` (IAM-Rolle: `Agent Platform User` / `roles/aiplatform.user`)
* **Schlüsseldatei (Git-ignoriert, Zero-Secret):** `storage/app/vertex-service-account.json` (`chmod 640` lokal / `600` auf Stage & Worker)

## 3. Technische Architektur (`App\Services\AI\VertexAiService`)
1. **RS256-JWT & OAuth2-Token-Caching:**
   * Signiert lokal ein kurzlebiges RS256-JWT gegen `https://oauth2.googleapis.com/token` (`scope: cloud-platform`) und cached den `ya29.*` Bearer-Token für ~53 Minuten im Laravel-Cache.
   * Automatischer 1-maliger Token-Refresh bei `HTTP 401`.
2. **Endpunkt- & Modell-Normalisierung:**
   * **REST (`:generateContent`) & OpenAI-kompatibel (`/endpoints/openapi/chat/completions`):** Läuft über `locations/global` (`https://aiplatform.googleapis.com/v1beta1/projects/gen-lang-client-0670786367/locations/global/...`).
   * **Automatisches Modell-Mapping:** Veraltete AI-Studio-Modellnamen (`gemini-2.5-flash`, `gemini-2.0-flash`, `gemini-1.5-flash`) werden transparent auf aktive Vertex-Modelle (`gemini-3.5-flash`, `gemini-3.5-flash-lite`, `gemini-3.1-pro-preview`, `gemini-2.5-flash-tts`, `gemini-3.8-flash-tts`) abgebildet; OpenAI-Aufrufe erhalten automatisch das `google/`-Präfix.
   * **Payload-Normalisierung:** Ergänzt automatisch `role: "user"` in `contents[]` und konvertiert `inline_data`/`mime_type` nach `inlineData`/`mimeType`.
3. **Echtzeit-Sprach- & Telefon-Brücken (`BidiGenerateContent` in EU `europe-west4`):**
   * `server-twilio.js` (`v1.5-vertex-live-secretary`, inkl. 45-Min.-Hintergrund-Token-Prewarm), `TwilioGeminiLiveBridgeService` (Reverb `v2.0`), `AIController::liveCredentials` sowie die Android-App (`FunkiraLiveViewModel` & `StandalonePhoneSecretaryEngine`) verbinden sich latenzarm über den EU-Endpunkt `wss://europe-west4-aiplatform.googleapis.com/ws/google.cloud.aiplatform.v1beta1.LlmBidiService/BidiGenerateContent` (`gemini-live-2.5-flash-native-audio`).
4. **100 % PHPUnit-Test-Isolation & Fallback:**
   * In `app()->runningUnitTests()` bleibt Vertex standardmäßig deaktiviert (`services.vertex.allow_in_tests = false`), sodass bestehende `Http::fake()`-Suites gegen `generativelanguage.googleapis.com` deterministisch ohne externe Netzwerkanfragen durchlaufen.
   * `SystemErrorLogger::sanitizeSensitiveText` und `scrubBridgeSecrets` maskieren neben `AIza*` auch alle `ya29.*` OAuth2-Tokens.
5. **Stage- & Worker-Synchronisation (`./funki sync-worker` / `stage-deploy`):**
   * Kopiert `storage/app/vertex-service-account.json` automatisch mit `chmod 600` nach `worker-stage-3/storage/app/vertex-service-account.json` und `twilio-bridge/vertex-service-account.json`.
