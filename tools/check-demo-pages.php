<?php
declare(strict_types=1);
$root=dirname(__DIR__);libxml_use_internal_errors(true);$count=0;
$required=[
 'ims/index.html'=>['//input[@name="identifier"]','//input[@name="password"]'],
 'ims/equipment.html'=>['//table[@id="equipmentTable"]/tbody','//a[@data-sidebar-link and @href="equipment.html"]','//a[@data-sidebar-link and @href="equipment.html?archived=1"]'],
 'ims/supplies.html'=>['//table[@id="suppliesTable"]/tbody'],
 'ims/assign.html'=>['//table[@id="assignTable"]/tbody'],
 'ims/borrowed.html'=>['//table[@id="borrowedTable"]/tbody'],
 'health/customer.html'=>['//input[@id="full_name"]','//select[@id="gender"]','//input[@name="company"]','//input[@name="job"]'],
 'health/schedule.html'=>['//form[@id="availability-form"]','//form[@id="customer-schedule"]','//input[@id="period-AM"]','//input[@id="period-PM"]','//div[@id="calendar-days"]'],
 'health/login.html'=>['//input[@name="username"]','//input[@name="password"]'],
 'health/admin.html'=>['//form[contains(@class,"appointment-search")]','//input[@id="filter-date"]'],
 'health/manage-admins.html'=>['//input[@id="admin-username"]','//input[@id="admin-password"]'],
 'verification/professional.html'=>['//form[@id="verifyForm"]','//select[@id="brgySelect"]','//input[@id="unique_id"]'],
 'verification/home.html'=>['//form[@id="verifyForm"]','//input[@id="senior_citizen_id"]'],
 'verification/food.html'=>['//form[@id="verifyForm"]','//input[@id="unique_id"]'],
];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/browser-demo',FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){if($file->getExtension()!=='html')continue;$count++;$html=file_get_contents($file->getPathname());$relative=str_replace('\\','/',substr($file->getPathname(),strlen($root.'/browser-demo/')));if(str_contains($html,'<?php')||str_contains($html,'Fatal error:'))throw new RuntimeException('Invalid export: '.$relative);$dom=new DOMDocument();$dom->loadHTML($html);$xpath=new DOMXPath($dom);foreach($required[$relative]??[] as $selector)if(!$xpath->query($selector)->length)throw new RuntimeException('Missing form control in '.$relative.': '.$selector);
 foreach($xpath->query('//*[@href or @src]') as $element)foreach(['href','src'] as $attribute){if(!$element->hasAttribute($attribute))continue;$value=html_entity_decode($element->getAttribute($attribute));if($value===''||preg_match('~^(?:https?:|data:|#|mailto:|tel:)~i',$value))continue;$path=preg_split('/[?#]/',$value)[0];if(!file_exists(dirname($file->getPathname()).'/'.$path))throw new RuntimeException('Missing link in '.$relative.': '.$value);}
 if(isset($required[$relative])){if(!str_contains($html,'../shared/store.js')||strpos($html,'../shared/store.js')>strpos($html,'src="local.js'))throw new RuntimeException('Storage script order is incorrect: '.$relative);}
}
$script=file_get_contents($root.'/script.js');preg_match_all("~(?:url|adminUrl):'(browser-demo/[^']+)'~",$script,$matches);if(count($matches[1])!==5)throw new RuntimeException('Expected four static projects and one admin link.');foreach($matches[1] as $link)if(!file_exists($root.'/'.$link))throw new RuntimeException('Broken project destination: '.$link);
echo 'PASS '.$count." static pages, required original form controls, local asset/navigation links, storage script order, and all portfolio destinations.\n";
