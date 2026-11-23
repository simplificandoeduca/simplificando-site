/* ---------- Treino: grade (uma bolha por série) ---------- */
const REST = 60, GKEY = 'simplificando-pro-grade';
function loadGS(){ try { const v = JSON.parse(localStorage.getItem(GKEY+':'+uidOf())||'null'); return v && v.d===tDia() && Array.isArray(v.skip) ? v : {d:tDia(), skip:[]}; } catch(e){ return {d:tDia(), skip:[]}; } }
let GS = {d:today(), skip:[]};
function saveG(){ try { localStorage.setItem(GKEY+':'+uidOf(), JSON.stringify(GS)); } catch(e){ /* sem armazenamento */ } }
const isSkip = id => GS.d===tDia() && GS.skip.includes(id);
function toggleSkip(id){ if(GS.d!==tDia()) GS = {d:tDia(), skip:[]}; const k = GS.skip.indexOf(id); if(k>=0) GS.skip.splice(k,1); else GS.skip.push(id); saveG(); }
const GRP = a => { const w = a.split(' ')[0]; return ({Deltoide:'Ombro',Dorsal:'Costas',Peitoral:'Peito'})[w] || w; };
const exN = e => Math.max(e.s, logOf(e.id).length);
function gTot(t){ let tot = 0, dn = 0; PLAN[t].ex.forEach(e => { const d = doneSets(e.id); dn += d; tot += isSkip(e.id) ? d : exN(e); }); return {tot,dn}; }
function pendIdx(e){ const L = logOf(e.id), n = exN(e); for(let j=0; j<n; j++) if(!(L[j] && L[j].ok)) return j; return -1; }
/* próxima série pendente: o par do bi-set primeiro, depois o mesmo exercício, depois a ordem do plano */
function nextSet(t, fromId){
  const ex = PLAN[t].ex, ok = e => !isSkip(e.id);
  let k = 0;
  const from = fromId && findEx(fromId);
  if(from && from.t===t){
    const e = from.e; k = ex.indexOf(e);
    const prev = ex[k-1];
    if(e.sup){ const m = findEx(e.sup).e; if(ok(m) && pendIdx(m)>=0 && doneSets(m.id)<doneSets(e.id)) return {id:m.id, i:pendIdx(m)}; }
    else if(prev && prev.sup===e.id && ok(prev) && pendIdx(prev)>=0 && doneSets(prev.id)<=doneSets(e.id)) return {id:prev.id, i:pendIdx(prev)};
  }
  for(let s=0; s<ex.length; s++){ const e = ex[(k+s)%ex.length]; if(!ok(e)) continue; const j = pendIdx(e); if(j>=0) return {id:e.id, i:j}; }
  return null;
}
const planKg = (id,x) => x && x.kg ? x.kg : (startKg(id)!=null ? fmtKg(startKg(id)) : '');
const planReps = (id,i,x) => x && x.reps ? x.reps : String(repTarget(id,i));
function gPop(e,i){
  const id = e.id, x = getLog(id)[i], mode = ui.pop.mode, kg = planKg(id,x), reps = planReps(id,i,x), cx = ((i%5)+.5)/5*100;
  const stp = (f,lab,val,unit,im) => `<div><label class="gp-l" for="gp-${f}">${lab}</label><div class="gstep"><button type="button" data-a="gstep" data-f="${f}" data-d="-1" aria-label="Menos ${unit}">${ic('minus')}</button><input id="gp-${f}" class="numin" inputmode="${im}" enterkeyhint="done" autocomplete="off" value="${esc(val)}" data-set="${f}" data-ex="${id}" data-i="${i}" data-fix="1"><button type="button" data-a="gstep" data-f="${f}" data-d="1" aria-label="Mais ${unit}">${ic('plus')}</button></div></div>`;
  const rir = x.ok ? `<div class="rir"><span>Quantas ainda sairiam?</span>${[0,1,2,3].map(n => `<button type="button" class="rb${x.rir===n?' on':''}" aria-pressed="${x.rir===n}" aria-label="Sobraram ${n===3?'3 ou mais':n}" data-a="grir" data-v="${n}">${n===3?'3+':n}</button>`).join('')}</div>` : '';
  const act = mode==='confirm'
    ? `<button type="button" class="btn primary block" data-a="gok">${ic('check')}Confirmar série ${i+1}</button>`
    : `${rir}<div class="row-btns">${x.ok?`<button type="button" class="btn" data-a="gun">${ic('undo')}Desmarcar</button>`:`<button type="button" class="btn" data-a="gok">${ic('check')}Confirmar</button>`}<button type="button" class="btn primary" data-a="gclose">Pronto</button></div>`;
  return `<div class="gpop" role="group" aria-label="Série ${i+1} de ${e.nome}" style="--cx:${cx}%">
    <div class="gp-h"><div><b>Série ${i+1}${x.ok?' · feita':''}</b><span>${mode==='edit'?'Ajuste os números':'Confira e confirme'} · descanso de ${REST}s depois</span></div><button type="button" class="btn round" data-a="gclose" aria-label="Fechar">${ic('x')}</button></div>
    <div class="gp-st">${stp('kg','Carga (kg)',kg,'carga','decimal')}${stp('reps','Repetições',reps,'uma repetição','numeric')}</div>${act}</div>`;
}
function vTreinoGrade(){
  const t = ui.t || nextTreino(), p = PLAN[t], ses = S.session, active = ses && ses.t===t;
  const {tot,dn} = gTot(t), vol = liveVol(t), prs = livePRs(t).length, armed = ui.arm==='finish', allDone = active && dn>0 && dn>=tot;
  const nx = nextSet(t, ui.lastId), just = ui.just; ui.just = null;
  const groups = [];
  p.ex.forEach(e => { const g = GRP(e.alvo); let o = groups.find(x => x.g===g); if(!o) groups.push(o = {g, d:0, n:0}); const d = doneSets(e.id); o.d += d; o.n += isSkip(e.id) ? d : exN(e); });
  const mus = groups.map(o => `<div class="gm${o.n && o.d>=o.n?' full':''}"><span>${o.g}<em>${o.d}/${o.n}</em></span><i><u style="width:${o.n?Math.round(o.d/o.n*100):0}%"></u></i></div>`).join('');
  let top;
  if(active) top = `<div class="gs-top"><div class="gs-clock"><span><i class="live-dot${ses.run?'':' off'}"></i>${ses.run?'Ao vivo':'Pausado'} · Treino ${t}</span><b data-tick="ses">${fmtDur(elapsed())}</b></div>
      <button type="button" class="btn round" data-a="pause" aria-label="${ses.run?'Pausar cronômetro':'Retomar cronômetro'}">${ic(ses.run?'pause':'play')}</button>
      <button type="button" class="btn ${armed?'armed':allDone?'primary':''}" data-a="finish">${ic('flag')}${armed?'Confirmar':'Finalizar'}</button></div>${gymChip()}${sesCancel()}`;
  else if(ses) top = `<div class="gs-top"><div class="gs-clock"><span>Treino ${ses.t} em andamento</span><b data-tick="ses">${fmtDur(elapsed())}</b></div><button type="button" class="btn primary" data-a="t" data-v="${ses.t}">Abrir ${ses.t}</button></div>`;
  else top = `<div class="gs-top"><div class="gs-clock"><span>Treino ${t} · cerca de ${p.min} min</span><b class="idle">00:00</b></div></div>${slideStart(t,'Começar treino '+p.nome)}`;
  const hint = armed ? 'Toque de novo em Confirmar para salvar o treino.' : allDone ? 'Todas as séries feitas. Finalize para ver o resumo.' : '';
  const sum = `<div class="gstick"><section class="card gsum" aria-label="Resumo do treino">${top}
    <div class="gs-stats"><div><b>${dn}<small>/${tot}</small></b><span>séries</span></div><div><b>${fmtN(vol)}<small> kg</small></b><span>volume</span></div><div><b>${prs}</b><span>recorde${prs===1?'':'s'}</span></div></div>
    <div class="gs-mus" aria-label="Séries por grupo muscular">${mus}</div>${hint?`<p class="gs-hint" role="status">${hint}</p>`:''}</section></div>`;
  const low = lowReady(S.checkin[today()]);
  const hasLast = p.ex.some(e => S.last[e.id]);
  const rows = p.ex.map((e,k) => {
    const L = logOf(e.id), n = exN(e), sg = suggest(e.id), sk = isSkip(e.id), prev = p.ex[k-1], pr = S.pr[e.id], tb = todayBest(e.id), d = doneSets(e.id), ed = ui.editRow===e.id;
    const bs = e.sup ? 'bs-a' : (prev && prev.sup===e.id ? 'bs-b' : '');
    let bubs = '', pop = '';
    for(let i=0; i<n; i++){
      const x = L[i], ok = !!(x && x.ok), kg = planKg(e.id,x), reps = planReps(e.id,i,x);
      const isPR = ok && pr && tb.i===i && tb.v>pr.e1+0.05;
      const open = !!(ui.pop && ui.pop.id===e.id && ui.pop.i===i);
      const cls = (ok?' ok':'')+(!ok && nx && nx.id===e.id && nx.i===i?' nx':'')+(open?' open':'')+(just && just.id===e.id && just.i===i?' just':'');
      bubs += `<button type="button" class="gbub${cls}" data-a="gb" data-ex="${e.id}" data-i="${i}" aria-expanded="${open}" aria-label="Série ${i+1}: ${ok?'feita com':'planejado'} ${kg?kg+' kg':'carga livre'} por ${reps} repetições${isPR?', recorde':''}"${sk&&!ok?' disabled':''}><b>${kg||'livre'}</b><small>× ${reps}</small>${isPR?`<span class="gtr">${ic('trophy')}</span>`:''}</button>`;
      if(open) pop = gPop(e,i);
    }
    if(!sk && !isPersonal()) bubs += `<button type="button" class="gbub add" data-a="gadd" data-ex="${e.id}" aria-label="Adicionar série extra em ${e.nome}">${ic('plus')}</button>`;
    return `<div class="gex${bs?' '+bs:''}${sk?' skip':''}">
      <div class="gex-h"><button type="button" class="gex-n" data-a="open" data-ex="${e.id}" aria-label="${e.nome}: abrir execução, séries e histórico"><b>${e.nome}</b><span>${sk?'Pulado hoje':`${e.s} × ${e.r} · ${d} de ${n} feitas`}${!sk && sg.tone==='up'?` <em class="up">+${fmtKg(sg.up)} kg</em>`:''}</span></button>
      ${sk?'':`<button type="button" class="gib${excTem(e.id)?' on':''}" data-a="exq-open" data-ex="${e.id}" aria-label="Como foi ${e.nome}" title="Como foi">${ic('msg')}</button>`}
      ${sk?'':`<button type="button" class="gib" data-a="gedit" data-ex="${e.id}" aria-pressed="${ed}" aria-label="Editar séries de ${e.nome}" title="Editar séries">${ic('edit')}</button>`}
      ${isPersonal()?'':`<button type="button" class="gib" data-a="gskip" data-ex="${e.id}" aria-pressed="${sk}" aria-label="${sk?'Voltar a fazer':'Pular exercício'} ${e.nome}" title="${sk?'Voltar a fazer':'Pular exercício'}">${ic(sk?'undo':'skip')}</button>`}</div>
      ${bs==='bs-a'?`<span class="tag">${ic('layers')}Bi-set com o próximo</span>`:''}${tecTag(e)?`<span class="tag tq">${ic('bolt')}${tecTag(e)}</span>`:''}
      ${sk?'':tObs(e)+tWarm(e)}<div class="gbubs" role="group" aria-label="Séries de ${e.nome}">${bubs}</div>${pop}</div>`;
  }).join('');
  return header('Treino','Treino '+t+' · '+p.foco)+exampleNote()+
    seg('t',LETTERS,t,LETTERS.map(l => l+' · '+PLAN[l].nome),'Escolher treino')+sum+
    (low?`<p class="warn">${ic('moon')}<span>Check-in de hoje pede um dia mais leve. As sugestões repetem a carga da última vez.</span></p>`:'')+
    `<div class="gtools"><button type="button" class="btn ghost" data-a="grepeat"${hasLast?'':' disabled'}>${ic('undo')}Repetir último treino</button><span class="gleg" aria-hidden="true"><i></i>planejado<i class="o"></i>feito</span></div>`+
    `<div class="ggrid">${rows}</div>`+
    `<p class="foot">Toque numa bolha para conferir carga e repetições e confirmar a série. Segure a bolha, ou use o lápis, para editar. Toque no nome do exercício para ver a execução e o histórico.</p>`;
}
/* ---------- Treino: quatro jeitos (Lista viva, Foco, Folha, Grade), mesmos dados e regras ---------- */
const TLAYS = [
  {id:'lista', n:'Lista viva', s:'O exercício da vez aberto, o resto em lista.'},
  {id:'foco', n:'Foco', s:'Um exercício por tela, números grandes.'},
  {id:'folha', n:'Folha', s:'Como a ficha de papel, uma linha por série.'},
  {id:'grade', n:'Grade', s:'Todas as séries em bolhas.'}
];
const tLay = () => TLAYS.some(o => o.id===PREF.treinoLayout) ? PREF.treinoLayout : 'grade';
/* primeiro acesso: só 2 modelos de treino, combinando com o jeito de registrar o cardio */
const TL_BY_CARDIO = {
  toque:   {ids:['lista','foco'],  why:'Você escolheu registrar o cardio em um toque. Estes dois são os mais rápidos no treino.'},
  crono:   {ids:['foco','folha'],  why:'Você escolheu acompanhar o cardio ao vivo. Estes dois guiam série por série.'},
  cartoes: {ids:['grade','folha'], why:'Você escolheu os ícones dos aparelhos. Estes dois mostram o treino inteiro de uma vez.'}
};
const tlSuggest = () => TL_BY_CARDIO[PREF.cardioLayout] || TL_BY_CARDIO.toque;
function stHoje(html){
  const i = S.history.length-1, h = S.history[i]; if(!h || h.date!==today() || S.session) return html;
  const card = `<button type="button" class="stj" data-a="st-open" data-i="${i}"><span class="stj-i">${ic('share')}</span><span><b>Poste o treino de hoje</b><small>Treino ${h.t} · ${h.min} min · ${h.sets} séries</small></span>${ic('chev','chev')}</button>`;
  const k = html.indexOf('</div>', html.indexOf('wave-strip')); return k>=0 ? html.slice(0,k+6)+card+html.slice(k+6) : card+html;
}
const layDica = () => PREF.layDica && !S.history.length ? `<p class="lay-dica">Visual: <b>${TLAYS.find(o => o.id===tLay()).n}</b> · <button type="button" class="linkbtn" data-a="lay-dica">trocar no Perfil</button></p>` : '';
function vTreino(){ if(noPlan()) return header('Treino','Seu treino')+noPlanCard(); const L = tLay(), h0 = L==='lista' ? vTreinoLista() : L==='foco' ? vTreinoFoco() : L==='folha' ? vTreinoFolha() : vTreinoGrade(); const h = stHoje(h0)+layDica(); return ACCT && ACCT.mode==='self' ? h+`<button type="button" class="btn ghost block mt-link" data-a="mt-open-sheet">${ic('edit')}Editar meus treinos</button>` : h; }
function tCtx(){ const t = ui.t || nextTreino(), p = PLAN[t], ses = S.session, g = gTot(t); return { t, p, ses, active:!!(ses && ses.t===t), tot:g.tot, dn:g.dn }; }
const exDone = e => isSkip(e.id) || doneSets(e.id)>=exN(e);
function exSummary(e){ const L = logOf(e.id).filter(x => x.ok), mk = L.reduce((a,x) => Math.max(a,num(x.kg)),0); return `${L.length} de ${exN(e)}${mk?' · até '+fmtKg(mk)+' kg':''}`; }
function exPlan(e){ const k = startKg(e.id), d = doneSets(e.id); return `${e.s} × ${e.r}${k!=null?' · '+fmtKg(k)+' kg':''}${e.tec && TEC[e.tec.k]?' · '+TEC[e.tec.k].n:''}${e.sup?' · Bi-set com o próximo':''}${d?' · '+d+' de '+exN(e)+' feitas':''}`; }
const tDots = e => { const n = exN(e), d = doneSets(e.id); return `<span class="tdots" aria-hidden="true">${Array.from({length:n},(_,j) => `<s class="${j<d?'f':''}"></s>`).join('')}</span>`; };
function bsOf(p,e){ const k = p.ex.indexOf(e), prev = p.ex[k-1]; const m = e.sup ? findEx(e.sup).e : (prev && prev.sup===e.id ? prev : null); return m ? 'Bi-set com '+m.nome : ''; }
/* série em foco: a escolhida pelo aluno, senão a próxima pendente (bi-set primeiro) */
function curOf(c){ const s = ui.tsel; if(s && c.p.ex.some(e => e.id===s.id)) return s; return nextSet(c.t, ui.lastId); }
/* número de carga/repetições que também aceita digitação (toque no número) */
function numIn(id,i,f,v,cls){
  const lab = f==='kg' ? `Carga da série ${i+1} em kg` : `Repetições da série ${i+1}`;
  return `<input class="numin${cls?' '+cls:''}" inputmode="${f==='kg'?'decimal':'numeric'}" enterkeyhint="done" autocomplete="off" aria-label="${lab}. Toque para digitar" placeholder="–" value="${esc(v||'')}" data-len="${String(v||'').length}" data-set="${f}" data-ex="${id}" data-i="${i}" data-fix="1">`;
}
function tStep(id,i,f){
  const x = getLog(id)[i], v = f==='kg' ? planKg(id,x) : planReps(id,i,x), u = f==='kg' ? 'carga' : 'uma repetição';
  return `<div class="tst"><button type="button" class="tst-b" data-a="tstep" data-ex="${id}" data-i="${i}" data-f="${f}" data-d="-1" aria-label="Menos ${u}, série ${i+1}">${ic('minus')}</button><label class="tst-v">${numIn(id,i,f,v)}<small>${f==='kg'?'kg':'reps'}</small></label><button type="button" class="tst-b" data-a="tstep" data-ex="${id}" data-i="${i}" data-f="${f}" data-d="1" aria-label="Mais ${u}, série ${i+1}">${ic('plus')}</button></div>`;
}
/* aquecimento: com carga, mostra o peso; no 1º treino (ainda sem carga), mostra a % da carga da 1ª série */
function warmPills(e, ws, act, exAttr){
  const sg = suggest(e.id), kg = sg.kg, st = kg ? step(kg) : 0;
  return aqSeries(e).map(([fr,r],j) => `<button type="button" class="wpill${ws[j]?' on':''}" aria-pressed="${!!ws[j]}" data-a="${act}"${exAttr} data-i="${j}">${ws[j]?ic('check'):''}${kg ? fmtKg(Math.max(st,roundTo(kg*fr,st)))+' kg' : Math.round(fr*100)+'%'} × ${r}</button>`).join('');
}
const warmNota = e => suggest(e.id).kg ? '' : `<small class="wnote">% da carga que você vai usar na 1ª série</small>`;
function tWarm(e){
  if(!e.aq || doneSets(e.id)) return '';
  const ws = (S.warm[tDia()]||{})[e.id] || [];
  return `<div class="twarm"><span>${ic('flame')}Aquecimento</span>${warmPills(e, ws, 'twarm', ` data-ex="${e.id}"`)}${warmNota(e)}</div>`;
}
/* observação que o personal escreveu no exercício: em destaque em todos os jeitos de treino */
const tObs = e => e.obs && String(e.obs).trim() ? `<p class="tobs">${ic('msg')}<span><b>Do seu personal</b>${esc(String(e.obs).trim())}</span></p>` : '';
const tLow = () => lowReady(S.checkin[today()]) ? `<p class="warn">${ic('moon')}<span>Check-in de hoje pede um dia mais leve. As sugestões repetem a carga da última vez.</span></p>` : '';
/* topo comum: escolher treino + arrastar para começar, ou relógio, pausa e Finalizar (dois toques) */
/* cancelar o treino iniciado sem querer: dois toques, nada é registrado */
const sesCancel = () => ui.arm==='cancel'
  ? `<button type="button" class="ses-x on" data-a="ses-cancel" role="alert">${ic('x')}<span>Toque de novo para cancelar. Este treino não vai ser registrado.</span></button>`
  : `<button type="button" class="ses-x" data-a="ses-cancel">${ic('x')}Iniciou sem querer? Cancelar treino</button>`;
let sesCancelT = 0;
function cancelSession(){
  const ses = S.session; if(!ses) return;
  const t0 = tDia(), ids = ((PLAN[ses.t]||{}).ex||[]).map(e => e.id);
  [S.logs, S.warm, S.exc].forEach(D => { const d = D && D[t0]; if(!d) return; ids.forEach(id => delete d[id]); if(!Object.keys(d).length) delete D[t0]; });
  if(GS.d===t0){ GS.skip = GS.skip.filter(id => !ids.includes(id)); saveG(); }
  S.session = null; ui.rest = null; renderRest(); save(); treinoAviso();
  toast('Treino cancelado. Nada foi registrado.');
}
function tSesBar(c){
  const prog = `<div class="tprog" role="progressbar" aria-label="Séries feitas" aria-valuemin="0" aria-valuemax="${c.tot}" aria-valuenow="${c.dn}"><i style="width:${c.tot?Math.round(c.dn/c.tot*100):0}%"></i></div>`;
  if(!c.ses) return seg('t',LETTERS,c.t,LETTERS.map(l => l+' · '+PLAN[l].nome),'Escolher treino')+slideStart(c.t,'Começar treino '+c.p.nome)+prog;
  if(!c.active) return `<div class="tsbar"><div class="tsb-c"><b data-tick="ses">${fmtDur(elapsed())}</b><span>Treino ${c.ses.t} em andamento</span></div><button type="button" class="btn primary" data-a="t" data-v="${c.ses.t}">Abrir ${c.ses.t}</button></div>`;
  const armed = ui.arm==='finish', allDone = c.dn>0 && c.dn>=c.tot, run = c.ses.run;
  return `<div class="tsbar"><div class="tsb-c"><b><i class="live-dot${run?'':' off'}"></i><span data-tick="ses">${fmtDur(elapsed())}</span></b><span role="status">${armed?'Toque de novo para salvar':c.dn+' de '+c.tot+' séries'}</span></div>
    <button type="button" class="btn round" data-a="pause" aria-label="${run?'Pausar cronômetro':'Retomar cronômetro'}">${ic(run?'pause':'play')}</button>
    <button type="button" class="btn ${armed?'armed':allDone?'primary':''}" data-a="finish">${ic('flag')}${armed?'Confirmar':'Finalizar'}</button></div>`+gymChip()+prog+sesCancel();
}
function tAllDone(c){ return `<section class="card"><h2 class="big">Tudo feito<span class="dot" aria-hidden="true"></span></h2><p class="muted">${c.dn} séries no treino ${c.t}. Toque em Finalizar para ver o resumo.</p></section>`; }
/* 1 · Lista viva */
function tPanel(c,e,k){
  const n = exN(e), L = logOf(e.id), si = ui.tsel && ui.tsel.id===e.id ? ui.tsel.i : Math.max(0, pendIdx(e)), x = L[si], ok = !!(x && x.ok), bs = bsOf(c.p,e);
  const tiles = Array.from({length:n},(_,j) => { const y = L[j], o = !!(y && y.ok), kg = planKg(e.id,y), r = planReps(e.id,j,y);
    return `<button type="button" class="tset${o?' ok':''}${j===si?' now':''}" data-a="tsel" data-ex="${e.id}" data-i="${j}" aria-pressed="${j===si}" aria-label="Série ${j+1}: ${kg||'carga livre'} kg por ${r}${o?', feita':''}">S${j+1}<b>${kg||'–'}×${r}</b></button>`; }).join('');
  return `<section class="tpanel" aria-label="${e.nome}, exercício ${k+1} de ${c.p.ex.length}">
    <div class="tp-h"><div><small>Agora · ${k+1} de ${c.p.ex.length}</small><h2>${e.nome}</h2>${bs?`<span class="tp-bs">${ic('layers')}${bs}</span>`:''}${tecTag(e)?`<span class="tp-bs tq">${ic('bolt')}${tecTag(e)}</span>`:''}</div><button type="button" class="tp-how" data-a="howto" data-ex="${e.id}">${ic('play')}Como fazer</button></div>
    ${tObs(e)}${tWarm(e)}<div class="tsets" style="grid-template-columns:repeat(${Math.min(n,4)},minmax(0,1fr))">${tiles}</div>
    <div class="tsteps">${tStep(e.id,si,'kg')}${tStep(e.id,si,'reps')}</div>
    ${ok ? `<button type="button" class="btn block" data-a="tun" data-ex="${e.id}" data-i="${si}">${ic('undo')}Desmarcar série ${si+1}</button>` : slideStart(c.t,'Série '+(si+1)+' feita','tset',` data-ex="${e.id}" data-i="${si}"`)}
    <div class="tp-foot">${excBtn(e)}</div></section>`;
}
function vTreinoLista(){
  const c = tCtx(), cur = curOf(c), openId = ui.lopen && c.p.ex.some(e => e.id===ui.lopen) ? ui.lopen : cur && cur.id;
  let pend = 0;
  const rows = c.p.ex.map((e,k) => {
    if(e.id===openId) return tPanel(c,e,k);
    const done = exDone(e); if(!done) pend++;
    return `<div class="lrow-w${done?' done':''}"><div class="lrow-under" aria-hidden="true">${ic('check')}Feito</div>
      <div class="lrow"${done?'':` data-swipe="${e.id}"`}><button type="button" class="lrow-m" data-a="lopen" data-ex="${e.id}" aria-label="${e.nome}, ${isSkip(e.id)?'pulado hoje':done?'feito, '+exSummary(e):exPlan(e)}. Abrir"><span class="lnum">${done?ic(isSkip(e.id)?'skip':'check'):k+1}</span><span class="lmain"><b>${e.nome}</b><span>${isSkip(e.id)?'Pulado hoje':done?exSummary(e):exPlan(e)}</span></span>${tDots(e)}</button>
      ${done?'':`<button type="button" class="sr sr-f" data-a="lall" data-ex="${e.id}">Marcar todas as séries de ${e.nome} como feitas</button>`}</div></div>`;
  }).join('');
  return header('Treino','Treino '+c.t+' · '+c.p.nome)+exampleNote()+tSesBar(c)+tLow()+(cur || !c.active ? '' : tAllDone(c))+rows+
    (pend ? `<p class="lhint">${ic('chev')}Deslize um exercício para a direita para marcar tudo como feito.</p>` : '');
}
/* 2 · Foco */
function vTreinoFoco(){
  const c = tCtx(), ex = c.p.ex;
  let cur = ui.tsel && ex.some(e => e.id===ui.tsel.id) ? ui.tsel : ui.rest && ui.fdone && ex.some(e => e.id===ui.fdone.id) ? ui.fdone : nextSet(c.t, ui.lastId);
  if(!cur) return `<div class="fo-eb"><p class="eyebrow">Treino ${c.t} · ${c.p.nome}</p></div>`+exampleNote()+tSesBar(c)+(c.active?tAllDone(c):'');
  const e = findEx(cur.id).e, k = ex.indexOf(e), n = exN(e), L = logOf(e.id), i = Math.min(cur.i, n-1), x = L[i], ok = !!(x && x.ok), last = lastFor(e.id), bs = bsOf(c.p,e);
  const nxt = ex.slice(k+1).concat(ex.slice(0,k)).find(y => !exDone(y));
  const pills = Array.from({length:n},(_,j) => { const o = !!(L[j] && L[j].ok);
    return `<button type="button" class="fo-pill${o?' ok':''}${j===i?' now':''}" data-a="tsel" data-ex="${e.id}" data-i="${j}" aria-pressed="${j===i}" aria-label="Série ${j+1}${o?', feita':''}">${o?ic('check'):''}${j===i&&!o?'Série '+(j+1):j+1}</button>`; }).join('');
  const nb = (f,lab,unit) => `<div class="fo-nb"><small>${lab}</small>${numIn(e.id,i,f,f==='kg'?planKg(e.id,x):planReps(e.id,i,x),'fo-in')}<small>${unit}</small>
    <div class="fo-pm"><button type="button" data-a="tstep" data-ex="${e.id}" data-i="${i}" data-f="${f}" data-d="-1" aria-label="Menos ${f==='kg'?'carga':'uma repetição'}">${ic('minus')}</button><button type="button" data-a="tstep" data-ex="${e.id}" data-i="${i}" data-f="${f}" data-d="1" aria-label="Mais ${f==='kg'?'carga':'uma repetição'}">${ic('plus')}</button></div></div>`;
  let zone;
  if(ui.rest){
    const r = ui.rest, left = r.done ? 0 : Math.max(0, r.end-Date.now());
    zone = `<div class="fo-rest resting" style="--p:${(left/(r.total*1000)).toFixed(3)}"><span class="fo-rest-fill" aria-hidden="true"></span><span class="fo-rest-t"><span>Descanso</span><b role="timer" data-rest-t>${fmtDur(left+999).replace(/^0/,'')}</b></span>
      <button type="button" class="btn" data-a="rest-add" aria-label="Mais 15 segundos de descanso">+15s</button><button type="button" class="btn go" data-a="skiprest" aria-label="Pular descanso">Pular</button></div>`;
  } else zone = ok ? `<button type="button" class="fo-done ghost" data-a="tun" data-ex="${e.id}" data-i="${i}">${ic('undo')}Desmarcar série ${i+1}</button>`
    : `<button type="button" class="fo-done" data-a="fdone" data-ex="${e.id}" data-i="${i}">${ic('check')}Série ${i+1} feita</button>`;
  const lastTxt = last ? `${fmtKg(last.kg)} kg × ${last.reps[i]!=null ? last.reps[i] : last.reps[last.reps.length-1]}` : '';
  return `<div class="fo-eb"><p class="eyebrow">Treino ${c.t} · exercício ${k+1} de ${ex.length}</p><span class="fo-nav"><button type="button" class="btn round" data-a="fnav" data-d="-1" aria-label="Exercício anterior">${ic('chevL')}</button><button type="button" class="btn round" data-a="fnav" data-d="1" aria-label="Próximo exercício">${ic('chev')}</button></span></div>`+
    exampleNote()+tSesBar(c)+tLow()+
    `<div class="fo-main" data-fswipe="1"><div><h2 class="fo-name">${e.nome}<span class="dot" aria-hidden="true"></span></h2><p class="fo-sub">${e.alvo} · ${e.s} × ${e.r}${bs?' · '+bs:''}${tecTag(e)?' · '+tecTag(e):''} · <button type="button" class="linkbtn" data-a="howto" data-ex="${e.id}">Como fazer</button> · ${excBtn(e)}</p></div>
    ${tObs(e)}${tWarm(e)}<div class="fo-pills" role="group" aria-label="Séries de ${e.nome}" style="grid-template-columns:repeat(${n},minmax(0,1fr))">${pills}</div>
    <div class="fo-nums">${nb('kg','Carga','kg')}${nb('reps','Repetições','reps')}</div>
    ${lastTxt?`<p class="fo-last">Última vez: <b>${lastTxt}</b></p>`:''}</div>
    <div class="fo-zone${ui.rest?' resting':''}">${zone}</div>
    ${nxt?`<p class="fo-next">Depois: <b>${nxt.nome}</b> · ${nxt.s} × ${nxt.r}</p>`:''}`;
}
/* 3 · Folha de treino */
function vTreinoFolha(){
  const c = tCtx(), cur = curOf(c), op = ui.fopen || [];
  const blocks = c.p.ex.map(e => {
    const done = exDone(e), isCur = !!(cur && cur.id===e.id), open = isCur || op.includes(e.id);
    if(!open && done) return `<button type="button" class="fcol" data-a="fexp" data-ex="${e.id}" aria-expanded="false" aria-label="${e.nome}: ${isSkip(e.id)?'pulado hoje':'feito, '+exSummary(e)}. Abrir"><span><b>${e.nome}</b><span>${isSkip(e.id)?'Pulado hoje':exSummary(e)}</span></span><span class="fcol-c">${ic(isSkip(e.id)?'skip':'check')}</span></button>`;
    if(!open) return `<button type="button" class="fhead" data-a="fexp" data-ex="${e.id}" aria-expanded="false"><b>${e.nome}</b><span>${exPlan(e)}</span></button>`;
    const n = exN(e), L = logOf(e.id), bs = bsOf(c.p,e);
    let rows = '';
    for(let j=0;j<n;j++){
      const y = L[j], o = !!(y && y.ok), now = isCur && cur.i===j, ed = !!(ui.fed && ui.fed.id===e.id && ui.fed.i===j), kg = planKg(e.id,y), r = planReps(e.id,j,y);
      rows += `<div class="fset${o?' ok':''}${now?' now':''}"><span class="fno">${j+1}</span><button type="button" class="fval" data-a="fed" data-ex="${e.id}" data-i="${j}" aria-expanded="${ed}" aria-label="Série ${j+1}: ${kg||'carga livre'} kg por ${r}. Mudar números">${kg||'–'}<small>kg ×</small>${r}${now&&!ed?'<span class="fed-h">toque para mudar</span>':''}</button>
        <button type="button" class="fck" data-a="${o?'tun':'fck'}" data-ex="${e.id}" data-i="${j}" aria-pressed="${o}" aria-label="${o?'Desmarcar':'Marcar'} série ${j+1} de ${e.nome}">${ic('check')}</button></div>`+
        (ed ? `<div class="fedit">${tStep(e.id,j,'kg')}${tStep(e.id,j,'reps')}</div>` : '');
    }
    return `<section class="fex"><button type="button" class="fex-h" data-a="fexp" data-ex="${e.id}" aria-expanded="true"${isCur?' disabled':''}><b>${e.nome}</b><span>${e.s} × ${e.r}</span></button>
      ${bs?`<span class="tp-bs">${ic('layers')}${bs}</span>`:''}${tecTag(e)?`<span class="tp-bs tq">${ic('bolt')}${tecTag(e)}</span>`:''}${tObs(e)}${tWarm(e)}${rows}<div class="fex-f"><button type="button" class="linkbtn" data-a="howto" data-ex="${e.id}">Como fazer</button>${excBtn(e)}</div></section>`;
  }).join('');
  return header('Treino','Treino '+c.t+' · '+c.p.nome)+exampleNote()+tSesBar(c)+tLow()+(cur || !c.active ? '' : tAllDone(c))+`<div class="folha">${blocks}</div>`;
}
/* ações comuns */
function tConfirm(id,i){
  const had = !!S.session;
  confirmSet(id,i);
  if(had){ ui.tsel = null; ui.lopen = null; ui.fed = null; ui.fdone = {id,i}; }
  render();
}
function markAll(id){
  if(!S.session){ toast('Arraste “Começar treino” para iniciar. Aí as séries contam.'); render(); return; }
  const e = findEx(id).e, L = getLog(id), n = exN(e), ch = [];
  for(let j=0;j<n;j++){ const x = L[j]; if(!x || x.ok) continue; if(!x.reps) x.reps = String(repTarget(id,j)); if(!x.kg && startKg(id)!=null) x.kg = fmtKg(startKg(id)); x.ok = true; ch.push(j); }
  ui.lastId = id; ui.lopen = null; ui.tsel = null; save(); render(); vibrate(20);
  if(ch.length) undoToast(`${e.nome}: ${ch.length} série${ch.length>1?'s':''} marcada${ch.length>1?'s':''}.`, () => { ch.forEach(j => { L[j].ok = false; L[j].rir = null; }); save(); render(); });
}
let utT = 0;
function undoToast(msg,fn){
  let el = document.getElementById('utoast'); if(el) el.remove(); clearTimeout(utT);
  el = document.createElement('div'); el.id = 'utoast'; el.className = 'utoast'; el.setAttribute('role','status');
  el.innerHTML = `<span>${esc(msg)}</span><button type="button">${ic('undo')}Desfazer</button>`;
  el.querySelector('button').addEventListener('click', () => { el.remove(); fn(); toast('Desfeito.'); });
  document.body.appendChild(el); utT = setTimeout(() => el.remove(), 5000);
}
/* descanso fora da Grade: barra encaixada (Lista), pílula fina (Folha) ou o próprio botão (Foco) */
function renderDock(){
  const lay = tLay(), r = ui.rest, inFoco = lay==='foco' && ui.tab==='treino' && !ui.sheet;
  let d = document.getElementById('rdock');
  const left = r ? (r.done ? 0 : Math.max(0, r.end-Date.now())) : 0, p = r ? left/(r.total*1000) : 0, txt = fmtDur(left+999).replace(/^0/,'');
  if(lay==='foco' && ui.tab==='treino'){
    const fz = $app.querySelector('.fo-zone');
    if(fz && fz.classList.contains('resting')!==!!r){ if(!r) ui.fdone = null; render(); }
    else if(fz && r){ const b = fz.querySelector('[data-rest-t]'), w = fz.querySelector('.fo-rest'); if(b && b.textContent!==txt) b.textContent = txt; if(w) w.style.setProperty('--p', p.toFixed(3)); }
  }
  if(!r || inFoco){ if(d) d.remove(); document.body.classList.remove('has-dock'); return; }
  const kind = lay==='folha' ? 'pill' : 'bar';
  if(!d || d.dataset.k!==kind){
    if(d) d.remove();
    d = document.createElement('div'); d.id = 'rdock'; d.dataset.k = kind; d.setAttribute('role','timer'); d.setAttribute('aria-label','Descanso');
    d.className = kind==='pill' ? 'rpill' : 'rdock';
    d.innerHTML = kind==='pill'
      ? `<span>Descanso</span><b data-rt></b><span class="f"></span><button type="button" class="rp-skip" data-a="skiprest" aria-label="Pular descanso">Pular</button>`
      : `<div class="rd-t"><span>Descanso</span><b data-rt></b></div><button type="button" class="btn" data-a="rest-add" aria-label="Mais 15 segundos de descanso">+15s</button><button type="button" class="btn rd-skip" data-a="skiprest" aria-label="Pular descanso">Pular</button><i class="rd-bar" aria-hidden="true"><u></u></i>`;
    document.body.appendChild(d); document.body.classList.add('has-dock');
  }
  const b = d.querySelector('[data-rt]'); if(b.textContent!==txt) b.textContent = txt;
  const u = d.querySelector('.rd-bar u'); if(u) u.style.width = (p*100).toFixed(1)+'%';
}
/* Recentes do cardio: arrastar para a esquerda mostra o botão Excluir (arrastar até o fim já exclui) */
function wireCSwipe(){
  let sw = null;
  const fecha = ex => document.querySelectorAll('.cs-wrap.open').forEach(w => { if(w!==ex){ w.classList.remove('open'); const r = w.querySelector('.cs-row'); if(r) r.style.transform = ''; } });
  document.addEventListener('pointerdown', ev => {
    const row = ev.target.closest('.cs-row[data-cswipe]'); if(!row){ if(!ev.target.closest('.cs-del')) fecha(); return; }
    const w = row.parentElement; fecha(w); sw = { row, w, x0:ev.clientX, y0:ev.clientY, dx:0, on:false, base:w.classList.contains('open') ? -104 : 0 };
  });
  document.addEventListener('pointermove', ev => {
    if(!sw) return; const dx = ev.clientX-sw.x0, dy = ev.clientY-sw.y0;
    if(!sw.on){ if(Math.abs(dy)>10 && Math.abs(dy)>Math.abs(dx)){ sw = null; return; } if(Math.abs(dx)<10) return; sw.on = true; sw.row.style.transition = 'none'; }
    sw.dx = Math.min(0, Math.max(-sw.row.offsetWidth, sw.base+dx)); sw.row.style.transform = 'translateX('+sw.dx+'px)';
  });
  const fim = () => { if(!sw) return; const { row, w, dx, on } = sw; sw = null; row.style.transition = '';
    if(!on){ if(w.classList.contains('open')){ w.classList.remove('open'); row.style.transform = ''; } return; }
    if(dx < -row.offsetWidth*.6){ const b = w.querySelector('.cs-del'); if(b) b.click(); return; }
    if(dx < -50){ w.classList.add('open'); row.style.transform = 'translateX(-104px)'; } else { w.classList.remove('open'); row.style.transform = ''; } };
  document.addEventListener('pointerup', fim); document.addEventListener('pointercancel', fim);
}
wireCSwipe();
/* deslizar: linha da Lista para a direita marca tudo; tela do Foco troca de exercício */
function wireTSwipe(){
  let sw = null;
  document.addEventListener('pointerdown', ev => {
    if(ev.target.closest('.slide-knob') || ev.target.closest('input')) return;
    const row = ev.target.closest('.lrow[data-swipe]'), fo = !row && ev.target.closest('[data-fswipe]');
    if(!row && !fo) return;
    sw = { row, fo, x0:ev.clientX, y0:ev.clientY, dx:0, on:false };
  });
  document.addEventListener('pointermove', ev => {
    if(!sw) return;
    const dx = ev.clientX-sw.x0, dy = ev.clientY-sw.y0;
    if(!sw.on){ if(Math.abs(dx)>12 && Math.abs(dx)>Math.abs(dy)*1.4) sw.on = true; else if(Math.abs(dy)>12){ sw = null; return; } else return; }
    sw.dx = dx;
    if(sw.row){ const v = Math.max(0, Math.min(150, dx)); sw.row.classList.add('drag'); sw.row.style.transform = `translateX(${v}px)`; sw.row.parentNode.classList.toggle('sw', v>4); }
  });
  const end = () => {
    if(!sw) return;
    const s = sw; sw = null;
    if(!s.on) return;
    lpAt = Date.now();
    if(s.row){ s.row.classList.remove('drag'); s.row.style.transform = ''; if(s.dx>96) markAll(s.row.dataset.swipe); else s.row.parentNode.classList.remove('sw'); }
    else if(Math.abs(s.dx)>70) fNav(s.dx<0 ? 1 : -1);
  };
  document.addEventListener('pointerup', end); document.addEventListener('pointercancel', end);
}
function fNav(d){
  const c = tCtx(), ex = c.p.ex, cur = curOf(c) || {id:ex[0].id, i:0}, k = ex.findIndex(e => e.id===cur.id), e = ex[Math.max(0, Math.min(ex.length-1, k+d))];
  if(!e || e.id===cur.id) return;
  ui.tsel = {id:e.id, i:Math.max(0, pendIdx(e))}; render();
}
function tStepAct(d){
  const id = d.ex, i = +d.i, x = getLog(id)[i]; if(!x) return;
  if(d.f==='kg'){ const cur = x.kg!=='' ? num(x.kg) : (startKg(id)||0), st = step(cur||10); x.kg = fmtKg(Math.max(0, roundTo(cur + +d.d*st, st))); if(!x.ok) x.man = true; }
  else { const cur = x.reps!=='' ? int(x.reps) : repTarget(id,i); x.reps = String(Math.max(1, cur + +d.d)); }
  if(!ui.tsel || ui.tsel.id!==id || ui.tsel.i!==i){ if(tLay()!=='folha') ui.tsel = {id, i}; }
  save();
}
/* demonstrações dos jeitos de treinar (100 × 112) */
function tlThumb(id){
  const n = ++LAN, r = (x,y,w,h,rx,cls) => `<rect class="${cls}" x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}"/>`;
  const t = (x,y,s,cls,txt,anchor,extra) => `<text class="${cls}" x="${x}" y="${y}" font-size="${s}"${anchor?` text-anchor="${anchor}"`:''}${extra||''}>${txt}</text>`;
  const ck = (x,y,cls) => `<path class="${cls||'lt-ck'}" d="M${x-2} ${y+.2}l1.4 1.4 2.8 -3"/>`;
  const head = t(7,13,8,'la-h','Treino')+`<circle class="lt-a" cx="33.6" cy="12.2" r="1.2"/>`;
  const prog = w => r(6,17,88,2.5,1.25,'la-b')+`<rect class="lt-a ${w}" x="6" y="17" width="88" height="2.5" rx="1.25"/>`;
  let g = '';
  if(id==='lista'){
    g = head+prog('lv-pr')+r(6,23,88,11,5.5,'la-b')+`<circle class="lt-a" cx="12" cy="28.5" r="3"/>`+ck(12,28.5)+t(18,30.5,5,'la-tx la-b8','Agachamento');
    for(let j=0;j<4;j++) g += `<circle class="lt-a" cx="${76+j*4.4}" cy="28.5" r="1.4"/>`;
    g += r(6,37,88,48,8,'lt-p')+t(11,44,3.6,'lt-pm la-up','Agora · 2 de 6')+t(11,52,7,'lt-pf la-b9','Leg press');
    [['lt-a',''],['lt-a',''],['lt-pf lv-t3',''],['lt-pc lv-t4','']].forEach(([cl],j) => { g += r(11+j*20,56,18,10,3,cl)+t(20+j*20,62.6,3.8,'lt-pm la-b8','S'+(j+1),'middle'); });
    g += r(11,70,78,11,5.5,'lt-pc')+`<rect class="lt-a lv-fill" x="11" y="70" width="78" height="11" rx="5.5" opacity=".3"/>`+t(55,76.9,4.6,'lt-pf la-b8 lv-l3','Série 3 feita ›››','middle')+t(55,76.9,4.6,'lt-pf la-b8 lv-l4','Série 4 feita ›››','middle');
    g += `<g class="lv-knob"><circle class="lt-a" cx="16.5" cy="75.5" r="4.6"/>${ck(16.5,75.5,'lt-cko')}</g>`;
    g += r(6,88,88,11,5.5,'la-b')+`<circle class="la-b2" cx="12" cy="93.5" r="3"/>`+t(12,95,3.8,'la-tx la-b8','3','middle')+t(18,95.4,5,'la-tx la-b8','Extensora');
    g += `<g class="lv-dock">${r(6,95,88,14,6,'lt-dk')}${t(11,100.5,3.2,'la-mu la-up','Descanso')}${t(11,106.6,6,'la-tx la-b9','0:42','',' data-clk="rest" data-a0=".41" data-a1=".88"')}${r(56,98,15,8,4,'la-b')}${t(63.5,103.2,3.6,'la-tx la-b8','+15s','middle')}${r(73,98,17,8,4,'la-w')}${t(81.5,103.2,3.6,'la-wt la-b8','Pular','middle')}${r(11,107,78,1,.5,'la-b')}<rect class="lt-a lv-bar" x="11" y="107" width="78" height="1" rx=".5"/></g>`;
    g += `<g class="la-fgw lv-f">${laTap}</g>`;
  } else if(id==='foco'){
    g = t(6,11,3.8,'lt-at la-up','Treino A · 2 de 6')+t(94,11.5,5.4,'la-tx la-b9','24:18','end')+prog('ff-pr');
    g += t(6,31,11,'la-h','Leg press')+`<circle class="lt-a" cx="66" cy="30" r="1.6"/>`;
    for(let j=0;j<4;j++){ g += r(6+j*22.5,35,20.5,8,3,j<2?'lt-a':'la-b'); g += t(16.25+j*22.5,40.4,4,j<2?'lt-ao la-b8':'la-tx la-b8',String(j+1),'middle'); }
    g += `<rect class="lt-a ff-p3" x="51" y="35" width="20.5" height="8" rx="3"/><rect class="lt-o ff-o3" x="51.6" y="35.6" width="19.3" height="6.8" rx="2.6"/><rect class="lt-o ff-o4" x="74.1" y="35.6" width="19.3" height="6.8" rx="2.6"/>`;
    g += r(6,47,43,31,7,'la-b')+r(51,47,43,31,7,'la-b')+t(27.5,53.4,3.4,'la-mu la-up','Carga','middle')+t(72.5,53.4,3.4,'la-mu la-up','Reps','middle')+t(27.5,70,16,'la-tx la-b9','190','middle')+t(72.5,70,16,'la-tx la-b9','10','middle');
    g += `<g class="ff-btn">${r(6,82,88,19,8,'lt-a')}${ck(29,91.5,'lt-cko2')}${t(54,94,6.2,'lt-ao la-b9 ff-l3','Série 3 feita','middle')}${t(54,94,6.2,'lt-ao la-b9 ff-l4','Série 4 feita','middle')}</g>`;
    g += `<g class="ff-rest">${r(6,82,88,19,8,'la-b')}<rect class="lt-a ff-fill" x="6" y="82" width="88" height="19" rx="8" opacity=".32"/>${t(12,88.6,3.2,'la-mu la-up','Descanso')}${t(12,97.4,8,'la-tx la-b9','0:42','',' data-clk="rest" data-a0=".24" data-a1=".71"')}${r(54,86,17,11,5.5,'la-b2')}${t(62.5,92.8,3.8,'la-tx la-b8','+15s','middle')}${r(73,86,17,11,5.5,'la-w')}${t(81.5,92.8,3.8,'la-wt la-b8','Pular','middle')}</g>`;
    g += t(50,108,3.8,'la-mu','Depois: Cadeira extensora','middle');
    g += `<g class="la-fgw ff-f">${laTap}</g>`;
  } else if(id==='folha'){
    g = head+prog('fh-pr')+r(6,23,88,11,5.5,'la-b')+t(11,30.5,5,'la-tx la-b8','Agachamento')+`<circle class="lt-a" cx="87" cy="28.5" r="3.2"/>`+ck(87,28.5);
    g += t(7,43,6.6,'la-h','Leg press')+t(94,43,3.8,'la-mu la-b8','4 × 10–12','end');
    [['185','12'],['190','12'],['190','10'],['190','12']].forEach(([kg,rp],j) => { const y = 46+j*12.5;
      g += t(10,y+7.6,4,'la-mu la-b8',String(j+1),'middle')+t(16,y+8,6,j<2?'la-mu la-b9':'la-tx la-b9',kg+'<tspan font-size="3.4"> kg × </tspan>'+rp);
      g += `<circle class="lt-ring" cx="86" cy="${y+5.5}" r="4.4"/>`;
      if(j<2) g += `<circle class="lt-a" cx="86" cy="${y+5.5}" r="4.6"/>`+ck(86,y+5.5);
      if(j===2) g += `<g class="fh-c3"><circle class="lt-a" cx="86" cy="${y+5.5}" r="4.6"/>${ck(86,y+5.5)}</g>`; });
    g += `<rect class="lt-ob fh-o3" x="6.6" y="71.6" width="86.8" height="11" rx="4"/><rect class="lt-ob fh-o4" x="6.6" y="84.1" width="86.8" height="11" rx="4"/>`;
    g += `<g class="fh-pill">${r(6,98,88,11,5.5,'lt-inv')}${t(12,105.2,4.4,'lt-invt la-b8','Descanso')}${t(37,105.4,5.4,'lt-invt la-b9','0:42','',' data-clk="rest" data-a0=".28" data-a1=".84"')}${r(76,99.5,16,8,4,'lt-a')}${t(84,104.6,3.8,'lt-ao la-b8','Pular','middle')}</g>`;
    g += `<g class="la-fgw fh-f">${laTap}</g>`;
  } else {
    g = head+r(6,18,88,13,6,'la-b')+`<circle class="lt-a" cx="12" cy="24.5" r="1.4"/>`+t(16,26.6,5.6,'la-tx la-b9','24:18')+t(89,26.4,4.4,'la-mu la-b8','9 / 21','end');
    const blk = (y,name,done) => { let s = r(6,y,88,31,6,'la-b')+t(10,y+8,4.8,'la-tx la-b8',name);
      for(let j=0;j<4;j++){ const x = 10+j*20.5, cl = j<done ? 'lt-a' : 'la-b2'; s += r(x,y+12,18.5,14,4,cl)+t(x+9.25,y+20.6,4.4,j<done?'lt-ao la-b9':'la-tx la-b9',j<done?'190':'190','middle'); } return s; };
    g += blk(35,'Agachamento',4)+blk(70,'Leg press',2);
    g += `<rect class="lt-a gd-b3" x="51" y="82" width="18.5" height="14" rx="4"/><rect class="lt-o gd-o3" x="51.6" y="82.6" width="17.3" height="12.8" rx="3.6"/>`;
    g += `<g class="gd-pop">${r(18,50,66,20,5,'la-w')}${t(23,58,4.2,'la-wt la-b8','Série 3')}${t(23,65,4.2,'la-wt','190 kg × 10')}${r(54,56,26,9,4.5,'lt-a')}${t(67,61.6,3.8,'lt-ao la-b8','Confirmar','middle')}</g>`;
    g += `<g class="gd-rest">${r(1,1,98,110,14,'la-fr')}${t(50,22,8,'la-h','Respire','middle')}<circle class="lt-rt" cx="50" cy="56" r="16"/><circle class="lt-rf gd-ring" cx="50" cy="56" r="16" stroke-dasharray="100.5" transform="rotate(-90 50 56)"/>${t(50,59.4,9,'la-tx la-b9','0:59','middle',' data-clk="rest" data-a0=".39" data-a1=".86"')}${r(14,86,34,12,6,'la-b')}${t(31,93.6,4.2,'la-tx la-b8','+15s','middle')}${r(52,86,34,12,6,'lt-a')}${t(69,93.6,4.2,'lt-ao la-b8','Pular','middle')}</g>`;
    g += `<g class="la-fgw gd-f">${laTap}</g>`;
  }
  g += `<rect class="la-clock" x="0" y="0" width="1" height="1" opacity="0"/>`;
  return `<span class="lay-anim tlx la-t${id}"><svg viewBox="0 0 100 112" aria-hidden="true"><defs><clipPath id="lac${n}"><rect x="1" y="1" width="98" height="110" rx="14"/></clipPath></defs><rect class="la-fr" x="1" y="1" width="98" height="110" rx="14"/><g clip-path="url(#lac${n})">${g}</g><rect class="la-frl" x="1" y="1" width="98" height="110" rx="14"/></svg></span>`;
}
function confirmSet(id,i){
  const L = getLog(id), x = L[i], f = findEx(id), e = f.e; if(!x || x.ok) return;
  if(!S.session){ ui.pop = null; render(); toast('Arraste “Começar treino” para iniciar. Aí as séries contam.'); return; }
  const sg = suggest(id);
  if(!x.reps) x.reps = String(repTarget(id,i));
  if(!x.kg && startKg(id)!=null) x.kg = fmtKg(startKg(id));
  const prev = Math.max(S.pr[id] ? S.pr[id].e1 : 0, todayBest(id).v);
  x.ok = true; startSession(f.t); ui.t = f.t; ui.pop = null; ui.lastId = id; ui.just = {id,i};
  L.forEach((y,j) => { if(j>i && !y.ok && !y.man) y.kg = x.kg; });
  const v = e1(num(x.kg),int(x.reps)), nx = nextSet(f.t,id), isPR = S.pr[id] && v>prev+0.05;
  save();
  if(isPR) celebrate('Recorde pessoal', `${e.nome}: ${x.kg} kg × ${x.reps}.`, `1RM estimado ${fmtKg(Math.round(v))} kg, antes ${fmtKg(Math.round(prev))} kg`);
  if(nx) startRest(nx);
  else { ui.rest = null; renderRest(); if(!isPR) toast('Última série feita. Toque em Finalizar para ver o resumo.'); }
  if(exDone(e)) askExq(e);
  vibrate(15);
}

