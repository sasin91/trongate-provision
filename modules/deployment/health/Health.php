<?php

require_once __DIR__ . '/../event/Emits_events.php';

class Health extends Trongate
{

  use Emits_events;

  private int $probe_timeout = 5;

  function health(): void
  {
    $this->_require_auth();
    $overview = $this->model->latest_all(1);
    $stats    = $this->model->stats(1);

    $data = [
      'view_module'   => 'deployment/health',
      'view_file'     => 'health',
      'page_title'    => 'Health',
      'current_email' => defined('OUR_EMAIL_ADDRESS') ? OUR_EMAIL_ADDRESS : '',
      'overview'      => $overview,
      'stats'         => $stats,
    ];

    $this->module('templates');
    $this->templates->customer($data);
  }

  function check(): void
  {
    $type      = segment(3);
    $target_id = (int) segment(4);
    $this->_require_auth();
    $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
    if ($this->validation->run() !== true) {
      redirect('server-health/health');
      return;
    }

    if ($type === 'deployment') {
      $this->check_deployment_result($target_id, 1);
      redirect('deployment/show/' . $target_id);
    } elseif ($type === 'service') {
      $this->check_service_result($target_id, 1);
      redirect('environment-services/show/' . $target_id);
    } else {
      redirect('server-health/health');
    }
  }

  function check_server(): void
  {
    $server_id = (int) segment(3);
    $this->_require_auth();
    $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
    if ($this->validation->run() !== true) {
      redirect('server/show/' . $server_id);
      return;
    }

    $this->module('deployment');
    $deployments = $this->deployment->model->by_server($server_id);

    $saved_timeout = $this->probe_timeout;
    $this->probe_timeout = 2;
    foreach ($deployments as $d) {
      $this->check_deployment_result((int) $d->id, 1);
    }
    $this->probe_timeout = $saved_timeout;

    $checked = count($deployments);
    $_SESSION['flash_success'] = "Ran health checks on {$checked} deployment(s).";
    redirect('server/show/' . $server_id);
  }

  function check_all(): void
  {
    $this->_require_auth();
    $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
    if ($this->validation->run() !== true) {
      redirect('server-health/health');
      return;
    }

    $saved_timeout = $this->probe_timeout;
    $this->probe_timeout = 2;

    $this->module('deployment');
    $deployments = array_slice($this->deployment->model->all(), 0, 10);
    foreach ($deployments as $d) {
      $this->check_deployment_result((int) $d->id, 1);
    }

    $this->module('deployment-services');
    $services = array_slice($this->services->model->all(), 0, 10);
    foreach ($services as $s) {
      $this->check_service_result((int) $s->id, 1);
    }

    $this->probe_timeout = $saved_timeout;

    $checked = count($deployments) + count($services);
    $_SESSION['flash_success'] = "Ran health checks on {$checked} target(s).";
    redirect('server-health/health');
  }

  function check_deployment_result(int $id, int $customer_id): ?array
  {
    $this->module('deployment');
    $d = $this->deployment->model->get($id, $customer_id);
    if ($d === false) return null;

    $result = $this->_probe_http($d->domain ?? '', $d->ip_address ?? '');
    $this->model->record_health([
      'target_type'      => 'deployment',
      'target_id'        => $id,
      'customer_id'      => $customer_id,
      'status'           => $result['status'],
      'response_time_ms' => $result['ms'],
      'http_status'      => $result['http_code'],
      'message'          => $result['message'],
    ]);
    $this->_emit('HealthCheckCompleted', 'deployment', $id, [
      'status'           => $result['status'],
      'response_time_ms' => $result['ms'],
      'http_code'        => $result['http_code'],
      'message'          => $result['message'],
    ]);

    $_SESSION['flash_success'] = 'Health check complete: ' . $result['status'] . ' (' . $result['message'] . ')';
    return $result;
  }

  function check_service_result(int $id, int $customer_id): ?array
  {
    $this->module('deployment-services');
    $s = $this->services->model->get($id, $customer_id);
    if ($s === false) return null;

    $result = in_array($s->type, ['mysql', 'mariadb'], true)
      ? $this->_probe_mysql_via_ssh((int) $s->environment_id, $customer_id)
      : $this->_probe_tcp($s->host ?? '', (int) $s->port);
    $this->model->record_health([
      'target_type'      => 'service',
      'target_id'        => $id,
      'customer_id'      => $customer_id,
      'status'           => $result['status'],
      'response_time_ms' => $result['ms'],
      'http_status'      => null,
      'message'          => $result['message'],
    ]);
    $this->_emit('HealthCheckCompleted', 'service', $id, [
      'status'           => $result['status'],
      'response_time_ms' => $result['ms'],
      'message'          => $result['message'],
    ]);

    $_SESSION['flash_success'] = 'Health check complete: ' . $result['status'] . ' (' . $result['message'] . ')';
    return $result;
  }

  private function _probe_mysql_via_ssh(int $env_id, int $customer_id): array
  {
    if (!defined('RUNNER_SSH_KEY') || !RUNNER_SSH_KEY) {
      return ['status' => 'unknown', 'message' => 'SSH key not configured', 'ms' => null];
    }

    $this->module('deployment-server');
    $servers = $this->server->model->by_environment($env_id, $customer_id);
    if (empty($servers)) {
      return ['status' => 'unknown', 'message' => 'No server for environment', 'ms' => null];
    }
    $srv = $servers[0];

    if (!filter_var($srv->ip_address, FILTER_VALIDATE_IP)) {
      return ['status' => 'unknown', 'message' => 'Invalid server IP', 'ms' => null];
    }

    $user = $srv->ssh_user ?: 'root';
    if (!preg_match('/^[a-z_][a-z0-9_.-]{0,31}$/i', $user)) {
      return ['status' => 'unknown', 'message' => 'Invalid SSH user', 'ms' => null];
    }
    $port    = max(1, min(65535, (int) ($srv->ssh_port ?: 22)));
    $timeout = $this->probe_timeout;

    $cmd = 'ssh'
      . ' -i '  . escapeshellarg(RUNNER_SSH_KEY)
      . ' -o StrictHostKeyChecking=accept-new'
      . ' -o UserKnownHostsFile=/dev/null'
      . ' -o LogLevel=ERROR'
      . ' -o BatchMode=yes'
      . ' -o ConnectTimeout=' . $timeout
      . ' -p '  . $port
      . ' '     . escapeshellarg("{$user}@{$srv->ip_address}")
      . ' '     . escapeshellarg("timeout {$timeout} systemctl is-active mariadb 2>&1");

    $start = microtime(true);
    exec($cmd, $out, $code);
    $ms = (int) ((microtime(true) - $start) * 1000);

    if ($code === 0) {
      return ['status' => 'healthy', 'message' => 'MariaDB active (via SSH)', 'ms' => $ms];
    }
    $detail = trim(implode(' ', $out) ?: 'service not active');
    return ['status' => 'unhealthy', 'message' => 'MariaDB: ' . substr($detail, 0, 120), 'ms' => $ms];
  }

  private function _is_safe_host(string $host): bool
  {
    // Strip scheme and path — we just need the hostname/IP
    $bare = preg_replace('#^https?://|/.*$#', '', $host);
    $bare = strtok($bare, ':'); // remove port
    $ip   = filter_var($bare, FILTER_VALIDATE_IP) ? $bare : gethostbyname($bare);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    // Reject loopback, RFC1918, link-local (SSRF targets)
    return filter_var(
      $ip,
      FILTER_VALIDATE_IP,
      FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
  }

  private function _probe_http(string $domain, string $fallback_ip): array
  {
    $host = $domain ?: $fallback_ip;
    if (empty($host)) {
      return ['status' => 'unknown', 'message' => 'No host configured', 'ms' => null, 'http_code' => null];
    }
    if (!$this->_is_safe_host($host)) {
      return ['status' => 'unknown', 'message' => 'Host not reachable from probe', 'ms' => null, 'http_code' => null];
    }

    $url = (str_starts_with($host, 'http') ? '' : 'http://') . $host;
    $start = microtime(true);

    $ctx = stream_context_create(['http' => [
      'timeout'         => $this->probe_timeout,
      'ignore_errors'   => true,
      'follow_location' => true,
    ]]);

    $headers = @get_headers($url, false, $ctx);
    $ms = (int) ((microtime(true) - $start) * 1000);

    if ($headers === false) {
      return ['status' => 'unhealthy', 'message' => 'Connection failed', 'ms' => $ms, 'http_code' => null];
    }

    $code = (int) substr($headers[0] ?? '', 9, 3);
    $ok   = $code >= 200 && $code < 400;
    return [
      'status'    => $ok ? 'healthy' : 'unhealthy',
      'message'   => trim($headers[0] ?? 'No response'),
      'ms'        => $ms,
      'http_code' => $code ?: null,
    ];
  }

  private function _probe_tcp(string $host, int $port): array
  {
    if (empty($host) || $port <= 0) {
      return ['status' => 'unknown', 'message' => 'No host/port configured', 'ms' => null];
    }
    if (!$this->_is_safe_host($host)) {
      return ['status' => 'unknown', 'message' => 'Host not reachable from probe', 'ms' => null];
    }

    $start = microtime(true);
    $conn  = @fsockopen($host, $port, $errno, $errstr, $this->probe_timeout);
    $ms    = (int) ((microtime(true) - $start) * 1000);

    if ($conn === false) {
      return ['status' => 'unhealthy', 'message' => "Port {$port} closed: {$errstr}", 'ms' => $ms];
    }

    fclose($conn);
    return ['status' => 'healthy', 'message' => "Port {$port} open", 'ms' => $ms];
  }

  private function _require_auth(): void
  {
    $this->module('trongate_tokens');
    if (!$this->trongate_tokens->_attempt_get_valid_token(1)) {
      redirect('login');
    }
  }
}
