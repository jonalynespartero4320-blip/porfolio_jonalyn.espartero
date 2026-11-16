<?php
declare(strict_types=1);
$root=dirname(__DIR__);$out=$root.'/browser-demo/health';
$routes=['customer','schedule','booking-confirmation','cancel-appointment','login','admin','manage-admins'];
if(($argv[1]??'')==='--render') {
 date_default_timezone_set('Asia/Singapore');
 require $root.'/.static-export-ims/config/database.php';
 class HealthStatement extends DemoStatement {
  public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0): mixed { return ['id'=>1,'full_name'=>'Demo Customer','gender'=>'Other','company'=>'Sample Company','job'=>'Sample Role','date'=>date('Y-m-d'),'period'=>'AM','reference'=>'DEMO-REFERENCE','status'=>'confirmed','arrival_status'=>'not_arrived','created_at'=>date('Y-m-d H:i:s')]; }
 }
 class HealthPDO extends DemoPDO { public function prepare(string $query,array $options=[]):PDOStatement|false{return new HealthStatement();} }
 $db=new HealthPDO();$route=$argv[2];$error='';$me=in_array($route,['admin','manage-admins'])?['id'=>1,'name'=>'Demo Administrator','username'=>'demo','role'=>'super_admin']:false;
 $_SESSION=['customer_draft'=>['full_name'=>'Demo Customer','gender'=>'Other','company'=>'Sample Company','job'=>'Sample Role'],'customer_receipt'=>1,'booking_last_activity'=>time()];$_POST=[];$_GET=[];define('BOOKING_IDLE_SECONDS',900);
 function e(mixed $value):string{return htmlspecialchars((string)($value??''),ENT_QUOTES,'UTF-8');}
 function url(string $route=''):string{return str_starts_with($route,'assets/')?'./'.$route:'./'.(explode('?',$route)[0]?:'customer').'.html';}
 function csrf():string{return '';}
 function is_staff($account):bool{return (bool)$account;}
 function customer_date_valid($date):bool{return false;}
 function customer_availability(...$args):array{return ['AM'=>['open'=>true,'remaining'=>250],'PM'=>['open'=>true,'remaining'=>250]];}
 function customer_print_name($name):string{return $name;}
 $source=file_get_contents($root.'/Health/public/index.php');$source=substr($source,strpos($source,'$titles='));
 $source=str_replace("dirname(__DIR__).'/app/",var_export($root.'/Health/app/',true).".'",$source);
 eval($source);exit;
}
if(!is_dir($out.'/assets'))mkdir($out.'/assets',0777,true);
foreach(glob($root.'/Health/public/assets/*.css') as $css){$text=file_get_contents($css);$text=preg_replace('~url\([^)]*\.(?:png|jpg|svg)[^)]*\)~i','none',$text);file_put_contents($out.'/assets/'.basename($css),$text);}
foreach($routes as $route){$pipes=[];$process=proc_open(escapeshellarg(PHP_BINARY).' -d display_errors=stderr '.escapeshellarg(__FILE__).' --render '.escapeshellarg($route),[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);$html=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);if($errors||$exit){fwrite(STDERR,$route.': '.$errors);exit(1);}
 $html=preg_replace('~<script\b[^>]*>.*?</script>~is','',$html);
 $html=str_replace('</head>','<link rel="stylesheet" href="../shared/demo.css?v=all-1"></head>',$html);
 $html=str_replace('</body>','<script src="../shared/store.js"></script><script src="local.js"></script></body>',$html);
 // Repair the stray quote present in the original head without changing the layout.
 $html=str_replace('</title>">','</title>',$html);
 file_put_contents($out.'/'.$route.'.html',$html);echo 'Exported Health '.$route."\n";
}
file_put_contents($out.'/index.html','<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=customer.html"><a href="customer.html">Open booking</a>');
