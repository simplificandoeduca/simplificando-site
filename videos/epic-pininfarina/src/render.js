const fs=require('fs'),path=require('path'),{spawn}=require('child_process');
const {chromium}=require('playwright');
const SP=__dirname;
function grainURI(){ // 256x256 noise PNG via a tiny PNG encoder
  const zlib=require('zlib'); const W=256,H=256; const raw=Buffer.alloc((W*3+1)*H); let s=12345;
  for(let y=0;y<H;y++){raw[y*(W*3+1)]=0; for(let x=0;x<W;x++){s=(s*1103515245+12345)&0x7fffffff; const v=128+((s>>8)%96)-48; const o=y*(W*3+1)+1+x*3; raw[o]=raw[o+1]=raw[o+2]=v;}}
  const crc=b=>{let c=~0;for(const x of b){c^=x;for(let k=0;k<8;k++)c=c&1?(c>>>1)^0xEDB88320:c>>>1}return ~c>>>0};
  const chunk=(t,d)=>{const l=Buffer.alloc(4);l.writeUInt32BE(d.length);const td=Buffer.concat([Buffer.from(t),d]);const c=Buffer.alloc(4);c.writeUInt32BE(crc(td));return Buffer.concat([l,td,c])};
  const ihdr=Buffer.alloc(13);ihdr.writeUInt32BE(W,0);ihdr.writeUInt32BE(H,4);ihdr[8]=8;ihdr[9]=2;
  const png=Buffer.concat([Buffer.from([137,80,78,71,13,10,26,10]),chunk('IHDR',ihdr),chunk('IDAT',zlib.deflateSync(raw)),chunk('IEND',Buffer.alloc(0))]);
  return 'data:image/png;base64,'+png.toString('base64');
}
(async()=>{
  const mode=process.argv[2]||'preview';
  const tl=fs.readFileSync(path.join(SP,'build/timeline.json'),'utf8');
  const html=fs.readFileSync(path.join(SP,process.env.SCENE||'scene.html'),'utf8').replace('__TIMELINE__',tl).replace('__GRAIN__',grainURI());
  fs.writeFileSync(path.join(SP,'build/scene.built.html'),html);
  const browser=await chromium.launch(); const page=await browser.newPage({viewport:{width:1080,height:1920},deviceScaleFactor:1});
  page.on('pageerror',e=>console.error('PAGEERROR',e.message));
  await page.goto('file://'+path.join(SP,'build/scene.built.html')); await page.evaluate(()=>document.fonts.ready);
  const TOTAL=await page.evaluate(()=>window.TOTAL);
  if(mode==='preview'){
    const times=process.argv.slice(3).map(Number); fs.mkdirSync(path.join(SP,'build/prev'),{recursive:true});
    for(const t of times){ await page.evaluate(t=>render(t),t); await page.screenshot({path:path.join(SP,`build/prev/f_${t.toFixed(1)}.png`),type:'png'}); }
    console.log('preview done',times.length);
  } else {
    const FPS=30, n=Math.ceil(TOTAL*FPS); const out=path.join(SP,'build/video.mp4');
    const ff=spawn('ffmpeg',['-y','-loglevel','error','-f','image2pipe','-framerate',String(FPS),'-c:v','mjpeg','-i','-','-c:v','libx264','-preset','medium','-crf','18','-pix_fmt','yuv420p','-movflags','+faststart',out],{stdio:['pipe','inherit','inherit']});
    const t0=Date.now();
    for(let i=0;i<n;i++){ const t=i/FPS; await page.evaluate(t=>render(t),t); const buf=await page.screenshot({type:'jpeg',quality:92});
      if(!ff.stdin.write(buf)) await new Promise(r=>ff.stdin.once('drain',r));
      if(i%300===0) console.log(`frame ${i}/${n} ${(t).toFixed(1)}s  elapsed ${((Date.now()-t0)/1000).toFixed(0)}s`); }
    ff.stdin.end(); await new Promise(r=>ff.on('close',r)); console.log('video done',n,'frames');
  }
  await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
