<?php

namespace App\Controller;

use App\Entity\Attribution;
use App\Entity\CategorieEquipement;
use App\Entity\Composant;
use App\Entity\Equipement;
use App\Entity\Lieu;
use App\Entity\TicketMaintenance;
use App\Service\ExcelReportGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/rapports')]
class RapportController extends AbstractController
{
    public const EQUIPEMENT_COLUMNS = [
        'nom'             => ['label' => 'Désignation de l\'équipement', 'type' => 'string',   'default' => true],
        'marque'          => ['label' => 'Marque / Fabricant',           'type' => 'string',   'default' => true],
        'modele'          => ['label' => 'Modèle / Réf.',                'type' => 'string',   'default' => true],
        'numeroSerie'     => ['label' => 'Numéro de série (S/N)',        'type' => 'string',   'default' => true],
        'adresseIp'       => ['label' => 'Adresse IP Réseau',            'type' => 'string',   'default' => true],
        'systemeExploitation' => ['label' => 'Système d\'exploitation (OS)', 'type' => 'string', 'default' => true],
        'tagInventaire'   => ['label' => 'Code inventaire GTAC2A-IARDT', 'type' => 'string',   'default' => true],
        'categorie'       => ['label' => 'Catégorie / Famille',          'type' => 'string',   'default' => true],
        'lieu'            => ['label' => 'Emplacement / Salle',          'type' => 'string',   'default' => true],
        'statut'          => ['label' => 'Statut opérationnel',          'type' => 'badge',    'default' => true],
        'dateAcquisition' => ['label' => 'Date d\'acquisition',          'type' => 'date',     'default' => true],
        'ageAnnees'       => ['label' => 'Âge constaté (ans)',           'type' => 'number',   'default' => true],
        'vetuste'         => ['label' => 'Niveau de vétusté',            'type' => 'badge',    'default' => true],
        'finGarantie'     => ['label' => 'Date fin de garantie',         'type' => 'date',     'default' => false],
        'statutGarantie'  => ['label' => 'Garantie (Valide/Expirée)',    'type' => 'badge',    'default' => false],
        'prixAchat'       => ['label' => 'Prix d\'achat (FCFA)',         'type' => 'currency', 'default' => true],
        'beneficiaire'    => ['label' => 'Bénéficiaire actuel',          'type' => 'string',   'default' => true],
        'dateAjout'       => ['label' => 'Date d\'enregistrement',       'type' => 'date',     'default' => false],
        'notes'           => ['label' => 'Spécifications & notes',       'type' => 'string',   'default' => false],
    ];

    public const COMPOSANT_COLUMNS = [
        'nom'             => ['label' => 'Désignation de la pièce',     'type' => 'string',   'default' => true],
        'type'            => ['label' => 'Type de composant',           'type' => 'string',   'default' => true],
        'numeroSerie'     => ['label' => 'Numéro de série',             'type' => 'string',   'default' => true],
        'quantite'        => ['label' => 'Quantité disponible',         'type' => 'number',   'default' => true],
        'statut'          => ['label' => 'Statut opérationnel',          'type' => 'badge',    'default' => true],
        'equipement'      => ['label' => 'Machine associée',            'type' => 'string',   'default' => true],
        'lieu'            => ['label' => 'Lieu de stockage',            'type' => 'string',   'default' => true],
        'dateAcquisition' => ['label' => 'Date d\'entrée en stock',      'type' => 'date',     'default' => false],
        'ageAnnees'       => ['label' => 'Âge (ans)',                   'type' => 'number',   'default' => false],
        'prixAchat'       => ['label' => 'Prix unitaire (FCFA)',        'type' => 'currency', 'default' => true],
        'valeurTotale'    => ['label' => 'Valeur totale (FCFA)',        'type' => 'currency', 'default' => true],
        'notes'           => ['label' => 'Spécifications',              'type' => 'string',   'default' => false],
    ];

    public const ATTRIBUTION_COLUMNS = [
        'equipement'          => ['label' => 'Matériel attribué',     'type' => 'string', 'default' => true],
        'numeroSerie'         => ['label' => 'N° de série',           'type' => 'string', 'default' => true],
        'beneficiaireNom'     => ['label' => 'Nom du collaborateur',  'type' => 'string', 'default' => true],
        'beneficiaireService' => ['label' => 'Service / Direction',   'type' => 'string', 'default' => true],
        'dateDebut'           => ['label' => 'Date de dotation',      'type' => 'date',   'default' => true],
        'dateFinPrevue'       => ['label' => 'Retour prévu',          'type' => 'date',   'default' => true],
        'dateRestitution'     => ['label' => 'Date de restitution',   'type' => 'date',   'default' => true],
        'statut'              => ['label' => 'Statut du prêt',        'type' => 'badge',  'default' => true],
        'motif'               => ['label' => 'Motif / Justification', 'type' => 'string', 'default' => false],
    ];

    public const MAINTENANCE_COLUMNS = [
        'id'             => ['label' => 'Réf. Ticket',               'type' => 'string',   'default' => true],
        'equipement'     => ['label' => 'Matériel concerné',         'type' => 'string',   'default' => true],
        'titre'          => ['label' => 'Intitulé incident',         'type' => 'string',   'default' => true],
        'priorite'       => ['label' => 'Priorité',                  'type' => 'badge',    'default' => true],
        'statut'         => ['label' => 'Statut d\'intervention',    'type' => 'badge',    'default' => true],
        'technicien'     => ['label' => 'Technicien / Prestataire',  'type' => 'string',   'default' => true],
        'dateCreation'   => ['label' => 'Date déclaration',          'type' => 'date',     'default' => true],
        'dateResolution' => ['label' => 'Date clôture',              'type' => 'date',     'default' => true],
        'cout'           => ['label' => 'Coût réparation (FCFA)',    'type' => 'currency', 'default' => true],
        'solution'       => ['label' => 'Solution technique',        'type' => 'string',   'default' => false],
    ];

    /**
     * Structure des colonnes du Bilan Global (Tableau unique 360° du Parc)
     * Regroupées logiquement : Équipement, Dotation/Utilisateur, Composants, Maintenance
     */
    public const BILAN_COLUMNS_GROUPS = [
        'equipement' => [
            'title'   => 'Équipement & Matériel',
            'icon'    => 'bi-pc-display-horizontal',
            'color'   => '#E47000',
            'columns' => [
                'nom'             => ['label' => 'Désignation de l\'équipement', 'type' => 'string',   'default' => true],
                'modele'          => ['label' => 'Modèle / Référence exacte',     'type' => 'string',   'default' => true],
                'marque'          => ['label' => 'Marque / Fabricant',           'type' => 'string',   'default' => true],
                'numeroSerie'     => ['label' => 'Numéro de série (S/N)',        'type' => 'string',   'default' => true],
                'adresseIp'           => ['label' => 'Adresse IP Réseau',            'type' => 'string',   'default' => true],
                'systemeExploitation' => ['label' => 'Système d\'exploitation (OS)', 'type' => 'string',   'default' => true],
                'tagInventaire'       => ['label' => 'Code inventaire GTAC2A-IARDT', 'type' => 'string',   'default' => true],
                'categorie'       => ['label' => 'Catégorie / Famille',          'type' => 'string',   'default' => true],
                'lieu'            => ['label' => 'Emplacement / Salle',          'type' => 'string',   'default' => true],
                'statut'          => ['label' => 'Statut opérationnel',          'type' => 'badge',    'default' => true],
                'dateAcquisition' => ['label' => 'Date d\'acquisition',          'type' => 'date',     'default' => false],
                'ageAnnees'       => ['label' => 'Âge constaté (ans)',           'type' => 'number',   'default' => false],
                'vetuste'         => ['label' => 'Niveau de vétusté',            'type' => 'badge',    'default' => false],
                'finGarantie'     => ['label' => 'Date fin de garantie',         'type' => 'date',     'default' => false],
                'statutGarantie'  => ['label' => 'Garantie (Valide/Expirée)',    'type' => 'badge',    'default' => false],
                'prixAchat'       => ['label' => 'Prix d\'achat (FCFA)',         'type' => 'currency', 'default' => true],
                'notes'           => ['label' => 'Spécifications & notes',       'type' => 'string',   'default' => false],
            ],
        ],
        'dotation' => [
            'title'   => 'Dotation & Utilisateur (Celui qui l\'utilise)',
            'icon'    => 'bi-person-badge',
            'color'   => '#198754',
            'columns' => [
                'beneficiaireNom'          => ['label' => 'Utilisateur actuel (Bénéficiaire)', 'type' => 'string', 'default' => true],
                'beneficiaireService'      => ['label' => 'Service / Direction',              'type' => 'string', 'default' => true],
                'dateDebutAttribution'     => ['label' => 'Date d\'attribution',               'type' => 'date',   'default' => false],
                'dateFinPrevueAttribution' => ['label' => 'Date de retour prévue',             'type' => 'date',   'default' => false],
                'statutAttribution'        => ['label' => 'Statut du prêt',                    'type' => 'badge',  'default' => false],
                'motifAttribution'         => ['label' => 'Motif d\'attribution',              'type' => 'string', 'default' => false],
            ],
        ],
        'composants' => [
            'title'   => 'Composants & Pièces associés',
            'icon'    => 'bi-cpu',
            'color'   => '#0d6efd',
            'columns' => [
                'composantsDetails' => ['label' => 'Composants montés (RAM, SSD...)', 'type' => 'string',   'default' => true],
                'composantsCount'   => ['label' => 'Nombre de composants montés',     'type' => 'number',   'default' => false],
                'composantsValeur'  => ['label' => 'Valeur pièces montées (FCFA)',     'type' => 'currency', 'default' => false],
            ],
        ],
        'maintenance' => [
            'title'   => 'Maintenances & SAV',
            'icon'    => 'bi-tools',
            'color'   => '#dc3545',
            'columns' => [
                'ticketsCount'            => ['label' => 'Nombre total d\'incidents',     'type' => 'number',   'default' => false],
                'dernierTicketTitre'      => ['label' => 'Dernier incident / Panne',      'type' => 'string',   'default' => false],
                'dernierTicketStatut'     => ['label' => 'Statut dernière intervention',  'type' => 'badge',    'default' => false],
                'dernierTicketTechnicien' => ['label' => 'Technicien / Prestataire',      'type' => 'string',   'default' => false],
                'coutTotalMaintenance'    => ['label' => 'Coût cumulé réparation (FCFA)', 'type' => 'currency', 'default' => false],
            ],
        ],
    ];

    /**
     * Retourne la liste à plat de toutes les colonnes disponibles pour le Bilan Global
     *
     * @return array<string, array{label: string, type: string, default: bool}>
     */
    public static function getBilanFlatColumns(): array
    {
        $all = [];
        foreach (self::BILAN_COLUMNS_GROUPS as $group) {
            foreach ($group['columns'] as $key => $conf) {
                $all[$key] = $conf;
            }
        }
        return $all;
    }

    #[Route('', name: 'app_rapport_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $scope = $request->query->get('scope', 'equipements');
        $statutFilter = $request->query->get('statut', 'all');
        $categorieFilter = $request->query->get('categorie_id', 'all');
        $lieuFilter = $request->query->get('lieu_id', 'all');
        $ageFilter = $request->query->get('age', 'all');

        $equipementRepo = $em->getRepository(Equipement::class);
        $composantRepo = $em->getRepository(Composant::class);
        $ticketRepo = $em->getRepository(TicketMaintenance::class);
        $categorieRepo = $em->getRepository(CategorieEquipement::class);
        $lieuRepo = $em->getRepository(Lieu::class);

        /** @var Equipement[] $equipements */
        $equipements = $equipementRepo->findAll();
        /** @var Composant[] $composants */
        $composants = $composantRepo->findAll();
        /** @var TicketMaintenance[] $tickets */
        $tickets = $ticketRepo->findAll();
        $categories = $categorieRepo->findAll();
        $lieux = $lieuRepo->findAll();

        // 1. Calcul financier et volumétrie globale du parc
        $valeurEquipements = 0.0;
        $totalEquipements = count($equipements);
        $equipementsRecents = 0;
        $equipementsVieillissants = 0;
        $equipementsObsoletes = 0;
        $statutCounts = ['En service' => 0, 'En stock' => 0, 'En panne' => 0, 'Prêté' => 0, 'Au rebut' => 0];

        foreach ($equipements as $eq) {
            $valeurEquipements += ($eq->getPrixAchat() ?? 0.0);
            $st = $eq->getStatut() ?? 'En service';
            if (isset($statutCounts[$st])) {
                $statutCounts[$st]++;
            } else {
                $statutCounts[$st] = 1;
            }

            if ($eq->isObsolete()) {
                $equipementsObsoletes++;
            } elseif ($eq->isVieillissant()) {
                $equipementsVieillissants++;
            } elseif ($eq->getDateAcquisition() !== null) {
                $equipementsRecents++;
            }
        }

        $valeurComposants = 0.0;
        $totalComposantsPieces = 0;
        foreach ($composants as $c) {
            $valeurComposants += (($c->getPrixAchat() ?? 0.0) * $c->getQuantite());
            $totalComposantsPieces += $c->getQuantite();
        }

        $coutTotalMaintenance = 0.0;
        $ticketsResolus = 0;
        $ticketsEnCours = 0;
        foreach ($tickets as $t) {
            $coutTotalMaintenance += ($t->getCout() ?? 0.0);
            if ($t->getStatut() === 'Résolu') {
                $ticketsResolus++;
            } else {
                $ticketsEnCours++;
            }
        }

        $tauxDisponibilite = $totalEquipements > 0
            ? round(($statutCounts['En service'] / $totalEquipements) * 100, 1)
            : 100.0;

        $isBilan = ($scope === 'bilan');
        $rawColumnsParam = $request->query->all('columns');

        if ($isBilan) {
            // Scope Bilan Global : Tableau unique intégrant équipement, utilisateur/dotation, composants, maintenance
            $allAvailableColumns = self::getBilanFlatColumns();

            if (empty($rawColumnsParam)) {
                $selectedColumns = [];
                foreach ($allAvailableColumns as $key => $conf) {
                    if ($conf['default'] ?? false) {
                        $selectedColumns[] = $key;
                    }
                }
            } else {
                // Aplatir si nécessaire
                $flat = [];
                foreach ((array) $rawColumnsParam as $val) {
                    if (is_array($val)) {
                        $flat = array_merge($flat, $val);
                    } else {
                        $flat[] = $val;
                    }
                }
                $selectedColumns = array_intersect($flat, array_keys($allAvailableColumns));
            }

            $previewData = $this->fetchBilanMasterData($selectedColumns, $em, 50, $statutFilter, $categorieFilter, $lieuFilter, $ageFilter);
        } else {
            // Scope unitaire standard
            $allAvailableColumns = match ($scope) {
                'composants'   => self::COMPOSANT_COLUMNS,
                'attributions' => self::ATTRIBUTION_COLUMNS,
                'maintenances' => self::MAINTENANCE_COLUMNS,
                default        => self::EQUIPEMENT_COLUMNS,
            };

            if (empty($rawColumnsParam)) {
                $selectedColumns = [];
                foreach ($allAvailableColumns as $key => $conf) {
                    if ($conf['default'] ?? false) {
                        $selectedColumns[] = $key;
                    }
                }
            } else {
                $flat = is_array($rawColumnsParam) && isset($rawColumnsParam[$scope])
                    ? $rawColumnsParam[$scope]
                    : $rawColumnsParam;
                $selectedColumns = array_intersect((array) $flat, array_keys($allAvailableColumns));
            }

            $previewData = $this->fetchReportData($scope, $selectedColumns, $statutFilter, $categorieFilter, $lieuFilter, $ageFilter, $em, 50);
        }

        return $this->render('rapport/index.html.twig', [
            // KPIs
            'totalEquipements'         => $totalEquipements,
            'valeurEquipements'        => $valeurEquipements,
            'valeurComposants'         => $valeurComposants,
            'valeurTotaleParc'         => $valeurEquipements + $valeurComposants,
            'coutTotalMaintenance'     => $coutTotalMaintenance,
            'tauxDisponibilite'        => $tauxDisponibilite,
            'statutCounts'             => $statutCounts,
            'equipementsRecents'       => $equipementsRecents,
            'equipementsVieillissants' => $equipementsVieillissants,
            'equipementsObsoletes'     => $equipementsObsoletes,
            'totalComposantsPieces'    => $totalComposantsPieces,
            'ticketsResolus'           => $ticketsResolus,
            'ticketsEnCours'           => $ticketsEnCours,
            'categories'               => $categories,
            'lieux'                    => $lieux,

            // Custom Report Generator
            'current_scope'            => $scope,
            'is_bilan'                 => $isBilan,
            'bilan_groups'             => self::BILAN_COLUMNS_GROUPS,
            'available_columns'        => $allAvailableColumns,
            'selected_columns'         => $selectedColumns,
            'current_statut'           => $statutFilter,
            'current_categorie'        => $categorieFilter,
            'current_lieu'             => $lieuFilter,
            'current_age'              => $ageFilter,
            'preview_rows'             => $previewData['rows'],
            'preview_columns'          => $previewData['columns'],
            'total_matching_rows'      => $previewData['total_count'],
            'dateGeneration'           => new \DateTime(),
        ]);
    }

    #[Route('/export', name: 'app_rapport_export', methods: ['GET', 'POST'])]
    public function export(Request $request, ExcelReportGenerator $excelGenerator, EntityManagerInterface $em): Response
    {
        $scope = $request->query->get('scope', $request->request->get('scope', 'equipements'));
        $format = $request->query->get('format', $request->request->get('format', 'excel'));
        $statutFilter = $request->query->get('statut', $request->request->get('statut', 'all'));
        $categorieFilter = $request->query->get('categorie_id', $request->request->get('categorie_id', 'all'));
        $lieuFilter = $request->query->get('lieu_id', $request->request->get('lieu_id', 'all'));
        $ageFilter = $request->query->get('age', $request->request->get('age', 'all'));
        $dateStr = date('Y-m-d_His');

        $rawCols = $request->query->all('columns');
        if (empty($rawCols)) {
            $rawCols = (array) $request->request->all('columns');
        }

        if ($scope === 'bilan') {
            // ── Bilan Global : Fichier unique avec toutes les colonnes cochées ──────
            $allAvailableColumns = self::getBilanFlatColumns();

            if (empty($rawCols)) {
                $selectedColumns = [];
                foreach ($allAvailableColumns as $key => $conf) {
                    if ($conf['default'] ?? false) {
                        $selectedColumns[] = $key;
                    }
                }
            } else {
                $flat = [];
                foreach ((array) $rawCols as $val) {
                    if (is_array($val)) {
                        $flat = array_merge($flat, $val);
                    } else {
                        $flat[] = $val;
                    }
                }
                $selectedColumns = array_intersect($flat, array_keys($allAvailableColumns));
            }

            $data = $this->fetchBilanMasterData($selectedColumns, $em, null, $statutFilter, $categorieFilter, $lieuFilter, $ageFilter);
            $columns = $data['columns'];
            $rows = $data['rows'];

            $columnTypes = [];
            foreach ($selectedColumns as $colKey) {
                $columnTypes[$colKey] = $allAvailableColumns[$colKey]['type'] ?? 'string';
            }

            if ($format === 'csv') {
                $content = $excelGenerator->generateCsv($columns, $rows);
                $filename = "bilan_global_gtac2a_{$dateStr}.csv";
                $response = new Response($content);
                $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
                $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");
                return $response;
            }

            $content = $excelGenerator->generateExcelXml('Bilan Global GTAC2A-IARDT', $columns, $rows, $columnTypes);
            $filename = "bilan_global_gtac2a_{$dateStr}.xls";
            $response = new Response($content);
            $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
            $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        }

        // ── Export standard (scope unique) ────────────────────────────────────
        $allAvailableColumns = match ($scope) {
            'composants'   => self::COMPOSANT_COLUMNS,
            'attributions' => self::ATTRIBUTION_COLUMNS,
            'maintenances' => self::MAINTENANCE_COLUMNS,
            default        => self::EQUIPEMENT_COLUMNS,
        };

        if (empty($rawCols)) {
            $selectedColumns = [];
            foreach ($allAvailableColumns as $key => $conf) {
                if ($conf['default'] ?? false) {
                    $selectedColumns[] = $key;
                }
            }
        } else {
            $flat = is_array($rawCols) && isset($rawCols[$scope]) ? $rawCols[$scope] : $rawCols;
            $selectedColumns = array_intersect((array) $flat, array_keys($allAvailableColumns));
        }

        $data = $this->fetchReportData(
            $scope,
            $selectedColumns,
            $statutFilter,
            $categorieFilter,
            $lieuFilter,
            $ageFilter,
            $em,
            null
        );

        $columns = $data['columns'];
        $rows = $data['rows'];
        $columnTypes = [];
        foreach ($selectedColumns as $colKey) {
            $columnTypes[$colKey] = $allAvailableColumns[$colKey]['type'] ?? 'string';
        }

        $sheetTitle = match ($scope) {
            'composants'   => 'Composants GTAC2A-IARDT',
            'attributions' => 'Attributions GTAC2A-IARDT',
            'maintenances' => 'Maintenances GTAC2A-IARDT',
            default        => 'Équipements GTAC2A-IARDT',
        };

        if ($format === 'csv') {
            $content = $excelGenerator->generateCsv($columns, $rows);
            $filename = sprintf('rapport_gtac2a_%s_%s.csv', $scope, $dateStr);
            $response = new Response($content);
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
            return $response;
        }

        $content = $excelGenerator->generateExcelXml($sheetTitle, $columns, $rows, $columnTypes);
        $filename = sprintf('rapport_gtac2a_%s_%s.xls', $scope, $dateStr);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * Génère la table de synthèse 360° du Parc pour le Bilan Global :
     * Chaque ligne est un matériel du parc, enrichi de son utilisateur (dotation),
     * de ses composants montés et de son historique de maintenance.
     *
     * @param string[] $selectedColumns
     * @return array{columns: array<string, string>, rows: array<int, array<string, mixed>>, total_count: int}
     */
    private function fetchBilanMasterData(
        array $selectedColumns,
        EntityManagerInterface $em,
        ?int $limit = null,
        string $statutFilter = 'all',
        string $categorieFilter = 'all',
        string $lieuFilter = 'all',
        string $ageFilter = 'all'
    ): array {
        $allCols = self::getBilanFlatColumns();
        $columns = [];
        foreach ($selectedColumns as $colKey) {
            if (isset($allCols[$colKey])) {
                $columns[$colKey] = $allCols[$colKey]['label'];
            }
        }

        // 1. Charger tous les équipements avec catégorie et lieu en appliquant les filtres
        $qb = $em->getRepository(Equipement::class)
            ->createQueryBuilder('e')
            ->leftJoin('e.categorie', 'c')->addSelect('c')
            ->leftJoin('e.lieu', 'l')->addSelect('l')
            ->orderBy('e.dateAjout', 'DESC');

        if ($statutFilter !== 'all') {
            $qb->andWhere('e.statut = :statut')->setParameter('statut', $statutFilter);
        }
        if ($categorieFilter !== 'all') {
            $qb->andWhere('c.id = :catId')->setParameter('catId', $categorieFilter);
        }
        if ($lieuFilter !== 'all') {
            $qb->andWhere('l.id = :lieuId')->setParameter('lieuId', $lieuFilter);
        }

        /** @var Equipement[] $equipements */
        $equipements = $qb->getQuery()->getResult();

        if ($ageFilter === 'vieillissant') {
            $equipements = array_filter($equipements, fn(Equipement $e) => $e->isVieillissant());
        } elseif ($ageFilter === 'obsolete') {
            $equipements = array_filter($equipements, fn(Equipement $e) => $e->isObsolete());
        }

        // 2. Pré-charger les attributions
        /** @var Attribution[] $allAttributions */
        $allAttributions = $em->getRepository(Attribution::class)->findAll();
        $attributionMap = [];
        foreach ($allAttributions as $att) {
            $eq = $att->getEquipement();
            if (!$eq) continue;
            $eqId = $eq->getId();
            // Priorité absolue aux attributions actives (En cours ou Prolongé)
            if (in_array($att->getStatut(), ['En cours', 'Prolongé'])) {
                $attributionMap[$eqId] = $att;
            } elseif (!isset($attributionMap[$eqId])) {
                $attributionMap[$eqId] = $att;
            }
        }

        // 3. Pré-charger les composants montés et en stock
        /** @var Composant[] $allComposants */
        $allComposants = $em->getRepository(Composant::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.lieu', 'l')->addSelect('l')
            ->getQuery()->getResult();

        $composantsByEquipement = [];
        $unmountedComposants = [];
        foreach ($allComposants as $comp) {
            $eq = $comp->getEquipement();
            if ($eq) {
                $composantsByEquipement[$eq->getId()][] = $comp;
            } else {
                $unmountedComposants[] = $comp;
            }
        }

        // 4. Pré-charger les tickets de maintenance
        /** @var TicketMaintenance[] $allTickets */
        $allTickets = $em->getRepository(TicketMaintenance::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.equipement', 'e')->addSelect('e')
            ->orderBy('t.dateCreation', 'DESC')
            ->getQuery()->getResult();

        $ticketsByEquipement = [];
        foreach ($allTickets as $t) {
            $eq = $t->getEquipement();
            if ($eq) {
                $ticketsByEquipement[$eq->getId()][] = $t;
            }
        }

        $rows = [];

        // Lignes pour chaque équipement du parc
        foreach ($equipements as $eq) {
            $eqId = $eq->getId();
            $att = $attributionMap[$eqId] ?? null;
            $eqComps = $composantsByEquipement[$eqId] ?? [];
            $eqTickets = $ticketsByEquipement[$eqId] ?? [];

            // Synthèse composants montés
            $compDetailsList = [];
            $compValeurTotale = 0.0;
            foreach ($eqComps as $c) {
                $qtyStr = $c->getQuantite() > 1 ? ' (' . $c->getQuantite() . 'x)' : '';
                $compDetailsList[] = $c->getNom() . $qtyStr;
                $compValeurTotale += (($c->getPrixAchat() ?? 0) * $c->getQuantite());
            }
            $composantsStr = !empty($compDetailsList) ? implode(', ', $compDetailsList) : 'Aucun';

            // Synthèse maintenance
            $latestTicket = $eqTickets[0] ?? null;
            $coutTotalMaint = 0.0;
            foreach ($eqTickets as $t) {
                $coutTotalMaint += ($t->getCout() ?? 0.0);
            }

            // Statuts vétusté & garantie
            $isGarantieValide = $eq->getFinGarantie() && $eq->getFinGarantie()->format('Y-m-d') >= date('Y-m-d');
            $vetusteLabel = $eq->isObsolete() ? 'Obsolète (> 5 ans)' : ($eq->isVieillissant() ? 'Vieillissant (3-5 ans)' : ($eq->getDateAcquisition() ? 'Récent (< 3 ans)' : 'Non défini'));

            $row = [];
            foreach ($selectedColumns as $col) {
                $row[$col] = match ($col) {
                    // Équipement & Matériel
                    'nom'                      => $eq->getNom(),
                    'modele'                   => $eq->getModele() ?: '-',
                    'marque'                   => $eq->getMarque() ?: '-',
                    'numeroSerie'              => $eq->getNumeroSerie() ?: '-',
                    'adresseIp'                => $eq->getAdresseIp() ?: '-',
                    'systemeExploitation'      => $eq->getSystemeExploitation() ?: '-',
                    'tagInventaire'            => $eq->getTagInventaire() ?: '-',
                    'categorie'                => $eq->getCategorie()?->getNomCat() ?: 'Non classé',
                    'lieu'                     => $eq->getLieu()?->getNomLieu() ?: 'Non assigné',
                    'statut'                   => $eq->getStatut() ?: 'En service',
                    'dateAcquisition'          => $eq->getDateAcquisition()?->format('d/m/Y') ?: '-',
                    'ageAnnees'                => $eq->getAgeAnnees() !== null ? $eq->getAgeAnnees() : '-',
                    'vetuste'                  => $vetusteLabel,
                    'finGarantie'              => $eq->getFinGarantie()?->format('d/m/Y') ?: '-',
                    'statutGarantie'           => $eq->getFinGarantie() ? ($isGarantieValide ? 'Valide' : 'Expirée') : 'N/C',
                    'prixAchat'                => $eq->getPrixAchat() ?? 0,
                    'notes'                    => $eq->getNotes() ?: '-',

                    // Dotation & Bénéficiaire (Celui qui l'utilise)
                    'beneficiaireNom'          => $att?->getBeneficiaireNom() ?: ($eq->getStatut() === 'En service' ? 'Non renseigné' : '-'),
                    'beneficiaireService'      => $att?->getBeneficiaireService() ?: ($att ? 'GTAC2A-IARDT' : '-'),
                    'dateDebutAttribution'     => $att?->getDateDebut()?->format('d/m/Y') ?: '-',
                    'dateFinPrevueAttribution' => $att?->getDateFinPrevue()?->format('d/m/Y') ?: '-',
                    'statutAttribution'        => $att?->getStatut() ?: ($eq->getStatut() === 'En stock' ? 'Disponible' : '-'),
                    'motifAttribution'         => $att?->getMotif() ?: '-',

                    // Composants & Pièces
                    'composantsDetails'        => $composantsStr,
                    'composantsCount'          => count($eqComps),
                    'composantsValeur'         => $compValeurTotale,

                    // Maintenances & SAV
                    'ticketsCount'             => count($eqTickets),
                    'dernierTicketTitre'       => $latestTicket?->getTitre() ?: 'Aucun incident',
                    'dernierTicketStatut'      => $latestTicket?->getStatut() ?: 'Aucun',
                    'dernierTicketTechnicien'  => $latestTicket?->getTechnicien() ?: '-',
                    'coutTotalMaintenance'     => $coutTotalMaint,

                    default                    => '-',
                };
            }
            $rows[] = $row;
        }

        // Lignes pour les pièces détachées en stock non montées (si compatibles avec les filtres)
        if ($categorieFilter === 'all') {
            foreach ($unmountedComposants as $c) {
                if ($statutFilter !== 'all' && $c->getStatut() !== $statutFilter) {
                    continue;
                }
                if ($lieuFilter !== 'all' && (string) $c->getLieu()?->getId() !== (string) $lieuFilter) {
                    continue;
                }
                if ($ageFilter === 'vieillissant' && !$c->isVieillissant()) {
                    continue;
                }
                if ($ageFilter === 'obsolete' && !$c->isObsolete()) {
                    continue;
                }

                $row = [];
            foreach ($selectedColumns as $col) {
                $row[$col] = match ($col) {
                    'nom'                      => '[Stock] ' . $c->getNom(),
                    'modele'                   => $c->getType() ?: '-',
                    'marque'                   => '-',
                    'numeroSerie'              => $c->getNumeroSerie() ?: '-',
                    'adresseIp'                => '-',
                    'systemeExploitation'      => '-',
                    'tagInventaire'            => '-',
                    'categorie'                => 'Pièce détachée',
                    'lieu'                     => $c->getLieu()?->getNomLieu() ?: 'Stock DSI',
                    'statut'                   => $c->getStatut() ?: 'En stock',
                    'dateAcquisition'          => $c->getDateAcquisition()?->format('d/m/Y') ?: '-',
                    'ageAnnees'                => $c->getAgeAnnees() !== null ? $c->getAgeAnnees() : '-',
                    'vetuste'                  => $c->isObsolete() ? 'Obsolète (> 5 ans)' : ($c->isVieillissant() ? 'Vieillissant (3-5 ans)' : 'Récent (< 3 ans)'),
                    'finGarantie'              => '-',
                    'statutGarantie'           => 'N/C',
                    'prixAchat'                => $c->getPrixAchat() ?? 0,
                    'notes'                    => $c->getNotes() ?: '-',
                    'beneficiaireNom'          => 'En stock DSI (Non attribué)',
                    'beneficiaireService'      => '-',
                    'dateDebutAttribution'     => '-',
                    'dateFinPrevueAttribution' => '-',
                    'statutAttribution'        => 'En stock',
                    'motifAttribution'         => '-',
                    'composantsDetails'        => 'Quantité : ' . $c->getQuantite(),
                    'composantsCount'          => $c->getQuantite(),
                    'composantsValeur'         => ($c->getPrixAchat() ?? 0) * $c->getQuantite(),
                    'ticketsCount'             => 0,
                    'dernierTicketTitre'       => '-',
                    'dernierTicketStatut'      => '-',
                    'dernierTicketTechnicien'  => '-',
                    'coutTotalMaintenance'     => 0,
                    default                    => '-',
                };
            }
            $rows[] = $row;
            }
        }

        $totalCount = count($rows);
        if ($limit !== null) {
            $rows = array_slice($rows, 0, $limit);
        }

        return [
            'columns'     => $columns,
            'rows'        => $rows,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Extrait les enregistrements pour les scopes simples (équipements, composants, attributions, maintenances)
     *
     * @param string[] $selectedColumns
     * @return array{columns: array<string, string>, rows: array<int, array<string, mixed>>, total_count: int}
     */
    private function fetchReportData(
        string $scope,
        array $selectedColumns,
        string $statutFilter,
        string $categorieFilter,
        string $lieuFilter,
        string $ageFilter,
        EntityManagerInterface $em,
        ?int $limit = null
    ): array {
        $allAvailableColumns = match ($scope) {
            'composants'   => self::COMPOSANT_COLUMNS,
            'attributions' => self::ATTRIBUTION_COLUMNS,
            'maintenances' => self::MAINTENANCE_COLUMNS,
            default        => self::EQUIPEMENT_COLUMNS,
        };

        $columns = [];
        foreach ($selectedColumns as $colKey) {
            if (isset($allAvailableColumns[$colKey])) {
                $columns[$colKey] = $allAvailableColumns[$colKey]['label'];
            }
        }

        $rows = [];
        $totalCount = 0;

        if ($scope === 'composants') {
            $qb = $em->getRepository(Composant::class)->createQueryBuilder('c')
                ->leftJoin('c.equipement', 'e')
                ->leftJoin('c.lieu', 'l')
                ->addSelect('e')
                ->addSelect('l')
                ->orderBy('c.dateAjout', 'DESC');

            if ($statutFilter !== 'all') {
                $qb->andWhere('c.statut = :statut')->setParameter('statut', $statutFilter);
            }
            if ($lieuFilter !== 'all') {
                $qb->andWhere('l.id = :lieuId')->setParameter('lieuId', $lieuFilter);
            }

            /** @var Composant[] $items */
            $items = $qb->getQuery()->getResult();

            if ($ageFilter === 'vieillissant') {
                $items = array_filter($items, fn(Composant $c) => $c->isVieillissant());
            } elseif ($ageFilter === 'obsolete') {
                $items = array_filter($items, fn(Composant $c) => $c->isObsolete());
            }

            $totalCount = count($items);
            if ($limit !== null) {
                $items = array_slice($items, 0, $limit);
            }

            foreach ($items as $c) {
                $row = [];
                foreach ($selectedColumns as $col) {
                    $row[$col] = match ($col) {
                        'nom'             => $c->getNom(),
                        'type'            => $c->getType(),
                        'numeroSerie'     => $c->getNumeroSerie() ?: '-',
                        'quantite'        => $c->getQuantite(),
                        'statut'          => $c->getStatut(),
                        'equipement'      => $c->getEquipement()?->getNom() ?: 'Non monté',
                        'lieu'            => $c->getLieu()?->getNomLieu() ?: '-',
                        'dateAcquisition' => $c->getDateAcquisition()?->format('d/m/Y') ?: '-',
                        'ageAnnees'       => $c->getAgeAnnees() !== null ? $c->getAgeAnnees() : '-',
                        'prixAchat'       => $c->getPrixAchat() ?? 0,
                        'valeurTotale'    => ($c->getPrixAchat() ?? 0) * $c->getQuantite(),
                        'notes'           => $c->getNotes() ?: '-',
                        default           => '',
                    };
                }
                $rows[] = $row;
            }

        } elseif ($scope === 'attributions') {
            $qb = $em->getRepository(Attribution::class)->createQueryBuilder('a')
                ->leftJoin('a.equipement', 'e')
                ->addSelect('e')
                ->orderBy('a.dateDebut', 'DESC');

            if ($statutFilter !== 'all') {
                $qb->andWhere('a.statut = :statut')->setParameter('statut', $statutFilter);
            }

            /** @var Attribution[] $items */
            $items = $qb->getQuery()->getResult();
            $totalCount = count($items);
            if ($limit !== null) {
                $items = array_slice($items, 0, $limit);
            }

            foreach ($items as $a) {
                $row = [];
                foreach ($selectedColumns as $col) {
                    $row[$col] = match ($col) {
                        'equipement'          => $a->getEquipement()?->getNom() ?: 'Matériel retiré',
                        'numeroSerie'         => $a->getEquipement()?->getNumeroSerie() ?: '-',
                        'beneficiaireNom'     => $a->getBeneficiaireNom(),
                        'beneficiaireService' => $a->getBeneficiaireService() ?: 'GTAC2A-IARDT',
                        'dateDebut'           => $a->getDateDebut()?->format('d/m/Y') ?: '-',
                        'dateFinPrevue'       => $a->getDateFinPrevue()?->format('d/m/Y') ?: '-',
                        'dateRestitution'     => $a->getDateRestitution()?->format('d/m/Y') ?: '-',
                        'statut'              => $a->getStatut(),
                        'motif'               => $a->getMotif() ?: '-',
                        default               => '',
                    };
                }
                $rows[] = $row;
            }

        } elseif ($scope === 'maintenances') {
            $qb = $em->getRepository(TicketMaintenance::class)->createQueryBuilder('t')
                ->leftJoin('t.equipement', 'e')
                ->addSelect('e')
                ->orderBy('t.dateCreation', 'DESC');

            if ($statutFilter !== 'all') {
                $qb->andWhere('t.statut = :statut')->setParameter('statut', $statutFilter);
            }

            /** @var TicketMaintenance[] $items */
            $items = $qb->getQuery()->getResult();
            $totalCount = count($items);
            if ($limit !== null) {
                $items = array_slice($items, 0, $limit);
            }

            foreach ($items as $t) {
                $row = [];
                foreach ($selectedColumns as $col) {
                    $row[$col] = match ($col) {
                        'id'             => '#' . $t->getId(),
                        'equipement'     => $t->getEquipement()?->getNom() ?: 'Matériel retiré',
                        'titre'          => $t->getTitre(),
                        'priorite'       => $t->getPriorite(),
                        'statut'         => $t->getStatut(),
                        'technicien'     => $t->getTechnicien() ?: 'Non assigné',
                        'dateCreation'   => $t->getDateCreation()?->format('d/m/Y') ?: '-',
                        'dateResolution' => $t->getDateResolution()?->format('d/m/Y') ?: '-',
                        'cout'           => $t->getCout() ?? 0,
                        'solution'       => $t->getSolution() ?: '-',
                        default          => '',
                    };
                }
                $rows[] = $row;
            }

        } else {
            // Scope par défaut : Équipements
            $qb = $em->getRepository(Equipement::class)->createQueryBuilder('e')
                ->leftJoin('e.categorie', 'c')
                ->leftJoin('e.lieu', 'l')
                ->addSelect('c')
                ->addSelect('l')
                ->orderBy('e.dateAjout', 'DESC');

            if ($statutFilter !== 'all') {
                $qb->andWhere('e.statut = :statut')->setParameter('statut', $statutFilter);
            }
            if ($categorieFilter !== 'all') {
                $qb->andWhere('c.id = :catId')->setParameter('catId', $categorieFilter);
            }
            if ($lieuFilter !== 'all') {
                $qb->andWhere('l.id = :lieuId')->setParameter('lieuId', $lieuFilter);
            }

            /** @var Equipement[] $items */
            $items = $qb->getQuery()->getResult();

            if ($ageFilter === 'vieillissant') {
                $items = array_filter($items, fn(Equipement $e) => $e->isVieillissant());
            } elseif ($ageFilter === 'obsolete') {
                $items = array_filter($items, fn(Equipement $e) => $e->isObsolete());
            }

            $totalCount = count($items);
            if ($limit !== null) {
                $items = array_slice($items, 0, $limit);
            }

            $activeAttributions = $em->getRepository(Attribution::class)->findBy(['statut' => 'En cours']);
            $beneficiaireMap = [];
            foreach ($activeAttributions as $att) {
                if ($att->getEquipement()) {
                    $beneficiaireMap[$att->getEquipement()->getId()] = $att->getBeneficiaireNom();
                }
            }

            foreach ($items as $eq) {
                $row = [];
                $isGarantieValide = $eq->getFinGarantie() && $eq->getFinGarantie()->format('Y-m-d') >= date('Y-m-d');
                $vetusteLabel = $eq->isObsolete() ? 'Obsolète (> 5 ans)' : ($eq->isVieillissant() ? 'Vieillissant (3-5 ans)' : ($eq->getDateAcquisition() ? 'Récent (< 3 ans)' : 'Non défini'));

                foreach ($selectedColumns as $col) {
                    $row[$col] = match ($col) {
                        'nom'             => $eq->getNom(),
                        'marque'          => $eq->getMarque(),
                        'modele'          => $eq->getModele(),
                        'numeroSerie'         => $eq->getNumeroSerie(),
                        'adresseIp'           => $eq->getAdresseIp() ?: '-',
                        'systemeExploitation' => $eq->getSystemeExploitation() ?: '-',
                        'tagInventaire'       => $eq->getTagInventaire() ?: '-',
                        'categorie'       => $eq->getCategorie()?->getNomCat() ?: 'Non classé',
                        'lieu'            => $eq->getLieu()?->getNomLieu() ?: 'Non assigné',
                        'statut'          => $eq->getStatut(),
                        'dateAcquisition' => $eq->getDateAcquisition()?->format('d/m/Y') ?: '-',
                        'ageAnnees'       => $eq->getAgeAnnees() !== null ? $eq->getAgeAnnees() : '-',
                        'vetuste'         => $vetusteLabel,
                        'finGarantie'     => $eq->getFinGarantie()?->format('d/m/Y') ?: '-',
                        'statutGarantie'  => $eq->getFinGarantie() ? ($isGarantieValide ? 'Valide' : 'Expirée') : 'N/C',
                        'prixAchat'       => $eq->getPrixAchat() ?? 0,
                        'beneficiaire'    => $beneficiaireMap[$eq->getId()] ?? ($eq->getStatut() === 'En service' ? 'Attribué' : '-'),
                        'dateAjout'       => $eq->getDateAjout()?->format('d/m/Y') ?: '-',
                        'notes'           => $eq->getNotes() ?: '-',
                        default           => '',
                    };
                }
                $rows[] = $row;
            }
        }

        return [
            'columns'     => $columns,
            'rows'        => $rows,
            'total_count' => $totalCount,
        ];
    }
}
