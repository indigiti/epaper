<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);
$swirl=$read('public_html/assets/reader-swipe-feedback.js');
$reader=$read('public_html/assets/reader.js');
$flip=$read('public_html/assets/reader-flip.js');
$css=$read('public_html/assets/reader.css');
$view=$read('app/Core/View.php');
foreach([
  "document.querySelector('#reader')","root?.querySelector('[data-page-stage]')",
  "overlay.setAttribute('aria-hidden','true')",'stage.appendChild(overlay)',
  'window.EpdiSwipeFeedback={show,hide,commit}',
  'Math.min(1.5,window.devicePixelRatio||1)',
  "prefers-reduced-motion: reduce",'navigator.connection?.saveData',
  'time-lastFrame<33','clearTimeout(exitTimer)','requestAnimationFrame(draw)',
  "window.addEventListener('pagehide',hide)",
] as $token)
 if(!str_contains($swirl,$token))
   throw new RuntimeException('Swipe visual safety/behavior missing: '.$token);
if(str_contains($swirl,'touchstart')||str_contains($swirl,'touchend')||
 str_contains($swirl,'window.location')||str_contains($swirl,'location.href')||
 str_contains($swirl,'document.body.classList'))
 throw new RuntimeException('Swipe feedback must not register duplicate gestures or reload edition pages');
foreach([
 'const swipeFx=window.EpdiSwipeFeedback;',
 'window.EpdiReaderViews?.mode?.()',
 "return mode==='page'",
 '!clipMode&&!articlePick&&!pinch&&!pagePending',
 'absScale()<=fitPageScale()*1.18',
 "stage.addEventListener('touchstart'",
 "stage.addEventListener('touchmove'",
 "stage.addEventListener('touchend'",
 "stage.addEventListener('touchcancel',clearSwipe",
 "Math.abs(dx)>70",
 'Math.abs(dx)>Math.abs(dy)*1.3',
 'target>=1&&target<=pageCount',
 "swipeFx?.show(dx<0?'left':'right')",
 "swipeFx?.commit(dx<0?'left':'right')",
 "void gotoPage(target,{method:'swipe'})",
] as $token)
 if(!str_contains($reader,$token))
  throw new RuntimeException('Reader swipe/navigation integration missing: '.$token);
if(str_contains($reader,"window.location.href = nextUrl")||
   !str_contains($flip,'function beginDrag(e)')||
   !str_contains($flip,"mode!=='flip'")||
   !str_contains($css,'.reader-view-continuous .reader-stage{touch-action:pan-y}'))
 throw new RuntimeException('Flip/Continuous page-turn or scroll behavior regressed');
foreach([
 '.reader-swipe-fx{','.reader-swipe-fx-arrow{',
 '.reader-swipe-fx.committed','.reader-swipe-fx canvas{display:none}',
 '@media(prefers-reduced-motion:reduce)'
] as $token)
 if(!str_contains($css,$token))
  throw new RuntimeException('Swipe visual styles missing: '.$token);
if(!str_contains($view,"Build::asset('/assets/reader-swipe-feedback.js')")||
 !str_contains($view,"Build::asset('/assets/reader.js').'&swipe-fx=20261019-v1'")||
 !str_contains($view,"Build::asset('/assets/reader.css').'&swipe-fx=20261019-v1'"))
 throw new RuntimeException('Reader-only asset ordering and cache version missing');
$fxPosition=strpos($view,"Build::asset('/assets/reader-swipe-feedback.js')");
$readerPosition=strpos($view,"Build::asset('/assets/reader.js')");
if($fxPosition===false||$readerPosition===false||$fxPosition>$readerPosition)
 throw new RuntimeException('Swipe effect must initialize before Reader gesture integration');
echo "Reader Page-mode swipe feedback safety/contract checks: OK\n";
