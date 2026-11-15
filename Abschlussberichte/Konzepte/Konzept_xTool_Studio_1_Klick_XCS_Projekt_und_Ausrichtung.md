# Konzept & Ideen-Speicher: xTool Studio 1-Klick `.xcs` Projekt-Übergabe, Automatische Laserwerte & Geister-Kontur-Ausrichtung

**Datum:** 14.11.2026  
**System:** Mein-Seelenfunke Backend (`/admin/orders`, [`XtoolLaserCockpitService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Laser/XtoolLaserCockpitService.php), [`FileDownloadService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Export/FileDownloadService.php), [`OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php), [`Product.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Product/Product.php)) & Windows Host (`xTool Studio v1.10.15`, Gerät: `xTool F2 Ultra UV` / `GS009-CLASS-4`, SN `MXFK007003260530H0194A6`, IP `192.168.188.150`)  
**Status:** ✅ Produktiv umgesetzt & automatisiert getestet ([`XtoolLaserCockpitTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Shop/Order/XtoolLaserCockpitTest.php))

---

## 1. Ausgangsidee & Unternehmerischer Mehrwert

Beim Abarbeiten der aktuellen Fokus-Bestellung in `/admin/orders` muss aktuell eine `.svg`-Datei heruntergeladen, manuell in `xTool Studio` hineingezogen und anschließend jeder Laser-Parameter (Modus, Fokus/Dicke in `mm`, Material, Punktdauer `µs`, Leistung `%`, `DPI`, Frequenz `kHz`, Bildmodus `Jarvis`, Durchgänge) von Hand eingetippt werden. Zudem fehlt im `.svg`-Export die äußere Rohling-Kontur, weil `xTool Studio` diese bei einem reinen SVG-Import sonst mitlasern würde.

**Kernidee von Alina:**
Statt einer „dummen“ `.svg`-Datei übergibt das System die aktuelle Bestellung direkt als **fertig konfiguriertes `xTool Studio`-Projekt (`.xcs`)** inklusive aller im Produkt hinterlegten Laserwerte, Fokus-Maße und einer automatischen Ausrichthilfe auf den Rohling.

**Mehrwert für die Manufaktur:**
1. **0 Tippfehler & 0 Ausschuss:** Keine Gefahr mehr, versehentlich mit Edelstahl-Parametern auf K9-Kristallglas oder mit falschem Fokus (`thickness`) zu lasern.
2. **Zeitersparnis pro Auftrag (1 Klick statt 12 Klicks):** Kein Speichern und Reinziehen mehr. Durch die Windows-Dateiverknüpfung (`.xcs` -> `xTool Studio.exe`) öffnet ein Klick im Browser das komplette Projekt direkt in `xTool Studio`.
3. **Präzise 1:1 Ausrichtung auf dem Kamerabild:** Durch eine nicht-lasernde **„Geister-Kontur“ (`processIgnore: true`)** in Originalgröße des Rohlings (inkl. Herz-Umriss, Amulett-Öse oder Kristall-Kante) lässt sich das Motiv in Sekundenbruchteilen deckungsgleich auf das Kamerabild legen oder per xTool **„Intelligentes Füllen“ (`BATCH_TEMPLATE`)** automatisch auf Serien-Rohlinge verteilen.

---

## 2. Technische Analyse der lokalen `xTool Studio`-Installation (`v1.10.15`)

Die Tiefenprüfung auf dem Manufaktur-Notebook ergab folgende technische Fakten:

1. **Windows Registry & Protokoll-Handler:**
   * `HKCU\Software\Classes\.xcs` ist nativ als `xTool Studio Project File` mit `"C:\Users\konta\AppData\Local\Programs\xTool Studio\xTool Studio.exe" "%1"` registriert.
   * Zusätzlich existiert das URL-Protokoll `xtool://` (`OpenInStudio`).
   * Wird im Browser für `.xcs`-Downloads einmalig *„Dateien dieses Typs immer öffnen“* aktiviert, startet `xTool Studio` unmittelbar beim Klick im Backend ohne manuellen Drag-and-Drop-Schritt.

2. **Natives `.xcs` JSON-Projektformat (Migration `v1-to-canonical`):**
   * `xTool Studio` liest `.xcs`-Dateien als UTF-8 JSON ein und migriert diese automatisch in das interne Projekt-Schema (`version: "1.3.7"`).
   * Geräte-Identifikation für den **xTool F2 Ultra UV**:
     * `extId`: `"GS009-CLASS-4"`
     * `extName`: `"F2 Ultra UV"`
   * Unterstützte Prozess-Modi (`mode`) in `device.data`:
     * `LASER_PLANE` (Flache Oberfläche / Standard-Lamellenrost) -> Mapping aus `xtool_equipment_key = flatbed_standard`
     * `LASER_CYLINDER` / `LASER_CYLINDER_ROLLER` (RA3 Rotationsaufsatz) -> Mapping aus `xtool_equipment_key = rotary_ra3`
     * `LASER_CONVEYOR_FEEDER` (Förderband) -> Mapping aus `xtool_equipment_key = conveyor_batch`
     * `INNER_LASER` (3D-Kristall-Innengravur) -> Mapping aus `xtool_equipment_key = inner_3d`

3. **Fokus-Höhe & Offizielle xTool Material-IDs:**
   * Der im Produkt gepflegte Fokuswert `xtool_focus_mm` (z. B. `33.1` mm beim Großen Seelenkristall oder `19.9` mm beim Seelenanhänger) wird direkt in `device.data.<mode>.thickness` übergeben.
   * Bekannte Material-IDs aus Alinas lokalen xTool-Projekten:
     * `4064`: K9 Kristall (`K9 Crystal`)
     * `985`: Aluminium / Metall-Anhänger
     * `1098`: Edelstahl (`Stainless Steel`)
     * `0` / `custom`: Benutzerdefinierte Parameter

---

## 3. Architektur des `.xcs`-Generators (`FileDownloadService::downloadLaserXcs`)

### 3.1 Aufbau der generierten `.xcs`-Datei
Für jede Bestellposition (`OrderOrderItem` bzw. `OrderQuoteRequestItem`) erzeugt `FileDownloadService::downloadLaserXcs($itemId, $side)` ein vollständiges JSON-Projekt mit 3 Ebenen-Typen auf dem Canvas:

1. **Ebene 1: Kundenfotos / Raster-Grafiken (`type: "BITMAP"`, `processingType: "BITMAP_ENGRAVING"`)**
   * Enthält das hochauflösende, bereits auf die Produktform geclippte Base64-PNG (`base64Image`), exakte Millimeter-Maße (`width`, `height`, `x`, `y`, `angle`) sowie alle Bild-Laserwerte aus `Product::evaluateLaserInstructionSettings()`:
     * `dotDuration`: `xtool_dot_duration_us` (z. B. `500` µs)
     * `power`: `xtool_power_percent` (z. B. `100` %)
     * `dpi`: `xtool_dpi` (z. B. `600` DPI)
     * `repeat`: `xtool_passes` (z. B. `1`)
     * `frequency`: `xtool_frequency_khz` (z. B. `40` kHz)
     * `bitmapMode`: `xtool_bitmap_mode` (z. B. `"Jarvis"` oder `"grayscale"`)
     * `bitmapScanMode`: `"zMode"` (bei `Zweidirektional`) bzw. `"singleMode"`
     * `processIgnore`: `false` (Aktiv zum Lasern)

2. **Ebene 2: Texte & Vektor-Symbole (`type: "PATH"`, `processingType: "FILL_VECTOR_ENGRAVING"`)**
   * Enthält die SVG-Pfade (`dPath`) der Kunden-Texte (inkl. Text-Bogen-Verzerrung) und Symbole im exakten Millimeter-Maßstab.
   * Laser-Parameter für Vektor-Füllung (`power`, `speed`, `dotDuration`, `frequency`, `density`, `repeat`) werden automatisch gesetzt, `processIgnore: false`.

3. **Ebene 3: Die „Geister-Kontur“ zur Ausrichtung (`type: "PATH"`, `layerColor: "#00bcd4"`, `processIgnore: true`)**
   * Enthält die **exakte 1:1 Außenkontur des physischen Rohlings** (z. B. den 72-Punkte-Herzumriss, den Anhänger-Kreis mit Öse, die Hundemarke oder das `180 x 180 mm` Kristall-Rechteck).
   * Durch `"processIgnore": true` ist diese Kontur in `xTool Studio` fest auf **„Ignorieren“ (wird niemals mitgelasert)** geschaltet.
   * Alle Elemente (Ebene 1 + Ebene 2 + Ebene 3) teilen sich dieselbe `groupTag`-ID, sodass sie sich in `xTool Studio` als **eine fest zusammenhängende Gruppe** verschieben und drehen lassen.

---

## 4. Vollautomatische Kamera-Erfassung & Auto-Positionierung („Zero-Touch Alignment“)

Die vertiefte Reverse-Engineering-Analyse von `xTool Studio` (`GS009-CLASS-4/index.js`, `main.log` und `algo-server`) zeigt: **Wir kommen sowohl lokal auf der Festplatte als auch direkt über das Netzwerk an die Laser-Kamera und die Fokus-Steuerung heran!**

### 4.1 Zwei direkte Zugriffswege auf die Laser-Kamera des xTool F2 Ultra UV

1. **Weg A: Lokales Abfangen des fertig entzerrten `2000 x 2000 px` Kamerabildes (`background.jpg`)**
   * `xTool Studio` speichert jedes aufgenommene und bereits über die Geräte-Kalibrierung (`global_calib.json` + `fl_calib_uv.json`) sowie die Z-Höhe entzerrte Kamerabild unverschlüsselt unter:
     `C:\Users\konta\AppData\Roaming\xTool Studio\atomm\GS009-CLASS-4\MXFK007003260530H0194A6\background.jpg`
   * Das Rohbild der Kamera (`4000 x 3000 px`) liegt im selben Ordner unter `capture.png`.
   * Zusätzlich speichert `xTool Studio` nach jedem abgeschlossenen Laser-Vorgang unter `reportData\<YYYY_MM_DD_HH_MM_SS>\processComplete.jpg` automatisch ein Foto des fertig gelaserten Produkts.
   * **Exakter Maßstab (`10 Pixel = 1,0 mm`):**
     Da der Arbeitsbereich des `xTool F2 Ultra UV` exakt `200 x 200 mm` beträgt und `background.jpg` eine Auflösung von `2000 x 2000 Pixel` besitzt, gilt ohne jede Verzerrung:
     **`10 Pixel = 1,0 Millimeter` (`1 Pixel = 0,1 mm`)!**

2. **Weg B: Direkter Netzwerk-/WebSocket-Abruf vom Laser (`192.168.188.150` WLAN / `172.25.3.1` USB)**
   * Der `xTool F2 Ultra UV` (`GS009-CLASS-4`, SN `MXFK007003260530H0194A6`) stellt über WebSocket (`/websocket?function=instruction`) folgende direkte Befehle bereit:
     * **Punktlaser für Foto kurz ausschalten:** `PUT /v1/peripheral/param?type=ir_led` mit `{"action": "off", "index": 0}` sowie `PUT /v1/laser-head/parameter` mit `{"action": "power", "ctrl": "set", "value": 0}`
     * **Kamera-Schnappschuss auslösen:** `POST /v1/camera?exp=0&stream=0` gefolgt von `GET /v1/camera/snap?name=main&timeOut=30000` (liefert `/tmp/main_<timestamp>.jpg`)
     * **Bild-Download:** `PUT /v1/filetransfer/download` (`filetype: 5`, `packetsize: 1048576`) und Abschluss via `PUT /v1/filetransfer/finish`
     * **Fokus-Motor (Z-Achse) automatisch auf Produkt-Fokus fahren (`handFocus`):** `POST /v1/mode/switch` (`state: P_AUTOFOCUS`) und `PUT /v1/focus/control` mit `{"action": "goTo", "z": xtool_focus_mm + 6, "stopFirst": 1}`
   * **Lokale Z-Entzerrung & KI-Objekterkennung über xTools `algo-server`:**
     Über `C:\Users\konta\AppData\Local\Programs\xTool Studio\resources\tools\algo-server\algo-server-win32-x64.exe` (Plugins `path_planning` & `visual_perception` / `GS_MATERIAL_SPLIT`) wird das Rohbild via `GS009CorrectImageWithZSync` (`src_jpg`, `dst_jpg`, `calib_json`, `z`) innerhalb von `~170 ms` auf die exakte Fokus-Höhe umgerechnet.

### 4.2 Vollautomatischer „Zero-Touch“-Ablauf (Rohling einlegen -> Fertig ausgerichtet!)

Durch den Zugriff auf `background.jpg` (`2000 x 2000 px` = `200 x 200 mm`) entfällt sogar das manuelle Hingucken und Hin- und Herschieben in `xTool Studio`:

1. **Rohling einlegen:** Alina legt das Produkt (z. B. Anhänger, Herz, Hundemarke oder Kristall) einfach auf den dunklen Lamellenrost.
2. **Automatische Kontur- & Mittelpunkt-Erkennung (`10 px = 1 mm`):**
   * Unser lokaler Helper (bzw. xTools `GS_MATERIAL_SPLIT` / OpenCV-Konturerkennung) analysiert `background.jpg`, erkennt den hellen/metallischen/gläsernen Rohling auf dem schwarzen Lamellenrost und berechnet auf `0,1 mm` genau:
     * **Mittelpunkt des eingelegten Rohlings:** `X_mm = Pixel_X / 10.0`, `Y_mm = Pixel_Y / 10.0` (z. B. `X = 96,4 mm`, `Y = 104,2 mm`)
     * **Drehwinkel des Rohlings:** `angle` in Grad (falls der Rohling leicht schräg eingelegt wurde, z. B. `+2,4°`)
3. **Direktes Einbrennen der Ziel-Koordinaten in die `.xcs`-Datei:**
   * Der `.xcs`-Generator platziert das Kunden-Design (Foto + Text + türkisfarbene `processIgnore: true` Geister-Kontur) direkt auf diese erkannten `(X_mm, Y_mm, angle)`-Koordinaten!
4. **Ergebnis beim Öffnen von `xTool Studio`:**
   * Das Projekt geht auf, alle Laserwerte (`µs`, `%`, `DPI`, `kHz`, `Jarvis`, `Fokus mm`) sind eingestellt, das Kamerabild liegt im Hintergrund **und das Design sitzt bereits exakt zentriert und im richtigen Winkel auf dem fotografierten Produkt!**
   * Alina wirft nur noch einen kurzen Kontrollblick auf den Bildschirm (die türkisfarbene Geister-Kontur umschließt den Rohling passgenau) und drückt auf Start.

---

## 5. Ausbaustufe 2: Direktes „Laser-Cockpit“ im Webportal (`/admin/orders`) ohne Datei-Download & ohne `xTool Studio`

Über den `.xcs`-Export hinaus hat die Analyse von `xtool.xf` (`C:\Users\konta\AppData\Roaming\xTool Studio\atomm\GS009-CLASS-4\MXFK007003260530H0194A6\xtool.xf`) und `algo-server-win32-x64.exe` gezeigt, dass wir den gesamten Prozess **direkt in die Bestellansicht (`/admin/orders`)** holen können, sodass weder ein Datei-Download noch das Öffnen von `xTool Studio` nötig ist.

### 5.1 Entschlüsselung des nativen `xTool F2 Ultra UV` Druck-Pakets (`tmp.xf`)
Wenn `xTool Studio` auf „Verarbeiten / Drucken“ klickt, sendet es ein unverschlüsseltes TAR-Archiv (`tmp.xf`) an den Laser (`192.168.188.150`), das aus exakt 4 Dateien besteht:
1. **`preview.jpg`**: Kleines Vorschaubild für das Display am Laser.
2. **`border.gcode`**: Text-basierter G-Code für den blauen Rahmen-Laser (`Framing`), z. B.:
   ```gcode
   # GS009-4 HEAD
   G0 F180000
   M4 S0
   G1 F180000
   M114 S1
   G21
   G90
   # blockConfig={"powerFactor":0.133,"isVector":true,"crossDot":false}
   G0X74.572Y95.795
   G1X138.985Y95.795S133F1440000
   G1X138.985Y101.237
   G1X74.572Y101.237
   G1X74.572Y95.795
   G90
   G0 S0
   M536 U0
   M6 P1
   ```
3. **`motion.bgcode`**: Der kompilierte Galvo-Maschinencode, der lokal über xTools eigenen Rechen-Server (`algo-server-win32-x64.exe` -> Plugins `graphic_image::bitmapCv`, `graphic_image::svgsJsonToPngSync` und `path_planning::genGCodeSync`) in wenigen Millisekunden erzeugt wird.
4. **`description.json`**: JSON-Steuerdatei mit den Prozessparametern (`processingParams`: `dotDuration`, `power`, `dpi`, `frequency`, `bitmapScanMode: "zMode"`, `speed`, `density`), `material` (z. B. `4064` oder `1098`) und `deviceInfo`.

### 5.2 Direkter Job-Upload per API an den Laser (`192.168.188.150`)
Sobald `tmp.xf` erstellt ist, wird das Paket direkt über die WebSocket-/Filetransfer-Schnittstelle des Lasers übertragen:
1. `PUT /v1/filetransfer/upload` (`filename: "tmp.xf"`, `filetype: 1`, `packetsize: 1048576`)
2. `PUT /v1/processing/upload/config` mit `{"autoStart": 0, "fileType": "xf", "taskId": "<uuid>"}`
3. Der Laser wechselt sofort in den Status `P_WORK` / `WORK_PREPARED` (bereit zum Start per Knopfdruck am Gerät).
4. Nach Abschluss des Laservorgangs liefert die Kamera automatisch das fertige Nachweisfoto (`processComplete.jpg`), das direkt in der Bestellung archiviert werden kann.

### 5.3 Wie der schlanke 2-Schritte „Zero-Thinking“-Workflow direkt in `/admin/orders` aussieht
Für **alle Standard- und Flachbett-Aufträge** (Anhänger, Herzen, Hundemarken, Schieferplatten, flache K9-Kristalle) spart das eingebettete **2-Schritte Live-Laser-Cockpit** in [`laser-cockpit-card.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-offer-detail-partials/laser-cockpit-card.blade.php) den Umweg über `xTool Studio` sowie den unnötigen Zwischenschritt einer Rahmen-Projektion komplett ein:

1. **2-Tab Ansicht in Schritt 1 & `Details anzeigen`-Umschalter:**
   * Schritt 1 bietet zwei klare Tabs: **`xTool F2 Ultra UV · Live-Laser-Cockpit`** und **`Live-Konfigurations-Vorschau`**.
   * Technische Diagnose-Elemente (`IP`, `KI %`, `!`-Info, `.xcs Backup`, optionale `Rahmen-Vorschau`, Zeitstempel sowie die manuelle `X / Y / Winkel`-Koordinatenleiste) sind standardmäßig ausgeblendet und werden nur bei Klick auf **`Details anzeigen`** (`toggleLaserDetails`) eingeblendet.
2. **Button `1. Kamera & KI-Ausrichten` (`refreshLaserCameraAndAlign` / `captureAndCalibrateLiveCameraFromLaserV2`):**
   * **Direkter Live-Kamera-Schnappschuss vom Laser ohne `xTool Studio` (`wss://192.168.188.150:28900`):**
     1. Verbindet sich aus dem Backend (`XtoolLaserCockpitService`) direkt per TLS-WebSocket mit dem `xTool F2 Ultra UV` auf Port `28900` (`/websocket?id=<uuid>&function=instruction` sowie `function=file_stream`) mittels xTools nativem Binär-Framing (`0xBABE`-Header + `CRC-16/ARC`).
     2. Authentifiziert die Session über `GET /v1/user/parity` mit dem gekoppelten Gerätetoken (`TOKEN_AUTHORIZED`), schaltet kurzzeitig IR-LED (`/v1/peripheral/param?type=ir_led`) und Laserpunkt (`/v1/laser-head/parameter`) aus (verhindert Überstrahlen auf Kristall/Metall) und löst über `POST /v1/camera` + `GET /v1/camera/snap?name=main` ein frisches `4000x3000 px` Live-Foto direkt auf dem Laser aus.
     3. Lädt das Foto in ca. `0,34 s` über den Binärkanal `file_stream` (`PUT /v1/filetransfer/download` -> `FILE_REQUEST` -> `FILE_DATA`) herunter und schaltet IR-LED sowie Positionierlaser sofort wieder ein.
     4. Entzerrt das `4000x3000 px` Rohbild anhand des 21x21 Werkskalibrierungs-Gitters (`data0` aus `global_calib.json` des Lasers `MXFK007003260530H0194A6`) in `~185 ms` auf das exakt maßstabsgetreue `2000x2000 px` (`200x200 mm`, `10 px = 1 mm`) Arbeitsflächenbild (`storage/app/public/bestellungen/xtool/background.jpg`).
     5. Falls der Laser ausgeschaltet bzw. im Standby ist, greift automatisch der Fallback auf das zuletzt gecachte Kamerabild (`host_appdata/capture.jpg` bzw. `background.jpg`).
   * Erhöht das Livewire-Refresh-Token (`$laserCameraRefreshToken`) zur sofortigen Cache-freien Bildaktualisierung im Browser (`?v=<mtime>&r=<token>`) und richtet das Kunden-Design (inkl. türkisfarbener Kontroll-Kontur) automatisch auf Position (`X`, `Y`) und Drehwinkel (`°`) des Rohlings aus.
3. **Button `2. An Laser senden` (`sendJobDirectlyToLaser`):**
   * Erzeugt das fertige `tmp.xf`-Paket mit allen am Produkt hinterlegten Laserwerten und überträgt es direkt an den `xTool F2 Ultra UV`. Alina drückt nur noch den physischen Start-Knopf am Laser.
4. **Optionale `Rahmen-Vorschau` & `.xcs Backup` hinter `Details anzeigen`:**
   * Für seltene Sonderfälle (z. B. manuelle Rahmenkontrolle via `border.gcode` oder `.xcs`-Export für `INNER_LASER`) stehen beide Funktionen dezent unter `Details anzeigen` sowie im Header-Modal **`Dateien`** bereit.
5. **Millimetergenaue 1:1 Rohlings-Kalibrierung & Einzel-Außenkontur (`resolvePhysicalBlankCalibration`):**
   * Im Live-Laser-Cockpit, im `.xcs`-Projekt sowie im `.xf`-Druckpaket wird stets **exakt eine einzige geschlossene 1:1 Außenkontur** (`#00bcd4`, Modus: Ignorieren) in den realen Millimeter-Außenmaßen des physischen Rohlings gerendert (keine doppelten/konzentrischen Rahmen).
   * Über `xtool_plate_box_pct` (`mapShopConfigOntoPhysicalBlank`) wird die Sub-Fläche des Rohlings aus dem quadratischen `800×800` bzw. `918×918` Shop-Konfigurator-Overlay (ohne Kette, Schlüsselring oder Standfuß) verlustfrei auf den realen Rohling projiziert:
     * `seelen-kristall`: **Ø `161,5 × 161,5 mm`** K9-Kreisscheibe (Gesamthöhe inkl. Standfuß `180 mm`; `xtool_plate_box_pct`: `left 10,88 %`, `top 6,48 %`, `78,24 × 78,24 %`)
     * `seelen-anhaenger`: **`45,0 × 40,0 mm`** Alu-Herz (`left 25,875 %`, `top 52,25 %`, `48,25 × 44,25 %`)
     * `seelen-talisman`: **`28,0 × 50,0 mm`** Dog-Tag-Plakette (`left 33,875 %`, `top 40,25 %`, `32,25 × 54,75 %`)
     * `seelen-pfote-hundemarke`: **`40,0 × 22,0 mm`** Knochen-Hundemarke (`left 10,0 %`, `top 29,75 %`, `80,0 × 40,5 %`)
     * `goldenes-seelen-herz`: **`25,0 × 22,0 mm`** Edelstahl-Goldherz (`left 17,5 %`, `top 35,125 %`, `65,0 × 59,125 %`)
     * `id-armband-silber-herz` & `id-armband-gold-herz`: **`24,0 × 5,5 mm`** ID-Gravurplatte (`left 17,0 %`, `top 42,5 %`, `66,0 × 15,0 %`)
     * `geschenkgutschein` (Alu-Karte): **`86,0 × 54,0 mm`** ISO-Karte
   * HUD-Anzeige und Eingabefelder (`X`, `Y`, `Winkel` in `0,1 mm`-Schritten) sind über `$wire.entangle(...)` verzögerungs- und abweichungsfrei synchronisiert.
   * Zusatzinformationen (`Dateien & Downloads`, `Kostenübersicht`, `Kunden Infos`, `Lieferadresse`) sind jederzeit über die 4 kompakten Icon-Modals rechts oben im Bestell-Header abrufbar.
