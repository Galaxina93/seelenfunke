# Abschlussbericht: Stabilisierung der AI Map Control

## Ausgangssituation & Fehlerbild
Das System sollte es dem Benutzer ermöglichen, über den "Live Mode" (WebSocket Voice API) sowie über den Text-Chat mit dem KI-Agenten zu kommunizieren. Bei Ortsanfragen oder dem Befehl, die Karte zu minimieren, meldeten die KI-Modelle zwar "Ich habe die Karte verschoben", aber im Frontend passierte **nichts**. Ebenso wurden keine Logs generiert, wenn der Befehl über die Sprachsteuerung gegeben wurde.

## Ursachenanalyse

Die detaillierte Untersuchung der Code-Basis förderte mehrere tief verflochtene Fehler zutage:

1. **Der Live Mode (WebSocket) Bypass**
   Wenn der Benutzer mit dem Agenten über das Mikrofon sprach (Multimodal Live Mode), lief die gesamte Kommunikation über den WebSocket und den `AIController::execute` Endpunkt. Dadurch wurde der `ManagesAiChat.php`-Trait sowie die Livewire-Lebenszyklen komplett umgangen. Debugging-Logs in den Agent-Klassen (`GeminiAgent.php`, `MittwaldAgent.php`) wurden somit für Sprachbefehle nie erreicht oder geschrieben.
   
2. **Der Schlüssel-Konflikt: `_event` vs `_frontend_event`**
   Die Werkzeuge (z.B. in `AiMapControlFuncs.php`) geben korrekt das Array `_frontend_event` zurück. Im WebSocket-Handler in `ai-widget-part2.blade.php` wurde jedoch fälschlicherweise `resultData.result._event` ausgelesen. Map-Steuerungs-Befehle via Sprache wurden daher komplett ins Leere laufen gelassen.

3. **Der strikte `type === 'dispatch'` Check**
   Sowohl der Text-Chat (`sendToAI()`) als auch der Voice-Chat (`handleWsMessage()`) verlangten im JavaScript, dass das empfangene Event-Objekt das Attribut `type: 'dispatch'` besitzt, bevor `window.dispatchEvent()` aufgerufen wurde. Die Events aus `AiMapControlFuncs` senden jedoch nur `name` und `detail`. Durch das Fehlen des `type` Attributs fielen alle Events durch das Raster und wurden nie getriggert.

## Implementierte Lösungen

1. **Lockerung der Event-Dispatcher-Logik**
   In `ai-widget-part2.blade.php` wurde die Frontend-Logik robuster gemacht. Statt strikt auf `evt.type === 'dispatch'` zu pochen, erlaubt der Code nun das Ausführen des Events, sofern `evt.type === 'dispatch' || !evt.type` zutrifft.
   
2. **Korrektur des WebSocket Event-Parsings**
   Im WebSocket-Handler (`handleWsMessage()`) sucht das Script nun korrekt nach `resultData.result._frontend_event` als auch nach `_event`, sodass Voice-Befehle reibungslos Events ins Frontend emittieren können.
   
3. **Erweiterung auf `_frontend_events` (Multi-Event Support)**
   Zusätzlich wurde die Architektur so erweitert, dass ein KI-Tool nun auch ein Array an Events (`_frontend_events`) zurückgeben kann, damit komplexe parallele Aktionen (z.B. Map Fly-To und UI Panel Popup gleichzeitig) synchron ausgelöst werden können.

## Fazit
Die Map-Steuerung und das UI-Event-Routing sind nun stabil und fehlertolerant über beide Kommunikationswege (Text und Voice) eingebunden. Die direkte JavaScript-Ebene verarbeitet die Signale der KI nun synchron und ohne Verzögerung.

## Fehleranalyse & Behebung: Widget-Steuerung, Jarvis & Kamera

1. **Fehlerhaftes Event-Routing (Window vs. Document)**
   - **Ursache**: Einige Vanilla-JavaScript-Event-Listener (Kamera, Widget-Schließ-Mechanismen) nutzten `document.addEventListener`. Livewire, Alpine.js und das Web-Socket-Skript feuern Frontend-Events jedoch strikt über `window.dispatchEvent` ab. Da Events, die am `window` getriggert werden, nicht zum `document` hinunter propagiert werden (kein Bubble-Down), wurden die Events nie empfangen.
   - **Lösung**: Umstellung aller betreffenden Listener auf `window.addEventListener`.

2. **Kollision von Alpine.js Local Scope und globalen Objekten**
   - **Ursache**: Die Jarvis-3D-Kern-Transformation brach stumm in `updateJarvisMode()` ab. Der Sicherheitscheck prüfte fälschlicherweise auf `!window.t3`. Die 3D-Engine-Referenz `t3` ist jedoch nur eine lokale Variable (`let t3 = {...}`) innerhalb des Alpine.js Kontextes.
   - **Lösung**: Das `window.`-Präfix wurde entfernt, um korrekt auf die lokale Alpine-Referenz zu prüfen.

3. **Browser-Sicherheit & DOM-Rendering bei Video-Streams**
   - **Ursache**: Das Zuweisen eines Kamera-Streams (`srcObject`) an ein Video-Widget, das zunächst unsichtbar war und durch Alpine.js (`x-show`) eingeblendet wurde, verhinderte die Videoausgabe. Browser wie Chrome weigern sich oft, solche dynamisch eingeblendeten Streams ohne expliziten `.play()` Aufruf zu starten.
   - **Lösung**: Einbindung eines kurzen Timeouts (50ms) zum Abwarten des Alpine-Renderings und ein robuster Aufruf von `video.play()`.
