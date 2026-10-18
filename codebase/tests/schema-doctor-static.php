<?php
declare(strict_types=1);
require_once __DIR__.'/support/app-source.php';
$root=dirname(__DIR__);require_once $root.'/app/Services/SchemaDoctor.php';
$fails=[];$pass=function(bool$ok,string$name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};
$ref=new ReflectionClass(EpaperSaas\Services\SchemaDoctor::class);$m=$ref->getMethod('splitSql');$m->setAccessible(true);
$sql=(string)file_get_contents($root.'/database/upgrade-v2.0.0-alpha.2.sql');$parts=$m->invoke(null,$sql);
$pass(count($parts)===3,'alpha.2 migration splits into exactly three DDL statements');
foreach(['ep_navigation_scopes','ep_navigation_items','ep_navigation_versions'] as$table)$pass((bool)array_filter($parts,fn($s)=>str_contains($s,'CREATE TABLE IF NOT EXISTS '.$table)),'migration contains '.$table);
$upper=strtoupper($sql);$pass(!str_contains($upper,'DROP TABLE')&&!str_contains($upper,'TRUNCATE ')&&!str_contains($upper,'DELETE FROM '),'canonical alpha.2 migration contains no destructive data DDL/DML');
$source=(string)file_get_contents($root.'/app/Services/SchemaDoctor.php');
$pass(str_contains($source,'GET_LOCK')&&str_contains($source,'RELEASE_LOCK'),'repair path is concurrency locked');
$pass(str_contains($source,'Base schema is incomplete')&&str_contains($source,'Automatic v2 repair is blocked'),'repair refuses unknown base-schema damage');

$rc1=(string)file_get_contents($root.'/database/upgrade-v2.0.0-rc.1.sql');$rc1Parts=$m->invoke(null,$rc1);
$pass(count($rc1Parts)===7,'rc.1 migration splits into seven additive DDL statements');
foreach(['ep_authors','ep_topics','ep_entities','ep_articles','ep_article_authors','ep_article_topics','ep_article_entities'] as$table)$pass((bool)array_filter($rc1Parts,fn($s)=>str_contains($s,'CREATE TABLE IF NOT EXISTS '.$table)),'rc.1 migration contains '.$table);
$rc1Upper=strtoupper($rc1);$pass(!str_contains($rc1Upper,'DROP TABLE')&&!str_contains($rc1Upper,'TRUNCATE ')&&!str_contains($rc1Upper,'DELETE FROM '),'canonical rc.1 migration contains no destructive data DDL/DML');
$pass(str_contains($source,'RC1_TABLES')&&str_contains($source,'upgrade-v2.0.0-rc.1.sql'),'Schema Doctor integrates rc.1 semantic migration');
$pass(str_contains($source,'MOBILE15_TABLES')&&str_contains($source,'upgrade-v2.0.1.5-mobile-auth.sql'),'Schema Doctor integrates v2.0.1.5 mobile-auth migration');
$pass(str_contains($source,'FLEX17_TABLES')&&str_contains($source,'upgrade-v2.0.1.7-flexible-reader-identity.sql'),'Schema Doctor integrates v2.0.1.7 flexible-reader migration');
$pass(str_contains($source,'TRUST18_TABLES')&&str_contains($source,'upgrade-v2.0.1.8-trusted-app-desktop-login.sql'),'Schema Doctor integrates v2.0.1.8 trusted-login migration');
$app=epdiAppSource($root);
$pass(str_contains($app,'SchemaDoctor::hasTrustedAppSchema()')&&str_contains($app,'Database upgrade required.'),'Subscribers page has missing-schema degraded mode');
if($fails){fwrite(STDERR,"\nSCHEMA DOCTOR STATIC TEST FAILED\n");exit(1);}echo"\nSCHEMA DOCTOR STATIC TEST PASSED\n";
