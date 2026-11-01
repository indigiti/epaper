<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Auth,Http,RedisStore,Store,Uuid,View};

/**
 * Retention-aware cleanup. Audit, invoices, payments, refunds, usage and
 * subscription references survive. Archived operational records do not keep
 * unique keys/hostnames reserved forever.
 *
 * No external ePaper media is touched. All writes are within a single SQL
 * transaction, with a mandatory durable audit row.
 */
final class RegistryCleanup
{
    private const TYPES=['domains','products','organisations'];

    private static function bin(string $guid): string { return Uuid::toBinary($guid); }
    private static function code(string $guid): string { return 'RETIRED_'.strtoupper(str_replace('-','',$guid)); }
    private static function table(string $type): array {
        return match($type){
            'domains'=>['domains','domain_guid','hostname'],
            'products'=>['products','product_guid','product_key'],
            'organisations'=>['organisations','organisation_guid','organisation_code'],
            default=>throw new \InvalidArgumentException('Unsupported cleanup record')
        };
    }

    private static function superAdmin(): array
    {
        $admin=Auth::requireAdmin();
        $role=Store::value('SELECT role_key FROM platform_admins WHERE admin_guid=?',[self::bin($admin['admin_guid'])]);
        if($role!=='SUPER_ADMIN')throw new \InvalidArgumentException('Only Platform SUPER_ADMIN may permanently release archived records.');
        return $admin;
    }

    private static function snapshot(string $type,string $guid,bool $lock=false): array
    {
        [$table,$column,$labelCol]=self::table($type);
        $row=Store::one("SELECT * FROM $table WHERE $column=?".($lock?' FOR UPDATE':''),[self::bin($guid)]);
        if(!$row)return ['exists'=>false,'blockers'=>['Record not found'],'counts'=>[]];
        $blockers=[];
        if($row['status']!=='REMOVED')$blockers[]='Archive the existing record before cleanup. Active and suspended records cannot be purged.';
        if($type!=='domains' && str_starts_with((string)$row[$labelCol],'RETIRED_'))$blockers[]='This record has already released its identifier; only its minimal historical reference is retained.';
        $counts=[];
        $bin=self::bin($guid);
        if($type==='domains') {
            $counts['Archived domain records']=1;
            $counts['Financial and audit records preserved']='All';
        } elseif($type==='organisations') {
            $counts['Product tenants']=(int)Store::value('SELECT COUNT(*) FROM product_tenants WHERE organisation_guid=?',[$bin]);
            $counts['Memberships']=(int)Store::value('SELECT COUNT(*) FROM memberships WHERE organisation_guid=?',[$bin]);
            $counts['Domains']=(int)Store::value('SELECT COUNT(*) FROM domains WHERE organisation_guid=?',[$bin]);
            $active=(int)Store::value('SELECT COUNT(*) FROM product_tenants WHERE organisation_guid=? AND status<>"REMOVED"',[$bin])
                +(int)Store::value('SELECT COUNT(*) FROM memberships WHERE organisation_guid=? AND status<>"REMOVED"',[$bin])
                +(int)Store::value('SELECT COUNT(*) FROM domains WHERE organisation_guid=? AND status<>"REMOVED"',[$bin]);
            if($active>0)$blockers[]='Active tenants or memberships are still linked to this organisation.';
        } elseif($type==='products') {
            $queries=[
                'Domain registrations'=>'SELECT COUNT(*) FROM domains WHERE product_guid=?',
                'Applications'=>'SELECT COUNT(*) FROM applications WHERE product_guid=?',
                'Publisher tenants'=>'SELECT COUNT(*) FROM product_tenants WHERE product_guid=?',
                'Service accounts'=>'SELECT COUNT(*) FROM service_accounts WHERE product_guid=?',
                'Plans'=>'SELECT COUNT(*) FROM plans WHERE product_guid=?',
                'Features'=>'SELECT COUNT(*) FROM features WHERE product_guid=?',
                'Subscriptions retained'=>'SELECT COUNT(*) FROM subscriptions s JOIN product_tenants pt ON pt.product_tenant_guid=s.product_tenant_guid WHERE pt.product_guid=?',
                'Invoices retained'=>'SELECT COUNT(*) FROM invoices i JOIN product_tenants pt ON pt.product_tenant_guid=i.product_tenant_guid WHERE pt.product_guid=?',
                'Payments retained'=>'SELECT COUNT(*) FROM payments p JOIN product_tenants pt ON pt.product_tenant_guid=p.product_tenant_guid WHERE pt.product_guid=?',
                'Usage meters retained'=>'SELECT COUNT(*) FROM usage_meters u JOIN product_tenants pt ON pt.product_tenant_guid=u.product_tenant_guid WHERE pt.product_guid=?',
            ];
            foreach($queries as $label=>$sql)$counts[$label]=(int)Store::value($sql,[$bin]);
            $activeSql=[
                'domain'=>'SELECT COUNT(*) FROM domains WHERE product_guid=? AND status<>"REMOVED"',
                'application'=>'SELECT COUNT(*) FROM applications WHERE product_guid=? AND status<>"REMOVED"',
                'publisher tenant'=>'SELECT COUNT(*) FROM product_tenants WHERE product_guid=? AND status<>"REMOVED"',
                'service account'=>'SELECT COUNT(*) FROM service_accounts WHERE product_guid=? AND status<>"REMOVED"'
            ];
            foreach($activeSql as $label=>$sql)
                if((int)Store::value($sql,[$bin])>0)$blockers[]='A '.$label.' is not archived. Archive all linked '.$label.' records first.';
            $openSubs=(int)Store::value('SELECT COUNT(*) FROM subscriptions s JOIN product_tenants pt ON pt.product_tenant_guid=s.product_tenant_guid WHERE pt.product_guid=? AND s.status IN ("ACTIVE","TRIALING","PENDING","PAST_DUE")',[$bin]);
            if($openSubs)$blockers[]=$openSubs.' subscriptions are still active, pending or past due. Settle/cancel them before cleanup.';
            $openPayment=(int)Store::value('SELECT COUNT(*) FROM payments p JOIN product_tenants pt ON pt.product_tenant_guid=p.product_tenant_guid WHERE pt.product_guid=? AND p.status IN ("CREATED","PENDING","PROCESSING","AUTHORIZED")',[$bin]);
            if($openPayment)$blockers[]=$openPayment.' payments are still pending or processing.';
            $openRefund=(int)Store::value('SELECT COUNT(*) FROM refunds r JOIN payments p ON p.payment_guid=r.payment_guid JOIN product_tenants pt ON pt.product_tenant_guid=p.product_tenant_guid WHERE pt.product_guid=? AND r.status IN ("REQUESTED","PENDING","PROCESSING")',[$bin]);
            if($openRefund)$blockers[]=$openRefund.' refunds are not yet settled.';
        }
        return [
            'exists'=>true,'type'=>$type,'guid'=>$guid,'label'=>(string)$row[$labelCol],
            'row'=>$row,'counts'=>$counts,'blockers'=>$blockers
        ];
    }

    public static function run(string $type,string $guid,string $method): never
    {
        $admin=self::superAdmin();
        if(!in_array($type,self::TYPES,true)||!preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i',$guid))
            throw new \InvalidArgumentException('Select an archived domain, product or organisation.');
        if(!in_array($method,['GET','POST'],true)){
            http_response_code(405);header('Allow: GET, POST');
            View::layout('Method not allowed','<section class="card">Open the cleanup review first.</section>');
        }
        $info=self::snapshot($type,$guid);
        if(!$info['exists'])throw new \InvalidArgumentException('The archived record was already deleted.');
        if($method==='GET')self::review($info);
        if($info['blockers'])throw new \InvalidArgumentException('Cleanup is blocked. Reopen the review and resolve its dependencies.');
        $label=$info['label'];
        $entered=trim((string)($_POST['confirm_record']??''));
        if($entered!==$label || ($_POST['ack_backup']??'')!=='1' || ($_POST['ack_permanent']??'')!=='1')
            throw new \InvalidArgumentException('Type the exact record identifier and check both confirmations.');
        if($type==='products' && ($_POST['ack_billing']??'')!=='1')
            throw new \InvalidArgumentException('Confirm that retained billing records remain linked to historical GUIDs.');
        $purgedHostnames=[];
        Store::tx(function()use($type,$guid,$label,$admin,&$purgedHostnames):void{
            $check=self::snapshot($type,$guid,true);
            if(!$check['exists'] || $check['blockers'] || $check['label']!==$label)
                throw new \RuntimeException('Records changed since preview. Reload and review cleanup again.');
            if($type==='domains') {
                $purgedHostnames=[(string)$check['row']['hostname']];
                Store::exec('DELETE FROM domains WHERE domain_guid=? AND status="REMOVED"',[self::bin($guid)]);
                $impact=['deleted_domains'=>1];
            } elseif($type==='products') {
                $impact=self::releaseProduct($guid,$purgedHostnames);
            } else {
                $impact=self::releaseOrganisation($guid);
            }
            self::audit($admin,$type,$guid,$label,$impact);
        });
        foreach(array_unique($purgedHostnames) as $host)RedisStore::del('domain:'.$host);
        if($type==='products')RedisStore::del('product:'.$guid);
        View::flash($type==='domains'
            ?'Archived hostname deleted and released. Historical audit remains; cached ePaper routing may persist until refreshed.'
            :'Archived configuration cleaned up and unique keys released. Billing/audit references were preserved.');
        Http::redirect('/admin/'.$type);
    }

    private static function review(array $info): never
    {
        $type=$info['type'];$label=View::esc($info['label']);$guid=$info['guid'];
        $blockers='';
        foreach($info['blockers'] as $message)$blockers.='<li>'.View::esc($message).'</li>';
        $blockers=$blockers?'<div class="platform-purge-warning" role="alert"><strong>Cleanup blocked</strong><ul>'.$blockers.'</ul></div>':'';
        $stats='';
        foreach($info['counts'] as $name=>$value)
            $stats.='<li><span>'.View::esc($name).'</span><strong>'.View::esc((string)$value).'</strong></li>';
        $description=match($type){
            'domains'=>'Delete this archived hostname permanently and free its unique address. Its product and tenant remain unchanged.',
            'products'=>'Delete archived operational configuration where possible; retain minimal historical identity rows only when billing references require them. Release the original product key and related application/feature keys.',
            'organisations'=>'Release this archived organisation code; preserve a minimal historical organisation identity if linked records still reference it.'
        };
        $body='<section class="card platform-purge-review"><h2>Release archived '.View::esc(rtrim($type,'s')).'</h2>'
            .'<p><strong>'. $label .'</strong> · '.View::esc($description).'</p>'
            .'<p><strong>Never deleted:</strong> invoices, payments, refunds, subscriptions, billing usage, audit and error logs. Original GUID references are preserved.</p>'
            .'<p><strong>Important:</strong> this does not remove ePaper image/PDF/media files from the separate application or immediately clear its independent domain cache.</p>'
            .'<ul class="platform-purge-counts">'.$stats.'</ul>'
            .$blockers
            .'<form method="post" action="/admin/'.View::esc($type).'/'.rawurlencode($guid).'/release">'
            .'<input type="hidden" name="_csrf" value="'.View::esc(Http::csrf()).'">'
            .'<label>Type <strong>'.$label.'</strong> to release permanently'
            .'<input name="confirm_record" autocomplete="off" required placeholder="'.$label.'"></label>'
            .'<label><input type="checkbox" name="ack_backup" value="1" required> A verified database and media backup is available.</label>'
            .'<label><input type="checkbox" name="ack_permanent" value="1" required> I understand archived configuration and reusable keys are permanently released.</label>'
            .($type==='products'?'<label><input type="checkbox" name="ack_billing" value="1" required> Preserve existing invoice, payment, subscription and audit references; do not delete them.</label>':'')
            .'<div class="form-action"><button class="danger" type="submit"'.($info['blockers']?' disabled':'')
            .' data-confirm="Irreversibly release archived configuration and reserved keys?">Release archived resources</button>'
            .'<a class="button" href="/admin/'.View::esc($type).'">Cancel</a></div></form></section>';
        View::layout('Release archived resources',$body,['subtitle'=>$info['label']]);
    }

    /** Remove archived operational resources, preserve only FK-backed historical identities. */
    private static function releaseProduct(string $guid,array &$hostnames): array
    {
        $bin=self::bin($guid);
        $hostnames=array_column(Store::all('SELECT hostname FROM domains WHERE product_guid=?',[$bin]),'hostname');
        // Safe because the preview + transaction require every linked domain archived.
        $deletedDomains=Store::exec('DELETE FROM domains WHERE product_guid=? AND status="REMOVED"',[$bin]);
        $apps=Store::all('SELECT application_guid FROM applications WHERE product_guid=?',[$bin]);
        foreach($apps as $app){
            $appBin=self::bin($app['application_guid']);
            Store::exec('DELETE FROM auth_codes WHERE application_guid=?',[$appBin]);
            Store::exec('DELETE FROM application_heartbeats WHERE application_guid=?',[$appBin]);
            Store::exec('DELETE FROM application_redirect_uris WHERE application_guid=?',[$appBin]);
        }
        Store::exec('DELETE FROM applications WHERE product_guid=? AND status="REMOVED"',[$bin]);
        Store::exec('DELETE FROM service_accounts WHERE product_guid=? AND status="REMOVED"',[$bin]);
        // Entitlements are operational grants; metered usage is billing history.
        Store::exec('DELETE g FROM entitlement_grants g JOIN product_tenants pt
            ON pt.product_tenant_guid=g.product_tenant_guid WHERE pt.product_guid=?',[$bin]);
        // A retired product cannot sell new plans. Retire its plans as part of cleanup.
        Store::exec('UPDATE plans SET status="REMOVED",updated_at=NOW(6) WHERE product_guid=?',[$bin]);
        // Remove only plans never used by a subscription. Keep financial plans and prices.
        $unused=Store::all('SELECT pl.plan_guid FROM plans pl WHERE pl.product_guid=?
            AND NOT EXISTS (SELECT 1 FROM subscriptions s WHERE s.plan_guid=pl.plan_guid)',[$bin]);
        foreach($unused as $plan){
            $planBin=self::bin($plan['plan_guid']);
            Store::exec('DELETE FROM plan_features WHERE plan_guid=?',[$planBin]);
            Store::exec('DELETE FROM prices WHERE plan_guid=?',[$planBin]);
            Store::exec('DELETE FROM plans WHERE plan_guid=? AND status="REMOVED"',[$planBin]);
        }
        // Features tied to retained plans/usage must remain so retained billing
        // records have resolvable references. Free their old global feature keys.
        $features=Store::all('SELECT feature_guid FROM features WHERE product_guid=?',[$bin]);
        foreach($features as $feature){
            $id=$feature['feature_guid'];$featureBin=self::bin($id);
            $references=(int)Store::value('SELECT COUNT(*) FROM plan_features WHERE feature_guid=?',[$featureBin])
                +(int)Store::value('SELECT COUNT(*) FROM usage_meters WHERE feature_guid=?',[$featureBin])
                +(int)Store::value('SELECT COUNT(*) FROM entitlement_grants WHERE feature_guid=?',[$featureBin]);
            if($references)Store::exec('UPDATE features SET feature_key=? WHERE feature_guid=?',[
                self::code($id),$featureBin
            ]);
            else Store::exec('DELETE FROM features WHERE feature_guid=?',[$featureBin]);
        }
        // Product-specific permissions and roles have no financial references.
        Store::exec('DELETE rp FROM role_permissions rp JOIN roles r ON r.role_guid=rp.role_guid WHERE r.product_guid=?',[$bin]);
        Store::exec('DELETE FROM roles WHERE product_guid=?',[$bin]);
        $tenants=Store::all('SELECT product_tenant_guid FROM product_tenants WHERE product_guid=?',[$bin]);
        $keptTenants=0;
        foreach($tenants as $tenant){
            $tid=self::bin($tenant['product_tenant_guid']);
            $history=0;
            foreach(['subscriptions','invoices','payments','usage_meters'] as $table)
                $history+=(int)Store::value("SELECT COUNT(*) FROM $table WHERE product_tenant_guid=?",[$tid]);
            if($history){
                $keptTenants++;
                Store::exec('UPDATE product_tenants SET status="REMOVED"
                    WHERE product_tenant_guid=? AND status="REMOVED"',[$tid]);
            }else Store::exec('DELETE FROM product_tenants WHERE product_tenant_guid=? AND status="REMOVED"',[$tid]);
        }
        // Any retained plan or tenant is a financial FK lineage, so preserve a
        // tiny product tombstone with original GUID but NOT original unique key.
        $retained=(int)Store::value('SELECT COUNT(*) FROM product_tenants WHERE product_guid=?',[$bin])
            +(int)Store::value('SELECT COUNT(*) FROM plans WHERE product_guid=?',[$bin])
            +(int)Store::value('SELECT COUNT(*) FROM features WHERE product_guid=?',[$bin]);
        if($retained){
            Store::exec('UPDATE products SET product_key=?,description=NULL,
                updated_at=NOW(6) WHERE product_guid=? AND status="REMOVED"',[
                self::code($guid),$bin
            ]);
        }else {
            Store::exec('DELETE FROM products WHERE product_guid=? AND status="REMOVED"',[$bin]);
        }
        return [
            'deleted_domains'=>$deletedDomains,
            'deleted_applications'=>count($apps),
            'retained_billing_tenants'=>$keptTenants,
            'product_identity_tombstone'=>$retained>0
        ];
    }

    private static function releaseOrganisation(string $guid): array
    {
        $bin=self::bin($guid);
        $links=(int)Store::value('SELECT COUNT(*) FROM product_tenants WHERE organisation_guid=?',[$bin])
            +(int)Store::value('SELECT COUNT(*) FROM domains WHERE organisation_guid=?',[$bin])
            +(int)Store::value('SELECT COUNT(*) FROM memberships WHERE organisation_guid=?',[$bin])
            +(int)Store::value('SELECT COUNT(*) FROM impersonation_grants WHERE organisation_guid=?',[$bin]);
        if($links){
            Store::exec('UPDATE organisations SET organisation_code=?,metadata=NULL,updated_at=NOW(6)
                WHERE organisation_guid=? AND status="REMOVED"',[self::code($guid),$bin]);
        }else Store::exec('DELETE FROM organisations WHERE organisation_guid=? AND status="REMOVED"',[$bin]);
        return ['historical_identity_retained'=>$links>0,'reusable_code_released'=>true];
    }

    private static function audit(array $admin,string $type,string $guid,string $label,array $impact): void
    {
        Store::exec('INSERT INTO audit_events(audit_event_guid,occurred_at,actor_type,actor_user_guid,
            action,resource_type,resource_guid,result,request_guid,ip_address,user_agent,before_data,metadata)
            VALUES(?,NOW(6),"PLATFORM_ADMIN",?,"registry.release",?,?,"SUCCESS",?,?,?,?,?)',[
            self::bin(Uuid::v7()),self::bin($admin['admin_guid']),
            rtrim($type,'s'),self::bin($guid),self::bin(Http::requestGuid()),
            (string)($_SERVER['REMOTE_ADDR']??''),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255),
            json_encode(['identifier'=>$label],JSON_UNESCAPED_SLASHES),
            json_encode(['impact'=>$impact,'billing_preserved'=>true,'audit_preserved'=>true],JSON_UNESCAPED_SLASHES)
        ]);
    }
}
