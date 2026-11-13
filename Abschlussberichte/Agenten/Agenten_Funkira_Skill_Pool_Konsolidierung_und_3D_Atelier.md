# KI-Agenten: 2-Agenten-Konsolidierung (`Funkira` & `Funki`), Dynamische Skill-Pool-Engine & Luxus-3D-Atelier (inkl. Kollegen-Gruppenfoto Easter Egg)

**Datum:** 13.11.2026  
**Abteilung:** Agenten / System / Funkenwerk OS Linux  
**Status:** Produktiv ausgerollt (`mein_php_server` + `funkenwerk-minipc` `192.168.188.151`)

---

## 1. Architektur-Entscheidung: 2-Agenten-Modell statt 12 Einzel-Agenten

Alle bisherigen 10 internen Fach-Subagenten (`Bestelli`, `Produkti`, `Marketi`, `Buchi`, `Systemi`, `Agenti`, `Dr. Funki`, `Globi`, `Einkaufi`, `Lasi`) wurden im [`AiAgentSeeder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/AiAgentSeeder.php) sauber in den Ruhestand versetzt (`is_active = false`, `is_in_chat = false`, `wake_word = null`), ohne historische Chat-Verläufe (`AiChatMemory`) oder Organigramm-Referenzen zu beschädigen.

Aktiv verbleiben exakt **zwei strikt getrennte KI-Identitäten**:

1. **`Funkira` (Interne Super-Intelligenz & Geschäftsführung / Manufaktur-Leitung):**
   - Einzige aktive interne Ansprechpartnerin im Admin-Backend (`/admin/ai-workspace`, globales `AiWidget`) sowie live per Sprache und 3D-Körper auf dem Werkstatt-Mini-PC (`funkenwerk-minipc`).
   - [`AiAgentRouter.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/AiAgentRouter.php) routet interne Anfragen in `0 ms` ohne zusätzlichen LLM-HTTP-Roundtrip direkt auf `Funkira`.
2. **`Funki` (Externer Shop-Support hinter Panzerglas / Zero-Trust Air-Gap):**
   - Betreut ausschließlich den öffentlichen Kunden-Support im Shop-Frontend (`AiFrontendSupportChat`).
   - Durch harte Isolation in [`GeminiAgent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/GeminiAgent.php), [`AiPromptService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/AiPromptService.php) und [`AIFunctionsRegistry.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/AIFunctionsRegistry.php) erhält `Funki` **niemals** interne System-Prompts, Finanzzahlen, 10-Jahres-Ziele, Workspace-Dokumente oder Admin-Werkzeuge, selbst dann nicht, wenn im selben Browser parallel eine Admin-Session eingeloggt ist. Erlaubt sind ausschließlich öffentliche `support_*`-Kundenwerkzeuge sowie `system_get_current_time`.

---

## 2. Dreistufige Dynamische Skill-Pool-Engine (`FunkiraSkillPoolService.php`)

Um Token-Kosten, Latenz und Tool-Auswahl-Präzision optimal zu halten, lädt `Funkira` nicht permanent alle `295` System-Tools gleichzeitig in den Prompt, sondern arbeitet über [`FunkiraSkillPoolService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/FunkiraSkillPoolService.php) in drei deterministischen Schritten rein im PHP-RAM:

1. **Start im schlanken Hauptfähigkeiten-Pool (Core-Pool):**
   - Enthält Navigation, Uhrzeit, Wissen, Tagesroutine, Aufgaben, Kalender, Brain, Master-Status sowie das Meta-Werkzeug `funkira_equip_skill_pool`.
2. **Vordistanz-Erkennung (`< 1 ms`, `0` Extra-Tokens) + Selbst-Ausrüstung zur Laufzeit:**
   - `detectPoolsForRequest()` erkennt anhand eindeutiger Fachsignale bereits vor dem ersten Modell-Aufruf passende Fähigkeits-Pools.
   - Zusätzlich kann `Funkira` jederzeit mitten im Denkprozess `funkira_equip_skill_pool(pools: [...])` für bis zu **9 Spezial-Pools** (`finance`, `orders`, `products`, `laser`, `marketing`, `support_admin`, `office_comms`, `health_legal`, `system_global`) aufrufen und erhält im nächsten Loop-Schritt sofort die exakten Werkzeuge inklusive der destillierten Fachregeln (SOPs) ihrer früheren Kollegen.
   - Dabei wurde auch [`AiSuppliersFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiSuppliersFuncs.php) (`supplier_*`) vollständig in [`AIFunctionsRegistry.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/AIFunctionsRegistry.php) registriert.
3. **Automatischer Snap-Back (`try / finally`):**
   - Sobald `GeminiAgent::ask()` beendet ist, setzt der `finally`-Block `$this->ephemeralEquippedPools = []` automatisch auf die schlanken Hauptfähigkeiten zurück (`0` Datenbank-Schreibzugriffe, `100 %` Request-Isolation).

---

## 3. Easter Egg: Gerahmtes 12er-Gruppenfoto im 3D-Atelier & Kollegen-Erinnerung

Als bleibende Erinnerung an die Gründungszeit von `Mein-Seelenfunke` wurde aus allen 12 Original-Charaktergrafiken (mit Kanten-Despill, proportionaler 2-Reihen-Aufstellung, Walnussholz-Tischreflexion und gebürsteter 24K-Goldplakette **`DAS GRÜNDER-TEAM · MEIN SEELENFUNKE`**) ein hochauflösendes Studio-Gruppenporträt erstellt:

- **Bilddateien:**
  - [`public/shop/ai/images/funkira_colleagues_group.jpg`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/public/shop/ai/images/funkira_colleagues_group.jpg)
  - [`funkenwerk-os-linux/wwwroot/assets/colleagues_group_photo.jpg`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/assets/colleagues_group_photo.jpg)
- **Platzierung im 3D-Atelier (`funkira3d.js`):**
  - Hängt links oben an der Rückwand von Funkiras 3D-Atelier in einem profilierten 24K-Goldrahmen mit elfenbeinfarbenem Passepartout und eigener warmer Messing-Bilderlampe samt gebackenem Lichtkegel.
- **Aktives Wissen & Zeigegeste (`point_photo`):**
  - Sowohl im Laravel-Backend ([`FunkiraSkillPoolService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/FunkiraSkillPoolService.php)) als auch live auf dem Werkstatt-Mini-PC ([`FunkiraLiveAndMcpEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs) & [`wwwroot/app.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/app.js)) kennt Funkira jeden einzelnen Kollegen auf dem Bild mit Position, Aussehen und früherer Aufgabe (Hintere Reihe v.l.n.r.: `Produkti`, `Buchi`, `Dr. Funki`, `Lasi`, `Marketi`, `Agenti`; Vordere Reihe v.l.n.r.: `Einkaufi`, `Globi`, `Funkira`, `Funki`, `Bestelli`, `Systemi`).
  - Bei Fragen zum Wandbild oder zu früheren Kollegen führt Funkira automatisch `point_photo` aus, dreht sich im 3D-Raum stolz zum Gemälde (inkl. sanftem Kamera-Schwenk) und zeigt mit dem Arm direkt auf das Gruppenfoto. Im autonomen 0-Token-Atelierleben betrachtet sie das Bild gelegentlich liebevoll (`admire_photo`).

---

## 4. Schickes & 100 % Lagfreies 3D-Atelier-Upgrade (`funkira3d.js`)

Für den Manufaktur Mini-PC (`Intel Pentium Silver N5030` + `UHD Graphics 605` auf `2560 × 1440` QHD) wurden Funkira und ihr 3D-Atelier mit lizenzfreien MIT-Geometrie-Werkzeugen ([`wwwroot/vendor/RoundedBoxGeometry.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/vendor/RoundedBoxGeometry.js), `LatheGeometry`, `ExtrudeGeometry`) optisch auf Manufaktur-Boutique-Niveau gehoben, ohne unsere Zero-Light-60-FPS-Architektur anzutasten:

- **Dual-Light Studio MatCaps + Bevel-Normalen:** Abgerundete `RoundedBoxGeometry`- und gedrechselte `LatheGeometry`-Kanten an Helm, Brustpanzer, Stiefeln, Werkbank und Lasergehäuse fangen die warmen 24K-Gold-Glanzlichter und kühlen Rim-Highlights organisch ein (`0` dynamische Echtzeit-Lichter, `0` PBR-Shader-Loops).
- **Gebackenes Fischgrät-Walnussparkett & Obsidian-Gold-Wandvertäfelung:** Prozedurale `1024 × 1024` Fischgrät-Parkett-Textur mit direkt eingebackenen weichen Kontaktschatten (Baked Ambient Occlusion) unter Werkbank, Teppich und Laserstation sowie edle dunkle Kassettenwände mit 24K-Goldleisten und Wandleuchten.
- **Rundbogen-Atelierfenster (Blick ins Gifhorner Grün) mit volumetrischem Sonnenstrahl, Schwarz-Gold-Medaillon-Teppich & detaillierter `xTool F2 Ultra UV` Station (inkl. Conveyor & `SafetyPro IF2 2.0`).**

---

## 5. Automatisierte Verifikation

- **Laravel Test-Suite (`mein_php_server`):** [`FunkiraSkillPoolAndFunkiIsolationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/FunkiraSkillPoolAndFunkiIsolationTest.php), `GeminiAgentTest`, `AiWorkspaceTest` & `AiFinanceFixedCostsTest` bestanden (`35 Tests`, `239 Assertions`, `0 Fehler`).
- **Funkenwerk OS Linux (`192.168.188.151`):** `/opt/funkenwerk-os/FunkenwerkOSLinux --self-test` mit **`12/12 TESTS BESTANDEN`**, alle Assets (`index.html`, `funkira3d.js`, `RoundedBoxGeometry.js`, `colleagues_group_photo.jpg`) liefern `HTTP 200`, System-Idle auf QHD bei `> 70 %` freier CPU-Reserve.
