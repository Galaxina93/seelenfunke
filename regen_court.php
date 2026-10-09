<?php
require '/var/www/html/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$md = file_get_contents('/var/www/html/court_temp.md');
$lines = explode("\n", $md);
$html = '';
$inTable = false;
$inList = false;

for ($i = 0; $i < count($lines); $i++) {
    $t = trim($lines[$i]);
    
    // Ignore raw <br> tags completely
    $t = preg_replace('/<br\s*\/?>/i', '', $t);
    $t = trim($t);
    
    if (str_starts_with($t, '# ')) {
        $html .= '<h1>' . htmlspecialchars(substr($t, 2)) . '</h1>';
        continue;
    }
    if (str_starts_with($t, '## ')) {
        $html .= '<h2>' . htmlspecialchars(substr($t, 3)) . '</h2>';
        continue;
    }
    if (str_starts_with($t, '### ')) {
        $html .= '<h3>' . htmlspecialchars(substr($t, 4)) . '</h3>';
        continue;
    }
    if ($t === '---') {
        $html .= '<hr>';
        continue;
    }
    if (str_starts_with($t, '|')) {
        if (!$inTable) {
            $inTable = true;
            $html .= '<table>';
        }
        if (str_contains($t, '---')) continue;
        $cols = array_map('trim', explode('|', trim($t, '|')));
        $html .= '<tr>';
        foreach ($cols as $c) {
            $cH = preg_replace('/\\*\\*(.*?)\\*\\*/', '<strong>$1</strong>', htmlspecialchars($c));
            $html .= '<td>' . $cH . '</td>';
        }
        $html .= '</tr>';
        continue;
    } else {
        if ($inTable) {
            $inTable = false;
            $html .= '</table>';
        }
    }
    if (str_starts_with($t, '- ')) {
        if (!$inList) {
            $inList = true;
            $html .= '<ul>';
        }
        $cH = preg_replace('/\\*\\*(.*?)\\*\\*/', '<strong>$1</strong>', htmlspecialchars(substr($t, 2)));
        $html .= '<li>' . $cH . '</li>';
        continue;
    } else {
        if ($inList) {
            $inList = false;
            $html .= '</ul>';
        }
    }
    
    // Signature block handling
    if (str_starts_with($t, 'Gifhorn, den')) {
        $html .= '<div style="margin-top: 25px;"><p>' . htmlspecialchars($t) . '</p>';
        $html .= '<div style="margin-top: 35px; border-bottom: 1px solid #475569; width: 260px;"></div>';
        $html .= '<p style="margin-top: 5px;"><strong>Alina Steinhauer</strong> (Antragstellerin)</p></div>';
        // skip subsequent signature lines
        while ($i + 1 < count($lines)) {
            $next = trim($lines[$i + 1]);
            if (str_contains($next, 'Alina Steinhauer') || str_contains($next, '____') || empty($next) || str_contains($next, '<br')) {
                $i++;
            } else {
                break;
            }
        }
        continue;
    }
    
    if (!empty($t) && !str_starts_with($t, '____') && !str_contains($t, 'Alina Steinhauer (Antragstellerin)')) {
        $pH = preg_replace('/\\*\\*(.*?)\\*\\*/', '<strong>$1</strong>', htmlspecialchars($t));
        $html .= '<p>' . $pH . '</p>';
    }
}

$full = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 9.5pt; line-height: 1.42; margin: 25px 22px; color: #0f172a; }
h1 { font-size: 13pt; color: #0369a1; border-bottom: 2px solid #0284c7; padding-bottom: 3px; margin-bottom: 12px; }
h2 { font-size: 10.5pt; color: #0369a1; margin-top: 14px; margin-bottom: 4px; }
h3 { font-size: 9.5pt; color: #0369a1; margin-top: 10px; margin-bottom: 3px; }
table { width: 100%; border-collapse: collapse; margin: 8px 0; }
td { border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 8.5pt; vertical-align: top; }
p { margin: 3.5px 0; }
ul { margin: 3px 0 5px 16px; }
li { margin-bottom: 1.5px; }
hr { border: none; border-top: 1px solid #e2e8f0; margin: 10px 0; }
</style></head><body>' . $html . '</body></html>';

$opt = new Options();
$opt->set('isRemoteEnabled', true);
$opt->set('defaultFont', 'DejaVu Sans');
$d = new Dompdf($opt);
$d->loadHtml($full);
$d->setPaper('A4', 'portrait');
$d->render();
file_put_contents('/var/www/html/court_temp.pdf', $d->output());
echo "Court PDF rendered perfectly without raw tags.\n";
