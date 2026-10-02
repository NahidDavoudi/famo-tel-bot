<?php

namespace Illuminate\Contracts\Support;

interface Htmlable
{
    /**
     * Get content as a string of HTML.
     *
     *  string
     */
    public function toHtml();
}
