<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Job;
use App\Models\JobDescriptionBlock;
use App\Models\JobPipelineStage;
use App\Models\JobWeightingConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JobTestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Use numa Demo (from DatabaseSeeder), or fall back to any active company.
        $company = Company::query()->where('slug', 'numa-demo')->first()
            ?? Company::query()->where('status', Company::STATUS_ACTIVE)->firstOrFail();

        // -----------------------------------------------------------------
        // 1. Departments
        // -----------------------------------------------------------------
        $engineering = Department::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Engineering'],
            []
        );

        $marketing = Department::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Marketing'],
            []
        );

        // -----------------------------------------------------------------
        // 2. Job
        // -----------------------------------------------------------------
        $job = Job::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'title' => 'Senior Fullstack Developer'],
            [
                'department_id' => $engineering->id,
                'location' => 'Remote',
                'status' => Job::STATUS_PUBLISHED,
                'blind_mode_active' => false,
                'salary_budget_max' => 120000,
            ]
        );

        // -----------------------------------------------------------------
        // 3. Description Blocks (4 blocks)
        // -----------------------------------------------------------------
        DB::table('job_description_blocks')->where('job_id', $job->id)->delete();

        $blocks = [
            [
                'job_id' => $job->id,
                'block_type' => 'overview',
                'block_content_json' => json_encode([
                    'text' => 'We are looking for a Senior Fullstack Developer to join our core engineering team. You will work across our entire stack â€” from crafting beautiful, performant React frontends to building rock-solid Laravel APIs and data pipelines.',
                ]),
                'display_order' => 1,
            ],
            [
                'job_id' => $job->id,
                'block_type' => 'responsibilities',
                'block_content_json' => json_encode([
                    'text' => "- Design, build and maintain high-quality web applications using Laravel and React\n- Collaborate with Product and Design to define and deliver features\n- Write clean, well-tested code with emphasis on performance and scalability\n- Participate in code reviews and mentor junior engineers\n- Contribute to architecture discussions and technical roadmap",
                ]),
                'display_order' => 2,
            ],
            [
                'job_id' => $job->id,
                'block_type' => 'requirements',
                'block_content_json' => json_encode([
                    'text' => "- 4+ years of experience with PHP/Laravel and modern JavaScript (React or Vue)\n- Solid understanding of relational databases (PostgreSQL or MySQL)\n- Experience with RESTful API design and consumption\n- Familiarity with CI/CD pipelines and cloud deployment (AWS, GCP, or Azure)\n- Strong communication skills in English; French is a plus",
                ]),
                'display_order' => 3,
            ],
            [
                'job_id' => $job->id,
                'block_type' => 'benefits',
                'block_content_json' => json_encode([
                    'text' => "- Competitive salary up to \$120,000 USD\n- Fully remote with flexible hours\n- Annual professional development budget of \$2,000\n- 5 weeks of paid vacation\n- Access to our comprehensive health and wellness program",
                ]),
                'display_order' => 4,
            ],
        ];

        foreach ($blocks as $block) {
            JobDescriptionBlock::withoutGlobalScopes()->create($block);
        }

        // -----------------------------------------------------------------
        // 4. Pipeline Stages (7 stages per spec)
        // -----------------------------------------------------------------
        DB::table('job_pipeline_stages')->where('job_id', $job->id)->delete();

        $stages = [
            ['stage_key' => 'applied',    'stage_label' => 'Applied',    'display_order' => 1, 'is_terminal' => false],
            ['stage_key' => 'screening',  'stage_label' => 'Screening',  'display_order' => 2, 'is_terminal' => false],
            ['stage_key' => 'interview',  'stage_label' => 'Interview',  'display_order' => 3, 'is_terminal' => false],
            ['stage_key' => 'tech_test',  'stage_label' => 'Tech Test',  'display_order' => 4, 'is_terminal' => false],
            ['stage_key' => 'offer',      'stage_label' => 'Offer',      'display_order' => 5, 'is_terminal' => false],
            ['stage_key' => 'hired',      'stage_label' => 'Hired',      'display_order' => 6, 'is_terminal' => true],
            ['stage_key' => 'rejected',   'stage_label' => 'Rejected',   'display_order' => 7, 'is_terminal' => true],
        ];

        foreach ($stages as $stage) {
            JobPipelineStage::withoutGlobalScopes()->create([
                'job_id' => $job->id,
                ...$stage,
            ]);
        }

        // -----------------------------------------------------------------
        // 5. Weighting Config (must sum to 100)
        // -----------------------------------------------------------------
        JobWeightingConfig::withoutGlobalScopes()->updateOrCreate(
            ['job_id' => $job->id],
            [
                'weighting_json' => [
                    'skill'      => 35,
                    'experience' => 30,
                    'culture'    => 20,
                    'potential'  => 15,
                    'total'      => 100,
                ],
            ]
        );

        $this->command->info("âœ… Test job seeded:");
        $this->command->info("   Company  : {$company->name} (slug: {$company->slug})");
        $this->command->info("   Job      : {$job->title} [{$job->status}]");
        $this->command->info("   Dept     : Engineering + Marketing created");
        $this->command->info("   Blocks   : 4 description blocks");
        $this->command->info("   Pipeline : 7 stages (5 non-terminal + 2 terminal)");
        $this->command->info("   Weights  : 35/30/20/15 = 100");
        $this->command->info("   Career   : http://127.0.0.1:8000/careers/{$company->slug}");
    }
}

