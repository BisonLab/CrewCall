<?php

namespace App\Model;

class FullCalendarEvent implements \ArrayAccess
{
    /**
     * @var integer
     */
    protected $id;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var boolean
     */
    protected $allDay = false;

    /**
     * @var \DateTime
     */
    protected $start;

    /**
     * @var \DateTime
     */
    protected $end;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var boolean
     */
    protected $editable = false;

    /**
     * @var boolean
     */
    protected $startEditable = false;

    /**
     * @var boolean
     */
    protected $durationEditable = false;

    /**
     * @var boolean
     */
    protected $resourceEditable = false;

    /**
     * @var string
     */
    protected $rendering;

    /**
     * @var boolean
     */
    protected $overlap = false;

    /**
     * @var integer
     */
    protected $constraint;

    /**
     * @var string
     */
    protected $source;

    /**
     * @var string
     */
    protected $color;

    /**
     * @var string
     */
    protected $backgroundColor;

    /**
     * @var string
     */
    protected $borderColor;

    /**
     * @var string
     */
    protected $textColor;

    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (property_exists($this, $offset)) {
            $this->$offset = $value;
            return;
        }
        throw new \InvalidArgumentException("Something tried to set " . $offset . " which does not exist");
    }

    public function offsetUnset(mixed $offset): void
    {
        return;
    }

    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }

    public function toArray()
    {
        return array('title' => 'Blah');
    }
}
