<?php
namespace Digiti\Domain;
interface EdgeProviderInterface
{
    public function key(): string;
    public function provision(string $hostname,array $existing=[]): array;
    public function status(string $hostname,array $metadata=[]): array;
}
