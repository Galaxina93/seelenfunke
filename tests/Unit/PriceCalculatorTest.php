<?php

namespace Tests\Unit;

use App\Services\PriceCalculator;
use Tests\TestCase;

class PriceCalculatorTest extends TestCase
{
    public function test_it_calculates_net_from_gross()
    {
        $calc = new PriceCalculator();
        // 119€ Brutto bei 19% MwSt = 100€ Netto (in Cent: 11900 -> 10000)
        $this->assertEquals(10000, $calc->getNetFromGross(11900, 19.0));
    }

    public function test_it_calculates_gross_from_net()
    {
        $calc = new PriceCalculator();
        $this->assertEquals(11900, $calc->getGrossFromNet(10000, 19.0));
    }
}
