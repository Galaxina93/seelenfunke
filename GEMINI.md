# Seelenfunke Project Rules & Path Standards

## 1. WSL & Windows Path Integrity (CRITICAL - BLACK SCREEN PREVENTION)

This project resides in WSL (`\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke`).
When operating from the Windows Antigravity IDE:

1. **Tool File Arguments (view_file, replace_file_content, write_to_file, etc.)**:
   - ✅ **ALWAYS USE WINDOWS UNC**: `\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\...`
   - ❌ **NEVER USE FORWARD-SLASH UNC**: `//wsl.localhost/...`
   - ❌ **NEVER PASS RELATIVE OR LINUX ROOT PATHS DIRECTLY TO WINDOWS TOOLS**: `/home/ubuntuxina/...`

2. **Clickable Links in Chat**:
   - ✅ `[Dateiname](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/...)`
   - ❌ `[Dateiname](file:////wsl.localhost/...)` (FATAL: crashes Antigravity UI)

3. **Bash / CLI Execution**:
   - When running Artisan, PHPUnit, Node or Docker in this project, run via WSL:
     `wsl -d Ubuntu bash -c "cd /home/ubuntuxina/meine-projekte/seelenfunke && ..."`

---

## 2. Verbindliche Prüfung & Pflege der `Abschlussberichte/`

1. **Vorab-Prüfung vor jeder Aufgabe**:
   - Vor jeder Code-, UI- oder Architektur-Änderung immer zuerst [`Abschlussberichte/README.md`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/README.md) sowie die thematisch passenden Berichte in `Abschlussberichte/<Abteilung>/` (`Agenten/`, `Android/`, `Bestellungen/`, `Buchhaltung/`, `Dashboard/`, `Leitung/`, `Marketing/`, `Produkte/`, `Support/`, `System/`) und `Abschlussberichte/Workflows/` prüfen.
   - Bei Produkterstellungen, 3D-/2D-Konfigurator-Arbeiten oder Aufräumarbeiten sind [`Workflow_KI_Automatisierte_Produkterstellung.md`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/Workflows/Workflow_KI_Automatisierte_Produkterstellung.md) und [`Workflow_Verzeichnis_Reinigung.md`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/Workflows/Workflow_Verzeichnis_Reinigung.md) verbindlich einzuhalten.
2. **Fortlaufende Dokumentation**:
   - Nach Abschluss relevanter Änderungen muss der entsprechende Fachbericht in `Abschlussberichte/<Abteilung>/` aktualisiert werden.

---

## 3. Verbindliche Architektur-, UI- & Code-Konventionen

1. **Theme-Farbensystem (`var(--theme-color)`) & Deep Dark / Clean Light UX**:
   - Im Backend/Admin-Bereich immer `var(--theme-color)` und `var(--theme-color-10)` bis `var(--theme-color-50)` nutzen.
   - Im Shop-Frontend und bei Marken-Visuals die Primärfarbe Gold (`#C5A059` / `primary`) verwenden (Rot nur bei kritischen Zuständen).
   - Alle Views müssen adaptiv für Light- & Dark-Mode gestaltet sein (`bg-white dark:bg-gray-900/80`, `border-gray-200 dark:border-gray-800`, `text-gray-900 dark:text-white`).
2. **Projektaufbau & Native Seeder-Pipeline**:
   - Strikte Einhaltung der Domain-Ordnerstruktur (`app/Livewire/Shop/<Domain>`, `app/Models/<Domain>`, `app/Services/<Domain>`, `resources/views/livewire/shop/<domain>`).
   - Keine losen Einmal-Skripte für Stammdaten oder Produkt-Assets: Alles fließt in die nativen Laravel-Seeder (`database/seeders/*Seeder.php`, inkl. `ProductSeeder` & `ProductAssetSeeder`) ein.
3. **Finanz- & Sicherheits-Standards**:
   - Geldbeträge immer als Cent-Integer (`int`) verarbeiten.
   - Strikte Guard- & Rollen-Isolation (`admin`, `employee`, `customer`; externe Partner `influencer` und `tax_advisor` isoliert via `AiAuthHelper` / `EnsureEmployeeRole`) sowie `#[Locked]` für sicherheitskritische Livewire-Properties.
