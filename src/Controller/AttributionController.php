<?php

namespace App\Controller;

use App\Entity\Attribution;
use App\Entity\Equipement;
use App\Form\AttributionType;
use App\Repository\AttributionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/attributions')]
class AttributionController extends AbstractController
{
    #[Route('', name: 'app_attribution_index', methods: ['GET'])]
    public function index(Request $request, AttributionRepository $repository): Response
    {
        $filter = $request->query->get('statut', 'all');
        $search = trim((string) $request->query->get('q', ''));

        $qb = $repository->createQueryBuilder('a')
            ->leftJoin('a.equipement', 'e')
            ->addSelect('e')
            ->orderBy('a.dateDebut', 'DESC');

        if ($filter === 'active') {
            $qb->andWhere('a.statut = :statut')->setParameter('statut', 'En cours');
        } elseif ($filter === 'returned') {
            $qb->andWhere('a.statut = :statut')->setParameter('statut', 'Restitué');
        }

        if ($search !== '') {
            $qb->andWhere(
                'a.beneficiaireNom LIKE :q OR a.beneficiaireService LIKE :q OR a.motif LIKE :q OR e.nom LIKE :q OR e.numeroSerie LIKE :q OR e.tagInventaire LIKE :q'
            )->setParameter('q', '%' . $search . '%');
        }

        $attributions = $qb->getQuery()->getResult();

        return $this->render('attribution/index.html.twig', [
            'attributions' => $attributions,
            'current_filter' => $filter,
            'current_search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_attribution_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        \App\Service\AttributionManager $attributionManager
    ): Response
    {
        $attribution = new Attribution();

        // Pré-sélection éventuelle via query param ?equipement_id=X
        $eqId = $request->query->get('equipement_id');
        if ($eqId) {
            $eq = $em->getRepository(Equipement::class)->find($eqId);
            if ($eq) {
                $attribution->setEquipement($eq);
            }
        }

        // Pré-sélection éventuelle via query param ?personnel_id=X
        $personnelId = $request->query->get('personnel_id');
        if ($personnelId) {
            $p = $em->getRepository(\App\Entity\Personnel::class)->find($personnelId);
            if ($p) {
                $attribution->setPersonnel($p);
            }
        }

        $form = $this->createForm(AttributionType::class, $attribution);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $equipement = $attribution->getEquipement();
            $personnel = $attribution->getPersonnel();
            $operatorUsername = $this->getUser()?->getUserIdentifier() ?? 'system';
            $clientIp = $request->getClientIp() ?? '127.0.0.1';

            // Règle Métier : Vérifier si le collaborateur possède déjà un matériel actif dans la même catégorie individuelle
            if ($personnel && $equipement) {
                $conflicting = $attributionManager->findConflictingActiveAttribution($personnel, $equipement);
                if ($conflicting) {
                    $oldAction = (string) $request->request->get('old_equipment_action', 'stock');
                    $reassignPersonnelId = $request->request->get('reassign_personnel_id');
                    $reassignPersonnel = null;
                    if ($reassignPersonnelId && is_numeric($reassignPersonnelId)) {
                        $reassignPersonnel = $em->getRepository(\App\Entity\Personnel::class)->find((int) $reassignPersonnelId);
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

            // Mise à jour automatique de l'état du nouvel équipement
            if ($equipement) {
                $equipement->setStatut('En service');
            }

            $em->persist($attribution);
            $em->flush();

            $this->addFlash('success', sprintf('Équipement « %s » attribué avec succès à %s.', $equipement?->getNom(), $attribution->getBeneficiaireNom()));
            return $this->redirectToRoute('app_attribution_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('attribution/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle Attribution / Prêt de Matériel',
            'button_label' => 'Valider l\'attribution',
        ]);
    }

    #[Route('/{id}/restituer', name: 'app_attribution_restituer', methods: ['POST'])]
    public function restituer(Request $request, Attribution $attribution, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('restituer' . $attribution->getId(), (string) $request->request->get('_token'))) {
            $attribution->setStatut('Restitué');
            $attribution->setDateRestitution(new \DateTime());

            // L'équipement redevient disponible en stock
            $equipement = $attribution->getEquipement();
            if ($equipement) {
                $equipement->setStatut('En stock');
            }

            $em->flush();
            $this->addFlash('success', 'Matériel restitué avec succès. Statut remis en stock.');
        }

        return $this->redirectToRoute('app_attribution_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_attribution_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Attribution $attribution, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AttributionType::class, $attribution);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Fiche d\'attribution modifiée.');
            return $this->redirectToRoute('app_attribution_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('attribution/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier l\'attribution #' . $attribution->getId(),
            'button_label' => 'Mettre à jour',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_attribution_delete', methods: ['POST'])]
    public function delete(Request $request, Attribution $attribution, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $attribution->getId(), (string) $request->request->get('_token'))) {
            $em->remove($attribution);
            $em->flush();
            $this->addFlash('success', 'Attribution supprimée.');
        }

        return $this->redirectToRoute('app_attribution_index', [], Response::HTTP_SEE_OTHER);
    }
}
