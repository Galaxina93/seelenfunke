# Offene Support-Anfrage: Mittwald mStudio & Laravel Reverb

**Status:** 🟡 Warten auf Antwort von Mittwald Support  
**Datum der Anfrage:** 13. Mai 2026  
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

## 3. Die eigentliche Support-Anfrage
Wir haben den Mittwald-Support kontaktiert, um zwei Kernfragen zu klären:

1. **Warum sind die Cronjobs eingefroren?**  
   Wir haben nachgefragt, ob das Mittwald-System (oder das Monitoring) den Cronjob intern abgewürgt hat, weil er als "hängender" Prozess identifiziert wurde. Wir benötigen die Information, ob unser Workaround so toleriert wird oder ob wir hier regelmäßig mit stillschweigenden Ausfällen rechnen müssen.

2. **Offizielle Best-Practice für mStudio:**  
   Wir haben um eine offizielle, von Mittwald vorgesehene Lösung für WebSockets gebeten. Unser ursprünglicher Plan, Reverb in einer dedizierten **PHP-Worker App** laufen zu lassen, scheiterte, da mStudio an Worker-Apps offenbar keinen externen Ingress-Traffic (Public WSS auf Port 443) durchreicht, was zu permanenten 503-Fehlern führte.

## 4. Nächste Schritte (Warten auf Antwort)
Sobald Mittwald antwortet, wird dieses Dokument aktualisiert. Die erhofften Ergebnisse sind:
- **Szenario A (Whitelist/Fix):** Mittwald bestätigt, dass der Cronjob-Ansatz legitim ist, behebt das Einfrier-Problem oder setzt uns auf eine Ausnahme-Liste.
- **Szenario B (Worker-App Config):** Mittwald erklärt uns, wie man einer Worker-App in mStudio den öffentlichen Traffic erlaubt (z. B. durch versteckte Einstellungen oder Ingress-Rules), sodass wir Reverb sauber und dediziert in einem eigenen Container betreiben können.
- **Szenario C (Alternativ-Lösung):** Mittwald stellt eine komplett andere, native mStudio-Lösung für persistente Dämonen vor.

*(Wird nach Erhalt der Support-Antwort aktualisiert)*
