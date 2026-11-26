<?php

namespace App\Controller;

use App\Entity\CategorieEquipement;
use App\Entity\Equipement;
use App\Form\CategorieEquipementType;
use App\Repository\CategorieEquipementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/categories')]
class CategorieEquipementController extends AbstractController
{
    #[Route('', name: 'app_categorie_index', methods: ['GET'])]
    public function index(Request $request, CategorieEquipementRepository $repository, EntityManagerInterface $em): Response
    {
        $search = trim((string) $request->query->get('q', ''));

        $qb = $repository->createQueryBuilder('c')->orderBy('c.nom_cat', 'ASC');

        if ($search !== '') {
            $qb->andWhere('c.nom_cat LIKE :q OR c.description LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        $categories = $qb->getQuery()->getResult();

        // Calcul du nombre d'équipements par catégorie
        $counts = [];
        $eqRepo = $em->getRepository(Equipement::class);
        foreach ($categories as $cat) {
            $counts[$cat->getId()] = $eqRepo->count(['categorie' => $cat]);
        }

        return $this->render('categorie/index.html.twig', [
            'categories' => $categories,
            'counts' => $counts,
            'current_search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_categorie_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $categorie = new CategorieEquipement();
        $form = $this->createForm(CategorieEquipementType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($categorie);
            $em->flush();

            $this->addFlash('success', 'Catégorie "' . $categorie->getNomCat() . '" créée avec succès.');
            return $this->redirectToRoute('app_categorie_index');
        }

        return $this->render('categorie/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle Catégorie d\'Équipements',
            'button_label' => 'Enregistrer la catégorie',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_categorie_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CategorieEquipement $categorie, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategorieEquipementType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Catégorie modifiée avec succès.');
            return $this->redirectToRoute('app_categorie_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('categorie/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier la catégorie : ' . $categorie->getNomCat(),
            'button_label' => 'Mettre à jour',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_categorie_delete', methods: ['POST'])]
    public function delete(Request $request, CategorieEquipement $categorie, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $categorie->getId(), (string) $request->request->get('_token'))) {
            // Dissocier les équipements liés pour préserver la cohérence
            $equipements = $em->getRepository(Equipement::class)->findBy(['categorie' => $categorie]);
            foreach ($equipements as $eq) {
                $eq->setCategorie(null);
            }

            $em->remove($categorie);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée avec succès.');
        }

        return $this->redirectToRoute('app_categorie_index', [], Response::HTTP_SEE_OTHER);
    }
}
