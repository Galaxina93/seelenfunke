# Vollautomatische 4-Säulen-Universums-Selbstheilung & Zero-Log-Baseline

## 1. Überblick & „Zero-Log-Baseline“ Prinzip
Das Mein-Seelenfunke-Ökosystem arbeitet nach dem verbindlichen **Zero-Log-Baseline-Prinzip**:
* **Haupt-Logdateien = `0 Bytes` (leer):** Bedeutet auf einen Blick, dass im gesamten Universum (**Webportal/Stage**, **Manufaktur Mini-PC**, **Windows Funkenwerk** und **Android-App**) kein einziger unbearbeiteter Fehler offen ist.
* **Haupt-Logdateien > `0 Bytes`:** Es liegt ein neuer, noch unbearbeiteter Vorfall vor, der automatisch erfasst, geheilt, getestet, im Abschlussbericht protokolliert und anschließend verlustfrei ins **Log-Archiv** verschoben wird.

---

## 2. Ablageorte: Aktiv (`0 Bytes`) vs. Revisionssicheres `Log-Archiv` (30 Tage)

| Plattform | Aktive Haupt-Logs (Normalzustand = `0 Bytes` / leer) | Verlustfreies Log-Archiv (Geheimnisbereinigt, 30 Tage Rotation) |
| :--- | :--- | :--- |
| **Portal & Stage (`LogArchiveService`)** | `storage/logs/laravel.log` (`0 B`), `storage/logs/twilio_bridge.log` (`0 B`), `storage/logs/cron-*.log` (`0 B`), `storage/app/private/antigravity_inbox.json` (`items: []`), Tab `Offen` in `/admin/global-logs` (`0`) | `storage/logs/archive/YYYY-MM/healed_YYYY-MM-DD_HHmmss_*.log` + DB-Tab `Archiv / Geheilt` in `/admin/global-logs` |
| **Manufaktur Mini-PC (`192.168.188.151`)** | `/home/alina/.local/share/FunkenwerkOS/sentinel_history.json` (`0 B`), kein offenes `antigravity_error_handoff.json`, kein `sentinel_queue.json` | `/home/alina/.local/share/FunkenwerkOS/archive/sentinel_archive_YYYY-MM.jsonl` & `archive/handoff_*.json` (`chmod 0600`) |
| **Windows Funkenwerk (`FunkenwerkMcp` & `Tresor`)** | `%LOCALAPPDATA%\Funkenwerk\error_sentinel_queue.json` (nicht vorhanden / `0 B`), `%LOCALAPPDATA%\FunkenwerkTresor\funkenwerk_tresor_boot.log` (`0 B`) | `%LOCALAPPDATA%\FunkenwerkTresor\archive\healed_YYYY-MM-DD_HHmmss_funkenwerk_tresor_boot.log` + Stage-Archiv |
| **Android-App (`ErrorSentinelReporter`)** | `files/error_sentinel_queue.json` (wird nach Hintergrund-Versand via `ShopWatchtowerJobService` sofort gelöscht) | `files/error_sentinel_history.json` (verschlüsselter lokaler Ringpuffer) + Stage-Archiv |

---

## 3. Verbindliche 5-Schritt-Kette jedes Selbstheilungslaufs (`Seelenfunke Universum Selbstheilung`)
Zu jeder vollen Stunde (`CRON_TZ=Europe/Berlin 0 * * * *`) startet [sidecar.json](file:///C:/Users/konta/.gemini/config/sidecars/seelenfunke-universe-self-heal/sidecar.json) den stillen C#-Wächter `FunkenwerkMcp.exe --sentinel-hourly-guard` (`0` neue Einzel-Chats, `0` KI-Token im Normalbetrieb):

1. **Stiller C#-Pull & 3-Phasen-Entscheidung (`EvaluateSentinelHourlyState`):**
   * `FunkenwerkMcp.exe --sentinel-hourly-guard` zieht per gepinntem Ed25519-SSH den Stage-Pool (`Mittwald`) und direkt den Mini-PC (`192.168.188.151`).
   * **Fall 1 (`open_pool_count == 0` & alle Haupt-Logs `0 Bytes`):** Stiller Sofort-Exit in `< 3 s` (`ExitCode 0`, `0` Chats, `0` Token).
   * **Fall 2 (`open_pool_count == 0`, harmlose Info-Logs `> 0 Bytes` ohne `[ERROR]`/`CRITICAL`/`Exception`):** Automatische C#-Rotation nach `storage/logs/archive/` und Nullstellung auf `0 Bytes` (`0` Chats).
   * **Fall 3 (Echter ungelöster Fehler):** SHA-256-deduplizierte Benachrichtigung ausschließlich per `agentapi send-message` in den einzigen zentralen `Leitung`-Chat (`a75e6db9-d026-4955-97c0-6e8695d6193d`).
2. **Code-Fix & Pflicht-Test (`100 % Grün`):**
   * PHP/JS/Blade -> `php artisan test` in `mein_php_server`.
   * Mini-PC (`funkenwerk-os-linux`) / Windows (`funkenwerk`) -> `dotnet build` & Selbsttest.
   * Android-App (`android-app` + Mirror `AndroidStudioProjects\seelenfunke-android`) -> `gradlew testDebugUnitTest`.
3. **Automatischer Rollout:**
   * Stage-Deploy (`--stage-deploy --skip-shop-assets`), Mini-PC-Update bzw. signierter 4096-Bit-RSA-Android-OTA-Rollout (`--stage-publish-apk`).
4. **Protokollierung in `Abschlussberichte/`:**
   * Jeder behobene Code-Fehler wird mit Datum, Plattform, Ursache, geänderter Datei und Testergebnis im untenstehenden **Chronologischen Selbstheilungs-Protokoll** (sowie bei Architekturänderungen im jeweiligen Abteilungs-Bericht) dokumentiert.
5. **Archivieren & Nullstellen (`0 Bytes`):**
   * `FunkenwerkMcp.exe --sentinel-sync --resolve-all` verschiebt alle Haupt-Logs auf Stage, Mini-PC und Lokal geheimnisbereinigt ins `archive/` und setzt sämtliche Haupt-Logdateien wieder exakt auf **`0 Bytes`**.

---

## 4. Chronologisches Selbstheilungs-Protokoll

### 16.11.2026 (Initial-Lauf & Einführung Zero-Log-Baseline)
* **Erfasste & geheilte Befunde (`5 Dossiers` + `2 Roh-Logdateien`):**
  1. `Mini-PC` (`DocumentArchiveAndNasEngine.cs` / `LinuxVaultAndApi.cs`): Historischer Konfigurations-Hinweis (`NAS SMB-Anmeldung` ohne hinterlegtes optionales Passwort), erledigter `GoAway`-Session-Rollover und einmaliger `portal_pulse` `Status 0` WLAN-Wackler in `AutoHealRecoverableHistory` aufgenommen und direkt in `/home/alina/.local/share/FunkenwerkOS/archive/` (`91.416 Bytes`) überführt.
  2. `Android-App` (`ErrorSentinelReporter.kt`, `ShopWatchtowerJobService.kt`, `SentinelTriageService.php`): Kurzzeitige Mobilfunk-/WLAN-Wechsel (`UnknownHostException` in `OrganizerRepo` bei aktivem Offline-Cache) als selbstheilend klassifiziert; Hintergrund-Queue-Flush an `ShopWatchtowerJobService` gekoppelt; echte `BuildConfig.VERSION_NAME`/`VERSION_CODE` ergänzt.
  3. `Portal / System` (`LogArchiveService.php`): Roh-Log-Ingestion + verlustfreie Archiv-Rotation (`storage/logs/archive/2026-11/`) implementiert (`4.954 Bytes` aus `laravel.log` & `twilio_bridge.log` archiviert).
* **Testnachweis:** `46/46` `FunkenwerkMcp`-Selbsttests + `7/7` PHPUnit-Tests (`LogArchiveServiceTest` & `SentinelSelfHealingAndInboxTest`, `71 Assertions`) + `FunkenwerkOS.Linux` Release-Build (`0 Warnungen, 0 Fehler`) grün.
* **Endstatus Haupt-Logs:** `laravel.log = 0 B`, `twilio_bridge.log = 0 B`, Mini-PC `sentinel_history.json = 0 B`, `open_pool_count = 0`.

### 16.11.2026 (Verbindlicher 5-Schritt-Selbstheilungslauf & Universums-Zero-Log-Baseline)
* **Erfasste & geheilte Befunde (`4 Stage/Lokal-Dossiers`):**
  1. `Mini-PC` (`LinuxVaultAndApi.cs` / `portal_pulse`): Telemetrie-Meldung Status 429 per Playbook 5 (`client_portal_pulse_recovered`) auto-geheilt und revisionssicher archiviert.
  2. `Android-App` (`FunkiraLiveViewModel.kt` / `funkira_live_ws`): Kurzzeitiger Stream-Verbindungsabbruch (`EOFException`) via Playbook 6 (`transient_mobile_ws_recovered`) auto-geheilt.
  3. `Portal / Sicherheit` (`SystemSecurityAuditCommand.php` / `security:package_audit`): Überholte Warnung nach Bestätigung von 0 echten Produktions-CVEs (6/6 System-Schranken grün) via Playbook 7 (`security_audit_verified_clean`) archiviert.
  4. `Portal / Infrastruktur` (`MasterAnalytics.php` / `system:health_fail` - `backup`): Datenbank-Backup per `backup:run --only-db` frisch erstellt (`2,32 MB`) und via neuem Playbook 8 (`health_backup_fresh_verified`) auto-geheilt.
  5. `Portal / Infrastruktur` (`MasterAnalytics.php` / `system:health_fail` - `telephony`): Mittwald Twilio Audio-Bridge Container via `stage-restart-workers` (mittnitectl `pid=1454`) neu gestartet und via neuem Playbook 9 (`health_telephony_worker_recovered`) auto-geheilt.
  6. `Portal / CLI` (`SentinelTriageService.php` / `interactive_cli_typo_handled`): Playbook 10 für interaktive Tippfehler bei manuellen Artisan-CLI-Aufrufen implementiert.
* **Testnachweis (`100 % Grün` über alle 4 Säulen):**
  - **Funkenwerk MCP**: `57/57` gehärtete Zero-Trust- und E2E-Selbsttests bestanden (`FunkenwerkMcp.exe --self-test`).
  - **Shop / Portal (PHPUnit)**: `8/8` Tests (`71 Assertions`) in `SentinelSelfHealingAndInboxTest` + `1/1` Test (`16 Assertions`) in `LogArchiveServiceTest` bestanden.
  - **Mini-PC (FunkenwerkOS.Linux)**: Release-Build erfolgreich (`0 Warnungen, 0 Fehler`).
  - **Windows Funkenwerk Desktop**: Release-Build erfolgreich (`0 Fehler`).
  - **Android-App (Kotlin / Compose)**: `./gradlew testDebugUnitTest` erfolgreich (`BUILD SUCCESSFUL`, 29 Tasks).
* **Endstatus Haupt-Logs (Zero-Log-Baseline):**
  - `storage/logs/laravel.log`: **`0 Bytes`**
  - `storage/logs/twilio_bridge.log`: **`0 Bytes`**
  - Mini-PC `/home/alina/.local/share/FunkenwerkOS/sentinel_history.json`: **`0 Bytes`**
  - `storage/app/private/antigravity_inbox.json`: `open_pool_count = 0`, `items = []`
  - Stage-Pool (Mittwald `seelenfunke-stage`): `open_pool_count = 0`, gelöst und archiviert.

### 16.11.2026, 13:25 Uhr (4-Säulen-Ursachenheilung, Rate-Limiter-Entkopplung, Android OTA `v1.8.0` & Zero-Log-Baseline)
* **Erfasste & dauerhaft im Code geheilte Befunde (`4 Stage-Dossiers`, `14 Stage-Cron-Logs`, `Mini-PC History` & `Tresor Boot-Log`):**
  1. `Portal & Mini-PC` (`RouteServiceProvider.php`, `routes/api.php`, `LinuxVaultAndApi.cs`, `SentinelTriageService.php` / `stage_log_id: 3425`): Dedizierten `RateLimiter::for('monitor-pulse')` (`180/min` getrennt nach `user_id + Token-ID + IP`) eingeführt, damit sich Mini-PC, Windows Funkenwerk und Android-App bei geteiltem Admin-Konto nicht mehr gegenseitig in HTTP `429` drosseln; auf dem Mini-PC (`192.168.188.151`) `Retry-After`-Backoff und `AutoHealRecoverableHistory` für `Status 429/502/503/504` ergänzt (`CONNECTED = True`, `STAGE · 137 ms`, `0` neue `429`).
  2. `Portal / Sicherheit` (`SystemSecurityAuditCommand.php` / `stage_log_id: 3428`): `auditNpmPackages()` auf `--package-lock-only --omit=dev --json` plus `filterProdNpmVulnerabilities()` gehärtet (ignoriert verwaiste `node_modules/`-Dev-Reste, behält 100 % Schärfe für echte Prod-CVEs); auf Stage `vendor/`-Sync + `APP_DEBUG=false` verifiziert (`135` Composer- & `953` NPM-Pakete geprüft -> **`0` Prod-CVEs, `6/6` System-Schranken grün**).
  3. `Portal / Stage-Logs` (`LogArchiveService.php`, `routes/console.php`): Alle aktiven `storage/logs/*.log` inkl. `cron-*.log` (exkl. `archive/`) in Fehler-Ingestion und `0-Byte`-Archivierung aufgenommen; fehlerfreie temporäre `cron-*.log` werden nach `onSuccess` direkt auf `0 Bytes` gehalten.
  4. `Windows Funkenwerk` (`funkenwerk-tresor/App.xaml.cs`, `funkenwerk-mcp/Program.cs`, `funki`): Reguläres Close-Stacktrace-Logging und CRT-Shutdown-Unload-Noise in `FunkenwerkTresor` beseitigt; `funkenwerk_tresor_boot.log` wird von `FunkenwerkMcp --sentinel-sync` überwacht und bei `--resolve-all` verlustfrei nach `%LOCALAPPDATA%\FunkenwerkTresor\archive\` archiviert; Dual-Binary-Sync für `FunkenwerkMcp.exe` eingerichtet; zusätzlich `PreToolUse`-Quoting in `.agents/hooks.json` entstört.
  5. `Android-App` (`ErrorSentinelReporter.kt`, `FunkiraLiveViewModel.kt`, `FalconClientTools.kt`, `build.gradle.kts` / `stage_log_id: 3424` & `3433`): Transiente WebSocket-/Mobilfunk-Abbrüche (`EOFException`, `SocketException`, Transport-`SSLException`) auf `*_ws`-Kanälen gefiltert, `"Verbindungsfehler: null"` im UI eliminiert, `Dispatchers.Main.immediate` in `FalconClientTools` gehärtet, 1:1 mit Windows-Mirror synchronisiert (`211/211` Dateien SHA-256 identisch) und als 4096-Bit-RSA-signiertes Release **`v1.8.0` (`versionCode = 24`)** per `--stage-publish-apk` ausgerollt.
* **Testnachweis (`100 % Grün` über alle 4 Säulen):**
  - **Portal / Stage (PHPUnit in `mein_php_server`)**: `221 passed (4.478 assertions), 0 failed`.
  - **Manufaktur Mini-PC (`FunkenwerkOS.Linux`)**: `44/44 TESTS BESTANDEN` (lokal + live auf `192.168.188.151`, `funkenwerk-os.service` `active running`).
  - **Windows Funkenwerk (`Tresor` & `FunkenwerkMcp`)**: `27/27` (`FunkenwerkTresor --self-test`) & `58/58` (`FunkenwerkMcp.exe --self-test`).
  - **Android-App (`seelenfunke-android`)**: `65/65` Unit-Tests in `11` Test-Suiten grün (`gradlew testDebugUnitTest`) + `assembleRelease` (`release-b24`).
* **Verifizierter Endstatus (Harte Gegenprobe nach `--sentinel-sync --resolve-all`):**
  - Stage `storage/logs/laravel.log` & `14x cron-*.log`: **`0 Bytes`** (`open_pool_count = 0`)
  - Lokal `storage/logs/laravel.log` & `twilio_bridge.log`: **`0 Bytes`** (`antigravity_inbox.json`: `open_pool_count = 0`, `items: []`)
  - Mini-PC `/home/alina/.local/share/FunkenwerkOS/sentinel_history.json`: **`0 Bytes`** (kein Handoff, keine Queue)
  - Windows `%LOCALAPPDATA%\FunkenwerkTresor\funkenwerk_tresor_boot.log`: **`0 Bytes`** (keine Queue)
  - Android Stage-OTA `manifest.json`: **`v1.8.0` (`version_code = 24`)**, `open_pool_count = 0`.

### 16.11.2026, 14:15 Uhr (5-Schritt-Universums-Selbstheilung, AG-Inbox CLI-Entkopplung, Android OTA `v1.8.2` (Build 26) & Verifizierte Zero-Log-Baseline `0 Bytes`)
* **Erfasste & dauerhaft im Code geheilte Befunde:**
  1. `Portal & CLI-Brücke` (`AppServiceProvider.php`, `AntigravityDirectInboxCommand.php`, `SentinelTriageService.php`, `Program.cs`):
     - `system:ag-inbox` und `system:sentinel-triage` in `$ignoredInteractiveCommands` in `AppServiceProvider.php` aufgenommen, damit interaktive oder CLI-Bridge-Aufrufe mit Exit 1 (z. B. lokaler Komplettierungs-Versuch für Stage-Meldung) nicht fälschlich als `Cronjob fehlgeschlagen` registriert werden.
     - `--ignore-missing` Option in `AntigravityDirectInboxCommand.php` und `FunkenwerkMcp` ergänzt, sodass idempotente Fertigmeldungen über Umgebungen hinweg sauber quittiert werden.
     - Neues Playbook 11 (`interactive_ag_inbox_reconciled`) in `SentinelTriageService.php` implementiert, das bestehende und künftige AG-Inbox-Abschlüsse autonom auto-heilt und archiviert.
  2. `Android-App` (`WakeWordService.kt`, `AgDirectInboxManagerTest.kt`, `build.gradle.kts`):
     - Unaufgelöste SDK-Konstante `EXTRA_AUDIO_SOURCE_SAMPLE_RATE` -> `EXTRA_AUDIO_SOURCE_SAMPLING_RATE` korrigiert.
     - `AgDirectInboxManagerTest.kt` auf den neuen Badge-Text `"Bei Falcone"` aktualisiert.
     - 1:1 Windows-Mirror synchronisiert (`seelenfunke-android`), alle 65 Unit-Tests 100 % grün.
     - Release-APK **`v1.8.2` (Build 26)** signiert gebaut (`4.6 MB`, SHA-256: `7bfc62eba78bdbabc02c5d7e0ccfa0a3331a44c73ba06f60615871a47753f4c9`) und via `--stage-publish-apk` auf Stage ausgerollt.
* **Testnachweis (`100 % Grün` über alle 4 Säulen):**
  - **Portal / Stage (PHPUnit in `mein_php_server`)**: `SentinelSelfHealingAndInboxTest` (8/8 Tests, 73 Assertions) & `LogArchiveServiceTest` (2/2 Tests, 32 Assertions) bestanden.
  - **Manufaktur Mini-PC (`FunkenwerkOS.Linux`)**: `45/45 TESTS BESTANDEN` live auf `192.168.188.151`.
  - **Windows Funkenwerk (`FunkenwerkMcp` & `Tresor`)**: `58/58` MCP-Selbsttests (`FunkenwerkMcp.exe --self-test`) & FunkenwerkTresor Selbsttest bestanden.
  - **Android-App (`seelenfunke-android`)**: `65/65` Unit-Tests grün (`BUILD SUCCESSFUL`) + `assembleRelease` (`v1.8.2`, Build 26).
* **Verifizierter Endstatus (Zero-Log-Baseline = 0 Bytes):**
  - Portal & Stage (`storage/logs/laravel.log`, `twilio_bridge.log`, `14x cron-*.log`): **`0 Bytes`**
  - `storage/app/private/antigravity_inbox.json`: `open_pool_count = 0`, `items: []`
  - Manufaktur Mini-PC (`/home/alina/.local/share/FunkenwerkOS/sentinel_history.json`): **`0 Bytes`** (kein Handoff, keine Queue)
  - Windows Funkenwerk (`%LOCALAPPDATA%\FunkenwerkTresor\funkenwerk_tresor_boot.log`): **`0 Bytes`** (keine Queue)
  - Android Stage-OTA `manifest.json`: **`v1.8.2` (`version_code = 26`)**, `open_pool_count = 0`.

### 16.11.2026, 18:55 Uhr (Stündlicher Selbstheilungs-Wächter: `VARCHAR(191)`-Spaltenschutz in `SystemLog` & Stage-Pool-Bereinigung)
* **Erfasste & dauerhaft im Code geheilte Befunde (`2 Stage-Dossiers` + `1 lokaler Sync-Folgefehler`):**
  1. `Portal / Sentinel-Sync` (`app/Models/System/SystemLog.php`, `app/Services/System/SentinelTriageService.php`): Beim Import überlanger `[STAGE]`-Dossier-Titel (`> 190` Zeichen) erzeugte `Str::limit($prefixedTitle, 190)` inklusive `'...'`-Suffix `193` Zeichen und überschritt damit `system_logs.title` (`VARCHAR(191)` -> `SQLSTATE[22001]: Data too long for column 'title'`). Zentral über `SystemLog::clampColumnValue()` und einen `booted()`-`saving`-Hook im Model `SystemLog` (`title <= 191`, `action_id <= 191`, `type <= 64`, `status <= 64`, `message <= 16000`) sowie in allen Schreibpfaden von `SentinelTriageService` (inkl. Nullsafe-Schutz `!empty($top['culprit_file'])` in `writeInboxSnapshotFile()`) dauerhaft abgesichert.
  2. `Stage / Einmaliger CLI-Stdin-Aufruf` (`stage_log_id: 3449` & `3450`, `route_or_command: Standard input code`): Repo-weiter Code-Scan (`app/`, `routes/`, `resources/`, `funkenwerk-mcp/`, `funkenwerk/`, `funkenwerk-os-linux/`, `android-app/`, `scripts/`) bestätigte `0` Code-Stellen mit `category`/`action` auf `system_logs`; Ursache war ein einmaliger interaktiver CLI-Stdin-Befehl auf Stage, der sauber über `--sentinel-sync --resolve-all` ins Archiv überführt wurde.
* **Testnachweis (`100 % Grün`):**
  - **Portal / System (PHPUnit in `mein_php_server`)**: `9/9` Tests in `SentinelSelfHealingAndInboxTest` + `5/5` Tests in `SystemLogHardeningTest` (`14/14 Tests, 124 Assertions`) inkl. neuem Grenz- und Überlängen-Test (`> 340` Zeichen) bestanden.
* **Verifizierter Endstatus (Zero-Log-Baseline = 0 Bytes):**
  - Stage & Lokal `storage/logs/laravel.log`: **`0 Bytes`**
  - `storage/app/private/antigravity_inbox.json`: `open_pool_count = 0`, `items: []`
  - Bestätigung durch `FunkenwerkMcp.exe --sentinel-hourly-guard`: **`0` offene Dossiers, `0 Bytes` Fehler-Logs**.

### 16.11.2026 (Gemini-Bridge Health-Check-Präzisierung & Sofort-Warnprozess bei echten Bridge-Ausfällen)
* **Architektur- & Selbstheilungs-Erweiterung (`MasterAnalytics.php` & `SentinelTriageService.php`):**
  1. **Präzise JSON-Health-Endpunkte statt WebSocket-Pfad ([`MasterAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Master/MasterAnalytics.php)):** Die Live-Gesundheitsprüfung der Echtzeit-Bridges prüft gezielt `/twilio-health` (`a-iurgq8` / `ws.mein-seelenfunke.de`) und `/health` (`a-yeq7el` / `api-live-bridge.mein-seelenfunke.de`) auf `status === "ok"` inklusive `400 ms`-Retry anstelle von `/twilio-stream` (verhindert Schein-Fehler durch fehlende WebSocket-Upgrade-Header).
  2. **Sofort-Warnkette & Auto-Heil-Quittierung ([`SentinelTriageService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SentinelTriageService.php)):** Echte Ausfälle von `a-iurgq8` oder `a-yeq7el` werden sofort unter `sentinel:bridge_down:*` erfasst, schalten die Statuskachel im Operativen Status (`/admin/dashboard`) auf Rot, lösen eine gedrosselte Warn-Mail sowie einen Admin-Push via `FirebaseService::sendToAdmins` aus und werden nach erfolgreicher Wiederherstellung automatisch mit `[AUTO-GEHEILT]` quittiert.
* **Testnachweis:** **`23/23 Tests Grün`**.


