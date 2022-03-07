<?php

namespace App\Entity;

/*
 * Notes. A collection of'em.
 * You ask yourself "Why are these not entities in a relational table?"
 * Question is good, answer is "Speed". They were, and it took too long.
 */

trait NotesTrait
{
    /**
     * @var array $notes
     *
     * @ORM\Column(name="notes", type="json", nullable=true)
     * @Gedmo\Versioned
     */
    private $notes;

    /**
     * Set all notes
     *
     * @return array
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * Get a specified note
     *
     * @return array
     */
    public function getNote($noteid)
    {
        return $this->notes[$noteid] ?? null;
    }

    /**
     * Get all notes
     *
     * @return array
     */
    public function getNotes(): array
    {
        return $this->notes ?? [];
    }

    /**
     * Add note
     *
     * @param array $note
     * @return Object
     */
    public function addNote(array $note)
    {
        // shorthands later
        if (!isset($note['id']))
            $note['id'] = uniqid();

        if (!isset($note['type']))
            throw new \InvalidArgumentException("This note has no type set. No can do");
        if (!isset($note['subject']))
            $note['subject'] = "";

        $this->notes[$note['id']] = $note;
        return $this;
    }

    /**
     * Add note
     *
     * @param array $note
     * @return Object
     */
    public function removeNote($note)
    {
        if (is_array($note))
            unset($this->notes[$note['id']]);
        else
            unset($this->notes[$note]);
        return $this;
    }

    /**
     * Add note
     *
     * @param Note $note
     * @return Object
     */
    public function updateNote(array $unote)
    {
        if (!$note = $this->getNote($unote['id']))
            return $this;

        $note['type'] = $unote['type'] ?: $note['type'];
        $note['subject'] = $unote['subject'] ?: $note['subject'];
        $note['body'] = $unote['body'] ?: $note['body'];

        $this->notes[$note['id']] = $note;
        return $this;
    }

    /**
     * Get notes by type
     *
     * @return array
     */
    public function getNotesByType($type): array
    {
        $notes = [];
        foreach($this->getNotes() as $note) {
            if ($note['type'] == $type)
                $notes[] = $note;
        }
        return $notes;
    }
}
