<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\Profile;
use App\Models\SocialPost;
use App\Models\SocialReaction;
use App\Models\User;
use App\Services\SocialHub\SocialHubService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SocialHubModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('social_posts') || ! Schema::hasTable('social_reactions')) {
            $this->command?->warn('Skipping SocialHubModuleSeeder: social hub tables not found. Run migrations first.');

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

        if ($company->status !== Company::STATUS_ACTIVE) {
            $company->forceFill(['status' => Company::STATUS_ACTIVE])->save();
        }

        $job = Job::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        if ($job->status !== Job::STATUS_PUBLISHED) {
            $job->forceFill(['status' => Job::STATUS_PUBLISHED])->save();
        }

        $users = [
            'admin' => $this->ensureMember(
                companyId: (string) $company->id,
                role: CompanyMembership::ROLE_COMPANY_ADMIN,
                email: 'socialhub.admin@example.com',
                fullName: 'Numa CEO'
            ),
            'recruiter' => $this->ensureMember(
                companyId: (string) $company->id,
                role: CompanyMembership::ROLE_RECRUITER,
                email: 'socialhub.recruiter@example.com',
                fullName: 'Social Hub Recruiter'
            ),
            'manager' => $this->ensureMember(
                companyId: (string) $company->id,
                role: CompanyMembership::ROLE_MANAGER,
                email: 'socialhub.hr@example.com',
                fullName: 'Social Hub HR Manager'
            ),
            'employee' => $this->ensureMember(
                companyId: (string) $company->id,
                role: CompanyMembership::ROLE_EMPLOYEE,
                email: 'socialhub.employee@example.com',
                fullName: 'Social Hub Employee'
            ),
        ];

        if (
            ! $users['admin'] instanceof User
            || ! $users['recruiter'] instanceof User
            || ! $users['manager'] instanceof User
            || ! $users['employee'] instanceof User
        ) {
            return;
        }

        $posts = [
            'welcome' => SocialPost::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => (string) $company->id,
                    'author_user_id' => (string) $users['recruiter']->id,
                    'type' => SocialPost::TYPE_WELCOME,
                ],
                [
                    'content_text' => "Meet Ayesha, our new {$job->title}!\n\nFun facts:\n- Favorite movie: Interstellar\n- Coffee or tea: Coffee\n- Hidden talent: Speaks three languages",
                    'visibility' => SocialPost::VISIBILITY_PUBLIC,
                    'media_url' => null,
                    'reactions' => app(SocialHubService::class)->normalizeReactionSummary([]),
                    'related_job_id' => (string) $job->id,
                    'metadata_json' => [
                        'automation' => 'contract_signed_demo',
                        'application_id' => 'demo-social-seed',
                        'interaction' => [
                            'label' => __('social_hub.feed.say_hi_button'),
                            'reaction' => SocialReaction::TYPE_WAVE,
                        ],
                    ],
                    'poll_question_text' => null,
                    'poll_options_json' => null,
                ]
            ),
            'kudos' => SocialPost::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => (string) $company->id,
                    'author_user_id' => (string) $users['employee']->id,
                    'type' => SocialPost::TYPE_KUDOS,
                ],
                [
                    'content_text' => 'Thank you for stepping in late and saving the release plan.',
                    'visibility' => SocialPost::VISIBILITY_TEAM_ONLY,
                    'media_url' => null,
                    'reactions' => app(SocialHubService::class)->normalizeReactionSummary([]),
                    'related_job_id' => null,
                    'metadata_json' => [
                        'kudos' => [
                            'sender_user_id' => (string) $users['employee']->id,
                            'recipient_user_id' => (string) $users['recruiter']->id,
                            'recipient_name' => 'Social Hub Recruiter',
                            'category_key' => 'mvp',
                            'category_label' => 'The MVP - for saving a project',
                            'icon' => "\u{1F3C6}",
                        ],
                    ],
                    'poll_question_text' => null,
                    'poll_options_json' => null,
                ]
            ),
            'announcement' => SocialPost::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => (string) $company->id,
                    'author_user_id' => (string) $users['admin']->id,
                    'type' => SocialPost::TYPE_ANNOUNCEMENT,
                ],
                [
                    'content_text' => 'CEO update: we are expanding the AI team this quarter. Internal mobility applications are now open.',
                    'visibility' => SocialPost::VISIBILITY_TEAM_ONLY,
                    'media_url' => null,
                    'reactions' => app(SocialHubService::class)->normalizeReactionSummary([]),
                    'related_job_id' => (string) $job->id,
                    'metadata_json' => [
                        'cta' => [
                            'label' => __('social_hub.feed.view_roles'),
                        ],
                    ],
                    'poll_question_text' => null,
                    'poll_options_json' => null,
                ]
            ),
        ];

        $reactionBlueprints = [
            ['post' => 'welcome', 'type' => SocialReaction::TYPE_WAVE, 'user' => 'employee'],
            ['post' => 'welcome', 'type' => SocialReaction::TYPE_HEART, 'user' => 'manager'],
            ['post' => 'kudos', 'type' => SocialReaction::TYPE_CLAP, 'user' => 'admin'],
            ['post' => 'kudos', 'type' => SocialReaction::TYPE_FIRE, 'user' => 'manager'],
            ['post' => 'announcement', 'type' => SocialReaction::TYPE_FIRE, 'user' => 'recruiter'],
            ['post' => 'announcement', 'type' => SocialReaction::TYPE_CLAP, 'user' => 'employee'],
        ];

        foreach ($reactionBlueprints as $blueprint) {
            $post = $posts[$blueprint['post']] ?? null;
            $user = $users[$blueprint['user']] ?? null;

            if (! $post instanceof SocialPost || ! $user instanceof User) {
                continue;
            }

            if ((string) $post->author_user_id === (string) $user->id) {
                continue;
            }

            SocialReaction::withoutGlobalScopes()->firstOrCreate([
                'company_id' => (string) $company->id,
                'post_id' => (string) $post->id,
                'reaction_type' => (string) $blueprint['type'],
                'user_id' => (string) $user->id,
            ]);
        }

        $socialHubService = app(SocialHubService::class);
        foreach ($posts as $post) {
            if ($post instanceof SocialPost) {
                $socialHubService->syncReactionSummary($post);
            }
        }

        $this->command?->info('Social Hub demo content seeded: automated welcome, kudos recognition, and CEO internal mobility announcement.');
    }

    private function ensureMember(string $companyId, string $role, string $email, string $fullName): ?User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'password' => Hash::make('password'),
                'platform_role' => User::PLATFORM_NONE,
                'active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (Schema::hasTable('profiles')) {
            Profile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $fullName,
                    'locale' => 'en',
                    'avatar_url' => null,
                ]
            );
        }

        CompanyMembership::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => (string) $user->id,
            ],
            [
                'company_role' => $role,
                'membership_status' => CompanyMembership::STATUS_ACTIVE,
            ]
        );

        return $user;
    }
}
