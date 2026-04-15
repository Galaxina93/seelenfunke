<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$agent = App\Models\Ai\AiAgent::first();
if (!$agent) die("No agent found");

class DummyComponent extends \Livewire\Component {
    public function __construct() {}
    public $session_id;
    public function getGlobalFilesProperty() {
        $sessionId = \Session::getId();
        echo "Session ID: " . $sessionId . "\n";
        return [];
    }
}
$memories = App\Models\Ai\AiChatMemory::get();
echo "Total memories: " . count($memories) . "\n";
foreach($memories as $m) {
    if(!empty($m->context_data['local_uploads'])) {
        print_r($m->context_data['local_uploads']);
    }
}
