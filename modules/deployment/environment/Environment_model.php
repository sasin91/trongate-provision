<?php

class Environment_model extends Model {

    function all(): array {
        return $this->db->query_bind(
            "SELECT e.*, (SELECT COUNT(*) FROM server WHERE environment_id = e.id) as server_count
             FROM environment e
             ORDER BY e.created_at DESC",
            [],
            'object'
        );
    }

    function get(int $id): object|false {
        $rows = $this->db->query_bind(
            "SELECT * FROM environment WHERE id = :id LIMIT 1",
            ['id' => $id],
            'object'
        );
        return $rows[0] ?? false;
    }

    function create(array $data): int|false {
        return $this->db->insert($data, 'environment');
    }

    function create_with_defaults(
        string $name,
        string $php_version,
        ?string $domain = null,
        array $variables = []
    ): int|false {
        $db_name = $this->slug_db_name($name);
        $env_id = $this->create([
            'name'        => $name,
            'php_version' => $php_version,
            'web_root'    => '/var/www/html',
            'domain'      => $domain ?: null,
            'db_name'     => $db_name,
        ]);

        if ($env_id === false) {
            return false;
        }

        $db_password = bin2hex(random_bytes(16));
        $this->save_variables((int) $env_id, array_merge([
            'DB_NAME'     => $db_name,
            'DB_USER'     => $db_name,
            'DB_PASSWORD' => $db_password,
        ], $variables));

        return (int) $env_id;
    }

    function slug_db_name(string $name): string {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        return trim($slug, '_');
    }

    function delete(int $id): bool {
        $this->db->query_bind(
            "DELETE FROM health_check WHERE target_type = 'service' AND target_id IN (
                SELECT sv.id FROM service sv WHERE sv.environment_id = :eid)",
            ['eid' => $id]
        );
        $this->db->query_bind(
            "DELETE FROM health_check WHERE target_type = 'deployment' AND target_id IN (
                SELECT d.id FROM deployment d
                JOIN server s ON d.server_id = s.id
                WHERE s.environment_id = :eid)",
            ['eid' => $id]
        );
        $this->db->query_bind(
            "DELETE FROM service WHERE environment_id = :eid",
            ['eid' => $id]
        );
        $this->db->query_bind(
            "DELETE FROM deployment WHERE server_id IN (SELECT id FROM server WHERE environment_id = :eid)",
            ['eid' => $id]
        );
        $this->db->query_bind(
            "DELETE FROM server WHERE environment_id = :eid",
            ['eid' => $id]
        );
        $this->db->query_bind(
            "DELETE FROM environment WHERE id = :id",
            ['id' => $id]
        );
        return true;
    }

    // ── Variables (encrypted JSON) ─────────────────────────────────

    function get_variables(int $env_id): array {
        $env = $this->get($env_id);
        if ($env === false || empty($env->variables)) return [];
        $json = $this->_decrypt($env->variables);
        return json_decode($json, true) ?: [];
    }

    function save_variables(int $env_id, array $vars): void {
        $json = json_encode($vars, JSON_UNESCAPED_UNICODE);
        $encrypted = $this->_encrypt($json);
        $this->db->query_bind(
            "UPDATE environment SET variables = :v WHERE id = :id",
            ['v' => $encrypted, 'id' => $env_id]
        );
    }

    public function decrypt_blob(string $ciphertext): array {
        if (empty($ciphertext)) return [];
        $json = $this->_decrypt($ciphertext);
        return json_decode($json, true) ?: [];
    }

    private function _encrypt(string $plaintext): string {
        $key = hash('sha256', ENCRYPTION_KEY, true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    private function _decrypt(string $ciphertext): string {
        $key  = hash('sha256', ENCRYPTION_KEY, true);
        $raw  = base64_decode($ciphertext);
        $iv   = substr($raw, 0, 16);
        $enc  = substr($raw, 16);
        $dec  = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $dec === false ? '{}' : $dec;
    }
}
