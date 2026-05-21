# Offene Support-Anfrage: Mittwald mStudio & Laravel Reverb

**Status:** 🟢 Antwort erhalten / Migration geplant  
**Datum der Anfrage:** 13. Mai 2026  
**Datum der Antwort:** 19. Mai 2026  
**Themenbereich:** Infrastruktur, WebSockets, Cronjobs, Worker-Apps

---

## 1. Ausgangssituation
Um Echtzeit-Funktionen (WebSockets) in unserer Laravel 13 App (`seelenfunke-stage`) nutzen zu können, benötigen wir den **Laravel Reverb** Dämon. Dieser muss permanent als Hintergrundprozess (`php artisan reverb:start`) laufen.

Da wir auf der Mittwald mStudio Shared-Hosting-Architektur (Docker) keinen direkten Zugriff auf klassische Prozess-Manager wie `Supervisor` haben, mussten wir einen Workaround über das Mittwald-Cronjob-System einrichten.

## 2. Der Workaround & Das Problem
Wir haben zwei getrennte, minütliche Cronjobs im Mittwald-Panel eingerichtet:
1. **Task Scheduler:** `php artisan schedule:run`
2. **Reverb Daemon:** `php artisan reverb:start`

Dies hat hervorragend funktioniert, bis beide Cronjobs am 13. Mai plötzlich "stumm" eingefroren sind ("Hinweis: Daemon"). Obwohl sie im Mittwald-Dashboard weiterhin auf "Aktiviert" standen, wurden sie vom System nicht mehr ausgeführt. Erst ein manueller Eingriff (Neustart der Cronjobs im Panel) hat die Systeme wiederbelebt.

Es ist stark davon auszugehen, dass sich der interne Cron-Dämon von Mittwald an dem absichtlich blockierenden/langlaufenden Reverb-Prozess "verschluckt" hat.

## 3. Die Support-Antwort (19. Mai 2026)
Gerrit Hartwig vom Mittwald-Support hat auf unsere Anfrage geantwortet und wichtige Details geliefert:

1. **Warum sind die Cronjobs eingefroren?**  
   Die genaue Ursache für das "Verschlucken" des Cronjobs ließ sich im Nachhinein nicht mehr feststellen. Der Support bestätigte jedoch indirekt, dass ein minütlicher Cronjob für einen dauerhaft laufenden Prozess (wie Reverb) nicht ideal ist und durch interne Limits oder Timeouts beeinträchtigt werden kann.

2. **Offizielle Best-Practice (PHP Worker App):**  
   Der von Mittwald vorgesehene Weg für persistente Dienste wie Laravel Reverb ist die Nutzung einer **PHP Worker App**. Gerrit Hartwig stellte klar, dass unser "Versuch 1" (die Kommunikation über einen internen Host) grundsätzlich der richtige Weg ist, wir jedoch die falsche Hostname-Syntax verwendet haben.
   - Die Web-App kann mit der Worker-App über deren internen Shortcode (`a-iurgq8`) kommunizieren.
   - Dieser Shortcode entspricht dem Benutzernamen in der SSH-Login-URL der Worker-App.
   - Die `.htaccess`-Proxy-Regel der Web-App kann direkt auf `a-iurgq8:6001` verweisen.
   - **Vorteil:** Mittwalds internes System `mittnite` überwacht den Prozess in der Worker App und hält ihn permanent am Leben. Ein minütlicher Cronjob und Hilfskonstrukte wie der "Zombie-Killer" entfallen damit komplett.

## 4. Nächste Schritte: Migration auf die Worker-App-Architektur
Wir haben das System auf die von Mittwald empfohlene Architektur umgestellt:

1. **Worker App anlegen:** [ERLEDIGT] Die PHP Worker App `WORKER - Websocket Stage` wurde erstellt.
2. **Shortcode ermitteln:** [ERLEDIGT] Der Shortcode lautet `a-iurgq8`.
3. **Konfiguration anpassen:** [ERLEDIGT]
   - In der `.env` auf dem Stage-Server wird `REVERB_HOST` auf `"a-iurgq8"` gesetzt.
   - In der `public/.htaccess` der Web-App wurden die Proxy-Weiterleitungen auf `ws://a-iurgq8:6001` und `http://a-iurgq8:6001` umgestellt.
   - In `app/Livewire/Shop/Master/MasterAnalytics.php` wurde die Health-Check- und Heilungs-Logik (`fixSystem`) für WebSockets angepasst (kein `pkill` mehr auf Stage, da Native-Prozessüberwachung über `mittnite` erfolgt).
4. **Cronjobs bereinigen:** Der minütliche Cronjob für `reverb:start` im Mittwald Panel wurde deaktiviert. Der Scheduler-Cronjob (`schedule:run`) bleibt aktiv.
5. **Scheduler-Worker bereinigen:** [ERLEDIGT] Die redundante PHP-Worker-App `WORKER - Scheduler Stage` (mit dem Befehl `schedule:work`) wurde gelöscht, da sie zu einer fehlerhaften Doppel-Ausführung des Schedulers pro Minute führte. Der minütliche Cronjob `schedule:run` auf dem Web-App-Container ist nun die alleinige Quelle für periodische Tasks.

