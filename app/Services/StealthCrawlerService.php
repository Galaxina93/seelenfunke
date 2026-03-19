<?php

namespace App\Services;

use App\Models\Product\NicheProduct;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StealthCrawlerService
{
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1'
    ];

    public function crawlEtsy(string $jobId, string $keyword = 'personalisiertes geschenk', int $pages = 1)
    {
        Log::info("StealthCrawler: Start crawling Etsy for '{$keyword}' (Job: {$jobId})");
        
        $updateJobState = function($progress, $status) use ($jobId, $keyword) {
            Cache::put("crawler_job_{$jobId}", [
                'id' => $jobId,
                'keyword' => $keyword,
                'platform' => 'Etsy',
                'progress' => $progress,
                'status' => $status,
                'is_running' => true
            ], 600);
        };

        $updateJobState(5, 'Initialisiere Session & Browser-Spoofing...');

        $client = new Client([
            'base_uri' => 'https://www.etsy.com/',
            'timeout'  => 60.0,
            'cookies'  => true,
            'verify' => false,
        ]);

        for ($page = 1; $page <= $pages; $page++) {
            if (Cache::has("cancel_crawler_{$jobId}")) {
                Cache::forget("cancel_crawler_{$jobId}");
                $updateJobState(0, "Vorgang durch Benutzer abgebrochen.");
                Cache::forget("crawler_job_{$jobId}");
                return;
            }

            $updateJobState(5, "Warte auf zufälligen Browser-Delay vor Seite {$page} (Anti-Bot)...");
            $this->politeDelay();

            $progress = 10 + (($page - 1) / max(1, $pages)) * 80;
            $updateJobState((int)$progress, "Lade Etsy Suchergebnisseite {$page} von {$pages}...");

            try {
                $targetUrl = "https://www.etsy.com/search?q=" . urlencode($keyword) . "&page={$page}&ref=pagination";
                $scraperApiKey = env('SCRAPER_API_KEY');

                if ($scraperApiKey) {
                    $updateJobState((int)$progress, "Routing via ScraperAPI (Stealth Mode) für Seite {$page}...");
                    $apiUrl = "http://api.scraperapi.com?api_key={$scraperApiKey}&url=" . urlencode($targetUrl) . "&keep_headers=true";
                    $response = $client->request('GET', $apiUrl, [
                        'headers' => $this->getRandomHeaders()
                    ]);
                } else {
                    $response = $client->request('GET', $targetUrl, [
                        'headers' => $this->getRandomHeaders()
                    ]);
                }

                $html = $response->getBody()->getContents();
                $crawler = new Crawler($html);
                
                $listings = $crawler->filter('.v2-listing-card');

                if ($listings->count() === 0) {
                    Log::warning("StealthCrawler: No listings found on Etsy page {$page}. Maybe CAPTCHA blocked?");
                    $updateJobState((int)$progress, "Warnung: Keine Produkte auf Seite {$page} gefunden (Captcha?)");
                    continue;
                }

                $count = $listings->count();
                $updateJobState((int)$progress, "Analysiere und berechne Nischen-Scores für {$count} Produkte auf Seite {$page}...");

                $listings->each(function (Crawler $node, $i) use ($page, $pages, $count, $jobId, $updateJobState) {
                    if (Cache::has("cancel_crawler_{$jobId}")) { return false; } // Break inner loop

                    $currentProductProgress = 10 + ((($page - 1) / max(1, $pages)) * 80) + (($i / max(1, $count)) * (80 / max(1, $pages)));
                    $updateJobState((int)$currentProductProgress, "Extrahiere Daten für Produkt " . ($i+1) . " von {$count}...");
                    
                    try {
                        $title = $node->filter('.v2-listing-card__title')->count() > 0 ? $node->filter('.v2-listing-card__title')->text('') : '';
                        
                        $linkNode = $node->filter('a.v2-listing-card__img');
                        $url = $linkNode->count() > 0 ? $linkNode->attr('href') : '';
                        
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
                            // Beispiel: "4.8 star rating with 4.8k reviews"
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

                        $sales_volume = $review_count * 15; // Estimator
                        $score = $this->calculateNicheScore($price, $sales_volume, $review_count);

                        $isBulky = false;
                        $bulkyKeywords = ['schrank', 'esstisch', 'sofa', 'sessel', 'bett', 'kommode', 'fass', 'xxl', 'gartenbank', 'regal', 'möbel', 'truhe', 'sitzbank', 'säule'];
                        $titleLower = strtolower($title);
                        foreach ($bulkyKeywords as $keyword) {
                            if (strpos($titleLower, $keyword) !== false) {
                                $isBulky = true;
                                break;
                            }
                        }

                        if (!empty($title) && !empty($url) && !$isBulky) {
                            NicheProduct::updateOrCreate(
                                ['url' => $this->cleanUrl($url)],
                                [
                                    'title' => trim($title),
                                    'platform' => 'Etsy',
                                    'price' => $price,
                                    'sales_volume' => $sales_volume,
                                    'rating' => $rating,
                                    'review_count' => $review_count,
                                    'image_url' => $image_url,
                                    'niche_score' => $score,
                                    'scraped_at' => now(),
                                ]
                            );
                        }
                    } catch (\Exception $e) {
                         Log::error("StealthCrawler: Failed to parse or save product: " . $e->getMessage());
                    }
                });

            } catch (\GuzzleHttp\Exception\ClientException $e) {
                Log::error("StealthCrawler: ClientException: " . $e->getMessage());
                if ($e->getResponse()->getStatusCode() == 403) {
                    $updateJobState((int)$progress, "⛔ Blockiert (403): Etsy Anti-Bot aktiv. Trage einen kostenlosen SCRAPER_API_KEY in die .env ein, um den Schutz zu umgehen.");
                } else {
                    $updateJobState((int)$progress, "HTTP Fehler " . $e->getResponse()->getStatusCode() . " auf Seite {$page}.");
                }
                sleep(6); // Halte kurz an, damit Admin den Fehler im Live-Tracker und den Tipp lesen kann
            } catch (\Exception $e) {
                Log::error("StealthCrawler: Etsy Request failed: " . $e->getMessage());
                $updateJobState((int)$progress, "Kritischer Fehler beim Laden von Seite {$page}: " . substr($e->getMessage(), 0, 50));
                sleep(4);
            }
            
            if (Cache::has("cancel_crawler_{$jobId}")) {
                Cache::forget("cancel_crawler_{$jobId}");
                Cache::forget("crawler_job_{$jobId}");
                return;
            }
        }
        
        $updateJobState(100, 'Scraping abgeschlossen! Aktualisiere Dashboard...');
        sleep(2); // Short display buffer for 100% completion
        
        Cache::forget("crawler_job_{$jobId}");
    }
    public function crawlAmazon(string $jobId, string $keyword = 'personalisiertes geschenk', int $pages = 1)
    {
        Log::info("StealthCrawler: Start crawling Amazon for '{$keyword}' (Job: {$jobId})");
        $apiKey = env('SCRAPER_API_KEY', '707ccc851a9e7c4759106d2f6e6bf764');
        $client = new Client(['timeout' => 60]);

        $updateJobState = function($progress, $status) use ($jobId, $keyword) {
            Cache::put("crawler_job_{$jobId}", [
                'id' => $jobId, 'keyword' => $keyword, 'platform' => 'Amazon',
                'progress' => $progress, 'status' => $status, 'is_running' => true
            ], 600);
        };

        $updateJobState(5, 'Initialisiere ScraperAPI für Amazon...');

        for ($page = 1; $page <= $pages; $page++) {
            if (Cache::has("cancel_crawler_{$jobId}")) {
                Cache::forget("cancel_crawler_{$jobId}");
                $updateJobState(0, "Vorgang durch Benutzer abgebrochen.");
                Cache::forget("crawler_job_{$jobId}");
                return;
            }

            $updateJobState(10 + (($page-1)/max(1, $pages)*80), "Lade Amazon Seite {$page} via ScraperAPI...");
            
            $targetUrl = "https://www.amazon.de/s?k=" . urlencode($keyword) . "&page={$page}";
            
            try {
                $response = $client->request('GET', 'http://api.scraperapi.com', [
                    'query' => [
                        'api_key' => $apiKey,
                        'url' => $targetUrl,
                        'country_code' => 'de',
                        'premium' => 'true'
                    ]
                ]);
                
                $html = $response->getBody()->getContents();
                $crawler = new Crawler($html);
                
                $crawler->filter('div[data-component-type="s-search-result"]')->each(function (Crawler $node) {
                    $titleNode = $node->filter('h2 span');
                    $title = $titleNode->count() > 0 ? $titleNode->text('') : '';
                    
                    $linkNode = $node->filter('.s-title-instructions-style a');
                    $url = $linkNode->count() > 0 ? 'https://www.amazon.de' . $linkNode->attr('href') : '';
                    
                    $imgNode = $node->filter('img.s-image');
                    $image_url = $imgNode->count() > 0 ? $imgNode->attr('src') : null;
                    
                    $priceWhole = $node->filter('.a-price-whole')->count() > 0 ? $node->filter('.a-price-whole')->text() : '';
                    $priceFraction = $node->filter('.a-price-fraction')->count() > 0 ? $node->filter('.a-price-fraction')->text() : '';
                    $priceStr = trim(str_replace([',', '.'], '', $priceWhole)) . '.' . trim($priceFraction);
                    $price = (float)$priceStr;
                    
                    $ratingNode = $node->filter('i[data-cy="reviews-ratings-slot"] span');
                    $ratingNode2 = $node->filter('i.a-icon-star-small span');
                    $ratingText = $ratingNode->count() > 0 ? $ratingNode->text() : ($ratingNode2->count() > 0 ? $ratingNode2->text() : 'N/A');
                    $rating = 5.0;
                    if (preg_match('/([\d,]+)/', $ratingText, $m)) {
                        $rating = (float)str_replace(',', '.', $m[1]);
                    }
                    
                    $reviewNode = $node->filter('span.s-underline-text');
                    $reviewCountText = $reviewNode->count() > 0 ? $reviewNode->text() : '0';
                    $review_count = (int)preg_replace('/[^0-9]/', '', $reviewCountText);
                    
                    $sales_volume = $review_count * 15;
                    $score = $this->calculateNicheScore($price, $sales_volume, $review_count);
                    
                    $isBulky = false;
                    $bulkyKeywords = ['schrank', 'esstisch', 'sofa', 'sessel', 'bett', 'kommode', 'fass', 'xxl', 'gartenbank', 'regal', 'möbel', 'truhe', 'sitzbank', 'säule'];
                    $titleLower = strtolower($title);
                    foreach ($bulkyKeywords as $keyword) {
                        if (strpos($titleLower, $keyword) !== false) {
                            $isBulky = true; break;
                        }
                    }
                    
                    if (!empty($title) && !empty($url) && !$isBulky) {
                        NicheProduct::updateOrCreate(
                            ['url' => $this->cleanUrl($url)],
                            [
                                'title' => trim($title),
                                'platform' => 'Amazon',
                                'price' => $price,
                                'sales_volume' => $sales_volume,
                                'rating' => $rating,
                                'review_count' => $review_count,
                                'image_url' => $image_url,
                                'niche_score' => $score,
                                'scraped_at' => now(),
                            ]
                        );
                    }
                });
            } catch (\Exception $e) {
                Log::error("StealthCrawler Amazon error: " . $e->getMessage());
                sleep(4);
            }
        }
        $updateJobState(100, 'Scraping abgeschlossen!');
        sleep(2);
        Cache::forget("crawler_job_{$jobId}");
    }

    public function crawlAlibaba(string $jobId, string $keyword = 'personalisiertes geschenk', int $pages = 1)
    {
        Log::info("StealthCrawler: Start crawling Alibaba for '{$keyword}' (Job: {$jobId})");
        $apiKey = env('SCRAPER_API_KEY', '707ccc851a9e7c4759106d2f6e6bf764');
        $client = new Client(['timeout' => 90]);

        $updateJobState = function($progress, $status) use ($jobId, $keyword) {
            Cache::put("crawler_job_{$jobId}", [
                'id' => $jobId, 'keyword' => $keyword, 'platform' => 'Alibaba',
                'progress' => $progress, 'status' => $status, 'is_running' => true
            ], 600);
        };

        $updateJobState(5, 'Initialisiere ScraperAPI (Premium Render) für Alibaba...');

        for ($page = 1; $page <= $pages; $page++) {
            if (Cache::has("cancel_crawler_{$jobId}")) {
                Cache::forget("cancel_crawler_{$jobId}");
                $updateJobState(0, "Vorgang durch Benutzer abgebrochen.");
                Cache::forget("crawler_job_{$jobId}");
                return;
            }

            $updateJobState(10 + (($page-1)/max(1, $pages)*80), "Lade Alibaba Seite {$page} via ScraperAPI...");
            
            $targetUrl = "https://www.alibaba.com/trade/search?SearchText=" . urlencode($keyword) . "&page={$page}";
            
            try {
                $response = $client->request('GET', 'http://api.scraperapi.com', [
                    'query' => [
                        'api_key' => $apiKey,
                        'url' => $targetUrl,
                        'country_code' => 'us',
                        'render' => 'true',
                        'premium' => 'true'
                    ]
                ]);
                
                $html = $response->getBody()->getContents();
                $crawler = new Crawler($html);
                
                $crawler->filter('.searchx-offer-item')->each(function (Crawler $node) {
                    $titleNode = $node->filter('h2.search-card-e-title span');
                    $title = $titleNode->count() > 0 ? $titleNode->text('') : '';

                    $linkNode = $node->filter('h2.search-card-e-title a');
                    $url = $linkNode->count() > 0 ? $linkNode->attr('href') : '';
                    if ($url !== '' && strpos($url, '//') === 0) {
                        $url = 'https:' . $url;
                    }

                    $imgNode = $node->filter('img.search-card-e-slider__img');
                    $image_url = $imgNode->count() > 0 ? $imgNode->attr('src') : null;
                    if ($image_url && strpos($image_url, '//') === 0) {
                        $image_url = 'https:' . $image_url;
                    }

                    $priceNode = $node->filter('.search-card-e-price-main');
                    $priceText = $priceNode->count() > 0 ? $priceNode->text() : '';
                    preg_match('/([0-9.,]+)/', $priceText, $m);
                    $price = isset($m[1]) ? (float)str_replace(',', '.', $m[1]) : 0;

                    $reviewNode = $node->filter('.search-card-e-review');
                    $rating = 5.0;
                    $review_count = 0;
                    if ($reviewNode->count() > 0) {
                        $text = $reviewNode->text();
                        if (preg_match('/([0-9.]+)\/5\.0\s*\(([^)]+)\)/', $text, $matches)) {
                            $rating = (float)$matches[1];
                            $review_count = (int)preg_replace('/[^0-9]/', '', $matches[2]);
                        }
                    }
                    
                    $sales_volume = $review_count * 15;
                    $score = $this->calculateNicheScore($price, $sales_volume, $review_count);
                    
                    $isBulky = false;
                    $bulkyKeywords = ['schrank', 'esstisch', 'sofa', 'sessel', 'bett', 'kommode', 'fass', 'xxl', 'gartenbank', 'regal', 'möbel', 'truhe', 'sitzbank', 'säule'];
                    $titleLower = strtolower($title);
                    foreach ($bulkyKeywords as $keyword) {
                        if (strpos($titleLower, $keyword) !== false) {
                            $isBulky = true; break;
                        }
                    }
                    
                    if (!empty($title) && !empty($url) && !$isBulky) {
                        NicheProduct::updateOrCreate(
                            ['url' => $this->cleanUrl($url)],
                            [
                                'title' => trim($title),
                                'platform' => 'Alibaba',
                                'price' => $price,
                                'sales_volume' => $sales_volume,
                                'rating' => $rating,
                                'review_count' => $review_count,
                                'image_url' => $image_url,
                                'niche_score' => $score,
                                'scraped_at' => now(),
                            ]
                        );
                    }
                });
            } catch (\Exception $e) {
                Log::error("StealthCrawler Alibaba error: " . $e->getMessage());
                sleep(4);
            }
        }
        $updateJobState(100, 'Scraping abgeschlossen!');
        sleep(2);
        Cache::forget("crawler_job_{$jobId}");
    }

    private function calculateNicheScore($price, $sales, $reviews): int
    {
        $baseScore = 10;
        
        if ($sales > 1000) $baseScore += 30;
        elseif ($sales > 500) $baseScore += 20;
        elseif ($sales > 100) $baseScore += 10;
        
        if ($price > 50) $baseScore += 20;
        elseif ($price > 20) $baseScore += 10;

        if ($sales > 100 && $reviews < 50) {
            $baseScore += 25; // Hidden Gem
        }

        if ($baseScore > 99) return 99;
        return $baseScore + rand(1, 10);
    }

    private function politeDelay()
    {
        sleep(rand(2, 6)); // Stealth waiting
    }

    private function getRandomHeaders(): array
    {
        $agent = $this->userAgents[array_rand($this->userAgents)];
        return [
            'User-Agent'      => $agent,
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'de,en-US;q=0.7,en;q=0.3',
            'Connection'      => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest'  => 'document',
            'Sec-Fetch-Mode'  => 'navigate',
        ];
    }
    
    private function cleanUrl($url) {
        $parsed = parse_url($url);
        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'www.etsy.com') . ($parsed['path'] ?? '');
    }
}
