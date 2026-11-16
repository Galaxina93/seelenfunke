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

## 5. Hinterlegte xTool-Laserwerte am Produkt (`Product::getLaserInstructionSummary()`)

Alle xTool-Parameter (`Material`, `Fokus mm`, `Punktdauer µs`, `Leistung %`, `DPI`, `Durchgänge`, `kHz`, `Bitmap-Modus`, `Gravurmodus`) sind zentral in Schritt `4.2` am Produkt hinterlegt und fließen automatisch in die `.xf`-/`.xcs`-Laserübergabe ein. Um die tägliche Bestellabwicklung in `/admin/orders` maximal aufgeräumt zu halten, werden die Parameter-Tabellen nicht mehr redundant unter dem Laser-Cockpit eingeblendet (in der Angebotsansicht `right.blade.php` bleiben sie für Sonderkalkulationen erhalten).

---

## 6. Clean-Design 3-Schritte-Bestellabwicklung, 2-Tab Cockpit/Vorschau & Header-Modals

Die Bestelldetail-Ansicht in `/admin/orders` ([`order-offer-detail-content.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-offer-detail-content.blade.php), [`detail-header.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview-partials/detail-header.blade.php), [`header-modals.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview-partials/header-modals.blade.php), [`laser-cockpit-card.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-offer-detail-partials/laser-cockpit-card.blade.php)) folgt strikt dem Seelenfunke Clean-Design ohne aufklappbare Doppel-Panels am unteren Rand:

1. **Bestell-Header mit 4 Schnellzugriff-Modals (`detail-header.blade.php` & `header-modals.blade.php`):**
   * Links: Zurück-Button, `#Auftragsnummer`, Express-Badge und Zahlstatus-Badge (`✓ Bezahlt` / `Zahlung offen`).
   * Mitte: Die zentrierte 3-Schritte-Pipeline (`Schritt 1 · In Bearbeitung (X/N)`, `Schritt 2 · Versand`, `Schritt 3 · Abgeschlossen`).
   * Rechts neben `Status / Optionen` liegen 4 kompakte Schnellzugriff-Buttons als Modals:
     1. **Ordner-Icon + `Dateien`** (`openHeaderModal('files')`): Kunden-Uploads, Konfigurations-Sicherungen, Foto-Mangel-Meldung sowie `.xcs`-, `Laser-SVG`- und `Kontur-PNG`-Downloads.
     2. **`€`-Icon** (`openHeaderModal('costs')`): Vollständige Kostenübersicht (`<x-shop.cost-summary>`).
     3. **User-Icon** (`openHeaderModal('customer')`): Kunden- & Rechnungsdaten, E-Mail-Schnellkopie sowie GoBD-konforme Löschfunktion für unbezahlte Testbestellungen.
     4. **GPS-Icon** (`openHeaderModal('shipping')`): Lieferadresse inklusive Hinweis bei abweichender Lieferanschrift.
2. **Start-Kachel „Wartend“ (`status = pending`):**
   * Zeigt `Konfigurator-Vorgaben` nur dann an, wenn tatsächlich ein Kundenwunsch, Geschenkpapier oder eine Schmuckbox gebucht wurde. Klick auf **`Jetzt starten`** (`startOrderProcessing`) setzt den Status autonom auf `processing` und öffnet Schritt 1.
3. **Schritt 1 · In Bearbeitung (2-Tab Ansicht + `Details anzeigen` + 2-Klick-Lasersteuerung):**
   * Das Badge `Artikel X von N` erscheint nur bei Bestellungen mit mehr als einer Position (`$totalOrderItems > 1`).
   * **2-Tab Umschalter:**
     * **Tab 1:** `xTool F2 Ultra UV · Live-Laser-Cockpit`
     * **Tab 2:** `Live-Konfigurations-Vorschau` (eingebettete 2D/3D `ProductConfigurator`-Vorschau mit identischer `line-height: 1.15` und exakt kalibrierter 3D-Gravurskalierung).
   * **Clean Cockpit & `Details anzeigen`:** Technische Statusleisten (`IP`, `KI %`, `!`-Tooltip, `.xcs Backup`, Zeitstempel, `Rahmen-Vorschau`) sowie die manuelle `X / Y / Winkel`-Feinjustierung sind standardmäßig ausgeblendet und lassen sich rechts oben in der Kachel über den schlichten Text-Button **`Details anzeigen`** (`toggleLaserDetails`) zuschalten.
   * **2-Schritte-Laserleiste:** Nur noch **`1. Kamera & KI-Ausrichten`** (`refreshLaserCameraAndAlign`) und **`2. An Laser senden`** (`sendJobDirectlyToLaser`). Beim `Seelen Kristall` ist die Scheibenkontur exakt auf den realen K9-Scheibendurchmesser **`Ø 161,5 mm`** (Gesamthöhe inkl. Sockel `180 mm`) und un-erodierte Kanten-Luminanz (`X = 104,5 mm`, `Y = 96,5 mm`) kalibriert.
   * **Konfigurator-Vorgaben & Weiter-Button:** Unter dem Cockpit erscheinen ausschließlich tatsächlich zutreffende Vorgaben (`Kundenwunsch`, `Geschenkpapier`, `Edle Schmuckbox`); „Nein“- oder „OK“-Zeilen entfallen komplett. Der Weiter-Button heißt schlicht **`Weiter zur Verpackung`**.
4. **Schritt 2 · Verpackung & Versand (Klare Karton-Namen & Chronologische Reinigungs-/Pack-Checkliste):**
   * `1. Benötigte Kartons & Verpackungs-Zuordnung`: Verwendet die 5 klaren Versandverpackungen nach den realen Karton.eu-Rechnungen (`Luftpolstertasche` `260×180×10 mm`, `Buchverpackung (Trophäe)` `284×244×70 mm`, `Normaler Karton` `300×230×150 mm`, `Mittlerer Karton` `600×400×200 mm`, `Großer Karton` `600×400×400 mm`) mit automatischer Volumen-/Mengen-Hochstufung.
   * `2. Pflicht-Prüfung vor dem Verschließen des Pakets`: Neutrale Karten mit dezentem Gold-Haken (`#C5A059`) exakt in der physischen Handgriff-Reihenfolge: `1. Reinigen · Isopropanol & Tuch` (`cleaned`), `2. Schmuckbox` (nur wenn gebucht), `3. Geschenkpapier · Einpacken / Beilegen` (nur wenn gebucht), `4. Dankeskarte`, `5. Aufkleber Zerbrechlich` (nur bei Glas/Kristall/Schiefer/Keramik).
5. **Schritt 3 · Bestellung abgeschlossen:**
   * Natürliche Singular-/Plural-Formulierung (`✓ 1 Artikel erledigt` bei Einzelpositionen, `✓ Alle N Artikel erledigt` ab 2 Positionen), Abschluss-Button und Direkt-Sprung zum nächsten Auftrag.
6. **Hands-Free Mini-PC Fokus-Leitstand (`/api/shop/workshop-focus` & `FunkenwerkOS.Linux`):**
   * Über [`WorkshopFocusApiController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/WorkshopFocusApiController.php) (`routes/api/orders.php`) und den `◎ Fokus-Modus` auf dem Werkstatt-Mini-PC (`192.168.188.151`) lassen sich alle fertigungsbereiten Bestellungen komplett ohne Notebook in 2 ultra-cleanen Ansichten (`Ansicht 1: Lasern` mit 3D-Bambu-Klickplatten-Nullpunkt & lokalem V2-WebSocket-Upload an `192.168.188.150` -> `Ansicht 2: Reinigen & Verpacken` mit 1-Klick/Sprach-DHL-Labeldruck) abarbeiten. Das Produkt `weizenglas-personalisiert` ist für einen umbaufreien 100 %-Flachbett-Start vorerst auf `draft` pausiert.
   * **Feature-Tests:** [`WorkshopFocusAndPackagingCleanNamesTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/WorkshopFocusAndPackagingCleanNamesTest.php), [`XtoolLaserCockpitTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Shop/Order/XtoolLaserCockpitTest.php), [`LaserDesignParityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/LaserDesignParityTest.php) & [`OrderWorkshopCheckinAndAiGuardTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/OrderWorkshopCheckinAndAiGuardTest.php) (`100 %` grün).
7. **Mathematische 1:1 Konfigurator-zu-Laser-Cockpit-Parität (`FileDownloadService.php` & `v5-crystal-82pct`):**
   * **Gemeinsame `zIndex`-Zeichenreihenfolge (`buildSortedLaserLayerQueue`):** Logos (`Default 10 + idx`) und Texte (`Default 30 + idx`) werden identisch zu `scripts_frontend_2.blade.php` in einer gemeinsamen, stabil nach `zIndex` sortierten Warteschlange gerendert, sodass über Fotos gelegte Vektor-Symbole (z. B. `weihnachtsbaum.svg` mit `zIndex: 31` über Porträt `zIndex: 11`) niemals vom darunterliegenden Foto überdeckt werden.
   * **W3C-SVG-Renderer mit 2-Pass-Kontrast-Halo & aktivem SVG-ClipPath:** Unterstützt elliptische Bögen (`A`/`a`), alle SVG-Grundformen (`<circle>`, `<ellipse>`, `<rect>`, `<polygon>`, `<polyline>`), `evenodd`/`nonzero`-Aussparungen, aktives `<g clip-path="url(#workingAreaClip)">` im SVG-Export sowie einen automatischen weißen Kontrast-Halo auf dunklen Laser-Porträts.
   * **Konzentrische `82,00 %` Kristall-Innenflächen-Kalibrierung (`Ø 133,93 mm` im `Ø 161,5 mm` Außenrahmen) & Fokus-Leitstand-Cache-Sync:** Beim `Seelen Kristall` bildet `plateBoxPct` (`[left: 9.00, top: 4.60, width: 82.00, height: 82.00]`) den `68,0 %`-Konfigurator-Arbeitskreis konzentrisch auf `82,93 %` (`Ø 133,93 mm`, `13,79 mm` Randabstand je Seite) innerhalb des `Ø 161,5 mm` blauen Justierungsrahmens ab (mit Sicherheitsreserve vor dem `~11,0 mm` geschliffenen Facettenkranz, der Sockel-Klebekante und dem `Ø 138,0 mm` Durchschussfenster der 3D-Bambu-Klickplatte). Sowohl [`OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php) als auch [`WorkshopFocusApiController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/WorkshopFocusApiController.php) nutzen identisch den kalibrierungsabhängigen Disk-Cache-Salt `v5-crystal-82pct|`.
