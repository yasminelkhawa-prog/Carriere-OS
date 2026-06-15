<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignUuid('cv_id')->nullable()->constrained('cvs')->nullOnDelete();
            $table->integer('score')->nullable();
            $table->jsonb('ai_result_json')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['cv_id']);
            $table->dropColumn(['cv_id', 'score', 'ai_result_json']);
        });
    }
};
