<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        // Pro-User-Ausweichpfade fuer /tmp-Testlogs & kompilierte Views (protected_regular=2 im Container)
        \Tests\Support\TestingTmpPathGuard::apply();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
