<?php

class Deployment_onboarding_model extends Model
{
    function first_environment(): object|false
    {
        $rows = $this->db->query_bind(
            "SELECT * FROM environment ORDER BY id ASC LIMIT 1",
            [],
            'object'
        );
        return $rows[0] ?? false;
    }

    function first_server(): object|false
    {
        $rows = $this->db->query_bind(
            "SELECT * FROM server ORDER BY id ASC LIMIT 1",
            [],
            'object'
        );
        return $rows[0] ?? false;
    }

    function first_deployment(): object|false
    {
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
}
