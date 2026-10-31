<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Services/FrontendHomeLayouts.php';
use EpaperSaas\Services\FrontendHomeLayouts;

$data=[
  'brand'=>'Civic Mirror',
  'tagline'=>'The city. The stories. The truth.',
  'editions'=>[
    ['publication_name'=>'Civic Mirror','publication_slug'=>'civic-mirror','slug'=>'civic-mirror-2026-10-18','edition_date'=>'2026-10-18','cover_url'=>''],
    ['publication_name'=>'Pune Times Mirror','publication_slug'=>'pune-times','slug'=>'pune-times-2026-10-17','edition_date'=>'2026-10-17','cover_url'=>''],
  ],
  'publications'=>[
    ['name'=>'Civic Mirror','slug'=>'civic-mirror'],
    ['name'=>'Pune Times Mirror','slug'=>'pune-times'],
  ],
  'articles'=>[
    ['title'=>'Real headline & <quoted>','slug'=>'headline-1','deck'=>'Newsroom description','publication_name'=>'Civic Mirror','primary_image_url'=>'javascript:alert(1)'],
    ['title'=>'Local infrastructure update','slug'=>'headline-2','publication_name'=>'Pune Times Mirror','primary_image_url'=>''],
    ['title'=>'Another news story','slug'=>'headline-3','publication_name'=>'Civic Mirror','primary_image_url'=>''],
  ],
  'highlights'=>[
    ['title'=>'Verified update','target_url'=>'/article/headline-1']
  ],
];
$fails=[];
$check=static function(bool $condition,string $name) use (&$fails):void {
  echo ($condition?'PASS':'FAIL').' '.$name.PHP_EOL;
  if(!$condition)$fails[]=$name;
};
$layouts=['Newsstand Modern'=>'fth-newsstand','Editorial Portal'=>'fth-editorial','Classic Newspaper'=>'fth-newspaper','Digital Magazine'=>'fth-magazine'];
$markup=[];
foreach($layouts as $name=>$expectedClass){
    $html=FrontendHomeLayouts::render($name,$data);
    $markup[$name]=$html;
    $check(str_contains($html,$expectedClass),$name.' has its own layout structure');
    $check(str_contains($html,'/edition/civic-mirror-2026-10-18/page/1'),$name.' retains published edition links');
    $check(str_contains($html,'/epaper/pune-times'),$name.' uses real publication links');
    $check(!str_contains($html,'javascript:'),$name.' rejects unsafe image scheme');
    $check(!str_contains($html,'<quoted>'),$name.' safely escapes a publisher headline');
    $check(str_contains($html,'fth-archive-end'),$name.' contains archive path');
}
$check(count(array_unique(array_values($markup)))===4,'The four approved designs use different HTML markup');
$check(FrontendHomeLayouts::render('Default',$data)==='','Default remains in its original homepage renderer');
$empty=FrontendHomeLayouts::render('Digital Magazine',['brand'=>'ePaper','editions'=>[],'publications'=>[]]);
$check(str_contains($empty,'New editions are on the way.'),'Empty newspaper inventory is handled');
$view=(string)file_get_contents($root.'/app/Core/View.php');
$app=(string)file_get_contents($root.'/app/App.php');
$css=(string)file_get_contents($root.'/public_html/assets/frontend-layouts.css');
$check(str_contains($app,'FrontendHomeLayouts::render($selectedHomeTheme'),'Homepage uses themed renderer');
$check(str_contains($app,'$homeContent=$resume.$hero.$recent.$pubHtml.$archiveCta.$hi'),'Original Default homepage kept');
$check(str_contains($view,'frontend-layouts.css'),'New styles are linked on public HTML shell');
foreach(array_values($layouts) as $name)$check(str_contains($css,'.'.$name),'Style definitions exist for '.$name);
if($fails){fwrite(STDERR,implode("\n",$fails).PHP_EOL);exit(1);}

if(in_array('--fixtures',$argv,true)){
    $folder=$root.'/tests/frontend-layout-fixtures';
    if(!is_dir($folder)&&!mkdir($folder,0775,true)&&!is_dir($folder))throw new RuntimeException('Cannot create fixture directory');
    $appCSS=(string)file_get_contents($root.'/public_html/assets/app.css');
    $themeCSS=(string)file_get_contents($root.'/public_html/assets/frontend-themes.css');
    foreach($layouts as $name=>$class){
        $slug=strtolower(str_replace(' ','-',$name));
        $page='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
          .'<style>'.$appCSS."\n".$themeCSS."\n".$css.'</style></head>'
          .'<body class="public-shell theme-'.$slug.'"><main class="main-container">'.$markup[$name].'</main></body></html>';
        file_put_contents($folder.'/'.$slug.'.html',$page);
    }
}
echo 'PASS four approved frontend layout regressions'.PHP_EOL;
