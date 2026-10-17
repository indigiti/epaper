<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use EpaperSaas\Core\Config;use EpaperSaas\Services\{DomainResolver,PlatformClient};
$host=(string)(parse_url((string)Config::get('app.url'),PHP_URL_HOST)?:'');foreach($argv as$a)if(str_starts_with($a,'--host='))$host=substr($a,7);$checks=['safe_diagnostic'=>DomainResolver::diagnose($host)];$tenant=(string)Config::get('platform.default_tenant_guid','');$c=PlatformClient::instance();if($tenant){foreach(['tenant','access','entitlements'] as$m)try{$checks[$m]=$c->$m($tenant);}catch(Throwable$e){$checks[$m]=['ok'=>false,'message'=>$e->getMessage()];}}echo json_encode($checks,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
