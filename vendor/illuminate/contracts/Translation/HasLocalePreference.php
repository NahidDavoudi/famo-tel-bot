<?php

namespace Illuminate\Contracts\Translation;

interface HasLocalePreference
{
    /**
     * Get the preferred locale of the entity.
     *
     *  string|null
     */
    public function preferredLocale();
}
