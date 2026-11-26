<?php

namespace App\Service;

use App\Entity\Equipement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MaintenanceAssistantService
{
    public function __construct(
        private readonly ?HttpClientInterface $httpClient = null,
    ) {}

    /**
     * Base locale des pannes informatiques courantes DSI
     */
    public function getPannesCourantes(): array
    {
        return [
            'ecran_noir' => [
                'id' => 'ecran_noir',
                'titre' => 'Écran noir / Pas de signal vidéo',
                'icone' => 'bi-display',
                'severite' => 'Haute',
                'symptomes' => ['L\'écran reste noir au démarrage', 'Le voyant de l\'écran clignote orange', 'Ventilateurs tournent mais rien ne s\'affiche'],
                'causes' => [
                    'Mauvais contact ou poussière sur les barrettes de RAM',
                    'Câble vidéo (HDMI, DisplayPort, VGA) mal branché ou défectueux',
                    'Électricité statique résiduelle bloquant la carte mère',
                    'Écran éteint ou mauvaise source d\'entrée (HDMI 1 / HDMI 2)',
                    'Alimentation ou carte graphique défectueuse',
                ],
                'actions' => [
                    '1. Débrancher la prise d\'alimentation et maintenir le bouton Power appuyé 30 secondes (décharge statique).',
                    '2. Vérifier la source vidéo sur l\'écran (bouton Input/Source : HDMI/DP).',
                    '3. Tester avec un autre câble vidéo ou un autre écran de secours.',
                    '4. Ouvrir le boîtier, retirer les barrettes de RAM, nettoyer les contacts et les réenclencher fermement (entendre le clic).',
                    '5. Si processeur avec puce vidéo intégrée, brancher l\'écran directement sur la carte mère pour isoler la carte graphique.',
                ],
                'commande' => null,
                'solution_recommandee' => "Décharge statique effectuée et réenfichage des barrettes de RAM. Affichage rétabli avec succès.",
            ],
            'disque_100_lenteur' => [
                'id' => 'disque_100_lenteur',
                'titre' => 'PC très lent / Disque saturé à 100%',
                'icone' => 'bi-speedometer2',
                'severite' => 'Moyenne',
                'symptomes' => ['Windows met plusieurs minutes à démarrer', 'Gestionnaire des tâches indique Disque à 100%', 'Applications qui se figent régulièrement'],
                'causes' => [
                    'Disque dur mécanique (HDD) usé ou vieillissant (secteurs défectueux)',
                    'Service Windows Search / SysMain (SuperFetch) qui boucle',
                    'Trop de programmes lancés automatiquement au démarrage',
                    'Fichiers temporaires ou espace disque résiduel inférieur à 10%',
                ],
                'actions' => [
                    '1. Ouvrir le Gestionnaire des tâches (Ctrl+Shift+Echap) -> onglet Démarrage -> Désactiver les logiciels superflus.',
                    '2. Désactiver temporairement SysMain dans services.msc pour tester l\'impact.',
                    '3. Lancer un nettoyage de disque (`cleanmgr /sageset:1`).',
                    '4. Vérifier l\'état de santé SMART du disque avec CrystalDiskInfo ou commande WMIC.',
                    '5. Recommandation DSI : Remplacer le disque par un SSD si HDD encore présent.',
                ],
                'commande' => "wmic diskdrive get status,model\nnet stop sysmain",
                'solution_recommandee' => "Nettoyage du système, optimisation des programmes au démarrage et désactivation des services encombrants. Disque stabilisé à < 15%.",
            ],
            'ecran_bleu_bsod' => [
                'id' => 'ecran_bleu_bsod',
                'titre' => 'Écran bleu de la mort (BSOD) / Reboot impromptu',
                'icone' => 'bi-exclamation-octagon',
                'severite' => 'Critique',
                'symptomes' => ['Windows redémarre avec un écran bleu', 'Code d\'arrêt visible (MEMORY_MANAGEMENT, CRITICAL_PROCESS_DIED, etc.)'],
                'causes' => [
                    'Pilote de périphérique instable (pilote graphique ou réseau récemment mis à jour)',
                    'Barrette mémoire RAM défaillante',
                    'Fichiers système Windows corrompus',
                    'Surchauffe du processeur (CPU) ou de la carte mère',
                ],
                'actions' => [
                    '1. Noter le code d\'arrêt exact indiqué en bas de l\'écran bleu.',
                    '2. Exécuter l\'invite de commandes en administrateur et lancer `sfc /scannow`.',
                    '3. Lancer la réparation de l\'image système Windows avec DISM.',
                    '4. Tester la mémoire vive avec l\'outil Diagnostic de mémoire Windows (`mdsched.exe`).',
                    '5. Mettre à jour ou rétrograder le pilote incriminé depuis le Gestionnaire de périphériques.',
                ],
                'commande' => "sfc /scannow\nDISM /Online /Cleanup-Image /RestoreHealth",
                'solution_recommandee' => "Réparation des fichiers système corrompus via SFC & DISM, mise à jour des pilotes de périphériques.",
            ],
            'reseau_pas_internet' => [
                'id' => 'reseau_pas_internet',
                'titre' => 'Pas d\'accès réseau / Erreur DHCP / Pas d\'Internet',
                'icone' => 'bi-wifi-off',
                'severite' => 'Haute',
                'symptomes' => ['Icône réseau avec globe terrestre barré', 'Adresse IP en 169.254.x.x (APIPA)', 'Impossible d\'accéder aux partages ou à Internet'],
                'causes' => [
                    'Câble Ethernet RJ45 débranché, mal serti ou prise murale désactivée',
                    'Échec d\'obtention du bail DHCP sur le serveur de l\'entreprise',
                    'Paramètres proxy incorrects ou carte réseau en veille',
                    'Cache DNS corrompu ou pile TCP/IP déréglée',
                ],
                'actions' => [
                    '1. Vérifier si les voyants (LEDs verte/orange) de la prise réseau RJ45 clignotent au dos du PC.',
                    '2. Renouveler le bail DHCP via l\'invite de commandes (`ipconfig /release` puis `ipconfig /renew`).',
                    '3. Vider le cache de résolution DNS (`ipconfig /flushdns`).',
                    '4. Réinitialiser la pile Winsock (`netsh winsock reset`) et redémarrer la machine.',
                    '5. Tester avec un autre port réseau ou un câble Ethernet certifié.',
                ],
                'commande' => "ipconfig /flushdns\nipconfig /release\nipconfig /renew\nnetsh winsock reset",
                'solution_recommandee' => "Réinitialisation de la pile TCP/IP & renouvellement du bail DHCP. Connexion au réseau d'entreprise rétablie.",
            ],
            'imprimante_hors_ligne' => [
                'id' => 'imprimante_hors_ligne',
                'titre' => 'Imprimante "Hors ligne" / Spooler bloqué',
                'icone' => 'bi-printer',
                'severite' => 'Moyenne',
                'symptomes' => ['Documents en attente sans s\'imprimer', 'Imprimante marquée "Hors ligne" dans Windows', 'File d\'attente impossible à vider'],
                'causes' => [
                    'Service de spouleur d\'impression Windows (Spooler) bloqué ou planté',
                    'Changement d\'adresse IP de l\'imprimante réseau',
                    'Option "Utiliser l\'imprimante hors connexion" cochée par erreur',
                    'Fichier temporaire d\'impression (.SHD / .SPL) verrouillé dans Windows',
                ],
                'actions' => [
                    '1. Décocher "Utiliser l\'imprimante hors connexion" dans la file d\'attente de l\'imprimante.',
                    '2. Stopper le spouleur d\'impression, purger le dossier `C:\\Windows\\System32\\spool\\PRINTERS` puis relancer le service.',
                    '3. Effectuer un ping vers l\'adresse IP de l\'imprimante réseau pour vérifier sa joignabilité.',
                    '4. Redémarrer l\'imprimante physique (cycle d\'alimentation 60s).',
                ],
                'commande' => "net stop spooler\ndel /Q /F /S \"%systemroot%\\System32\\Spool\\Printers\\*.*\"\nnet start spooler",
                'solution_recommandee' => "Purge forcée de la file d'attente d'impression et redémarrage du service Spooler. Impression de test validée.",
            ],
            'surchauffe_ventilateur' => [
                'id' => 'surchauffe_ventilateur',
                'titre' => 'Surchauffe / Ventilateur bruyant à 100% / Arrêt subit',
                'icone' => 'bi-thermometer-high',
                'severite' => 'Haute',
                'symptomes' => ['Le PC souffle très fort en continu même sans activité', 'La machine s\'éteint brusquement après 10-15 minutes', 'Boîtier très chaud au toucher'],
                'causes' => [
                    'Accumulation de poussière sur le radiateur (dissipateur) ou les pales du ventilateur',
                    'Pâte thermique asséchée entre le processeur (CPU) et le ventirad',
                    'Processus en arrière-plan qui consomme 100% du CPU en boucle',
                    'Ventilateur grippé ou en fin de vie',
                ],
                'actions' => [
                    '1. Dépoussiérer les grilles d\'aération et le radiateur avec une bombe d\'air comprimé.',
                    '2. Démonter le ventirad, nettoyer l\'ancienne pâte thermique avec de l\'alcool isopropylique et en appliquer une nouvelle couche fine.',
                    '3. Vérifier dans le Gestionnaire des tâches quel processus monopolise le CPU.',
                    '4. S\'assurer que l\'unité centrale n\'est pas enfermée dans un meuble clos sans circulation d\'air.',
                ],
                'commande' => null,
                'solution_recommandee' => "Dépoussiérage complet du bloc de refroidissement et remplacement de la pâte thermique CPU. Température stabilisée à 42°C.",
            ],
            'onduleur_bip' => [
                'id' => 'onduleur_bip',
                'titre' => 'Onduleur qui bipe en continu ou ne tient plus la charge',
                'icone' => 'bi-battery-charging',
                'severite' => 'Haute',
                'symptomes' => ['Bip strident et continu de l\'onduleur', 'Extinction immédiate de la machine dès coupure électrique', 'Voyant rouge / défaut batterie'],
                'causes' => [
                    'Batterie plomb-acide interne en fin de vie (durée de vie habituelle : 2 à 3 ans)',
                    'Surcharge d\'appareils branchés sur les prises secourues (ex: imprimante laser branchée sur onduleur)',
                    'Tension secteur instable (sous-tension ou surtension)',
                ],
                'actions' => [
                    '1. Ne JAMAIS brancher une imprimante laser ou un chauffage sur un onduleur DSI (pic de puissance destructeur).',
                    '2. Débrancher les équipements non critiques (écrans secondaires, chargeurs) pour réduire la charge.',
                    '3. Tester la tension d\'entrée sur la prise murale.',
                    '4. Si l\'onduleur a plus de 2-3 ans, commander et remplacer la batterie interne 12V 7Ah / 9Ah.',
                ],
                'commande' => null,
                'solution_recommandee' => "Remplacement de la batterie interne de l'onduleur (12V 7Ah) et délestage des prises non critiques. Test de bascule sur batterie OK.",
            ],
            'peripherique_usb' => [
                'id' => 'peripherique_usb',
                'titre' => 'Clavier / Souris / Périphérique USB non reconnu',
                'icone' => 'bi-usb-symbol',
                'severite' => 'Basse',
                'symptomes' => ['Le périphérique ne s\'allume pas', 'Message "Périphérique USB non reconnu"', 'Déconnexions intempestives'],
                'causes' => [
                    'Port USB physique endommagé ou encrassé',
                    'Économie d\'énergie qui coupe l\'alimentation du contrôleur USB',
                    'Pilote de concentrateur USB corrompu',
                    'Câble du périphérique défaillant',
                ],
                'actions' => [
                    '1. Brancher le périphérique sur un autre port USB (de préférence à l\'arrière directement sur la carte mère).',
                    '2. Dans le Gestionnaire de périphériques -> Concentrateurs USB -> Propriétés -> Gestion de l\'alimentation -> Décocher "Autoriser l\'ordinateur à éteindre ce périphérique".',
                    '3. Désinstaller le concentrateur USB et redémarrer la machine pour forcer sa réinstallation.',
                    '4. Tester le périphérique sur un autre ordinateur pour isoler si la panne vient du matériel.',
                ],
                'commande' => null,
                'solution_recommandee' => "Changement de port USB et mise à jour du pilote concentrateur. Périphérique opérationnel.",
            ],
            'session_profil_temp' => [
                'id' => 'session_profil_temp',
                'titre' => 'Profil temporaire Windows / Fichiers bureau disparus',
                'icone' => 'bi-person-x',
                'severite' => 'Haute',
                'symptomes' => ['Message : "Vous avez été connecté avec un profil temporaire"', 'Icônes du bureau et documents introuvables'],
                'causes' => [
                    'Profil utilisateur Windows corrompu ou verrouillé par un processus antivirus',
                    'Clé de registre .bak présente dans ProfileList',
                    'Arrêt forcé pendant la fermeture de session',
                ],
                'actions' => [
                    '1. Redémarrer 2 à 3 fois l\'ordinateur (résout souvent le verrouillage temporaire).',
                    '2. Vérifier dans `C:\\Users` que le dossier utilisateur d\'origine existe bien avec ses fichiers.',
                    '3. Ouvrir `regedit` -> `HKEY_LOCAL_MACHINE\\SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion\\ProfileList`.',
                    '4. Repérer le dossier se terminant par `.bak`, renommer la clé sans `.bak` et remettre `RefCount` et `State` à 0.',
                ],
                'commande' => null,
                'solution_recommandee' => "Restauration du profil utilisateur dans la base de registre (ProfileList). Session et documents de l'utilisateur récupérés.",
            ],
        ];
    }

    /**
     * Diagnostic principal :
     * 1. Clic sur un bouton de panne prédéfinie -> fiche locale instantanée GTAC2A
     * 2. Saisie libre -> RECHERCHE WEB EN DIRECT PRIORITAIRE (DuckDuckGo Lite, SuperUser, Wikipedia)
     * 3. Fallback -> Protocole DSI standard
     */
    public function diagnostiquer(string $question, ?Equipement $equipement = null): array
    {
        $qClean = trim($question);
        $qLower = mb_strtolower($qClean);
        $pannes = $this->getPannesCourantes();

        // 1. Clic exact sur l'une des pannes fréquentes prédéfinies
        foreach ($pannes as $id => $panne) {
            if ($qClean === $panne['titre'] || $qClean === $id || mb_strtolower($panne['titre']) === $qLower) {
                return [
                    'type' => 'local',
                    'source' => 'Base DSI GTAC2A (Panne Fréquente)',
                    'titre' => $panne['titre'],
                    'severite' => $panne['severite'],
                    'causes' => $panne['causes'],
                    'actions' => $panne['actions'],
                    'commande' => $panne['commande'],
                    'solution_recommandee' => $panne['solution_recommandee'],
                    'equipement_info' => $this->formatEquipement($equipement),
                ];
            }
        }

        // 2. Problème personnalisé : RECHERCHE WEB EN DIRECT PRIORITAIRE
        $webResult = $this->chercherEnLigne($qClean, $equipement);
        if ($webResult !== null) {
            return $webResult;
        }

        // 3. Fallback : Protocole d'investigation DSI
        return [
            'type' => 'general',
            'source' => 'Protocole DSI standard (Hors-ligne)',
            'titre' => 'Diagnostic technique : ' . $qClean,
            'severite' => 'Moyenne',
            'causes' => [
                'Dysfonctionnement matériel, périphérique déconnecté ou pilote instable',
                'Conflit logiciel ou mise à jour système incomplète',
                'Paramétrage BIOS/UEFI ou secteur d\'amorçage corrompu',
            ],
            'actions' => [
                '1. Vérifier l\'Observateur d\'événements Windows (`eventvwr.msc`) -> Journaux Windows -> Système pour identifier l\'ID d\'événement critique.',
                '2. Lancer une vérification de l\'intégrité des fichiers système Windows via invite administrateur (`sfc /scannow`).',
                '3. Vérifier le Gestionnaire de périphériques (`devmgmt.msc`) pour détecter d\'éventuels points d\'exclamation jaunes.',
                '4. Tester avec un composant ou périphérique de test certifié pour isoler la panne.',
            ],
            'commande' => "eventvwr.msc\nsfc /scannow",
            'solution_recommandee' => sprintf('Diagnostic appliqué pour "%s". Vérification des journaux système et intégrité matérielle validées.', $qClean),
            'equipement_info' => $this->formatEquipement($equipement),
        ];
    }

    /**
     * Recherche en ligne multi-sources sans clé API :
     * A. DuckDuckGo Lite (recherche web réelle en français avec titres, résumés et liens)
     * B. StackExchange SuperUser (pour les codes d'erreur 0x..., BIOS, boot, etc.)
     * C. Wikipedia API (technique)
     */
    private function chercherEnLigne(string $query, ?Equipement $equipement = null): ?array
    {
        if (!$this->httpClient) {
            return null;
        }

        // 1. DuckDuckGo Lite (Web réel en français)
        $ddgResult = $this->chercherDuckDuckGoLite($query, $equipement);
        if ($ddgResult !== null) {
            return $ddgResult;
        }

        // 2. StackExchange SuperUser (pour codes d'erreur hexadécimaux et pannes matérielles)
        $stackResult = $this->chercherStackExchange($query, $equipement);
        if ($stackResult !== null) {
            return $stackResult;
        }

        // 3. Wikipedia API
        return $this->chercherWikipedia($query, $equipement);
    }

    /**
     * Recherche Web réelle via DuckDuckGo Lite (sans clé API, en français)
     */
    private function chercherDuckDuckGoLite(string $query, ?Equipement $equipement = null): ?array
    {
        try {
            $searchQuery = $query;
            if ($equipement && $equipement->getMarque() && !str_contains(mb_strtolower($query), mb_strtolower($equipement->getMarque()))) {
                $searchQuery .= ' ' . $equipement->getMarque();
            }

            $response = $this->httpClient->request('POST', 'https://lite.duckduckgo.com/lite/', [
                'body' => ['q' => $searchQuery],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Referer' => 'https://lite.duckduckgo.com/',
                ],
                'timeout' => 5.0,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $html = $response->getContent();

            // Extraction des liens et des snippets
            preg_match_all("/<a[^>]*href=['\"]([^'\"]+)['\"][^>]*class=['\"]result-link['\"][^>]*>(.*?)<\/a>/is", $html, $links);
            preg_match_all("/<td[^>]*class=['\"]result-snippet['\"][^>]*>(.*?)<\/td>/is", $html, $snippets);

            $count = min(count($links[1] ?? []), count($snippets[1] ?? []));
            if ($count === 0) {
                return null;
            }

            $premierTitre = html_entity_decode(strip_tags($links[2][0] ?? $query));
            $premierSnippet = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($snippets[1][0] ?? ''))));

            $causes = [
                'Diagnostic identifié sur les bases de support technique en ligne :',
                $premierSnippet,
            ];

            $actions = [];
            for ($i = 0; $i < min(3, $count); $i++) {
                $t = html_entity_decode(strip_tags($links[2][$i] ?? ''));
                $url = $links[1][$i] ?? '';
                $snip = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($snippets[1][$i] ?? ''))));

                $actions[] = sprintf('%d. %s : %s', $i + 1, $t, substr($snip, 0, 140) . '...');
                if ($url !== '') {
                    $actions[] = sprintf('   🔗 Source web : %s', $url);
                }
            }

            // Commande utile contextuelle
            $commande = null;
            $qLower = mb_strtolower($query);
            if (str_contains($qLower, 'bios') || str_contains($qLower, 'mot de passe')) {
                $commande = "shutdown /r /fw /t 0"; // Accéder directement au BIOS UEFI au redémarrage
            } elseif (str_contains($qLower, 'boot') || str_contains($qLower, 'demarrage') || str_contains($qLower, '0xc00')) {
                $commande = "bootrec /fixmbr\nbootrec /fixboot\nbootrec /rebuildbcd";
            } elseif (str_contains($qLower, 'reseau') || str_contains($qLower, 'ip') || str_contains($qLower, 'dns') || str_contains($qLower, 'wifi')) {
                $commande = "ipconfig /flushdns\nnetsh int ip reset\nnetsh winsock reset";
            } else {
                $commande = "sfc /scannow\ndism /online /cleanup-image /restorehealth";
            }

            return [
                'type' => 'web',
                'source' => '🌐 Recherche Web en direct',
                'titre' => $premierTitre,
                'severite' => 'Moyenne',
                'causes' => $causes,
                'actions' => $actions,
                'commande' => $commande,
                'solution_recommandee' => sprintf('Résolution appliquée d\'après le support technique ("%s") : %s', substr($premierTitre, 0, 80), substr($premierSnippet, 0, 120)),
                'equipement_info' => $this->formatEquipement($equipement),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Recherche sur StackExchange / SuperUser
     */
    private function chercherStackExchange(string $query, ?Equipement $equipement = null): ?array
    {
        try {
            // Nettoyage de la requête pour SuperUser (anglais / code d'erreur)
            $cleanQ = $query;
            if (preg_match('/(0x[0-9a-fA-F]+)/i', $query, $m)) {
                $cleanQ = $m[1];
            } else {
                $cleanQ = str_ireplace(['erreur', 'panne', 'probleme', 'problème', 'demarrage', 'démarrage'], ['error', 'issue', 'problem', 'problem', 'boot', 'boot'], $query);
            }

            $url = 'https://api.stackexchange.com/2.3/search/advanced?' . http_build_query([
                'site' => 'superuser',
                'q' => $cleanQ,
                'pagesize' => 3,
                'order' => 'desc',
                'sort' => 'relevance',
            ]);

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 4.0,
                'headers' => ['User-Agent' => 'G-PARC-DSI-Assistant/1.0'],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $raw = $response->getContent(false);
            $decompressed = @gzdecode($raw) ?: $raw;
            $data = json_decode($decompressed, true);

            $items = $data['items'] ?? [];
            if (empty($items)) {
                return null;
            }

            $premierTitre = html_entity_decode((string) $items[0]['title']);
            $causes = [
                sprintf('Problème répertorié sur SuperUser : "%s"', $premierTitre),
                'Cas de support technique confirmé avec solution documentée par les administrateurs systèmes.',
            ];

            $actions = [];
            foreach ($items as $idx => $it) {
                $t = html_entity_decode((string) ($it['title'] ?? ''));
                $link = (string) ($it['link'] ?? '');
                $statut = !empty($it['is_answered']) ? ' [Résolu par la communauté]' : '';
                $actions[] = sprintf('%d. %s%s', $idx + 1, $t, $statut);
                if ($link !== '') {
                    $actions[] = sprintf('   🔗 Lien : %s', $link);
                }
            }

            return [
                'type' => 'web',
                'source' => '🌐 Recherche Web en direct (SuperUser IT)',
                'titre' => 'Solution Web : ' . $premierTitre,
                'severite' => 'Moyenne',
                'causes' => $causes,
                'actions' => $actions,
                'commande' => "sfc /scannow\ndism /online /cleanup-image /restorehealth",
                'solution_recommandee' => sprintf('Résolution appliquée selon le support technique ("%s").', substr($premierTitre, 0, 90)),
                'equipement_info' => $this->formatEquipement($equipement),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Recherche encyclopédique sur Wikipedia
     */
    private function chercherWikipedia(string $query, ?Equipement $equipement = null): ?array
    {
        try {
            $url = 'https://fr.wikipedia.org/w/api.php?' . http_build_query([
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $query,
                'format' => 'json',
                'utf8' => 1,
                'srlimit' => 2,
            ]);

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 3.5,
                'headers' => ['User-Agent' => 'G-PARC-DSI-Assistant/1.0'],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray(false);
            $results = $data['query']['search'] ?? [];
            if (empty($results)) {
                return null;
            }

            $premier = $results[0];
            $titre = $premier['title'];
            $snippet = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $premier['snippet']))));

            if (strlen($snippet) < 25) {
                return null;
            }

            return [
                'type' => 'web',
                'source' => '🌐 Recherche Web (Documentation Technique)',
                'titre' => 'Documentation en ligne : ' . $titre,
                'severite' => 'Moyenne',
                'causes' => [
                    sprintf('Article technique trouvé : "%s"', $titre),
                    $snippet,
                ],
                'actions' => [
                    '1. Suivre les spécifications et recommandations du constructeur.',
                    '2. Appliquer les correctifs ou mises à jour mentionnés dans la documentation.',
                ],
                'commande' => "sfc /scannow",
                'solution_recommandee' => sprintf('Résolution d\'après la documentation technique : %s', substr($snippet, 0, 140)),
                'equipement_info' => $this->formatEquipement($equipement),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatEquipement(?Equipement $eq): ?string
    {
        if (!$eq) {
            return null;
        }
        return sprintf('%s (%s %s)', $eq->getNom(), $eq->getMarque(), $eq->getModele());
    }
}
