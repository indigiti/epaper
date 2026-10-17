<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/RestoreReferenceAudit.php';
use EpaperSaas\Core\RestoreReferenceAudit as A;
$root=sys_get_temp_dir().'/epdi-db-storage-check-'.bin2hex(random_bytes(8));
mkdir($root.'/media/tenant/edition',0700,true);
file_put_contents($root.'/media/tenant/edition/page.webp','sample-page');
$path='media/tenant/edition/page.webp';
$fails=0;$checks=0;
$assert=static function(bool $ok,string $label)use(&$checks,&$fails):void{
 $checks++;if(!$ok){fwrite(STDERR,"FAIL: $label\n");$fails++;}
};
try{
 $assert(A::exists($root,$path),'existing media file accepted');
 $assert(!A::exists($root,'media/tenant/edition/lost.webp'),'missing file denied');
 $assert(!A::exists($root,'../secret.php'),'parent traversal denied');
 $assert(!A::exists($root,'media/../something'),'internal parent traversal denied');
 $assert(!A::exists($root,'/etc/passwd'),'absolute path denied');
 $assert(!A::exists($root,'media\\tenant\\edition\\page.webp'),'Windows path denied');
 $assert(!A::exists($root,"media/\0name"),'null byte denied');
 $assert(!A::exists($root,'media//tenant/edition/page.webp'),'duplicate separators denied');
 $assert(!A::exists($root,''),'empty path denied');
 symlink('/etc/passwd',$root.'/media/tenant/edition/escaped.webp');
 $assert(!A::exists($root,'media/tenant/edition/escaped.webp'),'symlink escape denied');
}finally{
 @unlink($root.'/media/tenant/edition/escaped.webp');
 @unlink($root.'/media/tenant/edition/page.webp');
 @rmdir($root.'/media/tenant/edition');
 @rmdir($root.'/media/tenant');@rmdir($root.'/media');@rmdir($root);
}
$src=(string)file_get_contents(dirname(__DIR__).'/bin/staging-restore-acceptance.php');
$assert(str_contains($src,"SET SESSION TRANSACTION READ ONLY"),'enforces a read-only database session');
$assert(str_contains($src,'EPDI_STAGING_RESTORE_ONLY'),'requires staging confirmation');
$assert(str_contains($src,'EPDI_STAGING_DB_NAME'),'checks exact staging database name');
$assert(str_contains($src,'assets_without_pages'),'checks file/edition referential integrity');
$assert(!str_contains($src,'INSERT INTO ')&&!str_contains($src,'DELETE FROM ')&&!str_contains($src,'UPDATE ep_'),'no mutating DML in script');
if($fails)exit(1);
echo "PASS isolated staging DB/media reference audit ($checks assertions)\n";
