<?php

namespace App\Entity;

/*
 * In case of collection based notes, make an entity.
 * Thiis is instead of using sakonnin for notes. Makes it all a lot speedier.
 */
class Notes
{
    /*
     * @var uniqid
     */
    private $id;

    /*
     * @var string
     *
     * Could "define" this as an enum as other types.
     * The existing/planned types are:
     * "CrewNote", "ConfirmCheck", "InformCheck"
     */
    private $note_type;

    /*
     * @var string
     */
    private $subject;

    /*
     * @var string
     */
    private $body;

    public function __construct($options = array())
    {
        $this->id = $options['id'] ?? uniqid();
        $this->note_type = $options['type'] ?? $options['note_type'] ?? '';
        $this->subject = $options['subject'] ?? '';
        $this->body = $options['body'] ?? '';
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @param string $type
     * @return Role
     */
    public function setNoteType($note_type)
    {
        $this->note_type = $note_type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string 
     */
    public function getNoteType()
    {
        return $this->note_type;
    }

    /**
     *
     * @param string $subject
     * @return Note
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Get subject
     *
     * @return string 
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     *
     * @param string $body
     * @return Note
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get body
     *
     * @return string 
     */
    public function getBody()
    {
        return $this->body;
    }
}
