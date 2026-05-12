<?php

require_once __DIR__ . '/../traits/Current_customer.php';

class Onboarding_model extends Model {
    use Current_customer;

    function is_onboarded(int $customer_id): bool {
        $rows = $this->db->query_bind(
            "SELECT 1 FROM customer WHERE id = :id AND active = 1 AND onboarded_at IS NOT NULL LIMIT 1",
            ['id' => $customer_id],
            'column'
        );
        return (bool) $rows;
    }

    function mark_onboarded(int $customer_id): void {
        $this->db->update($customer_id, ['onboarded_at' => date('Y-m-d H:i:s')], 'customer');
    }

    function save_ssh_key(int $customer_id, string $public_key): void {
        $this->db->update($customer_id, ['ssh_public_key' => trim($public_key)], 'customer');
    }

    function save_onboarding_provider(int $customer_id, string $provider): void {
        $this->db->update($customer_id, ['onboarding_provider' => $provider], 'customer');
    }

    function save_onboarding_server(int $customer_id, int $server_id): void {
        $this->db->update($customer_id, ['onboarding_server_id' => $server_id], 'customer');
    }

    function mark_dns_ssl_seen(int $customer_id): void {
        $this->db->update($customer_id, ['onboarding_dns_ssl_seen' => 1], 'customer');
    }

    function has_ssh_key(int $customer_id): bool {
        $rows = $this->db->query_bind(
            "SELECT 1 FROM customer WHERE id = :id AND ssh_public_key IS NOT NULL AND ssh_public_key != '' LIMIT 1",
            ['id' => $customer_id],
            'column'
        );
        return (bool) $rows;
    }

    function first_environment(int $customer_id): object|false {
        $rows = $this->db->query_bind(
            "SELECT * FROM environment ORDER BY id ASC LIMIT 1",
            [],
            'object'
        );
        return $rows[0] ?? false;
    }

    function first_server(int $customer_id): object|false {
        $rows = $this->db->query_bind(
            "SELECT s.*
             FROM server s
             JOIN customer c ON c.id = :id
             ORDER BY CASE WHEN s.id = c.onboarding_server_id THEN 0 ELSE 1 END, s.id ASC
             LIMIT 1",
            ['id' => $customer_id],
            'object'
        );
        return $rows[0] ?? false;
    }

    function create_server(array $data): int|false {
        return $this->db->insert($data, 'server');
    }

    function first_deployment(int $customer_id): object|false {
        $rows = $this->db->query_bind(
            "SELECT * FROM deployment
             ORDER BY
                CASE status
                    WHEN 'script_ready' THEN 0
                    WHEN 'running' THEN 1
                    WHEN 'success' THEN 2
                    ELSE 3
                END,
                id DESC
             LIMIT 1",
            [],
            'object'
        );
        return $rows[0] ?? false;
    }

    function create_deployment(array $data): int|false {
        return $this->db->insert($data, 'deployment');
    }

    function backfill_service_hosts(int $env_id, string $ip): void {
        $this->db->query_bind(
            "UPDATE service SET host = :ip WHERE environment_id = :env_id AND (host IS NULL OR host = '')",
            ['ip' => $ip, 'env_id' => $env_id]
        );
    }

    // Hetzner provider_ids that already have at least one deployment
    function hetzner_ids_with_deployments(int $customer_id): array {
        $rows = $this->db->query_bind(
            "SELECT DISTINCT s.provider_id
             FROM server s
             JOIN deployment d ON d.server_id = s.id
             WHERE s.provider = 'hetzner' AND s.provider_id IS NOT NULL",
            [],
            'object'
        );
        return array_column($rows, 'provider_id');
    }
}
