<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->unsignedTinyInteger('years_experience')->nullable()->after('location');
            $table->string('last_company', 255)->nullable()->after('years_experience');
            $table->text('main_skills')->nullable()->after('last_company');
            $table->string('diploma_type', 100)->nullable()->after('main_skills');
            $table->string('school_type', 20)->nullable()->after('diploma_type'); // 'moroccan' or 'foreign'
            $table->string('school_name', 255)->nullable()->after('school_type');
            $table->string('school_country', 100)->nullable()->after('school_name');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn([
                'years_experience',
                'last_company',
                'main_skills',
                'diploma_type',
                'school_type',
                'school_name',
                'school_country',
            ]);
        });
    }
};
