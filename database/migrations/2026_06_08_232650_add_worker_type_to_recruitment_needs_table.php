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
        Schema::table('recruitment_needs', function (Blueprint $table) {
            $table->string('worker_type')->nullable()->after('contract_type')->comment('BC for Blue Collar, WC for White Collar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recruitment_needs', function (Blueprint $table) {
            $table->dropColumn('worker_type');
        });
    }
};
