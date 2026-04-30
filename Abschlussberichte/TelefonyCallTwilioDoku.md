# Architektur- & Abschlussbericht: Twilio AI Telefony Bridge auf Mittwald mStudio

## 1. Zusammenfassung der Ausgangslage
Beim Versuch, eine dedizierte Node.js-App (`seelenfunke-nodejs`) für den Twilio WebSocket-Stream (`wss://twilio.mein-seelenfunke.de`) auf Mittwald zu betreiben, kam es persistent zu Verbindungsabbrüchen. 
Twilio meldete wiederholt: **"Stream - WebSocket - Handshake Error"** (erwartet HTTP 101, erhielt 503). 
Manuelle Aufrufe der Domain lieferten stets ein `503 Service Unavailable`.

## 2. Chronologie der Fehlersuche & Erkenntnisse

### A. Das IPv6 / Port-Binding Problem
Ursprünglich versuchte das Skript `server-twilio.js`, ohne explizite IP-Angabe zu lauschen. Node.js wählt dabei standardmäßig IPv6 (`::`). Der Mittwald Nginx-Loadbalancer (der IPv4 spricht) konnte den Node-Prozess dadurch nicht finden. 
**Erkenntnis:** Das Skript muss zwingend auf `0.0.0.0` lauschen.

### B. Die SSH-Container vs. App-Container Isolation
Bei der Fehleranalyse über die SSH-Konsole stellten wir fest, dass Befehle wie `killall node` ins Leere liefen und `ps aux` keine Node-Prozesse (außer dem PM2 Manager) auflistete.
**Erkenntnis:** Mittwald nutzt eine strikte Container-Isolierung. Die SSH-Sitzung läuft in einem völlig anderen Pod/Container als die eigentliche Live-App im Hintergrund. Lokale Tests (z.B. `PORT=8080 node server-twilio.js`) spiegeln nicht zwingend die Hintergrund-Realität wider, da im SSH-Container z.B. Port 9000 durch PHP-FPM blockiert wird, im leeren Node-Container jedoch nicht.

### C. Das Symlink-Jail (Die wahre Ursache für den 503-Fehler)
Um Code-Duplizierung zu vermeiden, hatten wir die Dateien (`server-twilio.js`, `.env`, `package.json`, `node_modules`) aus dem Hauptprojekt (`seelenfunke-stage`) per Symlink in das Installationsverzeichnis der Node-App (`/html/twilio-bridge`) verknüpft.
**Der fatale Fehler:** Mittwald sperrt jede App in einen "Käfig" (Chroot-Umgebung). Der Käfig der Node.js App begann exakt bei `/html/twilio-bridge`. Alle Symlinks, die auf `../seelenfunke-stage` verwiesen, zeigten somit aus dem Käfig heraus. 
**Ergebnis:** Innerhalb des App-Containers waren alle Symlinks *tot* (Broken Links). Als Mittwald die App starten wollte, gab es sofort einen unsichtbaren `MODULE_NOT_FOUND` Crash, da die Datei `server-twilio.js` schlichtweg nicht existierte.

## 3. Die finale Lösung & Architektur

Anstatt Dateien mühsam zu kopieren oder fehleranfällige Workarounds zu bauen, nutzen wir eine native Eigenschaft des Mittwald mStudios: **Mehrere Apps dürfen dasselbe Installationsverzeichnis nutzen.**

### Die saubere Architektur:
1. **Löschung des Bridge-Ordners:** Der künstliche Ordner `/html/twilio-bridge` wurde komplett verworfen.
2. **Neuerstellung der Node.js App:** Die App `seelenfunke-nodejs` wurde neu erstellt.
3. **Gemeinsames Verzeichnis:** Als Installationsverzeichnis wurde direkt der Hauptordner der Laravel-App zugewiesen: **`/html/seelenfunke-stage`**.
4. **Verknüpfung:** Die Domain `twilio.mein-seelenfunke.de` wurde mit dieser neuen Node.js-App verknüpft.
5. **Startbefehl:** `node server-twilio.js`

### Warum das perfekt funktioniert:
- **Keine Symlinks:** Node.js startet direkt im Hauptprojekt und hat nativen Zugriff auf die originale `.env` Datei und die echten `node_modules`.
- **Automatische Port-Zuweisung:** Mittwald weist der Node-App im Hintergrund automatisch einen freien Port zu (z.B. `PORT=3000`) und übergibt diesen als Umgebungsvariable.
- Unser Code (`const PORT = process.env.PORT || 8081;`) greift diesen Port ab und lauscht auf `0.0.0.0`.
- Der Nginx-Loadbalancer von Mittwald routet den Traffic von `twilio.mein-seelenfunke.de` fehlerfrei an genau diesen internen Port weiter.

## 4. Fazit
Der hartnäckige `503 Service Unavailable` Fehler war kein Netzwerkfehler, sondern ein `MODULE_NOT_FOUND` Crash beim Booten, verursacht durch tote Symlinks innerhalb der isolierten Mittwald Container-Umgebung. 
Durch das Zusammenlegen des Installationsverzeichnisses von PHP-App und Node-App auf `/html/seelenfunke-stage` ist die Infrastruktur nun hochgradig robust, pflegeleicht (da keine Dateien kopiert werden müssen) und der WebSocket-Stream für Twilio steht fehlerfrei zur Verfügung.

## 5. Status des Audio-Streams (Der Anruf)
Nachdem die Verbindungs-Architektur erfolgreich korrigiert wurde, zeigten die Tests:
- **Verbindung:** Twilio verbindet sich fehlerfrei mit dem Node.js Skript (keine 503 Fehler mehr).
- **Tracking:** Das Skript hält die Verbindung aufrecht und trackt die korrekte Anrufdauer (z.B. 20 Sekunden).
- **Audio-Übertragung:** Derzeit bleibt das Handy stumm. Dies belegt, dass die bidirektionale Serververbindung zwar steht, aber das Audio-Encoding (8kHz muLaw für Twilio) oder das Trigger-Verhalten von Gemini (Warten auf Voice Activity vs. initiales 'turnComplete') im `server-twilio.js` Skript noch feinjustiert werden muss.
- **Nächster Schritt:** Implementierung eines internen Loggers im Skript, um zu prüfen, ob Gemini reine Text-Antworten oder korrekte `audio/pcm` Chunks zurückschickt, und Optimierung der `WaveFile` Resampling-Logik.
