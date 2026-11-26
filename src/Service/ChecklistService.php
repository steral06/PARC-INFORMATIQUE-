<?php

namespace App\Service;

use App\Entity\ChecklistEtape;
use App\Entity\ChecklistPreparation;
use App\Entity\Equipement;
use App\Entity\User;
use App\Repository\ChecklistEtapeRepository;
use App\Repository\ChecklistPreparationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gère les checklists de préparation/vérification des équipements.
 *
 * Définit les étapes standards selon le type de machine :
 *  - "stock"   → 8 étapes d'installation uniquement
 *  - "service" → sauvegarde du disque externe d'abord, puis les 8 étapes
 */
class ChecklistService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChecklistPreparationRepository $preparationRepo,
        private readonly ChecklistEtapeRepository $etapeRepo,
        private readonly ?AuditLogger $auditLogger = null,
    ) {}

    /* ═══════════════════════════════════════════════════════════
       DÉFINITION DES ÉTAPES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Étapes de sauvegarde (uniquement pour machines "En service").
     * Structure : ['cle', 'libelle', 'description', 'icone']
     */
    private function getEtapesSauvegarde(): array
    {
        return [
            [
                'cle'         => 'sauvegarde_c_bureau',
                'libelle'     => 'Sauvegarde Disque C — Bureau (Desktop)',
                'description' => 'Copier le contenu du Bureau de l\'utilisateur (C:\Users\<nom>\Desktop) vers le disque externe de sauvegarde.',
                'icone'       => 'bi-folder-symlink',
            ],
            [
                'cle'         => 'sauvegarde_c_documents',
                'libelle'     => 'Sauvegarde Disque C — Documents',
                'description' => 'Copier le dossier Documents (C:\Users\<nom>\Documents) vers le disque externe de sauvegarde.',
                'icone'       => 'bi-file-earmark-text',
            ],
            [
                'cle'         => 'sauvegarde_c_autres',
                'libelle'     => 'Sauvegarde Disque C — Autres dossiers utilisateur',
                'description' => 'Copier tout autre dossier personnel de l\'utilisateur dans C:\Users\<nom>\ (Téléchargements, Images, Musique, Vidéos…) vers le disque externe.',
                'icone'       => 'bi-folder2-open',
            ],
            [
                'cle'         => 'sauvegarde_d',
                'libelle'     => 'Sauvegarde Disque D — Tous les dossiers',
                'description' => 'Copier l\'intégralité du contenu du Disque D: vers le disque externe de sauvegarde (D:\\ → Sauvegarde\D\).',
                'icone'       => 'bi-hdd',
            ],
            [
                'cle'         => 'sauvegarde_disques_sup',
                'libelle'     => 'Sauvegarde disques supplémentaires (optionnel)',
                'description' => 'Si la machine possède d\'autres partitions ou disques (E:, F:, etc.), copier leur contenu vers le disque externe. Laisser "N/A" si aucun disque supplémentaire.',
                'icone'       => 'bi-device-hdd',
            ],
        ];
    }

    /**
     * Étapes d'installation (communes aux machines En stock et En service).
     */
    private function getEtapesInstallation(): array
    {
        return [
            [
                'cle'         => 'os',
                'libelle'     => 'Réinstallation du Système d\'exploitation (Windows 11)',
                'description' => 'Installer Windows 11 (standardisation du parc GTAC2A-IARDT). L\'adresse IPv4 et le code inventaire seront renseignés lors de l\'attribution officielle de la machine.',
                'icone'       => 'bi-windows',
            ],
            [
                'cle'         => 'orass',
                'libelle'     => 'ORASS — Netscape + JInit',
                'description' => 'Installer Netscape Navigator (version ORASS) puis JInit. Vérifier que l\'application ORASS se lance correctement et peut atteindre le serveur.',
                'icone'       => 'bi-globe2',
            ],
            [
                'cle'         => 'office',
                'libelle'     => 'Microsoft Office + Activation',
                'description' => 'Installer Microsoft Office (version du parc). Activer la licence avec la clé institutionnelle. Vérifier l\'activation dans Fichier → Compte.',
                'icone'       => 'bi-microsoft',
            ],
            [
                'cle'         => 'winzip',
                'libelle'     => 'WinZip',
                'description' => 'Installer WinZip. Vérifier qu\'il s\'intègre correctement au menu contextuel de l\'Explorateur Windows.',
                'icone'       => 'bi-file-zip',
            ],
            [
                'cle'         => 'winrar',
                'libelle'     => 'WinRAR',
                'description' => 'Installer WinRAR. Vérifier l\'intégration au menu contextuel. Configurer comme archiveur par défaut si nécessaire.',
                'icone'       => 'bi-file-earmark-zip',
            ],
            [
                'cle'         => 'kaspersky',
                'libelle'     => 'Kaspersky + Agent d\'administration',
                'description' => 'Installer Kaspersky Endpoint Security et son agent d\'administration (Network Agent). Vérifier que l\'agent se connecte au serveur de gestion Kaspersky Security Center.',
                'icone'       => 'bi-shield-check',
            ],
            [
                'cle'         => 'chrome',
                'libelle'     => 'Google Chrome',
                'description' => 'Installer Google Chrome (dernière version stable). Définir comme navigateur par défaut si souhaité.',
                'icone'       => 'bi-browser-chrome',
            ],
            [
                'cle'         => 'partition',
                'libelle'     => 'Partitionnement du disque',
                'description' => 'Partitionner le disque dur selon la convention du parc (ex: C: Système ~100 Go, D: Données reste). Utiliser la Gestion des disques Windows ou DiskPart.',
                'icone'       => 'bi-pie-chart',
            ],
        ];
    }

    /* ═══════════════════════════════════════════════════════════
       ACTIONS PRINCIPALES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Crée une nouvelle session de préparation pour un équipement.
     * Abandonne toute session "en cours" existante avant d'en créer une nouvelle.
     */
    public function demarrer(Equipement $equipement, User $technicien): ChecklistPreparation
    {
        // Abandonner toute session en cours existante
        $enCours = $this->preparationRepo->findEnCours($equipement);
        if ($enCours !== null) {
            $enCours->setStatut(ChecklistPreparation::STATUT_ABANDONNEE);
            $enCours->setDateFin(new \DateTimeImmutable());
        }

        $type = ($equipement->getStatut() === 'En service')
            ? ChecklistPreparation::TYPE_SERVICE
            : ChecklistPreparation::TYPE_STOCK;

        $preparation = new ChecklistPreparation();
        $preparation->setEquipement($equipement);
        $preparation->setTechnicien($technicien);
        $preparation->setType($type);

        // Construire la liste des étapes selon le type
        $definitionsEtapes = [];
        $groupe = ChecklistEtape::GROUPE_INSTALLATION;

        if ($type === ChecklistPreparation::TYPE_SERVICE) {
            // Sauvegarde en premier
            foreach ($this->getEtapesSauvegarde() as $def) {
                $definitionsEtapes[] = array_merge($def, ['groupe' => ChecklistEtape::GROUPE_SAUVEGARDE]);
            }
        }

        foreach ($this->getEtapesInstallation() as $def) {
            $definitionsEtapes[] = array_merge($def, ['groupe' => ChecklistEtape::GROUPE_INSTALLATION]);
        }

        foreach ($definitionsEtapes as $ordre => $def) {
            $etape = new ChecklistEtape();
            $etape->setCle($def['cle']);
            $etape->setLibelle($def['libelle']);
            $etape->setDescription($def['description']);
            $etape->setIcone($def['icone']);
            $etape->setGroupe($def['groupe']);
            $etape->setOrdre($ordre);
            $preparation->addEtape($etape);
        }

        $this->em->persist($preparation);
        $this->em->flush();

        return $preparation;
    }

    /**
     * Valide ou marque en erreur une étape.
     * Retourne true si toutes les étapes sont désormais validées (checklist complète).
     */
    public function validerEtape(
        ChecklistEtape $etape,
        string $statut,         // 'ok', 'ko' ou 'en_attente' (réouverture)
        ?string $observations = null,
        ?bool $sauvegardeSupplementaire = null
    ): bool {
        $etape->setStatut($statut);

        if ($statut === ChecklistEtape::STATUT_EN_ATTENTE) {
            // Réouverture : effacer la date de validation
            $etape->setDateValidation(null);
            // Remettre la préparation en cours si elle était terminée
            $preparation = $etape->getPreparation();
            if ($preparation->getStatut() !== ChecklistPreparation::STATUT_EN_COURS) {
                $preparation->setStatut(ChecklistPreparation::STATUT_EN_COURS);
                $preparation->setDateFin(null);
            }
            $this->em->flush();
            return false;
        }

        $etape->setDateValidation(new \DateTimeImmutable());
        if ($observations !== null) {
            $etape->setObservations($observations);
        }

        $preparation = $etape->getPreparation();

        // Vérifier si toutes les étapes sont validées
        $toutesValidees = true;
        foreach ($preparation->getEtapes() as $e) {
            if ($e->isEnAttente()) {
                $toutesValidees = false;
                break;
            }
        }

        if ($toutesValidees) {
            $preparation->setStatut(ChecklistPreparation::STATUT_TERMINEE);
            $preparation->setDateFin(new \DateTimeImmutable());
            $this->appliquerMiseAJourApresPreparation($preparation);
        }

        $this->em->flush();

        return $toutesValidees;
    }

    /**
     * Ajoute une étape de sauvegarde supplémentaire (disque additionnel).
     */
    public function ajouterSauvegardeSupplementaire(
        ChecklistPreparation $preparation,
        string $libelleDisque
    ): ChecklistEtape {
        // Trouver le dernier ordre des étapes de sauvegarde
        $maxOrdre = 0;
        $ordreInstallation = PHP_INT_MAX;

        foreach ($preparation->getEtapes() as $etape) {
            if ($etape->isSauvegarde()) {
                $maxOrdre = max($maxOrdre, $etape->getOrdre());
            } elseif ($etape->isInstallation()) {
                $ordreInstallation = min($ordreInstallation, $etape->getOrdre());
            }
        }

        // Décaler les étapes d'installation pour insérer la nouvelle sauvegarde
        foreach ($preparation->getEtapes() as $etape) {
            if ($etape->isInstallation()) {
                $etape->setOrdre($etape->getOrdre() + 1);
            }
        }

        $nouvelleEtape = new ChecklistEtape();
        $nouvelleEtape->setCle('sauvegarde_sup_' . uniqid());
        $nouvelleEtape->setLibelle('Sauvegarde disque supplémentaire — ' . $libelleDisque);
        $nouvelleEtape->setDescription('Copier le contenu de ' . $libelleDisque . ' vers le disque externe de sauvegarde.');
        $nouvelleEtape->setIcone('bi-device-hdd');
        $nouvelleEtape->setGroupe(ChecklistEtape::GROUPE_SAUVEGARDE);
        $nouvelleEtape->setOrdre($maxOrdre + 1);

        $preparation->addEtape($nouvelleEtape);
        $this->em->flush();

        return $nouvelleEtape;
    }

    /**
     * Termine ou abandonne manuellement une préparation.
     */
    public function cloturer(ChecklistPreparation $preparation, string $statut, ?string $observations = null): void
    {
        $preparation->setStatut($statut);
        $preparation->setDateFin(new \DateTimeImmutable());
        if ($observations) {
            $preparation->setObservations($observations);
        }
        if ($statut === ChecklistPreparation::STATUT_TERMINEE) {
            $this->appliquerMiseAJourApresPreparation($preparation);
        }
        $this->em->flush();
    }

    /**
     * Applique les mises à jour automatiques sur l'équipement une fois la préparation terminée avec succès.
     * Règle GTAC2A : Si la machine a un OS Windows 10, basculer automatiquement en Windows 11.
     */
    private function appliquerMiseAJourApresPreparation(ChecklistPreparation $preparation): void
    {
        $equipement = $preparation->getEquipement();
        if (!$equipement) {
            return;
        }

        $currentOs = (string) $equipement->getSystemeExploitation();

        // Si l'OS est Windows 10 (ex: "Windows 10", "Windows 10 Pro", etc.), basculer automatiquement en Windows 11
        if (preg_match('/(?:windows|win)\s*10/i', $currentOs)) {
            $newOs = preg_replace('/(?:windows|win)\s*10/i', 'Windows 11', $currentOs);
            $equipement->setSystemeExploitation($newOs);

            if ($this->auditLogger) {
                try {
                    $this->auditLogger->log(
                        action: 'EQUIPEMENT_OS_UPGRADED',
                        level: 'INFO',
                        status: 'SUCCES',
                        username: $preparation->getTechnicien()?->getUserIdentifier() ?? 'Technicien',
                        ipAddress: null,
                        entityType: 'EQUIPEMENT',
                        entityId: (string) $equipement->getId(),
                        description: sprintf(
                            'Migration automatique OS : "%s" mis à niveau en "%s" suite à la préparation terminée avec succès.',
                            $currentOs,
                            $newOs
                        )
                    );
                } catch (\Throwable) {}
            }
        } elseif ($preparation->getType() === ChecklistPreparation::TYPE_STOCK && empty(trim($currentOs))) {
            $equipement->setSystemeExploitation('Windows 11');
        }
    }

    /**
     * Supprime une préparation et ses étapes associées.
     */
    public function supprimer(ChecklistPreparation $preparation): void
    {
        $this->em->remove($preparation);
        $this->em->flush();
    }
}

