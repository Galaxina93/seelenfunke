# Funkenwerk Android Suite: Architektur-, Redesign- & Testbericht der Homescreen-Widgets

**Stand:** November 2026  
**Abteilung:** `Android/` (`de.meinseelenfunke.app.widget.*`)  
**Zielgerät (Live-Test):** Google Pixel (`48201FDAS0064P`, Android 17 / API 37)

---

## 1. Ausgangslage & Identifizierte Kernprobleme (Root-Cause-Analyse)

Vor dem Komplett-Umbau wiesen die Android-Homescreen-Widgets (`TasksWidgetProvider`, `ShoppingListWidgetProvider`, `CalendarAppWidgetProvider`, `CalendarMonthWidgetProvider`) sowohl **visuelle Mängel** als auch **kritische Funktionsblockaden unter Android 14–17 (API 34–37)** auf:

### A. Technische & Funktionale Fehlerursachen
1. **Blockierte `ListView`-Interaktionen unter Android 14+ (`PendingIntent.FLAG_MUTABLE` ohne explizite Action):**
   * In [`TasksWidgetProvider.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/TasksWidgetProvider.kt) und [`CalendarAppWidgetProvider.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/CalendarAppWidgetProvider.kt) wurden `PendingIntent`-Templates mit `FLAG_MUTABLE` ohne gesetzte `Intent.action` bzw. mit kollidierendem `requestCode = 0` erstellt. Ab Android 14 (API 34+) verwirft das Betriebssystem mutable `PendingIntent`-Templates ohne explizite Action oder überschreibt bestehende Header-Intents.
   * Zudem besaßen Kind-Elemente in den XML-Zeilen (`widget_tasks_task_item.xml`, `widget_tasks_subtask_item.xml`) Attribute wie `android:autoLink="web"`, `android:clickable="true"` und `android:focusable="true"`, wodurch die `ListView` des Android-Launchers Touch-Events verschluckte und `setOnClickFillInIntent` blockierte.
2. **Fehlerhafter Inline-Bearbeitungs-Workflow & `ServiceConnectionLeaked`-Abstürze:**
   * Statt beim Tippen auf „Neue Aufgabe“ oder „Bearbeiten“ direkt die sauberen Compose-Dialoge ([`AddTaskWidgetActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/AddTaskWidgetActivity.kt), [`EditTaskWidgetActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/EditTaskWidgetActivity.kt)) zu öffnen, klappte das Widget provisorische Inline-Boxen (`[Tippen zum Schreiben]`) auf, die `TextInputActivity` starteten.
   * Beim Schließen von `TextInputActivity` wurde `AppWidgetManager.getInstance(this)` auf dem kurzlebigen Activity-Context aufgerufen, was laut `adb logcat` zu einem `android.app.ServiceConnectionLeaked`-Fehler führte.
3. **View-Recycling-Korruption in `TasksWidgetService`:**
   * Der „+ Neue Aufgabe“-Platzhalter nutzte dasselbe Layout (`widget_tasks_task_item.xml`) wie reguläre Aufgaben und blendete Checkbox, Priorität und Edit-Icon per `View.GONE` aus. Beim Scrollen wurden diese recycelten Views für echte Aufgaben wiederverwendet, ohne die Sichtbarkeiten zurückzusetzen.
4. **Fehlende Hintergrund-Synchronisation in beiden Kalender-Widgets:**
   * Weder [`CalendarAppWidgetProvider.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/CalendarAppWidgetProvider.kt) noch [`CalendarMonthWidgetProvider.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/CalendarMonthWidgetProvider.kt) riefen bei `onUpdate` oder `ACTION_REFRESH` jemals `ServiceLocator.organizerRepository.getCalendarEvents()` auf. Es wurde ausschließlich der lokale `SharedPreferences`-Cache gelesen.
5. **Fehlende `goAsync()`-Absicherung & instabile `getItemId()` in `ShoppingListWidgetProvider`:**
   * Netzwerk-Aufrufe in `ShoppingListWidgetProvider.onReceive()` liefen ohne `goAsync()`, sodass Android den Broadcast-Prozess vor Abschluss des API-Requests einfrieren konnte. Zudem gab `ShoppingListWidgetService.getItemId(position)` nur `position.toLong()` statt einer stabilen Objekt-ID zurück, obwohl sich die Sortierposition beim Abhaken (`needed` -> `stocked`) sofort ändert.

### B. Visuelle & Design-Mängel
* Verwendung veralteter Android-2.3-System-Icons (`@android:drawable/ic_menu_rotate`, `@android:drawable/ic_input_add`, `@android:drawable/ic_menu_edit`).
* Ausgewaschene graublaue Flächen (`#1E2638` / `#2A344B`), ungerundete Termin-Boxen im Monatskalender (`#161E2E`), dreizeilige überladene Header und unprofessionelle Text-Buttons (`<`, `>`, `[Tippen zum Schreiben]`).

---

## 2. Durchgeführtes Redesign & Architektur-Upgrade

### 2.1 Einheitliches Funkenwerk „SpaceBlack & Gold“ Design-System (`res/drawable/`)
* [`widget_background_shape.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/widget_background_shape.xml): Edler, tiefer SpaceBlack-Hintergrund (`#0D131F`) mit `22dp` Eckradius und dezenter Gold-Kontur (`1.5dp`, `#38C5A059`).
* [`widget_item_card_shape.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/widget_item_card_shape.xml): Matte, kontrastreiche Kartenoberfläche (`#161F30`) mit `12dp` Eckradius und `1dp` Slate-Border (`#26334A`).
* [`widget_button_gold_shape.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/widget_button_gold_shape.xml) & [`widget_badge_shape.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/widget_badge_shape.xml): Einheitliche Gold-Akzent-Buttons (`#1FC5A059` mit `#55C5A059` Rahmen) für alle `+`-Aktionen und Zähler-Badges.
* **Eigene Vektor-Icons (`24dp`):**
  * [`ic_widget_refresh.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_refresh.xml), [`ic_widget_add.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_add.xml), [`ic_widget_edit.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_edit.xml), [`ic_widget_chevron_left.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_chevron_left.xml), [`ic_widget_chevron_right.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_chevron_right.xml), [`ic_widget_calendar.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_calendar.xml).

### 2.2 Aufgabenverwaltung Widget ([`TasksWidgetProvider.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/TasksWidgetProvider.kt), [`TasksWidgetService.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/TasksWidgetService.kt) & [`TasksWidgetActionActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/TasksWidgetActionActivity.kt))
* **Zweistufige Navigation (Listen-Übersicht <-> Aufgabenliste):**
  * **Listen-Übersicht:** Zeigt alle Aufgabenlisten mit individuellem Vektor-Symbol, Listenname, Badge der offenen Aufgaben, Umbenennen-Button ([`RenameTaskListWidgetActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/RenameTaskListWidgetActivity.kt)) und Löschen-Button ([`ConfirmDeleteListActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/ConfirmDeleteListActivity.kt)).
  * **Einzellisten-Ansicht:** Zeigt den goldenen Zurück-Pfeil im Header, die Anzahl offener Aufgaben im Untertitel sowie alle Hauptaufgaben (sortiert nach Status & Priorität) inklusive eingerückter Unter-Schritte (`SubTask`).
* **Android 14–17 BAL-konformes Trampoline-Routing ([`TasksWidgetActionActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/TasksWidgetActionActivity.kt)):**
  * Da Android 15–17 (API 35–37) das Starten von Dialog-Activities aus einem Hintergrund-`BroadcastReceiver` (`BAL_BLOCK`) blockiert, nutzt das `ListView`-Template nun `PendingIntent.getActivity` auf [`TasksWidgetActionActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/TasksWidgetActionActivity.kt) (`Theme.WidgetTrampoline` ohne sichtbares Fenster/Animation). Dialog-Aktionen starten dadurch mit voller Vordergrund-Berechtigung (`result code=0`), während Hintergrund-Aktionen (`ACTION_SELECT_LIST`, `ACTION_TOGGLE_TASK`, `ACTION_CYCLE_PRIORITY`) verzögerungsfrei an den Receiver delegiert werden.
* **Direkte Compose-Dialoge statt fehlerhafter Inline-Formulare:**
  * Klick auf `+` im Header oder auf die untere Aktionszeile ([`widget_tasks_action_row.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/layout/widget_tasks_action_row.xml)) öffnet direkt [`AddTaskWidgetActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/AddTaskWidgetActivity.kt) bzw. [`AddTaskListWidgetActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/AddTaskListWidgetActivity.kt).
  * Klick auf eine Aufgabe oder das Stift-Symbol öffnet direkt [`EditTaskWidgetActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/EditTaskWidgetActivity.kt) (inkl. neuem Papierkorb-Button zum Löschen der Aufgabe sowie Löschen einzelner Unter-Schritte).
  * Klick auf die Checkbox schaltet den Erledigt-Status optimistisch in `0 ms` um und synchronisiert im Hintergrund via `goAsync()`.
  * Klick auf den farbigen Prioritäts-Punkt schaltet zyklisch zwischen `niedrig` (Blau), `mittel` (Amber) und `hoch` (Rot) um.

### 2.3 Einkaufsliste Widget ([`ShoppingListWidgetProvider.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/ShoppingListWidgetProvider.kt) & [`ShoppingListWidgetService.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/ShoppingListWidgetService.kt))
* **Live-Status im Header:** Zeigt dynamisch `"X benötigt · Y vorrätig"` an; ein Tipp auf den Header öffnet die Funkenwerk-App direkt im Tab `Organizer -> Einkauf` (`open_tab = 2, open_subtab = 3`).
* **1-Tap Toggle & Direkt-Löschen:**
  * Tipp auf einen Artikel schaltet sofort zwischen `needed` (oben, weiße Schrift) und `stocked` (unten, durchgestrichen) um.
  * Tipp auf das Papierkorb-Symbol am rechten Rand löscht den Artikel direkt aus der Einkaufsliste (`ACTION_DELETE_ITEM`).
* **Erweiterter Hinzufügen-Dialog ([`AddShoppingItemActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/AddShoppingItemActivity.kt)):**
  * Neben der Texteingabe mit automatischem Tastatur-Fokus verfügt der Dialog nun über eine Mengen-Auswahl (`−` / `1x` / `+`), sodass Stückzahlen direkt vom Homescreen aus erfasst werden.

### 2.4 Kalender-Widgets ([`CalendarAppWidgetProvider.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/CalendarAppWidgetProvider.kt) & [`CalendarMonthWidgetProvider.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/CalendarMonthWidgetProvider.kt))
* **Vollständige API-Synchronisation:** Beide Kalender-Widgets rufen bei `onUpdate` und `ACTION_REFRESH` über `goAsync()` aktiv `ServiceLocator.organizerRepository.getCalendarEvents()` auf und aktualisieren sowohl die Termin-Agenda als auch das Monatsraster über `triggerCalendarWidgetUpdate()`.
* **Agenda-Widget (`CalendarAppWidgetProvider`):**
  * Zeigt im Untertitel `"Heute: X · Gesamt: Y"`, besitzt einen Refresh- sowie einen `+`-Button (öffnet direkt den „Neuer Termin“-Dialog in der App) und formatiert Termine übersichtlich auf Deutsch (`"Heute · 14:00 Uhr"`, `"Morgen · Ganztägig"`, `"Fr, 12.11. · 10:00 Uhr"`).
* **Monatskalender-Widget (`CalendarMonthWidgetProvider`):**
  * Saubere Vektor-Chevrons (`<` / `>`), Refresh-Button, `+`-Button (übergibt das aktuell ausgewählte Datum `selected_date` an den Erstellungsdialog), Tipp auf den Monatstitel setzt Monat & Auswahl auf **Heute** zurück, und die Tagestermine unterhalb des Kalenderrasters werden in abgerundeten `@drawable/widget_item_card_shape`-Karten dargestellt.

### 2.5 Eliminierung von `ServiceConnectionLeaked` in [`OrganizerRepository.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/data/repository/OrganizerRepository.kt)
* `triggerTasksWidgetUpdate()`, `triggerShoppingWidgetUpdate()` und `triggerCalendarWidgetUpdate()` nutzen nun strikt `context.applicationContext` für `AppWidgetManager.getInstance(appContext)`. Dadurch kann beim Schließen von Dialog-Activities niemals eine `ServiceConnection` leaken.

### 2.6 Neues All-in-One „Funkenwerk Master-Hub“ Widget ([`MasterWidgetProvider.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/MasterWidgetProvider.kt), [`MasterWidgetService.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/MasterWidgetService.kt) & [`QuickEntryWidgetActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/QuickEntryWidgetActivity.kt))
* **Platzsparende Icon-Tab-Leiste oben ([`widget_master_layout.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/layout/widget_master_layout.xml)):**
  * Statt ausgeschriebener Text-Reiter nutzt die Kopfzeile kompakte Vektor-Icons mit Live-Zähler-Badges:
    * **`✓` Aufgaben** ([`ic_widget_tasks_check.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_tasks_check.xml) + Anzahl offener Aufgaben)
    * **`📅` Kalender** ([`ic_widget_calendar.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_calendar.xml) + Anzahl heutiger/kommender Termine)
    * **`🛒` Einkaufsliste** ([`ic_widget_shopping_cart.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_shopping_cart.xml) + Anzahl benötigter Artikel)
    * **`🎵` Musik-Bibliothek** ([`ic_widget_music_note.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_music_note.xml) – zeigt alle gecachten Songs als scrollbare Liste zum direkten Antippen & Abspielen)
* **Direkter Beleg-Scanner & Schnelleintrag ([`QuickEntryWidgetActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/QuickEntryWidgetActivity.kt)):**
  * Rechts in der Kopfzeile sitzt das goldene Beleg-Icon ([`ic_widget_receipt_camera.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/res/drawable/ic_widget_receipt_camera.xml)), das direkt über dem Homescreen das transparente Popup für **Beleg erfassen (KI-Kamera/Datei-Scanner) & manuellen Schnelleintrag** öffnet.
* **Kontextsensitives `+` & Persistente Mini-Player-Fußzeile:**
  * Der `+`-Button passt sich automatisch dem aktiven Tab an (*Neue Aufgabe/Liste*, *Neuer Termin*, *Neuer Einkaufsartikel*).
  * Am unteren Rand des Master-Widgets sitzt dauerhaft eine kompakte **Mini-Player-Leiste** (`🎵 Aktueller Song · Künstler | 🔀 ⏮ ▶/❚❚ ⏭`), sodass die Musikwiedergabe aus jedem Tab heraus gesteuert werden kann.

---

## 3. End-to-End-Verifikation & Live-Gerätetests (`48201FDAS0064P`)

Alle Widgets und Aktionen wurden nach Installation der Debug-APK auf dem angeschlossenen Smartphone (`48201FDAS0064P`) getestet:

| Testbereich | Geprüfte Aktion / Broadcast / Activity | Ergebnis |
| :--- | :--- | :--- |
| **Build & Installation** | `gradlew assembleDebug` + `adb install -r app-debug.apk` | **100 % Erfolgreich** (0 Compiler-Fehler) |
| **Funkenwerk Master-Hub** | Tab-Wechsel (`TAB_TASKS`, `TAB_CALENDAR`, `TAB_SHOPPING`, `TAB_MUSIC`), `QuickEntryWidgetActivity` (Beleg & Schnelleintrag), Mini-Player-Steuerung | **100 % Erfolgreich** |
| **Aufgaben-Widget** | `ACTION_TASKS_REFRESH`, `ACTION_TASKS_SELECT_LIST`, `ACTION_TASKS_BACK`, `ACTION_TASKS_TOGGLE`, `ACTION_TASKS_CYCLE_PRIORITY`, Start von `AddTaskWidgetActivity`, `EditTaskWidgetActivity`, `AddTaskListWidgetActivity` | **100 % Erfolgreich** (0 Crashes, sofortiges UI-Update) |
| **Einkaufsliste-Widget** | `ACTION_SHOPPING_REFRESH`, `ACTION_SHOPPING_TOGGLE`, `ACTION_SHOPPING_DELETE`, Start von `AddShoppingItemActivity` (inkl. Mengen-Auswahl) | **100 % Erfolgreich** (0 Crashes, stabile Sortierung) |
| **Kalender-Agenda-Widget** | `ACTION_REFRESH` (API-Sync), Klick auf Termin -> Deep-Link in Kalender-Tab, `+`-Button -> Termin-Erstellung | **100 % Erfolgreich** |
| **Monatskalender-Widget** | `ACTION_PREV_MONTH`, `ACTION_NEXT_MONTH`, `ACTION_RESET_MONTH`, `ACTION_SELECT_DAY`, `ACTION_MONTH_REFRESH` | **100 % Erfolgreich** |
| **Logcat-Stabilitätsprüfung** | Prüfung auf `AndroidRuntime`, `FATAL EXCEPTION`, `ServiceConnectionLeaked`, `PendingIntent` | **0 Fehler / 0 Leaks** |
