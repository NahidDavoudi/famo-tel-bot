<?php

namespace Illuminate\Contracts\Process;

interface InvokedProcess
{
    /**
     * Get the process ID if the process is still running.
     *
     *  int|null
     */
    public function id();

    /**
     * Get the command line for the process.
     *
     *  string
     */
    public function command();

    /**
     * Send a signal to the process.
     *
     * @param  int  $signal
     *  $this
     */
    public function signal(int $signal);

    /**
     * Determine if the process is still running.
     *
     *  bool
     */
    public function running();

    /**
     * Get the standard output for the process.
     *
     *  string
     */
    public function output();

    /**
     * Get the error output for the process.
     *
     *  string
     */
    public function errorOutput();

    /**
     * Get the latest standard output for the process.
     *
     *  string
     */
    public function latestOutput();

    /**
     * Get the latest error output for the process.
     *
     *  string
     */
    public function latestErrorOutput();

    /**
     * Wait for the process to finish.
     *
     * @param  callable|null  $output
     *  \Illuminate\Process\ProcessResult
     */
    public function wait(?callable $output = null);

    /**
     * Wait until the given callback returns true.
     *
     * @param  callable|null  $output
     *  \Illuminate\Process\ProcessResult
     */
    public function waitUntil(?callable $output = null);
}
