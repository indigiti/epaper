<?php
declare(strict_types=1);
namespace EpaperSaas\Contracts;
interface ClipReader
{
    /** @return array<string,mixed>|null */
    public function bySlug(string $slug): ?array;
    /** @param array<string,mixed> $clip */
    public function canView(array $clip): bool;
}
