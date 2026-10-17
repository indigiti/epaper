<?php
declare(strict_types=1);
namespace Digiti\Application\Admin;

use Digiti\Core\{Audit,Store,Uuid};
use Digiti\Application\Integration\CacheInvalidationOutbox;

/**
 * Admin use cases. Presentation keeps forms/HTML; all registry writes go through
 * validated, transactionally grouped service methods instead of inlined HTTP SQL.
 */
final class RegistryMutations
{
    private static function id(string $guid):string {
        if(!Uuid::isValid($guid))throw new \InvalidArgumentException('Invalid registry identifier');
        return Uuid::toBinary($guid);
    }
    private static function choice(string $value,array $allowed):string {
        if(!in_array($value,$allowed,true))throw new \InvalidArgumentException('Invalid lifecycle value');
        return $value;
    }
    private static function name(mixed $value):string {
        $v=trim((string)$value);
        if($v===''||mb_strlen($v)>190)throw new \InvalidArgumentException('A valid name is required');
        return $v;
    }
    private static function target(string $table,string $column,string $guid):array {
        $allowed=['organisations'=>'organisation_guid','products'=>'product_guid',
            'applications'=>'application_guid','platform_users'=>'user_guid','service_accounts'=>'service_account_guid',
            'features'=>'feature_guid','plans'=>'plan_guid','prices'=>'price_guid'];
        if(($allowed[$table]??null)!==$column)throw new \InvalidArgumentException('Invalid registry target');
        $row=Store::one("SELECT * FROM $table WHERE $column=? FOR UPDATE",[self::id($guid)]);
        if(!$row)throw new \DomainException('Registry record not found');
        return $row;
    }
    private static function lifecycle(string $old,string $requested):string {
        if($old==='REMOVED'&&$requested!=='REMOVED')throw new \DomainException('Removed record cannot be reactivated');
        return self::choice($requested,['ACTIVE','SUSPENDED','REMOVED']);
    }
    public static function organisation(string $action,string $guid,array $input=[]):void
    {
        Store::tx(static function()use($action,$guid,$input):void{
            $row=self::target('organisations','organisation_guid',$guid);
            $status=$action==='remove'?'REMOVED':self::lifecycle($row['status'],
                $action==='status'?self::choice((string)($input['status']??''),['ACTIVE','SUSPENDED']):(string)($input['status']??$row['status']));
            if($action==='edit'){
                $code=strtoupper(preg_replace('/[^A-Z0-9_]+/','_',strtoupper(trim((string)($input['code']??'')))));
                if($code==='')throw new \InvalidArgumentException('Organisation code required');
                $name=self::name($input['display_name']??'');
                Store::exec('UPDATE organisations SET organisation_code=?,legal_name=?,display_name=?,status=?,updated_at=NOW(6) WHERE organisation_guid=?',[$code,trim((string)($input['legal_name']??$name)),$name,$status,self::id($guid)]);
            }else Store::exec('UPDATE organisations SET status=?,updated_at=NOW(6) WHERE organisation_guid=?',[$status,self::id($guid)]);
            if($action==='remove'){
                Store::exec('UPDATE memberships SET status="REMOVED",updated_at=NOW(6) WHERE organisation_guid=?',[self::id($guid)]);
                Store::exec('UPDATE product_tenants SET status="REMOVED",updated_at=NOW(6) WHERE organisation_guid=?',[self::id($guid)]);
                Store::exec('UPDATE domains SET status="REMOVED",is_primary=0,updated_at=NOW(6) WHERE organisation_guid=?',[self::id($guid)]);
            }
            CacheInvalidationOutbox::stageForParent('organisation_guid',$guid);
        });
        Audit::log('organisation.'.$action,'organisation',$guid);
    }
    public static function product(string $action,string $guid,array $input=[]):void
    {
        Store::tx(static function()use($action,$guid,$input):void{
            $row=self::target('products','product_guid',$guid);
            $status=$action==='remove'?'REMOVED':self::lifecycle($row['status'],
                $action==='status'?self::choice((string)($input['status']??''),['ACTIVE','SUSPENDED']):(string)($input['status']??$row['status']));
            if($action==='edit')Store::exec('UPDATE products SET name=?,description=?,status=?,updated_at=NOW(6) WHERE product_guid=?',[self::name($input['name']??''),trim((string)($input['description']??'')),$status,self::id($guid)]);
            else Store::exec('UPDATE products SET status=?,updated_at=NOW(6) WHERE product_guid=?',[$status,self::id($guid)]);
            if($action==='remove'){
                Store::exec('UPDATE applications SET status="REMOVED",updated_at=NOW(6) WHERE product_guid=?',[self::id($guid)]);
                Store::exec('UPDATE product_tenants SET status="REMOVED",updated_at=NOW(6) WHERE product_guid=?',[self::id($guid)]);
                Store::exec('UPDATE domains SET status="REMOVED",is_primary=0,updated_at=NOW(6) WHERE product_guid=?',[self::id($guid)]);
                Store::exec('UPDATE service_accounts SET status="REMOVED" WHERE product_guid=?',[self::id($guid)]);
            }
            CacheInvalidationOutbox::stageForParent('product_guid',$guid);
        });
        Audit::log('product.'.$action,'product',$guid);
    }
    public static function application(string $action,string $guid,array $input=[]):void
    {
        Store::tx(static function()use($action,$guid,$input):void{
            $row=self::target('applications','application_guid',$guid);
            $status=$action==='remove'?'REMOVED':self::lifecycle($row['status'],
                $action==='status'?self::choice((string)($input['status']??''),['ACTIVE','SUSPENDED']):(string)($input['status']??$row['status']));
            if($action==='edit'){
                $base=rtrim(trim((string)($input['base_url']??'')),'/');
                if(!filter_var($base,FILTER_VALIDATE_URL)||parse_url($base,PHP_URL_SCHEME)!=='https')
                    throw new \InvalidArgumentException('HTTPS application base URL required');
                $callback=trim((string)($input['callback_url']??''))?:null;
                if($callback!==null&&(!filter_var($callback,FILTER_VALIDATE_URL)||parse_url($callback,PHP_URL_SCHEME)!=='https'))
                    throw new \InvalidArgumentException('HTTPS callback URL required');
                $mode=self::choice((string)($input['integration_mode']??'REMOTE_API'),['REMOTE_API','LOCAL_MODULE','INTERNAL_API']);
                Store::exec('UPDATE applications SET name=?,base_url=?,admin_url=?,health_url=?,callback_url=?,integration_mode=?,status=?,updated_at=NOW(6) WHERE application_guid=?',[
                    self::name($input['name']??''),$base,trim((string)($input['admin_url']??''))?:null,trim((string)($input['health_url']??''))?:null,$callback,$mode,$status,self::id($guid)
                ]);
                if($callback)Store::exec('INSERT INTO application_redirect_uris(redirect_guid,application_guid,redirect_uri,status,created_at) VALUES(?,?,?,"ACTIVE",NOW(6)) ON DUPLICATE KEY UPDATE status="ACTIVE"',[self::id(Uuid::v7()),self::id($guid),$callback]);
            }else Store::exec('UPDATE applications SET status=?,updated_at=NOW(6) WHERE application_guid=?',[$status,self::id($guid)]);
            if($action==='remove')Store::exec('UPDATE application_redirect_uris SET status="REMOVED" WHERE application_guid=?',[self::id($guid)]);
            CacheInvalidationOutbox::stageForParent('application_guid',$guid);
        });
        Audit::log('application.'.$action,'application',$guid);
    }
    public static function user(string $action,string $guid,array $input=[]):void
    {
        Store::tx(static function()use($action,$guid,$input):void{
            $row=self::target('platform_users','user_guid',$guid);
            $status=$action==='remove'?'REMOVED':self::lifecycle($row['status'],
                $action==='status'?self::choice((string)($input['status']??''),['ACTIVE','SUSPENDED']):(string)($input['status']??$row['status']));
            if($action==='edit'){
                $email=strtolower(trim((string)($input['email']??'')));
                if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Valid email required');
                $password=(string)($input['new_password']??'');
                if($password!==''&&strlen($password)<10)throw new \InvalidArgumentException('New password requires 10+ characters');
                Store::exec('UPDATE platform_users SET email=?,display_name=?,status=?,updated_at=NOW(6) WHERE user_guid=?',[$email,self::name($input['display_name']??''),$status,self::id($guid)]);
                if($password!=='')Store::exec('UPDATE platform_users SET password_hash=?,updated_at=NOW(6) WHERE user_guid=?',[password_hash($password,PASSWORD_DEFAULT),self::id($guid)]);
            }else Store::exec('UPDATE platform_users SET status=?,updated_at=NOW(6) WHERE user_guid=?',[$status,self::id($guid)]);
            if($action==='remove')Store::exec('UPDATE memberships SET status="REMOVED",updated_at=NOW(6) WHERE user_guid=?',[self::id($guid)]);
        });
        Audit::log('user.'.$action,'platform_user',$guid);
    }
    public static function serviceAccount(string $action,string $guid,array $input=[]): ?string
    {
        $token=null;
        Store::tx(static function()use($action,$guid,$input,&$token):void{
            $row=self::target('service_accounts','service_account_guid',$guid);
            if($row['status']==='REMOVED')throw new \DomainException('Removed service account cannot be reactivated');
            if($action==='rotate'){
                $token='dgt_'.rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');
                Store::exec('UPDATE service_accounts SET token_hash=?,status="ACTIVE",rotated_at=NOW(6) WHERE service_account_guid=?',[hash('sha256',$token,true),self::id($guid)]);
            }elseif($action==='edit'){
                $scopes=array_values(array_filter(array_map('trim',explode(',',(string)($input['scopes']??'')))));
                if(!$scopes)throw new \InvalidArgumentException('At least one scope is required');
                $status=self::choice((string)($input['status']??'REVOKED'),['ACTIVE','REVOKED','REMOVED']);
                Store::exec('UPDATE service_accounts SET name=?,scopes=?,status=? WHERE service_account_guid=?',[self::name($input['name']??''),json_encode($scopes,JSON_THROW_ON_ERROR),$status,self::id($guid)]);
            }else{
                $status=$action==='remove'?'REMOVED':self::choice((string)($input['status']??''),['ACTIVE','REVOKED']);
                Store::exec('UPDATE service_accounts SET status=? WHERE service_account_guid=?',[$status,self::id($guid)]);
            }
        });
        Audit::log('service_account.'.$action,'service_account',$guid);
        return $token;
    }
    private static function invalidateCommercial(string $productGuid):void
    {
        CacheInvalidationOutbox::stageForParent('product_guid',$productGuid);
    }
    public static function feature(string $action,?string $guid,array $input=[]):void
    {
        $entity=$guid??Uuid::v7();
        Store::tx(static function()use($action,$guid,$entity,$input):void {
            if($action==='create'){
                $key=strtoupper(trim((string)($input['feature_key']??'')));
                if(!preg_match('/^[A-Z0-9_.-]{3,128}$/D',$key))throw new \InvalidArgumentException('Invalid feature key');
                $product=(string)($input['product_guid']??'');
                Store::exec('INSERT INTO features(feature_guid,product_guid,feature_key,name,feature_type,unit,created_at) VALUES(?,?,?,?,?,?,NOW(6))',[
                    self::id($entity),$product!==''?self::id($product):null,$key,self::name($input['name']??''),
                    self::choice((string)($input['feature_type']??'BOOLEAN'),['BOOLEAN','LIMIT','CONFIG']),trim((string)($input['unit']??''))?:null]);
                if($product!=='')self::invalidateCommercial($product);
                return;
            }
            $row=self::target('features','feature_guid',(string)$guid);
            if($action==='edit')Store::exec('UPDATE features SET name=?,feature_type=?,unit=? WHERE feature_guid=?',[
                self::name($input['name']??''),self::choice((string)($input['feature_type']??'BOOLEAN'),['BOOLEAN','LIMIT','CONFIG']),trim((string)($input['unit']??''))?:null,self::id($entity)]);
            elseif($action==='remove'){
                $count=(int)Store::value('SELECT (SELECT COUNT(*) FROM plan_features WHERE feature_guid=?)+(SELECT COUNT(*) FROM entitlement_grants WHERE feature_guid=?)+(SELECT COUNT(*) FROM usage_meters WHERE feature_guid=?)+(SELECT COUNT(*) FROM usage_events WHERE feature_guid=?)',[self::id($entity),self::id($entity),self::id($entity),self::id($entity)]);
                if($count)throw new \DomainException('Feature has dependent plans or usage and cannot be deleted');
                Store::exec('DELETE FROM features WHERE feature_guid=?',[self::id($entity)]);
            }else throw new \InvalidArgumentException('Unsupported feature operation');
            if(!empty($row['product_guid']))self::invalidateCommercial($row['product_guid']);
        });
        Audit::log('feature.'.$action,'feature',$entity);
    }
    public static function plan(string $action,?string $guid,array $input=[]):void
    {
        $entity=$guid??Uuid::v7();
        Store::tx(static function()use($action,$guid,$entity,$input):void{
            if($action==='create'){
                $key=strtoupper(preg_replace('/[^A-Z0-9_]+/','_',trim((string)($input['plan_key']??''))));
                if($key==='')throw new \InvalidArgumentException('Plan key required');
                $product=(string)($input['product_guid']??'');
                self::id($product);
                Store::exec('INSERT INTO plans(plan_guid,product_guid,plan_key,name,status,created_at,updated_at) VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',[self::id($entity),self::id($product),$key,self::name($input['name']??'')]);
                self::invalidateCommercial($product);return;
            }
            $row=self::target('plans','plan_guid',(string)$guid);
            $status=$action==='remove'?'REMOVED':self::lifecycle($row['status'],(string)($input['status']??$row['status']));
            if($action==='edit')Store::exec('UPDATE plans SET name=?,status=?,updated_at=NOW(6) WHERE plan_guid=?',[self::name($input['name']??''),$status,self::id($entity)]);
            else Store::exec('UPDATE plans SET status=?,updated_at=NOW(6) WHERE plan_guid=?',[$status,self::id($entity)]);
            if($action==='remove')Store::exec('UPDATE prices SET status="REMOVED" WHERE plan_guid=?',[self::id($entity)]);
            self::invalidateCommercial($row['product_guid']);
        });
        Audit::log('plan.'.$action,'plan',$entity);
    }
    public static function price(string $action,string $guid,array $input=[]):void
    {
        $entity=$action==='create'?Uuid::v7():$guid;
        Store::tx(static function()use($action,$guid,$entity,$input):void{
            if($action==='create'){
                $plan=self::target('plans','plan_guid',$guid);
                if($plan['status']!=='ACTIVE')throw new \DomainException('Plan must be active to add price');
                $currency=strtoupper(trim((string)($input['currency']??'INR')));
                if(!preg_match('/^[A-Z]{3}$/D',$currency))throw new \InvalidArgumentException('Invalid currency');
                $raw=trim((string)($input['amount']??''));
                if(!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D',$raw,$m))
                    throw new \InvalidArgumentException('Invalid monetary amount');
                $amount=(int)$m[1]*100+(int)str_pad($m[2]??'0',2,'0');
                $interval=self::choice((string)($input['billing_interval']??'MONTH'),['MONTH','YEAR','WEEK','DAY']);
                $count=filter_var($input['interval_count']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>365]]);
                if($count===false)throw new \InvalidArgumentException('Invalid price interval count');
                Store::exec('INSERT INTO prices(price_guid,plan_guid,currency,amount_minor,billing_interval,interval_count,status,created_at) VALUES(?,?,?,?,?,?,"ACTIVE",NOW(6))',[self::id($entity),self::id($guid),$currency,$amount,$interval,$count]);
                self::invalidateCommercial($plan['product_guid']);return;
            }
            $row=self::target('prices','price_guid',$guid);
            $status=self::choice((string)($input['status']??''),['ACTIVE','SUSPENDED','REMOVED']);
            if($row['status']==='REMOVED'&&$status!=='REMOVED')throw new \DomainException('Removed price cannot be enabled');
            Store::exec('UPDATE prices SET status=? WHERE price_guid=?',[$status,self::id($guid)]);
            $plan=self::target('plans','plan_guid',$row['plan_guid']);
            self::invalidateCommercial($plan['product_guid']);
        });
        Audit::log('price.'.$action,'price',$entity);
    }
}
