<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('platform');
            $table->string('status')->default('disabled');
            $table->longText('ai_generated_content')->nullable();
            $table->text('tracking_url')->nullable();
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'platform']);
        });

        Schema::create('click_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->timestamp('clicked_at')->useCurrent();
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->index(['job_posting_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_events');
        Schema::dropIfExists('job_postings');
    }
};
