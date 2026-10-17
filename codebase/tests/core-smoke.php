<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/Uuid.php';require dirname(__DIR__).'/app/Services/AnalyticsCatalog.php';
use EpaperSaas\Core\Uuid;use EpaperSaas\Services\AnalyticsCatalog;
$fail=0;$check=function($ok,$name)use(&$fail){echo($ok?'PASS':'FAIL')." — $name\n";if(!$ok)$fail++;};
for($i=0;$i<50;$i++){$u=Uuid::v7();$check((bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',$u),'UUIDv7 format '.$i);$check(Uuid::fromBinary(Uuid::toBinary($u))===$u,'UUID binary roundtrip '.$i);}
$check(AnalyticsCatalog::valid('page_view'),'canonical page_view event');$check(!AnalyticsCatalog::valid('reader_page_view'),'unknown analytics event rejected');exit($fail?1:0);
