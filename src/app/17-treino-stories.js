/* ---------- story do fim do treino: cronômetro e cupom, desenhados em canvas 1080×1920 ---------- */
const STC = {dk:'#0F1712', cr:'#F6F5EF', rd:'#FF4646', or:'#FF9A3C', li:'#A98BFF', lm:'#C6F04A'};
const ST_CORES = [['vermelho','Vermelho',STC.rd],['laranja','Laranja',STC.or],['lilas','Lilás',STC.li],['verde','Verde',STC.lm],['preto','Preto',STC.dk],['branco','Branco','#FFFFFF']];
const ST_RING = [[0,90,STC.rd],[90,180,STC.li],[180,270,STC.lm],[270,360,STC.or]];
const ST_TEMA = {
  vermelho:{bg:STC.rd,tx:STC.cr,eb:STC.cr,dot:STC.dk,ft:'rgba(246,245,239,.85)',cards:[[STC.cr,STC.dk,'#5B625D'],[STC.dk,STC.cr,'#9AA29B'],[STC.lm,STC.dk,'#2F3A12']],pc:STC.dk,cap:STC.dk,btn:STC.dk},
  laranja:{bg:STC.or,tx:STC.dk,eb:STC.dk,dot:STC.cr,ft:'rgba(15,23,18,.7)',cards:[[STC.rd,STC.cr,'#3A0D0D'],[STC.dk,STC.cr,'#9AA29B'],[STC.lm,STC.dk,'#2F3A12']],pc:STC.dk,cap:STC.dk,btn:STC.rd},
  lilas:{bg:STC.li,tx:STC.dk,eb:STC.dk,dot:STC.cr,ft:'rgba(15,23,18,.7)',cards:[[STC.rd,STC.cr,'#3A0D0D'],[STC.dk,STC.cr,'#9AA29B'],[STC.lm,STC.dk,'#2F3A12']],pc:STC.dk,cap:STC.dk,btn:STC.rd},
  verde:{bg:STC.lm,tx:STC.dk,eb:STC.dk,dot:STC.rd,ft:'rgba(15,23,18,.7)',cards:[[STC.rd,STC.cr,'#3A0D0D'],[STC.dk,STC.cr,'#9AA29B'],[STC.li,STC.dk,'#2B1F5C']],pc:STC.dk,cap:STC.dk,btn:STC.rd},
  preto:{bg:STC.dk,tx:STC.cr,eb:STC.lm,dot:STC.rd,ft:'#6E776F',cards:[[STC.rd,STC.cr,'#2A0B0B'],['#1C2620',STC.cr,'#9AA29B'],[STC.lm,STC.dk,'#2F3A12']],pc:'#2E3A33',cap:STC.lm,btn:STC.rd},
  branco:{bg:STC.cr,tx:STC.dk,eb:STC.dk,dot:STC.rd,ft:'rgba(15,23,18,.6)',cards:[[STC.rd,STC.cr,'#3A0D0D'],[STC.dk,STC.cr,'#9AA29B'],[STC.lm,STC.dk,'#2F3A12']],pc:STC.dk,cap:STC.dk,btn:STC.rd}
};
const ST_MES = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
const ST_SANS = 'Inter, system-ui, sans-serif', ST_MONO = '"JetBrains Mono", ui-monospace, Menlo, monospace';
let stLogo = null;
function stFontes(){
  if(!document.getElementById('st-fonts')){ const l = document.createElement('link'); l.id = 'st-fonts'; l.rel = 'stylesheet'; l.href = 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@600;800&display=swap'; document.head.appendChild(l); }
  const logo = stLogo ? Promise.resolve() : new Promise(ok => { const im = new Image(); im.onload = () => { stLogo = im; ok(); }; im.onerror = () => ok(); im.src = 'icon-512.png'; });
  const fs = document.fonts && document.fonts.load ? Promise.all(['900 110px Inter','800 34px Inter','700 32px Inter','600 30px Inter','800 128px "JetBrains Mono"','600 36px "JetBrains Mono"'].map(f => document.fonts.load(f).catch(() => null))) : Promise.resolve();
  return Promise.race([Promise.all([logo, fs]), new Promise(ok => setTimeout(ok, 3500))]);
}
function stDados(h){
  const p = PLAN[h.t] || {nome:'Treino'}, d = parse(h.date), sec = h.sec!=null ? h.sec : h.min*60;
  const prs = new Set((h.prd||[]).map(x => x.id).concat(h.prs||[]));
  const cargas = (h.ex||[]).map(x => ({n:x.n, kg:Math.max(0, ...(x.s||[]).map(s => s[0])), pr:prs.has(x.id)})).filter(x => x.kg>0).sort((a,b) => b.kg-a.kg).slice(0,7);
  return {t:h.t, nome:p.nome||'Treino', sec, min:h.min, sets:h.sets, vol:h.vol||0, prs:prs.size, dia:d.getDate(), mes:d.getMonth(), d,
    hr:h.hr||'', gym:h.gym||'', cargas};
}
function stLs(c,v){ if('letterSpacing' in c) c.letterSpacing = v+'px'; }
function stTxt(c,s,x,y,font,cor,al,ls){ c.font = font; c.fillStyle = cor; c.textAlign = al||'left'; stLs(c, ls||0); c.fillText(s,x,y); stLs(c,0); }
function stRR(c,x,y,w,h,r){ c.beginPath(); if(c.roundRect) c.roundRect(x,y,w,h,r); else { c.moveTo(x+r,y); c.arcTo(x+w,y,x+w,y+h,r); c.arcTo(x+w,y+h,x,y+h,r); c.arcTo(x,y+h,x,y,r); c.arcTo(x,y,x+w,y,r); c.closePath(); } }
function stFit(c,s,font,size,maxW,peso){ let z = size; c.font = peso+' '+z+'px '+font; while(z>40 && c.measureText(s).width>maxW){ z -= 4; c.font = peso+' '+z+'px '+font; } return z; }
function stCorte(c,s,maxW){ if(c.measureText(s).width<=maxW) return s; while(s.length>1 && c.measureText(s+'…').width>maxW) s = s.slice(0,-1); return s.trim()+'…'; }
function stLogoDraw(c,x,y,n){ c.save(); stRR(c,x,y,n,n,n*.26); c.clip(); if(stLogo) c.drawImage(stLogo,x,y,n,n); else { c.fillStyle = STC.dk; c.fillRect(x,y,n,n); } c.restore(); }
function stArc(c,cx,cy,r,a0,a1){ const k = Math.PI/180; c.beginPath(); c.arc(cx,cy,r,(a0-90)*k,(a1-90)*k); }
function stCrono(c,D,T){
  const W = 1080, H = 1920;
  c.fillStyle = T.bg; c.fillRect(0,0,W,H);
  stLogoDraw(c,90,110,110);
  stTxt(c,'simplificando',226,183,'900 52px '+ST_SANS,T.tx,'left',-2);
  c.font = '900 52px '+ST_SANS; stLs(c,-2); const lw = c.measureText('simplificando').width; stLs(c,0);
  stTxt(c,'.',226+lw,183,'900 52px '+ST_SANS,T.dot);
  stTxt(c,('Treino '+D.t+' · '+D.nome).toUpperCase(),540,322,'800 34px '+ST_SANS,T.eb,'center',6);
  // cronômetro (coordenadas do desenho 800×880, deslocado para x 140, y 368)
  c.save(); c.translate(140,368);
  const cx = 400, cy = 480, R = 340;
  c.fillStyle = T.pc; stRR(c,355,30,90,80,16); c.fill();
  c.fillStyle = T.cap; stRR(c,320,6,160,44,18); c.fill();
  [[123,153,-45,T.pc],[677,153,45,T.btn]].forEach(([x,y,a,cor]) => { c.save(); c.translate(x,y); c.rotate(a*Math.PI/180); c.fillStyle = cor; stRR(c,-35,-23,70,46,14); c.fill(); c.restore(); });
  c.fillStyle = STC.dk; c.beginPath(); c.arc(cx,cy,R+30,0,Math.PI*2); c.fill();
  c.lineWidth = 30; ST_RING.forEach(([a0,a1,cor]) => { c.strokeStyle = cor; stArc(c,cx,cy,R,a0,a1); c.stroke(); });
  c.fillStyle = STC.dk; c.beginPath(); c.arc(cx,cy,R-24,0,Math.PI*2); c.fill();
  c.strokeStyle = '#2E3A33'; c.lineCap = 'round';
  for(let i=0;i<60;i++){ const a = (i*6-90)*Math.PI/180, l = i%5 ? 16 : 34, r = R-44; c.lineWidth = i%5 ? 4 : 7; c.beginPath(); c.moveTo(cx+r*Math.cos(a),cy+r*Math.sin(a)); c.lineTo(cx+(r-l)*Math.cos(a),cy+(r-l)*Math.sin(a)); c.stroke(); }
  c.lineCap = 'butt';
  stTxt(c,'● TEMPO DE TREINO',cx,cy-90,'800 34px '+ST_SANS,STC.lm,'center',6);
  c.fillStyle = '#1C2620'; stRR(c,cx-250,cy-50,500,170,30); c.fill();
  const mm = Math.floor(D.sec/60), ss = D.sec%60, tt = String(mm).padStart(2,'0')+':'+String(ss).padStart(2,'0');
  c.font = '800 128px '+ST_MONO; stLs(c,-4); const tz = Math.min(128, Math.floor(128*460/Math.max(1,c.measureText(tt).width))); stLs(c,0);
  stTxt(c,tt,cx,cy+74,'800 '+tz+'px '+ST_MONO,STC.cr,'center',-4);
  stTxt(c,'min : seg',cx,cy+190,'700 36px '+ST_SANS,'#9AA29B','center');
  c.restore();
  // título
  const tit = D.nome+' em dia', z = stFit(c,tit+'.',ST_SANS,110,900,900);
  c.font = '900 '+z+'px '+ST_SANS; stLs(c,-4); const tw0 = c.measureText(tit).width, dw = c.measureText('.').width; stLs(c,0);
  const x0 = 540-(tw0+dw)/2;
  stTxt(c,tit,x0,1378,'900 '+z+'px '+ST_SANS,T.tx,'left',-4);
  stTxt(c,'.',x0+tw0,1378,'900 '+z+'px '+ST_SANS,T.dot);
  // caixinhas: séries, data, recordes
  const cards = [[String(D.sets),'série'+(D.sets===1?'':'s')],[String(D.dia).padStart(2,'0'),'de '+ST_MES[D.mes]],[String(D.prs),'recorde'+(D.prs===1?'':'s')]];
  cards.forEach(([v,l],i) => { const [bg,fg,sb] = T.cards[i], x = 90+i*308;
    c.fillStyle = bg; stRR(c,x,1525,284,200,36); c.fill();
    stTxt(c,v,x+34,1633,'900 80px '+ST_SANS,fg,'left',-4);
    c.font = '600 30px '+ST_SANS; stTxt(c,stCorte(c,l,224),x+34,1680,'600 30px '+ST_SANS,sb); });
  stTxt(c,'simplificandoeduca.com',90,1800,'700 32px '+ST_SANS,T.ft);
  stTxt(c,'#simplificando',990,1800,'700 32px '+ST_SANS,T.ft,'right');
}
function stCupom(c,D,cor){
  const W = 1080, H = 1920, bg = cor==='branco' ? '#FFFFFF' : ST_TEMA[cor].bg, paper = cor==='branco' ? '#F1F0EA' : STC.cr;
  c.fillStyle = bg; c.fillRect(0,0,W,H);
  const X = 90, PW = 900, P = 70, rows = D.cargas.length || 1;
  const hm = 64+120+44+182+18+32+ (36+4+36) + rows*67 + (36+4+36) + 120 + 2*67 + 40 + 28, G = 24, hs = 28+44+110+26+40+56, h = hm+G+hs;
  const Y = Math.max(40, Math.round((H-h)/2)), Z = 28, ym = Y+hm, ys = ym-Z+G;
  // cupom com o pé picotado
  c.fillStyle = paper; c.beginPath(); c.moveTo(X,Y+36); c.arcTo(X,Y,X+36,Y,36); c.lineTo(X+PW-36,Y); c.arcTo(X+PW,Y,X+PW,Y+36,36);
  c.lineTo(X+PW,ym-Z); for(let x=PW-30;x>=0;x-=30){ c.lineTo(X+x, (PW-x)/30%2 ? ym : ym-Z); } c.closePath(); c.fill();
  // canhoto destacado: os dentes encaixam no picote do cupom, com um espaço entre os dois
  c.beginPath(); c.moveTo(X,ys); for(let x=30;x<=PW;x+=30){ c.lineTo(X+x, (PW-x)/30%2 ? ys+Z : ys); }
  c.lineTo(X+PW,ys+hs-36); c.arcTo(X+PW,ys+hs,X+PW-36,ys+hs,36); c.lineTo(X+36,ys+hs); c.arcTo(X,ys+hs,X,ys+hs-36,36); c.closePath(); c.fill();
  let y = Y+64;
  stLogoDraw(c,X+P,y,120);
  const dt = String(D.dia).padStart(2,'0')+'/'+String(D.mes+1).padStart(2,'0')+'/'+D.d.getFullYear()+(D.hr?' · '+D.hr:'');
  const hd = ['CUPOM DO TREINO', dt].concat(D.gym ? [D.gym.toUpperCase()] : []);
  c.font = '600 30px '+ST_MONO; hd.forEach((s,i) => stTxt(c,stCorte(c,s,560),X+PW-P,y+34+i*45,'600 30px '+ST_MONO,'#5B625D','right'));
  y += 120+44;
  c.font = '900 96px '+ST_SANS; stLs(c,-4); const l1 = 'Treino '+D.t, w1 = c.measureText(l1).width; stLs(c,0);
  stTxt(c,l1,X+P,y+76,'900 96px '+ST_SANS,STC.dk,'left',-4); stTxt(c,'.',X+P+w1,y+76,'900 96px '+ST_SANS,STC.rd);
  const z2 = stFit(c,D.nome,ST_SANS,96,PW-2*P,900); stTxt(c,D.nome,X+P,y+76+91,'900 '+z2+'px '+ST_SANS,STC.dk,'left',-4);
  y += 182+18;
  stTxt(c,'MAIOR CARGA DE CADA EXERCÍCIO',X+P,y+26,'600 26px '+ST_MONO,'#86928A','left',2);
  y += 32;
  const linha = () => { y += 36; c.strokeStyle = '#C9CDC6'; c.lineWidth = 4; c.setLineDash([12,12]); c.beginPath(); c.moveTo(X+P,y+2); c.lineTo(X+PW-P,y+2); c.stroke(); c.setLineDash([]); y += 4+36; };
  const kg = v => fmtKg(v)+' kg';
  const row = (a,b,pr) => {
    const cr = pr ? '#E8A500' : STC.dk, fw = pr ? '800' : '600';
    if(pr){ c.save(); c.shadowColor = 'rgba(255,190,0,.55)'; c.shadowBlur = 14; }
    stTxt(c,b,X+PW-P,y+46,'800 36px '+ST_MONO,cr,'right'); c.font = '800 36px '+ST_MONO; const bw = c.measureText(b).width;
    c.font = fw+' 36px '+ST_MONO; stTxt(c,stCorte(c,a,PW-2*P-bw-30),X+P,y+46,fw+' 36px '+ST_MONO,cr);
    if(pr) c.restore();
    y += 67; };
  linha();
  if(D.cargas.length) D.cargas.forEach(x => row(x.n, kg(x.kg)+(x.pr?' ★':''), x.pr)); else row('Séries feitas', String(D.sets));
  linha();
  stTxt(c,'TOTAL',X+P,y+92,'900 44px '+ST_MONO,STC.dk);
  const tot = fmtN(D.vol)+' kg'; c.font = '900 110px '+ST_MONO; stLs(c,-5); const tz = Math.min(110, Math.floor(110*560/Math.max(1,c.measureText(tot).width))); stLs(c,0);
  stTxt(c,tot,X+PW-P,y+92,'900 '+tz+'px '+ST_MONO,STC.dk,'right',-5);
  y += 120;
  row('Tempo de treino', D.min+' min'); row('Recordes batidos', D.prs+(D.prs?' ★':''), D.prs>0);
  y = ys+Z+44; c.fillStyle = STC.dk;
  for(let x=0;x<PW-2*P;x+=40) [[0,6],[12,16],[26,34]].forEach(([a,b]) => { const w = Math.min(b, PW-2*P-x) - a; if(w>0) c.fillRect(X+P+x+a,y,w,110); });
  y += 110+26;
  stTxt(c,'simplificandoeduca.com · pago com suor',X+PW/2,y+32,'700 32px '+ST_MONO,'#5B625D','center');
}
function stFotoBox(s){ return {x:s.sx-30, y:s.sy-130, w:720, h:560}; }
function stFoto(c,D,s){
  const W = 1080, H = 1920;
  if(s.ph){
    const p = s.ph, k = Math.max(W/p.width, H/p.height), dw = p.width*k, dh = p.height*k;
    const mx = (dw-W)/2, my = (dh-H)/2; s.px = Math.max(-mx, Math.min(mx, s.px||0)); s.py = Math.max(-my, Math.min(my, s.py||0));
    c.fillStyle = '#000'; c.fillRect(0,0,W,H); c.drawImage(p, (W-dw)/2+s.px, (H-dh)/2+s.py, dw, dh);
  } else {
    const g = c.createLinearGradient(0,0,0,H); g.addColorStop(0,'#2A332D'); g.addColorStop(1,'#0F1712'); c.fillStyle = g; c.fillRect(0,0,W,H);
    c.strokeStyle = 'rgba(246,245,239,.25)'; c.lineWidth = 6; c.setLineDash([24,18]); stRR(c,70,70,W-140,H-140,48); c.stroke(); c.setLineDash([]);
    stTxt(c,'📷',W/2,640,'160px '+ST_SANS,'#fff','center');
    stTxt(c,'Tire uma foto',W/2,800,'900 76px '+ST_SANS,STC.cr,'center',-3);
    stTxt(c,'ou escolha da galeria',W/2,890,'700 48px '+ST_SANS,'#9AA29B','center');
  }
  const sh = c.createLinearGradient(0,H*.55,0,H); sh.addColorStop(0,'rgba(0,0,0,0)'); sh.addColorStop(1,'rgba(0,0,0,.35)'); c.fillStyle = sh; c.fillRect(0,H*.55,W,H*.45);
  const cor = s.cor==='branco' ? STC.cr : ST_TEMA[s.cor].bg, claro = s.cor==='preto' || s.cor==='vermelho', fg = claro ? STC.cr : STC.dk, sub = claro ? 'rgba(246,245,239,.75)' : '#4A524C';
  const x = s.sx, y = s.sy, w = 640, h = 300;
  // recordes
  if(D.prs){ c.save(); c.translate(x+450, y-95); c.rotate(6*Math.PI/180); c.shadowColor = 'rgba(0,0,0,.35)'; c.shadowBlur = 30; c.shadowOffsetY = 12;
    const t = '🔥 '+D.prs+' recorde'+(D.prs===1?'':'s'); c.font = '900 44px '+ST_SANS; const tw = c.measureText(t).width+72;
    c.fillStyle = s.cor==='vermelho' ? STC.dk : STC.rd; stRR(c,-tw/2,-46,tw,92,46); c.fill(); c.shadowColor = 'transparent';
    stTxt(c,t,0,16,'900 44px '+ST_SANS,'#fff','center'); c.restore(); }
  // cartão principal
  c.save(); c.translate(x+w/2, y+h/2); c.rotate(-5*Math.PI/180); c.shadowColor = 'rgba(0,0,0,.4)'; c.shadowBlur = 50; c.shadowOffsetY = 20;
  c.fillStyle = cor; stRR(c,-w/2,-h/2,w,h,44); c.fill(); c.shadowColor = 'transparent';
  stLogoDraw(c,-w/2+44,-h/2+40,64);
  c.font = '900 32px '+ST_SANS; stLs(c,-1); const nw = c.measureText('simplificando').width; stLs(c,0);
  stTxt(c,'simplificando',-w/2+124,-h/2+84,'900 32px '+ST_SANS,fg,'left',-1); stTxt(c,'.',-w/2+124+nw,-h/2+84,'900 32px '+ST_SANS,claro?STC.lm:STC.rd);
  stTxt(c,'· Treino '+D.t,-w/2+124+nw+20,-h/2+84,'800 30px '+ST_SANS,sub);
  const tt = String(Math.floor(D.sec/60)).padStart(2,'0')+':'+String(D.sec%60).padStart(2,'0');
  stTxt(c,tt,-w/2+40,-h/2+210,'800 116px '+ST_MONO,fg,'left',-5);
  c.font = '700 32px '+ST_SANS; stTxt(c,stCorte(c,D.sets+' séries · '+D.nome.toLowerCase()+' · '+String(D.dia).padStart(2,'0')+' '+ST_MES[D.mes].slice(0,3),w-84),-w/2+44,-h/2+262,'700 32px '+ST_SANS,sub);
  c.restore();
  // hashtag
  c.save(); c.translate(x+470, y+h+40); c.rotate(-3*Math.PI/180); c.fillStyle = s.cor==='verde' ? STC.dk : STC.lm; stRR(c,-150,-38,300,76,22); c.fill();
  stTxt(c,'#simplificando',0,12,'900 34px '+ST_SANS,s.cor==='verde'?STC.lm:STC.dk,'center'); c.restore();
}
let stBlobT = 0, stOk = false;
function stBlob(s){
  clearTimeout(stBlobT); s.blob = null; $sheet.querySelectorAll('[data-st-go]').forEach(b => b.disabled = true);
  stBlobT = setTimeout(() => { const cv = $sheet.querySelector('#st-cv'); if(!cv || ui.sheet!==s) return;
    cv.toBlob(b => { if(ui.sheet!==s || !b) return; if(s.url) URL.revokeObjectURL(s.url); s.blob = b; s.url = URL.createObjectURL(b); $sheet.querySelectorAll('[data-st-go]').forEach(x => x.disabled = false); }, 'image/png'); }, 220);
}
function stDraw(s){
  const cv = $sheet.querySelector('#st-cv'); if(!cv) return;
  const h = S.history[s.i]; if(!h) return;
  const c = cv.getContext('2d'), D = stDados(h);
  c.setTransform(1,0,0,1,0,0); c.clearRect(0,0,1080,1920);
  if(s.m==='cupom') stCupom(c,D,s.cor); else if(s.m==='foto') stFoto(c,D,s); else stCrono(c,D,ST_TEMA[s.cor]);
  cv.classList.add('on');
}
function stBind(s){
  $sheet.querySelectorAll('[data-st-file]').forEach(inp => inp.onchange = () => {
    const f = inp.files && inp.files[0]; inp.value = ''; if(!f) return;
    const u = URL.createObjectURL(f), im = new Image();
    im.onload = () => { const k = Math.min(1, 2200/Math.max(im.width, im.height)), p = document.createElement('canvas');
      p.width = Math.round(im.width*k); p.height = Math.round(im.height*k); p.getContext('2d').drawImage(im,0,0,p.width,p.height); URL.revokeObjectURL(u);
      s.ph = p; s.px = 0; s.py = 0; if(ui.sheet===s){ stDraw(s); stBlob(s); const t = $sheet.querySelector('.st-tip'); if(t) t.hidden = false; } };
    im.onerror = () => { URL.revokeObjectURL(u); toast('Não deu para abrir essa foto. Tente outra.'); };
    im.src = u;
  });
  const cv = $sheet.querySelector('#st-cv'); if(!cv || s.m!=='foto') return;
  let drag = null, raf = 0;
  cv.addEventListener('pointerdown', ev => {
    const r = cv.getBoundingClientRect(), k = 1080/r.width, x = (ev.clientX-r.left)*k, y = (ev.clientY-r.top)*k, b = stFotoBox(s);
    const onStk = x>=b.x && x<=b.x+b.w && y>=b.y && y<=b.y+b.h;
    if(!onStk && !s.ph) return;
    drag = {k, x0:ev.clientX, y0:ev.clientY, stk:onStk, a:onStk ? s.sx : s.px, b:onStk ? s.sy : s.py};
    try { cv.setPointerCapture(ev.pointerId); } catch(e){ /* sem captura */ } ev.preventDefault();
  });
  cv.addEventListener('pointermove', ev => {
    if(!drag) return; const dx = (ev.clientX-drag.x0)*drag.k, dy = (ev.clientY-drag.y0)*drag.k;
    if(drag.stk){ s.sx = Math.max(-20, Math.min(1080-660, drag.a+dx)); s.sy = Math.max(150, Math.min(1920-420, drag.b+dy)); } else { s.px = drag.a+dx; s.py = drag.b+dy; }
    if(!raf) raf = requestAnimationFrame(() => { raf = 0; stDraw(s); });
  });
  const end = () => { if(!drag) return; drag = null; stDraw(s); stBlob(s); };
  cv.addEventListener('pointerup', end); cv.addEventListener('pointercancel', end);
}
async function stRender(){
  const s = ui.sheet; if(!s || s.k!=='story') return;
  if(!S.history[s.i]) return;
  if(!stOk){ await stFontes(); stOk = true; if(ui.sheet!==s) return; }
  stDraw(s); stBind(s); stBlob(s); stThumbs(s.i, s.cor);
}
const quem = () => isPersonal() ? { nome:ACCT.nome||'', tipo:'personal' } : { nome:S.nome||'', tipo:'autonomo' };
/* datas especiais: o story ganha um visual da data (a programação fica na aba Estatísticas do painel) */
function temaAtivo(){ return ''; }
// conta qual modelo de story a pessoa postou (uma vez por modelo, a cada vez que abre o compartilhar)
// registro do comprovante (story): 'abriu' a tela, 'baixar' e 'share' contam separados, uma vez cada por modelo a cada abertura
function stReg(s, acao, tema){ if(!FB || !s) return; const vid = s.m==='cbon' && s.tipo==='video', k = s.m+'|'+acao+(vid?'v':''), L = s.logged || (s.logged = {}); if(L[k]) return; L[k] = 1;
  FB.evento('storiesUso', Object.assign(quem(), { modelo:s.m, cor:s.cor||'', acao:acao+(vid && acao!=='abriu' ? '-video' : ''), tema, em:Date.now() })).then(r => {
    if(r==='permission-denied') errSend('Regras do Firebase desatualizadas: o registro dos comprovantes ('+(tema==='cardio'?'cardio':'treino')+') foi bloqueado. Cole o REGRAS-FIRESTORE.txt novo no Firebase.', 'storiesUso', 0, 0, ''); }); }
function stLog(s, acao){ stReg(s, acao, temaAtivo() || 'treino'); }
function stNome(s){ const h = S.history[s.i]; return 'simplificando-treino-'+(h ? h.t+'-'+h.date : 'hoje')+'.png'; }
function stBaixar(s){
  if(!s.blob) return;
  const a = document.createElement('a'); a.href = s.url; a.download = stNome(s); document.body.appendChild(a); a.click(); a.remove();
  toast('Imagem salva. Procure nos seus downloads ou na galeria.');
}
async function stShare(s){
  if(!s.blob) return;
  const f = new File([s.blob], stNome(s), {type:'image/png'});
  if(navigator.canShare && navigator.canShare({files:[f]})){
    try { await navigator.share({files:[f]}); } catch(e){ if(e && e.name!=='AbortError') stBaixar(s); }
  } else { stBaixar(s); toast('Seu navegador não abre o compartilhar. A imagem foi salva: poste pelo Instagram ou WhatsApp.'); }
}
function vStory(){
  const s = ui.sheet, h = S.history[s.i]; if(!h) return '';
  const foto = s.m==='foto';
  return `<div class="sheet-wrap" data-a="st-closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop('Treino '+h.t+' · '+dayLabel(h.date), 'Compartilhar no story<span class="dot" aria-hidden="true"></span>').replace('data-a="close"','data-a="st-close"')}
    <div class="stm" role="group" aria-label="Modelo da imagem">${ST_MODS.map(([m,n]) => `<button type="button" class="stm-c${s.m===m?' on':''}" aria-pressed="${s.m===m}" data-a="st-m" data-v="${m}"><span class="stp-th"><canvas data-st-th="${m}" width="135" height="240" aria-hidden="true"></canvas></span><b>${n}</b></button>`).join('')}</div>
    ${foto ? `<div class="row-btns st-foto"><label class="btn">${ic('camera')}Tirar foto<input type="file" accept="image/*" capture="user" data-st-file hidden></label><label class="btn">${ic('layers')}Galeria<input type="file" accept="image/*" data-st-file hidden></label></div>` : ''}
    <div class="st-cores" role="group" aria-label="${foto?'Cor da figurinha':'Cor do fundo'}">${ST_CORES.map(([k,n,c]) => `<button type="button" class="${s.cor===k?'on':''}" style="--c:${c}" aria-pressed="${s.cor===k}" aria-label="${n}" title="${n}" data-a="st-cor" data-v="${k}"></button>`).join('')}</div>
    <div class="st-prev"><canvas id="st-cv" width="1080" height="1920" role="img" aria-label="Prévia da imagem do treino para o story"${foto?' style="touch-action:none;cursor:grab"':''}></canvas></div>
    ${foto ? `<p class="foot0 st-tip"${s.ph?'':' hidden'}>Arraste a figurinha para mudar de lugar. Arraste a foto para ajustar o enquadramento. A foto fica só no seu celular.</p>` : ''}
    <button type="button" class="btn primary block" data-a="st-share" data-st-go disabled>${ic('share')}Compartilhar no Instagram ou WhatsApp</button>
    <button type="button" class="btn block" data-a="st-down" data-st-go disabled>${ic('dl')}Baixar imagem</button>
    ${igTag()}<p class="foot0">No celular, escolha Instagram (story) ou WhatsApp na lista que abrir.</p></div></div>`;
}
const ST_MODS = [['crono','Cronômetro','Seu tempo de treino'],['cupom','Cupom','Suas cargas do dia'],['foto','Foto','Sua foto com figurinhas']];
function stThumbs(i, cor){
  const h = S.history[i]; if(!h) return;
  const run = () => { const D = stDados(h);
    $sheet.querySelectorAll('canvas[data-st-th]').forEach(cv => { const c = cv.getContext('2d'), m = cv.dataset.stTh, k = cv.width/1080;
      c.setTransform(k,0,0,k,0,0); c.clearRect(0,0,1080,1920);
      if(m==='cupom') stCupom(c,D,cor); else if(m==='foto') stFoto(c,D,{cor, sx:70, sy:1280, px:0, py:0}); else stCrono(c,D,ST_TEMA[cor]);
      cv.classList.add('on'); }); };
  if(stOk) run(); else stFontes().then(() => { stOk = true; run(); });
}
/* convite para seguir o Instagram: depois do treino ou do cardio, no máximo 1 vez por semana; some para quem já segue */
const IG_USER = 'simplificando.educa', IG_URL = 'https://www.instagram.com/simplificando.educa/';
const IG_SVG = '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>';
function igPode(){ const g = S.ig || {}, t = today(); if(g.sigo || g.x===t || g.abriu===t) return false; return !g.visto || g.visto===t || diffDays(t, g.visto) >= 7; }
function igCard(pr){
  if(!igPode()) return '';
  const g = S.ig || (S.ig = {}); if(g.visto!==today()){ g.visto = today(); save(); }
  return `<div class="ig-c" role="region" aria-label="Instagram do Simplificando"><button type="button" class="ig-x" data-a="ig-x" aria-label="Agora não">${ic('x')}</button>
    <span class="ig-ic">${IG_SVG}</span><div class="ig-t"><b>${pr ? 'Bateu recorde 🔥' : 'Mandou bem ✅'} Siga @${IG_USER}</b><span>Toda semana tem dica de execução, de carga e de cardio.</span>
    <div class="ig-bt"><button type="button" class="btn small primary" data-a="ig-abrir">${IG_SVG}Seguir no Instagram</button><button type="button" class="btn small" data-a="ig-sigo">Já sigo</button></div></div></div>`;
}
const igTag = () => `<div class="ig-tag">${IG_SVG}<span>Marque <b>@${IG_USER}</b> no story</span><button type="button" class="btn small" data-a="ig-copiar">Copiar @</button></div>`;
function igSome(msg){ document.querySelectorAll('.ig-c').forEach(el => el.remove()); if(msg) toast(msg); }
function vStPick(){
  const s = ui.sheet, h = S.history[s.i]; if(!h) return '';
  const p = PLAN[h.t] || {nome:'Treino'};
  return `<div class="stp" role="dialog" aria-modal="true" aria-labelledby="stp-t" tabindex="-1">
    <button type="button" class="btn round stp-x${s.xOn?' on':''}" data-a="stp-x" aria-label="Agora não"${s.xOn?'':' tabindex="-1" aria-hidden="true"'}>${ic('x')}</button>
    <div class="stp-h"><p class="eyebrow">Treino ${h.t} · ${esc(p.nome)} · ${h.min} min</p><h2 id="stp-t">Treino concluído<span class="dot" aria-hidden="true"></span></h2><p>Agora mostra pra todo mundo. Escolha como quer postar no seu story:</p></div>
    <div class="stp-cards">${ST_MODS.map(([m,n,d],j) => `<button type="button" class="stp-c" data-a="stp-go" data-v="${m}" style="--d:${j*90}ms"><span class="stp-th"><canvas data-st-th="${m}" width="270" height="480" aria-hidden="true"></canvas>${m==='foto'?`<i>${ic('camera')}</i>`:''}</span><b>${n}</b><small>${d}</small></button>`).join('')}</div>
    <p class="stp-f">${ic('share')}Depois é só tocar em Instagram ou WhatsApp.</p>${igCard((h.prs||[]).length>0)}</div>`;
}
let stpT = 0;
function stPick(i){
  clearTimeout(stpT);
  ui.sheet = {k:'stpick', i, xOn:false}; renderSheet(true); stThumbs(i, PREF.stCor||'vermelho');
  const s = ui.sheet;
  stpT = setTimeout(() => { if(ui.sheet!==s) return; s.xOn = true; const x = $sheet.querySelector('.stp-x'); if(x){ x.classList.add('on'); x.removeAttribute('tabindex'); x.removeAttribute('aria-hidden'); } }, 5000);
}
function stOpen(i,m){
  const old = ui.sheet && ui.sheet.k==='story' ? ui.sheet : null;
  ui.sheet = {k:'story', i, m:m||PREF.stM||'crono', cor:PREF.stCor||'vermelho', sx:70, sy:1280, px:0, py:0, back:old ? old.back : i};
  renderSheet(true); stRender();
}
function stSet(k,v){ const s = ui.sheet; s[k] = v; s.blob = null; PREF[k==='m'?'stM':'stCor'] = v; savePref(); renderSheet(); stRender(); }

/* ---------- story do cardio: Boneco (foto ou vídeo), Bilhete, Semana e Pulso, em canvas 1080×1920 ---------- */
const CST_MODS = [['cbon','Boneco','O boneco em movimento'],['cbil','Bilhete','Do sofá para a meta'],['csem','Semana','Seus minutos da semana'],['cpul','Pulso','Batimento e tempo']];
const CST_COR0 = { cbon:'lilas', cbil:'preto', csem:'branco', cpul:'preto' };
// frase do Boneco: muda todo dia (a mesma o dia inteiro, na foto e no vídeo)
const CST_FRASES = ['Bora que é cardio','Cardio feito, mente leve','O coração agradece','Hoje teve suor','Fôlego em dia','Um passo de cada vez','Mais leve que ontem','Corpo em movimento','Constância vence','Missão cumprida','Hoje eu fui','Sem desculpa hoje','Suor que vale a pena','Disciplina em dia','Ritmo bom','A meta está mais perto','Mexer é viver','Energia renovada','Feito é melhor que perfeito','Pulso firme','Saúde em primeiro lugar','Cada minuto conta','O sofá perdeu','Foco no processo','Mais um pra conta','Cansaço bom','Bati o ponto no cardio','Hoje foi dia de cardio','O pulmão agradece','Devagar e sempre'];
const cFrase = D => { const t = parse(D.date); const n = Math.floor((Date.UTC(t.getFullYear(), t.getMonth(), t.getDate()))/864e5); return CST_FRASES[((n % CST_FRASES.length) + CST_FRASES.length) % CST_FRASES.length]; };
const CST_DE = { Esteira:'de esteira', Bicicleta:'de bike', Rua:'na rua', Escada:'de escada', 'Elíptico':'de elíptico', Outro:'de cardio' };
function cPal(cor){
  const P = {
    vermelho:{bg:STC.rd,tx:STC.cr,sub:'rgba(246,245,239,.82)',dot:STC.dk,acc:STC.lm,accTx:STC.dk,pan:STC.dk,panTx:STC.cr,paper:STC.cr,line:STC.lm,heart:STC.cr,mov:'rgba(15,23,18,.25)'},
    laranja:{bg:STC.or,tx:STC.dk,sub:'rgba(15,23,18,.7)',dot:STC.cr,acc:STC.rd,accTx:STC.cr,pan:STC.dk,panTx:STC.cr,paper:STC.cr,line:STC.dk,heart:STC.rd,mov:STC.or},
    lilas:{bg:STC.li,tx:STC.dk,sub:'rgba(15,23,18,.7)',dot:STC.cr,acc:STC.lm,accTx:STC.dk,pan:STC.dk,panTx:STC.cr,paper:STC.cr,line:STC.dk,heart:STC.rd,mov:STC.li},
    verde:{bg:STC.lm,tx:STC.dk,sub:'rgba(15,23,18,.7)',dot:STC.rd,acc:STC.rd,accTx:STC.cr,pan:STC.dk,panTx:STC.cr,paper:STC.cr,line:STC.dk,heart:STC.rd,mov:'#9BC52E'},
    preto:{bg:STC.dk,tx:STC.cr,sub:'#9AA29B',dot:STC.rd,acc:STC.lm,accTx:STC.dk,pan:'#1C2620',panTx:STC.cr,paper:STC.cr,line:STC.lm,heart:STC.rd,mov:STC.li},
    branco:{bg:STC.cr,tx:STC.dk,sub:'#5B625D',dot:STC.rd,acc:STC.rd,accTx:STC.cr,pan:STC.dk,panTx:STC.cr,paper:'#FFFFFF',line:STC.rd,heart:STC.rd,mov:STC.li} };
  return P[cor] || P.preto;
}
function cDados(j){
  const c = S.cardio[j]; if(!c) return null;
  const seg = monday(c.date), dias = Array.from({length:7},(_,i) => addDays(seg,i)), d = parse(c.date);
  const km = +c.km || 0, p = km ? c.min/km : 0, pm = Math.floor(p), ps = Math.round((p-pm)*60);
  return { j, local:c.local, min:c.min, km, kmT:km ? fmtKm(km) : '', int:c.int, intT:INT_L[c.int]||c.int, zona:CARDIO.zona||'Zona 2', date:c.date, hoje:c.date===today(),
    dia:d.getDate(), mes:ST_MES[d.getMonth()], ano:d.getFullYear(), pace:km ? pm+"'"+String(ps===60?59:ps).padStart(2,'0')+'"' : '',
    dias:dias.map(k => k<=c.date ? cardioOn(k) : -1), idx:dias.indexOf(c.date), semana:dias.filter(k => k<=c.date).reduce((t,k) => t+cardioOn(k),0), meta:S.cardioGoal||150 };
}
let CST_PONY = null; const CST_TMP = document.createElement('canvas');
// desenha o mesmo boneco do app (aparelho, intensidade e boneco da pessoa) num quadro do story
function cBoneco(c, x, y, w, h, D, phase, dt){
  const kind = ANIM_KIND[D.local] || 'rope', k = Math.max(0, INTENS.indexOf(D.int));
  if(CST_TMP.width!==Math.round(w)) CST_TMP.width = Math.round(w); if(CST_TMP.height!==Math.round(h)) CST_TMP.height = Math.round(h);
  const keep = {}; ['ctx','kind','k','P','phase','col','sex','pony','w','h','dpr'].forEach(f => keep[f] = AN[f]);
  try { AN.ctx = CST_TMP.getContext('2d'); AN.kind = kind; AN.k = k; AN.P = Object.assign({}, AP[kind][k]); AN.phase = phase==null ? STILL[kind] : phase; AN.col = readCols(document.documentElement); AN.sex = sexOf(); AN.pony = CST_PONY; AN.w = w; AN.h = h; AN.dpr = 1; drawAnim(dt||1/30); CST_PONY = AN.pony; }
  catch(e){ /* sem boneco: segue o resto do story */ }
  finally { Object.assign(AN, keep); }
  c.drawImage(CST_TMP, x, y);
}
// traços de velocidade atrás do boneco: parados na foto; no vídeo andam para trás e somem, em laço
function cLinhas(c, P, t){
  c.save(); c.strokeStyle = P.mov; c.lineCap = 'round'; c.lineWidth = 10;
  [[150,830,90],[120,900,140],[160,970,80]].forEach(([x,y,l],i) => {
    if(t==null){ c.beginPath(); c.moveTo(x,y); c.lineTo(x+l,y); c.stroke(); return; }
    const f = (t*1.5 + i*.37) % 1, a = Math.sin(Math.PI*f), dx = 50 - f*120, ll = l*(.7+.3*a);
    c.globalAlpha = a; c.beginPath(); c.moveTo(x+dx,y); c.lineTo(x+dx+ll,y); c.stroke(); });
  c.restore();
}
const cFreq = D => { const kind = ANIM_KIND[D.local] || 'rope'; return (AP[kind][Math.max(0, INTENS.indexOf(D.int))]||{}).freq || 1; };
function cMarca(c,cor,dot){ stLogoDraw(c,90,110,110); stTxt(c,'simplificando',226,183,'900 52px '+ST_SANS,cor,'left',-2); c.font = '900 52px '+ST_SANS; stLs(c,-2); const lw = c.measureText('simplificando').width; stLs(c,0); stTxt(c,'.',226+lw,183,'900 52px '+ST_SANS,dot); }
function cRodape(c,cor){ stTxt(c,'simplificandoeduca.com',90,1800,'700 32px '+ST_SANS,cor); stTxt(c,'#simplificando',990,1800,'700 32px '+ST_SANS,cor,'right'); }
function cTitulo(c,s,y,size,cor,dot){ const z = stFit(c,s+'.',ST_SANS,size,900,900); c.font = '900 '+z+'px '+ST_SANS; stLs(c,-4); const w = c.measureText(s).width, wd = c.measureText('.').width; stLs(c,0); const x = 540-(w+wd)/2; stTxt(c,s,x,y,'900 '+z+'px '+ST_SANS,cor,'left',-4); stTxt(c,'.',x+w,y,'900 '+z+'px '+ST_SANS,dot); }
function cCards(c,arr,y){ arr.forEach(([v,l,bg,fg,sb],i) => { const x = 90+i*308; c.fillStyle = bg; stRR(c,x,y,284,200,36); c.fill(); c.font = '900 80px '+ST_SANS; const z = Math.min(80, Math.floor(80*220/Math.max(1,c.measureText(v).width))); stTxt(c,v,x+34,y+108,'900 '+z+'px '+ST_SANS,fg,'left',-3); c.font = '600 30px '+ST_SANS; stTxt(c,stCorte(c,l,224),x+34,y+155,'600 30px '+ST_SANS,sb); }); }
// sem: true = o fundo do Boneco sem o boneco (o vídeo desenha o boneco por cima)
function cDraw(c, D, m, cor, o){
  const P = cPal(cor), W = 1080, H = 1920, hoje = D.hoje ? ' hoje' : '';
  c.setTransform(1,0,0,1,0,0); c.clearRect(0,0,W,H); c.fillStyle = P.bg; c.fillRect(0,0,W,H);
  if(m==='cbon'){
    cMarca(c,P.tx,P.dot);
    const n = String(D.min); stTxt(c,n,90,560,'800 300px '+ST_MONO,P.tx,'left',-12); c.font = '800 300px '+ST_MONO; stLs(c,-12); const w = c.measureText(n).width; stLs(c,0);
    stTxt(c,'min',90+w+24,560,'900 80px '+ST_SANS,P.tx); stTxt(c,(CST_DE[D.local]||'de cardio')+hoje,90+w+24,470,'700 40px '+ST_SANS,P.sub);
    c.fillStyle = P.paper; stRR(c,60,640,960,640,64); c.fill();
    if(!(o && o.sem)){ cLinhas(c,P,null); cBoneco(c,90,690,900,583,D,o && o.phase,o && o.dt); }
    cTitulo(c,cFrase(D),1420,96,P.tx,P.dot);
    const pills = (D.km ? [D.kmT+' km','pace '+D.pace+'/km'] : [D.intT,D.min+' min']).concat([D.zona]);
    c.font = '800 40px '+ST_SANS; const ws = pills.map(t => c.measureText(t).width+72), tot = ws.reduce((a,b) => a+b,0)+2*20; let x = 540-tot/2;
    const pc = [[P.pan,P.panTx],[P.paper,STC.dk],[cor==='verde'?STC.dk:STC.lm, cor==='verde'?STC.lm:STC.dk]];
    pills.forEach((t,i) => { c.fillStyle = pc[i][0]; stRR(c,x,1510,ws[i],96,48); c.fill(); stTxt(c,t,x+ws[i]/2,1572,'800 40px '+ST_SANS,pc[i][1],'center'); x += ws[i]+20; });
    cRodape(c,P.sub);
  } else if(m==='cbil'){
    c.fillStyle = P.acc; stRR(c,300,150,480,84,42); c.fill(); stTxt(c,'✓ CARDIO FEITO',540,206,'900 38px '+ST_SANS,P.accTx,'center',3);
    const X = 90, Y = 290, PW = 900, H1 = 1000, H2 = 330, cut = 34;
    c.fillStyle = P.paper; stRR(c,X,Y,PW,H1+H2,44); c.fill();
    c.fillStyle = P.bg; [X, X+PW].forEach(x => { c.beginPath(); c.arc(x,Y+H1,cut,0,Math.PI*2); c.fill(); });
    c.setLineDash([16,14]); c.strokeStyle = '#C9CCC4'; c.lineWidth = 4; c.beginPath(); c.moveTo(X+cut+10,Y+H1); c.lineTo(X+PW-cut-10,Y+H1); c.stroke(); c.setLineDash([]);
    stLogoDraw(c,X+60,Y+56,100); stTxt(c,'PASSE DE CARDIO',X+PW-60,Y+100,'600 32px '+ST_MONO,'#5B625D','right',2);
    stTxt(c,String(D.dia).padStart(2,'0')+'/'+D.date.slice(5,7)+'/'+D.ano,X+PW-60,Y+142,'600 32px '+ST_MONO,'#5B625D','right');
    stTxt(c,'DE',X+60,Y+270,'800 30px '+ST_SANS,'#7A817C','left',4); stTxt(c,'PARA',X+PW-60,Y+270,'800 30px '+ST_SANS,'#7A817C','right',4);
    stTxt(c,'SOFÁ',X+60,Y+380,'900 104px '+ST_SANS,STC.dk,'left',-5); stTxt(c,'META',X+PW-60,Y+380,'900 104px '+ST_SANS,STC.dk,'right',-5);
    c.strokeStyle = STC.rd; c.lineWidth = 8; c.setLineDash([2,18]); c.lineCap = 'round'; c.beginPath(); c.moveTo(X+350,Y+340); c.lineTo(X+PW-420,Y+340); c.stroke(); c.setLineDash([]);
    c.fillStyle = STC.rd; c.beginPath(); c.moveTo(X+PW-385,Y+340); c.lineTo(X+PW-420,Y+318); c.lineTo(X+PW-420,Y+362); c.closePath(); c.fill();
    const F = [['APARELHO',D.local],['TEMPO',D.min+' min']].concat(D.km ? [['DISTÂNCIA',D.kmT+' km']] : []).concat([['INTENSIDADE',D.intT]]).concat(D.km ? [['PACE',D.pace+'/km']] : []).concat([['ASSENTO',D.zona]]);
    F.forEach(([l,v],i) => { const x = X+60+(i%3)*270, y = Y+520+Math.floor(i/3)*190; stTxt(c,l,x,y,'800 26px '+ST_SANS,'#7A817C','left',3); c.font = '900 54px '+ST_SANS; stTxt(c,stCorte(c,v,250),x,y+66,'900 54px '+ST_SANS,STC.dk,'left',-2); });
    stTxt(c,'SEMANA',X+60,Y+930,'800 26px '+ST_SANS,'#7A817C','left',3); c.fillStyle = '#E2E3DC'; stRR(c,X+250,Y+905,PW-310,28,14); c.fill();
    c.fillStyle = STC.rd; stRR(c,X+250,Y+905,Math.max(28,(PW-310)*Math.min(1,D.semana/D.meta)),28,14); c.fill();
    stTxt(c,D.semana+'/'+D.meta+' min',X+PW-60,Y+880,'600 28px '+ST_MONO,'#5B625D','right');
    let sd = 77; const rnd = () => (sd = sd*16807 % 2147483647)/2147483647; let bx = X+60; c.fillStyle = STC.dk; while(bx < X+PW-60){ const w = 3+Math.floor(rnd()*9); c.fillRect(bx,Y+H1+60,w,150); bx += w+4+Math.floor(rnd()*7); }
    stTxt(c,'simplificandoeduca.com · embarque confirmado',540,Y+H1+H2-50,'600 30px '+ST_MONO,'#5B625D','center');
    cTitulo(c,'Cardio pago',1770,96,P.tx,P.dot);
  } else if(m==='csem'){
    cMarca(c,P.tx,P.dot);
    stTxt(c,'MINHA SEMANA DE CARDIO',90,330,'800 34px '+ST_SANS,P.tx,'left',6);
    const big = '+'+D.min+' min'; stTxt(c,big,90,500,'900 150px '+ST_SANS,P.tx,'left',-7); c.font = '900 150px '+ST_SANS; stLs(c,-7); const w = c.measureText(big).width; stLs(c,0); stTxt(c,'.',90+w,500,'900 150px '+ST_SANS,P.dot);
    stTxt(c,[(CST_DE[D.local]||'de cardio')+hoje].concat(D.km ? [D.kmT+' km'] : []).concat([D.intT.toLowerCase()]).join(' · '),90,570,'700 40px '+ST_SANS,P.sub);
    const top = 700, base = 1250, bw = 100, gap = (900-7*bw)/6, mx = Math.max(45, CARDIO.min*1.6, ...D.dias), dl = ['SEG','TER','QUA','QUI','SEX','SÁB','DOM'];
    const yg = base-(base-top)*CARDIO.min/mx; c.setLineDash([14,12]); c.strokeStyle = P.sub; c.globalAlpha = .55; c.lineWidth = 4; c.beginPath(); c.moveTo(90,yg); c.lineTo(990,yg); c.stroke(); c.setLineDash([]); c.globalAlpha = 1;
    stTxt(c,'meta do dia',990,yg-16,'700 26px '+ST_SANS,P.sub,'right');
    D.dias.forEach((v,i) => { const x = 90+i*(bw+gap), eu = i===D.idx;
      if(v<0){ c.strokeStyle = P.sub; c.globalAlpha = .45; c.lineWidth = 4; stRR(c,x+2,base-40,bw-4,40,20); c.stroke(); c.globalAlpha = 1; }
      else if(v){ const h = Math.max(30,(base-top)*Math.min(v,mx)/mx); c.fillStyle = eu ? P.acc : P.tx; stRR(c,x,base-h,bw,h,26); c.fill(); stTxt(c,String(v),x+bw/2,base-h-24,'900 44px '+ST_SANS,eu ? P.acc : P.tx,'center'); }
      else { c.fillStyle = P.sub; c.globalAlpha = .3; stRR(c,x,base-24,bw,24,12); c.fill(); c.globalAlpha = 1; }
      stTxt(c,dl[i],x+bw/2,base+64,'800 30px '+ST_SANS,eu ? P.acc : P.sub,'center',2); });
    c.fillStyle = P.pan; stRR(c,90,1420,900,250,44); c.fill();
    stTxt(c,String(D.semana),140,1545,'900 110px '+ST_SANS,P.panTx,'left',-5); c.font = '900 110px '+ST_SANS; stLs(c,-5); const w2 = c.measureText(String(D.semana)).width; stLs(c,0);
    stTxt(c,'/'+D.meta+' min',150+w2,1545,'800 44px '+ST_SANS,'#9AA29B');
    stTxt(c,D.semana>=D.meta ? 'meta da semana batida!' : 'faltam '+(D.meta-D.semana)+' min para a meta',940,1530,'700 32px '+ST_SANS,STC.lm,'right');
    c.fillStyle = '#2E3A33'; stRR(c,140,1590,800,30,15); c.fill(); c.fillStyle = STC.lm; stRR(c,140,1590,Math.max(30,800*Math.min(1,D.semana/D.meta)),30,15); c.fill();
    cRodape(c,P.sub);
  } else {
    cMarca(c,P.tx,P.dot);
    stTxt(c,'● CARDIO NA '+D.zona.toUpperCase(),540,330,'800 34px '+ST_SANS,P.line,'center',6);
    const y0 = 790; c.lineJoin = 'round'; c.lineCap = 'round';
    const pts = [[0,y0],[230,y0],[280,y0-40],[320,y0],[380,y0],[420,y0+70],[470,y0-250],[520,y0+120],[560,y0],[640,y0],[690,y0-50],[740,y0],[1080,y0]];
    c.save(); c.shadowColor = P.line; c.shadowBlur = 36; c.strokeStyle = P.line; c.lineWidth = 16; c.beginPath(); pts.forEach(([x,y],i) => i ? c.lineTo(x,y) : c.moveTo(x,y)); c.stroke(); c.restore();
    c.save(); c.translate(470,y0-320); c.scale(2,2); c.fillStyle = P.heart; c.beginPath(); c.moveTo(0,12); c.bezierCurveTo(-26,-6,-14,-30,0,-16); c.bezierCurveTo(14,-30,26,-6,0,12); c.fill(); c.restore();
    stTxt(c,String(D.min).padStart(2,'0')+':00',540,1110,'800 250px '+ST_MONO,P.tx,'center',-10);
    stTxt(c,'min '+(CST_DE[D.local]||'de cardio')+hoje,540,1190,'700 42px '+ST_SANS,P.sub,'center');
    cTitulo(c,'Coração em dia',1380,104,P.tx,P.dot);
    const c1 = cor==='vermelho' ? [STC.cr,STC.dk,'#5B625D'] : [STC.rd,STC.cr,'#3A0D0D'], c3 = cor==='verde' ? [STC.dk,STC.lm,'#9AA29B'] : [STC.lm,STC.dk,'#2F3A12'];
    cCards(c,[[D.km ? D.kmT+' km' : D.intT, D.km ? 'distância' : 'intensidade', ...c1], [D.km ? D.pace : D.zona, D.km ? 'pace por km' : 'zona alvo', P.pan, P.panTx, '#9AA29B'], [String(D.dia), 'de '+D.mes, ...c3]], 1480);
    cRodape(c,P.sub);
  }
}
function cPick(j){
  if(!S.cardio[j]) return; clearTimeout(stpT);
  ui.sheet = {k:'cstpick', j, xOn:false}; renderSheet(true);
  const s = ui.sheet; (stOk ? Promise.resolve() : stFontes().then(() => { stOk = true; })).then(() => { if(ui.sheet!==s) return; cThumbs(s, null); });
  stpT = setTimeout(() => { if(ui.sheet!==s) return; s.xOn = true; const x = $sheet.querySelector('.stp-x'); if(x){ x.classList.add('on'); x.removeAttribute('tabindex'); x.removeAttribute('aria-hidden'); } }, 4000);
}
function vCStPick(){
  const s = ui.sheet, D = cDados(s.j); if(!D) return '';
  return `<div class="stp" role="dialog" aria-modal="true" aria-labelledby="stp-t" tabindex="-1">
    <button type="button" class="btn round stp-x${s.xOn?' on':''}" data-a="cstp-x" aria-label="Agora não"${s.xOn?'':' tabindex="-1" aria-hidden="true"'}>${ic('x')}</button>
    <div class="stp-h"><p class="eyebrow">${esc(D.local)} · ${D.min} min · ${esc(D.intT.toLowerCase())}</p><h2 id="stp-t">Cardio feito<span class="dot" aria-hidden="true"></span></h2><p>Agora mostra pra todo mundo. Escolha como quer postar no seu story:</p></div>
    <div class="stp-cards c4">${CST_MODS.map(([m,n,d],j) => `<button type="button" class="stp-c" data-a="cstp-go" data-v="${m}" style="--d:${j*80}ms"><span class="stp-th"><canvas data-cst-th="${m}" width="270" height="480" aria-hidden="true"></canvas>${m==='cbon'?`<i>${ic('play')}</i>`:''}</span><b>${n}</b><small>${d}</small></button>`).join('')}</div>
    <p class="stp-f">${ic('share')}Depois é só tocar em Instagram ou WhatsApp.</p>${igCard(false)}</div>`;
}
function cThumbs(s, cor){
  const D = cDados(s.j); if(!D) return;
  $sheet.querySelectorAll('canvas[data-cst-th]').forEach(cv => { const c = cv.getContext('2d'), m = cv.dataset.cstTh, k = cv.width/1080, tmp = document.createElement('canvas'); tmp.width = 1080; tmp.height = 1920;
    try { cDraw(tmp.getContext('2d'), D, m, cor || CST_COR0[m]); } catch(e){ return; } c.setTransform(1,0,0,1,0,0); c.clearRect(0,0,cv.width,cv.height); c.drawImage(tmp,0,0,cv.width,cv.height); cv.classList.add('on'); });
}
function cOpen(j, m){
  ui.sheet = {k:'cstory', j, m:m||PREF.cstM||'cbon', cor:(PREF.cstCor||{})[m||PREF.cstM||'cbon'] || CST_COR0[m||'cbon'], tipo:PREF.cstTipo||'foto', blob:null, vblob:null, grav:false};
  renderSheet(true); cRender();
}
function vCStory(){
  const s = ui.sheet, D = cDados(s.j); if(!D) return '';
  const vid = s.m==='cbon' && s.tipo==='video';
  const acoes = vid
    ? (s.vblob ? `<button type="button" class="btn primary block" data-a="cst-share">${ic('share')}Compartilhar vídeo no Instagram ou WhatsApp</button><button type="button" class="btn block" data-a="cst-gravar">${ic('play')}Gravar de novo</button>`
      : `<button type="button" class="btn primary block" data-a="cst-gravar"${s.grav?' disabled':''}>${ic('play')}${s.grav ? 'Gravando o vídeo… 6 s' : 'Gerar vídeo (6 segundos)'}</button><p class="foot0">O vídeo é gravado aqui no celular. Depois aparece o botão de compartilhar.</p>`)
    : `<button type="button" class="btn primary block" data-a="cst-share" data-st-go disabled>${ic('share')}Compartilhar no Instagram ou WhatsApp</button><button type="button" class="btn block" data-a="cst-down" data-st-go disabled>${ic('dl')}Baixar imagem</button>`;
  return `<div class="sheet-wrap" data-a="cst-closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop(esc(D.local)+' · '+D.min+' min · '+dayLabel(D.date), 'Compartilhar no story<span class="dot" aria-hidden="true"></span>').replace('data-a="close"','data-a="cst-close"')}
    <div class="stm c4" role="group" aria-label="Modelo da imagem">${CST_MODS.map(([m,n]) => `<button type="button" class="stm-c${s.m===m?' on':''}" aria-pressed="${s.m===m}" data-a="cst-m" data-v="${m}"><span class="stp-th"><canvas data-cst-th="${m}" width="135" height="240" aria-hidden="true"></canvas></span><b>${n}</b></button>`).join('')}</div>
    ${s.m==='cbon' ? `<div class="iseg cst-tipo" role="group" aria-label="Foto ou vídeo">${[['foto','Foto'],['video','Vídeo']].map(([v,l]) => `<button type="button" class="${s.tipo===v?'on':''}" aria-pressed="${s.tipo===v}" data-a="cst-tipo" data-v="${v}">${l}</button>`).join('')}</div>` : ''}
    <div class="st-cores" role="group" aria-label="Cor do fundo">${ST_CORES.map(([k,n,c]) => `<button type="button" class="${s.cor===k?'on':''}" style="--c:${c}" aria-pressed="${s.cor===k}" aria-label="${n}" title="${n}" data-a="cst-cor" data-v="${k}"></button>`).join('')}</div>
    <div class="st-prev"><canvas id="st-cv" width="1080" height="1920" role="img" aria-label="Prévia da imagem do cardio para o story"></canvas></div>
    ${acoes}${igTag()}
    <p class="foot0">No celular, escolha Instagram (story) ou WhatsApp na lista que abrir.</p></div></div>`;
}
let CST_RAF = 0;
async function cRender(){
  const s = ui.sheet; if(!s || s.k!=='cstory') return; const D = cDados(s.j); if(!D) return;
  if(!stOk){ await stFontes(); stOk = true; if(ui.sheet!==s) return; }
  cancelAnimationFrame(CST_RAF); const cv = $sheet.querySelector('#st-cv'); if(!cv) return; const c = cv.getContext('2d');
  cThumbs(s, s.cor);
  if(s.m==='cbon' && s.tipo==='video'){
    const base = document.createElement('canvas'), P = cPal(s.cor); base.width = 1080; base.height = 1920; cDraw(base.getContext('2d'), D, 'cbon', s.cor, { sem:true });
    let ph = .1, last = 0; const f = cFreq(D);
    const step = now => { if(ui.sheet!==s || !cv.isConnected) return; const dt = last ? Math.min(.05,(now-last)/1000) : 1/30; last = now; ph += dt*f;
      c.setTransform(1,0,0,1,0,0); c.drawImage(base,0,0); cLinhas(c,P,now/1000); cBoneco(c,90,690,900,583,D,ph,dt); CST_RAF = requestAnimationFrame(step); };
    CST_RAF = requestAnimationFrame(step); cv.classList.add('on'); return;
  }
  cDraw(c, D, s.m, s.cor); cv.classList.add('on'); stBlob(s);
}
function cSet(k, v){ const s = ui.sheet; s[k] = v; s.blob = null; s.vblob = null; if(k==='m'){ PREF.cstM = v; s.cor = (PREF.cstCor||{})[v] || CST_COR0[v]; } if(k==='cor'){ PREF.cstCor = Object.assign({}, PREF.cstCor, {[s.m]:v}); } if(k==='tipo') PREF.cstTipo = v; savePref(); renderSheet(); cRender(); }
// grava 6 s do story do Boneco direto da prévia (o canvas já está animando)
async function cGravar(){
  const s = ui.sheet; if(!s || s.k!=='cstory' || s.grav) return;
  const cv = $sheet.querySelector('#st-cv'), tipos = ['video/mp4;codecs=avc1.42E01E','video/mp4','video/webm;codecs=vp9','video/webm'];
  const tipo = window.MediaRecorder && cv && cv.captureStream ? tipos.find(t => { try { return MediaRecorder.isTypeSupported(t); } catch(e){ return false; } }) : null;
  if(!tipo){ toast('Este celular não grava vídeo por aqui. Use a opção Foto.'); return; }
  s.grav = true; s.vblob = null; renderSheet(); await cRender();
  const cv2 = $sheet.querySelector('#st-cv'); let rec, partes = [];
  try { rec = new MediaRecorder(cv2.captureStream(30), { mimeType:tipo, videoBitsPerSecond:5e6 }); } catch(e){ s.grav = false; renderSheet(); cRender(); toast('Não deu para gravar o vídeo. Use a opção Foto.'); return; }
  rec.ondataavailable = e => { if(e.data && e.data.size) partes.push(e.data); };
  const fim = new Promise(ok => { rec.onstop = ok; });
  rec.start(250); await new Promise(ok => setTimeout(ok, 6000)); rec.stop(); await fim;
  if(ui.sheet!==s) return;
  s.grav = false; s.vblob = partes.length ? new Blob(partes, { type:tipo.split(';')[0] }) : null;
  if(!s.vblob) toast('Não deu para gravar o vídeo. Use a opção Foto.');
  renderSheet(); cRender();
}
function cNome(s, blob){ const D = cDados(s.j); const ext = blob && /mp4/.test(blob.type) ? '.mp4' : blob && /webm/.test(blob.type) ? '.webm' : '.png'; return 'simplificando-cardio-'+(D ? D.date : 'hoje')+ext; }
function cSalvar(blob, nome){ const u = URL.createObjectURL(blob), a = document.createElement('a'); a.href = u; a.download = nome; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(u), 5000); toast('Salvo. Procure nos seus downloads ou na galeria.'); }
async function cShare(s){
  const vid = s.m==='cbon' && s.tipo==='video', blob = vid ? s.vblob : s.blob; if(!blob) return;
  const nome = cNome(s, blob), f = new File([blob], nome, { type:blob.type });
  if(navigator.canShare && navigator.canShare({files:[f]})){ try { await navigator.share({files:[f]}); } catch(e){ if(e && e.name!=='AbortError') cSalvar(blob, nome); } }
  else { cSalvar(blob, nome); toast('Seu navegador não abre o compartilhar. O arquivo foi salvo: poste pelo Instagram ou WhatsApp.'); }
}
function cLog(s, acao){ stReg(s, acao, 'cardio'); }

