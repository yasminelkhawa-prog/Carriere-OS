<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyIntegration;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyIntegrationLinkedInTest extends TestCase
{
    use RefreshDatabase;

    public function test_connected_integration_can_be_verified_using_stored_token(): void
    {
        $company = $this->createCompany('linkedin-integration-company-a');
        $admin = $this->createCompanyAdmin($company);

        $integration = CompanyIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            'status' => CompanyIntegration::STATUS_CONNECTED,
            'access_token' => 'linkedin-access-token',
            'external_account_name' => 'Old Account Name',
            'last_connected_at' => now()->subDay(),
            'granted_scopes_json' => ['openid', 'profile'],
        ]);

        Http::fake([
            'https://api.linkedin.com/v2/userinfo' => Http::response([
                'sub' => 'linkedin-company-123',
                'name' => 'Acme Hiring',
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('admin.integrations.linkedin.test', ['company_id' => $company->id]));

        $response->assertRedirect(route('configuration.index', ['company_id' => (string) $company->id]));
        $response->assertSessionHas('status', __('ui.integrations.linkedin.test_success', [
            'account' => 'Acme Hiring',
        ]));

        Http::assertSentCount(1);

        $integration->refresh();

        $this->assertSame(CompanyIntegration::STATUS_CONNECTED, $integration->status);
        $this->assertSame('linkedin-company-123', $integration->external_account_id);
        $this->assertSame('Acme Hiring', $integration->external_account_name);
        $this->assertNotNull($integration->last_used_at);
        $this->assertNull($integration->last_error);
        $this->assertSame('Acme Hiring', data_get($integration->meta_json, 'profile.name'));
        $this->assertNotNull(data_get($integration->meta_json, 'last_verified_at'));
    }

    public function test_test_connection_marks_integration_expired_when_token_is_expired(): void
    {
        $company = $this->createCompany('linkedin-integration-company-b');
        $admin = $this->createCompanyAdmin($company);

        $integration = CompanyIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            'status' => CompanyIntegration::STATUS_CONNECTED,
            'access_token' => 'expired-token',
            'token_expires_at' => now()->subMinute(),
            'last_connected_at' => now()->subDay(),
        ]);

        Http::fake();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('admin.integrations.linkedin.test', ['company_id' => $company->id]));

        $response->assertRedirect(route('configuration.index', ['company_id' => (string) $company->id]));
        $response->assertSessionHas('error', __('ui.integrations.linkedin.test_failed', [
            'message' => 'LinkedIn access token has expired. Please reconnect the account.',
        ]));

        Http::assertNothingSent();

        $integration->refresh();
        $this->assertSame(CompanyIntegration::STATUS_EXPIRED, $integration->status);
        $this->assertSame('LinkedIn access token has expired. Please reconnect the account.', $integration->last_error);
        $this->assertNull($integration->last_used_at);
    }

    public function test_test_connection_requires_existing_integration(): void
    {
        $company = $this->createCompany('linkedin-integration-company-c');
        $admin = $this->createCompanyAdmin($company);

        Http::fake();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('admin.integrations.linkedin.test', ['company_id' => $company->id]));

        $response->assertRedirect(route('configuration.index', ['company_id' => (string) $company->id]));
        $response->assertSessionHas('error', __('ui.integrations.linkedin.not_connected'));

        Http::assertNothingSent();
    }

    private function createCompany(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Company '.strtoupper($slug),
            'slug' => $slug,
            'status' => Company::STATUS_ACTIVE,
        ]);
    }

    private function createCompanyAdmin(Company $company): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'company_role' => CompanyMembership::ROLE_COMPANY_ADMIN,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        return $user;
    }
}
