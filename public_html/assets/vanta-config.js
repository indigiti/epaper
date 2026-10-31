/* Pure, bounded Vanta renderer options shared by public hero and Admin customizer. */
(()=>{
  'use strict';
  const defaults=Object.freeze({
    backgroundColor:'#F4F8FF',baseColor:'#3877BD',secondaryColor:'#67C9C3',
    opacity:.28,size:1.15,speed:.30,amplitudeFactor:.75,xOffset:.12,
    yOffset:0,ringFactor:1.15,rotationFactor:.18,
    waveHeight:12,waveSpeed:.5,shininess:30,zoom:1,
    points:8,maxDistance:18,spacing:18,showDots:true,
    dotSpacing:35,showLines:true,
    skyColor:'#9DCCE0',cloudColor:'#DCE8F5',cloudShadowColor:'#94B6CD',sunColor:'#FFE4A7',
    fogHighlight:'#B3E9E3',fogMidtone:'#80B7DC',fogLowlight:'#B8D0EF',blurFactor:.6
  });
  const bounds={
    opacity:[.08,.45],size:[.5,2.5],speed:[.1,1.5],
    amplitudeFactor:[.2,2],xOffset:[-1,1],yOffset:[-1,1],
    ringFactor:[.3,2],rotationFactor:[0,2],
    waveHeight:[2,30],waveSpeed:[.1,1.5],shininess:[0,100],
    zoom:[.55,1.6],points:[4,16],maxDistance:[8,35],
    spacing:[12,30],dotSpacing:[18,60],blurFactor:[.2,1.5]
  };
  const allowed=['HALO','WAVES','NET','DOTS','RINGS','CLOUDS','CELLS','FOG'];
  const clamp=(key,val)=>{
    const [min,max]=bounds[key];
    const num=Number(val);
    return Number.isFinite(num)?Math.min(max,Math.max(min,num)):defaults[key];
  };
  function sanitize(raw){
    const v={...defaults};
    if(!raw||typeof raw!=='object')return v;
    for(const key of ['backgroundColor','baseColor','secondaryColor','skyColor','cloudColor',
      'cloudShadowColor','sunColor','fogHighlight','fogMidtone','fogLowlight']){
      const value=String(raw[key]??'');
      if(/^#[0-9a-fA-F]{6}$/.test(value))v[key]=value.toUpperCase();
    }
    for(const key of Object.keys(bounds)){
      if(Object.hasOwn(raw,key))v[key]=clamp(key,raw[key]);
    }
    v.showDots=raw.showDots===undefined?defaults.showDots:raw.showDots===true;
    v.showLines=raw.showLines===undefined?defaults.showLines:raw.showLines===true;
    return v;
  }
  function color(raw){return parseInt(raw.slice(1),16);}
  function options(effect,raw){
    const name=String(effect).toUpperCase();
    if(!allowed.includes(name))return null;
    const v=sanitize(raw);
    const pixel=typeof window.devicePixelRatio==='number'?window.devicePixelRatio:1;
    // Bigger Vanta scale means lower shader render resolution.
    const base={
      mouseControls:false,touchControls:false,gyroControls:false,
      minWidth:240,minHeight:200,
      scale:Math.max(1.45,Math.min(2.6,pixel*1.2)),
      scaleMobile:2.0
    };
    switch(name){
      case 'HALO':
        return {...base,
          backgroundColor:color(v.backgroundColor),
          baseColor:color(v.baseColor),color2:color(v.secondaryColor),
          size:v.size,speed:v.speed,amplitudeFactor:v.amplitudeFactor,
          xOffset:v.xOffset,yOffset:v.yOffset,
          ringFactor:v.ringFactor,rotationFactor:v.rotationFactor
        };
      case 'WAVES':
        return {...base,color:color(v.baseColor),
          backgroundColor:color(v.backgroundColor),
          waveHeight:v.waveHeight,waveSpeed:v.waveSpeed,
          shininess:v.shininess,zoom:v.zoom};
      case 'NET':
        return {...base,color:color(v.baseColor),
          backgroundColor:color(v.backgroundColor),points:v.points,
          maxDistance:v.maxDistance,spacing:v.spacing,showDots:v.showDots};
      case 'DOTS':
        return {...base,color:color(v.baseColor),color2:color(v.secondaryColor),
          backgroundColor:color(v.backgroundColor),
          size:v.size*3,spacing:v.dotSpacing,showLines:v.showLines};
      case 'RINGS':
        return {...base,backgroundColor:color(v.backgroundColor),
          color:color(v.baseColor)};
      case 'CLOUDS':
        return {...base,
          // Clouds' shader is relatively costly; keep it capped below 1x DPR.
          scale:Math.max(2,Math.min(3,(pixel||1)*1.6)),
          backgroundColor:color(v.backgroundColor),
          skyColor:color(v.skyColor),cloudColor:color(v.cloudColor),
          cloudShadowColor:color(v.cloudShadowColor),
          sunColor:color(v.sunColor),sunGlareColor:color(v.sunColor),
          sunlightColor:color(v.sunColor),speed:v.speed};
      case 'CELLS':
        return {...base,
          backgroundColor:color(v.backgroundColor),
          color1:color(v.baseColor),color2:color(v.secondaryColor),
          amplitudeFactor:v.amplitudeFactor,ringFactor:v.ringFactor,
          rotationFactor:v.rotationFactor,size:v.size,speed:v.speed};
      case 'FOG':
        return {...base,
          baseColor:color(v.baseColor),
          highlightColor:color(v.fogHighlight),
          midtoneColor:color(v.fogMidtone),
          lowlightColor:color(v.fogLowlight),
          blurFactor:v.blurFactor,zoom:v.zoom,speed:v.speed};
    }
    return null;
  }
  window.EpaperVantaConfig=Object.freeze({
    options,sanitize,
    allowed:Object.freeze(allowed),
    defaults
  });
})();
