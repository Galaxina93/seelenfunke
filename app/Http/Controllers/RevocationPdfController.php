<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RevocationPdfController extends Controller
{
    /**
     * Gesetzliches Muster-Widerrufsformular dynamisch als PDF generieren.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request)
    {
        $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
        $ownerProprietor = shop_setting('owner_proprietor', 'Alina Steinhauer');

        $companyStreet = shop_setting('company_street');
        $streetNumber = shop_setting('company_street_number');
        if ($companyStreet) {
            $street = trim($companyStreet . ' ' . $streetNumber);
        } else {
            $street = shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
        }

        $zip = shop_setting('company_zip', '38518');
        $city = shop_setting('company_city', 'Gifhorn');
        $countryCode = strtoupper((string) shop_setting('company_country', 'DE'));

        $activeCountries = shop_setting('active_countries', []);
        if (is_array($activeCountries) && isset($activeCountries[$countryCode])) {
            $country = $activeCountries[$countryCode];
        } else {
            $countryMap = [
                'DE' => 'Deutschland',
                'AT' => 'Österreich',
                'CH' => 'Schweiz',
            ];
            $country = $countryMap[$countryCode] ?? ($countryCode ?: 'Deutschland');
        }

        $email = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
        $phone = shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64'));
        $website = shop_setting('owner_website', 'www.mein-seelenfunke.de');

        $logoPath = public_path('shop/projekt/logo/mein-seelenfunke-logo.png');
        $logoBase64 = null;
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
        }

        $data = [
            'companyName'     => $companyName,
            'ownerProprietor' => $ownerProprietor,
            'street'          => $street,
            'zip'             => $zip,
            'city'            => $city,
            'country'         => $country,
            'email'           => $email,
            'phone'           => $phone,
            'website'         => $website,
            'logoPath'        => file_exists($logoPath) ? $logoPath : null,
            'logoBase64'      => $logoBase64,
        ];

        $pdf = Pdf::loadView('pdf.revocation-form', $data)
            ->setPaper('a4', 'portrait')
            ->setOption([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
                'defaultFont'          => 'sans-serif',
            ]);

        $safeCompanyName = Str::slug($companyName, '_');
        $fileName = 'Widerrufsformular_' . ($safeCompanyName ?: 'MeinSeelenfunke') . '.pdf';

        if ($request->has('stream') || $request->has('preview') || $request->has('view')) {
            return $pdf->stream($fileName);
        }

        return $pdf->download($fileName);
    }
}
