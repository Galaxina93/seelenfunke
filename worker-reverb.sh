#!/bin/bash
# Wechsel in das Hauptverzeichnis der App
cd /html/seelenfunke-stage

# Lade verifizierte PHP Version und starte Reverb
# Wir loggen alles in eine Textdatei, damit wir JEDEN Fehler sehen!
php artisan reverb:start --host="127.0.0.1" --port=6002 > storage/logs/worker-reverb.log 2>&1
