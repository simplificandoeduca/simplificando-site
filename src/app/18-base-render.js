/* ---------- render ---------- */
let $app, $sheet, $rest, $toast, $tabs, $fx, toastT, lpT = 0, lpAt = 0, lpXY = null;
const TABS = [['hoje','Hoje','sun'],['treino','Treino','dumbbell'],['cardio','Cardio','pulse'],['agua','Água','drop'],['evo','Evolução','chart']];
const FMT = { m:v => Math.round(v)+' min', pct:v => Math.round(v)+'%', ml:v => fmtN(v)+' ml', L:v => fmtL(v), i:v => fmtN(v), t:v => (Math.round(v*10)/10).toFixed(1).replace('.',',') };

function runTweens(root){
  const rm = RM();
  root.querySelectorAll('[data-tw]').forEach(el => {
    const k = el.dataset.tw, to = +el.dataset.v, f = FMT[el.dataset.f] || FMT.i, from = k in ui.tw ? ui.tw[k] : 0;
    ui.tw[k] = to;
    if(rm || from===to) return;
    const t0 = performance.now(), D = 700;
    el.textContent = f(from);
    const tick = now => { const p = Math.min(1,(now-t0)/D), q = 1-Math.pow(1-p,3); el.textContent = f(from+(to-from)*q); if(p<1 && el.isConnected) requestAnimationFrame(tick); };
    requestAnimationFrame(tick);
  });
  root.querySelectorAll('[data-lvl]').forEach(el => {
    const k = 'l-'+el.dataset.lvl, to = +el.dataset.y, from = k in ui.tw ? ui.tw[k] : 292;
    ui.tw[k] = to;
    if(rm || from===to) return;
    el.style.transition = 'none'; el.style.transform = `translateY(${from}px)`;
    el.getBoundingClientRect();
    requestAnimationFrame(() => { el.style.transition = ''; el.style.transform = `translateY(${to}px)`; });
  });
  root.querySelectorAll('[data-ring]').forEach(el => {
    const k = 'r-'+el.dataset.ring, to = +el.dataset.p, from = k in ui.tw ? ui.tw[k] : 0;
    ui.tw[k] = to;
    if(rm || from===to) return;
    const c = +el.getAttribute('stroke-dasharray');
    el.style.transition = 'none'; el.style.strokeDashoffset = c*(1-from);
    el.getBoundingClientRect();
    requestAnimationFrame(() => { el.style.transition = ''; el.style.strokeDashoffset = c*(1-to); });
  });
}
/* ---------- robô de testes (pasta testes/): acesso ao estado do app, só no computador de teste (127.0.0.1, localhost ou arquivo); no site não existe ---------- */
if(TESTE_LOCAL) window.__simp = {
  get S(){ return S; }, set S(v){ S = v; }, get ui(){ return ui; }, get PREF(){ return PREF; }, get PLAN(){ return PLAN; }, get ACCT(){ return ACCT; }, get LETTERS(){ return LETTERS; },
  render, renderSheet, closeSheet, save, tidy, applyAccount, startSession, finish, getLog, logOf, exN, confirmSet, markAll, esqAsk, mtOpen, lbHoras, lbSubTxt, lbAPartir, today, daysAgo, addDays, nextTreino,
  lbSync: () => { LBC = ''; lbSync(); }, setLay: (t, c) => { PREF.treinoLayout = t; if(c!==undefined) PREF.cardioLayout = c; else if(!PREF.cardioLayout) PREF.cardioLayout = 'toque'; savePref(); }
};
function render(){
  $app.innerHTML = ({hoje:vHoje,treino:vTreino,cardio:vCardio,agua:vAgua,evo:vEvo})[ui.tab]();
  // 1ª vez na aba Treino (ou Cardio): escolher o visual do treino e do cardio
  if(ACCT && (ui.tab==='treino' || ui.tab==='cardio') && (!PREF.treinoLayout || !PREF.cardioLayout) && !ui.sheet && !document.getElementById('pick') && !document.getElementById('login')){ ui.pstep = null; setTimeout(renderPick, 0); }
  $app.dataset.tab = ui.tab;
  $app.classList.remove('enter');
  if(ui.enter){ void $app.offsetWidth; $app.classList.add('enter'); ui.enter = false; }
  const i = TABS.findIndex(t => t[0]===ui.tab);
  $tabs.style.setProperty('--ti', i);
  $tabs.querySelectorAll('.tab').forEach((b,j) => { b.classList.toggle('on', j===i); if(j===i) b.setAttribute('aria-current','page'); else b.removeAttribute('aria-current'); });
  runTweens($app);
  initAnim();
  if(ui.popScroll){ ui.popScroll = false; const pe = $app.querySelector('.gpop'); if(pe) try { pe.scrollIntoView({block:'nearest', behavior:RM()?'auto':'smooth'}); } catch(e){ /* sem rolagem */ } }
}
function renderSheet(freshOpen){
  if(!ui.sheet){ $sheet.innerHTML = ''; document.body.classList.remove('locked'); return; }
  const oldWrap = $sheet.querySelector('.sheet-wrap:not(.closing)'), old = oldWrap && oldWrap.querySelector('.sheet'), st = old && !freshOpen ? old.scrollTop : 0;
  $sheet.innerHTML = ui.sheet.k==='aval' ? vAval() : ui.sheet.k==='dsask' ? vDsAsk() : ui.sheet.k==='esq' ? vEsq() : (ui.sheet.k==='site' || ui.sheet.k==='inst' || ui.sheet.k==='baixar') ? vBaixar() : ui.sheet.k==='acomp' ? vAcomp() : ui.sheet.k==='doc' ? vDocSheet() : ui.sheet.k==='excluir' ? vExcluir() : ui.sheet.k==='meus' ? vMeus() : ui.sheet.k==='ia' ? vIA() : ui.sheet.k==='ex' ? vSheet(ui.sheet.id) : ui.sheet.k==='prof' ? vProfile() : ui.sheet.k==='cs' || ui.sheet.k==='cman' ? vCardioSheet() : ui.sheet.k==='gym' ? vGym() : ui.sheet.k==='exq' ? vExq(ui.sheet.id) : ui.sheet.k==='story' ? vStory() : ui.sheet.k==='stpick' ? vStPick() : ui.sheet.k==='cstory' ? vCStory() : ui.sheet.k==='cstpick' ? vCStPick() : vSummary(ui.sheet.i);
  const sh = $sheet.querySelector('.sheet');
  const nw = $sheet.querySelector('.sheet-wrap');
  if(old && nw && sh){ nw.classList.add('still'); sh.scrollTop = st; }
  else if(sh){ try { sh.focus({preventScroll:true}); } catch(e){ /* foco opcional */ } }
  document.body.classList.add('locked');
  bindDrag(sh); runTweens($sheet);
  initAnim(); initLayAnim();
}
function closeSheet(){
  const wrap = $sheet.querySelector('.sheet-wrap');
  if(ui.sheet && ui.sheet.k==='meus' && S && S.conta && mtLetras()) mtFix();
  if(ui.sheet && ui.sheet.k==='meus' && S && !S.acomp) setTimeout(acompAsk, 300);
  ui.sheet = null;
  document.body.classList.remove('locked');
  if(!wrap || RM()) $sheet.innerHTML = '';
  else { wrap.classList.add('closing'); setTimeout(() => { if(!ui.sheet) $sheet.innerHTML = ''; }, 300); }
  render();
}
function bindDrag(sh){
  const top = sh && sh.querySelector('[data-drag]'); if(!top) return;
  let y0 = null, dy = 0;
  top.addEventListener('pointerdown', ev => { if(ev.target.closest('button')) return; y0 = ev.clientY; dy = 0; sh.style.transition = 'none'; try { top.setPointerCapture(ev.pointerId); } catch(e){ /* sem captura */ } });
  top.addEventListener('pointermove', ev => { if(y0==null) return; dy = Math.max(0, ev.clientY-y0); sh.style.transform = `translateY(${dy}px)`; });
  const end = () => { if(y0==null) return; y0 = null; sh.style.transition = ''; if(dy>110) closeSheet(); else sh.style.transform = ''; };
  top.addEventListener('pointerup', end); top.addEventListener('pointercancel', end);
}
function renderRest(){
  restPush();
  if(tLay()!=='grade'){ if(!$rest.hidden){ $rest.hidden = true; $rest.innerHTML = ''; } renderDock(); return; }
  { const dk = document.getElementById('rdock'); if(dk){ dk.remove(); document.body.classList.remove('has-dock'); } }
  if(!ui.rest){
    if(!$rest.hidden && !$rest.classList.contains('out')){
      $rest.classList.add('out');
      setTimeout(() => { if(!ui.rest){ $rest.hidden = true; $rest.classList.remove('out','done'); $rest.innerHTML = ''; } }, RM() ? 0 : 320);
    }
    return;
  }
  const C = 2*Math.PI*46;
  if(!$rest.querySelector('.gr-prog')){
    const nx = ui.rest.next; let nh = '';
    if(nx && findEx(nx.id)){
      const f = findEx(nx.id), x = logOf(nx.id)[nx.i], kg = planKg(nx.id,x), reps = planReps(nx.id,nx.i,x);
      nh = `<div class="gr-next"><span class="eyebrow">Próxima série</span><b>${f.e.nome}</b><span>Série ${nx.i+1} de ${exN(f.e)} · ${kg?kg+' kg':'carga livre'} × ${reps}</span></div>`;
    }
    $rest.innerHTML = `<div class="gr-top"><p class="eyebrow">Descanso de ${REST} s</p></div>
      <h2 class="gr-title" id="gr-t">Respire<span class="dot" aria-hidden="true"></span></h2>
      <div class="gr-stage"><span class="gr-breath" aria-hidden="true"></span><svg viewBox="0 0 100 100" aria-hidden="true"><circle class="gr-trk" cx="50" cy="50" r="46"/><circle class="gr-prog" cx="50" cy="50" r="46" stroke-dasharray="${C.toFixed(2)}" transform="rotate(-90 50 50)"/></svg>
      <div class="gr-c"><b class="gr-t" role="timer" aria-label="Tempo de descanso restante"></b><span class="gr-ph" aria-hidden="true"></span></div></div>
      ${nh}
      <div class="gr-btns"><button type="button" class="btn" data-a="rest-add" aria-label="Mais 15 segundos de descanso">+15s</button><button type="button" class="btn primary" data-a="skiprest" aria-label="Pular descanso">Pular${ic('chev')}</button></div>`;
    $rest.dataset.t0 = Date.now();
    $rest.classList.toggle('rm', RM());
    $rest.classList.remove('out','done');
    $rest.hidden = false;
    try { $rest.querySelector('[data-a="skiprest"]').focus({preventScroll:true}); } catch(e){ /* foco opcional */ }
  }
  const left = ui.rest.done ? 0 : Math.max(0, ui.rest.end-Date.now()), p = left/(ui.rest.total*1000);
  const tEl = $rest.querySelector('.gr-t'), txt = fmtDur(left+999).replace(/^0/,'');
  if(tEl.textContent!==txt) tEl.textContent = txt;
  $rest.querySelector('.gr-prog').setAttribute('stroke-dashoffset', (C*(1-p)).toFixed(2));
  const el = (Date.now()-(+$rest.dataset.t0||Date.now()))/1000;
  const ph = ui.rest.done ? 'Hora da série' : (el%8)<4 ? 'Inspire' : 'Expire', phEl = $rest.querySelector('.gr-ph');
  if(phEl.textContent!==ph) phEl.textContent = ph;
}
function toast(msg){
  $toast.textContent = msg; $toast.classList.add('show');
  clearTimeout(toastT); toastT = setTimeout(() => $toast.classList.remove('show'), 2800);
}
function celebrate(title,sub,small,tone){
  const cols = ['#B794FF','#C8F542','#FF9A2E','#FF4646'];
  let parts = '';
  if(!RM()) for(let i=0; i<40; i++){
    const a = Math.random()*Math.PI*2, d = 110+Math.random()*160;
    parts += `<i style="--x:${(Math.cos(a)*d).toFixed(0)}px;--y:${(Math.sin(a)*d-50).toFixed(0)}px;--r:${Math.round(Math.random()*540-270)}deg;--c:${cols[i%4]};--d:${(Math.random()*.15).toFixed(2)}s"></i>`;
  }
  const w = document.createElement('div');
  w.className = 'fx'; w.setAttribute('role','alert');
  w.innerHTML = `<div class="fx-burst" aria-hidden="true">${parts}</div><div class="fx-card${tone?' c-'+tone:''}"><span class="fx-ic">${ic(tone==='water'?'drop':tone==='cardio'?'flag':'trophy')}</span><b>${title}<span class="dot" aria-hidden="true"></span></b><span>${sub}</span>${small?`<small>${small}</small>`:''}</div>`;
  $fx.innerHTML = ''; $fx.appendChild(w);
  const kill = () => { w.classList.add('out'); setTimeout(() => w.remove(), 350); };
  w.addEventListener('click', kill);
  setTimeout(kill, 3000);
  vibrate([30,40,70]);
}

