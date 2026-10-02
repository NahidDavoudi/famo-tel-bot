<?php

namespace Illuminate\Contracts\Process;

interface ProcessResult
{
    /**
     * Get the original command executed by the process.
     *
     *  string
     */
    public function command();

    /**
     * Determine if the process was successful.
     *
     *  bool
     */
    public function successful();

    /**
     * Determine if the process failed.
     *
     *  bool
     */
    public function failed();

    /**
     * Get the exit code of the process.
     *
     *  int|null
     */
    public function exitCode();

    /**
     * Get the standard output of the process.
     *
     *  string
     */
    public function output();

    /**
     * Determine if the output contains the given string.
     *
     * @param  string  $output
     *  bool
     */
    public function seeInOutput(string $output);

    /**
     * Get the error output of the process.
     *
     *  string
     */
    public function errorOutput();

    /**
     * Determine if the error output contains the given string.
     *
     * @param  string  $output
     *  bool
     */
    public function seeInErrorOutput(string $output);

    /**
     * Throw an exception if the process failed.
     *
     * @param  callable|null  $callback
     *  $this
     */
    public function throw(?callable $callback = null);

    /**
     * Throw an exception if the process failed and the given condition is true.
     *
     * @param  bool  $condition
     * @param  callable|null  $callback
     *  $this
     */
    public function throwIf(bool $condition, ?callable $callback = null);
}
