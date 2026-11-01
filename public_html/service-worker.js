const VERSION='epdi-v2.0.2.3';
const REVISIONED_ASSET=/^2\.0\.2\.3\.[0-9a-z]+-[0-9a-z]+$/;
const SHELL_CACHE=`${VERSION}:shell`;
const PAGE_CACHE=`${VERSION}:pages`;
const SHELL=[
  '/assets/app.css?v=2.0.2.3',
  '/assets/app.js?v=2.0.2.3',
  '/assets/offline-vault.js?v=2.0.2.3',
  '/assets/offline-vault.css?v=2.0.2.3',
  '/offline.html',
  '/offline-library.html',
  '/offline-reader.html',
  '/assets/icon-192.png',
  '/assets/icon-512.png'
];
async function trim(cacheName,max){const c=await caches.open(cacheName),keys=await c.keys();if(keys.length<=max)return;await Promise.all(keys.slice(0,keys.length-max).map(k=>c.delete(k)))}
self.addEventListener('install',event=>{event.waitUntil(caches.open(SHELL_CACHE).then(c=>c.addAll(SHELL)).then(()=>self.skipWaiting()))});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==SHELL_CACHE&&key!==PAGE_CACHE&&(key.startsWith('epdi-v2')||key.startsWith('epaper-saas-')||key.startsWith('epaper-reader-media-')||key==='epaper-offline-v1')).map(key=>caches.delete(key)))).then(()=>self.clients.claim()))});
self.addEventListener('fetch',event=>{
  const request=event.request,url=new URL(request.url);if(request.method!=='GET'||url.origin!==self.location.origin)return;
  if(url.pathname==='/pwa-manifest.webmanifest'||url.pathname==='/manifest.webmanifest'){
    event.respondWith(fetch(request,{cache:'no-store'}));return;
  }
  if(url.pathname.startsWith('/media/')||url.pathname.startsWith('/api/v2/offline/')){
    // Protected newspaper bytes are never persisted in CacheStorage. The Offline Vault
    // stores only AES-GCM ciphertext in IndexedDB and decrypts on demand in page memory.
    event.respondWith(fetch(request,{cache:'no-store'}));return;
  }
  if(request.mode==='navigate'){
    const privatePath=url.pathname.startsWith('/admin')||url.pathname.startsWith('/account')||url.pathname.startsWith('/login')||url.pathname.startsWith('/auth/');
    const offlineStatic=url.pathname==='/offline-library.html'||url.pathname==='/offline-reader.html'||url.pathname==='/offline.html';
    if(offlineStatic){event.respondWith(caches.match(request).then(c=>c||fetch(request)));return;}
    event.respondWith(fetch(request).then(async response=>{const policy=(response.headers.get('Cache-Control')||'').toLowerCase();if(!privatePath&&response.ok&&!policy.includes('no-store')&&!policy.includes('private')){const cacheCopy=response.clone();const cache=await caches.open(PAGE_CACHE);await cache.put(request,cacheCopy);await trim(PAGE_CACHE,12)}return response}).catch(async()=>{if(!privatePath){const cached=await caches.match(request);if(cached)return cached;}return caches.match('/offline.html')}));return;
  }
  if(url.pathname.startsWith('/assets/')){
    event.respondWith((async()=>{
      const revision=url.searchParams.get('v')||'';
      const immutable=url.pathname.startsWith('/assets/')&&REVISIONED_ASSET.test(revision);
      // Immutable cache-first is ONLY safe for a URL that includes the actual
      // on-disk asset revision. All other asset URLs revalidate when online.
      if(immutable){
        const cached=await caches.match(request);
        if(cached)return cached;
      }
      try{
        const response=await fetch(request,immutable?undefined:{cache:'no-cache'});
        if(!response||!response.ok)return response;
        // Clone before async cache operations to keep the returned body usable.
        const copy=response.clone();
        try{
          const cache=await caches.open(SHELL_CACHE);
          await cache.put(request,copy);
        }catch(_e){}
        return response;
      }catch(error){
        // Offline first visit: the precached shell may have a release-only
        // query while the rendered page uses a content-specific fingerprint.
        const cached=await caches.match(request)||
          await caches.match(request,{ignoreSearch:true});
        if(cached)return cached;
        throw error;
      }
    })());return;
  }
});
