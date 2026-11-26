<?php

namespace App\Controller;

use App\Entity\ChecklistEtape;
use App\Entity\ChecklistPreparation;
use App\Repository\ChecklistEtapeRepository;
use App\Repository\ChecklistPreparationRepository;
use App\Repository\EquipementRepository;
use App\Service\ChecklistService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/equipements/{id}/checklist', name: 'app_checklist_')]
#[IsGranted('ROLE_USER')]
class ChecklistController extends AbstractController
{
    public function __construct(
        private readonly ChecklistService $checklistService,
        private readonly ChecklistPreparationRepository $preparationRepo,
        private readonly ChecklistEtapeRepository $etapeRepo,
        private readonly EquipementRepository $equipementRepo,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    /**
     * Démarre ou reprend une session de préparation.
     * Retourne le HTML du modal wizard (rendu partiel Twig).
     */
    #[Route('/demarrer', name: 'demarrer', methods: ['POST'])]
    public function demarrer(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$this->isCsrfTokenValid('checklist_demarrer_' . $id, $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $equipement = $this->equipementRepo->find($id);
        if (!$equipement) {
            return new JsonResponse(['error' => 'Équipement introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (!$equipement->isEligiblePreparation()) {
            return new JsonResponse(['error' => 'Cet équipement n\'est pas un ordinateur éligible à la procédure de préparation.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $preparation = $this->checklistService->demarrer($equipement, $user);

        return $this->render('checklist/_wizard.html.twig', [
            'preparation' => $preparation,
            'equipement'  => $equipement,
        ]);
    }

    /**
     * Retourne l'état courant du wizard (rafraîchissement AJAX).
     */
    #[Route('/{preparationId}', name: 'show', methods: ['GET'])]
    public function show(int $id, int $preparationId): Response
    {
        $equipement  = $this->equipementRepo->find($id);
        $preparation = $this->preparationRepo->find($preparationId);

        if (!$equipement || !$preparation || $preparation->getEquipement()?->getId() !== $equipement->getId()) {
            return new JsonResponse(['error' => 'Introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->render('checklist/_wizard.html.twig', [
            'preparation' => $preparation,
            'equipement'  => $equipement,
        ]);
    }

    /**
     * Valide ou marque en erreur une étape (AJAX POST).
     */
    #[Route('/{preparationId}/etape/{etapeId}/valider', name: 'valider_etape', methods: ['POST'])]
    public function validerEtape(
        int $id,
        int $preparationId,
        int $etapeId,
        Request $request
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('checklist_etape_' . $etapeId, $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $equipement  = $this->equipementRepo->find($id);
        $preparation = $this->preparationRepo->find($preparationId);
        $etape       = $this->etapeRepo->find($etapeId);

        if (!$equipement || !$preparation || !$etape
            || $preparation->getEquipement()?->getId() !== $equipement->getId()
            || $etape->getPreparation()?->getId() !== $preparation->getId()) {
            return new JsonResponse(['error' => 'Introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($preparation->isTerminee()) {
            return new JsonResponse(['error' => 'Cette préparation est déjà terminée.'], Response::HTTP_CONFLICT);
        }

        $statut       = $request->request->get('statut', ChecklistEtape::STATUT_OK);
        $observations = $request->request->get('observations');

        if (!in_array($statut, [ChecklistEtape::STATUT_OK, ChecklistEtape::STATUT_KO, ChecklistEtape::STATUT_EN_ATTENTE], true)) {
            return new JsonResponse(['error' => 'Statut invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $toutesValidees = $this->checklistService->validerEtape($etape, $statut, $observations);

        return new JsonResponse([
            'success'         => true,
            'etapeId'         => $etape->getId(),
            'etapeStatut'     => $etape->getStatut(),
            'progression'     => $preparation->getTauxProgression(),
            'nbOk'            => $preparation->getNbEtapesOk(),
            'nbKo'            => $preparation->getNbEtapesKo(),
            'nbTotal'         => $preparation->getNbEtapesTotal(),
            'checklistTerminee' => $toutesValidees,
            'preparationStatut' => $preparation->getStatut(),
        ]);
    }

    /**
     * Ajoute un disque supplémentaire à sauvegarder (AJAX POST, type service uniquement).
     */
    #[Route('/{preparationId}/ajouter-disque', name: 'ajouter_disque', methods: ['POST'])]
    public function ajouterDisque(int $id, int $preparationId, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('checklist_disque_' . $preparationId, $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $equipement  = $this->equipementRepo->find($id);
        $preparation = $this->preparationRepo->find($preparationId);

        if (!$equipement || !$preparation || $preparation->getEquipement()?->getId() !== $equipement->getId()) {
            return new JsonResponse(['error' => 'Introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($preparation->getType() !== ChecklistPreparation::TYPE_SERVICE) {
            return new JsonResponse(['error' => 'Uniquement disponible pour les machines en service.'], Response::HTTP_BAD_REQUEST);
        }

        $libelleDisque = trim($request->request->get('libelle_disque', ''));
        if ($libelleDisque === '') {
            return new JsonResponse(['error' => 'Le libellé du disque est requis.'], Response::HTTP_BAD_REQUEST);
        }

        $nouvelleEtape = $this->checklistService->ajouterSauvegardeSupplementaire($preparation, $libelleDisque);

        return new JsonResponse([
            'success'   => true,
            'etapeId'   => $nouvelleEtape->getId(),
            'etapeLibelle' => $nouvelleEtape->getLibelle(),
            'etapeCle'  => $nouvelleEtape->getCle(),
            'csrfToken' => $this->csrfTokenManager
                               ->getToken('checklist_etape_' . $nouvelleEtape->getId())->getValue(),
        ]);
    }

    /**
     * Clôture ou abandonne manuellement la préparation.
     */
    #[Route('/{preparationId}/cloturer', name: 'cloturer', methods: ['POST'])]
    public function cloturer(int $id, int $preparationId, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('checklist_cloturer_' . $preparationId, $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $equipement  = $this->equipementRepo->find($id);
        $preparation = $this->preparationRepo->find($preparationId);

        if (!$equipement || !$preparation || $preparation->getEquipement()?->getId() !== $equipement->getId()) {
            return new JsonResponse(['error' => 'Introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $statut = $request->request->get('statut', ChecklistPreparation::STATUT_TERMINEE);
        $observations = $request->request->get('observations');

        $this->checklistService->cloturer($preparation, $statut, $observations);

        return new JsonResponse(['success' => true, 'statut' => $preparation->getStatut()]);
    }

    /**
     * Supprime une session de préparation depuis l'historique.
     */
    #[Route('/{preparationId}/supprimer', name: 'supprimer', methods: ['POST'])]
    public function supprimer(int $id, int $preparationId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('checklist_supprimer_' . $preparationId, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité CSRF invalide.');
            return $this->redirectToRoute('app_equipement_show', ['id' => $id]);
        }

        $equipement  = $this->equipementRepo->find($id);
        $preparation = $this->preparationRepo->find($preparationId);

        if (!$equipement || !$preparation || $preparation->getEquipement()?->getId() !== $equipement->getId()) {
            $this->addFlash('danger', 'Préparation introuvable.');
            return $this->redirectToRoute('app_equipement_show', ['id' => $id]);
        }

        $this->checklistService->supprimer($preparation);
        $this->addFlash('success', 'Session de préparation supprimée avec succès de l\'historique.');

        return $this->redirectToRoute('app_equipement_show', ['id' => $id]);
    }
}

