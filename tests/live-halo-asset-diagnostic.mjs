/*
 * Advisory public release diagnostic. No cookies, credentials, private URLs
 * or origin mutations. CI deliberately does not fail before Cloudways Pull.
 */
const base=process.env.EPAPER_PUBLIC_URL||'https://epaper.digiti.in';
const variants=[
  ['regular','/assets/home-halo.css?v=2.0.2.2'],
  ['cache-bypass','/assets/home-halo.css?epdi_asset_probe=transparent-v3']
];
for(const [label,path] of variants){
  try{
    const response=await fetch(new URL(path,base),{
      headers:{'Cache-Control':'no-cache','Pragma':'no-cache'},
      signal:AbortSignal.timeout(12000)
    });
    const body=response.ok?await response.text():'';
    const style=body.includes('HALO transparent hero treatment')?'transparent':
      body.includes('Homepage HALO progressive enhancement')?'legacy-dark':
      'unrecognized';
    console.log('LIVE ASSET '+label+':',JSON.stringify({
      status:response.status,
      theme:style,
      cacheControl:response.headers.get('cache-control'),
      cacheStatus:response.headers.get('cf-cache-status')||null,
      age:response.headers.get('age')||null,
      lastModified:response.headers.get('last-modified')||null
    }));
    if(style==='legacy-dark'){
      console.log('ACTION REQUIRED: origin or intermediary still serves the old dark HALO stylesheet.');
    }else if(style==='transparent'){
      console.log('TRANSPARENT CSS IS LIVE: a remaining dark hero points to stale browser/SW cache or old HTML.');
    }
  }catch(err){
    console.log('LIVE ASSET '+label+': unreachable from CI ('+String(err?.cause?.code||err?.name||'network-error')+'). No production-state claim.');
  }
}
