<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('interview_type');
            // Use datetime for broad MySQL compatibility on older servers.
            $table->dateTime('scheduled_start_at');
            $table->dateTime('scheduled_end_at');
            $table->string('timezone', 64);
            $table->string('location_type', 32);
            $table->string('meeting_link')->nullable();
            $table->string('status')->default('draft');
            $table->foreignUuid('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['application_id', 'scheduled_start_at']);
        });

        DB::statement("ALTER TABLE interviews DROP CONSTRAINT IF EXISTS interviews_status_check");
        DB::statement("ALTER TABLE interviews ADD CONSTRAINT interviews_status_check CHECK (status IN ('draft','scheduled','completed','cancelled'))");

        Schema::create('interview_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('participant_role');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['interview_id', 'user_id']);
        });

        Schema::create('interview_feedback', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->foreignUuid('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->jsonb('ratings_json')->nullable();
            $table->string('recommendation');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['interview_id', 'created_at']);
        });

        Schema::create('user_secure_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('setting_key');
            $table->text('setting_value');
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_secure_settings');
        Schema::dropIfExists('interview_feedback');
        Schema::dropIfExists('interview_participants');
        Schema::dropIfExists('interviews');
    }
};
