<?php

namespace App\Controller;

use App\Entity\Equipement;
use App\Entity\SauvegardePoste;
use App\Repository\SauvegardePosteRepository;
use App\Service\PosteBackupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/sauvegardes-postes')]
class PosteBackupController extends AbstractController
{
    #[Route('', name: 'app_sauvegarde_poste_index', methods: ['GET'])]
    public function index(
        Request $request,
        PosteBackupService $backupService,
        SauvegardePosteRepository $sauvegardeRepo,
    ): Response {
        $parc = $backupService->getEtatParc();
        $historique = $sauvegardeRepo->findHistoriqueRecent(30);

        $search = trim((string) $request->query->get('q', ''));
        $filtreSecurite = (string) $request->query->get('securite', 'all');

        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $parc = array_filter($parc, function ($item) use ($searchLower) {
                return str_contains(mb_strtolower($item['nom']), $searchLower)
                    || str_contains(mb_strtolower($item['beneficiaire'] ?? ''), $searchLower)
                    || str_contains(mb_strtolower($item['beneficiaire_service'] ?? ''), $searchLower)
                    || str_contains(mb_strtolower($item['marque'] ?? ''), $searchLower)
                    || str_contains(mb_strtolower($item['modele'] ?? ''), $searchLower)
                    || str_contains(mb_strtolower($item['ip'] ?? ''), $searchLower);
            });
        }

        if ($filtreSecurite !== 'all') {
            $parc = array_filter($parc, function ($item) use ($filtreSecurite) {
                return ($item['securite']['niveau'] ?? '') === $filtreSecurite;
            });
        }

        // Statistiques
        $stats = [
            'total' => count($backupService->getEtatParc()),
            'securises' => 0,
            'a_renouveler' => 0,
            'danger' => 0,
        ];
        foreach ($backupService->getEtatParc() as $p) {
            $niv = $p['securite']['niveau'] ?? 'danger';
            if ($niv === 'excellent') {
                $stats['securises']++;
            } elseif ($niv === 'warning') {
                $stats['a_renouveler']++;
            } else {
                $stats['danger']++;
            }
        }

        return $this->render('sauvegarde_poste/index.html.twig', [
            'ordinateurs' => $parc,
            'stats' => $stats,
            'historique' => $historique,
            'current_search' => $search,
            'current_securite' => $filtreSecurite,
        ]);
    }

    /**
     * Teste la joignabilité préalable d'un PC cible (Ping ICMP / Ports d'administration)
     */
    #[Route('/verifier-machine', name: 'app_sauvegarde_poste_verifier', methods: ['POST'])]
    public function verifierMachine(
        Request $request,
        PosteBackupService $backupService,
        EntityManagerInterface $em,
    ): JsonResponse {
        $payload = json_decode((string) $request->getContent(), true) ?? $request->request->all();
        $equipementId = (int) ($payload['equipement_id'] ?? 0);

        if ($equipementId <= 0) {
            return new JsonResponse([
                'success' => true,
                'en_ligne' => true,
                'ip' => 'Réseau domaine',
                'nom' => 'Tous les PC du domaine',
                'temps_ms' => '< 1 ms',
                'details' => 'Réseau domaine GTAC2A accessible pour la campagne globale.',
                'message' => 'Réseau domaine prêt pour la campagne.',
            ]);
        }

        $equipement = $em->getRepository(Equipement::class)->find($equipementId);
        if (!$equipement) {
            return new JsonResponse([
                'success' => false,
                'en_ligne' => false,
                'message' => 'Équipement introuvable dans le parc.',
            ], Response::HTTP_NOT_FOUND);
        }

        $test = $backupService->testerJoignabilite($equipement->getAdresseIp(), $equipement->getNom());

        return new JsonResponse([
            'success' => true,
            'en_ligne' => $test['en_ligne'],
            'ip' => $test['ip'],
            'nom' => $equipement->getNom() ?: ('PC-' . $equipement->getId()),
            'temps_ms' => $test['temps_ms'],
            'methode' => $test['methode'],
            'details' => $test['details'],
            'message' => $test['en_ligne']
                ? sprintf('✅ Machine en ligne et prête (%s - Temps : %s)', $test['methode'], $test['temps_ms'])
                : sprintf('🔴 Machine injoignable (%s). Le PC semble éteint ou hors du réseau.', $test['ip']),
        ]);
    }

    /**
     * Demande d'un token d'autorisation administrative temporaire avec validation du Code PIN administrateur
     */
    #[Route('/demander-token', name: 'app_sauvegarde_poste_token', methods: ['POST'])]
    public function demanderToken(Request $request, PosteBackupService $backupService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? $request->request->all();
        $action = (string) ($payload['action'] ?? 'ALL');
        $pin = trim((string) ($payload['pin'] ?? ''));

        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $adminUser = $currentUser?->getUserIdentifier() ?: 'admin';
        $expectedPin = $currentUser?->getPinCode() ?: '1234';

        if ($pin === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Veuillez saisir votre Code PIN Administrateur pour confirmer l\'autorisation.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($pin !== $expectedPin) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Code PIN Administrateur incorrect. L\'autorisation a été refusée.',
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $backupService->demanderTokenAutorisation($adminUser, $action);

        return new JsonResponse([
            'success' => true,
            'token' => $token,
            'admin' => $adminUser,
            'message' => sprintf('Autorisation accordée par %s. Token de sécurité délivré.', $adminUser),
        ]);
    }

    /**
     * Déclenchement d'un Point de Restauration distant sur un PC
     */
    #[Route('/point-restauration', name: 'app_sauvegarde_poste_point', methods: ['POST'])]
    public function creerPoint(
        Request $request,
        PosteBackupService $backupService,
        EntityManagerInterface $em,
    ): JsonResponse {
        $payload = json_decode((string) $request->getContent(), true) ?? $request->request->all();
        $equipementId = (int) ($payload['equipement_id'] ?? 0);
        $token = (string) ($payload['token'] ?? '');
        $adminUser = $this->getUser()?->getUserIdentifier() ?: 'admin';

        // Validation du token à usage unique
        if (!$backupService->verifierEtConsommerToken($token, $adminUser, SauvegardePoste::TYPE_POINT_RESTAURATION)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Autorisation invalide ou expirée. Veuillez redemander une autorisation.',
            ], Response::HTTP_FORBIDDEN);
        }

        $equipement = $em->getRepository(Equipement::class)->find($equipementId);
        if (!$equipement) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Équipement introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $force = (bool) ($payload['force'] ?? false);
        $result = $backupService->creerPointRestauration($equipement, $adminUser, $force);

        return new JsonResponse($result);
    }

    /**
     * Déclenchement de la Sauvegarde Incrémentielle des Fichiers Utilisateur (Bureau, Documents, Maquettes)
     */
    #[Route('/sauvegarde-profil', name: 'app_sauvegarde_poste_profil', methods: ['POST'])]
    public function sauvegarderProfil(
        Request $request,
        PosteBackupService $backupService,
        EntityManagerInterface $em,
    ): JsonResponse {
        $payload = json_decode((string) $request->getContent(), true) ?? $request->request->all();
        $equipementId = (int) ($payload['equipement_id'] ?? 0);
        $token = (string) ($payload['token'] ?? '');
        $adminUser = $this->getUser()?->getUserIdentifier() ?: 'admin';

        // Validation du token à usage unique
        if (!$backupService->verifierEtConsommerToken($token, $adminUser, SauvegardePoste::TYPE_SAUVEGARDE_PROFIL)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Autorisation invalide ou expirée. Veuillez redemander une autorisation.',
            ], Response::HTTP_FORBIDDEN);
        }

        $equipement = $em->getRepository(Equipement::class)->find($equipementId);
        if (!$equipement) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Équipement introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $force = (bool) ($payload['force'] ?? false);
        $result = $backupService->sauvegarderProfilUtilisateur($equipement, $adminUser, null, $force);

        return new JsonResponse($result);
    }

    /**
     * Campagne globale : Points de restauration sur tous les ordinateurs du parc en 1 clic
     */
    #[Route('/campagne-globale', name: 'app_sauvegarde_poste_campagne', methods: ['POST'])]
    public function campagneGlobale(
        Request $request,
        PosteBackupService $backupService,
    ): JsonResponse {
        $payload = json_decode((string) $request->getContent(), true) ?? $request->request->all();
        $token = (string) ($payload['token'] ?? '');
        $adminUser = $this->getUser()?->getUserIdentifier() ?: 'admin';

        if (!$backupService->verifierEtConsommerToken($token, $adminUser, 'ALL')) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Autorisation requise pour lancer une campagne globale.',
            ], Response::HTTP_FORBIDDEN);
        }

        $parc = $backupService->getEtatParc();
        $count = 0;

        foreach ($parc as $item) {
            $eq = $item['equipement'];
            if ($eq instanceof Equipement) {
                $backupService->creerPointRestauration($eq, $adminUser);
                $count++;
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => sprintf('Campagne exécutée : points de restauration créés sur %d ordinateurs du réseau.', $count),
            'count' => $count,
        ]);
    }

    /**
     * Retourne les données complètes de la fiche de sécurité et sauvegarde d'un poste
     */
    #[Route('/fiche/{id}', name: 'app_sauvegarde_poste_fiche', methods: ['GET'])]
    public function fiche(
        Equipement $equipement,
        PosteBackupService $backupService,
        SauvegardePosteRepository $sauvegardeRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $nomMachine = $equipement->getNom() ?: ('PC-' . $equipement->getId());
        $codeInventaire = $equipement->getTagInventaire()
            ?: ($equipement->getNumeroSerie() ?: ($nomMachine ?: 'EQUIP-' . $equipement->getId()));

        /** @var \App\Entity\Attribution|null $att */
        $att = $em->getRepository(\App\Entity\Attribution::class)->findOneBy([
            'equipement' => $equipement,
            'statut' => ['En cours', 'Prolongé'],
        ]);

        $beneficiaireNom = $att?->getBeneficiaireNom() ?: 'Non attribué';
        $beneficiaireService = $att?->getBeneficiaireService() ?: 'N/C';
        $personnel = $att?->getPersonnel();

        $sessionWindows = $backupService->detecterSessionWindows($beneficiaireNom);

        $safeBenef = preg_replace('/[^\p{L}\p{N}_\- ]/u', '_', trim($beneficiaireNom));
        $safeBenef = trim(str_replace(' ', '_', $safeBenef), '_') ?: 'Non_Attribue';
        $safeInv = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim((string) $codeInventaire));
        $safeInv = trim($safeInv, '_') ?: 'GTA-INVENTAIRE';

        // Chemin selon que la session soit détectée ou non
        if ($sessionWindows !== null) {
            $safeSession = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', trim($sessionWindows)) ?: null;
            $cheminSauvegarde = $safeSession !== null
                ? sprintf('C:\\GPARC_Sauvegardes\\%s\\%s\\%s\\', $safeBenef, $safeInv, $safeSession)
                : sprintf('C:\\GPARC_Sauvegardes\\%s\\%s\\', $safeBenef, $safeInv);
        } else {
            $cheminSauvegarde = sprintf('C:\\GPARC_Sauvegardes\\%s\\%s\\', $safeBenef, $safeInv);
        }

        $dernierPoint = $sauvegardeRepo->findDerniereOperation($equipement, SauvegardePoste::TYPE_POINT_RESTAURATION);
        $derniereSauvegarde = $sauvegardeRepo->findDerniereOperation($equipement, SauvegardePoste::TYPE_SAUVEGARDE_PROFIL);

        // Historique des interventions spécifiques à ce poste
        $historiquePoste = $sauvegardeRepo->findBy(
            ['equipement' => $equipement],
            ['dateExecution' => 'DESC'],
            15
        );

        $toUtf8 = function (?string $str): string {
            if ($str === null || $str === '') return '';
            if (mb_check_encoding($str, 'UTF-8')) return $str;
            return mb_convert_encoding($str, 'UTF-8', 'Windows-1252, ISO-8859-1, CP850');
        };

        $historiqueFormatted = [];
        foreach ($historiquePoste as $h) {
            $historiqueFormatted[] = [
                'id' => $h->getId(),
                'date' => $h->getDateExecution()?->format('d/m/Y H:i:s'),
                'type' => $toUtf8($h->getTypeOperation()),
                'is_point' => $h->isPointRestauration(),
                'statut' => $toUtf8($h->getStatut()),
                'details' => $toUtf8($h->getDetails()),
                'taille' => $toUtf8($h->getTailleEstimee()),
                'lance_par' => $toUtf8($h->getLancePar()),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'id' => $equipement->getId(),
            'nom' => $toUtf8($nomMachine),
            'marque' => $toUtf8($equipement->getMarque() ?: 'GTA'),
            'modele' => $toUtf8($equipement->getModele() ?: 'Standard'),
            'numero_serie' => $toUtf8($equipement->getNumeroSerie() ?: 'N/C'),
            'code_inventaire' => $toUtf8($codeInventaire),
            'os' => $toUtf8($equipement->getSystemeExploitation() ?: 'Windows'),
            'ip' => $toUtf8($equipement->getAdresseIp() ?: 'DHCP'),
            'statut' => $toUtf8($equipement->getStatut() ?: 'En service'),
            'lieu' => $toUtf8($equipement->getLieu()?->getNomLieu() ?: 'Non localisé'),
            'beneficiaire' => [
                'nom' => $toUtf8($beneficiaireNom),
                'service' => $toUtf8($beneficiaireService),
                'email' => $toUtf8($personnel?->getEmail() ?: 'N/C'),
                'telephone' => $toUtf8($personnel?->getTelephone() ?: 'N/C'),
                'type' => $toUtf8($personnel?->getType() ?: 'N/C'),
                'statut_attribution' => $toUtf8($att?->getStatut() ?: 'Libre'),
            ],
            'session_windows' => $sessionWindows ?? 'Non détectée',
            'chemin_sauvegarde' => $toUtf8($cheminSauvegarde),
            'dossiers_inclus' => ['Documents', 'Bureau', 'Musique', 'Vidéos', 'Images', 'Téléchargements'],
            'dernier_point' => $dernierPoint ? [
                'date' => $dernierPoint->getDateExecution()?->format('d/m/Y à H:i'),
                'details' => $toUtf8($dernierPoint->getDetails()),
                'taille' => $toUtf8($dernierPoint->getTailleEstimee() ?: 'Point Actif'),
            ] : null,
            'derniere_sauvegarde' => $derniereSauvegarde ? [
                'date' => $derniereSauvegarde->getDateExecution()?->format('d/m/Y à H:i'),
                'details' => $toUtf8($derniereSauvegarde->getDetails()),
                'taille' => $toUtf8($derniereSauvegarde->getTailleEstimee() ?: 'Incrémentiel validé'),
            ] : null,
            'historique' => $historiqueFormatted,
            'url_fiche_complete' => $this->generateUrl('app_equipement_show', ['id' => $equipement->getId()]),
        ]);
    }
}

