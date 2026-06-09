<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$job = App\Models\Job::with('pipelineStages')->first();
echo "Job {$job->id} has " . $job->pipelineStages->count() . " stages.\n";
foreach($job->pipelineStages as $s) {
    echo " - {$s->stage_label}\n";
}
