<?php

class Services_model extends Model {

    private array $type_defaults = [
        'mariadb'    => ['port' => 3306,  'label' => 'MariaDB'],
        'apache2'    => ['port' => 80,    'label' => 'Apache'],
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

    function all(int $customer_id): array {
        return $this->db->query_bind(
            "SELECT sv.*, e.name as env_name
             FROM service sv
             JOIN environment e ON sv.environment_id = e.id
             WHERE sv.customer_id = :cid
             ORDER BY sv.created_at DESC",
            ['cid' => $customer_id],
            'object'
        );
    }

    function by_environment(int $environment_id, int $customer_id): array {
        return $this->db->query_bind(
            "SELECT * FROM service WHERE environment_id = :eid AND customer_id = :cid ORDER BY created_at DESC",
            ['eid' => $environment_id, 'cid' => $customer_id],
            'object'
        );
    }

    function get(int $id, int $customer_id): object|false {
        $rows = $this->db->query_bind(
            "SELECT sv.*, e.name as env_name
             FROM service sv
             JOIN environment e ON sv.environment_id = e.id
             WHERE sv.id = :id AND sv.customer_id = :cid LIMIT 1",
            ['id' => $id, 'cid' => $customer_id],
            'object'
        );
        return $rows[0] ?? false;
    }

    function create(array $data): int|false {
        return $this->db->insert($data, 'service');
    }

    function update_status(int $id, string $status): void {
        $this->db->update($id, ['status' => $status], 'service');
    }

    function delete_service(int $id, int $customer_id): void {
        $this->db->query_bind(
            "DELETE FROM health_check WHERE target_type = 'service' AND target_id = :id",
            ['id' => $id]
        );
        $this->db->query_bind(
            "DELETE FROM service WHERE id = :id AND customer_id = :cid",
            ['id' => $id, 'cid' => $customer_id]
        );
    }

    function environments_for_customer(int $customer_id): array {
        return $this->db->query_bind(
            "SELECT id, name, php_version FROM environment WHERE customer_id = :cid ORDER BY name ASC",
            ['cid' => $customer_id],
            'object'
        );
    }
}
