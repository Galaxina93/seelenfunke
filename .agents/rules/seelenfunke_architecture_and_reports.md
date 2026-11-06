# Seelenfunke Architecture, Theme, Clean-Design & Abschlussberichte Rule

1. **Always check `Abschlussberichte/` and `GEMINI.md` / `ai_map.md` first**:
   - Before implementing or modifying features, check `Abschlussberichte/README.md` and the corresponding report in `Abschlussberichte/<Abteilung>/` or `Abschlussberichte/Workflows/`.
   - Keep the corresponding report in `Abschlussberichte/<Abteilung>/` updated after architectural, security, or feature changes.
   - Run `php artisan ai:generate-map` whenever Models, Livewire components, Controllers, Services, or Seeders are added or renamed (auto-syncs `ai_map.md` and `GEMINI.md`).
2. **Enforce Theme, Clean-Design & UX Standards**:
   - **Shop-Frontend & Modals:** Always friendly Light-Mode (no dark boxes in frontend), noble matte Black-Gold (`#C5A059`), no artificial glow, no `bg-amber` boxes or purple checkmarks on product extras (use clean surface + golden `✓` + max 3 short benefits).
   - **Backend & Funkenwerk:** Always use `var(--theme-color)` (`-10`..`-50`) and 100% consistent Light & Dark mode (`bg-white dark:bg-gray-900/80`, `text-gray-900 dark:text-white`, white icons in Dark mode, eye icon on password fields).
   - **Zero Text-Overload:** Never put explanatory sub-sentences below switches, buttons, or headings (use `!` hover tooltip or modal instead). Hide descriptions/tags in main overview tables. Never use AI en-dashes (` – `) in frontend/SEO/marketing texts. Open images in a Lightbox slider (never new tab).
3. **Enforce Project Architecture & Native Seeder Pipeline**:
   - Follow the domain structure (`Accounting`, `Ai`, `Management`, `Marketing`, `Order`, `Product`, `Support`, `System`).
   - Integrate all accounting purchases/contracts directly into `database/seeders/AccountingSeeder.php` (in cents, exact dates, Business vs. Private) and all product data, templates, suppliers, packaging, and 3D/2D assets directly into `database/seeders/Product*Seeder.php` (`Workflow_KI_Automatisierte_Produkterstellung.md`), never as standalone one-off scripts.
4. **Enforce Gamification Clean-Design Workflow (`Abschlussberichte/Workflows/Workflow_Gamification_Clean_Design.md`)**:
   - **No unnecessary sentences:** Never add instructional banners, sub-headers, or status texts for things that are already visually obvious.
   - **No artificial/weird headings:** Use short, natural labels (`Inventar`, `Rucksack`, `KLASSE C/B/A/S`, `Profil`, `In die Schlacht`).
   - **No overloaded views:** Keep cards, headers, and modals minimal and progressive (details on hover/modal/expand, never stacked badge walls).
