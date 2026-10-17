<?php
declare(strict_types=1);
namespace Digiti\Application\Billing;

final class WebhookFailure extends \RuntimeException
{
    public function __construct(private readonly string $codeName,private readonly int $httpStatus,string $message)
    {
        parent::__construct($message);
    }
    public function errorCode(): string{return $this->codeName;}
    public function status(): int{return $this->httpStatus;}
}
