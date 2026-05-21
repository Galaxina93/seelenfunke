# System-Dokumentation: Support / Tickets

Das Ticketsystem ist die primäre Plattform zur asynchronen Problembehandlung und Verwaltung komplexerer Kundenanfragen. Es bietet ein integriertes KPI-Dashboard, Datei-Anhänge und eine automatische Benachrichtigungskopplung über E-Mail, falls der Kunde offline ist.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Erfassung, Priorisierung und Abarbeitung von Kundenproblemen.
- **Kombinierte Ansichten:** Bietet eine geteilte Ansicht (`viewMode = 'split'`) zur parallelen Anzeige von Ticketliste und Chatverlauf oder eine klassische Tabellenübersicht (`'table'`).
- **Offline-Ausfallsicherung:** Automatische Zustellung von Admin-Antworten per E-Mail-Queue, falls der Kunde nicht live im Webportal eingeloggt ist.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`SupportTicket`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportTicket.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Support`)
- **Traits:** `WithPagination`, `WithFileUploads` (für Dateianhänge)

### 2.2 Datenbank-Modelle
- **`App\Models\Support\SupportTicket` (alias `SupportTicketModel`):**
  Repräsentiert das übergeordnete Ticket.
  - `ticket_number`: Eindeutige alphanumerische Ticket-Identifikation.
  - `customer_id`: Fremdschlüssel auf den Kunden (`customers`).
  - `order_id`: Optionale Referenz auf eine Bestellung zur direkten Verknüpfung.
  - `subject`: Betreff der Anfrage.
  - `status`: Status des Tickets (`open`, `answered`, `closed`).
  - `rating`: Optionale Bewertung durch den Kunden (1-5 Sterne).
- **`App\Models\Support\SupportTicketMessage`:**
  Der Nachrichtenverlauf des Tickets.
  - `support_ticket_id`: Fremdschlüssel auf das Ticket.
  - `sender_type`: Absendertyp (`customer` oder `admin`).
  - `message`: Textnachricht.
  - `attachments`: JSON-Array mit Pfaden zu hochgeladenen Dateien.
  - `is_read_by_admin`: Boolean-Lesestatus für Support-Mitarbeiter.
  - `is_read_by_customer`: Boolean-Lesestatus für den Kunden.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Live-Updates über WebSockets (Reverb)
- **Eingehende Nachrichten:**
  Die Komponente lauscht über das Attribut `#[On('echo-private:admin.tickets,.TicketMessageSent')]` auf neue Ticket-Nachrichten.
- **Automatische Aktualisierung:**
  Trifft ein WebSocket-Event ein, markiert `receiveMessage()` die Nachricht als gelesen (falls das Ticket gerade aktiv geöffnet ist) und aktualisiert die Ansicht per `$refresh`. Rote Ungelesen-Indikatoren ("Badges") im Backend-Menü werden bei Betrachten sofort gelöscht.

### 3.2 Senden von Antworten & E-Mail Fallback
Wenn ein Support-Mitarbeiter eine Antwort abschickt (`sendReply()`):
1. **Speichern & Anhänge:** Eingegebene Dateien werden im Verzeichnis `public/support/tickets/attachments` abgelegt. Die Nachricht wird mit dem Status `sender_type = admin` erstellt.
2. **Statuswechsel:** Das Ticket wird auf `status = answered` gesetzt.
3. **WebSocket Broadcast:** Die Nachricht wird live über das Event `TicketMessageSent` an den Browser des Kunden übertragen.
4. **E-Mail-Fallback bei Offline-Status:**
   - Das System prüft über einen Cache-Schlüssel (`Cache::has('is_online' . $customer_id)`), ob der Kunde aktuell im Webshop aktiv ist.
   - Ist der Kunde **offline**, wird die Antwort automatisch in die Laravel E-Mail-Queue eingereiht (`Mail::to(...)->queue(new SupportTicketUpdateMailToCustomer(...))`), um den Mitarbeiter nicht durch synchrone Sendevorgänge zu blockieren.

### 3.2 Ticket Schließen (`closeTicket`)
- Setzt den Status des Tickets dauerhaft auf `closed`. Dies aktualisiert den Zeitstempel `updated_at`, welcher zur Berechnung der durchschnittlichen Lösungszeit (`kpiAvgResolutionHrs`) herangezogen wird.
