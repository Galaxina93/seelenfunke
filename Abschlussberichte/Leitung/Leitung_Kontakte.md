# System-Dokumentation: Leitung / Kontakte (Adressbuch & KI-Profile)

Das Kontakt-Modul dient der Verwaltung von Personen und Ansprechpartnern im ERP-System. Es fungiert als erweitertes Adressbuch, das wichtige persönliche Informationen speichert, sowie als Integrationspunkt für die künstliche Intelligenz des Systems.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Speicherung und Bearbeitung von Kontakten (Geschäftspartner, Kunden, Lieferanten oder private Beziehungen).
- **KI-Integration:** Speicherung von systemspezifischen Anweisungen und gelernten Fakten über Personen, auf die die KI bei Anrufen (Telefonie) oder Chats direkt zugreifen kann.
- **Geburtstags-Synchronisation:** Automatischer Export von Geburtstagen in das Kalender-Modul.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ManagementContacts`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementContacts.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Leitung`)

### 2.2 Datenbank-Modelle
- **`App\Models\Management\ManagementContact`:**
  Der Datenbestand eines Kontaktes. Felder:
  - `first_name`, `last_name`, `nickname`: Personenidentifikation.
  - `relation_type`: Beziehungstyp (z. B. Kunde, Partner, Lieferant).
  - `avatar_path`: Profilbild-Pfad im Storage (`leitung/person_profiles`).
  - `links`: JSON-Array für Social-Media-Links oder Websites.
  - `birthday`: Datumsfeld (automatisch gecastet).
  - `email`, `phone`: Kontaktdaten.
  - `street`, `postal_code`, `city`, `country`: Anschrift.
  - **`system_instructions`:** Spezifische Handlungsanweisungen für die KI, wie sie mit diesem Kontakt interagieren soll (z. B. "Sehr höflich sein, bevorzugt E-Mail").
  - **`ai_learned_facts`:** Durch die KI im Laufe von Gesprächen autonom gelernte Fakten über die Person.
- **`App\Models\Management\ManagementCalendarEvent`:**
  Wird verwendet, um Geburtstage als wiederkehrende Kalenderereignisse zu hinterlegen.

---

## 3. Kernfunktionen

### 3.1 Profil-Verwaltung & Favorisierung
- Kontakte können über eine integrierte Suchmaske durchsucht werden.
- Die Ansicht teilt sich in eine Master-Liste (mit Favoriten-Status `is_favorite` ganz oben) und eine detaillierte Profil-Detailkarte.
- Ein Avatar-Upload ermöglicht das Zuweisen von Fotos.

### 3.2 Geburtstags-Kalender-Synchronisation (`syncBirthdaysToCalendar`)
- Die Methode durchläuft alle Kontakte.
- Für jeden Kontakt mit hinterlegtem Geburtsdatum wird ein `ManagementCalendarEvent` erzeugt oder aktualisiert.
- **Identifikationsmerkmal (ICS UID):** `contact_birthday_{id}` stellt sicher, dass keine Duplikate entstehen.
- Die Termine werden als ganztägig (`is_all_day = true`), jährlich wiederkehrend (`recurrence = 'yearly'`) und der Kategorie `birthday` zugeordnet.
- Gibt dem Benutzer im UI direktes Feedback über synchronisierte Einträge und listet Namen mit fehlenden Geburtstagen auf.

---

## 4. Struktur der KI-Felder

Die Felder `system_instructions` und `ai_learned_facts` sind für das System-AI-System von kritischer Bedeutung:

```json
{
  "system_instructions": "Liefert Rohstoffe für Verpackungsmaterial. Verhandelt zäh. KI soll auf pünktliche Zahlung verweisen.",
  "ai_learned_facts": "- Hat am 12. Mai angerufen.\n- Bevorzugt Lieferungen am Donnerstag.\n- Benötigt Rechnungen im PDF-Format per Mail."
}
```

Diese Daten werden bei der Live-Telefonie über Twilio/Gemini per API in den System-Prompt des Live-Agenten eingespielt, sobald die Telefonnummer des Kontakts erkannt wird.
