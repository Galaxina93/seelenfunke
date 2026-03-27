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
                'description' => 'Durchsucht dein allgemeines Wiki-Langzeitgedächtnis nach Wissen über die echte Welt, Fakten oder Einstellungen. Nutze für Personensuche immer contact_search! Stichworte: Wie war das Passwort, Suche im Gehirn, Brain Scan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff (z.B. "Vorlieben", "Rentenversicherungsnummer").'
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
            if (empty($args['query'])) {
                return ['status' => 'error', 'message' => 'Es wurde kein Suchbegriff angegeben.'];
            }

            $queryStr = $args['query'];
            $results = [];

            // Suche in der AiKnowledgeBase
            $kbResults = AiKnowledgeBase::with(['category', 'tags'])
                ->where('is_published', true)
                ->where(function ($q) use ($queryStr) {
                    $q->where('title', 'like', '%' . $queryStr . '%')
                      ->orWhere('content', 'like', '%' . $queryStr . '%')
                      ->orWhereHas('tags', function($t) use ($queryStr) {
                          $t->where('name', 'like', '%' . $queryStr . '%');
                      })
                      ->orWhereHas('category', function($c) use ($queryStr) {
                          $c->where('name', 'like', '%' . $queryStr . '%');
                      });
                })
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            foreach ($kbResults as $kb) {
                $results[] = [
                    'type' => 'knowledge_base',
                    'title' => $kb->title,
                    'category' => $kb->category ? $kb->category->name : 'Allgemein',
                    'tags' => $kb->tags->pluck('name')->implode(', '),
                    'content' => $kb->content,
                    'date' => $kb->created_at->format('Y-m-d')
                ];
            }

            if (empty($results)) {
                 return [
                    'status' => 'success',
                    'message' => 'Ich habe in meinem Gehirn-Wiki zu "' . $queryStr . '" nichts gefunden.',
                    'results' => []
                ];
            }

            return ['status' => 'success', 'results_count' => count($results), 'results' => $results];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Durchsuchen des Gehirns: ' . $e->getMessage()];
        }
    }
}
