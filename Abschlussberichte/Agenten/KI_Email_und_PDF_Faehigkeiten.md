# KI-Fähigkeiten: E-Mail-Versand und PDF-Generierung

Diese Dokumentation bietet eine vollständige Übersicht aller Stellen im Seelenfunke-System, an denen die Künstliche Intelligenz (KI) in der Lage ist, E-Mails zu versenden oder PDF-Dokumente zu generieren und zu verarbeiten.

---

## 1. Zentrale E-Mail- und PDF-Infrastruktur

### A. Mailable-Klassen (E-Mail-Vorlagen)
Die E-Mail-Infrastruktur für KI-Agenten befindet sich unter `app/Services/AI/Mails/`:
1. **[AiAgentMessageMail](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Mails/AiAgentMessageMail.php)**: 
   * **Verwendung**: Allgemeiner E-Mail-Versand (Texte, Laser-Dateien, Behandlungspläne, Systemberichte, Absturzberichte).
   * **Designs**: 
     * `seelenfunke` (CI-Design mit Logo & Farben) -> Ansicht: `global.mails.ai.ai-agent-message`
     * `generic` (neutrales Design) -> Ansicht: `global.mails.ai.ai-agent-message-generic`
2. **[AiHolidayPlanMail](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Mails/AiHolidayPlanMail.php)**: 
   * **Verwendung**: Urlaubsplaner, Personen-Dossiers und Kamera-Snapshots.
   * **Designs**: 
     * `seelenfunke` -> Ansicht: `global.mails.ai.ai-holiday-plan`
     * `generic` -> Ansicht: `global.mails.ai.ai-holiday-plan-generic`
3. **[AiMapSummaryMail](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Mails/AiMapSummaryMail.php)**: 
   * **Verwendung**: Geodaten-Zusammenfassungen von Orten.
   * **Designs**: 
     * `seelenfunke` -> Ansicht: `global.mails.ai.ai-map-summary`
     * `generic` -> Ansicht: `global.mails.ai.ai-map-summary-generic`

### B. PDF-Engine & Vorlagen
Die Generierung erfolgt über den DomPDF-Wrapper `Barryvdh\DomPDF\Facade\Pdf`. 
Die Blade-Layouts befinden sich in:
* **`resources/views/pdf/`**:
  * `holiday_plan.blade.php` (Urlaubsplanung)
  * `map_summary.blade.php` (Kartenübersichten)
  * `persona_dossier.blade.php` (Profil-Dossier)
  * `places_list.blade.php` (Detaillierte Ortslisten)
* **`resources/views/global/pdf/`**:
  * `ai-report-seelenfunke.blade.php` / `ai-report-generic.blade.php` (Standard-Systemberichte)
  * `health-treatment-plan.blade.php` (Behandlungspläne)
  * `health-protocol.blade.php` (Medizinische Gesprächsprotokolle)
  * `ceo-report.blade.php` (CEO-Analytics-Report)

### C. Automatisches Sicherheitsnetz bei Tool-Abstürzen
In **[AIFunctionsRegistry::execute()](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/AIFunctionsRegistry.php#L156-L186)** existiert eine globale Fehlerbehandlung:
* Stürzt ein von der KI aufgerufenes Werkzeug ab (Exception), generiert das System über die Klasse `SystemNeuralAnalysisIndex` eine Markdown-Zusammenfassung der fehlerhaften PHP-Datei.
* Diese Struktur wird automatisch als E-Mail-Anhang über `AiAgentMessageMail` an den Administrator (`kontakt@mein-seelenfunke.de`) versendet.

---

## 2. Spezifische KI-Funktionsmodule (Traits)

Die KI-Funktionen sind in Form von Traits unter `app/Services/AI/Functions/` organisiert. Folgende Traits enthalten E-Mail- und PDF-Fähigkeiten:

### 1. Allgemeiner E-Mail-Versand (`AiMailFuncs`)
* **Datei**: [AiMailFuncs.php](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMailFuncs.php)
* **Funktion**: `email_send_message`
  * **Beschreibung**: Ermöglicht der KI das freie Verfassen und Versenden von E-Mails an beliebige Kontakte oder System-E-Mail-Adressen.
  * **Logik**: Nutzt `AiAgentMessageMail` für den direkten Versand. Falls kein Empfänger definiert ist, wird die Firmen-E-Mail aus den Einstellungen geladen.

### 2. Finanzen & Steuern (`AiFinanceFuncs`)
* **Datei**: [AiFinanceFuncs.php](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiFinanceFuncs.php)
* **Funktion**: `finance_generate_and_send_report`
  * **Beschreibung**: Generiert einen monatlichen Finanzreport (PDF und CSV in einer ZIP-Datei) und sendet ihn an eine E-Mail-Adresse.
  * **Logik**: Ruft `FinancialService::generateTaxExport` auf und versendet die erstellte Datei als Anhang per Mail.

### 3. Gesundheit & Behandlungsdaten (`AiHealthFuncs`)
* **Datei**: [AiHealthFuncs.php](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiHealthFuncs.php)
* **Funktion**: `health_export_treatment_plan`
  * **Beschreibung**: Exportiert einen Behandlungsplan als PDF-Dokument in den Dateimanager (Ordner `Gesundheit`) und sendet ihn optional per E-Mail an den Patienten/Nutzer.
* **Funktion**: `health_export_protocol`
  * **Beschreibung**: Exportiert ein medizinisches Gesprächsprotokoll als PDF und versendet es optional per E-Mail.
* **Funktion**: `health_read_document` (PDF einlesen!)
  * **Beschreibung**: Die KI kann den Inhalt hochgeladener medizinischer PDFs einlesen.
  * **Logik**: Nutzt den PDF-Parser `Smalot\PdfParser\Parser`, um den reinen Text aus der Datei zu extrahieren.

### 4. Urlaubs- & Reiseplanung (`AiHolidayPlannerFuncs`)
* **Datei**: [AiHolidayPlannerFuncs.php](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiHolidayPlannerFuncs.php)
* **Funktion**: `holiday_generate_pdf_plan`
  * **Beschreibung**: Generiert eine strukturierte Reiseplanung (Logistik, Packliste, Sehenswürdigkeiten, Route) als PDF.
  * **Logik**: Ermöglicht dem Benutzer den direkten Browser-Download oder sendet die Datei per `AiHolidayPlanMail`.

### 5. Laserschutz & Schulung (`AiLaserFuncs`)
* **Datei**: [AiLaserFuncs.php](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiLaserFuncs.php)
* **Funktion**: `laser_generate_pdf_and_mail`
  * **Beschreibung**: Wandelt Laserschutz-Berichte oder Schulungsunterlagen von Markdown in PDF um und stellt sie zum Download oder per E-Mail bereit.
  * **Logik**: Nutzt `Str::markdown()` zur HTML-Generierung und `global.pdf.ai-report-[design]` als Template.

### 6. Geodaten- & Snapshot-Verarbeitung (`AiMapControlFuncs`)
* **Datei**: [AiMapControlFuncs.php](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiMapControlFuncs.php)
* **Funktion**: `map_generate_pdf_summary`
  * **Beschreibung**: Erstellt einen PDF-Bericht über gefundene Koordinaten, Orte und Wegbeschreibungen.
* **Funktion**: `map_generate_places_pdf_and_mail`
  * **Beschreibung**: Exportiert eine detaillierte Liste gesuchter Orte (z.B. Autohäuser, Arztpraxen inkl. Telefonnummern & Webseiten) als PDF und versendet sie optional.
* **Funktion**: `camera_process_snapshot`
  * **Beschreibung**: Verarbeitet ein Bild der Webcam (z.B. "Speichere das Bild als PDF" oder "Schick mir das Bild").
  * **Logik**: Konvertiert das Bild in ein base64-kodiertes HTML-Element, bettet es in `pdf.places_list` ein, speichert das PDF unter `agenten/workspace/Kamera-Snapshots/` und versendet es optional als Anhang.

### 7. Systemberichte & Administration (`AiSystemFuncs`)
* **Datei**: [AiSystemFuncs.php](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiSystemFuncs.php)
* **Funktion**: `system_email_neural_structure`
  * **Beschreibung**: Sendet die neuronale Strukturkarte einer PHP-Datei als PDF-Anhang oder reinen Text per E-Mail.
* **Funktion**: `system_send_neural_report_mail`
  * **Beschreibung**: Sendet einen zuvor generierten neuronalen Fehleranalysebericht per E-Mail.
* **Funktion**: `system_analyze_security_threats`
  * **Beschreibung**: Generiert eine Sicherheitsanalyse (gescheiterte Logins, kritische Systemlogs der letzten 48h) und sendet sie optional als Report an den Admin.
* **Funktion**: `system_generate_pdf_report`
  * **Beschreibung**: Universalfunktion für beliebige Markdown-Inhalte (Tabellen, Statistiken), um diese in ein PDF umzuwandeln und zu mailen/downloaden.
* **Funktion**: `system_export_system_report`
  * **Beschreibung**: Generiert native Berichte, darunter den **CEO-Strategie-Report** (PDF über Umsätze, Logins und Roadmap via `global.pdf.ceo-report`) oder den DATEV-Steuerexport (ZIP).

### 8. Laser-Produktionsdaten (`AiOrderFuncs`)
* **Datei**: [AiOrderFuncs.php](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiOrderFuncs.php)
* **Funktion**: `order_generate_xtool_svg` (Sonderfall: SVG statt PDF)
  * **Beschreibung**: Generiert eine XTool Laser-Produktionsdatei (SVG) für eine Bestellung und sendet sie per E-Mail an den Kunden oder die Produktion.
  * **Logik**: Erfasst das SVG über Output-Buffering und versendet es mittels `AiAgentMessageMail`.
