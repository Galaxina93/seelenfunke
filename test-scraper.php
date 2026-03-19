<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Symfony\Component\DomCrawler\Crawler;
$html = file_get_contents('card.html');
$node = new Crawler($html);

$title = $node->filter('.v2-listing-card__title')->count() > 0 ? trim($node->filter('.v2-listing-card__title')->text('')) : 'NO_TITLE';
$linkNode = $node->filter('a.v2-listing-card__img');
$url = $linkNode->count() > 0 ? $linkNode->attr('href') : 'NO_URL';

$imgNode = $node->filter('img.wt-image');
$image_url = '';
if ($imgNode->count() > 0) {
    $image_url = $imgNode->attr('data-preload-lp-src') ?: ($imgNode->attr('data-listing-card-listing-image') ?: $imgNode->attr('src'));
}

$priceNode = $node->filter('.currency-value');
$price = $priceNode->count() > 0 ? (float)str_replace(',', '.', $priceNode->text()) : null;

$reviewNode = $node->filter('div[role="img"][aria-label*="rating"]');
$rating = 5.0; 
$review_count = 0;
if ($reviewNode->count() > 0) {
    $ariaLabel = $reviewNode->attr('aria-label');
    if (preg_match('/([\d\.]+)\s*star rating/', $ariaLabel, $rm)) {
        $rating = (float)$rm[1];
    }
    if (preg_match('/with ([\d\.]+[k|m]?)\s*review/', $ariaLabel, $rm)) {
        $revText = strtolower($rm[1]);
        if (strpos($revText, 'k') !== false) {
            $review_count = (int)(floatval($revText) * 1000);
        } elseif (strpos($revText, 'm') !== false) {
            $review_count = (int)(floatval($revText) * 1000000);
        } else {
            $review_count = (int)str_replace('.', '', $revText);
        }
    }
}

echo "Title: $title\n";
echo "URL: $url\n";
echo "Image: $image_url\n";
echo "Price: $price\n";
echo "Rating: $rating ($review_count reviews)\n";
