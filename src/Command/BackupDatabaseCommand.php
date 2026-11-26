<?php

namespace App\Command;

use App\Service\BackupManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backup-database',
    description: 'Sauvegarde à chaud et sécurisée de la base de données GTAC2A (Disaster Recovery)'
)]
class BackupDatabaseCommand extends Command
{
    public function __construct(
        private readonly BackupManager $backupManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('G-PARC GTAC2A-IARDT - Sauvegarde de la Base de Données');

        $result = $this->backupManager->createBackup('cli_scheduled');

        if ($result['success']) {
            $io->success(sprintf('Sauvegarde générée avec succès : %s (%s)', $result['filename'], $result['size']));
            return Command::SUCCESS;
        }

        $io->error('Échec de la sauvegarde : ' . ($result['error'] ?? 'Erreur inconnue'));
        return Command::FAILURE;
    }
}

