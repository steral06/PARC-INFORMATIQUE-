<?php

namespace App\Controller\Api;

use App\Entity\Attribution;
use App\Entity\Composant;
use App\Entity\Equipement;
use App\Entity\Lieu;
use App\Entity\TicketMaintenance;
use App\Repository\EquipementRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api', name: 'api_')]
class EquipementApiController extends AbstractController
{
    /**
     * Liste filtrée et paginée des équipements en JSON.
     */
    #[Route('/equipements', name: 'equipements_list', methods: ['GET'])]
    public function list(Request $request, EquipementRepository $repo): JsonResponse
    {
        $q       = trim((string) $request->query->get('q', ''));
        $statut  = trim((string) $request->query->get('statut', ''));
        $catId   = $request->query->get('categorie_id');
        $lieuId  = $request->query->get('lieu_id');
        $limit   = max(1, min(200, (int) $request->query->get('limit', 50)));
        $page    = max(1, (int) $request->query->get('page', 1));
        $offset  = ($page - 1) * $limit;

        $qb = $repo->createQueryBuilder('e')
            ->leftJoin('e.categorie', 'c')
            ->leftJoin('e.lieu', 'l')
            ->addSelect('c', 'l')
            ->orderBy('e.dateAjout', 'DESC');

        if ($q !== '') {
            $qb->andWhere('e.nom LIKE :q OR e.numeroSerie LIKE :q OR e.tagInventaire LIKE :q OR e.modele LIKE :q OR e.adresseIp LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        if ($statut !== '' && $statut !== 'all') {
            $qb->andWhere('e.statut = :statut')->setParameter('statut', $statut);
        }

        if ($catId) {
            $qb->andWhere('c.id = :catId')->setParameter('catId', $catId);
        }

        if ($lieuId) {
            $qb->andWhere('l.id = :lieuId')->setParameter('lieuId', $lieuId);
        }

        $totalCount = (int) (clone $qb)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        $qb->setFirstResult($offset)->setMaxResults($limit);
        /** @var Equipement[] $items */
        $items = $qb->getQuery()->getResult();

        $data = [];
        foreach ($items as $eq) {
            $data[] = $this->serializeEquipementSummary($eq);
        }

        return $this->json([
            'success'     => true,
            'total'       => $totalCount,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil($totalCount / $limit),
            'data'        => $data,
        ]);
    }

    /**
     * Fiche technique complète d'un équipement (avec pièces montées, bénéficiaire et historique SAV).
     */
    #[Route('/equipements/{id}', name: 'equipement_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Equipement|null $eq */
        $eq = $em->getRepository(Equipement::class)->find($id);
        if (!$eq) {
            return $this->json(['success' => false, 'error' => 'Équipement introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Attribution en cours
        $att = $em->getRepository(Attribution::class)->findOneBy(['equipement' => $eq, 'statut' => 'En cours']);

        // Composants montés
        $composants = $em->getRepository(Composant::class)->findBy(['equipement' => $eq]);
        $composantsData = [];
        foreach ($composants as $c) {
            $composantsData[] = [
                'id'          => $c->getId(),
                'nom'         => $c->getNom(),
                'type'        => $c->getType(),
                'numeroSerie' => $c->getNumeroSerie(),
                'quantite'    => $c->getQuantite(),
            ];
        }

        // Tickets de maintenance récents
        $tickets = $em->getRepository(TicketMaintenance::class)->findBy(['equipement' => $eq], ['dateCreation' => 'DESC'], 5);
        $ticketsData = [];
        foreach ($tickets as $t) {
            $ticketsData[] = [
                'id'       => $t->getId(),
                'titre'    => $t->getTitre(),
                'priorite' => $t->getPriorite(),
                'statut'   => $t->getStatut(),
                'date'     => $t->getDateCreation()?->format('Y-m-d'),
            ];
        }

        $result = $this->serializeEquipementSummary($eq);
        $result['notes'] = $eq->getNotes();
        $result['beneficiaire'] = $att ? [
            'id'                  => $att->getId(),
            'nom'                 => $att->getBeneficiaireNom(),
            'service'             => $att->getBeneficiaireService(),
            'dateDebut'           => $att->getDateDebut()?->format('Y-m-d'),
            'dateFinPrevue'       => $att->getDateFinPrevue()?->format('Y-m-d'),
        ] : null;
        $result['composants'] = $composantsData;
        $result['tickets_recents'] = $ticketsData;

        return $this->json(['success' => true, 'data' => $result]);
    }

    /**
     * Recherche instantanée pour lecteur code-barres / douchette / QR code.
     * Accepte : S/N, Tag d'inventaire, IP ou ID numérique.
     */
    #[Route('/equipements/scan/{query}', name: 'equipement_scan', methods: ['GET'])]
    public function scan(string $query, EntityManagerInterface $em): JsonResponse
    {
        $q = trim(urldecode($query));
        if ($q === '') {
            return $this->json(['success' => false, 'error' => 'Identifiant de scan requis.'], Response::HTTP_BAD_REQUEST);
        }

        $repo = $em->getRepository(Equipement::class);

        // 1. Recherche par S/N ou Tag exact
        $eq = $repo->findOneBy(['tagInventaire' => $q]);
        if (!$eq) {
            $eq = $repo->findOneBy(['numeroSerie' => $q]);
        }
        if (!$eq) {
            $eq = $repo->findOneBy(['adresseIp' => $q]);
        }
        if (!$eq && ctype_digit($q)) {
            $eq = $repo->find((int) $q);
        }

        if (!$eq) {
            return $this->json([
                'success' => false,
                'error'   => sprintf('Aucun équipement GTAC2A correspondant au code scanné : « %s »', $q),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->detail($eq->getId(), $em);
    }

    /**
     * Mise à jour rapide du statut ou de l'emplacement (idéal inventaire terrain via tablette/mobile).
     */
    #[Route('/equipements/{id}/status', name: 'equipement_update_status', methods: ['POST', 'PATCH'], requirements: ['id' => '\d+'])]
    public function updateStatus(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AuditLogger $auditLogger
    ): JsonResponse {
        /** @var Equipement|null $eq */
        $eq = $em->getRepository(Equipement::class)->find($id);
        if (!$eq) {
            return $this->json(['success' => false, 'error' => 'Équipement introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $body = json_decode((string) $request->getContent(), true) ?: $request->request->all();

        $newStatut = $body['statut'] ?? null;
        $newLieuId = isset($body['lieu_id']) ? (int) $body['lieu_id'] : null;

        $changes = [];

        if ($newStatut !== null) {
            $allowed = ['En service', 'En stock', 'En panne', 'Prêté', 'Au rebut'];
            if (!in_array($newStatut, $allowed, true)) {
                return $this->json(['success' => false, 'error' => 'Statut invalide.'], Response::HTTP_BAD_REQUEST);
            }
            $changes['old_statut'] = $eq->getStatut();
            $changes['new_statut'] = $newStatut;
            $eq->setStatut($newStatut);
        }

        if ($newLieuId !== null) {
            $newLieu = $newLieuId > 0 ? $em->getRepository(Lieu::class)->find($newLieuId) : null;
            $changes['old_lieu'] = $eq->getLieu()?->getNomLieu();
            $changes['new_lieu'] = $newLieu?->getNomLieu();
            $eq->setLieu($newLieu);
        }

        $em->flush();

        $currentUser = $this->getUser()?->getUserIdentifier() ?? 'api_user';
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        $auditLogger->log(
            'API_EQUIPEMENT_STATUS_UPDATE',
            'INFO',
            'SUCCESS',
            $currentUser,
            $clientIp,
            'Equipement',
            (string) $eq->getId(),
            sprintf('Mise à jour rapide via API REST pour l\'équipement #%d (%s).', $eq->getId(), $eq->getNom()),
            $changes
        );

        return $this->json([
            'success' => true,
            'message' => 'Équipement mis à jour avec succès.',
            'data'    => $this->serializeEquipementSummary($eq),
        ]);
    }

    /**
     * Indicateurs clés globaux (KPIs) en JSON pour dashboard externe ou mobile.
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(EntityManagerInterface $em): JsonResponse
    {
        $eqRepo = $em->getRepository(Equipement::class);
        $totalEq = (int) $eqRepo->count([]);
        $enService = (int) $eqRepo->count(['statut' => 'En service']);
        $enStock = (int) $eqRepo->count(['statut' => 'En stock']);
        $enPanne = (int) $eqRepo->count(['statut' => 'En panne']);

        $totalValeur = (float) ($eqRepo->createQueryBuilder('e')
            ->select('SUM(e.prixAchat)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        return $this->json([
            'success' => true,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'stats' => [
                'total_equipements' => $totalEq,
                'en_service'        => $enService,
                'en_stock'          => $enStock,
                'en_panne'          => $enPanne,
                'valeur_totale_fcfa'=> $totalValeur,
                'taux_disponibilite'=> $totalEq > 0 ? round(($enService / $totalEq) * 100, 1) : 0,
            ],
        ]);
    }

    /**
     * Normalisation standardisée d'un équipement.
     *
     * @return array<string, mixed>
     */
    private function serializeEquipementSummary(Equipement $eq): array
    {
        return [
            'id'                   => $eq->getId(),
            'nom'                  => $eq->getNom(),
            'marque'               => $eq->getMarque(),
            'modele'               => $eq->getModele(),
            'numeroSerie'          => $eq->getNumeroSerie(),
            'tagInventaire'        => $eq->getTagInventaire(),
            'adresseIp'            => $eq->getAdresseIp(),
            'systemeExploitation'  => $eq->getSystemeExploitation(),
            'statut'               => $eq->getStatut(),
            'categorie'            => $eq->getCategorie() ? [
                'id'  => $eq->getCategorie()->getId(),
                'nom' => $eq->getCategorie()->getNomCat(),
            ] : null,
            'lieu'                 => $eq->getLieu() ? [
                'id'  => $eq->getLieu()->getId(),
                'nom' => $eq->getLieu()->getNomLieu(),
            ] : null,
            'prixAchat'            => $eq->getPrixAchat(),
            'dateAcquisition'      => $eq->getDateAcquisition()?->format('Y-m-d'),
            'finGarantie'          => $eq->getFinGarantie()?->format('Y-m-d'),
            'ageAnnees'            => $eq->getAgeAnnees(),
            'isObsolete'           => $eq->isObsolete(),
            'isVieillissant'       => $eq->isVieillissant(),
            'dateAjout'            => $eq->getDateAjout()?->format('Y-m-d H:i:s'),
        ];
    }
}

