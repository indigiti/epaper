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
console.log('Reader interaction source regression checks passed');
