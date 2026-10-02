<?php

namespace Illuminate\Contracts\View;

interface Engine
{
    /**
     * Get the evaluated contents of the view.
     *
     * @param  string  $path
     * @param  array  $data
     *  string
     */
    public function get($path, array $data = []);
}
