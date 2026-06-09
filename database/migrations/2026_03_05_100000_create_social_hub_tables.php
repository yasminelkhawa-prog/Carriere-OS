<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('post_type', 40);
            $table->text('content_text');
            $table->string('media_url')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('social_reactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->string('reaction_type', 40);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->unique(['post_id', 'user_id', 'reaction_type'], 'social_reactions_post_user_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_reactions');
        Schema::dropIfExists('social_posts');
    }
};
