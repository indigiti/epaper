<?php
declare(strict_types=1);
$root=require dirname(__DIR__).'/app/bootstrap.php';
use Digiti\Core\Database;

$pdo=Database::pdo();
$lock='digiti:platform:migration';
if((int)$pdo->query("SELECT GET_LOCK('digiti:platform:migration',10)")->fetchColumn()!==1)
    throw new \RuntimeException('Another Platform schema migration is in progress');
try{
    $baseline=file_get_contents($root.'/database/schema.sql');
    if($baseline===false)throw new \RuntimeException('Baseline schema unavailable');
    $statements=static function(string $sql):array {
        return array_values(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[])));
    };
    $count=0;
    foreach($statements($baseline) as $sql){$pdo->exec($sql);$count++;}
    $pdo->exec('INSERT INTO schema_migrations(migration_key,applied_at) VALUES("baseline-1.0.0",NOW(6)) ON DUPLICATE KEY UPDATE applied_at=applied_at');
    $migrations=glob($root.'/database/migrations/*.sql')?:[];
    sort($migrations,SORT_STRING);
    $applied=0;
    foreach($migrations as $file){
        $name=basename($file,'.sql');
        if(!preg_match('/^[0-9]{8}_[a-z0-9_]+$/',$name))throw new \RuntimeException('Unsafe migration name');
        $stmt=$pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration_key=?');
        $stmt->execute([$name]);
        if($stmt->fetchColumn())continue;
        $sql=file_get_contents($file);
        if($sql===false)throw new \RuntimeException('Cannot read migration');
        foreach($statements($sql) as $query)$pdo->exec($query);
        $stmt=$pdo->prepare('INSERT INTO schema_migrations(migration_key,applied_at) VALUES(?,NOW(6))');
        $stmt->execute([$name]);
        $applied++;
    }
    echo "Schema verified; {$count} baseline statements; {$applied} new migrations applied.\n";
}finally{
    $pdo->query("SELECT RELEASE_LOCK('digiti:platform:migration')");
}
