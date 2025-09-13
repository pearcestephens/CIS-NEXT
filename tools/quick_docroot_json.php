<?php
/**
 * Quick JSON Document Root Output — v2.5
 * Path: tools/quick_docroot_json.php
 *
 * Upgrades:
 * - Modes: full | summary | flat | offenders | diff
 * - Filters: base, only (comma paths), exclude_globs (comma), max_depth, since, limit_items
 * - Hashing: hash=none|small|all (hash_max_bytes)
 * - Snapshots: save=1 (writes var/reports/docroot_snapshot_*.json), diff=?file=snapshot.json
 * - Offenders: *_new|*_enhanced|*_refactored|*_old|*_backup|*_fixed|*_copy|*_temp_backup
 * - Dangerous path exposure: tools/, migrations/, backups/, var/, docs/, resources/
 * - Optional access token for web requests (?token=...) via env DOCROOT_JSON_TOKEN
 */

declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
}

$DEFAULT_BASE = '/var/www/cis.dev.ecigdis.co.nz/public_html';
$DEFAULT_TZ   = 'Pacific/Auckland';

date_default_timezone_set($DEFAULT_TZ);

/* ---------- input parsing (CLI or GET) ---------- */
function arg(string $name, $default = null) {
    global $isCli;
    if ($isCli) {
        $long = "--$name=";
        foreach ($GLOBALS['argv'] ?? [] as $a) {
            if (strpos($a, $long) === 0) return substr($a, strlen($long));
        }
        return $default;
    }
    return $_GET[$name] ?? $default;
}

$cfg = [
    'base_path'      => rtrim((string)arg('base', $DEFAULT_BASE), '/'),
    'timezone'       => (string)arg('tz', $DEFAULT_TZ),
    'mode'           => strtolower((string)arg('mode', 'full')),   // full|summary|flat|offenders|diff
    'only'           => (string)arg('only', ''),                   // CSV of subpaths (e.g. "app, routes")
    'exclude_globs'  => (string)arg('exclude', '.git/*,node_modules/*,vendor/*,var/cache/*,var/logs/archive/*'),
    'max_depth'      => (int)arg('depth', 10),
    'since'          => (string)arg('since', ''),                  // ISO 8601 filter by mtime
    'limit_items'    => (int)arg('limit', 25000),                  // hard cap
    'hash_mode'      => strtolower((string)arg('hash', 'none')),   // none|small|all
    'hash_max_bytes' => (int)arg('hash_max_bytes', 1048576),       // 1MB
    'save_snapshot'  => (bool)arg('save', '0'),
    'diff_file'      => (string)arg('diff', ''),                   // path to prior snapshot
    'danger_paths'   => ['tools/', 'migrations/', 'backups/', 'var/', 'docs/', 'resources/'],
    'offender_rx'    => '/_(new|enhanced|refactored|old|backup|fixed|copy|temp_backup)\.[A-Za-z0-9]+$/i',
    'show_progress'  => (bool)($isCli && (arg('progress', '0')==='1')),
];

/* ---------- optional access token for web -------- */
$tokenEnv = getenv('DOCROOT_JSON_TOKEN') ?: '';
if (!$isCli && $tokenEnv !== '') {
    $tok = $_GET['token'] ?? '';
    if (!hash_equals($tokenEnv, $tok)) {
        http_response_code(401);
        echo json_encode(['error'=>true,'code'=>'UNAUTHORIZED','message'=>'missing/invalid token'], JSON_PRETTY_PRINT);
        exit;
    }
}

date_default_timezone_set($cfg['timezone']);

/* ---------- helpers ---------- */
final class FSScan {
    public array $errors = [];
    public int $files = 0;
    public int $dirs  = 0;
    public int $totalBytes = 0;
    public int $maxDepth = 0;
    public array $flat = []; // flat list records
    private array $extStats = [];
    private array $largest = [];
    private array $offenders = [];
    private array $danger = [];

    public function __construct(private array $cfg) {}

    private function rel(string $p): string {
        $base = rtrim($this->cfg['base_path'], '/') . '/';
        return str_starts_with($p, $base) ? substr($p, strlen($base)) : $p;
    }
    private function includePath(string $rel): bool {
        $only = array_filter(array_map('trim', explode(',', $this->cfg['only'])));
        if ($only) {
            $ok = false;
            foreach ($only as $o) {
                $o = rtrim($o,'/').'/';
                if (str_starts_with($rel, $o)) { $ok = true; break; }
            }
            if (!$ok) return false;
        }
        return true;
    }
    private function excluded(string $rel): bool {
        $globs = array_filter(array_map('trim', explode(',', $this->cfg['exclude_globs'])));
        foreach ($globs as $g) {
            if (fnmatch($g, $rel, FNM_PATHNAME|FNM_CASEFOLD)) return true;
        }
        return false;
    }
    private function sinceFilter(int $mtime): bool {
        if (!$this->cfg['since']) return true;
        $ts = strtotime($this->cfg['since']);
        return $mtime >= $ts;
    }
    private function maybeHash(string $path, int $size): ?string {
        $mode = $this->cfg['hash_mode'];
        if ($mode === 'none') return null;
        if ($mode === 'small' && $size > $this->cfg['hash_max_bytes']) return null;
        return hash_file('sha256', $path);
    }
    private function recordLargest(string $rel, int $size): void {
        $this->largest[] = ['path'=>$rel,'size'=>$size];
        if (count($this->largest) > 50) {
            usort($this->largest, fn($a,$b)=>$b['size']<=>$a['size']);
            $this->largest = array_slice($this->largest, 0, 50);
        }
    }
    private function recordExt(string $ext, int $size): void {
        if ($ext==='') $ext = '(none)';
        $this->extStats[$ext] = ($this->extStats[$ext] ?? ['count'=>0,'bytes'=>0]);
        $this->extStats[$ext]['count']++;
        $this->extStats[$ext]['bytes'] += $size;
    }
    private function recordDanger(string $rel): void {
        foreach ($this->cfg['danger_paths'] as $d) {
            $d = rtrim($d,'/').'/';
            if (str_starts_with($rel,$d)) {
                $this->danger[] = $rel;
                break;
            }
        }
    }
    private function recordFlat(array $node): void {
        $this->flat[] = $node;
        if (count($this->flat) >= $this->cfg['limit_items']) {
            throw new RuntimeException('limit_items_exceeded');
        }
    }

    public function scanTree(string $path, int $depth=0): array {
        $rel = $this->rel($path);
        $type = is_dir($path) ? 'directory' : 'file';
        $node = [
            'name' => basename($path),
            'type' => $type,
            'path' => $rel,
            'depth'=> $depth,
        ];
        $this->maxDepth = max($this->maxDepth, $depth);

        if ($depth > $this->cfg['max_depth']) {
            $node['error'] = 'max_depth_exceeded';
            return $node;
        }
        if (!$this->includePath($rel) || $this->excluded($rel)) {
            $node['excluded'] = true;
            return $node;
        }

        if (!file_exists($path)) {
            $node['error'] = 'not_found';
            return $node;
        }

        $stat = @stat($path);
        if ($stat) {
            $node['created']  = date('c', $stat['ctime']);
            $node['modified'] = date('c', $stat['mtime']);
            $node['permissions'] = substr(sprintf('%o', @fileperms($path)), -4);
        }

        try {
            if ($type === 'file') {
                $size = @filesize($path) ?: 0;
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
                $node['size_bytes'] = $size;
                $node['extension']  = $ext ?: null;
                $node['readable']   = is_readable($path);
                $node['writable']   = is_writable($path);
                $node['executable'] = is_executable($path);

                if ($this->sinceFilter($stat['mtime'] ?? 0)) {
                    $this->files++;
                    $this->totalBytes += $size;
                    $this->recordExt($ext, $size);
                    $this->recordLargest($rel, $size);
                    $this->recordDanger($rel);
                    if (preg_match($this->cfg['offender_rx'], $rel)) {
                        $this->offenders[] = $rel;
                    }
                    if ($hash = $this->maybeHash($path, $size)) {
                        $node['sha256'] = $hash;
                    }
                    $this->recordFlat([
                        'path'=>$rel,'type'=>'file','size'=>$size,
                        'mtime'=>$stat['mtime'] ?? null,'sha256'=>$node['sha256'] ?? null
                    ]);
                } else {
                    $node['skipped_since'] = true;
                }
            } else { // directory
                $this->dirs++;
                $node['children'] = [];
                $dir = @scandir($path);
                if ($dir !== false) {
                    foreach ($dir as $entry) {
                        if ($entry === '.' || $entry === '..') continue;
                        $child = $path . '/' . $entry;
                        $node['children'][] = $this->scanTree($child, $depth+1);
                    }
                    usort($node['children'], function($a,$b){
                        if ($a['type'] !== $b['type']) return $a['type']==='directory' ? -1 : 1;
                        return strcasecmp($a['name'],$b['name']);
                    });
                }
                $this->recordFlat(['path'=>$rel,'type'=>'directory','mtime'=>$stat['mtime'] ?? null]);
            }
        } catch (\Throwable $e) {
            $node['error'] = $e->getMessage();
            $this->errors[] = ['path'=>$rel,'error'=>$e->getMessage()];
        }
        return $node;
    }

    public function summary(): array {
        $exts = [];
        foreach ($this->extStats as $ext=>$s) {
            $exts[] = ['ext'=>$ext,'count'=>$s['count'],'bytes'=>$s['bytes']];
        }
        usort($exts, fn($a,$b)=>$b['bytes']<=>$a['bytes']);
        usort($this->largest, fn($a,$b)=>$b['size']<=>$a['size']);
        sort($this->offenders);
        sort($this->danger);

        return [
            'counts'   => ['files'=>$this->files,'dirs'=>$this->dirs,'total_bytes'=>$this->totalBytes,'max_depth'=>$this->maxDepth],
            'extstats' => $exts,
            'largest'  => array_slice($this->largest,0,20),
            'offenders'=> $this->offenders,
            'dangerous'=> $this->danger,
            'errors'   => $this->errors,
        ];
    }
}

/* ---------- scan & assemble ---------- */
try {
    $scanner = new FSScan($cfg);
    $t0 = microtime(true);

    $tree = $scanner->scanTree($cfg['base_path'], 0);

    $meta = [
        'scan_timestamp'   => date('c'),
        'timezone'         => $cfg['timezone'],
        'base_path'        => $cfg['base_path'],
        'execution_time_ms'=> round((microtime(true)-$t0)*1000),
        'generator'        => 'QuickDocRootJSON v2.5',
        'mode'             => $cfg['mode'],
        'hash_mode'        => $cfg['hash_mode'],
        'max_depth'        => $cfg['max_depth'],
        'since'            => $cfg['since'] ?: null,
        'limit_items'      => $cfg['limit_items'],
    ];

    $result = [
        'meta'  => $meta,
        'stats' => [
            'total_files'        => $scanner->files,
            'total_directories'  => $scanner->dirs,
            'total_size_bytes'   => $scanner->totalBytes,
            'max_depth'          => $scanner->maxDepth,
            'total_items'        => $scanner->files + $scanner->dirs
        ],
        'structure' => $tree,
    ];

    // modes / transforms
    if ($cfg['mode'] === 'summary') {
        $result['summary'] = $scanner->summary();
        unset($result['structure']); // lighter
    } elseif ($cfg['mode'] === 'flat') {
        $result['flat'] = $scanner->flat;
        unset($result['structure']);
    } elseif ($cfg['mode'] === 'offenders') {
        $sum = $scanner->summary();
        $result = ['meta'=>$meta,'offenders'=>$sum['offenders'],'dangerous'=>$sum['dangerous'],'errors'=>$sum['errors']];
    } elseif ($cfg['mode'] === 'diff') {
        $sum = $scanner->summary();
        $result['summary'] = $sum;
        $diffFile = $cfg['diff_file'];
        if ($diffFile && is_file($diffFile)) {
            $prev = json_decode(file_get_contents($diffFile), true);
            $prevFlat = [];
            foreach (($prev['flat'] ?? []) as $r) { $prevFlat[$r['path']] = $r; }
            $nowFlat = [];
            foreach ($scanner->flat as $r) { $nowFlat[$r['path']] = $r; }

            $added = array_values(array_diff(array_keys($nowFlat), array_keys($prevFlat)));
            $removed = array_values(array_diff(array_keys($prevFlat), array_keys($nowFlat)));
            $changed = [];
            foreach ($nowFlat as $p=>$r) {
                if (!isset($prevFlat[$p])) continue;
                $a=$prevFlat[$p]; $b=$r;
                if (($a['size'] ?? null) !== ($b['size'] ?? null) || ($a['mtime'] ?? null) !== ($b['mtime'] ?? null)) {
                    $changed[]=$p;
                }
            }
            $result['diff'] = ['added'=>$added,'removed'=>$removed,'changed'=>$changed];
        } else {
            $result['diff'] = ['error'=>'diff_file_missing_or_invalid'];
        }
    }

    // optional snapshot save
    if ($cfg['save_snapshot']) {
        $snapDir = $cfg['base_path'].'/var/reports';
        @mkdir($snapDir, 0775, true);
        $snapFile = $snapDir.'/docroot_snapshot_'.date('Ymd_His').'.json';
        $toSave = $result;
        if (!($toSave['flat'] ?? null)) $toSave['flat'] = $scanner->flat; // keep flat in snapshot for diffs
        file_put_contents($snapFile, json_encode($toSave, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        $result['snapshot_saved'] = basename($snapFile);
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    if (!$isCli) http_response_code(500);
    echo json_encode([
        'error'=>true,'message'=>$e->getMessage(),
        'trace'=>$isCli ? $e->getTraceAsString() : null,
        'timestamp'=>date('c')
    ], JSON_PRETTY_PRINT);
    if ($isCli) exit(1);
}
