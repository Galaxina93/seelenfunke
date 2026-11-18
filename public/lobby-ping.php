<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

echo '{"ok":true,"server_ts_ms":' . (int) round(microtime(true) * 1000) . '}';
