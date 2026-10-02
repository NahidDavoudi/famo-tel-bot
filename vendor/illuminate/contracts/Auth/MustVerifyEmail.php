<?php

namespace Illuminate\Contracts\Auth;

interface MustVerifyEmail
{
    /**
     * Determine if the user has verified their email address.
     *
     *  bool
     */
    public function hasVerifiedEmail();

    /**
     * Mark the given user's email as verified.
     *
     *  bool
     */
    public function markEmailAsVerified();

    /**
     * Mark the given user's email as unverified.
     *
     *  bool
     */
    public function markEmailAsUnverified();

    /**
     * Send the email verification notification.
     *
     *  void
     */
    public function sendEmailVerificationNotification();

    /**
     * Get the email address that should be used for verification.
     *
     *  string
     */
    public function getEmailForVerification();
}
