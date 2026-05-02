<?php
class Deployment_test extends Trongate {

    function provider_url(): void {
        // Test _get_provider_archive_url logic (mirrors the standalone test)
        // Direct URL access only in dev
        if (ENV !== 'dev') {
            http_response_code(403);
            die('Tests are only available in dev mode.');
        }
        header('Content-Type: text/plain');

        $cases = [
            ['https://github.com/acme/myapp.git',   'main',   'https://github.com/acme/myapp/archive/refs/heads/main.zip'],
            ['https://github.com/acme/myapp',        'v1.2',   'https://github.com/acme/myapp/archive/refs/heads/v1.2.zip'],
            ['git@github.com:acme/myapp.git',        'dev',    'https://github.com/acme/myapp/archive/refs/heads/dev.zip'],
            ['https://gitlab.com/acme/myapp.git',    'main',   'https://gitlab.com/acme/myapp/-/archive/main/myapp-main.zip'],
            ['https://bitbucket.org/acme/myapp.git', 'main',   false],
        ];

        $pass = 0; $fail = 0;
        foreach ($cases as [$url, $branch, $expected]) {
            $got = $this->_url($url, $branch);
            if ($got === $expected) {
                echo "PASS: {$url}\n"; $pass++;
            } else {
                echo "FAIL: {$url}\n  expected: " . var_export($expected, true) . "\n  got:      " . var_export($got, true) . "\n";
                $fail++;
            }
        }
        echo "\n{$pass} passed, {$fail} failed\n";
    }

    private function _url(string $repo_url, string $branch): string|false {
        $b = rawurlencode($branch);
        if (preg_match('#(?:https?://|git@)github\.com[:/](.+?)(?:\.git)?/?$#i', $repo_url, $m)) {
            return 'https://github.com/' . trim($m[1], '/') . '/archive/refs/heads/' . $b . '.zip';
        }
        if (preg_match('#(?:https?://|git@)gitlab\.com[:/](.+?)(?:\.git)?/?$#i', $repo_url, $m)) {
            $slug = trim($m[1], '/');
            return "https://gitlab.com/{$slug}/-/archive/{$b}/" . basename($slug) . "-{$b}.zip";
        }
        return false;
    }
}
