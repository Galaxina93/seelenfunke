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
**Ergebnis:** Innerhalb des App-Containers waren alle Symlinks *tot* (Broken Links). Als Mittwald die App starten wollte, gab es sofort einen unsichtbaren `MODULE_NOT_FOUND` Crash, da die Datei `server-twilio.js` schlichtweg nich## 3. Die finale Lösung & Architektur

Anstatt das Installationsverzeichnis von Laravel und Node.js zu mischen (was zu Konflikten führen kann), nutzen wir einen dedizierten App-Ordner (`/html/twilio-bridge`), in den wir die aktualisierten Dateien bei jedem Deployment hart hineinkopieren.

### Die saubere Architektur & Deployment-Workflow:
Da Mittwald Node.js-Apps in strikt isolierten Containern betreibt, schlagen herkömmliche SSH-Neustarts (wie `kill -9`) fehl. Um sicherzustellen, dass die App wirklich den neuesten Code zieht, muss folgender strikter Workflow bei jedem Update eingehalten werden:

1. **App komplett löschen:** Die alte Node.js-App `seelenfunke-nodejs` im Mittwald-Dashboard restlos löschen.
2. **App neu erstellen:** Eine neue App `seelenfunke-nodejs` anlegen mit dem Installationsverzeichnis `/html/twilio-bridge`.
3. **Domain verknüpfen:** Die Domain `twilio.mein-seelenfunke.de` mit der neuen App verknüpfen.
4. **Dateien kopieren:** Über die SSH-Konsole die aktuellen Dateien aus dem Stage-Projekt in den Bridge-Ordner kopieren:
   ```bash
   cd /html/twilio-bridge
   cp ../seelenfunke-stage/server-twilio.js .
   cp ../seelenfunke-stage/.env .
   cp ../seelenfunke-stage/package.json .
   cp -r ../seelenfunke-stage/node_modules .
   ```
5. **Startbefehl:** Mittwald führt im Hintergrund `node server-twilio.js` aus. Unser Code (`const PORT = process.env.PORT || 8081;`) greift den von Mittwald vergebenen Port ab und lauscht auf `0.0.0.0`.

## 4. Fazit Architektur
Der hartnäckige `503 Service Unavailable` Fehler war kein Netzwerkfehler, sondern ein `MODULE_NOT_FOUND` Crash beim Booten, verursacht durch tote Symlinks innerhalb der isolierten Mittwald Container-Umgebung. Durch das harte Kopieren der Dateien (`cp` statt `ln -s`) und die strikte Trennung der Ordner ist die Infrastruktur nun hochgradig robust.

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

## 6. Das Phantom-Caching (Warum Updates scheiterten)
Ein massives Hindernis bei der Fehlersuche war, dass Änderungen am Code (z.B. der Wechsel von `mediaChunks` zu `audio`) über Tage hinweg scheinbar vom Server ignoriert wurden. Die API warf weiterhin den "deprecated" Fehler.

**Die Ursache:**
Der Versuch, den Node-Prozess über die SSH-Konsole mittels `kill -9` neu zu starten, schlug stillschweigend fehl. Die SSH-Umgebung hat keinen Zugriff auf den Container der Node-App. Das bedeutet: Obwohl Dateien per `git pull` oder `cp` auf der Festplatte aktualisiert wurden, lief der alte Node.js-Prozess ununterbrochen im Arbeitsspeicher weiter.

**Die finale Lösung:**
Siehe Workflow in **Punkt 3**. Ein normaler Neustart oder SSH-Befehl reicht nicht aus. Die App **MUSS im Mittwald-Dashboard komplett gelöscht und neu angelegt werden**, um einen sauberen Container-Boot mit den neuen Dateien (`cp`) zu erzwingen.

## 7. Der Audio-Codec Bug (Rauschen statt Stimme)
Nachdem die Verbindung zu Gemini endlich stand, antwortete die KI zwar korrekt auf Sprach-Prompts, am Telefon (Twilio) kam jedoch nur ein extrem lautes Kratzen/Rauschen an.

**Die Ursache:**
Google sendet die Audio-Antwort als rohe Bytes (16-bit PCM mit 24kHz). Unsere Konvertierungs-Bibliothek (`wavefile`) benötigt für 16-bit PCM zwingend ein `Int16Array`. Wir haben stattdessen direkt den Node.js `Buffer` (welcher ein Array aus 8-bit Bytes ist) an die `fromScratch()` Methode übergeben. Dadurch hat die Bibliothek **jedes einzelne Byte** fälschlicherweise als eigenes 16-bit Sample interpretiert (und mit Nullen aufgefüllt). Das Audio wurde dadurch künstlich auf die doppelte Länge gestreckt und die Kurve zerrissen (Quantisierungsrauschen).

**Die finale Lösung:**
Der Node.js Buffer muss vor der Übergabe explizit in ein `Int16Array` gegossen werden:
```javascript
const pcmBuffer = Buffer.from(part.inlineData.data, 'base64');
const int16Data = new Int16Array(pcmBuffer.buffer, pcmBuffer.byteOffset, pcmBuffer.length / 2);
let wav = new WaveFile();
wav.fromScratch(1, 24000, '16', int16Data);
```

## 8. Fazit & Aktueller Status
Der hartnäckige `503 Service Unavailable` Fehler war ein Infrastruktur-Problem (Symlinks & Container-Isolation auf Mittwald), welches durch den strikten "Löschen & Kopieren" Workflow gelöst wurde. Die Verbindungsabbrüche (Code 1008 & 1007) wurden durch radikale API-Änderungen seitens Google verursacht. Das letzte Hindernis – das statische Rauschen – war ein Type-Casting Fehler im Audio-Buffer.

Durch die exakte Konfiguration (`v1alpha` + `gemini-3.1-flash-live-preview`), das korrekte Casting des Audio-Buffers in ein `Int16Array` und die konsequente Container-Neuerstellung bei Code-Updates ist die Node.js Bridge nun zu 100% stabil. Sie nimmt Audio-Chunks von Twilio entgegen, kommuniziert fehlerfrei mit Gemini Live und überträgt glasklare Antworten nahtlos an den Anrufer. Mission erfüllt!
