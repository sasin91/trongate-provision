<?php

class Services_model extends Model {

    private array $type_defaults = [
        'mariadb'    => ['port' => 3306,  'label' => 'MariaDB'],
        'apache2'    => ['port' => 80,    'label' => 'Apache2'],
        'redis'      => ['port' => 6379,  'label' => 'Redis'],
        'postgresql' => ['port' => 5432,  'label' => 'PostgreSQL'],
        'memcached'  => ['port' => 11211, 'label' => 'Memcached'],
        'custom'     => ['port' => 0,     'label' => 'Custom'],
    ];

    // ── Service CRUD ──────────────────────────────────────────────

    function get_type_defaults(): array {
        return $this->type_defaults;
    }

    function get_type_label(string $type): string {
        return $this->type_defaults[$type]['label'] ?? ucfirst($type);
    }

    function all(): array {
        return $this->db->query_bind(
            "SELECT sv.*, e.name as env_name
             FROM service sv
             JOIN environment e ON sv.environment_id = e.id
             ORDER BY sv.created_at DESC",
            [],
            'object'
        );
    }

    function by_environment(int $environment_id, int $customer_id = 0): array {
        return $this->db->query_bind(
            "SELECT * FROM service WHERE environment_id = :eid ORDER BY created_at DESC",
            ['eid' => $environment_id],
            'object'
        );
    }

    function get(int $id, int $customer_id = 0): object|false {
        $rows = $this->db->query_bind(
            "SELECT sv.*, e.name as env_name
             FROM service sv
             JOIN environment e ON sv.environment_id = e.id
             WHERE sv.id = :id LIMIT 1",
            ['id' => $id],
            'object'
        );
        return $rows[0] ?? false;
    }

    function create(array $data): int|false {
        return $this->db->insert($data, 'service');
    }

    function create_defaults_for_environment(
        int $environment_id,
        array $types,
        ?string $domain = null
    ): void {
        $types = array_values(array_unique(array_map('strval', $types)));

        foreach ($types as $type) {
            if (!isset($this->type_defaults[$type]) || $type === 'custom') {
                continue;
            }

            $default = $this->type_defaults[$type];
            $this->create([
                'environment_id' => $environment_id,
                'name'           => $default['label'],
                'type'           => $type,
                'host'           => $type === 'apache2' ? ($domain ?: null) : null,
                'port'           => (int) $default['port'],
                'status'         => 'pending',
            ]);
        }
    }

    function update_status(int $id, string $status): void {
        $this->db->update($id, ['status' => $status], 'service');
    }

    function delete_service(int $id, int $customer_id = 0): void {
        $this->db->query_bind(
            "DELETE FROM health_check WHERE target_type = 'service' AND target_id = :id",
            ['id' => $id]
        );
        $this->db->query_bind(
            "DELETE FROM service WHERE id = :id",
            ['id' => $id]
        );
    }

    function environments_for_select(): array {
        return $this->db->query_bind(
            "SELECT id, name, php_version FROM environment ORDER BY name ASC",
            [],
            'object'
        );
    }
}
