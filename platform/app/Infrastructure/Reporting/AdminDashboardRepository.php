<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Reporting;

use Digiti\Core\Store;

/** One DB round-trip for the Admin overview's four independent KPIs. */
final class AdminDashboardRepository
{
    public static function counts(): array
    {
        $row=Store::one('SELECT
          (SELECT COUNT(*) FROM organisations WHERE status<>"PURGED") organisations,
          (SELECT COUNT(*) FROM product_tenants WHERE status="ACTIVE") tenants,
          (SELECT COUNT(*) FROM domains WHERE status="ACTIVE") domains,
          (SELECT COUNT(*) FROM subscriptions WHERE status IN ("ACTIVE","PAST_DUE","GRACE_PERIOD")) subscriptions');
        return [
            'Organisations'=>(int)($row['organisations']??0),
            'Product Tenants'=>(int)($row['tenants']??0),
            'Active Domains'=>(int)($row['domains']??0),
            'Subscriptions'=>(int)($row['subscriptions']??0),
        ];
    }
}
