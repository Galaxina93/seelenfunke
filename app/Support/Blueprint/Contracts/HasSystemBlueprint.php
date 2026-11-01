<?php

namespace App\Support\Blueprint\Contracts;

use App\Support\Blueprint\ModelBlueprint;

interface HasSystemBlueprint
{
    /**
     * Liefert das verpflichtende Architektur-, Compliance- & KI-Sicherheits-Typenschild (Blueprint) dieses Models.
     */
    public static function blueprint(): ModelBlueprint;
}
