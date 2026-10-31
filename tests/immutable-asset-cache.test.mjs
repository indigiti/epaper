import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { runInNewContext } from 'node:vm';
import assert from 'node:assert/strict';

const root=resolve(import.meta.dirname,'..');
const source=await readFile(resolve(root,'public_html/service-worker.js'),'utf8');
const handlers={};
const storage=new Map();
const names=[];
const network=[];
let offline=false;
const cacheName='epdi-v2.0.2.3:shell';
const nameFor=request=>typeof request==='string'?new URL(request,'https://epaper.test/').href:request.url;
const cacheApi={
  async open(name){
    if(!storage.has(name)){storage.set(name,new Map());names.push(name);}
    const map=storage.get(name);
    return {
      put:async(req,res)=>{map.set(nameFor(req),res.clone());},
      match:async(req)=>map.get(nameFor(req))?.clone(),
      keys:async()=>[...map.keys()].map(url=>new Request(url)),
      delete:async req=>map.delete(nameFor(req)),
      addAll:async urls=>{
        for(const url of urls)map.set(nameFor(url),new Response('precache '+url));
      }
    };
  },
  async match(req,options={}){
    const key=nameFor(req);
    for(const map of storage.values()){
      for(const [url,response] of map){
        if(url===key)return response.clone();
        if(options.ignoreSearch&&new URL(url).pathname===new URL(key).pathname)return response.clone();
      }
    }
    return undefined;
  },
  async keys(){return [...storage.keys()];},
  async delete(name){return storage.delete(name);}
};
const fakeFetch=async(req,opts)=>{
  const url=nameFor(req);
  network.push({url,cache:opts?.cache||'default'});
  if(offline)throw new Error('Simulated offline');
  return new Response('network '+network.length,{status:200});
};
const self={
  location:{origin:'https://epaper.test'},
  addEventListener(name,callback){handlers[name]=callback;},
  skipWaiting:async()=>undefined,
  clients:{claim:async()=>undefined}
};
runInNewContext(source,{self,caches:cacheApi,fetch:fakeFetch,URL,Request,Response,console});

const hit=async path=>{
  const request=new Request(new URL(path,'https://epaper.test'));
  let promise=null;
  handlers.fetch({request,respondWith(value){promise=value;}});
  assert(promise!==null,'SW must intercept same-origin asset request');
  return promise;
};
const check=(ok,title)=>{
  assert(ok,title);
  console.log('PASS',title);
};
const keyOld='/assets/home-halo.css?v=2.0.2.2';
const oldRequest=new Request('https://epaper.test'+keyOld);
const cache=await cacheApi.open(cacheName);
await cache.put(oldRequest,new Response('stale navy CSS'));
const previous=network.length;
const fresh=await hit(keyOld);
check((await fresh.text())==='network 1','Old static-version CSS bypasses stale service-worker cache');
check(network.length===previous+1&&network.at(-1).cache==='no-cache',
    'Unfingerprinted CSS revalidates despite immutable HTTP cache');

const exact='/assets/home-halo.css?v=2.0.2.3.abc12-def3';
const revisioned=new Request('https://epaper.test'+exact);
await cache.put(revisioned,new Response('cached immutable new CSS'));
const before=network.length;
const stillCached=await hit(exact);
check((await stillCached.text())==='cached immutable new CSS'&&network.length===before,
  'Fingerprinted CSS remains cache-first for repeat-load performance');

const newRevision='/assets/home-halo.css?v=2.0.2.3.xyz42-def4';
const newResponse=await hit(newRevision);
check((await newResponse.text()).startsWith('network '),
  'New asset fingerprint automatically causes a fresh download');

offline=true;
const offlineRequest='/assets/home-halo.css?v=2.0.2.3.missing-0';
const offlineAsset=await hit(offlineRequest);
const offlineText=await offlineAsset.text();
check(offlineText.includes('home-halo.css')||offlineText.includes('CSS'),
  'Offline first load can reuse a precached asset with a different revision query');

offline=false;
const protectedRequest=new Request('https://epaper.test/media/private-page');
let protectedResponse;
handlers.fetch({request:protectedRequest,respondWith(value){protectedResponse=value;}});
await protectedResponse;
check(network.at(-1).cache==='no-store','Protected newspaper data still uses no-store');
check(![...storage.values()].some(m=>m.has(protectedRequest.url)),
  'Protected pages never enter CacheStorage');

await cacheApi.open('epdi-v2.0.2.1:shell');
let activated;
handlers.activate({waitUntil(promise){activated=promise;}});
await activated;
check(!storage.has('epdi-v2.0.2.1:shell'),
  'Activating new service worker discards obsolete cached assets');
check(storage.has(cacheName),'Activating SW retains current cache generation');

console.log('PASS complete cache invalidation and offline service-worker behavior');
