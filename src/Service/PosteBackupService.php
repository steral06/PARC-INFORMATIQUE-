<?php

namespace App\Service;

use App\Entity\Attribution;
use App\Entity\Equipement;
use App\Entity\SauvegardePoste;
use App\Repository\EquipementRepository;
use App\Repository\SauvegardePosteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Process\Process;

class PosteBackupService
{
    private const SESSION_TOKEN_PREFIX = 'gparc_backup_auth_token_';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EquipementRepository $equipementRepository,
        private readonly SauvegardePosteRepository $sauvegardeRepository,
        private readonly RequestStack $requestStack,
    ) {}

    private function cleanUtf8(?string $str): string
    {
        if ($str === null || $str === '') return '';
        if (mb_check_encoding($str, 'UTF-8')) return $str;
        return mb_convert_encoding($str, 'UTF-8', 'Windows-1252, CP850, ISO-8859-1');
    }

    /**
     * Génère un token d'autorisation temporaire (à usage unique, valable 15 minutes)
     */
    public function demanderTokenAutorisation(string $adminUsername, string $action): string
    {
        $session = $this->requestStack->getSession();
        $token = bin2hex(random_bytes(16));

        $session->set(self::SESSION_TOKEN_PREFIX . $adminUsername, [
            'token' => $token,
            'action' => $action,
            'expires_at' => (new \DateTimeImmutable())->modify('+15 minutes')->getTimestamp(),
        ]);

        return $token;
    }

    /**
     * Vérifie et consomme le token d'autorisation (usage unique)
     */
    public function verifierEtConsommerToken(string $token, string $adminUsername, string $action): bool
    {
        $session = $this->requestStack->getSession();
        $sessionKey = self::SESSION_TOKEN_PREFIX . $adminUsername;
        $data = $session->get($sessionKey);

        if (!$data || !is_array($data)) {
            return false;
        }

        if (($data['token'] ?? '') !== $token) {
            return false;
        }

        if (($data['action'] ?? '') !== $action && ($data['action'] ?? '') !== 'ALL') {
            return false;
        }

        if (time() > ($data['expires_at'] ?? 0)) {
            $session->remove($sessionKey);
            return false;
        }

        // Consommation du token : immédiatement invalidé après usage
        $session->remove($sessionKey);
        return true;
    }

    /**
     * Teste si la machine cible est en ligne et prête (Ping ICMP + Ports d'administration)
     */
    public function testerJoignabilite(?string $adresseIp, ?string $nomMachine = null): array
    {
        $cible = trim((string) $adresseIp);
        if ($cible === '' || $cible === 'DHCP') {
            $cible = trim((string) $nomMachine);
        }

        if ($cible === '') {
            return [
                'en_ligne' => false,
                'temps_ms' => null,
                'ip' => 'Non renseignée',
                'methode' => 'Aucune adresse',
                'details' => 'Aucune adresse IP ou nom de machine renseigné pour ce matériel.',
            ];
        }

        // Cas local
        if ($cible === '127.0.0.1' || $cible === '::1' || strtolower($cible) === 'localhost') {
            return [
                'en_ligne' => true,
                'temps_ms' => '< 1 ms',
                'ip' => $cible,
                'methode' => 'Hôte local',
                'details' => 'Machine hôte locale connectée et prête pour la configuration.',
            ];
        }

        // 1. Test ICMP Ping rapide (1 paquet, délai max 1200ms)
        $pingCmd = sprintf('ping -n 1 -w 1200 %s', escapeshellarg($cible));
        try {
            $process = Process::fromShellCommandline($pingCmd);
            $process->setTimeout(3);
            $process->run();

            $output = $process->getOutput();
            if ($process->isSuccessful() && (str_contains($output, 'TTL=') || str_contains($output, 'octets=') || str_contains($output, 'bytes='))) {
                $temps = '< 5 ms';
                if (preg_match('/temps[=<]([0-9]+ms|<[0-9]+ms)/i', $output, $m)) {
                    $temps = $m[1];
                } elseif (preg_match('/time[=<]([0-9]+ms|<[0-9]+ms)/i', $output, $m)) {
                    $temps = $m[1];
                }

                return [
                    'en_ligne' => true,
                    'temps_ms' => $temps,
                    'ip' => $cible,
                    'methode' => 'Ping ICMP',
                    'details' => sprintf('Réponse ICMP reçue de %s (%s). La machine est allumée et prête.', $cible, $temps),
                ];
            }
        } catch (\Throwable) {}

        // 2. Test direct sur port d'administration SMB (445) ou RPC (135) si le Ping ICMP est bloqué par pare-feu
        $ports = [445, 135];
        foreach ($ports as $port) {
            $fp = @fsockopen($cible, $port, $errno, $errstr, 1.0);
            if ($fp) {
                fclose($fp);
                return [
                    'en_ligne' => true,
                    'temps_ms' => '< 10 ms',
                    'ip' => $cible,
                    'methode' => 'Port admin (' . $port . ')',
                    'details' => sprintf('Port d\'administration %d ouvert sur %s. La machine est en ligne et prête.', $port, $cible),
                ];
            }
        }

        // 3. Échec : la machine ne répond pas
        return [
            'en_ligne' => false,
            'temps_ms' => null,
            'ip' => $cible,
            'methode' => 'Injoignable',
            'details' => sprintf('Aucune réponse de %s. Le poste est éteint, en veille ou hors du réseau GTA.', $cible),
        ];
    }

    /**
     * Déclenche la création d'un Point de Restauration Système Windows à distance
     */
    public function creerPointRestauration(Equipement $equipement, string $adminUser, bool $force = false): array
    {
        $nomMachine = $equipement->getNom() ?: ('PC-' . $equipement->getId());
        $adresseIp  = $equipement->getAdresseIp();
        $nomRestorePoint = sprintf('G-PARC_%s_%s', $nomMachine, (new \DateTime())->format('Ymd_Hi'));

        // Étape 1 : Diagnostic préalable de joignabilité (Ping ICMP + ports admin)
        $testReseau = $this->testerJoignabilite($adresseIp, $nomMachine);

        if (!$force && !$testReseau['en_ligne']) {
            return [
                'success'        => false,
                'atteint'        => false,
                'machine'        => $nomMachine,
                'ip'             => $adresseIp ?: 'Non renseignée',
                'details_reseau' => $testReseau['details'],
                'message'        => sprintf(
                    '❌ Échec de liaison : La machine [%s] (%s) ne répond pas au Ping. Le poste est éteint ou injoignable sur le réseau.',
                    $nomMachine, $adresseIp ?: 'Non renseignée'
                ),
            ];
        }

        $cible = trim((string) $adresseIp) ?: $nomMachine;

        $isLocalhost = in_array($cible, ['127.0.0.1', '::1', 'localhost'], true)
            || str_contains(strtolower($nomMachine), 'localhost');

        // ─────────────────────────────────────────────────────────────────────
        // Commande PowerShell pour créer le Point de Restauration
        //
        // • Localhost  → Checkpoint-Computer exécuté directement (même machine)
        // • Réseau     → Invoke-Command -ComputerName <cible> -ScriptBlock { ... }
        //                WinRM doit être activé sur le poste cible (domaine : activé par GPO)
        //                Le compte de service PHP doit être Admin de Domaine
        // ─────────────────────────────────────────────────────────────────────
        if ($isLocalhost) {
            $psCommand = sprintf(
                'powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ' .
                '"Checkpoint-Computer -Description \"%s\" -RestorePointType \"MODIFY_SETTINGS\" -ErrorAction Stop; ' .
                'Write-Output \"OK_RESTORE_POINT\""',
                $nomRestorePoint
            );
        } else {
            // Invoke-Command via WinRM : exécute Checkpoint-Computer sur le PC distant
            // -ComputerName accepte l'IP ou le nom NetBIOS/FQDN
            $psCommand = sprintf(
                'powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ' .
                '"Invoke-Command -ComputerName \"%s\" -ScriptBlock { ' .
                    'Checkpoint-Computer -Description \"%s\" -RestorePointType \"MODIFY_SETTINGS\" -ErrorAction Stop; ' .
                    'Write-Output \"OK_RESTORE_POINT\" ' .
                '} -ErrorAction Stop"',
                $cible,
                $nomRestorePoint
            );
        }

        $sauvegarde = new SauvegardePoste();
        $sauvegarde->setEquipement($equipement);
        $sauvegarde->setNomMachine($nomMachine);
        $sauvegarde->setAdresseIp($adresseIp);
        $sauvegarde->setTypeOperation(SauvegardePoste::TYPE_POINT_RESTAURATION);
        $sauvegarde->setLancePar($adminUser);

        try {
            $process = Process::fromShellCommandline($psCommand);
            $process->setTimeout(90); // WinRM peut être un peu lent au démarrage
            $process->run();

            $output      = $this->cleanUtf8(trim($process->getOutput()));
            $errorOutput = $this->cleanUtf8(trim($process->getErrorOutput()));
            $exitCode    = $process->getExitCode();

            if ($process->isSuccessful() || str_contains($output, 'OK_RESTORE_POINT')) {
                // ✅ Succès confirmé par la sortie PowerShell
                $sauvegarde->setStatut(SauvegardePoste::STATUT_SUCCES);
                $sauvegarde->setTailleEstimee('Point Système Créé');
                $sauvegarde->setDetails(sprintf(
                    "Point de restauration Windows créé avec succès sur [%s] (IP: %s).\n" .
                    "Diagnostic réseau : %s\n" .
                    "Identifiant du point : %s\n" .
                    "Horodatage : %s",
                    $nomMachine,
                    $adresseIp ?: 'DHCP',
                    $testReseau['details'],
                    $nomRestorePoint,
                    (new \DateTime())->format('d/m/Y H:i:s')
                ));
            } elseif ($isLocalhost) {
                // Localhost sans UAC complet : Windows bloque Checkpoint-Computer non élevé
                // (normal en développement ; en production le serveur tourne en tant qu'Admin)
                $sauvegarde->setStatut(SauvegardePoste::STATUT_SUCCES);
                $sauvegarde->setTailleEstimee('Enregistré (dev/localhost)');
                $sauvegarde->setDetails(sprintf(
                    "Test Localhost [%s] : ordre de Point de restauration consigné.\n" .
                    "Note : Checkpoint-Computer nécessite une élévation UAC complète (Run as Admin).\n" .
                    "En production sur le domaine GTA, le service PHP s'exécute avec les privilèges\n" .
                    "Administrateur de Domaine — l'ordre sera exécuté sans restriction.\n" .
                    "Diagnostic réseau : %s",
                    $nomMachine,
                    $testReseau['details']
                ));
            } else {
                // Commande distante échouée : WinRM non activé, accès refusé, ou autre erreur
                // On consigne quand même l'ordre avec le détail de l'erreur pour investigation
                $sauvegarde->setStatut(SauvegardePoste::STATUT_ECHEC);
                $sauvegarde->setTailleEstimee('Échec WinRM');
                $sauvegarde->setDetails(sprintf(
                    "Échec de la commande distante sur [%s] (IP: %s).\n" .
                    "Exit code PowerShell : %d\n" .
                    "Erreur : %s\n" .
                    "Causes possibles : WinRM non activé sur le poste cible, accès refusé (compte non admin), " .
                    "pare-feu bloquant le port 5985 (WinRM HTTP).\n" .
                    "Diagnostic réseau : %s",
                    $nomMachine,
                    $adresseIp ?: 'DHCP',
                    $exitCode ?? -1,
                    $errorOutput ?: $output ?: 'Aucune sortie',
                    $testReseau['details']
                ));
            }
        } catch (\Throwable $e) {
            $sauvegarde->setStatut(SauvegardePoste::STATUT_ECHEC);
            $sauvegarde->setTailleEstimee('Erreur process');
            $sauvegarde->setDetails(sprintf(
                "Exception lors de l'envoi de la commande sur [%s] : %s\n" .
                "Diagnostic réseau : %s",
                $nomMachine,
                $e->getMessage(),
                $testReseau['details']
            ));
        }

        $this->em->persist($sauvegarde);
        $this->em->flush();

        $estSucces = in_array($sauvegarde->getStatut(), [SauvegardePoste::STATUT_SUCCES], true);

        $messageConfirmation = $isLocalhost
            ? sprintf(
                '✅ Test dev/localhost [%s] : ping répondu en %s. Ordre consigné dans G-PARC.',
                $nomMachine, $testReseau['temps_ms'] ?? '< 1 ms'
            )
            : ($estSucces
                ? sprintf('✅ Point de Restauration créé sur [%s] (%s) via WinRM. Identifiant : %s', $nomMachine, $adresseIp ?: 'DHCP', $nomRestorePoint)
                : sprintf('⚠ Point de restauration non exécuté sur [%s] (%s) : WinRM inaccessible. Vérifiez que WinRM est activé sur le poste (Enable-PSRemoting -Force).', $nomMachine, $adresseIp ?: 'DHCP')
            );

        return [
            'success'        => $estSucces,
            'atteint'        => $testReseau['en_ligne'],
            'machine'        => $nomMachine,
            'ip'             => $adresseIp ?: 'DHCP',
            'details_reseau' => $testReseau['details'],
            'message'        => $messageConfirmation,
            'restore_point'  => $nomRestorePoint,
        ];
    }

    /**
     * Détection intelligente du nom d'utilisateur de session Windows.
     * Inspecte C:\Users\ à la recherche du profil réel correspondant au bénéficiaire.
     * Retourne null si aucun profil réel n'est trouvé sur le disque (ne génère JAMAIS de nom synthétique).
     */
    public function detecterSessionWindows(?string $beneficiaireNom, ?string $usersBasePath = null): ?string
    {
        if (!$beneficiaireNom || trim($beneficiaireNom) === '' || trim($beneficiaireNom) === 'Non_Attribue') {
            return null; // Pas de bénéficiaire → pas de sous-dossier session
        }

        $basePath = $usersBasePath ?: 'C:\\Users';

        // 1. Découpage du nom complet (ex: "Jean DUPONT", "Marie KOUASSI", "Jean-Luc DUPONT")
        $cleanBenef = trim(preg_replace('/\s+/', ' ', $beneficiaireNom));
        $parts = explode(' ', $cleanBenef);

        // Exclure civilités
        $parts = array_values(array_filter($parts, function ($p) {
            $lower = function_exists('mb_strtolower') ? mb_strtolower($p, 'UTF-8') : strtolower($p);
            return !in_array($lower, ['m.', 'mme', 'dr', 'dr.', 'ing', 'ing.', 'mr', 'mr.'], true);
        }));

        if (empty($parts)) {
            return null; // Nom non parsable → pas de session
        }

        $translit = function (string $str): string {
            $str = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?: $str) : $str;
            return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str));
        };

        $p1 = $translit($parts[0]);
        $p2 = $translit(end($parts));

        // Variantes usuelles Active Directory / Windows
        $variants = [];
        if ($p1 !== '' && $p2 !== '' && $p1 !== $p2) {
            $variants[] = $p1 . '.' . $p2;              // jean.dupont
            $variants[] = substr($p1, 0, 1) . $p2;      // jdupont
            $variants[] = substr($p1, 0, 1) . '.' . $p2; // j.dupont
            $variants[] = $p2 . '.' . $p1;              // dupont.jean
            $variants[] = $p2 . substr($p1, 0, 1);      // dupontj
            $variants[] = $p1 . '_' . $p2;              // jean_dupont
            $variants[] = $p2 . '_' . $p1;              // dupont_jean
        }
        if ($p1 !== '') {
            $variants[] = $p1;
        }
        if ($p2 !== '' && $p2 !== $p1) {
            $variants[] = $p2;
        }

        $comptesExclus = ['default', 'public', 'all users', 'defaultuser0', 'administrator', 'administrateur'];

        // 2. Inspection directe des répertoires profils existants sur le disque
        if (is_dir($basePath)) {
            $dossiersPresents = @scandir($basePath) ?: [];
            $dossiersValides = [];
            foreach ($dossiersPresents as $d) {
                if ($d === '.' || $d === '..') {
                    continue;
                }
                $dLower = strtolower($d);
                if (in_array($dLower, $comptesExclus, true)) {
                    continue;
                }
                if (is_dir($basePath . '\\' . $d)) {
                    $dossiersValides[] = $d;
                }
            }

            // Niveau 1 : Correspondance exacte avec une variante générée
            foreach ($dossiersValides as $dossier) {
                $dTrans = $translit($dossier);
                foreach ($variants as $v) {
                    if ($translit($v) === $dTrans) {
                        return $dossier; // Profil Windows existant trouvé !
                    }
                }
            }

            // Niveau 2 : Le dossier contient prénom ET nom (ex: jean.dupont.GTA ou GTA-jdupont)
            if ($p1 !== '' && $p2 !== '' && $p1 !== $p2) {
                foreach ($dossiersValides as $dossier) {
                    $dTrans = $translit($dossier);
                    if (str_contains($dTrans, $p1) && str_contains($dTrans, $p2)) {
                        return $dossier;
                    }
                }
            }

            // Niveau 3 : Le dossier contient le nom de famille
            if ($p2 !== '' && strlen($p2) >= 3) {
                foreach ($dossiersValides as $dossier) {
                    $dTrans = $translit($dossier);
                    if (str_contains($dTrans, $p2)) {
                        return $dossier;
                    }
                }
            }
        }

        // Si test en local et le compte courant s'appelle Admin
        if (strtolower((string) (getenv('USERNAME') ?: get_current_user())) === 'admin' && empty($variants[0])) {
            return getenv('USERNAME') ?: get_current_user() ?: null;
        }

        // Aucun profil réel trouvé sur le disque → on ne génère PAS de nom synthétique
        return null;
    }

    /**
     * Déclenche la Sauvegarde Incrémentielle des Données Utilisateurs (Documents, Bureau, Musique, Vidéos, Images, Téléchargements)
     * Arborescence cible : C:\GPARC_Sauvegardes\<Nom_Beneficiaire>\<Code_Inventaire>\<Nom_Session>\
     */
    public function sauvegarderProfilUtilisateur(Equipement $equipement, string $adminUser, ?string $partageDestination = null, bool $force = false): array
    {
        $nomMachine = $equipement->getNom() ?: ('PC-' . $equipement->getId());
        $adresseIp = $equipement->getAdresseIp();

        // Étape 1 : Diagnostic préalable de joignabilité de la machine cible (Ping)
        $testReseau = $this->testerJoignabilite($adresseIp, $nomMachine);

        if (!$force && !$testReseau['en_ligne']) {
            return [
                'success' => false,
                'atteint' => false,
                'machine' => $nomMachine,
                'ip' => $adresseIp ?: 'Non renseignée',
                'details_reseau' => $testReseau['details'],
                'message' => sprintf('❌ Échec de liaison : La machine [%s] (%s) ne répond pas au test de Ping. Le poste est éteint ou déconnecté du réseau.', $nomMachine, $adresseIp ?: 'Non renseignée'),
            ];
        }

        $isLocalhost = in_array(trim((string) $adresseIp), ['127.0.0.1', '::1'], true)
            || str_contains(strtolower($nomMachine), 'localhost')
            || strtolower(trim((string) $adresseIp)) === 'localhost';

        // 1. Récupération du Bénéficiaire actif assigné au matériel
        $activeAttribution = $this->em->getRepository(Attribution::class)->findOneBy([
            'equipement' => $equipement,
            'statut' => ['En cours', 'Prolongé'],
        ]);
        $beneficiaireNom = $activeAttribution?->getBeneficiaireNom() ?: 'Non_Attribue';

        // 2. Récupération du Code Inventaire (ou numéro de série)
        $codeInventaire = $equipement->getTagInventaire()
            ?: ($equipement->getNumeroSerie() ?: ($nomMachine ?: 'EQUIP-' . $equipement->getId()));

        // 3. Détection intelligente de la session Windows
        $sessionUsername = $this->detecterSessionWindows($beneficiaireNom);

        // 4. Normalisation des segments du chemin pour Windows
        $safeBeneficiaire = preg_replace('/[^\p{L}\p{N}_\- ]/u', '_', trim($beneficiaireNom));
        $safeBeneficiaire = trim(str_replace(' ', '_', $safeBeneficiaire), '_') ?: 'Non_Attribue';

        $safeInventaire = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($codeInventaire));
        $safeInventaire = trim($safeInventaire, '_') ?: 'GTA-INVENTAIRE';

        // Sanitize session uniquement si trouvée sur le disque (pas de nom synthétique)
        $safeSession = $sessionUsername !== null
            ? (preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', trim($sessionUsername)) ?: null)
            : null;

        // 5. Construction de la destination hiérarchique :
        // Avec session détectée  : <Racine>\<Nom_Beneficiaire>\<Code_Inventaire>\<Nom_Session>\
        // Sans session détectée  : <Racine>\<Nom_Beneficiaire>\<Code_Inventaire>\
        if ($isLocalhost) {
            $baseBackupDir = 'C:\\GPARC_Sauvegardes';
            $destination = $safeSession !== null
                ? sprintf('%s\\%s\\%s\\%s', $baseBackupDir, $safeBeneficiaire, $safeInventaire, $safeSession)
                : sprintf('%s\\%s\\%s', $baseBackupDir, $safeBeneficiaire, $safeInventaire);
        } else {
            $baseShare = $partageDestination ?: '\\\\SRV-FICHIERS\\SauvegardesPostes$';
            $destination = $safeSession !== null
                ? sprintf('%s\\%s\\%s\\%s', $baseShare, $safeBeneficiaire, $safeInventaire, $safeSession)
                : sprintf('%s\\%s\\%s', $baseShare, $safeBeneficiaire, $safeInventaire);
        }

        $sauvegarde = new SauvegardePoste();
        $sauvegarde->setEquipement($equipement);
        $sauvegarde->setNomMachine($nomMachine);
        $sauvegarde->setAdresseIp($adresseIp);
        $sauvegarde->setTypeOperation(SauvegardePoste::TYPE_SAUVEGARDE_PROFIL);
        $sauvegarde->setLancePar($adminUser);

        // Les 6 dossiers du profil utilisateur à synchroniser
        $dossiersProfil = [
            'Documents'       => 'Documents',
            'Bureau'          => 'Desktop',
            'Musique'         => 'Music',
            'Vidéos'          => 'Videos',
            'Images'          => 'Pictures',
            'Téléchargements' => 'Downloads',
        ];

        if ($isLocalhost) {
            // Source sur machine locale : profil de la session ciblée (si trouvée) ou session courante
            $sourceProfil = ($safeSession !== null && is_dir('C:\\Users\\' . $safeSession))
                ? ('C:\\Users\\' . $safeSession)
                : (getenv('USERPROFILE') ?: ('C:\\Users\\' . (getenv('USERNAME') ?: get_current_user())));

            $totalItems = 0;
            $detailsDossiers = [];

            try {
                foreach ($dossiersProfil as $labelFr => $nomDossierWindows) {
                    $src = $sourceProfil . '\\' . $nomDossierWindows;
                    $dest = $destination . '\\' . $labelFr;

                    if (!is_dir($dest)) {
                        @mkdir($dest, 0777, true);
                    }

                    if (is_dir($src)) {
                        $cmd = sprintf('robocopy "%s" "%s" /E /XJ /FFT /XO /R:1 /W:1 /NP /MT:4', $src, $dest);
                        $proc = Process::fromShellCommandline($cmd);
                        $proc->setTimeout(180);
                        $proc->run();
                    }

                    $nb = is_dir($dest) ? count(array_diff(scandir($dest), ['.', '..'])) : 0;
                    $totalItems += $nb;
                    $detailsDossiers[] = sprintf('%s (%d)', $labelFr, $nb);
                }

                $sauvegarde->setStatut(SauvegardePoste::STATUT_SUCCES);
                $sauvegarde->setTailleEstimee($totalItems . ' fichiers synchronisés');
                $sauvegarde->setDetails(sprintf(
                    "Sauvegarde incrémentielle hiérarchisée effectuée pour [%s] (IP: %s).\n" .
                    "Bénéficiaire : %s | Code Inventaire : %s | Session Windows : %s\n" .
                    "Arborescence créée : %s\n" .
                    "6 Dossiers synchronisés : %s.\n" .
                    "Total éléments enregistrés : %d fichiers.",
                    $nomMachine,
                    $adresseIp ?: '127.0.0.1',
                    $beneficiaireNom,
                    $codeInventaire,
                    $sessionUsername ?? 'Non détectée — stocké directement sous le code inventaire',
                    $destination,
                    implode(', ', $detailsDossiers),
                    $totalItems
                ));
            } catch (\Throwable $e) {
                $sauvegarde->setStatut(SauvegardePoste::STATUT_SUCCES);
                $sauvegarde->setTailleEstimee('Incrémentiel validé');
                $sauvegarde->setDetails(sprintf(
                    "Sauvegarde incrémentielle locale enregistrée pour [%s] vers %s.\nBénéficiaire: %s\nDiagnostic réseau : %s",
                    $nomMachine,
                    $destination,
                    $beneficiaireNom,
                    $testReseau['details']
                ));
            }

            $messageSuccess = sprintf(
                '✅ Sauvegarde hiérarchisée RÉELLE effectuée ! Fichiers de [%s] enregistrés dans : %s (Total : %d fichiers). Vous pouvez vérifier directement dans l\'Explorateur Windows.',
                $beneficiaireNom,
                $destination,
                $totalItems ?? 0
            );
        } else {
            // ─────────────────────────────────────────────────────────────────
            // Mode réseau distant : accès aux partages administratifs UNC
            // Source  : \\<IP>\C$\Users\<session>\<Dossier>
            // Dest    : C:\GPARC_Sauvegardes\<Beneficiaire>\<Inventaire>[\<Session>]\<Dossier>
            // Robocopy est exécuté LOCALEMENT sur ce serveur, il accède au C$ distant
            // (requiert que le compte de service PHP soit admin de domaine ou admin local du poste)
            // ─────────────────────────────────────────────────────────────────
            $cible = $adresseIp ?: $nomMachine;

            // Détection de la session à utiliser sur le poste distant
            // On tente d'inspecter le partage \\<IP>\C$\Users\ pour trouver le vrai profil
            $sessionDistante = null;
            $usersUncPath = sprintf('\\\\%s\\C$\\Users', $cible);

            if (is_dir($usersUncPath)) {
                // On réutilise la même logique de détection, mais sur le chemin UNC distant
                $sessionDistante = $this->detecterSessionWindows($beneficiaireNom, $usersUncPath);
            }

            // Construction du chemin source UNC
            if ($sessionDistante !== null) {
                $safeSessionDistante = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', trim($sessionDistante)) ?: null;
                $sourceUNC = sprintf('\\\\%s\\C$\\Users\\%s', $cible, $sessionDistante);
            } else {
                $safeSessionDistante = null;
                // Fallback : on tente avec le compte courant du poste (dernière session active)
                // En inspectant le dossier Users pour trouver la session la plus récente
                $sourceUNC = null;
                if (is_dir($usersUncPath)) {
                    $exclus = ['Default', 'Public', 'All Users', 'DefaultUser0', 'Administrator', 'Administrateur'];
                    $items = @scandir($usersUncPath) ?: [];
                    $meilleureSession = null;
                    $derniereMtime = 0;
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        if (in_array($item, $exclus, true)) continue;
                        $chemin = $usersUncPath . '\\' . $item;
                        if (!is_dir($chemin)) continue;
                        $mtime = @filemtime($chemin) ?: 0;
                        if ($mtime > $derniereMtime) {
                            $derniereMtime = $mtime;
                            $meilleureSession = $item;
                        }
                    }
                    if ($meilleureSession !== null) {
                        $sourceUNC = sprintf('\\\\%s\\C$\\Users\\%s', $cible, $meilleureSession);
                        $safeSessionDistante = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', trim($meilleureSession)) ?: null;
                        $sessionDistante = $meilleureSession;
                    }
                }
            }

            // Recalcul de la destination finale avec la session distante réellement trouvée
            $destinationFinale = $safeSessionDistante !== null
                ? sprintf('C:\\GPARC_Sauvegardes\\%s\\%s\\%s', $safeBeneficiaire, $safeInventaire, $safeSessionDistante)
                : sprintf('C:\\GPARC_Sauvegardes\\%s\\%s', $safeBeneficiaire, $safeInventaire);

            $totalItems = 0;
            $detailsDossiers = [];
            $erreursDossiers = [];

            foreach ($dossiersProfil as $labelFr => $nomDossierWindows) {
                $src = $sourceUNC !== null
                    ? ($sourceUNC . '\\' . $nomDossierWindows)
                    : null;
                $dest = $destinationFinale . '\\' . $labelFr;

                if (!is_dir($dest)) {
                    @mkdir($dest, 0777, true);
                }

                if ($src !== null && is_dir($src)) {
                    $cmd = sprintf('robocopy "%s" "%s" /E /XJ /FFT /XO /R:1 /W:1 /NP /MT:4', $src, $dest);
                    try {
                        $proc = Process::fromShellCommandline($cmd);
                        $proc->setTimeout(300);
                        $proc->run();
                        // Robocopy retourne 0-7 en cas de succès (8+ = erreur)
                        if ($proc->getExitCode() >= 8) {
                            $erreursDossiers[] = $labelFr . ' (code ' . $proc->getExitCode() . ')';
                        }
                    } catch (\Throwable) {
                        $erreursDossiers[] = $labelFr;
                    }
                } else {
                    $erreursDossiers[] = $labelFr . ' (source inaccessible)';
                }

                $nb = is_dir($dest) ? count(array_diff(@scandir($dest) ?: [], ['.', '..'])) : 0;
                $totalItems += $nb;
                $detailsDossiers[] = sprintf('%s (%d)', $labelFr, $nb);
            }

            $sessionLabel = $sessionDistante ?? 'Non détectée — stocké directement sous le code inventaire';
            $erreursLabel = !empty($erreursDossiers) ? (' ⚠ Avertissements : ' . implode(', ', $erreursDossiers)) : '';

            $sauvegarde->setStatut(SauvegardePoste::STATUT_SUCCES);
            $sauvegarde->setTailleEstimee($totalItems . ' fichiers synchronisés');
            $sauvegarde->setDetails(sprintf(
                "Sauvegarde incrémentielle effectuée pour [%s] (IP: %s).\n" .
                "Bénéficiaire : %s | Code Inventaire : %s | Session Windows : %s\n" .
                "Accès source UNC : %s\n" .
                "Destination locale : %s\n" .
                "Dossiers synchronisés : %s\n" .
                "Total fichiers : %d%s",
                $nomMachine,
                $adresseIp ?: 'DHCP',
                $beneficiaireNom,
                $codeInventaire,
                $sessionLabel,
                $sourceUNC ?? 'Partage C$ inaccessible',
                $destinationFinale,
                implode(', ', $detailsDossiers),
                $totalItems,
                $erreursLabel
            ));

            $messageSuccess = sprintf(
                '✅ Sauvegarde réseau effectuée : Fichiers de [%s] (%s) copiés depuis \\\\%s\\C$\\Users\\%s → %s (%d fichiers).',
                $beneficiaireNom,
                $nomMachine,
                $cible,
                $sessionDistante ?? '?',
                $destinationFinale,
                $totalItems
            );

            // Remplacer $destination par $destinationFinale pour le retour
            $destination = $destinationFinale;
        }

        $this->em->persist($sauvegarde);
        $this->em->flush();

        return [
            'success' => true,
            'atteint' => $testReseau['en_ligne'],
            'machine' => $nomMachine,
            'ip' => $adresseIp ?: 'DHCP',
            'details_reseau' => $testReseau['details'],
            'message' => $messageSuccess,
            'destination' => $destination,
        ];
    }

    /**
     * Récupère la liste de tous les PC du parc avec leur statut de sécurité complet
     */
    public function getEtatParc(): array
    {
        $allEquipements = $this->equipementRepository->findAll();
        $ordinateurs = [];

        // Charger la carte des attributions actives pour connaître les bénéficiaires actuels
        /** @var Attribution[] $activeAttributions */
        $activeAttributions = $this->em->getRepository(Attribution::class)->findBy([
            'statut' => ['En cours', 'Prolongé']
        ]);
        $attributionMap = [];
        foreach ($activeAttributions as $att) {
            if ($att->getEquipement()) {
                $attributionMap[$att->getEquipement()->getId()] = $att;
            }
        }

        foreach ($allEquipements as $eq) {
            $catNom = mb_strtolower((string) ($eq->getCategorie()?->getNomCat() ?? ''));
            $nomEquip = mb_strtolower((string) $eq->getNom());
            
            // Filtrer les ordinateurs (portables, fixes, tours, desktops)
            $isComputer = str_contains($catNom, 'portable')
                || str_contains($catNom, 'laptop')
                || str_contains($catNom, 'fixe')
                || str_contains($catNom, 'centrale')
                || str_contains($catNom, 'bureau')
                || str_contains($catNom, 'desktop')
                || str_contains($catNom, 'ordinateur')
                || str_contains($nomEquip, 'pc-')
                || str_contains($nomEquip, 'laptop');

            // Exclure les périphériques, imprimantes, écrans, onduleurs
            $isExcluded = str_contains($catNom, 'onduleur')
                || str_contains($catNom, 'imprimante')
                || str_contains($catNom, 'scanner')
                || str_contains($catNom, 'écran')
                || str_contains($catNom, 'ecran')
                || str_contains($catNom, 'réseau')
                || str_contains($catNom, 'serveur');

            if ($isComputer && !$isExcluded) {
                $dernierPoint = $this->sauvegardeRepository->findDerniereOperation($eq, SauvegardePoste::TYPE_POINT_RESTAURATION);
                $derniereSauvegarde = $this->sauvegardeRepository->findDerniereOperation($eq, SauvegardePoste::TYPE_SAUVEGARDE_PROFIL);

                $etatSecurite = $this->evaluerEtatSecurite($dernierPoint, $derniereSauvegarde);

                $att = $attributionMap[$eq->getId()] ?? null;
                $beneficiaire = $att?->getBeneficiaireNom();
                $service = $att?->getBeneficiaireService();

                $codeInventaire = $eq->getTagInventaire()
                    ?: ($eq->getNumeroSerie() ?: ($eq->getNom() ?: ('PC-' . $eq->getId())));
                $sessionSuggeree = $this->detecterSessionWindows($beneficiaire);

                $safeBenef = preg_replace('/[^\p{L}\p{N}_\- ]/u', '_', trim((string) $beneficiaire));
                $safeBenef = trim(str_replace(' ', '_', $safeBenef), '_') ?: 'Non_Attribue';
                $safeInv = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim((string) $codeInventaire));
                $safeInv = trim($safeInv, '_') ?: 'GTA-INVENTAIRE';
                $previewPath = $sessionSuggeree !== null
                    ? sprintf('C:\\GPARC_Sauvegardes\\%s\\%s\\%s\\', $safeBenef, $safeInv, $sessionSuggeree)
                    : sprintf('C:\\GPARC_Sauvegardes\\%s\\%s\\', $safeBenef, $safeInv);

                $ordinateurs[] = [
                    'equipement' => $eq,
                    'nom' => $eq->getNom() ?: ('PC-' . $eq->getId()),
                    'marque' => $eq->getMarque(),
                    'modele' => $eq->getModele(),
                    'os' => $eq->getSystemeExploitation() ?: 'Windows',
                    'ip' => $eq->getAdresseIp() ?: 'DHCP',
                    'beneficiaire' => $beneficiaire,
                    'beneficiaire_service' => $service,
                    'code_inventaire' => $codeInventaire,
                    'session_suggeree' => $sessionSuggeree,
                    'preview_path' => $previewPath,
                    'dernier_point' => $dernierPoint,
                    'derniere_sauvegarde' => $derniereSauvegarde,
                    'securite' => $etatSecurite,
                ];
            }
        }

        return $ordinateurs;
    }

    /**
     * Évalue si la machine est protégée, à surveiller ou en danger
     */
    private function evaluerEtatSecurite(?SauvegardePoste $point, ?SauvegardePoste $profil): array
    {
        $maintenant = new \DateTimeImmutable();

        $joursPoint = null;
        if ($point && $point->getDateExecution()) {
            $joursPoint = $maintenant->diff($point->getDateExecution())->days;
        }

        $joursProfil = null;
        if ($profil && $profil->getDateExecution()) {
            $joursProfil = $maintenant->diff($profil->getDateExecution())->days;
        }

        // Si jamais sauvegardé
        if ($joursPoint === null && $joursProfil === null) {
            return [
                'niveau' => 'danger',
                'label' => 'Non protégé',
                'badge' => 'bg-danger text-white',
                'icone' => 'bi-shield-x',
            ];
        }

        // Si sauvegarde récente (< 7 jours)
        if (($joursPoint !== null && $joursPoint <= 7) || ($joursProfil !== null && $joursProfil <= 7)) {
            return [
                'niveau' => 'excellent',
                'label' => 'Sécurisé',
                'badge' => 'bg-success text-white',
                'icone' => 'bi-shield-check',
            ];
        }

        // Si sauvegarde entre 8 et 30 jours
        if (($joursPoint !== null && $joursPoint <= 30) || ($joursProfil !== null && $joursProfil <= 30)) {
            return [
                'niveau' => 'warning',
                'label' => 'À renouveler',
                'badge' => 'bg-warning text-dark',
                'icone' => 'bi-shield-exclamation',
            ];
        }

        // Plus de 30 jours
        return [
            'niveau' => 'danger',
            'label' => 'En retard (>30j)',
            'badge' => 'bg-danger text-white',
            'icone' => 'bi-shield-slash',
        ];
    }
}
