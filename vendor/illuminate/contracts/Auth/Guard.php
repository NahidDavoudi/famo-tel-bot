<?php

namespace Illuminate\Contracts\Auth;

interface Guard
{
    /**
     * Determine if the current user is authenticated.
     *
     *  bool
     */
    public function check();

    /**
     * Determine if the current user is a guest.
     *
     *  bool
     */
    public function guest();

    /**
     * Get the currently authenticated user.
     *
     *  \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user();

    /**
     * Get the ID for the currently authenticated user.
     *
     *  int|string|null
     */
    public function id();

    /**
     * Validate a user's credentials.
     *
     * @param  array  $credentials
     *  bool
     */
    public function validate(array $credentials = []);

    /**
     * Determine if the guard has a user instance.
     *
     *  bool
     */
    public function hasUser();

    /**
     * Set the current user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     *  $this
     */
    public function setUser(Authenticatable $user);
}
