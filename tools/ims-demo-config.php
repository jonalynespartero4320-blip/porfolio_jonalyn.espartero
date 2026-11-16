<?php
$pages = [
 'index'=>'modules/Auth/views/login.php',
 'dashboard'=>'modules/Pages/dashboard/index.php',
 'equipment'=>'modules/Pages/equipment/equipment.php',
 'supplies'=>'modules/Pages/supplies/supplies.php',
 'assign'=>'modules/Pages/assign/assign.php',
 'borrowed'=>'modules/Pages/borrowed/borrowed.php',
 'subscription'=>'modules/Pages/subscription/subscription.php',
 'transmittal'=>'modules/Pages/transmittal/transmittal.php',
 'service-request'=>'modules/Pages/request-service/service-request.php',
 'equipment-request'=>'modules/Pages/request-equipment/request-equipment.php',
 'masterlist'=>'modules/Pages/masterlist/masterlist.php',
 'reports'=>'modules/Reports/views/index.php',
];
$routeMap = ['login'=>'index','logout'=>'index','ictd'=>'dashboard','ictd/dashboard'=>'dashboard','ictd/equipment'=>'equipment','ictd/supplies'=>'supplies','ictd/assign'=>'assign','ictd/borrowed'=>'borrowed','ictd/subscription'=>'subscription','ictd/transmittal'=>'transmittal','ictd/requests/service'=>'service-request','ictd/requests/equipment'=>'equipment-request','ictd/masterlist'=>'masterlist','ictd/reports'=>'reports'];
$routeMap['ictd/equipment/manage']='equipment';
$routeMap['ictd/masterlists']='masterlist';
foreach (['equipment','supplies','assign','borrowed','subscription','transmittal'] as $module) {
    $archiveView='modules/Pages/'.$module.'/'.$module.'-archive.php';
    if(is_file($root.'/ims/'.$archiveView)){$pages[$module.'-archive']=$archiveView;$routeMap['ictd/'.$module.'/archive']=$module.'-archive';}
}
foreach(['bidding','maintenance','service-report'] as $module){$pages[$module]='modules/Pages/'.$module.'/'.$module.'.php';$pages[$module.'-archive']='modules/Pages/'.$module.'/'.$module.'-archive.php';$routeMap['ictd/'.$module]=$module;$routeMap['ictd/'.$module.'/archive']=$module.'-archive';}
$pages['assign-history']='modules/Pages/assign/assign-history.php';$routeMap['ictd/assign/history']='assign-history';
