<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/BackupZipPreflight.php';
use EpaperSaas\Core\BackupZipPreflight;
if(!class_exists(ZipArchive::class)){fwrite(STDERR,"FAIL ZIP extension is unavailable\n");exit(1);}
$tmp=sys_get_temp_dir().'/epdi-zip-test-'.bin2hex(random_bytes(8));
$zipfile=$tmp.'.zip';
$z=new ZipArchive();
if($z->open($zipfile,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)exit(1);
$z->addFromString('approot/app/bootstrap.php','<?php');
$z->addFromString('app-root/storage/media/tenant/edition/page-01.webp','fake-webp');
$z->addFromString('app-root/storage/uploads/tenant/edition/source.pdf','fake-pdf');
$z->addFromString('app-root/storage/clips/tenant/clip.webp','fake-clip');
$z->addFromString('backup/database.sql','CREATE TABLE test(id int);');
$z->close();
$checks=0;$fails=0;
$ok=static function(bool $yes,string $name)use(&$checks,&$fails):void{
    $checks++;if(!$yes){fwrite(STDERR,"FAIL ZIP: $name\n");$fails++;}
};
try{
    $s=BackupZipPreflight::inspect($zipfile);
    $ok($s['archive_structure_ok'],'normal ZIP safe');
    $ok($s['counts']['media_files']===1,'media detected');
    $ok($s['counts']['original_pdf_files']===1,'PDF detected');
    $ok($s['counts']['clip_files']===1,'clip detected');
    $ok($s['counts']['database_export_candidates']===1,'possible SQL detected');
    $ok($s['checked_member_contents']===false,'metadata scan default');
    $ok($s['restore_test_verified']===false,'no false restore certification');
    $s2=BackupZipPreflight::inspect($zipfile,true);
    $ok($s2['archive_structure_ok']&&$s2['checked_member_contents'],'all members readable');
    $z=new ZipArchive();$z->open($zipfile);$z->addFromString('../danger.txt','fake');$z->close();
    $unsafe=BackupZipPreflight::inspect($zipfile);
    $ok(!$unsafe['archive_structure_ok']&&$unsafe['suspicious_entries']>=1,'traversal rejected');
}finally{@unlink($zipfile);}
if($fails)exit(1);
echo "PASS ZIP backup preflight ($checks checks)\n";
