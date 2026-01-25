<?php

namespace App\Services;

class PriceCalculator
{
    /**
     * Berechnet den Nettopreis aus einem Bruttopreis.
     * Anwendung: Wenn Preise im Backend inkl. MwSt. gespeichert sind (B2C Standard).
     */
    public function getNetFromGross(int $grossPrice, float $taxRate): int
    {
        if ($taxRate == 0) {
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
        if ($taxRate == 0) {
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
        return $this->getGrossFromNet($netPrice, $taxRate) - $netPrice;
    }

    /**
     * Berechnet den reinen Steuerbetrag aus dem Bruttopreis.
     */
    public function getTaxAmountFromGross(int $grossPrice, float $taxRate): int
    {
        return $grossPrice - $this->getNetFromGross($grossPrice, $taxRate);
    }
}
