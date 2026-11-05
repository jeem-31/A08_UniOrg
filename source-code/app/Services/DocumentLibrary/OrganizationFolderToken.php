<?php

namespace App\Services\DocumentLibrary;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use JsonException;

final readonly class OrganizationFolderToken
{
    public function __construct(private Encrypter $encrypter) {}

    public function issue(OrganizationFolderIdentity $identity): string
    {
        return $this->encrypter->encryptString(json_encode([
            'version' => 1,
            'identity' => $identity->key(),
        ], JSON_THROW_ON_ERROR));
    }

    public function verify(?string $token): ?string
    {
        if ($token === null || $token === '' || strlen($token) > 4096) {
            return null;
        }

        try {
            $payload = json_decode($this->encrypter->decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($payload) || ($payload['version'] ?? null) !== 1 || ! is_string($payload['identity'] ?? null)) {
            return null;
        }

        $identity = $payload['identity'];

        return preg_match('/^(?:linked:[1-9]\d*|legacy:[a-f0-9]{64})$/D', $identity) === 1
            ? $identity
            : null;
    }
}
