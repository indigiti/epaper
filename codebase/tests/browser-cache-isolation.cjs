'use strict';
/**
 * Deterministic service-worker contract test in an isolated Node VM.
 * Not a browser/Cloudways live test. No network, cookies or real users.
 */
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const sw=fs.readFileSync(path.join(__dirname,'../public_html/service-worker.js'),'utf8');
const origin='https://staging-epdi.example.test';
const handlers={};
const stores=new Map();
let offline=false, visitor='A', networkCalls=0, protectedNoStore=false;
const key=x=>typeof x==='string'?x:x.url;
const response=(body)=>({ok:true,body,clone(){return response(body);}});
const cache={
  async keys(){return Array.from(stores.keys());},
  async open(name){
    if(!stores.has(name))stores.set(name,new Map());
    const rows=stores.get(name);
    return{
      async keys(){return Array.from(rows.keys()).map(url=>({url}));},
      async put(request,value){rows.set(key(request),value);},
      async addAll(items){for(const item of items)rows.set(key(item),response('offline-shell:'+item));},
      async delete(request){return rows.delete(key(request));}
    };
  },
  async delete(name){return stores.delete(name);},
  async match(request){
    for(const rows of stores.values()){
      const found=rows.get(key(request));
      if(found)return found;
    }
  }
};
const network=async(request,options)=>{
  networkCalls++;
  if(options?.cache==='no-store'&&String(request.url).includes('/api/v2/offline/'))protectedNoStore=true;
  if(offline)throw new Error('simulated offline');
  if(request.mode==='navigate')return response('private-home:'+visitor);
  return response('static:'+request.url);
};
const context={self:{
  location:{origin},
  clients:{claim:async()=>{}},
  addEventListener(name,handler){handlers[name]=handler;}
}, caches:cache,fetch:network,URL,Promise,console};
vm.runInNewContext(sw,context,{filename:'service-worker.js',timeout:2000});
assert.equal(typeof handlers.activate,'function');
assert.equal(typeof handlers.fetch,'function');
function serviceEvent(request){
  let promise;
  handlers.fetch({
    request,
    respondWith(p){promise=Promise.resolve(p);}
  });
  return promise;
}
function makeRequest(uri,mode='navigate'){
  return {url:origin+uri,method:'GET',mode};
}
function activate(){
  let work;
  handlers.activate({waitUntil(p){work=p;}});
  return work;
}
(async()=>{
  const legacy='epdi-v2.0.2.1:pages';
  const previous='epdi-v2.0.2.0:pages';
  stores.set(legacy,new Map([[origin+'/',response('private-home:prior-user')]]));
  stores.set(previous,new Map([[origin+'/',response('private-home:older-user')]]));
  stores.set('epdi-v2.0.2.1:shell',new Map([['/offline.html',response('public offline screen')]]));
  await activate();
  assert.equal(stores.has(legacy),false,'current-version legacy dynamic HTML must be purged');
  assert.equal(stores.has(previous),false,'older-version HTML must be purged');

  visitor='A'; const a=await serviceEvent(makeRequest('/'));
  assert.equal(a.body,'private-home:A');
  visitor='B'; const b=await serviceEvent(makeRequest('/'));
  assert.equal(b.body,'private-home:B','B must not see A dynamic HTML');
  assert.equal(networkCalls,2,'both navigations must hit the network');
  assert.equal([...stores.values()].some(rows=>rows.has(origin+'/')),false,'HTML cannot be cached');

  offline=true;
  const absent=await serviceEvent(makeRequest('/'));
  assert.equal(absent.body,'public offline screen','offline fallback must not reveal user A');
  offline=false;
  visitor='logged-out';const logout=await serviceEvent(makeRequest('/'));
  assert.equal(logout.body,'private-home:logged-out','logout must not replay B');

  // Keep immutable same-origin shell assets cacheable.
  const asset=makeRequest('/assets/app.css?v=2.0.2.1','no-cors');
  const cssA=await serviceEvent(asset);
  const before=networkCalls;
  const cssB=await serviceEvent(asset);
  assert.equal(cssB.body,cssA.body);
  assert.equal(networkCalls,before,'static CSS should be reused from cache');

  // Protected bytes may be fetched but never saved in CacheStorage.
  await serviceEvent(makeRequest('/api/v2/offline/licenses/fixture/pages/1','cors'));
  assert.equal(protectedNoStore,true);
  assert.equal([...stores.values()].some(rows=>[...rows.keys()].some(k=>k.includes('/api/v2/offline/'))),false);

  console.log('PASS PWA isolation simulator: old-session purge, A/B/logout, offline fallback, static assets and protected bytes (10 assertions)');
})().catch(error=>{console.error('FAIL PWA isolation simulator:',error.message);process.exitCode=1;});
