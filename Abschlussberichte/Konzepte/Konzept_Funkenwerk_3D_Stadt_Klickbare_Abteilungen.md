# Konzept & Umsetzungsbericht: Klickbare 3D-Stadt-Abteilungen in Funkenwerk OS (Mini-PC & Web)

**Datum:** 16.11.2026  
**Status:** ✅ 100 % Grün umgesetzt & live auf Mini-PC (192.168.188.151) ausgerollt  
**Systeme:** `FunkenwerkOS.Linux` (`192.168.188.151`), [`funkenwerk_city_3d.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/funkenwerk_city_3d.js), [`index.html`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/index.html), [`styles.css`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/styles.css), [`app.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/app.js), [`Program.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/Program.cs), [`FunkiraLiveAndMcpEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs)  
**Verfasser:** Falcone (Technischer Mitgründer & Chef-Architekt) · **Qualitäts-Audit:** Globi

---

## 1. Ausgangslage & Unternehmerischer Mehrwert

### Architektur der 7 Abteilungs-Piers
In der 60-FPS Stargate-Atlantis Wasserstadt `Funkenwerk` ([`funkenwerk_city_3d.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/funkenwerk_city_3d.js)) bilden die 7 sternförmigen Atlantis-Piers rund um den 328 m hohen `MEIN SEELENFUNKE`-Hauptturm die Fachabteilungen des Unternehmens ab:
* `support` (Support, Cyan `#06B6D4`)
* `products` (Produkte, Blau `#3B82F6`)
* `marketing` (Marketing, Lila `#A855F7`)
* `orders` (Bestellungen, Gold/Bernstein `#F59E0B`)
* `accounting` (Buchhaltung, Smaragdgrün `#10B981`)
* `agents` (Agenten, Indigo `#6366F1`)
* `system` (System, Signalrot `#EF4444`)

Neben dem Penthouse-Thronsaal an der Turmspitze (`flyToThroneRoom`) und dem Pantheon `🏛️ Halle der Helden` (`flyToHallOfHeroes`) sind nun sämtliche 7 Abteilungs-Piers voll interaktiv per Klick, Touch, HUD-Menü, Tastatur (`1`..`7`), API/MCP und Sprachbefehl erreichbar.

### Unternehmerischer Mehrwert für Alina
1. **Lebendiges Firmen-Cockpit statt Menü-Klickerei:** Alina sieht mit einem Blick den Zustand ihres gesamten Unternehmens. Die 3D-Stadt fungiert als echter operativer Steuerstand.
2. **0 Sekunden Verzögerung in den Manufaktur-Fokus:** Ein Klick auf den goldenen Bestellungs-Pier gleitet direkt zur Zitadelle und startet mit einem Klick den Laser-Leitstand (`◎ Fokus starten`).
3. **Echtzeit-Transparenz ohne Text-Overload:** Jeder Pier zeigt im Clean-Design exakt 3 Live-KPIs aus `/api/pulse` ohne erklärende Subtexte, Info-Banner oder überladene Tabellen.
4. **Volle Plattform-Harmonie:** Steuerung nahtlos per Maus, Touch, Tastatur-Shortcuts (`1` bis `7`), Funkira-Sprachbefehl (*„Zeige mir die Abteilung Bestellungen“*) oder REST-API/MCP.

---

## 2. Reale Technische Implementierung

### A. 60-FPS Raycasting, Hit-Proxies & Matte Akzent-Beacons ([`funkenwerk_city_3d.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/funkenwerk_city_3d.js))
* **7 Unsichtbare Hit-Proxies:** Auf jedem Pier sitzt ein präziser Raycast-Quader (`THREE.BoxGeometry(68, 52, 290)` auf `(0, 26, 360)`, `visible: false`), registriert in `this.departmentClickTargets` mit `userData` (`isDepartment: true`, `deptKey`, `deptIndex`, `name`, `icon`, `hex`).
* **Matte Ring-Beacons ohne künstlichen KI-Glow (Baustein 2):** Jeder Pier besitzt zwei dezente, matte `MeshBasicMaterial`-Ring-Beacons (`this.departmentBeacons[dept.key]`: `podiumBeacon` mit `RingGeometry(24.2, 26.8, 32)` bei `z = 362` und oktagonales `padBeacon` mit `RingGeometry(17.2, 20.4, 8)` am Landedeck `z = 540`, `0` Bloom-/PostProcessing-Shader). Im Ruhezustand liegt die Deckkraft bei `0.26`; bei Hover oder aktivem Abteilungs-Fokus pulsieren die Ringe sanft (`opacity: 0.52..0.82`).
* **Hover-Reaktivität (`#fw-city-hover-chip`):** Bei `pointermove` ohne Drag wechselt der Cursor auf `pointer` und positioniert den kompakten Matt-Obsidian Hover-Chip (`#fw-city-hover-dot` in Abteilungsfarbe + Icon & Abteilungsname) verzögerungsfrei per `translate3d`.
* **Strikte Zero-Load-Disziplin:** Beim Schließen (`close()`) stoppt `cancelAnimationFrame(this.rafId)` die Render-Schleife sofort (`0 %` CPU/GPU-Last im Hintergrund); während geöffneter 3D-Stadt pausiert `syncAtelierVideoPlayback()` zudem das Hintergrundvideo für konstante 60 FPS auf der Intel UHD 605.

### B. Filmischer Kamera-Tiefflug (`flyToDepartment` / `glide_department`)
* **Quadratische Bezier-Spline-Fahrt (`duration: 2.2s`, `easeInOutCubic`):**
  * Zielpunkt (`destTarget`): Zitadelle des gewählten Piers bei `(0, 18.0, 362)` in lokaler Pier-Rotation `rotY`.
  * Kameraposition (`destPos`): Erhöhter 3/4-Lagunen-Winkel bei `(54, 40.0, 248)` mit freiem Blick über die Pier-Skyline und den 328 m hohen `MEIN SEELENFUNKE`-Hauptturm.
  * Kollisionsfreier Bogen über das Wasser (`midHoriz >= 240`, `mid.y + 28`) sowie definierte OrbitControls-Grenzen im Modus `this.viewMode = 'department'` (`minRadius: 42.0`, `maxRadius: 920.0`, `minY: 6.2`).
  * Feuert das Custom-Event `fw-city-department-change` zur Synchronisation von HUD-Leiste und Department-Drawer.

### C. Minimalistisches Schwarz-Gold Department-HUD (Clean Design Baustein 1 & 2)
Beim Auswählen eines Piers öffnet sich rechts der schlanke Drawer `#fw-city-department-drawer` ([`index.html`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/index.html), [`styles.css`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/styles.css), [`app.js`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/wwwroot/app.js)) in Matt-Obsidian (`#0B0E14`), 24K-Gold-Rahmen (`rgba(197, 160, 89, 0.32)`), `3px` Top-Akzentlinie in `var(--dept-accent)` und garantiert **ohne erklärende Subtexte oder Info-Banner** (`sanitizeCleanCityText()` bereinigt zudem etwaige Server-Strings zur Laufzeit):

1. **Kopfzeile:** Versalien-Titel mit Icon (`#fw-dept-title`), `LIVE`-Badge in Abteilungsfarbe (`#fw-dept-badge`) und Schließen-Button `✕` (`#btn-fw-dept-close`, gleitet zurück zur Stadtübersicht).
2. **Exakt 3 Live-KPI-Pills (`#fw-dept-kpi-1..3`, gespeist aus `/api/pulse`) & 2 Direkt-Aktionen (`CITY_DEPT_CONFIGS`):**
   * **`orders` (`📦 BESTELLUNGEN`, `#F59E0B`):** `Offen` | `Heute` | `Warenkörbe` · Primär: `◎ Fokus starten` (`switchWorkspace('gewerbe')` + `toggleFocusMode(true)`) · Portal: `/admin/orders`
   * **`products` (`🎨 PRODUKTE`, `#3B82F6`):** `Shop-Stufe` | `Saison` | `Woche` · Primär: `📁 Laser-Dateien` (`launchApp('files')`) · Portal: `/admin/products`
   * **`accounting` (`💶 BUCHHALTUNG`, `#10B981`):** `Monat` | `Belege` | `FYRST` · Primär: `🔐 Tresor öffnen` (`launchApp('tresor')`) · Portal: `/admin/financial-analytics`
   * **`marketing` (`📣 MARKETING`, `#A855F7`):** `Anlass` | `TikTok Live` | `Rosen` · Primär: `🎥 TikTok Live` (`#btn-funkira-tiktok`) · Portal: `/admin/marketing/analytics`
   * **`support` (`🎧 SUPPORT`, `#06B6D4`):** `Portal` | `Telefon-AB` | `Latenz` · Primär: `📞 Telefonie` (`#btn-funkira-phone`) · Portal: `/admin/support-tickets`
   * **`agents` (`🤖 AGENTEN`, `#6366F1`):** `Funkira` | `Falcone` | `Vertex AI` · Primär: `✦ Funkira Atelier` (`openFunkiraStage()`) · Portal: `/admin/organigramm`
   * **`system` (`⚙️ SYSTEM`, `#EF4444`):** `CPU / Temp` | `Sentinel` | `Uptime` · Primär: `🧠 Kuppelhalle` (`#btn-funkira-cellar`) · Portal: `/admin/settings`
3. **Sekundär-Aktion (`#btn-fw-dept-portal`, Matt-Anthrazit `#161A24`):** `🌐 Im Webportal öffnen` übergibt die passende `/admin/*`-Zielroute umgebungsbewusst (`stage.mein-seelenfunke.de` bzw. `mein-seelenfunke.de`) an `POST /api/browser/open`.

### D. HUD-Dropdown, Tastatur-Shortcuts, Sprachsteuerung & Security (Baustein 8)
* **HUD-Menü (`🏢 Abteilungen ▾`):** `#btn-fw-city-departments` öffnet `#fw-city-dept-menu` mit allen 7 Abteilungen, Farbpunkt und Shortcut-Badge `<kbd>1</kbd>` bis `<kbd>7</kbd>` und zeigt im aktiven Modus den aktuellen Abteilungsnamen direkt in der HUD-Leiste.
* **Tastatur & Wasser-Rückflug:** Tasten `1` bis `7` (`CITY_DEPT_SHORTCUT_ORDER`: `orders`, `products`, `accounting`, `marketing`, `support`, `agents`, `system`) fliegen sofort zur jeweiligen Abteilung. `Esc` oder ein Klick ins freie Wasser im Modus `department` gleitet sanft zurück in die Stadtansicht (`flyToCityOverview(false)`); ein weiteres `Esc` schließt die 3D-Stadt komplett.
* **API & lokales MCP-Routing:** `POST /api/funkenwerk-city/control` ([`Program.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/Program.cs)) sowie `ExecuteLocalMiniPcToolAsync("minipc_ui_control", ...)` ([`FunkiraLiveAndMcpEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs)) lösen Synonyme über `NormalizeFunkenwerkDepartmentKey` auf (`department:<key>` / `open_department:<key>`). Der Endpunkt ist strikt durch Loopback-/Host-Prüfung (`403`) und Constant-Time-Session-Token (`fw_kiosk_session` / `X-Fw-Session`, `401` ohne Auth) geschützt.
* **Deutsche Sprachsteuerung mit Privacy-/Visitor-Sperre:** `TryMatchSpokenFunkenwerkCityCommand` erkennt natürliche Sätze für alle 7 Abteilungen (z. B. *„Zeige mir die Abteilung Bestellungen“*, *„Flieg zur Buchhaltung“*, *„Abteilung System öffnen“*) ohne Kollision mit Werkstatt-Fokus-Befehlen. In `PollWakeAsync` blockiert `privacyBlocked` (`TikTokLiveMode || EffectivePrivacyIdentity == "visitor"`) gesprochene Navigationsbefehle bei Besuchern oder laufendem TikTok-Stream vollständig.

---

## 3. Qualitäts-Audit & Testnachweis (`47 / 47` Grün)

* **10-Bausteine-Audit (Globi):** `0` erklärende Subtexte/Info-Banner im Department-HUD und HUD-Menü, `0` KI-Gedankenstriche in allen 6 Code-Dateien und Berichten, Matt-Obsidian `#0B0E14` & 24K-Gold `#C5A059` ohne künstliche Bloom-/Glow-Shader, `0 %` CPU/GPU-Last im geschlossenen Zustand, `401`-Session-Schutz & Visitor-Privacy-Sperre verifiziert.
* **Automatisierter Mini-PC Selbsttest (`--self-test`):** `47 / 47` Prüfungen in [`Program.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os-linux/Program.cs) bestanden (Check `#47` verifiziert `POST /api/funkenwerk-city/control`, `/api/pulse` System-Telemetrie, alle deutschen Abteilungs-Sprachbefehle inkl. Fokus-Regressionsschutz, `minipc_ui_control` MCP-Routing, alle 7 `/admin/*`-Portalrouten sowie `0` KI-Gedankenstriche) und live auf `192.168.188.151` ausgerollt.
