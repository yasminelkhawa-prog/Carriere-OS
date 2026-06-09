<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('about_pages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('hero_image_url', 2048)->nullable();
            $table->text('story_text');
            $table->text('mission');
            $table->text('vision');
            $table->text('culture_text');
            $table->jsonb('stats_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_pages');
    }
};

