# Zentrales Sicherheitskonzept & Automatischer Paket-Watchdog (Mein-Seelenfunke)

**Stand:** 12.11.2026  
**Abteilung:** System & Architektur  
**Kommandozentrale:** `/admin/dashboard` (Reiter `Operativer Status` & `Sicherheit & Abwehr`)  
**CLI-Steuerung:** `./funki security` & `./funki security update`  
**Cronjob:** `system:security-audit` (Täglich um `04:30` Uhr, `30 4 * * *`)

---

## 1. Leitbild & Zielsetzung (Zero-Trust-Architektur)

Die Plattform **Mein-Seelenfunke** (Web-Plattform, 2D/3D-Konfigurator, KI-Agenten-Ökosystem, Funkenwerk Windows Suite und Android App) folgt dem Prinzip **Defense-in-Depth (7 Verteidigungsringe)** kombiniert mit einem **automatisierten Paket- & CVE-Watchdog**.

Kein Paket-Update (Composer, NPM, NuGet, Gradle) wird blind in Produktion eingespielt, und keine bekannte Sicherheitslücke (CVE) bleibt länger als 24 Stunden unentdeckt.

---

## 2. Die 4 überwachten Paket- & System-Säulen (`system:security-audit`)

Der tägliche Watchdog-Prozess (`App\Console\Commands\SystemSecurityAuditCommand`) prüft vollautomatisch vier Ebenen und speichert das Ergebnis revisionssicher unter `storage/app/system/security_audit_report.json`:

### Säule 1: Laravel & PHP-Pakete (`composer.json` / `composer.lock`)
* **CVE-Prüfung:** Führt `composer audit --no-dev --format=json` (mit Fallback auf die offizielle Packagist Security Advisories API `https://packagist.org/api/security-advisories/`) gegen alle 135+ gesperrten Pakete in `composer.lock` aus.
* **Update-Radar:** Prüft via `composer outdated --direct --format=json`, ob für direkte Abhängigkeiten (`laravel/framework`, `livewire/livewire`, `stripe/stripe-php`, `dompdf/dompdf`, `guzzlehttp/guzzle`, `spatie/laravel-backup` etc.) sichere Minor-/Patch-Updates (`semver-safe-update`) vorliegen.

### Säule 2: Frontend & Node/NPM-Pakete (`package.json` / `package-lock.json`)
* **CVE-Prüfung:** Führt `npm audit --omit=dev --json` über sämtliche Produktions-Abhängigkeiten (`three`, `alpinejs`, `tailwindcss`, `playwright`, `sharp`, `ws`, `vite`) aus.
* **Transitive Härtung:** Über das `overrides`-Feld in `package.json` werden tief verschachtelte Sub-Abhängigkeiten (`postcss-selector-parser`, `browserslist`, `baseline-browser-mapping`, `@babel/core`, `immutable`) auf gepatchte Mindestversionen gezwungen.

### Säule 3: Funkenwerk Desktop (.NET 9) & Android App (Kotlin / Compose)
* **Funkenwerk Windows Desktop (`funkenwerk/Funkenwerk.csproj`):**
  * Prüft Target-Framework (`net9.0-windows`), Release-Version (`2.1.0`), `Nullable`-Sicherheit und gepinnte NuGet-Referenzen.
* **Funkenwerk Android Suite (`android-app/app/build.gradle.kts` & `gradle/libs.versions.toml`):**
  * Prüft `compileSdk` (`34`), `targetSdk` (`34`), `minSdk` (`26`), aktives Release-ProGuard/R8-Shrinking (`isMinifyEnabled = true`) sowie die zentral in `libs.versions.toml` gepinnten Bibliotheken (Kotlin, Jetpack Compose BOM, Retrofit, OkHttp, AndroidX Biometric, Firebase BOM, Google GenAI).

### Säule 4: Zentrale System-Schranken (6 Invarianten)
1. **`.env` & `APP_KEY` Schutz:** Stellt sicher, dass keine `.env`-Datei im öffentlichen `public/`-Verzeichnis liegt und `APP_KEY` gesetzt ist.
2. **Produktions-Debug-Sperre:** Erzwingt `APP_DEBUG=false` in der `production`-Umgebung.
3. **Public-Verzeichnis-Integrität:** Prüft, dass außer `index.php` keinerlei ausführbare `.php`-, `.sql`-, `.bak`-, `.env`- oder `.sh`-Dateien unter `public/` existieren.
4. **Session- & CSRF-Cookie-Härtung:** Verifiziert `HttpOnly=true` und `SameSite=lax/strict`.
5. **Architektur- & DSGVO-Blueprint (`SystemBlueprintService`):** Prüft, dass alle Eloquent-Modelle eine explizite `RetentionPolicy` besitzen und keine Dateien außerhalb der erlaubten Abteilungsordner unter `storage/app/` liegen.
6. **Administrator 2FA-Bereitschaft:** Überwacht den 2FA-Status (`two_factor_is_active`) aller Admin-Profile.

---

## 3. Die 7 Verteidigungsringe im laufenden Betrieb

1. **Ring 1 (Netzwerk, IP-Firewall & Bot-Abwehr):**
   * Automatischer 24h-IP-Ban (`SystemBlockedIp`) ab 10 fehlgeschlagenen Login- oder 2FA-Versuchen innerhalb von 15 Minuten.
   * Honeypot-Felder und striktes Rate-Limiting an Login, Checkout, Kontaktformular, Widerruf und Passwort-Reset.
2. **Ring 2 (Identität, Guards & Rollen-Isolation):**
   * Strikte Trennung der Guards (`admin`, `employee`, `customer`).
   * Vollständige Isolation der Rollen `influencer` und `tax_advisor` über `EnsureEmployeeRole`, `EmployeeRoleIsolationHook` und `AiAuthHelper`.
3. **Ring 3 (Livewire 3 Manipulation & IDOR-Schutz):**
   * Kritische Livewire-Properties (IDs, Preise, Rollen, Filter) sind serverseitig mit `#[Locked]` gegen Client-Payloads gesperrt.
   * Jede mutierende Livewire-Action prüft explizit Guard und Eigentümerschaft (IDOR-Schutz).
4. **Ring 4 (Zahlungs-, Angebots- & Widerrufs-Integrität):**
   * Server-autoritative Preisberechnung in Cent-Integern; Stripe-Webhooks werden kryptographisch über `Stripe-Signature` verifiziert.
   * 1-Klick-Stripe-Rückerstattungen im Widerruf (`/admin/widerruf`) sind durch Admin-Guard, `#[Locked]` und Status-Sperren gegen Doppel-Erstattungen gesichert.
5. **Ring 5 (Datei-Uploads, 2D/3D-Konfigurator & LFI/SSRF-Schutz):**
   * Kunden-Uploads und Widerrufs-Anhänge prüfen reale MIME-Bytes (`finfo_buffer`) statt Dateiendungen; SVGs werden über `SvgSanitizerService` von Skripten und externen Referenzen bereinigt.
   * Pfad-Parameter werden mit `basename()` und `realpath()` strikt innerhalb erlaubter Storage-Wurzeln gehalten.
6. **Ring 6 (KI-Agenten-Sandbox & Funkenwerk API):**
   * Öffentliche KI-Support-Chats können ausschließlich freigegebene Read-Only-Kundentools aufrufen; interne Admin-Tools werden durch das zentrale Gate in `AIController` und `AiSupportService` blockiert.
   * Kiosk-/Monitor-Tokens (`monitor:pulse`) sind strikt von Vollzugriffs-Tokens (`*`) getrennt.
7. **Ring 7 (DSGVO, GoBD & Automatische Daten-Hygiene):**
   * Klarnamen werden nur bei aktivem DSGVO-Opt-in an externe KI-Schnittstellen übergeben (sonst anonymisiert).
   * Nächtliche Bereinigung verwaister Uploads (`shop:cleanup-orphaned-uploads`) und unverifizierter Accounts (`system:delete-unverified-users`).

---

## 4. Operativer Workflow: Überwachung & Sicheres Update

### A. Tägliche Überwachung im Dashboard (`/admin/dashboard`)
* **Grün (`0 CVEs`):** Im `Operativen Status` steht `Paket- & CVE-Prüfung` auf Grün (`Erledigt`).
* **Rot (`CVE erkannt`):** Sobald Composer oder NPM eine neue Sicherheitslücke melden, springt die Kachel im `Operativen Status` automatisch nach ganz oben auf **Rot** und Funkira sendet eine E-Mail-Warnung an `kontakt@mein-seelenfunke.de`.
* **Manueller Live-Scan:** Unter `/admin/dashboard` -> `Sicherheit & Abwehr` -> `Automatische Paket- & Sicherheitsprüfung` kann jederzeit per Klick auf **„Jetzt prüfen“** ein sofortiger Live-Scan ausgelöst werden.

### B. Sicheres Einspielen von Updates via CLI (`./funki security`)
1. **Nur prüfen (ohne Änderungen):**
   ```bash
   ./funki security
   ```
2. **Sicheres Komplett-Update (Composer + NPM + Audit + Test-Suite):**
   ```bash
   ./funki security update
   ```
   Dieser Befehl führt nacheinander aus:
   * `composer update --with-all-dependencies`
   * `npm update --legacy-peer-deps` & `npm audit fix --omit=dev`
   * `php artisan system:security-audit --no-alert`
   * Automatischer Sicherheitstest (`./funki test SystemSecurityAuditCommandTest`)
3. **Abschluss-Verifikation vor Deployment:**
   ```bash
   ./funki test
   ```
   Erst wenn alle automatisierten Tests zu 100 % grün durchlaufen, werden `composer.lock` und `package-lock.json` committet und auf Stage/Live ausgerollt.
