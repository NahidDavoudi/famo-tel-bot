<?php

namespace Illuminate\Contracts\Queue;

interface Job
{
    /**
     * Get the UUID of the job.
     *
     *  string|null
     */
    public function uuid();

    /**
     * Get the job identifier.
     *
     *  string
     */
    public function getJobId();

    /**
     * Get the decoded body of the job.
     *
     *  array
     */
    public function payload();

    /**
     * Fire the job.
     *
     *  void
     */
    public function fire();

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param  int  $delay
     *  void
     */
    public function release($delay = 0);

    /**
     * Determine if the job was released back into the queue.
     *
     *  bool
     */
    public function isReleased();

    /**
     * Delete the job from the queue.
     *
     *  void
     */
    public function delete();

    /**
     * Determine if the job has been deleted.
     *
     *  bool
     */
    public function isDeleted();

    /**
     * Determine if the job has been deleted or released.
     *
     *  bool
     */
    public function isDeletedOrReleased();

    /**
     * Get the number of times the job has been attempted.
     *
     *  int
     */
    public function attempts();

    /**
     * Determine if the job has been marked as a failure.
     *
     *  bool
     */
    public function hasFailed();

    /**
     * Mark the job as "failed".
     *
     *  void
     */
    public function markAsFailed();

    /**
     * Delete the job, call the "failed" method, and raise the failed job event.
     *
     * @param  \Throwable|null  $e
     *  void
     */
    public function fail($e = null);

    /**
     * Get the number of times to attempt a job.
     *
     *  int|null
     */
    public function maxTries();

    /**
     * Get the maximum number of exceptions allowed, regardless of attempts.
     *
     *  int|null
     */
    public function maxExceptions();

    /**
     * Get the number of seconds the job can run.
     *
     *  int|null
     */
    public function timeout();

    /**
     * Get the timestamp indicating when the job should timeout.
     *
     *  int|null
     */
    public function retryUntil();

    /**
     * Get the name of the queued job class.
     *
     *  string
     */
    public function getName();

    /**
     * Get the display name of the queued job class.
     *
     * Resolves the name of "wrapped" jobs such as class-based handlers.
     *
     *  string
     */
    public function resolveName();

    /**
     * Get the class of the queued job.
     *
     * Resolves the class of "wrapped" jobs such as class-based handlers.
     *
     *  string
     */
    public function resolveQueuedJobClass();

    /**
     * Get the name of the connection the job belongs to.
     *
     *  string
     */
    public function getConnectionName();

    /**
     * Get the name of the queue the job belongs to.
     *
     *  string
     */
    public function getQueue();

    /**
     * Get the raw body string for the job.
     *
     *  string
     */
    public function getRawBody();
}
