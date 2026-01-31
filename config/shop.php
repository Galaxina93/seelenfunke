<?php

return [
    'shipping' => [
        'cost' => 490, // Standardversand in Cent (4,90 €)
        'free_threshold' => 5000, // Kostenlos ab 50,00 €
        'tax_rate' => 19, // 19% MwSt auf Versand
        'prices_entered_gross' => true, // true = Du tippst Brutto ein, false = Netto
    ],

    // Nur für die Anzeige in Dropdowns!
    'countries' => [
        // Europa
        'DE' => 'Deutschland',
        'AT' => 'Österreich',
        'CH' => 'Schweiz',
        'NL' => 'Niederlande',
        'BE' => 'Belgien',
        'FR' => 'Frankreich',
        'IT' => 'Italien',
        'ES' => 'Spanien',
        'GB' => 'Großbritannien',
        'PL' => 'Polen',
        'CZ' => 'Tschechien',
        'DK' => 'Dänemark',
        'SE' => 'Schweden',
        'NO' => 'Norwegen',
        'FI' => 'Finnland',
        'IE' => 'Irland',
        'PT' => 'Portugal',
        'GR' => 'Griechenland',
        'HU' => 'Ungarn',
        'RO' => 'Rumänien',
        'BG' => 'Bulgarien',
        'SK' => 'Slowakei',
        'SI' => 'Slowenien',
        'HR' => 'Kroatien',
        'EE' => 'Estland',
        'LV' => 'Lettland',
        'LT' => 'Litauen',
        'LU' => 'Luxemburg',
        'IS' => 'Island',

        // Nordamerika
        'US' => 'USA',
        'CA' => 'Kanada',
        'MX' => 'Mexiko',

        // Südamerika
        'BR' => 'Brasilien',
        'AR' => 'Argentinien',
        'CL' => 'Chile',
        'CO' => 'Kolumbien',

        // Asien
        'CN' => 'China',
        'JP' => 'Japan',
        'KR' => 'Südkorea',
        'IN' => 'Indien',
        'SG' => 'Singapur',
        'AE' => 'Vereinigte Arabische Emirate',

        // Ozeanien
        'AU' => 'Australien',
        'NZ' => 'Neuseeland',

        // Afrika (optional)
        'ZA' => 'Südafrika',

        // Fallback Steuern (falls Produkt keine hat)
        'default_tax_rate' => 19,
    ],

];
