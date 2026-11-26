<?php

namespace App\Controller;

use App\Entity\Attribution;
use App\Entity\CategorieEquipement;
use App\Entity\Composant;
use App\Entity\Equipement;
use App\Entity\Lieu;
use App\Entity\Personnel;
use App\Entity\TicketMaintenance;
use App\Form\EquipementType;
use App\Repository\ChecklistPreparationRepository;
use App\Repository\EquipementRepository;
use App\Service\AuditLogger;
use App\Service\PdfExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/equipements')]
class EquipementController extends AbstractController
{
    #[Route('', name: 'app_equipement_index', methods: ['GET'])]
    public function index(Request $request, EquipementRepository $repository, EntityManagerInterface $em): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $catId  = $request->query->get('categorie');
        $statut = $request->query->get('statut');
        $lieuId = $request->query->get('lieu');

        $currentLieu = !empty($lieuId) ? $em->getRepository(Lieu::class)->find($lieuId) : null;
        $equipements = $repository->findByFilters(
            $search !== '' ? $search : null,
            $catId ?: null,
            $statut ?: null,
            $currentLieu?->getId()
        );

        $categories = $em->getRepository(CategorieEquipement::class)->findBy([], ['nom_cat' => 'ASC']);

        // Carte des attributions actives pour afficher immédiatement le bénéficiaire de chaque machine
        /** @var Attribution[] $activeAttributions */
        $activeAttributions = $em->getRepository(Attribution::class)->findBy([
            'statut' => ['En cours', 'Prolongé']
        ]);
        $attributionMap = [];
        foreach ($activeAttributions as $att) {
            if ($att->getEquipement()) {
                $attributionMap[$att->getEquipement()->getId()] = $att;
            }
        }

        return $this->render('equipement/index.html.twig', [
            'equipements'     => $equipements,
            'categories'      => $categories,
            'attribution_map' => $attributionMap,
            'current_search'  => $search,
            'current_cat'     => $catId,
            'current_statut'  => $statut,
            'current_lieu'    => $currentLieu,
            'all_lieux'       => $em->getRepository(Lieu::class)->findBy([], ['nom_lieu' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_equipement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $equipement = new Equipement();
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($equipement);
            $em->flush();

            $this->addFlash('success', 'Équipement "' . $equipement->getNom() . '" ajouté avec succès au parc.');
            return $this->redirectToRoute(
                'app_equipement_show',
                ['id' => $equipement->getId(), 'attribuer' => 1],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->render('equipement/form.html.twig', [
            'form' => $form,
            'title' => 'Ajouter un Équipement au Parc',
            'button_label' => 'Enregistrer l\'équipement',
        ]);
    }

    #[Route('/{id}', name: 'app_equipement_show', methods: ['GET'])]
    public function show(
        Equipement $equipement,
        EntityManagerInterface $em,
        \App\Service\AttributionManager $attributionManager,
        ChecklistPreparationRepository $checklistRepo,
    ): Response
    {
        $attributions = $em->getRepository(Attribution::class)->findBy(['equipement' => $equipement], ['dateDebut' => 'DESC']);
        $tickets = $em->getRepository(TicketMaintenance::class)->findBy(['equipement' => $equipement], ['dateCreation' => 'DESC']);
        $composants = $em->getRepository(Composant::class)->findBy(['equipement' => $equipement], ['nom' => 'ASC']);
        $personnels = $em->getRepository(Personnel::class)->findBy(['statut' => 'Actif'], ['type' => 'ASC', 'nom' => 'ASC']);

        // Préparer un mapping des équipements actifs de chaque personnel pour le contrôle temps réel en modale
        $personnelActiveDevices = [];
        foreach ($personnels as $p) {
            $devices = [];
            foreach ($p->getAttributions() as $att) {
                if ($att->getStatut() === 'En cours' && $att->getEquipement()) {
                    $eq = $att->getEquipement();
                    $devices[] = [
                        'id' => $eq->getId(),
                        'nom' => $eq->getNom(),
                        'sn' => $eq->getNumeroSerie() ?? $eq->getTagInventaire() ?? 'S/N non renseigné',
                        'catNom' => $eq->getCategorie()?->getNomCat() ?? 'Équipement',
                        'family' => $attributionManager->getCategoryFamily($eq->getCategorie()),
                        'attributionId' => $att->getId(),
                    ];
                }
            }
            $personnelActiveDevices[$p->getId()] = $devices;
        }

        // Historique des checklists de préparation
        $preparations = $checklistRepo->findByEquipement($equipement);

        return $this->render('equipement/show.html.twig', [
            'equipement'              => $equipement,
            'equipement_family'       => $attributionManager->getCategoryFamily($equipement->getCategorie()),
            'attributions'            => $attributions,
            'tickets'                 => $tickets,
            'composants'              => $composants,
            'personnels'              => $personnels,
            'personnel_active_devices' => $personnelActiveDevices,
            'preparations'            => $preparations,
        ]);
    }

    /**
     * Traitement du formulaire d'attribution rapide depuis la fiche équipement (modale).
     */
    #[Route('/{id}/attribuer-rapide', name: 'app_equipement_attribuer_rapide', methods: ['POST'])]
    public function attribuerRapide(
        Request $request,
        Equipement $equipement,
        EntityManagerInterface $em,
        \App\Service\AttributionManager $attributionManager
    ): Response {
        if (!$this->isCsrfTokenValid('attribuer_rapide_' . $equipement->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_equipement_show', ['id' => $equipement->getId()]);
        }

        $personnelId = $request->request->get('personnel_id');
        $personnel = null;
        if (!empty($personnelId) && is_numeric($personnelId)) {
            $personnel = $em->getRepository(Personnel::class)->find((int) $personnelId);
        }

        $beneficiaireNom     = trim((string) $request->request->get('beneficiaireNom'));
        $beneficiaireService = trim((string) $request->request->get('beneficiaireService'));
        $motif               = trim((string) $request->request->get('motif'));
        $dateDebutStr        = (string) $request->request->get('dateDebut');
        $dateFinStr          = (string) $request->request->get('dateFin');

        if ($personnel) {
            if ($beneficiaireNom === '') {
                $beneficiaireNom = $personnel->getNom();
            }
            if ($beneficiaireService === '' && $personnel->getService()) {
                $beneficiaireService = $personnel->getService();
            }
        }

        if ($beneficiaireNom === '') {
            $this->addFlash('danger', 'Veuillez sélectionner un personnel ou renseigner le nom du bénéficiaire.');
            return $this->redirectToRoute('app_equipement_show', ['id' => $equipement->getId()]);
        }

        $operatorUsername = $this->getUser()?->getUserIdentifier() ?? 'system';
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        // Règle Métier : Vérifier si le collaborateur possède déjà un matériel actif dans la même catégorie individuelle
        if ($personnel) {
            $conflicting = $attributionManager->findConflictingActiveAttribution($personnel, $equipement);
            if ($conflicting) {
                $oldAction = (string) $request->request->get('old_equipment_action', 'stock');
                $reassignPersonnelId = $request->request->get('reassign_personnel_id');
                $reassignPersonnel = null;
                if ($reassignPersonnelId && is_numeric($reassignPersonnelId)) {
                    $reassignPersonnel = $em->getRepository(Personnel::class)->find((int) $reassignPersonnelId);
                }

                $res = $attributionManager->handleOldEquipmentAction(
                    $conflicting,
                    $equipement,
                    $oldAction,
                    $reassignPersonnel,
                    $operatorUsername,
                    $clientIp
                );

                $this->addFlash('info', $res['message']);
            }
        }

        $attribution = new Attribution();
        $attribution->setEquipement($equipement);
        if ($personnel) {
            $attribution->setPersonnel($personnel);
        }
        $attribution->setBeneficiaireNom($beneficiaireNom);
        $attribution->setBeneficiaireService($beneficiaireService !== '' ? $beneficiaireService : null);
        $attribution->setMotif($motif !== '' ? $motif : null);
        $attribution->setStatut('En cours');

        $dateDebut = $dateDebutStr !== ''
            ? \DateTime::createFromFormat('Y-m-d', $dateDebutStr) ?: new \DateTime()
            : new \DateTime();
        $attribution->setDateDebut($dateDebut);

        if ($dateFinStr !== '') {
            $dateFin = \DateTime::createFromFormat('Y-m-d', $dateFinStr);
            if ($dateFin) {
                $attribution->setDateFinPrevue($dateFin);
            }
        }

        // Mise à jour automatique du statut de l'équipement
        $equipement->setStatut('En service');

        $em->persist($attribution);
        $em->flush();

        $this->addFlash(
            'success',
            sprintf('Équipement attribué à « %s » avec succès.', htmlspecialchars($beneficiaireNom))
        );

        return $this->redirectToRoute('app_equipement_show', ['id' => $equipement->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_equipement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Equipement $equipement, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Fiche équipement mise à jour avec succès.');
            return $this->redirectToRoute('app_equipement_show', ['id' => $equipement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('equipement/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier la fiche : ' . $equipement->getNom(),
            'button_label' => 'Mettre à jour l\'équipement',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_equipement_delete', methods: ['POST'])]
    public function delete(Request $request, Equipement $equipement, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $equipement->getId(), (string) $request->request->get('_token'))) {
            // Dissocier proprement toutes les relations pour éviter toute corruption ou orphelins
            $attributions = $em->getRepository(Attribution::class)->findBy(['equipement' => $equipement]);
            foreach ($attributions as $attribution) {
                $attribution->setEquipement(null);
            }

            $tickets = $em->getRepository(TicketMaintenance::class)->findBy(['equipement' => $equipement]);
            foreach ($tickets as $ticket) {
                $ticket->setEquipement(null);
            }

            $composants = $em->getRepository(Composant::class)->findBy(['equipement' => $equipement]);
            foreach ($composants as $composant) {
                $composant->setEquipement(null);
            }

            $em->remove($equipement);
            $em->flush();
            $this->addFlash('success', 'Équipement supprimé du parc et ses historiques ont été sécurisés.');
        }

        return $this->redirectToRoute('app_equipement_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/bulk-action', name: 'app_equipement_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request, EntityManagerInterface $em, AuditLogger $auditLogger): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('equipement_bulk_action', $token)) {
            $this->addFlash('danger', 'Jeton de sécurité CSRF invalide.');
            return $this->redirectToRoute('app_equipement_index');
        }

        $action = (string) $request->request->get('bulk_action');
        $rawIds = $request->request->all('ids');
        $ids = array_filter(array_map('intval', (array) $rawIds));

        if (empty($ids)) {
            $this->addFlash('warning', 'Aucun équipement sélectionné pour l\'action groupée.');
            return $this->redirectToRoute('app_equipement_index');
        }

        $equipements = $em->getRepository(Equipement::class)->findBy(['id' => $ids]);
        $count = count($equipements);

        if ($count === 0) {
            $this->addFlash('warning', 'Aucun équipement correspondant trouvé.');
            return $this->redirectToRoute('app_equipement_index');
        }

        $currentUser = $this->getUser()?->getUserIdentifier() ?? 'system';
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        switch ($action) {
            case 'modify':
                $modifyStatut    = $request->request->getBoolean('modify_statut');
                $modifyLieu      = $request->request->getBoolean('modify_lieu');
                $modifyCategorie = $request->request->getBoolean('modify_categorie');
                $modifyOs        = $request->request->getBoolean('modify_os');
                $modifyNotes     = $request->request->getBoolean('modify_notes');

                if (!$modifyStatut && !$modifyLieu && !$modifyCategorie && !$modifyOs && !$modifyNotes) {
                    $this->addFlash('warning', 'Aucun champ n\'a été sélectionné pour la mise à jour groupée.');
                    return $this->redirectToRoute('app_equipement_index');
                }

                $updatedLabels = [];

                if ($modifyStatut) {
                    $newStatut = (string) $request->request->get('new_statut');
                    $allowedStatuts = ['En service', 'En stock', 'En panne', 'Prêté', 'Au rebut'];
                    if (in_array($newStatut, $allowedStatuts, true)) {
                        foreach ($equipements as $eq) {
                            $eq->setStatut($newStatut);
                        }
                        $updatedLabels[] = 'Statut (' . $newStatut . ')';
                    }
                }

                if ($modifyLieu) {
                    $lieuId = (int) $request->request->get('new_lieu_id');
                    $newLieu = $lieuId > 0 ? $em->getRepository(Lieu::class)->find($lieuId) : null;
                    foreach ($equipements as $eq) {
                        $eq->setLieu($newLieu);
                    }
                    $updatedLabels[] = 'Emplacement (' . ($newLieu ? $newLieu->getNomLieu() : 'Non assigné') . ')';
                }

                if ($modifyCategorie) {
                    $catId = (int) $request->request->get('new_cat_id');
                    $newCat = $catId > 0 ? $em->getRepository(CategorieEquipement::class)->find($catId) : null;
                    foreach ($equipements as $eq) {
                        $eq->setCategorie($newCat);
                    }
                    $updatedLabels[] = 'Catégorie (' . ($newCat ? $newCat->getNomCat() : 'Non classé') . ')';
                }

                if ($modifyOs) {
                    $newOs = trim((string) $request->request->get('new_os'));
                    $newOsVal = $newOs !== '' ? $newOs : null;
                    foreach ($equipements as $eq) {
                        $eq->setSystemeExploitation($newOsVal);
                    }
                    $updatedLabels[] = 'OS (' . ($newOsVal ?? 'Non spécifié') . ')';
                }

                if ($modifyNotes) {
                    $newNotes = trim((string) $request->request->get('new_notes'));
                    $notesMode = (string) $request->request->get('notes_mode', 'append');
                    foreach ($equipements as $eq) {
                        if ($notesMode === 'append' && !empty($eq->getNotes())) {
                            $eq->setNotes($eq->getNotes() . "\n" . $newNotes);
                        } else {
                            $eq->setNotes($newNotes);
                        }
                    }
                    $updatedLabels[] = 'Notes';
                }

                $em->flush();

                $summary = implode(', ', $updatedLabels);
                $auditLogger->log(
                    'EQUIPEMENT_BULK_UPDATE',
                    'INFO',
                    'SUCCESS',
                    $currentUser,
                    $clientIp,
                    'Equipement',
                    implode(',', $ids),
                    sprintf('Mise à jour groupée de %d équipement(s) [%s].', $count, $summary),
                    ['ids' => $ids, 'fields' => $updatedLabels]
                );

                $this->addFlash('success', sprintf('Mise à jour groupée réussie sur %d équipement(s) : %s.', $count, $summary));
                break;

            case 'status':
                $newStatut = (string) $request->request->get('new_statut');
                $allowedStatuts = ['En service', 'En stock', 'En panne', 'Prêté', 'Au rebut'];
                if (!in_array($newStatut, $allowedStatuts, true)) {
                    $this->addFlash('danger', 'Statut sélectionné invalide.');
                    return $this->redirectToRoute('app_equipement_index');
                }

                foreach ($equipements as $eq) {
                    $eq->setStatut($newStatut);
                }
                $em->flush();

                $auditLogger->log(
                    'EQUIPEMENT_BULK_STATUS',
                    'INFO',
                    'SUCCESS',
                    $currentUser,
                    $clientIp,
                    'Equipement',
                    implode(',', $ids),
                    sprintf('Mise à jour groupée du statut vers "%s" pour %d équipement(s).', $newStatut, $count),
                    ['ids' => $ids, 'new_statut' => $newStatut]
                );

                $this->addFlash('success', sprintf('Le statut de %d équipement(s) a été mis à jour vers "%s".', $count, $newStatut));
                break;

            case 'lieu':
                $lieuId = (int) $request->request->get('new_lieu_id');
                $newLieu = $lieuId > 0 ? $em->getRepository(Lieu::class)->find($lieuId) : null;

                foreach ($equipements as $eq) {
                    $eq->setLieu($newLieu);
                }
                $em->flush();

                $lieuNom = $newLieu ? $newLieu->getNomLieu() : 'Non assigné';
                $auditLogger->log(
                    'EQUIPEMENT_BULK_LOCATION',
                    'INFO',
                    'SUCCESS',
                    $currentUser,
                    $clientIp,
                    'Equipement',
                    implode(',', $ids),
                    sprintf('Déplacement groupé vers "%s" pour %d équipement(s).', $lieuNom, $count),
                    ['ids' => $ids, 'lieu' => $lieuNom]
                );

                $this->addFlash('success', sprintf('%d équipement(s) ont été affectés à l\'emplacement "%s".', $count, $lieuNom));
                break;

            case 'delete':
                if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
                    $this->addFlash('danger', 'La suppression en lot est réservée aux Super Administrateurs.');
                    return $this->redirectToRoute('app_equipement_index');
                }

                foreach ($equipements as $eq) {
                    // Dissocier les attributions
                    $attributions = $em->getRepository(Attribution::class)->findBy(['equipement' => $eq]);
                    foreach ($attributions as $a) {
                        $a->setEquipement(null);
                    }
                    // Dissocier les tickets
                    $tickets = $em->getRepository(TicketMaintenance::class)->findBy(['equipement' => $eq]);
                    foreach ($tickets as $t) {
                        $t->setEquipement(null);
                    }
                    // Dissocier les composants
                    $composants = $em->getRepository(Composant::class)->findBy(['equipement' => $eq]);
                    foreach ($composants as $c) {
                        $c->setEquipement(null);
                    }
                    $em->remove($eq);
                }
                $em->flush();

                $auditLogger->log(
                    'EQUIPEMENT_BULK_DELETE',
                    'WARNING',
                    'SUCCESS',
                    $currentUser,
                    $clientIp,
                    'Equipement',
                    implode(',', $ids),
                    sprintf('Suppression groupée de %d équipement(s) du parc.', $count),
                    ['ids' => $ids]
                );

                $this->addFlash('success', sprintf('%d équipement(s) ont été supprimés avec succès du parc.', $count));
                break;

            default:
                $this->addFlash('danger', 'Action groupée non reconnue.');
                break;
        }

        return $this->redirectToRoute('app_equipement_index');
    }

    /**
     * Exporte la fiche complète de l'équipement au format PDF institutionnel.
     */
    #[Route('/{id}/pdf', name: 'app_equipement_pdf', methods: ['GET'])]
    public function exportPdf(
        Equipement $equipement,
        EntityManagerInterface $em,
        PdfExportService $pdfService
    ): Response {
        $attributions = $em->getRepository(Attribution::class)->findBy(
            ['equipement' => $equipement],
            ['dateDebut' => 'DESC']
        );
        $tickets = $em->getRepository(TicketMaintenance::class)->findBy(
            ['equipement' => $equipement],
            ['dateCreation' => 'DESC']
        );
        $composants = $em->getRepository(Composant::class)->findBy(
            ['equipement' => $equipement],
            ['nom' => 'ASC']
        );

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $equipement->getNom());
        $filename = sprintf('Fiche_Equipement_%s_%s.pdf', $safeName, date('Ymd'));

        return $pdfService->generatePdfResponse(
            template: 'pdf/equipement.html.twig',
            variables: [
                'equipement'   => $equipement,
                'attributions' => $attributions,
                'tickets'      => $tickets,
                'composants'   => $composants,
            ],
            filename: $filename,
        );
    }
}
