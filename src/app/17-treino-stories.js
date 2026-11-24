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
const IG_AT = '@simplificando.educa'; // o nosso @ do Instagram, no pé de cada story
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
  // para os stories novos: cada exercício com séries × repetições da série mais pesada (recordes primeiro) e os grupos treinados
  const ex = (h.ex||[]).filter(x => (x.s||[]).length).map(x => { const b = x.s.reduce((a,s) => s[0]>a[0] ? s : a, x.s[0]); return {nome:x.n, n:x.s.length, kg:b[0]||0, reps:b[1]||0, pr:prs.has(x.id)}; })
    .filter(x => x.kg>0).sort((a,b) => (b.pr-a.pr) || (b.kg-a.kg));
  return {t:h.t, nome:p.nome||'Treino', sec, min:h.min, sets:h.sets, vol:h.vol||0, prs:prs.size, dia:d.getDate(), mes:d.getMonth(), d,
    hr:h.hr||'', gym:h.gym||'', cargas, ex, grupos:ST_NOVO ? stGrupos(h) : []};
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
  const hh = Math.floor(D.sec/3600), mm = Math.floor(D.sec%3600/60), ss = D.sec%60, tt = (hh ? hh+':'+String(mm).padStart(2,'0') : String(mm).padStart(2,'0'))+':'+String(ss).padStart(2,'0');
  c.font = '800 128px '+ST_MONO; stLs(c,-4); const tz = Math.min(128, Math.floor(128*460/Math.max(1,c.measureText(tt).width))); stLs(c,0);
  stTxt(c,tt,cx,cy+74,'800 '+tz+'px '+ST_MONO,STC.cr,'center',-4);
  stTxt(c,hh ? 'h : min : seg' : 'min : seg',cx,cy+190,'700 36px '+ST_SANS,'#9AA29B','center');
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
  stTxt(c,IG_AT,990,1800,'800 32px '+ST_SANS,T.ft,'right');
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
  row('Tempo de treino', fmtMin(D.min)); row('Recordes batidos', D.prs+(D.prs?' ★':''), D.prs>0);
  y = ys+Z+44; c.fillStyle = STC.dk;
  for(let x=0;x<PW-2*P;x+=40) [[0,6],[12,16],[26,34]].forEach(([a,b]) => { const w = Math.min(b, PW-2*P-x) - a; if(w>0) c.fillRect(X+P+x+a,y,w,110); });
  y += 110+26;
  stTxt(c,IG_AT+' · pago com suor',X+PW/2,y+32,'700 32px '+ST_MONO,'#5B625D','center');
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
  c.save(); c.translate(x+470, y+h+40); c.rotate(-3*Math.PI/180); c.fillStyle = s.cor==='verde' ? STC.dk : STC.lm; stRR(c,-200,-38,400,76,22); c.fill();
  stTxt(c,IG_AT,0,12,'900 34px '+ST_SANS,s.cor==='verde'?STC.lm:STC.dk,'center'); c.restore();
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
  stPinta(c,D,s.m,s.cor,s);
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
  stDraw(s); stBind(s); stBlob(s); stThumbs(s.i, s.cor, s.tom);
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
  const foto = s.m==='foto', mods = stMods(s.i), nc = mods.length>3 ? ' c'+mods.length : '';
  return `<div class="sheet-wrap" data-a="st-closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop('Treino '+h.t+' · '+dayLabel(h.date), 'Compartilhar no story<span class="dot" aria-hidden="true"></span>').replace('data-a="close"','data-a="st-close"')}
    <div class="stm${nc}" role="group" aria-label="Modelo da imagem">${mods.map(([m,n]) => `<button type="button" class="stm-c${s.m===m?' on':''}" aria-pressed="${s.m===m}" data-a="st-m" data-v="${m}"><span class="stp-th"><canvas data-st-th="${m}" width="135" height="240" aria-hidden="true"></canvas></span><b>${n}</b></button>`).join('')}</div>
    ${foto ? `<div class="row-btns st-foto"><label class="btn">${ic('camera')}Tirar foto<input type="file" accept="image/*" capture="user" data-st-file hidden></label><label class="btn">${ic('layers')}Galeria<input type="file" accept="image/*" data-st-file hidden></label></div>` : ''}
    ${s.m==='recorde' ? `<div class="st-cores st-tom" role="group" aria-label="Fundo">${ST_CORES.filter(x => x[0]==='preto' || x[0]==='branco').map(([k,n,c]) => `<button type="button" class="${(s.tom||'preto')===k?'on':''}" style="--c:${c}" aria-pressed="${(s.tom||'preto')===k}" aria-label="${n}" title="${n}" data-a="st-tom" data-v="${k}"></button>`).join('')}</div>` : ST_NOVOS.includes(s.m) ? '' : `<div class="st-cores" role="group" aria-label="${foto?'Cor da figurinha':'Cor do fundo'}">${ST_CORES.map(([k,n,c]) => `<button type="button" class="${s.cor===k?'on':''}" style="--c:${c}" aria-pressed="${s.cor===k}" aria-label="${n}" title="${n}" data-a="st-cor" data-v="${k}"></button>`).join('')}</div>`}
    <div class="st-prev"><canvas id="st-cv" width="1080" height="1920" role="img" aria-label="Prévia da imagem do treino para o story"${foto?' style="touch-action:none;cursor:grab"':''}></canvas></div>
    ${foto ? `<p class="foot0 st-tip"${s.ph?'':' hidden'}>Arraste a figurinha para mudar de lugar. Arraste a foto para ajustar o enquadramento. A foto fica só no seu celular.</p>` : ''}
    <button type="button" class="btn primary block" data-a="st-share" data-st-go disabled>${ic('share')}Compartilhar no Instagram ou WhatsApp</button>
    <button type="button" class="btn block" data-a="st-down" data-st-go disabled>${ic('dl')}Baixar imagem</button>
    ${igTag()}<p class="foot0">No celular, escolha Instagram (story) ou WhatsApp na lista que abrir.</p></div></div>`;
}
const ST_MODS = [['crono','Cronômetro','Seu tempo de treino'],['cupom','Cupom','Suas cargas do dia'],['foto','Foto','Sua foto com figurinhas']];
/* ---------- stories novos: Corpo (no lugar do Cronômetro), Recordes (só com recorde) e Sequência (só com 3+ semanas na meta) ----------
   Por enquanto só no app de teste e no robô (TESTE_LOCAL). Para liberar para todo mundo: ST_NOVO = true. */
const ST_NOVO = TESTE_LOCAL;
// contorno dos músculos: react-body-highlighter (licença MIT, github.com/GV79/react-body-highlighter), frente (f) e costas (c)
const ST_CORPO = {"f":[["CHEST",["51.8 41.6 51 55.1 58 58 67.8 55.5 70.6 47.3 62 41.6","29.8 46.5 31.4 55.5 40.8 58 48.2 55.1 47.8 42 37.6 42"]],["OBLIQUES",["68.6 63.3 67.3 57.1 58.8 59.6 60 64.1 60.4 83.3 65.7 78.8 66.5 69.8","33.9 78.4 33.1 71.8 31 63.3 32.2 57.1 40.8 59.2 39.2 63.3 39.2 83.7"]],["ABS",["56.3 59.2 58 64.1 58.4 78 58.4 92.7 56.3 98.4 55.1 104.1 51.4 107.8 51 84.5 50.6 67.3 51 57.1","43.7 58.8 48.6 57.1 49 67.3 48.6 84.5 48.2 107.3 44.5 103.7 40.8 91.4 40.8 78.4 41.2 64.5"]],["BICEPS",["16.7 68.2 18 71.4 22.9 66.1 29 53.9 27.8 49.4 20.4 55.9","71.4 49.4 70.2 54.7 76.3 66.1 81.6 71.8 82.9 69 78.8 55.5"]],["TRICEPS",["69.4 55.5 69.4 61.6 75.9 72.7 77.6 70.2 75.5 67.3","22.4 69.4 29.8 55.5 29.8 60.8 22.9 73.1"]],["NECK",["55.5 23.7 50.6 33.5 50.6 39.2 61.6 40 70.6 44.9 69.4 36.7 63.3 35.1 58.4 30.6","29 44.9 30.2 37.1 36.3 35.1 41.2 30.2 44.5 24.5 49 33.9 48.6 39.2 38 39.6"]],["FRONT_DELTOIDS",["78.4 53.1 79.6 47.8 79.2 41.2 75.9 38 71 36.3 72.2 42.9 71.4 47.3","28.2 47.3 21.2 53.1 20 47.8 20.4 40.8 24.5 37.1 28.6 37.1 26.9 43.3"]],["HEAD",["42.4 2.9 40 11.8 42 19.6 46.1 23.3 49.8 25.3 54.7 22.4 57.6 19.2 59.2 10.2 57.1 2.4 49.8 0"]],["ABDUCTORS",["52.7 110.2 54.3 124.9 60 110.2 62 100 64.9 94.3 60 92.7 56.7 104.5","47.8 110.6 44.9 125.3 42 115.9 40.4 113.1 39.6 107.3 38 102.4 34.7 93.9 39.6 92.2 41.6 99.2 43.7 105.3"]],["QUADRICEPS",["34.7 98.8 37.1 108.2 37.1 127.8 34.3 137.1 31 132.7 29.4 120 28.2 111.4 29.4 100.8 32.2 94.7","63.3 105.7 64.5 100 66.9 94.7 70.2 101.2 71 111.8 68.2 133.1 65.3 137.6 62.4 128.6 62 111.4","38.8 129.4 38.4 112.2 41.2 118.4 44.5 129.4 42.9 135.1 40 146.1 36.3 146.5 35.5 140","59.6 145.7 55.5 129 60.8 113.9 61.2 130.2 64.1 139.6 62.9 146.5","32.7 138.4 26.5 145.7 25.7 136.7 25.7 127.3 26.9 114.3 29.4 133.5","71.8 113.1 73.9 124.1 73.9 140.4 72.7 145.7 66.5 138.4 70.2 133.5"]],["KNEES",["33.9 140 34.7 143.3 35.5 147.3 36.3 151 35.1 156.7 29.8 156.7 27.3 152.7 27.3 147.3 30.2 144.1","65.7 140 72.2 147.8 72.2 152.2 69.8 157.1 64.9 156.7 62.9 151"]],["CALVES",["71.4 160.4 73.5 153.5 76.7 161.2 79.6 167.8 78.4 187.8 79.6 195.5 74.7 195.5","24.9 194.7 27.8 164.9 28.2 160.4 26.1 154.3 24.9 157.6 22.4 161.6 20.8 167.8 22 188.2 20.8 195.5","72.7 195.1 69.8 159.2 65.3 158.4 64.1 162.4 64.1 165.3 65.7 177.1","35.5 158.4 35.9 162.4 35.9 166.9 35.1 172.2 35.1 176.7 32.2 182 30.6 187.3 26.9 194.7 27.3 187.8 28.2 180.4 28.6 175.5 29 169.8 29.8 164.1 30.2 158.8"]],["FOREARM",["6.1 88.6 10.2 75.1 14.7 70.2 16.3 74.3 19.2 73.5 4.5 97.6 0 100","84.5 69.8 83.3 73.5 80 73.1 95.1 98.4 100 100.4 93.5 89.4 89.8 76.3","77.6 72.2 77.6 77.6 80.4 84.1 85.3 89.8 92.2 101.2 94.7 99.6","6.9 101.2 13.5 90.6 18.8 84.1 21.6 77.1 21.2 71.8 4.9 98.8"]]],"c":[["HEAD",["50.6 0 46 0.9 40.9 5.5 40.4 12.8 45.1 20 55.7 20 59.1 13.6 59.6 4.7 55.7 1.3"]],["TRAPEZIUS",["44.7 21.7 47.7 21.7 47.2 38.3 47.7 64.7 38.3 53.2 35.3 40.9 31.1 36.6 39.1 33.2 43.8 27.2","52.3 21.7 55.7 21.7 56.6 27.2 60.9 32.8 68.9 36.6 64.7 40.4 61.7 53.2 52.3 64.7 53.2 38.3"]],["BACK_DELTOIDS",["29.4 37 23 39.1 17.4 44.3 18.3 53.6 24.3 49.4 27.2 46.4","71.1 37 78.3 39.6 82.6 44.7 81.7 53.6 74.9 48.9 72.3 45.1"]],["UPPER_BACK",["31.1 38.7 28.1 48.9 28.5 55.3 34 75.3 47.2 71.1 47.2 66.4 36.6 54 33.6 41.3","68.9 38.7 71.9 49.4 71.5 56.2 66 75.3 52.8 71.1 52.8 66.4 63.4 54.5 66.4 41.7"]],["TRICEPS",["26.8 49.8 17.9 55.7 14.5 72.3 16.6 81.7 21.7 63.8 26.8 55.7","73.6 50.2 82.1 55.7 86 73.2 83.4 82.1 77.9 63 73.2 55.7","26.8 58.3 26.8 68.5 23 75.3 19.1 77.4 22.6 65.5","72.8 58.3 77 64.7 80.4 77.4 76.6 75.3 72.8 68.9"]],["LOWER_BACK",["47.7 72.8 34.5 77 35.3 83.4 49.4 102.1 46.8 83","52.3 72.8 65.5 77 64.7 83.4 50.6 102.1 53.2 83.8"]],["FOREARM",["86.4 75.7 91.1 83.4 93.2 94 100 106.4 96.2 104.3 88.1 89.4 84.3 83.8","13.6 75.7 8.9 83.8 6.8 93.6 0 106.4 3.8 104.3 12.3 88.5 15.7 83","81.3 79.6 77.4 77.9 79.1 84.7 91.1 103.8 93.2 108.9 94.5 104.7","18.7 79.6 22.1 77.9 20.9 84.3 9.4 103 6.8 108.5 5.1 104.7"]],["GLUTEAL",["44.7 99.6 30.2 108.5 29.8 118.7 31.5 126 47.2 121.3 49.4 114.9","55.3 99.1 51.1 114.5 52.3 120.9 68.1 126 69.8 119.1 69.4 108.5"]],["ABDUCTOR",["48.1 123 44.7 123 41.3 125.5 45.1 144.3 48.5 135.7 48.9 129.4","51.9 122.6 55.7 123.4 59.1 126 54.9 144.3 51.9 136.2 51.1 129.4"]],["HAMSTRING",["28.9 122.1 31.1 129.4 36.6 126 35.3 135.3 34.5 150.2 29.4 158.3 28.9 146.8 27.7 141.3 27.2 131.5","71.5 121.7 69.4 128.9 63.8 126 65.5 136.6 66.4 150.2 71.1 158.3 71.5 147.7 72.8 142.1 73.6 131.9","38.7 125.5 44.3 146 40.4 166.8 36.2 152.8 37 135.3","61.7 125.5 63.4 136.2 64.3 153.2 60 166.8 56.2 146.4"]],["KNEES",["34.5 153.2 31.1 159.1 33.6 166.4 37.4 162.6","66.4 153.6 63 163 66.8 166.4 69.4 159.1"]],["CALVES",["29.4 160.4 28.5 167.2 24.7 179.6 23.8 192.8 25.5 197 28.5 193.2 29.8 180 31.9 171.1 31.9 166.8","37.4 165.1 35.3 167.7 33.2 171.9 31.1 180.4 30.2 191.9 34 200 38.7 190.6 39.1 168.9","63 165.1 61.3 168.5 61.7 190.6 66.4 199.6 70.6 191.9 68.9 179.6 66.8 170.2","70.6 160.4 72.3 168.5 75.7 179.1 76.6 192.8 74.5 196.6 72.3 193.6 70.6 179.6 68.1 168.1"]],["LEFT_SOLEUS",["28.5 195.7 30.2 195.7 33.6 201.7 30.6 220 28.5 213.6 26.8 198.3"]],["RIGHT_SOLEUS",["69.8 195.7 71.9 195.7 73.6 198.3 71.9 213.2 70.2 219.6 67.2 202.1"]]]};
const ST_GM = {peito:['CHEST'], costas:['UPPER_BACK','LOWER_BACK','TRAPEZIUS','OBLIQUES'], ombro:['FRONT_DELTOIDS','BACK_DELTOIDS'], bic:['BICEPS'], tri:['TRICEPS'], abd:['ABS'],
  quad:['QUADRICEPS'], post:['HAMSTRING'], glut:['GLUTEAL'], adut:['ABDUCTORS','ABDUCTOR'], pant:['CALVES','LEFT_SOLEUS','RIGHT_SOLEUS']};
const ST_GN = {peito:'Peito', costas:'Costas', ombro:'Ombro', bic:'Bíceps', tri:'Tríceps', abd:'Abdômen', quad:'Quadríceps', post:'Posterior', glut:'Glúteo', adut:'Adutor', pant:'Panturrilha'};
const ST_GR = [[/adut/,'adut'],[/tr[ií]ceps|testa|franc[eê]s|mergulho/,'tri'],[/panturr|g[eê]meos|s[oó]leo/,'pant'],[/gl[uú]t|p[eé]lvic|coice|abdu/,'glut'],[/stiff|flexora|posterior|romeno|good morning/,'post'],
  [/agach|leg press|extensora|afundo|b[uú]lgaro|passada|hack|quadr|sissy/,'quad'],[/desenvolv|eleva[cç][aã]o (lateral|frontal)|ombro|deltoid|face pull|invertido/,'ombro'],
  [/supino|peito|crucifixo|peck|voador|flex[aã]o de bra|crossover|fly/,'peito'],[/remada|puxada|pulldown|barra fixa|costas|dorsal|serrote|pullover|lombar|hiperext/,'costas'],[/rosca|b[ií]ceps/,'bic'],[/abdom|prancha|crunch|obl[ií]q/,'abd']];
function stGrupo(id, nome){
  const f = findEx(id), t = ((nome||'')+' '+(f && f.e.alvo || '')).toLowerCase();
  if(/adut/.test(t)) return 'adut';
  const k = catOf(id); if(k && ST_GM[k.g]) return k.g;
  for(const [r,g] of ST_GR) if(r.test(t)) return g;
  return '';
}
function stGrupos(h){ const s = new Set(); (h.ex||[]).forEach(x => { if((x.s||[]).length){ const g = stGrupo(x.id, x.n); if(g) s.add(g); } }); return Object.keys(ST_GN).filter(g => s.has(g)); }
function stFrase(gs){
  const legs = ['quad','post','glut','adut','pant'].filter(g => gs.includes(g)), up = ['peito','costas','ombro','bic','tri','abd'].filter(g => gs.includes(g));
  if(legs.length>=2 && up.filter(g => ['peito','costas','ombro'].includes(g)).length>=2 && gs.length>=6) return 'o corpo todo';
  const n = []; up.forEach(g => { if(g==='tri' && up.includes('bic')) return; n.push(g==='bic' && up.includes('tri') ? 'braços' : g==='ombro' ? 'ombros' : ST_GN[g].toLowerCase()); });
  if(legs.length>=2) n.push('pernas'); else legs.forEach(g => n.push(ST_GN[g].toLowerCase()));
  if(!n.length) return 'dia de treino';
  return n.length===1 ? n[0] : n.slice(0,-1).join(', ')+' e '+n[n.length-1];
}
function stQuebra(c, s, maxW){ const L = []; let a = ''; s.split(' ').forEach(w => { const t = a ? a+' '+w : w; if(a && c.measureText(t).width>maxW){ L.push(a); a = w; } else a = t; }); if(a) L.push(a); return L; }
// título grande que quebra em linhas (diminui a letra se passar de maxL linhas); devolve onde acabou
function stTitulo(c, s, x, y, size, maxL, cor, dot, maxW){
  let z = size, L; for(;;){ c.font = '900 '+z+'px '+ST_SANS; stLs(c,-z*.05); L = stQuebra(c, s, maxW); stLs(c,0); if(L.length<=maxL || z<=60) break; z -= 8; }
  const lh = Math.round(z*.97);
  L.forEach((l,i) => { const yy = y+i*lh; stTxt(c,l,x,yy,'900 '+z+'px '+ST_SANS,cor,'left',-z*.05);
    if(i===L.length-1){ c.font = '900 '+z+'px '+ST_SANS; stLs(c,-z*.05); const w = c.measureText(l).width; stLs(c,0); stTxt(c,'.',x+w,yy,'900 '+z+'px '+ST_SANS,dot); } });
  return y+(L.length-1)*lh;
}
// marca no pé dos stories novos: o logo e o nosso @ do Instagram (ajuda a divulgar)
function stMarca(c, cor){ stLogoDraw(c,80,1772,68); stTxt(c,IG_AT,166,1820,'900 38px '+ST_SANS,cor,'left',-1); }
const stEyebrow = D => ('Treino '+D.t+' · '+D.dia+' '+ST_MES[D.mes].slice(0,3)).toUpperCase();
// pílulas que quebram em linhas; com y0 = null só mede (devolve as linhas)
function stPilulas(c, itens, x, y0, maxW, bg, fg){
  c.font = '800 38px '+ST_SANS; const L = [[]]; let w = 0;
  itens.forEach(t => { const pw = c.measureText(t).width+48; if(w && w+pw>maxW){ L.push([]); w = 0; } L[L.length-1].push([t,pw]); w += pw+14; });
  if(y0!=null) L.forEach((ln,i) => { let xx = x; ln.forEach(([t,pw]) => { c.fillStyle = bg; stRR(c,xx,y0+i*90,pw,76,38); c.fill(); stTxt(c,t,xx+24,y0+i*90+51,'800 38px '+ST_SANS,fg); xx += pw+14; }); });
  return L.length;
}
const ST_VERDE = '#8EE85F';
// claro = versão branca (fundo creme, texto escuro)
function stMapa(c, D, claro){
  const W = 1080, H = 1920, gs = D.grupos, cor = ST_VERDE, tx = claro ? STC.dk : STC.cr;
  if(claro){ c.fillStyle = STC.cr; c.fillRect(0,0,W,H); }
  else { const g = c.createRadialGradient(540,1050,60,540,1050,1150); g.addColorStop(0,'#1E2A23'); g.addColorStop(.75,STC.dk); c.fillStyle = g; c.fillRect(0,0,W,H); }
  stTxt(c,stEyebrow(D),80,160,'800 38px '+ST_SANS,claro ? '#3F8F1F' : cor,'left',4.5);
  const fim = stTitulo(c,'Hoje foi '+stFrase(gs),80,290,118,4,tx,claro ? '#3F8F1F' : cor,920);
  const chips = gs.map(k => ST_GN[k]), nl = chips.length ? stPilulas(c,chips,80,null,920) : 0, cy = 1740-nl*90+14;
  if(nl) stPilulas(c,chips,80,cy,920,cor,STC.dk);
  // corpo: frente e costas (desenho 208×205)
  const t1 = fim+80, bot = (nl ? cy : 1740)-50, k = Math.min(1000/208, (bot-t1)/222), top = t1+Math.max(0, (bot-t1-222*k)/2), ox = 540-104*k, mus = new Set(gs.flatMap(x => ST_GM[x]));
  c.save(); c.translate(ox,top); c.scale(k,k); c.translate(4,2); c.lineJoin = 'round'; c.lineWidth = claro ? .5 : .35; c.strokeStyle = claro ? STC.cr : STC.dk;
  [['f',0],['c',104]].forEach(([lado,dx]) => ST_CORPO[lado].forEach(([m,ps]) => {
    c.fillStyle = mus.has(m) ? cor : ['HEAD','NECK','KNEES'].includes(m) ? (claro ? '#C2C6BE' : '#5B6B60') : (claro ? '#DCDED6' : '#2B3830');
    ps.forEach(p => { const n = p.split(' ').map(Number); c.beginPath(); for(let i=0;i<n.length;i+=2) i ? c.lineTo(n[i]+dx,n[i+1]) : c.moveTo(n[i]+dx,n[i+1]); c.closePath(); c.fill(); c.stroke(); });
  }));
  c.restore();
  stMarca(c,tx);
  stTxt(c,fmtMin(D.min)+' · '+D.sets+' série'+(D.sets===1?'':'s'),1000,1820,'800 36px '+ST_SANS,claro ? '#6E776F' : '#9AA29B','right');
}
function stMedalha(c, x, y, r){ c.fillStyle = '#FF4646'; c.beginPath(); c.moveTo(x-r*.7,y-r*2.1); c.lineTo(x-r*.15,y-r*.6); c.lineTo(x+r*.15,y-r*.6); c.lineTo(x+r*.7,y-r*2.1); c.closePath(); c.fill();
  c.fillStyle = '#FFC93C'; c.beginPath(); c.arc(x,y,r,0,Math.PI*2); c.fill(); c.fillStyle = '#E8A500'; c.beginPath(); c.arc(x,y,r*.6,0,Math.PI*2); c.fill(); }
function stTrofeu(c, x, y){ // taça dourada simples (x,y = canto de cima à esquerda, 96×96)
  c.save(); c.translate(x,y); c.strokeStyle = '#E8A500'; c.lineWidth = 7; c.beginPath(); c.arc(18,30,13,Math.PI*.5,Math.PI*1.5); c.stroke(); c.beginPath(); c.arc(78,30,13,-Math.PI*.5,Math.PI*.5); c.stroke();
  c.fillStyle = '#FFC93C'; c.beginPath(); c.moveTo(18,8); c.lineTo(78,8); c.quadraticCurveTo(78,56,48,62); c.quadraticCurveTo(18,56,18,8); c.fill();
  c.fillStyle = '#E8A500'; c.fillRect(42,60,12,16); stRR(c,26,76,44,14,5); c.fill(); c.restore(); }
function stRecorde(c, D, claro){
  const W = 1080, H = 1920, tx = claro ? STC.dk : STC.cr, sub = claro ? '#8A918B' : '#9AA29B', laranja = claro ? '#E8770F' : STC.or;
  c.fillStyle = claro ? STC.cr : STC.dk; c.fillRect(0,0,W,H);
  stTxt(c,stEyebrow(D),80,160,'800 38px '+ST_SANS,claro ? '#3F8F1F' : STC.lm,'left',4.5);
  const fim = stTitulo(c,D.nome,80,290,120,2,tx,STC.rd,920), by = fim+70;
  [[fmtMin(D.min).replace(' min',''), D.min>=60 ? 'de treino' : 'min', ST_VERDE],[String(D.sets),'série'+(D.sets===1?'':'s'),STC.rd]].forEach(([v,l,bg],i) => {
    const x = 80+i*470; c.fillStyle = bg; stRR(c,x,by,450,190,36); c.fill();
    stTxt(c,v,x+34,by+110,'900 84px '+ST_SANS,'#FFFFFF','left',-3); stTxt(c,l,x+34,by+160,'700 34px '+ST_SANS,'#FFFFFF'); });
  const boxY = 1440, ry = by+250, n = Math.max(1, Math.min(D.ex.length, Math.floor((boxY-40-ry)/94)));
  D.ex.slice(0,n).forEach((x,i) => { const y = ry+i*94;
    c.fillStyle = claro ? '#E2E3DC' : '#26332B'; c.fillRect(80,y,920,2);
    const kg = fmtKg(x.kg)+' kg', sr = x.n+'×'+x.reps+' ';
    c.font = '800 42px '+ST_MONO; const wk = c.measureText(kg).width, ws = c.measureText(sr).width, md = x.pr ? 56 : 0;
    stTxt(c,kg,1000-md,y+62,'800 42px '+ST_MONO,x.pr ? laranja : sub,'right');
    stTxt(c,sr,1000-md-wk-ws,y+62,'800 42px '+ST_MONO,tx);
    if(x.pr) stMedalha(c,978,y+50,16);
    c.font = '700 42px '+ST_SANS; stTxt(c,stCorte(c,x.nome,920-md-wk-ws-30),80,y+62,'700 42px '+ST_SANS,tx); });
  const g = c.createLinearGradient(80,0,1000,0); g.addColorStop(0,'rgba(255,154,60,'+(claro?.18:.22)+')'); g.addColorStop(1,'rgba(255,154,60,'+(claro?.05:.06)+')');
  c.fillStyle = g; stRR(c,80,boxY,920,170,36); c.fill(); c.strokeStyle = 'rgba(255,154,60,.5)'; c.lineWidth = 3; stRR(c,81.5,boxY+1.5,917,167,35); c.stroke();
  stTrofeu(c,114,boxY+38);
  stTxt(c,D.prs+' recorde'+(D.prs===1?'':'s')+' hoje',236,boxY+76,'900 46px '+ST_SANS,laranja);
  c.font = '700 34px '+ST_SANS; stTxt(c,stCorte(c,D.ex.filter(x => x.pr).map(x => x.nome+' '+fmtKg(x.kg)+' kg').join(' · '),730),236,boxY+126,'700 34px '+ST_SANS,claro ? '#5B625D' : '#C9CDC6');
  stMarca(c,tx);
}
function stSeqDados(){
  const t0 = today(), d0 = parse(t0), y = d0.getFullYear(), m = d0.getMonth(), nd = new Date(y,m+1,0).getDate(), lead = (new Date(y,m,1).getDay()+6)%7, um = dk(new Date(y,m,1));
  const tr = new Set(S.history.map(h => h.date)), meta = streakMin(), wg = S.waterGoal||0;
  const dias = Array.from({length:nd},(_,i) => { const k = dk(new Date(y,m,i+1)); return {d:i+1, k, tr:tr.has(k), ca:cardioOn(k)>0, wa:wg>0 && (S.water[k]||0)>=wg, fut:k>t0, hoje:k===t0}; });
  const rows = Math.ceil((lead+nd)/7), ok = Array.from({length:rows},(_,r) => { const seg = addDays(um,-lead+7*r); if(seg>t0) return false; const ds = Array.from({length:7},(_,i) => addDays(seg,i)); return S.history.filter(h => ds.includes(h.date)).length>=meta; });
  const mk = t0.slice(0,7), ntr = S.history.filter(h => h.date.slice(0,7)===mk).length, sem = weekTrainCount(0);
  return {mes:ST_MES[m], dias, lead, rows, ok, st:streak(), meta, ntr, sem};
}
function stSequencia(c){
  const W = 1080, H = 1920, Q = stSeqDados();
  const g = c.createLinearGradient(0,0,W*.6,H); g.addColorStop(0,'#FF6A3D'); g.addColorStop(.55,'#FF4646'); g.addColorStop(1,'#D92626'); c.fillStyle = g; c.fillRect(0,0,W,H);
  [[140,.35],[230,.25],[320,.18],[410,.12],[500,.08]].forEach(([r,o]) => { c.strokeStyle = 'rgba(255,255,255,'+o+')'; c.lineWidth = 3; c.beginPath(); c.arc(900,380,r,0,Math.PI*2); c.stroke(); });
  stTxt(c,'MINHA SEQUÊNCIA',80,172,'800 38px '+ST_SANS,'#FFFFFF','left',4.5);
  const num = String(Q.st), nz = num.length>1 ? 380 : 460; c.font = '900 '+nz+'px '+ST_SANS; stLs(c,-nz*.08); const nw = c.measureText(num).width; stLs(c,0);
  stTxt(c,num,60,560,'900 '+nz+'px '+ST_SANS,'#FFFFFF','left',-nz*.08);
  const tx = 60+nw+40; ['semanas','seguidas','na meta'].forEach((l,i) => stTxt(c,l,tx,402+i*87,'900 92px '+ST_SANS,'#FFFFFF','left',-3.7));
  c.font = '900 92px '+ST_SANS; stLs(c,-3.7); const lw = c.measureText('na meta').width; stLs(c,0); stTxt(c,'.',tx+lw,576,'900 92px '+ST_SANS,STC.dk);
  // cartão branco com o calendário do mês
  const cw = 112, ch = 92, gp = 12, x0 = 124, top = 680, ry = top+190, ch0 = 190+Q.rows*(ch+14)-14+110;
  c.save(); c.shadowColor = 'rgba(0,0,0,.18)'; c.shadowBlur = 60; c.shadowOffsetY = 30; c.fillStyle = '#FFFFFF'; stRR(c,50,top,980,ch0,48); c.fill(); c.restore();
  stTxt(c,Q.mes.charAt(0).toUpperCase()+Q.mes.slice(1),x0-24,top+92,'900 52px '+ST_SANS,STC.dk,'left',-1.5);
  stTxt(c,'meta: '+Q.meta+' treino'+(Q.meta===1?'':'s')+'/semana',1000,top+90,'800 30px '+ST_SANS,'#5B625D','right');
  'STQQSSD'.split('').forEach((l,i) => stTxt(c,l,x0+i*(cw+gp)+cw/2,top+160,'800 30px '+ST_SANS,'#9AA29B','center'));
  Q.ok.forEach((ok,r) => { if(!ok) return; c.fillStyle = STC.li; stRR(c,x0-24,ry+r*(ch+14)-7,10,ch+14,5); c.fill(); });
  Q.dias.forEach(x => { const p = Q.lead+x.d-1, cx = x0+(p%7)*(cw+gp), cy = ry+Math.floor(p/7)*(ch+14);
    if(x.hoje){ c.strokeStyle = STC.dk; c.lineWidth = 5; stRR(c,cx-6.5,cy-6.5,cw+13,ch+13,28); c.stroke(); }
    if(!x.fut){ c.fillStyle = x.tr ? STC.rd : '#F1EFEA'; stRR(c,cx,cy,cw,ch,22); c.fill(); }
    if(x.ca && !x.fut){ c.strokeStyle = ST_VERDE; c.lineWidth = 7; stRR(c,cx+3.5,cy+3.5,cw-7,ch-7,19); c.stroke(); }
    stTxt(c,String(x.d),cx+cw/2,cy+(x.wa && !x.fut ? 52 : 59),'900 38px '+ST_SANS,x.fut ? '#C9CDC6' : x.tr ? '#FFFFFF' : '#5B625D','center');
    if(x.wa && !x.fut){ c.fillStyle = '#FFFFFF'; c.beginPath(); c.arc(cx+cw/2,cy+ch-17,10,0,Math.PI*2); c.fill(); c.fillStyle = '#5AB8FF'; c.beginPath(); c.arc(cx+cw/2,cy+ch-17,7,0,Math.PI*2); c.fill(); } });
  // legenda
  let lx = x0-24; const ly = top+ch0-52;
  [['tr','Treino'],['ca','Cardio'],['wa','Água na meta'],['sm','Semana na meta']].forEach(([k,t]) => {
    if(k==='tr'){ c.fillStyle = STC.rd; stRR(c,lx,ly-24,30,30,8); c.fill(); }
    else if(k==='ca'){ c.strokeStyle = ST_VERDE; c.lineWidth = 6; stRR(c,lx+3,ly-21,24,24,7); c.stroke(); }
    else if(k==='wa'){ c.fillStyle = '#5AB8FF'; c.beginPath(); c.arc(lx+15,ly-9,9,0,Math.PI*2); c.fill(); }
    else { c.fillStyle = STC.li; stRR(c,lx+10,ly-30,10,42,5); c.fill(); }
    stTxt(c,t,lx+42,ly,'700 28px '+ST_SANS,'#5B625D'); c.font = '700 28px '+ST_SANS; lx += 42+c.measureText(t).width+34; });
  const fy = top+ch0+76;
  stTxt(c,Q.ntr+' treino'+(Q.ntr===1?'':'s')+' no mês',80,fy,'800 40px '+ST_SANS,'#FFFFFF');
  stTxt(c,Q.sem>=Q.meta ? '✓ meta da semana batida' : 'faltam '+(Q.meta-Q.sem)+' esta semana',1000,fy,'800 40px '+ST_SANS,Q.sem>=Q.meta ? STC.lm : '#FFFFFF','right');
  stMarca(c,'#FFFFFF');
}
/* treino e cardio no mesmo dia: um story só com os dois */
const stCardioDia = h => (S.cardio||[]).map((c,j) => Object.assign({ j }, c)).filter(c => h && c.date===h.date && +c.min>0);
function stCombo(c, D, h){
  const W = 1080, H = 1920, CD = stCardioDia(h), cmin = CD.reduce((t,x) => t+(+x.min||0), 0), km = CD.reduce((t,x) => t+(+x.km||0), 0), loc = [...new Set(CD.map(x => x.local))];
  c.fillStyle = STC.dk; c.fillRect(0,0,W,H);
  stTxt(c,('Hoje · '+D.dia+' '+ST_MES[D.mes].slice(0,3)).toUpperCase(),80,160,'800 38px '+ST_SANS,STC.lm,'left',4.5);
  stTitulo(c,'Treino e cardio feitos',80,290,120,2,STC.cr,STC.rd,920);
  // tempo do dia
  const tot = (D.min||0)+cmin, tt = fmtMin(tot); c.font = '900 230px '+ST_SANS; stLs(c,-12); const tw = c.measureText(tt).width; stLs(c,0);
  stTxt(c,tt,74,720,'900 230px '+ST_SANS,STC.cr,'left',-12); stTxt(c,'de movimento hoje',84,790,'700 40px '+ST_SANS,'#9AA29B');
  // treino (vermelho) com o corpo e os músculos do dia
  const card = (y, bg, tx, sub, eb, big, lin) => { c.fillStyle = bg; stRR(c,60,y,960,360,44); c.fill();
    stTxt(c,eb,104,y+76,'800 32px '+ST_SANS,sub,'left',4); stTxt(c,big,100,y+200,'900 120px '+ST_SANS,tx,'left',-5);
    c.font = '700 38px '+ST_SANS; stTxt(c,stCorte(c,lin,520),104,y+282,'700 38px '+ST_SANS,sub); };
  card(880, STC.rd, '#FFFFFF', 'rgba(255,255,255,.85)', ('Treino '+D.t+' · '+D.nome).toUpperCase().slice(0,28), fmtMin(D.min), D.sets+' série'+(D.sets===1?'':'s')+(D.prs ? ' · '+D.prs+' recorde'+(D.prs===1?'':'s') : ''));
  const gs = ST_NOVO ? D.grupos : [], mus = new Set(gs.flatMap(x => ST_GM[x]||[]));
  c.save(); c.translate(694,904); c.scale(1.45,1.45); c.lineJoin = 'round'; c.lineWidth = .5; c.strokeStyle = STC.rd;
  [['f',0],['c',104]].forEach(([lado,dx]) => ST_CORPO[lado].forEach(([m,ps]) => { c.fillStyle = mus.has(m) ? '#FFFFFF' : 'rgba(255,255,255,.28)';
    ps.forEach(p => { const n = p.split(' ').map(Number); c.beginPath(); for(let i=0;i<n.length;i+=2) i ? c.lineTo(n[i]+dx,n[i+1]) : c.moveTo(n[i]+dx,n[i+1]); c.closePath(); c.fill(); c.stroke(); }); }));
  c.restore();
  // cardio (verde) com o boneco
  card(1270, ST_VERDE, STC.dk, 'rgba(15,23,18,.7)', ('Cardio · '+(loc.join(' e ')||'cardio')).toUpperCase().slice(0,28), fmtMin(cmin), km ? fmtKm(km)+' km' : (CD.length>1 ? CD.length+' sessões' : (INT_L[CD[0] && CD[0].int]||'')+' intensidade'));
  const big = CD.slice().sort((a,b) => b.min-a.min)[0], cd = big ? cDados(big.j) : null;
  if(cd) try { cBoneco(c, 640, 1290, 360, 320, cd); } catch(e){ /* sem boneco */ }
  stMarca(c,STC.cr);
}
const ST_NOVOS = ['mapa','recorde','seq','combo'];
function stMods(i){
  if(!ST_NOVO) return ST_MODS;
  const h = S.history[i], L = [['mapa','Corpo','Os músculos do dia']];
  if(h && stDados(h).prs>0) L.push(['recorde','Recordes','Seus recordes do dia']);
  if(streak()>=3) L.push(['seq','Sequência','Suas semanas na meta']);
  if(h && stCardioDia(h).length) L.splice(1, 0, ['combo','Treino + cardio','O dia completo']);
  return L.concat(ST_MODS.slice(1));
}
function stPinta(c, D, m, cor, s){
  const claro = (s && s.tom || PREF.stTom)==='branco';
  if(m==='combo'){ stCombo(c,D,S.history[s && s.i!=null ? s.i : S.history.length-1]); return; }
  if(m==='mapa') stMapa(c,D,false); else if(m==='recorde') stRecorde(c,D,claro); else if(m==='seq') stSequencia(c);
  else if(m==='cupom') stCupom(c,D,cor); else if(m==='foto') stFoto(c,D,s); else stCrono(c,D,ST_TEMA[cor]);
}
// leque de miniaturas com 4 ou 5 stories (com 3 fica o leque de sempre)
function stLeque(j, n){ if(n===3) return ' class="sum-th t'+j+'"'; const c = (n-1)/2, o = j-c, st = n===4 ? 70 : 58;
  return ` class="sum-th fv" style="--w:${n===4?96:88}px;--tx:${(-50+o*st).toFixed(0)}%;--ty:${(-22+16*Math.abs(o)/c).toFixed(0)}px;--r:${(o*12/c).toFixed(1)}deg;z-index:${10-Math.round(Math.abs(o)*2)}"`; }
function stThumbs(i, cor, tom){
  const h = S.history[i]; if(!h) return;
  const run = () => { const D = stDados(h);
    $sheet.querySelectorAll('canvas[data-st-th]').forEach(cv => { const c = cv.getContext('2d'), m = cv.dataset.stTh, k = cv.width/1080;
      c.setTransform(k,0,0,k,0,0); c.clearRect(0,0,1080,1920);
      stPinta(c,D,m,cor,{cor, tom, i, sx:70, sy:1280, px:0, py:0});
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
  const p = PLAN[h.t] || {nome:'Treino'}, mods = stMods(s.i);
  return `<div class="stp" role="dialog" aria-modal="true" aria-labelledby="stp-t" tabindex="-1">
    <button type="button" class="btn round stp-x${s.xOn?' on':''}" data-a="stp-x" aria-label="Agora não"${s.xOn?'':' tabindex="-1" aria-hidden="true"'}>${ic('x')}</button>
    <div class="stp-h"><p class="eyebrow">Treino ${h.t} · ${esc(p.nome)} · ${fmtMin(h.min)}</p><h2 id="stp-t">Treino concluído<span class="dot" aria-hidden="true"></span></h2><p>Agora mostra pra todo mundo. Escolha como quer postar no seu story:</p></div>
    <div class="stp-cards${mods.length>3?' c'+mods.length:''}">${mods.map(([m,n,d],j) => `<button type="button" class="stp-c" data-a="stp-go" data-v="${m}" style="--d:${j*90}ms"><span class="stp-th"><canvas data-st-th="${m}" width="270" height="480" aria-hidden="true"></canvas>${m==='foto'?`<i>${ic('camera')}</i>`:''}</span><b>${n}</b><small>${d}</small></button>`).join('')}</div>
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
  const mods = stMods(i).map(x => x[0]), m0 = [m, PREF.stM].find(x => mods.includes(x)) || mods[0];
  ui.sheet = {k:'story', i, m:m0, tom:PREF.stTom||'preto', cor:PREF.stCor||'vermelho', sx:70, sy:1280, px:0, py:0, back:old ? old.back : i};
  renderSheet(true); stRender();
}
function stSet(k,v){ const s = ui.sheet; s[k] = v; s.blob = null; PREF[k==='m'?'stM':k==='tom'?'stTom':'stCor'] = v; savePref(); renderSheet(); stRender(); }

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
function cRodape(c,cor){ stTxt(c,'simplificandoeduca.com',90,1800,'700 32px '+ST_SANS,cor); stTxt(c,IG_AT,990,1800,'800 32px '+ST_SANS,cor,'right'); }
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
    const pills = (D.km ? [D.kmT+' km','pace '+D.pace+'/km'] : [D.intT,fmtMin(D.min)]).concat([D.zona]);
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
    const F = [['APARELHO',D.local],['TEMPO',fmtMin(D.min)]].concat(D.km ? [['DISTÂNCIA',D.kmT+' km']] : []).concat([['INTENSIDADE',D.intT]]).concat(D.km ? [['PACE',D.pace+'/km']] : []).concat([['ASSENTO',D.zona]]);
    F.forEach(([l,v],i) => { const x = X+60+(i%3)*270, y = Y+520+Math.floor(i/3)*190; stTxt(c,l,x,y,'800 26px '+ST_SANS,'#7A817C','left',3); c.font = '900 54px '+ST_SANS; stTxt(c,stCorte(c,v,250),x,y+66,'900 54px '+ST_SANS,STC.dk,'left',-2); });
    stTxt(c,'SEMANA',X+60,Y+930,'800 26px '+ST_SANS,'#7A817C','left',3); c.fillStyle = '#E2E3DC'; stRR(c,X+250,Y+905,PW-310,28,14); c.fill();
    c.fillStyle = STC.rd; stRR(c,X+250,Y+905,Math.max(28,(PW-310)*Math.min(1,D.semana/D.meta)),28,14); c.fill();
    stTxt(c,D.semana+'/'+D.meta+' min',X+PW-60,Y+880,'600 28px '+ST_MONO,'#5B625D','right');
    let sd = 77; const rnd = () => (sd = sd*16807 % 2147483647)/2147483647; let bx = X+60; c.fillStyle = STC.dk; while(bx < X+PW-60){ const w = 3+Math.floor(rnd()*9); c.fillRect(bx,Y+H1+60,w,150); bx += w+4+Math.floor(rnd()*7); }
    stTxt(c,IG_AT+' · embarque confirmado',540,Y+H1+H2-50,'600 30px '+ST_MONO,'#5B625D','center');
    cTitulo(c,'Cardio pago',1770,96,P.tx,P.dot);
  } else if(m==='csem'){
    cMarca(c,P.tx,P.dot);
    stTxt(c,'MINHA SEMANA DE CARDIO',90,330,'800 34px '+ST_SANS,P.tx,'left',6);
    const big = '+'+fmtMin(D.min); stTxt(c,big,90,500,'900 150px '+ST_SANS,P.tx,'left',-7); c.font = '900 150px '+ST_SANS; stLs(c,-7); const w = c.measureText(big).width; stLs(c,0); stTxt(c,'.',90+w,500,'900 150px '+ST_SANS,P.dot);
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
    <div class="stp-h"><p class="eyebrow">${esc(D.local)} · ${fmtMin(D.min)} · ${esc(D.intT.toLowerCase())}</p><h2 id="stp-t">Cardio feito<span class="dot" aria-hidden="true"></span></h2><p>Agora mostra pra todo mundo. Escolha como quer postar no seu story:</p></div>
    <div class="stp-cards c4">${CST_MODS.map(([m,n,d],j) => `<button type="button" class="stp-c" data-a="cstp-go" data-v="${m}" style="--d:${j*80}ms"><span class="stp-th"><canvas data-cst-th="${m}" width="270" height="480" aria-hidden="true"></canvas>${m==='cbon'?`<i>${ic('play')}</i>`:''}</span><b>${n}</b><small>${d}</small></button>`).join('')}</div>
    ${cComboBtn(D)}<p class="stp-f">${ic('share')}Depois é só tocar em Instagram ou WhatsApp.</p>${igCard(false)}</div>`;
}
// cardio no mesmo dia de um treino: botão para postar os dois juntos (abre o story "Treino + cardio")
function cComboI(D){ if(!ST_NOVO || !D) return -1; for(let i=S.history.length-1;i>=0;i--) if(S.history[i].date===D.date) return i; return -1; }
const cComboBtn = D => cComboI(D)<0 ? '' : `<button type="button" class="btn block cst-combo" data-a="cst-combo" data-i="${cComboI(D)}">${ic('layers')}Postar o treino e o cardio juntos</button>`;
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
    ${acoes}${cComboBtn(D)}${igTag()}
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

