'use strict';
/**
 * Real Chromium cache-isolation test, staging ONLY, read-only GET navigation.
 * Requires Playwright locally and private storageState JSON from TWO TEST users.
 * Never output cookies, storage state, DOM, user markers or personal identities.
 */
const fs=require('node:fs');
const assert=require('node:assert/strict');
const env=process.env;
const base=(env.EPDI_STAGE_BASE_URL||'').replace(/\/+$/,'');
const approved=(env.EPDI_STAGE_APPROVED_HOST||'').toLowerCase();
let u;
try{u=new URL(base);}catch{throw Error('Set EPDI_STAGE_BASE_URL');}
if(u.protocol!=='https:'||u.hostname.toLowerCase()!==approved||u.hostname==='epaper.digiti.in'
   ||u.username||u.password||u.search||u.hash||(u.pathname!=='/'&&u.pathname!==''))
 throw Error('Refusing unapproved/non-staging host. Require exact approved HTTPS host.');
function stateFile(key){
 const file=env[key];
 if(!file)throw Error('Missing private staged browser state: '+key);
 const stat=fs.statSync(file);
 if(!stat.isFile()||(stat.mode&0o077)!==0)throw Error('Storage-state file must be private (0600): '+key);
 const data=JSON.parse(fs.readFileSync(file,'utf8'));
 if(!Array.isArray(data.cookies)||data.cookies.length===0)throw Error('No staged test-user cookies in '+key);
 return data;
}
const a=stateFile('EPDI_STAGE_READER_A_STORAGE_STATE');
const b=stateFile('EPDI_STAGE_READER_B_STORAGE_STATE');
const markerA=env.EPDI_STAGE_READER_A_MARKER||'';
const markerB=env.EPDI_STAGE_READER_B_MARKER||'';
if(!markerA||!markerB||markerA===markerB)throw Error('Supply distinct non-sensitive visible test markers for A and B');
async function cacheInventory(page){
 return page.evaluate(async()=>{
   const all=[];
   for(const name of await caches.keys()){
     const cache=await caches.open(name);
     for(const req of await cache.keys()){
       all.push({name,path:new URL(req.url).pathname});
     }
   }
   return all;
 });
}
function assertClean(cacheItems){
 assert(cacheItems.every(x=>!x.name.endsWith(':pages')),'Legacy HTML page cache remains');
 assert(cacheItems.every(x=>!['/','/archive','/search','/admin','/account'].includes(x.path)),
   'Sensitive/dynamic HTML was persisted to CacheStorage');
}
async function main(){
 let chromium;
 try{({chromium}=require('playwright'));}catch{
   throw Error('Playwright is not installed in the isolated staging test environment');
 }
 const browser=await chromium.launch({headless:true});
 let count=0;
 try{
   const context=await browser.newContext({storageState:a,serviceWorkers:'allow'});
   const page=await context.newPage();
   const headers=async()=>{const response=await page.goto(base+'/',{waitUntil:'networkidle',timeout:25000});
     assert.equal(response.status(),200);
     const policy=(response.headers()['cache-control']||'').toLowerCase();
     assert(policy.includes('private')&&policy.includes('no-store'),'Private reader HTML must never be cached');
     count++;};
   await headers();
   const bodyA=await page.locator('body').innerText();
   assert(bodyA.includes(markerA),'Reader A marker not found on configured staging fixture');count++;
   await page.evaluate(async()=>{
     await Promise.race([navigator.serviceWorker.ready,new Promise((_,reject)=>setTimeout(()=>reject(Error('SW not ready')),10000))]);
   });
   assertClean(await cacheInventory(page));count++;
   // Swap the active session WITHOUT clearing the service worker or CacheStorage.
   await context.clearCookies();
   await context.addCookies(b.cookies);
   await headers();
   const bodyB=await page.locator('body').innerText();
   assert(bodyB.includes(markerB)&&!bodyB.includes(markerA),'Reader B received data from session A');count++;
   assertClean(await cacheInventory(page));count++;
   // Logout cookie removal without discarding browser persistent caches.
   await context.clearCookies();
   await page.goto(base+'/',{waitUntil:'networkidle',timeout:25000});
   const guest=await page.locator('body').innerText();
   assert(!guest.includes(markerA)&&!guest.includes(markerB),'Guest page leaks previous user');count++;
   assertClean(await cacheInventory(page));count++;
   console.log('PASS real staging Chromium session-isolation (8 checkpoints; A -> B -> guest)');
 }finally{await browser.close();}
}
main().catch(e=>{console.error('FAIL Chromium staging isolation:',e.message);process.exitCode=1;});
