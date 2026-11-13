<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
// Read credentials from stdin; never embed a default password in application code.
$input=json_decode(stream_get_contents(STDIN),true);
if(!is_array($input) || !is_string($input['username']??null) || !is_string($input['password']??null)) { fwrite(STDERR,"Provide JSON containing username and password on stdin.\n"); exit(1); }
$username=strtolower(trim($input['username'])); $password=$input['password'];
if(!preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/',$username) || strlen($password)<12 || strlen($password)>72) { fwrite(STDERR,"Invalid username or password length.\n"); exit(1); }
try {
    db()->prepare("INSERT INTO users(name,username,password,role) VALUES(?,?,?,'super_admin')")->execute(['Super Administrator',$username,password_hash($password,PASSWORD_DEFAULT)]);
    echo "Super administrator created. Sign in through /public/login.\n";
} catch(PDOException $e) { fwrite(STDERR,"Account not created. Username may already exist; existing accounts were not changed.\n"); exit(1); }
