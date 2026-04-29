<?php

require_once __DIR__ . "/../event/Emits_events.php";
require_once __DIR__ . "/../script/Script_model.php";

class Server extends Trongate
{
  use Emits_events;

  function index(): void
  {
    $customer = $this->_require_onboarded_customer();

    $data = [
      "view_module" => "server",
      "view_file" => "index",
      "page_title" => "Servers",
      "current_email" => $customer->email,
      "servers" => $this->model->all((int) $customer->id),
    ];

    $this->module("templates");
    $this->templates->customer($data);
  }

  function create(): void
  {
    $customer = $this->_require_onboarded_customer();

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
      $provider = post("provider", true) ?: "manual";
      $stored = match ($provider) {
        "hetzner" => $this->_try_store_hetzner($customer),
        "hetzner_import" => $this->_try_import_hetzner($customer),
        default => $this->_try_store_manual($customer),
      };
      if ($stored) {
        return;
      }
    }

    $preselected_env = (int) ($_GET["env"] ?? 0);

    $this->module("provider");
    $has_hetzner = $this->provider->model->has_hetzner((int) $customer->id);
    $hetzner_regions = [];
    $hetzner_types = [];
    $hetzner_servers = [];

    if ($has_hetzner) {
      try {
        $h = $this->_hetzner_client((int) $customer->id);
        $hetzner_regions = $h->list_regions();
        $hetzner_types = $h->list_server_types();
        $all_servers = $h->list_servers();
        $tracked = $this->model->tracked_hetzner_ids((int) $customer->id);
        $hetzner_servers = array_values(
          array_filter($all_servers, fn($s) => !in_array($s["id"], $tracked)),
        );
      } catch (Throwable $e) {
        $has_hetzner = false;
        $_SESSION["flash_error"] =
          "Could not load Hetzner data: " . $e->getMessage();
      }
    }

    $data = [
      "view_module" => "server",
      "view_file" => "create",
      "page_title" => "Add Server",
      "current_email" => $customer->email,
      "form_location" => "server/create",
      "environments" => $this->model->environments_for_customer(
        (int) $customer->id,
      ),
      "preselected_env" => $preselected_env,
      "has_hetzner" => $has_hetzner,
      "hetzner_regions" => $hetzner_regions,
      "hetzner_types" => $hetzner_types,
      "hetzner_servers" => $hetzner_servers,
    ];

    $this->module("templates");
    $this->templates->customer($data);
  }

  private function _try_store_manual(object $customer): bool
  {
    $this->validation->set_rules("name", "name", "required|max_length[100]");
    $this->validation->set_rules("environment_id", "environment", "required");
    $this->validation->set_rules(
      "ip_address",
      "IP address",
      "required|max_length[45]",
    );
    $this->validation->set_rules(
      "ssh_user",
      "SSH user",
      "required|max_length[100]",
    );
    $this->validation->set_rules("ssh_port", "SSH port", "required");

    if ($this->validation->run() !== true) {
      return false;
    }

    $id = $this->model->create([
      "environment_id" => (int) post("environment_id"),
      "customer_id" => (int) $customer->id,
      "name" => post("name", true),
      "ip_address" => post("ip_address", true),
      "ssh_user" => post("ssh_user", true),
      "ssh_port" => (int) post("ssh_port"),
      "provider" => "manual",
      "status" => "pending",
    ]);

    $this->_emit("ServerCreated", "server", (int) $id, [
      "provider" => "manual",
      "environment_id" => (int) post("environment_id"),
      "ip_address" => post("ip_address", true),
    ]);
    $_SESSION["flash_success"] =
      "Server added. Run the provision script on your server to complete setup.";
    redirect("server/show/" . $id);
    return true;
  }

  private function _try_store_hetzner(object $customer): bool
  {
    $this->validation->set_rules("name", "name", "required|max_length[100]");
    $this->validation->set_rules("environment_id", "environment", "required");
    $this->validation->set_rules("region", "region", "required");
    $this->validation->set_rules("server_type", "server type", "required");

    if ($this->validation->run() !== true) {
      return false;
    }

    $this->module("provider");
    if (!$this->provider->model->has_hetzner((int) $customer->id)) {
      $_SESSION["flash_error"] = "Hetzner not connected.";
      return false;
    }

    $h = $this->_hetzner_client((int) $customer->id);
    $creds = $this->provider->model->get_hetzner((int) $customer->id);
    $name = post("name", true);
    $region = post("region", true);
    $type = post("server_type", true);

    $ssh_key_ids = !empty($creds["ssh_key_id"]) ? [$creds["ssh_key_id"]] : [];

    $result = $h->create_server(
      name: preg_replace("/[^a-z0-9-]/", "-", strtolower($name)),
      type: $type,
      region: $region,
      ssh_keys: $ssh_key_ids,
      labels: ["managed_by" => "provision"],
      user_data: "",
      image: "ubuntu-22.04",
    );

    $id = $this->model->create([
      "environment_id" => (int) post("environment_id"),
      "customer_id" => (int) $customer->id,
      "name" => $name,
      "ip_address" => $result["ipv4"],
      "ssh_user" => "root",
      "ssh_port" => 22,
      "provider" => "hetzner",
      "provider_id" => $result["provider_id"],
      "region" => $result["region"],
      "server_type" => $result["type"],
      "status" => "pending",
    ]);

    $this->_emit("ServerCreated", "server", (int) $id, [
      "provider" => "hetzner",
      "environment_id" => (int) post("environment_id"),
      "ip_address" => $result["ipv4"],
      "region" => $result["region"],
      "server_type" => $result["type"],
    ]);
    $_SESSION["flash_success"] =
      "Hetzner server is being provisioned. Run the LAMP setup script once it reaches Active status.";
    redirect("server/show/" . $id);
    return true;
  }

  private function _try_import_hetzner(object $customer): bool
  {
    $this->validation->set_rules("name", "name", "required|max_length[100]");
    $this->validation->set_rules("environment_id", "environment", "required");
    $this->validation->set_rules("hetzner_id", "server", "required");

    if ($this->validation->run() !== true) {
      return false;
    }

    $this->module("provider");
    if (!$this->provider->model->has_hetzner((int) $customer->id)) {
      $_SESSION["flash_error"] = "Hetzner not connected.";
      return false;
    }

    $h = $this->_hetzner_client((int) $customer->id);
    $provider_id = post("hetzner_id", true);
    $remote = $h->get_server($provider_id);

    if (!$remote) {
      $_SESSION["flash_error"] = "Server not found in your Hetzner account.";
      return false;
    }

    $id = $this->model->create([
      "environment_id" => (int) post("environment_id"),
      "customer_id" => (int) $customer->id,
      "name" => post("name", true),
      "ip_address" => $remote["ip"],
      "ssh_user" => "root",
      "ssh_port" => 22,
      "provider" => "hetzner",
      "provider_id" => $remote["provider_id"],
      "region" => $remote["region"],
      "server_type" => $remote["type"],
      "status" => $remote["status"] === "running" ? "provisioning" : "pending",
    ]);

    $this->_emit("ServerCreated", "server", (int) $id, [
      "provider" => "hetzner",
      "environment_id" => (int) post("environment_id"),
      "ip_address" => $remote["ip"],
      "region" => $remote["region"],
      "server_type" => $remote["type"],
    ]);
    $_SESSION["flash_success"] =
      "Hetzner server imported. Run the LAMP setup script to prepare it for deployments.";
    redirect("server/show/" . $id);
    return true;
  }

  function server_types_options(): void
  {
    $customer = $this->_require_customer();

    $location = preg_replace(
      "/[^a-z0-9-]/",
      "",
      strtolower(trim($_GET["location"] ?? "fsn1")),
    );
    if (empty($location)) {
      $location = "fsn1";
    }

    try {
      $h = $this->_hetzner_client((int) $customer->id);
      $server_types = $h->list_server_types($location);
    } catch (Throwable) {
      http_response_code(503);
      echo '<p style="color:#b91c1c;font-size:.85rem;padding:.5rem">Could not load server types for this region.</p>';
      return;
    }

    header("Content-Type: text/html");
    $this->view("_server_type_cards", ["server_types" => $server_types]);
  }

  function show(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_onboarded_customer();
    $server = $this->model->get($id, (int) $customer->id);
    if ($server === false) {
      redirect("server");
    }

    // Sync IP from Hetzner while still pending
    if (
      $server->provider === "hetzner" &&
      !empty($server->provider_id) &&
      $server->status === "pending"
    ) {
      try {
        $h = $this->_hetzner_client((int) $customer->id);
        $remote = $h->get_server($server->provider_id);
        if (
          $remote &&
          !empty($remote["ip"]) &&
          $server->ip_address !== $remote["ip"]
        ) {
          $server->ip_address = $remote["ip"];
          $server->status = "active";
          $this->db->update(
            $id,
            [
              "ip_address" => $remote["ip"],
              "status" => "active",
            ],
            "server",
          );
        }
      } catch (Throwable) {
      }
    }

    $this->module("deployment");
    $deployments = $this->deployment->model->by_server(
      $id,
      (int) $customer->id,
    );

    $this->module("script");
    $lamp_scripts = $this->script->model->scripts_for_server(
      $id,
      (int) $customer->id,
    );
    $latest = $this->script->model->latest_for_server($id, (int) $customer->id);
    $lamp_script =
      $latest !== false ? $latest->body : $this->_generate_lamp_script($server);

    $data = [
      "view_module" => "server",
      "view_file" => "show",
      "page_title" => htmlspecialchars($server->name),
      "current_email" => $customer->email,
      "server" => $server,
      "deployments" => $deployments,
      "lamp_script" => $lamp_script,
      "lamp_scripts" => $lamp_scripts,
      "lamp_vars" => Script_model::LAMP_VARS,
      "additional_includes_top" => [
        '<meta refresh="8" content="8;url=server/show/<?= (int) $server->id ?>">',
      ],
    ];

    $this->module("templates");
    $this->templates->customer($data);
  }

  function save_lamp_script(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_onboarded_customer();
    $server = $this->model->get($id, (int) $customer->id);
    if ($server === false) {
      redirect("server");
    }

    $body = post("body");
    if (empty(trim($body ?? ""))) {
      $_SESSION["flash_error"] = "Script body cannot be empty.";
      redirect("server/show/" . $id);
      return;
    }

    $this->module("script");
    $this->script->model->create([
      "customer_id" => (int) $customer->id,
      "server_id" => $id,
      "name" => $server->name . " — LAMP — " . date("Y-m-d H:i"),
      "type" => "lamp",
      "body" => $body,
    ]);

    $_SESSION["flash_success"] = "LAMP script saved.";
    redirect("server/show/" . $id);
  }

  function reboot(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_onboarded_customer();
    $this->validation->set_rules("dummy", "dummy", "max_length[1]");
    if ($this->validation->run() !== true) {
      redirect("server/show/" . $id);
      return;
    }
    $server = $this->model->get($id, (int) $customer->id);
    if (
      $server === false ||
      $server->provider !== "hetzner" ||
      empty($server->provider_id)
    ) {
      redirect("server");
    }
    try {
      $h = $this->_hetzner_client((int) $customer->id);
      $h->reboot_server($server->provider_id);
      $_SESSION["flash_success"] = "Reboot requested.";
    } catch (Throwable $e) {
      $_SESSION["flash_error"] = "Reboot failed: " . $e->getMessage();
    }
    redirect("server/show/" . $id);
  }

  function destroy_hetzner(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_onboarded_customer();
    $this->validation->set_rules("dummy", "dummy", "max_length[1]");
    if ($this->validation->run() !== true) {
      redirect("server/show/" . $id);
      return;
    }
    $server = $this->model->get($id, (int) $customer->id);
    if ($server === false) {
      redirect("server");
    }

    if ($server->provider === "hetzner" && !empty($server->provider_id)) {
      try {
        $h = $this->_hetzner_client((int) $customer->id);
        $h->delete_server($server->provider_id);
      } catch (Throwable) {
      }
    }

    $this->model->delete($id, (int) $customer->id);
    $this->_emit("ServerDeleted", "server", $id, [
      "name" => $server->name ?? null,
      "provider" => $server->provider ?? null,
      "ip_address" => $server->ip_address ?? null,
    ]);
    $_SESSION["flash_success"] =
      "Server deleted from Provision" .
      ($server->provider === "hetzner" ? " and Hetzner." : ".");
    redirect("server");
  }

  function stream(): void
  {
    $id = (int) segment(3);

    $this->module("trongate_tokens");
    $token = $this->trongate_tokens->attempt_get_valid_token(
      Customer::CUSTOMER_LEVEL,
    );
    if ($token === false) {
      http_response_code(401);
      exit();
    }
    $this->module("customer");
    $customer = $this->customer->model->_get_current_customer();
    if ($customer === false || empty($customer->onboarded_at)) {
      http_response_code(401);
      exit();
    }

    $s = $this->model->get($id, (int) $customer->id);
    if ($s === false) {
      http_response_code(404);
      exit();
    }

    if (!defined("RUNNER_SSH_KEY") || !RUNNER_SSH_KEY) {
      http_response_code(503);
      echo "RUNNER_SSH_KEY is not configured.";
      exit();
    }

    header("Content-Type: text/event-stream");
    header("Cache-Control: no-cache");
    header("X-Accel-Buffering: no");
    header("Connection: keep-alive");
    ini_set("output_buffering", "Off");
    ini_set("zlib.output_compression", "Off");
    set_time_limit(0);
    while (ob_get_level() > 0) {
      ob_end_clean();
    }

    $emit = static function (string $line, string $event = ""): void {
      if ($event !== "") {
        echo "event: {$event}\n";
      }
      echo "data: " . $line . "\n\n";
      flush();
    };

    $user = $s->ssh_user ?: "root";
    $port = (int) ($s->ssh_port ?: 22);

    if (!filter_var($s->ip_address, FILTER_VALIDATE_IP)) {
      $emit("Invalid server IP address.");
      $emit(json_encode(["status" => "failed"]), "done");
      return;
    }
    if (!preg_match('/^[a-z_][a-z0-9_.-]{0,31}$/i', $user)) {
      $emit("Invalid SSH user.");
      $emit(json_encode(["status" => "failed"]), "done");
      return;
    }
    if (!$this->model->mark_provisioning($id)) {
      $emit("Server is already being provisioned.");
      $emit(json_encode(["status" => "provisioning"]), "done");
      return;
    }

    $this->_emit("ServerStatusChanged", "server", $id, [
      "from" => $s->status,
      "to" => "provisioning",
      "source" => "stream",
    ]);

    $provision_user = getenv("PROVISION_USER") ?: "provision";

    $this->module("environment");
    $env_vars = $this->environment->model->decrypt_blob(
      $s->env_variables_enc ?? "",
    );
    $db_name =
      $env_vars["DB_NAME"] ?? ($env_vars["db_name"] ?? ($s->db_name ?? ""));
    $db_user = $env_vars["DB_USER"] ?? ($env_vars["db_user"] ?? "");
    $db_pass = $env_vars["DB_PASSWORD"] ?? ($env_vars["db_password"] ?? "");

    $q = static fn(string $v): string => "'" .
      str_replace("'", "'\\''", $v) .
      "'";
    $exports = "";
    foreach (
      [
        "PROVISION_USER" => $provision_user,
        "LIVE_LINK" => $s->web_root ?: "/var/www/html",
        "DOMAIN" => $s->domain ?? "",
        "CERTBOT_EMAIL" => $s->customer_email ?? "",
        "DB_NAME" => $db_name,
        "DB_USER" => $db_user,
        "DB_PASSWORD" => $db_pass,
      ]
      as $k => $v
    ) {
      if ($v !== null && $v !== "") {
        $exports .= "export {$k}={$q($v)}\n";
      }
    }

    $script = $exports . $this->_default_provision_script();
    $timeout = RUNNER_SCRIPT_TIMEOUT;

    $cmd =
      "ssh" .
      " -i " .
      escapeshellarg(RUNNER_SSH_KEY) .
      " -o StrictHostKeyChecking=no" .
      " -o BatchMode=yes" .
      " -o ConnectTimeout=15" .
      " -o ServerAliveInterval=30" .
      " -o ServerAliveCountMax=3" .
      " -p " .
      $port .
      " " .
      escapeshellarg("{$user}@{$s->ip_address}") .
      " 'timeout {$timeout} bash -s'";

    $provider = strtolower((string) ($s->provider ?? ""));
    $max_ssh_attempts = $provider === "hetzner" ? 20 : 3;
    $retry_delay = 15;
    $exit_code = 255;

    for ($attempt = 1; $attempt <= $max_ssh_attempts; $attempt++) {
      if ($attempt === 1) {
        $emit("Connecting to {$s->ip_address}:{$port}…");
      } else {
        $emit(
          "SSH is not ready yet; retrying connection ({$attempt}/{$max_ssh_attempts})…",
        );
      }

      $proc = proc_open(
        $cmd,
        [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]],
        $pipes,
      );
      if (!is_resource($proc)) {
        $emit("Failed to open SSH connection.");
        $emit(json_encode(["status" => "failed"]), "done");
        $this->model->mark_result($id, "failed");
        return;
      }

      fwrite($pipes[0], $script);
      fclose($pipes[0]);

      stream_set_blocking($pipes[1], false);
      stream_set_blocking($pipes[2], false);

      $attempt_log = "";
      $open = [1 => $pipes[1], 2 => $pipes[2]];
      $buf = [1 => "", 2 => ""];

      while (!empty($open)) {
        $read = array_values($open);
        $w = $e = null;
        $n = stream_select($read, $w, $e, 15);
        if ($n === false) {
          break;
        }
        if ($n === 0) {
          echo ": ping\n\n";
          flush();
          continue;
        }

        foreach ($read as $fh) {
          $chunk = fread($fh, 4096);
          if ($chunk !== false && $chunk !== "") {
            $key = $fh === $pipes[1] ? 1 : 2;
            $buf[$key] .= $chunk;
            $attempt_log .= $chunk;
            while (($nl = strpos($buf[$key], "\n")) !== false) {
              $line = substr($buf[$key], 0, $nl);
              $buf[$key] = substr($buf[$key], $nl + 1);
              if ($line !== "") {
                $emit(rtrim($line, "\r"));
              }
            }
          }
          if (feof($fh)) {
            $key = $fh === $pipes[1] ? 1 : 2;
            if ($buf[$key] !== "") {
              $emit(rtrim($buf[$key], "\r\n"));
              $buf[$key] = "";
            }
            $open = array_filter($open, static fn($p) => $p !== $fh);
          }
        }
      }

      fclose($pipes[1]);
      fclose($pipes[2]);
      $exit_code = proc_close($proc);

      if ($exit_code === 0) {
        break;
      }

      $ssh_not_ready =
        $exit_code === 255 &&
        preg_match(
          '/(connection timed out|connection refused|no route to host|network is unreachable|operation timed out)/i',
          $attempt_log,
        );

      if (!$ssh_not_ready || $attempt >= $max_ssh_attempts) {
        break;
      }

      $emit("SSH is still unavailable; waiting {$retry_delay} seconds…");
      for ($waited = 0; $waited < $retry_delay; $waited += 5) {
        sleep(min(5, $retry_delay - $waited));
        echo ": waiting-for-ssh\n\n";
        flush();
      }
    }

    if ($exit_code === 0) {
      $this->model->mark_result($id, "active", $provision_user);
      $this->db->query_bind(
        "UPDATE service SET status = 'active' WHERE environment_id = :eid",
        ["eid" => (int) $s->environment_id],
      );
      $this->_emit("ServerStatusChanged", "server", $id, [
        "from" => "provisioning",
        "to" => "active",
        "source" => "stream",
      ]);
      $emit(json_encode(["status" => "active"]), "done");
    } else {
      $this->model->mark_result($id, "failed");
      $this->_emit("ServerStatusChanged", "server", $id, [
        "from" => "provisioning",
        "to" => "failed",
        "source" => "stream",
      ]);
      $emit(json_encode(["status" => "failed"]), "done");
    }
  }

  function mark_active(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_onboarded_customer();
    $this->validation->set_rules("dummy", "dummy", "max_length[1]");
    if ($this->validation->run() !== true) {
      redirect("server/show/" . $id);
      return;
    }
    $server = $this->model->get($id, (int) $customer->id);
    if ($server !== false) {
      $old_status = $server->status;
      $this->model->update_status($id, "active");
      $this->_emit("ServerStatusChanged", "server", $id, [
        "from" => $old_status,
        "to" => "active",
        "source" => "manual",
      ]);
      $_SESSION["flash_success"] = "Server marked as active.";
    }
    redirect("server/show/" . $id);
  }

  function delete(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_onboarded_customer();
    $this->validation->set_rules("dummy", "dummy", "max_length[1]");
    if ($this->validation->run() !== true) {
      redirect("server/show/" . $id);
      return;
    }
    $snap = $this->model->get($id, (int) $customer->id);
    $this->model->delete($id, (int) $customer->id);
    $this->_emit("ServerDeleted", "server", $id, [
      "name" => $snap ? $snap->name : null,
      "provider" => $snap ? $snap->provider : null,
      "ip_address" => $snap ? $snap->ip_address : null,
    ]);
    $_SESSION["flash_success"] = "Server deleted.";
    redirect("server");
  }

  private function _generate_lamp_script(object $server): string
  {
    return (string) $this->view(
      "scripts/lamp_script",
      [
        "view_module" => "server",
        "server" => $server,
      ],
      true,
    );
  }

  private function _default_provision_script(): string
  {
    return <<<'BASH'
    #!/bin/bash
    set -euo pipefail

    PROVISION_USER="${PROVISION_USER:-provision}"
    RELEASES_DIR="${RELEASES_DIR:-/var/www/releases}"
    LIVE_LINK="${LIVE_LINK:-/var/www/html}"
    DOMAIN="${DOMAIN:-}"
    CERTBOT_EMAIL="${CERTBOT_EMAIL:-}"
    DB_NAME="${DB_NAME:-}"
    DB_USER="${DB_USER:-}"
    DB_PASSWORD="${DB_PASSWORD:-}"

    # ── LAMP stack (MariaDB) ──────────────────────────────────────────
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -q
    PACKAGES="apache2 php libapache2-mod-php php-mysql php-curl php-mbstring php-xml php-zip mariadb-server unzip"
    [ -n "$DOMAIN" ] && PACKAGES="$PACKAGES certbot python3-certbot-apache"
    apt-get install -y -q $PACKAGES

    # ── Dedicated deploy user ─────────────────────────────────────────
    useradd -m -s /bin/bash "$PROVISION_USER" 2>/dev/null || true
    mkdir -p "/home/$PROVISION_USER/.ssh"
    cp /root/.ssh/authorized_keys "/home/$PROVISION_USER/.ssh/authorized_keys" 2>/dev/null || true
    chown -R "$PROVISION_USER:$PROVISION_USER" "/home/$PROVISION_USER/.ssh"
    chmod 700 "/home/$PROVISION_USER/.ssh"
    chmod 600 "/home/$PROVISION_USER/.ssh/authorized_keys" 2>/dev/null || true

    cat > "/etc/sudoers.d/$PROVISION_USER" << SUDOEOF
    $PROVISION_USER ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload apache2
    $PROVISION_USER ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart apache2
    SUDOEOF
    chmod 440 "/etc/sudoers.d/$PROVISION_USER"

    # ── Apache ────────────────────────────────────────────────────────
    a2enmod rewrite
    sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf
    chown "$PROVISION_USER":www-data /var/www
    chmod 2775 /var/www
    mkdir -p "$RELEASES_DIR"
    chmod 2775 "$RELEASES_DIR"
    # Replace Apache's default html dir so provision user controls the web root
    rm -rf /var/www/html
    mkdir -p /var/www/html
    chown "$PROVISION_USER":www-data /var/www/html
    chmod 2775 /var/www/html
    systemctl enable --now apache2 mariadb

    # ── MariaDB ───────────────────────────────────────────────────────
    if [ -n "$DB_NAME" ]; then
        SQL_TMP=$(mktemp)
        printf "CREATE DATABASE IF NOT EXISTS \`%s\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n" \
            "$DB_NAME" >> "$SQL_TMP"
        if [ -n "$DB_USER" ] && [ -n "$DB_PASSWORD" ]; then
            DB_USER_ESC="${DB_USER//\'/\'\'}"
            DB_PASS_ESC="${DB_PASSWORD//\'/\'\'}"
            printf "CREATE USER IF NOT EXISTS '%s'@'localhost' IDENTIFIED BY '%s';\n" \
                "$DB_USER_ESC" "$DB_PASS_ESC" >> "$SQL_TMP"
            printf "GRANT ALL PRIVILEGES ON \`%s\`.* TO '%s'@'localhost';\n" \
                "$DB_NAME" "$DB_USER_ESC" >> "$SQL_TMP"
            printf "GRANT CREATE ON *.* TO '%s'@'localhost';\n" \
                "$DB_USER_ESC" >> "$SQL_TMP"
            printf "FLUSH PRIVILEGES;\n" >> "$SQL_TMP"
        fi
        mysql -u root < "$SQL_TMP"
        rm -f "$SQL_TMP"

        if [ -n "$DB_USER" ] && [ -n "$DB_PASSWORD" ]; then
            cat > "/home/$PROVISION_USER/.my.cnf" << MYCNFEOF
    [client]
    user=$DB_USER
    password=$DB_PASSWORD
    host=localhost
    MYCNFEOF
            chmod 600 "/home/$PROVISION_USER/.my.cnf"
            chown "$PROVISION_USER:$PROVISION_USER" "/home/$PROVISION_USER/.my.cnf"
        fi
    fi

    # ── SSL via Let's Encrypt ─────────────────────────────────────────
    if [ -n "$DOMAIN" ] && [ -n "$CERTBOT_EMAIL" ]; then
        cat > "/etc/apache2/sites-available/${DOMAIN}.conf" << VHOSTEOF
    <VirtualHost *:80>
        ServerName ${DOMAIN}
        DocumentRoot ${LIVE_LINK}
        <Directory ${LIVE_LINK}>
            Options -Indexes +FollowSymLinks
            AllowOverride All
            Require all granted
        </Directory>
    </VirtualHost>
    VHOSTEOF
        a2ensite "$DOMAIN"
        a2dissite 000-default 2>/dev/null || true
        systemctl reload apache2
        certbot --apache -d "$DOMAIN" --non-interactive --agree-tos -m "$CERTBOT_EMAIL" --redirect
    fi

    echo "Provisioned. User: $PROVISION_USER"
    BASH;
  }

  private function _hetzner_client(int $customer_id): Hetzner
  {
    $this->module("provider");
    $creds = $this->provider->model->get_hetzner($customer_id);
    if (empty($creds["token"])) {
      throw new RuntimeException("Hetzner token not configured.");
    }
    $this->module("cloud");
    return $this->cloud->hetzner($creds["token"]);
  }

  private function _require_customer(): object
  {
    $this->module("customer");
    return $this->customer->_require_customer();
  }

  private function _require_onboarded_customer(): object
  {
    $this->module("customer");
    $this->customer->_require_onboarded();
    return $this->customer->_require_customer();
  }
}
