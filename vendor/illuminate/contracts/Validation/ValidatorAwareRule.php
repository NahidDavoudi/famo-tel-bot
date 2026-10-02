<?php

namespace Illuminate\Contracts\Validation;

use Illuminate\Validation\Validator;

interface ValidatorAwareRule
{
    /**
     * Set the current validator.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     *  $this
     */
    public function setValidator(Validator $validator);
}
