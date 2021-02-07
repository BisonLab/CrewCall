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

        $summary[] = array(
            'name' => 'name',
            'value' => (string)$location,
            'label' => 'Name'
            );

        return $summary;
    }
}
