<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Department;
use App\Models\Job;
use App\Models\JobDescriptionBlock;
use App\Models\JobPipelineStage;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class RealisticDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        $moroccanNames = [
            'Mohammed Benali', 'Fatima Ezzahra Alaoui', 'Ahmed Benkirane', 'Khadija Tazi', 'Youssef Benjelloun',
            'Zineb Chraibi', 'Omar Bensouda', 'Meryem Fassi Fihri', 'Hassan Berrada', 'Nadia Kettani',
            'Khalid Benhima', 'Sanaa Filali', 'Rachid Lahlou', 'Loubna Squalli', 'Abdelaziz Mezzour',
            'Houda Rami', 'Karim Benali', 'Samira Idrissi', 'Tariq Benomar', 'Imane Bensalah',
            'Mehdi Lahlou', 'Dounia Berrada', 'Amine Benabdallah', 'Salma Tazi', 'Adil Benkirane',
            'Hajar Alaoui', 'Hamza Chraibi', 'Soukaina Fassi', 'Ayoub Benbrahim', 'Nour Squalli',
            'Saad Bensouda', 'Ghita Laroui', 'Othmane Benali', 'Rim Berrada', 'Zakaria Kettani',
            'Sara Filali', 'Iliass Benomar', 'Nisrine Idrissi', 'Badr Lahlou', 'Yasmine Alaoui',
            'Reda Benjelloun', 'Hind Tazi', 'Bilal Benabdallah', 'Asmaa Chraibi', 'Jawad Fassi Fihri',
            'Manal Benhima', 'Soufiane Berrada', 'Wafa Laroui', 'Hicham Benali', 'Hafsa Kettani',
            'Anass Filali', 'Siham Squalli', 'Mouad Benomar', 'Fatima Zahra Idrissi', 'Rayan Lahlou',
            'Chaimae Bensouda', 'Sami Benbrahim', 'Maha Alaoui', 'Taha Benkirane', 'Lina Chraibi',
            'Yassine Fassi', 'Nour El Houda Tazi', 'Ziad Berrada', 'Lamia Benabdallah', 'Walid Kettani',
            'Aya Laroui', 'Oussama Filali', 'Khaoula Idrissi', 'Adam Squalli', 'Rabab Benali',
            'Ismail Benomar', 'Zineb Lahlou', 'Nassim Benbrahim', 'Sana Fassi Fihri', 'Hamid Alaoui',
            'Amira Chraibi', 'Nabil Bensouda', 'Ibtissam Berrada', 'Anas Kettani', 'Rania Filali',
            'Yousra Benkirane', 'Fouad Squalli', 'Mouna Idrissi', 'Zakaria Laroui', 'Ikram Benali',
            'Driss Benbrahim', 'Mariam Tazi', 'Rahim Fassi', 'Basma Alaoui', 'Monir Berrada',
            'Kaoutar Lahlou', 'Iliass Kettani', 'Selma Chraibi', 'Noureddine Filali', 'Jamila Benomar',
            'Abdelhak Squalli', 'Hala Idrissi', 'Samir Benabdallah', 'Nawal Laroui', 'Tarik Bensouda'
        ];
        shuffle($moroccanNames);
        $nameIndex = 0;

        $company = Company::query()->where('slug', 'numa-demo')->first()
            ?? Company::query()->where('status', Company::STATUS_ACTIVE)->first();

        if (!$company) {
            return;
        }

        $this->command->info('Seeding realistic data: Departments...');
        
        $departments = collect(['Ingénierie', 'Produit & Design', 'Ventes', 'Marketing', 'Ressources Humaines'])
            ->map(fn($name) => Department::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $name]
            ));

        $this->command->info('Seeding realistic data: Jobs...');
        
        $jobTitles = [
            'Ingénierie' => ['Développeur Senior Backend', 'Développeur Frontend React', 'Responsable Réalisation Électrique'],
            'Produit & Design' => ['Chef de Produit', 'Designer UX/UI'],
            'Ventes' => ['Responsable Commercial', 'Chargé d\'Affaires'],
            'Marketing' => ['Responsable Marketing Digital', 'Stratège de Contenu'],
            'Ressources Humaines' => ['Responsable Recrutement', 'C&B Manager']
        ];

        $jobs = collect();
        $stagesData = [
            ['stage_key' => 'applied', 'stage_label' => 'Applied', 'is_terminal' => false],
            ['stage_key' => 'screening', 'stage_label' => 'Screening', 'is_terminal' => false],
            ['stage_key' => 'interview', 'stage_label' => 'Interview', 'is_terminal' => false],
            ['stage_key' => 'offer', 'stage_label' => 'Offer', 'is_terminal' => false],
            ['stage_key' => 'hired', 'stage_label' => 'Hired', 'is_terminal' => true],
            ['stage_key' => 'rejected', 'stage_label' => 'Rejected', 'is_terminal' => true],
        ];

        foreach ($jobTitles as $deptName => $titles) {
            $dept = $departments->firstWhere('name', $deptName);
            foreach ($titles as $title) {
                $job = Job::withoutGlobalScopes()->updateOrCreate(
                    ['company_id' => $company->id, 'title' => $title],
                    [
                        'department_id' => $dept->id,
                        'location' => $faker->city,
                        'status' => $faker->randomElement([Job::STATUS_PUBLISHED, Job::STATUS_PUBLISHED, Job::STATUS_PUBLISHED, Job::STATUS_DRAFT, Job::STATUS_ARCHIVED]),
                        'blind_mode_active' => false,
                        'salary_budget_max' => $faker->numberBetween(60000, 150000),
                    ]
                );
                
                $jobs->push($job);

                // Add Description Block
                JobDescriptionBlock::withoutGlobalScopes()->updateOrCreate(
                    ['job_id' => $job->id, 'block_type' => 'overview'],
                    [
                        'block_content_json' => json_encode(['text' => $faker->paragraphs(2, true)]),
                        'display_order' => 1,
                    ]
                );

                // Add Pipeline Stages
                foreach ($stagesData as $index => $stage) {
                    JobPipelineStage::withoutGlobalScopes()->updateOrCreate(
                        ['job_id' => $job->id, 'stage_key' => $stage['stage_key']],
                        [
                            'stage_label' => $stage['stage_label'],
                            'display_order' => $index + 1,
                            'is_terminal' => $stage['is_terminal'],
                        ]
                    );
                }
            }
        }

        $this->command->info('Seeding realistic data: 100 Candidates & Applications...');

        $password = Hash::make('password');
        
        foreach ($jobs as $job) {
            $stages = JobPipelineStage::withoutGlobalScopes()->where('job_id', $job->id)->orderBy('display_order')->get();
            $stagesMap = $stages->keyBy('stage_key');
            
            $appliedStage = $stagesMap->get('applied');
            $screeningStage = $stagesMap->get('screening');
            $interviewStage = $stagesMap->get('interview');
            $offerStage = $stagesMap->get('offer');
            $hiredStage = $stagesMap->get('hired');
            
            if (!$appliedStage || !$screeningStage || !$interviewStage || !$offerStage || !$hiredStage) {
                continue;
            }
            
            $candidatesByFunnel = [
                'hired' => 1,
                'offer' => $faker->numberBetween(0, 1),
                'interview' => $faker->numberBetween(1, 2),
                'screening' => $faker->numberBetween(1, 2),
                'applied' => $faker->numberBetween(3, 8),
            ];
            
            $stagesToHit = [
                'hired' => [$appliedStage, $screeningStage, $interviewStage, $offerStage, $hiredStage],
                'offer' => [$appliedStage, $screeningStage, $interviewStage, $offerStage],
                'interview' => [$appliedStage, $screeningStage, $interviewStage],
                'screening' => [$appliedStage, $screeningStage],
                'applied' => [$appliedStage],
            ];
            
            foreach ($candidatesByFunnel as $level => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $email = $faker->unique()->safeEmail;
                    
                    $user = User::query()->updateOrCreate(
                        ['email' => $email],
                        [
                            'email_verified_at' => now(),
                            'password' => $password,
                            'platform_role' => User::PLATFORM_NONE,
                            'active' => true,
                        ]
                    );

                    $fullName = $moroccanNames[$nameIndex % count($moroccanNames)];
                    $nameIndex++;

                    Profile::query()->updateOrCreate(
                        ['user_id' => $user->id],
                        ['full_name' => $fullName, 'locale' => 'en']
                    );

                    CompanyMembership::query()->updateOrCreate(
                        ['company_id' => $company->id, 'user_id' => $user->id],
                        ['company_role' => CompanyMembership::ROLE_CANDIDATE, 'membership_status' => CompanyMembership::STATUS_ACTIVE]
                    );

                    $candidate = Candidate::withoutGlobalScopes()->updateOrCreate(
                        ['company_id' => $company->id, 'email' => $email],
                        [
                            'user_id' => $user->id,
                            'full_name' => $fullName,
                            'phone' => $faker->phoneNumber,
                            'location' => $faker->city . ', ' . $faker->country,
                        ]
                    );
                    
                    foreach ($stagesToHit[$level] as $stage) {
                        $status = Application::STATUS_ACTIVE;
                        if ($stage->stage_key === 'hired') $status = Application::STATUS_HIRED;
                        if ($stage->stage_key === 'rejected') $status = Application::STATUS_REJECTED;

                        Application::withoutGlobalScopes()->updateOrCreate(
                            [
                                'company_id' => $company->id,
                                'candidate_id' => $candidate->id,
                                'job_id' => $job->id,
                                'current_stage_id' => $stage->id,
                            ],
                            [
                                'status' => $status,
                                'source_type' => $faker->randomElement(['career_page', 'linkedin', 'referral', 'indeed']),
                                'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                            ]
                        );
                    }
                }
            }
        }

        $this->command->info('Realistic funnel data seeding completed.');
    }
}
