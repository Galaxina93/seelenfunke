# Seelenfunke Architecture, Theme & Abschlussberichte Rule

1. **Always check `Abschlussberichte/` first**:
   - Before implementing or modifying features, check `Abschlussberichte/README.md` and the corresponding report in `Abschlussberichte/<Abteilung>/` or `Abschlussberichte/Workflows/`.
   - Keep the corresponding report in `Abschlussberichte/<Abteilung>/` updated after architectural, security, or feature changes.
2. **Enforce Theme & UX Standards**:
   - Always use `var(--theme-color)` and `var(--theme-color-10)`..`var(--theme-color-50)` in backend/admin views, and primary Seelenfunke Gold (`#C5A059`) in brand/frontend visuals (red only for critical states).
   - Always support both Light and Dark mode (`bg-white dark:bg-gray-900/80`, `border-gray-200 dark:border-gray-800`, `text-gray-900 dark:text-white`).
3. **Enforce Project Architecture & Seeder Pipeline**:
   - Follow the domain structure (`Accounting`, `Ai`, `Management`, `Marketing`, `Order`, `Product`, `Support`, `System`).
   - Integrate all product data, templates, and 3D/2D asset generation directly into the native Laravel seeders (`database/seeders/*Seeder.php`), never as standalone one-off scripts.
4. **Enforce Gamification Clean-Design Workflow (`Abschlussberichte/Workflows/Workflow_Gamification_Clean_Design.md`)**:
   - **No unnecessary sentences:** Never add instructional banners, sub-headers, or status texts for things that are already visually obvious.
   - **No artificial/weird headings:** Use short, natural labels (`Inventar`, `Rucksack`, `KLASSE C/B/A/S`, `Profil`, `In die Schlacht`).
   - **No overloaded views:** Keep cards, headers, and modals minimal and progressive (details on hover/modal/expand, never stacked badge walls).
