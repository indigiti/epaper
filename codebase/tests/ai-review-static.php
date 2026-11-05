<?php
declare(strict_types=1);
// Staging source-contract checks. Not HTTP/PHP session integration tests.
$root=dirname(__DIR__);
$app=file_get_contents($root.'/app/App.php');
$editor=file_get_contents($root.'/app/Services/AiEditorialReview.php');
$bridge=file_get_contents($root.'/app/Services/AiCandidatePipeline.php');
$worker=file_get_contents($root.'/app/Services/ProcessingPipeline.php');
$schema=file_get_contents($root.'/database/upgrade-ai-page-candidates-20261018.sql');
$config=file_get_contents($root.'/config/config.example.php');
$drafts=file_get_contents($root.'/app/Services/AiReviewDrafts.php');
$checks=[
 'guarded admin route'=>str_contains($app,"if(\$path==='/admin/article-review'")&&str_contains($app,'Auth::requireAdmin();if($method===\'POST\')Http::verifyCsrf();'),
 'editor role restriction'=>str_contains($editor,"['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR']"),
 'review transactions'=>str_contains($editor,'Store::tx('),
 'tenant and revision compare-and-swap'=>str_contains($editor,'WHERE tenant_guid=? AND page_guid=? AND version=?'),
 'review is never publish'=>!str_contains($editor,'INSERT INTO ep_articles')&&!str_contains($editor,'ClipService'),
 'private and protected previews'=>str_contains($editor,'Http::privateNoStore()')&&str_contains($editor,'/storage/media/'),
 'tenant-scoped page and image path'=>str_contains($bridge,'WHERE p.tenant_guid=? AND p.edition_guid=? AND p.page_guid=?')&&str_contains($bridge,'realpath('),
 'no editorial overwrite on retry'=>str_contains($bridge,'INSERT IGNORE INTO ep_ai_page_candidates'),
 'default off'=>str_contains($config,"'ai_clipping'=>['enabled'=>false"),
 'nonfatal worker integration'=>str_contains($worker,'if(AiCandidatePipeline::enabled())')&&str_contains($worker,'AI_CANDIDATE_STAGE_FAILED'),
 'isolated candidate schema'=>str_contains($schema,'UNIQUE KEY uk_ai_candidate_tenant_page (tenant_guid,page_guid)'),
 'paginated review queue'=>str_contains($editor,'$perPage=24')&&str_contains($editor,' OFFSET '),
 'edition, status filter'=>str_contains($editor,'article-review-controls')&&str_contains($editor,'name="edition"'),
 'legacy review URL preserved'=>str_contains($editor,'(?:article-review|ai-review)'),
 'reviewed story needed for draft'=>str_contains($drafts,"'REVIEWED'")&&str_contains($drafts,"'article_group'")&&str_contains($editor,"'/draft'")===false&&str_contains($editor,"/draft"),
 'explicit draft stays private'=>str_contains($drafts,'"DRAFT"')&&!str_contains($drafts,'"PUBLISHED"')&&!str_contains($drafts,'ClipService'),
 'idempotent source group slug'=>str_contains($drafts,"hash('sha256',$group)")&&str_contains($drafts,"SELECT article_guid,page_guid,edition_guid"),

 'new route editor-only'=>str_contains(file_get_contents($root.'/app/Services/AdminRoutePolicy.php'),"'/admin/article-review'"),

];
$bad=0;
foreach($checks as$name=>$ok){echo ($ok?'PASS ':'FAIL ').$name."\n";if(!$ok)$bad++;}
exit($bad?1:0);
