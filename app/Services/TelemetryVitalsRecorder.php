<?php
declare(strict_types=1);
namespace EpaperSaas\Services;
use EpaperSaas\Contracts\VitalsRecorder;
final class TelemetryVitalsRecorder implements VitalsRecorder
{
    public function record(array $metrics): void
    {
        PerformanceTelemetry::recordVitals($metrics);
    }
}
