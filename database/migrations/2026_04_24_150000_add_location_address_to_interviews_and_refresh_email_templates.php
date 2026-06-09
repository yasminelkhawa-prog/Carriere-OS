<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('interviews', 'location_address')) {
                $table->string('location_address', 1000)->nullable()->after('meeting_link');
            }
        });

        $oldEnglishBody = implode("\n\n", [
            'Hello {{candidate_name}},',
            'Your interview for {{job_title}} is confirmed for {{scheduled_for}} via {{channel}}.',
            'Meeting link: {{meeting_link}}',
            'Regards, Recruitment Team',
        ]);

        $newEnglishBody = implode("\n\n", [
            'Hello {{candidate_name}},',
            'Your interview for {{job_title}} is confirmed for {{scheduled_for}} via {{channel}}.',
            '{{location_label}}: {{location_value}}',
            'Regards, Recruitment Team',
        ]);

        $oldFrenchBody = implode("\n\n", [
            'Bonjour {{candidate_name}},',
            'Votre entretien pour {{job_title}} est confirme le {{scheduled_for}} via {{channel}}.',
            'Lien de reunion : {{meeting_link}}',
            'Cordialement, equipe recrutement',
        ]);

        $newFrenchBody = implode("\n\n", [
            'Bonjour {{candidate_name}},',
            'Votre entretien pour {{job_title}} est confirme le {{scheduled_for}} via {{channel}}.',
            '{{location_label}} : {{location_value}}',
            'Cordialement, equipe recrutement',
        ]);

        DB::table('email_templates')
            ->where('template_key', 'interview_confirmation')
            ->where('language', 'en')
            ->where('body_template', $oldEnglishBody)
            ->update([
                'body_template' => $newEnglishBody,
                'updated_at' => now(),
            ]);

        DB::table('email_templates')
            ->where('template_key', 'interview_confirmation')
            ->where('language', 'fr')
            ->where('body_template', $oldFrenchBody)
            ->update([
                'body_template' => $newFrenchBody,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $newEnglishBody = implode("\n\n", [
            'Hello {{candidate_name}},',
            'Your interview for {{job_title}} is confirmed for {{scheduled_for}} via {{channel}}.',
            '{{location_label}}: {{location_value}}',
            'Regards, Recruitment Team',
        ]);

        $oldEnglishBody = implode("\n\n", [
            'Hello {{candidate_name}},',
            'Your interview for {{job_title}} is confirmed for {{scheduled_for}} via {{channel}}.',
            'Meeting link: {{meeting_link}}',
            'Regards, Recruitment Team',
        ]);

        $newFrenchBody = implode("\n\n", [
            'Bonjour {{candidate_name}},',
            'Votre entretien pour {{job_title}} est confirme le {{scheduled_for}} via {{channel}}.',
            '{{location_label}} : {{location_value}}',
            'Cordialement, equipe recrutement',
        ]);

        $oldFrenchBody = implode("\n\n", [
            'Bonjour {{candidate_name}},',
            'Votre entretien pour {{job_title}} est confirme le {{scheduled_for}} via {{channel}}.',
            'Lien de reunion : {{meeting_link}}',
            'Cordialement, equipe recrutement',
        ]);

        DB::table('email_templates')
            ->where('template_key', 'interview_confirmation')
            ->where('language', 'en')
            ->where('body_template', $newEnglishBody)
            ->update([
                'body_template' => $oldEnglishBody,
                'updated_at' => now(),
            ]);

        DB::table('email_templates')
            ->where('template_key', 'interview_confirmation')
            ->where('language', 'fr')
            ->where('body_template', $newFrenchBody)
            ->update([
                'body_template' => $oldFrenchBody,
                'updated_at' => now(),
            ]);

        Schema::table('interviews', function (Blueprint $table): void {
            if (Schema::hasColumn('interviews', 'location_address')) {
                $table->dropColumn('location_address');
            }
        });
    }
};
