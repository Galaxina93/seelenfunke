<?php

namespace App\Services\AI\Functions;

use App\Models\Management\ManagementShoppingItem;
use App\Models\Management\ManagementShoppingCategory;

trait AiShoppingListFuncs
{
    public static function getAiShoppingListFuncsSchema(): array
    {
        return [
            [
                'name' => 'shopping_list_get',
                'description' => 'Holt die gesamte Einkaufsliste. Zeigt sowohl Produkte an, die aktuell benötigt werden, als auch den Vorrat.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'only_needed' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, werden nur die Produkte zurückgegeben, die aktuell benötigt werden (auf der Liste stehen).'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeGetShoppingList']
            ],
            [
                'name' => 'shopping_list_add',
                'description' => 'Fügt ein neues Produkt zur Einkaufsliste hinzu.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name des Produkts (z.B. Milch, Toilettenpapier).'
                        ],
                        'category_name' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Die Kategorie (z.B. Lebensmittel, Drogerie).'
                        ]
                    ],
                    'required' => ['name']
                ],
                'callable' => [self::class, 'executeAddShoppingItem']
            ],
            [
                'name' => 'shopping_list_bulk_toggle',
                'description' => 'Ändert den Status von einem oder mehreren Produkten gleichzeitig. Kann für Listen von Namen oder für ganze Kategorien (mit Ausnahmen) genutzt werden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'description' => 'Der neue Status: "needed" (Brauchen wir) oder "stocked" (Gekauft/Vorrat).'
                        ],
                        'item_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Eine Liste von exakten oder teilweisen Produktnamen, die geändert werden sollen.'
                        ],
                        'category_name' => [
                            'type' => 'string',
                            'description' => 'Wenn gesetzt, werden ALLE Produkte in dieser Kategorie geändert.'
                        ],
                        'exclude_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Produkte, die NICHT geändert werden sollen (nützlich in Kombination mit category_name).'
                        ]
                    ],
                    'required' => ['status']
                ],
                'callable' => [self::class, 'executeBulkToggleShoppingItem']
            ],
            [
                'name' => 'shopping_list_rename',
                'description' => 'Benennt ein Produkt auf der Einkaufsliste um oder ändert die Kategorie.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'item_id' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Die UUID des Produkts.'
                        ],
                        'old_name' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Der alte Name des Produkts (falls ID nicht bekannt).'
                        ],
                        'new_name' => [
                            'type' => 'string',
                            'description' => 'Der neue Name des Produkts.'
                        ],
                        'new_category' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Die neue Kategorie.'
                        ]
                    ],
                    'required' => ['new_name']
                ],
                'callable' => [self::class, 'executeRenameShoppingItem']
            ],
            [
                'name' => 'shopping_list_delete',
                'description' => 'Löscht eines oder mehrere Produkte komplett aus dem System. Kann für Listen von Namen oder für alle Einträge ganzer Kategorien (mit Ausnahmen) genutzt werden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'item_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Eine Liste von exakten oder teilweisen Produktnamen, die gelöscht werden sollen.'
                        ],
                        'category_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Wenn gesetzt, werden ALLE Produkte in diesen Kategorien gelöscht.'
                        ],
                        'exclude_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Produkte, die NICHT gelöscht werden sollen (nützlich in Kombination mit category_names).'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeDeleteShoppingItem']
            ],
            [
                'name' => 'shopping_category_add',
                'description' => 'Erstellt eine neue Kategorie für die Einkaufsliste.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name der neuen Kategorie (z.B. Getränke).'
                        ],
                        'icon' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Nur GÜLTIGE Heroicons: shopping-cart, home, sparkles, beaker, heart, cake, fire, gift, star, scissors, cube, sun, moon, tag. WICHTIG: Erfinde keine Icons wie "droplet".'
                        ]
                    ],
                    'required' => ['name']
                ],
                'callable' => [self::class, 'executeAddShoppingCategory']
            ],
            [
                'name' => 'shopping_category_update',
                'description' => 'Aktualisiert eine bestehende Kategorie (Name oder Icon ändern).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'old_name' => [
                            'type' => 'string',
                            'description' => 'Der aktuelle Name der Kategorie.'
                        ],
                        'new_name' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Der neue Name der Kategorie.'
                        ],
                        'new_icon' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Das neue Heroicon.'
                        ]
                    ],
                    'required' => ['old_name']
                ],
                'callable' => [self::class, 'executeUpdateShoppingCategory']
            ],
            [
                'name' => 'shopping_category_delete',
                'description' => 'Löscht eine oder mehrere Kategorien. Kann dynamisch gesteuert werden (z.B. alle löschen außer XY, Produkte verschieben, etc.).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'category_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Die Namen der zu löschenden Kategorien.'
                        ],
                        'delete_all' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, werden ALLE bestehenden Kategorien gelöscht (kann mit exclude_names kombiniert werden).'
                        ],
                        'exclude_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Kategorien, die von der Löschung ausgenommen werden sollen.'
                        ],
                        'delete_items' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, werden auch alle Produkte gelöscht, die diesen Kategorien zugeordnet waren. Wenn false, fallen die Produkte auf "Ohne Kategorie" zurück.'
                        ],
                        'move_items_to_category' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Der Name einer Ziel-Kategorie. Alle Produkte der zu löschenden Kategorien werden in diese neue Kategorie verschoben, bevor gelöscht wird.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeDeleteShoppingCategory']
            ],
            [
                'name' => 'shopping_item_assign_category',
                'description' => 'Ordnet ein Produkt einer Kategorie zu oder entfernt die Zuordnung.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'item_name' => [
                            'type' => 'string',
                            'description' => 'Der Name des Produkts.'
                        ],
                        'category_name' => [
                            'type' => 'string',
                            'description' => 'Der Name der Kategorie. Leer lassen (""), um die Zuordnung aufzuheben.'
                        ]
                    ],
                    'required' => ['item_name']
                ],
                'callable' => [self::class, 'executeAssignShoppingCategory']
            ]
        ];
    }

    public static function executeGetShoppingList(array $args)
    {
        try {
            $onlyNeeded = $args['only_needed'] ?? false;
            
            $query = ManagementShoppingItem::with('category');
            if ($onlyNeeded) {
                $query->where('status', 'needed');
            }

            $items = $query->get()->map(function($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'status' => $item->status,
                    'category' => $item->category ? $item->category->name : 'Ohne',
                    'last_purchased_at' => $item->last_purchased_at ? $item->last_purchased_at->format('Y-m-d H:i') : null,
                    'diff_for_humans' => $item->last_purchased_at ? $item->last_purchased_at->diffForHumans() : 'Nie gekauft',
                ];
            });

            return [
                'status' => 'success',
                'items' => $items->toArray(),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abrufen der Liste: ' . $e->getMessage()];
        }
    }

    public static function executeAddShoppingItem(array $args)
    {
        try {
            if (empty($args['name'])) {
                return ['status' => 'error', 'message' => 'Der Name des Produkts fehlt.'];
            }

            $category_id = null;
            if (!empty($args['category_name'])) {
                $category = ManagementShoppingCategory::firstOrCreate(
                    ['name' => $args['category_name']],
                    ['sort_order' => ManagementShoppingCategory::max('sort_order') + 1]
                );
                $category_id = $category->id;
            }

            $item = ManagementShoppingItem::where('name', 'like', $args['name'])->first();

            if ($item) {
                $item->status = 'needed';
                if ($category_id) {
                    $item->category_id = $category_id;
                }
                $item->save();
                return ['status' => 'success', 'message' => "Produkt existierte bereits und wurde auf die Einkaufsliste gesetzt.", 'item_id' => $item->id];
            }

            $item = ManagementShoppingItem::create([
                'name' => $args['name'],
                'category_id' => $category_id,
                'status' => 'needed',
            ]);

            return ['status' => 'success', 'message' => "Produkt erfolgreich hinzugefügt und auf die Liste gesetzt.", 'item_id' => $item->id];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Hinzufügen: ' . $e->getMessage()];
        }
    }

    public static function executeBulkToggleShoppingItem(array $args)
    {
        try {
            $status = $args['status'] ?? 'stocked';
            $itemNames = $args['item_names'] ?? [];
            $categoryName = $args['category_name'] ?? null;
            $excludeNames = $args['exclude_names'] ?? [];

            if (empty($itemNames) && empty($categoryName)) {
                return ['status' => 'error', 'message' => 'Es muss entweder eine Liste von Namen (item_names) oder eine Kategorie (category_name) angegeben werden.'];
            }

            $query = ManagementShoppingItem::query();

            // Wenn eine Kategorie angegeben wurde, suche nach Produkten in dieser Kategorie
            if (!empty($categoryName)) {
                $query->whereHas('category', function($q) use ($categoryName) {
                    $q->where('name', 'like', '%' . $categoryName . '%');
                });
            } else {
                // Ansonsten nur die spezifischen Produkte suchen
                $query->where(function($q) use ($itemNames) {
                    foreach ($itemNames as $name) {
                        $q->orWhere('name', 'like', '%' . $name . '%');
                    }
                });
            }

            $items = $query->get();

            if ($items->isEmpty()) {
                return ['status' => 'error', 'message' => 'Keine passenden Produkte gefunden.'];
            }

            $updatedCount = 0;
            $updatedNames = [];

            foreach ($items as $item) {
                // Prüfen ob das Produkt auf der Ausschlussliste steht
                $isExcluded = false;
                foreach ($excludeNames as $exName) {
                    if (stripos($item->name, $exName) !== false) {
                        $isExcluded = true;
                        break;
                    }
                }

                // Falls wir keine Kategorie nutzen, sondern nur eine Namensliste, prüfen wir ob der Name wirklich drin steht
                if (empty($categoryName) && !empty($itemNames)) {
                    $isIncluded = false;
                    foreach ($itemNames as $inName) {
                        if (stripos($item->name, $inName) !== false) {
                            $isIncluded = true;
                            break;
                        }
                    }
                    if (!$isIncluded) continue;
                }

                if (!$isExcluded) {
                    if ($status === 'stocked' && $item->status === 'needed') {
                        $item->last_purchased_at = now();
                        $item->purchase_count++;
                    }
                    $item->status = $status;
                    $item->save();
                    
                    $updatedCount++;
                    $updatedNames[] = $item->name;
                }
            }

            return [
                'status' => 'success', 
                'message' => "Es wurden $updatedCount Produkte auf '$status' gesetzt.",
                'updated_items' => $updatedNames
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Bulk-Aktualisieren: ' . $e->getMessage()];
        }
    }

    public static function executeRenameShoppingItem(array $args)
    {
        try {
            if (empty($args['item_id']) && empty($args['old_name'])) {
                return ['status' => 'error', 'message' => 'Es muss entweder item_id oder old_name angegeben werden.'];
            }
            if (empty($args['new_name'])) {
                return ['status' => 'error', 'message' => 'Der neue Name (new_name) fehlt.'];
            }

            if (!empty($args['item_id'])) {
                $item = ManagementShoppingItem::find($args['item_id']);
            } else {
                $item = ManagementShoppingItem::where('name', 'like', '%' . $args['old_name'] . '%')->first();
            }

            if (!$item) {
                return ['status' => 'error', 'message' => 'Produkt nicht gefunden.'];
            }

            $item->name = $args['new_name'];

            if (!empty($args['new_category'])) {
                $category = ManagementShoppingCategory::firstOrCreate(
                    ['name' => $args['new_category']],
                    ['sort_order' => ManagementShoppingCategory::max('sort_order') + 1]
                );
                $item->category_id = $category->id;
            }

            $item->save();

            return ['status' => 'success', 'message' => "Das Produkt wurde erfolgreich aktualisiert auf: '{$item->name}'."];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Umbenennen: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteShoppingItem(array $args)
    {
        try {
            $itemNames = $args['item_names'] ?? [];
            $categoryNames = $args['category_names'] ?? [];
            $excludeNames = $args['exclude_names'] ?? [];

            if (empty($itemNames) && empty($categoryNames)) {
                return ['status' => 'error', 'message' => 'Es muss entweder eine Liste von Namen (item_names) oder Kategorien (category_names) angegeben werden.'];
            }

            $query = ManagementShoppingItem::query();

            // Filtern nach Kategorien
            if (!empty($categoryNames)) {
                $query->whereHas('category', function($q) use ($categoryNames) {
                    $q->where(function($subQ) use ($categoryNames) {
                        foreach ($categoryNames as $cName) {
                            $subQ->orWhere('name', 'like', '%' . $cName . '%');
                        }
                    });
                });
            } else {
                // Nur spezifische Produkte suchen
                $query->where(function($q) use ($itemNames) {
                    foreach ($itemNames as $name) {
                        $q->orWhere('name', 'like', '%' . $name . '%');
                    }
                });
            }

            $items = $query->get();

            if ($items->isEmpty()) {
                return ['status' => 'error', 'message' => 'Keine passenden Produkte gefunden.'];
            }

            $deletedCount = 0;
            $deletedNames = [];

            foreach ($items as $item) {
                // Prüfen ob das Produkt auf der Ausschlussliste steht
                $isExcluded = false;
                foreach ($excludeNames as $exName) {
                    if (stripos($item->name, $exName) !== false) {
                        $isExcluded = true;
                        break;
                    }
                }

                // Falls wir keine Kategorie nutzen, sondern nur eine Namensliste, prüfen wir ob der Name wirklich drin steht
                if (empty($categoryNames) && !empty($itemNames)) {
                    $isIncluded = false;
                    foreach ($itemNames as $inName) {
                        if (stripos($item->name, $inName) !== false) {
                            $isIncluded = true;
                            break;
                        }
                    }
                    if (!$isIncluded) continue;
                }

                if (!$isExcluded) {
                    $deletedNames[] = $item->name;
                    $item->delete();
                    $deletedCount++;
                }
            }

            return [
                'status' => 'success', 
                'message' => "Es wurden $deletedCount Produkte vollständig gelöscht.",
                'deleted_items' => $deletedNames
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }

    public static function executeAddShoppingCategory(array $args)
    {
        try {
            if (empty($args['name'])) {
                return ['status' => 'error', 'message' => 'Der Name der Kategorie fehlt.'];
            }

            $icon = $args['icon'] ?? 'shopping-cart';
            $allowedIcons = ['shopping-cart', 'home', 'sparkles', 'beaker', 'heart', 'cake', 'fire', 'gift', 'star', 'scissors', 'cube', 'sun', 'moon', 'tag'];
            if (!in_array($icon, $allowedIcons)) {
                $icon = 'shopping-cart';
            }

            $category = ManagementShoppingCategory::firstOrCreate(
                ['name' => $args['name']],
                ['icon' => $icon, 'sort_order' => ManagementShoppingCategory::max('sort_order') + 1]
            );

            return ['status' => 'success', 'message' => "Die Kategorie '{$category->name}' wurde erfolgreich angelegt.", 'category_id' => $category->id];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Anlegen der Kategorie: ' . $e->getMessage()];
        }
    }

    public static function executeUpdateShoppingCategory(array $args)
    {
        try {
            if (empty($args['old_name'])) {
                return ['status' => 'error', 'message' => 'Der alte Name der Kategorie fehlt.'];
            }

            $category = ManagementShoppingCategory::where('name', 'like', $args['old_name'])->first();

            if (!$category) {
                return ['status' => 'error', 'message' => "Kategorie '{$args['old_name']}' nicht gefunden."];
            }

            if (!empty($args['new_name'])) {
                $category->name = $args['new_name'];
            }
            if (!empty($args['new_icon'])) {
                $icon = $args['new_icon'];
                $allowedIcons = ['shopping-cart', 'home', 'sparkles', 'beaker', 'heart', 'cake', 'fire', 'gift', 'star', 'scissors', 'cube', 'sun', 'moon', 'tag'];
                if (!in_array($icon, $allowedIcons)) {
                    $icon = 'shopping-cart';
                }
                $category->icon = $icon;
            }
            
            $category->save();

            return ['status' => 'success', 'message' => "Die Kategorie wurde erfolgreich aktualisiert auf: '{$category->name}'."];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Aktualisieren der Kategorie: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteShoppingCategory(array $args)
    {
        try {
            $categoryNames = $args['category_names'] ?? [];
            $deleteAll = $args['delete_all'] ?? false;
            $excludeNames = $args['exclude_names'] ?? [];
            $deleteItems = $args['delete_items'] ?? false;
            $moveToCategoryName = $args['move_items_to_category'] ?? null;

            if (empty($categoryNames) && !$deleteAll) {
                return ['status' => 'error', 'message' => 'Es muss entweder eine Liste von Kategorien (category_names) oder delete_all = true angegeben werden.'];
            }

            $query = ManagementShoppingCategory::query();

            if (!$deleteAll) {
                $query->where(function($q) use ($categoryNames) {
                    foreach ($categoryNames as $cName) {
                        $q->orWhere('name', 'like', '%' . $cName . '%');
                    }
                });
            }

            $categories = $query->get();

            if ($categories->isEmpty()) {
                return ['status' => 'error', 'message' => 'Keine passenden Kategorien gefunden.'];
            }

            // Ziel-Kategorie verarbeiten (wenn gesetzt)
            $targetCategoryId = null;
            if (!empty($moveToCategoryName)) {
                $targetCat = ManagementShoppingCategory::firstOrCreate(
                    ['name' => $moveToCategoryName],
                    ['icon' => 'shopping-cart', 'sort_order' => ManagementShoppingCategory::max('sort_order') + 1]
                );
                $targetCategoryId = $targetCat->id;
            }

            $deletedCount = 0;
            $deletedNames = [];

            foreach ($categories as $category) {
                // Prüfen ob die Kategorie ausgeschlossen ist
                $isExcluded = false;
                foreach ($excludeNames as $exName) {
                    if (stripos($category->name, $exName) !== false) {
                        $isExcluded = true;
                        break;
                    }
                }
                
                if ($targetCategoryId && $category->id === $targetCategoryId) {
                    $isExcluded = true; // Niemals das eigene Ziel löschen!
                }

                if (!$isExcluded) {
                    $catId = $category->id;
                    $deletedNames[] = $category->name;
                    
                    if ($targetCategoryId) {
                        // Verschiebe alle Items in die Ziel-Kategorie
                        ManagementShoppingItem::where('category_id', $catId)->update(['category_id' => $targetCategoryId]);
                    } elseif ($deleteItems) {
                        // Lösche alle betroffenen Items
                        ManagementShoppingItem::where('category_id', $catId)->delete();
                    } else {
                        // Löse Verknüpfungen (Fallback auf "Ohne Kategorie")
                        ManagementShoppingItem::where('category_id', $catId)->update(['category_id' => null]);
                    }
                    
                    $category->delete();
                    $deletedCount++;
                }
            }

            $msg = "Es wurden $deletedCount Kategorien gelöscht.";
            if ($targetCategoryId) {
                $msg .= " Alle enthaltenen Produkte wurden erfolgreich nach '$moveToCategoryName' verschoben.";
            } elseif ($deleteItems) {
                $msg .= " Alle enthaltenen Produkte wurden ebenfalls hart gelöscht.";
            } else {
                $msg .= " Enthaltene Produkte fielen auf 'Ohne Kategorie' zurück.";
            }

            return [
                'status' => 'success', 
                'message' => $msg,
                'deleted_categories' => $deletedNames
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen der Kategorien: ' . $e->getMessage()];
        }
    }

    public static function executeAssignShoppingCategory(array $args)
    {
        try {
            if (empty($args['item_name'])) {
                return ['status' => 'error', 'message' => 'Der Produktname (item_name) fehlt.'];
            }

            $item = ManagementShoppingItem::where('name', 'like', '%' . $args['item_name'] . '%')->first();

            if (!$item) {
                return ['status' => 'error', 'message' => "Produkt '{$args['item_name']}' nicht gefunden."];
            }

            if (empty($args['category_name'])) {
                $item->category_id = null;
                $item->save();
                return ['status' => 'success', 'message' => "Dem Produkt '{$item->name}' wurde die Kategorie entzogen (Ohne Kategorie)."];
            }

            $category = ManagementShoppingCategory::firstOrCreate(
                ['name' => $args['category_name']],
                ['icon' => 'shopping-cart', 'sort_order' => ManagementShoppingCategory::max('sort_order') + 1]
            );

            $item->category_id = $category->id;
            $item->save();

            return ['status' => 'success', 'message' => "Das Produkt '{$item->name}' wurde erfolgreich der Kategorie '{$category->name}' zugeordnet."];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der Zuweisung: ' . $e->getMessage()];
        }
    }
}
