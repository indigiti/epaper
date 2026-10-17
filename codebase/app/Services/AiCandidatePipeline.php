<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RequestContext,Store,Uuid};

/**
 * Opt-in, tenant-bound OCR-to-candidate bridge. Never affects ePaper processing
 * success; candidate failures are reported separately and never published.
 */
final class AiCandidatePipeline
{
    public static function enabled(): bool
    {
        return Config::get('ai_clipping.enabled', false) === true;
    }

    public static function stage(string $editionGuid,string $pageGuid,string $hocr,string $imagePath): bool
    {
        if(!self::enabled())return false;
        $tenant=RequestContext::tenantGuid();
        if(!Uuid::isValid($editionGuid)||!Uuid::isValid($pageGuid)||strlen($hocr)>16*1024*1024){
            throw new \RuntimeException('AI candidate input invalid');
        }
        $root=dirname(__DIR__,2);
        $known=Store::one('SELECT p.width,p.height FROM ep_pages p WHERE p.tenant_guid=? AND p.edition_guid=? AND p.page_guid=?',[
            Uuid::toBinary($tenant),Uuid::toBinary($editionGuid),Uuid::toBinary($pageGuid)
        ]);
        if(!$known||!$known['width']||!$known['height'])throw new \RuntimeException('Candidate page not tenant-owned');
        $real=realpath($imagePath);
        $media=realpath($root.'/storage/media/'.$tenant.'/'.$editionGuid);
        if(!$real||!$media||!str_starts_with($real,$media.DIRECTORY_SEPARATOR)||!is_file($real)){
            throw new \RuntimeException('Candidate image path is outside tenant edition');
        }
        $input=[
            'tenant_guid'=>$tenant,'page_guid'=>$pageGuid,
            'width'=>(int)$known['width'],'height'=>(int)$known['height'],
            'languages'=>(string)Config::get('processing.ocr_languages','eng'),
            'hocr'=>$hocr,
        ];
        if($input['languages']==='auto')$input['languages']='mar+hin+eng';
        $data=json_encode($input,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(strlen($data)>18*1024*1024)throw new \RuntimeException('Candidate input JSON too large');
        $tmp=$root.'/storage/tmp/ai-candidates';
        if(!is_dir($tmp)&&!@mkdir($tmp,0700,true)&&!is_dir($tmp))throw new \RuntimeException('Cannot create private AI temp directory');
        $in=tempnam($tmp,'in-');$out=tempnam($tmp,'out-');
        if(!$in||!$out){if($in)@unlink($in);throw new \RuntimeException('AI candidate temporary files unavailable');}
        try{
            @chmod($in,0600);@chmod($out,0600);
            if(file_put_contents($in,$data,LOCK_EX)!==strlen($data))throw new \RuntimeException('AI input write failed');
            $python=trim((string)Config::get('ai_clipping.python_binary','python3'));
            if(!preg_match('/^(?:[A-Za-z0-9_.-]+|\/[A-Za-z0-9_.\/-]+)$/',$python)||str_contains($python,'..')){
                throw new \RuntimeException('Invalid Python runtime configuration');
            }
            $command=[$python,$root.'/bin/ai-candidate-generate.py','--input-json',$in,'--output-json',$out,'--image',$real];
            $proc=@proc_open($command,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
            if(!is_resource($proc))throw new \RuntimeException('AI candidate process unavailable');
            $exit=null;$error='';$started=microtime(true);$timeout=max(5,min(90,(int)Config::get('ai_clipping.timeout_seconds',30)));
            try{
                stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
                while(true){
                    @stream_get_contents($pipes[1]);$error=substr($error.(string)@stream_get_contents($pipes[2]),-512);
                    $state=proc_get_status($proc);
                    if(!$state||!$state['running']){$exit=$state['exitcode']??-1;break;}
                    if(microtime(true)-$started>$timeout){
                        proc_terminate($proc,15);usleep(200000);
                        $state=proc_get_status($proc);if($state&&$state['running'])proc_terminate($proc,9);
                        throw new \RuntimeException('AI candidate timeout');
                    }
                    usleep(100000);
                }
            }finally{
                foreach($pipes as$pipe)if(is_resource($pipe))fclose($pipe);
                $closed=proc_close($proc);
                if(($exit===null||$exit===-1)&&$closed>=0)$exit=$closed;
            }
            if($exit!==0)throw new \RuntimeException('AI candidate worker exited unsuccessfully');
            $size=@filesize($out);
            if(!$size||$size>8*1024*1024)throw new \RuntimeException('AI candidate output exceeds bounds');
            $raw=(string)file_get_contents($out);
            $doc=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
            if(!is_array($doc)||($doc['schema_version']??null)!=='epdi.segmentation.v1'
                ||($doc['status']??null)!=='REVIEW_REQUIRED'
                ||($doc['page']['tenant_guid']??null)!==$tenant
                ||($doc['page']['page_guid']??null)!==$pageGuid
                ||count($doc['regions']??[])>4096){
                throw new \RuntimeException('AI candidate output contract invalid');
            }
            // Do not overwrite any existing editorial work when OCR is retried.
            return Store::exec('INSERT IGNORE INTO ep_ai_page_candidates(candidate_guid,tenant_guid,edition_guid,page_guid,source_sha256,candidate_json,status,version,created_at,updated_at) VALUES(?,?,?,?,?,?,"PENDING",1,NOW(6),NOW(6))',[
                Uuid::toBinary(Uuid::v7()),Uuid::toBinary($tenant),Uuid::toBinary($editionGuid),
                Uuid::toBinary($pageGuid),hash('sha256',$hocr),$raw
            ])===1;
        }finally{
            @unlink($in);@unlink($out);
        }
    }
}
