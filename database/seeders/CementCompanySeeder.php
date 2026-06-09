<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Department;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\RecruitmentNeed;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CementCompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'numa Demo')->first()
            ?? Company::where('status', 'active')->first()
            ?? Company::first();

        if (!$company) {
            $this->command->error('Aucune société trouvée.');
            return;
        }

        $this->command->info("Ciblage : {$company->name} ({$company->id})");

        // Nettoyage complet
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Application::where('company_id', $company->id)->delete();
        Candidate::where('company_id', $company->id)->delete();
        RecruitmentNeed::where('company_id', $company->id)->delete();
        Job::where('company_id', $company->id)->delete();
        Department::where('company_id', $company->id)->delete();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $faker = \Faker\Factory::create('fr_FR');

        // 1. Directions
        $deptNames = [
            'Production & Cimenterie',
            'Ingénierie & Projets',
            'Ressources Humaines',
            'Finances & Contrôle de Gestion',
            'Supply Chain & Logistique',
        ];

        $departments = [];
        foreach ($deptNames as $dn) {
            $departments[$dn] = Department::create([
                'company_id' => $company->id,
                'name' => $dn,
            ]);
        }

        // 2. Liste COMPLETE : 39 postes (1 besoin = 1 job, synchronisés)
        // Chaque entrée = 1 recruitment_need + 1 job posting
        $postes = [
            // ---- Production & Cimenterie (12 postes) ----
            ['dept' => 'Production & Cimenterie', 'title' => 'Ingénieur Procédés Ciment', 'wt' => 'WC', 'site' => 'Safi'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Ingénieur Procédés Ciment', 'wt' => 'WC', 'site' => 'Agadir'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Chef de Carrière', 'wt' => 'WC', 'site' => 'Agadir'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Chef de Carrière', 'wt' => 'WC', 'site' => 'Nador'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Opérateur de Salle Centrale', 'wt' => 'BC', 'site' => 'Safi'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Opérateur de Salle Centrale', 'wt' => 'BC', 'site' => 'Casablanca'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Technicien Laboratoire Qualité', 'wt' => 'BC', 'site' => 'Agadir'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Chef de Quart Production', 'wt' => 'WC', 'site' => 'Nador'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Chef de Quart Production', 'wt' => 'WC', 'site' => 'Safi'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Rondier Cuisson', 'wt' => 'BC', 'site' => 'Safi'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Rondier Broyage', 'wt' => 'BC', 'site' => 'Laayoune'],
            ['dept' => 'Production & Cimenterie', 'title' => 'Préparateur Cru', 'wt' => 'BC', 'site' => 'Casablanca'],

            // ---- Ingénierie & Projets (8 postes) ----
            ['dept' => 'Ingénierie & Projets', 'title' => 'Chef de Projet Mécanique', 'wt' => 'WC', 'site' => 'Casablanca'],
            ['dept' => 'Ingénierie & Projets', 'title' => 'Ingénieur Fiabilité et Maintenance', 'wt' => 'WC', 'site' => 'Safi'],
            ['dept' => 'Ingénierie & Projets', 'title' => 'Ingénieur Fiabilité et Maintenance', 'wt' => 'WC', 'site' => 'Agadir'],
            ['dept' => 'Ingénierie & Projets', 'title' => 'Technicien Maintenance Électrique', 'wt' => 'BC', 'site' => 'Agadir'],
            ['dept' => 'Ingénierie & Projets', 'title' => 'Technicien Maintenance Électrique', 'wt' => 'BC', 'site' => 'Nador'],
            ['dept' => 'Ingénierie & Projets', 'title' => 'Automaticien Industriel', 'wt' => 'WC', 'site' => 'Nador'],
            ['dept' => 'Ingénierie & Projets', 'title' => 'Ingénieur Instrumentation', 'wt' => 'WC', 'site' => 'Safi'],
            ['dept' => 'Ingénierie & Projets', 'title' => 'Soudeur Tuyauteur', 'wt' => 'BC', 'site' => 'Laayoune'],

            // ---- Ressources Humaines (7 postes) ----
            ['dept' => 'Ressources Humaines', 'title' => 'HR Business Partner Usine', 'wt' => 'WC', 'site' => 'Safi'],
            ['dept' => 'Ressources Humaines', 'title' => 'HR Business Partner Usine', 'wt' => 'WC', 'site' => 'Agadir'],
            ['dept' => 'Ressources Humaines', 'title' => 'Responsable Développement RH', 'wt' => 'WC', 'site' => 'Casablanca'],
            ['dept' => 'Ressources Humaines', 'title' => 'Gestionnaire Paie et Admin', 'wt' => 'WC', 'site' => 'Casablanca'],
            ['dept' => 'Ressources Humaines', 'title' => 'Gestionnaire Paie et Admin', 'wt' => 'WC', 'site' => 'Nador'],
            ['dept' => 'Ressources Humaines', 'title' => 'Chargé de Formation', 'wt' => 'WC', 'site' => 'Casablanca'],
            ['dept' => 'Ressources Humaines', 'title' => 'Assistant RH Site', 'wt' => 'BC', 'site' => 'Laayoune'],

            // ---- Finances & Contrôle de Gestion (6 postes) ----
            ['dept' => 'Finances & Contrôle de Gestion', 'title' => 'Contrôleur de Gestion Industriel', 'wt' => 'WC', 'site' => 'Agadir'],
            ['dept' => 'Finances & Contrôle de Gestion', 'title' => 'Contrôleur de Gestion Industriel', 'wt' => 'WC', 'site' => 'Safi'],
            ['dept' => 'Finances & Contrôle de Gestion', 'title' => 'Comptable Fournisseurs', 'wt' => 'WC', 'site' => 'Casablanca'],
            ['dept' => 'Finances & Contrôle de Gestion', 'title' => 'Auditeur Interne Senior', 'wt' => 'WC', 'site' => 'Casablanca'],
            ['dept' => 'Finances & Contrôle de Gestion', 'title' => 'Trésorier Adjoint', 'wt' => 'WC', 'site' => 'Casablanca'],
            ['dept' => 'Finances & Contrôle de Gestion', 'title' => 'Analyste Financier', 'wt' => 'WC', 'site' => 'Casablanca'],

            // ---- Supply Chain & Logistique (6 postes) ----
            ['dept' => 'Supply Chain & Logistique', 'title' => 'Responsable Expéditions', 'wt' => 'WC', 'site' => 'Safi'],
            ['dept' => 'Supply Chain & Logistique', 'title' => 'Responsable Expéditions', 'wt' => 'WC', 'site' => 'Agadir'],
            ['dept' => 'Supply Chain & Logistique', 'title' => 'Acheteur Matières Premières', 'wt' => 'WC', 'site' => 'Casablanca'],
            ['dept' => 'Supply Chain & Logistique', 'title' => 'Magasinier Pièces de Rechange', 'wt' => 'BC', 'site' => 'Agadir'],
            ['dept' => 'Supply Chain & Logistique', 'title' => 'Magasinier Pièces de Rechange', 'wt' => 'BC', 'site' => 'Nador'],
            ['dept' => 'Supply Chain & Logistique', 'title' => 'Planificateur Transport', 'wt' => 'WC', 'site' => 'Casablanca'],
        ];

        $this->command->info("Création de " . count($postes) . " postes synchronisés (1 need = 1 job)");

        $canaux = ['LinkedIn', 'Rekrute', 'Jobboards', 'Cabinet', 'Cooptation'];
        $typesRecrutement = ['Création de poste', 'Remplacement'];
        $contrats = ['CDI', 'CDD', 'Intérim'];
        $createdJobs = [];

        // Pipeline stages
        $stages = JobPipelineStage::orderBy('display_order')->get();
        if ($stages->isEmpty()) {
            $stageNames = ['Nouveau', 'Qualification', 'Entretien RH', 'Entretien Manager', 'Offre', 'Recruté'];
            foreach ($stageNames as $order => $name) {
                $stages->push(JobPipelineStage::create([
                    'stage_label' => $name,
                    'display_order' => $order + 1,
                    'is_system_default' => true,
                ]));
            }
        }

        // Étape "Recruté" pour le statut hired
        $stageRecrute = $stages->firstWhere('stage_label', 'Recruté') ?? $stages->last();

        foreach ($postes as $idx => $p) {
            $dept = $departments[$p['dept']];
            $gender = $faker->randomElement(['M', 'F']);

            // Décider le statut de ce poste
            // ~30% clôturé, ~45% en cours, ~25% pas encore lancé
            $rand = rand(1, 100);
            if ($rand <= 30) {
                $needStatus = 'Clôturé';
                $jobStatus = 'archived';  // DB constraint: draft/published/archived
            } elseif ($rand <= 75) {
                $needStatus = 'En cours';
                $jobStatus = 'published';
            } else {
                $needStatus = 'Pas encore lancé';
                $jobStatus = 'draft';
            }

            // Titre unique pour le job (ajouter le site pour différencier)
            $jobTitle = $p['title'];
            $jobLocation = $p['site'] . ', Maroc';

            // Créer le Job
            $job = Job::create([
                'company_id' => $company->id,
                'department_id' => $dept->id,
                'title' => $jobTitle,
                'location' => $jobLocation,
                'employment_type' => 'full_time',
                'status' => $jobStatus,
            ]);
            $createdJobs[] = $job;

            // Créer le RecruitmentNeed (lié au même titre, même site)
            $isClosed = $needStatus === 'Clôturé';
            RecruitmentNeed::create([
                'company_id' => $company->id,
                'department_id' => $dept->id,
                'year' => 2026,
                'site' => $p['site'],
                'departing_position_title' => $faker->randomElement([$p['title'], null]),
                'departure_date' => $faker->optional(0.5)->dateTimeBetween('-6 months', 'now'),
                'departure_reason' => $faker->randomElement(['Démission', 'Retraite', 'Licenciement', 'Mutation']),
                'new_recruit_position_title' => $p['title'],
                'budget_approved' => $faker->boolean(80),
                'status' => $needStatus,
                'contract_type' => $faker->randomElement($contrats),
                'worker_type' => $p['wt'],
                'recruitment_type' => $faker->randomElement($typesRecrutement),
                'internal_posting' => $faker->boolean(20),
                'external_sourcing' => true,
                'sourcing_tools' => $faker->randomElement($canaux),
                'new_recruit_name' => $isClosed ? $faker->name($gender === 'M' ? 'male' : 'female') : null,
                'gender' => $gender,
                'expected_start_date' => $faker->dateTimeBetween('now', '+6 months'),
            ]);

            // Si le poste est clôturé, créer un candidat embauché
            if ($isClosed) {
                $hiredName = $faker->firstName . ' ' . $faker->lastName;
                $candidate = Candidate::create([
                    'company_id' => $company->id,
                    'full_name' => $hiredName,
                    'email' => $faker->unique()->safeEmail,
                    'phone' => $faker->phoneNumber,
                    'location' => $p['site'],
                ]);
                Application::create([
                    'company_id' => $company->id,
                    'candidate_id' => $candidate->id,
                    'job_id' => $job->id,
                    'current_stage_id' => $stageRecrute->id,
                    'status' => 'hired',
                    'source_type' => $faker->randomElement(['linkedin', 'career_page', 'job_board', 'referral']),
                    'created_at' => $faker->dateTimeBetween('-3 months', '-1 week'),
                ]);
            }
        }

        // 3. Candidatures supplémentaires (actives) sur les postes published
        $publishedJobs = collect($createdJobs)->filter(fn($j) => $j->status === 'published');

        for ($i = 0; $i < 120; $i++) {
            $firstName = $faker->firstName;
            $lastName = $faker->lastName;

            $candidate = Candidate::create([
                'company_id' => $company->id,
                'full_name' => $firstName . ' ' . $lastName,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'location' => $faker->randomElement(['Casablanca', 'Safi', 'Agadir', 'Nador', 'Rabat', 'Marrakech']),
            ]);

            $job = $faker->randomElement($publishedJobs->values()->all());
            // Don't use "Recruté" stage for active candidates
            $activeStages = $stages->filter(fn($s) => $s->stage_label !== 'Recruté');
            $stage = $faker->randomElement($activeStages->values()->all());

            $appStatus = 'active';
            if (rand(0, 10) > 8) {
                $appStatus = 'rejected';
            }

            Application::create([
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
                'current_stage_id' => $stage->id,
                'status' => $appStatus,
                'source_type' => $faker->randomElement(['linkedin', 'career_page', 'job_board', 'referral']),
                'created_at' => $faker->dateTimeBetween('-3 months', 'now'),
            ]);
        }

        // Résumé
        $this->command->info("✅ {$company->name} : " . count($postes) . " postes créés");
        $this->command->info("   Jobs: " . Job::where('company_id', $company->id)->count());
        $this->command->info("   Needs: " . RecruitmentNeed::where('company_id', $company->id)->count());
        $this->command->info("   Published: " . Job::where('company_id', $company->id)->where('status', 'published')->count());
        $this->command->info("   Draft: " . Job::where('company_id', $company->id)->where('status', 'draft')->count());
        $this->command->info("   Closed: " . Job::where('company_id', $company->id)->where('status', 'closed')->count());
    }
}
