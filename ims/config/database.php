<?php
require_once dirname(__DIR__, 2) . '/demo/config.php';
try { $pdo = demo_pdo('ims'); } catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(503);
    exit('Demo database unavailable. Run php demo/setup.php from the portfolio folder.');
}
