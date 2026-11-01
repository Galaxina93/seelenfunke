---
name: gamification-clean-design
description: >-
  Enforces the mandatory Clean-Design, minimal wording, and clutter-free UI workflow
  for all Seelenfunke Gamification views, modals, shop cards, inventory, hangar, and games.
  Activate this skill whenever creating or modifying any Gamification UI, Blade template, or feature.
---

# Seelenfunke Gamification Clean-Design Workflow

Whenever you create, edit, or review any Gamification interface (`game-games-component.blade.php`, `seelen-hangar-panel.blade.php`, `cockpit-ingame-shop-panel.blade.php`, `admin-gamification-support.blade.php`, or related partials), you **MUST** strictly follow this workflow.

Read the full SOP specification in [`Workflow_Gamification_Clean_Design.md`](../../Abschlussberichte/Workflows/Workflow_Gamification_Clean_Design.md) if you need detailed module specifications.

## 1. The 3 Iron Rules of Gamification UI

1. **No Unnecessary Sentences (Zero Redundancy):**
   - Never write text for things that are already visually obvious (e.g., never show *"🔓 Bereich freigeschaltet"*, *"🎯 Aktives Ziel"*, *"Klick auf belegten Slot = Direkt Ausrüsten"*, or duplicate unlock requirement boxes).
   - Delete explanatory paragraphs, sub-headlines, and instructional banners.
2. **No Weird or Inflated Headings:**
   - Use short, natural German terms:
     - ✅ `Inventar` (never *Funkis RPG-Rüstkammer*)
     - ✅ `Rucksack` (never *Visuelles Rucksack-Slot-Raster*)
     - ✅ `KLASSE C`, `KLASSE B`, `KLASSE A`, `KLASSE S` (never *KLASSE B - BRONZE • KRISTALLJÄGER-BEREICH*)
     - ✅ `Profil` (never *Visitenkarte & Trophäen inspizieren*)
     - ✅ `In die Schlacht` (for onboarding confirmation)
3. **No Overloaded Views (Progressive Disclosure):**
   - **Cockpit Center:** Only player name + 1 combined Title/Prestige button above/below Funki. No duplicate Inventory button in the center (Inventory lives in the Hangar).
   - **Hall of Legends Cards:** Only Rank `#1`, Avatar, Name, Title, Sparks `✨`, and 1 `Profil` button. Put emblems (ship, record, faction) inside the Profile modal as compact `Icon + Value` pills.
   - **Shop Cards:** Max 4 category tabs, uncropped `object-contain` artwork, and exactly 3 golden checkmark (`✓`) bullet points per product on a dark/clean surface.
   - **Locked Ship Classes:** Dim the entire class section with 1 centered Lock overlay. Show 1 concise sentence on hover explaining what is missing.
   - **Inventory & Tooltips:** Support Drag & Drop, Double-Click, and Right-Click sticky menu. Render item stats vertically (`untereinander`) with green/red `▲`/`▼` comparison arrows, a short description, and a lore story for Purple (`rare`+) items.
   - **Settings Modal (`⚙️`):** Keep keybindings collapsed behind an `Ändern` toggle, use a simple checkbox for `Echten Vornamen nutzen`, and default volume to `50%` (`0–100%`).

## 2. Pre-Completion Verification Steps

1. Audit your Blade changes and remove any helper sentence, subtitle, or badge that repeats visual state.
2. Verify both Light Mode and Dark Mode classes (`bg-white dark:bg-[#0d1017]`, `text-gray-900 dark:text-white`).
3. Run `wsl -d Ubuntu bash -c "cd /home/ubuntuxina/meine-projekte/seelenfunke && php artisan view:cache && DB_HOST=127.0.0.1 php artisan test tests/Feature/Gamification/SpielerCockpitCompleteTest.php"`.
