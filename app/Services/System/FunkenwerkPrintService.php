<?php

namespace App\Services\System;

use App\Models\Order\OrderOrder;
use App\Models\Order\OrderShipment;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FunkenwerkPrintService
{
    private const QUEUE_FILE = 'system/print_queue/jobs.json';
    private const LOCK_FILE = 'system/print_queue/jobs.lock';
    private const MAX_HISTORY = 30;
    private const MAX_PDF_BYTES = 10 * 1024 * 1024; // 10 MB Obergrenze für Versandlabel-PDFs
    private const HISTORY_TTL_DAYS = 7;

    /**
     * Prüft strikt, ob eine IP-Adresse eine zulässige private RFC1918-Heimnetz-IPv4 ist.
     */
    public static function sanitizePrivateLanIp(?string $ip, string $fallback = '192.168.188.149'): string
    {
        $candidate = trim((string) $ip);
        if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $fallback;
        }

        // Muss im privaten RFC1918-Bereich liegen (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
        $isPublicRange = filter_var(
            $candidate,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($isPublicRange !== false) {
            return $fallback;
        }

        if (
            str_starts_with($candidate, '192.168.') ||
            str_starts_with($candidate, '10.') ||
            preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $candidate)
        ) {
            return $candidate;
        }

        return $fallback;
    }

    /**
     * Legt einen Druckauftrag für ein einzelnes DHL-Versandlabel (OrderShipment) in die Funkenwerk-Queue.
     */
    public function enqueueShipmentLabel(OrderShipment $shipment): array
    {
        $order = $shipment->order()->with('items.product')->first();
        if (!$order) {
            throw new \RuntimeException('Zugehörige Bestellung für das Versandlabel nicht gefunden.');
        }

        if (empty($shipment->shipping_label_path) || $this->readLabelPdfBytes($shipment->shipping_label_path) === null) {
            throw new \RuntimeException("Für Paket {$shipment->tracking_number} ist keine gültige DHL-Label-PDF-Datei hinterlegt.");
        }

        return $this->createJobFromOrderAndShipment($order, $shipment, 'dhl_label');
    }

    /**
     * Legt alle vorhandenen DHL-Labels einer Bestellung in die Funkenwerk-Queue.
     * Wirft eine klare Exception, falls noch kein DHL-Label existiert.
     */
    public function enqueueOrderLabel(OrderOrder $order): array
    {
        $order->loadMissing(['items.product', 'shipments']);

        if ($order->isOnlyDigital()) {
            throw new \RuntimeException("Bestellung #{$order->order_number} enthält nur digitale Produkte (kein physischer Versand).");
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            throw new \RuntimeException("Bestellung #{$order->order_number} ist storniert bzw. erstattet.");
        }

        $validShipments = ($order->shipments ?? collect())->filter(function (OrderShipment $s) {
            return !empty($s->shipping_label_path) && $this->readLabelPdfBytes($s->shipping_label_path) !== null;
        })->values();

        if ($validShipments->isEmpty()) {
            if ($order->shipments && $order->shipments->isNotEmpty()) {
                throw new \RuntimeException("Für Bestellung #{$order->order_number} existiert zwar eine Sendungsnummer, aber die zugehörige DHL-PDF-Datei wurde auf dem Server nicht gefunden.");
            }
            throw new \RuntimeException("Für Bestellung #{$order->order_number} wurde noch kein DHL-Versandlabel generiert. Bitte erstelle zuerst das DHL-Label.");
        }

        $createdJobs = [];
        foreach ($validShipments as $index => $shipment) {
            $createdJobs[] = $this->createJobFromOrderAndShipment($order, $shipment, 'dhl_label', $index + 1, $validShipments->count());
        }

        return $createdJobs;
    }

    /**
     * Legt ein 4x6-Zoll Seelenfunke Test-Etikett für den MUNBYN ITPP941AP in die Queue.
     */
    public function enqueueTestLabel(string $printerIp = '192.168.188.149'): array
    {
        $safeIp = self::sanitizePrivateLanIp($printerIp);
        $job = [
            'id' => (string) Str::uuid(),
            'type' => 'test_label',
            'status' => 'pending',
            'printer_ip' => $safeIp,
            'order_id' => null,
            'order_number' => 'SF-TEST-' . now()->format('Hi'),
            'shipment_id' => null,
            'tracking_number' => '00340434161094015900',
            'package_index' => 1,
            'package_total' => 1,
            'weight_kg' => 0.45,
            'is_express' => true,
            'sender' => $this->resolveSenderAddress(),
            'recipient' => [
                'name' => 'Alina Steinhauer',
                'company' => 'Mein-Seelenfunke Manufaktur',
                'street' => 'Carl-Goerdeler-Ring 26',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'items' => [
                ['qty' => 1, 'name' => 'MUNBYN ITPP941AP WLAN-Testdruck (203 DPI)', 'sku' => 'SF-TEST-941AP'],
                ['qty' => 1, 'name' => 'xTool F2 Ultra UV Manufaktur-Kalibrierung', 'sku' => 'SF-LASER-UV'],
            ],
            'shipping_label_path' => null,
            'has_pdf' => false,
            'created_at' => now()->toIso8601String(),
            'printed_at' => null,
            'error_message' => null,
        ];

        $this->appendJob($job);
        return $this->sanitizeJobForPublicOutput($job, false);
    }

    /**
     * Liefert alle offenen (pending) sowie die letzten abgeschlossenen Druckaufträge.
     */
    public function getQueueState(bool $includePdfBase64 = true): array
    {
        return $this->withExclusiveLock(function () use ($includePdfBase64) {
            $jobs = $this->readJobsUnlocked();
            $pending = [];
            $history = [];

            foreach ($jobs as $job) {
                $sanitized = $this->sanitizeJobForPublicOutput($job, $includePdfBase64);
                if (($sanitized['status'] ?? '') === 'pending') {
                    $pending[] = $sanitized;
                } else {
                    $history[] = $sanitized;
                }
            }

            return [
                'printer_default_ip' => '192.168.188.149',
                'printer_hostname' => 'ITPP941AP.fritz.box',
                'printer_model' => 'MUNBYN ITPP941AP',
                'pending_count' => count($pending),
                'pending' => array_values($pending),
                'history' => array_slice(array_reverse($history), 0, 25),
            ];
        });
    }

    /**
     * Bestätigt einen Druckauftrag durch Funkenwerk (completed / failed).
     */
    public function acknowledgeJob(string $jobId, string $status, ?string $errorMessage = null, ?string $printerIp = null): ?array
    {
        if (!Str::isUuid($jobId)) {
            return null;
        }

        return $this->withExclusiveLock(function () use ($jobId, $status, $errorMessage, $printerIp) {
            $jobs = $this->readJobsUnlocked();
            $updatedJob = null;

            foreach ($jobs as &$job) {
                if (($job['id'] ?? '') === $jobId) {
                    $job['status'] = in_array($status, ['completed', 'failed', 'printing'], true) ? $status : 'completed';
                    $job['printed_at'] = now()->toIso8601String();
                    $job['error_message'] = $errorMessage !== null ? Str::limit(strip_tags($errorMessage), 500) : null;
                    if ($printerIp) {
                        $job['printer_ip'] = self::sanitizePrivateLanIp($printerIp, $job['printer_ip'] ?? '192.168.188.149');
                    }
                    $updatedJob = $job;
                    break;
                }
            }
            unset($job);

            if ($updatedJob !== null) {
                $this->writeJobsUnlocked($jobs);
                return $this->sanitizeJobForPublicOutput($updatedJob, false);
            }

            return null;
        });
    }

    /**
     * Stellt einen bereits gedruckten oder fehlgeschlagenen Auftrag erneut auf 'pending'.
     */
    public function requeueJob(string $jobId): ?array
    {
        if (!Str::isUuid($jobId)) {
            return null;
        }

        return $this->withExclusiveLock(function () use ($jobId) {
            $jobs = $this->readJobsUnlocked();
            $requeued = null;

            foreach ($jobs as &$job) {
                if (($job['id'] ?? '') === $jobId) {
                    $job['status'] = 'pending';
                    $job['printed_at'] = null;
                    $job['error_message'] = null;
                    $job['created_at'] = now()->toIso8601String();
                    $requeued = $job;
                    break;
                }
            }
            unset($job);

            if ($requeued !== null) {
                $this->writeJobsUnlocked($jobs);
                return $this->sanitizeJobForPublicOutput($requeued, false);
            }

            return null;
        });
    }

    /**
     * Leert die abgeschlossenen/fehlgeschlagenen Einträge oder die gesamte Queue.
     */
    public function clearQueue(bool $includePending = false): void
    {
        $this->withExclusiveLock(function () use ($includePending) {
            if ($includePending) {
                $this->writeJobsUnlocked([]);
                return;
            }

            $jobs = array_values(array_filter($this->readJobsUnlocked(), fn(array $j) => ($j['status'] ?? '') === 'pending'));
            $this->writeJobsUnlocked($jobs);
        });
    }

    /**
     * Entfernt interne Dateisystempfade (shipping_label_path) vor der API-Ausgabe und bettet optional das validierte PDF als Base64 ein.
     */
    private function sanitizeJobForPublicOutput(array $job, bool $includePdfBase64): array
    {
        $labelPath = $job['shipping_label_path'] ?? null;
        unset($job['shipping_label_path']);

        $job['printer_ip'] = self::sanitizePrivateLanIp($job['printer_ip'] ?? '192.168.188.149');

        if ($includePdfBase64 && !empty($labelPath)) {
            $pdfBytes = $this->readLabelPdfBytes($labelPath);
            $job['pdf_base64'] = $pdfBytes !== null ? base64_encode($pdfBytes) : null;
            $job['has_pdf'] = $pdfBytes !== null;
        } else {
            $job['pdf_base64'] = null;
        }

        return $job;
    }

    private function createJobFromOrderAndShipment(
        OrderOrder $order,
        ?OrderShipment $shipment = null,
        string $type = 'order_label',
        int $packageIndex = 1,
        int $packageTotal = 1
    ): array {
        $hasShipping = !empty($order->shipping_address)
            && (isset($order->shipping_address['street']) || isset($order->shipping_address['address']) || isset($order->shipping_address['first_name']))
            && trim(($order->shipping_address['street'] ?? ($order->shipping_address['address'] ?? '')) . ($order->shipping_address['first_name'] ?? '')) !== '';

        $addr = $hasShipping ? $order->shipping_address : ($order->billing_address ?? []);
        $fullName = trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? ''));
        if ($fullName === '') {
            $fullName = $order->customer_name ?: 'Kunde';
        }

        $street = trim(($addr['street'] ?? ($addr['address'] ?? '')) . ' ' . ($addr['house_number'] ?? ''));
        $postalCode = trim((string) ($addr['postal_code'] ?? ($addr['zip'] ?? '')));
        $city = trim((string) ($addr['city'] ?? ''));
        $country = strtoupper(trim((string) ($addr['country'] ?? 'DE')));

        $items = [];
        foreach ($order->items ?? [] as $item) {
            $items[] = [
                'qty' => (int) $item->quantity,
                'name' => (string) $item->product_name,
                'sku' => (string) ($item->product?->sku ?? ''),
            ];
        }

        $weightDetails = (!$order->isOnlyDigital() && $order->items && $order->items->isNotEmpty())
            ? $order->getDhlWeightDetails(max(1, $packageTotal), 'box')
            : ['recommended_weight_per_package' => 0.5];

        $labelPath = $shipment?->shipping_label_path;
        $hasPdf = $labelPath ? ($this->readLabelPdfBytes($labelPath) !== null) : false;

        $job = [
            'id' => (string) Str::uuid(),
            'type' => $hasPdf ? 'dhl_pdf' : $type,
            'status' => 'pending',
            'printer_ip' => '192.168.188.149',
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'shipment_id' => $shipment ? (string) $shipment->id : null,
            'tracking_number' => $shipment?->tracking_number ?: ('SF-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $order->order_number)),
            'package_index' => $packageIndex,
            'package_total' => $packageTotal,
            'weight_kg' => (float) ($weightDetails['recommended_weight_per_package'] ?? 0.5),
            'is_express' => (bool) $order->is_express,
            'sender' => $this->resolveSenderAddress(),
            'recipient' => [
                'name' => $fullName,
                'company' => trim((string) ($addr['company'] ?? '')),
                'street' => $street,
                'postal_code' => $postalCode,
                'city' => $city,
                'country' => $country,
            ],
            'items' => $items,
            'shipping_label_path' => $labelPath,
            'has_pdf' => $hasPdf,
            'created_at' => now()->toIso8601String(),
            'printed_at' => null,
            'error_message' => null,
        ];

        $this->appendJob($job);
        return $this->sanitizeJobForPublicOutput($job, false);
    }

    private function resolveSenderAddress(): array
    {
        return [
            'name' => shop_setting('company_name') ?: 'Mein-Seelenfunke · Alina Steinhauer',
            'street' => trim((shop_setting('company_street') ?: 'Carl-Goerdeler-Ring') . ' ' . (shop_setting('company_street_number') ?: '26')),
            'postal_code' => shop_setting('company_zip') ?: '38518',
            'city' => shop_setting('company_city') ?: 'Gifhorn',
            'country' => 'DE',
        ];
    }

    public function readLabelPdfBytes(?string $path): ?string
    {
        if (!$path || !is_string($path) || str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if (strtolower(pathinfo($normalized, PATHINFO_EXTENSION)) !== 'pdf') {
            return null;
        }

        foreach (['local', 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($normalized)) {
                    $diskRoot = realpath(Storage::disk($disk)->path(''));
                    $fullPath = Storage::disk($disk)->path($normalized);
                    $realFullPath = realpath($fullPath);

                    if (
                        !$diskRoot ||
                        !$realFullPath ||
                        !str_starts_with($realFullPath, $diskRoot . DIRECTORY_SEPARATOR) ||
                        is_link($fullPath) ||
                        !is_file($realFullPath)
                    ) {
                        continue;
                    }

                    $size = @filesize($realFullPath);
                    if ($size === false || $size < 64 || $size > self::MAX_PDF_BYTES) {
                        continue;
                    }

                    $bytes = Storage::disk($disk)->get($normalized);
                    if (is_string($bytes) && strncmp($bytes, '%PDF-', 5) === 0) {
                        return $bytes;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function appendJob(array $job): void
    {
        $this->withExclusiveLock(function () use ($job) {
            $jobs = $this->readJobsUnlocked();
            $jobs[] = $job;

            $cutoff = now()->subDays(self::HISTORY_TTL_DAYS)->timestamp;
            $pending = array_filter($jobs, fn(array $j) => ($j['status'] ?? '') === 'pending');
            $done = array_filter($jobs, function (array $j) use ($cutoff) {
                if (($j['status'] ?? '') === 'pending') {
                    return false;
                }
                $ts = !empty($j['created_at']) ? strtotime((string) $j['created_at']) : time();
                return $ts === false || $ts >= $cutoff;
            });

            if (count($done) > self::MAX_HISTORY) {
                $done = array_slice($done, -self::MAX_HISTORY);
            }

            $this->writeJobsUnlocked(array_values(array_merge($done, $pending)));
        });
    }

    /**
     * Führt eine Queue-Operation unter exklusivem Dateisperr-Mutex (flock LOCK_EX) aus,
     * um Race-Conditions zwischen 3s-Polling, Ack-Calls und Admin-Klicks auszuschließen.
     */
    private function withExclusiveLock(callable $callback): mixed
    {
        $lockPath = Storage::disk('local')->path(self::LOCK_FILE);
        $dir = dirname($lockPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $fp = @fopen($lockPath, 'c+');
        if ($fp === false) {
            return $callback();
        }

        try {
            @flock($fp, LOCK_EX);
            return $callback();
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    private function readJobsUnlocked(): array
    {
        try {
            if (!Storage::disk('local')->exists(self::QUEUE_FILE)) {
                return [];
            }
            $raw = trim((string) Storage::disk('local')->get(self::QUEUE_FILE));
            if ($raw === '') {
                return [];
            }

            // At-Rest Entschlüsselung (AES-256-CBC + HMAC-SHA256 über APP_KEY)
            if (str_starts_with($raw, 'ENC:v1:')) {
                $json = Crypt::decryptString(substr($raw, 7));
                $decoded = json_decode($json, true);
                return is_array($decoded) ? $decoded : [];
            }

            // Rückwärtskompatible Migration bestehender Klartext-Queue-Dateien -> sofort verschlüsseln
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->writeJobsUnlocked($decoded);
                return $decoded;
            }

            return [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function writeJobsUnlocked(array $jobs): void
    {
        try {
            $json = json_encode(array_values($jobs), JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) {
                return;
            }
            $encryptedPayload = 'ENC:v1:' . Crypt::encryptString($json);
            Storage::disk('local')->put(self::QUEUE_FILE, $encryptedPayload);
            $fullPath = Storage::disk('local')->path(self::QUEUE_FILE);
            @chmod($fullPath, 0660);
        } catch (\Throwable) {
        }
    }
}
