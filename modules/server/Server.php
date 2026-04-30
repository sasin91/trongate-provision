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
      "additional_includes_top" => ["server_module/css/server.css"],
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
      "additional_includes_top" => ["server_module/css/server.css"],
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

    $runner_public_key = $this->_runner_public_key();
    if ($runner_public_key === "") {
      $_SESSION["flash_error"] =
        "Runner SSH public key not found. Create " .
        RUNNER_SSH_KEY .
        ".pub before provisioning Hetzner servers.";
      return false;
    }

    $runner_key = $h->ensure_ssh_key(
      "provision-runner-" . substr(md5(BASE_URL), 0, 8),
      $runner_public_key,
    );
    $ssh_key_ids = $this->_hetzner_ssh_key_ids($creds);
    $ssh_key_ids[] = $runner_key["id"];
    $ssh_key_ids = array_values(array_unique(array_filter($ssh_key_ids)));

    $this->provider->model->save_hetzner(
      (int) $customer->id,
      $creds["token"],
      $creds["ssh_key_id"] ?? $runner_key["id"],
      $creds["ssh_key_label"] ?? $runner_key["name"],
      $ssh_key_ids,
    );

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
      "ipv6_address" => $result["ipv6"] ?? null,
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
      "ipv6_address" => $remote["ipv6"] ?? null,
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
          (
            $server->ip_address !== $remote["ip"] ||
            ($server->ipv6_address ?? null) !== ($remote["ipv6"] ?? null)
          )
        ) {
          $server->ip_address = $remote["ip"];
          $server->ipv6_address = $remote["ipv6"] ?? null;
          $server->status = "active";
          $this->db->update(
            $id,
            [
              "ip_address" => $remote["ip"],
              "ipv6_address" => $remote["ipv6"] ?? null,
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
      "additional_includes_top" => array_filter([
        "server_module/css/server.css",
        $server->status === 'pending' ? '<meta http-equiv="refresh" content="8">' : null,
      ]),
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
    $this->_start_event_stream();
    $emit = static function (string $line, string $event = ""): void {
      if ($event !== "") {
        echo "event: {$event}\n";
      }
      echo "data: " . $line . "\n\n";
      flush();
    };

    $this->module("trongate_tokens");
    $token = $this->trongate_tokens->attempt_get_valid_token(
      Customer::CUSTOMER_LEVEL,
    );
    if ($token === false) {
      http_response_code(401);
      $emit("Unauthorized.");
      $emit(json_encode(["status" => "failed"]), "done");
      exit();
    }
    $this->module("customer");
    $customer = $this->customer->model->_get_current_customer();
    if ($customer === false) {
      http_response_code(401);
      $emit("Unauthorized.");
      $emit(json_encode(["status" => "failed"]), "done");
      exit();
    }

    $is_onboarding_server = (int) ($customer->onboarding_server_id ?? 0) === $id;
    if (empty($customer->onboarded_at) && !$is_onboarding_server) {
      http_response_code(401);
      $emit("Unauthorized for this onboarding server.");
      $emit(json_encode(["status" => "failed"]), "done");
      exit();
    }

    $s = $this->model->get($id, (int) $customer->id);
    if ($s === false) {
      http_response_code(404);
      $emit("Server not found.");
      $emit(json_encode(["status" => "failed"]), "done");
      exit();
    }

    if (!defined("RUNNER_SSH_KEY") || !RUNNER_SSH_KEY) {
      http_response_code(503);
      $emit("RUNNER_SSH_KEY is not configured.");
      $emit(json_encode(["status" => "failed"]), "done");
      exit();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }

    ignore_user_abort(true);
    ini_set("output_buffering", "Off");
    ini_set("zlib.output_compression", "Off");
    set_time_limit(0);
    while (ob_get_level() > 0) {
      ob_end_clean();
    }

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
      if (($s->status ?? "") === "active") {
        $emit("Server is already provisioned and active.");
        $emit(json_encode(["status" => "active"]), "done");
        return;
      }

      if (($s->status ?? "") === "failed") {
        $emit("Previous provisioning attempt failed.");
        $emit(json_encode(["status" => "failed"]), "done");
        return;
      }

      $this->_wait_for_existing_provisioning($id, (int) $customer->id, $emit);
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
      " -o UserKnownHostsFile=/dev/null" .
      " -o LogLevel=ERROR" .
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

  function _start_event_stream(): void
  {
    header("Content-Type: text/event-stream");
    header("Cache-Control: no-cache");
    header("X-Accel-Buffering: no");
    header("Connection: keep-alive");
  }

  function _wait_for_existing_provisioning(int $id, int $customer_id, callable $emit): void
  {
    $emit("Server is already being provisioned; waiting for the running job to finish.");

    $timeout = 90;
    $started = time();

    while ((time() - $started) < $timeout) {
      sleep(5);

      $server = $this->model->get($id, $customer_id);
      if ($server === false) {
        $emit("Server not found.");
        $emit(json_encode(["status" => "failed"]), "done");
        return;
      }

      if ($server->status === "active" || $server->status === "failed") {
        $emit(json_encode(["status" => $server->status]), "done");
        return;
      }

      echo ": waiting-for-existing-provision\n\n";
      flush();
    }

    $this->model->mark_result($id, "failed");
    $emit("Provisioning did not finish after the browser reconnected. Restarting provisioning.");
    $emit(json_encode(["status" => "retry"]), "done");
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

  function enable_ssl(): void
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
      return;
    }

    $domain = trim((string) ($server->domain ?? ""));
    $email = trim((string) ($server->customer_email ?? ""));
    $user = $server->ssh_user ?: "root";

    if (!defined("RUNNER_SSH_KEY") || !RUNNER_SSH_KEY) {
      $_SESSION["flash_error"] = "RUNNER_SSH_KEY is not configured.";
      redirect("server/show/" . $id);
      return;
    }
    if (!filter_var($server->ip_address, FILTER_VALIDATE_IP)) {
      $_SESSION["flash_error"] = "Invalid server IP address.";
      redirect("server/show/" . $id);
      return;
    }
    if (!preg_match('/^[a-z_][a-z0-9_.-]{0,31}$/i', $user)) {
      $_SESSION["flash_error"] = "Invalid SSH user.";
      redirect("server/show/" . $id);
      return;
    }
    if (!$this->_valid_domain($domain)) {
      $_SESSION["flash_error"] =
        "Configure a valid environment domain before enabling SSL.";
      redirect("server/show/" . $id);
      return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $_SESSION["flash_error"] =
        "Configure a valid customer email before enabling SSL.";
      redirect("server/show/" . $id);
      return;
    }

    $script = $this->_render_certbot_script($server);
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }

    [$exit_code, $log] = $this->_run_remote_bash($server, $script);

    if (session_status() !== PHP_SESSION_ACTIVE) {
      session_start();
    }

    if ($exit_code === 0) {
      $_SESSION["flash_success"] =
        "SSL enabled for " . $domain . ".";
      $this->_emit("ServerSslEnabled", "server", $id, [
        "domain" => $domain,
      ]);
    } else {
      $_SESSION["flash_error"] =
        $this->_ssl_failure_message($log, $exit_code);
      $this->_emit("ServerSslFailed", "server", $id, [
        "domain" => $domain,
        "exit_code" => $exit_code,
      ]);
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
    return (string) $this->view(
      "scripts/default_provision_script",
      ["view_module" => "server"],
      true,
    );
  }

  private function _render_certbot_script(object $server): string
  {
    return (string) $this->view(
      "scripts/certbot_script",
      [
        "view_module" => "server",
        "server" => $server,
      ],
      true,
    );
  }

  private function _run_remote_bash(object $server, string $script): array
  {
    $user = $server->ssh_user ?: "root";
    $port = (int) ($server->ssh_port ?: 22);
    $timeout = RUNNER_SCRIPT_TIMEOUT;
    $cmd =
      "ssh" .
      " -i " .
      escapeshellarg(RUNNER_SSH_KEY) .
      " -o StrictHostKeyChecking=no" .
      " -o UserKnownHostsFile=/dev/null" .
      " -o LogLevel=ERROR" .
      " -o BatchMode=yes" .
      " -o ConnectTimeout=15" .
      " -o ServerAliveInterval=30" .
      " -o ServerAliveCountMax=3" .
      " -p " .
      $port .
      " " .
      escapeshellarg("{$user}@{$server->ip_address}") .
      " 'timeout {$timeout} bash -s' 2>&1";

    $proc = proc_open(
      $cmd,
      [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["file", "/dev/null", "w"]],
      $pipes,
    );
    if (!is_resource($proc)) {
      return [1, "Failed to open SSH connection."];
    }

    fwrite($pipes[0], $script);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return [proc_close($proc), trim($output)];
  }

  private function _ssl_failure_message(string $log, int $exit_code): string
  {
    $message = trim($log);

    if ($exit_code === 124) {
      return "SSL setup failed: the remote setup timed out before finishing. Check whether apt, Apache, or certbot is still running on the server, then try again.";
    }

    if (preg_match('/SUDO_DENIED_COMMAND=([^\r\n]+)/', $message, $matches)) {
      return "SSL setup failed: passwordless sudo is missing for `" . trim($matches[1]) . "`. Add that command to /etc/sudoers.d/provision, then try again.";
    }

    if (stripos($message, "requires root privileges") !== false ||
      (stripos($message, "permission denied") !== false && stripos($message, "apt") !== false)
    ) {
      return "SSL setup failed: the SSH user needs root privileges to install certbot. Connect as root or configure passwordless sudo, then try again.";
    }

    if ($exit_code === 13) {
      return "SSL setup failed: sudo rejected one of the required commands. Details: " . $this->_tail_text($message ?: "No remote sudo output was captured.", 800);
    }

    if (stripos($message, "Timed out waiting for apt/dpkg locks") !== false ||
      $exit_code === 75 ||
      stripos($message, "Could not get lock") !== false ||
      stripos($message, "Unable to lock directory") !== false
    ) {
      return "SSL setup failed: another apt/dpkg process is still running on the server. Wait a few minutes, then try again.";
    }

    if ($message === "") {
      return "SSL setup failed with exit code " . $exit_code . " before producing output. Retry once; if it repeats, run certbot manually on the server to see the underlying error.";
    }

    return "SSL setup failed: " . substr($message ?: "certbot failed.", 0, 500);
  }

  private function _tail_text(string $message, int $length): string
  {
    return strlen($message) > $length ? "..." . substr($message, -$length) : $message;
  }

  private function _valid_domain(string $domain): bool
  {
    if ($domain === "" || strlen($domain) > 253) {
      return false;
    }
    return (bool) preg_match(
      '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
      $domain,
    );
  }

  private function _hetzner_ssh_key_ids(array $creds): array
  {
    $ids = $creds["ssh_key_ids"] ?? [];
    if (!is_array($ids)) {
      $ids = [];
    }
    if (!empty($creds["ssh_key_id"])) {
      array_unshift($ids, $creds["ssh_key_id"]);
    }
    $normalized = [];
    foreach ($ids as $id) {
      $id = trim((string) $id);
      if ($id === "" || in_array($id, $normalized, true)) {
        continue;
      }
      $normalized[] = $id;
    }
    return $normalized;
  }

  private function _runner_public_key(): string
  {
    if (!defined("RUNNER_SSH_KEY") || RUNNER_SSH_KEY === "") {
      return "";
    }
    $path = RUNNER_SSH_KEY . ".pub";
    if (is_readable($path)) {
      return trim((string) file_get_contents($path));
    }
    exec("ssh-keygen -y -f " . escapeshellarg(RUNNER_SSH_KEY) . " 2>/dev/null", $out, $code);
    return $code === 0 ? trim(implode("\n", $out)) : "";
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
