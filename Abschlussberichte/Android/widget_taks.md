# Abschlussbericht: Android Aufgaben-Widget (Tasks Widget)

Dieses Dokument bietet eine detaillierte Übersicht über die Architektur, Implementierungsdetails, Features und Navigationsstabilität des Android-Aufgaben-Widgets für die **Seelenfunke**-App.

---

## 1. Übersicht & Zielsetzung

Das Aufgaben-Widget ermöglicht Benutzern den schnellen Zugriff auf ihre Aufgaben und Aufgabenlisten direkt vom Android-Startbildschirm aus. Es wurde mit einem Fokus auf eine performante, flüssige Benutzererfahrung und eine konsistente Premium-Ästhetik (Dunkelblau/Gold/Schiefergrau) entwickelt. 

Das Widget unterstützt:
* **Listenübersicht** mit dynamischen Icons und Live-Zählern der offenen Aufgaben.
* **Aufgabenansicht** innerhalb einer ausgewählten Liste mit Prioritätsfarben und Datumsangaben.
* **Inline-Interaktionen** (Erledigen von Aufgaben, Ändern von Prioritäten und Fälligkeiten).
* **Direktes Hinzufügen** von Listen (inklusive Icon-Auswahl) und Unteraufgaben über eigenständige, schwebende Dialog-Aktivitäten direkt vom Homescreen aus, ohne die Haupt-App in den Vordergrund zu holen.

---

## 2. Widget-Architektur

Das Widget basiert auf der Android `AppWidgetProvider`-Architektur und verwendet eine `RemoteViewsService`-Datenquelle für scrollbare Listen.

### 2.1 Daten-Caching (Offline-First)
Um Ladezeiten beim Rendern des Widgets zu vermeiden (da RemoteViews keine asynchronen Netzwerkaufrufe im UI-Thread zulassen), verwendet das Widget ein lokales Cache-System in den `SharedPreferences` (`tasks_widget_prefs`):
* `task_lists_cache`: Enthält die JSON-Darstellung (GSON-serialisiert) aller Aufgabenlisten (`ManagementTaskList`).
* `tasks_cache`: Enthält die JSON-Darstellung aller Aufgaben (`ManagementTask`).

Jede Änderung in der App (z.B. Erstellen einer Aufgabe, Ändern einer Priorität) oder im Widget triggert ein Update dieses Caches und ruft anschließend `AppWidgetManager.notifyAppWidgetViewDataChanged()` auf.

### 2.2 Mehrere Widget-Instanzen (Multi-Instance Safety)
Wenn ein Benutzer mehrere Instanzen des Aufgaben-Widgets auf dem Homescreen platziert, müssen diese unabhängig voneinander agieren können (z.B. Widget A zeigt Liste X, Widget B zeigt Liste Y).
* **Instanzspezifische Präferenzen**: Zustände wie die ausgewählte Listen-ID, der Editier-Modus oder Zwischenzustände beim Hinzufügen werden mit dem Suffix `_$appWidgetId` in den SharedPreferences gespeichert (z.B. `widget_tasks_selected_list_id_10024`).
* **Verhinderung von Intent-Verschmelzung**: Android tendiert dazu, ähnliche `PendingIntent`s im Cache zu überschreiben. Um dies zu verhindern, wird die `appWidgetId` als Query-Parameter in das `data`-Feld des Intents codiert (z.B. `widget://tasks/click?appWidgetId=10024`). Dadurch wird jeder Intent als einzigartig eingestuft.

---

## 3. UI/UX & Interaktions-Design

### 3.1 Farb- und Designsystem
Das Layout orientiert sich streng an den Farbgebungen der Haupt-App:
* **Hintergrund**: Dunkles Schieferblau (`#1E293B` / `#0F172A`).
* **Akzentfarbe**: Gold (`#C5A059`) für Titel, Dummy-Aktionen und ausgewählte Zustände.
* **Prioritätsbalken**: Rot (`#EF4444`) für hoch, Orange (`#F97316`) für mittel, Schiefergrau (`#64748B`) für niedrig.

### 3.2 Icon-Mapping (Laravel-Kompatibilität)
Die Liste unterstützt 14 verschiedene Vektorsymbole, die mit dem Laravel-Backend synchronisiert sind. In [TasksWidgetService.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/TasksWidgetService.kt) ordnet die Hilfsmethode `getIconDrawableRes` dem String-Namen der API das entsprechende XML-Drawable zu:
* `bookmark`, `star`, `heart`, `bolt`, `home`, `briefcase`, `shopping-bag`, `trophy`, `sun`, `moon`, `wrench`, `rocket-launch`, `tag`, `flag`.

---

## 4. Features & Interaktions-Flows

### 4.1 Aufgaben-Liste (Inline-Steuerung)
* **Erledigen (Checkbox)**: Ein Klick auf die Checkbox führt einen optimistischen lokalen Zustand-Wechsel aus (durchgestrichener Text) und führt den API-Aufruf im Hintergrund aus.
* **Prioritäts-Wechsel**: Ein Klick auf das Prioritäts-Label (z.B. "MITTEL") rotiert die Priorität direkt im Widget (Niedrig -> Hoch -> Mittel -> Niedrig).
* **Fälligkeits-Wechsel**: Ein Klick auf das Fälligkeitsdatum (z.B. "Relevant ab: ...") rotiert das Datum durch vordefinierte Schritte (Heute -> Morgen -> In 3 Tagen -> In 7 Tagen -> Kein Datum).

### 4.2 Editier-Modus (Inline-Details)
Durch Tippen auf eine Aufgabe wird diese im Widget in den Editier-Modus versetzt:
* Das Widget lädt an Stelle 0 eine spezielle Kontrollkarte ([widget_tasks_edit_control.xml](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/res/layout/widget_tasks_edit_control.xml)).
* Hier können direkt die Prioritäten (Hoch, Mittel, Niedrig) per Button-Klick gesetzt, das Datum geändert oder gelöscht werden.
* Unter der Kontrollkarte werden alle zugehörigen Unteraufgaben (Subtasks) gerendert.

### 4.3 Erstellen von neuen Listen (Standalone-Dialog)
Tippt der Benutzer auf "+ NEUE LISTE HINZUFÜGEN" in der Listenübersicht, öffnet sich eine eigenständige, schwebende Compose-Aktivität ([AddTaskListWidgetActivity.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/AddTaskListWidgetActivity.kt)):
* Die Activity ist als halbdurchsichtiger Dialog gestaltet (`Theme.TransparentDialog`).
* Sie bietet ein Eingabefeld für den Listennamen.
* Sie zeigt ein Grid der 14 auswählbaren Icons, bei dem das gewählte Icon gold hervorgehoben wird.
* Beim Speichern wird die Liste über den Service erstellt und das Widget aktualisiert.

### 4.4 Hinzufügen von Unteraufgaben (Subtasks)
Tippt der Benutzer in der Detailkarte einer Aufgabe auf "+ NEUER SCHRITT", klappt das Formular **vollständig inline** direkt im Widget-Layout ([widget_tasks_add_subtask_control.xml](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/res/layout/widget_tasks_add_subtask_control.xml)) auf:
* Das Eingabefeld zeigt initial "[Tippen zum Schreiben]" an.
* Durch Tippen auf diesen Text wird ein minimalistischer, transparenter Dialog ([TextInputActivity.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/TextInputActivity.kt)) eingeblendet, um die Tastatur zu fokussieren und den Text einzugeben.
* Der eingegebene Titel wird direkt im Inline-Formular angezeigt.
* Durch Tippen auf "SPEICHERN" wird der neue Unter-Schritt über die API angelegt, die Liste aktualisiert und das Inline-Erstellungs-Layout schließt sich automatisch. Tapping "ABBRECHEN" klappt das Formular ohne Speichern wieder ein.

---

## 5. Gelöste Navigations- und Interaktionsprobleme

### 5.1 Blockierte Klicks durch Parent-Listener (Klick-Interzeptierung)
* **Problem**: Der Zurück-Button (`btn_tasks_back`) und der Refresh-Button reagierten auf manchen Android-Versionen nicht auf Klicks.
* **Ursache**: Dem Root-Layout (`R.id.tasks_widget_root`) war ein Dummy-PendingIntent (`ACTION_NONE`) zugeordnet, um leere Klicks abzufangen. Dieses Layout hat alle Touch-Events konsumiert und nicht an seine Kind-Elemente weitergegeben.
* **Lösung**: Der Dummy-Klick-Listener auf dem Root-Layout wurde vollständig entfernt. Dadurch können Touch-Events die Buttons nun uneingeschränkt erreichen.

### 5.2 Öffnen der Haupt-App im Hintergrund (Task-Affinity Konflikt)
* **Problem**: Beim Hinzufügen einer Liste oder eines Unter-Schritts öffnete sich die Dialog-Aktivität, zog jedoch die vollständige Benutzeroberfläche der Haupt-App (`MainActivity`) mit in den Vordergrund. Dies zerstörte das Gefühl, die Aktion "direkt im Widget" auszuführen.
* **Ursache**: Standardmäßig teilen sich alle Aktivitäten derselben App dieselbe Task-Affinity. Wenn eine Aktivität gestartet wird, holt das System den gesamten Task-Stack der App (inklusive der im Hintergrund liegenden MainActivity) in den Vordergrund.
* **Lösung**: Alle Widget-spezifischen Dialog-Aktivitäten wurden in der [AndroidManifest.xml](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/AndroidManifest.xml) mit `android:taskAffinity=""` deklariert. Dadurch starten sie in einem isolierten Task-Stack und verdecken den Homescreen als kleine, schwebende Popups, während die Haupt-App unsichtbar im Hintergrund verbleibt.

### 5.3 Cache-Konflikte des Zurück-Buttons
* **Problem**: Der Zurück-Button führte manchmal falsche Aktionen aus (z.B. Schließen des Editier-Modus statt Zurückgehen zur Listenübersicht).
* **Ursache**: Beide Aktionen benutzten identische Daten-URIs (`widget://tasks/back?appWidgetId=$appWidgetId`). Android führte die Intents zusammen.
* **Lösung**: Eindeutige URIs wurden vergeben:
  * Zurück aus Edit-Modus: `widget://tasks/back/exit_edit?appWidgetId=$appWidgetId`
  * Zurück aus Listenübersicht: `widget://tasks/back/to_lists?appWidgetId=$appWidgetId`

### 5.4 Blockierte Klicks auf Dialog-Elementen in Compose (Card Disable Bug)
* **Problem**: In den Compose-basierten Widget-Dialog-Aktivitäten (`AddShoppingItemActivity`, `AddTaskListWidgetActivity`, `AddTaskWidgetActivity`, `ConfirmDeleteListActivity`, `EditTaskWidgetActivity` und `TextInputActivity`) ließen sich die Eingabefelder nicht anklicken/fokussieren und die Buttons reagierten nicht.
* **Ursache**: Auf dem umschließenden `Card`-Container war der Modifier `.clickable(enabled = false) {}` gesetzt. In Jetpack Compose deaktiviert dieser Modifier die Touch-Ereignis-Weitergabe (Click Propagation) für alle Kind-Elemente innerhalb der Karte.
* **Lösung**: Der Card-Modifier wurde in allen betroffenen Aktivitäten durch ein standardmäßiges `.clickable {}` (ohne `enabled = false`) ersetzt. Dies schluckt Klicks auf den Kartenhintergrund (sodass sie nicht den darunterliegenden Schließen-Listener der Box triggern), hält aber die inneren Elemente voll funktionsfähig.

### 5.5 Verschwinden und Flackern des Zurück-Buttons bei Aktualisierung (Refresh)
* **Problem**: Drückte man den Aktualisieren-Button (oben rechts) im Edit-Modus oder in einer Liste, flackerte das Zurück-Icon kurz und verschwand dann dauerhaft.
* **Ursache**: Die Sichtbarkeit des Zurück-Buttons hing von der Variable `selectedListName != null` ab. Bei einer Aktualisierung wird der Cache neu geladen und die Werte können kurzzeitig null sein.
* **Lösung**: Die Sichtbarkeit wurde entkoppelt und hängt nun direkt von den Zustandsvariablen `isInsideList` oder `isEditing` ab, die während des Refreshes stabil bleiben. Der Titel greift stabil auf `(selectedListName ?: "Aufgaben").uppercase()` zurück.

### 5.6 Navigation-Hänger beim Zurückkehren zur Listenübersicht (PendingIntent Caching)
* **Problem**: Wenn der Benutzer sich in einer Aufgabenliste befindet (aber nicht im Editier-Modus einer bestimmten Aufgabe) und auf den Zurück-Button klickt, wechselt das Widget nicht zurück zur Listenübersicht.
* **Ursache**: In `updateAppWidget` wurden je nach Zustand unterschiedliche `PendingIntent`s (mit unterschiedlichen Actions `ACTION_EXIT_EDIT_MODE` und `ACTION_BACK_TO_LISTS`) an dieselbe View-ID (`R.id.btn_tasks_back`) gebunden. Android's RemoteViews-Framework führt bei Updates partielle Merges durch und cached PendingIntents aggressiv. Dadurch wurde beim Klick auf den Zurück-Button weiterhin die alte Aktion (`ACTION_EXIT_EDIT_MODE`) gesendet, obwohl sich der interne Zustand bereits geändert hatte und der Intent eigentlich auf `ACTION_BACK_TO_LISTS` hätte wechseln müssen.
* **Lösung**: Die Bindung der Klick-Aktionen wird vereinheitlicht. Der Zurück-Button erhält einen einzigen, statischen PendingIntent mit der Aktion `ACTION_BACK_TO_LISTS`. In der `onReceive`-Methode des Providers wird die tatsächliche Navigationsabsicht dynamisch anhand des aktuellen SharedPreferences-Zustands aufgelöst:
  1. Ist eine `editingTaskId` vorhanden, wird der Editier-Modus verlassen (Entfernen der ID).
  2. Ist keine `editingTaskId` vorhanden, aber eine `selectedListId`, wird die Liste verlassen (Zurückgehen zur Listenübersicht).
  Dadurch entfallen fehleranfällige dynamische Wechsel der PendingIntent-Binds auf Betriebssystemebene.

---

## 6. Zusammenfassung der betroffenen Dateien

* **Widget-Provider & Logik**:
  * [TasksWidgetProvider.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/TasksWidgetProvider.kt): Steuert Klick-Broadcasts, Cache-Updates, Navigationsübergänge und Stabilität des Zurück-Buttons.
  * [TasksWidgetService.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/TasksWidgetService.kt): Fabrikklasse zur Erstellung der scrollbaren Zeilen im Widget.
* **Dialog-Aktivitäten (mit Compose-Klick-Fix)**:
  * [AddShoppingItemActivity.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/AddShoppingItemActivity.kt): Dialog zum Hinzufügen von Artikeln zur Einkaufsliste.
  * [AddSubtaskWidgetActivity.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/AddSubtaskWidgetActivity.kt): Dialog zum Hinzufügen von Unteraufgaben.
  * [AddTaskListWidgetActivity.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/AddTaskListWidgetActivity.kt): Dialog zum Erstellen von Aufgabenlisten mit Icon-Auswahl-Grid.
  * [AddTaskWidgetActivity.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/AddTaskWidgetActivity.kt): Dialog zum Erstellen neuer Aufgaben mit Prioritäts- und Datumsauswahl.
  * [ConfirmDeleteListActivity.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/ConfirmDeleteListActivity.kt): Bestätigungsdialog zum Löschen einer Liste.
  * [EditTaskWidgetActivity.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/EditTaskWidgetActivity.kt): Ausführlicher Editor für Aufgabendetails und Unteraufgaben.
  * [TextInputActivity.kt](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/java/de/meinseelenfunke/app/widget/TextInputActivity.kt): Hilfsdialog für generische Texteingaben (für Inline-Unteraufgaben).
* **Konfiguration & Layout**:
  * [AndroidManifest.xml](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/AndroidManifest.xml): Deklaration der Widgets und Isolierung der Dialog-Aktivitäten via `taskAffinity`.
  * [widget_tasks_layout.xml](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/res/layout/widget_tasks_layout.xml): Hauptstruktur des Aufgaben-Widgets.
  * [widget_tasks_edit_control.xml](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/res/layout/widget_tasks_edit_control.xml): Detailkarten-Layout mit direkt klickbaren Buttons im Widget.
  * [widget_tasks_add_subtask_control.xml](file:///C:/Users/konta/AndroidStudioProjects/seelenfunke-android/app/src/main/res/layout/widget_tasks_add_subtask_control.xml): Inline-Layout für das Erstellen von Unteraufgaben.

