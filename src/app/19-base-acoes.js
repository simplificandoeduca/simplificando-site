/* ---------- ações ---------- */
function slideStart(t, label, kind, extra){
  const icn = kind==='csave' || kind==='tset' ? 'check' : kind==='cstart' ? 'play' : 'dumbbell', tap = kind==='cstart';
  return `<div class="slide" data-t="${t}"${kind?` data-kind="${kind}"`:''}${extra||''} style="--p:0"><i class="slide-fill" aria-hidden="true"></i>
    <span class="slide-txt" aria-hidden="true">${label}<span class="slide-arrows">${ic('chev')}${ic('chev')}${ic('chev')}</span></span>
    <button type="button" class="slide-knob" data-t="${t}"${tap?' data-a="cstart"':''} aria-label="${label}. ${tap?'Toque, arraste para a direita':'Arraste para a direita'} ou pressione Enter.">${ic(icn,'k-play')}${ic('check','k-ok')}</button></div>`;
}
function wireSlide(){
  let drag = null;
  const done = (sl) => {
    const t = sl.dataset.t, kind = sl.dataset.kind; sl.classList.add('ok'); sl.style.setProperty('--p',1); vibrate(30);
    if(kind==='csave'){ setTimeout(() => { logCardio(ui.cf.local, ui.cf.min, ui.cf.int); render(); }, RM() ? 0 : 380); return; }
    if(kind==='cstart'){ setTimeout(() => { startCardio(); window.scrollTo(0,0); render(); }, RM() ? 0 : 300); return; }
    if(kind==='tset'){ setTimeout(() => tConfirm(sl.dataset.ex, +sl.dataset.i), RM() ? 0 : 320); return; }
    setTimeout(() => { ui.t = t; startSession(t); ui.tab = 'treino'; ui.enter = true; save(); window.scrollTo(0,0); render(); }, RM() ? 0 : 420);
  };
  document.addEventListener('pointerdown', ev => {
    const k = ev.target.closest('.slide-knob'); if(!k) return;
    const sl = k.closest('.slide'); if(sl.classList.contains('ok')) return;
    const max = sl.clientWidth - k.offsetWidth - 8;
    drag = {sl, k, x0:ev.clientX, max, p:0}; sl.classList.add('drag');
    try { k.setPointerCapture(ev.pointerId); } catch(e){ /* sem captura */ }
    ev.preventDefault();
  });
  document.addEventListener('pointermove', ev => {
    if(!drag) return;
    const p = Math.max(0, Math.min(1, (ev.clientX - drag.x0) / drag.max));
    if(p>.5 && drag.p<=.5) vibrate(8);
    drag.p = p; drag.sl.style.setProperty('--p', p.toFixed(3));
  });
  const end = () => {
    if(!drag) return;
    const {sl, p} = drag; drag = null; sl.classList.remove('drag');
    if(p>=.85) done(sl); else sl.style.setProperty('--p',0);
  };
  document.addEventListener('pointerup', end);
  document.addEventListener('pointercancel', end);
  document.addEventListener('keydown', ev => {
    const k = ev.target.closest && ev.target.closest('.slide-knob');
    if(k && (ev.key==='Enter' || ev.key===' ' || ev.key==='ArrowRight')){ ev.preventDefault(); done(k.closest('.slide')); }
  });
}
function startSession(t){ if(!S.session){ S.session = {t, start:Date.now(), acc:0, run:true, gym:'', d:today(), ini:Date.now()}; setTimeout(treinoAviso, 0); dsAsk(); toast('Cronômetro do treino '+t+' ligado. Bom treino.'); ui.sheet = {k:'gym'}; setTimeout(() => { if(ui.sheet && ui.sheet.k==='gym') renderSheet(true); }, 0); } }
/* ---------- academia do dia: o aluno escreve o nome uma vez; nos próximos treinos é só tocar ---------- */
const gymKey = v => String(v||'').normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().replace(/\s+/g,' ').trim();
const gymNome = v => String(v||'').replace(/\s+/g,' ').trim().slice(0,40);
const gymsOf = () => (S.gyms || []).slice().sort((a,b) => String(b.d||'').localeCompare(String(a.d||'')) || (b.c||0)-(a.c||0));
const gymNow = () => (S.session && S.session.gym) || '';
// última vez no exercício: primeiro nesta academia; se ainda não treinou nela, a última vez em qualquer lugar
function lastFor(id){ const g = gymNow(); if(g){ const m = (S.gymLast||{})[gymKey(g)]; if(m && m[id]) return m[id]; } return S.last[id]; }
function setGym(v){
  let nm = gymNome(v); if(!nm || !S.session) return false;
  const L = S.gyms = S.gyms || [], k = gymKey(nm), o = L.find(x => gymKey(x.n)===k);
  if(o){ if(gymKey(S.session.gym)!==k){ o.d = today(); o.c = (o.c||0)+1; } nm = o.n; }
  else { L.push({n:nm, d:today(), c:1}); L.sort((a,b) => String(b.d||'').localeCompare(String(a.d||''))); if(L.length>20) L.length = 20; }
  S.session.gym = nm;
  // séries de hoje ainda não feitas passam a começar com a carga da última vez nesta academia
  const lg = S.logs[tDia()] || {};
  Object.keys(lg).forEach(id => { if(!findEx(id)) return; const k0 = startKg(id); lg[id].forEach(x => { if(!x.ok && !x.man) x.kg = k0!=null ? fmtKg(k0) : ''; }); });
  return true;
}
const gymChip = () => S.session ? `<button type="button" class="gym-chip${gymNow()?'':' empty'}" data-a="gym-open">${ic('pin')}<span>${gymNow() ? esc(gymNow()) : 'Em qual academia você está?'}</span>${ic('edit')}</button>` : '';
function vGym(){
  const L = gymsOf().slice(0,8), cur = gymKey(gymNow());
  return `<div class="sheet-wrap" data-a="closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop(S.session ? 'Treino '+S.session.t+' · '+((PLAN[S.session.t]||{}).nome||'') : 'Treino', 'Em qual academia você está treinando hoje?')}
    ${L.length ? `<div class="gym-list" role="group" aria-label="Academias em que você já treinou">${L.map(g => `<button type="button" class="gym-opt${gymKey(g.n)===cur?' on':''}" aria-pressed="${gymKey(g.n)===cur}" data-a="gym-pick" data-v="${esc(g.n)}">${ic('pin')}<span>${esc(g.n)}</span></button>`).join('')}</div>` : ''}
    <div class="field"><label class="flabel" for="gym-in">${ic('pin')}${L.length ? 'Outra academia' : 'Nome da academia'}</label><div class="gym-new"><div class="inp-wrap"><input id="gym-in" class="inp" type="text" maxlength="40" autocomplete="off" autocapitalize="words" enterkeyhint="done" placeholder="Ex.: Smart Fit, Blue Fit"></div><button type="button" class="btn primary" data-a="gym-ok">${ic('check')}Ok</button></div></div>
    <p class="foot0">Fica salva: no próximo treino é só tocar no nome. A carga sugerida usa o que você fez na mesma academia.</p>
    <button type="button" class="btn block" data-a="gym-skip">Pular</button></div></div>`;
}
/* ---------- "como foi" em cada exercício ---------- */
const EXQ = ['Leve','Na medida','Pesado','Senti dor','Aparelho diferente','Aparelho ocupado'], EXQ3 = EXQ.slice(0,3);
function excOf(id, make){ const t = tDia(), D = S.exc || (make ? (S.exc = {}) : null); if(!D) return null; if(!D[t]){ if(!make) return null; D[t] = {}; } if(!D[t][id] && make) D[t][id] = {q:[], n:''}; return D[t][id] || null; }
// treino de hoje já finalizado: o comentário novo vai junto para o histórico
function excHist(id){ const c = excOf(id), h = (S.history||[]).slice().reverse().find(x => x.date===today() && (x.ex||[]).some(y => y.id===id)); if(!h || !c) return; const y = h.ex.find(z => z.id===id); y.q = c.q.slice(); y.c = c.n; }
const excBox = (id, e, bare) => { const x = excOf(id) || {q:[], n:''};
  return `<div class="exq">${bare ? '' : `<span class="flabel">${ic('msg')}Como foi este exercício?</span>`}<div class="warm-row">${EXQ.map(q => { const on = x.q.includes(q); return `<button type="button" class="wpill${on?' on':''}" aria-pressed="${on}" data-a="exq" data-ex="${id}" data-v="${q}">${on?ic('check'):''}${q}</button>`; }).join('')}</div>
    <textarea rows="2" maxlength="300" data-exn="${id}" aria-label="Comentário sobre ${esc(e.nome)}" placeholder="${isPersonal() ? 'Conte pro seu personal. Ex.: senti o joelho, máquina diferente' : 'Anotação. Ex.: senti o joelho, máquina diferente'}">${esc(x.n)}</textarea>
    ${isPersonal() ? '<p class="foot0">Vai junto com o treino para o seu personal.</p>' : ''}</div>`; };
const excTem = id => { const x = excOf(id); return !!(x && (x.q.length || x.n)); };
const excBtn = (e, cls) => `<button type="button" class="${cls||'linkbtn'}${excTem(e.id)?' on':''}" data-a="exq-open" data-ex="${e.id}">${excTem(e.id) ? ic('check')+'Comentado' : 'Como foi?'}</button>`;
function vExq(id){ const f = findEx(id); if(!f) return ''; const e = f.e;
  return `<div class="sheet-wrap" data-a="closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop('Treino '+f.t+' · '+esc(e.nome), 'Como foi este exercício?')}${excBox(id, e, true)}<button type="button" class="btn primary block" data-a="exq-done">${ic('check')}Pronto</button></div></div>`; }
// terminou todas as séries de um exercício: pergunta, sem travar, como foi
function askExq(e){ if(excTem(e.id)) return;
  let el = document.getElementById('utoast'); if(el) el.remove(); clearTimeout(utT);
  el = document.createElement('div'); el.id = 'utoast'; el.className = 'utoast'; el.setAttribute('role','status');
  el.innerHTML = `<span>${esc(e.nome)} feito. Como foi?</span><button type="button">${ic('msg')}Comentar</button>`;
  el.querySelector('button').addEventListener('click', () => { el.remove(); ui.sheet = {k:'exq', id:e.id}; renderSheet(true); });
  document.body.appendChild(el); utT = setTimeout(() => el.remove(), 6000);
}
const pObs = e => e.obs ? `<p class="pobs">${ic('msg')}<span><b>Do seu personal</b>${esc(e.obs)}</span></p>` : '';
function startRest(nx){ ui.rest = {end:Date.now()+REST*1000, total:REST, next:nx||null, done:false}; $rest.innerHTML = ''; renderRest(); }
function addWater(ml){
  const t = today(), before = S.water[t]||0;
  (S.wlog[t] = S.wlog[t]||[]).push({m:nowMin(), ml});
  S.water[t] = before+ml; setTimeout(pushCopo, 0);
  if(before<S.waterGoal && before+ml>=S.waterGoal) celebrate('Meta de água batida', fmtL(before+ml)+' hoje. Garrafinha cheia.', '', 'water');
  else toast('+'+ml+' ml. Total '+fmtL(before+ml)+'.');
}
function undoWater(){
  const t = today(), L = S.wlog[t]||[], x = L.pop();
  const ml = x ? x.ml : Math.min(250, S.water[t]||0);
  S.water[t] = Math.max(0,(S.water[t]||0)-ml);
  toast(ml<0 ? 'Voltei '+(-ml)+' ml.' : 'Tirei '+ml+' ml.');
}
function toggleSet(id,i){
  const L = getLog(id), x = L[i], f = findEx(id), e = f.e;
  if(!x.ok && !S.session){ toast('Arraste “Começar treino” na tela inicial para iniciar o treino.'); return; }
  if(!x.ok){
    const sg = suggest(id);
    if(!x.reps) x.reps = String(repTarget(id,i));
    if(!x.kg && startKg(id)!=null) x.kg = fmtKg(startKg(id));
    const prev = Math.max(S.pr[id] ? S.pr[id].e1 : 0, todayBest(id).v);
    x.ok = true;
    L.forEach((y,j) => { if(j>i && !y.ok && !y.man) y.kg = x.kg; });
    startSession(f.t); ui.t = f.t;
    const v = e1(num(x.kg),int(x.reps));
    ui.lastId = id;
    const nxs = nextSet(f.t,id);
    if(S.pr[id] && v>prev+0.05){
      celebrate('Recorde pessoal', `${e.nome}: ${x.kg} kg × ${x.reps}.`, `1RM estimado ${fmtKg(Math.round(v))} kg, antes ${fmtKg(Math.round(prev))} kg`);
    } else if(!nxs) toast('Última série feita. Finalize quando quiser.');
    if(nxs) startRest(nxs); else { ui.rest = null; renderRest(); }
    vibrate(15);
  } else x.ok = false;
  save(); renderSheet();
}
function finish(){
  const ses = S.session; if(!ses) return;
  // treino que começou num dia e foi finalizado no outro fica no dia em que começou (com as séries dos dois dias)
  const hoje = today(), t0 = ses.d && ses.d<hoje ? ses.d : hoje, p = PLAN[ses.t], prd = [];
  const log = Object.assign({}, S.logs[t0]||{}); if(t0!==hoje) Object.keys(S.logs[hoje]||{}).forEach(id => { const ok = (S.logs[hoje][id]||[]).filter(x => x.ok); if(ok.length) log[id] = ((log[id]||[]).filter(x => x.ok)).concat(ok); });
  let sets = 0, vol = 0;
  p.ex.forEach(e => {
    const ok = (log[e.id]||[]).filter(x => x.ok); if(!ok.length) return;
    let mk = 0;
    ok.forEach(x => { const kg = num(x.kg); sets++; vol += kg*int(x.reps); mk = Math.max(mk,kg); });
    const top = ok.filter(x => num(x.kg)===mk);
    S.last[e.id] = {kg:mk, lastKg:num(ok[ok.length-1].kg), reps:top.map(x => int(x.reps)), rir:top.map(x => x.rir==null ? 1 : x.rir), d:t0, g:ses.gym||''};
    if(ses.gym){ const gm = S.gymLast || (S.gymLast = {}), gk = gymKey(ses.gym); (gm[gk] = gm[gk] || {})[e.id] = S.last[e.id]; }
    const H = S.loadHist[e.id] = (S.loadHist[e.id]||[]).filter(x => x.d!==t0); H.push({d:t0,kg:mk}); while(H.length>16) H.shift();
    const tb = t0===hoje ? todayBest(e.id) : (() => { let v = 0, i = -1; (log[e.id]||[]).forEach((x,j) => { if(!x.ok) return; const w = e1(num(x.kg),int(x.reps)); if(w>v){ v = w; i = j; } }); return {v,i}; })(), old = S.pr[e.id];
    if(tb.i>=0){
      const x = log[e.id][tb.i], rec = {e1:tb.v, kg:num(x.kg), reps:int(x.reps), d:t0};
      if(!old) S.pr[e.id] = rec;
      else if(tb.v>old.e1+0.05){ prd.push({id:e.id, kg:rec.kg, reps:rec.reps, e1:tb.v, prev:old.e1}); S.pr[e.id] = rec; }
    }
  });
  if(!sets){ toast('Marque pelo menos uma série antes de finalizar.'); return; }
  const el = ses.fix || elapsed(), cap = t0!==hoje && !ses.fix; // tempo ajustado pelo aluno (treino esquecido aberto) vale como está
  const min = Math.max(1, Math.round(el/60000)) > 240 && cap ? 240 : Math.max(1, Math.round(el/60000)), sec = Math.min(Math.max(1, Math.round(el/1000)), cap ? 240*60 : Infinity), nd = new Date(), hr = String(nd.getHours()).padStart(2,'0')+':'+String(nd.getMinutes()).padStart(2,'0');
  const WM = Object.assign({}, S.warm[hoje]||{}, S.warm[t0]||{});
  const det = p.ex.map(e => { const ok = (log[e.id]||[]).filter(x => x.ok), c = excOf(e.id), wt = e.aq ? aqSeries(e).length : 0, w = wt ? (WM[e.id]||[]).slice(0,wt).filter(Boolean).length : 0;
    if(!ok.length && !w && !(c && (c.q.length || c.n))) return null;
    const o = {id:e.id, n:e.nome, s:ok.map(x => [num(x.kg), int(x.reps)]), q:c ? c.q.slice() : [], c:c ? c.n : ''}; if(wt){ o.w = w; o.wt = wt; } return o; }).filter(Boolean);
  const hiD = new Date(sesIni(ses)), hi = String(hiD.getHours()).padStart(2,'0')+':'+String(hiD.getMinutes()).padStart(2,'0');
  const novo = {date:t0, t:ses.t, min, sec, hr, hi, vol:Math.round(vol), sets, prs:prd.map(x => x.id), prd, rpe:0, note:'', gym:ses.gym||'', ex:det}; S.history.push(novo); if(t0!==hoje) S.history.sort((x,y) => x.date<y.date ? -1 : x.date>y.date ? 1 : 0);
  S.session = null; ui.rest = null; renderRest(); treinoAviso();
  stPick(S.history.indexOf(novo));
}

function onClick(ev){
  if(lpAt && Date.now()-lpAt<900){ lpAt = 0; ev.preventDefault(); return; }
  const b = ev.target.closest('[data-a]');
  if(ui.pop && !ev.target.closest('.gpop,.gbub')){ ui.pop = null; if(!b || b.disabled){ render(); return; } }
  if(!b || b.disabled) return;
  const a = b.dataset.a, d = b.dataset;
  if(a==='lg-close' && b.classList.contains('sheet-wrap') && ev.target!==b) return;
  if(a.slice(0,3)==='lg-' || a.slice(0,3)==='su-'){ lgClick(a,d,b); return; }
  if(a.slice(0,3)==='iq-'){ iqClick(a,d,b); return; }
  if(a==='closebg' && ev.target!==b) return;
  const armed = ui.arm; ui.arm = null;
  if(a.slice(0,4)==='esq-'){ if(a==='esq-x' && b.classList.contains('sheet-wrap') && ev.target!==b) return; if(a==='esq-del') ui.arm = armed; esqClick(a,d); return; }
  if(mtClick(a,d,b,armed)){ save(); render(); return; }
  if(a==='ds-sim' || a==='ds-no'){ if(b.classList.contains('sheet-wrap') && ev.target!==b) return; closeSheet(); if(a==='ds-sim'){ PREF.avDesc = true; savePref(); pushEnable(true); } return; }
  if(a==='ntf-push'){ pushEnable(true).then(() => { if(ui.sheet && ui.sheet.k==='prof') renderSheet(); }); return; }
  if(a==='doc-open'){ ui.sheet = {k:'doc', d:d.v}; renderSheet(true); return; }
  if(a==='doc-tab'){ ui.sheet.d = d.v; renderSheet(); return; }
  if(a==='acc-open'){ ui.sheet = {k:'excluir'}; renderSheet(true); return; }
  if(a==='bx-x' || a==='bx-ok'){ if(b.classList.contains('sheet-wrap') && ev.target!==b) return; const novo = S && S.inst==='novo'; if(novo){ S.inst = 'visto'; save(); } closeSheet();
    // conta recém-criada: depois do "Baixe nosso app" vem montar o treino (quem escolheu montar) ou o "Quer compartilhar" (se ficou no site)
    if(novo && AFTER==='meus' && ACCT && ACCT.mode==='self'){ AFTER = null; setTimeout(mtOpen, 400); } else if(novo && a==='bx-x') acompAsk(); if(a==='bx-ok') toast(IOS ? 'Boa! A partir de agora, abra pelo ícone.' : 'Boa! Abra o Simplificando pelo ícone na sua tela.'); return; }
  if(a==='bx-go'){ const s2 = ui.sheet; if(!s2) return; const evI = INST_EV;
    if(!evI){ s2.st = 'menu'; renderSheet(); return; }
    INST_EV = null; s2.st = 'quase'; renderSheet();
    try { evI.prompt(); evI.userChoice.then(r => { if(r && r.outcome==='accepted'){ if(S){ S.inst = 'feito'; save(); } if(ui.sheet && ui.sheet.st==='quase'){ closeSheet(); toast('Instalando. O ícone aparece na sua tela.'); } } else if(ui.sheet && ui.sheet.st==='quase'){ ui.sheet.st = 'menu'; renderSheet(); } }).catch(() => {}); } catch(e){ s2.st = 'menu'; renderSheet(); }
    return; }
  if(a==='av-x'){ if(b.classList.contains('sheet-wrap') && ev.target!==b) return; avalFim(false); closeSheet(); return; }
  if(a==='av-n'){ const s2 = ui.sheet; if(!s2 || s2.k!=='aval') return; const v = +d.v; s2.notas = s2.notas || {}; s2.notas[d.k] = v;
    b.parentElement.querySelectorAll('button').forEach((x,i) => { x.classList.toggle('on', i < v); x.setAttribute('aria-checked', String(i+1===v)); });
    const go = document.querySelector('#sheet [data-a="av-go"]'); if(go) go.disabled = false; return; }
  if(a==='av-go'){ avalEnviar(b); return; }
  if(a==='site-x'){ if(b.classList.contains('sheet-wrap') && ev.target!==b) return; closeSheet(); return; }
  if(a==='inst-x' || a==='inst-ok'){ if(b.classList.contains('sheet-wrap') && ev.target!==b) return; if(S){ S.inst = 'visto'; save(); } closeSheet(); if(a==='inst-ok' && !(ANDROID && INST_EV)) toast('Boa! A partir de agora, abra pelo ícone.'); return; }
  if(a==='inst-go'){ const evI = INST_EV; if(!evI) return; INST_EV = null; evI.prompt(); evI.userChoice.then(r => { if(r && r.outcome==='accepted'){ if(S){ S.inst = 'feito'; save(); } closeSheet(); toast('Instalando. O ícone aparece na tela do celular.'); } else if(document.getElementById('login')) renderLogin(); else renderSheet(); }).catch(() => { if(document.getElementById('login')) renderLogin(); else renderSheet(); }); return; }
  if(a==='acomp-sim' || a==='acomp-no'){ if(b.classList.contains('sheet-wrap') && ev.target!==b) return; acompSet(a==='acomp-sim'); closeSheet(); toast(a==='acomp-sim' ? 'Pronto. Seus treinos estão compartilhados com o Simplificando.' : 'Tudo bem. Dá pra ligar depois no Perfil.'); return; }
  if(a==='acc-del'){ if(armed==='acc-del') accDelete(); else { ui.arm = 'acc-del'; renderSheet(); } return; }
  let re = true;
  switch(a){
    case 'tab': if(ui.tab!==d.v){ ui.tab = d.v; ui.enter = true; window.scrollTo(0,0); } if(d.v==='treino' && !ui.t && !noPlan()) ui.t = nextTreino(); break;
    case 'water': addWater(+d.ml); break;
    case 'undo-water': undoWater(); break;
    case 'water-minus': { const t = today(), cur = S.water[t]||0, take = Math.min(250, cur); if(take>0){ (S.wlog[t] = S.wlog[t]||[]).push({m:nowMin(), ml:-take}); S.water[t] = cur-take; toast('Tirei '+take+' ml. Total '+fmtL(cur-take)+'.'); } break; }
    case 'force-train': ui.forceTrain = true; break;
    case 'tday': { if(isPersonal()) break; const v = +d.v, cur = tDays().slice(), k = cur.indexOf(v); if(k>=0){ if(cur.length>1) cur.splice(k,1); else { toast('Deixe pelo menos um dia de treino.'); renderSheet(); break; } } else cur.push(v); if(!isPersonal() && S.conta){ S.conta.dias = cur.sort((a,b) => a-b); applyAccount(); } renderSheet(); break; }
    case 'go-treino': ui.tab = 'treino'; ui.t = d.t; ui.enter = true; window.scrollTo(0,0); break;
    case 't': ui.t = d.v; break;
    case 'start': ui.t = d.t; startSession(d.t); break;
    case 'pause': { const s = S.session; if(s.run){ s.acc += Date.now()-s.start; s.run = false; } else { s.start = Date.now(); s.run = true; } break; }
    case 'finish': if(esqFim() && !S.session.fix){ esqAbre(true); re = false; break; } if(armed==='finish') finish(); else ui.arm = 'finish'; break;
    case 'ses-cancel': clearTimeout(sesCancelT); if(armed==='cancel') cancelSession(); else { ui.arm = 'cancel'; sesCancelT = setTimeout(() => { if(ui.arm==='cancel'){ ui.arm = null; render(); } }, 6000); } break;
    case 'open': {
      const was = ui.sheet && ui.sheet.k==='ex';
      ui.sheet = {k:'ex', id:d.ex};
      if(!was) ui.pane = S.session ? 'series' : 'exec';
      renderSheet(true); re = false; break;
    }
    case 'close': case 'closebg': closeSheet(); re = false; break;
    case 'pane': ui.pane = d.v; renderSheet(); re = false; break;
    case 'setok': toggleSet(ui.sheet.id, +d.i); re = false; break;
    case 'rir': getLog(ui.sheet.id)[+d.i].rir = +d.v; save(); renderSheet(); re = false; break;
    case 'warm': { const t = tDia(); S.warm[t] = S.warm[t]||{}; const w = S.warm[t][ui.sheet.id] = S.warm[t][ui.sheet.id]||[]; w[+d.i] = !w[+d.i]; save(); renderSheet(); re = false; break; }
    case 'addset': { const L = getLog(ui.sheet.id), l = L[L.length-1]; L.push({kg:l?l.kg:'', reps:'', ok:false, rir:null, add:true}); save(); renderSheet(); re = false; break; }
    case 'rest': startRest(ui.sheet && ui.sheet.id ? nextSet(findEx(ui.sheet.id).t, ui.sheet.id) : null); re = false; break;
    case 'rest-add': if(ui.rest && !ui.rest.done){ ui.rest.end += 15000; ui.rest.total += 15; renderRest(); } re = false; break;
    case 'skiprest': ui.rest = null; renderRest(); re = false; break;
    case 'gb': {
      const id = d.ex, i = +d.i, x = logOf(id)[i], same = ui.pop && ui.pop.id===id && ui.pop.i===i;
      ui.pop = same ? null : {id, i, mode:(x && x.ok) || ui.editRow===id ? 'edit' : 'confirm'}; ui.popScroll = !same;
      break;
    }
    case 'gclose': ui.pop = null; break;
    case 'gstep': {
      if(!ui.pop) break;
      const {id,i} = ui.pop, x = getLog(id)[i];
      if(d.f==='kg'){ const cur = x.kg!=='' ? num(x.kg) : (startKg(id)||0), st = step(cur||10); x.kg = fmtKg(Math.max(0, roundTo(cur + +d.d*st, st))); if(!x.ok) x.man = true; }
      else { const cur = x.reps!=='' ? int(x.reps) : repTarget(id,i); x.reps = String(Math.max(1, cur + +d.d)); }
      break;
    }
    case 'gok': if(ui.pop) confirmSet(ui.pop.id, ui.pop.i); break;
    case 'gun': if(ui.pop){ const x = getLog(ui.pop.id)[ui.pop.i]; x.ok = false; x.rir = null; ui.pop = null; toast('Série desmarcada.'); } break;
    case 'grir': if(ui.pop){ getLog(ui.pop.id)[ui.pop.i].rir = +d.v; } break;
    case 'gedit': ui.editRow = ui.editRow===d.ex ? null : d.ex; ui.pop = null; if(ui.editRow) toast('Edição ligada: toque numa série para ajustar.'); break;
    case 'gskip': { toggleSkip(d.ex); ui.pop = null; if(ui.editRow===d.ex) ui.editRow = null; const nm = findEx(d.ex).e.nome; toast(isSkip(d.ex) ? nm+' pulado hoje.' : nm+' de volta ao treino.'); break; }
    case 'gadd': { const L = getLog(d.ex), l = L[L.length-1]; L.push({kg:l?l.kg:'', reps:'', ok:false, rir:null, add:true}); ui.pop = {id:d.ex, i:L.length-1, mode:'confirm'}; ui.popScroll = true; break; }
    case 'grepeat': {
      const t = ui.t || nextTreino(); let n = 0;
      PLAN[t].ex.forEach(e => { const la = lastFor(e.id); if(!la || isSkip(e.id)) return; getLog(e.id).forEach((x,i) => { if(x.ok) return; x.kg = fmtKg(la.kg); x.reps = String(la.reps[i]!=null ? la.reps[i] : la.reps[la.reps.length-1]); n++; }); });
      ui.pop = null; toast(n ? 'Cargas e repetições da última vez preenchidas. Confirme série por série.' : 'Nada para preencher.');
      break;
    }
    case 'cf-local': ui.cf.local = d.v; break;
    case 'ob-go': { const o = OBRIG[+d.i]; if(!o) break; ui.cf.local = o.local; ui.cf.min = o.min; ui.cfMore = ![10,15,20,25,30,40,45,60].includes(o.min); if(layOf()==='cartoes'){ ui.tab = 'cardio'; ui.cs = { local:o.local, min:o.min, int:ui.cf.int }; ui.sheet = {k:'cs'}; setTimeout(() => renderSheet(true), 0); } else { if(ui.tab!=='cardio'){ ui.tab = 'cardio'; ui.enter = true; } window.scrollTo(0,0); } toast(LOC_S[o.local]+' · '+o.min+' min. É só começar.'); break; }
    case 'cfint': ui.cf.int = d.v; break;
    case 'cf-min': ui.cf.min = +d.v; ui.cfMore = false; break;
    case 'cf-more': ui.cfMore = !ui.cfMore; break;
    case 'cstart': if(!S.cardioLive){ startCardio(); window.scrollTo(0,0); } break;
    case 'cpause': { const L = S.cardioLive; if(L){ if(L.run){ L.acc += Date.now()-L.start; L.run = false; } else { L.start = Date.now(); L.run = true; } } break; }
    case 'cend': endCardio(); break;
    case 'clint': if(S.cardioLive) S.cardioLive.int = d.v; ui.cf.int = d.v; break;
    case 'cmanual': { const L = S.cardioLive; openCS(L ? L.local : ui.cf.local, 'cman'); renderSheet(); re = false; break; }
    case 'cs-open': openCS(d.v, 'cs'); re = false; break;
    case 'cs-local': { const x = lastOf(d.v); ui.cs.local = d.v; if(x && INTENS.includes(x.int)) ui.cs.int = x.int; renderSheet(); re = false; break; }
    case 'cs-int': ui.cs.int = d.v; renderSheet(); re = false; break;
    case 'cs-min': ui.cs.min = +d.v; renderSheet(); re = false; break;
    case 'cs-step': ui.cs.min = Math.min(240, Math.max(5, ui.cs.min+ +d.d)); renderSheet(); re = false; break;
    case 'cs-save': { const c = ui.cs; logCardio(c.local, c.min, c.int); closeSheet(); re = false; break; }
    case 'clay': {
      ui.pick = d.v;
      document.querySelectorAll('#pick .lay').forEach(x => { const on = x.dataset.v===d.v; x.classList.toggle('on', on); x.setAttribute('aria-checked', on); });
      const go = document.querySelector('#pick [data-a="clay-go"]'); if(go) go.disabled = false;
      re = false; break;
    }
    case 'clay-go': { const v = ui.pick || PREF.cardioLayout; if(v){ PREF.cardioLayout = v; savePref(); ui.pstep = 0; renderPick(); toast('Pronto. Dá para trocar no seu perfil.'); } break; }
    case 'prof-to': {
      ui.sheet = {k:'prof', p:'visual'}; renderSheet(true);
      const sh = $sheet.querySelector('.sheet'), h = document.getElementById(d.v), box = h && h.closest('.prefs');
      if(sh && box){ sh.scrollTop += box.getBoundingClientRect().top - sh.getBoundingClientRect().top - 72; const on = box.querySelector('.lay.on'); try { (on||box).focus({preventScroll:true}); } catch(e){ /* foco opcional */ } }
      re = false; break;
    }
    case 'pk-back': ui.pstep = 1; renderPick(); re = false; break;
    case 'tlay': {
      ui.tpick = d.v;
      document.querySelectorAll('#pick .lay').forEach(x => { const on = x.dataset.v===d.v; x.classList.toggle('on', on); x.setAttribute('aria-checked', on); });
      const go = document.querySelector('#pick [data-a="tlay-go"]'); if(go) go.disabled = false;
      re = false; break;
    }
    case 'tlay-go': if(ui.tpick){ PREF.treinoLayout = ui.tpick; savePref(); ui.pstep = PREF.cardioLayout && !ui.pback ? 0 : 2; ui.pback = true; renderPick(); if(!ui.pstep) toast('Pronto. Dá para trocar no seu perfil.'); } break;
    case 'set-tlay': PREF.treinoLayout = d.v; savePref(); ui.tsel = null; ui.pop = null; renderSheet(); renderRest(); toast('Treino no jeito '+TLAYS.find(o => o.id===d.v).n+'.'); break;
    case 'tsel': ui.tsel = {id:d.ex, i:+d.i}; ui.lopen = d.ex; break;
    case 'lopen': ui.lopen = ui.lopen===d.ex ? null : d.ex; ui.tsel = null; break;
    case 'lall': markAll(d.ex); re = false; break;
    case 'tstep': tStepAct(d); break;
    case 'fdone': case 'fck': tConfirm(d.ex, +d.i); re = false; break;
    case 'tun': { const x = getLog(d.ex)[+d.i]; if(x){ x.ok = false; x.rir = null; } ui.tsel = {id:d.ex, i:+d.i}; toast('Série desmarcada.'); break; }
    case 'twarm': { const t = tDia(); S.warm[t] = S.warm[t]||{}; const w = S.warm[t][d.ex] = S.warm[t][d.ex]||[]; w[+d.i] = !w[+d.i]; break; }
    case 'howto': ui.sheet = {k:'ex', id:d.ex}; ui.pane = 'exec'; renderSheet(true); re = false; break;
    case 'fnav': fNav(+d.d); re = false; break;
    case 'fexp': { const k = ui.fopen.indexOf(d.ex); if(k>=0) ui.fopen.splice(k,1); else ui.fopen.push(d.ex); break; }
    case 'fed': ui.fed = ui.fed && ui.fed.id===d.ex && ui.fed.i===+d.i ? null : {id:d.ex, i:+d.i}; break;
    case 'set-clay': PREF.cardioLayout = d.v; savePref(); renderSheet(); toast('Cardio no jeito '+LAYS.find(o => o.id===d.v).n+'.'); break;
    case 'boneco': if(!isPersonal() && S.conta){ S.conta.sexo = d.v==='M' ? 'M' : 'F'; applyAccount(); } renderSheet(); break;
    case 'plan-set': if(!isPersonal() && S.conta && (d.v==='abc' || d.v==='ab')){ S.conta.plan = d.v; applyAccount(); ui.t = null; ui.tsel = null; ui.pop = null; toast(d.v==='ab' ? 'Plano: corpo todo A e B.' : 'Plano: A, B e C.'); } renderSheet(); break;
    case 'logout': if(armed==='logout') logout(); else { ui.arm = 'logout'; renderSheet(); } re = false; break;
    case 'cfmin': ui.cf.min = Math.min(240, Math.max(5, ui.cf.min+ +d.d)); break;
    case 'ci-sono': ui.ci.sono = +d.v; break;
    case 'ci-energia': ui.ci.energia = +d.v; break;
    case 'ci-dor': ui.ci.dor = +d.v; break;
    case 'ci-save': {
      S.checkin[today()] = Object.assign({}, ui.ci); ui.ci = null; ui.ciEdit = false;
      toast(lowReady(S.checkin[today()]) ? 'Check-in salvo. Hoje as cargas sugeridas repetem a última vez.' : (isPersonal()?'Check-in salvo. Seu personal já pode ver.':'Check-in salvo.'));
      break;
    }
    case 'ci-edit': ui.ciEdit = true; ui.ci = Object.assign({}, S.checkin[today()]); break;
    case 'remind-ev': S.remind.every = +d.v; ui.remindFired = null; pushSave(); break;
    case 'push-on': pushEnable(); re = false; break;
    case 'push-off': pushDisable(false); re = false; break;
    case 'goal': if(isPersonal()) break; S.waterGoal = Math.min(6000, Math.max(1000, S.waterGoal+ +d.d)); if(ui.sheet && ui.sheet.k==='prof') renderSheet(); break;
    case 'cgoal': if(isPersonal()) break; S.cardioGoal = Math.min(600, Math.max(30, S.cardioGoal+ +d.d)); if(ui.sheet && ui.sheet.k==='prof') renderSheet(); break;
    case 'ed': if(isPersonal()) break; S.ed = d.v; if(ui.sheet && ui.sheet.k==='prof') renderSheet(); break;
    case 'foto-del': if(armed==='foto-del'){ saveFoto(''); toast('Foto removida.'); } else ui.arm = 'foto-del'; renderSheet(); break;
    case 'profile': ui.sheet = {k:'prof'}; renderSheet(true); re = false; break;
    case 'pf-go': { ui.sheet = {k:'prof', p:d.v||''}; ui.arm = null; renderSheet(); const sh = $sheet.querySelector('.sheet'); if(sh){ sh.scrollTop = 0; try { sh.focus({preventScroll:true}); } catch(e){ /* foco opcional */ } } re = false; break; }
    case 'theme': PREF.theme = curTheme()==='dark' ? 'light' : 'dark'; savePref(); applyLook(); toast(PREF.theme==='light' ? 'Modo claro.' : 'Modo escuro.'); break;
    case 'accent': PREF.accent = ACC[d.v] ? d.v : 'vermelho'; savePref(); applyLook(); renderSheet(); break;
    case 'month': ui.month = Math.min(0, Math.max(-11, ui.month+ +d.d)); break;
    case 'chart': ui.chartEx = d.v; break;
    case 'hist': ui.sheet = {k:'sum', i:+d.i}; renderSheet(true); re = false; break;
    case 'rpe': S.history[ui.sheet.i].rpe = +d.v; save(); renderSheet(); re = false; break;
    case 'gym-pick': setGym(d.v); closeSheet(); toast('Treino na '+gymNow()+'. Bom treino.'); re = false; break;
    case 'gym-ok': { const el = document.getElementById('gym-in'), v = el ? gymNome(el.value) : ''; if(!v){ if(el){ el.setAttribute('aria-invalid','true'); el.focus(); } toast('Escreva o nome da academia ou toque em Pular.'); re = false; break; } setGym(v); closeSheet(); toast('Treino na '+gymNow()+'. Bom treino.'); re = false; break; }
    case 'gym-skip': closeSheet(); re = false; break;
    case 'gym-open': ui.sheet = {k:'gym'}; renderSheet(true); re = false; break;
    case 'exq-open': ui.sheet = {k:'exq', id:d.ex, back:d.back!=null ? {k:'sum', i:+d.back} : null}; renderSheet(true); re = false; break;
    case 'exq-done': { const bk = ui.sheet && ui.sheet.back; if(bk){ ui.sheet = bk; renderSheet(true); } else closeSheet(); toast(isPersonal() ? 'Comentário salvo. Vai para o seu personal.' : 'Comentário salvo.'); re = false; break; }
    case 'exq': { const x = excOf(d.ex, true), v = d.v, k = x.q.indexOf(v); if(k>=0) x.q.splice(k,1); else { if(EXQ3.includes(v)) x.q = x.q.filter(q => !EXQ3.includes(q)); x.q.push(v); } excHist(d.ex); renderSheet(); re = false; break; }
    case 'st-open': stPick(+d.i); re = false; break;
    case 'cst-open': cPick(+d.j); re = false; break;
    case 'cstp-go': { const s = ui.sheet; if(s && s.k==='cstpick'){ clearTimeout(stpT); PREF.cstM = d.v; savePref(); cOpen(s.j, d.v); } re = false; break; }
    case 'cstp-x': { const s = ui.sheet; if(s && s.k==='cstpick' && s.xOn){ closeSheet(); toast('Quando quiser, poste pelo botão de compartilhar em Cardio › Recentes.'); } re = false; break; }
    case 'cst-closebg': if(ev.target!==b){ re = false; break; } /* falls through */
    case 'cst-close': cancelAnimationFrame(CST_RAF); closeSheet(); re = false; break;
    case 'cst-m': if(ui.sheet && ui.sheet.k==='cstory' && ui.sheet.m!==d.v && !ui.sheet.grav) cSet('m', d.v); re = false; break;
    case 'cst-cor': if(ui.sheet && ui.sheet.k==='cstory' && ui.sheet.cor!==d.v && !ui.sheet.grav) cSet('cor', d.v); re = false; break;
    case 'cst-tipo': if(ui.sheet && ui.sheet.k==='cstory' && ui.sheet.tipo!==d.v && !ui.sheet.grav) cSet('tipo', d.v); re = false; break;
    case 'cst-gravar': cGravar(); re = false; break;
    case 'cst-share': if(ui.sheet && ui.sheet.k==='cstory'){ cLog(ui.sheet, 'share'); cShare(ui.sheet); } re = false; break;
    case 'cst-down': if(ui.sheet && ui.sheet.k==='cstory' && ui.sheet.blob){ cLog(ui.sheet, 'baixar'); cSalvar(ui.sheet.blob, cNome(ui.sheet, ui.sheet.blob)); } re = false; break;
    case 'stp-go': { const s = ui.sheet; if(s && s.k==='stpick'){ clearTimeout(stpT); PREF.stM = d.v; savePref(); stOpen(s.i, d.v); } re = false; break; }
    case 'stp-x': { const s = ui.sheet; if(s && s.k==='stpick' && s.xOn){ ui.sheet = {k:'sum', i:s.i}; renderSheet(true); toast('Quando quiser, toque em Compartilhar no story.'); } re = false; break; }
    case 'st-closebg': if(ev.target!==b){ re = false; break; } /* falls through */
    case 'st-close': { const s = ui.sheet; ui.sheet = {k:'sum', i:s.i}; renderSheet(true); re = false; break; }
    case 'st-m': if(ui.sheet && ui.sheet.k==='story' && ui.sheet.m!==d.v) stSet('m', d.v); re = false; break;
    case 'st-cor': if(ui.sheet && ui.sheet.k==='story' && ui.sheet.cor!==d.v) stSet('cor', d.v); re = false; break;
    case 'st-share': if(ui.sheet && ui.sheet.k==='story'){ stLog(ui.sheet, 'share'); stShare(ui.sheet); } re = false; break;
    case 'ig-abrir': { const g = S.ig || (S.ig = {}); g.abriu = today(); save(); try { window.open(IG_URL, '_blank', 'noopener'); } catch(e){ /* sem pop-up */ } igSome(); re = false; break; }
    case 'ig-sigo': { const g = S.ig || (S.ig = {}); g.sigo = true; save(); igSome('Valeu por seguir! 💪'); re = false; break; }
    case 'ig-x': { const g = S.ig || (S.ig = {}); g.x = today(); save(); igSome(); re = false; break; }
    case 'ig-copiar': { const v = '@'+IG_USER, ok = () => toast('Copiado. No story, toque em Aa ou em Mencionar e cole.'), fb = () => toast('Marque @'+IG_USER+' no story.');
      try { if(navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(v).then(ok, fb); else fb(); } catch(e){ fb(); } re = false; break; }
    case 'st-down': if(ui.sheet && ui.sheet.k==='story'){ stLog(ui.sheet, 'baixar'); stBaixar(ui.sheet); } re = false; break;
    case 'sug-send': sugEnviar(b); re = false; break;
    case 'ver-checar': if(NOVAV) versaoAtualiza(); else versaoPerfil(b); re = false; break;
    case 'cdel': { const c = S.cardio[+d.i]; if(!c) break; S.cardio.splice(+d.i, 1); S.cDel = (S.cDel||[]).filter(k => k!==c.date+'|'+c.local+'|'+c.min).concat([c.date+'|'+c.local+'|'+c.min]).slice(-80); save(); toast('Cardio excluído: '+c.local+' · '+c.min+' min'); break; }
    case 'sum-save': closeSheet(); toast(isPersonal()?'Salvo. Seu personal vai ver.':'Salvo no seu histórico.'); re = false; break;
    case 'wipe':
      if(armed==='wipe'){ const ed = S.ed, g = S.waterGoal, cg = S.cardioGoal, nm = S.nome, conta = S.conta; S = fresh(false); S.ed = ed; S.waterGoal = g; S.cardioGoal = cg; S.nome = nm; if(conta) S.conta = conta; applyAccount(); ui.rest = null; ui.t = null; ui.tw = {}; renderRest(); toast('Registros apagados.'); }
      else ui.arm = 'wipe';
      if(ui.sheet) renderSheet();
      break;
    case 'fresh':
      if(armed==='fresh'){ const ed = S.ed; S = fresh(false); S.ed = ed; ui.t = null; ui.ci = null; ui.ciEdit = false; ui.rest = null; ui.tw = {}; renderRest(); toast('Pronto. Agora é com você.'); }
      else ui.arm = 'fresh';
      break;
  }
  save();
  if(re || armed) render();
}
function onInput(ev){
  const el = ev.target;
  if(el.id==='lg-pw'){ lgInput(el); return; }
  if(el.id==='lg-em'){ LG.email = el.value; LG.help = ''; return; }
  if(el.id==='fg-email'){ LG.fg.email = el.value; const go = document.querySelector('#login .lg-go'); if(go) go.disabled = !el.value.trim(); const e = document.getElementById('fg-err'); if(e) e.textContent = ''; return; }
  if(el.id==='rs-1' || el.id==='rs-2'){ rsInput(); return; }
  if(el.dataset.su){ if(el.dataset.su==='senha' && /\D/.test(el.value)) el.value = el.value.replace(/\D/g,'').slice(0,6); LG.f[el.dataset.su] = el.value; const go = document.querySelector('#login .su-go'); if(go) go.disabled = !suOk1(); const wt = document.querySelector('#login [data-su-wt]'); if(wt) wt.textContent = suWaterTxt(); if(LG.serr){ LG.serr = ''; const e = document.getElementById('su-err'); if(e) e.textContent = ''; } return; }
  if(el.id==='ex-pw' && ui.sheet && ui.sheet.k==='excluir'){ ui.sheet.pw = el.value; ui.sheet.err = ''; return; }
  if(el.dataset.en){ LG.en[el.dataset.en] = el.value; if(LG.serr){ LG.serr = ''; const e = document.getElementById('en-err'); if(e) e.textContent = ''; } return; }
  if(el.dataset.mt && ui.sheet && ui.sheet.k==='meus'){
    const sh = ui.sheet, k = el.dataset.mt, t = S.conta.treinos[sh.ed];
    if(k==='nome' && t){ t.nome = cleanName(el.value); save(); const h = document.getElementById('sh-t'); if(h) h.innerHTML = esc(t.nome||'Treino '+sh.ed)+'<span class="dot" aria-hidden="true"></span>'; }
    else if(k==='q' && t){ sh.q = el.value; const L = document.getElementById('mt-list'); if(L){ L.innerHTML = mtPickRows(sh, t); L.scrollTop = 0; } }
    else if(k==='cn'){ sh.cn = el.value; const bt = document.getElementById('mt-cbtn'); if(bt) bt.disabled = !(cleanName(sh.cn) && sh.cg); }
    return;
  }
  if(el.dataset.mx && ui.sheet && ui.sheet.k==='meus'){ const t = S.conta.treinos[ui.sheet.ed], e = t && t.ex[+el.dataset.i]; if(e){ e.r = String(el.value).replace(/[<>&"]/g,'').slice(0,14); save(); } return; }
  if(el.dataset.set){ const y = getLog(el.dataset.ex)[+el.dataset.i]; if(!y) return; y[el.dataset.set] = el.value.trim(); if(el.dataset.set==='kg' && !y.ok) y.man = true; if(el.dataset.fix) el.dataset.len = el.value.trim().length; save(); }
  if(el.dataset.prof){ S.nome = el.value.trim().slice(0,40); save(); }
  if(el.dataset.note!=null){ const h = S.history[+el.dataset.note]; if(h){ h.note = el.value; save(); } }
  if(el.dataset.exn){ const x = excOf(el.dataset.exn, true); x.n = el.value.slice(0,300); excHist(el.dataset.exn); save(); }
  if(el.id==='gym-in') el.removeAttribute('aria-invalid');
}
function onChange(ev){
  const el = ev.target;
  if(el.dataset.remind){
    const m = /^(\d{1,2}):(\d{2})/.exec(el.value||''); if(!m){ render(); return; }
    const v = Math.min(23*60+45, +m[1]*60 + +m[2]), k = el.dataset.remind;
    if(k==='ini'){ S.remind.ini = v; if(RFIM()<v+60) S.remind.fim = Math.min(23*60+45, v+60); }
    else { S.remind.fim = v; if(v<RINI()+60) S.remind.ini = Math.max(0, v-60); }
    ui.remindFired = null; save(); pushSave(); render(); toast('Lembretes das '+hmTxt(RINI())+' às '+hmTxt(RFIM())+'.'); return;
  }
  if(el.dataset.mx && ui.sheet && ui.sheet.k==='meus'){ const t = S.conta.treinos[ui.sheet.ed], e = t && t.ex[+el.dataset.i]; if(e){ e.r = String(el.value).replace(/[<>&"]/g,'').trim().slice(0,14); if(!/\d/.test(e.r)) e.r = '10–12'; el.value = e.r; save(); render(); } return; }
  if(el.dataset.fix && el.dataset.set){
    const y = getLog(el.dataset.ex)[+el.dataset.i]; if(!y) return;
    const v = el.value.trim();
    if(el.dataset.set==='kg'){ y.kg = v==='' ? '' : fmtKg(Math.max(0, Math.round(num(v)*100)/100)); if(!y.ok) y.man = true; }
    else y.reps = v==='' ? '' : String(Math.max(1, int(v)));
    if(tLay()==='lista' && !ui.pop) ui.tsel = {id:el.dataset.ex, i:+el.dataset.i};
    save();
    setTimeout(() => {
      const a = document.activeElement, k = a && a.dataset && a.dataset.fix ? [a.dataset.set, a.dataset.ex, a.dataset.i] : null;
      render();
      if(k){ const n = document.querySelector(`.numin[data-set="${k[0]}"][data-ex="${k[1]}"][data-i="${k[2]}"]`); if(n){ n.focus(); n.select(); } }
    }, 0);
    return;
  }
  if(el.dataset.sw==='acomp'){ acompSet(el.checked); toast(el.checked ? 'Acompanhamento ligado.' : 'Acompanhamento desligado. Seus treinos não aparecem mais para ninguém.'); }
  if(el.dataset.sw==='avdia'){ PREF.avDia = el.checked; savePref(); lbSync(); if(el.checked && !pushLigado() && pushPode()) pushEnable(true); else toast(el.checked ? 'Lembrete do treino ligado.' : 'Lembrete do treino desligado.'); renderSheet(); }
  if(el.dataset.sw==='avdesc'){ PREF.avDesc = el.checked; savePref(); restPush(); if(el.checked && !pushLigado() && pushPode()) pushEnable(true); else toast(el.checked ? 'Aviso do fim do descanso ligado.' : 'Aviso do fim do descanso desligado.'); renderSheet(); }
  if(el.dataset.sw==='avtreino'){ PREF.avTreino = el.checked; PREF.trIni = null; savePref(); treinoAviso(); if(el.checked && !pushLigado() && pushPode()) pushEnable(true); else toast(el.checked ? 'Aviso de treino esquecido ligado.' : 'Aviso de treino esquecido desligado.'); renderSheet(); }
  if(el.dataset.sw==='glass'){ PREF.glass = el.checked; savePref(); applyGlass(); toast(el.checked ? 'Liquid Glass ligado.' : 'Visual sólido de volta.'); }
  if(el.dataset.sw==='remind'){ S.remind.on = el.checked; ui.remindFired = null; save(); if(PREF.pushOn) pushSave(); render(); toast(el.checked ? 'Lembretes ligados.' : 'Lembretes desligados.'); }
}
function tick(){
  if(!ACCT) return;
  if(S.cardioLive){
    const ms = cardioElapsed(), t = fmtDur(ms), g = liveGoal(ms);
    document.querySelectorAll('[data-tick="cardio"]').forEach(n => { if(n.textContent!==t) n.textContent = t; });
    document.querySelectorAll('[data-tick="cgoal"]').forEach(n => { if(n.textContent!==g.txt) n.textContent = g.txt; });
    document.querySelectorAll('[data-tick="cbar"]').forEach(n => { n.style.width = Math.round(g.p*100)+'%'; });
  }
  if(S.session){ const t = fmtDur(elapsed()); document.querySelectorAll('[data-tick="ses"]').forEach(n => { if(n.textContent!==t) n.textContent = t; }); }
  if(ui.rest){
    restPerto();
    if(!ui.rest.done && Date.now()>=ui.rest.end){
      ui.rest.done = true; $rest.classList.add('done'); toast('Descanso acabou. Bora para a próxima série.'); vibrate([120,60,120]);
      setTimeout(() => { if(ui.rest && ui.rest.done){ ui.rest = null; renderRest(); } }, 900);
    }
    renderRest();
  }
  const n = nextRemind();
  if(n>0 && nowMin()>=n && ui.remindFired!==n){ ui.remindFired = n; toast('Hora da água. Que tal 300 ml agora?'); vibrate(80); }
  if(ui.day!==today()){ ui.day = today(); tidy(); render(); }
}
function boot(){
  $app = document.getElementById('app'); $sheet = document.getElementById('sheet'); $rest = document.getElementById('rest');
  $toast = document.getElementById('toast'); $tabs = document.getElementById('tabs'); $fx = document.getElementById('fx');
  applyLook();
  try { matchMedia('(prefers-color-scheme: light)').addEventListener('change', () => { if(!PREF.theme){ applyLook(); render(); } }); } catch(e){ /* sem preferência do sistema */ }
  $tabs.innerHTML = '<span class="tab-ind" aria-hidden="true"></span>'+TABS.map(t => `<button type="button" class="tab" data-a="tab" data-v="${t[0]}">${ic(t[2])}<span>${t[1]}</span></button>`).join('');
  document.addEventListener('click', onClick);
  wireSlide(); wireTSwipe();
  document.addEventListener('change', ev => { if(ev.target && ev.target.id==='prof-foto'){ loadFoto(ev.target.files && ev.target.files[0]); ev.target.value = ''; } });
  document.addEventListener('input', onInput);
  document.addEventListener('keydown', ev => { if(ev.key==='Enter' && ev.target && ev.target.id==='gym-in'){ ev.preventDefault(); const b = document.querySelector('[data-a="gym-ok"]'); if(b) b.click(); } });
  document.addEventListener('focusin', ev => { const el = ev.target; if(el.classList && el.classList.contains('numin')) setTimeout(() => { try { el.setSelectionRange(0, el.value.length); } catch(e){ el.select(); } }, 0); });
  document.addEventListener('keydown', ev => { const el = ev.target; if(ev.key==='Enter' && el.classList && el.classList.contains('numin')){ ev.preventDefault(); el.blur(); } });
  document.addEventListener('change', onChange);
  document.addEventListener('keydown', e => { if(e.key==='Escape' && document.getElementById('login')){ const w = document.querySelector('#login .sheet-wrap'); if(w) w.remove(); return; }
    if(e.key==='Escape' && !document.getElementById('pick')){ if($fx.firstChild) $fx.innerHTML = ''; else if(ui.rest){ ui.rest = null; renderRest(); } else if(ui.sheet) closeSheet(); else if(ui.pop){ ui.pop = null; render(); } } });
  // segurar a bolha abre a edição
  const lpCancel = () => { clearTimeout(lpT); lpXY = null; };
  document.addEventListener('pointerdown', ev => {
    const bb = ev.target.closest('.gbub[data-a="gb"]'); if(!bb || bb.disabled) return;
    lpXY = [ev.clientX, ev.clientY]; clearTimeout(lpT);
    lpT = setTimeout(() => { lpAt = Date.now(); lpXY = null; ui.pop = {id:bb.dataset.ex, i:+bb.dataset.i, mode:'edit'}; vibrate(10); render(); }, 480);
  });
  document.addEventListener('pointermove', ev => { if(lpXY && Math.hypot(ev.clientX-lpXY[0], ev.clientY-lpXY[1])>10) lpCancel(); });
  document.addEventListener('pointerup', lpCancel); document.addEventListener('pointercancel', lpCancel);
  document.addEventListener('contextmenu', ev => { if(ev.target.closest('.gbub')) ev.preventDefault(); });
  const n = nextRemind(); if(n>0 && nowMin()>=n) ui.remindFired = n;
  document.addEventListener('submit', ev => { if(ev.target.dataset && ev.target.dataset.lg){ ev.preventDefault(); lgSubmit(ev.target.dataset.lg); } });
  window.addEventListener('hashchange', () => { if(location.hash==='#redefinir' && LG.view!=='reset') openReset(); });
  const rp = FB && resetParams();
  if(rp) openNovo(rp); else if(location.hash==='#redefinir') openReset(); else if(readSess()) startApp(); else { renderLogin(); if(location.hash==='#sem-personal'){ history.replaceState(null,'',location.pathname); try { selfStart(); } catch(e){ /* fica no login */ } } }
  setInterval(tick, 250);
  offlineSetup(); versaoSetup();
}
/* erros do app: quando algo quebra no celular da aluna, manda um aviso curto para o painel do personal (erros.php) */
const ERRS = { n:0, vistos:{} };
function errSend(msg, src, line, col, stack){
  try {
    if(!FB || !navigator.onLine || ERRS.n>=5) return;
    const k = String(msg)+'|'+src+'|'+line; if(ERRS.vistos[k]) return; ERRS.vistos[k] = 1; ERRS.n++;
    const quem = ACCT ? (ACCT.mode==='personal' ? (firstName((ACCT.aluno && ACCT.aluno.perfil && ACCT.aluno.perfil.nome) || '')+' · '+ACCT.code) : 'sem personal') : 'antes de entrar';
    const body = JSON.stringify({ msg:String(msg).slice(0,300), src:String(src||'').replace(location.origin,'').slice(0,160), line:line||0, col:col||0, stack:String(stack||'').split(location.origin).join('').slice(0,900),
      tela:(typeof ui!=='undefined' && ui) ? (ui.sheet ? 'janela '+ui.sheet.k : ui.tab) : '', ver:APPV, quem, ua:navigator.userAgent });
    const url = '/erros.php?acao=registrar';
    if(navigator.sendBeacon) navigator.sendBeacon(url, new Blob([body], {type:'application/json'}));
    else fetch(url, {method:'POST', body, headers:{'Content-Type':'application/json'}, keepalive:true}).catch(() => {});
  } catch(e){ /* sem aviso */ }
}
// falhas conhecidas do Safari/iPhone no banco interno (IndexedDB) que o login do Firebase usa quando o app volta do segundo plano:
// o próprio Firebase tenta de novo e o aluno não percebe nada, então não viram aviso no painel
const ERR_IGNORA = /without an in-progress transaction|Connection to Indexed Database server lost|Database deleted by request of the user agent|IDBDatabase|IndexedDB/i;
window.addEventListener('error', ev => { if(ev && ev.message && !/^Script error\.?$/i.test(ev.message) && !ERR_IGNORA.test(ev.message)) errSend(ev.message, ev.filename, ev.lineno, ev.colno, ev.error && ev.error.stack); });
window.addEventListener('unhandledrejection', ev => { const r = ev && ev.reason, m = r && r.message ? r.message : String(r); if(ERR_IGNORA.test(m)){ try { ev.preventDefault(); } catch(e){ /* nada */ } return; } errSend('Promessa sem tratamento: '+m, '', 0, 0, r && r.stack); });
