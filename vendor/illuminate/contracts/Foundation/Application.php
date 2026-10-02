<?php

namespace Illuminate\Contracts\Foundation;

use Illuminate\Contracts\Container\Container;

interface Application extends Container
{
    /**
     * Get the version number of the application.
     *
     *  string
     */
    public function version();

    /**
     * Get the base path of the Laravel installation.
     *
     * @param  string  $path
     *  string
     */
    public function basePath($path = '');

    /**
     * Get the path to the bootstrap directory.
     *
     * @param  string  $path
     *  string
     */
    public function bootstrapPath($path = '');

    /**
     * Get the path to the application configuration files.
     *
     * @param  string  $path
     *  string
     */
    public function configPath($path = '');

    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     *  string
     */
    public function databasePath($path = '');

    /**
     * Get the path to the language files.
     *
     * @param  string  $path
     *  string
     */
    public function langPath($path = '');

    /**
     * Get the path to the public directory.
     *
     * @param  string  $path
     *  string
     */
    public function publicPath($path = '');

    /**
     * Get the path to the resources directory.
     *
     * @param  string  $path
     *  string
     */
    public function resourcePath($path = '');

    /**
     * Get the path to the storage directory.
     *
     * @param  string  $path
     *  string
     */
    public function storagePath($path = '');

    /**
     * Get or check the current application environment.
     *
     * @param  \UnitEnum|string|array  ...$environments
     *  string|bool
     */
    public function environment(...$environments);

    /**
     * Determine if the application is running in the console.
     *
     *  bool
     */
    public function runningInConsole();

    /**
     * Determine if the application is running unit tests.
     *
     *  bool
     */
    public function runningUnitTests();

    /**
     * Determine if the application is running with debug mode enabled.
     *
     *  bool
     */
    public function hasDebugModeEnabled();

    /**
     * Get an instance of the maintenance mode manager implementation.
     *
     *  \Illuminate\Contracts\Foundation\MaintenanceMode
     */
    public function maintenanceMode();

    /**
     * Determine if the application is currently down for maintenance.
     *
     *  bool
     */
    public function isDownForMaintenance();

    /**
     * Register all of the configured providers.
     *
     *  void
     */
    public function registerConfiguredProviders();

    /**
     * Register a service provider with the application.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @param  bool  $force
     *  \Illuminate\Support\ServiceProvider
     */
    public function register($provider, $force = false);

    /**
     * Register a deferred provider and service.
     *
     * @param  string  $provider
     * @param  string|null  $service
     *  void
     */
    public function registerDeferredProvider($provider, $service = null);

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string  $provider
     *  \Illuminate\Support\ServiceProvider
     */
    public function resolveProvider($provider);

    /**
     * Boot the application's service providers.
     *
     *  void
     */
    public function boot();

    /**
     * Register a new boot listener.
     *
     * @param  callable  $callback
     *  void
     */
    public function booting($callback);

    /**
     * Register a new "booted" listener.
     *
     * @param  callable  $callback
     *  void
     */
    public function booted($callback);

    /**
     * Run the given array of bootstrap classes.
     *
     * @param  array  $bootstrappers
     *  void
     */
    public function bootstrapWith(array $bootstrappers);

    /**
     * Get the current application locale.
     *
     *  string
     */
    public function getLocale();

    /**
     * Get the application namespace.
     *
     *  string
     *
     * @throws \RuntimeException
     */
    public function getNamespace();

    /**
     * Get the registered service provider instances if any exist.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     *  array
     */
    public function getProviders($provider);

    /**
     * Determine if the application has been bootstrapped before.
     *
     *  bool
     */
    public function hasBeenBootstrapped();

    /**
     * Load and boot all of the remaining deferred providers.
     *
     *  void
     */
    public function loadDeferredProviders();

    /**
     * Set the current application locale.
     *
     * @param  string  $locale
     *  void
     */
    public function setLocale($locale);

    /**
     * Determine if middleware has been disabled for the application.
     *
     *  bool
     */
    public function shouldSkipMiddleware();

    /**
     * Register a terminating callback with the application.
     *
     * @param  callable|string  $callback
     *  \Illuminate\Contracts\Foundation\Application
     */
    public function terminating($callback);

    /**
     * Terminate the application.
     *
     *  void
     */
    public function terminate();
}
