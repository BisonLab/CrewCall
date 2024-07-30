<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;

use App\Entity\Person;
use App\Entity\Organization;
use App\Entity\Event;

/* 
 * This is the way to be able to program "Summaries" on entities. Both
 * here in the main bundle, but also customize whenever needed.
 */
class Summarizer
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private RouterInterface $router,
    ) {
    }

    /*
     * Frog is the entity we are about to summarize.
     */
    public function summarize($frog, $access = null)
    {
        if ($frog instanceOf Person)
            return $this->summarizePerson($frog, $access);
        if ($frog instanceOf Organization)
            return $this->summarizeOrganization($frog, $access);
        if ($frog instanceOf Event)
            return $this->summarizeEvent($frog, $access);
    }

    public function summarizePerson($frog, $access = null)
    {
        $ds = new \App\Lib\Summarizer\Person($this->router);
        $summary = $ds->summarize($frog, $access);
        if (class_exists('CustomBundle\Lib\Summarizer\Person')) {
            $cs = new \CustomBundle\Lib\Summarizer\Person($this->router);
            $summary = $cs->summarize($frog, $summary, $access);
        }
        return $summary;
    }

    public function summarizeOrganization($frog, $access = null)
    {
        return array();
    }

    public function summarizeEvent($frog, $access = null)
    {
        $ds = new \App\Lib\Summarizer\Event($this->router);
        $summary = $ds->summarize($frog, $access);
        if (class_exists('CustomBundle\Lib\Summarizer\Event')) {
            $cs = new \CustomBundle\Lib\Summarizer\Event($this->router);
            $summary = $cs->summarize($frog, $summary, $access);
        }
        return $summary;
    }
}
