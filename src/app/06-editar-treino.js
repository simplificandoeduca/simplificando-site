function toOwn(){
  const c = S.conta; if(!c || ownPlan()) return;
  const base = JSON.parse(JSON.stringify(c.plan==='ab' ? PLAN_AB : PLAN_ABC)), ls = Object.keys(base).sort();
  ls.forEach(l => { base[l].dias = []; base[l].g = []; (base[l].ex||[]).forEach(x => { delete x.cad; delete x.tec; delete x.sup; }); });
  (c.dias||[]).forEach((d,i) => base[ls[i % ls.length]].dias.push(d));
  c.treinos = base; c.plan = 'meu';
}
/* letras sempre em sequência: apagou treinos e sobraram B e E? viram A e B (os vazios vão para o fim).
   O treino aberto, o treino escolhido e o histórico acompanham a letra nova. */
function mtLetras(){
  const c = S.conta, T = c && c.treinos; if(!T) return false;
  const old = Object.keys(T).filter(l => T[l]).sort(), cheio = old.filter(l => (T[l].ex||[]).length), ord = cheio.concat(old.filter(l => !(T[l].ex||[]).length));
  if(ord.every((l,i) => l==='ABCDEF'[i])) return false;
  const N = {}, mapa = {}; ord.forEach((l,i) => { N['ABCDEF'[i]] = T[l]; mapa[l] = 'ABCDEF'[i]; });
  c.treinos = N;
  if(S.session && mapa[S.session.t]) S.session.t = mapa[S.session.t];
  if(ui.t) ui.t = mapa[ui.t] || null;
  if(ui.sheet && ui.sheet.k==='meus' && ui.sheet.ed) ui.sheet.ed = mapa[ui.sheet.ed] || null;
  // o histórico dos treinos que continuam vai junto (a rotação "próximo treino" segue certa); o dos apagados fica como foi feito
  const apag = new Set(Object.keys(mapa).filter(l => mapa[l]!==l));
  (S.history||[]).forEach(h => { if(h && apag.has(h.t) && mapa[h.t]) h.t = mapa[h.t]; });
  const L = cheio.map(l => mapa[l]);
  if(cheio.some(l => mapa[l]!==l)) toast(L.length===1 ? 'Seu treino agora é o A.' : 'Letras arrumadas: seus treinos agora são '+L.slice(0,-1).join(', ')+' e '+L[L.length-1]+'.');
  return true;
}
function mtFix(){
  const c = S.conta;
  Object.keys(c.treinos||{}).forEach(l => {
    const t = c.treinos[l]; t.ex = t.ex||[]; t.dias = t.dias||[]; t.g = (t.g||[]).filter(x => GNOME[x]); t.min = estMin(t.ex);
    t.foco = t.g.length ? focoTxt(t.g.slice(0,4)) : (joinPt([...new Set(t.ex.map(e => e.alvo).filter(Boolean))].slice(0,3)) || 'Treino livre');
  });
  applyAccount(); if(ui.t && !PLAN[ui.t]) ui.t = null; ui.tsel = null; ui.pop = null; save();
}
function mtOpen(){ if(!ACCT || ACCT.mode!=='self') return; toOwn(); mtFix(); ui.sheet = { k:'meus', ed:null, pick:false, q:'', pg:'', cn:'', cg:'' }; renderSheet(true); }
function iaOpen(){
  if(!ACCT || ACCT.mode!=='self') return;
  const c = S.conta || {};
  IQ = c.ia ? Object.assign(IQ0(), JSON.parse(JSON.stringify(c.ia))) : IQ0();
  if(!c.ia && tDays().length && tDays().length<7) IQ.dias = tDays().slice();
  ui.sheet = { k:'ia', res:null, busy:false }; renderSheet(true);
}
function exThumb(e, sfx){
  const svg = XA.figure(e, 0, true, sfx);
  return svg ? `<span class="mt-th" aria-hidden="true"><svg viewBox="${XA.FIGVB}">${svg}</svg></span>` : `<span class="mt-th none" aria-hidden="true">${ic('dumbbell')}</span>`;
}
const shWrap = inner => `<div class="sheet-wrap" data-a="closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${inner}</div></div>`;
/* aquecimento no treino que a pessoa monta (igual ao painel do personal): séries em % da carga do dia × repetições; o app calcula o peso */
const MT_AQ = [['Padrão',[[50,10],[75,5]]],['Leve',[[40,12]]],['Pesado',[[40,10],[60,6],[80,3]]],['Só 1 série',[[60,8]]]].map(([n,W]) => [n, W.map(w => ({p:w[0], r:w[1]}))]);
const mtAqTxt = W => W.map(w => w.p+'% × '+w.r).join(' · ');
const mtAqW = e => { if(!Array.isArray(e.aqW) || !e.aqW.length || e.aqW.some(w => !w || typeof w!=='object' || Array.isArray(w))) e.aqW = MT_AQ[0][1].map(w => Object.assign({}, w)); return e.aqW; };
function mtAq(e,i){
  const on = !!e.aq, head = `<div class="mt-aqh"><span class="mt-aqi">${ic('flame')}</span><div><b>Aquecimento</b><span>${on ? 'Antes da 1ª série · '+mtAqTxt(mtAqW(e)) : 'Séries leves antes da 1ª série'}</span></div>
    <button type="button" class="mt-sw" role="switch" aria-checked="${on}" data-a="mt-aq" data-i="${i}" aria-label="Aquecimento em ${esc(e.nome)}"><span class="knob"></span></button></div>`;
  if(!on) return `<div class="mt-aq">${head}</div>`;
  const W = mtAqW(e), cur = mtAqTxt(W);
  const rows = W.map((w,j) => `<div class="mt-aqr"><span class="mt-aqn">${j+1}ª</span>
      <div class="mt-st"><button type="button" data-a="mt-aqp" data-i="${i}" data-j="${j}" data-d="-5" aria-label="Menos carga na série ${j+1} do aquecimento"${w.p>10?'':' disabled'}>${ic('minus')}</button><b>${w.p}%</b><button type="button" data-a="mt-aqp" data-i="${i}" data-j="${j}" data-d="5" aria-label="Mais carga na série ${j+1} do aquecimento"${w.p<100?'':' disabled'}>${ic('plus')}</button></div>
      <span class="mt-aqx">×</span>
      <div class="mt-st"><button type="button" data-a="mt-aqr" data-i="${i}" data-j="${j}" data-d="-1" aria-label="Menos repetições na série ${j+1} do aquecimento"${w.r>1?'':' disabled'}>${ic('minus')}</button><b>${w.r}</b><button type="button" data-a="mt-aqr" data-i="${i}" data-j="${j}" data-d="1" aria-label="Mais repetições na série ${j+1} do aquecimento"${w.r<30?'':' disabled'}>${ic('plus')}</button></div>
      ${W.length>1 ? `<button type="button" class="btn round mt-aqdel" data-a="mt-aqdel" data-i="${i}" data-j="${j}" aria-label="Tirar a série ${j+1} do aquecimento">${ic('x')}</button>` : '<span class="mt-aqdel"></span>'}</div>`).join('');
  return `<div class="mt-aq on">${head}<div class="mt-aqrs">${rows}</div>
    <div class="mt-aqm" role="group" aria-label="Modelos prontos de aquecimento">${MT_AQ.map(([n,M],k) => `<button type="button" class="${mtAqTxt(M)===cur?'on':''}" aria-pressed="${mtAqTxt(M)===cur}" data-a="mt-aqmod" data-i="${i}" data-k="${k}"><b>${n}</b><span>${mtAqTxt(M)}</span></button>`).join('')}</div>
    ${W.length<5 ? `<button type="button" class="btn small mt-aqadd" data-a="mt-aqadd" data-i="${i}">${ic('plus')}Série de aquecimento</button>` : ''}
    <p class="foot0">O app mostra o peso de cada série a partir da sua carga do dia. Ex.: carga 40 kg e 50% = 20 kg.</p></div>`;
}
function vMeus(){
  const sh = ui.sheet, c = S.conta, T = c.treinos || {}, ls = Object.keys(T).sort();
  if(sh.ed && T[sh.ed]) return shWrap(sh.pick ? vMtPick(sh, T[sh.ed]) : vMtEdit(sh, T[sh.ed], T));
  const week = TDAYS_L.map((d,i) => { const l = ls.find(x => (T[x].dias||[]).includes(i)); return `<div class="mt-wd${l?' on':''}"><span>${d}</span><b>${l||'–'}</b></div>`; }).join('');
  const cards = ls.map(l => { const t = T[l], ds = (t.dias||[]).map(i => TDAYS_L[i]), n = t.ex.length;
    return `<button type="button" class="row" data-a="mt-open" data-v="${l}"><span class="num">${l}</span><span class="main"><b>${esc(t.nome||'Treino '+l)}</b><span>${n} exercício${n===1?'':'s'}${n?' · cerca de '+t.min+' min':''} · ${ds.length?joinPt(ds):'qualquer dia'}</span></span>${ic('chev','chev')}</button>`; }).join('');
  return shWrap(`${sheetTop(c.plan==='ia'?'Montado pela IA · edite à vontade':'Seu plano', 'Meus treinos<span class="dot" aria-hidden="true"></span>')}
    <p class="muted mt-lead">Um treino para cada dia, ou o mesmo treino em vários dias. Dê o nome que quiser e escolha os exercícios.</p>
    <div class="mt-week" aria-label="Sua semana">${week}</div>
    ${ls.length ? `<div class="list">${cards}</div>` : `<div class="mt-empty"><span class="ico" aria-hidden="true">${ic('dumbbell')}</span><b>Nenhum treino ainda</b><span>Crie o primeiro: dê um nome, escolha os dias e adicione os exercícios.</span></div>`}
    ${ls.length<6 ? `<button type="button" class="btn primary block" data-a="mt-new">${ic('plus')}${ls.length?'Novo treino':'Criar meu primeiro treino'}</button>` : '<p class="foot0">Você chegou ao limite de 6 treinos.</p>'}
    <div class="mt-ia"><span class="ico" aria-hidden="true">${ic('spark')}</span><div><b>Prefere que a IA monte?</b><span>Responda 3 perguntas e receba um treino pronto. Seu histórico continua.</span></div><button type="button" class="btn small" data-a="ia-open">Usar IA</button></div>`);
}
function vMtEdit(sh, t, T){
  const l = sh.ed, other = d => Object.keys(T).find(x => x!==l && (T[x].dias||[]).includes(d));
  const days = TDAYS_L.map((d,i) => { const on = t.dias.includes(i), o = !on && other(i); return `<button type="button" class="${on?'on':''}" aria-pressed="${on}" data-a="mt-day" data-v="${i}" aria-label="${d}${o?', hoje com o treino '+o:''}">${d}${o?`<small>${o}</small>`:''}</button>`; }).join('');
  const gs = GRUPOS.map(([k,n]) => togBtn('mt-g',k,t.g.includes(k),n,'mt-chip')).join('');
  const rows = t.ex.map((e,i) => `<div class="mt-ex">
      <div class="mt-exh">${exThumb(e,'m'+i)}<div class="mt-exn"><b>${esc(e.nome)}</b><span>${esc(e.alvo||'')}</span></div>
        <div class="mt-mv"><button type="button" class="btn round" data-a="mt-mv" data-i="${i}" data-d="-1" aria-label="Subir ${esc(e.nome)}"${i?'':' disabled'}>${ic('up')}</button><button type="button" class="btn round" data-a="mt-mv" data-i="${i}" data-d="1" aria-label="Descer ${esc(e.nome)}"${i<t.ex.length-1?'':' disabled'}>${ic('down')}</button><button type="button" class="btn round" data-a="mt-rm" data-i="${i}" aria-label="Tirar ${esc(e.nome)} do treino">${ic('x')}</button></div></div>
      <div class="mt-pr">
        <div class="mt-f"><span>Séries</span><div class="mt-st"><button type="button" data-a="mt-s" data-i="${i}" data-d="-1" aria-label="Menos séries"${e.s>1?'':' disabled'}>${ic('minus')}</button><b>${e.s}</b><button type="button" data-a="mt-s" data-i="${i}" data-d="1" aria-label="Mais séries"${e.s<8?'':' disabled'}>${ic('plus')}</button></div></div>
        <label class="mt-f"><span>Repetições</span><input class="mt-in" data-mx="r" data-i="${i}" value="${esc(e.r)}" maxlength="14" autocomplete="off" aria-label="Repetições de ${esc(e.nome)}"></label>
        <div class="mt-f"><span>Descanso</span><div class="mt-st"><button type="button" data-a="mt-d" data-i="${i}" data-d="-15" aria-label="Menos descanso"${e.d>15?'':' disabled'}>${ic('minus')}</button><b>${e.d}s</b><button type="button" data-a="mt-d" data-i="${i}" data-d="15" aria-label="Mais descanso"${e.d<300?'':' disabled'}>${ic('plus')}</button></div></div>
      </div>${mtAq(e,i)}</div>`).join('');
  const armed = ui.arm==='mt-del:'+l;
  return `${sheetTop('Treino '+l, esc(t.nome||'Treino '+l)+'<span class="dot" aria-hidden="true"></span>')}
    <button type="button" class="linkbtn pk-back mt-back" data-a="mt-back">${ic('chevL')}Meus treinos</button>
    <div class="field"><label class="flabel" for="mt-nome">Nome do treino</label><div class="inp-wrap"><input class="inp" id="mt-nome" data-mt="nome" maxlength="40" placeholder="Ex.: Pernas e glúteo" value="${esc(t.nome||'')}" autocomplete="off"></div></div>
    <div class="field"><span class="flabel" id="mt-dl">Em quais dias? <small>(opcional)</small></span><div class="tdays mt-days" role="group" aria-labelledby="mt-dl">${days}</div><p class="foot0">${t.dias.length ? 'O treino '+l+' cai '+(t.dias.length===1?'na ':'em ')+joinPt(t.dias.map(i => TDAYS_L[i]))+'.' : 'Opcional. Sem dia fixo, o treino fica livre para qualquer dia. A letra pequena mostra o treino que já está naquele dia.'}</p></div>
    <div class="field"><span class="flabel" id="mt-gl">O que você vai treinar?</span><div class="mt-chips" role="group" aria-labelledby="mt-gl">${gs}</div></div>
    <div class="mt-exs-h"><h3>Exercícios${t.ex.length?' · '+t.ex.length:''}</h3>${t.ex.length?`<span class="foot0">cerca de ${t.min} min</span>`:''}</div>
    ${rows ? `<div class="mt-exs">${rows}</div>` : '<p class="muted mt-none">Nenhum exercício ainda. Adicione da lista, com a animação de como fazer, ou crie o seu.</p>'}
    <div class="row-btns"><button type="button" class="btn" data-a="mt-add">${ic('plus')}Adicionar</button><button type="button" class="btn" data-a="mt-sug"${t.g.length?'':' disabled'}>${ic('spark')}Sugerir</button></div>
    ${t.g.length ? '' : '<p class="foot0">Marque o que vai treinar para receber sugestões de exercícios.</p>'}
    <button type="button" class="btn primary block" data-a="mt-back">${ic('check')}Pronto</button>
    <button type="button" class="btn block ghost mt-del${armed?' armed':''}" data-a="mt-del">${ic('trash')}${armed?'Toque de novo para excluir':'Excluir este treino'}</button>`;
}
function mtPickRows(sh, t){
  const q = normTx(sh.q.trim());
  const AP = { quad:'perna coxa', post:'perna coxa posterior', glut:'gluteo bumbum perna quadril', pant:'perna batata', peito:'peitoral', costas:'dorsal', ombro:'deltoide', bic:'braco', tri:'braco', abd:'abdomen abs core barriga' }, tk = q.split(/\s+/).filter(Boolean);
  const L = CAT.filter(x => q ? tk.every(w => normTx(x.nome+' '+GNOME[x.g]+' '+(AP[x.g]||'')+' '+eqTxt(x.eq)).includes(w)) : !sh.pg || x.g===sh.pg || x.g2===sh.pg); // digitou: busca em todos os grupos
  if(!L.length) return '<p class="muted mt-none">Nada com esse nome. Crie o seu logo abaixo.</p>';
  const GI = g => GRUPOS.findIndex(x => x[0]===g); L.sort((a,b) => GI(a.g)-GI(b.g) || a.nome.localeCompare(b.nome,'pt-BR'));
  return L.map((x,i) => { const has = t.ex.some(e => e.id===x.id);
    return `<button type="button" class="row mt-prow${has?' has':''}" data-a="mt-pick" data-v="${x.id}" aria-pressed="${has}">${exThumb(x,'p'+i)}<span class="main"><b>${esc(x.nome)}</b><span>${GNOME[x.g]} · ${eqTxt(x.eq)}</span></span><span class="mt-plus" aria-hidden="true">${ic(has?'check':'plus')}</span></button>`; }).join('');
}
function vMtPick(sh, t){
  return `${sheetTop('Adicionar ao treino '+sh.ed, 'Exercícios<span class="dot" aria-hidden="true"></span>')}
    <button type="button" class="linkbtn pk-back mt-back" data-a="mt-pback">${ic('chevL')}Voltar ao treino</button>
    <div class="inp-wrap mt-q">${ic('search')}<input class="inp" id="mt-q" type="search" data-mt="q" placeholder="Buscar exercício" value="${esc(sh.q)}" autocomplete="off" aria-label="Buscar exercício"></div>
    <div class="mt-chips sc" role="group" aria-label="Filtrar por grupo">${togBtn('mt-pg','',!sh.pg,'Todos','mt-chip')}${GRUPOS.map(([k,n]) => togBtn('mt-pg',k,sh.pg===k,n,'mt-chip')).join('')}</div>
    <p class="foot0">Toque para adicionar. Toque de novo para tirar.</p>
    <div class="pk-box"><div class="list mt-list pk-res" id="mt-list">${mtPickRows(sh,t)}</div><div class="pk-bar" aria-hidden="true"><i></i></div></div>
    <div class="prefs mt-own"><div class="pref-h"><b>Não achou? Crie o seu</b><span>Escreva o nome e escolha o grupo.</span></div>
      <div class="inp-wrap"><input class="inp" id="mt-cn" data-mt="cn" maxlength="40" placeholder="Ex.: Cadeira adutora" value="${esc(sh.cn)}" autocomplete="off" aria-label="Nome do exercício"></div>
      <div class="mt-chips" role="group" aria-label="Grupo do exercício">${GRUPOS.map(([k,n]) => togBtn('mt-cg',k,sh.cg===k,n,'mt-chip')).join('')}</div>
      <button type="button" class="btn block" id="mt-cbtn" data-a="mt-custom"${cleanName(sh.cn)&&sh.cg?'':' disabled'}>${ic('plus')}Criar e adicionar</button></div>`;
}
function vIA(){
  const sh = ui.sheet;
  if(sh.busy) return shWrap(`${sheetTop('Treino pela IA','IA<span class="dot" aria-hidden="true"></span>')}${genView()}`);
  if(sh.res) return shWrap(`${sheetTop('Treino pela IA','Seu treino novo<span class="dot" aria-hidden="true"></span>')}${iaResult(sh.res, S.ed, IQ)}
    <p class="foot0">Seu histórico e suas cargas continuam. Depois dá para editar tudo em Meus treinos.</p>
    <button type="button" class="btn primary block" data-a="ia-use">${ic('check')}Usar este treino</button>
    <button type="button" class="btn block ghost" data-a="ia-redo">Mudar respostas</button>`);
  return shWrap(`${sheetTop('Treino pela IA','Responda e a IA monta<span class="dot" aria-hidden="true"></span>')}
    <p class="muted">A IA usa seu objetivo (${(ED[S.ed]||ED.hipertrofia).nome}) e estas respostas. O treino atual só muda quando você confirmar.</p>
    ${iqQ(1)}${iqQ(2)}${iqQ(3)}
    <button type="button" class="btn primary block" data-a="ia-gen">${ic('spark')}Montar meu treino</button>`);
}
function refocus(sel){ if(!sel) return; const n = $sheet.querySelector(sel); if(n && !n.disabled){ try { n.focus({preventScroll:true}); } catch(e){ /* foco opcional */ } } }
const selOf = b => '[data-a="'+b.dataset.a+'"]'+(b.dataset.v!=null?'[data-v="'+b.dataset.v+'"]':'')+(b.dataset.i!=null?'[data-i="'+b.dataset.i+'"]':'')+(b.dataset.d!=null?'[data-d="'+b.dataset.d+'"]':'');
/* toques no "Meus treinos" e na IA do perfil; devolve true quando tratou */
function mtClick(a, d, b, armed){
  const sh = ui.sheet;
  if(a==='mt-open-sheet'){ mtOpen(); return true; }
  if(a==='ia-open'){ iaOpen(); return true; }
  if(a.slice(0,3)==='ia-' && sh && sh.k==='ia'){
    if(a==='ia-gen'){ sh.busy = true; renderSheet(); setTimeout(() => { if(ui.sheet!==sh) return; sh.res = iaPlan(IQ, S.ed); sh.busy = false; renderSheet(); refocus('[data-a="ia-use"]'); }, RM() ? 300 : 1500); return true; }
    if(a==='ia-redo'){ sh.res = null; renderSheet(); return true; }
    if(a==='ia-use'){
      if(S.session){ toast('Finalize o treino em andamento antes de trocar.'); return true; }
      S.conta.treinos = sh.res; S.conta.plan = 'ia'; S.conta.ia = JSON.parse(JSON.stringify(IQ)); S.conta.semana = iqN(IQ);
      mtFix(); ui.t = null; closeSheet(); toast('Treino novo no ar. Seu histórico continua.'); return true;
    }
    return true;
  }
  if(a.slice(0,3)!=='mt-' || !sh || sh.k!=='meus') return false;
  const T = S.conta.treinos, t = sh.ed && T[sh.ed], i = +d.i;
  switch(a){
    case 'mt-new': { const l = 'ABCDEF'.split('').find(x => !T[x]); if(!l) break;
      T[l] = { nome:'', foco:'', g:[], min:0, dias:[], ex:[] };
      sh.ed = l; sh.pick = false; mtFix(); renderSheet(true); refocus('#mt-nome'); return true; }
    case 'mt-open': sh.ed = d.v; sh.pick = false; renderSheet(true); return true;
    case 'mt-back': sh.ed = null; sh.pick = false; mtLetras(); mtFix(); renderSheet(true); return true;
    case 'mt-pback': sh.pick = false; renderSheet(true); refocus('[data-a="mt-add"]'); return true;
    case 'mt-add': sh.pick = true; sh.q = ''; sh.pg = t && t.g[0] || ''; renderSheet(true); return true;
    case 'mt-pg': sh.pg = d.v; renderSheet(); refocus(selOf(b)); return true;
    case 'mt-cg': sh.cg = sh.cg===d.v ? '' : d.v; renderSheet(); refocus(selOf(b)); return true;
  }
  if(!t) return true;
  switch(a){
    case 'mt-day': { const v = +d.v, k = t.dias.indexOf(v);
      if(k>=0) t.dias.splice(k,1);
      else { const o = Object.keys(T).find(x => x!==sh.ed && (T[x].dias||[]).includes(v)); if(o){ T[o].dias = T[o].dias.filter(x => x!==v); toast(TDAYS_L[v]+' saiu do treino '+o+'.'); } t.dias.push(v); t.dias.sort((x,y) => x-y); }
      break; }
    case 'mt-g': { const k = t.g.indexOf(d.v); if(k>=0) t.g.splice(k,1); else t.g.push(d.v); break; }
    case 'mt-s': { const e = t.ex[i]; if(e) e.s = Math.max(1, Math.min(8, e.s + +d.d)); break; }
    case 'mt-aq': { const e = t.ex[i]; if(e){ e.aq = !e.aq; if(e.aq) mtAqW(e); } break; }
    case 'mt-aqp': case 'mt-aqr': { const e = t.ex[i], w = e && mtAqW(e)[+d.j]; if(!w) break; if(a==='mt-aqp') w.p = Math.max(10, Math.min(100, w.p + +d.d)); else w.r = Math.max(1, Math.min(30, w.r + +d.d)); break; }
    case 'mt-aqdel': { const e = t.ex[i], W = e && mtAqW(e); if(W && W.length>1) W.splice(+d.j,1); break; }
    case 'mt-aqadd': { const e = t.ex[i], W = e && mtAqW(e); if(W && W.length<5){ const l = W[W.length-1]; W.push({p:Math.min(100, (l ? l.p : 40)+15), r:Math.max(1, (l ? l.r : 10)-3)}); } break; }
    case 'mt-aqmod': { const e = t.ex[i], M = MT_AQ[+d.k]; if(e && M){ e.aq = true; e.aqW = M[1].map(w => Object.assign({}, w)); } break; }
    case 'mt-d': { const e = t.ex[i]; if(e) e.d = Math.max(15, Math.min(300, (+e.d||60) + +d.d)); break; }
    case 'mt-mv': { const j = i + +d.d; if(j>=0 && j<t.ex.length){ const x = t.ex[i]; t.ex[i] = t.ex[j]; t.ex[j] = x; } mtFix(); renderSheet(); refocus(`[data-a="mt-mv"][data-i="${j}"][data-d="${d.d}"]`); return true; }
    case 'mt-rm': { const x = t.ex.splice(i,1)[0]; if(x) toast(x.nome+' saiu do treino.'); break; }
    case 'mt-pick': { const k = t.ex.findIndex(e => e.id===d.v);
      if(k>=0){ t.ex.splice(k,1); }
      else { const x = catOf(d.v); if(!x) break; const ia = S.conta.ia || {}; t.ex.push(exFromCat(x, S.ed, ia.nivel||'int')); if(!t.g.includes(x.g)) t.g.push(x.g); }
      mtFix(); const lst = document.getElementById('mt-list'); if(lst){ const st = $sheet.querySelector('.sheet'), y = st ? st.scrollTop : 0; lst.innerHTML = mtPickRows(sh, t); if(st) st.scrollTop = y; refocus(selOf(b)); }
      toast(k>=0 ? 'Tirei do treino '+sh.ed+'.' : 'Adicionado ao treino '+sh.ed+'.'); return true; }
    case 'mt-custom': { const nome = cleanName(sh.cn); if(!nome || !sh.cg) return true;
      t.ex.push({ id:'u'+Date.now().toString(36)+Math.random().toString(36).slice(2,5), nome, alvo:GNOME[sh.cg], s:3, r:'10–12', d:60, cues:[], erro:'' });
      if(!t.g.includes(sh.cg)) t.g.push(sh.cg); sh.cn = ''; sh.cg = ''; sh.pick = false; mtFix(); renderSheet(true); toast(nome+' entrou no treino '+sh.ed+'.'); return true; }
    case 'mt-sug': { const ia = S.conta.ia || {}, loc = ia.local || 'a', lim = ia.lim || [], per = t.g.length<=2 ? 3 : t.g.length===3 ? 2 : 1; let n = 0;
      t.g.forEach(g => { CAT.filter(x => x.g===g && x.eq.includes(loc) && !x.noIa && !lim.some(k => x.lim.includes(k)) && !t.ex.some(e => e.id===x.id)).slice(0, per).forEach(x => { if(t.ex.length<10){ t.ex.push(exFromCat(x, S.ed, ia.nivel||'int')); n++; } }); });
      toast(n ? 'Sugeri '+n+' exercício'+(n===1?'':'s')+'. Ajuste séries e repetições se quiser.' : 'Já tem as sugestões para esses grupos.'); break; }
    case 'mt-del': if(armed==='mt-del:'+sh.ed){ const l = sh.ed; delete T[l]; sh.ed = null; toast('Treino '+l+' excluído.'); mtLetras(); mtFix(); renderSheet(true); return true; }
      ui.arm = 'mt-del:'+sh.ed; renderSheet(); refocus('[data-a="mt-del"]'); return true;
  }
  mtFix(); renderSheet(); refocus(selOf(b));
  return true;
}
function lgClick(a,d,b){
  switch(a){
    case 'lg-eye': LG.show = !LG.show; renderLogin('#lg-pw'); return true;
    case 'nv-sair': try { history.replaceState(null, '', location.pathname); } catch(e){ /* nada */ } LG.view = 'login'; LG.nv = null; renderLogin(); return true;
    case 'lg-eye1': LG.rs.s1 = !LG.rs.s1; renderLogin('#rs-1'); return true;
    case 'lg-eye2': LG.rs.s2 = !LG.rs.s2; renderLogin('#rs-2'); return true;
    case 'lg-back': { LG.view = 'login'; LG.err = ''; try { if(location.hash) history.replaceState(null,'',location.pathname+location.search); } catch(e){ /* sem histórico */ } if(readSess() && !ACCT){ startApp(); return true; } renderLogin('#lg-pw'); return true; }
    case 'lg-reset-open': try { location.hash = 'redefinir'; } catch(e){ /* sem hash */ } openReset(); return true;
    case 'lg-forgot': if(FB){ lgEsqueci(); return true; } lgForgot(); return true;
    case 'lg-close': { const w = document.querySelector('#login .sheet-wrap'); if(w) w.remove(); return true; }
    case 'lg-copy': lgCopy(); return true;
    case 'lg-self': selfStart(); return true;
    case 'lg-inst': LG.inst = !LG.inst; renderLogin(); return true;
    case 'su-back': if(LG.gen) return true; if(LG.step>1){ LG.step--; LG.serr = ''; renderLogin(); suTop(); } else { LG.view = 'login'; LG.serr = ''; renderLogin('#lg-pw'); } return true;
    case 'su-next': suNext(); return true;
    case 'su-eye': LG.f.show = !LG.f.show; renderLogin('#su-senha'); return true;
    case 'su-eye2': LG.en.show = !LG.en.show; renderLogin('#en-senha'); return true;
    case 'su-entrar': LG.view = 'entrar'; LG.serr = ''; LG.en = {email:LG.f.email||'', senha:'', show:false, msg:''}; renderLogin('#en-email'); return true;
    case 'su-criar': LG.view = 'setup'; LG.step = 1; LG.serr = ''; renderLogin('#su-nome'); return true;
    case 'su-esq': suForgot(); return true;
    case 'su-sexo': LG.f.sexo = d.v==='M' ? 'M' : 'F'; renderLogin(`[data-a="su-sexo"][data-v="${d.v}"]`); return true;
    case 'su-ed': LG.f.ed = d.v; renderLogin(`[data-a="su-ed"][data-v="${d.v}"]`); return true;
    case 'su-mode': if(d.v==='ia'){ IQ = IQ0(); LG.step = 4; renderLogin(); suTop(); } else suFinish('meu'); return true;
    case 'su-done': suFinish('ia'); return true;
    case 'su-ck': { const c = LG.f.ck; c[d.v] = !c[d.v]; renderLogin(`[data-a="su-ck"][data-v="${d.v}"]`); return true; }
    case 'lg-ck': { const c = LG.ac; c[d.v] = !c[d.v]; renderLogin(`[data-a="lg-ck"][data-v="${d.v}"]`); return true; }
    case 'lg-doc': if(LG.view!=='doc') LG.back = LG.view; LG.view = 'doc'; LG.doc = d.v; renderLogin(); suTop(); return true;
    case 'lg-doc-back': LG.view = LG.back || 'login'; renderLogin(); suTop(); return true;
    case 'lg-ac-sair': LG.view = 'login'; logout(); return true;
    case 'lg-ac-ok': { if(!ACCT) return true; const self = ACCT.mode==='self'; if(!ckOk(LG.ac, self)) return true;
      S.aceite = aceiteNovo(self); save(); perfilSync(true); LG.view = 'login'; LG.ac = {t:false, s:false, p:false}; startApp(); return true; }
  }
  return false;
}


/* ---------- trocar o exercício (só quem treina sem personal): no lugar de um exercício, um parecido.
   Ex.: sem halter para a elevação lateral → elevação lateral na polia, na máquina, desenvolvimento… A troca fica no treino;
   o exercício de antes aparece no topo para voltar quando quiser. ---------- */
const trocaPode = () => !!ACCT && ACCT.mode==='self';
const trGrupo = e => { const k = catOf(e.id); if(k) return k.g; const g = GRUPOS.find(x => normTx(x[1])===normTx(e.alvo)); return g ? g[0] : ''; };
const trPal = s => normTx(s).replace(/\b(com|na|no|de|do|da|em|e|a|o)\b/g,' ').split(/\s+/).filter(w => w.length>2);
function trocaLista(e, t){
  const k = catOf(e.id), g = trGrupo(e), pal = trPal(e.nome).slice(0,2), noTreino = new Set(((S.conta && S.conta.treinos && S.conta.treinos[t] || {}).ex || PLAN[t].ex).map(x => x.id));
  const nota = x => { let n = 0; if(x.g===g) n += 4; else if(x.g2===g || (k && k.g2===x.g)) n += 1;
    if(k){ if(x.t===k.t) n += 2; if(k.anim && (x.anim===k.anim || x.id===k.anim)) n += 3; if(x.anim && x.anim===k.id) n += 3; }
    const xp = trPal(x.nome); n += pal.filter(w => xp.includes(w)).length*2; return n; };
  return CAT.filter(x => x.id!==e.id && !noTreino.has(x.id) && (x.g===g || x.g2===g)).map(x => ({x, n:nota(x)})).sort((a,b) => b.n-a.n || a.x.nome.localeCompare(b.x.nome,'pt-BR'));
}
function vTroca(id){
  const f = findEx(id); if(!f) return '';
  const e = f.e, L = trocaLista(e, f.t), k = catOf(e.id);
  // os 4 mais parecidos + os 2 melhores do outro tipo (ex.: elevação lateral → desenvolvimento), para quem não tem o aparelho
  const top = L.filter(o => o.n>=6).slice(0,4); L.filter(o => !top.includes(o) && k && o.x.t!==k.t && o.x.g===k.g).slice(0,2).forEach(o => top.push(o));
  const resto = L.filter(o => !top.includes(o));
  const row = (x, i, extra) => `<button type="button" class="row mt-prow" data-a="troca" data-v="${x.id}">${exThumb(x,'tr'+i)}<span class="main"><b>${esc(x.nome)}</b><span>${extra || (GNOME[x.g]+' · '+eqTxt(x.eq))}</span></span><span class="mt-plus" aria-hidden="true">${ic('chev')}</span></button>`;
  const orig = e.orig && catOf(e.orig);
  return shWrap(`${sheetTop('Treino '+f.t+' · no lugar de '+esc(e.nome), 'Trocar exercício<span class="dot" aria-hidden="true"></span>')}
    <p class="muted tr-txt">Escolha um parecido. Ele entra no lugar de <b>${esc(e.nome)}</b> no treino ${f.t}, com as mesmas séries.</p>
    ${orig ? `<div class="list mt-list">${row(orig, 'o', 'Voltar para o exercício de antes')}</div>` : ''}
    ${top.length ? `<h3>Mais parecidos</h3><div class="list mt-list">${top.map((o,i) => row(o.x, i)).join('')}</div>` : ''}
    ${resto.length ? `<h3>${top.length ? 'Outros de ' : ''}${GNOME[trGrupo(e)] || 'Outros'}</h3><div class="list mt-list">${resto.map((o,i) => row(o.x, 'r'+i)).join('')}</div>` : ''}
    ${!L.length && !orig ? '<p class="muted">Não achei outro exercício para esse grupo. Dá para montar o seu em Perfil › Meus treinos.</p>' : ''}
    <button type="button" class="btn block" data-a="open" data-ex="${e.id}">${ic('chevL')}Voltar ao exercício</button>`);
}
function trocaFaz(oldId, newId){
  if(!trocaPode()) return;
  const f = findEx(oldId), x = catOf(newId); if(!f || !x) return;
  if(doneSets(oldId)){ toast('Você já fez séries desse exercício hoje. Troque no próximo treino.'); return; }
  toOwn(); const T = S.conta.treinos[f.t], ex = T && T.ex, i = ex ? ex.findIndex(e => e.id===oldId) : -1; if(i<0) return;
  if(ex.some(e => e.id===newId)){ toast(x.nome+' já está no treino '+f.t+'.'); return; }
  const v = ex[i], ia = S.conta.ia || {}, n = exFromCat(x, S.ed, ia.nivel||'int');
  n.s = v.s || n.s;
  if(v.aq && x.t==='c'){ n.aq = true; if(v.aqW) n.aqW = JSON.parse(JSON.stringify(v.aqW)); }
  if(v.sup) n.sup = v.sup;
  const orig = v.orig || v.id; if(orig!==newId && catOf(orig)) n.orig = orig;
  ex[i] = n; ex.forEach(e => { if(e.sup===oldId) e.sup = newId; });
  if(!T.g.includes(x.g)) T.g.push(x.g);
  const lg = S.logs[tDia()]; if(lg) delete lg[oldId];
  mtFix(); ui.sheet = {k:'ex', id:newId}; renderSheet(true); render();
  toast(newId===orig ? 'Voltou para '+x.nome+'.' : 'Trocado por '+x.nome+'.');
}
