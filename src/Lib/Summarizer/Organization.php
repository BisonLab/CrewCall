<?php

namespace App\Lib\Summarizer;

class Organization
{
    private $router;

    public function __construct($router)
    {
        $this->router = $router;
    }

    public function summarize(\App\Entity\Organization $organization, $access = null)
    {
        $summary = array();

        $oarr = array(
            'name' => 'name',
            'value' => (string)$organization,
            'label' => 'Name'
            );
        if ($access)
            $oarr['url'] = $this->router->generate('organization_show',
                array('access' => $access,
                'id' => $organization->getId()
                ));
        $summary[] = $oarr;

        return $summary;
    }
}
