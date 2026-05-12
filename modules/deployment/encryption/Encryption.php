<?php

require_once __DIR__ . '/Encryptor.php';

class Encryption extends Trongate {

    function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        block_url('encryption');
    }

    function validate_key(string $key): string {
        return Encryptor::validate_key($key);
    }

    function encrypt(string $plaintext, string $key): string {
        return Encryptor::encrypt($plaintext, $key);
    }

    function decrypt(string $encrypted, string $key): string|false {
        return Encryptor::decrypt($encrypted, $key);
    }

    static function generate_key(): string {
        return Encryptor::generate_key();
    }
}
