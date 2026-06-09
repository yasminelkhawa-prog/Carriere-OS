<?php

$dir = __DIR__ . '/lang/fr';

$replacements = [
    '/\bacces\b/' => 'accès',
    '/\bAcces\b/' => 'Accès',
    '/\ba\b/' => 'à',  // be careful, but generally 'a' in french as a preposition is 'à'. If it's the verb avoir, it's 'a'.
    // Let's remove standalone 'a' to avoid "Il a mangé" becoming "Il à mangé", which is worse.
    '/\b(L|l) evaluer\b/' => '$1\'évaluer',
    '/\b(L|l) evaluation\b/' => '$1\'évaluation',
    '/\bevaluation\b/' => 'évaluation',
    '/\bEvaluation\b/' => 'Évaluation',
    '/\bevaluations\b/' => 'évaluations',
    '/\bEvaluations\b/' => 'Évaluations',
    '/\bcreer\b/' => 'créer',
    '/\bCreer\b/' => 'Créer',
    '/\bdeconnecter\b/' => 'déconnecter',
    '/\bDeconnecter\b/' => 'Déconnecter',
    '/\betape\b/' => 'étape',
    '/\bEtape\b/' => 'Étape',
    '/\betapes\b/' => 'étapes',
    '/\bEtapes\b/' => 'Étapes',
    '/\btaches\b/' => 'tâches',
    '/\bTaches\b/' => 'Tâches',
    '/\btache\b/' => 'tâche',
    '/\bTache\b/' => 'Tâche',
    '/\bcloturee\b/' => 'clôturée',
    '/\bcloturees\b/' => 'clôturées',
    '/\bparametres\b/' => 'paramètres',
    '/\bParametres\b/' => 'Paramètres',
    '/\bactualites\b/' => 'actualités',
    '/\bActualites\b/' => 'Actualités',
    '/\bsecurise\b/' => 'sécurisé',
    '/\bsecurisee\b/' => 'sécurisée',
    '/\bequipes\b/' => 'équipes',
    '/\bEquipes\b/' => 'Équipes',
    '/\bequipe\b/' => 'équipe',
    '/\bEquipe\b/' => 'Équipe',
    '/\boublie\b/' => 'oublié',
    '/\brecu\b/' => 'reçu',
    '/\bRecu\b/' => 'Reçu',
    '/\bresume\b/' => 'résumé',
    '/\bResume\b/' => 'Résumé',
    '/\bbrievement\b/' => 'brièvement',
    '/\bclarte\b/' => 'clarté',
    '/\bpreselectionne\b/' => 'présélectionné',
    '/\bpretes\b/' => 'prêtes',
    '/\bdonnees\b/' => 'données',
    '/\bteleverse\b/' => 'téléversé',
    '/\bteleverser\b/' => 'téléverser',
    '/\bsysteme\b/' => 'système',
    '/\bSysteme\b/' => 'Système',
    '/\bverifier\b/' => 'vérifier',
    '/\bVerifier\b/' => 'Vérifier',
    '/\bverifiee\b/' => 'vérifiée',
    '/\breinitialiser\b/' => 'réinitialiser',
    '/\bReinitialiser\b/' => 'Réinitialiser',
    '/\breinitialisation\b/' => 'réinitialisation',
    '/\boperations\b/' => 'opérations',
    '/\bOperations\b/' => 'Opérations',
    '/\b(L|l) acces\b/' => '$1\'accès',
    '/\ba jour\b/' => 'à jour',
    '/\bA jour\b/' => 'À jour',
    '/\bdeja\b/' => 'déjà',
    '/\bDeja\b/' => 'Déjà',
    '/\bgrace a\b/' => 'grâce à',
    '/\bGrace a\b/' => 'Grâce à',
    '/\bayant trait a\b/' => 'ayant trait à',
    '/\bpreparez\b/' => 'préparez',
    '/\bPreparez\b/' => 'Préparez',
    '/\bdecrivez\b/' => 'décrivez',
    '/\bDecrivez\b/' => 'Décrivez',
    '/\breponse\b/' => 'réponse',
    '/\bReponse\b/' => 'Réponse',
    '/\breponses\b/' => 'réponses',
    '/\bReponses\b/' => 'Réponses',
    '/\bnumero\b/' => 'numéro',
    '/\bNumero\b/' => 'Numéro',
    '/\bevenement\b/' => 'événement',
    '/\bevenements\b/' => 'événements',
    '/\b(C|c)reation\b/' => '$1réation',
    '/\b(G|g)enerer\b/' => '$1énérer',
    '/\b(G|g)enere\b/' => '$1énéré',
    '/\b(V|v)erification\b/' => '$1érification',
    '/\b(R|r)egles\b/' => '$1ègles',
    '/\b(P|p)recedent\b/' => '$1récédent',
    '/\b(D|d)etail\b/' => '$1étail',
    '/\b(D|d)etails\b/' => '$1étails',
    '/\b(R|r)ecrutes\b/' => '$1ecrutés',
    '/\b(R|r)ecrute\b/' => '$1ecruté',
    '/\b(T|t)elechargement\b/' => '$1éléchargement',
    '/\b(A|a)meliorer\b/' => '$1méliorer',
    '/\b(E|e)pingle\b/' => '$1pinglé',
    '/\b(E|e)crire\b/' => '$1crire',
    '/\b(D|d)emarrage\b/' => '$1émarrage',
    '/\b(C|c)ote\b/' => '$1ôté',
    '/\b(A|a)cces\b/' => '$1ccès',
    '/\b(E|e)xpediteur\b/' => '$1xpéditeur',
    '/\b(I|i)ntegration\b/' => '$1ntégration',
    '/\b(G|g)enerale\b/' => '$1énérale',
    '/\b(P|p)ersonnalise\b/' => '$1ersonnalisé',
    '/\b(A|a)nnule\b/' => '$1nnulé',
    '/\b(A|a)nnulee\b/' => '$1nnulée',
    '/\b(R|r)eferences\b/' => '$1éférences',
    '/\b(D|d)eveloppeur\b/' => '$1éveloppeur',
    '/\b(M|m)etre a jour\b/' => '$1ettre à jour',
    '/\b(V|v)ous etes\b/' => '$1ous êtes',
    '/\b(A|a)bientot\b/' => '$1 bientôt',
    '/\b(C|c)oordonnees\b/' => '$1oordonnées',
    '/\ba\b/' => 'à', // Only isolated ' a '. We handle regex boundaries carefully. Actually, a lot of ' a ' are 'à' in UI text ("Mettre a jour", "Boite a outils"). Let's stick to safe words.
    '/\b(B|b)oite\b/' => '$1oîte',
    '/\b(M|m)eme\b/' => '$1ême',
    '/\b(C|c)out\b/' => '$1oût',
    '/\b(D|d)elai\b/' => '$1élai',
    '/\b(D|d)elais\b/' => '$1élais',
    '/\b(C|c)andidatures\b/' => '$1andidatures',
    '/\b(C|c)loner\b/' => '$1loner',
];

$files = glob($dir . '/*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Some very common UI phrases that use 'a' instead of 'à'
    $content = str_replace('Mettre a jour', 'Mettre à jour', $content);
    $content = str_replace('mettre a jour', 'mettre à jour', $content);
    $content = str_replace('Boite a outils', 'Boîte à outils', $content);
    $content = str_replace('Candidature a ', 'Candidature à ', $content);
    $content = str_replace(' l etape', ' l\'étape', $content);
    $content = str_replace(' d etape', ' d\'étape', $content);
    $content = str_replace(' l acces', ' l\'accès', $content);
    $content = str_replace(' l adresse', ' l\'adresse', $content);
    $content = str_replace(' L adresse', ' L\'adresse', $content);
    $content = str_replace(' a la', ' à la', $content);
    $content = str_replace(' a l ', ' à l\'', $content);
    $content = str_replace(' a des', ' à des', $content);
    $content = str_replace(' a un', ' à un', $content);
    $content = str_replace(' a une', ' à une', $content);
    $content = str_replace(' d un', ' d\'un', $content);
    $content = str_replace(' d une', ' d\'une', $content);
    $content = str_replace(' l evaluation', ' l\'évaluation', $content);
    $content = str_replace(' l email', ' l\'email', $content);
    $content = str_replace(' s ouvre', ' s\'ouvre', $content);
    $content = str_replace(' n est', ' n\'est', $content);
    $content = str_replace(' c est', ' c\'est', $content);
    $content = str_replace(' s ils', ' s\'ils', $content);
    $content = str_replace(' qu ils', ' qu\'ils', $content);
    $content = str_replace(' jusqu a', ' jusqu\'à', $content);
    $content = str_replace(' l offre', ' l\'offre', $content);
    $content = str_replace(' d offre', ' d\'offre', $content);
    $content = str_replace(' d autres', ' d\'autres', $content);
    $content = str_replace(' l utilisateur', ' l\'utilisateur', $content);

    // Apply regex rules
    foreach ($replacements as $pattern => $replacement) {
        // Exclude the isolated 'a' rule to avoid breaking "Il y a"
        if ($pattern === '/\ba\b/') continue; 
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    file_put_contents($file, $content);
}

echo "Accents fixed successfully.\n";
