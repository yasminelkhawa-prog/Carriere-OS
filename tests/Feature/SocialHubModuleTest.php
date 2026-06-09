<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Contract;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\SocialPost;
use App\Models\SocialPulsePollVote;
use App\Models\SocialReaction;
use App\Models\User;
use Database\Seeders\SocialHubModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialHubModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_hub_feed_has_red_watercooler_theme_and_filtering_with_select_inputs(): void
    {
        $context = $this->createSocialContext();

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['employee']->id,
            'type' => SocialPost::TYPE_KUDOS,
            'visibility' => SocialPost::VISIBILITY_TEAM_ONLY,
            'content_text' => 'Kudos filter target',
            'media_url' => null,
            'reactions' => [],
        ]);

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['admin']->id,
            'type' => SocialPost::TYPE_ANNOUNCEMENT,
            'visibility' => SocialPost::VISIBILITY_TEAM_ONLY,
            'content_text' => 'Announcement should be filtered out',
            'media_url' => null,
            'reactions' => [],
        ]);

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('social-hub.index', [
                'post_types' => [SocialPost::TYPE_KUDOS],
                'author_user_id' => (string) $context['employee']->id,
            ]));

        $response->assertOk();
        $response->assertSee('social-hub-red', false);
        $response->assertSee(__('social_hub.index.watercooler_label'));
        $response->assertSee('name="post_types[]"', false);
        $response->assertSee('data-placeholder=', false);
        $response->assertSee('Kudos filter target');
        $response->assertDontSee('Announcement should be filtered out');
    }

    public function test_post_permissions_follow_role_rules_and_internal_market_link_rules(): void
    {
        $context = $this->createSocialContext();
        $job = $this->createPublishedJob($context['company']);

        $cases = [
            [
                'user' => $context['admin'],
                'payload' => $this->standardPayload(SocialPost::TYPE_ANNOUNCEMENT, 'Admin announcement'),
                'allowed' => true,
            ],
            [
                'user' => $context['manager'],
                'payload' => $this->standardPayload(SocialPost::TYPE_ANNOUNCEMENT, 'HR announcement'),
                'allowed' => true,
            ],
            [
                'user' => $context['recruiter'],
                'payload' => $this->standardPayload(SocialPost::TYPE_WELCOME, 'Recruiter welcome', SocialPost::VISIBILITY_PUBLIC),
                'allowed' => true,
            ],
            [
                'user' => $context['recruiter'],
                'payload' => $this->standardPayload(SocialPost::TYPE_ANNOUNCEMENT, 'Recruiter without job link'),
                'allowed' => false,
                'error_field' => 'type',
            ],
            [
                'user' => $context['recruiter'],
                'payload' => array_merge(
                    $this->standardPayload(SocialPost::TYPE_ANNOUNCEMENT, 'Recruiter internal market post'),
                    ['related_job_id' => (string) $job->id]
                ),
                'allowed' => true,
            ],
            [
                'user' => $context['employee'],
                'payload' => [
                    'mode' => 'kudos',
                    'type' => SocialPost::TYPE_KUDOS,
                    'visibility' => SocialPost::VISIBILITY_TEAM_ONLY,
                    'kudos_recipient_user_id' => (string) $context['recruiter']->id,
                    'kudos_category' => 'mvp',
                    'kudos_message' => 'Saved the release timeline today.',
                ],
                'allowed' => true,
            ],
            [
                'user' => $context['employee'],
                'payload' => $this->standardPayload(SocialPost::TYPE_WELCOME, 'Employee welcome attempt'),
                'allowed' => false,
                'error_field' => 'type',
            ],
        ];

        foreach ($cases as $case) {
            $response = $this->actingAs($case['user'])
                ->withSession(['active_company_id' => (string) $context['company']->id])
                ->from(route('social-hub.index'))
                ->post(route('social-hub.posts.store'), $case['payload']);

            $response->assertRedirect(route('social-hub.index'));

            if ($case['allowed']) {
                $this->assertDatabaseHas('social_posts', [
                    'company_id' => (string) $context['company']->id,
                    'author_user_id' => (string) $case['user']->id,
                    'type' => (string) $case['payload']['type'],
                ]);
            } else {
                $response->assertSessionHasErrors([$case['error_field'] ?? 'type']);
                $this->assertDatabaseMissing('social_posts', [
                    'company_id' => (string) $context['company']->id,
                    'author_user_id' => (string) $case['user']->id,
                    'content_text' => (string) ($case['payload']['content_text'] ?? ''),
                ]);
            }
        }
    }

    public function test_reactions_are_idempotent_and_post_author_cannot_react(): void
    {
        $context = $this->createSocialContext();

        $post = SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['recruiter']->id,
            'type' => SocialPost::TYPE_WELCOME,
            'visibility' => SocialPost::VISIBILITY_PUBLIC,
            'content_text' => 'Reaction idempotency post',
            'media_url' => null,
            'reactions' => [],
        ]);

        $first = $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('social-hub.reactions.store', ['post' => $post->id]), [
                'reaction_type' => SocialReaction::TYPE_FIRE,
            ]);

        $first->assertRedirect(route('social-hub.index'));
        $first->assertSessionHas('status', __('social_hub.flash.reaction_added'));

        $second = $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('social-hub.reactions.store', ['post' => $post->id]), [
                'reaction_type' => SocialReaction::TYPE_FIRE,
            ]);

        $second->assertRedirect(route('social-hub.index'));
        $second->assertSessionHas('status', __('social_hub.flash.reaction_unchanged'));

        $this->assertSame(
            1,
            SocialReaction::withoutGlobalScopes()
                ->where('post_id', (string) $post->id)
                ->where('reaction_type', SocialReaction::TYPE_FIRE)
                ->count()
        );

        $authorAttempt = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('social-hub.reactions.store', ['post' => $post->id]), [
                'reaction_type' => SocialReaction::TYPE_HEART,
            ]);

        $authorAttempt->assertRedirect(route('social-hub.index'));
        $authorAttempt->assertSessionHas('error', __('social_hub.flash.self_reaction_forbidden'));

        $post->refresh();
        $this->assertSame(1, (int) ($post->reactions[SocialReaction::TYPE_FIRE] ?? 0));
        $this->assertSame(0, (int) ($post->reactions[SocialReaction::TYPE_HEART] ?? 0));
    }

    public function test_candidates_can_access_social_hub_and_only_candidate_visible_posts_are_visible(): void
    {
        $context = $this->createSocialContext();
        $candidate = $context['candidate_record'];

        [$job, $appliedStage] = $this->createJobWithStages($context['company']);

        $application = Application::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'candidate_id' => (string) $candidate->id,
            'job_id' => (string) $job->id,
            'current_stage_id' => (string) $appliedStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
            'source_detail' => null,
            'utm_source' => null,
            'utm_campaign' => null,
            'utm_medium' => null,
        ]);

        $publicPost = SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['recruiter']->id,
            'type' => SocialPost::TYPE_WELCOME,
            'visibility' => SocialPost::VISIBILITY_PUBLIC,
            'content_text' => 'Public welcome post',
            'media_url' => null,
            'reactions' => [],
        ]);

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['employee']->id,
            'type' => SocialPost::TYPE_KUDOS,
            'visibility' => SocialPost::VISIBILITY_PUBLIC,
            'content_text' => 'Public kudos post',
            'media_url' => null,
            'reactions' => [],
        ]);

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['admin']->id,
            'type' => SocialPost::TYPE_ANNOUNCEMENT,
            'visibility' => SocialPost::VISIBILITY_PUBLIC,
            'content_text' => 'Public announcement post',
            'media_url' => null,
            'reactions' => [],
        ]);

        $teamOnlyPost = SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['admin']->id,
            'type' => SocialPost::TYPE_ANNOUNCEMENT,
            'visibility' => SocialPost::VISIBILITY_TEAM_ONLY,
            'content_text' => 'Team-only update',
            'media_url' => null,
            'reactions' => [],
        ]);

        $ideaPost = SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['manager']->id,
            'type' => SocialPost::TYPE_IDEA,
            'visibility' => SocialPost::VISIBILITY_PUBLIC,
            'content_text' => 'Public idea post that should be hidden',
            'media_url' => null,
            'reactions' => [],
            'poll_question_text' => 'Should we move lunch to 1 PM?',
            'poll_options_json' => [
                ['key' => 'yes', 'emoji' => "\u{2705}", 'label' => 'Yes'],
                ['key' => 'no', 'emoji' => "\u{274C}", 'label' => 'No'],
            ],
        ]);

        $allowed = $this->actingAs($context['candidate'])
            ->get(route('candidate.social-hub.index', ['company' => $context['company']->slug]));

        $allowed->assertOk();
        $allowed->assertSee('Public welcome post');
        $allowed->assertSee('Public kudos post');
        $allowed->assertSee('Public announcement post');
        $allowed->assertDontSee('Team-only update');
        $allowed->assertDontSee('Public idea post that should be hidden');

        $forbiddenReaction = $this->actingAs($context['candidate'])
            ->post(route('candidate.social-hub.reactions.store', [
                'company' => $context['company']->slug,
                'post' => $teamOnlyPost->id,
            ]), [
                'reaction_type' => SocialReaction::TYPE_HEART,
            ]);

        $forbiddenReaction->assertRedirect(route('candidate.social-hub.index', ['company' => $context['company']->slug]));
        $forbiddenReaction->assertSessionHas('error', __('social_hub.flash.reaction_forbidden'));

        $forbiddenIdeaReaction = $this->actingAs($context['candidate'])
            ->post(route('candidate.social-hub.reactions.store', [
                'company' => $context['company']->slug,
                'post' => $ideaPost->id,
            ]), [
                'reaction_type' => SocialReaction::TYPE_HEART,
            ]);

        $forbiddenIdeaReaction->assertRedirect(route('candidate.social-hub.index', ['company' => $context['company']->slug]));
        $forbiddenIdeaReaction->assertSessionHas('error', __('social_hub.flash.reaction_forbidden'));

        $forbiddenIdeaVote = $this->actingAs($context['candidate'])
            ->post(route('candidate.social-hub.poll-votes.store', [
                'company' => $context['company']->slug,
                'post' => $ideaPost->id,
            ]), [
                'option_key' => 'yes',
            ]);

        $forbiddenIdeaVote->assertRedirect(route('candidate.social-hub.index', ['company' => $context['company']->slug]));
        $forbiddenIdeaVote->assertSessionHas('error', __('social_hub.flash.poll_forbidden'));

        $this->assertDatabaseMissing('social_pulse_poll_votes', [
            'company_id' => (string) $context['company']->id,
            'post_id' => (string) $ideaPost->id,
            'user_id' => (string) $context['candidate']->id,
        ]);

        $allowedReaction = $this->actingAs($context['candidate'])
            ->post(route('candidate.social-hub.reactions.store', [
                'company' => $context['company']->slug,
                'post' => $publicPost->id,
            ]), [
                'reaction_type' => SocialReaction::TYPE_WAVE,
            ]);

        $allowedReaction->assertRedirect(route('candidate.social-hub.index', ['company' => $context['company']->slug]));

        $this->assertDatabaseHas('social_reactions', [
            'company_id' => (string) $context['company']->id,
            'post_id' => (string) $publicPost->id,
            'user_id' => (string) $context['candidate']->id,
            'reaction_type' => SocialReaction::TYPE_WAVE,
        ]);
    }

    public function test_contract_signing_creates_automated_welcome_post_with_fun_facts(): void
    {
        $context = $this->createSocialContext();
        $candidate = $context['candidate_record'];

        [$job, , $screeningStage] = $this->createJobWithStages($context['company']);

        $application = Application::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'candidate_id' => (string) $candidate->id,
            'job_id' => (string) $job->id,
            'current_stage_id' => (string) $screeningStage->id,
            'status' => Application::STATUS_HIRED,
            'source_type' => 'referral',
            'source_detail' => null,
            'utm_source' => null,
            'utm_campaign' => null,
            'utm_medium' => null,
        ]);

        Contract::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $application->id,
            'contract_file_url' => 'private/contracts/sample.pdf',
            'contract_status' => Contract::STATUS_SENT,
            'signed_at' => null,
            'signer_user_id' => null,
            'signature_method' => Contract::SIGNATURE_METHOD_TYPED,
            'audit_metadata_json' => [],
        ]);

        $response = $this->actingAs($context['candidate'])
            ->post(route('candidate.contract.sign', [
                'company' => $context['company']->slug,
                'application' => $application->id,
            ]), [
                'typed_signature' => 'Candidate Sign',
                'acknowledgement' => '1',
            ]);

        $response->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $response->assertSessionHas('status', __('candidate_portal.onboarding.contract.signed_success'));

        $welcomePost = SocialPost::withoutGlobalScopes()
            ->where('company_id', (string) $context['company']->id)
            ->where('type', SocialPost::TYPE_WELCOME)
            ->where('metadata_json->automation', 'contract_signed')
            ->where('metadata_json->application_id', (string) $application->id)
            ->first();

        $this->assertNotNull($welcomePost);
        $this->assertSame((string) $job->id, (string) $welcomePost?->related_job_id);
        $this->assertStringContainsString('Meet', (string) $welcomePost?->content_text);
        $this->assertStringContainsString('Fun facts', (string) $welcomePost?->content_text);
        $this->assertSame(
            __('social_hub.feed.say_hi_button'),
            (string) data_get($welcomePost?->metadata_json, 'interaction.label')
        );
    }

    public function test_pulse_poll_votes_are_upserted_per_user(): void
    {
        $context = $this->createSocialContext();

        $ideaPost = SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['manager']->id,
            'type' => SocialPost::TYPE_IDEA,
            'visibility' => SocialPost::VISIBILITY_PUBLIC,
            'content_text' => 'How is your energy this week?',
            'media_url' => null,
            'reactions' => [],
            'poll_question_text' => 'How is your energy this week?',
            'poll_options_json' => [
                ['key' => 'high', 'emoji' => "\u{1F50B}", 'label' => 'High'],
                ['key' => 'medium', 'emoji' => "\u{26A1}", 'label' => 'Medium'],
                ['key' => 'low', 'emoji' => "\u{1FAAB}", 'label' => 'Low'],
            ],
        ]);

        $firstVote = $this->actingAs($context['employee'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('social-hub.poll-votes.store', ['post' => $ideaPost->id]), [
                'option_key' => 'high',
            ]);

        $firstVote->assertRedirect(route('social-hub.index'));
        $firstVote->assertSessionHas('status', __('social_hub.flash.poll_vote_saved'));

        $secondVote = $this->actingAs($context['employee'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('social-hub.poll-votes.store', ['post' => $ideaPost->id]), [
                'option_key' => 'low',
            ]);

        $secondVote->assertRedirect(route('social-hub.index'));

        $this->assertSame(
            1,
            SocialPulsePollVote::withoutGlobalScopes()
                ->where('post_id', (string) $ideaPost->id)
                ->where('user_id', (string) $context['employee']->id)
                ->count()
        );

        $this->assertDatabaseHas('social_pulse_poll_votes', [
            'company_id' => (string) $context['company']->id,
            'post_id' => (string) $ideaPost->id,
            'user_id' => (string) $context['employee']->id,
            'option_key' => 'low',
        ]);
    }

    public function test_social_hub_demo_seeder_creates_welcome_kudos_and_announcement_with_job_link(): void
    {
        $this->seed(SocialHubModuleSeeder::class);

        $company = Company::query()->where('slug', 'numa-demo')->first();
        $this->assertNotNull($company);

        $posts = SocialPost::withoutGlobalScopes()
            ->where('company_id', (string) $company?->id)
            ->get();

        $this->assertCount(3, $posts);
        $this->assertSame(1, $posts->where('type', SocialPost::TYPE_WELCOME)->count());
        $this->assertSame(1, $posts->where('type', SocialPost::TYPE_KUDOS)->count());
        $this->assertSame(1, $posts->where('type', SocialPost::TYPE_ANNOUNCEMENT)->count());

        $announcement = $posts->firstWhere('type', SocialPost::TYPE_ANNOUNCEMENT);
        $welcome = $posts->firstWhere('type', SocialPost::TYPE_WELCOME);

        $this->assertNotNull($announcement);
        $this->assertNotNull($announcement?->related_job_id);
        $this->assertSame(__('social_hub.feed.view_roles'), (string) data_get($announcement?->metadata_json, 'cta.label'));

        $this->assertNotNull($welcome);
        $this->assertSame(SocialPost::VISIBILITY_PUBLIC, (string) $welcome?->visibility);
        $this->assertSame(__('social_hub.feed.say_hi_button'), (string) data_get($welcome?->metadata_json, 'interaction.label'));

        $this->assertSame(6, SocialReaction::withoutGlobalScopes()->count());
        $this->assertIsArray($welcome?->reactions);
    }

    public function test_ai_cards_show_emerging_leaders_and_internal_mobility_suggestions(): void
    {
        $context = $this->createSocialContext();

        $suggestedJobA = Job::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'department_id' => null,
            'title' => 'Platform Delivery Lead',
            'location' => 'Remote',
            'status' => Job::STATUS_PUBLISHED,
            'blind_mode_active' => false,
            'salary_budget_max' => 110000,
        ]);

        $suggestedJobB = Job::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'department_id' => null,
            'title' => 'Innovation Program Manager',
            'location' => 'Hybrid',
            'status' => Job::STATUS_PUBLISHED,
            'blind_mode_active' => false,
            'salary_budget_max' => 105000,
        ]);

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['manager']->id,
            'type' => SocialPost::TYPE_KUDOS,
            'visibility' => SocialPost::VISIBILITY_TEAM_ONLY,
            'content_text' => 'Outstanding release ownership and delivery leadership on platform rollout.',
            'media_url' => null,
            'reactions' => [],
            'related_job_id' => null,
            'metadata_json' => [
                'kudos' => [
                    'sender_user_id' => (string) $context['manager']->id,
                    'recipient_user_id' => (string) $context['employee']->id,
                    'recipient_name' => (string) $context['employee']->email,
                    'category_key' => 'mvp',
                    'category_label' => 'The MVP - for saving a project',
                    'icon' => "\u{1F3C6}",
                ],
            ],
            'poll_question_text' => null,
            'poll_options_json' => null,
        ]);

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['admin']->id,
            'type' => SocialPost::TYPE_KUDOS,
            'visibility' => SocialPost::VISIBILITY_TEAM_ONLY,
            'content_text' => 'Great innovation mindset and clear communication with stakeholders.',
            'media_url' => null,
            'reactions' => [],
            'related_job_id' => null,
            'metadata_json' => [
                'kudos' => [
                    'sender_user_id' => (string) $context['admin']->id,
                    'recipient_user_id' => (string) $context['employee']->id,
                    'recipient_name' => (string) $context['employee']->email,
                    'category_key' => 'innovator',
                    'category_label' => 'The Innovator - for a great idea',
                    'icon' => "\u{1F4A1}",
                ],
            ],
            'poll_question_text' => null,
            'poll_options_json' => null,
        ]);

        $response = $this->actingAs($context['employee'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('social-hub.index', [
                'company_id' => (string) $context['company']->id,
            ]));

        $response->assertOk();
        $response->assertSee(__('social_hub.ai.kudos_title'));
        $response->assertSee(__('social_hub.ai.mobility_title'));
        $response->assertSee('Platform Delivery Lead');
        $response->assertSee('Innovation Program Manager');
        $response->assertSee('Estimated fit');

        $this->assertNotNull($suggestedJobA->id);
        $this->assertNotNull($suggestedJobB->id);
    }

    public function test_candidate_social_hub_access_allows_rejected_stage_candidates_to_view_public_feed(): void
    {
        $context = $this->createSocialContext();

        $job = $this->createPublishedJob($context['company']);
        $rejectedStage = JobPipelineStage::query()->create([
            'job_id' => (string) $job->id,
            'stage_key' => 'rejected',
            'stage_label' => 'Rejected',
            'display_order' => 9,
            'is_terminal' => true,
        ]);

        Application::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'candidate_id' => (string) $context['candidate_record']->id,
            'job_id' => (string) $job->id,
            'current_stage_id' => (string) $rejectedStage->id,
            'status' => Application::STATUS_HIRED,
            'source_type' => 'career_page',
            'source_detail' => null,
            'utm_source' => null,
            'utm_campaign' => null,
            'utm_medium' => null,
        ]);

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['admin']->id,
            'type' => SocialPost::TYPE_ANNOUNCEMENT,
            'visibility' => SocialPost::VISIBILITY_PUBLIC,
            'content_text' => 'Rejected-stage candidate can still see this post',
            'media_url' => null,
            'reactions' => [],
        ]);

        $response = $this->actingAs($context['candidate'])
            ->get(route('candidate.social-hub.index', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertSee('Rejected-stage candidate can still see this post');
    }

    /**
     * @return array{
     *   company: Company,
     *   admin: User,
     *   manager: User,
     *   recruiter: User,
     *   employee: User,
     *   candidate: User,
     *   candidate_record: Candidate,
     * }
     */
    private function createSocialContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Social Hub Test Co',
            'slug' => 'social-hub-test-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $admin = $this->createMember($company, CompanyMembership::ROLE_COMPANY_ADMIN);
        $manager = $this->createMember($company, CompanyMembership::ROLE_MANAGER);
        $recruiter = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);
        $employee = $this->createMember($company, CompanyMembership::ROLE_EMPLOYEE);
        $candidate = $this->createMember($company, CompanyMembership::ROLE_CANDIDATE);

        $candidateRecord = Candidate::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'user_id' => (string) $candidate->id,
            'full_name' => 'Candidate Person',
            'email' => (string) $candidate->email,
            'phone' => null,
            'location' => null,
        ]);

        return [
            'company' => $company,
            'admin' => $admin,
            'manager' => $manager,
            'recruiter' => $recruiter,
            'employee' => $employee,
            'candidate' => $candidate,
            'candidate_record' => $candidateRecord,
        ];
    }

    /**
     * @return array{0: Job, 1: JobPipelineStage, 2: JobPipelineStage}
     */
    private function createJobWithStages(Company $company): array
    {
        $job = $this->createPublishedJob($company);

        $appliedStage = JobPipelineStage::query()->create([
            'job_id' => (string) $job->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $preselectedStage = JobPipelineStage::query()->create([
            'job_id' => (string) $job->id,
            'stage_key' => 'preselected',
            'stage_label' => 'Preselected',
            'display_order' => 2,
            'is_terminal' => false,
        ]);

        return [$job, $appliedStage, $preselectedStage];
    }

    private function createPublishedJob(Company $company): Job
    {
        return Job::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'department_id' => null,
            'title' => 'Social Hub Engineer',
            'location' => 'Remote',
            'status' => Job::STATUS_PUBLISHED,
            'blind_mode_active' => false,
            'salary_budget_max' => 90000,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function standardPayload(string $type, string $content, string $visibility = SocialPost::VISIBILITY_TEAM_ONLY): array
    {
        return [
            'mode' => 'standard',
            'type' => $type,
            'visibility' => $visibility,
            'content_text' => $content,
            'media_url' => null,
            'related_job_id' => null,
            'poll_enabled' => false,
            'poll_question_text' => null,
        ];
    }

    private function createMember(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => (string) $company->id,
            'user_id' => (string) $user->id,
            'company_role' => $role,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        return $user;
    }
}
