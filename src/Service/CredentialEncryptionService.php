<?php

namespace App\Service;

class CredentialEncryptionService 
{
    public function __construct(
        private string $encryptionKey,
    ){}

    public function encrypt(array $data): string
    {
        $jsonData = json_encode($data);
        $key = base64_decode($this->encryptionKey);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($jsonData, $nonce, $key);
        return base64_encode($nonce . $encrypted);
    }

    public function decrypt(string $encrypted): array
    {
        $key = base64_decode($this->encryptionKey);
        $decoded = base64_decode($encrypted);
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }
        return json_decode($decrypted, true);
    }
}