<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$j = \App\Models\Job::pluck("title")->toArray();
$r = \App\Models\RecruitmentNeed::pluck("new_recruit_position_title")->toArray();
print_r(array_unique(array_diff($r, $j)));