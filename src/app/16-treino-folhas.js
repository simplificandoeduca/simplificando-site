/* ---------- folhas ---------- */
const sheetTop = (eyebrow,title) => `<div class="sheet-top" data-drag><div class="grabber" aria-hidden="true"></div><div class="sheet-h"><div><p class="eyebrow">${eyebrow}</p><h2 id="sh-t">${title}</h2></div><button type="button" class="btn round" data-a="close" aria-label="Fechar">${ic('x')}</button></div></div>`;
function vSheet(id){
  const f = findEx(id), e = f.e, L = getLog(id), last = lastFor(id), sg = suggest(id), pane = ui.pane;
  const p = PLAN[f.t], idx = p.ex.indexOf(e), nxt = p.ex[idx+1];
  const partner = e.sup ? findEx(e.sup).e : (idx>0 && p.ex[idx-1].sup===id ? p.ex[idx-1] : null);
  let body = '';
  if(pane==='series'){
    const pr = S.pr[id], tb = todayBest(id);
    const sug = `<div class="sug tone-${sg.tone}"><div class="card-h"><span class="label">${ic('bolt')}Carga sugerida hoje</span><span class="tagc">${{up:'Subir',keep:'Manter',hold:'Dia leve',new:'Primeira vez'}[sg.tone]}</span></div>
      <b class="sug-kg">${sg.kg!=null?fmtKg(sg.kg)+'<small> kg</small>':'Teste'}</b><p>${sg.why}</p>
      ${last?`<p class="sug-last">Última vez (${dayLabel(last.d)}${last.g?' · '+esc(last.g):''}): ${fmtKg(last.kg)} kg × ${last.reps.join(', ')}${pr?' · recorde '+fmtKg(pr.kg)+' × '+pr.reps:''}</p>`:''}</div>`;
    let warm = '';
    if(e.aq){
      const ws = (S.warm[tDia()]||{})[id] || [];
      warm = `<div class="warm"><span class="flabel">${ic('flame')}Aquecimento antes da primeira série</span><div class="warm-row">${warmPills(e, ws, 'warm', '')}</div>${warmNota(e)}</div>`;
    }
    const bs = partner ? `<p class="warn">${ic('layers')}<span><b>Bi-set</b>Faça junto com ${partner.nome}, sem descanso entre os dois. Descanse ${e.d}s depois do par.</span></p>` : '';
    const rows = L.map((x,i) => {
      const isPR = x.ok && pr && tb.i===i && tb.v>pr.e1+0.05;
      return `<div class="set${x.ok?' ok':''}${isPR?' pr':''}"><span class="n">${isPR?ic('trophy')+'<span class="sr">Recorde</span>':i+1}</span>
        <input inputmode="decimal" autocomplete="off" aria-label="Carga da série ${i+1} em kg" placeholder="${sg.kg!=null?fmtKg(sg.kg):'kg'}" value="${esc(x.kg)}" data-set="kg" data-ex="${id}" data-i="${i}">
        <input inputmode="numeric" autocomplete="off" aria-label="Repetições da série ${i+1}" placeholder="${repTarget(id,i)}" value="${esc(x.reps)}" data-set="reps" data-ex="${id}" data-i="${i}">
        <button type="button" class="check" aria-label="Série ${i+1} feita" aria-pressed="${x.ok}" data-a="setok" data-i="${i}">${ic('check')}</button>
        ${x.ok?`<div class="rir"><span>Quantas ainda sairiam?</span>${[0,1,2,3].map(n => `<button type="button" class="rb${x.rir===n?' on':''}" aria-pressed="${x.rir===n}" aria-label="Sobraram ${n===3?'3 ou mais':n} na série ${i+1}" data-a="rir" data-i="${i}" data-v="${n}">${n===3?'3+':n}</button>`).join('')}</div>`:''}</div>`;
    }).join('');
    body = pObs(e)+sug+warm+bs+
      `<div class="sets-h"><h3>Suas séries</h3><span class="muted sm">${doneSets(id)} de ${L.length} feitas</span></div>
      <div class="sets"><div class="set set-head" aria-hidden="true"><span>#</span><span>Carga (kg)</span><span>Reps</span><span>Feita</span></div>${rows}</div>
      <div class="row-btns"${isPersonal()?' style="grid-template-columns:1fr"':''}>${isPersonal()?'':`<button type="button" class="btn" data-a="addset">${ic('plus')}Série extra</button>`}<button type="button" class="btn primary" data-a="rest">${ic('timer')}Descanso ${REST}s</button></div>
      ${excBox(id, e)}
      ${nxt?`<button type="button" class="row nextex" data-a="open" data-ex="${nxt.id}"><span class="main"><span>Próximo exercício</span><b>${nxt.nome}</b></span>${ic('chev','chev')}</button>`:''}`;
  } else if(pane==='exec'){
    body = `<div class="exa" data-ex="${e.id}" role="img" aria-label="Animação da execução: ${esc(e.nome)}"><svg viewBox="0 0 480 420" font-family="Inter, system-ui, sans-serif" aria-hidden="true"></svg></div>
      ${pObs(e)}${(e.cues||[]).length?`<h3>Execução</h3><ol class="cues">${e.cues.map(c => `<li>${c}</li>`).join('')}</ol>`:''}
      ${e.erro?`<p class="warn">${ic('x')}<span><b>Evite</b>${e.erro.charAt(0).toUpperCase()+e.erro.slice(1)}</span></p>`:''}
      <button type="button" class="btn primary block" data-a="pane" data-v="series">${ic('dumbbell')}Ir para as séries</button>`;
  } else {
    const a = (S.loadHist[id]||[]).slice().reverse().slice(0,6), pr = S.pr[id];
    body = `${loadChart(id)}
      ${pr?`<div class="kv"><div><b>${fmtKg(pr.kg)} kg × ${pr.reps}</b><span>recorde (${dayLabel(pr.d)})</span></div><div><b>${fmtKg(Math.round(pr.e1))} kg</b><span>1RM estimado</span></div></div>`:''}
      <div class="list">${a.map(x => `<div class="row"><span class="main"><b>${fmtKg(x.kg)} kg</b></span><span class="when">${dayLabel(x.d)} · ${dm(x.d)}</span></div>`).join('') || '<div class="row"><span class="main"><span>Ainda sem histórico.</span></span></div>'}</div>
      <p class="foot0">1RM estimado pela fórmula de Epley: carga × (1 + repetições / 30).</p>`;
  }
  return `<div class="sheet-wrap" data-a="closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop('Treino '+f.t+' · '+e.alvo, e.nome)}
    <div class="presc"><div><b>${e.s}</b><span>séries</span></div><div><b>${e.r}</b><span>repetições</span></div><div><b>${e.d}s</b><span>descanso</span></div></div>
    ${trocaPode() && !doneSets(id) ? `<button type="button" class="btn small tr-btn" data-a="troca-open" data-ex="${e.id}">${ic('swap')}Trocar exercício</button>` : ''}
    ${seg('pane',['series','exec','hist'],pane,['Séries','Execução','Histórico'],'Seções do exercício')}<div class="pane">${body}</div></div></div>`;
}
function vSummary(i){
  const h = S.history[i]; if(!h) return '';
  const p = PLAN[h.t] || {nome:'Treino'}, prev = S.history.slice(0,i).reverse().find(x => x.t===h.t);
  const dv = prev && prev.vol ? Math.round((h.vol-prev.vol)/prev.vol*100) : null;
  const isNew = h.date===today() && i===S.history.length-1;
  const prd = h.prd || (h.prs||[]).map(id => ({id}));
  const prRows = prd.map(r => { const f = findEx(r.id); if(!f) return '';
    return `<div class="row"><span class="num pr">${ic('trophy')}</span><span class="main"><b>${f.e.nome}</b><span>${r.kg!=null?`${fmtKg(r.kg)} kg × ${r.reps} · 1RM estimado ${fmtKg(Math.round(r.e1))} kg, antes ${fmtKg(Math.round(r.prev))} kg`:'Recorde de carga neste dia'}</span></span></div>`; }).join('');
  return `<div class="sheet-wrap" data-a="closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop('Treino '+h.t+' · '+p.nome+' · '+dayLabel(h.date)+(h.gym?' · '+esc(h.gym):''), (isNew?'Treino concluído':'Resumo do treino')+'<span class="dot" aria-hidden="true"></span>')}
    <div class="sum-grid sg4"><div class="sg-min">${tw('s-min',h.min,'i',String(h.min))}<span>minuto${h.min===1?'':'s'} de treino</span></div>
    <div class="sg-vol">${tw('s-vol',h.vol,'i',fmtN(h.vol))}<span>kg de volume${dv!=null?` · ${dv>=0?'+':''}${dv}% sobre o último ${h.t}`:''}</span></div>
    <div class="sg-sets">${tw('s-sets',h.sets,'i',String(h.sets))}<span>série${h.sets===1?'':'s'} feita${h.sets===1?'':'s'}</span></div>
    <div class="sg-pr">${tw('s-pr',prd.length,'i',String(prd.length))}<span>recorde${prd.length===1?'':'s'} pessoa${prd.length===1?'l':'is'}</span></div></div>
    <button type="button" class="btn block st-open" data-a="st-open" data-i="${i}">${ic('share')}Compartilhar no story</button>
    ${prRows?`<h3>Recordes</h3><div class="list">${prRows}</div>`:''}
    ${h.date===today() && (h.ex||[]).length ? `<h3>Como foi cada exercício</h3><div class="list">${h.ex.filter(x => findEx(x.id)).map(x => `<button type="button" class="row" data-a="exq-open" data-ex="${x.id}" data-back="${i}"><span class="num">${ic(((x.q||[]).length || x.c) ? 'check' : 'msg')}</span><span class="main"><b>${esc(x.n)}</b><span>${((x.q||[]).length || x.c) ? esc([...(x.q||[]), x.c].filter(Boolean).join(' · ')) : 'Toque para contar como foi'}</span></span>${ic('chev','chev')}</button>`).join('')}</div>` : ''}
    <div class="field"><span class="flabel">${ic('bolt')}Esforço do treino, de 1 a 10</span><div class="rpe" role="group" aria-label="Esforço do treino">${Array.from({length:10},(_,j) => j+1).map(n => `<button type="button" class="${h.rpe===n?'on':''}" aria-pressed="${h.rpe===n}" data-a="rpe" data-v="${n}">${n}</button>`).join('')}</div><div class="scale"><span>muito leve</span><span>máximo</span></div></div>
    <div class="field"><label class="flabel" for="sum-note">${ic('msg')}${isPersonal()?'Mensagem pro personal':'Anotação do treino'}</label><textarea id="sum-note" rows="3" data-note="${i}" placeholder="Ex.: senti o joelho no afundo">${esc(h.note||'')}</textarea></div>
    <button type="button" class="btn primary block" data-a="sum-save">${ic('check')}Salvar</button>
    <p class="foot0">${isPersonal()?'Fica salvo junto com o treino para o seu personal ver.':'Fica salva junto com o treino, no seu histórico.'}</p></div></div>`;
}

