# Dokumentation: Kunden-Dashboard (`customer-dashboard-component`)

Dieses Dokument dokumentiert die technische Struktur, das Design-System und die Benutzeroberfläche des Kunden-Dashboards im Kundenbereich (`/customer/dashboard`).

---

## 1. Übersicht & Zielsetzung

Das Kunden-Dashboard ([`customer-dashboard-component.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/customer/customer-dashboard-component.blade.php)) bietet registrierten Kunden eine zentrale Anlaufstelle zur Verwaltung ihrer Interaktionen mit dem Shop:
1. **Letzte Bestellungen:** Direkte Übersicht der jüngsten Käufe inklusive Status-Badges und Schnellzugriff auf Details.
2. **Konto- & Profildaten:** Anschrift, Kontaktdaten und Bearbeitungs-Modal.
3. **Gutschein- und Bonussystem:** Aktive Einkaufsgutscheine und Viral-Referral-Link („Freunde werben & 5 € Guthaben erhalten“).

---

## 2. Tabelle „Letzte Bestellungen“: Spacing & Interaktions-Design

### 2.1 Problemstellung (Oktober 2026)
Die Tabelle der letzten Bestellungen wies an den äußeren Rändern keine Innenabstände auf (`pl-0` / `pr-0`).
* Das „Aktion“-Auge-Icon klebte bündig an der rechten Begrenzungslinie und am Kartenrand.
* Beim Zeilen-Hover (`hover:bg-gray-50`) stießen die Inhalte an die Ränder der Hover-Fläche.
* Die Spaltenbreiten waren unausgewogen (enge Abstände links, leere Fläche rechts).
* Das Auge-Icon verlinkte nur allgemein auf die Bestellübersicht, statt die gewählte Bestellung direkt zu öffnen.

### 2.2 Technische Umsetzung
In [`customer-dashboard-component.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/customer/customer-dashboard-component.blade.php):
1. **Zellen- und Spalten-Padding:**
   - Kopfzeile:
     - `Bestellung`: `pb-3 pl-4 pr-3`
     - `Datum`, `Betrag`, `Status`: `pb-3 px-3`
     - `Aktion`: `pb-3 pl-3 pr-4 sm:pr-5 text-right`
   - Tabellenzeilen (`tbody tr td`):
     - Erste Spalte: `py-3.5 pl-4 pr-3 rounded-l-xl`
     - Mittlere Spalten: `py-3.5 px-3`
     - Letzte Spalte: `py-3.5 pl-3 pr-4 sm:pr-5 text-right rounded-r-xl`
2. **Harmonische Hover-Pills:**
   - Zeilen erhalten `group hover:bg-gray-50/80 dark:hover:bg-gray-800/20 transition-colors rounded-xl`.
   - Durch `rounded-l-xl` und `rounded-r-xl` auf den Randzellen leuchtet die Zeile beim Überfahren mit der Maus als sanfte Pille auf; Text und Icons haben rund 16–20 px Abstand zu den Rändern.
3. **Direktlink zur Einzelbestellung:**
   - Das Aktions-Auge verlinkt nun mit:
     ```blade
     <a href="{{ route('customer.orders', ['order' => $order->id]) }}" title="Bestellung ansehen" ...>
     ```
   - In [`CustomerOrdersComponent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Customer/CustomerOrdersComponent.php) wird `request()->get('order')` im `mount()` ausgewertet und die Bestellung sofort geöffnet.
4. **Mobile Scroll-Sicherheit:**
   - Mit `min-w-[540px]` scrollt die Tabelle auf Smartphones horizontal, ohne dass Texte abgeschnitten oder umbrochen werden.

---

## 3. Kompaktes 2-Ebenen-Layout & 3-Kachel-Design (Scroll-Freiheit auf Desktop & Tablet)

### 3.1 Problemstellung
Zuvor war das Kunden-Dashboard in eine 2/3-Spalte (nur „Letzte Bestellungen“) und eine extrem lange 1/3-Spalte unterteilt, in der „Profildaten“, „Freunde werben“, „Magisches Abenteuer“ und „Hilfe & Support“ untereinander aufgereiht waren. Dadurch entstand eine enorme vertikale Asymmetrie: Kunden mussten insbesondere auf Tablets und Desktop-Bildschirmen weit nach unten scrollen, um Aktionen durchzuführen. Zudem nahm der obere Header-Bereich („Hallo, ...“) viel vertikale Höhe ein.

### 3.2 Lösungsarchitektur
1. **Verschlankter Begrüßungs-Header („Mitgliederbereich“):**
   - Reduzierung des vertikalen Innenabstands auf `py-4 sm:py-5` (zuvor `py-10`).
   - Kompakte Avatar-Abmessungen (`w-14 h-14 sm:w-16 sm:h-16`) und dezente Schnellübersichts-Kacheln (`px-3.5 py-2`).
   - Spart ca. 120–150 px vertikalen Raum im ersten Viewport.
2. **Obere Zeile: Kerninformationen nebeneinander (Grid 2/3 + 1/3):**
   - **Links (2/3):** „Letzte Bestellungen“ mit direkter Bestellübersicht.
   - **Rechts (1/3):** „Profildaten“ mit Name, E-Mail, Anschrift und Bearbeiten-Button.
3. **Untere Zeile: 3-Kachel-Design nebeneinander (`grid-cols-1 md:grid-cols-3`):**
   - **Kachel 1 (Freunde werben):** Kompakte Rabattcode-Box, Kopier-Button und Direkt-Share via WhatsApp.
   - **Kachel 2 (Magisches Abenteuer):** Gamification-Teaser, Belohnungs-Hinweise und Aktions-Button zu den Minispielen.
   - **Kachel 3 (Hilfe & Support):** Support-Garantie (&lt; 24 Std.), Hilfethemen und direkter Absprung zum Ticket-System.
4. **Ergebnis:**
   - Auf Standard-Desktops und Tablets im Querformat ist das gesamte Kunden-Dashboard vollständig und ohne Scrollen im ersten Viewport erfassbar.

---

## 4. UI-Standards & Theme-Vorgaben

* **Standard-Modus:** Der „Hell“-Modus (Light Mode) ist im gesamten Frontend als verbindlicher Standard definiert.
* **Header-Logo:** Vergrößerte Darstellung für eine stärkere visuelle Markenpräsenz.
* **Schnelleintrag:** Kompaktes Layout ohne vertikales Scrollen; doppelte Bezeichnungen („Schnelleintrag hinzufügen“) wurden bereinigt.
