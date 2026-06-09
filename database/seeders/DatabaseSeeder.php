<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyRegistrationRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->updateOrCreate(
            ['name' => 'numa Demo'],
            [
                'slug' => 'numa-demo',
                'brand_logo_url' => null,
                'status' => Company::STATUS_ACTIVE,
            ]
        );

        $superadmin = User::query()->updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'platform_role' => User::PLATFORM_SUPERADMIN,
                'active' => true,
            ]
        );

        Profile::query()->updateOrCreate(
            ['user_id' => $superadmin->id],
            [
                'full_name' => 'Platform Superadmin',
                'locale' => 'en',
                'avatar_url' => null,
            ]
        );

        $users = [
            ['email' => 'admin@example.com', 'role' => User::ROLE_COMPANY_ADMIN, 'full_name' => 'Admin User', 'locale' => 'en'],
            ['email' => 'recruiter@example.com', 'role' => User::ROLE_RECRUITER, 'full_name' => 'Recruiter User', 'locale' => 'fr'],
            ['email' => 'manager@example.com', 'role' => User::ROLE_MANAGER, 'full_name' => 'Manager User', 'locale' => 'en'],
            ['email' => 'employee@example.com', 'role' => User::ROLE_EMPLOYEE, 'full_name' => 'Employee User', 'locale' => 'fr'],
            ['email' => 'candidate@example.com', 'role' => User::ROLE_CANDIDATE, 'full_name' => 'Candidate User', 'locale' => 'en'],
        ];

        foreach ($users as $seedUser) {
            $user = User::query()->updateOrCreate(
                ['email' => $seedUser['email']],
                [
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'platform_role' => User::PLATFORM_NONE,
                    'active' => true,
                ]
            );

            Profile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $seedUser['full_name'],
                    'locale' => $seedUser['locale'],
                    'avatar_url' => null,
                ]
            );

            CompanyMembership::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                ],
                [
                    'company_role' => $seedUser['role'],
                    'membership_status' => CompanyMembership::STATUS_ACTIVE,
                ]
            );
        }

        // Pending company registration request for platform approval testing
        $pendingCompany = Company::query()->updateOrCreate(
            ['slug' => 'acme-corp'],
            [
                'name' => 'Acme Corp',
                'brand_logo_url' => null,
                'status' => Company::STATUS_PENDING,
            ]
        );

        $requester = User::query()->where('email', 'admin@example.com')->first();

        CompanyRegistrationRequest::query()->updateOrCreate(
            ['company_id' => $pendingCompany->id],
            [
                'requested_by_user_id' => $requester->id,
                'status' => CompanyRegistrationRequest::STATUS_PENDING,
                'request_payload' => [
                    'company_name' => 'Acme Corp',
                    'industry'     => 'Technology',
                    'size'         => '50-200',
                    'website'      => 'https://acme.example.com',
                ],
                'reviewed_by_user_id' => null,
                'reviewed_at'         => null,
                'rejection_reason'    => null,
                'created_at'          => now(),
            ]
        );

        if (app()->environment('local')) {
            $this->call(RealisticDataSeeder::class);
            $this->call(PublicWebsiteSeeder::class);
            $this->call(CandidatePortalModuleSeeder::class);
            $this->call(RecruitmentOverviewModuleSeeder::class);
            $this->call(EmployerBrandModuleSeeder::class);
            $this->call(ReferralModuleSeeder::class);
            $this->call(MultipostingModuleSeeder::class);
            $this->call(OfferOnboardingModuleSeeder::class);
            $this->call(SocialHubModuleSeeder::class);
            $this->call(FairnessModuleSeeder::class);
            $this->call(ReportingExportsModuleSeeder::class);
        }
    }
}

