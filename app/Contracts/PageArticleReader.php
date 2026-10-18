<?php
declare(strict_types=1);
namespace EpaperSaas\Contracts;
interface PageArticleReader
{
    /** @return array<string,mixed>|null */
    public function dimensions(string $pageGuid): ?array;
    /** @return list<array<string,mixed>> */
    public function coveringBlocks(string $pageGuid,int $x,int $y): array;
}
