<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_scorings', function (Blueprint $table): void {
            $table->jsonb('component_scores_json')->nullable()->after('vrin_json');
            $table->jsonb('source_status_json')->nullable()->after('component_scores_json');
            $table->jsonb('strengths_json')->nullable()->after('source_status_json');
            $table->jsonb('weaknesses_json')->nullable()->after('strengths_json');
            $table->text('overall_recommendation')->nullable()->after('xai_summary');
            $table->unsignedInteger('ranking_position')->nullable()->after('overall_recommendation');
            $table->decimal('ranking_percentile', 6, 2)->nullable()->after('ranking_position');
            $table->boolean('is_top_three')->default(false)->after('ranking_percentile');
            $table->string('analysis_status', 32)->default('pending_analysis')->after('is_top_three');
        });
    }

    public function down(): void
    {
        Schema::table('application_scorings', function (Blueprint $table): void {
            $table->dropColumn([
                'component_scores_json',
                'source_status_json',
                'strengths_json',
                'weaknesses_json',
                'overall_recommendation',
                'ranking_position',
                'ranking_percentile',
                'is_top_three',
                'analysis_status',
            ]);
        });
    }
};

