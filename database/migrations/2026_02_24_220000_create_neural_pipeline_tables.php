<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_stage_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignUuid('from_stage_id')->nullable()->constrained('job_pipeline_stages')->nullOnDelete();
            $table->foreignUuid('to_stage_id')->constrained('job_pipeline_stages')->restrictOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'created_at']);
        });

        Schema::create('application_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('title');
            $table->foreignUuid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();

            $table->index(['owner_user_id', 'status']);
        });

        DB::statement("ALTER TABLE application_tasks DROP CONSTRAINT IF EXISTS application_tasks_status_check");
        DB::statement("ALTER TABLE application_tasks ADD CONSTRAINT application_tasks_status_check CHECK (status IN ('open','done'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('application_tasks');
        Schema::dropIfExists('application_stage_histories');
    }
};
