<?php
/**
 * No-Git Autopatch Harness (Filesystem Journal + Rollback)
 * --------------------------------------------------------
 * - Apply safe, minimal file edits without Git/GitHub.
 * - Per-transaction backups, manifest, release zip.
 * - Jail-safe path resolution (cannot escape repo).
 *
 * Usage examples:
 *   # Begin a transaction
 *   php tools/ui-bot/no_git_autopatch.php --begin --note "fix 404s on stock-transfer"
 *
 *   # Apply a file replacement (content as base64 to preserve bytes)
 *   php tools/ui-bot/no_git_autopatch.php --apply --txnid TXN123 --path public_html/stock-transfer/create.php --content-b64 "$(base64 -w0 newfile.php)"
 *
 *   # Commit and create a release zip
 *   php tools/ui-bot/no_git_autopatch.php --commit --txnid TXN123
 *
 *   # Rollback everything from a transaction
 *   php tools/ui-bot/no_git_autopatch.php --revert --txnid TXN123
 */

declare(strict_types=1);
date_default_timezone_set('Pacific/Auckland');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
umask(002);

// ---------- Config ----------
$ROOT = realpath(__DIR__ . '/../../');                 // repo root (adjust if needed)
$VAR  = $ROOT . '/var/ui-bot';
$DIRS = [
    'backups'  => $VAR . '/backups',
    'logs'     => $VAR . '/logs',
    'tmp'      => $VAR . '/tmp',
    'releases' => $VAR . '/releases',
];
$JAILS = [
    $ROOT . '/public_html',
    $ROOT . '/assets',
    $ROOT . '/modules',
    $ROOT . '/tools',
];

foreach ($DIRS as $d) { if (!is_dir($d)) @mkdir($d, 0775, true); }

// ---------- Helpers ----------
function out(array $payload, int $code = 0): void {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    if ($code !== 0) exit($code);
}

function fail(string $msg, array $extra = []): void {
    out(['ok' => false, 'error' => $msg] + $extra, 1);
}

function ok(array $data = []): void { out(['ok' => true] + $data); }

function normalize_path(string $base, string $rel): string {
    $p = $base . '/' . ltrim($rel, '/');
    $parts = [];
    foreach (explode('/', $p) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') { array_pop($parts); continue; }
        $parts[] = $seg;
    }
    $norm = '/' . implode('/', $parts);
    // collapse // -> /
    $norm = preg_replace('#/+#','/',$norm);
    return $norm;
}

function in_jail(string $abs, array $jails): bool {
    foreach ($jails as $j) {
        $j = rtrim($j, '/');
        if (str_starts_with($abs, $j . '/')) return true;
        if ($abs === $j) return true;
    }
    return false;
}

function ensure_dir(string $path): void { if (!is_dir($path)) @mkdir($path, 0775, true); }

function write_file_atomic(string $path, string $bytes): void {
    $dir = dirname($path);
    ensure_dir($dir);
    $tmp = tempnam($dir, '.swap.');
    if ($tmp === false) throw new RuntimeException("Cannot create temp file in $dir");
    $n = file_put_contents($tmp, $bytes);
    if ($n === false) throw new RuntimeException("Failed to write temp file for $path");
    @chmod($tmp, 0664);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Failed to atomic-rename into $path");
    }
}

function copy_with_dirs(string $src, string $dst): void {
    ensure_dir(dirname($dst));
    if (!@copy($src, $dst)) throw new RuntimeException("Backup copy failed: $src -> $dst");
    @chmod($dst, fileperms($src) & 0777);
}

function zip_paths(string $zipPath, string $root, array $files): void {
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot open $zipPath for writing");
    }
    foreach ($files as $rel) {
        $abs = normalize_path($root, $rel);
        if (is_dir($abs)) continue;
        if (!is_file($abs)) continue;
        $local = ltrim($rel, '/');
        $zip->addFile($abs, $local);
    }
    $zip->close();
}

function journal_log(string $logsDir, string $entry): void {
    $file = $logsDir . '/journal.jsonl';
    file_put_contents($file, $entry . PHP_EOL, FILE_APPEND);
}

function txn_dir(string $backupsDir, string $txnid): string {
    return rtrim($backupsDir, '/') . '/' . $txnid;
}

function gen_txn_id(): string {
    return 'TXN' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
}

// ---------- CLI Parse ----------
$args = $argv; array_shift($args);
$flags = [];
for ($i = 0; $i < count($args); $i++) {
    $a = $args[$i];
    if (str_starts_with($a, '--')) {
        $key = ltrim($a, '-');
        $val = true;
        if (isset($args[$i+1]) && !str_starts_with($args[$i+1], '--')) {
            $val = $args[++$i];
        }
        $flags[$key] = $val;
    }
}

// ---------- Actions ----------
$begin   = isset($flags['begin']);
$apply   = isset($flags['apply']);
$commit  = isset($flags['commit']);
$revert  = isset($flags['revert']);
$status  = isset($flags['status']);

if (!$begin && !$apply && !$commit && !$revert && !$status) {
    fail("Specify one: --begin | --apply | --commit | --revert | --status");
}

// --begin
if ($begin) {
    $txnid = gen_txn_id();
    $note  = (string)($flags['note'] ?? '');
    $tDir  = txn_dir($DIRS['backups'], $txnid);
    ensure_dir($tDir . '/orig');
    ensure_dir($tDir . '/new');
    $meta = [
        'txnid' => $txnid,
        'note'  => $note,
        'created_at' => date('Y-m-d H:i:s'),
        'root' => $ROOT,
        'changed' => [],
        'committed' => false,
    ];
    write_file_atomic($tDir . '/meta.json', json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    journal_log($DIRS['logs'], json_encode(['type'=>'begin','txnid'=>$txnid,'note'=>$note,'ts'=>time()]));
    ok(['txnid' => $txnid, 'backups_dir' => $tDir]);
    exit;
}

$txnid = (string)($flags['txnid'] ?? '');
if ($apply || $commit || $revert || $status) {
    if ($txnid === '') fail("Missing --txnid");
}
$tDir = txn_dir($DIRS['backups'], $txnid);
$metaFile = $tDir . '/meta.json';
if (!is_file($metaFile)) fail("Unknown txn: $txnid");

$meta = json_decode(file_get_contents($metaFile), true);
if (!is_array($meta)) fail("Corrupt meta for $txnid");

// --status
if ($status) {
    ok(['txnid' => $txnid, 'meta' => $meta]);
    exit;
}

// --apply
if ($apply) {
    $relPath = (string)($flags['path'] ?? '');
    $contentB64 = (string)($flags['content-b64'] ?? '');
    if ($relPath === '' || $contentB64 === '') fail("Need --path and --content-b64");

    $absTarget = normalize_path($ROOT, $relPath);
    if (!in_jail($absTarget, $JAILS)) fail("Path outside jail", ['path'=>$relPath]);

    $bytes = base64_decode($contentB64, true);
    if ($bytes === false) fail("content-b64 is not valid base64");

    // Backup original if exists & not backed up yet
    $backupRel = 'orig/' . ltrim($relPath,'/');
    $newRel    = 'new/'  . ltrim($relPath,'/');
    $absBackup = $tDir . '/' . $backupRel;
    $absNew    = $tDir . '/' . $newRel;

    if (is_file($absTarget) && !is_file($absBackup)) {
        copy_with_dirs($absTarget, $absBackup);
    }

    // Write new file to target, store a copy under txn/new for release bundle
    write_file_atomic($absTarget, $bytes);
    write_file_atomic($absNew, $bytes);

    // Update meta
    if (!in_array($relPath, $meta['changed'], true)) $meta['changed'][] = $relPath;
    write_file_atomic($metaFile, json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));

    journal_log($DIRS['logs'], json_encode(['type'=>'apply','txnid'=>$txnid,'path'=>$relPath,'bytes'=>strlen($bytes),'ts'=>time()]));
    ok(['applied' => $relPath, 'bytes' => strlen($bytes)]);
    exit;
}

// --commit
if ($commit) {
    if (!empty($meta['committed'])) ok(['txnid'=>$txnid,'message'=>'already committed']);
    $files = $meta['changed'] ?? [];
    $zipName = 'release-' . $txnid . '.zip';
    $zipPath = $DIRS['releases'] . '/' . $zipName;

    // Build release from txn/new copies to ensure consistency
    $releaseList = [];
    foreach ($files as $rel) {
        $rel = ltrim($rel, '/');
        $releaseList[] = $rel;
    }
    zip_paths($zipPath, $tDir . '/new', $releaseList);

    $meta['committed'] = true;
    $meta['committed_at'] = date('Y-m-d H:i:s');
    $meta['release_zip'] = $zipPath;
    write_file_atomic($metaFile, json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));

    journal_log($DIRS['logs'], json_encode(['type'=>'commit','txnid'=>$txnid,'files'=>count($releaseList),'zip'=>$zipPath,'ts'=>time()]));
    ok(['txnid'=>$txnid,'release_zip'=>$zipPath,'files'=>count($releaseList)]);
    exit;
}

// --revert
if ($revert) {
    $files = $meta['changed'] ?? [];
    $reverted = 0; $missing = 0;
    foreach ($files as $rel) {
        $absTarget = normalize_path($ROOT, $rel);
        if (!in_jail($absTarget, $JAILS)) continue;
        $absBackup = $tDir . '/orig/' . ltrim($rel,'/');
        if (is_file($absBackup)) {
            copy_with_dirs($absBackup, $absTarget);
            $reverted++;
        } else {
            // If there was no original backup (new file), remove the file
            if (is_file($absTarget)) @unlink($absTarget);
            $missing++;
        }
    }
    journal_log($DIRS['logs'], json_encode(['type'=>'revert','txnid'=>$txnid,'reverted'=>$reverted,'removed_new'=>$missing,'ts'=>time()]));
    ok(['txnid'=>$txnid,'reverted'=>$reverted,'removed_new'=>$missing]);
    exit;
}
