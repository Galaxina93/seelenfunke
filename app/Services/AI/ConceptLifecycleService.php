<?php

namespace App\Services\AI;

use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConceptLifecycleService
{
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED_VERIFIED = 'completed_verified';
    public const CARRIER_FINGERPRINT = 'sf_central_concept_hub_v1';

    /**
     * Erzeugt einen bereinigten, kanonischen Themen-Slug für die Dublettenprüfung
     * (entfernt Präfixe wie "Konzept:", "Entwurf:", Datumsangaben und Dateiendungen).
     */
    public static function normalizeConceptSlug(string $rawTitle): string
    {
        $clean = trim(preg_replace('/\.(md|txt)$/iu', '', trim($rawTitle)) ?? $rawTitle);
        $clean = preg_replace('/^\d{4}[-_]\d{2}[-_]\d{2}(?:[-_]\d{4,6})?[-_\s]*/u', '', $clean) ?? $clean;
        $clean = preg_replace('/^(?:architektur[\s\-]*)?(?:fach[\s\-]*)?(?:konzept|entwurf|plan|spezifikation)\s*(?:zur|zum|für|fuer|:|\-|–)?\s*/iu', '', trim($clean)) ?? $clean;

        return Str::slug($clean) ?: Str::slug($rawTitle) ?: 'konzept';
    }

    /**
     * Listet alle im Ticketsystem (`/admin/tickets?view=files&ftype=concept`) hinterlegten Konzepte auf.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listConcepts(string $statusFilter = 'all', string $search = ''): array
    {
        $normStatus = in_array($statusFilter, [self::STATUS_OPEN, self::STATUS_COMPLETED_VERIFIED], true)
            ? $statusFilter
            : 'all';

        $collected = TicketDeliverableService::collectAllTicketDeliverables(
            'all',
            'concept',
            $search,
            $normStatus
        );

        return $collected['items'] ?? [];
    }

    /**
     * Sucht ein bestehendes Konzept über 4 Stufen (ID/Pfad -> kanonischer Slug -> SHA-256 -> Jaccard >= 0.72),
     * um doppelte Konzepterstellungen 100 % zuverlässig zu verhindern.
     *
     * @return array{message: AiUniverseTicketMessage, index: int, attachment: array<string, mixed>}|null
     */
    public static function findExistingConcept(string $titleOrIdentifier, ?string $markdownContent = null): ?array
    {
        if (!Schema::hasTable('ai_universe_ticket_messages')) {
            return null;
        }

        $targetRaw = trim($titleOrIdentifier);
        $targetSlug = self::normalizeConceptSlug($targetRaw);
        $targetHash = $markdownContent !== null && trim($markdownContent) !== ''
            ? CentralBrainService::computeCanonicalHash($markdownContent)
            : null;
        $incomingTokens = CentralBrainService::extractSignificantTokens(
            $targetRaw . ' ' . mb_substr((string) $markdownContent, 0, 1800),
            28
        );

        $messages = AiUniverseTicketMessage::whereNotNull('attachments')
            ->orderByDesc('created_at')
            ->limit(400)
            ->get();

        $disk = Storage::disk(TicketDeliverableService::DISK);

        foreach ($messages as $msg) {
            if (!is_array($msg->attachments) || empty($msg->attachments)) {
                continue;
            }

            foreach ($msg->attachments as $idx => $att) {
                if (!is_array($att) || !empty($att['purged'])) {
                    continue;
                }
                $ext = strtolower((string) ($att['ext'] ?? pathinfo((string) ($att['name'] ?? ''), PATHINFO_EXTENSION)));
                $cat = (string) ($att['category'] ?? TicketDeliverableService::detectCategory($ext));
                if ($cat !== 'concept') {
                    continue;
                }

                $attId = (string) ($att['id'] ?? '');
                $attRel = (string) ($att['rel_path'] ?? '');
                $attTitle = (string) ($att['concept_title'] ?? ($att['name'] ?? ''));
                $attSlug = (string) ($att['concept_slug'] ?? self::normalizeConceptSlug($attTitle));

                // Stufe 1: Exakte ID oder Pfad
                if ($targetRaw !== '' && ($targetRaw === $attId || $targetRaw === $attRel || strcasecmp($targetRaw, (string) ($att['name'] ?? '')) === 0)) {
                    return ['message' => $msg, 'index' => $idx, 'attachment' => $att];
                }

                // Stufe 2: Kanonischer Themen-Slug (inkl. Teil-Slug bei Kurz-Referenzen durch Funkira/Alina)
                if ($targetSlug !== '' && $attSlug !== '') {
                    if ($targetSlug === $attSlug || self::normalizeConceptSlug($attTitle) === $targetSlug) {
                        return ['message' => $msg, 'index' => $idx, 'attachment' => $att];
                    }
                    if (mb_strlen($targetSlug) >= 6 && mb_strlen($attSlug) >= 6
                        && (str_contains($attSlug, $targetSlug) || str_contains($targetSlug, $attSlug))) {
                        return ['message' => $msg, 'index' => $idx, 'attachment' => $att];
                    }
                }

                $existingText = $attRel !== '' ? TicketDeliverableService::readConceptText($attRel) : null;

                // Stufe 3: Exakter Inhalts-Hash
                if ($targetHash !== null && is_string($existingText) && trim($existingText) !== '') {
                    if (CentralBrainService::computeCanonicalHash($existingText) === $targetHash) {
                        return ['message' => $msg, 'index' => $idx, 'attachment' => $att];
                    }
                }

                // Stufe 4: Semantischer Themen-Check (Jaccard >= 0.72)
                if (count($incomingTokens) >= 4) {
                    $candTokens = CentralBrainService::extractSignificantTokens(
                        $attTitle . ' ' . mb_substr((string) $existingText, 0, 1800),
                        28
                    );
                    if (CentralBrainService::jaccardSimilarity($incomingTokens, $candTokens) >= 0.72) {
                        return ['message' => $msg, 'index' => $idx, 'attachment' => $att];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Erstellt ein neues Konzept unter `/admin/tickets?view=files&ftype=concept` (Standard-Status: `Offen`)
     * oder aktualisiert dublettenfrei das bereits vorhandene Konzept zum selben Thema!
     *
     * @return array{status: string, action: string, concept: array<string, mixed>, ticket_number: string, message_id: string}
     */
    public static function createOrUpdateConcept(
        string $title,
        string $markdown,
        ?string $ticketId = null,
        string $status = self::STATUS_OPEN,
        string $authorName = 'Antigravity',
        string $departmentName = 'Produkte'
    ): array {
        $cleanTitle = trim(str_replace([' – ', ' — '], ' - ', $title));
        if ($cleanTitle === '') {
            $cleanTitle = 'Unbenanntes Konzept';
        }
        $normStatus = $status === self::STATUS_COMPLETED_VERIFIED
            ? self::STATUS_COMPLETED_VERIFIED
            : self::STATUS_OPEN;
        $slug = self::normalizeConceptSlug($cleanTitle);
        $cleanBody = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
        if ($cleanBody === '') {
            $cleanBody = "# {$cleanTitle}\n";
        }

        $existingMatch = self::findExistingConcept($cleanTitle, $cleanBody);
        $disk = Storage::disk(TicketDeliverableService::DISK);

        if ($existingMatch !== null) {
            /** @var AiUniverseTicketMessage $msg */
            $msg = $existingMatch['message'];
            $idx = $existingMatch['index'];
            $att = $existingMatch['attachment'];
            $relPath = (string) ($att['rel_path'] ?? '');

            // Falls archiviert (.gz), zunächst wieder im aktiven Pfad ablegen
            if ($relPath === '' || !empty($att['archived']) || str_ends_with($relPath, '.gz')) {
                $safeFile = now()->format('Y-m-d_His') . '_konzept_' . Str::limit($slug, 55, '') . '.md';
                $relPath = TicketDeliverableService::ACTIVE_DIR . '/' . $safeFile;
            }

            if (!$disk->exists(TicketDeliverableService::ACTIVE_DIR)) {
                $disk->makeDirectory(TicketDeliverableService::ACTIVE_DIR);
            }
            $disk->put($relPath, $cleanBody);

            $version = max(1, (int) ($att['concept_version'] ?? 1)) + 1;
            $att['name'] = Str::limit($slug, 70, '') . '.md';
            $att['concept_title'] = $cleanTitle;
            $att['concept_slug'] = $slug;
            $att['concept_status'] = $normStatus;
            $att['concept_version'] = $version;
            $att['verified_at'] = $normStatus === self::STATUS_COMPLETED_VERIFIED ? now()->toIso8601String() : null;
            $att['rel_path'] = $relPath;
            $att['url'] = route('admin.ai.workspace.file', ['path' => $relPath], false);
            $att['size_bytes'] = strlen($cleanBody);
            $att['size_human'] = TicketDeliverableService::formatBytes(strlen($cleanBody));
            $att['archived'] = false;
            $att['archived_at'] = null;
            $att['pinned'] = ($normStatus === self::STATUS_OPEN) ? true : !empty($att['pinned']);
            $att['updated_at'] = now()->toIso8601String();

            $attachments = $msg->attachments;
            $attachments[$idx] = $att;
            $msg->update(['attachments' => array_values($attachments)]);

            if ($normStatus === self::STATUS_COMPLETED_VERIFIED) {
                self::syncVerifiedConceptToBrain($cleanTitle, $cleanBody, $slug, $departmentName);
            }
            Cache::forget('sf_open_concepts_instant_ctx');

            $ticketNum = (string) ($msg->ticket?->ticket_number ?? 'SF-KONZEPT');

            return [
                'status' => 'success',
                'action' => 'updated_existing',
                'concept' => $att,
                'ticket_number' => $ticketNum,
                'message_id' => (string) $msg->id,
            ];
        }

        // Neues Konzept im zentralen Träger-/Fach-Ticket anlegen
        $ticket = self::resolveOrCreateConceptTicket($ticketId, $cleanTitle, $departmentName);
        $filename = Str::limit($slug, 70, '') . '.md';

        $meta = TicketDeliverableService::storeBytesForTicket(
            $cleanBody,
            $filename,
            $ticket->ticket_number,
            [
                'concept_status' => $normStatus,
                'concept_title' => $cleanTitle,
                'concept_slug' => $slug,
                'concept_version' => 1,
                'pinned' => true,
            ]
        );

        $statusLabel = $normStatus === self::STATUS_COMPLETED_VERIFIED ? 'Fertig & Geprüft' : 'Offen';
        $message = AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'agent',
            'sender_name' => $authorName,
            'channel' => 'concept_hub',
            'content' => "Konzept hinterlegt: **{$cleanTitle}** (Status: `{$statusLabel}`)",
            'attachments' => $meta ? [$meta] : [],
        ]);

        $ticket->increment('messages_count');
        $ticket->update(['last_activity_at' => now()]);

        if ($normStatus === self::STATUS_COMPLETED_VERIFIED) {
            self::syncVerifiedConceptToBrain($cleanTitle, $cleanBody, $slug, $departmentName);
        }
        Cache::forget('sf_open_concepts_instant_ctx');

        return [
            'status' => 'success',
            'action' => 'created_new',
            'concept' => $meta ?? [],
            'ticket_number' => (string) $ticket->ticket_number,
            'message_id' => (string) $message->id,
        ];
    }

    /**
     * Setzt den Status eines Konzepts (`open` vs. `completed_verified`) anhand von ID, Pfad, Titel oder Slug.
     *
     * @return array<string, mixed>|null
     */
    public static function setConceptStatus(
        string $identifier,
        string $status,
        ?string $messageId = null
    ): ?array {
        $normStatus = $status === self::STATUS_COMPLETED_VERIFIED
            ? self::STATUS_COMPLETED_VERIFIED
            : self::STATUS_OPEN;

        if ($messageId !== null && $messageId !== '') {
            $ok = TicketDeliverableService::setConceptStatusOnAttachment($messageId, $identifier, $normStatus);
            if ($ok) {
                $msg = AiUniverseTicketMessage::find($messageId);
                foreach ((array) ($msg?->attachments ?? []) as $att) {
                    if (is_array($att) && ((($att['id'] ?? '') === $identifier) || (($att['rel_path'] ?? '') === $identifier))) {
                        if ($normStatus === self::STATUS_COMPLETED_VERIFIED && !empty($att['rel_path'])) {
                            $body = TicketDeliverableService::readConceptText((string) $att['rel_path']) ?? '';
                            if ($body !== '') {
                                self::syncVerifiedConceptToBrain(
                                    (string) ($att['concept_title'] ?? $att['name'] ?? 'Konzept'),
                                    $body,
                                    (string) ($att['concept_slug'] ?? self::normalizeConceptSlug((string) ($att['name'] ?? 'konzept'))),
                                    (string) ($msg?->ticket?->department?->name ?? 'System')
                                );
                            }
                        }
                        Cache::forget('sf_open_concepts_instant_ctx');
                        return $att;
                    }
                }
            }
        }

        $found = self::findExistingConcept($identifier);
        if ($found === null) {
            return null;
        }

        $msg = $found['message'];
        $att = $found['attachment'];
        $key = (string) ($att['id'] ?? ($att['rel_path'] ?? ''));
        TicketDeliverableService::setConceptStatusOnAttachment((string) $msg->id, $key, $normStatus);

        $updatedMsg = $msg->fresh();
        $updatedAtt = $updatedMsg?->attachments[$found['index']] ?? $att;
        $updatedAtt['concept_status'] = $normStatus;

        if ($normStatus === self::STATUS_COMPLETED_VERIFIED && !empty($updatedAtt['rel_path'])) {
            $body = TicketDeliverableService::readConceptText((string) $updatedAtt['rel_path']) ?? '';
            if ($body !== '') {
                self::syncVerifiedConceptToBrain(
                    (string) ($updatedAtt['concept_title'] ?? $updatedAtt['name'] ?? 'Konzept'),
                    $body,
                    (string) ($updatedAtt['concept_slug'] ?? self::normalizeConceptSlug((string) ($updatedAtt['name'] ?? 'konzept'))),
                    (string) ($msg->ticket?->department?->name ?? 'System')
                );
            }
        }

        Cache::forget('sf_open_concepts_instant_ctx');

        return $updatedAtt;
    }

    /**
     * Löscht ein Konzept per 1-Klick oder Funkira-Befehl rückstandsfrei.
     */
    public static function deleteConcept(string $identifier, ?string $messageId = null): bool
    {
        if ($messageId !== null && $messageId !== '') {
            $deleted = TicketDeliverableService::deleteAttachment($messageId, $identifier);
            if ($deleted) {
                Cache::forget('sf_open_concepts_instant_ctx');
                return true;
            }
        }

        $found = self::findExistingConcept($identifier);
        if ($found === null) {
            return false;
        }

        $msg = $found['message'];
        $att = $found['attachment'];
        $key = (string) ($att['id'] ?? ($att['rel_path'] ?? (string) $found['index']));
        $deleted = TicketDeliverableService::deleteAttachment((string) $msg->id, $key);
        Cache::forget('sf_open_concepts_instant_ctx');

        return $deleted;
    }

    /**
     * Erzeugt einen kompakten 0-Token-Überblick über alle aktuell offenen Konzepte für Funkira & interne Agenten.
     */
    public static function buildCompactOpenConceptsContext(): string
    {
        if (!Schema::hasTable('ai_universe_ticket_messages')) {
            return '';
        }

        return Cache::remember('sf_open_concepts_instant_ctx', 60, function (): string {
            $open = self::listConcepts(self::STATUS_OPEN);
            if (empty($open)) {
                return '';
            }

            $lines = ['[OFFENE KONZEPTE UNTER /admin/tickets?view=files&ftype=concept (KEINE DUBLETTEN ANLEGEN!)]:'];
            foreach (array_slice($open, 0, 6) as $c) {
                $label = (string) ($c['concept_title'] ?? $c['name'] ?? 'Konzept');
                $ver = (int) ($c['concept_version'] ?? 1);
                $lines[] = "- [OFFEN · v{$ver}] {$label} (Ticket {$c['ticket_number']}, Datei: `{$c['name']}`)";
            }

            return implode("\n", $lines);
        });
    }

    protected static function syncVerifiedConceptToBrain(
        string $title,
        string $markdown,
        string $slug,
        string $departmentName = 'System'
    ): void {
        CentralBrainService::upsertDeduplicatedKnowledge(
            title: "Geprüftes Konzept: {$title}",
            content: $markdown,
            categoryName: $departmentName !== '' ? $departmentName : 'System',
            tags: ['Konzept', 'Fertig & Geprüft', $departmentName],
            sourcePath: 'concept:' . $slug,
            visibilityScope: AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE
        );
    }

    protected static function resolveOrCreateConceptTicket(
        ?string $explicitTicketId,
        string $conceptTitle,
        string $departmentName
    ): AiUniverseTicket {
        if ($explicitTicketId !== null && $explicitTicketId !== '') {
            $found = AiUniverseTicket::find($explicitTicketId);
            if ($found) {
                return $found;
            }
        }

        if (app()->bound('active_universe_ticket_id')) {
            $boundId = (string) app('active_universe_ticket_id');
            if ($boundId !== '') {
                $found = AiUniverseTicket::find($boundId);
                if ($found) {
                    return $found;
                }
            }
        }

        $existingHub = AiUniverseTicket::where('error_fingerprint', self::CARRIER_FINGERPRINT)->first();
        if ($existingHub) {
            return $existingHub;
        }

        $deptId = Schema::hasTable('ai_departments')
            ? (AiDepartment::where('name', $departmentName)->value('id')
                ?? AiDepartment::where('name', 'Leitung')->value('id')
                ?? AiDepartment::value('id'))
            : null;

        $nextSeq = (int) AiUniverseTicket::count() + 1;
        do {
            $ticketNumber = 'SF-' . str_pad((string) $nextSeq++, 4, '0', STR_PAD_LEFT);
        } while (AiUniverseTicket::where('ticket_number', $ticketNumber)->exists());

        $payload = [
            'ticket_number' => $ticketNumber,
            'title' => 'Konzept-Zentrale & Architektur-Entwürfe',
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'priority' => 'normal',
            'ticket_type' => 'feature',
            'ai_department_id' => $deptId,
            'assigned_to_antigravity' => false,
            'source_channel' => 'web_portal',
            'environment_tier' => 'local',
            'error_fingerprint' => self::CARRIER_FINGERPRINT,
            'occurrence_count' => 1,
            'messages_count' => 0,
            'last_activity_at' => now(),
        ];
        if (Schema::hasColumn('ai_universe_tickets', 'is_business')) {
            $payload['is_business'] = true;
        }

        return AiUniverseTicket::create($payload);
    }
}
