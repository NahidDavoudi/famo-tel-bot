<?php

namespace Illuminate\Contracts\Auth;

interface Authenticatable
{
    /**
     * Get the name of the unique identifier for the user.
     *
     *  string
     */
    public function getAuthIdentifierName();

    /**
     * Get the unique identifier for the user.
     *
     *  mixed
     */
    public function getAuthIdentifier();

    /**
     * Get the name of the password attribute for the user.
     *
     *  string
     */
    public function getAuthPasswordName();

    /**
     * Get the password for the user.
     *
     *  string
     */
    public function getAuthPassword();

    /**
     * Get the token value for the "remember me" session.
     *
     *  string|null
     */
    public function getRememberToken();

    /**
     * Set the token value for the "remember me" session.
     *
     * @param  string  $value
     *  void
     */
    public function setRememberToken($value);

    /**
     * Get the column name for the "remember me" token.
     *
     *  string
     */
    public function getRememberTokenName();
}
