<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sentiment_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('source_type', 80);
            $table->uuid('source_id');
            $table->decimal('sentiment_score', 5, 4)->nullable();
            $table->jsonb('top_themes_json')->nullable();
            $table->string('risk_level', 32);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->unique(['company_id', 'source_type', 'source_id'], 'sentiment_results_company_source_unique');
        });

        Schema::create('brand_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('alert_type', 80);
            $table->string('severity', 24);
            $table->text('message');
            $table->string('related_entity_type', 80);
            $table->uuid('related_entity_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_alerts');
        Schema::dropIfExists('sentiment_results');
    }
};

