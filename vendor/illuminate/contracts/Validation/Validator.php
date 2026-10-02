<?php

namespace Illuminate\Contracts\Validation;

use Illuminate\Contracts\Support\MessageProvider;

interface Validator extends MessageProvider
{
    /**
     * Run the validator's rules against its data.
     *
     *  array
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate();

    /**
     * Get the attributes and values that were validated.
     *
     *  array
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validated();

    /**
     * Determine if the data fails the validation rules.
     *
     *  bool
     */
    public function fails();

    /**
     * Get the failed validation rules.
     *
     *  array
     */
    public function failed();

    /**
     * Add conditions to a given field based on a Closure.
     *
     * @param  string|array  $attribute
     * @param  string|array  $rules
     * @param  callable  $callback
     *  $this
     */
    public function sometimes($attribute, $rules, callable $callback);

    /**
     * Add an after validation callback.
     *
     * @param  callable|string  $callback
     *  $this
     */
    public function after($callback);

    /**
     * Get all of the validation error messages.
     *
     *  \Illuminate\Support\MessageBag
     */
    public function errors();
}
