<?php

namespace Illuminate\Contracts\View;

use Illuminate\Contracts\Support\Renderable;

interface View extends Renderable
{
    /**
     * Get the name of the view.
     *
     *  string
     */
    public function name();

    /**
     * Add a piece of data to the view.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     *  $this
     */
    public function with($key, $value = null);

    /**
     * Get the array of view data.
     *
     *  array
     */
    public function getData();
}
