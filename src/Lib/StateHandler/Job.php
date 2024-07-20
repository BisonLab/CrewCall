<?php

namespace App\Lib\StateHandler;

class Job
{
    private $container;
    private $em;
    private $sm;

    public function __construct($container)
    {
        $this->container = $container;
        $this->em = $container->get('doctrine')->getManager();
        $this->sm = $container->get('sakonnin.messages');
    }

    public function handle(\App\Entity\Job $job, $from, $to)
    {
        $notify_methods = $this->container->getParameter('job_notification_methods:');

        if ($to == "CONFIRMED" || $to == "ASSIGNED") {
            // Create a message.
            $data = array(
                'job'    => $job,
                'event'  => $job->getEvent(),
                'person' => $job->getPerson(),
                'function' => $job->getFunction(),
            );
            if ($to == "CONFIRMED")
                $template = 'confirm';
            if ($to == "ASSIGNED")
                $template = 'assigned';

            if (in_array('sms', $notify_methods)) {
                $this->sm->postMessage([
                    'template' => $template - '-sms',
                    'template_data' => $data,
                    'subject' => "Confirmation",
                    'to_type' => "INTERNAL",
                    'from_type' => "INTERNAL",
                    'message_type' => "BULKSMS"
                ],
                [
                    'system' => 'crewcall',
                    'object_name' => 'person',
                    'external_id' => $job->getPerson()->getId(),
                ]);
            }
            if (in_array('mail', $notify_methods)) {
                $this->sm->postMessage([
                    'template' => $template - '-mail',
                    'template_data' => $data,
                    'subject' => "Confirmation",
                    'to_type' => "INTERNAL",
                    'from_type' => "INTERNAL",
                    'message_type' => "BULKMAIL"
                ],
                [
                    'system' => 'crewcall',
                    'object_name' => 'person',
                    'external_id' => $job->getPerson()->getId(),
                ]);
            }
            if (in_array('pm', $notify_methods)) {
                $this->sm->postMessage([
                    'template' => $template - '-mail',
                    'template_data' => $data,
                    'subject' => "Confirmation",
                    'to_type' => "INTERNAL",
                    'from_type' => "INTERNAL",
                    'message_type' => "PM"
                ],
                [
                    'system' => 'crewcall',
                    'object_name' => 'person',
                    'external_id' => $job->getPerson()->getId(),
                ]);
            }
        }
    }
}
