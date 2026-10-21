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

### 2.10 [KRITISCH & IDOR] Kundenportal: Autorisierung von Bestellungen & Positionen (IDOR-Prävention)
* **Datei:** [`app/Livewire/Customer/CustomerOrdersComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerOrdersComponent.php)
* **Befund:** In den berechneten Eigenschaften `selectedOrder`, `previewItem` und `editItem` sowie in den Methoden `showOrder($id)`, `openPreview($itemId)` und `openEdit($itemId)` wurden Bestellungen und Bestellpositionen direkt per ID aus der Datenbank geladen, ohne zu prüfen, ob die Bestellung dem aktuell authentifizierten Kunden gehört. Ein authentifizierter Kunde konnte durch Manipulation der UUID im Livewire-Payload fremde Kundendaten (vollständige Rechnungs-/Lieferadressen, bestellte Artikel, Rechnungs-PDFs und Laservorschauen) einsehen.
* **Lösung:** Strikte Bindung aller Abfragen an `customer_id === Auth::guard('customer')->id()`. Fremde Bestellungen oder Positionen werden ignoriert bzw. mit HTTP 403 / Reset abgewiesen.

### 2.11 [SICHERHEIT & IDOR] Kundenportal: Support-Tickets Autorisierung & Upload-Filterung
* **Datei:** [`app/Livewire/Customer/CustomerTicketsComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerTicketsComponent.php)
* **Befund:** In `setMode('chat', $ticketId)` und `markAsRead($ticketId)` fehlte die Verifikation der Kundeninhaberschaft des Tickets. Zudem validierte `processUploads()` Anhänge nur bezüglich der Dateigröße, jedoch nicht bezüglich des Dateityps oder der Dateiendung (Risiko des Uploads bösartiger ausführbarer Skripte).
* **Lösung:** Ticketabfragen auf `customer_id` des aktuellen Kunden beschränkt. Dateiendungen in Ticket-Anhängen werden strikt auf erlaubte Typen (`jpg, jpeg, png, webp, gif, pdf, txt, docx`) geprüft.

### 2.12 [SHOPLOGIK] Warenkorb: Schutz vor negativen Mengen und Preis-Manipulationen
* **Datei:** [`app/Services/CartService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartService.php)
* **Befund:** Bei Aufruf von `addItem()` oder `updateItem()` mit negativen Mengenangaben (z. B. `-1`) konnte ein negativer Artikel- oder Rechnungsbetrag erzeugt werden, sofern die Eingabe nicht vom Frontend gefiltert wurde.
* **Lösung:** In `addItem()` wird die Mindestmenge serverseitig auf `max(1, $quantity)` forciert. In `updateItem()` führt eine Menge `<= 0` zum sicheren Entfernen des Artikels aus dem Warenkorb.

### 2.13 [SICHERHEIT] Buchhaltung: Dateityp-Validierung für Beleg- und Vertragsuploads
* **Dateien:** [`app/Livewire/Shop/Accounting/AccountingFixCosts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingFixCosts.php), [`app/Livewire/Shop/Accounting/AccountingQuickEntry.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingQuickEntry.php), [`app/Livewire/Shop/Accounting/AccountingVariableCosts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingVariableCosts.php), [`app/Livewire/Shop/Accounting/AccountingBank.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingBank.php)
* **Befund:** In den Livewire-Komponenten der Buchhaltung wurden Datei-Uploads (Verträge, Rechnungsbelege, QuickEntry-Belege) mit `file|max:10240` validiert, ohne MIME-Typen oder Dateiendungen einzuschränken. Potentiell gefährliche Dateien (`.php`, `.sh`, `.exe`, `.bat`, `.html`) hätten in den Belegspeicher hochgeladen werden können.
* **Lösung:** Erweiterung der Validierungsregeln auf sichere Dokumenten- und Bildformate: `mimes:pdf,jpg,jpeg,png,webp,zip,docx,txt,xml`.

### 2.14 [KRITISCH] Newsletter Automation & Admin: Fataler Klassenfehler behoben
* **Dateien:** [`app/Console/Commands/SendNewsletter.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/SendNewsletter.php), [`app/Livewire/Shop/Marketing/MarketingNewsletter.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingNewsletter.php)
* **Befund:** Im Artisan-Kommando `send-newsletters` und in der Newsletter-Verwaltungskomponente wurde `use App\Mail\AutomaticNewsletterMail;` importiert und instanziiert. Diese Klasse existierte physisch nicht (korrekter Klassenname ist `App\Mail\NewsletterAutomaticMail`). Der geplante Kampagnenversand via Cronjob stürzte mit einem fatalen Fehler (`Class not found`) ab.
* **Lösung:** Korrektur des Imports und der Instanziierung auf `App\Mail\NewsletterAutomaticMail`.

### 2.15 [DATENSCHUTZ & ENUMERATION] Newsletter-Abmeldung & Double-Opt-In
* **Dateien:** [`app/Livewire/Shop/Marketing/MarketingNewsletterPage.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingNewsletterPage.php), [`app/Livewire/Shop/Marketing/MarketingNewsletterSignup.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingNewsletterSignup.php)
* **Befund:** 
  1. Die Abmeldefunktion gab bei unbekannten Adressen eine Fehlermeldung zurück, wodurch Angreifer die E-Mail-Adressen aller registrierten Abonnenten enumerieren konnten (User Enumeration).
  2. Bei Neuanmeldung blockierte `unique:marketing_newsletter_subscribers,email` bereits registrierte, aber noch unbestätigte Abonnenten (`is_verified = false`) dauerhaft, anstatt ihnen einen neuen Bestätigungslink zu senden.
  3. `MarketingNewsletterSignup` referenzierte in den Validierungsregeln eine veraltete Tabellenbezeichnung (`newsletter_subscribers`).
* **Lösung:** Einheitliche, neutrale Erfolgsmeldung bei der Abmeldung zum Schutz vor Enumeration. Automatisches Re-Tokening und erneuter Versand des Double-Opt-In-Links für unbestätigte Adressen. Korrektur der Validierungsregeln.

### 2.16 [KRITISCH - ARBITRARY FILE DELETION] Produktbewertungen: Bereinigung & Schutz des öffentlichen Speichers
* **Dateien:** [`app/Livewire/Shop/Product/ProductReviews.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductReviews.php), [`app/Livewire/Shop/Product/ProductControlReviews.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductControlReviews.php)
* **Befund:** In `submitReview()` und `deleteReview()` übernahm die Komponente Client-seitig übergebene Pfade in `$existingMedia` ungeprüft in die Model-Eigenschaft `media`. Beim Löschen der Bewertung (`deleteReview`) rief die Komponente `Storage::disk('public')->delete($media)` ohne Pfadprüfung auf. Ein Angreifer konnte über die Livewire-Payload beliebige fremde Dateien auf dem Public Storage (z. B. Produktbilder, Avatare, Logos) referenzieren und über die Löschfunktion gezielt vernichten (Arbitrary File Deletion).
* **Lösung:**
  - Neue Bewertungen ignorieren Client-seitige `existingMedia` vollständig.
  - Bei Aktualisierungen dürfen nur Medien übernommen werden, die bereits im ursprünglichen Datenbank-Eintrag des Nutzers vorhanden waren (`array_intersect`).
  - Beim physischen Löschen von Dateien wird strikt geprüft, ob der Pfad im dedizierten Verzeichnis `produkte/reviews/` liegt und keine Directory-Traversal-Elemente (`..`) enthält.

### 2.17 [PHP 8.4 & SHOPLOGIK] Angebotserstellung & Annahme (Order Quotes): Nullable Expiry, Mengenhärtung & Session-Schutz
* **Dateien:** [`app/Models/Order/OrderQuoteRequest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Order/OrderQuoteRequest.php), [`app/Livewire/Shop/Order/OrderQuoteAcceptance.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderQuoteAcceptance.php), [`app/Services/CartService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartService.php)
* **Befund:**
  1. `isValid()` und `OrderQuoteAcceptance::mount()` riefen `$quote->expires_at->isPast()` bzw. `isFuture()` auf. War `expires_at` `null`, führte dies in PHP 8.4 zu einem fatalen `Call to a member function on null`.
  2. Im Livewire-Event `calculator-save` fehlte eine Prüfung, ob das Angebot noch gültig und offen ist. Zudem konnten über negative Mengen (`$data['qty'] < 0`) negative Summen erzeugt werden.
  3. In `CartService::resolveItemUnitPrice()` wurde eine in der Session hinterlegte `checkout_from_quote_id` nicht auf ihren aktuellen Gültigkeitsstatus (`isValid()`) überprüft.
* **Lösung:** Null-sichere Ablaufprüfungen (`(!$expires_at || $expires_at->isFuture())`), serverseitige Mengenbereinigung (`max(1, $qty)`), Autorisierungscheck vor Speicherung im Angebotskonfigurator sowie Gültigkeitsvalidierung und automatisches Bereinigen ungültiger Angebots-IDs aus der Nutzersitzung.

### 2.18 [DATENVERLUST-SCHUTZ] Cronjob `system:delete-unverified-users`: Schutz von Admins, Mitarbeitern & Käuferkonten
* **Dateien:** [`app/Console/Commands/System/DeleteUnverifiedUsers.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/System/DeleteUnverifiedUsers.php), [`app/Models/Customer/Customer.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Customer/Customer.php)
* **Befund:** Der Aufräum-Cronjob bezog Administratoren und Mitarbeiter in die Löschroutine ein (`$types = ['admin', 'customer', 'employee']`). Da Admins und Mitarbeiter intern erstellt werden und kein `MustVerifyEmail` besitzen, wurden Konten ohne `email_verified_at` im Profil nach 24 Stunden unwiederbringlich per `forceDelete()` gelöscht. Bei Kunden fehlte zudem der Schutz vor Löschung von Konten mit bestehenden Bestellungen (`orders`).
* **Lösung:** Beschränkung des Cronjobs auf reine Kundenkonten (`Customer::class`), Prüfung beider Verifikationsspalten (auf Kunde und Profil) sowie automatischer Schutz von Konten mit Bestellhistorie (`doesntHave('orders')`). Ergänzung der fehlenden Beziehung `orders()` im `Customer`-Model.

### 2.19 [SPAM- & DOS-SCHUTZ] Öffentliches Kontaktformular: Honeypot-Integration, Rate-Limiting & String-Längen
* **Dateien:** [`app/Livewire/Frontend/ContactForm.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Frontend/ContactForm.php), [`resources/views/livewire/frontend/contact-form.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/frontend/contact-form.blade.php)
* **Befund:** Das öffentliche Kontaktformular enthielt weder Bot-Schutz noch Rate-Limiting und validierte String-Längen nicht. Bots konnten unbegrenzt Tickets und automatisierte Bestätigungs-Mails auslösen (Gefahr von E-Mail-Bombing und Mail-Server-Blacklisting).
* **Lösung:** Unsichtbares Honeypot-Feld (`honey_name`), IP-basiertes Rate-Limiting (maximal 5 Anfragen je 10 Minuten) und strikte Längenbeschränkungen für alle Formularfelder.

### 2.20 [AUTORISIERUNG] Belohnungsgutscheine im Warenkorb: Zwingende Authentifizierung für personengebundene Gutscheine
* **Datei:** [`app/Services/CartService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartService.php)
* **Befund:** Die Prüfung personengebundener Belohnungsgutscheine (`recipient_email`) in `applyCoupon()` prüfte nur `if ($buyerEmail && strtolower(...) !== strtolower(...))`. Bei einem nicht eingeloggten Gastkunden (`$buyerEmail === null`) griff diese Bedingung nicht, wodurch der Gutschein zunächst im Gast-Warenkorb aktiviert werden konnte.
* **Lösung:** Zwingende Authentifizierung vorausgesetzt (`if (!$buyerEmail || strtolower(...) !== ...)`): Gastnutzer werden aufgefordert, sich mit dem berechtigten Konto anzumelden.

### 2.21 [DSGVO & DATENSCHUTZ] Öffentliche Spiegelung aller Rechnungs-PDFs im Web-Root
* **Datei:** [`app/Services/InvoiceService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/InvoiceService.php)
* **Befund:** In `storePdf()` wurden sämtliche generierten Kundenrechnungen nicht nur im GoBD-konformen privaten Storage (`disk('local')`) abgelegt, sondern zusätzlich über `Storage::disk('public')->put(...)` in `storage/app/public/buchhaltung/invoices` gespiegelt. Durch den Symlink `public/storage` waren sämtliche Rechnungen im Internet unter `https://domain/storage/buchhaltung/invoices/...` ohne jede Authentifizierung öffentlich downloadbar (Schwere Datenschutzverletzung nach Art. 32 DSGVO).
* **Lösung:** Die Spiegelung in das `public` Disk wurde vollständig entfernt. Alle im öffentlichen Verzeichnis existierenden PDFs wurden bereinigt. Rechnungen liegen ausschließlich im privaten Speicher und werden ausschließlich über authentifizierte Routen mit strikter Autorisierungsprüfung (`abort_unless($user->canView($invoice))`) ausgeliefert.

### 2.22 [DATENSCHUTZ] DHL-Versandlabels im öffentlichen Web-Verzeichnis
* **Dateien:** [`app/Services/DhlService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/DhlService.php), [`app/Livewire/Shop/Order/OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php)
* **Befund:** In `DhlService::handleResponse()` wurden DHL-Paketscheine mit Empfänger-Klarnamen, Postadressen und Sendungsnummern auf `Storage::disk('public')` abgelegt.
* **Lösung:** Die Speicherung wurde auf das private Dateisystem (`disk('local')`, Pfad: `storage/app/bestellungen/dhl_labels/`) umgestellt. Die Download- und Mergemethoden in `OrderOverview` (`downloadDhlLabel`, `downloadMergedLabels`) wurden aktualisiert, um primär aus dem geschützten lokalen Speicher zu laden (mit sicherem Fallback für Altdaten).

### 2.23 [SHOPLOGIK & VORGABEN] Kein Lagerbestands-Rückbuchen bei Stornierung personalisierter Ware
* **Datei:** [`app/Livewire/Shop/Order/OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php)
* **Befund:** Bei Stornierung einer Bestellung (`cancelled`) buchte das System pauschal alle Artikelpositionen in den Lagerbestand zurück (`$item->product->restoreStock(...)`). Bei personalisierten bzw. gelaserten Artikeln ist die Ware nach der Gravur jedoch unverkäuflich (Ausschuss/Verfall) und darf unter keinen Umständen wieder den physischen Lagerbestand erhöhen.
* **Lösung:** Strikte Unterscheidung: Nur Positionen ohne Konfiguration und ohne Personalisierungskennzeichen (`!empty($item->configuration) || $item->product->is_personalizable`) erhalten eine Bestandskorrektur. Personalisierte Artikel verfallen bei Stornierung ohne Bestandsaufstockung.

### 2.24 [SPAM- & DOS-SCHUTZ] Öffentliches Widerrufsformular: Honeypot & Rate-Limiting
* **Dateien:** [`app/Livewire/Shop/Order/OrderRevocationForm.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderRevocationForm.php), [`resources/views/livewire/shop/order/order-revocation-form.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-revocation-form.blade.php)
* **Befund:** Das öffentlich erreichbare Widerrufsformular besaß keinerlei Bot-Schutz oder Rate-Limiting. Bei jedem Absenden wurden zwei E-Mails ausgelöst (an Kunde und Shop-Betreiber). Bots konnten diesen Endpunkt zur Mail-Überflutung missbrauchen.
* **Lösung:** Unsichtbares Honeypot-Feld (`honey_name`) mit lautlosem Abbruch bei Befüllung sowie IP-basiertes Rate-Limiting (maximal 5 Widerrufe je 10 Minuten).

### 2.25 [MULTI-TENANCY & AUTORISIERUNG] Härtung des Bankabgleichs & Zugriffsbeschränkung für Buchungsbelege
* **Dateien:** [`app/Livewire/Shop/Accounting/AccountingBank.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingBank.php), [`routes/partials/admin_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/admin_routes.php)
* **Befund:**
  1. In `AccountingBank.php` fehlte bei Methoden wie `assignCategory()`, `assignCostItem()`, `toggleBusinessStatus()`, `addTag()`, `removeTag()`, `syncAccount()` und `disconnectAccount()` die Prüfung auf `admin_id`. Zudem konnte über `selectBank()` und die ungefilterte Basistransaktions-Query Einsicht in Transaktionen anderer Administratoren genommen werden (IDOR).
  2. Die Hilfsroute `/admin/accounting/receipt` erlaubte das Ausliefern beliebiger privater Dateien im lokalen Storage, solange keine `..`-Pfade enthalten waren (z. B. Zugriff auf ELSTER-Schlüssel oder Konfigurationen).
* **Lösung:**
  1. Die Basis-Abfrage in `render()` sowie alle Mutations-Methoden wurden strikt an `admin_id = auth('admin')->id()` gekoppelt.
  2. Die Route `/admin/accounting/receipt` wurde mit einer strikten Pfad-Whitelist versehen (`buchhaltung/receipts/` und `buchhaltung/contracts/`). Jeder andere Pfad wird sofort mit 403 Forbidden abgewiesen.

### 2.26 [SICHERHEIT & CONFIG-CACHE] Entfernung hartkodierter API-Keys & Absicherung externer Dienste
* **Dateien:** [`app/Services/StealthCrawlerService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/StealthCrawlerService.php), [`app/Services/BankApiService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/BankApiService.php), [`app/Services/ElsterEricService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ElsterEricService.php), [`config/services.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/config/services.php)
* **Befund:**
  1. Im Quellcode von `StealthCrawlerService.php` war ein aktiver ScraperAPI-Schlüssel fest einprogrammiert (`707ccc851a9e7c4759106d2f6e6bf764`).
  2. `BankApiService.php` und `ElsterEricService.php` nutzten direkte `env()`-Aufrufe anstelle von `config()`. In einer Produktivumgebung mit aktivem Konfigurations-Cache (`php artisan config:cache`) liefern `env()`-Aufrufe außerhalb von Konfigurationsdateien `null` zurück, wodurch `BankApiService::generateUserPassword` Passwörter mit leerem Secret erzeugte und ELSTER-Aufrufe fehlschlugen.
  3. In `ElsterEricService.php` fehlte XML-Escaping bei Steuernummern/Finanzamts-IDs und ein `try...finally`-Block für temporäre Steuer-XML-Dateien im Dateisystem.
* **Lösung:**
  1. Der fest kodierte Key wurde rückstandslos entfernt; stattdessen wird auf `config('services.scraperapi.key')` zurückgegriffen mit sauberem Fallback und Logging.
  2. `finapi`, `eric` und `scraperapi` wurden offiziell in [`config/services.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/config/services.php) registriert. `BankApiService` nutzt nun `config('app.key')`.
  3. `ElsterEricService` maskiert XML-Zeichen vor der Payload-Erstellung und garantiert über `try ... finally` die Bereinigung sensibler UStVA-Dateien.

### 2.27 [DATENSCHUTZ & ENUMERATION] Neutraler Status & Rate-Limiting für API Password-Reset
* **Dateien:** [`routes/api/auth.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/auth.php)
* **Befund:** Der Endpunkt `/api/v1/auth/password/email` lieferte bei unbekannten E-Mail-Adressen einen HTTP 404-Statuscode mit der Meldung "Diese E-Mail-Adresse ist uns nicht bekannt." zurück und besaß kein Rate-Limiting. Dies ermöglichte Angreifern User-Enumeration (Auskundschaften registrierter E-Mail-Adressen) und Spam-Missbrauch.
* **Lösung:** Der Endpunkt liefert nun stets eine neutrale Erfolgsmeldung zurück ("Falls ein Konto mit dieser E-Mail-Adresse existiert, haben wir einen Link zum Zurücksetzen des Passworts zugesandt.") und wurde mit einem Rate-Limiter (`throttle:5,1`) gegen Brute-Force und Mailbombing gehärtet.

### 2.28 [MULTI-TENANCY & IDOR] Mandanten-Scoping von Finanz-Ausgaben & Kostenstellen in der API
* **Dateien:** [`routes/api/finance.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/finance.php)
* **Befund:** In den API-Routen `PUT/DELETE /funki/financials/variable/{id}` und `POST/PUT/DELETE /funki/financials/fixed-item` wurden Datensätze (`AccountingSpecialIssue`, `AccountingCostItem`) lediglich per ID abgefragt, ohne den zugehörigen Administrator (`admin_id`) zu validieren. Ein authentifizierter Admin konnte dadurch fremde Fixkosten und Sonderausgaben anderer Mandanten modifizieren oder löschen.
* **Lösung:** Strikte Bindung aller Abfragen und Löschungen an `admin_id = $request->user()->id` sowie Validierung, dass zugewiesene Finanzgruppen dem aktuellen Admin gehören.

### 2.29 [SHOP-LOGIK & DOMAIN-REGEL] Ausschließliche Lager-Rückbuchung unpersonalisierter Standardware bei Stornierung via API
* **Dateien:** [`routes/api/orders.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/orders.php)
* **Befund:** Die API-Route `/shop/orders/{id}/status` buchte bei einer Statusänderung auf `cancelled` entweder gar keinen Lagerbestand zurück oder bot keine Unterscheidung zwischen Stangenware und individualisierten Laserprodukten. Gemäß Geschäftslogik verfallen personalisierte/gelaserte Produkte bei Stornierung und dürfen den Lagerbestand niemals erhöhen.
* **Lösung:** Analoge Implementierung zur Livewire-Bestellübersicht: Beim Setzen des Status auf `cancelled` wird der Lagerbestand ausschließlich für unpersonalisierte Produkte (`!$isPersonalized`) zurückgebucht.

### 2.30 [SYSTEM-SICHERHEIT] Admin-Lockout-Schutz bei Account-Löschung
* **Dateien:** [`app/Traits/handleProfilesTrait.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Traits/handleProfilesTrait.php)
* **Befund:** Wenn ein Administrator in seinem Profil seinen Account löschte (`deleteUserAccount`), gab es keine Prüfung, ob es sich um den letzten Administrator im System handelte. Das Löschen des letzten Administrators hätte zu einem irreparablen System-Lockout geführt.
* **Lösung:** Integrierte Sicherheitsprüfung im Trait: Falls `guard === 'admin'` und `Admin::count() <= 1`, wird die Löschung mit einer Fehlermeldung unterbunden.

### 2.31 [MARKETING & SPENDEN-LOGIK] Automatisierte Rückbuchung von Herzensprojekt-Spendenbeiträgen bei Auftragsstornierung & Erstattung
* **Dateien:** [`app/Livewire/Shop/Order/OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php), [`routes/api/orders.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/orders.php), [`app/Services/HerzensprojektService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/HerzensprojektService.php)
* **Befund:** Bei Bezahlung eines Auftrags wird über `HerzensprojektService::recordDonationForOrder` ein Spendenbeitrag in den Spendentopf verbucht. Wird der Auftrag später storniert oder erstattet, verblieb der Betrag fälschlicherweise als Phantom-Spende im Topf, da `reverseDonationForOrder` nirgendwo im System aufgerufen wurde.
* **Lösung:** Automatische Ausführung von `HerzensprojektService::reverseDonationForOrder($order)` bei Stornierung (`cancelled`) oder Rückerstattung (`refunded`) sowohl im Livewire-Adminbereich als auch im REST-API-Endpunkt.

### 2.32 [KUNDENPROGRAMM & MISSBRAUCHSSCHUTZ] Verhinderung doppelter Empfehlungsvergütungen über Zweit-E-Mails bei identischer Kunden-ID
* **Dateien:** [`app/Services/ReferralService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ReferralService.php)
* **Befund:** `ReferralService::processOrderReferralReward` prüfte bei der Neukunden-Validierung (`$paidOrdersCount`) lediglich, ob unter der angegebenen E-Mail-Adresse bereits bezahlte Bestellungen existierten. Registrierte Kunden mit vorhandenem Kundenkonto (`customer_id`) konnten durch Eingabe einer alternativen E-Mail-Adresse wiederholt als vermeintliche Neukunden gewertet werden und Empfehlungsvergütungen generieren.
* **Lösung:** Die Abfrage aggregiert nun sowohl E-Mail als auch `customer_id`. Wurde über das Kundenkonto bereits zuvor ein bezahlter Auftrag abgewickelt, wird der Empfehlungsbonus strikt verweigert.

### 2.33 [CRM & STORED-XSS-SCHUTZ] Schutz vor Stored XSS & Path Traversal beim Download von E-Mail-Anhängen
* **Dateien:** [`routes/partials/admin_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/admin_routes.php), [`app/Services/Export/FileDownloadService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Export/FileDownloadService.php)
* **Befund:** In den Routen `/admin/inbox/attachment/{id}` und `downloadMailAttachment` wurden Anhänge mit dem MIME-Typ aus der Datenbank bedingungslos mit `Content-Disposition: inline` ausgeliefert. Enthielt eine eingehende E-Mail einen HTML- oder SVG-Anhang mit JavaScript, führte der Klick des Administrators zur Ausführung bösartigen Codes im Kontext des Administrationsbereichs (Stored XSS). Zudem fehlte eine Path-Traversal-Prüfung auf dem hinterlegten Dateipfad.
* **Lösung:** Nur unbedenkliche Medientypen (`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `application/pdf`, `text/plain`) dürfen `inline` dargestellt werden. Alle anderen MIME-Typen werden als sicherer Download erzwungen (`attachment`), ergänzt durch `X-Content-Type-Options: nosniff`, Traversal-Prüfung (`..`, `/`, `\\`) und Bereinigung von Header-Zeichen im Dateinamen.

### 2.34 [FILE-UPLOAD-SCHUTZ] MIME- und Dateityp-Validierung für E-Mail- und Support-Ticket-Anhänge
* **Dateien:** [`app/Livewire/Shop/Support/SupportTicket.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportTicket.php), [`app/Livewire/Customer/CustomerTicketsComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerTicketsComponent.php), [`app/Livewire/Shop/Management/ManagementEMails.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementEMails.php), [`routes/api/emails.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/emails.php)
* **Befund:** Im Support-Ticket-Chat (Admin `sendReply`), in Kunden-Ticket-Antworten und beim Upload von E-Mail-Anhängen fehlte eine Whitelist-Validierung für Dateiendungen und MIME-Typen, sodass beliebige potenziell gefährliche Dateien hochgeladen werden konnten.
* **Lösung:** Strikte `mimes`-Regeln für Dokumente, Bilder und Archive (z. B. `jpg,jpeg,png,webp,gif,pdf,txt,docx` im Support bzw. gängige Büro- und Bildformate im Mail-Bereich).

### 2.35 [AUTORISIERUNG & DOWNLOADS] Absicherung von Widerrufsdateien & flexible Signaturunterstützung für digitale Downloads
* **Dateien:** [`app/Http/Controllers/RevocationFileController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/RevocationFileController.php), [`app/Http/Controllers/Customer/DigitalProductDownloadController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Customer/DigitalProductDownloadController.php), [`routes/partials/global_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/global_routes.php)
* **Befund:** `RevocationFileController` besaß keinerlei Zugriffsbeschränkung oder Traversal-Prüfung. `DigitalProductDownloadController` blockierte signierte Links, falls der Kunde nicht zeitgleich in seiner Session eingeloggt war. Der Resend-Endpunkt für Verifizierungs-Mails war auf den Web-Guard beschränkt.
* **Lösung:** Rollenprüfung und Traversal-Schutz in `RevocationFileController`, Unterstützung kryptografisch signierter URLs (`hasValidSignature`) für digitale Downloads sowie Multi-Guard-Support (`auth:web,customer`) beim Verifizierungs-Resend.

### 2.36 [GUTSCHEIN- & FINANZSCHUTZ] Vollständige Gutschein-Rückabwicklung & Deaktivierung bei Stornierung
* **Dateien:** [`app/Models/Order/OrderOrder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Order/OrderOrder.php), [`app/Livewire/Shop/Order/OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php), [`routes/api/orders.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/orders.php)
* **Befund:** Bei Stornierung oder Erstattung einer Bestellung gab es zwei gravierende Finanzlecks:
  1. Wurde in der Bestellung ein Wertgutschein (`MarketingGiftVoucher`) eingelöst, verfiel das Guthaben des Kunden unwiederbringlich, oder bei Aktionsgutscheinen (`MarketingVoucher`) wurde der Einlösezähler nicht korrigiert.
  2. Wurde in der stornierten Bestellung ein Geschenkgutschein *gekauft*, blieb dieser nach der Stornierung aktiv und voll einlösbar, wodurch Kunden den erstatteten Gutschein kostenlos im Shop einlösen konnten.
* **Lösung:** Einführung einer zentralen Rückabwicklungsmethode `OrderOrder::handleCancellation()`. Eingelöste Wertgutscheine erhalten ihr Saldo über eine negative Buchung im `MarketingGiftVoucherLog` gutgeschrieben, Aktionsgutschein-Zähler werden dekrementiert, und gekaufte Wertgutscheine der Bestellung werden sofort deaktiviert und auf Guthaben 0 gesetzt.

### 2.37 [PAYMENT- & WEBHOOK-INTEGRITÄT] Lückenlose Storno- & Erstattungssynchronisation in Stripe-Webhooks
* **Dateien:** [`app/Http/Controllers/StripeWebhookController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/StripeWebhookController.php)
* **Befund:** Der Stripe-Webhook verarbeitete Rückerstattungen (`charge.refunded`) oder stornierte Zahlungsabsichten (`payment_intent.canceled`) nicht automatisiert bezüglich der vollständigen Geschäftslogik (Bestand, Gutscheine, Herzensprojekt, Empfehlungsprämien).
* **Lösung:** Integration von `charge.refunded` und `payment_intent.canceled` im Webhook-Controller mit Aufruf von `$order->handleCancellation(...)` und sauberer Status-Synchronisation (`refunded`/`cancelled`).

### 2.38 [KI-SICHERHEIT & RECHTEISOLATION] Autorisierungsprüfung für KI-Agenten-Werkzeuge (Tool Privilege Escalation Guard)
* **Dateien:** [`app/Services/AI/AIFunctionsRegistry.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/AIFunctionsRegistry.php)
* **Befund:** In `AIFunctionsRegistry::execute()` wurde zwar geprüft, ob ein Werkzeug im System existiert, jedoch nicht, ob der ausführende KI-Agent (`$agent->tools`) überhaupt über die Berechtigung für dieses spezifische Werkzeug verfügt. Ein Prompt-Injection-Angriff auf einen limitierten Support-Bot hätte ihm gestattet, administrative Finanztools oder Systemfunktionen auszuführen.
* **Lösung:** Integrierte Autorisierungsprüfung in `AIFunctionsRegistry::execute`: Werkzeugaufrufe werden gegen die Tool-Sammlung der Rolle des Agenten abgeglichen; unberechtigte Aufrufe werden sofort mit `PERMISSION DENIED` blockiert.

### 2.39 [WARENKORB & IDOR-SCHUTZ] Mandanten- und Sitzungs-Isolation im CartService
* **Dateien:** [`app/Services/CartService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartService.php)
* **Befund:** In `CartService::updateItem()`, `updateQuantity()` und `removeItem()` wurde lediglich per `CartItem::find($itemId)` nach dem Datensatz gesucht, ohne abzugleichen, ob der Artikel zum aktiven Warenkorb (`$this->getCart()->id`) des aktuellen Besuchers gehört. Ein entfernter Angreifer konnte fremde Warenkorbpositionen manipulieren oder löschen.
* **Lösung:** Integrierte Autorisierungsprüfung: Sofern kein Administrator angemeldet ist (`!auth()->guard('admin')->check()`), wird sichergestellt, dass `$item->cart_id === $currentCart->id`. Manipulationen an fremden Warenkörben werden protokolliert und blockiert.

### 2.40 [KONFIGURATOR & IDOR-SCHUTZ] Berechtigungsprüfung im ProductConfigurator bei nachträglicher Auftragsbearbeitung
* **Dateien:** [`app/Livewire/Shop/Product/ProductConfigurator/ProductConfigurator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductConfigurator/ProductConfigurator.php)
* **Befund:** Im Modus `context === 'order_edit'` lud und überschrieb die Komponente in `mount()` und `save()` die Konfiguration von Bestellpositionen (`OrderOrderItem`), ohne zu überprüfen, ob der eingeloggte Kunde Eigentümer des zugehörigen Auftrags ist (`order->customer_id === auth()->guard('customer')->id()`).
* **Lösung:** Strikte Besitzprüfung sowohl beim Initialisieren (`mount()`) via 403 Abort als auch bei der Ausführung (`save()`). Nur der Eigentümer des Auftrags oder ein Administrator darf das Design bearbeiten.

### 2.41 [BUCHHALTUNG & MULTI-TENANCY] Multi-Tenancy-Scoping & MIME-Whitelist bei Beleg-Uploads in der USt-Zentrale
* **Dateien:** [`app/Livewire/Shop/Accounting/AccountingTax.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingTax.php)
* **Befund:** In `AccountingTax::uploadMissingReceipt()` fehlte eine MIME-Typ- und Größenvalidierung für hochgeladene Belege. Zudem wurden die Ziel-Ausgaben (`AccountingSpecialIssue` und `AccountingCostItem`) ohne Abgleich der `admin_id` geladen (IDOR). In `deleteExport()` fehlte zudem eine Prüfung auf die Dateiendung `.zip`.
* **Lösung:** Strikte Dateivalidierung (`mimes:pdf,jpg,jpeg,png,webp|max:10240`), Sanitizing des Originaldateinamens, Scoping der Belegzuordnung auf `admin_id = auth()->guard('admin')->id()` sowie Prüfung auf `.zip`-Dateiendung beim Löschen archivierter Exporte.

### 2.42 [CHECKOUT & SESSION-SICHERHEIT] Schutz vor Session-Fixation, Brute-Force Rate Limiting & Blocked-IP-Enforcement bei Checkout-Login
* **Dateien:** [`app/Livewire/Shop/Order/OrderCheckout/OrderCheckout.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderCheckout/OrderCheckout.php)
* **Befund:** Die Methode `OrderCheckout::loginUser()` rief nach erfolgreicher Authentifizierung kein `session()->regenerate()` auf, wodurch die Sitzung anfällig für Session-Fixation-Angriffe war. Des Weiteren fehlten IP-Sperrprüfungen (`SystemBlockedIp`), Rate-Limiting gegen Brute-Force-Angriffe und Login-Audit-Logs (`SystemLoginAttempt`).
* **Lösung:** Vollständige Härtung von `loginUser()`: Blockprüfung für gesperrte IPs, Rate-Limiting (5 Versuche / 300s), `session()->regenerate()`, Speicherung der neuen Session-ID am Kunden-Warenkorb und lückenlose Protokollierung in `system_login_attempts`.

### 2.43 [ACCOUNT-SICHERHEIT & PRIVACY] Reset der E-Mail-Verifizierung bei Adressänderung & Folder-Isolation für Profilfotos
* **Dateien:** [`app/Traits/handleProfilesTrait.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Traits/handleProfilesTrait.php)
* **Befund:** Wenn ein Kunde im Profil seine E-Mail-Adresse änderte, blieb sein Verifizierungsstatus (`email_verified_at`) unverändert bestehen, sodass er ohne Bestätigung der Inhaberschaft Zugriff auf fremde Kontrollfunktionen erhalten konnte. Beim Löschen des Profilfotos fehlte zudem ein Pfadpräfix-Check gegen Arbitrary File Deletion.
* **Lösung:** Bei einer Änderung der E-Mail-Adresse im Kundenprofil wird `email_verified_at` auf `null` zurückgesetzt und automatisch eine neue Bestätigungsmail versandt (`sendEmailVerificationNotification`). Beim Löschen des Profilfotos wird geprüft, dass der Pfad strikt in `dashboard/user/` liegt.

### 2.44 [PASSWORT-RESET-SCHUTZ] Rate Limiting & Guard-Scoping bei der Passwort-Rücksetzung
* **Dateien:** [`app/Livewire/Auth/AuthPasswordReset.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Auth/AuthPasswordReset.php)
* **Befund:** Das Formular zur Passworteingabe nach Token-Klick (`AuthPasswordReset::submit`) besaß kein Rate-Limiting gegen Token-Brute-Force. Zudem wurde der Token-Datensatz in `SystemPasswordResetToken` ohne Abgleich des aktuellen Guards abgefragt.
* **Lösung:** Einbau eines Rate-Limiters (5 Versuche / 300s) und Guard-Abgleich anhand des URL-Segments (`admin`, `employee`, `customer`).

### 2.45 [ADMIN-AREA STORED-XSS-SCHUTZ] Sichere Content-Disposition & MIME-Whitelisting für AI-Workspace und Widerrufs-Anhänge
* **Dateien:** [`routes/partials/admin_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/admin_routes.php)
* **Befund:** Die Routen `/admin/ai/workspace/file` und `/admin/widerruf/file/{revocation}/{fileName}` lieferten hochgeladene Dateien direkt mit `response()->file()` aus. Bei HTML- oder SVG-Dateien mit JavaScript-Code konnte dies zu Stored XSS im Admin-Bereich führen.
* **Lösung:** Beschränkung von `inline`-Darstellung auf sichere MIME-Typen (`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `application/pdf`, `text/plain`). Alle anderen Dateitypen werden als `attachment` mit `X-Content-Type-Options: nosniff` forciert.

### 2.46 [ADMIN-LOCKOUT & KUNDENVERIFIZIERUNG] SystemUserManagement Admin-Selbstlöschungsschutz & Kunden-E-Mail-Sync
* **Dateien:** [`app/Livewire/Shop/System/SystemUserManagement.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemUserManagement.php), [`resources/views/livewire/shop/system/system-user-management.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/system/system-user-management.blade.php)
* **Befund:** In der zentralen Benutzerverwaltung (`SystemUserManagement`) fehlten in `archiveUser()` und `forceDelete()` Prüfungen gegen das versehentliche Archivieren des eigenen Admin-Kontos sowie das Löschen des letzten Administrators. Ferner aktualisierte `saveNewUser()` und `saveInline()` nur das Profil, nicht aber `customers.email_verified_at`, wodurch neu verifizierte Kunden sich wegen Laravel MustVerifyEmail-Checks nicht einloggen konnten.
* **Lösung:** 
  1. Hinzufügen von Sperren in `archiveUser()` und `forceDelete()`: Admins können sich nicht selbst löschen/archivieren; der letzte Administrator kann weder archiviert noch unwiderruflich gelöscht werden (`Admin::count() <= 1` / `Admin::withTrashed()->count() <= 1`).
  2. Synchronisation von `email_verified_at` auf der Haupttabelle `customers` bei Anlage und Inline-Bearbeitung.
  3. Integration von Fehlermeldungs-Toasts (`session('error')`) in das Blade-Template.

### 2.47 [BUCHHALTUNGS- & STORNO-SYNC] Rechnungsstornierung mit automatischer Auftrags-, Gutschein- & Lager-Synchronisation
* **Dateien:** [`app/Livewire/Shop/Accounting/AccountingInvoice.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingInvoice.php), [`app/Services/InvoiceService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/InvoiceService.php)
* **Befund:** Bei der Stornierung einer Rechnung über die Buchhaltungs-UI oder den `InvoiceService` wurde zwar eine Stornorechnung / Gutschrift erzeugt, die verknüpfte Bestellung (`$invoice->order`) verblieb jedoch im aktiven Status (`processing`/`completed`). Weder Gutscheine noch Bestände oder Spenden wurden zurückgewickelt. Zudem enthielt `downloadXml()` keine Bereinigung der Rechnungsnummer gegen Directory Traversal.
* **Lösung:**
  1. Automatische Ausführung von `$invoice->order->handleCancellation(...)` und Aktualisierung des Auftragsstatus auf `cancelled` bei Rechnungsstornierung. Dadurch wird Standardware wieder eingebucht, Geschenkgutschein-Guthaben erstattet und gekaufte Gutscheine deaktiviert (während personalisierte Laser-Unikate wunschgemäß als Ausschuss verfallen).
  2. Bereinigung der Rechnungsnummer in `downloadXml()` via `preg_replace('/[^a-zA-Z0-9_-]/', '_', ...)`.

### 2.48 [GAMIFICATION & SUPPORT-ABUSE-SCHUTZ] Null-Safety in der Halle der Legenden & Ticket-Spam-Begrenzung
* **Dateien:** [`app/Livewire/Customer/Gamification/GameGlobalRankingComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/Gamification/GameGlobalRankingComponent.php), [`resources/views/livewire/customer/gamification/game-global-ranking-component.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/customer/gamification/game-global-ranking-component.blade.php), [`app/Livewire/Customer/CustomerTicketsComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerTicketsComponent.php)
* **Befund:** 
  1. Die globale Rangliste (`GameGlobalRankingComponent`) lud Gamification-Datensätze ohne `has('customer')`-Filter und griff im Blade ungeschützt auf `$p->customer->first_name` zu. Bei gelöschten Kunden führte dies zu einem 500er-Fehler für alle Besucher.
  2. Im Kunden-Support (`CustomerTicketsComponent`) konnten Kunden beliebig viele Bug-Report-Tickets eröffnen und unbegrenzt oft je 5 Seelenfunken (+5 ✨) farmen. Zudem war die Angabe einer fremden `order_id` möglich.
* **Lösung:**
  1. `has('customer')`-Filterung und Null-Safe-Operatoren (`$p->customer?->first_name`, `mb_substr`) in der Rangliste.
  2. Rate-Limiting für Ticketerstellungen (5 Versuche / Stunde).
  3. Validierung, dass verknüpfte Bestellungen (`newOrderId`) tatsächlich dem Kunden gehören (IDOR-Schutz).
  4. Begrenzung der Funken-Belohnung für Bug-Meldungen auf maximal einmal pro Tag pro Kunde.

### 2.49 [CHECKOUT PAYMENT-BYPASS & IDOR-SCHUTZ] Absicherung von `handlePaymentSuccess` gegen unberechtigte Zahlungsbestätigung
* **Dateien:** [`app/Livewire/Shop/Order/OrderCheckout/Traits/HandlesStripePayment.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderCheckout/Traits/HandlesStripePayment.php)
* **Befund:** Die Methode `handlePaymentSuccess($orderId)` überprüfte bei Aufrufen ohne aktiven PaymentIntent (`currentPaymentIntentId == null`, z. B. bei manipulierter Session oder 0-Euro-Starts) weder die Kundenbesitzerschaft von `$candidate` noch, ob ein Auftrag mit Betrag > 0 € tatsächlich bei Stripe bezahlt worden war. Ein Angreifer konnte so fremde oder unbezahlte Bestellungen unrechtmäßig als bezahlt markieren.
* **Lösung:**
  1. Strikter Session-Ownership-Check: Die Zielbestellung muss zum aktuell eingeloggten Kunden oder zur eingegebenen Checkout-E-Mail gehören.
  2. Bestellungen mit Betrag > 0 € müssen zwingend einen übereinstimmenden PaymentIntent besitzen, der serverseitig bei Stripe mit Status `succeeded` und exakter Betragsübereinstimmung verifiziert wird.
  3. Nur echte 0,00 € Bestellungen (100% Gutscheindeckung) dürfen ohne externen Stripe-Intent als bezahlt finalisiert werden.

### 2.50 [BELEG-DOWNLOAD STORED-XSS-SCHUTZ] Absicherung der Buchhaltungs-Belegabrufe
* **Dateien:** [`routes/partials/admin_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/admin_routes.php)
* **Befund:** Der Endpoint `/admin/accounting/receipt` lieferte Belege und Verträge über `response()->file()` ohne MIME-Typ-Validierung aus. Wurden manipulierte Dateien (HTML, SVG mit Scripten) hinterlegt, konnte beim Betrachten im Admin-Kontext Stored XSS ausgelöst werden.
* **Lösung:** MIME-Whitelisting (`safeMimes`), Forcierung von `Content-Disposition: attachment` für potentiell gefährliche Dateiformate und Auslieferung mit `X-Content-Type-Options: nosniff`.

### 2.51 [HERZENSPROJEKT SETTINGS-BUG & VALIDIERUNG] Fehlerhafte Konfigurationsspeicherung & unvalidierte Spendenbeträge behoben
* **Dateien:** [`app/Livewire/Shop/Marketing/MarketingHerzensprojekte.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingHerzensprojekte.php)
* **Befund:** In `saveSettings()` wurde die Klasse `\App\Models\System\ShopConfig` referenziert, welche im System gar nicht existiert (`class_exists` lieferte `false`). Einstellungen für `herzensprojekt_cents_per_order` wurden daher stillschweigend verworfen. Zudem fehlte eine Authentifizierungsprüfung in `mount()` und `saveDeposit()` sowie `saveSettings()` besaßen keine Server-Validierung gegen negative Werte oder Eingabefehler.
* **Lösung:** 
  1. `mount()` mit `Auth::guard('admin')->check()` gegen unberechtigte Aufrufe abgesichert.
  2. Speicherung der Konfiguration auf das existierende Model `SystemSetting::updateOrCreate(['key' => 'herzensprojekt_cents_per_order'], ...)` umgestellt und globalen Shop-Cache via `Cache::forget('global_shop_settings')` invalidiert.
  3. Serverseitige Validierungsregeln für Einzahlungsbeträge (`depositAmountEuro => min:0.01|max:100000`) und Spenden je Bestellung (`centsPerOrder => min:0|max:100000`) implementiert.
* **Testabdeckung:** [`tests/Feature/Marketing/MarketingHerzensprojekteSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/MarketingHerzensprojekteSecurityTest.php) (3 Tests, 7 Assertions).

### 2.52 [WARTUNGS-ROUTEN HÄRTUNG & PRODUCTION BACKDOOR REMOVAL] Beseitigung hartcodierter Secret-Routen
* **Dateien:** [`routes/partials/global_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/global_routes.php), [`config/services.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/config/services.php)
* **Befund:** In den öffentlichen Routen existierten hartcodierte Pfade (`/system/cronjob/run-secret-847294` und `/system/cache/clear-secret-847294`). Wurde in der Produktivumgebung `php artisan config:cache` ausgeführt, lieferte `env()` außerhalb der Configs `null` zurück, sodass der statische Default-Secret griff und jeder Internetnutzer den Cache leeren (`optimize:clear`) oder Cronjobs anstoßen konnte.
* **Lösung:** Statische Backdoor-Routen vollständig entfernt. Parameter-Routen `/system/cronjob/{token}` und `/system/cache/clear/{token}` lesen Geheimnisse nun aus `config('services.system.cron_secret')` bzw. `config('services.system.cache_secret')`. Ist kein Secret konfiguriert oder leer, wird der Aufruf ausnahmslos mit HTTP 403 Forbidden abgewiesen.
* **Testabdeckung:** [`tests/Feature/System/SystemMaintenanceSecretRouteTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemMaintenanceSecretRouteTest.php) (3 Tests, 7 Assertions).

### 2.53 [MASS-ASSIGNMENT & MANDANTEN-ISOLATION] Schutz variabler Kosten vor unberechtigter Spaltenüberschreibung
* **Dateien:** [`app/Livewire/Shop/Accounting/AccountingVariableCosts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingVariableCosts.php)
* **Befund:** 
  1. In `updateSpecialField($id, $field, $value)` wurde der Spaltenname `$field` ohne Whitelist direkt an Eloquent `$special->update([$field => $value])` übergeben. Da `AccountingSpecialIssue` mit `protected $guarded = []` ungeschützt war, hätten Fremdschlüssel (`admin_id`), IDs oder Systemdaten beliebig überschrieben werden können.
  2. In `confirmDeleteCategory()` wurden `$oldCat` und `$newCat` ohne Prüfung der `admin_id` geladen, wodurch Kategorien anderer Admins gelöscht oder manipuliert werden konnten.
* **Lösung:** 
  1. Strikte Whitelist für erlaubte Felder (`title`, `category`, `amount`, `is_business`, `execution_date`, `location`, `invoice_number`, `tax_rate`) mit Typprüfung, Datumsformatierung und Sanitization.
  2. Bindung aller Kategorie-Abfragen an `where('admin_id', $this->getAdminId())`.
* **Testabdeckung:** [`tests/Feature/Accounting/AccountingVariableCostsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/AccountingVariableCostsSecurityTest.php) (3 Tests, 6 Assertions).

### 2.54 [AUTORISIERUNGSSCHUTZ INFRASTRUKTUR-CONTROLLER] Absicherung von Kapazitäts- & Speicherverwaltung
* **Dateien:** [`app/Livewire/Shop/Master/MasterShopCapacity.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Master/MasterShopCapacity.php), [`app/Livewire/Shop/Master/MasterStorageCapacity.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Master/MasterStorageCapacity.php)
* **Befund:** Beide Livewire-Komponenten besitzen mächtige Systemfunktionen (Umschalten des Shop-Autopiloten, Löschen von Laravel-Serverlogs, Leeren temporärer Uploads und Blade-View-Caches). Sie besaßen in `mount()` jedoch keinerlei Authentifizierungsprüfung, sodass unberechtigte Nutzer über Livewire-Update-Payloads administrative Systemaktionen auslösen konnten.
* **Lösung:** Beide Komponenten verifizieren in `mount()` zwingend `Auth::guard('admin')->check()` und brechen bei fehlenden Admin-Rechten sofort mit HTTP 403 Forbidden ab.
* **Testabdeckung:** [`tests/Feature/Master/MasterCapacityAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Master/MasterCapacityAuthorizationTest.php) (5 Tests, 6 Assertions).

### 2.55 [DOS-, MAIL-BOMBING & RECEIPT-XSS-SCHUTZ] Härtung öffentlicher Telemetrie- und mobiler Beleg-Endpunkte
* **Dateien:** [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php)
* **Befund:** 
  1. `/api/log/frontend-error` und `/api/log/websocket` waren ungeschützt und besaßen kein Rate-Limiting. Bei `/api/log/websocket` wurde bei jedem Request mit `type: error` unmittelbar eine E-Mail an den Admin versandt, was triviales Mail-Bombing und Quota-Erschöpfung ermöglichte.
  2. Die Flug-Proxy-Route `/flights` war ohne Rate-Limiting öffentlich erreichbar.
  3. Der Endpunkt `/api/funki/financials/receipt` lieferte Belege ohne MIME-Prüfung aus und nutzte statische `storage_path('app/...')`-Pfade anstelle der flexiblen Laravel Storage Disks.
* **Lösung:** 
  1. Rate-Limiting `throttle:10,1` für Logging-Endpunkte und Cache-gestütztes E-Mail-Throttling (maximal 1 Benachrichtigung pro 15 Minuten bei WebSocket-Fehlern).
  2. Rate-Limiting `throttle:30,1` für den `/flights`-Proxy.
  3. Dynamische Pfadauflösung über `Storage::disk('local')->path($path)`, strikte Inline-Begrenzung auf sichere Bild- und PDF-Formate sowie Forcierung von `attachment` mit `X-Content-Type-Options: nosniff`.
* **Testabdeckung:** [`tests/Feature/Api/ApiLoggingAndReceiptSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/ApiLoggingAndReceiptSecurityTest.php) (4 Tests, 12 Assertions).

---

## 3. Übersicht aller modifizierten Dateien

| Datei | Art der Änderung |
|---|---|
| [`app/Livewire/Shop/Marketing/MarketingHerzensprojekte.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingHerzensprojekte.php) | Admin-Autorisierung in `mount()`, Speicherung via `SystemSetting` + Cache-Clearing, serverseitige Validierung |
| [`routes/partials/global_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/global_routes.php) | Entfernung hartcodierter Secret-Routen; sichere Parameter-Routen mit `config()` und 403-Fallback |
| [`config/services.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/config/services.php) | Registrierung von `system.cron_secret` und `system.cache_secret` für reibungsloses Config-Caching |
| [`app/Livewire/Shop/Accounting/AccountingVariableCosts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingVariableCosts.php) | Whitelist & Validierung bei `updateSpecialField` gegen Mass-Assignment; Admin-Scoping bei `confirmDeleteCategory` |
| [`app/Livewire/Shop/Master/MasterShopCapacity.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Master/MasterShopCapacity.php) | Admin-Autorisierungsprüfung in `mount()` (`Auth::guard('admin')->check()`) |
| [`app/Livewire/Shop/Master/MasterStorageCapacity.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Master/MasterStorageCapacity.php) | Admin-Autorisierungsprüfung in `mount()` (`Auth::guard('admin')->check()`) |
| [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php) | Rate-Limiter für Logs & Proxy, Mail-Throttling (15 Min), Storage-Disk-Pfadauflösung & MIME-Whitelisting für Belege |
| [`tests/Feature/Marketing/MarketingHerzensprojekteSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/MarketingHerzensprojekteSecurityTest.php) | Neue Testsuite: Herzensprojekt Admin-Guard, Settings-Persistenz & Einzahlungs-Validierung |
| [`tests/Feature/System/SystemMaintenanceSecretRouteTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemMaintenanceSecretRouteTest.php) | Neue Testsuite: Ablehnung alter statischer Secret-Tokens & sichere Token-Verifikation |
| [`tests/Feature/Accounting/AccountingVariableCostsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/AccountingVariableCostsSecurityTest.php) | Neue Testsuite: Mass-Assignment-Schutz für Buchungsfelder & Mandanten-Isolation |
| [`tests/Feature/Master/MasterCapacityAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Master/MasterCapacityAuthorizationTest.php) | Neue Testsuite: Admin-Zugriffsschutz für Kapazitäts- und Speichermanagement |
| [`tests/Feature/Api/ApiLoggingAndReceiptSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/ApiLoggingAndReceiptSecurityTest.php) | Neue Testsuite: Mail-Bombing-Throttling, Beleg-MIME-Whitelisting (`nosniff`) & Auth-Gates |
| [`app/Livewire/Shop/System/SystemUserManagement.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemUserManagement.php) | Admin-Lockout-Schutz (Selbst-Archivierung & Löschung des letzten Admins gesperrt) & `email_verified_at` Sync für Kunden |
| [`resources/views/livewire/shop/system/system-user-management.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/system/system-user-management.blade.php) | Fehler-Toast Anzeige (`session('error')`) |
| [`app/Livewire/Shop/Accounting/AccountingInvoice.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingInvoice.php) | Storno-Synchronisation mit Bestellung (`handleCancellation`) & XML-Dateinamen-Bereinigung |
| [`app/Services/InvoiceService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/InvoiceService.php) | Storno-Synchronisation mit Bestellung (`handleCancellation`) bei Rechnungsannullierung |
| [`app/Livewire/Customer/Gamification/GameGlobalRankingComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/Gamification/GameGlobalRankingComponent.php) | `has('customer')`-Filterung zur Vermeidung von 500er-Crashes bei gelöschten Nutzern |
| [`resources/views/livewire/customer/gamification/game-global-ranking-component.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/customer/gamification/game-global-ranking-component.blade.php) | Null-sichere Blade-Ausgabe für Kundennamen |
| [`app/Livewire/Customer/CustomerTicketsComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerTicketsComponent.php) | Rate-Limiter (5/Stunde), IDOR-Schutz bei Bestellzuordnung & Bug-Funken-Begrenzung (max. 1x täglich) |
| [`app/Livewire/Shop/Order/OrderCheckout/Traits/HandlesStripePayment.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderCheckout/Traits/HandlesStripePayment.php) | Payment-Bypass-Sperre & IDOR-Schutz: Strikte Besitzprüfung & PaymentIntent-Abgleich vor Abschluss |
| [`routes/partials/admin_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/admin_routes.php) | Stored-XSS-Schutz bei Buchhaltungs-Belegen (`/admin/accounting/receipt`) mit MIME-Whitelist & `nosniff` |
| [`tests/Feature/System/SystemUserManagementSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemUserManagementSecurityTest.php) | Neue Testsuite: Admin-Lockout-Schutz & Kundenverifizierungs-Sync |
| [`tests/Feature/Accounting/AccountingInvoiceCancellationOrderSyncTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/AccountingInvoiceCancellationOrderSyncTest.php) | Neue Testsuite: Rechnungsstorno-Synchronisation, Lager-Restore & Gutschein-Saldo |
| [`tests/Feature/Customer/CustomerTicketSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Customer/CustomerTicketSecurityTest.php) | Neue Testsuite: Ticket-Rate-Limiter, Order-IDOR-Schutz & tägliche Funken-Deckelung |
| [`tests/Feature/Order/CheckoutPaymentSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/CheckoutPaymentSecurityTest.php) | Neue Testsuite: Schutz vor unberechtigter Zahlungsbestätigung & Intent-Mismatch im Checkout |
| [`app/Livewire/Frontend/Emergency/EmergencyProtocol.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Frontend/Emergency/EmergencyProtocol.php) | `ensureAuthenticated()`-Schutz vor allen sensiblen Aktionen & PDF-Downloads |
| [`app/Livewire/Shop/System/SystemBackups.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemBackups.php) | Path-Traversal-Schutz & Endungs-Validierung für Backups |
| [`app/Services/Export/FileDownloadService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Export/FileDownloadService.php) | Directory-Traversal-Schutz, Whitelist für Steuerexporte & XSS-Schutz für Mail-Anhänge |
| [`app/Livewire/Shop/Accounting/AccountingTax.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingTax.php) | Sichere Übergabe von `basename($filename)` an den Export-Service |
| [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php) | Striktes Admin-Middleware-Gate für alle Back-Office-API-Routen |
| [`routes/api/ai.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/ai.php) | Zugriffsschutz auf Schema, Chat, Execute & Live-Credentials für Admins/Mitarbeiter |
| [`app/Services/AI/AiAuthHelper.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/AiAuthHelper.php) | Neue Helfermethode `isEmployee()` hinzugefügt |
| [`app/Models/Customer/Customer.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Customer/Customer.php) | `google_id` und `email_verified_at` in `$fillable` aufgenommen; `orders()`-Relation ergänzt |
| [`app/Models/Admin/Admin.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Admin/Admin.php) | `google_id` in `$fillable` aufgenommen |
| [`app/Models/Employee/Employee.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Employee/Employee.php) | `google_id` in `$fillable` aufgenommen |
| [`app/Http/Controllers/GoogleAuthController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/GoogleAuthController.php) | Abweisung unverifizierter Google-Mails & automatische E-Mail-Verifizierung für neue Google-Kunden |
| [`app/Http/Controllers/Api/TelegramAgentController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/TelegramAgentController.php) | Chat-ID Whitelist vor Dateidownloads, Upload-Dateiendungs-Whitelist, Plaintext-Fallback |
| [`app/Http/Controllers/Api/TwilioCallController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/TwilioCallController.php) | Validierung von `planned_call_id` zur lückenlosen Zuordnung von Anrufprotokollen |
| [`app/Livewire/Landing/LandingPageView.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Landing/LandingPageView.php) | 404-Schutz für Entwurfs-Landing-Pages und inaktive Produkte |
| [`app/Livewire/Shop/Marketing/MarketingLandingPages.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingLandingPages.php) | Bereinigung des Slugs bei dynamischer Blade-Erstellung/Löschung |
| [`resources/views/livewire/landing/partials/master-template.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/landing/partials/master-template.blade.php) | PHP 8.4 Null-Safe `media_gallery` Prüfung & Stored-XSS-Schutz für Sales Copy |
| [`routes/partials/global_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/global_routes.php) | Härtung der System-Cron & Cache-Routen gegen Secret-Bypasses, Multi-Guard Resend (`auth:web,customer`) |
| [`app/Http/Controllers/Customer/GiftVoucherDownloadController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Customer/GiftVoucherDownloadController.php) | Download-Autorisierung für Empfehlungsgutscheine ohne OrderItem |
| [`resources/views/pdf/marketing-gift-voucher.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/pdf/marketing-gift-voucher.blade.php) | Null-sichere Darstellung des Ablaufdatums |
| [`routes/api/calendar.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/calendar.php) | Endlosschleifen-Schutz bei Intervall-Berechnung |
| [`app/Livewire/Customer/CustomerOrdersComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerOrdersComponent.php) | IDOR-Schutz: Strikte Kundenbindung für Bestellungen, Positionen, Rechnungen & Previews |
| [`app/Services/CartService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartService.php) | Absicherung gegen negative Mengen, IDOR-Schutz (`updateItem`, `updateQuantity`, `removeItem`) auf aktiven Warenkorb |
| [`app/Livewire/Shop/Product/ProductConfigurator/ProductConfigurator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductConfigurator/ProductConfigurator.php) | IDOR-Schutz: Kundenbindung bei `order_edit` Design-Modifikationen in `mount()` und `save()` |
| [`app/Livewire/Shop/Order/OrderCheckout/OrderCheckout.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderCheckout/OrderCheckout.php) | Session-Regeneration gegen Session Fixation, Brute-Force-Rate-Limiter & Blocked-IP-Enforcement bei Checkout-Login |
| [`app/Livewire/Auth/AuthPasswordReset.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Auth/AuthPasswordReset.php) | Brute-Force-Rate-Limiting (5/300s) & Guard-Scoping bei der Passwort-Rücksetzung |
| [`tests/Feature/Cart/CartServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Cart/CartServiceSecurityTest.php) | Neue Testsuite: Verhinderung von Cross-Cart Item Manipulation & Löschung |
| [`tests/Feature/Customer/ConfiguratorOrderEditSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Customer/ConfiguratorOrderEditSecurityTest.php) | Neue Testsuite: IDOR-Schutz für Bestellpositionen im Konfigurator |
| [`tests/Feature/Accounting/AccountingTaxReceiptSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/AccountingTaxReceiptSecurityTest.php) | Neue Testsuite: MIME-Whitelist, Multi-Tenancy IDOR & Zip-Löschschutz bei USt-Belegen |
| [`tests/Feature/Auth/CheckoutAuthSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Auth/CheckoutAuthSecurityTest.php) | Neue Testsuite: IP-Sperre, Rate-Limiting & Session-Regeneration bei Checkout-Login |
| [`app/Livewire/Shop/Accounting/AccountingFixCosts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingFixCosts.php) | MIME-Typ-Validierung für Fixkosten-Belege und Verträge |
| [`app/Livewire/Shop/Accounting/AccountingQuickEntry.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingQuickEntry.php) | MIME-Typ-Validierung für Schnellbeleg-Uploads |
| [`app/Livewire/Shop/Accounting/AccountingVariableCosts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingVariableCosts.php) | MIME-Typ-Validierung für Beleg-Uploads variabler Kosten |
| [`app/Livewire/Shop/Accounting/AccountingBank.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingBank.php) | Multi-Tenancy-Scoping auf `admin_id` für Transaktionen, Konten, Zuweisungen und Aktionen |
| [`app/Console/Commands/SendNewsletter.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/SendNewsletter.php) | Behebung des Klassenimport-Fehlers (`NewsletterAutomaticMail`) |
| [`app/Livewire/Shop/Marketing/MarketingNewsletter.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingNewsletter.php) | Behebung des Klassenimport-Fehlers (`NewsletterAutomaticMail`) |
| [`app/Livewire/Shop/Marketing/MarketingNewsletterPage.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingNewsletterPage.php) | User-Enumeration-Schutz & Re-Tokening für unbestätigte Adressen |
| [`app/Livewire/Shop/Marketing/MarketingNewsletterSignup.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingNewsletterSignup.php) | Bereinigung der Validierungsregeln auf korrekte Tabellen |
| [`app/Livewire/Shop/Product/ProductReviews.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductReviews.php) | Schutz vor Arbitrary File Deletion im Public Storage |
| [`app/Livewire/Shop/Product/ProductControlReviews.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductControlReviews.php) | Folder- und Traversal-Prüfung beim Löschen von Bewertungsmedien |
| [`app/Models/Order/OrderQuoteRequest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Order/OrderQuoteRequest.php) | Null-sichere `isValid()`-Prüfung bei Angeboten |
| [`app/Livewire/Shop/Order/OrderQuoteAcceptance.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderQuoteAcceptance.php) | Statusprüfung vor Speicherung, Mengenbereinigung & Null-Safe Expiry |
| [`app/Console/Commands/System/DeleteUnverifiedUsers.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/System/DeleteUnverifiedUsers.php) | Schutz interner Admins/Mitarbeiter und aktiver Kunden vor versehentlicher Löschung |
| [`app/Livewire/Frontend/ContactForm.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Frontend/ContactForm.php) | Honeypot gegen Spam-Bots, Rate-Limiting & String-Längenbegrenzung |
| [`resources/views/livewire/frontend/contact-form.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/frontend/contact-form.blade.php) | Unsichtbares Honeypot-Eingabefeld im Kontaktformular |
| [`app/Services/DhlService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/DhlService.php) | Umstellung aller DHL-Labels auf geschützten privaten lokalen Speicher |
| [`app/Livewire/Shop/Order/OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php) | Kein Lagerbestand-Restore für personalisierte Ware; Herzensprojekt-Spenden-Rückbuchung bei Storno/Erstattung |
| [`app/Livewire/Shop/Order/OrderRevocationForm.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderRevocationForm.php) | Bot-Honeypot und IP-Rate-Limiting (5/10min) für elektronische Widerrufe |
| [`resources/views/livewire/shop/order/order-revocation-form.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-revocation-form.blade.php) | Unsichtbares Honeypot-Feld im Widerrufsformular |
| [`config/services.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/config/services.php) | Registrierung von `finapi`, `eric` und `scraperapi` für `config:cache` Robustheit |
| [`app/Services/BankApiService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/BankApiService.php) | Migration auf `config('services.finapi...')` und `config('app.key')` |
| [`app/Services/StealthCrawlerService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/StealthCrawlerService.php) | Entfernung hartkodierter API-Keys; sichere Konfigurationsauflösung |
| [`app/Services/ElsterEricService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ElsterEricService.php) | `config()`-Migration, XML-Injection-Schutz, CLI-Escaping & `try...finally`-Dateibereinigung |
| [`routes/api/auth.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/auth.php) | User-Enumeration-Schutz & Rate-Limiter (`throttle:5,1`) beim Passwort-Reset |
| [`routes/api/finance.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/finance.php) | Mandanten-Isolation (`admin_id`) bei Fix- und variablen Kosten in der REST-API |
| [`routes/api/orders.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/orders.php) | Domain-Regel für Lager-Restore & Herzensprojekt-Spenden-Rückbuchung via API |
| [`app/Traits/handleProfilesTrait.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Traits/handleProfilesTrait.php) | Admin-Lockout-Schutz: Letzter Admin kann sich nicht selbst löschen |
| [`app/Services/ReferralService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ReferralService.php) | Verlässlicher Neukunden-Schutz für Empfehlungen über E-Mail und `customer_id` |
| [`app/Livewire/Shop/Support/SupportTicket.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportTicket.php) | Whitelist-Validierung für Admin-Antwort-Anhänge im Support |
| [`app/Livewire/Shop/Management/ManagementEMails.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementEMails.php) | Whitelist-Validierung für E-Mail-Anhänge |
| [`routes/api/emails.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/emails.php) | Whitelist-Validierung für E-Mail-Anhänge in der REST-API |
| [`app/Http/Controllers/Customer/DigitalProductDownloadController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Customer/DigitalProductDownloadController.php) | Signatur-Unterstützung (`hasValidSignature`) & Admin/Mitarbeiter-Bypass |
| [`app/Http/Controllers/RevocationFileController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/RevocationFileController.php) | Authentifizierungsprüfung & Path-Traversal-Schutz |
| [`app/Models/Order/OrderOrder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Order/OrderOrder.php) | Zentrale Rückabwicklungs-Methode `handleCancellation()` für Gutscheine, Lager, Spenden & Referrals |
| [`app/Http/Controllers/StripeWebhookController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/StripeWebhookController.php) | Automatisierte Storno- und Erstattungsverarbeitung bei `charge.refunded` und `payment_intent.canceled` |
| [`app/Services/AI/AIFunctionsRegistry.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/AIFunctionsRegistry.php) | Autorisierungsprüfung auf Agenten-Tools vor Funktionsausführung (Privilege Escalation Guard) |
| [`tests/Feature/Order/OrderCancellationComprehensiveTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderCancellationComprehensiveTest.php) | Neue Testsuite: Gutschein-Saldo-Restore, Geschenkgutschein-Deaktivierung & Referral-Reversal |
| [`tests/Feature/Ai/AiToolAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiToolAuthorizationTest.php) | Neue Testsuite: Prüfung von Permission Denied bei nicht zugewiesenen KI-Werkzeugen |
| [`tests/Feature/Accounting/InvoiceStoragePrivacyTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/InvoiceStoragePrivacyTest.php) | Neue Testsuite: Rechnungen existieren nur im privaten Storage |
| [`tests/Feature/Order/OrderCancellationStockTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderCancellationStockTest.php) | Neue Testsuite: Lagerbestandsprüfung bei Storno (Standard vs. Gravurware) |
| [`tests/Feature/Order/OrderRevocationSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderRevocationSecurityTest.php) | Neue Testsuite: Bot-Honeypot & Rate-Limiter im Widerrufsformular |
| [`tests/Feature/Accounting/AccountingBankSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/AccountingBankSecurityTest.php) | Neue Testsuite: Traversal-Schutz, Beleg-Whitelist & Multi-Tenancy im Bankabgleich |
| [`tests/Feature/Product/StealthCrawlerSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/StealthCrawlerSecurityTest.php) | Neue Testsuite: Prüfung auf rückstandsfreie Entfernung harter Keys & sanfte Fehlerbehandlung |
| [`tests/Feature/Api/AuthApiSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/AuthApiSecurityTest.php) | Neue Testsuite: Neutrale Password-Reset-Responses & Enumeration-Schutz |
| [`tests/Feature/Api/FinanceApiSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/FinanceApiSecurityTest.php) | Neue Testsuite: Multi-Tenancy IDOR-Schutz bei API-Kostenstellen |
| [`tests/Feature/Api/OrderApiCancellationStockTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/OrderApiCancellationStockTest.php) | Neue Testsuite: Domain-Regel für Bestandsrückbuchung via API |
| [`tests/Feature/Auth/AdminAccountProtectionTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Auth/AdminAccountProtectionTest.php) | Neue Testsuite: Schutz vor versehentlicher Löschung des letzten Administrators |
| [`tests/Feature/Crm/MailAttachmentSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Crm/MailAttachmentSecurityTest.php) | Neue Testsuite: Stored XSS-Schutz & Traversal-Sperre bei E-Mail-Anhängen |
| [`tests/Feature/Marketing/HerzensprojektDonationReversalTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/HerzensprojektDonationReversalTest.php) | Neue Testsuite: Automatische Spenden-Rückbuchung bei Auftragsstorno |
| [`tests/Feature/Marketing/ReferralCustomerProtectionTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/ReferralCustomerProtectionTest.php) | Neue Testsuite: Neukunden-Schutz für Empfehlungen über Kunden-ID |

---

## 4. Test- & Verifikationsergebnisse

In der Docker-Umgebung (`mein_php_server`, PHP 8.4) wurden alle Tests erfolgreich validiert:
* **Sicherheits- & Autorisierungs-Testsuiten (Gesamt):** 136 Tests bestanden (426 Assertions, 0 Fehler)
  * `MarketingHerzensprojekteSecurityTest`: 3/3 bestanden (Herzensprojekt Admin-Guard, Settings-Persistenz & Einzahlungs-Validierung)
  * `SystemMaintenanceSecretRouteTest`: 3/3 bestanden (Ablehnung alter statischer Secret-Tokens & sichere Token-Verifikation)
  * `AccountingVariableCostsSecurityTest`: 3/3 bestanden (Mass-Assignment-Schutz für Buchungsfelder & Mandanten-Isolation)
  * `MasterCapacityAuthorizationTest`: 5/5 bestanden (Admin-Zugriffsschutz für Kapazitäts- und Speichermanagement)
  * `ApiLoggingAndReceiptSecurityTest`: 4/4 bestanden (Mail-Bombing-Throttling, Beleg-MIME-Whitelisting `nosniff` & Auth-Gates)
  * `SystemUserManagementSecurityTest`: 5/5 bestanden (Admin-Lockout-Schutz & Kundenverifizierungs-Sync)
  * `AccountingInvoiceCancellationOrderSyncTest`: 1/1 bestanden (Rechnungsstorno-Sync, Standardwaren-Bestandsrestore & Gutschein-Saldo-Restore; Laserware verbleibt unberührt)
  * `CustomerTicketSecurityTest`: 3/3 bestanden (Ticket-Rate-Limiter, Fremdbestellungs-IDOR-Schutz & tägliche Funkenbelohnungs-Deckelung)
  * `CheckoutPaymentSecurityTest`: 2/2 bestanden (Schutz vor unberechtigter Zahlungsbestätigung & Intent-Mismatch im Checkout)
  * `CartServiceSecurityTest`: 1/1 bestanden (Warenkorb-Isolation gegen fremde Item-Manipulation)
  * `ConfiguratorOrderEditSecurityTest`: 2/2 bestanden (IDOR-Schutz bei Konfigurator-Bestelländerungen)
  * `AccountingTaxReceiptSecurityTest`: 3/3 bestanden (MIME-Whitelist, Mandanten-Isolation & Export-Löschschutz)
  * `CheckoutAuthSecurityTest`: 3/3 bestanden (IP-Sperren, Rate-Limiting & Session-Regeneration)
  * `AiToolAuthorizationTest`: 1/1 bestanden (Unbefugte Tool-Ausführung für KI-Agenten unterbunden)
  * `OrderCancellationComprehensiveTest`: 3/3 bestanden (Gutschein-Saldo-Restore, Geschenkgutschein-Sperre & Referral-Reversal)
  * `InvoiceStoragePrivacyTest`: 1/1 bestanden (Private Storage Verifikation)
  * `OrderCancellationStockTest`: 2/2 bestanden (Standard- vs. Gelasertes Produkt Bestandsprüfung)
  * `OrderApiCancellationStockTest`: 1/1 bestanden (API Bestandsprüfung bei Storno)
  * `HerzensprojektDonationReversalTest`: 1/1 bestanden (Spenden-Rückbuchung bei Storno)
  * `ReferralCustomerProtectionTest`: 1/1 bestanden (Neukunden-Schutz über Customer-ID)
  * `MailAttachmentSecurityTest`: 2/2 bestanden (Stored XSS & Path Traversal Sperre)
  * `OrderRevocationSecurityTest`: 3/3 bestanden (Honeypot, legitimer Widerruf, Rate-Limiter)
  * `AccountingBankSecurityTest`: 5/5 bestanden (Traversal, Whitelist, Multi-Tenancy IDOR)
  * `StealthCrawlerSecurityTest`: 2/2 bestanden (Kein Key-Leak, Graceful API-Handling)
  * `AuthApiSecurityTest`: 2/2 bestanden (Enumeration-Schutz)
  * `FinanceApiSecurityTest`: 3/3 bestanden (Multi-Tenancy IDOR-Schutz)
  * `AdminAccountProtectionTest`: 2/2 bestanden (Lockout-Schutz für letzten Admin)
  * `BackOfficeApiSecurityTest`: 4/4 bestanden
  * `TaxExportSecurityTest`: 3/3 bestanden
  * `GoogleAuthFillableTest`: 2/2 bestanden
  * `LandingPageSecurityTest`: 3/3 bestanden
  * `AiWidgetLiveModeTest`: 8/8 bestanden
  * `GiftVoucherTest`: 21/21 bestanden
  * `EmergencyProtocolTest`: 4/4 bestanden
  * `CustomerOrdersSecurityTest`: 4/4 bestanden
  * `CustomerTicketsSecurityTest`: 3/3 bestanden
  * `CartQuantitySecurityTest`: 2/2 bestanden
  * `NewsletterSecurityTest`: 3/3 bestanden
  * `ProductReviewSecurityTest`: 2/2 bestanden
  * `OrderQuoteSecurityTest`: 3/3 bestanden
  * `DeleteUnverifiedUsersTest`: 3/3 bestanden
  * `ContactFormSecurityTest`: 2/2 bestanden
* **Regressionstests:** Keine Regressionen in Bestandsmodulen festgestellt.
* **PHP Static Lint:** 0 Syntaxfehler in allen Routen, Modellen und Controllern.
