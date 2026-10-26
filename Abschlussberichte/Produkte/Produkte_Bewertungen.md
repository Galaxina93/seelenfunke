# System-Dokumentation: Produkte / Bewertungen (Kundenrezensionen & Moderation)

Das Bewertungs-Modul ermöglicht es verifizierten Käufern, gekaufte Produkte mit Sternen (1-5), Text und Bild-/Video-Anhängen zu bewerten. Es steuert den Freigabeprozess (Moderation) im Backend und berechnet die durchschnittlichen Kundenbewertungen für die Storefront.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Erhöhung des Vertrauens (Social Proof) auf Produktseiten durch authentische Kundenbewertungen bei gleichzeitiger Vermeidung von Spam und Fake-Bewertungen.
- **Kauf-Verifizierung:** Nur Kunden, die ein Produkt nachweislich erworben und bezahlt haben, dürfen eine Bewertung abgeben.
- **Moderations-Workflow:** Bewertungen mit Medien-Uploads (Bildern oder Videos) werden vor der Veröffentlichung manuell oder automatisiert auf Richtlinienkonformität geprüft.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ProductReviews`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductReviews.php)
- **Layout:** Standard-Kundenlayout (Storefront) mit Zuweisung des Sales-Theming.
- **Admin-Klasse:** `ProductControlReviews` (im Backend zur Freigabe/Ablehnung).

### 2.2 Datenbank-Modell
- **`App\Models\Product\ProductReview`:**
  Erfasst die einzelne Kundenbewertung.
  - `product_id`: Referenzierte Ware.
  - `customer_id`: Verfasser der Bewertung.
  - `rating`: Sterneanzahl (Integer, 1 bis 5).
  - `title`: Optionaler Kurzbetreff.
  - `content`: Textinhalt (Pflichtfeld, min. 10, max. 1000 Zeichen).
  - `media`: JSON-Array mit Pfaden zu hochgeladenen Bildern und Videos.
  - `status`: Moderationsstatus (`pending` bei Medien-Uploads zur Prüfung, `approved` zur sofortigen Anzeige, `rejected` bei Ablehnung).

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Verifizierung & Berechtigungsprüfung
Vor der Erstellung einer Bewertung wird in `submitReview()` geprüft:
1. **Login-Status:** Ist der Benutzer als Kunde authentifiziert (`Auth::guard('customer')->check()`)?
2. **Kaufverifikation:** Hat der Kunde das betroffene Produkt in einer erfolgreich bezahlten und abgeschlossenen Bestellung erworben (`OrderOrder` im Status `completed`, `shipped`, `processing` oder `pending` und `payment_status = paid`)?
3. **Duplikatsvermeidung:** Es darf nur eine Bewertung pro Kunde und Produkt existieren.

### 3.2 Medien-Upload und Bildoptimierung
- Kunden können maximal 3 Dateien (Bilder/Videos, max. 10MB) pro Bewertung anhängen.
- **Intervention Image Integration:**
  Hochgeladene Bilder werden zur Server-Optimierung eingelesen, automatisch aufrecht gedreht (`orientate()`), ins JPG-Format konvertiert und mit 80% Kompressionsqualität unter `public/produkte/reviews/` abgespeichert.

### 3.3 Moderationsstatus
- Enthält eine Bewertung Medien-Uploads, wird ihr Status initial auf `pending` gesetzt. Sie erscheint erst nach der Freigabe durch den Admin in der Storefront.
- Reine Textbewertungen werden direkt auf `approved` gesetzt und sofort veröffentlicht.
- Bei Freigabe wird der Gamification-Fortschritt des Kunden aktualisiert (z. B. Inkrementierung des Titels "Botschafter").
