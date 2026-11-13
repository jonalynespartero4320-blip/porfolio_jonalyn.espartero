<?php
$path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
define('BASE_URL', rtrim($path, '/'));
define('QR_SECRET_KEY', 'portfolio_demo_bjmp_qr_key');
