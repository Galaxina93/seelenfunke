<?php

namespace Tests\Feature\Price;

use App\Services\PriceCalcService;
use Tests\TestCase;

class PriceCalcServiceSecurityTest extends TestCase
{
    public function test_price_calc_prevents_division_by_zero_and_negative_rates()
    {
        $service = new PriceCalcService();

        // Negative tax rate would previously cause division by zero (-100%) or math errors
        $netAtMinus100 = $service->getNetFromGross(1000, -100.0);
        $this->assertEquals(1000, $netAtMinus100);

        $netAtMinus50 = $service->getNetFromGross(1000, -50.0);
        $this->assertEquals(1000, $netAtMinus50);

        // Clamping of negative prices
        $netNegativePrice = $service->getNetFromGross(-500, 19.0);
        $this->assertEquals(0, $netNegativePrice);

        $grossNegativePrice = $service->getGrossFromNet(-500, 19.0);
        $this->assertEquals(0, $grossNegativePrice);
    }

    public function test_price_calc_accurately_calculates_standard_vat()
    {
        $service = new PriceCalcService();

        // 119 Euro gross with 19% VAT = 100 Euro net, 19 Euro tax
        $gross = 11900;
        $net = $service->getNetFromGross($gross, 19.0);
        $tax = $service->getTaxAmountFromGross($gross, 19.0);

        $this->assertEquals(10000, $net);
        $this->assertEquals(1900, $tax);

        // 0% VAT
        $this->assertEquals(10000, $service->getNetFromGross(10000, 0.0));
        $this->assertEquals(10000, $service->getGrossFromNet(10000, 0.0));
        $this->assertEquals(0, $service->getTaxAmountFromNet(10000, 0.0));
    }
}
