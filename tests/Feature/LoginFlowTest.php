<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admin_can_login(): void
    {
        $this->seed();

        $response = $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('auth.company.dispatch'));
        $this->assertAuthenticated();
    }

    public function test_session_remains_authenticated_across_subsequent_requests(): void
    {
        $this->seed();

        $this->withServerVariables(['HTTP_HOST' => '127.0.0.1'])
            ->post(route('login.store', absolute: false), [
                'email' => 'admin@example.com',
                'password' => 'password',
            ])
            ->assertRedirect(route('auth.company.dispatch', absolute: false));

        $this->withServerVariables(['HTTP_HOST' => '127.0.0.1'])
            ->get(route('auth.company.dispatch', absolute: false))
            ->assertRedirect(route('home', absolute: false));

        $this->withServerVariables(['HTTP_HOST' => '127.0.0.1'])
            ->get(route('home', absolute: false))
            ->assertOk();

        $this->assertAuthenticated();
    }

    public function test_candidate_login_redirects_to_candidate_portal(): void
    {
        $company = Company::query()->create([
            'name' => 'Portal Login Company',
            'slug' => 'portal-login-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $candidateUser = User::factory()->create([
            'email' => 'candidate-login@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => (string) $company->id,
            'user_id' => (string) $candidateUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        Candidate::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'user_id' => (string) $candidateUser->id,
            'full_name' => 'Candidate Login',
            'email' => (string) $candidateUser->email,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'candidate-login@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('auth.company.dispatch'));

        $follow = $this->actingAs($candidateUser)
            ->get(route('auth.company.dispatch'));

        $follow->assertRedirect(route('candidate.portal', ['company' => $company->slug]));
    }
}
