<?php

return [
    'outbox_queue' => (string) env('COMMUNICATION_OUTBOX_QUEUE', 'default'),
    'outbox_dispatch' => (string) env('COMMUNICATION_OUTBOX_DISPATCH', 'queue'),

    'template_defaults' => [
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
        'application_portal_verification' => [
            'en' => [
                'subject' => 'Verify your email to access your application portal - {{job_title}}',
                'body' => implode("\n\n", [
                    'Hello {{candidate_name}},',
                    'We received your application for "{{job_title}}" at {{company_name}}.',
                    'Reference: {{application_reference}}',
                    'Verify your email to access your candidate portal using this secure link:',
                    '{{verification_url}}',
                    'After verification, you will be signed in automatically and redirected to your portal.',
                ]),
            ],
            'fr' => [
                'subject' => 'Verifiez votre email pour acceder a votre portail candidat - {{job_title}}',
                'body' => implode("\n\n", [
                    'Bonjour {{candidate_name}},',
                    'Nous avons bien recue votre candidature pour "{{job_title}}" chez {{company_name}}.',
                    'Reference : {{application_reference}}',
                    'Verifiez votre email pour acceder a votre portail candidat via ce lien securise :',
                    '{{verification_url}}',
                    'Apres verification, vous serez connecte automatiquement et redirige vers votre portail.',
                ]),
            ],
        ],
        'interview_confirmation' => [
            'en' => [
                'subject' => 'Interview Confirmation - {{job_title}}',
                'body' => implode("\n\n", [
                    'Hello {{candidate_name}},',
                    'Your interview for {{job_title}} is confirmed for {{scheduled_for}} via {{channel}}.',
                    '{{location_label}}: {{location_value}}',
                    'Regards, Recruitment Team',
                ]),
            ],
            'fr' => [
                'subject' => 'Confirmation entretien - {{job_title}}',
                'body' => implode("\n\n", [
                    'Bonjour {{candidate_name}},',
                    'Votre entretien pour {{job_title}} est confirme le {{scheduled_for}} via {{channel}}.',
                    '{{location_label}} : {{location_value}}',
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
    ],
];
