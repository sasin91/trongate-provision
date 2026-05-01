<?php

/**
 * Standalone Encryptor
 * 
 * OpenSSL wrapper with no framework dependencies.
 * Used by both the Encryption Trongate module and Runtime worker.
 */
class Encryptor
{
    private const KEY_BYTES = 32;
    private const CIPHER = 'AES-256-CBC';
    private const IV_BYTES = 16;

    public static function validate_key(string $key): string
    {
        if (empty($key)) {
            throw new InvalidArgumentException('key must not be empty.');
        }

        if (strlen($key) !== self::KEY_BYTES) {
            $decoded = base64_decode($key, true);
            if ($decoded !== false) {
                $key = $decoded;
            }
        }

        if (strlen($key) !== self::KEY_BYTES) {
            throw new InvalidArgumentException(
                $decoded === false
                    ? 'key must be 32 bytes'
                    : 'key must be 32 bytes (base64 encoded)'
            );
        }

        return $key;
    }

    /**
     * Encrypt a string
     * @param string $plaintext
     * @param string $key
     * @return string Encrypted payload
     */
    public static function encrypt(string $plaintext, string $key): string
    {
        $key = self::validate_key($key);
        $iv = random_bytes(self::IV_BYTES);
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new RuntimeException('OpenSSL encryption failed.');
        }

        return base64_encode($iv . $ciphertext);
    }

    /**
     * Decrypt a string
     * @param string $encrypted Encrypted payload
     * @param string $key
     * @return string|false Plaintext or false on failure
     */
    public static function decrypt(string $encrypted, string $key): string|false
    {
        $key = self::validate_key($key);
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false || strlen($decoded) <= self::IV_BYTES) {
            return false;
        }

        $iv = substr($decoded, 0, self::IV_BYTES);
        $ciphertext = substr($decoded, self::IV_BYTES);

        return openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Generate a new encryption key
     * @return string Base64 encoded key
     */
    public static function generate_key(): string
    {
        return function_exists('make_rand_str') ? make_rand_str(self::KEY_BYTES) : bin2hex(random_bytes(16));
    }
}
