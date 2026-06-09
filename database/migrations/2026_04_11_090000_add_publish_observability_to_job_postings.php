<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->timestamp('last_publish_attempted_at')->nullable()->after('posted_at');
            $table->timestamp('last_publish_succeeded_at')->nullable()->after('last_publish_attempted_at');
            $table->string('last_publish_status')->nullable()->after('last_publish_succeeded_at');
            $table->string('last_execution_mode')->nullable()->after('last_publish_status');
            $table->text('last_publish_error')->nullable()->after('last_execution_mode');
        });

        Schema::create('job_posting_publish_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignUuid('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('platform');
            $table->unsignedInteger('attempt_number');
            $table->string('status');
            $table->string('execution_mode')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_payload_json')->nullable();
            $table->text('external_url')->nullable();
            $table->json('diagnostics_json')->nullable();
            $table->timestamps();

            $table->index(['job_posting_id', 'created_at']);
            $table->unique(['job_posting_id', 'attempt_number'], 'jp_publish_attempts_job_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posting_publish_attempts');

        Schema::table('job_postings', function (Blueprint $table): void {
            $table->dropColumn([
                'last_publish_attempted_at',
                'last_publish_succeeded_at',
                'last_publish_status',
                'last_execution_mode',
                'last_publish_error',
            ]);
        });
    }
};
