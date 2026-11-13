<?php

session_name('portfolio_bjmp');
session_start();

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/config/app.php';
require ROOT_PATH . '/config/database.php';
require ROOT_PATH . '/helpers/functions.php';   // MUST BE HERE
require ROOT_PATH . '/routes/web.php';