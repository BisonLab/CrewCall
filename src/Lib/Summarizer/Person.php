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

        $parr = array(
            'name' => 'name',
            'value' => (string)$person,
            'label' => 'Name'
            );
        if ($access)
            $parr['url'] = $this->router->generate('person_show',
                array('access' => $access,
                'id' => $person->getId()
                ));
        $summary[] = $parr;

        $summary[] = array(
            'name' => 'userame',
            'value' => $person->getUsername(),
            'label' => 'Username'
            );

        $summary[] = array(
            'name' => 'mobile_phone_number',
            'value' => (string)$person->getMobilePhoneNumber(),
            'label' => 'Mobile',
            );

        if ($email = $person->getEmail()) {
            if (in_array($access, ["ajax", "web"]))
                $content = '<a href="mailto:' . $email  . '">'
                    . $email . '</a>'
                    ;
            else
                $content = $email;
            $summary[] = array(
                'name' => 'email',
                'value' => $content,
                'label' => 'Email address',
                'html' => true
                );
        }

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
        return $summary;
    }
}
