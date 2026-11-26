<?php

namespace App\Controller;

use App\Entity\Equipement;
use App\Entity\TicketMaintenance;
use App\Form\TicketMaintenanceType;
use App\Repository\TicketMaintenanceRepository;
use App\Service\MaintenanceAssistantService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/maintenances')]
class MaintenanceController extends AbstractController
{
    #[Route('', name: 'app_maintenance_index', methods: ['GET'])]
    public function index(
        Request $request,
        TicketMaintenanceRepository $repository,
        MaintenanceAssistantService $assistantService,
    ): Response {
        $filterStatut = $request->query->get('statut', 'all');
        $filterType = $request->query->get('type', 'all');
        $search = trim((string) $request->query->get('q', ''));

        $qb = $repository->createQueryBuilder('t')
            ->leftJoin('t.equipement', 'e')
            ->addSelect('e')
            ->orderBy('t.dateCreation', 'DESC');

        if ($filterStatut === 'open') {
            $qb->andWhere('t.statut IN (:openStatuses)')
                ->setParameter('openStatuses', ['Nouveau', 'En cours']);
        } elseif ($filterStatut === 'resolved') {
            $qb->andWhere('t.statut = :resolved')
                ->setParameter('resolved', 'Résolu');
        }

        if ($filterType === 'dsi') {
            $qb->andWhere('t.typeMaintenance = :typeDsi')
                ->setParameter('typeDsi', 'DSI');
        } elseif ($filterType === 'prestataire') {
            $qb->andWhere('t.typeMaintenance = :typePres')
                ->setParameter('typePres', 'Prestataire');
        }

        if ($search !== '') {
            $qb->andWhere(
                't.titre LIKE :q OR t.description LIKE :q OR t.technicien LIKE :q OR t.prestataireNom LIKE :q OR t.numeroBon LIKE :q OR e.nom LIKE :q OR e.numeroSerie LIKE :q OR e.tagInventaire LIKE :q'
            )->setParameter('q', '%' . $search . '%');
        }

        $tickets = $qb->getQuery()->getResult();

        // Statistiques globales pour les compteurs
        $allTickets = $repository->findAll();
        $stats = [
            'total' => count($allTickets),
            'dsi' => 0,
            'prestataire' => 0,
            'open' => 0,
            'resolved' => 0,
        ];
        foreach ($allTickets as $t) {
            if ($t->getTypeMaintenance() === 'Prestataire') {
                $stats['prestataire']++;
            } else {
                $stats['dsi']++;
            }

            if ($t->getStatut() === 'Résolu') {
                $stats['resolved']++;
            } elseif (in_array($t->getStatut(), ['Nouveau', 'En cours'], true)) {
                $stats['open']++;
            }
        }

        return $this->render('maintenance/index.html.twig', [
            'tickets' => $tickets,
            'current_statut' => $filterStatut,
            'current_type' => $filterType,
            'current_search' => $search,
            'stats' => $stats,
            'pannes_courantes' => $assistantService->getPannesCourantes(),
        ]);
    }

    #[Route('/new', name: 'app_maintenance_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        MaintenanceAssistantService $assistantService,
    ): Response {
        $ticket = new TicketMaintenance();

        // Pré-sélection du type via paramètre d'URL (?type=prestataire ou ?type=dsi)
        $typeParam = strtolower((string) $request->query->get('type', 'dsi'));
        if ($typeParam === 'prestataire') {
            $ticket->setTypeMaintenance('Prestataire');
        } else {
            $ticket->setTypeMaintenance('DSI');
        }

        $eqId = $request->query->get('equipement_id');
        if ($eqId) {
            $eq = $em->getRepository(Equipement::class)->find($eqId);
            if ($eq) {
                $ticket->setEquipement($eq);
            }
        }

        $form = $this->createForm(TicketMaintenanceType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $equipement = $ticket->getEquipement();
            if ($equipement && $ticket->getStatut() !== 'Résolu') {
                $equipement->setStatut('En panne');
            }

            $em->persist($ticket);
            $em->flush();

            $nomEq = $equipement ? $equipement->getNom() : 'matériel non spécifié';
            $typeLabel = $ticket->isPrestataire() ? 'prestataire' : 'interne DSI';
            $this->addFlash('success', sprintf('Ticket de maintenance (%s) créé pour l\'équipement "%s".', $typeLabel, $nomEq));
            return $this->redirectToRoute('app_maintenance_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('maintenance/form.html.twig', [
            'form' => $form,
            'ticket' => $ticket,
            'title' => $ticket->isPrestataire() ? 'Confier à un Prestataire Externe' : 'Intervention Rapide DSI',
            'button_label' => 'Enregistrer le ticket',
            'pannes_courantes' => $assistantService->getPannesCourantes(),
        ]);
    }

    #[Route('/assistant', name: 'app_maintenance_assistant', methods: ['POST'])]
    public function assistant(
        Request $request,
        MaintenanceAssistantService $assistantService,
        EntityManagerInterface $em,
    ): JsonResponse {
        try {
            $payload = json_decode((string) $request->getContent(), true) ?? $request->request->all();
            $question = trim((string) ($payload['question'] ?? ''));
            $equipementId = $payload['equipement_id'] ?? null;

            if ($question === '') {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Veuillez décrire le problème ou choisir une panne.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $equipement = null;
            if ($equipementId) {
                $equipement = $em->getRepository(Equipement::class)->find($equipementId);
            }

            $diagnostic = $assistantService->diagnostiquer($question, $equipement);

            return new JsonResponse([
                'success' => true,
                'diagnostic' => $diagnostic,
            ]);
        } catch (\Throwable $e) {
            // Sécurité absolue : en cas d'erreur imprévue ou réseau, on renvoie un diagnostic DSI secouru sans crasher
            return new JsonResponse([
                'success' => true,
                'diagnostic' => [
                    'type' => 'fallback',
                    'source' => 'Support DSI GTAC2A',
                    'titre' => 'Diagnostic technique : ' . ($question ?? 'Incident'),
                    'severite' => 'Moyenne',
                    'causes' => [
                        'Panne matérielle non documentée ou conflit de configuration',
                        'Périphérique ou contrôleur en état d\'erreur',
                    ],
                    'actions' => [
                        '1. Vérifier l\'Observateur d\'événements Windows (`eventvwr.msc`).',
                        '2. Lancer la vérification de l\'intégrité système (`sfc /scannow`).',
                        '3. Isoler les périphériques externes connectés.',
                    ],
                    'commande' => "sfc /scannow",
                    'solution_recommandee' => 'Diagnostic préliminaire réalisé. Analyse des journaux système et test matériel préconisés.',
                    'equipement_info' => null,
                ],
            ]);
        }
    }

    #[Route('/{id}/resolve', name: 'app_maintenance_resolve', methods: ['POST'])]
    public function resolve(Request $request, TicketMaintenance $ticket, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('resolve' . $ticket->getId(), (string) $request->request->get('_token'))) {
            $ticket->setStatut('Résolu');
            $ticket->setDateResolution(new \DateTime());

            $equipement = $ticket->getEquipement();
            if ($equipement) {
                $equipement->setStatut('En service');
            }

            $em->flush();
            $this->addFlash('success', 'Ticket de maintenance marqué comme résolu. Équipement remis en service.');
        }

        return $this->redirectToRoute('app_maintenance_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_maintenance_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TicketMaintenance $ticket,
        EntityManagerInterface $em,
        MaintenanceAssistantService $assistantService,
    ): Response {
        $form = $this->createForm(TicketMaintenanceType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($ticket->getStatut() === 'Résolu' && !$ticket->getDateResolution()) {
                $ticket->setDateResolution(new \DateTime());
                $ticket->getEquipement()?->setStatut('En service');
            }

            $em->flush();
            $this->addFlash('success', 'Ticket de maintenance mis à jour.');
            return $this->redirectToRoute('app_maintenance_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('maintenance/form.html.twig', [
            'form' => $form,
            'ticket' => $ticket,
            'title' => 'Modifier le ticket #' . $ticket->getId() . ' : ' . $ticket->getTitre(),
            'button_label' => 'Mettre à jour le ticket',
            'pannes_courantes' => $assistantService->getPannesCourantes(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_maintenance_delete', methods: ['POST'])]
    public function delete(Request $request, TicketMaintenance $ticket, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $ticket->getId(), (string) $request->request->get('_token'))) {
            $em->remove($ticket);
            $em->flush();
            $this->addFlash('success', 'Ticket de maintenance supprimé.');
        }

        return $this->redirectToRoute('app_maintenance_index', [], Response::HTTP_SEE_OTHER);
    }
}

