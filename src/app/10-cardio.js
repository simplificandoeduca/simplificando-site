/* ---------- Cardio: três jeitos de registrar, mesmos dados ---------- */
const LOC_S = { Esteira:'Esteira', Bicicleta:'Bike', Rua:'Rua', Escada:'Escada', 'Elíptico':'Elíptico', Outro:'Outro' };
const INT_L = { Leve:'Leve', Moderada:'Média', Forte:'Forte' };
const TCHIPS = [15,20,30,45];
const EQI = { // ícones dos aparelhos: pessoa em movimento (esteira, bike, rua, elíptico), escada e coração
  Esteira:'<g class="e"><path d="M2.5 20.5h14.5"/><path d="M17 20.5l2.6-12.5h2"/><path d="M19.1 10.5h-3.4"/></g><g class="p"><circle class="hd" cx="10.4" cy="3.9" r="1.8"/><path d="M10 6.6l-1.4 5.2"/><path d="M8.6 11.8l3 2.3-.8 3.9"/><path d="M8.6 11.8l-2.3 3.3-2.8-.5"/><path d="M9.8 7.7l2.6 1.8 2.1-.9"/><path d="M9.8 7.7l-2.7 1.4-.9 2.1"/></g>',
  Bicicleta:'<g class="e"><circle cx="5" cy="17.2" r="3.4"/><circle cx="19" cy="17.2" r="3.4"/><path d="M5 17.2l4-6.4h6.2l3.8 6.4"/><path d="M9 10.8l3.4 6.4"/><path d="M15.2 10.8l.9-2.6h2"/></g><g class="p"><circle class="hd" cx="13.6" cy="3.6" r="1.8"/><path d="M12.8 6l-3.4 3.4"/><path d="M12.6 6.6l3.4 1.6"/><path d="M9.4 9.4l3.3 2.8-.8 3.6"/></g>',
  Rua:'<g class="e"><path d="M2.5 21h6.5M13 21h8.5"/><path d="M2 9.5h3.5M3 12.5h3"/></g><g class="p"><circle class="hd" cx="15" cy="3.8" r="1.9"/><path d="M14.4 6.7l-2.6 5.4"/><path d="M11.8 12.1l3.4 2.4-1.4 4.6"/><path d="M11.8 12.1l-2.4 3.6-3.6-.4"/><path d="M14 7.8l3 2.1 2.6-.7"/><path d="M14 7.8l-3.2.4-1.7 2.4"/></g>',
  Escada:'<path d="M3 20h4v-4h4v-4h4V8h4V4"/>',
  'Elíptico':'<g transform="matrix(-1 0 0 1 24 0)"><g class="e"><path d="M3 21h18"/><circle cx="18.2" cy="16.4" r="2.7"/><path d="M6.3 21L9.4 6.6"/><path d="M9.4 6.6l-2.1-1.8"/><path d="M5.5 18.6l8.9-2.6"/></g><g class="p"><circle class="hd" cx="13.4" cy="3.3" r="1.7"/><path d="M13.1 5.6l.4 5.4"/><path d="M13.5 11l-2.2 5"/><path d="M13.5 11l2.4 4.5"/><path d="M13 7l-3.5.6"/></g></g>',
  Outro:'<path d="M12 20.4S3.6 15.3 3.6 9.3A4.3 4.3 0 0 1 12 7.4a4.3 4.3 0 0 1 8.4 1.9c0 6-8.4 11.1-8.4 11.1z"/><path d="M6.6 12.6h2.9l1.4-2.6 2.1 4.2 1.4-1.6h3"/>'
};
const eqIc = (l,cls) => `<svg class="i ${cls||''}" viewBox="0 0 24 24" aria-hidden="true">${EQI[l]}</svg>`;
const LAYS = [
  {id:'toque', n:'Um toque', d:'Escolha aparelho, tempo e intensidade e arraste para salvar.', s:'Toca e arrasta para salvar.'},
  {id:'crono', n:'Cronômetro', d:'Ligue no começo e toque em Terminar. O tempo vai sozinho.', s:'Liga no início, termina no fim.'},
  {id:'cartoes', n:'Ícones', d:'Toque no aparelho que usou e ajuste os minutos.', s:'Toque no aparelho e salve.'}
];
const layOf = () => LAYS.some(o => o.id===PREF.cardioLayout) ? PREF.cardioLayout : 'toque';
const weekMin = () => weekDays(0).reduce((a,k) => a+cardioOn(k),0);
function lastOf(l){ for(let i=S.cardio.length-1; i>=0; i--) if(S.cardio[i].local===l) return S.cardio[i]; return null; }
function shortDay(k){ const d = diffDays(today(),k); if(d===0) return 'hoje'; if(d===1) return 'ontem'; if(d<7) return parse(k).toLocaleDateString('pt-BR',{weekday:'short'}).replace('.',''); return dm(k); }
function cardioElapsed(){ const L = S.cardioLive; if(!L) return 0; return L.acc+(L.run ? Date.now()-L.start : 0); }
function liveGoal(ms){
  const tot = cardioOn(today())+ms/60000, rem = Math.ceil(CARDIO.min-tot);
  return { p:Math.min(1, tot/CARDIO.min), txt: rem>0 ? `Faltam ${rem} min para a meta de hoje` : 'Meta de hoje batida. Agora é bônus.' };
}
function logCardio(local,min,int_){
  min = Math.max(1, Math.round(min));
  const wkB = weekMin();
  S.cardio.push({date:today(), local, min, int:int_, km:0});
  if(S.cDel) S.cDel = S.cDel.filter(k => k!==today()+'|'+local+'|'+min);
  ui.cf.local = local; ui.cf.int = int_;
  save();
  if(wkB<S.cardioGoal && wkB+min>=S.cardioGoal) setTimeout(() => celebrate('Meta de cardio batida', S.cardioGoal+' min na semana. O anel fechou.', '', 'cardio'), RM() ? 0 : 450);
  else toast(`Cardio salvo: ${min} min · ${local}, ${INT_L[int_].toLowerCase()}.`);
  vibrate(20);
  const jc = S.cardio.length-1; setTimeout(() => { if(!ui.sheet || ui.sheet.k==='cs' || ui.sheet.k==='cman') cPick(jc); }, RM() ? 600 : 1500);
}
function miniRing(p,label){
  const r = 15, c = 2*Math.PI*r;
  return `<span class="mring" role="img" aria-label="${label}"><svg viewBox="0 0 40 40" aria-hidden="true"><circle class="mr-t" cx="20" cy="20" r="${r}"/><circle class="mr-f" cx="20" cy="20" r="${r}" stroke-dasharray="${c.toFixed(1)}" stroke-dashoffset="${(c*(1-Math.min(1,Math.max(0,p)))).toFixed(1)}" transform="rotate(-90 20 20)"/></svg></span>`;
}
function eqGrid(action,cur,label){
  return `<div class="eqrow" role="group" aria-label="${label||'Aparelho'}" style="grid-template-columns:repeat(${LOCAIS.length},minmax(0,1fr))">${LOCAIS.map(l => `<button type="button" class="eqb${l===cur?' on':''}" aria-pressed="${l===cur}" data-a="${action}" data-v="${l}">${eqIc(l)}<span>${LOC_S[l]}</span></button>`).join('')}</div>`;
}
function intSeg(action,cur,dots){
  return `<div class="iseg" role="group" aria-label="Intensidade">${INTENS.map((n,i) => `<button type="button" class="${n===cur?'on':''}" aria-pressed="${n===cur}" data-a="${action}" data-v="${n}">${INT_L[n]}${dots?`<span class="bars3" aria-hidden="true">${[0,1,2].map(j => `<s class="${j<=i?'f':''}"></s>`).join('')}</span>`:''}</button>`).join('')}</div>`;
}
function timeChips(action,cur,more){
  const custom = !TCHIPS.includes(cur);
  return `<div class="tchips" role="group" aria-label="Tempo em minutos">${TCHIPS.map(m => `<button type="button" class="tchip${cur===m?' on':''}" aria-pressed="${cur===m}" data-a="${action}" data-v="${m}">${m}<small>min</small></button>`).join('')}
    ${more ? `<button type="button" class="tchip more${custom?' on':''}" data-a="${more}" aria-expanded="${!!ui.cfMore}" aria-label="${custom?'Tempo: '+cur+' minutos. Ajustar':'Outro tempo'}">${custom?cur+'<small>min</small>':ic('edit')}</button>` : ''}</div>`;
}
function minStepper(action,v,big){
  return `<div class="stepper${big?' big':''}"><button type="button" class="btn round" data-a="${action}" data-d="-5" aria-label="Menos 5 minutos">${ic('minus')}</button><output aria-live="polite">${v}<small> min</small></output><button type="button" class="btn round" data-a="${action}" data-d="5" aria-label="Mais 5 minutos">${ic('plus')}</button></div>`;
}
function weekStrip(){
  const ds = weekDays(0), t0 = today(), wk = weekMin(), G = S.cardioGoal;
  return `<section class="card wstrip" aria-label="Minutos de cardio por dia nesta semana"><div class="ws-days">${ds.map((k,i) => { const v = cardioOn(k); return `<div class="ws-d${k===t0?' now':''}${k>t0?' fut':''}"><span>${WD[i]}</span><i class="${v?'f':''}">${v||''}</i></div>`; }).join('')}</div>
    <div class="ws-tot"><b>${wk}${G ? `<small>/${G}</small>` : ''}</b><span>min na semana</span></div></section>`;
}
function weekCompact(){
  const wk = weekMin(), G = S.cardioGoal, ok = G>0 && wk>=G;
  return `<section class="card cwk${ok?' goal-hit':''}"><div class="card-h"><span class="label">${ic('pulse')}Esta semana</span><span class="pill${ok?' ok':''}">${ok?'Meta batida':G?'Faltam '+(G-wk)+' min':'Sem meta'}</span></div>
    <div class="wkc-row"><b>${wk}<small>${G?'/'+G:''} min</small></b>${bar(G?wk/G:0)}</div>
    <p class="foot0">Prescrição: ${CARDIO.min} min por dia · ${CARDIO.zona}. ${CARDIO.desc}</p></section>`;
}
function cardioRecent(){
  const N = S.cardio.length, list = S.cardio.slice().reverse().slice(0,5).map((c,j) =>
    `<div class="cs-wrap"><button type="button" class="cs-del" data-a="cdel" data-i="${N-1-j}" aria-label="Excluir ${esc(c.local)} de ${c.min} min (${dayLabel(c.date)})">${ic('trash')}Excluir</button><div class="row cs-row" data-cswipe><span class="num">${eqIc(LOCAIS.includes(c.local)?c.local:'Outro')}</span><span class="main"><b>${esc(c.local)} · ${c.min} min</b><span>${INT_L[c.int]||c.int}${c.km?' · '+fmtKm(c.km)+' km':''}</span></span><span class="when">${dayLabel(c.date)}</span><button type="button" class="cs-st" data-a="cst-open" data-j="${N-1-j}" aria-label="Postar este cardio no story">${ic('share')}</button></div></div>`).join('')
    || '<div class="row"><span class="main"><span>Nenhum cardio registrado ainda.</span></span></div>';
  return `<h3 class="sec">Recentes<span class="dot" aria-hidden="true"></span></h3><div class="list">${list}</div>${N ? '<p class="foot0">Registrou errado? Arraste o cardio para a esquerda e toque em Excluir.</p>' : ''}`;
}
function vCardio(){
  const L = layOf();
  const h = L==='crono' ? vCardioCrono() : L==='cartoes' ? vCardioCards() : vCardioToque(), ob = S.cardioLive ? '' : obrigCard(false), i = h.indexOf('</header>');
  return (ob && i>0 ? h.slice(0,i+9)+ob+h.slice(i+9) : h+ob)+cardioRecent();
}
/* 1 · Um toque */
function vCardioToque(){
  const cf = ui.cf, wk = weekMin(), G = S.cardioGoal;
  return header('Cardio', G ? `Semana: ${wk} de ${G} min` : `Semana: ${wk} min`, wk>0 && G ? miniRing(wk/G, `Semana: ${wk} de ${G} minutos`) : '')+exampleNote()+
    `<section class="scn-card"><span class="scn-cap">${LOC_S[cf.local]} · ${INT_L[cf.int].toLowerCase()}</span>${sceneCanvas(cf.local,cf.int)}</section>
    <div class="cq"><span class="flabel">Aparelho</span>${eqGrid('cf-local',cf.local)}
      <span class="flabel">Tempo</span>${timeChips('cf-min',cf.min,'cf-more')}
      ${ui.cfMore ? minStepper('cfmin',cf.min) : ''}
      ${intSeg('cfint',cf.int,true)}
      ${slideStart('cardio','Salvar '+cf.min+' min','csave')}</div>`+weekCompact();
}
/* 2 · Cronômetro */
function vCardioCrono(){
  const L = S.cardioLive, wk = weekMin(), G = S.cardioGoal, ext = `<span class="wk-mini"><span>Semana</span><b>${wk}${G?'/'+G:''}</b></span>`;
  const manual = `<p class="lv-hint">${L?'O tempo vai sozinho pro registro. ':''}Fez sem cronômetro? <button type="button" class="linkbtn" data-a="cmanual">Lançar manual</button></p>`;
  if(!L){
    const cf = ui.cf, g = liveGoal(0);
    return header('Cardio', LOC_S[cf.local]+' · '+INT_L[cf.int].toLowerCase(), ext)+exampleNote()+
      `<section class="card blk-cardio live idle" aria-label="Cronômetro do cardio"><div class="lv-row"><span>Pronto para começar</span><span>Meta ${CARDIO.min} min</span></div>
        <b class="lv-timer">00:00</b><p class="lv-sub">${g.txt}</p><div class="lv-bar"><i style="width:${Math.round(g.p*100)}%"></i></div>
        <div class="lv-scn">${sceneCanvas(cf.local,cf.int)}</div></section>
      <div class="cq"><span class="flabel">Aparelho</span>${eqGrid('cf-local',cf.local)}${intSeg('cfint',cf.int,true)}
      ${slideStart('cardio','Começar '+LOC_S[cf.local].toLowerCase(),'cstart')}${manual}</div>`;
  }
  const ms = cardioElapsed(), g = liveGoal(ms);
  return header('Cardio', LOC_S[L.local]+' · '+INT_L[L.int].toLowerCase(), ext)+exampleNote()+
    `<section class="card blk-cardio live" aria-label="Cardio ao vivo"><div class="lv-row"><span><i class="live-dot${L.run?'':' off'}"></i>${L.run?'Ao vivo':'Pausado'}</span><span>Meta ${CARDIO.min} min</span></div>
      <b class="lv-timer" role="timer" data-tick="cardio">${fmtDur(ms)}</b><p class="lv-sub" data-tick="cgoal">${g.txt}</p><div class="lv-bar"><i data-tick="cbar" style="width:${Math.round(g.p*100)}%"></i></div>
      <div class="lv-scn">${sceneCanvas(L.local,L.int)}</div></section>
    <div class="cq">${intSeg('clint',L.int,true)}
    <div class="row-btns lv-btns"><button type="button" class="btn" data-a="cpause">${ic(L.run?'pause':'play')}${L.run?'Pausar':'Retomar'}</button><button type="button" class="btn lv-end" data-a="cend">${ic('check')}Terminar</button></div>${manual}</div>`;
}
/* 3 · Cartões */
function vCardioCards(){
  const last = S.cardio[S.cardio.length-1];
  const tiles = LOCAIS.map(l => {
    const x = lastOf(l), on = !!(last && last.local===l);
    return `<button type="button" class="eqc${on?' on':''}" data-a="cs-open" data-v="${l}" aria-label="${l}${x?', última vez '+x.min+' minutos '+shortDay(x.date):', ainda sem registro'}${on?', o último que você usou':''}">${on?'<span class="eqc-tag" aria-hidden="true">última</span>':''}${eqIc(l,'eqc-i')}<span class="eqc-t"><b>${LOC_S[l]}</b><span>${x?x.min+' min '+shortDay(x.date):'—'}</span></span></button>`;
  }).join('');
  return header('Cardio','Toque no aparelho que usou')+exampleNote()+weekStrip()+`<div class="eqgrid">${tiles}</div>`;
}
/* folha do cartão (opção 3) e lançamento manual (opção 2) */
function vCardioSheet(){
  const c = ui.cs, man = ui.sheet.k==='cman', nm = c.local==='Outro' ? 'cardio' : LOC_S[c.local].toLowerCase();
  const body = man
    ? `<div class="field"><span class="flabel">Aparelho</span>${eqGrid('cs-local',c.local)}</div><div class="field"><span class="flabel">Tempo</span>${timeChips('cs-min',c.min)}${minStepper('cs-step',c.min)}</div>`
    : `<div class="cs-scn">${sceneCanvas(c.local,c.int)}</div>${minStepper('cs-step',c.min,true)}`;
  return `<div class="sheet-wrap" data-a="closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop(man?'Cardio sem cronômetro':'Registrar cardio', man?'Lançar manual':esc(c.local))}
    ${body}<div class="field"><span class="flabel">Intensidade</span>${intSeg('cs-int',c.int,true)}<p class="zone">${ic('pulse')}<span><b>${ZONA[c.int][0]}</b>: ${ZONA[c.int][1]}.</span></p></div>
    <button type="button" class="btn block cs-save" data-a="cs-save">${ic('check')}Salvar ${c.min} min de ${nm}</button></div></div>`;
}
function openCS(local,k){
  const x = lastOf(local);
  ui.cs = { local, min:x ? x.min : CARDIO.min, int:x && INTENS.includes(x.int) ? x.int : ui.cf.int };
  ui.sheet = {k}; renderSheet(true);
}
function startCardio(){
  if(S.cardioLive) return;
  S.cardioLive = { local:ui.cf.local, int:ui.cf.int, start:Date.now(), acc:0, run:true };
  save(); toast('Cronômetro do cardio ligado. Bom treino.'); vibrate(30);
}
function endCardio(){
  const L = S.cardioLive; if(!L) return;
  const min = Math.max(1, Math.round(cardioElapsed()/60000));
  S.cardioLive = null;
  logCardio(L.local, min, L.int);
}
/* demonstrações dos três jeitos: mini vídeo em SVG animado por CSS, laço de 5 s */
let LAN = 0;
function miniRunner(x,y){
  const leg = n => `<g class="mf-th mf-th${n}"><path class="mf-lm${n===2?' b':''}" d="M0 0V5.6"/><g transform="translate(0 5.6)"><g class="mf-sn mf-sn${n}"><path class="mf-lm${n===2?' b':''}" d="M0 0V5.2"/><path class="mf-sh" d="M-.8 5.6H3"/></g></g></g>`;
  const arm = n => `<g transform="translate(1.6 -9)"><g class="mf-ar mf-ar${n}"><path class="mf-lm${n===2?' b':''}" d="M0 0V4.2L2.6 3"/></g></g>`;
  return `<g transform="translate(${x} ${y})"><g class="mf-bob">${arm(2)}${leg(2)}<path class="mf-tp" d="M0 0L1.6 -9"/><circle class="mf-hd" cx="2.6" cy="-12.4" r="2.5"/><path class="mf-hr" d="M.2 -12.6a2.5 2.5 0 0 1 4.6 -1.2L3.2 -13.4l-1 .4zM.4 -13.6L-2.6 -12.4"/>${leg(1)}${arm(1)}</g></g>`;
}
function miniBike(x,y){
  return `<g transform="translate(${x} ${y})"><path class="mb-fr" d="M-10 13H14M-4 13V9M-4 9L-6 1M-4 9L9 4M-7 2L8 1M14 9L9 1L11 -3M14 13V9M-9 1H-3M11 -3H15"/>
    <circle class="mb-wh" cx="14" cy="9" r="5"/><g transform="translate(14 9)"><path class="mb-sp mb-rot" d="M-3.6 0H3.6"/></g>
    <g transform="translate(-6 -1)"><g class="mc-th mc-th2"><path class="mf-lm b" d="M0 0L5 5.4"/><g transform="translate(5 5.4)"><g class="mc-sn mc-sn2"><path class="mf-lm b" d="M0 0L-1.2 5"/><path class="mf-sh" d="M-2 5.4H1.8"/></g></g></g>
    <path class="mf-tp" d="M0 0L8 -5.6"/><circle class="mf-hd" cx="10.4" cy="-8.4" r="2.5"/><path class="mf-hr" d="M8 -8.8a2.5 2.5 0 0 1 4.6 -1L10.8 -9.6zM8.2 -9.6L5.4 -8.6"/>
    <g class="mc-th mc-th1"><path class="mf-lm" d="M0 0L5 5.4"/><g transform="translate(5 5.4)"><g class="mc-sn mc-sn1"><path class="mf-lm" d="M0 0L-1.2 5"/><path class="mf-sh" d="M-2 5.4H1.8"/></g></g></g>
    <path class="mf-lm" d="M8 -5.6L12.6 -1.6L16 -2.4"/></g></g>`;
}
const laTap = `<g class="la-fg"><circle class="la-rip" r="5"/><circle class="la-fin" r="4.6"/></g>`;
function layThumb(id){
  const n = ++LAN, r = (x,y,w,h,rx,cls) => `<rect class="${cls}" x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}"/>`;
  const t = (x,y,s,cls,txt,anchor) => `<text class="${cls}" x="${x}" y="${y}" font-size="${s}"${anchor?` text-anchor="${anchor}"`:''}>${txt}</text>`;
  const ico = (l,x,y,cls) => `<g transform="translate(${x} ${y}) scale(.42)"><g class="la-ic ${cls||''}">${EQI[l]}</g></g>`;
  const head = t(8,16,9,'la-h','Cardio')+`<circle class="la-g" cx="38.4" cy="15" r="1.3"/>`;
  let g = '';
  if(id==='toque'){
    g = head+`<circle class="la-rt" cx="86" cy="12" r="5.5"/><circle class="la-rf la1-ring" cx="86" cy="12" r="5.5" stroke-dasharray="34.6" transform="rotate(-90 86 12)"/>`;
    g += r(6,22,88,32,7,'la-b')+`<path class="la-eqp" d="M22 48.5H68M68 48.5L73 32M73 32L66 34"/>`+miniRunner(44,37.5);
    LOCAIS_ALL.forEach((l,i) => { g += r(6+i*15,58,13,14,4,i===0?'la-b la1-eq0':'la-b')+ico(l,7.5+i*15,60,i===0?'la1-ic0':''); });
    TCHIPS.forEach((m,i) => { g += r(6+i*22.5,76,20.5,12,4,i===2?'la-b la1-ch2':'la-b')+t(16.25+i*22.5,84.4,7,i===2?'la-ct la1-ct2':'la-ct',m,'middle'); });
    g += r(6,92,88,12,6,'la-b')+r(35.7,93,28.6,10,5,'la-w la1-seg')+t(20.7,100,5.8,'la-st','Leve','middle')+t(50,100,5.8,'la-st la1-sm','Média','middle')+t(79.3,100,5.8,'la-st la1-sf','Forte','middle');
    g += r(6,110,88,16,8,'la-b')+`<rect class="la-gf la1-fill" x="6" y="110" width="88" height="16" rx="8"/>`+t(56,120.2,6.2,'la-tx la1-stx','Salvar 30 min ›››','middle');
    g += `<g class="la1-knob"><g transform="translate(14 118)"><g class="la1-pop"><circle class="la-g" r="6.6"/><path class="la-ck" d="M-2.6 .2L-.8 2L2.8 -1.8"/></g></g></g>`;
    g += r(6,134,88,3,1.5,'la-b')+`<rect class="la-g la1-wk" x="6" y="134" width="88" height="3" rx="1.5"/>`+t(6,145,5.5,'la-mu','Semana 50 → 80 de 120 min');
    g += t(58,108,7.5,'la-plus la1-plus','+30 min','middle');
    g += `<g class="la-fgw la1-fg">${laTap}</g>`;
  } else if(id==='crono'){
    g = head+t(93,15,6,'la-tx','50/120','end');
    g += r(6,22,88,84,9,'la-g')+`<circle class="la-ink" cx="12.4" cy="30" r="1.4"/>`+t(16,32,5.6,'la-ink la-up la2-rdy','Pronto','start')+t(16,32,5.4,'la-ink la-up la2-live','Ao vivo','start')+t(89,32,5.4,'la-ink la-up','Meta 25 min','end');
    g += `<text class="la-ink la-tm" x="10" y="52" font-size="20" data-clk="up" data-a0=".12" data-a1=".7">18:42</text>`;
    g += r(11,57,78,3,1.5,'la-ink2')+`<rect class="la-ink la2-bar" x="11" y="57" width="78" height="3" rx="1.5"/>`;
    g += `<circle class="la-ink2" cx="22" cy="80" r="7"/><path class="la-ink2s" d="M22 80V100"/><circle class="la-ink2" cx="78" cy="84" r="5"/><path class="la-ink2s" d="M78 84V100"/><path class="la-ink2s" d="M6 100.5H94"/>`;
    g += `<g class="la-onink">${miniRunner(50,89)}</g>`;
    g += `<g class="la2-start">${r(6,111,88,15,7.5,'la-g')}<path class="la-ink" d="M38 115.2v6.6l5.4-3.3z"/>${t(55,121,6.4,'la-ink la-b8','Começar','middle')}</g>`;
    g += `<g class="la2-btns">${r(6,111,43,15,7.5,'la-b')+t(27.5,121,6.2,'la-tx la-b8','Pausar','middle')+r(51,111,43,15,7.5,'la-w')+t(72.5,121,6.2,'la-wt la-b8','Terminar','middle')}</g>`;
    g += t(50,139,6,'la-mu','Lançar manual','middle');
    g += `<g class="la2-done">${r(6,22,88,84,9,'la-dk')}<circle class="la-g" cx="50" cy="56" r="10"/><path class="la-ck2" d="M45.6 56.4L48.6 59.4L54.6 53"/>${t(50,80,7.4,'la-cream la-b8','25 min salvos','middle')}</g>`;
    g += `<rect class="la-clock" x="0" y="0" width="1" height="1" opacity="0"/>`;
    g += `<g class="la-fgw la2-fg">${laTap}</g>`;
  } else {
    g = head+r(6,22,88,12,6,'la-b');
    for(let i=0;i<7;i++) g += `<circle class="${i===1||i===2?'la-g':'la-dot'}" cx="${12.5+i*7.6}" cy="28" r="2.5"/>`;
    g += t(91,30.4,6.2,'la-tx la-b8','51/120','end');
    LOCAIS_ALL.forEach((l,i) => { const x = 6+(i%3)*30, y = 38+Math.floor(i/3)*33, on = i===1;
      g += r(x,y,28,31,6,on?'la-b la3-t1':'la-b')+ico(l,x+3,y+3,on?'la3-ic1':'')+t(x+3.4,y+26.5,5.6,on?'la-tx la-b8 la3-tt1':'la-tx la-b8',LOC_S[l]); });
    g += t(6,115,7,'la-h','Recentes')+r(6,119,88,12,5,'la-b')+r(6,133,88,12,5,'la-b');
    g += `<g class="la3-toast">${r(20,128,60,13,6.5,'la-g')}<path class="la-ck" d="M27.5 134.6L29.3 136.4L32.9 132.6"/>${t(55,136.8,5.8,'la-ink la-b8','30 min salvos','middle')}</g>`;
    g += `<rect class="la-scrim la3-scrim" x="0" y="0" width="100" height="150"/>`;
    g += `<g class="la3-sheet">${r(1,56,98,100,12,'la-sh')}${r(44,59,12,1.6,.8,'la-shg')}${t(8,71,8,'la-sht la-b9','Bicicleta')}${r(8,75,84,29,6,'la-shp')}${miniBike(44,86)}
      ${r(8,107,84,14,7,'la-shc')}<circle class="la-shw" cx="15" cy="114" r="5.2"/><path class="la-shs" d="M12.6 114H17.4"/><circle class="la-shw la3-plus" cx="85" cy="114" r="5.2"/><path class="la-shs" d="M82.6 114H87.4M85 111.6V116.4"/>
      ${t(50,117.4,9,'la-sht la-b9 la3-v25','25<tspan font-size="5"> min</tspan>','middle')}${t(50,117.4,9,'la-sht la-b9 la3-v30','30<tspan font-size="5"> min</tspan>','middle')}
      ${r(8,125,84,14,7,'la-shb')}${t(50,133.6,5.8,'la-cream la-b8 la3-v25','Salvar 25 min de bike','middle')}${t(50,133.6,5.8,'la-cream la-b8 la3-v30','Salvar 30 min de bike','middle')}</g>`;
    g += `<g class="la-fgw la3-fg">${laTap}</g>`;
  }
  return `<span class="lay-anim la-${id}"><svg viewBox="0 0 100 150" aria-hidden="true"><defs><clipPath id="lac${n}"><rect x="1" y="1" width="98" height="148" rx="14"/></clipPath></defs><rect class="la-fr" x="1" y="1" width="98" height="148" rx="14"/><g clip-path="url(#lac${n})">${g}</g><rect class="la-frl" x="1" y="1" width="98" height="148" rx="14"/></svg></span>`;
}
function layCard(o,on,action,tag,th){
  return `<button type="button" role="radio" aria-checked="${on}" class="lay${on?' on':''}" data-a="${action}" data-v="${o.id}">${(th||layThumb)(o.id)}<span class="lay-t"><b><span class="lay-ck" aria-hidden="true">${ic('check')}</span>${o.n}${tag?`<em>${tag}</em>`:''}</b><span>${o.s}</span></span></button>`;
}
/* só anima o que está visível; o relógio do cronômetro lê o tempo da animação CSS para ficar em sincronia */
const LA = { io:null, raf:0 };
function laClock(){
  LA.raf = 0;
  const live = document.querySelectorAll('.lay-anim.play [data-clk]');
  if(!live.length || document.visibilityState==='hidden') return;
  live.forEach(tm => {
    const root = tm.closest('.lay-anim'), ck = root.querySelector('.la-clock'), a = ck && ck.getAnimations ? ck.getAnimations()[0] : null;
    const t = a && a.currentTime!=null ? (a.currentTime%5000)/5000 : (performance.now()%5000)/5000, a0 = +tm.dataset.a0, a1 = +tm.dataset.a1;
    let txt;
    if(tm.dataset.clk==='up'){ const sec = t<a0 || t>.97 ? 0 : t>=a1 ? 1500 : Math.floor((t-a0)/(a1-a0)*1500); txt = pad(Math.floor(sec/60))+':'+pad(sec%60); }
    else { const sec = t<a0 ? 60 : t>=a1 ? 0 : Math.ceil(60-(t-a0)/(a1-a0)*60); txt = Math.floor(sec/60)+':'+pad(sec%60); }
    if(tm.textContent!==txt) tm.textContent = txt;
  });
  LA.raf = requestAnimationFrame(laClock);
}
function setPlay(el,on){ el.classList.toggle('play', on); if(on && !LA.raf) LA.raf = requestAnimationFrame(laClock); }
function initLayAnim(){
  if(LA.io){ LA.io.disconnect(); LA.io = null; }
  if(RM()) return;
  const els = document.querySelectorAll('.lay-anim');
  if(!('IntersectionObserver' in window)){ els.forEach(el => setPlay(el,true)); return; }
  LA.io = new IntersectionObserver(es => es.forEach(e => { e.target._vis = e.isIntersecting; setPlay(e.target, e.isIntersecting && document.visibilityState!=='hidden'); }), {threshold:.25});
  els.forEach(el => LA.io.observe(el));
}
document.addEventListener('visibilitychange', () => document.querySelectorAll('.lay-anim').forEach(el => setPlay(el, !!el._vis && document.visibilityState!=='hidden' && !RM())));
function renderPick(){
  const el = document.getElementById('pick');
  if(ui.pstep==null) ui.pstep = !PREF.treinoLayout ? 1 : !PREF.cardioLayout ? 2 : 0;
  const st = ui.pstep;
  if(!st){ if(el){ el.remove(); document.body.classList.toggle('locked', !!ui.sheet); } if(AFTER==='meus' && ACCT){ AFTER = null; if(ACCT.mode==='self') mtOpen(); } return; }
  const steps = `<div class="pk-steps" aria-label="Passo ${st} de 2"><span class="${st===1?'on':'ok'}"><b>${st===1?'1':ic('check')}</b>Treino</span><i aria-hidden="true"></i><span class="${st===2?'on':''}"><b>2</b>Cardio</span></div>`;
  const top = `<div class="pk-top"><span class="pk-logo" aria-hidden="true">${logo()}</span>${st===2 && ui.pback ? `<button type="button" class="linkbtn pk-back" data-a="pk-back">${ic('chevL')}Voltar</button>` : ''}</div>`;
  let html;
  if(st===2){ const sel = ui.pick || PREF.cardioLayout;
    html = top+steps+`<p class="eyebrow">Quase lá</p><h1 id="pk-t">Como você prefere registrar o cardio?</h1><p class="muted">Escolha um jeito. Dá para trocar quando quiser no seu perfil.</p>
      <div class="pk-list" role="radiogroup" aria-labelledby="pk-t">${LAYS.map(o => layCard(o, sel===o.id, 'clay')).join('')}</div>
      <button type="button" class="btn primary block" data-a="clay-go"${sel?'':' disabled'}>Começar${ic('chev')}</button>`; }
  else { const sel = ui.tpick;
    const sg = tlSuggest(), opts = sg.ids.map(id => TLAYS.find(o => o.id===id)).concat(TLAYS.filter(o => !sg.ids.includes(o.id))); // os 4 visuais, o sugerido primeiro
    html = top+steps+`<p class="eyebrow">Antes de começar</p><h1 id="pk-t">Como você prefere registrar o treino?</h1><p class="muted">Escolha um jeito. Dá para trocar quando quiser no seu perfil.</p>
      <div class="pk-list t4" role="radiogroup" aria-labelledby="pk-t">${opts.map((o,i) => layCard(o, sel===o.id, 'tlay', i===0?'Sugerido':'', tlThumb)).join('')}</div>
      <button type="button" class="btn primary block" data-a="tlay-go"${sel?'':' disabled'}>Continuar${ic('chev')}</button>`; }
  let w = el;
  if(!w){ w = document.createElement('div'); w.id = 'pick'; w.setAttribute('role','dialog'); w.setAttribute('aria-modal','true'); w.setAttribute('aria-labelledby','pk-t'); document.body.appendChild(w); }
  w.className = 'pick st'+st; w.innerHTML = `<div class="pk-in">${html}</div>`; w.scrollTop = 0; document.body.classList.add('locked');
  initLayAnim();
}

