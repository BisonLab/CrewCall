<?php

namespace CustomBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use CrewCallBundle\Entity\FunctionEntity;

class CreateBaseFunctionCommand extends ContainerAwareCommand
{
    private $functions = array(
        array(
            'name' => 'Crewmember',
            'function_type' => 'ROLE',
            'description' => 'Base role',
        ),
        array(
            'name' => 'Admin',
            'function_type' => 'ROLE',
            'description' => 'Admin Functions',
        ),
        array(
            'name' => 'Contact',
            'function_type' => 'ROLE',
            'description' => 'Contact Person',
        ),
        array(
            'name' => 'Gaffer',
            'function_type' => 'SKILL',
            'description' => 'Gotta have it',
        ),
    );

    protected function configure()
    {
        $this
            ->setName('once:create-base-functions')
            ->setDescription('Creates the first set of function the system needs.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $function_repo = $em->getRepository('CrewCallBundle:FunctionEntity');

        foreach ($this->functions as $fd) {
            // It may have been made already, if config_custom is untouched.
            if ($function_repo->findOneByName($fd['name']))
                continue;
            $func = new FunctionEntity();
            $func->setState("VISIBLE");
            $func->setName($fd['name']);
            $func->setDescription($fd['description']);
            $func->setFunctionType($fd['function_type']);
            $em->persist($func);
            $em->flush();
            $em->clear();
            $output->writeln('Made ' . $fd['name']);

        }
        $output->writeln('OK Done.');
    }
}
