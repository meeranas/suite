<?php

namespace App\Services\Encryption;

use Illuminate\Support\Facades\Crypt;

class EncryptionService
{
    /**
     * Encrypt sensitive data (API keys, secrets)
     */
    public function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt(string $encryptedValue): string
    {
        return Crypt::decryptString($encryptedValue);
    }

    /**
     * Encrypt API key for storage
     */
    public function encryptApiKey(string $apiKey): string
    {
        return $this->encrypt($apiKey);
    }

    /**
     * Decrypt API key for use
     */
    public function decryptApiKey(string $encryptedApiKey): string
    {
        return $this->decrypt($encryptedApiKey);
    }
}

