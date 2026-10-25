<?php

namespace App\Http\Controllers;

use App\Models\Marketing\MarketingBlogPost;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Product\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeoController extends Controller
{
    /**
     * Dynamische XML-Sitemap (/sitemap.xml) inkl. Google Image Sitemap Extension.
     */
    public function sitemap(): Response
    {
        $staticUrls = [
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('shop'), 'changefreq' => 'daily', 'priority' => '0.95'],
            ['loc' => route('manufacture'), 'changefreq' => 'monthly', 'priority' => '0.85'],
            ['loc' => route('marketing/marketing/blog'), 'changefreq' => 'weekly', 'priority' => '0.80'],
            ['loc' => route('calculator'), 'changefreq' => 'weekly', 'priority' => '0.80'],
            ['loc' => route('contact'), 'changefreq' => 'monthly', 'priority' => '0.75'],
            ['loc' => route('versand'), 'changefreq' => 'monthly', 'priority' => '0.65'],
            ['loc' => route('newsletter.page'), 'changefreq' => 'monthly', 'priority' => '0.60'],
            ['loc' => route('barrierefreiheit'), 'changefreq' => 'yearly', 'priority' => '0.40'],
            ['loc' => route('verhaltenskodex'), 'changefreq' => 'yearly', 'priority' => '0.40'],
            ['loc' => route('impressum'), 'changefreq' => 'yearly', 'priority' => '0.30'],
            ['loc' => route('datenschutz'), 'changefreq' => 'yearly', 'priority' => '0.30'],
            ['loc' => route('agb'), 'changefreq' => 'yearly', 'priority' => '0.30'],
            ['loc' => route('widerruf'), 'changefreq' => 'yearly', 'priority' => '0.30'],
        ];

        $products = collect();
        if (Schema::hasTable('products')) {
            $products = Product::where('status', 'active')
                ->orderBy('updated_at', 'desc')
                ->get();
        }

        $landingPages = collect();
        if (Schema::hasTable('marketing_landing_pages')) {
            $landingPages = MarketingLandingPage::where('status', 'active')
                ->whereHas('product', fn ($q) => $q->where('status', 'active'))
                ->with('product')
                ->orderBy('updated_at', 'desc')
                ->get();
        }

        $blogPosts = collect();
        if (Schema::hasTable('marketing_blog_posts')) {
            $blogPosts = MarketingBlogPost::published()
                ->orderBy('published_at', 'desc')
                ->get();
        }

        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

        foreach ($staticUrls as $item) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . e($item['loc']) . '</loc>';
            $xml[] = '    <changefreq>' . e($item['changefreq']) . '</changefreq>';
            $xml[] = '    <priority>' . e($item['priority']) . '</priority>';
            $xml[] = '  </url>';
        }

        foreach ($products as $product) {
            $loc = route('product.show', $product->slug);
            $lastmod = optional($product->updated_at ?? $product->created_at)->toAtomString() ?: now()->toAtomString();
            $mainImgUrl = $product->resolveMainImageUrl(null, false) ?: $product->resolveMainImageUrl();

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . e($loc) . '</loc>';
            $xml[] = '    <lastmod>' . e($lastmod) . '</lastmod>';
            $xml[] = '    <changefreq>weekly</changefreq>';
            $xml[] = '    <priority>0.90</priority>';
            if (!empty($mainImgUrl)) {
                $xml[] = '    <image:image>';
                $xml[] = '      <image:loc>' . e($mainImgUrl) . '</image:loc>';
                $xml[] = '      <image:title>' . e($product->name) . '</image:title>';
                if (!empty($product->short_description)) {
                    $xml[] = '      <image:caption>' . e(Str::limit(strip_tags($product->short_description), 180)) . '</image:caption>';
                }
                $xml[] = '    </image:image>';
            }
            $xml[] = '  </url>';
        }

        foreach ($landingPages as $lp) {
            $loc = route('landing-page', $lp->slug);
            $lastmod = optional($lp->updated_at ?? $lp->created_at)->toAtomString() ?: now()->toAtomString();

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . e($loc) . '</loc>';
            $xml[] = '    <lastmod>' . e($lastmod) . '</lastmod>';
            $xml[] = '    <changefreq>weekly</changefreq>';
            $xml[] = '    <priority>0.80</priority>';
            $xml[] = '  </url>';
        }

        foreach ($blogPosts as $post) {
            $loc = route('blog.show', $post->slug);
            $lastmod = optional($post->updated_at ?? $post->published_at ?? $post->created_at)->toAtomString() ?: now()->toAtomString();
            $imgPath = $post->header_image ?: $post->featured_image;

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . e($loc) . '</loc>';
            $xml[] = '    <lastmod>' . e($lastmod) . '</lastmod>';
            $xml[] = '    <changefreq>monthly</changefreq>';
            $xml[] = '    <priority>0.75</priority>';
            if (!empty($imgPath)) {
                $xml[] = '    <image:image>';
                $xml[] = '      <image:loc>' . e(asset('storage/' . ltrim($imgPath, '/'))) . '</image:loc>';
                $xml[] = '      <image:title>' . e($post->title) . '</image:title>';
                $xml[] = '    </image:image>';
            }
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return response(implode("\n", $xml), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Kompakte KI-Suchmaschinen-Übersicht (/llms.txt) nach dem llms.txt Standard
     * für ChatGPT / OAI-SearchBot, Perplexity, Claude, Gemini & Co.
     */
    public function llmsTxt(): Response
    {
        $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
        $founderName = shop_setting('owner_proprietor', 'Alina Steinhauer');
        $city = shop_setting('company_city', 'Gifhorn');
        $zip = shop_setting('company_zip', '38518');
        $email = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
        $phone = shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64'));
        $shippingCost = number_format(((int) shop_setting('shipping_cost', 490)) / 100, 2, ',', '.');
        $freeThreshold = number_format(((int) shop_setting('shipping_free_threshold', 5000)) / 100, 2, ',', '.');

        $products = Schema::hasTable('products')
            ? Product::where('status', 'active')->with('categories')->orderBy('name')->get()
            : collect();

        $blogPosts = Schema::hasTable('marketing_blog_posts')
            ? MarketingBlogPost::published()->latest('published_at')->take(10)->get()
            : collect();

        $lines = [];
        $lines[] = "# {$companyName} – Manufaktur für personalisierte Geschenke & Lasergravur";
        $lines[] = '';
        $lines[] = "> {$companyName} ist eine deutsche Manufaktur mit Sitz in {$zip} {$city} (Niedersachsen, Deutschland), gegründet und geführt von {$founderName}. Wir veredeln hochwertiges K9-Kristallglas (Trophäen, Glasfotos, Pokale), Naturschiefer, eloxiertes Edelmetall und Massivholz mittels hochpräziser Lasergravur zu persönlichen Unikaten – einzeln ab 1 Stück oder als B2B-Serienfertigung. Kunden gestalten ihr Produkt live im interaktiven 2D- & 3D-Online-Konfigurator direkt im Browser.";
        $lines[] = '';
        $lines[] = '## Kernfakten für KI-Assistenten & Suchmaschinen';
        $lines[] = "- **Marke / Unternehmen:** {$companyName}";
        $lines[] = "- **Inhaberin & Gründerin:** {$founderName}";
        $lines[] = "- **Standort / Fertigung:** {$zip} {$city}, Niedersachsen, Deutschland (100 % Handveredelung in Deutschland)";
        $lines[] = "- **Kontakt:** E-Mail: {$email} | Telefon: {$phone}";
        $lines[] = '- **Besonderheit:** Interaktiver 2D-Editor & 3D-Echtzeit-Produktkonfigurator (Vorder- und Rückseitengravur, freie Text-, Motiv-, Logo- und Grafikplatzierung direkt am 3D-Modell ohne Zusatzkosten).';
        $lines[] = "- **Versand (DE):** {$shippingCost} € versicherter DHL-Versand (ab {$freeThreshold} € Bestellwert versandkostenfrei). Express-Fertigung verfügbar.";
        $lines[] = '- **Mindestbestellmenge:** Keine Mindestbestellmenge (Einzelstücke ab 1 Stück sowie Mengenrabatte/Staffelpreise für Vereine, Hochzeiten und Firmenkunden).';
        $lines[] = '- **Vollständige Wissensdatenbank:** [' . url('/llms-full.txt') . '](' . url('/llms-full.txt') . ')';
        $lines[] = '';
        $lines[] = '## Wichtige Seiten & Einstiegspunkte';
        $lines[] = '- [Startseite](' . route('home') . '): Überblick über Manufaktur, Materialien, Kundenstimmen und häufige Fragen.';
        $lines[] = '- [Online-Shop & Kollektion](' . route('shop') . '): Alle personalisierbaren Geschenke, Trophäen, Glasfotos und Unikate.';
        $lines[] = '- [Die Manufaktur & Gründerin Alina Steinhauer](' . route('manufacture') . '): Hintergrund zur Lasertechnologie, Materialkunde (K9-Kristallglas, Schiefer, Metall) und geprüften Sicherheitsstandards.';
        $lines[] = '- [Sofort-Kalkulator für B2B & Großbestellungen](' . route('calculator') . '): Unverbindliche Preis- und Projektkalkulation für Firmen, Vereine und Events.';
        $lines[] = '- [Versand & Lieferzeiten](' . route('versand') . '): Details zu DHL-Versand, bruchsicherer Verpackung und Express-Option.';
        $lines[] = '- [Kontakt & Individuelle Anfrage](' . route('contact') . '): Direkter Draht zur Manufaktur.';
        $lines[] = '';
        $lines[] = '## Aktuelles Produktsortiment (Live-Preise inkl. MwSt.)';

        if ($products->isEmpty()) {
            $lines[] = '- Aktuell werden die Produkte im Shop aktualisiert: ' . route('shop');
        } else {
            foreach ($products as $product) {
                $url = route('product.show', $product->slug);
                $price = number_format($product->price / 100, 2, ',', '.') . ' €';
                $compare = ($product->compare_at_price && $product->compare_at_price > $product->price)
                    ? ' (Statt ' . number_format($product->compare_at_price / 100, 2, ',', '.') . ' €)'
                    : '';
                $pers = $product->isPersonalizable() ? 'Personalisierbar im 2D/3D-Konfigurator' : 'Sofort bestellbar';
                $avail = $product->isAvailable() ? 'Auf Lager / Verfügbar' : 'Derzeit ausverkauft';
                $shortDesc = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($product->short_description ?: $product->seo_description ?: $product->description))));
                $shortDesc = Str::limit($shortDesc, 180);

                $lines[] = "- [{$product->name}]({$url}) – **{$price}**{$compare} | {$pers} | {$avail}" . ($shortDesc ? " — {$shortDesc}" : '');
            }
        }

        if ($blogPosts->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Magazin & Ratgeber-Artikel';
            foreach ($blogPosts as $post) {
                $postUrl = route('blog.show', $post->slug);
                $excerpt = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post->excerpt ?: $post->meta_description)))), 150);
                $lines[] = "- [{$post->title}]({$postUrl})" . ($excerpt ? ": {$excerpt}" : '');
            }
        }

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Detaillierte KI-Wissensdatenbank (/llms-full.txt) mit sämtlichen Produktdetails,
     * Varianten, Staffelpreisen, Attributen und FAQs für präzise Antworten in KI-Suchmaschinen.
     */
    public function llmsFullTxt(): Response
    {
        $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
        $founderName = shop_setting('owner_proprietor', 'Alina Steinhauer');
        $street = trim(shop_setting('company_street', 'Carl-Goerdeler-Ring') . ' ' . shop_setting('company_street_number', '26'));
        $zip = shop_setting('company_zip', '38518');
        $city = shop_setting('company_city', 'Gifhorn');
        $email = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
        $phone = shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64'));
        $shippingCost = number_format(((int) shop_setting('shipping_cost', 490)) / 100, 2, ',', '.');
        $freeThreshold = number_format(((int) shop_setting('shipping_free_threshold', 5000)) / 100, 2, ',', '.');

        $products = Schema::hasTable('products')
            ? Product::where('status', 'active')->with(['categories', 'tierPrices', 'reviews' => fn ($q) => $q->where('status', 'approved')])->orderBy('name')->get()
            : collect();

        $lines = [];
        $lines[] = "# {$companyName} – Vollständige Produkt- & Manufaktur-Dokumentation (LLMs-Full)";
        $lines[] = '';
        $lines[] = "## 1. Über {$companyName}";
        $lines[] = "- **Unternehmen:** {$companyName}";
        $lines[] = "- **Inhaberin:** {$founderName}";
        $lines[] = "- **Anschrift:** {$street}, {$zip} {$city}, Deutschland";
        $lines[] = "- **Webseite:** " . url('/');
        $lines[] = "- **E-Mail:** {$email}";
        $lines[] = "- **Telefon:** {$phone}";
        $lines[] = '- **Positionierung:** Manufaktur für personalisierte Geschenke, exklusive Pokale/Trophäen aus optischem K9-Kristallglas, gravierte Naturschieferplatten, Edelstahl-/Aluminium-Unikate und Holzgravuren.';
        $lines[] = '- **Technologie:** Hochpräzise Dioden-, Infrarot- und Faserlaser-Gravur kombiniert mit einem eigens entwickelten interaktiven 2D/3D-Web-Konfigurator. Jedes Werkstück wird in Gifhorn von Hand geprüft, veredelt und bruchsicher verpackt.';
        $lines[] = '';
        $lines[] = '## 2. Detaillierter Produktkatalog';

        foreach ($products as $product) {
            $url = route('product.show', $product->slug);
            $price = number_format($product->price / 100, 2, ',', '.') . ' €';
            $lines[] = '';
            $lines[] = "### {$product->name}";
            $lines[] = "- **URL:** {$url}";
            if (!empty($product->sku)) {
                $lines[] = "- **SKU / Artikelnummer:** {$product->sku}";
            }
            $lines[] = "- **Basispreis:** {$price} (inkl. MwSt., inkl. individueller Lasergravur)";
            if ($product->compare_at_price && $product->compare_at_price > $product->price) {
                $lines[] = '- **Regulärer Vergleichspreis:** ' . number_format($product->compare_at_price / 100, 2, ',', '.') . ' €';
            }
            $lines[] = '- **Verfügbarkeit:** ' . ($product->isAvailable() ? 'Auf Lager / Sofort bestellbar' : 'Derzeit nicht auf Lager');
            $lines[] = '- **Personalisierbar:** ' . ($product->isPersonalizable() ? 'Ja – interaktiver 2D-Editor & 3D-Vorschau (eigene Texte, Schriftarten, Vektormotive & eigenes Foto/Logo gravierbar)' : 'Standardprodukt ohne Gravur-Konfigurator');

            if ($product->categories->isNotEmpty()) {
                $lines[] = '- **Kategorien:** ' . $product->categories->pluck('name')->implode(', ');
            }

            if ($product->delivery_time_label) {
                $lines[] = "- **Lieferzeit:** {$product->delivery_time_label}";
            }

            $attrs = is_array($product->attributes) ? $product->attributes : [];
            if (!empty($attrs)) {
                $attrParts = [];
                foreach ($attrs as $k => $v) {
                    if (is_scalar($v) && trim((string) $v) !== '') {
                        $attrParts[] = "{$k}: {$v}";
                    }
                }
                if (!empty($attrParts)) {
                    $lines[] = '- **Eigenschaften & Maße:** ' . implode(' | ', $attrParts);
                }
            }

            $variants = $product->getSanitizedVariantsData(false);
            if (!empty($variants)) {
                $varStrings = [];
                foreach ($variants as $v) {
                    if (!filter_var($v['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                        continue;
                    }
                    $vName = $v['name'] ?? 'Standard';
                    $vPrice = isset($v['price']) && $v['price'] !== ''
                        ? number_format((float) $v['price'], 2, ',', '.') . ' €'
                        : $price;
                    $varStrings[] = "{$vName} ({$vPrice})";
                }
                if (!empty($varStrings)) {
                    $lines[] = '- **Wählbare Ausführungen / Varianten:** ' . implode(', ', $varStrings);
                }
            }

            if ($product->tierPrices && $product->tierPrices->isNotEmpty()) {
                $tierStrings = [];
                foreach ($product->tierPrices as $tp) {
                    $tierStrings[] = "ab {$tp->qty} Stk.: {$tp->percent}% Rabatt";
                }
                $lines[] = '- **Mengenrabatt / Staffelpreise:** ' . implode(', ', $tierStrings);
            }

            $approvedReviews = $product->reviews;
            if ($approvedReviews && $approvedReviews->count() > 0) {
                $avg = round($approvedReviews->avg('rating'), 1);
                $cnt = $approvedReviews->count();
                $lines[] = "- **Kundenbewertungen:** {$avg} / 5.0 Sterne ({$cnt} verifizierte Bewertungen)";
            }

            $desc = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($product->description ?: $product->short_description))));
            if ($desc !== '') {
                $lines[] = "- **Beschreibung:** {$desc}";
            }
        }

        $lines[] = '';
        $lines[] = '## 3. Häufig gestellte Fragen (FAQ)';
        $lines[] = '- **Wie lange dauert die Anfertigung und der Versand?** Jedes Stück wird individuell nach Bestellung gefertigt. Die reguläre Fertigungs- und Lieferzeit beträgt in der Regel 3 bis 5 Werktage innerhalb Deutschlands. Für eilige Geschenke gibt es im Checkout eine Express-Option.';
        $lines[] = '- **Welche Materialien werden graviert?** Wir veredeln optisch reines K9-Kristallglas, echten Naturschiefer, eloxiertes Aluminium, Edelstahl sowie ausgewählte Hölzer.';
        $lines[] = '- **Was kostet die Personalisierung?** Die individuelle Lasergravur (Texte, Motive, eigenes Logo und Vorder-/Rückseitengravur bei Glas) ist im angezeigten Produktpreis bereits vollständig enthalten – keine versteckten Zusatzkosten.';
        $lines[] = '- **Gibt es eine Mindestbestellmenge?** Nein, wir fertigen sowohl Einzelstücke (ab 1 Stück) für Privatkunden als auch Großserien mit attraktiven Staffelpreisen für Unternehmen, Vereine und Hochzeiten.';
        $lines[] = "- **Wie hoch sind die Versandkosten?** Innerhalb Deutschlands berechnen wir {$shippingCost} € für den versicherten DHL-Versand. Ab {$freeThreshold} € Warenkorbwert liefern wir innerhalb Deutschlands versandkostenfrei.";
        $lines[] = '- **Was passiert, wenn mein Glasprodukt beim Transport beschädigt wird?** Jede Kristallglas-Trophäe wird in einer passgenau gepolsterten Geschenkbox versendet. Sollte dennoch ein Transportschaden auftreten, fertigen wir nach kurzer Fotodokumentation umgehend kostenfreien Ersatz.';

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
