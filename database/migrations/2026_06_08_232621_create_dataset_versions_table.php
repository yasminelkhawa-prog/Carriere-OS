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
        Schema::create('dataset_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_name'); // e.g. "Dataset v2 · remplacement import"
            $table->string('file_path')->nullable();
            $table->integer('row_count')->default(0);
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_versions');
    }
};
