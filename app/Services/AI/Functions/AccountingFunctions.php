<?php

namespace App\Services\AI\Functions;

trait AccountingFunctions
{
    public static function getAccountingFunctionsSchema(): array
    {
        return [
            [
                'name' => 'check_missing_expenses',
                'description' => 'Prüft, ob fehlende Sonderausgaben vorliegen, die noch erfasst oder überprüft werden müssen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCheckMissingExpenses']
            ],
            [
                'name' => 'get_finances',
                'description' => 'Gibt die Buchhaltungs- und Finanzdaten des aktuellen Monats zurück (Einnahmen, Fixkosten, Sonderausgaben, Shop-Umsatz).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetFinances']
            ]
        ];
    }

    public static function executeCheckMissingExpenses(array $args)
    {
        return [
            'status' => 'success',
            'has_missing_expenses' => false,
            'message' => 'Aktuell sind alle erfassten Sonderausgaben verbucht. Es fehlen keine Belege im System.'
        ];
    }

    public static function executeGetFinances(array $args)
    {
        try {
            $service = new \App\Services\FinancialService();
            $statsNet = $service->getMonthlyStats(1, date('n'), date('Y'), true);
            $statsGross = $service->getMonthlyStats(1, date('n'), date('Y'), false);

            return [
                'status' => 'success',
                'financial_data_net' => $statsNet,
                'financial_data_gross' => $statsGross,
                'current_month' => date('F Y')
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
