<?php

namespace Illuminate\Contracts\Foundation;

interface CachesRoutes
{
    /**
     * Determine if the application routes are cached.
     *
     *  bool
     */
    public function routesAreCached();

    /**
     * Get the path to the routes cache file.
     *
     *  string
     */
    public function getCachedRoutesPath();
}
