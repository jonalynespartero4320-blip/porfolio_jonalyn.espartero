<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Singapore');
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies','1');
session_name('HEALTHSESSID');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; object-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') header('Strict-Transport-Security: max-age=31536000');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
define('BASE', PHP_SAPI === 'cli-server' ? '' : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/.'));
function url(string $path = ''): string { return BASE . '/' . ltrim($path, '/'); }
function e(mixed $value): string { return htmlspecialchars(is_scalar($value)?(string)$value:'', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function go(string $path): never { header('Location: ' . url($path), true, 303); exit; }
function csrf(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ??= bin2hex(random_bytes(32))) . '">' . (function_exists('booking_guard_field') ? booking_guard_field() : ''); }
function flash(string $message): void { $_SESSION['flash'] = $message; }
function is_staff(array|false $account): bool { return $account && in_array($account['role'], ['admin','super_admin'], true); }
function db(): PDO {
    static $db;
    if ($db) return $db;
    if(is_file(dirname(__DIR__).'/storage/database-migration.lock')) throw new PDOException('Database migration in progress.');
    $configPath=dirname(__DIR__).'/config/database.php';
    $config=is_file($configPath)?require $configPath:['driver'=>'sqlite'];
    if (!is_array($config) || !in_array($config['driver']??'', ['sqlite','mysql'],true)) throw new RuntimeException('Invalid database driver configuration.');
    if ($config['driver']==='mysql') {
        $host=(string)($config['host']??'127.0.0.1'); $database=(string)($config['database']??'health_appointments'); $port=(int)($config['port']??3306);
        if (!preg_match('/^[a-zA-Z0-9._:-]+$/D',$host) || !preg_match('/^[a-zA-Z0-9_]+$/D',$database) || $port<1 || $port>65535) throw new RuntimeException('Invalid database connection configuration.');
        $db=new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",(string)($config['username']??''),(string)($config['password']??''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        $db->exec("SET time_zone = '+08:00'");
        return $db; // Import database/health.sql first; no web-request DDL for MySQL.
    }
    $db = new PDO('sqlite:' . dirname(__DIR__) . '/storage/health.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, username TEXT NOT NULL COLLATE NOCASE UNIQUE, password TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'patient');
    CREATE TABLE IF NOT EXISTS doctors (id INTEGER PRIMARY KEY, name TEXT NOT NULL, specialty TEXT NOT NULL, initials TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS appointments (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id), doctor_id INTEGER NOT NULL REFERENCES doctors(id), date TEXT NOT NULL, time TEXT NOT NULL, reason TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
    CREATE UNIQUE INDEX IF NOT EXISTS reserved_slot ON appointments(doctor_id,date,time) WHERE status IN ('pending','confirmed');
    CREATE TABLE IF NOT EXISTS login_attempts (identity TEXT PRIMARY KEY, attempts INTEGER NOT NULL, expires INTEGER NOT NULL);");
    $db->exec("CREATE TABLE IF NOT EXISTS customer_bookings (
        id INTEGER PRIMARY KEY, full_name TEXT NOT NULL, gender TEXT NOT NULL, company TEXT NOT NULL, job TEXT NOT NULL,
        date TEXT NOT NULL, period TEXT NOT NULL CHECK(period IN ('AM','PM')), reference TEXT NOT NULL UNIQUE,
        request_token TEXT NOT NULL UNIQUE, status TEXT NOT NULL DEFAULT 'confirmed' CHECK(status IN ('confirmed','cancelled','completed')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
        CREATE INDEX IF NOT EXISTS customer_capacity ON customer_bookings(date,period,status);
        CREATE TABLE IF NOT EXISTS reference_sequences (period TEXT PRIMARY KEY, last_number INTEGER NOT NULL);");
    $db->exec('CREATE TABLE IF NOT EXISTS daily_reference_sequences (date TEXT NOT NULL, period TEXT NOT NULL, last_number INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(date,period))');
    // Additive migrations preserve existing accounts and appointments.
    $columns=array_column($db->query('PRAGMA table_info(users)')->fetchAll(),'name');
    if (!in_array('username',$columns,true)) $db->exec('ALTER TABLE users ADD COLUMN username TEXT COLLATE NOCASE');
    if(in_array('email',$columns,true)) {
        // Preserve IDs/passwords and foreign-key references while removing legacy email data.
        $db->exec('PRAGMA foreign_keys=OFF');
        try {
            $db->exec('BEGIN IMMEDIATE');
            $db->exec("CREATE TABLE users_without_email (id INTEGER PRIMARY KEY, name TEXT NOT NULL, username TEXT NOT NULL COLLATE NOCASE UNIQUE, password TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'patient');
                INSERT INTO users_without_email(id,name,username,password,role) SELECT id,name,COALESCE(NULLIF(username,''),'legacy-' || id),password,role FROM users;
                DROP TABLE users;
                ALTER TABLE users_without_email RENAME TO users;");
            $db->exec('COMMIT');
        } catch(Throwable $ex) { $db->exec('ROLLBACK'); throw $ex; }
        finally { $db->exec('PRAGMA foreign_keys=ON'); }
    }
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS unique_username ON users(username COLLATE NOCASE)');
    foreach (['appointments','customer_bookings'] as $table) {
        $columns=array_column($db->query('PRAGMA table_info('.$table.')')->fetchAll(),'name');
        if (!in_array('arrived_at',$columns,true)) $db->exec('ALTER TABLE '.$table.' ADD COLUMN arrived_at TEXT');
        if (!in_array('arrived_by',$columns,true)) $db->exec('ALTER TABLE '.$table.' ADD COLUMN arrived_by INTEGER REFERENCES users(id)');
    }
    $customerColumns=array_column($db->query('PRAGMA table_info(customer_bookings)')->fetchAll(),'name');
    foreach(['cancellation_reason','cancelled_at','cancellation_source'] as $column) {
        if(!in_array($column,$customerColumns,true)) $db->exec('ALTER TABLE customer_bookings ADD COLUMN '.$column.' TEXT');
    }
    if (!(int)$db->query('SELECT COUNT(*) FROM doctors')->fetchColumn()) {
        $q = $db->prepare('INSERT INTO doctors(name,specialty,initials) VALUES(?,?,?)');
        foreach ([['Dr. Olivia Chen','General Medicine','OC'],['Dr. James Wilson','Cardiology','JW'],['Dr. Amara Santos','Family Medicine','AS']] as $d) $q->execute($d);
    }
    return $db;
}
function user(): array|false {
    if (empty($_SESSION['uid'])) return false;
    $q=db()->prepare('SELECT id,name,username,role FROM users WHERE id=?'); $q->execute([$_SESSION['uid']]); return $q->fetch();
}
function slots(): array { return ['09:00','09:30','10:00','10:30','11:00','11:30','13:00','13:30','14:00','14:30','15:00','15:30','16:00']; }
function valid_slot(string $date, string $time): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)) return false;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date && $date >= date('Y-m-d') && $date <= date('Y-m-d', strtotime('+90 days')) && (int)$d->format('N') <= 5 && in_array($time, slots(), true) && strtotime("$date $time") > time();
}
