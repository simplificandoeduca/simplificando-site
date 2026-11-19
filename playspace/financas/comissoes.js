/* Play Space Finanças 2.8 (Play Space 1.67): as Comissões (logo abaixo dos Freelancers).
   Pedido do dono: em toda venda de pacote, o nome de quem vendeu (Aline, Edjane, Karol e Maria Vitória); nas comissões, o nome de cada
   uma com o bônus; as regras pré-configuradas (o bônus trimestral, a partir de 15/05/2026) e o financeiro edita ("amanhã muda e passa
   a ser 5%, 1%"). O trimestre (ou o mês), o bônus de cada vendedora, as vendas com a regra de cada uma, as regras e os nomes. */
'use strict';
const CM = { per: '', u: 'todas', vend: '', d: null, n: 0, ed: null, sujo: false, nomes: null };
const cmTriDe = d => `${d.getFullYear()}-T${Math.floor(d.getMonth() / 3) + 1}`;
const cmMesDe = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
try { const g = JSON.parse(localStorage.getItem('psfin-comissoes') || 'null'); if (g && /^\d{4}-(T[1-4]|\d{2})$/.test(g.per || '')) CM.per = g.per; if (g && g.u) CM.u = g.u; } catch (e) { /* sem o guardado */ }
if (!CM.per) CM.per = cmTriDe(new Date());
const cmGuarda = () => { try { localStorage.setItem('psfin-comissoes', JSON.stringify({ per: CM.per, u: CM.u })); } catch (e) { /* sem espaço */ } };
const cmEhTri = () => /-T/.test(CM.per);
function cmAnda(passo) {
  if (cmEhTri()) { const [a, t] = CM.per.split('-T').map(Number), d = new Date(a, (t - 1) * 3 + passo * 3, 1); CM.per = cmTriDe(d); }
  else { const [a, m] = CM.per.split('-').map(Number), d = new Date(a, m - 1 + passo, 1); CM.per = cmMesDe(d); }
}
const cmPct = v => (Math.round(v * 100) / 100).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
const cmNum = t => { const s = String(t == null ? '' : t).replace(/[R$\s%]/g, ''); const n = parseFloat(/,/.test(s) ? s.replace(/\./g, '').replace(',', '.') : s); return isFinite(n) ? Math.round(n * 100) / 100 : 0; };
const CM_IC = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="9" r="6"/><path d="M9 14.4 7.6 22l4.4-2.4 4.4 2.4L15 14.4"/><path d="m10 11 4-4"/></svg>';

async function cmCarregar() {
  const n = ++CM.n, per = CM.per, c = $('#cm-corpo'); if (c) c.classList.add('carregando');
  try { const r = await api('cm_listar', { per, u: CM.u }); if (n !== CM.n || ABA !== 'comis') return; CM.d = r; if (!CM.sujo) CM.ed = r.regras.map(x => ({ ...x })); if (!CM.nomes) CM.nomes = [...r.vendedoras]; }
  catch (e) { if (n === CM.n) { aviso(e.message); const c2 = $('#cm-corpo'); if (c2) { c2.classList.remove('carregando'); c2.innerHTML = `<div class="vazio">${esc(e.message)}</div>`; } } return; }
  if (CM.per === per) comissoesDesenhar();
}
function comissoesDesenhar() {
  const t = $('#tela'); if (!t) return;
  const unis = UNIS.filter(x => x.ativo).map(x => x.id), tri = cmEhTri();
  t.innerHTML = `<div class="cm-tela"><div class="topo"><div><h1>Comissões</h1><div class="sub">O bônus das vendedoras: quem vendeu cada pacote e cada produto, pelas regras de comissão</div></div>
      <div class="lista-acoes"><button class="btn btn-ghost" id="cm-pdf">${PDF_IC} PDF das comissões</button></div></div>
    <div class="filtros"><div class="chips"><button class="chip" data-cmper="-1" aria-label="${tri ? 'Trimestre' : 'Mês'} anterior">‹</button><b class="g-mes">${esc(CM.d && CM.d.per === CM.per ? CM.d.nome : CM.per)}</b><button class="chip" data-cmper="1" aria-label="Próximo ${tri ? 'trimestre' : 'mês'}">›</button>
      <button class="chip ${tri ? 'on' : ''}" data-cmtipo="tri">Trimestre</button><button class="chip ${tri ? '' : 'on'}" data-cmtipo="mes">Mês</button></div>
      ${unis.length > 1 ? `<div class="chips"><button class="chip ${CM.u === 'todas' ? 'on' : ''}" data-cmu="todas">Todas as unidades</button>${unis.map(u => `<button class="chip ${String(CM.u) === String(u) ? 'on' : ''}" data-cmu="${u}">${esc(nomeUni(u))}</button>`).join('')}</div>` : ''}</div>
    <div id="cm-corpo">${CM.d && CM.d.per === CM.per ? cmCorpo() : '<div class="vazio">Carregando as comissões…</div>'}</div></div>`;
  $$('[data-cmper]').forEach(b => b.onclick = () => { cmAnda(+b.dataset.cmper); cmGuarda(); comissoesDesenhar(); cmCarregar(); });
  $$('[data-cmtipo]').forEach(b => b.onclick = () => { const agora = CM.d ? new Date(CM.d.de + 'T12:00:00') : new Date(); CM.per = b.dataset.cmtipo === 'tri' ? cmTriDe(agora) : cmMesDe(agora); cmGuarda(); comissoesDesenhar(); cmCarregar(); });
  $$('[data-cmu]').forEach(b => b.onclick = () => { CM.u = b.dataset.cmu; cmGuarda(); comissoesDesenhar(); cmCarregar(); });
  const pdf = $('#cm-pdf'); if (pdf) pdf.onclick = () => pdfBaixar(pdf, { acao: 'cm_pdf', per: CM.per, u: CM.u }, `play-space-comissoes-${CM.per}.pdf`);
  cmLigar();
  if (!CM.d || CM.d.per !== CM.per) cmCarregar();
}
function cmCorpo() {
  const D = CM.d, T = D.total, S = D.sem;
  const meses = D.meses.length > 1 ? D.meses : [];
  const cartoes = D.porVend.map(v => `<button class="cm-v ${CM.vend === v.n ? 'on' : ''}" data-cmvend="${esc(v.n)}" aria-pressed="${CM.vend === v.n}"><b>${esc(v.n)}</b><span class="val num">${brl(v.bonus)}</span>
      <small>${int(v.vendas)} ${v.vendas === 1 ? 'venda' : 'vendas'} · vendido ${brl(v.vendido)}</small>${meses.length ? `<span class="cm-meses">${meses.map(m => `<span>${esc(fsMesNome(m).slice(0, 3))} ${brl(v.meses[m] || 0)}</span>`).join('')}</span>` : ''}</button>`).join('');
  return `<div class="grade fs-kpis">${kpi('Bônus do período', brl(T.bonus), 'a soma de todas as vendedoras', 'lucro')}${kpi('Vendido', brl(T.vendido), 'pacotes e produtos com a vendedora')}
      ${kpi('Vendas', int(T.n), T.semRegra ? `${int(T.semRegra)} sem regra (sem bônus)` : 'todas dentro das regras')}${kpi('Sem vendedora', int(S.n), S.n ? `${brl(S.v)} em pacotes e produtos sem o nome` : 'todas com o nome', S.n ? 'alerta' : '')}</div>
    <div class="cm-vends">${cartoes}</div>
    ${cmVendas()}
    ${cmRegrasHtml()}
    ${cmNomesHtml()}
    ${D.hist.length ? `<section class="cartao"><h2>Mudanças</h2><p class="expl">Quem mudou as regras ou os nomes, e quando.</p><ul class="cm-hist">${D.hist.map(h => `<li><b>${esc(dataBR(h.quando.slice(0, 10)))} ${esc(h.quando.slice(11, 16))}</b> · ${esc(h.quem || '—')} · ${esc(h.txt)}</li>`).join('')}</ul></section>` : ''}`;
}
function cmVendas() {
  const D = CM.d, L = D.linhas.filter(l => !CM.vend || (CM.vend === '-' ? !l.vend : l.vend === CM.vend));
  const tem = D.linhas.some(l => !l.vend), nomes = D.porVend.filter(v => v.vendas > 0).map(v => v.n);
  const somaB = L.reduce((t, l) => t + l.bonus, 0), somaP = L.reduce((t, l) => t + l.pago, 0);
  const linha = l => `<tr class="${l.bonus > 0 ? '' : 'cm-zero'}"><td>${esc(dataBR(l.quando.slice(0, 10)).slice(0, 5))} <small class="sub">${esc(l.quando.slice(11, 16))}</small></td><td>${l.vend ? `<b>${esc(l.vend)}</b>` : '<span class="cm-sem">sem o nome</span>'}</td>
      <td>${esc(l.item)}${l.qtd > 1 && l.k === 'produto' ? ` <small class="sub">× ${int(l.qtd)}</small>` : ''}</td><td>${esc(l.resp || l.cri || '—')}${l.resp && l.cri ? ` <small class="sub">· ${esc(l.cri)}</small>` : ''}</td>
      <td class="v">${brl(l.tab)}</td><td class="v">${brl(l.pago)}</td><td>${l.rnome ? esc(l.rnome) : '<span class="cm-sem">nenhuma regra</span>'}</td><td class="v"><b>${l.bonus > 0 ? brl(l.bonus) : '—'}</b></td></tr>`;
  return `<section class="cartao"><h2>As vendas${CM.vend ? ' · ' + esc(CM.vend === '-' ? 'sem o nome' : CM.vend) : ''}</h2><p class="expl">Cada pacote e cada produto vendido no período, com a regra que valeu. A venda cancelada não aparece.</p>
    <div class="chips cm-filtro"><button class="chip ${!CM.vend ? 'on' : ''}" data-cmvend="">Todas</button>${nomes.map(n => `<button class="chip ${CM.vend === n ? 'on' : ''}" data-cmvend="${esc(n)}">${esc(n)}</button>`).join('')}${tem ? `<button class="chip ${CM.vend === '-' ? 'on' : ''}" data-cmvend="-">Sem o nome</button>` : ''}</div>
    ${L.length ? `<div class="rolar"><table class="fz-tab cm-tab"><thead><tr><th>Dia</th><th>Vendedora</th><th>O que</th><th>Cliente</th><th class="v">Valor de tabela</th><th class="v">Pago</th><th>Regra</th><th class="v">Bônus</th></tr></thead>
      <tbody>${L.map(linha).join('')}</tbody><tfoot><tr><td>Total · ${int(L.length)} ${L.length === 1 ? 'venda' : 'vendas'}</td><td></td><td></td><td></td><td></td><td class="v">${brl(somaP)}</td><td></td><td class="v">${brl(somaB)}</td></tr></tfoot></table></div>`
      : `<div class="vazio">Nenhuma venda de pacote ou de produto ${CM.vend ? 'nesse filtro' : 'neste período'}.</div>`}</section>`;
}
function cmRegrasHtml() {
  const R = CM.ed || [], hoje = fzHoje(), tipos = CM.d.tipos;
  const linha = (r, i) => `<tr data-cmr="${i}" class="${r.fim && r.fim < hoje ? 'enc' : ''}">
      <td><select class="fz-uni" data-cmc="tipo" aria-label="O que a regra paga">${Object.entries(tipos).map(([k, n]) => `<option value="${k}" ${r.tipo === k ? 'selected' : ''}>${esc(n)}</option>`).join('')}</select></td>
      <td>${r.tipo === 'produto' ? '<span class="sub">—</span>' : `<input class="fz-in num cm-h" data-cmc="h" inputmode="numeric" placeholder="qualquer" value="${r.h ? r.h : ''}" aria-label="Horas do pacote">`}</td>
      <td class="v"><input class="fz-in num" data-cmc="de" inputmode="decimal" value="${esc(cxFmt(r.de))}" aria-label="Valor da venda (de)"></td>
      <td class="v"><input class="fz-in num" data-cmc="ate" inputmode="decimal" value="${esc(cxFmt(r.ate))}" aria-label="Valor da venda (até)"></td>
      <td class="v cm-b"><input class="fz-in num" data-cmc="bonus" inputmode="decimal" value="${esc(r.modo === 'fixo' ? cxFmt(r.bonus) : cmPct(r.bonus))}" aria-label="Bônus"><select class="fz-uni" data-cmc="modo" aria-label="Bônus em % ou em reais"><option value="pct" ${r.modo !== 'fixo' ? 'selected' : ''}>%</option><option value="fixo" ${r.modo === 'fixo' ? 'selected' : ''}>R$ fixo</option></select></td>
      <td><input class="fz-in cm-d" type="date" data-cmc="desde" value="${esc(r.desde)}" aria-label="Vale a partir de"></td>
      <td><input class="fz-in cm-d" type="date" data-cmc="fim" value="${esc(r.fim || '')}" aria-label="Vale até"></td>
      <td class="cm-ac">${i > 0 ? `<button class="link" data-cmsobe="${i}" title="Subir (a primeira regra que serve é a que conta)" aria-label="Subir a regra">↑</button>` : ''}${!r.fim ? `<button class="btn btn-ghost g-mini" data-cmenc="${i}" title="Encerrar hoje: a regra deixa de valer para as vendas de amanhã em diante">Encerrar</button>` : ''}</td></tr>`;
  return `<section class="cartao" id="cm-regras"><h2>Regras de comissão</h2>
    <p class="expl">A <b>primeira regra da lista que serve</b> é a que conta. O valor da venda é o de tabela (antes do desconto); o bônus em % é sobre o que foi pago (já com o desconto); o fixo é por unidade do produto. "Pacote sem produto": o pacote vendido sem nenhum produto no mesmo atendimento. Para uma regra parar de valer, escreva a data "até" (ou toque em Encerrar): as vendas antigas continuam com a regra da época.</p>
    <div class="rolar"><table class="fz-tab cm-reg"><thead><tr><th>O que</th><th>Horas</th><th class="v">Valor de</th><th class="v">até</th><th class="v">Bônus</th><th>Vale de</th><th>até</th><th></th></tr></thead><tbody>${R.map(linha).join('')}</tbody></table></div>
    <div class="fz-acoes"><button class="btn btn-ghost" id="cm-nova">+ Nova regra</button><button class="btn btn-brand" id="cm-salvar" ${CM.sujo ? '' : 'disabled'}>${CM.sujo ? 'Salvar as regras' : 'Regras salvas ✓'}</button>${CM.sujo ? '<button class="link" id="cm-desfaz">Desfazer as mudanças</button>' : ''}</div></section>`;
}
function cmNomesHtml() {
  const N = CM.nomes || [], muda = JSON.stringify(N) !== JSON.stringify(CM.d.vendedoras);
  return `<section class="cartao" id="cm-nomes"><h2>Vendedoras</h2><p class="expl">Os nomes que aparecem no atendimento do Play Space, na hora da venda (a vendedora toca no próprio nome a cada venda). Tirar um nome da lista não mexe nas vendas que ela já fez.</p>
    <div class="cm-nomes">${N.map((n, i) => `<span class="cm-nome">${esc(n)}<button data-cmtira="${i}" aria-label="Tirar ${esc(n)} da lista" title="Tirar da lista">×</button></span>`).join('')}
      <input class="fz-in cm-novo" id="cm-novo-nome" maxlength="30" placeholder="Novo nome" autocomplete="off" aria-label="Novo nome"><button class="btn btn-ghost g-mini" id="cm-poe">Pôr na lista</button></div>
    <div class="fz-acoes"><button class="btn btn-brand" id="cm-nomes-salvar" ${muda ? '' : 'disabled'}>${muda ? 'Salvar os nomes' : 'Nomes salvos ✓'}</button>${muda ? '<button class="link" id="cm-nomes-desfaz">Desfazer</button>' : ''}</div></section>`;
}
function cmRedesenhaRegras() { const s = $('#cm-regras'); if (s) { s.outerHTML = cmRegrasHtml(); cmLigar(); } }
function cmRedesenhaNomes() { const s = $('#cm-nomes'); if (s) { s.outerHTML = cmNomesHtml(); cmLigar(); } }
function cmSujo() { CM.sujo = true; const b = $('#cm-salvar'); if (b) { b.disabled = false; b.textContent = 'Salvar as regras'; } }
function cmLigar() {
  $$('[data-cmvend]').forEach(b => b.onclick = () => { const v = b.dataset.cmvend; CM.vend = CM.vend === v && b.classList.contains('cm-v') ? '' : v; const c = $('#cm-corpo'); if (c) { c.innerHTML = cmCorpo(); cmLigar(); } });
  $$('#cm-regras [data-cmr]').forEach(tr => {
    const i = +tr.dataset.cmr, r = CM.ed[i];
    tr.querySelectorAll('[data-cmc]').forEach(el => {
      const k = el.dataset.cmc;
      el.onchange = () => {
        if (k === 'tipo') { r.tipo = el.value; if (r.tipo === 'produto') r.h = 0; cmSujo(); cmRedesenhaRegras(); return; }
        if (k === 'modo') r.modo = el.value; else if (k === 'desde' || k === 'fim') r[k] = el.value; else if (k === 'h') r.h = Math.max(0, parseInt(el.value, 10) || 0);
        else { r[k] = cmNum(el.value); if (k === 'de' && !(r.ate >= r.de)) { r.ate = r.de; const a = tr.querySelector('[data-cmc="ate"]'); if (a) a.value = cxFmt(r.ate); } }
        cmSujo();
      };
    });
  });
  $$('[data-cmsobe]').forEach(b => b.onclick = () => { const i = +b.dataset.cmsobe; [CM.ed[i - 1], CM.ed[i]] = [CM.ed[i], CM.ed[i - 1]]; cmSujo(); cmRedesenhaRegras(); });
  $$('[data-cmenc]').forEach(b => b.onclick = () => { const r = CM.ed[+b.dataset.cmenc]; r.fim = r.desde > fzHoje() ? r.desde : fzHoje(); cmSujo(); cmRedesenhaRegras(); });
  const nova = $('#cm-nova'); if (nova) nova.onclick = () => { CM.ed.push({ id: '', tipo: 'produto', h: 0, de: 0, ate: 0, modo: 'pct', bonus: 0, desde: fzHoje(), fim: '' }); cmSujo(); cmRedesenhaRegras(); const u = $$('#cm-regras [data-cmr]').pop(); if (u) { const x = u.querySelector('[data-cmc="de"]'); if (x) x.focus(); } };
  const des = $('#cm-desfaz'); if (des) des.onclick = () => { CM.ed = CM.d.regras.map(x => ({ ...x })); CM.sujo = false; cmRedesenhaRegras(); };
  const sv = $('#cm-salvar'); if (sv) sv.onclick = async () => {
    const a = document.activeElement; if (a && a.blur) a.blur();
    sv.disabled = true; sv.textContent = 'Salvando…';
    try { await api('cm_regras_salvar', { regras: CM.ed }); CM.sujo = false; aviso('Regras salvas ✓'); CM.d = null; await cmCarregar(); }
    catch (e) { aviso(e.message); sv.disabled = false; sv.textContent = 'Salvar as regras'; }
  };
  $$('[data-cmtira]').forEach(b => b.onclick = () => { CM.nomes.splice(+b.dataset.cmtira, 1); cmRedesenhaNomes(); });
  const poe = $('#cm-poe'), ni = $('#cm-novo-nome');
  const pondo = () => { const n = (ni.value || '').trim().replace(/\s+/g, ' ').toLocaleUpperCase('pt-BR'); if (!n) { ni.focus(); return; } if (!CM.nomes.includes(n)) CM.nomes.push(n); cmRedesenhaNomes(); const x = $('#cm-novo-nome'); if (x) x.focus(); };
  if (poe && ni) { poe.onclick = pondo; ni.onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); pondo(); } }; }
  const nd = $('#cm-nomes-desfaz'); if (nd) nd.onclick = () => { CM.nomes = [...CM.d.vendedoras]; cmRedesenhaNomes(); };
  const ns = $('#cm-nomes-salvar'); if (ns) ns.onclick = async () => {
    ns.disabled = true; ns.textContent = 'Salvando…';
    try { const r = await api('cm_vendedoras_salvar', { vendedoras: CM.nomes }); CM.d.vendedoras = r.vendedoras; CM.nomes = [...r.vendedoras]; CM.d.hist = r.hist; aviso('Nomes salvos ✓'); CM.d = null; await cmCarregar(); }
    catch (e) { aviso(e.message); ns.disabled = false; ns.textContent = 'Salvar os nomes'; }
  };
}
