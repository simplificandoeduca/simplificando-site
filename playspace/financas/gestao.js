/* Play Space Finanças 2.0: a gestão financeira (pedido do dono: "não quero mais nada por Excel; o que o financeiro tiver que alimentar,
   o sistema fornece os campos; e exportar tudo em Excel ou PDF, o PDF com gráficos e imagens, bonito para o dono olhar").
   - Lançamentos: receitas (faturamento), despesas, recebimentos (o líquido, por forma) e a Rede (a maquininha), com os campos da planilha.
   - Relatório: o ano mês a mês (ou um mês), com os gráficos; as mesmas contas da planilha.
   - Importar a planilha atual (.xlsx, lida aqui mesmo, sem programa nenhum) e exportar em Excel (.xlsx) e em PDF (feito no servidor).
   Usa o que a tela das Finanças já tem: $, $$, esc, brl, brlCurto, int, dataBR, api, aviso. */
'use strict';
const GT = {
  receita: { nome: 'Receitas', novo: 'Nova receita', expl: 'O faturamento: o que foi vendido (valor bruto), com o sub item, a filial e o segmento.' },
  despesa: { nome: 'Despesas', novo: 'Nova despesa', expl: 'O que foi gasto: custo fixo e custo variável (as despesas) e o investimento (à parte).' },
  recebimento: { nome: 'Recebimentos', novo: 'Novo recebimento', expl: 'O que entrou de verdade (o líquido), por forma de pagamento e por local.' },
  rede: { nome: 'Rede', novo: 'Novo pagamento da Rede', expl: 'O que a Rede (a maquininha) pagou no dia, por unidade. Fica à parte: não soma no líquido.' },
};
const GFORMAS = { pix: 'Pix', dinheiro: 'Dinheiro', credito: 'Crédito', debito: 'Débito', link: 'Link de pagamento', rede: 'Rede (maquininha)' };
const GMESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const GCOR = { bruto: '#2a78d6', liquido: '#1baf7a', desp: '#eb6834', res: '#1baf7a', inv: '#A39B91' };
const GHOJE = new Date(), GS = { tipo: 'receita', mes: `${GHOJE.getFullYear()}-${String(GHOJE.getMonth() + 1).padStart(2, '0')}`, filial: '', segmento: '', busca: '', dados: null, pag: 0, listas: null, imps: [],
  relAno: GHOJE.getFullYear(), relMes: 0, rel: null, n: 0, ult: {}, forn: [], apagar: null, painel: null, pagar: null, pagarVer: 'abertas', fornL: null, fornBusca: '' };
const GFPAG = { pix: 'Pix', boleto: 'Boleto', transferencia: 'Transferência', dinheiro: 'Dinheiro', cartao: 'Cartão de crédito', debito_auto: 'Débito automático' };   /* 2.1: como a despesa foi paga */
try { const g = JSON.parse(localStorage.getItem('psfin-gestao') || 'null'); if (g) { if (GT[g.tipo]) GS.tipo = g.tipo; if (/^\d{4}-\d{2}$/.test(g.mes || '')) GS.mes = g.mes; if (g.relAno) GS.relAno = +g.relAno; GS.relMes = +g.relMes || 0; GS.ult = g.ult || {}; } } catch (e) { /* sem o guardado */ }
const gGuarda = () => { try { localStorage.setItem('psfin-gestao', JSON.stringify({ tipo: GS.tipo, mes: GS.mes, relAno: GS.relAno, relMes: GS.relMes, ult: GS.ult })); } catch (e) { /* sem espaço */ } };
const gNorm = s => String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
const gMesNome = ym => { const [a, m] = ym.split('-'); return GMESES[+m - 1].replace(/^./, c => c.toUpperCase()) + ' de ' + a; };
const gUltimoDia = ym => { const [a, m] = ym.split('-').map(Number); return `${ym}-${String(new Date(a, m, 0).getDate()).padStart(2, '0')}`; };
const gLocal = (f, s) => (['Play', 'Casa Tom'].includes(s) && ['Morumbi', 'Anália Franco'].includes(f)) ? `${s} ${f}` : (s || f || 'Sem local');
const gPct = (a, b) => b > 0.004 ? (a / b * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%' : '–';

/* ---------- a tela (as duas abas novas: Lançamentos e Relatório financeiro) ---------- */
async function gestaoDesenhar(aba) {
  const t = $('#tela'); if (!t) return;
  if (!GS.listas) { t.innerHTML = '<div class="vazio">Carregando…</div>'; try { gPoeListas(await api('fl_listas')); } catch (e) { t.innerHTML = `<div class="vazio">${esc(e.message)}</div>`; return; } if (ABA !== aba) return; }
  if (aba === 'lanc') { t.innerHTML = telaLanc(); ligarLanc(); if (!GS.dados) carregarLanc(); }
  else if (aba === 'relat') { t.innerHTML = telaRel(); ligarRel(); if (!GS.rel || GS.rel.ano !== GS.relAno) carregarRel(); }
  else if (aba === 'fin') { t.innerHTML = telaPainel(); ligarPainel(); if (!GS.painel || GS.painel.mes !== GS.mes) carregarPainel(); }   /* 2.1 (gestao2.js) */
  else if (aba === 'pagar') { t.innerHTML = telaPagar(); ligarPagar(); if (!GS.pagar) carregarPagar(); }
  else if (aba === 'forn') { t.innerHTML = telaForn(); ligarForn(); if (!GS.fornL) carregarForn(); }
  else if (aba === 'plan') planDesenhar();   /* 2.1 (planilhas.js) */
}
/* 2.1: as listas, os fornecedores e as somas do a pagar (o número vermelho no menu); depois de lançar, tudo é recarregado */
function gPoeListas(r) { GS.listas = r.listas; GS.imps = r.importacoes || []; GS.forn = r.fornecedores || []; GS.apagar = r.apagar || null; gContaPagar(); }
function gContaPagar() { const b = $('[data-cont-pagar]'); if (!b) return; const S = GS.apagar, n = S ? S.vencidas.n + S.vencemHoje.n : 0; b.hidden = !n; b.textContent = n; b.title = S ? `${S.vencidas.n} vencidas · ${S.vencemHoje.n} vencem hoje` : ''; }
async function gSujou() { GS.dados = null; GS.rel = null; GS.painel = null; GS.pagar = null; GS.fornL = null; try { gPoeListas(await api('fl_listas')); } catch (e) { /* fica a lista de antes */ } }
const gOpcoes = (lista, sel, vazio) => (vazio != null ? `<option value="">${esc(vazio)}</option>` : '') + (lista || []).map(v => `<option${v === sel ? ' selected' : ''}>${esc(v)}</option>`).join('');
function telaLanc() {
  const T = GT[GS.tipo], D2 = GS.dados, L = GS.listas, de = GS.mes + '-01', ate = gUltimoDia(GS.mes);
  const cols = { receita: ['Data', 'Sub item', 'Local', 'Vendas', 'Crianças', 'Valor', 'Obs.'], despesa: ['Data', 'Fornecedor · descrição', 'Local', 'Grupo · centro de custo', 'Valor', 'Devido', 'Pago?'], recebimento: ['Data', 'Forma', 'Local', 'Valor', 'Obs.', 'Após o fechamento'], rede: ['Data', 'Filial', 'Valor', 'Obs.'] }[GS.tipo];
  const v = new Set(['Valor', 'Vendas', 'Crianças', 'Devido']);
  const linha = l => ({ receita: [dataBR(l.data), esc(l.descricao || '—'), esc(gLocal(l.filial, l.segmento)), int(l.qtd_vendas), int(l.qtd_criancas), brl(l.valor), esc(l.obs)],
    despesa: [dataBR(l.data), `${l.fornecedor ? `<b>${esc(l.fornecedor)}</b><br>` : ''}${esc(l.descricao)}${gRec(l)}${l.obs ? `<small class="g-obs">${esc(l.obs)}</small>` : ''}${gComp(l)}`, l.filial || l.segmento ? esc(gLocal(l.filial, l.segmento)) : '—', `${esc(l.grupo)}${l.centro ? `<br><span class="sub">${esc(l.centro)}</span>` : ''}`, brl(l.valor), gDevido(l), gTog(l) + gSituacao(l)],
    recebimento: [dataBR(l.data), esc(GFORMAS[l.forma] || l.forma), esc(gLocal(l.filial, l.segmento)), brl(l.valor), esc(l.obs), l.data_fech ? dataBR(l.data_fech) : ''],
    rede: [dataBR(l.data), esc(l.filial), brl(l.valor), esc(l.obs)] }[GS.tipo]);
  const resumo = D2 ? `<b>${int(D2.n)}</b> ${D2.n === 1 ? 'lançamento' : 'lançamentos'} · soma <b class="num">${brl(D2.soma)}</b>${GS.tipo === 'receita' ? ` · ${int(D2.qv)} vendas · ${int(D2.qc)} crianças` : ''}` : 'Carregando…';
  return `<div class="topo"><div><h1>Lançamentos</h1><div class="sub">O que o financeiro lança (no lugar da planilha) · ${esc(gMesNome(GS.mes))}</div></div>
      <div class="lista-acoes"><button class="btn btn-ghost" data-g="importar">Importar a planilha</button><button class="btn btn-ghost" data-g="excel">Exportar Excel</button><button class="btn btn-brand g-novo" data-g="novo">+ ${esc(T.novo)}</button></div></div>
    <div class="filtros"><div class="chips" role="group" aria-label="O que lançar">${Object.entries(GT).map(([k, x]) => `<button class="chip ${GS.tipo === k ? 'on' : ''}" data-gtipo="${k}">${x.nome}</button>`).join('')}</div>
      <div class="chips"><button class="chip" data-gmes="-1" aria-label="Mês anterior">‹</button><b class="g-mes">${esc(gMesNome(GS.mes))}</b><button class="chip" data-gmes="1" aria-label="Mês seguinte">›</button>
        <select class="chip ${GS.filial ? 'on' : ''}" id="g-f-filial" aria-label="Filial">${gOpcoes(L.filial, GS.filial, 'Todas as filiais')}</select>
        ${GS.tipo !== 'rede' ? `<select class="chip ${GS.segmento ? 'on' : ''}" id="g-f-seg" aria-label="Segmento">${gOpcoes(L.segmento, GS.segmento, 'Todos os segmentos')}</select>` : ''}
        <input class="chip g-busca" id="g-f-busca" type="search" placeholder="Buscar…" value="${esc(GS.busca)}" aria-label="Buscar"></div></div>
    ${GS.tipo === 'despesa' && D2 ? `<div class="grade g-cards">${kpi('Despesas do mês', brl(D2.soma), `${int(D2.n)} ${D2.n === 1 ? 'lançamento' : 'lançamentos'}`)}${kpi('Pagas', brl(D2.soma - D2.aberto), `${int(D2.n - D2.nAberto)} no mês`)}${kpi('Ainda devido', brl(D2.aberto), D2.nAberto ? `${int(D2.nAberto)} ${D2.nAberto === 1 ? 'conta' : 'contas'} a pagar · <a href="#" data-g="pagar">ver contas a pagar</a>` : 'nada a pagar', D2.aberto > 0.004 ? 'devido' : '')}${kpi('Em atraso', brl(D2.atraso || 0), D2.nAtraso ? `${int(D2.nAtraso)} ${D2.nAtraso === 1 ? 'conta vencida' : 'contas vencidas'}` : 'nada vencido', D2.nAtraso ? 'alerta' : '')}</div>` : ''}
    <div class="cartao"><div class="cab-cartao"><div><h2>${T.nome}</h2><p class="expl">${T.expl} Toque numa linha para corrigir.</p></div><div class="sub g-resumo">${resumo}</div></div>
      ${!D2 ? '<div class="vazio">Carregando…</div>' : !D2.linhas.length ? `<div class="vazio">Nada lançado em ${esc(gMesNome(GS.mes).toLowerCase())}${GS.filial || GS.segmento || GS.busca ? ' com este filtro' : ''}.<br><button class="btn btn-ghost" style="margin-top:12px" data-g="novo">+ ${esc(T.novo)}</button></div>`
        : `<div class="rolar"><table class="g-lanc"><thead><tr>${cols.map(c => `<th${v.has(c) ? ' class="v"' : ''}>${c}</th>`).join('')}</tr></thead><tbody>${gPorDia(D2.linhas, cols.length, l => `<tr data-gl="${l.id}" tabindex="0" title="Corrigir">${linha(l).map((x, i) => `<td${v.has(cols[i]) ? ' class="v"' : ''}>${x}${i === 0 && l.origem === 'planilha' ? '<small>da planilha</small>' : ''}</td>`).join('')}</tr>`)}</tbody></table></div>
          ${D2.mais ? `<button class="btn btn-ghost" style="margin-top:12px" data-g="mais">Mostrar mais</button>` : ''}`}</div>
    <p class="sub" style="margin-top:14px">Período: ${dataBR(de)} a ${dataBR(ate)}. ${GS.imps.length ? `Última importação da planilha: ${esc(quandoBR(GS.imps[0].em))}, por ${esc(GS.imps[0].por)} (${int(GS.imps[0].n)} linhas).` : 'A planilha ainda não foi importada.'}</p>`;
}
function ligarLanc() {
  $$('[data-gtipo]').forEach(b => b.onclick = () => { GS.tipo = b.dataset.gtipo; GS.dados = null; GS.pag = 0; gGuarda(); gestaoDesenhar('lanc'); carregarLanc(); });
  $$('[data-gmes]').forEach(b => b.onclick = () => { const [a, m] = GS.mes.split('-').map(Number), d = new Date(a, m - 1 + +b.dataset.gmes, 1); GS.mes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; GS.dados = null; gGuarda(); gestaoDesenhar('lanc'); carregarLanc(); });
  const f = $('#g-f-filial'), s = $('#g-f-seg'), bu = $('#g-f-busca');
  if (f) f.onchange = () => { GS.filial = f.value; carregarLanc(); }; if (s) s.onchange = () => { GS.segmento = s.value; carregarLanc(); };
  if (bu) { let t0 = 0; bu.oninput = () => { clearTimeout(t0); t0 = setTimeout(() => { GS.busca = bu.value.trim(); carregarLanc(true); }, 350); }; }
  $$('[data-g]').forEach(b => b.onclick = e => { e.preventDefault(); ({ novo: () => formLanc(GS.tipo), importar: () => folhaImportar(), excel: () => exportarExcel(+GS.mes.slice(0, 4)), mais: () => carregarLanc(false, true), pagar: () => { ABA = 'pagar'; desenhar(); } })[b.dataset.g](); });
  $$('tr[data-gl]').forEach(tr => { const abre = () => { const l = GS.dados.linhas.find(x => x.id === +tr.dataset.gl); if (l) formLanc(l.tipo, l); }; tr.onclick = abre; tr.onkeydown = e => { if (e.key === 'Enter') abre(); }; });
  gLigaTog(id => GS.dados.linhas.find(x => x.id === id));
}
async function carregarLanc(manterFoco, mais) {
  const n = ++GS.n, pag = mais ? GS.pag + 1 : 0;
  try { const r = await api('fl_listar', { tipo: GS.tipo, de: GS.mes + '-01', ate: gUltimoDia(GS.mes), filial: GS.filial, segmento: GS.tipo === 'rede' ? '' : GS.segmento, busca: GS.busca, pagina: pag }); if (n !== GS.n) return;
    if (mais && GS.dados) r.linhas = GS.dados.linhas.concat(r.linhas); GS.dados = r; GS.pag = pag; }
  catch (e) { if (n === GS.n) aviso(e.message); return; }
  if (ABA !== 'lanc') return; const foco = manterFoco && document.activeElement && document.activeElement.id === 'g-f-busca'; const pos = foco ? document.activeElement.selectionStart : 0;
  gestaoDesenhar('lanc'); if (foco) { const b = $('#g-f-busca'); if (b) { b.focus(); b.setSelectionRange(pos, pos); } }
}

/* 2.1: a situação da despesa (paga, a pagar, vencida) e as linhas separadas por dia, com a soma do dia */
const GSEM = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
function gHoje() { return iso(new Date()); }
/* 2.1: o que ainda é devido (o valor, se está a pagar; zero, se já foi paga) e a chave "Pago?" que dá baixa na própria linha */
function gDevido(l) { if (l.situacao !== 'aberto') return '<span class="g-devido zero">R$ 0,00</span>'; return `<span class="g-devido${l.vencimento < gHoje() ? ' atraso' : ''}">${brl(l.valor)}</span>`; }
function gTog(l) { const p = l.situacao !== 'aberto'; return `<button type="button" class="g-tog${p ? ' on' : ''}" data-gtog="${l.id}" role="switch" aria-checked="${p}" title="${p ? 'Paga · toque para desfazer o pagamento' : 'A pagar · toque para dar baixa (pagar)'}"><i></i></button>`; }
function gLigaTog(acha) {
  $$('[data-gtog]').forEach(b => b.onclick = async e => { e.stopPropagation(); const l = acha(+b.dataset.gtog); if (!l) return;
    if (l.situacao === 'aberto') { folhaPagar(l); return; }
    if (!b.dataset.certeza) { b.dataset.certeza = '1'; b.classList.add('pergunta'); aviso('Toque de novo para desfazer o pagamento (a conta volta a ser devida)'); setTimeout(() => { delete b.dataset.certeza; b.classList.remove('pergunta'); }, 4000); return; }
    try { await api('ap_reabrir', { id: l.id }); aviso('Pagamento desfeito ✓ (a conta voltou a ser devida)'); gDepois(); } catch (x) { aviso(x.message); } });
}
function gSituacao(l) {
  if (l.situacao !== 'aberto') return `<span class="selo bom">Paga${l.pago_em && l.pago_em !== l.data ? ` em ${dataBR(l.pago_em).slice(0, 5)}` : ''}</span>${l.forma && GFPAG[l.forma] ? `<br><span class="sub">${esc(GFPAG[l.forma])}</span>` : ''}`;
  const h = gHoje(); return l.vencimento < h ? `<span class="selo ruim">Vencida · ${dataBR(l.vencimento).slice(0, 5)}</span>` : l.vencimento === h ? '<span class="selo aviso">Vence hoje</span>' : `<span class="selo aviso">A pagar · ${dataBR(l.vencimento).slice(0, 5)}</span>`;
}
function gPorDia(L, ncols, linha) {
  let o = '', dia = ''; const soma = {}, qtd = {}; L.forEach(l => { soma[l.data] = (soma[l.data] || 0) + l.valor; qtd[l.data] = (qtd[l.data] || 0) + 1; });
  for (const l of L) { if (l.data !== dia) { dia = l.data; const d = new Date(dia + 'T12:00:00'), n = qtd[dia];
      o += `<tr class="g-dia"><td colspan="${ncols}"><b>${GSEM[d.getDay()].replace(/^./, c => c.toUpperCase())}, ${dataBR(dia)}</b><span>${int(n)} ${n === 1 ? 'lançamento' : 'lançamentos'} · ${brl(soma[dia])}</span></td></tr>`; }
    o += linha(l); }
  return o;
}

/* ---------- lançar e corrigir: os campos de cada tipo (os nomes da planilha) ---------- */
function gFolha(titulo, corpo, { ok, outra, remover, fazer, aoRemover }) {
  const f = document.createElement('div'); f.className = 'fundo';
  f.innerHTML = `<form class="folha g-folha" novalidate role="dialog" aria-modal="true" aria-label="${esc(titulo)}"><h3>${esc(titulo)}</h3>${corpo}<p class="erro" hidden></p>
    <div class="acoes"><button type="button" class="btn btn-ghost" data-fechar>Cancelar</button>${outra ? `<button type="button" class="btn btn-ghost" data-outra>${esc(outra)}</button>` : ''}<button class="btn btn-brand" type="submit">${esc(ok)}</button></div>
    ${remover ? `<button type="button" class="link g-tirar" data-tirar>${esc(remover)}</button>` : ''}</form>`;
  document.body.appendChild(f); const er = $('.erro', f); let ini = null;
  const fecha = () => { f.remove(); document.removeEventListener('keydown', k); }, k = e => { if (e.key === 'Escape') fecha(); };
  document.addEventListener('keydown', k); f.addEventListener('pointerdown', e => { ini = e.target; });
  f.addEventListener('click', e => { if ((e.target === f && ini === f && !String(getSelection() || '').trim()) || e.target.hasAttribute('data-fechar')) fecha(); });
  const vai = async (de, depois) => { $$('button', f).forEach(b => { b.disabled = true; }); er.hidden = true;
    try { await de(); depois(); } catch (x) { er.textContent = x.message; er.hidden = false; } $$('button', f).forEach(b => { b.disabled = false; }); };
  $('form', f).onsubmit = e => { e.preventDefault(); vai(() => fazer(f), fecha); };
  if (outra) $('[data-outra]', f).onclick = () => vai(() => fazer(f), () => { $$('[data-limpa]', f).forEach(i => { i.value = ''; }); const p = $('[data-limpa]', f); if (p) p.focus(); aviso('Lançado ✓ Pode lançar o próximo'); });
  if (remover) { const b = $('[data-tirar]', f); b.onclick = () => { if (b.dataset.certeza) vai(aoRemover, fecha); else { b.dataset.certeza = '1'; b.textContent = 'Toque de novo para tirar (fica registrado quem tirou)'; } }; }
  setTimeout(() => { const i = $('input:not([type=hidden]), select', f); if (i) i.focus(); }, 50); return f;
}
const gCampo = (id, rot, html, larg) => `<div class="g-c${larg ? ' g-larg' : ''}"><label class="lbl" for="${id}">${rot}</label>${html}</div>`;
const gInp = (id, val, extra) => `<input class="inp" id="${id}" value="${esc(val == null ? '' : val)}" ${extra || ''}>`;
const gDl = (id, lista) => `<datalist id="${id}">${(lista || []).map(v => `<option value="${esc(v)}">`).join('')}</datalist>`;
const gValor = v => v == null || v === '' ? '' : (+v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function formLanc(tipo, l) {
  if (tipo === 'despesa') return formDespesa(l);   /* 2.1: a despesa tem o formulário dela (gestao2.js) */
  const L = GS.listas, u = GS.ult[tipo] || {}, x = l || { data: u.data || (GS.mes === iso(new Date()).slice(0, 7) ? iso(new Date()) : GS.mes + '-01'), filial: u.filial || '', segmento: u.segmento || '', grupo: u.grupo || '', forma: u.forma || '' };
  const subs = s => (L.subitem || {})[s] || [];
  let c = gCampo('g-data', 'Data', gInp('g-data', x.data, 'type="date" required')) + gCampo('g-valor', 'Valor (R$)', gInp('g-valor', gValor(x.valor), 'inputmode="decimal" placeholder="0,00" required data-limpa'));
  if (tipo === 'rede') c += gCampo('g-filial', 'Filial', `<select class="inp" id="g-filial">${gOpcoes(['Morumbi', 'Anália Franco'].concat((L.filial || []).filter(f => !['Morumbi', 'Anália Franco'].includes(f))), x.filial || 'Morumbi')}</select>`);
  else c += gCampo('g-filial', 'Filial', gInp('g-filial', x.filial, 'list="g-dl-filial" autocomplete="off" placeholder="Morumbi, Anália Franco…"') + gDl('g-dl-filial', L.filial))
    + gCampo('g-seg', 'Segmento', gInp('g-seg', x.segmento, 'list="g-dl-seg" autocomplete="off" placeholder="Play, Casa Tom…"') + gDl('g-dl-seg', L.segmento));
  if (tipo === 'receita') c += gCampo('g-desc', 'Sub item', gInp('g-desc', x.descricao, 'list="g-dl-sub" autocomplete="off" placeholder="Recreação, Pacote, Vestido…" data-limpa') + gDl('g-dl-sub', subs(x.segmento)))
    + gCampo('g-qv', 'Qtde de vendas', gInp('g-qv', x.qtd_vendas || '', 'inputmode="numeric" data-limpa')) + gCampo('g-qc', 'Qtde de crianças', gInp('g-qc', x.qtd_criancas || '', 'inputmode="numeric" data-limpa'));
  if (tipo === 'despesa') c += gCampo('g-desc', 'Descrição', gInp('g-desc', x.descricao, 'required data-limpa placeholder="Ex.: Conta de luz de outubro"'), true)
    + gCampo('g-grupo', 'Grupo de despesa', `<select class="inp" id="g-grupo">${gOpcoes(L.grupo, x.grupo || 'Custo Fixo')}</select>`)
    + gCampo('g-centro', 'Centro de custo', gInp('g-centro', x.centro, 'list="g-dl-centro" autocomplete="off" placeholder="RH, Aluguel, Luz…"') + gDl('g-dl-centro', L.centro));
  if (tipo === 'recebimento') c += gCampo('g-forma', 'Forma', `<select class="inp" id="g-forma">${Object.entries(GFORMAS).filter(([k]) => k !== 'rede').map(([k, n]) => `<option value="${k}"${k === (x.forma || 'pix') ? ' selected' : ''}>${n}</option>`).join('')}</select>`)
    + gCampo('g-fech', 'Data após o fechamento <small>(se houver)</small>', gInp('g-fech', x.data_fech, 'type="date"'));
  c += gCampo('g-obs', 'Observações', gInp('g-obs', x.obs, 'data-limpa placeholder="Opcional"'), true);
  const quem = l ? `<p class="sub" style="margin:10px 0 0">${l.origem === 'planilha' ? 'Veio da planilha' : 'Lançado'} por ${esc(l.criado_por || '—')} em ${esc(quandoBR(l.criado_em))}${l.alterado_por ? ` · corrigido por ${esc(l.alterado_por)} em ${esc(quandoBR(l.alterado_em))}` : ''}</p>` : '';
  const f = gFolha(l ? 'Corrigir ' + GT[tipo].nome.toLowerCase().replace(/s$/, '') : GT[tipo].novo, `<div class="g-grid">${c}</div>${quem}`, {
    ok: l ? 'Salvar' : 'Lançar', outra: l ? '' : 'Lançar e mais um', remover: l ? 'Tirar este lançamento' : '',
    fazer: async F => { const val = id => ($('#' + id, F) || {}).value || '', dados = { data: val('g-data'), valor: val('g-valor'), filial: val('g-filial'), segmento: val('g-seg'), descricao: val('g-desc'), obs: val('g-obs'),
        qtd_vendas: val('g-qv'), qtd_criancas: val('g-qc'), grupo: val('g-grupo'), centro: val('g-centro'), forma: val('g-forma'), data_fech: val('g-fech') };
      if (!dados.valor.trim()) throw new Error('Digite o valor.');
      const r = await api('fl_salvar', { id: l ? l.id : 0, tipo, lanc: dados }); GS.listas = r.listas || GS.listas;
      if (!l) { GS.ult[tipo] = { data: dados.data, filial: r.lanc.filial, segmento: r.lanc.segmento, grupo: r.lanc.grupo, forma: r.lanc.forma }; gGuarda(); }
      if (r.lanc.data.slice(0, 7) !== GS.mes && !l) aviso(`Lançado em ${dataBR(r.lanc.data)} (outro mês)`); gDepois(); },
    aoRemover: async () => { await api('fl_remover', { id: l.id }); aviso('Lançamento tirado ✓'); gDepois(); } });
  const sg = $('#g-seg', f); if (sg) sg.addEventListener('input', () => { const d = $('#g-dl-sub', f); if (d) d.innerHTML = subs(sg.value.trim()).map(v => `<option value="${esc(v)}">`).join(''); });
}

/* ---------- importar a planilha (.xlsx): lida aqui mesmo (um .xlsx é um zip com XML), as linhas vão ao servidor aos pedaços ---------- */
async function lerXlsx(buf) {
  const u8 = new Uint8Array(buf), dv = new DataView(buf); let e = -1;
  for (let i = u8.length - 22; i >= Math.max(0, u8.length - 70000); i--) if (dv.getUint32(i, true) === 0x06054b50) { e = i; break; }
  if (e < 0) throw new Error('Este arquivo não é uma planilha .xlsx (do Excel). Salve como .xlsx e tente de novo.');
  const ent = {}, dec = new TextDecoder(); let p = dv.getUint32(e + 16, true);
  for (let k = 0, n = dv.getUint16(e + 10, true); k < n && dv.getUint32(p, true) === 0x02014b50; k++) { const nl = dv.getUint16(p + 28, true), xl = dv.getUint16(p + 30, true), cl = dv.getUint16(p + 32, true);
    ent[dec.decode(u8.subarray(p + 46, p + 46 + nl))] = { met: dv.getUint16(p + 10, true), tam: dv.getUint32(p + 20, true), off: dv.getUint32(p + 42, true) }; p += 46 + nl + xl + cl; }
  const ler = async nome => { const x = ent[nome]; if (!x) return null; const ini = x.off + 30 + dv.getUint16(x.off + 26, true) + dv.getUint16(x.off + 28, true), dados = u8.subarray(ini, ini + x.tam);
    if (x.met === 0) return dec.decode(dados);
    if (x.met !== 8 || typeof DecompressionStream !== 'function') throw new Error('Este navegador não abre a planilha. Use o Chrome (ou o Edge) atualizado.');
    return await new Response(new Blob([dados]).stream().pipeThrough(new DecompressionStream('deflate-raw'))).text(); };
  const xml = t => new DOMParser().parseFromString(t || '<x/>', 'application/xml'), tags = (el, n) => [...el.getElementsByTagName(n)];
  const wb = xml(await ler('xl/workbook.xml')), rels = xml(await ler('xl/_rels/workbook.xml.rels')), alvo = {};
  tags(rels, 'Relationship').forEach(r => { const t = r.getAttribute('Target') || ''; alvo[r.getAttribute('Id')] = t.startsWith('/') ? t.slice(1) : 'xl/' + t.replace(/^\.\//, ''); });
  const ss = tags(xml(await ler('xl/sharedStrings.xml')), 'si').map(si => tags(si, 't').map(t => t.textContent).join(''));
  const folhas = tags(wb, 'sheet').map(s => ({ nome: s.getAttribute('name') || '', arq: alvo[s.getAttribute('r:id') || s.getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')] }));
  const col = ref => { let c = 0; for (const ch of ref.replace(/\d+$/, '')) c = c * 26 + ch.charCodeAt(0) - 64; return c - 1; };
  const lerFolha = async f => { const d = xml(await ler(f.arq)), rows = [];
    for (const row of tags(d, 'row')) { const r = +row.getAttribute('r') - 1, L = rows[r] = [];
      for (const c of tags(row, 'c')) { const t = c.getAttribute('t'), vEl = c.getElementsByTagName('v')[0], v = vEl ? vEl.textContent : null; let x = null;
        if (t === 's') x = v == null ? null : ss[+v]; else if (t === 'inlineStr') x = tags(c, 't').map(z => z.textContent).join(''); else if (t === 'str') x = v; else if (t === 'b') x = v === '1'; else if (t === 'e') x = null; else x = v == null || v === '' ? null : +v;
        L[col(c.getAttribute('r') || 'A1')] = x; } }
    return rows; };
  return { folhas, lerFolha };
}
const gSerial = v => typeof v === 'number' && v > 36000 && v < 73100 ? new Date(Math.round((v - 25569) * 86400000)).toISOString().slice(0, 10) : '';
const gNum = v => typeof v === 'number' && isFinite(v) ? v : null;
const gTxt = v => v == null ? '' : typeof v === 'number' ? String(v) : String(v).trim();
function gCab(rows, quer, precisa) {
  for (let r = 0; r < Math.min(rows.length, 90); r++) { const L = rows[r] || [], m = {};
    L.forEach((v, c) => { if (typeof v !== 'string') return; const n = gNorm(v); for (const [k, pre] of Object.entries(quer)) if (m[k] == null && pre.some(p => n.startsWith(p))) { m[k] = c; break; } });
    if (precisa.every(k => m[k] != null)) return { r, m }; }
  return null;
}
function gLocalDe(txt) {
  const n = gNorm(txt), fil = n.includes('morumbi') ? 'Morumbi' : n.includes('analia') ? 'Anália Franco' : '', seg = n.includes('casa tom') ? 'Casa Tom' : n.includes('play') ? 'Play' : '';
  if (fil && seg) return { filial: fil, segmento: seg }; const nome = n.startsWith('bolo') ? 'Bolo' : gTxt(txt); return { filial: nome, segmento: nome };
}
async function lerPlanilha(arq) {
  const X = await lerXlsx(await arq.arrayBuffer()), O = { receita: [], despesa: [], recebimento: [], rede: [] }, achou = [];
  for (const f of X.folhas) { const n = gNorm(f.nome);
    if (!(n.startsWith('faturamento (receita') || n === 'despesas' || n.startsWith('recebimentos') || n === 'rede')) continue;
    const rows = await X.lerFolha(f);
    if (n.startsWith('faturamento')) { const c = gCab(rows, { data: ['data'], qc: ['qtde de cr', 'qtd de cr', 'criancas'], qv: ['qtde de venda', 'qtd de venda', 'vendas'], desc: ['descricao', 'sub item'], filial: ['filial'], seg: ['segmento'], valor: ['valor bruto', 'valor'], obs: ['observ', 'obs'] }, ['data', 'filial', 'seg', 'valor']);
      if (!c) continue; achou.push(f.nome);
      for (let r = c.r + 1; r < rows.length; r++) { const L = rows[r]; if (!L) continue; const d = gSerial(L[c.m.data]), v = gNum(L[c.m.valor]); if (!d || v == null || Math.abs(v) < 0.005) continue;
        O.receita.push({ _l: r + 1, data: d, qtd_criancas: gNum(L[c.m.qc]) || 0, qtd_vendas: gNum(L[c.m.qv]) || 0, descricao: gTxt(L[c.m.desc]), filial: gTxt(L[c.m.filial]), segmento: gTxt(L[c.m.seg]), valor: v, obs: c.m.obs != null ? gTxt(L[c.m.obs]) : '' }); } }
    else if (n === 'despesas') { const c = gCab(rows, { data: ['data'], desc: ['descricao'], filial: ['filial'], grupo: ['grupo'], seg: ['segmento'], valor: ['valor'], obs: ['obs'], centro: ['centro'] }, ['data', 'desc', 'grupo', 'valor']);
      if (!c) continue; achou.push(f.nome);
      for (let r = c.r + 1; r < rows.length; r++) { const L = rows[r]; if (!L) continue; const d = gSerial(L[c.m.data]), v = gNum(L[c.m.valor]); if (!d || v == null || Math.abs(v) < 0.005) continue;
        const centro = gTxt(L[c.m.centro]); O.despesa.push({ _l: r + 1, data: d, descricao: gTxt(L[c.m.desc]) || '(sem descrição)', filial: gTxt(L[c.m.filial]), grupo: gTxt(L[c.m.grupo]) || 'Custo variável', segmento: gTxt(L[c.m.seg]), valor: v, obs: c.m.obs != null ? gTxt(L[c.m.obs]) : '', centro: /^\*+$/.test(centro) ? '' : centro }); } }
    else if (n.startsWith('recebimentos')) { const c = gCab(rows, { dia: ['dia'], pix: ['pix'], dinheiro: ['dinheiro'], credito: ['credito'], debito: ['debito'], link: ['link'], local: ['local'], obs: ['obs'], fech: ['data apos'] }, ['dia', 'local', 'pix']);
      if (!c) continue; achou.push(f.nome);
      for (let r = c.r + 1; r < rows.length; r++) { const L = rows[r]; if (!L) continue; const d = gSerial(L[c.m.dia]), loc = L[c.m.local]; if (!d || typeof loc !== 'string' || !loc.trim()) continue;
        const lc = gLocalDe(loc), fech = c.m.fech != null ? gSerial(L[c.m.fech]) : '';
        for (const fo of ['pix', 'dinheiro', 'credito', 'debito', 'link']) { if (c.m[fo] == null) continue; const v = gNum(L[c.m[fo]]); if (v == null || Math.abs(v) < 0.005) continue;
          O.recebimento.push({ _l: r + 1, data: d, forma: fo, filial: lc.filial, segmento: lc.segmento, valor: v, obs: c.m.obs != null ? gTxt(L[c.m.obs]) : '', data_fech: fech }); } } }
    else if (n === 'rede') { let rm = -1; for (let r = 0; r < Math.min(rows.length, 20); r++) if ((rows[r] || []).filter(v => gSerial(v)).length >= 1 && rows[r + 1] && (rows[r + 1] || []).some(v => /morumbi|analia/.test(gNorm(v)))) { rm = r; break; }
      if (rm < 0) continue; achou.push(f.nome); const MS = rows[rm], US = rows[rm + 1];
      for (let r = rm + 2; r < rows.length; r++) { const L = rows[r]; if (!L || typeof L[0] !== 'number' || L[0] < 1 || L[0] > 31) continue;
        for (let c2 = 1; c2 < L.length; c2++) { const v = gNum(L[c2]), un = gNorm(US[c2]); if (v == null || Math.abs(v) < 0.005 || !/morumbi|analia/.test(un)) continue;
          const mref = gSerial(MS[c2]) || gSerial(MS[c2 - 1]); if (!mref) continue; const [a, m] = mref.split('-').map(Number); if (L[0] > new Date(a, m, 0).getDate()) continue;
          O.rede.push({ _l: r + 1, data: `${a}-${String(m).padStart(2, '0')}-${String(L[0]).padStart(2, '0')}`, filial: un.includes('morumbi') ? 'Morumbi' : 'Anália Franco', valor: v, obs: '' }); } } }
  }
  return { O, achou };
}
function folhaImportar() {
  let lido = null;
  const f = gFolha('Importar a planilha', `<p class="sub" style="margin:0 0 12px">Escolha a planilha do financeiro (.xlsx). O sistema lê as abas <b>Faturamento (Receita)</b>, <b>Despesas</b>, <b>Recebimentos LÍQUIDOS</b> e <b>Rede</b>; as outras (gráficos, comparativos) o sistema faz sozinho.</p>
      <input class="inp g-arq" type="file" id="g-arq" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"><div id="g-imp-res" class="g-imp"></div>
      <p class="sub" style="margin:12px 0 0">Importar de novo <b>troca</b> o que veio da planilha antes. O que foi lançado aqui no sistema continua.</p>`, {
    ok: 'Importar', fazer: async F => { if (!lido) throw new Error('Escolha a planilha primeiro.'); const tot = Object.values(lido.O).reduce((t, x) => t + x.length, 0); if (!tot) throw new Error('Não achei nenhuma linha para importar nesta planilha.');
      const lote = 'pl-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8), res = $('#g-imp-res', F); let foi = 0, erros = 0, ex = [];
      for (const tipo of Object.keys(lido.O)) for (let i = 0; i < lido.O[tipo].length; i += 500) { const r = await api('fl_importar', { lote, tipo, linhas: lido.O[tipo].slice(i, i + 500) }); foi += r.entrou; erros += r.erros; ex = ex.concat((r.exemplos || []).map(x => ({ ...x, tipo })));
        res.innerHTML = `<div class="barra"><i style="width:${Math.round((foi + erros) / tot * 100)}%"></i></div><p class="sub">Enviando… ${int(foi + erros)} de ${int(tot)}</p>`; }
      const fim = await api('fl_importar_fim', { lote }); await gSujou();
      aviso(`Planilha importada ✓ ${int(foi)} linhas${erros ? ` · ${int(erros)} não entraram` : ''}`); if (erros) setTimeout(() => alert(`${int(erros)} linhas não entraram. Exemplos:\n` + ex.slice(0, 8).map(x => `${GT[x.tipo].nome}, linha ${x.linha}: ${x.erro}`).join('\n')), 300);
      if (ABA === 'lanc') carregarLanc(); else gestaoDesenhar(ABA); } });
  $('#g-arq', f).onchange = async e => { const a = e.target.files[0], res = $('#g-imp-res', f); lido = null; if (!a) return; res.innerHTML = '<p class="sub">Lendo a planilha…</p>';
    try { lido = await lerPlanilha(a); const O = lido.O, soma = L => L.reduce((t, x) => t + x.valor, 0);
      res.innerHTML = lido.achou.length ? `<table style="margin-top:12px"><thead><tr><th>Aba</th><th class="v">Linhas</th><th class="v">Soma</th></tr></thead><tbody>${Object.keys(O).map(k => `<tr><td>${GT[k].nome}</td><td class="v">${int(O[k].length)}</td><td class="v">${brl(soma(O[k]))}</td></tr>`).join('')}</tbody></table>
        <p class="sub" style="margin-top:8px">Abas lidas: ${lido.achou.map(esc).join(', ')}. Confira e toque em <b>Importar</b>.</p>` : '<p class="erro">Não achei as abas de Faturamento, Despesas, Recebimentos ou Rede nesta planilha.</p>'; }
    catch (x) { res.innerHTML = `<p class="erro">${esc(x.message)}</p>`; } };
}

/* ---------- o relatório: as contas (as mesmas do PDF, no servidor) ---------- */
function gContas(R, mes) {
  const M = []; for (let m = 1; m <= 12; m++) M[m] = { bruto: 0, liquido: 0, desp: 0, inv: 0, rede: 0, qv: 0, qc: 0 };
  const loc = {}, sub = {}, dl = {}, cen = {}, formas = {}, rede = {}, no = m => !mes || m === mes;
  for (const [t, m, f, s, d, g, c, fo, v, n, qv, qc] of R.g) { if (m < 1 || m > 12) continue; const lk = gLocal(f, s), L = loc[lk] = loc[lk] || { local: lk, bruto: 0, liquido: 0, desp: 0 };
    if (t === 'receita') { M[m].bruto += v; M[m].qv += qv; M[m].qc += qc; if (no(m)) { L.bruto += v; const k = s + '|' + (d || 'Sem sub item'), x = sub[k] = sub[k] || { seg: s, item: d || 'Sem sub item', v: 0, qv: 0, qc: 0 }; x.v += v; x.qv += qv; x.qc += qc; } }
    else if (t === 'despesa') { const inv = g === 'Investimento' || s === 'Investimento' || f === 'Investimento'; if (inv) M[m].inv += v; else M[m].desp += v; if (no(m)) { if (!inv) { L.desp += v; const gg = g || 'Sem grupo'; dl[lk] = dl[lk] || {}; dl[lk][gg] = (dl[lk][gg] || 0) + v; } const ck = c || 'Sem centro de custo'; cen[ck] = (cen[ck] || 0) + v; } }
    else if (t === 'recebimento') { M[m].liquido += v; if (no(m)) { L.liquido += v; formas[fo] = (formas[fo] || 0) + v; } }
    else if (t === 'rede') { M[m].rede += v; if (no(m)) rede[f || 'Sem filial'] = (rede[f || 'Sem filial'] || 0) + v; } }
  const T = { bruto: 0, liquido: 0, desp: 0, inv: 0, rede: 0, qv: 0, qc: 0 }; M.forEach((x, m) => { if (!x) return; x.res = x.bruto - x.desp; x.resl = x.liquido - x.desp; if (no(m)) for (const k in T) T[k] += x[k]; });
  T.res = T.bruto - T.desp; T.resl = T.liquido - T.desp;
  const S = { 1: { bruto: 0, liquido: 0, inv: 0, desp: 0 }, 2: { bruto: 0, liquido: 0, inv: 0, desp: 0 }, 3: { bruto: 0, liquido: 0, inv: 0, desp: 0 } };
  if (mes) for (const [t, m, s, inv, v] of R.sem) { if (m !== mes || !S[s]) continue; if (t === 'receita') S[s].bruto += v; else if (t === 'recebimento') S[s].liquido += v; else if (t === 'despesa') S[s][inv ? 'inv' : 'desp'] += v; }
  const ord = o => Object.entries(o).sort((a, b) => b[1] - a[1]);
  return { M, T, loc: Object.values(loc).filter(x => x.bruto || x.liquido || x.desp).map(x => ({ ...x, res: x.bruto - x.desp })).sort((a, b) => b.bruto - a.bruto || b.desp - a.desp),
    sub: Object.values(sub).sort((a, b) => b.v - a.v), dl, cen: ord(cen), formas: ord(formas), rede: ord(rede), S };
}
async function carregarRel() {
  const n = ++GS.n; try { const r = await api('fl_rel', { ano: GS.relAno }); if (n !== GS.n) return; GS.rel = r; } catch (e) { aviso(e.message); return; }
  if (ABA === 'relat') gestaoDesenhar('relat');
}
function telaRel() {
  const R = GS.rel && GS.rel.ano === GS.relAno ? GS.rel : null, anos = R ? R.anos : [GS.relAno], mes = GS.relMes;
  const cab = `<div class="topo"><div><h1>Relatório financeiro</h1><div class="sub">${mes ? esc(gMesNome(`${GS.relAno}-${String(mes).padStart(2, '0')}`)) : 'Ano de ' + GS.relAno + ' · mês a mês'} · dos lançamentos do financeiro</div></div>
      <div class="lista-acoes"><button class="btn btn-ghost" data-gr="excel">Exportar Excel</button><button class="btn btn-brand g-novo" data-gr="pdf">Exportar PDF</button></div></div>
    <div class="filtros"><div class="chips"><select class="chip on" id="g-ano" aria-label="Ano">${[...new Set(anos.concat([GHOJE.getFullYear(), GS.relAno]))].sort((a, b) => b - a).map(a => `<option${a === GS.relAno ? ' selected' : ''}>${a}</option>`).join('')}</select>
      <button class="chip ${!mes ? 'on' : ''}" data-grm="0">Ano todo</button>${GMESES.map((m, i) => `<button class="chip ${mes === i + 1 ? 'on' : ''}" data-grm="${i + 1}">${m.slice(0, 3)}</button>`).join('')}</div></div>`;
  if (!R) return cab + '<div class="vazio">Carregando…</div>';
  if (!R.g.length) return cab + `<div class="cartao"><div class="vazio">Nada lançado em ${GS.relAno}. Lance em <b>Lançamentos</b> ou importe a planilha do financeiro.<br><button class="btn btn-ghost" style="margin-top:12px" data-ir="lanc">Ir para Lançamentos</button></div></div>`;
  const C = gContas(R, mes), T = C.T, k = (r, v, d, cls) => kpi(r, v, d, cls);
  const barras = (L, cor, tot) => { const mx = Math.max(1, ...L.map(x => x[1])); return `<div class="g-barras">${L.map(([n, v, extra]) => `<div class="g-b"><span class="g-bn" title="${esc(n)}">${esc(n)}</span><span class="g-bt"><i style="width:${Math.max(0.5, v / mx * 100)}%;background:${cor}"></i></span><span class="g-bv num">${brl(v)}${tot ? ` <small>${gPct(v, tot)}</small>` : ''}${extra || ''}</span></div>`).join('')}</div>`; };
  const tSub = C.sub.reduce((t, x) => t + x.v, 0), grs = [...new Set(Object.values(C.dl).flatMap(o => Object.keys(o)))].sort((a, b) => (GS.listas.grupo || []).indexOf(a) - (GS.listas.grupo || []).indexOf(b));
  return cab + `<div class="grade">${k('Receita bruta', brl(T.bruto), `${int(T.qv)} vendas · ${int(T.qc)} crianças`)}${k('Receita líquida', brl(T.liquido), 'o que entrou (recebimentos)')}${k('Despesas', brl(T.desp), `custo fixo + variável · investimento ${brl(T.inv)}`)}${k('Resultado', brl(T.res), `margem ${gPct(T.res, T.bruto)} · líquido ${brl(T.resl)}`, T.res < 0 ? 'alerta' : '')}</div>
    <div class="cartao"><div class="cab-cartao"><div><h2>Receitas x despesas, mês a mês</h2><p class="expl">Barras: receita bruta e despesas. Linha: o resultado. Toque num mês para ver os números.</p></div></div>
      <div class="legenda"><span><i style="background:${GCOR.bruto}"></i>Receita bruta</span><span><i style="background:${GCOR.desp}"></i>Despesas</span><span><i style="background:${GCOR.res};border-radius:50%"></i>Resultado</span></div>
      <div class="grafico" id="g-graf"></div>
      <div class="rolar" style="margin-top:12px"><table><thead><tr><th>Mês</th><th class="v">Receita bruta</th><th class="v">Líquida</th><th class="v">Despesas</th><th class="v">Investimento</th><th class="v">Resultado</th><th class="v">Margem</th></tr></thead>
        <tbody>${C.M.map((x, m) => !x || (!x.bruto && !x.desp && !x.liquido && !x.inv) ? '' : `<tr${mes === m ? ' class="g-dest"' : ''}><td>${GMESES[m - 1].replace(/^./, c => c.toUpperCase())}</td><td class="v">${brl(x.bruto)}</td><td class="v">${brl(x.liquido)}</td><td class="v">${brl(x.desp)}</td><td class="v">${brl(x.inv)}</td><td class="v${x.res < 0 ? ' g-neg' : ''}">${brl(x.res)}</td><td class="v">${gPct(x.res, x.bruto)}</td></tr>`).join('')}</tbody>
        <tfoot>${(() => { const A = C.M.reduce((a, x) => { if (x) for (const kk of ['bruto', 'liquido', 'desp', 'inv']) a[kk] += x[kk]; return a; }, { bruto: 0, liquido: 0, desp: 0, inv: 0 }); return `<tr><td>Ano</td><td class="v">${brl(A.bruto)}</td><td class="v">${brl(A.liquido)}</td><td class="v">${brl(A.desp)}</td><td class="v">${brl(A.inv)}</td><td class="v">${brl(A.bruto - A.desp)}</td><td class="v">${gPct(A.bruto - A.desp, A.bruto)}</td></tr>`; })()}</tfoot></table></div></div>
    <div class="cartao"><h2>Cada local</h2><p class="expl">O local é a unidade com o segmento (Play, Casa Tom) ou o próprio segmento (eventos, bolos…).</p>
      <div class="rolar"><table><thead><tr><th>Local</th><th class="v">Receita bruta</th><th class="v">Líquida</th><th class="v">Despesas</th><th class="v">Resultado</th><th class="v">Margem</th></tr></thead><tbody>${C.loc.map(x => `<tr><td>${esc(x.local)}<div class="barra"><i style="width:${Math.max(0.5, x.bruto / Math.max(1, C.loc[0].bruto) * 100)}%;background:${GCOR.bruto}"></i></div></td><td class="v">${brl(x.bruto)}</td><td class="v">${brl(x.liquido)}</td><td class="v">${brl(x.desp)}</td><td class="v${x.res < 0 ? ' g-neg' : ''}">${brl(x.res)}</td><td class="v">${gPct(x.res, x.bruto)}</td></tr>`).join('')}</tbody></table></div></div>
    <div class="dois"><div class="cartao"><h2>O que mais vendeu</h2><p class="expl">Os sub itens da receita, do maior para o menor.</p>${C.sub.length ? barras(C.sub.slice(0, 14).map(x => [`${x.item} · ${x.seg}`, x.v]), 'var(--brand)', tSub) : '<div class="vazio">Nenhuma receita.</div>'}
        ${C.sub.length > 14 ? `<details style="margin-top:8px"><summary class="sub" style="cursor:pointer">Ver todos (${C.sub.length})</summary><div class="rolar"><table><thead><tr><th>Segmento</th><th>Sub item</th><th class="v">Vendas</th><th class="v">Crianças</th><th class="v">Valor</th></tr></thead><tbody>${C.sub.map(x => `<tr><td>${esc(x.seg)}</td><td>${esc(x.item)}</td><td class="v">${int(x.qv)}</td><td class="v">${int(x.qc)}</td><td class="v">${brl(x.v)}</td></tr>`).join('')}</tbody></table></div></details>` : ''}</div>
      <div class="cartao"><h2>Centros de custo</h2><p class="expl">As despesas (com o investimento) por centro de custo.</p>${C.cen.length ? barras(C.cen.slice(0, 14), GCOR.desp, C.cen.reduce((t, x) => t + x[1], 0)) : '<div class="vazio">Nenhuma despesa.</div>'}</div></div>
    ${Object.keys(C.dl).length ? `<div class="cartao"><h2>Custo fixo e variável de cada local</h2><div class="rolar"><table><thead><tr><th>Local</th>${grs.map(g => `<th class="v">${esc(g)}</th>`).join('')}<th class="v">Total</th></tr></thead><tbody>${Object.entries(C.dl).sort((a, b) => Object.values(b[1]).reduce((t, x) => t + x, 0) - Object.values(a[1]).reduce((t, x) => t + x, 0)).map(([lk, o]) => `<tr><td>${esc(lk)}</td>${grs.map(g => `<td class="v">${brl(o[g] || 0)}</td>`).join('')}<td class="v">${brl(Object.values(o).reduce((t, x) => t + x, 0))}</td></tr>`).join('')}</tbody></table></div></div>` : ''}
    ${mes ? `<div class="cartao"><h2>Semana a semana</h2><p class="expl">Do dia 1 ao 7, do 8 ao 15 e do 16 ao fim do mês (como a aba da planilha).</p><div class="rolar"><table><thead><tr><th>Semana</th><th class="v">Receita bruta</th><th class="v">Líquida</th><th class="v">Investimento</th><th class="v">Despesas</th><th class="v">Resultado bruto</th><th class="v">Resultado líquido</th></tr></thead><tbody>${[1, 2, 3].map(s => { const x = C.S[s]; return `<tr><td>${['', 'Dia 1 ao 7', 'Dia 8 ao 15', 'Dia 16 ao fim'][s]}</td><td class="v">${brl(x.bruto)}</td><td class="v">${brl(x.liquido)}</td><td class="v">${brl(x.inv)}</td><td class="v">${brl(x.desp)}</td><td class="v">${brl(x.bruto - x.desp)}</td><td class="v">${brl(x.liquido - x.desp)}</td></tr>`; }).join('')}</tbody></table></div></div>` : ''}
    ${C.formas.length || C.rede.length ? `<div class="cartao"><h2>Recebimentos</h2><p class="expl">O que entrou, por forma de pagamento; e o que a Rede (maquininha) pagou, à parte.</p>${barras(C.formas.map(([f, v]) => [GFORMAS[f] || f, v]), GCOR.liquido, C.formas.reduce((t, x) => t + x[1], 0))}${C.rede.length ? `<p class="sub" style="margin:12px 0 6px">Rede (à parte, não soma no líquido)</p>${barras(C.rede, GCOR.inv)}` : ''}</div>` : ''}`;
}
function ligarRel() {
  const a = $('#g-ano'); if (a) a.onchange = () => { GS.relAno = +a.value; GS.rel = null; gGuarda(); gestaoDesenhar('relat'); };
  $$('[data-grm]').forEach(b => b.onclick = () => { GS.relMes = +b.dataset.grm; gGuarda(); gestaoDesenhar('relat'); });
  $$('[data-gr]').forEach(b => b.onclick = () => b.dataset.gr === 'pdf' ? baixarPdfRel(b) : exportarExcel(GS.relAno));
  $$('[data-ir]').forEach(b => b.onclick = () => { ABA = b.dataset.ir; desenhar(); });
  if (GS.rel && GS.rel.ano === GS.relAno && GS.rel.g.length) desenharGrafRel();
}
// o gráfico do ano: as barras (receita bruta e despesas) e a linha do resultado, com a dica ao tocar no mês
function desenharGrafRel() {
  const box = $('#g-graf'); if (!box) return; const C = gContas(GS.rel, GS.relMes), M = C.M.slice(1), vals = M.flatMap(x => [x.bruto, x.desp, x.res]);
  const W = Math.max(320, box.clientWidth || 640), H = 260, pl = 78, pr = 10, pt = 12, pb = 28, cw = (W - pl - pr) / 12, bw = Math.max(4, Math.min(22, cw * 0.3));
  const max = Math.max(1, ...vals), min = Math.min(0, ...vals), passo = (() => { const b = (max - min) / 4, p10 = Math.pow(10, Math.floor(Math.log10(Math.max(b, 1)))); return [1, 2, 2.5, 5, 10].map(m => m * p10).find(m => m >= b) || b; })();
  const topo = Math.ceil(max / passo) * passo, fundo = Math.floor(min / passo) * passo, y = v => pt + (H - pt - pb) * (topo - v) / Math.max(1, topo - fundo);
  let s = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Receitas e despesas mês a mês">`;
  for (let v = fundo; v <= topo + 0.001; v += passo) s += `<line class="grade-l" x1="${pl}" x2="${W - pr}" y1="${y(v)}" y2="${y(v)}"${Math.abs(v) < 0.001 ? ' style="stroke:var(--ink-3)"' : ''}/><text class="eixo" x="${pl - 8}" y="${y(v) + 4}" text-anchor="end">${brlCurto(v)}</text>`;
  const pts = [];
  M.forEach((x, i) => { const cx = pl + cw * i + cw / 2; if (GS.relMes === i + 1) s += `<rect x="${pl + cw * i + 2}" y="${pt}" width="${cw - 4}" height="${H - pt - pb}" rx="6" fill="var(--brand-bg)" opacity=".5"/>`;
    s += `<rect class="alvo" data-i="${i}" x="${pl + cw * i}" y="${pt}" width="${cw}" height="${H - pt - pb}"/><g data-g="${i}" style="pointer-events:none">`;
    for (const [v, cor, lado] of [[x.bruto, GCOR.bruto, -1], [x.desp, GCOR.desp, 1]]) if (v > 0.004) { const t = y(v), b = y(0), r = Math.min(4, bw / 2, b - t), x0 = lado < 0 ? cx - bw - 1 : cx + 1;
      s += `<path d="M${x0},${b} V${t + r} Q${x0},${t} ${x0 + r},${t} H${x0 + bw - r} Q${x0 + bw},${t} ${x0 + bw},${t + r} V${b} Z" fill="${cor}"/>`; }
    s += '</g>'; s += `<text class="eixo" x="${cx}" y="${H - 9}" text-anchor="middle"${GS.relMes === i + 1 ? ' style="font-weight:800;fill:var(--ink)"' : ''}>${GMESES[i].slice(0, 3)}</text>`;
    if (x.bruto > 0.004 || x.desp > 0.004) pts.push([cx, y(x.res), i]); });
  if (pts.length > 1) s += `<polyline points="${pts.map(p => p[0] + ',' + p[1]).join(' ')}" fill="none" stroke="${GCOR.res}" stroke-width="2" stroke-linejoin="round" style="pointer-events:none"/>`;
  pts.forEach(([px, py]) => { s += `<circle cx="${px}" cy="${py}" r="4.5" fill="${GCOR.res}" stroke="var(--card)" stroke-width="2" style="pointer-events:none"/>`; });
  box.innerHTML = s + '</svg><div class="dica-g" hidden></div>';
  const svg = $('svg', box), dica = $('.dica-g', box);
  const mostra = i => { const x = M[i], r = svg.getBoundingClientRect(), k2 = r.width / W; $$('g[data-g]', svg).forEach(e => e.classList.toggle('on', e.dataset.g === String(i)));
    dica.innerHTML = `<b>${GMESES[i].replace(/^./, c => c.toUpperCase())}</b><div><i style="background:${GCOR.bruto}"></i>Receita bruta: ${brl(x.bruto)}</div><div><i style="background:${GCOR.liquido}"></i>Líquida: ${brl(x.liquido)}</div><div><i style="background:${GCOR.desp}"></i>Despesas: ${brl(x.desp)}</div><div style="margin-top:4px;font-weight:700">Resultado: ${brl(x.res)}</div>`;
    dica.hidden = false; const meia = dica.offsetWidth / 2; dica.style.left = Math.min(Math.max((pl + cw * i + cw / 2) * k2, meia), r.width - meia) + 'px'; dica.style.top = Math.max(y(Math.max(x.bruto, x.desp, x.res)) * k2, 40) + 'px'; };
  $$('.alvo', svg).forEach(a => { a.addEventListener('mouseenter', () => mostra(+a.dataset.i)); a.addEventListener('click', () => mostra(+a.dataset.i)); });
  box.addEventListener('mouseleave', () => { dica.hidden = true; $$('g[data-g]', svg).forEach(e => e.classList.remove('on')); });
}
async function baixarPdfRel(b) {
  if (b) b.disabled = true;
  try { let r; try { r = await fetch('api.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Fin': '1' }, body: JSON.stringify({ acao: 'fl_pdf', ano: GS.relAno, mes: GS.relMes }) }); } catch (e) { throw new Error('Sem conexão com o servidor. Confira a internet e tente de novo.'); }
    if (!r.ok || !/pdf/.test(r.headers.get('Content-Type') || '')) { let j = {}; try { j = await r.json(); } catch (e) { /* sem JSON */ } throw new Error(j.erro || 'Não deu para gerar o PDF.'); }
    gBaixa(await r.blob(), `play-space-relatorio-financeiro-${GS.relAno}${GS.relMes ? '-' + String(GS.relMes).padStart(2, '0') : ''}.pdf`); aviso('PDF baixado ✓'); }
  catch (e) { aviso(e.message); } finally { if (b) b.disabled = false; }
}
function gBaixa(blob, nome) { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nome; document.body.appendChild(a); a.click(); setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1500); }

/* ---------- exportar em Excel: um .xlsx de verdade (feito aqui: o zip com o XML), com o resumo mês a mês e cada aba de lançamentos ---------- */
const GCRC = (() => { const t = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
const gCrc = b => { let c = 0xFFFFFFFF; for (let i = 0; i < b.length; i++) c = GCRC[(c ^ b[i]) & 0xFF] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; };
function gZip(arqs) { /* [{ nome, texto }] → Blob (zip "store", sem compressão: o Excel abre igual) */
  const enc = new TextEncoder(), partes = [], central = []; let off = 0;
  for (const a of arqs) { const nome = enc.encode(a.nome), dados = enc.encode(a.texto), crc = gCrc(dados), h = new DataView(new ArrayBuffer(30));
    h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint16(6, 0x0800, true); h.setUint32(14, crc, true); h.setUint32(18, dados.length, true); h.setUint32(22, dados.length, true); h.setUint16(26, nome.length, true);
    partes.push(new Uint8Array(h.buffer), nome, dados); const c = new DataView(new ArrayBuffer(46));
    c.setUint32(0, 0x02014b50, true); c.setUint16(4, 20, true); c.setUint16(6, 20, true); c.setUint16(8, 0x0800, true); c.setUint32(16, crc, true); c.setUint32(20, dados.length, true); c.setUint32(24, dados.length, true); c.setUint16(28, nome.length, true); c.setUint32(42, off, true);
    central.push(new Uint8Array(c.buffer), nome); off += 30 + nome.length + dados.length; }
  const tc = central.reduce((t, x) => t + x.length, 0), e = new DataView(new ArrayBuffer(22));
  e.setUint32(0, 0x06054b50, true); e.setUint16(8, arqs.length, true); e.setUint16(10, arqs.length, true); e.setUint32(12, tc, true); e.setUint32(16, off, true);
  return new Blob([...partes, ...central, new Uint8Array(e.buffer)], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
}
function gXlsx(abas) { /* abas: [{ nome, cols: [[título, largura, tipo('txt'|'data'|'brl'|'int'|'pct')]], linhas: [[...]], total: [..] }] */
  const xe = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '');
  const letra = c => { let s = ''; c++; while (c) { const r = (c - 1) % 26; s = String.fromCharCode(65 + r) + s; c = Math.floor((c - 1) / 26); } return s; };
  const serial = d => (Date.UTC(+d.slice(0, 4), +d.slice(5, 7) - 1, +d.slice(8, 10)) - Date.UTC(1899, 11, 30)) / 86400000;
  const EST = { txt: 0, data: 1, brl: 2, int: 4, pct: 5, num: 0 }, NEG = { txt: 6, data: 1, brl: 7, int: 8, pct: 9, num: 6 };
  const cel = (v, tipo, ref, neg) => { if (v == null || v === '') return '';
    if (tipo === 'data' && /^\d{4}-\d{2}-\d{2}$/.test(v)) return `<c r="${ref}" s="1"><v>${serial(v)}</v></c>`;
    if (tipo !== 'txt' && typeof v === 'number' && isFinite(v)) return `<c r="${ref}" s="${neg ? NEG[tipo] : EST[tipo]}"><v>${tipo === 'num' ? v : Math.round(v * 100) / 100}</v></c>`;
    return `<c r="${ref}" t="inlineStr"${neg ? ' s="6"' : ''}><is><t xml:space="preserve">${xe(v)}</t></is></c>`; };
  const folha = a => { const n = a.linhas.length; let x = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>`;
    a.cols.forEach((c, i) => { x += `<col min="${i + 1}" max="${i + 1}" width="${c[1]}" customWidth="1"/>`; }); x += '</cols><sheetData><row r="1">';
    a.cols.forEach((c, i) => { x += `<c r="${letra(i)}1" t="inlineStr" s="3"><is><t>${xe(c[0])}</t></is></c>`; }); x += '</row>';
    a.linhas.forEach((l, r) => { x += `<row r="${r + 2}">` + l.map((v, i) => cel(v, a.cols[i][2], letra(i) + (r + 2))).join('') + '</row>'; });
    if (a.total) x += `<row r="${n + 2}">` + a.total.map((v, i) => cel(v, a.cols[i][2], letra(i) + (n + 2), true)).join('') + '</row>';
    return x + `</sheetData>${n ? `<autoFilter ref="A1:${letra(a.cols.length - 1)}${n + 1}"/>` : ''}<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/></worksheet>`; };
  const arqs = [{ nome: '[Content_Types].xml', texto: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>${abas.map((a, i) => `<Override PartName="/xl/worksheets/sheet${i + 1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>`).join('')}</Types>` },
    { nome: '_rels/.rels', texto: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' },
    { nome: 'xl/workbook.xml', texto: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>${abas.map((a, i) => `<sheet name="${xe(a.nome.slice(0, 31))}" sheetId="${i + 1}" r:id="rId${i + 1}"/>`).join('')}</sheets></workbook>` },
    { nome: 'xl/_rels/workbook.xml.rels', texto: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">${abas.map((a, i) => `<Relationship Id="rId${i + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet${i + 1}.xml"/>`).join('')}<Relationship Id="rId${abas.length + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>` },
    { nome: 'xl/styles.xml', texto: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;R$&quot; #,##0.00;[Red]-&quot;R$&quot; #,##0.00"/><numFmt numFmtId="165" formatCode="0.0%"/></numFmts><fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF9D9D0"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="10"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/><xf numFmtId="3" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/><xf numFmtId="165" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>' }];
  abas.forEach((a, i) => arqs.push({ nome: `xl/worksheets/sheet${i + 1}.xml`, texto: folha(a) }));
  return gZip(arqs);
}
async function exportarExcel(ano) {
  aviso('Montando a planilha…');
  try { const [E, R] = await Promise.all([api('fl_exportar', { de: `${ano}-01-01`, ate: `${ano}-12-31` }), api('fl_rel', { ano })]), C = gContas(R, 0), L = E.lanc;
    const A = C.M.reduce((a, x) => { if (x) for (const k of ['bruto', 'liquido', 'desp', 'inv', 'rede']) a[k] += x[k]; return a; }, { bruto: 0, liquido: 0, desp: 0, inv: 0, rede: 0 });
    const abas = [
      { nome: 'Resumo mês a mês', cols: [['Mês', 14, 'txt'], ['Receita bruta', 16, 'brl'], ['Receita líquida', 16, 'brl'], ['Despesas', 16, 'brl'], ['Investimento', 15, 'brl'], ['Resultado', 16, 'brl'], ['Resultado líquido', 17, 'brl'], ['Margem', 10, 'pct'], ['Rede (à parte)', 15, 'brl']],
        linhas: C.M.slice(1).map((x, i) => [GMESES[i].replace(/^./, c => c.toUpperCase()), x.bruto, x.liquido, x.desp, x.inv, x.res, x.resl, x.bruto > 0.004 ? x.res / x.bruto : null, x.rede]),
        total: [`Ano ${ano}`, A.bruto, A.liquido, A.desp, A.inv, A.bruto - A.desp, A.liquido - A.desp, A.bruto > 0.004 ? (A.bruto - A.desp) / A.bruto : null, A.rede] },
      { nome: 'Cada local', cols: [['Local', 26, 'txt'], ['Receita bruta', 16, 'brl'], ['Receita líquida', 16, 'brl'], ['Despesas', 16, 'brl'], ['Resultado', 16, 'brl']], linhas: C.loc.map(x => [x.local, x.bruto, x.liquido, x.desp, x.res]) },
      { nome: 'Receitas', cols: [['Data', 12, 'data'], ['Qtde de crianças', 10, 'int'], ['Qtde de vendas', 10, 'int'], ['Sub item', 26, 'txt'], ['Filial', 18, 'txt'], ['Segmento', 18, 'txt'], ['Valor bruto', 15, 'brl'], ['Observações', 30, 'txt'], ['Lançado por', 18, 'txt']],
        linhas: L.receita.map(l => [l.data, l.qtd_criancas, l.qtd_vendas, l.descricao, l.filial, l.segmento, l.valor, l.obs, l.origem === 'planilha' ? 'planilha' : l.criado_por]), total: ['Total', L.receita.reduce((t, l) => t + l.qtd_criancas, 0), L.receita.reduce((t, l) => t + l.qtd_vendas, 0), '', '', '', L.receita.reduce((t, l) => t + l.valor, 0), '', ''] },
      { nome: 'Despesas', cols: [['Data', 12, 'data'], ['Descrição', 34, 'txt'], ['Filial', 18, 'txt'], ['Grupo de despesa', 16, 'txt'], ['Segmento', 18, 'txt'], ['Valor', 15, 'brl'], ['Obs', 26, 'txt'], ['Centro de custo', 20, 'txt'], ['Fornecedor', 24, 'txt'], ['Situação', 11, 'txt'], ['Vencimento', 12, 'data'], ['Pago em', 12, 'data'], ['Forma', 16, 'txt'], ['Lançado por', 18, 'txt']],
        linhas: L.despesa.map(l => [l.data, l.descricao, l.filial, l.grupo, l.segmento, l.valor, l.obs, l.centro, l.fornecedor, l.situacao === 'aberto' ? 'A pagar' : 'Paga', l.vencimento, l.pago_em, GFPAG[l.forma] || '', l.origem === 'planilha' ? 'planilha' : l.criado_por]), total: ['Total', '', '', '', '', L.despesa.reduce((t, l) => t + l.valor, 0), '', '', '', '', '', '', '', ''] },
      { nome: 'Recebimentos', cols: [['Data', 12, 'data'], ['Forma', 18, 'txt'], ['Filial', 18, 'txt'], ['Segmento', 18, 'txt'], ['Valor', 15, 'brl'], ['Obs', 26, 'txt'], ['Data após o fechamento', 14, 'data'], ['Lançado por', 18, 'txt']],
        linhas: L.recebimento.map(l => [l.data, GFORMAS[l.forma] || l.forma, l.filial, l.segmento, l.valor, l.obs, l.data_fech, l.origem === 'planilha' ? 'planilha' : l.criado_por]), total: ['Total', '', '', '', L.recebimento.reduce((t, l) => t + l.valor, 0), '', '', ''] },
      { nome: 'Rede', cols: [['Data', 12, 'data'], ['Filial', 18, 'txt'], ['Valor', 15, 'brl'], ['Obs', 26, 'txt'], ['Lançado por', 18, 'txt']], linhas: L.rede.map(l => [l.data, l.filial, l.valor, l.obs, l.origem === 'planilha' ? 'planilha' : l.criado_por]), total: ['Total', '', L.rede.reduce((t, l) => t + l.valor, 0), '', ''] }];
    gBaixa(gXlsx(abas), `play-space-financeiro-${ano}.xlsx`); aviso('Planilha baixada ✓'); }
  catch (e) { aviso(e.message); }
}
window.addEventListener('resize', () => { if (typeof ABA === 'undefined') return; if (ABA === 'relat' && GS.rel) desenharGrafRel(); if (ABA === 'fin' && GS.painel) gGrafDias(); });
