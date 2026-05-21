# Abschlussbericht & Systemarchitektur: Finanzen (Beleg-Upload, State-Sync & Datenqualität)

Dieses Dokument dokumentiert die technische Architektur, Datenfluss-Schnittstellen und Fehlerbehebungen der Finanz-Features (Variable Ausgaben, Fixkosten & Belege) in der Seelenfunke App-Infrastruktur.

---

## 1. High-Resolution Kamera-Erfassung & Bildkompression

### Ursache für schlechte Bildqualität (Thumbnail-Bug)
Zuvor wurde das Standard-Intent `ActivityResultContracts.TakePicturePreview()` genutzt.
* **Problem:** Dieser Kontrakt liefert nur ein verkleinertes Vorschaubild (Thumbnail) direkt über das Intent-Ergebnis-Bundle zurück.
* **Auswirkung:** Hochgeladene Belege waren stark verpixelt und unlesbar.

### Lösung über FileProvider (Vollauflösung)
Um Fotos in Originalqualität aufzunehmen, müssen diese vom Kamerasystem direkt in eine Datei auf dem Gerätespeicher geschrieben werden.
1. **FileProvider-Registrierung (`AndroidManifest.xml`):** Registriert unter der Authority `de.meinseelenfunke.app.fileprovider`.
2. **Pfad-Konfiguration (`res/xml/file_paths.xml`):**
   ```xml
   <paths>
       <cache-path name="camera_photos" path="." />
   </paths>
   ```
3. **Ablauf:**
   * Beim Klick auf den Kamera-Button wird eine temporäre Datei im Cache-Verzeichnis erzeugt: `File.createTempFile("JPEG_", ".jpg", context.cacheDir)`.
   * Daraus wird eine sichere Inhalts-URI erzeugt: `FileProvider.getUriForFile(context, "de.meinseelenfunke.app.fileprovider", tempFile)`.
   * Diese URI wird an `ActivityResultContracts.TakePicture()` übergeben.
   * Die System-Kamera-App schreibt das Foto in voller Auflösung in die Datei.

### Speicher- & Netzwerkoptimierte Bildkompression (`ImageUtils.kt`)
Moderne Smartphone-Sensoren erzeugen Fotos von 8-15 MB. Dies führt im mobilen Netz zu Timeouts.
* **Downsampling:** Das Bild wird über `BitmapFactory.Options` analysiert. Überschreitet eine Dimension **1920px**, wird das Bild proportional herunterskaliert.
* **Kompression:** Das herunterskalierte Bitmap wird mit **80% JPEG-Qualität** komprimiert.
* **Ergebnis:** Die Dateigröße sinkt von ~10MB auf **~200-400KB** bei voller Lesbarkeit von Texten auf Quittungen.
* **Bereinigung:** Temporäre Cache-Dateien werden nach dem Upload sofort gelöscht.

---

## 2. Zeitzonen-Offset & "22 Uhr" Anzeige-Bug

### Ursache
1. **Laravel Date Casting:** In Eloquent war `execution_date` als `'date'` definiert.
2. **JSON-Serialisierung:** Laravel konvertiert Carbon-Objekte standardmäßig in ISO-8601 UTC-Strings (z. B. `2026-05-21 00:00:00` in Berlin (UTC+2) wird zu `2026-05-20T22:00:00.000000Z`).
3. **Anzeige-Offset:**
   * Der Android-Client zeigte den serialisierten String roh an.
   * Dadurch verschob sich das Datum für den Nutzer fälschlicherweise um einen Tag nach hinten (20. statt 21. Mai) und zeigte die Uhrzeit `22:00` an.

### Backend-Lösung
Anpassung der Casts in den Laravel-Modellen `AccountingSpecialIssue` und `AccountingCostItem`:
```php
protected $casts = [
    'execution_date'     => 'date:Y-m-d',
    'first_payment_date' => 'date:Y-m-d',
];
```
Dadurch gibt die API das Datum als reinen Datums-String (`"2026-05-21"`) aus, ohne Uhrzeit oder Zeitzonenshift.

### Client-Seitiger Resilient Formatter
Zur Absicherung älterer Datenbestände parst der Client in `FinanceScreen.kt` Datumsangaben flexibel:
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
Dies wandelt UTC ISO-Zeitstempel zurück in das lokale Nutzerdatum und formatiert es zu `dd.MM.yyyy`.

---

## 3. Chronologische Sortierung (Vermeidung von UUID-Randomisierung)

### Ursache des Sortierungs-Bugs
Transactions wurden auf dem Client nach `execution_date` absteigend und bei Gleichheit nach `id` absteigend sortiert.
* **Fehler:** Die IDs sind zufällig generierte v4 UUIDs. Das Sortieren nach UUIDs führte bei gleichem Datum (z. B. am selben Tag erfasste Testbuchungen) zu einer zufälligen Reihenfolge statt zur Sortierung nach Erstellungszeitpunkt.

### Lösung
1. **Backend-Sortierung:** Die API sortiert nun standardmäßig nach Erstellungszeitpunkt (`created_at`):
   ```php
   return AccountingSpecialIssue::where('admin_id', $request->user()->id)
       ->orderBy('execution_date', 'desc')
       ->orderBy('created_at', 'desc')
       ->take($limit)
       ->get();
   ```
2. **Stabile Client-Sortierung:** Kotlin's Sortierung ist stabil. Wir entfernen die Sortierung nach der zufälligen UUID und sortieren im ViewModel nur nach Datum:
   ```kotlin
   _variableItems.value = list.sortedWith(
       compareByDescending { parseDate(it.execution_date) }
   )
   ```
   Dadurch bleibt die vom Server gelieferte Reihenfolge (`created_at` absteigend) für Einträge am selben Tag erhalten.

---

## 4. API-Sicherheit & Automatischer Logout (HTTP 401)

### Ursache
Nach Ablauf oder Widerruf des Sanctum-Tokens lieferte die API den Code `401 Unauthorized`. Da der Client dies nicht abfing, luden Ladespinner unendlich weiter und die App wirkte eingefroren.

### Lösung (Interceptor & UI-Bridge)
1. **Interceptor Hook (`ServiceLocator.kt`):** Ein Netzwerk-Interceptor überwacht alle HTTP-Antworten.
2. **Token-Löschung:** Bei Status `401` wird das Token sofort aus den SharedPreferences entfernt.
3. **UI-Benachrichtigung:** Es wird ein Signal über einen SharedFlow gefeuert:
   ```kotlin
   object NavigationBridge {
       private val _logoutTrigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
       val logoutTrigger = _logoutTrigger.asSharedFlow()
       
       fun triggerLogout() {
           _logoutTrigger.tryEmit(Unit)
       }
   }
   ```
4. **UI-Sicherheits-Umleitung (`AppNavigation.kt`):**
   ```kotlin
   LaunchedEffect(Unit) {
       NavigationBridge.logoutTrigger.collect {
           navController.navigate("login") {
               popUpTo(0)
           }
       }
   }
   ```
   Der Nutzer wird bei abgelaufener Session sofort zum Login-Screen umgeleitet.

---

## 5. UI Betrags-Einfärbung (Branding-Farbpaletten)

Zur Erhaltung des hochwertigen Dark-Mode-Designs verwenden wir harmonische, nicht-generische HSL-Farbwerte:
* **Emerald500 (`#10B981`):** Für positive Beträge (Einnahmen/Rückerstattungen).
* **Rose500 (`#F43F5E`):** Für negative Beträge (Ausgaben).

### Logik
```kotlin
color = if (item.amount < 0) Rose500 else Emerald500
```
Dies wurde sowohl in der Liste der **Variablen Ausgaben** als auch der **Fixkosten-Verträge** umgesetzt.

---

## 6. Komma-zu-Punkt-Eingabekonvertierung

### Ursache für blockierte Formulare
Deutsche Tastatur-Layouts nutzen das Komma (`,`) als Dezimaltrenner. Kotlins Standardmethode `.toDoubleOrNull()` wirft bei Kommas `null` zurück. Dadurch schlug die Formular-Validierung unbemerkt fehl und der Speichern-Button blieb inaktiv.

### Lösung
Alle Eingabefelder ersetzen Kommas vor der Konvertierung durch Punkte:
```kotlin
val amount = amountText.replace(',', '.').toDoubleOrNull()
```
Dadurch funktioniert die Validierung und der Eintrag unabhängig von der Systemsprache der Tastatur.
