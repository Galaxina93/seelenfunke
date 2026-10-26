# System-Dokumentation: Support / Kontaktanfragen

Das Modul Kontaktanfragen verarbeitet alle über das öffentliche Kontaktformular eingegangenen Anfragen von Interessenten und Kunden. Es fungiert als Lead-Erfassungs-System und bietet Werkzeuge zur schnellen Beantwortung über vorgefertigte Antwortvorlagen (Canned Responses).

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Strukturierte Erfassung und Abarbeitung allgemeiner Kunden- und Geschäftsanfragen.
- **Lead-Management:** Übernahme und Kategorisierung von potenziellen Neukundenanfragen.
- **Effizienzsteigerung:** Zeitersparnis bei Routineanfragen durch vordefinierte Textschablonen für häufige Anliegen (z. B. Preisanfragen, Detail-Rückfragen).

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`SupportContactFormComponent`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportContactFormComponent.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Support`)
- **Traits:** `WithPagination`, `handleMailsTrait` (für den Mailversand)

### 2.2 Datenbank-Modell
- **`App\Models\Support\SupportContactRequest`:**
  Erfasst die über das Kontaktformular gesendeten Daten.
  - `ticket_number`: Alphanumerische ID (z. B. zur Referenzierung in E-Mails).
  - `first_name` & `last_name`: Name des Absenders.
  - `email`: Kontakt-E-Mail-Adresse.
  - `subject`: Betreffzeile der Nachricht.
  - `status`: Status der Anfrage (`new`, `in_progress`, `waiting_for_customer`, `resolved`).
- **`App\Models\Support\SupportContactRequestMessage` (relationiert über `messages()`):**
  Speichert den Kommunikationsverlauf zwischen Admin und Kunde.
  - `sender_type`: Absender (`customer` oder `admin`).
  - `message`: Nachrichtentext.
  - `is_read_by_admin`: Status, ob die Nachricht vom Support-Team gelesen wurde.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Status- und Leseverlauf
- Sobald ein Support-Mitarbeiter eine Anfrage öffnet (`openRequest($id)`), wird der Status automatisch von `new` auf `in_progress` gesetzt, sofern es sich um eine Erstöffnung handelt.
- Alle ungelesenen Nachrichten des Kunden (`sender_type = customer`) werden als gelesen markiert (`is_read_by_admin = true`).
- Der Benachrichtigungs-Badge im Admin-Bereich wird per Event-Auslösung (`clear-admin-contactreq-badge`) sofort zurückgesetzt.

### 3.2 Vorlagen-System (Canned Responses)
Das System stellt über die Methode `insertCannedResponse($type)` drei vordefinierte Textschablonen bereit:
- **`busy` (Hohes Aufkommen):** Informiert den Kunden über eine leicht verzögerte Bearbeitungszeit aufgrund hohen Anfragevolumens.
- **`details` (Rückfrage):** Bittet den Kunden höflich um zusätzliche Spezifikationen zu seinem Anliegen.
- **`calculator` (Gravur-Kalkulator):** Verweist auf den interaktiven Gravur-Kalkulator des Shops (`/kalkulator`), um dem Kunden eine sofortige Preisberechnung zu ermöglichen.

### 3.3 Antwortversand & Mail-Kopplung (`sendReply`)
1. **Nachrichtenspeicherung:** Die Admin-Antwort wird im Nachrichtenverlauf der Anfrage persistiert.
2. **Statusaktualisierung:** Der Status der Anfrage wechselt auf `waiting_for_customer`.
3. **E-Mail-Versand:** Das System generiert eine E-Mail mit dem Betreff `Re: Deine Anfrage (TICKET_NUMMER)`. Unter Verwendung des `handleMailsTrait` wird die E-Mail basierend auf dem Template `global.mails.contact-form-reply` an den Kunden gesendet.
