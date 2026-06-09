<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_inquiries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 60)->nullable();
            $table->string('subject');
            $table->text('message');
            $table->string('status')->default('new');
            $table->foreignUuid('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('source')->default('public_contact_form');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['email', 'created_at']);
            $table->index(['source', 'created_at']);
        });

        DB::statement("ALTER TABLE contact_inquiries DROP CONSTRAINT IF EXISTS contact_inquiries_status_check");
        DB::statement("ALTER TABLE contact_inquiries ADD CONSTRAINT contact_inquiries_status_check CHECK (status IN ('new', 'in_progress', 'resolved', 'closed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');
    }
};

