# Brennpunkt-Maße & Laser-Arbeitsdokumentation (xTool F2 Ultra UV)

Zentrale Praxis-Dokumentation aller gemessenen Fokus-Höhen (`mm` in xTool Studio), Ausrichtungs-Schritte und Gravur-Abläufe für unsere Seelenfunke-Rohlinge.

---

## 1. Produkt-Brennpunkt-Maße (Referenztabelle)

> [!IMPORTANT]
> ### 🏆 ABSOLUTE, FINALE & UNANTASTBARE MASTER-EINSTELLUNGEN: **DER SEELENKRISTALL** (`K9-Kristalltrophäe 180 × 200 × 40 mm`)
> Nach ausführlichen Praxistests und Bild-Aufbereitung durch Alina festgeschriebene, **unantastbare Endergebnis-Referenz** (Trophäe auf schwarzer Unterlegplatte so weit unten aufgelegt, dass der Standfuß frei in der Luft schwebt):
> * **Brennpunkt-Höhe (`mm`):** **`32,8 mm`** (`32.8`)
> * **Punktdauer (`µs`):** **`500`**
> * **Leistung (`%`):** **`85 %`**
> * **DPI:** **`600`**
> * **Bearbeitungsanzahl:** **`1`**
> * **Frequenz (`kHz`):** **`40`**
> * **Bitmap-Modus:** **`Jarvis`**
> * **Gravurmodus:** **`Zweidirektional`**
> * **Shop-SVG (`< Bild`):** **NICHT in xTool Studio invertieren** (kommt bereits vor-invertiert mit HDR-Tiefenentzerrung & Mikrokontrast aus dem Shop-Export)
> * **Vektor-Elemente (Text & Symbole):** `Gravieren` · `60 %` Leistung · `80 mm/s` · `300 Linien/cm` · `40 kHz`

| Produkt | Unterlage & Ausrichtung im Laser | Fokus Oberfläche (`mm`) | Fokus Innengravur / Effekt (`mm`) | Wichtige Einstellungen in xTool Studio (UNANTASTBARE REFERENZ) |
| :--- | :--- | :--- | :--- | :--- |
| **Der Seelenkristall** (K9-Kristalltrophäe `180 × 200 × 40 mm`) | Schwarze Unterlegplatte / Lamellenrost: **So weit nach unten aufgelegt, dass der Standfuß frei über der Vorderkante in der Luft schwebt** -> Glasscheibe liegt zu 100 % plan und absolut gerade auf! | **`33,1 mm`** (`33.1` im Shop-System Schritt 4.2) / **`32,8 mm`** (`32.8` Fein-Test) | **`33,1 mm` / `32,8 mm`** | Material: **`K9 Kristalltrophäe`** · **WICHTIG zur Shop-SVG:** **NICHT invertieren!** · **Foto (`< Bild`):** **`Brennpunkt: 33,1 mm`** (`32,8 mm`) · **`Punktdauer: 500 µs`** · **`Leistung: 100 %`** (`85 %` bei `32,8 mm`) · **`DPI: 600`** · **`Bearbeitungsanzahl: 1`** · **`Frequenz: 40 kHz`** · **`Bitmap-Modus: Jarvis`** · **`Gravurmodus: Zweidirektional`** · Vektor: `Gravieren · 60 % · 80 mm/s · 300 Linien/cm · 40 kHz` |
| **Der Seelenanhänger** (Schwarzes Eloxal-Aluminium-Herz `45 × 40 mm`) | Schwarze Unterlegplatte / Flachbett (plan aufliegend, Standard-Gravurlinse) | **`19,9 mm`** (`19.9`, Jig) / Flachbett ohne Jig `1,8 mm` | - | Modus: **`Prozess auf flacher Oberfläche`** · Material: **`0,2 mm Aluminiumlegierung`** · Polarität: **`white_on_dark`** · **Foto-Layer (`Bitmap`, Stand 11/2026 PDF 2):** **`85 %`** · **`280 µs`** · **`40 kHz`** · **`600 DPI`** · **`Jarvis`** · `1x` · Zweidirektional · **Text-Layer (`Vektor`):** **`70 %`** · **`450 mm/s`** · **`300 L/cm`** · **`40 kHz`** (Fein-Text PDF 1: `55 %` · `700 mm/s` · `450 L/cm`) · *Alt-Preset PDF 1: `90 %` · `300 µs` · `500 DPI`* |
| **Rundes Glas-Ornament / Untersetzer** | Schwarzes Lamellenrost (flach aufliegend) | *(folgt)* | *(folgt)* | Basiswert Lamellenrost ohne Glas: `21,9 mm` |

---

## 1b. Validierte Handbuchwerte 11/2026 (Vektor & Bitmap getrennt) + Foto-Dot-Gain-Regel

> [!IMPORTANT]
> Quelle: **PDF 1** (Laserwerte- & Schwarzgravur-Handbuch, Pos. 01-16) und **PDF 2** (Laserwerte-Datenbank Stand 11/2026). Alle Produkte unterstützen unter `/admin/products` -> Schritt `4.2` -> `2. Wie man es bearbeitet` beliebig viele benannte, aufklappbare **Laserprofile (`xtool_profiles`)** (z. B. `Schwarz (Anlass-Gravur 100 kHz)` auf dem Lamellenrost mit `21,5 mm` Fokus vs. `Weiß / Silber-Kontrast` mit `19,5 mm` Fokus, Punktdauer-Range `1 bis 3000 µs`). Beim direkten Senden aus dem Live-Laser-Cockpit (`2. An Laser senden`) fährt `XtoolLaserCockpitService::sendJobToLaserV2()` vor dem Upload von `tmp.xf` automatisch den Z-Motor via `POST /v1/laser-head/focus/control` (`goTo`, `z = focus_mm + 6,0`) auf die eingestellte Fokus-Höhe. Kundenfotos laufen im `.xcs`/`.xf` auf einem eigenen **Foto-Bitmap-Layer**, Texte & Symbole auf einem eigenen **Vektor-Layer** (deckungsgleiche Geometrie).

| Produkt | Polarität | Vektor (Text & Symbole) | Bitmap (Kundenfoto) | Zusatz-Presets |
| :--- | :--- | :--- | :--- | :--- |
| **Die Seelen-Pfote** (`40 × 22 × 1,8 mm`, 14K IP-Edelstahl PVD) | `dark_on_light` | `3,2 mm` (1,8 + 1,4) · `42 %` · `110 mm/s` · `65 kHz` · `330 L/cm` · 1x (opt. 2x 90°) | `3,2 mm` · `45 %` · `210 µs` · `65 kHz` · `800 DPI` · Jarvis (Silhouette: Schwellenwert) | Tiefschwarz 100 kHz (PDF 1 Pos. 01/02): Vektor `3,8 mm` · `95 %` · `25 mm/s` · `100 kHz` · `1000 L/cm`, Bitmap `3,6 mm` · `95 %` · `1100 µs` · `1270 DPI` · Silber-Kontrast `1,8 mm` · `28 %` · `550 mm/s` · `40 kHz` · Gold-Eloxal Alu `1,8 mm (+0,4)` · `55 %` · `450 mm/s` · `50 kHz` · `300 L/cm`, Bitmap `160 µs` · `600 DPI` |
| **Das Goldene Seelen-Herz** (`25 × 22 × 4,0 mm`, STA1274, Kette abnehmen) | `dark_on_light` | `5,4 mm` · `46 %` · `100 mm/s` · `65 kHz` · `350 L/cm` · 1-2x | `5,4 mm` · `48 %` · `220 µs` · `65 kHz` · `800 DPI` · Jarvis / Graustufen | Massiv-Tiefschwarz (Pos. 03): `6,0 mm` · `98 %` · `20 mm/s` · `100 kHz` · `1000 L/cm`, Bitmap `95 %` · `1100 µs` · `1270 DPI` |
| **Der Seelen-Talisman** (`50 × 28 × 2,2 mm`, Ring frei über Lamellenkante) | `dark_on_light` | `3,6 mm` · `44 %` · `105 mm/s` · `65 kHz` · `330 L/cm` | `3,5 mm` · `46 %` · `215 µs` · `65 kHz` · `800 DPI` · Jarvis | Tiefschwarz (Pos. 04): `4,2 mm` · `95 %` · `25 mm/s` · `100 kHz` · `1000 L/cm`, Bitmap `95 %` · `1100 µs` · `1270 DPI` |
| **Der Seelenanhänger** (`45 × 40 mm`, Schwarzes Herz PVD/Eloxal) | `white_on_dark` | `19,9 mm` (Jig) · `70 %` · `450 mm/s` · `40 kHz` · `300 L/cm` | `19,9 mm` · `85 %` · `280 µs` · `40 kHz` · `600 DPI` · Jarvis | Fein-Text `55 %` · `700 mm/s` · `450 L/cm` · Alt-Foto `90 %` · `300 µs` · `500 DPI` · Flachbett ohne Jig `1,8 mm` |
| **Echtsilber Seelen-Armband** (925er, `24 × 5,5 × 1,2 mm`) | `dark_on_light` | `2,2 mm` (1,2 + 1,0) · `75 %` · `180 mm/s` · `50 kHz` · `400 L/cm` · 2x | `2,2 mm` · `75 %` · `240 µs` · `50 kHz` · `1000 DPI` · 2x | Kreuzschraffur (Pos. 06): `2,0 mm` · `85 %` · `40 mm/s` · `80 kHz` · `1000 L/cm` · 2x |
| **Gold-Vermeil Seelen-Armband** (925er Gold-Vermeil) | `dark_on_light` | `2,5 mm` (1,2 + 1,3) · `34 %` · `130 mm/s` · `70 kHz` · `400 L/cm` | `2,5 mm` · `34 %` · `180 µs` · `70 kHz` · `1000 DPI` | Schon-Oxidation (Pos. 07): `2,4 mm` · `55 %` · `60 mm/s` · `90 kHz` · `1000 L/cm` |
| **585er Echtgold Seelen-Herz** (`13 × 14 × 0,8 mm`) | `dark_on_light` | `0,8 mm` (Dunkelton `1,6 mm`) · `70 %` · `80 mm/s` · `60 kHz` · `800 L/cm` · 2x | nur Vektor | |
| **Der Seelen Kristall** (`Ø 161,5 × 40 mm`, K9) | `white_on_dark` | `33,1 mm` · `60 %` · `80 mm/s` · `40 kHz` · `300 L/cm` | `33,1 mm` · `100 %` · `450 µs` · `40 kHz` · `500 DPI` · Jarvis | PDF 1 Frost `500 µs` · `600 DPI` · Master-Referenz `32,8 mm` · `85 %` · `500 µs` · `600 DPI` · Innengravur (Pos. 09, `inner_3d`) Offset `-15 bis -20 mm` · `80 %` · `220 µs` · `600 DPI` · Jarvis |
| **Feierabend-Funke Weizenbierglas** (RA3, `Ø 74 × 218 mm`) | `white_on_dark` | `Ø 74,0 mm` Scheitel · `55 %` · `380 mm/s` · `40 kHz` | `55 %` · `260 µs` · `40 kHz` · `400 DPI` · Jarvis | Kräftig-Frost (Pos. 11): Vektor `80 %` · `125 mm/s` · `300 L/cm`, Bild `92 %` · `650 µs` · `500 DPI` |
| **Gutscheinkarte Alu** (`86 × 54 mm`) | Schwarz `white_on_dark` / Gold `dark_on_light` | Schwarz `0,8 mm` · `45 %` · `800-900 mm/s` · `50-60 kHz` · `450 L/cm` | Schwarz `220 µs` · `500 DPI` · Schwellenwert | Gold `1,2 mm (+0,4)` · `52 %` · `450 mm/s` · `50 kHz` · `800 DPI` |

**Werkstoff-Presets (Schritt 1):** Edelstahl V2A Anlass-Schwarz `+1,5 mm` · `55 %` · `95 mm/s` · Bitmap `260 µs` · `1000 DPI` · `55 kHz` | Edelstahl V2A Silber-Frost `0,0 mm` · `85 %` · `600 mm/s` · `200 µs` · `500 DPI` · `40 kHz` | Naturschiefer (`white_on_dark`) `0,0 mm` · Bild `90 %` · `350 µs` · `400 DPI` · Jarvis · Vektor `75-85 %` · `350-500 mm/s` · `40 kHz` | Alu-Flaschenöffner Conveyor `0,0 mm` · `55 %` · `750 mm/s` · `45 kHz` · `450 L/cm` | Massivholz & Bambus `0,0 mm` · `45-55 %` (Bambus `35 %`) · `250-650 mm/s` · Bild `160-280 µs` · `300-450 DPI` · `40-50 kHz` | Messing poliert `+0,8..+1,0 mm` · `80-85 %` · `120-220 mm/s` · Bild `280 µs` · `800 DPI` · `45-70 kHz` · 1-2x | Acrylglas & Echtleder `0,0 mm` · `38 %` · `650 mm/s` · `140 µs` · `300 DPI` · `60 kHz`.

### Foto-Dot-Gain-Regel (Lösung für zu helle Fotos auf dem Seelenanhänger)
* **Physik:** Bei `600 DPI` liegt der Punktabstand bei `42,3 µm`, der UV-Krater misst `42-48 µm`. Ab ca. `58-60 %` Punktdichte überlappen die Krater und das schwarze PVD/Eloxal wird flächig zu `100 %` abgetragen (Gesicht verschmilzt zur Silberfläche).
* **Automatische Pipeline (`app/Services/Laser/LaserPhotoPreprocessor.php`):** Halbton-Foto vs. Silhouette erkennen -> P2..P98 Tonwertspreizung -> Unsharp Mask -> Substrat-Kurve -> `Gray = round(255 · (1 - d))`.
* **`white_on_dark`:** Luminanz `< 0,13` = `0 %`, Dot-Gain-Gamma `1,90`, Highlight-Deckel `78 %`. Gemessen auf `seelen-anhaenger` (synthetisches helles Porträt, Konfigurator-Upload): mittlere Gesichts-Laserdichte **vorher `71,4 %` -> nachher `56,7 %`** (echtes RGB-Foto vorher `7,5 %` mit falscher Polarität und `72,5 %` Hintergrund -> nachher `56,5 %` Gesicht), dunkler Hintergrund `0 %`. Ablauf: P2..P98 Spreizung + S-Kurve (`k = 0,22`) + Unsharp Mask (`0,85` · `σ 0,12 mm`).
* **`dark_on_light`:** `d = k^1,40 · 0,82` mit `k = 1 - Luminanz`, Lichter `< 10 %` bleiben reines Gold/Silber/Holz.
* **Lesbarkeits-Schutzsaum:** Um jeden Buchstaben und jedes Symbol über einem Foto bleibt ein Saum von ca. `0,45 mm` ohne Foto-Laserung stehen (auf dem Anhänger eine feine tiefschwarze Kontur um die silberweiße Schrift).
* **Jarvis-Kontrolle:** Bei Halbton-Fotos liegt im `.xcs` zusätzlich `seelenfunke/Laser_Foto_Jarvis_Vorschau.png` (12-Gewichte Jarvis-Judice-Ninke), beim Senden an den Laser auch unter `storage/app/public/bestellungen/xtool/Laser_Foto_Jarvis_Vorschau.png`.

---

## 1a. System-Integration im Shop (`/admin/products` Schritt 4.2 & `/admin/orders`) + 3 PDF-Anleitungen

Alle gerätespezifischen Voraussetzungen und die 1:1 xTool Studio Parameter sind direkt am jeweiligen Produkt unter **`/admin/products` -> Schritt `4.2 xTool Laser-Parameter` -> Kachel `Anleitung: Voraussetzungen & xTool Bearbeitung`** hinterlegt und werden automatisch in der Bestellabwicklung (**`/admin/orders`**) neben dem SVG-Download eingeblendet:

* **Hinterlegte PDF-Equipment-Anleitungen (`storage/app/public/produkte/laser-anleitungen/`, `/admin/ai-workspace -> Berufsleben/Laser-Anleitungen` & Desktop):**
  1. **`Anleitung_1_360_Grad_Gravur_xTool_RA3.pdf`**: 360°-Rotationsaufsatz **RA3** (Auspacken, Spannbacken/Rollen, Anschluss & Kalibrierung für Gläser/Trinkgefäße).
  2. **`Anleitung_2_Laufband_Massenproduktion_xTool_F2_Ultra_UV.pdf`**: **Förderband (Conveyor)** (Schienen-Montage, Kabelverbindung & Serienfertigung z. B. für Kugelschreiber).
  3. **`Anleitung_3_3D_Innengravur_xTool_F2_Ultra_UV.pdf`**: **3D-Innengravur-Set** (Wechsel auf goldene Innengravur-Linse, schwarzer Riser-Tisch, K9-Messschieber-Maße & `Konturgravur`).
* **Pflicht-Vollständigkeitsprüfung (`Laser-Werte fehlen`):** Solange bei einem physischen personalisierbaren Produkt die xTool-Werte in Schritt `4.2` fehlen, meldet das Produkt in der Kollektions-Übersicht den roten Klick-Badge **`✕ Laser-Werte fehlen`** und springt per Klick direkt in Schritt `4.2`.

---

## 2. Verbindlicher Schritt-für-Schritt-Ablauf am xTool F2 Ultra UV (Glas & Kristall)

1. **Vorbereitung, Baumwollhandschuhe & UV-Glastest (Vorab-Qualitätskontrolle):**
   * **Weiße Baumwollhandschuhe anziehen:** Den K9-Glasrohling ab sofort nur noch mit weißen Baumwollhandschuhen berühren und die Oberfläche komplett fett- und staubfrei halten (Fingerabdrücke streuen den 355-nm-UV-Strahl).
   * **UV-Lampen-Kontrolle:** Vor dem Start mit der **UV-Lampe** durch das K9-Glas leuchten und prüfen, dass im Glasinneren keine Schlieren, Einschlüsse oder Wolken vorhanden sind und auf der Oberfläche keine Fussel/Fettspuren liegen.
2. **Linse & Unterlage prüfen:**
   * Für flache Trophäen (wie **Der Seelenkristall**) bleibt die **Standard-Gravurlinse** im Laserkopf installiert (kein Wechsel auf die goldene Innengravur-Linse nötig).
   * Immer das schwarze **Lamellenrost (Slat Panel)** auf die gelochte Bodenplatte legen, damit der Laserstrahl unter dem Glas frei zwischen den Stegen ausläuft und niemals auf der Bodenplatte reflektiert.
3. **Rohling einlegen & waagerecht ausrichten (Nivellieren):**
   * **Verbindliche Methode beim Seelenkristall (Alinas Praxis-Lösung):** Die Trophäe auf der schwarzen Unterlegplatte (Lamellenrost) so weit unten platzieren, dass der dickere **Standfuß über der Kante frei in der Luft schwebt**.
   * **Warum kein Unterlegen mit dem Metallteil aus dem Nähkasten:** Der angeklebte/geschliffene Glasfuß ist fertigungsbedingt (vom Hersteller) minimal schräg gearbeitet. Wenn der Fuß aufliegt und oben das Metallteil aus dem Nähkasten untergelegt wird, überträgt sich diese Schräglage des Fußes auf die Kristallscheibe. Beim 355-nm-UV-Laser reicht bereits **1 mm Höhenunterschied** zwischen linker und rechter Seite aus, damit der Laser die Symbole auf der abweichenden Seite (wie die linken Herzen in Test 4) gar nicht mehr ins Glas lasert! Sobald der Fuß frei in der Luft schwebt, liegt die runde Kristallscheibe direkt und zu 100 % plan auf der schwarzen Unterlegplatte.
4. **Fokus-Höhe & Material in xTool Studio einstellen:**
   * **Material:** **`K9-Kristalltrophäe`** (alternativ `Klares Glas`) auswählen.
   * **Bekannter Rohling (Der Seelenkristall: UNANTASTBAR):** Direkt rechts in xTool Studio in das Feld `mm` den finalen Referenzwert **`32.8`** (`32,8 mm`) eintragen.
   * **Neuer Rohling (Einmaliges Einmessen):** Ein weißes Blatt Papier flach auf die Glasoberfläche legen -> in xTool Studio neben dem `mm`-Feld auf das **Fokus-Messsymbol** klicken -> **Papier vor dem Lasern zwingend wieder entfernen** -> Kamerabild (`Foto aktualisieren`) neu laden -> gemessenen `mm`-Wert hier in der Tabelle notieren.
5. **Druckdatei (`SVG (xTool F2)`) im Admin herunterladen & Ebenen prüfen:**
   * Die SVG-Datei aus der Bestellübersicht (`/admin/orders`) in xTool Studio ziehen und mittig auf dem Kamerabild des Rohlings ausrichten (Arbeitsfläche beim Seelenkristall: `68,0 %` Kreis, exakt bis zur inneren Facettenkante).
   * **KEIN `Invertieren` in xTool Studio aktivieren (bei Shop-SVGs):** Unser Shop-Konfigurator (`ProductConfigurator.php`, `$isPositivePhotoOnDark = true`) speichert Kundenfotos für Glas- und dunkle Oberflächen bereits **automatisch vor-invertiert (als Negativ)** und brennt den Spruch mit **schwarzem Buchstabenkern** (`#000`) und weißem Kontrast-Halo ein. Wenn man in xTool Studio nochmals auf `Invertieren` klickt, kehrt sich das Bild wieder in ein Positiv um (und die Schrift wird weiß mit schwarzem Rand!). Nur bei fremden, direkt in xTool Studio geladenen Originalfotos wird `Invertieren` benötigt.
   * **Foto-Ebene (`< Bild`: FINAL & UNANTASTBAR):** `Gravieren` · **`Brennpunkt: 32,8 mm`** · **`Punktdauer: 500 µs`** · **`Leistung: 85 %`** · **`DPI: 600`** · **`Bearbeitungsanzahl: 1`** · **`Frequenz: 40 kHz`** · **`Bitmap-Modus: Jarvis`** · **`Gravurmodus: Zweidirektional`**.
   * **Text- & Symbol-Ebenen:** Auf **`Gravieren`** (Flächenfüllung, `60 %`, `80 mm/s`, `300 Linien/cm`, `40 kHz`) stellen.
6. **Letzter Check vor dem Klick auf „Lasern“ & Arbeitsschutz:**
   * Nochmals mit der UV-Lampe prüfen, dass kein Staubkorn auf der Gravurfläche liegt -> Schutzhaube komplett schließen (Laserklasse 1 im geschlossenen Zustand) -> Gravur starten.

---

## 2a. Laufendes Praxis-Protokoll der Manufaktur-Durchläufe

Hier werden alle realen Test- und Erstgravuren Schritt für Schritt protokolliert, um ein lückenloses Nachschlagewerk für jedes Produkt aufzubauen:

### Produkt: Der Seelenkristall (`K9-Kristalltrophäe 180 × 200 × 40 mm`)
* **Grund-Setup & Vorbereitung:**
  * **Linse & Unterlage:** Standard-Gravurlinse · Schwarze Unterlegplatte (Lamellenrost) · **Ab Test 5:** Trophäe so weit unten auf die schwarze Unterlegplatte gelegt, dass der **Fuß frei in der Luft schwebt**, damit die Kristallscheibe perfekt gerade aufliegt *(Test 1–4: Metallteil unter der oberen Kante)*.
  * **Reinigung & Qualitätskontrolle:** Oberfläche vorab mit **Isopropanol** entfettet und mit **Brillenputztuch** nachpoliert · Einlegen mit **weißen Baumwollhandschuhen** · Prüfung mit **UV-Lampe** auf Schlierenfreiheit.

| Versuch | Fokus (`mm`) | Bild-Parameter (`< Bild`) | Text & Herzen (Vektor) | Geschätzte Dauer & Beobachtung / Ergebnis |
| :--- | :--- | :--- | :--- | :--- |
| **Test 1** *(Abgebrochen)* | **`47,0 mm`** *(0,3 mm unter Oberfläche)* | `Punktdauer: 200 µs` · `Leistungsbereich: 1 - 60 %` · **`DPI: 500`** · `Frequenz: 40 kHz` · `Invertieren: Aktiv (Doppel-Invertierung!)` | `Gravieren` · `60 %` (`62 %` Herzen) · `80 mm/s` · `300 L/cm` · `40 kHz` | **Direkt gestoppt:** Angezeigte Dauer **`01:08:44 Std.`** (wegen `500 DPI`). Keinerlei Veränderung im Glas sichtbar. |
| **Test 2** *(Abgebrochen)* | **`47,0 mm`** | `Punktdauer: 200 µs` · `Leistungsbereich: 60 %` · **`DPI: 200`** · `Frequenz: 40 kHz` · `Invertieren: Aktiv (Doppel-Invertierung!)` | `Gravieren` · `60 %` (`62 %` Herzen) · `80 mm/s` · `300 L/cm` · `40 kHz` | **Dauer auf `12 Min.` reduziert** (dank `200 DPI`), aber durch die doppelte Invertierung noch keine Gravur am oberen Rand. |
| **Test 3** *(Abgebrochen)* | **`47,3 mm`** *(Oberfläche)* | `Punktdauer: 200 µs` · **`Leistungsbereich: 80 %`** · **`DPI: 200`** · `Frequenz: 40 kHz` · `Invertieren: Aktiv (Doppel-Invertierung!)` | `Gravieren` · `60 %` (`62 %` Herzen) · `80 mm/s` · `300 L/cm` · `40 kHz` | **Ursache gefunden (Alinas Volltreffer):** Auch bei `47,3 mm` und `80 %` passierte oben nichts, weil unsere Shop-SVG (`ProductConfigurator.php` Zeile 988) das Bild bereits **vor-invertiert** ausgibt! Durch das zusätzliche `Invertieren` in xTool Studio wurde das Negativ wieder zum Positiv (weiße Schrift mit schwarzem Rand, weißer oberer Rand = `0 %` Laser). |
| **Test 4** *(Nach 7 Min. ausgewertet & Export-Fix in `FileDownloadService.php`)* | **`47,3 mm`** *(Oberfläche rechts)* | `Punktdauer: 200 µs` · `Leistungsbereich: 60 - 60 %` (`Graustufen`) · **`DPI: 200`** · `Frequenz: 40 kHz` · **`Invertieren: AUS`** | `Gravieren` · `60 %` (`Philipper 1, Vers 3`) & `62 %` (Herzen) · `80 mm/s` · `300 L/cm` · `40 kHz` | **Wichtiges Teilergebnis & Ursache der Seiten-Abweichung:** **1)** Rechts wurden die **Herzen** (`62 %`, `80 mm/s`, `300 L/cm`) und der Vektor-Text **`Philipper 1, Vers 3`** (`60 %`, `80 mm/s`, `300 L/cm`) sauber ins Glas gelasert! **2)** Links fehlten die Herzen trotz identischer Vektor-Ebene -> **Ursache:** Vorher lag das Metallteil aus dem Nähkasten oben drunter, während die Trophäe unten auf dem Glasfuß stand. Da der Fuß herstellerseitig (aus China) minimal schräg geschliffen/verklebt ist, entstand von rechts nach links ca. **1 mm Höhenunterschied**: und schon bei 1 mm Fokus-Abweichung lasert der UV-Laser die Symbole nicht mehr ins Glas! **3)** Das Foto (`< Bild`) + Hauptspruch fehlten wegen zu blasser Graustufen (`Ø 150,5`, kein Schwarz `0`). **Fix in `FileDownloadService.php`:** Automatische Kontrastspreizung auf echtes Tiefschwarz (`0..242`) bei 100 % transparenten Ecken außerhalb des Kreises (keine weißen Quadrat-Ränder in xTool Studio) + sauber eingebettete Originalschrift (`Caveat`). |
| **Test 5** *(Abgebrochen)* | **`32,6 mm`** *(Mit aufgelegtem Papier/Pappe gemessen bei frei schwebendem Fuß + Unterlegscheibe; vorher `47,3 mm`)* | `Punktdauer: 200 µs` · `Leistungsbereich: 60 %` · **`DPI: 200`** · `Frequenz: 40 kHz` · **`Invertieren: AUS`** (Optimierte Shop-SVG ohne weiße Quadrat-Ecken) | `Gravieren` · **Herzen links & rechts auf `60 %`** · `80 mm/s` · `300 L/cm` · `40 kHz` | **Beobachtung:** Trophäe liegt mit Unterlegscheibe so auf der schwarzen Platte, dass der Fuß frei in der Luft schwebt. Bei `32,6 mm` (gemessen inklusive der Dicke des aufgelegten Papiers/Pappe) griff der Laser oben noch nicht in die Glasoberfläche, da der Brennpunkt durch die Papier-/Pappedicke minimal über dem Glas lag. |
| **Test 6** *(Abgeschlossen)* | **`32,4 mm`** *(Papier-/Pappedicke von `0,2 mm` abgezogen)* | `Punktdauer: 200 µs` · `Leistung: 90 %` · **`DPI: 200`** · `Frequenz: 40 kHz` · **`Bitmap-Modus: Jarvis`** (Kleines Testfoto) | - | **Ergebnis:** Fokus-Korrektur um `-0,2 mm` auf **`32,4 mm`** greift auf der Glasoberfläche, aber mit `200 µs` Punktdauer wird das Bild nur **ganz leicht und hauchdünn** ins Glas gelasert. |
| **Test 7** *(Abgeschlossen: BESTES ERGEBNIS / SWEET-SPOT)* | **`32,4 mm`** | **`Punktdauer: 800 µs`** *(von `200 µs` erhöht!)* · **`Leistung: 90 %`** · **`DPI: 200`** · `Frequenz: 40 kHz` · **`Bitmap-Modus: Jarvis`** | - | **Bisher bestes Ergebnis (Sweet-Spot):** Alle Werte wie zuvor belassen und gezielt die **Punktdauer von `200 µs` auf `800 µs` erhöht** -> Das Bild ist **deutlich sauberer und am besten im Glas zu erkennen**! |
| **Test 8** *(Abgeschlossen: Zu tief)* | **`32,4 mm`** | **`Punktdauer: 1000 µs`** *(von `800 µs` erhöht)* · **`Leistung: 90 %`** · **`DPI: 200`** · `Frequenz: 40 kHz` · **`Bitmap-Modus: Jarvis`** | - | **Ergebnis:** Bei **`1000 µs`** wandert die Gravur noch weiter **tiefer ins Glas hinein**, wodurch man das Bild wieder **schlechter erkennen** kann. Fazit: **`800 µs` Punktdauer (Test 7)** liefert das beste Ergebnis! |
| **Test 9** *(Abgeschlossen: Noch besser als Test 7)* | **`32,4 mm`** | **`Punktdauer: 800 µs`** · **`Leistung: 100 %`** · **`DPI: 250`** · `Frequenz: 40 kHz` · **`Bitmap-Modus: Jarvis`** | - | **Ergebnis:** Durch das Erhöhen auf **`100 %` Leistung** und **`250 DPI`** (bei `800 µs` Punktdauer) ist das Bild **noch besser erkennbar** als in Test 7, aber noch nicht ganz so deckend-kräftig wie die Vektor-Herzen aus Test 4 (`300 Linien/cm` = `762 DPI`). |
| **Test 10** *(Abgeschlossen: Sehr gut mit Shop-SVG, Text perfekt)* | **`32,4 mm`** | **`Punktdauer: 1000 µs` / `1200 µs`** · **`Leistung: 100 %`** · **`DPI: 500` / `300`** · `Frequenz: 40 kHz` · **`Bitmap-Modus: Jarvis`** (Mit unserer Shop-SVG) | `Gravieren` · `60 %` · `80 mm/s` · `300 L/cm` · `40 kHz` | **Ergebnis:** Der **Text ist super und kam perfekt (tiefmatt-weiß) auf die Trophäe**! Das Foto (`< Bild`) sieht schon gut aus, ist aber im Vergleich zum Vektor-Text und zu matt-weißen Trophäen noch zu schwach/wenig deckend. |
| **Test 11** *(Abgeschlossen: Fehlgeschlagen mit `800 DPI` & `2000 µs`)* | **`32,4 mm`** | **11a:** **`DPI: 800`** (`100 %`, `Jarvis`) · **11b:** **`Punktdauer: 2000 µs`** (`500 DPI`, `100 %`, `Jarvis`) | - | **Beide Extremwerte fehlgeschlagen:** **1)** `800 DPI` dauert extrem lange und sieht optisch nicht gut aus. **2)** `2000 µs` Punktdauer bringt ebenfalls nichts (je höher die Punktdauer über `800 µs` steigt, desto tiefer bohrt der Strahl ins Glas, statt an der Oberfläche matt-weiß zu werden). |
| **Test 12** *(Abgeschlossen: Schon besser)* | **`32,4 mm`** | **`Punktdauer: 500 µs`** · **`Leistung: 100 %`** · **`DPI: 500`** · `Frequenz: 40 kHz` · **`Bitmap-Modus: Jarvis`** | - | **Ergebnis:** „So lala, aber auf jeden Fall schon besser!“ Die kürzere Punktdauer (`500 µs` statt `1000–2000 µs`) hält die Gravur näher an der Oberfläche. |
| **Test 13** *(Abgeschlossen)* | **`32,4 mm`** | **`Punktdauer: 400 µs`** · **`Leistung: 100 %`** · **`DPI: 600`** · **`Frequenz: 40 kHz`** · **`Bitmap-Modus: Jarvis`** | - | **Ergebnis:** Getestet mit kürzerer Punktdauer (`400 µs`) und höherer Dichte (`600 DPI`); im Anschluss Rückkehr zur Kombination aus moderaten `250 DPI` und kräftigerer Punktdauer. |
| **Test 14** *(Abgeschlossen: Fehlgeschlagen)* | **`32,4 mm`** | **`Punktdauer: 1000 µs`** · **`Leistung: 100 %`** · **`DPI: 250`** · **`Frequenz: 40 kHz`** · **`Bitmap-Modus: Jarvis`** | - | **Ergebnis:** „Gar nichts, richtig schlecht.“ Bestätigt erneut wie in Test 8, dass `1000 µs` in Kombination mit niedrigen `250 DPI` zu tief ins Glas schlägt und keine saubere Zeichnung liefert. |
| **Test 14b** *(Abgeschlossen: Besserung)* | **`32,4 mm`** | **`Punktdauer: 400 µs`** · **`Leistung: 100 %`** · **`DPI: 500`** · **`Frequenz: 40 kHz`** · **`Bitmap-Modus: Jarvis`** | - | **Ergebnis:** „Definitiv noch mal eine Besserung gehabt!“ Die `500 DPI` bringen sichtbar mehr Klarheit an der Oberfläche. |
| **Test 15** *(Abgeschlossen: EINES DER BESTEN ERGEBNISSE / TOP-REFERENZ)* | **`32,4 mm`** | **`Punktdauer: 800 µs`** · **`Leistung: 100 %`** · **`DPI: 500`** · **`Frequenz: 40 kHz`** · **`Bitmap-Modus: Jarvis`** | - | **Volltreffer (Alinas Urteil: „Bis jetzt eines der besten Ergebnisse!“):** Die erstmalige Kombination aus dem Tiefen-Sweet-Spot **`800 µs`** und der hohen Punktdichte **`500 DPI`** bei **`100 %` Leistung** und **`Jarvis`** liefert das bisher stärkste, klarste Bild im K9-Glas! |
| **Test 16** *(Abgeschlossen)* | **`32,4 mm`** | **`Punktdauer: 850 µs`** · **`Leistung: 100 %`** · **`DPI: 450`** · **`Frequenz: 40 kHz`** · **`Bitmap-Modus: Jarvis`** | - | **Feinabstimmung um den `800 µs / 500 DPI` Bereich:** Punktdauer `850 µs` und `450 DPI` getestet; anschließend weitere Versuchsreihe mit Fokus-Feinabstimmung auf `32,8 mm`. |
| **🏆 FINALE MASTER-REFERENZ (UNANTASTBAR)** | **`32,8 mm`** (`32.8`) | **`Punktdauer: 500 µs`** · **`Leistung: 85 %`** · **`DPI: 600`** · **`Bearbeitungsanzahl: 1`** · **`Frequenz: 40 kHz`** · **`Bitmap-Modus: Jarvis`** · **`Gravurmodus: Zweidirektional`** | `Gravieren` · `60 %` · `80 mm/s` · `300 L/cm` · `40 kHz` | **ABSOLUTE, FINALE & UNANTASTBARE BESTEINSTELLUNG („Jetzt haben wir's!“):** Mit der neuen HDR-/Mikrokontrast-Bildaufbereitung und **`32,8 mm` Brennpunkt-Höhe**, **`500 µs` Punktdauer**, **`85 %` Leistung**, **`600 DPI`**, **`Bearbeitungsanzahl: 1`**, **`40 kHz` Frequenz**, **`Bitmap-Modus: Jarvis`** und **`Gravurmodus: Zweidirektional`** sitzt das Ergebnis perfekt auf dem Seelenkristall! |

---

## 3. Wichtiger System-Unterschied: Oberflächengravur vs. Echte 3D-Innengravur

Der xTool F2 Ultra UV besitzt **zwei unterschiedliche Linsen und Aufbauten**, die strikt nach Produkttyp getrennt werden müssen:

| Modus | Eingesetzte Linse & Unterlage | Wann verwenden? | Funktionsweise in xTool Studio |
| :--- | :--- | :--- | :--- |
| **A) 2D-Oberflächen- & Flach-Subsurface-Gravur** *(Standard für flache Trophäen & Schmuck)* | **Standard-Gravurlinse** + Schwarzes Lamellenrost (oder Bodenplatte) | **Der Seelenkristall (`180 × 200 × 40 mm`)**, Glas-Untersetzer, Trinkgläser (mit RA3), Edelstahl-Schmuck, Gold-Alu-Gutscheinkarte, Schiefer, Holz | Modus `Auf flacher Oberfläche lasern` · Material z. B. `K9-Kristalltrophäe` oder `Klares Glas` · Fokus auf Oberfläche (`47,3 mm`) oder `0,3 mm` bis `1,0 mm` leicht ins Glas (`47,0 mm`) für glatte Oberseite |
| **B) Echte 3D-Innengravur (Subsurface 3D)** *(Für tiefe K9-Glasblöcke/Würfel bis `70 × 70 × 150 mm`)* | **Goldene Innengravur-Linse** (spezielle Fokussierlinse einschrauben!) + **Schwarzer Riser-Tisch** (Schrift nach vorne lesbar, Schieber mit Pfeil auf halbe Blockbreite, z. B. `35` bei `70 mm` oder `25` bei `50 mm`) | Reine K9-Kristallblöcke / Quader / Würfel (z. B. `50 × 50 × 80 mm` oder `70 × 70 × 70 mm`) mit mehrschichtigen 3D-Hologrammen oder 3D-Porträts mitten im Glas | Modus `Innengravur - 3D-Miniatur` · Exakte Blockmaße (`L × B × H` per Messschieber!) eintragen · Brechungsindex fest auf **`1,5`** (K9-Glas) · Immer **`Konturgravur`** statt *Vollständige Gravur* wählen |

> [!IMPORTANT]
> **Warum für unseren flachen Seelenkristall (`180 × 200 × 40 mm`) Modus A (Standard-Linse) gilt:**
> Der Modus *Innengravur (3D-Miniatur)* mit der goldenen Speziallinse und dem Riser-Tisch ist technisch auf K9-Quader bis maximal `70 × 70 × 150 mm` begrenzt. Da unsere Seelenkristall-Trophäe `180 × 200 mm` groß ist und eine dünne Scheibenform mit Facettenrand hat, wird sie liegend mit der **Standard-Linse** auf dem Lamellenrost bei `47,3 mm` (Oberfläche) bzw. `47,0 mm` (`0,3 mm` unter der Oberfläche) graviert.

---

## 4. Erprobte Referenz-Parameter für den xTool F2 Ultra UV (5W, 355 nm)

Zusammenstellung der getesteten Praxis-Richtwerte aus den Fach-Reviews (*Willi's 3D-Druck*, *Gipfelholz* und *Techmagnet*):

### 4.1 Glas & K9-Kristallglas

| Anwendung / Material | Leistung (%) | Geschwindigkeit / Punktdauer | Linien/cm / DPI | Frequenz (kHz) | Besonderheiten & Modus (Exakt laut Video-Transkripten) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Glas / K9 Oberflächengravur: VEKTOR (Text & Symbole)** | **`60 %`** | **`80 mm/s`** | **`300 Linien/cm`** | **`40 kHz`** | *Quelle: Gipfelholz-Tabelle* · Modus `Gravieren` (Flächenfüllung) · Hat in **Test 4** bei den Herzen & `Philipper 1, Vers 3` sofort funktioniert! |
| **Glas Oberflächengravur: FOTO / BITMAP (`< Bild`: UNANTASTBARE MASTER-REFERENZ)** | **`85 %`** | **`Punktdauer: 500 µs`** | **`600 DPI`** | **`40 kHz`** | **🏆 ABSOLUTE, FINALE & UNANTASTBARE EINSTELLUNG (Der Seelenkristall):** **`Brennpunkt-Höhe: 32,8 mm`** · **`Punktdauer: 500 µs`** · **`Leistung: 85 %`** · **`DPI: 600`** · **`Bearbeitungsanzahl: 1`** · **`Frequenz: 40 kHz`** · **`Bitmap-Modus: Jarvis`** · **`Gravurmodus: Zweidirektional`**! |
| **K9-Kristall 3D-Innengravur (3D-Modell / 3D-Foto)** | **`50 % - 58 %`** | Punktdauer **`180 µs`** | Schichthöhe **`0,03 mm`** (bis `0,05 mm`) · Punktabstand **`0,05 mm`** (bis `0,03 mm`) | **`40 kHz`** | Goldene Innengravur-Linse + Riser-Tisch · Zwingend **`Konturgravur`** aktivieren |
| **K9-Kristall 3D-Innengravur (Schrift / Logo im Block)** | **`60 %`** | Punktdauer **`250 µs`** | Schichthöhe **`0,03 mm`** · Punktabstand **`0,04 mm`** | **`60 kHz`** | Goldene Innengravur-Linse · Schwebender Text vor 3D-Motiv (keine Überlappung!) |

### 4.2 Metalle, Schmuck & Gold-Aluminium-Gutscheinkarte

| Anwendung / Material | Leistung (%) | Geschwindigkeit / Punktdauer | Linien/cm (DPI-Äquivalent) | Frequenz (kHz) | Besonderheiten & Modus |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Edelstahl glänzend (Fotogravur)** | **`90 %`** | Punktdauer **`90 µs`** | **`500 L/cm`** *(bzw. `50.000` Feinraster)* | **`40 kHz`** | Bildmodus: **`Jarvis`** · Extrem feiner Kontrast ohne Materialverzug |
| **Edelstahl / Stahl (Logo & Textgravur)** | **`100 %`** | **`100 mm/s`** | **`200 Linien/cm`** | **`40 kHz`** | Saubere, dunkle Markierung auf Edelstahl-Schmuckanhängern |
| **Eloxiertes Aluminium / Alu-Visitenkarte (Gold/Schwarz)** | **`90 % - 91 %`** | Punktdauer **`90 µs`** | **`500 L/cm`** | **`40 kHz`** | Bildmodus: **`Jarvis`** · Perfekte Referenz für unsere **Gold-Aluminium-Gutscheinkarte** (`85 × 54 mm`) |
| **Messing (3D-Relief / Münzprägung)** | **`90 %`** | **`500 mm/s`** | **`500 Linien/cm`** | **`40 kHz`** | `100 Ebenen` · Gravurwinkel `30°` · Z-Achse `-0,01 mm` pro Ebene |

### 4.3 Holz, Leder & Naturmaterialien

| Anwendung / Material | Leistung (%) | Geschwindigkeit / Punktdauer | Linien/cm | Frequenz (kHz) | Besonderheiten & Modus |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Holz (Logo- & Textgravur, z. B. Nussbaum/Buche)** | **`70 %`** | **`210 mm/s`** | **`200 Linien/cm`** | **`40 kHz`** | Kein Schmauchspuren-Rand dank kaltem UV-Licht („Cold Processing“) |
| **Holz (Fotogravur)** | **`90 %`** | Punktdauer **`1000 µs`** | **`250 Linien/cm`** | **`40 kHz`** | Bildmodus: **`Jarvis`** |
| **Holz (3D-Relief / Tiefengravur Buche)** | **`100 %`** | **`1000 mm/s`** | **`160 Linien/cm`** | **`40 kHz`** | `100 Ebenen` · Gravurwinkel `30°` · Z-Achse `-0,05 mm` pro Ebene |
| **Leder / Kunstleder (Gravur)** | **`80 %`** | **`600 mm/s`** | **`250 Linien/cm`** | **`40 kHz`** | Extrem randscharf ohne Verbrennen der Nahtkanten |
| **Holz / Sperrholz Schneiden (`3 mm`)** | **`100 %`** | **`8 mm/s`** | `2 Durchgänge` | **`40 kHz`** | Absaugung (*SafetyPro IF2 2.0*) auf volle Stufe stellen |

---

## 5. Goldene Praxis-Tipps für K9-Kristallglas & UV-Laser (Aus den Video-Analysen)

1. **Baumwollhandschuhe & 100 % fettfreie Oberfläche (Pflicht bei K9-Glas):**
   * K9-Kristallglas vor dem Einlegen immer mit Glasreiniger/Isopropanol abwischen und nur noch mit unseren **weißen Baumwollhandschuhen** anfassen. Selbst ein unsichtbarer Fingerabdruck auf der Oberseite streut den hochfrequenten 355-nm-UV-Strahl und erzeugt blasse Stellen im Gravurbild.
2. **UV-Taschenlampen-Qualitätstest bei K9-Rohlingen:**
   * Vor einer aufwendigen Gravur kurz mit einer kleinen UV-Taschenlampe seitlich durch den K9-Kristall leuchten: Das Glas muss im Inneren völlig klar sein und darf keine milchigen Schlieren oder Wolken zeigen. Normales Fensterglas oder durchgefärbtes Dekoglas funktioniert für UV-Innengravuren nicht, da xTool Studio fest mit dem **Brechungsindex `1,5` von reinem K9-Kristall** rechnet.
3. **Exaktes Nachmessen mit dem Messschieber:**
   * Verlasse dich bei K9-Blöcken niemals auf das Nennmaß der Verpackung (ein „70 × 70 mm“-Würfel misst real oft `68,0 × 67,8 × 67,8 mm`). Immer alle 3 Achsen (`Länge`, `Breite`, `Höhe`) mit dem digitalen Messschieber prüfen und millimetergenau in xTool Studio eintragen.
4. **2D-Kundenfotos in 3D-Modelle umwandeln (xTool Studio `3D-Foto`):**
   * Für echte 3D-Kristallblöcke kann in xTool Studio jedes freigestellte 2D-Porträt oder Haustierfoto über den Button **`3D-Foto`** (KI-Tiefengenerator, 6 Credits) in ein plastisches 3D-Relief umgewandelt werden.
   * **Tipp zur Ausrichtung im Quader:** Das generierte 3D-Porträt im Vorschau-Würfel ganz leicht nach hinten neigen und einen Namen/Text als separates Element frei davor platzieren. **Wichtig:** Die Rahmen (Bounding-Boxen) von Bild und Text dürfen sich im 3D-Raum nicht schneiden (rote Warnung in xTool Studio beachten).
5. **Konturgravur statt Vollständige Gravur:**
   * Bei 3D-Innengravuren immer **`Konturgravur`** auswählen. Die Option *Vollständige Gravur* füllt das gesamte Innenvolumen des Modells mit Laserpunkten auf, was das Motiv undurchsichtig-milchig macht und die Gravurzeit vervielfacht.

---

## 6. Video-Referenzen & Quellen (xTool F2 Ultra UV)

* **Video 1 (Willi's 3D-Druck - K9-Glas & 3D-Innengravur Schritt-für-Schritt):**
  [Unglaublich! K9-Glas mit dem xTool F2 Ultra UV-Laser graviert](https://youtu.be/NjDfW7raSI0?is=0b9BJGZ8KL_MNOlB)
* **Video 2 (Gipfelholz - Großer 5W UV-Laser Material- & Parametertest):**
  [xTool F2 Ultra UV - 5 Watt UV Laser Test & Erfahrungen](https://youtu.be/PdSfzt6EvAU?is=H8CtVpYBvTynSbXi)
  *(Begleitende Parameter-Tabelle: https://werkzeugshop.gipfelholz.de/xtool-f2-ultra-uv-test-erfahrung/)*
* **Video 3 (Techmagnet - Ausführliches Review, Glas, Fokus-Tricks & Linsenwechsel):**
  [Dieser UV-Laser graviert PERFEKT Glas | xTool F2 Ultra UV im ausführlichen Review](https://youtu.be/CDcWbqLqtIQ?is=D3gWNFmQnqKeUZdA)
