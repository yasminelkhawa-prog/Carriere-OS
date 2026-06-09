<?php

return [
    'portal' => [
        'title' => 'Entretien video asynchrone',
        'subtitle' => 'Completez votre évaluation Stories question par question.',
        'labels' => [
            'progress' => ':current / :total completes',
            'processing' => 'Rapport final en traitement',
            'processing_failed' => 'Traitement echoue',
            'completed' => 'Termine',
            'not_started' => 'Non commence',
        ],
        'actions' => [
            'open' => 'Ouvrir Stories',
            'continue' => 'Continuer Stories',
            'retry_failed' => 'Ouvrir et relancer plus tard',
        ],
    ],
    'config' => [
        'title' => 'Configurations entretien video asynchrone',
        'subtitle' => 'Configurez les timers Stories, les retries et les questions ordonnees par poste.',
        'fields' => [
            'job' => 'Poste',
            'name' => 'Nom de configuration',
            'read_time_seconds' => 'Temps de lecture (secondes)',
            'answer_time_seconds' => 'Temps de réponse (secondes)',
            'retries_allowed' => 'Retries autorises',
            'questions' => 'Questions',
            'question_placeholder' => 'Question :number',
        ],
        'actions' => [
            'create' => 'Créer configuration',
            'update' => 'Mettre à jour configuration',
        ],
        'messages' => [
            'created' => 'Configuration video creee.',
            'updated' => 'Configuration video mise à jour.',
        ],
        'validation' => [
            'questions_required' => 'Au moins une question est requise.',
            'question_text_required' => 'Le texte de la question est requis.',
        ],
        'empty_title' => 'Aucune configuration video',
        'empty_message' => 'Creez une configuration pour activer les stories video asynchrones.',
    ],
    'stories' => [
        'title' => 'Entretien video asynchrone',
        'subtitle' => 'Repondez a chaque question dans le temps imparti.',
        'progress' => ':current / :total completes',
        'labels' => [
            'read_timer' => 'Timer de lecture',
            'answer_timer' => 'Timer de réponse',
            'retries_left' => 'Retries restants',
            'attempt' => 'Tentative :attempt',
            'duration_seconds' => 'Duree enregistree (secondes)',
            'pauses_count' => 'Nombre de pauses (optionnel)',
            'speech_rate' => 'Estimation debit parole (optionnel)',
            'filler_ratio' => 'Estimation ratio mots de remplissage (optionnel)',
            'transcript' => 'Texte de transcription (optionnel)',
            'recording_file' => 'Fichier video',
            'guide_blocked' => 'Le bot guide candidat est desactive sur les pages d évaluation video.',
            'read_complete' => 'Temps de lecture termine. Vous pouvez soumettre.',
            'processing' => 'Le rapport final est en traitement.',
            'processing_failed' => 'Traitement echoue. Le recruteur peut relancer depuis l espace candidat.',
        ],
        'actions' => [
            'submit_and_next' => 'Soumettre et suivant',
            'submit_and_retry' => 'Soumettre et refaire',
            'open_candidate_portal' => 'Retour au tableau de bord candidat',
            'start_reading' => 'Demarrer le timer lecture',
        ],
        'messages' => [
            'no_config_title' => 'Aucun entretien video configure',
            'no_config_message' => 'Le recruteur n a pas encore configure cette évaluation pour ce poste.',
            'completed_title' => 'Entretien video termine',
            'completed_message' => 'Toutes les questions ont ete soumises. Le rapport IA final est en preparation.',
            'saved' => 'Réponse video enregistree.',
            'retry_limit' => 'Nombre maximal de retries atteint pour cette question.',
            'read_timer_required' => 'Vous devez terminer le timer de lecture avant la soumission.',
            'answer_timer_exceeded' => 'La duree enregistree depasse le temps de réponse autorise.',
        ],
    ],
];
