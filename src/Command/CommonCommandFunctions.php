<?php
  
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

trait CommonCommandFunctions
{
    private $params;
    private $crewcall_em;
    private $sakonnin_em;
    private $passwordEncoder;

    public function __construct(EntityManagerInterface $crewcall_em, EntityManagerInterface $sakonnin_em, UserPasswordEncoderInterface $passwordEncoder, ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->crewcall_em = $crewcall_em;
        $this->sakonnin_em = $sakonnin_em;
        $this->passwordEncoder = $passwordEncoder;

        parent::__construct();
    }

    public function addCommonOptions()
    {
        $this->addOption('run-as-user', null, InputOption::VALUE_REQUIRED, 'Set the user running this command', null);
    }
}
