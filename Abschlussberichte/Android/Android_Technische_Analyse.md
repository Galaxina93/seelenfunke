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
2. **Pfadkonfiguration (`res/xml/file_paths.xml`):** Streng auf isolierte Unterordner statt auf das gesamte Cache-Root begrenzt:
   ```xml
   <paths xmlns:android="http://schemas.android.com/apk/res/android">
       <cache-path name="camera_captures" path="camera_captures/" />
       <cache-path name="mail_attachments" path="mail_attachments/" />
       <cache-path name="calendar_exports" path="calendar_exports/" />
       <cache-path name="secure_downloads" path="secure_downloads/" />
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

---

## 7. Fokussiertes Startseiten-Cockpit (`getUltimateCommand()`), Deep-Link-Fix & Parallele Datenpipeline

### 7.1 Startseiten-Fokus (`ZentrumScreen.kt` & `ZentrumViewModel.kt`)
Die Startseite (`ZentrumScreen`) konzentriert sich im aufgeräumten „Alle Kacheln“-Layout auf die drei wichtigsten Alltagsfunktionen:
1. **Nächster Schritt (`getUltimateCommand()` via `GET /api/funki/command`):** Zeigt in einem Satz die aktuell wichtigste Handlungsempfehlung (`UltimateCommandCard`) und navigiert per Tap direkt zur passenden Ansicht (`action_route`).
2. **Verfügbares Monatsguthaben (`FinanceDashboardCard`):** Sofortiger Überblick über das im aktuellen Monat verfügbare Budget (`kpis.available`).
3. **Schnelle Belegerfassung (`QuickEntryForm`):** Direkter Zugriff auf den KI-Beleg-Scanner (Kamera/Datei) und manuelle Schnellbuchungen.

### 7.2 Behebung des Organizer Sub-Tab Deep-Link-Bugs (`AppNavigation.kt` & `OrganizerScreen.kt`)
* **Ursache:** In `OrganizerScreen.kt` verhinderte die Bedingung `if (initialTab != 0)` innerhalb von `LaunchedEffect(initialTab)`, dass der `HorizontalPager` beim Klick auf die **To-do Liste** (`initialTab = 0`) auf den Tab `Aufgaben` zurückscrollte, wenn zuvor der Tab `Kalender` (`1`) aktiv war. Zudem teilten sich Aufgaben-, Routine- und Kalender-Kachel denselben parameterlosen Callback.
* **Lösung:** `onNavigateToOrganizer(subTab: Int)` übergibt nun explizit den Ziel-Sub-Tab (`0 = Aufgaben`, `1 = Kalender`, `2 = Routine`, `3 = Einkauf`, `4 = E-Mail`) zusammen mit einem inkrementierenden `organizerNavTrigger`. In `OrganizerScreen.kt` reagiert `LaunchedEffect(initialTab, navTrigger)` auf jede Navigation und scrollt den Pager zuverlässig auf den angeforderten Sub-Tab.

### 7.3 Parallele Coroutine-Datenpipeline & Optimistische UI-Updates
* **Parallele Initialisierung:** `ZentrumViewModel`, `FinanceViewModel` und `OrganizerViewModel` bündeln alle API-Aufrufe in einem `coroutineScope { async { ... } }`-Block. Dadurch reduziert sich die Ladezeit von der Summe aller Einzelanfragen ($\sum T_i$) auf die Dauer des langsamsten Einzel-Requests ($\max(T_i)$).
* **Optimistisches Task-Toggling:** Beim Abhaken einer Aufgabe wird der UI-State sofort im Arbeitsspeicher aktualisiert (`< 16 ms`) und anschließend `getUltimateCommand()` im Hintergrund neu abgefragt, damit sich die „Nächster Schritt“-Empfehlung sofort anpasst.

---

## 8. Wecker, Timer, Stoppuhr, Smart-Home KLAP v2 & Tagesroutine-Sync
* **Kompakte 7-Icon-Organizer-Navigation (`OrganizerScreen.kt`):** Die obere Organizer-Leiste nutzt 7 kompakte Icons (`Aufgaben`, `Kalender`, `Routine`, `Einkauf`, `E-Mail`, `Kontakte`, `Wecker`), ergänzt durch eine direkte **„Wecker“**-Kachel auf der Startseite (`ZentrumScreen.kt`).
* **3 Unter-Tabs (`AlarmTabScreen.kt`):** Integriert `Wecker`, `Timer` (2-Spalten-Grid mit Kreis-Fortschrittsring) und `Stoppuhr` (`00:00.00` mit Rundenzähler) im konsistenten Dark- & Light-Mode.
* **Smart-Home & KI-Briefing (`FunkenwerkAlarmManager.kt` & `SmartAlarmReceiver.kt`):** Schaltet beim Klingeln lokale TP-Link Tapo WLAN-Steckdosen über das lokale KLAP v2 AES-128-CBC-Protokoll (`192.168.188.x`), spricht ein deutsches KI-Morgen-Briefing (`TextToSpeech`) und synchronisiert die Weck- und Aufstehzeiten als JSON-Paket via `POST /api/funki/routine/alarm-sync` mit `/admin/routine` (Detailbericht: [`Android_Wecker_SmartHome_und_Routine_Sync.md`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/Android/Android_Wecker_SmartHome_und_Routine_Sync.md)).

---

## 9. E-Mail & KI-Trichter 1:1 Parität mit Funkenwerk Windows Suite & Webportal

### 9.1 Ursache für `???`-Artefakte und rohen CSS-/HTML-Code in E-Mails
1. **Korrupte UTF-8-Emojis (`???` / `????`) in Betreffzeilen:** Ältere oder durch IMAP-Header-Konvertierung beschädigte E-Mails enthielten `???` bzw. `????` anstelle von 4-Byte-UTF-8-Emojis sowie unkodierte RFC-2047-Strings (`=?UTF-8?B?...?=`).
2. **Roher CSS-Code in Vorschau & Leseansicht:**
   * Viele HTML-E-Mails speicherten `<style>`-Blöcke (`a {text-decoration: none;}`, `@import url(...)`, `body { font-family:... }`) direkt in `body_plain`.
   * Zudem nutzte `EmailDetailDialog` zuvor `HtmlCompat.fromHtml(htmlContent)` in einer Android `TextView`. Da `HtmlCompat.fromHtml` den Textinhalt innerhalb von `<style>...</style>`-Tags nicht entfernt, wurde der gesamte CSS-Code sichtbar über der E-Mail ausgegeben.

### 9.2 Mehrstufige Sanitizer-Pipeline (Backend + Android)
* **Backend (`FetchMailsCommand.php` & `routes/api/emails.php`):**
  * `repairCorruptedSubjectIcons` repariert alle 17 bekannten Shop- & System-Betreffmuster (`💰 [NEUE BESTELLUNG]`, `✅ [ANGEBOT ANGENOMMEN]`, `📦 Deine Bestellung`, `🧾 Deine Rechnung`, `🎁 Juhu!`, `✨ Dein Geschenkgutschein`, `🚚 Gute Nachrichten!`, `🛒 Vergiss deine Unikate nicht`, `📸✨ Wie gefällt dir dein Unikat` u. v. m.) und entfernt verbleibende `???`-Präfixe/Suffixe sowohl beim IMAP-Import als auch on-the-fly in `GET /api/funki/emails/messages`.
  * `extractCleanPlainText` erkennt verschmutzte `body_plain`-Felder (`@import url`, `font-family:`, `<!DOCTYPE`, `<style`) und extrahiert stattdessen strukturierten Klartext aus `body_html`. Alle 223 betroffenen Bestands-E-Mails in `mail_messages` wurden per Datenbank-Migration bereinigt.
* **Android (`EmailTextSanitizer` in `EmailViewModel.kt`):**
  * 1:1 Kotlin-Port der Windows-Desktop-Sanitizer-Engine (`decodeRfc2047`, `repairSubject`, `cleanSenderName`, `formatCleanEmailBody`, `stripHtmlToStructuredText`, `getPlainSnippet`).
  * Im E-Mail-Reader (`EmailDetailDialog`) wird standardmäßig die saubere, selektierbare Textansicht (`📄 Bereinigter Text`) im nativen Dark-/Light-Mode gerendert; optional kann per Toggle (`🌐 Original HTML-Ansicht`) auf eine echte `WebView` umgeschaltet werden.

### 9.3 1:1 Funktionsumfang (KI-Trichter, Anhänge, Kontextmenü & Compose)
* **Globale KI-Trichter-Ansichten (`✨ Verarbeitet` & `📦 Archiv`):** Übergreifende Ansicht aller durch den KI-Trichter analysierten E-Mails inkl. Zähler-Badges (`importantCount`, `totalCount`, `archivedCount`), dynamischer Filter-Pills (`Alle`, `🔴 Wichtig`, `⚪ Normal`, `🏷 Kategorie`), Inline-`👉 ActionHint` und direktem **`✓ Erledigt`**-Button (`PUT /api/funki/emails/messages/{id}/resolve`).
* **KI-Sortierung & IMAP-Sync:** Direkte Buttons für `✨ KI-Trichter` (`POST /api/funki/emails/ai-sort`) und `↻ Sync` (`POST /api/funki/emails/sync`) im Header.
* **E-Mail-Anhänge (`📎 Anhänge`):** Anzeige aller Anhänge mit Dateigröße im Reader sowie 1-Tap-Download & Öffnen über `OkHttpClient` + `FileProvider` (`GET /api/funki/emails/attachments/{id}`).
* **Vollständiges 3-Punkte-Kontextmenü & Reader-Aktionen:** `Öffnen`, `Aufgabe erledigt / Als Wichtig markieren`, `Gelesen / Ungelesen`, `↩ Antworten` (inkl. Zitat), `↪ Weiterleiten` (inkl. Übernahme bestehender Anhänge), `📝 Entwurf speichern & weiterbearbeiten`, `📋 Als Aufgabe (To-Do) anlegen`, `👤 Absender als Kontakt speichern`, `📁 Verschieben nach...`, `⚡ Absender-Regel (Auto-Ordner)...`, `🛡 Als Spam markieren` und `🗑 Löschen`.

---

## 10. Umfassende Sicherheits-Härtung (Security-Audit & Defense-in-Depth)

Im Rahmen des vollständigen Sicherheits-Audits wurden sämtliche Angriffsflächen zwischen Android-App, Homescreen-Widgets, lokalem Smart-Home-Netz und Laravel-API geschlossen:

1. **Zero Hardcoded Secrets & Hardware-Keystore-Zwang ([`ServiceLocator.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/di/ServiceLocator.kt), [`MusicPlayerManager.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/MusicPlayerManager.kt), [`FunkenwerkAlarmManager.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/FunkenwerkAlarmManager.kt)):**
   * Vollständige Entfernung hartkodierter Zugangsdaten und Fallback-Tokens sowie Entfernung von `gemini_tts_key` aus `/api/funki/routine/alarm-sync`.
   * Keine Passwort-Persistenz auf dem Gerät (`KEY_SAVED_PASSWORD` wird beim Start bereinigt).
   * Alle sensitiven Werte (`auth_token`, `gemini_api_key`, `fcm_token`, `saved_email`, `tapo_email`, `tapo_pass`, `_fw_sec_token`) werden ausschließlich AES-256-GCM-verschlüsselt über den `AndroidKeyStore` gespeichert; ein Klartext-Fallback in `SharedPreferences` existiert nicht mehr (nur flüchtiger RAM-Fallback bei Hardware-Keystore-Fehlern).
2. **TLS-Standard, Host-Allowlist & Geschützte Datei-Downloads ([`SecureFileOpener.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/SecureFileOpener.kt)):**
   * Standard-Endpunkt für physische Geräte ist `https://stage.mein-seelenfunke.de/api/`; benutzerdefinierte Server-URLs werden strikt gegen eine Host-Allowlist geprüft (`mein-seelenfunke.de`, `stage.mein-seelenfunke.de` sowie explizite lokale Entwicklungs-Hosts).
   * Gemini-Live-WebSockets erzwingen bei HTTPS automatisch `wss://` inklusive Host-Abgleich.
   * Keine Sanctum-Bearer-Tokens mehr in Browser-Query-Strings (`?token=`): Belege, Verträge, Aufgaben-Dateien und Laser-SVGs werden über `SecureFileOpener.openAuthenticatedApiFile` per `Authorization: Bearer`-Header in `cacheDir/secure_downloads/` (25 MB Limit, Pfad-Kanonisierung, 15-Minuten-Auto-Purge) geladen und per `FileProvider` geöffnet.
   * Lokale Tapo-Steckdosen-IPs werden strikt auf private RFC-1918-IPv4-Adressen geprüft.
3. **Kryptografische Intent-Signierung & Widget-/Lockscreen-Schutz ([`SecurityIntentHelper.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/SecurityIntentHelper.kt), [`AndroidManifest.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/AndroidManifest.xml)):**
   * Alle 6 `AppWidgetProvider` akzeptieren im Manifest nur noch `android.appwidget.action.APPWIDGET_UPDATE`. Alle internen Widget- und Notification-`PendingIntent`s werden mit einem 256-Bit-Keystore-Token (`MessageDigest.isEqual`) signiert und in `onReceive`, `TasksWidgetActionActivity` sowie `MainActivity.handleIntent` verifiziert.
   * Sämtliche Widget-Dialog-Activities prüfen über `guardWidgetDialogActivity`, dass das Gerät entsperrt (`!isDeviceLocked`) und eine gültige Sitzung vorhanden ist.
4. **Biometrische App-Sperre, `FLAG_SECURE` & Echter Server-Logout ([`AppNavigation.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/navigation/AppNavigation.kt), [`AuthRepository.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/data/repository/AuthRepository.kt)):**
   * Bevorzugung von `BIOMETRIC_STRONG`, automatische Nachsperre nach 3 Minuten im Hintergrund, `FLAG_SECURE` im gesperrten Zustand sowie `setRecentsScreenshotEnabled(false)` ab Android 13.
   * Beim Logout wird `POST /api/logout` aufgerufen (widerruft das aktuelle Sanctum-Token und entfernt das `fcm_token`), laufende Dienste/WebSockets werden gestoppt und sämtliche lokalen Widget- und Datei-Caches restlos gelöscht.
5. **Gehärtete E-Mail-WebView, Upload-Limits, Redacted Notifications & R8-Release-Build:**
   * HTML-E-Mails rendern in einer strikt isolierten `WebView` (`allowFileAccess = false`, `allowContentAccess = false`, Cookies aus, strenge Content-Security-Policy, Filterung gefährlicher Tags/Event-Handler, Blockierung unverschlüsselter Sub-Requests).
   * Serverseitige Sperrliste ausführbarer Dateiendungen in `POST /api/funki/files/upload`, strikte YouTube-ID-Validierung + `--`-Argumenttrennung in `GET /api/funki/music/stream/{id}`, 25-MB-/40-MB-Streaming-Limits in der App, `VISIBILITY_PRIVATE` mit neutraler Sperrbildschirm-Vorschau für Benachrichtigungen, Debug-Gating in `AppLogger` sowie aktivierte R8-Minifizierung (`isMinifyEnabled = true`, `isShrinkResources = true`) mit Logcat-Stripping im Release-Build.




---

## 11. Direkter Mehrseiten-PDF-Scanner, Mehrfachdatei-Auswahl & Share-Target für den Werkstatt-Mini-PC (`📥 In Trichter`, Version `1.3.2`)

* **2-Wege-Umschalter & Mehrfachdatei-Auswahl in `BELEG & SCHNELLEINTRAG` ([`FinanceScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/FinanceScreen.kt), [`FinanceViewModel.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/FinanceViewModel.kt), [`QuickEntryWidgetActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/QuickEntryWidgetActivity.kt)):**
  * Schlanker Pill-Toggle (`🧾 Beleg buchen` vs. `📥 In Trichter`) im Homescreen-Widget-Dialog und im Finanzzentrum ohne erklärende Subtexte.
  * **Mehrfachdatei-Picker (`ActivityResultContracts.GetMultipleContents()`):** Über `+ Dateien` lassen sich beliebig viele Dateien gleichzeitig vom Smartphone auswählen (mehrere PDFs, Fotos, DOCX/XLSX oder gemischte Pakete bis je 25 MB), einzeln per `✕` aus der Warteschlange entfernen oder bei 2+ Bildern optional mit einem Tipp (`Als 1 PDF`) zu einem mehrseitigen Dokument bündeln.
  * **Mehrseiten-Kamera-Scanner:** Über `Seite scannen` / `+ Seite (N)` lassen sich `1..N` Briefseiten nacheinander abfotografieren und lokal per [`ImageUtils.buildPdfFromJpegPages`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/ImageUtils.kt) zu einer mehrseitigen A4-PDF (`Scan_YYYY-MM-DD_HHmmss.pdf`) zusammenfügen (auch kombiniert mit zusätzlichen ausgewählten Dateien in einem Sammel-Upload `sendMultipleToMiniPcTrichter`).
* **Systemweites Android Share-Target ([`TrichterShareActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/TrichterShareActivity.kt), [`AndroidManifest.xml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/AndroidManifest.xml)):**
  * Nimmt `ACTION_SEND` und `ACTION_SEND_MULTIPLE` (`application/pdf`, `image/*`, bis zu 20 Dateien pro Share-Vorgang) aus Mail-, Browser-, Dateimanager- oder Messenger-Apps direkt als Einzel- oder Sammelpaket im Modus `📥 In Trichter` entgegen, während `QuickEntryWidgetActivity` weiterhin `exported="false"` bleibt und `extractSafeExternalUris` eigene `fileprovider`-/`dataDir`-Pfade gegen Confused-Deputy-Angriffe blockiert.
* **Verschlüsselte Zero-Port-Forwarding-Übergabe ([`FinanceApi.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/data/api/FinanceApi.kt), [`MiniPcTrichterRelayService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/MiniPcTrichterRelayService.php)):**
  * Übergibt jede ausgewählte Datei per `POST /api/funki/minipc-trichter/enqueue` (`Crypt::encryptString` at rest), sodass der Werkstatt-Mini-PC alle Pakete beim nächsten Pulse (`GET /api/shop/minipc-trichter/pull/{id}` + `DELETE /api/shop/minipc-trichter/ack/{id}`) automatisch in `/home/alina/Meine_Daten/00_Eingang_Trichter` abholt, per OCR/FTS5 einsortiert und sofort rückstandslos vom Webserver löscht.

---

## 12. Proaktiver Cross-Platform Error-Sentinel & Auto-Heal Telemetrie (Version `1.3.3`, `versionCode = 7`)

* **Zentrale Android-Fehler- & Selbstheilungs-Meldung ([`ErrorSentinelReporter.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/ErrorSentinelReporter.kt)):**
  * Überträgt App-Fehler und automatische Selbstheilungen (`platform = "android"`) asynchron an `POST /api/system/error-ingest` im Seelenfunke-Portal (`/admin/logs` und `/admin/dashboard` -> `Log`).
  * **3 Erfassungspunkte:** Globaler `Thread.UncaughtExceptionHandler` in [`MainActivity.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/MainActivity.kt) (`uncaught_crash` mit synchronem Offline-Queue-Persist vor Prozessende), produktiver Hook in [`AppLogger.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/AppLogger.kt) (`AppLogger.e(...)` feuert auch im minifizierten Release-Build kontrolliert an den Sentinel) sowie Netzwerk-Selbstheilung in [`ServiceLocator.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/di/ServiceLocator.kt) (`autoHealed = true`, sobald ein unerreichbarer lokaler LAN-Host automatisch auf die öffentliche HTTPS-Domain umschaltet).
  * **Datenschutz, Cooldown & Offline-Puffer:** Automatische Maskierung sensibler Tokens (`Bearer`, `api_key`, `password`, `AIza`), 60-Minuten-Deduplizierungs-Cooldown pro SHA-256-Fingerprint im RAM sowie verschlüsselte Offline-Warteschlange (bis zu 30 Events), die beim nächsten erfolgreichen App-Start automatisch nachgesendet wird.

---

## 13. Twilio-Freier Funkira Whitelist-Telefon-Assistent (`CallScreeningService` + Bluetooth-HFP-Gateway, Version `1.4.0`, `versionCode = 8`)

* **0,00 € Native Mobilfunk-Architektur ohne Drittanbieter ([`FunkiraCallScreeningService.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/services/FunkiraCallScreeningService.kt), [`FunkiraPhoneConciergeService.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/services/FunkiraPhoneConciergeService.kt), [`PhoneConciergeManager.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/PhoneConciergeManager.kt)):**
  * `FunkiraCallScreeningService` prüft eingehende Mobilfunk-Anrufe in `< 20 ms` rein lokal gegen den Whitelist-Cache (`PhoneConciergeManager.matchIncomingWhitelistedCaller`, E.164-Normalisierung + 8-stelliger Suffix-Abgleich). Unbekannte Nummern und nicht freigegebene Kontakte werden niemals angerührt (`respondToCall` lässt das Smartphone normal klingeln).
  * Bei einem Whitelist-Kontakt (standardmäßig voraktiviert für `Jan Steinhauer` / `Papa`) wartet `FunkiraPhoneConciergeService` die eingestellte Klingelzeit (`Sofort`, `8 Sek.`, `12 Sek.`, `18 Sek.`) in `500 ms`-Intervallen ab. Nimmt Alina selbst ab (`CALL_STATE_OFFHOOK`) oder legt der Anrufer auf (`CALL_STATE_IDLE`), bricht der Dienst sofort eingriffsfrei ab.
  * Nimmt Alina nicht ab, akzeptiert der Dienst das Gespräch über `TelecomManager.acceptRingingCall()`, routet den Telefon-Audiostream per `AudioManager.setCommunicationDevice` (`TYPE_BLUETOOTH_SCO`) direkt an den gekoppelten `Funkenwerk-MiniPC`, signalisiert `accepted` an `POST /api/system/phone-concierge/signal` und legt nach Funkiras Gesprächsabschluss (`hangup_requested`) automatisch per `TelecomManager.endCall()` auf (inkl. lokaler Push-Zusammenfassung `📞 Anrufnotiz von <Name>`).
* **Clean-Design Steuerung in den Einstellungen ([`SettingsScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/SettingsScreen.kt), [`SettingsViewModel.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/SettingsViewModel.kt)):**
  * Eigene Kachel **`Telefon-Assistent`** mit `!`-Erklärungsdialog (`Icons.Default.Info`), Hauptschalter, 4 Klingelzeit-Chips (`Sofort`, `8 Sek.`, `12 Sek.`, `18 Sek.`), 1-Tap-Einrichtung für Telefon-Berechtigungen + `RoleManager.ROLE_CALL_SCREENING` sowie direkter Kontakt-Whitelist-Liste (`PUT /api/funki/contacts/{id}/call-whitelist`).

---

## 14. Vollständige Trichter-Anbindung auf der Startseite & Eindeutige Erfolgs-/Fehlerrückmeldung (`📥 In Trichter senden`, Version `1.4.1`, `versionCode = 9`)

* **Ursachenbehebung für fehlende Aktion & Rückmeldung:**
  1. **Startseite (`ZentrumScreen.kt` + `ZentrumViewModel.kt`):** Die Karte `Beleg erfassen & Schnelleintrag` auf der Startseite übergab zuvor weder `onSendToTrichter` noch `onSendMultipleToTrichter` an `QuickEntryForm` (Default-No-Op-Lambda). Beide Methoden inklusive Multi-Datei-Warteschlange und `trichterFeedback`-State wurden vollständig in [`ZentrumViewModel.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/ZentrumViewModel.kt) und [`ZentrumScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/ZentrumScreen.kt) verdrahtet.
  2. **Finanzzentrum (`FinanceScreen.kt`):** Das sofortige Zuklappen (`showQuickEntryDialog = false`) vor Upload-Beginn wurde entfernt, sodass der Live-Sendeindikator (`Wird gesendet...`) sichtbar bleibt und ausgewählte Dateien im Fehlerfall für einen erneuten Versuch erhalten bleiben.
* **Doppelte visuelle Rückmeldung an allen 4 Einstiegspunkten (`ZentrumScreen`, `FinanceScreen`, `QuickEntryWidgetActivity`, `TrichterShareActivity`):**
  * **Inline-Statuskarte direkt im Trichter-Formular:** Zeigt nach Abschluss unmittelbar ein grünes Bestätigungsbanner (`✅ ...`, leert automatisch die bereits übertragenen Dateien) bzw. im Fehlerfall ein rotes Diagnosebanner (`⚠️ ...` inkl. klarem HTTP-Code `401`/`404`/`413`/Timeout aus [`FinanceRepository.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/data/repository/FinanceRepository.kt)).
  * **Systemweiter `Toast.LENGTH_LONG` (+ Snackbar im Finanzzentrum):** Meldet unübersehbar `✅ ...` bei Erfolg und `⚠️ ...` bei Fehlern.

---

## 15. Musik-Engine, Cold-Start-Widget-Wiedergabe & DEX-Register-Entflechtung (`Version 1.4.2`, `versionCode = 10`)

### 15.1 Ursache für den Absturz beim Öffnen des Musik-Tabs (`java.lang.VerifyError`)
* **Fehlerbild auf Android 16 (Google Pixel 9 Pro XL):** Beim Tippen auf das Musik-Icon (`Tab 5`) in der oberen Organizer-Leiste scrollte `HorizontalPager` mittels `animateScrollToPage(5)` über den benachbarten E-Mail-Tab (`Tab 4`, `EmailTabContent`).
* **Technische Ursache:** Die monolithische Composable-Funktion `EmailTabContent` (über 1.030 Zeilen in einer einzigen Methode) überschritt im Debug-DEX-Bytecode das Dalvik-Registerlimit für `copy-cat1` (`[0x985] copy-cat1 v1<-v301 type=Reference: androidx.compose.runtime.Composer`). Der ART-Bytecode-Verifier lehnte die Klasse `EmailTabContentKt` zur Laufzeit mit `java.lang.VerifyError` ab, wodurch die App beim Wechsel in den Musik- oder E-Mail-Tab sofort abstürzte.
* **Lösung ([`EmailTabContent.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/EmailTabContent.kt) & [`OrganizerScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/OrganizerScreen.kt)):**
  1. Vollständige modulare Aufteilung von `EmailTabContent` in kompakte Teil-Composables (`EmailTopHeaderRow`, `EmailStatusBannerRow`, `EmailAccountsAndFunnelSection`, `EmailAccountFoldersRow`, `EmailFunnelFilterPillsRow`, `EmailSearchAndUnreadRow`, `EmailMessageListArea`, `EmailOverlayDialogs`). Die vollständige ART-Prüfung (`cmd package compile -m verify -f`) läuft mit `0` Verifikationsfehlern durch.
  2. Direkte Tab-Sprünge in [`OrganizerScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/OrganizerScreen.kt) nutzen bei nicht benachbarten Reitern `pagerState.scrollToPage(index)` statt über dazwischenliegende Seiten zu animieren.

### 15.2 Synchroner Cold-Start-Katalog & Robuste `.m4a`-Wiedergabe ([`MusicPlayerManager.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/MusicPlayerManager.kt), [`MusicTabContent.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/MusicTabContent.kt), [`MasterWidgetProvider.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/widget/MasterWidgetProvider.kt))
* **Sofort-Start aus Homescreen-Widgets (`ensureCatalogLoadedFast`):** Beim Kaltstart über das Musik- oder Master-Widget lädt `ensureCatalogLoadedFast(context)` den lokalen Playlist- und Metadaten-Cache (`playlists_cache.json` + `orphan_meta_cache.json`, 362 gecachte `.m4a`-Titel) synchron in ca. `2 ms` und startet `MusicPlaybackService.startOrUpdate(appCtx)` noch innerhalb des `BroadcastReceiver.onReceive`-Zeitfensters (verhindert Android-15/16 Foreground-Service-Sperren nach Prozess-Ruhezustand).
* **Mutex-geschützte `MediaPlayer`-Pipeline:** Explizites FileDescriptor-Offset/Length-Handling (`setDataSource(fis.fd, 0L, localFile.length())` mit Pfad-Fallback für ISO-BMFF/DASH-`.m4a`-Container), Container-Header-Prüfung (`ftyp`, `ID3`, ADTS, `OggS`, `RIFF`, `fLaC`), Schutz intakter Offline-Dateien vor versehentlichem Löschen sowie konsistentes Play/Pause-Toggling ohne ungewolltes Autoplay beim bloßen Filtern von Playlists (`autoPlayRandom = isPlaying`).
* **Deterministischer Bearer-Resolver ([`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php)):** Explizites `$request->setUserResolver(fn() => $user)` im Bearer-Token-Zweig von `/api/funki/music/stream/{id}` für konsistente Nutzerauflösung beim Audio-Streaming.

---

## 16. Autarke Funkira Smartphone-Sekretärin & Live-AI Verbindungs-Resilienz (`Version 1.5.0`, `versionCode = 12`)

* **100 % Unabhängig vom Werkstatt-Mini-PC ([`StandalonePhoneSecretaryEngine.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/services/StandalonePhoneSecretaryEngine.kt), [`FunkiraPhoneConciergeService.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/services/FunkiraPhoneConciergeService.kt), [`PhoneConciergeManager.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/PhoneConciergeManager.kt)):**
  * Ist `Funkenwerk-MiniPC` nicht aktiv per Bluetooth-SCO verbunden (z. B. unterwegs, nachts oder am Wochenende im Energiesparmodus), führt das Smartphone das Sekretärinnen-Gespräch komplett eigenständig über Gemini Live durch, ohne den Mini-PC in der Werkstatt aufzuwecken (und fällt selbst bei aktivem Bluetooth innerhalb von `4,5 s` automatisch auf das Smartphone zurück, falls der Mini-PC nicht übernimmt).
  * **Zero-Delay Annahme, Android-14+-FGS-Split & Audio-Pipeline:** Wärmt die Live-Zugangsdaten bereits `2,5 s` vor Ablauf der Klingelzeit vor (`preWarmCredentials`), startet während `RINGING` konform zu Android 14/15/16 zunächst ausschließlich mit `FOREGROUND_SERVICE_TYPE_PHONE_CALL` und erweitert erst nach `acceptRingingCall()` (`OFFHOOK`) auf `FOREGROUND_SERVICE_TYPE_PHONE_CALL | FOREGROUND_SERVICE_TYPE_MICROPHONE` (`resolveForegroundServiceType`, kein `ForegroundServiceStartNotAllowedException`-Absturz im Hintergrund). Auch bei abgelaufenem Portal-Token arbeitet [`FunkiraCallScreeningService.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/services/FunkiraCallScreeningService.kt) nahtlos über den lokalen Keystore-Gemini-Key oder das deutsche `TextToSpeech`-Notfallnetz (`STREAM_VOICE_CALL`) weiter. Verbindet direkt über `direct_ws_url` mit Gemini-3.1-nativem `realtimeInput.text`-Kickoff (`~514 ms` bis zum ersten `24 kHz`-Sprach-Chunk), `1,8 s` Anti-Barge-In-Schutz für die Begrüßung, sofortigem `AudioTrack`-Flush bei `interrupted` sowie bis zu `16,0 s` Ausklingfenster für die herzliche Verabschiedung nach `phone_secretary_finish`.
  * **Freundliche Sekretärinnen-Persona & Offline-Sicherheit:** Funkira begrüßt Whitelist-Anrufer auf Deutsch als Alinas persönliche Assistentin, nimmt alle Infos auf, bestätigt herzlich, dass Alina die Nachricht sofort bekommt, und ruft ausschließlich das isolierte Zero-PII-Tool `phone_secretary_finish(summary, urgency, callback_requested)` auf (inklusive Transkript-Sicherheitsnetz bei vorzeitigem Auflegen). Das Ergebnis wird per `POST /api/system/phone-concierge/complete` als Dashboard-To-Do angelegt oder bei Funkloch AES-256-GCM-verschlüsselt in `PendingPhoneSecretaryNote` gepuffert.
* **Gehärtete Live-AI WebSocket- & DNS-Resilienz ([`FunkiraLiveViewModel.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/FunkiraLiveViewModel.kt), [`AiRepository.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/data/repository/AiRepository.kt), [`ErrorSentinelReporter.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/ErrorSentinelReporter.kt)):**
  * `isTrustedLiveWebSocketHost` erlaubt Subdomains derselben Seelenfunke-Apex-Domain (`api-live-bridge.mein-seelenfunke.de` von `stage.mein-seelenfunke.de`), `OkHttpClient` bevorzugt IPv4-DNS mit Keepalive-Pings, `getLiveCredentials` wiederholt transiente Netzwerkfehler bis zu 3-mal (`[AUTO-GEHEILT]`) und `FunkiraLiveViewModel` schaltet bei Bridge- oder Stage-Störungen nahtlos auf `direct_ws_url` bzw. den lokalen Keystore-Key um. Abgesichert durch [`FunkiraResilienceAndSecretaryTest.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/test/java/de/meinseelenfunke/app/FunkiraResilienceAndSecretaryTest.kt).

---

## 17. Privater 1-Click Over-the-Air (OTA) Update-Kanal, 4096-Bit-RSA-Release-Key & R8-Timer-Selbstheilung (`Version 1.6.0`, `versionCode = 13`)

### 17.1 Behebung des R8-Timer-Absturzes ([`proguard-rules.pro`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/proguard-rules.pro), [`FunkenwerkAlarmManager.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/FunkenwerkAlarmManager.kt))
* **Ursache:** In `proguard-rules.pro` war versehentlich `SmartTimerItem` statt `TimerPresetItem` eingetragen. Im R8-minifizierten Release-Build wurden die Felder von `TimerPresetItem` umbenannt, wodurch Gson via `Unsafe.allocateInstance` Objekte mit `id = null` erzeugte und `LazyVerticalGrid` beim Tippen auf einen Timer sofort mit `IllegalArgumentException` abstürzte.
* **Lösung:** Vollständige Keep-Regeln für `TimerPresetItem`, `SmartCameraDevice`, `MusicPlaylistDto`, `MusicTrackDto`, `MusicImportProgress`, `PendingPhoneSecretaryNote`, `CachedWhitelistContact`, `SentinelQueuedEvent` und `OtaReleaseManifest`, explizite `@SerializedName`-Annotationen sowie eine selbstheilende Deserialisierung (`parseTimersJsonSelfHealing` + `normalizeTimers`) für bereits korrumpierte `SharedPreferences`- oder Backend-Bestände.

### 17.2 Dedizierter 4096-Bit-RSA-Enterprise-Signing-Key (`funkenwerk-release.jks`)
* **Ablösung des `debug.keystore`:** Statt des öffentlichen Standard-Debug-Zertifikats (`CN=Android Debug`) nutzt der Release-Build in [`app/build.gradle.kts`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/build.gradle.kts) nun automatisch einen eigenen PKCS12-Keystore (`4096-Bit RSA`, `SHA256withRSA`, Gültigkeit 35 Jahre, APK Signature Scheme `v1 + v2 + v3`, Zertifikats-SHA-256 `e4c85cdd79648fa19430c91b47e1db036544d60d2fa6136a0c5106300c849064`).
* **Isolierte Schlüsselhaltung:** `funkenwerk-release.jks` und `keystore.properties` liegen außerhalb aller Git-Repositories unter `C:\Users\konta\.android\signing\` (NTFS-ACL exklusiv für den Windows-Benutzer gesperrt) sowie verschlüsselt gesichert im Backup-Tresor `08_Tresor_Backups`.

### 17.3 Privater Laravel-OTA-Tresor & Dual-Channel-Verteilung ([`AndroidReleaseService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/System/AndroidReleaseService.php), [`system_ota.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/system_ota.php), [`SystemShopConfig.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemShopConfig.php))
* **Kein öffentlicher Link & Race-freie Dateihaltung:** Die signierten Release-Pakete lagern ausschließlich im nicht-öffentlichen Verzeichnis `storage/app/private/releases/android/` als unveränderliche, versionierte Dateien (`release-b{code}-{sha12}.apk` plus `funkenwerk-latest.apk` und `manifest.json`), geschützt durch ein exklusives Datei-Lock (`.publish.lock` mit `flock(LOCK_EX)`) und automatische Bereinigung abgelöster Vorversionen.
* **Kryptografische Server-Prüfung vor Veröffentlichung:** `AndroidReleaseService::storeVerifiedRelease` prüft vor der Übernahme in den Tresor die ZIP-Magic-Bytes (`PK\x03\x04`), die `ZipArchive`-Struktur (`AndroidManifest.xml` + `classes.dex`), Größenlimits (`512 KB .. 95 MB`), Anti-Rollback-`version_code`, parst binär den Android-Signaturblock (`APK Sig Block 42`, APK Signature Scheme `v2` `0x7109871a` & `v3` `0xf05368c0`), erzwingt zeitkonstantes X.509-Zertifikatspinning (`PINNED_CERT_SHA256`) und verifiziert die kryptografische RSA-Signatur (`0x0103` SHA-256 / `0x0104` SHA-512) per `openssl_verify` direkt gegen das gepinnte Zertifikat.
* **Isolierte Rate-Limiter ([`AppServiceProvider.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Providers/AppServiceProvider.php)):** Dedizierte benannte Buckets (`ota-check` 60/Min., `ota-download` 20/Min., `ota-publish` 10/Min.) verhindern jede gegenseitige Beeinflussung mit anderen API-Endpunkten.
* **2 Abrufwege exklusiv für authentifizierte Admins:**
  1. **Web-Portal (`/admin/configuration` -> Tab `System`):** Clean-Design-Karte *„Funkenwerk Android App“* in [`system_tab.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/system/system-config-partials/system_tab.blade.php) mit 15-minütiger `URL::temporarySignedRoute` (`auth:admin` + `signed` + `throttle:ota-download`) für den Erstwechsel oder ein neues Smartphone sowie direktem Release-Upload.
  2. **Automatischer App-Start-Check & 1-Tap-Dialog ([`AppNavigation.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/navigation/AppNavigation.kt), [`AppUpdateManager.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/util/AppUpdateManager.kt) & [`SettingsScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/SettingsScreen.kt)):** Bei jedem App-Start (Cold-Start nach Login/Biometrie sowie beim Wiederöffnen aus dem Hintergrund nach `>= 10 s`) prüft `AppUpdateManager.onAppStartCheck` im Hintergrund `GET /api/funki/system/app-update/check` und blendet bei einer neueren Version automatisch den Clean-Design-Dialog **`Update vX.Y.Z`** mit **`Installieren`** und **`Später`** ein (blockiert niemals aktive Wecker oder Live-Telefonate). Beim Tippen auf **`Installieren`** lädt die App das Paket per `Authorization: Bearer`-Header (`followRedirects = false`, strikte Host-Allowlist, `AtomicBoolean`-Concurrency-Schutz) in `cacheDir/apk_updates/`, verifiziert vor dem Start des Android-System-Installers **4 Sicherheits-Gates** (exakte Dateigröße + Magic-Header, zeitkonstanter SHA-256-Hashvergleich per `MessageDigest.isEqual`, Paketname + Anti-Downgrade-`versionCode` sowie hartes Pinning gegen das 4096-Bit-RSA-Release-Zertifikat, bei Ablehnung sofortiges Zero-Byte-Wiping via `securePurgeFile`) und räumt erledigte oder abgelaufene `.apk`-Pakete beim App-Start und Logout rückstandslos auf.
* **1-Befehl Release-Pipeline & Vollständige Testabdeckung ([`publish-ota.ps1`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/publish-ota.ps1), [`AndroidOtaReleaseChannelTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/System/AndroidOtaReleaseChannelTest.php), [`AppUpdateOtaHardeningTest.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/test/java/de/meinseelenfunke/app/AppUpdateOtaHardeningTest.kt)):** `publish-ota.ps1` synchronisiert den Quellcode, baut das R8-Release-APK, prüft den X.509-Fingerprint, aktiviert das Paket im lokalen Laravel-Tresor und überträgt es optional verschlüsselt per `POST /api/funki/system/app-update/publish` an Stage oder Live. End-to-End abgesichert durch PHPUnit-Security-Tests (inkl. Rogue-Key-, Spoofed-Cert-, Rollback- und Rollen-Isolations-Tests) sowie Robolectric-/MockWebServer-Tests auf Android.
