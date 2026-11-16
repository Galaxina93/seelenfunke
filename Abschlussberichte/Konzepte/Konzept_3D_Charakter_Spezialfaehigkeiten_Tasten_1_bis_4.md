# Konzept: 3D-Charakter-Spezialfähigkeiten auf Tasten 1 bis 4

> **Status:** Konzept erstellt · Wartet auf Go (Baustein 3)  
> **Kontext:** 3D-Showroom & Halle der Helden (`public/js/seelenfunke-louvre-showroom-3d.js`)  
> **Zielgruppe:** Shop-Besucher (Gäste) & eingeloggte Kunden (Funki-Level 1–10)  
> **Verfasser:** Falcone (Chef-Architekt & Mitgründer)

---

## 1. Unternehmerischer Mehrwert & Gamification-Fokus

Die Seelenfunke 3D-Welt bietet Kunden und Besuchern ein immersives Manufaktur-Erlebnis bei 60 FPS. Nach der Einführung des Doppelsprungs mit Salto und der Schlaganimation auf der Maustaste wünschen sich Spieler mehr dynamische Interaktionsmöglichkeiten beim Erkunden des Showrooms und der Halle der Helden.

Durch 4 klar definierte Spezialfähigkeiten auf den Tasten `1`, `2`, `3` und `4` (bzw. als Schnellwahl-Dock auf Mobile) wird:
1. **Die Verweildauer (Dwell-Time) im 3D-Shop messbar gesteigert.**
2. **Das Leveling-Gefühl der Kunden-Funkis (Level 1 bis 10) erlebbar gemacht.**
3. **Ein spielerisches Belohnungssystem ohne Clutter geschaffen (Clean-Design-Prinzip).**

---

## 2. Die 4 Spezialfähigkeiten im Detail

| Slot | Taste | Fähigkeit | Wirkungsdauer | Cooldown | Visueller Effekt (Three.js 60 FPS) |
|---|---|---|---|---|---|
| **1** | `1` | **Funken-Sprint** | 4,5 s | 10 s | +50 % Laufgeschwindigkeit; feiner goldener Partikelschweif an den Fersen (`#C5A059`). |
| **2** | `2` | **Titan-Sprung** | Sofort | 8 s | 2,5-fache Sprunghöhe (`vy = 12.5`); goldener Abstoß-Ring und sanfter Boden-Impakt bei Landung. |
| **3** | `3` | **Goldener Schutzschild** | 6,0 s | 15 s | Transluzente Gold-Sphäre um den Avatar; verhindert Verdrängung in vollen 12er-Lobbys. |
| **4** | `4` | **Radialer Funkenstoß** | Sofort | 20 s | Kreisförmige Stoßwelle (`3,5 m`), die Partikel und Bodenstaub radial nach außen wirbelt. |

---

## 3. UI/UX Clean-Design & Steuerung (Baustein 1 & 2)

- **Desktop (Tastatur & Maus):**
  - Direkte Aktivierung über Zifferntasten `1`, `2`, `3`, `4`.
  - Schlankes Schnellwahl-Dock am unteren Bildschirmrand mittig (`bottom-6`).
  - 4 kompakte Slots (`44 × 44 px`), abgerundete Ecken (`rounded-xl`), Obsidian-Hintergrund (`bg-[#0B0E14]/85`) mit dezentem Goldrand (`border-[#C5A059]/40`).
  - Cooldown-Anzeige als ablaufender, abgedunkelter Kreissektor (Radial-Wipe) mit kleiner Restsekunden-Zahl (`text-[10px] text-[#C5A059]`).
  - Kein Text-Overload: Reines Piktogramm-Design (Blitz, Pfeil nach oben, Schild, Nova-Funke). Erklärung ausschließlich im `!`-Hover-Tooltip.
- **Mobile & Touch:**
  - Identisches Schnellwahl-Dock oberhalb der unteren Steuerungsleiste (`bottom-20`), optimierte Tap-Ziele (`44 px`) mit taktilem Druck-Feedback.

---

## 4. Technische Architektur & 60-FPS-Garantie

1. **Zero Garbage Collection Overhead:**
   - Geometrien (Sphäre, Ringe, Partikel-Diamanten) stammen aus dem existierenden `_ensureAvatarSharedPool()`.
   - Keine Erzeugung von Objekten im Animations-Frame; Wiederverwendung des bestehenden `this.weaponBursts`-Pools.
2. **Multiplayer-Synchronisation (12er-Räume):**
   - Fähigkeiten werden als schlankes Byte-Event im Lobby-Sync übermittelt:  
     `action: { type: 'ability', slot: 1..4, seq: n }`
   - Andere Clients rendern die Effekte nur bei FPS >= 40 (Focus-Protection), um schwächere Mobilgeräte nicht zu belasten.
3. **Gast- vs. Kunden-Balance:**
   - Gäste können die Fähigkeiten 1 und 2 (Sprint und Sprung) frei nutzen.
   - Fähigkeiten 3 und 4 (Schild und Funkenstoß) schalten sich ab Kunden-Funki-Level 3 und 5 frei, was einen sanften Anreiz zur Shop-Registrierung setzt.

---

## 5. Nächste Schritte nach Erteilung des „Go“

1. Integration der 4 Fähigkeiten in `seelenfunke-louvre-showroom-3d.js` (`triggerAbility(slot)`).
2. Ergänzung des 4-Slot-Action-Docks in `product-frontend-filter-area.blade.php`.
3. Test-Suite in `Louvre3DShowroomTest.php` erweitern und automatisierte Verifikation durchführen.
