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

> [!CAUTION]
> **OBLIGATORISCHER DEPLOYMENT-WORKFLOW**
> Da Mittwald Node.js-Apps in strikt isolierten Containern betreibt, schlagen herkömmliche SSH-Neustarts (wie `kill -9`) fehl. Um sicherzustellen, dass die App wirklich den neuesten Code zieht, **MUSS** folgender strikter Workflow bei *jedem* Update zwingend eingehalten werden:

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

## 8. Das Problem der "Stille" (API-Tücken und Initialer Prompt)

Nach der Behebung des statischen Rauschens waren beide Seiten der Leitung komplett stumm. Gemini antwortete nicht und schien den Anrufer nicht zu hören.

### A. 8kHz Audio & Resampling
Zunächst wurde vermutet, dass die Stille daran lag, dass Gemini 3.1 Flash Live nativ 16kHz erwartet und das 8kHz Audio von Twilio ablehnt. Ein Blick in die offizielle Dokumentation zeigt jedoch: *"Die Live API führt bei Bedarf jedoch eine Resampling durch, sodass jede Abtastrate gesendet werden kann."*
**Erkenntnis:** Ein teures manuelles Upsampling auf 16kHz im Node.js Server ist unnötig! Es reicht völlig aus, das 8kHz PCM Audio zu senden und den `mimeType` korrekt auf `"audio/pcm;rate=8000"` zu deklarieren. Gemini übernimmt das Resampling intern.

### B. Natürlicher Gesprächsfluss (Warten auf das erste "Hallo")
Zunächst wurde versucht, die KI zu zwingen, sofort beim Verbindungsaufbau loszusprechen (via `clientContent` oder `realtimeInput`). Dies führte jedoch zu Kollisionen: Wenn der Angerufene "Hallo" sagte, während die KI gerade ihren erzwungenen ersten Satz begann, interpretierte die KI dies als Unterbrechung (`interrupted: true`) und stoppte abrupt.

**Die Lösung (Flüssige, menschliche Dynamik):**
Wir verzichten komplett auf erzwungene Start-Prompts! Das Gemini-Modell arbeitet ohnehin bidirektional. 
1. Der `systemPrompt` instruiert die KI nun: *"Warte absolut still ab, bis der Angerufene sich meldet."*
2. Sobald der Angerufene abhebt und sich meldet (z.B. "Schmidt?"), greift Geminis Voice Activity Detection (VAD).
3. Die KI reagiert nativ auf diese Begrüßung und stellt sich vor: *"Hallo, hier spricht der KI-Agent von..."*.

Dieses "Warten auf den Angerufenen" entspricht der exakten Dynamik eines echten Telefonats und eliminiert jegliches Verschlucken oder fehlerhafte Unterbrechungen beim Gesprächsstart. Gleichzeitig wurde die KI angewiesen, bei echten Unterbrechungen kurz innezuhalten, aber danach konsequent zum ursprünglichen Ziel/Plan zurückzukehren.

## 9. Fazit & Aktueller Status
Der hartnäckige `503 Service Unavailable` Fehler war ein Infrastruktur-Problem (Symlinks & Container-Isolation auf Mittwald), welches durch den strikten "Löschen & Kopieren" Workflow gelöst wurde. Die Verbindungsabbrüche (Code 1008 & 1007) wurden durch radikale API-Änderungen seitens Google verursacht. Das letzte Hindernis – das statische Rauschen – war ein Type-Casting Fehler im Audio-Buffer.

Durch die exakte Konfiguration (`v1alpha` + `gemini-3.1-flash-live-preview`), das korrekte Casting des Audio-Buffers in ein `Int16Array`, die Nutzung von `realtimeInput` für den initiierenden Text-Prompt und die konsequente Container-Neuerstellung bei Code-Updates ist die Node.js Bridge nun zu 100% stabil. Sie nimmt Audio-Chunks von Twilio entgegen, kommuniziert fehlerfrei mit Gemini Live und überträgt glasklare Antworten nahtlos an den Anrufer. Mission erfüllt!

## 10. Das Problem des abgeschnittenen Call-Endes (toolCall "end_call")
Als die KI in der Lage war, Anrufe proaktiv zu beenden (durch Nutzung des `end_call` Tools), trat ein neues Problem auf: Die KI verabschiedete sich (z.B. "Das klingt ja wunderbar..."), aber der Anruf wurde **mitten im Satz abgebrochen**.

**Die Ursache:**
Die Gemini Live API arbeitet asynchron und streamt Audio-Chunks (Base64) an unseren Server, während sie gleichzeitig Metadaten wie `toolCall` sendet. Sobald die KI beschließt aufzulegen, wird der `toolCall` oft **gesendet, bevor die letzten Audio-Chunks vollständig übermittelt wurden**. Wenn wir den Twilio-WebSocket sofort beim Eintreffen des `toolCall` schließen, verwirft Twilio den gesamten Audio-Puffer und der Anrufer hört das Ende des Satzes nicht mehr.

**Die finale Lösung (Graceful Hangup mit Twilio Mark-Event):**
1. Wenn der `toolCall` `end_call` eintrifft, merken wir uns lediglich die Absicht (`shouldEndCall = true;`), schließen die Verbindung aber **nicht** sofort. Zudem bestätigen wir Gemini den Tool-Aufruf, damit die API nicht blockiert.
2. Wir warten, bis Gemini das Flag `{"serverContent":{"turnComplete":true}}` sendet. Das signalisiert, dass Gemini alle Audio-Chunks für diesen Satz an uns übergeben hat.
3. Sobald wir `turnComplete` erhalten und `shouldEndCall` aktiv ist, senden wir ein spezielles **Mark-Event** an Twilio (`{"event": "mark", "mark": {"name": "end_of_call"}}`).
4. Twilio reiht dieses Mark-Event hinter den bereits empfangenen Audio-Puffer ein. Erst wenn Twilio die Audio-Wiedergabe bis genau zu diesem Punkt **abgeschlossen** hat, sendet Twilio uns das Mark-Event zurück.
5. Wenn wir von Twilio das Event `msg.event === 'mark'` erhalten, wissen wir absolut sicher, dass der Anrufer den letzten Ton gehört hat, und rufen `ws.close()` auf. Das Gespräch wird sauber und ohne Abschneiden beendet.

## 11. Enterprise UI & Datenintegrität (Anruf-Historie)
Nachdem die technische Stabilität der Telefongespräche erreicht war, lag der Fokus auf der Optimierung der Datenbank-Logik und der Darstellung im Dashboard (Livewire UI).

### A. Vermeidung doppelter Datenbankeinträge
Zuvor wurde bei der Erstellung des *Anruf-Plans* (durch die AI) ein Datenbankeintrag im Status `planned` angelegt. Wenn der Anruf dann durch Twilio beendet und das Protokoll an das Backend gesendet wurde, konnte das Backend diesen Plan nicht zuordnen und erstellte einen **neuen Datensatz** für das Ergebnis.
**Lösung:**
1. Die `planned_call_id` wird nun von der Laravel-Planungslogik über den Twilio-Stream (als `<Parameter>`) direkt in die Node.js-Bridge eingespeist.
2. Der Node.js-Server sendet diese `planned_call_id` am Ende des Anrufs im Payload an den `/api/twilio/call-log` Endpunkt zurück.
3. Der `TwilioCallController` sucht anhand dieser ID den bestehenden `planned` Datensatz und aktualisiert diesen, statt einen neuen anzulegen.

### B. Strukturierte Auswertung & Enterprise UI
Statt eines einfachen Text-Fazits wird die Auswertungs-KI nun per Prompt gezwungen, das ursprüngliche Ziel (Objective) in eine strukturiertes Checklisten-Array (JSON) herunterzubrechen:
`{"summary": "...", "next_steps": ["..."], "goals": [{"task": "Alter abfragen", "achieved": true}]}`
Um aufwändige und riskante Datenbank-Migrationen auf dem Live-System zu vermeiden, werden diese `goals` und die `next_steps` elegant zusammen im bereits vorhandenen JSON-Feld `next_steps` gespeichert.

**Dashboard UI Updates:**
- **Gesprächsprotokoll:** Das Transkript wird nicht mehr als einfache Liste gerendert, sondern als modernes **Chat-Bubble-Interface** (ähnlich iMessage/WhatsApp), bei dem die KI und der Anrufer farblich und räumlich (links/rechts) getrennt sind.
- **Zielerreichung (Checkliste):** Die strukturierten Ziele werden mit visuellen Badges dargestellt (grünes Tailwind-Checkmark für erreichte Ziele, rotes Kreuz für verfehlte Ziele).
- Dadurch wirkt die Auswertung deutlich professioneller ("Enterprise Niveau") und erlaubt eine sofortige visuelle Erfassung der Call-Ergebnisse.
