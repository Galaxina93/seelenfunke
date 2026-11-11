# Funkenwerk Collection – Desktop-Ökosystem, Icons, Sicherheit & Architektur

**Stand:** 09.11.2026  
**Desktop-Ordner:** `C:\Users\konta\Desktop\Funkenwerk Collection`  
**Quellverzeichnisse (WSL):**
- [`funkenwerk/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk) - Funkenwerk Haupt-Suite (Mail, Aufgaben, Kalender, Kontakte, Musik mit GPU-Fokus-Modus, Smart-Home, Kamera-Studio, Dateiverwaltung & integrierter PC-KI-Agent **Funkira**)
- [`funkenwerk-player/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-player) - Funkenwerk Player (Globaler Windows-Standard-Mediaplayer als VLC-Ersatz)
- [`funkenwerk-video/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-video) - Funkenwerk Video (KI-Viral-Videograph auf Basis von EffectCraft/FFmpeg)
- [`funkenwerk-ftp/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-ftp) - Funkenwerk FTP (Hochsicherer SFTP/FTPS/FTP-Client als WinSCP-Ersatz)
- [`funkenwerk-ssh/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-ssh) - Funkenwerk SSH (Gehärteter, schneller SSHv2-Terminal-Client als PuTTY-Ersatz)
- [`funkenwerk-pdf/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-pdf) - Funkenwerk PDF (Cleanes PDF-Studio zum Öffnen, Ausfüllen, Unterschreiben, Ändern & Speichern als Adobe-Acrobat-Ersatz)
- [`funkenwerk-miro/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-miro) - Funkenwerk Miro (100 % lokales Offline-Whiteboard & Infrastruktur-Board als Miro-Ersatz)
- [`funkenwerk-drucker/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-drucker) - Funkenwerk Drucker (100 % lokales Druck-, Mehrseiten-Scan- & Kopier-Studio ohne Konto- oder Abo-Zwang als HP-Smart-Ersatz)
- [`funkenwerk-browser/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-browser) - Funkenwerk Browser (Eigener Zero-Trust Whitelist- & Schleusen-Browser mit integriertem Netzwerk-Ad-/Tracker-Blocker, Multi-Tabs & `F12` DevTools)
- [`funkenwerk-os/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os) - Funkenwerk OS v3.0 (Vollwertiges Desktop-Betriebssystem mit Profi-Dateimanager, Atelier-Live-Wallpaper, 60 FPS Seelenfunken-Engine & Digitaler Festung)
- [`funkenwerk-mcp/`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp) - Funkenwerk MCP v1.3 (Gehärteter lokaler Zero-Secret `stdio` Model Context Protocol Server inkl. Live-IPC-Brücke für Musik & Smart-Home)

---

## 1. Zentrale Desktop-Struktur (`Funkenwerk Collection`) & Marken-Icons

Alle Desktop-Applikationen, der lokale MCP-Server sowie die direkten `.lnk`-Schnellstarter liegen gebündelt in `C:\Users\konta\Desktop\Funkenwerk Collection`:

| Anwendung | Ausführbare Datei | Multi-Resolutions-Icon (`.ico` / `.png`) | Motiv (Schwarz-Gold `#C5A059` 3D-Badge) |
| :--- | :--- | :--- | :--- |
| **Funkenwerk** | `Funkenwerk\Funkenwerk.exe` | `funkenwerk.ico` | Original Funkenwerk-Funke |
| **Funkenwerk OS** | `Funkenwerk OS\FunkenwerkOS.exe` | `funkenwerk_os.ico` | 3D-Gold-Festungsschild mit Prozessorkern & Seelenfunke |
| **Funkenwerk Browser** | `Funkenwerk Browser\FunkenwerkBrowser.exe` | `funkenwerk_browser.ico` | 3D-Gold-Globus mit Kompass-Funke |
| **Funkenwerk Player** | `Funkenwerk Player\FunkenwerkPlayer.exe` | `funkenwerk_player.ico` | 3D-Gold-Musiknote (`♫`) mit Funken |
| **Funkenwerk Video** | `Funkenwerk Video\FunkenwerkVideo.exe` | `funkenwerk_video.ico` | 3D-Gold-Filmklappe mit Play-Dreieck & Funke |
| **Funkenwerk FTP** | `Funkenwerk FTP\FunkenwerkFtp.exe` | `funkenwerk_ftp.ico` | 3D-Gold-Server-Blades mit Transfer-Pfeilen (`⇄`) & Funke |
| **Funkenwerk SSH** | `Funkenwerk SSH\FunkenwerkSsh.exe` | `funkenwerk_ssh.ico` | 3D-Gold-Sicherheitsschild mit Terminal-Prompt (`>_`) & Funke |
| **Funkenwerk PDF** | `Funkenwerk PDF\FunkenwerkPdf.exe` | `funkenwerk_pdf.ico` | 3D-Gold-Dokument mit Füllfederhalter, Unterschrift & Funke |
| **Funkenwerk Miro** | `Funkenwerk Miro\FunkenwerkMiro.exe` | `funkenwerk_miro.ico` | 3D-Gold-Knotenboard mit Verbindungslinien & Funke |
| **Funkenwerk Drucker** | `Funkenwerk Drucker\FunkenwerkDrucker.exe` | `funkenwerk_drucker.ico` | 3D-Gold-Drucker & Scanner mit Scan-Lichtbalken, Blatt & Funken |
| **Funkenwerk MCP** | `Funkenwerk MCP\FunkenwerkMcp.exe` | Headless `stdio` Dienst | Lokale Zero-Secret KI-Brücke (`mcp_config.json`) |

---

## 2. Funkenwerk FTP (WinSCP-Nachfolger)

### Clean-Design & Bedienung
- **Reduziertes 2-Kachel-Layout:** Links `LOKAL`, rechts `SERVER`, dazwischen zentrale Transfer-Buttons (`→` Upload / `←` Download) inklusive vollständigem **rekursivem Ordner-Upload, -Download und -Löschen**.
- **Bidirektionales 4-Wege Drag & Drop (mit Gold-Rahmen-Feedback `#C5A059`):**
  1. **Windows Explorer → `SERVER`**: Lädt einzelne oder mehrere Dateien sowie komplette Ordnerstrukturen rekursiv in das aktuelle Server-Verzeichnis hoch.
  2. **`LOKAL` → `SERVER` (intern)**: Ziehen von Dateien/Ordnern aus der linken Liste direkt in die rechte Server-Liste lädt diese rekursiv hoch.
  3. **`SERVER` → `LOKAL` (intern)**: Ziehen von Dateien/Ordnern aus der rechten Server-Liste in die linke lokale Liste lädt diese rekursiv herunter.
  4. **Windows Explorer ↔ `LOKAL`**: Zieht man einen Ordner aus dem Explorer auf `LOKAL`, wechselt die Ansicht direkt in diesen Ordner; zieht man Dateien auf `LOKAL`, werden sie übernommen; Dateien aus `LOKAL` lassen sich zudem direkt auf den Windows-Desktop ziehen.
- **Direkter `SSH`-Button neben `← HERUNTERLADEN`:** Sobald eine Serververbindung aufgebaut ist, erscheint links neben `← HERUNTERLADEN` der `SSH`-Button. Ein Klick übergibt die aktive Sitzung samt aktuellem Server-Verzeichnis (`RemotePathBox.Text`) per einmaligem, Windows-DPAPI-verschlüsseltem Handoff-Ticket (`handoff_ticket.dpapi`) an **Funkenwerk SSH**, baut die SSH-Verbindung automatisch auf und wechselt per `cd` direkt in genau diesen Server-Ordner.
- **Keine Text-Überfrachtung:** Keine überflüssigen Erklärtexte, Untertitel oder verschachtelten Einstellungsbäume.
- **100 % Light-/Darkmode & Passwort-Auge (`👁`/`🙈`):** Konsistentes Theme-Switching und einblendbares Passwortfeld.

### Automatische WinSCP- & PuTTY-Migration
Beim ersten Start liest [`FunkenwerkFtpSecurity.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-ftp/FunkenwerkFtpSecurity.cs) alle bestehenden Sitzungen aus `HKCU\Software\Martin Prikryl\WinSCP 2\Sessions` und `HKCU\Software\SimonTatham\PuTTY\Sessions` aus:
- Dekodiert die unsichere WinSCP-`0xA3`-XOR-Passwortverschleierung im Arbeitsspeicher und überführt die Zugangsdaten sofort in den **AES-256-GCM + Windows DPAPI Vault** (`%LOCALAPPDATA%\FunkenwerkFtp\profiles_vault.json`).
- Bereinigt fehlerhafte Hostnamen (z. B. führendes `%20` in `bollmann`) und verknüpft automatisch den vorhandenen OpenSSH-Schlüssel `C:\Users\konta\.ssh\id_ed25519`.
- **Importierte Profile (8):** `All Inkl - Bollmann`, `bollmann`, `danny`, `Meral`, `Mittwald`, `Mittwald Websocket`, `Mittwald Node.js App`, `ssh-cqa7h4@a-bycqrf@ssh.altgemeinde.project.host`.

### Sicherheits- & Härtungsarchitektur
1. **AES-256-GCM + Windows DPAPI (`CurrentUser`)**: Jedes gespeicherte Passwort wird mit einem 256-Bit-Schlüssel, 96-Bit-Krypto-Nonce und 128-Bit-Auth-Tag verschlüsselt und zusätzlich per Windows DPAPI an das Windows-Benutzerkonto gebunden; Schlüsselmaterial wird nach Gebrauch via `CryptographicOperations.ZeroMemory` genullt.
2. **SHA-256 Host-Key Pinning (`known_hosts_sha256.json`)**: Schutz vor Man-in-the-Middle-Angriffen (MITM). Bei abweichendem Server-Host-Key wird der Verbindungsaufbau sofort hart abgebrochen.
3. **Path-Traversal- & Zip-Slip-Schutz (`ValidateSafeLocalPath`)**: Blockiert manipulierte Server-Dateinamen mit `..`, `/`, `\` oder `:`, bevor Daten lokal geschrieben werden können.
4. **Gepatchte Protokoll-Bibliotheken**: Verwendet `SSH.NET 2026.0.0` (frei von `GHSA-mggc-4xg6-vcxf` / `GHSA-q939-rpr3-3284`) und `FluentFTP 51.1.0`.

---

## 3. Funkenwerk SSH (PuTTY-Nachfolger)

### PuTTY-Konsolenansicht & Direkteingabe
- **Echte PuTTY-Konsole (`#000000` & Grüner Block-Cursor `█`):** Rechts befindet sich die schwarze SSH-Konsole ([`PuTTYAnsiScreenBuffer`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-ssh/MainWindow.xaml.cs)) ohne separate Eingabeleiste. Eingaben (`git pull`, `git -v`, `php artisan`, `Tab`-Autovervollständigung, Pfeiltasten `↑`/`↓`, `Backspace`, Rechtsklick-Einfügen) erfolgen wie in PuTTY direkt am grünen Cursor in der Konsole.
- **24-Bit TrueColor & ANSI-Prompt-Rendering:** Rendert die originalen Mittwald-Prompt-Badges (` p-g27wim @ ` in Violett `#847AFD`, ` [a-lc6tkm] ` in Blau `#0054F5`, ` ~/html/seelenfunke-stage ` in Grün `#00C795`) sowie die Login-Zeilen (`Using username "kontakt@mein-seelenfunke.de@a-lc6tkm"`, `Authenticating with public key "kontakt@mein-seelenfunke.de"`).
- **Automatische SFTP-zu-SSH-Container-Übersetzung:** Beim Klick auf den `SSH`-Button in **Funkenwerk FTP** wird der reine SFTP-Benutzer (`sftp-nvhbhb@p-g27wim`) automatisch auf den echten SSH-Container-Benutzer (`kontakt@mein-seelenfunke.de@a-lc6tkm` für Stage bzw. `kontakt@mein-seelenfunke.de@a-iurgq8` für den Websocket-Worker) umgeschlüsselt und direkt per `cd` in das aktuelle Server-Verzeichnis gewechselt.

### Sicherheits- & Stabilitätsvorteile gegenüber PuTTY
1. **100 % Outbound-Only (Null Netzwerk-Angriffsfläche)**: Öffnet keinerlei lokale Listener-Ports (kein X11-Forwarding, kein SOCKS-Proxy, kein Agent-Forwarding, kein Telnet/Rlogin/Serial). Die Anwendung kann im Netzwerk nicht von außen adressiert oder angegriffen werden.
2. **Aktiver 15-Sekunden KeepAlive-Heartbeat**: Verhindert eingefrorene oder abgerissene SSH-Sitzungen bei NAT-/Firewall-Timeouts.
3. **ANSI-Escape-Sanitizer (`StripAnsiControlSequences`)**: Filtert potenziell gefährliche Terminal-Escape-Injektionen (z. B. OSC-Title-Injection) vor der Darstellung heraus.
4. **AES-256-GCM + Windows DPAPI Vault & SHA-256 Host-Key Pinning**: Identische kryptografische Härtung wie in Funkenwerk FTP (`%LOCALAPPDATA%\FunkenwerkSsh\`).
5. **Importierte Profile (6):** `bollmann`, `danny`, `Meral`, `Mittwald Node.js App`, `Mittwald Websocket`, `ssh-cqa7h4@a-bycqrf@ssh.altgemeinde.project.host`.

---

## 4. Funkenwerk PDF (Adobe-Acrobat-Ersatz)

- **Code & UI**: [`MainWindow.xaml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-pdf/MainWindow.xaml), [`MainWindow.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-pdf/MainWindow.xaml.cs), [`PdfFormFieldEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-pdf/PdfFormFieldEngine.cs), [`App.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-pdf/App.xaml.cs).
- **Echte Interaktive PDF-Formularfelder (AcroForm-Engine wie in Adobe Acrobat)**:
  - [`PdfFormFieldEngine`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-pdf/PdfFormFieldEngine.cs) liest beim Öffnen automatisch sämtliche PDF-Widget-Annotationen (`/Subtype /Widget`) über alle Seiten inkl. Vererbungskette (`/Parent`), Koordinaten-Transformation (`MediaBox`/`CropBox` & Seitenrotation `0/90/180/270°`), `/FT` (`/Tx` ein- und mehrzeilige Textfelder, `/Btn` Checkboxen & Radio-Gruppen, `/Ch` Dropdown-Auswahlfelder, `/Sig` Unterschriftenfelder), `/MaxLen` und `/Q`-Ausrichtung aus.
  - Alle ausfüllbaren Inputfelder werden wie in Adobe Acrobat dezent blau hinterlegt direkt auf dem Dokument gerendert (mit Gold-Fokusrahmen `#C5A059` beim Reinklicken und sauberer `Tab` / `Shift+Tab`-Reihenfolge von oben nach unten). Kleine `Ja`/`Nein`-Kästchen lassen sich zusätzlich per `Leertaste` oder Doppelklick sofort mit `X` umschalten.
  - **100 % kompatibles Speichern (`/V` + ISO-32000 `/AP` Form-XObjects):** Beim Speichern werden sowohl die echten AcroForm-Werte (`/V`, `/AS`) erhalten – sodass die Felder beim erneuten Öffnen in **Funkenwerk PDF** oder **Adobe Acrobat** weiterhin bearbeitbar bleiben – als auch frische ISO-32000 Appearance-Streams (`/AP -> /N` mit WinAnsi-Umlautunterstützung & Mehrzeilen-Umbruch) erzeugt, damit Browser, Smartphones und E-Mail-Vorschauen jedes ausgefüllte Feld sofort scharf anzeigen.
- **Responsive Mobile-/Desktop-Toolbar & Einklappbare Seitenleiste**:
  - Die obere Werkzeugleiste nutzt ein adaptives `WrapPanel`-Layout statt starrer Grid-Spalten, sodass bei jeder Fensterbreite und DPI-Skalierung (4K-Desktop, Laptop-Skalierung, Split-Screen) **kein einziger Button abgeschnitten** wird (`↖ Auswahl`, `T Text`, `✓ Haken`, `✕ Kreuz`, `Datum`, `▭ Korrektur`, `Marker`, `✍ Unterschrift`).
  - Über `☰` lässt sich die linke `SEITEN`-Leiste jederzeit ein-/ausblenden; bei schmalen Fenstern (`< 760 DIPs`) klappt sie automatisch ein.
  - Die helle Standard-Windows-ComboBox wurde durch einen 100 % Dark-/Lightmode-konsistenten Größen-Stepper (`− 14 pt +`) ersetzt.
- **Öffnen, `STRG + Mausrad` Zoom & High-DPI-Rendering**: Nutzt die hardwarebeschleunigte Windows-PDF-Engine (`Windows.Data.Pdf.PdfDocument`) kombiniert mit `PDFsharp-WPF 6.2.4`, unterstützt stufenloses mauszeiger-zentriertes Zoomen per `STRG + Mausrad` sowie `STRG + + / - / 0` (`40 %` bis `350 %`, 60-FPS Sofort-Skalierung + verzögerungsfreies High-DPI-Nachschärfen), Drag & Drop von PDFs und registriert sich als Windows-Handler für `.pdf`-Dateien.
- **Ausfüllen, Vektor-Skalierung & Korrigieren**:
  - `✓ Haken` / `✕ Kreuz` (**Stufenlos skalierbare Vektor-Symbole**): Werden als Vektor-`Path` in einer `Viewbox` gerendert und lassen sich auf **4 Arten** stufenlos vergrößern/verkleinern (`8–220 pt`): direkt über die schwebende Mini-Leiste `[ − ] [ + ] [ ✕ ]` über dem Element, per Ziehen an der goldenen Ecke unten rechts (`⤡`), über den oberen Größen-Stepper (`− 14 pt +`) oder per Tastatur (`+` / `-`). Beim Speichern skaliert die PDF-Linienstärke proportional mit.
  - `T Text`: Klick an beliebige Stelle platziert ein frei verschieb- und skalierbares Textfeld (`6–96 pt`, Farben Schwarz, Dokumentenblau, Gold, Rot) mit separatem Gold-Ziehstreifen links zum konfliktfreien Verschieben.
  - `Datum`: 1-Klick-Einfügen von `Gifhorn, TT.MM.JJJJ`.
  - `▭ Korrektur`: Überdeckt bestehenden PDF-Text sauber weiß und erlaubt direktes Überschreiben mit neuem Text.
  - `Marker`: Transparente Textmarker-Hervorhebung.
  - **Seiten-Operationen (`SEITEN`)**: Seiten um 90° drehen (`↻ 90°`), weitere PDFs anhängen (`+ PDF`) und einzelne Seiten löschen (`✕`).
- **Unterschreiben (`✍ Unterschrift`)**:
  - Integriertes Unterschriften-Pad (`InkCanvas` mit geglätteter Kurvenführung in Dokumentenblau, Schwarz, Gold oder Rot), Alternativ-Import eines Unterschriften-Bildes (`Bild laden`) sowie **1-Klick-Wiederverwendung** der einmalig gespeicherten Unterschrift (`Gespeicherte nutzen`).
- **Echtes Einbrennen & Speichern (`Speichern` / `Speichern als`)**: Schreibt alle Eintragungen, skalierten Haken/Kreuze, Korrekturen und die hochauflösende transparente Unterschrift vektor- und punktgenau direkt in die PDF-Datei (geprüft per automatisiertem `--self-test`).

---

## 5. Bidirektionale Aufgaben-Synchronisation & Android-Wecker-Restzeit

- **3-Wege-Aufgaben-Sync (Webportal `/admin/dashboard` & `/admin/task` ↔ Funkenwerk Windows ↔ Funkenwerk Android App):**
  - Neuer REST-Endpunkt `DELETE /api/tasks/{id}` in [`routes/api/tasks.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api/tasks.php).
  - Livewire-Polling (`wire:poll.15s`) in [`management-task.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/management/management-task.blade.php).
  - 15-Sekunden-Auto-Sync-Timer in [`funkenwerk/MainWindow.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk/MainWindow.xaml.cs).
  - Vollständige Offline-/Online-Synchronisation inkl. `DELETE` in [`OrganizerRepository.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/data/repository/OrganizerRepository.kt).
- **Android-Wecker Restzeit-Einblendung:** Beim Einschalten eines Wecker-Switches in [`AlarmTabScreen.kt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/ui/screens/AlarmTabScreen.kt) berechnet [`FunkenwerkAlarmManager.formatRemainingDurationText`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/android-app/app/src/main/java/de/meinseelenfunke/app/alarm/FunkenwerkAlarmManager.kt) exakt die verbleibende Zeit bis zum Klingeln (z. B. *„Wecker klingelt in 7 Stunden und 45 Minuten.“*) und blendet diese sowohl als animiertes Gold-Banner als auch als Android-Toast für 3,5 Sekunden ein.

---

## 6. Funkenwerk MCP v1.3 (Gehärteter Lokaler Zero-Secret KI-Server & Live-PC-Brücke)

- **Code & Deployment**:
  - [`Program.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/Program.cs), [`McpSecurityGuard.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/McpSecurityGuard.cs), [`McpPdfEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/McpPdfEngine.cs), [`McpOrganizerClient.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/McpOrganizerClient.cs), [`McpMiroEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/McpMiroEngine.cs), [`FunkenwerkMcp.csproj`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/FunkenwerkMcp.csproj).
  - Produktiv-Binary: [`FunkenwerkMcp.exe`](file:///C:/Users/konta/Desktop/Funkenwerk%20Collection/Funkenwerk%20MCP/FunkenwerkMcp.exe) (eingebunden in [`mcp_config.json`](file:///C:/Users/konta/.gemini/config/mcp_config.json) unter `mcpServers.funkenwerk` ohne Klartext-Tokens).

### Bereitgestellte MCP-Tools (15 Tools + 3 Read-Only Resources)
1. **PDF-Autopilot (`McpPdfEngine`)**:
   - `pdf_inspect`: Liest Seitenmaße und sämtliche ausfüllbaren AcroForm-Felder (`/Tx`, `/Btn`, `/Ch`, `/Sig`, Koordinaten, Optionen, aktuelle Werte) aus.
   - `pdf_fill_form`: Füllt AcroForm-Felder (`/V` + ISO-32000 `/AP` Form-XObjects) sowie optionale Vektor-Overlays (`text`, `check`, `cross`, `whiteout`) atomar aus und öffnet das weiterhin bearbeitbare Dokument auf Wunsch direkt in **Funkenwerk PDF** zur Endkontrolle & Unterschrift.
   - `pdf_autofill_stammdaten`: Befüllt Lieferanten-, KYC-, Händler- und Behörden-Formulare automatisch mit den verifizierten `Mein-Seelenfunke`-Stammdaten (inkl. `overrides` für Ja/Nein-Kästchen) und listet noch offene Rückfragefelder transparent auf.
2. **Lokale Miro-Projekt- & Infrastruktur-Boards (`McpMiroEngine`)**:
   - `miro_board` (`list_projects`, `get_project`, `create_project`, `upsert_node`, `connect_nodes`, `delete_item`, `delete_project`, `open_board`) sowie Read-Only-Ressource `funkenwerk://infrastructure/board`: Verwaltet 100 % lokale Offline-Boards in `%LOCALAPPDATA%\FunkenwerkMiro\projects\*.json` atomar und öffnet Projekte direkt in **Funkenwerk Miro**.
3. **3-Wege Organizer-Synchronisation (`McpOrganizerClient`)**:
   - `tasks_list` & `tasks_manage` (`create`, `update`, `toggle`, `subtask`, `delete`): Synchronisiert Aufgaben in Echtzeit zwischen Webportal (`/admin/dashboard`), **Funkenwerk Windows** und **Android App**.
   - `calendar_list` & `calendar_create`: Liest und erstellt Termine im zentralen Funkenwerk-Kalender.
   - `shopping_manage` (`list`, `add`, `toggle`, `delete`): Pflegt die synchronisierte Einkaufs-/Besorgungsliste.
4. **Zero-Secret Server- & Collection-Steuerung (`McpSecurityGuard`)**:
   - `server_list_profiles`: Liefert ausschließlich passwortfreie Metadaten der gespeicherten FTP-/SSH-Profile inkl. SHA-256 Host-Key-Pinning-Status.
   - `server_open_client`: Startet **Funkenwerk FTP** oder **Funkenwerk SSH** (optional mit blindem, DPAPI-verschlüsseltem Einmal-Handoff `handoff_ticket.dpapi` direkt in ein Zielverzeichnis).
   - `ssh_exec_safe_readonly`: Führt ausschließlich fest freigegebene Diagnose-/Stage-Presets (`git_status`, `git_log`, `git_branch`, `php_version`, `artisan_about`, `disk_usage`, `list_dir`, `stage_git_pull`) auf SHA-256-gepinnten Hosts aus (keine freien Shell-Befehle möglich).
   - `collection_control` (`status`, `launch` für alle 7 Apps inkl. `miro`, `stammdaten`, `security_audit`) sowie die Ressourcen `funkenwerk://company/stammdaten`, `funkenwerk://security/policy` und `funkenwerk://infrastructure/board`.
5. **Live-Musik- & Smart-Home-Steuerung auf dem PC (`Funkenwerk_ControlPipe_v1`)**:
   - `music_control` (`status`, `play`, `pause`, `toggle`, `next`, `prev`, `set_volume`, `volume_delta`, `focus_on`, `focus_off`, `favorite`, `shuffle`, `repeat`): Steuert den laufenden Funkenwerk-Musikplayer live über eine `CurrentUserOnly`-isolierte Named Pipe (Lautstärke strikt `0..100` in 1er-Schritten, niemals ungefragtes Autoplay ohne expliziten Befehl, optionales `autoStartApp`).
   - `smarthome_control` (`status`, `plug_on`, `plug_off`, `plug_toggle`, `camera_focus`, `camera_grid`, `camera_ptz`, `camera_snapshot`): Schaltet Tapo-Steckdosen (KLAP v2) und steuert ONVIF-Kameras inkl. PTZ-Richtungskorrektur direkt über die laufende Funkenwerk-Hauptanwendung.

### Mehrschichtige Sicherheits- & Zweitstufen-Härtung (26/26 Penetration-Checks)
1. **Reiner `stdio`-Transport, `CurrentUserOnly` Named Pipe & `stdout`-Wire-Isolation**: Öffnet **0 TCP/UDP-Netzwerk-Ports**. Lokale IPC zur Desktop-Suite läuft ausschließlich über `PipeOptions.Asynchronous | PipeOptions.CurrentUserOnly` (`Funkenwerk_ControlPipe_v1`, 64-KB-Payload-Limit). Direkt beim Prozessstart wird `Console.Out` auf `Console.Error` umgebogen, sodass Drittbibliotheken niemals den JSON-RPC-Datenstrom kompromittieren können.
2. **Zero-Secret Vault-Bridge & `ZeroMemory`**: Entschlüsselt `FWVAULT2:` (Win32 `CryptUnprotectData` + `PBKDF2-SHA512` 100.000 Iterationen + `AES-256-GCM`), `FWAES256GCM1:` und `FWSSHAES256GCM1:` ausschließlich kurzlebig im RAM, überschreibt Schlüssel-/Klartext-Arrays im `finally`-Block via `CryptographicOperations.ZeroMemory` und speichert keinerlei Secrets in `mcp_config.json`.
3. **14-Muster Outbound-DLP-Redactor (`ScrubSecrets`)**: Filtert vor jeder Ausgabe automatisch Sanctum-Tokens, Private Keys, PuTTY-Keys, Vault-Chiffren (`FWVAULT2:` / `FWAES256GCM1:` / `FWSSHAES256GCM1:`), `.env`-Secrets, API-Keys, JWTs, Basic-Auth-URLs und Bearer-Header heraus.
4. **Dateisystem-Sandbox, `%PDF-` Magic-Header & Atomares Schreiben**:
   - Strikte Whitelist-Wurzeln (`Downloads`, `Documents`, `Desktop`, `Pictures`, `buchhaltung`, `Abschlussberichte`) kombiniert mit expliziter Komplettsperre für `.ssh`, `.gemini`, `AppData`, `.git`, `.env*` sowie den gesamten Programmordner `Desktop\Funkenwerk Collection\*`.
   - Blockiert Pfad-Traversal (`..`), Symlinks/Junctions (`FileAttributes.ReparsePoint`), NTFS Alternate Data Streams (`:`), ausführbare Dateiendungen sowie reservierte Windows-DOS-Gerätenamen (`CON`, `NUL`, `COM1` usw.) und fremde Named Pipes (`\\.\`).
   - Prüft vor dem Einlesen jeder `.pdf`-Datei zwingend den echten `%PDF-`-Magic-Header und schützt das Hauptboard `funkenwerk_infrastruktur` vor versehentlichem Löschen.
5. **Fail-Closed SHA-256 SSH-Pinning & Shell-Metazeichen-Sperre**: Prüft Server-Fingerabdrücke in konstanter Zeit (`FixedTimeEquals`) gegen `FunkenwerkSsh`, `FunkenwerkFtp` und OpenSSH `~/.ssh/known_hosts` und validiert Host, Port, User und `RemoteDirectory` strikt per Whitelist-Regex (`^[A-Za-z0-9/_.\-~]+$`).
6. **SSRF-, DoS- & Rate-Limit-Härtung**: `SocketsHttpHandler` mit `AllowAutoRedirect = false`, `UseProxy = false`, `TLS 1.2/1.3`, Host-Pinning, `4 MB` Response-Deckel, Bounded-Line-Reader (`512 KB`), `MaxDepth = 24` gegen JSON-Bomben sowie Sliding-Window-Rate-Limiter (`60 Calls/Min` gesamt, `10 SSH-Calls/Min`).
7. **Manipulationssicheres SHA-256 Hash-Chain Audit-Log & NTFS-ACLs**: Jede Entscheidung wird in `%LOCALAPPDATA%\FunkenwerkMcp\security_audit.jsonl` kryptografisch verkettet protokolliert (`FunkenwerkMcp.exe --self-test`: **26/26 Tests bestanden**).

---

## 7. Integrierter PC-KI-Agent („Funkira“), GPU-Musik-Fokus & Smart-Home-DB-Sync

- **Integrierter PC-Agent `Funkira` in `Funkenwerk` (`TAB 11` / `Strg+K`) mit nativer MCP-v1.3-Brücke**:
  - Implementiert in [`FunkenwerkAgentEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk/FunkenwerkAgentEngine.cs) sowie eingebettet als Space #11 (`✦`) in [`MainWindow.xaml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk/MainWindow.xaml) und [`MainWindow.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk/MainWindow.xaml.cs).
  - Verbindet sich direkt per JSON-RPC 2.0 (`CallFunkenwerkMcpToolAsync`) mit [`FunkenwerkMcp.exe`](file:///C:/Users/konta/Desktop/Funkenwerk%20Collection/Funkenwerk%20MCP/FunkenwerkMcp.exe) sowie lokal über `IFunkenwerkLocalBridge` und `<fw_action>`-Tool-Blöcke mit dem Seelenfunke-Backend (`/api/ai/chat`).
  - Beherrscht sofort: **Funkenwerk Miro** (Projekte anlegen, öffnen, Notizen/Karten/Verbindungen ergänzen, Live-Infrastruktur- & IP-Auskunft), **Funkenwerk PDF** (Stammdaten-Autofill in `Downloads`/`Documents`), **Aufgaben / Kalender / Einkaufsliste** inkl. sofortigem UI-Refresh, **Musik & Fokus-Modus**, **Smart-Home-Steckdosen & IP-Kameras**, **gepinnte SSH-Diagnosen** sowie den Start aller **7 Funkenwerk Collection Apps**.
- **Zero-Allocation VSync GPU Fokus-Modus (`CelestialFocusRenderer`)**:
  - [`CelestialFocusRenderer.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk/CelestialFocusRenderer.cs) rendert 60–144 FPS über `CompositionTarget.Rendering` und eingefrorene (`Freeze()`) Ressourcen ohne pro-Frame-Allokationen.
- **Smart-Home Kamera-PTZ-Richtungskorrektur, Hand-Cursor & Automatischer DB-Sync**:
  - [`SmartHomeEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk/SmartHomeEngine.cs) setzt `InvertPan = true` für Tapo `C520WS`/`C210`, `Cursors.Hand` für Doppelklick-Großansicht und synchronisiert alle 3 Steckdosen & 4 Kameras passwortfrei über `POST /api/funki/routine/smarthome-sync`.

---

## 8. Funkenwerk Miro (100 % Lokales Offline-Board), PDF-Endlos-Scroll/Text-Copy & Mail-Verbesserungen

- **Funkenwerk Miro (`funkenwerk-miro/` -> [`FunkenwerkMiro.exe`](file:///C:/Users/konta/Desktop/Funkenwerk%20Collection/Funkenwerk%20Miro/FunkenwerkMiro.exe))**:
  - Eigenständiges, 100 % lokales Offline-Whiteboard (`%LOCALAPPDATA%\FunkenwerkMiro\projects\*.json`, keinerlei Internet-/Cloud-Abhängigkeit) in [`MiroModelsAndStorage.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-miro/MiroModelsAndStorage.cs), [`MainWindow.xaml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-miro/MainWindow.xaml) und [`MainWindow.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-miro/MainWindow.xaml.cs).
  - Links per 1-Klick `+ NEUES PROJEKT` und Projektauswahl; rechts das komplette unendliche Miro-Board mit Punkt-Raster, Mauszeiger-Zoom, schwebender Miro-Werkzeugleiste (`Auswählen`, `Hand`, `Frame`, `Karte`, `Notiz`, `Form` inkl. Datenbank-Zylinder, `Text`, `Verbindungslinie` mit Port-Ankern & Beschriftungs-Pills), Undo/Redo, PNG-Export, Light-/Darkmode und Live-`FileSystemWatcher`-Sync.
  - Vorinstalliertes Master-Projekt **„Funkenwerk Infrastruktur“** (`funkenwerk_infrastruktur.json`) mit vollständiger Architektur- & IP-Topologie (Cloud `185.215.158.114` / `185.215.158.113`, Android App, Windows-Host `192.168.188.119`, WSL2/Docker `172.24.132.110`, FRITZ!Box `192.168.188.1`, 4× Tapo-Kameras `.101/.102/.105/.132`, 3× Tapo-Steckdosen `.109/.110/.123`, Labeldrucker `.149`, xTool F2 Ultra UV).
- **Funkenwerk PDF (`funkenwerk-pdf/` -> [`FunkenwerkPdf.exe`](file:///C:/Users/konta/Desktop/Funkenwerk%20Collection/Funkenwerk%20PDF/FunkenwerkPdf.exe))**:
  - Durchgehendes Mehrseiten-Scrollen (`PagesStackPanel`) mit automatischer Seiten-/Miniatur-Synchronisation, Maustext-Markierung & Kopieren ([`PdfTextSelectionEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-pdf/PdfTextSelectionEngine.cs) via `PdfPig` + WinRT-`OcrEngine`-Fallback für Scans, `Strg+C`, Doppel-/Dreifachklick, schwebender `Kopieren`-Button) sowie unübersehbarer Speicher-Toast (`✓ Gespeichert`).
- **Funkenwerk Mail & Backend (`routes/api/emails.php`, `CrmOutgoingMailToCustomer.php`, `MailAccountSeeder.php`)**:
  - Datei-Anhänge per Drag & Drop auf das gesamte Verfassen-Modal (`ComposeModalCard`), Textstart oben links (`VerticalContentAlignment="Top"`), Schutz vorhandener Postfach-Passwörter im Seeder sowie neues `⚙ Postfach & SMTP`-Modal (mit Auge-Icon und Live-SMTP-Check) zur Hinterlegung des T-Online E-Mail-Passworts (`securesmtp.t-online.de:465`).
  - **100× gehärtete Versand-Pipeline & Smart-Guidance:** Permanente Anhang-Persistenz (`leitung/crm/mail-attachments/drafts/` & `sent/`), automatischer lokaler Client-Anhang-Recovery-Upload, automatischer SMTP-Port-Fallback (`465 SSL <-> 587 STARTTLS`), Account-Pinning im Verfassen-Modal (`_composePinnedAccountId`) sowie intelligentes Hilfe-Banner (`ComposeSmartGuidanceBanner`) statt roher Fehlermeldungen.
  - **Live-IMAP-Sync-Feedback (`↻ Sync`):** Gold-rotierendes `↻`-Icon (`MailSyncBtn`), kompakter Live-Fortschrittsbalken (`MailSyncStatusBar` mit Sekunden-Zähler), echte synchrone IMAP-Ausführung (`POST /api/funki/emails/sync` mit `wait: true`) und grüner Bestätigungsstatus (`✓ Aktuell` bzw. `✓ +X neu`) inkl. sofortigem Refresh der Ordnerzähler und Nachrichtenliste.

---

## 9. Funkenwerk Drucker (100 % Lokaler & Abofreier HP-Smart-Nachfolger)

- **Code & Deployment**:
  - [`MainWindow.xaml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-drucker/MainWindow.xaml), [`MainWindow.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-drucker/MainWindow.xaml.cs), [`PrinterHardwareEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-drucker/PrinterHardwareEngine.cs), [`DocumentAndPrintEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-drucker/DocumentAndPrintEngine.cs), [`PrinterModelsAndSettings.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-drucker/PrinterModelsAndSettings.cs), [`PrinterSelfTestRunner.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-drucker/PrinterSelfTestRunner.cs).
  - Produktiv-Binary: [`FunkenwerkDrucker.exe`](file:///C:/Users/konta/Desktop/Funkenwerk%20Collection/Funkenwerk%20Drucker/FunkenwerkDrucker.exe) + Schnellstarter `Funkenwerk Drucker.lnk` in `C:\Users\konta\Desktop\Funkenwerk Collection`.
- **100 % Entkopplung von HP Smart, HP-Konto & Instant-Ink-Abo**:
  - **Treiberloser Dual-Stack Scanner (eSCL AirScan LAN + Windows WIA 2.0):** Scannt Einzelseiten, Mehrseiten-PDFs (`+ Weitere Seite anhängen`) und mehrseitige ADF-Stapel (`Flachbett`, `Einzug ADF`, `ADF 2-seitig` bei `150/300/600 DPI`) direkt im Heimnetz über `/eSCL/ScanJobs` oder lokal über WIA 2.0 ohne HP-Cloud-Account.
  - **Abofreie Echtzeit-Tintenstand-Abfrage & WSD-Offline-Heilung:** Ermittelt die lokale Heimnetz-IP (z. B. `HPAA51AF.fritz.box` -> `192.168.188.26`, streng auf private RFC1918-LAN-IPs begrenzt), liest echte Füllstände für `Schwarz (K)` und `Farbe (CMY)` lokal aus `/DevMgmt/ConsumableConfigDyn.xml` bzw. IPP `marker-levels` (auch bei kompatiblen Drittanbieter-/XL-Patronen) und umgeht fehlerhafte Windows-WSD-„Offline“-Sperren automatisch per direktem LAN-IPP-Druck (`:631/ipp/print`).
  - **Druck-Studio, Beleg-Kontrast & Ökosystem-Handoff:** Unterstützt PDF & Bilder mit Live-Blattvorschau (`DIN A4`, `DIN A5`, `10×15 Etikett`), echtem Graustufen-Schwarzdruck (schont die Farbpatrone), Duplex, `100 % Maßtreu`-Modus inkl. 100-mm-Kalibrierungs-Prüfblatt für Laser-Vorlagen, interaktivem Zuschnitt (`✂ Schnitt`), tintensparendem `Beleg-Kontrast`, 1-Klick-Direktkopierer, Warteschlangen-Reiniger sowie 1-Klick-Übergabe an **Funkenwerk PDF** (`✍ In Funkenwerk PDF unterschreiben`) und die Seelenfunke-Buchhaltung (`storage/app/buchhaltung/receipts`).

---

## 10. Funkenwerk OS v3.0 (Notebook-Reifes Desktop-Betriebssystem, Profi-Dateisystem & Digitale Festung)

- **Code & Self-Contained Deployment**:
  - [`MainWindow.xaml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/MainWindow.xaml), [`MainWindow.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/MainWindow.xaml.cs), [`OsBuiltInAppsEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/OsBuiltInAppsEngine.cs), [`AmbientSparksRenderer.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/AmbientSparksRenderer.cs), [`OsModelsAndVault.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/OsModelsAndVault.cs), [`SeelenfunkeLiveClient.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/SeelenfunkeLiveClient.cs), [`OsHardwareAndAudioEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/OsHardwareAndAudioEngine.cs), [`FortressProvisioningEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/FortressProvisioningEngine.cs), [`SecondaryWallWindow.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/SecondaryWallWindow.cs), [`OsSelfTestRunner.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/OsSelfTestRunner.cs).
  - Produktiv-Binary (`--self-contained true` `win-x64`): [`FunkenwerkOS.exe`](file:///C:/Users/konta/Desktop/Funkenwerk%20Collection/Funkenwerk%20OS/FunkenwerkOS.exe) inkl. Zero-Flicker-Atelier-Loop (`atelier_seamless_loop.mp4` mit `0` B-Frames, Ping-Pong-Loop & 1,5 s Underneath-Pre-Roll ohne Seek-Ruckler), `login_atelier_poster.jpg`, Schnellstarter `Funkenwerk OS.lnk` sowie `MiniPC-USB-Setup\`.
- **Pragmatische App-Benennung, Farbcodierte Icons, Desktop-Verknüpfungen & Windows-Tasten Smart-Suche**:
  - Alle Programme innerhalb von `Funkenwerk OS` tragen pragmatische Funktionsnamen ohne redundantes „Funkenwerk“-Präfix (`Zentrale`, `Browser`, `PDF`, `Drucker`, `FTP`, `SSH`, `Mediaplayer`, `Videostudio`, `Tresor`, `Meine Daten`, `Taschenrechner`, `Ausschneiden`, `Notizen`, `Dateien`, `Leitstand`, `Einstellungen`, `Papierkorb`; die alte `Whiteboard`-App wurde vollständig entfernt).
  - **Visuell unterscheidbare App-Icons (`GetSystemBadgeIcon` & Collection-Icon-Sync):**
    - **`Mediaplayer`**: **Orange** (angelehnt an den VLC-Player mit orangem Kegel & Streifen).
    - **`Taschenrechner`**: **Leicht blauer Ton** (`#7DD3FC` -> `#2563EB`).
    - **`Ausschneiden` (Snipping Tool)**: **Rot und Blau leicht gemischt** (`#F87171` + `#A855F7` + `#3B82F6`).
    - **`Notizen`**: **Gold** (`#C5A059`).
    - **`PDF`**: **Rot** (`#F87171` -> `#B91C1C`).
    - **`Browser`**: **3/3 Rot, Gelb und Grün** (`#EF4444`, `#FACC15`, `#22C55E` 120°-Drittel-Rand & Globus).
    - **`Einstellungen`**: **Silber** (`#F3F4F6` -> `#6B7280`).
  - **Löschbare Desktop-Verknüpfungen & Rechtsklick-Umbenennen:** Jedes App-Symbol auf dem Desktop ist eine Verknüpfung und lässt sich per Rechtsklick -> `🗑 Vom Desktop entfernen`, `Entf`-Taste oder Ziehen auf den `Papierkorb` vom Desktop entfernen (sowie über die Suche jederzeit per Rechtsklick -> `📌 Auf Desktop ablegen` oder per Desktop-Rechtsklick -> `📌 Alle Apps einblenden` zurückholen). Zusätzlich ist jedes Symbol per Rechtsklick -> `✎ Umbenennen` (`F2`) frei umbenennbar.
  - **Taskleisten-Favoriten, Rechtsklick `Taskleisteneinstellungen`, Position (`Unten`, `Oben`, `Links`, `Rechts`), App-Ausrichtung (`Links`, `Mittig`, `Rechts`) & `Transparent-Modus`:**
    - Jede App lässt sich per Rechtsklick (`📌 An Taskleiste anheften` / `📌 Von Taskleiste lösen`) dauerhaft als Favorit in die Taskleiste heften.
    - Ein Rechtsklick auf die Taskleiste öffnet direkt das Schnellmenü sowie **`⚙ Taskleisteneinstellungen`** (eigener Tab `Taskleiste` in `Einstellungen`): Position der Taskleiste (`Unten`, `Oben`, `Links`, `Rechts`), Ausrichtung der App-Verknüpfungen (`Links`, `Mittig`, `Rechts`) sowie **`Transparent-Modus`** (Taskleisten-Hintergrund wird 100 % unsichtbar, sodass Start-Emblem, App-Favoriten und System-Tray-Infos frei schweben).
  - **8-Säulen-Ordnerstruktur `Meine Daten`, Automatischer `Daten-Trichter` (`00_Eingang_Trichter`), `kDrive`-Cloud-Anbindung & Kein-Datenmüll-Garantie:**
    - **Keine unstrukturierten Standardverzeichnisse (`Downloads`, `Dokumente`, `Bilder`):** Im gesamten `Funkenwerk OS` (Seitenleiste von `Dateien` und Startmenü) gibt es keine unstrukturierten Standardordner mehr. Stattdessen bietet die linke Seitenleiste von `Dateien` direkten 1-Klick-Zugriff und Drag-&-Drop-Ziele für **`✦ Meine Daten`**, **`📥 00 · Eingang-Trichter`**, alle **8 Säulen (`01` bis `08`)**, **`☁ Anbindungen (`kDrive`)`** sowie die gemounteten Laufwerke (`C:`, `U:`, `G:`, `B:`, USB).
    - **Direkte Browser- & Download-Umleitung in den Trichter (`Zero-Downloads-Clutter`):** `Funkenwerk Browser` (`core.DownloadStarting`) speichert jeden Download direkt in `%LOCALAPPDATA%\FunkenwerkOS\Meine Daten\00_Eingang_Trichter\`. Zusätzlich überwacht `Funkenwerk OS` per Live-`FileSystemWatcher` den Windows-`Downloads`-Ordner (`IngestExternalDownloadsAndCloudInboxes`) und zieht neue externe Downloads sofort nach Abschluss automatisch in den `00_Eingang_Trichter` und sortiert sie datiert (`YYYY-MM-DD_Dateiname`) in die 8 Säulen ein.
    - **Strikter 8-Säulen-Zwang & Kein-Datenmüll-Wächter (`EnforceStrictTrichterStructureOnMeineDaten` & `PurgeZeroGarbageFiles`):** Werden lose Dateien oder fremde Ordner (wie `Neuer Ordner` oder `Downloads`) direkt auf der obersten Ebene von `Meine Daten` abgelegt, schleust der Wächter deren Inhalt sofort durch den `00_Eingang_Trichter` in die passende der 8 Säulen und entfernt den fremden Ordner. System-Mülldateien (`Thumbs.db`, `ehthumbs.db`, `desktop.ini`, `.DS_Store`, `._*`, verwaiste `.tmp`/`.crdownload`/`.part`-Fragmente sowie temporäre Notiz-Drag-Exporte) werden automatisch restlos gelöscht.
    - **`kDrive` (Infomaniak) & Speicher-Anbindungen (`OsStorageBindingDto`, Tab `Anbindungen & Trichter` in `Einstellungen`):** Erkennt automatisch das installierte **`kDrive`** (`C:\Users\konta\kDrive`), schaltet `kDrive.exe` in der Festungs-Firewall frei (`kDrive: FREI`), überwacht `kDrive\00_Eingang_Trichter` für vom Smartphone/Web hochgeladene Belege/Scans und spiegelt auf Wunsch (`8-Säulen-Sync` / Button `☁ Sync`) die saubere 8-Säulen-Struktur `Meine Daten` (`01_Mein-Seelenfunke` bis `08_Tresor_Backups` inkl. `meine_daten_index.json`) 1:1 und frei von Datenmüll nach `kDrive\Meine Daten`. Weitere Cloud-, WebDAV- oder NAS-Ordner lassen sich jederzeit unter `Einstellungen -> Anbindungen & Trichter` hinzufügen (`18/18` Selbsttests bestanden).
  - **Windows-Tasten Sofort-Suche (`WindowsKeySearchHook` + `OsSmartSearchEngine`):** Ein Druck auf die **Windows-Taste** öffnet sofort die OS-Suche mit aktivem Cursor in der Suchleiste; schon beim ersten Buchstaben werden dynamisch passende & naheliegende Apps (inkl. Synonymen wie `re`/`mwst` -> `Taschenrechner`, `snip`/`foto` -> `Ausschneiden`, `kurz`/`memo` -> `Notizen` sowie Tippfehler-Toleranz) gefunden, gold hervorgehoben und per `Enter` direkt gestartet.
- **Integrierte System-Apps (`Taschenrechner`, `Ausschneiden`, `Notizen`)**:
  - **`Taschenrechner`:** Exakte 128-Bit-`decimal`-Arithmetik, Numpad-/Tastatursteuerung, Rechenverlauf zum Anklicken/Kopieren sowie 1-Klick-Tasten `+19%` (Brutto) und `-19%` (Netto).
  - **`Ausschneiden` (Snipping Tool ohne Datenmüll):** DPI-exakter Vollbild-Ausschnitt über alle Monitore (`✂ Neu` sofort oder mit `⏱ 3 Sek.` Verzögerung), landet inkl. aller Stift-/Marker-Einzeichnungen sofort im Zwischenspeicher für **`Strg + V`** ohne Speichern-Zwang; beim Schließen des Fensters wird der Ausschnitt restlos aus dem RAM verworfen (0 Byte Datenmüll).
  - **`Notizen` (Kurznotizen):** Mehrere Sticky-Notes mit sofortigem Auto-Save (`sticky_notes.json`), Echtzeit-Suche und 6 edlen Farbakzenten (`Gold`, `Champagner`, `Smaragd`, `Ozean`, `Rosé`, `Schiefer`) (`16/16` Selbsttests bestanden).
- **100 % Performante Datei- & Fenster-Architektur (`Dateien` Pro)**:
  - **Asynchrones `Task.Run`-Ordnerladen & Hardware-Virtualisierung:** Kein Blockieren des UI-Threads auf großen Ordnern, Netzlaufwerken (`U:\`) oder WSL-Pfaden; `VirtualizingPanel.IsVirtualizing="True"` (`Recycling`), `INotifyPropertyChanged`-Sofortauswahl ohne `ItemsSource`-Reset und asynchrones Thumbnail-Decoding im Hintergrund.
  - **2 Ansichtsmodi per 1 Klick (`⊞ Große Symbole` & `☰ Detail-Tabelle`):**
    - **`☰ Detail-Tabelle`** mit klickbaren Sortier-Spaltenköpfen (`▲`/`▼`) für **`Name`**, **`Erstellt am`** (`dd.MM.yyyy HH:mm`), **`Geändert am`**, **`Typ`** und **`Größe`**.
    - **`⊞ Große Symbole`** mit echten Bild-Miniaturvorschauen für Fotos (`.png`/`.jpg`/`.webp`), 3D-Gold-Typ-Badges und eingeblendetem Erstelldatum.
  - **Vollständige Datei-Logik:** Navigation (`←` Zurück, `→` Vorwärts, `↑` Hoch), klickbare Pfad-Breadcrumbs (`C: › Users › ...`), Echtzeit-Suchfilter (`🔍`), alle gemounteten Laufwerke (`C:`, `U:`, USB-Sticks mit freiem Speicher in GB, `Seelenfunke Projekt`), Mehrfachauswahl (`Strg+Klick`, `Shift+Klick`, `Strg+A`), **echtes Drag & Drop (`DataFormats.FileDrop`)** zwischen Desktop, Unterordnern, Papierkorb und externen Programmen, **Datei-Zwischenablage (`Strg+C` Kopieren, `Strg+X` Ausschneiden, `Strg+V` Einfügen)**, `F2`-Umbenennung, `Entf`-Papierkorb sowie `Eigenschaften`-Dialog inkl. exakter Bytegröße, Erstell-/Änderungszeitstempel und `SHA-256`-Prüfsumme.
  - **Skalierbare & andockbare Glas-Systemfenster mit Aero-Snap:** Alle Systemfenster besitzen stufenlose Größenänderung (`⤡` Resize-Grip), Links-/Rechts-Halbbild-Snap (`◧`/`◨`), Maximieren, Minimieren in die Taskleiste, automatisches Ent-Maximieren beim Ziehen der Titelleiste sowie **echtes Aero-Snap mit Live-Gold-Vorschau (`WindowSnapPreviewBorder`)** beim Ziehen an den linken, rechten oder oberen Bildschirmrand.
  - **Durchgängige Drag-&-Drop-Logik & Instinktive Bedienung im gesamten OS:**
    - **Desktop Multi-Item Drag & Drop mit Live-Ziel-Highlight:** Mehrfach ausgewählte Symbole bewegen sich gemeinsam; beim Ziehen über Ordner, `Meine Daten`, Apps oder den `Papierkorb` leuchtet das Zielsymbol gold auf (`UpdateDesktopDropTargetHighlight`).
    - **Drag & Drop auf Apps, `Meine Daten` & Taskleiste:** Dateien lassen sich direkt auf Desktop-App-Icons oder Taskleisten-Buttons (`PDF`, `Drucker`, `Browser`, `Mediaplayer`, `Tresor`) ziehen, um sie sofort darin zu öffnen. Ziehen auf `Meine Daten` wirft Dateien direkt in den `00_Eingang_Trichter` und sortiert sie automatisch in die 8 Säulen. Apps lassen sich aus dem Startmenü/Suche per Drag & Drop auf den Desktop (exakte Rasterzelle) oder in die Taskleiste ziehen; Favoriten in der Taskleiste sind per Drag & Drop frei umsortierbar; Mittelklick auf ein Taskleisten-Element schließt das Fenster.
    - **`Dateien` Pro Universal-Drop-Ziele & Navigation:** Drag & Drop von Dateien direkt auf beliebige Breadcrumb-Segmente, alle Einträge der linken Schnellnavigation (`✦ Meine Daten`, `📥 Daten-Trichter`, `Desktop`, `Downloads`, `Dokumente`, `Bilder`, `Seelenfunke Projekt`, `Papierkorb`) sowie gemountete Laufwerke (`C:`, `U:`, USB). Unterstützt Maus-Vor/Zurück (`XButton1`/`XButton2`), `Enter`, `Backspace`, `Alt+Links/Rechts/Hoch`, `F5`, `Strg+Shift+N` (Neuer Ordner), `Strg+F` (Suche) sowie **Direkt-Einfügen von Bildern/Screenshots aus dem Zwischenspeicher per `Strg+V`** (`Ausschnitt_yyyy-MM-dd_HHmmss.png`).
    - **Papierkorb mit Ursprungsort-Gedächtnis (`recycle_origins.json`):** Gelöschte Dateien/Ordner merken sich ihren ursprünglichen Pfad (`RememberRecycleOrigin`) und kehren bei `↩ Wiederherstellen` exakt in ihren Ursprungsordner zurück.
    - **Lightbox-Slider (`ViewerWin`) & Notizen-Drag-&-Drop (`NotesWin`):** Bildvorschau mit Vor/Zurück-Slider (`‹` / `›` + Pfeiltasten Links/Rechts), Mausrad-Zoom (`50%`–`400%`) und Drag & Drop; Kurznotizen lassen sich per Drag & Drop direkt als `.txt`-Datei auf den Desktop oder in Ordner herausziehen.
    - **Live-`FileSystemWatcher` & Zero-Allocation Brush-Cache:** Überwacht `Desktop` und `00_Eingang_Trichter` in Echtzeit (automatische Trichter-Sortierung bei neuen Scans/Downloads) und cacht eingefrorene `SolidColorBrush`-Instanzen (`s_frozenBrushCache`).
  - **Zero-Allocation 60–240 FPS Desktop-Mauswellen & Nicht-blockierender System-Tray (`AmbientSparksRenderer.cs` & `OsHardwareAndAudioEngine.cs`):**
    - `AmbientSparksRenderer` rendert sämtliche Funken, 4-Punkt-Sterne und Maus-/Klickwellen komplett ohne `MatrixTransform`-Heap-Allokationen pro Frame (`0` GC-Druck).
    - Alle System-Tray-, Audio-, Netzwerk- und Prozess-Abfragen (`TriggerBackgroundTrayRefresh`, Win32 `QueryFullProcessImageName` statt `Process.GetProcessesByName`, gecachte App-Metadaten und Signatur-geprüftes Taskleisten-Binding `_lastTaskbarSignature`) laufen zu 100 % im Hintergrund-Thread, sodass der UI-Thread beim Bewegen der Maus über den Desktop 0 ms blockiert. Das Mausrad über dem Sound-Tray-Symbol regelt die Systemlautstärke direkt in `±2%`-Schritten (`17/17` Selbsttests bestanden).
  - **System-Tray, Headset-Mikrofon & Programm-Freigaben:** Unten rechts mit WLAN-Scanner/Direktverbindung, Bluetooth/Teufel, Soundverwaltung (`0` bis `100` in 1er-Schritten + 1-Klick-Umschaltung für **Lautsprecher/Headset UND Headset-Mikrofon** inkl. Mikrofon-Stummschaltung) sowie 1-Klick-Freigabeschaltern für `Discord`, `League of Legends` und `PhpStorm & Docker` (`16/16` Selbsttests bestanden).

---

## 11. Funkenwerk Browser (Chrome-Lesezeichenleiste, Ordner, Favicons, Passwörter/Autofill & Werbe-Blocker)

- **Code & Deployment**:
  - [`MainWindow.xaml`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-browser/MainWindow.xaml), [`MainWindow.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-browser/MainWindow.xaml.cs), [`BrowserAutofillAndVaultEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-browser/BrowserAutofillAndVaultEngine.cs), [`BrowserShieldAndSettings.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-browser/BrowserShieldAndSettings.cs).
  - Produktiv-Binary: [`FunkenwerkBrowser.exe`](file:///C:/Users/konta/Desktop/Funkenwerk%20Collection/Funkenwerk%20Browser/FunkenwerkBrowser.exe) + Schnellstarter `Funkenwerk Browser.lnk`.
- **Verwaltbare Chrome-Lesezeichenleiste (1:1 Chrome-Ansicht, Ohne Button-Hintergrund, Ordner-Hover-Wechsel, Drag & Drop & Namenlose Icon-Lesezeichen)**:
  - **1:1 Synchronisation mit dem aktiven Chrome-Profil (`Profile 1`):** Erkennt automatisch das aktuell genutzte Chrome-Profil anhand der neuesten `Bookmarks`-Datei (`Profile 1\Bookmarks`) und übernimmt exakt die 18 Einträge der Chrome-Lesezeichenleiste (`ÜBERSICHT`, `ENTWICKLUNG`, `SOCIALMEDIA`, `KI`, `AMAZON`, `YOUTUBE`, `GOOGLE`, `ELSTER`, `ALG`, `KLAGE`, `PROJEKTE`, die 3 namenlosen Icon-Einträge DeepL, Every Foods, FYRST sowie `Inspect with Chrome Developer Tools`, `Goldschmuck24`, `Id-Armband Panzer` und `GravurMomente`) ohne alte `Default`-Profile einzumischen.
  - **1:1 Chrome-Optik ohne Button-Hintergrund (`FlatBookmarkBtn`):** Lesezeichen und Ordner besitzen keinen Kasten-/Button-Hintergrund (`Background="Transparent"`, `BorderThickness="0"`), nutzen das schlichte Chrome-Outline-Ordner-Icon ohne `▾`-Pfeil und kürzen lange Lesezeichen-Titel exakt wie Chrome.
  - **Ordner- & Lesezeichen-Positionen per Drag & Drop ändern:** Alle Ordner und Lesezeichen in der Leiste (sowie innerhalb von Ordnern) lassen sich einfach mit gedrückter Maustaste an jede beliebige Position ziehen (mit goldener Einfügelinie an der linken/rechten Kante).
  - **Automatischer Ordner-Wechsel beim Drüber-Hovern:** Sobald ein Ordner in der Lesezeichenleiste einmal per Klick geöffnet wurde, öffnen sich beim Drüber-Hovern über andere Ordner in der Leiste (sowie über Unterordner im Menü) diese sofort automatisch, wobei immer nur der aktuell gehoverte Ordner aktiv hervorgehoben ist.
  - **Echte Webseiten-Icons (`FaviconCache`) & Namenlose Lesezeichen (`Name == ""`):** Extrahiert über `winsqlite3.dll` (`FileShare.ReadWrite`) selbst bei geöffnetem Chrome alle gecachten Webseiten-Icons direkt aus Chromes `Favicons`-SQLite-Datenbank, aktualisiert sie live bei Seitenbesuchen (`CoreWebView2.FaviconChanged`) und unterstützt namenlose Nur-Icon-Einträge (`Name == ""`) in der Leiste.
  - **Rechtsklick -> Name ändern (auch leer `""`) -> mit `Enter` bestätigen:** Ein Rechtsklick auf ein beliebiges Lesezeichen oder einen Ordner öffnet direkt das Bearbeiten-Popover (`BookmarkEditPopup`) mit fokussiertem und markiertem Namensfeld (`EditBookmarkNameBox`); der Name kann geändert oder komplett geleert (`""` für Nur-Icon-Anzeige) und direkt per **`Enter`** bestätigt werden.
- **Passwörter & Autofill (`🔑 Passwörter`, `⚡ Autofill` & `FWBVAULT1` Tresor)**:
  - Aktiviert WebView2-Autofill (`IsPasswordAutosaveEnabled = true`, `IsGeneralAutofillEnabled = true`) und kombiniert es mit dem eigenen `FWBVAULT1`-Passwort- & Autofill-Tresor (`DPAPI` + `AES-256-GCM`, `credentials_vault.json`).
  - Synchronisiert aus Chromes `Web Data` und `Login Data` alle gespeicherten Logins (`422` Konten) und Adress-Stammdaten (`Alina Steinhauer`, `Mein-Seelenfunke`, `Carl-Goerdeler-Ring 26`, `38518 Gifhorn`, `kontakt@mein-seelenfunke.de`, `+49 1590 1966864`), füllt Login- und Adressformulare automatisch oder per `⚡ Autofill` aus, speichert neue Logins beim Formular-Absenden automatisch und unterstützt Chrome-CSV-Passwort-Importe (`📥 CSV`).
- **Freier Web-Zugriff & Werbeblocker**:
  - **Offenes Web als Standard (`🔓 OFFEN`):** Öffnet standardmäßig alle Webseiten wie `Google`, `YouTube`, `Instagram`, `WhatsApp Web`, `Mein-Seelenfunke`, `FYRST` und `DHL` sofort ohne Straßensperren (inkl. Multi-Tabs, HTTPS-Mikrofon-/Kamera-Freigabe und `F12` DevTools).
  - **Netzwerk-Ad- & Tracker-Blocker (`WebResourceRequested`):** Blockiert Werbenetzwerke, Tracking-Skripte und Analyse-Domains im Hintergrund noch vor dem Laden (mit Live-Zähler `🛡`).
  - **Optionaler Kiosk-Modus (`🔒 FESTUNG`):** Auf Wunsch per 1 Klick zuschaltbar (`7/7` Selbsttests bestanden).

## 10. Funkenwerk Tresor (`Tresor` im Funkenwerk OS)
- **Zweck**: Vollständig offline arbeitender, 100 % KeePass-kompatibler Passwort-, 2FA- & Schlüssel-Tresor (`\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\funkenwerk-tresor` -> `C:\Users\konta\Desktop\Funkenwerk Collection\Funkenwerk Tresor\FunkenwerkTresor.exe`), der im `Funkenwerk OS` schlicht als **`Tresor`** erscheint und `.kdbx`- sowie `.keyx`-Dateien direkt per Doppelklick öffnet.
- **Kryptographie & `.kdbx`-Kompatibilität (`KdbxCryptoEngine.cs`)**:
  - **KDBX 4.0 / 4.1 (Standard für neue Tresore & 1-Klick-Upgrade):**
    - **Key Derivation (KDF):** `Argon2id` (Standard: `64 MB RAM`, `3 Iterationen`, `2 Lanes`) und `Argon2d` via `BouncyCastle.Cryptography` sowie `AES-KDF`.
    - **Outer Cipher:** `AES-256-CBC` (PKCS7) und `ChaCha20` (RFC 7539) inkl. `Twofish`-Support.
    - **Integrität & Authentifizierung:** `SHA-256` Header-Hash, `HMAC-SHA256` Header-MAC und `HMAC-SHA256 Block Stream` (Manipulationsschutz auf Blockebene).
    - **Inner Stream Cipher (Speicherschutz für Passwörter im XML):** `ChaCha20` (KDBX 4) und `Salsa20` (KDBX 3.1).
  - **KDBX 3.1 Abwärtskompatibilität & 1-Klick-Härtung:** Öffnet bestehende Datenbanken aus KeePass 2 und KeePassXC direkt ohne Konvertierungszwang und bietet in der Top-Leiste den Button **`⚡ Auf Argon2id (KDBX 4) härten`** für ein verlustfreies kryptographisches Upgrade.
  - **2-Faktor-Schlüsseldatei (`.keyx` v2.0 XML / `.key`):** Erstellt und prüft KeePass-2-kompatible 256-Bit-XML-Schlüsseldateien mit integriertem SHA-256-Integritätscheck (`Composite Key = SHA256(SHA256(Passwort) || KeyfileBytes)`).
- **Datenbank-Übertrag & Merge (`📥 .kdbx importieren`)**:
  - Bestehende `.kdbx`-Datenbanken können entweder direkt weiterverwendet oder über **`📥 .kdbx importieren`** (mit eigenem Master-Passwort / Keyfile der Quelldatenbank) in den geöffneten Tresor importiert und zusammengeführt werden (inkl. Ordnerstruktur, Notizen, 2FA-Secrets, Anhängen und automatischer Dubletten-Erkennung).
- **Klassische 3-Bereichs-KeePass-Ansicht (`MainWindow.xaml` & `KeePassIconRenderer.cs`)**:
  - **Mehrstufiger Strukturbaum (`TreeView` links):** Klein & fein mit ausklappbaren Untergruppen und gestochen scharfen 16x16 Vektor-Icons (`KeePassIconRenderer` für alle KeePass `IconID`s `0..68` sowie `<CustomIcons>`-PNGs aus bestehenden `.kdbx`-Dateien). Neue Datenbanken starten direkt mit der vollständigen Struktur-Vorlage (`DB -> Alina -> Privat / Mein-Seelenfunke`).
  - **5-Spalten-Tabelle rechts (`Title | User Name | Password | URL | Notes`):** Durchgehende Spaltenlinien, Sortierung, `Empty.`-Anzeige bei leeren Gruppen, Doppelklick zum Bearbeiten und Rechtsklick-Kontextmenü.
  - **Unteres Detailfeld & Statusleiste:** Zeigt `Group: <Name>.` bzw. die vollständige Vorschau des ausgewählten Eintrags inkl. Live-2FA-Ticker und Schnellkopier-Buttons sowie unten `0 of 0 selected | Ready.`.
- **Hardware- & OS-Sicherheits-Architektur (`TresorSecurityGuard.cs`)**:
  - **Optionale Auto-Sperre (Standardmäßig AUS):** Über die Checkbox **`Auto-Sperre`** in der oberen Werkzeugleiste frei zuschaltbar (`2 / 5 / 10 / 30 Min.`). Ist der Haken nicht gesetzt, bleibt der Tresor dauerhaft geöffnet (`Geöffnet (Auto-Sperre: Aus)`). Beim manuellen oder automatischen Sperren überschreibt `WipeAllInMemory()` alle Schlüssel im RAM via `CryptographicOperations.ZeroMemory`.
  - **Anti-Screen-Capture (`SetWindowDisplayAffinity` `WDA_EXCLUDEFROMCAPTURE`):** Schützt das Tresor-Fenster aktiv vor Screenshots, Snipping Tool, OBS und Screen-Grabbern.
  - **Auto-Clear Clipboard Guard (12 s):** Kopierte Benutzernamen, Passwörter und 2FA-Codes setzen `ExcludeClipboardContentFromMonitorProcessing` sowie `CanIncludeInClipboardHistory = 0` (blockiert `Win+V` & Cloud-Clipboard) und leeren die Zwischenablage nach 12 Sekunden automatisch mit visuellem Countdown-Balken.
  - **Integrierter 2FA / TOTP-Authenticator (RFC 6238), Passwort-/Passphrasen-Generator, Auto-Type (`⌨ Auto-Fill`) & Offline-Sicherheits-Audit:** 12/12 automatisierte Selbsttests (`FunkenwerkTresor.exe --self-test`) sowie 18/18 OS-Selbsttests (`FunkenwerkOS.exe --self-test`) bestanden.

---

## 12. Monitor Gewerbebereich (`GewerbeMonitorWindow.cs`) & Manufaktur Mini-PC (60-FPS DirectX GPU-Sprites & 08:00/22:00 RTC-Automatik)

- **Code & Deployment**:
  - [`GewerbeMonitorWindow.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/GewerbeMonitorWindow.cs), [`OsHardwareAndAudioEngine.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/OsHardwareAndAudioEngine.cs), [`MainWindow.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/MainWindow.xaml.cs), [`App.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/App.xaml.cs).
  - Produktiv-Binaries (`--self-contained true` `win-x64` für alle 11 Apps der Suite): Synchron auf Notebook (`C:\Users\konta\Desktop\Funkenwerk Collection`) und Manufaktur Mini-PC (`C:\Funkenwerk Collection` auf `192.168.188.151`).
- **Locked 60-FPS DirectX GPU-Sprite Engine (`GewerbeMagicPortalRenderer`)**:
  - **Vorgerenderter GPU-Textur-Atlas (`RenderTargetBitmap` + `Freeze()`):** Alle komplexen Vektor- und Verlaufsebenen (äußere Aura & Korona, gegenläufige Haute-Horlogerie-Filigranringe mit `DashStyle`, 3 fließende Seiden-Lichtbänder, 4-Punkt-Diamantsterne, 18 Bokeh-Orbs, 88 Seelenfunken sowie die 3D-K9-Kristallglaskugel) werden einmalig beim Start in eingefrorene DirectX-GPU-Texturen gerendert (`0` `StreamGeometry`-Allokationen, `0` `DashStyle`-Tessellierungen und `0` Software-Clip-Masken in `OnRender`).
  - **60-FPS Saphirglas-Spiegelreflexion im 10-Sekunden-Intervall (`CreateLiveSheenBrush`):** Statt abgestufter Einzelbilder gleitet alle **10 Sekunden** (sowie direkt bei Bestelleingang) ein kontinuierlicher 60-FPS-Doppel-Lichtreflex (`1,45 s` Ease-In-Out-Sweep mit sanftem Ein-/Ausblenden über `Math.Sin(t * Math.PI)`, gefolgt von `8,55 s` Ruhepause) exakt innerhalb des stationären inneren Kristallglas-Radius (`orbR * 0.94`) über die Kugel.
  - **Clean „Fokus“-Modus (`◎ Fokus` Button unten rechts / Taste `F`):** Blendet sämtliche Zusatz- und Steuerelemente (Logo, Untertitel, Status-Pill, Uhrzeit/FPS, obere Leiste und Test-Button) komplett aus, vergrößert den Schriftzug **`MEIN SEELENFUNKE`** monumental auf **`132 pt`** (in einer responsiven `Viewbox` mit `StretchDirection.DownOnly`) und skaliert die mittige Animation (`74 %`) perfekt darunter, sodass ausschließlich der riesige Schriftzug und die Animation sichtbar sind. In der Kugel steht groß und weithin lesbar **`BESTELLUNGEN`** (`20 pt Bold`). Der `◎ Fokus`-Button unten rechts blendet sich im Fokus-Modus bei ruhender Maus unsichtbar aus (`Opacity = 0`) und erscheint sofort wieder, sobald die Maus bewegt wird.
  - **Klassischer Registrierkassen-Sound („Katsching!“) bei Bestelleingang (`EnsureChimeSynthesized`):** Synthetisiert in 48 kHz Stereo (`s_persistentChimePlayer` mit 280 ms Bluetooth-A2DP-Pre-Roll für die Teufel-Anlage) den zweistufigen Klang einer echten mechanischen Registrierkasse: zuerst das metallische Aufspringen der Kassenschublade samt Münzschlitten (`"Ka-"`, `0,28 s–0,40 s`) und direkt darauf den hellen Doppel-Anschlag der Messing-Kassenglocke (`"-tsching!"`, `E7 2637 Hz` / `B7 3951 Hz` / `E8 5274 Hz` + `G#7`-Glanzoberton bis `2,68 s`).
  - **Exakte Bluetooth-Verbindungslogik (`ScanBluetoothDevicesAsync` & `BluetoothDeviceDto`):** Filtert reine `AudioEndpoint`-Einträge (`Headphones`, `Speakers`) sowie interne `BTHENUM\{GUID}`-Dienstknoten strikt aus der Bluetooth-Geräteliste heraus und prüft den echten Hardware-Verbindungsstatus über `BluetoothAPIs.dll` (`fConnected`) sowie die Kernel-PnP-Eigenschaft `DEVPKEY_Device_IsConnected` (`{83da6326-97a6-4088-9453-a1923f573b29} 15`). Dadurch zeigt ausschließlich das tatsächlich aktive Gerät **`✓ Verbunden`** (Smaragdgrün `#34D399`), während gekoppelte, aber getrennte Geräte **`Gekoppelt`** (`#C5A059`) und neue Geräte in Reichweite **`Koppeln`** (`#9CA3AF`) anzeigen.
  - **60-Hz-VSync-Taktung & Live-FPS-Benchmark:** Kombiniert `timeBeginPeriod(1)`, `Timeline.SetDesiredFrameRate(60)` und `DispatcherPriority.Render` mit komplettem Pausieren verdeckter Hintergrund-Layer (`PauseDesktopBackgroundForGewerbeMonitor`). Erreicht im Live-Benchmark sowohl im **24K-Gold-Modus** als auch während des **5-Sekunden-Smaragd-Bestelleingangs** stabile **60 FPS** (`59,2–62,5 FPS`) bei **`0,05–0,11 ms`** CPU-Renderzeit pro Frame und **`0,3 %–2,8 %` Prozess-CPU-Last** (protokolliert in `LocalApplicationData\FunkenwerkOS\gewerbe_fps_benchmark.txt` sowie live unten links neben der Uhrzeit).
- **Dauerhafte Energiesparmodus-Sperre, Black-Screen-Schutz & 08:00-Morgen-Aufweck-Automatik (`OsHardwareAndAudioEngine.cs`)**:
  - **Kein ungewollter Standby (`0` Minuten Timeout auf allen Ebenen):** Alle sichtbaren und versteckten Windows-11-Energiespar-Timer (`monitor-timeout`, `standby-timeout`, `hibernate-timeout`, `VIDEOIDLE`, `CONSOLELOCK` `8ec4b3a5-6868-48c2-be75-4f3044be88a7`, `STANDBYIDLE`, `UNATTENDSLEEP` `7bc4a2f9-d8fc-4469-b07b-33eb785aaca0`) sind dauerhaft auf `0` (Niemals) gesetzt, ergänzt durch kontinuierliches `SetThreadExecutionState(ES_CONTINUOUS | ES_SYSTEM_REQUIRED | ES_DISPLAY_REQUIRED | ES_AWAYMODE_REQUIRED)`. Die automatische 22:00-Uhr-Zwangsabschaltung (`Funkenwerk_Abendruhe_2200`) wurde entfernt, sodass das Gerät im laufenden Betrieb niemals von selbst in den Energiesparmodus wechselt.
  - **Abgesicherter Autostart ohne Black-Screen-Risiko:** `HKLM\...\Winlogon\Shell` steht fest auf `explorer.exe` (mit `AutoAdminLogon = 1`), während `FunkenwerkOS.exe --shell --workspace gewerbe` über `HKCU\Software\Microsoft\Windows\CurrentVersion\Run\FunkenwerkOS` (geschützt durch `Local\FunkenwerkOS_SingleInstance_Mutex` in [`App.xaml.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-os/App.xaml.cs)) startet. Selbst bei Updates oder einem Neustart der App entsteht niemals ein schwarzer Bildschirm.
  - **Gezielte Nachtruhe & 08:00-Morgenstart:** Über den Button `☾ Nachtruhe (08:00)` programmiert der Mini-PC den Kernel-CMOS-RTC-Wecker (`CreateWaitableTimer` + `SetWaitableTimer(..., fResume: true)`) auf 08:00 Uhr morgens und wacht um Punkt 08:00 Uhr über den Hardware-RTC-Timer sowie `Funkenwerk_Morgenstart_0800` (`--wake-gewerbe`, `WakeToRun=true`) selbstständig wieder auf.


