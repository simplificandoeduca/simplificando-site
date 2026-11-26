/* Play Space Finanças 3.0 (Play Space 1.69): o relatório por cliente, no alto da aba Vendas e pacotes.
   Pedido do dono: "uma opção de relatório com o nome do cliente e o pacote e o produto, tudo discriminado". Cada atendimento com pacote
   ou produto no período (e na unidade) escolhidos em cima: o cliente, cada pacote, cada produto, o total, como foi pago e quem vendeu.
   Com o PDF bonito e o Excel (uma linha por item, para filtrar). */
'use strict';
const VC = { chave: '', d: null, n: 0, busca: '', aberto: false };
const vcChave = () => { const [de, ate] = periodo(); return `${de}|${ate}|${UNI}`; };
function vcCartao() {
  const ok = VC.d && VC.chave === vcChave();
  setTimeout(() => { vcLigar(); if (!ok) vcCarregar(); }, 0);
  return `<div class="cartao" id="vc-cartao"><div class="vc-cab"><div><h2>Relatório por cliente</h2><p class="expl">Cada venda de pacote e de produto no período (as mais recentes primeiro), com o nome do cliente e tudo discriminado: o pacote, o produto, o total, como foi pago e quem vendeu.</p></div>
    <div class="lista-acoes"><button class="btn btn-ghost" id="vc-pdf">${typeof PDF_IC !== 'undefined' ? PDF_IC : ''} PDF</button><button class="btn btn-ghost" id="vc-excel">Exportar Excel</button></div></div>
    <div id="vc-corpo">${ok ? vcCorpo() : '<div class="vazio">Carregando o relatório…</div>'}</div></div>`;
}
async function vcCarregar() {
  const ch = vcChave(), n = ++VC.n, [de, ate] = periodo();
  try { const r = await api('vc_listar', { de, ate, u: UNI }); if (n !== VC.n) return; VC.d = r; VC.chave = ch; }
  catch (e) { const c = $('#vc-corpo'); if (c) c.innerHTML = `<div class="vazio">${esc(e.message)}</div>`; return; }
  const c = $('#vc-corpo'); if (c) { c.innerHTML = vcCorpo(); vcLigar(); }
}
const vcNorm = t => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
function vcCorpo() {
  const D2 = VC.d, T = D2.total, b = vcNorm(VC.busca).trim();
  const bd = /[a-z]/.test(b) ? '' : b.replace(/\D/g, '');   /* 2.25: o CPF e o telefone só com números (com ou sem pontos e traço) */
  const L = D2.linhas.slice().reverse().filter(x => !b || vcNorm(`${x.resp} ${x.cri} ${x.tel} ${x.pacotes.map(p => p.n).join(' ')} ${x.produtos.map(p => p.n).join(' ')} ${x.vend}`).includes(b) || (bd.length >= 3 && (String(x.cpf || '').includes(bd) || String(x.tel || '').replace(/\D/g, '').includes(bd))));
  const MAX = 300, mostra = VC.aberto ? L : L.slice(0, 40);
  const lin = x => `<tr><td class="vc-q"><b>${esc(dataBR(x.quando.slice(0, 10)).slice(0, 5))}</b> <small>${esc(x.quando.slice(11, 16))}</small>${UNI === 'todas' && UNIS.length > 1 ? `<small>${esc(nomeUni(x.u))}</small>` : ''}</td>
      <td><b>${esc(x.resp || x.cri || '—')}</b>${x.resp && x.cri ? `<small>${esc(x.cri)}</small>` : ''}${x.tel ? `<small>${esc(x.tel)}</small>` : ''}</td>
      <td>${x.pacotes.length ? x.pacotes.map(p => `<span class="vc-i">${esc(p.n)}<small>${String(p.h).replace('.', ',')} h${p.com ? ' · com ' + esc(p.com) : ''}</small><em>${brl(p.v)}</em></span>`).join('') : '<span class="sub">—</span>'}</td>
      <td>${x.produtos.length ? x.produtos.map(p => `<span class="vc-i">${p.q > 1 ? p.q + ' × ' : ''}${esc(p.n)}<em>${brl(p.v)}</em></span>`).join('') : '<span class="sub">—</span>'}</td>
      <td class="v"><b>${brl(x.total)}</b>${x.desconto > 0.004 ? `<small>desconto ${brl(x.desconto)}</small>` : ''}</td>
      <td>${x.formas.length ? x.formas.map(f => `<small class="vc-f">${esc(f.n)} ${brl(f.v)}</small>`).join('') : ''}${x.aReceber > 0.004 ? `<small class="vc-ar">a receber ${brl(x.aReceber)}</small>` : ''}</td>
      <td>${x.vend ? esc(x.vend) : '<span class="sub">—</span>'}</td></tr>`;
  return `<div class="grade fs-kpis vc-kpis">${kpi('Total vendido', brl(T.total), `${int(T.atend)} ${T.atend === 1 ? 'atendimento' : 'atendimentos'} · ${int(T.clientes)} ${T.clientes === 1 ? 'cliente' : 'clientes'}`)}${kpi('Pacotes', int(T.pacN), brl(T.pacV))}${kpi('Produtos', int(T.prodN), brl(T.prodV))}${kpi('Descontos', brl(T.desconto), T.aReceber > 0.004 ? `a receber ${brl(T.aReceber)}` : 'nada a receber')}</div>
    ${D2.linhas.length ? `<div class="chips vc-filtro"><input class="chip g-busca" id="vc-busca" type="search" placeholder="Buscar CPF, nome, telefone, pacote ou produto…" value="${esc(VC.busca)}" aria-label="Buscar no relatório"><span class="sub">${int(L.length)} de ${int(D2.linhas.length)}</span></div>
    <div class="rolar"><table class="vc-tab"><thead><tr><th>Quando</th><th>Cliente</th><th>Pacote</th><th>Produto</th><th class="v">Total</th><th>Pago</th><th>Vendedora</th></tr></thead><tbody>${mostra.map(lin).join('')}</tbody></table></div>
    ${L.length > mostra.length ? `<button class="link" id="vc-mais">Mostrar todos (${int(Math.min(L.length, MAX))})</button>` : ''}` : '<div class="vazio">Nenhuma venda de pacote ou de produto neste período.</div>'}`;
}
function vcLigar() {
  const pdf = $('#vc-pdf'); if (pdf) pdf.onclick = () => { const [de, ate] = periodo(); pdfBaixar(pdf, { acao: 'vc_pdf', de, ate, u: UNI }, `play-space-vendas-por-cliente-${de}${de !== ate ? '-a-' + ate : ''}.pdf`); };
  const xl = $('#vc-excel'); if (xl) xl.onclick = vcExcel;
  const bu = $('#vc-busca'); if (bu) bu.oninput = () => { VC.busca = bu.value; const c = $('#vc-corpo'), pos = bu.selectionStart; c.innerHTML = vcCorpo(); vcLigar(); const n = $('#vc-busca'); if (n) { n.focus(); try { n.setSelectionRange(pos, pos); } catch (e) { /* sem cursor */ } } };
  const mais = $('#vc-mais'); if (mais) mais.onclick = () => { VC.aberto = true; const c = $('#vc-corpo'); c.innerHTML = vcCorpo(); vcLigar(); };
}
function vcExcel() {
  if (!VC.d || VC.chave !== vcChave()) { aviso('Espere o relatório carregar.'); return; }
  const rows = [];
  for (const x of VC.d.linhas) {
    const base = [x.quando.slice(0, 10), x.quando.slice(11, 16), nomeUni(x.u), x.resp, x.cri, x.tel], pago = x.formas.map(f => `${f.n} ${brl(f.v)}`).join(' + ');
    for (const p of x.pacotes) rows.push([...base, 'Pacote', p.n + (p.com ? ' (com ' + p.com + ')' : ''), p.h, 1, p.v, pago, x.vend]);
    for (const p of x.produtos) rows.push([...base, 'Produto', p.n, '', p.q, p.v, pago, x.vend]);
  }
  if (!rows.length) { aviso('Nenhuma venda para exportar.'); return; }
  const [de, ate] = periodo(), soma = i => Math.round(rows.reduce((t, l) => t + (+l[i] || 0), 0) * 100) / 100;
  gBaixa(gXlsx([{ nome: 'Vendas por cliente', cols: [['Dia', 12, 'data'], ['Hora', 8, 'txt'], ['Unidade', 16, 'txt'], ['Responsável', 26, 'txt'], ['Criança', 20, 'txt'], ['Telefone', 16, 'txt'], ['Tipo', 10, 'txt'], ['Pacote ou produto', 32, 'txt'], ['Horas', 8, 'txt'], ['Qtd', 6, 'int'], ['Valor', 13, 'brl'], ['Pagamento', 30, 'txt'], ['Vendedora', 16, 'txt']], linhas: rows,
    total: ['Total', '', '', '', '', '', '', '', '', soma(9), soma(10), '', ''] }]), `play-space-vendas-por-cliente-${de}${de !== ate ? '-a-' + ate : ''}.xlsx`);
}
