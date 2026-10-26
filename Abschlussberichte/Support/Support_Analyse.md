# System-Dokumentation: Support / Analyse

Die Support-Analyse bietet umfassende Kennzahlen (KPIs) zur Qualitätssicherung und statistischen Auswertung aller Supportkanäle (Tickets, Live-Chats und Kontaktanfragen) über frei wählbare Zeiträume.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Bereitstellung von Management-Entscheidungsgrundlagen bezüglich des Support-Aufkommens und der Kundenzufriedenheit.
- **Kanal-Verteilung:** Visualisierung des prozentualen Anteils der Support-Kanäle (Tickets vs. Live-Chats vs. Kontaktanfragen).
- **Zufriedenheits-Monitoring:** Aggregation von Sterne-Bewertungen aus Chatverläufen und geschlossenen Tickets zur Messung des Net Promoter Scores (NPS).
- **Effizienz-Metriken:** Berechnung der durchschnittlichen Bearbeitungsdauer (Resolution Time) zur Leistungsoptimierung.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`SupportAnalytics`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportAnalytics.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Support`)

### 2.2 Relevante Datenbank-Modelle
- **`App\Models\Support\SupportTicket`:** Liefert Ticketvolumen, Lösungsdauer, Statusverteilung und Kundensupportbewertungen.
- **`App\Models\Support\SupportCustomerChat`:** Liefert Live-Chat-Aufkommen, Antwortzeiten, KI-Konfidenzwerte und Chatbewertungen.
- **`App\Models\Support\SupportContactRequest`:** Liefert die Menge der über das Kontaktformular eingegangenen Anfragen.

---

## 3. Berechnungslogik & KPIs

### 3.1 Datumsfilterung
- Der Zeitraum kann über die Eigenschaft `dateRange` via URL-Query-Parameter gesteuert werden (Unterstützte Werte: `7`, `30`, `90`, `365` Tage sowie `all` für die gesamte Historie).
- Die Methode `updateDateRange()` berechnet dynamisch die Start- und Endzeitpunkte (`dateFrom`, `dateTo`).

### 3.2 KPI-Berechnung (`computeAnalytics`)
- **Support-Volumen-Verlauf (Volume Chart):**
  Zusammenfassung aller Tickets, Chats und Kontaktanfragen gruppiert nach Tag (`Y-m-d`) oder Monat (`Y-m`) bei größeren Zeiträumen, um Lastspitzen und Trends aufzuzeigen.
- **Durchschnittliche Ticket-Bewertung:**
  Arithmetisches Mittel aller abgegebenen Sterne-Bewertungen (`rating` von 1 bis 5) für Tickets, bei denen diese vorliegen.
- **Durchschnittliche Lösungszeit (`kpiAvgResolutionHrs`):**
  Differenz in Stunden zwischen dem Erstellungsdatum (`created_at`) und dem letzten Update (`updated_at`) aller geschlossenen Tickets (`status = closed`).
- **Antwortqualität & Konfidenz:**
  Statistische Auswertung des durchschnittlichen KI-Konfidenzwerts (`ai_confidence_score`) und der durchschnittlichen Antwortzeit in Millisekunden (`avg_response_time_ms`) aus den Kundenchats, um die Qualität des Support-Agenten zu tracken.

---

## 4. Sicherheitssteuerung
- Die Methode `mount` überprüft die Authentifizierung des Admins über den Admin-Guard (`Auth::guard('admin')->check()`).
- Unautorisierte Zugriffe werden sofort mit einer HTTP 403 (Abbruch) abgewiesen.
