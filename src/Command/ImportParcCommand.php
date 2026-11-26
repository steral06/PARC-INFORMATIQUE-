<?php

namespace App\Command;

use App\Entity\Attribution;
use App\Entity\CategorieEquipement;
use App\Entity\Equipement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:import-parc',
    description: 'Importe les données du parc informatique depuis le fichier JSON seed_parc.json',
)]
class ImportParcCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les données sans les persister');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $jsonPath = $this->projectDir . '/data/seed_parc.json';
        if (!file_exists($jsonPath)) {
            $io->error(sprintf('Fichier introuvable : %s', $jsonPath));
            return Command::FAILURE;
        }

        $data = json_decode(file_get_contents($jsonPath), true, 512, \JSON_THROW_ON_ERROR);

        // 1. Créer ou récupérer la catégorie
        $catRepo = $this->em->getRepository(CategorieEquipement::class);
        $categorie = $catRepo->findOneBy(['nom_cat' => $data['categorie']['nom_cat']]);

        if (!$categorie) {
            $categorie = new CategorieEquipement();
            $categorie->setNomCat($data['categorie']['nom_cat']);
            $categorie->setDescription($data['categorie']['description']);
            $this->em->persist($categorie);
            $io->text(sprintf('✓ Catégorie créée : <info>%s</info>', $data['categorie']['nom_cat']));
        } else {
            $io->text(sprintf('→ Catégorie existante : <info>%s</info>', $data['categorie']['nom_cat']));
        }

        // 2. Importer les équipements + attributions
        $equipRepo = $this->em->getRepository(Equipement::class);
        $created = 0;
        $skipped = 0;

        foreach ($data['equipements'] as $row) {
            // Vérifier si un équipement avec ce tag existe déjà
            $existing = $equipRepo->findOneBy(['tagInventaire' => $row['tag']]);
            if ($existing) {
                $io->text(sprintf('  ⏭ Équipement existant (tag: %s), ignoré', $row['tag']));
                $skipped++;
                continue;
            }

            // Vérifier si l'adresse IP est déjà utilisée
            $existingIp = $equipRepo->findOneBy(['adresseIp' => $row['ip']]);
            if ($existingIp) {
                $io->text(sprintf('  ⏭ Adresse IP %s déjà utilisée par « %s », équipement ignoré', $row['ip'], $existingIp->getNom()));
                $skipped++;
                continue;
            }

            // Créer l'équipement
            $equip = new Equipement();
            $equip->setNom($row['nom']);
            $equip->setMarque($row['marque']);
            $equip->setModele($row['modele']);
            $equip->setTagInventaire($row['tag']);
            $equip->setAdresseIp($row['ip']);
            $equip->setStatut('En service');
            $equip->setCategorie($categorie);
            $this->em->persist($equip);

            // Créer l'attribution
            $attr = new Attribution();
            $attr->setEquipement($equip);
            $attr->setBeneficiaireNom($row['beneficiaire']);
            $attr->setStatut('En cours');
            $attr->setMotif('Dotation initiale — Import parc GTA');
            $this->em->persist($attr);

            $created++;
            $io->text(sprintf('  ✓ %s → %s [%s] (%s)', $row['beneficiaire'], $row['nom'], $row['tag'], $row['ip']));
        }

        if ($dryRun) {
            $io->warning(sprintf('Mode dry-run : %d équipement(s) auraient été créés, %d ignoré(s). Aucune donnée persistée.', $created, $skipped));
            return Command::SUCCESS;
        }

        $this->em->flush();

        $io->newLine();
        $io->success(sprintf(
            'Import terminé ! %d équipement(s) créé(s) avec attribution, %d ignoré(s) (doublons).',
            $created,
            $skipped
        ));

        return Command::SUCCESS;
    }
}

