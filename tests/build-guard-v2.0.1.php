<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool $c,string $m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};
$ok(trim((string)file_get_contents($root.'/VERSION'))==='2.0.1','VERSION 2.0.1');
$view=(string)file_get_contents($root.'/app/Core/View.php');$app=(string)file_get_contents($root.'/app/App.php');$settings=(string)file_get_contents($root.'/app/Services/SettingsCatalog.php');$cache=(string)file_get_contents($root.'/app/Services/PublicReadCache.php');$css=(string)file_get_contents($root.'/public_html/assets/reader.css');$js=(string)file_get_contents($root.'/public_html/assets/app.js');$sw=(string)file_get_contents($root.'/public_html/service-worker.js');
$ok(str_contains($view,"self::icon('login')"),'guest Sign in icon');
$ok(str_contains($view,"href=\"/admin\"")&&str_contains($view,'$canAdmin'),'role-aware Admin shortcut');
$ok(str_contains($view,"href=\"/account\"")&&str_contains($view,'My account'),'Account icon link');
$ok(str_contains($settings,'appearance.public_color_mode')&&str_contains($settings,'appearance.public_color_switcher'),'publisher public appearance controls');
$ok(str_contains($settings,'reader.edition_date_switcher'),'Reader edition-date switcher control');
$ok(str_contains($cache,'readerEditionCalendar')&&str_contains($cache,'publication_guid=?'),'cached lightweight Reader edition index');
$ok(str_contains($app,'readerEditionStrip($ed)')&&str_contains($app,'reader_edition_strip'),'Reader strip wired to layout');
$ok(str_contains($app,'/page/1')&&str_contains($app,'data-reader-edition-switcher'),'edition date links open page 1');
$ok(str_contains($css,'reader-has-edition-switcher')&&str_contains($css,'--reader-edition-switcher-height'),'Reader viewport offset guard');
$ok(str_contains($js,"publicThemeMode")&&str_contains($js,"publicThemeSwitcher"),'public theme policy runtime');
$ok(str_contains($js,"archive-date-rail")&&str_contains($js,"data-edition-date-rail"),'date rails auto-center current edition');
$ok(str_contains($sw,"epdi-v2.0.1"),'service worker cache version');
$ok(!is_file($root.'/database/upgrade-v2.0.1.sql'),'no v2.0.1 migration');
if($fails){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$fails)."\n");exit(1);}echo "\nPASS v2.0.1 guard\n";
