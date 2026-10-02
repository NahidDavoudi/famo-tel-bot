<?php

namespace Illuminate\Contracts\Redis;

interface Factory
{
    /**
     * Get a Redis connection by name.
     *
     * @param  \UnitEnum|string|null  $name
     *  \Illuminate\Redis\Connections\Connection
     */
    public function connection($name = null);
}
