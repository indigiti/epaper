<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $name)use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($name,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($name,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});
use EpaperSaas\Services\ReaderHub;
use EpaperSaas\Core\View;

$check=static function(bool $ok,string $label):void{
    echo ($ok?'PASS':'FAIL').' '.$label.PHP_EOL;
    if(!$ok)throw new RuntimeException($label);
};
$metrics=['bookmarks'=>2,'saved_articles'=>5,'clips'=>3,'offline'=>1,'notifications'=>2,'history'=>4];
$first=[
    'slug'=>'civic-mirror-2026-10-19','title'=>'Civic Mirror','publication_name'=>'Civic Mirror',
    'edition_date'=>'2026-10-19','page_number'=>2,'page_count'=>15,
    'last_viewed_at'=>'2026-10-22 17:42:00','thumb_url'=>'/media/cover-one',
];
$recent=[$first,[
    'slug'=>'pune-mirror-2026-10-18','title'=>'Pune Mirror','publication_name'=>'Pune Mirror',
    'edition_date'=>'2026-10-18','page_number'=>5,'page_count'=>20,
    'last_viewed_at'=>'2026-10-20 12:30:00','thumb_url'=>'/media/cover-two',
]];
$capabilities=[['Smart Discovery',true],['OCR Search',true],['Smart Article',false],['Clipping',true]];
$html=ReaderHub::render($metrics,$first,$recent,$capabilities);
$check(str_contains($html,'Civic Mirror'),'Personalized published edition shown');
$check(str_contains($html,'/edition/civic-mirror-2026-10-19/page/2'),'Resume link preserves original reader URL and page');
$check(str_contains($html,'aria-valuenow="13"'),'Resume progress is computed from actual page count');
$check(str_contains($html,'3</span><strong>Clippings'),'Shelf uses real clipping count');
$check(str_contains($html,'2 unread updates'),'Unread notifications are shown only when present');
$check(str_contains($html,'/account/saved-articles')&&str_contains($html,'/account/offline')&&str_contains($html,'/account/bookmarks'),'Original reader actions remain');
$check(str_contains($html,'/edition/pune-mirror-2026-10-18/page/5'),'Recent editions retain their exact last page');
$check(str_contains($html,'Smart Article')&&str_contains($html,'Not in your current plan'),'Capability access reflects plan entitlements');
$check(str_contains($html,'data-pwa-install'),'Existing progressive-web-app install control retained');
$check(!str_contains($html,'https://cdn.'),'No third-party frontend dependencies');
$empty=ReaderHub::render(array_fill_keys(array_keys($metrics),0),null,[],[]);
$check(str_contains($empty,'Your next story starts here.')&&str_contains($empty,'Your reading history will appear here.'),'First-visit empty state is helpful');
$check(!str_contains($empty,'unread updates'),'No invented notifications for new reader');
$attack=$first;
$attack['title']='<img src=x onerror=alert(1)>';
$attack['thumb_url']='javascript:alert(1)';
$attack['slug']='evil?x=1&y=2';
$unsafe=ReaderHub::render($metrics,$attack,[$attack],$capabilities);
$check(!str_contains($unsafe,'<img src=x onerror'),'Content titles are escaped');
$check(!str_contains($unsafe,'javascript:'),'Unsafe image schemes are filtered');
$check(str_contains($unsafe,'/edition/evil%3Fx%3D1%26y%3D2/page/2'),'Slug characters encoded for reader links');
$clamped=$first;
$clamped['page_number']=999;
$check(str_contains(ReaderHub::render($metrics,$clamped,[$clamped],$capabilities),'aria-valuenow="100"'),'Reading progress is safely clamped');

$_SERVER['REQUEST_URI']='/account';
$method=new ReflectionMethod(View::class,'accountNav');
$nav=$method->invoke(null);
foreach(['/account','/account/library','/account/saved-articles','/account/clips','/account/offline','/account/bookmarks','/account/history','/account/notifications','/account/subscription','/account/devices','/logout'] as $url)
    $check(str_contains($nav,'href="'.$url.'"'),'Retained reader nav route '.$url);
$check(str_contains($nav,'reader-mobile-dock'),'Mobile tab bar exists');
$check(str_contains($nav,'reader-more-menu'),'More disclosure keeps secondary routes accessible');
$check(str_contains($nav,'aria-current="page"'),'Account home is correctly marked active');

$app=(string)file_get_contents($root.'/app/App.php');
$view=(string)file_get_contents($root.'/app/Core/View.php');
$css=(string)file_get_contents($root.'/public_html/assets/reader-hub.css');
$check(str_contains($app,'ReaderHub::render($metrics,$resume,$recent,$capabilities)'),'Account overview calls independent presentation layer');
$check(str_contains($app,'ORDER BY h.last_viewed_at DESC LIMIT 5'),'Recent and resume are supplied by bounded history read');
$check(str_contains($app,'$resume=$recent[0]??null'),'Resume reuses recent read with no duplicate history query');
$check(str_contains($app,'$this->accountOverviewMetrics($uid'),'Existing tenant/user-scoped Redis metrics remain');
$check(str_contains($view,'reader-hub.css'),'Reader styles are loaded only for authenticated account surfaces');
$check(str_contains($css,'.reader-mobile-dock')&&str_contains($css,'.rh-continue'),'Mobile dock and reading feature are designed');
$check(str_contains($css,'body.account-shell.dark'),'Dark theme support included');
$check(!str_contains($app,'CREATE TABLE ep_')&&!str_contains($css,'@import'),'No schema changes or external style imports');

if(in_array('--fixtures',$argv,true)){
    $out=$root.'/tests/reader-hub-fixtures';
    if(!is_dir($out)&&!mkdir($out,0775,true)&&!is_dir($out))throw new RuntimeException('Cannot write fixture folder');
    $appCss=(string)file_get_contents($root.'/public_html/assets/app.css');
    $accountCss=(string)file_get_contents($root.'/public_html/assets/account-beta.css');
    $styles='<style>'.$appCss."\n".$accountCss."\n".$css.'</style>';
    foreach(['reading-light'=>[$html,false],'reading-dark'=>[$html,true],'new-reader'=>[$empty,false]] as $name=>[$body,$dark]){
        $bodyClass=$dark?'account-shell dark':'account-shell';
        $document='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .$styles.'</head><body class="'.$bodyClass.'">'
            .'<header class="site-header"><div class="site-header-inner"><a class="brand" href="/">Pune Times Mirror</a></div></header>'
            .'<div class="app-workspace"><aside class="app-sidebar">'.$nav.'</aside>'
            .'<main class="workspace-main">'.$body.'</main></div></body></html>';
        file_put_contents($out.'/'.$name.'.html',$document);
    }
}
echo 'PASS Reader Hub source and route contracts'.PHP_EOL;
