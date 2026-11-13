<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
echo "Admin name: "; $name=trim(fgets(STDIN));
echo "Admin username: "; $username=strtolower(trim(fgets(STDIN)));
echo "Password (12–72 characters; input is visible): "; $password=rtrim(fgets(STDIN), "\r\n");
if (!$name || !preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/',$username) || strlen($password)<12 || strlen($password)>72) { fwrite(STDERR,"Invalid name, username, or password.\n"); exit(1); }
try { db()->prepare("INSERT INTO users(name,username,password,role) VALUES(?,?,?,'admin')")->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT)]); echo "Admin created. Sign in through /login.\n"; }
catch (PDOException $e) { fwrite(STDERR,"Unable to create administrator. Username may already exist; verify database configuration.\n"); exit(1); }
