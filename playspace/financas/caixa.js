/* Play Space Finanças 2.2 (Play Space 1.61): o Controle de caixa (a primeira tela das Finanças).
   Pedido do dono: escolher o dia (hoje, ontem ou a data), ver o que passou no caixa no crédito, no débito e no Pix, lançar ao lado o valor
   da Rede e o sistema dizer na hora se bateu (positivo) ou não (negativo), sem ninguém precisar comparar número com número.
   Os valores da Rede ficam guardados (por dia, unidade e forma), com quem lançou. Usa o que a tela das Finanças já tem:
   $, $$, esc, brl, dataBR, iso, api, aviso, corUni, nomeUni, UNIS. */
'use strict';
const CX = { dia: null, d: null, n: 0, s: 0, gerado: '' };
const CX_SEM = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
const CX_MES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const CX_IC = {
  ok: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  dif: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><path d="M8.5 8.5l7 7M15.5 8.5l-7 7" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/></svg>',
  falta: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="3.2 3"/><path d="M12 7.5v5M12 16v.01" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>',
  vazio: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 12h8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>'
};
const cxHoje = () => iso(new Date());
const cxMais = (d, n) => { const x = new Date(d + 'T12:00:00'); x.setDate(x.getDate() + n); return iso(x); };
const cxNomeDia = d => { const x = new Date(d + 'T12:00:00'); return `${CX_SEM[x.getDay()]}, ${x.getDate()} de ${CX_MES[x.getMonth()]} de ${x.getFullYear()}`; };
const cxRotulo = d => d === cxHoje() ? 'Hoje' : d === cxMais(cxHoje(), -1) ? 'Ontem' : '';
/* "1.225,50", "1225,5", "1225.50", "R$ 1.225" → 1225.5; vazio → null; o que não é número → NaN */
function cxNum(t) {
  t = String(t == null ? '' : t).replace(/R\$|\s| /g, ''); if (t === '') return null;
  if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.'); else if (/^\d{1,3}(\.\d{3})+$/.test(t)) t = t.replace(/\./g, '');
  if (!/^\d+(\.\d+)?$/.test(t)) return NaN;
  return Math.round(parseFloat(t) * 100) / 100;
}
const cxFmt = v => v == null ? '' : (+v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function cxSit(sis, rede) { if (rede == null || Number.isNaN(rede)) return Math.abs(sis) > 0.004 ? 'falta' : ''; return Math.abs(Math.round((sis - rede) * 100)) < 1 ? 'ok' : 'dif'; }
const CX_ORDEM = { '': 0, ok: 1, falta: 2, dif: 3 };

/* ---------- a tela ---------- */
function caixaDesenhar() {
  const t = $('#tela'); if (!t) return;
  if (!CX.dia) CX.dia = cxHoje();
  const hoje = cxHoje(), ontem = cxMais(hoje, -1), d = CX.dia, rot = cxRotulo(d);
  t.innerHTML = `<div class="cx-tela"><div class="topo"><div><h1>Controle de caixa</h1><div class="sub">O que passou no caixa no dia, conferido com a Rede</div></div>
      <div class="topo-d"><div class="sub" id="cx-gerado">${CX.gerado ? `Atualizado às ${esc(CX.gerado)} · ` : ''}<button class="link" style="display:inline;padding:0" id="cx-atualizar">atualizar</button></div></div></div>
    <div class="filtros"><div class="chips" role="group" aria-label="Dia do caixa">
      <button class="chip ${d === hoje ? 'on' : ''}" data-cxdia="${hoje}">Hoje</button><button class="chip ${d === ontem ? 'on' : ''}" data-cxdia="${ontem}">Ontem</button>
      <span class="cx-data"><button class="chip cx-passo" data-cxpasso="-1" aria-label="Dia anterior" title="Dia anterior">‹</button>
        <label class="chip cx-escolher ${d !== hoje && d !== ontem ? 'on' : ''}"><span>Escolher a data</span><input type="date" id="cx-data" value="${esc(d)}" max="${esc(hoje)}" aria-label="Escolher a data do caixa"></label>
        <button class="chip cx-passo" data-cxpasso="1" aria-label="Dia seguinte" title="Dia seguinte" ${d >= hoje ? 'disabled' : ''}>›</button></span></div></div>
    <h2 class="cx-titulo">${rot ? `<span>${rot}</span> · ` : ''}${esc(cxNomeDia(d))}</h2>
    <div id="cx-corpo">${CX.d && CX.d.dia === d ? cxCorpo() : '<div class="vazio">Carregando o caixa…</div>'}</div></div>`;
  $$('[data-cxdia]').forEach(b => b.onclick = () => cxIr(b.dataset.cxdia));
  $$('[data-cxpasso]').forEach(b => b.onclick = () => { const x = cxMais(CX.dia, +b.dataset.cxpasso); if (x <= cxHoje()) cxIr(x); });
  const dt = $('#cx-data'); if (dt) dt.onchange = () => { if (/^\d{4}-\d{2}-\d{2}$/.test(dt.value)) cxIr(dt.value > cxHoje() ? cxHoje() : dt.value); };
  $('#cx-atualizar').onclick = () => cxCarregar();
  if (CX.d && CX.d.dia === d) cxLigar(); else cxCarregar();
}
function cxIr(d) { if (d === CX.dia && CX.d && CX.d.dia === d) return; CX.dia = d; caixaDesenhar(); }
async function cxCarregar() {
  const n = ++CX.n, d = CX.dia, c = $('#cx-corpo'); if (c) c.classList.add('carregando');
  try { const r = await api('cx_dia', { dia: d }); if (n !== CX.n || ABA !== 'caixa') return; CX.d = r; CX.gerado = new Date().toTimeString().slice(0, 5); }
  catch (e) { if (n === CX.n) { aviso(e.message); const c2 = $('#cx-corpo'); if (c2) { c2.classList.remove('carregando'); if (!CX.d || CX.d.dia !== d) c2.innerHTML = `<div class="vazio">${esc(e.message)}</div>`; } } return; }
  if (CX.dia === d) caixaDesenhar();
}
function cxCorpo() {
  const D = CX.d;
  const cards = D.unidades.length ? D.unidades.map(cxCartao).join('') : '<div class="cartao"><div class="vazio">Nenhuma unidade cadastrada.</div></div>';
  return `${cards}
    <section class="cartao cx-hist"><div class="cab-cartao"><div><h2>Últimos ${D.hist.length} dias</h2><p class="expl">Toque num dia para abrir o caixa dele.</p></div>
      <div class="cx-leg"><span class="cx-s ok">${CX_IC.ok} bateu</span><span class="cx-s dif">${CX_IC.dif} não bateu</span><span class="cx-s falta">${CX_IC.falta} falta lançar</span><span class="cx-s vazio">${CX_IC.vazio} sem movimento</span></div></div>
      <div class="cx-dias">${D.hist.slice().reverse().map(h => { const x = new Date(h.dia + 'T12:00:00');
        return `<button class="cx-diab ${h.dia === D.dia ? 'on' : ''}" data-cxdia="${h.dia}" title="${esc(cxNomeDia(h.dia))}: ${cxSitTexto(h.sit)}"><small>${CX_SEM[x.getDay()].slice(0, 3)}</small><b>${h.dia.slice(8, 10)}/${h.dia.slice(5, 7)}</b><span class="cx-s ${h.sit || 'vazio'}">${CX_IC[h.sit || 'vazio']}</span></button>`; }).join('')}</div></section>`;
}
const cxSitTexto = s => ({ ok: 'bateu', dif: 'não bateu', falta: 'falta lançar a Rede', '': 'sem movimento' })[s || ''];
function cxCartao(U) {
  const nome = nomeUni(U.u), cor = corUni(U.u);
  return `<section class="cartao cx-uni" data-cxuni="${U.u}">
    <div class="cab-cartao"><div><h2><i class="uni-dot" style="background:${cor}"></i>${esc(nome)}</h2>
      <p class="expl">Total do caixa no dia: <b class="num">${brl(U.total)}</b> · cartões e Pix ${brl(U.sis)} · dinheiro ${brl(U.dinheiro)}${Math.abs(U.outras) > 0.004 ? ` · outras ${brl(U.outras)}` : ''}</p></div>
      <div class="cx-geral" data-cxgeral></div></div>
    <div class="cx-tab">
      <div class="cx-cab"><span>Forma</span><span class="v">No sistema</span><span>Na Rede <small>(digite o valor)</small></span><span>Conferência</span></div>
      ${U.formas.map(F => `<div class="cx-linha" data-cxf="${F.f}" data-sis="${F.sis}">
        <span class="cx-forma">${esc(F.nome)}</span>
        <span class="cx-sis num"><small class="cx-mob">No sistema</small>${brl(F.sis)}</span>
        <label class="cx-rede"><small class="cx-mob">Na Rede</small><span class="cx-campo"><span class="cx-rs">R$</span><input class="inp num" inputmode="decimal" autocomplete="off" placeholder="0,00" value="${esc(cxFmt(F.rede))}" data-cxinp="${F.f}" aria-label="${esc(F.nome)} na Rede · ${esc(nome)}"></span></label>
        <span class="cx-res" data-cxres aria-live="polite"></span>
        <small class="cx-quem" data-cxquem>${F.por ? `lançado por ${esc(F.por)} · ${esc(quandoBR(F.em))}` : ''}</small>
      </div>`).join('')}
      <div class="cx-linha cx-total"><span class="cx-forma">Total</span><span class="cx-sis num"><small class="cx-mob">No sistema</small>${brl(U.sis)}</span>
        <span class="cx-rede num"><small class="cx-mob">Na Rede</small><b data-cxtrede>—</b></span><span class="cx-res" data-cxtres></span></div>
    </div>
    <p class="cx-din"><b>Dinheiro:</b> <span class="num">${brl(U.dinheiro)}</span> · não passa pela Rede, fica fora da conferência.</p>
  </section>`;
}
/* a conferência de cada linha, do total e do cartão, na hora (com o que está digitado, mesmo antes de salvar) */
function cxPinta(card) {
  let ts = 0, tr = 0, temR = false, pior = '', nDif = 0, nFalta = 0, nOk = 0, ruim = false;
  $$('.cx-linha[data-cxf]', card).forEach(l => {
    const sis = +l.dataset.sis, inp = $('input', l), r = cxNum(inp.value), res = $('[data-cxres]', l);
    if (Number.isNaN(r)) { ruim = true; nDif++; inp.classList.add('cx-ruim'); res.innerHTML = `<span class="cx-s dif">${CX_IC.dif}</span><span><b>Valor inválido</b><small>Digite só números, ex.: 1.225,50</small></span>`; l.dataset.sit = 'dif'; return; }
    inp.classList.remove('cx-ruim');
    const s = cxSit(sis, r); l.dataset.sit = s; ts += sis; if (r != null) { tr += r; temR = true; }
    if (CX_ORDEM[s] > CX_ORDEM[pior]) pior = s; if (s === 'dif') nDif++; if (s === 'falta') nFalta++; if (s === 'ok') nOk++;
    res.innerHTML = cxResHtml(s, sis, r);
  });
  const tres = $('[data-cxtres]', card), trede = $('[data-cxtrede]', card);
  trede.textContent = temR ? brl(tr) : '—';
  const st = ruim ? 'dif' : pior;
  tres.innerHTML = !st ? '' : st === 'dif' && (nFalta || ruim) ? `<span class="cx-s dif">${CX_IC.dif}</span><span><b>Não bateu</b><small>Confira as formas acima</small></span>` : cxResHtml(st, ts, temR ? tr : null, true);
  const g = $('[data-cxgeral]', card);
  g.className = 'cx-geral ' + (st || 'vazio');
  g.innerHTML = `${CX_IC[st || 'vazio']}<span>${st === 'ok' ? 'Tudo bateu' : st === 'dif' ? (nDif === 1 ? '1 forma não bateu' : `${nDif} formas não bateram`) : st === 'falta' ? `Falta lançar a Rede${nOk ? ` (${nFalta} de ${nFalta + nOk})` : ''}` : 'Sem movimento no dia'}</span>`;
}
function cxResHtml(s, sis, r, total) {
  if (s === 'ok') return `<span class="cx-s ok">${CX_IC.ok}</span><span><b>Bateu</b>${total ? '<small>O total também confere</small>' : ''}</span>`;
  if (s === 'dif') {
    if (r == null) return `<span class="cx-s dif">${CX_IC.dif}</span><span><b>Não bateu</b></span>`;
    const d = Math.round((r - sis) * 100) / 100;
    return `<span class="cx-s dif">${CX_IC.dif}</span><span><b>Não bateu</b><small>${d === 0 ? 'confira as formas acima' : `Rede ${brl(Math.abs(d))} ${d < 0 ? 'a menos' : 'a mais'}`}</small></span>`;
  }
  if (s === 'falta') return `<span class="cx-s falta">${CX_IC.falta}</span><span><b>Falta lançar</b><small>${total ? 'Lance as formas acima' : 'Digite o valor da Rede'}</small></span>`;
  return `<span class="cx-s vazio">${CX_IC.vazio}</span><span class="cx-mudo">Sem movimento</span>`;
}
function cxLigar() {
  $$('.cx-uni').forEach(card => {
    cxPinta(card);
    $$('input[data-cxinp]', card).forEach(inp => {
      inp.addEventListener('input', () => cxPinta(card));
      fsSeleciona(inp);   /* 1.62 (festas.js): seleciona na hora; a seleção de depois apagava a 1ª tecla de quem digita rápido */
      inp.addEventListener('keydown', e => { if (e.key !== 'Enter') return; e.preventDefault(); const L = $$('input[data-cxinp]'), i = L.indexOf(inp); if (L[i + 1]) L[i + 1].focus(); else inp.blur(); });
      inp.addEventListener('change', () => cxGuardar(card, inp));
    });
  });
}
async function cxGuardar(card, inp) {
  const v = cxNum(inp.value); if (Number.isNaN(v)) { aviso('Digite o valor em reais, ex.: 1.225,50'); return; }
  inp.value = cxFmt(v); cxPinta(card);
  const u = +card.dataset.cxuni, f = inp.dataset.cxinp, dia = CX.dia, linha = inp.closest('.cx-linha'), quem = $('[data-cxquem]', linha);
  quem.textContent = 'salvando…';
  try {
    const s = ++CX.s, r = await api('cx_salvar', { dia, u, forma: f, valor: v });
    if (CX.dia !== dia) return;
    if (s === CX.s) CX.d = r;   /* a resposta do último que foi salvo (duas respostas podem chegar fora de ordem) */
    const U = r.unidades.find(x => x.u === u), F = U && U.formas.find(x => x.f === f);
    quem.textContent = F && F.por ? `lançado por ${F.por} · ${quandoBR(F.em)} · salvo ✓` : 'tirado ✓';
    const h = $('.cx-hist'); if (h && s === CX.s) { const novo = document.createElement('div'); novo.innerHTML = cxCorpo(); const nh = $('.cx-hist', novo); h.replaceWith(nh); $$('[data-cxdia]', nh).forEach(b => b.onclick = () => cxIr(b.dataset.cxdia)); }
  } catch (e) { quem.textContent = 'não salvou: ' + e.message; aviso(e.message); }
}
