<?php
declare(strict_types=1);
namespace EpaperSaas\Contracts;
interface VitalsRecorder
{
    /** @param array<string,mixed> $metrics */
    public function record(array $metrics): void;
}
