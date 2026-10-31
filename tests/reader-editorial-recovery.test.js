'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const code=fs.readFileSync(path.join(__dirname,'../public_html/assets/reader-editorial-clips.js'),'utf8');
const GUID='12345678-1234-4123-8123-123456789abc';
async function scenario(respond){
 const timers=[],listeners={};
 let requests=0;
 const added=[];
 const doc={dataset:{},isConnected:true,querySelectorAll:()=>[],appendChild:node=>added.push(node)};
 const root={dataset:{pageGuid:GUID,pageNumber:'1'},querySelector:s=>s==='[data-document]'?doc:null,
  querySelectorAll:s=>s==='[data-editorial-failed="1"]'&&doc.dataset.editorialFailed==='1'?[doc]:[]};
 const createElement=()=>({dataset:{},style:{},children:[],setAttribute(){},addEventListener(){},appendChild(c){this.children.push(c)}});
 const window={addEventListener:(type,fn)=>listeners[type]=fn};
 vm.runInNewContext(code,{window,document:{getElementById:()=>root,createElement},
  fetch:()=>respond(++requests),MutationObserver:class{observe(){}},setTimeout:fn=>{timers.push(fn);return timers.length}},{filename:'reader-editorial-clips.js'});
 const settle=async()=>{for(let i=0;i<15;i++)await Promise.resolve()};
 await settle();
 return {window,doc,added,timers,listeners,settle,get requests(){return requests},
  async advance(){assert.ok(timers.length,'expected retry');timers.shift()();await settle()}};
}
(async()=>{
 const s=await scenario(n=>n===1?Promise.reject(new Error('network')):Promise.resolve({ok:true,json:async()=>({ok:true,page_guid:GUID,marks:[]})}));
 assert.equal(s.requests,1);assert.notEqual(s.doc.dataset.editorialLoaded,'1');
 await s.advance();assert.equal(s.requests,2);assert.equal(s.doc.dataset.editorialLoaded,'1');
 assert.equal(s.timers.length,0);
 await s.window.EpdiEditorialClips.attach(s.doc,GUID,1);
 assert.equal(s.requests,2,'empty successful response must be cached');
 const b=await scenario(()=>Promise.resolve({ok:false,status:503,json:async()=>({})}));
 for(let i=0;i<3;i++)await b.advance();
 assert.equal(b.requests,4);assert.equal(b.timers.length,0);assert.equal(b.doc.dataset.editorialFailed,'1');
 b.listeners.online();await b.settle();assert.equal(b.requests,5,'online event should retry');
 const mark={mark_guid:'87654321-1234-4123-8123-123456789abc',title:'Headline',x:.9,y:.005,w:.05,h:.06};
 const t=await scenario(()=>Promise.resolve({ok:true,json:async()=>({ok:true,page_guid:GUID,marks:[mark]})}));
 assert.equal(t.added.length,1);assert.equal(t.added[0].children[0].dataset.tooltipBelow,'1');
 assert.equal(t.added[0].children[0].dataset.tooltipAlign,'end');
 console.log('Reader bounded retries, recovery, empty results, tooltip placement: OK');
})().catch(e=>{console.error(e);process.exitCode=1});
