<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,Http,RequestContext,Store,Uuid,View};

/**
 * Owner-only recoverable removal, including already published editions.
 * Never DELETE records, source PDFs, or customer media from this HTTP route.
 */
final class EditionRemoval
{
    private static function deny(string $message,int $code=422): never
    {
        http_response_code($code);
        View::layout('Remove edition','<section class="panel"><h1>Edition was not removed</h1><p>'.View::e($message).'</p><a class="btn ghost" href="/admin/editions">Return to Editions</a></section>',['admin'=>true]);
    }

    public static function remove(string $guid): never
    {
        if(strtoupper((string)(Auth::currentMembership()['role_key']??''))!=='OWNER')self::deny('Only the publisher OWNER can remove a published edition.',403);
        if(!Uuid::isValid($guid))self::deny('Edition not found.',404);
        $typed=trim((string)($_POST['confirm_slug']??''));
        if(($_POST['confirm_remove']??'')!=='yes'||$typed==='')self::deny('Tick the confirmation and enter the exact edition slug.');
        if(!\EpaperSaas\Core\RateLimiter::allow('edition-remove:'.RequestContext::tenantGuid(),5,300))self::deny('Too many removal attempts. Try again later.',429);
        try{
            $result=EditionOperations::locked($guid,static function()use($guid,$typed): array{
                return Store::tx(static function()use($guid,$typed): array{
                    $tenant=TenantData::tenantBin();$bin=Uuid::toBinary($guid);
                    $row=Store::one('SELECT slug,status FROM ep_editions WHERE tenant_guid=? AND edition_guid=? FOR UPDATE',[$tenant,$bin]);
                    if(!$row)throw new \DomainException('Edition not found.');
                    if(!hash_equals((string)$row['slug'],$typed))throw new \DomainException('Edition slug confirmation did not match.');
                    if((string)$row['status']==='TRASHED')throw new \DomainException('This edition is already in Trash.');
                    if(EditionOperations::active($guid))throw new \DomainException('Cancel processing before removing the edition.');
                    Store::exec('UPDATE ep_editions SET status="TRASHED",publish_mode="MANUAL",scheduled_at=NULL,schedule_timezone=NULL,updated_at=NOW(6),version=version+1 WHERE tenant_guid=? AND edition_guid=?',[$tenant,$bin]);
                    return ['slug'=>(string)$row['slug'],'old_status'=>(string)$row['status']];
                });
            });
        }catch(\DomainException $e){self::deny($e->getMessage());}
        catch(\RuntimeException $e){self::deny('Edition is busy. Please retry after processing finishes.');}
        Audit::log('edition.owner_removed','edition',$guid,'SUCCESS',['previous_status'=>$result['old_status'],'recoverable'=>true]);
        EditionManifest::invalidate($result['slug'],$guid);
        RuntimeCache::broadcast(RequestContext::tenantGuid(),['dashboard','public-editions','reader_state']);
        Http::redirect('/admin/editions?notice='.rawurlencode('Edition removed from publication and moved to recoverable Trash.'));
    }
}
