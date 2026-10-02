<?php

namespace Illuminate\Contracts\Hashing;

interface Hasher
{
    /**
     * Get information about the given hashed value.
     *
     * @param  string  $hashedValue
     *  array
     */
    public function info($hashedValue);

    /**
     * Hash the given value.
     *
     * @param  string  $value
     * @param  array  $options
     *  string
     */
    public function make(#[\SensitiveParameter] $value, array $options = []);

    /**
     * Check the given plain value against a hash.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @param  array  $options
     *  bool
     */
    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []);

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param  string  $hashedValue
     * @param  array  $options
     *  bool
     */
    public function needsRehash($hashedValue, array $options = []);
}
