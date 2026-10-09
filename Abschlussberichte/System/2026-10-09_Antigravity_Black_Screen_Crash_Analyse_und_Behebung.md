# Dokumentation: Antigravity UI Black-Screen Crash – Ursachenanalyse & Behebung

**Datum**: 09. Oktober 2026  
**Status**: Erfolgreich behoben & verifiziert  
**Betroffene Konversationen**: `572cb8cf-64ea-4ad7-99f5-3b2d4bdd81b6`, `e6650ae5-a284-4d19-8bb8-750aef17d12c`  
**Systemkomponenten**: Antigravity IDE (Electron / Chromium / React 18 / TanStack Router), VSCode URI Parser (`Pca`/`Qe`), SQLite Chat Storage (`conversations.db` / Protobuf)

---

## 1. Problem & Symptomatik

Sobald der Benutzer in der Antigravity IDE versuchte, die Konversationen `572cb8cf-64ea-4ad7-99f5-3b2d4bdd81b6` oder `e6650ae5-a284-4d19-8bb8-750aef17d12c` zu öffnen, trat ein kompletter UI-Crash auf:
- Das gesamte Antigravity-Fenster verlor seinen Inhalt (vollständiger "Black Screen" bzw. "White Screen", Leere ohne Menüs oder Interaktionselemente).
- Ein Neuladen oder Neustart der Anwendung half nicht: Sobald der betroffene Chat angewählt oder als aktiver Tab wiederhergestellt wurde, stürzte die Oberfläche reproduzierbar ab.
- Die Chats waren für den Entwickler unzugänglich und blockierten die Projektarbeit.

---

## 2. Exakte technische Root-Cause-Analyse

### 2.1 React 18 & TanStack Router Root Unmount
Die Benutzeroberfläche von Antigravity basiert auf **React 18** in Kombination mit dem **TanStack Router** für die Verwaltung des Navigationszustands und der Chat-Tabs.
Wenn innerhalb eines React-Komponentenbaums während des Renderings eine ungefangene Ausnahme (`Unhandled Runtime Exception`) geworfen wird, fängt die oberste Fehlerbehandlung (`Root Error Boundary`) diesen Fehler ab. Da der Fehler im zentralen Nachrichten-Viewer (`ConversationView` / `VirtualChatList`) auftrat, wurde der gesamte Anwendungsbaum de-mounted. Zurück blieb ein leeres DOM-Container-Element (`<div id="root"></div>`), was sich für den Benutzer als plötzlicher Totalausfall (Black Screen) darstellte.

### 2.2 Der VSCode URI-Parser und die 4-Slash-Anomalie (`file:////`)
Die Antigravity-IDE nutzt unter der Haube den standardmäßigen VSCode-URI-Parser (`URI.parse`, im minifizierten Bundle als `Pca.parse` bzw. `Qe` referenziert).

Bei der Anzeige von Chatnachrichten, Werkzeugaufrufen (`tool_calls`), Dateipfaden und klickbaren Links greift das UI auf eine Pfad-Normalisierungsfunktion (`rK`) zurück:
```javascript
// Pseudocode der internen Bundle-Logik
function normalizeLinkTarget(path) {
    if (path.startsWith("/")) {
        return `file://${path}`;
    }
    return path;
}
```

Wenn nun ein WSL-Pfad im Format `//wsl.localhost/Ubuntu/...` (Forward-Slashes) an ein Tool oder eine Nachricht übergeben wurde:
1. `path.startsWith("/")` evaluiert zu `true`.
2. Das System erzeugte daraus: `file:////wsl.localhost/Ubuntu/...` (mit **vier führenden Slashes**).
3. Der VSCode URI-Parser erwartet bei `file://` entweder:
   - Lokale Pfade: `file:///C:/Users/...` (drei Slashes, Authority ist leer, Path beginnt mit `/C:`).
   - UNC-Netzwerkpfade: `file://wsl.localhost/Ubuntu/...` (zwei Slashes gefolgt von Host/Authority `wsl.localhost`).
4. Durch die vier Slashes interpretierte der Parser:
   - `scheme`: `file`
   - `authority`: `""` (leer)
   - `path`: `//wsl.localhost/Ubuntu/...`
5. Beim anschließenden Zugriff auf `.fsPath` oder bei internen Pfad-Splits trat eine fatale Ausnahme auf:
   `TypeError: Cannot read properties of undefined` bzw. ein Invarianz-Bruch bei der Erkennung des Windows-Laufwerksbuchstabens.
6. Da diese Funktion synchron im Render-Zyklus der virtuellen Nachrichtenliste ausgeführt wurde, riss der Crash den gesamten React-Renderbaum mit.

### 2.3 Persistierung in SQLite (Protobuf Wire Format)
Die Chat-Nachrichten, Werkzeug-Ergebnisse und Zwischenschritte werden von Antigravity lokal in SQLite-Datenbanken gespeichert:
- Speicherort: `AppData\Roaming\Antigravity\...` bzw. `<appDataDir>\conversations.db`
- Spalte `state` / `payload`: Speicherung erfolgt nicht in reinem JSON, sondern als **binär serialisierter Protobuf-Blob**.
- Sobald ein solcher `file:////wsl.localhost`-String in einer Nachricht oder einem Tool-Ergebnis persistiert wurde, wurde dieser ungültige String bei jedem Öffnen des Chats erneut aus SQLite geladen und deserialisiert.
- Dadurch war der Chat persistent unbenutzbar („vergiftet“), bis die zugrundeliegenden Bytes bereinigt wurden.

---

## 3. Durchgeführte Reparatur & Bereinigungsverfahren

Um die Konversationen verlustfrei wiederherzustellen, wurde eine dreistufige Bereinigung durchgeführt:

### 3.1 Sicherung (Backups)
Vor jeglicher Byte- oder Datenbank-Manipulation wurden vollständige Sicherungskopien aller betroffenen Dateien erstellt:
- `conversations.db.bak`
- `transcript.jsonl.bak`
- `transcript_full.jsonl.bak`

### 3.2 Bereinigung der SQLite-Datenbank & Protobuf-Strings
- Ein gezieltes Bereinigungsskript durchsuchte die SQLite-Datenbank nach den betroffenen Konversations-IDs `572cb8cf-64ea-4ad7-99f5-3b2d4bdd81b6` und `e6650ae5-a284-4d19-8bb8-750aef17d12c`.
- Alle Vorkommnisse der fehlerhaften Schemata `file:////wsl.localhost/` und `file:////` wurden normalisiert:
  - Transformation zu validen UNC-Pfaden (`file://wsl.localhost/...` bzw. `\\wsl.localhost\...`).
  - Im Protobuf-Wire-Format (Wire-Typ 2: Length-delimited) wurden die Längenbytes bei Längenänderungen konsistent angepasst, um Korruption der Binärdaten zu verhindern.
- Anschließend wurde `PRAGMA wal_checkpoint(TRUNCATE);` und `VACUUM;` auf der SQLite-Datenbank ausgeführt, um sicherzustellen, dass alte unbereinigte Seiten nicht im Write-Ahead-Log (WAL) verweilen.

### 3.3 Bereinigung der Transkripte
In `<appDataDir>\brain\<conversation-id>\.system_generated\logs\`:
- Sowohl `transcript.jsonl` als auch `transcript_full.jsonl` wurden zeilenweise bereinigt.
- Alle Vorkommen von `file:////` wurden durch korrekte Pfadangaben ersetzt.

---

## 4. Verifikation über das Chrome DevTools Protocol (CDP)

Um sicherzustellen, dass die Chats in der laufenden Antigravity Electron-Applikation tatsächlich wieder reibungslos laden, wurde ein automatisierter Test via **Chrome DevTools Protocol (CDP)** auf Port `58562` ausgeführt:

1. **Test-Ablauf**:
   - Die Electron-App wurde über WebSocket angesprochen.
   - Das Navigations-Event zum Umschalten auf die Konversation `572cb8cf-64ea-4ad7-99f5-3b2d4bdd81b6` wurde ausgelöst.
   - Der DOM wurde auf Vorhandensein des virtuellen Listen-Containers (`.conversation-view` / React-Tree) überprüft.
   - Anschließend wurde derselbe Test für Konversation `e6650ae5-a284-4d19-8bb8-750aef17d12c` wiederholt.

2. **Testergebnisse**:
   - `hasConvoView: true` für beide Konversationen.
   - `badUrisCount: 0` (Keine fehlerhaften `file:////`-URIs mehr im gerenderten DOM auffindbar).
   - Die React-Fehlergrenze löste nicht aus.
   - Beide Chats lassen sich nun wieder vollständig, flüssig und ohne Datenverlust öffnen.

---

## 5. Prävention & Entwickler-Richtlinien für WSL-Pfade

Um ein erneutes Auftreten dieses Fehlers bei zukünftigen Interaktionen oder Werkzeugaufrufen strikt zu verhindern, gelten folgende Standards:

1. **Keine Forward-Slash-UNC-Pfade ohne URI-Schema**:
   - ❌ **Falsch**: `//wsl.localhost/Ubuntu/home/...`
   - ✅ **Richtig (Windows UNC)**: `\\wsl.localhost\Ubuntu\home\...`
   - ✅ **Richtig (RFC File URI)**: `file://wsl.localhost/Ubuntu/home/...`

2. **Relative und absolute Pfade in Tool-Calls**:
   - Werkzeuge, die auf das Dateisystem zugreifen (`view_file`, `replace_file_content`, `write_to_file`), müssen unter Windows immer entweder mit Windows-Laufwerksbuchstaben (`C:\...`) oder mit Windows-UNC-Pfaden (`\\wsl.localhost\...`) aufgerufen werden.

3. **Markdown-Links im Chat**:
   - Für klickbare Links im Chat-Fenster ist stets das Format `[Link-Text](file://wsl.localhost/Ubuntu/pfad/zur/datei)` oder `[Link-Text](file:///C:/pfad/zur/datei)` zu verwenden.
