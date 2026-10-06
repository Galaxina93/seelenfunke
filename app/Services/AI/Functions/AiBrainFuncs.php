<?php

namespace App\Services\AI\Functions;

use App\Models\Ai\AiKnowledgeBase;

trait AiBrainFuncs
{
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
                            'description' => 'Kurzer, prägnanter Titel (z.B. "WLAN Passwort").'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Die eigentliche Information, die du dir merken sollst.'
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Relevante Tags zur Kategorisierung.'
                        ]
                    ],
                    'required' => ['title', 'content', 'tags']
                ],
                'callable' => [self::class, 'executeSaveToBrain']
            ],
            [
                'name' => 'brain_search',
                'description' => 'Durchsucht blitzschnell das gesamte Gehirn: Die Wissensdatenbank (Dossiers, Chronologien, Leitfäden) UND den privaten Dokumenten-Workspace (alle vertraulichen Akten, Nachweise, Anträge, Verträge). Unterstützt kombinierte Themen wie "BKK firmus und Arbeitsamt", "Gründungszuschuss" oder "Krankengeld". Liefert sofort vollständige Fakten, Pfade und Zwecke.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff oder kombiniertes Thema (z.B. "BKK firmus und Arbeitsamt", "Gründungszuschuss", "Krankengeld").'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeSearchBrain']
            ],
            [
                'name' => 'brain_update_entry',
                'description' => 'Aktualisiert einen fehlerhaften oder veralteten Eintrag in deinem Wiki Langzeit-Gehirn. Stichworte: Ändere das in meinem Gehirn, Update diesen Fakt, Info austauschen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff, um den alten Eintrag zu finden (z.B. der exakte bisherige Text oder der Titel).'
                        ],
                        'new_content' => [
                            'type' => 'string',
                            'description' => 'Der neue, korrigierte Inhalt, der gespeichert werden soll.'
                        ]
                    ],
                    'required' => ['search_query', 'new_content']
                ],
                'callable' => [self::class, 'executeUpdateBrainEntry']
            ],
            [
                'name' => 'brain_delete_entry',
                'description' => 'Löscht eine gespeicherte Information vollständig aus deinem Wiki Erinnerungs-Gehirn. Stichworte: Vergiss das, Entferne Notiz, Brain Reset.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff, um den zu löschenden Eintrag zu finden (z.B. der exakte Inhalt oder Titel).'
                        ]
                    ],
                    'required' => ['search_query']
                ],
                'callable' => [self::class, 'executeDeleteBrainEntry']
            ]
        ];
    }

    public static function executeUpdateBrainEntry(array $args)
    {
        try {
            $query = strtolower(trim($args['search_query'] ?? ''));
            $newContent = $args['new_content'] ?? '';

            if (empty($query) || empty($newContent)) {
                return ['status' => 'error', 'message' => 'Suchbegriff und neuer Inhalt sind erforderlich.'];
            }

            // Wiki Update
            $kb = AiKnowledgeBase::where('title', 'like', "%{$query}%")
                               ->orWhere('content', 'like', "%{$query}%")
                               ->first();
            if ($kb) {
                $kb->content = $newContent;
                $kb->save();
                return ['status' => 'success', 'message' => "Der Wiki-Eintrag '{$kb->title}' wurde erfolgreich aktualisiert."];
            }
            return ['status' => 'error', 'message' => 'Kein passender Eintrag im Wiki gefunden.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Update fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteBrainEntry(array $args)
    {
        try {
            $query = strtolower(trim($args['search_query'] ?? ''));

            if (empty($query)) {
                return ['status' => 'error', 'message' => 'Suchbegriff zum Löschen ist erforderlich.'];
            }

            // Wiki Delete
            $kb = AiKnowledgeBase::where('title', 'like', "%{$query}%")
                               ->orWhere('content', 'like', "%{$query}%")
                               ->first();
            if ($kb) {
                $title = $kb->title;
                $kb->delete();
                return ['status' => 'success', 'message' => "Der Wiki-Eintrag '{$title}' wurde erfolgreich und permanent gelöscht."];
            }
            return ['status' => 'error', 'message' => 'Kein passender Eintrag im Wiki gefunden, der gelöscht werden könnte.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Löschen fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    public static function executeSaveToBrain(array $args)
    {
        try {
            if (empty($args['title']) || empty($args['content'])) {
                return ['status' => 'error', 'message' => 'Titel und Inhalt sind für das Speichern erforderlich.'];
            }

            $tags = $args['tags'] ?? [];

            // Anti-Duplikat Check für AiKnowledgeBase
            $existingKb = AiKnowledgeBase::where('content', 'like', '%' . $args['content'] . '%')
                                       ->orWhere('title', $args['title'])
                                       ->exists();
            if ($existingKb) {
                return [
                    'status' => 'success',
                    'message' => 'Dieser identische Fakten-Eintrag existiert bereits in meinem generellen Wiki. Ich habe ihn nicht doppelt gespeichert.'
                ];
            }

            // Speichere in AiKnowledgeBase
            $catId = \App\Models\Ai\AiKnowledgeBaseCategory::firstOrCreate(
                ['slug' => 'ai-memory'],
                ['name' => 'AI Memory']
            )->id;

            $kb = AiKnowledgeBase::create([
                'title' => substr($args['title'], 0, 255),
                'slug' => \Illuminate\Support\Str::slug(substr($args['title'], 0, 255)) . '-' . rand(1000, 9999),
                'ai_knowledge_base_category_id' => $catId,
                'content' => $args['content'],
                'is_published' => true
            ]);

            $tagList = array_merge(['ai_memory', 'auto_saved'], $tags);
            $syncTags = [];
            foreach ($tagList as $t) {
                $syncTags[] = \App\Models\Ai\AiKnowledgeBaseTag::firstOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($t)],
                    ['name' => $t]
                )->id;
            }
            $kb->tags()->sync($syncTags);

            return [
                'status' => 'success',
                'message' => "Die Information '{$kb->title}' wurde erfolgreich im allgemeinen Langzeitgedächtnis (Wiki) gespeichert."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Speichern: ' . $e->getMessage()];
        }
    }

    public static function executeSearchBrain(array $args)
    {
        try {
            $queryStr = trim($args['query'] ?? '');
            if (empty($queryStr)) {
                return ['status' => 'error', 'message' => 'Es wurde kein Suchbegriff angegeben.'];
            }

            // 1. Clean conversational filler words
            $fillers = [
                'dokumente von der', 'dokumente vom', 'dokumente von', 'dokumente zu',
                'dokumente über', 'unterlagen von der', 'unterlagen vom', 'unterlagen von',
                'dateien von der', 'dateien vom', 'dateien von', 'zeigen', 'finde', 'suche'
            ];
            $cleaned = $queryStr;
            foreach ($fillers as $filler) {
                $cleaned = preg_replace('/\b' . preg_quote($filler, '/') . '\b/iu', ' ', $cleaned);
            }
            $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));
            if (empty($cleaned)) {
                $cleaned = $queryStr;
            }

            // 2. Split into segments for multi-topic queries
            $segments = preg_split('/\s*(?:\bund\b|\boder\b|\band\b|\bor\b|[,&+])\s*/iu', $cleaned, -1, PREG_SPLIT_NO_EMPTY);
            if (empty($segments)) {
                $segments = [$queryStr];
            }

            // 3. Search AiKnowledgeBase (Master-Dossiers, Guides, Wiki)
            $kbResults = collect();
            if (class_exists(AiKnowledgeBase::class)) {
                $kbQuery = AiKnowledgeBase::with(['category', 'tags'])->where('is_published', true);

                $kbQuery->where(function ($rootQ) use ($queryStr, $segments) {
                    // Exact literal match
                    $rootQ->where(function ($subQ) use ($queryStr) {
                        $subQ->where('title', 'like', "%{$queryStr}%")
                             ->orWhere('content', 'like', "%{$queryStr}%");
                    });

                    // Multi-topic & synonym segments
                    foreach ($segments as $seg) {
                        $seg = trim($seg);
                        if (mb_strlen($seg) < 2) continue;

                        $synonyms = [$seg];
                        $lower = mb_strtolower($seg);
                        if (str_contains($lower, 'arbeitsamt') || str_contains($lower, 'arbeitsagentur')) {
                            $synonyms[] = 'Agentur für Arbeit';
                            $synonyms[] = 'Bundesagentur';
                            $synonyms[] = 'Gründungszuschuss';
                            $synonyms[] = 'Existenzgründung';
                        } elseif (str_contains($lower, 'agentur')) {
                            $synonyms[] = 'Arbeitsamt';
                            $synonyms[] = 'Bundesagentur';
                            $synonyms[] = 'Gründungszuschuss';
                        } elseif (str_contains($lower, 'bkk')) {
                            $synonyms[] = 'firmus';
                            $synonyms[] = 'Krankengeld';
                            $synonyms[] = 'Sozialrecht';
                        }

                        $rootQ->orWhere(function ($segQ) use ($synonyms) {
                            foreach ($synonyms as $syn) {
                                $segQ->orWhere('title', 'like', "%{$syn}%")
                                     ->orWhere('content', 'like', "%{$syn}%")
                                     ->orWhereHas('tags', fn($t) => $t->where('name', 'like', "%{$syn}%"))
                                     ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$syn}%"));
                            }
                        });
                    }
                });

                $kbResults = $kbQuery->orderBy('created_at', 'desc')->limit(6)->get();
            }

            $kbList = [];
            foreach ($kbResults as $kb) {
                $kbList[] = [
                    'type' => 'knowledge_base',
                    'title' => $kb->title,
                    'category' => $kb->category ? $kb->category->name : 'Allgemein',
                    'tags' => $kb->tags->pluck('name')->implode(', '),
                    'content' => mb_substr($kb->content, 0, 1500),
                    'date' => $kb->created_at ? $kb->created_at->format('Y-m-d') : null
                ];
            }

            // 4. Search AiWorkspaceDocument (all private workspace files)
            $wsList = [];
            if (class_exists(\App\Models\Ai\AiWorkspaceDocument::class)) {
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
                    'workspace_documents' => []
                ];
            }

            return [
                'status' => 'success',
                'message' => "Erfolgreich gefunden: " . count($kbList) . " Dossiers in der Wissensdatenbank und " . count($wsList) . " Dokumente im privaten Workspace.",
                'knowledge_base' => $kbList,
                'workspace_documents' => $wsList,
                'results' => array_merge($kbList, $wsList)
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Durchsuchen des Gehirns: ' . $e->getMessage()];
        }
    }
}
