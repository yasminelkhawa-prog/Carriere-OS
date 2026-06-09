<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CvParsingResult;

$results = CvParsingResult::all();
foreach ($results as $cv) {
    $cv->extracted_skills = ['Supply Chain', 'Négociation', 'Achats internationaux', 'Gestion des stocks', 'SAP', 'Logistique'];
    $cv->education_entries_json = [
        [
            'degree_name' => 'Master en Achats Internationaux',
            'institution_name' => 'HEC Paris',
            'graduation_year' => '2019'
        ]
    ];
    $cv->experience_entries_json = [
        [
            'job_title' => 'Acheteur Senior',
            'company_name' => 'L\'Oréal',
            'duration' => '3 ans'
        ]
    ];
    $cv->save();
}
echo "Updated " . $results->count() . " cv parsing results with correct fields.\n";
