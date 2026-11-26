<?php

namespace App\Controller;

use App\Entity\PosteAutorise;
use App\Repository\PosteAutoriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/postes')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class PosteAutoriseController extends AbstractController
{
    #[Route('', name: 'app_postes_index', methods: ['GET'])]
    public function index(PosteAutoriseRepository $posteRepository, Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        if ($search !== '') {
            $qb = $posteRepository->createQueryBuilder('p')
                ->where('p.nom LIKE :q OR p.adresseIp LIKE :q OR p.description LIKE :q')
                ->setParameter('q', '%' . $search . '%')
                ->orderBy('p.dateDerniereConnexion', 'DESC');
            $postes = $qb->getQuery()->getResult();
        } else {
            $postes = $posteRepository->findBy([], ['dateDerniereConnexion' => 'DESC']);
        }

        return $this->render('poste_autorise/index.html.twig', [
            'postes' => $postes,
            'client_ip' => $clientIp,
            'current_search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_postes_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, PosteAutoriseRepository $posteRepository): Response
    {
        if (!$this->isCsrfTokenValid('new_poste', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_postes_index');
        }

        $nom = trim((string) $request->request->get('nom'));
        $ip = trim((string) $request->request->get('adresse_ip'));
        $description = trim((string) $request->request->get('description'));

        if (empty($nom) || empty($ip)) {
            $this->addFlash('warning', 'Le nom et l\'adresse IP sont obligatoires.');
            return $this->redirectToRoute('app_postes_index');
        }

        $existing = $posteRepository->findOneBy(['adresseIp' => $ip]);
        if ($existing) {
            $this->addFlash('warning', sprintf('Cette adresse IP (%s) est déjà enregistrée sous le nom "%s".', $ip, $existing->getNom()));
            return $this->redirectToRoute('app_postes_index');
        }

        $poste = new PosteAutorise();
        $poste->setNom($nom);
        $poste->setAdresseIp($ip);
        $poste->setDescription($description ?: 'Ajouté manuellement par le Superadmin');
        $poste->setIsAutorise(true);
        $poste->setDatePremiereConnexion(new \DateTimeImmutable());
        $poste->setDateDerniereConnexion(new \DateTimeImmutable());

        $entityManager->persist($poste);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Le poste "%s" (%s) a été ajouté et autorisé.', $nom, $ip));

        return $this->redirectToRoute('app_postes_index');
    }

    #[Route('/{id}/toggle', name: 'app_postes_toggle', methods: ['POST'])]
    public function toggle(PosteAutorise $poste, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('toggle_poste_' . $poste->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_postes_index');
        }

        $poste->setIsAutorise(!$poste->isAutorise());
        $entityManager->flush();

        $statusText = $poste->isAutorise() ? 'autorisé' : 'bloqué / restreint';
        $this->addFlash(
            $poste->isAutorise() ? 'success' : 'warning',
            sprintf('Le poste "%s" (IP : %s) est désormais %s.', $poste->getNom(), $poste->getAdresseIp(), $statusText)
        );

        return $this->redirectToRoute('app_postes_index');
    }

    #[Route('/{id}/delete', name: 'app_postes_delete', methods: ['POST'])]
    public function delete(PosteAutorise $poste, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_poste_' . $poste->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_postes_index');
        }

        $nom = $poste->getNom();
        $ip = $poste->getAdresseIp();

        $entityManager->remove($poste);
        $entityManager->flush();

        $this->addFlash('info', sprintf('Le poste "%s" (%s) a été supprimé de la liste blanche.', $nom, $ip));

        return $this->redirectToRoute('app_postes_index');
    }
}
