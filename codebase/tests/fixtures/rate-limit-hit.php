<?php
declare(strict_types=1);
/** Child process for cross-process limiter test, with no production access. */
require_once dirname(__DIR__,2).'/app/Core/Config.php';
require_once dirname(__DIR__,2).'/app/Core/RedisStore.php';
require_once dirname(__DIR__,2).'/app/Core/RateLimiter.php';
if(($argc??0)!==4){fwrite(STDERR,"Arguments missing\n");exit(2);}
\EpaperSaas\Core\Config::load((string)$argv[1]);
$allowed=\EpaperSaas\Core\RateLimiter::allow((string)$argv[2],(int)$argv[3],120);
echo $allowed?'ALLOW':'DENY';
