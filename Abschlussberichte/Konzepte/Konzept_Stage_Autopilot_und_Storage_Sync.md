# Konzept & Bestandsanalyse (v2): Stage-Bugfix-Autopilot & Reiner Shop-Asset-Sync

**Datum:** 14.11.2026  
**System:** Mein Seelenfunke (`Lokal` -> `stage.mein-seelenfunke.de` via Git + Funkenwerk SFTP/SSH Zero-Secret Vault)  
**Status:** Vollständig implementiert, gehärtet & live verifiziert (`SystemShopAssetManifestCommand`, `./funki stage-deploy`, `FunkenwerkMcp` `44/44` Selbsttests inkl. Ed25519-Key-Only-Zwang, automatischer Container-Neustart für `worker-stage-3` [`a-iurgq8`] & `twilio-bridge` [`a-yeq7el`], `--stage-publish-apk` & `StageShopAssetManifestTest` grün)

---

## 1. Verbindliche Abgrenzung für `storage/app` (2,26 GB lokal -> 82,45 MB Shop-Whitelist)

Gemäß Inhaberin-Vorgabe bleiben Musik, Buchhaltungsdaten, interne Verwaltungsdokumente und Testdaten **ausnahmslos lokal** (weder Stage- noch Live-Transfer, auch kein optionales Sync-Flag):

1. **Erlaubte reine Shop- & Konfigurator-Stammdaten (`315 Dateien`, `82,45 MB` aktiv genutzt):**
   * Ausschließlich aktiv referenzierte Shop-Medien aus `ProductSeeder`, `ProductAssetSeeder`, `ProductTemplateSeeder`, `MarketingBlogSeeder` und `ProductConfiguratorService`:
     * `storage/app/public/produkte/*` (aktive Produktbilder, `.glb`-3D-Modelle, 2D-Overlays, Maßbilder, `product-templates`, `configurator/vectors`, `configurator/quotes`, `configurator/tutorial/configurator-tutorial-default.mp4`, `configurator-presets`, `addons`, `laser-anleitungen` ohne verwaiste Alt-Ordner wie `echtgold-seelen-herz`).
     * `storage/app/produkte/products-secure/KI_Agenten_Management_Paket.zip` (`2,27 MB`).
     * `storage/app/public/marketing/blog/*.webp` (6 aktive Blog-Beitragsbilder, `0,21 MB`).
     * `storage/app/public/shopverwaltung/images/projekt/about/gruender-profil.webp` (`9,7 KB`).
2. **Strikte Lokal-Sperre (100 % Lokal, niemals auf Stage oder Live):**
   * `leitung/music/*` (`346 Dateien`, `1,79 GB` Hintergrundmusik; `ManagementMusicSeeder` beendet sich auf Stage/Live automatisch sauber, wenn kein lokaler Katalog vorliegt).
   * `buchhaltung/*` (`231 Dateien`, `178,0 MB` Belege, Verträge, ELSTER-ERiC, Test-Rechnungen, Steuer-Exporte).
   * `leitung/*` & `public/leitung/*` (Kontakte, Aufgaben-Anhänge, CRM).
   * `agenten/*` & `public/support/*` (`200,3 MB` inkl. `Gesundheit/*`, `wiki/health/*`, `Kameraaufnahmen/*`, Ticket-Anhänge).
   * `bestellungen/*`, `private/*`, `system/*`, `firebase-service-account.json`, `.env*`.

---

## 2. Ziel-Architektur (Fokus auf sofortige Bugfix-Übertragung)

1. **Git-Branch-Trennung:** `main` für Lokal & Stage, separater geschützter `production`-Branch für den Live-Server (`mein-seelenfunke.de`).
2. **Primärer Bugfix-Autopilot (`./funki stage-deploy` & MCP-Preset `stage_deploy`):**
   * Für alle Code-, Logik-, Backend- und Mobile-UI-Fixes (inkl. per `npm run prod` gebauter `public/css/app.css` und `public/js/admin-bundle.js`).
   * Vierfache Sicherheit: Branch-Trennung (`main` vs. `production`), harte Pfadprüfung in `McpSecurityGuard.cs` auf `/home/p-g27wim/html/seelenfunke-stage` (`worker-stage-3` & `twilio-bridge`), `.env`-Prüfung (`APP_URL=https://stage.mein-seelenfunke.de`) sowie **harter Ed25519-Key-Only-Zwang** (das gespeicherte Tresor-Passwort wird für Stage-Deploys und SFTP-Asset-Syncs nicht einmal im Arbeitsspeicher entschlüsselt; Passwort-Fallback ist hart abgeschaltet).
   * 4-Stufen-Ablauf auf Stage: `[1/4]` SFTP-Shop-Asset-Delta-Sync -> `[2/4]` auf `a-lc6tkm`: `git pull --ff-only origin main`, `composer install --no-dev` (falls `composer.lock` geändert), `php artisan migrate --force`, `optimize:clear`, `system:brain:generate`, `./funki sync-worker` (kopiert PHP-Code nach `worker-stage-3` sowie `server-twilio.js` + `GEMINI_BRIDGE_SECRET` nach `twilio-bridge`), `system:logs-cleanup --audit --prune` & `./funki check` -> `[3/4]` direkter `mittnitectl job restart` im Websocket-Container `a-iurgq8` -> `[4/4]` direkter `mittnitectl job restart` im Node.js-Container `a-yeq7el` (einzeln ausführbar via `--stage-restart-workers`; Gesamtaufbau siehe [`Support_Twilio_Call_Dokumentation.md`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/Support/Support_Twilio_Call_Dokumentation.md)).
3. **Sekundärer Reiner Shop-Asset-Sync (`php artisan system:shop-asset-manifest` & MCP `stage_sync_shop_assets`):**
   * Doppelte Positivliste (in PHP und hart im C#-Code von `FunkenwerkMcp`) nur für die 4 öffentlichen Shop-Asset-Pfade; alle anderen Ordner (`music`, `buchhaltung`, `leitung`, `agenten`, `support`, `private`, `system`) sind technisch gesperrt.
   * Überträgt per SFTP-SHA-256-Delta nur neue oder geänderte Shop-/3D-Medien (`0 Byte` bei reinen Code-Bugfixes), sodass Stage auch nach jedem `./funki migrate fresh` alle Shop-Bilder und 3D-Modelle vollständig anzeigt.
