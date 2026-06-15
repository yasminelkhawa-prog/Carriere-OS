<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('template_key', 120);
            $table->string('language', 2);
            $table->text('subject_template');
            $table->text('body_template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'template_key', 'language'], 'email_templates_company_template_language_unique');
            $table->index(['company_id', 'template_key']);
        });

        Schema::create('email_outbox_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->text('subject');
            $table->text('body');
            $table->string('status', 20);
            $table->string('template_key', 120)->nullable();
            $table->string('related_entity_type', 120)->nullable();
            $table->uuid('related_entity_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'template_key']);
            $table->index(['company_id', 'related_entity_type', 'related_entity_id'], 'email_outbox_logs_related_entity_index');
        });

        Schema::create('rejection_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->text('draft_subject');
            $table->text('draft_body');
            $table->text('xai_reason_text');
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE email_outbox_logs DROP CONSTRAINT IF EXISTS email_outbox_logs_status_check");
        }
        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE email_outbox_logs ADD CONSTRAINT email_outbox_logs_status_check CHECK (status IN ('queued', 'sent', 'failed'))");
        }
        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE rejection_drafts DROP CONSTRAINT IF EXISTS rejection_drafts_status_check");
        }
        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE rejection_drafts ADD CONSTRAINT rejection_drafts_status_check CHECK (status IN ('draft', 'approved', 'sent'))");
        }

        $companies = DB::table('companies')->pluck('id');
        $now = now();
        $seedRows = [];
        $defaults = [
            'application_acknowledgement' => [
                'en' => [
                    'subject' => 'Application received for {{job_title}}',
                    'body' => implode("\n\n", [
                        'Hello {{candidate_name}},',
                        'We received your application for "{{job_title}}" at {{company_name}}.',
                        'Reference: {{application_reference}}',
                        'Our recruiting team will review your profile and contact you if your experience matches the role requirements.',
                        'Thank you for your interest in joining us.',
                    ]),
                ],
                'fr' => [
                    'subject' => 'Candidature recue pour {{job_title}}',
                    'body' => implode("\n\n", [
                        'Bonjour {{candidate_name}},',
                        'Nous avons bien recue votre candidature pour "{{job_title}}" chez {{company_name}}.',
                        'Reference : {{application_reference}}',
                        'Notre equipe recrutement analysera votre profil et vous contactera si votre experience correspond au poste.',
                        'Merci pour votre interet.',
                    ]),
                ],
            ],
            'interview_confirmation' => [
                'en' => [
                    'subject' => 'Interview Confirmation - {{job_title}}',
                    'body' => implode("\n\n", [
                        'Hello {{candidate_name}},',
                        'Your interview for {{job_title}} is confirmed for {{scheduled_for}} via {{channel}}.',
                        'Meeting link: {{meeting_link}}',
                        'Regards, Recruitment Team',
                    ]),
                ],
                'fr' => [
                    'subject' => 'Confirmation entretien - {{job_title}}',
                    'body' => implode("\n\n", [
                        'Bonjour {{candidate_name}},',
                        'Votre entretien pour {{job_title}} est confirme le {{scheduled_for}} via {{channel}}.',
                        'Lien de reunion : {{meeting_link}}',
                        'Cordialement, equipe recrutement',
                    ]),
                ],
            ],
            'onboarding_welcome_after_signing' => [
                'en' => [
                    'subject' => 'Welcome to {{company_name}}',
                    'body' => implode("\n\n", [
                        'Hello {{candidate_name}},',
                        'Welcome to {{company_name}}. Your onboarding journey for {{job_title}} is now active.',
                        'We are excited to have you with us.',
                    ]),
                ],
                'fr' => [
                    'subject' => 'Bienvenue chez {{company_name}}',
                    'body' => implode("\n\n", [
                        'Bonjour {{candidate_name}},',
                        'Bienvenue chez {{company_name}}. Votre integration pour {{job_title}} est maintenant active.',
                        'Nous sommes ravis de vous accueillir.',
                    ]),
                ],
            ],
            'rejection_decision' => [
                'en' => [
                    'subject' => 'Application Update - {{job_title}}',
                    'body' => implode("\n\n", [
                        'Hello {{candidate_name}},',
                        '{{draft_body}}',
                        'Reason context: {{xai_reason}}',
                        'Regards, Recruitment Team',
                    ]),
                ],
                'fr' => [
                    'subject' => 'Mise a jour candidature - {{job_title}}',
                    'body' => implode("\n\n", [
                        'Bonjour {{candidate_name}},',
                        '{{draft_body}}',
                        'Contexte : {{xai_reason}}',
                        'Cordialement, equipe recrutement',
                    ]),
                ],
            ],
        ];

        foreach ($companies as $companyId) {
            foreach ($defaults as $templateKey => $languages) {
                foreach ($languages as $language => $template) {
                    $seedRows[] = [
                        'id' => (string) Str::uuid(),
                        'company_id' => (string) $companyId,
                        'template_key' => $templateKey,
                        'language' => $language,
                        'subject_template' => $template['subject'],
                        'body_template' => $template['body'],
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($seedRows, 400) as $chunk) {
            DB::table('email_templates')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rejection_drafts');
        Schema::dropIfExists('email_outbox_logs');
        Schema::dropIfExists('email_templates');
    }
};

