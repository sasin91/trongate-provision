<?php

require_once __DIR__ . "/../event/Emits_events.php";

class Deployment extends Trongate
{
  use Emits_events;

  function index(): void
  {
    $customer = $this->_require_customer();

    $data = [
      "view_module" => "deployment",
      "view_file" => "index",
      "page_title" => "Deployments",
      "current_email" => $customer->email,
      "deployments" => $this->model->all((int) $customer->id),
    ];

    $this->module("templates");
    $this->templates->customer($data);
  }

  function create(): void
  {
    $customer = $this->_require_customer();

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
      $source_type = post("source_type", true) === "zip" ? "zip" : "git";
      $this->validation->set_rules("server_id", "server", "required");
      $this->validation->set_rules("environment_id", "environment", "required");
      if ($source_type === "git") {
        $this->validation->set_rules(
          "repo_url",
          "repo URL",
          "required|max_length[500]",
        );
        $this->validation->set_rules(
          "branch",
          "branch",
          "required|max_length[100]",
        );
      }

      if ($this->validation->run() === true) {
        $server_id = (int) post("server_id");
        $environment_id = (int) post("environment_id");
        $this->module("server");
        $this->module("environment");
        $server_ok =
          $this->server->model->get($server_id, (int) $customer->id) !== false;
        $env_ok =
          $this->environment->model->get(
            $environment_id,
            (int) $customer->id,
          ) !== false;

        $lock_name = "";

        if (!$server_ok || !$env_ok) {
          $_SESSION["flash_error"] = "Invalid server or environment.";
        } elseif (
          !$this->_env_server_allowed(
            $environment_id,
            $server_id,
            (int) $customer->id,
            $lock_name,
          )
        ) {
          $_SESSION[
            "flash_error"
          ] = "Environment is already deployed to \"{$lock_name}\". Each environment is locked to one server.";
        } else {
          $zip_path = null;
          if ($source_type === "zip") {
            $zip_path = $this->_store_zip();
            if ($zip_path === false) {
              $_SESSION["flash_error"] = "Zip upload failed or missing.";
              goto render_create;
            }
          }

          $script_id_raw = (int) post("script_id");
          if ($script_id_raw > 0) {
            $this->module("script");
            if (
              $this->script->model->get($script_id_raw, (int) $customer->id) ===
              false
            ) {
              $script_id_raw = 0;
            }
          }

          $is_canary = post("is_canary") ? 1 : 0;
          $canary_weight = $is_canary
            ? max(1, min(99, (int) post("canary_weight") ?: 10))
            : 100;

          $id = $this->model->create([
            "server_id" => $server_id,
            "environment_id" => $environment_id,
            "customer_id" => (int) $customer->id,
            "script_id" => $script_id_raw > 0 ? $script_id_raw : null,
            "source_type" => $source_type,
            "repo_url" =>
              $source_type === "git" ? post("repo_url", true) : null,
            "branch" => $source_type === "git" ? post("branch", true) : null,
            "zip_path" => $zip_path,
            "is_canary" => $is_canary,
            "canary_weight" => $canary_weight,
            "status" => "script_ready",
          ]);

          $this->_emit("DeploymentCreated", "deployment", (int) $id, [
            "server_id" => $server_id,
            "environment_id" => $environment_id,
            "source_type" => $source_type,
            "is_canary" => $is_canary,
            "status" => "script_ready",
          ]);
          $_SESSION["flash_success"] =
            "Deployment created. Run the deployment script on your server.";
          redirect("deployment/show/" . $id);
          return;
        }
      }
    }

    render_create:

    $preselected_server = (int) ($_GET["server"] ?? 0);
    $preselected_env = (int) ($_GET["env"] ?? 0);

    $this->module("script");
    $deploy_scripts = $this->script->model->by_type(
      (int) $customer->id,
      "deploy",
    );

    $data = [
      "view_module" => "deployment",
      "view_file" => "create",
      "page_title" => "New Deployment",
      "current_email" => $customer->email,
      "form_location" => "deployment/create",
      "servers" => $this->model->servers_for_customer((int) $customer->id),
      "environments" => $this->model->environments_for_customer(
        (int) $customer->id,
      ),
      "preselected_server" => $preselected_server,
      "preselected_env" => $preselected_env,
      "deploy_scripts" => $deploy_scripts,
    ];

    $this->module("templates");
    $this->templates->customer($data);
  }

  function show(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $deployment = $this->model->get($id, (int) $customer->id);
    if ($deployment === false) {
      redirect("deployment");
    }

    $script = $this->_render_deploy_script($deployment);

    $this->module("environment-services");
    $services = $this->services->model->by_environment(
      (int) $deployment->env_id,
      (int) $customer->id,
    );

    $this->module("script");
    $deploy_scripts = $this->script->model->by_type(
      (int) $customer->id,
      "deploy",
    );

    $this->module("event");
    $recent_events = $this->event->model->recent_for_entity(
      "deployment",
      $id,
      (int) $customer->id,
      5,
    );

    $data = [
      "view_module" => "deployment",
      "view_file" => "show",
      "page_title" => "Deployment #" . $id,
      "current_email" => $customer->email,
      "deployment" => $deployment,
      "deploy_script" => $script,
      "services" => $services,
      "deploy_scripts" => $deploy_scripts,
      "recent_events" => $recent_events,
    ];

    $this->module("templates");
    $this->templates->customer($data);
  }

  function stream(): void
  {
    $id = (int) segment(3);

    // Auth before any output — SSE can't use redirects
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

    $d = $this->model->get($id, (int) $customer->id);
    if ($d === false) {
      http_response_code(404);
      exit();
    }

    if (!defined("RUNNER_SSH_KEY") || !RUNNER_SSH_KEY) {
      http_response_code(503);
      echo "RUNNER_SSH_KEY is not configured.";
      exit();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }

    // SSE setup
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

    $user = $d->ssh_user ?: "root";
    $port = (int) ($d->ssh_port ?: 22);

    // Validate before touching DB state
    if (!filter_var($d->ip_address, FILTER_VALIDATE_IP)) {
      $emit("Invalid server IP address.");
      $emit(json_encode(["status" => "failed", "sha" => null]), "done");
      return;
    }
    if (!preg_match('/^[a-z_][a-z0-9_.-]{0,31}$/i', $user)) {
      $emit("Invalid SSH user.");
      $emit(json_encode(["status" => "failed", "sha" => null]), "done");
      return;
    }

    // Concurrent guard — prevent double-deploy
    if ($d->status === "running") {
      $emit("Deployment is already running.");
      $emit(json_encode(["status" => "running", "sha" => null]), "done");
      return;
    }

    $this->model->mark_running($id);
    $this->_emit("DeploymentStarted", "deployment", $id, [
      "source" => "stream",
    ]);

    $script = $this->_render_deploy_script($d);

    // SCP zip if source is zip and file is present locally
    if (!empty($d->zip_path) && file_exists($d->zip_path)) {
      $remote_zip = "/tmp/provision_deploy_" . $id . "_" . time() . ".zip";
      $emit("Uploading application zip…");
      $scp =
        "scp" .
        " -i " .
        escapeshellarg(RUNNER_SSH_KEY) .
        " -o StrictHostKeyChecking=accept-new" .
        " -o BatchMode=yes" .
        " -o ConnectTimeout=15" .
        " -P " .
        $port .
        " " .
        escapeshellarg($d->zip_path) .
        " " .
        escapeshellarg("{$user}@{$d->ip_address}:{$remote_zip}");
      exec($scp . " 2>&1", $scp_out, $scp_code);
      @unlink($d->zip_path); // local copy no longer needed
      if ($scp_code !== 0) {
        $msg = "SCP failed: " . implode("\n", $scp_out);
        $emit($msg);
        $emit(json_encode(["status" => "failed", "sha" => null]), "done");
        $this->model->finish($id, "failed", $msg, null);
        $this->_emit("DeploymentFailed", "deployment", $id, [
          "reason" => "scp_failed",
        ]);
        return;
      }
      $emit("Zip uploaded to {$remote_zip}");
      $script =
        "export DEPLOY_ZIP='" .
        str_replace("'", "'\\''", $remote_zip) .
        "'\n" .
        $script;
    }

    $timeout = RUNNER_SCRIPT_TIMEOUT;
    $cmd =
      "ssh" .
      " -i " .
      escapeshellarg(RUNNER_SSH_KEY) .
      " -o StrictHostKeyChecking=accept-new" .
      " -o BatchMode=yes" .
      " -o ConnectTimeout=15" .
      " -o ServerAliveInterval=30" .
      " -o ServerAliveCountMax=3" .
      " -p " .
      $port .
      " " .
      escapeshellarg("{$user}@{$d->ip_address}") .
      " 'timeout {$timeout} bash -s'";

    $proc = proc_open(
      $cmd,
      [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]],
      $pipes,
    );
    if (!is_resource($proc)) {
      $emit("Failed to open SSH connection.");
      $emit(json_encode(["status" => "failed", "sha" => null]), "done");
      $this->model->finish($id, "failed", "proc_open failed.", null);
      return;
    }

    fwrite($pipes[0], $script);
    fclose($pipes[0]);

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $log = "";
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
          $log .= $chunk;
          // Emit complete lines only
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
    $log = trim($log);

    $sha = null;
    if (preg_match_all("/SHA\s*:\s*([0-9a-f]{7,40})\b/i", $log, $m)) {
      $sha = strtolower(end($m[1]));
    }

    $status = $exit_code === 0 ? "success" : "failed";
    $this->model->finish($id, $status, $log, $sha);

    if ($status === "success") {
      $this->_emit("DeploymentSucceeded", "deployment", $id, [
        "sha" => $sha,
        "source" => "stream",
      ]);
    } else {
      $this->_emit("DeploymentFailed", "deployment", $id, [
        "exit_code" => $exit_code,
        "source" => "stream",
      ]);
    }

    $emit(json_encode(["status" => $status, "sha" => $sha]), "done");
  }

  function assign_script(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $d = $this->model->get($id, (int) $customer->id);
    if ($d === false) {
      redirect("deployment");
    }

    $this->validation->set_rules("dummy", "dummy", "max_length[1]");
    if ($this->validation->run() !== true) {
      redirect("deployment/show/" . $id);
      return;
    }

    $script_id_raw = (int) post("script_id");
    if ($script_id_raw > 0) {
      $this->module("script");
      if (
        $this->script->model->get($script_id_raw, (int) $customer->id) === false
      ) {
        $script_id_raw = 0;
      }
    }
    $this->model->assign_script(
      $id,
      (int) $customer->id,
      $script_id_raw > 0 ? $script_id_raw : null,
    );
    $this->_emit("DeploymentScriptChanged", "deployment", $id, [
      "script_id" => $script_id_raw > 0 ? $script_id_raw : null,
    ]);
    $_SESSION["flash_success"] =
      $script_id_raw > 0
        ? "Custom script assigned."
        : "Reverted to default generated script.";
    redirect("deployment/show/" . $id);
  }

  function mark_success(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $this->validation->set_rules("dummy", "dummy", "max_length[1]");
    if ($this->validation->run() !== true) {
      redirect("deployment/show/" . $id);
      return;
    }
    $d = $this->model->get($id, (int) $customer->id);
    if ($d !== false) {
      $prev_status = $d->status;
      $this->model->update_status($id, "success");
      $sha = trim(post("deployed_sha", true) ?: "");
      if (preg_match('/^[0-9a-f]{7,40}$/i', $sha)) {
        $this->model->set_deployed_sha($id, strtolower($sha));
      }
      $this->_emit("DeploymentSucceeded", "deployment", $id, [
        "previous_status" => $prev_status,
        "deployed_sha" => $sha ?: null,
      ]);
      $_SESSION["flash_success"] = "Deployment marked as successful.";
    }
    redirect("deployment/show/" . $id);
  }

  function promote_canary(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $this->validation->set_rules("dummy", "dummy", "max_length[1]");
    if ($this->validation->run() !== true) {
      redirect("deployment/show/" . $id);
      return;
    }
    $d = $this->model->get($id, (int) $customer->id);
    if ($d !== false && (int) $d->is_canary === 1) {
      $prev_weight = (int) $d->canary_weight;
      $this->model->promote_canary($id, (int) $customer->id);
      $this->_emit("CanaryPromoted", "deployment", $id, [
        "previous_weight" => $prev_weight,
      ]);
      $_SESSION["flash_success"] =
        "Canary promoted — now receiving full traffic.";
    }
    redirect("deployment/show/" . $id);
  }

  function delete(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $this->validation->set_rules("dummy", "dummy", "max_length[1]");
    if ($this->validation->run() !== true) {
      redirect("deployment/show/" . $id);
      return;
    }
    $snap = $this->model->get($id, (int) $customer->id);
    $this->model->delete($id, (int) $customer->id);
    $this->_emit("DeploymentDeleted", "deployment", $id, [
      "repo_url" => $snap ? $snap->repo_url : null,
      "server_id" => $snap ? (int) $snap->server_id : null,
    ]);
    $_SESSION["flash_success"] = "Deployment deleted.";
    redirect("deployment");
  }

  /**
   * Decrypts the deployment's env-vars and renders the bash deployment
   * script via the `scripts/deploy_script` view (returned as a string).
   */
  private function _render_deploy_script(object $d): string
  {
    $env_vars = [];
    if (!empty($d->env_variables_enc)) {
      $this->module("environment");
      $env_vars =
        $this->environment->model->decrypt_blob($d->env_variables_enc) ?: [];
    }

    return (string) $this->view(
      "scripts/deploy_script",
      [
        "view_module" => "deployment",
        "deployment" => $d,
        "env_vars" => $env_vars,
      ],
      true,
    );
  }

  private function _env_server_allowed(
    int $env_id,
    int $server_id,
    int $customer_id,
    string &$locked_name,
  ): bool {
    $rows = $this->db->query_bind(
      "SELECT d.server_id, s.name FROM deployment d
             JOIN server s ON s.id = d.server_id
             WHERE d.environment_id = :eid AND d.customer_id = :cid LIMIT 1",
      ["eid" => $env_id, "cid" => $customer_id],
      "object",
    );
    if (empty($rows)) {
      return true;
    }
    $locked_name = $rows[0]->name;
    return (int) $rows[0]->server_id === $server_id;
  }

  private function _store_zip(): string|false
  {
    if (
      empty($_FILES["zip_file"]["tmp_name"]) ||
      $_FILES["zip_file"]["error"] !== UPLOAD_ERR_OK
    ) {
      return false;
    }
    if (
      strtolower(pathinfo($_FILES["zip_file"]["name"], PATHINFO_EXTENSION)) !==
      "zip"
    ) {
      return false;
    }
    $hash = hash_file("sha256", $_FILES["zip_file"]["tmp_name"]);
    $dest = "/tmp/provision_deploy_" . $hash . ".zip";
    if (!move_uploaded_file($_FILES["zip_file"]["tmp_name"], $dest)) {
      return false;
    }
    return $dest;
  }

  private function _require_customer(): object
  {
    $this->module("customer");
    $this->customer->_require_onboarded();
    return $this->customer->_require_customer();
  }
}
