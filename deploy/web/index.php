<?php

declare(strict_types=1);

/**
 * GFM Deploy Hub — simple step UI (Git + ZIP). Local use only.
 *
 *   php -S 127.0.0.1:8765 -t deploy/web
 *
 * Copy config.example.php → config.local.php and set password.
 */

session_start();

$webRoot = __DIR__;
$projectRoot = dirname($webRoot, 2);
$configPath = $webRoot . DIRECTORY_SEPARATOR . 'config.local.php';
$configExample = $webRoot . DIRECTORY_SEPARATOR . 'config.example.php';

function deploy_ui_private_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (str_starts_with($ip, '10.')) {
            return true;
        }
        if (str_starts_with($ip, '192.168.')) {
            return true;
        }
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip) === 1) {
            return true;
        }
    }

    return false;
}

function deploy_ui_client_allowed(array $config): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }

    return ($config['allow_lan'] ?? false) === true && deploy_ui_private_ip($ip);
}

function deploy_ui_load_config(string $configPath, string $configExample): array
{
    if (!is_readable($configPath)) {
        return ['_missing' => true, 'example' => $configExample];
    }
    /** @var mixed $c */
    $c = require $configPath;

    return is_array($c) ? $c : [];
}

function deploy_ui_csrf_token(): string
{
    if (empty($_SESSION['_deploy_csrf'])) {
        $_SESSION['_deploy_csrf'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['_deploy_csrf'];
}

function deploy_ui_verify_csrf(string $token): bool
{
    return isset($_SESSION['_deploy_csrf']) && hash_equals($_SESSION['_deploy_csrf'], $token);
}

function deploy_console_add(string $title, string $body): void
{
    if (!isset($_SESSION['deploy_console']) || !is_array($_SESSION['deploy_console'])) {
        $_SESSION['deploy_console'] = [];
    }
    $_SESSION['deploy_console'][] = '>> ' . date('H:i:s') . ' ' . $title . "\n" . rtrim($body);
    $_SESSION['deploy_console'] = array_slice($_SESSION['deploy_console'], -25);
}

function deploy_console_text(): string
{
    $a = $_SESSION['deploy_console'] ?? [];

    return is_array($a) ? implode("\n\n", $a) : '';
}

/**
 * Run git with a fixed allowlist (no user-supplied git args).
 *
 * @param list<string> $args e.g. ['status','-sb']
 * @return array{0: int, 1: string}
 */
function deploy_ui_git(string $projectRoot, array $args): array
{
    $allowed = [
        'status -sb',
        'status --porcelain',
        'remote -v',
        'rev-parse --abbrev-ref HEAD',
        'rev-parse HEAD',
        'rev-parse --short HEAD',
        'log -1 --oneline',
        'pull --ff-only',
        'push',
        'fetch',
        'merge --abort',
    ];
    $key = implode(' ', $args);
    if (!in_array($key, $allowed, true)) {
        return [1, "Internal error: disallowed git command: {$key}\n"];
    }
    $cmd = ['git', '-C', $projectRoot, ...$args];
    $line = '';
    foreach ($cmd as $p) {
        $line .= ($line === '' ? '' : ' ') . escapeshellarg($p);
    }
    $out = [];
    $code = 0;
    exec($line . ' 2>&1', $out, $code);

    return [$code, implode("\n", $out) . "\n"];
}

function deploy_ui_merge_ref_safe(string $ref): bool
{
    $ref = trim($ref);
    if ($ref === '' || strlen($ref) > 200 || str_contains($ref, '..')) {
        return false;
    }

    return preg_match('/^[a-zA-Z0-9@^_./-]+$/', $ref) === 1;
}

function deploy_ui_merge_in_progress(string $projectRoot): bool
{
    $line = 'git -C ' . escapeshellarg($projectRoot) . ' rev-parse --verify MERGE_HEAD 2>&1';
    exec($line, $out, $code);

    return $code === 0;
}

/**
 * Merge ref into the current branch (--no-edit avoids opening an editor when merge succeeds).
 *
 * @return array{0: int, 1: string}
 */
function deploy_ui_git_merge(string $projectRoot, string $ref, bool $noFf): array
{
    if (!deploy_ui_merge_ref_safe($ref)) {
        return [1, "Invalid merge ref (use e.g. origin/main, develop, or a tag).\n"];
    }
    $cmd = ['git', '-C', $projectRoot, 'merge', '--no-edit'];
    if ($noFf) {
        $cmd[] = '--no-ff';
    }
    $cmd[] = $ref;
    $line = '';
    foreach ($cmd as $p) {
        $line .= ($line === '' ? '' : ' ') . escapeshellarg($p);
    }
    $out = [];
    $code = 0;
    exec($line . ' 2>&1', $out, $code);

    return [$code, implode("\n", $out) . "\n"];
}

/**
 * @return array{0: int, 1: string}
 */
function deploy_ui_git_merge_continue(string $projectRoot): array
{
    $des = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $env = [];
    foreach ($_ENV as $k => $v) {
        if (is_string($k) && (is_string($v) || is_int($v))) {
            $env[$k] = (string) $v;
        }
    }
    foreach (['PATH', 'SystemRoot', 'TEMP', 'TMP', 'HOME', 'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH'] as $k) {
        $v = getenv($k);
        if ($v !== false && $v !== '') {
            $env[$k] = $v;
        }
    }
    $env['GIT_EDITOR'] = 'true';
    $env['EDITOR'] = 'true';
    $proc = @proc_open(['git', '-C', $projectRoot, 'merge', '--continue'], $des, $pipes, $projectRoot, $env);
    if (!is_resource($proc)) {
        return [1, "Could not start git merge --continue.\n"];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $msg = (is_string($stdout) ? $stdout : '') . (is_string($stderr) && $stderr !== '' ? "\n" . $stderr : '');

    return [$code, $msg !== '' ? $msg . "\n" : "(no output)\n"];
}

/** Default GitHub remote for one-click setup (override in config.local.php). */
function deploy_ui_github_remote_default(): string
{
    return 'https://github.com/prasadmallesh/greenfield.git';
}

/**
 * @param array<string, mixed> $cfg
 */
function deploy_ui_resolve_github_remote(array $cfg): string
{
    $u = trim((string) ($cfg['github_remote_url'] ?? ''));
    if ($u !== '') {
        return preg_match('/\.git$/i', $u) === 1 ? $u : rtrim($u, '/') . '.git';
    }

    return deploy_ui_github_remote_default();
}

function deploy_ui_github_remote_valid(string $url): bool
{
    $u = trim($url);
    if ($u === '' || strlen($u) > 220) {
        return false;
    }

    return preg_match('#^https://github\.com/[a-z0-9_.-]+/[a-z0-9_.-]+(?:\.git)?/?$#i', $u) === 1
        || preg_match('#^git@github\.com:[a-z0-9_.-]+/[a-z0-9_.-]+(?:\.git)?$#i', $u) === 1;
}

/**
 * @param list<string> $argv arguments after "git -C root"
 * @return array{0: int, 1: string}
 */
function deploy_ui_git_exec_raw(string $projectRoot, array $argv): array
{
    $parts = array_merge(['git', '-C', $projectRoot], $argv);
    $line = '';
    foreach ($parts as $p) {
        $line .= ($line === '' ? '' : ' ') . escapeshellarg($p);
    }
    $out = [];
    $code = 0;
    exec($line . ' 2>&1', $out, $code);

    return [$code, implode("\n", $out) . "\n"];
}

/**
 * One-shot: init repo (if needed), set origin, rename branch to main, add all, commit if needed, push.
 *
 * @return array{0: bool, 1: string} success flag and full log
 */
function deploy_ui_github_bootstrap(string $projectRoot, string $remoteUrl, string $commitMessage): array
{
    $log = '';
    if (deploy_ui_merge_in_progress($projectRoot)) {
        return [false, "Aborted: a merge is in progress. Abort or continue the merge first.\n"];
    }
    if (!deploy_ui_github_remote_valid($remoteUrl)) {
        return [false, "Invalid GitHub remote URL.\n"];
    }
    if (!deploy_ui_commit_safe($commitMessage)) {
        return [false, "Invalid commit message.\n"];
    }

    if (!is_dir($projectRoot . DIRECTORY_SEPARATOR . '.git')) {
        [$c, $o] = deploy_ui_git_exec_raw($projectRoot, ['init']);
        $log .= ">> git init\n" . $o;
        if ($c !== 0) {
            return [false, $log];
        }
    }

    [$rc] = deploy_ui_git_exec_raw($projectRoot, ['remote', 'get-url', 'origin']);
    if ($rc !== 0) {
        [$c, $o] = deploy_ui_git_exec_raw($projectRoot, ['remote', 'add', 'origin', $remoteUrl]);
        $log .= ">> git remote add origin\n" . $o;
        if ($c !== 0) {
            return [false, $log];
        }
    } else {
        [$c, $o] = deploy_ui_git_exec_raw($projectRoot, ['remote', 'set-url', 'origin', $remoteUrl]);
        $log .= ">> git remote set-url origin\n" . $o;
        if ($c !== 0) {
            return [false, $log];
        }
    }

    [$c, $o] = deploy_ui_git_exec_raw($projectRoot, ['add', '-A']);
    $log .= ">> git add -A\n" . $o;
    if ($c !== 0) {
        return [false, $log];
    }

    [, $por] = deploy_ui_git_exec_raw($projectRoot, ['status', '--porcelain']);
    [$hc] = deploy_ui_git_exec_raw($projectRoot, ['rev-parse', 'HEAD']);
    $hasCommit = $hc === 0;

    if (trim($por) !== '') {
        [$c, $o] = deploy_ui_git_exec_raw($projectRoot, ['commit', '-m', $commitMessage]);
        $log .= ">> git commit -m …\n" . $o;
        if ($c !== 0) {
            return [false, $log];
        }
        $hasCommit = true;
    } elseif (!$hasCommit) {
        return [false, $log . ">> Stopped: nothing to commit (no files to push). Add your project files or adjust .gitignore, then try again.\n"];
    } else {
        $log .= ">> working tree already committed — skipping commit\n";
    }

    [$c, $o] = deploy_ui_git_exec_raw($projectRoot, ['branch', '-M', 'main']);
    $log .= ">> git branch -M main\n" . $o;
    if ($c !== 0) {
        return [false, $log];
    }

    [$c, $o] = deploy_ui_git_exec_raw($projectRoot, ['push', '-u', 'origin', 'main']);
    $log .= ">> git push -u origin main\n" . $o;
    if ($c !== 0) {
        return [false, $log];
    }

    return [true, $log];
}

function deploy_ui_git_repo(string $projectRoot): bool
{
    if (!is_dir($projectRoot . DIRECTORY_SEPARATOR . '.git')) {
        return false;
    }
    [$c] = deploy_ui_git($projectRoot, ['rev-parse', 'HEAD']);

    return $c === 0;
}

/**
 * @return array{branch: string, short: string, status_line: string, clean: bool, remote: string}
 */
function deploy_ui_git_banner(string $projectRoot): array
{
    $def = ['branch' => '?', 'short' => '?', 'status_line' => '(not a git repo)', 'clean' => true, 'remote' => '—', 'merging' => false];
    if (!deploy_ui_git_repo($projectRoot)) {
        return $def;
    }
    [, $br] = deploy_ui_git($projectRoot, ['rev-parse', '--abbrev-ref', 'HEAD']);
    [, $sh] = deploy_ui_git($projectRoot, ['rev-parse', '--short', 'HEAD']);
    [, $sb] = deploy_ui_git($projectRoot, ['status', '-sb']);
    [, $por] = deploy_ui_git($projectRoot, ['status', '--porcelain']);
    $branch = trim(explode("\n", $br)[0] ?? '?') ?: '?';
    $short = trim(explode("\n", $sh)[0] ?? '?') ?: '?';
    $statusLine = trim(explode("\n", $sb)[0] ?? '') ?: 'unknown';
    $clean = trim($por) === '';
    [, $rv] = deploy_ui_git($projectRoot, ['remote', '-v']);
    $remote = trim($rv) !== '' ? trim(explode("\n", $rv)[0] ?? '') : 'no remote';

    return [
        'branch' => $branch,
        'short' => $short,
        'status_line' => $statusLine,
        'clean' => $clean,
        'remote' => $remote,
        'merging' => deploy_ui_merge_in_progress($projectRoot),
    ];
}

function deploy_ui_run_packager(string $projectRoot, array $argvParts): array
{
    $build = $projectRoot . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'build.php';
    if (!is_readable($build)) {
        return [1, '', "deploy/build.php not found.\n"];
    }
    $cmd = array_merge([PHP_BINARY, $build], $argvParts);
    $des = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = @proc_open($cmd, $des, $pipes, $projectRoot);
    if (!is_resource($proc)) {
        return [1, '', "Could not start PHP process.\n"];
    }
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    return [$code, is_string($out) ? $out : '', is_string($err) ? $err : ''];
}

function deploy_ui_commit_safe(string $msg): bool
{
    $msg = str_replace(["\0", "\r"], '', $msg);
    if (strlen($msg) < 1 || strlen($msg) > 400) {
        return false;
    }

    return preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $msg) !== 1;
}

/**
 * Stage known app paths only (never .env or vendor).
 *
 * @return array{0: int, 1: string}
 */
function deploy_ui_git_stage_app(string $projectRoot): array
{
    $paths = ['src', 'templates', 'public', 'deploy', 'bootstrap.php', 'composer.json'];
    $lock = $projectRoot . DIRECTORY_SEPARATOR . 'composer.lock';
    if (is_file($lock)) {
        $paths[] = 'composer.lock';
    }
    $cmd = ['git', '-C', $projectRoot, 'add', '--', ...$paths];
    $line = '';
    foreach ($cmd as $p) {
        $line .= ($line === '' ? '' : ' ') . escapeshellarg($p);
    }
    $out = [];
    $code = 0;
    exec($line . ' 2>&1', $out, $code);

    return [$code, implode("\n", $out) . "\n"];
}

/**
 * @return array{0: int, 1: string}
 */
function deploy_ui_git_commit_m(string $projectRoot, string $message): array
{
    if (!deploy_ui_commit_safe($message)) {
        return [1, "Invalid commit message (length 1–400, single line).\n"];
    }
    $cmd = ['git', '-C', $projectRoot, 'commit', '-m', $message];
    $line = '';
    foreach ($cmd as $i => $p) {
        $line .= ($line === '' ? '' : ' ') . escapeshellarg($p);
    }
    $out = [];
    $code = 0;
    exec($line . ' 2>&1', $out, $code);

    return [$code, implode("\n", $out) . "\n"];
}

function deploy_ui_list_zips(string $projectRoot): array
{
    $dir = $projectRoot . DIRECTORY_SEPARATOR . 'dist';
    if (!is_dir($dir)) {
        return [];
    }
    $g = glob($dir . DIRECTORY_SEPARATOR . 'gfm-deploy-*.zip') ?: [];
    usort($g, static function (string $a, string $b): int {
        return filemtime($b) <=> filemtime($a);
    });

    return array_slice($g, 0, 15);
}

function deploy_ui_safe_zip_basename(string $name): bool
{
    return preg_match('/^gfm-deploy-[0-9]{8}-[0-9]{6}Z\.zip$/', $name) === 1;
}

// --- download ---
if (isset($_GET['download'])) {
    $cfg = deploy_ui_load_config($configPath, $configExample);
    if (isset($cfg['_missing']) || !deploy_ui_client_allowed($cfg)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    if (empty($_SESSION['_deploy_login'])) {
        http_response_code(403);
        echo 'Login required';
        exit;
    }
    $base = basename((string) $_GET['download']);
    if (!deploy_ui_safe_zip_basename($base)) {
        http_response_code(400);
        echo 'Invalid file name';
        exit;
    }
    $full = $projectRoot . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . $base;
    $realDist = realpath($projectRoot . DIRECTORY_SEPARATOR . 'dist');
    $realFile = realpath($full);
    if ($realDist === false || $realFile === false || !str_starts_with($realFile, $realDist)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $base . '"');
    header('Content-Length: ' . (string) filesize($realFile));
    readfile($realFile);
    exit;
}

if (isset($_GET['clearlog']) && !empty($_SESSION['_deploy_login'])) {
    $_SESSION['deploy_console'] = [];
    header('Location: ./');
    exit;
}

$cfg = deploy_ui_load_config($configPath, $configExample);
$missingConfig = isset($cfg['_missing']);

if (!$missingConfig && $cfg !== [] && !deploy_ui_client_allowed($cfg)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Deploy Hub</title></head><body style="font-family:sans-serif;padding:2rem;">'
        . '<p><strong>Access denied.</strong> Use <code>php -S 127.0.0.1:8765 -t deploy/web</code> or set <code>allow_lan</code> in config.local.php.</p></body></html>';
    exit;
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ./');
    exit;
}

$loginError = null;
if (!$missingConfig && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['login'])) {
    $pw = (string) ($_POST['password'] ?? '');
    $expected = (string) ($cfg['password'] ?? '');
    if ($expected !== '' && hash_equals($expected, $pw)) {
        $_SESSION['_deploy_login'] = true;
        header('Location: ./');
        exit;
    }
    $loginError = 'Invalid password.';
}

$loggedIn = !$missingConfig && !empty($_SESSION['_deploy_login']);
$banner = $loggedIn ? deploy_ui_git_banner($projectRoot) : null;
$isGit = $loggedIn && deploy_ui_git_repo($projectRoot);

$runError = null;
$output = '';
$exitCode = null;

if ($loggedIn && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['hub_action'])) {
    if (!deploy_ui_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
        $runError = 'Session expired — refresh and try again.';
    } else {
        $act = (string) $_POST['hub_action'];
        if ($act === 'pull') {
            [$c, $o] = deploy_ui_git($projectRoot, ['pull', '--ff-only']);
            deploy_console_add('git pull --ff-only', $o . ($c !== 0 ? "\n(exit {$c})" : ''));
        } elseif ($act === 'github_setup') {
            if (empty($_POST['github_setup_confirm'])) {
                $runError = 'Check the confirmation box to run automated GitHub setup.';
            } else {
                $url = trim((string) ($_POST['github_remote_url'] ?? ''));
                if ($url === '') {
                    $url = deploy_ui_resolve_github_remote($cfg);
                }
                if (!deploy_ui_github_remote_valid($url)) {
                    $runError = 'Remote must be a github.com HTTPS or git@github.com SSH URL.';
                } else {
                    $msg = trim((string) ($_POST['github_setup_msg'] ?? ''));
                    if ($msg === '') {
                        $msg = 'Initial commit from GFM Deploy Hub';
                    }
                    [$ok, $log] = deploy_ui_github_bootstrap($projectRoot, $url, $msg);
                    deploy_console_add('GitHub one-click setup', $log . ($ok ? "\nDone." : "\nStopped with errors."));
                    if (!$ok) {
                        $runError = 'Setup did not finish — see Output log. Fix auth (PAT/SSH) or errors, then retry.';
                    }
                }
            }
        } elseif ($act === 'fetch') {
            [$c, $o] = deploy_ui_git($projectRoot, ['fetch']);
            deploy_console_add('git fetch', $o . ($c !== 0 ? "\n(exit {$c})" : ''));
        } elseif ($act === 'merge') {
            $ref = trim((string) ($_POST['merge_ref'] ?? ''));
            if ($ref === '') {
                $runError = 'Enter a branch or ref to merge (e.g. origin/main).';
            } else {
                $noFf = !empty($_POST['merge_no_ff']);
                [$mc, $mo] = deploy_ui_git_merge($projectRoot, $ref, $noFf);
                $label = 'git merge --no-edit' . ($noFf ? ' --no-ff' : '') . ' ' . $ref;
                deploy_console_add($label, $mo . ($mc !== 0 ? "\n(exit {$mc})" : ''));
            }
        } elseif ($act === 'merge_abort') {
            [$c, $o] = deploy_ui_git($projectRoot, ['merge', '--abort']);
            deploy_console_add('git merge --abort', $o . ($c !== 0 ? "\n(exit {$c})" : ''));
        } elseif ($act === 'merge_continue') {
            [$c, $o] = deploy_ui_git_merge_continue($projectRoot);
            deploy_console_add('git merge --continue', $o . ($c !== 0 ? "\n(exit {$c})" : ''));
        } elseif ($act === 'commit') {
            $msg = trim((string) ($_POST['commit_msg'] ?? ''));
            [$ac, $ao] = deploy_ui_git_stage_app($projectRoot);
            deploy_console_add('git add (app paths)', $ao . ($ac !== 0 ? "\n(exit {$ac})" : ''));
            if ($ac === 0) {
                [$cc, $co] = deploy_ui_git_commit_m($projectRoot, $msg);
                deploy_console_add('git commit', $co . ($cc !== 0 ? "\n(exit {$cc})" : ''));
                if ($cc === 0 && !empty($_POST['commit_push'])) {
                    [$pc, $po] = deploy_ui_git($projectRoot, ['push']);
                    deploy_console_add('git push', $po . ($pc !== 0 ? "\n(exit {$pc})" : ''));
                }
            }
        } elseif ($act === 'pack') {
            $mode = (string) ($_POST['mode'] ?? 'full');
            $args = [];
            if ($mode === 'full') {
                $args[] = '--full';
                if (!empty($_POST['with_vendor'])) {
                    $args[] = '--with-vendor';
                }
            } elseif ($mode === 'delta') {
                $since = trim((string) ($_POST['since'] ?? ''));
                if ($since === '') {
                    $runError = 'Enter a ref for delta (e.g. origin/main).';
                } else {
                    $args[] = '--since=' . $since;
                }
            } elseif ($mode === 'delta_marker') {
                $refFile = $projectRoot . DIRECTORY_SEPARATOR . '.deploy' . DIRECTORY_SEPARATOR . 'last-ref';
                if (!is_readable($refFile)) {
                    $runError = 'No .deploy/last-ref yet — use “Mark deploy” in the packager once or run mark-only from CLI.';
                } else {
                    $ref = trim((string) file_get_contents($refFile));
                    if ($ref === '') {
                        $runError = '.deploy/last-ref is empty.';
                    } else {
                        $args[] = '--since=' . $ref;
                    }
                }
            } elseif ($mode === 'mark_only') {
                $args[] = '--mark-only';
            }

            if ($runError === null) {
                $dry = !empty($_POST['dry_run']);
                if ($dry && $mode !== 'mark_only') {
                    $args[] = '--dry-run';
                }
                if (!empty($_POST['mark']) && $mode !== 'mark_only') {
                    $args[] = '--mark';
                }
                if (!empty($_POST['git_push'])) {
                    $args[] = '--push';
                    $remote = trim((string) ($_POST['push_remote'] ?? 'origin'));
                    if ($remote !== '') {
                        $args[] = '--push-remote=' . $remote;
                    }
                    $pbr = trim((string) ($_POST['push_branch'] ?? ''));
                    if ($pbr !== '') {
                        $args[] = '--push-branch=' . $pbr;
                    }
                }
                if ($args !== []) {
                    [$exitCode, $stdout, $stderr] = deploy_ui_run_packager($projectRoot, $args);
                    $output = trim($stdout . ($stderr !== '' ? "\n--- stderr ---\n" . $stderr : ''));
                    deploy_console_add('deploy/build.php ' . implode(' ', $args), $output . "\n(exit " . (int) $exitCode . ')');
                }
            }
        }
    }
}

$csrf = deploy_ui_csrf_token();
$zips = $loggedIn ? deploy_ui_list_zips($projectRoot) : [];
$console = $loggedIn ? deploy_console_text() : '';
$githubRemoteDefault = ($loggedIn && !$missingConfig) ? deploy_ui_resolve_github_remote($cfg) : deploy_ui_github_remote_default();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GFM Deploy Hub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        :root { --gf:#0c6932; --sb:#1e2a24; --sb2:#152018; }
        body { background:#e8ebe8; min-height:100vh; }
        .sidebar { background:linear-gradient(180deg,var(--sb),var(--sb2)); color:#e8f0ea; min-height:100vh; width:220px; flex-shrink:0; }
        .sidebar .brand { color:#7dffb0; font-weight:700; letter-spacing:.02em; font-size:1rem; }
        .step { background:#fff; border-radius:6px; border:1px solid #d5ddd6; padding:1rem 1.1rem; margin-bottom:1rem; }
        .step h3 { font-size:.95rem; font-weight:600; margin:0 0 .35rem; color:#1a2c22; }
        .step p.desc { font-size:.8rem; color:#5a6b5f; margin:0 0 .75rem; }
        .badge-soft { background:#e3ece5; color:#1a4d2e; font-weight:500; }
        pre.console { background:#141a16; color:#c8e6d0; font-size:.8rem; padding:1rem; border-radius:4px; max-height:16rem; overflow:auto; white-space:pre-wrap; }
        .status-strip { background:#fff; border:1px solid #d5ddd6; border-radius:6px; padding:.6rem 1rem; font-size:.85rem; margin-bottom:1rem; }
    </style>
</head>
<body class="d-flex flex-column flex-md-row">
<?php if ($missingConfig): ?>
    <main class="flex-fill p-4">
        <p class="brand text-success font-weight-bold">GFM Deploy Hub</p>
        <div class="alert alert-warning mt-3" style="max-width:36rem;">
            Copy <code>config.example.php</code> to <code>config.local.php</code> and set <code>password</code>.
        </div>
    </main>
<?php elseif (!$loggedIn): ?>
    <main class="flex-fill p-4 d-flex align-items-center justify-content-center">
        <div class="card shadow-sm" style="max-width:22rem;width:100%;">
            <div class="card-body">
                <p class="text-success font-weight-bold mb-3">GFM Deploy Hub</p>
                <form method="post">
                    <input type="hidden" name="login" value="1">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success btn-block">Sign in</button>
                </form>
                <?php if ($loginError): ?><p class="text-danger small mt-2 mb-0"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            </div>
        </div>
    </main>
<?php else: ?>
    <aside class="sidebar p-3">
        <div class="brand mb-1">GFM Deploy Hub</div>
        <div class="small text-white-50 mb-4">Local Git + ZIP</div>
        <div class="small"><a href="?logout=1" class="text-light">Sign out</a></div>
    </aside>
    <main class="flex-fill p-3 p-md-4" style="min-width:0;">
        <h1 class="h5 text-dark mb-3">Update hub</h1>

        <?php if ($banner !== null): ?>
        <div class="status-strip d-flex flex-wrap align-items-center">
            <span class="mr-3"><strong>Branch</strong> <?= htmlspecialchars($banner['branch'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="mr-3"><strong>Commit</strong> <code><?= htmlspecialchars($banner['short'], ENT_QUOTES, 'UTF-8') ?></code></span>
            <span class="mr-3"><strong>Tree</strong>
                <?php if ($isGit): ?>
                    <span class="badge badge-<?= $banner['clean'] ? 'success' : 'warning' ?>"><?= $banner['clean'] ? 'clean' : 'has changes' ?></span>
                    <?php if (!empty($banner['merging'])): ?>
                        <span class="badge badge-danger ml-1">MERGING</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge badge-secondary">not git</span>
                <?php endif; ?>
            </span>
            <span class="text-truncate" style="max-width:28rem;" title="<?= htmlspecialchars($banner['remote'], ENT_QUOTES, 'UTF-8') ?>"><strong>Remote</strong> <?= htmlspecialchars($banner['remote'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>

        <?php if ($runError): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($runError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="step">
            <h3>1. Connect this folder to GitHub</h3>
            <p class="desc">Target repo: <a href="https://github.com/prasadmallesh/greenfield" target="_blank" rel="noopener">github.com/prasadmallesh/greenfield</a>. Use the button below instead of typing commands — you still need GitHub auth (HTTPS personal access token or SSH key) on this machine.</p>
            <form method="post" class="border rounded p-3 bg-light mb-3">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="github_setup">
                <div class="form-group mb-2">
                    <label class="small font-weight-bold" for="ghurl">Remote URL</label>
                    <input type="text" name="github_remote_url" id="ghurl" class="form-control form-control-sm" value="<?= htmlspecialchars($githubRemoteDefault, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://github.com/owner/repo.git or git@github.com:owner/repo.git" autocomplete="off">
                </div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold" for="ghmsg">Commit message (if a new commit is created)</label>
                    <input type="text" name="github_setup_msg" id="ghmsg" class="form-control form-control-sm" value="Initial commit from GFM Deploy Hub" maxlength="400">
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" name="github_setup_confirm" id="ghconf" value="1" required>
                    <label class="custom-control-label small" for="ghconf">Run <code>git init</code> (if needed), set <code>origin</code>, <code>git add -A</code>, commit if there are changes, rename branch to <code>main</code>, and <code>git push -u origin main</code></label>
                </div>
                <button type="submit" class="btn btn-success btn-sm">Connect &amp; push to GitHub</button>
            </form>
            <p class="desc mb-1">Manual fallback (same project root):</p>
            <pre class="small bg-white p-2 rounded mb-0" style="max-height:7rem;overflow:auto;">git init
git remote add origin <?= htmlspecialchars($githubRemoteDefault, ENT_QUOTES, 'UTF-8') ?>

git add -A &amp;&amp; git commit -m "Initial commit" &amp;&amp; git branch -M main &amp;&amp; git push -u origin main</pre>
        </div>

        <div class="step">
            <h3>2. Pull / fetch from GitHub</h3>
            <p class="desc"><strong>Pull</strong> runs <code>git pull --ff-only</code> (fast-forward only). <strong>Fetch</strong> updates remote refs without merging — use before merging <code>origin/…</code> branches.</p>
            <form method="post" class="d-inline mr-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="pull">
                <button type="submit" class="btn btn-outline-primary btn-sm" <?= $isGit ? '' : 'disabled title="Not a git repo"' ?>>Pull latest</button>
            </form>
            <form method="post" class="d-inline">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="fetch">
                <button type="submit" class="btn btn-outline-secondary btn-sm" <?= $isGit ? '' : 'disabled' ?>>Fetch remotes</button>
            </form>
        </div>

        <div class="step">
            <h3>3. Merge branches</h3>
            <p class="desc">Merges another branch or remote tracking branch <strong>into your current branch</strong> with <code>git merge --no-edit</code>. If there are conflicts, resolve them in your editor/terminal, then use <strong>Continue merge</strong> here (uses a non-interactive editor for the merge commit).</p>
            <form method="post" class="mb-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="merge">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-6 mb-2 mb-md-0">
                        <label class="small mb-0" for="merge_ref">Branch / ref to merge in</label>
                        <input type="text" name="merge_ref" id="merge_ref" class="form-control form-control-sm" placeholder="e.g. origin/main, develop" maxlength="200" <?= $isGit ? '' : 'disabled' ?>>
                    </div>
                    <div class="form-group col-md-3 mb-2 mb-md-0">
                        <div class="custom-control custom-checkbox mt-4 pt-1">
                            <input type="checkbox" class="custom-control-input" name="merge_no_ff" id="mnoff" value="1">
                            <label class="custom-control-label small" for="mnoff"><code>--no-ff</code></label>
                        </div>
                    </div>
                    <div class="form-group col-md-3 mb-0">
                        <button type="submit" class="btn btn-primary btn-sm btn-block" <?= $isGit ? '' : 'disabled' ?>>Merge</button>
                    </div>
                </div>
            </form>
            <p class="desc small mb-2">While a merge is in progress (conflicts or stopped mid-merge):</p>
            <div class="d-flex flex-wrap">
                <form method="post" class="mr-2 mb-2">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="hub_action" value="merge_continue">
                    <button type="submit" class="btn btn-outline-success btn-sm" <?= ($isGit && !empty($banner['merging'])) ? '' : 'disabled' ?>>Continue merge</button>
                </form>
                <form method="post" class="mb-2">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="hub_action" value="merge_abort">
                    <button type="submit" class="btn btn-outline-danger btn-sm" <?= ($isGit && !empty($banner['merging'])) ? '' : 'disabled' ?> onclick="return confirm('Abort this merge?');">Abort merge</button>
                </form>
            </div>
        </div>

        <div class="step">
            <h3>4. Commit &amp; push (app files only)</h3>
            <p class="desc">Stages: <code>src/</code>, <code>templates/</code>, <code>public/</code>, <code>deploy/</code>, <code>bootstrap.php</code>, Composer files — not <code>vendor/</code> or <code>.env</code>.</p>
            <form method="post" class="mb-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="commit">
                <div class="form-group mb-2">
                    <input type="text" name="commit_msg" class="form-control form-control-sm" placeholder="Commit message" required maxlength="400">
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" name="commit_push" id="cpush" value="1" checked>
                    <label class="custom-control-label small" for="cpush">Also <code>git push</code> after commit</label>
                </div>
                <button type="submit" class="btn btn-success btn-sm" <?= $isGit ? '' : 'disabled' ?>>Commit &amp; push</button>
            </form>
        </div>

        <div class="step">
            <h3>5. Build deploy ZIP</h3>
            <p class="desc">Same as <code>php deploy/build.php</code>. Upload the ZIP to your host, then <code>composer install --no-dev</code> if needed.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="pack">
                <div class="form-group mb-2">
                    <label class="small d-block">Package</label>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input class="custom-control-input" type="radio" name="mode" id="pk_full" value="full" checked>
                        <label class="custom-control-label small" for="pk_full">Full</label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input class="custom-control-input" type="radio" name="mode" id="pk_dm" value="delta_marker">
                        <label class="custom-control-label small" for="pk_dm">Delta since marker</label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input class="custom-control-input" type="radio" name="mode" id="pk_d" value="delta">
                        <label class="custom-control-label small" for="pk_d">Delta ref</label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input class="custom-control-input" type="radio" name="mode" id="pk_m" value="mark_only">
                        <label class="custom-control-label small" for="pk_m">Mark only</label>
                    </div>
                </div>
                <div class="form-group mb-2">
                    <input type="text" name="since" class="form-control form-control-sm" placeholder="Delta ref: origin/main">
                </div>
                <div class="custom-control custom-checkbox mb-1">
                    <input class="custom-control-input" type="checkbox" name="dry_run" id="pdr" value="1">
                    <label class="custom-control-label small" for="pdr">Dry run (no ZIP)</label>
                </div>
                <div class="custom-control custom-checkbox mb-1">
                    <input class="custom-control-input" type="checkbox" name="with_vendor" id="pwv" value="1">
                    <label class="custom-control-label small" for="pwv">Include vendor (full only)</label>
                </div>
                <div class="custom-control custom-checkbox mb-1">
                    <input class="custom-control-input" type="checkbox" name="mark" id="pmk" value="1">
                    <label class="custom-control-label small" for="pmk">Update <code>.deploy/last-ref</code> after ZIP</label>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input class="custom-control-input" type="checkbox" name="git_push" id="pgp" value="1">
                    <label class="custom-control-label small" for="pgp">Git push after pack</label>
                </div>
                <div class="form-row mb-2">
                    <div class="col-6"><input class="form-control form-control-sm" name="push_remote" placeholder="origin" value="origin"></div>
                    <div class="col-6"><input class="form-control form-control-sm" name="push_branch" placeholder="branch (optional)"></div>
                </div>
                <button type="submit" class="btn btn-dark btn-sm">Build package</button>
            </form>
            <?php if ($zips !== []): ?>
                <p class="small mt-3 mb-1"><strong>Downloads</strong></p>
                <ul class="small mb-0 pl-3">
                    <?php foreach ($zips as $z): $bn = basename($z); ?>
                        <li><a href="?download=<?= rawurlencode($bn) ?>"><?= htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="mt-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong class="small text-uppercase text-muted">Output</strong>
                <a href="?clearlog=1" class="small">Clear log</a>
            </div>
            <pre class="console mb-0"><?= $console !== '' ? htmlspecialchars($console, ENT_QUOTES, 'UTF-8') : htmlspecialchars('>> Actions will log here.', ENT_QUOTES, 'UTF-8') ?></pre>
        </div>

        <p class="small text-muted mt-3 mb-0">Run locally: <code>php -S 127.0.0.1:8765 -t deploy/web</code></p>
    </main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
