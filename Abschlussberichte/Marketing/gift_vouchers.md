# Abschlussbericht: Implementation der Geschenkgutscheine (Wertgutscheine)

## 1. Einleitung & Zielsetzung
Ziel dieses Teilprojekts war die Einführung von kaufbaren Geschenkgutscheinen (Wertgutscheinen) im Seelenfunken-Shop. Im Gegensatz zu reinen Werbe- oder Rabattcodes (Aktionsgutscheinen) müssen Geschenkgutscheine wie ein alternatives Zahlungsmittel (Bargeldersatz) behandelt werden. 

Die wichtigsten Anforderungen waren:
* **Gesetzliche Konformität (BGB):** Gültigkeit von 3 Jahren (§ 195 BGB) ab dem Ende des Kaufjahres.
* **Steuerliche Behandlung (Mehrzweck-Gutschein):** Da zum Kaufzeitpunkt des Gutscheins noch nicht feststeht, welche Produkte (mit welchen Steuersätzen) damit erworben werden, erfolgt der Kauf steuerfrei (0% USt.). Die tatsächliche Versteuerung findet erst bei der Einlösung statt.
* **Teileinlösungen:** Ein verbleibendes Restguthaben muss auf dem Gutscheincode gespeichert und für spätere Einkäufe genutzt werden können.
* **Umfassende Abdeckung:** Der Gutschein muss die gesamte Bestellsumme (Warenwert + Versandkosten + Express-Service) mindern können.

---

## 2. Systemarchitektur & Datenbank-Schema

Die Datenhaltung wurde über zwei neue Tabellen und die zugehörigen Eloquent-Models realisiert:

### 2.1 Model: `MarketingGiftVoucher`
* **Tabelle:** `marketing_gift_vouchers`
* **Aufgabe:** Speicherung des Gutscheincodes, des Initialwerts, des aktuellen Guthabens (`current_balance`), des Empfängers, der Zustellungsart sowie des Gültigkeitsdatums.
* **Code-Generierung:** Das System generiert eindeutige, zufällige Codes im Format `SEELENFUNKE-XXXX-XXXX` (z. B. `SEELENFUNKE-A3F9-X8Y1`), um Missbrauch vorzubeugen.
* **Gültigkeitsprüfung (`isValid()`):** Prüft, ob der Gutschein aktiv ist, ein Restguthaben von $> 0$ besitzt und das Gültigkeitsdatum noch in der Zukunft liegt.

### 2.2 Model: `MarketingGiftVoucherLog`
* **Tabelle:** `marketing_gift_voucher_logs`
* **Aufgabe:** Lückenlose Protokollierung aller Abbuchungen. Jede Einlösung wird mit der Bestell-ID, dem genutzten Betrag und dem verbleibenden Restguthaben historisiert.

---

## 3. Produktkonfiguration & Kaufprozess

Der Geschenkgutschein ist im System als ein spezielles digitales Produkt mit dem Slug `geschenkgutschein` angelegt.

### 3.1 Livewire-Kauf-Konfigurator (`MarketingVoucherPurchasePage`)
Die Konfiguration des Gutscheins erfolgt direkt auf der Produktdetailseite über eine interaktive Oberfläche:
* **Betragsauswahl:** Vordefinierte Buttons (10 €, 25 €, 50 €, 100 €) oder freie Eingabe über "Anderen Wunschbetrag wählen".
  * *Validierung:* Der Wunschbetrag muss mindestens **5,00 €** und maximal **1.000,00 €** betragen und zwingend in **5er-Schritten** erfolgen.
* **Zustellungsarten:**
  1. **E-Mail (Digital):** Kostenloser, sofortiger Versand nach Zahlungseingang als hochauflösendes PDF-Dokument per Mail.
  2. **Post (Physisch):** Hochwertiger Postversand mit einem festen Versandkostenaufpreis von **3,50 €** (konfigurierbar über die Systemeinstellungen als `shipping_cost_voucher`).
* **Conversion-Booster:** Einbindung eines Live-Guthabenprüfers (`MarketingVoucherBalanceChecker`) sowie verkaufsfördernder Trust-Badges (inkl. Verlinkung der BGB-Verjährungsfrist auf die AGB-Seite, die sich in einem neuen Tab öffnet).

---

## 4. Warenkorb- & Einlösungslogik (`CartService`)

Die Berechnung der Gutscheine wurde tief in die Kernberechnung des Warenkorbs integriert:
* **Verhinderung von Doppel-Rabatten:** Rabattcodes (z. B. `10% Rabatt`) können nicht auf den Kauf von Geschenkgutscheinen angewendet werden. Bei gemischten Warenkörben wird der Rabattcode proportional nur auf die normalen Produkte angewendet.
* **Gesamtdeckungs-Prinzip:** Bei der Einlösung eines Geschenkgutscheins wird der Abzug erst berechnet, nachdem der Warenwert, die Versandkosten und eventuelle Express-Gebühren addiert wurden. Das Guthaben zieht somit auch die Versandkosten ab.
* **Proportionale Steuerkorrektur:** Bei einer Reduzierung des Warenwerts durch den Gutschein wird die ausgewiesene Mehrwertsteuer der betroffenen Produkte und der Versandkosten proportional gemindert, um den steuerlichen Vorgaben zu entsprechen.

---

## 5. Checkout & Stripe-Bypass

Wenn ein Kunde einen Geschenkgutschein einlöst und die Gesamtsumme der Bestellung dadurch auf **0,00 €** sinkt, greift eine optimierte Checkout-Strecke:
1. **Frontend-Bypass (`stripe-js.blade.php`):** Das Stripe-Javascript-Element zur Kreditkarteneingabe wird ausgeblendet und nicht initialisiert (da Stripe keine Zahlungen über 0,00 € verarbeiten kann).
2. **Direkte Bestellbestätigung:** Die Livewire-Checkout-Komponente sendet die Bestellung ab, überspringt den Stripe-Payment-Schritt und führt im Backend direkt `completePayment(null)` auf dem Order-Modell aus.
3. **Sofortige Aktivierung:** Die Bestellung wird sofort in den Status `paid` (Bezahlt) versetzt, der Gutschein-Abzug wird in den Logtabellen verbucht und das Restguthaben des Gutscheins aktualisiert.

---

## 6. Bereinigte UI-Darstellung & UX-Optimierungen

* **Kostenübersicht (`cost-summary.blade.php`):**
  * *Problem:* Bisher wurden Geschenkgutscheine im oberen Rabattblock abgezogen, was bei vollständiger Deckung zu verwirrenden negativen Zwischensummen führte.
  * *Lösung:* Geschenkgutscheine werden nun als Zahlungsmittel erkannt. Aktions-Rabatte verbleiben im oberen Bereich und mindern die Zwischensumme. Wertgutscheine hingegen werden erst **nach** Aufaddierung von Versand und Express abgezogen, direkt vor der Gesamtsumme. Negative Werte sind dadurch ausgeschlossen.
* **Produkttyp-Badges (`left.blade.php`):**
  * *Problem:* Das "DIGITAL"-Badge auf dem Produktbild in der administrativen Bestellübersicht war als vollflächiges Overlay implementiert, wodurch das eigentliche Produktbild verdeckt und unleserlich wurde.
  * *Lösung:* Das Badge wurde in eine kompakte, dezente Leiste am unteren Bildrand umgewandelt (analog zur Warenkorb-Ansicht), sodass das Produktbild voll erkennbar bleibt.

---

## 7. Sicherheit & Schutz vor Missbrauch

* **Sicherheits-Check beim Checkout:** Unmittelbar vor dem Schreiben der Bestellung in die Datenbank wird der Gutscheincode im Backend erneut validiert (Schutz vor unberechtigter Mehrfacheinlösung bei parallelen Requests).
* **Rate-Limiting:** Der Live-Guthabenprüfer blockiert Anfragen nach 5 Fehlversuchen kurzzeitig, um Brute-Force-Angriffe auf Gutscheincodes zu verhindern.

---

## 8. Qualitätssicherung & Testabdeckung

Die gesamte Gutschein-Logik ist durch eine automatisierte Testsuite in `tests/Feature/GiftVoucherTest.php` abgesichert:

| Testname | Beschreibung | Status |
| :--- | :--- | :---: |
| `it_generates_codes_with_correct_prefix` | Prüft Länge und Präfix des Codes. | **PASS** |
| `it_creates_gift_vouchers_on_order_fulfillment` | Validiert die Gutschein-Generierung nach Bezahlung. | **PASS** |
| `it_prevents_duplicate_vouchers_upon_double_fulfillment` | Verhindert doppelte Gutscheine durch Race Conditions. | **PASS** |
| `it_handles_partial_redemption` | Prüft korrekte Guthabenminderung bei Teileinlösung. | **PASS** |
| `it_handles_full_redemption` | Prüft automatische Deaktivierung bei vollständiger Einlösung. | **PASS** |
| `it_fails_validation_on_purchase_page_if_message_exceeds_160_chars` | Validiert Längenbeschränkung der Grußbotschaft. | **PASS** |
| `it_truncates_personal_message_to_160_chars_on_order_fulfillment` | Schneidet Botschaften bei Datenbankspeicherung ab. | **PASS** |
| `it_does_not_apply_promotional_coupons_to_gift_vouchers` | Verhindert Rabattierung von Geschenkgutscheinen. | **PASS** |
| `it_checks_voucher_balance_successfully_when_valid` | Prüft den Live-Guthabenprüfer. | **PASS** |
| `it_fails_to_check_balance_if_invalid_or_expired` | Simuliert abgelaufene/ungültige Gutscheine im Prüfer. | **PASS** |
| `it_rate_limits_balance_queries` | Validiert Brute-Force-Schutz. | **PASS** |
| `it_redirects_old_vouchers_url_to_product_page` | Verifiziert 301-Weiterleitung. | **PASS** |
| `it_renders_gift_voucher_purchase_page_on_product_show` | Testet das korrekte Blade-Template-Rendering. | **PASS** |
| `it_calculates_correct_shipping_cost_for_voucher_purchase_livewire` | Prüft Umschalten von E-Mail (0 €) auf Post (3,50 €). | **PASS** |
| `it_calculates_correct_totals_with_only_post_delivery_voucher` | Prüft Versandzuschlag bei physischen Gutscheinen. | **PASS** |
| `it_calculates_correct_totals_with_only_email_delivery_voucher` | Prüft Versandfreiheit bei digitalen Gutscheinen. | **PASS** |
| `it_uses_standard_shipping_rate_when_other_physical_products_exist` | Verhindert doppelte Versandkostenberechnung bei Mischwarenkörben. | **PASS** |
| `it_can_checkout_fully_paid_order_via_voucher_without_stripe` | Testet Stripe-Bypass bei 0,00 € Gesamtsumme. | **PASS** |
| `it_validates_custom_amount_in_realtime` | Testet die Echtzeit-Validierung des Wunschbetrags bei Fehleingaben. | **PASS** |

### Testlauf-Ergebnis:

#### 1. GiftVoucherTest (19 feature tests for voucher checkout & lifecycle)
```bash
PHPUnit 12.5.14 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.22
Configuration: /var/www/html/phpunit.xml

...................                                               19 / 19 (100%)

Time: 00:15.398, Memory: 122.50 MB

OK (19 tests, 115 assertions)
```

#### 2. MarketingVoucherTest (11 dashboard tests including the new filtering & stats test)
```bash
PHPUnit 12.5.14 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.22
Configuration: /var/www/html/phpunit.xml

...........                                                       11 / 11 (100%)

Time: 00:13.249, Memory: 120.50 MB

OK (11 tests, 52 assertions)
```

---

## 9. Administrativer Verwaltungsbereich (Wertgutscheine Dashboard)

Um dem Administrator die Verwaltung der verkauften Geschenkgutscheine zu erleichtern, wurde der Tab **Verkaufte Geschenkgutscheine** auf der Seite `/admin/voucher` vollständig überarbeitet und mit einem detaillierten Such- und Filterbereich sowie Live-Statistiken ausgestattet.

### 9.1 Detaillierter Suchbereich (Livewire Search)
Die Freitextsuche (`searchCode`) wurde hochgradig optimiert und durchsucht nun folgende Datenfelder in Echtzeit:
- **Gutscheincode** (`code`)
- **Name des Empfängers** (`recipient_name`)
- **E-Mail des Empfängers** (`recipient_email`)
- **Persönliche Nachricht** (`personal_message`)
- **Bestellnummer** der zugehörigen Bestellung (`orderItem.order.order_number`)
- **E-Mail des Käufers** (`orderItem.order.email`)
- **Vor- und Nachname des Käufers** (`orderItem.order.billing_address->first_name` und `billing_address->last_name`)

### 9.2 Umfangreiche Filter- & Sortierungsoptionen
Der Administrator kann über ein elegantes, 4-spaltiges Grid im passenden Gold/Amber-Farbschema feingranulare Filter anwenden:
- **Versandart:** Filtern nach E-Mail (Digital) oder Post (Postalisch).
- **Guthaben-Status:** Filtern nach *Vollständig (ungenutzt)*, *Teilweise genutzt* oder *Aufgebraucht (leer)*.
- **Gültigkeits-Status:** Filtern nach *Aktiv & Gültig*, *Manuell Deaktiviert* oder *Abgelaufen*.
- **Originalwert Spanne:** Eingrenzung nach Mindest- und/oder Maximal-Nennwert in €.
- **Restguthaben Spanne:** Eingrenzung nach verbleibendem Mindest- und/oder Maximalwert in €.
- **Erstellungsdatum:** Filtern nach Erstellungszeitraum (Von / Bis).
- **Gültigkeitsdatum:** Filtern nach Ablaufzeitraum (Von / Bis).
- **Dynamische Sortierung:** Sortieren der Ergebnisliste nach Erstellungsdatum (Neueste/Älteste zuerst), verbleibendem Guthaben (Aufsteigend/Absteigend), Originalwert (Aufsteigend/Absteigend) oder alphabetisch nach Empfängername (A-Z/Z-A).

### 9.3 Echtzeit-Statistiken (Dynamic Stats Dashboard)
Über der Gutschein-Tabelle werden vier Info-Karten angezeigt, die sich synchron mit den aktiven Filtern in Echtzeit aktualisieren (berechnet über eine effiziente geklonte DB-Query, die die Paginierung nicht beeinflusst):
1. **Gutscheine (Gefiltert):** Anzahl der passenden Gutscheine.
2. **Gesamter Nennwert:** Die Summe des ursprünglichen Ausgabewertes aller gefilterten Gutscheine in €.
3. **Restliches Guthaben:** Das aktuell noch verfügbare Gesamtguthaben aller gefilterten Gutscheine in € (hervorgehoben in Emerald-Grün).
4. **Bereits eingelöst:** Die Differenz aus Nennwert und Restguthaben in € (hervorgehoben in Amber-Gold).

### 9.4 Qualitätssicherung (Livewire Dashboard Tests)
Die Dashboard-Steuerung und Filterlogik sind in `tests/Feature/Livewire/Shop/Marketing/MarketingVoucherTest.php` durch den Test `it_can_filter_and_sort_sold_gift_vouchers_with_realtime_stats` abgesichert. Der Test prüft die korrekte Filterung aller obigen Felder, die Sortierung, das Zurücksetzen und die korrekte Berechnung der Statistiken.
