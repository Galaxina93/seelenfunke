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

---

## 3. Übersicht aller modifizierten Dateien

| Datei | Art der Änderung |
|---|---|
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
| [`app/Livewire/Customer/CustomerTicketsComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerTicketsComponent.php) | IDOR-Schutz: Kundenbindung für Tickets & Dateityp-Validierung für Ticket- und Chat-Anhänge |
| [`app/Services/CartService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartService.php) | Absicherung gegen negative Artikelmengen, Session-Validierung für Angebote & Gutscheinschutz |
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
| [`app/Services/InvoiceService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/InvoiceService.php) | Vollständige Beseitigung der öffentlichen Rechnungsspiegelung auf `public` Disk |
| [`app/Services/DhlService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/DhlService.php) | Umstellung aller DHL-Labels auf geschützten privaten lokalen Speicher |
| [`app/Livewire/Shop/Order/OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php) | Kein Lagerbestand-Restore für personalisierte Ware; Herzensprojekt-Spenden-Rückbuchung bei Storno/Erstattung |
| [`app/Livewire/Shop/Order/OrderRevocationForm.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderRevocationForm.php) | Bot-Honeypot und IP-Rate-Limiting (5/10min) für elektronische Widerrufe |
| [`resources/views/livewire/shop/order/order-revocation-form.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-revocation-form.blade.php) | Unsichtbares Honeypot-Feld im Widerrufsformular |
| [`routes/partials/admin_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/admin_routes.php) | Strikte Beleg-Whitelist & Stored-XSS-Schutz bei E-Mail-Anhängen |
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
* **Sicherheits- & Autorisierungs-Testsuiten (Gesamt):** 93 Tests bestanden (283 Assertions, 0 Fehler)
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
