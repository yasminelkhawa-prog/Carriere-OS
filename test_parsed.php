<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cv = App\Models\CvParsingResult::first();
echo "parsed_education:\n";
echo json_encode($cv->parsed_education, JSON_PRETTY_PRINT);
echo "\n\nparsed_experience:\n";
echo json_encode($cv->parsed_experience, JSON_PRETTY_PRINT);
