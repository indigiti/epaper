<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RequestContext};
use EpaperSaas\Repositories\SqlAiClippingReadinessRepository;

/**
 * Read-only, bounded web diagnostics. This is not a worker smoke test:
 * executable availability in PHP-FPM does not certify Supervisor or OCR output.
 */
final class AiClippingReadiness
{
    public static function snapshot(): array
    {
        $root=dirname(__DIR__,2);
        $enabled=AiCandidatePipeline::enabled();
        $schema=SqlAiClippingReadinessRepository::inspect(RequestContext::tenantGuid());
        $python=(string)Config::get('ai_clipping.python_binary','python3');
        $absolute=str_starts_with($python,'/');
        $pythonKnown=$absolute&&is_file($python)&&is_executable($python);
        $source=is_file($root.'/bin/ai-candidate-generate.py')
            &&is_file($root.'/ai_processing/hocr.py')
            &&is_file($root.'/ai_processing/segmentation.py');
        $spawn=function_exists('proc_open');
        $rabbit=false;
        try{$rabbit=QueueService::rabbitEnabled();}catch(\Throwable){}

        $checks=[
            ['name'=>'Feature flag','status'=>$enabled?'ENABLED':'DISABLED',
             'detail'=>$enabled?'Private runtime config permits candidate generation.':
             'ai_clipping.enabled is false or unset; editorial review is intentionally hidden.'],
            ['name'=>'Candidate database','status'=>$schema['state'],
             'detail'=>match($schema['state']){
                 'AVAILABLE'=>'Tenant-scoped candidate table is available.',
                 'MISSING'=>'Optional AI candidate migration has not been applied.',
                 default=>'Candidate database cannot be inspected; check application database health.'}],
            ['name'=>'Python runtime','status'=>$pythonKnown?'CONFIGURED':'UNVERIFIED',
             'detail'=>$pythonKnown?'Configured absolute Python binary is executable by this PHP process; worker validation still required.':
                 'Worker Python executable is not verified. Set an absolute private interpreter path for staging.'],
            ['name'=>'AI source modules','status'=>$source?'PRESENT':'MISSING',
             'detail'=>$source?'Segmentation and candidate CLI files exist.':'Candidate CLI or segmentation files are absent from this checkout.'],
            ['name'=>'PHP process support','status'=>$spawn?'AVAILABLE':'UNAVAILABLE',
             'detail'=>$spawn?'proc_open exists in web PHP; Supervisor worker PHP may differ.':
                 'proc_open is missing from web PHP; validate the worker PHP runtime separately.'],
            ['name'=>'RabbitMQ driver','status'=>$rabbit?'CONFIGURED':'UNVERIFIED',
             'detail'=>$rabbit?'RabbitMQ is configured; connected, live OCR workers are not proven here.':
                 'RabbitMQ OCR worker operation is not confirmed. Inspect Processing Center.'],
        ];
        $issues=$schema['state']!=='AVAILABLE'||!$source;
        $status=!$enabled?'DISABLED':($issues?'ACTION_REQUIRED':'VALIDATION_REQUIRED');
        return [
            'enabled'=>$enabled,'status'=>$status,'checks'=>$checks,
            'counts'=>$schema['counts'],
            'review_url'=>$enabled&&$schema['state']==='AVAILABLE'?'/admin/ai-review':null,
            'setup'=>AiClippingWebSetup::gate(),
        ];
    }
}
