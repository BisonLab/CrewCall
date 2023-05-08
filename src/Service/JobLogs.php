<?php

namespace App\Service;

use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Job;
use App\Entity\JobLog;
use App\Entity\Person;
use App\Entity\Shift;

class JobLogs
{
    private $em;
    private $params;
    private $handler;

    public function __construct($em, $params)
    {
        $this->em = $em;
        $this->params = $params;
        $handler = $this->params->get('joblogs_handler')['class'];
        $this->handler = new $handler($em, $params);
    }

    public function getJobLogsForPerson(Person $person, $options = array())
    {
        return $this->handler->getJobLogsForPerson($person, $options);
    }
}
