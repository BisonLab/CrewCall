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

        $summary[] = array(
            'name' => 'name',
            'value' => (string)$event,
            'label' => 'Name'
            );

        $summary[] = array(
            'name' => 'location',
            'value' => (string)$event->getLocation(),
            'label' => 'Location'
            );

        $summary[] = array(
            'name' => 'state',
            'value' => (string)$event->getState(),
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
