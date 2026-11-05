// Fast source-level regression guards for reader interaction failures.
// This does not replace real browser/mobile acceptance tests.
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {Script} from 'node:vm';
const read=name=>readFileSync(new URL('../public_html/assets/'+name,import.meta.url),'utf8');
const offline=read('offline-vault.js');
const reader=read('reader.js');
const flip=read('reader-flip.js');
const prefetch=read('reader-prefetch.js');
for(const [name,source] of [['offline-vault.js',offline],['reader.js',reader],['reader-flip.js',flip],['reader-prefetch.js',prefetch]]){
  assert.doesNotThrow(()=>new Script(source,{filename:name}),name+' must parse');
}
assert.equal(offline.includes("e.target.closest('[data-reader-offline]')"),false,'Offline Vault must not intercept reader offline clicks');
assert.match(reader,/EpdiOfflineVault\.downloadEdition\(editionGuid/, 'Reader must own offline download action');
assert.match(reader,/queuedNavigation=\{n,history,method\}/, 'Latest navigation request must be queued');
assert.match(reader,/const next=queuedNavigation;queuedNavigation=null;/,'Queued navigation must drain');
assert.match(reader,/closest\?\.\('input,textarea,select,/,'Reader must guard input focus');
assert.match(flip,/closest\?\.\('input,textarea,select,/,'Flip keyboard must guard input focus');
assert.doesNotMatch(flip,/best\.intersectionRatio<\.55/,'Continuous pages must not need 55% viewport visibility');
assert.match(prefetch,/if\(q!=='low'\)\{warm\(c\+1/,'Low Data must not automatically prefetch adjacent previews');
assert.match(reader,/effectiveQuality\(\)==='low'\|\|scale<threshold/,'Low Data must disable tiles');
const readerCss=read('reader.css');
assert.match(readerCss,/\.reader-header-tool>span:not\(\.reader-view-glyph\)/,'Mobile header must not visually hide the Flip icon');
assert.match(readerCss,/\.reader-view-trigger > \.reader-view-glyph/,'Flip icon must retain visible dimensions on mobile');
assert.match(reader,/function closeOtherReaderMenus\(activeToggle\)/,'Toolbar menus must share a single close-others coordinator');
for(const name of ['reader-view','quality','reader-edition','reader-more']){
  assert.ok(reader.includes('[data-'+name+'-'),name+' toolbar selector must be coordinated');
}
assert.match(reader,/const options=\$\$\('\[role="menuitemradio"\]',qualityMenu\)/,'Image-quality keyboard navigation must iterate all options');
assert.match(flip,/const options=\$\$\('\[role="menuitemradio"\]',viewMenu\)/,'Reading-mode keyboard navigation must iterate all options');
assert.match(reader,/button\.title=description/,'All reader action icons should expose mouse-hover tooltips');
assert.match(flip,/viewToggle\.title='Reading mode:/,'Dynamically inserted Flip control must expose its own tooltip');
assert.match(reader,/function selectionNormalized\(\)/,'Clipping must normalize coordinates');
assert.match(reader,/selection\.addEventListener\('pointerdown'/,'Clipping handles must support resizing');
assert.match(reader,/\[data-reader-text-mode\]/,'OCR reader mode must be accessible');
assert.match(flip,/continuous\.addEventListener\('scroll'/,'Continuous mode must track scrolled page');
assert.match(prefetch,/decodePreview:q!=='low'/,'Gesture prediction must honor Low Data mode');
const php=readFileSync(new URL('../app/App.php',import.meta.url),'utf8');
const media=readFileSync(new URL('../app/Services/Media.php',import.meta.url),'utf8');
assert.match(php,/private function pageText\(string \$pageGuid\)/,'OCR API endpoint required');
assert.match(php,/e\.status="PUBLISHED"/,'Reader media must respect publication status');
assert.match(media,/function safeStoragePath/,'Media must confine storage paths');
assert.match(media,/Auth::currentMembership\(\)/,'Private media must require active membership');
console.log('Reader interaction source regression checks passed');
