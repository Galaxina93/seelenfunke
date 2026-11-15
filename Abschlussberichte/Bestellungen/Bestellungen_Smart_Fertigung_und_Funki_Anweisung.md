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

---

## 6. Zentrierte 3-Schritte-Bestellabwicklung, Live-Laser-Cockpit & Packtisch-Prüfung (`Zero-Thinking`)

Die Bestelldetail-Ansicht in `/admin/orders` ([`order-offer-detail-content.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-offer-detail-content.blade.php), [`detail-header.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview-partials/detail-header.blade.php), [`laser-cockpit-card.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-offer-detail-partials/laser-cockpit-card.blade.php)) führt statt eines überladenen 2-Spalten-Layouts **mittig zentriert (`max-w-3xl`) Schritt für Schritt** durch den Auftrag. Alle Detailansichten (Produktkonfigurator, Laser-Steckbrief, Dateien & Downloads, Lieferadresse & Kostenübersicht) sind darunter optional per 1-Klick einblendbar:

1. **Vereinfachter Bestell-Header (`detail-header.blade.php`):**
   * Links: Zurück-Button, `#Auftragsnummer`, Express-Badge und kompakter Zahlstatus-Badge (`✓ Bezahlt` / `Zahlung offen`).
   * Mitte: Die zentrierte 3-Schritte-Pipeline (`Schritt 1 · In Bearbeitung (X/N)`, `Schritt 2 · Versand`, `Schritt 3 · Abgeschlossen`).
   * Rechts: Direkt-Button `Nächster gleicher Artikel` / `Nächster Auftrag` sowie ein kompaktes `Status / Optionen`-Menü für manuelle Status-Overrides.
2. **Start-Kachel „Wartend“ (`status = pending`):**
   * Beim Öffnen einer wartenden Bestellung erscheint mittig zentriert die Kachel **`Wartend`** mit Vorschau der 3 Konfigurator-Vorgaben und dem Button **`Jetzt starten`** (`wire:click="startOrderProcessing"` -> setzt Status autonom auf `processing` und öffnet Schritt 1).
3. **Schritt 1 · In Bearbeitung (Zentriertes `xTool F2 Ultra UV · Live-Laser-Cockpit` je Artikel, wiederholend für alle Positionen):**
   * Zeigt mittig zentriert das **`xTool F2 Ultra UV · Live-Laser-Cockpit`** für den ersten offenen Artikel der Bestellung, inklusive horizontaler Artikel-Umschaltleiste (`Artikel X von N`) und Stückzahl-Zähler (`- / +`) für Großbestellungen.
   * **Präzise KI-Rohling-Erkennung & konzentrische Kontur:** [`XtoolLaserCockpitService::detectBlankPositionOnBed()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Laser/XtoolLaserCockpitService.php) blendet die helle Aluminium-Frontschürze am unteren Bildrand (`Y >= 165 mm`) automatisch aus, analysiert die verdeckten Lamellen-Spalten über eine gesättigte `[0..1]`-Maske (immun gegen punktuelle LED-Spiegelungen im Glas) und zentriert runde Kristall-Trophäen (`Der Seelen Kristall`) über [`FileDownloadService::buildLaserDesignPayload()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Export/FileDownloadService.php) exakt konzentrisch mit innerem Gravurkreis und äußerem Kristallrand.
   * **Echtes Maus-Drag-&-Drop & Drehgriff (`↻`):** Das Gravurmotiv lässt sich im Kamerabild per relativer Mausbewegung (`cursor-grab`) ruckelfrei ohne Springen verschieben sowie direkt am oberen `↻`-Griff drehen (`angleDeg`), ohne dass Livewire das DOM beim Loslassen neu lädt. Das `!`-Info-Popover im Cockpit-Header (`z-[90]`) ragt frei über den Container hinaus und wird nicht abgeschnitten.
   * Darunter stehen die 3 abhakbaren Konfigurator-Vorgaben (`1. Besondere Wünsche: JA/NEIN`, `2. Geschenkpapier: Verpacken / Nur als Bogen dazu legen / Nein`, `3. Edle Schmuckbox: Ja / Nein`).
   * Ein Klick auf **`Artikel erledigt & weiter zum nächsten Artikel`** (`completeCurrentItemAndAdvance`) lädt automatisch den nächsten offenen Artikel der Bestellung ins Cockpit; sobald alle Artikel erledigt sind, wechselt die Ansicht autonom in **Schritt 2**.
4. **Schritt 2 · Verpackung & Versand (Karton-Stückliste, Dankeskarte, Warnaufkleber & DHL-Label):**
   * [`OrderOrder::getPackingStationPlan()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Order/OrderOrder.php) zeigt exakt auf, **welche Kartons/Versandtaschen wie oft für welche Produkte** der Bestellung benötigt werden und wie jedes Produkt verpackt werden muss (inkl. Westpack-Schmuckbox und Geschenkpapier-Modus).
   * Zwei interaktive Pflicht-Prüfungen vor dem Verschließen (`wire:click="toggleOrderPackingCheck"`):
     1. **`Dankeskarte beigelegt?`** (`thank_you_card`)
     2. **`Prüfung Warnaufkleber (Zerbrechlich / Vorsicht Glas)`** (`fragile_sticker`, mit automatischem Pflicht-Hinweis bei Kristall, Glas, Trophäe, Schiefer, Keramik, Porzellan).
   * Über **`1. DHL Label erstellen & drucken`** (`openDhlModal` / `printDhlLabelViaFunkenwerk`) und **`2. Fertig verpackt & versendet -> Schritt 3`** (`completeStepTwoShipped`) wechselt die Bestellung autonom auf `shipped` und in **Schritt 3**.
5. **Schritt 3 · Bestellung abgeschlossen:**
   * Zentrierte Abschluss-Kachel mit Zusammenfassung (`1. Laser-Fertigung ✓`, `2. Verpackung & Versand ✓`), Button **`Bestellung endgültig abschließen`** (`completeStepThreeOrder` -> `status = completed`) und Direkt-Sprung zum nächsten Auftrag.
   * **Feature-Tests:** [`XtoolLaserCockpitTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Shop/Order/XtoolLaserCockpitTest.php) (`7/7` grün) & [`OrderWorkshopCheckinAndAiGuardTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderWorkshopCheckinAndAiGuardTest.php) (`4/4` grün).
