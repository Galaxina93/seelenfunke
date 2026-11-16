<?php

namespace App\Services\System;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

class AntigravityDirectInboxService
{
    public const INBOX_FILENAME = 'antigravity_direct_inbox.json';
    public const LOCK_FILENAME = 'antigravity_direct_inbox.lock';
    public const MEDIA_DIR_NAME = 'ag_direct_media';

    public const MAX_ITEMS = 200;
    public const COMPLETED_RETENTION_DAYS = 30;
    public const MAX_ATTACHMENT_BYTES = 8388608; // 8 MB
    public const MAX_CHECKLIST_ITEMS = 25;
    public const MAX_CHECKLIST_ITEM_LENGTH = 300;

    public const ALLOWED_EXECUTION_MODES = ['concept', 'direct'];
    public const ALLOWED_CHANNELS = ['leitung', 'shop', 'android', 'minipc', 'buchhaltung', 'new'];
    public const ALLOWED_STATUSES = ['pending', 'delivered', 'awaiting_go', 'completed'];
    public const ALLOWED_DECISIONS = ['approved', 'info_provided', 'dismissed', 'deferred'];
    public const ALLOWED_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];
    public const ALLOWED_IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    public function __construct(
        private readonly ?string $privateRootOverride = null
    ) {
    }

    public function privateRootPath(): string
    {
        $dir = $this->privateRootOverride ?? storage_path('app/private');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0700, true, true);
        }

        return rtrim($dir, '/\\');
    }

    public function inboxFilePath(): string
    {
        return $this->privateRootPath() . DIRECTORY_SEPARATOR . self::INBOX_FILENAME;
    }

    public function lockFilePath(): string
    {
        return $this->privateRootPath() . DIRECTORY_SEPARATOR . self::LOCK_FILENAME;
    }

    public function mediaDirectoryPath(): string
    {
        $dir = $this->privateRootPath() . DIRECTORY_SEPARATOR . self::MEDIA_DIR_NAME;
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0700, true, true);
        }

        return $dir;
    }

    public static function isValidItemId(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{3,80}$/', $id);
    }

    public function normalizeChannel(mixed $rawChannel): string
    {
        if (!is_scalar($rawChannel) || $rawChannel === null) {
            return 'leitung';
        }

        $normalized = strtolower(trim((string) $rawChannel));

        return in_array($normalized, self::ALLOWED_CHANNELS, true)
            ? $normalized
            : 'leitung';
    }

    /**
     * Normalisiert und validiert die Checkliste (Array oder JSON-String eines Arrays).
     *
     * @return array<int, string>
     */
    public function normalizeChecklist(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new InvalidArgumentException('Die Checkliste muss ein Array oder ein valides JSON-Array sein.');
            }

            $raw = $decoded;
        }

        if (!is_array($raw)) {
            throw new InvalidArgumentException('Ungültiges Format für die Checkliste.');
        }

        $cleaned = [];
        foreach ($raw as $entry) {
            if (!is_scalar($entry) && $entry !== null) {
                throw new InvalidArgumentException('Checklisten-Einträge müssen Textwerte sein.');
            }

            $line = trim((string) $entry);
            if ($line === '') {
                continue;
            }

            if (mb_strlen($line) > self::MAX_CHECKLIST_ITEM_LENGTH) {
                throw new InvalidArgumentException(
                    'Einzelne Checklisten-Punkte dürfen maximal ' . self::MAX_CHECKLIST_ITEM_LENGTH . ' Zeichen enthalten.'
                );
            }

            $cleaned[] = $line;
        }

        if (count($cleaned) > self::MAX_CHECKLIST_ITEMS) {
            throw new InvalidArgumentException(
                'Die Checkliste darf maximal ' . self::MAX_CHECKLIST_ITEMS . ' Punkte enthalten.'
            );
        }

        return array_values($cleaned);
    }

    /**
     * Validiert einen Multipart-Upload als echtes Bild (PNG, JPG/JPEG, WEBP, max. 8 MB).
     *
     * @return array{bytes: string, mime: string, ext: string, name: string}
     */
    public function validateUploadedAttachment(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new InvalidArgumentException('Ungültiger Datei-Upload.');
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::MAX_ATTACHMENT_BYTES) {
            throw new InvalidArgumentException('Der Bildanhang darf maximal 8 MB groß sein.');
        }

        $realPath = $file->getRealPath();
        if (!is_string($realPath) || $realPath === '' || is_link($realPath) || !is_file($realPath)) {
            throw new InvalidArgumentException('Hochgeladene temporäre Datei konnte nicht geprüft werden.');
        }

        $rawBytes = @file_get_contents($realPath);
        if (!is_string($rawBytes) || $rawBytes === '') {
            throw new InvalidArgumentException('Hochgeladener Bildanhang konnte nicht gelesen werden.');
        }

        return $this->validateAndNormalizeImageBytes(
            rawBytes: $rawBytes,
            originalName: $file->getClientOriginalName(),
            declaredMime: $file->getClientMimeType()
        );
    }

    /**
     * Validiert einen Base64-codierten Bildanhang.
     *
     * @return array{bytes: string, mime: string, ext: string, name: string}
     */
    public function validateBase64Attachment(
        string $base64Payload,
        ?string $originalName = null,
        ?string $declaredMime = null
    ): array {
        $cleaned = trim($base64Payload);
        if ($cleaned === '') {
            throw new InvalidArgumentException('Leerer Base64-Bildanhang übergeben.');
        }

        if (preg_match('/^data:([a-zA-Z0-9.+\/-]+);base64,(.*)$/s', $cleaned, $matches)) {
            if ($declaredMime === null || trim($declaredMime) === '') {
                $declaredMime = $matches[1];
            }
            $cleaned = $matches[2];
        }

        $cleaned = preg_replace('/\s+/', '', $cleaned) ?? '';
        $rawBytes = base64_decode($cleaned, true);
        if (!is_string($rawBytes) || $rawBytes === '') {
            throw new InvalidArgumentException('Ungültige Base64-Daten für den Bildanhang.');
        }

        return $this->validateAndNormalizeImageBytes(
            rawBytes: $rawBytes,
            originalName: $originalName,
            declaredMime: $declaredMime
        );
    }

    /**
     * Echte Binär- & MIME-Prüfung gegen Polyglot- und PHP-Uploads.
     *
     * @return array{bytes: string, mime: string, ext: string, name: string}
     */
    public function validateAndNormalizeImageBytes(
        string $rawBytes,
        ?string $originalName = null,
        ?string $declaredMime = null
    ): array {
        $byteLength = strlen($rawBytes);
        if ($byteLength <= 0 || $byteLength > self::MAX_ATTACHMENT_BYTES) {
            throw new InvalidArgumentException('Der Bildanhang muss zwischen 1 Byte und 8 MB groß sein.');
        }

        $origBase = $originalName !== null ? basename(str_replace('\\', '/', trim($originalName))) : '';
        $origExt = $origBase !== '' ? strtolower((string) pathinfo($origBase, PATHINFO_EXTENSION)) : '';

        if ($origExt !== '' && !in_array($origExt, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Dateiendung nicht erlaubt. Zulässig sind nur PNG, JPG, JPEG und WEBP.');
        }

        if ($declaredMime !== null && trim($declaredMime) !== '') {
            $normDeclared = strtolower(trim($declaredMime));
            if ($normDeclared === 'image/jpg') {
                $normDeclared = 'image/jpeg';
            }
            if (
                $normDeclared !== 'application/octet-stream'
                && !in_array($normDeclared, self::ALLOWED_IMAGE_MIMES, true)
            ) {
                throw new InvalidArgumentException('Deklarierter MIME-Typ ist nicht zulässig.');
            }
        }

        if (stripos($rawBytes, '<?php') !== false || stripos($rawBytes, '<?=') !== false) {
            throw new InvalidArgumentException('Sicherheitsblockade: Ausführbarer PHP-Code im Anhang erkannt.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = strtolower((string) $finfo->buffer($rawBytes));
        if (!in_array($realMime, self::ALLOWED_IMAGE_MIMES, true)) {
            throw new InvalidArgumentException('Echte MIME-Prüfung fehlgeschlagen: Datei ist kein gültiges PNG-, JPEG- oder WEBP-Bild.');
        }

        $imageInfo = @getimagesizefromstring($rawBytes);
        if (!is_array($imageInfo) || ($imageInfo[0] ?? 0) < 1 || ($imageInfo[1] ?? 0) < 1) {
            throw new InvalidArgumentException('Bildstruktur ungültig oder beschädigt.');
        }

        $imageType = (int) ($imageInfo[2] ?? 0);
        $canonicalExt = match ($imageType) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => $origExt === 'jpeg' ? 'jpeg' : 'jpg',
            IMAGETYPE_WEBP => 'webp',
            default => throw new InvalidArgumentException('Nicht unterstützter Bildtyp.'),
        };

        $expectedMimeForType = match ($imageType) {
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_WEBP => 'image/webp',
        };

        if ($realMime !== $expectedMimeForType) {
            throw new InvalidArgumentException('Abweichung zwischen MIME-Header und Bild-Container erkannt.');
        }

        $rawStem = $origBase !== '' ? (string) pathinfo($origBase, PATHINFO_FILENAME) : '';
        $cleanStem = preg_replace('/[^A-Za-z0-9ÄÖÜäöüß._-]/u', '_', $rawStem) ?? '';
        $cleanStem = trim(preg_replace('/_+/', '_', $cleanStem) ?? '', '._- ');
        if ($cleanStem === '') {
            $cleanStem = 'ag_screenshot_' . now('Europe/Berlin')->format('Ymd_His');
        }
        $cleanStem = mb_substr($cleanStem, 0, 80);
        $safeDisplayName = $cleanStem . '.' . $canonicalExt;

        return [
            'bytes' => $rawBytes,
            'mime' => $realMime,
            'ext' => $canonicalExt,
            'name' => $safeDisplayName,
        ];
    }

    /**
     * Listet Status-Zähler und Einträge (neueste zuerst) für App & CLI.
     *
     * @return array{counts: array{pending: int, delivered: int, completed: int}, items: array<int, array<string, mixed>>}
     */
    public function listInbox(?int $limit = null): array
    {
        return $this->withSharedLock(function (array $items) use ($limit): array {
            $counts = $this->computeCounts($items);
            $sortedDesc = $this->sortNewestFirst($items);

            if ($limit !== null) {
                $clamped = max(1, min(self::MAX_ITEMS, $limit));
                $sortedDesc = array_slice($sortedDesc, 0, $clamped);
            }

            $formatted = array_map(fn (array $item): array => $this->formatItemSchema($item), $sortedDesc);

            return [
                'counts' => $counts,
                'items' => array_values($formatted),
            ];
        });
    }

    /**
     * Legt eine neue Direktnachricht atomar (mit client_uuid-Idempotenz) an.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{bytes: string, mime: string, ext: string, name: string}|null  $validatedAttachment
     * @return array{idempotent_replay: bool, item: array<string, mixed>}
     */
    public function enqueueMessage(array $payload, ?array $validatedAttachment = null): array
    {
        return $this->withExclusiveLock(function (array $items) use ($payload, $validatedAttachment): array {
            $clientUuid = isset($payload['client_uuid']) ? trim((string) $payload['client_uuid']) : '';
            $clientUuid = $clientUuid !== '' ? mb_substr($clientUuid, 0, 64) : null;

            if ($clientUuid !== null) {
                foreach ($items as $existing) {
                    if (($existing['client_uuid'] ?? null) === $clientUuid) {
                        return [
                            [
                                'idempotent_replay' => true,
                                'item' => $this->formatItemSchema($existing),
                            ],
                            $items,
                            false,
                        ];
                    }
                }
            }

            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '') {
                throw new InvalidArgumentException('Titel darf nicht leer sein.');
            }
            $title = mb_substr($title, 0, 500);

            $body = isset($payload['body']) && $payload['body'] !== null
                ? mb_substr(trim((string) $payload['body']), 0, 5000)
                : '';

            $checklist = $this->normalizeChecklist($payload['checklist'] ?? []);

            $mode = isset($payload['execution_mode']) ? trim((string) $payload['execution_mode']) : 'concept';
            if (!in_array($mode, self::ALLOWED_EXECUTION_MODES, true)) {
                $mode = 'concept';
            }

            $channel = $this->normalizeChannel($payload['channel'] ?? 'leitung');

            $sourceRaw = isset($payload['source']) ? trim((string) $payload['source']) : '';
            $source = ($sourceRaw !== '' && preg_match('/^[A-Za-z0-9_-]{2,40}$/', $sourceRaw))
                ? $sourceRaw
                : 'android_app';

            $id = $this->generateUniqueId($items);

            $hasAttachment = false;
            $attachmentName = null;
            $attachmentMime = null;
            $attachmentRelPath = null;

            if ($validatedAttachment !== null) {
                $stored = $this->writeMediaAttachment($id, 0, $validatedAttachment);
                $hasAttachment = true;
                $attachmentName = $stored['name'];
                $attachmentMime = $stored['mime'];
                $attachmentRelPath = $stored['rel_path'];
            }

            $item = $this->formatItemSchema([
                'id' => $id,
                'client_uuid' => $clientUuid,
                'channel' => $channel,
                'title' => $title,
                'body' => $body,
                'checklist' => $checklist,
                'execution_mode' => $mode,
                'status' => 'pending',
                'source' => $source,
                'has_attachment' => $hasAttachment,
                'attachment_name' => $attachmentName,
                'attachment_mime' => $attachmentMime,
                'attachment_rel_path' => $attachmentRelPath,
                'ag_response_summary' => null,
                'pending_question' => null,
                'awaiting_go_at' => null,
                'created_at' => now('Europe/Berlin')->toIso8601String(),
                'delivered_at' => null,
                'completed_at' => null,
            ]);

            $autoResolvedIds = $this->autoCloseMatchingAwaitingItemsUnlocked($items, $item);
            $items[] = $item;

            return [
                [
                    'idempotent_replay' => false,
                    'item' => $item,
                    'auto_resolved_ids' => $autoResolvedIds,
                ],
                $items,
                true,
            ];
        });
    }

    /**
     * Alias für enqueueMessage zur einheitlichen Service-Nutzung.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{bytes: string, mime: string, ext: string, name: string}|null  $validatedAttachment
     * @return array{idempotent_replay: bool, item: array<string, mixed>, auto_resolved_ids?: array<int, string>}
     */
    public function createItem(array $payload, ?array $validatedAttachment = null): array
    {
        return $this->enqueueMessage($payload, $validatedAttachment);
    }

    /**
     * Löst den privaten Dateipfad eines Bildanhangs sicher (Path-Traversal-geschützt) auf.
     *
     * @return array{path: string, mime: string, name: string, item: array<string, mixed>}|null
     */
    public function resolveAttachmentForStreaming(string $id): ?array
    {
        if (!self::isValidItemId($id)) {
            return null;
        }

        return $this->withSharedLock(function (array $items) use ($id): ?array {
            foreach ($items as $item) {
                if (($item['id'] ?? '') !== $id) {
                    continue;
                }

                if (empty($item['has_attachment']) || empty($item['attachment_rel_path'])) {
                    return null;
                }

                $absPath = $this->resolveVerifiedMediaFilePath((string) $item['attachment_rel_path']);
                if ($absPath === null) {
                    return null;
                }

                $mime = (string) ($item['attachment_mime'] ?? 'image/png');
                if (!in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
                    $mime = 'application/octet-stream';
                }

                $rawName = (string) ($item['attachment_name'] ?? basename($absPath));
                $safeAsciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawName) ?: basename($absPath);

                return [
                    'path' => $absPath,
                    'mime' => $mime,
                    'name' => $safeAsciiName,
                    'item' => $this->formatItemSchema($item),
                ];
            }

            return null;
        });
    }

    /**
     * Löscht einen Eintrag inklusive zugehöriger privater Mediendatei.
     */
    public function deleteMessage(string $id): bool
    {
        if (!self::isValidItemId($id)) {
            return false;
        }

        return $this->withExclusiveLock(function (array $items) use ($id): array {
            $remaining = [];
            $deleted = false;

            foreach ($items as $item) {
                if (($item['id'] ?? '') === $id) {
                    $this->deleteItemMediaFile($item);
                    $deleted = true;
                    continue;
                }
                $remaining[] = $item;
            }

            return [$deleted, $remaining, $deleted];
        });
    }

    /**
     * Liest alle offenen (pending) Einträge chronologisch (älteste zuerst) ohne Statusänderung (2-Phasen-Commit).
     *
     * @return array<int, array<string, mixed>>
     */
    public function peekPending(bool $includeAttachmentBase64 = true): array
    {
        return $this->withSharedLock(function (array $items) use ($includeAttachmentBase64): array {
            $pending = array_values(array_filter(
                $this->sortOldestFirst($items),
                static fn (array $item): bool => ($item['status'] ?? '') === 'pending'
            ));

            return array_map(
                fn (array $item): array => $this->enrichItemForBridge($item, $includeAttachmentBase64),
                $pending
            );
        });
    }

    /**
     * Liest alle offenen (pending) Einträge chronologisch (älteste zuerst) und setzt sie atomar auf delivered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pullPending(bool $includeAttachmentBase64 = true): array
    {
        return $this->withExclusiveLock(function (array $items) use ($includeAttachmentBase64): array {
            $ordered = $this->sortOldestFirst($items);
            $deliveredAt = now('Europe/Berlin')->toIso8601String();
            $pulled = [];

            foreach ($ordered as &$item) {
                if (($item['status'] ?? '') === 'pending') {
                    $item['status'] = 'delivered';
                    $item['delivered_at'] = $deliveredAt;
                    $pulled[] = $this->enrichItemForBridge($item, $includeAttachmentBase64);
                }
            }
            unset($item);

            return [$pulled, $ordered, count($pulled) > 0];
        });
    }

    /**
     * Markiert konkrete pending-Einträge anhand ihrer IDs als delivered.
     *
     * @param  array<int, string>  $ids
     */
    public function markDelivered(array $ids): int
    {
        $validIds = array_values(array_unique(array_filter(
            array_map('trim', $ids),
            static fn (string $id): bool => self::isValidItemId($id)
        )));

        if ($validIds === []) {
            return 0;
        }

        return $this->withExclusiveLock(function (array $items) use ($validIds): array {
            $idLookup = array_fill_keys($validIds, true);
            $deliveredAt = now('Europe/Berlin')->toIso8601String();
            $updated = 0;

            foreach ($items as &$item) {
                $id = (string) ($item['id'] ?? '');
                if (isset($idLookup[$id]) && ($item['status'] ?? '') === 'pending') {
                    $item['status'] = 'delivered';
                    $item['delivered_at'] = $deliveredAt;
                    $updated++;
                }
            }
            unset($item);

            return [$updated, $items, $updated > 0];
        });
    }

    /**
     * Markiert einen Eintrag als abgeschlossen (completed) oder setzt ihn intelligent auf awaiting_go,
     * sobald ein Konzept oder eine offene Rückfrage von Antigravity auf eine Entscheidung/Antwort von Alina wartet.
     *
     * @return array<string, mixed>|null
     */
    public function completeMessage(string $id, ?string $summary = null, bool $forceFinalComplete = false): ?array
    {
        if (!self::isValidItemId($id)) {
            return null;
        }

        return $this->withExclusiveLock(function (array $items) use ($id, $summary, $forceFinalComplete): array {
            $cleanSummary = ($summary !== null && trim($summary) !== '')
                ? mb_substr(trim($summary), 0, 5000)
                : null;
            $nowIso = now('Europe/Berlin')->toIso8601String();
            $foundItem = null;

            foreach ($items as &$item) {
                if (($item['id'] ?? '') === $id) {
                    if (!$forceFinalComplete && self::requiresGoOrDecision($item, $cleanSummary)) {
                        $item['status'] = 'awaiting_go';
                        $item['awaiting_go_at'] = $nowIso;
                        $item['completed_at'] = null;
                        $item['ag_response_summary'] = $cleanSummary;
                        $item['pending_question'] = $cleanSummary
                            ?? ('Konzept "' . (string) ($item['title'] ?? $id) . '" wartet auf deine Freigabe (Go).');
                    } else {
                        $item['status'] = 'completed';
                        $item['completed_at'] = $nowIso;
                        if ($cleanSummary !== null) {
                            $item['ag_response_summary'] = $cleanSummary;
                        }
                        $item['pending_question'] = null;
                    }
                    $foundItem = $this->formatItemSchema($item);
                    break;
                }
            }
            unset($item);

            return [$foundItem, $items, $foundItem !== null];
        });
    }

    /**
     * Alias für completeMessage zur einheitlichen Service-Nutzung.
     *
     * @return array<string, mixed>|null
     */
    public function completeItem(string $id, ?string $summary = null, bool $forceFinalComplete = false): ?array
    {
        return $this->completeMessage($id, $summary, $forceFinalComplete);
    }

    /**
     * Prüft, ob ein Ticket (Konzept-Modus oder Rückfrage im Summary) auf eine Entscheidung/Antwort von Alina wartet.
     *
     * @param  array<string, mixed>  $item
     */
    public static function requiresGoOrDecision(array $item, ?string $summary): bool
    {
        if (($item['execution_mode'] ?? 'concept') === 'concept') {
            return true;
        }

        if ($summary === null || trim($summary) === '') {
            return false;
        }

        $trimmed = rtrim($summary);
        if (str_ends_with($trimmed, '?')) {
            return true;
        }

        $lower = mb_strtolower($trimmed);
        $markers = [
            'wartet auf',
            'warte auf',
            'go benoetigt',
            'go benötigt',
            'dein go',
            'ihr go',
            'entscheidung',
            'bitte um freigabe',
            'freigabe erforderlich',
            'rückfrage',
            'rueckfrage',
            'welche option',
        ];

        foreach ($markers as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Liefert alle offenen Freigabe- und Rückfrage-Blockaden chronologisch (älteste zuerst) inkl. Wartezeit in Minuten.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPendingBlockades(): array
    {
        return $this->withSharedLock(function (array $items): array {
            $ordered = $this->sortOldestFirst($items);
            $now = now('Europe/Berlin');
            $blockades = [];

            foreach ($ordered as $item) {
                $status = (string) ($item['status'] ?? '');
                $mode = (string) ($item['execution_mode'] ?? 'concept');
                $summary = isset($item['ag_response_summary']) ? trim((string) $item['ag_response_summary']) : '';

                $isAwaitingGo = $status === 'awaiting_go';
                $isDeliveredConceptWithSummary = $status === 'delivered' && $mode === 'concept' && $summary !== '';

                if (!$isAwaitingGo && !$isDeliveredConceptWithSummary) {
                    continue;
                }

                $formatted = $this->formatItemSchema($item);
                $waitingRef = (string) ($formatted['awaiting_go_at'] ?: $formatted['delivered_at'] ?: $formatted['created_at']);
                $waitingMinutes = 0;
                if ($waitingRef !== '') {
                    try {
                        $waitingMinutes = max(0, (int) Carbon::parse($waitingRef)->diffInMinutes($now, false));
                    } catch (\Throwable) {
                        $waitingMinutes = 0;
                    }
                }

                $question = $formatted['pending_question']
                    ?: $formatted['ag_response_summary']
                    ?: ('Konzept "' . $formatted['title'] . '" wartet auf dein Go oder deine Entscheidung.');

                $blockades[] = [
                    'id' => $formatted['id'],
                    'channel' => $formatted['channel'],
                    'title' => $formatted['title'],
                    'execution_mode' => $formatted['execution_mode'],
                    'status' => $formatted['status'],
                    'pending_question' => $question,
                    'ag_response_summary' => $formatted['ag_response_summary'],
                    'waiting_since' => $waitingRef,
                    'waiting_minutes' => $waitingMinutes,
                ];
            }

            return $blockades;
        });
    }

    /**
     * Löst ein wartendes Blockade-Ticket atomar auf (approved, info_provided, dismissed oder deferred)
     * und reiht bei Freigabe/Antwort sofort ein direktes Folge-Ticket im exakt gleichen Kanal ein.
     *
     * @return array{decision: string, resolved_item: array<string, mixed>, followup_item: array<string, mixed>|null}|null
     */
    public function resolveBlockadeItem(
        string $id,
        string $decision = 'approved',
        string $answerText = '',
        ?string $actor = null
    ): ?array {
        $targetId = trim($id);
        if (!self::isValidItemId($targetId)) {
            throw new InvalidArgumentException('Ungültige Ticket-ID für die Stau-Auflösung.');
        }

        $normalizedDecision = match (strtolower(trim($decision))) {
            'go', 'approve', 'approved' => 'approved',
            'answer', 'info', 'info_provided' => 'info_provided',
            'dismiss', 'dismissed', 'reject', 'rejected', 'cancel' => 'dismissed',
            'defer', 'deferred', 'later', 'snooze' => 'deferred',
            default => strtolower(trim($decision)),
        };

        if (!in_array($normalizedDecision, self::ALLOWED_DECISIONS, true)) {
            throw new InvalidArgumentException(
                'Ungültige Entscheidung. Erlaubt sind: approved, info_provided, dismissed, deferred.'
            );
        }

        $cleanAnswer = mb_substr(trim($answerText), 0, 4000);
        $actorLabel = trim((string) ($actor ?: 'Alina')) ?: 'Alina';

        return $this->withExclusiveLock(function (array $items) use ($targetId, $normalizedDecision, $cleanAnswer, $actorLabel): array {
            $nowIso = now('Europe/Berlin')->toIso8601String();
            $targetIdx = null;

            foreach ($items as $idx => $candidate) {
                if (($candidate['id'] ?? '') === $targetId) {
                    $targetIdx = $idx;
                    break;
                }
            }

            if ($targetIdx === null) {
                return [null, $items, false];
            }

            $orig = $items[$targetIdx];
            $origTitle = (string) ($orig['title'] ?? $targetId);
            $origChannel = $this->normalizeChannel($orig['channel'] ?? 'leitung');
            $existingSummary = isset($orig['ag_response_summary']) && trim((string) $orig['ag_response_summary']) !== ''
                ? trim((string) $orig['ag_response_summary'])
                : '';

            if ($normalizedDecision === 'deferred') {
                $orig['status'] = 'awaiting_go';
                $orig['awaiting_go_at'] = $nowIso;
                if ($cleanAnswer !== '') {
                    $note = "[Zurückgestellt durch {$actorLabel}: {$cleanAnswer}]";
                    $orig['ag_response_summary'] = mb_substr(
                        $existingSummary !== '' ? ($existingSummary . "\n" . $note) : $note,
                        0,
                        5000
                    );
                }
                if (empty($orig['pending_question'])) {
                    $orig['pending_question'] = $existingSummary !== ''
                        ? $existingSummary
                        : ('Konzept "' . $origTitle . '" wartet weiter auf deine Entscheidung.');
                }

                $resolvedFormatted = $this->formatItemSchema($orig);
                $items[$targetIdx] = $resolvedFormatted;

                return [
                    [
                        'decision' => 'deferred',
                        'resolved_item' => $resolvedFormatted,
                        'followup_item' => null,
                    ],
                    $items,
                    true,
                ];
            }

            if ($normalizedDecision === 'dismissed') {
                $orig['status'] = 'completed';
                $orig['completed_at'] = $nowIso;
                $orig['pending_question'] = null;
                $dismissNote = "[Verworfen durch {$actorLabel}" . ($cleanAnswer !== '' ? ": {$cleanAnswer}" : '') . ']';
                $orig['ag_response_summary'] = mb_substr(
                    $existingSummary !== '' ? ($existingSummary . "\n" . $dismissNote) : $dismissNote,
                    0,
                    5000
                );

                $resolvedFormatted = $this->formatItemSchema($orig);
                $items[$targetIdx] = $resolvedFormatted;

                return [
                    [
                        'decision' => 'dismissed',
                        'resolved_item' => $resolvedFormatted,
                        'followup_item' => null,
                    ],
                    $items,
                    true,
                ];
            }

            // approved oder info_provided -> Original-Blockade final schließen + Direkt-Folgeauftrag im gleichen Kanal anlegen
            $orig['status'] = 'completed';
            $orig['completed_at'] = $nowIso;
            $origQuestion = isset($orig['pending_question']) && trim((string) $orig['pending_question']) !== ''
                ? trim((string) $orig['pending_question'])
                : $existingSummary;
            $orig['pending_question'] = null;

            $approvalLabel = $normalizedDecision === 'approved' ? 'GO erteilt' : 'Antwort geliefert';
            $resolutionNote = "[{$approvalLabel} durch {$actorLabel}"
                . ($cleanAnswer !== '' ? ": {$cleanAnswer}" : '')
                . ']';
            $orig['ag_response_summary'] = mb_substr(
                $existingSummary !== '' ? ($existingSummary . "\n" . $resolutionNote) : $resolutionNote,
                0,
                5000
            );

            $resolvedFormatted = $this->formatItemSchema($orig);
            $items[$targetIdx] = $resolvedFormatted;

            $followupId = $this->generateUniqueId($items);
            $followupTitle = mb_substr('GO & Antwort zu: ' . $origTitle, 0, 500);
            $effectiveAnswer = $cleanAnswer !== ''
                ? $cleanAnswer
                : 'Explizites GO von Alina erteilt. Bitte wie konzeptioniert direkt umsetzen.';

            $followupBodyLines = [
                'Referenz-Ticket: ' . $targetId,
                'Entscheidung von ' . $actorLabel . ': ' . ($normalizedDecision === 'approved' ? 'FREIGABE / GO' : 'RÜCKFRAGE BEANTWORTET'),
                'Antwort / Vorgabe: ' . $effectiveAnswer,
            ];
            if ($origQuestion !== '') {
                $followupBodyLines[] = 'Ursprüngliche Rückfrage / Zusammenfassung: ' . mb_substr($origQuestion, 0, 1500);
            }

            $followupItem = $this->formatItemSchema([
                'id' => $followupId,
                'client_uuid' => null,
                'channel' => $origChannel,
                'title' => $followupTitle,
                'body' => mb_substr(implode("\n", $followupBodyLines), 0, 5000),
                'checklist' => [],
                'execution_mode' => 'direct',
                'status' => 'pending',
                'source' => 'falcone_go_resolver',
                'has_attachment' => false,
                'attachment_name' => null,
                'attachment_mime' => null,
                'attachment_rel_path' => null,
                'ag_response_summary' => null,
                'pending_question' => null,
                'awaiting_go_at' => null,
                'created_at' => $nowIso,
                'delivered_at' => null,
                'completed_at' => null,
            ]);

            $items[] = $followupItem;

            return [
                [
                    'decision' => $normalizedDecision,
                    'resolved_item' => $resolvedFormatted,
                    'followup_item' => $followupItem,
                ],
                $items,
                true,
            ];
        });
    }

    /**
     * Schließt passende wartende awaiting_go-Tickets automatisch auf completed, sobald Alina manuell
     * per Button ("Go erteilen") oder Nachricht ("Go für ...", "Go für alle offenen Konzepte") antwortet.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $newItem
     * @return array<int, string>
     */
    private function autoCloseMatchingAwaitingItemsUnlocked(array &$items, array $newItem): array
    {
        $title = trim((string) ($newItem['title'] ?? ''));
        $body = trim((string) ($newItem['body'] ?? ''));
        $combined = mb_strtolower($title . ' ' . $body);
        $channel = (string) ($newItem['channel'] ?? 'leitung');
        $newId = (string) ($newItem['id'] ?? '');
        $nowIso = now('Europe/Berlin')->toIso8601String();

        $isBulkGo = (bool) (
            preg_match('/\bgo\b.*?\balle\b.*?(?:konzept|offen|punkt|stau|freigab)/iu', $combined)
            || preg_match('/\balle\s+offenen\s+(?:konzepte|freigaben|punkte)\b/iu', $combined)
        );

        $referencedIds = [];
        if (preg_match_all('/\bag_[0-9]{8}_[0-9]{6}_[a-f0-9]{8}\b/i', $title . ' ' . $body, $idMatches)) {
            $referencedIds = array_values(array_unique($idMatches[0]));
        }

        $isExplicitGoIntent = $isBulkGo
            || !empty($referencedIds)
            || (bool) preg_match('/^(?:⚡\s*)?(?:go\b|freigabe\b|go\s+f(?:ü|ue)r\b|go\s*:|go\s*&|antwort\s+zu\b)/iu', $title)
            || (bool) preg_match('/\b(?:go\s+erteilt|mein\s+go|hiermit\s+go|ich\s+gebe\s+mein\s+go)\b/iu', $combined);

        if (!$isExplicitGoIntent) {
            return [];
        }

        $subjectHint = '';
        if (preg_match('/^(?:⚡\s*)?(?:go\s*&\s*antwort\s+zu|go\s+f(?:ü|ue)r|freigabe\s+f(?:ü|ue)r|antwort\s+zu|go)\s*:?\s*(.+)$/iu', $title, $m)) {
            $subjectHint = trim(mb_strtolower($m[1]));
            if (preg_match('/\balle\b.*?(?:konzept|offen)/iu', $subjectHint)) {
                $subjectHint = '';
            }
        }

        $awaitingIndicesInChannel = [];
        foreach ($items as $idx => $existing) {
            if (($existing['status'] ?? '') === 'awaiting_go' && ($existing['channel'] ?? '') === $channel) {
                $awaitingIndicesInChannel[] = $idx;
            }
        }

        $closedIds = [];
        foreach ($items as $idx => &$existing) {
            if (($existing['status'] ?? '') !== 'awaiting_go') {
                continue;
            }

            $existingId = (string) ($existing['id'] ?? '');
            $existingTitleLower = mb_strtolower(trim((string) ($existing['title'] ?? '')));
            $sameChannel = ($existing['channel'] ?? '') === $channel;

            $matches = false;
            if ($isBulkGo) {
                $matches = true;
            } elseif (in_array($existingId, $referencedIds, true)) {
                $matches = true;
            } elseif ($subjectHint !== '' && mb_strlen($subjectHint) >= 4 && $existingTitleLower !== '') {
                $matches = str_contains($existingTitleLower, $subjectHint) || str_contains($subjectHint, $existingTitleLower);
            } elseif ($subjectHint === '' && $sameChannel && count($awaitingIndicesInChannel) === 1) {
                $matches = true;
            }

            if ($matches) {
                $existing['status'] = 'completed';
                $existing['completed_at'] = $nowIso;
                $existing['pending_question'] = null;
                $prevSummary = isset($existing['ag_response_summary']) && trim((string) $existing['ag_response_summary']) !== ''
                    ? trim((string) $existing['ag_response_summary']) . "\n"
                    : '';
                $existing['ag_response_summary'] = mb_substr(
                    $prevSummary . '[Freigabe-Stau automatisch aufgelöst durch Folgeauftrag ' . $newId . ']',
                    0,
                    5000
                );
                $existing = $this->formatItemSchema($existing);
                $closedIds[] = $existingId;
            }
        }
        unset($existing);

        return $closedIds;
    }

    /**
     * Importiert/merged ein Array von Items (z. B. von Stage in die lokale Inbox) idempotent anhand der ID.
     *
     * @param  array<int, mixed>  $incomingItems
     */
    public function importItems(array $incomingItems): int
    {
        if ($incomingItems === []) {
            return 0;
        }

        return $this->withExclusiveLock(function (array $items) use ($incomingItems): array {
            $indexById = [];
            foreach ($items as $idx => $existing) {
                $eid = (string) ($existing['id'] ?? '');
                if ($eid !== '') {
                    $indexById[$eid] = $idx;
                }
            }

            $merged = 0;
            $dirty = false;

            foreach ($incomingItems as $raw) {
                if (!is_array($raw)) {
                    continue;
                }

                $id = trim((string) ($raw['id'] ?? ''));
                if (!self::isValidItemId($id)) {
                    continue;
                }

                $title = trim((string) ($raw['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                if (isset($indexById[$id])) {
                    $existingIdx = $indexById[$id];
                    $existingItem = $items[$existingIdx];
                    $itemChanged = false;

                    $existingRank = $this->statusRank((string) ($existingItem['status'] ?? 'pending'));
                    $incomingStatus = in_array($raw['status'] ?? '', self::ALLOWED_STATUSES, true)
                        ? (string) $raw['status']
                        : 'pending';
                    $incomingRank = $this->statusRank($incomingStatus);

                    if ($incomingRank > $existingRank) {
                        $existingItem['status'] = $incomingStatus;
                        if (!empty($raw['delivered_at']) && is_string($raw['delivered_at'])) {
                            $existingItem['delivered_at'] = $raw['delivered_at'];
                        }
                        if (!empty($raw['awaiting_go_at']) && is_string($raw['awaiting_go_at'])) {
                            $existingItem['awaiting_go_at'] = $raw['awaiting_go_at'];
                        }
                        if (!empty($raw['completed_at']) && is_string($raw['completed_at'])) {
                            $existingItem['completed_at'] = $raw['completed_at'];
                        }
                        if (isset($raw['ag_response_summary']) && is_string($raw['ag_response_summary'])) {
                            $existingItem['ag_response_summary'] = mb_substr(trim($raw['ag_response_summary']), 0, 5000);
                        }
                        if (array_key_exists('pending_question', $raw)) {
                            $existingItem['pending_question'] = is_string($raw['pending_question']) && trim($raw['pending_question']) !== ''
                                ? mb_substr(trim($raw['pending_question']), 0, 5000)
                                : null;
                        }
                        $itemChanged = true;
                    }

                    $localMediaMissing = !empty($existingItem['has_attachment'])
                        && (
                            empty($existingItem['attachment_rel_path'])
                            || $this->resolveVerifiedMediaFilePath((string) $existingItem['attachment_rel_path']) === null
                        );

                    if ($localMediaMissing && !empty($raw['attachment_base64']) && is_string($raw['attachment_base64'])) {
                        $validated = $this->validateBase64Attachment(
                            $raw['attachment_base64'],
                            isset($raw['attachment_name']) ? (string) $raw['attachment_name'] : null,
                            isset($raw['attachment_mime']) ? (string) $raw['attachment_mime'] : null
                        );
                        $stored = $this->writeMediaAttachment($id, 0, $validated);
                        $existingItem['has_attachment'] = true;
                        $existingItem['attachment_name'] = $stored['name'];
                        $existingItem['attachment_mime'] = $stored['mime'];
                        $existingItem['attachment_rel_path'] = $stored['rel_path'];
                        $itemChanged = true;
                    }

                    if ($itemChanged) {
                        $items[$existingIdx] = $this->formatItemSchema($existingItem);
                        $merged++;
                        $dirty = true;
                    }

                    continue;
                }

                $checklist = [];
                try {
                    $checklist = $this->normalizeChecklist($raw['checklist'] ?? []);
                } catch (\Throwable) {
                    $checklist = [];
                }

                $mode = in_array($raw['execution_mode'] ?? '', self::ALLOWED_EXECUTION_MODES, true)
                    ? (string) $raw['execution_mode']
                    : 'concept';

                $status = in_array($raw['status'] ?? '', self::ALLOWED_STATUSES, true)
                    ? (string) $raw['status']
                    : 'pending';

                $hasAttachment = false;
                $attachmentName = null;
                $attachmentMime = null;
                $attachmentRelPath = null;

                if (!empty($raw['attachment_base64']) && is_string($raw['attachment_base64'])) {
                    $validated = $this->validateBase64Attachment(
                        $raw['attachment_base64'],
                        isset($raw['attachment_name']) ? (string) $raw['attachment_name'] : null,
                        isset($raw['attachment_mime']) ? (string) $raw['attachment_mime'] : null
                    );
                    $stored = $this->writeMediaAttachment($id, 0, $validated);
                    $hasAttachment = true;
                    $attachmentName = $stored['name'];
                    $attachmentMime = $stored['mime'];
                    $attachmentRelPath = $stored['rel_path'];
                }

                $clientUuid = isset($raw['client_uuid']) && trim((string) $raw['client_uuid']) !== ''
                    ? mb_substr(trim((string) $raw['client_uuid']), 0, 64)
                    : null;

                $sourceRaw = isset($raw['source']) ? trim((string) $raw['source']) : '';
                $source = ($sourceRaw !== '' && preg_match('/^[A-Za-z0-9_-]{2,40}$/', $sourceRaw))
                    ? $sourceRaw
                    : 'android_app';

                $createdAt = isset($raw['created_at']) && is_string($raw['created_at']) && trim($raw['created_at']) !== ''
                    ? trim($raw['created_at'])
                    : now('Europe/Berlin')->toIso8601String();

                $newItem = $this->formatItemSchema([
                    'id' => $id,
                    'client_uuid' => $clientUuid,
                    'channel' => $this->normalizeChannel($raw['channel'] ?? 'leitung'),
                    'title' => mb_substr($title, 0, 500),
                    'body' => isset($raw['body']) && $raw['body'] !== null
                        ? mb_substr(trim((string) $raw['body']), 0, 5000)
                        : '',
                    'checklist' => $checklist,
                    'execution_mode' => $mode,
                    'status' => $status,
                    'source' => $source,
                    'has_attachment' => $hasAttachment,
                    'attachment_name' => $attachmentName,
                    'attachment_mime' => $attachmentMime,
                    'attachment_rel_path' => $attachmentRelPath,
                    'ag_response_summary' => isset($raw['ag_response_summary']) && is_string($raw['ag_response_summary']) && trim($raw['ag_response_summary']) !== ''
                        ? mb_substr(trim($raw['ag_response_summary']), 0, 5000)
                        : null,
                    'pending_question' => isset($raw['pending_question']) && is_string($raw['pending_question']) && trim($raw['pending_question']) !== ''
                        ? mb_substr(trim($raw['pending_question']), 0, 5000)
                        : null,
                    'awaiting_go_at' => isset($raw['awaiting_go_at']) && is_string($raw['awaiting_go_at']) && trim($raw['awaiting_go_at']) !== ''
                        ? trim($raw['awaiting_go_at'])
                        : null,
                    'created_at' => $createdAt,
                    'delivered_at' => isset($raw['delivered_at']) && is_string($raw['delivered_at']) && trim($raw['delivered_at']) !== ''
                        ? trim($raw['delivered_at'])
                        : null,
                    'completed_at' => isset($raw['completed_at']) && is_string($raw['completed_at']) && trim($raw['completed_at']) !== ''
                        ? trim($raw['completed_at'])
                        : null,
                ]);

                $items[] = $newItem;
                $indexById[$id] = count($items) - 1;
                $merged++;
                $dirty = true;
            }

            return [$merged, $items, $dirty];
        });
    }

    /**
     * @param  array{bytes: string, mime: string, ext: string, name: string}  $validatedAttachment
     * @return array{rel_path: string, mime: string, name: string}
     */
    private function writeMediaAttachment(string $id, int $idx, array $validatedAttachment): array
    {
        if (!self::isValidItemId($id)) {
            throw new RuntimeException('Ungültige Nachrichten-ID für Medienablage.');
        }

        $ext = $validatedAttachment['ext'];
        if (!in_array($ext, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
            throw new RuntimeException('Ungültige Dateiendung für Medienablage.');
        }

        $fileName = $id . '_' . max(0, $idx) . '.' . $ext;
        $mediaDir = $this->mediaDirectoryPath();
        $absPath = $mediaDir . DIRECTORY_SEPARATOR . $fileName;

        $tmpPath = $absPath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $written = @file_put_contents($tmpPath, $validatedAttachment['bytes'], LOCK_EX);
        if ($written === false || $written !== strlen($validatedAttachment['bytes'])) {
            @unlink($tmpPath);
            throw new RuntimeException('Privater Bildanhang konnte nicht gespeichert werden.');
        }

        @chmod($tmpPath, 0600);
        if (!@rename($tmpPath, $absPath)) {
            @unlink($tmpPath);
            throw new RuntimeException('Privater Bildanhang konnte nicht finalisiert werden.');
        }
        @chmod($absPath, 0600);

        return [
            'rel_path' => self::MEDIA_DIR_NAME . '/' . $fileName,
            'mime' => $validatedAttachment['mime'],
            'name' => $validatedAttachment['name'],
        ];
    }

    private function resolveVerifiedMediaFilePath(string $relPath): ?string
    {
        $normalized = str_replace('\\', '/', trim($relPath));
        if (!preg_match('/^ag_direct_media\/[A-Za-z0-9_-]+\.(png|jpg|jpeg|webp)$/', $normalized)) {
            return null;
        }

        $candidate = $this->privateRootPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (is_link($candidate) || !is_file($candidate) || !is_readable($candidate)) {
            return null;
        }

        $realCandidate = realpath($candidate);
        $realMediaDir = realpath($this->mediaDirectoryPath());
        if ($realCandidate === false || $realMediaDir === false) {
            return null;
        }

        $prefix = rtrim($realMediaDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($realCandidate, $prefix)) {
            return null;
        }

        return $realCandidate;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function deleteItemMediaFile(array $item): void
    {
        $relPath = isset($item['attachment_rel_path']) ? (string) $item['attachment_rel_path'] : '';
        if ($relPath === '') {
            return;
        }

        $absPath = $this->resolveVerifiedMediaFilePath($relPath);
        if ($absPath !== null && is_file($absPath) && !is_link($absPath)) {
            @unlink($absPath);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function enrichItemForBridge(array $item, bool $includeAttachmentBase64): array
    {
        $formatted = $this->formatItemSchema($item);

        if ($includeAttachmentBase64 && $formatted['has_attachment'] === true && is_string($formatted['attachment_rel_path'])) {
            $absPath = $this->resolveVerifiedMediaFilePath($formatted['attachment_rel_path']);
            if ($absPath !== null) {
                $rawBytes = @file_get_contents($absPath);
                if (is_string($rawBytes) && $rawBytes !== '') {
                    $formatted['attachment_base64'] = base64_encode($rawBytes);
                }
            }
        }

        return $formatted;
    }

    /**
     * Exaktes 15-Felder JSON-Schema für Android & FunkenwerkMcp.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function formatItemSchema(array $raw): array
    {
        $checklist = [];
        if (isset($raw['checklist']) && is_array($raw['checklist'])) {
            foreach ($raw['checklist'] as $entry) {
                if (is_scalar($entry) && trim((string) $entry) !== '') {
                    $checklist[] = trim((string) $entry);
                }
            }
        }

        $mode = in_array($raw['execution_mode'] ?? '', self::ALLOWED_EXECUTION_MODES, true)
            ? (string) $raw['execution_mode']
            : 'concept';

        $status = in_array($raw['status'] ?? '', self::ALLOWED_STATUSES, true)
            ? (string) $raw['status']
            : 'pending';

        $channel = $this->normalizeChannel($raw['channel'] ?? 'leitung');

        return [
            'id' => (string) ($raw['id'] ?? ''),
            'client_uuid' => isset($raw['client_uuid']) && trim((string) $raw['client_uuid']) !== ''
                ? trim((string) $raw['client_uuid'])
                : null,
            'channel' => $channel,
            'title' => (string) ($raw['title'] ?? ''),
            'body' => isset($raw['body']) && $raw['body'] !== null ? (string) $raw['body'] : '',
            'checklist' => array_values($checklist),
            'execution_mode' => $mode,
            'status' => $status,
            'source' => isset($raw['source']) && trim((string) $raw['source']) !== ''
                ? (string) $raw['source']
                : 'android_app',
            'has_attachment' => (bool) ($raw['has_attachment'] ?? false),
            'attachment_name' => isset($raw['attachment_name']) && $raw['attachment_name'] !== ''
                ? (string) $raw['attachment_name']
                : null,
            'attachment_mime' => isset($raw['attachment_mime']) && $raw['attachment_mime'] !== ''
                ? (string) $raw['attachment_mime']
                : null,
            'attachment_rel_path' => isset($raw['attachment_rel_path']) && $raw['attachment_rel_path'] !== ''
                ? (string) $raw['attachment_rel_path']
                : null,
            'ag_response_summary' => isset($raw['ag_response_summary']) && $raw['ag_response_summary'] !== ''
                ? (string) $raw['ag_response_summary']
                : null,
            'pending_question' => isset($raw['pending_question']) && trim((string) $raw['pending_question']) !== ''
                ? trim((string) $raw['pending_question'])
                : null,
            'awaiting_go_at' => isset($raw['awaiting_go_at']) && trim((string) $raw['awaiting_go_at']) !== ''
                ? trim((string) $raw['awaiting_go_at'])
                : null,
            'created_at' => (string) ($raw['created_at'] ?? now('Europe/Berlin')->toIso8601String()),
            'delivered_at' => isset($raw['delivered_at']) && $raw['delivered_at'] !== ''
                ? (string) $raw['delivered_at']
                : null,
            'completed_at' => isset($raw['completed_at']) && $raw['completed_at'] !== ''
                ? (string) $raw['completed_at']
                : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{pending: int, delivered: int, awaiting_go: int, open: int, completed: int}
     */
    private function computeCounts(array $items): array
    {
        $counts = [
            'pending' => 0,
            'delivered' => 0,
            'awaiting_go' => 0,
            'open' => 0,
            'completed' => 0,
        ];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'pending');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        $counts['open'] = $counts['pending'] + $counts['delivered'] + $counts['awaiting_go'];

        return $counts;
    }

    /**
     * Rotation abgeschlossener Alt-Einträge (> 30 Tage) + striktes Limit auf MAX_ITEMS (200).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function pruneAndCapItems(array $items): array
    {
        $ordered = $this->sortOldestFirst($items);
        $cutoff = now('Europe/Berlin')->subDays(self::COMPLETED_RETENTION_DAYS);

        $kept = [];
        foreach ($ordered as $item) {
            if (($item['status'] ?? '') === 'completed') {
                $refTs = (string) ($item['completed_at'] ?: ($item['created_at'] ?? ''));
                if ($refTs !== '') {
                    try {
                        if (Carbon::parse($refTs)->lt($cutoff)) {
                            $this->deleteItemMediaFile($item);
                            continue;
                        }
                    } catch (\Throwable) {
                    }
                }
            }
            $kept[] = $this->formatItemSchema($item);
        }

        if (count($kept) <= self::MAX_ITEMS) {
            return array_values($kept);
        }

        foreach (['completed', 'delivered', 'pending'] as $evictStatus) {
            if (count($kept) <= self::MAX_ITEMS) {
                break;
            }

            foreach ($kept as $idx => $candidate) {
                if (count($kept) <= self::MAX_ITEMS) {
                    break;
                }

                if (($candidate['status'] ?? '') === $evictStatus) {
                    $this->deleteItemMediaFile($candidate);
                    unset($kept[$idx]);
                }
            }
        }

        return array_values($kept);
    }

    /**
     * Stabile chronologische Sortierung (älteste zuerst).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function sortOldestFirst(array $items): array
    {
        $indexed = [];
        foreach (array_values($items) as $pos => $item) {
            $ts = 0;
            if (!empty($item['created_at']) && is_string($item['created_at'])) {
                $parsed = strtotime($item['created_at']);
                $ts = $parsed !== false ? $parsed : 0;
            }
            $indexed[] = [
                'pos' => $pos,
                'ts' => $ts,
                'item' => $item,
            ];
        }

        usort($indexed, static function (array $a, array $b): int {
            if ($a['ts'] !== $b['ts']) {
                return $a['ts'] <=> $b['ts'];
            }

            return $a['pos'] <=> $b['pos'];
        });

        return array_map(static fn (array $row): array => $row['item'], $indexed);
    }

    /**
     * Stabile umgekehrt-chronologische Sortierung (neueste zuerst).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function sortNewestFirst(array $items): array
    {
        return array_reverse($this->sortOldestFirst($items));
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'completed' => 3,
            'awaiting_go' => 2,
            'delivered' => 1,
            default => 0,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $existingItems
     */
    private function generateUniqueId(array $existingItems): string
    {
        $existingIds = [];
        foreach ($existingItems as $item) {
            if (!empty($item['id'])) {
                $existingIds[(string) $item['id']] = true;
            }
        }

        do {
            $candidate = 'ag_' . now('Europe/Berlin')->format('Ymd_His') . '_' . bin2hex(random_bytes(4));
        } while (isset($existingIds[$candidate]));

        return $candidate;
    }

    private function withSharedLock(callable $callback): mixed
    {
        $lockFp = @fopen($this->lockFilePath(), 'c+');
        if (!is_resource($lockFp)) {
            throw new RuntimeException('Sperrdatei für AG-Direct-Inbox konnte nicht geöffnet werden.');
        }
        @chmod($this->lockFilePath(), 0600);

        if (!@flock($lockFp, LOCK_SH)) {
            @fclose($lockFp);
            throw new RuntimeException('Lesesperre für AG-Direct-Inbox konnte nicht erworben werden.');
        }

        try {
            $items = $this->readItemsUnlocked();

            return $callback($items);
        } finally {
            @flock($lockFp, LOCK_UN);
            @fclose($lockFp);
        }
    }

    private function withExclusiveLock(callable $callback): mixed
    {
        $lockFp = @fopen($this->lockFilePath(), 'c+');
        if (!is_resource($lockFp)) {
            throw new RuntimeException('Sperrdatei für AG-Direct-Inbox konnte nicht geöffnet werden.');
        }
        @chmod($this->lockFilePath(), 0600);

        if (!@flock($lockFp, LOCK_EX)) {
            @fclose($lockFp);
            throw new RuntimeException('Schreibsperre für AG-Direct-Inbox konnte nicht erworben werden.');
        }

        try {
            $items = $this->readItemsUnlocked();
            [$result, $updatedItems, $dirty] = $callback($items);

            if ($dirty) {
                $pruned = $this->pruneAndCapItems($updatedItems);
                $this->writeItemsUnlocked($pruned);
            }

            return $result;
        } finally {
            @flock($lockFp, LOCK_UN);
            @fclose($lockFp);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readItemsUnlocked(): array
    {
        $inboxFile = $this->inboxFilePath();
        if (is_link($inboxFile) || !is_file($inboxFile)) {
            return [];
        }

        $raw = @file_get_contents($inboxFile);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $list = isset($decoded['items']) && is_array($decoded['items'])
            ? $decoded['items']
            : (array_is_list($decoded) ? $decoded : []);

        $valid = [];
        foreach ($list as $entry) {
            if (is_array($entry) && isset($entry['id']) && self::isValidItemId((string) $entry['id'])) {
                $valid[] = $this->formatItemSchema($entry);
            }
        }

        return $valid;
    }

    /**
     * Atomares Schreiben via Temp-Datei + LOCK_EX + Rename.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function writeItemsUnlocked(array $items): void
    {
        $inboxFile = $this->inboxFilePath();
        $payload = json_encode(
            array_values($items),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        if (!is_string($payload)) {
            throw new RuntimeException('JSON-Serialisierung der AG-Direct-Inbox fehlgeschlagen.');
        }

        $tmpFile = $inboxFile . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $written = @file_put_contents($tmpFile, $payload, LOCK_EX);
        if ($written === false || $written !== strlen($payload)) {
            @unlink($tmpFile);
            throw new RuntimeException('Temporäre AG-Direct-Inbox-Datei konnte nicht geschrieben werden.');
        }

        @chmod($tmpFile, 0600);
        if (!@rename($tmpFile, $inboxFile)) {
            @unlink($tmpFile);
            throw new RuntimeException('AG-Direct-Inbox konnte nicht atomar aktualisiert werden.');
        }
        @chmod($inboxFile, 0600);
    }
}
