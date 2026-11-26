<?php

namespace App\Service;

use App\Entity\Attribution;
use App\Entity\CategorieEquipement;
use App\Entity\Equipement;
use App\Entity\Personnel;
use Doctrine\ORM\EntityManagerInterface;

class AttributionManager
{
    public const FAMILY_LAPTOP  = 'laptop';   // Ordinateurs Portables
    public const FAMILY_DESKTOP = 'desktop';  // Ordinateurs de Bureau / Fixes / Unités centrales
    public const FAMILY_PRINTER = 'printer';  // Imprimantes & Scanners
    public const FAMILY_OTHER   = 'other';    // Serveurs, Onduleurs, Réseau, Écrans (Multiples autorisés)

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Détermine la famille de catégorie pour les règles d'attribution individuelle.
     * Note Métier :
     * - Un collaborateur peut avoir 1 Laptop ET 1 Desktop (Directeur, Chef de service, DSI).
     * - Mais il ne peut pas avoir 2 Laptops ni 2 Desktops ni 2 Imprimantes en même temps.
     * - Pour les serveurs, onduleurs, switchs : illimité.
     */
    public function getCategoryFamily(?CategorieEquipement $categorie): string
    {
        if (!$categorie || !$categorie->getNomCat()) {
            return self::FAMILY_OTHER;
        }

        $norm = mb_strtolower($categorie->getNomCat());

        // 1. Ordinateurs Portables
        if (str_contains($norm, 'portable') || str_contains($norm, 'laptop')) {
            return self::FAMILY_LAPTOP;
        }

        // 2. Ordinateurs de Bureau / Postes Fixes / Unités Centrales
        if (
            str_contains($norm, 'fixe') ||
            str_contains($norm, 'centrale') ||
            str_contains($norm, 'bureau') ||
            str_contains($norm, 'desktop')
        ) {
            return self::FAMILY_DESKTOP;
        }

        // 3. Imprimantes & Scanners
        if (
            str_contains($norm, 'imprimante') ||
            str_contains($norm, 'scanner') ||
            str_contains($norm, 'copieur') ||
            str_contains($norm, 'printer')
        ) {
            return self::FAMILY_PRINTER;
        }

        // 4. Serveurs, Onduleurs, Écrans, Réseau -> Infrastructure / Multiples autorisés
        return self::FAMILY_OTHER;
    }

    /**
     * Indique si cette catégorie est soumise à la règle de dotation exclusive (max 1 par collaborateur).
     */
    public function isSinglePerUserCategory(?CategorieEquipement $categorie): bool
    {
        $family = $this->getCategoryFamily($categorie);
        return in_array($family, [self::FAMILY_LAPTOP, self::FAMILY_DESKTOP, self::FAMILY_PRINTER], true);
    }

    /**
     * Recherche une attribution active existante pour le même personnel dans la même catégorie exclusive.
     * (Ex: 2ème portable, 2ème fixe, ou 2ème imprimante).
     */
    public function findConflictingActiveAttribution(Personnel $personnel, Equipement $newEquipement): ?Attribution
    {
        $newFamily = $this->getCategoryFamily($newEquipement->getCategorie());

        // Si c'est un serveur, onduleur, switch, écran, pas de conflit possible
        if (!in_array($newFamily, [self::FAMILY_LAPTOP, self::FAMILY_DESKTOP, self::FAMILY_PRINTER], true)) {
            return null;
        }

        // Parcourir les attributions actives du personnel
        foreach ($personnel->getAttributions() as $attribution) {
            if ($attribution->getStatut() === 'En cours') {
                $eq = $attribution->getEquipement();
                if ($eq && $eq->getId() !== $newEquipement->getId()) {
                    $existingFamily = $this->getCategoryFamily($eq->getCategorie());
                    if ($existingFamily === $newFamily) {
                        return $attribution;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Gère la transition d'un ancien équipement lors d'un remplacement :
     * 1. Clôture l'ancienne attribution.
     * 2. Transfère l'adresse IP et le code inventaire de l'ancien équipement vers le nouveau.
     * 3. Remet l'ancien en stock ou le réattribue immédiatement.
     *
     * @return array{message: string, type: string}
     */
    public function handleOldEquipmentAction(
        Attribution $oldAttribution,
        Equipement $newEquipement,
        string $action,
        ?Personnel $newBeneficiary,
        string $operatorUsername,
        string $clientIp
    ): array {
        $oldEquipement = $oldAttribution->getEquipement();
        $oldBeneficiaryNom = $oldAttribution->getBeneficiaireNom() ?? 'Collaborateur';

        // 1. Clôturer l'ancienne attribution
        $oldAttribution->setStatut('Restitué');
        $oldAttribution->setDateRestitution(new \DateTime());

        // 2. Transfert de l'Adresse IP et du Code Inventaire (Règle DSI GTAC2A)
        $transferredDetails = [];
        if ($oldEquipement) {
            $oldIp  = $oldEquipement->getAdresseIp();
            $oldTag = $oldEquipement->getTagInventaire();

            // Supprimer l'IP et le code inventaire de l'ancienne machine
            $oldEquipement->setAdresseIp(null);
            $oldEquipement->setTagInventaire(null);

            // Transférer au nouvel équipement
            if ($oldIp) {
                $newEquipement->setAdresseIp($oldIp);
                $transferredDetails[] = sprintf('IP : %s', $oldIp);
            }
            if ($oldTag) {
                $newEquipement->setTagInventaire($oldTag);
                $transferredDetails[] = sprintf('Tag Inventaire : %s', $oldTag);
            }
        }

        $transferSummary = !empty($transferredDetails)
            ? ' (' . implode(', ', $transferredDetails) . ' transférés au nouveau matériel)'
            : '';

        // 3. Option Réattribution immédiate
        if ($action === 'reassign' && $newBeneficiary) {
            $newAttribution = new Attribution();
            $newAttribution->setEquipement($oldEquipement);
            $newAttribution->setPersonnel($newBeneficiary);
            $newAttribution->setBeneficiaireNom($newBeneficiary->getNom());
            $newAttribution->setBeneficiaireService($newBeneficiary->getService());
            $newAttribution->setDateDebut(new \DateTime());
            $newAttribution->setStatut('En cours');
            $newAttribution->setMotif(sprintf('Réattribution suite au renouvellement du poste de %s', $oldBeneficiaryNom));

            if ($oldEquipement) {
                $oldEquipement->setStatut('En service');
            }

            $this->em->persist($newAttribution);

            try {
                $this->auditLogger->log(
                    action: 'EQUIPEMENT_REASSIGNED',
                    level: 'INFO',
                    status: 'SUCCES',
                    username: $operatorUsername,
                    ipAddress: $clientIp,
                    entityType: 'ATTRIBUTION',
                    entityId: (string) $newAttribution->getId(),
                    description: sprintf(
                        'Remplacement matériel : Ancien équipement « %s » transféré à « %s »%s.',
                        $oldEquipement?->getNom() ?? 'Matériel',
                        $newBeneficiary->getNom(),
                        $transferSummary
                    )
                );
            } catch (\Throwable) {}

            return [
                'type' => 'info',
                'message' => sprintf(
                    'L\'ancien équipement « %s » a été transféré à « %s »%s.',
                    $oldEquipement?->getNom() ?? 'Matériel',
                    $newBeneficiary->getNom(),
                    $transferSummary
                ),
            ];
        }

        // Option Remise en stock (par défaut)
        if ($oldEquipement) {
            $oldEquipement->setStatut('En stock');
        }

        try {
            $this->auditLogger->log(
                action: 'EQUIPEMENT_RETURNED_FOR_SWAP',
                level: 'INFO',
                status: 'SUCCES',
                username: $operatorUsername,
                ipAddress: $clientIp,
                entityType: 'ATTRIBUTION',
                entityId: (string) $oldAttribution->getId(),
                description: sprintf(
                    'Remplacement matériel : Ancien équipement « %s » de « %s » remis en stock%s.',
                    $oldEquipement?->getNom() ?? 'Matériel',
                    $oldBeneficiaryNom,
                    $transferSummary
                )
            );
        } catch (\Throwable) {}

        return [
            'type' => 'info',
            'message' => sprintf(
                'L\'ancien équipement « %s » de « %s » a été remis en stock%s.',
                $oldEquipement?->getNom() ?? 'Matériel',
                $oldBeneficiaryNom,
                $transferSummary
            ),
        ];
    }
}
