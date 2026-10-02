<?php

namespace Illuminate\Contracts\Auth\Access;

interface Authorizable
{
    /**
     * Determine if the entity has a given ability.
     *
     * @param  \UnitEnum|iterable|string  $abilities
     * @param  mixed  $arguments
     *  bool
     */
    public function can($abilities, $arguments = []);
}
