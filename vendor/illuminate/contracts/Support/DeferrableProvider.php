<?php

namespace Illuminate\Contracts\Support;

interface DeferrableProvider
{
    /**
     * Get the services provided by the provider.
     *
     *  array
     */
    public function provides();
}
