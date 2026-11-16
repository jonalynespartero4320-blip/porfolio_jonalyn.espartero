<?php
// Development-only exporter. Published output is HTML/CSS/JS; no PHP server is used.
declare(strict_types=1);
$root = dirname(__DIR__);
$system = in_array('--ims',$argv,true) || getenv('PORTFOLIO_EXPORT_SYSTEM') === 'ims' ? 'ims' : 'bjmp';
putenv('PORTFOLIO_EXPORT_SYSTEM='.$system);
$sourceFolder = $system === 'ims' ? 'ims' : 'bjmp_vaqs';
$out = $root . '/browser-demo/'.$system;
$temp = $root . '/.static-export-'.$system;
$pages = [
    'index' => 'modules/Auth/views/login.php',
    'dashboard' => 'modules/Dashboard/views/index.php',
    'queue' => 'modules/Pages/queuing/queue.php',
    'queue-monitor' => 'modules/Pages/queuing/queue-monitor.php',
    'queue-board' => 'modules/Pages/queuing/queue-board.php',
    'visitor-registration' => 'modules/Pages/visitor-main/visitor-registration/visitor-registration.php',
    'visitor-management' => 'modules/Pages/visitor-main/visitor-management/visitor-management.php',
    'pdl-management' => 'modules/Pages/pdl-management/pdl-management.php',
    'pdl-form' => 'modules/Pages/pdl-management/pdl-form.php',
    'id-issuance' => 'modules/Pages/id-issuance/id-issuance.php',
    'reports' => 'modules/Reports/views/index.php',
    'masterlist' => 'modules/Dashboard/views/masterlist.php',
    'admin' => 'modules/Users/views/admin.php',
    'activity-logs' => 'modules/Logs/activity_logs.php',
    'documentation-process-flow' => 'modules/Reports/views/documentation.php',
    'special-cases' => 'modules/Pages/queuing/special-cases.php',
    'change-password' => 'modules/Auth/views/change_password.php',
    'register' => 'modules/Auth/views/register.php',
    'reset-request' => 'modules/Auth/views/reset_request.php',
];
$routeMap = [];
if ($system === 'ims') require __DIR__.'/ims-demo-config.php';
if (($argv[1] ?? '') === '--render') {
    define('ROOT_PATH', $temp);
    define('BASE_URL', '.');
    $_SERVER['REQUEST_URI'] = '/' . $argv[2];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SESSION = ['user' => ['id'=>1,'name'=>'Demo Administrator','username'=>'demo','role_id'=>1,'role_name'=>'Super Admin']];
    require $temp . '/config/database.php';
    require $temp . '/helpers/functions.php';
    require $temp . '/' . $pages[$argv[2]];
    exit;
}
foreach ([$out,$temp] as $dir) if (!is_dir($dir)) mkdir($dir,0777,true);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/'.$sourceFolder,FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $relative = str_replace('\\','/',substr($file->getPathname(),strlen($root . '/'.$sourceFolder.'/')));
    if (pathinfo($relative,PATHINFO_EXTENSION) !== 'php' || preg_match('~uploads|config/database|helpers/functions~i',$relative)) continue;
    $target = $temp . '/' . $relative;
    if (!is_dir(dirname($target))) mkdir(dirname($target),0777,true);
    $source = file_get_contents($file->getPathname());
    $source = str_replace('session_start();','/* No server session in static export. */',$source);
    $source = preg_replace('~filemtime\(ROOT_PATH\s*\.\s*\x27[^\x27]+\x27\)~','1',$source);
    $source = preg_replace('~(?:require_once|require|include_once|include)\s+dirname\(__DIR__,\s*\d+\)\s*\.\s*[\'"]/demo/home-link.php[\'"]\s*;~','',$source);
    file_put_contents($target,$source);
}
foreach (['config','helpers'] as $dir) if (!is_dir($temp.'/'.$dir)) mkdir($temp.'/'.$dir,0777,true);
file_put_contents($temp.'/config/database.php', <<<'PHP'
<?php
class DemoStatement extends PDOStatement {
    public function getIterator(): Iterator { return new ArrayIterator([]); }
    public function execute(?array $params = null): bool { return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed { return false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
    public function fetchColumn(int $column = 0): mixed { return 0; }
    public function rowCount(): int { return 0; }
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool { return true; }
}
class DemoPDO extends PDO {
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return 'sqlite'; }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false { return new DemoStatement(); }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new DemoStatement(); }
    public function exec(string $statement): int|false { return 0; }
    public function lastInsertId(?string $name = null): string|false { return '1'; }
}
$pdo = new DemoPDO();
PHP);
file_put_contents($temp.'/helpers/functions.php', <<<'PHP'
<?php
function current_user() { return $_SESSION['user']; }
function require_login() {}
function require_permission($p) {}
function require_any_permission($p) {}
function require_super_admin() {}
function is_super_admin() { return true; }
function is_admin() { return true; }
function can($p) { return true; }
function normalize_permission_name($p) { return strtolower(trim($p)); }
function e($s) { return htmlspecialchars((string)($s ?? ''),ENT_QUOTES,'UTF-8'); }
function show_notification(...$args) {}
function log_route_activity(...$args) {}
function log_activity(...$args) {}
function ensure_record_history_table(...$args) {}
function permission_catalog() { return []; }
function ensure_permission_catalog(...$args) {}
function role_permission_profile_ids(...$args) { return []; }
function db_table_exists(...$args) { return true; }
function db_column_exists(...$args) { return true; }
function ensure_record_scope_schema(...$args) {}
function ensure_rbac_schema(...$args) {}
function ensure_password_reset_schema(...$args) {}
function ensure_activity_logs_schema(...$args) {}
function record_scope_current_user_id(...$args) { return 1; }
function user_can_view_all_records(...$args) { return true; }
function user_department_scope_ids(...$args) { return []; }
function record_scope_column(...$args) { return null; }
function apply_record_scope(...$args) {}
function request_mine_only() { return false; }
function apply_mine_only_scope(...$args) {}
PHP);
$assets = ['css/main.css','css/table.css','css/select.css','images/demo-mark.svg','images/demo-scene.svg'];
foreach ($assets as $asset) {
    $target = $out.'/assets/'.$asset;
    if (!is_dir(dirname($target))) mkdir(dirname($target),0777,true);
    $content = file_get_contents($root.'/'.$sourceFolder.'/public/assets/'.$asset);
    // Plain page background; keep photos and ID preview assets separate.
    if (str_ends_with($asset,'.css')) {
        $content = preg_replace('~background\s*:\s*url\([\'"]?\.\./images/bg\.png[\'"]?\)[^;]*;~i','background: #f4f6f9;',$content);
    }
    file_put_contents($target,$content);
}
$failed = [];
foreach ($pages as $slug=>$view) {
    $command = escapeshellarg(PHP_BINARY).' -d display_errors=stderr '.escapeshellarg(__FILE__).' --render '.escapeshellarg($slug);
    $pipes = [];
    $process = proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    $html = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
    if ($exit !== 0) { $failed[] = $slug; fwrite(STDERR,$slug.': '.$errors."\n"); continue; }
    if ($errors !== '') fwrite(STDERR,$slug.': '.$errors."\n");
    // Server handlers are replaced by one localStorage adapter, not called remotely.
    $html = preg_replace('~<script\b[^>]*>.*?</script>~is','',$html);
    $html = preg_replace('~body\.auth-screen\s*\{.*?\}~s','body.auth-screen { min-height: 100vh; background: #f4f6f9; }',$html);
    $html = preg_replace('~url\([\'"]?[^)]*demo-scene\.svg[\'"]?\)~i','none',$html);
    $html = preg_replace('~\s+on(?:click|change|submit)="[^"]*"~i','',$html);
    if ($system === 'ims') {
        $html = preg_replace_callback('~(href|action)="\./([^"?#]+)([^" ]*)"~i',function($match) use ($routeMap,$pages) {
            if (str_starts_with($match[2],'assets/')) return $match[0];
            $route = $routeMap[$match[2]] ?? (isset($pages[$match[2]]) ? $match[2] : null);
            $parts = $route ? explode('?',$route,2) : [];
            return $match[1].'="'.($route ? $parts[0].'.html'.(isset($parts[1])?'?'.$parts[1]:'') : '#').$match[3].'"';
        },$html);
    }
    $html = preg_replace_callback('~(href|action)="\./([a-z][a-z0-9-]*)([^"/]*)"~i',function($match) use ($pages) {
        $route = $match[2] === 'logout' ? 'index' : $match[2];
        return $match[1].'="'.(isset($pages[$route]) ? $route.'.html' : '#').$match[3].'"';
    },$html);
    $html = preg_replace('~<link[^>]+(?:datatables|select2|sweetalert2)[^>]*>~i','',$html);
    $html = preg_replace('~(href|src)="\./(?:downloads/[^" ]+|demo/)"~','$1="#" data-local-unconverted="1"',$html);
    $html = preg_replace('~<source\b[^>]*src="\./assets/videos/[^\"]+"[^>]*>~i','',$html);
    if (!str_contains($html,'<html')) $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.($system==='ims'?'Inventory Management System':'ID & Queuing System').'</title></head><body>'.$html.'</body></html>';
    $html = str_replace('assets/css/main.css"','assets/css/main.css?v=plain-2"',$html);
    $html = str_replace('</head>','<link rel="stylesheet" href="local.css?v=plain-2"></head>',$html);
    $scripts = '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>'.($system==='ims'?'<script src="../shared/store.js"></script>':'').'<script src="local.js?v='.($system==='ims'?'buttons-2':'all-1').'"></script>';
    $html = str_replace('</body>','<link rel="stylesheet" href="local.css?v=plain-2">'.$scripts.'</body>',$html);
    if ($slug === 'index') {
        $html = str_replace('<body>','<body class="auth-screen">',$html);
        $html = str_replace('Protected by role-based access control','Browser demo: demo / Demo2026!',$html);
        $html = str_replace('Use your approved account credentials to open the ID & Queuing System.','Demo account: demo / Demo2026! — fictional data, stored on this device only.',$html);
    }
    file_put_contents($out.'/'.$slug.'.html',$html);
    echo 'Exported '.$slug."\n";
}
if ($failed) exit(1);
