<?php

namespace App\Controller;

use App\Entity\Composant;
use App\Entity\Equipement;
use App\Form\ComposantType;
use App\Repository\ComposantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/composants')]
class ComposantController extends AbstractController
{
    #[Route('', name: 'app_composant_index', methods: ['GET'])]
    public function index(Request $request, ComposantRepository $repository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $statutFilter = $request->query->get('statut', 'all');
        $typeFilter = $request->query->get('type', 'all');
        $ageFilter = $request->query->get('age', 'all');

        $qb = $repository->createQueryBuilder('c')
            ->leftJoin('c.equipement', 'e')
            ->leftJoin('c.lieu', 'l')
            ->addSelect('e')
            ->addSelect('l')
            ->orderBy('c.dateAjout', 'DESC');

        if ($search !== '') {
            $qb->andWhere('c.nom LIKE :q OR c.numeroSerie LIKE :q OR e.nom LIKE :q')
                ->setParameter('q', '%' . $search . '%');
        }

        if ($statutFilter !== 'all') {
            $qb->andWhere('c.statut = :statut')
                ->setParameter('statut', $statutFilter);
        }

        if ($typeFilter !== 'all') {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $typeFilter);
        }

        $allComposants = $qb->getQuery()->getResult();

        // Filtre vétusté en mémoire (calcul basé sur la date d'acquisition)
        if ($ageFilter === 'vieillissant') {
            $composants = array_filter($allComposants, fn(Composant $c) => $c->isVieillissant());
        } elseif ($ageFilter === 'obsolete') {
            $composants = array_filter($allComposants, fn(Composant $c) => $c->isObsolete());
        } else {
            $composants = $allComposants;
        }

        // Statistiques rapides
        $totalItems = count($allComposants);
        $totalStock = count(array_filter($allComposants, fn(Composant $c) => $c->getStatut() === 'En stock'));
        $totalInstalles = count(array_filter($allComposants, fn(Composant $c) => $c->getStatut() === 'Installé'));
        $totalDefectueux = count(array_filter($allComposants, fn(Composant $c) => $c->getStatut() === 'Défectueux'));
        $totalVieux = count(array_filter($allComposants, fn(Composant $c) => $c->isVieillissant() || $c->isObsolete()));

        return $this->render('composant/index.html.twig', [
            'composants' => $composants,
            'search' => $search,
            'current_statut' => $statutFilter,
            'current_type' => $typeFilter,
            'current_age' => $ageFilter,
            'stats' => [
                'total' => $totalItems,
                'stock' => $totalStock,
                'installes' => $totalInstalles,
                'defectueux' => $totalDefectueux,
                'vieux' => $totalVieux,
            ],
        ]);
    }

    #[Route('/new', name: 'app_composant_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $composant = new Composant();

        // Pré-sélection éventuelle via ?equipement_id=X
        $eqId = $request->query->get('equipement_id');
        if ($eqId) {
            $eq = $em->getRepository(Equipement::class)->find($eqId);
            if ($eq) {
                $composant->setEquipement($eq);
                $composant->setStatut('Installé');
            }
        }

        $form = $this->createForm(ComposantType::class, $composant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($composant);
            $em->flush();

            $this->addFlash('success', 'Composant "' . $composant->getNom() . '" ajouté à l\'inventaire.');
            return $this->redirectToRoute('app_composant_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('composant/form.html.twig', [
            'form' => $form,
            'title' => 'Ajouter un Composant / Pièce Détachée',
            'button_label' => 'Enregistrer le composant',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_composant_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Composant $composant, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ComposantType::class, $composant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Fiche composant mise à jour avec succès.');
            return $this->redirectToRoute('app_composant_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('composant/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le composant : ' . $composant->getNom(),
            'button_label' => 'Mettre à jour',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_composant_delete', methods: ['POST'])]
    public function delete(Request $request, Composant $composant, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $composant->getId(), (string) $request->request->get('_token'))) {
            $em->remove($composant);
            $em->flush();
            $this->addFlash('success', 'Composant retiré de l\'inventaire.');
        }

        return $this->redirectToRoute('app_composant_index', [], Response::HTTP_SEE_OTHER);
    }
}
