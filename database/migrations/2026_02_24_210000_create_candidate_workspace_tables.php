<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignUuid('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'created_at']);
        });

        Schema::create('application_activity_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('event_type');
            $table->jsonb('payload')->nullable();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'created_at']);
        });

        Schema::create('application_scorings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->decimal('global_match_score', 5, 2)->nullable();
            $table->jsonb('vrin_json')->nullable();
            $table->text('xai_summary')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('unified_interview_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->jsonb('ai_full_payload')->nullable();
            $table->integer('ocean_openness')->nullable();
            $table->integer('ocean_conscientiousness')->nullable();
            $table->integer('ocean_extraversion')->nullable();
            $table->integer('ocean_agreeableness')->nullable();
            $table->integer('ocean_neuroticism')->nullable();
            $table->boolean('is_generic_motivation')->default(false);
            $table->decimal('match_percentage', 5, 2)->nullable();
            $table->integer('salary_expected_min')->nullable();
            $table->integer('salary_expected_max')->nullable();
            $table->string('salary_currency', 8)->nullable();
            $table->decimal('salary_fit_score', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unified_interview_reports');
        Schema::dropIfExists('application_scorings');
        Schema::dropIfExists('application_activity_events');
        Schema::dropIfExists('candidate_notes');
    }
};
