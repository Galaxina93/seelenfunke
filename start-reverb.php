<?php
// Gehe sicher in das Hauptverzeichnis des Shops
chdir(__DIR__);

// Führe den Artisan-Befehl über die System-Shell aus, sodass die Log-Umleitung funktioniert!
passthru("php artisan reverb:start --host=127.0.0.1 --port=6002 >> storage/logs/worker-reverb.log 2>&1");
