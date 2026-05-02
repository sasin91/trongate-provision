<?php

require_once __DIR__ . "/../event/Emits_events.php";

class Deployment extends Trongate
{
  use Emits_events;

  private const RUNNING_STALE_SECONDS = 120;
  private const ZIP_RETENTION_SECONDS = 604800;

  function index(): void
  {
    $customer = $this->_require_customer();

    $data = [
      "view_module" => "deployment",
      "view_file" => "index",
      "page_title" => "Deployments",
      "current_email" => $customer->email,
      "deployments" => $this->model->all((int) $customer->id),
      "additional_includes_top" => ["deployment_module/css/deployment.css"],
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
          } elseif ($source_type === "git") {
            // Attempt to pull release zip from GitHub/GitLab.
            // On failure, zip_path stays null → remote falls back to git clone.
            $zip_path = $this->_fetch_zip_from_provider(
              (string) post("repo_url", true),
              (string) post("branch", true),
            ) ?: null;
          }

          $id = $this->model->create([
            "server_id" => $server_id,
            "environment_id" => $environment_id,
            "customer_id" => (int) $customer->id,
            "script_id" => null,
            "source_type" => $source_type,
            "repo_url" =>
              $source_type === "git" ? post("repo_url", true) : null,
            "branch" => $source_type === "git" ? post("branch", true) : null,
            "zip_path" => $zip_path,
            "status" => "script_ready",
          ]);

          $this->_emit("DeploymentCreated", "deployment", (int) $id, [
            "server_id" => $server_id,
            "environment_id" => $environment_id,
            "source_type" => $source_type,
            "status" => "script_ready",
          ]);
          $_SESSION["flash_success"] =
            "Deployment created. Staging will start automatically.";
          redirect("deployment/create/" . $id);
          return;
        }
      }
    }

    render_create:

    $wizard_id = (int) segment(3);
    $wizard_deployment = false;
    if ($wizard_id > 0 && $_SERVER["REQUEST_METHOD"] !== "POST") {
      $wizard_deployment = $this->model->get($wizard_id, (int) $customer->id);
      if ($wizard_deployment === false) {
        redirect("deployment/create");
        return;
      }
    }

    $preselected_server = (int) ($_GET["server"] ?? 0);
    $preselected_env = (int) ($_GET["env"] ?? 0);

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
      "deployment" => $wizard_deployment,
      "preselected_server" => $preselected_server,
      "preselected_env" => $preselected_env,
    ];

    $this->view("create", $data);
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
    $display_script = $this->_redact_deploy_script($script);

    $this->module("environment-services");
    $services = $this->services->model->by_environment(
      (int) $deployment->env_id,
      (int) $customer->id,
    );
    $this->module("server-health");
    $latest_health = $this->health->model->latest("deployment", $id);

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
      "deploy_script" => $display_script,
      "services" => $services,
      "latest_health" => $latest_health,
      "recent_events" => $recent_events,
      "additional_includes_top" => ["deployment_module/css/deployment.css"],
    ];

    $this->module("templates");
    $this->templates->customer($data);
  }

  function stream(): void
  {
    $id = (int) segment(3);
    $this->module("stream");
    $this->stream->start();
    $emit = function (string $line, string $event = ""): void {
      $this->stream->emit($line, $event);
    };

    $this->module("trongate_tokens");
    $token = $this->trongate_tokens->attempt_get_valid_token(
      Customer::CUSTOMER_LEVEL,
    );
    if ($token === false) {
      http_response_code(401);
      $emit("Unauthorized.");
      $this->stream->done(["status" => "failed"]);
      exit();
    }
    $this->module("customer");
    $customer = $this->customer->model->_get_current_customer();

    if ($customer === false) {
      http_response_code(401);
      $emit("Unauthorized.");
      $this->stream->done(["status" => "failed"]);
      exit();
    }

    $d = $this->model->get($id, (int) $customer->id);
    if ($d === false) {
      http_response_code(404);
      $emit("Deployment not found.");
      $this->stream->done(["status" => "failed"]);
      exit();
    }

    $is_onboarding_deployment = (int) ($customer->onboarding_server_id ?? 0) === (int) $d->server_id;
    if (empty($customer->onboarded_at) && !$is_onboarding_deployment) {
      http_response_code(401);
      $emit("Unauthorized for this onboarding deployment.");
      $this->stream->done(["status" => "failed"]);
      exit();
    }

    if (!defined("RUNNER_SSH_KEY") || !RUNNER_SSH_KEY) {
      http_response_code(503);
      $emit("RUNNER_SSH_KEY is not configured.");
      $this->stream->done(["status" => "failed"]);
      exit();
    }

    $this->stream->prepare_long_running();

    $user = $d->ssh_user ?: "root";
    $port = (int) ($d->ssh_port ?: 22);

    // Validate before touching DB state
    if (!filter_var($d->ip_address, FILTER_VALIDATE_IP)) {
      $emit("Invalid server IP address.");
      $this->stream->done(["status" => "failed", "sha" => null]);
      return;
    }
    if (!preg_match('/^[a-z_][a-z0-9_.-]{0,31}$/i', $user)) {
      $emit("Invalid SSH user.");
      $this->stream->done(["status" => "failed", "sha" => null]);
      return;
    }

    if ($d->status === "running") {
      $this->_wait_for_running_deployment($id, (int) $customer->id, $emit);
      return;
    }

    $this->model->mark_running($id);
    $this->_emit("DeploymentStarted", "deployment", $id, [
      "source" => "stream",
    ]);

    $script = $this->_render_deploy_script($d);

    // SCP zip if source is zip and file is present locally
    if (($d->source_type ?? "") === "zip" && empty($d->zip_path)) {
      $msg = "Deployment zip is missing from storage. Upload the zip again to retry.";
      $emit($msg);
      $this->stream->done(["status" => "missing_zip", "sha" => null]);
      $this->model->finish($id, "failed", $msg, null);
      return;
    }

    if (!empty($d->zip_path) && file_exists($d->zip_path)) {
      $remote_zip = "/tmp/provision_deploy_" . $id . "_" . time() . ".zip";
      $emit("Uploading application zip…");
      $scp =
        "scp" .
        " -i " .
        escapeshellarg(RUNNER_SSH_KEY) .
        " -o StrictHostKeyChecking=accept-new" .
        " -o UserKnownHostsFile=/dev/null" .
        " -o LogLevel=ERROR" .
        " -o BatchMode=yes" .
        " -o ConnectTimeout=15" .
        " -P " .
        $port .
        " " .
        escapeshellarg($d->zip_path) .
        " " .
        escapeshellarg("{$user}@{$d->ip_address}:{$remote_zip}");
      exec($scp . " 2>&1", $scp_out, $scp_code);
      if ($scp_code !== 0) {
        $msg = "SCP failed: " . implode("\n", $scp_out);
        $emit($msg);
        $this->stream->done(["status" => "failed", "sha" => null]);
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
    } elseif (($d->source_type ?? "") === "zip") {
      $msg = "Deployment zip was removed from storage. Upload the zip again to retry.";
      $emit($msg);
      $this->stream->done(["status" => "missing_zip", "sha" => null]);
      $this->model->finish($id, "failed", $msg, null);
      return;
    }

    $timeout = RUNNER_SCRIPT_TIMEOUT;
    $cmd =
      "ssh" .
      " -i " .
      escapeshellarg(RUNNER_SSH_KEY) .
      " -o StrictHostKeyChecking=accept-new" .
      " -o UserKnownHostsFile=/dev/null" .
      " -o LogLevel=ERROR" .
      " -o BatchMode=yes" .
      " -o ConnectTimeout=15" .
      " -o ServerAliveInterval=30" .
      " -o ServerAliveCountMax=3" .
      " -p " .
      $port .
      " " .
      escapeshellarg("{$user}@{$d->ip_address}") .
      " \"timeout {$timeout} bash -s\"";

    $proc = proc_open(
      $cmd,
      [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]],
      $pipes,
    );
    if (!is_resource($proc)) {
      $emit("Failed to open SSH connection.");
      $this->stream->done(["status" => "failed", "sha" => null]);
      $this->model->finish($id, "failed", "proc_open failed.", null);
      return;
    }

    fwrite($pipes[0], str_replace(["\r\n", "\r"], "\n", $script));
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
        $this->stream->ping();
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
    $release_path = null;
    if (preg_match_all("/RELEASE_PATH\s*:\s*(\S+)/i", $log, $m)) {
      $release_path = end($m[1]);
    }

    $status = $exit_code === 0 ? "staged" : "failed";
    try {
      $this->model->finish($id, $status, $log, $sha, $release_path);
    } catch (Throwable $e) {
      $message = "Deployment finished remotely, but Provision could not save the staged release metadata: " . $e->getMessage();
      $emit($message);
      try {
        $this->model->mark_stale_running_failed($id, (int) $customer->id, $message);
      } catch (Throwable) {
      }
      $this->stream->done(["status" => "failed", "sha" => $sha, "release_path" => $release_path]);
      return;
    }

    if ($status === "staged") {
      if (!empty($d->zip_path) && file_exists($d->zip_path)) {
        @unlink($d->zip_path);
      }
      $this->_emit("DeploymentSucceeded", "deployment", $id, [
        "sha" => $sha,
        "release_path" => $release_path,
        "source" => "stream",
      ]);
    } else {
      $this->_emit("DeploymentFailed", "deployment", $id, [
        "exit_code" => $exit_code,
        "source" => "stream",
      ]);
    }

    $this->stream->done(["status" => $status, "sha" => $sha, "release_path" => $release_path]);
  }

  private function _wait_for_running_deployment(int $id, int $customer_id, callable $emit): void
  {
    $stale_after = self::RUNNING_STALE_SECONDS;
    $current = $this->model->get($id, $customer_id);
    if ($current === false) {
      $emit("Deployment not found.");
      $this->stream->done(["status" => "failed", "sha" => null]);
      return;
    }

    if ($current->status !== "running") {
      $log = trim((string) ($current->run_log ?? ""));
      if ($log !== "") {
        $emit($this->_tail_text($log, 4000));
      }
      $this->stream->done([
        "status" => $current->status,
        "sha" => $current->deployed_sha ?? null,
        "release_path" => $current->release_path ?? null,
      ]);
      return;
    }

    $started_at = strtotime((string) ($current->started_at ?? ""));
    if ($started_at === false || time() - $started_at <= $stale_after) {
      $this->stream->emit(json_encode([
        "status" => "running",
        "message" => "Deployment is already running.",
      ]), "state");
      $this->stream->done(["status" => "running", "sha" => null]);
      return;
    }

    $message = "Deployment was left in running state for more than " . self::RUNNING_STALE_SECONDS . " seconds. It was marked failed so you can retry.";
    $this->model->mark_stale_running_failed($id, $customer_id, $message);
    $emit($message);
    $this->stream->done(["status" => "failed", "sha" => null]);
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

  function promote_release(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $this->validation->set_rules("dummy", "dummy", "max_length[1]");
    if ($this->validation->run() !== true) {
      redirect("deployment/show/" . $id);
      return;
    }
    $result = $this->_promote_release_result($id, (int) $customer->id);
    if (!$result["ok"]) {
      $_SESSION["flash_error"] = $result["message"];
      redirect("deployment/show/" . $id);
      return;
    }

    $summary = $result["health"];
    $_SESSION["flash_success"] =
      "Release promoted. Health checks: {$summary["healthy"]} healthy, {$summary["unhealthy"]} unhealthy, {$summary["unknown"]} unknown.";
    redirect("deployment/show/" . $id);
  }

  function scan_release_sql(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $d = $this->model->get($id, (int) $customer->id);
    if ($d === false || $d->status !== "staged" || empty($d->release_path)) {
      $this->_json_response(["ok" => false, "message" => "Only staged releases can be scanned for SQL files."], 422);
      return;
    }

    $script = $this->_render_release_script("scan_release_sql", $d);
    $result = $this->_run_remote_script($d, $script);
    if ($result["exit_code"] !== 0) {
      $this->_json_response([
        "ok" => false,
        "message" => "SQL scan failed: " . $this->_tail_text(trim($result["log"]), 800),
      ], 500);
      return;
    }

    $files = [];
    foreach (preg_split("/\r\n|\n|\r/", trim((string) $result["log"])) ?: [] as $line) {
      if (!str_starts_with($line, "SQL_FILE\t")) {
        continue;
      }
      $parts = explode("\t", $line, 3);
      if (count($parts) !== 3) {
        continue;
      }
      $path = base64_decode($parts[1], true);
      $sql = base64_decode($parts[2], true);
      if ($path === false || $sql === false) {
        continue;
      }
      $files[] = ["path" => $path, "sql" => $sql];
    }

    $this->_json_response(["ok" => true, "files" => $files]);
  }

  function delete_release_sql(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $d = $this->model->get($id, (int) $customer->id);
    if ($d === false || $d->status !== "staged" || empty($d->release_path)) {
      $this->_json_response(["ok" => false, "message" => "Only staged releases can have SQL files deleted."], 422);
      return;
    }

    $payload = $this->_json_request();
    $paths = is_array($payload["paths"] ?? null) ? $payload["paths"] : [];
    $clean_paths = [];
    foreach ($paths as $path) {
      if (!is_string($path)) {
        continue;
      }
      $path = str_replace("\\", "/", trim($path));
      if (
        $path === "" ||
        str_starts_with($path, "/") ||
        str_contains($path, "../") ||
        str_contains($path, "/..") ||
        $path === ".."
      ) {
        continue;
      }
      if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== "sql") {
        continue;
      }
      $clean_paths[] = $path;
    }

    if (empty($clean_paths)) {
      $this->_json_response(["ok" => false, "message" => "No valid SQL files were selected for deletion."], 422);
      return;
    }

    $script = $this->_render_release_script("delete_release_sql", (object) [
      "release_path" => $d->release_path,
      "sql_paths" => $clean_paths,
    ]);
    $result = $this->_run_remote_script($d, $script);
    if ($result["exit_code"] !== 0) {
      $this->_json_response([
        "ok" => false,
        "message" => "SQL deletion failed: " . $this->_tail_text(trim($result["log"]), 800),
      ], 500);
      return;
    }

    $deleted = 0;
    if (preg_match("/DELETED_SQL_COUNT\s*:\s*(\d+)/", $result["log"], $matches)) {
      $deleted = (int) $matches[1];
    }
    $this->_json_response(["ok" => true, "deleted" => $deleted]);
  }

  function promote_release_wizard(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $result = $this->_promote_release_result($id, (int) $customer->id);
    $this->_json_response($result, $result["ok"] ? 200 : 422);
  }

  function demote_release(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $this->validation->set_rules("dummy", "dummy", "max_length[1]");
    if ($this->validation->run() !== true) {
      redirect("deployment/show/" . $id);
      return;
    }
    $d = $this->model->get($id, (int) $customer->id);
    if ($d === false || $d->status !== "success" || empty($d->previous_release_path)) {
      $_SESSION["flash_error"] = "Only live deployments with a previous release can be demoted.";
      redirect("deployment/show/" . $id);
      return;
    }

    $script = $this->_render_release_script("demote_release", $d);
    $result = $this->_run_remote_script($d, $script);
    if ($result["exit_code"] !== 0) {
      $_SESSION["flash_error"] = "Demotion failed: " . $this->_tail_text(trim($result["log"]), 500);
      redirect("deployment/show/" . $id);
      return;
    }

    $this->model->demote_release($id, (int) $customer->id);
    $this->_emit("ReleaseDemoted", "deployment", $id, [
      "release_path" => $d->release_path,
      "previous_release_path" => $d->previous_release_path,
    ]);

    $summary = $this->_run_environment_health_checks($id, (int) $d->env_id, (int) $customer->id);
    $_SESSION["flash_success"] =
      "Release demoted. Health checks: {$summary["healthy"]} healthy, {$summary["unhealthy"]} unhealthy, {$summary["unknown"]} unknown.";
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
    if ($snap && !empty($snap->zip_path) && file_exists($snap->zip_path)) {
      @unlink($snap->zip_path);
    }
    $this->model->delete($id, (int) $customer->id);
    $this->_emit("DeploymentDeleted", "deployment", $id, [
      "repo_url" => $snap ? $snap->repo_url : null,
      "server_id" => $snap ? (int) $snap->server_id : null,
    ]);
    $_SESSION["flash_success"] = "Deployment deleted.";
    redirect("deployment");
  }

  private function _promote_release_result(int $id, int $customer_id): array
  {
    $d = $this->model->get($id, $customer_id);
    if ($d === false || $d->status !== "staged" || empty($d->release_path)) {
      if ($d !== false && $d->status === "staged") {
        $recovered_path = $this->_release_path_from_log((string) ($d->run_log ?? ""));
        if ($recovered_path !== null) {
          $this->model->set_release_path($id, $recovered_path);
          $d->release_path = $recovered_path;
        }
      }
      if ($d === false || $d->status !== "staged" || empty($d->release_path)) {
        return [
          "ok" => false,
          "message" => "Only staged releases with a saved release path can be promoted.",
        ];
      }
    }

    $script = $this->_render_release_script("promote_release", $d);
    $result = $this->_run_remote_script($d, $script);
    if ($result["exit_code"] !== 0) {
      return [
        "ok" => false,
        "message" => "Promotion failed: " . $this->_tail_text(trim($result["log"]), 800),
      ];
    }

    $previous_release_path = null;
    if (preg_match("/PREVIOUS_RELEASE_PATH\s*:\s*(.*)$/mi", $result["log"], $m)) {
      $previous_release_path = trim($m[1]) ?: null;
    }
    $this->model->promote_release($id, $customer_id, $previous_release_path);
    $this->_emit("ReleasePromoted", "deployment", $id, [
      "release_path" => $d->release_path,
      "previous_release_path" => $previous_release_path,
    ]);

    $health = $this->_run_environment_health_checks($id, (int) $d->env_id, $customer_id);

    return [
      "ok" => true,
      "message" => "Release promoted.",
      "release_path" => $d->release_path,
      "previous_release_path" => $previous_release_path,
      "health" => $health,
    ];
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

  private function _render_release_script(string $script, object $d): string
  {
    return (string) $this->view(
      "scripts/" . $script,
      [
        "view_module" => "deployment",
        "deployment" => $d,
      ],
      true,
    );
  }

  private function _run_remote_script(object $d, string $script): array
  {
    if (!defined("RUNNER_SSH_KEY") || !RUNNER_SSH_KEY) {
      return ["exit_code" => 1, "log" => "RUNNER_SSH_KEY is not configured."];
    }

    $user = $d->ssh_user ?: "root";
    $port = (int) ($d->ssh_port ?: 22);
    if (!filter_var($d->ip_address, FILTER_VALIDATE_IP)) {
      return ["exit_code" => 1, "log" => "Invalid server IP address."];
    }
    if (!preg_match('/^[a-z_][a-z0-9_.-]{0,31}$/i', $user)) {
      return ["exit_code" => 1, "log" => "Invalid SSH user."];
    }

    $timeout = RUNNER_SCRIPT_TIMEOUT;
    $cmd =
      "ssh" .
      " -i " .
      escapeshellarg(RUNNER_SSH_KEY) .
      " -o StrictHostKeyChecking=accept-new" .
      " -o UserKnownHostsFile=/dev/null" .
      " -o LogLevel=ERROR" .
      " -o BatchMode=yes" .
      " -o ConnectTimeout=15" .
      " -p " .
      $port .
      " " .
      escapeshellarg("{$user}@{$d->ip_address}") .
      " \"timeout {$timeout} bash -s\"";

    $proc = proc_open(
      $cmd,
      [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]],
      $pipes,
    );
    if (!is_resource($proc)) {
      return ["exit_code" => 1, "log" => "Failed to open SSH connection."];
    }

    fwrite($pipes[0], str_replace(["\r\n", "\r"], "\n", $script));
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($proc);

    return [
      "exit_code" => $exit_code,
      "log" => trim((string) $stdout . "\n" . (string) $stderr),
    ];
  }

  private function _run_environment_health_checks(int $deployment_id, int $env_id, int $customer_id): array
  {
    $summary = ["healthy" => 0, "unhealthy" => 0, "unknown" => 0];
    $record = static function (?array $result) use (&$summary): void {
      $status = $result["status"] ?? "unknown";
      if (!isset($summary[$status])) {
        $status = "unknown";
      }
      $summary[$status]++;
    };

    $this->module("server-health");
    $record($this->health->check_deployment_result($deployment_id, $customer_id));

    $this->module("environment-services");
    $services = $this->services->model->by_environment($env_id, $customer_id);
    foreach ($services as $service) {
      $record($this->health->check_service_result((int) $service->id, $customer_id));
    }

    return $summary;
  }

  /**
   * Removes secret values from the script preview shown in the browser.
   * The stream deploy path renders a fresh unredacted script server-side.
   */
  private function _redact_deploy_script(string $script): string
  {
    $lines = preg_split("/\r\n|\n|\r/", $script);
    if ($lines === false) {
      return $script;
    }

    $redacted = [];
    foreach ($lines as $line) {
      if (preg_match('/^export\s+([A-Z0-9_]+)=/', $line, $matches) === 1) {
        $key = $matches[1];
        if ($this->_is_sensitive_script_key($key)) {
          $redacted[] = "export {$key}='[redacted]'";
          continue;
        }
      }

      $line = preg_replace(
        "/^(\\s*'(?:user|password)'\\s*=>\\s*)'[^']*'(,?\\s*)$/",
        "$1'[redacted]'$2",
        $line,
      );
      $redacted[] = $line ?? "";
    }

    return implode("\n", $redacted);
  }

  private function _is_sensitive_script_key(string $key): bool
  {
    if ($key === "DB_USER") {
      return true;
    }

    return preg_match(
      '/(?:PASSWORD|PASS|SECRET|TOKEN|PRIVATE_KEY|API_KEY|ACCESS_KEY)/',
      $key,
    ) === 1;
  }

  private function _tail_text(string $message, int $length): string
  {
    return strlen($message) > $length ? "..." . substr($message, -$length) : $message;
  }

  private function _json_request(): array
  {
    $raw = file_get_contents("php://input");
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : [];
  }

  private function _json_response(array $payload, int $status = 200): void
  {
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode($payload);
  }

  private function _release_path_from_log(string $log): ?string
  {
    if (preg_match_all("/RELEASE_PATH\s*:\s*(\S+)/i", $log, $matches)) {
      $path = trim((string) end($matches[1]));
      return $path !== "" ? $path : null;
    }
    return null;
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
    $this->_prune_zip_storage();

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
    if ($hash === false) {
      return false;
    }
    $dir = $this->_zip_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
      return false;
    }
    $deny = $dir . DIRECTORY_SEPARATOR . ".htaccess";
    if (!file_exists($deny)) {
      @file_put_contents($deny, "Require all denied\nDeny from all\n");
    }
    $dest = $dir . DIRECTORY_SEPARATOR . "provision_deploy_" . $hash . ".zip";
    if (!move_uploaded_file($_FILES["zip_file"]["tmp_name"], $dest)) {
      return false;
    }
    return $dest;
  }

  private function _zip_storage_dir(): string
  {
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "deploy_zips";
  }

  private function _prune_zip_storage(): void
  {
    $dir = $this->_zip_storage_dir();
    if (!is_dir($dir)) {
      return;
    }

    $keep = [];
    $rows = $this->db->query_bind(
      "SELECT zip_path FROM deployment WHERE zip_path IS NOT NULL AND status IN ('script_ready','running','failed')",
      [],
      "object",
    );
    foreach ($rows as $row) {
      $keep[(string) $row->zip_path] = true;
    }

    foreach (glob($dir . DIRECTORY_SEPARATOR . "*.zip") ?: [] as $file) {
      if (isset($keep[$file])) {
        continue;
      }
      $mtime = filemtime($file);
      if ($mtime !== false && time() - $mtime > self::ZIP_RETENTION_SECONDS) {
        @unlink($file);
      }
    }
  }

  private function _require_customer(): object
  {
    $this->module("customer");
    $this->customer->_require_onboarded();
    return $this->customer->_require_customer();
  }

  private function _get_provider_archive_url(string $repo_url, string $branch): string|false
  {
    $b = rawurlencode($branch);
    if (preg_match('#(?:https?://|git@)github\.com[:/](.+?)(?:\.git)?/?$#i', $repo_url, $m)) {
      return 'https://github.com/' . trim($m[1], '/') . '/archive/refs/heads/' . $b . '.zip';
    }
    if (preg_match('#(?:https?://|git@)gitlab\.com[:/](.+?)(?:\.git)?/?$#i', $repo_url, $m)) {
      $slug = trim($m[1], '/');
      $name = basename($slug);
      return "https://gitlab.com/{$slug}/-/archive/{$b}/{$name}-{$b}.zip";
    }
    return false;
  }

  private function _fetch_zip_from_provider(string $repo_url, string $branch): string|false
  {
    $archive_url = $this->_get_provider_archive_url($repo_url, $branch);
    if ($archive_url === false) {
      return false;
    }

    $ch = curl_init($archive_url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS      => 5,
      CURLOPT_TIMEOUT        => 60,
      CURLOPT_USERAGENT      => 'Provision-Deploy/1.0',
      CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $data      = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($data === false || $http_code !== 200 || strlen($data) < 100) {
      return false;
    }

    $hash = hash('sha256', $data);
    $dir  = $this->_zip_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
      return false;
    }
    $deny = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($deny)) {
      @file_put_contents($deny, "Require all denied\nDeny from all\n");
    }
    $dest = $dir . DIRECTORY_SEPARATOR . 'provision_deploy_' . $hash . '.zip';
    return file_put_contents($dest, $data) !== false ? $dest : false;
  }
}
