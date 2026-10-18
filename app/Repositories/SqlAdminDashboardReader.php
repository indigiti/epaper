<?php
declare(strict_types=1);
namespace Digiti\Repositories;
use Digiti\Contracts\AdminDashboardReader;
use Digiti\Core\Store;
/** Global Platform administrator metrics, not a tenant-facing API. */
final class SqlAdminDashboardReader implements AdminDashboardReader
{
    public function counts(): array
    {
        return [
            'Organisations'=>(int)Store::value('SELECT COUNT(*) FROM organisations WHERE status<>"PURGED"'),
            'Product Tenants'=>(int)Store::value('SELECT COUNT(*) FROM product_tenants WHERE status="ACTIVE"'),
            'Active Domains'=>(int)Store::value('SELECT COUNT(*) FROM domains WHERE status="ACTIVE"'),
            'Subscriptions'=>(int)Store::value('SELECT COUNT(*) FROM subscriptions WHERE status IN ("ACTIVE","PAST_DUE","GRACE_PERIOD")')
        ];
    }
}
