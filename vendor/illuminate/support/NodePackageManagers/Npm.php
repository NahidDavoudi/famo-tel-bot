<?php

namespace Illuminate\Support\NodePackageManagers;

use Illuminate\Support\Contracts\NodePackageManager;

class Npm implements NodePackageManager
{
    /**
     * Determine if the npm package manager is in use.
     *
     *  bool
     */
    public static function matches(): bool
    {
        return file_exists(getcwd().'/package-lock.json');
    }

    /**
     * Get the command to run a script using npm.
     *
     * @param  string  $command
     *  string
     */
    public function getRunCommand(string $command): string
    {
        return "npm run {$command}";
    }

    /**
     * Get the command to execute a package using npm.
     *
     * @param  string  $command
     *  string
     */
    public function getExecCommand(string $command): string
    {
        return "npx {$command}";
    }
}
