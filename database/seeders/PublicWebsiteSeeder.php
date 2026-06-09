<?php

namespace Database\Seeders;

use App\Models\AboutPage;
use App\Models\Company;
use App\Models\CompanyValue;
use App\Models\ContactInquiry;
use App\Models\Department;
use App\Models\Job;
use App\Models\JobDescriptionBlock;
use App\Models\JobPipelineStage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PublicWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('about_pages') || ! Schema::hasTable('contact_inquiries')) {
            $this->command?->warn('Skipping PublicWebsiteSeeder: required tables are missing. Run migrations first.');

            return;
        }

        $company = Company::query()->firstOrCreate(
            ['slug' => 'numa-demo'],
            [
                'name' => 'numa Demo',
                'status' => Company::STATUS_ACTIVE,
                'brand_logo_url' => null,
            ]
        );

        $departments = [
            'Engineering' => null,
            'People Operations' => null,
        ];

        foreach (array_keys($departments) as $departmentName) {
            $department = Department::withoutGlobalScopes()->firstOrCreate(
                [
                    'company_id' => (string) $company->id,
                    'name' => $departmentName,
                ]
            );

            $departments[$departmentName] = (string) $department->id;
        }

        $valueRows = [
            ['title' => 'Innovation', 'description' => 'We improve hiring with practical technology that solves real recruiting problems.', 'order' => 1],
            ['title' => 'Respect', 'description' => 'We treat candidates and teams with transparency, empathy, and professionalism.', 'order' => 2],
            ['title' => 'Accountability', 'description' => 'We own outcomes, keep promises, and build trust through consistent delivery.', 'order' => 3],
            ['title' => 'Candidate Experience', 'description' => 'We design every touchpoint to make hiring clear, fair, and human.', 'order' => 4],
        ];

        foreach ($valueRows as $row) {
            CompanyValue::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => (string) $company->id,
                    'title' => $row['title'],
                ],
                [
                    'description' => $row['description'],
                    'display_order' => $row['order'],
                    'icon_name' => null,
                ]
            );
        }

        $aboutPage = AboutPage::query()->where('is_active', true)->first();
        if (! $aboutPage instanceof AboutPage) {
            $aboutPage = new AboutPage();
        }

        $aboutPage->fill([
            'title' => 'Building Careers With Clarity and Confidence',
            'subtitle' => 'A modern hiring experience for candidates, recruiters, and growing teams.',
            'hero_image_url' => null,
            'story_text' => implode("\n\n", [
                'We started with a simple belief: hiring should feel clear, respectful, and decisive for everyone involved.',
                'Our platform helps organizations streamline recruitment operations while giving candidates transparent progress and timely communication.',
                'By combining structured workflows, analytics, and responsible AI support, we help teams hire better and help people find meaningful opportunities.',
            ]),
            'mission' => 'Streamline recruitment with transparency, speed, and fairness.',
            'vision' => 'Become a trusted digital hiring operating system for modern teams.',
            'culture_text' => 'Innovation, respect, accountability, and a strong candidate-first mindset guide every product decision we make.',
            'stats_json' => [
                ['label' => 'Open roles', 'value' => '150+'],
                ['label' => 'Hiring teams supported', 'value' => '40+'],
                ['label' => 'Candidate-first approach', 'value' => '100%'],
                ['label' => 'Fast response process', 'value' => '<48h'],
            ],
            'is_active' => true,
        ]);
        $aboutPage->save();

        $jobRows = [
            [
                'title' => 'Senior Backend Engineer',
                'department_id' => $departments['Engineering'],
                'location' => 'Remote',
                'status' => Job::STATUS_PUBLISHED,
            ],
            [
                'title' => 'Product Designer',
                'department_id' => $departments['Engineering'],
                'location' => 'New York',
                'status' => Job::STATUS_PUBLISHED,
            ],
            [
                'title' => 'Talent Acquisition Specialist',
                'department_id' => $departments['People Operations'],
                'location' => 'Toronto',
                'status' => Job::STATUS_PUBLISHED,
            ],
            [
                'title' => 'Customer Success Manager',
                'department_id' => $departments['People Operations'],
                'location' => 'Remote',
                'status' => Job::STATUS_PUBLISHED,
            ],
            [
                'title' => 'Internal Hiring Program Lead',
                'department_id' => $departments['People Operations'],
                'location' => 'Paris',
                'status' => Job::STATUS_DRAFT,
            ],
        ];

        foreach ($jobRows as $jobRow) {
            $job = Job::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => (string) $company->id,
                    'title' => $jobRow['title'],
                ],
                [
                    'department_id' => $jobRow['department_id'],
                    'location' => $jobRow['location'],
                    'status' => $jobRow['status'],
                    'blind_mode_active' => false,
                    'salary_budget_max' => null,
                ]
            );

            JobDescriptionBlock::withoutGlobalScopes()->where('job_id', (string) $job->id)->delete();

            JobDescriptionBlock::withoutGlobalScopes()->create([
                'job_id' => (string) $job->id,
                'block_type' => 'overview',
                'block_content_json' => ['text' => 'Join our team to build reliable hiring workflows and candidate-facing experiences.'],
                'display_order' => 1,
            ]);
            JobDescriptionBlock::withoutGlobalScopes()->create([
                'job_id' => (string) $job->id,
                'block_type' => 'responsibilities',
                'block_content_json' => ['text' => 'Own core deliverables, collaborate with cross-functional teams, and improve execution quality.'],
                'display_order' => 2,
            ]);
            JobDescriptionBlock::withoutGlobalScopes()->create([
                'job_id' => (string) $job->id,
                'block_type' => 'requirements',
                'block_content_json' => ['text' => 'Demonstrated experience, strong communication, and a candidate-centric mindset.'],
                'display_order' => 3,
            ]);

            if (! JobPipelineStage::withoutGlobalScopes()->where('job_id', (string) $job->id)->exists()) {
                JobPipelineStage::withoutGlobalScopes()->create([
                    'job_id' => (string) $job->id,
                    'stage_key' => 'screening',
                    'stage_label' => 'Screening',
                    'display_order' => 1,
                    'is_terminal' => false,
                ]);

                JobPipelineStage::withoutGlobalScopes()->create([
                    'job_id' => (string) $job->id,
                    'stage_key' => 'closed',
                    'stage_label' => 'Closed',
                    'display_order' => 2,
                    'is_terminal' => true,
                ]);
            }
        }

        $superadminId = (string) (User::query()->where('platform_role', User::PLATFORM_SUPERADMIN)->value('id') ?? '');

        ContactInquiry::query()->updateOrCreate(
            ['email' => 'public.inquiry.one@example.test', 'subject' => 'Partnership and hiring query'],
            [
                'full_name' => 'Alex Morgan',
                'phone' => '+1-555-0101',
                'message' => 'We are evaluating recruitment platforms and want a demo focused on multi-team hiring workflows.',
                'status' => ContactInquiry::STATUS_NEW,
                'assigned_to_user_id' => $superadminId !== '' ? $superadminId : null,
                'notes' => 'High-priority enterprise lead.',
                'source' => ContactInquiry::SOURCE_PUBLIC_CONTACT_FORM,
            ]
        );

        ContactInquiry::query()->updateOrCreate(
            ['email' => 'public.inquiry.two@example.test', 'subject' => 'Candidate support question'],
            [
                'full_name' => 'Sophie Martin',
                'phone' => null,
                'message' => 'I submitted an application and want to confirm if my portfolio file was uploaded correctly.',
                'status' => ContactInquiry::STATUS_IN_PROGRESS,
                'assigned_to_user_id' => $superadminId !== '' ? $superadminId : null,
                'notes' => 'Coordinate with candidate support.',
                'source' => ContactInquiry::SOURCE_PUBLIC_CONTACT_FORM,
            ]
        );

        ContactInquiry::query()->updateOrCreate(
            ['email' => 'public.inquiry.three@example.test', 'subject' => 'Media request'],
            [
                'full_name' => 'Jordan Lee',
                'phone' => '+1-555-0103',
                'message' => 'Please share a media contact for an upcoming article about hiring technology trends.',
                'status' => ContactInquiry::STATUS_RESOLVED,
                'assigned_to_user_id' => $superadminId !== '' ? $superadminId : null,
                'notes' => 'Responded with media kit link.',
                'source' => ContactInquiry::SOURCE_PUBLIC_CONTACT_FORM,
            ]
        );
    }
}


