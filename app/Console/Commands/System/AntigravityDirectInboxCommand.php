<?php

namespace App\Console\Commands\System;

use App\Services\System\AntigravityDirectInboxService;
use Illuminate\Console\Command;

class AntigravityDirectInboxCommand extends Command
{
    protected $signature = 'system:ag-inbox
        {--peek-pending : Gibt alle pending-Eintraege chronologisch (aelteste zuerst) inkl. attachment_base64 als JSON aus}
        {--pull-pending : Gibt alle pending-Eintraege chronologisch (aelteste zuerst) aus und stellt sie atomar auf delivered}
        {--mark-delivered= : Setzt einen Eintrag oder kommagetrennte IDs von pending auf delivered}
        {--complete= : Setzt einen Eintrag anhand seiner ID auf completed}
        {--ignore-missing : Beendet bei nicht gefundenen Eintraegen ohne Fehler (status=not_found)}
        {--summary-b64= : Base64-codierte Kurz-Antwort fuer --complete}
        {--summary= : Klartext-Kurz-Antwort fuer --complete}
        {--list : Gibt Status-Zaehler und Eintraege (neueste zuerst) als JSON aus}
        {--import-b64= : Importiert/merged ein Base64-codiertes JSON-Array von Items idempotent}';

    protected $description = '24/7 SSH- und CLI-Bruecke fuer das direkte Antigravity-Postfach (Android <-> Stage <-> Master-Chat)';

    public function handle(AntigravityDirectInboxService $inbox): int
    {
        try {
            $importB64 = trim((string) $this->option('import-b64'));
            if ($importB64 !== '') {
                $rawJson = base64_decode(preg_replace('/\s+/', '', $importB64) ?? '', true);
                if (!is_string($rawJson) || $rawJson === '') {
                    return $this->emitJsonError('Ungueltiges Base64-Payload bei --import-b64.');
                }

                $decoded = json_decode($rawJson, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    return $this->emitJsonError('Dekodierter Inhalt bei --import-b64 ist kein valides JSON.');
                }

                $list = isset($decoded['items']) && is_array($decoded['items'])
                    ? $decoded['items']
                    : (array_is_list($decoded) ? $decoded : []);

                $merged = $inbox->importItems($list);

                return $this->emitJson([
                    'status' => 'success',
                    'merged' => $merged,
                ]);
            }

            $completeId = trim((string) $this->option('complete'));
            if ($completeId !== '') {
                $summary = null;
                $summaryB64 = trim((string) $this->option('summary-b64'));
                $summaryPlain = $this->option('summary');

                if ($summaryB64 !== '') {
                    $decodedSummary = base64_decode(preg_replace('/\s+/', '', $summaryB64) ?? '', true);
                    if (!is_string($decodedSummary)) {
                        return $this->emitJsonError('Ungueltiges Base64 in --summary-b64.');
                    }
                    $summary = $decodedSummary;
                } elseif (is_string($summaryPlain) && trim($summaryPlain) !== '') {
                    $summary = $summaryPlain;
                }

                $completedItem = $inbox->completeMessage($completeId, $summary);
                if ($completedItem === null) {
                    if ((bool) $this->option('ignore-missing')) {
                        return $this->emitJson([
                            'status' => 'not_found',
                            'message' => 'Eintrag lokal nicht gefunden: ' . $completeId,
                        ]);
                    }
                    return $this->emitJsonError('Eintrag nicht gefunden: ' . $completeId);
                }

                return $this->emitJson([
                    'status' => 'success',
                    'item' => $completedItem,
                ]);
            }

            $markDeliveredRaw = trim((string) $this->option('mark-delivered'));
            if ($markDeliveredRaw !== '') {
                $ids = array_values(array_filter(array_map('trim', explode(',', $markDeliveredRaw))));
                $updated = $inbox->markDelivered($ids);

                return $this->emitJson([
                    'status' => 'success',
                    'updated' => $updated,
                ]);
            }

            if ((bool) $this->option('pull-pending')) {
                $items = $inbox->pullPending(true);

                return $this->emitJson([
                    'status' => 'success',
                    'count' => count($items),
                    'items' => $items,
                ]);
            }

            if ((bool) $this->option('peek-pending')) {
                $items = $inbox->peekPending(true);

                return $this->emitJson([
                    'status' => 'success',
                    'count' => count($items),
                    'items' => $items,
                ]);
            }

            $snapshot = $inbox->listInbox();

            return $this->emitJson([
                'status' => 'success',
                'counts' => $snapshot['counts'],
                'items' => $snapshot['items'],
            ]);
        } catch (\Throwable $e) {
            return $this->emitJsonError($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emitJson(array $payload): int
    {
        $this->line((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function emitJsonError(string $message): int
    {
        $this->line((string) json_encode([
            'status' => 'error',
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::FAILURE;
    }
}
