<?php
declare(strict_types=1);
namespace EpaperSaas\Services;
use EpaperSaas\Contracts\EditionPublishPresenter;
/** Pure edition status presentation; accepts no HTTP/session state. */
final class DefaultEditionPublishPresenter implements EditionPublishPresenter
{
    public function present(array $r): array
    {
        $status=(string)($r['status']??'DRAFT');$mode=(string)($r['publish_mode']??'MANUAL');
        if($status==='PUBLISHED')return['label'=>'Published','class'=>'published','note'=>!empty($r['published_at'])?date('d M · H:i',strtotime((string)$r['published_at'])):'Live'];
        if($status==='TRASHED')return['label'=>'In Trash','class'=>'archived','note'=>'Files retained for Restore'];
        if($status==='ARCHIVED')return['label'=>'Archived','class'=>'archived','note'=>'Not in public library'];
        if($mode==='SCHEDULED')return['label'=>'Scheduled','class'=>'scheduled','note'=>$status==='NEEDS_ATTENTION'?'Waiting for successful reprocess':'Will publish when ready'];
        if($mode==='AUTO_READY')return['label'=>'Auto publish','class'=>'scheduled','note'=>$status==='READY'?'Publishing on scheduler':'After processing succeeds'];
        return['label'=>'Draft','class'=>'draft','note'=>$status==='READY'?'Ready to publish':'Not public'];
    }
}
