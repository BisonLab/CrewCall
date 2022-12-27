<?php

namespace App\Lib\Summarizer;

class Location
{
    private $router;

    public function __construct($router)
    {
        $this->router = $router;
    }

    public function summarize(\App\Entity\Location $location, $access = null)
    {
        $summary = array();

        $larr = array(
            'name' => 'name',
            'value' => (string)$location,
            'label' => 'Name'
            );
        if ($access)
            $larr['url'] = $this->router->generate('location_show',
                array('access' => $access,
                'id' => $location->getId()
                ));
        $summary[] = $larr;

        return $summary;
    }
}
