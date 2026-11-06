# Bestellungen - Warenkörbe

Dieses Dokument beschreibt das technische System zur Erfassung, Verwaltung und Nachverfolgung aktiver sowie verlassener Warenkörbe (Abandoned Carts) im Laravel-Projekt. Es erläutert das administrative Kontrollzentrum im Backend sowie den automatisierten Mahnprozess für Kunden mit gefüllten, aber nicht abgeschlossenen Warenkörben.

## Zielsetzung
Das Warenkorb-Tracking dient der Senkung der Kaufabbruchquote. Über eine optische Klassifizierung und manuelle oder automatische E-Mail-Erinnerungen können Kunden dazu bewegt werden, ihren Einkauf zu vollenden.

---

## Beteiligte Komponenten & Modelle

### Backend-Livewire-Controller
* [OrderShoppingCarts](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderShoppingCarts.php)
  * Stellt die Benutzeroberfläche zur Administration aller existierenden Warenkörbe bereit.
  * Verwaltet die Ampel-Grenzwerte und steuert den E-Mail-Mahnprozess.

### Frontend-Komponenten & Services
* [Cart](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Cart/Cart.php)
  * Die Frontend-Livewire-Komponente zur Darstellung des aktuellen Kunden-Warenkorbs.
* [CartService](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/CartService.php)
  * Kapselt die Berechnungslogik (Zwischensummen, Steuern, Mengenstaffelrabatte und Versandkosten je nach Zielland).
* [AbandonedCartReminder](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Mail/AbandonedCartReminder.php)
  * Die Mailable-Klasse für die Erinnerungs-E-Mail.

### Modelle
* [Cart](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Cart/Cart.php)
  * Speichert den Warenkorb-Header: `session_id`, `customer_id` (falls angemeldet), `reminder_email_sent_at` und Zeitstempel.
* [CartItem](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Cart/CartItem.php)
  * Einzelpositionen mit Produkt-ID und `quantity`.

---

## Technischer Datenfluss & Ampel-Grenzwerte

Im Backend wird jeder Warenkorb nach der Inaktivitätsdauer klassifiziert. Die Schwellenwerte können in der Administration konfiguriert werden und werden in der Tabelle `system_settings` persistiert:
1. **Gelbe Zone (`cart_abandoned_yellow_hours`, Standard: 3 Std.)**:
   * Der Warenkorb wurde seit mehr als X Stunden nicht mehr aktualisiert, aber das Limit für die rote Zone ist noch nicht erreicht. Signalisiert einen potenziellen Abbruch.
2. **Rote Zone (`cart_abandoned_red_hours`, Standard: 24 Std.)**:
   * Der Warenkorb liegt seit über Y Stunden unberührt im System. Signalisiert einen definitiven Abbruch.

```mermaid
graph TD
    A[Warenkorb Aktualisierung] --> B{Inaktivität in Stunden?}
    B -- "< Gelber Schwellenwert" --> C[Status: Aktiv - Grün]
    B -- ">= Gelber & < Roter Schwellenwert" --> D[Status: Gefährdet - Gelb]
    B -- ">= Roter Schwellenwert" --> E[Status: Verlassen - Rot]
    E --> F[Aktion: Erinnerungs-E-Mail senden]
```

---

## Administrative Eingriffsmöglichkeiten

Der Administrator hat vollen Zugriff auf den Inhalt fremder Warenkörbe und kann helfend eingreifen, um z. B. telefonische Kundenbestellungen manuell zu begleiten:
* **Mengenänderung**: Über `increment()`, `decrement()` oder manuelle Eingabe (`updateQuantity()`) wird die Stückzahl angepasst. Hierbei berechnet der `CartService` im Hintergrund automatisch Staffelpreise (`tierPrices`) und Steuersätze neu.
* **Artikel entfernen (`removeItem`)**: Einzelne Posten können gelöscht werden. Sinkt die Artikelanzahl im Warenkorb auf 0, wird der gesamte Cart-Eintrag aus der Datenbank entfernt.
* **Warenkorb löschen (`deleteCart`)**: Entfernt verwaiste Carts restlos aus der Datenbank.

---

## Kaufabbruch-Mahnwesen (`sendReminderEmail`) & Dashboard-To-Do

Befindet sich ein registrierter Kunde im Warenkorb (E-Mail vorhanden):
1. **Rechtssicherheit (§ 7 Abs. 2 UWG):** Sowohl der manuelle Versand (`sendReminderEmail`), der Cronjob (`shop:send-abandoned-cart-reminders`) als auch die Dashboard-Aufgabe in [`AnalyticsService::checkAbandonedCarts()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AnalyticsService.php) prüfen über `$customer->allowsCartReminders()`, ob ein verifiziertes Werbe-Opt-In vorliegt und kein Opt-Out existiert.
2. **Kein Geister-To-Do bei fehlendem Opt-In:** Warenkörbe von Gästen ohne E-Mail oder Kunden ohne Werbe-Opt-In lösen im Operativen Status (`/admin/dashboard`) keinen Warn-Eintrag aus und werden in `/admin/shopping-carts` mit gelber Ampel und dem Hinweis `Kein Opt-In` dargestellt, sodass die gespeicherten Artikel des Kunden erhalten bleiben.
3. **Bereinigung leerer Warenkorb-Hüllen:** Leere Warenkörbe (`0 Artikel`) werden in `OrderShoppingCarts` ausgeblendet (`whereHas('items')`) und nach über 1 Stunde Inaktivität automatisch bereinigt.
4. Bei erfolgreichem Versand wird `reminder_email_sent_at` gesetzt (grüne Ampel), wodurch der Warenkorb sofort aus den offenen To-Dos verschwindet.
