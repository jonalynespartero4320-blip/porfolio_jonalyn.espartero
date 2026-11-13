
<?php

class UserController{

public static function updatePermission(){

global $pdo;

$user_id=$_POST['user_id'];
$perm=$_POST['permission'];

$pdo->prepare("DELETE FROM permissions WHERE user_id=?")->execute([$user_id]);
$pdo->prepare("INSERT INTO permissions(user_id,permission) VALUES(?,?)")->execute([$user_id,$perm]);

header("Location: ".BASE_URL."/admin");
}

}
