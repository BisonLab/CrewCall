<?php

namespace App\Service;

use App\Entity\Person;

class CrewCallRetriever
{
    private $em;

    public function __construct($em)
    {
        $this->em = $em;
    }

    public function getExternalDataFromContext($context)
    {
        // Which contexts do we care about?
        // Person for now.
        if ('crewcall' == $context->getSystem()) {
            // There should be only one.
            if ('person' == $context->getObjectName()) {
                return $this->em->getRepository(Person::class)->find($context->getExternalId());
            }
        }
        return null;
    }
}
