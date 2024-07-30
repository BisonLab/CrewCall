<?php

namespace App\Lib\Sakonnin;

use BisonLab\SakonninBundle\Entity\MessageContext;
use BisonLab\SakonninBundle\Lib\Functions\CommonFunctions;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Job;

/*
 */

class SmsHandler
{
    use CommonFunctions;

    public $callback_functions = [];

    public $forward_functions = [
        'smscodehandle' => array(
            'description' => "Act from code and content in SMS.",
            'attribute_spec' => null,
            'needs_attributes' => false,
        ),
    ];

    public function __construct(
        protected ParameterBagInterface $parameterBag,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    /*
     * Returning null on missing code and wrong sender instead of throwing
     * exceptions. Rather have silence than 500 errors.
     */
    public function execute($options = array())
    {
        $codeword = $this->parameterBag->get('sakonnin.sms')['smscode'];

        $message = $options['message'];
        $body = str_replace($codeword, "", strtolower($message->getBody()));

        if (!preg_match("/\s(\w{5,8})\s*/", $body, $umatch))
            return null;
        $ucode = $umatch[1];

        // Gotta find the Job related to the code
        // Does the job exist?
        if (!$job = $this->entityManager->getRepository(Job::class)->findOneBy(['ucode' => $ucode])) {
            return null;
        }
        // The sender matches the job owner?
        $number_length = $this->parameterBag->get('sakonnin.sms')['national_number_lenght'];
        $person = $job->getPerson();
        $snum = substr($message->getFrom(), $number_length * -1);
        $pnum = substr($person->getMobilePhoneNumber(), $number_length * -1);

        if ($snum != $pnum)
            return null;

        // So, what will we be doing then?
        // For now, just look for "Confirm".
        if (preg_match("/CONFIRM/", strtoupper($body)) && $job->getState() == "ASSIGNED") {
            $job->setState("CONFIRMED");
            // Gonna tie the message to the job object.
            $smmanager = $this->sakonninMessages->getDoctrineManager();
            $mc = new MessageContext();
            $mc->setOwner($message);
            $mc->setSystem('crewcall');
            $mc->setObjectName('job');
            $mc->setExternalId($job->getId());
            $smmanager->persist($mc);
            $smmanager->flush();
            $this->entityManager->flush();
        }
        return true;
    }
}
