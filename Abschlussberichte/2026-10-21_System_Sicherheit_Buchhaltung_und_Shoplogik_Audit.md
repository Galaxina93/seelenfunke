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
| [`app/Models/Customer/Customer.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Customer/Customer.php) | `google_id` und `email_verified_at` in `$fillable` aufgenommen; `orders()`-Relation ergänzt |
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
| [`app/Livewire/Customer/CustomerOrdersComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerOrdersComponent.php) | IDOR-Schutz: Strikte Kundenbindung für Bestellungen, Positionen, Rechnungen & Previews |
| [`app/Livewire/Customer/CustomerTicketsComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerTicketsComponent.php) | IDOR-Schutz: Kundenbindung für Tickets & Dateityp-Validierung für Ticket-Anhänge |
| [`app/Services/CartService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartService.php) | Absicherung gegen negative Artikelmengen, Session-Validierung für Angebote & Gutscheinschutz |
| [`app/Livewire/Shop/Accounting/AccountingFixCosts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingFixCosts.php) | MIME-Typ-Validierung für Fixkosten-Belege und Verträge |
| [`app/Livewire/Shop/Accounting/AccountingQuickEntry.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingQuickEntry.php) | MIME-Typ-Validierung für Schnellbeleg-Uploads |
| [`app/Livewire/Shop/Accounting/AccountingVariableCosts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingVariableCosts.php) | MIME-Typ-Validierung für Beleg-Uploads variabler Kosten |
| [`app/Livewire/Shop/Accounting/AccountingBank.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingBank.php) | MIME-Typ-Validierung für Belegzuordnung im Bankabgleich |
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
| [`tests/Feature/Api/BackOfficeApiSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/BackOfficeApiSecurityTest.php) | Neue Testsuite für Back-Office & KI API Autorisierung (4 Tests, 23 Assertions) |
| [`tests/Feature/Accounting/TaxExportSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/TaxExportSecurityTest.php) | Neue Testsuite für Steuerexport Traversal- & Format-Prüfung (3 Tests, 3 Assertions) |
| [`tests/Feature/Auth/GoogleAuthFillableTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Auth/GoogleAuthFillableTest.php) | Neue Testsuite für Mass-Assignment von `google_id` und Verifizierung (2 Tests, 4 Assertions) |
| [`tests/Feature/LandingPageSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/LandingPageSecurityTest.php) | Neue Testsuite für Landing-Page Zugriffsschutz & Entwurfs-Status (3 Tests, 4 Assertions) |
| [`tests/Feature/Livewire/Frontend/EmergencyProtocolTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Frontend/EmergencyProtocolTest.php) | Neue Testsuite für Notfallprotokoll (4 Tests, 11 Assertions) |
| [`tests/Feature/Customer/CustomerOrdersSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Customer/CustomerOrdersSecurityTest.php) | Neue Testsuite für IDOR-Schutz im Kundenportal für Bestellungen (4 Tests, 8 Assertions) |
| [`tests/Feature/Customer/CustomerTicketsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Customer/CustomerTicketsSecurityTest.php) | Neue Testsuite für IDOR- und Upload-Schutz bei Support-Tickets (3 Tests, 6 Assertions) |
| [`tests/Feature/Cart/CartQuantitySecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Cart/CartQuantitySecurityTest.php) | Neue Testsuite für Warenkorb-Mengensicherheit (2 Tests, 4 Assertions) |
| [`tests/Feature/Marketing/NewsletterSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/NewsletterSecurityTest.php) | Neue Testsuite für Newsletter-Sicherheit & Mailable-Resolution (3 Tests, 7 Assertions) |
| [`tests/Feature/Product/ProductReviewSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductReviewSecurityTest.php) | Neue Testsuite für File Deletion Schutz bei Bewertungen (2 Tests, 6 Assertions) |
| [`tests/Feature/Order/OrderQuoteSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderQuoteSecurityTest.php) | Neue Testsuite für Angebotssicherheit, Mengenhärtung & Session-Verfall (3 Tests, 8 Assertions) |
| [`tests/Feature/System/DeleteUnverifiedUsersTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/DeleteUnverifiedUsersTest.php) | Neue Testsuite für den sicheren Benutzerbereinigungs-Cronjob (3 Tests, 7 Assertions) |
| [`tests/Feature/Support/ContactFormSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Support/ContactFormSecurityTest.php) | Neue Testsuite für Honeypot- & Spam-Schutz im Kontaktformular (2 Tests, 6 Assertions) |

---

## 4. Test- & Verifikationsergebnisse

In der Docker-Umgebung (`mein_php_server`, PHP 8.4) wurden alle Tests erfolgreich validiert:
* **Sicherheits- & Autorisierungs-Testsuiten (Gesamt):** 67 Tests bestanden (245 Assertions, 0 Fehler)
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
