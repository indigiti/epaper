<?php
declare(strict_types=1);
namespace EpaperSaas\Services;
use EpaperSaas\Contracts\ClipReader;
final class ClipVisibilityService
{
    public function __construct(private readonly ClipReader $clips) {}
    /** @return array<string,mixed>|null */
    public function visible(string $slug): ?array
    {
        $clip=$this->clips->bySlug($slug);
        return $clip && $this->clips->canView($clip) ? $clip : null;
    }
}
