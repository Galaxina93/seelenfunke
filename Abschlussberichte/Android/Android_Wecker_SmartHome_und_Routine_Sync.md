# Funkenwerk Android – Wecker, Timer, Stoppuhr, Smart-Home KLAP v2 & Tagesroutine-Sync

**Abteilung:** `Android/` & `Leitung/`  
**Plattformen:** Funkenwerk Android App (`android-app/`) ↔ Mein-Seelenfunke Webportal (`/admin/routine`)  
**Status:** Produktiv & Vollständig Synchronisiert

---

## 1. Architektur-Leitentscheidung (Android-First + Paket-Sync)

Der **Wecker, Timer und die Stoppuhr** samt **Smart-Home-Steckdosensteuerung** und **KI-Morgen-Sprachbriefing** sind vollständig nativ in der **Funkenwerk Android App** beheimatet.
* **Keine unnötige Echtzeit-Komplexität in der Windows Desktop Suite (`funkenwerk/`):** Da das Smartphone am Bett das physische Weck-Medium ist, laufen Wecker-Planung (`AlarmManager`), lokaler WLAN-Schaltbefehl an die Tapo-Steckdosen und die Sprachausgabe autark auf dem Android-Gerät.
* **Gezielte Paket-Synchronisation mit `/admin/routine`:** Da die reale Aufstehzeit im Unternehmer-Alltag variiert, synchronisiert die Android App ihre Weckzeiten und die tatsächlich geloggten Aufstehzeiten (`wake_up_logs`) als leichtgewichtiges JSON-Paket mit dem Webportal (`POST /api/funki/routine/alarm-sync`). In `/admin/routine` verschiebt sich dadurch die morgendliche Tagesroutine-Timeline automatisch auf die echte Aufstehzeit (**Dynamische Wecker-Taktung**).

---

## 2. UI/UX-Integration in der Funkenwerk Android App

### 2.1 Startseiten-Kachel & Kompakte Organizer-Icon-Leiste
1. **Startseite ([`ZentrumScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/ZentrumScreen.kt)):**
   * Neben den Schnellzugriff-Kacheln (*Termine*, *Bestellungen*, *Routine*, *To-do Liste*) befindet sich im kompakten Dashboard-Grid die neue Kachel **„Wecker“** (`Icons.Default.Alarm`, Untertitel `Smart & KI`).
   * Ein Tap navigiert über `onNavigateToOrganizer(6)` direkt in den Wecker-Tab des Organizers.
2. **Kompakte Icon-Navigation im Organizer ([`OrganizerScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/OrganizerScreen.kt)):**
   * Statt breiter Text-Tabs nutzt die obere Organizer-Leiste **7 kompakte, gleichmäßig verteilte Icons** (`SpaceEvenly`), sodass auf jedem Smartphone-Display alle Bereiche ohne horizontales Abschneiden erreichbar sind:
     * `0`: Aufgaben (`CheckCircle`)
     * `1`: Kalender (`CalendarToday`)
     * `2`: Tagesroutine (`WbSunny`)
     * `3`: Einkaufsliste (`ShoppingCart`)
     * `4`: E-Mail (`Email`)
     * `5`: Kontakte (`Contacts`)
     * `6`: **Wecker, Timer & Stoppuhr (`Alarm`)**

### 2.2 Die 3 Unter-Tabs in [`AlarmTabScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/AlarmTabScreen.kt)
Am unteren Rand des Wecker-Bereichs befindet sich eine eigene Sub-Tab-Navigation (**Wecker | Timer | Stoppuhr**):
1. **Unter-Tab 1: `Wecker` (`WeckerSubTabView`)**
   * Große, abgerundete Karten (`24.dp`) mit Wochentags-Anzeige (*„Jeden Tag“*, *„Mo–Fr“*, *„Nicht geplant“*), `46.sp` Uhrzeit, Status-Badges (`⚡ Steckdosen`, `🎙️ KI`, `🔄 Routine`) und Pill-Switch.
   * **Native `AppTheme.colors`-Kartenlogik:** Alle Kacheln nutzen `AppTheme.colors.cardBackground` (`Slate900` im Dark Mode, `PureWhite` im Light Mode) und `AppTheme.colors.cardBorder`. Aktive Wecker erhalten analog zum Routine-Tab einen dezenten Gold-Tint (`Gold.copy(alpha = 0.10f)`), einen `1.5.dp` Gold-Rahmen (`#C5A059`), goldene Wochentags-Badges und einen Gold/SpaceBlack-Switch.
   * Über den schwebenden `+`-Button unten rechts oder durch Antippen einer Karte öffnet sich der `AlarmEditDialog`.
2. **Unter-Tab 2: `Timer` (`TimerSubTabView`)**
   * 2-spaltiges Grid (`LazyVerticalGrid(GridCells.Fixed(2))`) mit den Standard-Presets (`5 Min.`, `10 Min.`, `15 Min.`, `20 Min.`, `30 Min.`) sowie beliebig vielen eigenen Timern.
   * Jede Karte besitzt einen responsiven **Kreis-Fortschrittsring** (`.fillMaxWidth(0.82f).aspectRatio(1f)`), die Restzeit in `MM:SS`, einen Play/Pause-Button im Zentrum sowie Reset- und Lösch-Icons.
3. **Unter-Tab 3: `Stoppuhr` (`StoppuhrSubTabView`)**
   * Große, zentrierte `60.sp` Monospace-Anzeige (`00:00.00`) mit `16 ms` Frame-Genauigkeit.
   * Großer abgerundeter Pill-Button (**„Starten“ / „Stopp“ / „Weiter“**) sowie **„Runde“** und **„Zurücksetzen“** mit scrollbarer Rundenliste.

---

## 3. Technische Kern-Engine ([`FunkenwerkAlarmManager.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/FunkenwerkAlarmManager.kt) & [`SmartAlarmReceiver.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/receiver/SmartAlarmReceiver.kt))

### 3.1 Exaktes Android Wecker-Scheduling, Statusleisten-Anzeige & Boot-Persistenz
* Nutzt `AlarmManager.setAlarmClock(AlarmClockInfo(triggerAtMillis, showIntent), pendingIntent)`, damit das Android-System den Wecker selbst im tiefen Doze-Modus sekundengenau auslöst und das Wecker-Symbol in der Statusleiste anzeigt.
* **Permanente Anzeige in der Benachrichtigungsleiste (beim Herunterziehen) & Hintergrund-Stabilität:**
  * **Gestellter Wecker (`NOTIFICATION_ID_ACTIVE_ALARM = 9101`):** Sobald mindestens ein Wecker aktiv ist, erscheint oben in der herunterziehbaren Android-Benachrichtigungsleiste dauerhaft `⏰ Wecker gestellt: 08:30 Uhr` inkl. Bezeichnung, Smart-Home/KI-Badges, Ziel-Zeitstempel (`setWhen`), Tap-to-Open (springt direkt in den Wecker-Tab) und dem Schnell-Button **„Ausschalten“**.
  * **Laufender & Pausierter Timer (`NOTIFICATION_ID_ACTIVE_TIMER = 9102`):** Jeder laufende Timer wird an einen exakten Echtzeit-Ziel-Zeitstempel (`_timerEndMillisMap[timerId] = System.currentTimeMillis() + remSec * 1000L`) gekoppelt, in `SharedPreferences` persistiert und zusätzlich per `AlarmManager.setExactAndAllowWhileIdle` (`ACTION_TIMER_EXPIRED`) abgesichert. Dadurch läuft der Timer **100 % driftfrei weiter**, selbst wenn die App minimiert wird, der Tab gewechselt wird oder das Display gesperrt ist. In der Benachrichtigungsleiste zählt die Anzeige über den nativen Android-Countdown-Chronometer (`setUsesChronometer(true)`, `setChronometerCountDown(true)`) und den Sekundenticker (`⏳ Timer: 5 Min. · 04:58`) inkl. Fortschrittsbalken sowie den Direkt-Buttons **„Pause / Weiter“** und **„Zurücksetzen“** live herunter.
  * **Hintergrundfeste Stoppuhr (`stopwatchRunningFlow`, `stopwatchElapsedMsFlow`, `stopwatchLapsFlow`):** Auch die Stoppuhr berechnet ihre Laufzeit direkt über die System-Echtzeit (`stopwatchBaseElapsedMs + (System.currentTimeMillis() - stopwatchStartWallMs)`) im `FunkenwerkAlarmManager`, sodass sie beim Minimieren der App oder beim Tab-Wechsel ohne Unterbrechung weiterläuft.
* Beim Klingeln startet [`SmartAlarmReceiver.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/receiver/SmartAlarmReceiver.kt) eine Prioritäts-Notification (`IMPORTANCE_HIGH`) mit den Direkt-Aktionen **„Aufstehen & Sync“** (`ACTION_ALARM_DISMISS`) und **„5 Min Schlummern“** (`ACTION_ALARM_SNOOZE`) und plant Wiederholungswecker automatisch für den nächsten aktiven Wochentag neu ein.
* Nach einem Smartphone-Neustart stellt [`BootReceiver.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/receiver/BootReceiver.kt) alle aktiven Wecker automatisch wieder her (`rescheduleAllFromBoot`).

### 3.2 Lokale Smart-Home WLAN-Steuerung (TP-Link Tapo KLAP v2)
* Sobald ein Wecker mit aktivierter Steckdosen-Option klingelt (oder im Einstellungs-Dialog auf **„EIN / AUS“** getippt wird), steuert `FunkenwerkAlarmManager.switchTapoPlugKlapV2()` die ausgewählten WLAN-Steckdosen direkt im lokalen Heimnetzwerk (`192.168.188.x`) ohne Cloud-Umweg an.
* Implementiert das vollständige **TP-Link Tapo KLAP v2 Handshake- & AES-128-CBC-Protokoll** (`handshake1` -> `handshake2` -> `request?seq=...` mit `set_device_info {"device_on": true}`).
* Standardmäßig vorkonfigurierte Steckdosen (jederzeit in der App erweiterbar):
  * `Steckdose 1 (Werkstatt / Licht)` -> `192.168.188.109`
  * `Steckdose 2 (Kaffee / Büro)` -> `192.168.188.106`
  * `Steckdose 3 (Laser / Zusatz)` -> `192.168.188.123`

### 3.3 KI-Morgen-Sprachbriefing (`TextToSpeech`)
* Ist beim Wecker **„KI-Morgen-Briefing“** aktiviert, begrüßt die App Alina beim Aufstehen über die native deutsche Android-Sprachausgabe (`TextToSpeech`, `Locale.GERMANY`), nennt die aktuelle Uhrzeit, bestätigt das Einschalten der Smart-Home-Steckdosen und liest die wichtigsten gecachten Aufgaben und Kalendereinträge des Tages vor.

---

## 4. Paket-Synchronisation & Dynamische Wecker-Taktung in `/admin/routine`

### 4.1 REST-API Endpunkte ([`routes/api/routine.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/routine.php))
* **`GET /api/funki/routine/alarm-sync`:** Liefert das zuletzt synchronisierte Wecker-Paket (`active_alarm_time`, `active_alarm_label`, `alarms`, `timer_presets`, `smart_plugs`, `wake_up_logs`) aus `SystemSetting` (`'funki_alarm_sync_package'`).
* **`POST /api/funki/routine/alarm-sync`:** Nimmt das Synchronisations-Paket aus der Android App entgegen, führt die täglichen `wake_up_logs` der letzten 90 Tage verlustfrei zusammen und speichert den Zeitstempel `last_synced_at`.

### 4.2 Dynamische Routine-Verschiebung ([`ManagementRoutine.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementRoutine.php) & [`management-routine.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/management/management-routine.blade.php))
* Oben in `/admin/routine` zeigt die **Wecker-Taktungs-Leiste**:
  * **⏰ Aktiver Wecker** (z. B. `08:30 Uhr`) und **tatsächliche Aufstehzeit** des gewählten Tages
  * **Ø Aufstehen** über alle geloggten Tage
  * **Umschalter „Dynamisch aktiv (+X Min)“ / „Statisch“** (`wire:click="toggleAlarmShift"`)
  * **Button „Wecker-Sync“** (`wire:click="syncAlarmPackage"`) zum sofortigen Abruf des aktuellen Android-Pakets
* Ist **Dynamisch aktiv** eingeschaltet, berechnet `ManagementRoutine` die Differenz zwischen der frühesten Morgenroutine (z. B. `07:00 Uhr`) und der tatsächlichen Weck-/Aufstehzeit des jeweiligen Tages (z. B. `08:30 Uhr` -> `+90 Min`) und verschiebt alle Startzeiten der Tagesroutine-Schritte sowie die Fälligkeitsprüfung („Fällige abhaken“ & Smart-Score) automatisch passend zum echten Tagesstart.

---

## 5. Dark-Mode, Light-Mode & Mobile-Optimierung (Checkliste)

* **100 % Dark- & Light-Mode-Konsistenz:**
  * Alle Karten, Dialoge, OutlinedTextFields (`alarmOutlinedTextFieldColors()`) und Sub-Tab-Leisten in [`AlarmTabScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/AlarmTabScreen.kt) sowie die Wecker-Taktungs-Leiste in [`management-routine.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/management/management-routine.blade.php) unterstützen sowohl Dark Mode als auch Light Mode ohne harte Kontrastbrüche.
  * Das Passwortfeld für die lokalen Tapo-Zugangsdaten besitzt ein Auge-Icon (`Visibility` / `VisibilityOff`), das im Dark Mode explizit weiß (`Color.White`) eingefärbt ist.
* **Mobile Responsiveness:**
  * Kompakte 7-Icon-Leiste im Organizer verhindert abgeschnittene Tab-Namen auf schmalen Displays.
  * Timer-Kreisringe skalieren proportional über `.fillMaxWidth(0.82f).aspectRatio(1f)`.
  * Dialoge (`AlarmEditDialog`, `SmartHomeAndAiSettingsDialog`) sind vollständig vertikal scrollbar (`verticalScroll(rememberScrollState())`).
