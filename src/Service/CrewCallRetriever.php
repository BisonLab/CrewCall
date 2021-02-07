<?php

namespace App\Service;

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
                return $this->em->getRepository('App:Person')->find($context->getExternalId());
            }
        }
        return null;
    }
}
