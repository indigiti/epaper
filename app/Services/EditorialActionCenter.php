<?php
declare(strict_types=1);
namespace EpaperSaas\Services;
use EpaperSaas\Contracts\EditorialActionRepository;

/** Injectable read model; never retries jobs or approves editorial content. */
final class EditorialActionCenter
{
    public function __construct(private readonly EditorialActionRepository $repo){}
    public function snapshot(string $tenant,array $counts,string $role,bool $aiEnabled): array
    {
        $role=strtoupper($role);
        $editor=in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'],true);
        $operator=in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'],true);
        $groups=[];
        if($editor){
            $groups[]=$this->editionGroup($tenant,'NEEDS_ATTENTION','Editions needing attention',
                '/admin/editions?status=NEEDS_ATTENTION',(int)($counts['NEEDS_ATTENTION']??0));
            $groups[]=$this->editionGroup($tenant,'READY','Ready for manual publishing',
                '/admin/editions?status=READY',null);
        }
        if($operator){
            try{
                $rows=$this->repo->failedJobs($tenant,4);$items=[];
                foreach($rows as $row){
                    $guid=(string)($row['edition_guid']??'');
                    if(!self::guid($guid))continue;
                    $items[]=['title'=>self::name($row),'detail'=>'Failure event: '.self::plain($row['job_type']??'processing',60)
                       .' · '.self::plain($row['edition_date']??'',20),
                        'url'=>'/admin/processing/'.$guid];
                }
                $groups[]=self::group('processing','Processing failures','/admin/processing',null,$items,
                    'OBSERVED','Latest failed job events; historical events may have been recovered.');
            }catch(\Throwable){
                $groups[]=self::group('processing','Processing failures','/admin/processing',null,[],
                    'UNAVAILABLE','Job failure data is unavailable. Inspect Processing Center.');
            }
        }
        if($editor){
            if(!$aiEnabled){
                $groups[]=self::group('ai','AI clipping review',null,null,[],'DISABLED',
                    'AI clipping is disabled in the current staging configuration.');
            }else{
                try{
                    $result=$this->repo->pendingAi($tenant,4);$items=[];
                    foreach($result['rows'] as $row){
                        $guid=(string)($row['page_guid']??'');
                        if(!self::guid($guid))continue;
                        $items[]=['title'=>self::name($row).' · Page '.(int)($row['page_number']??0),
                            'detail'=>'Pending human editorial review',
                            'url'=>'/admin/ai-review/'.$guid];
                    }
                    $groups[]=self::group('ai','AI clipping review','/admin/ai-review',
                        max(0,(int)$result['total']),$items,'AVAILABLE',
                        'Candidate pages awaiting review; approval never publishes a clipping.');
                }catch(\Throwable){
                    $groups[]=self::group('ai','AI clipping review','/admin/ai-review',
                        null,[],'UNAVAILABLE','AI candidate records unavailable. Verify the optional migration.');
                }
            }
        }
        $pending=0;
        foreach($groups as $group)if(in_array($group['id'],['attention','ai'],true)
            && $group['status']==='AVAILABLE')$pending+=(int)($group['count']??0);
        return ['groups'=>$groups,'pending_count'=>$pending];
    }
    private function editionGroup(string $tenant,string $status,string $title,string $url,?int $total): array
    {
        $id=$status==='READY'?'ready':'attention';
        try{
            $rows=$this->repo->editions($tenant,$status,4);$items=[];
            foreach($rows as $row){
                $guid=(string)($row['edition_guid']??'');
                if(!self::guid($guid))continue;
                $items[]=['title'=>self::name($row),'detail'=>self::plain($row['edition_date']??'',20)
                    .' · '.str_replace('_',' ',$status),
                    'url'=>'/admin/editions?date='.rawurlencode((string)($row['edition_date']??''))];
            }
            return self::group($id,$title,$url,$total,$items,'AVAILABLE',
                $status==='READY'?'Ready manual editions awaiting publication.':'Editions requiring recovery or editorial attention.');
        }catch(\Throwable){
            return self::group($id,$title,$url,null,[],'UNAVAILABLE','Edition queue could not be read.');
        }
    }
    private static function group(string $id,string $title,?string $url,?int $count,array $items,string $status,string $description): array
    {
        return compact('id','title','url','count','items','status','description');
    }
    private static function guid(string $value): bool {
        return (bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',$value);
    }
    private static function name(array $row): string {
        return self::plain(trim((string)($row['title']??''))?:($row['publication_name']??'Untitled edition'),110);
    }
    private static function plain(mixed $value,int $limit): string {
        $safe=preg_replace('/[\x00-\x1f\x7f]+/u',' ',(string)$value)??'';
        return mb_substr($safe,0,$limit,'UTF-8');
    }
}
