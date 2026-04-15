<?php
$content = file_get_contents('resources/views/livewire/shop/ai/ai-chat.blade.php');

$chatStart = strpos($content, '<div x-show="activeTab === \'chat\'"');
$plansStart = strpos($content, '<!-- PLANS TAB CONTENT -->');

if ($chatStart === false) die("NOT FOUND");

$substr = substr($content, $chatStart, $plansStart - $chatStart);

// Count `<div` and `</div>`
$divOpens = preg_match_all('/<div\b[^>]*>/i', $substr);
$divCloses = preg_match_all('/<\/div>/i', $substr);

echo "Chat Start found. Length to Plans: " . strlen($substr) . "\n";
echo "Divs opened: $divOpens\n";
echo "Divs closed: $divCloses\n";

if ($divOpens != $divCloses) {
    echo "MISMATCH! The chat tab does NOT close correctly!\n";
} else {
    echo "MATCH! The chat tab closes perfectly.\n";
}
