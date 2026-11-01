<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/ClipApiError.php';
use EpaperSaas\Services\ClipApiError;
function check(bool $ok,string $case):void {
    if(!$ok){fwrite(STDERR,"FAIL: $case\n");exit(1);}
}
$request='test-request-opaque';
$internal=new \Error('Call to undefined function EpaperSaas\\Services\\link() at /private/path');
$a=ClipApiError::payload($internal,$request);
check(($a['code']??'')==='CLIP_CREATE_FAILED','Internal error code');
check(ClipApiError::status($internal)===503,'Internal error 503');
check(!str_contains(json_encode($a),'link')&&!str_contains(json_encode($a),'private/path'),
  'PHP internal details must not appear in the API response');
check(($a['request_guid']??'')===$request,'Client gets traceable request reference');
$validation=new \InvalidArgumentException('Secret internal detail');
$b=ClipApiError::payload($validation,$request);
check(($b['code']??'')==='CLIP_INVALID'&&ClipApiError::status($validation)===422,'Invalid selection handled as input error');
check(!str_contains(json_encode($b),'Secret internal detail'),'Validation exception also sanitized');
$app=file_get_contents(dirname(__DIR__).'/app/App.php');
check(substr_count($app,'$this->clipCreateFailure($e);')===2,'Guest and subscriber errors share sanitized response handling');
check(str_contains($app,'Http::json(ClipApiError::payload($error,$request),ClipApiError::status($error))'),'Clipping API uses safe error responder');
echo "Clipping API public error safety OK\n";
