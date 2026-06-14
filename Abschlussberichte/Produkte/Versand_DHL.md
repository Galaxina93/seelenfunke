# System-Dokumentation: DHL Geschäftskunden & Versandlogistik (Gifhorn, 38518)

Diese Dokumentation beschreibt die Rahmenbedingungen, Konditionen und logistischen Prozesse für den Paketversand über **DHL** bzw. alternative Versandplattformen für den Standort Gifhorn (PLZ 38518).

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Rechtssicherer, kosteneffizienter und hochautomatisierter Versand der in der Manufaktur gefertigten Unikate.
- **Zielgruppe:** Interne Verwendung zur Logistikplanung des Shops *Mein Seelenfunke*.
- **Kernanforderung:** Reibungslose Übergabe der fertig verpackten Ware an den Logistikdienstleister (DHL) unter Berücksichtigung lokaler Gegebenheiten in Gifhorn.

---

## 2. DHL Geschäftskunden-Modelle

### 2.1 Direkter DHL Geschäftskundenvertrag
DHL bietet ab einem Mindestvolumen von **200 Paketen pro Jahr** (ca. 4 Pakete pro Woche) den Abschluss eines direkten Geschäftskundenvertrags an.

- **Vorteile:**
  - Individuell verhandelte Versandtarife (deutlich unter den Preisen für Privatkunden, z. B. 3,50 € – 4,50 € netto pro Standardpaket).
  - Zugang zum **DHL Geschäftskundenportal** zur Label-Erstellung, Sendungsverfolgung und Retourenabwicklung.
  - Integration von **GoGreen** (klimaneutraler Versand) und Standardhaftung bis 500 € pro Paket.
- **Einschränkung:** Sinkt die jährliche Menge dauerhaft unter 200 Sendungen, behält sich DHL das Recht vor, den Vertrag aufzulösen.

### 2.2 Alternative: Versandplattformen (für Kleinversender)
Falls das jährliche Versandvolumen unter 200 Paketen liegt, empfiehlt sich die Nutzung von Aggregatoren/Schnittstellen-Partnern wie **Sendcloud**, **SimpleSell** oder **Shipcloud**:
- Keine Mindestmengen-Verpflichtung.
- Nutzung vergünstigter DHL-Rahmentarife direkt über die Plattform.
- Vollautomatisierte Generierung von Versandlabels direkt aus den Bestelldaten des Laravel-Shops.

---

## 3. Abholkonditionen & Zeiten (Gifhorn, 38518)

Für den Abtransport der Sendungen aus der Manufaktur in Gifhorn stehen verschiedene Optionen zur Verfügung:

### 3.1 Bedarfsabholung (Flexibel)
- **Ablauf:** Die Abholung wird bei Bedarf flexibel über das Geschäftskundenportal gebucht.
- **Buchungsfrist:** Orders müssen am Vorabend (üblicherweise bis 22:00 Uhr) erteilt werden.
- **Abholzeit:** Die Abholung erfolgt am Folgetag (Montag bis Samstag) im Rahmen der regulären Zustelltour des Gifhorner DHL-Zustellers. Ein exaktes Zeitfenster ist routenabhängig (meist zwischen 10:00 und 17:00 Uhr).
- **Kosten:** Häufig ab einer bestimmten Paketmenge pro Abholung kostenfrei oder gegen eine geringe Gebühr zubuchbar.

### 3.2 Regelabholung (Fest vereinbart)
- **Ablauf:** DHL holt an fest vereinbarten Werktagen (z. B. jeden Montag, Mittwoch und Freitag) automatisch und ohne Einzelbuchung ab.
- **Kosten:** In der Regel ab ca. 3.000 Paketen/Jahr kostenfrei inbegriffen, darunter gegen eine monatliche Pauschale (wird individuell vereinbart).

### 3.3 Zusteller-Mitnahme (Kostenlose Sofort-Option)
- **Ablauf:** Der tägliche DHL-Zusteller nimmt fertig frankierte Pakete bei der regulären Zustellung an der Adresse der Manufaktur direkt mit.
- **Kapazität:** Bis zu 10–20 Pakete pro Tag sind in der Regel problemlos möglich, sofern im Zustellfahrzeug noch Ladekapazität vorhanden ist.
- **Kosten:** Zu 100 % kostenfrei.

### 3.4 Eigene Abgabe in Gifhorn
Ergänzend können Pakete jederzeit an folgenden Stellen abgegeben werden:
- **DHL Packstationen** (24/7 zugänglich).
- **DHL Paketshops / Postfilialen** im Stadtgebiet Gifhorn (z. B. Steinweg, Limbergstraße etc.) zu den regulären Öffnungszeiten.

---

## 4. Technische Anbindung im E-Commerce Portal

Der Laravel-Shop kann über entsprechende Module (z. B. native DHL-API oder Shipcloud-Wrapper) angebunden werden:
- Automatischer Export der Lieferadresse bei Statusänderung einer Bestellung auf "bereit zum Versand".
- Rückübermittlung der **Tracking-Nummer** an den Kunden per E-Mail nach erfolgreicher Label-Generierung.
