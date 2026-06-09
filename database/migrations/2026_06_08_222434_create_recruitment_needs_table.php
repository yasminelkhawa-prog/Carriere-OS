<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recruitment_needs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('year');
            $table->string('site')->nullable();
            $table->string('departing_position_title')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('departure_reason')->nullable();
            $table->string('new_recruit_position_title');
            $table->boolean('budget_approved')->default(false);
            $table->string('status')->default('pas encore lancé');
            $table->string('contract_type')->nullable();
            $table->string('recruitment_type')->nullable(); // e.g., BC
            $table->boolean('internal_posting')->default(false);
            $table->boolean('external_sourcing')->default(true);
            $table->string('sourcing_tools')->nullable();
            $table->string('new_recruit_name')->nullable();
            $table->string('gender')->nullable(); // M/F
            $table->date('expected_start_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recruitment_needs');
    }
};
