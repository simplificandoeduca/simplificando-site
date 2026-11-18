/* ---------- regras ---------- */
function findEx(id){ for(const t of LETTERS){ const e = PLAN[t].ex.find(x => x.id===id); if(e) return {e,t}; } return null; }
function nextTreino(){ const t = nextTreino0(); return PLAN[t] ? t : LETTERS[0]; }
function nextTreino0(){
  if(S.session) return S.session.t;
  const h = S.history[S.history.length-1];
  if(h && h.date===today()) return h.t;
  if(ownPlan()){ const wd = (new Date().getDay()+6)%7, l = LETTERS.find(x => (PLAN[x].dias||[]).includes(wd)); if(l) return l; }
  if(!h) return LETTERS[0] || 'A';
  return LETTERS[(LETTERS.indexOf(h.t)+1)%LETTERS.length];
}
/* conta com personal, mas o treino ainda não foi montado no painel */
const noPlan = () => !LETTERS.length;
function noPlanCard(){
  if(ACCT && ACCT.mode==='self') return `<section class="card wcard"><div class="card-h"><span class="label">${ic('dumbbell')}Treino</span><span class="pill">A montar</span></div>
    <div><h2 class="big">Monte seu treino<span class="dot" aria-hidden="true"></span></h2><p class="muted">Dê um nome, adicione os exercícios e, se quiser, escolha os dias. Ou deixe a IA montar para você.</p></div>
    <button type="button" class="btn primary block" data-a="mt-open-sheet">${ic('plus')}Montar meu treino</button>
    <button type="button" class="btn block" data-a="ia-open">${ic('spark')}Deixar a IA montar</button></section>`;
  return `<section class="card wcard"><div class="card-h"><span class="label">${ic('dumbbell')}Treino</span><span class="pill">Em preparação</span></div>
    <div><h2 class="big">Seu treino está sendo montado<span class="dot" aria-hidden="true"></span></h2><p class="muted">Assim que seu personal terminar, ele aparece aqui sozinho. Enquanto isso, dá para registrar água e cardio.</p></div></section>`;
}
const lowReady = c => !!c && (c.energia<=2 || c.dor>=3 || c.sono<=5);
const sonoTxt = v => v<=5 ? '5h ou menos' : v>=9 ? '9h ou mais' : v+'h';

/* progressão dupla: fechou o topo da faixa em todas as séries, sobe a carga; senão, busca mais repetições */
function suggest(id){
  const e = findEx(id).e, L = lastFor(id), rg = range(e.r);
  if(!L) return e.kg ? {kg:e.kg, tone:'new', why:'Carga indicada pelo seu personal para começar. Se sobrar muito ou faltar, ajuste.'} : {kg:null, tone:'new', why:'Primeira vez: escolha uma carga em que sobrem 2 ou 3 repetições no fim da série.'};
  if(lowReady(S.checkin[today()])) return {kg:L.kg, tone:'hold', why:'Seu check-in de hoje pede um dia mais leve. Repita a carga da última vez e deixe 2 repetições sobrando.'};
  const mn = Math.min(...L.reps), rir = L.rir && L.rir.length ? L.rir.reduce((a,b)=>a+b,0)/L.rir.length : 1, st = step(L.kg);
  if(mn>=rg[1]){
    const up = rir>=3 ? 2*st : st;
    return {kg:L.kg+up, up, tone:'up', why:'Você fechou '+rg[1]+' repetições em todas as séries'+(rir>=3?' e ainda sobrou bastante':'')+'. Suba '+fmtKg(up)+' kg e volte para '+rg[0]+' repetições.'};
  }
  if(mn<rg[0]) return {kg:L.kg, tone:'keep', why:'Uma série ficou abaixo de '+rg[0]+' repetições. Repita '+fmtKg(L.kg)+' kg até fechar a faixa inteira.'};
  return {kg:L.kg, tone:'keep', why:'Mesma carga, buscando 1 repetição a mais por série. Quando todas chegarem a '+rg[1]+', a carga sobe.'};
}
function repTarget(id,i){
  const e = findEx(id).e, rg = range(e.r), L = lastFor(id), sg = suggest(id);
  if(!L || sg.tone==='up' || sg.tone==='new') return rg[0];
  const lr = L.reps[i]!=null ? L.reps[i] : L.reps[L.reps.length-1];
  return sg.tone==='hold' ? lr : Math.min(rg[1], lr+1);
}
/* carga de partida: o peso da última série feita na última vez; sem histórico, a sugestão */
function startKg(id){
  const L = lastFor(id);
  if(L) return L.lastKg!=null ? L.lastKg : L.kg;
  return suggest(id).kg;
}
/* dia do treino: enquanto um treino está aberto, tudo fica no dia em que ele começou (treino que passa da meia-noite não some) */
function tDia(){ return (typeof S!=='undefined' && S && S.session && S.session.d) || today(); }
function getLog(id){
  const t = tDia(); S.logs[t] = S.logs[t] || {};
  if(!S.logs[t][id]){
    const e = findEx(id).e, k0 = startKg(id);
    S.logs[t][id] = Array.from({length:e.s}, () => ({kg:k0!=null?fmtKg(k0):'', reps:'', ok:false, rir:null}));
  }
  return fitLog(id, S.logs[t][id]);
}
/* as séries do dia acompanham o plano: se o treino foi editado depois de abrir o exercício,
   completa as que faltam e tira as que sobraram (só as não feitas e que não foram adicionadas na hora) */
function fitLog(id, L){
  const f = findEx(id), n = f && f.e ? Math.max(1, +f.e.s||1) : 0; if(!n) return L;
  while(L.length>n){ const z = L[L.length-1]; if(!z || z.ok || z.add) break; L.pop(); }
  if(L.length<n){ const l = L[L.length-1], k0 = l && l.kg!=='' ? null : startKg(id); while(L.length<n) L.push({kg:l && l.kg!=='' ? l.kg : (k0!=null ? fmtKg(k0) : ''), reps:'', ok:false, rir:null}); }
  return L;
}
const logOf = id => { const L = (S.logs[tDia()]||{})[id]; return L ? fitLog(id, L) : []; };
const doneSets = id => logOf(id).filter(x => x.ok).length;
const totalSets = t => PLAN[t].ex.reduce((a,e) => a+exN(e), 0);
const doneSetsPlan = t => PLAN[t].ex.reduce((a,e) => a+doneSets(e.id), 0);
const liveVol = t => PLAN[t].ex.reduce((a,e) => a+logOf(e.id).filter(x=>x.ok).reduce((b,x) => b+num(x.kg)*int(x.reps), 0), 0);
function todayBest(id, except){
  let v = 0, i = -1;
  logOf(id).forEach((x,j) => { if(!x.ok || j===except) return; const w = e1(num(x.kg),int(x.reps)); if(w>v){ v = w; i = j; } });
  return {v,i};
}
const livePRs = t => PLAN[t].ex.filter(e => S.pr[e.id] && todayBest(e.id).v > S.pr[e.id].e1+0.05).map(e => e.id);
function elapsed(){ const s = S.session; if(!s) return 0; return s.acc + (s.run ? Date.now()-s.start : 0); }
const cardioOn = k => S.cardio.filter(c => c.date===k).reduce((a,c) => a+c.min, 0);
function monday(k){ const d = parse(k); d.setDate(d.getDate()-(d.getDay()+6)%7); return dk(d); }
function weekDays(off){ const m = addDays(monday(today()), off*7); return Array.from({length:7},(_,i) => addDays(m,i)); }
const weekTrainCount = off => { const ds = weekDays(off); return S.history.filter(h => ds.includes(h.date)).length; };
function streak(){ let n = weekTrainCount(0)>=streakMin() ? 1 : 0; for(let w=-1; w>-80; w--){ if(weekTrainCount(w)>=streakMin()) n++; else break; } return n; }
function waterPace(ml){
  const exp = S.waterGoal*Math.min(1,Math.max(0,(nowMin()-7*60)/(14*60)));
  return {exp, ok: ml>=exp-250};
}
const RINI = () => Number.isFinite(+S.remind.ini) ? +S.remind.ini : 8*60, RFIM = () => Number.isFinite(+S.remind.fim) ? +S.remind.fim : 22*60;
const hmTxt = m => (m%60 ? Math.floor(m/60)+'h'+String(m%60).padStart(2,'0') : Math.floor(m/60)+'h');
const hhmm = m => String(Math.floor(m/60)).padStart(2,'0')+':'+String(m%60).padStart(2,'0');
function nextRemind(){
  if(!S.remind.on) return null;
  if((S.water[today()]||0)>=S.waterGoal) return -2;
  const L = S.wlog[today()]||[], last = L.length ? L[L.length-1].m : null;
  let n = last==null ? RINI() : last+S.remind.every;
  if(n<RINI()) n = RINI();
  if(n>RFIM()) return -1;
  return n;
}
function remindTxt(){
  const n = nextRemind();
  if(n==null) return '';
  if(n===-2) return 'Lembretes pausados até amanhã';
  if(n===-1) return 'Lembretes voltam amanhã às '+hhmm(RINI());
  return n<=nowMin() ? 'Lembrete: hora de beber água' : 'Próximo lembrete às '+hm(n);
}

/* ---------- ícones (traço) ---------- */
const IC = {
  up:'<path d="M6 14.5l6-6 6 6"/>',
  share:'<path d="M12 14.5V3.5M7.5 8L12 3.5 16.5 8M5 11.5v8h14v-8"/>',
  dl:'<path d="M12 3.5v11M7.5 10L12 14.5 16.5 10M5 19.5h14"/>',
  down:'<path d="M6 9.5l6 6 6-6"/>',
  spark:'<path d="M12 3.5l1.8 5.2 5.2 1.8-5.2 1.8L12 17.5l-1.8-5.2-5.2-1.8 5.2-1.8z"/><path d="M18.5 3v3.4M16.8 4.7h3.4"/>',
  search:'<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
  trash:'<path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13"/>',
  sun:'<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6L6 6M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4L6 18M18 6l1.4-1.4"/>',
  dumbbell:'<path d="M6.5 6.5v11M17.5 6.5v11M3.5 9.5v5M20.5 9.5v5M6.5 12h11"/>',
  pulse:'<path d="M2.5 12.5h4l2.2-5 3.6 10 2.4-5h6.8"/>',
  chart:'<path d="M5 19.5v-7M10.5 19.5v-14M16 19.5v-9.5M21.5 19.5H2.5"/>',
  drop:'<path d="M12 3.2s6.2 6.6 6.2 11.2a6.2 6.2 0 0 1-12.4 0C5.8 9.8 12 3.2 12 3.2z"/>',
  play:'<path d="M8 5.5v13l10.5-6.5z" fill="currentColor"/>',
  pause:'<path d="M8.5 5.5v13M15.5 5.5v13"/>',
  check:'<path d="M5 12.5l4.5 4.5L19 7.5"/>',
  chev:'<path d="M9.5 5.5l6.5 6.5-6.5 6.5"/>',
  chevL:'<path d="M14.5 5.5L8 12l6.5 6.5"/>',
  x:'<path d="M6 6l12 12M18 6L6 18"/>',
  plus:'<path d="M12 5v14M5 12h14"/>',
  minus:'<path d="M5 12h14"/>',
  timer:'<circle cx="12" cy="13.5" r="7.5"/><path d="M12 9.5v4l2.5 2M9.5 2.5h5"/>',
  undo:'<path d="M9 14.5l-5-5 5-5"/><path d="M4 9.5h10a6 6 0 0 1 0 12h-3"/>',
  pin:'<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
  flag:'<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
  trophy:'<path d="M8 4h8v5a4 4 0 0 1-8 0zM8 6H5v1a3 3 0 0 0 3 3M16 6h3v1a3 3 0 0 1-3 3M12 13v4M8.5 20.5h7M10 17h4l.5 3.5h-5z"/>',
  bell:'<path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15zM10 21h4"/>',
  eye:'<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
  eyeOff:'<path d="M4 4l16 16M9.9 5.8A9.7 9.7 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3 3.7M6.5 7.6A16.5 16.5 0 0 0 2.5 12S6 18.5 12 18.5a9.4 9.4 0 0 0 4.2-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
  lock:'<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
  camera:'<path d="M4 8h3l1.6-2.5h6.8L17 8h3v11H4z"/><circle cx="12" cy="13.2" r="3.6"/>',
  gear:'<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
  swap:'<path d="M7 4.5L3.5 8 7 11.5M3.5 8h13M17 12.5l3.5 3.5-3.5 3.5M20.5 16h-13"/>',
  moon:'<path d="M19.5 14.5A8 8 0 1 1 9.5 4.5a6.5 6.5 0 0 0 10 10z"/>',
  bolt:'<path d="M13 2.5L5 13.5h6l-1 8 8-11h-6z"/>',
  flame:'<path d="M12 21.5a6.5 6.5 0 0 0 6.5-6.5c0-4.5-4-6.5-4.5-11-2.5 2-5 5-5 8a3 3 0 0 1-1.5-2.5C6.2 11 5.5 12.8 5.5 15a6.5 6.5 0 0 0 6.5 6.5z"/>',
  cal:'<rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
  msg:'<path d="M4.5 5.5h15a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H10l-4.5 3.5v-3.5h-1a1 1 0 0 1-1-1v-10a1 1 0 0 1 1-1z"/>',
  layers:'<path d="M12 4l8.5 4.5L12 13 3.5 8.5z"/><path d="M3.5 13L12 17.5 20.5 13"/>',
  edit:'<path d="M4.5 19.5l1-4L15.5 5.5l3 3-10 10z"/>',
  body:'<circle cx="12" cy="5" r="2.2"/><path d="M5 9.5l7 1.5 7-1.5M12 11v4.5l-3 6M12 15.5l3 6"/>',
  skip:'<path d="M5.5 5.5l9 6.5-9 6.5z"/><path d="M18.5 5.5v13"/>',
  route:'<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18h7a3 3 0 0 0 0-6h-7a3 3 0 0 1 0-6h7"/>'
};
const ic = (n,cls) => '<svg class="i '+(cls||'')+'" viewBox="0 0 24 24" aria-hidden="true">'+IC[n]+'</svg>';

const S_PATH = 'M34.4 1.2C54.1 1.2 65.4 -7.5 65.4 -22.8C65.4 -34.4 58.3 -40.8 41.6 -43.9L35.7 -45C28.2 -46.3 24.9 -48.2 24.9 -51.7C24.9 -55.3 28.9 -57.9 34.6 -57.9C40.7 -57.9 44.7 -54.8 45.2 -49.7H63.9C63.4 -64.9 52.4 -73.9 34.2 -73.9C16.6 -73.9 4.5 -64.5 4.5 -50.7C4.5 -39.9 12.1 -32.4 26.2 -29.7L33.4 -28.4C41.6 -26.8 45.3 -24.8 45.3 -21.2C45.3 -17.4 41 -14.8 34.6 -14.8C27.2 -14.8 22.6 -18.3 22.3 -24.2H3.1C3.1 -7.9 14.2 1.2 34.4 1.2Z';
const DOT_PATH = 'M80.8 0.9C86.4 0.9 90.9 -3.6 90.9 -9.2C90.9 -14.8 86.4 -19.4 80.8 -19.4C75.2 -19.4 70.6 -14.8 70.6 -9.2C70.6 -3.6 75.2 0.9 80.8 0.9Z';
function logo(){
  const arc = (d,c) => '<path d="'+d+'" fill="none" stroke="'+c+'" stroke-width="3.8"/>';
  return '<svg class="logo" viewBox="-50 -50 100 100" role="img" aria-label="Simplificando"><circle r="50" fill="#0F1712"/>'+
    arc('M0 -44.3A44.3 44.3 0 0 1 44.3 0.31','#B794FF')+arc('M44.3 0A44.3 44.3 0 0 1 -0.31 44.3','#C8F542')+
    arc('M0 44.3A44.3 44.3 0 0 1 -44.3 -0.31','#FF9A2E')+arc('M-44.3 0A44.3 44.3 0 0 1 0.31 -44.3','#FF4646')+
    '<g transform="translate(-1 0) scale(0.5593) translate(-47.03 36.38)"><path d="'+S_PATH+'" fill="#F6F5EF"/><path d="'+DOT_PATH+'" fill="#F6F5EF"/></g></svg>';
}
const WAVE = '<svg class="wave-svg" viewBox="0 0 1080 300" preserveAspectRatio="none" aria-hidden="true"><path d="M-40 236C194 -71 410 -71 562 144S886 374 1120 67" fill="none" stroke-width="44" vector-effect="non-scaling-stroke"/></svg>';
/* a onda anda sozinha por um relógio único (não pelo SVG): redesenhar a tela, abrir janelas ou tocar em botões não para nem reinicia */
const WAVE_K = [[-40,236,194,-71,410,-71,562,144,886,374,1120,67],[-40,140,210,-10,420,40,562,170,900,330,1120,150],[-40,190,230,330,430,290,562,150,880,-30,1120,110]];
const WAVE_T0 = performance.now();
function waveD(t){
  const q = ((t/16000)%1+1)%1*3, i = Math.floor(q), f = q-i, e = .5-.5*Math.cos(Math.PI*f), A = WAVE_K[i], B = WAVE_K[(i+1)%3];
  const v = A.map((a,k) => (a+(B[k]-a)*e).toFixed(1));
  return 'M'+v[0]+' '+v[1]+'C'+v[2]+' '+v[3]+' '+v[4]+' '+v[5]+' '+v[6]+' '+v[7]+'S'+v[8]+' '+v[9]+' '+v[10]+' '+v[11];
}
let waveRaf = 0;
function waveTick(){
  waveRaf = 0;
  const ps = document.querySelectorAll('svg.wave-svg path'); if(!ps.length || document.visibilityState==='hidden') return;
  const d = waveD(performance.now()-WAVE_T0); ps.forEach(p => p.setAttribute('d', d));
  if(!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) waveRaf = requestAnimationFrame(waveTick);
}
function waveSync(){ if(!waveRaf) waveTick(); else { const d = waveD(performance.now()-WAVE_T0); document.querySelectorAll('svg.wave-svg path').forEach(p => p.setAttribute('d', d)); } }
try { new MutationObserver(() => waveSync()).observe(document.documentElement, {childList:true, subtree:true}); } catch(e){ /* sem observador */ }
document.addEventListener('visibilitychange', () => { if(document.visibilityState==='visible') waveSync(); });

/* ---------- pedaços de interface ---------- */
const header = (title,sub,extra) => `<header class="top"><div class="top-txt"><p class="eyebrow">${sub}</p><h1>${title}<span class="dot" aria-hidden="true"></span></h1></div><div class="top-act">${extra||''}<button type="button" class="btn round theme-btn" data-a="theme" aria-label="${curTheme()==='dark'?'Mudar para modo claro':'Mudar para modo escuro'}">${ic(curTheme()==='dark'?'sun':'moon')}</button><button type="button" class="btn round theme-btn prof-btn" data-a="profile" aria-label="Abrir seu perfil e os ajustes">${ic('gear')}</button></div></header><div class="wave-strip" aria-hidden="true">${WAVE}</div>`;
function ring(key,pct,big,small){
  const r = 54, c = 2*Math.PI*r, p = Math.min(1,Math.max(0,pct));
  return `<div class="ring"><svg viewBox="0 0 132 132" aria-hidden="true"><circle class="ring-track" cx="66" cy="66" r="${r}"/><circle class="ring-fill" cx="66" cy="66" r="${r}" stroke-dasharray="${c.toFixed(1)}" stroke-dashoffset="${(c*(1-p)).toFixed(1)}" transform="rotate(-90 66 66)" data-ring="${key}" data-p="${p.toFixed(4)}"/></svg><div class="ring-c">${big}<span>${small}</span></div></div>`;
}
const tw = (key,v,f,txt) => `<b data-tw="${key}" data-v="${v}" data-f="${f}">${txt}</b>`;
const bar = p => `<div class="bar" role="presentation"><i style="width:${Math.round(Math.min(1,Math.max(0,p))*100)}%"></i></div>`;
function seg(name,opts,cur,labels,aria){
  return `<div class="seg" role="group"${aria?` aria-label="${aria}"`:''}>${opts.map((o,i) => `<button type="button" class="${o===cur?'on':''}" aria-pressed="${o===cur}" data-a="${name}" data-v="${o}">${labels?labels[i]:o}</button>`).join('')}</div>`;
}
function exampleNote(){
  if(!S.example) return '';
  const armed = ui.arm==='fresh';
  return `<div class="note"><div><b>Dados de exemplo</b><span>Explore à vontade.</span></div><button type="button" class="btn small ${armed?'armed':'ghost'}" data-a="fresh">${armed?'Toque de novo para limpar':'Começar do zero'}</button></div>`;
}

