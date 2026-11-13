<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
// Local one-time migration. Administrative connection credentials arrive on stdin.
$root=dirname(__DIR__); $options=json_decode(stream_get_contents(STDIN),true);
if(!is_array($options)) { fwrite(STDERR,"Provide the local MySQL connection as JSON on stdin.\n"); exit(1); }
$host=$options['host']??'127.0.0.1'; $port=(int)($options['port']??3308);
if($host!=='127.0.0.1' || $port<1 || $port>65535 || is_file($root.'/config/database.php')) { fwrite(STDERR,"Migration requires an unconfigured local project. No existing config was changed.\n"); exit(1); }
$tables=['users','doctors','appointments','customer_bookings','reference_sequences','daily_reference_sequences','login_attempts'];
$source=new PDO('sqlite:'.$root.'/storage/health.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$source->exec('CREATE TABLE IF NOT EXISTS daily_reference_sequences(date TEXT NOT NULL, period TEXT NOT NULL,last_number INTEGER NOT NULL DEFAULT 0,PRIMARY KEY(date,period))');
$admin=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$options['username']??'root',$options['password']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$q=$admin->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?'); $q->execute(['health_appointments']);
if($q->fetchColumn()) { fwrite(STDERR,"Target database already exists. Refusing to overwrite it.\n"); exit(1); }
$lock=$root.'/storage/database-migration.lock'; $locked=false; $accountCreated=false; $configInstalled=false; $failed=false;
$appUsername='wellnest_health_app'; $appPassword=bin2hex(random_bytes(24));
try {
    $handle=fopen($lock,'x'); if(!$handle) throw new RuntimeException('Migration is already in progress.'); fclose($handle); $locked=true;
    // Drain ordinary short requests before taking the SQLite write lock.
    usleep(1000000);
    $backup=$root.'/storage/health-before-mysql-'.date('Ymd-His').'.sqlite';
    $source->exec('VACUUM INTO '.$source->quote($backup));
    $source->exec('BEGIN IMMEDIATE');
    $admin->exec(file_get_contents($root.'/database/health.sql'));
    $admin->exec('USE health_appointments');
    $admin->beginTransaction();
    // Only the schema's freshly created seeds exist in this new database.
    foreach(['reference_sequences','doctors','users'] as $table) $admin->exec('DELETE FROM '.$table);
    foreach($tables as $table) {
        $rows=$source->query('SELECT * FROM '.$table)->fetchAll();
        if($rows) {
            $columns=array_keys($rows[0]);
            $sql='INSERT INTO `'.$table.'` (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';
            $insert=$admin->prepare($sql); foreach($rows as $row) $insert->execute(array_values($row));
        }
        $key=match($table) {'reference_sequences'=>'period','login_attempts'=>'identity',default=>'id'};
        // Compare every field, not just row counts. Normalize PDO's numeric representations.
        $columns=array_column($source->query('PRAGMA table_info('.$table.')')->fetchAll(),'name');
        $select='SELECT `'.implode('`,`',$columns).'` FROM `'.$table.'` ORDER BY `'.$key.'`';
        $normalize=static fn(array $records): array=>array_map(static fn(array $record): array=>array_map(static fn($v)=>$v===null?null:(string)$v,$record),$records);
        if($normalize($source->query($select)->fetchAll())!==$normalize($admin->query($select)->fetchAll())) throw new RuntimeException('Record verification failed for '.$table);
        echo $table.': '.count($rows)." records copied and verified.\n";
    }
    $admin->commit();
    $admin->exec("INSERT IGNORE INTO reference_sequences(period,last_number) VALUES('AM',0),('PM',0)");
    // Dedicated app account cannot create/drop tables or grant permissions.
    $admin->exec('CREATE USER '.$admin->quote($appUsername)."@'127.0.0.1' IDENTIFIED BY ".$admin->quote($appPassword)); $accountCreated=true;
    $admin->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON health_appointments.* TO '.$admin->quote($appUsername)."@'127.0.0.1'");
    $app=new PDO("mysql:host=$host;port=$port;dbname=health_appointments;charset=utf8mb4",$appUsername,$appPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $app->query('SELECT COUNT(*) FROM customer_bookings')->fetchColumn();
    $config=['driver'=>'mysql','host'=>$host,'port'=>$port,'database'=>'health_appointments','username'=>$appUsername,'password'=>$appPassword];
    $temporary=$root.'/config/database.php.pending';
    file_put_contents($temporary,"<?php\n// Local MySQL connection. Keep this file private.\nreturn ".var_export($config,true).";\n",LOCK_EX);
    if(!rename($temporary,$root.'/config/database.php')) throw new RuntimeException('Unable to install database config.');
    $configInstalled=true;
    $source->exec('COMMIT');
    echo "Migration complete. App now uses MySQL on port $port. SQLite original and backup were retained.\n";
} catch(Throwable $ex) {
    if($admin->inTransaction()) $admin->rollBack();
    try { $source->exec('ROLLBACK'); } catch(Throwable) {}
    if($accountCreated && !$configInstalled) { try { $admin->exec('DROP USER '.$admin->quote($appUsername)."@'127.0.0.1'"); } catch(Throwable) {} }
    fwrite(STDERR,'Migration failed: '.($ex instanceof PDOException?'Database operation failed (SQLSTATE '.$ex->getCode().').':$ex->getMessage())." Original SQLite data remains intact.\n");
    $failed=true;
} finally { if($locked && is_file($lock)) unlink($lock); }
if($failed) exit(1);
