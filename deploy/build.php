#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Greenfield deployment packager — builds a ZIP you can upload (FTP/cPanel).
 *
 * Incremental (default): files changed since a git ref (commit/branch/tag),
 *   compared to HEAD. Ref is taken from --since=, or .deploy/last-ref, or fails.
 * Full: application code under src/, public/, templates/ plus bootstrap and Composer files.
 *
 * Usage:
 *   php deploy/build.php --help
 *   php deploy/build.php --full [--with-vendor] [--output=dist/gfm-full.zip]
 *   php deploy/build.php --since=origin/main [--output=dist/gfm-delta.zip]
 *   php deploy/build.php   # same as --since=$(cat .deploy/last-ref) when that file exists
 *   php deploy/build.php --mark   # after a good pack, record HEAD in .deploy/last-ref for next delta
 *   php deploy/build.php --mark-only   # only update .deploy/last-ref to HEAD (no ZIP)
 *   php deploy/build.php --full --push [--push-remote=origin] [--push-branch=main]
 *       After a successful pack (and optional --mark), run: git push <remote> <branch>
 *
 * On the server: unzip into the site root (same layout as this repo), then run
 *   composer install --no-dev --optimize-autoloader
 * unless you packaged with --with-vendor.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from CLI only.\n");
    exit(1);
}

$projectRoot = realpath(dirname(__DIR__));
if ($projectRoot === false) {
    fwrite(STDERR, "Cannot resolve project root.\n");
    exit(1);
}

chdir($projectRoot);

$markerDir = $projectRoot . DIRECTORY_SEPARATOR . '.deploy';
$markerFile = $markerDir . DIRECTORY_SEPARATOR . 'last-ref';

$args = array_slice($argv, 1);
$opts = [
    'full' => false,
    'with_vendor' => false,
    'mark' => false,
    'mark_only' => false,
    'dry_run' => false,
    'help' => false,
    'since' => null,
    'output' => null,
    'push' => false,
    'push_remote' => 'origin',
    'push_branch' => null,
];

$badArg = false;
foreach ($args as $a) {
    if ($a === '--help' || $a === '-h') {
        $opts['help'] = true;
    } elseif ($a === '--full') {
        $opts['full'] = true;
    } elseif ($a === '--with-vendor') {
        $opts['with_vendor'] = true;
    } elseif ($a === '--mark') {
        $opts['mark'] = true;
    } elseif ($a === '--mark-only') {
        $opts['mark_only'] = true;
    } elseif ($a === '--dry-run') {
        $opts['dry_run'] = true;
    } elseif (str_starts_with($a, '--since=')) {
        $opts['since'] = substr($a, 8);
    } elseif (str_starts_with($a, '--output=')) {
        $opts['output'] = substr($a, 9);
    } elseif ($a === '--push') {
        $opts['push'] = true;
    } elseif (str_starts_with($a, '--push-remote=')) {
        $opts['push_remote'] = substr($a, 15);
    } elseif (str_starts_with($a, '--push-branch=')) {
        $opts['push_branch'] = substr($a, 15);
    } else {
        fwrite(STDERR, "Unknown argument: {$a}\n");
        $opts['help'] = true;
        $badArg = true;
    }
}

if ($opts['help']) {
    $h = <<<'TXT'
Greenfield deploy packager

  php deploy/build.php --full [--with-vendor] [--output=PATH] [--dry-run]
      Build a ZIP of application files (excludes .env; vendor optional).

  php deploy/build.php [--since=GIT_REF] [--output=PATH] [--dry-run] [--mark]
      Delta ZIP: files changed between GIT_REF and HEAD (git repo required).
      If --since is omitted, uses .deploy/last-ref if present.

  php deploy/build.php --mark
      After a successful pack, write current HEAD to .deploy/last-ref (next delta baseline).

  php deploy/build.php --mark-only
      Only update .deploy/last-ref to HEAD (e.g. you uploaded files manually).

  php deploy/build.php ... --push [--push-remote=origin] [--push-branch=BRANCH]
      After a successful ZIP (or with --mark-only), run git push. If --push-branch
      is omitted, uses the current branch name. Requires Git auth (SSH key or credential helper).

  Outputs default: dist/gfm-deploy-YYYYMMDD-His.zip

TXT;
    fwrite(STDOUT, $h);
    exit($badArg ? 1 : 0);
}

if ($opts['push'] && $opts['dry_run']) {
    $opts['push'] = false;
    fwrite(STDERR, "Note: --push is ignored with --dry-run.\n");
}

if ($opts['mark_only']) {
    if (!is_dir($markerDir) && !@mkdir($markerDir, 0775, true)) {
        fwrite(STDERR, "Could not create .deploy directory.\n");
        exit(1);
    }
    run_git($projectRoot, 'rev-parse', '--is-inside-work-tree');
    $head = git_head($projectRoot);
    file_put_contents($markerFile, $head . "\n");
    fwrite(STDOUT, "Updated .deploy/last-ref = {$head}\n");
    $brNv = git_current_branch_nv($projectRoot);
    if ($opts['push']) {
        $ok = deploy_git_push($projectRoot, $opts['push_remote'], $opts['push_branch']);
        print_deploy_plan($opts['push_remote'], $brNv, true, $ok);
        exit($ok ? 0 : 1);
    }
    print_deploy_plan($opts['push_remote'], $brNv, false, true);
    exit(0);
}

if ($opts['output'] === null || $opts['output'] === '') {
    $opts['output'] = 'dist/gfm-deploy-' . gmdate('Ymd-His') . 'Z.zip';
}

$outputAbs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $opts['output']);
$outputDir = dirname($outputAbs);
if (!is_dir($outputDir)) {
    if (!@mkdir($outputDir, 0775, true)) {
        fwrite(STDERR, "Cannot create output directory: {$outputDir}\n");
        exit(1);
    }
}

/** @return list<string> */
function run_git(string $projectRoot, string ...$cmdTail): array
{
    $parts = ['git', '-C', $projectRoot, ...$cmdTail];
    $cmd = '';
    foreach ($parts as $p) {
        $cmd .= ($cmd === '' ? '' : ' ') . escapeshellarg($p);
    }
    exec($cmd . ' 2>&1', $out, $code);
    if ($code !== 0) {
        fwrite(STDERR, "git failed ({$code}): " . implode("\n", $out) . "\n");
        exit(1);
    }

    return $out;
}

/**
 * @return array{0: int, 1: list<string>}
 */
function run_git_nv(string $projectRoot, string ...$cmdTail): array
{
    $parts = ['git', '-C', $projectRoot, ...$cmdTail];
    $cmd = '';
    foreach ($parts as $p) {
        $cmd .= ($cmd === '' ? '' : ' ') . escapeshellarg($p);
    }
    exec($cmd . ' 2>&1', $out, $code);

    return [$code, is_array($out) ? $out : []];
}

function git_head(string $projectRoot): string
{
    $out = run_git($projectRoot, 'rev-parse', 'HEAD');
    $h = trim($out[0] ?? '');
    if ($h === '' || !preg_match('/^[a-f0-9]{7,40}$/i', $h)) {
        fwrite(STDERR, "Could not read HEAD.\n");
        exit(1);
    }

    return $h;
}

function git_current_branch_nv(string $projectRoot): ?string
{
    [$code, $out] = run_git_nv($projectRoot, 'rev-parse', '--abbrev-ref', 'HEAD');
    if ($code !== 0) {
        return null;
    }
    $b = trim($out[0] ?? '');
    if ($b === '' || $b === 'HEAD') {
        return null;
    }

    return $b;
}

function git_current_branch(string $projectRoot): string
{
    $b = git_current_branch_nv($projectRoot);
    if ($b === null) {
        fwrite(STDERR, "Detached HEAD — set --push-branch=your-branch\n");
        exit(1);
    }

    return $b;
}

function git_warn_if_dirty(string $projectRoot): void
{
    [$code, $out] = run_git_nv($projectRoot, 'status', '--porcelain');
    if ($code !== 0) {
        return;
    }
    $s = trim(implode("\n", $out));
    if ($s !== '') {
        fwrite(STDERR, "Warning: working tree has uncommitted changes. Push sends only committed commits.\n");
    }
}

function deploy_git_ref_safe(string $value): bool
{
    $value = trim($value);

    return $value !== '' && preg_match('/^[a-zA-Z0-9_./-]+$/', $value) === 1;
}

function deploy_git_push(string $projectRoot, string $remote, ?string $branch): bool
{
    $remote = trim($remote);
    if (!deploy_git_ref_safe($remote)) {
        fwrite(STDERR, "Invalid --push-remote name.\n");

        return false;
    }
    $br = $branch !== null && trim($branch) !== '' ? trim((string) $branch) : git_current_branch($projectRoot);
    if (!deploy_git_ref_safe($br)) {
        fwrite(STDERR, "Invalid branch name.\n");

        return false;
    }
    git_warn_if_dirty($projectRoot);
    fwrite(STDOUT, "Pushing to {$remote} {$br} ...\n");
    [$code, $out] = run_git_nv($projectRoot, 'push', $remote, $br);
    fwrite(STDOUT, implode("\n", $out) . "\n");
    if ($code !== 0) {
        fwrite(STDERR, "git push failed (exit {$code}). Fix remote/auth, then run: git push {$remote} {$br}\n");

        return false;
    }

    return true;
}

function print_deploy_plan(string $remote, ?string $branchHint, bool $pushUsed, bool $pushOk): void
{
    $b = $branchHint ?? '(your branch)';
    fwrite(STDOUT, "\n--- Deploy plan (GitHub + hosting) ---\n");
    fwrite(STDOUT, "[ ] 1. Commit anything not yet committed: git status → git add … → git commit -m \"…\"\n");
    fwrite(STDOUT, "[ ] 2. Push to GitHub: git push {$remote} {$b}\n");
    if ($pushUsed) {
        fwrite(STDOUT, '    (You used --push: ' . ($pushOk ? 'completed.' : 'FAILED — fix and push manually.)') . "\n");
    }
    fwrite(STDOUT, "[ ] 3. Upload the ZIP to the server, extract over the app root\n");
    fwrite(STDOUT, "[ ] 4. On server (if ZIP has no vendor): composer install --no-dev --optimize-autoloader\n");
    fwrite(STDOUT, "--------------------------------------\n");
}

function inside_project(string $root, string $path): bool
{
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
    $full = str_replace('\\', '/', $path);
    $real = realpath($full);
    if ($real !== false) {
        $full = str_replace('\\', '/', $real);
    }

    return str_starts_with($full, $root . '/') || $full === $root;
}

/** @param list<string> $rels */
function should_skip_upload(string $rel): bool
{
    $rel = str_replace('\\', '/', $rel);
    if ($rel === '' || str_contains($rel, '..')) {
        return true;
    }
    if (str_starts_with($rel, 'vendor/')) {
        return true;
    }
    if ($rel === '.env' || str_starts_with($rel, '.env.')) {
        return true;
    }
    if (str_starts_with($rel, 'dist/') && str_ends_with($rel, '.zip')) {
        return true;
    }

    return false;
}

/**
 * @return list<string> relative paths using /
 */
function collect_full_paths(string $root, bool $withVendor): array
{
    $out = [];
    $dirs = ['src', 'public', 'templates'];
    foreach ($dirs as $dir) {
        $base = $root . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );
        /** @var SplFileInfo $fi */
        foreach ($it as $fi) {
            if (!$fi->isFile()) {
                continue;
            }
            $abs = $fi->getPathname();
            $rel = substr(str_replace('\\', '/', $abs), strlen(str_replace('\\', '/', $root)) + 1);
            if (should_skip_upload($rel)) {
                continue;
            }
            if (!$withVendor && str_starts_with($rel, 'vendor/')) {
                continue;
            }
            $out[] = $rel;
        }
    }
    foreach (['bootstrap.php', 'composer.json', 'composer.lock', 'deploy/build.php'] as $f) {
        $p = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $f);
        if (is_file($p)) {
            $out[] = str_replace('\\', '/', $f);
        }
    }
    if ($withVendor && is_dir($root . DIRECTORY_SEPARATOR . 'vendor')) {
        $vb = $root . DIRECTORY_SEPARATOR . 'vendor';
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($vb, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $fi) {
            if (!$fi->isFile()) {
                continue;
            }
            $abs = $fi->getPathname();
            $rel = substr(str_replace('\\', '/', $abs), strlen(str_replace('\\', '/', $root)) + 1);
            if (should_skip_upload($rel)) {
                continue;
            }
            $out[] = $rel;
        }
    }

    return array_values(array_unique($out));
}

/**
 * @return array{0: list<string>, 1: list<string>} added/changed files and deleted (git paths)
 */
function collect_delta_paths(string $root, string $since): array
{
    if (!preg_match('/^[a-zA-Z0-9_^./~@-]+$/', $since)) {
        fwrite(STDERR, "Unsafe or invalid --since ref.\n");
        exit(1);
    }
    $outFiles = run_git($root, 'diff', '--diff-filter=d', '--name-only', $since . '..HEAD');
    $outDel = run_git($root, 'diff', '--diff-filter=D', '--name-only', $since . '..HEAD');
    $files = [];
    foreach ($outFiles as $line) {
        $path = str_replace('\\', '/', trim($line));
        if ($path === '' || should_skip_upload($path)) {
            continue;
        }
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($abs)) {
            $files[] = $path;
        }
    }
    $deleted = [];
    foreach ($outDel as $line) {
        $path = str_replace('\\', '/', trim($line));
        if ($path === '' || should_skip_upload($path)) {
            continue;
        }
        $deleted[] = $path;
    }

    return [array_values(array_unique($files)), array_values(array_unique($deleted))];
}

// --- main ---

$since = null;
if ($opts['full']) {
    $paths = collect_full_paths($projectRoot, $opts['with_vendor']);
    $deleted = [];
    fwrite(STDOUT, 'Full package: ' . count($paths) . " file(s)" . ($opts['with_vendor'] ? ' (with vendor)' : '') . ".\n");
} else {
    $since = $opts['since'];
    if ($since === null || $since === '') {
        if (is_readable($markerFile)) {
            $since = trim((string) file_get_contents($markerFile));
        }
    }
    if ($since === null || $since === '') {
        fwrite(STDERR, "Incremental mode needs a git ref. Use --since=branch_or_commit, or create .deploy/last-ref, or use --full.\n");
        exit(1);
    }
    run_git($projectRoot, 'rev-parse', '--verify', $since . '^{commit}');
    [$paths, $deleted] = collect_delta_paths($projectRoot, $since);
    fwrite(STDOUT, "Delta since {$since}: " . count($paths) . " file(s), " . count($deleted) . " deleted path(s) listed in manifest.\n");
}

sort($paths);

if ($opts['dry_run']) {
    foreach ($paths as $p) {
        fwrite(STDOUT, $p . "\n");
    }
    if (!empty($deleted)) {
        fwrite(STDOUT, "\n--- deleted (remove on server if applicable) ---\n");
        foreach ($deleted as $p) {
            fwrite(STDOUT, $p . "\n");
        }
    }
    $brNv = git_current_branch_nv($projectRoot);
    print_deploy_plan($opts['push_remote'], $brNv, false, true);
    exit(0);
}

if (!class_exists(\ZipArchive::class)) {
    fwrite(STDERR, "PHP zip extension (ZipArchive) is required to create ZIP files.\n");
    exit(1);
}

if ($paths === [] && $deleted === []) {
    fwrite(STDERR, "Nothing to pack (no changes since ref, and no deletions).\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($outputAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot open ZIP for writing: {$outputAbs}\n");
    exit(1);
}

$manifest = "Greenfield deploy manifest\n";
$manifest .= 'Generated (UTC): ' . gmdate('c') . "\n";
$manifest .= 'Mode: ' . ($opts['full'] ? 'full' : 'delta') . "\n";
if (!$opts['full']) {
    $manifest .= 'Since ref: ' . ($since ?? '') . "\n";
    $manifest .= 'HEAD: ' . git_head($projectRoot) . "\n";
}
$manifest .= "\nFiles in archive:\n";
foreach ($paths as $rel) {
    $manifest .= $rel . "\n";
}
if ($deleted !== []) {
    $manifest .= "\nPaths deleted since ref (not in ZIP — delete on server if needed):\n";
    foreach ($deleted as $rel) {
        $manifest .= $rel . "\n";
    }
}
$planBranch = git_current_branch_nv($projectRoot);
$manifest .= "\n--- Deploy plan (GitHub + hosting) ---\n";
$manifest .= "1) Commit: git status → git add … → git commit -m \"…\"\n";
$manifest .= '2) Push: git push ' . $opts['push_remote'] . ' ' . ($planBranch ?? '<branch>') . "\n";
if ($opts['push']) {
    $manifest .= "   (This run will attempt git push immediately after the ZIP is written.)\n";
}
$manifest .= "3) Upload this ZIP to the server and extract over the app root.\n";
$manifest .= "4) Server: composer install --no-dev --optimize-autoloader (if vendor not in ZIP).\n";
$zip->addFromString('DEPLOY-MANIFEST.txt', $manifest);

foreach ($paths as $rel) {
    $rel = str_replace('\\', '/', $rel);
    $abs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($abs) || !inside_project($projectRoot, $abs)) {
        continue;
    }
    $zip->addFile($abs, $rel);
}

$zip->close();

$size = @filesize($outputAbs);
fwrite(STDOUT, "Wrote: {$outputAbs} (" . ($size !== false ? (string) $size : '?') . " bytes)\n");

if ($opts['mark']) {
    if (!is_dir($markerDir) && !@mkdir($markerDir, 0775, true)) {
        fwrite(STDERR, "Could not create .deploy directory for --mark.\n");
        exit(1);
    }
    $head = git_head($projectRoot);
    file_put_contents($markerFile, $head . "\n");
    fwrite(STDOUT, "Marked .deploy/last-ref = {$head}\n");
}

$pushOk = true;
if ($opts['push']) {
    $pushOk = deploy_git_push($projectRoot, $opts['push_remote'], $opts['push_branch']);
}
print_deploy_plan($opts['push_remote'], $planBranch ?? null, $opts['push'], $pushOk);

exit($opts['push'] && !$pushOk ? 1 : 0);
