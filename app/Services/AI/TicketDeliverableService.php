<?php

namespace App\Services\AI;

use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Models\System\SystemLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketDeliverableService
{
    public const DISK = 'workspace';
    public const ACTIVE_DIR = 'agenten/workspace/tickets';
    public const ARCHIVE_DIR = 'agenten/workspace/tickets/archiv';

    /**
     * Nach 4 Wochen (28 Tagen) wandern aktive Ticket-Dateien vollautomatisch ins komprimierte Archiv.
     */
    public const ARCHIVE_AFTER_DAYS = 28;

    /**
     * Nach 180 Tagen werden nicht angepinnte Archiv-Dateien (Arbeitsmüll / Zwischenstände) physisch gelöscht.
     */
    public const PURGE_ARCHIVED_AFTER_DAYS = 180;

    public const THREE_D_EXTENSIONS = ['stl', '3mf', 'glb', 'gltf', 'obj', 'ply'];
    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    public const CONCEPT_EXTENSIONS = ['md', 'txt'];
    public const DOCUMENT_EXTENSIONS = ['pdf', 'docx', 'csv', 'xlsx', 'ics', 'xcs', 'json', 'zip'];

    public const ALLOWED_EXTENSIONS = [
        'stl', '3mf', 'glb', 'gltf', 'obj', 'ply',
        'png', 'jpg', 'jpeg', 'webp', 'gif',
        'md', 'txt',
        'pdf', 'docx', 'csv', 'xlsx', 'ics', 'xcs', 'json', 'zip',
    ];

    /**
     * Request-lokale Warteschlange: Sobald ein Agent im Tool-Loop eine Datei (PDF, DOCX, Konzept, 3D-Modell, ICS)
     * erzeugt, wird sie hier vorgemerkt und beim Speichern der Agenten-Nachricht im Ticket automatisch "Sticky" angeheftet.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private static array $pendingTicketAttachments = [];

    public static function queueAttachmentForTicket(?string $ticketId, array $attachmentMeta): void
    {
        $resolvedId = $ticketId ?: (app()->bound('active_universe_ticket_id') ? (string) app('active_universe_ticket_id') : '');
        if ($resolvedId === '') {
            return;
        }

        if (!isset(self::$pendingTicketAttachments[$resolvedId])) {
            self::$pendingTicketAttachments[$resolvedId] = [];
        }

        $relPath = (string) ($attachmentMeta['rel_path'] ?? '');
        foreach (self::$pendingTicketAttachments[$resolvedId] as $existing) {
            if ($relPath !== '' && ($existing['rel_path'] ?? '') === $relPath) {
                return;
            }
        }

        self::$pendingTicketAttachments[$resolvedId][] = $attachmentMeta;
    }

    /**
     * Holt und leert alle während des aktuellen Agenten-Turns erzeugten Sticky-Anhänge für dieses Ticket.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pullQueuedAttachments(string $ticketId): array
    {
        $items = self::$pendingTicketAttachments[$ticketId] ?? [];
        unset(self::$pendingTicketAttachments[$ticketId]);

        return array_values($items);
    }

    public static function clearAllQueued(): void
    {
        self::$pendingTicketAttachments = [];
    }

    /**
     * Klassifiziert eine Datei anhand ihrer Endung in '3d', 'concept', 'image' oder 'document'.
     */
    public static function detectCategory(string $ext, string $mime = ''): string
    {
        $ext = strtolower(ltrim($ext, '.'));
        if (in_array($ext, self::THREE_D_EXTENSIONS, true)) {
            return '3d';
        }
        if (in_array($ext, self::CONCEPT_EXTENSIONS, true)) {
            return 'concept';
        }
        if (in_array($ext, self::IMAGE_EXTENSIONS, true) || str_starts_with(strtolower($mime), 'image/')) {
            return 'image';
        }

        return 'document';
    }

    public static function resolveMimeForExtension(string $ext, ?string $fallback = null): string
    {
        return match (strtolower(ltrim($ext, '.'))) {
            'stl' => 'model/stl',
            '3mf' => 'model/3mf',
            'glb' => 'model/gltf-binary',
            'gltf' => 'model/gltf+json',
            'obj' => 'model/obj',
            'ply' => 'application/octet-stream',
            'md' => 'text/markdown',
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ics' => 'text/calendar',
            'xcs' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => $fallback ?: 'application/octet-stream',
        };
    }

    /**
     * Speichert Rohbytes oder kopiert eine bestehende Datei sicher in den zentralen Ticket-Speicher
     * (`agenten/workspace/tickets/...`) und erzeugt standardisierte Sticky-Metadaten inkl. Funkenwerk-3D-URI.
     *
     * @param array<string, mixed> $extraMeta
     * @return array<string, mixed>|null
     */
    public static function storeBytesForTicket(
        string $rawBytes,
        string $originalFilename,
        ?string $ticketNumber = null,
        array $extraMeta = []
    ): ?array {
        if ($rawBytes === '') {
            return null;
        }

        // Harte Sicherheits-Sperre gegen ausführbaren Code
        if (stripos($rawBytes, '<?php') !== false || stripos($rawBytes, '<?=') !== false) {
            return null;
        }

        $cleanOrig = basename(str_replace('\\', '/', trim($originalFilename)));
        $ext = strtolower((string) pathinfo($cleanOrig, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        $stem = Str::slug((string) pathinfo($cleanOrig, PATHINFO_FILENAME));
        if ($stem === '') {
            $stem = 'ticket-datei';
        }
        $prefix = $ticketNumber ? (strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', $ticketNumber)) . '_') : '';
        $safeFilename = now()->format('Y-m-d_His') . '_' . $prefix . Str::limit($stem, 60, '') . '_' . Str::lower(Str::random(4)) . '.' . $ext;
        $relPath = self::ACTIVE_DIR . '/' . $safeFilename;

        $disk = Storage::disk(self::DISK);
        if (!$disk->exists(self::ACTIVE_DIR)) {
            $disk->makeDirectory(self::ACTIVE_DIR);
        }

        $disk->put($relPath, $rawBytes);

        return self::buildAttachmentMetadata($relPath, $cleanOrig, strlen($rawBytes), $extraMeta);
    }

    /**
     * Übernimmt eine bereits erzeugte lokale Datei (z. B. aus DomPDF, DOCX-Generator oder Upload)
     * in den zentralen Ticket-Speicher und reiht sie optional direkt für das aktive Ticket ein.
     *
     * @param array<string, mixed> $extraMeta
     * @return array<string, mixed>|null
     */
    public static function registerLocalFileForActiveTicket(
        string $absoluteSourcePath,
        ?string $displayName = null,
        array $extraMeta = [],
        ?string $explicitTicketId = null
    ): ?array {
        if (!is_file($absoluteSourcePath) || is_link($absoluteSourcePath)) {
            return null;
        }

        $bytes = @file_get_contents($absoluteSourcePath);
        if (!is_string($bytes) || $bytes === '') {
            return null;
        }

        $ticketId = $explicitTicketId ?: (app()->bound('active_universe_ticket_id') ? (string) app('active_universe_ticket_id') : null);
        $ticketNumber = null;
        if ($ticketId && Schema::hasTable('ai_universe_tickets')) {
            $ticketNumber = AiUniverseTicket::where('id', $ticketId)->value('ticket_number');
        }

        $meta = self::storeBytesForTicket(
            $bytes,
            $displayName ?: basename($absoluteSourcePath),
            $ticketNumber,
            $extraMeta
        );

        if ($meta !== null && $ticketId) {
            self::queueAttachmentForTicket($ticketId, $meta);
        }

        return $meta;
    }

    /**
     * Erzeugt das vollständige, rückwärtskompatible Metadaten-Array für `AiUniverseTicketMessage::$attachments`.
     *
     * @param array<string, mixed> $extraMeta
     * @return array<string, mixed>
     */
    public static function buildAttachmentMetadata(
        string $relPath,
        string $displayName,
        int $sizeBytes,
        array $extraMeta = []
    ): array {
        $ext = strtolower((string) pathinfo($relPath, PATHINFO_EXTENSION));
        $mime = self::resolveMimeForExtension($ext, $extraMeta['mime'] ?? null);
        $category = self::detectCategory($ext, $mime);

        $url = route('admin.ai.workspace.file', ['path' => $relPath], false);
        $winUncPath = self::resolveWindowsUncPath($relPath);

        $meta = [
            'id' => (string) ($extraMeta['id'] ?? ('att_' . Str::lower(Str::random(10)))),
            'name' => $displayName,
            'ext' => $ext,
            'mime' => $mime,
            'category' => $category, // '3d' | 'concept' | 'image' | 'document'
            'is_image' => $category === 'image' ? '1' : '0',
            'is_3d' => $category === '3d' ? '1' : '0',
            'is_concept' => $category === 'concept' ? '1' : '0',
            'size_bytes' => $sizeBytes,
            'size_human' => self::formatBytes($sizeBytes),
            'rel_path' => $relPath,
            'url' => $url,
            'funkenwerk_3d_uri' => ($category === '3d' && $winUncPath !== null)
                ? ('funkenwerk3d://open?path=' . rawurlencode($winUncPath))
                : null,
            'pinned' => !empty($extraMeta['pinned']),
            'archived' => !empty($extraMeta['archived']),
            'archived_at' => $extraMeta['archived_at'] ?? null,
            'purged' => !empty($extraMeta['purged']),
            'created_at' => $extraMeta['created_at'] ?? now()->toIso8601String(),
        ];

        if (!empty($extraMeta['dimensions_mm']) && is_array($extraMeta['dimensions_mm'])) {
            $meta['dimensions_mm'] = $extraMeta['dimensions_mm'];
        }
        if (!empty($extraMeta['filament_grams'])) {
            $meta['filament_grams'] = (int) $extraMeta['filament_grams'];
        }
        if (!empty($extraMeta['printer_profile'])) {
            $meta['printer_profile'] = (string) $extraMeta['printer_profile'];
        }
        if ($category === 'concept') {
            $cStatus = in_array(($extraMeta['concept_status'] ?? ''), ['open', 'completed_verified'], true)
                ? (string) $extraMeta['concept_status']
                : 'open';
            $meta['concept_status'] = $cStatus;
            $meta['concept_title'] = isset($extraMeta['concept_title']) && trim((string) $extraMeta['concept_title']) !== ''
                ? trim((string) $extraMeta['concept_title'])
                : null;
            $meta['concept_slug'] = (string) ($extraMeta['concept_slug'] ?? Str::slug($meta['concept_title'] ?: pathinfo($displayName, PATHINFO_FILENAME)));
            $meta['concept_version'] = max(1, (int) ($extraMeta['concept_version'] ?? 1));
            $meta['verified_at'] = $cStatus === 'completed_verified'
                ? ($extraMeta['verified_at'] ?? now()->toIso8601String())
                : null;
            if ($cStatus === 'open' && !array_key_exists('pinned', $extraMeta)) {
                $meta['pinned'] = true;
            }
        }

        return $meta;
    }

    /**
     * Berechnet den sicheren Windows-UNC-Pfad für das lokale Funkenwerk-3D-Desktop-Modul auf Alinas Notebook.
     */
    public static function resolveWindowsUncPath(string $relPath): ?string
    {
        $cleanRel = trim(str_replace('\\', '/', $relPath), '/');
        if ($cleanRel === '' || str_contains($cleanRel, '..')) {
            return null;
        }

        $linuxAbs = '/home/ubuntuxina/meine-projekte/seelenfunke/storage/app/' . $cleanRel;

        return '\\\\wsl.localhost\\Ubuntu' . str_replace('/', '\\', $linuxAbs);
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }

        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }

    /**
     * Liest den Textinhalt eines Konzept-/Markdown-Anhangs sicher für das Inline-Konzept-Modal aus.
     */
    public static function readConceptText(string $relPath): ?string
    {
        $clean = trim(str_replace('\\', '/', $relPath), '/');
        if ($clean === '' || str_contains($clean, '..') || !str_starts_with($clean, self::ACTIVE_DIR . '/')) {
            return null;
        }

        $disk = Storage::disk(self::DISK);
        if ($disk->exists($clean)) {
            $full = $disk->path($clean);
            if (is_file($full) && !is_link($full) && filesize($full) <= 2 * 1024 * 1024) {
                return (string) $disk->get($clean);
            }
        }

        // Falls bereits ins .gz-Archiv verschoben -> transparent entpacken!
        $gzPath = $clean . '.gz';
        if ($disk->exists($gzPath)) {
            $rawGz = $disk->get($gzPath);
            $decoded = @gzdecode((string) $rawGz);
            if (is_string($decoded)) {
                return mb_substr($decoded, 0, 500000);
            }
        }

        return null;
    }

    /**
     * Setzt oder löst den Schutz-Pin (`pinned`) einer Datei im Ticket, damit wichtige Druckvorlagen oder Belege
     * vom 4-Wochen-Autopiloten dauerhaft im Direktzugriff gehalten werden.
     */
    public static function togglePinOnAttachment(string $messageId, string $attachmentKey): bool
    {
        if (!Schema::hasTable('ai_universe_ticket_messages')) {
            return false;
        }

        $msg = AiUniverseTicketMessage::find($messageId);
        if (!$msg || !is_array($msg->attachments)) {
            return false;
        }

        $attachments = $msg->attachments;
        $changed = false;
        foreach ($attachments as &$att) {
            if (!is_array($att)) {
                continue;
            }
            $matches = (($att['id'] ?? '') !== '' && ($att['id'] ?? '') === $attachmentKey)
                || (($att['rel_path'] ?? '') !== '' && ($att['rel_path'] ?? '') === $attachmentKey)
                || (($att['url'] ?? '') !== '' && ($att['url'] ?? '') === $attachmentKey);

            if ($matches) {
                $att['pinned'] = empty($att['pinned']);
                $changed = true;
                break;
            }
        }
        unset($att);

        if ($changed) {
            $msg->update(['attachments' => $attachments]);
        }

        return $changed;
    }

    /**
     * Aktualisiert den Konzept-Status (`open` vs. `completed_verified`) eines Konzept-Anhangs.
     */
    public static function setConceptStatusOnAttachment(string $messageId, string $attachmentKey, string $status): bool
    {
        if (!Schema::hasTable('ai_universe_ticket_messages')) {
            return false;
        }
        $normStatus = $status === 'completed_verified' ? 'completed_verified' : 'open';

        $msg = AiUniverseTicketMessage::find($messageId);
        if (!$msg || !is_array($msg->attachments)) {
            return false;
        }

        $attachments = $msg->attachments;
        $changed = false;
        foreach ($attachments as &$att) {
            if (!is_array($att)) {
                continue;
            }
            $matches = (($att['id'] ?? '') !== '' && ($att['id'] ?? '') === $attachmentKey)
                || (($att['rel_path'] ?? '') !== '' && ($att['rel_path'] ?? '') === $attachmentKey)
                || (($att['url'] ?? '') !== '' && ($att['url'] ?? '') === $attachmentKey);

            if ($matches) {
                $att['concept_status'] = $normStatus;
                $att['verified_at'] = $normStatus === 'completed_verified' ? now()->toIso8601String() : null;
                if ($normStatus === 'open') {
                    $att['pinned'] = true;
                }
                $changed = true;
                break;
            }
        }
        unset($att);

        if ($changed) {
            $msg->update(['attachments' => $attachments]);
        }

        return $changed;
    }

    /**
     * Löscht eine hinterlegte Ticket-Datei bzw. ein Konzept per 1-Klick rückstandsfrei von der Platte und aus der Nachricht.
     */
    public static function deleteAttachment(string $messageId, string $attachmentKey): bool
    {
        if (!Schema::hasTable('ai_universe_ticket_messages')) {
            return false;
        }

        $msg = AiUniverseTicketMessage::find($messageId);
        if (!$msg || !is_array($msg->attachments)) {
            return false;
        }

        $disk = Storage::disk(self::DISK);
        $remaining = [];
        $deleted = false;

        foreach ($msg->attachments as $idx => $att) {
            if (!is_array($att)) {
                continue;
            }
            $matches = (($att['id'] ?? '') !== '' && ($att['id'] ?? '') === $attachmentKey)
                || (($att['rel_path'] ?? '') !== '' && ($att['rel_path'] ?? '') === $attachmentKey)
                || (($att['url'] ?? '') !== '' && ($att['url'] ?? '') === $attachmentKey)
                || ((string) $idx === $attachmentKey);

            if ($matches && !$deleted) {
                foreach (['rel_path', 'archive_rel_path'] as $pKey) {
                    $p = trim((string) ($att[$pKey] ?? ''));
                    if ($p !== '' && !str_contains($p, '..') && str_starts_with($p, self::ACTIVE_DIR . '/')) {
                        if ($disk->exists($p)) {
                            $disk->delete($p);
                        }
                        if ($disk->exists($p . '.gz')) {
                            $disk->delete($p . '.gz');
                        }
                    }
                }
                if (!empty($att['concept_slug']) && Schema::hasTable('ai_knowledge_bases')) {
                    \App\Models\Ai\AiKnowledgeBase::where('source_path', 'concept:' . $att['concept_slug'])->delete();
                }
                $deleted = true;
                continue;
            }

            $remaining[] = $att;
        }

        if ($deleted) {
            $msg->update(['attachments' => array_values($remaining)]);
        }

        return $deleted;
    }

    /**
     * Stellt eine archivierte Datei bei Bedarf per 1-Klick wieder in den aktiven Direktzugriff her.
     */
    public static function restoreArchivedAttachment(string $messageId, string $attachmentKey): bool
    {
        if (!Schema::hasTable('ai_universe_ticket_messages')) {
            return false;
        }

        $msg = AiUniverseTicketMessage::find($messageId);
        if (!$msg || !is_array($msg->attachments)) {
            return false;
        }

        $disk = Storage::disk(self::DISK);
        $attachments = $msg->attachments;
        $restored = false;

        foreach ($attachments as &$att) {
            if (!is_array($att)) {
                continue;
            }
            $matches = (($att['id'] ?? '') !== '' && ($att['id'] ?? '') === $attachmentKey)
                || (($att['rel_path'] ?? '') !== '' && ($att['rel_path'] ?? '') === $attachmentKey);

            if (!$matches || empty($att['archived']) || !empty($att['purged'])) {
                continue;
            }

            $archiveRel = (string) ($att['archive_rel_path'] ?? '');
            if ($archiveRel === '' || !$disk->exists($archiveRel)) {
                continue;
            }

            $raw = (string) $disk->get($archiveRel);
            if (str_ends_with($archiveRel, '.gz')) {
                $unpacked = @gzdecode($raw);
                if (is_string($unpacked)) {
                    $raw = $unpacked;
                }
            }

            $origName = basename(preg_replace('/\.gz$/i', '', $archiveRel) ?? $archiveRel);
            $activeRel = self::ACTIVE_DIR . '/' . $origName;
            $disk->put($activeRel, $raw);
            $disk->delete($archiveRel);

            $att['archived'] = false;
            $att['archived_at'] = null;
            $att['archive_rel_path'] = null;
            $att['rel_path'] = $activeRel;
            $att['url'] = route('admin.ai.workspace.file', ['path' => $activeRel], false);
            if (($att['category'] ?? '') === '3d') {
                $winUnc = self::resolveWindowsUncPath($activeRel);
                $att['funkenwerk_3d_uri'] = $winUnc ? ('funkenwerk3d://open?path=' . rawurlencode($winUnc)) : null;
            }
            $att['created_at'] = now()->toIso8601String();
            $restored = true;
            break;
        }
        unset($att);

        if ($restored) {
            $msg->update(['attachments' => $attachments]);
        }

        return $restored;
    }

    /**
     * Aggregiert alle im gesamten Ticketsystem hinterlegten Dateien, Konzepte, Bilder & 3D-Modelle
     * für die zentrale Datei-Ansicht (`/admin/tickets?view=files`) inkl. Speicher-Statistiken.
     *
     * @return array{items: array<int, array<string, mixed>>, stats: array<string, mixed>}
     */
    public static function collectAllTicketDeliverables(
        string $stateFilter = 'active', // 'active' | 'archived' | 'pinned' | 'all'
        string $typeFilter = 'all',     // 'all' | '3d' | 'concept' | 'document' | 'image'
        string $search = '',
        string $conceptStatusFilter = 'all' // 'all' | 'open' | 'completed_verified'
    ): array {
        $emptyStats = [
            'active_count' => 0,
            'archived_count' => 0,
            'pinned_count' => 0,
            'concept_open_count' => 0,
            'concept_verified_count' => 0,
            'active_bytes' => 0,
            'archived_bytes' => 0,
            'active_human' => '0 B',
            'archived_human' => '0 B',
        ];

        if (!Schema::hasTable('ai_universe_ticket_messages') || !Schema::hasTable('ai_universe_tickets')) {
            return ['items' => [], 'stats' => $emptyStats];
        }

        $messages = AiUniverseTicketMessage::with(['ticket.department'])
            ->whereNotNull('attachments')
            ->orderByDesc('created_at')
            ->limit(400)
            ->get();

        $disk = Storage::disk(self::DISK);
        $items = [];
        $activeCount = 0;
        $archivedCount = 0;
        $pinnedCount = 0;
        $conceptOpenCount = 0;
        $conceptVerifiedCount = 0;
        $activeBytes = 0;
        $archivedBytes = 0;

        $isAdminViewer = \App\Models\Ai\AiUniverseTicket::isStrictAdminActor();
        $searchLower = mb_strtolower(trim($search));

        foreach ($messages as $msg) {
            if (!is_array($msg->attachments) || empty($msg->attachments)) {
                continue;
            }
            $ticket = $msg->ticket;
            if (!$ticket) {
                continue;
            }
            if (!$isAdminViewer && !((bool) ($ticket->is_business ?? true))) {
                continue;
            }

            foreach ($msg->attachments as $idx => $att) {
                if (!is_array($att) || !empty($att['purged'])) {
                    continue;
                }

                $name = (string) ($att['name'] ?? ('Anhang ' . ($idx + 1)));
                $ext = strtolower((string) ($att['ext'] ?? pathinfo($name, PATHINFO_EXTENSION)));
                $mime = (string) ($att['mime'] ?? self::resolveMimeForExtension($ext));
                $category = (string) ($att['category'] ?? self::detectCategory($ext, $mime));
                $isArchived = !empty($att['archived']);
                $isPinned = !empty($att['pinned']);

                $relPath = (string) ($att['rel_path'] ?? '');
                $rawAttUrl = (string) ($att['url'] ?? '');
                $sizeBytes = max(0, (int) ($att['size_bytes'] ?? 0));
                if (preg_match('#^/api/(?:funkenwerk/ag-inbox|system/ag-direct-inbox)/([A-Za-z0-9_-]+)/attachment$#', $rawAttUrl, $agUrlMatch)) {
                    $agItemId = $agUrlMatch[1];
                    $rawAttUrl = "/admin/ai/ag-attachment/{$agItemId}";
                    if ($sizeBytes <= 0) {
                        try {
                            $resolvedAg = app(\App\Services\System\AntigravityDirectInboxService::class)->resolveAttachmentForStreaming($agItemId);
                            if ($resolvedAg !== null && !empty($resolvedAg['path']) && is_file($resolvedAg['path'])) {
                                $sizeBytes = (int) filesize($resolvedAg['path']);
                            }
                        } catch (\Throwable) {
                        }
                    }
                } elseif (preg_match('#^/admin/ai/ag-attachment/([A-Za-z0-9_-]+)$#', $rawAttUrl, $agAdminMatch) && $sizeBytes <= 0) {
                    try {
                        $resolvedAg = app(\App\Services\System\AntigravityDirectInboxService::class)->resolveAttachmentForStreaming($agAdminMatch[1]);
                        if ($resolvedAg !== null && !empty($resolvedAg['path']) && is_file($resolvedAg['path'])) {
                            $sizeBytes = (int) filesize($resolvedAg['path']);
                            $att['size_bytes'] = $sizeBytes;
                        }
                    } catch (\Throwable) {
                    }
                }
                if ($sizeBytes <= 0 && $relPath !== '' && $disk->exists($relPath)) {
                    $sizeBytes = (int) $disk->size($relPath);
                }

                $conceptStatus = $category === 'concept'
                    ? (in_array(($att['concept_status'] ?? ''), ['open', 'completed_verified'], true) ? (string) $att['concept_status'] : 'open')
                    : null;

                if ($category === 'concept') {
                    if ($conceptStatus === 'completed_verified') {
                        $conceptVerifiedCount++;
                    } else {
                        $conceptOpenCount++;
                    }
                }

                if ($isArchived) {
                    $archivedCount++;
                    $archivedBytes += max(0, (int) ($att['archived_size_bytes'] ?? $sizeBytes));
                } else {
                    $activeCount++;
                    $activeBytes += max(0, $sizeBytes);
                }

                if ($isPinned) {
                    $pinnedCount++;
                }

                if ($stateFilter === 'active' && $isArchived) {
                    continue;
                }
                if ($stateFilter === 'archived' && !$isArchived) {
                    continue;
                }
                if ($stateFilter === 'pinned' && !$isPinned) {
                    continue;
                }
                if ($typeFilter !== 'all' && $category !== $typeFilter) {
                    continue;
                }
                if ($category === 'concept' && $conceptStatusFilter !== 'all' && $conceptStatus !== $conceptStatusFilter) {
                    continue;
                }

                if ($searchLower !== '') {
                    $haystack = mb_strtolower($name . ' ' . ($att['concept_title'] ?? '') . ' ' . $ticket->ticket_number . ' ' . $ticket->title . ' ' . $msg->sender_name);
                    if (!str_contains($haystack, $searchLower)) {
                        continue;
                    }
                }

                $createdAt = !empty($att['created_at']) ? Carbon::parse((string) $att['created_at']) : ($msg->created_at ?? now());
                $isOpenConceptProtected = ($category === 'concept' && $conceptStatus === 'open');
                $daysUntilArchive = $isPinned || $isArchived || $isOpenConceptProtected
                    ? null
                    : max(0, self::ARCHIVE_AFTER_DAYS - (int) $createdAt->diffInDays(now()));

                $winUnc = ($category === '3d' && $relPath !== '' && !$isArchived)
                    ? self::resolveWindowsUncPath($relPath)
                    : null;

                $items[] = [
                    'key' => (string) ($att['id'] ?? ($relPath !== '' ? $relPath : ($att['url'] ?? $idx))),
                    'message_id' => (string) $msg->id,
                    'ticket_id' => (string) $ticket->id,
                    'ticket_number' => (string) $ticket->ticket_number,
                    'ticket_title' => (string) $ticket->title,
                    'department_name' => (string) ($ticket->department?->name ?? 'Leitung'),
                    'sender_name' => (string) $msg->sender_name,
                    'name' => $name,
                    'concept_title' => $att['concept_title'] ?? null,
                    'concept_slug' => $att['concept_slug'] ?? null,
                    'concept_status' => $conceptStatus,
                    'concept_version' => $att['concept_version'] ?? 1,
                    'verified_at' => $att['verified_at'] ?? null,
                    'ext' => $ext,
                    'mime' => $mime,
                    'category' => $category,
                    'size_human' => self::formatBytes($isArchived ? (int) ($att['archived_size_bytes'] ?? $sizeBytes) : $sizeBytes),
                    'rel_path' => $relPath,
                    'url' => $rawAttUrl !== '' ? $rawAttUrl : ($relPath !== '' ? route('admin.ai.workspace.file', ['path' => $relPath], false) : '#'),
                    'funkenwerk_3d_uri' => $att['funkenwerk_3d_uri'] ?? ($winUnc ? ('funkenwerk3d://open?path=' . rawurlencode($winUnc)) : null),
                    'pinned' => $isPinned,
                    'archived' => $isArchived,
                    'days_until_archive' => $daysUntilArchive,
                    'dimensions_mm' => $att['dimensions_mm'] ?? null,
                    'filament_grams' => $att['filament_grams'] ?? null,
                    'created_at_formatted' => $createdAt->format('d.m.Y H:i'),
                ];
            }
        }

        return [
            'items' => $items,
            'stats' => [
                'active_count' => $activeCount,
                'archived_count' => $archivedCount,
                'pinned_count' => $pinnedCount,
                'concept_open_count' => $conceptOpenCount,
                'concept_verified_count' => $conceptVerifiedCount,
                'active_bytes' => $activeBytes,
                'archived_bytes' => $archivedBytes,
                'active_human' => self::formatBytes($activeBytes),
                'archived_human' => self::formatBytes($archivedBytes),
            ],
        ];
    }

    /**
     * Vollautomatischer 3-Stufen-Lebenszyklus gegen Datenmüll (täglicher Cronjob, 0 Aufwand für Alina):
     * 1. Aktive Ticket-Dateien > 28 Tage (nicht angepinnt) -> werden verlustfrei ins Monats-Archiv verschoben
     *    (große unkomprimierte Formate wie .stl, .obj, .md, .txt, .xcs werden automatisch per gzip geschrumpft!).
     * 2. Archivierte Dateien > 180 Tage (nicht angepinnt) -> werden physisch gelöscht, Ticket-Nachricht behält sauberen Historien-Vermerk.
     * 3. Verwaiste Dateien im Ordner `agenten/workspace/tickets/` (> 48h alt, in keiner Nachricht referenziert)
     *    sowie flüchtige Alt-Exporte in `public/agenten/exports/reports/` (> 28 Tage) -> werden restlos entfernt.
     *
     * @return array{archived_count: int, purged_archive_count: int, orphan_deleted_count: int, bytes_saved: int}
     */
    public static function runAutomatedLifecycleCleanup(
        int $archiveAfterDays = self::ARCHIVE_AFTER_DAYS,
        int $purgeArchiveAfterDays = self::PURGE_ARCHIVED_AFTER_DAYS
    ): array {
        $disk = Storage::disk(self::DISK);
        $archiveCutoff = now()->subDays(max(1, $archiveAfterDays));
        $purgeCutoff = now()->subDays(max($archiveAfterDays + 1, $purgeArchiveAfterDays));
        $orphanCutoffTs = now()->subHours(48)->timestamp;

        $archivedCount = 0;
        $purgedArchiveCount = 0;
        $orphanDeletedCount = 0;
        $bytesSaved = 0;
        $referencedPaths = [];

        if (Schema::hasTable('ai_universe_ticket_messages')) {
            $messages = AiUniverseTicketMessage::whereNotNull('attachments')->get();

            foreach ($messages as $msg) {
                if (!is_array($msg->attachments) || empty($msg->attachments)) {
                    continue;
                }

                $attachments = $msg->attachments;
                $modified = false;

                foreach ($attachments as &$att) {
                    if (!is_array($att) || !empty($att['purged'])) {
                        continue;
                    }

                    $isPinned = !empty($att['pinned']);
                    $isArchived = !empty($att['archived']);
                    $relPath = (string) ($att['rel_path'] ?? '');
                    if ($relPath === '' && !empty($att['url']) && preg_match('/[?&]path=([^&]+)/', (string) $att['url'], $pm)) {
                        $decoded = urldecode($pm[1]);
                        if (str_starts_with($decoded, self::ACTIVE_DIR . '/')) {
                            $relPath = $decoded;
                            $att['rel_path'] = $relPath;
                        }
                    }

                    $createdAt = !empty($att['created_at'])
                        ? Carbon::parse((string) $att['created_at'])
                        : ($msg->created_at ?? now());

                    $isOpenConcept = (($att['category'] ?? '') === 'concept' && ($att['concept_status'] ?? 'open') === 'open');

                    // Stufe 1: > 28 Tage alt & nicht angepinnt & kein offenes Konzept -> ins Archiv verschieben (+ Gzip bei Rohdaten)
                    if (!$isArchived && !$isPinned && !$isOpenConcept && $createdAt->lte($archiveCutoff) && $relPath !== '' && $disk->exists($relPath)) {
                        $origSize = (int) $disk->size($relPath);
                        $rawBytes = (string) $disk->get($relPath);
                        $ext = strtolower((string) pathinfo($relPath, PATHINFO_EXTENSION));
                        $compressible = in_array($ext, ['stl', 'obj', 'ply', 'md', 'txt', 'csv', 'json', 'xcs', 'ics'], true);

                        $monthBucket = $createdAt->format('Y-m');
                        $archiveSubDir = self::ARCHIVE_DIR . '/' . $monthBucket;
                        if (!$disk->exists($archiveSubDir)) {
                            $disk->makeDirectory($archiveSubDir);
                        }

                        if ($compressible) {
                            $compressed = gzencode($rawBytes, 6);
                            $targetRel = $archiveSubDir . '/' . basename($relPath) . '.gz';
                            $disk->put($targetRel, $compressed !== false ? $compressed : $rawBytes);
                            $newSize = (int) $disk->size($targetRel);
                        } else {
                            $targetRel = $archiveSubDir . '/' . basename($relPath);
                            $disk->put($targetRel, $rawBytes);
                            $newSize = $origSize;
                        }

                        $disk->delete($relPath);

                        $att['archived'] = true;
                        $att['archived_at'] = now()->toIso8601String();
                        $att['archive_rel_path'] = $targetRel;
                        $att['rel_path'] = $targetRel;
                        $att['archived_size_bytes'] = $newSize;
                        $att['url'] = route('admin.ai.workspace.file', ['path' => $targetRel], false);
                        $att['funkenwerk_3d_uri'] = null;

                        $bytesSaved += max(0, $origSize - $newSize);
                        $archivedCount++;
                        $modified = true;
                        $referencedPaths[$targetRel] = true;
                        continue;
                    }

                    // Stufe 2: Archiviert > 180 Tage & nicht angepinnt -> physisch löschen (0 Speicherleichen)
                    if ($isArchived && !$isPinned) {
                        $archAt = !empty($att['archived_at']) ? Carbon::parse((string) $att['archived_at']) : $createdAt;
                        $archRel = (string) ($att['archive_rel_path'] ?? $relPath);
                        if ($archAt->lte($purgeCutoff)) {
                            if ($archRel !== '' && $disk->exists($archRel)) {
                                $bytesSaved += (int) $disk->size($archRel);
                                $disk->delete($archRel);
                            }
                            $att['purged'] = true;
                            $att['purged_at'] = now()->toIso8601String();
                            $att['url'] = null;
                            $purgedArchiveCount++;
                            $modified = true;
                            continue;
                        }
                    }

                    if ($relPath !== '') {
                        $referencedPaths[$relPath] = true;
                    }
                    if (!empty($att['archive_rel_path'])) {
                        $referencedPaths[(string) $att['archive_rel_path']] = true;
                    }
                }
                unset($att);

                if ($modified) {
                    $msg->update(['attachments' => $attachments]);
                }
            }
        }

        // Stufe 3a: Verwaiste Dateien im aktiven Ticket-Ordner (> 48h ohne Nachrichten-Referenz) entfernen
        if ($disk->exists(self::ACTIVE_DIR)) {
            foreach ($disk->files(self::ACTIVE_DIR) as $file) {
                $base = basename($file);
                if (str_starts_with($base, '.')) {
                    continue;
                }
                if (!isset($referencedPaths[$file]) && (int) $disk->lastModified($file) <= $orphanCutoffTs) {
                    $bytesSaved += (int) $disk->size($file);
                    $disk->delete($file);
                    $orphanDeletedCount++;
                }
            }
        }

        // Stufe 3b: Temporäre Export-Reports in public/agenten/exports/reports (> 28 Tage) bereinigen
        $legacyReportsDir = 'public/agenten/exports/reports';
        if (Storage::exists($legacyReportsDir)) {
            $exportCutoffTs = $archiveCutoff->timestamp;
            foreach (Storage::files($legacyReportsDir) as $repFile) {
                if ((int) Storage::lastModified($repFile) <= $exportCutoffTs) {
                    $bytesSaved += (int) Storage::size($repFile);
                    Storage::delete($repFile);
                    $orphanDeletedCount++;
                }
            }
        }

        return [
            'archived_count' => $archivedCount,
            'purged_archive_count' => $purgedArchiveCount,
            'orphan_deleted_count' => $orphanDeletedCount,
            'bytes_saved' => $bytesSaved,
        ];
    }
}
