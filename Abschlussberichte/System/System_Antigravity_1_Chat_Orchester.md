# Antigravity 1-Chat-Orchester (`seelenfunke-orchestrator`)

## 1. Zweck & Leitstand-Architektur
Das globale Antigravity-Plugin **`seelenfunke-orchestrator`** (`C:\Users\konta\.gemini\config\plugins\seelenfunke-orchestrator\`) bündelt die gesamte Entwicklungs-, Prüf- und Verwaltungsarbeit für **Mein-Seelenfunke** und **Funkenwerk** in **einem einzigen zentralen Haupt-Chat (Dirigent / Leitstand)** statt in dutzenden getrennten Einzel-Chats.

### Kernprinzipien des 1-Chat-Orchesters
1. **Ein einziger Leitstand statt Chat-Zersplitterung:** Alle Anfragen zu Shop, Laser-Manufaktur, Windows-Suite, Werkstatt Mini-PC, Synology NAS, Android-App, Buchhaltung und Dokumentation laufen im selben Haupt-Chat zusammen.
2. **Triage & Baustein-3-Gate im Haupt-Chat:**
   * **Direkt im Leitstand (ohne Subagent):** Strategische Fragen, Mitgründer-Entscheidungen, Konzepte vor einem expliziten **„Go“** (Baustein 3) sowie winzige, klar eingegrenzte Direkt-Edits an 1 bis 2 bekannten Dateien.
   * **Zwingende Delegation (`invoke_subagent`):** Code-Recherchen über mehr als 4 Dateien, neue Features, Refactorings, Compiler-Builds (`dotnet`, `gradlew`), umfangreiche Test-Suiten (`php artisan test`, `--self-test`) sowie abteilungsübergreifende Vorhaben.
3. **Isolierte 100-%-Kontextfenster pro Hintergrund-Subagent:** Jeder gestartete Spezialist arbeitet in einem eigenen, frischen Kontextfenster. Große Datei-Reads, Stacktraces, Build-Ausgaben und Testläufe belasten niemals das Kontextfenster des Haupt-Chats.
4. **Kompaktes 12-Punkte-Rückmeldebudget:** Jeder Hintergrund-Subagent arbeitet seine Pflicht-Tests eigenständig bis **100 % Grün** ab und sendet dem Dirigenten ausschließlich eine komprimierte Ergebnis-Meldung von **maximal 12 Stichpunkten** (geänderte Pfade als sichere Links, Prüfstatus und exakte Testzahlen) zurück. Dadurch bleibt der Haupt-Chat über Wochen schnell, präzise und kontextsparend.
5. **Echte Parallelität & strikter Dateikonflikt-Schutz:**
   * Betreffen Teilaufgaben getrennte Verzeichnisse (z. B. `app/` im Laravel-Shop und `android-app/` auf Android), starten die zuständigen Spezialisten **parallel** im selben Schritt (`Model: "inherit"`, `Workspace: "inherit"`).
   * Müssen zwei Teilaufgaben **dieselbe Datei** bearbeiten, werden niemals zwei schreibende Subagenten gleichzeitig darauf angesetzt, sondern streng seriell nacheinander ausgeführt.

```mermaid
flowchart TD
    U["Alina Steinhauer (1-Chat-Leitstand)"] --> D["Dirigent im Haupt-Chat (rules/AGENTS.md + sf-orchestrate)"]
    D -->|"Konzept / Beratung / 1-2 Dateien"| U
    D -->|"invoke_subagent (isoliertes Kontextfenster)"| S1["sf-shop-laravel (Portal, Konfigurator, Laser, PHPUnit)"]
    D -->|"invoke_subagent (isoliertes Kontextfenster)"| S2["sf-funkenwerk-systems (Windows Suite, Mini-PC, NAS)"]
    D -->|"invoke_subagent (isoliertes Kontextfenster)"| S3["sf-android-mobile (Android-App, Mirror-Sync, Gradle)"]
    D -->|"invoke_subagent (isoliertes Kontextfenster)"| S4["sf-buchhaltung-einkauf (Belege, AccountingSeeder, Kalkulation)"]
    D -->|"invoke_subagent (isoliertes Kontextfenster)"| S5["sf-qa-berichte (10-Bausteine-Audit, Pfadschutz, Berichte)"]
    S1 -->|"Max. 12 Punkte + 100 % Grün"| D
    S2 -->|"Max. 12 Punkte + 100 % Grün"| D
    S3 -->|"Max. 12 Punkte + 100 % Grün"| D
    S4 -->|"Max. 12 Punkte + 100 % Grün"| D
    S5 -->|"Max. 12 Punkte + Freigabe"| D
```

---

## 2. Plugin-Komponenten & Datei-Übersicht
Installationspfad: `C:\Users\konta\.gemini\config\plugins\seelenfunke-orchestrator\`

### Manifest, Regelwerk & Orchestrierungs-Skill
* [`plugin.json`](file:///C:/Users/konta/.gemini/config/plugins/seelenfunke-orchestrator/plugin.json): Offizielles Antigravity-Plugin-Manifest (`v1.0.0`, Autor: `Mein-Seelenfunke (Alina Steinhauer)`), inklusive 3 Schnellstart-Prompts und Schwarz-Gold-Sternlogo ([`assets/logo.svg`](file:///C:/Users/konta/.gemini/config/plugins/seelenfunke-orchestrator/assets/logo.svg)) sowie Kurzübersicht in [`README.md`](file:///C:/Users/konta/.gemini/config/plugins/seelenfunke-orchestrator/README.md).
* [`rules/AGENTS.md`](file:///C:/Users/konta/.gemini/config/plugins/seelenfunke-orchestrator/rules/AGENTS.md): Dauerhaft aktive Dirigenten-Regel im Haupt-Chat (reines Markdown ohne YAML-Frontmatter). Steuert Delegations-Schwellen, Spezialisten-Auswahl, Parallelität, Dateikonflikt-Schutz und das 12-Punkte-Rückmeldebudget.
* [`skills/sf-orchestrate/SKILL.md`](file:///C:/Users/konta/.gemini/config/plugins/seelenfunke-orchestrator/skills/sf-orchestrate/SKILL.md): 3-Phasen-Koordinations-Leitfaden für komplexe Mehr-Abteilungs-Projekte:
  1. **Phase 1 (Triage & Baustein-3-Gate):** Konzept + Mehrwert vor Umsetzung neuer Features; Zerlegung nach Dateigrenzen nach „Go“.
  2. **Phase 2 (Konfliktfreie Delegation):** Übergabe von Zielpfaden (Windows-UNC), Schnittstellen-Vertrag (z. B. JSON-API-Schema zwischen Shop und Android), Pflicht-Tests bis 100 % Grün und 12-Zeilen-Rückmeldelimit.
  3. **Phase 3 (Abschluss-Abnahme & Dokumentation):** Prüfung von Teststatus, `php artisan ai:generate-map`, Verzeichnis-Sauberkeit, Pfad-/Link-Sicherheit und [`Abschlussberichte/README.md`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/README.md).

### Die 5 spezialisierten Hintergrund-Subagenten (`agents/*.md`)
Alle 5 Agenten sind mit `subagent: true`, `mainAgent: false` und einer expliziten `tools:`-Whitelist definiert:

| Spezialist | Datei | Zuständigkeitsbereich & Pflicht-Prüfungen |
| :--- | :--- | :--- |
| **`sf-shop-laravel`** | [`sf-shop-laravel.md`](file:///C:/Users/konta/.gemini/config/plugins/seelenfunke-orchestrator/agents/sf-shop-laravel.md) | Laravel-Hauptportal (`Mein-Seelenfunke`), Livewire 3 (`#[Locked]`, Rollen-Isolation), Blade Clean-Design, 2D/3D-Konfigurator, `Product*Seeder.php`, xTool Laser-Cockpit, REST-API, PHPUnit in `mein_php_server` & `php artisan ai:generate-map`. |
| **`sf-funkenwerk-systems`** | [`sf-funkenwerk-systems.md`](file:///C:/Users/konta/.gemini/config/plugins/seelenfunke-orchestrator/agents/sf-funkenwerk-systems.md) | Funkenwerk Windows Desktop Suite (`funkenwerk*`, C#/WPF/.NET), Werkstatt Mini-PC (`funkenwerk-os-linux`, SSH `alina@192.168.188.151`), Funkira 3D-Atelier, Python-Brücken, MCP-Tools, `--self-test`-Suiten & Synology NAS (`\\nas\...`). |
| **`sf-android-mobile`** | [`sf-android-mobile.md`](file:///C:/Users/konta/.gemini/config/plugins/seelenfunke-orchestrator/agents/sf-android-mobile.md) | Funkenwerk Android-App & Homescreen-Widgets (`Kotlin` / `Jetpack Compose`), **zwingender Datei-Sync** von `android-app/` in den Windows-Mirror `C:\Users\konta\AndroidStudioProjects\seelenfunke-android` & `./gradlew testDebugUnitTest`. |
| **`sf-buchhaltung-einkauf`** | [`sf-buchhaltung-einkauf.md`](file:///C:/Users/konta/.gemini/config/plugins/seelenfunke-orchestrator/agents/sf-buchhaltung-einkauf.md) | Belege (`storage/app/buchhaltung/receipts/`), Verträge (`contracts/`), direkte Pflege in [`AccountingSeeder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/AccountingSeeder.php) (Cent-Integer, exaktes Kaufdatum, Gewerblich vs. Privat, Dublettenprüfung) sowie wirtschaftlich durchgerechnete Einkaufs-/Verpackungsempfehlungen. |
| **`sf-qa-berichte`** | [`sf-qa-berichte.md`](file:///C:/Users/konta/.gemini/config/plugins/seelenfunke-orchestrator/agents/sf-qa-berichte.md) | Unabhängiger Qualitäts-, Security- und Regel-Auditor (**striktes Änderungsverbot** an Produktionscode). Prüft alle 10 Seelenfunke-Bausteine, Black-Screen-Pfadschutz (`file://wsl.localhost/...` / `file:///C:/...`), Clean-Design, `0` KI-Gedankenstriche und pflegt `Abschlussberichte/`. |

---

## 3. Alltags-Steuerung im 1-Chat-Leitstand
Im täglichen Betrieb wird nur noch **ein einziger Haupt-Chat** geöffnet und über folgende Werkzeuge gesteuert:

* **`/compact` (Kontext-Komprimierung statt neuem Chat):** Sobald der Haupt-Chat nach vielen Tagen oder Wochen umfangreich geworden ist, fasst `/compact` den bisherigen Verlauf verlustarm zusammen und gibt das Kontextfenster sofort wieder frei.
* **`/teamwork` (Parallele Abteilungs-Orchestrierung):** Ideal für größere Vorhaben über mehrere Welten hinweg (z. B. gleichzeitige API-Erweiterung im Shop, Anpassung in der Android-App und Audit durch QA).
* **`/plan` (Strukturierte Vorab-Planung):** Erstellt bei komplexen Umbauten vor der Ausführung einen klaren Schritt-für-Schritt-Plan zur Freigabe.
* **`@`-Mention für Alt-Chats:** Über `@` im Eingabefeld kann bei Bedarf Wissen oder Kontext aus einer älteren Einzel-Konversation gezielt einmalig in den zentralen Leitstand übernommen werden.
* **Plugin-Verwaltung in Antigravity:** Status, enthaltene Agenten, Regeln und Skills des Plugins lassen sich jederzeit direkt über die interne Verwaltungs-URI `customization://seelenfunke-orchestrator` im Antigravity-Customizations-Panel einsehen und verwalten.

---

## 4. 24/7 Smartphone-Direktbrücke („Direktnachricht an AG“)
Damit Ideen, Bugs und Aufträge von unterwegs ohne Zwischenschritt über externe Notiz-Apps direkt im zentralen Antigravity-Master-Chat landen (selbst wenn das Windows-Notebook unterwegs ausgeschaltet ist), verbindet eine ausfallsichere 2-Stufen-Brücke die **Funkenwerk Android-App**, **Mittwald Stage** und **Antigravity**:

1. **Android-App Mic-Auswahl & Eingabe (`v1.7.3+`):**
   * Das Mikrofon-Icon oben rechts auf der Startseite ([`ZentrumScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/ZentrumScreen.kt)) öffnet ein cleanes Auswahl-Sheet ([`AgDirectBottomSheets.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/components/AgDirectBottomSheets.kt)):
     1. **`Mit Agenten sprechen`** -> Öffnet unverändert den KI-Livecall mit Funkira.
     2. **`Direktnachricht an AG`** -> Öffnet das Direktfenster mit Sprache-zu-Text (`de-DE`), Checklisten-Unterpunkten (`+ Punkt`), optionalem Screenshot-Anhang inkl. In-App-Lightbox, Offline-Outbox ([`AgDirectInboxManager.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/AgDirectInboxManager.kt)), Modus-Wahl (`Erst Konzept` vs. `Direkt umsetzen`) und Live-Status-Verlauf (`Wartet` -> `Gesendet` -> `Im AG-Chat` -> `Erledigt ✓`).
2. **24/7 Stage-Zwischenpostfach (`/api/system/ag-direct-inbox`):**
   * [`AntigravityDirectInboxController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/AntigravityDirectInboxController.php), [`AntigravityDirectInboxService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/AntigravityDirectInboxService.php) und [`AntigravityDirectInboxCommand.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/System/AntigravityDirectInboxCommand.php) (`php artisan system:ag-inbox`) speichern Aufträge und validierte Screenshots strikt privat unter `storage/app/private/` (Admin-Only Sanctum-Schutz, `client_uuid`-Idempotenz, 30-Tage-Rotation).
3. **Verlustfreie 2-Phasen-Zustellung in den Master-Chat (`FunkenwerkMcp` + Antigravity Sidecar):**
   * Der offizielle Antigravity-Hintergrunddienst **Seelenfunke AG-Direkt-Brücke** ([`sidecar.json`](file:///C:/Users/konta/.gemini/config/sidecars/seelenfunke-ag-direct-bridge/sidecar.json)) führt [`FunkenwerkMcp.exe`](file:///C:/Users/konta/AppData/Local/Funkenwerk/SentinelCli/FunkenwerkMcp.exe) im Modus `--ag-sidecar-daemon` aus (`0` KI-Token Verbrauch im Leerlauf).
   * Alle 20 Sekunden prüft der Dienst per Ed25519-SSH das Stage-Postfach (`--peek-pending`), legt eventuelle Screenshots lokal unter `C:\Users\konta\AppData\Local\Funkenwerk\AgDirectMedia\` ab und injiziert neue Nachrichten über `agentapi send-message` direkt in den aktiven Master-Chat ([`ag_direct_bridge_config.json`](file:///C:/Users/konta/AppData/Local/Funkenwerk/ag_direct_bridge_config.json)). Erst nach `ExitCode == 0` wird der Eintrag per `--mark-delivered` bestätigt; nach Abschluss setzt `--ag-inbox-complete` den Status für die Android-App auf `Erledigt ✓`.
