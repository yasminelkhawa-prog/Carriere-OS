<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('offer_status');
            $table->decimal('salary_amount', 12, 2)->nullable();
            $table->string('currency', 8);
            $table->date('start_date')->nullable();
            $table->timestamps();

            $table->unique('application_id');
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->text('contract_file_url');
            $table->string('contract_status');
            $table->timestamp('signed_at')->nullable();
            $table->foreignUuid('signer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signature_method');
            $table->json('audit_metadata_json');
            $table->timestamps();

            $table->unique('application_id');
        });

        Schema::create('onboarding_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('doc_type');
            $table->text('file_url');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('onboarding_schedule', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('location')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('onboarding_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('task_name');
            $table->timestamp('due_at')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboarding_schedule');
        Schema::dropIfExists('onboarding_documents');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('offers');
    }
};
