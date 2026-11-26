<?php

namespace App\Command;

use App\Entity\Equipement;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:test-audit')]
class TestAuditCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger
    ) {
        parent::__construct();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $equipement = $this->em->getRepository(Equipement::class)->find(1);
        
        if (!$equipement) {
            $io->error('No equipment found with ID 1');
            return Command::FAILURE;
        }
        
        $io->note('Found equipment: ' . $equipement->getNom());
        $io->note('Current status: ' . $equipement->getStatut());
        
        // Change status to a different value to trigger preUpdate
        $newStatus = $equipement->getStatut() === 'En stock' ? 'En service' : 'En stock';
        $equipement->setStatut($newStatus);
        $this->em->flush();
        
        $io->success('Updated status to: ' . $newStatus);
        
        return Command::SUCCESS;
    }
}