<?php
declare(strict_types=1);
namespace Digiti\Application\Operations;

use Digiti\Core\Store;

/** Reclaim crashed worker leases without acknowledging unfinished work. */
final class QueueLeaseRecovery
{
    public static function recover(): array
    {
        // Workers reserve outbox items by moving available_at fifteen minutes forward.
        $outbox=Store::exec(
            'UPDATE outbox_events
             SET status=IF(attempts>=5,"FAILED","PENDING"),
                 available_at=NOW(6),
                 last_error=COALESCE(last_error,"Recovered expired outbox worker lease")
             WHERE status="PROCESSING" AND available_at<=NOW(6)'
        );
        $jobs=Store::exec(
            'UPDATE jobs
             SET status=IF(attempts>=5,"FAILED","QUEUED"),
                 available_at=NOW(6),locked_at=NULL,locked_by=NULL,
                 last_error=COALESCE(last_error,"Recovered expired job worker lease")
             WHERE status="RUNNING" AND locked_at<DATE_SUB(NOW(6),INTERVAL 15 MINUTE)'
        );
        return ['outbox'=>$outbox,'jobs'=>$jobs];
    }
}
