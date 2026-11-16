<?php
declare(strict_types=1);
$root=dirname(__DIR__);$out=$root.'/browser-demo/verification';
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function asset_url($path){return './'.$path;}
function endpoint_url($path){return '#';}
foreach(['assets/css','assets/images'] as $dir){if(!is_dir($out.'/'.$dir))mkdir($out.'/'.$dir,0777,true);foreach(glob($root.'/verification_system/'.$dir.'/*') as $asset){if(in_array(pathinfo($asset,PATHINFO_EXTENSION),['css','svg'])){$content=file_get_contents($asset);if(pathinfo($asset,PATHINFO_EXTENSION)==='css')$content=preg_replace('~url\([\'"]?\.\./images/[^)]+\)~i','none',$content);file_put_contents($out.'/'.$dir.'/'.basename($asset),$content);}}}
foreach(['professional.png','home.png','food.png','map.png'] as $image)copy($root.'/verification_system/'.$image,$out.'/'.$image);
ob_start();require $root.'/verification_system/includes/footer.php';$footer=ob_get_clean();
$sources=['index'=>'index.php','professional'=>'public/pdad_verifyid/index.php','home'=>'public/osca_verifyid/index.php','food'=>'public/gad_verifyid/index.php'];
foreach($sources as $slug=>$file){$source=file_get_contents($root.'/verification_system/'.$file);$source=preg_replace('~^<\?php.*?\?>~s','',$source);$source=preg_replace_callback('~<\?php\s+(?:require|include)[^?]*footer\.php[^?]*\?>~s',static fn()=>$footer,$source);$source=preg_replace('~<\?php\s+(?:require|include)[^?]*\?>~s','',$source);ob_start();eval('?>'.$source);$html=ob_get_clean();$html=preg_replace('~<script\b[^>]*>.*?</script>~is','',$html);
 foreach(['public/pdad_verifyid/'=>'professional.html','public/osca_verifyid/'=>'home.html','public/gad_verifyid/'=>'food.html'] as $from=>$to)$html=str_replace($from,$to,$html);
 $html=str_replace('href="./"','href="index.html"',$html);
 $html=str_replace('</head>','<link rel="stylesheet" href="../shared/demo.css?v=all-1"></head>',$html);
 $html=str_replace('</body>','<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="../shared/store.js"></script><script src="local.js"></script></body>',$html);
 file_put_contents($out.'/'.$slug.'.html',$html);echo 'Exported verification '.$slug."\n";
}
