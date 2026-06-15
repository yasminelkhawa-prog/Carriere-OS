<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psy_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('candidate_first_name');
            $table->string('candidate_last_name');
            $table->string('candidate_email');
            $table->string('profile', 50); // ingenieur, management, finance
            $table->string('status', 20)->default('pending'); // pending, completed, expired
            $table->dateTime('expires_at');
            $table->dateTime('completed_at')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('answers_json')->nullable();
            $table->json('dimension_scores_json')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('candidate_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psy_tests');
    }
};
