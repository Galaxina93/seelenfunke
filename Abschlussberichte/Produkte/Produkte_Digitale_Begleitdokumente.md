# System-Dokumentation: Optionale Digitale Begleitdokumente für Physische Produkte

**Kategorie:** Produkte & Bestellungen (`Produkte/`)  
**Status:** Produktiv & 100 % verifiziert  

---

## 1. Fachlicher Hintergrund & Zielsetzung

Physische Manufaktur-Unikate (wie das geplante *Echtgold Seelen-Herz* oder hochwertige *3D-Kristalle*) können optional um **digitale Begleitdokumente** wie ein **Echtheitszertifikat**, ein **Manufaktur-Zertifikat** oder **Pflegehinweise** ergänzt werden.

Wichtigste Abgrenzung:
- Das Produkt bleibt ein reguläres **physisches Produkt (`type = 'physical'`)** mit normalem DHL-Versand, Verpackungsgewicht und Lagerbestandsführung.
- Es wird **nicht** als reines Digitalprodukt (`type = 'digital'`) eingestuft, liefert dem Kunden aber nach dem Kauf automatisch die zugehörigen Begleitdokumente über den geschützten Kundenportal-Kanal aus.

---

## 2. Technische Architektur & Datenfluss

### 2.1 Produkt-Editor (`/admin/products` $\rightarrow$ Schritt 2 „Medien“)
- In [`_edit_step_2.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/partials/edit-partials/_edit_step_2.blade.php) wurde innerhalb der Karte **Galerie & Marketing** direkt unterhalb von *Produktbilder* und *Produktvideos* die Sektion **Digitale Dokumente** ergänzt.
- **Clean-Design & Schnell-Titel:**
  - Ein `!`-Hover-Tooltip erläutert die Funktionsweise ohne dauerhaften Text-Overload.
  - Über die Schnellwahl-Buttons (`+ Echtheitszertifikat`, `+ Pflegehinweise`, `+ Manufaktur-Zertifikat`) oder das freie Bezeichnungsfeld kann der sichtbare Dokumententitel vor dem Upload gesetzt und jederzeit nachträglich per Inline-Input geändert werden (`updateDigitalDocumentTitle()`).
- **Geschützter Speicherort (`local`-Disk):**
  - Alle hochgeladenen Dokumente (`pdf, zip, rar, jpg, jpeg, png, webp` bis 50 MB) werden außerhalb des öffentlichen Webroots unter `storage/app/private/produkte/products-secure/{slug}/documents/` gespeichert.
  - Die Metadaten (`id`, `title`, `path`, `filename`, `extension`, `size_bytes`, `uploaded_at`) werden im JSON-Feld `configurator_settings['digital_documents']` am [`Product`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Product/Product.php)-Modell abgelegt (`getDigitalDocuments()`, `hasDigitalDocuments()`, `normalizeDigitalDocumentItem()`).

### 2.2 Bestell-Snapshot & Rückwirkende Bereitstellung (`OrderOrderItem`)
- **Kaufzeitpunkt-Snapshot:** Beim Anlegen einer Bestellung in [`HandlesOrderCreation.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderCheckout/Traits/HandlesOrderCreation.php) werden vorhandene Begleitdokumente in `order_order_items.configuration['digital_documents']` eingefroren. Bestellt ein Gast ohne aktives Konto ein Produkt mit digitalen Begleitdokumenten, wird automatisch die Konto-Aktivierungseinladung (`needs_password_change = true`) vorbereitet.
- **Kombination mit Live-Produktdaten:** [`OrderOrderItem::resolveDigitalDocuments()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Order/OrderOrderItem.php) führt den Kaufzeitpunkt-Snapshot und `Product::getDigitalDocuments()` zusammen (dedupliziert nach ID und Pfad). Dadurch erscheinen auch nachträglich am Produkt hinterlegte Zertifikate sofort bei bereits bestehenden Bestellungen.
- **Lagerbestands-Schutz bei Storno:** In [`OrderOrder::handleCancellation()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Order/OrderOrder.php) wird `digital_documents` als System-Metadatum behandelt, sodass unpersonalisierte Standardware mit Zertifikat bei einer Stornierung weiterhin regulär ins Lager zurückgebucht wird.

### 2.3 Kundenportal (`/orders` $\rightarrow$ „Dokumente“) & Gehärteter Download-Kanal
- **Anzeige im Kundenportal:** In [`customer-orders-component.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/customer/customer-orders-component.blade.php) werden alle digitalen Begleitdokumente der Bestellpositionen im Bereich **Dokumente** aufgelistet (auch dann, wenn die Rechnung noch erstellt wird).
- **Sicherheitsprüfungen beim Download ([`DigitalProductDownloadController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Customer/DigitalProductDownloadController.php)):**
  - Route: `GET /digital-product/{item}/download?doc={docId}` (`digital-product.download`).
  - **Autorisierung:** Nur der verifizierte Käufer der Bestellung (`customer_id`), eine gültige signierte URL oder ein Admin/interner Mitarbeiter erhält Zugriff (IDOR-Schutz).
  - **Zahlungsstatus:** Für Kunden wird der Download erst freigeschaltet, sobald `payment_status === 'paid'` ist und die Bestellung weder storniert (`cancelled`) noch erstattet (`refunded`) wurde. Vor Zahlungseingang erscheint im Portal das Badge `Nach Zahlung`.
  - **Dateisicherheit:** Strikte Pfad-Validierung auf `produkte/products-secure/`, Symlink-Abwehr sowie Auslieferung mit `X-Content-Type-Options: nosniff` und `Cache-Control: no-store`.

### 2.4 Professioneller Hinweis in den Bestell-E-Mails
- Über [`FormatsECommerceData::toFormattedArray()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Traits/FormatsECommerceData.php) werden `has_digital_documents` und `digital_documents_list` an alle E-Mail-Templates übergeben.
- Sobald `has_digital_documents === true` ist, blenden [`new_order_mail_to_customer.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/global/mails/new_order_mail_to_customer.blade.php), [`payment_received.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/global/mails/payment_received.blade.php) und [`new_order_shipped_to_customer.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/global/mails/new_order_shipped_to_customer.blade.php) automatisch folgenden Hinweis im Schwarz-Gold-Design ein:
  > **📄 Digitale Dokumente zu deiner Bestellung:**  
  > Zu deiner Bestellung gehören digitale Begleitdokumente. Du findest diese jederzeit in deinem **Kundenportal** in der Bestellansicht unter **Dokumente**.

---

## 3. Automatisierte Qualitätssicherung

Die gesamte Pipeline (Admin-Upload, Umbenennen, Löschen, Persistenz beim Speichern, Anzeige im Kundenportal unter „Dokumente“, 403-Sperre vor Zahlung und bei Fremdzugriff, 200-Download nach Zahlung sowie E-Mail-Rendering) wird durch die Feature-Testsuite [`ProductDigitalDocumentsTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/ProductDigitalDocumentsTest.php) geprüft.
