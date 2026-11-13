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

## 5. Automatisierte Verifikation & Live-Credentials-Härtung

- **Laravel Test-Suite & Web-Portal `Live-AI -> 3D` Härtung (`mein_php_server`):** [`FunkiraSkillPoolAndFunkiIsolationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Ai/FunkiraSkillPoolAndFunkiIsolationTest.php) (inkl. `FunkiraSkillPoolService::isFunkira()` und Endpunkt-Test für `GET /api/ai/live-credentials` in [`AIController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/AIController.php)), Korrektur von `getHolidayEventsDefinition()` in [`HolidayOfferAutopilotService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Marketing/HolidayOfferAutopilotService.php) sowie Bereinigung von [`ai-widget-part1.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/ai/ai-widget-part1.blade.php) bis [`ai-widget-part6.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/ai/ai-widget-part6.blade.php) (Vermeidung doppelter `toggleLiveMode()`-Aufrufe, Timer-Bereinigung beim schnellen Wiederöffnen der 3D-Ansicht, `pointer-events-none` auf `#css2d-container` für freie `OrbitControls`, `config('services.mapbox.token')` statt `env('MAPBOX_TOKEN')`, deduplizierte Vendor-Skripte und WebGL-Context-Recovery).
- **Funkenwerk OS Linux (`192.168.188.151`):** `/opt/funkenwerk-os/FunkenwerkOSLinux --self-test` mit **`17/17 TESTS BESTANDEN`**, verbundenem **2-Geschoss-3D-Haus** (`Erdgeschoss` + `3D-Treppe` + `Obergeschoss` mit `Seelenfunken Atelier`, goldenem Leuchtschild `"Mein-Seelenfunke"` und drei 3D-Glas-Trophäen) und natürlichem Emotions-/Lach-Ausklingen (`burstFade` + `calmFunkiraEmotionAfterTurn`) in [`wwwroot/funkira3d.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/funkira3d.js), **Multi-Personen Biometrie & Begleiter-Erkennung** (`alina_with_companion` + `ManagementContact`-Abgleich + `minipc_companion_memory` + `48-Float` Offline-Stimmvektor in `AES-256-GCM`, `0` gespeicherte Fotos/Audiodateien) in [`funkira_local_sense.py`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/funkira_local_sense.py) und [`FunkiraLiveAndMcpEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs) sowie angebundenem **Cross-Platform Error-Sentinel** (`system_get_error_sentinel`).

---

## 6. Twilio-Freier Whitelist-Telefon-Assistent (`0,00 €` Bluetooth-HFP-Anrufbeantworter & 3D-Smartphone)

Über alle 4 Schichten des Ökosystems wurde Funkira als intelligenter, zu `100 %` Twilio-freier Telefon-Assistent (`0,00 €` laufende Telefoniekosten) für explizit freigegebene Kontakte (`funkira_call_whitelist = true`, z. B. `Jan Steinhauer` / `Papa`) integriert:

1. **Portal-Whitelist & Zentrales Dashboard-To-Do ([`FunkiraPhoneConciergeService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/FunkiraPhoneConciergeService.php), [`ManagementContacts.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementContacts.php), [`FunkiraPhoneConciergeTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/FunkiraPhoneConciergeTest.php)):**
   - Schalter pro Kontakt in `/admin/contacts` (`📞`) und in der Funkenwerk Android-App (`Einstellungen -> Telefon-Assistent`). Unbekannte Rufnummern und nicht freigegebene Kontakte werden niemals angenommen.
   - Nach Gesprächsende legt der Dienst automatisch eine Aufgabe **`📞 Anruf von <Name>: <Zusammenfassung>`** in Alinas Haupt-To-Do-Liste auf `/admin/dashboard` an.
2. **Android `CallScreeningService` & Bluetooth-HFP-Brücke ([`FunkiraCallScreeningService.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/services/FunkiraCallScreeningService.kt), [`FunkiraPhoneConciergeService.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/services/FunkiraPhoneConciergeService.kt)):**
   - Wartet die konfigurierte Klingelzeit (`Sofort`, `8 Sek.`, `12 Sek.`, `18 Sek.`) ab; nimmt Alina selbst ab, greift Funkira zu `0 %` ein. Andernfalls nimmt `TelecomManager.acceptRingingCall()` das Gespräch an und routet das Mobilfunk-Audio lokal über Bluetooth HFP (`TYPE_BLUETOOTH_SCO`) an `Funkenwerk-MiniPC`, während `Teufel KB 43 CR 19` in WirePlumber fest auf `A2DP Stereo` verriegelt bleibt.
3. **Rechtskonforme Zero-PII-Telefon-Session & 3D-Smartphone-Pose ([`FunkiraLiveAndMcpEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs), [`wwwroot/funkira3d.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/funkira3d.js), [`wwwroot/app.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/app.js)):**
   - **EU AI Act Art. 50 & `§ 201 StGB` Konformität:** Funkira stellt sich im allerersten Satz transparent als digitale KI-Assistentin von Alina vor, speichert `0` Audio-Mitschnitte (`.wav`/`.mp3`) und hat während `mode=phone_concierge` ausschließlich Zugriff auf `minipc_phone_call_finish(summary, urgency, callback_requested)` (harte Sperre aller Shop-, Bestell-, Finanz-, Mail- und Dokumenten-Tools).
   - **3D-Smartphone in Funkiras rechter Hand (`this.rig.smartphone` + Pose `'talk_phone'`):** Sobald ein Whitelist-Anruf eingeht, sprintet Funkira (falls sie gerade im Haus Pause macht) die 3D-Treppe hinauf in ihr `Seelenfunken Atelier`, nimmt ein schwarz-goldenes 3D-Smartphone in die rechte Hand direkt ans rechte Ohr, führt das Gespräch und steckt das Smartphone nach der Verabschiedung sauber wieder weg. Ist Alina anschließend vor der Werkstattkamera anwesend, richtet Funkira ihr die Nachricht direkt persönlich aus.

---

## 9. Allgemeine 3D-Raumbewegung & Ganzkörper-Kinematik (`minipc_avatar_move`) sowie freie Sicht aufs Kollegen-Gruppenfoto

1. **Allgemeine logische Bewegungs- & Körper-Motorik ([`FunkiraLiveAndMcpEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs), [`funkira3d.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/funkira3d.js), [`app.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/app.js)):**
   - **MCP-Tool `minipc_avatar_move` (12. lokales Mini-PC-Tool, auch in der TikTok-Sandbox erlaubt, da 0 PII):** Übersetzt gesprochene oder getippte Raum- und Haltungsbefehle während des Live-Gesprächs direkt an `window.Funkira3D.executeMovement(opts)` (ergänzt durch einen negationssicheren Turn-End-Reflex `maybeTriggerReflexMoveFromUserTurn` in `app.js`).
   - **Kamerabasierte Relativ-Schritte:** Berechnet anhand der aktuellen 3D-Kamerabasisvektoren (`right` / `forward`) exakte Schritte aus Zuschauersicht (`step_aside`, `step_left`, `step_right`, `closer_to_camera`, `step_back`) inklusive AABB-Möbelkollisionsprüfung.
   - **Atelier-Ziele, 3D-Treppe & alle Räume beider Etagen (`EG` & `OG`):** Unterstützt gezieltes Hinlaufen zu `colleagues_photo`, `brand_sign`, `workbench`, `laser`, `atelier_window`, `atelier_center`, über die 3D-Treppe (`stairs_down`, `stairs_mid`, `stairs_up`) sowie per etagenübergreifendem Dijkstra-Routing in jeden Raum des Hauses (`kitchen`, `living_room`, `dining_room`, `office`, `bedroom`, `gym`, `bathroom_eg`, `utility_room`, `hallway`, `og_studio`, `og_bathroom`, `og_gallery`) bei automatischer Kamera-Nachführung und Blickausrichtung zur Kamera.
   - **Bodenverankerte 3D-Ganzkörper-Animationen (`activeBodyAction`):** Echte Gelenk-Kinematik ohne Boden-Clipping für `duck` (exakte Beinbeugung `hip -1.18 rad` / `knee +2.02 rad` bei `pelvis.y = 0.31m`, Stiefelsohlen exakt auf `y = 0.00m`), parabolisches `jump`, 360°-`spin`, 180°-`turn_around`, `bow`, `stretch`, `lean_closer`, `dance` und `stand_up`.
2. **Balkenfreie Sicht auf das 12-Kollegen-Gruppenfoto im Atelier ([`funkira3d.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/funkira3d.js)):**
   - Die freistehende Mittelstütze bei `(x = -0.08, z = 2.36)` (die beim Herausfahren der Kamera direkt vor der Linse stand) sowie der Wandpfosten `z = 6.74` wurden entfernt und der südliche Dachsparren hinter die Giebelwand (`z = 6.88`) versetzt.
   - Bei `point_photo` oder `target = 'colleagues_photo'` fährt die Kamera hindernisfrei nah vor das Gruppenfoto (`z = 3.45 -> 6.76`), während Funkira seitlich links neben dem Goldrahmen (`x = -0.18, z = 5.58`) steht und darauf zeigt.
