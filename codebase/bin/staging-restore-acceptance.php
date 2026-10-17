<?php
declare(strict_types=1);
/**
 * READ-ONLY, isolated staging DB/file restore acceptance.
 * Must run with private external staging configuration; no writes or PII output.
 *
 * Usage:
 * EPDI_STAGING_RESTORE_ONLY=1 EPDI_STAGING_DB_NAME=expected_restore_db \
 * php bin/staging-restore-acceptance.php
 */
if(PHP_SAPI!=='cli'){http_response_code(403);exit(2);}
$root=dirname(__DIR__);
require $root.'/app/bootstrap.php';
require_once $root.'/app/Core/RestoreReferenceAudit.php';

use EpaperSaas\Core\{Config,Database,RestoreReferenceAudit};
$confirm=(string)(getenv('EPDI_STAGING_RESTORE_ONLY')?:'');
$expected=trim((string)(getenv('EPDI_STAGING_DB_NAME')?:''));
$name=(string)Config::get('db.name','');
if($confirm!=='1'||$expected===''||$name===''||!hash_equals($expected,$name)){
    fwrite(STDERR,"Refusing restore acceptance: explicitly confirm isolated staging and exact restored DB name.\n");
    exit(2);
}
$storage=$root.'/storage';
$report=['ok'=>false,'staging_confirmed'=>true,'db_name_match'=>true,
    'database_counts'=>[],'file_references'=>[],'orphans'=>[],
    'note'=>'Read-only counts/reference checks; no rows, identities, paths or credentials included'];
try{
    $pdo=Database::pdo();
    // The database session itself is read-only in addition to running SELECTs only.
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $tables=['ep_tenants','ep_publications','ep_editions','ep_pages','ep_page_assets',
        'ep_clips','ep_offline_licenses','ep_processing_jobs'];
    foreach($tables as$table){
        $report['database_counts'][$table]=(int)$pdo->query("SELECT COUNT(*) FROM ".$table)->fetchColumn();
    }
    $checks=[
        'assets'=>'SELECT storage_path AS relative_path FROM ep_page_assets',
        'original_pdfs'=>"SELECT source_pdf_path AS relative_path FROM ep_editions WHERE source_pdf_path IS NOT NULL AND source_pdf_path<>''",
        'active_clips'=>"SELECT storage_path AS relative_path FROM ep_clips WHERE storage_path IS NOT NULL AND storage_path<>'' AND (expires_at IS NULL OR expires_at > NOW(6))"
    ];
    $valid=true;
    foreach($checks as$category=>$query){
        $rows=$pdo->query($query);
        $found=0;$missing=0;
        while(($row=$rows->fetch(PDO::FETCH_ASSOC))!==false){
            $found++;
            if(!RestoreReferenceAudit::exists($storage,(string)($row['relative_path']??'')))$missing++;
        }
        $report['file_references'][$category]=['checked'=>$found,'missing_or_unreadable'=>$missing];
        if($missing>0)$valid=false;
    }
    $orphanQueries=[
        'editions_without_publications'=>
            'SELECT COUNT(*) FROM ep_editions e LEFT JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid WHERE p.publication_guid IS NULL',
        'pages_without_editions'=>
            'SELECT COUNT(*) FROM ep_pages p LEFT JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid WHERE e.edition_guid IS NULL',
        'assets_without_pages'=>
            'SELECT COUNT(*) FROM ep_page_assets a LEFT JOIN ep_pages p ON p.tenant_guid=a.tenant_guid AND p.page_guid=a.page_guid WHERE p.page_guid IS NULL',
        'clips_without_pages'=>
            'SELECT COUNT(*) FROM ep_clips c LEFT JOIN ep_pages p ON p.tenant_guid=c.tenant_guid AND p.page_guid=c.page_guid WHERE p.page_guid IS NULL',
        'offline_licenses_without_editions'=>
            'SELECT COUNT(*) FROM ep_offline_licenses l LEFT JOIN ep_editions e ON e.tenant_guid=l.tenant_guid AND e.edition_guid=l.edition_guid WHERE e.edition_guid IS NULL'
    ];
    foreach($orphanQueries as$label=>$sql){
        $orphan=(int)$pdo->query($sql)->fetchColumn();
        $report['orphans'][$label]=$orphan;
        if($orphan>0)$valid=false;
    }
    // A blank newly installed database cannot certify a restore of existing media.
    foreach(['ep_editions','ep_pages','ep_page_assets'] as$table)
        if(($report['database_counts'][$table]??0)<1)$valid=false;
    if(($report['file_references']['original_pdfs']['checked']??0)<1)$valid=false;
    $report['ok']=$valid;
    $report['browser_acceptance_verified']=false;
    $report['cloudways_backup_integrity_verified']=false;
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit($valid?0:1);
} catch(Throwable){
    // Even SQL/PDO error text can expose database names or connection secrets.
    fwrite(STDERR,"Staging database/media audit was unable to complete. Verify isolated staging schema, DB permissions and storage.\n");
    exit(2);
}
