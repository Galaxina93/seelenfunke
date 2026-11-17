# System: 24/7 Watchtower & 4-Säulen Health-Puls (Autarker Uptime-Kuma-Ersatz)

> **Stand:** November 2026 | **Abteilung:** System & Infrastruktur | **Status:** Produktiv & 100 % Grün getestet

---

## 1. Architekturziel & Unternehmerischer Mehrwert

Statt ein separates Fremd-Tool wie Uptime Kuma samt Docker-Container, externer Datenbank und doppelter Pflege aufzusetzen, besitzt das Seelenfunke- und Funkenwerk-Ökosystem einen nativen, mehrschichtigen **24/7 Watchtower & 4-Säulen Health-Puls**:

1. **Gebündelter Server-Puls (`< 10 ms`) unter `/api/system/health-pulse`:** Prüft in einem einzigen verschlüsselten Aufruf alle vier geschäftskritischen Säulen:
   - **`shop` (Shop, Datenbank, Cache & SSL):** Live-Datenbank-Latenz (`SELECT 1`), Cache-Roundtrip, freier Festplattenspeicher (`%` / `GB`) sowie verbleibende SSL-Zertifikatslaufzeit (`> 14 Tage` grün, `8..14 Tage` Warnung, `<= 7 Tage` kritisch; 6h gecacht).
   - **`cronjobs` (Scheduler & Hintergrund-Zeitplan):** Frische von `scheduler_last_run` (`<= 180 s` grün, `181..600 s` Warnung, `> 600 s` kritisch), `scheduler_last_exception` sowie aktive DB-Cronjobs (`system_cronjobs`) mit Fehlerstatus.
   - **`queues` (Queue-Worker & Job-Rückstau):** Lebendigkeit über `ai-worker-heartbeat` / `system/ai_worker_heartbeat.txt`, hängende Aufträge in `jobs` (`> 600 s` ohne Worker-Puls = kritisch) sowie fehlgeschlagene Hintergrund-Jobs in `failed_jobs`.
   - **`backups` (Shop-DB- & Synology-NAS-Sicherung):** Aktualität des neuesten Spatie-`.zip`-Backups (`<= 26 Std` grün, `27..48 Std` Warnung, `> 48 Std` kritisch) kombiniert mit dem Live-Herzschlag der Mini-PC-zu-Synology-NAS-Deltasicherung (`POST /api/system/health-pulse/nas-heartbeat`).
2. **Echte Außen-Überwachung außerhalb von Mittwald (3 unabhängige Wächter-Schichten):**
   - **Schicht 1: Funkenwerk Android-App (`ShopWatchtowerJobService.kt`):** Läuft auf Alinas Smartphone (24/7 eingeschaltet, WLAN & 5G) über den nativen Android `JobScheduler` (`15 Min`, `NETWORK_TYPE_ANY`, `setPersisted(true)` über Geräte-Neustarts hinweg, `0` Zusatz-Bibliotheken).
   - **Schicht 2: Werkstatt Mini-PC (`FunkenwerkOS.Linux`):** Prüft alle `3 Minuten` im Hintergrund den Shop-Puls und meldet nach jedem Snapshot-Delta-Backup automatisch den NAS-Sicherungsstatus an das Portal.
   - **Schicht 3: Synology NAS (`192.168.188.33` / `\\nas\Backups\Unterlagen\Funkenwerk_Watchtower`):** Vorbereitetes, abhängigkeitsfreies Bash-Wächterskript (`funkenwerk_watchtower.sh`) für den Synology DSM Aufgabenplaner (alle 2 Minuten) inkl. Direkt-Notruf via Telegram/Webhook selbst bei komplettem Mittwald-Ausfall.
3. **Intelligenter Fehlalarm-Schutz (Internet-Gegenprüfung & 2-Strike-Regel):**
   - Antwortet `mein-seelenfunke.de` nicht, prüfen Smartphone, Mini-PC und Synology NAS sofort gegen neutrale Gegenpunkte (`connectivitycheck.gstatic.com/generate_204` / `1.1.1.1`). Ist lediglich das eigene WLAN, ein Funkloch oder der nächtliche FRITZ!Box-Reconnect Ursache, wird **kein Fehlalarm** ausgelöst.
   - Erst bei bestätigter eigener Internetverbindung und zwei aufeinanderfolgenden Verbindungsfehlern (bzw. gemeldetem `status = critical`) erfolgt ein Alarm (mit 60-Minuten-Cooldown sowie automatischer **`Entwarnung`** nach Genesung).
4. **90-Tage-Uptime-Historie & Funkira 3D-Kuppelhallen-Kopplung:**
   - Tägliche Verfügbarkeits-Buckets werden atomar (`0600`) in `storage/app/private/system_uptime_90d.json` geführt und im `/admin/dashboard` (`System & Infrastruktur`) als **90-Tage-Uptime (`%`)** samt 30-Tage-Verlaufsbalken im Clean-Design dargestellt.
   - Kritische Säulen fließen dedupliziert (`watchtower:pillar:{key}`) in `SystemLog` und Funkiras 3D-Analyse-Kuppelhalle ein und heilen sich bei Rückkehr auf Grün vollautomatisch (`[AUTO-GEHEILT]`).

---

## 2. Beteiligte Systemdateien & Pfade

### Laravel Hauptportal (`Mein-Seelenfunke`)
- [`SystemHealthPulseService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SystemHealthPulseService.php): Zentrale 4-Säulen-Auswertung, 90-Tage-Uptime-Ledger, Außen-Wächter-Knotenregister, NAS-Backup-Herzschlag und `SystemLog`-Selbstheilung.
- [`SystemHealthPulseCommand.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/System/SystemHealthPulseCommand.php): Artisan-CLI-Befehl `php artisan system:health-pulse` (`--strict`, `--sync-logs`, `--json`).
- [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php): Geschützte Endpunkte vor der Vollzugriffs-Schranke (`GET /api/system/health-pulse`, `POST /api/system/health-pulse/nas-heartbeat` für `monitor:pulse` / `ai:funkira` / `*` sowie `POST /api/system/health-pulse/issue-token` exklusiv für Voll-Admin `*`).
- [`SentinelTriageService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/SentinelTriageService.php): Automatische 5-Minuten-Einbindung in `probeActiveInfrastructure()`.
- [`MasterAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Master/MasterAnalytics.php) & [`master_scores.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/master/master-analytics-partials/master_scores.blade.php): Clean-Design Watchtower- & 90-Tage-Uptime-Kachel im `/admin/dashboard`.

### Werkstatt Mini-PC (`FunkenwerkOS.Linux`)
- [`DocumentArchiveAndNasEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/DocumentArchiveAndNasEngine.cs) & [`Program.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/Program.cs): 3-Minuten-Außen-Wächter (`ProbeShopHealthPulseWatchtowerAsync`), automatische NAS-Backup-Meldung (`ReportNasBackupHeartbeatToShopAsync`) und Selbsttest `#40` (`40/40` bestanden).

### Funkenwerk Android-App (`v1.7.2+`)
- [`ShopWatchtowerManager.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/ShopWatchtowerManager.kt) & [`ShopWatchtowerJobService.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/services/ShopWatchtowerJobService.kt): Nativer `JobScheduler`-Hintergrundwächter inkl. Rollen-Sperre (nur `admin`), Internet-Gegenprüfung, 2-Strike-Schutz und Hochprioritäts-Benachrichtigungskanal `watchtower_alert_channel`.
- [`SeelenfunkeApp.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/SeelenfunkeApp.kt), [`BootReceiver.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/receiver/BootReceiver.kt) & [`AndroidManifest.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/AndroidManifest.xml).

### Synology NAS Außenposten
- [`funkenwerk_watchtower.sh`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/scripts/nas-watchtower/funkenwerk_watchtower.sh), [`watchtower.env.example`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/scripts/nas-watchtower/watchtower.env.example) & [`ANLEITUNG_Synology_Aufgabenplaner.txt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/scripts/nas-watchtower/ANLEITUNG_Synology_Aufgabenplaner.txt) (synchron bereitgestellt auf `\\nas\Backups\Unterlagen\Funkenwerk_Watchtower`).

---

## 3. Sicherheits- & Rollen-Isolation

- **Strikte Rollen-Schranke (`401` / `403`):** Unauthentifizierte Aufrufe erhalten `401`. Kunden (`Customer`), interne Mitarbeiter (`internal`), Influencer (`influencer`) und Steuerberater (`tax_advisor`) werden selbst mit gültigem Sanctum-Token strikt mit `403 Forbidden` abgewiesen.
- **Least-Privilege Monitor-Token (`monitor:pulse`):** Externe Wächter benötigen ausschließlich das Read-Only-Recht `monitor:pulse`. Dieses Token kann weder neue Tokens ausstellen (`POST /api/system/health-pulse/issue-token` -> `403`) noch auf Finanz-, Kunden- oder Bestelldaten hinter der Vollzugriffs-Schranke zugreifen (`403`).
- **Keine Geheimnisse in Logs oder Payloads:** Alle Zusammenfassungen durchlaufen `SystemErrorLogger::sanitizeSensitiveText()` bzw. `ErrorSentinelAndWatchdog.ScrubSecrets()`.

---

## 4. Automatisierte Testabdeckung (`100 % Grün`)

- **Laravel Feature- & Security-Suite ([`SystemHealthPulseWatchtowerTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemHealthPulseWatchtowerTest.php) + `tests/Feature/Api`):** `52/52 Tests` (`621 Assertions`) bestanden (inkl. Rollen-/Scope-Matrix, 4-Säulen-Grünstatus, Erkennung von überfälligen Cronjobs/Queues/Backups/SSL, `SystemLog`-Deduplizierung & `[AUTO-GEHEILT]`-Genesung sowie CLI-JSON-Check).
- **Architektur-, Routen- & Security-Regression:** `SystemRouteAndLinkIntegrityTest` & `SystemSecurityAuditCommandTest` (`11/11 Tests`, `216 Assertions`) bestanden.
- **Funkenwerk OS Linux (`FunkenwerkOSLinux --self-test` auf `192.168.188.151`):** `40/40 Selbsttests` bestanden (inkl. Test `#40` Watchtower & NAS-Heartbeat).
- **Android Unit-Tests ([`ShopWatchtowerManagerTest.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/test/java/de/meinseelenfunke/app/util/ShopWatchtowerManagerTest.kt) + Gesamt-Suite):** `53/53 JVM-Tests` (`./gradlew testDebugUnitTest`) bestanden.
- **Synology NAS Watchtower ([`funkenwerk_watchtower.sh`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/scripts/nas-watchtower/funkenwerk_watchtower.sh)):** `--self-test` (`WATCHTOWER_SCRIPT_SYNTAX_OK`) bestanden und auf `\\nas\Backups\Unterlagen\Funkenwerk_Watchtower` ausgerollt.
- **Read-Only Live-Domain-Monitoring & Falcone Voice-Vollmacht ([`LiveServerHealthPulseTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/LiveServerHealthPulseTest.php)):** `SystemHealthPulseService::probeLiveDomains()` prüft `https://mein-seelenfunke.de` und `https://www.mein-seelenfunke.de` strikt read-only (HTTP-Status, Wartungsmodus-Erkennung, Antwortzeit, SSL-Resttage). Sowohl `system_get_server_status` als auch `system_get_health` sind in `AIController::liveCredentials()` (`$voiceAllowedTools`), `AIFunctionsRegistry` (`FunkiraSkillPoolService::isExecutiveRoot`), `AiAuthHelper` (Sanctum-Bearer-Token ohne Session-Cookie) und `AiSystemFuncs` freigeschaltet.
- **Priorisierte Multi-Subdomain-Überwachung (`ag_20261116_212125_559acb18`):**
  - `SystemHealthPulseService` überwacht parallel via `Http::pool()` (`45s` Cache-TTL, sofortiger Bypass bei `force_refresh = true`) alle Seelenfunke-Domains und Subdomains: `mein-seelenfunke.de`, `stage.mein-seelenfunke.de`, `ws.mein-seelenfunke.de` (`/twilio-health`), `api-live-bridge.mein-seelenfunke.de` (`/health`) sowie `www.mein-seelenfunke.de`.
  - Ein vorgeschalteter DNS-Vorabcheck (`resolveHostDnsIps`, `< 5 ms` bei NXDOMAIN) verhindert jeglichen Socket-Timeout für vorbereitete Subdomains ohne DNS-Aufschaltung (`badge_label = "Vorbereitet · DNS offen"`).
  - `AIController` (`liveCredentials` & `chat`) injiziert die `SUBDOMAIN- & SERVER-STATUS-DIREKTIVE`, sodass Falcone und Funkira bei Fragen zu allen Domains und Subdomains sofort ohne Rückfragen alle Fakten nennen.
  - Im `/admin/dashboard` ([`MasterAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Master/MasterAnalytics.php) & [`master_scores.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/master/master-analytics-partials/master_scores.blade.php)) zeigt die Watchtower-Kachel die Clean-Design-Sektion **Domains & Subdomains** mit kurzen Status-Pills (`Live`, `Wartung 503`, `Vorbereitet · DNS offen`, `Weiterleitung 301`, `Störung`).
