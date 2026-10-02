<?php

namespace Illuminate\Contracts\Auth\Access;

interface Gate
{
    /**
     * Determine if a given ability has been defined.
     *
     * @param  \UnitEnum|string  $ability
     *  bool
     */
    public function has($ability);

    /**
     * Define a new ability.
     *
     * @param  \UnitEnum|string  $ability
     * @param  callable|string  $callback
     *  $this
     */
    public function define($ability, $callback);

    /**
     * Define abilities for a resource.
     *
     * @param  string  $name
     * @param  string  $class
     * @param  array|null  $abilities
     *  $this
     */
    public function resource($name, $class, ?array $abilities = null);

    /**
     * Define a policy class for a given class type.
     *
     * @param  string  $class
     * @param  string  $policy
     *  $this
     */
    public function policy($class, $policy);

    /**
     * Register a callback to run before all Gate checks.
     *
     * @param  callable  $callback
     *  $this
     */
    public function before(callable $callback);

    /**
     * Register a callback to run after all Gate checks.
     *
     * @param  callable  $callback
     *  $this
     */
    public function after(callable $callback);

    /**
     * Determine if all of the given abilities should be granted for the current user.
     *
     * @param  iterable|\UnitEnum|string  $ability
     * @param  mixed  $arguments
     *  bool
     */
    public function allows($ability, $arguments = []);

    /**
     * Determine if any of the given abilities should be denied for the current user.
     *
     * @param  iterable|\UnitEnum|string  $ability
     * @param  mixed  $arguments
     *  bool
     */
    public function denies($ability, $arguments = []);

    /**
     * Determine if all of the given abilities should be granted for the current user.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  mixed  $arguments
     *  bool
     */
    public function check($abilities, $arguments = []);

    /**
     * Determine if any one of the given abilities should be granted for the current user.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  mixed  $arguments
     *  bool
     */
    public function any($abilities, $arguments = []);

    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @param  \UnitEnum|string  $ability
     * @param  mixed  $arguments
     *  \Illuminate\Auth\Access\Response
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize($ability, $arguments = []);

    /**
     * Inspect the user for the given ability.
     *
     * @param  \UnitEnum|string  $ability
     * @param  mixed  $arguments
     *  \Illuminate\Auth\Access\Response
     */
    public function inspect($ability, $arguments = []);

    /**
     * Get the raw result from the authorization callback.
     *
     * @param  string  $ability
     * @param  mixed  $arguments
     *  mixed
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function raw($ability, $arguments = []);

    /**
     * Get a policy instance for a given class.
     *
     * @param  object|string  $class
     *  mixed
     *
     * @throws \InvalidArgumentException
     */
    public function getPolicyFor($class);

    /**
     * Get a guard instance for the given user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|mixed  $user
     *  static
     */
    public function forUser($user);

    /**
     * Get all of the defined abilities.
     *
     *  array
     */
    public function abilities();
}
