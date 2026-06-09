<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_registration_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->jsonb('request_payload');
            $table->string('status')->default('pending');
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE company_registration_requests DROP CONSTRAINT IF EXISTS company_registration_requests_status_check");
        DB::statement("ALTER TABLE company_registration_requests ADD CONSTRAINT company_registration_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('company_registration_requests');
    }
};
