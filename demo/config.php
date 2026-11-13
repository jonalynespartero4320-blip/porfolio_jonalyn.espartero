<?php
// These databases are disposable portfolio sandboxes, separate from source systems.
function demo_database_name(string $project): string {
    $names = ['ims' => 'portfolio_demo_ims', 'bjmp' => 'portfolio_demo_bjmp', 'pdad' => 'portfolio_demo_pdad', 'osca' => 'portfolio_demo_osca', 'rtc' => 'portfolio_demo_rtc'];
    return $names[$project];
}
function demo_pdo(?string $project = null): PDO {
    $dsn = 'mysql:host=' . (getenv('DEMO_DB_HOST') ?: '127.0.0.1') . ';charset=utf8mb4';
    if ($project !== null) $dsn .= ';dbname=' . demo_database_name($project);
    return new PDO($dsn, getenv('DEMO_DB_USER') ?: 'root', getenv('DEMO_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
