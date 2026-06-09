<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reverse_feedback', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->foreignUuid('recruiter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating_clarity');
            $table->unsignedTinyInteger('rating_speed');
            $table->unsignedTinyInteger('rating_kindness');
            $table->text('comment')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('candidate_surveys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->unsignedTinyInteger('overall_experience_rating');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_surveys');
        Schema::dropIfExists('reverse_feedback');
    }
};

