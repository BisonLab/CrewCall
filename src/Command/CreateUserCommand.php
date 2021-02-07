<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use App\Entity\Person;

class CreateUserCommand extends Command
{
    use \App\Command\CommonCommandFunctions;

    protected static $defaultName = 'crewcall:user:create';

    protected function configure()
    {
        $this
            ->setDescription('Create a new person/user')
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('email', InputArgument::REQUIRED, 'Email address')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Role, default USER')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');

        $role = null;
        if ($input->getOption('role')) {
            $role = "ROLE_" . strtoupper($input->getOption('role'));
        }

        $user = new Person();
        if ($password = $input->getOption('password')) {
            // Encode the plain password, and set it.
            $encodedPassword = $this->passwordEncoder->encodePassword(
                $user, $password
            );
            $user->setPassword($encodedPassword);
        } else {
            $user->setPassword(uniqid());
        }
        $user->setUsername($username);
        $user->setEmail($email);
        if ($role)
            $user->setRoles([$role]);

        $this->crewcall_em->persist($user);
        $this->crewcall_em->flush();

        $io->success('You added the user ' . $username . '. Now send a password email with crewcall:user:send-passwordmail ' . $username);

        return 0;
    }
}
