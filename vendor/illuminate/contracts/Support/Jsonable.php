<?php

namespace Illuminate\Contracts\Support;

interface Jsonable
{
    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     *  string
     */
    public function toJson($options = 0);
}
