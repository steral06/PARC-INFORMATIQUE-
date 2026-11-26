<?php

namespace App\Service;

use App\Entity\CampagneInventaire;
use App\Entity\Equipement;
use App\Entity\Lieu;
use App\Entity\LigneInventaire;
use App\Repository\CampagneInventaireRepository;
use App\Repository\EquipementRepository;
use App\Repository\LigneInventaireRepository;
use Doctrine\ORM\EntityManagerInterface;

class InventaireManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EquipementRepository $equipementRepository,
        private readonly CampagneInventaireRepository $campagneRepository,
        private readonly LigneInventaireRepository $ligneRepository,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Démarre une nouvelle campagne d'inventaire en injectant automatiquement
     * tous les équipements du parc (hors "Au rebut").
     */
    public function demarrerCampagne(
        string $titre,
        ?string $notes,
        string $username,
        ?string $ipAddress = null,
    ): CampagneInventaire {
        $campagne = new CampagneInventaire();
        $campagne->setTitre(trim($titre));
        $campagne->setNotes($notes ? trim($notes) : null);
        $campagne->setCreePar($username);
        $campagne->setDateDebut(new \DateTime());
        $campagne->setStatut(CampagneInventaire::STATUT_EN_COURS);

        $this->em->persist($campagne);

        // Récupérer tous les équipements hors "Au rebut"
        $equipements = $this->equipementRepository->createQueryBuilder('e')
            ->where('e.statut != :rebut')
            ->setParameter('rebut', 'Au rebut')
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($equipements as $equipement) {
            $ligne = new LigneInventaire();
            $ligne->setCampagne($campagne);
            $ligne->setEquipement($equipement);
            $ligne->setStatutConstate(LigneInventaire::STATUT_EN_ATTENTE);
            $ligne->setLieuAttendu($equipement->getLieu()?->getNomLieu() ?? 'Non affecté');

            $campagne->addLigne($ligne);
            $this->em->persist($ligne);
        }

        $this->em->flush();

        $this->auditLogger->log(
            action: 'INVENTAIRE_CAMPAGNE_CREATION',
            level: 'INFO',
            description: sprintf(
                'Lancement de la campagne d\'inventaire « %s » (%d équipements injectés)',
                $campagne->getTitre(),
                count($equipements)
            ),
            entityType: 'CampagneInventaire',
            entityId: (string) $campagne->getId(),
            entityLabel: $campagne->getTitre(),
            username: $username,
            ipAddress: $ipAddress ?? '—',
            details: [
                'campagne_id' => $campagne->getId(),
                'total_equipements' => count($equipements),
            ]
        );

        return $campagne;
    }

    /**
     * Enregistre le pointage physique d'un équipement.
     */
    public function pointerLigne(
        LigneInventaire $ligne,
        string $statut,
        ?Lieu $lieuConstate,
        ?string $observations,
        string $username,
        ?string $ipAddress = null,
    ): void {
        $ancienStatut = $ligne->getStatutConstate();

        $ligne->setStatutConstate($statut);
        $ligne->setLieuConstate($lieuConstate);
        $ligne->setObservations($observations ? trim($observations) : null);
        $ligne->setDatePointage(new \DateTime());
        $ligne->setPointePar($username);

        $this->em->flush();

        // Si écart constaté, journaliser dans l'audit
        if ($ligne->isEcart()) {
            $this->auditLogger->log(
                action: 'INVENTAIRE_ECART_CONSTATE',
                level: 'WARNING',
                description: sprintf(
                    'Écart d\'inventaire constaté sur [%s] %s : Statut « %s » (Lieu attendu: %s, Lieu constaté: %s)',
                    $ligne->getEquipement()->getNumeroSerie(),
                    $ligne->getEquipement()->getNom(),
                    $statut,
                    $ligne->getLieuAttendu() ?? 'N/C',
                    $lieuConstate?->getNomLieu() ?? 'N/C'
                ),
                entityType: 'Equipement',
                entityId: (string) $ligne->getEquipement()->getId(),
                entityLabel: $ligne->getEquipement()->getNom(),
                username: $username,
                ipAddress: $ipAddress ?? '—',
                details: [
                    'campagne_id' => $ligne->getCampagne()->getId(),
                    'statut_precedent' => $ancienStatut,
                    'statut_constate' => $statut,
                    'lieu_attendu' => $ligne->getLieuAttendu(),
                    'lieu_constate' => $lieuConstate?->getNomLieu(),
                    'observations' => $observations,
                ]
            );
        }
    }

    /**
     * Clôture formelle d'une campagne d'inventaire.
     */
    public function cloturerCampagne(
        CampagneInventaire $campagne,
        string $username,
        ?string $ipAddress = null,
    ): void {
        $campagne->setStatut(CampagneInventaire::STATUT_CLOTUREE);
        $campagne->setDateCloture(new \DateTime());

        $this->em->flush();

        $this->auditLogger->log(
            action: 'INVENTAIRE_CAMPAGNE_CLOTURE',
            level: 'INFO',
            description: sprintf(
                'Clôture officielle de la campagne d\'inventaire « %s » : Taux de couverture %.1f%%, %d conformes, %d écarts (%d déplacés, %d manquants, %d hors-service)',
                $campagne->getTitre(),
                $campagne->getTauxProgression(),
                $campagne->getConformesCount(),
                ($campagne->getDeplacesCount() + $campagne->getManquantsCount() + $campagne->getHorsServiceCount()),
                $campagne->getDeplacesCount(),
                $campagne->getManquantsCount(),
                $campagne->getHorsServiceCount()
            ),
            entityType: 'CampagneInventaire',
            entityId: (string) $campagne->getId(),
            entityLabel: $campagne->getTitre(),
            username: $username,
            ipAddress: $ipAddress ?? '—',
            details: [
                'taux_progression' => $campagne->getTauxProgression(),
                'total' => $campagne->getTotalEquipements(),
                'controles' => $campagne->getEquipementsControlesCount(),
                'conformes' => $campagne->getConformesCount(),
                'deplaces' => $campagne->getDeplacesCount(),
                'manquants' => $campagne->getManquantsCount(),
                'hors_service' => $campagne->getHorsServiceCount(),
            ]
        );
    }
}

