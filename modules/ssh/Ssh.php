<?php
class Ssh extends Trongate {

    /**
     * Execute a bash script on a remote server via SSH.
     *
     * @param callable      $on_line   Called with each complete output line (string).
     * @param callable|null $on_ping   Called on 15-second select timeout (SSE keepalive).
     * @return int  Process exit code (0 = success).
     */
    public function execute_script(
        string $ip,
        string $user,
        int $port,
        string $script,
        callable $on_line,
        ?callable $on_ping = null,
        int $timeout = 1800
    ): int {
        $cmd  = $this->_build_cmd($user, $ip, $port, $timeout);
        $proc = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($proc)) {
            ($on_line)("Failed to open SSH connection.");
            return 1;
        }

        fwrite($pipes[0], str_replace(["\r\n", "\r"], "\n", $script));
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $open = [1 => $pipes[1], 2 => $pipes[2]];
        $buf  = [1 => '',        2 => ''];

        while (!empty($open)) {
            $read = array_values($open);
            $w = $e = null;
            $n = stream_select($read, $w, $e, 15);
            if ($n === false) break;
            if ($n === 0) {
                if ($on_ping !== null) ($on_ping)();
                continue;
            }
            foreach ($read as $fh) {
                $chunk = fread($fh, 4096);
                if ($chunk !== false && $chunk !== '') {
                    $key = ($fh === $pipes[1]) ? 1 : 2;
                    $buf[$key] .= $chunk;
                    while (($nl = strpos($buf[$key], "\n")) !== false) {
                        $line = substr($buf[$key], 0, $nl);
                        $buf[$key] = substr($buf[$key], $nl + 1);
                        if ($line !== '') ($on_line)(rtrim($line, "\r"));
                    }
                }
                if (feof($fh)) {
                    $key = ($fh === $pipes[1]) ? 1 : 2;
                    if ($buf[$key] !== '') {
                        ($on_line)(rtrim($buf[$key], "\r\n"));
                        $buf[$key] = '';
                    }
                    $open = array_filter($open, static fn($p) => $p !== $fh);
                }
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($proc);
    }

    /**
     * SCP a local file to a remote path.
     *
     * @return array{success: bool, output: string}
     */
    public function scp_upload(
        string $local_path,
        string $ip,
        string $user,
        int $port,
        string $remote_path
    ): array {
        $cmd = "scp"
            . " -i "  . escapeshellarg(RUNNER_SSH_KEY)
            . " -o StrictHostKeyChecking=accept-new"
            . " -o UserKnownHostsFile=/dev/null"
            . " -o LogLevel=ERROR"
            . " -o BatchMode=yes"
            . " -o ConnectTimeout=15"
            . " -P "  . $port
            . " "     . escapeshellarg($local_path)
            . " "     . escapeshellarg("{$user}@{$ip}:{$remote_path}");
        exec($cmd . ' 2>&1', $out, $code);
        return ['success' => $code === 0, 'output' => implode("\n", $out)];
    }

    private function _build_cmd(string $user, string $ip, int $port, int $timeout): string {
        return "ssh"
            . " -i "  . escapeshellarg(RUNNER_SSH_KEY)
            . " -o StrictHostKeyChecking=accept-new"
            . " -o UserKnownHostsFile=/dev/null"
            . " -o LogLevel=ERROR"
            . " -o BatchMode=yes"
            . " -o ConnectTimeout=15"
            . " -o ServerAliveInterval=30"
            . " -o ServerAliveCountMax=3"
            . " -p "  . $port
            . " "     . escapeshellarg("{$user}@{$ip}")
            . " \"timeout {$timeout} bash -s\"";
    }
}
