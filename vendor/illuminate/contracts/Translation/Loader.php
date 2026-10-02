<?php

namespace Illuminate\Contracts\Translation;

interface Loader
{
    /**
     * Load the messages for the given locale.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     *  array
     */
    public function load($locale, $group, $namespace = null);

    /**
     * Add a new namespace to the loader.
     *
     * @param  string  $namespace
     * @param  string  $hint
     *  void
     */
    public function addNamespace($namespace, $hint);

    /**
     * Add a new JSON path to the loader.
     *
     * @param  string  $path
     *  void
     */
    public function addJsonPath($path);

    /**
     * Get an array of all the registered namespaces.
     *
     *  array<string, string>
     */
    public function namespaces();
}
