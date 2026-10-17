<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fail=0;foreach(['CITY-EPAPER-COMPATIBILITY-1.1.4.json','READER-BEHAVIORS-1.1.4.json','SETTINGS-MANIFEST-1.1.4.json','PROCESSING-STAGES-1.1.4.json'] as$f){$d=json_decode((string)file_get_contents($root.'/release/'.$f),true);$ok=is_array($d)&&json_last_error()===JSON_ERROR_NONE;echo($ok?'PASS':'FAIL')." — {$f}\n";if(!$ok)$fail++;}exit($fail?1:0);
