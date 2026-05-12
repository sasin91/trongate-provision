<?php

class Deployment extends Trongate
{
    function index(): void
    {
        $flash = [];
        foreach (['success', 'error'] as $type) {
            $key = 'flash_' . $type;
            if (!empty($_SESSION[$key])) {
                $flash[$type] = $_SESSION[$key];
                unset($_SESSION[$key]);
            }
        }

        $data = [
            'view_module' => 'deployment',
            'view_file'   => 'index',
            'flash'       => $flash,
        ];

        $this->module('templates');
        $this->templates->display($data);
    }

    function stream(): void
    {
        $s      = $this->_server();
        $script = $this->_render('deploy_script');

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $this->module('deployment-ssh');
        $exit = $this->ssh->execute_script(
            $s['host'], $s['user'], $s['port'], $script,
            function (string $line): void {
                echo 'data: ' . json_encode(['line' => $line]) . "\n\n";
                ob_flush();
                flush();
            },
            function (): void {
                echo ": ping\n\n";
                ob_flush();
                flush();
            }
        );

        echo 'data: ' . json_encode(['done' => true, 'exit' => $exit]) . "\n\n";
        ob_flush();
        flush();
    }

    function promote(): void
    {
        [$exit] = $this->_run('promote_release');
        $_SESSION[$exit === 0 ? 'flash_success' : 'flash_error'] =
            $exit === 0 ? 'Release promoted.' : 'Promotion failed — check server logs.';
        redirect('deployment');
    }

    function demote(): void
    {
        [$exit] = $this->_run('demote_release');
        $_SESSION[$exit === 0 ? 'flash_success' : 'flash_error'] =
            $exit === 0 ? 'Release rolled back.' : 'Rollback failed — check server logs.';
        redirect('deployment');
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function _server(): array
    {
        include __DIR__ . '/views/scripts/server.php';
        return compact(
            'host', 'user', 'port',
            'repo', 'branch',
            'webroot', 'releases_path', 'domain',
            'php_version',
            'db_name', 'db_user', 'db_pass',
            'env_vars'
        );
    }

    private function _render(string $name, array $extra = []): string
    {
        extract($this->_server());
        extract($extra);
        ob_start();
        include __DIR__ . '/views/scripts/' . $name . '.php';
        return ob_get_clean();
    }

    private function _run(string $script_name): array
    {
        $s      = $this->_server();
        $script = $this->_render($script_name);
        $log    = '';
        $this->module('deployment-ssh');
        $exit = $this->ssh->execute_script(
            $s['host'], $s['user'], $s['port'], $script,
            function (string $line) use (&$log): void { $log .= $line . "\n"; }
        );
        return [$exit, $log];
    }
}
