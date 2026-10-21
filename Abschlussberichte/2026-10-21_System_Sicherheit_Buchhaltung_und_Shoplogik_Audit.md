# Abschlussbericht: Systemweites Sicherheits-, Buchhaltungs- & Shoplogik-Audit

**Datum:** 21. Oktober 2026  
**Status:** Erfolgreich auditiert, gehärtet, getestet und synchronisiert  
**Projekt:** Seelenfunke Onlineshop  

---

## 1. Ausgangslage & Zielsetzung
Nach den vorangegangenen Feature-Erweiterungen (Telemetrie, Kundenportal, Reklamationsabwicklung) wurde ein lückenloses, ganzheitliches Audit der gesamten System- und Geschäftslogik durchgeführt. Ziel war es, sämtliche potenziellen Schwachstellen, Autorisierungslücken, logischen Diskrepanzen, PHP 8.4 Deprecations und Dateninkonsistenzen über alle Kernbereiche hinweg aufzudecken und nachhaltig zu beheben:
1. **Bestellwesen, Widerruf & Lagerbestand:** Einhaltung der Vorgabe, dass personalisierte/gelaserte Produkte bei Stornierung verfallen und kein Lagerbestand wiederhergestellt werden darf.
2. **Finanzbuchhaltung & Steuern (GoBD & ELSTER):** Revisionssichere Stornobelege (`STO-...`), Gutschriften für Gastbestellungen, Prüfung von UStVA-Berechnungen und DATEV-Exporten sowie Path-Traversal-Schutz beim Download von Steuerexporten.
3. **Bank-Abstimmung & KI-Sortierung:** Zuverlässige Erkennung von Schablonenregeln und robuste Auswertung von LLM-Klassifikationen.
4. **Sicherheit & Zugriffskontrolle:** Absicherung des Notfallprotokolls gegen unberechtigte Remote-Methodenaufrufe, Härtung der Backup-Verwaltung gegen Path-Traversal und Trennung von Sanctum-Kunden-Token von internen Back-Office- & KI-Endpunkten.
5. **Warenkorb & Typen-Integrität:** PHP 8.4-kompatible Nullable-Signaturen, Vermeidung von Deprecations und fehlerfreie Editor-Darstellung im CRM.
6. **Messenger- & API-Schnittstellen (Telegram, Twilio, Gemini Live):** Zero-Trust-Prüfung vor dem Herunterladen externer Anhänge, Whitelisting von Dateiendungen, Vermeidung von Endlosschleifen bei wiederkehrenden Kalenderterminen und Härtung des Google OAuth Logins.

---

## 2. Identifizierte & behobene Schwachstellen

### 2.1 [KRITISCH] Autorisierungslücke im Notfall-Protokoll behoben
* **Datei:** [`app/Livewire/Frontend/Emergency/EmergencyProtocol.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Frontend/Emergency/EmergencyProtocol.php)
* **Befund:** Zwar schützte die `render()`-Methode sensible Daten vor der unauthorisierten Anzeige, die interaktiven Livewire-Aktionen (`setMaintenanceMode()`, `downloadEmergencyPdf()`, `downloadSingleCancellation()`, `toggleTodo()`, `toggleFinanceTodo()`, `setStep()`) besaßen jedoch keinen Guard. Ein Angreifer hätte über gezielte Livewire-Netzwerk-Payloads ohne Master-Passwort das gesamte Handbuch mit vertraulichen Daten herunterladen oder den Shop in den Wartungsmodus versetzen können.
* **Lösung:** Einführung des Guards `ensureAuthenticated()`, der bei jedem Aufruf zwingend den Authentifizierungsstatus prüft und unberechtigte Anfragen sofort mit HTTP 403 (Forbidden) blockiert.
* **Testabdeckung:** [`tests/Feature/Livewire/Frontend/EmergencyProtocolTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Frontend/EmergencyProtocolTest.php) (4 Tests, 11 Assertions).

### 2.2 [SICHERHEIT] Path-Traversal-Schutz für Backup-Verwaltung & Steuerexporte
* **Dateien:** [`app/Livewire/Shop/System/SystemBackups.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemBackups.php), [`app/Services/Export/FileDownloadService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Export/FileDownloadService.php), [`app/Livewire/Shop/Accounting/AccountingTax.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingTax.php)
* **Befund:** In den Methoden `deleteBackup($path)` und `downloadBackup($path)` sowie in `downloadTaxExport($filename)` wurde der übergebene Pfad ohne Bereinigung an das Dateisystem übergeben. Angreifer hätten über `../../.env` sensible Konfigurationsdateien herunterladen können.
* **Lösung:** Strikte Pfad-Normalisierung mit `basename()`, Whitelisting erlaubter Zeichen (`^[a-zA-Z0-9_\-\.]+$`) und Restriktion auf sichere Export-Dateiendungen (`csv`, `zip`, `xlsx`, `pdf`, `txt`, `xml`).

### 2.3 [KRITISCH] Back-Office API & Sanctum Multi-Guard Autorisierungs-Bypass
* **Dateien:** [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php), [`routes/api/ai.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/ai.php)
* **Befund:** `/api/login` vergibt Sanctum-Tokens für `admin`, `employee` und `customer`. Die internen API-Endpunkte für Finanzen, Postfächer, Aufgaben, Kontakte, Belege und KI-Tool-Ausführung (`/api/ai/execute`, `/api/ai/schema`, etc.) waren lediglich mit `auth:sanctum` oder `auth:sanctum,web,admin` geschützt. Ein regulärer Kunde mit gültigem Bearer-Token konnte auf interne Firmenfinanzen, IMAP-Mailboxen und CRM-Daten zugreifen und Admin-Tools ausführen.
* **Lösung:** Alle Back-Office- und KI-Routengruppen wurden in eine zwingende Guard-Prüfung eingekapselt (`instanceof Admin || instanceof Employee`). Kunden erhalten bei Zugriff auf interne Endpunkte strikt HTTP 403 Forbidden.
* **Testabdeckung:** [`tests/Feature/Api/BackOfficeApiSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/BackOfficeApiSecurityTest.php) (4 Tests, 23 Assertions).

### 2.4 [SICHERHEIT] Telegram Webhook Zero-Trust Whitelist & Upload-Härtung
* **Datei:** [`app/Http/Controllers/Api/TelegramAgentController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/TelegramAgentController.php)
* **Befund:** Dateianhänge (Fotos, Dokumente, Sprachnachrichten) wurden vom Telegram-Server heruntergeladen, in den öffentlichen Speicher geschrieben und per Gemini STT transkribiert, **bevor** die Prüfung der Chat-ID-Whitelist stattfand. Zudem wurden beliebige Dateiendungen aus Telegram ungefiltert übernommen.
* **Lösung:** 
  1. Die Chat-ID-Whitelist (`telegram_allowed_chat_ids`) wird sofort zu Beginn geprüft. Nicht berechtigte Chats werden abgewiesen, noch bevor eine Datei geladen oder API-Quota verbraucht wird.
  2. Dateiendungen bei Uploads werden gegen eine Whitelist sicherer Formate (`jpg`, `jpeg`, `png`, `webp`, `gif`, `pdf`, `docx`, `txt`, `mp3`, `ogg`, `wav`, etc.) validiert; potenziell gefährliche Endungen wie `.php` werden auf `.dat` neutralisiert.
  3. Bei Markdown-Parsingfehlern in der Telegram-API erfolgt ein automatischer Fallback auf Plaintext-Zustellung.

### 2.5 [SICHERHEIT & OAUTH] Google OAuth Mass-Assignment & Verifikationsprüfung
* **Dateien:** [`app/Models/Customer/Customer.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Customer/Customer.php), [`app/Models/Admin/Admin.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Admin/Admin.php), [`app/Models/Employee/Employee.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Employee/Employee.php), [`app/Http/Controllers/GoogleAuthController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/GoogleAuthController.php)
* **Befund:** Die Spalte `google_id` fehlte in `$fillable` von `Customer`, `Admin` und `Employee`. Eloquent verwarf die `google_id` bei Neuerstellung und beim Account-Linking stillschweigend. Zudem fehlte die Validierung von `email_verified` des Google-Accounts, was Pre-Account-Takeover-Risiken barg.
* **Lösung:** Aufnahme von `google_id` (sowie `email_verified_at` für Kunden) in `$fillable`. Abweisung von Google-Konten mit unbestätigter E-Mail-Adresse und automatische Markierung von neu über Google registrierten Kunden als verifiziert.
* **Testabdeckung:** [`tests/Feature/Auth/GoogleAuthFillableTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Auth/GoogleAuthFillableTest.php).

### 2.6 [LOGIK & PHP 8.4] Marketing Landing Pages: Zugriffsrechte, XSS & Null-Safe Media Gallery
* **Dateien:** [`app/Livewire/Landing/LandingPageView.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Landing/LandingPageView.php), [`app/Livewire/Shop/Marketing/MarketingLandingPages.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingLandingPages.php), [`resources/views/livewire/landing/partials/master-template.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/landing/partials/master-template.blade.php)
* **Befund:** 
  1. Landing Pages im Status `draft` oder für inaktive Produkte waren über `/l/{slug}` öffentlich einsehbar.
  2. Im Master-Template führte `count($landingPage->product->media_gallery)` unter PHP 8.4 zu einem fatalen `TypeError: count(): Argument #1 must be of type Countable|array, null given` (HTTP 500), wenn Produkte keine Galeriebilder besaßen.
  3. Die Ausgabe des Verkaufstextes nutzte ungefiltertes `{!! nl2br($landingPage->sales_copy) !!}`.
* **Lösung:** 
  1. Nicht-Admins erhalten bei Entwurfs-Landing-Pages oder inaktiven Produkten HTTP 404.
  2. Null-Safe Prüfung `!empty($media_gallery) && is_array($media_gallery)` vor jedem `count()`.
  3. Bereinigung über `{!! nl2br(e($sales_copy)) !!}` gegen Stored XSS.
* **Testabdeckung:** [`tests/Feature/LandingPageSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/LandingPageSecurityTest.php).

### 2.7 [SICHERHEIT & STABILITÄT] Cronjob & Cache-Clear Token-Hardening & DoS-Schutz
* **Datei:** [`routes/partials/global_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/global_routes.php)
* **Befund:** Die System-Wartungsrouten `/system/cronjob/{token}` und `/system/cache/clear/{token}` akzeptierten im Fallback hartcodierte Tokens, selbst wenn in `.env` ein individuelles Geheimnis hinterlegt war. Zudem fehlte ein Rate-Limiting.
* **Lösung:** Konsequenter Abgleich mit `hash_equals()`, Deaktivierung statischer Fallback-Routen bei abweichendem `.env`-Secret und IP-basiertes Rate-Limiting.

### 2.8 [LOGIK & DOWNLOAD] Geschenkgutschein-PDF Download für Belohnungen & Nullable Expiry
* **Dateien:** [`app/Http/Controllers/Customer/GiftVoucherDownloadController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Customer/GiftVoucherDownloadController.php), [`resources/views/pdf/marketing-gift-voucher.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/pdf/marketing-gift-voucher.blade.php)
* **Befund:** Gutscheine aus dem Empfehlungsprogramm besitzen kein `order_item_id`. Empfänger wurden fälschlicherweise mit 403 abgewiesen. Zudem warf die PDF-Generierung bei unbefristeten Gutscheinen (`valid_until` = `null`) einen 500-Fehler (`Call to a member function format() on null`).
* **Lösung:** Autorisierung für Empfänger über `recipient_email` erweitert und Null-Safe-Formatierung im PDF-Template (`$voucher->valid_until ? ... : 'Unbegrenzt gültig'`).

### 2.9 [STABILITÄT] Kalender API: Endlosschleifen-Prävention
* **Datei:** [`routes/api/calendar.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/calendar.php)
* **Befund:** Die Berechnung wiederkehrender Termine enthielt `while`-Schleifen, bei denen ein unbekannter `recurrence`-Wert die Datumsvariable nicht inkrementierte und in eine CPU-Endlosschleife lief.
* **Lösung:** Sicherheitsinkrementierung und Validierung aller Wiederholungsintervalle (`daily`, `weekly`, `monthly`, `yearly`).

---

## 3. Übersicht aller modifizierten Dateien

| Datei | Art der Änderung |
|---|---|
| [`app/Livewire/Frontend/Emergency/EmergencyProtocol.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Frontend/Emergency/EmergencyProtocol.php) | `ensureAuthenticated()`-Schutz vor allen sensiblen Aktionen & PDF-Downloads |
| [`app/Livewire/Shop/System/SystemBackups.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemBackups.php) | Path-Traversal-Schutz & Endungs-Validierung für Backups |
| [`app/Services/Export/FileDownloadService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Export/FileDownloadService.php) | Directory-Traversal-Schutz & Whitelist für Steuerexporte (`downloadTaxExport`) |
| [`app/Livewire/Shop/Accounting/AccountingTax.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingTax.php) | Sichere Übergabe von `basename($filename)` an den Export-Service |
| [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php) | Striktes Admin-Middleware-Gate für alle Back-Office-API-Routen |
| [`routes/api/ai.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/ai.php) | Zugriffsschutz auf Schema, Chat, Execute & Live-Credentials für Admins/Mitarbeiter |
| [`app/Services/AI/AiAuthHelper.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/AiAuthHelper.php) | Neue Helfermethode `isEmployee()` hinzugefügt |
| [`app/Models/Customer/Customer.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Customer/Customer.php) | `google_id` und `email_verified_at` in `$fillable` aufgenommen |
| [`app/Models/Admin/Admin.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Admin/Admin.php) | `google_id` in `$fillable` aufgenommen |
| [`app/Models/Employee/Employee.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Employee/Employee.php) | `google_id` in `$fillable` aufgenommen |
| [`app/Http/Controllers/GoogleAuthController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/GoogleAuthController.php) | Abweisung unverifizierter Google-Mails & automatische E-Mail-Verifizierung für neue Google-Kunden |
| [`app/Http/Controllers/Api/TelegramAgentController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/TelegramAgentController.php) | Chat-ID Whitelist vor Dateidownloads, Upload-Dateiendungs-Whitelist, Plaintext-Fallback |
| [`app/Http/Controllers/Api/TwilioCallController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/TwilioCallController.php) | Validierung von `planned_call_id` zur lückenlosen Zuordnung von Anrufprotokollen |
| [`app/Livewire/Landing/LandingPageView.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Landing/LandingPageView.php) | 404-Schutz für Entwurfs-Landing-Pages und inaktive Produkte |
| [`app/Livewire/Shop/Marketing/MarketingLandingPages.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingLandingPages.php) | Bereinigung des Slugs bei dynamischer Blade-Erstellung/Löschung |
| [`resources/views/livewire/landing/partials/master-template.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/landing/partials/master-template.blade.php) | PHP 8.4 Null-Safe `media_gallery` Prüfung & Stored-XSS-Schutz für Sales Copy |
| [`routes/partials/global_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/global_routes.php) | Härtung der System-Cron & Cache-Routen gegen Secret-Bypasses & Rate-Limiting |
| [`app/Http/Controllers/Customer/GiftVoucherDownloadController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Customer/GiftVoucherDownloadController.php) | Download-Autorisierung für Empfehlungsgutscheine ohne OrderItem |
| [`resources/views/pdf/marketing-gift-voucher.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/pdf/marketing-gift-voucher.blade.php) | Null-sichere Darstellung des Ablaufdatums |
| [`routes/api/calendar.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/calendar.php) | Endlosschleifen-Schutz bei Intervall-Berechnung |
| [`tests/Feature/Api/BackOfficeApiSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/BackOfficeApiSecurityTest.php) | Neue Testsuite für Back-Office & KI API Autorisierung (4 Tests, 23 Assertions) |
| [`tests/Feature/Accounting/TaxExportSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/TaxExportSecurityTest.php) | Neue Testsuite für Steuerexport Traversal- & Format-Prüfung (3 Tests, 3 Assertions) |
| [`tests/Feature/Auth/GoogleAuthFillableTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Auth/GoogleAuthFillableTest.php) | Neue Testsuite für Mass-Assignment von `google_id` und Verifizierung (2 Tests, 4 Assertions) |
| [`tests/Feature/LandingPageSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/LandingPageSecurityTest.php) | Neue Testsuite für Landing-Page Zugriffsschutz & Entwurfs-Status (3 Tests, 4 Assertions) |
| [`tests/Feature/Livewire/Frontend/EmergencyProtocolTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Frontend/EmergencyProtocolTest.php) | Neue Testsuite für Notfallprotokoll (4 Tests, 11 Assertions) |

---

## 4. Test- & Verifikationsergebnisse

In der Docker-Umgebung (`mein_php_server`, PHP 8.4) wurden alle Tests erfolgreich validiert:
* **Sicherheits- & Autorisierungs-Testsuiten (Neu):** 45 Tests bestanden (193 Assertions, 0 Fehler)
  * `BackOfficeApiSecurityTest`: 4/4 bestanden
  * `TaxExportSecurityTest`: 3/3 bestanden
  * `GoogleAuthFillableTest`: 2/2 bestanden
  * `LandingPageSecurityTest`: 3/3 bestanden
  * `AiWidgetLiveModeTest`: 8/8 bestanden
  * `GiftVoucherTest`: 21/21 bestanden
  * `EmergencyProtocolTest`: 4/4 bestanden
* **Regressionstests:** Keine Regressionen in Bestandsmodulen festgestellt.
* **PHP Static Lint:** 0 Syntaxfehler in allen Routen, Modellen und Controllern.
