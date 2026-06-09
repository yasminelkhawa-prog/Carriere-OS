<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_integrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('status', 32)->default('disconnected');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->jsonb('granted_scopes_json')->nullable();
            $table->string('external_account_id')->nullable();
            $table->string('external_account_name')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->text('last_error')->nullable();
            $table->jsonb('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'provider']);
        });

        DB::statement("ALTER TABLE company_integrations ADD CONSTRAINT company_integrations_status_check CHECK (status IN ('disconnected', 'pending', 'connected', 'expired', 'error'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('company_integrations');
    }
};
