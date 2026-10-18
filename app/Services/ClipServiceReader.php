<?php
declare(strict_types=1);
namespace EpaperSaas\Services;
use EpaperSaas\Contracts\ClipReader;
/** Delegates to existing tenant-scoped clip lookup and visibility policy. */
final class ClipServiceReader implements ClipReader
{
    public function bySlug(string $slug): ?array {return ClipService::bySlug($slug);}
    public function canView(array $clip): bool {return ClipService::canView($clip);}
}
