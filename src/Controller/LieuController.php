<?php

namespace App\Controller;

use App\Entity\Composant;
use App\Entity\Equipement;
use App\Entity\Lieu;
use App\Form\LieuType;
use App\Repository\LieuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/lieux')]
class LieuController extends AbstractController
{
    #[Route('', name: 'app_lieu_index', methods: ['GET'])]
    public function index(Request $request, LieuRepository $repository, EntityManagerInterface $em): Response
    {
        $search = trim((string) $request->query->get('q', ''));

        $qb = $repository->createQueryBuilder('l')->orderBy('l.nom_lieu', 'ASC');

        if ($search !== '') {
            $qb->andWhere('l.nom_lieu LIKE :q OR l.description LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        $lieux = $qb->getQuery()->getResult();

        // Calcul du nombre d'équipements par lieu
        $counts = [];
        $eqRepo = $em->getRepository(Equipement::class);
        foreach ($lieux as $lieu) {
            $counts[$lieu->getId()] = $eqRepo->count(['lieu' => $lieu]);
        }

        return $this->render('lieu/index.html.twig', [
            'lieux' => $lieux,
            'counts' => $counts,
            'current_search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_lieu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $lieu = new Lieu();
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($lieu);
            $em->flush();

            $this->addFlash('success', 'Emplacement "' . $lieu->getNomLieu() . '" ajouté avec succès.');
            return $this->redirectToRoute('app_lieu_index');
        }

        return $this->render('lieu/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvel Emplacement / Salle',
            'button_label' => 'Enregistrer le lieu',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_lieu_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Lieu $lieu, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Emplacement mis à jour avec succès.');
            return $this->redirectToRoute('app_lieu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lieu/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le lieu : ' . $lieu->getNomLieu(),
            'button_label' => 'Mettre à jour',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_lieu_delete', methods: ['POST'])]
    public function delete(Request $request, Lieu $lieu, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $lieu->getId(), (string) $request->request->get('_token'))) {
            // Dissocier les équipements et composants liés
            $equipements = $em->getRepository(Equipement::class)->findBy(['lieu' => $lieu]);
            foreach ($equipements as $eq) {
                $eq->setLieu(null);
            }

            $composants = $em->getRepository(Composant::class)->findBy(['lieu' => $lieu]);
            foreach ($composants as $comp) {
                $comp->setLieu(null);
            }

            $em->remove($lieu);
            $em->flush();
            $this->addFlash('success', 'Emplacement supprimé avec succès.');
        }

        return $this->redirectToRoute('app_lieu_index', [], Response::HTTP_SEE_OTHER);
    }
}
