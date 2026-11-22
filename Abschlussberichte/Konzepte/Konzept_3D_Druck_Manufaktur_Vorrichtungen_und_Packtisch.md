# Konzept & Ideen-Speicher: Inhouse-3D-Druck für Laser-Nullpunkt-Klickplatten, Packtisch-Organisation & Manufaktur-Werkzeuge

**Datum:** 16.11.2026  
**System & Hardware:** **Bambu Lab A1** (`256 × 256 × 256 mm`, Solo ohne AMS lite) + **xTool F2 Ultra UV** (`GS009-CLASS-4`, SN `MXFK007003260530H0194A6`, M4-Bodenraster `35 × 35 mm`) + Seelenfunke Pack- & Versandstraße  
**Status:** ✅ Referenz-Klickplatte Nr. 1 (*Der Seelen Kristall*, `.3mf` + `.stl`) fertig konstruiert & Drucker-Beschaffung vorbereitet  
**Ablageort 3D-Schablonen:** [`storage/app/public/produkte/laser-schablonen/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/storage/app/public/produkte/laser-schablonen)

---

## 1. Strategische Leitlinie & Unternehmerischer Mehrwert

### 1.1 Strikte Abgrenzung: Reines Manufaktur-Betriebsmittel (Kein Kundenprodukt)
Der 3D-Drucker wird bei **Mein-Seelenfunke** bewusst **nicht** zur Herstellung verkäuflicher Kundenprodukte eingesetzt (keine sichtbaren FDM-Schichtlinien im Premium-Geschenksegment, keine Produkthaftungs- oder CE-Risiken bei Kunststoffartikeln). Stattdessen dient der **Bambu Lab A1** zu **100 % als interner Vorrichtungs-, Lehren- und Betriebsmittel-Fertiger** für Laser-Produktion, Qualitätskontrolle und Packtisch-Ergonomie.

### 1.2 Wirtschaftliche Entscheidung (Hardware & Material-Standard)
* **Drucker-Wahl:** **Bambu Lab A1 (Solo, ohne AMS lite)** für **`239,00 €` brutto** (`200,84 €` netto, sofort abzugsfähiges GWG unter `800 €` netto).
  * *Begründung:* Das `256 × 256 × 256 mm` Druckbett deckt die gesamte `200 × 200 mm` Arbeitsfläche des `xTool F2 Ultra UV` in **einem einzigen, nahtlosen Stück** ab (der kleinere `A1 mini` mit `180 × 180 mm` wäre für das `140 × 140 mm` M4-Eckraster samt Außenrahmen zu klein). Auf das `AMS lite` (`+100 €`) wird bewusst verzichtet, da alle Vorrichtungen einfarbig in reflexionsfreiem Mattschwarz gefertigt werden.
* **Verbindlicher Seelenfunke-Filament-Standard (Bundle-Einkauf mit 47 % Rabatt):**
  1. **Standard-Vorrichtungen & Organizer:** **Bambu PLA Matte `Charcoal / Mattschwarz (11101)`** (`10,59 € / kg` inkl. Spule im Drucker-Bundle; ca. `1,30 € bis 2,20 €` Materialkosten pro großer Vorrichtung). Reflexionsfrei im UV-Laserraum, edle matte Haptik passend zur Schwarz-Gold-Manufaktur, verzugsfrei und extrem maßhaltig.
  2. **Mechanisch/thermisch belastete Teile & Faltlehren:** **Bambu PETG Matte `Black (35101)`** (`10,06 € / kg` inkl. Spule im Drucker-Bundle). Zäh-elastisch, abriebfest und wärmebeständig bis ca. `70 °C` für Klemmschellen, Abluft-Adapter, Federclips und tausendfach beanspruchte Karton-Falthilfen.

---

## 2. Säule A: Laser-Nullpunkt-Klickplatten („Reinklick-Flächen“ statt Lamellenrost)

### 2.1 Grundprinzip im xTool F2 Ultra UV
Anstatt den losen Metall-Lamellenrost (`Z = 21,9 mm`) zu nutzen und Rohlinge bei jedem Auftrag neu per Auge oder Kamera auszurichten, wird der Lamellenrost herausgenommen und eine produktspezifische **3D-gedruckte Nullpunkt-Wechselplatte** direkt in die **M4-Senkkopf-Gewindelöcher am Aluminium-Laserboden** eingeklickt bzw. verschraubt.

* **Vermessenes xTool M4-Bodenraster (`GS009-CLASS-4`):**
  * Exaktes `35,00 × 35,00 mm` Quadratraster, zentriert auf `(100,00 | 100,00) mm`: `X, Y ∈ {-5, 30, 65, 100, 135, 170, 205} mm`.
  * Die 4 äußeren Referenz-Gewindelöcher im Arbeitsfeld liegen exakt bei **`(30, 30)`, `(170, 30)`, `(30, 170)` und `(170, 170) mm`** (`140,00 × 140,00 mm` Stichmaß).
  * M4-Kernlochdurchmesser des Innengewindes (`M4 × 0,7`): `Ø 3,24 bis 3,30 mm` (erlaubt neben `M4 × 12 mm` Zylinderkopfschrauben auch schraubenlose 3D-gedruckte Schnellsteckstifte mit `Ø 3,16 mm` Pilotzapfen).

### 2.2 Verbindliche Konstruktionsregeln für alle künftigen Seelenfunke-Laserplatten
1. **Offenes UV-Durchschussfenster (Luft unter der Gravurzone):** Bei klarem K9-Kristallglas und durchstrahlten Rändern darf unter dem Gravurbereich niemals PLA liegen, damit der `355 nm` UV-Strahl keinen Kunststoff anschmort und keinen Schmauch auf der Glasrückseite absetzt.
2. **Elastische 4-Punkt-Zentrierfedern bei Glas-, Schiefer- & Natur-Rohlingen:** Da handgeschliffenes K9-Kristallglas und Naturschiefer um `±1,5 bis 2,0 mm` schwanken, erhalten Aufnahmen **freistehende vertikale Blattfedern** (`1,60 mm` Wandstärke = `4` Arachne-Wandlinien, `45°` Einlaufschräge oben), die jede Rohling-Toleranz symmetrisch auf denselben Mittelpunkt `(X_soll | Y_soll)` zentrieren.
3. **Referenz-Auflagehöhe `Z = 22,00 mm`:** Entspricht nahezu 1:1 der bisherigen Lamellenrost-Oberkante (`21,9 mm`), sodass bestehende Fokus-Erfahrungswerte aus [`Brennpunkt_Masse.md`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/Arbeitsdokumentation/Brennpunkt_Masse.md) erhalten bleiben und rückseitig überstehende Standfüße (wie beim Seelen Kristall) frei in der Luft schweben, ohne Kippfehler zu übertragen.
4. **100 % Stützfreies Design (`0 % Support`):** Alle Platten werden so konstruiert, dass sie flach auf `Z = 0,00 mm` ohne jede Stützstruktur drucken, oben den Produktnamen vertieft tragen (`0,8 mm` Prägung) und seitliche Eingriffmulden für schnelles Entnehmen mit Baumwollhandschuhen besitzen.

### 2.3 Bereits fertiggestellte & geplante Laser-Klickplatten
* ✅ **Platte 01: Der Seelen Kristall (`MSF-KRISTALL-3D-001`)**
  * Dateien: [`Seelen_Kristall_xTool_Komplettset_BambuA1.3mf`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/storage/app/public/produkte/laser-schablonen/seelen-kristall/Seelen_Kristall_xTool_Komplettset_BambuA1.3mf), [`Seelen_Kristall_xTool_Klickplatte_v1.stl`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/storage/app/public/produkte/laser-schablonen/seelen-kristall/Seelen_Kristall_xTool_Klickplatte_v1.stl), [`xTool_M4_Test_Steckstifte_4x.stl`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/storage/app/public/produkte/laser-schablonen/seelen-kristall/xTool_M4_Test_Steckstifte_4x.stl)
  * Fester Laser-Nullpunkt: **`X = 100,0 mm`**, **`Y = 95,0 mm`**, **`Winkel = 0,0°`**, **`Auflage = 22,0 mm`**, Spannbereich Federn `Ø 154,3 bis 163,4 mm`, offenes Durchschussfenster `Ø 138,0 mm`.
* 📋 **Geplante Folge-Klickplatten (Backlog):**
  * **Platte 02:** Mehrfach-Nesting-Platte (4er/6er-Matrix) für **Edelstahl-Schmuckanhänger & Schlüsselanhänger** (alle 4 bis 6 Plätze mit exakten festen Koordinaten für Seriengravur in einem Durchgang).
  * **Platte 03:** Zentrierplatte für **Westpack-Schmuckboxen** (exakter Anschlag für Deckel-Logo-/Wunschgravur ohne Verrutschen).
  * **Platte 04:** Feder-Zentrierplatte für **Schiefertafeln & Naturstein-Gedenktafeln** (gleicht bruchraue Außenkanten automatisch auf die Mitte aus).
  * **Platte 05:** Schonbacken- & Anschlag-Aufsätze für den **RA3 Rotationsaufsatz** (wiederholgenauer Längsanschlag für Trinkgläser, Thermobecher und Flaschen).

---

## 3. Säule B: Packtisch-, Versand- & Manufaktur-Ideenkatalog (Alinas Backlog)

Alle folgenden Module werden im einheitlichen **Seelenfunke Clean-Design (Mattschwarz `PLA Matte 11101` bzw. `PETG Matte 35101`, abgerundete Kanten, stützfrei druckbar)** passgenau auf Alinas reale Geräte- und Kartonmaße konstruiert:

### 1. Labeldrucker-Schienen & Etiketten-Auffangschale
* **Problem im Versandalltag:** Gedruckte `100 × 150 mm` DHL-Versandetiketten bzw. Produktlabels schießen aus dem Thermodirektdrucker nach vorne heraus, rollen sich ein, kleben versehentlich fest oder fallen vom Tisch. Hinten fehlt oft eine saubere Führung für Faltstapel (Fanfold) oder große Rollen.
* **3D-Druck-Lösung:**
  * **Vordere Auffang- & Stapelschiene:** Direkt unter die Ausgabekante des Labeldruckers einhängbare, leicht nach oben gewölbte Auffangrutsche (`Breite ca. 112 mm` mit seitlichen Leitkanten und vorderem Stopp-Steg), in der bis zu 30 gedruckte DHL-Labels plan und griffbereit übereinander landen.
  * **Optionaler hinterer Fanfold-Einzugskanal:** Schmale Tischführung hinter dem Drucker, damit Endlos-Stapelkerne absolut gerade ohne Schrägzug einlaufen.
* **Material & Aufwand:** `PLA Matte Charcoal`, ca. `75 bis 95 g` Filament (`~1,00 €`), Druckzeit ca. `2,5 Std.`.

### 2. Modularer Packtisch-Kartenhalter & Beilagen-Organizer
* **Problem im Versandalltag:** Beim liebevollen Verpacken müssen **Dankeskarten (DIN A6 / Postkartenformat)**, **Pflegehinweis-Kärtchen**, **Schwarz-Gold-Markenaufkleber / Siegelsticker**, **Mikrofaser-Poliertücher** und ggf. ein goldener Signatur-Stift jederzeit mit einem Griff erreichbar sein, ohne auf der Arbeitsfläche zu verrutschen.
* **3D-Druck-Lösung:**
  * **Stufen-Organizer im Clean-Design:** Kompakter, schwerer Tisch-Aufsteller (`ca. 220 × 145 mm` Grundfläche mit rutschfesten Gummifüßen-Mulden) mit:
    * Schräggestelltem **A6-Dankeskarten-Fach** mit vorderem Daumen-Ausschnitt (einzelne Karte lässt sich blind mit einer Hand abziehen),
    * Zweitem Stufenfach für **Echtheits-/Pflegekärtchen oder Gutscheinkarten**,
    * Integriertem **Aufkleber-Abzieh-Spender** für runde Seelenfunke-Siegelaufkleber (zieht das Trägerband über eine scharfe `1,5 mm`-Spenderkante um `180°` ab, sodass sich der Aufkleber direkt nach vorne ablöst!),
    * Seitlicher Köcher-Mulde für **Skalpell/Schere, Pinzette & Goldstift**.
* **Material & Aufwand:** `PLA Matte Charcoal`, ca. `180 g` Filament (`~1,90 €`), Druckzeit ca. `4,5 Std.`.

### 3. Geschenkpapier- & Geschenkband-Halter (Abroll-Station)
* **Problem im Versandalltag:** Für die optionale Geschenkverpackung (Schwarz-Gold-Geschenkpapier + edles Satin-/Ripsband) rollen lose Geschenkband-Spulen über den Tisch oder verheddern sich, und das Abschneiden auf exakte Bogenlänge kostet Zeit.
* **3D-Druck-Lösung:**
  * **Mehrfach-Geschenkband-Spender mit Klemmbremse:** Tisch- oder Regal-Klemmhalterung mit schnellwechselbarer Steckachse für 2 bis 3 Bandrollen unterschiedlicher Breite (`10 mm`, `15 mm`, `25 mm`). Eine sanfte gedruckte Blattfeder bremst die Spule leicht ab, sodass das Band nach dem Abschneiden nicht zurückschnellt und das Ende immer griffbereit vorne herausschaut.
  * **Geschenkpapier-Rollenhalter + geschützter Zip-Cutter (Schneidschlitten):** Paarweise Tischklemmen oder Wandböcke für die Schwarz-Gold-Geschenkpapierrolle plus ein kleiner aufsteckbarer **Schneidgleiter** (nimmt eine Standard-Trapezklinge komplett verdeckt und verletzungssicher im Kunststoffkanal auf) für kerzengerade Schnitte in 2 Sekunden.
* **Material & Aufwand:** Halter in `PLA Matte Charcoal`, Klemmschrauben/Federbremse in `PETG Matte Black`, ca. `160 g` (`~1,70 €`).

### 4. Tisch-Fernbedienungshalter („Kommando-Dock“)
* **Problem im Versandalltag:** Fernbedienungen am Arbeitsplatz (z. B. Studio-/Deckenlicht, Klimagerät/Lüftung, Kamera-Auslöser, Audio/Smart-Home-Taster) sowie der digitale Messschieber für Fokus-Kontrollen liegen verstreut oder werden von Kartons verdeckt.
* **3D-Druck-Lösung:**
  * **Maßgeschneidertes Tisch-Dock (oder Untertisch-Seitenhalter an der Tischkante):** Jede vorhandene Fernbedienung bekommt ihren exakt nach Innenmaß (`Breite × Tiefe + 1,2 mm Spiel`) eingelassenen Steckplatz in aufrechter `15°`-Neigung, sodass alle Tasten sogar im Stehen bedienbar sind, ohne die Fernbedienung herauszunehmen.
  * Optional seitlich kombinierbar mit einer gepolsterten Ablagegabel für den **digitalen Messschieber** (immer direkt neben dem `xTool F2 Ultra UV`).
* **Material & Aufwand:** `PLA Matte Charcoal`, ca. `110 g` (`~1,15 €`), wird bei Umsetzung exakt auf die `B × T`-Millimetermaße von Alinas vorhandenen Fernbedienungen parametrisiert.

### 5. Karton-Falthilfen (Faltlehren) & Manufaktur-Ersatzteile
* **Problem im Versandalltag:** Flache Versand- und Maxibriefkartons (Karton.eu / Kartonfritze) müssen an den Rilllinien vorgebrochen, aufgestellt und während des Befüllens offen gehalten werden, da die Seitenklappen sonst ständig wieder nach innen klappen. Zudem fallen im Betrieb regelmäßig Spezial-Kleinteile an (Schlauchschellen, Kabelklemmen, Anschläge).
* **3D-Druck-Lösung:**
  * **Eindrück-Faltlehre („Box-Jig“ für Maxibrief- & Standard-Versandkartons):** Eine rechteckige Aufstell-Matrize auf dem Packtisch. Man legt den flachen Kartonrohling mittig auf den Rahmen und drückt einmal mit dem passenden Innenstempel (oder direkt mit der Westpack-Schmuckbox) nach unten: Alle 4 Seitenwände stellen sich gleichzeitig im exakten `90°`-Winkel auf, und die Lehre hält den Karton beim Einlegen von Seidenpapier, Produkt und Dankeskarte stabil offen.
  * **Rill-/Falzbein mit `90°`-Anschlag:** Ergonomischer Falzgleiter aus abriebfestem `PETG Matte`, um störrische Wellpapp-Kanten in einer Bewegung sauber umzulegen.
  * **Manufaktur-Ersatzteile & Laser-Peripherie (`PETG Matte Black`):**
    * Zugentlastungs- und Tisch-Führungsschellen für den Abluftschlauch des **SafetyPro IF2 2.0 Inline-Ventilators**,
    * Einrastbare **Seitenführungs-Schienen für das xTool Förderband (Conveyor)** bei größeren Serienläufen,
    * Magnetische **Kabel- & USB-C-Halter** an der Tischkante (kein Herunterfallen der Anschlusskabel hinter den Schreibtisch).

### 6. ✅ Hybrid-Batterie-Spender & Flach-Organizer für 26 × 35,5 cm Lagerregal (fertig konstruiert)
* **Problem im Alltag:** Gemischte Batterie-Restbestände (Zink-Kohle vs. Alkaline, verschiedene Marken, lose Knopfzellen mit Kurzschlussgefahr) bei `26,0 cm` Regaltiefe und `35,5 cm` Fachhöhe möglichst schmal, übersichtlich und ohne mehrteiliges Steck-Gefummel unterbringen.
* **Hauptlösung (Einteiliger Hybrid-Kompakt-Spender, `172,0 × 246,0 × 230,0 mm`, `181 Plätze`, `0 % Support`):**
  * Dateien: [`Seelenfunke_Batterie_Hybrid_Spender_172x246x230_BambuA1.3mf`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/storage/app/public/manufaktur/3d-vorlagen/batterie-organizer/Seelenfunke_Batterie_Hybrid_Spender_172x246x230_BambuA1.3mf) & [`Seelenfunke_Batterie_Hybrid_Spender_172x246x230_v2.stl`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/storage/app/public/manufaktur/3d-vorlagen/batterie-organizer/Seelenfunke_Batterie_Hybrid_Spender_172x246x230_v2.stl)
  * **Raumökonomie:** Spart über `7 cm` Regalbreite (`17,2 cm` schmal) und lässt oben volle **`12,5 cm` freie Eingreif-Höhe** im `35,5 cm` Regalfach zum bequemen Nachwerfen von `AA` und `AAA`.
  * **4-Stufen-Aufbau aus einem Guss (ohne Schwalbenschwanz/Stecksystem):**
    1. **Vorderkante (`Z = 12,6 mm`):** `82` kurzschlusssicher isolierte Knopfzellen-Schlitze (`40× CR2032`, `11× CR2025`, `21× LR44·AG13`, `10× AG10`), senkrecht von oben greifbar.
    2. **Mittel-Tribüne (`Z = 25,2 mm`):** `5× D · MONO`, `2× C`, `2× 9V` komplett vor dem Spender-Turm sichtbar angeordnet (kein blindes Hintergreifen).
    3. **Schulter-Auswurf (`Z = 57,6 mm`):** 3 Entnahme-Mulden mit Stoppkante und Daumen-Aussparung frei erreichbar über der Mittel-Tribüne.
    4. **Rückwand-Turm (`Z = 230,0 mm`):** `2× AA-Schlangen-Schächte` (`54× AA`) und `1× AAA-Schlangen-Schacht` (`36× AAA`) mit `45°`-Umlenkkeilen (`0 % Support` beim stehenden Druck), `17 mm` Fangrand oben, Füllstand-Sichtschlitzen und erhabener Front-Beschriftung.
* **Alternativ-Vorlage für niedrige Schubladen (`244,0 × 246,0 × 27,6 mm`, `185 Plätze`):** [`Seelenfunke_Batterie_Organizer_26cm_Regal_BambuA1.3mf`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/storage/app/public/manufaktur/3d-vorlagen/batterie-organizer/Seelenfunke_Batterie_Organizer_26cm_Regal_BambuA1.3mf) bleibt für flache Schubladen zusätzlich im Archiv erhalten.
* **Langfristige Batterie-Standardisierung:** Umstellung auf auslaufsichere **VARTA Industrial Pro** (plastikfreie 40er Pappbox) sowie **VARTA Lithium `CR2032` / `V13GA (LR44)`**.

---

## 4. Standardisierter Umsetzungs-Workflow („Auf Zuruf druckfertig“)

Für jedes gewünschte Teil aus Säule A oder Säule B gilt ab sofort folgender schlanker 3-Schritt-Ablauf:

1. **Kurz-Maßaufnahme (30 Sekunden):**
   * Bei Shop-Produkten/Kartons kommen die Maße direkt aus [`ProductSeeder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/ProductSeeder.php) bzw. [`ProductPackagingSeeder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/ProductPackagingSeeder.php).
   * Bei externen Tischgeräten (z. B. Modellname des Labeldruckers oder `Breite × Dicke` der Fernbedienungen) genügt ein einziges Stichwort oder kurzes Maß von Alina.
2. **Parametrische CAD-Generierung & Topologie-Prüfung:**
   * Erzeugung als 100 % wasserdichtes 2-Manifold-Mesh (`0` offene Kanten, `0` Non-Manifold-Kanten, `0 %` Stützstruktur) direkt als fertige `.3mf` für **Bambu Studio** (`256 × 256 mm` Bambu Lab A1).
   * Ablage unter `storage/app/public/produkte/laser-schablonen/` (für Laser-Klickplatten) bzw. `storage/app/public/manufaktur/3d-vorlagen/` (für Packtisch-Organizer & Falthilfen).
3. **Buchhalterische Erfassung nach Kauf:**
   * Sobald die Bestellbestätigung / Rechnung von Bambu Lab vorliegt, wird der Beleg gemäß Baustein 4 logisch benannt in `storage/app/buchhaltung/receipts/` abgelegt und als gewerbliche Anschaffung in [`AccountingSeeder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/AccountingSeeder.php) eingepflegt.
