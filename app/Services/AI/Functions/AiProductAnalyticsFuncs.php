<?php

namespace App\Services\AI\Functions;

trait AiProductAnalyticsFuncs
{
    /**
     * Define the Product Analytics specific tools for the Analyst Agent
     */
    public static function getAiProductAnalyticsFuncsSchema(): array
    {
        return [
            [
                'name' => 'product_analytics_get_overview',
                'description' => 'Liefert eine extrem detaillierte betriebswirtschaftliche Übersicht über alle aktiven physischen Produkte. Beinhaltet Marge, Verpackungskosten, Absatz-Geschwindigkeit (Velocity), Reichweite des Lagerbestands und den Bestell-Status. Nutze dies für Produkt-Vergleiche, Identifikation von Flops oder Bestell-Empfehlungen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetOverview']
            ],
            [
                'name' => 'product_analytics_get_lucid_report',
                'description' => 'Liefert eine detaillierte Auswertung des Verpackungsmülls (LUCID) für das aktuelle Jahr, aufgeschlüsselt nach Materialien (Papier, Plastik, Glas, etc.) und Produkten.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetLucidReport']
            ]
        ];
    }

    public static function executeGetOverview(array $args)
    {
        try {
            $data = \App\Livewire\Shop\Product\ProductAnalytics::getCombinedAnalyticsData();
            return [
                'status' => 'success',
                'products_count' => $data->count(),
                'report' => $data->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetLucidReport(array $args)
    {
        try {
            $data = \App\Livewire\Shop\Product\ProductAnalytics::getLucidData();
            return [
                'status' => 'success',
                'report' => $data
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
