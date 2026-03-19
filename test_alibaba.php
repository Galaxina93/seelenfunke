<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$apiKey = '707ccc851a9e7c4759106d2f6e6bf764';

$keyword = 'personalisiertes geschenk';
$url = 'https://www.alibaba.com/trade/search?SearchText=' . urlencode($keyword);

$client = new Client();

try {
    echo "Fetching $url via ScraperAPI...\n";
    $response = $client->request('GET', 'http://api.scraperapi.com', [
        'query' => [
            'api_key' => $apiKey,
            'url' => $url,
            'country_code' => 'us',
            'render' => 'true',
            'premium' => 'true'
        ],
        'timeout' => 60,
    ]);

    $html = $response->getBody()->getContents();
    file_put_contents('public/alibaba_raw.html', $html);
    echo "Saved to public/alibaba_raw.html - length: " . strlen($html) . "\n";

    $crawler = new Crawler($html);
    $results = [];

    $crawler->filter('.searchx-offer-item')->each(function (Crawler $node, $i) use (&$results) {
        if ($i >= 5) return;

        $titleNode = $node->filter('h2.search-card-e-title span');
        $title = $titleNode->count() > 0 ? $titleNode->text('') : 'N/A';

        $linkNode = $node->filter('h2.search-card-e-title a');
        $url = $linkNode->count() > 0 ? $linkNode->attr('href') : 'N/A';
        if ($url !== 'N/A' && strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        $imgNode = $node->filter('img.search-card-e-slider__img');
        $image_url = $imgNode->count() > 0 ? $imgNode->attr('src') : null;
        if ($image_url && strpos($image_url, '//') === 0) {
            $image_url = 'https:' . $image_url;
        }

        $priceNode = $node->filter('.search-card-e-price-main');
        $priceText = $priceNode->count() > 0 ? $priceNode->text() : '';
        // Extract numbers from "US$1.58-2.88"
        preg_match('/([0-9.,]+)/', $priceText, $m);
        $price = isset($m[1]) ? (float)str_replace(',', '.', $m[1]) : 0;

        $reviewNode = $node->filter('.search-card-e-review');
        $rating = 0;
        $reviews = 0;
        if ($reviewNode->count() > 0) {
            $text = $reviewNode->text(); // e.g. "4.9/5.0 (43 reviews)" or "4.9/5.0 (43)"
            if (preg_match('/([0-9.]+)\/5\.0\s*\(([^)]+)\)/', $text, $matches)) {
                $rating = (float)$matches[1];
                $reviews = (int)preg_replace('/[^0-9]/', '', $matches[2]);
            }
        }

        $results[] = [
            'title' => $title,
            'url' => $url,
            'image' => $image_url,
            'price' => $price,
            'rating' => $rating,
            'reviews' => $reviews
        ];
    });

    print_r($results);

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
