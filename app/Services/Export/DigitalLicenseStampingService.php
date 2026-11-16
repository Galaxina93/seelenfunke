<?php

namespace App\Services\Export;

use App\Models\Order\OrderOrderItem;
use App\Services\System\BlueprintZeroLeakScanner;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class DigitalLicenseStampingService
{
    public function __construct(
        private readonly BlueprintZeroLeakScanner $zeroLeakScanner
    ) {
    }

    /**
     * Prüft das Master-ZIP per BlueprintZeroLeakScanner, erstellt eine temporäre Arbeitskopie
     * und stempelt käuferspezifisch LICENSE.txt sowie blueprint-license.json ein.
     * Das Master-Archiv bleibt dabei zu 100 % unverändert.
     */
    public function createStampedZipCopy(OrderOrderItem $item, string $masterAbsolutePath): string
    {
        $this->zeroLeakScanner->assertZipIsClean($masterAbsolutePath);

        $tempDir = storage_path('framework/cache/stamped-blueprints');
        if (!is_dir($tempDir) && !@mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            $tempDir = sys_get_temp_dir();
        }

        $tempZipPath = rtrim($tempDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'blueprint_' . Str::lower(Str::random(20)) . '.zip';

        if (!@copy($masterAbsolutePath, $tempZipPath)) {
            throw new RuntimeException('Temporäre Lizenz-Kopie des ZIP-Archivs konnte nicht erstellt werden.');
        }

        $order = $item->order;
        $orderNumber = (string) ($order?->order_number ?: 'ORD-PREVIEW');
        $buyerEmailNormalized = strtolower(trim((string) ($order?->email ?: 'preview@invalid.local')));
        $buyerHash = hash('sha256', $buyerEmailNormalized . '|' . (string) ($order?->id ?: $item->id));
        $issuedAt = now()->toIso8601String();
        $licenseId = 'lic_' . substr(hash('sha256', $orderNumber . '|' . $item->id . '|' . $buyerHash), 0, 24);
        $packageSlug = (string) ($item->product?->slug ?: 'reference-architecture-blueprint');

        $licenseJsonPayload = [
            'license_id' => $licenseId,
            'order_number' => $orderNumber,
            'issued_at' => $issuedAt,
            'buyer_hash' => $buyerHash,
            'package' => $packageSlug,
            'license_model' => 'Single-Entity Commercial Reference Architecture License',
            'delivery_basis' => 'Reference Architecture as-is',
            'resale_and_redistribution_prohibited' => true,
            'public_repository_upload_prohibited' => true,
        ];

        $licenseText = "========================================================================\n"
            . "COMMERCIAL SOFTWARE REFERENCE ARCHITECTURE LICENSE (DE / EN)\n"
            . "========================================================================\n"
            . "License ID   : {$licenseId}\n"
            . "Order Number : {$orderNumber}\n"
            . "Issued At    : {$issuedAt}\n"
            . "Buyer Hash   : {$buyerHash}\n"
            . "Package      : {$packageSlug}\n"
            . "------------------------------------------------------------------------\n\n"
            . "[EN] GRANT OF LICENSE & AS-IS DISCLAIMER\n"
            . "1. Scope: Non-exclusive license to use, adapt, and integrate this Reference\n"
            . "   Architecture within the buyer's own internal commercial projects.\n"
            . "2. Strict Prohibition of Resale & Public Sharing: Redistribution, resale,\n"
            . "   sublicensing, or uploading any part of this package to public repositories\n"
            . "   is strictly prohibited. Each archive carries a cryptographic buyer hash.\n"
            . "3. Reference Architecture \"AS-IS\": Provided strictly as an engineering\n"
            . "   template for self-implementation without operational, uptime, or fitness\n"
            . "   warranties for third-party production environments.\n\n"
            . "[DE] NUTZUNGSRECHT & HAFTUNGSAUSSCHLUSS (REFERENZARCHITEKTUR)\n"
            . "1. Umfang: Einfaches Nutzungsrecht zur Anpassung und Integration dieser\n"
            . "   Software-Referenzarchitektur in eigene Projekte des Erwerbers.\n"
            . "2. Weiterverkaufs- und Veröffentlichungsverbot: Die Weitergabe, der\n"
            . "   Weiterverkauf, die Unterlizenzierung sowie die Veröffentlichung in\n"
            . "   öffentlichen Repositories sind strikt untersagt.\n"
            . "3. Bereitstellung als Referenzarchitektur (\"as-is\"): Bereitstellung als\n"
            . "   Code-Vorlage zur Eigenimplementierung ohne Betriebs- oder Erfolgsgarantie\n"
            . "   für fremde Drittsysteme.\n";

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath) !== true) {
            @unlink($tempZipPath);
            throw new RuntimeException('Temporäres Lizenz-Archiv konnte nicht zum Stempeln geöffnet werden.');
        }

        $zip->addFromString('LICENSE.txt', $licenseText);
        $zip->addFromString(
            'blueprint-license.json',
            (string) json_encode($licenseJsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $zip->close();

        return $tempZipPath;
    }
}
