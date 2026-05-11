# Abschlussbericht: Restrukturierung und Automatisierung der Finanzverwaltung (Sonderausgaben & Fixkosten)
**Datum:** 04. Mai 2026
**Projekt:** Seelenfunke

---

## 1. Ausgangssituation
In der Vergangenheit wurden Rechnungen und Verträge zu den Sonderausgaben (Variable Kosten) sowie den Fixkosten lose im Hauptverzeichnis der Anwendung (in Ordnern wie `Ausgabe` und `contracts`) gespeichert. Dies führte zu einer unübersichtlichen Struktur, entsprach nicht den gängigen Produktionsstandards (da Daten außerhalb von `storage` lagen) und barg Risiken bei Backups oder Live-Deployments. Die Zuordnung in der Datenbank (Seeder) geschah rein auf Basis von Namensabgleichen ohne echte Datei-Pfad-Bindung.

## 2. Zielsetzung
Das primäre Ziel war die Etablierung eines professionellen, systemnahen "Self-Contained" E-Commerce-Standards für die gesamte Buchhaltungs-Ablage.
- **Konsistenz:** Einheitliche Dateibenennung nach dem Muster `YYYY-MM-DD_Anbieter_Titel.pdf`.
- **Zentralisierung:** Alle Dokumente müssen in das gesicherte Laravel `storage/app/buchhaltung/` Verzeichnis integriert werden, chronologisch geordnet (nach `/YYYY/MM/`).
- **Seeder-Integration:** Der `FinancialSeeder` soll als "Single Source of Truth" fungieren und die Dateien direkt bei der Erstellung der Datensätze korrekt verknüpfen (als wären sie über das UI hochgeladen worden).
- **Cleanup:** Entfernung jeglicher temporärer Skripte und loser Ordner im Hauptverzeichnis.

## 3. Durchgeführte Maßnahmen

### 3.1. Umstrukturierung Sonderausgaben (Variable Costs)
- Sämtliche PDF-Rechnungen aus dem Ordner `Ausgabe` wurden analysiert.
- Dateien wurden in das Zielverzeichnis `storage/app/buchhaltung/receipts/YYYY/MM/` verschoben und nach dem strikten Schema umbenannt.
- Der `FinancialSeeder.php` wurde für das Array `specialIssues` um die Eigenschaft `file_paths` (JSON-Array) erweitert. 
- Das Modell `AccountingSpecialIssue` und die UI-Komponente verarbeiten nun dynamisch und nahtlos die hinterlegten Dateien.
- Die Mehrwertsteuer-Logik wurde an das `SystemSetting` angebunden, sodass Steuersätze nun dynamisch gezogen werden, jedoch veraltete Steuersätze aus historischen Einträgen für die UI erhalten bleiben.

### 3.2. Umstrukturierung Fixkosten (Fix Costs)
- Insgesamt 24 PDF-Verträge aus dem `contracts` Ordner wurden gescannt.
- Diese Dateien wurden analog nach `storage/app/buchhaltung/contracts/YYYY/MM/` migriert und umbenannt.
- Der `FinancialSeeder.php` wurde im `$items` Array um das Feld `contract_file_path` ergänzt.
- Nach Validierung der Funktionalität wurde der veraltete `contracts` Ordner im Root-Verzeichnis restlos gelöscht.

### 3.3. UI/UX Optimierungen
- Im Fixkosten-Dashboard (`accounting-fix-costs.blade.php`) wurde die globale Tag-Verwaltung standardmäßig eingeklappt (`x-collapse`), um den Fokus auf die wichtigen Daten zu lenken.
- An allen Datei-Upload-Feldern ("Vertrag / Datei") wurde ein Hilfe-Icon mit Tooltip hinzugefügt, das die Administratoren an das korrekte E-Commerce Upload-Schema (`YYYY-MM-DD_Anbieter_Titel.pdf`) erinnert.

## 4. Live-Deployment: Vorgehensweise & Best Practices

Damit diese Änderungen auf dem Produktionsserver (Stage/Live) fehlerfrei übernommen werden, ist folgendes Vorgehen zwingend erforderlich:

1. **Code & Datenbank synchronisieren:**
   Zunächst die regulären Deployment-Schritte durchführen (`git pull`, `composer install` falls nötig).
   
2. **Datenbank aktualisieren:**
   Um die Verknüpfungen herzustellen, muss der Seeder auf dem Live-Server laufen:
   ```bash
   php artisan db:seed --class=AccountingSeeder
   ```
   *(Achtung: Prüfe vorher, ob deine Live-Datenbank im Bereich der Finanzen bereits händisch modifiziert wurde, andernfalls werden die Seeder-Stände angewandt).*

3. **Dokumenten-Migration (WICHTIG):**
   Da Git den `storage/app`-Ordner standardmäßig ignoriert (ausgenommen `.gitignore` Dummys), müssen die lokal umstrukturierten PDFs manuell auf den Server geladen werden.
   - **Kopiere:** Den lokalen Ordner `storage/app/buchhaltung` 
   - **Wohin:** In das exakt gleiche Verzeichnis auf dem Live-Server (`/pfad/zum/projekt/storage/app/buchhaltung`).
   
4. **Altlosen entfernen:**
   Auf dem Live-Server müssen die Ordner `Ausgabe` und `contracts` im Hauptverzeichnis gelöscht werden (falls sie dort jemals manuell abgelegt wurden), da sie obsolet sind.

---
**Status:** Erfolgreich abgeschlossen. Keine kritischen Blocker vorhanden.
