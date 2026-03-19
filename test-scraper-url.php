<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Symfony\Component\DomCrawler\Crawler;
$apiKey = env('SCRAPER_API_KEY');
$url = "http://api.scraperapi.com?api_key={$apiKey}&url=https%3A%2F%2Fwww.etsy.com%2Fsearch%3Fq%3Dpersonalisiertes%2Bgeschenk&keep_headers=true";

$html = file_get_contents($url, false, stream_context_create([
    'http' => [
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\nAccept-Language: de-DE,de;q=0.9\r\n"
    ]
]));

$crawler = new Crawler($html);
$node = $crawler->filter('.v2-listing-card')->first();

if ($node->count() > 0) {
    echo "Dumping HTML of first listing...\n";
    file_put_contents('card.html', $node->outerHtml());
} else {
    echo "No listings found.\n";
}
