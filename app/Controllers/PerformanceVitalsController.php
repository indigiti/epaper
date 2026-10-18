<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;
use EpaperSaas\Contracts\VitalsRecorder;
use EpaperSaas\Core\Http;

/** Independently testable Web Vitals admission policy, no schema or route change. */
final class PerformanceVitalsController
{
    public function __construct(private readonly VitalsRecorder $recorder) {}
    /** @param array<string,mixed> $server
        @param callable():array<string,mixed> $body
        @return array{0:int,1:array<string,mixed>} */
    public function submit(array $server,string $host,callable $body): array
    {
        $origin=trim((string)($server['HTTP_ORIGIN']??''));
        if($origin!==''){
            $originHost=parse_url($origin,PHP_URL_HOST);
            if(!is_string($originHost)||strcasecmp($originHost,$host)!==0)return [403,['ok'=>false,'code'=>'ORIGIN_DENIED']];
        }
        if((int)($server['CONTENT_LENGTH']??0)>4096)return [413,['ok'=>false,'code'=>'PAYLOAD_TOO_LARGE']];
        $this->recorder->record($body());
        return [202,['ok'=>true]];
    }
    public function handle(): never
    {
        [$status,$response]=$this->submit($_SERVER,Http::host(),static fn():array=>Http::bodyJson());
        Http::json($response,$status);
    }
}
