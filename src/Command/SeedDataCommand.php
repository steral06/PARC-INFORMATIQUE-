<?php

namespace App\Command;

use App\Entity\Attribution;
use App\Entity\CategorieEquipement;
use App\Entity\Equipement;
use App\Entity\Lieu;
use App\Entity\TicketMaintenance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-data',
    description: 'Initialise les données de démonstration pour le parc informatique GTAC2A-IARDT'
)]
class SeedDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. Catégories
        $categoriesData = [
            ['Ordinateurs Portables', 'Laptops professionnels pour collaborateurs mobiles et nomades'],
            ['Unités Centrales & PC Fixes', 'Stations de travail de bureau pour agences et services centraux'],
            ['Écrans & Moniteurs', 'Écrans 24" à 27" bureautique et multi-écrans'],
            ['Imprimantes & Scanners', 'Imprimantes multifonctions réseau et bacs de numérisation'],
            ['Réseau & Télécoms', 'Switchs Cisco, routeurs, bornes Wi-Fi sécurisées GTA'],
            ['Serveurs & Stockage', 'Serveurs d\'infrastructure, NAS et baies de virtualisation'],
            ['Onduleurs & Énergie', 'Onduleurs APC rackables et individuels pour continuité de service'],
        ];

        $categories = [];
        foreach ($categoriesData as [$nom, $desc]) {
            $cat = $this->em->getRepository(CategorieEquipement::class)->findOneBy(['nom_cat' => $nom]);
            if (!$cat) {
                $cat = new CategorieEquipement();
                $cat->setNomCat($nom);
                $cat->setDescription($desc);
                $this->em->persist($cat);
            }
            $categories[$nom] = $cat;
        }

        // 2. Lieux & Salles
        $lieuxData = [
            ['GTA Siège - Direction Générale', 'Immeuble Siège, 4ème étage - Direction et Gouvernance'],
            ['GTA Siège - Salle Serveurs (DSI)', 'Immeuble Siège, Sous-sol sécurisé - Climatisation redondante'],
            ['GTA Siège - Sinistres & Souscription', 'Immeuble Siège, 2ème étage - Gestion des polices et contrats'],
            ['GTA Siège - RH & Juridique', 'Immeuble Siège, 3ème étage - Recrutement et affaires juridiques'],
            ['Agence GTA - Lomé Port', 'Agence commerciale zone portuaire - Guichets et souscriptions'],
            ['Agence GTA - Tokoin', 'Agence commerciale Tokoin Centre - Espace clientèle'],
        ];

        $lieux = [];
        foreach ($lieuxData as [$nom, $desc]) {
            $lieu = $this->em->getRepository(Lieu::class)->findOneBy(['nom_lieu' => $nom]);
            if (!$lieu) {
                $lieu = new Lieu();
                $lieu->setNomLieu($nom);
                $lieu->setDescription($desc);
                $this->em->persist($lieu);
            }
            $lieux[$nom] = $lieu;
        }

        $this->em->flush();

        // 3. Équipements de référence
        $equipementsData = [
            [
                'nom' => 'Dell Latitude 5530 - DG',
                'numeroSerie' => 'GTA-DELL-5530-001',
                'tagInventaire' => 'INV-2026-001',
                'marque' => 'Dell',
                'modele' => 'Latitude 5530 i7 16Go',
                'statut' => 'En service',
                'cat' => 'Ordinateurs Portables',
                'lieu' => 'GTA Siège - Direction Générale',
                'prix' => 850000,
                'acq' => '2025-01-15',
                'gar' => '2028-01-15',
            ],
            [
                'nom' => 'Dell Latitude 5430 - DSI Lead',
                'numeroSerie' => 'GTA-DELL-5430-002',
                'tagInventaire' => 'INV-2026-002',
                'marque' => 'Dell',
                'modele' => 'Latitude 5430 i5 16Go',
                'statut' => 'En service',
                'cat' => 'Ordinateurs Portables',
                'lieu' => 'GTA Siège - Salle Serveurs (DSI)',
                'prix' => 690000,
                'acq' => '2025-02-10',
                'gar' => '2028-02-10',
            ],
            [
                'nom' => 'HP EliteDesk 800 G6 - Poste Guichet 1',
                'numeroSerie' => 'GTA-HP-ED800-010',
                'tagInventaire' => 'INV-2026-010',
                'marque' => 'HP',
                'modele' => 'EliteDesk 800 G6 Micro',
                'statut' => 'En service',
                'cat' => 'Unités Centrales & PC Fixes',
                'lieu' => 'Agence GTA - Lomé Port',
                'prix' => 450000,
                'acq' => '2024-06-20',
                'gar' => '2027-06-20',
            ],
            [
                'nom' => 'HP EliteDesk 800 G6 - Poste Guichet 2',
                'numeroSerie' => 'GTA-HP-ED800-011',
                'tagInventaire' => 'INV-2026-011',
                'marque' => 'HP',
                'modele' => 'EliteDesk 800 G6 Micro',
                'statut' => 'En service',
                'cat' => 'Unités Centrales & PC Fixes',
                'lieu' => 'Agence GTA - Tokoin',
                'prix' => 450000,
                'acq' => '2024-06-20',
                'gar' => '2027-06-20',
            ],
            [
                'nom' => 'Dell PowerEdge R750 - Serveur Principal',
                'numeroSerie' => 'GTA-SRV-R750-001',
                'tagInventaire' => 'INV-2026-SRV1',
                'marque' => 'Dell',
                'modele' => 'PowerEdge R750 2x Xeon 128Go RAM',
                'statut' => 'En service',
                'cat' => 'Serveurs & Stockage',
                'lieu' => 'GTA Siège - Salle Serveurs (DSI)',
                'prix' => 4500000,
                'acq' => '2024-03-01',
                'gar' => '2029-03-01',
            ],
            [
                'nom' => 'HP Color LaserJet Enterprise M555',
                'numeroSerie' => 'GTA-IMP-HP-M555-01',
                'tagInventaire' => 'INV-2026-PRN1',
                'marque' => 'HP',
                'modele' => 'LaserJet Enterprise M555dn',
                'statut' => 'En service',
                'cat' => 'Imprimantes & Scanners',
                'lieu' => 'GTA Siège - Sinistres & Souscription',
                'prix' => 620000,
                'acq' => '2024-09-12',
                'gar' => '2026-09-12',
            ],
            [
                'nom' => 'Switch Cisco Catalyst 9200 48 Ports',
                'numeroSerie' => 'GTA-SW-CISCO-9200',
                'tagInventaire' => 'INV-2026-NET1',
                'marque' => 'Cisco',
                'modele' => 'Catalyst 9200L Gigabit PoE+',
                'statut' => 'En service',
                'cat' => 'Réseau & Télécoms',
                'lieu' => 'GTA Siège - Salle Serveurs (DSI)',
                'prix' => 1200000,
                'acq' => '2024-01-10',
                'gar' => '2029-01-10',
            ],
            [
                'nom' => 'Lenovo ThinkPad T14 - En réserve',
                'numeroSerie' => 'GTA-LEN-T14-005',
                'tagInventaire' => 'INV-2026-005',
                'marque' => 'Lenovo',
                'modele' => 'ThinkPad T14 Gen 3',
                'statut' => 'En stock',
                'cat' => 'Ordinateurs Portables',
                'lieu' => 'GTA Siège - Salle Serveurs (DSI)',
                'prix' => 720000,
                'acq' => '2025-05-18',
                'gar' => '2028-05-18',
            ],
            [
                'nom' => 'Imprimante EPSON EcoTank L3250 - En panne',
                'numeroSerie' => 'GTA-EPSON-L3250-02',
                'tagInventaire' => 'INV-2026-PRN2',
                'marque' => 'Epson',
                'modele' => 'EcoTank L3250 Wi-Fi',
                'statut' => 'En panne',
                'cat' => 'Imprimantes & Scanners',
                'lieu' => 'Agence GTA - Lomé Port',
                'prix' => 140000,
                'acq' => '2023-11-05',
                'gar' => '2024-11-05',
            ],
        ];

        $equipements = [];
        foreach ($equipementsData as $eqData) {
            $eq = $this->em->getRepository(Equipement::class)->findOneBy(['numeroSerie' => $eqData['numeroSerie']]);
            if (!$eq) {
                $eq = new Equipement();
                $eq->setNom($eqData['nom']);
                $eq->setNumeroSerie($eqData['numeroSerie']);
                $eq->setTagInventaire($eqData['tagInventaire']);
                $eq->setMarque($eqData['marque']);
                $eq->setModele($eqData['modele']);
                $eq->setStatut($eqData['statut']);
                $eq->setPrixAchat((float)$eqData['prix']);
                $eq->setDateAcquisition(new \DateTime($eqData['acq']));
                $eq->setFinGarantie(new \DateTime($eqData['gar']));
                $eq->setCategorie($categories[$eqData['cat']] ?? null);
                $eq->setLieu($lieux[$eqData['lieu']] ?? null);
                $this->em->persist($eq);
            }
            $equipements[$eqData['numeroSerie']] = $eq;
        }

        $this->em->flush();

        // 4. Attributions de démonstration
        if (isset($equipements['GTA-DELL-5530-001'])) {
            $att1 = $this->em->getRepository(Attribution::class)->findOneBy(['equipement' => $equipements['GTA-DELL-5530-001']]);
            if (!$att1) {
                $att1 = new Attribution();
                $att1->setEquipement($equipements['GTA-DELL-5530-001']);
                $att1->setBeneficiaireNom('Directeur Général');
                $att1->setBeneficiaireService('Direction Générale GTA');
                $att1->setDateDebut(new \DateTime('2025-01-20 08:30:00'));
                $att1->setStatut('En cours');
                $att1->setMotif('Matériel de dotation de fonction principale');
                $this->em->persist($att1);
            }
        }

        if (isset($equipements['GTA-DELL-5430-002'])) {
            $att2 = $this->em->getRepository(Attribution::class)->findOneBy(['equipement' => $equipements['GTA-DELL-5430-002']]);
            if (!$att2) {
                $att2 = new Attribution();
                $att2->setEquipement($equipements['GTA-DELL-5430-002']);
                $att2->setBeneficiaireNom('Responsable Infrastructure DSI');
                $att2->setBeneficiaireService('DSI GTAC2A-IARDT');
                $att2->setDateDebut(new \DateTime('2025-02-15 09:00:00'));
                $att2->setStatut('En cours');
                $att2->setMotif('Poste d\'administration réseau et virtualisation');
                $this->em->persist($att2);
            }
        }

        // 5. Tickets de maintenance
        if (isset($equipements['GTA-EPSON-L3250-02'])) {
            $t1 = $this->em->getRepository(TicketMaintenance::class)->findOneBy(['equipement' => $equipements['GTA-EPSON-L3250-02']]);
            if (!$t1) {
                $t1 = new TicketMaintenance();
                $t1->setEquipement($equipements['GTA-EPSON-L3250-02']);
                $t1->setTitre('Bourrage papier récurrent et tête d\'impression bouchée');
                $t1->setDescription('L\'imprimante refuse l\'entraînement du papier et signale une erreur tête cyan.');
                $t1->setPriorite('Haute');
                $t1->setStatut('En cours');
                $t1->setTechnicien('Technicien Support GTA');
                $t1->setCout(25000);
                $this->em->persist($t1);
            }
        }

        $this->em->flush();

        $io->success('Les données initiales GTAC2A-IARDT (catégories, lieux, équipements, attributions, maintenances) ont été créées avec succès !');

        return Command::SUCCESS;
    }
}
