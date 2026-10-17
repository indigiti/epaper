<?php
$root=require dirname(__DIR__).'/app/bootstrap.php';
use Digiti\Core\{Auth,Audit,Database,Http,Store,Uuid,View};
Auth::requireAdmin();
$key='platform-1.0.1-admin-crud';
$applied=(int)Store::value('SELECT COUNT(*) FROM schema_migrations WHERE migration_key=?',[$key])>0;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    Http::verifyCsrf();
    if(!$applied){
        Store::tx(function()use($key){
            Store::exec('UPDATE products SET name="ePaper SaaS",description="Multi-tenant digital newspaper and ePaper SaaS platform",updated_at=NOW(6) WHERE product_key="EPAPER" AND name IN ("City ePaper","ePaper SaaS")');
            Store::exec('UPDATE applications a JOIN products p ON p.product_guid=a.product_guid SET a.name="ePaper SaaS",a.updated_at=NOW(6) WHERE p.product_key="EPAPER" AND a.application_key="city_epaper"');
            Store::exec('INSERT INTO schema_migrations(migration_key,applied_at) VALUES(?,NOW(6))',[$key]);
        });
        $lock=$root.'/storage/install.lock';if(is_file($lock)){$data=json_decode((string)file_get_contents($lock),true)?:[];$data['version']='1.0.1';$data['updated_at']=gmdate('c');file_put_contents($lock,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);}
        Audit::log('platform.update','platform',null,'SUCCESS',['version'=>'1.0.1','migration'=>$key]);
        View::flash('Digiti Platform v1.0.1 management update applied. EPAPER renamed to ePaper SaaS.');
        Http::redirect('/admin/products');
    }
}
$csrf=Http::csrf();$body='<section class="card"><h2>Digiti Platform v1.0.1</h2><p>This update enables Add, Edit, Update, Activate/Deactivate, Remove and token rotation controls across the Platform registries and renames the EPAPER display name from <strong>City ePaper</strong> to <strong>ePaper SaaS</strong>.</p>'.($applied?'<div class="alert"><strong>Already applied.</strong> No database action is required.</div>':'<form method="post"><input type="hidden" name="_csrf" value="'.View::esc($csrf).'"><button class="primary">Apply v1.0.1 update</button></form>').'<p class="muted">This update does not change the stable product key <code>EPAPER</code> or application key <code>city_epaper</code>.</p></section>';
View::layout('Platform Update',$body,['subtitle'=>'Admin management controls and ePaper SaaS rename']);
