# Abschlussbericht: KI-Chat Verbindungsfehler ("Fehler beim Laden der Antwort") - Forensische Ursachenanalyse & Ende-zu-Ende-Härtung (`v1.8.12`)

**Datum:** 20.11.2026  
**Abteilung:** `Android/` & `Agenten/`  
**App-Version:** `Funkenwerk v1.8.12` (`versionCode = 36`)  
**Status:** Behoben, mehrstufig gehärtet & automatisiert getestet

---

## 1. Forensische Ursachenanalyse (Live-Telemetrie vom 20.11.2026, 13:01 - 13:03 Uhr)

Anhand der Stage-Datenbank (`SystemLog` #24, `AiMetric` & `AiChatMemory`) konnte der exakte Fehlerablauf auf dem Google Pixel 9 Pro XL (`SDK 37`, `v1.8.11`) millisekundengenau rekonstruiert werden:

1. **Fehlende Vertex-AI-Service-Account-Datei auf Stage (`is_vertex = false`):**
   - Lokal nutzte `mein_php_server` Google Cloud Vertex AI (`aiplatform.googleapis.com`) und antwortete in **1,5 bis 3,0 Sekunden**.
   - Auf `stage.mein-seelenfunke.de` fehlte `storage/app/vertex-service-account.json`, sodass `VertexAiService::resolveOpenAiEndpoint()` stillschweigend auf den AI-Studio-Wrapper (`generativelanguage.googleapis.com/v1beta/openai`) zurückfiel.
   - Auf diesem Wrapper blockierte das Modell `gemini-3.5-flash` im Queue-Stall (`0 Bytes empfangen`), bis der alte Server-cURL-Timeout von **120 Sekunden** griff (`AiMetric`: `total_time_ms = 137.140 ms` bzw. `135.481 ms`).
2. **Timeout-Scheren-Effekt zwischen Android (`45 s`) und Backend (`120 s` + synchrones TTS):**
   - `ServiceLocator.kt` kappte jede Anfrage pauschal nach `45 s` (`readTimeout(45, TimeUnit.SECONDS)`) mit einer `SocketTimeoutException`.
   - Während die App nach `45 s` abbrach und `"[Fehler beim Laden der Antwort]"` einblendete, lief PHP im Hintergrund weitere `90 s` weiter, führte danach noch synchron `GeminiTtsService::synthesizeWav()` (`bis zu 2 x 25 s`) aus und speicherte Funkiras echte Antwort (`"Ja, bin voll da, Alina!"`) erst bei Sekunde `137` in `AiChatMemory`, ohne dass die App sie jemals erhielt.
3. **Verlaufs-Vergiftung & fehlende Wiederherstellung im Android-Client:**
   - `ChatViewModel.kt` schrieb bei jedem Fehler `ChatMessage("assistant", "[Fehler beim Laden der Antwort]")` direkt als echte Assistenten-Nachricht in `_messages` und übertrug diesen Platzhalter beim nächsten Senden im `history`-Array an das LLM.
   - `AiRepository.sendChatMessage` besaß im Gegensatz zu `getLiveCredentials` weder Connection-Pool-Eviction noch einen idempotenten Retry oder History-Abgleich nach WLAN-/5G-Handovern.
4. **Falsch-positives `[AUTO-GEHEILT]`-Flag im Error-Sentinel:**
   - `ErrorSentinelReporter.captureError()` markierte jede `SocketTimeoutException` pauschal als `autoHealed = true` (*"Offline-Modus / lokaler Cache übernahm"*), obwohl der interaktive KI-Chat keinen Offline-Cache besitzt und für Alina sichtbar fehlschlug. Zudem fehlten `error_class` und `stacktrace`-Mapping in `SystemLog`.

---

## 2. Durchgeführte Härtungs-Maßnahmen (6 Verteidigungsschichten)

### Schicht 1: Vertex AI auf Stage & schneller AI-Studio-Modell-Guard
* **[`VertexAiService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/VertexAiService.php):**
  * `resolveCredentialsPath()` prüft neben dem konfigurierten Pfad automatisch sowohl `storage/app/vertex-service-account.json` als auch `storage/app/private/vertex-service-account.json`.
  * `formatOpenAiModel()` routet bei aktivem Vertex AI weiterhin auf `google/gemini-3.5-flash` (`~1,5 s`), weicht aber im Non-Vertex-Fallback (`generativelanguage.googleapis.com`) automatisch auf `gemini-3-flash-preview` (`~0,7 s`) aus, damit Queue-Stalls ausgeschlossen sind.
* **[`funki`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funki) (`stage-deploy`):**
  * Synchronisiert `storage/app/vertex-service-account.json` bei jedem Stage-Deploy verschlüsselt per Ed25519-SSH (`chmod 600`) nach `seelenfunke-stage` (und über `sync-worker` weiter an `worker-stage-3` sowie `twilio-bridge`).

### Schicht 2: Anti-Stall Stream-Watchdog & Redis-Resilienz im KI-Kern
* **[`GeminiAgent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/GeminiAgent.php):**
  * `Expect:`-Header ergänzt (verhindert `100-continue`-MTU-Blockaden bei großen Tool-Schemata).
  * `CURLOPT_LOW_SPEED_LIMIT = 1` und `CURLOPT_LOW_SPEED_TIME = 22` aktiviert sowie `CURLOPT_TIMEOUT` von `120 s` auf `45 s` gesenkt: Sendet ein Upstream-Knoten `22 s` lang kein einziges SSE-Byte, schwenkt `GeminiAgent` sofort auf das schnelle Fallback-Modell (`gemini-3.5-flash-lite` bzw. `gemini-3-flash-preview`) um.
  * SSE-Token-Zählung (`$streamUsage`) korrigiert, sodass mehrfache finale SSE-Usage-Chunks pro Stream nicht mehr doppelt oder dreifach aufaddiert werden.
* **[`GeminiTtsService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/GeminiTtsService.php) & [`AiPromptService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/AiPromptService.php):**
  * Sämtliche `Cache::*`-Aufrufe gegen transiente Mittwald-Redis-DNS-Aussetzer (`Predis\Connection\Resource\Exception\StreamInitException`) per `try/catch (\Throwable)` abgesichert.

### Schicht 3: Idempotenz (`client_message_id`) & TTS-Zeitbudget in `POST /api/ai/chat`
* **[`AIController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/AIController.php):**
  * **Idempotenz & In-Flight-Join:** Verarbeitet `client_message_id` pro Nutzer. Wiederholt die Android-App nach einem Funkloch oder Netzwechsel dieselbe Anfrage, wartet der Server auf den laufenden Worker bzw. liefert die bereits berechnete Antwort sofort dedupliziert aus (`0` doppelte LLM-Kosten, `0` doppelte `AiChatMemory`-Einträge).
  * **Zeitbudgetiertes synchrones TTS:** Begrenzt synchrones Chat-TTS auf maximal `8 s` und überspringt es automatisch, falls die vorherige Tool-/LLM-Kette bereits über `22 s` benötigt hat, damit die Textantwort niemals durch Audio-Synthese verzögert wird.
  * **History-Sanitizer:** Filtert historische `"[Fehler beim Laden der Antwort]"`-Einträge serverseitig heraus und liefert `chat_session_id` konsistent an die App zurück.

### Schicht 4: Android Netzwerk-, Retry- & History-Reconciliation-Engine
* **[`ServiceLocator.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/di/ServiceLocator.kt):**
  * HTTP/2 `pingInterval(25s)` gegen tote Keep-Alive-Sockets nach Doze/Standby aktiviert.
  * Dynamisches `readTimeout(90s)` gezielt für `/ai/chat` und `/ai/execute` eingerichtet (Standard-Endpunkte bleiben knackig bei `45 s`).
* **[`AiRepository.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/data/repository/AiRepository.kt) & [`AiApi.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/data/api/AiApi.kt):**
  * **Stufe 1 (Idempotenter Retry):** Bei transientem `IOException` oder HTTP `408/429/500/502/503/504` wird der OkHttp-Connection-Pool geleert und automatisch ein Retry mit identischer `client_message_id` ausgeführt.
  * **Stufe 2 (Server-Historien-Abgleich):** Bricht die Mobilfunkverbindung exakt während der Antwortübertragung ab, prüft `reconcileRecoveredAssistantReply()` nach `500 ms` automatisch `/api/ai/history` und übernimmt die bereits gespeicherte Antwort nahtlos in den Chat.

### Schicht 5: Clean-Design Klartext-Diagnose & 1-Tap `"Erneut senden"`
* **[`ChatViewModel.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/ChatViewModel.kt) & [`ChatScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/ChatScreen.kt):**
  * Kein Einfügen von `"[Fehler beim Laden der Antwort]"` mehr in den Nachrichtenverlauf.
  * Klare deutsche Fehlerursache (`formatUserFacingError`) plus direkter **Erneut senden**-Button im Clean-Design (nutzt dieselbe `client_message_id` ohne doppelte Nutzer-Sprechblase).

### Schicht 6: Ehrliche Cross-Platform-Fehlertelemetrie
* **[`ErrorSentinelReporter.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/ErrorSentinelReporter.kt), [`AppLogger.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/AppLogger.kt) & [`SystemErrorLogger.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SystemErrorLogger.php):**
  * Ungeheilte interaktive KI-Chat-Fehler setzen `allowOfflineAutoHeal = false` und übermitteln `error_class`, `file`, `line` und `stacktrace` vollständig an `/api/system/error-ingest`.

---

## 3. Automatisierte Tests & Verifikation

1. **Laravel Feature- & Unit-Tests ([`AndroidAiChatHardeningTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AndroidAiChatHardeningTest.php), [`VertexAiServiceTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Unit/Services/AI/VertexAiServiceTest.php)):**
   * `6 passed (60 assertions)` (Non-Vertex Modell-Guard, `client_message_id`-Idempotenz ohne doppelten LLM-Call, Platzhalter-Bereinigung, `/api/system/error-ingest` Stacktrace-Mapping).
2. **Android JVM Unit-Tests ([`ChatResilienceTest.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/test/java/de/meinseelenfunke/app/ui/screens/ChatResilienceTest.kt)):**
   * Prüft Verlaufs-Sanitizing, deutsche Klartext-Fehlermeldungen (`SocketTimeoutException`, `UnknownHostException`, `SSLException`, HTTP `401/503`), Retry-Klassifikation und Server-Historien-Wiederherstellung.
