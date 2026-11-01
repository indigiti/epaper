<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$app=(string)file_get_contents($root.'/app/App.php');
$semantic=(string)file_get_contents($root.'/app/Services/SemanticNews.php');
$pos=strpos($semantic,'function adminArticles(');
$end=strpos($semantic,'public static function adminAuthors(', $pos);
if($pos===false||$end===false)throw new RuntimeException('Article listing absent');
$listing=substr($semantic,$pos,$end-$pos);
if(str_contains($listing,'SELECT a.*')||str_contains($listing,'a.body')||
   !str_contains($listing,'a.title,a.slug,a.status')||
   !str_contains($listing,'LIMIT '.chr(39).'.max(1,min(300,$limit))'))
 throw new RuntimeException('Article listing queries excessive or unbounded data');
if(!str_contains($app,'SELECT * FROM ep_articles WHERE tenant_guid=? AND article_guid=?'))
 throw new RuntimeException('Single article editing must still retrieve complete body');
if(str_contains($app,"SELECT * FROM ep_error_events")||
   !str_contains($app,"ORDER BY occurred_at DESC LIMIT 75")||
   !str_contains($app,'DATE_SUB(NOW(6),INTERVAL 24 HOUR)'))
 throw new RuntimeException('Error Center projection, window or row bound regressed');
echo "Admin Article and Error Center query bounds: OK\n";
