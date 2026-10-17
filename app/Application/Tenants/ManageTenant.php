<?php
declare(strict_types=1);
namespace Digiti\Application\Tenants;

use Digiti\Core\{Audit,RedisStore,Uuid};
use Digiti\Infrastructure\Tenants\TenantRepository;
use Digiti\Domain\Tenants\TenantLifecyclePolicy;

final class ManageTenant
{
    public static function edit(string $guid,array $input,?TenantRepository $repository=null): void
    {
        if(!Uuid::isValid($guid))throw new \InvalidArgumentException('Invalid tenant ID.');
        $name=trim((string)($input['display_name']??''));
        if($name===''||mb_strlen($name)>190)throw new \InvalidArgumentException('Tenant name must be between 1 and 190 characters.');
        $external=trim((string)($input['external_ref']??''));
        if(strlen($external)>190)throw new \InvalidArgumentException('External reference too long.');
        self::change($guid,(string)($input['status']??''),$name,$external!==''?$external:null,$repository,'update');
    }
    public static function status(string $guid,string $status,?TenantRepository $repository=null): void
    {
        if($status==='REMOVED')throw new \InvalidArgumentException('Use the explicit remove action for tenant removal.');
        self::change($guid,$status,null,null,$repository,'status');
    }
    public static function remove(string $guid,?TenantRepository $repository=null): void
    {
        self::change($guid,'REMOVED',null,null,$repository,'remove');
    }
    private static function change(string $guid,string $status,?string $name,?string $external,?TenantRepository $repository,string $operation): void
    {
        if(!Uuid::isValid($guid))throw new \InvalidArgumentException('Invalid tenant ID.');
        $hosts=($repository??new TenantRepository())->change($guid,$status,$name,$external);
        RedisStore::del('entitlements:'.$guid);
        foreach($hosts as $hostname)RedisStore::del('domain:'.$hostname);
        Audit::log('product_tenant.'.$operation,'product_tenant',$guid,'SUCCESS',['status'=>$status]);
    }
}
