<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;

$scraperApiKey = env('SCRAPER_API_KEY');
if (!$scraperApiKey) die("No ScraperAPI key found.\n");

$keyword = "personalisiertes geschenk";
$targetUrl = "https://www.amazon.de/s?k=" . urlencode($keyword);
echo "Fetching: $targetUrl\n";

$apiUrl = "http://api.scraperapi.com?api_key={$scraperApiKey}&url=" . urlencode($targetUrl) . "&keep_headers=true&country_code=de";

$client = new Client(['timeout' => 90.0, 'verify' => false]);
$response = $client->request('GET', $apiUrl, [
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2.1 Safari/605.1.15',
        'Accept-Language' => 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7'
    ]
]);

$html = $response->getBody()->getContents();
file_put_contents('/var/www/html/public/amazon_raw.html', $html);
echo "Saved HTML to public/amazon_raw.html - Size: " . strlen($html) . " bytes\n";

$crawler = new Crawler($html);

// Amazon search results often use data-component-type="s-search-result"
$listings = $crawler->filter('div[data-component-type="s-search-result"]');
echo "Found " . $listings->count() . " products.\n";

$results = [];

$listings->each(function (Crawler $node, $i) use (&$results) {
    if ($i >= 5) return; // Only top 5 testing
    
    // Title
    $titleNode = $node->filter('h2 span');
    $title = $titleNode->count() > 0 ? $titleNode->text('') : 'N/A';
    
    // URL
    $linkNode = $node->filter('.s-title-instructions-style a');
    $url = $linkNode->count() > 0 ? 'https://www.amazon.de' . $linkNode->attr('href') : 'N/A';
    
    // Image
    $imgNode = $node->filter('img.s-image');
    $image_url = $imgNode->count() > 0 ? $imgNode->attr('src') : null;
    
    // Price
    $priceWhole = $node->filter('.a-price-whole')->count() > 0 ? $node->filter('.a-price-whole')->text() : '';
    $priceFraction = $node->filter('.a-price-fraction')->count() > 0 ? $node->filter('.a-price-fraction')->text() : '';
    $price = $priceWhole ? (float)str_replace(',', '.', $priceWhole . $priceFraction) : null;
    
    // Rating
    $ratingNode = $node->filter('i[data-cy="reviews-ratings-slot"] span');
    $ratingNode2 = $node->filter('.a-icon-star-small span');
    $ratingText = $ratingNode->count() > 0 ? $ratingNode->text() : ($ratingNode2->count() > 0 ? $ratingNode2->text() : 'N/A');
    
    // Review Count
    $reviewNode = $node->filter('span.s-underline-text');
    $reviewCountText = $reviewNode->count() > 0 ? $reviewNode->text() : '0';
    $reviewCount = (int)preg_replace('/[^0-9]/', '', $reviewCountText);
    
    $results[] = [
        'title' => $title,
        'url' => $url,
        'image' => $image_url,
        'price' => $price,
        'rating' => $ratingText,
        'reviews' => $reviewCount
    ];
});

print_r($results);
