<?php

class Secrets extends Trongate {

    function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        block_url('secrets');
    }

    function get(string $module, int $module_id, ?string $service = null): array {
        $row = $this->model->get_secrets($module, $module_id, $service);
        if ($row === null) return [];

        $data_key = $this->_unwrap_key($row->encryption_key);
        if ($data_key === false) {
            // Stale plaintext-keyed row — purge and return empty
            $this->model->delete_secrets($module, $module_id, $service);
            return [];
        }

        $this->module('encryption');
        $decrypted = $this->encryption->decrypt($row->variables, $data_key);
        if ($decrypted === false) return [];

        return json_decode($decrypted, true) ?: [];
    }

    function save(string $module, int $module_id, ?string $service, array $variables): void {
        $this->module('encryption');
        $data_key     = Encryption::generate_key();
        $encrypted    = $this->encryption->encrypt(json_encode($variables), $data_key);
        $wrapped_key  = $this->_wrap_key($data_key);
        $this->model->save_secrets($module, $module_id, $service, $wrapped_key, $encrypted);
    }

    function merge(string $module, int $module_id, ?string $service, array $variables): void {
        $existing = $this->get($module, $module_id, $service);
        $this->save($module, $module_id, $service, array_merge($existing, $variables));
    }

    function delete(string $module, int $module_id, ?string $service = null): void {
        $this->model->delete_secrets($module, $module_id, $service);
    }

    function exists(string $module, int $module_id, ?string $service = null): bool {
        return $this->model->get_secrets($module, $module_id, $service) !== null;
    }

    private function _wrap_key(string $data_key): string {
        $master = hash('sha256', ENCRYPTION_KEY, true);
        $iv     = random_bytes(16);
        $enc    = openssl_encrypt($data_key, 'AES-256-CBC', $master, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    private function _unwrap_key(string $wrapped): string|false {
        $raw = base64_decode($wrapped, true);
        if ($raw === false || strlen($raw) <= 16) return false;
        $master = hash('sha256', ENCRYPTION_KEY, true);
        $iv     = substr($raw, 0, 16);
        $enc    = substr($raw, 16);
        $key    = openssl_decrypt($enc, 'AES-256-CBC', $master, OPENSSL_RAW_DATA, $iv);
        return $key !== false ? $key : false;
    }
}
