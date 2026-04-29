<?php

class Secrets_model extends Model {

    function get_secrets(string $module, int $module_id, ?string $service = null): ?object {
        if ($service !== null) {
            $rows = $this->db->query_bind(
                "SELECT * FROM secrets WHERE module = :m AND module_id = :mid AND service = :svc LIMIT 1",
                ['m' => $module, 'mid' => $module_id, 'svc' => $service],
                'object'
            );
        } else {
            $rows = $this->db->query_bind(
                "SELECT * FROM secrets WHERE module = :m AND module_id = :mid AND service IS NULL LIMIT 1",
                ['m' => $module, 'mid' => $module_id],
                'object'
            );
        }
        return !empty($rows) ? $rows[0] : null;
    }

    function save_secrets(string $module, int $module_id, ?string $service, string $encryption_key, string $encrypted_variables): void {
        $this->db->query_bind(
            "INSERT INTO secrets (encryption_key, module, module_id, service, variables)
             VALUES (:ek, :m, :mid, :svc, :vars)
             ON DUPLICATE KEY UPDATE variables = :vars, encryption_key = :ek",
            ['ek' => $encryption_key, 'm' => $module, 'mid' => $module_id, 'svc' => $service, 'vars' => $encrypted_variables]
        );
    }

    function delete_secrets(string $module, int $module_id, ?string $service = null): void {
        if ($service !== null) {
            $this->db->query_bind(
                "DELETE FROM secrets WHERE module = :m AND module_id = :mid AND service = :svc",
                ['m' => $module, 'mid' => $module_id, 'svc' => $service]
            );
        } else {
            $this->db->query_bind(
                "DELETE FROM secrets WHERE module = :m AND module_id = :mid",
                ['m' => $module, 'mid' => $module_id]
            );
        }
    }
}
