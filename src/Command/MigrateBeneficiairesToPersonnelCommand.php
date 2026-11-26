<?php

namespace App\Command;

use App\Entity\Attribution;
use App\Entity\Personnel;
use App\Repository\AttributionRepository;
use App\Repository\PersonnelRepository;
use App\Service\MatriculeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-beneficiaires-personnel',
    description: 'Migre les bénéficiaires existants des attributions dans la table personnel en tant qu\'Employés avec matricule automatique.',
)]
class MigrateBeneficiairesToPersonnelCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AttributionRepository $attributionRepository,
        private readonly PersonnelRepository $personnelRepository,
        private readonly MatriculeGenerator $matriculeGenerator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migration des Bénéficiaires vers la table Personnel');

        $attributions = $this->attributionRepository->findAll();
        if (empty($attributions)) {
            $io->info('Aucune attribution trouvée.');
            return Command::SUCCESS;
        }

        $createdCount = 0;
        $linkedCount = 0;
        /** @var array<string, Personnel> $personnelCache */
        $personnelCache = [];

        // Pré-charger les personnels existants par nom
        foreach ($this->personnelRepository->findAll() as $existing) {
            $personnelCache[mb_strtolower(trim($existing->getNom()))] = $existing;
        }

        // Compteur par préfixe pour assigner des numéros séquentiels cohérents
        $prefixCounters = [];

        foreach ($attributions as $attr) {
            $nom = trim((string) $attr->getBeneficiaireNom());
            if ($nom === '') {
                continue;
            }

            $key = mb_strtolower($nom);
            if (!isset($personnelCache[$key])) {
                $service = $attr->getBeneficiaireService();
                $prefix = $this->matriculeGenerator->getServicePrefix($service);

                if (!isset($prefixCounters[$prefix])) {
                    $prefixCounters[$prefix] = $this->personnelRepository->getNextMatriculeNumberForPrefix($prefix);
                }

                $matricule = sprintf('%s-%04d', $prefix, $prefixCounters[$prefix]);
                $prefixCounters[$prefix]++;

                $personnel = new Personnel();
                $personnel->setNom($nom);
                $personnel->setMatricule($matricule);
                $personnel->setType(Personnel::TYPE_EMPLOYE);
                $personnel->setService($service);
                $personnel->setStatut('Actif');
                $personnel->setNotes('Créé automatiquement lors de la migration des attributions historiques.');

                $this->entityManager->persist($personnel);
                $personnelCache[$key] = $personnel;
                $createdCount++;

                $io->text(sprintf('  + Personnel créé : [%s] %s (%s, %s)', $matricule, $nom, $personnel->getType(), $service ?? 'Sans service'));
            }

            $matchedPersonnel = $personnelCache[$key];
            if ($attr->getPersonnel() !== $matchedPersonnel) {
                $attr->setPersonnel($matchedPersonnel);
                $linkedCount++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Migration terminée avec succès ! %d nouveau(x) personnel(s) créé(s), %d attribution(s) liée(s).',
            $createdCount,
            $linkedCount
        ));

        return Command::SUCCESS;
    }
}

