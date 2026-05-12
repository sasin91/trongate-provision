<?php

class Deployment_model extends Model {

    function all(): array {
        return $this->db->query_bind(
            "SELECT d.*, s.name as server_name, s.ip_address,
                    e.name as env_name, e.domain
             FROM deployment d
             JOIN server s ON d.server_id = s.id
             JOIN environment e ON d.environment_id = e.id
             ORDER BY d.created_at DESC",
            [],
            'object'
        );
    }

    function by_server(int $server_id): array {
        return $this->db->query_bind(
            "SELECT d.*, e.name as env_name, e.domain,
                    hc.status as health_status, hc.response_time_ms, hc.http_status,
                    hc.checked_at as health_checked_at, hc.message as health_message
             FROM deployment d
             JOIN environment e ON d.environment_id = e.id
             LEFT JOIN health_check hc ON hc.target_type = 'deployment' AND hc.target_id = d.id
                 AND hc.id = (SELECT MAX(id) FROM health_check
                              WHERE target_type = 'deployment' AND target_id = d.id)
             WHERE d.server_id = :sid
             ORDER BY d.created_at DESC",
            ['sid' => $server_id],
            'object'
        );
    }

    function get(int $id): object|false {
        $rows = $this->db->query_bind(
            "SELECT d.*, s.name as server_name, s.ip_address, s.ssh_user, s.ssh_port,
                    e.name as env_name, e.id as env_id, e.php_version,
                    e.web_root, e.domain, e.db_name,
                    e.variables as env_variables_enc
             FROM deployment d
             JOIN server s ON d.server_id = s.id
             JOIN environment e ON d.environment_id = e.id
             WHERE d.id = :id LIMIT 1",
            ['id' => $id],
            'object'
        );
        return $rows[0] ?? false;
    }

    function mark_running(int $id): void {
        $this->db->query_bind(
            "UPDATE deployment SET status='running', started_at=NOW() WHERE id=:id",
            ['id' => $id]
        );
    }

    function finish(int $id, string $status, string $log, ?string $sha, ?string $release_path = null): void {
        $params = ['status' => $status, 'log' => $log, 'id' => $id];
        $sets = "status=:status, run_log=:log, finished_at=NOW()";
        if ($sha !== null) {
            $sets .= ", deployed_sha=:sha";
            $params['sha'] = $sha;
        }
        if ($release_path !== null) {
            $sets .= ", release_path=:release_path";
            $params['release_path'] = $release_path;
        }
        $this->db->query_bind(
            "UPDATE deployment SET {$sets} WHERE id=:id",
            $params
        );
    }

    function mark_stale_running_failed(int $id, string $message): void {
        $this->db->query_bind(
            "UPDATE deployment
             SET status='failed', run_log=:log, finished_at=NOW()
             WHERE id=:id AND status='running'",
            ['id' => $id, 'log' => $message]
        );
    }

    function promote_release(int $id, ?string $previous_release_path): void {
        $this->db->query_bind(
            "UPDATE deployment
             SET status = 'success',
                 previous_release_path = :previous_release_path,
                 promoted_at = NOW()
             WHERE id = :id",
            ['id' => $id, 'previous_release_path' => $previous_release_path]
        );
    }

    function demote_release(int $id): void {
        $this->db->query_bind(
            "UPDATE deployment
             SET status = 'staged',
                 demoted_at = NOW()
             WHERE id = :id",
            ['id' => $id]
        );
    }

    function set_deployed_sha(int $id, string $sha): void {
        $this->db->update($id, ['deployed_sha' => $sha], 'deployment');
    }

    function set_release_path(int $id, string $release_path): void {
        $this->db->update($id, ['release_path' => $release_path], 'deployment');
    }

    function set_zip_path(int $id, string $zip_path): void {
        $this->db->query_bind(
            "UPDATE deployment SET zip_path = :zip_path WHERE id = :id",
            ['id' => $id, 'zip_path' => $zip_path]
        );
    }

    function create(array $data): int|false {
        return $this->db->insert($data, 'deployment');
    }

    function update_status(int $id, string $status, ?string $notes = null): void {
        $up = ['status' => $status];
        if ($notes !== null) $up['notes'] = $notes;
        $this->db->update($id, $up, 'deployment');
    }

    function delete(int $id): void {
        $this->db->query_bind(
            "DELETE FROM health_check WHERE target_type = 'deployment' AND target_id = :id",
            ['id' => $id]
        );
        $this->db->query_bind(
            "DELETE FROM deployment WHERE id = :id",
            ['id' => $id]
        );
    }

    function servers_for_select(): array {
        return $this->db->query_bind(
            "SELECT id, name, ip_address, status FROM server ORDER BY name",
            [],
            'object'
        );
    }

    function environments_for_select(): array {
        return $this->db->query_bind(
            "SELECT e.id, e.name, e.php_version, e.domain,
                    ls.id   AS locked_server_id,
                    ls.name AS locked_server_name
             FROM environment e
             LEFT JOIN (
                 SELECT environment_id, MIN(server_id) AS server_id
                 FROM deployment
                 GROUP BY environment_id
             ) fd ON fd.environment_id = e.id
             LEFT JOIN server ls ON ls.id = fd.server_id
             ORDER BY e.name",
            [],
            'object'
        );
    }
}
