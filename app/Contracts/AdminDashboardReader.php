<?php
declare(strict_types=1);
namespace Digiti\Contracts;
interface AdminDashboardReader
{
    /** @return array<string,int> */
    public function counts(): array;
}
