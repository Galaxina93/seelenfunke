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

## 5. Das Google Gemini Multimodal Live API Chaos
Nachdem die Server- und Netzwerkarchitektur stand, stießen wir auf massive, undokumentierte Änderungen seitens Google bezüglich der Gemini Live API (Stand: April 2026).

### A. Veraltete Modelle & Endpunkte (Fehler 1008)
Twilio verband sich erfolgreich mit unserem Node-Server, doch die WebSocket-Verbindung zu Google brach sofort mit dem Fehler **"Code 1008: is not supported for bidiGenerateContent"** ab.
**Erkenntnis:** Das anfänglich genutzte experimentelle Modell `models/gemini-2.0-flash-exp` wurde von Google komplett aus der Live API entfernt. Auch das Standardmodell `models/gemini-2.0-flash` auf dem `v1beta`-Endpunkt unterstützt kein bidirektionales Audio-Streaming (BidiGenerateContent). 
**Die Lösung:** Google hat die Audio-API stillschweigend auf ein neues Preview-Modell verlagert. Die korrekte Kombination für das Live-Streaming lautet nun zwingend:
- **Endpunkt:** `v1alpha`
- **Modell:** `models/gemini-3.1-flash-live-preview`

### B. Veraltete Audio-Payload Syntax (Fehler 1007)
Nachdem das richtige Modell gefunden war, gab Google den Fehler **"Code 1007: realtime_input.media_chunks is deprecated. Use audio, video, or text instead."** aus.
**Erkenntnis:** Google hat die Syntax für die Übermittlung von Echtzeit-Audio geändert. Das Konstrukt mit einem Array (`mediaChunks: [{ mimeType: ..., data: ... }]`) wird vom 3.1-Modell strikt abgelehnt.
**Die Lösung:** Das Audio-Paket muss direkt als Objekt übergeben werden:
```json
realtimeInput: {
    audio: {
        mimeType: "audio/pcm;rate=8000",
        data: pcmBase64
    }
}
```

## 6. Das Phantom-Caching (Warum Updates nicht zogen)
Ein massives Hindernis bei der Fehlersuche war, dass Änderungen am Code (z.B. der Wechsel von `mediaChunks` zu `audio`) über Tage hinweg scheinbar vom Server ignoriert wurden. Die API warf weiterhin den "deprecated" Fehler.

**Die Ursache:**
Der Versuch, den Node-Prozess über die SSH-Konsole mittels `kill -9` neu zu starten, schlug stillschweigend fehl. Auf dem Mittwald mStudio laufen Apps in strikt isolierten Containern. Die SSH-Umgebung hat keinen Zugriff auf den Container der Node-App. 
Das bedeutet: Obwohl Dateien per `git pull` oder `cp` auf der Festplatte aktualisiert wurden, lief der alte Node.js-Prozess ununterbrochen im Arbeitsspeicher weiter und verarbeitete Anrufe mit dem veralteten Code.

**Die finale Lösung:**
Um Änderungen an der Node.js App wirksam zu machen, MUSS die App zwingend über das **Mittwald-Dashboard** in der Weboberfläche neu gestartet (bzw. bei tiefgreifenden Ordner-Änderungen komplett neu angelegt) werden. Erst durch diesen harten Neustart des Containers zieht sich der Node-Prozess die aktuellen Dateien von der Festplatte.

## 7. Fazit & Aktueller Status
Der hartnäckige `503 Service Unavailable` Fehler war ein Infrastruktur-Problem (Symlinks & Container-Isolation auf Mittwald), welches durch ein gemeinsames App-Verzeichnis gelöst wurde.
Die anschließenden Verbindungsabbrüche (Code 1008 & 1007) wurden durch radikale, teils undokumentierte API-Änderungen seitens Google (Modell-Deprecation & Syntax-Änderung) verursacht. 

Durch das Brute-Forcing der Google API Endpunkte haben wir nun die exakte, aktuelle Konfiguration (`v1alpha` + `gemini-3.1-flash-live-preview` + neues `audio` Objekt) implementiert. Das letzte Hindernis – die Container-Isolation, die Code-Updates blockierte – wurde durch einen harten Neustart der App im Mittwald-Dashboard umgangen. 

Die Node.js Bridge steht nun stabil, nimmt Audio-Chunks von Twilio entgegen, kommuniziert fehlerfrei mit Gemini Live und überträgt die Antworten nahtlos an den Anrufer. Mission erfüllt!
