<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Services/ReaderEditionChoices.php';
$choices=\EpaperSaas\Services\ReaderEditionChoices::class;
$current=['edition_date'=>'2026-10-18','slug'=>'pune-mirror-current','page_count'=>12];

$only=$choices::withCurrent([], $current);
if(count($only)!==1||$only[0]['slug']!=='pune-mirror-current')
    throw new RuntimeException('Single published edition must keep its calendar choice');

$rows=[
    ['edition_date'=>'2026-10-17','slug'=>'yesterday','page_count'=>12],
    ['edition_date'=>'2026-10-18','slug'=>'stale-edition','page_count'=>8],
    ['edition_date'=>'2026-10-16','slug'=>'older','page_count'=>10],
    ['edition_date'=>'2026-02-30','slug'=>'invalid-date'],
    ['edition_date'=>'2026-10-15','slug'=>''],
];
$desc=$choices::withCurrent($rows,$current);
if(array_column($desc,'slug')!==['pune-mirror-current','yesterday','older'])
    throw new RuntimeException('Date selector must preserve active slug, dedupe and sort newest first');
$asc=$choices::withCurrent($rows,$current,true);
if(array_column($asc,'slug')!==['older','yesterday','pune-mirror-current'])
    throw new RuntimeException('Horizontal date strip must sort oldest first');
if($choices::withCurrent([],['edition_date'=>'invalid','slug'=>''])!==[])
    throw new RuntimeException('Invalid current edition must not fabricate a date');

$app=(string)file_get_contents($root.'/app/App.php');
$css=(string)file_get_contents($root.'/public_html/assets/reader.css');
$reader=(string)file_get_contents($root.'/public_html/assets/reader.js');
if(str_contains($app,"if(count(\$rows)<2)return''"))
    throw new RuntimeException('Date picker still disappears with fewer than two matching editions');
if(substr_count($app,'ReaderEditionChoices::withCurrent(')!==2 ||
    !str_contains($app,'$hasAlternates=count($rows)>1') ||
    !str_contains($app,'No other published dates found for this edition variant') ||
    !str_contains($app,'reader-edition-archive'))
    throw new RuntimeException('Reader dropdown/strip back-edition fallback missing');
if(!str_contains($app,"'reader.edition_date_switcher','on'")||
   !str_contains($app,"if(!$editionSwitcherEnabled)\$editionNavigation='off'"))
    throw new RuntimeException('Publisher edition navigation permissions changed');
if(!str_contains($app,"'publication'=>\$publication,'month'=>substr(\$date,0,7)"))
    throw new RuntimeException('Fallback must navigate to this publication and edition month');
if(!str_contains($reader,'data-reader-edition-trigger') ||
    !str_contains($reader,'function openEditionMenu()') ||
    !str_contains($reader,'function closeEditionMenu()'))
    throw new RuntimeException('Calendar interaction binding missing');
if(!str_contains($css,'.reader-edition-empty') ||
   !str_contains($css,'.reader-edition-archive'))
    throw new RuntimeException('Reader date navigation fallback styles missing');
echo "Edition-date switcher regression contracts: OK\n";
