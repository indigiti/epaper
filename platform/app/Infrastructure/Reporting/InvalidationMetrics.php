<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Reporting;

use Digiti\Core\Store;

/** Operational evidence for authenticated cross-application cache invalidations. */
final class InvalidationMetrics
{
    public static function snapshot(): array
    {
        $row=Store::one('SELECT COUNT(*) total,
            COALESCE(SUM(status="PENDING"),0) pending,
            COALESCE(SUM(status="PROCESSING"),0) processing,
            COALESCE(SUM(status="FAILED"),0) failed,
            COALESCE(SUM(status="PROCESSED"),0) acknowledged
            FROM outbox_events WHERE event_type="cache.invalidate"');
        $samples=Store::all('SELECT TIMESTAMPDIFF(MICROSECOND,created_at,processed_at)/1000 duration_ms
            FROM outbox_events WHERE event_type="cache.invalidate" AND status="PROCESSED" AND processed_at IS NOT NULL
            ORDER BY processed_at DESC LIMIT 100');
        $ms=array_map(static fn(array $x):float=>(float)$x['duration_ms'],$samples);
        sort($ms,SORT_NUMERIC);
        return [
            'total'=>(int)($row['total']??0),'pending'=>(int)($row['pending']??0),
            'processing'=>(int)($row['processing']??0),'failed'=>(int)($row['failed']??0),
            'acknowledged'=>(int)($row['acknowledged']??0),
            'p95_ack_ms'=>$ms?$ms[max(0,(int)ceil(count($ms)*0.95)-1)]:null,
            'sample_size'=>count($ms)
        ];
    }
}
