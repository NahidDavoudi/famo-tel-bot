<?php

namespace Illuminate\Contracts\Routing;

interface BindingRegistrar
{
    /**
     * Add a new route parameter binder.
     *
     * @param  string  $key
     * @param  string|callable  $binder
     *  void
     */
    public function bind($key, $binder);

    /**
     * Get the binding callback for a given binding.
     *
     * @param  string  $key
     *  \Closure|null
     */
    public function getBindingCallback($key);
}
