<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_tasks')) {
            return;
        }

        if (! Schema::hasColumn('application_tasks', 'description')) {
            Schema::table('application_tasks', function (Blueprint $table): void {
                $table->text('description')->nullable()->after('title');
            });
        }

        DB::statement('CREATE INDEX IF NOT EXISTS application_tasks_owner_user_id_index ON application_tasks (owner_user_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS application_tasks_status_index ON application_tasks (status)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('application_tasks')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS application_tasks_owner_user_id_index');
        DB::statement('DROP INDEX IF EXISTS application_tasks_status_index');

        if (Schema::hasColumn('application_tasks', 'description')) {
            Schema::table('application_tasks', function (Blueprint $table): void {
                $table->dropColumn('description');
            });
        }
    }
};
