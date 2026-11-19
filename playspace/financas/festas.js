/* Play Space Finanças 2.3 (Play Space 1.62): as Festas, com o lucro real de cada uma (logo abaixo do Controle de caixa).
   Pedido do dono: do sistema, a data da venda, quem vendeu, o valor bruto, a data da festa, a forma de pagamento e em quantas vezes; à mão
   (o dono lança, a equipe não): buffet, salgados, papelaria, o adiantamento (R$ 500 para balão e outras despesas) e o que retornou dele.
   No fim de cada linha, o lucro real. Digita como numa planilha (Enter desce, Tab vai para o lado), com a cara do sistema.
   Usa o que a tela das Finanças já tem: $, $$, esc, brl, int, dataBR, api, aviso, kpi, corUni, nomeUni, UNIS, cxNum, cxFmt, gXlsx, gBaixa. */
'use strict';
const FS = { mes: '', u: 'todas', busca: '', d: null, n: 0 };
const FS_MAO = [['buffet', 'Buffet'], ['salgados', 'Salgados'], ['papelaria', 'Papelaria'], ['adiant', 'Adiantamento'], ['retornado', 'Retornado']];
const FS_SEM = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
try { const g = JSON.parse(localStorage.getItem('psfin-festas') || 'null'); if (g && /^\d{4}-\d{2}$/.test(g.mes || '')) FS.mes = g.mes; if (g && g.u) FS.u = g.u; } catch (e) { /* sem o guardado */ }
if (!FS.mes) { const h = new Date(); FS.mes = `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}`; }
const fsGuarda = () => { try { localStorage.setItem('psfin-festas', JSON.stringify({ mes: FS.mes, u: FS.u })); } catch (e) { /* sem espaço */ } };
const fsMesNome = ym => { const [a, m] = ym.split('-'); return ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'][+m - 1] + ' de ' + a; };
const fsNorm = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
/* as festas que aparecem: a unidade escolhida e a busca (aniversariante, tema ou quem vendeu) */
function fsLinhas() {
  const D = FS.d; if (!D) return [];
  const b = fsNorm(FS.busca).trim();
  return D.festas.filter(f => (FS.u === 'todas' || +f.u === +FS.u) && (!b || fsNorm(f.aniv + ' ' + f.tema + ' ' + f.vendedora).includes(b)));
}
/* a conta da linha com o que está digitado (mesmo antes de salvar) */
function fsConta(f) {
  const n = k => +f[k] || 0, gasto = Math.round((n('adiant') - n('retornado')) * 100) / 100, custos = Math.round((n('buffet') + n('salgados') + n('papelaria') + gasto) * 100) / 100;
  return { gasto, custos, lucro: Math.round((n('bruto') - custos) * 100) / 100 };
}

function festasDesenhar() {
  const t = $('#tela'); if (!t) return;
  const unis = UNIS.filter(x => x.ativo).map(x => x.id);
  t.innerHTML = `<div class="fs-tela"><div class="topo"><div><h1>Festas</h1><div class="sub">O lucro real de cada festa: o que veio do sistema e o que você lança</div></div>
      <div class="lista-acoes"><button class="btn btn-ghost" id="fs-excel">Exportar Excel</button></div></div>
    <div class="filtros"><div class="chips"><button class="chip" data-fsmes="-1" aria-label="Mês anterior">‹</button><b class="g-mes">${esc(fsMesNome(FS.mes))}</b><button class="chip" data-fsmes="1" aria-label="Mês seguinte">›</button>
      ${unis.length > 1 ? `<button class="chip ${FS.u === 'todas' ? 'on' : ''}" data-fsu="todas">Todas as unidades</button>${unis.map(u => `<button class="chip ${String(FS.u) === String(u) ? 'on' : ''}" data-fsu="${u}"><i style="background:${corUni(u)}"></i>${esc(nomeUni(u))}</button>`).join('')}` : ''}
      <input class="chip g-busca" id="fs-busca" type="search" placeholder="Buscar aniversariante…" value="${esc(FS.busca)}" aria-label="Buscar festa"></div></div>
    <div id="fs-corpo">${FS.d && FS.d.mes === FS.mes ? fsCorpo() : '<div class="vazio">Carregando as festas…</div>'}</div></div>`;
  $$('[data-fsmes]').forEach(b => b.onclick = () => { const [a, m] = FS.mes.split('-').map(Number), d = new Date(a, m - 1 + +b.dataset.fsmes, 1); FS.mes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; fsGuarda(); festasDesenhar(); });
  $$('[data-fsu]').forEach(b => b.onclick = () => { FS.u = b.dataset.fsu === 'todas' ? 'todas' : +b.dataset.fsu; fsGuarda(); festasDesenhar(); });
  const bu = $('#fs-busca'); bu.oninput = () => { FS.busca = bu.value; const c = $('#fs-corpo'); if (c && FS.d) { c.innerHTML = fsCorpo(); fsLigar(); } };
  $('#fs-excel').onclick = fsExcel;
  if (FS.d && FS.d.mes === FS.mes) fsLigar(); else fsCarregar();
}
async function fsCarregar() {
  const n = ++FS.n, mes = FS.mes, c = $('#fs-corpo'); if (c) c.classList.add('carregando');
  try { const r = await api('fs_listar', { mes }); if (n !== FS.n || ABA !== 'festas') return; FS.d = r; }
  catch (e) { if (n === FS.n) { aviso(e.message); const c2 = $('#fs-corpo'); if (c2) { c2.classList.remove('carregando'); c2.innerHTML = `<div class="vazio">${esc(e.message)}</div>`; } } return; }
  if (FS.mes === mes) festasDesenhar();
}
function fsKpis(L) {
  const s = k => Math.round(L.reduce((t, f) => t + (k === 'gasto' || k === 'custos' || k === 'lucro' ? fsConta(f)[k] : +f[k] || 0), 0) * 100) / 100;
  const bruto = s('bruto'), custos = s('custos'), lucro = s('lucro'), faltam = L.filter(f => f.falta).length;
  return `${kpi('Festas no mês', int(L.length), L.length ? (faltam ? `<b>${int(faltam)}</b> sem os custos lançados` : 'todas com os custos lançados') : 'nenhuma festa')}
    ${kpi('Valor bruto', brl(bruto), 'o que as festas venderam')}
    ${kpi('Custos', brl(custos), 'buffet, salgados, papelaria e o gasto do adiantamento')}
    ${kpi('Lucro real', brl(lucro), bruto > 0.004 ? `margem de ${(lucro / bruto * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%` : '', lucro < -0.004 ? 'alerta' : 'lucro')}`;
}
function fsCorpo() {
  const L = fsLinhas();
  if (!FS.d.festas.length) return `<div class="grade fs-kpis" id="fs-kpis">${fsKpis([])}</div><div class="cartao"><div class="vazio">Nenhuma festa em ${esc(fsMesNome(FS.mes).toLowerCase())}.</div></div>`;
  const mao = (f, k, ph) => `<input class="fs-in num" data-fsc="${k}" inputmode="decimal" autocomplete="off" placeholder="${ph || '—'}" value="${esc(cxFmt(f[k]))}" aria-label="${esc(FS_MAO.find(x => x[0] === k)[1])} · festa de ${esc(f.aniv)}">`;
  const linha = f => { const x = new Date(f.data + 'T12:00:00'), kids = f.contratadas + f.extra, c = fsConta(f);
    const credito = f.formas.some(p => p.f === 'credito');
    const vezes = credito ? (f.parcSis ? `<span class="fs-vezes">${f.parcSis}x</span>` : `<select class="fs-vz" data-fsc="parcelas" aria-label="Em quantas vezes no crédito · festa de ${esc(f.aniv)}" title="Em quantas vezes foi no crédito (a venda não disse)"><option value="">?x</option>${[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map(n => `<option value="${n}" ${f.parcelas === n ? 'selected' : ''}>${n}x</option>`).join('')}</select>`) : '';
    return `<tr data-fs="${f.id}" class="${f.cancelada ? 'fs-canc' : ''}">
      <td class="fs-fixa"><b>${esc(f.aniv || 'Festa')}</b><small>${FS_SEM[x.getDay()]} ${dataBR(f.data).slice(0, 5)}${f.hora ? ' · ' + esc(f.hora) : ''}${UNIS.filter(u => u.ativo).length > 1 ? ` · <i class="uni-dot" style="background:${corUni(f.u)}"></i>${esc(nomeUni(f.u))}` : ''}</small>${f.tema ? `<small>${esc(f.tema)}</small>` : ''}${f.cancelada ? '<span class="selo ruim">venda cancelada</span>' : f.falta ? '<span class="selo aviso">falta lançar</span>' : ''}</td>
      <td class="q">${f.venda ? `${dataBR(f.venda).slice(0, 5)} <span class="sub">${esc(f.vendaHora)}</span><small>por ${esc(f.vendedora)}</small>` : '—'}</td>
      <td class="v">${kids ? `${int(kids)}${f.extra ? `<small>${int(f.contratadas)} + ${int(f.extra)} ${f.extra === 1 ? 'adicional' : 'adicionais'}</small>` : ''}<small>vieram ${int(f.vieram)}</small>` : '—'}</td>
      <td class="v fs-bruto"><b>${brl(f.bruto)}</b>${f.bruto - f.pago > 0.004 ? `<small class="fs-aberto">a receber ${brl(f.bruto - f.pago)}</small>` : ''}</td>
      <td class="fs-pag">${f.formas.length ? f.formas.map(p => `<span class="fs-forma"><b>${esc(p.nome)}</b>${p.f === 'credito' ? vezes : ''}<span class="num">${brl(p.v)}</span></span>`).join('') : '<span class="sub">—</span>'}</td>
      <td class="fs-m">${mao(f, 'buffet')}<small class="fs-porc" data-fsporc>${kids && +f.buffet > 0 ? brl(f.buffet / kids) + ' por criança' : ''}</small></td>
      <td class="fs-m">${mao(f, 'salgados')}</td><td class="fs-m">${mao(f, 'papelaria')}</td>
      <td class="fs-m">${mao(f, 'adiant', 'ex.: 500')}</td><td class="fs-m">${mao(f, 'retornado')}</td>
      <td class="v fs-gasto" data-fsgasto>${brl(c.gasto)}</td>
      <td class="v fs-lucro ${c.lucro < -0.004 ? 'neg' : ''}" data-fslucro><b>${brl(c.lucro)}</b></td></tr>`; };
  return `<div class="grade fs-kpis" id="fs-kpis">${fsKpis(L)}</div>
    <section class="cartao fs-cartao"><div class="rolar fs-rolar"><table class="fs-tab">
      <thead><tr class="fs-grupos"><th class="fs-fixa"></th><th colspan="4" class="fs-g-sis">Do sistema</th><th colspan="5" class="fs-g-mao">Você lança</th><th colspan="2" class="fs-g-res">Resultado</th></tr>
        <tr><th class="fs-fixa">Festa</th><th>Venda</th><th class="v">Crianças</th><th class="v">Valor bruto</th><th>Pagamento</th>${FS_MAO.map(([, n]) => `<th class="v fs-m-th">${n}</th>`).join('')}<th class="v">Gasto do adiant.</th><th class="v">Lucro real</th></tr></thead>
      <tbody>${L.length ? L.map(linha).join('') : '<tr><td colspan="12"><div class="vazio">Nenhuma festa com essa busca.</div></td></tr>'}</tbody>
      <tfoot id="fs-tot">${fsTotal(L)}</tfoot></table></div>
      <p class="fs-expl"><b>Lucro real</b> = valor bruto − buffet − salgados − papelaria − gasto do adiantamento (adiantamento − retornado). Enter desce para a festa de baixo; o valor fica salvo ao sair do campo.</p></section>`;
}
function fsTotal(L) {
  const s = k => Math.round(L.reduce((t, f) => t + (k === 'gasto' || k === 'lucro' ? fsConta(f)[k] : +f[k] || 0), 0) * 100) / 100;
  return `<tr><td class="fs-fixa">Total · ${int(L.length)} ${L.length === 1 ? 'festa' : 'festas'}</td><td></td><td class="v">${int(L.reduce((t, f) => t + f.contratadas + f.extra, 0))}</td><td class="v">${brl(s('bruto'))}</td><td></td>
    ${FS_MAO.map(([k]) => `<td class="v">${brl(s(k))}</td>`).join('')}<td class="v">${brl(s('gasto'))}</td><td class="v fs-lucro ${s('lucro') < -0.004 ? 'neg' : ''}">${brl(s('lucro'))}</td></tr>`;
}
/* a linha, o total e os números de cima, com o que está digitado */
function fsPinta(tr) {
  const f = FS.d.festas.find(x => x.id === +tr.dataset.fs); if (!f) return;
  const v = {}; let ruim = false;
  $$('input[data-fsc]', tr).forEach(i => { const n = cxNum(i.value); i.classList.toggle('cx-ruim', Number.isNaN(n)); if (Number.isNaN(n)) ruim = true; v[i.dataset.fsc] = Number.isNaN(n) ? f[i.dataset.fsc] : n; });
  const x = { ...f, ...v }, c = fsConta(x), kids = f.contratadas + f.extra;
  const re = $('input[data-fsc="retornado"]', tr); if (re && (+x.retornado || 0) > (+x.adiant || 0) + 0.004) { re.classList.add('cx-ruim'); re.title = 'O retornado passa do adiantamento'; } else if (re) re.title = '';
  $('[data-fsgasto]', tr).textContent = brl(c.gasto);
  const lc = $('[data-fslucro]', tr); lc.innerHTML = `<b>${brl(c.lucro)}</b>`; lc.classList.toggle('neg', c.lucro < -0.004);
  const pc = $('[data-fsporc]', tr); if (pc) pc.textContent = kids && +x.buffet > 0 ? brl(x.buffet / kids) + ' por criança' : '';
  const L = fsLinhas().map(y => y.id === f.id ? x : y);
  $('#fs-tot').innerHTML = fsTotal(L); $('#fs-kpis').innerHTML = fsKpis(L);
  return ruim;
}
/* tocar no campo seleciona o que está nele (para digitar por cima). Na hora (não depois): selecionar depois apagava a 1ª tecla de quem
   digita rápido ("300" virava "00"); e o soltar do clique não desfaz a seleção */
function fsSeleciona(inp) {
  inp.addEventListener('focus', () => { try { inp.select(); } catch (e) { /* sem seleção */ } inp.dataset.sel = '1'; });
  inp.addEventListener('mouseup', e => { if (inp.dataset.sel === '1') { e.preventDefault(); inp.dataset.sel = ''; } });
  inp.addEventListener('keydown', () => { inp.dataset.sel = ''; });
}
function fsLigar() {
  $$('.fs-tab tbody tr[data-fs]').forEach(tr => {
    $$('input[data-fsc]', tr).forEach(inp => {
      inp.addEventListener('input', () => fsPinta(tr));
      fsSeleciona(inp);
      inp.addEventListener('change', () => fsGuardar(tr, inp));
      inp.addEventListener('keydown', e => {   /* como numa planilha: Enter (ou ↓) desce, Shift+Enter (ou ↑) sobe, na mesma coluna */
        const desce = (e.key === 'Enter' && !e.shiftKey) || e.key === 'ArrowDown', sobe = (e.key === 'Enter' && e.shiftKey) || e.key === 'ArrowUp';
        if (!desce && !sobe) return; e.preventDefault();
        const linhas = $$('.fs-tab tbody tr[data-fs]'), i = linhas.indexOf(tr), alvo = linhas[i + (desce ? 1 : -1)];
        const prox = alvo && $(`input[data-fsc="${inp.dataset.fsc}"]`, alvo); if (prox) prox.focus(); else inp.blur();
      });
    });
    const vz = $('select[data-fsc="parcelas"]', tr); if (vz) vz.addEventListener('change', () => fsGuardar(tr, vz));
  });
}
async function fsGuardar(tr, el) {
  const id = +tr.dataset.fs, campo = el.dataset.fsc, f = FS.d.festas.find(x => x.id === id); if (!f) return;
  let valor = el.value;
  if (el.tagName === 'INPUT') { const n = cxNum(el.value); if (Number.isNaN(n)) { aviso('Digite o valor em reais, ex.: 1.225,50'); return; } valor = n; el.value = cxFmt(n); }
  tr.classList.add('fs-salvando');
  try {
    const r = await api('fs_salvar', { id, campo, valor });
    const novo = r.festa; if (novo) { const i = FS.d.festas.findIndex(x => x.id === id); if (i >= 0) FS.d.festas[i] = novo; }
    fsPinta(tr);
    const sel = $('.fs-fixa .selo.aviso', tr); if (sel && novo && !novo.falta) sel.remove();
    tr.classList.remove('fs-salvando'); tr.classList.add('fs-salvo'); setTimeout(() => tr.classList.remove('fs-salvo'), 900);
  } catch (e) {
    tr.classList.remove('fs-salvando'); aviso(e.message);
    if (el.tagName === 'INPUT') el.value = cxFmt(f[campo]); else el.value = f.parcelas || '';
    fsPinta(tr);
  }
}
function fsExcel() {
  const L = fsLinhas(); if (!L.length) { aviso('Nenhuma festa para exportar.'); return; }
  const pag = f => f.formas.map(p => p.nome + (p.f === 'credito' && (f.parcSis || f.parcelas) ? ' ' + (f.parcSis || f.parcelas) + 'x' : '') + ' ' + brl(p.v)).join(' + ');
  const linhas = L.map(f => { const c = fsConta(f); return [f.data, f.aniv, f.tema, nomeUni(f.u), f.venda || '', f.vendedora, f.contratadas + f.extra, f.vieram, f.bruto, pag(f),
    f.buffet, f.salgados, f.papelaria, f.adiant, f.retornado, c.gasto, c.lucro]; });
  const soma = i => Math.round(linhas.reduce((t, l) => t + (+l[i] || 0), 0) * 100) / 100;
  gBaixa(gXlsx([{ nome: 'Festas', cols: [['Data da festa', 13, 'data'], ['Aniversariante', 26, 'txt'], ['Tema', 20, 'txt'], ['Unidade', 16, 'txt'], ['Data da venda', 13, 'data'], ['Vendido por', 18, 'txt'],
    ['Crianças', 10, 'int'], ['Vieram', 9, 'int'], ['Valor bruto', 14, 'brl'], ['Pagamento', 34, 'txt'], ['Buffet', 12, 'brl'], ['Salgados', 12, 'brl'], ['Papelaria', 12, 'brl'], ['Adiantamento', 14, 'brl'],
    ['Retornado', 12, 'brl'], ['Gasto do adiant.', 15, 'brl'], ['Lucro real', 14, 'brl']], linhas,
    total: ['Total', '', '', '', '', '', soma(6), soma(7), soma(8), '', soma(10), soma(11), soma(12), soma(13), soma(14), soma(15), soma(16)] }]), `play-space-festas-${FS.mes}.xlsx`);
  aviso('Planilha baixada ✓');
}
