<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_posts')) {
            if (Schema::hasColumn('social_posts', 'post_type') && ! Schema::hasColumn('social_posts', 'type')) {
                Schema::table('social_posts', function (Blueprint $table): void {
                    $table->renameColumn('post_type', 'type');
                });
            }

            Schema::table('social_posts', function (Blueprint $table): void {
                if (! Schema::hasColumn('social_posts', 'visibility')) {
                    $table->string('visibility', 24)->default('team_only')->after('type');
                    $table->index(['company_id', 'visibility'], 'social_posts_company_visibility_idx');
                }

                if (! Schema::hasColumn('social_posts', 'reactions')) {
                    $table->json('reactions')->nullable()->after('media_url');
                }

                if (! Schema::hasColumn('social_posts', 'related_job_id')) {
                    $table->foreignUuid('related_job_id')->nullable()->after('reactions')
                        ->constrained('jobs')->nullOnDelete();
                    $table->index(['company_id', 'related_job_id'], 'social_posts_company_related_job_idx');
                }

                if (! Schema::hasColumn('social_posts', 'metadata_json')) {
                    $table->json('metadata_json')->nullable()->after('related_job_id');
                }

                if (! Schema::hasColumn('social_posts', 'poll_question_text')) {
                    $table->string('poll_question_text')->nullable()->after('metadata_json');
                }

                if (! Schema::hasColumn('social_posts', 'poll_options_json')) {
                    $table->json('poll_options_json')->nullable()->after('poll_question_text');
                }
            });

            if (Schema::hasColumn('social_posts', 'type')) {
                DB::table('social_posts')
                    ->whereNull('type')
                    ->update(['type' => 'announcement']);
            }

            if (Schema::hasColumn('social_posts', 'reactions')) {
                DB::table('social_posts')
                    ->whereNull('reactions')
                    ->update(['reactions' => json_encode(new stdClass(), JSON_THROW_ON_ERROR)]);
            }
        }

        if (! Schema::hasTable('social_pulse_poll_votes')) {
            Schema::create('social_pulse_poll_votes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignUuid('post_id')->constrained('social_posts')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('option_key', 60);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['company_id', 'created_at']);
                $table->unique(['post_id', 'user_id'], 'social_poll_votes_post_user_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_pulse_poll_votes');

        if (Schema::hasTable('social_posts')) {
            Schema::table('social_posts', function (Blueprint $table): void {
                if (Schema::hasColumn('social_posts', 'poll_options_json')) {
                    $table->dropColumn('poll_options_json');
                }

                if (Schema::hasColumn('social_posts', 'poll_question_text')) {
                    $table->dropColumn('poll_question_text');
                }

                if (Schema::hasColumn('social_posts', 'metadata_json')) {
                    $table->dropColumn('metadata_json');
                }

                if (Schema::hasColumn('social_posts', 'related_job_id')) {
                    $table->dropIndex('social_posts_company_related_job_idx');
                    $table->dropConstrainedForeignId('related_job_id');
                }

                if (Schema::hasColumn('social_posts', 'reactions')) {
                    $table->dropColumn('reactions');
                }

                if (Schema::hasColumn('social_posts', 'visibility')) {
                    $table->dropIndex('social_posts_company_visibility_idx');
                    $table->dropColumn('visibility');
                }
            });

            if (Schema::hasColumn('social_posts', 'type') && ! Schema::hasColumn('social_posts', 'post_type')) {
                Schema::table('social_posts', function (Blueprint $table): void {
                    $table->renameColumn('type', 'post_type');
                });
            }
        }
    }
};

