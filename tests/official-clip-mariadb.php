<?php
declare(strict_types=1);
/**
 * Disposable MariaDB fixture proving the exact SQL visibility predicate used
 * by OfficialClipService; never run against a live Cloudways database.
 */
if(getenv('EPDI_OFFICIAL_CI')!=='1')
    throw new RuntimeException('Official clip DB fixture requires explicit CI opt-in');
$host=getenv('EPDI_AI_TEST_DB_HOST')?:'127.0.0.1';
$user=getenv('EPDI_AI_TEST_DB_USER')?:'root';
$pass=getenv('EPDI_AI_TEST_DB_PASSWORD')?:'';
$pdo=new PDO('mysql:host='.$host.';port=3306;charset=utf8mb4',$user,$pass,[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);
$db='epdi_official_clip_fixture';
$pdo->exec('CREATE DATABASE IF NOT EXISTS '.$db.' CHARACTER SET utf8mb4');
$pdo->exec('USE '.$db);
try{
 $pdo->exec('CREATE TABLE ep_clip_marks (
   tenant_guid BINARY(16) NOT NULL,mark_guid BINARY(16) NOT NULL,
   page_guid BINARY(16) NOT NULL,status VARCHAR(20) NOT NULL,
   updated_at DATETIME(6) NOT NULL,PRIMARY KEY(tenant_guid,mark_guid)
 ) ENGINE=InnoDB');
 $pdo->exec('CREATE TABLE ep_editions (
   tenant_guid BINARY(16) NOT NULL,edition_guid BINARY(16) NOT NULL,
   status VARCHAR(20) NOT NULL,PRIMARY KEY(tenant_guid,edition_guid)
 ) ENGINE=InnoDB');
 $pdo->exec('CREATE TABLE ep_pages (
   tenant_guid BINARY(16) NOT NULL,page_guid BINARY(16) NOT NULL,
   edition_guid BINARY(16) NOT NULL,version BIGINT UNSIGNED NOT NULL,
   PRIMARY KEY(tenant_guid,page_guid)
 ) ENGINE=InnoDB');
 $pdo->exec('CREATE TABLE ep_page_assets (
   tenant_guid BINARY(16) NOT NULL,page_guid BINARY(16) NOT NULL,
   asset_guid BINARY(16) NOT NULL,asset_type VARCHAR(32) NOT NULL,
   checksum_sha256 CHAR(64) NULL,PRIMARY KEY(tenant_guid,page_guid,asset_type)
 ) ENGINE=InnoDB');
 $pdo->exec('CREATE TABLE ep_clips (
   tenant_guid BINARY(16) NOT NULL,clip_guid BINARY(16) NOT NULL,
   page_guid BINARY(16) NOT NULL,mark_guid BINARY(16) NULL,
   clip_slug CHAR(18) NOT NULL,visibility VARCHAR(32) NOT NULL,
   expires_at DATETIME(6) NULL,PRIMARY KEY(tenant_guid,clip_guid)
 ) ENGINE=InnoDB');
 $migration=(string)file_get_contents(dirname(__DIR__).'/database/upgrade-v2.0.2.4-official-clips.sql');
 $pdo->exec($migration);
 $pdo->exec($migration);
 $id=static fn(string $hex):string=>hex2bin(str_repeat($hex,32));
 $a=$id('a');$b=$id('b');$mark=$id('2');$page=$id('1');$clip=$id('3');$asset=$id('4');$edition=$id('5');$actor=$id('6');
 $stamp='2026-10-20 04:00:00.000000';$checksum=str_repeat('e',64);
 $insert=static function(string $sql,array $args)use($pdo):void{$q=$pdo->prepare($sql);$q->execute($args);};
 $insert('INSERT INTO ep_editions VALUES (?,?,"PUBLISHED")',[$a,$edition]);
 $insert('INSERT INTO ep_pages VALUES (?,?,?,1)',[$a,$page,$edition]);
 $insert('INSERT INTO ep_page_assets VALUES (?,?,?,"HIGHRES",?)',[$a,$page,$asset,$checksum]);
 $insert('INSERT INTO ep_clip_marks VALUES (?,?,?,"ACTIVE",?)',[$a,$mark,$page,$stamp]);
 $insert('INSERT INTO ep_clips VALUES (?,?,?,?,"123456789012345678","OFFICIAL",NULL)',[$a,$clip,$page,$mark]);
 $insert('INSERT INTO ep_official_clips
   (tenant_guid,mark_guid,page_guid,clip_guid,asset_guid,asset_checksum,
    page_version,mark_updated_at,status,approved_by_user_guid,approved_at,updated_at)
   VALUES (?,?,?,?,?,?,1,?,"ACTIVE",?,NOW(6),NOW(6))',
   [$a,$mark,$page,$clip,$asset,$checksum,$stamp,$actor]);
 require_once dirname(__DIR__).'/app/Services/OfficialClipService.php';
 $reflection=new ReflectionClass(\EpaperSaas\Services\OfficialClipService::class);
 $predicate=$reflection->getConstant('VALIDITY');
 if(!is_string($predicate)||$predicate==='')throw new RuntimeException('Official validity predicate missing');
 $query='SELECT COUNT(*)
   FROM ep_official_clips o
   JOIN ep_clip_marks m ON m.tenant_guid=o.tenant_guid AND m.mark_guid=o.mark_guid AND m.page_guid=o.page_guid
   JOIN ep_pages p ON p.tenant_guid=o.tenant_guid AND p.page_guid=o.page_guid
   JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid
   JOIN ep_page_assets a ON a.tenant_guid=p.tenant_guid AND a.page_guid=p.page_guid AND a.asset_type="HIGHRES"
   JOIN ep_clips c ON c.tenant_guid=o.tenant_guid AND c.clip_guid=o.clip_guid
   WHERE o.tenant_guid=? AND o.mark_guid=? AND o.page_guid=? AND o.clip_guid=? AND '.$predicate;
 $visible=static function(string $tenant)use($pdo,$query,$mark,$page,$clip):bool{
   $q=$pdo->prepare($query);$q->execute([$tenant,$mark,$page,$clip]);return (int)$q->fetchColumn()===1;
 };
 if(!$visible($a)||$visible($b))throw new RuntimeException('Valid official clip or tenant scope incorrect');
 $cases=[
   ['UPDATE ep_official_clips SET status="REVOKED"','UPDATE ep_official_clips SET status="ACTIVE"','Withdrawal'],
   ['UPDATE ep_clip_marks SET status="HIDDEN"','UPDATE ep_clip_marks SET status="ACTIVE"','Hide'],
   ["UPDATE ep_clip_marks SET updated_at='2026-10-20 04:01:00.000000'","UPDATE ep_clip_marks SET updated_at='$stamp'",'Edited mark'],
   ['UPDATE ep_pages SET version=2','UPDATE ep_pages SET version=1','Page reprocess'],
   ['UPDATE ep_editions SET status="DRAFT"','UPDATE ep_editions SET status="PUBLISHED"','Unpublished edition'],
   ['UPDATE ep_page_assets SET checksum_sha256="'.str_repeat('f',64).'"','UPDATE ep_page_assets SET checksum_sha256="'.$checksum.'"','Replaced asset'],
   ['UPDATE ep_clips SET visibility="PRIVATE"','UPDATE ep_clips SET visibility="OFFICIAL"','No private promotion'],
 ];
 foreach($cases as [$disable,$restore,$label]){
   $pdo->exec($disable);
   if($visible($a))throw new RuntimeException($label.' did not revoke official access');
   $pdo->exec($restore);
   if(!$visible($a))throw new RuntimeException($label.' did not restore base fixture');
 }
 echo "Official publisher clipping SQL: MariaDB visibility, withdrawal, page/version and tenant isolation OK\n";
}finally{
 $pdo->exec('DROP DATABASE '.$db);
}
