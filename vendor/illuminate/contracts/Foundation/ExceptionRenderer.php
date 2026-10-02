<?php

namespace Illuminate\Contracts\Foundation;

interface ExceptionRenderer
{
    /**
     * Renders the given exception as HTML.
     *
     * @param  \Throwable  $throwable
     *  string
     */
    public function render($throwable);
}
