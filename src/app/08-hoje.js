/* ---------- Hoje ---------- */
function vHoje(){
  const dateTxt = new Date().toLocaleDateString('pt-BR',{weekday:'long',day:'numeric',month:'long'});
  return header('Hoje',dateTxt)+exampleNote()+checkinCard()+treinoCard()+obrigCard(true)+cardioCard()+waterCard(true)+weekCard();
}
function checkinCard(){
  const ci = S.checkin[today()];
  if(ci && !ui.ciEdit){
    return `<section class="card ci-line"><span class="ci-ok" aria-hidden="true">${ic('check')}</span><span class="ci-tx"><b>Check-in feito</b><span>${ic('moon')}${sonoTxt(ci.sono)} · ${ic('bolt')}energia ${ci.energia} · ${ic('body')}dor ${DOR[ci.dor].toLowerCase()}</span></span><button type="button" class="btn round small" data-a="ci-edit" aria-label="Editar o check-in">${ic('edit')}</button></section>`;
  }
  const d = ui.ci || (ui.ci = ci ? Object.assign({},ci) : {sono:null,energia:null,dor:null});
  const ready = d.sono!=null && d.energia!=null && d.dor!=null;
  return `<section class="card checkin"><div class="card-h"><span class="label">${ic('sun')}Check-in do dia</span><span class="pill">3 toques</span></div>
    <h2 class="big">Como você chegou hoje?</h2>
    <div class="field"><span class="flabel ci-q ci-q-sono">${ic('moon')}Quanto você dormiu?</span>${seg('ci-sono',SONO,d.sono,SONO_L,'Horas de sono')}</div>
    <div class="field"><span class="flabel ci-q ci-q-en">${ic('bolt')}Energia</span>${seg('ci-energia',[1,2,3,4,5],d.energia,null,'Energia de 1 a 5')}<div class="scale"><span>baixa</span><span>alta</span></div></div>
    <div class="field"><span class="flabel ci-q ci-q-dor">${ic('body')}Dor muscular</span>${seg('ci-dor',[0,1,2,3],d.dor,DOR,'Dor muscular')}</div>
    <button type="button" class="btn primary block" data-a="ci-save"${ready?'':' disabled'}>${ic('check')}Salvar check-in</button>
    <p class="foot0">${isPersonal()?'Seu personal vê essas respostas e ajusta o treino se precisar.':'Num dia ruim, o app sugere repetir as cargas da última vez.'}</p></section>`;
}
function waterCard(compact){
  const t0 = today(), ml = S.water[t0]||0, g = S.waterGoal, hit = ml>=g, wp = waterPace(ml), L = S.wlog[t0]||[], last = L[L.length-1];
  const k = compact ? 'wh' : 'wa';
  const pill = hit ? `<span class="pill ok">${ic('check')}Meta batida</span>` : `<span class="pill">${wp.ok?'No ritmo':'Atrás do ritmo'}</span>`;
  const status = hit ? 'Meta do dia cumprida.' : 'Faltam '+fmtL(g-ml)+'. '+(wp.ok ? 'Você está no ritmo.' : 'Até agora o ideal era '+fmtL(wp.exp)+'.');
  const rt = remindTxt();
  return `<section class="card blk-water wcard hc">${HC_ART}<div class="card-h"><span class="label">${ic('drop')}Água</span>${pill}</div>
    <div class="water-body">${ring(k,ml/g,tw(k,ml,'L',fmtL(ml)),'de '+fmtL(g))}
    <div class="wbtns">${[200,300,500].map(v => `<button type="button" class="btn" data-a="water" data-ml="${v}" aria-label="Adicionar ${v} ml">+${v} ml</button>`).join('')}
    <button type="button" class="btn minus" data-a="water-minus" aria-label="Tirar 250 ml"${ml?'':' disabled'}>−250 ml</button></div></div>
    ${compact?'':`<p class="muted sm">${status}${rt?' '+rt+'.':''}</p>
    <button type="button" class="btn block" data-a="water" data-ml="500">${ic('plus')}Adicionar 500 ml</button>`}
    ${compact?`<button type="button" class="btn small ghost self-start" data-a="tab" data-v="agua">Ver a linha do dia${ic('chev')}</button>`:''}</section>`;
}
const WK_ART = '<span class="w-art" aria-hidden="true"><svg viewBox="0 0 372 330" preserveAspectRatio="none"><path d="M-20 236 C 70 186 150 300 240 240 S 340 176 400 214"/></svg></span>';
const WK_ART2 = '<span class="w-art" aria-hidden="true"><svg viewBox="0 0 372 330" preserveAspectRatio="none"><path class="rc-g" d="M-20 256 C 70 206 150 320 240 260 S 340 196 400 234"/><path class="rc-r" d="M-20 236 C 70 186 150 300 240 240 S 340 176 400 214"/></svg></span>';
const HC_ART = '<span class="w-art" aria-hidden="true"><svg viewBox="0 0 372 300" preserveAspectRatio="none"><path d="M-20 214 C 70 192 150 236 240 212 S 340 190 400 206"/></svg></span>';
const TDAYS_L = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];
// aquecimento: o personal pode personalizar as séries (% da carga × reps); sem isso, 50% × 10 e 75% × 5
const aqSeries = e => { const W = (Array.isArray(e.aqW) ? e.aqW : []).map(w => w && typeof w==='object' ? [+w.p, +w.r] : null).filter(w => w && w[0]>0 && w[1]>0).slice(0,5); return W.length ? W.map(w => [Math.min(1, w[0]/100), Math.round(w[1])]) : [[.5,10],[.75,5]]; };
const diasLivres = () => !!(ACCT && (ACCT.mode==='personal' || ACCT.mode==='self') && !(ACCT.dias||[]).length); // sem dia fixo: treina no dia que der
const tDays = () => ACCT && Array.isArray(ACCT.dias) && ACCT.dias.length ? ACCT.dias : diasLivres() ? [0,1,2,3,4,5,6] : [0,1,2,3,4];
const isTrainDay = () => tDays().includes((new Date().getDay()+6)%7);
function treinoCard(){
  if(noPlan()) return noPlanCard();
  const t0 = today(), done = S.history.some(h => h.date===t0), nd = diasLivres() ? SEMANA_TREINOS : tDays().length, wk = weekTrainCount(0);
  if(!S.session && !done && !ui.forceTrain){
    if(nd && wk>=nd) return weekDoneCard(wk,nd);
    if(!isTrainDay()) return restCard();
  }
  if(done && !S.session && !ui.forceTrain) return doneCard();
  return workoutCard();
}
/* treino do dia já feito: mostra o que foi feito e o resumo (e não o botão de começar de novo) */
function doneCard(){
  const t0 = today(), i = S.history.map(h => h.date).lastIndexOf(t0), h = S.history[i], p = PLAN[h.t] || {nome:'Treino '+h.t}, prs = (h.prs||[]).length;
  return `<section class="card wcard wkc wk-done wk-${['A','B','C','D','E'].includes(h.t)?h.t:'A'}" aria-label="Treino de hoje feito: ${p.nome}"><div class="card-h"><span class="label">${ic('dumbbell')}Treino de hoje</span><span class="pill">${ic('check')}Feito</span></div>
    <div><h2 class="big">Treino feito<span class="dot" aria-hidden="true"></span></h2><p class="muted">${p.nome} · ${h.min} min · ${h.sets} série${h.sets===1?'':'s'} · ${fmtN(h.vol)} kg${prs?' · '+prs+' recorde'+(prs>1?'s':''):''}</p></div>
    <button type="button" class="btn primary block" data-a="hist" data-i="${i}">${ic('chart')}Ver resumo</button>
    <button type="button" class="linkbtn" data-a="force-train">Treinar de novo mesmo assim</button></section>`;
}
function restCard(){
  return `<section class="card wcard restc" aria-label="Hoje é dia de descanso">${WK_ART2}<div class="card-h"><span class="label">${ic('dumbbell')}Hoje</span><span class="pill">Sem treino</span></div>
    <div><h2 class="big">Descanso<span class="dot" aria-hidden="true"></span></h2><p class="muted">Hoje o treino é recuperar. Que tal 20 min de caminhada leve?</p></div>
    <div class="chips"><span class="chip">Alongamento</span><span class="chip">Caminhada leve</span><span class="chip">Sono</span></div>
    <button type="button" class="btn block" data-a="tab" data-v="treino">Ver treinos da semana</button>
    <button type="button" class="linkbtn" data-a="force-train">Treinar mesmo assim</button></section>`;
}
function weekDoneCard(n,tot){
  const cm = weekDays(0).reduce((a,k) => a+cardioOn(k),0);
  return `<section class="card wcard restc" aria-label="Semana de treinos completa">${WK_ART2}<span class="rc-seal" aria-hidden="true">${ic('check')}</span><div class="card-h"><span class="label">${ic('dumbbell')}Esta semana · ${Math.min(n,tot)} de ${tot}</span></div>
    <div><h2 class="big">Semana feita<span class="dot" aria-hidden="true"></span></h2><p class="muted">${n>tot?n+' treinos, '+(n-tot)+' além da meta':Math.min(n,tot)+' de '+tot+' treinos'}. Você fechou a semana. Descanse e volte segunda.</p></div>
    <div class="chips"><span class="chip">${n} treino${n===1?'':'s'}</span><span class="chip">${cm} min de cardio</span></div>
    <button type="button" class="btn block" data-a="tab" data-v="evo">Ver resumo da semana</button>
    <button type="button" class="linkbtn" data-a="force-train">Treinar mesmo assim</button></section>`;
}
function workoutCard(){
  const t = nextTreino(), p = PLAN[t], ses = S.session, t0 = today(), done = S.history.some(h => h.date===t0);
  const tot = totalSets(t), dn = doneSetsPlan(t), prs = livePRs(t).length, low = lowReady(S.checkin[t0]);
  const prog = ses
    ? `<div>${bar(dn/tot)}<p class="muted sm" style="margin-top:8px">${dn} de ${tot} séries feitas${prs?' · '+prs+' recorde'+(prs>1?'s':'')+' hoje':''}</p></div>`
    : `<div class="chips">${p.ex.slice(0,2).map(e => `<span class="chip">${e.nome}</span>`).join('')}${p.ex.length>2?`<span class="chip">+${p.ex.length-2}</span>`:''}</div>`;
  const pill = done ? `<span class="pill">${ic('check')}Feito</span>` : ses ? '<span class="pill">Em andamento</span>' : '';
  return `<section class="card wcard wkc wk-${['A','B','C','D','E'].includes(t)?t:'A'}" aria-label="Treino de hoje: ${p.nome}">${WK_ART}<div class="card-h"><span class="label">${ic('dumbbell')}Treino de hoje</span>${pill}</div>
    <div><h2 class="big">${p.nome}<span class="dot" aria-hidden="true"></span></h2><p class="muted">${p.foco} · ${p.ex.length} exercícios · cerca de ${p.min} min</p></div>${prog}
    ${low&&!done?`<p class="warn">${ic('moon')}<span>Seu check-in pede um dia mais leve. As cargas sugeridas repetem a última vez.</span></p>`:''}
    ${ses ? `<button type="button" class="btn primary block" data-a="go-treino" data-t="${t}">${ic('timer')}Continuar · <span data-tick="ses">${fmtDur(elapsed())}</span></button>` : slideStart(t,'Começar treino '+p.nome)}</section>`;
}
/* cardio obrigatório do personal: um dia conta quando o aluno faz aquele aparelho por pelo menos o tempo pedido */
const DAYF = { seg:'seg', ter:'ter', qua:'qua', qui:'qui', sex:'sex', sab:'sáb', dom:'dom' };
const obMin = (o,k) => S.cardio.filter(c => c.date===k && c.local===o.local).reduce((a,c) => a+c.min, 0);
function obInfo(o){
  const ds = weekDays(0), t0 = today(), tk = DAYK[(new Date().getDay()+6)%7], alvo = o.dias.length || o.vezes || 1;
  const feitos = ds.filter(k => k<=t0 && obMin(o,k)>=o.min).length, hoje = obMin(o,t0), okHoje = hoje>=o.min;
  const devido = o.dias.length ? o.dias.includes(tk) : feitos<alvo;
  return { alvo, feitos, hoje, okHoje, devido, semOk:feitos>=alvo };
}
function obrigCard(home){
  if(!OBRIG.length) return '';
  const rows = OBRIG.map((o,i) => { const x = obInfo(o), quando = o.dias.length ? o.dias.map(d => DAYF[d]).join(', ') : x.alvo+'x por semana';
    const st = x.okHoje ? `<span class="pill ok">${ic('check')}Feito hoje</span>` : x.semOk ? `<span class="pill ok">${ic('check')}Semana feita</span>` : x.devido ? `<span class="pill warn">Para hoje</span>` : '';
    return `<div class="ob-r"><span class="ob-i">${eqIc(o.local,'')}</span><div class="ob-t"><b>${LOC_S[o.local]} · ${o.min} min</b><span>${quando} · ${x.feitos} de ${x.alvo} na semana${!x.okHoje && x.hoje ? ' · hoje '+x.hoje+' de '+o.min+' min' : ''}</span>${o.obs ? `<span class="ob-o">${esc(o.obs)}</span>` : ''}</div>
      ${st || !(x.okHoje || (!x.devido && !home)) ? `<div class="ob-a">${st}${x.okHoje || (!x.devido && !home) ? '' : `<button type="button" class="btn small${x.devido?' primary':''}" data-a="ob-go" data-i="${i}">${ic('play')}Fazer ${o.min} min</button>`}</div>` : ''}</div>`; }).filter(Boolean).join('');
  return `<section class="card ob-c" aria-label="Cardio pedido pelo seu personal"><div class="card-h"><span class="label">${ic('pulse')}Cardio do seu personal</span></div>${rows}<p class="foot0">Conta quando você registra esse aparelho com pelo menos o tempo pedido no dia.</p></section>`;
}
function cardioCard(){
  const cMin = cardioOn(today()), wk = weekDays(0).reduce((a,k) => a+cardioOn(k),0), ok = cMin>=CARDIO.min;
  const pill = ok ? `<span class="pill ok">${ic('check')}Meta batida</span>` : `<span class="pill">Faltam ${CARDIO.min-cMin} min</span>`;
  return `<section class="card blk-cardio wcard hc">${HC_ART}<div class="card-h"><span class="label">${ic('pulse')}Cardio</span>${pill}</div>
    <div class="water-body">${ring('ch',cMin/CARDIO.min,tw('ch',cMin,'m',cMin+' min'),'de '+CARDIO.min+' min')}
    <div><h2 class="big" style="font-size:34px">${CARDIO.min} min · ${CARDIO.zona}</h2><p class="muted sm" style="margin-top:6px">${CARDIO.desc}</p></div></div>
    <button type="button" class="btn block" data-a="tab" data-v="cardio">${S.cardioLive ? ic('timer')+'Cardio ao vivo · <span data-tick="cardio">'+fmtDur(cardioElapsed())+'</span>' : ic('plus')+'Registrar cardio'}</button></section>`;
}
function weekCard(){
  const ds = weekDays(0), t0 = today(), idx = ds.indexOf(t0);
  const tr = S.history.filter(h => ds.includes(h.date)), vol = tr.reduce((a,h) => a+h.vol,0);
  const pds = weekDays(-1).slice(0,idx+1), pvol = S.history.filter(h => pds.includes(h.date)).reduce((a,h) => a+h.vol,0);
  const dv = pvol ? Math.round((vol-pvol)/pvol*100) : null;
  const cm = ds.reduce((a,k) => a+cardioOn(k),0), wd = ds.slice(0,idx+1).filter(k => (S.water[k]||0)>=S.waterGoal).length, st = streak();
  const days = ds.map((k,i) => {
    const h = S.history.find(x => x.date===k), w = (S.water[k]||0)>=S.waterGoal, c = cardioOn(k)>0;
    return `<div class="day${h?' t':''}${k===t0?' now':''}${i>idx?' fut':''}"><span>${WD[i]}</span><div class="d">${h?h.t:''}</div><div class="marks"><span class="m m-w${w?' on':''}">${ic('drop')}</span><span class="m m-c${c?' on':''}">${ic('pulse')}</span></div></div>`;
  }).join('');
  return `<section class="card"><div class="card-h"><span class="label">${ic('cal')}Sua semana</span><span class="pill ok">${ic('flame')}${st} semana${st===1?'':'s'} na meta</span></div>
    <div class="week-row">${days}</div>
    <button type="button" class="btn small ghost self-start" data-a="tab" data-v="evo">Ver os números na Evolução${ic('chev')}</button></section>`;
}

