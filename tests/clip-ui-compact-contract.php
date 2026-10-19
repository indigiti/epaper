<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$read=static fn(string $file):string=>(string)file_get_contents($root.'/'.$file);
$app=$read('app/App.php');
$view=$read('app/Core/View.php');
$css=$read('public_html/assets/clip-ui-compact.css');
$js=$read('public_html/assets/app.js');
$reader=$read('public_html/assets/reader.js');

foreach([
 'width:min(320px,calc(100vw - 24px))',
 '.reader-page .clip-create-sheet',
 '.reader-page .clip-create-head',
 '.reader-page .clip-expiry-note',
 '.reader-page .clip-create-submit',
 'max-height:calc(100dvh - 20px)',
] as $token){
 if(!str_contains($css,$token))
  throw new RuntimeException('Compact Reader clipping dialog missing: '.$token);
}
foreach([
 '.clip-showcase .clip-icon-rail{',
 'flex-wrap:nowrap',
 'overflow-x:auto',
 'scroll-snap-type:x proximity',
 '.clip-showcase .clip-sr-only',
 '.clip-showcase .clip-icon-rail>a:focus-visible',
 '[data-clip-format="pdf"]',
 '[data-clip-share="telegram"]',
 '@media(prefers-reduced-motion:reduce)',
] as $token){
 if(!str_contains($css,$token))
  throw new RuntimeException('Accessible horizontal clipping icon rail missing: '.$token);
}
foreach([
 "'clip_ui'=>true",
 'clip-share-grid clip-icon-rail',
 'clip-format-grid clip-icon-rail',
 'role="group" aria-label="Clipping download formats"',
 'aria-label="Share on WhatsApp"',
 'aria-label="Share on Facebook"',
 'aria-label="Share on X"',
 'aria-label="Share on Telegram"',
 'aria-label="Copy clipping link"',
 'View::icon('."'send'".')',
 'title="Share on Telegram"',
 'data-clip-format',
 'data-format-state',
 '7-day access',
 'No extensions',
] as $token){
 if(!str_contains($app,$token))
  throw new RuntimeException('Clipping controls or seven-day information missing: '.$token);
}
if(!str_contains($app,'$f['."'ready'".']') ||
 !str_contains($app,"' aria-disabled=\"true\"'") ||
 !str_contains($app,"' download'"))
 throw new RuntimeException('Download ready/preparing permission semantics lost');
if(!str_contains($js,"card.setAttribute('aria-label',hint)") ||
 !str_contains($js,'card.title=hint') ||
 !str_contains($js,"if(a.getAttribute('aria-disabled')==='true')") ||
 !str_contains($js,'data-clip-copy') ||
 !str_contains($js,'data-clip-share'))
 throw new RuntimeException('Sharing or asynchronous download state behavior regressed');
if(!str_contains($view,'$clipCompact=(bool)($opts[\'clip_ui\']??false)') ||
 !str_contains($view,'($reader||$clipCompact)') ||
 !str_contains($view,"Build::asset('/assets/clip-ui-compact.css')"))
 throw new RuntimeException('Compact styles must load only in Reader and clipping views');
if(!str_contains($reader,"data-clip-modal") ||
 !str_contains($reader,"data-clip-create") ||
 !str_contains($reader,"data-clip-title"))
 throw new RuntimeException('Reader clipping modal bindings regressed');
echo "Compact clipping modal and horizontal share/download UI: OK\n";
