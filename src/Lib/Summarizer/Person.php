<?php

namespace App\Lib\Summarizer;

class Person
{
    private $router;

    public function __construct($router)
    {
        $this->router = $router;
    }

    public function summarize(\App\Entity\Person $person, $access = null)
    {
        $summary = array();

        $summary[] = array(
            'name' => 'name',
            'value' => (string)$person,
            'label' => 'Name'
            );

        $summary[] = array(
            'name' => 'userame',
            'value' => $person->getUsername(),
            'label' => 'Username'
            );

        $summary[] = array(
            'name' => 'mobile_phone_number',
            'value' => (string)$person->getMobilePhoneNumber(),
            'label' => 'Mobile'
            );

        $state_text = '';
        foreach ($person->getStates(['from_now' => true]) as $stateobj) {
            if (!empty($state_text))
                $state_text .= ", ";
            $state_text .= $stateobj->getStateLabel();
            if ($fd = $stateobj->getFromDate())
                $state_text .= " from " . $fd->format('Y-m-d');
            if ($td = $stateobj->getToDate())
                $state_text .= " to " . $td->format('Y-m-d');
        }
        if (!empty($state_text)) {
            $summary[] = array(
                'name' => 'states',
                'value' => $state_text,
                'label' => 'States'
                );
        }
        /*
        if ($stateobj = $person->getStateOnDate()) {
            $text = $stateobj->getState();
            if ($fd = $stateobj->getFromDate())
                $text .= "  From:" . $fd->format('Y-m-d');
            if ($td = $stateobj->getToDate())
                $text .= "  To:" . $td->format('Y-m-d');
            $summary[] = array(
                'name' => 'state',
                'value' => $text,
                'label' => 'State'
                );
        }
        */

        $summary[] = array(
            'name' => 'diets',
            'value' => implode(", ", $person->getDietsLabels()),
            'label' => 'Diets'
            );

        return $summary;
    }
}
