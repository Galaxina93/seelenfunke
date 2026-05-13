# 🤖 Telegram Agenten Integration - Handbuch

Die Telegram-Integration von Seelenfunke erlaubt es deinen autonomen KI-Agenten (wie Funkira), als eigenständige Telegram-Bots zu agieren. In diesem Messenger haben sie durch den `GeminiAgent`-Zugriff **die gleiche Macht** und das **gleiche Langzeitgedächtnis** wie im Web-Dashboard. Sie können Datenbankabfragen machen, Tickets beantworten und Systeme aus dem Chat heraus steuern.

Dieses Handbuch führt dich Schritt für Schritt durch die Einrichtung.

---

## 🏗️ Phase 1: Den Bot bei Telegram erstellen

Zuerst musst du bei Telegram offiziell einen Identitäts-Eintrag für deinen Bot erstellen. Telegram wickelt das über einen Meta-Bot namens **BotFather** ab.

1. **Telegram öffnen:** Öffne deine Telegram-App (Handy oder Desktop).
2. **BotFather suchen:** Suche in der globalen Suche nach `@BotFather` (achte auf den blauen Haken für den offiziellen Bot).
3. **Konversation starten:** Klicke unten auf "Starten" (oder tippe `/start` in den Chat).
4. **Neuen Bot anlegen:**
   - Sende den Befehl: `/newbot`
   - **Name wählen:** Der BotFather fragt nun nach dem Namen. Dies ist der "Anzeigename", den Nutzer sehen (z.B. `Funkira CEO` oder `Seelenfunke Support`).
   - **Username wählen:** Nun musst du einen einzigartigen Benutzernamen vergeben. Dieser **muss** auf "bot" oder "Bot" enden (z.B. `FunkiraBot` oder `seelenfunke_support_bot`). Dieser Name muss weltweit eindeutig sein.
5. **Den Token kopieren:**
   - Nach erfolgreicher Erstellung schickt dir der BotFather eine Nachricht mit einem extrem langen HTTP API Token.
   - Der Token sieht in etwa so aus: `8535490760:AAFPxLpfzPaVFhOobpYK-rVLbqLzvBXBPQ4`
   - **⚠️ ACHTUNG:** Halte diesen Token geheim! Wer diesen Token kennt, kann deinen Bot steuern.

**(Optional) Bot optisch ansprechend machen:**
Im Chat mit BotFather kannst du mit Befehlen wie `/setuserpic` (Profilbild ändern), `/setabouttext` (Kurztext) und `/setdescription` (Willkommenstext) deinen Bot verschönern.

---

## ⚙️ Phase 2: Den Token in Seelenfunke hinterlegen

Nun müssen wir dem Seelenfunke-System sagen, welcher KI-Agent zu welchem Telegram-Bot-Token gehört.

### Weg A: Über das Backend UI (Für Live-Systeme & Admins)
1. Öffne im Seelenfunke Web-Dashboard den **Agenten-Konfigurator** (den Bereich, wo du neue Agenten erschaffst oder bearbeitest).
2. Wähle im Listenmenü einen Agenten (z.B. Funkira) aus.
3. Im Bereich "Identität & Rolle" findest du nun das Feld **"Telegram Bot Token"**.
4. Füge den Token vom BotFather hier ein und klicke auf "Speichern".

### Weg B: Über den Seeder (Für lokale Entwicklung)
Wenn du deine Datenbank häufig über `migrate:fresh` zurücksetzt, ist es sinnvoll, den Token fest im Quellcode zu speichern.
1. Öffne die Datei `database/seeders/AiAgentSeeder.php`
2. Suche in der `$agentsData` Liste nach dem gewünschten Agenten (z.B. `Funkira`).
3. Trage den Token als Wert in der Array-Struktur ein:
   ```php
   'telegram_bot_token' => '8535490760:AAFPxLpfzPa...',
   ```
4. Speichere die Datei und setze die Datenbank einmal neu auf:
   ```bash
   php artisan migrate:fresh --seed
   ```

---

## 📡 Phase 3: Webhook aktivieren (Telegram mitteilen, wo dein Laravel liegt)

Telegram arbeitet mit einem "Webhook-System". Das bedeutet: Immer wenn jemand in Telegram eine Nachricht an deinen Bot schreibt, klopft der Telegram-Server blitzschnell bei deinem Server an, übermittelt die Nachricht im Hintergrund und wartet auf ein "Ok".

Um das zu koppeln, bringe wir Telegram die Adresse deines Servers bei:

### Auf dem Stage-Server (Testumgebung)
Verbinde dich via SSH auf den Stage Server (`stage.mein-seelenfunke.de`) und tippe dort im Verzeichnis:
```bash
php artisan telegram:register-webhooks --domain="https://stage.mein-seelenfunke.de"
```

### Auf dem Live-Server (Internet)
Hier ist das Projekt bereits online erreichbar.
Gehe über SSH in dein Live-Server-Terminal und tippe:
```bash
php artisan telegram:register-webhooks --domain="https://mein-seelenfunke.de"
```

**Erwarteter Konsolen-Output:**
```
Registering Webhooks on domain: https://deine-domain...
Registering Agent: Funkira...
✅ Success! Webhook active.
```

---

## 💬 Phase 4: Kommunikation starten

Deinem System ist nun alles bekannt!
1. Nimm dein Handy in die Hand und öffne Telegram.
2. Gib in der weltweiten Suchleiste den '@Benutzernamen' deines Bots ein.
3. Klicke auf **Starten**.

Die Nachricht wandert nun folgenden Pfad:
`Telegram App -> Telegram Server -> Dein Server (/api/telegram/webhook/{token}) -> TelegramAgentController -> GeminiAgent Analyse & Tool-Ausführung -> Datenbank -> API Antwort an Telegram Server -> Telegram App`

✅ **Das Besondere:** Durch das clevere Session-Mapping (wir speichern die Telegram-`chat_id` als Session-ID) behält der Agent im Messenger unendliches Kontext-Wissen zu exakt diesem Chat, genau als wärst du im Web-Modul eingeloggt. Es verfällt nach keinen 2 Stunden!

---

## 🛡️ Phase 5: Sicherheit durch ID-Whitelisting (Freigabeliste)

Um komplett zu verhindern, dass Fremde über Telegram mit deinem Agenten agieren, gibt es eine eingebaute Firewall.
Im Agenten-Editor findest du direkt unter dem Bot-Token das Feld **"Erlaubte Chat IDs (Whitelist)"**.

1. **Zero-Trust-Sperre:** Standardmäßig ist dein Agent für die Außenwelt aus Sicherheitsgründen vollkommen **gesperrt**. Solange das Feld leer ist, wird jede Anfrage (auch von dir) blockiert.
2. **Deine ID herausfinden:** Schreibe dem Bot bei Telegram einfach "Hallo". 
3. **Der Rauswurf:** Das System blockiert dich sofort und antwortet: *"Du bist nicht berechtigt... Deine Telegram-ID lautet: 123456789"*.
4. **Schranke öffnen:** Kopiere exakt diese genannte Ziffer ("123456789"), füge sie ins Whitelist-Feld des Agenten-Editors ein und speichere ab. Ab jetzt antwortet dir die KI vollumfänglich!
*(Mehrere Accounts/Mitarbeiter kannst du einfach mit einem Komma trennen: `123456, 987654`)*

>**Tipp:** Wenn du den Agenten zu reinen Testzwecken für wirklich **jeden** öffentlich zugänglich machen willst, trage als Whitelist einfach nur das Symbol `*` (Sternchen) ein.

---

## 🧯 Fehlerbehebung (Troubleshooting)

- **Der Bot antwortet nicht!**
  - Prüfe, ob dein lokaler HTTP-Tunnel noch läuft und nicht abgelaufen ist. 
  - Führe den `telegram:register-webhooks` Befehl zur Sicherheit noch einmal aus.
  - Schau in die Datei `storage/logs/laravel.log`. Fehler bei der KI-Ausführung werden dort unter "Telegram Agent Loop Failed" gespeichert.

- **Ich habe den Token geändert / Einen neuen Bot generiert.**
  - Überschreibe den Token in der Datenbank, füge ihn via UI/Seeder neu an und **vergiss nicht**, das Command `php artisan telegram:register-webhooks ...` erneut auszuführen, da Telegram alte Webhooks sonst noch an verwaiste Tokens sendet.

- **Telegram Webhook kann nicht registriert werden**
  - Stelle sicher, dass du eine URL mit **HTTPS** bei `--domain="..."` angegeben hast. HTTP-URLs werden von Telegram für Webhooks kategorisch geblockt.
