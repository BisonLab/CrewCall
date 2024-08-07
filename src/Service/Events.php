<?php

namespace App\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use BisonLab\SakonninBundle\Service\Messages as SakonninMessages;
use App\Entity\Event;
use App\Entity\Shift;

class Events
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SakonninMessages $sakonninMessages,
    ) {
    }

    public function cloneEvent(Event $orig, Event $clone)
    {
        // First, find the difference in time between original and clone.
        $diff = $orig->getStart()->diff($clone->getStart());
        $nend = clone($orig->getEnd());
        $clone->setEnd($nend->add($diff));
        if (!$clone->getState())
            $clone->setState(Event::getStatesList()[0]);
        
        foreach ($orig->getShifts() as $shift) {
            $ns = new Shift();
            $ns->setAmount($shift->getAmount());
            $ns->setFunction($shift->getFunction());
            $nsstart = clone($shift->getStart());
            $ns->setStart($nsstart->add($diff));
            $nsend = clone($shift->getEnd());
            $ns->setEnd($nsend->add($diff));
            $ns->setState(Shift::getStatesList()[0]);
            $ns->setNotes($shift->getNotes());
            // Cascade persist should have fixed this. But I have to use
            // prePersist for state change handling. (As far as I have found)
            $clone->addShift($ns);
            $this->entityManager->persist($ns);
            $this->_cloneMessages($shift, $ns);
        }

        foreach ($orig->getChildren() as $child) {
            $new_child = new Event();
            // What about the name?
            $new_child->setName($child->getName());
            $new_child->setDescription($child->getDescription());
            $new_child->setOrganization($child->getOrganization());
            $new_child->setLocation($child->getLocation());
            $cstart = clone($child->getStart());
            $new_child->setStart($cstart->add($diff));
            $nc = $this->cloneEvent($child, $new_child);
            $clone->addChild($nc);
        }
        $this->entityManager->persist($clone);
        $this->_cloneMessages($orig, $clone);
        
        return $clone;
    }

    // Clone (almost) all checks, notes and info.
    private function _cloneMessages($orig, $clone)
    {
        // Now we also have notes.
        $clone->setNotes($orig->getNotes());

        $object_name = "shift";
        if ($orig instanceOf Event)
            $object_name = "event";

        $orig_context = [
                'system' => 'crewcall',
                'object_name' => $object_name,
                'external_id' => $orig->getId()
        ];
        $clone_context = [
                'system' => 'crewcall',
                'object_name' => $object_name,
                'external_id' => $clone->getId()
        ];
        foreach ($this->sakonninMessages->getMessagesForContext($orig_context) as $orig_msg) { 
            // TODO: See if we want a white or black -list. Starting
            // with a blacklist/exclude.
            if ($orig_msg->getMessageType()->getName() == "List Sent")
                continue;
            $clone_msg = [
                'subject' => $orig_msg->getSubject(),
                'body' => $orig_msg->getBody(),
                'header' => $orig_msg->getHeader(),
                'message_type' => $orig_msg->getMessageType()->getName(),
                'to_type' => $orig_msg->getToType(),
                'from_type' => $orig_msg->getFromType(),
                'content_type' => $orig_msg->getContentType(),
            ];
            $this->sakonninMessages->postMessage($clone_msg, $clone_context);
        }
    }
}
