<?php

namespace Illuminate\Contracts\Filesystem;

interface Factory
{
    /**
     * Get a filesystem implementation.
     *
     * @param  \UnitEnum|string|null  $name
     *  \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function disk($name = null);
}
