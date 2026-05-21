# System-Dokumentation: Support / Chats

Die Chat-Infrastruktur ermöglicht Echtzeit-Kundenchats über WebSockets (Mittwald Reverb/Pusher-Protokoll) sowie die mobile Steuerung und automatische Kundeninteraktion über den Telegram-Bot.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Bereitstellung eines performanten Echtzeit-Support-Kanals zur Beantwortung von Kundenanfragen.
- **Kombination Mensch/KI:** Kunden chatten im Webshop primär mit einem intelligenten KI-Support-Agenten (z. B. "Funki"). Bei Bedarf oder geringer Konfidenz (`ai_confidence_score`) kann das Gespräch an einen menschlichen Mitarbeiter eskaliert werden (`needs_employee`).
- **Telegram Bot Integration:** Autonome Agenten können als eigenständige Telegram-Bots konfiguriert werden, um direkt über Telegram Kundenanfragen zu bearbeiten oder administrative Aufgaben auszuführen (Langzeitkontext & Whitelist-Sicherheit).

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente (Admin-Dashboard)
- **Klasse:** [`SupportChats`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportChats.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Support`)

### 2.2 Datenbank-Modelle
- **`App\Models\Support\SupportCustomerChat`:**
  Erfasst die übergeordnete Chat-Sitzung.
  - `session_token`: Eindeutiger Browser-Token des Kunden.
  - `status`: Status des Chats (`open`, `needs_employee`, `resolved_auto` für Troll/Spam-Erkennung, `resolved` und `resolved_admin`).
  - `top_topic`: Hauptthema des Gesprächs (automatisch durch KI klassifiziert).
  - `mentioned_product`: Erwähntes Produkt (zur gezielten Beratung).
  - `rating`: Sternebewertung (1-5).
  - `ai_confidence_score`: Konfidenzwert der KI-Antworten.
  - `avg_response_time_ms`: Durchschnittliche Reaktionszeit der KI in Millisekunden.
  - `ai_summary`: Kurze inhaltliche Zusammenfassung durch die KI.
- **`App\Models\Support\SupportCustomerChatMessage`:**
  Repräsentiert die einzelne Nachricht innerhalb einer Sitzung.
  - `support_customer_chat_id`: Fremdschlüssel auf den Chat.
  - `sender`: Absendertyp (`customer`, `ai`, `admin`).
  - `message`: Textinhalt der Nachricht.

---

## 3. WebSocket & Realtime (Laravel Reverb)
- **Protokoll:** Pusher-kompatible WebSockets über **Laravel Reverb**, das auf dem Mittwald-Server gehostet wird.
- **Echtzeit-Aktualisierung:** Bei neuen Kundennachrichten wird ein Broadcast-Event geschossen, wodurch das Livewire-Dashboard der Support-Mitarbeiter per Event-Listener sofort aktualisiert wird, ohne dass neu geladen werden muss.

---

## 4. Telegram-Bot-Integration

KI-Agenten können als Telegram-Bots agieren, wodurch sie mobil dieselben Fähigkeiten wie im Web-Dashboard erhalten.

### 4.1 Registrierung & Datenfluss
1. Ein Bot wird über den `@BotFather` in Telegram erstellt.
2. Der API-Token wird beim KI-Agenten im Backend-Editor hinterlegt oder via `AiAgentSeeder.php` gesät.
3. Über das Artisan-Command `php artisan telegram:register-webhooks --domain="..."` wird die HTTPS-Adresse des Laravel-Projekts bei der Telegram API als Webhook registriert.
4. Eingehende Nachrichten an den Telegram-Bot werden an `/api/telegram/webhook/{token}` gesendet und im `TelegramAgentController` verarbeitet.
5. Die Telegram `chat_id` dient als Session-ID, sodass die Unterhaltung einen unendlichen Kontext besitzt.

### 4.2 Sicherheitssteuerung (Whitelist)
- Um Missbrauch zu verhindern, besitzt jeder Telegram-Agent eine **ID-Whitelist (Freigabeliste)** im Agenten-Editor.
- Bei Erstkontakt eines unautorisierten Nutzers wird die Anfrage blockiert und dem Nutzer seine Telegram-ID mitgeteilt.
- Durch Eintragen dieser ID in die Whitelist (kommagetrennt) oder ein `*` (für globale Freigabe) wird der Zugriff für diesen Benutzer freigeschaltet.
