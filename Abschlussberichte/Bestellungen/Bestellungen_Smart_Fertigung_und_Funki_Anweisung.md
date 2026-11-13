# Bestellungen: Smart-Fertigungs-Sortierung (Rüstzeit-Optimierung) & Funki 1-Satz-Anweisung

## 1. Zielsetzung & Unternehmerischer Mehrwert
In der Laser-Manufaktur (**xTool F2 Ultra UV**, **RA3 Rotationsaufsatz**, **Förderband/Conveyor**) entsteht der größte Zeitverlust nicht beim Lasern selbst, sondern beim **Umrüsten** zwischen unterschiedlichen Artikeltypen (z. B. Wechsel zwischen `3D-Kristallfokus`, `RA3-Rotationsaufsatz` für Weizengläser und `Flachbett` für Schmuck/Anhänger sowie Kartonwechsel am Packtisch).

Gleichzeitig soll in `/admin/orders` (**Zero-Thinking-Prinzip**) auf den allerersten Blick oben im Funki-Kommandobereich in **einem einzigen, präzisen Satz** stehen, **welcher exakte Handgriff als Nächstes auszuführen ist**, egal welche Priorität oder Sonderkonstellation vorliegt.

---

## 2. Architektur & Kernkomponenten

* **Service:** [`OrderProductionBatchService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Order/OrderProductionBatchService.php)
* **Livewire-Steuerung:** [`OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php)
* **Views:**
  * [`order-overview.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview.blade.php) (Funki 1-Satz-Anweisung & kompakte Artikel-Serien-Leiste)
  * [`detail-header.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview-partials/detail-header.blade.php) (`Nächster gleicher Artikel →` / `Nächster Auftrag →`)
* **Feature-Tests:** [`OrderSmartBatchSortingAndFunkiDirectiveTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderSmartBatchSortingAndFunkiDirectiveTest.php)

---

## 3. Der 5-stufige Smart-Fertigungs-Score (`default_workflow`)

Offene Bestellungen (`pending`, `processing`) werden vollautomatisch nach folgendem lexikografischen Score sortiert:

1. **Stufe 1: Express & Frist-Schutz (Anti-Starvation)**
   * `Express-Aufträge` (`is_express = true`) und überfällige `Deadlines` stehen unantastbar auf Platz 1 (`urgencyTier = 0`).
   * Bezahlte Standard-Aufträge, die seit **≥ 48 Stunden** warten (`is_aging_sla`), rücken automatisch in `urgencyTier = 1` auf, damit Einzelstücke niemals hinter neuen Serien „verhungern“.
2. **Stufe 2: Produktions-Reifegrad (`stageRank`)**
   * Sofort ausführbare Aufträge (`ready_laser` im aktiven Fokus, `ready_ship`, `ready_laser`, `ready_pack`, `ready_digital`) stehen strikt vor administrativen Beschaffungs-Schritten (`action_supplier_order`) und vor extern blockierten Aufträgen (`blocked_supplier_wait`, `blocked_photo_issue`, `blocked_unpaid`).
3. **Stufe 3: Automatischer Laser-Fokus & Rüstgruppen-Affinität**
   * Sobald eine Bestellposition abgehakt wird (`toggleItemCompletion`, `incrementCompletedQuantity`, `setAllCompletedQuantity`) oder oben ein Artikel-Chip angeklickt wird (`selectBatchProduct`), merkt sich die Session den aktiven Serien-Artikel (`activeBatchProductId`).
   * Alle weiteren sofort fertigbaren Aufträge mit demselben Artikel ziehen geschlossen an die Spitze; danach folgen Aufträge derselben **xTool-Rüstgruppe** (`flatbed_laser`, `crystal_3d`, `rotary_ra3`, `conveyor_batch`, `pack_only`), gesteuert über `xtool_equipment_key` am Produkt.
   * Sobald der letzte offene Artikel einer Serie erledigt ist, löscht sich der Fokus automatisch und wechselt nahtlos zur nächsten Serie.
4. **Stufe 4: Natürliche Serien-Bündelung beim Tagesstart**
   * Auch ohne aktiven Fokus werden gleiche Artikel automatisch gebündelt (z. B. `Trophäe → Anhänger → Trophäe` wird automatisch zu `Trophäe 1 → Trophäe 2 → Anhänger`), geordnet nach dem ältesten Auftrag der jeweiligen Artikelgruppe.
5. **Stufe 5: Sortenreinheit & FIFO**
   * Innerhalb desselben Artikels stehen 1-Positionen-Aufträge (sofort nach dem Lasern versandfertig) vor Misch-Warenkörben, danach entscheidet chronologisch `created_at ASC` (FIFO).

---

## 4. Vollständige Funki 1-Satz-Entscheidungsmatrix (Zero-Thinking)

Funki gibt direkt unter der Begrüßung für **jede** denkbare Konstellation in einem Satz exakt vor, was jetzt zu tun ist:

| Zustand (`stage`) | Konkrete 1-Satz-Handlungsanweisung im Kopfbereich |
| :--- | :--- |
| **Express + Laser** | *„Express sofort lasern: Öffne #ORD-..., graviere X× ‚Artikel‘ (Setup-Hinweis) und erstelle direkt das DHL-Label.“* |
| **Aktive Laser-Serie (weitere folgen)** | *„Laser-Serie ohne Umbau fortsetzen: Graviere direkt X× ‚Artikel‘ (Setup-Hinweis) für #ORD-..., danach warten noch N weitere Aufträge mit demselben Artikel.“* |
| **Aktive Laser-Serie (letztes Stück)** | *„Letztes Stück der Serie: Graviere jetzt X× ‚Artikel‘ (Setup-Hinweis) für #ORD-... und erstelle direkt das DHL-Label.“* |
| **Neue Artikel-Serie starten** | *„Serie starten (N Aufträge gebündelt): Rüste auf [3D-Kristallfokus / RA3-Rotationsaufsatz / Flachbett] und lasere zuerst X× ‚Artikel‘ für #ORD-...“* |
| **Einzel-Laserauftrag** | *„Jetzt lasern: Öffne #ORD-..., graviere X× ‚Artikel‘ (inkl. Hinweis auf Vorder- & Rückseite) und erstelle direkt das DHL-Label.“* |
| **Reine Lagerware (ohne Laser)** | *„Kein Laser nötig: Nimm X× ‚Artikel‘ für #ORD-... aus dem Lagerregal, verpacke in [Luftpolstertasche/Versandkarton] und klicke auf ‚DHL Label‘.“* |
| **Fertig gelasert (Label fehlt)** | *„Fertig gelasert, Label erstellen: Öffne #ORD-..., verpacke die Ware und klicke oben rechts auf ‚DHL Label‘.“* |
| **Fertig + Label bereit** | *„Fertig produziert, jetzt versenden: Drucke das fertige DHL-Label für #ORD-... auf dem MUNBYN, klebe es auf und stelle auf ‚Versendet‘.“* |
| **Rohling nachbestellen (`Bestand 0`)** | *„Jetzt Rohling nachbestellen: Öffne #ORD-..., bestelle X× ‚Artikel‘ per 1-Klick-Kauflink bei [Lieferant] und setze den Haken ‚Rohling bestellt‘.“* |
| **Rohling bereits im Zulauf** | *„Warten auf Wareneingang: Für #ORD-... ist ‚Artikel‘ bei [Lieferant] im Zulauf, erst nach Anlieferung lasern.“* |
| **Offener Foto-Mangel** | *„Pausiert wegen Kundenfoto: Prüfe in #ORD-..., ob für ‚Artikel‘ ein neues Foto vorliegt, oder warte auf die Rückmeldung.“* |
| **Unbezahlt (`unpaid`)** | *„Noch nicht lasern (Zahlung offen): Kontrolliere den Geldeingang für #ORD-... und klicke erst bei Eingang auf ‚Bezahlt‘.“* |
| **Kritischer Fehlbestand** | *„Lagerbestand sofort prüfen: Für #ORD-... fehlen physisch X× ‚Artikel‘ im Bestand.“* |
| **Rein digital (`isOnlyDigital`)** | *„Keine Handarbeit nötig: Digitale Bestellung #ORD-... ist bereitgestellt, setze den Status auf ‚Abgeschlossen‘.“* |

---

## 5. Integriertes xTool-Handbuch & 1:1 Laser-Werte in der Bestellabwicklung (`right.blade.php`)

Direkt im Fertigungsbereich jeder Bestellposition unter `/admin/orders` ([`right.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-offer-detail-partials/right.blade.php)) wird automatisch die Karte **`xTool Anleitung & Laser-Werte`** des jeweiligen Produkts (`Product::getLaserInstructionSummary()`) ausgespielt:

* **Vollständig gepflegtes Produkt:** Zeigt Equipment-Badge (`Flachbett`, `360° RA3`, `Laufband`, `3D-Innengravur`), **1-Klick-Buttons zum Öffnen und Herunterladen (`PDF ziehen`) der PDF-Aufbauanleitungen** (`/storage/produkte/laser-anleitungen/...` inkl. Schnellzugriff `PDF-Anleitungen (3)`), optionalen Einlege-Hinweis (`xtool_setup_note`), das kompakte 1:1 Parameter-Raster (`Material`, `Fokus mm`, `Punktdauer µs`, `Leistung %`, `DPI`, `Durchgänge`, `kHz`, `Bitmap-Modus`, `Gravurmodus`, Vektor-Zusatz) sowie einen **`Werte kopieren`**-Button.
* **Fehlende Laser-Werte am Produkt:** Zeigt eine rote Warnbox **`✕ Laser-Werte am Produkt unvollständig`** mit 1-Klick-Deep-Link (`/admin/products?editProduct=<id>&step=4&subStep=2`) direkt in Schritt `4.2` des Produkt-Editors.
