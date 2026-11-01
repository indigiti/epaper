<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

/** Public clipping response policy: operational errors are never shown verbatim. */
final class ClipApiError
{
    public static function payload(\Throwable $error,string $requestGuid): array
    {
        if($error instanceof \InvalidArgumentException){
            return [
                'ok'=>false,
                'code'=>'CLIP_INVALID',
                'message'=>'Please adjust your clipping selection and try again.',
                'request_guid'=>$requestGuid,
            ];
        }
        return [
            'ok'=>false,
            'code'=>'CLIP_CREATE_FAILED',
            'message'=>'The clipping could not be saved. Please try again.',
            'request_guid'=>$requestGuid,
        ];
    }

    public static function status(\Throwable $error): int
    {
        return $error instanceof \InvalidArgumentException ? 422 : 503;
    }
}
