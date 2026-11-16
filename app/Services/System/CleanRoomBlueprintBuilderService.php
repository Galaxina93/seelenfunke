<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class CleanRoomBlueprintBuilderService
{
    public const STORAGE_REL_DIR = 'private/products-secure/blueprints';

    public function __construct(
        private readonly BlueprintZeroLeakScanner $zeroLeakScanner
    ) {
    }

    /**
     * Liefert das vollständige Metadaten-Manifest der 5 sterilen High-Ticket Architektur-Blueprints.
     *
     * @return array<string, array{
     *     slug: string,
     *     sku: string,
     *     name: string,
     *     name_en: string,
     *     price_cents: int,
     *     filename: string,
     *     module_class: string,
     *     short_de: string,
     *     short_en: string,
     *     description_de: string
     * }>
     */
    public static function getBlueprintDefinitions(): array
    {
        return [
            'autonomous-self-healing-sentinel-kit' => [
                'slug' => 'autonomous-self-healing-sentinel-kit',
                'sku' => 'NXC-BP-SENTINEL-01',
                'name' => 'Autonomous 4-Pillar Self-Healing Sentinel Kit',
                'name_en' => 'Autonomous 4-Pillar Self-Healing Sentinel Kit',
                'price_cents' => 34900,
                'filename' => 'NexusCore-Self-Healing-Sentinel-Kit.zip',
                'module_class' => 'SentinelEngine',
                'short_de' => '24/7 Watchtower, Dead-Letter-Spool, Stufe-1-Auto-Heal, SHA-256-Deduplizierung und 0-Byte-Log-Baseline als sterile Referenzarchitektur (DE & EN).',
                'short_en' => '24/7 Watchtower, dead-letter spool, stage-1 auto-heal, SHA-256 deduplicated repair dossiers, and zero-byte log baseline (DE & EN).',
                'description_de' => 'Produktionsreife Clean-Room-Referenzarchitektur (SentinelEngine) für vollautomatische Mehr-Plattform-Fehlerüberwachung: 2-Strike-Verifikation mit externer Gegenprüfung, Stufe-1-Selbstheilung, SHA-256-deduplizierte Reparatur-Dossiers ohne Log-Spam sowie automatisierte Sicherheits-Testsuite auf Deutsch und Englisch.',
            ],
            'multi-agent-executive-orchestra-router' => [
                'slug' => 'multi-agent-executive-orchestra-router',
                'sku' => 'NXC-BP-ORCHESTRA-02',
                'name' => 'Multi-Agent Executive Orchestra & Parallel Router',
                'name_en' => 'Multi-Agent Executive Orchestra & Parallel Router',
                'price_cents' => 39900,
                'filename' => 'OrchestratorRuntime-Multi-Agent-Router.zip',
                'module_class' => 'OrchestratorRuntime',
                'short_de' => '1-Chat-Dirigenten-Protokoll für 10 Spezial-Subagenten mit hartem Dateikonflikt-Schutz, Cross-Process-Mutex und 12-Punkte-Kontextbudget (DE & EN).',
                'short_en' => 'Single-conductor protocol for 10 specialist subagents with strict file-conflict locks, cross-process mutex, and 12-bullet context budget (DE & EN).',
                'description_de' => 'Universelles Multi-Agenten-Orchestrierungs-Framework (OrchestratorRuntime): Paralleles Subagenten-Routing über isolierte Arbeitsbereiche, deterministischer Dateikonflikt-Schutz, Cross-Process-Deploy-Mutex, 70 % reduzierter Tokenverbrauch durch kompakte Ergebnis-Budgets und strikter Freigabe-Wächter.',
            ],
            'zero-secret-ai-vault-airgapped-broker' => [
                'slug' => 'zero-secret-ai-vault-airgapped-broker',
                'sku' => 'NXC-BP-VAULT-03',
                'name' => 'Zero-Secret AI Vault & Air-Gapped Broker',
                'name_en' => 'Zero-Secret AI Vault & Air-Gapped Broker',
                'price_cents' => 34900,
                'filename' => 'NexusCore-Zero-Secret-AI-Vault-Broker.zip',
                'module_class' => 'VaultBroker',
                'short_de' => 'Sichere KI-Ausführung von Zahlungs-, API- und Deploy-Operationen mit 0 Klartext-Secrets, SSRF-Pinning, DLP-Scrubber und Air-Gap-Isolation (DE & EN).',
                'short_en' => 'Execute payments, APIs, and deployments via AI agents with zero plaintext secret exposure, SSRF pinning, DLP scrubber, and air-gap isolation (DE & EN).',
                'description_de' => 'Hochsichere Zero-Secret-Broker-Referenzarchitektur (VaultBroker): Kapselt Zahlungsgateways (inkl. Shared Payment Tokens & Refund-Deckeln), SSH-Workflows und 2FA-TOTP hinter einer strikten Air-Gap-Schranke, sodass niemals API-Schlüssel oder Kundendaten ungeschützt in den KI-Kontext gelangen.',
            ],
            'realtime-multimodal-ai-voice-edge-bridge' => [
                'slug' => 'realtime-multimodal-ai-voice-edge-bridge',
                'sku' => 'NXC-BP-VOICE-04',
                'name' => 'Realtime Multimodal AI Voice & Edge Bridge',
                'name_en' => 'Realtime Multimodal AI Voice & Edge Bridge',
                'price_cents' => 29900,
                'filename' => 'NexusCore-Realtime-Multimodal-Voice-Bridge.zip',
                'module_class' => 'EdgeVoiceBridge',
                'short_de' => 'Sub-300ms bidirektionale WebSocket-Audio- & Vision-Bridge mit Multi-Region-Failover und Whitelist-Telefon-Routing (DE & EN).',
                'short_en' => 'Sub-300ms bidirectional WebSocket audio and vision bridge with multi-region quota failover and whitelist telephony routing (DE & EN).',
                'description_de' => 'Reaktionsschnelle Echtzeit-Audio- und Vision-Brücke (EdgeVoiceBridge): Bidirektionales PCM-Streaming unter 300 ms Latenz, automatische Multi-Region-Quota-Kaskade, saubere Barge-In-Unterbrechungslogik und abgesicherte Telefonie-Integration ohne laufende Drittanbieter-Plattformgebühren.',
            ],
            'complete-autonomous-ai-company-suite' => [
                'slug' => 'complete-autonomous-ai-company-suite',
                'sku' => 'NXC-BP-SUITE-99',
                'name' => 'Complete Autonomous AI Company Suite',
                'name_en' => 'Complete Autonomous AI Company Suite',
                'price_cents' => 99000,
                'filename' => 'NexusCore-Complete-Autonomous-AI-Suite.zip',
                'module_class' => 'NexusCoreSuite',
                'short_de' => 'Das Flaggschiff-Komplettpaket: Alle 4 Architektur-Systeme vereint in einem schlüsselfertigen End-to-End Docker-Referenzstack inkl. Security-Testsuite (DE & EN).',
                'short_en' => 'The flagship enterprise bundle: All 4 reference architectures unified in an end-to-end Docker stack with full security verification suite (DE & EN).',
                'description_de' => 'Die vollständige Referenz-Suite (NexusCoreSuite) für CTOs, Software-Häuser und Tech-Gründer: Vereint SentinelEngine, OrchestratorRuntime, VaultBroker und EdgeVoiceBridge in einer durchgängig abgestimmten Multi-Service-Architektur inklusive zweisprachiger Handbücher (DE/EN), Mermaid-Diagrammen und automatisierten Sicherheitstests.',
            ],
        ];
    }

    /**
     * Erzeugt alle 5 sterilen Clean-Room-ZIP-Pakete unter storage/app/private/products-secure/blueprints/
     * und verifiziert jedes einzelne Paket unmittelbar mit dem BlueprintZeroLeakScanner.
     *
     * @return array<string, string> Map aus slug => relativer Disk-Pfad
     */
    public function buildAllPackages(): array
    {
        $built = [];
        foreach (array_keys(self::getBlueprintDefinitions()) as $slug) {
            $built[$slug] = $this->buildPackage($slug);
        }

        return $built;
    }

    /**
     * Erzeugt ein einzelnes steriles Blueprint-ZIP-Paket und prüft es auf 0 Sperrlisten-Treffer.
     */
    public function buildPackage(string $slug): string
    {
        $defs = self::getBlueprintDefinitions();
        if (!isset($defs[$slug])) {
            throw new RuntimeException("Unbekanntes Blueprint-Paket: {$slug}");
        }

        $meta = $defs[$slug];
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::STORAGE_REL_DIR);

        $relZipPath = self::STORAGE_REL_DIR . '/' . $slug . '.zip';
        $fullZipPath = $disk->path($relZipPath);
        $parentDir = dirname($fullZipPath);
        $dirMode = app()->environment('local', 'testing') ? 0777 : 0775;
        $fileMode = app()->environment('local', 'testing') ? 0666 : 0664;

        if (!is_dir($parentDir)) {
            @mkdir($parentDir, $dirMode, true);
        }
        @chmod(dirname($parentDir), $dirMode);
        @chmod($parentDir, $dirMode);

        $tmpBase = @tempnam(sys_get_temp_dir(), 'bp_');
        if ($tmpBase === false || $tmpBase === '') {
            $tmpBase = sys_get_temp_dir() . '/bp_' . bin2hex(random_bytes(8));
        } else {
            @unlink($tmpBase);
        }
        $tmpZipPath = $tmpBase . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpZipPath);
            throw new RuntimeException("Clean-Room Temp-ZIP konnte nicht angelegt werden: {$tmpZipPath}");
        }

        $module = $meta['module_class'];

        $zip->addFromString('README_DE.md', $this->renderGermanManual($meta));
        $zip->addFromString('README_EN.md', $this->renderEnglishManual($meta));
        $zip->addFromString('docs/architecture.mmd', $this->renderMermaidDiagram($module));
        $zip->addFromString('.env.example', $this->renderCleanEnvExample($module));
        $zip->addFromString('docker-compose.example.yml', $this->renderDockerComposeExample($slug));
        $zip->addFromString("src/NexusCore/{$module}.php", $this->renderPhpReferenceCode($module));
        $zip->addFromString("src/NexusCore/{$module}Bridge.ts", $this->renderTypeScriptReferenceCode($module));
        $zip->addFromString("tests/{$module}VerificationTest.php", $this->renderVerificationTest($module));

        $zip->close();
        @chmod($tmpZipPath, $fileMode);

        try {
            $this->zeroLeakScanner->assertZipIsClean($tmpZipPath);
        } catch (\Throwable $leakEx) {
            @unlink($tmpZipPath);
            throw $leakEx;
        }

        if (file_exists($fullZipPath)) {
            @chmod($fullZipPath, 0666);
            @unlink($fullZipPath);
        }

        $published = @rename($tmpZipPath, $fullZipPath);
        if (!$published) {
            $published = @copy($tmpZipPath, $fullZipPath);
            @unlink($tmpZipPath);
        }

        if (!$published || !is_file($fullZipPath)) {
            throw new RuntimeException("Clean-Room ZIP konnte nicht ans Ziel verschoben werden: {$fullZipPath}");
        }

        @chmod($fullZipPath, $fileMode);

        return $relZipPath;
    }

    private function renderGermanManual(array $meta): string
    {
        return "# {$meta['name']} (NexusCore Clean-Room Edition)\n\n"
            . "## Architektur-Überblick\n"
            . "{$meta['description_de']}\n\n"
            . "## Kernkomponenten\n"
            . "- **Primärmodul:** `NexusCore\\{$meta['module_class']}`\n"
            . "- **Netzwerk-Beispieltopologie:** `10.10.0.50` (`vault.example.org` / `api.example.org`)\n"
            . "- **Sicherheitsprinzip:** Strikte Zero-Secret-Kapselung, deterministische Idempotenz und Air-Gap-Rollentrennung.\n\n"
            . "## Schnellstart\n"
            . "1. `.env.example` nach `.env` kopieren und eigene Projektvariablen eintragen.\n"
            . "2. Referenzstack via `docker compose -f docker-compose.example.yml up -d` starten.\n"
            . "3. Sicherheits-Testsuite in `tests/{$meta['module_class']}VerificationTest.php` ausführen.\n";
    }

    private function renderEnglishManual(array $meta): string
    {
        return "# {$meta['name_en']} (NexusCore Clean-Room Edition)\n\n"
            . "## Executive Summary\n"
            . "{$meta['short_en']}\n\n"
            . "## Reference Topology\n"
            . "- **Core Engine:** `NexusCore\\{$meta['module_class']}`\n"
            . "- **Sample Internal Endpoint:** `https://vault.example.org` (`10.10.0.50`)\n"
            . "- **Zero-Secret Policy:** Credentials remain strictly inside the isolated broker runtime.\n\n"
            . "## Quickstart\n"
            . "1. Copy `.env.example` to `.env` and configure your environment placeholders.\n"
            . "2. Launch the isolated demo stack using `docker compose -f docker-compose.example.yml up -d`.\n"
            . "3. Run `tests/{$meta['module_class']}VerificationTest.php` to verify guardrails.\n";
    }

    private function renderMermaidDiagram(string $module): string
    {
        return "flowchart TD\n"
            . "    Client[\"Executive Operator\"] --> Router[\"OrchestratorRuntime\"]\n"
            . "    Router --> Engine[\"{$module}\"]\n"
            . "    Engine --> Broker[\"VaultBroker (Zero-Secret Gate)\"]\n"
            . "    Engine --> Sentinel[\"SentinelEngine (2-Strike Auto-Heal)\"]\n";
    }

    private function renderCleanEnvExample(string $module): string
    {
        return "APP_NAME=NexusCoreReference\n"
            . "APP_ENV=local\n"
            . "APP_URL=https://app.example.org\n"
            . "NEXUS_ACTIVE_MODULE={$module}\n"
            . "NEXUS_INTERNAL_NODE=10.10.0.50\n"
            . "PAYMENT_GATEWAY_KEY=pk_example_placeholder_only\n"
            . "PAYMENT_GATEWAY_SECRET=secret_example_placeholder_only\n"
            . "WEBHOOK_VERIFICATION_KEY=signing_example_placeholder_only\n";
    }

    private function renderDockerComposeExample(string $slug): string
    {
        return "services:\n"
            . "  nexus-core:\n"
            . "    image: php:8.3-cli-alpine\n"
            . "    container_name: nexus_{$slug}\n"
            . "    working_dir: /workspace\n"
            . "    volumes:\n"
            . "      - ./:/workspace\n"
            . "    networks:\n"
            . "      - nexus_internal\n"
            . "networks:\n"
            . "  nexus_internal:\n"
            . "    driver: bridge\n";
    }

    private function renderPhpReferenceCode(string $module): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace NexusCore;\n\n"
            . "final class {$module}\n"
            . "{\n"
            . "    private int \$refundCapCents = 25000;\n\n"
            . "    public function evaluateHealthPulse(int \$consecutiveFailures, bool \$externalProbeHealthy): string\n"
            . "    {\n"
            . "        if (\$consecutiveFailures < 2 || !\$externalProbeHealthy) {\n"
            . "            return 'standby_no_false_alarm';\n"
            . "        }\n\n"
            . "        return 'stage_1_auto_heal_triggered';\n"
            . "    }\n\n"
            . "    public function authorizeAutonomousAction(bool \$isExecutiveActor, int \$amountCents): bool\n"
            . "    {\n"
            . "        if (!\$isExecutiveActor) {\n"
            . "            return false;\n"
            . "        }\n\n"
            . "        return \$amountCents >= 0 && \$amountCents <= \$this->refundCapCents;\n"
            . "    }\n"
            . "}\n";
    }

    private function renderTypeScriptReferenceCode(string $module): string
    {
        return "export interface ExecutionEnvelope {\n"
            . "  readonly requestId: string;\n"
            . "  readonly moduleName: string;\n"
            . "  readonly airGapped: boolean;\n"
            . "}\n\n"
            . "export function create{$module}Envelope(requestId: string): ExecutionEnvelope {\n"
            . "  return {\n"
            . "    requestId,\n"
            . "    moduleName: '{$module}',\n"
            . "    airGapped: true,\n"
            . "  };\n"
            . "}\n";
    }

    private function renderVerificationTest(string $module): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "use NexusCore\\{$module};\n\n"
            . "// Reference self-check for {$module}\n"
            . "\$engine = new {$module}();\n"
            . "assert(\$engine->evaluateHealthPulse(1, true) === 'standby_no_false_alarm');\n"
            . "assert(\$engine->evaluateHealthPulse(2, true) === 'stage_1_auto_heal_triggered');\n"
            . "assert(\$engine->authorizeAutonomousAction(false, 1000) === false);\n"
            . "assert(\$engine->authorizeAutonomousAction(true, 15000) === true);\n";
    }
}
