<?php

namespace Illuminate\Contracts\Foundation;

interface CachesConfiguration
{
    /**
     * Determine if the application configuration is cached.
     *
     *  bool
     */
    public function configurationIsCached();

    /**
     * Get the path to the configuration cache file.
     *
     *  string
     */
    public function getCachedConfigPath();

    /**
     * Get the path to the cached services.php file.
     *
     *  string
     */
    public function getCachedServicesPath();
}
