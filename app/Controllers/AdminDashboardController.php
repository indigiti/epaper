<?php
declare(strict_types=1);
namespace Digiti\Controllers;
use Digiti\Contracts\AdminDashboardReader;
/** Independently testable Platform metrics controller, RBAC in front dispatcher. */
final class AdminDashboardController
{
    public function __construct(private readonly AdminDashboardReader $metrics) {}
    /** @return array<string,int> */
    public function counts(): array
    {
        return $this->metrics->counts();
    }
}
