<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('export_type', 80);
            $table->foreignUuid('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->jsonb('filters_json');
            $table->string('format', 16);
            $table->string('status', 24);
            $table->string('file_url')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE exports DROP CONSTRAINT IF EXISTS exports_format_check");
        }
        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE exports ADD CONSTRAINT exports_format_check CHECK (format IN ('csv','pdf'))");
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE exports DROP CONSTRAINT IF EXISTS exports_status_check");
        }
        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE exports ADD CONSTRAINT exports_status_check CHECK (status IN ('queued','processing','completed','failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
