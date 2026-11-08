# Abschlussbericht: Auftragsfertigung & Intelligente Lieferanten-Vorlaufzeit bei Bestand 0

**Status:** Produktiv / Vollständig getestet  
**Projekt:** Mein-Seelenfunke (`/home/ubuntuxina/meine-projekte/seelenfunke`)  
**Abteilung:** Produkte / Bestellungen / Logistik / Kommandozentrale  
**Datum:** 06.11.2026  

---

## 1. Ausgangslage & Unternehmerischer Mehrwert

Hochwertige Rohlinge oder neu ins Sortiment aufgenommene Artikel starten bei **Mein-Seelenfunke** bewusst mit **Bestand `0` (`quantity = 0`)** und aktivem Überverkauf (`continue_selling_when_out_of_stock = true`). Damit Kunden solche Produkte sofort bestellen können, ohne dass falsche Lieferzeitversprechen (Abmahnrisiko nach UWG / PAngV) entstehen oder eine Rohling-Nachbestellung im Tagesgeschäft übersehen wird, greift eine durchgängige **4-Säulen-Architektur**.

---

## 2. Die 4-Säulen-Architektur im Überblick

### Säule 1: Intelligente Lieferzeit-Addition bei Bestand `0` (`Product` & `DeliverySetting`)
* **Lagerware (`quantity >= $requestedQty` oder `!track_quantity`):**
  * Es gilt die reguläre Manufaktur-Lieferzeit aus [`DeliverySetting::getDeliveryDetails()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Delivery/DeliverySetting.php) (z. B. `3–5 Werktage`).
* **Auftragsfertigung mit Rohling-Abruf (`quantity < $requestedQty` und `continue_selling_when_out_of_stock = true`):**
  * [`Product::requiresSupplierProcurement()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Product/Product.php) erkennt automatisch, dass der Rohling beim Lieferanten bezogen werden muss.
  * [`Product::getSupplierLeadDaysRange()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Product/Product.php) ermittelt die Lieferanten-Vorlaufzeit (Min/Max in Werktagen) nach folgender Priorität:
    1. Produktspezifischer Wert aus `configurator_settings['out_of_stock_lead_days_min']` & `out_of_stock_lead_days_max` (pflegbar im Produkt-Editor unter **Schritt 3 -> 5. Lager & Verfügbarkeit**).
    2. Automatischer Fallback auf den verknüpften Lieferanten (`ProductSupplier::lead_time_land_days` / `lead_time_air_days`).
    3. Sicherer Standard-Fallback (`4–6 Werktage` für Manufaktur, `5–7 Werktage` für Atelier).
  * [`DeliverySetting::getDeliveryDetailsForProduct($product)`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Delivery/DeliverySetting.php) addiert die Lieferanten-Vorlaufzeit automatisch zur Manufaktur-Lieferzeit:
    $$\text{Gesamt-Lieferzeit (Min/Max)} = \text{Lieferanten-Zulauf (Min/Max)} + \text{Manufaktur-Veredelung (Min/Max)}$$

### Säule 2: Vollautomatische Integration in „Neu (KI)“ (`/admin/products`)
* In [`ProductCreate.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCreate.php) und [`_list.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/partials/_list.blade.php) ermittelt der KI-Produktgenerator (`inferFromQuickAiUrl()` & `createQuickAiProduct()`) automatisch die passende Lieferanten-Vorlaufzeit anhand des erkannten `ProductSupplier` bzw. der Händler-Domain.
* Die Vorlaufzeit (`quickAiLeadDaysMin` / `quickAiLeadDaysMax`) wird direkt in `configurator_settings` gespeichert und das Produktattribut `Lieferzeit` automatisch auf das kombinierte Lieferfenster bei Bestand `0` gesetzt.
* In der Produktübersicht (`/admin/products`) zeigen Produkte mit `quantity == 0` und aktivem Überverkauf das Badge **`0 Stk. (Auftragsfertigung)`**.

### Säule 3: Ehrliche, abmahnsichere & verkaufsstarke Darstellung im Shop, Warenkorb & Checkout
* **Produktseite & Konfigurator ([`SystemDeliveryDisplay.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemDeliveryDisplay.php) & [`system-delivery-display.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/system/system-delivery-display.blade.php)):**
  * Statt „Ausverkauft“ erscheint der hochwertige Status: **„Auftragsfertigung (Rohling wird auf Bestellung bezogen)“** inklusive präziser Gesamtdauer und Kalenderdatum (`Lieferzeit: X-Y Werktage (ca. ... bis ...)`).
  * Im ausklappbaren `(weitere Infos)`-Bereich wird transparent erklärt, dass der Rohling nach Bestelleingang frisch vom Hersteller bezogen und anschließend in der Manufaktur Gifhorn veredelt wird.
* **Warenkorb ([`cart.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/cart/cart.blade.php)) & Checkout ([`right-column-summary.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-checkout/partials/right-column-summary.blade.php)):**
  * Jede betroffene Position weist ihren Auftragsfertigungs-Hinweis und ihr Lieferfenster transparent aus; im Checkout wird zudem das kombinierte Gesamt-Lieferfenster der Bestellung angezeigt.

### Säule 4: Operativer Nachbestell-Workflow in `/admin/orders` & `/admin/dashboard`
* **Snapshot bei Bestellabschluss ([`HandlesOrderCreation.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderCheckout/Traits/HandlesOrderCreation.php)):**
  * Sobald eine Bestellung einen Artikel mit `requiresSupplierProcurement()` enthält, wird in `OrderOrderItem::$configuration['supplier_procurement']` ein unveränderlicher Snapshot mit Fehlmenge (`missing_qty`), Lieferant, Direkt-Kauflink (`reorder_url`), kalkulierter Lieferzeit und Bestellstatus (`ordered_from_supplier = false`) gespeichert.
* **Bestellübersicht & Detailansicht ([`OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php), [`table.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview-partials/table.blade.php), [`left.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-offer-detail-partials/left.blade.php)):**
  * Offene Bestellungen mit ausstehender Rohling-Beschaffung zeigen in der Tabelle das Badge **`📦 Rohling bestellen`** (und im Funki-Prio-Hinweis eine direkte Warnung).
  * In der Bestell-Detailansicht (`left.blade.php`) erscheint an der Position eine Aktionsleiste mit **1-Klick-Kauflink (`Kauflink ↗`)** direkt zum Lieferanten-Produkt sowie dem **1-Klick-Button `Als bestellt markieren`** (`toggleSupplierOrdered()`), der den Status auf **`🚚 Rohling beim Lieferanten bestellt ✓`** inkl. Zeitstempel setzt.
* **Kommandozentrale (`/admin/dashboard` – [`AnalyticsService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AnalyticsService.php)):**
  * `checkOrders()` prüft offene Bestellungen auf ausstehende Rohling-Nachbestellungen und hebt die Bestell-Kachel im Operativen Status automatisch mit **`X× Rohling bestellen!`** hervor.

---

## 3. Automatisierte Qualitätssicherung
* **Test-Suite:** [`tests/Feature/Order/SupplierProcurementAndLeadTimeTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/SupplierProcurementAndLeadTimeTest.php) (5 Tests, 33 Assertions) sowie [`tests/Feature/SmallBusinessAndDeliveryTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/SmallBusinessAndDeliveryTest.php) (29 Tests).
