# Support & System – 3-Tab Benutzerverwaltung („Medien & Dokumente“) & Admin-initiierte Support-Direktnachrichten

**Stand:** 06.11.2026  
**Abteilungen:** `System` (`/admin/users`) & `Support` (`/admin/support-tickets`)  
**Status:** Produktiv & automatisiert getestet (`CustomerMediaAndAdminTicketTest`, `ProductDigitalDocumentsTest`)

---

## 1. Sofort-Speicherung & `✓ Gespeichert`-Feedback bei Produkt-Begleitdokumenten (`/admin/products`)
* In [`ProductCreate.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCreate.php) und [`_edit_step_2.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/partials/edit-partials/_edit_step_2.blade.php) werden hochgeladene, umbenannte oder gelöschte digitale Begleitdokumente ohne separaten Klick auf „Produkt speichern“ sofort in `products.configurator_settings['digital_documents']` persistiert.
* Ein grünes **`✓ Gespeichert`**-Badge bestätigt jede Aktion unmittelbar im Kopfbereich der Kachel „Digitale Dokumente“.

---

## 2. 3-Tab-Benutzerprofil & „Medien & Dokumente“ (`System -> Benutzer`)
* Der Inline-Editor in [`SystemUserManagement.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/System/SystemUserManagement.php) und [`system-user-management.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/system/system-user-management.blade.php) ist in drei interaktive Tabs gegliedert:
  1. **`Account & Sicherheit`**: Rolle, Name, E-Mail, Passwort, Verifizierungsstatus, 2FA-Status/Reset sowie das vollständige Profil- & DSGVO-Änderungsprotokoll.
  2. **`Standort & Profil`**: Privat/B2B-Umschaltung, Adresse, Kontaktdaten, interne Admin-Notiz sowie Seelenfunken-/Gamification- und DSGVO-Einwilligungen.
  3. **`Medien & Dokumente` („Hinterlegte Dateien“)**:
     * **Komplettübersicht aller Kundendateien**: Zeigt sämtliche individuell hinterlegten Kundendokumente, Produkt-Zertifikate aus Bestellungen, Rechnungen/Gutschriften (`AccountingInvoice`) sowie Konfigurator-Gravurvorschauen an.
     * **Aktiver Sofort-Upload für den Kunden**: Über Schnellwahl-Buttons (`+ Echtheitszertifikat`, `+ Pflegehinweise`, `+ Manufaktur-Zertifikat`, `+ Kulanz-Beleg`), ein freies Titelfeld und eine optionale Bestell-Zuordnung kann der Admin Dateien (`PDF`, `ZIP`, `RAR`, `JPG`, `PNG`, `WEBP` bis 50 MB) direkt beim Kunden hinterlegen (inkl. `✓ Gespeichert`-Badge).
     * **Speicherung & Bereitstellung im Kundenportal**: Die Dateien liegen geschützt im privaten Storage (`storage/app/private/produkte/products-secure/customer-files/{customer_id}/`) und werden über [`Customer.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Customer/Customer.php) (`getAdminUploadedDocuments()`, `addAdminUploadedDocument()`, `removeAdminUploadedDocument()`) sowie [`DigitalProductDownloadController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Customer/DigitalProductDownloadController.php) (`GET /customer-document/{customer}/{doc}/download`) ausgeliefert. Für den Kunden erscheinen sie sofort im Kundenportal unter **Bestellungen -> Dokumente**.

---

## 3. Admin-initiierte Support-Tickets („Spieß umdrehen“ in `/admin/support-tickets`)
* Anstelle eines separaten Parallel-Chats nutzt das System die bestehende Echtzeit-Ticket-Infrastruktur in [`SupportTicket.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Support/SupportTicket.php) und [`support-ticket.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/support/support-ticket.blade.php) bidirektional:
  * Über den Button **`+ Nachricht an Kunde`** im Support-Desk oder den 1-Klick-Shortcut **`Nachricht senden`** in `System -> Benutzer` (`/admin/support-tickets?createForCustomer={id}`) kann der Admin jederzeit aktiv ein neues Ticket bzw. eine Direktnachricht für einen Kunden eröffnen (inkl. optionaler Bestell-Verknüpfung und Dateianhängen).
  * **Dynamische Kunden-Suche mit Ähnlichkeitserkennung & Skalierung (`getCustomerSearchResultsProperty`)**:
    * Sobald der Admin in das Feld **„Empfänger (Kunde) *“** tippt, lädt ein zweistufiger Such- und Phonetik-Algorithmus dynamisch passende Kunden.
    * Berücksichtigt neben exakten und Teilstring-Treffern (Vorname, Nachname, kombinierter Vollname, E-Mail, Kunden-ID) auch **ähnliche Schreibweisen, Lautgleichheiten und Tippfehler** (`c/k` wie `Klara` <-> `Clara`, `ai/ei/ay/ey` wie `Mayer` <-> `Meier`, `ph/f` wie `Stephan` <-> `Stefan`, Umlaute sowie Levenshtein-Distanz <= 2) und markiert diese transparent mit dem Badge **`Ähnlich`**.
    * Bei großen Kundenbeständen skaliert das Dropdown sauber über eine scrollbare Liste mit Initialen-Avatar, Kurz-ID (`KDN-...`), E-Mail, Ort sowie einer Statuszeile (`Top 15 von X Kunden`).
  * **Sofortiges Ausblenden des roten Benachrichtigungspunkts (ohne Seiten-Reload)**:
    * Im Admin-Backend ([`list-item.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/components/forms/list-item.blade.php), [`admin-navigation.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/components/backend/admin-navigation.blade.php), [`backend_layout.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/components/layouts/backend_layout.blade.php)) verschwindet der rote Punkt unter `Support -> Tickets` unmittelbar beim Anklicken/Öffnen eines Tickets (`x-on:clear-admin-ticket-badge.window`).
    * Im Kundenportal ([`customer_layout.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/components/layouts/customer_layout.blade.php), [`game-global-stats-header.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/customer/gamification/game-global-stats-header.blade.php), [`customer-tickets-component.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/customer/customer-tickets-component.blade.php)) verschwindet der rote Punkt unter `Hilfe & Support` (Desktop-Header & Mobile-Bar) ebenfalls verzögerungsfrei beim Öffnen des Tickets (`x-on:clear-ticket-badge.window`).
  * Beim Kunden leuchtet sofort der rote Benachrichtigungspunkt unter **Hilfe & Support** auf (Echtzeit via `TicketMessageSent`), und bei Offline-Status wird automatisch die E-Mail-Benachrichtigung (`SupportTicketUpdateMailToCustomer`) versendet.
