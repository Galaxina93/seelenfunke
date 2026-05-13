# Architektur-Abschlussbericht: Laravel Reverb auf Mittwald mStudio

## Zusammenfassung des Problems
Beim Versuch, den Laravel Reverb WebSocket-Server als "PHP-Worker" in der Mittwald mStudio-Umgebung auszuführen, traten persistente `503 Service Unavailable` und später `500 Proxy Error` Fehler auf. Die Ursache lag in der strikten Container-Isolierung von Mittwald. Worker-Apps und Web-Apps werden in komplett getrennten Docker-Containern ausgeführt.

## Lösungsansätze & Erkenntnisse

### Versuch 1: Mittwald PHP-Worker mit .htaccess Proxy (Gescheitert)
- **Die Idee**: Den Reverb-Server als dedizierte "PHP-Worker App" laufen zu lassen.
- **Problem**: 
  - Eine `.htaccess` Weiterleitung von der Web-App auf `127.0.0.1` funktionierte nicht, da dies nur den internsten Loopback-Adapter des Web-App-Containers anspricht.
  - Das Routing über den internen Hostnamen der Worker-App (`a-c45drm.internal`) schlug mit "DNS lookup failure" auf Apache-Ebene völlig fehl, da `.htaccess` keinen Zugriff auf die internen Docker-Routen von Mittwald hat.

### Versuch 2: Mittwald Domain-Direktzuweisung (Gescheitert)
- **Die Idee**: Die Subdomain `ws.mein-seelenfunke.de` via Mittwald-Backend direkt der Worker-App zuzuweisen.
- **Problem**: Mittwalds Ingress-Loadbalancer erwartet bei PHP-Worker-Apps keine direkt lauschenden Webdienste. Wir bekamen lediglich einen generierten Mittwald-503-Fehler. Worker-Apps in mStudio dürfen schlichtweg nicht vom öffentlichen Internet aufgerufen werden.

### Versuch 3: Cronjob im Web-Container (Erfolgreich, aber Scheduler blockiert)
- **Die Idee**: Reverb über den normalen Mittwald Cronjob zu starten.
- **Der Haken**: Wenn man `php artisan reverb:start` direkt in den Scheduler oder einen primären Cronjob hängt, bleibt der Prozess hängen und blockiert alle anderen Shell-Befehle, da der Supervisor von Mittwald ihn nicht als Dämon freigibt.

### Die finale & saubere Lösung: Zwei asynchrone Cronjobs
Da Reverb zwingend im **selben Container** wie die Web-App (`seelenfunke-stage`) laufen muss, damit `.htaccess` via `127.0.0.1` darauf zugreifen kann, wurde die Trennung in zwei vollständig voneinander isolierte Mittwald-Cronjobs vollzogen.

#### Schritt 1: Cronjobs anlegen
In der Mittwald Weboberfläche unter der App `seelenfunke-stage` müssen 2 Cronjobs (Intervall: `* * * * *`) angelegt werden:
1. **Scheduler**: Ausführen von `/html/seelenfunke-stage/artisan` mit Parameter `schedule:run`. Das stellt sicher, dass Emails/Backups pünktlich rausgehen.
2. **Reverb Daemon**: Ausführen von `/html/seelenfunke-stage/artisan` mit Parameter `reverb:start`.

#### Warum das perfekt funktioniert:
- Der Reverb-Cronjob "hängt" für das von Mittwald vorgegebene Limit (z. B. 60 Minuten), wodurch der Server durchgehend läuft und auf WebSockets auf `127.0.0.1:6001` antwortet. 
- Wenn Mittwald den Reverb-Cronjob am Limit abschießt (Timeout), wird er durch das `* * * * *` Intervall nur eine Minute später neu hochgefahren.
- Der Scheduler-Cronjob ist nun ein eigener Thread und wird **nicht mehr** von Reverb blockiert! Er läuft binnen 1 Sekunde durch.
- **Lebenswichtige Abhängigkeit:** Die *gesamte* Stabilität dieses Setups hängt 1:1 davon ab, dass die Cronjobs im Mittwald mStudio **aktiviert** sind. Stoppt Mittwald die Cronjobs, sterben sofort beide Systeme (Scheduler und Reverb) ab, da es keinen anderen Supervisor gibt, der sie neu startet.

### Erweiterung 1: Der "Zombie-Killer" (Infrastruktur-Heilung)
Aufgrund der Natur von Cronjobs kann es vorkommen, dass ein alter Reverb-Prozess abstürzt, sich aber nicht korrekt beendet (ein sogenannter "Zombie-Prozess"), wodurch der Port `6001` blockiert bleibt. 
Um das System resilient zu machen, wurde eine automatisierte `fixSystem('ws')` Logik im Backend (`MasterAnalytics.php`) implementiert:
1. Vor einem Neustart wird stets `pkill -f "reverb:start"` auf der Shell ausgeführt.
2. Das tötet sämtliche verklemmten PHP-Prozesse, die Reverb am Leben halten.
3. Danach verlässt sich das System wieder auf den minütlichen Mittwald-Cronjob, der im nächsten Zyklus einen frischen, sauberen Reverb-Daemon hochfährt.

### Erweiterung 2: Die Daemon-Sperre (Verhinderung von Scheduler-Hängern)
Ein kritisches Problem trat auf, wenn persistente Hintergrunddienste (wie `reverb:start` oder `queue:work`) fälschlicherweise in die interne Laravel-Datenbanktabelle (`system_cronjobs`) eingetragen wurden, welche vom Scheduler abgearbeitet wird.
Da der Mittwald-Cron-Container extrem restriktiv arbeitet, wartet er auf die Beendigung ALLER vom PHP-Skript gestarteten Kind-Prozesse (auch bei Nutzung von `runInBackground()`), bevor er den Cronjob (`schedule:run`) im Panel als "abgeschlossen" markiert. Das führte dazu, dass der gesamte Scheduler über Stunden hängen blieb (Status: "Ausführung läuft") und sämtliche anderen Hintergrundaufgaben lahmlegte.

**Die Lösung:** In der `routes/console.php` wurde eine harte Sicherheits-Sperre implementiert:
```php
$daemonCommands = ['reverb:start', 'queue:work', 'queue:listen', 'websockets:serve'];
// Falls einer dieser Befehle geladen wird -> Blockieren und auf inaktiv setzen!
```
Langlaufende Dämonen dürfen **niemals** über den internen Laravel-Scheduler auf Mittwald ausgeführt werden, sondern müssen zwingend als **eigenständige Cronjobs im Mittwald-Panel** konfiguriert werden. Bei einem Verstoß blockiert der Scheduler den Befehl automatisch, setzt ihn auf Inaktiv und erzeugt ein System-Log (`system:cronjob_blocked`).

### Erweiterung 3: Intelligentes WebSocket-Routing (Frontend)
Zuvor war es notwendig, beim Wechsel zwischen lokaler Entwicklung und Live-Deployment manuell `.env`-Variablen (`VITE_REVERB_HOST`, `MIX_PUSHER_HOST` etc.) auszutauschen und das Bundle neu zu kompilieren. Dies war fehleranfällig und aufwändig.

**Die Lösung:** Das JavaScript-Frontend (`resources/js/echo.js`) entscheidet nun zur Laufzeit selbständig anhand der Browser-URL (`window.location.hostname`), wohin es funkt:
- Ist die URL `127.0.0.1`, `localhost` oder endet auf `.test`, funkt Echo automatisch an `127.0.0.1:6001` (ohne TLS).
- Ist es eine Live-Domain (z.B. `stage.mein-seelenfunke.de`), funkt Echo automatisch über WSS an den entsprechenden Host auf Port `443`.

Alle `VITE_REVERB_*` und `MIX_PUSHER_*` Variablen bezüglich Host/Port/Scheme wurden somit restlos aus den `.env`-Dateien und Dashboards entfernt, da das Frontend völlig umgebungsunabhängig arbeitet.

#### Konfigurationen (`.env` & `.htaccess`)

**Backend .env** (Es werden nur noch die internen Server-Werte für Laravel benötigt!)
```env
# Reverb Server (Intern)
REVERB_SERVER_PORT=6001
REVERB_PORT=6001
REVERB_HOST="127.0.0.1"

# Frontend Variablen (VITE_REVERB_HOST etc.) KÖNNEN GELÖSCHT WERDEN!
```

**.htaccess** (Stage Server - Proxyt den öffentlichen 443 Traffic sicher auf den internen 6001 Reverb-Port)
```apache
RewriteCond %{HTTP_HOST} ^ws\.mein-seelenfunke\.de$ [NC]
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteRule ^(.*)$ ws://127.0.0.1:6001/$1 [P,L]

RewriteCond %{HTTP_HOST} ^ws\.mein-seelenfunke\.de$ [NC]
RewriteRule ^(.*)$ http://127.0.0.1:6001/$1 [P,L]
```

**Fazit:** Man darf sich von Shared-Hosting Docker-Umgebungen nicht vorschreiben lassen, was geht und was nicht. Die Aufteilung in zwei getrennte Cronjobs im selben Container hebelt sämtliche Limitierungen aus. Ergänzt um den "Zombie-Killer" und das intelligente Frontend-Routing haben wir ein vollwertiges, selbstheilendes und völlig wartungsfreies WebSocket-Ökosystem erschaffen – ganz ohne Supervisor, Forge oder teure Node-Container.
