<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$agent = App\Models\Ai\AiAgent::first();
if (!$agent) die("No agent");

$component = app(\Livewire\LivewireManager::class)->new('shop.ai.ai-chat');
$component->agents = collect([$agent]);
$component->activeAgentIds = [$agent->id];

$html = \Livewire\Livewire::mount('shop.ai.ai-chat');
$content = $html->html();

if (strpos($content, 'files-grid') !== false) {
    echo "Files grid found in HTML!\n";
    preg_match('/<div wire:key="tab-files"[^>]*>.*?<\/div>\s*<\/div>/is', $content, $matches);
    if (!empty($matches)) {
        echo "Extracted Tab Files HTML:\n";
        echo substr(strip_tags($matches[0], '<div><span><p>'), 0, 1000) . "\n";
    }
} else {
    echo "Files grid NOT FOUND in HTML.\n";
}
