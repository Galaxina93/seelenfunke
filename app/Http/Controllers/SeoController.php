<?php

namespace App\Http\Controllers;

use App\Models\Marketing\MarketingBlogPost;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeoController extends Controller
{
    /**
     * Dynamische XML-Sitemap (/sitemap.xml) inkl. Google Image & Google Video Sitemap Extensions.
     */
    public function sitemap(): Response
    {
        $staticUrls = [
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('shop'), 'changefreq' => 'daily', 'priority' => '0.95'],
            ['loc' => route('manufacture'), 'changefreq' => 'monthly', 'priority' => '0.85'],
            ['loc' => route('marketing/marketing/blog'), 'changefreq' => 'weekly', 'priority' => '0.85'],
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

        $occasionSlugs = ['weihnachten', 'ostern', 'hochzeit', 'geburtstag', 'valentinstag', 'muttertag', 'vatertag', 'halloween'];
        foreach ($occasionSlugs as $occ) {
            $staticUrls[] = [
                'loc' => route('shop', ['filterHoliday' => $occ]),
                'changefreq' => 'weekly',
                'priority' => '0.80',
            ];
        }

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
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';

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

            $imageEntries = [];
            $videoEntries = [];

            if (!empty($mainImgUrl)) {
                $imageEntries[$mainImgUrl] = [
                    'loc' => $mainImgUrl,
                    'title' => $product->name,
                    'caption' => Str::limit(strip_tags((string) ($product->short_description ?: $product->description)), 180),
                ];
            }

            foreach ($product->media_gallery ?? [] as $media) {
                if (!is_array($media) || empty($media['path'])) {
                    continue;
                }
                $mType = $media['type'] ?? 'image';
                if ($mType === 'video') {
                    $vUrl = asset('storage/' . ltrim((string) $media['path'], '/'));
                    $videoEntries[$vUrl] = [
                        'content_loc' => $vUrl,
                        'thumbnail_loc' => $mainImgUrl ?: asset('shop/projekt/logo/mein-seelenfunke-logo.png'),
                        'title' => ($media['alt'] ?? ($product->name . ' – Produktvideo & Lasergravur')),
                        'description' => Str::limit(strip_tags((string) ($product->short_description ?: $product->description)), 200),
                    ];
                } else {
                    $imgUrl = ProductTemplate::resolveImageUrl($media['path']);
                    if ($imgUrl && !isset($imageEntries[$imgUrl])) {
                        $imageEntries[$imgUrl] = [
                            'loc' => $imgUrl,
                            'title' => $media['alt'] ?? $product->name,
                            'caption' => $media['alt'] ?? Str::limit(strip_tags((string) $product->short_description), 180),
                        ];
                    }
                }
            }

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . e($loc) . '</loc>';
            $xml[] = '    <lastmod>' . e($lastmod) . '</lastmod>';
            $xml[] = '    <changefreq>weekly</changefreq>';
            $xml[] = '    <priority>0.90</priority>';
            foreach ($imageEntries as $img) {
                $xml[] = '    <image:image>';
                $xml[] = '      <image:loc>' . e($img['loc']) . '</image:loc>';
                $xml[] = '      <image:title>' . e($img['title']) . '</image:title>';
                if (!empty($img['caption'])) {
                    $xml[] = '      <image:caption>' . e($img['caption']) . '</image:caption>';
                }
                $xml[] = '    </image:image>';
            }
            foreach ($videoEntries as $vid) {
                $xml[] = '    <video:video>';
                $xml[] = '      <video:thumbnail_loc>' . e($vid['thumbnail_loc']) . '</video:thumbnail_loc>';
                $xml[] = '      <video:title>' . e($vid['title']) . '</video:title>';
                $xml[] = '      <video:description>' . e($vid['description']) . '</video:description>';
                $xml[] = '      <video:content_loc>' . e($vid['content_loc']) . '</video:content_loc>';
                $xml[] = '      <video:duration>8</video:duration>';
                $xml[] = '      <video:family_friendly>yes</video:family_friendly>';
                $xml[] = '    </video:video>';
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
        $lines[] = '- **Strukturierter Produktkatalog (JSON):** [' . url('/feed/products.json') . '](' . url('/feed/products.json') . ')';
        $lines[] = '- **Google Merchant Center Feed (XML):** [' . url('/feed/google-shopping.xml') . '](' . url('/feed/google-shopping.xml') . ')';
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
                $grossCents = (int) $product->gross_price;
                $price = number_format($grossCents / 100, 2, ',', '.') . ' €';
                $compare = ($product->compare_at_price && $product->compare_at_price > $grossCents)
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

        $blogPosts = Schema::hasTable('marketing_blog_posts')
            ? MarketingBlogPost::published()->latest('published_at')->take(15)->get()
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
            $grossCents = (int) $product->gross_price;
            $price = number_format($grossCents / 100, 2, ',', '.') . ' €';
            $lines[] = '';
            $lines[] = "### {$product->name}";
            $lines[] = "- **URL:** {$url}";
            if (!empty($product->sku)) {
                $lines[] = "- **SKU / Artikelnummer:** {$product->sku}";
            }
            $lines[] = "- **Basispreis:** {$price} (inkl. MwSt., inkl. individueller Lasergravur)";
            if ($product->compare_at_price && $product->compare_at_price > $grossCents) {
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

            $attrs = MarketingLandingPage::extractEssentialAttributes($product);
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

        if ($blogPosts->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## 3. Magazin & Ratgeber-Wissen';
            foreach ($blogPosts as $post) {
                $postUrl = route('blog.show', $post->slug);
                $summary = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post->excerpt ?: $post->content)))), 240);
                $lines[] = "- **[{$post->title}]({$postUrl})**" . ($summary ? ": {$summary}" : '');
            }
        }

        $lines[] = '';
        $lines[] = '## 4. Häufig gestellte Fragen (FAQ)';
        $lines[] = '- **Wie lange dauert die Anfertigung und der Versand?** Jedes Stück wird individuell nach Bestellung gefertigt. Die reguläre Fertigungs- und Lieferzeit beträgt in der Regel 3 bis 5 Werktage innerhalb Deutschlands. Für eilige Geschenke gibt es im Checkout eine Express-Option.';
        $lines[] = '- **Welche Materialien werden graviert?** Wir veredeln optisch reines K9-Kristallglas, echten Naturschiefer, eloxiertes Aluminium, Edelstahl sowie ausgewählte Hölzer.';
        $lines[] = '- **Was kostet die Personalisierung?** Die individuelle Lasergravur (Texte, Motive, eigenes Logo und Vorder-/Rückseitengravur bei Glas) ist im angezeigten Produktpreis bereits vollständig enthalten – keine versteckten Zusatzkosten.';
        $lines[] = '- **Gibt es eine Mindestbestellmenge?** Nein, wir fertigen sowohl Einzelstücke (ab 1 Stück) für Privatkunden als auch Großserien mit attraktiven Staffelpreisen für Unternehmen, Vereine und Hochzeiten.';
        $lines[] = "- **Wie hoch sind die Versandkosten?** Innerhalb Deutschlands berechnen wir {$shippingCost} € für den versicherten DHL-Versand. Ab {$freeThreshold} € Warenkorbwert liefern wir innerhalb Deutschlands versandkostenfrei.";
        $lines[] = '- **Was passiert, wenn mein Glasprodukt beim Transport beschädigt wird?** Jede Kristallglas-Trophäe wird bruchsicher gepolstert im stabilen Versandkarton versendet. Sollte dennoch ein Transportschaden auftreten, fertigen wir nach kurzer Fotodokumentation umgehend kostenfreien Ersatz.';

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Google Merchant Center & Google Shopping Produkt-Feed (/feed/google-shopping.xml)
     * nach dem offiziellen RSS 2.0 + xmlns:g="http://base.google.com/ns/1.0" Standard.
     */
    public function googleShoppingFeed(): Response
    {
        $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
        $shippingCostCents = (int) shop_setting('shipping_cost', 490);
        $freeThresholdCents = (int) shop_setting('shipping_free_threshold', 5000);

        $products = Schema::hasTable('products')
            ? Product::where('status', 'active')->with('categories')->orderBy('name')->get()
            : collect();

        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
        $xml[] = '  <channel>';
        $xml[] = '    <title>' . e($companyName . ' – Produktfeed (Personalisierte Geschenke & Lasergravur)') . '</title>';
        $xml[] = '    <link>' . e(url('/')) . '</link>';
        $xml[] = '    <description>' . e('Handveredelte Unikate mit hochpräziser Lasergravur und Live-3D-Konfigurator aus der Manufaktur ' . $companyName . ' in Gifhorn.') . '</description>';

        foreach ($products as $product) {
            $id = $product->sku ?: ('MSF-' . strtoupper(substr((string) $product->id, 0, 8)));
            $link = route('product.show', $product->slug);
            $mainImg = $product->resolveMainImageUrl(null, false)
                ?: ($product->resolveMainImageUrl() ?: asset('shop/projekt/logo/mein-seelenfunke-logo.png'));

            $additionalImages = [];
            foreach ($product->media_gallery ?? [] as $media) {
                if (!is_array($media) || ($media['type'] ?? 'image') !== 'image' || empty($media['path'])) {
                    continue;
                }
                $u = ProductTemplate::resolveImageUrl($media['path']);
                if ($u && $u !== $mainImg && !in_array($u, $additionalImages, true)) {
                    $additionalImages[] = $u;
                }
            }

            $grossCents = (int) $product->gross_price;
            $compareCents = (int) ($product->compare_at_price ?? 0);
            $hasSale = $compareCents > $grossCents;

            $regularPriceFormatted = number_format(($hasSale ? $compareCents : $grossCents) / 100, 2, '.', '') . ' EUR';
            $salePriceFormatted = $hasSale ? (number_format($grossCents / 100, 2, '.', '') . ' EUR') : null;

            $shipPriceCents = (!$product->isPhysical() || $grossCents >= $freeThresholdCents) ? 0 : $shippingCostCents;
            $shipPriceFormatted = number_format($shipPriceCents / 100, 2, '.', '') . ' EUR';

            $desc = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($product->description ?: $product->short_description))));
            $catString = $product->categories->isNotEmpty()
                ? $product->categories->pluck('name')->implode(' > ')
                : 'Personalisierte Geschenke > Lasergravur';

            $xml[] = '    <item>';
            $xml[] = '      <g:id>' . e($id) . '</g:id>';
            $xml[] = '      <g:title>' . e(Str::limit($product->name . ' – Personalisiert mit Lasergravur', 150)) . '</g:title>';
            $xml[] = '      <g:description>' . e(Str::limit($desc, 4500)) . '</g:description>';
            $xml[] = '      <g:link>' . e($link) . '</g:link>';
            $xml[] = '      <g:image_link>' . e($mainImg) . '</g:image_link>';
            foreach (array_slice($additionalImages, 0, 10) as $addImg) {
                $xml[] = '      <g:additional_image_link>' . e($addImg) . '</g:additional_image_link>';
            }
            $xml[] = '      <g:condition>new</g:condition>';
            $xml[] = '      <g:availability>' . ($product->isAvailable() ? 'in_stock' : 'out_of_stock') . '</g:availability>';
            $xml[] = '      <g:price>' . e($regularPriceFormatted) . '</g:price>';
            if ($salePriceFormatted) {
                $xml[] = '      <g:sale_price>' . e($salePriceFormatted) . '</g:sale_price>';
            }
            $xml[] = '      <g:brand>' . e($product->brand ?: $companyName) . '</g:brand>';
            $xml[] = '      <g:mpn>' . e($id) . '</g:mpn>';
            $xml[] = '      <g:identifier_exists>false</g:identifier_exists>';
            $xml[] = '      <g:google_product_category>536</g:google_product_category>';
            $xml[] = '      <g:product_type>' . e($catString) . '</g:product_type>';
            if ($product->width > 0) {
                $xml[] = '      <g:product_width>' . e((string) $product->width) . ' mm</g:product_width>';
            }
            if ($product->height > 0) {
                $xml[] = '      <g:product_height>' . e((string) $product->height) . ' mm</g:product_height>';
            }
            if ($product->length > 0) {
                $xml[] = '      <g:product_length>' . e((string) $product->length) . ' mm</g:product_length>';
            }
            if ($product->weight > 0) {
                $xml[] = '      <g:shipping_weight>' . e((string) ($product->weight + (int) ($product->packaging_weight ?? 15))) . ' g</g:shipping_weight>';
            }
            $xml[] = '      <g:custom_label_0>' . ($product->isPersonalizable() ? '3D-Konfigurator Personalisierbar' : 'Standard') . '</g:custom_label_0>';
            $xml[] = '      <g:shipping>';
            $xml[] = '        <g:country>DE</g:country>';
            $xml[] = '        <g:service>DHL Paket versichert</g:service>';
            $xml[] = '        <g:price>' . e($shipPriceFormatted) . '</g:price>';
            $xml[] = '      </g:shipping>';
            $xml[] = '    </item>';
        }

        $xml[] = '  </channel>';
        $xml[] = '</rss>';

        return response(implode("\n", $xml), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Strukturierter JSON-Produktkatalog (/feed/products.json) für KI-Shopping-Assistenten,
     * Preisvergleichsportale und automatisierte Katalog-Indexierung.
     */
    public function productsJsonFeed(): JsonResponse
    {
        $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));

        $products = Schema::hasTable('products')
            ? Product::where('status', 'active')
                ->with(['categories', 'tierPrices', 'reviews' => fn ($q) => $q->where('status', 'approved')])
                ->orderBy('name')
                ->get()
            : collect();

        $items = $products->map(function (Product $product) {
            $grossCents = (int) $product->gross_price;
            $images = [];
            $videoUrl = null;
            $dimensionSheetUrl = null;

            foreach ($product->media_gallery ?? [] as $m) {
                if (!is_array($m) || empty($m['path'])) {
                    continue;
                }
                if (($m['type'] ?? 'image') === 'video') {
                    $videoUrl = asset('storage/' . ltrim((string) $m['path'], '/'));
                } else {
                    $u = ProductTemplate::resolveImageUrl($m['path']);
                    if ($u) {
                        $images[] = $u;
                        if (str_contains((string) $m['path'], '-dimensions.jpg')) {
                            $dimensionSheetUrl = $u;
                        }
                    }
                }
            }

            return [
                'id' => $product->sku ?: $product->slug,
                'slug' => $product->slug,
                'name' => $product->name,
                'url' => route('product.show', $product->slug),
                'landing_page_url' => route('landing-page', $product->slug),
                'price_eur' => round($grossCents / 100, 2),
                'compare_at_price_eur' => $product->compare_at_price ? round($product->compare_at_price / 100, 2) : null,
                'currency' => 'EUR',
                'in_stock' => $product->isAvailable(),
                'is_personalizable_3d' => $product->isPersonalizable(),
                'dimensions_mm' => [
                    'width' => $product->width,
                    'height' => $product->height,
                    'depth' => $product->length,
                ],
                'weight_grams' => $product->weight,
                'short_description' => $product->short_description,
                'description' => trim(preg_replace('/\s+/', ' ', strip_tags((string) $product->description))),
                'attributes' => MarketingLandingPage::extractEssentialAttributes($product),
                'categories' => $product->categories->pluck('name')->values()->all(),
                'tier_pricing' => $product->tierPrices->map(fn ($tp) => [
                    'min_quantity' => (int) $tp->qty,
                    'discount_percent' => (float) $tp->percent,
                ])->values()->all(),
                'rating' => [
                    'average' => $product->reviews->count() > 0 ? round($product->reviews->avg('rating'), 1) : null,
                    'count' => $product->reviews->count(),
                ],
                'main_image_url' => $product->resolveMainImageUrl(null, false) ?: $product->resolveMainImageUrl(),
                'dimension_sheet_image_url' => $dimensionSheetUrl,
                'gallery_images' => $images,
                'promo_video_url' => $videoUrl,
            ];
        })->values();

        return response()->json([
            'shop' => $companyName,
            'url' => url('/'),
            'generated_at' => now()->toAtomString(),
            'product_count' => $items->count(),
            'products' => $items,
        ], 200, [
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
