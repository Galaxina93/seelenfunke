# System-Protokolle (Logs)

Das System-Protokollierungssystem (System-Logs) von Seelenfunke bietet eine konsolidierte und strukturierte Übersicht über alle im Hintergrund laufenden Systemaktivitäten, Fehlerzustände, KI-Agenten-Ausführungen und automatisierten System-Cronjobs.

## Zielsetzung
Ziel dieses Moduls ist es, Administratoren und Entwicklern eine zentrale Diagnoseplattform zur Verfügung zu stellen. Es überwacht fehlerhafte Ausführungsschritte, protokolliert Payload-Daten von externen Webhooks und API-Anfragen und ermöglicht es, Systemfehler interaktiv als gelöst zu markieren.

---

## Beteiligte Komponenten & Klassen

### Datenbank-Modelle
- [SystemLog](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/System/SystemLog.php): Das zentrale Modell für Log-Einträge. Speichert den ausführenden KI-Agenten (`ai_agent_id`), Log-Typ, die Action-ID zur Gruppierung nach Domänen, einen Titel, Detailnachrichten, Status (`success`, `warning`, `error`, `running`), JSON-Payloads sowie Start- und Endzeitpunkte.
- [SystemSchedulerLog](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/System/SystemSchedulerLog.php): Dediziertes Ausführungsprotokoll (`system_scheduler_logs`, Migration `2026_11_14_094500_create_system_scheduler_logs_table.php`) für alle geplanten Server-Cronjobs.

### Services, Commands & Livewire-Controller
- [SystemErrorLogger](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SystemErrorLogger.php): Zentraler Diagnose-, Secret-Scrubbing- und 24h-Deduplizierungs-Service für unbehandelte Exceptions, harte PHP-Shutdown-/OOM-Abstürze (`registerFatalShutdownHandler`), kompilierte Blade-View-Rückauflösung (`resolveCompiledBladeViewPath`), lokalen Dead-Letter-Notfall-Spool (`sentinel_deadletter.jsonl`), `Log::warning`/`Log::error`-Ereignisse, fehlgeschlagene operative Cronjobs sowie den Cross-Platform Error-Sentinel.
- [SentinelTriageService](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SentinelTriageService.php): Zweistufiger 24/7 Selbstheilungs-Wächter (Stufe 1 autonome Infrastruktur-Selbstheilung + Stufe 2 einmaliges Antigravity-Reparatur-Dossier im Auffang-Pool `storage/app/private/antigravity_inbox.json`).
- [SentinelTriageCommand](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/System/SentinelTriageCommand.php) (`system:sentinel-triage`) & [SentinelInboxCommand](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/System/SentinelInboxCommand.php) (`system:sentinel-inbox`): 5-Minuten-Wächter-Zyklus sowie Offline-Catch-Up-Synchronisation zwischen 24/7-Stage und lokalem Notebook (`127.0.0.1`).
- [SystemLogs](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemLogs.php): Der Controller für das administrative Log-Cockpit (`/admin/global-logs`) mit den drei Ansichten `Offen`, `Auffang-Pool` und `Archiv`, 1-Klick-Button `Wächter prüfen` (`runSentinelTriageNow`) sowie aufklappbarer Funkira-Dossier-Ansicht.

---

## Logging-Lifecycle & Domänen-Parsing

Logs werden im System asynchron oder synchron bei der Ausführung von Hintergrund-Tasks oder Webhook-Empfängern gestartet. Dabei folgt das System folgendem Datenfluss:

```mermaid
sequenceDiagram
    autonumber
    participant App as Applikation / Job
    participant DB as Datenbank (system_logs)
    participant Panel as SystemLogs (Livewire Dashboard)

    App->>DB: SystemLog::start($actionId, $title, $type, $agentId)
    Note over DB: Status: running, started_at: now()
    
    alt Ausführung erfolgreich
        App->>DB: $log->finish('success', $message, $payload)
        Note over DB: Status: success, finished_at: now()
    else Fehler aufgetreten
        App->>DB: $log->finish('error', $errorMessage, $errorDetails)
        Note over DB: Status: error, finished_at: now()
    end
    
    Panel->>DB: Lese Logs mit Filtern (Paginierung)
    DB-->>Panel: Ergebnisse (inkl. Error-Counts)
    Panel->>Panel: Gruppiere Action-IDs nach Domänen (Split vor ':')
```

### 1. Start- / Finish-Architektur
Um Laufzeiten von Automatisierungen genau zu tracken, nutzt das System zwei Hilfsmethoden:
- **`SystemLog::start(...)`**: Initiiert den Protokollsatz im Status `running` und setzt `started_at` auf den aktuellen Timestamp.
- **`$log->finish(...)`**: Setzt `finished_at` auf den aktuellen Timestamp und speichert den Ausgangsstatus sowie die Detailnachricht und Nutzdaten (`payload`).

### 2. Domänen-Parsing (`action_id`)
Log-Einträge verwenden strukturierte `action_id`-Werte im Format `domain:action` (z. B. `stripe:webhook`, `ebay:crawler`, `ai:workspace`). 
Der Controller parst diese IDs im Render-Prozess dynamisch vor dem Doppelpunkt (`:`), um eine Liste aller aktiven Log-Domänen zu generieren. Dadurch kann der Administrator das Log-Terminal nach spezifischen Integrationen filtern.

### 3. Fehler-Wiederholungs-Analyse & 24h-Deduplizierung
Anstelle langsamer Sub-Selects führt [`SystemLog::create()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/System/SystemLog.php) gemeinsam mit [`SystemErrorLogger`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SystemErrorLogger.php) bereits beim Schreiben eine universelle 24h-SHA-256-Deduplizierung durch und zählt identische Ereignisse performant über `payload.occurrences_count` hoch. Dadurch sieht der Administrator sofort anhand des Badges (z. B. `3x`), ob ein Fehler ein isolierter Einzelfall ist oder wiederholt auftritt.

### 4. Bereichs-Differenzierung in Spalte 1, Offen-Fokus & 7-Tage-Selbstbereinigung
Das administrative Log-Cockpit unter `/admin/global-logs` ([`SystemLogs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemLogs.php) und [`system-logs.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/system/system-logs.blade.php)) trennt aktive Probleme strikt von erledigten Protokollen:
- **Spalte 1 (`Bereich`):** Direkt in der ersten Tabellenspalte sowie über klickbare Bereichs-Pills wird jeder Eintrag eindeutig einem der vier Ökosystem-Bereiche zugeordnet (`SystemLog::PLATFORMS` & `scopeForPlatform()`):
  1. **Webportal** (`portal`: Laravel Shop, Checkout, Livewire, Frontend, Queue & Scheduler)
  2. **Android App** (`android`: Funkenwerk Android App & Smartphone-Dienste)
  3. **Mini-PC** (`minipc_linux`: Werkstatt-Mini-PC `Funkenwerk OS Linux`)
  4. **Funkenwerk Software** (`windows_desktop`: Funkenwerk Windows Desktop Suite)
- **Zählung ausschließlich ungelöster Meldungen (`Offen` vs. `Archiv`):** Standardmäßig öffnet `/admin/global-logs` im Tab **Offen** (`status IN ('error', 'warning')`) und zählt in den KPI-Kacheln sowie im Dashboard (`/admin/dashboard`) ausschließlich ungelöste Fehler und Warnungen. Sobald ein Eintrag gelöst wird (`toggleStatus` oder `markAllAsDone`), wandert er mit `[GELÖST]` (`status = 'info'`) automatisch zur Seite in den separaten Tab **Archiv**.
- **Automatischer 7-Tage-Selbstreinigungs-Zyklus (`system:logs-cleanup`):** Über [`CleanupSystemLogsCommand.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/System/CleanupSystemLogsCommand.php) (täglich um `04:15` Uhr via [`SystemCronjobSeeder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/SystemCronjobSeeder.php) / [`routes/console.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/console.php) sowie gedrosselt beim Öffnen des Cockpits) räumt sich das Log selbstständig auf:
  - Gelöste Meldungen und Routine-Erfolgslogs im Archiv werden nach **7 Tagen** automatisch gelöscht (inkl. Komprimierung von `system_scheduler_logs` auf 7 Tage und Abschluss hängender `running`-Zombies > 6h).
  - Gesetzlich und sicherheitsrelevante Nachweise (`SystemLog::isAuditProtected()`, insb. DSGVO-Opt-ins, Einwilligungen und Security-Audits) bleiben automatisch für **30 Tage** geschützt (`RetentionPolicy::ShortLived_30Days`).
  - Offene, noch nicht gelöste Fehler (`error` / `warning`) werden niemals automatisch weggelöscht, sondern bis zur echten Behebung sichtbar gehalten und dedupliziert.

---

### 5. Globaler Cross-Platform Error-Sentinel & Auto-Heal Hub
Über [`SystemErrorLogger::recordCrossPlatformEvent()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SystemErrorLogger.php) und den API-Endpunkt `POST /api/system/error-ingest` in [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php) laufen Fehlerzustände und automatische Selbstheilungen aus allen vier Plattformen zentral in `system_logs` zusammen:
- **Plattform-Domänen (`action_id`):**
  - `portal:<subsystem>` (Laravel Web-Portal)
  - `android:<subsystem>` (Funkenwerk Android App via [`ErrorSentinelReporter.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/ErrorSentinelReporter.kt))
  - `minipc_linux:<subsystem>` (Werkstatt-Mini-PC `Funkenwerk OS Linux` via [`LinuxVaultAndApi.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/LinuxVaultAndApi.cs))
  - `windows_desktop:<subsystem>` (Funkenwerk Windows Suite via [`App.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk/App.xaml.cs))
- **24h SHA-256 Deduplizierung & Secret-Scrubbing:** Identische Fehler werden innerhalb von 24 Stunden per SHA-256-Fingerprint (`payload.fingerprint`) auf denselben `SystemLog`-Datensatz aufaddiert (`occurrences`), während API-Keys, Bearer-Tokens, Passwörter und `AIza`-Schlüssel vorab automatisch maskiert werden.
- **Auto-Heal Kennzeichnung (`[AUTO-GEHEILT]`):** Hat sich ein Dienst selbstständig geheilt (z. B. Neustart des lokalen Sense-Daemons `:17701`, LAN-zu-HTTPS-Fallback in Android oder nachgeholtes NAS-Delta-Backup), wird der Eintrag mit `status = 'warning'` und dem Präfix `[AUTO-GEHEILT]` verbucht, ohne roten Fehlalarm auszulösen.
- **Zentrale Sichtbarkeit & Funkira-Abfrage:** Offene Cross-Platform-Fehler erscheinen mit Klartext-Plattform-Badge im **Operativen Status** (`/admin/dashboard` -> Kachel `Log` via [`AnalyticsService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AnalyticsService.php)), im Monitor-Pulse (`open_system_errors_count`) sowie im KI-Werkzeug `system_get_error_sentinel` ([`AiSystemFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiSystemFuncs.php)). Abgesichert durch [`CrossPlatformErrorSentinelTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/CrossPlatformErrorSentinelTest.php) und [`SystemLogHardeningTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemLogHardeningTest.php).

---

### 6. System-Härtung & Resilienz-Mechanismen (Stand 11/2026)
- **Durchgängige 24h-Fingerprint-Deduplizierung:** Sowohl `recordException()`, `recordLogEvent()` und `recordCommandFailure()` als auch `recordCrossPlatformEvent()` und `MasterAnalytics::logSystemFailure()` gruppieren identische Ereignisse innerhalb von 24 Stunden per SHA-256-Fingerprint (`occurrences_count`) statt redundante Einzelzeilen anzulegen.
- **Automatischer Caller-Trace bei Log-Events:** `SystemErrorLogger::recordLogEvent()` ermittelt über `extractCallerTraceExcerpt()` automatisch die auslösende Anwendungsdatei, Zeilennummer und `trace_excerpt` auch bei reinen `Log::warning`/`Log::error`-Aufrufen ohne übergebenes `Throwable` (inkl. Diagnose-Kategorie `php_deprecation_notice`).
- **Trennung zwischen echten Cronjobs und Entwickler-CLI:** Der `CommandFinished`-Listener in [`AppServiceProvider.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Providers/AppServiceProvider.php) ignoriert Unit-Tests (`testing`) sowie interaktive Entwicklerbefehle (`test`, `tinker`, `db:seed`, `migrate:*`, Cache-Befehle) und protokolliert nur reale registrierte `SystemCronjob`- bzw. operative Hintergrundbefehle als `scheduler:cronjob_failed:*`.
- **Selbstheilung von Infrastruktur-Warnungen (`MasterAnalytics`):** Sobald `checkSystemHealth()` erkennt, dass ein zuvor ausgefallener Dienst (z. B. `backup`, `database`, `redis`, `smtp`) wieder den Status `connected` erreicht hat, schließt `resolveSystemFailure()` offene `system:health_fail`-Meldungen automatisch mit `[GELÖST]` (`status = 'success'`) ab.
- **Filterung harmloser UI-Lebenszyklus-Abbrüche:** Normale Coroutine-/Compose-Abbrüche beim Bildschirmwechsel (`LeftCompositionCancellationException`, `JobCancellationException`) werden sowohl clientseitig in [`AppLogger.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/AppLogger.kt) / [`ErrorSentinelReporter.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/ErrorSentinelReporter.kt) als auch serverseitig in `SystemErrorLogger::isBenignClientCancellation()` herausgefiltert.
- **Gemini-TTS Multi-Model & Quota-Circuit-Breaker:** [`GeminiTtsService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/GeminiTtsService.php) nutzt aktive TTS-Modelle (`gemini-3.1-flash-tts-preview`, `gemini-3.8-flash-tts`, `gemini-3.8-flash-lite-tts`) mit 5-Minuten-Cooldown pro Modell bei HTTP `429`, sodass temporäre Kontingent-Spitzen sofort vom nächsten Modell oder dem lokalen Client-TTS abgefangen werden, ohne `SystemLog`-Warnungen zu fluten.
- **Live-AI Verbindungs-, DNS- (`EAI_AGAIN`) & Telefon-Sekretärinnen-Diagnose:** `SystemErrorLogger::diagnose()` sowie `diagnoseWsIssue()` in [`server-twilio.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/server-twilio.js) erkennen gezielt temporäre DNS-/Socket-Engpässe (`network_dns_or_socket_eagain`), abgelehnte WebSocket-Bridge-Verifizierungen (`gemini_bridge_token_verification_failed`, inkl. `POST /api/ai/client-error-log`) sowie Ereignisse der Funkira Smartphone-Sekretärin (`funkira_phone_secretary_error`). `server-twilio.js` erzwingt `dns.setDefaultResultOrder('ipv4first')`, unterstützt die volle Secret-Fallback-Kette (`GEMINI_BRIDGE_SECRET` -> `AI_PROXY_SECRET` -> `TWILIO_BRIDGE_SECRET` -> `BRIDGE_SECRET`), wiederholt transiente `EAI_AGAIN`-/`502-504`-Backend-Aufrufe automatisch (`fetchBackendWithRetry`) und maskiert `AIza...`-/Bearer-/Token-Geheimnisse in `bridge.log`. Im Web-Widget ([`ai-widget-part2.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/ai/ai-widget-part2.blade.php)) schaltet jeder nicht-reguläre Bridge-Abbruch vor `setupComplete` (`code !== 1000`) sofort nahtlos auf `direct_ws_url` um. Sowohl Mini-PC (`sentinel_history.json`, `0600`) als auch Android (`error_sentinel_history.json`, AES-256-GCM) führen zusätzlich lokale Diagnose-Ringpuffer für Offline-Analysen ([`FunkiraConnectionResilienceTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/FunkiraConnectionResilienceTest.php)).
- **Direkte visuelle Kopplung mit dem „Projekt Gehirn“ (`SystemBrainDiagnosticsService`):** Jeder offene `error`- oder `warning`-Eintrag in `system_logs` wird live über [`SystemBrainDiagnosticsService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SystemBrainDiagnosticsService.php) auf die verursachende Projektdatei aufgelöst (inkl. Rückauflösung kompilierter Blade-View-Hashes aus `storage/framework/views/*.php`) und sowohl unter `/admin/system/neural-analysis` ([`SystemNeuralAnalysisIndex.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Backend/System/SystemNeuralAnalysisIndex.php)) als auch im 3D-Graph des KI-Widgets in **Rot** als Fehlerkern samt ein- und ausgehenden Nachbardateien visualisiert ([`System_Projekt_Gehirn_und_Vollstaendige_Routen_Haertung.md`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/System/System_Projekt_Gehirn_und_Vollstaendige_Routen_Haertung.md)).
- **Valides Livewire-3 Table-DOM:** In [`system-logs.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/system/system-logs.blade.php) besitzt jeder Log-Eintrag ein eigenes valides `<tbody wire:key="sys-log-...">` direkt unter `<table>` (keine verschachtelten `<tbody>`-Elemente).
- **Stage-Fehlerursachen nachhaltig behoben (11/2026):**
  - **IMAP-Schutzsperre (`crm:fetch-mails`):** [`FetchMailsCommand.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/FetchMailsCommand.php) überspringt Platzhalter-Passwörter und aktiviert bei `AUTHENTICATIONFAILED` automatisch einen 6-Stunden-Cooldown pro Postfach (verhindert minütliche Log-Flutung und Fail2ban-Sperren).
  - **Backup-Mail-Entkopplung:** [`config/backup.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/config/backup.php) verzichtet auf nächtliche Erfolgs-E-Mails und bindet Fehler-E-Mails an aktive Produktions-Konfiguration, sodass `backup:run` und `backup:clean` auf Stage niemals am SMTP-Versand scheitern.
  - **Kryptografischer Bridge-Fallback (`HMAC-SHA256`):** [`server-twilio.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/server-twilio.js) und [`SystemSecurityService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SystemSecurityService.php) leiten ohne separates `GEMINI_BRIDGE_SECRET` einen domänengetrennten `HMAC-SHA256` aus `APP_KEY` ab (behebt HTTP-403 / Code-4003 zwischen internen Mittwald-Containern auf `/api/ai/verify-token` und `/api/log/websocket`).
  - **Exakte Packagist-Semver-Prüfung:** [`SystemSecurityAuditCommand.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/SystemSecurityAuditCommand.php) wertet Versions-Constraints via `versionMatchesPackagistConstraint()` auch ohne installiertes `composer/semver` (`--no-dev`) präzise aus (eliminiert Fehlalarme historischer Alt-CVEs).
  - **PHP 8.4 QR-Code & Livewire-Navigation:** [`InvoiceService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/InvoiceService.php) kapselt `BaconQrCode`-Deprecations sauber ab; [`master-analytics.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/master/master-analytics.blade.php) und [`frontend-error-tracker.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/components/layouts/partials/frontend-error-tracker.blade.php) verhindern transiente Alpine-/DOM-Swap-Meldungen bei `wire:navigate`.

---

### 7. 24/7 Selbstheilungs-Wächter & Antigravity-Auffang-Pool (`SentinelTriageService`)

Damit selbst dann kein einziger Fehler verloren geht oder unbemerkt bleibt, wenn das lokale Notebook ausgeschaltet und Antigravity offline ist, arbeitet auf Stage (`https://stage.mein-seelenfunke.de`) rund um die Uhr alle 5 Minuten der zweistufige **Funkira Selbstheilungs-Wächter** ([`SentinelTriageService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SentinelTriageService.php)):

```mermaid
flowchart TD
    A["Alle Quellen: Portal, Queue, PHP-Shutdown, Android, Mini-PC, Desktop & Bridges"] --> B["SystemErrorLogger + Dead-Letter-Spool (0% Verlust)"]
    B --> C["5-Minuten Cronjob: system:sentinel-triage --probe-bridges"]
    C --> D{"Stufe 1: Sicher autonom heilbar?"}
    D -- "Ja (Cache/View-Stream, Watchdog, abgelaufenes 419 < 25, erholte Bridge)" --> E["[AUTO-GEHEILT] -> 7-Tage-Archiv (status = success)"]
    D -- "Nein (Echter Code-Bug oder Fehler-Sturm)" --> F["Stufe 2: 1x pro Fingerprint Funkira-Dossier bauen"]
    F --> G["24/7 Auffang-Pool: antigravity_inbox.json + Tab 'Auffang-Pool'"]
    G --> H["Notebook startet: FunkenwerkMcp.exe --sentinel-sync / ./funki start"]
    H --> I["1:1 Sync in lokales 127.0.0.1 SystemLog + Bereit für Antigravity Code-Fix"]
```

1. **Lückenlose Erfassung ("Jeder noch so klebrige Fehler"):**
   - **PHP Fatal / Shutdown / OOM Catcher:** `SystemErrorLogger::registerFatalShutdownHandler()` (registriert in [`AppServiceProvider.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Providers/AppServiceProvider.php)) fängt selbst harte PHP-Prozessabbrüche (`E_ERROR`, `E_PARSE`, `E_COMPILE_ERROR`, Memory-Limit, Max-Execution-Time) ab.
   - **Kompilierte Blade-View-Rückauflösung:** Fehler in `storage/framework/views/*.php` werden über den eingebetteten `**PATH ... .blade.php ENDPATH**`-Header automatisch auf die echte `.blade.php`-Quelldatei zurückgeführt.
   - **Dead-Letter-Notfall-Spool (`sentinel_deadletter.jsonl`):** Falls MySQL im Moment eines Fehlers kurzzeitig nicht erreichbar ist, puffert `writeDeadLetterFallback()` den geheimnisbereinigten Eintrag lokal auf Disk und `drainDeadLetterSpool()` spült ihn beim nächsten Wächter-Lauf verlustfrei in `system_logs` nach.
   - **Aktiver Queue- & Echtzeit-Brücken-Puls:** `probeActiveInfrastructure()` importiert abgebrochene Einträge aus `failed_jobs` und prüft per IPv4-Puls `https://ws.mein-seelenfunke.de/twilio-health` (`a-iurgq8`) sowie `https://api-live-bridge.mein-seelenfunke.de/health` (`a-yeq7el`). Sobald eine gestörte Brücke wieder mit `HTTP 200` antwortet, heilt der Wächter die offene Warnung automatisch auf `[AUTO-GEHEILT]`.

2. **Stufe 1: Autonome Infrastruktur-Selbstheilung (0 Quellcode-Mutation):**
   - Verifizierbare Betriebsereignisse (bestätigte Client-Watchdog-Heilungen, kompilierte View-/Cache-Stream-Konflikte nach `view:clear` + `cache:clear`, einzelne abgelaufene Browser-Formular-Tokens `< 25`, abgemeldete FCM-Tokens sowie abgeklungene DNS-/Cache-Engpässe nach positivem Live-DB/Cache-Test) werden automatisch bereinigt und ehrlich als `[AUTO-GEHEILT]` (`status = 'success'`) ins 7-Tage-Archiv verschoben.
   - Echte Code-Fehler in Views (`Undefined variable`, `Syntax error`) oder Fehler-Stürme (z. B. `HTTP 419 >= 25`) werden **niemals** blind weggeheilt, sondern eskalieren zwingend in Stufe 2.

3. **Stufe 2: Antigravity-Auffang-Pool (`Auffang-Pool` / `antigravity_inbox.json`):**
   - Funkira verändert niemals eigenmächtig PHP-/JS-/C#-Quellcode auf dem Server. Stattdessen baut `SentinelTriageService` **strikt 1x pro eindeutigem SHA-256-Fingerprint** (keine Token-Schleifen bei Wiederholungsfehlern!) ein geheimnisbereinigtes **Funkira Reparatur-Dossier**:
     - Exakte Zieldatei, Zeilennummer und `+/- 12` Zeilen bereinigter Quellcode-Ausschnitt (strenge Pfad-Whitelist, `.env`/`storage/`/`vendor/` hart gesperrt)
     - Aufrufer- und Abhängigkeits-Nachbarschaft aus `SystemNeuralNode`
     - Deterministische Diagnose plus optionale einmalige `gemini-2.5-flash` Wurzelursachen- und Fix-Plan-Analyse (IPv4, 24h Cache)
     - Gedrosselter Smartphone-Push-Alarm (max. 1x pro 6h, ausschließlich bei `criticality = critical` und vorhandener `firebase-service-account.json`).

4. **24/7 Offline-Brücke zum lokalen Notebook (`FunkenwerkMcp` & `./funki`):**
   - Über die Ed25519-Key-Only SSH-Presets `stage_sentinel_export` und `stage_sentinel_resolve_all` in [`McpSecurityGuard.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/McpSecurityGuard.cs) und den CLI-Befehl `FunkenwerkMcp.exe --sentinel-sync` ([`Program.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/Program.cs), integriert in `./funki start` und `./funki sentinel`) zieht das lokale System beim Einschalten automatisch den gesamten 24/7-Stage-Auffang-Pool nach `storage/app/private/antigravity_inbox.json` und synchronisiert alle Einträge 1:1 in die lokale Datenbank (`127.0.0.1`).
   - Vollständig abgesichert durch [`SentinelSelfHealingAndInboxTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SentinelSelfHealingAndInboxTest.php) sowie Selbsttest #45 in `FunkenwerkMcp` (`45/45 PASSED`).
