<?php
declare(strict_types=1);
namespace Digiti\Application\Routing;

final class DomainResolutionFailure extends \RuntimeException
{
    public function __construct(private readonly string $codeName='DOMAIN_NOT_ACTIVE')
    {
        parent::__construct('Domain is not active');
    }
    public function errorCode(): string {return $this->codeName;}
}
