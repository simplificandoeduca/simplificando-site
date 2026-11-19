/* ---------- Evolução ---------- */
function niceTicks(mn,mx){
  if(mn===mx){ mn -= 5; mx += 5; }
  const raw = (mx-mn)/3, st = [1,2,2.5,5,10,20,25,50,100].find(s => s>=raw) || 200;
  const lo = Math.floor(mn/st)*st, hi = Math.ceil(mx/st)*st, r = [];
  for(let v=lo; v<=hi+1e-9; v+=st) r.push(Math.round(v*100)/100);
  if(r.length<2) r.push(lo+st);
  return r;
}
function loadChart(id){
  const a = S.loadHist[id] || [], f = findEx(id);
  if(a.length<2) return '<p class="muted sm">O gráfico aparece depois de dois treinos com este exercício.</p>';
  const W = 320, H = 184, Lm = 38, R = 20, T = 34, B = 24, vals = a.map(x => x.kg), tk = niceTicks(Math.min(...vals),Math.max(...vals));
  const lo = tk[0], hi = tk[tk.length-1];
  const X = i => Lm+i*(W-Lm-R)/(a.length-1), Y = v => T+(hi-v)/(hi-lo)*(H-T-B);
  const grid = tk.map(v => `<line class="grid" x1="${Lm}" x2="${W-R+8}" y1="${Y(v).toFixed(1)}" y2="${Y(v).toFixed(1)}"/><text x="${Lm-7}" y="${(Y(v)+3.5).toFixed(1)}" text-anchor="end">${fmtKg(v)}</text>`).join('');
  const pts = a.map((x,i) => [X(i),Y(x.kg)]);
  const line = pts.map((p,i) => (i?'L':'M')+p[0].toFixed(1)+','+p[1].toFixed(1)).join(' ');
  const area = line+` L${pts[pts.length-1][0].toFixed(1)},${Y(lo).toFixed(1)} L${pts[0][0].toFixed(1)},${Y(lo).toFixed(1)} Z`;
  const xi = [...new Set([0, Math.floor((a.length-1)/2), a.length-1])];
  const xl = xi.map(i => `<text x="${X(i).toFixed(1)}" y="${H-6}" text-anchor="${i===0?'start':i===a.length-1?'end':'middle'}">${dm(a[i].d)}</text>`).join('');
  const lp = pts[pts.length-1], lab = fmtKg(vals[vals.length-1])+' kg', lw = lab.length*7.4+16;
  const lx = Math.min(W-lw-2, lp[0]-lw/2), ly = lp[1]-34;
  return `<svg class="lchart" viewBox="0 0 ${W} ${H}" role="img" aria-label="Carga de ${f.e.nome}: de ${fmtKg(vals[0])} kg em ${dm(a[0].d)} para ${fmtKg(vals[vals.length-1])} kg em ${dm(a[a.length-1].d)}">${grid}
    <path class="area" d="${area}"/><path class="ln" d="${line}"/>
    ${pts.slice(0,-1).map(p => `<circle class="pt" cx="${p[0].toFixed(1)}" cy="${p[1].toFixed(1)}" r="3.5"/>`).join('')}
    <circle class="last" cx="${lp[0].toFixed(1)}" cy="${lp[1].toFixed(1)}" r="7"/>
    <rect class="lab" x="${lx.toFixed(1)}" y="${ly.toFixed(1)}" width="${lw.toFixed(1)}" height="24" rx="12"/><text class="labt" x="${(lx+lw/2).toFixed(1)}" y="${(ly+16.5).toFixed(1)}" text-anchor="middle">${lab}</text>${xl}</svg>`;
}
function calendar(){
  const base = new Date(); base.setDate(1); base.setMonth(base.getMonth()+ui.month);
  const y = base.getFullYear(), m = base.getMonth(), nd = new Date(y,m+1,0).getDate(), lead = (new Date(y,m,1).getDay()+6)%7, t0 = today();
  let cells = WD.split('').map(l => `<span class="wd">${l}</span>`).join('') + '<span class="cd empty"></span>'.repeat(lead);
  let ntr = 0, nca = 0, nwa = 0;
  for(let d=1; d<=nd; d++){
    const k = dk(new Date(y,m,d)), tr = S.history.some(h => h.date===k), ca = cardioOn(k)>0, wa = (S.water[k]||0)>=S.waterGoal, fut = k>t0;
    if(!fut){ ntr += tr; nca += ca; nwa += wa; }
    const cls = fut ? ' fut' : (tr ? ' tr'+(ca?' both':'') : ca ? ' ca' : '');
    cells += `<span class="cd${cls}${k===t0?' now':''}"><b>${d}</b>${wa&&!fut?'<i></i>':''}</span>`;
  }
  const title = base.toLocaleDateString('pt-BR',{month:'long',year:'numeric'});
  return `<section class="card"><div class="cal-h"><button type="button" class="btn round" data-a="month" data-d="-1" aria-label="Mês anterior"${ui.month<=-11?' disabled':''}>${ic('chevL')}</button>
    <b>${title.charAt(0).toUpperCase()+title.slice(1)}</b>
    <button type="button" class="btn round" data-a="month" data-d="1" aria-label="Próximo mês"${ui.month>=0?' disabled':''}>${ic('chev')}</button></div>
    <div class="cal" aria-hidden="true">${cells}</div>
    <p class="muted sm">${ntr} treino${ntr===1?'':'s'}, ${nca} dia${nca===1?'':'s'} com cardio e ${nwa} dia${nwa===1?'':'s'} na meta de água.</p>
    <div class="cal-leg"><span><i></i>Treino</span><span><i class="c"></i>Cardio</span><span><i class="b"></i>Os dois</span><span><i class="w"></i>Água na meta</span></div></section>`;
}
/* telas vazias da Evolução: desenho, título e um convite para começar */
const EMP_ILL = {"carga": "<svg viewBox=\"0 0 120 90\" aria-hidden=\"true\"><rect x=\"10\" y=\"70\" width=\"100\" height=\"4\" rx=\"2\" fill=\"var(--sep)\"/><rect x=\"18\" y=\"52\" width=\"14\" height=\"18\" rx=\"4\" fill=\"var(--ctl)\"/><rect x=\"40\" y=\"40\" width=\"14\" height=\"30\" rx=\"4\" fill=\"var(--ctl)\"/><rect x=\"62\" y=\"28\" width=\"14\" height=\"42\" rx=\"4\" fill=\"var(--ctl)\"/><rect x=\"84\" y=\"12\" width=\"14\" height=\"58\" rx=\"4\" fill=\"var(--brand)\"/><path d=\"M22 46 L46 34 L68 22 L92 6\" stroke=\"var(--fg)\" stroke-width=\"3\" fill=\"none\" stroke-linecap=\"round\" stroke-dasharray=\"4 6\"/></svg>", "rec": "<svg viewBox=\"0 0 120 90\" aria-hidden=\"true\"><circle cx=\"60\" cy=\"40\" r=\"28\" fill=\"var(--ctl)\"/><path d=\"M46 26h28v12a14 14 0 0 1-28 0z\" fill=\"#FFC83D\"/><path d=\"M46 30h-8v3a8 8 0 0 0 8 8M74 30h8v3a8 8 0 0 1-8 8\" stroke=\"#FFC83D\" stroke-width=\"3\" fill=\"none\"/><rect x=\"56\" y=\"52\" width=\"8\" height=\"8\" fill=\"#E0A800\"/><rect x=\"48\" y=\"60\" width=\"24\" height=\"6\" rx=\"3\" fill=\"#E0A800\"/><circle cx=\"96\" cy=\"16\" r=\"3\" fill=\"var(--brand)\"/><circle cx=\"24\" cy=\"22\" r=\"2.5\" fill=\"var(--brand)\"/></svg>", "hist": "<svg viewBox=\"0 0 120 90\" aria-hidden=\"true\"><rect x=\"22\" y=\"10\" width=\"76\" height=\"66\" rx=\"12\" fill=\"var(--ctl)\"/><rect x=\"22\" y=\"10\" width=\"76\" height=\"16\" rx=\"8\" fill=\"var(--brand)\"/><rect x=\"32\" y=\"34\" width=\"10\" height=\"10\" rx=\"3\" fill=\"var(--fg)\"/><rect x=\"47\" y=\"34\" width=\"10\" height=\"10\" rx=\"3\" fill=\"var(--sep)\"/><rect x=\"62\" y=\"34\" width=\"10\" height=\"10\" rx=\"3\" fill=\"var(--sep)\"/><rect x=\"77\" y=\"34\" width=\"10\" height=\"10\" rx=\"3\" fill=\"var(--sep)\"/><rect x=\"32\" y=\"50\" width=\"10\" height=\"10\" rx=\"3\" fill=\"var(--sep)\"/><rect x=\"47\" y=\"50\" width=\"10\" height=\"10\" rx=\"3\" fill=\"var(--sep)\"/><rect x=\"62\" y=\"50\" width=\"10\" height=\"10\" rx=\"3\" fill=\"var(--sep)\"/><rect x=\"77\" y=\"50\" width=\"10\" height=\"10\" rx=\"3\" fill=\"var(--sep)\"/></svg>"};
const empBox = (k,t,d,cta) => `<div class="emp">${EMP_ILL[k]}<b>${t}</b><span>${d}</span>${cta && !S.session ? `<button type="button" class="btn primary" data-a="tab" data-v="treino">${ic('dumbbell')}${S.history.length ? 'Ir para o treino' : 'Começar meu primeiro treino'}</button>` : ''}</div>`;
function vEvo(){
  const st = streak(), t0 = today(), mk = t0.slice(0,7);
  const wk8 = Array.from({length:8},(_,i) => i-7).map(off => { const c = weekTrainCount(off); return `<div class="${c>=streakMin()?'on':''}${off===0?' cur':''}"><i>${c}</i><span>${dm(weekDays(off)[0])}</span></div>`; }).join('');
  const mTr = S.history.filter(h => h.date.slice(0,7)===mk), mCa = S.cardio.filter(c => c.date.slice(0,7)===mk).reduce((a,c) => a+c.min,0);
  const mPr = mTr.reduce((a,h) => a+(h.prs||[]).length,0) + (noPlan() ? 0 : livePRs(nextTreino()).length)*(S.session?1:0);
  const order = LETTERS.flatMap(t => PLAN[t].ex.map(e => e.id)).filter(id => (S.loadHist[id]||[]).length>=2);
  if(!order.includes(ui.chartEx)) ui.chartEx = order.includes('agach') ? 'agach' : order[0];
  let loadSec = empBox('carga','Sua evolução começa no 2º treino','Depois de dois treinos salvos, aqui aparece o gráfico de quanto suas cargas subiram.',true);
  if(ui.chartEx){
    const id = ui.chartEx, a = S.loadHist[id], f = findEx(id), pr = S.pr[id], sg = suggest(id);
    const dlt = a[a.length-1].kg-a[0].kg, wks = Math.max(1,Math.round(diffDays(a[a.length-1].d,a[0].d)/7));
    loadSec = `<div class="scroller" role="group" aria-label="Escolher exercício">${order.map(x => `<button type="button" class="opt ${x===id?'on':''}" aria-pressed="${x===id}" data-a="chart" data-v="${x}">${findEx(x).e.nome}</button>`).join('')}</div>
      ${loadChart(id)}
      <div class="kv"><div><b>${dlt>0?'+':''}${fmtKg(dlt)} kg</b><span>em ${wks} semana${wks===1?'':'s'}</span></div>
      <div><b>${pr?fmtKg(Math.round(pr.e1)):'-'} kg</b><span>1RM estimado${pr?' · '+fmtKg(pr.kg)+' × '+pr.reps:''}</span></div>
      <div><b>${sg.kg!=null?fmtKg(sg.kg)+' kg':'Teste'}</b><span>sugestão do próximo ${f.t}</span></div>
      <div><b>${a.length}</b><span>treinos registrados</span></div></div>`;
  }
  const prs = Object.keys(S.pr).filter(id => findEx(id) && (S.loadHist[id]||[]).length>=2).sort((x,y) => S.pr[y].d.localeCompare(S.pr[x].d)).slice(0,5).map(id => {
    const p = S.pr[id];
    return `<div class="row"><span class="num pr">${ic('trophy')}</span><span class="main"><b>${findEx(id).e.nome}</b><span>${fmtKg(p.kg)} kg × ${p.reps} · 1RM estimado ${fmtKg(Math.round(p.e1))} kg</span></span><span class="when">${dayLabel(p.d)}</span></div>`;
  }).join('') || empBox('rec','Nenhum recorde ainda','Cada vez que você levantar mais que antes, o recorde aparece aqui com um troféu.',false);
  const cds = Array.from({length:7},(_,i) => daysAgo(6-i));
  const cell = (c,key) => {
    if(!c) return '<td><span class="cv none">·</span></td>';
    const v = c[key], flag = key==='sono' ? v<=5 : key==='energia' ? v<=2 : v>=3;
    return `<td><span class="cv${flag?' flag':''}">${key==='sono'?v+'h':v}</span></td>`;
  };
  const ciT = `<table class="citab"><caption class="sr">Check-ins dos últimos 7 dias</caption><thead><tr><th></th>${cds.map(k => `<th scope="col">${k===t0?'hoje':WD[(parse(k).getDay()+6)%7]}</th>`).join('')}</tr></thead><tbody>
    <tr><th scope="row">Sono</th>${cds.map(k => cell(S.checkin[k],'sono')).join('')}</tr>
    <tr><th scope="row">Energia</th>${cds.map(k => cell(S.checkin[k],'energia')).join('')}</tr>
    <tr><th scope="row">Dor</th>${cds.map(k => cell(S.checkin[k],'dor')).join('')}</tr></tbody></table>`;
  const hist = S.history.map((h,i) => [h,i]).reverse().slice(0,8).map(([h,i]) =>
    `<button type="button" class="row" data-a="hist" data-i="${i}"><span class="num">${h.t}</span><span class="main"><b>Treino ${h.t} · ${(PLAN[h.t]||{nome:'Treino'}).nome}</b><span>${fmtMin(h.min)} · ${h.sets} séries · ${fmtN(h.vol)} kg${h.rpe?' · PSE '+h.rpe:''}${(h.prs||[]).length?' · '+h.prs.length+' recorde'+(h.prs.length>1?'s':''):''}</span>${h.note?`<span>${ic('msg')} ${esc(h.note.length>46?h.note.slice(0,46)+'...':h.note)}</span>`:''}</span><span class="when">${dayLabel(h.date)}</span></button>`
  ).join('') || empBox('hist','Seu histórico está vazio','Seus treinos ficam guardados aqui, dia por dia.',true);
  const mesNome = new Date().toLocaleDateString('pt-BR',{month:'long'});
  // os números desta semana (antes ficavam no Hoje)
  const ds = weekDays(0), di = ds.indexOf(t0), wTr = S.history.filter(h => ds.includes(h.date)), wCa = ds.reduce((a,k) => a+cardioOn(k),0);
  const wAg = ds.slice(0,di+1).filter(k => (S.water[k]||0)>=S.waterGoal).length, wPr = wTr.reduce((a,h) => a+((h.prs||[]).length),0);
  const semana = `<section class="card ev-sem"><div class="card-h"><span class="label">${ic('cal')}Esta semana</span></div><div class="ev-semg">
    <div class="${wTr.length>=SEMANA_TREINOS?'ok':''}"><b>${wTr.length}<small>/${SEMANA_TREINOS}</small></b><span>treinos</span></div>
    <div class="${S.cardioGoal && wCa>=S.cardioGoal?'ok':''}"><b>${wCa}<small>/${S.cardioGoal}</small></b><span>min de cardio</span></div>
    <div class="${wAg>=7?'ok':''}"><b>${wAg}<small>/7</small></b><span>dias de água</span></div>
    <div class="${wPr?'ok':''}"><b>${wPr}</b><span>recorde${wPr===1?'':'s'}</span></div></div></section>`;
  const vazio = !S.history.length; // sem nenhum treino: um aviso só, no lugar de carga, recordes e histórico vazios
  return header('Evolução','Seu progresso')+exampleNote()+
    `<section class="card blk-cream stk-card"><div class="card-h"><span class="label">${ic('flame')}Sequência</span><span class="pill">${streakMin()}+ treinos por semana</span></div>
      <div class="st-big">${tw('st',st,'i',String(st))}<span>semana${st===1?'':'s'} seguida${st===1?'':'s'}<br>na meta de treinos</span></div>
      <div class="wk8" aria-label="Treinos por semana nas últimas 8 semanas">${wk8}</div></section>`+semana+
    `<div class="tiles"><div class="tile blk-brand">${ic('dumbbell')}${tw('m-tr',mTr.length,'i',String(mTr.length))}<span>treino${mTr.length===1?'':'s'} em ${mesNome}</span></div>
      <div class="tile blk-cardio">${ic('pulse')}${tw('m-ca',mCa,'i',String(mCa))}<span>min de cardio no mês</span></div>
      <div class="tile t-orange">${ic('trophy')}${tw('m-pr',mPr,'i',String(mPr))}<span>recorde${mPr===1?'':'s'} no mês</span></div></div>`+
    calendar()+
    (vazio ? `<section class="card blk-cream">${empBox('carga','Sua evolução começa no 1º treino','Depois dos treinos, aqui aparecem o gráfico das cargas, os recordes e o histórico de cada dia.',true)}</section>` :
    `<h3 class="sec">Evolução de carga<span class="dot" aria-hidden="true"></span></h3><section class="card blk-cream">${loadSec}</section>`+
    `<h3 class="sec">Recordes<span class="dot" aria-hidden="true"></span></h3><div class="list">${prs}</div>`)+
    `<h3 class="sec">Check-ins<span class="dot" aria-hidden="true"></span></h3><section class="card">${ciT}<p class="foot0">Em destaque: sono de 5h ou menos, energia 2 ou menos, dor 3 (forte). Dor vai de 0 (nenhuma) a 3.</p></section>`+
    (vazio ? '' : `<h3 class="sec">Histórico<span class="dot" aria-hidden="true"></span></h3><div class="list">${hist}</div>`)+
    `<p class="foot">Os registros ficam salvos neste aparelho. Ajustes e preferências ficam no seu perfil (toque na engrenagem).</p>`;
}

