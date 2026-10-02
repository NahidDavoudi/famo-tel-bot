<?php

namespace Illuminate\Contracts\Queue;

interface QueueableCollection
{
    /**
     * Get the type of the entities being queued.
     *
     *  string|null
     */
    public function getQueueableClass();

    /**
     * Get the identifiers for all of the entities.
     *
     *  array<int, mixed>
     */
    public function getQueueableIds();

    /**
     * Get the relationships of the entities being queued.
     *
     *  array<int, string>
     */
    public function getQueueableRelations();

    /**
     * Get the connection of the entities being queued.
     *
     *  string|null
     */
    public function getQueueableConnection();
}
