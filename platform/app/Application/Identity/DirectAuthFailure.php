<?php
declare(strict_types=1);
namespace Digiti\Application\Identity;

final class DirectAuthFailure extends \RuntimeException
{
    public function __construct(private readonly string $codeName,private readonly int $status,string $message)
    {
        parent::__construct($message);
    }
    public function errorCode(): string {return $this->codeName;}
    public function httpStatus(): int {return $this->status;}
}
