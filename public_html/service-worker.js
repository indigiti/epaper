const VERSION='epdi-v2.0.2.1';
const SHELL_CACHE=`${VERSION}:shell`;
const PAGE_CACHE=`${VERSION}:pages`;
const SHELL=[
  '/assets/app.css?v=2.0.2.1',
  '/assets/app.js?v=2.0.2.1',
  '/assets/offline-vault.js?v=2.0.2.1',
  '/assets/offline-vault.css?v=2.0.2.1',
  '/manifest.webmanifest?v=2.0.2.1',
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
  if(url.pathname.startsWith('/assets/')||url.pathname==='/manifest.webmanifest'){
    event.respondWith((async()=>{
      const cached=await caches.match(request);if(cached)return cached;
      const response=await fetch(request);if(!response||!response.ok)return response;
      // Clone immediately, before any asynchronous cache-open/write boundary. Returning
      // the original response allows the browser to consume its body; cloning later can
      // otherwise fail with "Response body is already used".
      const cacheCopy=response.clone();
      try{const cache=await caches.open(SHELL_CACHE);await cache.put(request,cacheCopy)}catch(_e){}
      return response;
    })());return;
  }
});
