<?php

namespace App\Controller;

use App\Entity\Attribution;
use App\Entity\Equipement;
use App\Entity\Personnel;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SearchController extends AbstractController
{
    #[Route('/api/recherche', name: 'app_search_global', methods: ['GET'])]
    public function search(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));

        if (strlen($q) < 2) {
            return $this->json(['results' => [], 'query' => $q]);
        }

        $like = '%' . $q . '%';
        $results = [];

        // ── Équipements ──────────────────────────────────────────────
        $equipements = $em->getRepository(Equipement::class)
            ->createQueryBuilder('e')
            ->where('e.nom LIKE :q OR e.tagInventaire LIKE :q OR e.numeroSerie LIKE :q OR e.marque LIKE :q OR e.modele LIKE :q OR e.adresseIp LIKE :q')
            ->setParameter('q', $like)
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        foreach ($equipements as $e) {
            $results[] = [
                'type'     => 'equipement',
                'icon'     => 'bi-pc-horizontal',
                'label'    => $e->getNom() . ($e->getMarque() ? ' · ' . $e->getMarque() : ''),
                'sub'      => $e->getTagInventaire() ?: ($e->getNumeroSerie() ?: $e->getAdresseIp() ?: '—'),
                'statut'   => $e->getStatut(),
                'url'      => $this->generateUrl('app_equipement_show', ['id' => $e->getId()]),
            ];
        }

        // ── Attributions en cours ─────────────────────────────────────
        $attributions = $em->getRepository(Attribution::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.equipement', 'e')
            ->addSelect('e')
            ->where('a.beneficiaireNom LIKE :q OR a.beneficiaireService LIKE :q OR e.nom LIKE :q OR e.tagInventaire LIKE :q')
            ->andWhere('a.statut = :statut')
            ->setParameter('q', $like)
            ->setParameter('statut', 'En cours')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        foreach ($attributions as $a) {
            $results[] = [
                'type'   => 'attribution',
                'icon'   => 'bi-laptop',
                'label'  => $a->getBeneficiaireNom(),
                'sub'    => ($a->getEquipement() ? $a->getEquipement()->getNom() : '—') . ($a->getBeneficiaireService() ? ' · ' . $a->getBeneficiaireService() : ''),
                'statut' => 'En cours',
                'url'    => $this->generateUrl('app_attribution_edit', ['id' => $a->getId()]),
            ];
        }

        // ── Personnels & Intervenants ─────────────────────────────────
        $personnels = $em->getRepository(Personnel::class)
            ->createQueryBuilder('p')
            ->where('p.nom LIKE :q OR p.matricule LIKE :q OR p.service LIKE :q OR p.email LIKE :q')
            ->setParameter('q', $like)
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        foreach ($personnels as $p) {
            $results[] = [
                'type'   => 'personnel',
                'icon'   => 'bi-person-badge',
                'label'  => sprintf('[%s] %s', $p->getMatricule(), $p->getNom()),
                'sub'    => $p->getType() . ($p->getService() ? ' · ' . $p->getService() : ''),
                'statut' => $p->getStatut(),
                'url'    => $this->generateUrl('app_personnel_edit', ['id' => $p->getId()]),
            ];
        }

        // ── Utilisateurs (Superadmin uniquement) ──────────────────────
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            $users = $em->getRepository(User::class)
                ->createQueryBuilder('u')
                ->where('u.username LIKE :q')
                ->setParameter('q', $like)
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();

            foreach ($users as $u) {
                $roles = $u->getRoles();
                $roleLabel = in_array('ROLE_SUPER_ADMIN', $roles) ? 'Superadmin' : (in_array('ROLE_ADMIN', $roles) ? 'Admin' : 'Utilisateur');
                $results[] = [
                    'type'   => 'user',
                    'icon'   => 'bi-person-circle',
                    'label'  => $u->getUsername(),
                    'sub'    => $roleLabel,
                    'statut' => '',
                    'url'    => $this->generateUrl('app_user_index'),
                ];
            }
        }

        return $this->json([
            'results' => $results,
            'query'   => $q,
            'total'   => count($results),
        ]);
    }
}

