<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
 $prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;
 $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
 if(is_file($file))require $file;
});
$geom=\EpaperSaas\Services\ManualClipGeometry::class;
$good=$geom::parse(['x'=>'0.10','y'=>'0.2','w'=>'0.4','h'=>'0.38']);
if($good['x']!==.1||$good['h']!==.38)throw new RuntimeException('Valid rectangle rejected');
foreach([
 ['x'=>'-0.1','y'=>'0','w'=>'0.2','h'=>'0.3'],
 ['x'=>'0.1','y'=>'0.1','w'=>'0.019','h'=>'0.3'],
 ['x'=>'0.9','y'=>'0.1','w'=>'0.2','h'=>'0.3'],
 ['x'=>'0.1','y'=>'0.8','w'=>'0.3','h'=>'0.4'],
 ['x'=>'INF','y'=>'0.1','w'=>'0.3','h'=>'0.4'],
 ['x'=>'0.1','y'=>'0.1','w'=>'0.3'],
 ['x'=>['0.1'],'y'=>'0.1','w'=>'0.3','h'=>'0.4'],
] as $bad){
 try{$geom::parse($bad);throw new RuntimeException('Invalid rectangle accepted');}
 catch(\InvalidArgumentException){}
}
$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);
$app=$read('app/App.php');$controller=$read('app/Controllers/AdminManualClippingController.php');
$clip=$read('app/Services/ClipService.php');$catalog=$read('app/Services/AdminFeatureCatalog.php');
$view=$read('app/Core/View.php');$ui=$read('app/Views/ManualClippingView.php');
$js=$read('public_html/assets/manual-clipping.js');
foreach(["Auth::requireAdmin();if(\$method==='POST')Http::verifyCsrf();",
 'AdminManualClippingController::dispatch'] as $tok)if(!str_contains($app,$tok))throw new RuntimeException('Admin/CSRF route missing');
foreach(['OWNER','PUBLISHER_ADMIN','EDITOR','Http::privateNoStore()','RateLimiter::allow',
 'TenantData::tenantBin()','ClipService::createEditorial','ManualClipMarking::add','LIMIT 21','LIMIT 500','LIMIT 24','realpath('] as $tok)
 if(!str_contains($controller,$tok))throw new RuntimeException('Missing security contract '.$tok);
foreach(['clip.manual_created',"'PRIVATE',true",'expiresAt=$editorial?null',
 'if(!$editorial)PlatformClient::instance()->usage'] as $tok)
 if(!str_contains($clip,$tok))throw new RuntimeException('Manual storage contract missing '.$tok);
if(!str_contains($catalog,"'/admin/manual-clipping'"))throw new RuntimeException('Navigation missing');
foreach(['data-manual-stage','data-manual-crop-preview','data-manual-form','title="Create private clipping now"','Save article outline'] as $tok)
 if(!str_contains($ui,$tok))throw new RuntimeException('Editor UI missing '.$tok);
foreach(['pointerdown','pointermove','pointerup','getContext','data-manual-coord'] as $tok)
 if(!str_contains($js,$tok))throw new RuntimeException('Client selection missing '.$tok);
foreach(["Build::asset('/assets/manual-clipping.css')","Build::asset('/assets/manual-clipping.js')"] as $tok)
 if(!str_contains($view,$tok))throw new RuntimeException('Clipping assets not included');

foreach(['GROUP BY e.edition_guid','COUNT(p.page_guid) ready_pages',
  'JOIN ep_pages p','p.edition_guid=?','checkdate(','has_next','LIMIT 21 OFFSET'] as $tok)
 if(!str_contains($controller,$tok))throw new RuntimeException('Edition-first browse contract missing '.$tok);
foreach(['manual-edition-filters','manual-collection-layout','manual-edition-card',
  'manual-page-picker','selected edition','pageNo','http_build_query'] as $tok)
 if(!str_contains(strtolower($ui),strtolower($tok)))throw new RuntimeException('Edition collection view contract missing '.$tok);
$css=$read('public_html/assets/manual-clipping.css');
foreach(['.manual-edition-list','.manual-edition-pages','.manual-page-pick','@media(max-width:900px)'] as $tok)
 if(!str_contains($css,$tok))throw new RuntimeException('Edition collection responsive CSS missing '.$tok);
$editionGuid='01a14d4c-0ee8-71df-a18e-e714ad1d8bc7';
$pageGuid='01a14d4c-0ee8-71df-a18e-e714ad1d8bc8';
$rendered=\EpaperSaas\Views\ManualClippingView::index(
 [['edition_guid'=>\EpaperSaas\Core\Uuid::toBinary($editionGuid),
   'edition_title'=>'Civic Mirror','edition_date'=>'2026-10-18',
   'publication_name'=>'Civic Mirror','ready_pages'=>1]],
 [['page_guid'=>\EpaperSaas\Core\Uuid::toBinary($pageGuid),'page_number'=>1,'label'=>'Front page']],
 [['publication_guid'=>\EpaperSaas\Core\Uuid::toBinary($editionGuid),'name'=>'Civic Mirror']],
 ['edition_guid'=>\EpaperSaas\Core\Uuid::toBinary($editionGuid),
   'edition_title'=>'Civic Mirror','edition_date'=>'2026-10-18',
   'publication_name'=>'Civic Mirror','ready_pages'=>1],
 ['publication'=>'','date'=>'','page'=>1,'has_next'=>false]
);
if(substr_count($rendered,'class="manual-edition-card active"')!==1 ||
   !str_contains($rendered,'/admin/manual-clipping/'.$pageGuid) ||
   !str_contains($rendered,'Page 1'))
 throw new RuntimeException('Edition page picker rendering contract failed');

// Headline is optional only for editor-created Clip Marking outlines, not semantic articles.
$markService=\EpaperSaas\Services\ManualClipMarking::class;
if($markService::normalizeTitle('')!=='Untitled article' ||
   $markService::normalizeTitle(" \t\n")!=='Untitled article' ||
   $markService::normalizeTitle('  Sample story  ')!=='Sample story' ||
   $markService::normalizeTitle(str_repeat('a',180))!==str_repeat('a',180))
 throw new RuntimeException('Clip Marking optional-headline fallback is incorrect');
try{$markService::normalizeTitle(str_repeat('b',181));throw new RuntimeException('Overlong headline accepted');}
catch(\InvalidArgumentException){}
if(!str_contains($ui,'Headline (optional)') ||
   !str_contains($ui,'placeholder="Leave blank for Untitled article"') ||
   str_contains($ui,'name="title" maxlength="180" required'))
 throw new RuntimeException('Clip Marking view still requires a headline');
if(str_contains($js,'!headline?.value.trim()') ||
   !str_contains($js,'Add a headline if desired') ||
   !str_contains($js,'if(!valid(parse()))'))
 throw new RuntimeException('Clip Marking browser still requires headline');
if(!str_contains($clip, "if(\$title==='')\$title='News clipping'"))
 throw new RuntimeException('Private clip title fallback lost');

$mark=$read('app/Services/ManualClipMarking.php');
$migration=$read('database/upgrade-v2.0.2.2-clip-marking.sql');
$schemaDoc=$read('app/Services/SchemaDoctor.php');
$reader=$read('public_html/assets/reader.js');
$flip=$read('public_html/assets/reader-flip.js');
$overlay=$read('public_html/assets/reader-editorial-clips.js');
$readerCss=$read('public_html/assets/reader-editorial-clips.css');
foreach(['ep_clip_marks','ix_mark_page','ix_mark_actor','status VARCHAR(16)'] as $tok)
 if(!str_contains($migration,$tok))throw new RuntimeException('Clipping migration missing '.$tok);
foreach(['upgrade-v2.0.2.2-clip-marking.sql','CLIP_MARK_TABLE'] as $tok)
 if(!str_contains($schemaDoc,$tok))throw new RuntimeException('Clip marking schema repair missing '.$tok);
foreach(['e.status="PUBLISHED"','m.status="ACTIVE"','m.tenant_guid=?','m.page_guid=?',
 'LIMIT 80','no_crop_generated','ManualClipGeometry::parse','hash_equals','Audit::log'] as $tok)
 if(!str_contains($mark,$tok))throw new RuntimeException('Marking security contract missing '.$tok);
if(str_contains($ui,'EpaperSaasCoreUuid')||
   !str_contains($ui,'Uuid::isValid($actor)')||
   !str_contains($ui,'Uuid::toBinary($actor)'))
 throw new RuntimeException('Clip marking editor incorrectly resolves actor UUID');
if(!str_contains($js,'data-manual-final-action') ||
   !str_contains($js,"e.submitter?.value==='private'"))
 throw new RuntimeException('Private manual clipping submit action can be lost');
foreach(['title="Convert private clip to reader outline"','manual-existing-mark','manual-mark-row',
 'Save article outline','manual-legacy-clip'] as $tok)
 if(!str_contains($ui,$tok))throw new RuntimeException('Clip marking editor missing '.$tok);
foreach(['ManualClipMarking::add','ManualClipMarking::change','ManualClipMarking::importPrivateClip',
 'limitActions','self::allow()','Http::redirect'] as $tok)
 if(!str_contains($controller,$tok))throw new RuntimeException('Controller mark workflow missing '.$tok);
foreach(['clip-marks','ManualClipMarking::respond'] as $tok)
 if(!str_contains($app,$tok))throw new RuntimeException('Public mark lookup missing '.$tok);
foreach(['createFromEditorialMarker','openClipModal({bounds:b','pageGuid!==sourcePageGuid',
 'window.EpdiReaderViews?.setMode'] as $tok)
 if(!str_contains($reader,$tok))throw new RuntimeException('On-demand clipping bridge missing '.$tok);
foreach(['MutationObserver','reader-flip-page','reader-continuous-page',
 'clip-marks','reader-editorial-marker','createFromEditorialMarker'] as $tok)
 if(!str_contains($overlay,$tok))throw new RuntimeException('Reader outline handling missing '.$tok);
if(!str_contains($flip,'delete el.dataset.editorialBoundGuid') ||
   !str_contains($readerCss,'.reader-editorial-marker'))
 throw new RuntimeException('Reader outline lifecycle missing');
if(str_contains($overlay,'reader-editorial-toggle') ||
   str_contains($overlay,"toggle.textContent='Articles'") ||
   str_contains($overlay,'header.insertBefore(toggle') ||
   str_contains($readerCss,'.reader-editorial-toggle'))
 throw new RuntimeException('Public Reader Articles toolbar control must stay removed');
if(!str_contains($overlay,"let enabled=true;") ||
   !str_contains($overlay,"layer.hidden=!enabled") ||
   !str_contains($overlay,'window.EpdiEditorialClips={attach,showPage:syncDocument,scan:scanOtherViews,setEnabled}'))
 throw new RuntimeException('Approved editorial outlines must still display and remain operable');
if(!str_contains($view,'editorial-ui=no-toolbar-20261019'))
 throw new RuntimeException('Reader editorial JS needs targeted cache bust after toolbar removal');
foreach(["Build::asset('/assets/reader-editorial-clips.css')",
 "Build::asset('/assets/reader-editorial-clips.js')"] as $tok)
 if(!str_contains($view,$tok))throw new RuntimeException('Reader assets missing '.$tok);
// Area Mapping integration: editable, movable, resizable article coordinates (no eager crop).
foreach(['function update(string $pageGuid,string $markGuid','ManualClipGeometry::parse($input)',
  'clip_mark.updated','tenant_guid=? AND page_guid=? AND mark_guid=?',
  'Editors may edit only their own outlines',"'image_created'=>false"] as $tok)
 if(!str_contains($mark,$tok))throw new RuntimeException('Existing marking update protection missing: '.$tok);
foreach(['self::updateMark','ManualClipMarking::update','page_number DESC LIMIT 1',
  'page_number ASC LIMIT 1','LIMIT 1'] as $tok)
 if(!str_contains($controller,$tok))
  throw new RuntimeException('Adjacent-page or edit route missing '.$tok);
foreach(['data-manual-handle="nw"','data-manual-handle="se"','data-manual-quick-save',
  'data-manual-start','data-manual-open-mark','data-manual-page-url','tabindex="0"',
  'manual-page-navigation','Update article outline','Edit marked article'] as $tok)
 if(!str_contains($ui,$tok)&&!str_contains($js,$tok))
  throw new RuntimeException('Area mapper editor control missing '.$tok);
foreach(['pointerdown','pointermove','pointerup','pointercancel','setPointerCapture',
  "mode:'resize'","mode:'move'","mode:'draw'",'resizeBox','editMark','requestSubmit',
  'manualFinalAction','manual-open-mark'] as $tok)
 if(!str_contains($js,$tok)&&!str_contains($ui,$tok))
  throw new RuntimeException('Area mapper interaction missing '.$tok);
if(!str_contains($css,'.manual-resize-handle') ||
   !str_contains($css,'.manual-quick-save') ||
   !str_contains($css,'.manual-existing-mark.is-editable'))
 throw new RuntimeException('Area mapper CSS missing');

// Remove must be an audited soft delete; never purge stored clips or cross tenant boundaries.
foreach([
 'function remove(string $pageGuid,string $markGuid): void',
 'status="DELETED"','clip_mark.removed',
 'Editors may remove only their own outlines',
 'status IN ("ACTIVE","HIDDEN")',
 'AND m.status="ACTIVE"',
 'Editors may edit only their own outlines',
 'Editors may change only their own markings',
] as $tok)if(!str_contains($mark,$tok))throw new RuntimeException('Secure removal contract missing: '.$tok);
if(!str_contains($mark,"FROM ep_clip_marks WHERE tenant_guid=? AND page_guid=? AND status IN") ||
   str_contains($mark,'DELETE FROM ep_clip_marks'))
 throw new RuntimeException('Removed marks still listed, or deleted from database');
foreach(['self::removeMark','ManualClipMarking::remove','?mark=removed',"/remove$#i",
 'self::limitActions'] as $tok)
 if(!str_contains($controller,$tok))throw new RuntimeException('Remove route missing '.$tok);
foreach(['manual-mark-remove','data-confirm="Remove this marked article',
    "'/remove\"","name=\"_csrf\""] as $tok)
 if(!str_contains($ui,$tok))throw new RuntimeException('Remove action UI/CSRF contract missing: '.$tok);
if(!str_contains($read('public_html/assets/crud.js'),'form.dataset.confirm'))
 throw new RuntimeException('Removal confirmation hook not connected');
if(!str_contains($css,'.manual-mark-actions'))
 throw new RuntimeException('Marked article actions not responsive');

// Icon-only editor controls are accessible and removal on existing overlays is a sibling form.
foreach([
 'data-manual-remove-overlay','manual-overlay-remove-btn','data-manual-discard',
 'data-manual-quick-save','data-manual-start',
 'manual-icon-action','manual-actions-legend',
 'aria-label="Save article outline"','title="Create a new article outline"',
 "View::icon('close')","View::icon('save')","View::icon('trash')",
 'data-confirm="Remove this marked article',
] as $token)
 if(!str_contains($ui,$token))throw new RuntimeException('Clip Marking overlay/icon control missing: '.$token);
if(!str_contains($ui,'if($editable){')||!str_contains($ui,'$closeX=')||
   !str_contains($ui,'$closeY=')||
   !str_contains($ui,'class="manual-overlay-remove" data-manual-remove-overlay'))
 throw new RuntimeException('Overlay remove controls must be editor-authorized and independently positioned');
if(!str_contains($js,"e.target.closest('[data-manual-quick-save],[data-manual-discard],[data-manual-remove-overlay]')") ||
   !str_contains($js,'discard?.addEventListener') ||
   !str_contains($js,'nameAction(primary') ||
   !str_contains($js,'nameAction(quick'))
 throw new RuntimeException('Overlay remove and icon action interaction is incomplete');
if(str_contains($js,"primary.textContent='")||str_contains($js,"quick.textContent='"))
 throw new RuntimeException('Dynamic action labels may not replace icon SVG contents');
foreach(['.manual-icon-action','.manual-overlay-remove-btn','.manual-selection-dismiss',
 '.manual-mark-row>.manual-mark-actions',':focus-visible'] as $selector)
 if(!str_contains($css,$selector))throw new RuntimeException('Accessible overlay icon styling missing: '.$selector);
if(!str_contains($view,'clip-ui=20261019-icons-v1'))
 throw new RuntimeException('Clip Marking JS/CSS cache version has not been advanced');
// Scope: this feature is not changing public Reader or semantic-article headline requirements.
if(!str_contains($ui,'Headline (optional)')||
   str_contains($ui,'name="title" maxlength="180" required')||
   str_contains($js,'!headline?.value.trim()'))
 throw new RuntimeException('Admin Clip Marking headline must remain optional');

echo "Clip marking and on-demand reader clipping contracts: OK\n";
