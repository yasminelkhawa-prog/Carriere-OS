<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('candidate_email');
            $table->string('candidate_name')->nullable();
            $table->string('candidate_linkedin_url')->nullable();
            $table->string('resume_file_url')->nullable();
            $table->string('status', 32)->default('submitted');
            $table->timestamps();

            $table->index(['referrer_user_id', 'created_at']);
            $table->index(['company_id', 'status']);
            $table->unique(['company_id', 'referrer_user_id', 'candidate_email'], 'referrals_company_referrer_email_unique');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement('ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_status_check');
        }
        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE referrals ADD CONSTRAINT referrals_status_check CHECK (status IN ('submitted','converted','hired','rejected'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
