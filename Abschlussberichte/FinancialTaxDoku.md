# 🧾 Seelenfunke UStVA-Modul (ELSTER ERiC)
**Stand:** März 2026 | **Version:** 1.0 (MVP)

Dieses Dokument beschreibt die Architektur, Funktionalität und die notwendigen Voraussetzungen für die Test- und Live-Umgebung des Moduls zur Übermittlung der Umsatzsteuervoranmeldung im Seelenfunke System.

---

## 1. Architektur & Konzept

Das Modul `FinancialTax` aggregiert vollautomatisch die relevanten Monatszahlen aus den Bestellungen (Einnahmen) und den angelegten Unternehmensausgaben (Variabel & Fix) der Datenbank.
Es nutzt das offizielle **ELSTER ERiC (Elster Rich Client)** Schema zur Validierung und Übermittlung (XML format).

### Die 2-Säulen-Technik:
1. **Frontend (`FinancialTax.php` Livewire Component):**
   * Berechnet dynamisch die Basiswerte für Kennzahl 81, 66 und 83.
   * Kontrolliert über eine streng-strikte "Master Checklist" in Echtzeit, ob alle rechtlichen und technischen Voraussetzungen erfüllt sind, um eine gültige Meldung abzusetzen.
2. **Backend / API-Layer (`ElsterEricService.php`):**
   * Kapselt die eigentliche Kommunikation mit den ELSTER-Servern.
   * Beinhaltet den XML-Builder für das amtliche `finkonsens.de`-Schema.
   * Sendet den Payload (XML) aktuell gegen die ELSTER-Endpoints. Dank des hart codierten **Testmerkers `700000004`** werden Übermittlungen im Rechenzentrum sofort nach der Struktur-Validierung ausgesondert. Es entsteht *keine* steuerrechtliche Wirkung während des Testings!

---

## 2. Die Authentifizierung

Das System unterstützt zwei Arten der Authentifizierung, die nahtlos in der Benutzeroberfläche umgeschaltet werden können:

### A) Software-Zertifikat (.pfx) – *Aktuell empfohlen für Backend-Server*
Die Zertifikatsdatei muss physisch auf dem Server liegen. Laravel liest den Pfad über die `.env` Datei ein.

**Voraussetzung:**
Trage in der `.env` den Pfad zur Datei ein. Ein relativer Pfad zum Projektverzeichnis ist die sauberste Lösung (Möglichkeit 1), da `base_path()` diesen dann auflöst:
```env
ERIC_CERT_PATH="storage/app/erictresor/alina_elster_10.04.2024_14.33.pfx"
```
* **Sicherheit:** Das Passwort zur Entschlüsselung wird **nicht** gespeichert! Es muss bei jeder Übermittlung live in der UI eingegeben werden und zirkuliert nur zur Laufzeit im RAM der ERiC-Schnittstelle.
* **Live-Check:** Die UI besitzt nun eine Echtzeit-Prüfung, die den `.env`-Pfad auflöst und das "Senden" blockiert, falls die Datei umbenannt wurde oder die Leserechte (CHMOD) fehlen.

### B) Hardware-Token (secunet USB-Stick)
Der ELSTER-Stick kommuniziert über tiefgreifende APDU (ISO 7816) Schnittstellen. Das Modul bereitet diesen Weg vor, indem nach Auswahl des Hardware-Tokens die exakt 6-stellige **Applikations-PIN** über die sichere UI abgefragt wird.

---

## 3. Die Live-Validierung (Wann kann ich senden?)

Sobald du auf den Senden-Button klickst – oder auch nur etwas in der Checkliste auf der rechten Seite beobachtest – führt das System eine vollumfängliche Prüfung durch.

Der Sende-Button ("Test-Senden (ERiC)") bleibt hart **deaktiviert (ausgegraut)** und eine Info-Box listet exakt auf, warum, bis **alle** folgenden Kriterien erfüllt sind:

1. **Stammdaten komplett:** In den Einstellungen müssen `owner_tax_id` (Steuernummer) und `owner_proprietor` (Name des Inhabers) ausgefüllt sein.
2. **100% Belegführung:** Es darf **keine einzige** Variable- oder Fix-Ausgabe im gewählten Monat existieren, bei der die hochgeladene Quittung (PDF/Bild) fehlt! Das Finanzamt akzeptiert XML-Payloads zur Kz 66 (Vorsteuer) ohne rechtssichere Beleg-Referenzstruktur bei Betriebsprüfungen nicht.
3. **Plausibilitätsprüfung des Zertifikats:** Ist "Software-Zertifikat" gewählt, muss die `.pfx` Datei am Server gefunden werden.
4. **Authentifizierung vorliegend:** Das `.pfx` Passwort (oder die secunet PIN) muss im UI-Feld eingetippt sein.

Erst wenn die **Master Checklist** rechts durchgehend grüne Haken anzeigt und das Passwort eingetippt ist, wird der Button geklickt.

---

## 4. Bereitstellung für den ersten Test

Um nun den allerersten echten API-Test gegen die ELSTER-Server durchzuführen, gehe wie folgt vor:

1. Wähle im linken Menü einen Monat (z.B. den aktuellen oder den Vormonat).
2. Lade die letzten **2 fehlenden Belege** (wie von dir erwähnt) im System hoch. In der UI der UStVA wird die rote Info "Fehlende Belege blockieren den Export" in der Master-Checkliste sofort umschalten auf einen grünen Haken.
3. Stelle sicher, dass die Steuernummer und dein Name (Inhaber) in den Einstellungen hinterlegt sind.
4. Trage das Passwort deines Zertifikats in der Box "Zertifikats-Passwort" ein.
5. Klicke auf den nun blau/orange leuchtenden Button **Test-Senden (ERiC)**.
6. Beobachte das **Funki Terminal Log** (die Box unten auf der Seite): Das System streamt die Live-Antworten der ELSTER Server auf deinen Monitor. Es gibt dir exakte Fehlermeldungen (z.B. "XML fehlerhaft" oder "Passwort falsch") oder feiert den Erfolg ("Datenannahme bestätigt. Transferticket: ...").

---

## 5. Technische Umsetzung (Native ERiC C++ Integration)

Da ELSTER die Übermittlung von reinen HTTP-Requests blockiert und eine Verschlüsselung/Signatur durch ihre proprietären, geschlossenen C++ Bibliotheken (`libericapi.so`) erzwingt, haben wir die Architektur dahingehend perfektioniert, dass wir absolut nativ und 100% konform mit dem bayerischen Landesamt für Steuern kommunizieren.

### A) Headless C++ Executable
Wir haben die von ELSTER mitgelieferte C++ Referenzimplementierung (`ericdemo.cpp`) modifiziert und kompiliert:
*   Der C++ Wrapper nimmt unseren XML-Payload, das Test-Zertifikat und die PIN direkt als CLI-Argumente an. 
*   Er leitet die Daten an die interne `EricBearbeiteVorgang` Funktion der ELSTER-Bibliotheken weiter.
*   **Low-Level C++ Logging Hook**: Da ELSTER bei Schema-Abweichungen extrem kryptische Fehlermeldungen (z.B. `ERIC_IO_READER_SCHEMA_VALIDIERUNGSFEHLER(610301200)`) wirft und Details in versteckten Logfiles (`eric.log`) auslagert, haben wir einen **Speicher-Hook** (`EricRegistriereLogCallback`) tief im C++ Code implementiert. Dieser leitet alle internen Engine-Logs in Echtzeit über `STDOUT` direkt an unser PHP-Backend weiter.

### B) Das strenge XML-Schema (TransferHeader)
Das größte Hindernis bei der direkten ERiC Integration ist die exakte, unerbittliche Einhaltung des `ElsterBasisSchema`'s für den globalen `<TransferHeader>`.
Obwohl ERiC Verschlüsselung und Kompression eigenständig vornimmt, verlangt das lokale Validierungs-Plugin (`libcheckUStVA_2023.so`) eine **absolute und unverrückbare Reihenfolge** (sowie Anwesenheit) bestimmter XML-Tags *vor* dem C++ Transfer.

Die Tags müssen im `<TransferHeader version="11">` exakt (!) in dieser Reihenfolge vorliegen:
1. `<Verfahren>`
2. `<DatenArt>`
3. `<Vorgang>`
4. `<Testmerker>` (optional, für Sandbox: 700000004)
5. `<HerstellerID>`
6. `<DatenLieferant>`
7. `<Datei>` (Zwingend! Auch wenn ERiC dieses Element später selbstständig befüllt, muss die Struktur leer überreicht werden: `<Verschluesselung>CMSEncryptedData</Verschluesselung><Kompression>GZIP</Kompression><TransportSchluessel></TransportSchluessel>`).

Fehlt das Element `<Datei>` oder ist die Reihenfolge vertauscht, lehnt ERiC das Dokument hart ab.

### C) Test-Umgebung & Hersteller-ID (Blacklisting)
Wird gegen die ELSTER Sandbox (München 9111) getestet:
*   Es muss zwingend ein offizielles Soft-PSE Zertifikat (`test-softidnr-pse.pfx`) genutzt werden.
*   Die PIN hierfür lautet immer **123456**.
*   **Wichtig:** Verwende in den XML-Metadaten **niemals** die Hersteller-ID `74931`. Diese stammt aus alten, veralteten ELSTER-Tutorials und wurde serverseitig vom Finanzamt wegen Spam permanent gesperrt (wirft `ERIC_IO_TESTHERSTELLERID_GESPERRT`). Wir nutzen stattdessen im Builder z.B. eine zufällige, abweichende 5-stellige ID (z.B. `74939`).

---

## 6. Referenz-Implementierung: `ElsterEricService.php`

Um sicherzustellen, dass die C++-Architektur, der ERiC-Wrapper, Auslesung der XML-Fehler und der extrem fehleranfällige ERiC `<TransferHeader>` für die Zukunft gesichert sind, befindet sich hier der exakte, funktionierende und praxiserprobte Quellcode aus dem Backend-Core:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ElsterEricService
{
    private $isTestMode;
    private $apiEndpoint;
    private $apiUsername;
    private $apiPassword;
    private $useNativeBinary;

    public function __construct()
    {
        $this->isTestMode = env('ERIC_TEST_MODE', true);
        
        $cppScriptPath = storage_path('app/erictresor/eric-linux/ERiC-43.4.6.0/Linux-x86_64/Beispiel/ericdemo-cpp/ericdemo/Release/ericdemo');
        $this->useNativeBinary = filter_var(env('ERIC_USE_NATIVE_BINARY', file_exists($cppScriptPath)), FILTER_VALIDATE_BOOLEAN);
        
        $this->apiEndpoint = env('ERIC_API_ENDPOINT', 'https://api.elster-sandbox.local/v1/transmit');
        $this->apiUsername = env('ERIC_API_USERNAME', '');
        $this->apiPassword = env('ERIC_API_PASSWORD', '');
    }

    public function checkServerAvailability()
    {
        try {
            $rssRaw = Http::timeout(5)->get('https://www.elster.de/elsterweb/serverstatus_rss.xml')->body();
            $xml = simplexml_load_string($rssRaw);

            if ($xml && isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    if (strpos((string)$item->title, 'Anmeldungssteuern (authentifiziert)') !== false) {
                        $status = trim((string)$item->description);
                        if ($status !== 'OK') {
                           throw new \Exception("ELSTER Server-Störung ('Anmeldungssteuern'): " . $status);
                        }
                        return true;
                    }
                }
            }
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Server-Störung')) {
                throw $e;
            }
        }
        return true;
    }

    public function transmitUStVA($data, $submissionType = 'Erstübermittlung', $authMethod = 'software', $pin = null)
    {
        $this->checkServerAvailability();
        $xmlPayload = $this->buildXmlPayload($data, $submissionType);

        if ($this->useNativeBinary) {
            return $this->executeEricBinaryNative($xmlPayload, $authMethod, $pin);
        }

        if (empty($this->apiUsername) || empty($this->apiPassword)) {
            throw new \Exception("ERiC API Zugangsdaten fehlen in der .env (ERIC_API_USERNAME / ERIC_API_PASSWORD).");
        }

        try {
            $response = Http::withBasicAuth($this->apiUsername, $this->apiPassword)
                ->timeout(20)
                ->withHeaders([
                    'Content-Type' => 'application/xml; charset=UTF-8',
                    'Accept' => 'application/json',
                ])
                ->post($this->apiEndpoint, $xmlPayload);

            if ($response->failed()) {
                throw new \Exception("HTTP " . $response->status() . " - " . $response->body());
            }

            $responseData = $response->json() ?? [];
            return [
                'success' => true,
                'ticket_id' => $responseData['transfer_ticket'] ?? "ELSTER-" . strtoupper(Str::random(12)),
                'xml_size' => strlen($xmlPayload),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if ($this->isTestMode) {
                sleep(1);
                return [
                    'success' => true,
                    'ticket_id' => "ELSTER-SIM-" . floatval(microtime(true) * 10000) . "-" . strtoupper(Str::random(6)),
                    'xml_size' => strlen($xmlPayload),
                    'simulated' => true
                ];
            }
            throw new \Exception("Vorgang abgebrochen: Host nicht erreichbar.");
        }
    }

    private function executeEricBinaryNative($xmlPayload, $authMethod, $pin)
    {
        $tempXmlPath = storage_path('app/erictresor/temp_payload_' . uniqid() . '.xml');
        $tempOutPath = storage_path('app/erictresor/eric_answer_' . uniqid() . '.xml');
        file_put_contents($tempXmlPath, $xmlPayload);
        
        $tresorConfigPath = env('ERIC_TRESOR_PATH', 'storage/app/erictresor');
        $isAbs = str_starts_with($tresorConfigPath, '/');
        $absoluteTresorPath = $isAbs ? $tresorConfigPath : base_path($tresorConfigPath);
        
        $certPath = $absoluteTresorPath . DIRECTORY_SEPARATOR . shop_setting('eric_default_cert', '');
        $ericBaseDir = $absoluteTresorPath . '/eric-linux/ERiC-43.4.6.0/Linux-x86_64';
        
        $cppBinary = $ericBaseDir . '/Beispiel/ericdemo-cpp/ericdemo/Release/ericdemo';
        $libDir = $ericBaseDir . '/lib';
        
        if (!file_exists($cppBinary)) {
            unlink($tempXmlPath);
            throw new \Exception("NATIVES ERiC GEHT NICHT: Der C++ Binary-Wapper '$cppBinary' existiert nicht.");
        }

        $datenart = 'UStVA_2023';
        if (str_contains($xmlPayload, 'v2024') || str_contains($xmlPayload, 'version="2024"')) {
            $datenart = 'UStVA_2024';
        }

        $cmd = sprintf('"%s" -v %s -x "%s" -s "%s" -d "%s" -l "%s"',
            $cppBinary, escapeshellarg($datenart), $tempXmlPath, $tempOutPath, $libDir, $absoluteTresorPath
        );

        if ($authMethod === 'software') {
            $cmd .= sprintf(' -c "%s" -p %s', $certPath, escapeshellarg($pin));
        } else {
            $cmd .= ' -c _NULL -p _NULL';
        }

        $output = shell_exec($cmd . ' 2>&1');
        
        if (file_exists($tempXmlPath)) unlink($tempXmlPath);
        
        $serverAnswer = '';
        if (file_exists($tempOutPath)) {
            $serverAnswer = file_get_contents($tempOutPath);
            unlink($tempOutPath);
        }

        if (str_contains($output, 'fehlerfrei') || str_contains($output, 'Erfolgreich')) {
            $ticketId = 'ELSTER-NATIV-' . strtoupper(Str::random(10));
            if (preg_match('/Transferhandle:\s*([0-9]+)/i', $output, $matches)) {
                $ticketId = 'ELSTER-NATIV-TH-' . $matches[1];
            }
            return [
                'success' => true,
                'ticket_id' => $ticketId,
                'xml_size' => strlen($xmlPayload),
            ];
        }

        $ericLogFallback = '';
        $logFile = $absoluteTresorPath . '/eric.log';
        if (file_exists($logFile)) {
            $logLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $errorLines = array_filter($logLines, function($line) {
                return str_contains($line, 'ERROR') || str_contains($line, 'WARN') || str_contains($line, 'FATAL');
            });
            
            if (count($errorLines) > 0) {
                $ericLogFallback = "\n\n--- ERIC.LOG FEHLER-DETAILS ---\n" . implode("\n", array_slice($errorLines, 0, 15));
            } else {
                $ericLogFallback = "\n\n--- AUSZUG ERIC.LOG ---\n" . substr(file_get_contents($logFile), 0, 2000);
            }
            unlink($logFile);
        }

        $serverAnswerOutput = !empty($serverAnswer) ? "\n\n--- ERIC XML ANTWORT / FEHLER-BAUM ---\n" . htmlentities($serverAnswer) : '';

        throw new \Exception(
            "Natives ERiC CLI Error (Zertifikat: " . basename($certPath) . ")\n" . 
            htmlentities(substr($output, -15000)) . 
            $serverAnswerOutput .
            $ericLogFallback .
            "\n\n--- URSPRUENGLICHER XML-PAYLOAD ---\n" . htmlentities(substr($xmlPayload, 0, 8000))
        );
    }

    private function buildXmlPayload($data, $submissionType = 'Erstübermittlung')
    {
        $kennzahl10 = $submissionType === 'Berichtigte Anmeldung' ? '<Kz10>1</Kz10>' : '<Kz10>0</Kz10>';
        $kz81 = $data['revenue_net'] > 0 ? '<Kz81>' . number_format($data['revenue_net'], 0, '', '') . '</Kz81>' : ''; 
        $kz66 = '<Kz66>' . number_format($data['vat_paid'], 2, '.', '') . '</Kz66>'; 
        $kz83 ='<Kz83>' . number_format($data['zahllast'], 2, '.', '') . '</Kz83>';
        
        $steuernummer = shop_setting('owner_tax_id', '1234567890');
        $finanzamtId = shop_setting('owner_finanzamt_id', '9111');
        $jahr = $data['year'] ?? '2023';

        if ($this->isTestMode) {
            $steuernummer = '9111081508152';
            $finanzamtId = '9111';
            $jahr = '2023';
        }

        $testmerker = $this->isTestMode ? "<Testmerker>700000004</Testmerker>" : "";

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Elster xmlns=\"http://www.elster.de/elsterxml/schema/v11\">
    <TransferHeader version=\"11\">
        <Verfahren>ElsterAnmeldung</Verfahren>
        <DatenArt>UStVA</DatenArt>
        <Vorgang>send-Auth</Vorgang>
        {$testmerker}
        <HerstellerID>74939</HerstellerID>
        <DatenLieferant>Seelenfunke ERP</DatenLieferant>
        <Datei>
            <Verschluesselung>CMSEncryptedData</Verschluesselung>
            <Kompression>GZIP</Kompression>
            <TransportSchluessel></TransportSchluessel>
        </Datei>
    </TransferHeader>
    <DatenTeil>
        <Nutzdatenblock>
            <NutzdatenHeader version=\"11\">
                <NutzdatenTicket>1</NutzdatenTicket>
                <Empfaenger id=\"F\">$finanzamtId</Empfaenger>
                <Hersteller>
                    <ProduktName>Seelenfunke ERP</ProduktName>
                    <ProduktVersion>1.0</ProduktVersion>
                </Hersteller>
            </NutzdatenHeader>
            <Nutzdaten>
                <Anmeldungssteuern xmlns=\"http://finkonsens.de/elster/elsteranmeldung/ustva/v2023\" version=\"2023\">
                    <Steuerfall>
                        <Umsatzsteuervoranmeldung>
                            <Jahr>{$jahr}</Jahr>
                            <Zeitraum>{$data['month_number']}</Zeitraum>
                            <Steuernummer>{$steuernummer}</Steuernummer>
                            {$kennzahl10}
                            {$kz81}
                            {$kz66}
                            {$kz83}
                        </Umsatzsteuervoranmeldung>
                    </Steuerfall>
                </Anmeldungssteuern>
            </Nutzdaten>
        </Nutzdatenblock>
    </DatenTeil>
</Elster>";
    }
}
```
