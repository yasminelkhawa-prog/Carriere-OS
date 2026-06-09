<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('company_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon_name')->nullable();
            $table->integer('display_order');
            $table->timestamps();

            $table->index(['company_id', 'display_order']);
        });

        Schema::create('faq_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('category');
            $table->string('question');
            $table->text('answer');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
        Schema::dropIfExists('company_values');
        Schema::dropIfExists('departments');
    }
};
