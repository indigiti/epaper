<?php
declare(strict_types=1);
/**
 * Disposable MariaDB acceptance for isolated AI review schema.
 * DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD point to CI-only disposable DB.
 * Never run against production.
 */
$host=getenv('EPDI_AI_TEST_DB_HOST')?:'127.0.0.1';
$user=getenv('EPDI_AI_TEST_DB_USER')?:'root';
$pass=getenv('EPDI_AI_TEST_DB_PASSWORD')?:'';
$dsn='mysql:host='.$host.';port=3306;charset=utf8mb4';
$db=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE DATABASE IF NOT EXISTS epdi_ai_candidate_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec('USE epdi_ai_candidate_test');
$migration=file_get_contents(dirname(__DIR__).'/database/upgrade-ai-page-candidates-20261018.sql');
$db->exec($migration);
$db->exec($migration); // Idempotence
$tenantA=hex2bin('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
$tenantB=hex2bin('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
$edition=hex2bin('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');
$pageA=hex2bin('11111111111111111111111111111111');
$pageB=hex2bin('22222222222222222222222222222222');
$guidA=hex2bin('33333333333333333333333333333333');
$guidB=hex2bin('44444444444444444444444444444444');
$insert=$db->prepare('INSERT INTO ep_ai_page_candidates(candidate_guid,tenant_guid,edition_guid,page_guid,source_sha256,candidate_json,status,version,created_at,updated_at) VALUES(?,?,?,?,?,?,"PENDING",1,NOW(6),NOW(6))');
$fixture='{"schema_version":"epdi.segmentation.v1","status":"REVIEW_REQUIRED","regions":[]}';
$insert->execute([$guidA,$tenantA,$edition,$pageA,str_repeat('a',64),$fixture]);
$insert->execute([$guidB,$tenantB,$edition,$pageB,str_repeat('b',64),$fixture]);
$read=$db->prepare('SELECT COUNT(*) FROM ep_ai_page_candidates WHERE tenant_guid=?');
$read->execute([$tenantA]);if((int)$read->fetchColumn()!==1)throw new RuntimeException('Tenant A row count not isolated');
$read->execute([$tenantB]);if((int)$read->fetchColumn()!==1)throw new RuntimeException('Tenant B row count not isolated');
$readWrong=$db->prepare('SELECT candidate_json FROM ep_ai_page_candidates WHERE tenant_guid=? AND page_guid=?');
$readWrong->execute([$tenantA,$pageB]);if($readWrong->fetchColumn()!==false)throw new RuntimeException('Cross-tenant page lookup leaked');
$edit=$db->prepare('UPDATE ep_ai_page_candidates SET editor_json=?,status="PENDING",version=version+1,updated_at=NOW(6) WHERE tenant_guid=? AND page_guid=? AND version=?');
$correction='{"regions":{"abc":{"kind":"HEADLINE_CANDIDATE","text":"Edited"}}}';
$edit->execute([$correction,$tenantA,$pageA,1]);
if($edit->rowCount()!==1)throw new RuntimeException('First authorized correction did not apply');
$edit->execute([$correction,$tenantA,$pageA,1]);
if($edit->rowCount()!==0)throw new RuntimeException('Stale correction overwrote a newer revision');
$edit->execute([$correction,$tenantB,$pageA,2]);
if($edit->rowCount()!==0)throw new RuntimeException('Foreign tenant updated source row');
$approve=$db->prepare('UPDATE ep_ai_page_candidates SET status="REVIEWED",version=version+1,updated_at=NOW(6) WHERE tenant_guid=? AND page_guid=? AND version=?');
$approve->execute([$tenantA,$pageA,2]);
if($approve->rowCount()!==1)throw new RuntimeException('Review status did not advance');
$reprocess=$db->prepare('INSERT IGNORE INTO ep_ai_page_candidates(candidate_guid,tenant_guid,edition_guid,page_guid,source_sha256,candidate_json,status,version,created_at,updated_at) VALUES(?,?,?,?,?,?,"PENDING",1,NOW(6),NOW(6))');
$reprocess->execute([hex2bin('55555555555555555555555555555555'),$tenantA,$edition,$pageA,str_repeat('c',64),$fixture]);
if($reprocess->rowCount()!==0)throw new RuntimeException('Reprocess overwrote corrected page');
$readState=$db->prepare('SELECT status,version,editor_json FROM ep_ai_page_candidates WHERE tenant_guid=? AND page_guid=?');
$readState->execute([$tenantA,$pageA]);$state=$readState->fetch(PDO::FETCH_ASSOC);
if($state['status']!=='REVIEWED'||(int)$state['version']!==3||$state['editor_json']!==$correction)throw new RuntimeException('Approved editorial correction changed');
$readState->execute([$tenantB,$pageB]);$other=$readState->fetch(PDO::FETCH_ASSOC);
if($other['status']!=='PENDING'||(int)$other['version']!==1||$other['editor_json']!==null)throw new RuntimeException('Other tenant was modified');
$db->exec('DROP DATABASE epdi_ai_candidate_test');
echo "PASS AI schema idempotence, tenant lookup, optimistic concurrency, preservation and isolated review status\n";
