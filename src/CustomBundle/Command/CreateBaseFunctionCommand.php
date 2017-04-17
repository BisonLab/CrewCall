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
            'name' => 'Admin',
            'description' => 'Admin Functions',
        ),
        array(
            'name' => 'Organizations',
            'description' => 'Organization Functions',
        ),
        array(
            'name' => 'Contact',
            'description' => 'Contact Person',
            'parent' => 'Organizations',
        ),
        array(
            'name' => 'Employee',
            'description' => 'Employee types',
        ),
        array(
            'name' => 'Freelance',
            'description' => 'Freelance',
            'parent' => 'Employee',
        ),
        array(
            'name' => 'Part-time',
            'description' => 'Paid per job',
            'parent' => 'Employee',
        ),
        array(
            'name' => 'Fulltime',
            'description' => 'Full time employee',
            'parent' => 'Employee',
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
        $this->function_repo = $em->getRepository('CrewCallBundle:FunctionEntity');

        foreach ($this->functions as $fd) {
            $func = new FunctionEntity();
            $func->setState("VISIBLE");
            $func->setName($fd['name']);
            $func->setDescription($fd['description']);
            if (isset($fd['parent'])) {
                $parent = $this->function_repo->findOneByName($fd['parent']);
                $func->setParent($parent);
            }
            $em->persist($func);
            $em->flush();
            $em->clear();
            $output->writeln('Made ' . $fd['name']);

        }
        $output->writeln('OK Done.');
    }
}
