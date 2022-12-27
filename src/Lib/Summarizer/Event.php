<?php

namespace App\Lib\Summarizer;

class Event
{
    private $router;

    public function __construct($router)
    {
        $this->router = $router;
    }

    public function summarize(\App\Entity\Event $event, $access = null)
    {
        $summary = array();

        $evarr = array(
            'name' => 'name',
            'value' => (string)$event,
            'label' => 'Name'
            );
        if ($access)
            $evarr['url'] = $this->router->generate('event_show',
                array('access' => $access,
                'id' => $event->getId()
                ));
        $summary[] = $evarr;

        $summary[] = array(
            'name' => 'location',
            'value' => (string)$event->getLocation(),
            'label' => 'Location'
            );

        $summary[] = array(
            'name' => 'state',
            'value' => (string)$event->getStateLabel(),
            'label' => 'State'
            );

        $summary[] = array(
            'name' => 'start',
            'value' => $event->getStart()->format("d M H:i"),
            'label' => 'Start'
            );

        if ($event->getEnd()) {
            $summary[] = array(
                'name' => 'end',
                'value' => $event->getEnd()->format("d M H:i"),
                'label' => 'End'
                );
        }

        return $summary;
    }
}
