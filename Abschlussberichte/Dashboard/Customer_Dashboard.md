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

## 3. UI-Standards & Theme-Vorgaben

* **Standard-Modus:** Der „Hell“-Modus (Light Mode) ist im gesamten Frontend als verbindlicher Standard definiert.
* **Header-Logo:** Vergrößerte Darstellung für eine stärkere visuelle Markenpräsenz.
* **Schnelleintrag:** Kompaktes Layout ohne vertikales Scrollen; doppelte Bezeichnungen („Schnelleintrag hinzufügen“) wurden bereinigt.
