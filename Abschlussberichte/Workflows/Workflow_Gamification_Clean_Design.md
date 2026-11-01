# Verbindlicher KI-Workflow: Gamification Clean-Design & UI-Minimalismus

**Status:** Aktiv / Verbindliche Standard-Operating-Procedure (SOP)  
**Projekt:** Mein-Seelenfunke (`/home/ubuntuxina/meine-projekte/seelenfunke`)  
**Geltungsbereich:** Alle Gamification-Module (`Spieler-Cockpit`, `Hangar`, `Inventar & Rucksack`, `Ingame-Shop`, `Halle der Legenden`, `3D-Sternenkarte`, `Funkenflug-Express`, `Kristall-Kollaps 3D`, `Einstellungen`, `Admin-Spielverwaltung`)  
**Erstelldatum:** 28.10.2026

---

## 1. Grundsatz (Das Clean-Design-Gesetz)

Jede Ansicht im Gamification-System folgt drei eisernen Regeln:
1. **Keine unnötigen Sätze:** Was visuell bereits sichtbar oder selbsterklärend ist, wird niemals zusätzlich als Satz, Hinweis-Box oder Untertitel hingeschrieben.
2. **Keine komischen oder künstlich aufgeblähten Überschriften:** Überschriften sind kurz, klar und natürlich (`Inventar`, `Rucksack`, `KLASSE B`, `Profil`, `Shop`).
3. **Keine überladenen Ansichten:** Jede Kachel und jedes Fenster zeigt nur die im jeweiligen Moment relevante Kerninformation. Detailtiefe entsteht durch gezielte Interaktion (Hover, Modal, Aufklappen), niemals durch Textwüsten auf der Hauptbühne.

---

## 2. Verbindliche Namens- & Überschriften-Konventionen (Wording-Matrix)

Bei allen Änderungen oder Erweiterungen im Gamification-Bereich sind folgende Bezeichnungen zwingend zu verwenden:

| Bereich / Element | ✅ Richtig (Clean & Direkt) | ❌ Verboten (Aufgebläht / Künstlich) |
| :--- | :--- | :--- |
| Charakter-Ausrüstung | **Inventar** | *Funkis RPG-Rüstkammer & Gegenstandsstufen* |
| Inventar-Plätze | **Rucksack** | *🎒 Visuelles Rucksack-Slot-Raster (Plätze #1 – #25)* |
| Raumschiff-Klassen | **KLASSE C**, **KLASSE B**, **KLASSE A**, **KLASSE S**, **KLASSE SS** | *KLASSE C • STANDARD*, *KLASSE B - BRONZE • KRISTALLJÄGER-BEREICH* |
| Ranglisten-Detailbutton | **Profil** | *🔍 Visitenkarte & Trophäen inspizieren* |
| Onboarding-Startbutton | **In die Schlacht** | *Eid schwören & Cockpit betreten* |
| Einstellungen Tasten | **Ändern** (ausklappbar) | Permanent ausgeklappte 10-Tasten-Tabellen |
| Echter Vorname | Schlichte **Checkbox** (*Echten Vornamen nutzen*) | Doppelte Transformations-Buttons mit langem Fließtext |

---

## 3. Verbot redundanter UI-Elemente (Was NIEMALS eingebaut werden darf)

1. **Keine doppelten Status-Texte für visuell Offensichtliches:**
   - ❌ Keine Badges wie *„🔓 Bereich freigeschaltet (1/3 Schiffe)“* in offenen Klassen.
   - ❌ Keine Zusatzboxen *„Freischaltungs-Voraussetzung…“* rechts oben in Klassen-Kacheln.
   - ❌ Keine gesperrten Hinweissätze unter jedem einzelnen Schiff einer ohnehin gesperrten Klasse.
   - ❌ Keine Box *„🎯 Aktives Ziel“* oder grün blinkende Status-Punkte neben der 3D-Sternenkarte (der ausgewählte Planet leuchtet bereits direkt auf der Karte).
   - ❌ Keine Bedienungsanleitungen wie *„Klick auf belegten Slot = Direkt Ausrüsten“* über dem Rucksack.
2. **Keine doppelten Navigations-Buttons im Cockpit-Zentrum:**
   - Um den zentralen Funki-Charakter stehen ausschließlich der **Spielername** und **1 kombinierter Titel-Button** (öffnet das Modal für *Titel*, *Prestige* & *Legenden-Pfad*).
   - Darunter liegen maximal die beiden Schnellzugriffe **Halle der Legenden** und **Shop**.
   - Das **Inventar** liegt ausschließlich im **Hangar** (Reiter *Inventar & Rucksack*) und wird nicht doppelt im Cockpit-Zentrum verlinkt.

---

## 4. Layout- & Komponenten-Regeln pro Modul

### A. Gesperrte Klassen & Bereiche (Hangar)
- Eine noch nicht freigeschaltete Schiffsklasse (`KLASSE B`, `KLASSE A`, `KLASSE S`) wird als **gesamte Sektion abgedunkelt** und mittig mit **einem zentralen Schloss-Overlay** verriegelt.
- Beim Hovern über das Schloss erscheint **exakt 1 prägnanter Satz**, welche Bedingung noch fehlt (z. B. *„Es fehlen noch 2 C-Klasse Schiffe im Hangar, um Klasse B freizuschalten.“*).
- Sobald die Bedingung erfüllt ist, wird die Klasse mit einer hochwertigen Freischalt-Animation entfesselt.

### B. Inventar & Rucksack (RPG-Ausrüstung)
- **Interaktion ohne Text-Erklärung:**
  - **Drag & Drop:** Items frei zwischen Rucksack-Plätzen und den 9 Ausrüstungs-Slots verschieben.
  - **Doppelklick:** Item sofort anlegen bzw. angelegtes Item wieder in den Rucksack ablegen.
  - **Rechtsklick:** Öffnet ein **Sticky-Kontextmenü** direkt am Item (*Anlegen/Ablegen*, *Verkaufen für ✨*, *Schließen*).
- **Schnellverkauf-Buttons:** Klein und dezent rechts oben im Header der Sektion **Rucksack** (*Graue verkaufen*, *Blaue verkaufen*, etc.).
- **Hover-Tooltip für Items:**
  - Kein Fließtext-Satz für Werte! Alle Attribute stehen **sauber untereinander** als vertikale Liste.
  - Neben jedem Wert steht direkt der Vergleich zum aktuell angelegten Item (`▲ +X` in Grün bei Verbesserung, `▼ -X` in Rot bei Verschlechterung).
  - Darunter 1 kurze Beschreibung des Gegenstands und ab Seltenheit **Lila (`rare`, `unique`, `legendary`)** eine kompakte Herkunfts-Story (*Lore*).
- **Grafik-Standard:** Jedes Item nutzt ein hochauflösendes **8K-RPG-Artwork** (`image_url`), niemals billige Standard-Icons.

### C. Cockpit Ingame-Shop
- **Maximal 4 klare Kategorien** in einer Zeile (keine Badge-Wüste im Header).
- **Produktbilder:** Immer vollständig sichtbar (`object-contain` auf edlem dunklem Grund, niemals oben/unten abgeschnitten).
- **Produkt-Details:** Direkt in der Kachel über **exakt 3 gleichbleibende Stichpunkte mit goldenem Haken (`✓`)** auf dunklem/edlem Hintergrund (niemals graue Textkästen).

### D. Halle der Legenden & Spieler-Profil
- **Ranglisten-Kacheln:** Zeigen ausschließlich Platzierung (`#1`), Avatar, Spielername, aktiven Titel, Funken-Stand (`✨`) und **1 Button „Profil“**.
- **Profil-Modal:** Zeigt alle Embleme (Volk, aktuelles Raumschiff, Funkenflug-Rekord, Level, Eroberungspunkte) kompakt als **Icon + Wert** ohne lange Text-Labels.

### E. Völker-Wahl & Einstellungen
- **Völker-Wahl (Onboarding):** Nur die 2 großen Fraktions-Kacheln (*Sternenwächter* vs. *Chaosbringer*), das Namensfeld und mittig unten der Button **„In die Schlacht“** (wird erst aktiv, wenn Volk + Name gewählt sind).
- **Verbindungslinien im Cockpit:** Fließanimationen in **Gold matt** für *Sternenwächter* und **Schwarz / Dunkelrot matt** für *Chaosbringer*.
- **Einstellungen (Goldenes Zahnrad `⚙️`):**
  - Erreichbar im Cockpit neben `UI-Ton` sowie direkt aus dem laufenden Spiel (Pause-Menü).
  - Lautstärke startet bei `50 %` (Skala `0 % – 100 %`).
  - Tastenbelegung ist standardmäßig eingeklappt (*„Ändern“*-Button).
  - *„Echten Vornamen nutzen“* ist eine schlichte Checkbox.

---

## 5. Verbindliche Prüf-Checkliste vor jedem Commit (Self-Audit)

Bevor eine Änderung an einer Gamification-Blade-View oder Livewire-Komponente abgeschlossen wird, muss der Agent folgende 6 Punkte prüfen:
1. **Text-Diät geprüft?** Kann ein Satz, Untertitel oder Hinweis ersatzlos gestrichen werden, weil das UI es ohnehin visuell zeigt? Wenn ja: **Streichen!**
2. **Überschriften geprüft?** Sind alle Titel kurz, natürlich und frei von künstlichen Doppel-Begriffen?
3. **Kachel-Dichte geprüft?** Hat jede Karte maximal 1 klare Hauptaktion und keine gestapelten Badge-Reihen?
4. **Dark- & Light-Mode geprüft?** Funktionieren alle Kontraste in `light` (`bg-white`, `text-gray-900`) und `dark` (`dark:bg-[#0d1017]`, `dark:text-white`) ohne graue Fremdkörper?
5. **Bild-Formatierung geprüft?** Werden alle Shop- und Item-Grafiken unbeschnitten und hochauflösend dargestellt?
6. **Tests grün?** `php artisan view:cache && DB_HOST=127.0.0.1 php artisan test tests/Feature/Gamification/SpielerCockpitCompleteTest.php` erfolgreich ausgeführt.
