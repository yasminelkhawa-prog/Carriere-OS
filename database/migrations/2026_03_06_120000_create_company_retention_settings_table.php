<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_retention_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('video_retention_days')->default(365);
            $table->unsignedSmallInteger('ai_artifact_retention_days')->default(180);
            $table->timestamp('last_pruned_at')->nullable();
            $table->timestamps();

            $table->unique('company_id');
            $table->index(['company_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_retention_settings');
    }
};
