<?php

namespace App\Services;

class PriceCalcService
{
    /**
     * Berechnet den Nettopreis aus einem Bruttopreis.
     * Anwendung: Wenn Preise im Backend inkl. MwSt. gespeichert sind (B2C Standard).
     */
    public function getNetFromGross(int $grossPrice, float $taxRate): int
    {
        $grossPrice = max(0, $grossPrice);
        $taxRate = max(0.0, min(100.0, $taxRate));

        if ($taxRate <= 0.0) {
            return $grossPrice;
        }
        // Formel: Brutto / (1 + Steuersatz/100)
        return (int) round($grossPrice / (1 + ($taxRate / 100)));
    }

    /**
     * Berechnet den Bruttopreis aus einem Nettopreis.
     * Anwendung: Wenn Preise exkl. MwSt. gespeichert sind (B2B) oder für die Endsumme.
     */
    public function getGrossFromNet(int $netPrice, float $taxRate): int
    {
        $netPrice = max(0, $netPrice);
        $taxRate = max(0.0, min(100.0, $taxRate));

        if ($taxRate <= 0.0) {
            return $netPrice;
        }
        // Formel: Netto * (1 + Steuersatz/100)
        return (int) round($netPrice * (1 + ($taxRate / 100)));
    }

    /**
     * Berechnet den reinen Steuerbetrag aus dem Nettopreis.
     */
    public function getTaxAmountFromNet(int $netPrice, float $taxRate): int
    {
        $netPrice = max(0, $netPrice);
        return $this->getGrossFromNet($netPrice, $taxRate) - $netPrice;
    }

    /**
     * Berechnet den reinen Steuerbetrag aus dem Bruttopreis.
     */
    public function getTaxAmountFromGross(int $grossPrice, float $taxRate): int
    {
        $grossPrice = max(0, $grossPrice);
        return $grossPrice - $this->getNetFromGross($grossPrice, $taxRate);
    }
}
