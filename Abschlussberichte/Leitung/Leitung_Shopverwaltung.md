# System-Dokumentation: Leitung / Shopverwaltung (Übersicht)

Die administrative Leitung und Shopverwaltung bildet das operative und strategische Kontrollzentrum des Laravel-Projekts. Sie bündelt alle administrativen Werkzeuge, steuert die Rechte- und Rollenverteilung im Backend und stellt das einheitliche Design- und Navigationssystem bereit.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Zentrale Bereitstellung einer konsistenten, rollenbasierten Navigation und Benutzeroberfläche für Administratoren, Abteilungsleiter und KI-Agenten.
- **Einheitliches Theming:** Dynamische farbliche Kennzeichnung der abteilungsspezifischen Backend-Ansichten (z. B. Gold für Leitung, Blau für Support, etc.) zur besseren Orientierung der Nutzer.
- **Modulare Struktur:** Trennung in administrative Leitungsfunktionen (CEO-Ebene) und operative Shopverwaltungs-Bereiche (Support, Produkte, Marketing, Bestellungen, Buchhaltung).

---

## 2. Technische System-Architektur

### 2.1 Navigations-Service
- **Klasse:** [`BackendNavigationService`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Navigation/BackendNavigationService.php)
- **Funktion:** Definiert die hierarchische Struktur des Backends. Jedes Element besitzt:
  - Eine eindeutige `id` und eine `route`.
  - Ein standardisiertes `icon`.
  - Eine `ai_department_id` zur Zuordnung von KI-Agenten zu bestimmten Abteilungen.
- **Breadcrumbs:** Die Methode `getBreadcrumbs` berechnet anhand der aktuellen URL dynamisch den Navigationspfad (z. B. `Systemverwaltung / Admin / Leitung / Kalender`).

### 2.2 Layout & Theming Trait
- **Layout-Datei:** `resources/views/components/layouts/backend_layout.blade.php`
- **Theming Trait:** `App\Livewire\Traits\WithDepartmentTheming`
- Jede Livewire-Komponente im Backend nutzt diesen Trait und deklariert die Eigenschaft `$themingDepartment`.
- Der Trait stellt CSS-Klassen und Farbvariablen basierend auf der Abteilung bereit, wodurch sich das UI-Farbschema (z. B. Buttons, Rahmen, Akzente) automatisch anpasst.

---

## 3. Struktur der Shopverwaltung

Die Shopverwaltung gliedert sich laut Navigation in folgende Hauptbereiche:

1. **Leitung (CEO / Management):**
   - Fokus: E-Mail-Postfach, CRM-Kontakte, Tagesroutinen, Aufgaben-Manager, Einkaufsliste, Kalender und Linktree-Pflege.
2. **Support:**
   - Fokus: Support-Kennzahlen, Live-Kundenchats (Pusher/Reverb & Telegram), Ticketsystem, Kontaktanfragen und Twilio-Telefonie-Anbindung.
3. **Produkte:**
   - Fokus: Retouren- und Schadensabwicklung, Produkt-CRUD, Design-Vorlagen, Lieferantenverwaltung, Kundenbewertungen, Preissuchmaschinen-Crawler und Verpackungsmaterial-Konfigurator.
4. **Marketing:**
   - Fokus: UTM-Kampagnentracking, Landing-Page-Builder, Instagram-Postings, Google Ads Schnittstelle, Newsletter-Versand, Gutscheincodes und Blog-CMS.
5. **Bestellungen:**
   - Fokus: Sales-Statistiken, Bestellabwicklung, Warenkorb-Verfolgung, B2B-Angebote und Widerrufsmanagement.
6. **Buchhaltung:**
   - Fokus: Einnahmen-Überschuss-Rechnung, Bankkonto-Umsatzabgleich, Steuervoranmeldungen, Fixkosten-Verträge, Gutschriften, Ausgangsrechnungen, OCR-Belegerfassung für variable Kosten und Liquiditätsplanung.

---

## 4. Berechtigungen & Sicherheit
- Der Zugang zur administrativen Shopverwaltung ist über den Route-Prefix `/admin/*` geschützt.
- Der Zugriff erfordert die Authentifizierung über das Admin-Guard-System mit entsprechenden Mitarbeiter-Rollen (siehe *System / Benutzer* Dokumentation).

---

## 5. Kommandozentrale & 10-Jahres-Firmenreise (`/admin/dashboard`)

- **Komponente:** [`MasterAnalytics`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Master/MasterAnalytics.php)
- **Views:** [`master_scores.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/master/master-analytics-partials/master_scores.blade.php) & [`company-journey-tile.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/master/master-analytics-partials/company-journey-tile.blade.php)
- **Mobiler Layout-Schutz (360px bis 412px Viewport):**
  - Alle Kachel-Container, Flex-Zeilen und Grid-Zellen besitzen durchgängig `min-w-0`, `max-w-full`, `overflow-hidden` und sauberes Wortumbruch-Verhalten (`break-words`, `[overflow-wrap:anywhere]`).
  - Die Status-Überschrift (`Aktueller Status`), der Startdatum-/Visions-Block sowie die Karten-Header stapeln und umbrechen auf Smartphones mehrzeilig ohne Rechts-Überlauf.
  - Der Sub-Tab-Switcher (`Umsatzplan`, `1-Laser-Etappen`, `Skalierung`, `Alle`) bricht auf Mobilgeräten in ein vollflächiges `grid-cols-1 sm:grid-cols-2 xl:flex` mit `whitespace-normal` um.
  - Der schwebende Fortschritts-Indikator nutzt `clamp(80px, ..., calc(100% - 80px))` mit `max-w-[160px] sm:max-w-none`; die Rand-Pins (`0 €` und `1 Mio`) sind bündig ausgerichtet und Zwischen-Pins auf schmalen Viewports ausgeblendet (`hidden md:flex`).
  - Die Maschinen-Kapazitätskarten, deren 3er-Kennzahlen-Zeilen (`grid-cols-1 sm:grid-cols-3`), die 10-Jahres-Roadmap (`grid-cols-1 lg:grid-cols-2`) und die Visions-Karten (`grid-cols-1 md:grid-cols-3`) rendern im Clean-Design (Light- & Dark-Mode, `var(--theme-color)`, ohne KI-Gedankenstriche) ausbruchsfrei.
- **Automatisierter Regressionstest:** [`CompanyJourneyMobileLayoutTest`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Shop/Master/CompanyJourneyMobileLayoutTest.php)

