<?php

namespace Illuminate\Contracts\Validation;

interface DataAwareRule
{
    /**
     * Set the data under validation.
     *
     * @param  array  $data
     *  $this
     */
    public function setData(array $data);
}
