# Android Chat: Galerie- und Screenshot-Upload beim Kamerasymbol

**Verfasser:** Falcone (Chef-Architekt & Mitgründer) & Systemi (Abteilungsleiter System & Android)  
**Datum:** 16.11.2026  
**Status:** Erfolgreich implementiert, 100 % grün getestet & in Windows-Mirror synchronisiert  

---

## 1. Ausgangslage & Zielsetzung
Über Alinas Android-App (Direktnachricht `ag_20261116_155240_0ae4841f` / Go `ag_20261116_160148_d13a9ad3`) wurde der Wunsch geäußert, im Chat beim Kamerasymbol bestehende Screenshots oder Galerie-Bilder direkt hochzuladen, anstatt ausschließlich die physische Kamera auszulösen.

**Ziel:**
1. Klick auf das Kamerasymbol im `FalconFocusScreen` öffnet ein Clean-Design ModalBottomSheet zur Wahl zwischen Live-Kamera und Galerie/Screenshots.
2. Long-Click auf den Kamera-Button fungiert als Schnellzugriff für sofortiges Öffnen des Galerie-Pickers.
3. Importierte Bilder werden automatisch ressourcenschonend komprimiert (max. 1440 px, EXIF-Drehung, 82 % JPEG-Qualität) und sofort als Gold-Thumbnail angezeigt, in der Lightbox vergrößerbar und an Nachrichten angehängt.
4. Auch im klassischen Text-Chat (`ChatScreen`) steht ein dedizierter Bild-Upload-Button bereit.

---

## 2. Umgesetzte Architektur & Änderungen

### 2.1 FalconCameraController
* **Datei:** `de.meinseelenfunke.app.util.FalconCameraController`
* **Methode:** `suspend fun importPhotoFromUri(context: Context, uri: Uri): FalconCapturedPhoto?`
* Verwendet `AgDirectInboxManager.prepareAttachmentFromUri(context, uri)` zur standardisierten Bildaufbereitung.
* Aktualisiert den `_lastCapturedPhoto` StateFlow und spielt den Bestätigungssound `R.raw.click_file_in_project_brain` ab.

### 2.2 AgDirectInboxManager
* **Datei:** `de.meinseelenfunke.app.util.AgDirectInboxManager`
* Dateinamen-Auflösung via `queryDisplayName` um Fallback `uri.lastPathSegment` erweitert, damit Galerie- und Content-URIs ohne `DISPLAY_NAME` sauber und deterministisch aufgelöst werden.

### 2.3 FalconFocusScreen
* **Datei:** `de.meinseelenfunke.app.ui.screens.FalconFocusScreen`
* Registrierung von `imagePickerLauncher` via `ActivityResultContracts.GetContent()`.
* **Kamera-Button im Bottom Dock:**
  * Klick öffnet `showPhotoSourceSheet = true`.
  * Langes Drücken (`onLongClick`) startet direkt `imagePickerLauncher.launch("image/*")`.
* **Clean-Design BottomSheet (`AgCameraSourceSheet`):**
  * Strikt nach Baustein 1 & 2: dunkler Hintergrund (`#10141E`), Gold-Rand (`#C5A059`), weiße Typografie, keine überflüssigen Subtexte.
  * Zwei klare Aktionen: `Foto aufnehmen` (`Icons.Default.PhotoCamera`) & `Bild wählen & an Antigravity senden` (`Icons.Default.Image`).
  * **1-Flow Auto-Open:** Nach Auswahl eines Bildes öffnet sich automatisch das `AgDirectInboxSheet` mit dem Bild als Anhang, sodass Alina direkt ihren Auftrag eintippen oder sprechen kann.
  * **Thumbnail-Quickaction:** Im Bottom Dock bietet das Foto-Thumbnail neben dem Verwerfen-Icon (`X`) ein goldenes Blitz-Icon (`Icons.Default.Bolt`), das den Sendedialog direkt öffnet.
  * **Lightbox Quick-Send:** Im `AgImageLightboxDialog` ([AgDirectBottomSheets.kt](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/components/AgDirectBottomSheets.kt)) erlaubt der Button `⚡ An Antigravity senden` den direkten Übergang aus der Großansicht in das Antigravity-Sheet.
  * **Auto-Reset nach Senden (Bugfix `ag_20261116_175736_0a9acd11`):** In `onAttachmentSubmitted` wird `FalconCameraController.clearLastCapturedPhoto()` aufgerufen, und das Bottom Dock filtert per `lastCapturedPhoto?.takeIf { !it.attachedToInbox }`, sodass das Thumbnail nach erfolgreichem Senden an Antigravity sofort und rückstandslos verschwindet.
  * **Zentrales Datei-Staging, Gold-Häkchen-Badge & Harter Kamera-Guard (`v1.8.10`, Build `34`):** Über `AgDirectInboxManager.stagedAttachmentFlow` synchronisieren Kamera, Galerie-Auswahl (`Bild aus Galerie hinterlegen`) und `AgDirectInboxSheet` (`+ Bild` / `+ Audio`) denselben Anhang-Zustand. Solange eine Datei hinterlegt ist, trägt der `📷`-Button ein goldenes `✓`-Badge, `FunkiraLiveViewModel` injiziert den Status in System-Prompt und laufende Live-WebSockets (`realtimeInput.video` + stilles Kontextpaket), `falcon_camera_control` blockt doppeltes Kamera-Öffnen per `status = "already_attached"` (sofern nicht explizit `force_new_photo = true`), und `falcon_send_to_antigravity` sendet hinterlegte Medien immer automatisch mit.

### 2.4 ChatScreen & ChatViewModel
* **Dateien:** `ChatScreen.kt`, `ChatViewModel.kt`
* Dedizierter Galerie-Button (`Icons.Default.Image`) neben dem Datei-Pin.
* Automatische Optimierung von Bild-Anhängen via `AgDirectInboxManager.prepareAttachmentFromUri`.
* Chat-Feed unterscheidet visuell zwischen `🖼️ Bild:` und `📎 Anhang:`.

---

## 3. Test- & Build-Ergebnisse

* **Unit-Tests:**
  * Neue Tests: `importPhotoFromUri_decodesResizesAndSetsLastCapturedPhotoCleanly` und `photoAttachmentLifecycle_clearAndMarkAttachedFilterDockThumbnailCleanly` in `FalconClientToolsAndMusicMatcherTest.kt`.
  * Ausführung: `./gradlew testDebugUnitTest`
  * Ergebnis: **74 von 74 Tests zu 100 % bestanden (BUILD SUCCESSFUL)**.
* **Compiler-Build:**
  * `./gradlew assembleDebug` erfolgreich abgeschlossen (DEX/APK fehlerfrei).
* **Mirror-Sync:**
  * Robocopy nach `C:\Users\konta\AndroidStudioProjects\seelenfunke-android\app\src` erfolgreich synchronisiert (Exit-Code 0).
