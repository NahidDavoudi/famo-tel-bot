<?php

namespace Illuminate\Contracts\Queue;

interface QueueableEntity
{
    /**
     * Get the queueable identity for the entity.
     *
     *  mixed
     */
    public function getQueueableId();

    /**
     * Get the relationships for the entity.
     *
     *  array
     */
    public function getQueueableRelations();

    /**
     * Get the connection of the entity.
     *
     *  string|null
     */
    public function getQueueableConnection();
}
