<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$appl = App\Models\Application::withoutGlobalScopes()->whereNotNull('current_stage_id')->first();
if ($appl) {
    echo "Stage ID: " . $appl->current_stage_id . "\n";
    echo "Stage Job ID: " . $appl->currentStage->job_id . "\n";
    echo "App Job ID: " . $appl->job_id . "\n";
} else {
    echo "No applications with current_stage_id\n";
}
