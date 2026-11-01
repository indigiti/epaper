<?php
require dirname(__DIR__).'/app/Core/Uuid.php';
require dirname(__DIR__).'/app/Core/DomainTools.php';
use Digiti\Core\Uuid;use Digiti\Core\DomainTools;
$fail=[];$check=function($ok,$name)use(&$fail){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fail[]=$name;};
$u=Uuid::v7();$check((bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',$u),'UUIDv7 format/version/variant');$check(Uuid::fromBinary(Uuid::toBinary($u))===$u,'UUID binary round-trip');$check(DomainTools::normalize('Example.COM.:443')==='example.com','host lowercase/port/trailing-dot normalization');try{DomainTools::normalize('bad host');$bad=false;}catch(Throwable){$bad=true;}$check($bad,'invalid host rejected');exit($fail?1:0);
