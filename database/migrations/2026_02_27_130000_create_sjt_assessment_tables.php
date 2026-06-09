<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sjt_scenarios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->string('title');
            $table->string('scenario_media_url')->nullable();
            $table->text('scenario_text');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->index(['job_id', 'is_active']);
        });

        Schema::create('sjt_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignUuid('scenario_id')->constrained('sjt_scenarios')->cascadeOnDelete();
            $table->text('response_text');
            $table->boolean('copy_paste_blocked_flag')->default(true);
            $table->decimal('ai_score', 5, 2)->nullable();
            $table->jsonb('ai_feedback_json')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'scenario_id']);
            $table->index(['company_id', 'application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sjt_responses');
        Schema::dropIfExists('sjt_scenarios');
    }
};

