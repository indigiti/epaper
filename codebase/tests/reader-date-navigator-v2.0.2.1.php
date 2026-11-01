<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool $v,string $m)use(&$fails){echo($v?'PASS':'FAIL')." — $m\n";if(!$v)$fails[]=$m;};
$version=trim((string)file_get_contents($root.'/VERSION'));
$app=(string)file_get_contents($root.'/app/App.php');$cache=(string)file_get_contents($root.'/app/Services/PublicReadCache.php');$settings=(string)file_get_contents($root.'/app/Services/SettingsCatalog.php');$view=(string)file_get_contents($root.'/app/Core/View.php');$reader=(string)file_get_contents($root.'/public_html/assets/reader.js');$css=(string)file_get_contents($root.'/public_html/assets/reader.css');$sw=(string)file_get_contents($root.'/public_html/service-worker.js');$manifest=json_decode((string)file_get_contents($root.'/manifest.json'),true);
$ok(version_compare($version,'2.0.2.1','>='),'VERSION remains at or above 2.0.2.1');
$ok(str_contains($sw,"const VERSION='epdi-v{$version}';")&&!str_contains($sw,'?v=2.0.2.0'),'Service Worker aligned with current release');
$ok(is_array($manifest)&&($manifest['version']??'')===$version&&($manifest['database_migration']??true)===false,'release manifest aligned and schema-free');
$ok(str_contains($settings,"'reader.edition_navigation'")&&str_contains($settings,"['dropdown','strip','off']")&&str_contains($settings,"'reader.edition_history_limit'"),'Admin Reader navigation controls registered');
$ok(str_contains($app,'readerEditionDropdown')&&str_contains($app,"\$editionNavigation==='dropdown'")&&str_contains($app,"\$editionNavigation==='strip'"),'Dropdown / Strip / Off dispatch is wired');
$ok(str_contains($app,"data-reader-offline")&&str_contains($app,"\$readerEditionControl")&&str_contains($app,"data-reader-share"),'Back-edition control renders between Download and Share');
$ok(str_contains($cache,'readerEditionHistory')&&str_contains($cache,'publication_guid=?')&&str_contains($cache,'city_guid=?')&&str_contains($cache,'language_guid=?'),'history query is exact publication + city + language scoped');
$ok(str_contains($app,'Exact publication + city + language')&&str_contains($app,"'/page/1'"),'date choice documents exact variant and opens page 1');
$ok(str_contains($view,"'calendar'=>")&&str_contains($css,'.reader-edition-full-date')&&str_contains($css,'.reader-edition-short-date')&&str_contains($css,'max-width:900px'),'calendar icon plus responsive full/short/icon-only trigger packaged');
$ok(str_contains($reader,'data-reader-edition-trigger')&&str_contains($reader,'openEditionMenu')&&str_contains($reader,'closeEditionMenu'),'Reader dropdown interaction packaged');
$ok(!is_file($root.'/database/upgrade-v2.0.2.1.sql'),'no v2.0.2.1 database migration');
if($fails){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS v2.0.2.1 reader date navigator guard\n";
