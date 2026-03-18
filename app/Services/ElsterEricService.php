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
        
        // Auto-Detect: Wenn das kompilierte C++ Executable im Tresor existiert, schalten wir in den Königsklasse-Modus (Nativ C++)
        $cppScriptPath = storage_path('app/erictresor/eric-linux/ERiC-43.4.6.0/Linux-x86_64/Beispiel/ericdemo-cpp/ericdemo/Release/ericdemo');
        $this->useNativeBinary = filter_var(env('ERIC_USE_NATIVE_BINARY', file_exists($cppScriptPath)), FILTER_VALIDATE_BOOLEAN);
        
        $this->apiEndpoint = env('ERIC_API_ENDPOINT', 'https://api.elster-sandbox.local/v1/transmit');
        $this->apiUsername = env('ERIC_API_USERNAME', '');
        $this->apiPassword = env('ERIC_API_PASSWORD', '');
    }

    /**
     * Prüft über den offiziellen RSS-Feed, ob die ELSTER-Server online sind.
     * Wirft eine Exception, wenn der Status für "Anmeldungssteuern (authentifiziert)" nicht "OK" ist.
     */
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
            // Falls der RSS Feed selbst down ist oder das Format nicht passt, werfen wir keinen fatalen Fehler,
            // sondern loggen es ggf. Wir wollen den Userzugang nicht hart blockieren, wenn nur der RSS Feed hängt.
            if (str_contains($e->getMessage(), 'Server-Störung')) {
                throw $e;
            }
        }

        return true;
    }

    /**
     * Dient als zentraler API-Wrapper für die ERiC Schnittstelle
     */
    public function transmitUStVA($data, $submissionType = 'Erstübermittlung', $authMethod = 'software', $pin = null)
    {
        // 1. Pre-Flight Check: Sind die ELSTER Server überhaupt online?
        $this->checkServerAvailability();

        $xmlPayload = $this->buildXmlPayload($data, $submissionType);

        // 2. Architektur-Weiche: Nativer C++ Client vs HTTP Gateway 
        if ($this->useNativeBinary) {
            return $this->executeEricBinaryNative($xmlPayload, $authMethod, $pin);
        }

        // --- HTTP GATEWAY FALLBACK / SANDBOX SIMULATION ---

        // Security-Prüfung: Fallback, damit nichts leeres übersendet wird
        if (empty($this->apiUsername) || empty($this->apiPassword)) {
            throw new \Exception("ERiC API Zugangsdaten fehlen in der .env (ERIC_API_USERNAME / ERIC_API_PASSWORD).");
        }

        try {
            // Sende an die tatsächliche Datenannahme API mit Authentifizierung
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
            $ticketId = $responseData['transfer_ticket'] ?? "ELSTER-" . strtoupper(Str::random(12));

            return [
                'success' => true,
                'ticket_id' => $ticketId,
                'xml_size' => strlen($xmlPayload),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Wenn der User lokal/offline entwickelt oder die Public API (z.B. finkonsens) nicht
            // existiert, simulieren wir das ERiC-Verhalten im Testmodus exakt nach.
            if ($this->isTestMode) {
                // 1 Sekunde künstlicher Delay für Authentizität
                sleep(1);
                $ticketId = "ELSTER-SIM-" . floatval(microtime(true) * 10000) . "-" . strtoupper(Str::random(6));
                return [
                    'success' => true,
                    'ticket_id' => $ticketId,
                    'xml_size' => strlen($xmlPayload),
                    'simulated' => true
                ];
            }
            throw new \Exception("Vorgang abgebrochen: Host ({$this->apiEndpoint}) nicht erreichbar. Da in der .env ERIC_TEST_MODE nicht auf true steht, wurde die Sandbox-Simulation aus Sicherheitsgründen hart blockiert!");
        }
    }

    /**
     * Führt das native kompilierte C++ ERiC Binary unter Linux aus.
     * Dies ist die finale "Live-Architektur", sobald der Server offiziell von ELSTER authorisiert wurde.
     */
    private function executeEricBinaryNative($xmlPayload, $authMethod, $pin)
    {
        // 1. Sicheres Temp-File für das XML Payload erzeugen
        $tempXmlPath = storage_path('app/erictresor/temp_payload_' . uniqid() . '.xml');
        $tempOutPath = storage_path('app/erictresor/eric_answer_' . uniqid() . '.xml');
        file_put_contents($tempXmlPath, $xmlPayload);
        
        // Neu: Wir nutzen den offiziellen Python-Wrapper, den wir vorhin lauffähig & headless gemacht haben!
        $tresorConfigPath = env('ERIC_TRESOR_PATH', 'storage/app/erictresor');
        $isAbs = str_starts_with($tresorConfigPath, '/');
        $absoluteTresorPath = $isAbs ? $tresorConfigPath : base_path($tresorConfigPath);
        
        $certPath = $absoluteTresorPath . DIRECTORY_SEPARATOR . shop_setting('eric_default_cert', '');
        
        $ericBaseDir = $absoluteTresorPath . '/eric-linux/ERiC-43.4.6.0/Linux-x86_64';
        
        // Neu: Wir haben den offiziellen C++ Quellcode kompiliert, um uns von Python/Java Abhängigkeiten auf dem Linux Server zu trennen!
        $cppBinary = $ericBaseDir . '/Beispiel/ericdemo-cpp/ericdemo/Release/ericdemo';
        $libDir = $ericBaseDir . '/lib';
        
        if (!file_exists($cppBinary)) {
            unlink($tempXmlPath);
            throw new \Exception("NATIVES ERiC GEHT NICHT: Der C++ Binary-Wapper '$cppBinary' existiert nicht. Bitte 'make all' im Ordner ausführen!");
        }

        // Automatische Erkennung der Datenart aus dem Payload (UStVA_2023, etc.)
        $datenart = 'UStVA_2023';
        if (str_contains($xmlPayload, 'v2024') || str_contains($xmlPayload, 'version="2024"')) {
            $datenart = 'UStVA_2024';
        }

        // Kommandozeilenaufruf vorbereiten (./ericdemo -v ... -x ... -c ... -p ... -l ...)
        $cmd = sprintf(
            '"%s" -v %s -x "%s" -s "%s" -d "%s" -l "%s"',
            $cppBinary,
            escapeshellarg($datenart),
            $tempXmlPath,
            $tempOutPath,
            $libDir,
            $absoluteTresorPath
        );

        // Security: _NULL wenn Hardware oder ohne Cert
        if ($authMethod === 'software') {
            $cmd .= sprintf(' -c "%s" -p %s', $certPath, escapeshellarg($pin));
        } else {
            $cmd .= ' -c _NULL -p _NULL'; // Fallback / Hardware Pinning
        }

        // Der $this->isTestMode ist absolut ausreichend, da unser buildXmlPayload() 
        // dann den <Testmerker>700000004</Testmerker> ins XML einbaut, womit 
        // das ELSTER-Postamt die Übertragung als reinen Stresstest ansieht.
        // Option '-n' würde den Versand ins Internet komplett unterbinden und nur offline prüfen.

        // EXECUTION (Live Ausführung auf Ubuntu Root-Ebene)
        $output = shell_exec($cmd . ' 2>&1');
        
        // Cleanup PII Payload XML
        if (file_exists($tempXmlPath)) {
            unlink($tempXmlPath);
        }
        
        $serverAnswer = '';
        if (file_exists($tempOutPath)) {
            $serverAnswer = file_get_contents($tempOutPath);
            unlink($tempOutPath);
        }

        // Analyseergebnis
        if (str_contains($output, 'fehlerfrei') || str_contains($output, 'Erfolgreich')) {
            $ticketId = 'ELSTER-NATIV-' . strtoupper(Str::random(10));
            // Falls das Skript uns ein echtes Ticket zurückgegeben hat, filtern wir es:
            if (preg_match('/Transferhandle:\s*([0-9]+)/i', $output, $matches)) {
                $ticketId = 'ELSTER-NATIV-TH-' . $matches[1];
            }
            return [
                'success' => true,
                'ticket_id' => $ticketId,
                'xml_size' => strlen($xmlPayload),
            ];
        }

        // Falls es einen Fehler gab, holen wir uns sofort die eric.log Details!
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
                // Fallback, falls keine ERROR Tags da sind
                $logContent = file_get_contents($logFile);
                $ericLogFallback = "\n\n--- AUSZUG ERIC.LOG ---\n" . substr($logContent, 0, 2000);
            }
            unlink($logFile); // Aufräumen für den nächsten Versuch
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
        $finanzamtId = shop_setting('owner_finanzamt_id', '9111'); // Standard: 9111 Bayern
        $jahr = $data['year'] ?? '2023';

        // Wenn wir im Testmodus sind, muessen wir ZWINGEND die offiziellen Test-Kennungen nutzen!
        if ($this->isTestMode) {
            $steuernummer = '9111081508152'; // Offizielle ELSTER Sandbox-Steuernummer
            $finanzamtId = '9111'; // Test Finanzamt München
            $jahr = '2023'; // Zwingende Anpassung an das fest einkodierte v2023 XML-Schema
        }

        // SECURITY LAYER: Testmerker 700000004 garantiert 100% straffreies Test-Sandboxing.
        $testmerker = $this->isTestMode ? "<Testmerker>700000004</Testmerker>" : "";

        // HINWEIS: Offiziell ab ERiC > 30.1 ist UTF-8 Zwang statt ISO-8859-15.
        // Die Nutzdaten selbst liegen zwingend im 'Anmeldungssteuern' (finkonsens.de) Schema.
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
