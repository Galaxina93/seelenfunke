# Technische Erkenntnisse & Systemarchitektur - Seelenfunke App

Dieses Dokument dient als umfassendes Referenzhandbuch für Entwickler, die am **Seelenfunke** Laravel-Backend und der Kotlin-Android-Anwendung arbeiten. Es beschreibt die Ursachen, Architekturmuster und technischen Details hinter den jüngsten Feature-Integrationen und Fehlerbehebungen.

---

## 1. Hochauflösende Kameraerfassung & Bildkompression

### Ursache für schlechte Bildqualität (Thumbnails)
Zuvor nutzte die App den standardmäßigen Intent-Capture-Vertrag `ActivityResultContracts.TakePicturePreview()`.
* **Verhalten:** Dieser Vertrag liefert direkt über das Intent-Ergebnis-Bundle ein kleines `Bitmap` mit niedriger Auflösung (eine Thumbnail-Darstellung) zurück.
* **Einschränkung:** Dies ist nur für schnelle Vorschauen gedacht und nicht für hochauflösende Uploads, was zu verpixelten Bildanhängen führte.

### Hochauflösende Lösung via FileProvider
Um Bilder in voller Auflösung aufzunehmen, muss Android die Datei über eine Datei-URI direkt auf einen Speicherpfad schreiben.
1. **FileProvider-Registrierung:** Definiert in `AndroidManifest.xml` unter der Authority `de.meinseelenfunke.app.fileprovider`.
2. **Pfadkonfiguration (`res/xml/file_paths.xml`):**
   ```xml
   <paths>
       <cache-path name="camera_photos" path="." />
   </paths>
   ```
3. **Ablauf:**
   * Beim Klick auf die Kamera wird eine temporäre Datei im Cache-Verzeichnis der App erstellt: `File.createTempFile("JPEG_", ".jpg", context.cacheDir)`.
   * Eine sichere `content://` URI wird generiert: `FileProvider.getUriForFile(context, "de.meinseelenfunke.app.fileprovider", tempFile)`.
   * Diese URI wird an `ActivityResultContracts.TakePicture()` übergeben.
   * Nach der Aufnahme schreibt die Kamera-App das unkomprimierte, hochauflösende Bild direkt in diese Datei.

### Speicher- & netzwerkoptimierte Kompression (`ImageUtils.kt`)
Hochauflösende Kamerasensoren erzeugen Bilder von über 10 MB, was zu HTTP-Timeouts und hohem Datenverbrauch führt.
* **Downsampling:** Das rohe Bitmap wird mit `BitmapFactory.Options` und einer Begrenzungsprüfung decodiert. Wenn eine Dimension **1920px** überschreitet, wird das Bild unter Beibehaltung des Seitenverhältnisses so herunterskaliert, dass es in eine `1920x1920` Box passt.
* **Qualitätskompression:** Das herunterskalierte Bitmap wird als JPEG mit **80% Qualität** in ein Byte-Array komprimiert. Dadurch sinkt die durchschnittliche Dateigröße von ca. 8–12 MB auf **ca. 200–400 KB**, ohne dass auf mobilen Bildschirmen ein sichtbarer Qualitätsverlust entsteht.
* **Bereinigung:** Temporäre Dateien im Cache-Verzeichnis werden nach Abschluss des Lifecycles explizit gelöscht.

---

## 2. Zeitzonen-Offset & "22 Uhr" Anzeige-Fehler

### Ursache
1. **Laravel-Datumskonvertierung:** In Eloquent konvertiert das Casten einer Spalte als `'date'` den rohen MySQL-Datumsstring (z. B. `2026-05-21`) in eine Carbon-Instanz.
2. **Standard-JSON-Serialisierung:** Bei der Serialisierung in JSON formatiert Laravel Carbon-Instanzen in der ISO-8601 UTC-Notation (`Z`).
3. **Offset-Verschiebung:**
   * Ein Datenbankwert von `2026-05-21` entspricht der lokalen Mitternacht: `2026-05-21 00:00:00` in der Anwendungszeitzone (`Europe/Berlin`, UTC+2).
   * Bei der JSON-Serialisierung wird diese Ortszeit in UTC umgerechnet, wodurch 2 Stunden abgezogen werden: `2026-05-20T22:00:00.000000Z`.
   * Der Android-Client zeigte diesen rohen serialisierten String direkt an und stellte somit den Zeitanteil `22:00:00` dar. Zudem verschob sich das Datum aufgrund des Offsets fälschlicherweise auf den **Vortag** (20. Mai statt 21. Mai).

### Backend-Behebung
Es wurden explizite Formate in den Eloquent-Model-Casts konfiguriert:
```php
// AccountingSpecialIssue.php & AccountingCostItem.php
protected $casts = [
    'execution_date'     => 'date:Y-m-d',
    'first_payment_date' => 'date:Y-m-d',
];
```
Dies erzwingt die Serialisierung als einfacher Datumsstring (`"2026-05-21"`), wodurch Uhrzeitangaben und Zeitzonenverschiebungen entfallen.

### Client-Seitiger toleranter Formatter
Um auch historische Daten (oder andere API-Antworten mit ISO-Zeitstempeln) korrekt zu verarbeiten, wurde ein toleranter Formatter in `FinanceScreen.kt` integriert:
```kotlin
private fun formatDateString(dateStr: String?): String {
    if (dateStr.isNullOrBlank()) return ""
    try {
        if (dateStr.contains("T")) {
            val isoParser = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.US).apply {
                timeZone = TimeZone.getTimeZone("UTC")
            }
            val date = isoParser.parse(dateStr)
            if (date != null) {
                val formatter = SimpleDateFormat("dd.MM.yyyy", Locale.GERMANY).apply {
                    timeZone = TimeZone.getDefault()
                }
                return formatter.format(date)
            }
        }
        val simpleParser = SimpleDateFormat("yyyy-MM-dd", Locale.US)
        val date = simpleParser.parse(dateStr)
        if (date != null) {
            val formatter = SimpleDateFormat("dd.MM.yyyy", Locale.GERMANY)
            return formatter.format(date)
        }
    } catch (e: Exception) {
        e.printStackTrace()
    }
    return dateStr
}
```
Dieser übersetzt UTC ISO-Zeitstempel vor der Formatierung in das deutsche Standardformat `dd.MM.yyyy` zurück in die lokale Benutzerzeit.

---

## 3. Chronologische Sortierung & Stabile Sortiermechanismen

### Ursache für zufällige Sortierung am selben Tag
Wenn mehrere Transaktionen dasselbe Ausführungsdatum hatten (z. B. vier Testeinträge am 20. Mai), sortierte der Client diese mit:
```kotlin
compareByDescending<FinanceSpecialIssue> { it.execution_date }
    .thenByDescending { it.id }
```
* **Grund des Fehlers:** Die Datenbank-IDs sind v4 UUIDs (z. B. `8d7c4932-a5bf...`), die komplett zufällig generiert werden. Die sekundäre Sortierung nach absteigender ID erzeugte somit eine willkürliche Reihenfolge für Transaktionen am selben Tag.

### Stabile chronologische Sortierungslösung
1. **Sekundäre Sortierung im Backend:** Die Backend-API liefert die Einträge nun sortiert nach Ausführungsdatum und Erstellungszeitstempel zurück:
   ```php
   return AccountingSpecialIssue::where('admin_id', $request->user()->id)
       ->orderBy('execution_date', 'desc')
       ->orderBy('created_at', 'desc')
       ->take($limit)
       ->get();
   ```
2. **Stabiles Sortieren auf dem Client:** Der Sortieralgorithmus von Kotlin ist stabil. Durch das Entfernen der sekundären UUID-Sortierung sortiert der Client *nur* nach absteigendem `execution_date`:
   ```kotlin
   _variableItems.value = list.sortedWith(
       compareByDescending { parseDate(it.execution_date) }
   )
   ```
   Dies garantiert, dass Einträge mit gleichem Datum ihre vom Server zurückgegebene relative Reihenfolge beibehalten (neueste Erstellung anhand `created_at` oben).

---

## 4. OkHttp Security-Interceptor & Auto-Logout (HTTP 401)

### Ursache für UI-Einfrieren bei abgelaufener Sitzung
Wenn ein Sanctum-API-Token ungültig wurde oder ablief, gab der Server `HTTP 401 Unauthorized` zurück. Die App fragte im Hintergrund weiterhin Endpunkte ab, was zu fortlaufenden Fehlern, Lade-Spinnern und einer unbenutzbaren App führte. Der einzige Ausweg für den Nutzer war das manuelle Löschen des App-Speichers.

### Automatisierte Logout-Architektur
1. **Interceptor-Hook (`ServiceLocator.kt`):** Ein eigener OkHttp-Netzwerk-Interceptor prüft den HTTP-Statuscode jeder Antwort.
2. **Token-Löschung:** Wenn ein `401` abgefangen wird, wird das zwischengespeicherte Autorisierungs-Token sofort aus den SharedPreferences gelöscht.
3. **Asynchroner Broadcast:** Ein Kotlin `SharedFlow`-Broadcast wird ausgelöst:
   ```kotlin
   object NavigationBridge {
       private val _logoutTrigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
       val logoutTrigger = _logoutTrigger.asSharedFlow()
       
       fun triggerLogout() {
           _logoutTrigger.tryEmit(Unit)
       }
   }
   ```
4. **UI-Navigations-Sammlung (`AppNavigation.kt`):**
   ```kotlin
   LaunchedEffect(Unit) {
       NavigationBridge.logoutTrigger.collect {
           navController.navigate("login") {
               popUpTo(0) // Backstack leeren
           }
       }
   }
   ```
   Dies leitet den Benutzer automatisch zurück zum Login-Bildschirm und erzwingt eine saubere Neuanmeldung.

---

## 5. UI-Betrags-Einfärbung (Farbtokens für Premium Dark Theme)
Um dem dunklen Premium-Design der App gerecht zu werden, werden grelle Primärfarben (wie einfaches Standard-Rot/Grün) vermieden. Stattdessen nutzen wir HSL-abgestimmte Markenfarben aus `Color.kt`:
* **Emerald500 (`#10B981`):** Steht für Einnahmen und positive Transaktionswerte.
* **Rose500 (`#F43F5E`):** Steht für Ausgaben und negative Transaktionswerte.

### Implementierung
```kotlin
color = if (item.amount < 0) Rose500 else Emerald500
```
Dies wird in den Listenfeldern für **"Variable Ausgaben"** und **"Fixkosten Verträge"** angewendet.

---

## 6. Komma-zu-Punkt-Eingabeparsing

### Ursache für blockierte Speicher-Buttons
Deutsche Tastaturlayouts verwenden standardmäßig ein Komma für Dezimalzahlen (z. B. `12,50`). Kotlins Standard-Parsefunktion `toDoubleOrNull()` erwartet jedoch einen Punkt (`.`) und gibt bei Kommas `null` zurück. Dadurch schlug die Eingabeprüfung lautlos fehl und der Speicherbutton blieb deaktiviert.

### Lösung
Eingegebene Zahlenstrings ersetzen Kommas durch Punkte, bevor sie geprüft oder an das API übergeben werden:
```kotlin
val amount = amountText.replace(',', '.').toDoubleOrNull()
```
Dies stellt sicher, dass die Eingabeprüfung und das Abspeichern unabhängig vom lokalen Tastaturlayout des Nutzers funktionieren.
