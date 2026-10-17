<?php
$root=require dirname(__DIR__).'/app/bootstrap.php';
use Digiti\Core\Database;
$schema=file_get_contents($root.'/database/schema.sql');$pdo=Database::pdo();$count=0;
foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$schema)))as$sql){$pdo->exec($sql);$count++;}
$pdo->prepare('INSERT INTO schema_migrations(migration_key,applied_at) VALUES("baseline-1.0.0",NOW(6)) ON DUPLICATE KEY UPDATE applied_at=applied_at')->execute();
echo "Schema checked; {$count} statements processed.\n";
