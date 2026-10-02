<?php

namespace Illuminate\Contracts\Support;

/**
 * @template TKey of array-key
 * @template TValue
 */
interface Arrayable
{
    /**
     * Get the instance as an array.
     *
     *  array<TKey, TValue>
     */
    public function toArray();
}
