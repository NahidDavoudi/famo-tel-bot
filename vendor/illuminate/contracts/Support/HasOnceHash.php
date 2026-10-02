<?php

namespace Illuminate\Contracts\Support;

interface HasOnceHash
{
    /**
     * Compute the hash that should be used to represent the object when given to a function using "once".
     *
     *  string
     */
    public function onceHash();
}
