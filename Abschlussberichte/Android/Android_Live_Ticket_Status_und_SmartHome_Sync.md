# Android: Echtzeit-Ticketstatus im Chat, 'Go benötigt'-Steuerung & Smart-Home Tapo-Sync

**Verfasser:** Falcone (Chef-Architekt & Mitgründer) & Systemi (Abteilungsleiter System & Android)  
**Datum:** 16.11.2026  
**Status:** Erfolgreich implementiert, 100 % grün getestet & in Windows-Mirror synchronisiert  

---

## 1. Ausgangslage & Zielsetzung
Über Alinas Android-App gingen folgende Anforderungen ein:
1. **Smart-Home Tapo-Sync (`ag_20261116_161340_f0c95a77`):** Steuerung funktionierte direkt in der App, aber im System/Funkira-Widget erschien „Zugangsdaten fehlen“. Die Schnittstelle zwischen App und System musste geschlossen werden.
2. **App-Verlauf Go-Badge (`ag_20261116_161746_4f5e5c02`):** Im Verlauf sollte sichtbar sein, wo noch ein „Go“ für Konzept-Tickets benötigt wird.
3. **Echtzeit-Ticketstatus im Chat (`ag_20261116_163249_af5152fe`):** Eine Live-Statusanzeige der Antigravity-Tickets direkt im App-Chatverlauf mit Zählern für „Go benötigt“, „in Arbeit“ und „erledigt“ inklusive 1-Tap Go-Button.

---

## 2. Umgesetzte Architektur & Komponenten

### 2.1 Backend & Smarthome-Sync (Laravel / PHP)
* **Datei:** `routes/api/routine.php`
* In `POST /smarthome-sync` und `POST /alarm-sync` werden `tapo_email` und `tapo_password` sicher entgegengenommen und AES-verschlüsselt via `Crypt::encryptString` in `funki_smarthome_config` und `funki_alarm_sync_package` persistiert.
* In `GET /smarthome-sync` und `GET /alarm-sync` werden die Daten an autorisierte Clients entschlüsselt ausgeliefert. Sensible `*_encrypted`-Properties werden vor der Response bereinigt.

### 2.2 Android Tapo-Credentials Sync
* **Dateien:** `FunkenwerkAlarmManager.kt`, `OrganizerRepository.kt`, `OrganizerApi.kt`
* `exportSyncPayloadStrings` sendet verschlüsselt gespeicherte Tapo-Zugangsdaten aus `ServiceLocator` mit.
* `applyRemotePackage` übernimmt leere lokale Credentials automatisch vom Server, sodass App, Windows-Suite und System synchron bleiben.

### 2.3 Funkenwerk Windows WPF Suite
* **Datei:** `funkenwerk/MainWindow.xaml.cs`
* In `PushSmartHomeConfigToServersAsync` werden `tapo_email` und `tapo_password` bei lokaler Abwesenheit direkt aus dem Server-Response übernommen und in `smarthome.json` via DPAPI/FWAES256GCM1 geschützt.

### 2.4 Echtzeit-Ticketstatusleiste im Chat (`AgLiveTicketStatusBar`)
* **Datei:** `ChatScreen.kt`
* Direkt oberhalb des Chatverlaufs zwischen Agent-Selektor und Nachrichtenliste integriert.
* **3 dynamische Status-Pills:**
  * `⚡ X Go benötigt` (Gold/Amber)
  * `⏳ X in Arbeit` (bei Falcone)
  * `✓ X erledigt` (Smaragdgrün)
* **Aufklappbare Detailkarte (`heightIn(max = 260.dp)`):**
  * **Gruppe 1 (Go benötigt):** Tickets mit 1-Tap Quick-Action `⚡ Go erteilen`, die sofort den Direktauftrag absendet.
  * **Gruppe 2 (In Arbeit):** Laufende Aufgaben mit Abteilungs-Badge (`👑 Leitung`, `🛍️ Shop`, `📱 Android`, etc.).
  * **Gruppe 3 (Kürzlich erledigt):** Letzte 5 erledigte Aufgaben samt `agResponseSummary`.

### 2.5 FalconFocusScreen Header-Pill
* **Datei:** `FalconFocusScreen.kt`
* Kompakter `⚡ X Go`-Indikator im Top-Header neben `Falcone · Mitgründer`, der per Klick das `AgDirectInboxSheet` öffnet.

### 2.6 App-Verlauf Go-Status & Direct-Go Sprachsteuerung (`ag_20261116_200558_802ffb86` & `ag_20261116_200638_23290b56`)
* **Dateien:** `AgDirectBottomSheets.kt`, `AgDirectInboxManager.kt`, `FalconClientTools.kt`
* **Go-Bedarf im Verlauf:** In `AgHistoryItemCard` wird per `needsGo = item.status == AgDirectStatus.AWAITING_GO || (item.executionMode == "concept" && item.status != AgDirectStatus.COMPLETED)` differenziert. Unerledigte Konzepte und offene Rückfragen erhalten einen goldenen Kartenrand (`Gold.copy(alpha = 0.65f)`), das auffällige Header-Badge `⚡ Go benötigt` sowie den 1-Tap Schnellfreigabe-Button `⚡ Go` und das direkte Antwortfeld mit `✓ Absenden & Freigeben`. Bereits erledigte Konzepte zeigen dezent `Konzept` ohne Go-Bedarf.
* **Direct-Go Einstellung per Sprache:** Über SharedPreferences persistierter Switch `Sprachbefehle direkt als Go senden` im Clean-Design über der Historie. Bei aktiver Einstellung werden gesprochene Aufträge über `falcon_send_to_antigravity` sofort als `"direct"` statt `"concept"` übermittelt.

### 2.7 Proaktive & beharrliche Falcone-Stau-Warnung + Sekunde-0 Sprach-Anstoß (`ag_20261116_200716_2dc89d0c`)
* **Backend (`AntigravityDirectInboxService.php`, `AntigravityDirectInboxController.php`, `AIController.php`, `AiSystemFuncs.php`):**
  * Neuer Status `awaiting_go` inklusive `pending_question` und `awaiting_go_at`. `completeMessage` / `completeItem` schützt alle `concept`-Tickets sowie Antworten mit Rückfrage-Markern automatisch vor versehentlichem Schließen auf `completed`.
  * Atomare Auflösung über `POST /api/funkenwerk/ag-inbox/{id}/resolve` sowie die neuen Executive-Tools `ag_resolve_pending_blockade` und `ag_list_pending_blockades` (legt bei `approved` / `info_provided` sofort den Direkt-Folgeauftrag im selben Kanal an).
  * `GET /api/ai/live-credentials` injiziert bei aktivem Stau die beharrliche Falcone-Direktive in `system_instruction` und liefert `pending_ag_blockades` + `proactive_kick_prompt`.
* **Android-App (`FalconFocusScreen.kt`, `FunkiraLiveViewModel.kt`, `AgDirectInboxManager.kt`, `AgDirectBottomSheets.kt`, `ChatScreen.kt`, v1.8.6 / Code 30):**
  * **Beharrliche Schwarz-Gold Blockade-Karte & Warn-Chip:** Pulsierender Header-Chip `⚠️ X Antwort offen` und kompakte Blockade-Karte über dem unteren Dock mit `✓ Go geben` und Inline-Antwortfeld (`Antworten` + Spracheingabe). Selbst nach Tippen auf `Später` blendet ein `LaunchedEffect` die Karte nach `90` Sekunden sowie bei jedem `ON_RESUME` automatisch wieder ein, bis die Blockade gelöst ist.
  * **Sekunde-0 Sprach-Anstoß:** Sobald der Gemini-Live-WebSocket `setupComplete` meldet, sendet `FunkiraLiveViewModel` bei aktiven Blockaden sofort ein `clientContent`-Startpaket, sodass Falcone von sich aus das Gespräch eröffnet und beharrlich auf eine Entscheidung besteht. Nach Ausführung von `ag_resolve_pending_blockade` oder `falcon_resolve_antigravity_blockade` aktualisiert `refreshFromServer()` die Ansicht in Echtzeit.

---

## 3. Verifikation & Qualitätssicherung

* **Unit-Tests (Android):** `./gradlew testDebugUnitTest` & `./gradlew assembleRelease` (v1.8.6 / versionCode 30) 100 % grün.
* **Backend-Tests (PHPUnit):** `FunkenwerkFilesRoutinePrinterFinanceApiTest.php` (5/5), `FalconeApprovalBlockadeGuardTest.php` (4/4), `AntigravityDirectInboxTest.php` (4/4) & `WebSearchQuotaResilienceTest.php` (4/4) 100 % grün.
* **WPF-Build:** `dotnet build Funkenwerk.csproj` mit 0 Fehlern abgeschlossen.
* **Mirror-Sync:** 1:1 Abgleich nach `C:\Users\konta\AndroidStudioProjects\seelenfunke-android` per Robocopy vollzogen.
* **Sentinel-Inbox:** Alle Task-IDs (`ag_20261116_161340_f0c95a77`, `ag_20261116_161746_4f5e5c02`, `ag_20261116_163249_af5152fe`, `ag_20261116_200716_2dc89d0c`) via `SentinelCli` als erledigt markiert; offene Konzept-Entscheidungen live auf `awaiting_go` gestellt.
