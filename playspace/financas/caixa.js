/* Play Space Finanças 2.2 (Play Space 1.61): o Controle de caixa (a primeira tela das Finanças).
   Pedido do dono: escolher o dia (hoje, ontem ou a data), ver o que passou no caixa no crédito, no débito e no Pix, lançar ao lado o valor
   da Rede e o sistema dizer na hora se bateu (positivo) ou não (negativo), sem ninguém precisar comparar número com número.
   Os valores da Rede ficam guardados (por dia, unidade e forma), com quem lançou. Usa o que a tela das Finanças já tem:
   $, $$, esc, brl, dataBR, iso, api, aviso, corUni, nomeUni, UNIS. */
'use strict';
const CX = { dia: null, d: null, n: 0, s: 0, gerado: '', modo: 'conf', liqSel: null };   /* 2.4: modo 'conf' (a conferência com a Rede) ou 'liq' (o caixa líquido) */
try { if (localStorage.getItem('psfin-cx-modo') === 'liq') CX.modo = 'liq'; } catch (e) { /* sem memória */ }
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

const cxFimMes = d => { const [a, m] = d.split('-').map(Number); return `${a}-${String(m).padStart(2, '0')}-${String(new Date(a, m, 0).getDate()).padStart(2, '0')}`; };
/* ---------- a tela ---------- */
function caixaDesenhar() {
  const t = $('#tela'); if (!t) return;
  if (!CX.dia) CX.dia = cxHoje();
  const hoje = cxHoje(), ontem = cxMais(hoje, -1), d = CX.dia, rot = cxRotulo(d);
  t.innerHTML = `<div class="cx-tela"><div class="topo"><div><h1>Controle de caixa</h1><div class="sub">O que passou no caixa no dia, conferido com a Rede</div></div>
      <div class="topo-d"><div class="lista-acoes cx-pdfs"><button class="btn btn-ghost" id="cx-pdf-dia">${PDF_IC} PDF do dia</button><button class="btn btn-ghost" id="cx-pdf-mes">${PDF_IC} PDF do mês</button></div><div class="sub" id="cx-gerado">${CX.gerado ? `Atualizado às ${esc(CX.gerado)} · ` : ''}<button class="link" style="display:inline;padding:0" id="cx-atualizar">atualizar</button></div></div></div>
    ${impAntCartao()}
    <div class="filtros"><div class="chips" role="group" aria-label="Dia do caixa">
      <button class="chip ${d === hoje ? 'on' : ''}" data-cxdia="${hoje}">Hoje</button><button class="chip ${d === ontem ? 'on' : ''}" data-cxdia="${ontem}">Ontem</button>
      <span class="cx-data"><button class="chip cx-passo" data-cxpasso="-1" aria-label="Dia anterior" title="Dia anterior">‹</button>
        <label class="chip cx-escolher ${d !== hoje && d !== ontem ? 'on' : ''}"><span>Escolher a data</span><input type="date" id="cx-data" value="${esc(d)}" max="${esc(hoje)}" aria-label="Escolher a data do caixa"></label>
        <button class="chip cx-passo" data-cxpasso="1" aria-label="Dia seguinte" title="Dia seguinte" ${d >= hoje ? 'disabled' : ''}>›</button></span></div></div>
    <div class="chips cx-modo" role="tablist" aria-label="O que ver"><button class="chip ${CX.modo === 'conf' ? 'on' : ''}" role="tab" aria-selected="${CX.modo === 'conf'}" data-cxmodo="conf">Conferência com a Rede</button><button class="chip ${CX.modo === 'liq' ? 'on' : ''}" role="tab" aria-selected="${CX.modo === 'liq'}" data-cxmodo="liq">Caixa líquido · o que cai na conta</button></div>
    <h2 class="cx-titulo">${rot ? `<span>${rot}</span> · ` : ''}${esc(cxNomeDia(d))}</h2>
    <div id="cx-corpo">${CX.d && CX.d.dia === d ? (CX.modo === 'liq' ? cxLiquidoHtml() : cxCorpo()) : '<div class="vazio">Carregando o caixa…</div>'}</div></div>`;
  $$('[data-cxmodo]').forEach(b => b.onclick = () => { CX.modo = b.dataset.cxmodo; CX.liqSel = null; try { localStorage.setItem('psfin-cx-modo', CX.modo); } catch (e) { /* sem memória */ } caixaDesenhar(); });
  $$('[data-cxdia]').forEach(b => b.onclick = () => cxIr(b.dataset.cxdia));
  /* 2.6: o PDF bonito do dia escolhido, ou do mês dele (do dia 1 até o dia escolhido) */
  $('#cx-pdf-dia').onclick = e => pdfBaixar(e.currentTarget, { acao: 'cx_rel_pdf', de: d, ate: d }, `play-space-caixa-${d}.pdf`);
  $('#cx-pdf-mes').onclick = e => { const de = d.slice(0, 8) + '01', ate = d.slice(0, 7) === hoje.slice(0, 7) ? hoje : cxFimMes(d); pdfBaixar(e.currentTarget, { acao: 'cx_rel_pdf', de, ate }, `play-space-caixa-${d.slice(0, 7)}.pdf`); };
  $$('[data-cxpasso]').forEach(b => b.onclick = () => { const x = cxMais(CX.dia, +b.dataset.cxpasso); if (x <= cxHoje()) cxIr(x); });
  const dt = $('#cx-data'); if (dt) dt.onchange = () => { if (/^\d{4}-\d{2}-\d{2}$/.test(dt.value)) cxIr(dt.value > cxHoje() ? cxHoje() : dt.value); };
  $('#cx-atualizar').onclick = () => cxCarregar();
  if (CX.d && CX.d.dia === d) { if (CX.modo === 'liq') cxLigarLiq(); else cxLigar(); } else cxCarregar();
}
function cxIr(d) { if (d === CX.dia && CX.d && CX.d.dia === d) return; CX.dia = d; CX.liqSel = null; caixaDesenhar(); }
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
      <div class="cx-direita"><div class="cx-geral" data-cxgeral></div>
        <div class="cx-acoes"><label class="btn btn-brand cx-pdf-bt" title="O relatório de vendas da Rede (PDF): o sistema lê e preenche crédito, débito e Pix, com a taxa"><input type="file" accept="application/pdf,.pdf" data-cxpdf="${U.u}" hidden>${IC.pdf}<span>Anexar PDF da Rede</span></label>
          <button class="btn btn-ghost" data-cxconserto="${U.u}" title="Lançar um valor a mais ou a menos, com o porquê, para as contas baterem">Consertar caixa</button></div></div></div>
    ${U.relatorio ? `<p class="cx-rel">PDF da Rede ${U.relatorio.de === U.relatorio.ate ? 'de ' + dataBR(U.relatorio.de).slice(0, 5) : 'de ' + dataBR(U.relatorio.de).slice(0, 5) + ' a ' + dataBR(U.relatorio.ate).slice(0, 5)} anexado por ${esc(U.relatorio.por)} · ${esc(quandoBR(U.relatorio.em))}${U.relatorio.ecs.length > 1 ? ` · <b>${U.relatorio.ecs.length} estabelecimentos juntos</b>` : U.relatorio.ecs.length ? ` · estabelecimento ${esc(U.relatorio.ecs[0])}` : ''} · <button class="link" data-cxverpdf="${U.relatorio.id}">baixar o PDF</button></p>` : ''}
    <div class="cx-tab">
      <div class="cx-cab"><span>Forma</span><span class="v">No sistema</span><span>Na Rede <small>(digite o valor)</small></span><span>Conferência</span></div>
      ${U.formas.map(F => `<div class="cx-linha" data-cxf="${F.f}" data-sis="${F.sis}">
        <span class="cx-forma">${esc(F.nome)}</span>
        <span class="cx-sis num"><small class="cx-mob">No sistema</small>${brl(F.sis)}${Math.abs(F.aj || 0) > 0.004 ? `<small class="cx-ajl">sistema ${brl(F.sis0)} ${F.aj > 0 ? '+' : '−'} conserto ${brl(Math.abs(F.aj))}</small>` : ''}</span>
        <label class="cx-rede"><small class="cx-mob">Na Rede</small><span class="cx-campo"><span class="cx-rs">R$</span><input class="inp num" inputmode="decimal" autocomplete="off" placeholder="0,00" value="${esc(cxFmt(F.rede))}" data-cxinp="${F.f}" aria-label="${esc(F.nome)} na Rede · ${esc(nome)}"></span>${F.taxa != null && F.rede != null ? `<small class="cx-info">taxa ${brl(F.taxa)} · líquido ${brl(F.liquido != null ? F.liquido : F.rede - F.taxa)}${F.qtde != null ? ` · ${int(F.qtde)} ${F.qtde === 1 ? 'venda' : 'vendas'}` : ''}</small>` : ''}</label>
        <span class="cx-res" data-cxres aria-live="polite"></span>
        <small class="cx-quem" data-cxquem>${F.por ? `${F.origem === 'pdf' ? 'do PDF da Rede · ' : ''}lançado por ${esc(F.por)} · ${esc(quandoBR(F.em))}` : ''}</small>
      </div>`).join('')}
      <div class="cx-linha cx-total"><span class="cx-forma">Total</span><span class="cx-sis num"><small class="cx-mob">No sistema</small>${brl(U.sis)}</span>
        <span class="cx-rede num"><small class="cx-mob">Na Rede</small><b data-cxtrede>—</b></span><span class="cx-res" data-cxtres></span></div>
    </div>
    <p class="cx-din"><b>Dinheiro:</b> <span class="num">${brl(U.dinheiro)}</span> · não passa pela Rede, fica fora da conferência.</p>
    ${U.ajustes && U.ajustes.length ? `<div class="cx-consertos"><h3>Consertos do dia</h3>${U.ajustes.map(a => `<div class="cx-aj"><span class="cx-aj-v num ${a.v < 0 ? 'neg' : ''}">${a.v < 0 ? '−' : '+'} ${brl(Math.abs(a.v))}</span><span class="cx-aj-f">${esc(a.nome)}</span><span class="cx-aj-o">${esc(a.obs)}<small>por ${esc(a.por)} · ${esc(quandoBR(a.em))}</small></span><button class="link" data-cxtirar="${a.id}">tirar</button></div>`).join('')}</div>` : ''}
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
  cxLigarBotoes();
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

/* =====================================================================
   2.4 (Play Space 1.63): anexar o PDF da Rede, consertar o caixa e o caixa líquido
   ===================================================================== */
function cxLigarBotoes() {
  $$('input[data-cxpdf]').forEach(inp => inp.onchange = () => { const f = inp.files && inp.files[0]; inp.value = ''; if (f) cxAnexar(+inp.dataset.cxpdf, f, inp.closest('label')); });
  $$('[data-cxconserto]').forEach(b => b.onclick = () => cxConsertar(+b.dataset.cxconserto));
  $$('[data-cxtirar]').forEach(b => b.onclick = () => cxTirar(+b.dataset.cxtirar));
  $$('[data-cxverpdf]').forEach(b => b.onclick = () => cxVerPdf(+b.dataset.cxverpdf));
}
/* o PDF da Rede: lido no servidor, preenche crédito, débito e Pix (com a taxa, o líquido e a quantidade) */
async function cxAnexar(u, f, bt) {
  if (!/\.pdf$/i.test(f.name) && f.type !== 'application/pdf') { aviso('Escolha o PDF do relatório de vendas da Rede.'); return; }
  if (f.size > 8 * 1024 * 1024) { aviso('PDF grande demais (até 8 MB). Tire o relatório de menos dias.'); return; }
  const rot = bt && $('span', bt), antes = rot ? rot.textContent : ''; if (rot) rot.textContent = 'Lendo o PDF…'; if (bt) bt.classList.add('carregando');
  try {
    const arquivo = await new Promise((ok, falha) => { const r = new FileReader(); r.onload = () => ok(String(r.result).split(',')[1] || ''); r.onerror = () => falha(new Error('Não consegui abrir o arquivo.')); r.readAsDataURL(f); });
    const r = await api('cx_pdf', { u, nome: f.name, arquivo, dia: CX.dia });
    CX.d = r; CX.dia = r.dia; CX.modo = 'conf'; caixaDesenhar(); cxResumoPdf(u, r.pdf);
  } catch (e) { aviso(e.message); if (rot) rot.textContent = antes; if (bt) bt.classList.remove('carregando'); }
}
function cxResumoPdf(u, P) {
  const dias = P.dias.map(x => {
    const m = x.modalidades, parte = (f, n) => m[f] ? `<li><b>${n}</b> ${brl(m[f].bruto)}${m[f].taxa > 0.004 ? ` <span class="sub">· taxa ${brl(m[f].taxa)} · líquido ${brl(m[f].liquido)}</span>` : ''}${m[f].qtde ? ` <span class="sub">· ${int(m[f].qtde)} ${m[f].qtde === 1 ? 'venda' : 'vendas'}</span>` : ''}</li>` : '';
    return `<div class="cx-pdf-dia"><h4>${esc(cxNomeDia(x.dia))}</h4><ul>${parte('credito', 'Crédito')}${parte('debito', 'Débito')}${parte('pix', 'Pix')}${parte('voucher', 'Voucher')}</ul>${x.cancelado > 0.004 ? `<p class="sub">Cancelamentos e estornos na Rede: ${brl(x.cancelado)}</p>` : ''}</div>`; }).join('');
  folha('PDF da Rede lido ✓', `<p class="dica">${esc(nomeUni(u))}: preenchi ${P.dias.length === 1 ? 'o dia' : 'os ' + P.dias.length + ' dias'} com o que está no PDF da Rede.${P.ecs.length ? ` Estabelecimento${P.ecs.length > 1 ? 's' : ''}: ${esc(P.ecs.join(', '))}.` : ''}</p>
    ${dias}${P.avisos.length ? `<div class="cx-pdf-av">${P.avisos.map(a => `<p>${esc(a)}</p>`).join('')}</div>` : ''}`, 'Entendi', async () => {});
  const sem = [...document.querySelectorAll('body > .fundo')].pop(); if (sem) { const c = sem.querySelector('[data-fechar]'); if (c) c.remove(); }   /* só informa: sem o Cancelar, que parecia desfazer a leitura */
}
async function cxVerPdf(id) {
  try {
    const r = await fetch('api.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Fin': '1' }, body: JSON.stringify({ acao: 'cx_pdf_ver', id }) });
    if (!r.ok || !/pdf/.test(r.headers.get('Content-Type') || '')) { let j = {}; try { j = await r.json(); } catch (e) { /* sem corpo */ } throw new Error(j.erro || 'Não consegui baixar o PDF.'); }
    const nome = ((r.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/) || [])[1] || 'rede.pdf';
    gBaixa(await r.blob(), nome);
  } catch (e) { aviso(e.message); }
}
/* consertar o caixa: um valor a mais ou a menos numa forma, com o porquê */
function cxConsertar(u) {
  const U = CX.d.unidades.find(x => x.u === u); if (!U) return;
  const st = { f: '', s: '-' };
  const corpo = `<p class="dica">${esc(nomeUni(u))} · ${esc(cxNomeDia(CX.d.dia))}. O conserto entra na conta do sistema; a Rede continua como veio.</p>
    <label class="lbl">Em que forma?</label><div class="chips" id="cj-f">${U.formas.map(F => `<button type="button" class="chip" data-cjf="${F.f}">${esc(F.nome)}</button>`).join('')}</div>
    <p class="cx-cj-dif" id="cj-dif" hidden></p>
    <label class="lbl">O conserto</label><div class="chips" id="cj-s"><button type="button" class="chip" data-cjs="+">+ Somar ao sistema</button><button type="button" class="chip on" data-cjs="-">− Tirar do sistema</button></div>
    <div class="cx-campo" style="margin-top:10px"><span class="cx-rs">R$</span><input class="inp num" id="cj-v" inputmode="decimal" autocomplete="off" placeholder="0,00" aria-label="Valor do conserto"></div>
    <label class="lbl" for="cj-o">Por que está consertando? <small>(obrigatório)</small></label><textarea class="inp" id="cj-o" maxlength="300" placeholder="Ex.: venda passada no débito e lançada no crédito no sistema"></textarea>`;
  folha('Consertar caixa', corpo, 'Salvar o conserto', async () => {
    if (!st.f) throw new Error('Escolha a forma (crédito, débito ou Pix).');
    const v = cxNum($('#cj-v').value); if (v == null || Number.isNaN(v) || !(v > 0)) throw new Error('Digite o valor do conserto, ex.: 10,00');
    const r = await api('cx_ajuste', { dia: CX.d.dia, u, forma: st.f, valor: (st.s === '-' ? '-' : '') + cxFmt(v), obs: $('#cj-o').value });
    CX.d = r; caixaDesenhar(); aviso('Conserto salvo ✓');
  });
  const pinta = () => {
    $$('[data-cjf]').forEach(b => b.classList.toggle('on', b.dataset.cjf === st.f)); $$('[data-cjs]').forEach(b => b.classList.toggle('on', b.dataset.cjs === st.s));
    const F = U.formas.find(x => x.f === st.f), p = $('#cj-dif'); if (!F) { p.hidden = true; return; }
    if (F.rede == null) { p.hidden = false; p.innerHTML = `No sistema: <b>${brl(F.sis)}</b>. A Rede desse dia ainda não foi lançada.`; return; }
    const d = Math.round((F.rede - F.sis) * 100) / 100; p.hidden = false;
    p.innerHTML = Math.abs(d) < 0.005 ? `${esc(F.nome)} já bate com a Rede (${brl(F.rede)}).` : `No sistema <b>${brl(F.sis)}</b> · na Rede <b>${brl(F.rede)}</b>: a Rede tem ${brl(Math.abs(d))} ${d < 0 ? 'a menos' : 'a mais'}. <button type="button" class="link" id="cj-usar">Usar a diferença (${d < 0 ? '−' : '+'} ${brl(Math.abs(d))})</button>`;
    const us = $('#cj-usar'); if (us) us.onclick = () => { st.s = d < 0 ? '-' : '+'; $('#cj-v').value = cxFmt(Math.abs(d)); pinta(); $('#cj-o').focus(); };
  };
  $$('[data-cjf]').forEach(b => b.onclick = () => { st.f = b.dataset.cjf; pinta(); });
  $$('[data-cjs]').forEach(b => b.onclick = () => { st.s = b.dataset.cjs; pinta(); });
  const dif = U.formas.find(x => x.sit === 'dif'); if (dif) { st.f = dif.f; } pinta();
  setTimeout(() => { const i = $('#cj-v'); if (i) i.focus(); }, 60);
}
function cxTirar(id) {
  folha('Tirar o conserto', '<p class="dica">O conserto sai da conta (fica guardado quem tirou e quando).</p>', 'Tirar', async () => {
    const r = await api('cx_ajuste_tirar', { id }); CX.d = r; caixaDesenhar(); aviso('Conserto tirado ✓');
  });
}
/* ---------- o caixa líquido: o que cai na conta no dia (e nos próximos dias) ---------- */
const CX_QUANDO = { pix: 'no mesmo dia', debito: '1 dia útil depois', credito: '2 dias úteis depois' };
function cxLiqCartoes(dia, unidades, util) {
  return unidades.map(U => {
    const linhas = U.itens.map(i => `<tr><td><b>${esc(i.nome)}</b><small>${CX_QUANDO[i.f] || ''}</small></td><td>${(x => CX_SEM[x.getDay()].slice(0, 3) + ' ' + i.venda.slice(8, 10) + '/' + i.venda.slice(5, 7))(new Date(i.venda + 'T12:00:00'))}</td><td class="v">${brl(i.bruto)}</td>
        <td class="v">${i.taxa != null ? '− ' + brl(i.taxa) : `<span class="selo aviso" title="Sem o PDF da Rede do dia da venda, o valor é o do sistema, sem descontar a taxa">sem a taxa</span>`}</td><td class="v"><b>${brl(i.liquido)}</b></td></tr>`).join('');
    return `<section class="cartao cx-liq" data-cxliq="${U.u}"><div class="cab-cartao"><div><h2><i class="uni-dot" style="background:${corUni(U.u)}"></i>${esc(nomeUni(U.u))}</h2>
        <p class="expl">Cai na conta em ${esc(cxNomeDia(dia))}${util ? '' : ' (não é dia útil: só o Pix cai)'}</p></div>
        <div class="cx-liq-total"><small>Líquido do dia</small><b class="num">${brl(U.total.liquido)}</b></div></div>
      ${U.itens.length ? `<div class="rolar"><table><thead><tr><th>Forma</th><th>Vendas de</th><th class="v">Bruto</th><th class="v">Taxa da Rede</th><th class="v">Líquido</th></tr></thead><tbody>${linhas}</tbody>
        <tfoot><tr><td colspan="2">Total</td><td class="v">${brl(U.total.bruto)}</td><td class="v">${U.total.taxa > 0.004 ? '− ' + brl(U.total.taxa) : '—'}</td><td class="v">${brl(U.total.liquido)}</td></tr></tfoot></table></div>`
        : '<div class="vazio">Nada cai na conta neste dia.</div>'}
      ${U.estimado ? '<p class="cx-din">Onde aparece <b>sem a taxa</b>, falta anexar o PDF da Rede do dia da venda: o valor é o do sistema, sem descontar a taxa.</p>' : ''}</section>`; }).join('');
}
function cxLiquidoHtml() {
  const L = CX.d.liquido; if (!L) return '<div class="vazio">Sem o caixa líquido.</div>';
  const sel = CX.liqSel != null ? L.proximos[CX.liqSel] : null;
  return `<p class="cx-regra">O Pix cai <b>no mesmo dia</b>, o débito em <b>1 dia útil</b> e o crédito em <b>2 dias úteis</b> (sábado, domingo e feriado não contam). Com o PDF da Rede, o valor já vem sem a taxa da Rede.</p>
    ${cxLiqCartoes(L.dia, L.unidades, L.util)}
    <section class="cartao cx-prox"><div class="cab-cartao"><div><h2>Os próximos dias</h2><p class="expl">O que ainda vai cair na conta, pelas vendas que já aconteceram. Toque num dia para ver de onde vem.</p></div></div>
      <div class="cx-dias">${L.proximos.map((p, i) => { const x = new Date(p.dia + 'T12:00:00');
        return `<button class="cx-diab cx-proxb ${CX.liqSel === i ? 'on' : ''} ${p.util ? '' : 'nutil'}" data-cxprox="${i}" title="${esc(cxNomeDia(p.dia))}"><small>${CX_SEM[x.getDay()].slice(0, 3)}</small><b>${p.dia.slice(8, 10)}/${p.dia.slice(5, 7)}</b><span class="num">${p.liquido > 0.004 ? brlCurto(p.liquido) : p.util ? 'R$ 0' : 'não é útil'}</span></button>`; }).join('')}</div>
      ${sel ? `<div class="cx-prox-det"><h3>${esc(cxNomeDia(sel.dia))}</h3>${cxLiqCartoes(sel.dia, sel.unidades, sel.util)}</div>` : ''}</section>`;
}
function cxLigarLiq() {
  $$('[data-cxprox]').forEach(b => b.onclick = () => { const i = +b.dataset.cxprox; CX.liqSel = CX.liqSel === i ? null : i; const c = $('#cx-corpo'); if (c) { c.innerHTML = cxLiquidoHtml(); cxLigarLiq(); } });
}
