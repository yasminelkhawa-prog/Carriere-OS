<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_does_not_fail_when_mail_server_is_unavailable(): void
    {
        $company = $this->createCompany('admin-users-company-a');
        $admin = $this->createCompanyAdmin($company);

        Password::shouldReceive('broker->sendResetLink')
            ->once()
            ->andThrow(new \RuntimeException('smtp unavailable'));

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'email' => 'new.recruiter@example.com',
                'full_name' => 'New Recruiter',
                'role' => User::ROLE_RECRUITER,
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', __('admin.users.user_added'));
        $response->assertSessionHas('warning', __('admin.users.password_setup_mail_unavailable'));

        $user = User::query()->where('email', 'new.recruiter@example.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('profiles', [
            'user_id' => (string) $user?->id,
            'full_name' => 'New Recruiter',
        ]);

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => (string) $company->id,
            'user_id' => (string) $user?->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);
    }

    public function test_admin_can_edit_existing_user_name_without_role_change_confirmation(): void
    {
        $company = $this->createCompany('admin-users-company-b');
        $admin = $this->createCompanyAdmin($company);

        $target = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $target->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update-role', $target), [
                'full_name' => 'Updated Recruiter Name',
                'role' => CompanyMembership::ROLE_RECRUITER,
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', __('admin.users.user_updated'));

        $this->assertDatabaseHas('profiles', [
            'user_id' => (string) $target->id,
            'full_name' => 'Updated Recruiter Name',
        ]);

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => (string) $company->id,
            'user_id' => (string) $target->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
        ]);
    }

    public function test_admin_can_remove_user_from_company_with_delete_action(): void
    {
        $company = $this->createCompany('admin-users-company-c');
        $admin = $this->createCompanyAdmin($company);

        $target = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $target->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $target));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', __('admin.users.user_removed'));

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => (string) $company->id,
            'user_id' => (string) $target->id,
            'membership_status' => CompanyMembership::STATUS_REVOKED,
        ]);
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
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'company_role' => CompanyMembership::ROLE_COMPANY_ADMIN,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        return $user;
    }
}
