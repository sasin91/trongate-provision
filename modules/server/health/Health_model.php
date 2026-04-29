<?php

class Health_model extends Model {

    function latest_all(int $customer_id): object {
        $deployments = $this->db->query_bind(
            "SELECT d.id, d.status as deploy_status, d.repo_url,
                    e.domain, e.name as env_name,
                    s.name as server_name, s.ip_address,
                    hc.status as health_status, hc.response_time_ms, hc.http_status, hc.checked_at, hc.message
             FROM deployment d
             JOIN server s ON d.server_id = s.id
             JOIN environment e ON d.environment_id = e.id
             LEFT JOIN health_check hc ON hc.target_type = 'deployment' AND hc.target_id = d.id
                 AND hc.id = (SELECT MAX(id) FROM health_check WHERE target_type='deployment' AND target_id = d.id)
             WHERE d.customer_id = :cid
             ORDER BY e.name, s.name, d.id",
            ['cid' => $customer_id],
            'object'
        );

        $services = $this->db->query_bind(
            "SELECT sv.id, sv.name, sv.type, sv.host, sv.port, sv.status as svc_status,
                    e.name as env_name,
                    hc.status as health_status, hc.response_time_ms, hc.checked_at, hc.message
             FROM service sv
             JOIN environment e ON sv.environment_id = e.id
             LEFT JOIN health_check hc ON hc.target_type = 'service' AND hc.target_id = sv.id
                 AND hc.id = (SELECT MAX(id) FROM health_check WHERE target_type='service' AND target_id = sv.id)
             WHERE sv.customer_id = :cid
             ORDER BY e.name, sv.name",
            ['cid' => $customer_id],
            'object'
        );

        return (object) ['deployments' => $deployments, 'services' => $services];
    }

    function history(string $type, int $target_id, int $customer_id, int $limit = 20): array {
        return $this->db->query_bind(
            "SELECT * FROM health_check
             WHERE target_type = :type AND target_id = :tid AND customer_id = :cid
             ORDER BY checked_at DESC LIMIT :lim",
            ['type' => $type, 'tid' => $target_id, 'cid' => $customer_id, 'lim' => $limit],
            'object'
        );
    }

    function latest(string $type, int $target_id): object|false {
        $rows = $this->db->query_bind(
            "SELECT * FROM health_check WHERE target_type = :type AND target_id = :tid ORDER BY id DESC LIMIT 1",
            ['type' => $type, 'tid' => $target_id],
            'object'
        );
        return $rows[0] ?? false;
    }

    function record_health(array $data): void {
        $this->db->insert($data, 'health_check');
    }

    function stats(int $customer_id): object {
        $rows = $this->db->query_bind(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status='healthy' THEN 1 ELSE 0 END) as healthy,
                SUM(CASE WHEN status='unhealthy' THEN 1 ELSE 0 END) as unhealthy
             FROM health_check
             WHERE customer_id = :cid
               AND checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            ['cid' => $customer_id],
            'object'
        );
        return $rows[0] ?? (object)['total' => 0, 'healthy' => 0, 'unhealthy' => 0];
    }
}
