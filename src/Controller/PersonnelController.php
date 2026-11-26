<?php

namespace App\Controller;

use App\Entity\Personnel;
use App\Form\PersonnelType;
use App\Repository\PersonnelRepository;
use App\Service\AuditLogger;
use App\Service\MatriculeGenerator;
use App\Service\PdfExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/personnel')]
#[IsGranted('ROLE_USER')]
class PersonnelController extends AbstractController
{
    #[Route('', name: 'app_personnel_index', methods: ['GET'])]
    public function index(Request $request, PersonnelRepository $personnelRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $currentType = trim((string) $request->query->get('type', 'all'));
        $currentService = trim((string) $request->query->get('service', 'all'));
        $currentStatut = trim((string) $request->query->get('statut', 'all'));

        $personnels = $personnelRepository->findByFilters(
            $search ?: null,
            $currentType,
            $currentService,
            $currentStatut
        );

        $stats = $personnelRepository->countByType();
        $services = $personnelRepository->findDistinctServices();

        return $this->render('personnel/index.html.twig', [
            'personnels' => $personnels,
            'stats' => $stats,
            'services' => $services,
            'current_search' => $search,
            'current_type' => $currentType,
            'current_service' => $currentService,
            'current_statut' => $currentStatut,
            'types' => Personnel::TYPES,
        ]);
    }

    #[Route('/new', name: 'app_personnel_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        MatriculeGenerator $matriculeGenerator,
        PersonnelRepository $personnelRepository,
        AuditLogger $auditLogger
    ): Response {
        $personnel = new Personnel();

        // Pré-générer un matricule par défaut
        $defaultMatricule = $matriculeGenerator->generateMatricule(null);
        $personnel->setMatricule($defaultMatricule);

        $form = $this->createForm(PersonnelType::class, $personnel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si le matricule est vide, le générer d'après le service
            if (!$personnel->getMatricule()) {
                $personnel->setMatricule($matriculeGenerator->generateMatricule($personnel->getService()));
            }

            $em->persist($personnel);
            $em->flush();

            $username = $this->getUser()?->getUserIdentifier() ?? 'Système';
            $ipAddress = $request->getClientIp() ?? '—';

            $auditLogger->logCreation(
                entityType: 'Personnel',
                entityId: (string) $personnel->getId(),
                entityLabel: sprintf('[%s] %s (%s)', $personnel->getMatricule(), $personnel->getNom(), $personnel->getType()),
                username: $username,
                ipAddress: $ipAddress,
                details: [
                    'matricule' => $personnel->getMatricule(),
                    'nom' => $personnel->getNom(),
                    'type' => $personnel->getType(),
                    'service' => $personnel->getService(),
                ]
            );

            $this->addFlash('success', sprintf('Personnel « %s » (%s) enregistré avec succès.', $personnel->getNom(), $personnel->getMatricule()));

            return $this->redirectToRoute('app_personnel_index');
        }

        return $this->render('personnel/form.html.twig', [
            'form' => $form->createView(),
            'personnel' => $personnel,
            'title' => 'Ajouter un Personnel',
            'services' => $personnelRepository->findDistinctServices(),
        ]);
    }

    #[Route('/{id}/show', name: 'app_personnel_show', methods: ['GET'])]
    public function show(Personnel $personnel): Response
    {
        // Séparer attributions actives / historiques
        $attributionsActives = [];
        $attributionsHistorique = [];

        foreach ($personnel->getAttributions() as $att) {
            if ($att->getStatut() === 'En cours') {
                $attributionsActives[] = $att;
            } else {
                $attributionsHistorique[] = $att;
            }
        }

        // Trier l'historique par date décroissante
        usort($attributionsHistorique, static function ($a, $b) {
            return $b->getDateDebut() <=> $a->getDateDebut();
        });

        return $this->render('personnel/show.html.twig', [
            'personnel'              => $personnel,
            'attributions_actives'   => $attributionsActives,
            'attributions_historique' => $attributionsHistorique,
        ]);
    }

    /**
     * Exporte la fiche complète du personnel au format PDF institutionnel.
     */
    #[Route('/{id}/pdf', name: 'app_personnel_pdf', methods: ['GET'])]
    public function exportPdf(
        Personnel $personnel,
        PdfExportService $pdfService
    ): Response {
        $attributionsActives = [];
        $attributionsHistorique = [];

        foreach ($personnel->getAttributions() as $att) {
            if ($att->getStatut() === 'En cours') {
                $attributionsActives[] = $att;
            } else {
                $attributionsHistorique[] = $att;
            }
        }

        usort($attributionsHistorique, static function ($a, $b) {
            return $b->getDateDebut() <=> $a->getDateDebut();
        });

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $personnel->getNom());
        $filename = sprintf('Fiche_Personnel_%s_%s.pdf', $safeName, date('Ymd'));

        return $pdfService->generatePdfResponse(
            template: 'pdf/personnel.html.twig',
            variables: [
                'personnel'              => $personnel,
                'attributions_actives'   => $attributionsActives,
                'attributions_historique' => $attributionsHistorique,
            ],
            filename: $filename,
        );
    }

    #[Route('/{id}/edit', name: 'app_personnel_edit', methods: ['GET', 'POST'])]
    public function edit(
        Personnel $personnel,
        Request $request,
        EntityManagerInterface $em,
        PersonnelRepository $personnelRepository,
        AuditLogger $auditLogger
    ): Response {
        $oldType = $personnel->getType();
        $oldService = $personnel->getService();

        $form = $this->createForm(PersonnelType::class, $personnel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $username = $this->getUser()?->getUserIdentifier() ?? 'Système';
            $ipAddress = $request->getClientIp() ?? '—';

            $auditLogger->logModification(
                entityType: 'Personnel',
                entityId: (string) $personnel->getId(),
                entityLabel: sprintf('[%s] %s', $personnel->getMatricule(), $personnel->getNom()),
                username: $username,
                ipAddress: $ipAddress,
                changes: [
                    'matricule' => $personnel->getMatricule(),
                    'type' => ['old' => $oldType, 'new' => $personnel->getType()],
                    'service' => ['old' => $oldService, 'new' => $personnel->getService()],
                ]
            );

            $this->addFlash('success', sprintf('Fiche de « %s » mise à jour avec succès.', $personnel->getNom()));

            return $this->redirectToRoute('app_personnel_index');
        }

        return $this->render('personnel/form.html.twig', [
            'form' => $form->createView(),
            'personnel' => $personnel,
            'title' => 'Modifier le Personnel',
            'services' => $personnelRepository->findDistinctServices(),
        ]);
    }

    #[Route('/{id}', name: 'app_personnel_delete', methods: ['POST'])]
    public function delete(
        Personnel $personnel,
        Request $request,
        EntityManagerInterface $em,
        AuditLogger $auditLogger
    ): Response {
        $token = (string) $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_personnel_' . $personnel->getId(), $token)) {
            $nom = $personnel->getNom();
            $mat = $personnel->getMatricule();

            $em->remove($personnel);
            $em->flush();

            $auditLogger->log(
                action: 'PERSONNEL_SUPPRESSION',
                level: 'WARNING',
                description: sprintf('Suppression de la fiche personnel [%s] %s', $mat, $nom),
                entityType: 'Personnel'
            );

            $this->addFlash('success', sprintf('Personnel « %s » supprimé.', $nom));
        }

        return $this->redirectToRoute('app_personnel_index');
    }

    /**
     * Action groupée sur les personnels sélectionnés
     */
    #[Route('/bulk-action', name: 'app_personnel_bulk_action', methods: ['POST'])]
    public function bulkAction(
        Request $request,
        PersonnelRepository $personnelRepository,
        EntityManagerInterface $em,
        MatriculeGenerator $matriculeGenerator,
        AuditLogger $auditLogger
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('personnel_bulk_action', $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_personnel_index');
        }

        $rawIds = $request->request->all('ids');
        $action = (string) $request->request->get('bulk_action', 'modify');

        // Récupérer les identifiants
        $ids = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $id) {
                if (is_numeric($id)) {
                    $ids[] = (int) $id;
                }
            }
        }

        if (empty($ids)) {
            $this->addFlash('warning', 'Aucun personnel sélectionné.');
            return $this->redirectToRoute('app_personnel_index');
        }

        $selectedPersonnels = $personnelRepository->findBy(['id' => $ids]);
        $count = count($selectedPersonnels);

        if ($action === 'delete') {
            if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
                $this->addFlash('error', 'Action réservée aux administrateurs DSI.');
                return $this->redirectToRoute('app_personnel_index');
            }

            foreach ($selectedPersonnels as $p) {
                $em->remove($p);
            }
            $em->flush();

            $auditLogger->log(
                action: 'PERSONNEL_BULK_DELETE',
                level: 'WARNING',
                description: sprintf('Suppression groupée de %d fiche(s) personnel', $count)
            );

            $this->addFlash('success', sprintf('%d personnel(s) supprimé(s) avec succès.', $count));
            return $this->redirectToRoute('app_personnel_index');
        }

        // Action de modification sélective
        $modifyType = $request->request->getBoolean('modify_type');
        $newType = trim((string) $request->request->get('new_type', ''));

        $modifyService = $request->request->getBoolean('modify_service');
        $newService = trim((string) $request->request->get('new_service', ''));

        $modifyStatut = $request->request->getBoolean('modify_statut');
        $newStatut = trim((string) $request->request->get('new_statut', ''));

        $modifyNotes = $request->request->getBoolean('modify_notes');
        $newNotes = trim((string) $request->request->get('new_notes', ''));

        $appliedFields = [];

        foreach ($selectedPersonnels as $p) {
            if ($modifyType && in_array($newType, Personnel::TYPES, true)) {
                $p->setType($newType);
                $appliedFields['type'] = $newType;
            }

            if ($modifyService) {
                $p->setService($newService !== '' ? $newService : null);
                $appliedFields['service'] = $newService !== '' ? $newService : '(vide)';
            }

            if ($modifyStatut && in_array($newStatut, ['Actif', 'Inactif', 'Suspendu'], true)) {
                $p->setStatut($newStatut);
                $appliedFields['statut'] = $newStatut;
            }

            if ($modifyNotes) {
                if ($newNotes !== '') {
                    $existing = $p->getNotes();
                    $p->setNotes($existing ? $existing . "\n" . $newNotes : $newNotes);
                }
                $appliedFields['notes'] = 'Notes mises à jour';
            }
        }

        if (empty($appliedFields)) {
            $this->addFlash('warning', 'Aucun champ n\'a été activé pour la modification groupée.');
            return $this->redirectToRoute('app_personnel_index');
        }

        $em->flush();

        $auditLogger->log(
            action: 'PERSONNEL_BULK_UPDATE',
            level: 'INFO',
            description: sprintf('Modification groupée appliquée sur %d personnel(s)', $count),
            details: [
                'champs_modifiés' => $appliedFields,
                'personnels_count' => $count,
            ]
        );

        $this->addFlash('success', sprintf(
            'Modification groupée effectuée avec succès sur %d personnel(s) (%s).',
            $count,
            implode(', ', array_keys($appliedFields))
        ));

        return $this->redirectToRoute('app_personnel_index');
    }

    /**
     * API JSON : Génération dynamique de matricule lors de la sélection d'un service
     */
    #[Route('/api/next-matricule', name: 'api_personnel_next_matricule', methods: ['GET'])]
    public function apiNextMatricule(Request $request, MatriculeGenerator $matriculeGenerator): JsonResponse
    {
        $service = $request->query->get('service');
        $matricule = $matriculeGenerator->generateMatricule($service);

        return new JsonResponse([
            'prefix' => $matriculeGenerator->getServicePrefix($service),
            'matricule' => $matricule,
        ]);
    }
}

