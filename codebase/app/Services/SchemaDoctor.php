<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Build,Config,Database,RedisStore,RequestContext,Store};

/**
 * Safe, version-aware database preflight and repair service.
 *
 * Only known EP-DI upgrade steps are auto-repaired. It never attempts to
 * recreate an unknown/incomplete base installation from schema.sql.
 */
final class SchemaDoctor
{
    private const CACHE_KEY = 'schema:doctor:v2:20181';

    private const ALPHA2_TABLES = [
        'ep_navigation_scopes',
        'ep_navigation_items',
        'ep_navigation_versions',
    ];

    private const ALPHA2_COLUMNS = [
        'ep_navigation_scopes' => [
            'nav_scope_guid' => 'BINARY(16) NOT NULL',
            'tenant_guid' => 'BINARY(16) NOT NULL',
            'scope_key' => 'VARCHAR(180) NOT NULL',
            'name' => 'VARCHAR(190) NOT NULL',
            'publication_guid' => 'BINARY(16) NULL',
            'city_guid' => 'BINARY(16) NULL',
            'language_guid' => 'BINARY(16) NULL',
            'header_style' => "VARCHAR(40) NOT NULL DEFAULT 'inherit'",
            'sticky_behavior' => "VARCHAR(40) NOT NULL DEFAULT 'hide_down_reveal_up'",
            'status' => "VARCHAR(40) NOT NULL DEFAULT 'DRAFT'",
            'draft_revision' => 'BIGINT UNSIGNED NOT NULL DEFAULT 1',
            'published_revision' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'published_by_user_guid' => 'BINARY(16) NULL',
            'published_at' => 'DATETIME(6) NULL',
            'created_at' => 'DATETIME(6) NULL',
            'updated_at' => 'DATETIME(6) NULL',
        ],
        'ep_navigation_items' => [
            'nav_item_guid' => 'BINARY(16) NOT NULL',
            'tenant_guid' => 'BINARY(16) NOT NULL',
            'nav_scope_guid' => 'BINARY(16) NOT NULL',
            'row_type' => "VARCHAR(24) NOT NULL DEFAULT 'PRIMARY'",
            'label' => "VARCHAR(160) NOT NULL DEFAULT ''",
            'destination_type' => "VARCHAR(40) NOT NULL DEFAULT 'CUSTOM_URL'",
            'custom_url' => 'VARCHAR(700) NULL',
            'badge' => 'VARCHAR(40) NULL',
            'visibility' => "VARCHAR(24) NOT NULL DEFAULT 'ALL'",
            'open_mode' => "VARCHAR(24) NOT NULL DEFAULT 'SAME_TAB'",
            'enabled' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'sort_order' => 'INT NOT NULL DEFAULT 0',
            'starts_at' => 'DATETIME(6) NULL',
            'expires_at' => 'DATETIME(6) NULL',
            'created_at' => 'DATETIME(6) NULL',
            'updated_at' => 'DATETIME(6) NULL',
        ],
        'ep_navigation_versions' => [
            'nav_version_guid' => 'BINARY(16) NOT NULL',
            'tenant_guid' => 'BINARY(16) NOT NULL',
            'nav_scope_guid' => 'BINARY(16) NOT NULL',
            'version_number' => 'BIGINT UNSIGNED NOT NULL DEFAULT 1',
            'compiled_json' => 'LONGTEXT NULL',
            'published_by_user_guid' => 'BINARY(16) NULL',
            'published_at' => 'DATETIME(6) NULL',
        ],
    ];

    private const ALPHA2_INDEXES = [
        'ep_navigation_scopes' => [
            'PRIMARY' => ['unique'=>true,'columns'=>['nav_scope_guid']],
            'uk_navigation_scope' => ['unique'=>true,'columns'=>['tenant_guid','scope_key']],
            'ix_navigation_scope_status' => ['unique'=>false,'columns'=>['tenant_guid','status','updated_at']],
        ],
        'ep_navigation_items' => [
            'PRIMARY' => ['unique'=>true,'columns'=>['nav_item_guid']],
            'ix_navigation_item_row' => ['unique'=>false,'columns'=>['tenant_guid','nav_scope_guid','row_type','sort_order']],
            'ix_navigation_item_active' => ['unique'=>false,'columns'=>['tenant_guid','nav_scope_guid','enabled','starts_at','expires_at']],
        ],
        'ep_navigation_versions' => [
            'PRIMARY' => ['unique'=>true,'columns'=>['nav_version_guid']],
            'uk_navigation_version' => ['unique'=>true,'columns'=>['tenant_guid','nav_scope_guid','version_number']],
            'ix_navigation_version_recent' => ['unique'=>false,'columns'=>['tenant_guid','nav_scope_guid','published_at']],
        ],
    ];

    private const BETA11_COLUMNS = [
        'publish_mode' => "VARCHAR(24) NOT NULL DEFAULT 'MANUAL'",
        'schedule_timezone' => 'VARCHAR(64) NULL',
    ];

    private const BETA11_INDEXES = [
        'ep_editions' => [
            'ix_edition_publish_schedule' => ['unique'=>false,'columns'=>['tenant_guid','publish_mode','status','scheduled_at']],
        ],
        'ep_processing_jobs' => [
            'ix_processing_edition_type_created' => ['unique'=>false,'columns'=>['tenant_guid','edition_guid','job_type','created_at']],
        ],
    ];

    private const BETA2_TABLES = ['ep_offline_devices','ep_offline_licenses'];
    private const BETA2_ITEM_COLUMNS = [
        'device_guid' => 'BINARY(16) NULL',
        'license_guid' => 'BINARY(16) NULL',
        'expires_at' => 'DATETIME(6) NULL',
        'page_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'downloaded_pages' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    ];
    private const BETA2_INDEXES = [
        'ep_offline_devices' => [
            'PRIMARY'=>['unique'=>true,'columns'=>['device_guid']],
            'uk_offline_device_user'=>['unique'=>true,'columns'=>['tenant_guid','platform_user_guid','device_guid']],
            'ix_offline_device_user_status'=>['unique'=>false,'columns'=>['tenant_guid','platform_user_guid','status','last_seen_at']],
        ],
        'ep_offline_licenses' => [
            'PRIMARY'=>['unique'=>true,'columns'=>['license_guid']],
            'ix_offline_license_user'=>['unique'=>false,'columns'=>['tenant_guid','platform_user_guid','status','expires_at']],
            'ix_offline_license_device'=>['unique'=>false,'columns'=>['tenant_guid','platform_user_guid','device_guid','status','expires_at']],
            'ix_offline_license_edition'=>['unique'=>false,'columns'=>['tenant_guid','platform_user_guid','edition_guid','status','expires_at']],
        ],
        'ep_offline_items' => [
            'uk_offline_edition'=>['unique'=>true,'columns'=>['tenant_guid','platform_user_guid','device_guid','edition_guid']],
            'ix_offline_user_status_expiry'=>['unique'=>false,'columns'=>['tenant_guid','platform_user_guid','status','expires_at']],
            'ix_offline_license'=>['unique'=>false,'columns'=>['tenant_guid','platform_user_guid','license_guid']],
        ],
    ];


    private const RC1_TABLES = [
        'ep_authors','ep_topics','ep_entities','ep_articles','ep_article_authors','ep_article_topics','ep_article_entities',
    ];

    private const RC1_REQUIRED_COLUMNS = [
        'ep_authors'=>['author_guid','tenant_guid','name','slug','bio','status','updated_at'],
        'ep_topics'=>['topic_guid','tenant_guid','publication_guid','name','slug','description','status','updated_at'],
        'ep_entities'=>['entity_guid','tenant_guid','entity_type','name','slug','description','status','updated_at'],
        'ep_articles'=>['article_guid','tenant_guid','publication_guid','edition_guid','page_guid','title','slug','deck','body','translation_key','source_notes','correction_note','ai_assisted','ai_disclosure','status','published_at','updated_at'],
        'ep_article_authors'=>['tenant_guid','article_guid','author_guid','sort_order'],
        'ep_article_topics'=>['tenant_guid','article_guid','topic_guid'],
        'ep_article_entities'=>['tenant_guid','article_guid','entity_guid'],
    ];

    private const MOBILE15_TABLES = ['ep_local_mobile_accounts','ep_mobile_auth_challenges'];
    private const FLEX17_TABLES = ['ep_local_device_accounts'];
    private const TRUST18_TABLES = ['ep_reader_devices','ep_reader_sessions','ep_desktop_login_challenges'];

    public static function quickStatus(bool $refresh=false): array
    {
        if (!$refresh) {
            $cached = RedisStore::getJson(self::CACHE_KEY);
            if (is_array($cached) && isset($cached['ok'])) return $cached;
        }
        $pending=[];
        try {
            // Metadata checks run only on a cache miss. Normal dashboard requests hit the Redis schema-status snapshot.
            $placeholders=implode(',',array_fill(0,count(self::ALPHA2_TABLES),'?'));
            $tableRows=Store::all('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$placeholders.')',self::ALPHA2_TABLES);
            $present=[];foreach($tableRows as$row)$present[]=(string)$row['TABLE_NAME'];
            foreach(self::ALPHA2_TABLES as$table)if(!in_array($table,$present,true)){$pending[]='v2.0.0-alpha.2 navigation schema';break;}

            $idxRows=Store::all('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',['ep_settings']);
            $by=[];foreach($idxRows as$row)$by[(string)$row['INDEX_NAME']][]=(string)$row['COLUMN_NAME'];
            $wanted=['tenant_guid','scope_type','scope_guid','setting_key'];$indexOk=false;foreach($by as$cols)if($cols===$wanted){$indexOk=true;break;}
            if(!$indexOk)$pending[]='v2.0.0-alpha.1 settings index';

            $editionCols=self::columnNames('ep_editions');
            foreach(array_keys(self::BETA11_COLUMNS) as$column)if(!in_array($column,$editionCols,true)){$pending[]='v2.0.0-beta.1.1 editions operations schema';break;}
            foreach(self::BETA11_INDEXES as$table=>$indexes){
                foreach($indexes as$spec)if(!self::hasEquivalentIndex($table,$spec['columns'],(bool)$spec['unique'])){$pending[]='v2.0.0-beta.1.1 editions operations indexes';break 2;}
            }
            foreach(self::BETA2_TABLES as$table)if(!self::tableExists($table)){$pending[]='v2.0.0-beta.2 offline vault tables';break;}
            $offlineCols=self::columnNames('ep_offline_items');foreach(array_keys(self::BETA2_ITEM_COLUMNS) as$column)if(!in_array($column,$offlineCols,true)){$pending[]='v2.0.0-beta.2 offline vault columns';break;}
            foreach(self::BETA2_INDEXES as$table=>$indexes){foreach($indexes as$spec)if(!self::hasEquivalentIndex($table,$spec['columns'],(bool)$spec['unique'])){$pending[]='v2.0.0-beta.2 offline vault indexes';break 2;}}
            foreach(self::RC1_TABLES as$table){if(!self::tableExists($table)){$pending[]='v2.0.0-rc.1 semantic news schema';break;}$cols=self::columnNames($table);foreach(self::RC1_REQUIRED_COLUMNS[$table] as$column)if(!in_array($column,$cols,true)){$pending[]='v2.0.0-rc.1 semantic news schema';break 2;}}
            $identityCols=self::columnNames('ep_identities');
            if(!in_array('identity_source',$identityCols,true))$pending[]='v2.0.1.5 mobile auth identity source';
            foreach(self::MOBILE15_TABLES as$table)if(!self::tableExists($table)){$pending[]='v2.0.1.5 mobile auth schema';break;}
            foreach(self::FLEX17_TABLES as$table)if(!self::tableExists($table)){$pending[]='v2.0.1.7 flexible reader identity schema';break;}
            foreach(self::TRUST18_TABLES as$table)if(!self::tableExists($table)){$pending[]='v2.0.1.8 trusted app / desktop login schema';break;}
        } catch (\Throwable $e) {
            $status=['ok'=>false,'pending'=>['database preflight unavailable'],'error'=>$e->getMessage(),'checked_at'=>gmdate('c')];
            RedisStore::setJson(self::CACHE_KEY,$status,30);
            return $status;
        }
        $status=['ok'=>$pending===[],'pending'=>array_values(array_unique($pending)),'checked_at'=>gmdate('c')];
        RedisStore::setJson(self::CACHE_KEY,$status,300);
        return $status;
    }

    public static function report(bool $refresh=false): array
    {
        $checks=[];$dbName='';$server='';
        try {
            $dbName=(string)Store::value('SELECT DATABASE()');
            $server=(string)Store::value('SELECT VERSION()');
            $checks[] = self::check('database','MariaDB connection',true,'Connected to '.$dbName.' · '.$server,false);
        } catch (\Throwable $e) {
            return ['ok'=>false,'version'=>Build::version(),'database'=>null,'checks'=>[self::check('database','MariaDB connection',false,$e->getMessage(),false)],'pending'=>['database connection'],'checked_at'=>gmdate('c')];
        }

        $baseTables=['ep_tenants','ep_settings','ep_editions','ep_pages','ep_page_assets','ep_processing_jobs','ep_system_state'];
        foreach($baseTables as $table){
            $ok=self::tableExists($table);
            $checks[]=self::check('base:'.$table,'Base table '.$table,$ok,$ok?'Present':'Missing — restore/install the v1.1.9.11 base schema first',false);
        }

        $alpha1=self::hasEquivalentIndex('ep_settings',['tenant_guid','scope_type','scope_guid','setting_key']);
        $checks[]=self::check('migration:alpha1','v2.0.0-alpha.1 settings scope index',$alpha1,$alpha1?'Present':'Missing index for low-query settings resolution',true);

        foreach(self::ALPHA2_TABLES as $table){
            $exists=self::tableExists($table);
            $checks[]=self::check('migration:alpha2:table:'.$table,'v2.0.0-alpha.2 table '.$table,$exists,$exists?'Present':'Missing Reader Navigation table',true);
            if(!$exists) continue;
            $columns=self::columnNames($table);
            foreach(self::ALPHA2_COLUMNS[$table] as $column=>$ddl){
                $ok=in_array($column,$columns,true);
                $checks[]=self::check('migration:alpha2:column:'.$table.'.'.$column,$table.'.'.$column,$ok,$ok?'Present':'Missing column',true);
            }
            foreach(self::ALPHA2_INDEXES[$table] as $name=>$spec){
                $ok=self::hasEquivalentIndex($table,$spec['columns'],(bool)$spec['unique']);
                $checks[]=self::check('migration:alpha2:index:'.$table.'.'.$name,$table.' index '.$name,$ok,$ok?'Present':'Missing index',true);
            }
        }

        $editionCols=self::columnNames('ep_editions');
        foreach(self::BETA11_COLUMNS as$column=>$ddl){
            $ok=in_array($column,$editionCols,true);
            $checks[]=self::check('migration:beta11:column:ep_editions.'.$column,'v2.0.0-beta.1.1 ep_editions.'.$column,$ok,$ok?'Present':'Missing editions publishing column',true);
        }
        foreach(self::BETA11_INDEXES as$table=>$indexes){
            foreach($indexes as$name=>$spec){
                $ok=self::hasEquivalentIndex($table,$spec['columns'],(bool)$spec['unique']);
                $checks[]=self::check('migration:beta11:index:'.$table.'.'.$name,$table.' index '.$name,$ok,$ok?'Present':'Missing editions operations index',true);
            }
        }
        foreach(self::BETA2_TABLES as$table){$ok=self::tableExists($table);$checks[]=self::check('migration:beta2:table:'.$table,'v2.0.0-beta.2 table '.$table,$ok,$ok?'Present':'Missing encrypted Offline Vault table',true);}
        $offlineCols=self::columnNames('ep_offline_items');foreach(self::BETA2_ITEM_COLUMNS as$column=>$ddl){$ok=in_array($column,$offlineCols,true);$checks[]=self::check('migration:beta2:column:ep_offline_items.'.$column,'v2.0.0-beta.2 ep_offline_items.'.$column,$ok,$ok?'Present':'Missing Offline Vault column',true);}
        foreach(self::BETA2_INDEXES as$table=>$indexes){foreach($indexes as$name=>$spec){$ok=self::hasEquivalentIndex($table,$spec['columns'],(bool)$spec['unique']);$checks[]=self::check('migration:beta2:index:'.$table.'.'.$name,$table.' index '.$name,$ok,$ok?'Present':'Missing Offline Vault index',true);}}
        foreach(self::RC1_TABLES as$table){$exists=self::tableExists($table);$checks[]=self::check('migration:rc1:table:'.$table,'v2.0.0-rc.1 table '.$table,$exists,$exists?'Present':'Missing semantic News/SEO table',true);if(!$exists)continue;$cols=self::columnNames($table);foreach(self::RC1_REQUIRED_COLUMNS[$table] as$column){$ok=in_array($column,$cols,true);$checks[]=self::check('migration:rc1:column:'.$table.'.'.$column,$table.'.'.$column,$ok,$ok?'Present':'Missing semantic publishing column',true);}}

        $identityCols=self::columnNames('ep_identities');$identitySource=in_array('identity_source',$identityCols,true);
        $checks[]=self::check('migration:2015:column:ep_identities.identity_source','v2.0.1.5 ep_identities.identity_source',$identitySource,$identitySource?'Present':'Missing mobile-auth identity source',true);
        foreach(self::MOBILE15_TABLES as$table){$ok=self::tableExists($table);$checks[]=self::check('migration:2015:table:'.$table,'v2.0.1.5 table '.$table,$ok,$ok?'Present':'Missing mobile authentication table',true);}
        foreach(self::FLEX17_TABLES as$table){$ok=self::tableExists($table);$checks[]=self::check('migration:2017:table:'.$table,'v2.0.1.7 table '.$table,$ok,$ok?'Present':'Missing flexible reader identity table',true);}
        foreach(self::TRUST18_TABLES as$table){$ok=self::tableExists($table);$checks[]=self::check('migration:2018:table:'.$table,'v2.0.1.8 table '.$table,$ok,$ok?'Present':'Missing trusted-app / desktop-login table',true);}

        $redisEnabled=RedisStore::enabled();
        if($redisEnabled){
            try{$ping=RedisStore::client()?->ping();$ok=$ping!==false&&$ping!==null;$detail=$ok?'Connected':'No response';}
            catch(\Throwable $e){$ok=false;$detail=$e->getMessage();}
        } else {$ok=true;$detail='Disabled in configuration';}
        $checks[]=self::check('runtime:redis','Redis runtime',$ok,$detail,false);

        $root=dirname(__DIR__,2);$storage=$root.'/storage';
        $checks[]=self::check('runtime:storage','Storage writable',is_writable($storage),is_writable($storage)?'Writable':'Not writable',false);
        $navDir=$storage.'/cache/navigation';
        $navWritable=is_dir($navDir)?is_writable($navDir):is_writable($storage.'/cache')||is_writable($storage);
        $checks[]=self::check('runtime:nav-cache','Compiled navigation cache path',$navWritable,$navWritable?'Writable/creatable':'Cannot create navigation cache files',false);

        $lockFile=$storage.'/installed.lock';$lockVersion='unknown';
        if(is_file($lockFile)){$d=json_decode((string)@file_get_contents($lockFile),true);if(is_array($d))$lockVersion=(string)($d['version']??'unknown');}
        $checks[]=self::check('runtime:version-lock','Installed version marker',$lockVersion===Build::version(),$lockVersion===Build::version()?'Aligned at '.Build::version():'Marker '.$lockVersion.' · code '.Build::version(),true);

        $pending=[];foreach($checks as $c)if(!$c['ok']&&$c['repairable'])$pending[]=$c['id'];
        $baseOk=true;foreach($checks as$c)if(str_starts_with($c['id'],'base:')&&!$c['ok']){$baseOk=false;break;}
        $report=['ok'=>$baseOk&&$pending===[],'base_ok'=>$baseOk,'version'=>Build::version(),'database'=>$dbName,'server'=>$server,'checks'=>$checks,'pending'=>$pending,'checked_at'=>gmdate('c')];
        RedisStore::setJson(self::CACHE_KEY,['ok'=>$report['ok'],'pending'=>$pending,'checked_at'=>$report['checked_at']],60);
        return $report;
    }

    public static function repair(?string $actorGuid=null): array
    {
        $before=self::report(true);
        if(empty($before['base_ok']))throw new \RuntimeException('Base schema is incomplete. Automatic v2 repair is blocked; restore the v1.1.9.11 base database first.');
        $pdo=Database::pdo();$lockName='epdi:v2:schema-upgrade';$got=(int)Store::value('SELECT GET_LOCK(?,10)',[$lockName]);
        if($got!==1)throw new \RuntimeException('Another database upgrade is already running. Try again shortly.');
        $actions=[];
        try{
            if(!self::hasEquivalentIndex('ep_settings',['tenant_guid','scope_type','scope_guid','setting_key'])){
                $pdo->exec('ALTER TABLE ep_settings ADD INDEX ix_setting_scope (tenant_guid,scope_type,scope_guid,setting_key)');
                $actions[]='Added ep_settings scope index (v2.0.0-alpha.1)';
            }

            $missingTable=false;foreach(self::ALPHA2_TABLES as$table)if(!self::tableExists($table)){$missingTable=true;break;}
            if($missingTable){
                $file=dirname(__DIR__,2).'/database/upgrade-v2.0.0-alpha.2.sql';
                $sql=(string)@file_get_contents($file);if($sql==='')throw new \RuntimeException('Alpha.2 migration file is missing. Re-upload the cumulative release package.');
                foreach(self::splitSql($sql) as$statement){$pdo->exec($statement);}
                $actions[]='Created missing v2.0.0-alpha.2 Reader Navigation tables';
            }

            // Repair partially-created alpha.2 tables conservatively: add missing columns and indexes only.
            foreach(self::ALPHA2_TABLES as$table){
                if(!self::tableExists($table))continue;
                $columns=self::columnNames($table);
                foreach(self::ALPHA2_COLUMNS[$table] as$column=>$ddl){
                    if(in_array($column,$columns,true))continue;
                    $pdo->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$ddl);
                    $actions[]='Added '.$table.'.'.$column;
                }
                foreach(self::ALPHA2_INDEXES[$table] as$name=>$spec){
                    if(self::hasEquivalentIndex($table,$spec['columns'],(bool)$spec['unique']))continue;
                    $cols='`'.implode('`,`',$spec['columns']).'`';
                    if($name==='PRIMARY')$ddl='ALTER TABLE `'.$table.'` ADD PRIMARY KEY ('.$cols.')';
                    else $ddl='ALTER TABLE `'.$table.'` ADD '.($spec['unique']?'UNIQUE ':'').'INDEX `'.$name.'` ('.$cols.')';
                    $pdo->exec($ddl);$actions[]='Added '.$table.' index '.$name;
                }
                // Backfill timestamps introduced into a partial table before tightening future writes.
                $cols=self::columnNames($table);
                if(in_array('created_at',$cols,true))$pdo->exec('UPDATE `'.$table.'` SET created_at=COALESCE(created_at,NOW(6)) WHERE created_at IS NULL');
                if(in_array('updated_at',$cols,true))$pdo->exec('UPDATE `'.$table.'` SET updated_at=COALESCE(updated_at,NOW(6)) WHERE updated_at IS NULL');
                if($table==='ep_navigation_versions'&&in_array('published_at',$cols,true))$pdo->exec('UPDATE `'.$table.'` SET published_at=COALESCE(published_at,NOW(6)) WHERE published_at IS NULL');
            }

            $betaMissing=false;
            $editionCols=self::columnNames('ep_editions');
            foreach(array_keys(self::BETA11_COLUMNS) as$column)if(!in_array($column,$editionCols,true)){$betaMissing=true;break;}
            if(!$betaMissing){
                foreach(self::BETA11_INDEXES as$table=>$indexes)foreach($indexes as$spec)if(!self::hasEquivalentIndex($table,$spec['columns'],(bool)$spec['unique'])){$betaMissing=true;break 2;}
            }
            if($betaMissing){
                $file=dirname(__DIR__,2).'/database/upgrade-v2.0.0-beta.1.1.sql';
                if(!is_file($file))throw new \RuntimeException('Beta.1.1 migration file is missing. Re-upload the cumulative release package.');
                $editionCols=self::columnNames('ep_editions');
                if(!in_array('publish_mode',$editionCols,true)){$pdo->exec('ALTER TABLE ep_editions ADD COLUMN publish_mode VARCHAR(24) NOT NULL DEFAULT "MANUAL" AFTER status');$actions[]='Added ep_editions.publish_mode';}
                $editionCols=self::columnNames('ep_editions');
                if(!in_array('schedule_timezone',$editionCols,true)){$pdo->exec('ALTER TABLE ep_editions ADD COLUMN schedule_timezone VARCHAR(64) NULL AFTER publish_mode');$actions[]='Added ep_editions.schedule_timezone';}
                foreach(self::BETA11_INDEXES as$table=>$indexes){
                    foreach($indexes as$name=>$spec){
                        if(self::hasEquivalentIndex($table,$spec['columns'],(bool)$spec['unique']))continue;
                        $cols='`'.implode('`,`',$spec['columns']).'`';$pdo->exec('ALTER TABLE `'.$table.'` ADD INDEX `'.$name.'` ('.$cols.')');$actions[]='Added '.$table.' index '.$name;
                    }
                }
                Store::exec('UPDATE ep_editions SET publish_mode="SCHEDULED" WHERE tenant_guid IS NOT NULL AND status="SCHEDULED" AND publish_mode="MANUAL"');
            }

            // v2.0.0-beta.2 encrypted Offline Vault — state-aware table/column/index repair.
            $migrationFile=dirname(__DIR__,2).'/database/upgrade-v2.0.0-beta.2.sql';
            if(!is_file($migrationFile))throw new \RuntimeException('Beta.2 migration file is missing. Re-upload the cumulative release package.');
            $migrationSql=(string)file_get_contents($migrationFile);$statements=self::splitSql($migrationSql);
            foreach(self::BETA2_TABLES as$table){if(self::tableExists($table))continue;foreach($statements as$statement){if(stripos($statement,'CREATE TABLE IF NOT EXISTS '.$table)!==false){$pdo->exec($statement);$actions[]='Created '.$table.' (v2.0.0-beta.2)';break;}}}
            $offlineCols=self::columnNames('ep_offline_items');foreach(self::BETA2_ITEM_COLUMNS as$column=>$ddl){if(in_array($column,$offlineCols,true))continue;$pdo->exec('ALTER TABLE `ep_offline_items` ADD COLUMN `'.$column.'` '.$ddl);$actions[]='Added ep_offline_items.'.$column;}
            foreach(self::BETA2_INDEXES as$table=>$indexes){if(!self::tableExists($table))continue;foreach($indexes as$name=>$spec){if(self::hasEquivalentIndex($table,$spec['columns'],(bool)$spec['unique']))continue;if($name==='uk_offline_edition'&&self::indexExists($table,$name)){$pdo->exec('ALTER TABLE `'.$table.'` DROP INDEX `'.$name.'`');$actions[]='Replaced legacy '.$table.' index '.$name;}if($name==='PRIMARY'){$pdo->exec('ALTER TABLE `'.$table.'` ADD PRIMARY KEY (`'.implode('`,`',$spec['columns']).'`)');}else{$pdo->exec('ALTER TABLE `'.$table.'` ADD '.($spec['unique']?'UNIQUE ':'').'INDEX `'.$name.'` (`'.implode('`,`',$spec['columns']).'`)');}$actions[]='Added '.$table.' index '.$name;}}

            // v2.0.0-rc.1 semantic News/SEO/Trust schema. New tables are additive.
            $rc1Missing=false;foreach(self::RC1_TABLES as$table){if(!self::tableExists($table)){$rc1Missing=true;break;} $cols=self::columnNames($table);foreach(self::RC1_REQUIRED_COLUMNS[$table] as$column)if(!in_array($column,$cols,true)){$rc1Missing=true;break 2;}}
            if($rc1Missing){
                $file=dirname(__DIR__,2).'/database/upgrade-v2.0.0-rc.1.sql';
                $sql=(string)@file_get_contents($file);if($sql==='')throw new \RuntimeException('RC.1 semantic News/SEO migration file is missing. Re-upload the cumulative release package.');
                foreach(self::splitSql($sql) as$statement){$pdo->exec($statement);}
                $actions[]='Created v2.0.0-rc.1 semantic News/SEO tables';
            }

            // v2.0.1.5 local mobile authentication. The migration is additive/idempotent.
            $identityCols=self::columnNames('ep_identities');$mobile15Missing=!in_array('identity_source',$identityCols,true);
            foreach(self::MOBILE15_TABLES as$table)if(!self::tableExists($table)){$mobile15Missing=true;break;}
            if($mobile15Missing)self::applyMigrationFile($pdo,'upgrade-v2.0.1.5-mobile-auth.sql',$actions,'Applied v2.0.1.5 mobile-auth schema');

            // v2.0.1.7 temporary/local device identity.
            $flex17Missing=false;foreach(self::FLEX17_TABLES as$table)if(!self::tableExists($table)){$flex17Missing=true;break;}
            if($flex17Missing)self::applyMigrationFile($pdo,'upgrade-v2.0.1.7-flexible-reader-identity.sql',$actions,'Applied v2.0.1.7 flexible-reader schema');

            // v2.0.1.8 trusted app + desktop login. This repairs the exact production failure where
            // /admin/subscribers referenced ep_reader_devices before its migration had been applied.
            $trust18Missing=false;foreach(self::TRUST18_TABLES as$table)if(!self::tableExists($table)){$trust18Missing=true;break;}
            if($trust18Missing)self::applyMigrationFile($pdo,'upgrade-v2.0.1.8-trusted-app-desktop-login.sql',$actions,'Applied v2.0.1.8 trusted-app / desktop-login schema');

            // Record the migration status in the existing application state table when available.
            if(self::tableExists('ep_system_state')){
                $payload=json_encode(['version'=>Build::version(),'actor'=>$actorGuid,'actions'=>$actions,'at'=>gmdate('c')],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES("schema.v2.last_repair",?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[$payload]);
            }

            self::alignInstalledLock($actions);
            RedisStore::del(self::CACHE_KEY);
            if(RequestContext::get('tenant_guid')){
                RedisStore::del('dashboard:v2:'.RequestContext::tenantGuid());
                RedisStore::del('runtime:v2:'.RequestContext::tenantGuid());
            }
            $after=self::report(true);
            if(!$after['ok'])$actions[]='Repair completed but one or more checks still require review';
            return ['ok'=>$after['ok'],'actions'=>$actions,'before'=>$before,'after'=>$after];
        } finally {
            try{Store::value('SELECT RELEASE_LOCK(?)',[$lockName]);}catch(\Throwable){}
        }
    }

    public static function lastRepair(): ?array
    {
        try{
            if(!self::tableExists('ep_system_state'))return null;
            $raw=Store::value('SELECT state_value FROM ep_system_state WHERE state_key="schema.v2.last_repair" LIMIT 1');
            if(!$raw)return null;
            $decoded=json_decode((string)$raw,true);
            return is_array($decoded)?$decoded:null;
        }catch(\Throwable){return null;}
    }

    public static function invalidateStatus(): void{RedisStore::del(self::CACHE_KEY);}

    public static function hasFlexibleReaderSchema(): bool
    {
        foreach(self::FLEX17_TABLES as$table)if(!self::tableExists($table))return false;return true;
    }

    public static function hasTrustedAppSchema(): bool
    {
        foreach(self::TRUST18_TABLES as$table)if(!self::tableExists($table))return false;return true;
    }

    private static function applyMigrationFile(\PDO $pdo,string $filename,array &$actions,string $label): void
    {
        $file=dirname(__DIR__,2).'/database/'.$filename;$sql=(string)@file_get_contents($file);
        if($sql==='')throw new \RuntimeException('Migration file '.$filename.' is missing. Re-upload the cumulative release package.');
        foreach(self::splitSql($sql) as$statement)$pdo->exec($statement);$actions[]=$label;
    }

    private static function alignInstalledLock(array &$actions): void
    {
        $root=dirname(__DIR__,2);$file=$root.'/storage/installed.lock';if(!is_file($file)||!is_writable($file))return;
        $data=json_decode((string)@file_get_contents($file),true);if(!is_array($data))$data=[];
        if((string)($data['version']??'')===Build::version())return;
        $data['version']=Build::version();$data['last_upgraded_at']=gmdate('c');
        if(@file_put_contents($file,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX)!==false)$actions[]='Aligned storage/installed.lock to '.Build::version();
    }

    private static function tableExists(string $table): bool
    {
        return (int)Store::value('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])===1;
    }

    private static function columnNames(string $table): array
    {
        $rows=Store::all('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$table]);
        return array_map(static fn(array $r): string => (string)$r['COLUMN_NAME'],$rows);
    }

    private static function indexExists(string $table,string $name): bool
    {
        if(!self::tableExists($table))return false;return (int)Store::value('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$name])>0;
    }

    private static function hasEquivalentIndex(string $table,array $columns,?bool $unique=null): bool
    {
        if(!self::tableExists($table))return false;
        $rows=Store::all('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table]);
        $by=[];$uniq=[];foreach($rows as$r){$name=(string)$r['INDEX_NAME'];$by[$name][]=(string)$r['COLUMN_NAME'];$uniq[$name]=(int)$r['NON_UNIQUE']===0;}
        foreach($by as$name=>$cols){if($cols!==$columns)continue;if($unique!==null&&$uniq[$name]!==$unique)continue;return true;}return false;
    }

    private static function splitSql(string $sql): array
    {
        $sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;$out=[];$buffer='';$quote=null;$len=strlen($sql);
        for($i=0;$i<$len;$i++){$ch=$sql[$i];if($quote!==null){$buffer.=$ch;if($ch===$quote&&($i===0||$sql[$i-1]!=='\\'))$quote=null;continue;}if($ch==="'"||$ch==='"'||$ch==='`'){$quote=$ch;$buffer.=$ch;continue;}if($ch===';'){if(trim($buffer)!=='')$out[]=trim($buffer);$buffer='';continue;}$buffer.=$ch;}if(trim($buffer)!=='')$out[]=trim($buffer);return$out;
    }

    private static function check(string$id,string$label,bool$ok,string$detail,bool$repairable): array
    {return['id'=>$id,'label'=>$label,'ok'=>$ok,'detail'=>$detail,'repairable'=>$repairable];}
}
