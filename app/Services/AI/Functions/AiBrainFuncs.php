<?php

namespace App\Services\AI\Functions;

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use App\Services\AI\AiAuthHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait AiBrainFuncs
{
    /** @var array<string, bool> */
    private static array $brainSchemaCache = [];

    protected static function cachedHasTable(string $table): bool
    {
        $key = DB::connection()->getDriverName() . ':' . DB::connection()->getDatabaseName() . ':tbl:' . $table;
        if (!array_key_exists($key, self::$brainSchemaCache)) {
            self::$brainSchemaCache[$key] = Schema::hasTable($table);
        }
        return self::$brainSchemaCache[$key];
    }

    protected static function cachedHasColumn(string $table, string $column): bool
    {
        $key = DB::connection()->getDriverName() . ':' . DB::connection()->getDatabaseName() . ':col:' . $table . '.' . $column;
        if (!array_key_exists($key, self::$brainSchemaCache)) {
            self::$brainSchemaCache[$key] = self::cachedHasTable($table) && Schema::hasColumn($table, $column);
        }
        return self::$brainSchemaCache[$key];
    }

    protected static function cachedHasMysqlFulltextIndex(): bool
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }
        $key = $driver . ':' . DB::connection()->getDatabaseName() . ':ft:ft_akb_title_content';
        if (!array_key_exists($key, self::$brainSchemaCache)) {
            try {
                $idx = DB::select("SHOW INDEX FROM `ai_knowledge_bases` WHERE Key_name IN ('ft_akb_title_content', 'akb_fulltext_title_content')");
                self::$brainSchemaCache[$key] = !empty($idx);
            } catch (\Throwable $ignore) {
                self::$brainSchemaCache[$key] = false;
            }
        }
        return self::$brainSchemaCache[$key];
    }
    public static function getAiBrainFuncsSchema(): array
    {
        return [
            [
                'name' => 'brain_save_entry',
                'description' => 'Speichert eine Tatsache, Notiz, generelles Wissen, App-Einstellung oder Passwort in deinem zentralen Langzeit-Gehirn (Wiki). Stichworte: Merke dir das, Notiere, Speicher das für immer, Neues Wissen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Kurzer, prägnanter Titel (z.B. "WLAN Passwort").',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Die eigentliche Information, die du dir merken sollst.',
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Relevante Tags zur Kategorisierung.',
                        ],
                    ],
                    'required' => ['title', 'content', 'tags'],
                ],
                'callable' => [self::class, 'executeSaveToBrain'],
            ],
            [
                'name' => 'brain_search',
                'description' => 'Durchsucht in Millisekunden das gesamte Unternehmens-Gehirn: Alle synchronisierten Abschlussberichte, Konzepte, Workflows und Prüfprotokolle (Abschlussberichte/*), manuelle Wiki-Dossiers UND den privaten Dokumenten-Workspace. Liefert token-sparend exakt die treffendsten Fach-Kapitel inklusive source_path, Kategorie und Tags.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff oder kombiniertes Fachthema (z.B. "RA3 Fokuswert xTool", "Marktanalyse Digitale Bestseller", "BKK firmus und Arbeitsamt").',
                        ],
                    ],
                    'required' => ['query'],
                ],
                'callable' => [self::class, 'executeSearchBrain'],
            ],
            [
                'name' => 'brain_update_entry',
                'description' => 'Aktualisiert einen fehlerhaften oder veralteten manuellen Eintrag in deinem Wiki Langzeit-Gehirn. Stichworte: Ändere das in meinem Gehirn, Update diesen Fakt, Info austauschen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff, um den alten Eintrag zu finden (z.B. der exakte bisherige Text oder der Titel).',
                        ],
                        'new_content' => [
                            'type' => 'string',
                            'description' => 'Der neue, korrigierte Inhalt, der gespeichert werden soll.',
                        ],
                    ],
                    'required' => ['search_query', 'new_content'],
                ],
                'callable' => [self::class, 'executeUpdateBrainEntry'],
            ],
            [
                'name' => 'brain_delete_entry',
                'description' => 'Löscht eine gespeicherte manuelle Information vollständig aus deinem Wiki Erinnerungs-Gehirn. Stichworte: Vergiss das, Entferne Notiz, Brain Reset.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff, um den zu löschenden Eintrag zu finden (z.B. der exakte Inhalt oder Titel).',
                        ],
                    ],
                    'required' => ['search_query'],
                ],
                'callable' => [self::class, 'executeDeleteBrainEntry'],
            ],
        ];
    }

    public static function executeUpdateBrainEntry(array $args): array
    {
        if (!AiAuthHelper::isAdmin()) {
            return [
                'status' => 'error',
                'message' => 'Zugriff verweigert: Das Langzeitgedächtnis (Brain) ist ausschließlich der Geschäftsführung (Admin) vorbehalten.',
            ];
        }

        try {
            $query = trim((string) ($args['search_query'] ?? ''));
            $newContent = trim((string) ($args['new_content'] ?? ''));

            if ($query === '' || $newContent === '') {
                return ['status' => 'error', 'message' => 'Suchbegriff und neuer Inhalt sind erforderlich.'];
            }

            $escapedQuery = addcslashes($query, '%_\\');
            $hasSourcePathCol = Schema::hasColumn('ai_knowledge_bases', 'source_path');

            $baseQuery = AiKnowledgeBase::query();
            if ($hasSourcePathCol) {
                $baseQuery->whereNull('source_path');
            }

            if (mb_strlen($query) < 4) {
                $kb = (clone $baseQuery)->where('title', $query)->first();
            } else {
                $kb = (clone $baseQuery)->where('title', 'like', "%{$escapedQuery}%")->first();
                if (!$kb) {
                    $kb = (clone $baseQuery)->where('content', 'like', "%{$escapedQuery}%")->first();
                }
            }

            if ($kb) {
                $kb->content = mb_substr($newContent, 0, 50000);
                $kb->save();
                return ['status' => 'success', 'message' => "Der Wiki-Eintrag '{$kb->title}' wurde erfolgreich aktualisiert."];
            }

            return ['status' => 'error', 'message' => 'Kein passender manueller Eintrag im Wiki gefunden.'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Update fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteBrainEntry(array $args): array
    {
        if (!AiAuthHelper::isAdmin()) {
            return [
                'status' => 'error',
                'message' => 'Zugriff verweigert: Das Langzeitgedächtnis (Brain) ist ausschließlich der Geschäftsführung (Admin) vorbehalten.',
            ];
        }

        try {
            $query = trim((string) ($args['search_query'] ?? ''));

            if ($query === '') {
                return ['status' => 'error', 'message' => 'Suchbegriff zum Löschen ist erforderlich.'];
            }

            $escapedQuery = addcslashes($query, '%_\\');
            $hasSourcePathCol = Schema::hasColumn('ai_knowledge_bases', 'source_path');

            $baseQuery = AiKnowledgeBase::query();
            if ($hasSourcePathCol) {
                $baseQuery->whereNull('source_path');
            }

            if (mb_strlen($query) < 4) {
                $kb = (clone $baseQuery)->where('title', $query)->first();
            } else {
                $kb = (clone $baseQuery)->where('title', 'like', "%{$escapedQuery}%")->first();
                if (!$kb) {
                    $kb = (clone $baseQuery)->where('content', 'like', "%{$escapedQuery}%")->first();
                }
            }

            if ($kb) {
                $title = $kb->title;
                $kb->delete();
                return ['status' => 'success', 'message' => "Der Wiki-Eintrag '{$title}' wurde erfolgreich und permanent gelöscht."];
            }

            return ['status' => 'error', 'message' => 'Kein passender manueller Eintrag im Wiki gefunden, der gelöscht werden könnte.'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Löschen fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    public static function executeSaveToBrain(array $args): array
    {
        if (!AiAuthHelper::isAdmin()) {
            return [
                'status' => 'error',
                'message' => 'Zugriff verweigert: Das Langzeitgedächtnis (Brain) ist ausschließlich der Geschäftsführung (Admin) vorbehalten.',
            ];
        }

        try {
            if (empty($args['title']) || empty($args['content'])) {
                return ['status' => 'error', 'message' => 'Titel und Inhalt sind für das Speichern erforderlich.'];
            }

            $title = trim(mb_substr((string) $args['title'], 0, 255));
            $content = trim(mb_substr((string) $args['content'], 0, 50000));
            $tags = is_array($args['tags'] ?? null) ? $args['tags'] : [];

            $kb = \App\Services\AI\CentralBrainService::upsertDeduplicatedKnowledge(
                title: $title,
                content: $content,
                categoryName: 'AI Memory',
                tags: array_merge(['ai_memory', 'auto_saved'], $tags),
                sourcePath: null,
                visibilityScope: AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE
            );

            if (!$kb) {
                return ['status' => 'error', 'message' => 'Wissenseintrag konnte nicht gespeichert werden.'];
            }

            return [
                'status' => 'success',
                'message' => "Die Information '{$kb->title}' wurde dublettenfrei im zentralen Langzeitgedächtnis gespeichert.",
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Speichern: ' . $e->getMessage()];
        }
    }

    public static function executeSearchBrain(array $args): array
    {
        if (!AiAuthHelper::isAdmin()) {
            return [
                'status' => 'error',
                'message' => 'Zugriff verweigert: Das Langzeitgedächtnis (Brain) ist ausschließlich der Geschäftsführung (Admin) vorbehalten.',
            ];
        }

        try {
            $queryStr = trim((string) ($args['query'] ?? ''));
            if ($queryStr === '') {
                return ['status' => 'error', 'message' => 'Es wurde kein Suchbegriff angegeben.'];
            }

            // Cache-Layer: Häufige erfolgreiche Suchanfragen 300 Sekunden cachen (mit Versions-Invalidierung)
            $cacheKey = 'ai_brain_search_' . md5($queryStr);
            $kbVersion = (int) Cache::get('ai_brain_search_version', 1);
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ($cached['_kb_ver'] ?? null) === $kbVersion) {
                unset($cached['_kb_ver']);
                return $cached;
            }

            $freshResult = self::performSearchBrainDirect($queryStr);
            if (($freshResult['status'] ?? '') === 'success') {
                $toStore = $freshResult;
                $toStore['_kb_ver'] = $kbVersion;
                Cache::put($cacheKey, $toStore, 300);
            }

            return $freshResult;
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Durchsuchen des Gehirns: ' . $e->getMessage()];
        }
    }

    /**
     * Führt die Suche im Langzeit-Gehirn und Workspace aus.
     */
    protected static function performSearchBrainDirect(string $queryStr): array
    {
        try {
            // 1. Konversations-Füllwörter bereinigen
            $fillers = [
                'dokumente von der', 'dokumente vom', 'dokumente von', 'dokumente zu',
                'dokumente über', 'unterlagen von der', 'unterlagen vom', 'unterlagen von',
                'dateien von der', 'dateien vom', 'dateien von', 'zeigen', 'finde', 'suche',
                'bitte', 'zeig mir', 'sage mir',
            ];
            $cleaned = $queryStr;
            foreach ($fillers as $filler) {
                $cleaned = preg_replace('/\b' . preg_quote($filler, '/') . '\b/iu', ' ', (string) $cleaned) ?? $cleaned;
            }
            $cleaned = trim(preg_replace('/\s+/', ' ', (string) $cleaned) ?? $cleaned);
            if ($cleaned === '') {
                $cleaned = $queryStr;
            }

            // 2. Themen-Segmente & präzise Einzel-Schlüsselwörter extrahieren
            $segments = preg_split('/\s*(?:\bund\b|\boder\b|\band\b|\bor\b|[,&+])\s*/iu', $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (empty($segments)) {
                $segments = [$queryStr];
            }

            $keywords = self::extractSearchKeywords($cleaned, $segments);

            // 3. Wissensdatenbank (inkl. aller synchronisierten Abschlussberichte/) mit Relevanz-Ranking durchsuchen
            $kbList = [];
            if (class_exists(AiKnowledgeBase::class) && self::cachedHasTable('ai_knowledge_bases')) {
                $rankedEntries = self::queryAndRankKnowledgeBase($queryStr, $cleaned, $segments, $keywords, 6);

                foreach ($rankedEntries as $kb) {
                    $rawKbContent = (string) $kb->content;
                    if (str_starts_with($rawKbContent, '[TS_ENC_V1]:')) {
                        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
                            continue;
                        }
                        $rawKbContent = method_exists(self::class, 'decryptTopSecretPayload')
                            ? self::decryptTopSecretPayload($rawKbContent)
                            : $rawKbContent;
                    }
                    $sliced = self::sliceRelevantMarkdownChapters(
                        $rawKbContent,
                        $cleaned,
                        $keywords,
                        1800,
                        2400
                    );

                    $kbList[] = [
                        'type' => 'knowledge_base',
                        'title' => $kb->title,
                        'source_path' => $kb->source_path ?? null,
                        'category' => $kb->category ? $kb->category->name : 'Allgemein',
                        'tags' => $kb->tags->pluck('name')->implode(', '),
                        'matched_sections' => $sliced['matched_sections'],
                        'content_sliced' => $sliced['sliced'],
                        'content' => $sliced['content'],
                        'date' => $kb->updated_at
                            ? $kb->updated_at->format('Y-m-d')
                            : ($kb->created_at ? $kb->created_at->format('Y-m-d') : null),
                    ];
                }
            }

            // 4. Privaten Dokumenten-Workspace durchsuchen
            $wsList = [];
            if (class_exists(\App\Models\Ai\AiWorkspaceDocument::class) && self::cachedHasTable('ai_workspace_documents')) {
                $wsDocs = \App\Models\Ai\AiWorkspaceDocument::search($queryStr)
                    ->orderBy('extracted_date', 'desc')
                    ->limit(10)
                    ->get();

                foreach ($wsDocs as $doc) {
                    $wsList[] = [
                        'type' => 'workspace_file',
                        'filename' => $doc->filename,
                        'path' => $doc->file_path,
                        'title' => $doc->title,
                        'category' => $doc->category,
                        'purpose' => $doc->purpose,
                        'summary' => $doc->summary,
                        'date' => $doc->extracted_date ? $doc->extracted_date->format('Y-m-d') : null,
                        'size' => $doc->file_size,
                    ];
                }
            }

            $totalCount = count($kbList) + count($wsList);

            if ($totalCount === 0) {
                return [
                    'status' => 'empty',
                    'message' => 'Ich habe in der Wissensdatenbank und im Workspace zu "' . $queryStr . '" keine passenden Einträge gefunden.',
                    'knowledge_base' => [],
                    'workspace_documents' => [],
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Erfolgreich gefunden: ' . count($kbList) . ' Dossiers in der Wissensdatenbank und ' . count($wsList) . ' Dokumente im privaten Workspace.',
                'knowledge_base' => $kbList,
                'workspace_documents' => $wsList,
                'results' => array_merge($kbList, $wsList),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Durchsuchen des Gehirns: ' . $e->getMessage()];
        }
    }

    /**
     * Extrahiert relevante Fachbegriffe und bekannte Domänen-Synonyme ohne Füllwörter.
     *
     * @param array<int, string> $segments
     * @return array<int, string>
     */
    protected static function extractSearchKeywords(string $cleanedQuery, array $segments): array
    {
        $stopwords = [
            'der' => true, 'die' => true, 'das' => true, 'den' => true, 'dem' => true, 'des' => true,
            'ein' => true, 'eine' => true, 'einer' => true, 'einem' => true, 'einen' => true,
            'und' => true, 'oder' => true, 'aber' => true, 'wie' => true, 'was' => true, 'wer' => true,
            'wo' => true, 'wann' => true, 'warum' => true, 'ist' => true, 'sind' => true, 'war' => true,
            'wird' => true, 'werden' => true, 'mit' => true, 'von' => true, 'vom' => true, 'zum' => true,
            'zur' => true, 'aus' => true, 'auf' => true, 'für' => true, 'fuer' => true, 'über' => true,
            'ueber' => true, 'nach' => true, 'bei' => true, 'im' => true, 'in' => true, 'am' => true,
            'an' => true, 'zu' => true, 'uns' => true, 'unsere' => true, 'unserem' => true, 'alle' => true,
        ];

        $rawTokens = preg_split('/[\s,;:\(\)\[\]\{\}"\'`!?]+/u', $cleanedQuery, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = [];

        foreach ($rawTokens as $tok) {
            $trimmed = trim($tok, " \t\n\r\0\x0B.");
            if ($trimmed === '' || mb_strlen($trimmed) < 2) {
                continue;
            }
            $low = mb_strtolower($trimmed);
            if (isset($stopwords[$low])) {
                continue;
            }
            $keywords[$low] = $trimmed;
        }

        foreach ($segments as $seg) {
            $segTrim = trim($seg);
            $low = mb_strtolower($segTrim);
            if ($segTrim === '' || mb_strlen($segTrim) < 2) {
                continue;
            }

            if (str_contains($low, 'arbeitsamt') || str_contains($low, 'arbeitsagentur')) {
                foreach (['Agentur für Arbeit', 'Bundesagentur', 'Gründungszuschuss', 'Existenzgründung'] as $syn) {
                    $keywords[mb_strtolower($syn)] = $syn;
                }
            } elseif (str_contains($low, 'agentur')) {
                foreach (['Arbeitsamt', 'Bundesagentur', 'Gründungszuschuss'] as $syn) {
                    $keywords[mb_strtolower($syn)] = $syn;
                }
            } elseif (str_contains($low, 'bkk')) {
                foreach (['firmus', 'Krankengeld', 'Sozialrecht'] as $syn) {
                    $keywords[mb_strtolower($syn)] = $syn;
                }
            } elseif (str_contains($low, 'krankschreibung') || str_contains($low, 'krankmeldung') || $low === 'au' || $low === 'eau') {
                foreach (['Krankschreibungen', 'Arbeitsunfähigkeit', 'Aussteuerung', 'Dorothea Jung'] as $syn) {
                    $keywords[mb_strtolower($syn)] = $syn;
                }
            } elseif (
                str_contains($low, 'labor')
                || str_contains($low, 'blut')
                || str_contains($low, 'hormon')
                || str_contains($low, 'endokrinolog')
                || str_contains($low, 'chromosom')
                || str_contains($low, 'attest')
            ) {
                foreach (['Endokrinologikum', 'Laborbefunde', 'Humangenetik', 'Krusche'] as $syn) {
                    $keywords[mb_strtolower($syn)] = $syn;
                }
            }
        }

        if (empty($keywords)) {
            $keywords[mb_strtolower($cleanedQuery)] = $cleanedQuery;
        }

        return array_slice(array_values($keywords), 0, 14);
    }

    /**
     * Erzeugt einen sicheren MySQL-FULLTEXT-Suchausdruck im Boolean Mode ohne Operator-Injektion.
     *
     * @param array<int, string> $keywords
     */
    protected static function buildMysqlBooleanFulltextQuery(array $keywords): string
    {
        $ftTokens = [];

        foreach ($keywords as $kw) {
            $parts = preg_split('/[^a-zA-Z0-9äöüÄÖÜß]+/u', $kw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($parts as $part) {
                if (mb_strlen($part) >= 3) {
                    $ftTokens[mb_strtolower($part)] = $part . '*';
                }
            }
        }

        return implode(' ', array_slice(array_values($ftTokens), 0, 12));
    }

    /**
     * Führt eine schnelle Kandidaten-Abfrage (MySQL FULLTEXT + Tag/Kategorie/Pfad-Boost bzw. SQLite Multi-LIKE)
     * und deterministisches Relevanz-Scoring aus.
     *
     * @param array<int, string> $segments
     * @param array<int, string> $keywords
     * @return array<int, AiKnowledgeBase>
     */
    protected static function queryAndRankKnowledgeBase(
        string $rawQuery,
        string $cleanedQuery,
        array $segments,
        array $keywords,
        int $limit = 6
    ): array {
        $driver = DB::connection()->getDriverName();
        $isMysqlFamily = in_array($driver, ['mysql', 'mariadb'], true);
        $hasSourcePath = self::cachedHasColumn('ai_knowledge_bases', 'source_path');
        $hasVisibilityScope = self::cachedHasColumn('ai_knowledge_bases', 'visibility_scope');
        $isAdminActor = \App\Services\AI\AiAuthHelper::isAdmin();
        $applyVisibilityGuard = static function ($query) use ($isAdminActor, $hasVisibilityScope): void {
            if ($isAdminActor) {
                return;
            }
            $query->whereDoesntHave('category', function ($cq) {
                $cq->whereIn('slug', \App\Livewire\Shop\Ai\AiKnowledgeBase::ADMIN_ONLY_CATEGORY_SLUGS);
            });
            if ($hasVisibilityScope) {
                $query->where(function ($vq) {
                    $vq->whereNull('visibility_scope')
                       ->orWhere('visibility_scope', '!=', AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE);
                });
            }
        };
        $booleanFt = $isMysqlFamily ? self::buildMysqlBooleanFulltextQuery($keywords) : '';
        $hasFulltextIndex = $isMysqlFamily && $booleanFt !== '' && self::cachedHasMysqlFulltextIndex();

        $candidates = collect();

        if ($hasFulltextIndex) {
            // Reiner FULLTEXT-Index-Scan ohne OR-Subqueries (nutzt ft_akb_title_content direkt in < 3 ms)
            $ftQuery = AiKnowledgeBase::with(['category', 'tags'])
                ->where('is_published', true);
            $applyVisibilityGuard($ftQuery);
            $candidates = $ftQuery
                ->select('ai_knowledge_bases.*')
                ->selectRaw('MATCH(`title`, `content`) AGAINST (? IN BOOLEAN MODE) AS `ft_score`', [$booleanFt])
                ->whereRaw('MATCH(`title`, `content`) AGAINST (? IN BOOLEAN MODE)', [$booleanFt])
                ->orderByDesc('ft_score')
                ->limit(14)
                ->get();

            $needsMetaFallback = $candidates->count() < $limit;
            if (!$needsMetaFallback) {
                foreach ($keywords as $kw) {
                    if (mb_strlen($kw) < 3 || str_starts_with($kw, '/')) {
                        $needsMetaFallback = true;
                        break;
                    }
                }
            }

            if ($needsMetaFallback) {
                $existingIds = $candidates->pluck('id')->all();
                $metaQuery = AiKnowledgeBase::with(['category', 'tags'])
                    ->where('is_published', true);
                $applyVisibilityGuard($metaQuery);
                $metaHits = $metaQuery
                    ->when(!empty($existingIds), fn ($q) => $q->whereNotIn('id', $existingIds))
                    ->where(function ($mq) use ($cleanedQuery, $keywords, $hasSourcePath) {
                        $escClean = addcslashes($cleanedQuery, '%_\\');
                        $mq->where('title', 'like', "%{$escClean}%")
                            ->orWhere('content', 'like', "%{$escClean}%");
                        if ($hasSourcePath) {
                            $mq->orWhere('source_path', 'like', "%{$escClean}%");
                        }
                        foreach (array_slice($keywords, 0, 5) as $kw) {
                            $escKw = addcslashes($kw, '%_\\');
                            $mq->orWhere('title', 'like', "%{$escKw}%")
                                ->orWhere('content', 'like', "%{$escKw}%")
                                ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', "%{$escKw}%"))
                                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$escKw}%"));
                            if ($hasSourcePath) {
                                $mq->orWhere('source_path', 'like', "%{$escKw}%");
                            }
                        }
                    })
                    ->limit(10)
                    ->get();

                if ($metaHits->isNotEmpty()) {
                    $candidates = $candidates->concat($metaHits);
                }
            }
        }

        if (!$hasFulltextIndex || $candidates->isEmpty()) {
            $kbQuery = AiKnowledgeBase::with(['category', 'tags'])->where('is_published', true);
            $applyVisibilityGuard($kbQuery);
            $kbQuery->where(function ($rootQ) use ($rawQuery, $cleanedQuery, $segments, $keywords, $hasSourcePath) {
                foreach (array_unique([$rawQuery, $cleanedQuery]) as $phrase) {
                    $escPhrase = addcslashes($phrase, '%_\\');
                    if ($escPhrase === '') {
                        continue;
                    }
                    $rootQ->orWhere('title', 'like', "%{$escPhrase}%")
                        ->orWhere('content', 'like', "%{$escPhrase}%");
                    if ($hasSourcePath) {
                        $rootQ->orWhere('source_path', 'like', "%{$escPhrase}%");
                    }
                }

                foreach (array_unique(array_merge($segments, $keywords)) as $term) {
                    $termTrim = trim((string) $term);
                    if (mb_strlen($termTrim) < 2) {
                        continue;
                    }
                    $escTerm = addcslashes($termTrim, '%_\\');
                    $rootQ->orWhere('title', 'like', "%{$escTerm}%")
                        ->orWhere('content', 'like', "%{$escTerm}%")
                        ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', "%{$escTerm}%"))
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$escTerm}%"));

                    if ($hasSourcePath) {
                        $rootQ->orWhere('source_path', 'like', "%{$escTerm}%");
                    }
                }
            });

            $fallbackCandidates = $kbQuery->orderByDesc('updated_at')->limit(30)->get();
            if ($fallbackCandidates->isNotEmpty()) {
                $candidates = $candidates->concat($fallbackCandidates)->unique('id')->values();
            }
        }

        if ($candidates->isEmpty()) {
            return [];
        }

        $cleanLower = mb_strtolower($cleanedQuery);
        $scored = [];

        foreach ($candidates as $entry) {
            $titleLow = mb_strtolower((string) $entry->title);
            $sourceLow = mb_strtolower((string) ($entry->source_path ?? ''));
            $catLow = mb_strtolower((string) ($entry->category?->name ?? ''));
            $tagsLow = $entry->tags->map(fn ($t) => mb_strtolower((string) $t->name))->all();
            $contentLow = mb_strtolower((string) $entry->content);

            $score = (float) ($entry->getAttribute('ft_score') ?? 0.0) * 12.0;

            if ($cleanLower !== '' && mb_strlen($cleanLower) >= 3) {
                if (str_contains($titleLow, $cleanLower)) {
                    $score += 90.0;
                }
                if ($sourceLow !== '' && str_contains($sourceLow, $cleanLower)) {
                    $score += 55.0;
                }
                if (str_contains($contentLow, $cleanLower)) {
                    $score += 45.0;
                }
            }

            $matchedDistinctKeywords = 0;
            foreach ($keywords as $kw) {
                $kwLow = mb_strtolower($kw);
                if ($kwLow === '') {
                    continue;
                }

                $kwMatchedHere = false;
                if (str_contains($titleLow, $kwLow)) {
                    $score += 32.0;
                    $kwMatchedHere = true;
                }
                if ($sourceLow !== '' && str_contains($sourceLow, $kwLow)) {
                    $score += 20.0;
                    $kwMatchedHere = true;
                }
                if ($catLow !== '' && str_contains($catLow, $kwLow)) {
                    $score += 18.0;
                    $kwMatchedHere = true;
                }
                foreach ($tagsLow as $tName) {
                    if ($tName === $kwLow || str_contains($tName, $kwLow)) {
                        $score += 24.0;
                        $kwMatchedHere = true;
                        break;
                    }
                }
                if (str_contains($contentLow, $kwLow)) {
                    $occ = min(6, substr_count($contentLow, $kwLow));
                    $score += 10.0 + ($occ * 1.5);
                    $kwMatchedHere = true;
                }

                if ($kwMatchedHere) {
                    $matchedDistinctKeywords++;
                }
            }

            // Multi-Begriff-Abdeckung stark belohnen, damit der präziseste Fachbericht garantiert auf Platz 1 landet
            if ($matchedDistinctKeywords > 1) {
                $score += ($matchedDistinctKeywords - 1) * 28.0;
            }

            $scored[] = [
                'model' => $entry,
                'score' => $score,
            ];
        }

        usort($scored, static function (array $a, array $b): int {
            if (abs($b['score'] - $a['score']) > 0.001) {
                return $b['score'] <=> $a['score'];
            }
            return strcmp((string) $a['model']->title, (string) $b['model']->title);
        });

        return array_map(
            static fn (array $row): AiKnowledgeBase => $row['model'],
            array_slice($scored, 0, max(1, $limit))
        );
    }

    /**
     * Zerlegt lange Markdown-Berichte (> 1.800 Zeichen) entlang von ##- und ###-Überschriften,
     * bewertet jedes Kapitel gegen die Suchbegriffe und gibt maximal die Top-3 relevantesten
     * Fach-Abschnitte kompakt (<= 2.400 Zeichen) zurück.
     *
     * @param array<int, string> $keywords
     * @return array{content: string, sliced: bool, matched_sections: array<int, string>}
     */
    public static function sliceRelevantMarkdownChapters(
        string $markdown,
        string $cleanedQuery,
        array $keywords,
        int $sliceThreshold = 1800,
        int $maxOutputChars = 2400
    ): array {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
        if (mb_strlen($normalized) <= $sliceThreshold) {
            return [
                'content' => $normalized,
                'sliced' => false,
                'matched_sections' => [],
            ];
        }

        $parts = preg_split('/^(#{2,3}\s+.+)$/m', $normalized, -1, PREG_SPLIT_DELIM_CAPTURE);
        $sections = [];

        if (is_array($parts) && count($parts) > 1) {
            $preamble = trim((string) $parts[0]);
            if ($preamble !== '') {
                $sections[] = [
                    'index' => 0,
                    'heading' => '## Überblick',
                    'clean_heading' => 'Überblick',
                    'body' => $preamble,
                ];
            }

            $secIdx = 1;
            for ($i = 1, $n = count($parts); $i < $n; $i += 2) {
                $headingLine = trim((string) $parts[$i]);
                $bodyText = isset($parts[$i + 1]) ? trim((string) $parts[$i + 1]) : '';
                $cleanHeading = trim(preg_replace('/^#{2,3}\s+/', '', $headingLine) ?? $headingLine);
                $sections[] = [
                    'index' => $secIdx++,
                    'heading' => $headingLine,
                    'clean_heading' => $cleanHeading,
                    'body' => $bodyText,
                ];
            }
        } else {
            // Fallback für lange Fließtexte ohne ##-Überschriften: 900-Zeichen-Absätze bilden
            $paragraphs = preg_split('/\n{2,}/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [$normalized];
            $buf = '';
            $secIdx = 0;
            foreach ($paragraphs as $p) {
                if ($buf !== '' && (mb_strlen($buf) + mb_strlen($p) + 2) > 900) {
                    $sections[] = [
                        'index' => $secIdx++,
                        'heading' => '## Abschnitt ' . $secIdx,
                        'clean_heading' => 'Abschnitt ' . $secIdx,
                        'body' => trim($buf),
                    ];
                    $buf = $p;
                } else {
                    $buf = $buf === '' ? $p : ($buf . "\n\n" . $p);
                }
            }
            if (trim($buf) !== '') {
                $sections[] = [
                    'index' => $secIdx++,
                    'heading' => '## Abschnitt ' . $secIdx,
                    'clean_heading' => 'Abschnitt ' . $secIdx,
                    'body' => trim($buf),
                ];
            }
        }

        $queryLow = mb_strtolower(trim($cleanedQuery));
        foreach ($sections as &$sec) {
            $hLow = mb_strtolower($sec['heading']);
            $bLow = mb_strtolower($sec['body']);
            $score = 0.0;
            $distinctHits = 0;

            if ($queryLow !== '' && mb_strlen($queryLow) >= 3) {
                if (str_contains($hLow, $queryLow)) {
                    $score += 80.0;
                }
                if (str_contains($bLow, $queryLow)) {
                    $score += 55.0;
                }
            }

            foreach ($keywords as $kw) {
                $kwLow = mb_strtolower(trim($kw));
                if ($kwLow === '') {
                    continue;
                }

                $hit = false;
                if (str_contains($hLow, $kwLow)) {
                    $score += 26.0;
                    $hit = true;
                }
                if (str_contains($bLow, $kwLow)) {
                    $occ = min(5, substr_count($bLow, $kwLow));
                    $score += 12.0 + ($occ * 2.0);
                    $hit = true;
                }
                if ($hit) {
                    $distinctHits++;
                }
            }

            if ($distinctHits > 1) {
                $score *= (1.0 + 0.25 * ($distinctHits - 1));
            }

            $sec['score'] = $score;
        }
        unset($sec);

        $positiveSections = array_values(array_filter($sections, static fn (array $s): bool => $s['score'] > 0.0));
        if (!empty($positiveSections)) {
            usort($positiveSections, static function (array $a, array $b): int {
                if (abs($b['score'] - $a['score']) > 0.001) {
                    return $b['score'] <=> $a['score'];
                }
                return $a['index'] <=> $b['index'];
            });
            $selected = array_slice($positiveSections, 0, 3);
        } else {
            $selected = array_slice($sections, 0, 3);
        }

        $countSelected = max(1, count($selected));
        $separatorOverhead = ($countSelected - 1) * 2;
        $perSectionBudget = max(320, intdiv($maxOutputChars - $separatorOverhead, $countSelected));

        $renderedBlocks = [];
        $matchedHeadings = [];

        foreach ($selected as $sec) {
            $matchedHeadings[] = $sec['clean_heading'];
            $fullBlock = $sec['body'] !== ''
                ? ($sec['heading'] . "\n" . $sec['body'])
                : $sec['heading'];

            if (mb_strlen($fullBlock) <= $perSectionBudget) {
                $renderedBlocks[] = $fullBlock;
                continue;
            }

            $headingPrefix = $sec['heading'] . "\n";
            $bodyBudget = max(120, $perSectionBudget - mb_strlen($headingPrefix));
            $windowedBody = self::extractKeywordCenteredSnippet($sec['body'], $cleanedQuery, $keywords, $bodyBudget);
            $renderedBlocks[] = $headingPrefix . $windowedBody;
        }

        $joined = implode("\n\n", $renderedBlocks);
        if (mb_strlen($joined) > $maxOutputChars) {
            $joined = rtrim(mb_substr($joined, 0, $maxOutputChars - 3)) . '...';
        }

        return [
            'content' => $joined,
            'sliced' => true,
            'matched_sections' => $matchedHeadings,
        ];
    }

    /**
     * Schneidet aus einem überlangen Kapitel ein zentriertes Textfenster rund um den ersten Treffer aus.
     *
     * @param array<int, string> $keywords
     */
    protected static function extractKeywordCenteredSnippet(
        string $body,
        string $cleanedQuery,
        array $keywords,
        int $budgetChars
    ): string {
        if (mb_strlen($body) <= $budgetChars) {
            return $body;
        }

        $bodyLow = mb_strtolower($body);
        $matchPos = null;

        $qLow = mb_strtolower(trim($cleanedQuery));
        if ($qLow !== '' && mb_strlen($qLow) >= 3) {
            $pos = mb_strpos($bodyLow, $qLow);
            if ($pos !== false) {
                $matchPos = $pos;
            }
        }

        if ($matchPos === null) {
            foreach ($keywords as $kw) {
                $kwLow = mb_strtolower(trim($kw));
                if ($kwLow === '') {
                    continue;
                }
                $pos = mb_strpos($bodyLow, $kwLow);
                if ($pos !== false) {
                    $matchPos = $pos;
                    break;
                }
            }
        }

        if ($matchPos === null || $matchPos < intdiv($budgetChars, 2)) {
            return rtrim(mb_substr($body, 0, max(10, $budgetChars - 3))) . '...';
        }

        $totalLen = mb_strlen($body);
        $innerBudget = max(20, $budgetChars - 6);
        $start = max(0, $matchPos - intdiv($innerBudget, 3));
        if ($start + $innerBudget > $totalLen) {
            $start = max(0, $totalLen - $innerBudget);
        }

        $slice = trim(mb_substr($body, $start, $innerBudget));
        $prefix = $start > 0 ? '...' : '';
        $suffix = ($start + $innerBudget) < $totalLen ? '...' : '';

        return $prefix . $slice . $suffix;
    }
}
