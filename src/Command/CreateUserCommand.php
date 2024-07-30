<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;

use App\Entity\Person;
use App\Entity\PersonRoleOrganization;
use App\Entity\FunctionEntity;
use App\Entity\Role;
use App\Entity\Organization;

#[AsCommand(
    name: 'crewcall:user:create',
    description: 'Create a new person/user'
)]
class CreateUserCommand extends Command
{
    use \App\Command\CommonCommandFunctions;

    protected function configure()
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('email', InputArgument::REQUIRED, 'Email address')
            ->addOption('system_role', null, InputOption::VALUE_REQUIRED, 'System role, default USER')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');

        $system_role = null;
        if ($input->getOption('system_role')) {
            $system_role = "ROLE_" . strtoupper($input->getOption('system_role'));
        }

        $user = new Person();
        if ($password = $input->getOption('password')) {
            // Encode the plain password, and set it.
            $encodedPassword = $this->userPasswordHasher->hashPassword(
                $user, $password
            );
            $user->setPassword($encodedPassword);
        } else {
            $user->setPassword(uniqid());
        }
        $user->setUsername($username);
        $user->setEmail($email);
        if ($system_role)
            $user->setRoles([$system_role]);
        $this->crewcall_em->persist($user);

        $first_org = $this->crewcall_em->getRepository(Organization::class)->getInternalOrganization();
        $first_role = $this->crewcall_em->getRepository(Role::class)->getDefaultRole();
        $pro = new PersonRoleOrganization();
        $pro->setPerson($user);
        $pro->setOrganization($first_org);
        $pro->setRole($first_role);
        $this->crewcall_em->persist($pro);

        $this->crewcall_em->flush();

        $io->success('You added the user ' . $username . '. Now send a password email with crewcall:user:send-passwordmail ' . $username);

        return 0;
    }
}
