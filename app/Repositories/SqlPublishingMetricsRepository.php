<?php
declare(strict_types=1);
namespace EpaperSaas\Repositories;

use EpaperSaas\Contracts\PublishingMetricsRepository;
use EpaperSaas\Core\Store;

/** MariaDB adapter: no schema change; every publishing metric is tenant-bound. */
final class SqlPublishingMetricsRepository implements PublishingMetricsRepository
{
    public function summary(string $tenantBinary): array
    {
        return Store::one('SELECT
            (SELECT COUNT(*) FROM ep_editions WHERE tenant_guid=? AND status="PUBLISHED") published_editions,
            (SELECT COUNT(*) FROM ep_pages WHERE tenant_guid=?) pages,
            (SELECT COUNT(*) FROM ep_processing_jobs WHERE tenant_guid=? AND status IN ("QUEUED","RUNNING","RETRY_WAIT")) processing,
            (SELECT COUNT(*) FROM ep_editions WHERE tenant_guid=? AND status="NEEDS_ATTENTION") failed_jobs,
            (SELECT COUNT(*) FROM ep_processing_jobs WHERE tenant_guid=? AND status IN ("QUEUED","RUNNING","RETRY_WAIT") AND updated_at<DATE_SUB(NOW(6),INTERVAL 20 MINUTE)) stuck_jobs,
            (SELECT COUNT(*) FROM ep_outbox_events WHERE tenant_guid=? AND status="PENDING") outbox_pending,
            (SELECT COUNT(*) FROM ep_outbox_events WHERE tenant_guid=? AND status="FAILED") outbox_failed,
            (SELECT COUNT(*) FROM ep_error_events WHERE tenant_guid=? AND occurred_at>=DATE_SUB(NOW(6),INTERVAL 7 DAY)) recent_errors,
            (SELECT TIMESTAMPDIFF(SECOND,updated_at,NOW(6)) FROM ep_system_state WHERE state_key="scheduler.last_cron") scheduler_age_seconds',
            array_fill(0,8,$tenantBinary)
        ) ?: [];
    }
}
