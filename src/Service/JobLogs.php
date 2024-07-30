<?php

namespace App\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use BisonLab\SakonninBundle\Service\Messages as SakonninMessages;
use App\Entity\Job;
use App\Entity\JobLog;
use App\Entity\Person;
use App\Entity\Shift;

class JobLogs
{
    private $handler;

    public function __construct(
        private EntityManagerInterface $entityManager,
        protected ParameterBagInterface $parameterBag,
    ) {
        $handler = $this->parameterBag->get('joblogs_handler')['class'];
        $this->handler = new $handler($this->entityManager, $this->parameterBag);
    }

    public function getJobLogsForPerson(Person $person, $options = array())
    {
        return $this->handler->getJobLogsForPerson($person, $options);
    }
}
