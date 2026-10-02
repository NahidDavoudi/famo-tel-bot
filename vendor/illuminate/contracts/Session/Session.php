<?php

namespace Illuminate\Contracts\Session;

interface Session
{
    /**
     * Get the name of the session.
     *
     *  string
     */
    public function getName();

    /**
     * Set the name of the session.
     *
     * @param  string  $name
     *  void
     */
    public function setName($name);

    /**
     * Get the current session ID.
     *
     *  string
     */
    public function getId();

    /**
     * Set the session ID.
     *
     * @param  string  $id
     *  void
     */
    public function setId($id);

    /**
     * Start the session, reading the data from a handler.
     *
     *  bool
     */
    public function start();

    /**
     * Save the session data to storage.
     *
     *  void
     */
    public function save();

    /**
     * Get all of the session data.
     *
     *  array
     */
    public function all();

    /**
     * Checks if a key exists.
     *
     * @param  string|array  $key
     *  bool
     */
    public function exists($key);

    /**
     * Checks if a key is present and not null.
     *
     * @param  string|array  $key
     *  bool
     */
    public function has($key);

    /**
     * Get an item from the session.
     *
     * @param  string  $key
     * @param  mixed  $default
     *  mixed
     */
    public function get($key, $default = null);

    /**
     * Get the value of a given key and then forget it.
     *
     * @param  string  $key
     * @param  mixed  $default
     *  mixed
     */
    public function pull($key, $default = null);

    /**
     * Put a key / value pair or array of key / value pairs in the session.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     *  void
     */
    public function put($key, $value = null);

    /**
     * Flash a key / value pair to the session.
     *
     * @param  string  $key
     * @param  mixed  $value
     *  void
     */
    public function flash(string $key, $value = true);

    /**
     * Get the CSRF token value.
     *
     *  string
     */
    public function token();

    /**
     * Regenerate the CSRF token value.
     *
     *  void
     */
    public function regenerateToken();

    /**
     * Remove an item from the session, returning its value.
     *
     * @param  string  $key
     *  mixed
     */
    public function remove($key);

    /**
     * Remove one or many items from the session.
     *
     * @param  string|array  $keys
     *  void
     */
    public function forget($keys);

    /**
     * Remove all of the items from the session.
     *
     *  void
     */
    public function flush();

    /**
     * Flush the session data and regenerate the ID.
     *
     *  bool
     */
    public function invalidate();

    /**
     * Generate a new session identifier.
     *
     * @param  bool  $destroy
     *  bool
     */
    public function regenerate($destroy = false);

    /**
     * Generate a new session ID for the session.
     *
     * @param  bool  $destroy
     *  bool
     */
    public function migrate($destroy = false);

    /**
     * Determine if the session has been started.
     *
     *  bool
     */
    public function isStarted();

    /**
     * Get the previous URL from the session.
     *
     *  string|null
     */
    public function previousUrl();

    /**
     * Set the "previous" URL in the session.
     *
     * @param  string  $url
     *  void
     */
    public function setPreviousUrl($url);

    /**
     * Get the session handler instance.
     *
     *  \SessionHandlerInterface
     */
    public function getHandler();

    /**
     * Determine if the session handler needs a request.
     *
     *  bool
     */
    public function handlerNeedsRequest();

    /**
     * Set the request on the handler instance.
     *
     * @param  \Illuminate\Http\Request  $request
     *  void
     */
    public function setRequestOnHandler($request);
}
