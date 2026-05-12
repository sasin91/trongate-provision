<?php

class Event extends Trongate {

    function timeline_for_deployment(): void {
        $id = (int) segment(3);
        $this->_require_auth();

        $this->module('deployment');
        $deployment = $this->deployment->model->get($id);
        if ($deployment === false) { redirect('deployment'); }

        $events = $this->model->for_entity('deployment', $id, 1);

        $data = [
            'view_module'   => 'deployment/event',
            'view_file'     => 'deployment',
            'page_title'    => 'Timeline — Deployment #' . $id,
            'current_email' => defined('OUR_EMAIL_ADDRESS') ? OUR_EMAIL_ADDRESS : '',
            'deployment'    => $deployment,
            'events'        => $events,
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function feed(): void {
        $this->_require_auth();
        $filter   = in_array($_GET['filter'] ?? '', ['deployment', 'server', 'service', 'environment', 'customer'])
            ? $_GET['filter']
            : '';

        $events = $this->model->for_customer(1, 100, $filter);

        $data = [
            'view_module'   => 'deployment/event',
            'view_file'     => 'feed',
            'page_title'    => 'Activity Feed',
            'current_email' => defined('OUR_EMAIL_ADDRESS') ? OUR_EMAIL_ADDRESS : '',
            'events'        => $events,
            'active_filter' => $filter,
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    static function label_for(string $event_type): string {
        $labels = [
            'DeploymentCreated'          => 'Deployment Created',
            'DeploymentSucceeded'        => 'Deployment Succeeded',
            'DeploymentFailed'           => 'Deployment Failed',
            'DeploymentDeleted'          => 'Deployment Deleted',
            'ReleasePromoted'            => 'Release Promoted',
            'ReleaseDemoted'             => 'Release Demoted',
            'ServerCreated'              => 'Server Added',
            'ServerStatusChanged'        => 'Server Status Changed',
            'ServerDeleted'              => 'Server Deleted',
            'ServiceAdded'               => 'Service Added',
            'ServiceStatusChanged'       => 'Service Status Changed',
            'ServiceDeleted'             => 'Service Deleted',
            'EnvironmentCreated'         => 'Environment Created',
            'EnvironmentVariablesUpdated'=> 'Variables Updated',
            'EnvironmentDeleted'         => 'Environment Deleted',
            'HealthCheckCompleted'       => 'Health Check',
            'CustomerLoggedIn'           => 'Logged In',
            'CustomerLoginFailed'        => 'Login Failed',
        ];
        return $labels[$event_type] ?? str_replace(['_', 'Completed'], [' ', ''], preg_replace('/([A-Z])/', ' $1', $event_type));
    }

    static function dot_color(string $entity_type, string $event_type): string {
        if (str_contains($event_type, 'Deleted') || str_contains($event_type, 'Failed')) {
            return '#ef4444';
        }
        return match ($entity_type) {
            'deployment' => '#6366f1',
            'server'     => '#3b82f6',
            'service'    => '#8b5cf6',
            'environment'=> '#f59e0b',
            'customer'   => '#10b981',
            default      => '#94a3b8',
        };
    }

    static function relative_time(string $datetime): string {
        $diff = time() - strtotime($datetime);
        if ($diff < 60)   return 'just now';
        if ($diff < 3600) return (int)($diff / 60) . 'm ago';
        if ($diff < 86400) return (int)($diff / 3600) . 'h ago';
        if ($diff < 604800) return (int)($diff / 86400) . 'd ago';
        return date('M j, Y', strtotime($datetime));
    }

    static function payload_summary(string $event_type, array $payload): string {
        return match ($event_type) {
            'DeploymentCreated'          => htmlspecialchars($payload['repo_url'] ?? '') . ' @ ' . htmlspecialchars($payload['branch'] ?? ''),
            'DeploymentSucceeded'        => ($payload['deployed_sha'] ?? $payload['sha'] ?? null) ? 'SHA: ' . htmlspecialchars(substr($payload['deployed_sha'] ?? $payload['sha'], 0, 8)) : '',
            'ServerStatusChanged'        => htmlspecialchars($payload['from'] ?? '') . ' → ' . htmlspecialchars($payload['to'] ?? ''),
            'HealthCheckCompleted'       => htmlspecialchars($payload['status'] ?? '') . ($payload['response_time_ms'] ?? null ? ', ' . $payload['response_time_ms'] . 'ms' : '') . ($payload['http_code'] ?? null ? ' (' . $payload['http_code'] . ')' : ''),
            'ServiceAdded'               => htmlspecialchars($payload['name'] ?? '') . ' (' . htmlspecialchars($payload['type'] ?? '') . ')',
            'EnvironmentVariablesUpdated'=> ($payload['var_count'] ?? 0) . ' variable(s)',
            'ReleasePromoted'            => htmlspecialchars($payload['release_path'] ?? ''),
            'ReleaseDemoted'             => htmlspecialchars($payload['previous_release_path'] ?? ''),
            'CustomerLoginFailed'        => htmlspecialchars($payload['email'] ?? ''),
            default                      => implode(', ', array_filter(array_map(
                fn($k, $v) => is_scalar($v) && $v !== '' ? htmlspecialchars($k . ': ' . $v) : null,
                array_keys($payload), $payload
            ))),
        };
    }

    private function _require_auth(): void {
        $this->module('trongate_tokens');
        if (!$this->trongate_tokens->_attempt_get_valid_token(1)) {
            redirect('login');
        }
    }
}
