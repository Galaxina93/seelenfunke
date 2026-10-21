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

### 2.56 [MARKETING GUTSCHEIN VALIDIERUNG & AUTH-GUARD] Autorisierung & serverseitige Wertebereichsprüfung für Aktionsgutscheine
* **Dateien:** [`app/Livewire/Shop/Marketing/MarketingVoucher.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingVoucher.php)
* **Befund:** 
  1. In `MarketingVoucher` fehlte in `mount()` jegliche Authentifizierungsprüfung; unberechtigte Nutzer konnten Gutschein-Konfigurationen und Codes manipulieren.
  2. In `saveManualCoupon()` fehlte eine Obergrenzen-Prüfung für Prozent-Gutscheine (`max:100`), sodass Rabatte von >100% oder negative Mindestbestellwerte/Nutzungslimits erfasst werden konnten.
* **Lösung:** 
  1. `mount()` mit `Auth::guard('admin')->check() || Auth::guard('employee')->check()` abgesichert (403 bei fehlenden Rechten).
  2. Strenge Validierung in `saveManualCoupon()` implementiert: `manual_code` (alpha_dash, 3-50 Zeichen), `manual_type` (in:fixed,percent), `manual_value` (min:0.01, bei percent max:100), `min_order_value` (min:0), `usage_limit` (min:1) und `valid_until` (after:now).
* **Testabdeckung:** [`tests/Feature/Marketing/MarketingVoucherSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/MarketingVoucherSecurityTest.php) (4 Tests, 11 Assertions).

### 2.57 [MANAGEMENT MODULE AUTORISIERUNGSSCHUTZ TEIL 1] Absicherung von E-Mails, Kontakten & Aufgaben gegen unberechtigte Livewire-Mounts
* **Dateien:** [`app/Livewire/Shop/Management/ManagementEMails.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementEMails.php), [`app/Livewire/Shop/Management/ManagementContacts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementContacts.php), [`app/Livewire/Shop/Management/ManagementTask.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementTask.php)
* **Befund:** Die internen Betriebsführungs-Komponenten für Webmail-Postfächer, sensible Geschäftskontakte und interne Betriebsaufgaben besaßen in ihren Livewire-Klassen keine Autorisierungsprüfung in `mount()`. Unberechtigte Anfragen an den `/livewire/update`-Endpunkt hätten sensible geschäftliche Kommunikation und Betriebsdaten einsehen oder manipulieren können.
* **Lösung:** In allen drei Komponenten wurde in `mount()` ein strikter Guard-Check integriert: `if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) abort(403);`.
* **Testabdeckung:** [`tests/Feature/Management/ManagementAccessSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Management/ManagementAccessSecurityTest.php) (3 Tests, 15 Assertions).

### 2.58 [MANAGEMENT MODULE AUTORISIERUNGSSCHUTZ TEIL 2] Absicherung von Kalender, Routinen & Einkaufsliste gegen unberechtigte Livewire-Mounts
* **Dateien:** [`app/Livewire/Shop/Management/ManagementCalender.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementCalender.php), [`app/Livewire/Shop/Management/ManagementRoutine.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementRoutine.php), [`app/Livewire/Shop/Management/ManagementShoppingList.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementShoppingList.php)
* **Befund:** Auch für den internen Betriebskalender, die täglichen/wöchentlichen Fertigungsroutinen und die betriebliche Einkaufsliste fehlte in `mount()` der Autorisierungscheck.
* **Lösung:** Livewire-Lifecycle-Guard in `mount()` für Admin und Mitarbeiter hinterlegt (`abort(403)` für Gäste und Kunden).
* **Testabdeckung:** [`tests/Feature/Management/ManagementAccessSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Management/ManagementAccessSecurityTest.php) (3 Tests, 15 Assertions).

### 2.59 [SUPPORT BACKEND AUTORISIERUNGSSCHUTZ] Absicherung von Support-Tickets & Live-Chats vor unbefugtem Livewire-Zugriff
* **Dateien:** [`app/Livewire/Shop/Support/SupportTicket.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportTicket.php), [`app/Livewire/Shop/Support/SupportChats.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportChats.php)
* **Befund:** Die Support-Backend-Komponenten zur Bearbeitung eingehender Kundenanfragen, interner Vermerke und Live-Chat-Sitzungen besaßen in ihren `mount()`-Methoden keine Berechtigungsprüfung.
* **Lösung:** In beiden Komponenten wurde eine strikte Prüfung auf `Auth::guard('admin')->check() || Auth::guard('employee')->check()` in `mount()` ergänzt.
* **Testabdeckung:** [`tests/Feature/Support/SupportComponentsAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Support/SupportComponentsAuthorizationTest.php) (3 Tests, 8 Assertions).

### 2.60 [PRODUKTVERWALTUNG AUTORISIERUNGSSCHUTZ] Absicherung von Neuanlage, Lieferanten, Bruchware & Verpackungskonfiguration
* **Dateien:** [`app/Livewire/Shop/Product/ProductCreate.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCreate.php), [`app/Livewire/Shop/Product/ProductSuppliers.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductSuppliers.php), [`app/Livewire/Shop/Product/ProductFracture.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductFracture.php), [`app/Livewire/Shop/Product/ProductPackagingConfigurator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductPackagingConfigurator.php)
* **Befund:** Die Produktverwaltungskomponenten für die Produktneuanlage, Lieferantenstammdaten, Bruchwarenbuchung und den Verpackungskonfigurator verfügten über keine Autorisierungsprüfung in `mount()`. Unberechtigte Nutzer hätten Artikel anlegen, Bruchwaren verbuchen oder Lieferantenkonditionen einsehen können.
* **Lösung:** Alle vier Komponenten prüfen in `mount()` zwingend die Admin- oder Mitarbeiter-Rolle und brechen bei fehlenden Rechten mit 403 ab.
* **Testabdeckung:** [`tests/Feature/Product/ProductManagementAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductManagementAuthorizationTest.php) (3 Tests, 12 Assertions).

### 2.61 [MENGENRECHNER PARAMETER-MANIPULATION & MAIL-THROTTLING] Absicherung gegen negative Mengen, Array-Key-Fehler & Mail-Bombing
* **Dateien:** [`app/Livewire/Shop/Product/ProductCalculator/ProductCalculator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCalculator/ProductCalculator.php)
* **Befund:** 
  1. In `saveItemFromConfigurator` wurde `$this->dbProducts[$data['product_id']]` ungesichert ohne `isset()` abgefragt, was bei ungültigen IDs zu PHP-Crashes führte.
  2. Die Stückzahl `$data['qty']` bzw. `$item['qty']` wurde weder in `saveItemFromConfigurator` noch in `validateCartItems` validiert; negative Mengen konnten eingeschleust werden, was zu negativen Zwischensummen und manipulierte Anfragesummen führte.
  3. Bei `submit()` wurden zwei E-Mails via `Mail::send` verschickt und Datenbankanfragen erzeugt – völlig ohne Rate-Limiter (Gefahr von Mail-Bombing und Denial-of-Service).
* **Lösung:** 
  1. Sichere Existenzprüfung von `$data['product_id']` vor Weiterverarbeitung.
  2. Forcierung von `$qty = max(1, min(10000, (int)$qty))` in `saveItemFromConfigurator` und `validateCartItems`.
  3. IP-basiertes Rate-Limiting (`RateLimiter::tooManyAttempts('calculator-submit:' . request()->ip(), 5)`) mit 10-Minuten-Decay und Absicherung gegen negative Gesamtbeträge.
* **Testabdeckung:** [`tests/Feature/Product/ProductCalculatorSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductCalculatorSecurityTest.php) (3 Tests, 7 Assertions).

### 2.62 [PRODUKTVARIANTEN & STEUERKLASSEN LIVEWIRE-GUARD] Autorisierungsschutz & Wertebereichsbereinigung für Varianten und Steuersätze
* **Dateien:** [`app/Livewire/Shop/Product/ProductVariants.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductVariants.php), [`app/Livewire/Shop/Product/ProductTax.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductTax.php), [`resources/views/livewire/shop/product/product-variants.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-variants.blade.php)
* **Befund:** 
  1. Beide Livewire-Komponenten besaßen keinen Autorisierungscheck in `mount()`, sodass Gäste oder Kunden per Livewire-Aufruf Produktvarianten überschreiben oder Steuerklassen (z. B. auf 0% Steuersatz) abändern konnten.
  2. In `saveVariants` fehlte eine Bereinigung negativer Preise und Lagerbestände.
  3. Im Blade-Template führte das Fehlen des `attributes`-Schlüssels zu ungefangenen Array-Zugriffsfehlern.
* **Lösung:** 
  1. Autorisierungsprüfung in `mount()` auf Admin- und Mitarbeiter-Guard hinterlegt (`abort(403)`).
  2. Bereinigung von Variantenpreisen (`max(0, ...)`) und Lagerbeständen (`max(0, (int)...)`).
  3. Null-sicherer Blade-Zugriff `@foreach($variant['attributes'] ?? [] as $key => $val)`.
* **Testabdeckung:** [`tests/Feature/Product/ProductVariantsAndTaxSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductVariantsAndTaxSecurityTest.php) (4 Tests, 9 Assertions).

### 2.63 [STAFFELPREISE MULTI-TENANCY IDOR & DISCOUNT-VALIDIERUNG] Absicherung gegen fremde Staffelpreis-Löschung & unzulässige Rabattsätze
* **Dateien:** [`app/Livewire/Shop/Product/ProductTierPricing.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductTierPricing.php)
* **Befund:** 
  1. `ProductTierPricing` besaß keinen Zugriffsschutz in `mount()`.
  2. In `removeTier($tierId)` wurde `ProductTierPrice::destroy($tierId)` ohne Prüfung der Produktzugehörigkeit aufgerufen – ein Angreifer konnte beliebige Staffelpreise anderer Produkte fremder Händler löschen (IDOR).
  3. In `updateTier($tierId)` fehlte ebenfalls das Scoping auf das aktuelle Produkt sowie die Validierung von Rabattsätzen (>100% Rabatt führte zu negativen Verkaufspreisen).
* **Lösung:** 
  1. `Auth::guard('admin')->check() || Auth::guard('employee')->check()` in `mount()` erzwungen.
  2. Scoping aller Lösch- und Aktualisierungsoperationen strikt auf `$this->product->tierPrices()->where('id', $tierId)`.
  3. Begrenzung von Rabattprozenten auf `0% - 100%` (`max(0, min(100, ...))`) und Mindestmenge >= 1.
* **Testabdeckung:** [`tests/Feature/Product/ProductTierPricingSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductTierPricingSecurityTest.php) (4 Tests, 8 Assertions).

### 2.64 [PRODUKTKATALOG BACKEND-AUTORISIERUNG] Absicherung von Attributen, Kategorien, Vorlagen und Nischen-Crawler
* **Dateien:** [`app/Livewire/Shop/Product/ProductAttributes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductAttributes.php), [`app/Livewire/Shop/Product/ProductCategories.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCategories.php), [`app/Livewire/Shop/Product/ProductTemplates.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductTemplates.php), [`app/Livewire/Shop/Product/ProductCrawler.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCrawler.php)
* **Befund:** 
  1. `ProductAttributes` und `ProductCategories` boten ungeschützte Livewire-Schnittstellen zur globalen Attribut- und Kategorie-Erstellung und -Löschung.
  2. `ProductTemplates` besaß keine `mount()`-Methode, sodass Vorlagen unbefugt gelöscht oder manipuliert werden konnten.
  3. `ProductCrawler` besaß keinen Guard in `mount()` und fiel in `saveCurrentRun()` auf `auth('admin')->id() ?? 1` zurück, wodurch anonyme Crawler-Läufe als Hauptadministrator verbucht und Tabellen bereinigt werden konnten (`truncate()`).
* **Lösung:** Allen vier Komponenten wurde in `mount()` ein strikter Autorisierungscheck (`admin` oder `employee`) vorgeschaltet und der unsichere Default-Fallback auf Admin ID 1 eliminiert.
* **Testabdeckung:** [`tests/Feature/Product/ProductCatalogBackendAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductCatalogBackendAuthorizationTest.php) (3 Tests, 12 Assertions).

### 2.65 [KUNDEN-SICHERHEIT & MARKETING-BLOG ZUGRIFFSSCHUTZ] Absicherung von Zwangspasswort-Änderung, Kunden-Rechnungen und Blogverwaltung
* **Dateien:** [`app/Livewire/Customer/CustomerForcePasswordChange.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerForcePasswordChange.php), [`app/Livewire/Customer/CustomerInvoicesComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerInvoicesComponent.php), [`app/Livewire/Shop/Marketing/MarketingBlog.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingBlog.php)
* **Befund:** 
  1. In `CustomerForcePasswordChange::submit()` wurde `$customer` ungeprüft dereferenziert (`Call to a member function update() on null` bei unauthentifizierten Requests).
  2. `CustomerInvoicesComponent` besaß keinen Guard in `mount()`, sodass Gastaufrufe bei `$user->id` mit 500er-Fehlern abstürzten.
  3. `MarketingBlog` besaß keine `mount()`-Methode, wodurch Blogposts und Kategorien unbefugt angelegt oder gelöscht werden konnten.
* **Lösung:** 
  1. `CustomerForcePasswordChange::submit()` prüft Authentifizierung und `needs_password_change` und validiert Passwörter via `same:passwordConfirm` und Längenbeschränkung.
  2. `CustomerInvoicesComponent` bricht bei fehlendem Kunden-Login sofort ab (`mount()` Guard).
  3. `MarketingBlog` sichert `mount()`, Beitrags- und Kategorieaktionen strikt für Admins und Mitarbeiter ab.
* **Testabdeckung:** [`tests/Feature/Customer/CustomerSecurityAndBlogAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Customer/CustomerSecurityAndBlogAuthorizationTest.php) (7 Tests, 11 Assertions).

### 2.66 [SYSTEMKONFIGURATION & PATH TRAVERSAL] Absicherung von SystemShopConfig vor Secrets-Leakage, Path Traversal und unberechtigtem Upload
* **Dateien:** [`app/Livewire/Shop/System/SystemShopConfig.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemShopConfig.php)
* **Befund:** In `SystemShopConfig` wurden in `mount()` hochkritische Konfigurationsparameter (Stripe API Keys, Hardware-PINs, Bankverbindungen, Master-Passwörter) ohne Rollenprüfung geladen. In `deleteReport()` und `getReportContent()` bestand durch ungeprüfte Dateinamen-Übergabe das Risiko von Directory Traversal (`../../etc/passwd`). Bei `saveUploads()` fehlte eine Whitelist für Datei-Extensions und MIME-Types.
* **Lösung:** Admin-Only-Guard in `mount()` integriert (`abort(403)` für Gäste, Kunden und Mitarbeiter). In `deleteReport()` und `getReportContent()` wird `basename()` erzwungen und die Datei auf das Storage-Verzeichnis gecheckt (`realpath()` bzw. Verzeichnisvalidierung). In `saveUploads()` Whitelist für `.png`, `.jpg`, `.jpeg`, `.webp`, `.svg`, `.ico` ergänzt.
* **Testabdeckung:** [`tests/Feature/System/SystemShopConfigSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemShopConfigSecurityTest.php) (4 Tests, 13 Assertions).

### 2.67 [BESTELLABWICKLUNG & WIDERRUF] GoBD- und Lager-konforme Widerrufsabwicklung und Autorisierung für Angebote
* **Dateien:** [`app/Livewire/Shop/Order/OrderRevocations.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderRevocations.php), [`app/Livewire/Shop/Order/OrderQuoteRequests.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderQuoteRequests.php)
* **Befund:** In `OrderRevocations::markAsProcessed()` wurde die zugehörige Bestellung nicht über die zentrale `$order->handleCancellation()` storniert, wodurch Bestandsrückbuchungen unvollständig waren, Spendenbeiträge und Werbegutschriften aktiv blieben und Gutscheinsaldi nicht restituiert wurden. Zudem fehlte der Autorisierungscheck in `mount()`. In `OrderQuoteRequests` fehlte der Schutz in `mount()`, `convertToOrder()`, `markAsRejected()` und `downloadLaserFile()`.
* **Lösung:** Autorisierungsschutz (`admin` oder `employee`) in beiden Komponenten implementiert. `markAsProcessed()` triggert nun `$order->handleCancellation()`, storniert die Bestellung GoBD-konform, bucht Standardartikel ins Lager zurück, belässt personalisierte Laserprodukte strikt unberührt (Ausschuss-Regel) und aktualisiert den Zahlungsstatus bei Bedarf auf `refunded`.
* **Testabdeckung:** [`tests/Feature/Order/OrderRevocationProcessingStockTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderRevocationProcessingStockTest.php) (2 Tests, 6 Assertions).

### 2.68 [BUCHHALTUNG & FINANZEN] Zugriffsschutz für Gutschriften und Rechnungs-Vorschau
* **Dateien:** [`app/Livewire/Shop/Accounting/AccountingCredit.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingCredit.php), [`app/Livewire/Shop/Accounting/AccountingInvoicePreview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingInvoicePreview.php)
* **Befund:** `AccountingCredit` und `AccountingInvoicePreview` besaßen keine Autorisierungsprüfung in `mount()`, `cancelInvoice()` und `downloadPdf()`. Dadurch konnten unbefugte Kunden oder Gäste Rechnungs-PDFs einsehen, herunterladen oder Rechnungen stornieren.
* **Lösung:** Admin- und Employee-Guards in `mount()` und Aktionsmethoden integriert.
* **Testabdeckung:** [`tests/Feature/Accounting/AccountingCreditAndInvoicePreviewSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/AccountingCreditAndInvoicePreviewSecurityTest.php) (3 Tests, 9 Assertions).

### 2.69 [SYSTEM-INFRASTRUKTUR & PROTOKOLLE] Autorisierung für Versandarten, Lieferzeiten und System-Logs
* **Dateien:** [`app/Livewire/Shop/System/SystemShipping.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemShipping.php), [`app/Livewire/Shop/System/SystemDeliveryTimes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemDeliveryTimes.php), [`app/Livewire/Shop/System/SystemLogs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemLogs.php)
* **Befund:** `SystemShipping`, `SystemDeliveryTimes` und `SystemLogs` besaßen keine `mount()`-Prüfungen. Unbefugte konnten Versandkosten manipulieren, Lieferzeiten verändern oder vertrauliche Systemprotokolle und Fehlermeldungen (SystemLogs) einsehen und löschen.
* **Lösung:** `SystemShipping` und `SystemDeliveryTimes` sind nun durch Mitarbeiter- und Admin-Guards geschützt. `SystemLogs` ist als hochsensible Komponente strikt auf Hauptadministratoren (`Auth::guard('admin')`) beschränkt.
* **Testabdeckung:** [`tests/Feature/System/SystemInfrastructureAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemInfrastructureAuthorizationTest.php) (3 Tests, 11 Assertions).

### 2.70 [OPERATIVE BACKEND-TOOLS] Absicherung von Support-Tools, KI-Cronjobs, Analytics & Warenkörben
* **Dateien:** [`app/Livewire/Shop/Support/SupportTelephony.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportTelephony.php), [`app/Livewire/Shop/Support/SupportContactFormComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportContactFormComponent.php), [`app/Livewire/Shop/Ai/PartialsAiWorkspace/TabCronjobs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/PartialsAiWorkspace/TabCronjobs.php), [`app/Livewire/Shop/Order/OrderAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderAnalytics.php), [`app/Livewire/Shop/Order/OrderShoppingCarts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderShoppingCarts.php), [`app/Livewire/Auth/AuthProfileDropdown.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Auth/AuthProfileDropdown.php)
* **Befund:** Verschiedene interne Backend-Komponenten wiesen fehlende Rollenprüfungen auf. Insbesondere konnten in `TabCronjobs` Hintergrund-Aufgaben unberechtigt getriggert oder manipuliert werden. In `AuthProfileDropdown` trat bei unauthentifizierten Requests ein Type-Error auf (`Cannot assign null to property $guard of type string`).
* **Lösung:** Autorisierungsprüfungen für Support- und Auftrags-Tools (`admin`/`employee`) sowie Admin-Only-Schutz für `TabCronjobs`. Nullable-Type-Deklaration `public ?string $guard = null;` und `abort(403)` für unauthentifizierte Gäste in `AuthProfileDropdown`.
* **Testabdeckung:** [`tests/Feature/Support/OperationalToolsAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Support/OperationalToolsAuthorizationTest.php) (3 Tests, 12 Assertions).

### 2.71 [SYSTEM-BACKUPS & DATENSCHUTZ] Admin-Only Autorisierung für System-Backups, Download & Löschung
* **Dateien:** [`app/Livewire/Shop/System/SystemBackups.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemBackups.php)
* **Befund:** In `SystemBackups` fehlte in `mount()`, `runTestBackup()`, `downloadBackup()` und `deleteBackup()` jeglicher Autorisierungsschutz. Dadurch konnten Gäste oder Kunden Datenbank-Backups triggern, herunterladen (inkl. Kundendaten, Passworthashes und Transaktionen) oder bestehende Backups unwiderruflich löschen. Zudem sollten Datenbank-Dumps niemals für reguläre Mitarbeiter, sondern strikt nur für Hauptadministratoren zugänglich sein.
* **Lösung:** Striktes Admin-Only Gate (`Auth::guard('admin')->check()`) in `mount()`, `runTestBackup()`, `deleteBackup()` und `downloadBackup()` implementiert.
* **Testabdeckung:** [`tests/Feature/System/SystemBackupsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemBackupsSecurityTest.php) (3 Tests, 4 Assertions).

### 2.72 [MANAGEMENT & LINKTREE-SICHERHEIT] Autorisierung für Linktree-Manager und System-Settings
* **Dateien:** [`app/Livewire/Backend/Management/ManagementLinktreeManager.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Backend/Management/ManagementLinktreeManager.php)
* **Befund:** `ManagementLinktreeManager` besaß keine Autorisierungsprüfung in `mount()`, `saveSettings()`, `deleteProfileImage()`, `save()`, `delete()`, `toggleActive()` und `updateOrder()`. Unbefugte konnten globale Shopeinstellungen manipulieren, beliebige Linktree-Einträge verändern oder Dateien in `/storage/linktree` ablegen.
* **Lösung:** Autorisierungsprüfung (`admin` oder `employee`) in `mount()` und allen Mutation-Methoden ergänzt.
* **Testabdeckung:** [`tests/Feature/Management/ManagementLinktreeSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Management/ManagementLinktreeSecurityTest.php) (3 Tests, 4 Assertions).

### 2.73 [SYSTEM-ANALYSE & DOKUMENTATION] Zugriffsschutz für Neurale Architekturanalyse
* **Dateien:** [`app/Livewire/Backend/System/SystemNeuralAnalysisIndex.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Backend/System/SystemNeuralAnalysisIndex.php)
* **Befund:** `SystemNeuralAnalysisIndex` besaß keine `mount()`-Methode und keine Rollenprüfung in `generateStructure()`. Beliebige Benutzer konnten Code-Strukturanalysen starten und Dateien im Storage erzeugen.
* **Lösung:** Autorisierungsprüfung für Mitarbeiter und Admins in `mount()` und `generateStructure()` integriert.
* **Testabdeckung:** [`tests/Feature/System/SystemNeuralAnalysisSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemNeuralAnalysisSecurityTest.php) (3 Tests, 4 Assertions).

### 2.74 [PRODUKT-MODERATION] Autorisierungsschutz für Produktbewertungs-Steuerung
* **Dateien:** [`app/Livewire/Shop/Product/ProductControlReviews.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductControlReviews.php)
* **Befund:** `ProductControlReviews` besaß keine `mount()`-Methode und keine Schutzprüfungen in `approve()`, `reject()` und `deleteReview()`. Externe Angreifer konnten Fake-Bewertungen freischalten, kritische Kundenrezensionen willkürlich verstecken oder Bewertungsmedien löschen.
* **Lösung:** Autorisierungsprüfung (`admin` oder `employee`) in `mount()`, `approve()`, `reject()` und `deleteReview()` integriert.
* **Testabdeckung:** [`tests/Feature/Product/ProductControlReviewsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductControlReviewsSecurityTest.php) (3 Tests, 4 Assertions).

### 2.75 [MARKETING-LANDINGPAGES] Autorisierung für Landingpage-Generierung, Bearbeitung und Löschung
* **Dateien:** [`app/Livewire/Shop/Marketing/MarketingLandingPages.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingLandingPages.php)
* **Befund:** `MarketingLandingPages` besaß keine `mount()`-Methode und ungeschützte Aktionsmethoden (`generateLandingPage()`, `openEditModal()`, `saveEdit()`, `toggleStatus()`, `deleteLandingPage()`, `createCustomOverrideFile()`). Unbefugte konnten dynamische Landingpages manipulieren oder Blade-Dateien anlegen und löschen.
* **Lösung:** Autorisierungsprüfungen (`admin` oder `employee`) in `mount()` und allen Aktionsmethoden integriert.
* **Testabdeckung:** [`tests/Feature/Marketing/MarketingLandingPagesSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/MarketingLandingPagesSecurityTest.php) (3 Tests, 4 Assertions).

### 2.76 [KI-ORGANISATION & ABTEILUNGEN] Autorisierungsschutz für AiCompanyStructure
* **Dateien:** [`app/Livewire/Shop/Ai/AiCompanyStructure.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiCompanyStructure.php)
* **Befund:** In `AiCompanyStructure` fehlte in `mount()` sowie in Mutationsmethoden (`saveDepartment()`, `deleteDepartment()`, `updateDepartmentOrder()`, `moveAgent()`, `editAgent()`) die Autorisierungsprüfung. Dadurch konnten unbefugte Dritte Unternehmensabteilungen modifizieren, löschen, umsortieren oder KI-Agenten verschieben.
* **Lösung:** Autorisierungsprüfung (`admin` oder `employee`) in `mount()` und allen Mutation-Methoden integriert.
* **Testabdeckung:** [`tests/Feature/Ai/AiCompanyStructureSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiCompanyStructureSecurityTest.php) (3 Tests, 4 Assertions).

### 2.77 [KI-ROLLEN & TOOL-ASSIGNMENT] Absicherung der Rollen- und Werkzeug-Konfiguration
* **Dateien:** [`app/Livewire/Shop/Ai/AiRoleManager.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiRoleManager.php)
* **Befund:** In `AiRoleManager` fehlten Autorisierungsprüfungen in `mount()`, `save()` und `delete()`. Unbefugte konnten Rollen manipulieren oder sensible System- und Finanztools beliebigen Rollen zuweisen.
* **Lösung:** Autorisierungsprüfungen für Administratoren und Mitarbeiter in `mount()`, `save()` und `delete()` implementiert.
* **Testabdeckung:** [`tests/Feature/Ai/AiRoleManagerSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiRoleManagerSecurityTest.php) (3 Tests, 4 Assertions).

### 2.78 [KI-WISSENSDATENBANK & DATEI-SICHERHEIT] Autorisierung, MIME-Whitelisting und Directory-Traversal-Schutz
* **Dateien:** [`app/Livewire/Shop/Ai/AiKnowledgeBase.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiKnowledgeBase.php)
* **Befund:** `AiKnowledgeBase` besaß keine Autorisierungsprüfung in `mount()`, `saveArticle()`, `deleteArticle()`, `updatedWikiFiles()` und `deleteWikiFile()`. In `deleteWikiFile()` war durch fehlende Pfadbereinigung Directory Traversal möglich. Beim Dateiupload (`updatedWikiFiles()`) fehlte die Validierung von Dateitypen und MIME-Types.
* **Lösung:** Autorisierungsprüfung in `mount()` und allen Aktionen. Strikte Whitelist für MIME-Types (`pdf, txt, md, docx, png, jpg, jpeg`) und `basename()`-Sanitization bei Upload und Löschung.
* **Testabdeckung:** [`tests/Feature/Ai/AiKnowledgeBaseSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiKnowledgeBaseSecurityTest.php) (3 Tests, 4 Assertions).

### 2.79 [KI-TAXONOMIE] Autorisierung für Knowledge-Base-Kategorien und Tags
* **Dateien:** [`app/Livewire/Shop/Ai/AiKnowledgeBaseCategories.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiKnowledgeBaseCategories.php), [`app/Livewire/Shop/Ai/AiKnowledgeBaseTags.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiKnowledgeBaseTags.php)
* **Befund:** `AiKnowledgeBaseCategories` und `AiKnowledgeBaseTags` besaßen keine Autorisierungsprüfungen in `mount()`, `createCategory()`, `updateCategory()`, `deleteCategory()`, `createTag()`, `updateTag()` und `deleteTag()`.
* **Lösung:** Autorisierungsprüfung (`admin` oder `employee`) in beiden Komponenten und allen Mutationsmethoden implementiert.
* **Testabdeckung:** [`tests/Feature/Ai/AiKnowledgeBaseTaxonomySecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiKnowledgeBaseTaxonomySecurityTest.php) (3 Tests, 8 Assertions).

### 2.80 [KI-AGENTEN-EDITOR & METHODEN] Absicherung von Agenten-Konfiguration und Methoden-Monitoring
* **Dateien:** [`app/Livewire/Shop/Ai/AiAgentEditor.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiAgentEditor.php), [`app/Livewire/Shop/Ai/AiMethod.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiMethod.php)
* **Befund:** `AiAgentEditor` besaß keine Autorisierung in `mount()`, `save()`, `toggleSkill()`, `deleteProfilePicture()` und `autoSaveModelOrVoice()`. Gäste oder Kunden konnten Prompts, Token, Modellkonfigurationen und Telegram-Tokens verändern. `AiMethod` besaß keine `mount()`-Methode zum Schutz vor unberechtigter Einsicht von System-Funktionsstatistiken.
* **Lösung:** Autorisierungsprüfungen (`admin` oder `employee`) in `mount()` und allen Aktionsmethoden integriert.
* **Testabdeckung:** [`tests/Feature/Ai/AiAgentEditorAndMethodSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiAgentEditorAndMethodSecurityTest.php) (3 Tests, 8 Assertions).

### 2.81 [CEO MASTER-DASHBOARD & OPERATIVE KONTROLLEN] Admin-Only Autorisierungsschutz für MasterAnalytics
* **Dateien:** [`app/Livewire/Shop/Master/MasterAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Master/MasterAnalytics.php)
* **Befund:** In `MasterAnalytics` (dem zentralen CEO Dashboard) fehlte in `mount()` sowie in allen operativen Kontrollmethoden (`updateStock()`, `uploadContract()`, `uploadSpecialReceipt()`, `approveReview()`, `rejectReview()`, `clearSecurityLogs()`, `blockIpAddress()`, `unblockIpAddress()`, `triggerAiSecurityThreatAudit()`, `flushFailedJobs()`, `fixSystem()`) der Autorisierungsschutz. Unbefugte konnten Unternehmens-Kennzahlen, Server-Health-Checks und Sicherheitswarnungen einsehen, IP-Adressen sperren, System-Reparaturen auslösen oder Queue-Jobs wipen.
* **Lösung:** Striktes Admin-Only Gate (`Auth::guard('admin')->check()`) in `mount()` und allen operativen Mutations- und Kontrollmethoden implementiert.
* **Testabdeckung:** [`tests/Feature/Master/MasterAnalyticsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Master/MasterAnalyticsSecurityTest.php) (3 Tests, 4 Assertions).

### 2.82 [KI-ANALYTICS & METRIKEN] Autorisierungsschutz für AiAnalytics Dashboard
* **Dateien:** [`app/Livewire/Shop/Ai/AiAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiAnalytics.php)
* **Befund:** `AiAnalytics` besaß keine `mount()`-Methode und war für jeden unauthentifizierten Gast frei aufrufbar. Dadurch wurden Token-Verbräuche, LLM-Inference-Kosten, Fehlerraten und Chat-Statistiken offengelegt.
* **Lösung:** Autorisierungsprüfung (`admin` oder `employee`) in `mount()` integriert (`abort(403)` für Gäste und Kunden).
* **Testabdeckung:** [`tests/Feature/Ai/AiAnalyticsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiAnalyticsSecurityTest.php) (3 Tests, 4 Assertions).

### 2.83 [LIEFERZEITEN-FEEDBACK & BLADE-NULL-SAFETY] Whitelisting von Feedback-Typen & Null-Sicherheit bei Produktanzeige
* **Dateien:** [`app/Livewire/Shop/System/SystemDeliveryDisplay.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemDeliveryDisplay.php), [`resources/views/livewire/shop/system/system-delivery-display.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/system/system-delivery-display.blade.php)
* **Befund:** In `SystemDeliveryDisplay::sendFeedback()` wurde der `$type`-Parameter ungeprüft in die Datenbank geschrieben (`DeliveryFeedback::create()`), wodurch beliebige String-Injections möglich waren. Im zugehörigen Blade-Template führte der Aufruf ohne `$product`-Model zu 500er-Fehlern (`Attempt to read property 'track_quantity' on null`).
* **Lösung:** Strikte Whitelist für `$type` (`['vacation', 'sick']`). Null-sicherer Blade-Zugriff (`@if($product && $product->track_quantity)` und `@if($product && $product->sku)`).
* **Testabdeckung:** [`tests/Feature/System/SystemDeliveryDisplaySecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemDeliveryDisplaySecurityTest.php) (3 Tests, 3 Assertions).

### 2.84 [VERPACKUNGSMATERIAL-MANAGEMENT] Method-Level Autorisierungsschutz für Verpackungskonfiguration
* **Dateien:** [`app/Livewire/Shop/Product/ProductPackagingConfigurator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductPackagingConfigurator.php)
* **Befund:** In `ProductPackagingConfigurator` waren `addMaterial()`, `saveEdit()` und `deleteMaterial()` nicht auf Methodenebene abgesichert, wodurch bei Livewire-Komponentenaufrufen ohne erneutes Mounten Manipulationsrisiken bestanden.
* **Lösung:** Autorisierungsprüfungen (`admin` oder `employee`) direkt in `addMaterial()`, `saveEdit()` und `deleteMaterial()` integriert.
* **Testabdeckung:** [`tests/Feature/Product/ProductPackagingConfiguratorSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductPackagingConfiguratorSecurityTest.php) (3 Tests, 4 Assertions).

### 2.85 [ANGEBOTS-ANNAHME & MENGENBEGRENZUNG] Integer-Overflow- und DoS-Schutz bei Angebotskalkulation
* **Dateien:** [`app/Livewire/Shop/Order/OrderQuoteAcceptance.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderQuoteAcceptance.php)
* **Befund:** In `OrderQuoteAcceptance::saveItem()` wurde die Positionsmenge lediglich mit `max(1, ...)` nach unten begrenzt. Extreme Stückzahlen führten zu Integer-Overflows, unkontrollierten Staffelrabatten und potenziellen DoS-Zuständen bei der Neuberechnung von Versand- und Steueranteilen.
* **Lösung:** Mengenbegrenzung auf Maximalwert 10.000 Einheiten geklammert (`min(10000, max(1, (int) $data['qty']))`).
* **Testabdeckung:** [`tests/Feature/Order/OrderQuoteAcceptanceQuantitySecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderQuoteAcceptanceQuantitySecurityTest.php) (1 Test, 2 Assertions).

### 2.86 [DATEI-DOWNLOAD-SERVICE, SSRF & SVG XSS] Autorisierungsgates, SSRF-Prävention und Stored-XSS-Schutz im FileDownloadService
* **Dateien:** [`app/Services/Export/FileDownloadService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Export/FileDownloadService.php)
* **Befund:** In `downloadHealthTreatmentPlanPdf` fehlte eine Berechtigungsprüfung; sensible Behandlungspläne (§ 9 DSGVO) konnten von unbefugten Dritten geladen werden. In `downloadProductAnalyticsFullReportPdf`, `downloadProductAnalyticsLucidPdf` und `downloadNicheCrawlerPdf` fehlte ein Gate für Admin/Mitarbeiter. In `downloadLaserSvg` war der Remote-Logo-Download anfällig für SSRF (Blacklist prüfte nur `127.0.0.1` und `localhost`, ignorierte jedoch Cloud-Metadata-Dienste wie `169.254.169.254` und interne RFC1918-Netze). Eingebettete SVGs wurden nicht von `<script>`-Tags oder Event-Handlern bereinigt, und `streamDownload` setzte keine restriktiven CSP- und Sniffing-Header. Zudem fehlte Null-Safety bei nicht gesetzten Positionierungsdaten (`x`, `y`).
* **Lösung:** Striktes Autorisierungs-Gate in allen Download-Methoden. Robuste SSRF-Prüfung via IP-Filter (`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`), Bereinigung von SVG-Tags (`<script>`, `<foreignObject>`, `<iframe`, `on...`), Null-Safety (`$x = (($logo['x'] ?? 50) / 100) * $widthMm`) sowie `Content-Security-Policy: default-src 'none'; sandbox` und `X-Content-Type-Options: nosniff`.
* **Testabdeckung:** [`tests/Feature/Export/FileDownloadServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Export/FileDownloadServiceSecurityTest.php) (3 Tests, 8 Assertions).

### 2.87 [EMPFEHLUNGSSYSTEM & STORNO-REVERSAL] Idempotenz-Schutz und Race-Condition-Prävention im ReferralService
* **Dateien:** [`app/Services/ReferralService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ReferralService.php)
* **Befund:** `processOrderReferralReward` und `reverseOrderReferralReward` liefen außerhalb von DB-Transaktionen ohne `lockForUpdate()`. In `reverseOrderReferralReward` wurde bei mehrfachem Aufruf (z. B. wiederholte Storno-Webhooks oder Admin-Klicks) nicht geprüft, ob die Gutschrift bereits zurückgebucht war (`personal_message` enthielt die Ordernummer weiterhin). Dadurch wurden bei wiederholten Aufrufen wiederholt je 5,00 € vom Gutscheinsaldo des Werbers abgezogen und `successful_referrals_count` dekrementiert.
* **Lösung:** `reverseOrderReferralReward` und `processOrderReferralReward` in `DB::transaction` gekapselt, Datensätze per `lockForUpdate()` gesperrt und Idempotenz-Check integriert (`if (str_contains($voucher->personal_message, "[Order {$order->order_number}: Storniert")) return false;`).
* **Testabdeckung:** [`tests/Feature/Marketing/ReferralServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/ReferralServiceSecurityTest.php) (1 Test, 11 Assertions).

### 2.88 [GAMIFICATION FUNKEN-GUTHABEN & CONCURRENCY] Atomare Transaktionen & Negativwert-Sperre in GamificationService
* **Dateien:** [`app/Services/Gamification/GamificationService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Gamification/GamificationService.php)
* **Befund:** In `addFunken()` konnten negative oder ungültige Werte (`$amount <= 0`) übergeben werden. Zudem erfolgte die Aktualisierung von `funken_balance`, `funken_total_earned` und `sparks_collected_today` ohne DB-Transaktion und `lockForUpdate()`. Parallele Anfragen (z. B. simultanes Einsammeln geheimer Funken über mehrere Browser-Tabs) konnten zu Race Conditions und Lost Updates führen.
* **Lösung:** Frühe Rückkehr bei `$amount <= 0` und Kapselung der Guthaben- und Trophäenfortschritte in `DB::transaction` mit Zeilensperre (`lockForUpdate()`).
* **Testabdeckung:** [`tests/Feature/Gamification/GamificationServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Gamification/GamificationServiceSecurityTest.php) (2 Tests, 12 Assertions).

### 2.89 [TELEFONIE & MESSENGER SCHUTZ] Rate-Limiting, Payload-Validierung & Prompt-Injection-Schutz für Twilio & Telegram
* **Dateien:** [`app/Http/Controllers/Api/TwilioCallController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/TwilioCallController.php), [`app/Http/Controllers/Api/TelegramAgentController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/TelegramAgentController.php), [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php)
* **Befund:** `/api/twilio/call-log` war ungedrosselt und unbegrenzt aufrufbar. Beliebige externe Anfragen konnten synchrone Gemini-Auswertungen triggern und Tokenkosten verursachen. In `TelegramAgentController` wurde der `first_name` aus dem Telegram-Payload ungefiltert in den internen System-Prompt eingebunden, was anfällig für Prompt-Injection war.
* **Lösung:** Rate-Limiting auf den Webhook-Routen (`throttle:30,1` bzw. `throttle:60,1`), strikte Validierung der Transcript-Arraygröße (`max:100`, String-Limit 2.000 Zeichen pro Zeile) sowie Bereinigung des Telegram-Vornamens (`preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', ...)`).
* **Testabdeckung:** [`tests/Feature/Support/TwilioCallControllerSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Support/TwilioCallControllerSecurityTest.php) (3 Tests, 9 Assertions).

### 2.90 [SYSTEM-COMMANDS & PFAD-INTEGRITÄT] Directory-Traversal-Schutz & Validierung in CLI-Befehlen
* **Dateien:** [`app/Console/Commands/ExportProjectForAI.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/ExportProjectForAI.php), [`app/Console/Commands/GenerateTaxExport.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/GenerateTaxExport.php)
* **Befund:** `ExportProjectForAI` nutzte die `--output`-Option ohne Bereinigung direkt in `base_path()`, wodurch Directory-Traversal-Schreibzugriffe möglich waren. In `GenerateTaxExport` fehlte eine Wertebereichsprüfung für Monat und Jahr, was bei fehlerhaften Eingaben zu unbehandelten Exceptions führte; zudem wurden Belege nur aus `public` statt auch aus `local` Storage gelesen.
* **Lösung:** Erzwingung von `basename()` für Zieldateien in `ExportProjectForAI`. Wertebereichs-Validierung für Monat (1–12) und Jahr (2000–2100) sowie Unterstützung beider Storage-Disks (`local` & `public`) in `GenerateTaxExport`.
* **Testabdeckung:** [`tests/Feature/Console/ExportProjectForAISecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Console/ExportProjectForAISecurityTest.php) (2 Tests, 5 Assertions).

### 2.91 [KI-AGENTEN & BESTELL-LOGIK] Bestell-Stornierung via KI-Funktionen ohne Bestands- und Schutzlogik
* **Dateien:** [`app/Services/AI/Functions/AiOrderFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiOrderFuncs.php)
* **Befund:** In `AiOrderFuncs::executeOrderUpdateStatus()` wurde bei einer Statusänderung auf `cancelled` lediglich der Datensatzstatus aktualisiert. Weder die Standardartikel-Bestandsrückbuchung, noch der Schutz vor Restauration von personalisierter Laser-Ausschussware, noch die Spenden-Stornierung bei Herzensprojekten, noch Gutschein-Reversals wurden ausgelöst.
* **Lösung:** Erfassung des vorherigen Status vor dem Update und Aufruf von `$order->handleCancellation('KI-Agent Status-Update: Stornierung', $oldStatus)`. Dadurch werden unpersonalisierte Produkte wieder auf Lager gelegt, personalisierte Laserartikel dauerhaft als Ausschuss geschützt, Spenden rückabgewickelt und Kundengutscheine wiederhergestellt.
* **Testabdeckung:** [`tests/Feature/Ai/AiOrderFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiOrderFuncsSecurityTest.php) (2 Tests, 7 Assertions).

### 2.92 [KI-AGENTEN & MARKETING] SQL-Fehler in Gutschein-Analytics & unvalidierte Gutschein-Parameter
* **Dateien:** [`app/Services/AI/Functions/AiMarketingVoucherFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMarketingVoucherFuncs.php)
* **Befund:** In `AiMarketingVoucherFuncs::marketing_voucher_get_analytics()` schlug die DB-Abfrage mit einem SQL-1054-Fehler fehl (`Unknown column 'total' in 'field list'`), da die Spalte in `order_orders` `total_price` heißt. Zudem erlaubte `marketing_voucher_create()` unbeschränkte Gutscheintypen, unzulässige Zeichenketten und prozentuale Rabatte über 100%.
* **Lösung:** Korrektur des Spaltennamens auf `sum(total_price)`. Strikte Regex-Prüfung für Gutscheincodes (`/^[A-Z0-9_\-]+$/`), Typ-Whitelisting (`fixed`, `percent`) sowie Validierung und Clamping von Rabattwerten (1–100% bzw. `> 0` für Festbeträge).
* **Testabdeckung:** [`tests/Feature/Ai/AiMarketingVoucherFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMarketingVoucherFuncsSecurityTest.php) (2 Tests, 10 Assertions).

### 2.93 [AUTHENTIFIZIERUNG & GOOGLE-OAUTH] SSRF-Gefahr via unbeschränkten Avatar-Download & Session Fixation
* **Dateien:** [`app/Http/Controllers/GoogleAuthController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/GoogleAuthController.php), [`routes/partials/global_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/global_routes.php)
* **Befund:** Profilbilder von Google-Accounts wurden via `file_get_contents($avatarUrl)` ohne Host- und Protokollvalidierung geladen, wodurch Angreifer über manipulierte OAuth-Provider-Payloads interne Netzwerkanfragen (SSRF) oder lokale Dateien auslesen konnten. Zudem fehlte `session()->regenerate()` direkt vor dem Login.
* **Lösung:** Protokoll- und Domain-Whitelisting (ausschließlich `https://*.googleusercontent.com` mit 5s-Timeout via Laravel `Http::get`), Hinzufügen von `session()->regenerate()` zur Session-Fixation-Prävention und Benennung der Callback-Route `google.callback`.
* **Testabdeckung:** [`tests/Feature/Auth/GoogleAuthControllerSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Auth/GoogleAuthControllerSecurityTest.php) (2 Tests, 7 Assertions).

### 2.94 [NOTFALL-PROTOKOLL & WORKFLOW] Unbegrenzte Step-Werte, fehlende Schlüssel-Validierung & Passwort-Länge
* **Dateien:** [`app/Livewire/Frontend/Emergency/EmergencyProtocol.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Frontend/Emergency/EmergencyProtocol.php)
* **Befund:** In `EmergencyProtocol` konnte `setStep()` auf beliebige Zahlen (auch negative oder extrem hohe Werte) gesetzt werden. `toggleTodo()` validierte die übergebenen Schlüssel nicht gegen die erlaubte Todo-Liste, wodurch willkürliche Schlüssel in die `SystemSetting`-Tabelle geschrieben werden konnten. `toggleFinanceTodo()` prüfte nicht die Existenz der Kostenstelle.
* **Lösung:** Clamping von `currentStep` zwischen 1 und 5 (`min(5, max(1, (int)$step))`). Whitelisting der Keys in `toggleTodo()` gegen `$this->allTodos`. Existenzprüfung in `toggleFinanceTodo()`. Längenbegrenzung für das Master-Passwort (max. 128 Zeichen).
* **Testabdeckung:** [`tests/Feature/Frontend/EmergencyProtocolSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Frontend/EmergencyProtocolSecurityTest.php) (2 Tests, 7 Assertions).

### 2.95 [GLOBALE ROUTEN & DATENSCHUTZ] DomPDF Remote-Asset-Aktivierung, ungeschützte Webhooks & Token-Validierung
* **Dateien:** [`app/Http/Controllers/RevocationPdfController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/RevocationPdfController.php), [`app/Http/Controllers/NewsletterController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/NewsletterController.php), [`routes/partials/global_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/global_routes.php)
* **Befund:** In `RevocationPdfController` war `'isRemoteEnabled' => true` aktiviert, obwohl alle Assets lokal vorliegen – dies eröffnete eine Angriffsfläche für Remote-SSRF/LFI im PDF-Renderer. `/newsletter/verify/{token}` akzeptierte beliebig kurze Tokens ohne Validierung. Die globalen PDF-, Abmelde- und Stripe-Webhook-Routen besaßen kein Rate-Limiting.
* **Lösung:** Deaktivierung von `isRemoteEnabled` (`false`) im DomPDF-Service. Validierung des Newsletter-Tokens (String, min. 16 Zeichen). Hinzufügen von Throttling (`throttle:30,1` für Widerrufs-PDF, Abmeldung & Newsletter-Verifikation sowie `throttle:60,1` für Stripe-Webhooks).
* **Testabdeckung:** [`tests/Feature/Global/GlobalRoutesAndControllersSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Global/GlobalRoutesAndControllersSecurityTest.php) (3 Tests, 11 Assertions).

### 2.96 [KUNDENVERIFIZIERUNG & SYNC] Fehlende User-Tabellen-Aktualisierung & DoS-Schutz bei E-Mail-Bestätigung
* **Dateien:** [`app/Http/Controllers/Customer/CustomerVerificationController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Customer/CustomerVerificationController.php), [`routes/partials/global_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/global_routes.php)
* **Befund:** In `CustomerVerificationController::verify()` wurde bei erfolgreicher Bestätigung lediglich `customer_profiles.email_verified_at` aktualisiert, nicht aber `$user->email_verified_at` in der Tabelle `customers`. Sofern ein Profil-Datensatz fehlte, blieb der Kunde unbestätigt und war durch den Cronjob `system:delete-unverified-users` von unberechtigter Löschung bedroht. Zudem fehlte auf der Gast-Route `/email/verify-customer/{id}/{hash}` ein Rate-Limiter.
* **Lösung:** Gleichzeitiges Update von `$user->update(['email_verified_at' => now()])` und Synchronisierung via `CustomerProfile::updateOrCreate(...)` sowie Ergänzung des `throttle:30,1`-Middlewares auf der Verifizierungsroute.
* **Testabdeckung:** [`tests/Feature/Customer/CustomerVerificationSyncSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Customer/CustomerVerificationSyncSecurityTest.php) (1 Test, 8 Assertions).

### 2.97 [KI-LIVE-API & WEBSOCKETS] Ungeschützte Transcript-Endpunkte, DoS-Payloads & Snapshot-Überschreibungen
* **Dateien:** [`app/Http/Controllers/AIController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/AIController.php), [`routes/api/ai.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/ai.php)
* **Befund:** In `saveLiveTranscript()` fehlte eine Längenbegrenzung für Textübertragungen (Gefahr von Memory-Exhaustion und Flooding in `ai_chat_memories`) sowie eine Validierung der Rolle (`role`), wodurch beliebige String-Injektionen und unkontrollierte Echo-Broadcasts möglich waren. Auf `/api/ai/verify-token` und `/api/ai/save-live-transcript` fehlte Rate-Limiting. In `saveCameraSnapshot()` führte die rein sekundengenaue Dateibenennung bei gleichzeitigen Snapshots zu Race-Conditions und Dateiüberschreibungen.
* **Lösung:** Längenbeschränkung (`content` max. 50.000 Zeichen, `session_id` max. 100), Whitelisting der Rolle (`user`, `assistant`, `system`), Hinzufügen von Entropietokens (`Str::random(8)`) bei Kamera-Snapshots sowie Throttling (`throttle:120,1` bzw. `throttle:60,1`) auf den API-Routen.
* **Testabdeckung:** [`tests/Feature/Ai/AiLiveTranscriptSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiLiveTranscriptSecurityTest.php) (3 Tests, 8 Assertions).

### 2.98 [KI-DOKUMENTE & SSRF] DomPDF Remote-Asset-Schutz & E-Mail-Validierung in Laser- und Reiseberichten
* **Dateien:** [`app/Services/AI/Functions/AiLaserFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiLaserFuncs.php), [`app/Services/AI/Functions/AiHolidayPlannerFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiHolidayPlannerFuncs.php)
* **Befund:** In `executeLaserGeneratePdfAndMail()` und `executeHolidayGeneratePdfPlan()` wurden E-Mail-Empfänger ohne RFC-2822-Validierung (`FILTER_VALIDATE_EMAIL`) verwendet, und in den Betreffzeilen konnten Newlines (`\r\n`) zu Header-Injection führen. Zudem waren die DomPDF-Renderer nicht gegen SSRF/LFI gehärtet (`isRemoteEnabled` war nicht explizit deaktiviert). Dateinamen-Generierung konnte bei Sonderzeichen im Titel kollabieren.
* **Lösung:** Stripping von Zeilenumbrüchen in Betreffzeilen, E-Mail-Validierung via `filter_var(..., FILTER_VALIDATE_EMAIL)` mit Fallback auf die System-E-Mail, Deaktivierung von Remote-Assets (`setOption(['isRemoteEnabled' => false])`), sichere Slug-Fallbacks und Nutzung der Laravel-`Storage`-Disk.
* **Testabdeckung:** [`tests/Feature/Ai/AiLaserAndHolidaySecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiLaserAndHolidaySecurityTest.php) (2 Tests, 4 Assertions).

### 2.99 [KI-KOMMUNIKATION & KONTAKTE] Header-Injection-Schutz, E-Mail-Validierung & String-Limits bei Kontakten
* **Dateien:** [`app/Services/AI/Functions/AiMailFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMailFuncs.php), [`app/Services/AI/Functions/AiContactFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiContactFuncs.php)
* **Befund:** In `executeSendEmail()` wurden Empfänger-Adressen nur auf naive Dummy-Begriffe ('example') geprüft, ungültige Formate führten zu Mailer-Abstürzen und Betreffzeilen waren nicht gegen CRLF-Header-Injection geschützt. In `AiContactFuncs` konnten KI-Agenten beliebig lange Namens- und Informations-Strings (`first_name`, `last_name`, `content`) anlegen, was zu MySQL-1406-Fehlern (`Data too long`) führte.
* **Lösung:** Strikte `FILTER_VALIDATE_EMAIL`-Prüfung und CRLF-Entfernung in `AiMailFuncs`. Feldlängen-Truncation (`first_name`/`last_name` max. 100 Zeichen, `relation_type` max. 50, Notiz-Inhalt max. 5.000 Zeichen) sowie Ergebnismengenbegrenzung (`limit(100)`) in `AiContactFuncs`.
* **Testabdeckung:** [`tests/Feature/Ai/AiMailAndContactSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMailAndContactSecurityTest.php) (2 Tests, 10 Assertions).

### 2.100 [LIEFERANTEN & DATENINTEGRITÄT] SQL-Column-Crash ('address'), Wertebereichs-Clamping & Lösch-Integrität
* **Dateien:** [`app/Services/AI/Functions/AiSuppliersFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiSuppliersFuncs.php)
* **Befund:** In `executeSupplierCreate()` und `executeSupplierUpdate()` wurde das Feld `address` übergeben, was zu einem SQL-1054-Absturz (`Unknown column 'address' in 'field list'`) führte, da die Tabelle stattdessen `street`, `zip`, `city` etc. nutzt. Zudem waren negative Vorlaufzeiten (`lead_time_*_days`) und beliebige Transportmethoden zulässig. `executeSupplierDelete()` löschte Lieferanten, selbst wenn noch aktive Produkte oder Bruchwareneinträge mit ihnen verknüpft waren, was verwaiste Fremdschlüssel hinterließ.
* **Lösung:** Mapping von `address` auf die korrekte Spalte `street`. Clamping der Vorlaufzeiten auf 0 bis 3.650 Tage. Whitelisting von `shipping_method` (`land`, `air`, `sea`, `train`). Hinzufügen einer relationalen Integritätsprüfung vor dem Löschen (`Product` & `ProductLoss`).
* **Testabdeckung:** [`tests/Feature/Ai/AiSuppliersSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiSuppliersSecurityTest.php) (2 Tests, 11 Assertions).

### 2.101 [PREISBERECHNUNG & BUCHHALTUNG] Division-by-Zero-Schutz & Wertebereichsprüfung im PriceCalcService
* **Dateien:** [`app/Services/PriceCalcService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/PriceCalcService.php)
* **Befund:** In `PriceCalcService` fehlte eine Wertebereichsvalidierung für Steuersätze und Beträge. Ein Steuersatz von `<= -100%` führte zu einer unbehandelten `DivisionByZeroError`-Exception (`1 + (-100/100) = 0`). Zudem führten negative Preise zu fehlerhaften Steuerdifferenzen.
* **Lösung:** Clamping des Steuersatzes auf `max(0.0, min(100.0, $taxRate))` und der Beträge auf `max(0, $price)`. Dadurch wird Division durch Null mathematisch ausgeschlossen und die Buchhaltungsintegrität gewahrt.
* **Testabdeckung:** [`tests/Feature/Price/PriceCalcServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Price/PriceCalcServiceSecurityTest.php) (2 Tests, 9 Assertions).

### 2.102 [VERSANDKOSTEN & CHECKOUT] Ländercode-Normalisierung & Wertebereichsabsicherung im ShippingCalculator
* **Dateien:** [`app/Services/ShippingCalculatorService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ShippingCalculatorService.php)
* **Befund:** In `calculateShippingCost()` wurde `$countryCode === 'DE'` strikt case-sensitiv verglichen. Übergaben von kleingeschriebenen Ländercodes (z.B. `'de'` aus Frontend- oder API-Payloads) führten dazu, dass Kunden aus Deutschland trotz Erreichen des Schwellenwerts keinen kostenlosen Versand erhielten und in teure Auslands-Fallbacks gerieten. Zudem waren negative Warenkorbwerte und Gewichte unbereinigt.
* **Lösung:** Automatische Normalisierung des Ländercodes (`strtoupper(trim($countryCode))`) sowie Clamping von Warenkorbwert (`max(0, ...)`) und Gesamtgewicht (`max(0.0, ...)`).
* **Testabdeckung:** [`tests/Feature/Shipping/ShippingCalculatorSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Shipping/ShippingCalculatorSecurityTest.php) (2 Tests, 8 Assertions).

### 2.103 [STEUEREXPORT & DATENSCHUTZ] Öffentlicher Download-Leak von Finanzdaten & DomPDF-Härtung
* **Dateien:** [`app/Services/FinancialService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/FinancialService.php)
* **Befund:** In `FinancialService::generateTaxExport()` wurde das vollständige Steuerexport-ZIP-Archiv (inkl. aller Banktransaktionen, Kundenrechnungen, Einnahmen und Belegen) im öffentlich erreichbaren Web-Pfad `app/public/systemsteuerung/exports/` abgelegt. Dadurch konnte jeder externe Angreifer über vorhersehbare Dateinamen (`Finanzbericht_YYYY_M.zip`) hochsensible Unternehmensdaten herunterladen. Zudem wurden Belege nur von der `public`-Disk gelesen und DomPDF-Assets waren nicht gegen SSRF gehärtet.
* **Lösung:** Verlagerung des Zielpfads in den privaten Speicher (`app/private/exports/`), Durchsuchung beider Disks (`local` & `public`) nach Belegen, Validierung von Monat (1–12) und Jahr (2000–2100) sowie Deaktivierung von Remote-Assets in DomPDF (`isRemoteEnabled => false`).
* **Testabdeckung:** [`tests/Feature/Accounting/FinancialServiceTaxExportSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/FinancialServiceTaxExportSecurityTest.php) (1 Test, 3 Assertions).

### 2.104 [KI-WIKI & DATENINTEGRITÄT] Falsche Duplikatserkennung & versehentliches Löschen im AiBrain
* **Dateien:** [`app/Services/AI/Functions/AiBrainFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiBrainFuncs.php)
* **Befund:** In `executeDeleteBrainEntry()` suchte die Löschabfrage per `%query%` über die gesamte `content`-Spalte aller Wiki-Einträge. Häufige Füllwörter oder kurze Suchanfragen führten zum unbeabsichtigten Löschen völlig unbeteiligter Wissenseinträge. In `executeSaveToBrain()` prüfte der Duplikat-Check `content like %content%` in umgekehrter Substring-Richtung, wodurch kurze Notizen fälschlicherweise als Duplikate langer Artikel abgewiesen wurden.
* **Lösung:** Priorisierung des Titel-Matches bei Löschung und Mindestlänge (>= 4 Zeichen) für Content-Suchen. Präzisierung der Duplikatsprüfung auf Titel und exakten Inhalt bei substantieller Länge. Längenbegrenzung für Titel (255) und Inhalt (50.000).
* **Testabdeckung:** [`tests/Feature/Ai/AiBrainFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiBrainFuncsSecurityTest.php) (2 Tests, 5 Assertions).

### 2.105 [EINKAUFSLISTE & SQL-WILDCARDS] Wildcard-Injektion & Längenbeschränkung in Shopping-Funktionen
* **Dateien:** [`app/Services/AI/Functions/AiShoppingListFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiShoppingListFuncs.php)
* **Befund:** In `executeAddShoppingItem()` wurde die Existenzprüfung mittels `where('name', 'like', $args['name'])` durchgeführt. Die Übergabe von `%` oder `_` matchte und manipulierte ungewollt den ersten existierenden Einkaufsartikel in der Datenbank. Zudem fehlten Längenbegrenzungen für Artikel- und Kategorienamen.
* **Lösung:** Umstellung auf exakten Namensvergleich (`=`), Truncation von Produktnamen auf 100 Zeichen und Kategorienamen auf 50 Zeichen.
* **Testabdeckung:** [`tests/Feature/Ai/AiShoppingListFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiShoppingListFuncsSecurityTest.php) (1 Test, 4 Assertions).

### 2.106 [DATEIBEREINIGUNG & WARENKORB] SQL-Wildcard-Escaping, Chunking & Pfad-Normalisierung im CartFileCleanupService
* **Dateien:** [`app/Services/CartFileCleanupService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartFileCleanupService.php)
* **Befund:** In `isFileUsedInOrders()`, `isFileUsedInQuotes()` und `isFileUsedInCarts()` wurden Dateinamen ohne Escaping in `LIKE '%...%'` eingebunden. Dateinamen mit `%` oder `_` führten zu False-Positives und blockierten die Bereinigung unbeteiligter Uploads. In `cleanupAbandonedCarts()` lud `get()` alle verlassenen Warenkörbe unbegrenzt in den Arbeitsspeicher (Gefahr von Memory Exhaustion). In `isManagedPath()` und `deleteFileSafely()` fehlte die Normalisierung von Windows-Backslashes.
* **Lösung:** Escaping aller SQL-Wildcards mit `addcslashes($basename, '%_\\')`, Umstellung auf speicherschonendes `chunkById(100)` und einheitliche Pfad-Normalisierung (`str_replace('\\', '/', trim($path, " \t\n\r\0\x0B/"))`).
* **Testabdeckung:** [`tests/Feature/Cart/CartFileCleanupServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Cart/CartFileCleanupServiceSecurityTest.php) (3 Tests, 9 Assertions).

### 2.107 [KONFIGURATOR & 3D/2D-STABILITÄT] Wertebereichs- & Koordinaten-Clamping gegen WebGL- & Canvas-Crashes
* **Dateien:** [`app/Services/ConfiguratorService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ConfiguratorService.php)
* **Befund:** In `mergeWithDefaults()` wurden gespeicherte Konfigurationen ungeprüft mit den Standardwerten zusammengeführt. Extreme, negative oder fehlerhafte Werte für Gravurbereiche (`area_left`, `area_top`, `area_width <= 0`, `area_height <= 0`) sowie Skalierungsfaktoren (`model_scale <= 0`, `engraving_scale`) führten im Frontend zu Three.js-Matrix-Inversionsfehlern, Divisionen durch Null und WebGL-Renderabbrüchen.
* **Lösung:** Serverseitiges Clamping von 2D-Koordinaten (`area_left`, `area_top` auf 0–100%, `area_width`, `area_height` auf 1–100%), Begrenzung von 3D-Skalierungen (`model_scale`, `engraving_scale` auf 10–1000%) und Validierung der `custom_points`-Koordinaten.
* **Testabdeckung:** [`tests/Feature/Configurator/ConfiguratorServiceBoundsTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Configurator/ConfiguratorServiceBoundsTest.php) (2 Tests, 10 Assertions).

### 2.108 [ANALYTICS & DATENBANK-SCHUTZ] Stringlängen-Sanitization & Integer-Overflow-Schutz im ProductTrackingService
* **Dateien:** [`app/Services/ProductTrackingService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ProductTrackingService.php)
* **Befund:** In `ProductTrackingService::track()` wurden `source`, `event_name`, `product_id` und `template_id` ohne Längenbegrenzung entgegengenommen. Überlange Eingaben verursachten SQL-Fehler (`Data too long for column 'source'`). Zudem konnten extreme Werte bei `value_cents` zu 32-Bit-Integer-Überläufen führen.
* **Lösung:** Truncation von `source` (max 30 Zeichen), `event_name` (max 60 Zeichen), `product_id` / `template_id` (max 36 Zeichen), Clamping von `step` (0–255) sowie Begrenzung von `value_cents` (`0 <= val <= 2147483647`).
* **Testabdeckung:** [`tests/Feature/Analytics/ProductTrackingServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Analytics/ProductTrackingServiceSecurityTest.php) (2 Tests, 9 Assertions).

### 2.109 [KI-KALENDER & INTEGRITÄT] Wildcard-Schutz vor Terminlöschung & Chronologische Validierung in AiCalendarFuncs
* **Dateien:** [`app/Services/AI/Functions/AiCalendarFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiCalendarFuncs.php)
* **Befund:** In `executeDeleteCalendarEvent()` erlaubte `title_suche` das Übergeben von `%` oder unzureichenden Einzelbuchstaben, was zum Löschen des ersten besten Kalendertermins führte. In `executeGetCalendarEvents()` fehlte Wildcard-Escaping im Suchbegriff und eine Begrenzung von `limit`. In `executeCreateCalendarEvent()` und `executeUpdateCalendarEvent()` konnte ein `end_date` vor `start_date` liegen, was negative Termindauern erzeugte.
* **Lösung:** Mindestlänge von 3 Zeichen und `addcslashes` für `title_suche`, Begrenzung von `limit` auf 1–100, Wildcard-Escaping und automatische Korrektur von `end_date` auf `start_date + 1 Stunde`, falls das Enddatum vor dem Startdatum liegt.
* **Testabdeckung:** [`tests/Feature/Ai/AiCalendarFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiCalendarFuncsSecurityTest.php) (2 Tests, 7 Assertions).

### 2.110 [KI-GESUNDHEITSAKTE & DSGVO] IDOR-Prävention für Behandlungspläne/Protokolle & Speicherschutz sensibler PDF-Exporte
* **Dateien:** [`app/Services/AI/Functions/AiHealthFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiHealthFuncs.php)
* **Befund:** In `executeUpdateTreatmentPlan()` und `executeCompleteTreatmentPlan()` fehlte die Authentifizierungs- und Mandantenprüfung; jeder Nutzer konnte über beliebige Plan-IDs fremde medizinische Behandlungspläne manipulieren oder abschließen. In `executeExportTreatmentPlan()` und `executeExportProtocol()` wurden hochsensible medizinische PDF-Dokumente (§ 9 DSGVO) im öffentlich zugänglichen `public`-Storage abgelegt und unberechtigt exportierbar gemacht.
* **Lösung:** Zwingende Authentifizierungsprüfung (`Auth::user()` / Notfall-Admin) und striktes Scoping auf `user_id` des aktuellen Nutzers bei Update, Abschluss und Export. Verlagerung der generierten medizinischen PDFs ausschließlich auf die private `workspace`-Disk mit Deaktivierung externer Ressourcen in DomPDF (`isRemoteEnabled => false`).
* **Testabdeckung:** [`tests/Feature/Ai/AiHealthFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiHealthFuncsSecurityTest.php) (3 Tests, 11 Assertions).

### 2.111 [KI-MARKETING & BLOG] Foreign-Key-Korrektur, Slug-Kollisionsauflösung & Status-Validierung in AiMarketingBlogFuncs
* **Dateien:** [`app/Services/AI/Functions/AiMarketingBlogFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMarketingBlogFuncs.php)
* **Befund:** In `marketing_blog_create_post()` wurde die statische Ganzzahl `user_id => 1` eingetragen. Da das Datenbankschema `marketing_blog_posts` einen UUID-Foreign-Key auf `admins(id)` definiert, führte jeder Aufruf zu einem fatalen SQL-Integrity-Constraint-Fehler. Bei identischen Beitragstiteln kam es zu `Duplicate entry`-Abbrüchen auf dem `slug`-Unique-Key. Zudem wurden beliebige unvalidierte Strings in die `status`-Spalte geschrieben und in `marketing_blog_get_posts()` fehlte Wildcard-Escaping.
* **Lösung:** Dynamische Zuordnung der authentifizierten Admin-UUID (bzw. des ersten aktiven Admins), automatische Kollisionserkennung und Anhängen von Suffixen (`-1`, `-2`) an den Slug, Whitelisting des Status (`draft`, `published`, `scheduled`) sowie SQL-Wildcard-Escaping (`addcslashes($search, '%_\\')`).
* **Testabdeckung:** [`tests/Feature/Ai/AiMarketingBlogFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMarketingBlogFuncsSecurityTest.php) (2 Tests, 8 Assertions).

### 2.112 [KI-NEWSLETTER & AUTOMATION] Wertebereichs-Clamping, String-Truncation & Boolean-Casting in AiMarketingNewsletterFuncs
* **Dateien:** [`app/Services/AI/Functions/AiMarketingNewsletterFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMarketingNewsletterFuncs.php), [`app/Models/Marketing/MarketingNewsletter.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Marketing/MarketingNewsletter.php)
* **Befund:** In `marketing_newsletter_create_automated_campaign()` konnte `days_offset` unbegrenzt negativ oder exorbitant hoch übergeben werden. Titel und Betreff wurden ungekürzt übergeben, was zu DB-Truncation-Exceptions führte. In `marketing_newsletter_get_subscribers()` führte die unmaskierte E-Mail-Suche mit `%` und `_` zu fehlerhaften Matches. In `marketing_newsletter_toggle_archive()` führte die Übergabe von String-Booleans (`"false"`) zu inkorrekter Aktivierung.
* **Lösung:** Clamping von `days_offset` auf den Bereich 0–365 Tage, Truncation von Betreff/Titel auf 255 Zeichen, Wildcard-Escaping mit `addcslashes`, robustes Parsen von Booleans mittels `filter_var(..., FILTER_VALIDATE_BOOLEAN)` und Hinzufügen von `protected $casts = ['is_active' => 'boolean']` im Model.
* **Testabdeckung:** [`tests/Feature/Ai/AiMarketingNewsletterFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMarketingNewsletterFuncsSecurityTest.php) (2 Tests, 6 Assertions).

### 2.113 [KI-TELEFONIE & CALL-MANAGEMENT] Status-Whitelisting, Model Mass-Assignment & Search-Clamping in AiTelefonyFuncs
* **Dateien:** [`app/Services/AI/Functions/AiTelefonyFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiTelefonyFuncs.php), [`app/Models/SupportTelephonyCall.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/SupportTelephonyCall.php)
* **Befund:** In `executeTelephonyCallUpdateStatus()` konnten beliebige ungültige Statusstrings in die Datenbank geschrieben werden, wodurch Anrufe im Dashboard verloren gingen. In `SupportTelephonyCall` fehlte `$guarded = []`, was Mass-Assignment verhinderte. In `executeTelephonyCallGetHistory()` war `limit` unbegrenzt und der Vorname wurde ohne SQL-Wildcard-Escaping gesucht.
* **Lösung:** Striktes Status-Whitelisting (`['completed', 'failed', 'ongoing', 'planned']`), Ergänzung von `protected $guarded = []` im Model, Begrenzung von `limit` auf 1–50 sowie SQL-Wildcard-Escaping mit `addcslashes`.
* **Testabdeckung:** [`tests/Feature/Ai/AiTelefonyFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiTelefonyFuncsSecurityTest.php) (2 Tests, 6 Assertions).

### 2.114 [HERZENSPROJEKT & BUCHHALTUNG] Atomare Transaktionssperren & Idempotenz gegen Mehrfachrückbuchungen im HerzensprojektService
* **Dateien:** [`app/Services/HerzensprojektService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/HerzensprojektService.php)
* **Befund:** In `recordDonationForOrder()` und `reverseDonationForOrder()` fehlten Datenbank-Transaktionen und Zeilensperren (`lockForUpdate()`), wodurch parallele Checkout-Webhooks oder Stornierungsanfragen doppelte Spendenbuchungen erzeugen konnten. In `reverseDonationForOrder()` basierte die Idempotenzprüfung fragil auf `where('description', 'like', '%Stornierung%')`, wodurch Erstattungen unter abweichendem Grund (z.B. "Widerruf", "Erstattung") mehrfach gebucht wurden. In `getActiveAction()` fehlte die Klammerung im Statusfilter (`orWhere`).
* **Lösung:** Kapselung beider Buchungsmethoden in `DB::transaction()` mit `lockForUpdate()`, Prüfung auf jegliche existierende negative Auszahlung (`where('amount_cents', '<', 0)->exists()`) für die gegebene Order und Umstellung auf sauberes `whereIn('status', ['in_progress', 'planned'])`.
* **Testabdeckung:** [`tests/Feature/Marketing/HerzensprojektServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/HerzensprojektServiceSecurityTest.php) (3 Tests, 7 Assertions).

### 2.115 [KI-AUFGABENVERWALTUNG & INTEGRITÄT] Wildcard-Löschschutz, Mindestlängen & sichere Listenauflösung in AiTaskFuncs
* **Dateien:** [`app/Services/AI/Functions/AiTaskFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiTaskFuncs.php)
* **Befund:** In `executeDeleteTask()` konnten über unmaskierte Wildcards wie `%` oder einbuchstabige Suchbegriffe versehentlich sämtliche Aufgaben des Systems massenhaft gelöscht werden. In `executeAddSubtask()` und `executeCompleteTask()` führten unmaskierte Substrings zu Fehlzuordnungen oder Bearbeitung falscher Tasks. In `resolveTaskListId()` fehlte Escaping beim LIKE-Abgleich.
* **Lösung:** Enforcing einer Mindestzeichenlänge (Aufgabentitel >= 3 Zeichen, Listennamen >= 2 Zeichen), Verbot leerer oder reiner Wildcard-Suchfilter in `executeDeleteTask()`, Escaping aller SQL-Wildcards mit `addcslashes` und Validierung übergeordneter Hauptaufgaben.
* **Testabdeckung:** [`tests/Feature/Ai/AiTaskFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiTaskFuncsSecurityTest.php) (2 Tests, 9 Assertions).

### 2.116 [KI-MARKETING & GUTSCHEINE] Wertebereichs-Clamping, Code-Sanitization & Kollisionssicherheit in AiMarketingFuncs
* **Dateien:** [`app/Services/AI/Functions/AiMarketingFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMarketingFuncs.php)
* **Befund:** In `executeCreateCoupon()` und `executeUpdateCoupon()` fehlte eine Wertebereichsvalidierung: Rabattsätze konnten negativ oder über 100% angegeben werden (was zu Negativpreisen führte), Gutscheincodes wurden ungefiltert und ohne Längenbegrenzung gespeichert, und bei der Umbenennung eines bestehenden Gutscheins fehlte eine Eindeutigkeitsprüfung (was SQL-Integrity-Exceptions bei Kollisionen auslöste). Ungültige Nutzungslimits (`<= 0`) wurden unplausibel interpretiert.
* **Lösung:** Clamping von Rabatten (Prozent 1–100%, Festbeträge 0,01–100.000 €), Code-Sanitization (`[A-Za-z0-9_\-]`, max 50 Zeichen), Behandlung von Nutzungslimits `<= 0` als unbegrenzt (`null`), strikte Kollisionsprüfung bei Code-Updates sowie Trim/Uppercase in `executeDeleteCoupon()`.
* **Testabdeckung:** [`tests/Feature/Ai/AiMarketingFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMarketingFuncsSecurityTest.php) (2 Tests, 9 Assertions).

### 2.117 [KI-MASTER & SHOP-WARTUNG] Beseitigung statischer Bypass-Tokens & striktes Aktions-Whitelisting in AiMasterFuncs
* **Dateien:** [`app/Services/AI/Functions/AiMasterFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMasterFuncs.php)
* **Befund:** In `executeToggleMaintenance()` wurde beim Aktivieren des Wartungsmodus ein fest einprogrammiertes Bypass-Token (`['--secret' => 'funkira-maintenance']`) verwendet, was den in Issue 93 behobenen Sicherheitsstandard untergrub. Zudem führte jede fehlerhafte oder beliebige Zeichenkette außer `"down"` unvalidiert zum Aufruf von `Artisan::call('up')`. In `executeGetCoreKpis()` fehlte die Absicherung gegen ungültige Timeframes.
* **Lösung:** Dynamische Generierung des Maintenance-Tokens über `config('app.maintenance_secret')` bzw. gehashten Salt, striktes Whitelisting der Aktionen (`['down', 'up']`) und Absicherung der Timeframe-Parameter in `executeGetCoreKpis()`.
* **Testabdeckung:** [`tests/Feature/Ai/AiMasterFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMasterFuncsSecurityTest.php) (2 Tests, 5 Assertions).

### 2.118 [KI-SCOUT & KUNDENANALYTICS] Behebung von Spaltennamens-Crashes & Wildcard-Dump-Schutz in AiScoutFuncs
* **Dateien:** [`app/Services/AI/Functions/AiScoutFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiScoutFuncs.php)
* **Befund:** In `executeSearchCustomers()` brach die Berechnung des Kundenumsatzes mit einer fatalen SQL-Exception ab (`Unknown column 'total_amount' in 'field list'`), da das Schema von `order_orders` das Feld `total_price` verwendet. Zudem fehlte eine Mindestlänge für den Suchbegriff, und unmaskierte `%`- bzw. `_`-Wildcards führten zum unkontrollierten Auslesen sensibler Kundendatensätze.
* **Lösung:** Korrektur des Spaltennamens auf `total_price`, Abfrage von abgeschlossenen und bezahlten Bestellungen, Erzwingen einer Mindestlänge von 2 Zeichen und Escaping aller SQL-Wildcards mit `addcslashes`.
* **Testabdeckung:** [`tests/Feature/Ai/AiScoutFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiScoutFuncsSecurityTest.php) (2 Tests, 7 Assertions).

### 2.119 [KI-NEWS & MULTIMEDIA] Beseitigung blockierender I/O-Hangs & Information-Leakage-Schutz in AiNewsFuncs
* **Dateien:** [`app/Services/AI/Functions/AiNewsFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiNewsFuncs.php)
* **Befund:** In `searchYoutubeVideo()` wurde `@file_get_contents` ohne HTTP-Socket-Timeout synchron aufgerufen, was bei YouTube-Drosselungen oder Netzwerkverzögerungen zum Blockieren des PHP-Workers führte. In `searchGlobalNews()` wurden ungefilterte `sortBy`-Parameter an die externe NewsAPI durchgereicht und interne HTTP-Fehlertexte (`$response->body()`) ungefiltert zurückgegeben.
* **Lösung:** Umstellung auf `Http::timeout(5)` mit kontrolliertem Header-Management und Regex-Extraktion von Video-IDs, Whitelisting des Sortierparameters (`['publishedAt', 'relevancy', 'popularity']`), Längenbeschränkung der Suchanfragen und Maskierung externer API-Fehler in generische Statusmeldungen.
* **Testabdeckung:** [`tests/Feature/Ai/AiNewsFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiNewsFuncsSecurityTest.php) (2 Tests, 7 Assertions).

### 2.120 [KI-PERSONA & OSINT] SSRF-Schutz für Bild- und Web-Extraktion, E-Mail-Header-Validierung & DomPDF-Härtung
* **Dateien:** [`app/Services/AI/Functions/AiPersonaFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina\meine-projekte\seelenfunke\app\Services\AI\Functions\AiPersonaFuncs.php)
* **Befund:** In `processPersonaImage()` und `executePersonaFetchUrl()` wurden beliebige externe URLs ohne Überprüfung heruntergeladen, wodurch Angreifer über Server-Side Request Forgery (SSRF) auf interne Docker-Container (`db`, `redis`, `mein_php_server`), Localhost-Dienste oder Cloud-Metadatendienste (`169.254.169.254`) zugreifen konnten. In `executePersonaGeneratePdf()` fehlte die E-Mail-Validierung und DomPDF erlaubte Remote-Assets. In `executePersonaDeleteIntel()` konnten leere Titel angefragt werden.
* **Lösung:** Implementierung von `isSafeExternalUrl()` zur strikten Blockierung von Loopback-, privaten (`FILTER_FLAG_NO_PRIV_RANGE`) und reservierten (`FILTER_FLAG_NO_RES_RANGE`) IP-Adressen sowie Docker-Hostnamen, E-Mail-RFC-Validierung und CRLF-Sanitization bei Dossier-Versand, Deaktivierung von DomPDF Remote-Ressourcen (`isRemoteEnabled => false`) und Titelvalidierung bei der Wissenslöschung.
* **Testabdeckung:** [`tests/Feature/Ai/AiPersonaFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiPersonaFuncsSecurityTest.php) (2 Tests, 7 Assertions).

### 2.121 [PUSH-NOTIFICATIONS & SYSTEM-ADMINS] Filterung auf aktive Admins, N+1-Query-Bereinigung & Push-Timeout im FirebaseService
* **Dateien:** [`app/Services/FirebaseService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/FirebaseService.php)
* **Befund:** In `sendToAdmins()` wurden über `Admin::all()` und anschließende Einzelschleifen N+1 Datenbankabfragen ausgeführt und Push-Benachrichtigungen auch an soft-gelöschte oder inaktive Administratoren versendet. In `sendPushNotification()` fehlte ein HTTP-Timeout (Gefahr von Worker-Hangs bei Google-FCM-Ausfall) und verschachtelte `$data`-Arrays warfen `Array to string conversion`-Exceptions.
* **Lösung:** Eingrenzung auf aktive Admins (`Admin::pluck('id')`), Einmal-Abfrage aller registrierten FCM-Tokens via `whereIn('userable_id', $adminIds)`, rekursive/sichere Serialisierung verschachtelter Payload-Daten (`json_encode`), Truncation von Titel (120 Zeichen) und Body (500 Zeichen) sowie `Http::timeout(5)`.
* **Testabdeckung:** [`tests/Feature/System/FirebaseServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/FirebaseServiceSecurityTest.php) (2 Tests, 4 Assertions).

### 2.122 [GOOGLE REVIEWS & MARKETING] Aktivierung der echten Places-API-Anbindung, 24h-Cache & Resilienz im GoogleReviewsService
* **Dateien:** [`app/Services/GoogleReviewsService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/GoogleReviewsService.php)
* **Befund:** Der Dienst befand sich in einem temporären statischen Testmodus; die echte Google Places API-Anbindung war auskommentiert, sodass im Live-Shop ausschließlich veraltete Mock-Bewertungen angezeigt wurden. Zudem fehlten Timeouts und eine saubere Fallback-Struktur im Fehlerfall.
* **Lösung:** Vollständige Aktivierung der Google Places API-Integration mit `Http::timeout(5)`, automatischem 24h-Caching (`Cache::remember('google_reviews', 86400)`), Auslesen konfigurierter Keys und robuster Auslagerung in `getFallbackReviews()`, falls Google-Credentials nicht gesetzt sind oder die API fehlschlägt.
* **Testabdeckung:** [`tests/Feature/Marketing/GoogleReviewsServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/GoogleReviewsServiceSecurityTest.php) (2 Tests, 9 Assertions).

### 2.123 [STEUER-ENGINE & RECHTSFORM-WECHSEL] Division-by-Zero-Schutz & transaktionssichere Status-Umschaltung im TaxSettingService
* **Dateien:** [`app/Services/Tax/TaxSettingService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Tax/TaxSettingService.php)
* **Befund:** In `calculateRetroactiveVat()` führte ein Steuersatz von `<= -100%` (oder ungültige numerische Eingaben) zu einem Division-by-Zero-Fatal-Error (`1 + ($taxRate / 100) == 0`). In `applyTransition()` wurden mehrere `SystemSetting`-Einträge ohne Datenbank-Transaktion geschrieben; bei einem Zwischenfehler drohte ein inkonsistenter Steuerstatus.
* **Lösung:** Clamping des Steuersatzes auf 0–100%, Spezialbehandlung von 0% ohne Division, Absicherung der Bruttowerte (`max(0, $grossCents)`) und Kapselung der Statusumschaltung in `DB::transaction()` mit Whitelisting des Transition-Modus (`immediate_retroactive`, `next_year`).
* **Testabdeckung:** [`tests/Feature/Tax/TaxSettingServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Tax/TaxSettingServiceSecurityTest.php) (2 Tests, 8 Assertions).

### 2.124 [E-RECHNUNG & XML-SICHERHEIT] Path-Traversal-Schutz, Null-Safety & saubere XML-Text-Nodes im AccountingXmlInvoiceService
* **Dateien:** [`app/Services/AccountingXmlInvoiceService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AccountingXmlInvoiceService.php)
* **Befund:** Beim Speichern von ZUGFeRD/XRechnung-XMLs (`Storage::disk('local')->put(...)`) wurde die Rechnungsnummer ungefiltert im Dateipfad verwendet; Rechnungsnummern mit Slashes (`RE/2026/01`) oder Punkten führten zu Dateisystemfehlern oder Directory-Traversal. In `addElement()` verursachte `htmlspecialchars()` auf `nodeValue` bei Sonderzeichen (`&`, `<`) Doppel-Kodierungen (`&amp;amp;`). Zudem brachen Null-Werte bei `invoice_date` oder `delivery_date` mit Exceptions ab.
* **Lösung:** Sanitization des Rechnungs-Dateinamens (`preg_replace('/[^A-Za-z0-9_\-]/', '_', ...)`), Umstellung auf native DOMDocument-Text-Nodes (`createTextNode((string)$value)`) für sauberes XML-Escaping und Null-Safe-Fallbacks (`$invoice->invoice_date ?? now()`).
* **Testabdeckung:** [`tests/Feature/Accounting/AccountingXmlInvoiceServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/AccountingXmlInvoiceServiceSecurityTest.php) (1 Test, 5 Assertions).

### 2.125 [KI-MAPS & GEODATEN] RFC-Validierung & CRLF-Header-Injection-Schutz für Map-PDF-Mails sowie DomPDF-Härtung in AiMapControlFuncs
* **Dateien:** [`app/Services/AI/Functions/AiMapControlFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMapControlFuncs.php)
* **Befund:** In `executeMapGeneratePdfSummary()` und `executeMapGeneratePlacesPdf()` wurde `recipient_email` ohne strikte E-Mail-Validierung an den Mailer übergeben; Eingaben mit CRLF (`\r\n`) ermöglichten Mail-Header-Injections. In beiden PDF-Generatoren fehlte die Deaktivierung von Remote-Ressourcen in DomPDF (`isRemoteEnabled`). Bei externen Mapbox- und Nominatim-Aufrufen fehlten HTTP-Timeouts.
* **Lösung:** Strikte RFC-Validierung (`filter_var`), CRLF-Prüfung (`preg_match('/[\r\n]/')`), DomPDF-Härtung mit `isRemoteEnabled => false` und Ergänzung von `Http::timeout(5)` für externe Geocoding-APIs.
* **Testabdeckung:** [`tests/Feature/Ai/AiMapControlFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMapControlFuncsSecurityTest.php) (2 Tests, 5 Assertions).

---

## 3. Übersicht aller modifizierten Dateien

| Datei | Art der Änderung |
|---|---|
| [`app/Services/FirebaseService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/FirebaseService.php) | Admin-Filterung (`Admin::pluck('id')`), Massen-Abfrage für FCM-Tokens, rekursive Daten-Serialisierung & Push-Timeout |
| [`app/Services/GoogleReviewsService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/GoogleReviewsService.php) | Aktivierung der Google Places API, 24h-Caching, `Http::timeout(5)` & Fallback-Handling |
| [`app/Services/Tax/TaxSettingService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Tax/TaxSettingService.php) | Division-by-Zero-Schutz, Raten-Clamping (0..100%), Nicht-Negativ-Garantie & DB-Transaktion bei Wechsel |
| [`app/Services/AccountingXmlInvoiceService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AccountingXmlInvoiceService.php) | Dateinamens-Sanitization (`preg_replace`), Null-Safe Dates & native DOM-Text-Nodes gegen Doppelkodierung |
| [`app/Services/AI/Functions/AiMapControlFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMapControlFuncs.php) | Strikte RFC-Validierung & CRLF-Schutz für Mailings, DomPDF SSRF-Deaktivierung & HTTP-Timeouts |
| [`tests/Feature/System/FirebaseServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/FirebaseServiceSecurityTest.php) | Neue Testsuite: Push-Token-Prüfung, verschachtelte Daten & Admin-Geräte-Filter |
| [`tests/Feature/Marketing/GoogleReviewsServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/GoogleReviewsServiceSecurityTest.php) | Neue Testsuite: Fallback-Reviews, Google Places Caching & Resilienz |
| [`tests/Feature/Tax/TaxSettingServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Tax/TaxSettingServiceSecurityTest.php) | Neue Testsuite: Division-by-Zero-Schutz & transaktionssichere Status-Umschaltung |
| [`tests/Feature/Accounting/AccountingXmlInvoiceServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/AccountingXmlInvoiceServiceSecurityTest.php) | Neue Testsuite: XML-Dateinamens-Sanitization & Sonderzeichen-Encoding |
| [`tests/Feature/Ai/AiMapControlFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMapControlFuncsSecurityTest.php) | Neue Testsuite: RFC-E-Mail-Validierung & CRLF-Injection-Schutz bei Map-PDFs |
| [`app/Services/AI/Functions/AiMarketingFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMarketingFuncs.php) | Wertebereichs-Clamping (Prozent 1–100%, Festbeträge max 100.000 €), Code-Sanitization, Limit-Behandlung & Kollisionsschutz |
| [`app/Services/AI/Functions/AiMasterFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMasterFuncs.php) | Dynamische Maintenance-Secrets, striktes Aktions-Whitelisting (`down`, `up`) & Timeframe-Absicherung |
| [`app/Services/AI/Functions/AiScoutFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiScoutFuncs.php) | Korrektur Spaltenname `total_price`, Mindestlänge für Kundensuche & SQL-Wildcard-Escaping |
| [`app/Services/AI/Functions/AiNewsFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiNewsFuncs.php) | Timeout `Http::timeout(5)` für YouTube, SortBy-Whitelisting & Maskierung von API-Fehlern |
| [`app/Services/AI/Functions/AiPersonaFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiPersonaFuncs.php) | SSRF-Schutz (`isSafeExternalUrl`), E-Mail-RFC-Validierung, DomPDF Remote-Deaktivierung & Titelprüfung |
| [`tests/Feature/Ai/AiMarketingFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMarketingFuncsSecurityTest.php) | Neue Testsuite: Wertebereichs-Clamping, Code-Sanitization & Kollisionssicherheit |
| [`tests/Feature/Ai/AiMasterFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMasterFuncsSecurityTest.php) | Neue Testsuite: Maintenance-Passphrase, Whitelisting & dynamisches Secret |
| [`tests/Feature/Ai/AiScoutFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiScoutFuncsSecurityTest.php) | Neue Testsuite: Mindestlänge, Wildcard-Escaping & `total_price`-Berechnung |
| [`tests/Feature/Ai/AiNewsFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiNewsFuncsSecurityTest.php) | Neue Testsuite: News-Validierung, Error-Masking & YouTube-Extraktion |
| [`tests/Feature/Ai/AiPersonaFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiPersonaFuncsSecurityTest.php) | Neue Testsuite: SSRF-Schutz, E-Mail-Validierung & Titelprüfung |
| [`app/Services/AI/Functions/AiMarketingBlogFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMarketingBlogFuncs.php) | Admin-UUID-Zuordnung statt integer 1, automatische Slug-Kollisionsauflösung (`-1`, `-2`), Status-Whitelisting & Wildcard-Escaping |
| [`app/Services/AI/Functions/AiMarketingNewsletterFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMarketingNewsletterFuncs.php) | Boolean-Parsing, Clamping von `days_offset` (0–365), String-Truncation & Wildcard-Escaping |
| [`app/Models/Marketing/MarketingNewsletter.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Marketing/MarketingNewsletter.php) | Boolean-Casting für `is_active` (`$casts`) |
| [`app/Services/AI/Functions/AiTelefonyFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiTelefonyFuncs.php) | Status-Whitelisting, Begrenzung von `limit` (1–50) & Wildcard-Escaping für Kontaktsuche |
| [`app/Models/SupportTelephonyCall.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/SupportTelephonyCall.php) | Mass-Assignment Freigabe (`protected $guarded = []`) |
| [`app/Services/HerzensprojektService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/HerzensprojektService.php) | Atomare Transaktionen & Zeilensperren für Spendenbuchung & Storno-Rückbuchung, Negativsaldo-Idempotenzprüfung |
| [`app/Services/AI/Functions/AiTaskFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiTaskFuncs.php) | Wildcard-Mass-Deletion-Sperre, Mindestlängen (Aufgaben >= 3, Listen >= 2) & LIKE-Wildcard-Escaping |
| [`tests/Feature/Ai/AiMarketingBlogFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMarketingBlogFuncsSecurityTest.php) | Neue Testsuite: Foreign-Key-Korrektur, Slug-Kollisionsauflösung & Wildcard-Escaping |
| [`tests/Feature/Ai/AiMarketingNewsletterFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMarketingNewsletterFuncsSecurityTest.php) | Neue Testsuite: Offset-Clamping, String-Truncation & Boolean-Archive-Toggle |
| [`tests/Feature/Ai/AiTelefonyFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiTelefonyFuncsSecurityTest.php) | Neue Testsuite: Status-Whitelisting, Mass-Assignment & Search-Clamping |
| [`tests/Feature/Marketing/HerzensprojektServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/HerzensprojektServiceSecurityTest.php) | Neue Testsuite: Spendenbuchungs-Idempotenz & robuste Storno-Rückbuchung |
| [`tests/Feature/Ai/AiTaskFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiTaskFuncsSecurityTest.php) | Neue Testsuite: Wildcard-Löschschutz, Mindestlängen & Listenauflösung |
| [`app/Livewire/Shop/Product/ProductCalculator/ProductCalculator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCalculator/ProductCalculator.php) | Sichere Produkt-Existenzprüfung, Stückzahl-Sanitization (`min:1, max:10000`), IP-Rate-Limiter (`calculator-submit`, 5/10min) & Negativpreis-Schutz |
| [`app/Services/CartFileCleanupService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartFileCleanupService.php) | SQL-Wildcard-Escaping mit `addcslashes`, speicherschonendes `chunkById(100)` für verlassene Warenkörbe & Pfad-Normalisierung |
| [`app/Services/ConfiguratorService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ConfiguratorService.php) | Serverseitiges Clamping von 2D-Bereichen (0–100%) und 3D-Skalierungen (10–1000%) gegen WebGL/Three.js-Crashes |
| [`app/Services/ProductTrackingService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ProductTrackingService.php) | Stringlängen-Truncation (`source`, `event_name`, UUIDs) und INT-Overflow-Schutz bei Tracking-Events |
| [`app/Services/AI/Functions/AiCalendarFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiCalendarFuncs.php) | Wildcard-Schutz vor Terminlöschung, Mindestlänge für Titelsuche (>= 3 Zeichen) und automatische Enddatum-Korrektur |
| [`app/Services/AI/Functions/AiHealthFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiHealthFuncs.php) | Mandanten-Isolation & IDOR-Prävention für Behandlungspläne/Protokolle sowie privater Storage-Export (§ 9 DSGVO) |
| [`tests/Feature/Cart/CartFileCleanupServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Cart/CartFileCleanupServiceSecurityTest.php) | Neue Testsuite: Pfadtraversal-Schutz, Wildcard-Escaping & chunked Löschung alter Warenkörbe |
| [`tests/Feature/Configurator/ConfiguratorServiceBoundsTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Configurator/ConfiguratorServiceBoundsTest.php) | Neue Testsuite: Koordinaten- & Skalierungs-Clamping im Konfigurator |
| [`tests/Feature/Analytics/ProductTrackingServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Analytics/ProductTrackingServiceSecurityTest.php) | Neue Testsuite: Stringlängen-Sanitization & Wertebereichsvalidierung im Tracking |
| [`tests/Feature/Ai/AiCalendarFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiCalendarFuncsSecurityTest.php) | Neue Testsuite: Wildcard-Löschschutz, Such-Mindestlänge & chronologische Validierung |
| [`tests/Feature/Ai/AiHealthFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiHealthFuncsSecurityTest.php) | Neue Testsuite: IDOR-Schutz bei Aktualisierung, Abschluss & Export medizinischer Akten |
| [`app/Livewire/Shop/Product/ProductVariants.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductVariants.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()`, Bereinigung von Preisen (`min:0`) und Beständen |
| [`app/Livewire/Shop/Product/ProductTax.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductTax.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()`, Whitelist für Steuerklassen (`standard, reduced, zero`) |
| [`resources/views/livewire/shop/product/product-variants.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-variants.blade.php) | Null-sicherer Blade-Zugriff auf Variantenattribute (`$variant['attributes'] ?? []`) |
| [`app/Livewire/Shop/Product/ProductTierPricing.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductTierPricing.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()`, Scoping aller Aktionen gegen IDOR, Rabatt-Clamping (0-100%) |
| [`app/Livewire/Shop/Product/ProductAttributes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductAttributes.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Product/ProductCategories.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCategories.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Product/ProductTemplates.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductTemplates.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Product/ProductCrawler.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCrawler.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()`, Beseitigung des unsicheren Admin-ID-1 Fallbacks |
| [`app/Livewire/Customer/CustomerForcePasswordChange.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerForcePasswordChange.php) | Null-Dereferenzierung behoben, Authentifizierungs-Check, Längenvalidierung & `same:passwordConfirm` |
| [`app/Livewire/Customer/CustomerInvoicesComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerInvoicesComponent.php) | Autorisierungs-Guard in `mount()` (`abort(403)`), Null-Prüfung in `render()` |
| [`app/Livewire/Shop/Marketing/MarketingBlog.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingBlog.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()`, Beitrags- und Kategorieaktionen |
| [`tests/Feature/Product/ProductCalculatorSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductCalculatorSecurityTest.php) | Neue Testsuite: Mengen-Sanitization, Produkt-Existenzprüfung & Rate-Limiter im Mengenrechner |
| [`tests/Feature/Product/ProductVariantsAndTaxSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductVariantsAndTaxSecurityTest.php) | Neue Testsuite: Autorisierungsschutz & Wertebereichsbereinigung für Varianten und Steuer |
| [`tests/Feature/Product/ProductTierPricingSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductTierPricingSecurityTest.php) | Neue Testsuite: Staffelpreise IDOR-Schutz, Prozent-Clamping & Mengenbereinigung |
| [`tests/Feature/Product/ProductCatalogBackendAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductCatalogBackendAuthorizationTest.php) | Neue Testsuite: Autorisierungsschutz für Attribute, Kategorien, Vorlagen & Crawler |
| [`tests/Feature/Customer/CustomerSecurityAndBlogAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Customer/CustomerSecurityAndBlogAuthorizationTest.php) | Neue Testsuite: Zwangspasswort-Sicherheit, Kunden-Rechnungen & Blog-Autorisierung |
| [`app/Livewire/Shop/Marketing/MarketingVoucher.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingVoucher.php) | Admin/Mitarbeiter-Autorisierung in `mount()`, Wertebereichsvalidierung (max:100% etc.) in `saveManualCoupon()` |
| [`app/Livewire/Shop/Management/ManagementEMails.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementEMails.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Management/ManagementContacts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementContacts.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Management/ManagementTask.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementTask.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Management/ManagementCalender.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementCalender.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Management/ManagementRoutine.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementRoutine.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Management/ManagementShoppingList.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementShoppingList.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Support/SupportTicket.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportTicket.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Support/SupportChats.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportChats.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Product/ProductCreate.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCreate.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Product/ProductSuppliers.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductSuppliers.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Product/ProductFracture.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductFracture.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Product/ProductPackagingConfigurator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductPackagingConfigurator.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`tests/Feature/Marketing/MarketingVoucherSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/MarketingVoucherSecurityTest.php) | Neue Testsuite: Gutschein-Wertebereichs- & Autorisierungsvalidierung |
| [`tests/Feature/Management/ManagementAccessSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Management/ManagementAccessSecurityTest.php) | Neue Testsuite: Autorisierungsschutz für alle 6 Management-Backend-Komponenten |
| [`tests/Feature/Support/SupportComponentsAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Support/SupportComponentsAuthorizationTest.php) | Neue Testsuite: Autorisierungsschutz für Support-Tickets & Support-Chats |
| [`tests/Feature/Product/ProductManagementAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductManagementAuthorizationTest.php) | Neue Testsuite: Autorisierungsschutz für Produktneuanlage, Lieferanten, Bruchware & Verpackung |
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
| [`app/Livewire/Shop/System/SystemShopConfig.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemShopConfig.php) | Admin-Guard gegen Secrets-Leakage (Stripe/Bank/PINs), Path Traversal Schutz via `basename()` & MIME-Whitelist bei Uploads |
| [`app/Livewire/Shop/Order/OrderRevocations.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderRevocations.php) | Autorisierungsschutz, zentrale Stornierungsabwicklung via `handleCancellation()`, Standardwaren-Restitution (Laser-Ausschuss-Regel) |
| [`app/Livewire/Shop/Order/OrderQuoteRequests.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderQuoteRequests.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()`, Angebotsannahme, Ablehnung & Laser-File Download |
| [`app/Livewire/Shop/Accounting/AccountingCredit.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingCredit.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` |
| [`app/Livewire/Shop/Accounting/AccountingInvoicePreview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Accounting/AccountingInvoicePreview.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()`, PDF-Download und Rechnungsstornierung |
| [`app/Livewire/Shop/System/SystemShipping.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemShipping.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` für Versandkostenverwaltung |
| [`app/Livewire/Shop/System/SystemDeliveryTimes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemDeliveryTimes.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` für Lieferzeitmanagement |
| [`app/Livewire/Shop/System/SystemLogs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemLogs.php) | Admin-Only-Autorisierungsprüfung in `mount()`, Log-Status und Log-Löschung |
| [`app/Livewire/Shop/Support/SupportTelephony.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportTelephony.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` für Telefonie-Dashboard |
| [`app/Livewire/Shop/Support/SupportContactFormComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportContactFormComponent.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` für Kontaktformular-Posteingang |
| [`app/Livewire/Shop/Ai/PartialsAiWorkspace/TabCronjobs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/PartialsAiWorkspace/TabCronjobs.php) | Admin-Only-Autorisierungsprüfung in `mount()` und Cronjob-Steuerungsaktionen |
| [`app/Livewire/Shop/Order/OrderAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderAnalytics.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` für Bestellstatistiken |
| [`app/Livewire/Shop/Order/OrderShoppingCarts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderShoppingCarts.php) | Admin/Mitarbeiter-Autorisierungsprüfung in `mount()` für Warenkorb-Monitoring |
| [`app/Livewire/Auth/AuthProfileDropdown.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Auth/AuthProfileDropdown.php) | Nullable-Guard-Deklaration `public ?string $guard = null;` & Gast-Abbruch (`abort(403)`) |
| [`tests/Feature/System/SystemShopConfigSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemShopConfigSecurityTest.php) | Neue Testsuite: SystemShopConfig Secrets-Schutz, Rollen-Gates & Traversal-Sperre |
| [`tests/Feature/Order/OrderRevocationProcessingStockTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderRevocationProcessingStockTest.php) | Neue Testsuite: Widerrufsabwicklung, Autorisierung, Standard-Bestandsrestore & Laser-Ausschussgarantie |
| [`tests/Feature/Accounting/AccountingCreditAndInvoicePreviewSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/AccountingCreditAndInvoicePreviewSecurityTest.php) | Neue Testsuite: Zugriffsschutz für Gutschriften und Rechnungsvorschau |
| [`tests/Feature/System/SystemInfrastructureAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemInfrastructureAuthorizationTest.php) | Neue Testsuite: Autorisierung für Versand, Lieferzeiten und System-Logs (Admin-Only) |
| [`tests/Feature/Support/OperationalToolsAuthorizationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Support/OperationalToolsAuthorizationTest.php) | Neue Testsuite: Autorisierung für Telefonie, Cronjobs, Analytics & Profil-Dropdown |
| [`app/Livewire/Shop/System/SystemBackups.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemBackups.php) | Admin-Only-Guard in `mount()`, `runTestBackup()`, `downloadBackup()` und `deleteBackup()` |
| [`app/Livewire/Backend/Management/ManagementLinktreeManager.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Backend/Management/ManagementLinktreeManager.php) | Autorisierungsschutz für Linktree-Verwaltung, Sortierung, Löschung und globale Shopeinstellungen |
| [`app/Livewire/Backend/System/SystemNeuralAnalysisIndex.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Backend/System/SystemNeuralAnalysisIndex.php) | Autorisierungsschutz in `mount()` und `generateStructure()` für Code-Architekturanalysen |
| [`app/Livewire/Shop/Product/ProductControlReviews.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductControlReviews.php) | Autorisierungsschutz in `mount()`, `approve()`, `reject()` und `deleteReview()` für Rezensionen |
| [`app/Livewire/Shop/Marketing/MarketingLandingPages.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingLandingPages.php) | Autorisierungsschutz in `mount()` und allen Aktionsmethoden (Generierung, Edit, Status, Löschung) |
| [`tests/Feature/System/SystemBackupsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemBackupsSecurityTest.php) | Neue Testsuite: Striktes Admin-Only Gate für Datenbank-Backups & Downloads |
| [`tests/Feature/Management/ManagementLinktreeSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Management/ManagementLinktreeSecurityTest.php) | Neue Testsuite: Zugriffsschutz für Linktree-Manager und Systemkonfiguration |
| [`tests/Feature/System/SystemNeuralAnalysisSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemNeuralAnalysisSecurityTest.php) | Neue Testsuite: Zugriffsschutz für Neurale Architekturanalyse |
| [`tests/Feature/Product/ProductControlReviewsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductControlReviewsSecurityTest.php) | Neue Testsuite: Zugriffsschutz für Produktbewertungs-Moderation |
| [`tests/Feature/Marketing/MarketingLandingPagesSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/MarketingLandingPagesSecurityTest.php) | Neue Testsuite: Zugriffsschutz für dynamische Marketing-Landingpages |
| [`app/Livewire/Shop/Ai/AiCompanyStructure.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiCompanyStructure.php) | Autorisierungsschutz in `mount()` und Mutationsmethoden für Abteilungen und Agenten |
| [`app/Livewire/Shop/Ai/AiRoleManager.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiRoleManager.php) | Autorisierungsschutz für Rollen-Konfiguration und Tool-Zuweisung |
| [`app/Livewire/Shop/Ai/AiKnowledgeBase.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiKnowledgeBase.php) | Autorisierungsschutz, MIME-Whitelisting (`pdf, txt, md, docx, png, jpg, jpeg`) & Traversal-Schutz |
| [`app/Livewire/Shop/Ai/AiKnowledgeBaseCategories.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiKnowledgeBaseCategories.php) | Autorisierungsschutz für Wissensdatenbank-Kategorien |
| [`app/Livewire/Shop/Ai/AiKnowledgeBaseTags.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiKnowledgeBaseTags.php) | Autorisierungsschutz für Wissensdatenbank-Tags |
| [`app/Livewire/Shop/Ai/AiAgentEditor.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiAgentEditor.php) | Autorisierungsschutz in `mount()`, Speicherung, Löschung, Prompt- & Token-Modifikation |
| [`app/Livewire/Shop/Ai/AiMethod.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiMethod.php) | Autorisierungsschutz in `mount()` für Funktionsstatistiken |
| [`tests/Feature/Ai/AiCompanyStructureSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiCompanyStructureSecurityTest.php) | Neue Testsuite: Zugriffsschutz für Unternehmensabteilungen & Agenten-Hierarchie |
| [`tests/Feature/Ai/AiRoleManagerSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiRoleManagerSecurityTest.php) | Neue Testsuite: Zugriffsschutz für KI-Rollenverwaltung & Werkzeug-Zuweisung |
| [`tests/Feature/Ai/AiKnowledgeBaseSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiKnowledgeBaseSecurityTest.php) | Neue Testsuite: Zugriffsschutz, MIME-Whitelisting & Traversal-Sperre in Wissensdatenbank |
| [`tests/Feature/Ai/AiKnowledgeBaseTaxonomySecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiKnowledgeBaseTaxonomySecurityTest.php) | Neue Testsuite: Zugriffsschutz für Taxonomie (Kategorien & Tags) |
| [`tests/Feature/Ai/AiAgentEditorAndMethodSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiAgentEditorAndMethodSecurityTest.php) | Neue Testsuite: Zugriffsschutz für KI-Agenten-Editor & Methoden-Monitoring |
| [`app/Livewire/Shop/Master/MasterAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Master/MasterAnalytics.php) | Admin-Only-Guard in `mount()` und allen operativen Kontrollmethoden (IP-Sperren, Logs, System-Repair, Jobs) |
| [`app/Livewire/Shop/Ai/AiAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Ai/AiAnalytics.php) | Autorisierungsschutz in `mount()` für Token-Metriken & Inferenz-Kosten |
| [`app/Livewire/Shop/System/SystemDeliveryDisplay.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemDeliveryDisplay.php) | Feedback-Typen Whitelisting (`vacation, sick`) & Null-Sicherheit |
| [`resources/views/livewire/shop/system/system-delivery-display.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/system/system-delivery-display.blade.php) | Null-sichere Blade-Dereferenzierung für `$product` (`track_quantity`, `sku`) |
| [`app/Livewire/Shop/Product/ProductPackagingConfigurator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductPackagingConfigurator.php) | Method-Level Autorisierungsschutz in `addMaterial()`, `saveEdit()`, `deleteMaterial()` |
| [`app/Livewire/Shop/Order/OrderQuoteAcceptance.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderQuoteAcceptance.php) | Stückzahl-Sanitization & Overflow-Schutz (`min:1, max:10000`) |
| [`app/Services/Export/FileDownloadService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Export/FileDownloadService.php) | Autorisierungsgates für Downloads, SSRF-Filterung für Logos, SVG XSS-Sanitization, CSP/nosniff-Header & Null-Safety |
| [`app/Services/ReferralService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ReferralService.php) | Concurrency-Schutz via `DB::transaction` & `lockForUpdate()`, Idempotenz-Schutz gegen Mehrfachabzüge bei Storno |
| [`app/Services/Gamification/GamificationService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Gamification/GamificationService.php) | Concurrency-Schutz via `DB::transaction` & `lockForUpdate()`, Abweisung negativer Funkenbeträge |
| [`app/Http/Controllers/Api/TwilioCallController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/TwilioCallController.php) | Transcript-Array- und Längenvalidierung zur Kostenkontrolle gegen Gemini DoS |
| [`app/Http/Controllers/Api/TelegramAgentController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/TelegramAgentController.php) | Prompt-Injection-Schutz durch Bereinigung des Telegram-Vornamens |
| [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php) | Rate-Limiter für Webhook-Routen (`/telegram/webhook/`, `/twilio/outbound`, `/twilio/inbound`, `/twilio/call-log`) |
| [`app/Console/Commands/ExportProjectForAI.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/ExportProjectForAI.php) | Directory-Traversal-Schutz für Exportpfad mittels `basename()` |
| [`app/Console/Commands/GenerateTaxExport.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/GenerateTaxExport.php) | Wertebereichsprüfung für Monat/Jahr & Unterstützung von `local` und `public` Storage |
| [`tests/Feature/Master/MasterAnalyticsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Master/MasterAnalyticsSecurityTest.php) | Neue Testsuite: Striktes Admin-Only Gate für CEO Master Dashboard & System-Health |
| [`tests/Feature/Ai/AiAnalyticsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiAnalyticsSecurityTest.php) | Neue Testsuite: Autorisierungsschutz für KI-Analytics & Token-Metriken |
| [`tests/Feature/System/SystemDeliveryDisplaySecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/SystemDeliveryDisplaySecurityTest.php) | Neue Testsuite: Feedback-Whitelisting & Null-Safe Blade Rendering |
| [`tests/Feature/Product/ProductPackagingConfiguratorSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Product/ProductPackagingConfiguratorSecurityTest.php) | Neue Testsuite: Method-Level Zugriffsschutz für Verpackungsmaterial |
| [`tests/Feature/Order/OrderQuoteAcceptanceQuantitySecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderQuoteAcceptanceQuantitySecurityTest.php) | Neue Testsuite: Overflow- & DoS-Schutz bei Angebotsannahme-Stückzahlen |
| [`tests/Feature/Export/FileDownloadServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Export/FileDownloadServiceSecurityTest.php) | Neue Testsuite: Autorisierung, SSRF-Prävention & Stored-XSS-Schutz im FileDownloadService |
| [`tests/Feature/Marketing/ReferralServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Marketing/ReferralServiceSecurityTest.php) | Neue Testsuite: Idempotenz-Schutz & Concurrency bei Referral-Rewards |
| [`tests/Feature/Gamification/GamificationServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Gamification/GamificationServiceSecurityTest.php) | Neue Testsuite: Atomare Funken-Transaktionen & Negativwert-Sperre |
| [`tests/Feature/Support/TwilioCallControllerSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Support/TwilioCallControllerSecurityTest.php) | Neue Testsuite: Payload-Validierung & DoS-Schutz bei KI-Telefonielogs |
| [`tests/Feature/Console/ExportProjectForAISecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Console/ExportProjectForAISecurityTest.php) | Neue Testsuite: Path-Traversal-Schutz & CLI-Parameterüberprüfung |
| [`app/Services/AI/Functions/AiOrderFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiOrderFuncs.php) | Storno-Handling (`handleCancellation`) mit Laserwaren-Schutz & Spenden-Rückabwicklung bei KI-Statusänderung |
| [`app/Services/AI/Functions/AiMarketingVoucherFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMarketingVoucherFuncs.php) | SQL-Spaltenkorrektur (`total_price`) & Eingabevalidierung für Gutscheincode, -typ und Rabattwert |
| [`app/Http/Controllers/GoogleAuthController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/GoogleAuthController.php) | Domain- & HTTPS-Whitelisting für Profilbild-Download (`*.googleusercontent.com`) & Session-Regeneration |
| [`app/Livewire/Frontend/Emergency/EmergencyProtocol.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Frontend/Emergency/EmergencyProtocol.php) | Todo-Key-Whitelisting, Step-Clamping (1–5) & Passwort-Längenvalidierung |
| [`app/Http/Controllers/RevocationPdfController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/RevocationPdfController.php) | Deaktivierung von DomPDF Remote-Assets (`isRemoteEnabled => false`) |
| [`app/Http/Controllers/NewsletterController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/NewsletterController.php) | Längen- & Typvalidierung für Bestätigungstokens |
| [`routes/partials/global_routes.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/partials/global_routes.php) | Throttling für Widerrufs-PDF, Abmeldung, Newsletter-Verifikation & Stripe-Webhooks |
| [`tests/Feature/Ai/AiOrderFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiOrderFuncsSecurityTest.php) | Neue Testsuite: KI-Status-Stornierung & Laser-Ausschusswarenschutz |
| [`tests/Feature/Ai/AiMarketingVoucherFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMarketingVoucherFuncsSecurityTest.php) | Neue Testsuite: Gutschein-Validierung & SQL-Query Analytics |
| [`tests/Feature/Auth/GoogleAuthControllerSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Auth/GoogleAuthControllerSecurityTest.php) | Neue Testsuite: Avatar SSRF-Filterung & Unverified-Email-Ablehnung |
| [`tests/Feature/Frontend/EmergencyProtocolSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Frontend/EmergencyProtocolSecurityTest.php) | Neue Testsuite: Auth-Gate, Todo-Key-Whitelisting & Step-Clamping |
| [`tests/Feature/Global/GlobalRoutesAndControllersSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Global/GlobalRoutesAndControllersSecurityTest.php) | Neue Testsuite: DomPDF-Schutz & Newsletter-Verifikationsvalidierung |
| [`app/Http/Controllers/Customer/CustomerVerificationController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Customer/CustomerVerificationController.php) | Doppelsynchronisation (`customers` & `customer_profiles`) bei E-Mail-Verifikation |
| [`app/Http/Controllers/AIController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/AIController.php) | Payload-Validierung für Live-Transkripte & Entropie-Token für Kamera-Snapshots |
| [`routes/api/ai.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/ai.php) | Rate-Limiter für WebSocket-Tokenverifikation & Live-Transkripte |
| [`app/Services/AI/Functions/AiLaserFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiLaserFuncs.php) | DomPDF SSRF-Schutz (`isRemoteEnabled => false`), E-Mail-Validierung & Header-Sanitization |
| [`app/Services/AI/Functions/AiHolidayPlannerFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiHolidayPlannerFuncs.php) | DomPDF SSRF-Schutz, E-Mail-Validierung & Storage-Disk-Nutzung |
| [`app/Services/AI/Functions/AiMailFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMailFuncs.php) | Strikte E-Mail-Validierung via `filter_var` & CRLF-Stripping gegen Header-Injection |
| [`app/Services/AI/Functions/AiContactFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiContactFuncs.php) | Längenbegrenzung für Kontaktdaten & Wissenseinträge gegen DB-Overflow |
| [`app/Services/AI/Functions/AiSuppliersFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiSuppliersFuncs.php) | Spaltenkorrektur (`street`), Vorlaufzeiten-Clamping & relationale Lösch-Integrität |
| [`tests/Feature/Customer/CustomerVerificationSyncSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Customer/CustomerVerificationSyncSecurityTest.php) | Neue Testsuite: Verifikations-Synchronisation zwischen Customer & Profile |
| [`tests/Feature/Ai/AiLiveTranscriptSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiLiveTranscriptSecurityTest.php) | Neue Testsuite: Payload-Validierung & Kollisionsschutz bei Snapshots |
| [`tests/Feature/Ai/AiLaserAndHolidaySecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiLaserAndHolidaySecurityTest.php) | Neue Testsuite: DomPDF SSRF-Schutz & E-Mail-Validierung |
| [`tests/Feature/Ai/AiMailAndContactSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiMailAndContactSecurityTest.php) | Neue Testsuite: Mail-Header-Injection & Kontakt-String-Limits |
| [`tests/Feature/Ai/AiSuppliersSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiSuppliersSecurityTest.php) | Neue Testsuite: Lieferanten-Vorlaufzeiten, Adress-Spalte & Lösch-Integrität |
| [`app/Services/PriceCalcService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/PriceCalcService.php) | Division-by-Zero-Schutz & Steuersatz-Clamping (0-100%) sowie Wertebereichsbereinigung |
| [`app/Services/ShippingCalculatorService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ShippingCalculatorService.php) | Case-Normalisierung von Ländercodes (`strtoupper`) & Clamping von Warenkorbwerten |
| [`app/Services/FinancialService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/FinancialService.php) | Privater Export-Speicherpfad (`app/private/exports`), Dual-Disk-Belegsuche & DomPDF SSRF-Schutz |
| [`app/Services/AI/Functions/AiBrainFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiBrainFuncs.php) | Präzise Duplikatsprüfung & Titel-Priorisierung bei Wiki-Löschung gegen Broad-Matches |
| [`app/Services/AI/Functions/AiShoppingListFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiShoppingListFuncs.php) | Exakter Namensabgleich (`=`) gegen SQL-Wildcard-Injektionen & Feldlängen-Limits |
| [`tests/Feature/Price/PriceCalcServiceSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Price/PriceCalcServiceSecurityTest.php) | Neue Testsuite: Division-by-Zero-Schutz & Steuersatzberechnung |
| [`tests/Feature/Shipping/ShippingCalculatorSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Shipping/ShippingCalculatorSecurityTest.php) | Neue Testsuite: Ländercode-Normalisierung & Versandkosten-Clamping |
| [`tests/Feature/Accounting/FinancialServiceTaxExportSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Accounting/FinancialServiceTaxExportSecurityTest.php) | Neue Testsuite: Privater Speicherort für Steuerexporte |
| [`tests/Feature/Ai/AiBrainFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiBrainFuncsSecurityTest.php) | Neue Testsuite: Duplikaterkennung & selektives Löschen im Wiki |
| [`tests/Feature/Ai/AiShoppingListFuncsSecurityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/AiShoppingListFuncsSecurityTest.php) | Neue Testsuite: Schutz vor SQL-Wildcard-Injektion bei Einkaufsartikeln |

---

## 4. Test- & Verifikationsergebnisse

In der Docker-Umgebung (`mein_php_server`, PHP 8.4) wurden alle Tests erfolgreich validiert:
* **Sicherheits- & Autorisierungs-Testsuiten (Gesamt):** 303 Tests bestanden (946 Assertions, 0 Fehler)
  * `FirebaseServiceSecurityTest`: 2/2 bestanden (Push-Token-Prüfung, verschachtelte Daten & Admin-Geräte-Filter)
  * `GoogleReviewsServiceSecurityTest`: 2/2 bestanden (Fallback-Reviews, Google Places Caching & Resilienz)
  * `TaxSettingServiceSecurityTest`: 2/2 bestanden (Division-by-Zero-Schutz & transaktionssichere Status-Umschaltung)
  * `AccountingXmlInvoiceServiceSecurityTest`: 1/1 bestanden (XML-Dateinamens-Sanitization & Sonderzeichen-Encoding)
  * `AiMapControlFuncsSecurityTest`: 2/2 bestanden (RFC-E-Mail-Validierung & CRLF-Injection-Schutz bei Map-PDFs)
  * `AiMarketingFuncsSecurityTest`: 2/2 bestanden (Wertebereichs-Clamping, Code-Sanitization & Kollisionssicherheit)
  * `AiMasterFuncsSecurityTest`: 2/2 bestanden (Maintenance-Passphrase, Whitelisting & dynamisches Secret)
  * `AiScoutFuncsSecurityTest`: 2/2 bestanden (Mindestlänge, Wildcard-Escaping & `total_price`-Berechnung)
  * `AiNewsFuncsSecurityTest`: 2/2 bestanden (News-Validierung, Error-Masking & YouTube-Extraktion)
  * `AiPersonaFuncsSecurityTest`: 2/2 bestanden (SSRF-Schutz, E-Mail-Validierung & Titelprüfung)
  * `AiMarketingBlogFuncsSecurityTest`: 2/2 bestanden (Foreign-Key-Korrektur, Slug-Kollisionsauflösung & Wildcard-Escaping)
  * `AiMarketingNewsletterFuncsSecurityTest`: 2/2 bestanden (Offset-Clamping, String-Truncation & Boolean-Archive-Toggle)
  * `AiTelefonyFuncsSecurityTest`: 2/2 bestanden (Status-Whitelisting, Mass-Assignment & Search-Clamping)
  * `HerzensprojektServiceSecurityTest`: 3/3 bestanden (Spendenbuchungs-Idempotenz & robuste Storno-Rückbuchung)
  * `AiTaskFuncsSecurityTest`: 2/2 bestanden (Wildcard-Löschschutz, Mindestlängen & Listenauflösung)
  * `CartFileCleanupServiceSecurityTest`: 3/3 bestanden (Pfadtraversal-Schutz, Wildcard-Escaping & chunked Löschung verlassener Warenkörbe)
  * `ConfiguratorServiceBoundsTest`: 2/2 bestanden (2D-Koordinaten- & 3D-Skalierungs-Clamping gegen WebGL/Canvas-Crashes)
  * `ProductTrackingServiceSecurityTest`: 2/2 bestanden (Stringlängen-Truncation & INT-Overflow-Schutz bei Tracking-Events)
  * `AiCalendarFuncsSecurityTest`: 2/2 bestanden (Wildcard-Löschschutz, Such-Mindestlänge & chronologische Validierung)
  * `AiHealthFuncsSecurityTest`: 3/3 bestanden (IDOR-Schutz bei Aktualisierung, Abschluss & privater Export medizinischer Akten)
  * `PriceCalcServiceSecurityTest`: 2/2 bestanden (Division-by-Zero-Schutz & Steuersatzberechnung)
  * `ShippingCalculatorSecurityTest`: 2/2 bestanden (Ländercode-Normalisierung, Schwellenwerte & Versandkosten-Clamping)
  * `FinancialServiceTaxExportSecurityTest`: 1/1 bestanden (Privater Speicherort für Steuerexporte & Beleg-Integration)
  * `AiBrainFuncsSecurityTest`: 2/2 bestanden (Duplikaterkennung & selektives Löschen im Wiki)
  * `AiShoppingListFuncsSecurityTest`: 1/1 bestanden (Schutz vor SQL-Wildcard-Injektion bei Einkaufsartikeln)
  * `CustomerVerificationSyncSecurityTest`: 1/1 bestanden (Verifikations-Synchronisation zwischen Customer & Profile)
  * `AiLiveTranscriptSecurityTest`: 3/3 bestanden (Payload-Validierung & Kollisionsschutz bei Snapshots)
  * `AiLaserAndHolidaySecurityTest`: 2/2 bestanden (DomPDF SSRF-Schutz & E-Mail-Validierung)
  * `AiMailAndContactSecurityTest`: 2/2 bestanden (Mail-Header-Injection & Kontakt-String-Limits)
  * `AiSuppliersSecurityTest`: 2/2 bestanden (Lieferanten-Vorlaufzeiten, Adress-Spalte & Lösch-Integrität)
  * `AiOrderFuncsSecurityTest`: 2/2 bestanden (KI-Status-Stornierung, Standardwaren-Restoration & strikter Schutz von Laser-Ausschussware)
  * `AiMarketingVoucherFuncsSecurityTest`: 2/2 bestanden (SQL-Spaltenkorrektur `total_price` & Wertebereichsvalidierung für Gutscheine)
  * `GoogleAuthControllerSecurityTest`: 2/2 bestanden (Avatar SSRF-Prävention, Unverified-Email-Block & Session-Regeneration)
  * `EmergencyProtocolSecurityTest`: 2/2 bestanden (Auth-Gate, Key-Whitelisting & Step-Clamping)
  * `GlobalRoutesAndControllersSecurityTest`: 3/3 bestanden (DomPDF Remote-Deaktivierung, Newsletter-Token-Validierung & Throttling)
  * `FileDownloadServiceSecurityTest`: 3/3 bestanden (Autorisierung, SSRF-Filterung & Stored-XSS-Schutz im FileDownloadService)
  * `ReferralServiceSecurityTest`: 1/1 bestanden (Idempotenz-Schutz & Vermeidung doppelter Storno-Abzüge bei Belohnungsgutscheinen)
  * `GamificationServiceSecurityTest`: 2/2 bestanden (Atomare Zeilensperren für Funken-Guthaben & Negativwert-Abweisung)
  * `TwilioCallControllerSecurityTest`: 3/3 bestanden (Rate-Limiting & Transcript-Arraybegrenzung gegen DoS/Kostenfallen)
  * `ExportProjectForAISecurityTest`: 2/2 bestanden (Directory-Traversal-Sperre via `basename()` & CLI-Parameterüberprüfung)
  * `MasterAnalyticsSecurityTest`: 3/3 bestanden (Striktes Admin-Only Gate für CEO Dashboard & Kontrollaktionen)
  * `AiAnalyticsSecurityTest`: 3/3 bestanden (Autorisierung für KI-Metriken & LLM-Kosten)
  * `SystemDeliveryDisplaySecurityTest`: 3/3 bestanden (Feedback-Typen Whitelist & Null-Safety)
  * `ProductPackagingConfiguratorSecurityTest`: 3/3 bestanden (Method-Level Autorisierung für Verpackungsdaten)
  * `OrderQuoteAcceptanceQuantitySecurityTest`: 1/1 bestanden (Mengen-Clamping auf max 10.000 Einheiten)
  * `AiCompanyStructureSecurityTest`: 3/3 bestanden (Autorisierung für Abteilungen & Agenten-Verschiebung)
  * `AiRoleManagerSecurityTest`: 3/3 bestanden (Autorisierung für Rollen & Tool-Zuweisung)
  * `AiKnowledgeBaseSecurityTest`: 3/3 bestanden (Autorisierung, MIME-Whitelist & Traversal-Sperre)
  * `AiKnowledgeBaseTaxonomySecurityTest`: 3/3 bestanden (Autorisierung für Kategorien & Tags)
  * `AiAgentEditorAndMethodSecurityTest`: 3/3 bestanden (Autorisierung für Agenten-Editor & Methodenüberwachung)
  * `SystemBackupsSecurityTest`: 3/3 bestanden (Striktes Admin-Only Gate für Backup-Dumps, Download & Löschung)
  * `ManagementLinktreeSecurityTest`: 3/3 bestanden (Autorisierung für Linktree-Verwaltung & System-Settings)
  * `SystemNeuralAnalysisSecurityTest`: 3/3 bestanden (Autorisierung für neuronale Architekturanalysen)
  * `ProductControlReviewsSecurityTest`: 3/3 bestanden (Autorisierung für Moderation, Freigabe & Löschung von Reviews)
  * `MarketingLandingPagesSecurityTest`: 3/3 bestanden (Autorisierung für Landingpage-Generierung, Edit & Löschung)
  * `SystemShopConfigSecurityTest`: 4/4 bestanden (Secrets-Leakage, Path-Traversal & Admin-Only-Guard)
  * `OrderRevocationProcessingStockTest`: 2/2 bestanden (Widerrufs-Autorisierung, Standardartikel-Restore & strikte Laser-Ausschussgarantie)
  * `AccountingCreditAndInvoicePreviewSecurityTest`: 3/3 bestanden (Autorisierung für Gutschriften & Rechnungs-PDFs)
  * `SystemInfrastructureAuthorizationTest`: 3/3 bestanden (Versand, Lieferzeiten & Admin-Only Logs)
  * `OperationalToolsAuthorizationTest`: 3/3 bestanden (Support-Telefonie, Kontaktformulare, Analytics & Cronjobs)
  * `ProductCalculatorSecurityTest`: 3/3 bestanden (Mengen-Sanitization, Produkt-Existenzprüfung & Rate-Limiter im Mengenrechner)
  * `ProductVariantsAndTaxSecurityTest`: 4/4 bestanden (Autorisierungsschutz & Wertebereichsbereinigung für Varianten und Steuer)
  * `ProductTierPricingSecurityTest`: 4/4 bestanden (Staffelpreise IDOR-Schutz, Prozent-Clamping & Mengenbereinigung)
  * `ProductCatalogBackendAuthorizationTest`: 3/3 bestanden (Autorisierungsschutz für Attribute, Kategorien, Vorlagen & Crawler)
  * `CustomerSecurityAndBlogAuthorizationTest`: 7/7 bestanden (Zwangspasswort-Sicherheit, Kunden-Rechnungen & Blog-Autorisierung)
  * `MarketingVoucherSecurityTest`: 4/4 bestanden (Gutschein-Wertebereichs- & Autorisierungsvalidierung)
  * `ManagementAccessSecurityTest`: 3/3 bestanden (Autorisierungsschutz für alle 6 Management-Backend-Komponenten)
  * `SupportComponentsAuthorizationTest`: 3/3 bestanden (Autorisierungsschutz für Support-Tickets & Support-Chats)
  * `ProductManagementAuthorizationTest`: 3/3 bestanden (Autorisierungsschutz für Produktneuanlage, Lieferanten, Bruchware & Verpackung)
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
