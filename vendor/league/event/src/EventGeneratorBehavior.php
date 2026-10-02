<?php

declare(strict_types=1);

namespace League\Event;

trait EventGeneratorBehavior
{
    /**
     * @var object[]
     */
    protected $events = [];

    /**
     *  $this
     */
    protected function recordEvent(object $event): self
    {
        $this->events[] = $event;

        return $this;
    }

    /**
     *  object[]
     */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
