<?php

namespace App\Controller;

use App\Entity\CampagneInventaire;
use App\Entity\Lieu;
use App\Entity\LigneInventaire;
use App\Repository\CampagneInventaireRepository;
use App\Repository\LieuRepository;
use App\Repository\LigneInventaireRepository;
use App\Service\InventaireManager;
use App\Service\PdfExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventaire')]
#[IsGranted('ROLE_USER')]
class InventaireController extends AbstractController
{
    #[Route('', name: 'app_inventaire_index', methods: ['GET'])]
    public function index(CampagneInventaireRepository $repository): Response
    {
        $campagnes = $repository->findAllOrdered();
        $campagneActive = $repository->findActiveCampagne();

        return $this->render('inventaire/index.html.twig', [
            'campagnes' => $campagnes,
            'campagne_active' => $campagneActive,
        ]);
    }

    #[Route('/new', name: 'app_inventaire_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        InventaireManager $inventaireManager
    ): Response {
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('inventaire_new', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('app_inventaire_index');
            }

            $titre = trim((string) $request->request->get('titre'));
            $notes = trim((string) $request->request->get('notes'));

            if ($titre === '') {
                $this->addFlash('danger', 'Le titre de la campagne est obligatoire.');
                return $this->render('inventaire/new.html.twig');
            }

            $username = $this->getUser()?->getUserIdentifier() ?? 'Système';
            $ipAddress = $request->getClientIp() ?? '—';

            $campagne = $inventaireManager->demarrerCampagne($titre, $notes, $username, $ipAddress);

            $this->addFlash('success', sprintf(
                'Campagne « %s » démarrée avec succès avec %d équipements.',
                $campagne->getTitre(),
                $campagne->getTotalEquipements()
            ));

            return $this->redirectToRoute('app_inventaire_show', ['id' => $campagne->getId()]);
        }

        return $this->render('inventaire/new.html.twig');
    }

    #[Route('/{id}', name: 'app_inventaire_show', methods: ['GET'])]
    public function show(
        CampagneInventaire $campagne,
        Request $request,
        LigneInventaireRepository $ligneRepo,
        LieuRepository $lieuRepo
    ): Response {
        $search = trim((string) $request->query->get('q', ''));
        $statut = trim((string) $request->query->get('statut', 'all'));
        $lieuId = $request->query->get('lieu');
        $lieuIdInt = (!empty($lieuId) && is_numeric($lieuId)) ? (int) $lieuId : null;

        $lignes = $ligneRepo->findByCampagneAndFilters($campagne, $search ?: null, $statut, $lieuIdInt);
        $lieux = $lieuRepo->findBy([], ['nom_lieu' => 'ASC']);

        return $this->render('inventaire/show.html.twig', [
            'campagne'        => $campagne,
            'lignes'          => $lignes,
            'lieux'           => $lieux,
            'current_search'  => $search,
            'current_statut'  => $statut,
            'current_lieu'    => $lieuIdInt,
            'statuts_choices' => LigneInventaire::STATUTS,
        ]);
    }

    /**
     * Pointage d'une ligne d'inventaire (POST standard ou AJAX).
     */
    #[Route('/ligne/{id}/pointer', name: 'app_inventaire_pointer', methods: ['POST'])]
    public function pointer(
        LigneInventaire $ligne,
        Request $request,
        EntityManagerInterface $em,
        InventaireManager $inventaireManager
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('pointer_' . $ligne->getId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'CSRF invalide'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_inventaire_show', ['id' => $ligne->getCampagne()->getId()]);
        }

        $campagne = $ligne->getCampagne();
        if ($campagne->getStatut() === CampagneInventaire::STATUT_CLOTUREE) {
            $this->addFlash('warning', 'Cette campagne est clôturée. Aucun pointage supplémentaire n\'est possible.');
            return $this->redirectToRoute('app_inventaire_show', ['id' => $campagne->getId()]);
        }

        $statut = trim((string) $request->request->get('statut'));
        if (!in_array($statut, LigneInventaire::STATUTS, true)) {
            $this->addFlash('danger', 'Statut constaté invalide.');
            return $this->redirectToRoute('app_inventaire_show', ['id' => $campagne->getId()]);
        }

        $lieuId = $request->request->get('lieu_constate_id');
        $lieuConstate = null;
        if (!empty($lieuId) && is_numeric($lieuId)) {
            $lieuConstate = $em->getRepository(Lieu::class)->find((int) $lieuId);
        }

        $observations = trim((string) $request->request->get('observations'));
        $username = $this->getUser()?->getUserIdentifier() ?? 'Système';
        $ipAddress = $request->getClientIp() ?? '—';

        $inventaireManager->pointerLigne($ligne, $statut, $lieuConstate, $observations, $username, $ipAddress);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'statut' => $ligne->getStatutConstate(),
                'isEcart' => $ligne->isEcart(),
                'progression' => $campagne->getTauxProgression(),
                'controles' => $campagne->getEquipementsControlesCount(),
                'total' => $campagne->getTotalEquipements(),
            ]);
        }

        $this->addFlash('success', sprintf(
            'Pointage enregistré pour « %s » : %s',
            $ligne->getEquipement()->getNom(),
            $statut
        ));

        return $this->redirectToRoute('app_inventaire_show', [
            'id' => $campagne->getId(),
            '_fragment' => 'ligne-' . $ligne->getId(),
        ]);
    }

    /**
     * Rapport détaillé des écarts constatés lors de la campagne.
     */
    #[Route('/{id}/ecarts', name: 'app_inventaire_ecarts', methods: ['GET'])]
    public function ecarts(
        CampagneInventaire $campagne,
        LigneInventaireRepository $ligneRepo
    ): Response {
        $ecarts = $ligneRepo->findEcarts($campagne);

        return $this->render('inventaire/ecarts.html.twig', [
            'campagne' => $campagne,
            'ecarts'   => $ecarts,
        ]);
    }

    /**
     * Clôture formelle de la campagne d'inventaire.
     */
    #[Route('/{id}/cloturer', name: 'app_inventaire_cloturer', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function cloturer(
        CampagneInventaire $campagne,
        Request $request,
        InventaireManager $inventaireManager
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cloturer_campagne_' . $campagne->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_inventaire_show', ['id' => $campagne->getId()]);
        }

        $username = $this->getUser()?->getUserIdentifier() ?? 'Système';
        $ipAddress = $request->getClientIp() ?? '—';

        $inventaireManager->cloturerCampagne($campagne, $username, $ipAddress);

        $this->addFlash('success', sprintf('La campagne « %s » a été officiellement clôturée.', $campagne->getTitre()));

        return $this->redirectToRoute('app_inventaire_show', ['id' => $campagne->getId()]);
    }

    /**
     * Procès-verbal officiel d'inventaire au format PDF.
     */
    #[Route('/{id}/pdf', name: 'app_inventaire_pdf', methods: ['GET'])]
    public function exportPdf(
        CampagneInventaire $campagne,
        LigneInventaireRepository $ligneRepo,
        PdfExportService $pdfService
    ): Response {
        $ecarts = $ligneRepo->findEcarts($campagne);

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $campagne->getTitre());
        $filename = sprintf('PV_Inventaire_%s_%s.pdf', $safeName, date('Ymd'));

        return $pdfService->generatePdfResponse(
            template: 'pdf/inventaire.html.twig',
            variables: [
                'campagne' => $campagne,
                'ecarts'   => $ecarts,
            ],
            filename: $filename,
            orientation: 'landscape', // Format paysage pour accueillir le tableau d'écarts
        );
    }
}

