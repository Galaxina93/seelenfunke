# Abschlussbericht & Standard-Betriebsablauf: Reinigung Verzeichnisse

**Status:** Aktiv / Standard-Operating-Procedure (SOP)  
**Projekt:** Mein-Seelenfunke (`/home/ubuntuxina/meine-projekte/seelenfunke`)  
**Erstelldatum:** 22.10.2026  
**Trigger-Kommando:** `Abschlussbericht "Reinigung Verzeichnisse" durchführen`

---

## 1. Zweck und Zielsetzung
Im laufenden Entwicklungs- und Produktivbetrieb sammeln sich im Projekt-Stammverzeichnis sowie in Unterverzeichnissen wie `storage/logs` temporäre Hilfsskripte, Ad-hoc-Dumps, alte Logdateien und Test-Artefakte an. 

Dieser Abschlussbericht dient als feste Arbeitsanweisung (SOP). Sobald der Befehl **Abschlussbericht "Reinigung Verzeichnisse" durchführen** erteilt wird, führt der KI-Assistent die folgenden Prüf- und Reinigungsschritte automatisiert und sicher aus.

---

## 2. Kriterien für zu bereinigende Dateien

### A. Projekt-Hauptverzeichnis (`seelenfunke/`)
Folgende Dateitypen und Muster sind **Wegwerf-Dateien** und werden bereinigt:
1. **Temporäre PHP-Einmalskripte:**
   * Skripte zur schnellen Datenmanipulation oder Schema-Änderung (z. B. `add_col.php`, `get_routines.php`, `test_*.php`).
   * Konvertierungs- und Regenerierungsskripte (z. B. `regen_au_index.php`, `regen_court.php`).
2. **Temporäre Dokumente & Dumps:**
   * Arbeitsdateien wie `*_temp.md`, `*_temp.pdf`.
   * Veraltete, nicht versionierte SQL-Dumps oder temporäre DB-Files (nach Prüfung).
3. **Root-Logfiles:**
   * Alle `*.log`-Dateien im Projekt-Root (z. B. `bridge.log`, `audio-debug.log`, `crash.log`, `node-live.log`).
4. **Test-Caches:**
   * `.phpunit.result.cache`, `.phpunit.cache`.

### B. Laravel Log-Verzeichnis (`storage/logs/`)
1. **Temporäre Befehls-Logs:**
   * Alle Dateien nach dem Muster `cmd_*.log`.
2. **Fehler- und API-Dumps:**
   * `admin_test_error.txt`, `gemini_payload_dump.json`, temporäre JSON-/TXT-Payload-Dumps.
3. **Haupt-Logfile (`laravel.log`):**
   * Die Datei `laravel.log` wird **nicht gelöscht**, sondern auf **0 Bytes geleert** (`truncate`), damit Dateiberechtigungen für den Webserver (`www-data`) erhalten bleiben und nachfolgende Logs frisch und übersichtlich starten.

---

## 3. Geschützte Dateien (Whitelist – NIEMALS LÖSCHEN!)
Folgende Dateien und Verzeichnisse sind essenzieller Bestandteil des Projekts und dürfen bei einer Bereinigung **unter keinen Umständen** angetastet werden:
* **Kern & Konfiguration:** `artisan`, `composer.json`, `composer.lock`, `composer.phar`, `package.json`, `package-lock.json`, `.env`, `.gitignore`, `.gitattributes`, `.editorconfig`, `phpunit.xml`.
* **Build & Assets:** `vite.config.js`, `webpack.mix.js`, `tailwind.config.js`, `postcss.config.js`.
* **Dokumentation & Anleitungen:** `README.md`, `GEMINI.md`, `ai_map.md`.
* **Infrastruktur & Daemon-Tools:** `docker-compose.yml`, `dockerfile`, `funki`, `server-twilio.js`.
* **Kernverzeichnisse:** `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `vendor/`, `node_modules/`, `Abschlussberichte/`, `android-app/`, `scripts/`.

---

## 4. Standard-Ausführungsbefehl

Bei Aufruf des Befehls führt die KI folgende Schritte im WSL-Terminal aus:

```bash
cd /home/ubuntuxina/meine-projekte/seelenfunke

# 1. Temporäre Root-Dateien und Wegwerf-Skripte entfernen
rm -f add_col.php get_routines.php regen_*.php test_*.php
rm -f *_temp.md *_temp.pdf
rm -f *.log .phpunit.result.cache

# 2. storage/logs bereinigen
rm -f storage/logs/cmd_*.log
rm -f storage/logs/admin_test_error.txt
rm -f storage/logs/gemini_payload_dump.json
[ -f storage/logs/laravel.log ] && > storage/logs/laravel.log

# 3. Statusprüfung zur Bestätigung
echo "--- Root-Status bereinigt ---"
git status --short
```

---

## 5. Protokoll der Erstreinigung (22.10.2026)
* **Entfernte Skripte im Root:** `add_col.php`, `get_routines.php`, `regen_au_index.php`, `regen_court.php`
* **Entfernte Temp-Dateien im Root:** `au_temp.md`, `au_temp.pdf`, `court_temp.md`, `court_temp.pdf`
* **Entfernte Logs im Root:** `bridge.log` (2,7 MB), `audio-debug.log`, `crash.log`, `node-live.log`, `.phpunit.result.cache`
* **Entfernte Dateien in `storage/logs`:** 13x `cmd_*.log`, `admin_test_error.txt`, `gemini_payload_dump.json`
* **`laravel.log` Status:** Erfolgreich auf 0 Bytes zurückgesetzt.
