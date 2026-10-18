<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Contracts\AcceptanceMetricsRepository;

/**
 * Derives a verifiable operator snapshot without executing unsafe browser,
 * publishing, AI edit, credential, tenant suspension or backup tests.
 */
final class StagingAcceptance
{
    public function __construct(private readonly AcceptanceMetricsRepository $repository) {}

    /** @param array<string,mixed> $readiness
        @return array<string,mixed> */
    public function capture(string $tenantBinary,bool $aiEnabled,array $readiness): array
    {
        $data=$this->repository->collect($tenantBinary,$aiEnabled);
        $known=['editions','pages','clips','jobs','ai'];
        $metrics=[];
        foreach($known as $name){
            $v=$data[$name]??null;
            $metrics[$name]=is_array($v)?array_map(static fn($n):int=>(int)$n,$v):null;
        }
        $checks=[];
        foreach(($readiness['checks']??[]) as $check){
            if(!is_array($check))continue;
            // Do not persist raw infrastructure metrics, secrets or exception traces.
            $checks[]=[
                'category'=>self::clean($check['category']??'OTHER',70),
                'name'=>self::clean($check['name']??'Unknown check',90),
                'status'=>in_array($check['status']??'', ['READY','DEGRADED','ACTION_REQUIRED','REVIEW'],true)?$check['status']:'REVIEW',
                'detail'=>self::clean($check['detail']??'',240),
                'action'=>self::clean($check['action']??'',240),
            ];
        }
        $overall=in_array($readiness['status']??'', ['READY','DEGRADED','ACTION_REQUIRED'],true)
            ?$readiness['status']:'ACTION_REQUIRED';
        if($overall==='READY'){
            foreach(['editions','pages','clips','jobs'] as $name){
                if($metrics[$name]===null){$overall='DEGRADED';break;}
            }
            if($aiEnabled && $metrics['ai']===null)$overall='DEGRADED';
        }
        $items=[
            self::item('Publishing & editions','/admin/editions',$metrics['editions'],(int)($metrics['editions']['published']??0),'Published edition records exist; PDF upload/publishing flow is not automatically exercised.'),
            self::item('PDF processing & recovery','/admin/processing',$metrics['jobs'],(int)($metrics['jobs']['total']??0),'Queue/job records exist; retry and failure recovery still require controlled staging tests.'),
            self::item('Reader & page sequence','/admin/editions',$metrics['pages'],(int)($metrics['pages']['total']??0),'Page data exists; visual Reader, navigation and reordering still require QA.'),
            self::item('Clipping & sharing','/admin/editions',$metrics['clips'],(int)($metrics['clips']['total']??0),'Clip records exist; crop, authorization and sharing behavior require QA.'),
            $aiEnabled?self::item('AI editorial review','/admin/ai-review',$metrics['ai'],(int)($metrics['ai']['total']??0),'AI candidate data may exist; editorial correction/approval requires an operator.')
              :['name'=>'AI editorial review','status'=>'DISABLED','detail'=>'AI clipping is disabled in current application configuration.','url'=>'/admin/ai-review'],
            ['name'=>'Platform tenant suspension and cache invalidation','status'=>'NOT_TESTED','detail'=>'Requires disposable staging tenant suspension and ePaper access verification.','url'=>'/admin/system/health'],
            ['name'=>'Backup and restore','status'=>'NOT_TESTED','detail'=>'Cloudways backup/restore evidence cannot be inferred from application health.','url'=>'/admin/storage'],
            ['name'=>'Login, CSRF and role matrix','status'=>'NOT_TESTED','detail'=>'Authentication and cross-role denial require a controlled browser or authenticated acceptance run.','url'=>'/admin/access'],
        ];
        return [
            'schema_version'=>1,
            'app_version'=>\EpaperSaas\Core\Build::version(),
            'captured_at'=>gmdate('c'),
            'automated_status'=>$overall,
            'checks'=>$checks,
            'metrics'=>$metrics,
            'functional'=>$items,
            'note'=>'Automated readiness and observed records are NOT equivalent to passed functional, security or restore acceptance.',
        ];
    }

    private static function clean(mixed $value,int $limit): string
    {
        // Avoid persisting unbounded diagnostic content or control characters.
        $text=preg_replace('/[\x00-\x1f\x7f]+/u',' ',(string)$value)??'';
        return mb_substr($text,0,$limit,'UTF-8');
    }

    /** @param array<string,int>|null $metrics
        @return array{name:string,status:string,detail:string,url:string} */
    private static function item(string $name,string $url,?array $metrics,int $count,string $detail): array
    {
        return [
            'name'=>$name,
            'status'=>$metrics===null?'UNAVAILABLE':($count>0?'OBSERVED':'NOT_TESTED'),
            'detail'=>($metrics===null?'Metrics unavailable. ':($count>0?$count.' recorded. ':'No matching records. ')).$detail,
            'url'=>$url,
        ];
    }
}
