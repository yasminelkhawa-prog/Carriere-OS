<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_lab_ai_summaries', function (Blueprint $table): void {
            $table->string('overall_recommendation')->nullable()->after('creativity_score');
        });

        Schema::table('strategy_lab_briefs', function (Blueprint $table): void {
            $table->string('final_decision_status')->nullable()->after('status');
            $table->text('final_decision_note')->nullable()->after('final_decision_status');
            $table->foreignUuid('final_decision_by_user_id')->nullable()->after('final_decision_note')->constrained('users')->nullOnDelete();
            $table->timestamp('final_decision_at')->nullable()->after('final_decision_by_user_id');
            $table->index(['company_id', 'final_decision_status']);
        });
    }

    public function down(): void
    {
        Schema::table('strategy_lab_briefs', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'final_decision_status']);
            $table->dropConstrainedForeignId('final_decision_by_user_id');
            $table->dropColumn([
                'final_decision_status',
                'final_decision_note',
                'final_decision_at',
            ]);
        });

        Schema::table('strategy_lab_ai_summaries', function (Blueprint $table): void {
            $table->dropColumn('overall_recommendation');
        });
    }
};

