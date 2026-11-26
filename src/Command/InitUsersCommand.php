<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:init-users',
    description: 'Initialise le compte Superadmin unique et le compte Utilisateur partagé',
)]
class InitUsersCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('admin-pass', null, InputOption::VALUE_OPTIONAL, 'Mot de passe superadmin', 'admin123')
            ->addOption('user-pass', null, InputOption::VALUE_OPTIONAL, 'Mot de passe utilisateur partagé', 'parc2026');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $adminPass = (string) $input->getOption('admin-pass');
        $userPass = (string) $input->getOption('user-pass');

        // 1. Superadmin
        $superadmin = $this->userRepository->findOneBy(['username' => 'superadmin']);
        if (!$superadmin) {
            $superadmin = new User();
            $superadmin->setUsername('superadmin');
            $this->entityManager->persist($superadmin);
            $io->text('Création du compte superadmin...');
        } else {
            $io->text('Mise à jour du compte superadmin existant...');
        }
        $superadmin->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']);
        $superadmin->setPassword($this->passwordHasher->hashPassword($superadmin, $adminPass));
        $superadmin->setPinCode('1234');
        if (!$superadmin->getPasswordChangedAt()) {
            $superadmin->setPasswordChangedAt(new \DateTimeImmutable());
        }

        // 2. Utilisateur partagé
        $sharedUser = $this->userRepository->findOneBy(['username' => 'utilisateur']);
        if (!$sharedUser) {
            $sharedUser = new User();
            $sharedUser->setUsername('utilisateur');
            $this->entityManager->persist($sharedUser);
            $io->text('Création du compte utilisateur partagé...');
        } else {
            $io->text('Mise à jour du compte utilisateur partagé existant...');
        }
        $sharedUser->setRoles(['ROLE_USER']);
        $sharedUser->setPassword($this->passwordHasher->hashPassword($sharedUser, $userPass));
        $sharedUser->setPinCode('1234');
        if (!$sharedUser->getPasswordChangedAt()) {
            $sharedUser->setPasswordChangedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        $io->success('Comptes créés avec succès !');
        $io->table(
            ['Type', 'Identifiant', 'Mot de passe par défaut', 'Rôles'],
            [
                ['Superadmin unique', 'superadmin', $adminPass, 'ROLE_SUPER_ADMIN'],
                ['Compte Partagé (Multi-postes)', 'utilisateur', $userPass, 'ROLE_USER'],
            ]
        );

        return Command::SUCCESS;
    }
}
