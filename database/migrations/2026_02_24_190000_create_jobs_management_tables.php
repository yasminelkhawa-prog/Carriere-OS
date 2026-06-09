<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsJobsRecreate = false;

        if (Schema::hasTable('jobs')) {
            $idColumnType = DB::table('information_schema.columns')
                ->where('table_name', 'jobs')
                ->where('column_name', 'id')
                ->value('data_type');

            $needsJobsRecreate = $idColumnType !== 'uuid';
        }

        if ($needsJobsRecreate) {
            Schema::dropIfExists('job_pipeline_stages');
            Schema::dropIfExists('job_weighting_configs');
            Schema::dropIfExists('job_personas');
            Schema::dropIfExists('job_description_blocks');
            Schema::dropIfExists('jobs');
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('title');
                $table->string('location')->nullable();
                $table->string('status')->default('draft');
                $table->boolean('blind_mode_active')->default(false);
                $table->integer('salary_budget_max')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
            });

            DB::statement("ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_status_check");
            DB::statement("ALTER TABLE jobs ADD CONSTRAINT jobs_status_check CHECK (status IN ('draft', 'published', 'archived'))");
        }

        if (! Schema::hasTable('job_description_blocks')) {
            Schema::create('job_description_blocks', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
                $table->string('block_type');
                $table->jsonb('block_content_json');
                $table->integer('display_order');
                $table->timestamps();

                $table->index(['job_id', 'display_order']);
            });
        }

        if (! Schema::hasTable('job_personas')) {
            Schema::create('job_personas', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
                $table->jsonb('persona_json');
                $table->timestamps();

                $table->unique('job_id');
            });
        }

        if (! Schema::hasTable('job_weighting_configs')) {
            Schema::create('job_weighting_configs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
                $table->jsonb('weighting_json');
                $table->timestamps();

                $table->unique('job_id');
            });
        }

        if (! Schema::hasTable('job_pipeline_stages')) {
            Schema::create('job_pipeline_stages', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
                $table->string('stage_key');
                $table->string('stage_label');
                $table->integer('display_order');
                $table->boolean('is_terminal')->default(false);
                $table->timestamps();

                $table->unique(['job_id', 'stage_key']);
                $table->index(['job_id', 'display_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_pipeline_stages');
        Schema::dropIfExists('job_weighting_configs');
        Schema::dropIfExists('job_personas');
        Schema::dropIfExists('job_description_blocks');
        Schema::dropIfExists('jobs');
    }
};
