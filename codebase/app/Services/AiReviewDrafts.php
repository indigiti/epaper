<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RequestContext,Store,Uuid};

/**
 * Reviewed OCR story group to unpublished article draft.
 * Called only by explicit authenticated Article Review POST after approval.
 */
final class AiReviewDrafts
{
    public static function suggestions(array $document,array $editor): array
    {
        $groups=[];
        foreach(($document['regions']??[]) as $position=>$region){
            if(!is_array($region)||!is_string($region['id']??null))continue;
            $saved=$editor['regions'][$region['id']]??null;
            if(!is_array($saved))continue; // Manual grouping, not machine-proposed links.
            $group=trim((string)($saved['article_group']??''));
            if($group===''||strlen($group)>40||!preg_match('/^[A-Za-z0-9_-]+$/D',$group))continue;
            $kind=(string)($saved['kind']??$region['suggested_kind']??'');
            if(!in_array($kind,['HEADLINE_CANDIDATE','BODY_CANDIDATE'],true))continue;
            $text=trim((string)($saved['text']??$region['text']??''));
            if($text==='')continue;
            $order=(int)($saved['reading_order']??((int)$position+1));
            if($order<1||$order>4096)continue;
            $groups[$group][]=[$kind,$order,(int)$position,$text];
        }
        $suggestions=[];
        foreach($groups as $group=>$parts){
            usort($parts,static fn(array $a,array $b):int=>[$a[1],$a[2]]<=>[$b[1],$b[2]]);
            $headlines=[];$bodies=[];
            foreach($parts as [$kind,$order,$position,$text]){
                if($kind==='HEADLINE_CANDIDATE')$headlines[]=$text;
                else $bodies[]=$text;
            }
            $title=mb_substr(trim(implode(' ',$headlines)),0,250,'UTF-8');
            $body=mb_substr(trim(implode("\n\n",$bodies)),0,200000,'UTF-8');
            $suggestions[$group]=['group'=>(string)$group,'title'=>$title,'body'=>$body,'region_count'=>count($parts),
                'ready'=>$title!==''&&mb_strlen($body,'UTF-8')>=80];
        }
        ksort($suggestions,SORT_NATURAL);
        return $suggestions;
    }

    public static function storeDraft(array $row,string $pageGuid,string $group,array $story,string $actor): string
    {
        if(($row['status']??'')!=='REVIEWED'||empty($story['ready'])||!Uuid::isValid($actor)
            ||!Uuid::isValid($pageGuid)||!preg_match('/^[A-Za-z0-9_-]{1,40}$/D',$group)){
            throw new \RuntimeException('Reviewed story and valid editor required');
        }
        // Stable source+group key: the same explicit action can safely be repeated.
        $slug='ocr-'.str_replace('-','',strtolower($pageGuid)).'-'.substr(hash('sha256',$group),0,12);
        $tenant=Uuid::toBinary(RequestContext::tenantGuid());
        $existing=Store::one('SELECT article_guid,page_guid,edition_guid FROM ep_articles WHERE tenant_guid=? AND slug=? LIMIT 1',[$tenant,$slug]);
        if($existing){
            if($existing['page_guid']!==$row['page_guid']||$existing['edition_guid']!==$row['edition_guid']){
                throw new \RuntimeException('Article draft slug conflict');
            }
            return Uuid::fromBinary($existing['article_guid']);
        }
        $id=Uuid::v7();$person=Uuid::toBinary($actor);
        $notes='Reviewed OCR source page '.$pageGuid.'; story group '.$group.'. Verify all text, attribution and article boundaries before publication.';
        Store::exec(
            'INSERT INTO ep_articles(article_guid,tenant_guid,publication_guid,city_guid,language_guid,section_guid,edition_guid,page_guid,title,slug,body,source_notes,ai_assisted,ai_disclosure,status,version,created_by_user_guid,updated_by_user_guid,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1,?,"DRAFT",1,?,?,NOW(6),NOW(6))',
            [Uuid::toBinary($id),$tenant,$row['publication_guid'],$row['city_guid']??null,$row['language_guid']??null,$row['section_guid']??null,
             $row['edition_guid'],$row['page_guid'],$story['title'],$slug,$story['body'],$notes,
             'OCR-assisted draft; requires human editorial verification before publication.',$person,$person]
        );
        return $id;
    }
}
