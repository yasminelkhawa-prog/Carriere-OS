<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (! Schema::hasColumn('companies', 'slug')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->string('slug')->nullable()->after('name');
            });
        }

        DB::table('companies')->select(['id', 'name'])->orderBy('created_at')->get()->each(function (object $company): void {
            $baseSlug = Str::slug((string) $company->name);
            $slug = $baseSlug === '' ? 'company-'.$company->id : $baseSlug;
            $counter = 1;

            while (
                DB::table('companies')
                    ->where('slug', $slug)
                    ->where('id', '!=', $company->id)
                    ->exists()
            ) {
                $counter++;
                $slug = $baseSlug.'-'.$counter;
            }

            DB::table('companies')->where('id', $company->id)->update(['slug' => $slug]);
        });

        if (Schema::hasColumn('companies', 'approved_at')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropColumn('approved_at');
            });
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('status')->default('pending')->change();
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_status_check");
            DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_status_check CHECK (status IN ('pending', 'active', 'rejected', 'suspended'))");
        }

        if (! Schema::hasColumn('users', 'platform_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('platform_role')->default('none')->after('password');
            });
        } else {
            DB::table('users')->where('platform_role', 'user')->update(['platform_role' => 'none']);
            Schema::table('users', function (Blueprint $table): void {
                $table->string('platform_role')->default('none')->change();
            });
        }

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('role');
            });
        }

        if (Schema::hasColumn('users', 'company_id')) {
            Schema::table('users', function (Blueprint $table): void {
                if (DB::connection()->getDriverName() !== 'sqlite') {
                    DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_company_id_foreign');
                } else {
                    $table->dropForeign(['company_id']);
                    $table->dropIndex(['company_id']);
                }
                $table->dropColumn('company_id');
            });
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_platform_role_check");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_platform_role_check CHECK (platform_role IN ('superadmin', 'none'))");
    
            if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE profiles DROP CONSTRAINT IF EXISTS profiles_locale_check");
            }
            if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE profiles ADD CONSTRAINT profiles_locale_check CHECK (locale IN ('en', 'fr'))");
            }
        }

        if (! Schema::hasColumn('company_memberships', 'company_role')) {
            Schema::table('company_memberships', function (Blueprint $table): void {
                $table->string('company_role')->nullable()->after('user_id');
            });
        }

        if (! Schema::hasColumn('company_memberships', 'membership_status')) {
            Schema::table('company_memberships', function (Blueprint $table): void {
                $table->string('membership_status')->nullable()->after('company_role');
            });
        }

        if (Schema::hasColumn('company_memberships', 'role')) {
            DB::statement("
                UPDATE company_memberships
                SET company_role = CASE
                    WHEN role = 'admin' THEN 'company_admin'
                    ELSE role
                END
                WHERE company_role IS NULL
            ");
        }

        if (Schema::hasColumn('company_memberships', 'active')) {
            DB::statement("
                UPDATE company_memberships
                SET membership_status = CASE
                    WHEN active THEN 'active'
                    ELSE 'revoked'
                END
                WHERE membership_status IS NULL
            ");
        }

        DB::statement("UPDATE company_memberships SET company_role = 'candidate' WHERE company_role IS NULL");
        DB::statement("UPDATE company_memberships SET membership_status = 'active' WHERE membership_status IS NULL");

        if (Schema::hasColumn('company_memberships', 'role')) {
            Schema::table('company_memberships', function (Blueprint $table): void {
                $table->dropColumn('role');
            });
        }

        if (Schema::hasColumn('company_memberships', 'active')) {
            Schema::table('company_memberships', function (Blueprint $table): void {
                if (DB::connection()->getDriverName() === 'sqlite') {
                    $table->dropIndex('company_memberships_user_id_active_index');
                }
                $table->dropColumn('active');
            });
        }

        Schema::table('company_memberships', function (Blueprint $table): void {
            $table->string('company_role')->nullable(false)->change();
            $table->string('membership_status')->nullable(false)->default('pending')->change();
            $table->index('user_id');
            $table->index('company_id');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE company_memberships DROP CONSTRAINT IF EXISTS company_memberships_company_role_check");
            DB::statement("ALTER TABLE company_memberships ADD CONSTRAINT company_memberships_company_role_check CHECK (company_role IN ('company_admin', 'recruiter', 'manager', 'employee', 'candidate'))");
            DB::statement("ALTER TABLE company_memberships DROP CONSTRAINT IF EXISTS company_memberships_membership_status_check");
            if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE company_memberships ADD CONSTRAINT company_memberships_membership_status_check CHECK (membership_status IN ('pending', 'active', 'revoked'))");
            }
        }

        Schema::dropIfExists('audit_logs');

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type');
            $table->string('entity_type');
            $table->uuid('entity_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        //
    }
};
