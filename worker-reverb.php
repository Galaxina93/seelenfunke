<?php
// Wechsle in das korrekte Laravel-Verzeichnis
chdir(__DIR__);

// Gebe alles für das Log aus
echo "Starte Reverb Worker Wrapper...\n";
echo "Aktuelles Verzeichnis: " . getcwd() . "\n";

// Binde artisan ein. Laravel wird $argv (die Parameter) automatisch verarbeiten.
require __DIR__ . '/artisan';
