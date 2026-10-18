<?php
/** Compatibility action seam: preserves existing Platform router, CSRF and RBAC. */
namespace Digiti\Controllers;
use Digiti\Core\{Auth,Audit,Config,Crypto,Database,DomainTools,Entitlements,Http,Mfa,OAuth,RateLimiter,RedisStore,ServiceAuth,Store,Uuid,View};
use Digiti\Billing\ProviderFactory;
use Digiti\Admin\Crud;
use Digiti\Domain\EdgeProviderFactory;
trait SupportActions
{
    private function version(): string{return trim((string)@file_get_contents(dirname(__DIR__,2).'/VERSION'))?:'unknown';}

    private function e(mixed $v): string{return View::esc($v);}

    private function csrf(): string{return '<input type="hidden" name="_csrf" value="'.$this->e(Http::csrf()).'">';}

    private function money(int|string $minor,string $currency='INR'): string{return $currency.' '.number_format(((int)$minor)/100,2);}

    private function status(string $s): string{return '<span class="status '.strtolower(preg_replace('/[^a-z0-9]+/i','-',$s)).'">'.$this->e($s).'</span>';}

    private function table(array $headers,array $rows): string
    {
        $h='<div class="table-wrap"><table><thead><tr>';foreach($headers as$x)$h.='<th>'.$this->e($x).'</th>';$h.='</tr></thead><tbody>';
        foreach($rows as$row){$h.='<tr>';foreach($row as$cell)$h.='<td>'.$cell.'</td>';$h.='</tr>';}$h.='</tbody></table></div>';return $h;
    }

    private function select(string $name,array $rows,string $valueKey,string $labelKey,?string $selected=null): string
    {
        $h='<select name="'.$this->e($name).'" required><option value="">Select…</option>';foreach($rows as$r){$v=(string)$r[$valueKey];$h.='<option value="'.$this->e($v).'"'.($selected===$v?' selected':'').'>'.$this->e($r[$labelKey]).'</option>';}$h.='</select>';return $h;
    }

    private function healthData(): array
    {
        $db=['ok'=>false];try{$start=microtime(true);Store::value('SELECT 1');$db=['ok'=>true,'latency_ms'=>round((microtime(true)-$start)*1000,1)];}catch(\Throwable$e){$db=['ok'=>false,'error'=>$e->getMessage()];}
        $redis=['enabled'=>RedisStore::enabled(),'ok'=>false];if(RedisStore::enabled()){try{$start=microtime(true);$pong=RedisStore::client()?->ping();$redis=['enabled'=>true,'ok'=>(bool)$pong,'latency_ms'=>round((microtime(true)-$start)*1000,1)];}catch(\Throwable$e){$redis=['enabled'=>true,'ok'=>false,'error'=>$e->getMessage()];}}
        $worker=Store::one('SELECT received_at,status,version FROM application_heartbeats ORDER BY received_at DESC LIMIT 1');$queue=['queued'=>(int)Store::value('SELECT COUNT(*) FROM jobs WHERE status="QUEUED"'),'failed'=>(int)Store::value('SELECT COUNT(*) FROM jobs WHERE status="FAILED"'),'outbox'=>(int)Store::value('SELECT COUNT(*) FROM outbox_events WHERE status="PENDING"')];return ['ok'=>$db['ok']&&(!$redis['enabled']||$redis['ok']),'version'=>$this->version(),'environment'=>Config::get('app.env'),'database'=>$db,'redis'=>$redis,'queue'=>$queue,'latest_application_heartbeat'=>$worker,'request_guid'=>Http::requestGuid()];
    }

    private function healthReady(): never{$h=$this->healthData();Http::json($h,$h['ok']?200:503);}
}
