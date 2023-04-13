<?php

namespace App\Service;

use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\ShiftOrganization;
use App\Entity\Event;
use App\Entity\PersonState;

/*
 * This thignie will convert events, shiftfs, and more with start and
 * end to FullCalendar - json and ical objects.
 */

class Calendar
{
    private $router;
    private $summarizer;

    /*
     *
     */
    private $options = [];

    public function __construct($router, $summarizer)
    {
        $this->router = $router;
        $this->summarizer = $summarizer;
    }

    public function toIcal($frog, $options = [])
    {
        $this->options = $options;
        if ($frog instanceof Event) {
            $cal = $this->_eventToCal($frog);
        } elseif ($frog instanceof Shift) {
            $cal = $this->_shiftToCal($frog);
        } elseif ($frog instanceof Job) {
            $cal = $this->_jobToCal($frog);
        } elseif ($frog instanceof PersonState) {
            $cal = $this->_personStateToCal($frog);
        } else {
            throw new \InvalidArgumentException("Could not do anything useful with "
                . get_class($frog));
        }
        if (!$cal) return null;
        // TODO: Configurable domain.
        $vCalendar = new \Eluceo\iCal\Component\Calendar('CrewCall');
        $vEvent = new \Eluceo\iCal\Component\Event();
        $vEvent->setSummary($cal['title']);
        $vEvent->setDtStart($cal['start']);
        if ($cal['end'])
            $vEvent->setDtEnd($cal['end']);

        $vCalendar->addComponent($vEvent);
        return $vCalendar->render();
    }

    public function toFullCalendarSummary($frogs, $options = [])
    {
        $this->options = $options;
        $arr = array();
        $summary_arr = [];
        foreach ($frogs as $frog) {
            if ($cal = $this->_calToFullCal($frog)) {
                $start = preg_replace("/([0-9-]+).*/", '$1', $cal['start']);
                $sd = new \DateTime($start);
                $end = preg_replace("/([0-9-]+).*/", '$1', $cal['end']);
                if (empty($end))
                    $ed = clone($sd);
                else
                    $ed = new \DateTime($end);

                /*
                 * For states, break each frog into days.
                */
                if ($frog instanceOf PersonState) {
                    while ($sd <= $ed) {
                        $s = $sd->format("Ymd") . $cal['color'];
                    
                        $cal['start'] = $sd->format("Y-m-d\T01:00");
                        $cal['end'] = $sd->format("Y-m-d\T23:59");
                        $cal['title'] = ' ';
                        $cal['textColor'] = $cal['color'];
                        $sd->modify('+1 day');

                        // Can not continue / drop until sd has been modified.
                        // (Avoiding eternal loops.)
                        if (isset($summary_arr[$s]))
                            continue;

                        $arr[] = $cal;
                        $summary_arr[$s] = true;
                    }
                } else {
                    /*
                     * This one does not add a dot for each day in the shift.
                     */
                    $s = $sd->format("Ymd") . $cal['color'];
                    $cal['start'] = $sd->format("Y-m-d\T01:00");
                    $cal['end'] = $sd->format("Y-m-d\T10:00");
                    $cal['title'] = ' ';
                    $cal['textColor'] = $cal['color'];

                    // Can not continue / drop until sd has been modified.
                    // (Avoiding eternal loops.)
                    if (isset($summary_arr[$s]))
                        continue;
                    // Can't do popups for some random (first of the day) job.
                    $cal['popup_title'] = "";
                    $cal['popup_content'] = "";
                    $arr[] = $cal;
                    $summary_arr[$s] = true;
                }
            }
        }
        return $arr;
    }

    public function toFullCalendarArray($frogs, $options = [])
    {
        $this->options = $options;
        $arr = array();
        foreach ($frogs as $frog) {
            if ($cal = $this->_calToFullCal($frog))
                $arr[] = $cal;
        }
        return $arr;
    }

    /*
     * We need: (but can do with more)
     *  - Id
     *  - Start
     *  - End
     *  - Title
     *  - Allday (true if it is)
     *  - Url (But I'm not sure this is the right place, and we do need two
     *    of'em. iCal and to the event/shift(function) itself.
     *
     */
    private function _calToFullCal($frog)
    {
        if ($frog instanceof Event) {
            $cal = $this->_eventToCal($frog);
        } elseif ($frog instanceof Shift) {
            $cal = $this->_shiftToCal($frog);
        } elseif ($frog instanceof Job) {
            $cal = $this->_jobToCal($frog);
        } elseif ($frog instanceof PersonState) {
            $cal = $this->_personStateToCal($frog);
        } else {
            throw new \InvalidArgumentException("Could not do anything useful with "
                . get_class($frog));
        }
        if (!$cal) return null;
        $fc['id'] = $cal['id'];
        $fc['title'] = $cal['title'];
        $fc['content'] = $cal['content'] ?? '';
        $fc['popup_title'] = $cal['popup_title'] ?? '';
        $fc['popup_content'] = $cal['popup_content'] ?? '';
        $fc['start'] = $cal['start']->format("Y-m-d\TH:i:sP");
        if ($cal['end'])
            $fc['end'] = $cal['end']->format("Y-m-d\TH:i:sP");
        else
            $fc['end'] = null;
        /*
        $fc['className'] = $cal[''];
        $fc['rendering'] = $cal[''];
        $fc['constraint'] = $cal[''];
        $fc['source'] = $cal[''];
        */
        if (isset($cal['color']))
            $fc['color'] = $cal['color'];
        if (isset($cal['backgroundColor']))
            $fc['backgroundColor'] = $cal['backgroundColor'];
        if (isset($cal['borderColor']))
            $fc['borderColor'] = $cal['borderColor'];
        if (isset($cal['textColor']))
            $fc['textColor'] = $cal['textColor'];

        $fc['allDay'] = $cal['allDay'] ?? false;
        $fc['overlap'] = false;
        $fc['editable'] = false;
        $fc['startEditable'] = false;
        $fc['durationEditable'] = false;
        $fc['resourceEditable'] = false;

        if (($this->options['event_url'] ?? true) && isset($cal['url']))
            $fc['url'] = $cal['url'];
        return $fc;
    }

    private function _eventToCal(Event $event)
    {
        // Pretty complex, but it does use the ID to make sure we use the same
        // colour on the same (sub) event.
        $phi = 0.618033988749895;
        $phi = (1 + sqrt(5))/2;
        $id =  $event->getId();
        if ($event->getParent()) $id = $event->getParent()->getId();
        $id = $id * 3.14;
        $n = $id * $phi - floor($id * $phi);
        $hue = floor($n * 256);
        $col = $this->_hslToRgb( $hue, 0.5, 0.7 );

        $c = array();
        $c['id'] = $event->getId();
        $c['title'] = $event->getName();
        $c['start'] = $event->getStart();
        $c['end'] = $event->getEnd();
        $c['color'] = "#" . $col;
        $c['textColor'] = "black";
        $c['content'] = 
              'What: ' . (string)$event . "\n"
            . 'Where: ' . (string)$event->getLocation() . "\n"
            . 'When: ' . $event->getStart()->format('H:i') 
                . " -> " . $event->getEnd()->format('H:i') . "\n";

        $c['popup_title'] = (string)$event;

        $c['popup_content'] = preg_replace("/\n/", "<br />"
            , $c['content']);

        if ($this->options['event_url'] ?? true) {
            $url =  $this->router->generate('event_show',
                array('id' => $event->getId()));
            $c['popup_content'] .= '<br><a href="'
                . $url  . '">Go to event</a>';
        }

        return $c;
    }

    private function _jobToCal(Job $job)
    {
        $c = $this->_shiftToCal($job->getShift());
        if ($this->options['with_times'] ?? false) {
            $c['title'] = $job->getStart()->format('H:i') ." "
                . (string)$job->getShift();
        } else {
            $c['title'] = (string)$job->getShift();
        }
        if ($job->getState() == "CONFIRMED") {
            $c['color'] = "green";
            $c['textColor'] = "white";
        } elseif ($job->getState() == "ASSIGNED") {
            $c['color'] = "orange";
            $c['textColor'] = "black";
        } else {
            $c['color'] = "red";
            $c['textColor'] = "white";
        }

        $c['content'] = 
            'Event: ' . (string)$job->getEvent() . "\n"
            . 'Where: ' . (string)$job->getLocation() . "\n"
            . 'When: ' . $job->getStart()->format('H:i') 
                . " -> " . $job->getEnd()->format('H:i') . "\n"
            . 'Work: ' . (string)$job->getFunction() . "\n"
        ;

        $c['popup_title'] = (string)$job->getEvent();
        $c['popup_content'] = preg_replace("/\n/", "<br />"
            , $c['content']);

        if ($this->options['ical_add_url'] ?? false) {
            $iadd_url = $this->router->generate('uf_job_calendar_item', 
                        array('id' => $job->getId()));
            $c['popup_content'] .= '<br><a href="'
                . $iadd_url  . '">Add to calendar</a>';
        }

        if ($this->options['event_url'] ?? true) {
            $url =  $this->router->generate('event_show',
                array('id' => $job->getEvent()->getId()));
            $c['popup_content'] .= '<br><a href="'
                . $url  . '">Go to event</a>';
        }

        return $c;
    }

    private function _shiftToCal(Shift $shift)
    {
        $c = array();
        $c['id'] = $shift->getId();
        $c['start'] = $shift->getStart();
        $c['end'] = $shift->getEnd();
        $c['title'] = (string)$shift->getFunction();
        return $c;
    }

    private function _personStateToCal(PersonState $ps)
    {
        if ($ps->getState() == "ACTIVE") return null;
        $c = array();
        $c['id'] = $ps->getId();
        $c['start'] = $ps->getFromDate();
        if (!$ps->getToDate())
            $td = new \DateTime("first day of next year");
        else
            $td = $ps->getToDate();
        // Gotta set 25:59 to make sure the to_day is included.
        $c['end'] = $td->modify("23:59");
        $c['title'] = (string)$ps->getStateLabel();
        $c['popup_content'] = (string)$ps->getStateLabel();
        $c['color'] = "blue";
        $c['textColor'] = "white";
        return $c;
    }

    // Nicked from https://gist.github.com/brandonheyer/5254516
    private function _hslToRgb( $h, $s, $l )
    {
        $r; 
        $g; 
        $b;
        $c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
        $x = $c * ( 1 - abs( fmod( ( $h / 60 ), 2 ) - 1 ) );
        $m = $l - ( $c / 2 );
        if ( $h < 60 ) {
            $r = $c;
            $g = $x;
            $b = 0;
        } else if ( $h < 120 ) {
            $r = $x;
            $g = $c;
            $b = 0;         
        } else if ( $h < 180 ) {
            $r = 0;
            $g = $c;
            $b = $x;                    
        } else if ( $h < 240 ) {
            $r = 0;
            $g = $x;
            $b = $c;
        } else if ( $h < 300 ) {
            $r = $x;
            $g = 0;
            $b = $c;
        } else {
            $r = $c;
            $g = 0;
            $b = $x;
        }
        $r = floor(( $r + $m ) * 255);
        $g = floor(( $g + $m ) * 255);
        $b = floor(( $b + $m ) * 255);
        $wcol = str_pad(dechex(round($r)), 2, "0", STR_PAD_LEFT);
        $wcol .= str_pad(dechex(round($g)), 2, "0", STR_PAD_LEFT);
        $wcol .= str_pad(dechex(round($b)), 2, "0", STR_PAD_LEFT);
        return $wcol;
        return array( floor( $r ), floor( $g ), floor( $b ) );
    }
}
