<?php
// Standalone test — no framework boot needed.
// Tests the URL-building logic extracted to a standalone function.
// Usage: php tests/test_provider_url.php

function get_provider_archive_url(string $repo_url, string $branch): string|false {
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

$cases = [
    ['https://github.com/acme/myapp.git',   'main',    'https://github.com/acme/myapp/archive/refs/heads/main.zip'],
    ['https://github.com/acme/myapp',        'v1.2',   'https://github.com/acme/myapp/archive/refs/heads/v1.2.zip'],
    ['git@github.com:acme/myapp.git',        'dev',    'https://github.com/acme/myapp/archive/refs/heads/dev.zip'],
    ['https://gitlab.com/acme/myapp.git',    'main',   'https://gitlab.com/acme/myapp/-/archive/main/myapp-main.zip'],
    ['https://bitbucket.org/acme/myapp.git', 'main',   false],
];

$pass = 0; $fail = 0;
foreach ($cases as [$url, $branch, $expected]) {
    $got = get_provider_archive_url($url, $branch);
    if ($got === $expected) {
        echo "PASS: {$url}\n"; $pass++;
    } else {
        echo "FAIL: {$url}\n  expected: " . var_export($expected, true) . "\n  got:      " . var_export($got, true) . "\n";
        $fail++;
    }
}
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
