<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('status')->default('pending')->after('brand_logo_url');
            $table->timestamp('approved_at')->nullable()->after('status');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN company_id DROP NOT NULL');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('platform_role')->default('user')->after('role');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_company_id_email_unique');
        } else {
            Schema::table('users', function (Blueprint $table): void {
                // MySQL requires a dedicated index for the foreign key once the composite unique key is dropped.
                $table->index('company_id', 'users_company_id_index');
                $table->dropUnique('users_company_id_email_unique');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email');
        });

        Schema::create('company_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_memberships');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->dropColumn('platform_role');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['company_id', 'email']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN company_id SET NOT NULL');
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['status', 'approved_at']);
        });
    }
};
