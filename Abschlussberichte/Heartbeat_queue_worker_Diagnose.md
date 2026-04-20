# Dokumentation: Queue Worker Heartbeat & Mittwald Container Isolation

**Datum**: 20. April 2026
**Zweck**: Analyse, Diagnose und Lösung des scheinbaren Server-Ausfalls des Queue Workers (Roter Heartbeat) auf dem Stage-System.

---

## 1. Das Problem / Symptom

- Im Seelenfunke **Ai-Workspace** blieb die visuelle EKG-Linie für den KI-Worker permanent auf **Rot** (Offline), obwohl Agenten aufgaben annahmen und nach einiger Zeit via SSH auch sauber in der Datenbank als "FERTIG" beantwortet wurden.
- Die Diagnose-Pings zeigten durchgehend: `Cache-Signal: Fehlt | NFS-Signal: Fehlt | pgrep: Leer | ps: Leer`.
- Neustart-Befehle (z. B. `php artisan queue:restart`) über die Web-Oberfläche (MasterAnalytics) schienen keine Wirkung zu erzielen, der Zustand blieb unverändert kritisch.

## 2. Die Mittwald Infrastruktur-Architektur

Die Ursache lag in der speziellen **Container-basierten Architektur** des Mittwald-Hostings:

Auf Mittwald werden Web-Instanzen (die Haupt-Website) und Hintergrund-Worker (die PHP Worker App) in **strikt isolierten Containern (Pods)** ausgeführt. 
- **Trennung der Prozesse**: Linux-Befehle wie `ps` oder `pgrep`, die vom Web-Container aus ausgeführt werden, können nicht "hinüber" in den benachbarten Worker-Container schauen. Daher waren diese Pings korrekterweise "Leer".
- **Trennung des lokalen Caches**: Wenn in der `.env` Datei der `CACHE_DRIVER=file` eingestellt ist, schreibt der Web-Container seine Cache-Daten auf seine eigene, vom Worker isolierte Festplatte. 

## 3. Die Diagnose des "Koma-Workers" (Frozen Process)

Da der Worker scheinbar reagiert hat (wenn man z. B. manuell SSH genutzt hat), aber das Dashboard Offline meldete, kamen drei entscheidende Faktoren zusammen:

1. **Der verpasste Neustart-Befehl:** Ein Klick auf "Worker Neu starten" im Laravel-Backend sendet lediglich die Nachricht `illuminate:queue:restart` an den Cache. Da der Web-Container diesen in seinen *isolierten* `file`-Cache gelegt hat, hat der Worker-Container diesen Befehl nie zu Gesicht bekommen.
2. **Alter Code im RAM:** Da der Worker nie wirklich neu gestartet wurde, lief er intern über Tage hinweg mit einer veralteten Codebasis im Zwischenspeicher (RAM). Ihm fehlte der neu entwickelte PHP-Code (`AppServiceProvider`), der den Heartbeat überhaupt erst in die Datenbank/NFS überträgt.
3. **Eingefrorene Jobs (Frozen Worker):** Bei Langläufer-Einstellungen wie `--max-time=3600` kann es passieren, dass ein einzelner Job (z. B. Server-Verbindungsfehler) stundenlang in der Ladehemmung liegt. In dieser Zeit läuft das Laravel-Event `Queue::looping` nicht durch – der Heartbeat sendet keine Pings mehr aus.

## 4. Die Implementierten Lösungen

Um das Monitoring zukunftssicher und zu 100% verlässlich zu machen, wurden folgende architektonische und administrative Änderungen vorgenommen:

### 4.1. Duales Ping-System (Code-Level)
Im `AppServiceProvider` wurde der Queue-Heartbeat so erweitert, dass er robuster kommuniziert:
- **Speicher-Fallback:** Zusätzlich zum Redis-Cache schreibt der Worker bei jedem Schleifendurchlauf einen minimalen Zeitstempel in eine Datei (`storage/app/ai_worker_heartbeat.txt`). Da die `storage/`-Ordner bei Mittwald zentral gehostet und in beiden Containern gemountet sind (NFS Storage), kann die Website hier sicher und container-übergreifend ablesen, ob der Worker noch atmet.

### 4.2 UI/UX Erweiterungen für zukünftige Diagnosen
Im **MasterAnalytics-Dashboard** wurde unter "Queue Worker" explizit ein aggressiver Neustart-Button (**Worker Neustarten (Kill)**) hinzugefügt, sodass der Administrator auch bei leerer Queue (was das System trügerischerweise als 'grün/gesund' markiert) jederzeit einen Restart erzwingen kann.

### 4.3. Der Hard-Reset bei Container-Isolation (Admin-Notfall-Protokoll)
Da ein Worker, der bereits mit altem Code im RAM feststeckt, nicht mehr auf Laravel-Befehle reagiert, wurde ein "Hard Reset" auf Container-Ebene notwendig.
*   **Best Practice bei festgefahrenen Apps auf Mittwald:**
    Im Mittwald Control-Panel wird bei der App `WORKER - Jobs (Mails,PDF) Stage` unter der Konfiguration ein einzelnes, nutzloses Leerzeichen am Ende des Command-Startbefehls angehängt und gespeichert. Dies zwingt die gesamte Mittwald-Node zu einem physischen Neustart der Worker-Umgebung. Der veraltete RAM-Zwischenspeicher wird verworfen, der Koma-Patient eliminiert und die neue Codebasis geladen.

## 5. Empfehlungen für die Live-Umgebung
Damit diese Diskrepanz nie auf dem finalen Live-System vorfällt, ist strengstens darauf zu achten:
- Das Stage- & Live-System sollten **IMMER** getrennte Redis-Prefixe besitzen oder im Rahmen isolierter `.env`-Architektur betrieben werden.
- Dem Wert `CACHE_STORE=redis` (bzw. `CACHE_DRIVER=redis`) oberste Priorität einräumen, damit Event-Loops, Cache-Pings und vor allem Restart-Befehle über das Netzwerk geteilt werden und Worker zuverlässig auf UI-Befehle hören.
