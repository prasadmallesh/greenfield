<?php

declare(strict_types=1);

/**
 * GFM Deploy Hub — Git, ZIP packager, and gated FTP upload. Local use only.
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

/** Hub wizard: step 1 = successful pull/fetch this session; cleared downstream when step 1 runs again. */
function deploy_hub_wizard_step1_ok(): bool
{
    return !empty($_SESSION['deploy_hub_step1']);
}

function deploy_hub_wizard_step2_ok(): bool
{
    return !empty($_SESSION['deploy_hub_step2']);
}

function deploy_hub_wizard_mark_pull_fetch_ok(): void
{
    $_SESSION['deploy_hub_step1'] = true;
    unset($_SESSION['deploy_hub_step2']);
}

function deploy_hub_wizard_mark_merge_ok(): void
{
    $_SESSION['deploy_hub_step2'] = true;
}

function deploy_hub_wizard_clear_merge_ok(): void
{
    unset($_SESSION['deploy_hub_step2']);
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

    return preg_match('#^[a-zA-Z0-9@^_./-]+$#', $ref) === 1;
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
 * Git must be clean and match upstream before FTP — runs git fetch for an accurate compare.
 *
 * @return array{ok: bool, lines: list<string>}
 */
function deploy_ui_git_deploy_gate(string $projectRoot): array
{
    $lines = [];
    if (!deploy_ui_git_repo($projectRoot)) {
        $lines[] = 'Not a git repository (or no commits yet).';

        return ['ok' => false, 'lines' => $lines];
    }
    if (deploy_ui_merge_in_progress($projectRoot)) {
        $lines[] = 'A merge is in progress — finish or abort it first.';

        return ['ok' => false, 'lines' => $lines];
    }
    [, $por] = deploy_ui_git($projectRoot, ['status', '--porcelain']);
    if (trim($por) !== '') {
        $lines[] = 'Working tree is not clean — commit or stash all changes before uploading to the server.';

        return ['ok' => false, 'lines' => $lines];
    }

    [$fc, $fo] = deploy_ui_git($projectRoot, ['fetch']);
    if ($fc !== 0) {
        $lines[] = 'git fetch failed — cannot verify you are in sync with GitHub. Fix network/auth, then try again.';
        $lines[] = trim($fo);

        return ['ok' => false, 'lines' => $lines];
    }

    [$uc, $uo] = deploy_ui_git_exec_raw($projectRoot, ['rev-parse', '--verify', '@{upstream}']);
    if ($uc !== 0) {
        $lines[] = 'Your project folder is not linked to your GitHub/GitLab copy for syncing.';
        $lines[] = 'Link it once from the command line (your developer can run e.g. git push -u origin main), then refresh this page.';

        return ['ok' => false, 'lines' => $lines];
    }

    [$cc, $co] = deploy_ui_git_exec_raw($projectRoot, ['rev-list', '--left-right', '--count', 'HEAD...@{upstream}']);
    if ($cc !== 0) {
        $lines[] = 'Could not compare HEAD to upstream.';
        $lines[] = trim($co);

        return ['ok' => false, 'lines' => $lines];
    }
    $parts = preg_split('/\s+/', trim($co));
    $ahead = (int) ($parts[0] ?? 0);
    $behind = (int) ($parts[1] ?? 0);
    if ($ahead > 0) {
        $lines[] = "Your computer has {$ahead} saved change(s) not yet on GitHub — push first, then upload.";
    }
    if ($behind > 0) {
        $lines[] = "GitHub has {$behind} newer save(s) — click Pull latest so your computer matches, then upload.";
    }
    if ($ahead > 0 || $behind > 0) {
        return ['ok' => false, 'lines' => $lines];
    }

    $lines[] = 'Safe to upload: no unsaved files, online copy checked, and your computer matches GitHub.';

    return ['ok' => true, 'lines' => $lines];
}

/**
 * @param array<string, mixed> $cfg
 */
function deploy_ui_ftp_configured(array $cfg): bool
{
    $h = trim((string) ($cfg['ftp_host'] ?? ''));
    $u = trim((string) ($cfg['ftp_user'] ?? ''));
    $d = trim((string) ($cfg['ftp_remote_dir'] ?? ''));

    return $h !== '' && $u !== '' && $d !== '';
}

/**
 * @param array<string, mixed> $cfg
 * @return array{0: bool, 1: string}
 */
function deploy_ui_ftp_upload_latest_zip(string $projectRoot, array $cfg): array
{
    if (!function_exists('ftp_connect')) {
        return [false, "PHP FTP extension is not enabled (enable ext-ftp).\n"];
    }
    if (!deploy_ui_ftp_configured($cfg)) {
        return [false, "FTP is not configured in config.local.php (ftp_host, ftp_user, ftp_password, ftp_remote_dir).\n"];
    }
    $gate = deploy_ui_git_deploy_gate($projectRoot);
    if (!$gate['ok']) {
        return [false, "Blocked: Git is not in sync.\n" . implode("\n", $gate['lines']) . "\n"];
    }
    $zips = deploy_ui_list_zips($projectRoot);
    if ($zips === []) {
        return [false, "No deploy ZIP found in dist/. Build a package first (step 5).\n"];
    }
    $localZip = $zips[0];
    $basename = basename($localZip);
    if (!deploy_ui_safe_zip_basename($basename)) {
        return [false, "Invalid ZIP name.\n"];
    }

    $host = trim((string) $cfg['ftp_host']);
    $port = (int) ($cfg['ftp_port'] ?? 21);
    $user = trim((string) $cfg['ftp_user']);
    $pass = (string) $cfg['ftp_password'];
    $remoteDir = trim((string) $cfg['ftp_remote_dir'], " /\r\n\t");
    $passive = ($cfg['ftp_passive'] ?? true) !== false;

    $conn = @ftp_connect($host, $port > 0 ? $port : 21, 30);
    if ($conn === false) {
        return [false, "Could not connect to FTP host {$host}:{$port}\n"];
    }
    if (!@ftp_login($conn, $user, $pass)) {
        ftp_close($conn);

        return [false, "FTP login failed.\n"];
    }
    if ($passive) {
        @ftp_pasv($conn, true);
    }
    if (!@ftp_chdir($conn, $remoteDir)) {
        ftp_close($conn);

        return [false, "Could not cd to remote directory: {$remoteDir}\n"];
    }
    $ok = @ftp_put($conn, $basename, $localZip, FTP_BINARY);
    ftp_close($conn);
    if (!$ok) {
        return [false, "FTP put failed for {$basename}\n"];
    }

    return [true, "Uploaded {$basename} to /{$remoteDir}/{$basename}\n"];
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
            if ($c === 0) {
                deploy_hub_wizard_mark_pull_fetch_ok();
            }
            deploy_console_add('git pull --ff-only', $o . ($c !== 0 ? "\n(exit {$c})" : ''));
        } elseif ($act === 'fetch') {
            [$c, $o] = deploy_ui_git($projectRoot, ['fetch']);
            if ($c === 0) {
                deploy_hub_wizard_mark_pull_fetch_ok();
            }
            deploy_console_add('git fetch', $o . ($c !== 0 ? "\n(exit {$c})" : ''));
        } elseif ($act === 'merge') {
            $ref = trim((string) ($_POST['merge_ref'] ?? ''));
            if ($ref === '') {
                $runError = 'Merge needs a target name (advanced — use only if you know Git branches).';
            } elseif (!deploy_hub_wizard_step1_ok()) {
                $runError = 'Run Pull or Fetch in step 1 successfully before merging.';
            } else {
                $noFf = !empty($_POST['merge_no_ff']);
                [$mc, $mo] = deploy_ui_git_merge($projectRoot, $ref, $noFf);
                $label = 'git merge --no-edit' . ($noFf ? ' --no-ff' : '') . ' ' . $ref;
                deploy_console_add($label, $mo . ($mc !== 0 ? "\n(exit {$mc})" : ''));
                if ($mc === 0) {
                    deploy_hub_wizard_mark_merge_ok();
                } else {
                    deploy_hub_wizard_clear_merge_ok();
                }
            }
        } elseif ($act === 'merge_abort') {
            [$c, $o] = deploy_ui_git($projectRoot, ['merge', '--abort']);
            if ($c === 0) {
                deploy_hub_wizard_clear_merge_ok();
            }
            deploy_console_add('git merge --abort', $o . ($c !== 0 ? "\n(exit {$c})" : ''));
        } elseif ($act === 'merge_continue') {
            [$c, $o] = deploy_ui_git_merge_continue($projectRoot);
            if ($c === 0) {
                deploy_hub_wizard_mark_merge_ok();
            }
            deploy_console_add('git merge --continue', $o . ($c !== 0 ? "\n(exit {$c})" : ''));
        } elseif ($act === 'commit') {
            if (!deploy_hub_wizard_step1_ok()) {
                $runError = 'Use Pull latest or Fetch first (step 1), then save & push.';
            } else {
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
                    $runError = 'Enter a snapshot name for this delta build (advanced).';
                } else {
                    $args[] = '--since=' . $since;
                }
            } elseif ($mode === 'delta_marker') {
                $refFile = $projectRoot . DIRECTORY_SEPARATOR . '.deploy' . DIRECTORY_SEPARATOR . 'last-ref';
                if (!is_readable($refFile)) {
                    $runError = 'No deploy marker yet (.deploy/last-ref is missing). A full ZIP yesterday does not create that file unless you checked “After a successful ZIP, remember this deploy”. Fix: (1) Choose “Remember this deploy only (no ZIP)” and Build once — that saves today’s commit as the baseline for future deltas; or (2) use “Smaller ZIP vs a specific snapshot” and type a Git ref (e.g. a commit hash from before your changes); or (3) run another Full ZIP with “remember this deploy” checked after it succeeds.';
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
        } elseif ($act === 'ftp_upload') {
            [$fok, $flog] = deploy_ui_ftp_upload_latest_zip($projectRoot, $cfg);
            deploy_console_add('FTP upload', $flog);
            if (!$fok) {
                $runError = 'FTP upload blocked or failed — see Output log.';
            }
        }
    }
}

$hubWizardStep1 = deploy_hub_wizard_step1_ok();

$csrf = deploy_ui_csrf_token();
$zips = $loggedIn ? deploy_ui_list_zips($projectRoot) : [];
$console = $loggedIn ? deploy_console_text() : '';
$deployGate = ['ok' => false, 'lines' => ['Sign in to see deploy status.']];
$ftpConfigured = false;
if ($loggedIn && !$missingConfig) {
    $ftpConfigured = deploy_ui_ftp_configured($cfg);
    if ($isGit) {
        $deployGate = deploy_ui_git_deploy_gate($projectRoot);
    } else {
        $deployGate = ['ok' => false, 'lines' => ['Not a git repository — connect this folder to git first. FTP upload is disabled.']];
    }
}

$deployLastRefPath = $projectRoot . DIRECTORY_SEPARATOR . '.deploy' . DIRECTORY_SEPARATOR . 'last-ref';
$deployLastRefOk = $loggedIn && is_readable($deployLastRefPath) && trim((string) @file_get_contents($deployLastRefPath)) !== '';

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
        pre.console { background:#141a16; color:#c8e6d0; font-size:.8rem; padding:1rem; border-radius:4px; overflow:auto; white-space:pre-wrap; min-height: 50vh; max-height: calc(100vh - 140px); }
        .status-strip { background:#fff; border:1px solid #d5ddd6; border-radius:6px; padding:.6rem 1rem; font-size:.85rem; margin-bottom:1rem; }
        .hub-controls-col { max-height: calc(100vh - 100px); overflow-y: auto; padding-right: .25rem; }
        .hub-output-col { position: sticky; top: .75rem; align-self: flex-start; }
        .hub-output-card { background:#fff; border:1px solid #d5ddd6; border-radius:6px; overflow:hidden; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
        .hub-output-card .card-head { background:#1a2420; color:#b8e6c8; padding:.5rem 1rem; font-size:.75rem; font-weight:600; letter-spacing:.04em; }
        @media (max-width: 767px) {
            pre.console { min-height: 35vh; max-height: 40vh; }
            .hub-controls-col { max-height: none; overflow-y: visible; }
            .hub-output-col { position: relative; top: 0; margin-top: 1rem; }
        }
        .pack-section-title { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:#5a6b5f; font-weight:600; margin:0 0 .5rem; }
        .pack-mode-list .custom-control { padding-left:1.65rem; margin-bottom:.65rem; }
        .pack-mode-list .custom-control:last-child { margin-bottom:0; }
        .pack-mode-list label { cursor:pointer; }
        .pack-mode-line { display:block; font-weight:600; font-size:.82rem; color:#1a2c22; }
        .pack-mode-hint { display:block; font-weight:400; font-size:.75rem; color:#6a7a70; margin-top:.1rem; line-height:1.35; }
        .pack-subbox { background:#f4f7f5; border:1px solid #dee5df; border-radius:6px; padding:.65rem .85rem; margin-bottom:.85rem; }
        .pack-push-row label { font-size:.72rem; color:#5a6b5f; margin-bottom:.15rem; }
        .sidebar-nav { border-top: 1px solid rgba(255,255,255,.14); padding-top: .75rem; margin-top: .35rem; margin-bottom: .75rem; }
        .sidebar-jump { display: block; color: #c8e6d0; padding: .4rem .35rem; font-size: .875rem; text-decoration: none; border-radius: 4px; line-height: 1.3; }
        .sidebar-jump:hover { color: #fff; background: rgba(255,255,255,.07); text-decoration: none; }
        #hub-git, #hub-zip, #hub-ftp { scroll-margin-top: 12px; }
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
        <div class="small text-white-50 mb-3">Git · ZIP · FTP</div>
        <nav class="sidebar-nav" aria-label="Jump to section">
            <a href="#hub-git" class="sidebar-jump">Git — sync &amp; save</a>
            <a href="#hub-zip" class="sidebar-jump">Build ZIP</a>
            <a href="#hub-ftp" class="sidebar-jump">Upload to server</a>
        </nav>
        <div class="small pt-2" style="border-top: 1px solid rgba(255,255,255,.14);"><a href="?logout=1" class="text-light">Sign out</a></div>
    </aside>
    <main class="flex-fill d-flex flex-column p-3 p-md-4" style="min-width:0;">
        <h1 class="h5 text-dark mb-3">Update hub</h1>

        <?php if ($banner !== null): ?>
        <div class="status-strip d-flex flex-wrap align-items-center">
            <span class="mr-3"><strong>Copy name</strong> <?= htmlspecialchars($banner['branch'], ENT_QUOTES, 'UTF-8') ?></span>
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
            <span class="text-truncate" style="max-width:28rem;" title="<?= htmlspecialchars($banner['remote'], ENT_QUOTES, 'UTF-8') ?>"><strong>GitHub link</strong> <?= htmlspecialchars($banner['remote'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($isGit && $loggedIn): ?>
                <span class="mr-3 ml-md-3"><strong>FTP gate</strong>
                    <span class="badge badge-<?= $deployGate['ok'] ? 'success' : 'danger' ?>" title="Git must match GitHub before server upload"><?= $deployGate['ok'] ? 'OK to deploy' : 'blocked' ?></span>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($runError): ?>
            <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($runError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="row flex-fill align-items-md-start">
        <div class="col-md-6 col-lg-7 hub-controls-col pr-md-2 pr-lg-3">
        <div class="step border-<?= $deployGate['ok'] ? 'success' : 'danger' ?>" style="border-width:2px;">
            <h3 class="d-flex align-items-center flex-wrap">Deploy readiness
                <span class="badge badge-<?= $deployGate['ok'] ? 'success' : 'secondary' ?> ml-2"><?= $deployGate['ok'] ? 'PASS' : 'FAIL' ?></span>
            </h3>
            <p class="desc mb-2">FTP upload is <strong>only allowed</strong> when everything matches: no unsaved files in this folder, the site can reach GitHub, and your computer is <strong>not behind or ahead</strong> of what’s online (same saves as GitHub). Otherwise the upload button stays off.</p>
            <ul class="small mb-3 pl-3">
                <?php foreach ($deployGate['lines'] as $ln): ?>
                    <li><?= htmlspecialchars($ln, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="desc small mb-0">Fix any FAIL items (commit, push, pull, or set upstream), then <strong>refresh this page</strong> to re-run the check.</p>
        </div>

        <div class="step" id="hub-git">
            <h3>1. Get the latest from GitHub</h3>
            <p class="desc"><strong>Pull latest</strong> downloads anything new from your online repo into this folder (safe update). Use <strong>Check online</strong> if you only want to refresh what Git knows without changing files yet — usually you only need <strong>Pull latest</strong>.</p>
            <form method="post" class="d-inline mr-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="pull">
                <button type="submit" class="btn btn-outline-primary btn-sm" <?= $isGit ? '' : 'disabled title="Not a git repo"' ?>>Pull latest</button>
            </form>
            <form method="post" class="d-inline">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="fetch">
                <button type="submit" class="btn btn-outline-secondary btn-sm" <?= $isGit ? '' : 'disabled' ?>>Check online</button>
            </form>
        </div>

        <?php if ($isGit && !empty($banner['merging'])): ?>
        <div class="step border-warning" style="border-width:2px;">
            <h3>Merge in progress</h3>
            <p class="desc small mb-2">Git started combining changes (maybe from another tool). Finish here or cancel.</p>
            <div class="d-flex flex-wrap">
                <form method="post" class="mr-2 mb-2">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="hub_action" value="merge_continue">
                    <button type="submit" class="btn btn-outline-success btn-sm">Continue merge</button>
                </form>
                <form method="post" class="mb-2">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="hub_action" value="merge_abort">
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Abort this merge?');">Abort merge</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $canStep3Commit = $isGit && $hubWizardStep1;
        ?>
        <div class="step">
            <h3>2. Save your work &amp; send to GitHub</h3>
            <p class="desc">Saves app files from this folder to Git and can copy them to GitHub: <code>src/</code>, <code>templates/</code>, <code>public/</code>, <code>deploy/</code>, <code>bootstrap.php</code>, Composer files — not <code>vendor/</code> or <code>.env</code>.</p>
            <?php if ($isGit && !$hubWizardStep1): ?>
                <p class="small text-muted mb-2"><strong>Locked</strong> — run <strong>Pull latest</strong> or <strong>Check online</strong> in step 1 successfully first (so we know you’re in sync before saving).</p>
            <?php endif; ?>
            <form method="post" class="mb-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="commit">
                <div class="form-group mb-2">
                    <input type="text" name="commit_msg" class="form-control form-control-sm" placeholder="Commit message" maxlength="400" <?= $canStep3Commit ? 'required' : 'disabled' ?>>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" name="commit_push" id="cpush" value="1" checked <?= $canStep3Commit ? '' : 'disabled' ?>>
                    <label class="custom-control-label small" for="cpush">Also upload saves to GitHub after committing</label>
                </div>
                <button type="submit" class="btn btn-success btn-sm" <?= $canStep3Commit ? '' : 'disabled' ?>>Save &amp; push to GitHub</button>
            </form>
        </div>

        <div class="step" id="hub-zip">
            <h3>3. Build deploy ZIP</h3>
            <p class="desc mb-2">Builds a file under <code>dist/</code> (same as <code>php deploy/build.php</code> from the project root). Most of the time choose <strong>Full site</strong>, download the ZIP, upload it to your host, then run <code>composer install --no-dev</code> on the server if you did not include <code>vendor</code>.</p>
            <?php if ($loggedIn && !$deployLastRefOk): ?>
                <div class="alert alert-info py-2 small mb-3">
                    <strong>First-time note for “changed files since last marked deploy”:</strong> that option needs a saved baseline in <code>.deploy/last-ref</code>.
                    A <strong>full ZIP without</strong> “After a successful ZIP, remember this deploy” does <strong>not</strong> create it.
                    To start using marker-based deltas: run <strong>Remember this deploy only (no ZIP)</strong> once (sets baseline to your current commit), or tick <strong>remember this deploy</strong> after your next successful full/delta ZIP.
                </div>
            <?php endif; ?>
            <form method="post" id="pack-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="pack">

                <p class="pack-section-title">What should go in the package?</p>
                <div class="pack-subbox pack-mode-list mb-2">
                    <div class="custom-control custom-radio">
                        <input class="custom-control-input" type="radio" name="mode" id="pk_full" value="full" checked>
                        <label class="custom-control-label" for="pk_full">
                            <span class="pack-mode-line">Full site (recommended)</span>
                            <span class="pack-mode-hint">A complete deploy ZIP of the app. Use this for a new server or when you want everything in one file.</span>
                        </label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input class="custom-control-input" type="radio" name="mode" id="pk_dm" value="delta_marker">
                        <label class="custom-control-label" for="pk_dm">
                            <span class="pack-mode-line">Changed files since last marked deploy</span>
                            <span class="pack-mode-hint">Smaller ZIP: only files changed since the commit in <code>.deploy/last-ref</code>. That file appears only after <strong>Remember this deploy only</strong> or after a ZIP with <strong>remember this deploy</strong> checked — not automatically on a plain full ZIP.</span>
                        </label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input class="custom-control-input" type="radio" name="mode" id="pk_d" value="delta">
                        <label class="custom-control-label" for="pk_d">
                            <span class="pack-mode-line">Smaller ZIP vs a specific snapshot (advanced)</span>
                            <span class="pack-mode-hint">Only if your workflow compares to a named snapshot on Git. Fill the box below only when this option is selected.</span>
                        </label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input class="custom-control-input" type="radio" name="mode" id="pk_m" value="mark_only">
                        <label class="custom-control-label" for="pk_m">
                            <span class="pack-mode-line">Remember this deploy only (no ZIP)</span>
                            <span class="pack-mode-hint">Updates the saved commit in <code>.deploy/last-ref</code> so the next “since marker” build knows where to start. No archive is created.</span>
                        </label>
                    </div>
                </div>

                <div class="form-group mb-2" id="since_ref_wrap">
                    <label class="small font-weight-bold mb-1" for="since_ref">Snapshot name (only if you chose “advanced” delta above)</label>
                    <input type="text" name="since" id="since_ref" class="form-control form-control-sm" placeholder="Ask your developer — usually only for teams" maxlength="300" autocomplete="off">
                    <span class="form-text text-muted small mb-0">Ignored for full, marker, and “remember only” modes.</span>
                </div>

                <p class="pack-section-title">Extra options</p>
                <div class="pack-subbox mb-2">
                    <div class="custom-control custom-checkbox mb-2">
                        <input class="custom-control-input" type="checkbox" name="dry_run" id="pdr" value="1">
                        <label class="custom-control-label small" for="pdr"><strong>Dry run</strong> — show what would be packaged; do not write a ZIP (not used with “remember only”).</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input class="custom-control-input" type="checkbox" name="with_vendor" id="pwv" value="1">
                        <label class="custom-control-label small" for="pwv"><strong>Include <code>vendor/</code></strong> — only applies to <em>Full site</em>. Leave off if the server runs Composer itself.</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-0">
                        <input class="custom-control-input" type="checkbox" name="mark" id="pmk" value="1">
                        <label class="custom-control-label small" for="pmk"><strong>After a successful ZIP, remember this deploy</strong> — writes the current commit to <code>.deploy/last-ref</code> for future “since marker” builds.</label>
                    </div>
                </div>

                <p class="pack-section-title">Optional: push to Git after packaging</p>
                <div class="pack-subbox mb-2">
                    <div class="custom-control custom-checkbox mb-2">
                        <input class="custom-control-input" type="checkbox" name="git_push" id="pgp" value="1">
                        <label class="custom-control-label small" for="pgp"><strong>Run <code>git push</code> after the build</strong> — uses the remote and branch below (same as CLI <code>--push</code>).</label>
                    </div>
                    <div class="form-row pack-push-row mb-0">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <label for="push_remote_in">Remote name</label>
                            <input class="form-control form-control-sm" name="push_remote" id="push_remote_in" placeholder="origin" value="origin" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="push_branch_in">Line name (optional, advanced)</label>
                            <input class="form-control form-control-sm" name="push_branch" id="push_branch_in" placeholder="Leave empty — only if you use more than one line of work" autocomplete="off">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-dark btn-sm font-weight-bold">Build package</button>
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

        <div class="step" id="hub-ftp">
            <h3>4. Upload to server (FTP)</h3>
            <p class="desc">Uploads the <strong>newest</strong> file from <code>dist/gfm-deploy-*.zip</code> into your configured remote folder (same filename). Unzip on the host if your workflow uses a package file. Configure <code>ftp_*</code> keys in <code>config.local.php</code>.</p>
            <?php if (!$ftpConfigured): ?>
                <div class="alert alert-warning py-2 small mb-2">Add <code>ftp_host</code>, <code>ftp_user</code>, <code>ftp_password</code>, and <code>ftp_remote_dir</code> to <code>deploy/web/config.local.php</code> (see <code>config.example.php</code>). Optional: <code>ftp_port</code> (default 21), <code>ftp_passive</code> (default true).</div>
            <?php endif; ?>
            <?php if ($zips === []): ?>
                <p class="small text-muted mb-2">Build a ZIP in step 3 first — nothing to upload yet.</p>
            <?php else: ?>
                <p class="small mb-2">Next file: <code><?= htmlspecialchars(basename($zips[0]), ENT_QUOTES, 'UTF-8') ?></code></p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hub_action" value="ftp_upload">
                <?php
                $canFtp = $ftpConfigured && $deployGate['ok'] && $zips !== [];
                ?>
                <button type="submit" class="btn btn-warning text-dark btn-sm font-weight-bold" <?= $canFtp ? '' : 'disabled title="Configure FTP, pass Git checks, and build a ZIP"' ?>>Upload latest ZIP via FTP</button>
                <?php if ($ftpConfigured && !$deployGate['ok']): ?>
                    <span class="small text-danger ml-2">Blocked — Git checks above must pass.</span>
                <?php elseif ($ftpConfigured && $zips === []): ?>
                    <span class="small text-muted ml-2">Blocked — no ZIP in dist/.</span>
                <?php endif; ?>
            </form>
        </div>

        </div>
        <div class="col-md-6 col-lg-5 pl-md-2 pl-lg-2 hub-output-col">
            <div class="hub-output-card">
                <div class="card-head d-flex justify-content-between align-items-center">
                    <span>OUTPUT</span>
                    <span>
                        <?php if ($exitCode !== null): ?>
                            <span class="badge badge-<?= $exitCode === 0 ? 'success' : 'danger' ?> mr-2">packager exit <?= (int) $exitCode ?></span>
                        <?php endif; ?>
                        <a href="?clearlog=1" class="text-white-50 small" style="text-decoration:underline;">Clear log</a>
                    </span>
                </div>
                <pre class="console mb-0 rounded-0 border-0"><?= $console !== '' ? htmlspecialchars($console, ENT_QUOTES, 'UTF-8') : htmlspecialchars('>> Actions will log here when you use the buttons on the left.', ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
        </div>
        </div>

        <p class="small text-muted mt-3 mb-0">Run locally: <code>php -S 127.0.0.1:8765 -t deploy/web</code></p>
    </main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    function packSinceRefForMode() {
        var checked = document.querySelector('#pack-form input[name="mode"]:checked');
        var since = document.getElementById('since_ref');
        var wrap = document.getElementById('since_ref_wrap');
        if (!since) return;
        var delta = checked && checked.value === 'delta';
        since.disabled = !delta;
        if (wrap) wrap.style.opacity = delta ? '1' : '0.62';
    }
    function packPushFields() {
        var cb = document.getElementById('pgp');
        var r = document.getElementById('push_remote_in');
        var b = document.getElementById('push_branch_in');
        if (!cb || !r || !b) return;
        var on = cb.checked;
        r.disabled = !on;
        b.disabled = !on;
    }
    var form = document.getElementById('pack-form');
    if (form) {
        form.querySelectorAll('input[name="mode"]').forEach(function (el) {
            el.addEventListener('change', packSinceRefForMode);
        });
        var pgp = document.getElementById('pgp');
        if (pgp) pgp.addEventListener('change', packPushFields);
        packSinceRefForMode();
        packPushFields();
    }
})();
(function () {
    document.querySelectorAll('a.sidebar-jump[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var id = this.getAttribute('href').slice(1);
            var el = document.getElementById(id);
            if (!el) return;
            e.preventDefault();
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (window.history && window.history.replaceState) {
                history.replaceState(null, '', '#' + id);
            }
        });
    });
    if (window.location.hash) {
        var t = document.querySelector(window.location.hash);
        if (t) setTimeout(function () { t.scrollIntoView({ behavior: 'auto', block: 'start' }); }, 0);
    }
})();
</script>
</body>
</html>
