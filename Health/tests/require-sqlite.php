<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
// HTTP fixtures clean up SQLite directly; do not leave test data in a MySQL app.
$testConfigPath=dirname(__DIR__).'/config/database.php';
$testConfig=is_file($testConfigPath)?require $testConfigPath:['driver'=>'sqlite'];
if(($testConfig['driver']??'')!=='sqlite') {
    fwrite(STDERR,"This integration suite requires the local SQLite app configuration. MySQL fixture cleanup is not implemented; no HTTP requests were sent.\n"); exit(1);
}
