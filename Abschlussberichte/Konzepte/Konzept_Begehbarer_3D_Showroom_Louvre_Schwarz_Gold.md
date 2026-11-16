# Konzept: Begehbarer 3D-Showroom im Louvre-Stil (Schlicht Schwarz-Gold & High-End Realismus)

**Datum:** 16.11.2026  
**Autor:** Falcone (Technischer Mitgründer & Chef-Architekt)  
**Auftrags-ID:** `ag_20261116_185534_b2629338` (Erweiterung zu `ag_20261116_185257_29e7042b` · Freigegeben durch `ag_20261116_185621_ccf578f9`)  
**Status:** ✅ Vollständig umgesetzt & per PHPUnit verifiziert (`ShopShowroomController`, `seelenfunke-louvre-showroom-3d.js`, `/shop` UI & Light-Mode Drawer, `Louvre3DShowroomTest`)

---

## 1. Leitidee & Unternehmerischer Mehrwert

Statt eines gewöhnlichen hellbeigen Ladenlokals betritt der Besucher auf `/shop` über den Button **`3D shoppen`** eine **museale High-End-Galerie im Stil des Pariser Louvre**, konsequent reduziert auf unsere Marken-DNA **Schlicht Schwarz-Gold (`#0A0C10` & `#C5A059`)** und kompromisslosen **physikalischen Material-Realismus**:

1. **Museale Wertigkeit für Unikate:** Ein K9-Kristall oder ein 14-Karat-vergoldetes Schmuckstück wirkt auf einer mattschwarzen Museums-Stele unter einer Louvre-Glaspyramide wie ein echtes Sammlerstück statt wie Massenware.
2. **`0 KB` Zusatzlast beim normalen `/shop`-Aufruf:** Der klassische Light-Mode-Shop bleibt unberührt pfeilschnell; Engine (`Three.js v0.182` + `MeshoptDecoder`) und Assets laden strikt On-Demand erst beim Klick auf **`3D shoppen`**.
3. **100 % Seelenfunke-Regelkonformität (Baustein 1 & 2):** Die begehbare 3D-Museumsarchitektur ist edel Schwarz-Gold ohne künstlichen KI-Glow/Lila; das aufklappende **Kauf- & Produkt-Drawer-Modal** bleibt strikt im **freundlichen Seelenfunke Light-Mode** (`bg-white`, `text-gray-900`, Lightbox-Slider, 3 kurze Vorteile mit goldenem `✓` in `#C5A059`, `0` Subtexte, `0` KI-Gedankenstriche).

---

## 2. Architektur & High-End-Realismus-Bausteine (`Three.js v0.182` PBR)

1. **Ikonische Louvre-Glaspyramiden-Halle (Schwarz-Gold):**
   * **Decke:** Freitragendes Louvre-Rautenfachwerk aus mattschwarzem Architekturstahl mit feinen gebürsteten `#C5A059` 24K-Gold-Knotenpunkten, klarem Museumsglas und sanftem Abend-/Tageslichteinfall.
   * **Boden:** Hochauflösender polierter **Nero-Marquina-Marmorboden** (`2048x2048` PBR Albedo + RoughnessMap + dezente Boden-Reflexion mit feinen weißen und `#C5A059` Goldadern).
   * **Wände & Kolonnaden:** Schlichte anthrazit-schwarze Museumsbögen mit minimalen `#C5A059` Messing-Zierlinien und dezentem `MEIN SEELENFUNKE` Stirnfries.
2. **Physikalisches Studio-Environment (`PMREMGenerator`) statt flachem Licht:**
   * Ein hochauflösendes HDR-ähnliches Museums-Licht-Environment versorgt alle `.glb`-Exponate mit realistischen Glanzkanten: echte Glas-Transmission (`ior: 1.52`, klare Kantenbrechung) beim `Seelen Kristall` und satter, matter bis seidenglänzender 24K-Gold-/Edelstahl-Schimmer beim Schmuck (komplett ohne Bloom-/Neon-Effekte).
3. **Museums-Stelen, Glas-Hauben & Baked Ambient Occlusion:**
   * Jedes aktive Produkt steht im realen Maßstabsverhältnis auf einem schlichten mattschwarzen Louvre-Monolith-Podest mit gebürsteter `#C5A059` Goldfase, weichem **Baked Contact-Shadow** auf dem Marmorboden, feiner Museums-Vitrinenscheibe und fokussiertem Museums-Spotlight von oben.
   * Produkte ohne eigenes `.glb` werden automatisch als edles Museums-Rahmenexponat (hochauflösendes Produktfoto auf schwarzem Samt-/Passepartout-Aufsteller mit Goldrahmen) präsentiert.
4. **Intuitive Begehbarkeit auf PC & Smartphone (60 FPS):**
   * **Eigener Funki-Charakter:** Eingeloggte Kunden erkunden den Louvre mit ihrem echten komprimierten 3D-Funki (`Lv. 1` bis `Lv. 10`), Gäste mit `Lv. 2`.
   * **Steuerung:** `WASD`/Pfeiltasten + Maus-Umschauen, **1-Klick Tap-to-Walk** direkt zu jeder Stele, mobiler Daumen-Stick sowie eine dezente Schnellwahl-Leiste (`‹ Vorheriges Exponat | Nächstes Exponat ›`).
5. **Heller Light-Mode Kauf-Drawer am Exponat:**
   * Klick auf ein Exponat (oder `Enter`/`E`) öffnet rechts den hellen Clean-Design Produkt-Drawer mit Bild-Karussell + Lightbox (inkl. Maßbild), Preis inkl. MwSt., Lieferzeit, exakt 3 goldenen `✓`-Vorteilen und Direkt-Button **`Jetzt personalisieren`** (`/produkt/{slug}`) bzw. **`In den Warenkorb`**.
