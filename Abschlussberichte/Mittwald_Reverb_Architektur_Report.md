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

#### Konfigurationen (`.env` & `.htaccess`)
Um dieses Setup zu fahren, bleibt die Codebase komplett sauber auf den Standard-Laravel-Werten:

**.env** (Backend)
```env
REVERB_SERVER_PORT=6001
REVERB_PORT=6001
REVERB_HOST="127.0.0.1"

VITE_REVERB_HOST="ws.mein-seelenfunke.de"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME="https"
```

**.htaccess** (Stage Server)
```apache
RewriteCond %{HTTP_HOST} ^ws\.mein-seelenfunke\.de$ [NC]
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteRule ^(.*)$ ws://127.0.0.1:6001/$1 [P,L]

RewriteCond %{HTTP_HOST} ^ws\.mein-seelenfunke\.de$ [NC]
RewriteRule ^(.*)$ http://127.0.0.1:6001/$1 [P,L]
```

**Fazit:** Man darf sich von Shared-Hosting Docker-Umgebungen nicht vorschreiben lassen, was geht und was nicht. Die Aufteilung in zwei getrennte Cronjobs im selben Container hebelt sämtliche Limitierungen aus und bietet ein vollwertiges WebSocket-Ökosystem ohne Notwendigkeit für Supervisor, Forge oder teure Node-Container.
