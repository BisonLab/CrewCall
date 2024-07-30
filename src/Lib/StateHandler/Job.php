<?php

namespace App\Lib\StateHandler;

use BisonLab\SakonninBundle\Service\Messages as SakonninMessages;
use App\Entity\Job as JobEntity;

class Job
{
    public function __construct(
        private SakonninMessages $sakonninMessages,
    ) {
    }

    public function getStateHandleClass()
    {
        return JobEntity::class;
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
                $this->sakonninMessages->postMessage([
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
                $this->sakonninMessages->postMessage([
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
                $this->sakonninMessages->postMessage([
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
