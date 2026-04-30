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

## 3. Server-Einrichtung (Mittwald Stage Server)

Da für die Echtzeit-Bridge ein laufender Node.js Prozess nötig ist, muss dieser auf dem Live/Stage-Server (Mittwald) dauerhaft aktiv sein.

### 3.1 Node.js nativ aktivieren
Da Mittwald eine "Chroot"-Umgebung in SSH-Sitzungen verwendet, funktioniert das manuelle Installieren via NVM oft nicht (Bibliotheks-Fehler).
Lösung:
1. Im Mittwald **mStudio** in den Bereich *Software / Apps* gehen.
2. Die App **"Node.js"** installieren (Empfohlen: Version 20.x LTS oder 22.x).
3. Nach der Installation das aktuelle SSH-Fenster schließen und neu verbinden.

### 3.2 Twilio-Bridge starten (PM2)
Sobald Node.js aktiv ist, können die benötigten Pakete installiert und der Server gestartet werden:
```bash
# 1. In Projekt navigieren
cd ~/html/seelenfunke-stage

# 2. Abhängigkeiten installieren
npm install ws dotenv wavefile

# 3. Server dauerhaft im Hintergrund mit PM2 starten
npx pm2 start server-twilio.js --name "twilio-bridge"

# 4. Status des Servers für Reboots sichern
npx pm2 save
```

Wartungsbefehle für PM2:
- Logs live ansehen: `npx pm2 logs twilio-bridge`
- Server neustarten: `npx pm2 restart twilio-bridge`
- Server stoppen: `npx pm2 stop twilio-bridge`

## 4. Wichtige Environment-Variablen (.env)
Damit alles funktioniert, müssen folgende Parameter zwingend in der `.env` Datei vorhanden sein:

```env
# Twilio Zugangsdaten für die Festnetz-Infrastruktur
TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
TWILIO_PHONE_NUMBER=+49...

# Die öffentliche URL für den Node.js WebSocket.
# Auf dem Stage Server: wss://stage.mein-seelenfunke.de:8081/twilio-stream
# Lokal (beim Testen via Laptop): wss://<ngrok-id>.ngrok-free.app/twilio-stream
TWILIO_WSS_URL=wss://stage.mein-seelenfunke.de:8081/twilio-stream

# API Keys für Gemini 2.0 Multimodal Live & Analyse
GEMINI_API_KEY=AIza...
GOOGLE_API_KEY=AIza...
```

> **Achtung zur Firewall:** Sollte der Anruf zustande kommen, aber direkt stumm auflegen, bedeutet dies, dass Twilio keine Verbindung zum WebSocket aufbauen konnte. In diesem Fall muss in den Mittwald Firewall-Regeln (oder Nginx Proxy) sichergestellt werden, dass Port **8081** von außen via WSS erreichbar ist.

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
