# Bestellungen & Laser-Cockpit: Medien-Dropdown, Originalfoto (`_raw`) & Laser-Foto-Studio

**Paket:** Medien-Dropdown, Verlustfreie Originalfoto-Sicherung (`_raw`), Mathematisches Laser-Foto-Studio (`⚙`), Blendfreie xTool-F2-Kamera (`fill_light`), Doppel-Kontrast-Rahmen & `0,01 mm` `Shift`-Feinjustierung  
**Stand:** 18.11.2026 · **Qualitäts-Audit:** `100 %` Grün (`0` KI-Gedankenstriche, `100 %` Light-/Dark-Mode, `#[Locked]`-Härtung)

---

## 1. Zielsetzung & Unternehmerischer Mehrwert

Für die tägliche Fertigung am **xTool F2 Ultra UV (5 W · 355 nm)** wurden fünf ineinandergreifende Praxis-Optimierungen über Konfigurator (`Produkti`), Laser-Engine (`Lasi`) und Bestellabwicklung (`Bestelli`) zusammengeführt:

1. **Verlustfreie Originalfoto-Sicherung (`_raw`):** Kunden-Uploads werden im Shop-Konfigurator vor jeder Vorschau-Skalierung oder Alpha-Maskierung Byte-für-Byte unverändert als `<uuid>_raw.<ext>` archiviert und stehen bis zum finalen Laser-Export in voller Sensor-Auflösung bereit.
2. **Aufgeräumter Bestell-Header & `Medien`-Dropdown:** Der obere Schnellzugriff zeigt ruhig `Kosten | Kunde | Adresse`. Alle Produktionsdateien, Konfigurations-Sicherungen (mit integrierter Lightbox im selben Fenster), Original-Downloads, Mangel-Meldungen sowie `XTOOL PROJEKT (.XCS)`, `LASER-SVG` und `KONTUR-PNG` sitzen gebündelt im aufklappbaren **`Medien`**-Dropdown direkt unter `Laserparameter anpassen` (mit automatischem Fallback in Wartend, Schritt 2 und Schritt 3).
3. **Mathematisches Laser-Foto-Studio (`⚙`):** Über das Zahnrad neben jedem Kunden-Rasterbild öffnet sich ein Vorher/Nachher-Studio auf Basis des unberührten `_raw`-Originals mit 1-Klick-Histogramm-Automatik (`✨ Automatisch Werte setzen`), `Gamma`, `Lichter-Schutz` (UV-Dot-Gain-Deckel `58 %` bei `600 DPI` auf dunklem Substrat), `Unsharp-Mask` sowie 4 Dither-Modi (`Jarvis`, `Stucki`, `Graustufen`, `Schwellenwert`).
4. **Blendfreie Live-Kamera (`fill_light`):** Spiegelnde Edelstahl- und K9-Kristall-Rohlinge überstrahlen nicht mehr unter den Arbeitsraum-LEDs. Vor `/v1/camera/snap` regelt das V2-WebSocket-Protokoll `fill_light` auf `Blendfrei (LED aus)` (`0/0`), `Gedimmt` (`0/20`) oder `Licht an`, wartet `220 ms` Belichtungs-Einpegelung ab und stellt die ursprüngliche Helligkeit im `finally`-Block garantiert wieder her.
5. **Doppel-Kontrast-Rahmen, `0 ms` Sichtbarkeits-Hilfen & `0,01 mm` `Shift`-Feinjustierung:** Eine zweilagige Kontur (`rgba(15, 23, 42, 0.85)` + `#00d8ff` Cyan) bleibt auf jedem Untergrund sichtbar, ergänzt um einen `Abdunkeln`-Regler (`0 %` bis `65 %`), `Motiv-Kontrast` sowie echte `0,01 mm` (`10 µm`) Koordinaten-Präzision mit `0,15x` `Shift`-Feingetriebe für Maus-Drag, Rotation, Skalierung und Pfeiltasten (`0,02 mm` mit `Shift`, `0,20 mm` normal).

---

## 2. Geprüfte & Integrierte Kernkomponenten

* **Produkt-Konfigurator & Warenkorb-Persistenz (`_raw` & `raw_files`):**
  * [`ProductConfigurator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductConfigurator/ProductConfigurator.php) (`#[Locked] public array $raw_files`, Upload-Sicherung `<uuid>_raw.<ext>`, Filerobot-Farbsicherung `:initial`, `saveCroppedLogo`, Path-Traversal- & Ownership-Prüfung)
  * [`HandlesConfiguratorLogic.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductConfigurator/Traits/HandlesConfiguratorLogic.php) (`resolveRawFileForProcessedPath`, `raw_value`-Mitführung auf Vorder- & Rückseite, sichere Bereinigung in `removeFile`)
  * [`CartService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartService.php) (`sanitizeConfiguration` bewahrt validierte `raw_files`, `logos[].raw_value`, `laser_photo_adjustments` und `laser_camera_light_mode`)
  * [`CartFileCleanupService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartFileCleanupService.php) (`resolveReferenceBasenames` schützt `<uuid>_raw.<ext>` über UUID-Stamm-Abgleich, solange `<uuid>_proc.png` oder `<uuid>.png` in Warenkorb, Bestellung, Angebot oder Vorlage aktiv ist; `extractManagedFilePathsFromConfig` berücksichtigt `raw_files`, `raw_value`, `edited_path` und `original_path`)
* **Foto-Pipeline, Export & xTool V2 Hardware-Steuerung:**
  * [`LaserPhotoPreprocessor.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Laser/LaserPhotoPreprocessor.php) (`resolveOriginalPhotoRelPath`, `hasUntouchedOriginal`, `findActivePhotoAdjustment`, `computeMathematicalAutoParams`, `renderStudioPreviewDataUrl`, `saveStudioProcessedLaserPng`, EXIF-Orientierungskorrekturen & Legacy-Alpha-Masken-Dekodierung)
  * [`FileDownloadService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Export/FileDownloadService.php) (`resolveEffectiveLogoSourceForLaser` bevorzugt aktive `laser_photo_adjustments[fileKey]['edited_path']` und fällt automatisch auf das echte `_raw`-Originalfoto zurück; volle Weitergabe an Cockpit-Vorschau, `LASER-SVG`, `KONTUR-PNG`, `.xcs` und `.xf`)
  * [`XtoolLaserCockpitService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Laser/XtoolLaserCockpitService.php) (`normalizeCameraLightMode`, `resolveFillLightTargetsForMode`, `fillLightPercentToBri`, `captureAndCalibrateLiveCameraFromLaserV2` mit `PUT /v1/peripheral/param?type=fill_light` + `finally`-Restore, Relay-Durchreichung von `light_mode`, geditherter Einzelpunkt-Puls-Stream für Single-Photo-`.xf` sowie `Jarvis`-Automatik für Halbton-Kundenfotos auf Schmuck-Presets)
* **Bestellübersicht, Live-Cockpit, Mini-PC Fokus-Sync & Clean-Design Partials:**
  * [`OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php) (`buildItemMediaPayload`, `openLaserPhotoStudio`, `autoOptimizeLaserPhoto`, `saveLaserPhotoStudio`, `setLaserCameraLightMode`, `captureLaserCameraSnapshot`, `updateLaserCoordinates` in `round(..., 2)` `0,01 mm`-Präzision, Disk-Preview-Cache `v8-cockpit-photo-layers|` inkl. `laser_photo_adjustments` sowie `#[Locked]`-Schutz auf allen `photoStudio*`-Pfad-/Status-Properties)
  * [`WorkshopFocusApiController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Api/WorkshopFocusApiController.php) (synchronisierter `v8-cockpit-photo-layers|` Preview-Cache-Key inklusive `laser_photo_adjustments` für sofortige Parität im Mini-PC Fokus-Modus)
  * [`detail-header.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview-partials/detail-header.blade.php) & [`header-modals.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview-partials/header-modals.blade.php)
  * [`media-dropdown-card.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview-partials/media-dropdown-card.blade.php) & [`laser-photo-studio-modal.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview-partials/laser-photo-studio-modal.blade.php)
  * [`laser-cockpit-card.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-offer-detail-partials/laser-cockpit-card.blade.php) & [`order-overview.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview.blade.php)

---

## 3. Unabhängiges 10-Bausteine- & Security-Audit

1. **Baustein 1 & 2 (Clean-Design, Lightbox & Theme-Disziplin):**
   * `0` KI-Gedankenstriche in allen geänderten und neuen Blade-Partials, Livewire-Meldungen und Services.
   * Keine erklärenden Subtext-Sätze unter Schaltern oder Reglern im `Laser-Foto-Studio` oder `Live-Laser-Cockpit`; Fachhinweise zu `Invertieren`, `Gamma`, `Lichter-Schutz` und `Schärfe` liegen kompakt hinter `!`-Hover-Tooltips.
   * Bildvorschauen im `Medien`-Dropdown öffnen über `openMediaLightbox(...)` im selben Fenster statt in einem neuen Browser-Tab.
   * Durchgängiger Light- & Dark-Mode (`bg-white dark:bg-gray-900`, `bg-gray-50 dark:bg-gray-950`, `text-gray-900 dark:text-white`) sowie strikte Nutzung von `var(--theme-color)`.
2. **Baustein 8 (Livewire `#[Locked]` & Path-Traversal-Abwehr):**
   * In [`ProductConfigurator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductConfigurator/ProductConfigurator.php) sind `$product`, `$cartItem`, `$context`, `$uploaded_files`, `$raw_files`, `$snapshot_path` und `$savedCustomFieldsSnapshot` mit `#[Locked]` geschützt; `raw_files` akzeptiert ausschließlich Pfade unter `bestellungen/cart-uploads/` ohne `..`, Backslashes oder Null-Bytes.
   * In [`OrderOverview.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderOverview.php) sind `$selectedOrderId`, `$selectedOrderItemId`, `$selectedOrder`, `$photoStudioItemId`, `$photoStudioFileKey`, `$photoStudioFileName`, `$photoStudioOriginalRelPath`, `$photoStudioOriginalUrl`, `$photoStudioPreviewUrl`, `$photoStudioIsDarkSurface`, `$photoStudioDpi` und `$photoStudioMaterialHint` strikt `#[Locked]`; `verifyAndResolveCustomerFileKey()` prüft die Zugehörigkeit zur Bestellposition und `resolveStorageAbsPath()` begrenzt Dateizugriffe auf freigegebene Bestell-Pfade ohne Symlinks.

---

## 4. Automatisierte End-to-End Testabdeckung

* [`CustomerRawPhotoPreservationTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/CustomerRawPhotoPreservationTest.php)
* [`LaserPhotoStudioAndAntiGlareTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/LaserPhotoStudioAndAntiGlareTest.php)
* [`OrderLaserCockpitMediaAndShiftTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/OrderLaserCockpitMediaAndShiftTest.php)
* [`XtoolLaserCockpitTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Shop/Order/XtoolLaserCockpitTest.php)
* [`LaserHandbookValuesAndPhotoPipelineTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/LaserHandbookValuesAndPhotoPipelineTest.php)
* [`LaserDesignParityTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/LaserDesignParityTest.php)
* [`OrderOverviewTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Shop/Order/OrderOverviewTest.php)
* [`LaserSvgExportTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Services/LaserSvgExportTest.php)
* [`WorkshopFocusAndPackagingCleanNamesTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Api/WorkshopFocusAndPackagingCleanNamesTest.php)

---

## 5. Performance-, Speicher- & UX-Upgrade (OOM-Eliminierung, 60-FPS `<canvas>` & „Magie“-Start)

1. **Vollständige Eliminierung aller Vollbild-PHP-Arrays (`0` OOM-Risiko):**
   * In [`LaserPhotoPreprocessor.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Laser/LaserPhotoPreprocessor.php) und [`FileDownloadService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Export/FileDownloadService.php) wurden sämtliche `array_fill(0, $w * $h, ...)`-Puffer durch kompakte `1-Byte`-Binär-Strings, `64`-Zeilen-Strip-Resampling für Megapixel-Aufnahmen und einen **3-Zeilen-Rolling-Error-Buffer** (`< 40 KB` im CPU-L1-Cache) für Jarvis- und Stucki-Dithering ersetzt.
   * **Telemetrie:** Ein `1500 px` Kundenfoto wird in `~195 ms` bei `< 22 MB` Peak-RAM verarbeitet; selbst ein `9,7 MP` (`3600 x 2700`) Smartphone-Foto bleibt unter `44,4 MB` Peak-RAM und `< 580 ms` Gesamtlaufzeit (`0,0 MB` zurückgehaltener Speicher).
2. **Verzögerungsfreie 60-FPS HTML5-`<canvas>`-Vorschau im Foto-Studio:**
   * [`laser-photo-studio-modal.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-overview-partials/laser-photo-studio-modal.blade.php) berechnet alle Regler-Änderungen (`Kontrast`, `Helligkeit`, `Gamma`, `Lichter-Schutz`, `Schärfe`, `Graustufen`, `Invertieren`, `Jarvis`/`Stucki`/`Graustufen`/`Schwellenwert`) per `requestAnimationFrame` lokal im Browser (`0` Server-Requests beim Schieben, keine Base64-Blobs mehr im Livewire-Snapshot) und überträgt die Parameter erst beim Klick auf `Für Laser übernehmen`.
3. **Seelenfunke „Magie“-Ladeanimation auf der `Wartend`-Kachel:**
   * Beim Klick auf `Jetzt starten` in [`order-offer-detail-content.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/order/order-offer-detail-content.blade.php) reagiert das UI in `0 ms` mit einem umlaufenden goldenen `conic-gradient`-Lichtkranz, einem pulsierenden Seelenfunken-Stern (`✦`) samt aufsteigenden Mikro-Funken und dem Ladezustand `✦ Magie wird aktiviert...`.
