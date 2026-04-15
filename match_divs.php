<?php
$content = file_get_contents('resources/views/livewire/shop/ai/ai-chat.blade.php');

$chatStart = strpos($content, '<div x-show="activeTab === \'chat\'"');
$plansStart = strpos($content, '<!-- PLANS TAB CONTENT -->');

$substr = substr($content, $chatStart, $plansStart - $chatStart);

$lines = explode("\n", $substr);
$depth = 0;
foreach($lines as $i => $line) {
    $opens = substr_count(strtolower($line), '<div ');
    $opens += substr_count(strtolower($line), '<div>');
    
    $closes = substr_count(strtolower($line), '</div>');
    
    $depth += ($opens - $closes);
    if ($opens > 0 || $closes > 0) {
        echo str_pad($i + 151, 4, "0", STR_PAD_LEFT) . " | +$opens -$closes | Depth: $depth | " . trim($line) . "\n";
    }
}
