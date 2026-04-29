<?php

class Script_model extends Model {

    // Variables available for interpolation in custom scripts
    public const DEPLOY_VARS = [
        '{{PHP_VERSION}}' => 'PHP version from the server (e.g. 8.2)',
        '{{REPO_URL}}'    => 'Git repository URL',
        '{{BRANCH}}'      => 'Git branch',
        '{{WEB_ROOT}}'    => 'Web root path on the server',
        '{{DOMAIN}}'      => 'Domain name (may be empty)',
        '{{DB_NAME}}'     => 'MySQL database name (may be empty)',
        '{{SERVER_IP}}'   => 'Server IP address',
        '{{SERVER_NAME}}' => 'Server display name',
        '{{ENV_NAME}}'    => 'Environment name',
        '{{ENV_VARS}}'    => 'Exported environment variables block',
    ];

    public const LAMP_VARS = [
        '{{PHP_VERSION}}' => 'PHP version from the server (e.g. 8.2)',
        '{{SERVER_IP}}'   => 'Server IP address',
        '{{SERVER_NAME}}' => 'Server display name',
        '{{ENV_NAME}}'    => 'Environment name',
    ];

    function all(int $customer_id): array {
        return $this->db->query_bind(
            "SELECT * FROM script WHERE customer_id = :cid ORDER BY type, name",
            ['cid' => $customer_id],
            'object'
        );
    }

    function by_type(int $customer_id, string $type): array {
        return $this->db->query_bind(
            "SELECT id, name FROM script WHERE customer_id = :cid AND type = :type ORDER BY name",
            ['cid' => $customer_id, 'type' => $type],
            'object'
        );
    }

    function get(int $id, int $customer_id): object|false {
        $rows = $this->db->query_bind(
            "SELECT * FROM script WHERE id = :id AND customer_id = :cid LIMIT 1",
            ['id' => $id, 'cid' => $customer_id],
            'object'
        );
        return $rows[0] ?? false;
    }

    function scripts_for_server(int $server_id, int $customer_id): array {
        return $this->db->query_bind(
            "SELECT id, name, created_at FROM script WHERE server_id = :sid AND customer_id = :cid AND type = 'lamp' ORDER BY created_at DESC",
            ['sid' => $server_id, 'cid' => $customer_id],
            'object'
        );
    }

    function latest_for_server(int $server_id, int $customer_id): object|false {
        $rows = $this->db->query_bind(
            "SELECT * FROM script WHERE server_id = :sid AND customer_id = :cid AND type = 'lamp' ORDER BY created_at DESC LIMIT 1",
            ['sid' => $server_id, 'cid' => $customer_id],
            'object'
        );
        return $rows[0] ?? false;
    }

    function create(array $data): int|false {
        return $this->db->insert($data, 'script');
    }

    function update(int $id, array $data): void {
        $this->db->update($id, $data, 'script');
    }

    function delete(int $id, int $customer_id): void {
        // Detach from deployments first
        $this->db->query_bind(
            "UPDATE deployment SET script_id = NULL WHERE script_id = :sid AND customer_id = :cid",
            ['sid' => $id, 'cid' => $customer_id]
        );
        $this->db->query_bind(
            "DELETE FROM script WHERE id = :id AND customer_id = :cid",
            ['id' => $id, 'cid' => $customer_id]
        );
    }

    function interpolate(string $body, array $vars): string {
        return strtr($body, $vars);
    }
}
