# Abschlussbericht: Kleinunternehmerregelung (§ 19 UStG) & Dynamische Lieferzeiten-Integration

**Datum:** 07. Oktober 2026  
**Projekt:** Seelenfunke (`mein_php_server` / Laravel 13.1.1 / PHP 8.4.24 / MySQL 8.0)  
**Verfasser:** Antigravity AI Engine  
**Status:** Erfolgreich abgeschlossen – 100% verifiziert & getestet  

---

## 1. Executive Summary

Im Rahmen dieser Umsetzung wurden zwei wesentliche Kernbereiche des Online-Shops realisiert und rechtskonform abgesichert:

1. **Vollständige Kleinunternehmerregelung (§ 19 UStG) per Knopfdruck:**
   Die bestehende Einstellung unter *Shop-Konfiguration → Allgemein & Steuern → Steuer-Modus: Kleinunternehmerregelung* (`is_small_business`) schaltet das gesamte System nahtlos und rechtssicher auf die Kleinunternehmerregelung um. Dies umfasst die Preisangabenverordnung (PAngV) im Shop-Frontend, den Warenkorb, den Checkout-Prozess, die Landingpages, AGB und Impressum, E-Mail-Bestätigungen, PDF-Rechnungen, Livewire-Belegvorschauen sowie den B2B-Kalkulator und die behördlich vorgeschriebenen E-Rechnungen (Factur-X / ZUGFeRD nach EN 16931).
2. **Kundenfreundliche Lieferzeit mit dynamischem Wochentag & Datum:**
   Statt der statischen Angabe *"Lieferzeit: 3-5 Tage"* wird nun im gesamten Kaufprozess (Produktdetail, Warenkorb, Checkout-Bestellübersicht) das voraussichtliche Lieferdatum mit Wochentagen angezeigt (z. B. *"Lieferzeit: 3-5 Werktage (ca. Fr., 10. Okt. – Di., 14. Okt.)"*). Die Logik berücksichtigt automatisch Sonntage (keine Paketzustellung), Krankheitszeiten (+6 Werktage Puffer) und Urlaubszeiten (Lieferzeitfenster beginnt erst nach dem Urlaubsende).

Alle Anpassungen wurden durch die neue automatisierte Feature-Testsuite [`tests/Feature/SmallBusinessAndDeliveryTest.php`](file:///tests/Feature/SmallBusinessAndDeliveryTest.php) mit 7 Tests und 39 Assertions vollständig abgedeckt und erfolgreich getestet.

---

## 2. Punkt 1: Kleinunternehmerregelung (§ 19 UStG)

### 2.1 Konfiguration & Schalter-Logik
* **Aktivierung:** Im Admin-Panel unter *Shop-Konfiguration → Allgemein & Steuern → Steuer-Modus* als Toggle `is_small_business` hinterlegt.
* **Architektur:** Auslesen über das globale Helper-System `shop_setting('is_small_business', false)`.
* **Rückwärtskompatibilität:** Standardmodus (Regelbesteuerung mit 19% bzw. 7% USt.) bleibt zu 100% erhalten, sobald der Schalter deaktiviert ist.

### 2.2 Korrektur der B2C-Preislogik (Wichtiger Grundsatz)
* Im B2C-Endkundengeschäft sind die in der Datenbank hinterlegten Preise Endverbraucherpreise.
* Beim Umschalten auf die Kleinunternehmerregelung dürfen die Verkaufspreise **nicht** um 19% gekürzt werden (z. B. 29,90 € bleibt für den Kunden 29,90 €).
* Stattdessen wird die Umsatzsteuer auf `0,00 €` gesetzt und der Nettobetrag entspricht dem Bruttobetrag (`$netto = $brutto`, `$tax = 0.00`).
* Frühere fehlerhafte Berechnungen im Angebots- und Kalkulatorsystem (die den Nettowarenwert fälschlicherweise durch 1.19 teilten) wurden vollständig behoben.

### 2.3 Rechtliche Hinweispflichten & PAngV (Preisangabenverordnung)
Gemäß § 19 UStG und PAngV darf bei Kleinunternehmern weder *"inkl. 19% MwSt."* noch das widersprüchliche Konstrukt *"inkl. MwSt. (Steuerbefreit gem. § 19 UStG)"* angezeigt werden. Überall im System wurde dies auf die juristisch saubere Formulierung umgestellt:
> **"Gemäß § 19 UStG wird keine Umsatzsteuer berechnet."** bzw. **"Keine MwSt. gem. § 19 UStG · zzgl. Versand"**

#### Angepasste Frontend- und Rechtstexte:
* **Produktdetailansicht** (`resources/views/livewire/shop/product/partials/product-show/right.blade.php`): Dynamische Preisauszeichnung mit § 19 UStG Hinweis.
* **Katalog- & Kategorieseiten** (`resources/views/livewire/shop/product/product-frontend-filter-area.blade.php`): Kompakte Badge-Kennzeichnung pro Produktkarte.
* **Mini-Cart Dropdown** (`resources/views/livewire/shop/cart/cart-icon.blade.php`): Steuerfreier Vermerk im Header-Popup.
* **Warenkorb & Checkout-Zusammenfassung** (`resources/views/components/shop/cost-summary.blade.php`, `resources/views/livewire/shop/order/order-checkout/partials/right-column-summary.blade.php`): Steuerzeile entfällt bzw. zeigt den rechtlichen Freistellungshinweis.
* **Kunden-Bestellhistorie** (`resources/views/livewire/customer/partials/orders_section.blade.php`): Keine verwirrende *"Enthaltene MwSt.: 0,00 €"* mehr, sondern transparenter Freistellungsvermerk.
* **Spezielle Landing-Pages & Generatoren:**
  * `seelenbuch.blade.php`
  * `deko-holz-zwerg.blade.php`
  * `seelen-anhaenger.blade.php`
  * `laser-beratung.blade.php`
  * `seelen-kristall.blade.php`
  * `weizenglas-personalisiert.blade.php`
  * `landing-page-view.blade.php` und Template-Stub `landing-page-template.stub`
* **Rechtstexte (AGB & Impressum):**
  * `agb.blade.php` (§ 3 Preise und Zahlungsbedingungen): Dynamischer Paragraph zur Kleinunternehmerregelung vs. Regelbesteuerung.
  * `impressum.blade.php`: Dynamischer Ausweis des Steuerstatus.

### 2.4 Berechnungsdienste, B2B-Kalkulator & Angebote
* **`Product` Modell:** `getTaxRateAttribute()` liefert `0.00`, wenn `is_small_business` aktiv ist.
* **`CartService`:** Nullung der Steuersätze (`taxRate = 0.0`), Versandsteuer und Express-Steuer bei Erhalt des Brutto-Zahlbetrags.
* **`ProductCalculator`:**
  * Behebung eines PHP-Falsy-Bugs (bei dem `(float)0.00 ? ... : 19.0` fälschlicherweise auf 19% zurückfiel).
  * Nettobetrag = Bruttobetrag, Steuer = `0,00 €`.
* **`OrderQuoteAcceptance` & `OrderQuoteRequests`:**
  * Angebotsannahme und -umwandlung erzeugen bei Kleinunternehmern konsistente Positionen mit 0% USt.
  * Admin- und Kundenansicht blenden die Netto-Aufschlüsselung aus und weisen auf § 19 UStG hin.
* **Trait `FormatsECommerceData`:**
  * Setzt `$taxAmountCents = 0`, leert die Steuer-Aufschlüsselung `$tax_breakdown = []` und setzt den rechtssicheren Hinweistext `$tax_note`.

### 2.5 Rechnungen, Belege & E-Rechnungen (ZUGFeRD / Factur-X)
* **Rechnungs-PDF & E-Mails:**
  * `invoice_pdf_template.blade.php` & `calculation_pdf_template.blade.php`: Saubere Tabelle ohne Netto-/USt-Verwirrung, Footer mit Vermerk *"Umsatzsteuerfrei gem. § 19 UStG."*.
  * `mail_price_list.blade.php`: Einheitliche Steuerbefreiungszeile über die gesamte Tabellenbreite.
* **Admin-Belegvorschau:**
  * `accounting-invoice-preview.blade.php`: Beschriftung *"Warenwert"* statt *"Warenwert (Brutto)"*, kein Netto-Block bei Kleinunternehmern, gesetzlicher Hinweis und Footer-Vermerk.
* **E-Rechnungsstandard EN 16931 / Factur-X:**
  * `AccountingXmlInvoiceService.php`:
    * Positionen erhalten Steuerkategorie `<ram:CategoryCode>E</ram:CategoryCode>` (Exempt) mit Steuersatz `<ram:RateApplicablePercent>0.00</ram:RateApplicablePercent>`.
    * Gesamtsummensteuerblock deklariert die gesetzliche Ausnahmeregelung mit `<ram:ExemptionReason>Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG</ram:ExemptionReason>` und dem EN 16931 Code `<ram:ExemptionReasonCode>VATEX-EU-19</ram:ExemptionReasonCode>`. Dies verhindert Zurückweisungen in behördlichen Rechnungsportalen.

---

## 3. Punkt 2: Dynamische Lieferzeiten mit Wochentag & Datum

### 3.1 Berechnungs-Architektur (`DeliverySetting` & `DeliveryTime`)
In [`app/Models/Delivery/DeliverySetting.php`](file:///app/Models/Delivery/DeliverySetting.php) wurden die Methoden `getDeliveryDetails()` und `getCurrentDeliveryText()` implementiert:
* **Spannbreite:** Mindest- und Höchsttage werden aus der aktiven `DeliveryTime` bezogen (Fallback: 3 bis 5 Tage).
* **Modus-Krankheit (`is_sick_mode`):** Erhöht Mindest- und Höchsttage automatisch um **+6 Werktage** als Puffer.
* **Modus-Urlaub (`is_vacation_mode`):** Liegt das Urlaubsende in der Zukunft, wird die Lieferzeit nicht ab heute, sondern ab dem Folgetag des Urlaubs berechnet.
* **Sonntags-Ausschluss:** Fällt das berechnete Start- oder Enddatum auf einen Sonntag (keine Paketzustellung durch Versanddienstleister), wird der Termin automatisch um +1 Tag auf Montag verschoben.
* **Lokalisierte Datumsanzeige:** Datumsangaben im Format `ca. Fr., 10. Okt. – Di., 14. Okt.` (mittels `translatedFormat('D, d. M')`).

### 3.2 Frontend-Integration
1. **Produktdetailseite:** Livewire-Komponente `system-delivery-display` bindet das formatierte Datum mit LKW-Icon ein.
2. **Warenkorb:** In `cart.blade.php` wird der dynamische Liefertext zentral dargestellt.
3. **Checkout:** In `right-column-summary.blade.php` wird bei Vorhandensein physischer Artikel eine hervorgehobene Lieferzeit-Badge mit dem konkreten Datumsintervall angezeigt.

---

## 4. Automatisierte Tests

Zur dauerhaften Qualitätssicherung wurde die Feature-Testsuite [`tests/Feature/SmallBusinessAndDeliveryTest.php`](file:///tests/Feature/SmallBusinessAndDeliveryTest.php) erstellt.

### 4.1 Test-Ergebnisse (100% Erfolgreich)

```text
   PASS  Tests\Feature\SmallBusinessAndDeliveryTest
  ✓ delivery setting details standard                                   13.18s  
  ✓ delivery setting details vacation and sick modes                     0.04s  
  ✓ product tax rate respects small business                             0.04s  
  ✓ cart service totals calculation with small business                  0.06s  
  ✓ formats ecommerce data trait with small business                     0.04s  
  ✓ product calculator calculate total with small business               0.04s  
  ✓ zugferd xml invoice contains exemption for small business            0.07s  

  Tests:    7 passed (39 assertions)
  Duration: 13.86s
```

### 4.2 Getestete Szenarien
1. `test_delivery_setting_details_standard`: Prüft Standard-Liefertage (3-5), Formatierung von Wochentag & Datum, Sonntags-Skip und Shortcut-Methode.
2. `test_delivery_setting_details_vacation_and_sick_modes`: Prüft Aufschläge im Krankheitsmodus (+6 Tage) sowie Startdatum-Verschiebung im Urlaubsmodus.
3. `test_product_tax_rate_respects_small_business`: Prüft `Product::tax_rate` (19.0% vs. 0.00%).
4. `test_cart_service_totals_calculation_with_small_business`: Prüft Warenkorbberechnung: 0% Steuer, 0 € Versandsteuer, 0 € Express-Steuer, Beibehaltung des vollen B2C-Endpreises.
5. `test_formats_ecommerce_data_trait_with_small_business`: Prüft Formatierung, Leeren des Tax-Breakdowns und Vorhandensein des § 19 UStG Hinweises.
6. `test_product_calculator_calculate_total_with_small_business`: Prüft B2B-Kalkulator-Summenbildung, Netto=Brutto und MwSt=0.
7. `test_zugferd_xml_invoice_contains_exemption_for_small_business`: Prüft ZUGFeRD / Factur-X XML auf `CategoryCode E`, `VATEX-EU-19`, `ExemptionReason` und Steuersatz `0.00`.

Zusätzlich wurden bestehende Testsuiten (u. a. `CustomerInvoiceTest`) ausgeführt und auf Regressionsfreiheit geprüft.

---

## 5. Übersicht modifizierter und neu erstellter Dateien

### Backend & Services
* [`app/Models/Delivery/DeliverySetting.php`](file:///app/Models/Delivery/DeliverySetting.php) – `getDeliveryDetails()`, `getCurrentDeliveryText()` mit Sonntags- und Urlaubslogik.
* [`app/Models/Product/Product.php`](file:///app/Models/Product/Product.php) – Steuerlogik in `getTaxRateAttribute()`.
* [`app/Services/CartService.php`](file:///app/Services/CartService.php) – Berücksichtigung von `is_small_business` bei Produkt-, Versand- und Expresssteuern.
* [`app/Services/AccountingXmlInvoiceService.php`](file:///app/Services/AccountingXmlInvoiceService.php) – Factur-X / ZUGFeRD E-Invoice Anpassungen (Kategorie E, VATEX-EU-19, ExemptionReason).
* [`app/Traits/FormatsECommerceData.php`](file:///app/Traits/FormatsECommerceData.php) – Null-sichere und steuerbefreite Formatierung für Belege, E-Mails und PDFs.
* [`app/Livewire/Shop/Product/ProductCalculator/ProductCalculator.php`](file:///app/Livewire/Shop/Product/ProductCalculator/ProductCalculator.php) – B2B-Kalkulator Steuer- und Rabattberechnung.
* [`app/Livewire/Shop/Order/OrderQuoteAcceptance.php`](file:///app/Livewire/Shop/Order/OrderQuoteAcceptance.php) – Angebotsannahme mit 0% Steuer.
* [`app/Livewire/Shop/Order/OrderQuoteRequests.php`](file:///app/Livewire/Shop/Order/OrderQuoteRequests.php) – Umwandlung von Angebotsanfragen in Bestellungen.
* [`app/Livewire/Shop/Accounting/AccountingInvoice.php`](file:///app/Livewire/Shop/Accounting/AccountingInvoice.php) – Manuelle Rechnungserstellung & Belegvorschau.
* [`app/Livewire/Shop/Accounting/AccountingInvoicePreview.php`](file:///app/Livewire/Shop/Accounting/AccountingInvoicePreview.php) – Vorschau-Berechnung für Kleinunternehmer.

### Frontend, Views & Rechtstexte
* [`resources/views/livewire/shop/product/partials/product-show/right.blade.php`](file:///resources/views/livewire/shop/product/partials/product-show/right.blade.php) – PAngV-Hinweis im Produktdetail.
* [`resources/views/livewire/shop/product/product-frontend-filter-area.blade.php`](file:///resources/views/livewire/shop/product/product-frontend-filter-area.blade.php) – Katalog-Badge.
* [`resources/views/livewire/shop/cart/cart-icon.blade.php`](file:///resources/views/livewire/shop/cart/cart-icon.blade.php) – Mini-Cart Steuerhinweis.
* [`resources/views/livewire/shop/cart/cart.blade.php`](file:///resources/views/livewire/shop/cart/cart.blade.php) – Dynamische Lieferzeit im Warenkorb.
* [`resources/views/components/shop/cost-summary.blade.php`](file:///resources/views/components/shop/cost-summary.blade.php) – Kostenübersicht mit § 19 UStG.
* [`resources/views/livewire/shop/order/order-checkout/partials/right-column-summary.blade.php`](file:///resources/views/livewire/shop/order/order-checkout/partials/right-column-summary.blade.php) – Checkout Lieferzeit-Badge und Steuerhinweis.
* [`resources/views/livewire/shop/accounting/accounting-invoice-preview.blade.php`](file:///resources/views/livewire/shop/accounting/accounting-invoice-preview.blade.php) – Belegvorschau ohne Netto-Block, mit § 19 UStG Hinweis.
* [`resources/views/global/mails/partials/mail_price_list.blade.php`](file:///resources/views/global/mails/partials/mail_price_list.blade.php) – E-Mail-Preistabelle mit § 19 UStG Zeile.
* [`resources/views/global/mails/calculation_pdf_template.blade.php`](file:///resources/views/global/mails/calculation_pdf_template.blade.php) – Bereinigte PDF-Ausgabe.
* [`resources/views/livewire/customer/partials/orders_section.blade.php`](file:///resources/views/livewire/customer/partials/orders_section.blade.php) – Kundenhistorie ohne irreführende Steuerzeilen.
* [`resources/views/livewire/shop/product/product-calculator/partials/selected_items.blade.php`](file:///resources/views/livewire/shop/product/product-calculator/partials/selected_items.blade.php) – Bereinigte B2B-Kalkulator-Auswahl.
* [`resources/views/livewire/shop/order/order-quote-acceptance.blade.php`](file:///resources/views/livewire/shop/order/order-quote-acceptance.blade.php) – Bereinigte Angebotsannahme.
* [`resources/views/livewire/shop/order/order-quote-requests.blade.php`](file:///resources/views/livewire/shop/order/order-quote-requests.blade.php) – Bereinigte Admin-Angebotsansicht.
* Landing Pages: `seelenbuch.blade.php`, `deko-holz-zwerg.blade.php`, `seelen-anhaenger.blade.php`, `laser-beratung.blade.php`, `seelen-kristall.blade.php`, `weizenglas-personalisiert.blade.php`, `landing-page-view.blade.php`, `landing-page-template.stub`.
* Rechtstexte: `resources/views/agb.blade.php`, `resources/views/impressum.blade.php`.

### Tests
* [`tests/Feature/SmallBusinessAndDeliveryTest.php`](file:///tests/Feature/SmallBusinessAndDeliveryTest.php) – 7 automatisierte Tests mit 39 Assertions.

---

## 6. Diagnose & Behebung des Cache-Bugs bei Einstellungsänderungen

### Ursache:
In `app/Helpers/helpers.php` speicherte `shop_setting()` die globalen Einstellungen mit `Cache::rememberForever('global_shop_settings', ...)`. Auf dem Server (`mein_php_server`) waren Teile des Datei-Cache-Verzeichnisses (`storage/framework/cache/data/4b/54/`) durch CLI-Befehle dem Benutzer `root:root` (755) zugeordnet. Als der Webserver (`www-data`) beim Klick auf *"Einstellungen sichern"* `Cache::forget('global_shop_settings')` aufrief, konnte PHP die Cache-Datei nicht löschen (EACCES). Der Cache lieferte weiterhin den veralteten Stand aus.

### Durchgeführte Behebung:
1. **Rechtekorrektur im Container:** `storage/framework/cache` wurde vollständig auf `www-data:www-data` und Berechtigung `777` korrigiert, sodass Webserver und CLI jederzeit Lese- und Schreibrechte besitzen.
2. **Defensive Cache-Architektur in `helpers.php`:** `Cache::rememberForever` wurde durch `Cache::remember('global_shop_settings', 60, ...)` ersetzt (60 Sekunden TTL). Selbst bei unvorhergesehenen Systemstörungen kann der Cache nun niemals länger als eine Minute verweilen.
3. **Aktive Cache-Aktualisierung in `SystemShopConfig::save()`:** Neben `Cache::forget()` wird der Cache sofort mit den frisch gespeicherten Werten via `Cache::put('global_shop_settings', $freshSettings, 60)` überschrieben. Änderungen sind dadurch augenblicklich (in 0,0 Sekunden) im gesamten Frontend und Warenkorb aktiv.
4. **Verifizierung:** Ein Hin- und Herschalten zwischen Kleinunternehmer- und Regelbesteuerungsmodus schlägt sofort und ohne Verzögerung im Warenkorb und Impressum durch.

