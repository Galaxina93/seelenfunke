# Konzept: Bestelllink bei kritischem Lagerbestand

## Ziel
Es soll möglich sein, bei Produkten mit kritischem Lagerbestand direkt einen Link zum Nachbestellen anzuzeigen.

## Datenstruktur-Erweiterung
Um dies umzusetzen, muss geklärt werden, wo der Bestelllink gespeichert wird.

### Option A: Speicherung am Produkt
- **Feld:** `order_url` in der Produkttabelle.
- **Vorteil:** Maximale Flexibilität. Jedes Produkt kann einen individuellen Link haben (auch zu verschiedenen Händlern, falls nötig).
- **Nachteil:** Höherer Pflegeaufwand, wenn ein Händler viele Produkte anbietet und sich der Link-Struktur ändert.

### Option B: Speicherung am Lieferanten
- **Feld:** `base_order_url` (oder ähnlich) in der Lieferantentabelle.
- **Vorteil:** Zentral verwaltet. Ändert sich der Link des Händlers, muss er nur an einer Stelle angepasst werden.
- **Nachteil:** Funktioniert nur gut, wenn der Händler eine generelle Bestellseite hat oder der Produktlink algorithmisch (z.B. über Artikelnummer) zusammensetzbar ist.

## Umsetzung im UI (Frontend)
In der Produktübersicht (`/admin/products`) wird die Lagerbestandsanzeige geprüft.
Wenn der Bestand `<=` kritischer Warnstufe ist:
1. Anzeige des Warnhinweises (wie bisher).
2. Zusätzliche Anzeige eines Links/Buttons "Nachbestellen".
3. Die URL des Links wird entweder direkt aus dem Produkt oder über den verknüpften Lieferanten bezogen.

## Nächste Schritte / Entscheidung
Es muss entschieden werden, ob Option A oder B (oder eine Hybridlösung) bevorzugt wird. Erst danach kann mit der technischen Umsetzung begonnen werden.