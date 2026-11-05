<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Services/AiReviewDrafts.php';
use EpaperSaas\Services\AiReviewDrafts;
function assertStory(bool $ok,string $label):void{
    if(!$ok){fwrite(STDERR,"FAIL ".$label."\n");exit(1);}
    echo "PASS ".$label."\n";
}
$document=['regions'=>[
    ['id'=>'h','suggested_kind'=>'HEADLINE_CANDIDATE','text'=>'Unreviewed headline'],
    ['id'=>'b','suggested_kind'=>'BODY_CANDIDATE','text'=>'Unreviewed body'],
    ['id'=>'ad','suggested_kind'=>'ADVERTISEMENT_CANDIDATE','text'=>'Advertisement'],
    ['id'=>'unassigned','suggested_kind'=>'BODY_CANDIDATE','text'=>'Other newspaper content']
]];
$body='This is a newspaper article paragraph that has been reviewed by an editor, with enough content to form a meaningful draft rather than an isolated phrase.';
$editor=['regions'=>[
    'h'=>['kind'=>'HEADLINE_CANDIDATE','text'=>'Corrected headline','article_group'=>'story-1','reading_order'=>1],
    'b'=>['kind'=>'BODY_CANDIDATE','text'=>$body,'article_group'=>'story-1','reading_order'=>2],
    'ad'=>['kind'=>'ADVERTISEMENT_CANDIDATE','text'=>'Advertisement','article_group'=>'story-1','reading_order'=>3]
]];
$out=AiReviewDrafts::suggestions($document,$editor);
assertStory(count($out)===1,'Only explicitly assigned story groups are assembled');
assertStory($out['story-1']['ready']===true,'Reviewed headline and body pass minimum checks');
assertStory($out['story-1']['title']==='Corrected headline'&&$out['story-1']['body']===$body,'Human corrections are authoritative');
assertStory(!str_contains($out['story-1']['body'],'Advertisement')&&!str_contains($out['story-1']['body'],'Other newspaper'),'Exclude adverts and unassigned regions');
$editor['regions']['b']['text']='Too short';
assertStory(!AiReviewDrafts::suggestions($document,$editor)['story-1']['ready'],'Short OCR snippets do not qualify as articles');
$editor['regions']['h']['article_group']='../../unsafe';
assertStory(!isset(AiReviewDrafts::suggestions($document,$editor)['../../unsafe']),'Unsafe story identifiers are excluded');
echo "PASS reviewed story-to-draft pure logic\n";
