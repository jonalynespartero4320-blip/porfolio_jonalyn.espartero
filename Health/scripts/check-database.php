<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
try {
    $db=db();
    echo 'Connected using '.$db->getAttribute(PDO::ATTR_DRIVER_NAME).".\n";
    foreach(['users','doctors','appointments','customer_bookings','reference_sequences','daily_reference_sequences','login_attempts'] as $table) {
        echo $table.': '.$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()." rows\n";
    }
    echo "Database tables are accessible.\n";
} catch(Throwable $ex) { fwrite(STDERR,"Database check failed. Verify config/database.php, the PDO driver, and the SQL import.\n"); exit(1); }
