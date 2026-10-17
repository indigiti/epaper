<?php
declare(strict_types=1);
namespace Digiti\Application\Admin;

use Digiti\Core\{Audit,DomainTools,Store,Uuid};
use Digiti\Application\Integration\CacheInvalidationOutbox;

/** Shared create use cases for the Platform Admin registry. */
final class RegistryCreates
{
    private static function bin(mixed $id):string {
        if(!is_string($id)||!Uuid::isValid($id))throw new \InvalidArgumentException('Invalid UUID');
        return Uuid::toBinary($id);
    }
    private static function label(mixed $raw):string {
        $value=trim((string)$raw);
        if($value===''||mb_strlen($value)>190)throw new \InvalidArgumentException('A name is required');
        return $value;
    }
    public static function organisation(array $input):void {
        $guid=Uuid::v7();$name=self::label($input['name']??'');
        $key=strtoupper(preg_replace('/[^A-Z0-9_]+/','_',strtoupper(trim((string)($input['code']??'')))));
        if($key==='')throw new \InvalidArgumentException('Organisation code required');
        Store::exec('INSERT INTO organisations(organisation_guid,organisation_code,legal_name,display_name,status,created_at,updated_at) VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',[
            self::bin($guid),$key,trim((string)($input['legal_name']??$name)),$name]);
        Audit::log('organisation.create','organisation',$guid);
    }
    public static function product(array $input):void {
        $guid=Uuid::v7();$key=strtoupper(preg_replace('/[^A-Z0-9_]+/','_',strtoupper(trim((string)($input['product_key']??'')))));
        if($key==='')throw new \InvalidArgumentException('Product key required');
        Store::exec('INSERT INTO products(product_guid,product_key,name,status,description,created_at,updated_at) VALUES(?,?,?,"ACTIVE",?,NOW(6),NOW(6))',[
            self::bin($guid),$key,self::label($input['name']??''),trim((string)($input['description']??''))]);
        Audit::log('product.create','product',$guid);
    }
    public static function user(array $input):void {
        $guid=Uuid::v7();$email=strtolower(trim((string)($input['email']??'')));
        $password=(string)($input['password']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<10)
            throw new \InvalidArgumentException('Valid email and password (10+ characters) required');
        $org=(string)($input['organisation_guid']??'');
        $role=(string)($input['role_key']??'OWNER');
        if(!in_array($role,['OWNER','ADMIN','MANAGER','VIEWER'],true))
            throw new \InvalidArgumentException('Invalid organisation role');
        Store::tx(static function()use($guid,$email,$password,$org,$role,$input):void{
            Store::exec('INSERT INTO platform_users(user_guid,email,display_name,password_hash,status,created_at,updated_at) VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',[
                self::bin($guid),$email,self::label($input['name']??''),password_hash($password,PASSWORD_DEFAULT)]);
            if($org!==''){
                if(Store::value('SELECT 1 FROM organisations WHERE organisation_guid=? AND status="ACTIVE"',[self::bin($org)])!==1
                    &&!Store::value('SELECT 1 FROM organisations WHERE organisation_guid=? AND status="ACTIVE"',[self::bin($org)]))
                    throw new \DomainException('Active organisation required');
                Store::exec('INSERT INTO memberships(membership_guid,organisation_guid,user_guid,role_key,status,created_at,updated_at) VALUES(?,?,?,?, "ACTIVE",NOW(6),NOW(6))',[
                    self::bin(Uuid::v7()),self::bin($org),self::bin($guid),$role]);
            }
        });
        Audit::log('user.create','platform_user',$guid);
    }
    public static function application(array $input):string {
        $guid=Uuid::v7();$client='app_'.bin2hex(random_bytes(12));
        $product=(string)($input['product_guid']??'');$base=rtrim(trim((string)($input['base_url']??'')),'/');
        if(!filter_var($base,FILTER_VALIDATE_URL)||parse_url($base,PHP_URL_SCHEME)!=='https')
            throw new \InvalidArgumentException('HTTPS application URL required');
        $callback=trim((string)($input['callback_url']??''));
        if($callback!==''&&(!filter_var($callback,FILTER_VALIDATE_URL)||parse_url($callback,PHP_URL_SCHEME)!=='https'))
            throw new \InvalidArgumentException('HTTPS OAuth callback required');
        Store::tx(static function()use($guid,$client,$product,$base,$callback,$input):void{
            $valid=Store::value('SELECT 1 FROM products WHERE product_guid=? AND status="ACTIVE"',[self::bin($product)]);
            if(!$valid)throw new \DomainException('Active product required');
            Store::exec('INSERT INTO applications(application_guid,product_guid,application_key,name,base_url,admin_url,health_url,callback_url,oauth_client_id,integration_mode,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,"REMOTE_API","ACTIVE",NOW(6),NOW(6))',[
                self::bin($guid),self::bin($product),self::label($input['application_key']??''),self::label($input['name']??''),
                $base,trim((string)($input['admin_url']??''))?:null,trim((string)($input['health_url']??''))?:null,$callback?:null,$client]);
            if($callback!=='')Store::exec('INSERT INTO application_redirect_uris(redirect_guid,application_guid,redirect_uri,status,created_at) VALUES(?,?,?,"ACTIVE",NOW(6)) ON DUPLICATE KEY UPDATE status="ACTIVE"',[self::bin(Uuid::v7()),self::bin($guid),$callback]);
        });
        Audit::log('application.create','application',$guid);
        return $client;
    }
    public static function tenant(array $input):void {
        $guid=Uuid::v7();$org=(string)($input['organisation_guid']??'');$product=(string)($input['product_guid']??'');
        Store::tx(static function()use($guid,$org,$product,$input):void{
            if(!Store::value('SELECT 1 FROM organisations WHERE organisation_guid=? AND status="ACTIVE"',[self::bin($org)])
                ||!Store::value('SELECT 1 FROM products WHERE product_guid=? AND status="ACTIVE"',[self::bin($product)]))
                throw new \DomainException('Active organisation and product required');
            Store::exec('INSERT INTO product_tenants(product_tenant_guid,organisation_guid,product_guid,external_tenant_reference,display_name,status,created_at,updated_at) VALUES(?,?,?,?,?,"ACTIVE",NOW(6),NOW(6))',[
                self::bin($guid),self::bin($org),self::bin($product),trim((string)($input['external_ref']??''))?:null,self::label($input['display_name']??'')]);
        });
        Audit::log('product_tenant.create','product_tenant',$guid);
    }
    public static function domain(array $input):array {
        $host=DomainTools::normalize((string)($input['hostname']??''));
        $tenant=(string)($input['product_tenant_guid']??'');$app=(string)($input['application_guid']??'');
        $guid=Uuid::v7();$token='digiti_'.bin2hex(random_bytes(16));
        Store::tx(static function()use($tenant,$app,$guid,$host,$token,$input):void {
            $row=Store::one('SELECT pt.organisation_guid,pt.product_guid FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid AND o.status="ACTIVE" JOIN products p ON p.product_guid=pt.product_guid AND p.status="ACTIVE" WHERE pt.product_tenant_guid=? AND pt.status="ACTIVE"',[self::bin($tenant)]);
            $application=Store::one('SELECT product_guid FROM applications WHERE application_guid=? AND status="ACTIVE"',[self::bin($app)]);
            if(!$row||!$application||$application['product_guid']!==$row['product_guid'])
                throw new \DomainException('Application/product/tenant mismatch');
            Store::exec('INSERT INTO domains(domain_guid,organisation_guid,product_guid,application_guid,product_tenant_guid,hostname,normalized_hostname,domain_type,status,is_primary,verification_token,created_at,updated_at) VALUES(?,?,?,?,?,?,?,"CUSTOMER_CUSTOM","AWAITING_DNS",?,?,NOW(6),NOW(6))',[
                self::bin($guid),self::bin($row['organisation_guid']),self::bin($row['product_guid']),self::bin($app),self::bin($tenant),
                $host,$host,!empty($input['is_primary'])?1:0,$token]);
            Store::exec('INSERT INTO application_redirect_uris(redirect_guid,application_guid,redirect_uri,status,created_at) VALUES(?,?,?,"ACTIVE",NOW(6)) ON DUPLICATE KEY UPDATE status="ACTIVE"',[self::bin(Uuid::v7()),self::bin($app),'https://'.$host.'/auth/callback']);
            CacheInvalidationOutbox::stageForTenant($tenant);
        });
        Audit::log('domain.create','domain',$guid);
        return ['host'=>$host,'token'=>$token];
    }
    public static function serviceAccount(array $input):string {
        $guid=Uuid::v7();$token='dgt_'.rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');
        $product=trim((string)($input['product_guid']??''));
        $scopes=array_values(array_filter(array_map('trim',explode(',',(string)($input['scopes']??'')))));
        if(!$scopes)throw new \InvalidArgumentException('Service scopes required');
        Store::exec('INSERT INTO service_accounts(service_account_guid,product_guid,name,token_hash,scopes,status,created_at) VALUES(?,?,?,?,?,"ACTIVE",NOW(6))',[
            self::bin($guid),$product!==''?self::bin($product):null,self::label($input['name']??''),hash('sha256',$token,true),
            json_encode($scopes,JSON_THROW_ON_ERROR)]);
        Audit::log('service_account.create','service_account',$guid);
        return $token;
    }
}
