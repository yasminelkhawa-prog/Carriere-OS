<?php

return [
    'portal' => [
        'title' => 'Entretien vidéo asynchrone',
        'subtitle' => 'Complétez votre évaluation Stories question par question.',
        'labels' => [
            'progress' => ':current / :total complétés',
            'processing' => 'Rapport final en traitement',
            'processing_failed' => 'Traitement échoué',
            'completed' => 'Terminé',
            'not_started' => 'Non commencé',
        ],
        'actions' => [
            'open' => 'Ouvrir Stories',
            'continue' => 'Continuer Stories',
            'retry_failed' => 'Ouvrir et relancer plus tard',
        ],
    ],
    'config' => [
        'title' => 'Configurations entretien vidéo asynchrone',
        'subtitle' => 'Configurez les timers Stories, les retries et les questions ordonnées par poste.',
        'fields' => [
            'job' => 'Poste',
            'name' => 'Nom de configuration',
            'read_time_seconds' => 'Temps de lecture (secondes)',
            'answer_time_seconds' => 'Temps de réponse (secondes)',
            'retries_allowed' => 'Retries autorisés',
            'questions' => 'Questions',
            'question_placeholder' => 'Question :number',
        ],
        'actions' => [
            'create' => 'Créer configuration',
            'update' => 'Mettre à jour configuration',
        ],
        'messages' => [
            'created' => 'Configuration vidéo créée.',
            'updated' => 'Configuration vidéo mise à jour.',
        ],
        'validation' => [
            'questions_required' => 'Au moins une question est requise.',
            'question_text_required' => 'Le texte de la question est requis.',
        ],
        'empty_title' => 'Aucune configuration vidéo',
        'empty_message' => 'Créez une configuration pour activer les stories vidéo asynchrones.',
    ],
    'stories' => [
        'title' => 'Entretien vidéo asynchrone',
        'subtitle' => 'Répondez à chaque question dans le temps imparti.',
        'progress' => ':current / :total complétés',
        'labels' => [
            'read_timer' => 'Timer de lecture',
            'answer_timer' => 'Timer de réponse',
            'retries_left' => 'Retries restants',
            'attempt' => 'Tentative :attempt',
            'duration_seconds' => 'Durée enregistrée (secondes)',
            'pauses_count' => 'Nombre de pauses (optionnel)',
            'speech_rate' => 'Estimation débit parole (optionnel)',
            'filler_ratio' => 'Estimation ratio mots de remplissage (optionnel)',
            'transcript' => 'Texte de transcription (optionnel)',
            'recording_file' => 'Fichier vidéo',
            'guide_blocked' => 'Le bot guide candidat est désactivé sur les pages d\'évaluation vidéo.',
            'read_complete' => 'Temps de lecture terminé. Vous pouvez soumettre.',
            'processing' => 'Le rapport final est en traitement.',
            'processing_failed' => 'Traitement échoué. Le recruteur peut relancer depuis l\'espace candidat.',
        ],
        'actions' => [
            'submit_and_next' => 'Soumettre et suivant',
            'submit_and_retry' => 'Soumettre et refaire',
            'open_candidate_portal' => 'Retour au tableau de bord candidat',
            'start_reading' => 'Démarrer le timer lecture',
        ],
        'messages' => [
            'no_config_title' => 'Aucun entretien vidéo configuré',
            'no_config_message' => 'Le recruteur n\'a pas encore configuré cette évaluation pour ce poste.',
            'completed_title' => 'Entretien vidéo terminé',
            'completed_message' => 'Toutes les questions ont été soumises. Le rapport IA final est en préparation.',
            'saved' => 'Réponse vidéo enregistrée.',
            'retry_limit' => 'Nombre maximal de retries atteint pour cette question.',
            'read_timer_required' => 'Vous devez terminer le timer de lecture avant la soumission.',
            'answer_timer_exceeded' => 'La durée enregistrée dépasse le temps de réponse autorisé.',
        ],
    ],
];
