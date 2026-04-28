<?php

namespace App\Services\AI\Functions;

use App\Models\Product\ProductSupplier;

trait AiSuppliersFuncs
{
    /**
     * Define the Supplier specific tools for the Analyst Agent
     */
    public static function getAiSuppliersFuncsSchema(): array
    {
        return [
            [
                'name' => 'supplier_get_all',
                'description' => 'Gibt eine einfache Liste aller Lieferanten (Name, E-Mail, Transportweg) zurück. Nutze dies, um dir einen schnellen Überblick zu verschaffen, welche Hersteller/Partner im System existieren.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeSupplierGetAll']
            ],
            [
                'name' => 'supplier_get_details',
                'description' => 'Liest die vollständige Akte eines bestimmten Lieferanten aus (inkl. Adressen, Notizen, dynamischen Links und den genauen Lieferzeiten für Land/Wasser/Luftfristen).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_supplier_id' => [
                            'type' => 'string',
                            'description' => 'Die exakte ID oder der ungefähre Name des Lieferanten.'
                        ]
                    ],
                    'required' => ['product_supplier_id']
                ],
                'callable' => [self::class, 'executeSupplierGetDetails']
            ],
            [
                'name' => 'supplier_create',
                'description' => 'Erstellt einen komplett neuen Lieferanten im System.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Name des Lieferanten.'],
                        'contact_person' => ['type' => 'string', 'description' => 'Name des Ansprechpartners.'],
                        'email' => ['type' => 'string', 'description' => 'Kontakt E-Mail.'],
                        'phone' => ['type' => 'string', 'description' => 'Telefonnummer.'],
                        'website' => ['type' => 'string', 'description' => 'Webseite.'],
                        'address' => ['type' => 'string', 'description' => 'Postanschrift.'],
                        'lead_time_land_days' => ['type' => 'integer', 'description' => 'Lieferzeit über Land (Tage).'],
                        'lead_time_sea_days' => ['type' => 'integer', 'description' => 'Lieferzeit über See (Tage).'],
                        'lead_time_air_days' => ['type' => 'integer', 'description' => 'Lieferzeit über Luftfracht (Tage).'],
                        'shipping_method' => ['type' => 'string', 'description' => 'Standard Transportweg ("land", "air", "sea", "train").', 'enum' => ['land', 'air', 'sea', 'train']],
                        'notes' => ['type' => 'string', 'description' => 'Wichtige Notizen zum Lieferanten (z.B. MOQ, Zahlungsziele).']
                    ],
                    'required' => ['name']
                ],
                'callable' => [self::class, 'executeSupplierCreate']
            ],
            [
                'name' => 'supplier_update',
                'description' => 'Aktualisiert (Patcht) ein oder mehrere Felder eines bestehenden Lieferanten (z.B. neue E-Mail Adresse hinterlegen, andere Lieferzeit anpassen).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_supplier_id' => ['type' => 'string', 'description' => 'Die ID oder der ungenaue Name des Lieferanten.'],
                        'name' => ['type' => 'string', 'description' => 'Neuer Name des Lieferanten.'],
                        'contact_person' => ['type' => 'string', 'description' => 'Neuer Ansprechpartner.'],
                        'email' => ['type' => 'string', 'description' => 'Neue E-Mail Adresse.'],
                        'phone' => ['type' => 'string', 'description' => 'Neue Telefonnummer.'],
                        'website' => ['type' => 'string', 'description' => 'Neue Webseite.'],
                        'address' => ['type' => 'string', 'description' => 'Adresse.'],
                        'lead_time_land_days' => ['type' => 'integer', 'description' => 'Lieferzeit über Land (Tage).'],
                        'lead_time_sea_days' => ['type' => 'integer', 'description' => 'Lieferzeit über See (Tage).'],
                        'lead_time_air_days' => ['type' => 'integer', 'description' => 'Lieferzeit über Luft (Tage).'],
                        'shipping_method' => ['type' => 'string', 'description' => 'Neuer Standard-Transportweg ("land", "air", "sea", "train").', 'enum' => ['land', 'air', 'sea', 'train']],
                        'notes' => ['type' => 'string', 'description' => 'Aktualisierte Notizen.']
                    ],
                    'required' => ['product_supplier_id']
                ],
                'callable' => [self::class, 'executeSupplierUpdate']
            ],
            [
                'name' => 'supplier_delete',
                'description' => 'Löscht einen Lieferanten komplett aus dem System. ACHTUNG: Nur ausführen, wenn er definitiv nicht mehr benötigt wird.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_supplier_id' => [
                            'type' => 'string',
                            'description' => 'Die ID oder der Name des zu löschenden Lieferanten.'
                        ]
                    ],
                    'required' => ['product_supplier_id']
                ],
                'callable' => [self::class, 'executeSupplierDelete']
            ]
        ];
    }

    public static function executeSupplierGetAll(array $args)
    {
        try {
            $suppliers = ProductSupplier::orderBy('name')->get()->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'email' => $s->email,
                    'contact_person' => $s->contact_person,
                    'shipping_method' => $s->shipping_method
                ];
            });

            return [
                'status' => 'success',
                'total_suppliers' => $suppliers->count(),
                'product_suppliers' => $suppliers->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeSupplierGetDetails(array $args)
    {
        try {
            if (empty($args['product_supplier_id'])) return ['status' => 'error', 'message' => 'Supplier ID fehlt.'];
            
            $supplier = self::findSupplier($args['product_supplier_id']);
            if (!$supplier) return ['status' => 'error', 'message' => 'Lieferant wurde nicht gefunden.'];

            return [
                'status' => 'success',
                'supplier' => $supplier->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeSupplierCreate(array $args)
    {
        try {
            if (empty($args['name'])) return ['status' => 'error', 'message' => 'Name ist ein Pflichtfeld.'];

            $data = [
                'name' => $args['name'],
                'contact_person' => $args['contact_person'] ?? null,
                'email' => $args['email'] ?? null,
                'phone' => $args['phone'] ?? null,
                'website' => $args['website'] ?? null,
                'address' => $args['address'] ?? null,
                'notes' => $args['notes'] ?? null,
                'lead_time_land_days' => $args['lead_time_land_days'] ?? null,
                'lead_time_sea_days' => $args['lead_time_sea_days'] ?? null,
                'lead_time_air_days' => $args['lead_time_air_days'] ?? null,
                'shipping_method' => $args['shipping_method'] ?? 'land',
            ];

            $supplier = ProductSupplier::create($data);

            return [
                'status' => 'success',
                'message' => "Lieferant ({$supplier->name}) erfolgreich angelegt.",
                'product_supplier_id' => $supplier->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Anlegen: ' . $e->getMessage()];
        }
    }

    public static function executeSupplierUpdate(array $args)
    {
        try {
            if (empty($args['product_supplier_id'])) return ['status' => 'error', 'message' => 'Supplier ID fehlt.'];
            
            $supplier = self::findSupplier($args['product_supplier_id']);
            if (!$supplier) return ['status' => 'error', 'message' => 'Lieferant wurde nicht gefunden.'];

            $updates = [];
            $allowedFields = [
                'name', 'contact_person', 'email', 'phone', 'website', 'address', 'notes',
                'lead_time_land_days', 'lead_time_sea_days', 'lead_time_air_days', 'shipping_method'
            ];

            foreach ($allowedFields as $field) {
                if (isset($args[$field])) {
                    $updates[$field] = $args[$field];
                }
            }

            if (!empty($updates)) {
                $supplier->update($updates);
            }

            return [
                'status' => 'success',
                'message' => "Lieferant ({$supplier->name}) wurde aktualisiert.",
                'product_supplier_id' => $supplier->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Update: ' . $e->getMessage()];
        }
    }

    public static function executeSupplierDelete(array $args)
    {
        try {
            if (empty($args['product_supplier_id'])) return ['status' => 'error', 'message' => 'Supplier ID fehlt.'];
            
            $supplier = self::findSupplier($args['product_supplier_id']);
            if (!$supplier) return ['status' => 'error', 'message' => 'Lieferant wurde nicht gefunden.'];

            $name = $supplier->name;
            $supplier->delete();

            return [
                'status' => 'success',
                'message' => "Lieferant '{$name}' wurde dauerhaft gelöscht."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }

    private static function findSupplier($identifier)
    {
        $supplier = ProductSupplier::find($identifier);
        if (!$supplier) {
            $supplier = ProductSupplier::where('name', 'like', '%' . $identifier . '%')->first();
        }
        return $supplier;
    }
}
