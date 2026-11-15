# Abschlussbericht: KI Telefonie-Infrastruktur (Twilio + Gemini)

Dieses Dokument fasst die gesamte Einrichtung der Echtzeit-Audio-Bridge und der automatisierten Anrufauswertung zusammen. Es dient als Leitfaden für die Architektur, die Server-Einrichtung und als Nachschlagewerk für zukünftige Wartungsarbeiten.

## 1. Architektur der Telefonie-Bridge
Die Lösung baut auf zwei Kernsystemen auf, die miteinander kommunizieren:

1. **Das Laravel Backend (Steuerung & Datenhaltung)**
   - Startet Anrufe via Twilio API (`triggerTwilioCall`).
   - Versorgt die KI mit System-Prompts, Kalenderdaten und Kontaktprofilen via TwiML-Parametern.
   - Evaluiert am Ende des Anrufs das Transkript mittels Gemini und speichert das Fazit in der Datenbank.
2. **Die Node.js Audio-Bridge (`server-twilio.js`)**
   - Agiert als WebSocket-Mittelsmann zwischen dem Twilio-Telefonnetz und der Google Gemini Live API.
   - Wandelt Twilio's 8kHz mu-Law Audio in 16kHz/24kHz PCM für Gemini um (und umgekehrt).
   - Registriert Unterbrechungen (VAD - Voice Activity Detection) durch den menschlichen Gesprächspartner und bricht den aktuellen Sprach-Stream der KI ab.
   - Sendet beim Auflegen das gesamte Transkript an das Laravel Backend.

## 2. Anpassungen im Code (Was wurde umgesetzt)
- **TwiML Bypass:** Twilio blockiert Outbound-Anrufe, wenn der HTTP-Webhook (`/api/twilio/outbound`) nicht aus dem Internet erreichbar ist (z. B. auf lokalen `.test` Domains). Dies wurde behoben, indem das benötigte TwiML (`<Connect><Stream>`) direkt beim Erstellen des Anrufs (`$client->calls->create`) als XML übergeben wird.
- **Fehlervalidierung:** Das Laravel Backend fängt explizite API-Fehler von Twilio ab (z. B. fehlendes Guthaben, falsche Nummer) und gibt diese im Klartext als Tool-Error an den KI-Agenten zurück, sodass dieser Fehler intelligent kommunizieren kann.
- **Call-Log & Analyse:** Die Tabelle `support_telephony_calls` speichert den kompletten Verlauf. Der neue Endpunkt `/api/twilio/call-log` schickt das Transkript zusammen mit dem Ziel (`objective`) des Anrufs an Gemini 2.5 Flash. Daraus generiert Gemini ein kurzes Fazit (`summary`) und nächste Aufgaben (`next_steps`), die im Support-Dashboard angezeigt werden.
- **Multitasking Blueprint:** In `GeminiAgent.php` wurde das Limit (Slicing auf max. 1 Tool) entfernt. Der Agent durchläuft jetzt eine native Event-Loop, um **mehrere Werkzeuge parallel/nacheinander** auszuführen, bevor er an den Nutzer antwortet.
- **Funki CLI:** Der Befehl `funki telefon` wurde hinzugefügt, um den Node.js Server lokal schnell starten zu können.

## 3. Server-Einrichtung (Mittwald mStudio Container-Architektur)

> [!IMPORTANT]
> **AKTUALISIERTER PRODUKTIV-AUFBAU (KEIN `PM2` & KEIN PORT `:8081` AUF MITTWALD):**  
> Die früheren Entwürfe mit `npx pm2` und `wss://stage.mein-seelenfunke.de:8081` sind auf Mittwald mStudio **abgelöst**, da Mittwald jede App in einem eigenen Chroot-Container mit eigenem Prozess-Supervisor (`mittnite`) kapselt.  
> Der aktive Aufbau besteht aus drei isolierten Containern im Projekt `p-g27wim` (vollständige Matrix & Schaubild siehe [`Support_Twilio_Call_Dokumentation.md`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/Support/Support_Twilio_Call_Dokumentation.md)):
> 1. **Web-App (`a-lc6tkm`, `/html/seelenfunke-stage`):** Hostet `https://stage.mein-seelenfunke.de` (`/api/twilio/inbound`, `/api/twilio/call-log`) und proxyt `ws.mein-seelenfunke.de` per `.htaccess` auf `a-iurgq8:6001`.
> 2. **PHP-Worker Websocket (`a-iurgq8`, `/html/worker-stage-3`, SSH `kontakt@mein-seelenfunke.de@a-iurgq8`):** Führt `php artisan reverb:start` inkl. der primären Twilio-Telefonie-Bridge (`wss://ws.mein-seelenfunke.de/twilio-stream`, `v2.0-reverb-live-secretary`) unter `mittnite` aus.
> 3. **Node.js App (`a-yeq7el`, `/html/twilio-bridge`, SSH `kontakt@mein-seelenfunke.de@a-yeq7el`):** Führt `node server-twilio.js` (`v1.4-live-secretary`) unter `mittnite` für `wss://api-live-bridge.mein-seelenfunke.de/gemini-live` (sowie `/twilio-stream` und `/health`) aus.
>
> **Deployment & Neustart (vollautomatisch):**
> ```bash
> ./funki stage-deploy
> ```
> Synchronisiert per `./funki sync-worker` alle Dateien physisch (`cp`/`rsync`, keine Symlinks!) samt `GEMINI_BRIDGE_SECRET` und startet anschließend beide Container (`a-iurgq8` und `a-yeq7el`) direkt via `mittnitectl job restart` neu. Einzel-Neustart jederzeit über `FunkenwerkMcp.exe --stage-restart-workers` oder in **Funkenwerk SSH** (Profile `Mittwald Websocket` bzw. `Mittwald Node.js App` -> `mittnitectl job restart`).

## 4. Wichtige Environment-Variablen (.env)
Damit alles funktioniert, sind folgende Parameter in der `.env` hinterlegt:

```env
# Twilio Zugangsdaten für die Gifhorner Festnetznummer (+49 5371 7269023)
TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
TWILIO_PHONE_NUMBER=+4953717269023

# Öffentliche WebSocket-URLs auf Mittwald mStudio:
# Primäre Telefonie-Sekretärinnen-Bridge (Reverb-Worker a-iurgq8):
TWILIO_WSS_URL=wss://ws.mein-seelenfunke.de/twilio-stream
# Dedizierte Node.js Live-AI-Bridge (Node-App a-yeq7el):
GEMINI_PROXY_WS_URL=wss://api-live-bridge.mein-seelenfunke.de/gemini-live

# Gemeinsames Container-Secret zwischen Stage, Reverb-Worker & Node.js-Bridge
GEMINI_BRIDGE_SECRET=...

# API Keys für Gemini 3.1 Flash Live (Aoede) & Gemini 2.5 Flash Call-Analyse
GEMINI_API_KEY=AIza...
GOOGLE_API_KEY=AIza...
```

---

## 5. System Dokumentation: Native Twilio Media Streams Integration

### 5.1 Architektur: Der Hardcore-Weg
Anstatt Drittanbieter-Wrapper zu nutzen, bauen wir die Infrastruktur komplett selbst auf. Seelenfunke nutzt **Twilio Media Streams**, um das rohe Telefonnetz in Echtzeit nativ mit der **Google Gemini Multimodal Live API** zu verbinden.

### 5.2 Ablauf eines nativen Anrufs
1. **Call Initiation & TwiML:** Ein Agent triggert das Backend. Laravel sagt der Twilio REST API: "Ruf an!". Wenn abgehoben wird, sendet Laravel einen TwiML `<Connect><Stream>` Befehl an Twilio.
2. **Die Audio-Bridge (WebSocket):** Twilio öffnet einen WebSocket zu unserer eigenen Audio-Bridge (Node.js). Diese Bridge übersetzt das rohe Base64 Audio-Format (mulaw 8000Hz) aus dem Telefonnetz und leitet es in Echtzeit an die Google Gemini API weiter.
3. **Interruption Handling & Transkript:** Sobald der Mensch der KI ins Wort fällt, erkennt die Bridge dies (Voice Activity Detection) und zwingt Twilio den Audio-Puffer zu leeren (`<Clear>`). Am Ende des Gesprächs speichert die Bridge das mitgeschriebene Transkript sicher in der Laravel-Datenbank.

### 5.3 Sicherheit & Kostenkontrolle
* **Volle Kontrolle:** Da wir keine externen Voice-Plattformen nutzen, zahlen wir nur die reinen Infrastruktur-Kosten (Twilio Minuten-Preise + Google Gemini Token).
* **Limits beachten:** Trotzdem gilt: Das Tages-Kosten-Limit sowie der "Nachtruhe"-Switch in den Einstellungen sollten stets aktiv bleiben, um teure Endlos-Schleifen der KI im Telefonnetz zu verhindern!

### 5.4 Preisübersicht: So setzen sich die Kosten zusammen
1. **Rufnummer (Twilio):** ~ 1,15 € / Monat (Fix). Für eine lokale deutsche Festnetznummer, über die die KI erreichbar ist und nach außen telefoniert.
2. **Telefonie (Twilio):** ~ 0,02 € / Minute (Outbound). Für ausgehende Anrufe ins deutsche Festnetz. *(Achtung: Anrufe in Mobilfunknetze kosten meist ca. 0,08 € bis 0,09 € pro Minute).*
3. **Audio-Stream:** ~ 0,004 € / Minute. Der Preis von Twilio "Media Streams", um das Live-Audio über den WebSocket an unseren eigenen Server durchzureichen.
4. **KI-Gehirn (Google):** Variabel. Die Gemini Live API wird in Token (Input/Output Audio) abgerechnet. Abhängig davon, wie viel die KI spricht und zuhört.
