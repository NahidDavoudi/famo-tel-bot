<?php

namespace Illuminate\Contracts\Encryption;

interface Encrypter
{
    /**
     * Encrypt the given value.
     *
     * @param  mixed  $value
     * @param  bool  $serialize
     *  string
     *
     * @throws \Illuminate\Contracts\Encryption\EncryptException
     */
    public function encrypt(#[\SensitiveParameter] $value, $serialize = true);

    /**
     * Decrypt the given value.
     *
     * @param  string  $payload
     * @param  bool  $unserialize
     *  mixed
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public function decrypt($payload, $unserialize = true);

    /**
     * Get the encryption key that the encrypter is currently using.
     *
     *  string
     */
    public function getKey();

    /**
     * Get the current encryption key and all previous encryption keys.
     *
     *  array
     */
    public function getAllKeys();

    /**
     * Get the previous encryption keys.
     *
     *  array
     */
    public function getPreviousKeys();
}
