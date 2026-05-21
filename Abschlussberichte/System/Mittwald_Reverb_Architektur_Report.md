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

### Update: Die offizielle & sauberste Lösung via PHP-Worker-App (19. Mai 2026)
Nach Rücksprache mit dem Mittwald-Support hat sich herausgestellt, warum **Versuch 1** mit der PHP-Worker-App fehlgeschlagen war:
- Wir hatten versucht, die Worker-App über die URL `a-c45drm.internal` anzusprechen. Dieser `.internal`-Suffix existiert in der mStudio DNS-Auflösung jedoch nicht.
- **Korrektur:** Der interne Hostname der Worker-App ist schlicht und ergreifend ihr **Shortcode** (z. B. `a-iurgq8`, zu finden in der SSH-Login-URL der Worker-App), ohne jeglichen Domain-Suffix.
- Innerhalb des Mittwald-Container-Netzwerks kann die Web-App den Worker-Container direkt über `http://a-iurgq8:6001` bzw. `ws://a-iurgq8:6001` erreichen.

#### Zukünftige Konfiguration mit Worker-App:

> [!CAUTION]
> **Zwingend erforderlich: Chroot-Jail & Datei-Kopiervorgang**
> Aufgrund der Chroot-Isolierung von Mittwald darf das Installationsverzeichnis des Workers (z. B. `/html/worker-stage-3`) **keine Symlinks** auf das Hauptprojekt (`/html/seelenfunke-stage`) enthalten. Innerhalb des Worker-Containers sind solche Symlinks tot (broken), wodurch der Startbefehl (`php artisan reverb:start`) sofort abstürzt.
>
> Das bedeutet:
> 1. Bei jedem Deployment / Code-Update müssen die Projektdateien physisch per `cp` in das Worker-Verzeichnis kopiert werden (analog zur Twilio-Node-App).
> 2. Wenn der Worker-Container zum ersten Mal angelegt wird, muss er erst befüllt werden, bevor er gestartet werden kann.
>
> **.htaccess** (Web-App leitet Traffic auf den Worker-Shortcode um):
```apache
RewriteCond %{HTTP_HOST} ^ws\.mein-seelenfunke\.de$ [NC]
RewriteCond %{HTTP:Upgrade} =websocket [NC,OR]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^(.*)$ ws://a-iurgq8:6001/$1 [P,L]

RewriteCond %{HTTP_HOST} ^ws\.mein-seelenfunke\.de$ [NC]
RewriteRule ^(.*)$ http://a-iurgq8:6001/$1 [P,L]
```

**Backend .env** (Web-App funkt an Worker-Container):
```env
REVERB_HOST="a-iurgq8" # Shortcode der Worker-App
REVERB_PORT=6001
REVERB_SERVER_PORT=6001
```

#### Synchronisierung & Neustart bei Code-Änderungen (21. Mai 2026)

Da der Worker in einem chroot-isolierten Container läuft, müssen Code-Änderungen manuell dorthin synchronisiert werden und der Worker-Prozess im Anschluss neu gestartet werden (Mittwald führt bei Code-Updates im Verzeichnis keinen automatischen Neustart des Workers durch).

##### 1. Synchronisierung (über den Web-App-Container `a-lc6tkm`)
Hierfür wurde der Befehl `sync-worker` in das globale `funki`-Hilfsskript integriert. Führen Sie im Hauptprojekt (`seelenfunke-stage`) folgenden Befehl aus:
```bash
bash funki sync-worker
```
Dies synchronisiert alle relevanten PHP-Dateien (unter Ausschluss von `storage/`, `node_modules/`, `.git/` und der `.env`) und leert anschließend die Konfigurations-Caches im Worker.

##### 2. Neustart des Workers (ausschließlich im Worker-Container `a-iurgq8`)
> [!WARNING]
> **Achtung bei SSH-Sitzungen!**
> Der Befehl `mittnitectl` steuert den Prozessmanager `mittnite` und funktioniert **ausschließlich** innerhalb der SSH-Konsole des jeweiligen Workers (z. B. `a-iurgq8`).
> 
> Selbst wenn Sie sich im Verzeichnis `~/html/worker-stage-3` befinden, aber im Web-App-Container (`a-lc6tkm`) eingeloggt sind, schlägt der Befehl mit folgendem Fehler fehl:
> ```bash
> p-g27wim @ [a-lc6tkm] ~/html/worker-stage-3
> $ mittnitectl job restart
> 💥 AN ERROR OCCURRED WHILE HANDLING YOUR COMMAND
>    failed to list jobs: Get "http://unix/v1/jobs": dial unix /var/run/mittnite.sock: connect: no such file or directory
> ```
> **Grund:** Der `mittnite`-Daemon und sein Steuerungs-Socket `/var/run/mittnite.sock` existieren ausschließlich in der isolierten Sandbox des Worker-Containers (`a-iurgq8`). Der Web-App-Container (`a-lc6tkm`) hat keinen Zugriff darauf. Sie müssen sich zwingend über den SSH-Zugang des Workers (`a-iurgq8`) verbinden, um den Prozess neu zu starten!

**Neustart-Befehl (auszuführen in der SSH-Konsole des Workers `a-iurgq8`):**
```bash
mittnitectl job restart
```

**Status prüfen:**
```bash
mittnitectl job status
```


### Ergänzung: Bereinigung & Best Practices für Hintergrund-Prozesse (20. Mai 2026)
Im Zuge dieser Umstellung wurde auch die restliche Struktur der Hintergrund-Prozesse in Mittwald mStudio analysiert und bereinigt, da zuvor ein redundantes Setup aktiv war.

#### Wann verwendet man Worker-Apps vs. Cronjobs in mStudio?

1. **PHP-Worker-App (Dauerläufer / Dämonen):**
   - **Einsatzbereich:** Für Prozesse, die dauerhaft im Hintergrund laufen müssen (z. B. `reverb:start` oder `queue:work` / `queue:listen`).
   - **Vorteil:** Mittwalds Prozess-Manager `mittnite` hält die App dauerhaft aktiv und startet sie bei Abstürzen oder Timeouts automatisch neu.
   - **Fehlkonfiguration:** Die Worker-App `WORKER - Scheduler Stage` mit dem Befehl `php artisan schedule:work` wurde gelöscht. Dieser Befehl simuliert lediglich eine Endlosschleife und ist nur für lokale Umgebungen ohne echten System-Cron gedacht. Im Live-Betrieb verbraucht er unnötig dauerhaft RAM.

2. **Cronjobs im Panel (Periodische Ausführung):**
   - **Einsatzbereich:** Für Befehle, die in festen Intervallen kurz starten, ihre Arbeit tun und sich wieder beenden (z. B. `php artisan schedule:run` jede Minute).
   - **Vorteil:** Ressourcenschonend, da der PHP-Prozess nach Erledigung der Aufgaben (meist < 2 Sekunden) sofort beendet wird und den RAM wieder freigibt.
   - **Status:** Der minütliche Cronjob auf der Web-App `seelenfunke-stage` mit dem Parameter `schedule:run` bleibt der führende Scheduler. Die redundante Worker-App wurde entfernt, um doppelte Task-Ausführungen (und damit z. B. doppelten Mailversand) zuverlässig zu verhindern.

**Bereinigte Gesamtstruktur auf Stage:**
* **Web-App Container (`seelenfunke-stage`):** Läuft als Webserver und führt minütlich den Cronjob `schedule:run` aus.
* **Worker-App `WORKER - Jobs (Mails,PDF) Stage`:** Läuft permanent für die Queue-Abarbeitung.
* **Worker-App `WORKER - Websocket Stage` (`a-iurgq8`):** Läuft permanent für den Laravel Reverb WebSocket-Server.

**Fazit:** Der Workaround mit zwei Cronjobs im selben Container war eine exzellente temporäre Lösung. Mit den neuen Erkenntnissen des Supports zur korrekten Hostname-Auflösung (`a-iurgq8` ohne `.internal`) wechseln wir zur nativen Worker-App-Architektur. Dies macht den minütlichen Reverb-Cronjob und den "Zombie-Killer" in `MasterAnalytics.php` hinfällig, da Mittwalds `mittnite` nun die Prozessüberwachung übernimmt. Gleichzeitig sorgt die Entfernung des redundanten Scheduler-Workers für ein saubereres, ressourcenschonenderes und fehlerfreies System-Setup.


