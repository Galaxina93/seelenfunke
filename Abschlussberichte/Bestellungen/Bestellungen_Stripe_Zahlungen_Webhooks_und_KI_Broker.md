# Stripe Zahlungs-Architektur, Webhook-Härtung & Zero-Secret KI-Broker

**Stand:** 16.11.2026  
**Beteiligte Module:** [`HandlesStripePayment.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderCheckout/Traits/HandlesStripePayment.php), [`StripeWebhookController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/StripeWebhookController.php), [`OrderCheckoutSuccess.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderCheckout/OrderCheckoutSuccess.php), [`OrderQuoteRequests.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderQuoteRequests.php), [`StripeAgentExecutionService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/StripeAgentExecutionService.php), [`McpTresorBroker.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/McpTresorBroker.cs) & [`FunkenwerkTresor.exe`](file:///C:/Users/konta/Desktop/Funkenwerk%20Collection/Funkenwerk%20Tresor/FunkenwerkTresor.exe)  
**Teststatus:** **`64/64` PHPUnit-Tests (`536 Assertions`)** & **`62/62` `FunkenwerkMcp`-Selbsttests (`100 % GRÜN`)**

---

## 1. Überblick & Sicherheits-Prinzipien

Die Stripe-Anbindung von `Mein-Seelenfunke` trennt strikt zwischen dem **Shop-Zahlungsverkehr** (Checkout, Angebote, Webhooks, GoBD-Gutschriften) und dem **Zero-Secret KI-Agenten-Zugriff** über den lokalen `Funkenwerk Tresor`:

1. **Zero-Secret im KI-Kontext:** Weder `sk_...`- noch `rk_...`- oder `whsec_...`-Schlüssel erscheinen jemals im Klartext in KI-Chats, Logs oder Tool-Ausgaben. Der eingeschränkte Agenten-Schlüssel (`rk_test_...` bzw. später `rk_live_...`) liegt DPAPI- und `AES-256-GCM`-verschlüsselt in `%LOCALAPPDATA%\FunkenwerkTresor\ai_broker_vault.bin` und wird ausschließlich im C#-Arbeitsspeicher (`ZeroMemory`) gegen `https://api.stripe.com:443/v1/...` eingesetzt.
2. **Kein Geldabfluss durch KI-Schlüssel (Least-Privilege):** Der Restricted Key besitzt **0 Schreibrechte** auf Auszahlungen (`Payouts = Lesen`), Überweisungen (`Transfers = Keine`), Kartenausgabe (`Issuing = Keine`), Kontoeinstellungen (`Balance Settings = Keine`) oder Webhooks (`Webhook Endpoints = Lesen`).
3. **Manipulationssichere Webhooks & Checkout-Absicherung:** Alle Zahlungseingänge prüfen kryptografisch die `Stripe-Signature` (`HMAC-SHA256`), erzwingen `EUR` sowie Cent-genaue Mindestbeträge (`amount >= order.total_price`), verarbeiten doppelte Stripe-Zustellungen strikt idempotent und schützen bezahlte Bestellungen vor verspäteten Cancel-Events.

---

## 2. Least-Privilege Berechtigungsmatrix des Stripe Restricted Keys (`Mein-Seelenfunke KI-Agenten & Shop`)

Von insgesamt `178` Stripe-Berechtigungszeilen sind exakt **33 gezielte Rechte** aktiviert (`145` verbleiben hart auf **`Keine`**):

### 2.1 `Schreiben` (13 operative Ressourcen für Shop, Bestelli, Produkti, Marketi & Buchi)
* **Kern-Zahlungsverkehr & Widerrufe (`Bestelli`):** `Payment Intents`, `Charges and Refunds` (Teil-/Voll-Erstattungen), `Customers`, `Customer Sessions`
* **B2B-Angebote & Zahlungslinks (`Bestelli`):** `Checkout Sessions`, `Quotes`, `Payment Links`, `Shipping Rates`
* **Sortiment & Rabattaktionen (`Produkti` & `Marketi`):** `Products`, `Prices`, `Coupons`, `Promotion Codes`
* **Steuerberater- & Monatsberichte (`Buchi`):** `Financial Reports` (erforderlich für `reporting.report_runs.create` ohne Zugriff auf Auszahlungen)

### 2.2 `Lesen` (20 Diagnose-, Buchhaltungs- & Watchtower-Ressourcen)
* **FYRST-Abgleich & Cent-Gebühren (`Buchi`):** `Balance`, `Balance Transaction Sources` (liefert exakte Stripe-Gebühren pro Zahlung), `Payouts` & `Payout Settings` (strikt Read-Only), `Invoices`, `Credit Notes`, `Tax IDs`, `Tax Rates`, `Files`
* **Sicherheit, Betrugsschutz & Selbstheilung (`Bestelli` & `Systemi`):** `Events`, `Webhook Endpoints, Event Destinations` (nur Lesen, kein Umbiegen von Ziel-URLs möglich), `Payment Disputes`, `Reviews` (Radar), `Health Alerts`, `3D Secure Authentications`, `Confirmation Tokens`, `Mandates`, `Payment Methods`, `Setup Intents`, `Payment Records`

---

## 3. Zero-Secret Stripe-Broker in `FunkenwerkMcp` (`--stripe-api`)

In [`McpTresorBroker.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/McpTresorBroker.cs) und [`McpSecurityGuard.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-mcp/McpSecurityGuard.cs) steht der gehärtete CLI-Broker bereit:

```powershell
& 'C:\Users\konta\AppData\Local\Funkenwerk\SentinelCli\FunkenwerkMcp.exe' --stripe-api <preset> --entry "Stripe Sandbox"
```

* **Freigegebene Presets (`AllowedStripePresets`):** `sandbox_audit`, `balance`, `list_balance_transactions`, `list_payment_intents`, `get_payment_intent` (`--object-id pi_...`), `list_charges`, `list_payouts`, `list_disputes`, `list_webhooks`, `list_events`, `list_products`, `list_coupons`.
* **SSRF- & DLP-Härtung:** Strikt gepinnt auf `https://api.stripe.com:443/v1/...` (`AllowAutoRedirect = false`). Ausgehende Antworten werden automatisch von allen `sk_*`, `rk_*`, `whsec_*` sowie `pi_*_secret_*` / `seti_*_secret_*`-Mustern bereinigt (`[REDACTED-BY-FUNKENWERK-DLP]`).

---

## 4. Vollständige Stripe-Webhook-Konfiguration & Code-Härtung

### 4.1 Aktive Webhook-Endpunkte & 8 abonnierte Events
Beide registrierten Endpunkte (`we_1Syw71GnukHKxpOePRpP3Jb0` -> `https://stage.mein-seelenfunke.de/stripe/webhook` und `we_1UXr5rGnukHKxpOeKk5vCjl5` -> `https://mein-seelenfunke.de/stripe/webhook`) empfangen nun alle **8 benötigten Zahlungs-, Storno- und Sicherheits-Events** (zuvor war nur `checkout.session.completed` aktiv):

| # | Stripe Event | Verarbeitung in [`StripeWebhookController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/StripeWebhookController.php) |
| :--- | :--- | :--- |
| 1 | **`payment_intent.succeeded`** | Prüft `currency === 'eur'` und Cent-Betrag (`>= order.total_price`), ruft idempotent `$order->completePayment($intentId)` auf (Rechnung, Lagerabzug, Bestätigungs-Mail) und konvertiert ggf. verknüpfte Angebote (`OrderQuoteRequest`). |
| 2 | **`checkout.session.completed`** | Verarbeitet bezahlte Hosted-Checkout-Sessions & Zahlungslinks (`payment_status === 'paid'`), inklusive Manipulationsschutz bei Angebots-Übernahmen (`total_price + discount_amount >= gross_total`). |
| 3 | **`checkout.session.async_payment_succeeded`** | Schließt verzögerte Zahlungsarten (z. B. SEPA-Lastschrift / Überweisungsträger) erst nach tatsächlichem Geldeingang ab. |
| 4 | **`checkout.session.async_payment_failed`** | Protokolliert fehlgeschlagene asynchrone Zahlungen sauber ohne Fehl-Freigabe der Bestellung. |
| 5 | **`payment_intent.payment_failed`** | Protokolliert den Zahlungsfehler geordnet (`HTTP 200`), damit der Kunde im Checkout die Zahlungsart wechseln kann. |
| 6 | **`payment_intent.canceled`** | Storniert ausschließlich unbezahlte `pending`-Bestellungen (`handleCancellation` gibt reservierte Bestände/Gutscheine frei); bereits bezahlte (`paid`) Bestellungen bleiben zu 100 % geschützt. |
| 7 | **`charge.refunded`** | Synchronisiert Teil- und Voll-Erstattungen idempotent über [`StripeAgentExecutionService::syncWebhookRefund`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/StripeAgentExecutionService.php) inkl. GoBD-konformer Gutschrift (mit Schutz gegen Fehlgutschriften auf unbezahlten Orders). |
| 8 | **`charge.dispute.created`** | Erzeugt sofort ein dedupliziertes Security-Event (`security:stripe_dispute_created:<id>`) im `SystemLog` sowie eine Prioritäts-Aufgabe im Operativen Status (`/admin/dashboard`). |

---

## 5. Automatisierte Testabdeckung (`100 % GRÜN`)

1. **Laravel / PHPUnit (`mein_php_server`):**
   * Neuer End-to-End-Webhook-Test [`StripeWebhookControllerTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/StripeWebhookControllerTest.php): **`10/10` Tests bestanden** (echte `t=...,v1=...` `HMAC-SHA256`-Signaturprüfung, Replay-/Manipulationsabwehr `HTTP 400`, Idempotenz, Quote-Konvertierung, Storno-Schutz für bezahlte Orders, Refund-Gutschrift & Dispute-Alarm).
   * Komplette Bestell-, Checkout- & Stripe-Regression (`tests/Feature/Order`, `tests/Feature/Stripe`, `CheckoutAuthSecurityTest`, `ShopProcessTest`): **`64/64` Tests bestanden (`536 Assertions, 0 Fehler`)**.
2. **Funkenwerk MCP (`FunkenwerkMcp.exe --self-test`):**
   * **`62/62` Sicherheits- & E2E-Checks bestanden** (inkl. Check 62 für `api.stripe.com:443`-Pinning, `rk_test_`-Erkennung, Preset-Allowlist & DLP-Redaction von `rk_`, `sk_`, `whsec_` und `client_secret`).
3. **Zero-Log-Baseline:**
   * Aktive Haupt-Logdateien (`storage/logs/laravel.log` & `twilio_bridge.log`) stehen nach Testlauf und Archivierung exakt auf **`0 Bytes`**.

---

## 6. Checkliste für den späteren Wechsel in den Live-Betrieb (`01.11.2026`)
1. Im Stripe Live-Konto unter `Entwickler -> API-Schlüssel` einen identisch konfigurierten Restricted Key (`33` Rechte) erstellen und als separaten Tresor-Eintrag **`Stripe Live`** ablegen.
2. Im Stripe Live-Konto prüfen, dass der Live-Webhook `https://mein-seelenfunke.de/stripe/webhook` ebenfalls exakt diese **8 Events** abonniert hat und sein `whsec_...` in der Live-`.env` hinterlegt ist.
