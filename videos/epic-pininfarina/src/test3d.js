const {chromium}=require('playwright'); const serve=require('./serve'); const path=require('path'); const fs=require('fs');
(async()=>{
  const srv=await serve(8765); const b=await chromium.launch({args:['--enable-unsafe-swiftshader']}); const pg=await b.newPage({viewport:{width:1080,height:1920}});
  pg.on('pageerror',e=>console.error('PAGEERROR',e.message)); pg.on('console',m=>{if(m.type()==='error')console.error('CONSOLE',m.text())});
  let t0=Date.now(); await pg.goto('http://127.0.0.1:8765/test3d.html'); await pg.evaluate(()=>window.ready); console.log('init',((Date.now()-t0)/1000).toFixed(1),'s');
  fs.mkdirSync(path.join(__dirname,'build/t3d'),{recursive:true});
  const shots=(process.argv[2]||'orbit,enter,pool').split(','); const ps=(process.argv[3]||'0,0.35,0.7,1').split(',').map(Number);
  for(const s of shots) for(const p of ps){ t0=Date.now(); await pg.evaluate(([s,p])=>r3(s,p,p*8),[s,p]); await pg.screenshot({path:path.join(__dirname,`build/t3d/${s}_${p}.png`)}); console.log(s,p,((Date.now()-t0)/1000).toFixed(2),'s'); }
  await b.close(); srv.close();
})().catch(e=>{console.error(e);process.exit(1)});
