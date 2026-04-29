<?php

class Server_model extends Model {

    function all(int $customer_id): array {
        return $this->db->query_bind(
            "SELECT s.*, e.name as env_name, e.php_version,
                    (SELECT COUNT(*) FROM deployment WHERE server_id = s.id) as deploy_count
             FROM server s
             JOIN environment e ON s.environment_id = e.id
             WHERE s.customer_id = :cid
             ORDER BY s.created_at DESC",
            ['cid' => $customer_id],
            'object'
        );
    }

    function all_with_health(int $customer_id): array {
        return $this->db->query_bind(
            "SELECT s.id, s.name, s.ip_address, s.status, s.provider, s.region, s.server_type,
                    s.environment_id, s.created_at,
                    e.name as env_name, e.php_version,
                    COUNT(DISTINCT d.id) as deploy_count,
                    SUM(CASE WHEN lhc.status = 'healthy'   THEN 1 ELSE 0 END) as healthy_count,
                    SUM(CASE WHEN lhc.status = 'unhealthy' THEN 1 ELSE 0 END) as unhealthy_count,
                    MAX(lhc.checked_at) as last_checked_at
             FROM server s
             JOIN environment e ON s.environment_id = e.id
             LEFT JOIN deployment d ON d.server_id = s.id AND d.customer_id = :cid
             LEFT JOIN health_check lhc ON lhc.target_type = 'deployment'
                 AND lhc.target_id = d.id
                 AND lhc.id = (SELECT MAX(id) FROM health_check
                               WHERE target_type = 'deployment' AND target_id = d.id)
             WHERE s.customer_id = :cid2
             GROUP BY s.id, s.name, s.ip_address, s.status, s.provider, s.region, s.server_type,
                      s.environment_id, s.created_at, e.name, e.php_version
             ORDER BY s.created_at DESC",
            ['cid' => $customer_id, 'cid2' => $customer_id],
            'object'
        );
    }

    function by_environment(int $env_id, int $customer_id): array {
        return $this->db->query_bind(
            "SELECT * FROM server WHERE environment_id = :eid AND customer_id = :cid ORDER BY created_at DESC",
            ['eid' => $env_id, 'cid' => $customer_id],
            'object'
        );
    }

    function get(int $id, int $customer_id): object|false {
        $rows = $this->db->query_bind(
            "SELECT s.*, e.name as env_name, e.php_version,
                    e.domain, e.web_root, e.db_name, e.variables AS env_variables_enc,
                    c.email AS customer_email
             FROM server s
             JOIN environment e ON s.environment_id = e.id
             JOIN customer    c ON c.id = s.customer_id
             WHERE s.id = :id AND s.customer_id = :cid LIMIT 1",
            ['id' => $id, 'cid' => $customer_id],
            'object'
        );
        return $rows[0] ?? false;
    }

    function create(array $data): int|false {
        return $this->db->insert($data, 'server');
    }

    function update_status(int $id, string $status): void {
        $this->db->update($id, ['status' => $status], 'server');
    }

    // Transitions server to 'provisioning'; returns false if already provisioning.
    function mark_provisioning(int $id): bool {
        $rows = $this->db->query_bind(
            "SELECT status FROM server WHERE id = :id LIMIT 1",
            ['id' => $id],
            'object'
        );
        if (empty($rows) || $rows[0]->status === 'provisioning') {
            return false;
        }
        $this->db->query_bind(
            "UPDATE server SET status='provisioning' WHERE id=:id",
            ['id' => $id]
        );
        return true;
    }

    function mark_result(int $id, string $status, ?string $provision_user = null): void {
        if ($provision_user !== null) {
            $this->db->query_bind(
                "UPDATE server SET status=:status, ssh_user=:user WHERE id=:id",
                ['status' => $status, 'user' => $provision_user, 'id' => $id]
            );
        } else {
            $this->db->update($id, ['status' => $status], 'server');
        }
    }

    function delete(int $id, int $customer_id): void {
        $this->db->query_bind(
            "DELETE FROM health_check WHERE target_type = 'deployment' AND target_id IN (
                SELECT id FROM deployment WHERE server_id = :sid)",
            ['sid' => $id]
        );
        $this->db->query_bind(
            "DELETE FROM deployment WHERE server_id = :sid",
            ['sid' => $id]
        );
        $this->db->query_bind(
            "DELETE FROM server WHERE id = :id AND customer_id = :cid",
            ['id' => $id, 'cid' => $customer_id]
        );
    }

    function tracked_hetzner_ids(int $customer_id): array {
        $rows = $this->db->query_bind(
            "SELECT provider_id FROM server WHERE customer_id = :cid AND provider = 'hetzner' AND provider_id IS NOT NULL",
            ['cid' => $customer_id],
            'object'
        );
        return array_column($rows, 'provider_id');
    }

    function environments_for_customer(int $customer_id): array {
        return $this->db->query_bind(
            "SELECT id, name FROM environment WHERE customer_id = :cid ORDER BY name ASC",
            ['cid' => $customer_id],
            'object'
        );
    }
}
