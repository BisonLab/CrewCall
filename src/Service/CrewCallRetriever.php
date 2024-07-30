<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Person;

class CrewCallRetriever
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getExternalDataFromContext($context)
    {
        // Which contexts do we care about?
        // Person for now.
        if ('crewcall' == $context->getSystem()) {
            // There should be only one.
            if ('person' == $context->getObjectName()) {
                return $this->entityManager->getRepository(Person::class)->find($context->getExternalId());
            }
        }
        return null;
    }
}
