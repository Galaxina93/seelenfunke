<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\StealthCrawlerService;

class RunProductNicheCrawlerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    protected string $jobId;
    protected string $platform;
    protected string $keyword;

    public function __construct(string $jobId, string $platform = 'Etsy', string $keyword = 'personalisiertes geschenk')
    {
        $this->jobId = $jobId;
        $this->platform = $platform;
        $this->keyword = $keyword;
    }

    public function handle(StealthCrawlerService $crawler)
    {
        $platform = strtolower($this->platform);

        if ($platform === 'etsy') {
            $crawler->crawlEtsy($this->jobId, $this->keyword, 2);
        } elseif ($platform === 'amazon') {
            $crawler->crawlAmazon($this->jobId, $this->keyword, 2);
        } elseif ($platform === 'alibaba') {
            $crawler->crawlAlibaba($this->jobId, $this->keyword, 2);
        }
    }
}
