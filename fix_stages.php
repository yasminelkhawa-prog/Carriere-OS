<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\Application;
use Illuminate\Support\Str;

$jobs = Job::all();
$defaultStages = [
    ['key' => 'applied', 'label' => 'Applied', 'terminal' => false],
    ['key' => 'screen', 'label' => 'Screening', 'terminal' => false],
    ['key' => 'interview', 'label' => 'Interview', 'terminal' => false],
    ['key' => 'offer', 'label' => 'Offer', 'terminal' => false],
    ['key' => 'hired', 'label' => 'Hired', 'terminal' => true],
    ['key' => 'rejected', 'label' => 'Rejected', 'terminal' => true],
];

foreach ($jobs as $job) {
    if ($job->pipelineStages()->count() === 0) {
        $order = 0;
        foreach ($defaultStages as $s) {
            $job->pipelineStages()->create([
                'id' => (string) Str::uuid(),
                'stage_key' => $s['key'],
                'stage_label' => $s['label'],
                'display_order' => $order++,
                'is_terminal' => $s['terminal'],
            ]);
        }
    }
}

$apps = Application::all();
foreach ($apps as $app) {
    if ($app->current_stage_id) {
        // Find the stage label of the current stage
        $oldStage = JobPipelineStage::find($app->current_stage_id);
        if ($oldStage && $oldStage->job_id !== $app->job_id) {
            // Find the corresponding stage in the app's job
            $newStage = JobPipelineStage::where('job_id', $app->job_id)
                ->where('stage_key', $oldStage->stage_key)
                ->first();
            
            if ($newStage) {
                $app->current_stage_id = $newStage->id;
                $app->save();
            }
        }
    } else {
        // If no stage, set to first stage
        $firstStage = JobPipelineStage::where('job_id', $app->job_id)->orderBy('display_order')->first();
        if ($firstStage) {
            $app->current_stage_id = $firstStage->id;
            $app->save();
        }
    }
}
echo "Fixed pipeline stages!\n";
