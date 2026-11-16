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
Zu jeder vollen Stunde (`CRON_TZ=Europe/Berlin 0 * * * *`) führt Antigravity automatisch folgende Kette aus:

1. **Universeller Pull & Roh-Log-Prüfung:**
   * `FunkenwerkMcp.exe --sentinel-sync` zieht per gepinntem Ed25519-SSH den Stage-Pool (`Mittwald`) und direkt den Mini-PC (`192.168.188.151`).
   * `LogArchiveService::ingestActiveLogFilesIntoSentinel()` überführt auch unstrukturierte `[ERROR]`/`CRITICAL`-Zeilen aus `storage/logs/*.log` dedupliziert in `system_logs`.
   * Sind `open_pool_count == 0` und alle Haupt-Logs `0 Bytes`, beendet sich der Lauf sofort in unter 3 Sekunden.
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
