<?php

namespace App\Services\AI\Functions;

use App\Models\Product\ProductTemplate;
use Illuminate\Support\Str;

trait AiProductTemplatesFuncs
{
    /**
     * Define the Product Templates tools for the Analyst Agent
     */
    public static function getAiProductTemplatesFuncsSchema(): array
    {
        return [
            [
                'name' => 'template_get_all',
                'description' => 'Liefert eine Liste aller Shop-Vorlagen (Templates). Ideal für einen Überblick über saisonale Layouts (z.B. "Weihnachten" oder "Muttertag"), die mit Produkten verknüpft sind.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'filter_holiday' => ['type' => 'string', 'description' => 'Optional: Filter nach dem eingetragenen Holiday-Tag, z.B. "Ostern", "Christmas", oder "Valentine".'],
                        'filter_active' => ['type' => 'boolean', 'description' => 'Optional: Nur aktive (true) oder inaktive (false) Vorlagen anzeigen.']
                    ]
                ],
                'callable' => [self::class, 'executeGetAll']
            ],
            [
                'name' => 'template_update',
                'description' => 'Patched Metadaten (Name, Holiday, aktiver Status) einer bestimmten Produkt-Vorlage. Der wichtigste Einsatz-Zweck: Vorlagen zum Saison-Start einschalten (is_active = true) bzw. nach Saisonsende ausschalten (is_active = false).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => ['type' => 'string', 'description' => 'Die exakte ID oder der ungenaue Name der Vorlage.'],
                        'name' => ['type' => 'string', 'description' => 'Neuer Anzeigename.'],
                        'holiday' => ['type' => 'string', 'description' => 'Saisonaler Tag der Vorlage (z.B. "Easter"). Leer lassen, um den Tag zu löschen.'],
                        'is_active' => ['type' => 'boolean', 'description' => 'True setzen, um die Vorlage im Konfigurator anzuzeigen, False zum deaktivieren/verbergen.']
                    ],
                    'required' => ['template_id']
                ],
                'callable' => [self::class, 'executeUpdate']
            ],
            [
                'name' => 'template_delete',
                'description' => 'Löscht eine Konfigurations-Vorlage komplett.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => ['type' => 'string', 'description' => 'Die ID oder der Name der zu löschenden Vorlage.']
                    ],
                    'required' => ['template_id']
                ],
                'callable' => [self::class, 'executeDelete']
            ]
        ];
    }

    public static function executeGetAll(array $args)
    {
        try {
            $query = ProductTemplate::with('product');
            
            if (isset($args['filter_holiday']) && !empty($args['filter_holiday'])) {
                $query->where('holiday', 'like', '%' . $args['filter_holiday'] . '%');
            }
            if (isset($args['filter_active'])) {
                $query->where('is_active', (bool)$args['filter_active']);
            }

            $templates = $query->get()->map(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'is_active' => $t->is_active,
                    'holiday' => $t->holiday,
                    'product_id' => $t->product_id,
                    'product_name' => $t->product ? $t->product->name : 'Unbekannt'
                ];
            });

            return [
                'status' => 'success',
                'total_templates' => $templates->count(),
                'templates' => $templates->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeUpdate(array $args)
    {
        try {
            if (empty($args['template_id'])) return ['status' => 'error', 'message' => 'Template ID fehlt.'];
            
            $template = self::findTemplate($args['template_id']);
            if (!$template) return ['status' => 'error', 'message' => 'Vorlage wurde nicht gefunden.'];

            $updates = [];
            
            if (isset($args['name'])) $updates['name'] = $args['name'];
            if (isset($args['holiday'])) $updates['holiday'] = empty($args['holiday']) ? null : $args['holiday'];
            if (isset($args['is_active'])) $updates['is_active'] = (bool)$args['is_active'];

            if (!empty($updates)) {
                $template->update($updates);
            }

            return [
                'status' => 'success',
                'message' => "Vorlage ({$template->name}) wurde erfolgreich aktualisiert."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Update: ' . $e->getMessage()];
        }
    }

    public static function executeDelete(array $args)
    {
        try {
            if (empty($args['template_id'])) return ['status' => 'error', 'message' => 'Template ID fehlt.'];
            
            $template = self::findTemplate($args['template_id']);
            if (!$template) return ['status' => 'error', 'message' => 'Vorlage wurde nicht gefunden.'];

            $name = $template->name;
            
            // Delete image if it belongs uniquely to the template
            if ($template->preview_image && Str::startsWith($template->preview_image, 'product-templates/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($template->preview_image);
            }
            
            $template->delete();

            return [
                'status' => 'success',
                'message' => "Vorlage '{$name}' wurde gelöscht."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }

    private static function findTemplate($identifier)
    {
        $template = ProductTemplate::find($identifier);
        if (!$template) {
            $template = ProductTemplate::where('name', 'like', '%' . $identifier . '%')->first();
        }
        return $template;
    }
}
