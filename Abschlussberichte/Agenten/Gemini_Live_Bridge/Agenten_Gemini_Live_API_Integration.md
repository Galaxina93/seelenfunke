# Abschlussbericht: Integration & Architektur der Gemini Multimodal Live API Bridge

**Datum:** 22. Mai 2026  
**Projekt:** Seelenfunke E-Commerce / AI Workspace  
**Status:** ERFOLGREICH INTEGRIERT & PRODUKTIV (Staging)  
**Entwickler:** Antigravity (Google DeepMind) & Team  

---

## 1. Ausgangssituation & Zielsetzung
Ziel war es, den statischen KI-Agenten des Seelenfunke-Workspace in einen echtzeitfähigen, multimodalen Live-Assistenten zu verwandeln. Der Benutzer soll per Audio (Sprachchat) in Echtzeit mit der KI kommunizieren können. Gleichzeitig muss die KI in der Lage bleiben, Systemwerkzeuge (Tools) auszuführen – beispielsweise Code-Dateien zu editieren, Pläne zu erstellen oder auf die interne Wissensdatenbank zuzugreifen.

Die größte Herausforderung lag in der Zusammenführung dreier Welten:
1. **Der Web-Client (Browser):** Nutzt standardmäßig hohe Abtastraten (44.1/48 kHz) und WebSockets (WSS).
2. **Die Telefonie (Twilio):** Nutzt veraltete Telefoniestandards (8 kHz Mu-Law).
3. **Google Gemini Live API:** Erwartet zwingend ein sehr spezifisches WebSocket-Protokoll (`BidiGenerateContent` auf dem `v1beta`-Endpunkt) mit 16 kHz PCM Mono-Audio.

---

## 2. Systemarchitektur & Verbindungsfluss

Das System läuft über eine performante Node.js-Audiobrücke (`server-twilio.js`), die als mStudio-App (`seelenfunke-nodejs`) auf dem Server läuft und als Proxy zwischen den Clients (Browser/Twilio) und Google fungiert.

### Architektur des Verbindungsflusses (Web-Client)

```mermaid
sequenceDiagram
    autonumber
    actor Browser as Browser (Alpine.js Client)
    participant Proxy as Node.js Bridge (Port 8089)
    participant Laravel as Laravel Backend (Verification)
    participant Google as Google Gemini Live API

    Browser->>Proxy: WebSocket Connect (ws://127.0.0.1:8089/gemini-live?token=...)
    Note over Proxy: Starte Eingangs-Pufferung (messageQueue)
    Browser->>Proxy: Sende setup (Modell & Tools)
    Note over Proxy: setup-Nachricht in messageQueue gespeichert
    
    Proxy->>Laravel: HTTP POST /api/ai/verify-token {token: ...}
    Laravel-->>Proxy: Token Valid (Liefert API-Key & Prompt-Konfiguration)
    
    Note over Proxy: Initialisiere Gemini Live Proxy
    Proxy->>Google: WebSocket Connect (wss://generativelanguage.googleapis...)
    Note over Proxy: Starte Ausgangs-Pufferung (googleQueue)
    
    Note over Proxy: Replay von messageQueue (setup-Paket)
    Note over Proxy: googleWs ist CONNECTING -> setup landet in googleQueue
    
    Google-->>Proxy: WebSocket Open
    Note over Proxy: Flush googleQueue -> Sende setup an Google
    
    Note over Proxy: Direkte Pipeline etabliert!
    Browser->>Proxy: Realtime Audio Input (16 kHz PCM)
    Proxy->>Google: Realtime Audio Input (16 kHz PCM)
    Google-->>Proxy: Realtime Audio Response (24 kHz PCM)
    Note over Proxy: Resampling & Weiterleitung
    Proxy-->>Browser: Audio Output & Transkript (24 kHz PCM)
```

---

## 3. Die Node.js-Audiobrücke (`server-twilio.js`)

Die Audiobrücke ([server-twilio.js](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/server-twilio.js)) erledigt das Routing, die Authentifizierung und die Formatkonvertierung. Sie lauscht auf Port `3000` (wird über Docker/Host auf Port `8089` gemappt) und verarbeitet zwei Pfade: `/gemini-live` (für Web-Clients) und `/twilio-stream` (für Twilio-Anrufe).

### 3.1 Token-Verifizierungsfluss & Sicherheit
Um den Gemini API-Key niemals im Browser zu exponieren, wird ein sicherer Handshake durchgeführt:
1. Der Client holt sich im Laravel-Backend ein temporäres Einweg-Token.
2. Der Client verbindet sich mit der Brücke über `ws://.../gemini-live?token=TOKEN`.
3. Die Brücke hält die Verbindung und sendet einen `POST` an den Laravel-Endpunkt `/api/ai/verify-token`.
4. Laravel verifiziert das Token aus dem Cache, löscht es sofort (One-Time-Use) und gibt den API-Key und die Prompt-Daten an die Bridge zurück.

### 3.2 Die Double-Race-Condition & ihre Lösung
Beim Verbindungsaufbau gab es ein gravierendes Timing-Problem (Race Condition):
- **Stufe 1 (Token-Verifizierung):** Der Client sendet unmittelbar nach dem Verbindungsaufbau das `setup`-Paket mit Anweisungen und Tools. Zu diesem Zeitpunkt wartet die Bridge jedoch noch auf die Antwort der HTTP-Verifizierung von Laravel. Frühe Nachrichten gingen verloren.
- **Stufe 2 (Asynchroner Google-Verbindungsaufbau):** Um Stufe 1 zu lösen, wurden die frühen Nachrichten gepuffert. Wenn sie nach der Verifizierung abgespielt wurden, war die ausgehende Verbindung von der Bridge zu Google jedoch oft noch im Zustand `CONNECTING`. Das WebSocket war nicht `OPEN`, weshalb Google das replayed `setup`-Paket verwarf. Ohne `setup` antwortete Google nicht.

**Die Lösung (Zweistufige Pufferung):**
1. **Eingangs-Pufferung (`messageQueue`):** Alle Client-Nachrichten werden ab der ersten Millisekunde in ein Array geschoben, bis die Token-Verifizierung erfolgreich ist.
2. **Ausgangs-Pufferung (`googleQueue`):** Sobald die Google-Verbindung initiiert wird, werden alle ausgehenden Nachrichten (wie das `setup`-Paket und frühe Audio-Chunks) in die `googleQueue` geschoben, solange das Google-WebSocket den Status `CONNECTING` hat. Sobald das Event `googleWs.on('open')` feuert, wird die gesamte Warteschlange der Reihe nach an Google übertragen.

```javascript
// Auszug aus server-twilio.js: Ausgangs-Pufferung
googleWs.on('open', () => {
    debugLog('🧠 Gemini Live Proxy: Verbindung zu Google hergestellt.');
    if (googleQueue.length > 0) {
        debugLog(`📦 Replay von ${googleQueue.length} gepufferten Client-Nachrichten an Google...`);
        while (googleQueue.length > 0) {
            const msg = googleQueue.shift();
            googleWs.send(msg);
        }
    }
});
```

### 3.3 Audio-Konvertierung (Twilio-Modus)
Twilio telefoniert mit 8.000 Hz µ-Law. Gemini arbeitet mit 16.000 Hz linear PCM.
- **Inbound (Anrufer -> Gemini):** Der Proxy wandelt den 8 kHz µ-Law Stream von Twilio mittels der Bibliothek `wavefile` in 8 kHz 16-Bit PCM um und führt anschließend ein Upsampling auf 16.000 Hz durch, bevor er es an Google sendet.
- **Outbound (Gemini -> Twilio):** Google sendet 24 kHz PCM-Audio zurück. Der Proxy liest dies als `Int16Array` ein (wichtig zur Vermeidung von Rauschen), führt ein Downsampling auf 8.000 Hz durch, wandelt es in µ-Law um und streamt es an Twilio.

---

## 4. Das Frontend-Widget (`ai-widget-part2.blade.php`)

Das Frontend ([ai-widget-part2.blade.php](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/ai/ai-widget-part2.blade.php)) fängt das Mikrofon des Nutzers ab und steuert die Benutzeroberfläche.

### 4.1 Clientseitiges Resampling (Linear Interpolation)
Da Browser standardmäßig in 44.1 kHz oder 48 kHz aufnehmen, die Gemini API jedoch strikt **16 kHz** erfordert, wurde ein linearer Interpolations-Algorithmus direkt im Web Audio API `AudioWorklet` / `ScriptProcessor` implementiert:

```javascript
// Resampling im ScriptProcessor-Handler
let buffer = event.inputBuffer.getChannelData(0);
let inputSampleRate = audioContext.sampleRate;
let outputSampleRate = 16000;

let sampleRateRatio = inputSampleRate / outputSampleRate;
let newLength = Math.round(buffer.length / sampleRateRatio);
let result = new Float32Array(newLength);
let offsetResult = 0;
let offsetInput = 0;

while (offsetResult < result.length) {
    let nextOffsetBuffer = Math.round((offsetResult + 1) * sampleRateRatio);
    let accum = 0, count = 0;
    for (let i = offsetInput; i < nextOffsetBuffer && i < buffer.length; i++) {
        accum += buffer[i];
        count++;
    }
    result[offsetResult] = accum / count;
    offsetResult++;
    offsetInput = nextOffsetBuffer;
}
```
Die resultierenden `Float32`-Werte werden in 16-Bit-Integers (`Int16Array`) umgerechnet, in Base64 konvertiert und per WebSocket gesendet.

### 4.2 Behebung des Stumm-Bugs (Mic Muted)
*   **Fehlersymptom:** Das Mikrofon leuchtete grün, die Verbindung stand, aber Google reagierte nicht auf Sprache.
*   **Ursache:** Im Widget wurde die Variable `isAudioMuted` zur Steuerung der Shop-Hintergrundmusik verwendet. Diese stand standardmäßig auf `true` (stumm). Der Audio-Aufnahme-Handler prüfte irrtümlicherweise ebenfalls `isAudioMuted`. Da der Benutzer stummgeschaltet startete, sandte der Browser nur leere Audio-Pakete an den Proxy.
*   **Die Lösung:** Einführung einer separaten Variable `isMicMuted` (Standard: `false`), die rein die Mikrofonaufnahme steuert. Der Musik-Mute-Status bleibt davon unberührt.

### 4.3 Hardware-Freigabe (Chrome Red-Dot)
Um das rote Aufnahmesymbol in Chrome nach dem Beenden des Live-Modus zuverlässig zu entfernen, werden alle Spuren des MediaStreams explizit gestoppt:
```javascript
if (mediaStream) {
    mediaStream.getTracks().forEach(track => track.stop());
}
```

---

## 5. Das Laravel-Backend (Verifizierung & Session-Sync)

### 5.1 Schema-Sanitizing
Die Gemini Live API weigert sich strikt, Verbindungen aufzubauen, wenn in den Tool-Parameterdeklarationen ungültige OpenAPI-Schlüssel wie `additionalProperties` oder leere Objekte vorhanden sind. Im `AIController` bereinigt eine rekursive Hilfsfunktion alle Schemata, bevor das Token gecached wird:

```php
private function removeAdditionalProperties(array $schema): array {
    unset($schema['additionalProperties']);
    foreach ($schema as $key => $value) {
        if (is_array($value)) {
            $schema[$key] = $this->removeAdditionalProperties($value);
        }
    }
    return $schema;
}
```

### 5.2 Session-Wiederherstellung (Session Restore)
Da der WebSocket-Verbindungsaufbau aus dem Browser heraus *stateless* läuft, erhielten Tool-Aufrufe der KI im Backend standardmäßig eine leere PHP-Session. Dadurch speicherte die KI erstellte Artefakte in "Geister-Ordnern" ab.
*   **Lösung:** Der Client sendet die Laravel-Session-ID im Payload. Das Backend greift diese in `AIController::execute` auf und initialisiert die Session des Benutzers manuell:
    ```php
    if ($request->has('session_id')) {
        session()->setId($request->get('session_id'));
        session()->start();
    }
    ```

---

## 6. Das Diagnosetool (`debug_websocket.php`)

Das Diagnosetool ([debug_websocket.php](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/Agenten/Gemini-Live-Api-Bridge-and-more/debug_websocket.php)) wurde zur Fehlersuche auf dem Staging-Server entwickelt.

### 6.1 Funktionen
*   **Netzwerkverbindungstest:** Führt einen Port-Check (`fsockopen`) von der Web-App zum PHP-Worker auf Port `6001` (Reverb) durch.
*   **Browser-WSS-Verbindungstest:** Simuliert im Browser des Aufrufers den Verbindungsaufbau zu Laravel Reverb (`wss://ws.mein-seelenfunke.de`).
*   **Browser-Gemini-Proxy-Test:** Testet den Handshake zur Node.js-Bridge (`wss://api-live-bridge.mein-seelenfunke.de/gemini-live`).
*   **Log-Reader:** Liest die letzten 150 Zeilen der Datei `bridge.log` aus und aktualisiert diese live.
*   **PHP CLI Diagnostik:** Prüft, welche PHP-Interpreter auf dem Server vorhanden sind (z.B. `/usr/bin/php8.4`), ob sie kompatibel sind und ob der Laravel-Scheduler läuft.
*   **Umgebungsvariablen-Vergleich:** Vergleicht die `.env`-Dateien der Web-App und der Worker-App auf Mismatches.
*   **Selbstheilungs-Aktionen:** Erlaubt das automatische Reparieren der Worker-`.env`, das Löschen hängender Scheduler-Mutex-Sperren in Redis/Cache und das manuelle Triggern des Schedulers.

### 6.2 Sicherheitsrelevante Verschiebung
Da das Diagnosetool sensible System- und Umgebungsvariablen (wie Passwörter und API-Keys) im Klartext anzeigt und tiefe Eingriffe in das System erlaubt, wurde es **aus dem öffentlichen Verzeichnis (`public/`) gelöscht** und als Referenz in diesen Dokumentations-Ordner verschoben. Es darf unter keinen Umständen im produktiven Web-Verzeichnis verbleiben!

---

## 7. Fehlerhistorie & Critical Learnings

1.  **Port-Kollision unter Windows (Port 8081):**  
    *Problem:* Der Port `8081` war lokal oft belegt.  
    *Lösung:* Die Node.js-Bridge lauscht intern weiter auf `8081` (bzw. `3000`), wird aber über Docker-Compose nach außen auf Port `8089` freigegeben. In der `.env` zeigt `GEMINI_PROXY_WS_URL` auf Port `8089`.
2.  **Modell-Abkündigung durch Google:**  
    *Problem:* Das Modell `models/gemini-2.0-flash-exp` wurde abgeschaltet.  
    *Lösung:* Es wird nun zwingend `models/gemini-3.1-flash-live-preview` genutzt, das die Bidi-WebSocket-Schnittstelle nativ unterstützt.
3.  **Warten auf den User (Twilio):**  
    *Problem:* Die KI fing bei Telefonaten sofort an zu sprechen, noch bevor der Angerufene "Hallo" gesagt hatte (durch Rauschen getriggert).  
    *Lösung:* Ein expliziter System-Prompt weist die KI an, stumm zu bleiben, bis ein klares, menschliches Eröffnungswort registriert wird.
4.  **Resampling-Verzerrungen:**  
    *Problem:* Die Stimme der KI klang verzerrt oder wie Micky-Maus.  
    *Lösung:* Beim Konvertieren der 24 kHz Gemini-Daten in 8 kHz Twilio µ-Law ist die korrekte Definition eines `Int16Array` zwingend notwendig, da rohe Byte-Buffer sonst fälschlicherweise als 16-Bit-Werte interpretiert werden.
5.  **Hardware-Konflikt & Aktivierungston-Schleife ("Düm-Düm") auf Mobilgeräten:**  
    *Problem:* Beim Aktivieren des Live-Modus auf Smartphones ertönte alle 5-6 Sekunden der System-Mikrofon-Aktivierungston des Handys ("düm-düm" bzw. kurzes Beep), auch wenn nichts gesprochen wurde.  
    *Ursache:* Im Live-Modus greift die App das Mikrofon direkt mittels `getUserMedia` ab, um den rohen 16 kHz PCM-Stream an die Gemini Live WebSocket-Verbindung zu senden. Gleichzeitig lief im Hintergrund parallel die native Spracherkennung des Browsers (`webkitSpeechRecognition`), um das gesprochene Wort für die Chat-Historie zu transkribieren. Da Mobilgeräte (iOS/Safari und Android/Chrome) den parallelen Zugriff von zwei API-Instanzen auf dasselbe physische Mikrofon nicht unterstützen, stürzte die native Spracherkennung kontinuierlich ab, triggerte ihr `onend`-Ereignis und startete sofort neu. Dies führte zu einer Dauerschleife von Mikrofon-Reinitialisierungen und den entsprechenden System-Beeps.  
    *Lösung:* Auf Mobilgeräten (`isMobile` ist aktiv) wird die parallele `webkitSpeechRecognition` im Live-Modus nun vollständig übersprungen. Da die Spracheingabe ohnehin direkt als rohes Audio gestreamt und von Gemini verarbeitet wird, bleibt der Live-Chat voll funktionsfähig, ohne dass Systemkonflikte oder störende Aktivierungstöne auftreten.

---

## 8. Fazit
Mit dieser Implementierung verfügt Seelenfunke über eine **hochmoderne, extrem performante und voll integrierte Echtzeit-KI-Architektur**. Der reibungslose Fluss von Audio, System-Tools und synchronisierten Sessions ermöglicht eine bisher unerreichte Benutzererfahrung sowohl im Web-Dashboard als auch in zukünftigen Telefonie-Anwendungen.

---

## 9. Einrichtung, Deployment & Update-Workflow der Node.js App (`twilio-bridge`) im mStudio

> [!IMPORTANT]
> **WICHTIG FÜR JEDES DEPLOYMENT (`twilio-bridge` vs. `seelenfunke-stage`):**  
> Auf dem Mittwald-Server läuft die Node.js-Audiobrücke (`seelenfunke-nodejs`) in einem **eigenen Verzeichnis** (`/html/twilio-bridge`), getrennt vom Git-Hauptverzeichnis (`/html/seelenfunke-stage`).  
> Ein normaler `git pull` in `/html/seelenfunke-stage` aktualisiert zwar die Datei `server-twilio.js` im Git-Ordner, **nicht aber automatisch die laufende Instanz in `/html/twilio-bridge`**!  
> Wenn `/html/twilio-bridge/server-twilio.js` nach einem Update nicht synchronisiert und die Node.js-App im mStudio neu gestartet wird, läuft die Bridge weiterhin mit der alten Version (erkennbar an `"bridge_version"` im Log oder im Live-Ping-Tooltip).

### 9.1 mStudio App-Konfiguration
Folgende Einstellungen sind beim Anlegen der Node.js App im Mittwald mStudio hinterlegt:

*   **Name:** `seelenfunke-nodejs`
*   **Installationsverzeichnis:** `/html/twilio-bridge` (relativ: `twilio-bridge`)
*   **Startbefehl:** `node server-twilio.js`
*   **Verknüpfung / Domain:** `api-live-bridge.mein-seelenfunke.de`
*   **Interner Health- & Ping-Port:** `127.0.0.1:8081` (`GET /health`)

### 9.2 Erstmalige Bereitstellung & Manuelles Update nach `git pull`
Um nach einem Git-Deployment die aktualisierte `server-twilio.js` aus dem Staging-Projekt in das Ausführungsverzeichnis der Node.js-App zu übernehmen, führen Sie per SSH auf dem Server folgende Befehle aus:

```bash
cd /html/twilio-bridge
cp ../seelenfunke-stage/server-twilio.js .
cp ../seelenfunke-stage/.env .
cp ../seelenfunke-stage/package.json .
cp -r ../seelenfunke-stage/node_modules .
```

### 9.3 Empfohlene Dauerlösung: Symlink statt manuellem Kopieren
Damit zukünftige `git pull`-Updates in `/html/seelenfunke-stage` **automatisch** auch in `/html/twilio-bridge` wirksam sind (ohne jedes Mal `cp` ausführen zu müssen), empfiehlt sich das einmalige Anlegen eines symbolischen Links:

```bash
cd /html/twilio-bridge
ln -sf ../seelenfunke-stage/server-twilio.js /html/twilio-bridge/server-twilio.js
ln -sf ../seelenfunke-stage/.env /html/twilio-bridge/.env
```

**Nach jedem Update von `server-twilio.js`:**
1. Im Mittwald mStudio unter **Apps → `seelenfunke-nodejs`** auf **„Neu starten“ (Restart)** klicken (damit Node.js den neuen Code in den Arbeitsspeicher lädt).
2. Zur schnellen Verifikation per SSH oder im Browser-Widget prüfen:
   ```bash
   curl -s http://127.0.0.1:8081/health
   ```
   Die Ausgabe muss `"bridge_version": "v1.3-stable"` und `"status": "ok"` anzeigen (alternativ sichtbar beim Hovern über den grünen Live-Ping-Punkt im Seiten-Widget **„Live-AI“**).

---

## 10. Einrichtung des Laravel Task Schedulers im mStudio
Für die periodische Ausführung von Hintergrundaufgaben (wie dem Laravel Scheduler) wurde im Mittwald mStudio ein entsprechender Cronjob eingerichtet.

### mStudio Cronjob-Konfiguration
Folgende Einstellungen wurden im Mittwald-Panel hinterlegt:

*   **Verknüpfte App:** `seelenfunke-stage`
*   **Ausführungstyp:** Befehl ausführen
*   **Interpreter:** `PHP` (bzw. `/usr/bin/php`)
*   **Datei:** `/html/seelenfunke-stage/artisan`
*   **Parameter:** `schedule:run`
*   **Auszuführender Befehl:** `/usr/bin/php /html/seelenfunke-stage/artisan schedule:run`
*   **Intervall:** `* * * * *` (Cron-Syntax; Jede Minute, jede Stunde, jeden Tag)
*   **Zeitzone:** `Europe/Berlin`
*   **Fehlerbehandlung:** E-Mail-Versand ab dem 1. Fehler, Timeout bei 3600 Sekunden.

### Visuelle Dokumentation
Hier sind die Einstellungen aus dem Mittwald-Panel zur Referenz:

#### 1. Cronjob Übersicht (Aktiv & Funktionsfähig)
![Cronjob Übersicht](images/cronjob_overview.png)

#### 2. Befehls-Konfiguration
![Befehls-Konfiguration](images/command_config.png)

#### 3. Intervall-Konfiguration (Cron-Syntax)
![Intervall-Konfiguration](images/interval_config.png)

---

## 11. Stabilitäts-, Resilienz- & Diagnose-Upgrade (`v1.2-diag` → `v1.3-stable`, 22. Oktober 2026)

### 11.1 Behobene Ursachen (Google Live API Code 1008, Redis DNS & PHP 8.5)
1. **Google Live API `Code: 1008 (Requested entity was not found)` & `1007`:**
   - **Automatische Modell-Normalisierung (`server-twilio.js`):** Veraltete Modellnamen (wie `models/gemini-2.5-flash-native-audio-preview-12-2025` von älteren Clients) werden in der Bridge automatisch auf `models/gemini-3.1-flash-live-preview` umgeschrieben.
   - **Audio- & Video-Payload-Konvertierung (`server-twilio.js` & `FunkiraLiveViewModel.kt`):** Veraltete `realtimeInput.mediaChunks`-Pakete werden live in `realtimeInput.audio` bzw. `realtimeInput.video` konvertiert.
   - **Schutz vor leeren Interrupt-Frames:** Leere `clientContent`-Pakete (`turnComplete: false` mit `" "`-Text) werden in der Bridge abgefangen und verworfen, bevor sie bei Google einen `1008`-Abbruch auslösen.
2. **Stage-Server Redis DNS-Ausfallsicherung (`AppServiceProvider.php`):**
   - Prüft beim Booten die DNS-Auflösbarkeit und Erreichbarkeit des Redis-Containers (`redis-t4tm3u...`). Bei einem Ausfall schaltet Laravel automatisch und unterbrechungsfrei auf `file` (Cache & Session) und `database` (Queue) um und protokolliert den Fallback gedrosselt in `system_logs`.
3. **PHP 8.5 Deprecation-Fix (`config/database.php`):**
   - Nutzt `defined('Pdo\Mysql::ATTR_SSL_CA') ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA`, wodurch die stündlichen PHP-8.5-Deprecation-Warnungen in den Mittwald-Cron-Logs eliminiert sind.

### 11.2 Behebung der Sprachabbrüche mitten im Satz & WebSocket-Disconnects (`v1.3-stable`)
In längeren Live-AI-Sitzungen (insb. nach ca. 120–150 Sekunden oder während mehrsätziger Antworten) trat das Problem auf, dass Funkira mitten im Satz aufhörte zu sprechen und im Log `client_ws_close (code=1006)` gefolgt von `google_ws_close (code=1000)` erschien. Folgende 6 Ursachen wurden identifiziert und behoben:

1. **Schutz der Audio-Wiedergabeschlange vor falschen Google-Interrupts (`ai-widget-part2.blade.php`):**
   - *Ursache:* Google sendete bei kurzen Raumgeräuschen oder internen Re-Evaluierungen während eines Satzes `serverContent.interrupted = true`. Der Client rief daraufhin `stopCurrentAudioPlayback()` auf und löschte alle bereits im Browser gepufferten `AudioBufferSourceNode`-Satzteile.
   - *Lösung:* `stopCurrentAudioPlayback()` wird bei `interrupted` nur noch ausgeführt, wenn `allowVoiceInterruption === true` **und** der Nutzer in den letzten `2500ms` tatsächlich selbst gesprochen hat (`userSpokeRecently`). Andernfalls spricht der Agent seinen Satz im WebAudio-Buffer vollständig und unterbrechungsfrei zu Ende.
2. **Verlängerte Raumhall-Schutzzeit (`650ms`) & `aiTurnGenerating`-Sperre in `isOutputActive()`:**
   - *Ursache:* Zwischen zwei von Google gestreamten Satz-Chunks entstand manchmal eine Lücke von >120ms. Dadurch wurde `isOutputActive()` kurzzeitig `false`, das Mikrofon öffnete sich für wenige Millisekunden und schickte den Nachhall des Lautsprechers an Google zurück (was wiederum `interrupted` auslöste).
   - *Lösung:* `roomEchoCushion` in `isOutputActive()` wurde von `120ms` auf `650ms` erhöht und zusätzlich `this.aiTurnGenerating` einbezogen. Solange Google noch am selben Turn generiert oder Audio im Raum nachhallt, bleibt das Mikrofon strikt stummgeschaltet.
3. **Beseitigung des 1200ms Noise-Gate-Sprungs (`onaudioprocess`):**
   - *Ursache:* 1,2 Sekunden nach Ende eines Satzes sprang der Mikrofon-Schwellenwert (`dynamicGate`) abrupt von `0.045` auf `0.004` (11-fache Empfindlichkeit). Normales Hintergrundrauschen schoss dadurch als plötzlicher Audio-Spike zu Google.
   - *Lösung:* Konstanter, sauberer Basis-Schwellenwert (`0.006`) im Normalbetrieb und `0.045` nur bei explizit aktiviertem Barge-In während aktiver Sprachausgabe.
4. **Deaktivierung der parallelen Browser-`SpeechRecognition` während `isLiveMode`:**
   - *Ursache:* Auf Desktop-Browsern lief `webkitSpeechRecognition` parallel zum 16 kHz WebAudio-Stream weiter und konkurrierte um den Audio-Thread.
   - *Lösung:* Da Gemini Live bereits eigene `inputTranscription`-Events liefert, wird die lokale Browser-`SpeechRecognition` während `isLiveMode` komplett pausiert.
5. **Bidirektionaler 15s WebSocket-Heartbeat (`server-twilio.js`) & `keepAudioAlive` bei Reconnect:**
   - *Ursache:* Nach ~150s schlossen Reverse-Proxies (Mittwald Ingress / Cloudflare) vermeintlich inaktive Verbindungen mit `Code: 1006`. Beim anschließenden Auto-Reconnect zerstörte `cleanupLiveConnection()` den `AudioContext`, wodurch die letzten gepufferten Wörter abgeschnitten wurden.
   - *Lösung:* `server-twilio.js` (`v1.3-stable`) sendet alle 15 Sekunden echte WebSocket-Protokoll-Pings (`googleWs.ping()` und `clientWs.ping()`). Zudem behält `cleanupLiveConnection(keepAudioAlive = true)` beim Auto-Reconnect den `AudioContext` und laufende `activeAudioSources` am Leben.
6. **Multi-Tool-Call-Schleife & Unterdrückung kaskadierender `Code: 1000`-Logs:**
   - Wenn Gemini mehrere Tools gleichzeitig aufruft (`functionCalls`), werden nun alle Calls in einer `for`-Schleife abgearbeitet (statt nur `functionCalls[0]`).
   - Schließt der Client zuerst die Verbindung (`1006`), unterdrückt `server-twilio.js` (`wasAlreadyCleanedUp`) den dadurch ausgelösten sekundären `Code: 1000`-Log von Google und filtert `sessionResumptionUpdate`-Nachrichten aus `recent_events`, damit die letzten 15 Diagnose-Ereignisse niemals durch Resumption-Updates überschrieben werden.

### 11.3 Live-Ping & Verbindungsstatus-Punkt im Seiten-Widget „Live-AI“ (`ai-widget-part1.blade.php`)
Im Header des Seiten-Widgets **„Live-AI“** (direkt neben dem Titel und der Modell-Auswahl) wurde ein kompakter Echtzeit-Status-Indikator mit Hover-Tooltip integriert:
- **Farbcodierter Status-Punkt:**
  - **Grün (pulsierend im Live-Modus):** Bridge online & bereit bzw. Live-WebSocket verbunden (`Verbunden (Live)` / `Bereit (Online)`).
  - **Gelb/Amber (animiert):** Verbindungsaufbau läuft (`Verbinde...`) oder erhöhte Latenz (`> 400 ms`).
  - **Rot:** Bridge nicht erreichbar oder Verbindung unterbrochen (`Nicht erreichbar`).
- **Detail-Tooltip beim Hovern:**
  Zeigt Verbindungsstatus, gemessenen **Ping in `ms`** (über `GET /api/ai/bridge-ping` → `http://127.0.0.1:8081/health`), **WebSocket-Status**, **Bridge-Version (`v1.3-stable`)** sowie die Anzahl aktiver Sessions.

### 11.4 Verstärkte Fehleranalyse & lückenloses Logging in `/admin/global-logs`
- **Automatische Ursachen-Diagnose (`diagnoseWsIssue` in `server-twilio.js`):**
  Ordnet jedem WebSocket-Close-Code (`1006`, `1007`, `1008`, `1009`, `1011`, `4001`, `4003`) automatisch eine präzise deutsche Fehlerkategorie, wahrscheinliche Ursache und Handlungsempfehlung zu.
- **Sitzungs-Telemetrie & Event-Ringpuffer (`buildSessionDiagnostics`):**
  Jeder ungewöhnliche Abbruch liefert in `system_logs` (`/admin/global-logs`) und bei `error`/`warning` per E-Mail:
  - Client-Typ (`Android App (OkHttp)` vs. `Web Browser`), IP, User-Agent, Agent-Name, User-ID, Chat-Session-ID
  - Angefordertes vs. aktives Gemini-Modell, `is_google_setup_complete`, Sitzungsdauer
  - Paket-Zähler (`client_audio_packets_sent`, `google_messages_received`, `google_audio_chunks_received`, `tool_calls_count`, `last_tool_call`)
  - Angewandte Schutz-Normalisierungen (`model_rewritten`, `media_chunks_converted`, `empty_interrupts_dropped`)
  - Chronologie der letzten 15 Protokoll-Ereignisse (`recent_events`) mit Millisekunden-Zeitstempeln vor dem Ausfall
- **Drei-Schichten-Erfassung nach `/admin/global-logs` (`SystemLog`):**
  1. **Laravel Backend & Queue/Cron (`bootstrap/app.php`):** Jede unbehandelte Exception wird inkl. Kategorie-Diagnose (`redis`, `database`, `gemini_api`, `mail`, `filesystem`) und Stack-Trace in `system_logs` gespeichert.
  2. **Node.js Bridge (`POST /api/ai/bridge-log`):** Alle Bridge-Warnungen, WebSocket-Abbrüche und Token-Fehler landen mit vollem Telemetrie-Payload in `/admin/global-logs`.
  3. **Browser Live-AI Frontend (`POST /api/ai/frontend-log`):** Mikrofon-/WebAudio-Fehler, Token-Fetch-Fehler und unerwartete WebSocket-Abbrüche im Browser werden direkt an `/admin/global-logs` gemeldet.

### 11.5 Umfangreiche Kalender-KI-Fähigkeiten (`.ics` E-Mail-Versand) & Entfernung unnötiger `ShouldQueue`-Abhängigkeiten
- **Neues KI-Tool `calendar_send_ics_email` (`AiCalendarFuncs.php`):**
  Ermöglicht KI-Agenten (wie Funkira), einzelne Termine, mehrere Termine per ID/Suchbegriff oder ganze Zeiträume (`start_date` bis `end_date`, z. B. Wochen-/Monatspläne) als RFC-5545-konforme `.ics`-Kalenderdatei (`VCALENDAR` mit `VEVENT`, `LOCATION`, `CATEGORIES`, `ORGANIZER`, `ATTENDEE`, `RRULE`, `VALARM` und `METHOD:REQUEST`) per E-Mail an beliebige Empfänger zu versenden.
- **Erweiterung von `calendar_create_event` & `calendar_update_event`:**
  Unterstützen nun direkt `recipient_email`, `recipient_name`, `location`, `custom_message` und `send_email`, um beim Anlegen oder Ändern eines Termins sofort die `.ics`-Einladung mitzuversenden.
- **Konsequente Beseitigung unnötiger `ShouldQueue`-Blockaden:**
  Da E-Mails und schnelle KI-Aktionen bei inaktivem Queue-Worker sonst in der `jobs`-Tabelle hängen blieben, wurden alle Einzel-Transaktions-Mailables (`CalendarEventCreated`, `SupportTicketCreatedMailToCustomer`, `SupportTicketUpdateMailToCustomer`, `NewOrderShippedToCustomer`, `PaymentReceivedMail`, `OrderPhotoIssueMailToCustomer`, `CreditNoteMailToCustomer`, `NewGiftVoucherToCustomer`, `ReferralRewardEarnedMail`, `NewsletterVerificationMail`, `RevocationMailToCustomer`, `RevocationMailToAdmin`, `NewCalcMailToCustomer`, `NewCalcMailToAdmin`, `AbandonedCartReminder`) sowie die KI-Funktionen `ManagementCalendarEvent::created`, `system_manage_logs`, `system_fix_errors (backup)`, `AiSupportFuncs` und `AiOrderFuncs` auf **sofortige synchrone Ausführung (`->send()` / `Artisan::call()`)** umgestellt.



