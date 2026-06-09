<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ContactInquiry;
use App\Models\Job;
use App\Models\JobDescriptionBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_public_home_jobs_and_job_detail_without_login(): void
    {
        $company = Company::query()->create([
            'name' => 'Public Site Co',
            'slug' => 'public-site-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $publishedJob = Job::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'title' => 'Public Senior Engineer',
            'status' => Job::STATUS_PUBLISHED,
            'location' => 'Remote',
        ]);

        JobDescriptionBlock::withoutGlobalScopes()->create([
            'job_id' => (string) $publishedJob->id,
            'block_type' => 'overview',
            'block_content_json' => ['text' => 'Public job detail overview block.'],
            'display_order' => 1,
        ]);

        $draftJob = Job::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'title' => 'Draft Hidden Job',
            'status' => Job::STATUS_DRAFT,
        ]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee(__('public_site.nav.entry_company'))
            ->assertSee(__('public_site.nav.entry_candidate'))
            ->assertSee('data-testid="public-header-login"', false)
            ->assertSee('Public Senior Engineer')
            ->assertDontSee('Draft Hidden Job');

        $this->get(route('public.jobs.index'))
            ->assertOk()
            ->assertSee('Public Senior Engineer')
            ->assertDontSee('Draft Hidden Job');

        $this->get(route('public.jobs.show', ['job' => $publishedJob->id]))
            ->assertOk()
            ->assertSee('Public Senior Engineer')
            ->assertSee(__('public_site.jobs.apply_cta'))
            ->assertSee(route('career.show', ['company' => $company->slug, 'job' => $publishedJob->id]), false);

        $this->get(route('public.jobs.show', ['job' => $draftJob->id]))
            ->assertNotFound();
    }

    public function test_public_entry_split_routes_company_and_candidate_to_expected_flows(): void
    {
        $this->get(route('public.entry.company'))
            ->assertRedirect(route('login', ['audience' => 'company']));

        $this->get(route('public.entry.candidate'))
            ->assertRedirect(route('public.jobs.index', ['audience' => 'candidate']));
    }

    public function test_login_page_shows_public_browse_pathways(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(__('auth.login_public_notice'));
        $response->assertSee(__('auth.new_here_title'));
        $response->assertSee(__('auth.create_company_account_action'));
        $response->assertSee(__('auth.apply_as_candidate_action'));
        $response->assertSee(__('auth.candidate_access_hint'));
        $response->assertSee(__('auth.browse_jobs_action'));
        $response->assertSee(__('auth.about_us_action'));
        $response->assertSee(__('auth.contact_us_action'));
    }

    public function test_about_page_renders_static_professional_content(): void
    {
        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee('Building careers with clarity, speed, and confidence.')
            ->assertSee('What makes numa different')
            ->assertSee('Ready to discover your next opportunity?');
    }

    public function test_register_page_uses_public_layout_navigation_and_footer(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee(__('public_site.nav.home'))
            ->assertSee(__('public_site.nav.jobs'))
            ->assertSee(__('public_site.nav.contact'))
            ->assertSee(__('public_site.footer.quick_links'));
    }

    public function test_contact_form_submission_is_stored_and_superadmin_can_manage_it(): void
    {
        $submission = [
            'full_name' => 'Jamie Candidate',
            'email' => 'jamie.candidate@example.test',
            'phone' => '+1-555-0100',
            'subject' => 'Question about opportunities',
            'message' => 'I want to understand which roles are best aligned with backend engineering experience.',
            'website' => '',
        ];

        $this->post(route('public.contact.store'), $submission)
            ->assertRedirect(route('public.contact'))
            ->assertSessionHas('status', __('public_site.contact.flash.submitted'));

        $this->assertDatabaseHas('contact_inquiries', [
            'email' => 'jamie.candidate@example.test',
            'status' => ContactInquiry::STATUS_NEW,
            'source' => ContactInquiry::SOURCE_PUBLIC_CONTACT_FORM,
        ]);

        $inquiry = ContactInquiry::query()->where('email', 'jamie.candidate@example.test')->first();
        $this->assertInstanceOf(ContactInquiry::class, $inquiry);

        $superadmin = User::factory()->create([
            'email' => 'platform.superadmin@example.test',
            'platform_role' => User::PLATFORM_SUPERADMIN,
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $regularUser = User::factory()->create([
            'platform_role' => User::PLATFORM_NONE,
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($superadmin)
            ->get(route('superadmin.contact-inquiries.index'))
            ->assertOk()
            ->assertSee('Question about opportunities');

        $this->actingAs($superadmin)
            ->patch(route('superadmin.contact-inquiries.update', ['contactInquiry' => $inquiry->id]), [
                'status' => ContactInquiry::STATUS_RESOLVED,
                'assigned_to_user_id' => (string) $superadmin->id,
                'notes' => 'Resolved after sharing job links.',
            ])
            ->assertRedirect(route('superadmin.contact-inquiries.show', ['contactInquiry' => $inquiry->id]));

        $this->assertDatabaseHas('contact_inquiries', [
            'id' => (string) $inquiry->id,
            'status' => ContactInquiry::STATUS_RESOLVED,
            'assigned_to_user_id' => (string) $superadmin->id,
            'notes' => 'Resolved after sharing job links.',
        ]);

        $this->actingAs($regularUser)
            ->get(route('superadmin.contact-inquiries.index'))
            ->assertForbidden();
    }

    public function test_public_contact_endpoint_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('public.contact.store'), [
                'full_name' => 'Rate Limit User '.$attempt,
                'email' => "ratelimit{$attempt}@example.test",
                'subject' => 'Rate limit test',
                'message' => 'This is a valid message body for contact form rate limit testing.',
                'website' => '',
            ])->assertRedirect(route('public.contact'));
        }

        $this->post(route('public.contact.store'), [
            'full_name' => 'Rate Limit User 7',
            'email' => 'ratelimit7@example.test',
            'subject' => 'Rate limit test',
            'message' => 'This is a valid message body for contact form rate limit testing.',
            'website' => '',
        ])->assertStatus(429);
    }

    public function test_public_pages_render_with_french_locale_strings(): void
    {
        $response = $this->withSession(['locale' => 'fr'])->get(route('public.jobs.index'));

        $response->assertOk();
        $response->assertSee(trans('public_site.nav.jobs', [], 'fr'));
        $response->assertSee(trans('public_site.jobs.title', [], 'fr'));
    }
}

