<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Candidate;
use App\Models\CvParsingResult;
use Illuminate\Support\Str;

echo "Seeding realistic AI data...\n";

$candidates = Candidate::all();

foreach ($candidates as $candidate) {
    echo "Processing candidate: {$candidate->full_name}\n";
    
    // First, check if they have applications
    if ($candidate->applications->isEmpty()) {
        continue;
    }

    $app = $candidate->applications->first();

    // Create realistic CV parsing result
    $cvResult = CvParsingResult::create([
        'company_id' => $candidate->company_id,
        'candidate_id' => $candidate->id,
        'application_id' => $app->id,
        'raw_text' => 'Dummy raw text for ' . $candidate->full_name,
        'parsed_skills' => ['Supply Chain', 'Négociation', 'Achats internationaux', 'Gestion des stocks', 'SAP', 'Logistique'],
        'parsed_education' => [
            [
                'degree_name' => 'Master en Achats Internationaux',
                'institution' => 'HEC Paris',
                'graduation_year' => '2019'
            ]
        ],
        'total_years_experience' => 5.5,
        'profile_summary' => "{$candidate->full_name} est un(e) professionnel(le) expérimenté(e) avec plus de 5 ans d'expérience dans les achats internationaux et la gestion de la chaîne d'approvisionnement. Reconnu(e) pour sa capacité à négocier des contrats complexes et à optimiser les coûts. Solide maîtrise de SAP et des processus logistiques.",
        'projects_json' => [],
        'certifications_json' => [],
        'keywords_json' => ['Supply Chain', 'Négociation', 'SAP'],
        'parsed_metadata_json' => [],
        'status' => 'ready',
        'score' => random_int(75, 95),
        'strengths_json' => [
            "Excellente maîtrise des achats internationaux.",
            "Formation académique de haut niveau (HEC Paris).",
            "Bonne expérience sur SAP."
        ],
        'weaknesses_json' => [
            "Manque d'expérience dans le secteur de la tech."
        ],
        'overall_recommendation' => "Profil très solide, recommandé pour un entretien approfondi.",
        'xai_summary' => "Le candidat correspond à 85% aux exigences du poste grâce à son expérience en négociation et sa maîtrise de SAP.",
        'evaluation_summary' => "Candidat avec un bon potentiel, à évaluer sur sa capacité d'adaptation à notre secteur."
    ]);
}

echo "Done seeding.\n";

echo "Done seeding.\n";
