<?php

namespace App\Services\AI;

use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use App\Models\Ai\AiLearnedPreference;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CentralBrainService
{
    public const SNAPSHOT_STORAGE_REL = 'private/knowledge_brain_reports_snapshot.json';
    public const SNAPSHOT_GIT_REL = 'seeders/data/knowledge_brain_reports_snapshot.json';

    public static function resolveSnapshotPath(): string
    {
        $gitTracked = database_path(self::SNAPSHOT_GIT_REL);
        if (is_file($gitTracked)) {
            return $gitTracked;
        }

        return storage_path('app/' . self::SNAPSHOT_STORAGE_REL);
    }

    private const STOPWORDS = [
        'der' => true, 'die' => true, 'das' => true, 'den' => true, 'dem' => true, 'des' => true,
        'ein' => true, 'eine' => true, 'einer' => true, 'eines' => true, 'einem' => true, 'einen' => true,
        'und' => true, 'oder' => true, 'aber' => true, 'fuer' => true, 'für' => true, 'mit' => true,
        'von' => true, 'vom' => true, 'zum' => true, 'zur' => true, 'aus' => true, 'auf' => true,
        'bei' => true, 'bitte' => true, 'mal' => true, 'kurz' => true, 'noch' => true, 'wie' => true,
        'was' => true, 'wann' => true, 'warum' => true, 'wieso' => true, 'kann' => true, 'soll' => true,
        'muss' => true, 'sind' => true, 'wird' => true, 'wurde' => true, 'haben' => true, 'hat' => true,
        'ticket' => true, 'bericht' => true, 'abschlussbericht' => true, 'mein' => true, 'seelenfunke' => true,
    ];

    /**
     * Normalisiert Text für kanonischen SHA-256-Hash & Dublettenvergleich.
     */
    public static function normalizeContent(string $content): string
    {
        $clean = preg_replace('/<!--\s*sha256:[a-f0-9]+\s*-->/iu', '', $content) ?? $content;
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);
        $clean = preg_replace("/\n{3,}/", "\n\n", trim($clean)) ?? trim($clean);

        return $clean;
    }

    public static function computeCanonicalHash(string $content): string
    {
        $norm = mb_strtolower(preg_replace('/\s+/u', ' ', self::normalizeContent($content)) ?? '');

        return hash('sha256', $norm);
    }

    public static function normalizeTitleKey(string $title): string
    {
        $clean = preg_replace('/^(?:abschlussbericht|system-dokumentation|konzept|workflow|bericht)\s*[:\-–]\s*/iu', '', trim($title)) ?? $title;

        return Str::slug($clean);
    }

    /**
     * Extrahiert signifikante Schlüsselwörter für schnellen Jaccard-Dubletten-Check & 0-Token-Kontextsuche.
     *
     * @return array<int, string>
     */
    public static function extractSignificantTokens(string $text, int $limit = 35): array
    {
        $lower = mb_strtolower(strip_tags($text));
        $words = preg_split('/[^a-z0-9äöüß.\-]+/iu', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $freq = [];

        foreach ($words as $w) {
            $w = trim($w, '.-');
            if (mb_strlen($w) < 3 || isset(self::STOPWORDS[$w])) {
                continue;
            }
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }

        arsort($freq);

        return array_slice(array_keys($freq), 0, $limit);
    }

    public static function jaccardSimilarity(array $tokensA, array $tokensB): float
    {
        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }
        $setA = array_flip($tokensA);
        $setB = array_flip($tokensB);
        $intersect = count(array_intersect_key($setA, $setB));
        $union = count($setA + $setB);

        return $union > 0 ? ($intersect / $union) : 0.0;
    }

    /**
     * Intelligentes, strikt dublettenfreies Upsert in `AiKnowledgeBase`:
     * Prüft 4 Stufen (source_path -> content_sha256 -> kanonischer Titel -> >= 82 % Token-Überlappung in derselben Kategorie),
     * führt Wissen bei Treffern sauber zusammen statt Duplikate anzulegen und synchronisiert Tags chronologisch.
     *
     * @param array<int, string> $tags
     */
    public static function upsertDeduplicatedKnowledge(
        string $title,
        string $content,
        string $categoryName = 'System',
        array $tags = [],
        ?string $sourcePath = null,
        string $visibilityScope = AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
        ?Carbon $chronologicalDate = null
    ): ?AiKnowledgeBase {
        if (!Schema::hasTable('ai_knowledge_bases') || !Schema::hasTable('ai_knowledge_base_categories')) {
            return null;
        }

        $cleanTitle = trim(mb_substr(AiOutputQualityGate::sanitizeText($title), 0, 250));
        $cleanContent = self::normalizeContent($content);
        if ($cleanTitle === '' || $cleanContent === '') {
            return null;
        }

        $sha256 = self::computeCanonicalHash($cleanContent);
        $cleanCatLabel = trim($categoryName) !== '' ? trim($categoryName) : 'Allgemein';
        $catSlug = Str::slug($cleanCatLabel) ?: 'allgemein';
        $category = AiKnowledgeBaseCategory::where('slug', $catSlug)
            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($cleanCatLabel)])
            ->first();
        if (!$category) {
            $category = AiKnowledgeBaseCategory::create([
                'name' => $cleanCatLabel,
                'slug' => $catSlug,
            ]);
        }

        $existing = null;

        // Stufe 1: Exakter source_path (z. B. ticket:SF-0101 oder Abschlussberichte/...)
        if ($sourcePath !== null && trim($sourcePath) !== '' && Schema::hasColumn('ai_knowledge_bases', 'source_path')) {
            $existing = AiKnowledgeBase::where('source_path', trim($sourcePath))->first();
        }

        // Stufe 2: Exakter Inhalts-Hash (verhindert 1:1 Kopien unter anderem Namen)
        if (!$existing && Schema::hasColumn('ai_knowledge_bases', 'content_sha256')) {
            $existing = AiKnowledgeBase::where('content_sha256', $sha256)->first();
        }

        // Stufe 3: Kanonischer Titel-Abgleich (z. B. "Abschlussbericht: X" vs. "X")
        $targetTitleKey = self::normalizeTitleKey($cleanTitle);
        if (!$existing && $targetTitleKey !== '') {
            $candidates = AiKnowledgeBase::where('ai_knowledge_base_category_id', $category->id)
                ->orWhere('title', $cleanTitle)
                ->limit(80)
                ->get();

            foreach ($candidates as $cand) {
                if (self::normalizeTitleKey((string) $cand->title) === $targetTitleKey) {
                    $existing = $cand;
                    break;
                }
            }
        }

        // Stufe 4: Semantischer Jaccard-Ähnlichkeits-Check innerhalb derselben Kategorie (>= 0.82 = gleiches Thema!)
        if (!$existing) {
            $incomingTokens = self::extractSignificantTokens($cleanTitle . ' ' . mb_substr($cleanContent, 0, 2000), 30);
            if (count($incomingTokens) >= 5) {
                $catEntries = AiKnowledgeBase::where('ai_knowledge_base_category_id', $category->id)
                    ->latest('updated_at')
                    ->limit(50)
                    ->get(['id', 'title', 'content', 'source_path']);

                foreach ($catEntries as $cand) {
                    if (str_starts_with((string) $cand->content, '[TS_ENC_V1]:')) {
                        continue;
                    }
                    $candTokens = self::extractSignificantTokens($cand->title . ' ' . mb_substr((string) $cand->content, 0, 2000), 30);
                    if (self::jaccardSimilarity($incomingTokens, $candTokens) >= 0.82) {
                        $existing = AiKnowledgeBase::find($cand->id);
                        break;
                    }
                }
            }
        }

        $payload = [
            'title' => $cleanTitle,
            'ai_knowledge_base_category_id' => $category->id,
            'content' => $cleanContent,
            'is_published' => true,
        ];
        if (Schema::hasColumn('ai_knowledge_bases', 'content_sha256')) {
            $payload['content_sha256'] = $sha256;
        }
        if (Schema::hasColumn('ai_knowledge_bases', 'visibility_scope')) {
            $payload['visibility_scope'] = $visibilityScope;
        }
        if ($sourcePath !== null && Schema::hasColumn('ai_knowledge_bases', 'source_path')) {
            $payload['source_path'] = mb_substr(trim($sourcePath), 0, 191);
        }

        if ($existing) {
            $existing->fill($payload);
            if ($chronologicalDate !== null) {
                $existing->updated_at = $chronologicalDate;
            }
            $existing->save();
            $kb = $existing;
        } else {
            $baseSlug = Str::limit(Str::slug($cleanTitle) ?: 'wissen', 210, '');
            $slug = $baseSlug;
            $suffix = 1;
            while (AiKnowledgeBase::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . (++$suffix);
            }
            $payload['slug'] = $slug;

            $kb = new AiKnowledgeBase($payload);
            if ($chronologicalDate !== null) {
                $kb->created_at = $chronologicalDate;
                $kb->updated_at = $chronologicalDate;
            }
            $kb->save();
        }

        // Tags sauber & dublettenfrei verknüpfen (bestehende + neue vereinigen)
        if (!empty($tags) && Schema::hasTable('ai_knowledge_base_tags')) {
            $tagIds = $kb->tags()->pluck('ai_knowledge_base_tags.id')->all();
            foreach ($tags as $rawTag) {
                $tName = trim((string) $rawTag);
                $tSlug = Str::slug($tName);
                if ($tName === '' || $tSlug === '') {
                    continue;
                }
                $tagModel = AiKnowledgeBaseTag::where('slug', mb_substr($tSlug, 0, 190))
                    ->orWhereRaw('LOWER(name) = ?', [mb_strtolower(mb_substr($tName, 0, 190))])
                    ->first();
                if (!$tagModel) {
                    $tagModel = AiKnowledgeBaseTag::create([
                        'name' => mb_substr($tName, 0, 190),
                        'slug' => mb_substr($tSlug, 0, 190),
                    ]);
                }
                $tagIds[] = $tagModel->id;
            }
            $kb->tags()->sync(array_values(array_unique($tagIds)));
        }

        return $kb;
    }

    /**
     * Bereinigt die gesamte bestehende `AiKnowledgeBase` von historischen Dubletten
     * (z. B. alte doppelte Einträge zwischen `Abschlussberichte: X` und `X` oder identische Titel/Hashes).
     *
     * @return array{scanned: int, merged_duplicates: int, empty_categories_removed: int}
     */
    public static function deduplicateEntireKnowledgeBase(): array
    {
        if (!Schema::hasTable('ai_knowledge_bases')) {
            return ['scanned' => 0, 'merged_duplicates' => 0, 'empty_categories_removed' => 0];
        }

        // 1. Kategorien "Abschlussberichte: <Abt>" sauber mit der kanonischen Kategorie "<Abt>" verschmelzen
        $legacyCats = AiKnowledgeBaseCategory::where('name', 'like', 'Abschlussberichte:%')->get();
        foreach ($legacyCats as $legCat) {
            $cleanCatName = trim(preg_replace('/^Abschlussberichte:\s*/iu', '', (string) $legCat->name) ?? '');
            if ($cleanCatName === '' || strcasecmp($cleanCatName, 'Allgemein') === 0) {
                $cleanCatName = 'Übersicht';
            }
            $canonicalCat = AiKnowledgeBaseCategory::where('slug', Str::slug($cleanCatName))
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($cleanCatName)])
                ->first();
            if (!$canonicalCat) {
                $canonicalCat = AiKnowledgeBaseCategory::create([
                    'name' => $cleanCatName,
                    'slug' => Str::slug($cleanCatName),
                ]);
            }
            if ($canonicalCat->id !== $legCat->id) {
                AiKnowledgeBase::where('ai_knowledge_base_category_id', $legCat->id)
                    ->update(['ai_knowledge_base_category_id' => $canonicalCat->id]);
                $legCat->delete();
            }
        }

        // 2. Einträge scannen (bevorzuge neuere Einträge mit sauberem source_path)
        $all = AiKnowledgeBase::with('tags')
            ->orderByRaw('CASE WHEN source_path IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('updated_at')
            ->get();

        $seenHashes = [];
        $seenCatTitleKeys = [];
        $mergedCount = 0;

        foreach ($all as $entry) {
            $raw = (string) $entry->content;
            if (str_starts_with($raw, '[TS_ENC_V1]:')) {
                continue;
            }

            $cleanBody = self::normalizeContent($raw);
            $hash = self::computeCanonicalHash($cleanBody);
            $titleKey = $entry->ai_knowledge_base_category_id . '|' . self::normalizeTitleKey((string) $entry->title);

            $winner = $seenHashes[$hash] ?? ($seenCatTitleKeys[$titleKey] ?? null);

            if ($winner !== null && $winner->id !== $entry->id) {
                // Tags vom Duplikat auf den Gewinner übertragen, bevor das Duplikat gelöscht wird
                $winnerTagIds = $winner->tags->pluck('id')->all();
                $dupTagIds = $entry->tags->pluck('id')->all();
                $mergedTags = array_values(array_unique(array_merge($winnerTagIds, $dupTagIds)));
                if (!empty($mergedTags)) {
                    $winner->tags()->sync($mergedTags);
                }

                // Falls der Duplikat-Eintrag länger/vollständiger war, Inhalt übernehmen
                if (mb_strlen($cleanBody) > mb_strlen((string) $winner->content) + 120) {
                    $winner->update([
                        'content' => $cleanBody,
                        'content_sha256' => $hash,
                    ]);
                }

                $entry->tags()->detach();
                $entry->delete();
                $mergedCount++;
                continue;
            }

            if ($entry->content !== $cleanBody || $entry->content_sha256 !== $hash) {
                DB::table('ai_knowledge_bases')->where('id', $entry->id)->update([
                    'content' => $cleanBody,
                    'content_sha256' => $hash,
                ]);
            }

            $seenHashes[$hash] = $entry;
            if (self::normalizeTitleKey((string) $entry->title) !== '') {
                $seenCatTitleKeys[$titleKey] = $entry;
            }
        }

        // 3. Verwaiste leere "Abschlussberichte"-Kategorien entfernen
        $emptyRemoved = AiKnowledgeBaseCategory::where('slug', 'like', 'abschlussberichte-%')
            ->whereDoesntHave('articles')
            ->delete();

        AiKnowledgeBase::flushBrainCache();

        return [
            'scanned' => $all->count(),
            'merged_duplicates' => $mergedCount,
            'empty_categories_removed' => $emptyRemoved,
        ];
    }

    /**
     * Erzeugt und speichert bei JEDES abgeschlossenen Tickets (`STATUS_COMPLETED`) vollautomatisch in 0 API-Tokens
     * einen kompakten, chronologischen Ticket-Abschlussbericht im zentralen Wissensgehirn (`source_path = ticket:SF-XXXX`).
     * Wird ein Ticket erneut geöffnet und nochmals abgeschlossen, aktualisiert es denselben Wissenseintrag dublettenfrei!
     */
    public static function recordTicketCompletionReport(AiUniverseTicket $ticket, ?string $explicitSummary = null): ?AiKnowledgeBase
    {
        if (!Schema::hasTable('ai_knowledge_bases') || $ticket->status !== AiUniverseTicket::STATUS_COMPLETED) {
            return null;
        }

        $ticket->loadMissing(['department', 'agent', 'messages']);
        $deptName = (string) ($ticket->department?->name ?? 'System');
        $agentName = (string) ($ticket->agent?->name ?? ($ticket->assigned_to_antigravity ? 'Antigravity' : 'Funkira'));
        $completedAt = $ticket->completed_at ?? now();

        $firstUserMsg = $ticket->messages->firstWhere('sender_role', 'alina');
        $lastAgentMsg = $ticket->messages->whereIn('sender_role', ['agent', 'system'])->last();

        $problemText = trim((string) ($firstUserMsg?->content ?? $ticket->title));
        $solutionText = $explicitSummary !== null && trim($explicitSummary) !== ''
            ? trim($explicitSummary)
            : trim((string) ($lastAgentMsg?->content ?? 'Aufgabe erfolgreich abgeschlossen.'));

        $toolsUsed = [];
        $deliverableNames = [];
        foreach ($ticket->messages as $m) {
            if (is_array($m->executed_tools)) {
                foreach ($m->executed_tools as $tName) {
                    $toolsUsed[] = (string) $tName;
                }
            }
            if (is_array($m->attachments)) {
                foreach ($m->attachments as $att) {
                    if (is_array($att) && !empty($att['name'])) {
                        $deliverableNames[] = (string) $att['name'];
                    }
                }
            }
        }
        $toolsUsed = array_values(array_unique(array_filter($toolsUsed)));
        $deliverableNames = array_values(array_unique(array_filter($deliverableNames)));

        $isBusinessTicket = (bool) ($ticket->is_business ?? true);
        $scopeLabel = AiUniverseTicket::getScopeLabel($isBusinessTicket);

        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $errorLoc = !empty($payload['file'])
            ? ((string) $payload['file'] . (!empty($payload['line']) ? ':' . $payload['line'] : ''))
            : '';
        $occurrences = max(1, (int) ($ticket->occurrence_count ?? 1));

        $mdLines = [
            "# [{$ticket->ticket_number}] {$ticket->title}",
            '',
            "- **Datum:** " . $completedAt->format('d.m.Y H:i') . ' Uhr',
            "- **Bereich:** {$scopeLabel}",
            "- **Abteilung & Einheit:** {$deptName} · {$agentName}",
            "- **Kanal & Umgebung:** " . AiUniverseTicket::getChannelLabel((string) $ticket->source_channel) . " (`{$ticket->environment_tier}`)",
        ];
        if ($errorLoc !== '') {
            $mdLines[] = "- **Gehärteter Fehlerort:** `{$errorLoc}`" . ($occurrences > 1 ? " (vor Fix {$occurrences}x aufgetreten)" : '');
        }
        if (!empty($toolsUsed)) {
            $mdLines[] = '- **Ausgeführte Werkzeuge:** `' . implode('`, `', $toolsUsed) . '`';
        }
        if (!empty($deliverableNames)) {
            $mdLines[] = '- **Bereitgestellte Dateien:** ' . implode(', ', $deliverableNames);
        }
        $mdLines[] = '';
        $mdLines[] = '## Ausgangsanfrage';
        $mdLines[] = Str::limit($problemText, 800);
        $mdLines[] = '';
        $mdLines[] = '## Lösung, Code-Härtung & Verifikation';
        $mdLines[] = Str::limit($solutionText, 1800);

        $hasHardeningDoc = mb_stripos($solutionText, 'härtung') !== false || $errorLoc !== '';

        $tags = array_values(array_unique(array_filter([
            'Ticket-Abschlussbericht',
            $hasHardeningDoc ? 'Code-Härtung' : null,
            $scopeLabel,
            strtolower($ticket->ticket_number),
            $deptName,
            $agentName,
            $ticket->ticket_type ?: null,
        ])));

        return self::upsertDeduplicatedKnowledge(
            title: "[{$ticket->ticket_number}] {$ticket->title}",
            content: implode("\n", $mdLines),
            categoryName: 'Ticket-Abschlussberichte',
            tags: $tags,
            sourcePath: 'ticket:' . $ticket->ticket_number,
            visibilityScope: AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
            chronologicalDate: $completedAt
        );
    }

    /**
     * Liefert die dynamisch aus den aktuellen Systemeinstellungen aufgelösten Gründungs-,
     * Gewerbeanmeldungs- und Steuernummern-Daten für interne KI-Agenten (0 Tokens, niemals für Funki).
     *
     * @return array<string, mixed>
     */
    public static function resolveCompanyFoundingAndTaxContext(): array
    {
        $companyName = trim((string) shop_setting('company_name', 'Mein Seelenfunke'));
        $proprietor = trim((string) shop_setting('owner_proprietor', 'Alina Steinhauer'));
        $finanzamtNr = trim((string) shop_setting('owner_finanzamt_nr', '2319'));
        $taxId = trim((string) shop_setting('owner_tax_id', '19/143/11624'));
        $ustId = trim((string) shop_setting('owner_ust_id', 'XXX'));
        $taxIdentNr = trim((string) shop_setting('owner_tax_ident_nr', '66324780911'));
        $economicIdentNr = trim((string) shop_setting('owner_economic_ident_nr', 'DE410594485'));
        $isSmallBusiness = filter_var(shop_setting('is_small_business', false), FILTER_VALIDATE_BOOLEAN);

        $regDateIso = trim((string) shop_setting('business_registration_date', '2026-11-01')) ?: '2026-11-01';
        $regCertifiedIso = trim((string) shop_setting('business_registration_certified_at', '2026-10-28')) ?: '2026-10-28';
        $regRef = trim((string) shop_setting('business_registration_ref', 'gew / 28-10-2026 / Ce / 402')) ?: 'gew / 28-10-2026 / Ce / 402';

        $elsterDateIso = trim((string) shop_setting('tax_registration_elster_submitted_at', '2026-11-06')) ?: '2026-11-06';
        $elsterTicket = trim((string) shop_setting('tax_registration_elster_ticket', 'ep3104hvg8fm3b1m4zrwrd3djc6ieugc')) ?: 'ep3104hvg8fm3b1m4zrwrd3djc6ieugc';
        $taxNumberStatusSetting = strtolower(trim((string) shop_setting('tax_number_status', 'pending')));
        $ustIdStatusSetting = strtolower(trim((string) shop_setting('ust_id_status', 'pending')));

        $formatGermanDate = static function (string $iso): string {
            try {
                return Carbon::parse($iso)->format('d.m.Y');
            } catch (\Throwable) {
                return $iso;
            }
        };

        $isPlaceholder = static function (string $val): bool {
            $lower = mb_strtolower(trim($val));
            return $lower === ''
                || $lower === 'xxx'
                || str_contains($lower, 'ausstehend')
                || str_contains($lower, 'beantragt')
                || str_contains($lower, 'nachgereicht')
                || str_contains($lower, 'placeholder');
        };

        $steuernummerPending = $isPlaceholder($taxId)
            || ($taxId === '19/143/11624' && $taxNumberStatusSetting !== 'assigned');
        $ustIdPending = $isPlaceholder($ustId)
            || ($ustIdStatusSetting !== 'assigned' && preg_match('/^DE[0-9]{9}$/i', $ustId) !== 1);

        $steuernummerSummary = $steuernummerPending
            ? "NOCH AUSSTEHEND beim Finanzamt Gifhorn ({$finanzamtNr}): ELSTER-Fragebogen am {$formatGermanDate($elsterDateIso)} ohne Steuernummer übermittelt (System-Alt-/Testwert: " . ($taxId !== '' ? $taxId : 'leer') . ')'
            : "Zugeordnet ({$taxId}, Finanzamt Gifhorn {$finanzamtNr})";

        $ustIdSummary = $ustIdPending
            ? "NOCH AUSSTEHEND beim BZSt: am {$formatGermanDate($elsterDateIso)} über ELSTER mitbeantragt (Shop-Platzhalter: " . ($ustId !== '' ? $ustId : 'leer') . ')'
            : "Zugeordnet ({$ustId})";

        $waitingDependencies = [];
        if ($steuernummerPending || $ustIdPending) {
            $waitingDependencies = [
                'LUCID Verpackungsregister & Lizenzero Jahreslizenzierung (vor erstem Live-Paketversand)',
                'Westpack Reverse-Charge-Rechnungskorrektur Order #29000069521 (29,19 EUR dänische MwSt.) & 100 m Geschenkpapier-Nachschub',
                'Stripe Live-Unternehmensdaten (Steuernummer & USt-IdNr. DE...)',
                'FBM (Friedrich Binder GmbH) Händlerkonto-Finalisierung & Steuerberater-Eröffnungsabstimmung',
            ];
        }

        return [
            'company_name' => $companyName,
            'proprietor' => $proprietor,
            'legal_form' => 'Einzelunternehmen (Haupterwerb, Hauptniederlassung, Neugründung)',
            'tax_regime' => $isSmallBusiness
                ? 'Kleinunternehmerregelung (§ 19 UStG)'
                : 'Regelbesteuerung (19 % / 7 % USt mit Vorsteuerabzug, Istversteuerung § 20 UStG)',
            'business_registration' => [
                'status' => 'registered',
                'status_label' => 'Angemeldet & amtlich bescheinigt (GewA 1)',
                'start_date' => $regDateIso,
                'start_date_de' => $formatGermanDate($regDateIso),
                'certified_date' => $regCertifiedIso,
                'certified_date_de' => $formatGermanDate($regCertifiedIso),
                'authority' => 'Stadt Gifhorn (Gemeindekennzahl 03151009)',
                'file_reference' => $regRef,
                'process_number' => 'gew / 28-10-2026 / Ce / 893',
                'receipt_path' => 'storage/app/buchhaltung/receipts/2026/10/2026-10-28_Stadt-Gifhorn_Gewerbeanmeldung-GewA1-Bescheinigung.pdf',
            ],
            'tax_registration' => [
                'finanzamt' => 'Finanzamt Gifhorn',
                'finanzamt_nr' => $finanzamtNr,
                'finanzamt_email' => 'poststelle@fa-gf.niedersachsen.de',
                'elster_submitted_at' => $elsterDateIso,
                'elster_submitted_at_de' => $formatGermanDate($elsterDateIso),
                'elster_transfer_ticket' => $elsterTicket,
                'elster_reference' => '2169193168 -B41',
                'follow_up_sent_at' => '2026-11-17',
                'follow_up_note' => 'Dringende Sachstandsanfrage am 17.11.2026 an poststelle@fa-gf.niedersachsen.de versendet (Ticket SF-0090).',
                'receipt_path' => 'storage/app/buchhaltung/receipts/2026/11/2026-11-06_Finanzamt-Gifhorn_ELSTER-Steuerliche-Erfassung-Versandbestaetigung.pdf',
            ],
            'tax_numbers' => [
                'steuernummer' => [
                    'status' => $steuernummerPending ? 'pending' : 'assigned',
                    'is_pending' => $steuernummerPending,
                    'configured_value' => $taxId,
                    'summary' => $steuernummerSummary,
                ],
                'ust_id' => [
                    'status' => $ustIdPending ? 'pending' : 'assigned',
                    'is_pending' => $ustIdPending,
                    'configured_value' => $ustId,
                    'summary' => $ustIdSummary,
                ],
                'steuer_ident_nr' => $taxIdentNr,
                'wirtschafts_ident_nr' => $economicIdentNr,
            ],
            'waiting_processes' => $waitingDependencies,
        ];
    }

    /**
     * Erzeugt einen kompakten 2-Zeilen-Statusblock für interne Agenten-Prompts (0 Token-Ballast).
     */
    public static function formatCompactFoundingAndTaxContext(): string
    {
        $ctx = self::resolveCompanyFoundingAndTaxContext();
        $reg = $ctx['business_registration'];
        $tax = $ctx['tax_numbers'];

        return "- Gründung & Gewerbe: {$ctx['company_name']} (Inh. {$ctx['proprietor']}), Gewerbeanmeldung GewA 1 Stadt Gifhorn zum {$reg['start_date_de']} (bescheinigt {$reg['certified_date_de']}, Az. `{$reg['file_reference']}`), {$ctx['tax_regime']}.\n"
            . "- Steuerstatus Finanzamt Gifhorn ({$ctx['tax_registration']['finanzamt_nr']}, ELSTER {$ctx['tax_registration']['elster_submitted_at_de']}): Steuernummer: {$tax['steuernummer']['summary']} | USt-IdNr.: {$tax['ust_id']['summary']} (bei Details Tool `finance_get_tax_and_founding_status` nutzen).";
    }

    /**
     * Millisekunden-Kontext-Injektor (0 API-Tokens!) für ALLE internen Agenten (niemals für Frontend-Support Funki!):
     * Baut vor jedem Agenten-Lauf in < 8 ms den exakten Kontext aus:
     * 1. Gelernte Dauer-Entscheidungen (`AiLearnedPreference`) der Abteilung
     * 2. Kompakte globale System- & Architektur-Leitplanken (Routen, Modelle, Pfade, Manufaktur-Stammdaten)
     * 3. Top-relevante Wissenskapitel + frühere Ticket-Abschlussberichte exakt zur aktuellen Anfrage (`$prompt`)!
     */
    public static function buildZeroTokenInstantContext(
        ?string $departmentId,
        string $prompt = '',
        ?object $agent = null
    ): string {
        // Eiserne Air-Gap-Schranke: Frontend-Support-Agent Funki erhält NIEMALS internes Gehirn-Wissen!
        if ($agent !== null && AgentSkillBudgetGuard::isFunkiAgent($agent)) {
            return '';
        }

        $blocks = [];

        // 1. Kompaktes globales Architektur-Spickzettel (< 0.1 ms im Speicher, erspart langes Suchen!)
        $blocks[] = "[GLOBALE SYSTEM-ARCHITEKTUR (0-TOKEN DIREKTWISSEN)]:\n"
            . "- Kommandozentrale: `/admin/dashboard` (`MasterAnalytics`, `ManagementTask`) | Zentrales Ticketsystem & Gehirn: `/admin/tickets` (`AiUniverseTickets`, Tabs: `Tickets`, `Dateien`, `Organigramm`, `Wissen`).\n"
            . "- Buchhaltung: `AccountingSeeder.php` (Cent-Integer, Gewerblich vs. Privat, FYRST), `/admin/financial-variable-costs`, `/admin/financial-fix-costs`.\n"
            . "- Produkte & Laser: `/admin/products` (`Product*Seeder.php`), `/admin/orders` (`OrderOverview`), xTool F2 Ultra UV (`5W UV`, `140x140 mm` Raster, K9 Seelen-Kristall Fokus `32,8-33,1 mm`), 3D-Druck Bambu Lab A1 (`256x256x256 mm`, `0 % Support`, `Funkenwerk 3D`).\n"
            . self::formatCompactFoundingAndTaxContext();

        // 2. Gelernte Dauer-Entscheidungen von Alina
        if (Schema::hasTable('ai_learned_preferences')) {
            $prefs = AiLearnedPreference::query()
                ->where('is_active', true)
                ->when($departmentId, function ($q) use ($departmentId) {
                    $q->where(function ($sq) use ($departmentId) {
                        $sq->where('ai_department_id', $departmentId)
                            ->orWhereNull('ai_department_id');
                    });
                })
                ->latest('updated_at')
                ->limit(8)
                ->get(['subject_label', 'rule_summary']);

            if ($prefs->isNotEmpty()) {
                $lines = ['[GELERNTE DAUER-ENTSCHEIDUNGEN VON ALINA (NICHT ERNEUT FRAGEN!)]:'];
                foreach ($prefs as $p) {
                    $lines[] = "- {$p->subject_label}: {$p->rule_summary}";
                }
                $blocks[] = implode("\n", $lines);
            }
        }

        // 2b. Offene Konzepte aus der zentralen Konzept-Verwaltung (/admin/tickets?view=files&ftype=concept)
        if (AiAuthHelper::isAdmin() && class_exists(ConceptLifecycleService::class)) {
            $openConceptBlock = ConceptLifecycleService::buildCompactOpenConceptsContext();
            if ($openConceptBlock !== '') {
                $blocks[] = $openConceptBlock;
            }
        }

        // 3. Themenbezogener Millisekunden-Treffer aus Wissensgehirn & früheren Ticket-Abschlussberichten
        $matchedBlock = self::findCompactPriorReportsContext($prompt, $departmentId, 4, 320, false);
        if ($matchedBlock !== '') {
            $blocks[] = $matchedBlock;
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Ultra-kompakter, 0-LLM-Token Vorab-Check (< 5 ms) gegen alle Abschlussberichte,
     * Ticket-Lösungen und Wissenseinträge in `AiKnowledgeBase`.
     * Liefert leer zurück, wenn kein belastbarer Treffer zur Anfrage vorliegt (0 Token-Overhead bei Smalltalk/"Go").
     */
    public static function findCompactPriorReportsContext(
        string $prompt,
        ?string $departmentId = null,
        int $limit = 3,
        int $snippetMaxChars = 250,
        bool $includeDirectiveHeader = true
    ): string {
        if (!Schema::hasTable('ai_knowledge_bases')) {
            return '';
        }

        $keywords = self::extractSignificantTokens($prompt, 6);
        $deptName = $departmentId && Schema::hasTable('ai_departments')
            ? (string) (AiDepartment::where('id', $departmentId)->value('name') ?? '')
            : '';

        if (empty($keywords) && ($includeDirectiveHeader || $deptName === '')) {
            return '';
        }

        $isAdminScope = AiAuthHelper::isAdmin();
        $hasSourcePath = Schema::hasColumn('ai_knowledge_bases', 'source_path');
        $cacheKey = 'sf_prior_reports_ctx_' . md5(
            ($isAdminScope ? 'adm' : 'emp')
            . '|' . ($departmentId ?? 'all')
            . '|' . $limit
            . '|' . $snippetMaxChars
            . '|' . ($includeDirectiveHeader ? '1' : '0')
            . '|' . implode(',', $keywords)
            . '|' . Cache::get('ai_brain_search_version', 1)
        );

        return Cache::remember($cacheKey, 180, function () use (
            $keywords,
            $deptName,
            $limit,
            $snippetMaxChars,
            $includeDirectiveHeader,
            $isAdminScope,
            $hasSourcePath
        ): string {
            $baseScope = function ($q) use ($isAdminScope) {
                $q->where('is_published', true);
                if (!$isAdminScope && Schema::hasColumn('ai_knowledge_bases', 'visibility_scope')) {
                    $q->where(function ($sq) {
                        $sq->whereNull('visibility_scope')
                            ->orWhere('visibility_scope', '!=', AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE);
                    });
                }
            };

            $selectCols = ['id', 'title', 'content', 'ai_knowledge_base_category_id', 'updated_at'];
            if ($hasSourcePath) {
                $selectCols[] = 'source_path';
            }

            $candidates = collect();

            if (!empty($keywords)) {
                // 1. Vorrangige Suche im Titel (findet auch ältere exakte Abschlussberichte zuverlässig)
                $titleMatches = AiKnowledgeBase::with('category')
                    ->tap($baseScope)
                    ->where(function ($q) use ($keywords) {
                        foreach ($keywords as $kw) {
                            $esc = addcslashes($kw, '%_\\');
                            $q->orWhere('title', 'like', '%' . $esc . '%');
                        }
                    })
                    ->latest('updated_at')
                    ->limit(20)
                    ->get($selectCols);

                // 2. Ergänzende Volltext-Kandidaten (bevorzugt Abschlussberichte & Ticket-Berichte)
                $bodyQuery = AiKnowledgeBase::with('category')
                    ->tap($baseScope)
                    ->where(function ($q) use ($keywords) {
                        foreach ($keywords as $kw) {
                            $esc = addcslashes($kw, '%_\\');
                            $q->orWhere('content', 'like', '%' . $esc . '%');
                        }
                    });

                if ($hasSourcePath) {
                    $bodyQuery->orderByRaw("CASE WHEN source_path LIKE 'Abschlussberichte/%' OR source_path LIKE 'ticket:%' THEN 1 ELSE 0 END DESC");
                }

                $bodyMatches = $bodyQuery
                    ->latest('updated_at')
                    ->limit(20)
                    ->get($selectCols);

                $candidates = $titleMatches->concat($bodyMatches)->unique('id')->values();
            } elseif ($deptName !== '') {
                $candidates = AiKnowledgeBase::with('category')
                    ->tap($baseScope)
                    ->whereHas('category', fn ($cq) => $cq->where('name', $deptName))
                    ->latest('updated_at')
                    ->limit(12)
                    ->get($selectCols);
            }

            if ($candidates->isEmpty()) {
                return '';
            }

            $minRawScore = !empty($keywords) ? 15 : 1;
            $scored = [];

            foreach ($candidates as $cand) {
                $raw = (string) $cand->content;
                if (str_starts_with($raw, '[TS_ENC_V1]:')) {
                    continue;
                }

                $rawScore = 0;
                $titleLower = mb_strtolower((string) $cand->title);
                $bodyLower = mb_strtolower(mb_substr($raw, 0, 5000));
                $srcPath = $hasSourcePath ? (string) ($cand->source_path ?? '') : '';
                $isCompletionReport = str_starts_with($srcPath, 'Abschlussberichte/')
                    || str_starts_with($srcPath, 'ticket:')
                    || str_contains($titleLower, 'abschlussbericht');

                $matchedKeywordCount = 0;
                foreach ($keywords as $kw) {
                    $hitThisKw = false;
                    if (str_contains($titleLower, $kw)) {
                        $rawScore += 16;
                        $hitThisKw = true;
                    }
                    if (str_contains($bodyLower, $kw)) {
                        $rawScore += 5;
                        $hitThisKw = true;
                    }
                    if ($hitThisKw) {
                        $matchedKeywordCount++;
                    }
                }

                if ($matchedKeywordCount >= 2) {
                    $rawScore += ($matchedKeywordCount - 1) * 6;
                }
                if (empty($keywords) && $deptName !== '' && strcasecmp((string) ($cand->category?->name ?? ''), $deptName) === 0) {
                    $rawScore += 5;
                }

                if ($rawScore < $minRawScore) {
                    continue;
                }

                $score = $rawScore;
                if ($isCompletionReport) {
                    $score += 6;
                }
                if ($deptName !== '' && strcasecmp((string) ($cand->category?->name ?? ''), $deptName) === 0) {
                    $score += 5;
                }

                $scored[] = [
                    'score' => $score,
                    'entry' => $cand,
                    'is_report' => $isCompletionReport,
                ];
            }

            if (empty($scored)) {
                return '';
            }

            usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
            $top = array_slice($scored, 0, max(1, $limit));

            $header = $includeDirectiveHeader
                ? "[BEREITS DOKUMENTIERTE ABSCHLUSSBERICHTE & WISSENSDATENBANK-TREFFER (VORAB-CHECK)]:\n"
                    . "WICHTIG: Zu dieser Anfrage liegt bereits dokumentiertes Systemwissen vor. Nutze diese Fakten direkt (bei Detailbedarf `brain_search`), damit Alina nichts doppelt erklären muss:"
                : '[SOFORT-KONTEXT AUS ZENTRALEM WISSENSGEHIRN & FRÜHEREN TICKETS]:';

            $lines = [$header];
            foreach ($top as $row) {
                /** @var AiKnowledgeBase $entry */
                $entry = $row['entry'];
                $snippet = self::extractRelevantSnippet((string) $entry->content, $keywords, $snippetMaxChars);
                $catLabel = $entry->category?->name ?? 'Wissen';
                $badge = $row['is_report'] ? "{$catLabel} · Abschlussbericht" : $catLabel;
                $lines[] = "- [{$badge}] {$entry->title}: {$snippet}";
            }

            return implode("\n", $lines);
        });
    }

    /**
     * Schneidet exakt den Absatz um das erste gefundene Keyword aus einem großen Bericht heraus (0 Token-Ballast).
     *
     * @param array<int, string> $keywords
     */
    public static function extractRelevantSnippet(string $markdown, array $keywords, int $maxLen = 340): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($markdown)) ?? '');
        if ($clean === '') {
            return '';
        }

        foreach ($keywords as $kw) {
            $pos = mb_stripos($clean, $kw);
            if ($pos !== false) {
                $start = max(0, $pos - 80);
                $slice = mb_substr($clean, $start, $maxLen);
                return ($start > 0 ? '...' : '') . trim($slice) . (mb_strlen($clean) > $start + $maxLen ? '...' : '');
            }
        }

        return Str::limit($clean, $maxLen);
    }

    /**
     * Digitalisiert einmalig alle Markdown-Dateien aus einem Verzeichnis (z. B. `Abschlussberichte/`),
     * sortiert sie chronologisch nach echtem Datum, importiert sie dublettenfrei in `AiKnowledgeBase`
     * und sichert einen portablen JSON-Snapshot für frische Test-/Stage-Datenbanken, damit das Verzeichnis
     * im Projekt-Root danach sauber aufgelöst werden kann!
     *
     * @return array{imported: int, snapshot_path: string, deduplication: array<string, int>}
     */
    public static function migrateDirectoryToBrainAndSnapshot(string $directoryPath): array
    {
        $entriesForSnapshot = [];
        $imported = 0;

        if (is_dir($directoryPath)) {
            $syncHelper = app(KnowledgeReportSyncService::class);
            $refFiles = new \ReflectionMethod($syncHelper, 'collectMarkdownFiles');
            $refFiles->setAccessible(true);
            $refCat = new \ReflectionMethod($syncHelper, 'resolveCategoryNameFromRelativePath');
            $refCat->setAccessible(true);
            $refTitle = new \ReflectionMethod($syncHelper, 'extractReportTitle');
            $refTitle->setAccessible(true);
            $refTags = new \ReflectionMethod($syncHelper, 'extractReportTags');
            $refTags->setAccessible(true);

            /** @var array<string, string> $mdFiles */
            $mdFiles = $refFiles->invoke($syncHelper, $directoryPath);

            // Chronologisch sortieren (älteste zuerst, neueste zuletzt -> saubere Timeline!)
            $sortable = [];
            foreach ($mdFiles as $relPath => $absPath) {
                $raw = @file_get_contents($absPath);
                if (!is_string($raw) || trim($raw) === '') {
                    continue;
                }
                $mtime = @filemtime($absPath) ?: time();
                $docDate = self::extractDateFromMarkdown($raw, $mtime);
                $sortable[] = [
                    'rel_path' => $relPath,
                    'content' => self::normalizeContent($raw),
                    'date' => $docDate,
                ];
            }

            usort($sortable, static fn (array $a, array $b): int => $a['date']->timestamp <=> $b['date']->timestamp);

            foreach ($sortable as $item) {
                $relPath = $item['rel_path'];
                $content = $item['content'];
                $catName = (string) $refCat->invoke($syncHelper, $relPath);
                $title = (string) $refTitle->invoke($syncHelper, $relPath, $content);
                /** @var array<int, string> $tags */
                $tags = $refTags->invoke($syncHelper, $relPath, $catName, $content);

                $sourcePath = KnowledgeReportSyncService::SOURCE_PREFIX . $relPath;

                self::upsertDeduplicatedKnowledge(
                    title: $title,
                    content: $content,
                    categoryName: $catName,
                    tags: $tags,
                    sourcePath: $sourcePath,
                    visibilityScope: AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
                    chronologicalDate: $item['date']
                );

                $entriesForSnapshot[] = [
                    'source_path' => $sourcePath,
                    'rel_path' => $relPath,
                    'category' => $catName,
                    'title' => $title,
                    'tags' => $tags,
                    'content' => $content,
                    'date' => $item['date']->toIso8601String(),
                ];
                $imported++;
            }
        }

        $dedup = self::deduplicateEntireKnowledgeBase();

        $snapshotAbs = database_path(self::SNAPSHOT_GIT_REL);
        if (!empty($entriesForSnapshot)) {
            $payload = json_encode([
                'generated_at' => now()->toIso8601String(),
                'count' => count($entriesForSnapshot),
                'entries' => $entriesForSnapshot,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            foreach ([$snapshotAbs, storage_path('app/' . self::SNAPSHOT_STORAGE_REL)] as $dest) {
                if (!is_dir(dirname($dest))) {
                    @mkdir(dirname($dest), 0775, true);
                }
                file_put_contents($dest, $payload);
            }
        }

        return [
            'imported' => $imported,
            'snapshot_path' => $snapshotAbs,
            'deduplication' => $dedup,
        ];
    }

    public static function extractDateFromMarkdown(string $content, int $fallbackTimestamp): Carbon
    {
        // Prüfe auf deutsches Datum im Header (z. B. 15.11.2026) oder ISO-Datum (2026-11-15)
        $head = mb_substr($content, 0, 800);
        if (preg_match('/\b(\d{2})\.(\d{2})\.(202\d)\b/', $head, $m)) {
            try {
                return Carbon::createFromFormat('d.m.Y H:i', "{$m[1]}.{$m[2]}.{$m[3]} 12:00");
            } catch (\Throwable) {
            }
        }
        if (preg_match('/\b(202\d-\d{2}-\d{2})\b/', $head, $m)) {
            try {
                return Carbon::parse($m[1] . ' 12:00:00');
            } catch (\Throwable) {
            }
        }

        return Carbon::createFromTimestamp($fallbackTimestamp);
    }

    /**
     * Speichert einen Meilenstein- oder Abteilungs-Abschlussbericht logisch und dublettenfrei
     * direkt in der zentralen Wissensdatenbank (`AiKnowledgeBase`) UND synchronisiert ihn
     * persistent in `knowledge_brain_reports_snapshot.json` (kein loser Ordner `Abschlussberichte/` nötig).
     *
     * @param array<int, string> $tags
     */
    public static function saveMilestoneReport(
        string $title,
        string $content,
        string $categoryName = 'System',
        array $tags = [],
        ?string $logicalReportPath = null,
        bool $syncSnapshot = true
    ): ?AiKnowledgeBase {
        $cleanCategory = trim($categoryName) !== '' ? trim($categoryName) : 'System';
        $relPath = $logicalReportPath !== null && trim($logicalReportPath) !== ''
            ? ltrim(str_replace('\\', '/', trim($logicalReportPath)), '/')
            : ($cleanCategory . '/Abschlussbericht_' . Str::slug($title, '_') . '.md');

        if (str_starts_with($relPath, KnowledgeReportSyncService::SOURCE_PREFIX)) {
            $relPath = substr($relPath, strlen(KnowledgeReportSyncService::SOURCE_PREFIX));
        }

        $sourcePath = KnowledgeReportSyncService::SOURCE_PREFIX . $relPath;
        $mergedTags = array_values(array_unique(array_filter(array_merge(['abschlussbericht', Str::slug($cleanCategory)], $tags))));

        $kb = self::upsertDeduplicatedKnowledge(
            title: $title,
            content: $content,
            categoryName: $cleanCategory,
            tags: $mergedTags,
            sourcePath: $sourcePath,
            visibilityScope: AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
            chronologicalDate: now()
        );

        if ($kb && $syncSnapshot && !app()->runningUnitTests()) {
            try {
                $snapshotAbs = database_path(self::SNAPSHOT_GIT_REL);
                $existingData = [];
                if (is_file($snapshotAbs)) {
                    $decoded = json_decode((string) @file_get_contents($snapshotAbs), true);
                    if (is_array($decoded) && is_array($decoded['entries'] ?? null)) {
                        $existingData = $decoded['entries'];
                    }
                }

                $newEntry = [
                    'source_path' => $sourcePath,
                    'rel_path' => $relPath,
                    'category' => $cleanCategory,
                    'title' => $kb->title,
                    'tags' => $mergedTags,
                    'content' => $kb->content,
                    'date' => now()->toIso8601String(),
                ];

                $replaced = false;
                foreach ($existingData as $idx => $row) {
                    if (is_array($row) && (($row['source_path'] ?? '') === $sourcePath || ($row['title'] ?? '') === $kb->title)) {
                        $existingData[$idx] = $newEntry;
                        $replaced = true;
                        break;
                    }
                }
                if (!$replaced) {
                    $existingData[] = $newEntry;
                }

                $payload = json_encode([
                    'generated_at' => now()->toIso8601String(),
                    'count' => count($existingData),
                    'entries' => array_values($existingData),
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                foreach ([$snapshotAbs, storage_path('app/' . self::SNAPSHOT_STORAGE_REL)] as $dest) {
                    if (!is_dir(dirname($dest))) {
                        @mkdir(dirname($dest), 0775, true);
                    }
                    @file_put_contents($dest, $payload);
                }
            } catch (\Throwable) {
            }
        }

        return $kb;
    }
}
